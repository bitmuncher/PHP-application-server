#!/usr/local/bin/php
<?php


// Set the include path
set_include_path('.' . PATH_SEPARATOR . dirname(dirname(__FILE__)).'/lib');


// Enable Zend Autoloader
require_once "Zend/Loader/Autoloader.php";
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('A2o_');


// Start the Application Server Demo
require dirname(__FILE__) . '/AppSrvDemo.php';
$AppSrvDemo = new AppSrvDemo('/opt/daemons/AppSrv/demo/demo.ini');
$AppSrvDemo->run();
