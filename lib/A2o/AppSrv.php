<?php
/**
 * A2o Framework
 *
 * LICENSE
 *
 * ...
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    FIXME
 */


/**
 * @category   A2o
 * @package    A2o_AppSrv
 */
class A2o_AppSrv
{
    /**************************************************************************\
    |
    | General settings
    |
    \**************************************************************************/
    // Metadata
    protected $_version = '0.1.0';

    // Data of current process
    protected $_whoAmI   = false;    // cli/master/worker
    protected $_myPid    = false;    // pid of current process
    
    // Process status
    protected $_procStatus            = 'init';   // current process status
    protected $_nextProcStatus        = false;    // schedule next process status
    protected $_availableProcStatuses = array(    // array of possible statuses -> if interruptible?
	'init'    => false,
	'idle'    => true,
	'working' => false,
	'exiting' => false,
    );
    
    // Config file parsing
    protected $_iniFile             = '/opt/daemons/AppSrv/AppSrv.ini';
    protected $_parseIniFile        = true;
    protected $_iniConfigArray      = false;
    protected $_parseIniConfigArray = true;
    
    // CLI arguments parsing
    protected $_parseCliArgs = true;
    protected $_cliArgsArray = true; // CHECK used?
    
    // Debugging
    protected $_debugEnabled = false;
    protected $_debugLevel   = 5;

    // General daemon options
    protected $_appTitle       = 'AppSrv';
    protected $_workDir        = '/opt/daemons/AppSrv';
    protected $_pidFile        = '/opt/daemons/AppSrv/AppSrv.pid';
    protected $_pidFileCreated = false;

    // Logging options
    protected $_logFile   = '/opt/daemons/AppSrv/AppSrv.log';
    protected $_logLevel  = 5;
    protected $_logStream = false;

    // Socket options
    protected $_listenAddress   = '0.0.0.0';
    protected $_listenPort      = 30000;
    protected $_listenSocket    = false;
    protected $_allowedIpsRegex = '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+';

    // Daemon options
    protected $_mp_minWorkers     = 3;
    protected $_mp_minIdleWorkers = 1;
    protected $_mp_maxIdleworkers = 2;   //TODO
    protected $_mp_maxWorkers     = 10;
    
    // Pool of workers
    protected $_mp_workers        = array();

