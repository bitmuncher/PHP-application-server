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
    protected $___parent = false;

    /**
     * Configuration array
     */
    protected $_config = false;

    /**
     * Stream to listen to
     */
    protected $_listenStream = false;

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
    protected $_client_allowedIpsRegex = '127\.0\.0\.1';
    protected $_client_className       = 'A2o_AppSrv_Client_Http';

    /**
     * Connected client details
     */
    protected $_client_stream          = false;
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
    public function __construct ($parent)
    {
    	$this->___parent  =  $parent;
        $this->_config    =& $this->___parent->_config;
    	$this->_masterPid =& $this->___parent->__masterPid;

    	$this->___parent->__registerMe_asWorker();
    }



    /**
     * Set the communication sockets and streams
     *
     * @return   void
     */
    public function __setSockets ($listenStream, $masterSocket_read, $masterSocket_write)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

    	$this->_listenStream         = $listenStream;
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
        $this->___configApply();

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
    private function ___configApply ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $config =& $this->_config;

        // Parse through sections
        $iniSection = 'Clients';
        $this->_client_allowedIpsRegex = $config[$iniSection]['allowed_ips_regex'];
        $this->_client_className       = $config[$iniSection]['class_name'];
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
        $this->___init_signalHandler();

        // If exists, call custom initialization method
        if (is_callable(array($this, 'init'))) $this->init();

        $this->_debug('Initialization complete');
    }



    /**
     * Install signal handlers
     *
     * @return   void
     */
    private function ___init_signalHandler ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $this->___parent->__init_signalHandler();
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

            // FIXME $this->_setStatus('working');

            $this->handleConnection($this->_client_stream, $this->_client_address, $this->_client_port);

            // FIXME $this->_setStatus('idle');
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
        $readStreams   = array($this->_listenStream);
        $writeStreams  = NULL;
        $exceptStreams = NULL;
        $r = @stream_select($readStreams, $writeStreams, $exceptStreams, 1, 0);
        if ($r == false) {
            return false;
        }

        $r = @stream_socket_accept($this->_listenStream, 0);
        if ($r === false) {
            return false;
        }
        $this->_client_stream = $r;

        // Get remote IP and port
        $r = stream_socket_get_name($this->_client_stream, true);
        if ($r === false) {
            $this->_log('Unable to get client name');
            return false;
        }
        list($this->_client_address, $this->_client_port) = explode(':', $r);

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
    protected function handleConnection ($stream, $address, $port)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Check if client address matches the regex
        if (!$this->isClientAllowed($address, $port)) {
            // Signal error to client
            $client = new A2o_AppSrv_Client_Generic($this->___parent, $stream, $address, $port);
            $client->writeError("Client not allowed: $address\n");

            // Make a log entry and close the connection
            $this->_log("Client not allowed: $address");
            $this->closeConnection();
            return;
        }

        // Create new client instance
        $client = new $this->_client_className($this->___parent, $stream, $address, $port);

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
            unset($this->_client);
            $this->_client = false;
        }

        // Unset all the variables
        $this->_client_stream  = false;
        $this->_client_address = false;
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
                $this->___parent->__exit();
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
     * This is usually called from $___parent instance.
     * It does the cleanup of worker-specific things.
     * Actual exit is done by parent instance.
     *
     * @return   void
     */
    public function __exitCleanup ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Close the listening stream
        fclose($this->_listenStream);

        // Close the client stream if there is any client active
        if (is_resource($this->_client_stream)) {
            fclose($this->_client_stream);
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
    	$this->___parent->__debug($message, $importanceLevel);
    }



    /**
     * Parent method wrapper for debug messages
     */
    protected function _debug_r ($var, $importanceLevel=5)
    {
    	$this->___parent->__debug_r($var, $importanceLevel);
    }


    /**
     * Parent method wrapper for log messages
     */
    protected function _log ($message)
    {
    	$this->___parent->__log($message);
    }



    /**
     * Parent method wrapper for warning messages
     */
    protected function _warning ($message)
    {
    	$this->___parent->__warning($message);
    }



    /**
     * Parent method wrapper for error messages
     */
    protected function _error ($message)
    {
    	$this->___parent->__error($message);
    }
}
