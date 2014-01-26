#!/usr/bin/env php

<?
include ("SendRegningLogic.php");
require ("fiken-api.php");

setlocale (LC_ALL, 'nb_NO.UTF-8');
date_default_timezone_set ('Europe/Oslo');
$today = ltrim(strftime('%d.%m.%y'));

$debug = 0;
$bill_logic = new SendRegningLogic($USER_NAME, $USER_PASSWORD, $debug, $debug);

$i = 0;
srand(2323234);
while ($i++ != 1)
{
  $price = 300;
  $member['account'] = rand()%2400283;
  $member['full_name'] = "Arne";
  $member['email'] = "lolsack@mail.com";
  $member['prodCode'] = getFikenProdCode($price);
  $member['fiken_kundenummer'] = $TEST_CUSTOMER_ID;

  $payload = generatePayLoad($member, $today);

  $result = "";
  $parameter = 'xml';

  $status = $bill_logic->post("send", "invoice", $payload, $result, $parameter);

  error_log($result, 3, "./logs/fiken-{$member['account']}-{$today}.log");
  $xmlData = simplexml_load_string ($result);

  $recipientNo = $xmlData->invoice->optional->recipientNo;

  if ($status != $OK)
  {
    echo "\nSomething went wrong\n";
    return ;
  }
}
