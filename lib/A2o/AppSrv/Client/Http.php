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
     * Raw peer certificate
     */
    public $sslCert = NULL;

    /**
     * Parsed peer certificate array
     */
    public $sslCertData = NULL;

    /**
     * Peer CN as specified in certificate subject
     */
    public $sslCN = NULL;



    /**
     * Raw HTTP request
     */
    public $requestRaw        = NULL;
    public $requestRawHeaders = NULL;
    public $requestRawBody    = NULL;



    /**
     * HTTP request headers parsed
     */
    public $requestMethod       = NULL;
    public $requestUri          = NULL;
    public $requestProtocol     = NULL;
    public $requestHeaders      = array();
    public $requestHeadersAssoc = array();



    /**
     * HTTP response values
     */
    public $responseProtocol      = 'HTTP/1.0';
    public $responseStatusCode    = '200';
    public $responseStatusMessage = 'OK';
    public $responseHeaders       = array(
        'server'       => 'A2o_AppSrv - Standalone preforking PHP application server framework',
        'connection'   => 'close',
        'content-type' => 'text/html',
    );
    public $responseBody          = '';



    /**
     * Checks for client certificate and parses it
     * Calls parent constructor.
     *
     * @param    parent     Parent object
     * @param    resource   Stream handle
     * @param    string     Remote IP address
     * @param    integer    Remote TCP port
     * @return   void
     */
    public function __construct ($parent, $stream, $address, $port)
    {
        parent::__construct($parent, $stream, $address, $port);

        // Check for cert and try to get data from it
        $contextOptions = stream_context_get_options($stream);
        if (isset($contextOptions['ssl']['peer_certificate'])) {
            $sslCert = $contextOptions['ssl']['peer_certificate'];

            // Is this cert really provided or is this a relic from previous connection
            if (get_resource_type($sslCert) == 'OpenSSL X.509') {
                $this->sslCert     = $sslCert;
                $this->sslCertData = openssl_x509_parse($this->sslCert);
                $this->sslCN       = $this->sslCertData['subject']['CN'];
            }
        }
    }



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
        $this->requestRaw        = '';
        $this->requestRawHeaders = '';
        $this->requestRawBody    = NULL;

        // Read and parse request line
        $requestLine = $this->readLine();
        $requestRaw  = $requestLine;
        $this->_debug("Request line from client: $requestLine", 8);
        $this->___parseRequestLine($requestLine);

        // Read the request headers
        $requestHeader = '';
        $contentLength = false;
        do {
            $requestHeader = $this->readLine();
            $this->requestRaw        .= $requestHeader;
            $this->requestRawHeaders .= $requestHeader;
            $this->_debug("Headers line from client: $requestHeader", 8);

            // If we receive header 'Content-Length: xxx' - parse it
            if (preg_match('/^content-length: ([0-9]+)\s*$/i', $requestHeader, $matches)) {
                $contentLength = $matches[1];
                $this->_debug("Content-Length header from client: $contentLength", 8);
            }

            // Check if this is an end of request headers
            if (preg_match('/^[\r\n]+$/', $requestHeader)) {
                break;
            }
        } while (true);
        $this->_debug("HTTP request headers from client: $this->requestRawHeaders", 7);

        // Parse request headers
        $this->___parseRequestHeaders();

        // Read request body
        if ($contentLength !== false) {
            $this->requestRawBody  = $this->read($contentLength);
            $this->requestRaw     .= $this->requestRawBody;
            $this->_debug("HTTP request body from client:\n$this->requestRawBody", 7);
        }
    }



    /**
     * Parses request line from client
     *
     * @param    string    HTTP request line
     * @throws   A2o_AppSrv_Client_Exception
     * @return   void
     */
    public function ___parseRequestLine ($requestLine)
    {
        $requestLine = trim($requestLine);

        if (!preg_match('/^([A-Z]+) ([^ ]+) (HTTP\/1.[01])$/', $requestLine, $matches)) {
            throw new A2o_AppSrv_Client_Exception("Invalid request line: $requestLine");
        }
        $this->requestMethod   = $matches[1];
        $this->requestUri      = $matches[2];
        $this->requestProtocol = $matches[3];
    }



    /**
     * Parses request headers into array
     *
     * @throws   A2o_AppSrv_Client_Exception
     * @return   void
     */
    public function ___parseRequestHeaders ()
    {
        // Split headers into array of strings
        $requestHeadersRaw = trim($this->requestRawHeaders);
        $matches = preg_split('/[\r]\n/', $requestHeadersRaw, -1);

        // Loop and assign headers
        foreach ($matches as $headerRaw) {
            if (!preg_match('/^([^:]+):(.*)/', $headerRaw, $headerMatches)) {
                throw new A2o_AppSrv_Client_Exception("Invalid request header: $headerRaw");
            }
            $headerField = $headerMatches[1];
            $headerValue = trim($headerMatches[2]);
            $this->requestHeaders[]                  = $headerRaw;
            $this->requestHeadersAssoc[$headerField] = $headerValue;
        }
    }



    /**
     * Sets the response header.
     *
     * If header names are CI identical, later prevails
     *
     * @param    string   Header name
     * @param    string   Header value
     * @return   void
     */
    public function setResponseHeader ($name, $value)
    {
        $name = strtolower(trim($name));
        if ($name == 'content-length') {
            throw new A2o_AppSrv_Client_Exception('Header Content-Length is added automatically');
        }
        $this->responseHeaders[$name] = $value;
    }



    /**
     * Removes response header.
     *
     * Case insensitive matching
     *
     * @param    string   Header name
     * @return   void
     */
    public function removeResponseHeader ($name)
    {
        $name = strtolower(trim($name));
	if (isset($this->responseHeaders[$name])) {
	    unset($this->responseHeaders[$name]);
	}
    }



    /**
     * writeResponse
     *
     * Writes response to the client
     *
     * @param    string    HTTP response content without headers
     * @return   void
     */
    public function writeResponse ($response)
    {
        $this->_debug("-----> ". __CLASS__ .'::'. __FUNCTION__ ."()", 9);

        // Generate response line line
        $responseFinal  = "$this->responseProtocol $this->responseStatusCode $this->responseStatusMessage\r\n";

        // Add headers
        foreach ($this->responseHeaders as $headerField => $headerValue) {
            $responseFinal .= ucfirst($headerField) .": $headerValue\r\n";
        }

        // Is response passed or what?
        $this->responseBody = $response;

        // Add content legth
        $responseFinal .= "Content-Length: ". strlen($response) ."\r\n";

        // Conclude headers
        $responseFinal .= "\r\n";

        // Add body
        $responseFinal .= "$response";

        // Send it to client
        $this->_debug("HTTP response:\n$responseFinal", 7);
        $this->write($responseFinal);
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
     * Cleans SSL resources and calls parent's destructor
     *
     * @return   void
     */
    public function __destruct ()
    {
        // Free cert resources
        if ($this->sslCert !== NULL) {
            @openssl_x509_free($this->sslCert);
        }
        unset($this->sslCert);
        $this->sslCert     = NULL;
        $this->sslCertData = NULL;
        $this->sslCN       = NULL;

        parent::__destruct();
    }
}
