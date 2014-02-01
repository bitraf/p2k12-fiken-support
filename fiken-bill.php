#!/usr/bin/env php

<?php

include ("SendRegningLogic.php");
require ("fiken-api.php");

setlocale (LC_ALL, 'nb_NO.UTF-8');
date_default_timezone_set ('Europe/Oslo');

if (false === pg_connect("dbname=p2k12 user=p2k12"))
{
  echo "Drats!\n";

  exit;
}

$members_res = pg_query(<<<SQL
SELECT email, full_name, price, account FROM active_members WHERE price > 0
  ORDER BY account
SQL
  );

$userName = "";
$userPassword = "";
$debug = 1;
$debug_post = 0;
$bill_logic = new SendRegningLogic($userName, $userPassword, $debug_post, $debug_post);

$today = ltrim(strftime('%d.%m.%y'));
while ($member = pg_fetch_assoc($members_res))
{
  if ($debug > 0)
    echo "---------------------------------\n";

  $error = "[STATUS]";
  $hasFikenCustomerId = FALSE;
  $billingInfo = FALSE;
  $account = $member['account'];

  $queryAccount = "SELECT * FROM fiken_faktura WHERE account=$1";
  if ($account_res = pg_query_params($queryAccount, array($member['account'])))
  {
    $billingInfo = pg_fetch_assoc($account_res);

    if ($billingInfo == FALSE)
    {
      if ($debug > 0) echo "Did not find any billing information.\n";

      $member['fiken_kundenummer'] = "";
      $hasFikenCustomerId = FALSE;
    }
    else
    {
      $hasFikenCustomerId = TRUE;
      $member['fiken_kundenummer'] = $billingInfo['fiken_kundenummer'];
      if($debug > 0) echo "Found the billing information!\n";
    }
  }

  $member['prodCode'] = getFikenProdCode($member['price']);
  $payload = generatePayLoad($member, $today);
  $result = "";
  $parameter = 'xml';

  pg_query('BEGIN');
  if ($hasFikenCustomerId)
  {
    $last_date_billed = $billingInfo['last_date_billed'];
    if($debug > 0) echo "The last date billed is {$last_date_billed}\n";
    if (strtotime($last_date_billed) < strtotime('-10 minutes'))
    {
      $updateLastDateBilled = "UPDATE fiken_faktura SET last_date_billed='NOW()' WHERE account='$account'"; 
      if (pg_query($updateLastDateBilled) == FALSE)
        $error .= "\nFailed updating last_date_billed\n";
      else
      {
        if ($debug > 0) echo "New bill for member.\n";
        $status = $bill_logic->post("send", "invoice", $payload, $result, $parameter);
      }
    }
  }
  else
  {
    if ($debug > 0) echo "Sending first invoice.\n";

    $status = $bill_logic->post("send", "invoice", $payload, $result, $parameter);

    if ($status == $OK)
    {
      $xmlData = simplexml_load_string ($result);
      $fiken_kundenummer = $xmlData->invoice->optional->recipientNo;
      $queryBillingUpdate = "INSERT INTO fiken_faktura(fiken_kundenummer, account, last_date_billed) VALUES('$fiken_kundenummer', '$account', 'NOW()')";
      if (pg_query($queryBillingUpdate) == FALSE)
        $error .= "\nFailed to insert";
    }
    else
      $error .= "\nDid not recieve correct status code from server.\n";
  }


  if ($error != "[STATUS]")
  {
    pg_query('ROLLBACK');
    echo "DRATS";
    //TODO: SEND email
    $result .= "\n{$error}";
  }
  else
  {
    pg_query('COMMIT');
    echo "*** Processed {$member['account']}, {$member['full_name']}, {$member['price']}, {$member['email']}\n";
  }

  error_log($result, 3, "./logs/fiken-{$member['account']}-{$today}.log");
}
