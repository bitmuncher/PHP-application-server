#!/usr/local/bin/php
<?php

// Generate request
$contextOptions = array(
    'ssl' => array(
        'local_cert'         => 'certs/client.pem',
    ),
);
$context = stream_context_create($contextOptions);

$fp = stream_socket_client('tls://localhost:30000', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
fwrite($fp, "GET\n\n");
echo fread($fp,8192);
fclose($fp);
