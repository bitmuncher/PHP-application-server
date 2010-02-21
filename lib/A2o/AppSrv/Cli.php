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
final class A2o_AppSrv_Cli
{
    /**
     * Parent instance object
     */
    protected $___parent = false;

    /**
     * CLI arguments parsing
     */
    protected $___parseCliArgs = true;

    /**
     * Config .ini file parsing
     */
    protected $___parseConfigFile = true;

    /**
     * Configuration data arrays
     */
    public $__configArray_cli    = array();   // From command line arguments
    public $__configArray_ini    = array();   // From config file or config array passed to constructor
    public $__configArray_custom = array();   // Custom array of unrecognised ini sections



    /**
     * Constructor
     *
     * @param    object                     Parent object reference
     * @param    (null|bool)                $parseCliArgs   Parse CLI arguments?
     * @return   void
     */
    public function __construct ($parent, $parseCliArgs=true)
    {
        // Set the parent object and register the process
        $this->___parent = $parent;
        $this->___parent->__registerMe_asCli();

        // Get config file
        $configFile = $this->___parent->__configFile;



        // CLI arguments parsing?
        if ($parseCliArgs === NULL) {
            // Defaults

        } elseif ($parseCliArgs === false) {
            $this->___parseCliArgs = false;

        } elseif ($parseCliArgs === true) {
            $this->___parseCliArgs = true;

        } else {
            throw new A2o_AppSrv_Exception('ERR_CONSTRUCTOR_PARAM_parseCliArgs_INVALID');
        }



        // Config file parsing?
        if ($this->___parent->__configFile === NULL) {
            // Defaults

        } elseif ($this->___parent->__configFile === false) {
            // Do not parse the config file
            $this->___parseConfigFile = false;

        } elseif ($this->___parent->__configFile === true) {
            // Parse the default config file
            $this->___parseConfigFile = true;

        } elseif (is_string($this->___parent->__configFile)) {
            // Parse THIS config file
            $this->___parseConfigFile = true;

        } elseif (is_array($configFile)) {
            // Load THIS config array
            $this->___parseConfigFile = false;
            $this->___configArray_ini = $this->___parent->__configFile;

        } else {
            throw new A2o_AppSrv_Exception('ERR_CONSTRUCTOR_parent->configFile_INVALID');
        }
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
        $this->___parseConfigFile();
    }



