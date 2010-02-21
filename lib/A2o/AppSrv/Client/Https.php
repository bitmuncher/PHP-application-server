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
 * @see A2o_AppSrv_Client_Http
 */
require_once 'A2o/AppSrv/Client/Http.php';



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
class A2o_AppSrv_Client_Https extends A2o_AppSrv_Client_Http
{
    /**
     * Raw peer certificate
     */
    public $sslCert = NULL;

    /**
     * Raw peer certificate
     */
    public $sslCertData = NULL;

    /**
     * Peer CN as specified in certificate subject
     */
    public $sslCN = NULL;



    /**
     * Constructor
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
     * Destructor
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
