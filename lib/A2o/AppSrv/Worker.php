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
 * @see A2o_AppSrv_Exception
 */
require_once 'A2o/AppSrv/Exception.php';


/**
 * Standalone preforking PHP application server framework
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */
class A2o_AppSrv_Worker
{
    /**
     * Parent instance object
     */
    protected $_parent = false;

    /**
     * Parent class name
     */
    protected $_parentClassName = false;

    /**
     * Configuration array
     */
    protected $___configArray = false;

    /**
     * Configuration array
     */
    protected $___configArray_custom = false;

    /**
     * Socket to listen to
     */
    protected $_listenSocket    = false;

    /**
     * Worker process properties
     */
    protected $_workerId             = false;
    protected $_masterPid            = false;
    protected $___masterSocket_read  = false;
    protected $___masterSocket_write = false;

    /**
     * Client details
     */
    protected $_client_allowedIpsRegex = '.*';
    protected $_client_type            = 'Http';
    protected $_client_className       = 'A2o_AppSrv_Client_Http';

    /**
     * Connected client details
     */
    protected $_client_socket          = false;
    protected $_client_address         = false;
    protected $_client_port            = false;

    /**
     * Client object instance or false if no client is connected
     */
    protected $_client                 = false;



    /**
     * Constructor
     *
     * @param    object   Parent object reference
     * @param    string   Parent class name, used for ini parsing (section prefix)
     * @return   void
     */
    public function __construct ($parent, $parentClassName)
    {
    	$this->_parent          = $parent;
        $this->_parentClassName = $parentClassName;

        $this->___configArray        =& $this->_parent->__configArray;
    	$this->___configArray_custom =& $this->_parent->__configArray_custom;

    	$this->_masterPid =& $this->_parent->__masterPid;

    	$this->_parent->__registerMe_asWorker();
    }



    /**
     * Set the communication sockets
     *
     * @return   void
     */
    public function __setSockets ($listenSocket, $masterSocket_read, $masterSocket_write)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

