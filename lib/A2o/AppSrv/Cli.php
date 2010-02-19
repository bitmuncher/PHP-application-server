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
class A2o_AppSrv_Cli
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
     * CLI arguments parsing
     */
    protected $_parseCliArgs = true;

    /**
     * Config .ini file parsing
     */
    protected $_iniFile             = '/opt/daemons/A2o_AppSrv/A2o_AppSrv.ini';
    protected $_parseIniFile        = true;

    /**
     * Configuration data arrays
     */
    public $__configArray_cli    = array();   // From command line arguments
    public $__configArray_ini    = array();   // From ini file or ini array passed to constructor
    public $__configArray_custom = array();   // Custom array of unrecognised ini sections



    /**
     * Constructor
     *
     * @param    object                     Parent object reference
     * @param    string                     Parent class name, used for ini parsing (section prefix)
     * @param    (null|bool|string|array)   $ini            Process ini file
     * @param    (null|bool)                $parseCliArgs   Parse CLI arguments?
     * @return   void
     */
    public function __construct ($parent, $parentClassName, $ini=NULL, $parseCliArgs=true)
    {
    	// Set the parent object and register the process
        $this->__setParent($parent, $parentClassName);
        $this->_parent->__registerMe_asCli();

        // Config file parsing?
        if ($ini === NULL) {
            // Defaults
        } elseif ($ini === false) {                // Do not parse the config file
            $this->_parseIniFile        = false;
            $this->_iniFile             = NULL;
        } elseif ($ini === true) {                 // Parse the default config file
            $this->_parseIniFile        = true;
        } elseif (is_string($ini)) {               // Parse THIS config file
            $this->_parseIniFile        = true;
                $this->_iniFile             = $ini;
        /* FIXME remove?
        } elseif (is_array($ini)) {                // Load THIS config array
            $this->_iniFile             = NULL;
            $this->_parseIniFile        = false;
            $this->_iniConfigArray      = $ini;
        */
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



    /**
     * __setParent
     *
     * @param    object   Save parent object reference for future access
     * @param    string   Parent object class name, for ini parsing - section prefix
     * @return   void
     */
    public function __setParent ($parent, $parentClassName)
    {
	if ($this->_parent !== false)
            throw new A2o_AppSrv_Exception('Parent object already set');
        $this->_parent = $parent;
        $this->_parentClassName = $parentClassName;
    }



    /**
     * CLI processing invocation
     *
     * @return   void
     */
    public function __run ()
    {
        // CLI process
        $this->___parseCliArgs();
        $this->___parseIniFile();
    }



    /**
     * Parses command line arguments
     *
     * @return   void
     */
    private function ___parseCliArgs ()
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
            $this->___action_displayHelp();
        }

        // Should we display version information?
        if (isset($cliArgs['v'])) {
            $this->___action_displayVersion();
        }

        // Shall we enable debugging?
        if (isset($cliArgs['d'])) {
            $this->__configArray_cli = array();
            $this->__configArray_cli['Logging'] = array();
            $this->__configArray_cli['Logging']['debug_to_screen'] = true;

            // Shall we set the debug level?
            if (($cliArgs['d'] !== false) && preg_match('/^[1-9]$/', $cliArgs['d'])) {
                $this->__configArray_cli['Logging']['debug_level'] = $cliArgs['d'];
            }
        }

        // Is custom ini file specified?
        if (isset($cliArgs['i']) && ($cliArgs['i'] !== false)) {
            $this->_parseIniFile        = true;
            $this->_iniFile             = $cliArgs['i'];
        }
    }



    /**
     * Displays help and exits
     *
     * @return   void
     */
    private function ___action_displayHelp ()
    {
        echo "Usage: " . $_SERVER['PWD'] . substr($_SERVER['SCRIPT_FILENAME'], 1) . " [OPTION]...\n";
        echo "Starts the standalone preforking PHP application server.\n";
        echo "\n";
        echo "Mandatory arguments to long options are mandatory for short options too.\n";
        echo "  -d, --debug[=LEVEL]          enable debugging to STDOUT. LEVEL specifies verbosity\n";
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
     *
     * @return void
     */
    private function ___action_displayVersion ()
    {
        echo "AppSrv (a2o.si) ". A2o_AppSrv::version ."\n";
        echo "Copyright (C) 2008 Bostjan Skufca (http://a2o.si)\n";
        echo "License GPLv3: GNU GPL version 3 <http://gnu.org/licenses/gpl.html>\n";
        echo "This software comes with NO WARRANTY, to the extent permitted by law.\n";
        echo "\n";
        echo "Written by Bostjan Skufca.\n";
        exit;
    }



    /**
     * Parse the .ini config file and store whole array
     *
     * @return   void
     */
    private function ___parseIniFile ()
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
        $iniConfigArray = parse_ini_file($iniFile, true);
        if ($iniConfigArray === false) {
            throw new A2o_AppSrv_Exception("Config .ini file parsing FAILED: $iniFile");
        }

        // Divide the sections between system and custom
        $sectionPrefix = $this->_parentClassName .'_';
        foreach ($iniConfigArray as $sectionName => $sectionData) {
            if (substr($sectionName, 0, strlen($sectionPrefix)) == $sectionPrefix) {
                // Correct section found
                $sectionNameShort = substr($sectionName, strlen($sectionPrefix));
                $this->__configArray_ini[$sectionNameShort] = $sectionData;
            } else {
                $this->__configArray_custom[$sectionName] = $sectionData;
            }
        }

        // Some debugging hints
        $this->_parent->__debug_r($this->__configArray_cli, 8);
        $this->_parent->__debug_r($this->__configArray_ini, 8);
        $this->_parent->__debug_r($this->__configArray_custom, 8);
    }



    /**
     * _cliProcess_error
     *
     * Outputs error message and exits
     */
    private function _cliProcess_error ($msg)
    {
        $this->_debugEnable = true;
        $this->_parent->__log("ERROR: $msg");

        if ($this->_pidFileCreated) {
            $this->_debug("Removing pid file: $this->_pidFile", 1);
            unlink($this->_pidFile);
        }

        $this->_parent->__log('Exiting...');
        exit(1);
    }



    /**
     * Parent method wrapper for debug messages
     *
     * Outputs error message and exits
     */
    protected function _debug ($message, $importanceLevel)
    {
    	$this->_parent->__debug($message, $importanceLevel);
    }
}
