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
 * @see A2o_AppSrv_Client_Exception
 */
require_once 'A2o/AppSrv/Client/Exception.php';



/**
 * @see A2o_AppSrv_Client_Abstract
 */
require_once 'A2o/AppSrv/Client/Abstract.php';



/**
 * Standalone preforking PHP application server framework
 *
 * @category   A2o
 * @package    A2o_AppSrv
 * @subpackage A2o_AppSrv_Client
 * @author     Bostjan Skufca <bostjan@a2o.si>
 * @copyright  Copyright (c) 2009 Bostjan Skufca (http://a2o.si)
 * @license    http://www.gnu.org/licenses/gpl.html     GNU GPLv3
 */
class A2o_AppSrv_Client_Generic extends A2o_AppSrv_Client_Abstract
{
    /**
     * Does not do any request reading
     *
     * @return   void
     */
    public function readRequest ()
    {
    	return;
    }



    /**
     * writeError
     *
     * Writes error to the client socket handle
     *
     * @param    string    Error message
     * @return   void
     */
    public function writeError ($errorMessage)
    {
	$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

	$this->write($errorMessage ."\n");
    }



    /**
     * writeResponse
     *
     * Writes response to the client socket handle
     *
     * @param    string    Response
     * @return   void
     */
    public function writeResponse ($response)
    {
	$this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

	$this->write($response);
    }
}
