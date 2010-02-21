#!/usr/local/bin/php
<?php



// Set the include path
set_include_path('.' . PATH_SEPARATOR . dirname(dirname(dirname(__FILE__))).'/lib' . PATH_SEPARATOR . get_include_path());

// Enable Zend Autoloader
require_once "Zend/Loader/Autoloader.php";
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('A2o_');



// Instantiate the application server object
$AppSrvDemo = new A2o_AppSrv('/opt/daemons/AppSrv/demos/demo_http/demo.ini');

// Set the worker class
require dirname(__FILE__) . '/A2o_AppSrv_Worker_DemoSimple.php';
$AppSrvDemo->setClassName_worker('A2o_AppSrv_Worker_DemoSimple');

// Run the application server
$AppSrvDemo->run();
