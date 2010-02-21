#!/usr/local/bin/php
<?php


$options = array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSLCERT        => 'certs/client.pem',
    CURLOPT_VERBOSE        => 0,
);

//while (true) {

$ch = curl_init('https://localhost:30000/');
curl_setopt_array($ch,$options);
$content = curl_exec($ch);

echo $content;
//}