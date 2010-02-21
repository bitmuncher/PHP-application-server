<?php

class A2o_AppSrv_Worker_DemoXmlRpc extends A2o_AppSrv_Worker
{
	/**
	 * XML-RPC server instance
	 */
	protected $XmlRpcServer = NULL;



	/**
	 * Initialize the XMLRPC server
	 *
	 * @return   void
	 */
    protected function init ()
    {
		$this->XmlRpcServer = xmlrpc_server_create();
		xmlrpc_server_register_method(
	    	    $this->XmlRpcServer,
	    	    'sayHello',
	    	    array($this, '_xmlrpc_sayHello')
		);
		xmlrpc_server_register_method(
	    	    $this->XmlRpcServer,
	    	    'sayYellow',
	    	    array($this, '_xmlrpc_sayYellow')
		);
    }



    protected function _xmlrpc_sayHello ($method, $params)
    {
        $this->_log("Client called RPC method: $method()");

    	// Get the parameters array printed
    	ob_start();
    	print_r($params);
    	$params_r = ob_get_contents();
    	ob_end_clean();

    	$response  = "Hello!\n";
    	$response .= "Method: $method\n";
    	$response .= "Params: $params_r\n";
    	return array($response);
    }



    protected function _xmlrpc_sayYellow ($params)
    {
        $this->_log("Client called RPC method: $method()");

    	return "Yellow!";
    }



    protected function handleClient ($client)
    {
    	// Read request from client
    	$client->readRequest();

    	// Get request
    	$request_raw = $client->request_raw;

    	// Construct response
    	$response = xmlrpc_server_call_method($this->XmlRpcServer, $request_raw, NULL);
    	//$this->_debug_r($response);

    	// Send the reponse back to the client
	$client->writeResponse($response);

	// Close the client connection
	$this->closeConnection();
    }
}
