<?php

// Generate request
$request = xmlrpc_encode_request("sayHello", array(1, 2, 3));

// Create connection context
$context = stream_context_create(
    array(
	'http' => array(
	    'method' => "POST",
	    'header' => "Content-Type: text/xml",
	    'content' => $request,
	)
    )
);

// Get the response
$file = file_get_contents("http://127.0.0.1:30000/", false, $context);

// Decode and display
$response = xmlrpc_decode($file);
if (xmlrpc_is_fault($response)) {
    trigger_error("xmlrpc: $response[faultString] ($response[faultCode])");
} else {
    print_r($response);
}
?>
