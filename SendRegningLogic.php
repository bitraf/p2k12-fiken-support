<?php
/*
        Copyright (C) 2009 Pal Orby, SendRegning AS. <http://www.sendregning.no/>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require ('http.php');
require("sasl.php");
include('secret.php');

class SendRegningLogic {

  // sws uses https
  private $PROTOCOL='https://';

  private $SERVER_NAME='';
  // domain
  //private $SERVER_NAME='localhost:8443'; // for internal use

  // path to sws
  private $SWS_PATH='/ws/';

  // private sws url (defined in constructor)
  private $SWS_URL;

  // sws butler name
  private $SWS_BUTLER_NAME="butler.do";

  // http client
  private $client;

  // debug 0 = none, 1 = headers and cookies, 2 = same as 1 inclusive response
  private $debug = 0;

  // XML header
  private $XML_HEADER="<?xml version=\"1.0\" encoding=\"UTF-8\"?>";

  function getResponse()
  {

    return $this->client->response;
  }

  // constructor
  function __construct($username, $password, $debug=0, $httpClientDebug=0) {

    $this->debug = $debug;
    //$this->client->debug=$httpClientDebug;

    $this->username = $username;
    $this->password = $password;
    // prepare the login url
    $authentication=(strlen($username) ? UrlEncode($username).":".UrlEncode($password)."@" : "");

    $this->SWS_URL=$this->PROTOCOL . $authentication . $this->SERVER_NAME . $this->SWS_PATH . $this->SWS_BUTLER_NAME;

    if($debug >= 1) {
      echo "SWS_URL=" . $this->SWS_URL . "\n";
    }

    $this->client = new http_class();
    $this->client->prefer_curl=1;
    $this->client->user_agent='SWS PHP Client - (httpclient - http://www.phpclasses.org/httpclient $Revision: 1.76 $';
    $this->client->follow_redirect=1;
    $this->client->authentication_mechanism='Basic';

  }

  public function get($parameters, &$result, &$acceptHeader='xml') {

    if($this->debug >= 1) {
      echo "Inside get(...), acceptHeader=" . $acceptHeader ."\n";
      echo "GET: " . $this->SWS_URL . $parameters . "\n";
    }

    // do not force multipart for get requests
    $this->client->force_multipart_form_post=0;

    // clear the arguments array
    $arguments = array();

    // get request
    $arguments["RequestMethod"]="GET";
    $this->client->request_method="GET";
    $arguments["AuthUser"] = $this->username;
    $arguments["AuthPassword"] = $this->password;

    // xml or json?
    if($acceptHeader === 'json') {
      $arguments['Headers']['Accept']='application/json';
    }

    if($this->debug >= 1) {
      echo "*** Header arguments - ($this->SWS_URL) ***\n";
      print_r($arguments);
    }

    // generate request
    $this->client->GetRequestArguments($this->SWS_URL . $parameters, $arguments);

    // open connection
    $error=$this->client->Open($arguments);

    if(!empty($error)) {
      echo "ERROR: $error\n";
      return "";
    }

    // send request
    $error=$this->client->SendRequest($arguments);

    if(!empty($error)) {
      echo "ERROR: $error\n";
      return "";
    }

    // read and parse the headers (this has to be done, so the cookies get posted back to the server?)
    $error=$this->client->ReadReplyHeaders($headers);
    if(!empty($error)) {
      echo "ERROR: $error\n";
      return "";
    }

    if($this->debug >= 1) {
      echo "\n*** Response headers - ($this->SWS_URL) ***\n";
      print_r($headers);
    }

    $this->client->ReadReplyBody($result, $this->client->content_length);

    if($this->debug == 2) {
      echo "\n*** Response body - ($this->SWS_URL) ***\n";
      echo $result . "\n";
    }

    // close connection
    $this->client->Close();

    // return http status code
    return $this->client->response_status;
  }

  public function post($action, $type, $xml, &$result, &$acceptHeader='xml', $test=true) {

    if($this->debug >= 1) {
      echo "Inside post(action=$action, type=$type, acceptHeader=$acceptHeader)\n";
      echo "\n*** Stored cookies ***\n";
      print_r($this->client->cookies);
    }

    if($test) {
      $url = $this->SWS_URL . "?action=$action&type=$type&test=true";
    }
    else {
      // we have to omitt test=true from the url when we aren't testing the implementation
      $url = $this->SWS_URL . "?action=$action&type=$type";
    }

    if($this->debug >= 1) {
      echo "Posting to this url: $url\n";
    }

    // post with multipart/form-data, not application/x-www-form-urlencoded
    $this->client->force_multipart_form_post=1;

    // clear the arguments array
    $arguments = array();

    // generate request
    $this->client->GetRequestArguments($url, $arguments);
    $arguments["RequestMethod"]="POST";

    // xml or json?
    if($acceptHeader === 'json') {
      $arguments['Headers']['Accept']='application/json';
    }

    // this is the "magic" for posting the xml as a multipart/form-data file
    $arguments["PostFiles"]=array(
      "xml"=>array(
        "Data"=>$this->XML_HEADER . $xml,
        "Name"=>"sws.xml",
        "Content-Type"=>"text/xml",
      )
    );

    $arguments['Headers']['Authorization'] = "Basic ".base64_encode($this->username . ":" . $this->password);//here
    if($this->debug >= 1) {
      echo "1*2** Header arguments - ($url) ***\n";

      print_r($arguments);
    }

    // open connection
    $this->client->Open($arguments);

    // send request
    $this->client->SendRequest($arguments);

    // read and parse the headers (this has to be done, so the cookies get posted back to the server?)
    $this->client->ReadReplyHeaders($headers);

    if($this->debug >= 1) {
      echo "\n*** Response headers - ($url) ***\n";
      print_r($headers);
    }

    $this->client->ReadReplyBody($result, $this->client->content_length);

    if($this->debug == 2) {
      echo "\n*** Response body - ($url) ***\n";
      echo $result;
    }

    // close connection
    $this->client->Close();

    // return http status code
    return $this->client->response_status;
  }
}

?>
