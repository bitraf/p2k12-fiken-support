<?
require_once('Mail.php');
require_once('Mail/mime.php');

function email_for_debug($content)
{

  $today = ltrim(strftime('%e. %B %Y'));

  $secret = file_get_contents('');

  $smtp = Mail::factory('smtp', array ('host' => 'localhost', 'auth' => false));

  $subject = "[INFO] Fakturering med Fiken";

  ob_start();
  printf ("Dato: %s\n", $today);
  printf ("%s\n", $content);
  $body = ob_get_contents();
  ob_end_clean();

  $html_body = htmlentities($body, ENT_QUOTES, 'utf-8');
  $html_body = "<html><body><pre>$html_body</pre></body></html>";
  $headers = array ('Subject' => $subject);
  $headers['From'] = "";
  $headers['To'] = "";
  $headers['Content-Transfer-Encoding'] = '8bit';
  $headers['Content-Type'] = 'text/plain; charset="UTF-8"';

  $message = new Mail_mime("\n");
  $message->setTXTBody($body);
  $message->setHTMLBody($html_body);
  $body = $message->get(array('text_charset' => 'utf-8', 'head_charset' => 'utf-8'));
  $headers = $message->headers($headers);

  $mail_result = $smtp->send('', $headers, $body);

  if ($mail_result !== TRUE)
  {
    printf ("Failed to send e-mail: %s\n", $mail_result->getMessage());
  }
}
?>
