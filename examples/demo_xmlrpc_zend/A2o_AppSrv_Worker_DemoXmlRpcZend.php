<?php



class A2o_AppSrv_Worker_DemoXmlRpcZend extends A2o_AppSrv_Worker
{
    /**
     * XML-RPC server instance
     */
    protected $server = NULL;



	/**
	 * Initialize the Zend XMLRPC server
	 *
	 * @return   void
	 */
    protected function init ()
    {
        $this->server = new Zend_XmlRpc_Server();
        $this->server->setClass('ServiceClass_ns1', 'ns1');
        $this->server->setClass('ServiceClass_ns2', 'ns2');
        $this->server->addFunction('sayHello');
    }



    protected function _xmlrpc_sayHello ($method, $params)
    {
        //$this->_log("Client called RPC method: $method()");

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
	try {
	    $client->readRequest();
	} catch (A2o_AppSrv_Client_Exception $e) {
	    $this->_log("Error reading client request: ". $e->getMessage());
	    $this->closeConnection();
	    return;
	}

        // Log method called
        $this->_log("Client called RPC method: $client->method");

        // Handle request
        $response = $this->server->handle($client->request);

    	// Send the reponse back to the client
	$client->writeResponse($response);

	// Close the client connection
	$this->closeConnection();
    }
}



/**
 * First namespaced class
 *
 * Blablabla blablabla
 */
class ServiceClass_ns1 {
    /**
     * Just does something with arg1 and arg2.
     *
     * @param    string   Some words
     * @param    int      Some number
     * @return   string   Invalid useless comparison
     */
    public function doSomething ($arg1, $arg2)
    {
        return "'$arg1' != $arg2 (namespaces=test1)";
    }
}



/**
 * Second namespaced class
 *
 * Blablabla blablabla
 */
class ServiceClass_ns2 {
    /**
     * Returns time on server
     *
     * @param    string   format supplied to date() function
     * @return   string
     */
    public function getServerTime ($format)
    {
        return date($format);
    }
}



/**
 * Just says hello back to the client.
 * 
 * @return   string
 */
function sayHello ()
{
    return "Hello!";
}
