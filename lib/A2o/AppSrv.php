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
class A2o_AppSrv
{
    /**
     * Version of this framework
     */
    const version = '0.3.2';

    /**
     * Subsystem class names
     */
    protected $_className        = 'A2o_AppSrv';
    protected $_className_cli    = 'A2o_AppSrv_Cli';
    protected $_className_master = 'A2o_AppSrv_Master';
    protected $_className_worker = 'A2o_AppSrv_Worker';
    protected $_className_log    = 'A2o_AppSrv_Log';
    protected $_className_debug  = 'A2o_AppSrv_Debug';


    /**
     * Log subsystem object
     */
    protected $_log = false;

    /**
     * Debug subsystem object
     */
    protected $_debug = false;

    /**
     * CLI subsystem object
     */
    protected $_cli = false;

    /**
     * Master process subsystem object
     */
    protected $_master = false;

    /**
     * Worker process subsystem object
     */
    protected $_worker = false;


    /**
     * Current process data
     */
    protected $_whoAmI     = 'uninit';   // (uninit|cli|master|worker)
    protected $_whoAmI_log = 'uninit';   // (uninit|CLI|MASTER|worker)
    protected $_whoAmI_pid = 'uninit';   // (uninit|pid of current process)

    /**
     * Master process PID
     */
    public $__masterPid = 'uninit';   // (uninit|pid of current process)

    /**
     * Config file parsing and CLI parsing parameters from constructor - temporary storage
     */
    private   $___tmp_ini          = false;
    private   $___tmp_parseCliArgs = true;

    /**
     * Configuration data arrays
     */
	public  $__configArray        = array();   // Merged together, for "public" access
	private $_configArray_cli     = array();   // From command line arguments
	private $_configArray_ini     = array();   // From ini file or ini array passed to constructor
	private $_configArray_default = array(     // Defaults
		'Daemon' => array(
			'executable_name' => 'A2o_AppSrv',
			'work_dir'        => '/opt/daemons/A2o_AppSrv',
			'pid_file'        => '/opt/daemons/A2o_AppSrv/A2o_AppSrv.pid',
			'user'            => 'nobody',
			'group'           => 'nogroup',
		),
		'Logging' => array(
			'log_file'        => '/opt/daemons/A2o_AppSrv/A2o_AppSrv.log',
			'debug_level'     => 5,
			'debug_to_screen' => true,
		),
		'Socket' => array(
			'listen_address' => '0.0.0.0',
			'listen_port'    => 30000,
		),
		'Workers' => array(
			'min_workers'      => 2,
			'min_idle_workers' => 1,
			'max_idle_workers' => 2,
			'max_workers'      => 10,
		),
		'Clients' => array(
			'allowed_ips_regex' => '[0-9]+.[0-9]+.[0-9]+.[0-9]+',
			'client_type'       => 'Http',
			'client_class_name' => 'A2o_AppSrv_Client_Http',
		),
	);

	/**
	 * Custom configuration options read from ini file - a.k.a. unrecognised sections
	 */
	public $__configArray_custom = false;



	/**
	 * Signal handling - is something currently processing?
	 * Signal handling - stack of deferred signals
	 */
    protected $___sh_isProcessing    = false;
    protected $___sh_deferredSignals = array();



    /**
     * Constructor
     *
     * Accepts two arguments, which then passes to CLI processing unit:
     * 1. ini file path or array of ini options
     * 2. flag whether it should parse the command line arguments
     *
     * @param    (null|bool|string|array)   $ini            Process ini file
     * @param    (null|bool)                $parseCliArgs   Parse CLI arguments?
     * @return   void
     */
    public function __construct ($ini=NULL, $parseCliArgs=true)
    {
    	$this->___tmp_ini          = $ini;
    	$this->___tmp_parseCliArgs = $parseCliArgs;
    }



    /**
     * Get version
     */
    public function getVersion ()
    {
    	return self::version;
    }



    /**
     * Set the worker class
     */
    public function setClassName_worker ($className_worker)
    {
    	$this->_className_worker = $className_worker;
    }



