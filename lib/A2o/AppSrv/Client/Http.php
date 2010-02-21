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
class A2o_AppSrv_Client_Http extends A2o_AppSrv_Client_Abstract
{
    /**
     * Whole HTTP request
     */
    public $request = NULL;

    /**
     * HTTP request headers
     */
    public $requestHeaders = NULL;

    /**
     * HTTP request body
     */
    public $requestBody = NULL;



    /**
     * Reads the HTTP request from client
     *
     * Starts reading the request headers and if Content-Length header is
     * encountered, reads the request body also.
     *
     * @return   void
     */
    public function readRequest ()
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

        // Init the request reading
        $this->request        = '';
        $this->requestHeaders = '';
        $this->requestBody    = NULL;

        // Read the request header
        $curLine        = '';
        $prevLine       = '';
        $contentLength  = false;
        do {
            $curLine = $this->readLine();
            $this->request        .= $curLine;
            $this->requestHeaders .= $curLine;
            $this->_debug("Input line from client: $curLine", 8);

            // If we receive header 'Content-Length: xxx' - parse it
            if (preg_match('/^content-length: ([0-9]+)\s*$/i', $curLine, $matches)) {
                        $contentLength = $matches[1];
                        $this->_debug("Content-Length header from client: $contentLength", 8);
            }

            // Check if this is an end of headers
            if (preg_match('/^[\r\n]+$/', $curLine)) {
                        $curLine = "\n";
            }
            if ($curLine == "\n") {
                break;
            }

            // Assign current line for later examination
            $prevLine = $curLine;
        } while (true);
        $this->_debug("HTTP request headers from client: $this->requestHeaders", 7);

        // Read request body
        if ($contentLength !== false) {
            $r = $this->read($contentLength);
            $this->request     .= $r;
            $this->requestBody  = trim($r);
            $this->_debug("HTTP request body from client:\n$this->requestBody", 7);
        }
    }



    /**
     * writeError
     *
     * Writes error to the client
     *
     * @param    string    HTTP error message
     * @param    integer   HTTP status code
     * @param    string    HTTP status header
     * @return   void
     */
    public function writeError ($errorMessage, $statusCode=500, $statusHeader='Internal server error')
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

        $errorMessageFinal  = "HTTP/1.0 $statusCode $statusHeader\n";
        $errorMessageFinal .= "Connection: close\n";
        $errorMessageFinal .= "Content-Length: ". strlen($errorMessage) ."\n\n";
        $errorMessageFinal .= "$errorMessage";

        $this->_debug("HTTP error response:\n$errorMessageFinal", 7);
        $this->write($errorMessageFinal);
    }



    /**
     * writeResponse
     *
     * Writes response to the client
     *
     * @param    string    HTTP response
     * @param    integer   HTTP status code
     * @param    string    HTTP status header
     * @return   void
     */
    public function writeResponse ($response, $statusCode=200, $statusHeader='OK')
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

        $responseFinal  = "HTTP/1.0 $statusCode $statusHeader\n";
        $responseFinal .= "Connection: close\n";
        $responseFinal .= "Content-Type: text/xml\n";
        $responseFinal .= "Content-Length: ". strlen($response) ."\n\n";
        $responseFinal .= "$response";

        $this->_debug("HTTP response:\n$responseFinal", 7);
        $this->write($responseFinal);
    }
}
