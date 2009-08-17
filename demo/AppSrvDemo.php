<?php

class AppSrvDemo extends A2o_AppSrv
{

    protected function worker_handleRequest ()
    {
	$this->_wp_serverResponse = $this->_wp_clientRequest . "\nI do work, you know, copying the input to the output...";
    }
}