    /**
     * Application server invocation method
     *
     * @return   void
     */
    public function run ()
	{
		// Start logging and debugging subsystems
		$this->_log   = new A2o_AppSrv_Log();
		$this->_debug = new A2o_AppSrv_Debug($this);

		// Start the CLI subsystem which parses the command line arguments and .ini file
		$this->_cli = new $this->_className_cli($this, $this->_className, $this->___tmp_ini, $this->___tmp_parseCliArgs);
    	$this->_cli->__run();

    	// Get the settings
    	$this->_configArray_cli     = $this->_cli->__configArray_cli;
    	$this->_configArray_ini     = $this->_cli->__configArray_ini;
    	$this->__configArray_custom = $this->_cli->__configArray_custom;

    	// Shut down the CLI subsystem
    	unset($this->_cli);

    	// Merge the config settings and apply them
    	$this->___configArrays_merge();
    	$this->___configArray_apply();

    	// Start the master process
		$this->_master = new $this->_className_master($this, $this->_className);
    	$this->_master->__run();

    	// If master process returns from run() method, this means it has forked itself and this is a child now
    	// Ergo - start the worker and remove the master object instance as it is redundant, but get the relevant data first
    	$tmp_listenSocket       = $this->_master->__tmp_listenSocket;
    	$tmp_masterSocket_read  = $this->_master->__tmp_masterSocket_read;
    	$tmp_masterSocket_write = $this->_master->__tmp_masterSocket_write;

    	// Shut down the master subsystem
    	unset($this->_master);

    	// Start the worker
    	$this->_worker = new $this->_className_worker($this, $this->_className);
    	$this->_worker->__setSockets($tmp_listenSocket, $tmp_masterSocket_read, $tmp_masterSocket_write);
    	$this->_worker->__run();
    }



    /**
     * Registers the given process as CLI process
     *
     * @return   void
     */
    private function ___configArrays_merge ()
    {
    	$r = $this->___array_replace_recursive($this->_configArray_default, $this->_configArray_ini, $this->_configArray_cli);
    	$this->__configArray = $r;
    }



    /**
     * Applys the appropriate configuration settings (opens logfile, enables debugging, etc)
     *
     * @return   void
     */
    private function ___configArray_apply ()
    {
    	// Enable logging
    	$this->_log->openLogFile($this->__configArray['Logging']['log_file']);

    	// Set debugging options
    	$this->_debug->setThreshold($this->__configArray['Logging']['debug_level']);
    	if ($this->__configArray['Logging']['debug_to_screen']) {
    		$this->_log->enableLogToScreen();
    		$this->_log->closeLogFile();
    	}

    	// Debug the array
    	$this->__debug("Configuration array dump START", 8);
    	$this->__debug_r($this->__configArray, 8);
    	$this->__debug("Configuration array dump END", 8);
    }



    /**
     * Registers the given process as CLI process
     *
     * @return   void
     */
    private function ___array_replace_recursive($arr1, $arr2, $arr3)
    {
    	// FIXME for PHP 5.3 replace with array_replace_recursive
    	foreach ($arr1 as $secName1 => $secData1) {
    		foreach ($secData1 as $secName2 => $secData2) {
    			if (isset($arr3[$secName1][$secName2])) {
    				$arr1[$secName1][$secName2] = $arr3[$secName1][$secName2];
    			} elseif (isset($arr2[$secName1][$secName2])) {
    				$arr1[$secName1][$secName2] = $arr2[$secName1][$secName2];
    			}
    		}
    	}
    	return $arr1;
    }



    /**
     * Registers the given process as CLI process
     *
     * @return   void
     */
    public function __registerMe_asCli ()
    {
		$this->_whoAmI     = 'cli';
		$this->_whoAmI_log = 'CLI';
		$this->_whoAmI_pid = getmypid();
    }



    /**
     * Registers the given process as MASTER process
     *
     * @return   void
     */
    public function __registerMe_asMaster ()
    {
		$this->_whoAmI     = 'master';
		$this->_whoAmI_log = 'MASTER';
		$this->_whoAmI_pid = getmypid();
		$this->__masterPid = $this->_whoAmI_pid;
    }