    /**
     * Parses command line arguments
     *
     * @return   void
     */
    private function ___parseCliArgs ()
    {
        // Check if parsing CLI arguments is explicitly disabled by constructor parameter
        if ($this->___parseCliArgs !== true) {
            return;
        }

        // These short options are available
        $shortOpts  = '';
        $shortOpts .= 'h';
        $shortOpts .= 'v';
        $shortOpts .= 'g';
        $shortOpts .= 'd::';
        $shortOpts .= 'c:';

        // These long options available - FIXME
        $longOpts   = array(
            'help',
            'version',
            'generate-config',
            'debug::',
            'config:',
        );

        // Parse CLI arguments
        // FIXME add support for long options (emits warning on PHP 5.2)
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

        // Should we generate default config file?
        if (isset($cliArgs['g'])) {
            $this->___action_generateConfig();
        }

        // Shall we enable debugging?
        if (isset($cliArgs['d'])) {
            $this->__configArray_cli = array();
            $this->__configArray_cli['Daemon'] = array();
            $this->__configArray_cli['Daemon']['daemonize'] = false;

            // Shall we set the debug level?
            if (($cliArgs['d'] !== false) && preg_match('/^[1-9]$/', $cliArgs['d'])) {
                $this->__configArray_cli['Logging']['log_level'] = $cliArgs['d'];
            }
        }

        // Is custom ini file specified?
        if (isset($cliArgs['c']) && ($cliArgs['c'] !== false)) {
            $this->___parseConfigFile      = true;
            $this->___parent->__configFile = $cliArgs['c'];
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
        echo "  -g, --generate-config        generate default config file\n";
        echo "  -d, --debug[=LEVEL]          enable debugging to STDOUT. LEVEL specifies verbosity\n";
        echo "                                 between 1 (lowest) and 9 (highest)\n";
        echo "  -c, --config=FILE            use ini file FILE\n";
        echo "  -h, --help     display this help and exit\n";
        echo "  -v, --version  output version information and exit\n";
        echo "\n";
        echo "Report bugs to <bostjan@a2o.si>.\n";
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
     * Displays help and exits
     *
     * @return   void
     */
    private function ___action_generateConfig ()
    {
        echo "; Default configuration for A2o_AppSrv\n";
        echo "\n";

        $longestOptionName   = 0;
        $longestDefaultValue = 0;
        foreach ($this->___parent->__configArray_defaults as $section => $options) {
            // Get longest optionName and defaultValue
            foreach ($options as $optionName => $defaultValue) {
                $optionNameLength   = strlen($optionName);
                $defaultValueLength = strlen($defaultValue);
                if ($optionNameLength > $longestOptionName) {
                    $longestOptionName = $optionNameLength;
                }
                if ($defaultValueLength > $longestDefaultValue) {
                    $longestDefaultValue = $defaultValueLength;
                }
            }
        }

        // Render
        foreach ($this->___parent->__configArray_defaults as $section => $options) {
            echo "[$section]\n";

            foreach ($options as $optionName => $defaultValue) {
                $optionNameLength   = strlen($optionName);
                $defaultValueLength = strlen($defaultValue);
                $optionDescription  = $this->___parent->__configArray_comments[$section][$optionName];

                echo "$optionName";
                echo str_repeat(' ', $longestOptionName - $optionNameLength);
                echo " = ";
                echo "$defaultValue";
                echo str_repeat(' ', $longestDefaultValue - $defaultValueLength);
                echo "   ; $optionDescription\n";
            }

            echo "\n";
        }

        // Add exemplary custom sections
        echo "[Custom_section_1]\n";
        echo "custom_value_1 = a\n";
        echo "custom_value_2 = b\n";
        echo "\n";
        echo "[Custom_section_2]\n";
        echo "custom_value_1 = a\n";
        echo "custom_value_2 = b\n";
        exit;
    }



    /**
     * Parse the .ini config file and store whole array
     *
     * @return   void
     */
    private function ___parseConfigFile ()
    {
        // Shall we skip config file parsing altogether
        if ($this->___parseConfigFile != true) {
            $this->_debug('Skipping .ini file parsing');
            return;
        }

        // Prepare path
        $configFile = $this->___parent->__configFile;

        // Just assign it if it is an array
        if (is_array($configFile)) {
            $this->__configArray_ini = $configFile;

        } else {

            // File accessibility checks
            if (!file_exists($configFile))
                $this->_cliProcess_error("Config .ini file does not exist: $configFile");
            if (!is_file($configFile))
                $this->_cliProcess_error("Config .ini file is not a file: $configFile");
            if (!is_readable($configFile))
                $this->_cliProcess_error("Config .ini file is not readable: $configFile");

            // Try to parse this ini file finally
            $iniConfigArray = parse_ini_file($configFile, true);
            if ($iniConfigArray === false) {
                throw new A2o_AppSrv_Exception("Config .ini file parsing FAILED: $configFile");
            }

            // Divide the sections between system and custom
            $sectionPrefix = $this->___parent->__configSectionPrefix;
            foreach ($iniConfigArray as $sectionName => $sectionData) {
                if (substr($sectionName, 0, strlen($sectionPrefix)) == $sectionPrefix) {
                    // Correct section found
                    $sectionNameShort = substr($sectionName, strlen($sectionPrefix));
                    $this->__configArray_ini[$sectionNameShort] = $sectionData;
                } else {
                    $this->__configArray_custom[$sectionName] = $sectionData;
                }
            }
        }

        // Some debugging hints
        $this->___parent->__debug_r($this->__configArray_cli, 8);
        $this->___parent->__debug_r($this->__configArray_ini, 8);
        $this->___parent->__debug_r($this->__configArray_custom, 8);
    }



    /**
     * _cliProcess_error
     *
     * Outputs error message and exits
     */
    private function _cliProcess_error ($msg)
    {
        $this->___parent->__log("ERROR: $msg");
        $this->___parent->__log('Exiting...');
        exit(1);
    }



    /**
     * Parent method wrapper for debug messages
     *
     * Outputs error message and exits
     */
    private function _debug ($message, $importanceLevel=5)
    {
    	$this->___parent->__debug($message, $importanceLevel);
    }
}