    // Worker process properties
    private $_wp_parentPid            = false;
    private $_wp_childId              = false;   // TODO how to get this?
    private $_wp_clientSocket         = false;
    private $_wp_clientAddress        = false;
    private $_wp_clientPort           = false;
    private $_wp_clientRequestHeaders = false;
    private $_wp_clientRequestBody    = false;
    private $_wp_appServerResponse    = false;
    protected $worker_writeBanner     = true;
    /**************************************************************************\
    |
    | EOF General settings
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | Constructor
    |
    \**************************************************************************/
    /**
     * Constructor
     *
     * @param    (null|bool|string|array)   $ini            Process ini file
     * @param    (null|bool)                $parseCliArgs   Parse CLI arguments?
     * @return   void
     */
    public function __construct ($ini=NULL, $parseCliArgs=true)
    {
	// Config file parsing?	
	if ($ini === NULL) {
	    // Defaults
	} elseif ($ini === false) {                // Do not parse the config file
	    $this->_parseIniFile        = false;
	    $this->_parseIniConfigArray = false;
	} elseif ($ini === true) {                 // Parse the default config file
	    $this->_parseIniFile        = true;
	    $this->_parseIniConfigArray = true;
	} elseif (is_string($ini)) {               // Parse THIS config file
	    $this->_iniFile             = $ini;
	    $this->_parseIniFile        = true;
	    $this->_parseIniConfigArray = true;
	} elseif (is_array($ini)) {                // Load THIS config array
	    $this->_iniFile             = NULL;
	    $this->_parseIniFile        = false;
	    $this->_iniConfigArray      = $ini;
	    $this->_parseIniConfigArray = true;
	} else {
	    throw new A2o_AppSrv_Exception('ERR_CONSTRUCTOR_PARAM_ini_INVALID');
	}

	// CLI arguments parsing?	
	if ($parseCliArgs === NULL) {
	    // Defaults
	} elseif ($parseCliArgs === false) {
	    $this->_parseCliArgs = false;
	} elseif ($parseCliArgs === true) {
	    $this->_parseCliArgs = true;
	} else {
	    throw new A2o_AppSrv_Exception('ERR_CONSTRUCTOR_PARAM_parseCliArgs_INVALID');
	}
    }
    /**************************************************************************\
    |
    | EOF Constructor
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | Startup 
    |
    \**************************************************************************/
    /*
    * Application server invocation method
    *
    * @return   void? TODO
    */
    public function run ()
    {
	// CLI process
	$this->_cliProcess_init();
	$this->_cliProcess_parseCliArgs();
	$this->_cliProcess_parseIniFile();
	$this->_cliProcess_parseIniConfigArray();
	$this->_cliProcess_initLogStream();

	// If debugging is enabled, do not fork, just init the CLI->MASTER
	if ($this->_debugEnabled) {
	    $this->_debug("Debug mode enabled, so not forking, just silently initializing as master");
	} else {
	    // Fork and exit to daemonize
	    $pid = pcntl_fork();
	    if ($pid == -1)
		$this->_cliProcess_error('Could not fork');
	    if ($pid) {
		// This is the CLI process
		$this->_debug("Saying goodbye to pid $pid and exiting...");
		// TODO, THINK wait for successful init and return correct exit status
		exit;
	    }
	}
	
	// Here is the child which is a daemon now - master process
	$this->_masterProcess_init();
	$this->_masterProcess_initWorkDir();
	$this->_masterProcess_initPidFile();
	$this->_masterProcess_initSocket();
	$this->_masterProcess_installSignalHandlers();
	
	// Start running the master process
	$this->_masterProcess_run();
    }
    /**************************************************************************\
    |
    | EOF Startup
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | CLI process methods
    |
    \**************************************************************************/
    /**
     * Initialization method
     *
     * @return   void
     */
    private function _cliProcess_init ()
    {
	$this->_whoAmI   = 'cli';
	$this->_myPid    = getmypid();
	$this->_myStatus = 'init';
    }


    
    /**
     * Parse command line arguments
     *
     * @return   void
     */
    private function _cliProcess_parseCliArgs ()
    {
	// Check if parsing CLI arguments is explicitly disabled by constructor parameter
	if ($this->_parseCliArgs !== true) {
	    return;
	}

	// These short options are available	
	$shortOpts  = '';
	$shortOpts .= 'd::';
	$shortOpts .= 'i:';
	$shortOpts .= 'h';
	$shortOpts .= 'v';

	// These long options available - FIXME
	$longOpts   = array(
	    'debug::',
	    'ini:',
	    'help',
	    'version',
	);

	// Parse CLI arguments
	// FIXME add support for long options
	//$cliArgs = getopt($shortOpts, $longOpts);
	$cliArgs = getopt($shortOpts);

	// Should we display help?
	if (isset($cliArgs['h'])) {
	    $this->_cliProcess_displayHelp();
	}

	// Should we display version information?
	if (isset($cliArgs['v'])) {
	    $this->_cliProcess_displayVersion();
	}
	
	// Shall we enable debugging?
	if (isset($cliArgs['d'])) {
	    $this->_debugEnabled = true;
	    if (($cliArgs['d'] !== false) && preg_match('/^[1-9]$/', $cliArgs['d'])) {
		$this->_debugLevel = $cliArgs['d'];
	    }
	}

	// Is ini file specified?
	if (isset($cliArgs['i']) && ($cliArgs['i'] !== false)) {
	    $this->_parseIniFile        = true;
	    $this->_iniFile             = $cliArgs['i'];
	    $this->_parseIniConfigArray = true;
	}
    }
    

    
    /**
     * Displays help and exits
     */
    private function _cliProcess_displayHelp ()
    {
	echo "Usage: " . $_SERVER['PWD'] . substr($_SERVER['SCRIPT_FILENAME'], 1) . " [OPTION]...\n";
	echo "Starts application server.\n";
	echo "\n";
	echo "Mandatory arguments to long options are mandatory for short options too.\n";
	echo "  -d, --debug[=LEVEL]          enable debugging. LEVEL specifies verbosity\n";
	echo "                                 between 1 (lowest) and 9 (highest)\n";
	echo "  -i, --ini=FILE               use ini file FILE\n";
	echo "  -h, --help     display this help and exit\n";
	echo "  -v, --version  output version information and exit\n";
	echo "\n";
	echo "Report bugs to <bug-appsrv@a2o.si>.\n";
	exit;
    }



    /**
     * Displays version information and exits
     */
    private function _cliProcess_displayVersion ()
    {
	echo "AppSrv (a2o.si) $this->_version\n";
	echo "FIXME copyright\n";
	echo "FIXME license\n";
	echo "\n";
	echo "Written by Bostjan Skufca.\n";
	exit;
    }