    	$this->_listenSocket       = $listenSocket;
    	$this->___masterSocket_read  = $masterSocket_read;
    	$this->___masterSocket_write = $masterSocket_write;
    }



    /**
     * Worker process invocation method
     *
     * @return   void
     */
    public function __run ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

    	// First merge the config with internal variables
        $this->___configArray_apply();

        // Execute worker initialization
        $this->___init();

        // Start running the worker process
        $this->___run();
    }



    /**
     * Maps the configuration array variables to properties
     *
     * @return   void
     */
    private function ___configArray_apply ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $ca =& $this->___configArray;

        // Parse through sections
        $iniSection = 'Clients';
        $this->_client_allowedIpsRegex = $ca[$iniSection]['allowed_ips_regex'];
        $this->_client_type            = $ca[$iniSection]['client_type'];
        $this->_client_className       = $ca[$iniSection]['client_class_name'];
    }



    /**
     * Initialize worker process
     *
     * @return   void
     */
    private function ___init ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // If exists, call custom pre-initialization method
        if (is_callable(array($this, 'preInit'))) $this->preInit();

        // Then proceed with initialization
        $this->___init_listenSocket();
        $this->___init_signalHandler();

        // If exists, call custom initialization method
        if (is_callable(array($this, 'init'))) $this->init();

        $this->_debug('Initialization complete');
    }



    /**
     * Initialize socket
     *
     * @return   void
     */
    private function ___init_listenSocket ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Set the socket to non-blocking
        socket_set_nonblock($this->_listenSocket);

        $this->_debug('Listening socket initialization complete');
    }



    /**
     * Install signal handlers
     *
     * @return   void
     */
    private function ___init_signalHandler ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $this->_parent->__init_signalHandler();
    }



    /**
     * Main method for worker
     *
     * @return   void
     */
    private function ___run ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // If we have just entered this method, this means
        // that initialization phase is complete and we are idle
        //$this->_setStatus('idle');

        // Enter the main loop
        do {
            // Accept connection - if there is such thing waiting at the door
            if (!$this->___getNewConnection()) {
                //$this->_debug("No client waiting, sleeping");
                usleep(500000);
                continue;
            }

            //$this->_setStatus('working');

            $this->handleConnection($this->_client_socket, $this->_client_address, $this->_client_port);

            //$this->_setStatus('idle'); // FIXME
        } while (true);

        throw new A2o_AppSrv_Exception("Reached the forbidden execution branch");
    }



    /**
     * Establish connection if there is a client waiting at the door
     * Does not block the whole process
     *
     * @return   bool   True if new connection was established, false if not
     */
    private function ___getNewConnection ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Check if any client is waiting for connection
        $r = @socket_accept($this->_listenSocket);
        if (($r === false) && (@socket_last_error($this->_listenSocket) === 0)) {
            return false;
        }

        // Accept the connection
        if ($r === false) throw new A2o_AppSrv_Exception(socket_strerror(socket_last_error($this->_listenSocket)));
        $this->_client_socket = $r;

        // Get remote IP and port
        $r = socket_getpeername($this->_client_socket, $this->_client_address, $this->_client_port);
        if ($r === false) throw new A2o_AppSrv_Exception(socket_strerror(socket_last_error($this->_client_socket)));

        // Log the connection
        $this->_log("Client connected - $this->_client_address:$this->_client_port");

        return true;
    }



    /**
     * Client connection handler
     *
     * If end-user wishes to handle the clients in her own way, then this method should be overriden.
     *
     * @return   void
     */
    protected function handleConnection ($socket, $address, $port)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Check if client address matches the regex
        if (!$this->isClientAllowed($address, $port)) {
            // Signal error to client
            $client = new A2o_AppSrv_Client_Generic($this->_parent, $socket, $address, $port);
            $client->writeError("Client not allowed: $address\n");

            // Make a log entry and close the connection
            $this->_log("Client not allowed: $address");
            $this->closeConnection();
            return;
        }

        // Create new client instance
        $client = new $this->_client_className($this->_parent, $socket, $address, $port);

        // Save the client object
        $this->_client = $client;

        // Deledate the client handling to another method
        $this->handleClient($client);
    }



    /**
     * Check if client is allowed to connect
     *
     * @return   bool   True if yes, false if no
     */
    private function isClientAllowed ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Construct final regex
        $regex = "/^$this->_client_allowedIpsRegex$/";
        $this->_debug("Allowed clients regex: $regex");

        // Check client
        if (preg_match($regex, $this->_client_address)) {
            return true;
        } else {
            return false;
        }
    }



    /**
     * Handle the client request
     */
    protected function handleClient ($client)
    {
	$this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

	// Read request from client
	try {
	    $client->readRequest();
	} catch (A2o_AppSrv_Client_Exception $e) {
    	    $this->_log("Client closed the connection unexpectedly: ". $e->getMessage());
    	    $this->closeConnection();
    	    return;
	}

	// Display it
	$this->_debug("Client request START");
	$this->_debug($client->request);
	$this->_debug("Client request END");

	// Write a response
	$client->writeResponse("Hello, World!\n");
	$this->closeConnection();
    }



    /**
     * Closes the client connection and resets the client data so worker can
     * accept new connection
     */
    protected function closeConnection ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Close the connection to client
        if ($this->_client !== false) {
            $this->_client->closeConnection();
            $this->_client = false;
        }

        // Unset all the variables
        $this->_client_socket  = false;
        $this->_client_sddress = false;
        $this->_client_port    = false;

        $this->_log("Client disconnected");
        return true;
    }
    /**************************************************************************\
    |
    | EOF WORKER process methods
    |
    \**************************************************************************/





    /**
     * Signal handler for worker process
     *
     * @param    integer   Signal number
     * @return   void
     */
    public function __signalHandler ($signo)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ ."($signo)", 9);

        switch ($signo) {
            case SIGUSR1:
                $this->_debug("Caught SIGUSR1, reading master for IPC message(s?)...");
                $this->_ipc_readMaster();
                break;
            case SIGTERM:
                $this->_debug("Caught SIGTERM, running shutdown method...");
                $this->_parent->__exit();
                break;
            case SIGCHLD:
            case SIGINT:
                break;
            default:
                $this->_debug("Caught unknown signal $signo, running shitdown method...");
                $this->_exit();
        }
    }







    /**************************************************************************\
    |
    | IPC routines
    |
    \**************************************************************************/
    /**
     * Read master message
     *
     * @return   void
     */
    private function _ipc_readMaster ()
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ .'()', 9);

        $msg = socket_read($this->___masterSocket_read, 256);
        $msg = trim($msg);
        if ($msg != '') {
            $this->_debug("Received message from master: $msg", 8);
        }

        // If no message is received
        if ($msg == '') continue;

        if (preg_match('/^system::setWorkerId::([0-9]+)$/', $msg, $matches)) {
    	    $this->_wp_workerId = $matches[1];
        } else {
	    throw new A2o_AppSrv_Exception("Unknown IPC message from master: $msg");
	}
    }



    /**
     * Notify the master that we are in the state of processing the client request
     *
     * @return   void
     */
    private function _ipc_tellMaster_statusWorking ()
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ .'()', 9);

        $ipcMessage = "system::myStatus::working";
        $this->_ipc_tellMaster($ipcMessage);
    }



    /**
     * Notify the master that we are in the idle state
     *
     * @return   void
     */
    private function _ipc_tellMaster_statusIdle ()
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ .'()', 9);

        $ipcMessage = "system::myStatus::idle";
        $this->_ipc_tellMaster($ipcMessage);
    }



    /**
     * Notify the master about something
     *
     * @param    string   Message to transmit to master
     * @return   void
     */
    private function _ipc_tellMaster ($ipcMessage)
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ .'()', 9);

        $ipcMessage .= "\n";
        $this->_ipc_tellMasterRaw($ipcMessage);
    }



    /**
     * Send the message to the master and send a signal so it is surely received in a timely manner
     *
     * @param    string   Raw message to transmit to master (must end with a newline)
     * @return   void
     */
    private function _ipc_tellMasterRaw ($rawIpcMessage)
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ .'()', 9);

        $r = socket_write($this->___masterSocket_write, $rawIpcMessage, strlen($rawIpcMessage));
        if ($r === false) throw new A2o_AppSrv_Exception(socket_strerror(socket_last_error($this->___masterSocket_write)));

        posix_kill($this->_masterPid, SIGUSR1);
    }



    /**
     * Handles the graceful exit of worker process
     *
     * @return   void
     */
    public function __exit ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Close the listening socket
        socket_close($this->_listenSocket);

        // Close the client socket if there is any client active
        if (is_resource($this->_client_socket)) {
            socket_close($this->_client_socket);
        }

        // Close the IPC sockets
        socket_close($this->___masterSocket_read);
        socket_close($this->___masterSocket_write);
    }



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