    /**
     * Registers the given process as MASTER process
     *
     * @return   void
     */
    public function __registerMe_asWorker ()
    {
		$this->_whoAmI     = 'worker';
		$this->_whoAmI_log = 'worker';
		$this->_whoAmI_pid = getmypid();
    }



    /**
     * Wrapper method to logging subsystem
     *
     * @param    string   Message to be logged
     * @return   void
     */
    public function __log ($message)
    {
		$messageFinal = "$this->_whoAmI_log (pid=$this->_whoAmI_pid): $message";
    	$this->_log->log($messageFinal);
    }



    /**
     * Wrapper method to logging subsystem - recursive
     *
     * @param    mixed   Any variable that desires recursive logging
     * @return   void
     */
    public function __log_r ($var)
    {
		ob_start();
		print_r($var);
		$contents = ob_get_contents();
		ob_end_clean();

    	$this->__log($contents);
    }



    /**
     * Wrapper method to debugging subsystem
     *
     * @param    string    Debugging message
     * @param    integer   Importance level
     * @return   void
     */
    public function __debug ($message, $importanceLevel=5)
    {
		$messageFinal =& $message;
    	$this->_debug->debug($messageFinal, $importanceLevel);
    }



    /**
     * Wrapper method to debugging subsystem - recursive
     *
     * @param    mixed     Any variable that desires recursive logging
     * @param    integer   Importance level
     * @return   void
     */
    public function __debug_r ($var, $importanceLevel=5)
    {
		ob_start();
		print_r($var);
		$contents = ob_get_contents();
		ob_end_clean();

    	$this->__debug($contents, $importanceLevel);
    }



    /**
     * Wrapper method to logging subsystem - prepends 'WARNING'
     *
     * @param    string    Warning message
     * @param    integer   Importance level
     * @return   void
     */
    public function __warning ($message)
    {
		$messageFinal = "WARNING: $message";
    	$this->__log($messageFinal, $importanceLevel);
    }



    /**
     * Error method, displays error message and exits current process with given $exitStatus
     *
     * @param    string    Error message to emit to log and screen
     * @param    integer   Exit status
     * @return   void
     */
    public function __error ($message, $exitStatus=1)
    {
    	$messageFinal = "ERROR: $message";

    	$this->_log->enableLogToScreen();
    	$this->__log($messageFinal);

    	$this->__exit($exitStatus);
    }



    /**
     * Install signal handlers
     *
     * @return   void
     */
    public function __init_signalHandler ()
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

		declare (ticks = 1);
		pcntl_signal(SIGTERM, array(&$this, 'signalHandler'));   // To exit
		pcntl_signal(SIGCHLD, array(&$this, 'signalHandler'));   // Child exited or died
		pcntl_signal(SIGUSR1, array(&$this, 'signalHandler'));   // Child waiting for IPC
	    pcntl_signal(SIGINT,  array(&$this, 'signalHandler'));   // Ctrl+C