    /**
     * Parse the .ini config file and store whole array
     *
     * @return   void
     */
    private function _cliProcess_parseIniFile ()
    {
	// Shall we skip config file parsing altogether
	if ($this->_parseIniFile !== true) {
	    $this->_debug('Skipping .ini file parsing');
	    return;
	}
	
	// Prepare path
	$iniFile =& $this->_iniFile;

	// File accessibility checks
	if (!file_exists($iniFile))
	    $this->_cliProcess_error("Config .ini file does not exist: $iniFile");
	if (!is_file($iniFile))
	    $this->_cliProcess_error("Config .ini file is not a file: $iniFile");
	if (!is_readable($iniFile))
	    $this->_cliProcess_error("Config .ini file is not readable: $iniFile");

	// Try to parse this ini file finally
	$iniConfigArray = @parse_ini_file($iniFile, true);
	if ($iniConfigArray === false)
	    $this->_cliProcess_error("Config .ini file parsing FAILED: $iniFile");
	
	// Assign it
	$this->_debug("Using config .ini file: $iniFile", 6);
	$this->_iniConfigArray = $iniConfigArray;
    }



    /**
     * Parses the .ini config array and assigns corresponding properties
     *
     * @return   void
     */
    private function _cliProcess_parseIniConfigArray ()
    {
	// Shall we skip config file parsing altogether
	if ($this->_parseIniConfigArray !== true) {
	    $this->_debug('Skipping .ini config array parsing');
	    return;
	}

	$tica =& $this->_iniConfigArray;

	// Parse through sections
	$iniSection = 'AppSrv';
	if (isset($tica[$iniSection]['app_title']))
	    $this->_appTitle = $tica[$iniSection]['app_title'];
	if (isset($tica[$iniSection]['work_dir']))
	    $this->_workDir = $tica[$iniSection]['work_dir'];
	if (isset($tica[$iniSection]['pid_file']))
	    $this->_pidFile = $tica[$iniSection]['pid_file'];
	    
	$iniSection = 'AppSrv_Logging';
	if (isset($tica[$iniSection]['log_file']))
	    $this->_logFile = $tica[$iniSection]['log_file'];
	if (isset($tica[$iniSection]['log_level']))
	    $this->_logLevel = $tica[$iniSection]['log_level'];
	
	$iniSection = 'AppSrv_Socket';
	if (isset($tica[$iniSection]['listen_address']))
	    $this->_listenAddress = $tica[$iniSection]['listen_address'];
	if (isset($tica[$iniSection]['listen_port']))
	    $this->_listenPort = $tica[$iniSection]['listen_port'];
	if (isset($tica[$iniSection]['allowed_ips_regex']))
	    $this->_allowedIpsRegex = $tica[$iniSection]['allowed_ips_regex'];

	$iniSection = 'AppSrv_Daemon';
	if (isset($tica[$iniSection]['min_workers']))
	    $this->_mp_minWorkers = $tica[$iniSection]['min_workers'];
	if (isset($tica[$iniSection]['min_idle_workers']))
	    $this->_mp_minIdleWorkers = $tica[$iniSection]['min_idle_workers'];
	if (isset($tica[$iniSection]['max_idle_workers']))
	    $this->_mp_maxIdleWorkers = $tica[$iniSection]['max_idle_workers'];
	if (isset($tica[$iniSection]['max_workers']))
	    $this->_mp_maxWorkers = $tica[$iniSection]['max_workers'];
    }



    /**
     * _cliProcess_initLogStream
     *
     * Check and open logfile
     *
     * @return   void
     */
    private function _cliProcess_initLogStream ()
    {
	// Prepare file paths
	$logFile    =& $this->_logFile;
	$logFileDir =  dirname($logFile);
	
	// File accessibility checks
	if (file_exists($logFile)) {
	    if (!is_file($logFile))
		$this->_cliProcess_error("Log file is not a regular file: $logFile");
	    if (!is_writeable($logFile))
		$this->_cliProcess_error("Log file is not writeable: $logFile");
	} else {
	    if (!file_exists($logFileDir))
		$this->_cliProcess_error("Log file directory does not exist: $logFileDir");
	    if (!is_writeable($logFileDir))
		$this->_cliProcess_error("Log file directory is not writeable: $logFileDir");
	}

	// Open logfile first
	$r = fopen($logFile, 'a');
	if ($r === false)
	    $this->_cliProcess_error("Unable to open logfile: $logFile");
	    
	// Assign it
	$this->_debug("Using log file: $logFile", 6);
	$this->_logStream = $r;
    }



