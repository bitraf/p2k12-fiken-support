<?

$OK = "200";

function getFikenProdCode($price)
{
  if ($price == 300 or $price == "300")
    return "";
  else if ($price == 500 or $price == "500")
    return "";
  else if ($price == 1000 or $price == "1000")
    return "";
}


function generatePayLoad($user, $invoiceDate)
{
  $name = $user['full_name'];
  $email = $user['email'];
  $prodCode = $user['prodCode'];
  $recipientNo = $user['fiken_kundenummer'];
  $qty = 1.00; 

  $payload = "
    <invoices>
    <invoice>
    <name>$name</name>
    <lines>
    <line>
    <prodCode>id={$prodCode}</prodCode>
    <qty>{$qty}</qty>
    </line>
    </lines>
    <optional>
    <email>$email</email>
    <invoiceDate>{$invoiceDate}</invoiceDate>";

  if ($recipientNo > 0)
    $payload .= "<recipientNo>{$recipientNo}</recipientNo>";

  $payload .= "</optional></invoice></invoices>";

  return $payload;
}

?>
