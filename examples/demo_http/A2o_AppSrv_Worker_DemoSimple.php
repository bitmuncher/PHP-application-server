<?php

class A2o_AppSrv_Worker_DemoSimple extends A2o_AppSrv_Worker
{
    protected function handleClient ($client)
    {
        // Read request from client
	try {
    	    $client->readRequest();
	} catch (A2o_AppSrv_Client_Exception $e) {
    	    $this->_log("Client closed the connection unexpectedly: ". $e->getMessage());
    	    $this->closeConnection();
    	    return;
	}

    	// Get request
    	$request = $client->request;

    	// Construct response
    	$response = $request ."\n\nI do work, you know, copying the input to the output...\n";

    	// Send the reponse back to the client
	$client->writeResponse($response);

	// Close the client connection
	$this->closeConnection();
    }
}
