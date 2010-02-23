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
$method = 'system.listMethods';



// Call and evaluate
try {
    $result = $client->call($method);
} catch (Zend_Exception $e) {
    echo "Error calling server method: ". $e->getMessage() ."\n";
    exit;
}



// Output result
print_r($result);
echo "\n";
