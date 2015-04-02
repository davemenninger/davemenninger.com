<?php

# code from: https://gist.github.com/adactio/8168e6b78da7b16a4644
# Licensed under a CC0 1.0 Universal (CC0 1.0) Public Domain Dedication
# http://creativecommons.org/publicdomain/zero/1.0/

$mysite = 'http://davemenninger.com/'; // Change this to your website.
$token_endpoint = 'https://tokens.indieauth.com/token';

function emu_getallheaders() {
    foreach($_SERVER as $name => $value) {
        if(substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    return $headers;
}

$_HEADERS = array();
foreach(emu_getallheaders() as $name => $value) {
    $_HEADERS[$name] = $value;
}

if (!isset($_HEADERS['Authorization'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 401 Unauthorized');
    echo 'Missing "Authorization" header.';
    exit;
}
if (!isset($_POST['h'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "h" value.';
    exit;
}

$options = array(
    CURLOPT_URL => $token_endpoint,
    CURLOPT_HTTPGET => TRUE,
    CURLOPT_USERAGENT => $mysite,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HEADER => FALSE,
    CURLOPT_HTTPHEADER => array(
        'Content-type: application/x-www-form-urlencoded',
        'Authorization: '.$_HEADERS['Authorization']
    )
);

$curl = curl_init();
curl_setopt_array($curl, $options);
$source = curl_exec($curl);
curl_close($curl);

parse_str($source, $values);

if (!isset($values['me'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "me" value in authentication token.';
    exit;
}
if (!isset($values['scope'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "scope" value in authentication token.';
    exit;
}
if (substr($values['me'], -1) != '/') {
    $values['me'].= '/';
}
if (substr($mysite, -1) != '/') {
    $mysite.= '/';
}
if (strtolower($values['me']) != strtolower($mysite)) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    echo 'Mismatching "me" value in authentication token.';
    exit;
}
if (!stristr($values['scope'], 'post')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    echo 'Missing "post" value in "scope".';
    exit;
}
if (!isset($_POST['content'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    echo 'Missing "content" value.';
    exit;
}

/* Everything's cool. Do something with the $_POST variables
   (such as $_POST['content'], $_POST['category'], $_POST['location'], etc.)
   e.g. create a new entry, store it in a database, whatever. */

$txtfile = 'microposts.txt';
$txtpost = $_POST['content'] ;
file_put_contents($txtfile, $txtpost."\n", FILE_APPEND | LOCK_EX);

$asciifile = 'microposts.asciidoc';
$asciipost =
    "= name\n" .
    "== content\n" .
    $_POST['content'] . "\n" .
    "== date\n" .
    date('c') . "\n" .
    "\n" ;
file_put_contents($asciifile, $asciipost."\n", FILE_APPEND | LOCK_EX);

header($_SERVER['SERVER_PROTOCOL'] . ' 201 Created');
header('Location: '.$mysite.$asciifile);

?>
