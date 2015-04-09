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

# munge post data here
$name = 'default post title';
if ( isset($_POST['title']) ) {
    $name = $_POST['title'];
} else {
    $name = $_POST['content'];
}
# linkify urls
# http://buildinternet.com/2010/05/how-to-automatically-linkify-text-with-php-regular-expressions/
function link_it($text)
{
    $text= preg_replace("/(^|[\n ])([\w]*?)((ht|f)tp(s)?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is", "$1$2<a href=\"$3\" >$3</a>", $text);
    $text= preg_replace("/(^|[\n ])([\w]*?)((www|ftp)\.[^ \,\"\t\n\r<]*)/is", "$1$2<a href=\"http://$3\" >$3</a>", $text);
    $text= preg_replace("/(^|[\n ])([a-z0-9&\-_\.]+?)@([\w\-]+\.([\w\-\.]+)+)/i", "$1<a href=\"mailto:$2@$3\">$2@$3</a>", $text);
    return($text);
}
$body = $_POST['content'];
$htmlbody = link_it($_POST['content']);
$imgurl = 'http://davemenninger.com/7867380014_2847527433_q.jpg';

# create a valid h-entry post, with open graph metadata
# http://microformats.org/wiki/h-entry
# http://ogp.me/
$htmlfile = 'micropost.html';
$htmlpost =
    "<!DOCTYPE html>\n".
    "<html lang=\"en\">\n".
    "<head>\n".
        "\t<meta charset=\"utf-8\" />\n".
        "\t<title>".$name."</title>\n".
        "\t<link rel=\"icon\" href=\"dave.png\" />\n".
        "\t<link rel=\"stylesheet\" href=\"style.css\" />\n".
	"\t<link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS for DaveMenninger.com microposts\" href=\"micropost.xml\" />\n".
        "\t<meta property=\"og:title\" content=\"".$name."\" />\n".
        "\t<meta property=\"og:description\" content=\"".$body."\" />\n".
        "\t<meta property=\"og:type\" content=\"article\" />\n".
        "\t<meta property=\"og:url\" content=\"http://davemenninger.com/micropost.html\" />\n".
        "\t<meta property=\"og:image\" content=\"".$imgurl."\" />\n".
        "\t<meta property=\"og:image:url\" content=\"".$imgurl."\" />\n".
    "</head>\n".
    "<body>\n".
        "\t<article class=\"h-entry\">\n".
            "\t\t<a class=\"u-url\" href=\"http://davemenninger.com/micropost.html\"><h1 class=\"p-name\">".$name."</h1></a>\n".
            "\t\t<p>Published by <a class=\"p-author h-card\" href=\"http://davemenninger.com/\">Dave Menninger</a>".
            " on <time class=\"dt-published\" datetime=\"".date('c')."\">".date('Y-m-d')."</time></p>\n".
            "\t\t<p class=\"p-summary\">".$htmlbody."</p>\n".
            "\t\t<div class=\"e-content\">\n".
                "\t\t\t<img class=\"u-photo\" src=\"".$imgurl."\" />\n".
                "\t\t\t<p>".$htmlbody."</p>\n".
            "\t\t</div>\n".
        "\t</article>\n".
    "</body>\n".
    "</html>\n";
file_put_contents($htmlfile,$htmlpost,LOCK_EX);

# create an rss feed with this item
# http://validator.w3.org/feed/
$rssfile = 'micropost.xml';
$rsspost =
    "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n".
    "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\" >\n".
    "<channel>\n".
	"\t<title>DaveMenninger.com microposts</title>\n".
	"\t<link>http://davemenninger.com/micropost.html</link>\n".
        "\t<atom:link href=\"http://davemenninger.com/micropost.xml\" rel=\"self\" type=\"application/rss+xml\" />\n".
	"\t<description>linkblog of Dave Menninger</description>\n".
	"\t<item>\n".
            "\t\t<title>".$name."</title>\n".
            "\t\t<link>http://davemenninger.com/micropost.html</link>\n".
            "\t\t<guid>http://davemenninger.com/micropost.html#".uniqid()."</guid>\n".
            "\t\t<description>".$body."</description>\n".
            "\t\t<pubDate>".date('r')."</pubDate>\n".
	"\t</item>\n".
    "</channel>\n".
    "</rss>\n";
file_put_contents($rssfile,$rsspost,LOCK_EX);


header($_SERVER['SERVER_PROTOCOL'] . ' 201 Created');
header('Location: '.$mysite.$htmlfile);

?>
