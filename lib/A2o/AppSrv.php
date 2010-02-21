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
    const version = '0.5.0prealpha';



    /**
     * Subsystems objects
     */
    protected $___log    = false;
    protected $___debug  = false;
    protected $___cli    = false;
    protected $___master = false;
    protected $___worker = false;



    /**
     * Current process data
     */
    protected $_whoAmI     = 'uninit';   // (uninit|cli|master|worker)
    protected $_whoAmI_log = 'uninit';   // (uninit|CLI|MASTER|worker)
    protected $_whoAmI_pid = 'uninit';   // (uninit|pid of current process)



    /**
     * Master process PID, for workers if required
     */
    public $__masterPid = 'uninit';   // (uninit|pid of current process)



    /**
     * Config file parsing and CLI parsing parameters from constructor - temporary storage
     */
    public  $__configFile          = false;
    public  $__configSectionPrefix = 'A2o_AppSrv_';
    private $___tmp_parseCliArgs   = true;



    /**
     * Configuration data arrays
     */
    public  $__config               = array();   // Merged together, for "public" access
    private $___configArray_cli     = array();   // From command line arguments
    private $___configArray_php     = array();   // Modifications hardcoded with set*() function calls
    private $___configArray_ini     = array();   // From ini file or ini array passed to constructor
    public  $__configArray_defaults = array(     // Defaults
        'Daemon' => array(
            'daemonize'         => true,
            'executable_name'   => 'A2o_AppSrv',
            'work_dir'          => '/opt/daemons/A2o/AppSrv',
            'pid_file'          => '/opt/daemons/A2o/AppSrv/A2o_AppSrv.pid',
            'user'              => 'nobody',
            'group'             => 'nogroup',
        ),
        'Logging' => array(
            'log_file'          => '/opt/daemons/A2o/AppSrv/A2o_AppSrv.log',
            'log_level'         => 5,
        ),
        'Socket' => array(
            'listen_address'    => '0.0.0.0',
            'listen_port'       => 30000,
        ),
        'Ssl' => array(
            'enabled'           => false,
            'type'              => 'ssl',
            'cafile'            => '/opt/daemons/A2o/AppSrv/ca.crt',
            'local_cert'        => '/opt/daemons/A2o/AppSrv/server.pem',
            'passphrase'        => '',
            'verify_peer'       => false,
            'verify_depth'      => 5,
            'allow_self_signed' => true,
            'CN_match'          => '',
        ),
        'Master' => array(
            'class_name'        => 'A2o_AppSrv_Master',
        ),
        'Workers' => array(
            'class_name'        => 'A2o_AppSrv_Worker',
            'min_workers'       => 2,
            'min_idle_workers'  => 1,
            'max_idle_workers'  => 2,
            'max_workers'       => 10,
        ),
        'Clients' => array(
            'class_name'        => 'A2o_AppSrv_Client_Http',
            'allowed_ips_regex' => '127\.0\.0\.1',
        ),
    );
    public  $__configArray_comments = array(     // Comments to values
        'Daemon' => array(
            'daemonize'         => '(bool) Whether to daemonize or stay in foreground',
            'executable_name'   => '(string) Name of executable, for searching the process table',
            'work_dir'          => '(string) Working directory of a daemon',
            'pid_file'          => '(string) File to write master pid to',
            'user'              => '(string) Username under which daemon should run',
            'group'             => '(string) Group under which daemon should run',
        ),
        'Logging' => array(
            'log_file'          => '(string) File where to log messages when daemonized',
            'log_level'         => '(int) Logging verbosity, available values are 0-9 ',
        ),
        'Socket' => array(
            'listen_address'    => '(string) IP address of listening socket, or 0.0.0.0 to use all',
            'listen_port'       => '(int) Port number of listening socket',
        ),
        'Ssl' => array(
            'enabled'           => '(bool) Whether to enable SSL on listening socket',
            'type'              => '(string) Which SSL type, ssl or tls are available options',
            'cafile'            => '(string) Path to CA certificate file (PEM encoded)',
            'local_cert'        => '(string) Path to local certificate & key to use as server (PEM encoded)',
            'passphrase'        => '(string) Passphrase to unlock private key, if required',
            'verify_peer'       => '(bool) Whether to verify peer against CA certificate',
            'verify_depth'      => '(int) Allow this long certificate issuer chain depth (1-N)',
            'allow_self_signed' => '(bool) Allow self signed certs?',
            'CN_match'          => '(string) Match this string against REMOTE CN. Currently (PHP 5.2.12) it does not support wildcards in CN_match, only in remote CN. Thus if you specify CN_match=host.example.org and client presents certificate with CN=*.example.org it will be allowed to connect. On the contrary, if you specify CN_match=*.example.org and client has CN=host.example.org, it will not match. See PHP bug #51100 for more information.',
        ),
        'Master' => array(
            'class_name'        => '(string) Name of master class',
        ),
        'Workers' => array(
            'class_name'        => '(string) Name of worker class',
            'min_workers'       => '(int) When started, start this many workers and do not drop below this number of workers',
            'min_idle_workers'  => '(int) If less that min_idle_workers of idle workers is available, start spawning new workers',
            'max_idle_workers'  => '(int) If more than max_idle_workers are available, start killing them to save memory',
            'max_workers'       => '(int) Hard upper limit of workers',
        ),
        'Clients' => array(
            'class_name'        => 'Name of client class to instantiate when client connection is received',
            'allowed_ips_regex' => '(string) Regex to match IP against when client connects. Start(^) and end($) anchors are added explicitly',
        ),
    );



    /**
     * Custom configuration options read from ini file - a.k.a. unrecognised sections
     */
    private $___configArray_custom = array();



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
     * @return   void
     */
    public function __construct ($configFile=NULL)
    {
    	$this->__configFile = $configFile;
    }



    /**
     * Get version
     */
    public function getVersion ()
    {
    	return self::version;
    }



    /**
     * Enable parsing of command line arguments
     */
    public function enableParsingCliArgs ()
    {
        $this->___parseCliArgs = true;
    }



    /**
     * Disable parsing of command line arguments
     */
    public function disableParsingCliArgs ()
    {
        $this->___parseCliArgs = false;
    }



    /**
     * Set path to config file or receive array of config options
     *
     * @param    (string|array|NULL)   Array of config options or path to config file
     * @return   void
     */
    public function setConfigFile ($configFile)
    {
        // If setting config file is
        if ($configFile == NULL) {
            $this->__configFile = NULL;
            return;
        }

        // If array just assign it
        if (is_array($configFile)) {
            $this->__configFile = $configFile;
            return;
        }

        // Else check if file exists
        if (!file_exists($configFile)) {
            throw new A2o_App_Exception("Config file does not exist: $configFile");
        }
        $this->__configFile = $configFile;
    }



    /**
     * Set prefix of system config sections
     *
     * @param    string   Config section prefix
     * @return   void
     */
    public function setConfigSectionPrefix ($configSectionPrefix)
    {
        $this->__configSectionPrefix = $configSectionPrefix;
    }



    /**
     * Set the master class name
     */
    public function setClassName_master ($className)
    {
        if (!class_exists($className)) {
            throw new A2o_AppSrv_Exception("Invalid class name: $className");
        }
        //FIXME
    	$this->___className_master = $className;
    }



    /**
     * Set the worker class name
     */
    public function setClassName_worker ($className)
    {
        if (!class_exists($className)) {
            throw new A2o_AppSrv_Exception("Invalid class name: $className");
        }
        //FIXME
    	$this->___className_worker = $className;
    }



    /**
     * Application server invocation method
     *
     * @return   void
     */
    public function run ()
    {
        // Start logging and debugging subsystems
        $this->___log   = new A2o_AppSrv_Log();
        $this->___debug = new A2o_AppSrv_Debug($this);

        // Start the CLI subsystem which parses the command line arguments and .ini file
        $this->___cli = new A2o_AppSrv_Cli($this, $this->___tmp_parseCliArgs);
    	$this->___cli->__run();

    	// Get the settings
    	$this->___configArray_cli    = $this->___cli->__configArray_cli;
    	$this->___configArray_ini    = $this->___cli->__configArray_ini;
    	$this->___configArray_custom = $this->___cli->__configArray_custom;

    	// Shut down the CLI subsystem
//    	unset($this->_cli);

    	// Merge the config settings and apply them
    	$this->___configArrays_merge();
    	$this->___configArray_apply();

    	// Start the master process
	$this->___master = new $this->_config['Master']['class_name']($this);
    	$this->___master->__run();

    	// If master process returns from run() method, this means it has forked itself and this is a child now
    	// Ergo - start the worker and remove the master object instance as it is redundant, but get the relevant data first
    	$tmp_listenStream       = $this->___master->__tmp_listenStream;
    	$tmp_masterSocket_read  = $this->___master->__tmp_masterSocket_read;
    	$tmp_masterSocket_write = $this->___master->__tmp_masterSocket_write;

    	// Shut down the master subsystem
    	unset($this->___master);

    	// Start the worker
    	$this->___worker = new $this->_config['Workers']['class_name']($this);
    	$this->___worker->__setSockets($tmp_listenStream, $tmp_masterSocket_read, $tmp_masterSocket_write);
    	$this->___worker->__run();

        // This should never occur
        throw new A2o_AppSrv_Exception('Invalid execution branch');
    }



    /**
     * Registers the given process as CLI process
     *
     * @return   void
     */
    private function ___configArrays_merge ()
    {
        $this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

    	$this->_config = $this->___array_replace_recursive(
            $this->___configArray_custom,
            $this->__configArray_defaults,
            $this->___configArray_ini,
            $this->___configArray_php,
            $this->___configArray_cli
        );
    }



    /**
     * Applys the appropriate configuration settings (opens logfile, enables debugging, etc)
     *
     * @return   void
     */
    private function ___configArray_apply ()
    {
        $this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

    	// Set logging and debugging options
    	if ($this->_config['Daemon']['daemonize'] == true) {
            $this->___log->openLogFile($this->_config['Logging']['log_file']);
    	} else {
            $this->___log->enableLogToScreen();
        }
    	$this->___debug->setThreshold($this->_config['Logging']['log_level']);

        // Dump config array
    	$this->__debug("Configuration array dump START", 8);
    	$this->__debug_r($this->_config, 8);
    	$this->__debug("Configuration array dump END", 8);
    }



    /**
     * Registers the given process as CLI process
     *
     * @return   void
     */
    private function ___array_replace_recursive()
    {
        // FIXME for PHP 5.3 replace with array_replace_recursive

        $retArray = array();
        for ($i=0 ; $i<func_num_args() ; $i++) {
            $arr = func_get_arg($i);
            foreach ($arr as $secName => $secData) {
                if (!isset($retArray[$secName])) {
                    $retArray[$secName] = array();
                }

                foreach ($secData as $option => $value) {
                    $retArray[$secName][$option] = $value;
                }
            }
        }

    	return $retArray;
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
        $this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

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
        $this->__debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

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
    	$this->___log->log($messageFinal);
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
    	$this->___debug->debug($messageFinal, $importanceLevel);
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
    	$this->__log($messageFinal);
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

    	$this->___log->enableLogToScreen();
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
                $this->___cli->__signalHandler($signo);
                break;
            case 'master':
                $this->___master->__signalHandler($signo);
                break;
            case 'worker':
                $this->___worker->__signalHandler($signo);
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
     * __exit
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
                $this->___cli->__exitCleanup();
                break;
            case 'master':
                $this->___master->__exitCleanup();
                break;
            case 'worker':
                $this->___worker->__exitCleanup();
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
        $this->___log->closeLogFile();

        // Do exit now
        exit($exitStatus);
    }
}
