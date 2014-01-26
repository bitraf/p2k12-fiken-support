#!/usr/bin/env php

<?

include ("secret.php");
include ("SendRegningLogic.php");
require ("fiken-api.php");

setlocale (LC_ALL, 'nb_NO.UTF-8');
date_default_timezone_set ('Europe/Oslo');

$today = ltrim(strftime('%e. %B %Y'));

if (false === pg_connect("dbname=p2k12 user=p2k12"))
{
  echo "lolsack!\n";

  exit;
}

$members_res = pg_query(<<<SQL
SELECT * FROM active_members WHERE price > 0 AND WHERE last_invoice_date < NOW() - '1 month'::INTERVAL
OR recipientNo == NULL
  ORDER BY account
SQL
  );

$bill_logic = new SendRegningLogic($user_name, $user_password, 0, 0);

$today = ltrim(strftime('%d.%m.%y'));
while ($member = pg_fetch_assoc($members_res))
{

  $member['prodCode'] = getFikenProdCode($member['price']);
  $payload = generatePayLoad($member, $today);

  $result = "";
  $parameter = 'xml';
  $status = $bill_logic->post("send", "invoice", $payload, $result, $parameter);

  error_log($result, 3, "./logs/fiken-{$member['account']}-{$today}.log");
  if ($status != $OK)
  {
    echo "\nSomething went wrong\n";
    return ;
  }
  else
  {
    // TODO: parse return from fiken and get recipientNo.
    pg_query('BEGIN');

    $xmlData = simplexml_load_string ($result);
    $fiken_kundenummer = $xmlData->invoice->optional->recipientNo;


    $update_recipientNo = "INSERT INTO members(full_name, email, account, price, fiken_kundenummer, last_invoice_date) VALUES($1, $2, $3, $4, $5, NOW());"
      if (pg_query_params($update_recipientNo,$member['full_name'],$member['email'],$member['account'],$member['price'], $fiken_kundenummer) == false)
      {
        pg_query('ROLLBACK')
          echo("Drats\n");
      }

    pg_query('COMMIT');
    echo "*** Processed {$member['account']}, {$member['full_name']}, {$member['price']}\n";
  }
}
