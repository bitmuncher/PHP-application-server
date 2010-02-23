#!/usr/local/bin/php
<?php



// Enable Zend Autoloader
require_once "Zend/Loader/Autoloader.php";
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('A2o_');



// Create client and
$client = new Zend_XmlRpc_Client('http://localhost:30000/');



// Which call and with what parameters
// Watch double array for parameters
$method = 'ns2.getServerTime';
$params = 'Y-m-d H:i:s';



// Call and evaluate
try {
    $result = $client->call($method, $params);
} catch (Zend_Exception $e) {
    echo "Error calling server method: ". $e->getMessage() ."\n";
    exit;
}



// Output result
print_r($result);
echo "\n";
