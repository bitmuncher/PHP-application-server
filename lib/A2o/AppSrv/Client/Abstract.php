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
 * Standalone preforking PHP application server framework
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @subpackage A2o_AppSrv_Client
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */
abstract class A2o_AppSrv_Client_Abstract
{
    /**
     * Parent instance object
     */
	protected $_parent = NULL;

    /**
     * Socket handle
     */
    public $socket = NULL;

    /**
     * Remote IP address
     */
    public $address = NULL;

    /**
     * Remote TCP port
     */
    public $port = NULL;



    /**
     * Constructor
     *
     * @param    resource   Socket handle
     * @param    string     Remote IP address
     * @param    integer    Remote TCP port
     * @return   void
     */
    public function __construct ($parent, $socket, $address, $port)
    {
    	// Parent instance object
    	$this->_parent = $parent;

    	// Client details
		$this->socket  = $socket;
		$this->address = $address;
		$this->port    = $port;
    }



    /**
     * Destructor
     *
     * @return   void
     */
    public function __destruct ()
    {
		if ($this->socket !== NULL) {
		    $this->closeConnection();
		}
    }



    /**
     * Reads given length from client socket handle
     *
     * Function is blocking until that many characters are received from client
     *
     * @param    integer   Number of bytes to read
     * @return   string
     */
    public function read ($length)
    {
		$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."($length)", 9);

		$r = socket_read($this->socket, $length, PHP_BINARY_READ);
		if ($r === false) throw new A2o_AppSrv_Client_Exception(socket_strerror(socket_last_error($this->socket)));

		return $r;
    }



    /**
     * Reads single line from client socket handle
     *
     * Reads max 16384 characters, terminates reading on \n (and \r?)
     *
     * @return   string
     */
    public function readLine ()
    {
		$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

		$r = socket_read($this->socket, 16384, PHP_NORMAL_READ);
		if ($r === false) throw new A2o_AppSrv_Client_Exception(socket_strerror(socket_last_error($this->socket)));
		$line = $r;

		return $line;
    }



    /**
     * Write data to client
     *
     * @param    string   Data to send to client
     * @return   void
     */
    public function write ($data)
    {
		$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

		$r = socket_write($this->socket, $data, strlen($data));
		if ($r === false) throw new A2o_AppSrv_Client_Exception(socket_strerror(socket_last_error($this->socket)));
    }



    /**
     * Close client connection
     *
     * Closes the client connection and resets the client data
     *
     * @return   void
     */
    public function closeConnection ()
    {
		$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

		// Close the connection
		if (is_resource($this->socket)) {
			socket_close($this->socket);
			$this->socket = NULL;
		}
    }



    /**
     * Reads the request from client
     *
     * @return   void
     */
    abstract public function readRequest ();



    /**
     * Writes the response to client
     *
     * @return   void
     */
    abstract public function writeResponse ($response);



    /**
     * Writes error to the client
     *
     * @return   void
     */
    abstract public function writeError ($errorMessage);



    /**
     * Parent method wrapper for debug messages
     */
    protected function _debug ($message, $importanceLevel=5)
    {
    	$this->_parent->__debug($message, $importanceLevel);
    }



    /**
     * Parent method wrapper for debug messages
     */
    protected function _debug_r ($var, $importanceLevel=5)
    {
    	$this->_parent->__debug_r($var, $importanceLevel);
    }


    /**
     * Parent method wrapper for log messages
     */
    protected function _log ($message)
    {
    	$this->_parent->__log($message);
    }



    /**
     * Parent method wrapper for warning messages
     */
    protected function _warning ($message)
    {
    	$this->_parent->__warning($message);
    }



    /**
     * Parent method wrapper for error messages
     */
    protected function _error ($message)
    {
    	$this->_parent->__error($message);
    }
}
