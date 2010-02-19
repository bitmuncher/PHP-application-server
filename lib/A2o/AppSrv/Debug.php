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
class A2o_AppSrv_Debug {
    /**
     * Parent system
     */
    protected $_parent = false;

    /**
     * Messages with importance number lower or equal to this threshold will get logged/written to output
     */
    protected $_threshold = 5;



    /**
     * Constructor
     *
     * @param    object   A2o_AppSrv object
     * @return   void
     */
    public function __construct ($parent)
    {
    	$this->_parent = $parent;
    }



    /**
     * Destructor
     *
     * @return   void
     */
    public function __destruct ()
    {
    	$this->_parent = NULL;
    }



    /**
     * Sets the debug threshold level
     *
     * @param    integer   New threshold level
     * @return   void
     */
    public function setThreshold ($threshold)
    {
        if (!preg_match('/^[1-9]$/', $threshold)) {
            throw new A2o_AppSrv_Exception("Given threshold is not a number between 1 and 9: $threshold");
        }
        $this->_threshold = $threshold;
    }



    /**
     * Sends the debug message if below threshold level
     *
     * @param    string   Log message
     * @param    int      Importance level of a given message, default 5
     * @return   void
     */
    public function debug ($message, $importanceLevel=5)
    {
        // echo __CLASS__ .'::'. __FUNCTION__ ." il=$importanceLevel th=$this->_threshold\n";
        if ($importanceLevel <= $this->_threshold) {
            $this->_parent->__log($message);
        }
    }



    /**
     * Logs any variable by print_r-ing it
     *
     * @param    mixed     Log variable
     * @param    integer   Importance level of given var, default 5
     * @return   void
     */
    public function debug_r ($var, $importanceLevel=5)
    {
    	// echo __CLASS__ .'::'. __FUNCTION__ ." il=$importanceLevel th=$this->_threshold\n";
    	if ($importanceLevel <= $this->_threshold) {
            $this->_parent->__log_r($var);
        }
    }
}
