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
final class A2o_AppSrv_Log {
    /**
     * Output messages to STDOUT
     */
    protected $_logToScreen = true;

    /**
     * Output messages to logFile
     */
    protected $_logToFile = false;

	/**
     * Log file name
     */
    protected $_logFile = false;

    /**
     * Log file handle
     */
    protected $_logFileHandle = false;



    /**
     * Constructor
     *
     * @param    (null|string)   Log file name
     * @return   void
     */
    public function __construct ($logFile=NULL)
    {
    	if ($logFile !== NULL) {
            $this->openLogFile($logFile);
    	}
    }



    /**
     * Destructor
     *
     * @return   void
     */
    public function __destruct ()
    {
    	$this->closeLogFile();
    }



    /**
     * Checks and opens the logfile
     *
     * @param    string   Log file name
     * @return   void
     */
    public function openLogFile ($logFile)
    {
        // File accessibility checks
        if (file_exists($logFile)) {
            if (!is_file($logFile))
                throw new A2o_AppSrv_Exception("Log file is not a regular file: $logFile");
            if (!is_writeable($logFile))
                throw new A2o_AppSrv_Exception("Log file is not writeable: $logFile");
        } else {
            $logFileDir = dirname($logFile);
            if (!file_exists($logFileDir))
                throw new A2o_AppSrv_Exception("Log file directory does not exist: $logFileDir");
            if (!is_writeable($logFileDir))
                throw new A2o_AppSrv_Exception("Log file directory is not writeable: $logFileDir");
        }

        // Open logfile first
        $r = fopen($logFile, 'a');
        if ($r === false)
            throw new A2o_AppSrv_Exception("Unable to open logfile: $logFile");
        $logFileHandle = $r;

        // Close old logfile if opened
        if (($this->_logFile !== false) && ($this->_logFileHandle !== false)) {
            $this->closeLogFile();
        }

        // Assign variables
        $this->_logFile       = $logFile;
        $this->_logFileHandle = $logFileHandle;

        // Enable file and disable screen logging
        $this->_logToFile   = true;
        $this->_logToScreen = false;

        // Assign it - THINK, FIXME
        //$this->_debug("Using log file: $logFile", 6);
    }



    /**
     * Closes currently open logfile, if any, and re-enable screen logging
     *
     * @return   void
     */
    public function closeLogFile ()
    {
        // Enable screen logging
        $this->_logToScreen = true;
        $this->_logToFile   = false;

        // Return if no logfile is opened
        if (($this->_logFile === false) || ($this->_logFileHandle === false)) {
            return;
        }

        // Close the file
        $r = fclose($this->_logFileHandle);
        if ($r === false) {
            throw new A2o_AppSrv_Exception("Unable to close logfile: $this->_logFile");
        }

        // Unset the variables
        $this->_logFile       = false;
        $this->_logFileHandle = false;
    }



    /**
     * Explicitly enables screen logging
     *
     * @return   void
     */
    public function enableLogToScreen ()
    {
        $this->_logToScreen = true;
    }



    /**
     * Explicitly disables screen logging
     *
     * @return   void
     */
    public function disableLogToScreen ()
    {
        $this->_logToScreen = false;
    }



    /**
     * Logs message to appropriate places, prepends timestamp and appends newline
     *
     * @param    string   Log message
     * @return   void
     */
    public function log ($message)
    {
        // Format message
        $messageFinal = date('Y-m-d H:i:s') ." $message\n";

        if ($this->_logToScreen === true) {
            echo "$messageFinal";
        }

        if ($this->_logToFile === true) {
            fwrite($this->_logFileHandle, $messageFinal);
        }
    }



    /**
     * Logs any variable by print_r-ing it
     *
     * @param    mixed   Log variable
     * @return   void
     */
    public function log_r ($var)
    {
        ob_start();
        print_r($var);
        $contents = ob_get_contents();
        ob_end_clean();

        $this->log($contents);
    }
}
