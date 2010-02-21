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
class A2o_AppSrv_Master
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
     * From config: Daemon
     */
    protected $_daemonize      = true;
    protected $_executableName = 'A2o_AppSrv';
    protected $_workDir        = '/opt/daemons/A2o/AppSrv';
    protected $_pid            = false;
    protected $_pidFile        = '/opt/daemons/A2o/AppSrv/A2o_AppSrv.pid';
    protected $_pidFileCreated = false;
    protected $_user           = 'nobody';
    protected $_group          = 'nogroup';

    /**
     * From config: Socket
     */
    protected $_listenAddress       = '0.0.0.0';
    protected $_listenPort          = 30000;
    protected $_listenStream        = false;
    protected $_listenStreamContext = false;

    /**
     * From config: Workers
     */
    protected $_workers_min     = 2;
    protected $_workers_minIdle = 1;
    protected $_workers_maxIdle = 2;
    protected $_workers_max     = 10;

    /**
     * Pool of workers
     *
     * Structure:
     *   $_workers = array(
     *       0 => array(
     *           'pid'          => 1234,
     *           'status'       => '(init|idle|working|exiting)',
     *           'socket_write' => @resource,
     *           'socket_read'  => @resource,
     *       ),
     *       1 => array...
     *   );
     */
    protected $_workers = array();


    /**
     * Temporary variables for master->worker transition
     */
    public $__tmp_listenStream       = false;
    public $__tmp_masterSocket_read  = false;
    public $__tmp_masterSocket_write = false;



    /**
     * Constructor
     *
     * @param    object   Parent object reference
     * @param    string   Parent class name, used for ini parsing (section prefix)
     * @return   void
     */
    public function __construct ($parent)
    {
    	$this->___parent =  $parent;
	$this->_config   =& $this->___parent->_config;
    }



    /**
     * Master process invocation method
     *
     * @return   void
     */
    public function __run ()
    {
    	// First merge the config with internal variables
	$this->___configApply();

        // Daemonise if debugging is not enabled
	$this->___daemoniseIfRequired();

	// Execute initialization
	$this->___init();

	// Start running the master process
	$this->___run();
    }



    /**
     * Maps the configuration array variables to properties
     *
     * @return   void
     */
    private function ___configApply ()
    {
        $ca =& $this->_config;

        // Parse through sections
        $iniSection = 'Daemon';
        $this->_daemonize           = $ca[$iniSection]['daemonize'];
        $this->_executableName      = $ca[$iniSection]['executable_name'];
        $this->_workDir             = $ca[$iniSection]['work_dir'];
        $this->_pidFile             = $ca[$iniSection]['pid_file'];
        $this->_user                = $ca[$iniSection]['user'];
        $this->_group               = $ca[$iniSection]['group'];

	$iniSection = 'Workers';
        $this->_mp_minWorkers       = $ca[$iniSection]['min_workers'];
        $this->_mp_minIdleWorkers   = $ca[$iniSection]['min_idle_workers'];
        $this->_mp_maxIdleWorkers   = $ca[$iniSection]['max_idle_workers'];
        $this->_mp_maxWorkers       = $ca[$iniSection]['max_workers'];
    }




    /**
     * Forks and exits the CLI process if debugging mode is enabled
     *
     * @return   void
     */
    private function ___daemoniseIfRequired ()
    {
        // If debugging is enabled, do not fork, just init the CLI (current process) as MASTER
        if ($this->_config['Daemon']['daemonize'] == false) {
            $this->_log("Debug mode enabled thus not forking, just silently initializing as master");
            $this->___parent->__registerMe_asMaster();
            $this->_pid = getmypid();
            return;
        }

        // Fork and exit the CLI process to daemonise
        $pid = pcntl_fork();
        if ($pid == -1)
            $this->_error('Could not fork');
        if ($pid) {
            // This is the CLI process
            $this->_debug("Saying goodbye to pid $pid and exiting...");
            exit;
        }

        // Here is the child which is a daemon now - master process
        // Register as ma master process
        $this->___parent->__registerMe_asMaster();
        $this->_pid = getmypid();
    }



    /**
     * Initialize master process
     *
     * @return   void
     */
    private function ___init ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // If exists, call custom pre-initialization method
        if (is_callable(array($this, '_preInit'))) $this->_preInit();

        // Then proceed with initialization
        $this->___init_php();
        $this->___init_stream();
        $this->___init_userGroup();
        $this->___init_workDir();
        $this->___init_pidFile();
        $this->___init_signalHandler();

        // If exists, call custom initialization method
        if (is_callable(array($this, '_init'))) $this->_init();

        $this->_debug('Initialization complete');
    }



    /**
     * PHP initialisation
     *
     * @return   void
     */
    private function ___init_php ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

    	// Common init
        set_time_limit(0);
        ob_implicit_flush();

        // Display all errors
        ini_set('display_errors', 1);
        ini_set('error_reporting', E_ALL);
    }



    /**
     * Open network listening stream, save it to $this->_listenStream, bind
     * and listen
     *
     * @return   void
     */
    private function ___init_stream ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        if ($this->_config['Ssl']['enabled'] == false) {
            $this->___init_stream_normal();
        } else {
            $this->___init_stream_ssl();
        }

        // Set stream to non-blocking
        $r = stream_set_blocking($this->_listenStream, 0);
        if ($r === false) {
            $this->_error("Unable to set stream to non-blocking.");
        }

        $this->_log("Listening stream initialization complete.");

    }



    /**
     * Open normal network stream, bind and listen
     *
     * @return   void
     */
    private function ___init_stream_normal ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Initialize stream context
        $contextOptions = array();
        $streamContext = stream_context_create($contextOptions);

        // Initialize listening stream
        $localSocket = 'tcp://'. $this->_config['Socket']['listen_address'] .':'. $this->_config['Socket']['listen_port'];
        $r = stream_socket_server(
            $localSocket,
            $errNo,
            $errStr,
            STREAM_SERVER_LISTEN | STREAM_SERVER_BIND,
            $streamContext
        );

        // Check for error
        if ($r === false) {
            $this->_error("Unable to create listening stream");
        }
        if ($errNo != 0) {
            $this->_error("Unable to open secure listening stream: $errStr");
        }

        // Assign it
        $this->_listenStream = $r;

        $this->_debug("Listening stream created: $localSocket");
    }



    /**
     * Open TLS/SSL network stream, bind and listen
     *
     * @return   void
     */
    private function ___init_stream_ssl ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Initialize context options
        $contextOptions = array(
            'ssl' => $this->_config['Ssl'],
        );
        $contextOptions['ssl']['capture_peer_cert']  = true;
        $contextOptions['ssl']['capture_peer_chain'] = true;

        // CN_match must not exist if otherwise what it does is comparison of
        // empty string agains peer CN which obviously fails
        if ($this->_config['Ssl']['CN_match'] == '') {
            unset($contextOptions['ssl']['CN_match']);
        }

        // Create SSL context for stream
        $streamContext = stream_context_create($contextOptions);

        // Initialize listening stream
        $localSocket = $this->_config['Ssl']['type'] .'://'. $this->_config['Socket']['listen_address'] .':'. $this->_config['Socket']['listen_port'];
        $r = stream_socket_server(
            $localSocket,
            $errNo,
            $errStr,
            STREAM_SERVER_LISTEN | STREAM_SERVER_BIND,
            $streamContext
        );

        // Check for error
        if ($errNo != 0) {
            $this->_error("Unable to open secure listening stream: $errStr");
        }
        
        // Assign it
        $this->_listenStream = $r;

        $this->_debug("Listening stream created: $localSocket");
    }



    /**
     * Check and chdir to $_workDir
     *
     * @return   void
     */
    private function ___init_userGroup ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

    	// Get current uid and gid
    	$uid_cur = posix_getuid();
    	$gid_cur = posix_getgid();

    	// If not root, skip the rest of this procedure
    	if ($uid_cur != 0) {
            $this->_log("Skipping the setUid/setGid part, because we are not root");
            return;
    	}

    	// Get desired uid/gid
        $r = posix_getpwnam($this->_user);
        if ($r === false) throw new A2o_AppSrv_Exception("Unable to get uid for user: $this->_user");
        $userData = $r;

        $r = posix_getgrnam($this->_group);
        if ($r === false) throw new A2o_AppSrv_Exception("Unable to get gid for group: $this->_group");
        $groupData = $r;

        $uid_desired = $userData['uid'];
        $gid_desired = $groupData['gid'];

        // Change effective uid/gid if required
        if ($gid_cur != $gid_desired) {
            $r = posix_setgid($gid_desired);
            if ($r === false) throw new A2o_AppSrv_Exception("Unable to setgid: $gid_cur -> $gid_desired");
            $this->_debug("Group (GID) changed to $this->_group ($gid_desired)");
        }

        if ($uid_cur != $uid_desired) {
            $r = posix_setuid($uid_desired);
            if ($r === false) throw new A2o_AppSrv_Exception("Unable to setuid: $uid_cur -> $uid_desired");
            $this->_debug("User (UID) changed to $this->_user ($uid_desired)");
        }

        $this->_debug("Setuid/setgid complete");
    }



    /**
     * Check and chdir to $_workDir
     *
     * @return   void
     */
    private function ___init_workDir ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Check work directory
        if (!file_exists($this->_workDir))
            $this->_error("Work directory does not exist: $this->_workDir");
        if (!is_readable($this->_workDir))
            $this->_error("Work directory is not readable: $this->_workDir");

        // Chdir to work directory
        $r = chdir($this->_workDir);
        if ($r !== true)
            $this->_error("Unable to chdir to work directory: $this->_workDir");

        $this->_debug("Work directory changed to $this->_workDir");
    }



    /**
     * Check and create pid file
     *
     * @return   void
     */
    private function ___init_pidFile ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Check for stale/existing pid file and if found, remove it
        if (file_exists($this->_pidFile)) {

            // Get old pid and check it
            $oldPid = trim(file_get_contents($this->_pidFile));
            if (!preg_match('/^[0-9]+$/', $oldPid)) {
                throw new A2o_AppSrv_Exception("Pid from $this->_pidFile invalid: $oldPid (Remove PID file manually!)");
            }

            // Check process table
            $cmd    = "ps ax | grep '^ *$oldPid ' | sed -e 's/a/a/'";
            exec($cmd, $resArray, $resStatus);
            if (count($resArray) > 1) {
                throw new A2o_AppSrv_Exception("Error while searching the process table");
            }
            if (count($resArray) == 0) {
                $resString = '';
            } else {
                $resString = $resArray[0];
            }

            // If process is not found or another process with given pid exists, remove pidfile
            if (($resString == '') || (!preg_match("/$this->_executableName/", $resString))) {
                $this->_log("Stale pid file detected, removing...");
                unlink($this->_pidFile);
            } else {
                $this->_error("Another instance of $this->_executableName is already running");
            }
        }

        // Check if pid file is writeable
        $pidFileDir = dirname($this->_pidFile);
        if (!is_writeable($pidFileDir)) {
            $this->_error("Directory of pidfile not writeable: $this->_pidFile");
        }

        // Write the pid file
        $r = @file_put_contents($this->_pidFile, "$this->_pid\n");
        if (($r === false) || ($r == 0)) $this->_error("Error writting to pid file");

        $this->_pidFileCreated = true;
        $this->_debug("Pid $this->_pid written to file $this->_pidFile");
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
     * Run the main master process loop
     *
     * If process returns from this loop, then new worker has been instantiated.
     * This means that you can (is preferred) destroy this object by unsetting it.
     *
     * @return   void
     */
    private function ___run ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Note the logfile
        $this->_log("A2o_AppSrv ". $this->___parent->getVersion() .": Resuming normal operations");

        // If we have just entered this method, this means
        // that initialization phase is complete and we are idle
        //$this->_setStatus('idle');

        // Enter the main loop
        do {
            //if ($this->_setStatus('working')) {

                // Poll for exit statuses
                $this->_mp_pollWorkers_forExit();

                //$this->_debug_r($this->_mp_workers);

                // TODO kill a worker if required
                // Fork a worker if too few idle workers
                $r = $this->_mp_forkIfRequired();
                if ($r == 'i_am_child') {
                        break;
                }

            //}
            //$this->_setStatus('idle');

            //$this->_debug('One second nap', 9);
            sleep(1);
        } while (true);

        // We are the forked worker now, return and parent object (A2o_AppSrv::run())
        // will enter the A2o_AppSrv_Worker::__run() method
        return;
    }



    /**
     * _mp_pollWorkers_forExit
     *
     * Polls children for exit statuses
     */
    private function _mp_pollWorkers_forExit ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

	$this->_debug("Polling workers for exit statuses", 9);
        foreach ($this->_workers as $workerId => $workerData) {
	    $status = NULL;
	    $r = pcntl_waitpid($workerData['pid'], $status, WNOHANG);
	    if ($r == -1) throw new A2o_AppSrv_Exception("PCNTL ERROR: $r");

	    if ($r == 0) {
		// worker is still alive
		$this->_debug("Worker $workerId is still alive", 9);
		continue;
	    } else {
		if ($r != $workerData['pid']) {
		    throw new A2o_AppSrv_Exception("Invalid pid $r");
		}

		// what kind of exit
		if (pcntl_wifexited($status)) {
                    if ($status == 0) {
                        $this->_debug("Worker $workerId exited normally ($status)");
                    } else {
                        $this->_warning("Worker $workerId died  with status $status");
                    }
		} else {
		    $this->_warning("Worker $workerId died with status $status");
		}
                $this->_mp_unregisterWorker($workerId);
	    }
	}
    }



    /**
     * Forks a new worker if required
     *
     * @return   string   'i_am_child' or 'i_am_master'
     */
    private function _mp_forkIfRequired ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

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
            return $this->_mp_fork();
        } else {
            return 'i_am_master';
        }
    }



    /**
     * Forks a new child
     *
     * @return   string   'i_am_child' or 'i_am_master'
     */
    private function _mp_fork ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Create new socket pair first
        $r = socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $socketPair);
        if ($r === false) throw new A2o_AppSrv_Exception('Unable to create a new socket pair');
        socket_set_nonblock($socketPair[0]);
        socket_set_nonblock($socketPair[1]);

        // Fork then
        $pid = pcntl_fork();
        if ($pid == -1) throw new A2o_AppSrv_Exception('Could not fork');

        if ($pid) {
            // We are a parent/master
            $workerId = $this->_mp_registerWorker($pid, $socketPair[0], $socketPair[1]);
            $this->_debug("worker with pid $pid registered");
            usleep(100000);

            // Check if worker is still registered and tell it it's ID
            if ($this->_mp_isWorkerRegistered($workerId)) {
                $this->_ipc_tellWorker_workerId($workerId);
            } else {
                $this->___parent->__log("Worker id=$workerId died unexpectedly");
            }
            return 'i_am_master';
        } else {
            // We are the child/worker

            //Assign the temporary variables
            $this->__tmp_listenStream       = $this->_listenStream;
            $this->__tmp_masterSocket_read  = $socketPair[0];
            $this->__tmp_masterSocket_write = $socketPair[1];

            return 'i_am_child';
        }
    }



    /**
     * _mp_countWorkers
     *
     * Counts all workers
     */
    private function _mp_countWorkers ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);
        return count($this->_workers);
    }



    /**
     * _mp_countIdleWorkers
     *
     * Counts idle workers
     */
    private function _mp_countIdleWorkers ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $count = 0;
        foreach ($this->_workers as $workerData) {
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
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $workerFound = false;
        foreach ($this->_workers as $workerId => $workerData) {
            if ($workerData['pid'] == $pid) {
                return $workerId;
            }
        }
        return false;
    }



    /**
     * Adds new worker to array of workers
     *
     * @param    integer    Worker PID number
     * @param    resource   Socket resource to read from worker
     * @param    resource   Socket resource to write to worker
     * @return   integer    Worker ID
     */
    private function _mp_registerWorker ($pid, $socketRead, $socketWrite)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        if ($this->_mp_searchWorkerByPid($pid) !== false) {
            throw new A2o_AppSrv_Exception("Worker with pid $pid already registered");
        }

        for ($i=0 ; $i<$this->_mp_maxWorkers ; $i++) {
            if (!isset($this->_workers[$i])) {
                $this->_workers[$i] = array(
                    'pid'          => $pid,
                    'status'       => 'idle',
                    'socket_read'  => $socketRead,
                    'socket_write' => $socketWrite,
                );
                return $i;
            }
        }
        throw new A2o_AppSrv_Exception ('No more empty slots for new children');
    }



    /**
     * Checks if worker is registered
     *
     * @param    integer   Worker ID
     * @return   bool      true for yes, false for no
     */
    private function _mp_isWorkerRegistered ($workerId)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ ."($workerId)", 9);

        if (isset($this->_workers[$workerId])) {
                return true;
        } else {
                return false;
        }
    }



    /**
     * Remove (dead?) worker from array of workers
     *
     * @param    integer   Worker ID number
     * @return   void
     */
    private function _mp_unregisterWorker ($workerId)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        if (!isset($this->_workers[$workerId])) {
            throw new A2o_AppSrv_Exception("worker with id $workerId not listed in array");
        }
        socket_close($this->_workers[$workerId]['socket_read']);
        socket_close($this->_workers[$workerId]['socket_write']);
        unset($this->_workers[$workerId]);
    }





    /**
     * Signal handler for master process
     *
     * @param    integer   Signal number
     * @return   void
     */
    public function __signalHandler ($signo)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ ."($signo)", 9);

        switch ($signo) {
            case SIGUSR1:
                $this->_debug("Caught SIGUSR1, polling the children for IPC messages...");
                $this->_ipc_pollWorkers();
                break;
            case SIGCHLD:
                $this->_debug("Caught SIGCHLD, polling the children for exit statuses...");
                $this->_mp_pollWorkers_forExit();
                break;
            case SIGTERM:
                $this->_debug("Caught SIGTERM, running shutdown method...");
                $this->___parent->__exit();
                break;
            case SIGINT:
                $this->_debug("Caught SIGINT, running shutdown method...");
                $this->___parent->__exit();
                break;
            default:
                $this->___parent->__log("Caught unknown signal $signo, running shitdown method...");
                $this->_exit();
        }
    }




    /**************************************************************************\
    |
    | IPC routines
    |
    \**************************************************************************/
    /**
     * Poll workers for their messages
     *
     * @return void
     */
    private function _ipc_pollWorkers ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Loop throught workers
        foreach ($this->_workers as $workerId => $worker) {

                // Try to read a message from read socket of given worker
            $msg = socket_read($worker['socket_read'], 256);
            $msg = trim($msg);
            if ($msg != '') {
                $this->_debug("Received message from worker $workerId: $msg", 8);
            }

            // If no message is received
            if ($msg == '') continue;

            switch ($msg) {
                case 'system::myStatus::idle':
                    $this->_workers[$workerId]['status'] = 'idle';
                    break;
                case 'system::myStatus::working':
                    $this->_workers[$workerId]['status'] = 'working';
                    break;
                default:
                    $this->_error("Unknown IPC message from worker id=$workerId: $msg");
            }
        }
    }



    /**
     * Tell the worker which is it's ID
     *
     * @param    integer   Worker ID
     * @return   void
     */
    private function _ipc_tellWorker_workerId ($workerId)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $ipcMessage = "system::setWorkerId::$workerId";
        $this->_ipc_tellWorker($workerId, $ipcMessage);
    }



    /**
     * Notify the worker about something
     *
     * @param    integer   Worker ID
     * @param    string    Message to transmit
     * @return   void
     */
    private function _ipc_tellWorker ($workerId, $ipcMessage)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $ipcMessage .= "\n";
        $this->_ipc_tellWorkerRaw($workerId, $ipcMessage);
    }



    /**
     * Send the message to the worker and send a signal so it is surely received in a timely manner
     *
     * @param    integer   Worker ID
     * @param    string    Message to transmit (must end with a newline)
     * @return   void
     */
    private function _ipc_tellWorkerRaw ($workerId, $rawIpcMessage)
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        $r = socket_write($this->_workers[$workerId]['socket_write'], $rawIpcMessage, strlen($rawIpcMessage));
        if ($r === false) {
                throw new A2o_AppSrv_Exception(socket_strerror(socket_last_error($this->_workers[$workerId]['socket_write'])));
        }

        posix_kill($this->_workers[$workerId]['pid'], SIGUSR1);
    }
    /**************************************************************************\
    |
    | EOF IPC routines
    |
    \**************************************************************************/





    /**
     * Handles the graceful exit of master process, signals to children to
     * stop working and exit, but does not exit the process, ___parent class
     * does that.
     *
     * @return   void
     */
    public function __exitCleanup ()
    {
        $this->_debug("-----> ". __CLASS__ . '::' . __FUNCTION__ .'()', 9);

        // Notice the log
        $this->_log("A2o_AppSrv ". $this->___parent->getVersion() .": Starting shutdown");

        // Kill the workers if necessary
        $this->___exit_killWorkers();

        // Close the listening stream
        $r = fclose($this->_listenStream);
        $this->_debug('Listening stream closed');


        // Remove pidfile
        if ($this->_pidFileCreated) {
            if (file_exists($this->_pidFile)) {
                unlink($this->_pidFile);
                $this->_debug('Pid file removed');
            } else {
                $this->_warning('Pid file missing!');
            }
        }
    }



    /**
     * Handles the graceful kill of worker processes
     *
     * @return   void
     */
    private function ___exit_killWorkers ()
    {
    	// If none are alive, just return
        if ($this->_mp_countWorkers() == 0) {
                return;
        }

    	// First signal the children to exit
        foreach ($this->_workers as $workerData) {
            $r = posix_kill($workerData['pid'], SIGTERM);
            if ($r !== true) throw new A2o_AppSrv_Exception("Unable to send SIGTERM");
        }

        // Wait a second
        sleep(2);

        // Poll children
        $this->_mp_pollWorkers_forExit();

        // Wait another second
        sleep(1);

        // Kill remaining children
        foreach ($this->_workers as $workerData) {
            $r = posix_kill($workerData['pid'], SIGKILL);
            if ($r !== true) throw new A2o_AppSrv_Exception("Unable to send SIGKILL");
        }
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
