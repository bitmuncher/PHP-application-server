<?php
/**
 * a2o framework
 *
 * LICENSE
 *
 * This source file is subject to the GNU GPLv3 license that is
 * bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl.html
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */



/**
 * @see A2o_AppSrv_Client_Exception
 */
require_once 'A2o/AppSrv/Client/Exception.php';



/**
 * @see A2o_AppSrv_Client_Http
 */
require_once 'A2o/AppSrv/Client/XmlRpc.php';



/**
 * Xml-Rpc client which uses Zend_XmlRpc objects
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @subpackage A2o_AppSrv_Client
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */
class A2o_AppSrv_Client_XmlRpcZend extends A2o_AppSrv_Client_XmlRpc
{
    /**
     * Zend_XmlRpc_Request object
     */
    public $request = NULL;



    /**
     * Zend_XmlRpc_Response|Zend_XmlRpc_Server_Fault object
     */
    public $response = NULL;



    /**
     * Reads the HTTP request from client and decodes the XML data into
     * Zend_XmlRpc_Request object
     *
     * @return   void
     */
    public function readRequest ()
    {
	$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

	// Parent will do the request reading
	parent::readRequest();

        // Let's create an object
        $this->request = new Zend_XmlRpc_Request();
        $r = $this->request->loadXml($this->requestRawXml);
        if ($r === false) {
            throw new A2o_AppSrv_Client_Exception('Unable to create request object from XML');
        }
    }



    /**
     * Writes response to client
     *
     * @param    Zend_XmlRpc_Response|Zend_XmlRpc_Server_Fault   Response object
     * @return   void
     */
    public function writeResponse ($response)
    {
        $this->responseBody = $response->saveXml();
        parent::writeResponse($this->responseBody);
    }
}
