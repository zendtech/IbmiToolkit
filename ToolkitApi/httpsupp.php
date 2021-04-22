<?php
namespace ToolkitApi;

/**
 * httpsupp is an experimental class (not supported by Zend at this time) that 
 * provides an 'http' driverless transport.
 * 
 * To set up your server for HTTP/CGI look here:
 * http://174.79.32.155/wiki/index.php/XMLService/XMLService
 * and read: "Optional XMLSERVICE REST interface via RPG CGI (xmlcgi.pgm)" 
 *
 * transport: REST POST
 * HTTP based transport to XMLSERVICE:
 * script.php(http://ibmi/cgi-bin/xmlcgi.pgm)--->XMLCGI.PGM--->XMLSERVICE
 * Apache conf (httpd.conf):
 * ScriptAlias /cgi-bin/ /QSYS.LIB/XMLSERVICE.LIB/
 * 
 * <Directory /QSYS.LIB/XMLSERVICE.LIB/>
 *      AllowOverride None
 *      order allow,deny
 *      allow from all
 *      SetHandler cgi-script
 *      Options +ExecCGI
 * </Directory>
 * 
 * @todo define common transport class/interface extended/implemented by all transports. They have much in common.
 * 
 * @package ToolkitApi
 */
class httpsupp
{
    protected $last_errorcode;
    protected $last_errormsg;
    protected $_ipc;
    protected $_ctl;
    protected $_url;
    protected $_db;
    protected $_user;
    protected $_pw;
    protected $_debug;
    //ssl variables
    protected $_sslcafile;
    protected $_servername;
    
    /**
     * @param string $ipc route to XMLSERVICE job (/tmp/xmlibmdb2)
     * @param string $ctl XMLSERVICE control (*sbmjob)
     * @param string $url URL to xmlcgi.pgm (example: http://ibmi/cgi-bin/xmlcgi.pgm )
     * @param string $debug *in|*out|*all (*in - dump XML input (call)) (*out - dump XML output (return)) (*all - dump XML input/output)
     */
    public function __construct($ipc='/tmp/xmldb2', $ctl='*sbmjob', $url='http://example.com/cgi-bin/xmlcgi.pgm', $debug='*none', $sslcafile=null, $servername=null )
    {
        $this->setIpc($ipc);
        $this->setCtl($ctl);
        $this->setUrl($url);
        $this->setSSLCAFile($sslcafile);
        $this->setServerName($servername);
        $this->_debug = $debug;
    }

    /**
     * @param $xmlIn
     * @param $outSize
     * @return string
     */
    public function send($xmlIn, $outSize)
    {
        $clobIn  = $xmlIn;
        $clobOut = $outSize;
        $postdata = http_build_query(
                array(
                        'db2' => $this->getDb(),
                        'uid' => $this->getUser(),
                        'pwd' => $this->getPw(),
                        'ipc' => $this->getIpc(),
                        'ctl' => $this->getCtl(),
                        'xmlin' => $clobIn,
                        'xmlout' => $clobOut    // size expected XML output
                )
        );
        
        $opts = array(
            'http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded\r\n'.
                             'Content-Length: ' . strlen($postdata) . '\r\n',
                'content' => $postdata
            )
        );
        
        if (substr($this->getUrl(), 0, 8) == "https://")
        {
            //If this the URL is HTTPS, then Add SSL to options to support a 
            //secure connection.
            //Explanation of Options: http://phpsecurity.readthedocs.org/en/latest/Transport-Layer-Security-%28HTTPS-SSL-and-TLS%29.html
            $opts['ssl'] =   array(
                'verify_peer'   => true, //Require verification of SSL certificate used.
                'cafile'        => $this->getSSLCAFile(),  //__DIR__ . '/cacert.pem'  //Location of Certificate Authority file on local filesystem which should be used with the verify_peer context option to authenticate the identity of the remote peer. PEM or CRT file. You can use this file: http://curl.haxx.se/ca/cacert.pem 
                'verify_depth'  => 5, // Abort if the certificate chain is too deep.
                'CN_match'      => $this->getServerName(), //EX: secure.example.com
                'disable_compression' => true,
                'SNI_enabled'         => true,
                'ciphers'             => 'ALL!EXPORT!EXPORT40!EXPORT56!aNULL!LOW!RC4');
        }
    
