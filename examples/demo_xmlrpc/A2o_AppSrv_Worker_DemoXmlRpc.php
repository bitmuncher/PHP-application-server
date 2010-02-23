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

        xmlrpc_server_register_method($this->XmlRpcServer, 'ns1.doSomething',   array($this, '_xmlrpc_ns1_doSomething'));
        xmlrpc_server_register_method($this->XmlRpcServer, 'ns2.getServerTime', array($this, '_xmlrpc_ns2_getServerTime'));
        xmlrpc_server_register_method($this->XmlRpcServer, 'sayHello',          array($this, '_xmlrpc_sayHello'));

        // TODO how does this work?
        //xmlrpc_server_register_introspection_callback($this->XmlRpcServer, array($this, '_xmlrpc_introspection'));
    }



/*    public function _xmlrpc_introspection ($arg1, $arg2)
    {
        var_dump($arg1);
        return 'asdfasdf';
    }
*/



    /**
     * Just does something with arg1 and arg2.
     *
     * @param    string   Some words
     * @param    int      Some number
     * @return   string   Invalid useless comparison
     */
    public function _xmlrpc_ns1_doSomething ($method, $params)
    {
        $arg1 = $params[0];
        $arg2 = $params[1];
        return "'$arg1' != $arg2 (namespace=ns1)";
    }



    /**
     * Returns time on server
     *
     * @param    string   format supplied to date() function
     * @return   string
     */
    protected function _xmlrpc_ns2_getServerTime ($method, $params)
    {
        $format = $params[0];
        return date($format);
    }



    protected function _xmlrpc_sayHello ($method, $params)
    {
        return "Hello!";
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

        // Log
        $this->_log("Client requested call to RPC method: $client->method");

        // Construct response
    	$response = xmlrpc_server_call_method($this->XmlRpcServer, $client->requestRawXml, NULL);

    	// Send the reponse back to the client
	$client->writeResponse($response);

	// Close the client connection
	$this->closeConnection();
    }
}