    /**
     * _cliProcess_error
     *
     * Outputs error message and exits
     */
    private function _cliProcess_error ($msg)
    {
	$this->_debugEnable = true;
	$this->_debug("ERROR: $msg", 1);
	
	if ($this->_pidFileCreated) {
	    $this->_debug("Removing pid file: $this->_pidFile", 1);
	    unlink($this->_pidFile);
	}
	
	$this->_debug('Exiting...', 1);
	exit(1);
    }
    /**************************************************************************\
    |
    | EOF CLI process methods
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | Common MASTER/WORKER process methods
    |
    \**************************************************************************/
    /**
     * _setStatus
     *
     * Tries to set new child/master status
     *
     * @return   bool
     */
    private function _setStatus ($newProcStatus)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// sanity check
	if (!isset($this->_availableProcStatuses[$newProcStatus]))
	    $this->_warning("Invalid new process status: $newProcStatus");
	if ($newProcStatus == 'init')
	    $this->_error("Cannot re-enter inititialization phase, newProcStatus=$newProcStatus");

	// deny setting new process status if in exiting mode
	if ($this->_procStatus == 'exiting') return false;

	// If currently working
	if ($this->_procStatus == 'working') {
	    if ($newProcStatus == 'idle') {
		if ($this->_nextProcStatus == 'exiting') {
		    $this->_procStatus = 'exiting';
		    $this->_exit();
		} else {
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
    | EOF Common MASTER/WORKER process methods
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | MASTER process methods
    |
    \**************************************************************************/
    /**
     * _masterProcess_init
     *
     * Initialize master process
     *
     * @return   void
     */
    private function _masterProcess_init ()
    {	
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Set master process data
	$this->_whoAmI = 'master';
	$this->_myPid  = getmypid();

	// Set common child data
	$this->_wp_parentPid = $this->_myPid;

	// Common init
	set_time_limit(0);
	ob_implicit_flush();

	$this->_debug('Hello from master process');
    }
    


    /**
     * _masterProcess_initWorkDir
     *
     * Check and chdir to workDir
     *
     * @return   void
     */
    private function _masterProcess_initWorkDir ()
    {	
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Check work directory
	if (!file_exists($this->_workDir))
	    $this->_cliProcess_error("Work directory does not exist: $this->_workDir");
	if (!is_readable($this->_workDir))
	    $this->_cliProcess_error("Work directory is not readable: $this->_workDir");

	// Chdir to work directory
	$r = chdir($this->_workDir);
	if ($r !== true) 
	    $this->_cliProcess_error("Unable to chdir to work directory: $this->_workDir");

	$this->_debug("Work directory changed to $this->_workDir");
    }


	
    /**
     * _masterProcess_initPidFile
     *
     * Check and create pid file
     *
     * @return   void
     */
    private function _masterProcess_initPidFile ()
    {	
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Check and write pidfile
	if (file_exists($this->_pidFile)) {
	    $oldPid = trim(file_get_contents($this->_pidFile));
	    $cmd    = "ps ax | grep '^ *$oldPid' | sed -e 's/a/a/'";
	    exec($cmd, $resArray, $resStatus);
	    if (count($resArray) > 1) {
		throw new Exception("Error while searching the process table");
	    }
	    if (count($resArray) == 0) {
		$resString = '';
	    } else {
		$resString = $resArray[0];
	    }
	    
	    if (($resString == '') || (!preg_match("/$this->_appTitle/", $resString))) {
		$this->_debug("Stale pid file detected, removing...");
		unlink($this->_pidFile);
	    } else {
		$this->_cliProcess_error("Another instance of $this->_appTitle is already running");
	    }
	}
	$r = file_put_contents($this->_pidFile, "$this->_myPid\n");
	if (($r === false) || ($r == 0)) throw new Exception("Error writting to pid file");

	$this->_pidFileCreated = true;
	$this->_debug("Pid $this->_myPid written to file $this->_pidFile");
    }
    


    /**
     * _masterProcess_initSocket
     *
     * Open network socket
     *
     * @return   void
     */
    private function _masterProcess_initSocket ()
    {	
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Initialize listening socket
	$r = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($r === false)
	    $this->_cliProcess_error("Unable to create socket");
	$this->_listenSocket = $r;
	
	$r = socket_bind($this->_listenSocket, $this->_listenAddress, $this->_listenPort);
	if ($r === false)
	    $this->_cliProcess_error("Unable to bind socket: " . socket_strerror(socket_last_error($this->_listenSocket)));
	
	$r = socket_listen($this->_listenSocket, 5);
	if ($r === false)
	    $this->_cliProcess_error("Unable to listen to socket: " . socket_strerror(socket_last_error($this->_listenSocket)));
	$this->_debug("Socket initialization complete");
    }



    /**
     * _masterProcess_installSignalHandlers
     *
     * Install signal handlers
     *
     * @return   void
     */
    private function _masterProcess_installSignalHandlers ()
    {	
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Install signal handlers
	declare (ticks = 1);
	pcntl_signal(SIGTERM, array(&$this, 'signalHandler'));
	pcntl_signal(SIGCHLD, array(&$this, 'signalHandler'));
	if ($this->_debugEnabled) {
	    pcntl_signal(SIGINT,  array(&$this, 'signalHandler'));   // Ctrl+C
	}

	$this->_debug("Signal handlers installed");
    }



    /**
     * _masterProcess_run
     *
     * Run the main master process loop
     */
    private function _masterProcess_run ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$this->_debug('Hello from master process again');

/*
	$this->_debug("Sleeping for some seconds");
	sleep(20);
	$this->_debug("Sucky alarm clock");
	exit;
*/

	// Enter the main loop
	do {
	    if ($this->_setStatus('working')) {
		// Poll for exit statuses
		$this->_mp_pollWorkers();

		// Fork if too few idle workers
		$this->_mp_forkIfRequired();
	    }
	    $this->_setStatus('idle');

	    //$this->_debug('One second nap', 9);
	    sleep(1);
	} while (true);

	throw new Exception("Reached the forbidden execution branch");
    }



    /**
     * _mp_pollWorkers
     *
     * Polls children for exit statuses
     */
    private function _mp_pollWorkers ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$this->_debug("Polling workers for exit statuses", 9);
        foreach ($this->_mp_workers as $workerId => $workerData) {
	    $status = NULL;
	    $r = pcntl_waitpid($workerData['pid'], $status, WNOHANG);
	    if ($r == -1) throw new Exception("PCNTL ERROR: $r");
	
	    if ($r == 0) {
		// worker is still alive
		$this->_debug("Worker $workerId is still alive", 9);
		continue;
	    } else {
		if ($r != $workerData['pid']) {
		    throw new Exception("Invalid pid $r");
		}
	    
		// what kind of exit
		if (pcntl_wifexited($status)) {
		    $this->_debug("Worker $workerId exited normally");
		    $this->_mp_unregisterWorker($workerId);
		} else {
		    $this->_debug("Worker $workerId died with status $status");
		    $this->_mp_unregisterWorker($workerId);
		}
	    }
	}
    }
    
    

    /**
     * _mp_forkIfRequired
     *
     * Forks a new worker if required
     */
    private function _mp_forkIfRequired ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Count workers
	$workersCount = $this->_mp_countWorkers();
	$idleWorkersCount = $this->_mp_countIdleWorkers();

	// Is there is already max number of workers
	if ($workersCount >= $this->_mp_maxWorkers) {
	    $this->_warning("Maximum number of workers reached: $this->_mp_maxWorkers");
	    return;
	}

	// Is there minimum number of workers alive?
	$forkNewWorker = false;
	if ($workersCount < $this->_mp_minWorkers) {
	    $this->_debug("Forking a new worker (only $workersCount workers alive, $this->_mp_minWorkers required)");
	    $forkNewWorker = true;
	} else {
	    // Is there minimum number of workers idle?
	    if ($idleWorkersCount < $this->_mp_minIdleWorkers) {
		$this->_debug("Forking a new worker (only $idleWorkersCount workers idle, $this->_mp_minIdleWorkers idle required)");
		$forkNewWorker = true;
	    }
	}

	// Do we need to fork a new worker?
	if ($forkNewWorker === true) {
	    $pid = pcntl_fork();
	    if ($pid == -1) throw new Exception('Could not fork');

	    if ($pid) {
	        // We are a parent/master
	        $this->_mp_registerWorker($pid);
	        $this->_debug("worker with pid $pid registered");
	    } else {
	        // We are the child/worker
	        $this->_workerProcess_run();
	        exit;   // Just as a precaution
	    }
	}
    }