        $context  = stream_context_create($opts);
        // execute (call IBM i)
        $linkall = $this->getUrl();
        
        if (!$linkall) {
            die('HTTP transport URL was not set');
        }

        $clobOut = file_get_contents($linkall, false, $context);
        $clobOut = $this->driverJunkAway($clobOut);
        
        if ($this->_debug == '*all' || $this->_debug == '*in') {
            echo "IN->".$clobIn;
        } 
        
        if ($this->_debug == '*all' || $this->_debug == '*out') {
            echo "OUT->".$clobOut;
        }
        
        return $clobOut;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->last_errorcode;
    }

    /**
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->last_errormsg;
    }

    /**
     * @param $errorCode
     */
    protected function setErrorCode($errorCode)
    {
        $this->last_errorcode = $errorCode;
    }

    /**
     * @param $errorMsg
     */
    protected function setErrorMsg($errorMsg)
    {
        $this->last_errormsg = $errorMsg;
    }

    /**
     * @param $ipc
     */
    public function setIpc($ipc)
    {
        $this->_ipc = $ipc;
    }

    /**
     * @return null
     */
    public function getIpc()
    {
        return $this->_ipc;
    }

    /**
     * @param string $ctl
     */
    public function setCtl($ctl = '')
    {
        $this->_ctl = $ctl;
    }

    /**
     * @return null
     */
    public function getCtl()
    {
        return $this->_ctl;
    }

    /**
     * @param string $url\
     */
    public function setUrl($url = '')
    {
        $this->_url = $url;
    }

    /**
     * @return null
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * @param null $sslcafile
     * @throws \Exception
     */
    public function setSSLCAFile($sslcafile = null)
    {
        if(substr($this->getUrl(), 0, 8) == "https://" && $sslcafile==null)
        {
            throw new \Exception('No SSL CA File specified.  Must pass in CA File to use https url');
        }
        $this->_sslcafile = $sslcafile;
    }

    /**
     * @return null
     */
    public function getSSLCAFile()
    {
        return $this->_sslcafile;
    }
    
    /**
     * @param string $servername\
     */
    public function setServerName($servername = null)
    {
        if($servername==null && isset($_SERVER['SERVER_NAME']))
        {
            $servername=$_SERVER['SERVER_NAME'];
        }
        $this->_servername = $servername;
    }

    public function getServerName()
    {
        return $this->_servername;
    }
    
    /**
     * 
     * @todo shared transport method
     * 
     * @param $xml
     * @return string
     */
    public function driverJunkAway($xml)
    {
        // trim blanks
        $clobOut = trim($xml);
        if (!$clobOut) return $clobOut;
    
        // result set has extra data (junk)
        $fixme = '</script>';
        $pos = strpos($clobOut,$fixme);
        
        if ($pos > -1) {
            $clobOut = substr($clobOut,0,$pos+strlen($fixme));
        } else { // maybe error/performance report
            $fixme = '</report>';
            $pos = strpos($clobOut,$fixme);
            
            if ($pos > -1) {
                $clobOut = substr($clobOut,0,$pos+strlen($fixme));
            }
        }
        
        return $clobOut;
    }

    /**
     * @param string $db
     * @param $user
     * @param $pw
     * @return $this
     */
    public function http_connect($db, $user, $pw)
    {
        // accommodate ('', '', '') style of connection
        if(!$db) {
            $db = '*LOCAL';
        }

        $this->_db = $db;
        $this->_user = $user;
        $this->_pw = $pw;
        
        return $this;
    }

    /**
     * @param string $database
     * @param $user
     * @param $password
     * @return httpsupp
     */
    public function connect($database, $user, $password)
    {
        // accommodate ('', '', '') style of connection
        if(!$database) {
            $database = '*LOCAL';
        }

        return $this->http_connect($database, $user, $password);
    
    }

    /**
     * @return null
     */
    protected function getUser()
    {
        return $this->_user;
    }

    /**
     * @return null
     */
    protected function getPw()
    {
        return $this->_pw;
    }

    /**
     * @return null
     */
    protected function getDb()
    {
        return $this->_db;
    }
}
