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
$imgurl = 'http://davemenninger.com/dave.png';
$summary = "this is a micropost";

# posts are stored in a json file
$jsonfile = 'microposts.json';

# the new post data is just everything from the POST plus a unix timestamp
$p = $_POST;
$p['pubTime'] = date('U');

# load the existing posts and push the new one
$j = json_decode( file_get_contents($jsonfile), true );
$j['posts'][] = $p;

# sort in reverse chronological order
usort($j['posts'], function($a,$b){ return $b['pubTime'] - $a['pubTime']; } );

# output JSON back to filesystem
file_put_contents( $jsonfile, json_encode( $j, JSON_PRETTY_PRINT ) );

# create an HTML page with the posts loaded from json
# the OGP metadata is of the top ( newest ) post
# http://microformats.org/wiki/h-entry
# http://ogp.me/
# and
# create an rss feed of items loaded from json
# http://validator.w3.org/feed/
$rssfile = 'microposts.xml';
$htmlfile = 'microposts.html';
$feedtitle = 'DaveMenninger.com microposts';
$feeddesc = 'linkblog of Dave Menninger';


$rss = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n".
    "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\" >\n".
    "<channel>\n".
	"\t<title>".$feedtitle."</title>\n".
	"\t<link>".$mysite.$htmlfile."</link>\n".
        "\t<atom:link href=\"".$mysite.$rssfile."\" rel=\"self\" type=\"application/rss+xml\" />\n".
        "\t<description>".$feeddesc."</description>\n";

$html = "<!DOCTYPE html>\n".
    "<html lang=\"en\">\n".
    "<head>\n".
        "\t<meta charset=\"utf-8\" />\n".
        "\t<title>".$name."</title>\n".
        "\t<link rel=\"icon\" href=\"dave.png\" />\n".
        "\t<link rel=\"stylesheet\" href=\"style.css\" />\n".
	"\t<link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS for ".$feedtitle."\" href=\"".$rssfile."\" />\n".
        "\t<meta property=\"og:title\" content=\"".$name."\" />\n".
        "\t<meta property=\"og:description\" content=\"".$body."\" />\n".
        "\t<meta property=\"og:type\" content=\"article\" />\n".
        "\t<meta property=\"og:url\" content=\"".$mysite.$htmlfile."\" />\n".
        "\t<meta property=\"og:image\" content=\"".$imgurl."\" />\n".
        "\t<meta property=\"og:image:url\" content=\"".$imgurl."\" />\n".
    "</head>\n".
    "<body>\n";

foreach( $j['posts'] as $i ) {
    # build one article for each post
    $html .=
        "\t<article class=\"h-entry\" id=\"".$i['pubTime']."\">\n".
            "\t\t<h2 class=\"p-name\"><a class=\"u-url\" href=\"".$mysite.$htmlfile."#".$i['pubTime']."\">".$i['title']."</a></h2>\n".
            "\t\t<h4>".date('r',$i['pubTime'])."</h4>\n".
            "\t\t<div class=\"e-content\">\n".
            "\t\t\t<p>".$i['content']."</p>\n".
            "\t\t</div>\n".
        "\t</article>\n";

    # build one item for each post
    $rss .= "\t<item>\n";
    if( $i['title'] != '' ) {
        $rss .= "\t\t<title>".$i['title']."</title>\n";
    }
    $rss .=
            "\t\t<link>".$mysite.$htmlfile."#".$i['pubTime']."</link>\n".
            "\t\t<guid>".$mysite.$htmlfile."#".$i['pubTime']."</guid>\n".
            "\t\t<description><![CDATA[".$i['content']."]]></description>\n".
            "\t\t<pubDate>".date('r',$i['pubTime'])."</pubDate>\n".
        "\t</item>\n";
}

#close up the HTML file and write it out
$html .= "</body>\n</html>\n";
file_put_contents($htmlfile,$html,LOCK_EX);

#close up the RSS file and write it out
$rss .= "</channel>\n</rss>\n";
file_put_contents($rssfile,$rss,LOCK_EX);

# send the success response back to the client
header($_SERVER['SERVER_PROTOCOL'] . ' 201 Created');
header('Location: '.$mysite.$htmlfile.'#'.$p['pubTime']);

?>
