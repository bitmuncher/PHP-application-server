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
require_once 'A2o/AppSrv/Client/Http.php';



/**
 * Standalone preforking PHP application server framework
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @subpackage A2o_AppSrv_Client
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */
class A2o_AppSrv_Client_XmlRpc extends A2o_AppSrv_Client_Http
{
    /**
     * Raw XML-RPC request
     */
    public $request_raw = NULL;

    /**
     * Decoded XML-RPC request method
     */
    public $request_method = NULL;

    /**
     * Decoded XML-RPC request params
     */
    public $request_params = NULL;



    /**
     * Reads the HTTP request from client and decodes the XML data
     *
     * @return   void
     */
    public function readRequest ()
    {
		$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

		// Parent will do the request reading
    	parent::readRequest();

    	// Assign and decode the request
    	$this->request_raw    =& $this->requestBody;
    	$this->request_params =  xmlrpc_decode_request($this->request_raw, $this->request_method);
    }
}
