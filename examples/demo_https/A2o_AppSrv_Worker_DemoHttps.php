<?php

class A2o_AppSrv_Worker_DemoHttps extends A2o_AppSrv_Worker
{
    protected function handleClient ($client)
    {



	// Buggy PHP: 
	//
	// Conditions:
	// - if verify_peer option is given and
	// - If previous client used identity certificate and 
	// - current client does not provide identity certificate,
	//
	// Resulting anomaly
	// - cert resource and CN remain here until there is an error reading from current client
	// - error reading request definitely occurs
	//
	$this->_log('Probably invalid client CN: ' . $client->sslCN);



        // Read request from client
	try {
	    $client->readRequest();
	} catch (A2o_AppSrv_Client_Exception $e) {
	    $this->_log("Client closed the connection unexpectedly: ". $e->getMessage());
	    $this->closeConnection();
	    return;
	}


	// If we are still here, then this client CN is valid
	$this->_log('Client CN: ' . $client->sslCN);


    		    	    		
    	// Get request
    	$request = $client->requestRaw;

    	// Construct response
    	$response = "Your name is: $client->sslCN\n";

    	// Send the reponse back to the client
	$client->writeResponse($response);

	// Close the client connection
	$this->closeConnection();
    }
}