    /**
     * _mp_countWorkers
     *
     * Counts all workers
     */
    private function _mp_countWorkers ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	return count($this->_mp_workers);
    }



    /**
     * _mp_countIdleWorkers
     *
     * Counts idle workers
     */
    private function _mp_countIdleWorkers ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$count = 0;
	foreach ($this->_mp_workers as $workerData) {
	    if ($workerData['status'] == 'idle') {
		$count++;
	    }
	}
	return $count;
    }



    /**
     * _mp_searchWorkerByPid
     *
     * Searches for a worker with given pid
     */
    private function _mp_searchWorkerByPid ($pid)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$workerFound = false;
	foreach ($this->_mp_workers as $workerId => $workerData) {
	    if ($workerData['pid'] == $pid) {
		return $workerId;
	    }
	}
	return false;
    }



    /**
     * _mp_registerWorker
     *
     * Adds new worker to array of workers
     */
    private function _mp_registerWorker ($pid)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	if ($this->_mp_searchWorkerByPid($pid) !== false) {
	    throw new Exception("Worker with pid $pid already registered");
	}

	for ($i=0 ; $i<$this->_mp_maxWorkers ; $i++) {
	    if (!isset($this->_mp_workers[$i])) {
		$this->_mp_workers[$i] = array(
		    'pid'    => $pid,
		    'status' => 'idle',
		);
		return true;
	    }
	}
        throw new Exception ('No more empty slots for new children');
    }



    /**
     * _mp_unregisterWorker
     *
     * Remove dead? worker from array of workers
     */
    private function _mp_unregisterWorker ($workerId)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	if (!isset($this->_mp_workers[$workerId])) {
	    throw new Exception("worker with id $workerId not listed in array");
	}
	unset($this->_mp_workers[$workerId]);
    }
    /**************************************************************************\
    |
    | EOF MASTER process methods
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | WORKER process methods
    |
    \**************************************************************************/
    /*
     * _runWorkerProcess
     *
     * Run the main worker process loop
     */
    private function _workerProcess_run ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Initialize worker
	$this->_wp_init();
	$this->_setStatus('idle');
	
	// Enter the main loop
	do {
	    // Accept connection - if there is such thing waiting at the door
	    if (!$this->_wp_getNewConnection()) {
		//$this->_debug("No client waiting, sleeping");
		usleep(500000);
		continue;
	    }

	    $this->_setStatus('working');
	    $this->worker_handleClient($this->_wp_clientSocket, $this->_wp_clientAddress, $this->_wp_clientPort);
	    $this->_setStatus('idle');
	} while (true);
	
	throw new Exception("Reached the forbidden execution branch");
    }
    


    /*
     * _wp_init
     *
     * Initialize the worker process
     */
    private function _wp_init ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$this->_whoAmI   = 'worker';
	$this->_myPid    = getmypid();
	$this->_children = array();
	$this->_debug('Hello from worker process');
	
	// Set the socket to non-blocking
	socket_set_nonblock($this->_listenSocket);
    }


    
    /*
     * _wp_getNewConnection
     *
     * Try to receive new connection, but do not block
     */
    private function _wp_getNewConnection ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Check if any client is waiting for connection
	$r = @socket_accept($this->_listenSocket);
	if (($r === false) && (socket_last_error($this->_listenSocket) === 0)) {
	    //$this->_debug("No client waiting");
	    return false;
	}
	    
	// Accept the connection
	if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_listenSocket)));
	$this->_wp_clientSocket = $r;

	// Get remote IP and port
	$r = socket_getpeername($this->_wp_clientSocket, $this->_wp_clientAddress, $this->_wp_clientPort);
	if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_wp_clientSocket)));
	$this->_debug("Client connected - $this->_wp_clientAddress:$this->_wp_clientPort");

	return true;
    }



    /*
     * worker_handleRequest
     *
     * Client request handler
     * Receives socket, address and port and handles the request
     * Users are supposed to FIXME...
     *
     * @return   FIXME
     */
    protected function worker_handleClient ($socket, $ip, $port)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	if (!$this->_wp_isClientAllowed()) {
	    $this->_debug("Client not allowed: $this->_wp_clientAddress");
	    $this->_wp_writeError("Client not allowed: $this->_wp_clientAddress");
	    $this->worker_closeConnection();
	    return;
	}

	// Handle the client
	if ($this->worker_writeBanner) {
	    $this->_wp_writeBanner();
	}
	$this->_wp_readRequest();
	$this->worker_handleRequest();
	$this->_wp_writeResponse();
	$this->worker_closeConnection();
	return;
    }



    /*
    * _wp_isClientAllowed
    *
    * Check if client is allowed to connect
    */
    private function _wp_isClientAllowed ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	if (preg_match("/^$this->_allowedIpsRegex\$/", $this->_wp_clientAddress)) {
	    return true;
	} else {
	    return false;
	}

    }
    


    /*
    * _wp_writeBanner
    *
    * Writes initial banner to the client 
    */
    private function _wp_writeBanner ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$banner  = "Hello from $this->_appTitle, a standalone application server!\n";
	$banner .= "This is a default  request handler which  behaves much like some HTTP server.\n";
	$banner .= "First it reads  request headers,  and if they contain  'Content-Length:',  it\n";
	$banner .= "proceeds to read the request body. When dual newline is received, the request\n";
	$banner .= "is handed over to request handler function, which defaults to simple  echo of\n";
	$banner .= "received content. Have fun!\n\n";
	$r = socket_write($this->_wp_clientSocket, $banner, strlen($banner));
	if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_wp_clientSocket)));

	return true;
    }



    /*
    * _wp_readRequest
    *
    * Read the request from client
    */
    private function _wp_readRequest ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Read the request header
	$requestHeaders = '';
	$thisLine       = '';
	$prevLine       = '';
	$contentLength  = false;
	do {
	    $r = socket_read($this->_wp_clientSocket, 16384, PHP_NORMAL_READ);
	    if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_wp_clientSocket)));
	    $thisLine = $r;
	    $this->_debug("Input line from client: $thisLine", 9);
	
	    // If we receive header 'Content-Length: xxx' - parse it
	    if (preg_match('/^content-length: ([0-9]+)\s*$/i', $thisLine, $matches)) {
		$contentLength = $matches[1];
		$this->_debug("Content-Length header from client: $contentLength", 9);
	    }
	
	    // Check if this is an end of headers
	    if (preg_match('/^[\r\n]+$/', $thisLine)) {
		$thisLine = "\n";
	    }
	    if (($thisLine == "\n") && ($prevLine == "\n")) {
	        break;
	    }
	    $requestHeaders .= $thisLine;
	    $prevLine        = $thisLine;
	} while (true);
	$this->_wp_clientRequestHeaders = $requestHeaders;
	$this->_debug("Request headers from client: $this->_wp_clientRequestHeaders", 9);
	
	// Read request body
	if ($contentLength !== false) {
	    $r = socket_read($this->_wp_clientSocket, $contentLength);
	    if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_wp_clientSocket)));
	}
	$this->_wp_clientRequestBody = trim($r);
	$this->_debug("Request body from client: $this->_wp_clientRequestBody", 9);

	return true;
    }



    /*
    * worker_handleRequest
    *
    * Handles the client request
    */
    protected function worker_handleRequest ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$this->_wp_appServerResponse = $this->_wp_clientRequestHeaders ."\n". $this->_wp_clientRequestBody;
	return true;
    }



    /*
    * _wp_writeError
    *
    * Writes error to the client socket 
    */
    private function _wp_writeError ($errMsg)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	$errMsgEncoded = xmlrpc_encode("ERROR: $errMsg");
	$r = socket_write($this->_wp_clientSocket, ($errMsgEncoded), strlen($errMsgEncoded));
    }



    /*
    * _wp_writeResponse
    *
    * Writes response to the client socket 
    */
    private function _wp_writeResponse ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	if ($this->_wp_appServerResponse !== false) {
	    $r = socket_write($this->_wp_clientSocket, $this->_wp_appServerResponse, strlen($this->_wp_appServerResponse));
	    if ($r === false) throw new Exception(socket_strerror(socket_last_error($this->_wp_clientSocket)));
	}
	return true;
    }



    /*
    * worker_closeConnection
    *
    * Closes the client connection and resets the client data so worker can 
    * accept new connection
    */
    private function worker_closeConnection ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Close the connection
	socket_close($this->_wp_clientSocket);
	$this->_wp_clientSocket         = false;
	$this->_wp_clientAddress        = false;
	$this->_wp_clientPort           = false;
	$this->_wp_clientRequestHeaders = false;
	$this->_wp_clientRequestBody    = false;
	$this->_debug("Client disconnected");
	return true;
    }
    /**************************************************************************\
    |
    | EOF WORKER process methods
    |
    \**************************************************************************/
    

    


    /**************************************************************************\
    |
    | SIGNAL handlers
    |
    \**************************************************************************/
    /**
     * signalHandler
     *
     * Signal handler for all processes
     *
     * @return   void
     */
    public function signalHandler ($signo)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	if ($this->_whoAmI === 'cli') {
	    $this->_signalHandler_cliProcess($signo);

	} elseif ($this->_whoAmI === 'master') {
	    $this->_signalHandler_masterProcess($signo);

	} elseif ($this->_whoAmI === 'worker') {
	    $this->_signalHandler_workerProcess($signo);

	} else {
	    throw new A2o_AppSrv_Exception("Unknown _whoAmI: $this->_whoAmI");
	}
    }



    /**
    * _signalHandler_masterProcess
    *
    * Signal handler for master process
    */
    private function _signalHandler_masterProcess ($signo)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	switch ($signo) {
	    case SIGTERM:
		$this->_debug("Caught SIGTERM, running shutdown method...");
		$this->_exit();
		break;
	    case SIGCHLD:
		$this->_debug("Caught SIGCHLD, polling the children...");
		$this->_mp_pollWorkers();
		break;
	    case SIGINT:
		$this->_debug("Caught SIGINT, running shutdown method...");
		$this->_exit();
		break;
	    default:
		$this->_debug("Caught unknown signal $signo, running shitdown method...");
		$this->_exit();
	}
    }



    /**
    * _signalHandler_workerProcess
    *
    * Signal handler for worker process
    */
    private function _signalHandler_workerProcess ($signo)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	switch ($signo) {
	    case SIGTERM:
		$this->_debug("Caught SIGTERM, running shutdown method...");
		$this->_exit();
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
    | EOF SIGNAL handlers
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
    private function _exit ($exitStatus=0)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	if ($this->_whoAmI === 'cli') {
	    $this->_exit_cliProcess($exitStatus);

	} elseif ($this->_whoAmI === 'master') {
	    $this->_exit_masterProcess($exitStatus);

	} elseif ($this->_whoAmI === 'worker') {
	    $this->_exit_workerProcess($exitStatus);

	} else {
	    throw new A2o_AppSrv_Exception("Unknown _whoAmI: $this->_whoAmI");
	}
    }



    /**
     * _exit_cliProcess
     *
     * Handles exit of CLI program
     *
     * @return   void
     */
    private function _exit_cliProcess ($exitStatus)
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	exit($exitStatus);
    }



    /**
     * _exit_masterProcess
     *
     * Handles the graceful exit of master process, signals to children to stop working and exit
     *
     * @return   void
     */
    private function _exit_masterProcess ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// First signal the children to exit
	foreach ($this->_mp_workers as $workerData) {
	    $r = posix_kill($workerData['pid'], SIGTERM);
	    if ($r !== true) throw new Exception("Unable to send SIGTERM");
	}
	
	// Wait a second
	sleep(2);
	
	// Poll children
	$this->_mp_pollWorkers();
	
	// Wait another second
	sleep(1);
	
	// Kill remaining children
	foreach ($this->_mp_workers as $workerData) {
	    $r = posix_kill($workerData['pid'], SIGKILL);
	    if ($r !== true) throw new Exception("Unable to send SIGKILL");
	}
	
	// Close the socket
	$r = socket_close($this->_listenSocket);

	// Remove pidfile
	if (file_exists($this->_pidFile)) unlink($this->_pidFile);
	
	// Close logfile and exit
	$this->_debug('Closing logfile and exiting...');
	$this->_debug('-------------------------------------------------');
	if ($this->_logStream !== false) fclose($this->_logStream);
	exit;
    }



    /**
     * _exit_workerProcess
     *
     * Handles the graceful exit of worker process
     */
    private function _exit_workerProcess ()
    {
	$this->_debug("-----> ". __FUNCTION__, 9);

	// Close the sockets
	socket_close($this->_listenSocket);
	if ($this->_wp_clientSocket !== false) {
	    socket_close($this->_wp_clientSocket);
	}
	
	// Close logfile and exit
	$this->_debug('Closing logfile and exiting...');
	if ($this->_logStream !== false) fclose($this->_logStream);
	exit;
    }
    /**************************************************************************\
    |
    | EOF SHUTDOWN methods
    |
    \**************************************************************************/





    /**************************************************************************\
    |
    | DEBUGGING/ERROR methods
    |
    \**************************************************************************/
    /**
     * _debug
     *
     * Outputs formatted debugging messages, with source info
     *
     * @return   void
     */
    protected function _debug ($msg, $level=1)
    {
	// Format message
	$msg2echo = date('Y-m-d H:i:s') ." ". strtoupper($this->_whoAmI) ." (pid=$this->_myPid): $msg\n";
	
	// Echo it if log stream is not open
	if ($this->_logStream === false) {
	    echo $msg2echo;
	    return;
	}
	
	// Log it if appropriate level is given
        if ($level <= $this->_logLevel) {
	    fwrite($this->_logStream, $msg2echo);
	}
	
	// If debugging is enabled, output it
	if ($this->_debugEnabled === true) {
	    if ($level <= $this->_debugLevel) {
		echo $msg2echo;
	    }
	}
    }



    /**
     * _debug_r
     *
     * Outputs (recursive) formatted debugging messages, with source info
     */
    protected function _debug_r ($var, $level=1)
    {
	ob_start();
	print_r($var);
	$msg = ob_get_contents();
	ob_end_clean();
	$this->_debug($msg, $level);
    }
    /**************************************************************************\
    |
    | EOF DEBUGGING/ERROR methods
    |
    \**************************************************************************/
}