		$this->__debug("Signal handlers installed");
    }


    /**
     * Signal handler for all processes - entry method
     *
     * @return   void
     */
    public function signalHandler ($signo)
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ ."($signo)", 9);

		// If we are just processing some other interrupt, store this interrupt in the array
		if ($this->___sh_isProcessing() == true) {
		    $this->___sh_addDeferredSignal($signo);
		    return;
		}

		// Notify that we are processing the interrupt currently
		$this->___sh_setFlag_processing();

		// Let us process the signal now
		switch ($this->_whoAmI) {
			case 'cli':
				$this->_cli->__signalHandler($signo);
				break;
			case 'master':
				$this->_master->__signalHandler($signo);
				break;
			case 'worker':
				$this->_worker->__signalHandler($signo);
				break;
		}

		// Notify that we have finished
		$this->___sh_unsetFlag_processing();

		// Process deferred signal - warning - recursion
		$signo = $this->___sh_getOneDeferredSignal();
		if ($signo !== false) {
			$this->signalHandler($signo);
    	}
    }



    /**
     * Check if some signal is currently being processed
     *
     * @return   bool
     */
    private function ___sh_isProcessing ()
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);
		return $this->___sh_isProcessing;
    }



    /**
     * Tell the 'world' (us) that we are processing some signal
     *
     * @return   void
     */
   	private function ___sh_setFlag_processing ()
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);
		$this->___sh_isProcessing = true;
    }



    /**
     * Tell the 'world' (us) that we are NOT processing some signal
     *
     * @return   void
     */
    private function ___sh_unsetFlag_processing ()
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);
    	$this->___sh_isProcessing = false;
    }



    /**
     * Add signal to the array of signals which should be processed in deferred mode
     *
     * @return   void
     */
    private function ___sh_addDeferredSignal ($signo)
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ ."($signo)", 9);
    	array_push($this->___sh_deferredSignals, $signo);
    }



    /**
     * Get one deferred signal from the array of deferred signals, or return false
     *
     * @return   (bool false|int)   bool false if no signal is pending, else integer of signo
     */
    private function ___sh_getOneDeferredSignal ()
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

		// If deferred signal is pending, shift one from the beginning of the array
		if (count($this->___sh_deferredSignals) > 0) {
	    	$signo = array_shift($this->___sh_deferredSignals);
	    	return $signo;
		}

		// No deferred signal pending
		return false;
    }




    /**
     * Tries to set new process status status
     *
     * @param    string   New status (init|idle|working|exiting)
     * @return   bool
     */
    private function __setProcStatus ($newProcStatus)
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

		// sanity check
		$this->__debug_r($this->_availableProcStatuses);
		if (!isset($this->_availableProcStatuses[$newProcStatus]))
		    $this->__error("Invalid new process status: $newProcStatus");
		if ($newProcStatus == 'init')
		    $this->__error("Cannot re-enter inititialization phase, newProcStatus=$newProcStatus");

		// deny setting new process status if in exiting mode
		if ($this->___procStatus == 'exiting') {
			return false;
		}

		// If currently working
		if ($this->___procStatus == 'working') {
		    if ($newProcStatus == 'idle') {
				if ($this->___nextProcStatus == 'exiting') {
				    $this->___procStatus = 'exiting';
				    $this->__exit();
			} else {
			    if ($this->_whoAmI == 'worker') $this->_ipc_tellMaster_statusIdle();
			    $this->_procStatus = 'idle';
			    return true;
			}
		    }
		    if ($newProcStatus == 'exiting') {
			$this->_nextProcStatus = 'exiting';
			return false;
		    }
		    throw new A2o_AppSrv_Exception('Forbidden place in program execution');
		}

		// If currently idle, allow any new status
		if ($this->_procStatus == 'idle') {
		    if ($this->_nextProcStatus == 'exiting') {
			$this->_procStatus = 'exiting';
			$this->_exit();
		    }
		    if ($newProcStatus == 'exiting') {
			$this->_procStatus = 'exiting';
			$this->_exit();
		    }
		    if ($newProcStatus == 'working') {
			if ($this->_whoAmI == 'worker') $this->_ipc_tellMaster_statusWorking();
			$this->_procStatus = 'working';
			return true;
		    }
		    throw new A2o_AppSrv_Exception('Forbidden place in program execution');
		}

		// If currently initializing, allow only to idle
		if ($this->_procStatus == 'init') {
		    if ($newProcStatus == 'idle') {
			$this->_procStatus = 'idle';
			return true;
		    } else {
			return false;
		    }
		}
		throw new A2o_AppSrv_Exception('Forbidden place in program execution');
    }

    /**************************************************************************\
    |
    | IPC routines
    |
    \**************************************************************************/
    /**
     * _ipc_pollWorkers
     *
     * Poll workers about their messages
     */
    private function _ipc_pollWorkers ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	foreach ($this->_mp_workers as $workerId => $worker) {
	    $msg = socket_read($worker['socket_read'], 256);
	    $msg = trim($msg);
	    if ($msg != '') {
		$this->_debug("Received message from worker $workerId: $msg", 8);
	    }

	    // If no message is received
	    if ($msg == '') continue;

	    switch ($msg) {
		case 'system::myStatus::idle':
		    $this->_mp_workers[$workerId]['status'] = 'idle';
		    break;
		case 'system::myStatus::working':
		    $this->_mp_workers[$workerId]['status'] = 'working';
		    break;
		default:
		    throw new A2o_AppSrv_Exception("Unknown IPC message from worker: $msg");
	    }
	}
    }



    /**
     * _ipc_readMaster
     *
     * Read master message
     */
    private function _ipc_readMaster ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

        $msg = socket_read($this->_wp_masterSocket_read, 256);
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
     * _ipc_tellWorker_workerId
     *
     * Tell the worker which is it's ID
     */
    private function _ipc_tellWorker_workerId ($workerId)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$ipcMessage = "system::setWorkerId::$workerId";
	$this->_ipc_tellWorker($workerId, $ipcMessage);
    }



    /**
     * _ipc_tellMaster_statusWorking
     *
     * Notify the master that we are in the state of processing the client request
     */
    private function _ipc_tellMaster_statusWorking ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$ipcMessage = "system::myStatus::working";
	$this->_ipc_tellMaster($ipcMessage);
    }



    /**
     * _ipc_tellMaster_statusIdle
     *
     * Notify the master that we are in the idle state
     */
    private function _ipc_tellMaster_statusIdle ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$ipcMessage = "system::myStatus::idle";
	$this->_ipc_tellMaster($ipcMessage);
    }



    /**
     * _ipc_tellMaster
     *
     * Notify the master about something
     */
    private function _ipc_tellMaster ($ipcMessage)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$ipcMessage .= "\n";
	$this->_ipc_tellMasterRaw($ipcMessage);
    }



    /**
     * _ipc_tellWorker
     *
     * Notify the worker about something
     */
    private function _ipc_tellWorker ($workerId, $ipcMessage)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$ipcMessage .= "\n";
	$this->_ipc_tellWorkerRaw($workerId, $ipcMessage);
    }



    /**
     * _ipc_tellMasterRaw
     *
     * Send the message to the master and send a signal so it is surely received in a timely manner
     */
    private function _ipc_tellMasterRaw ($rawIpcMessage)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$r = socket_write($this->_wp_masterSocket_write, $rawIpcMessage, strlen($rawIpcMessage));
	if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_wp_masterSocket_write)));

	posix_kill($this->_wp_masterPid, SIGUSR1);
    }



    /**
     * _ipc_tellWorkerRaw
     *
     * Send the message to the worker and send a signal so it is surely received in a timely manner
     */
    private function _ipc_tellWorkerRaw ($workerId, $rawIpcMessage)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$r = socket_write($this->_mp_workers[$workerId]['socket_write'], $rawIpcMessage, strlen($rawIpcMessage));
	if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_mp_workers[$workerId]['socket_write'])));

	posix_kill($this->_mp_workers[$workerId]['pid'], SIGUSR1);
    }
    /**************************************************************************\
    |
    | EOF IPC routines
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | SHUTDOWN methods
    |
    \**************************************************************************/
    /**
     * _exit
     *
     * Call appropriate exit method, depending on the context (cli|master|worker)
     *
     * @return   void
     */
    public function __exit ($exitStatus=0)
    {
		$this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

		switch ($this->_whoAmI) {
			case 'cli':
				$this->_cli->__exit($exitStatus);
				break;
			case 'master':
				$this->_master->__exit($exitStatus);
				break;
			case 'worker':
				$this->_worker->__exit($exitStatus);
				break;
			default:
		    	throw new A2o_AppSrv_Exception("Unknown _whoAmI: $this->_whoAmI");
		}

		// Close the logfile
		$this->__debug('Closing logfile and exiting...');
		if (($this->_whoAmI == 'cli') || ($this->_whoAmI == 'master')) {
			$this->__log("Shutdown complete (less logfile which will close now).");
			$this->__debug('--');
		}
		$this->_log->closeLogFile();

		// Do exit now
		exit($exitStatus);
    }
}
