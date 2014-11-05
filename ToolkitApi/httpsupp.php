<?php

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
 * AllowOverride None
 * order allow,deny
 * allow from all
 * SetHandler cgi-script
 * Options +ExecCGI
 * </Directory>
 *
 * @todo define common transport class/interface extended/implemented by all transports. They have much in common.
 *
 * @package ToolkitApi
 */
class httpsupp {

// TODO define common transport class/interface extended/implemented by all transports 	
// They have much in common.	
	
protected $last_errorcode = ''; // SQL State
protected $last_errormsg = ''; // SQL Code with message

protected $_ipc = null;
protected $_ctl = null;
protected $_url = null;
protected $_db = null;
protected $_user = null;
protected $_pw = null;

protected $_debug = null; // TODO see what for
	
	/**
	 * @param string $ipc route to XMLSERVICE job (/tmp/xmlibmdb2)
	 * @param string $ctl XMLSERVICE control (*sbmjob)
	 * @param string $url URL to xmlcgi.pgm (example: http://ibmi/cgi-bin/xmlcgi.pgm )
	 * @param string $debug *in|*out|*all (*in - dump XML input (call)) (*out - dump XML output (return)) (*all - dump XML input/output)
	 */
public function __construct(
		$ipc='/tmp/xmldb2',
		$ctl='*sbmjob',
		$url='http://example.com/cgi-bin/xmlcgi.pgm',
		$debug='*none' // *none, *in, *out, *all
)
{
}
	
	/**
	 * @param $xmlIn
	 * @param $outSize
	 * @return string
	 */
public function send($xmlIn,$outSize)
{
	// http POST parms
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
	$opts = array('http' =>
			array(
					'method'  => 'POST',
					'header'  => 'Content-type: application/x-www-form-urlencoded',
					'content' => $postdata
			)
	);

	$context  = stream_context_create($opts);
	// execute (call IBM i)
	$linkall = $this->getUrl();
	if (!$linkall) {
		die('HTTP transport URL was not set');
	}
	$clobOut = file_get_contents($linkall, false, $context);
	$clobOut = $this->driverJunkAway($clobOut);
	if ($this->_debug == '*all' || $this->_debug == '*in') echo "IN->".$clobIn;
	if ($this->_debug == '*all' || $this->_debug == '*out') echo "OUT->".$clobOut;
	return $clobOut;
	
} //(send)
	
	/**
	 * @return string
	 */
public function getErrorCode(){

	return $this->last_errorcode;
}
	
	/**
	 * @return string
	 */
public function getErrorMsg(){

	return $this->last_errormsg;
}
	
	/**
	 * @param $errorCode
	 */
protected function setErrorCode($errorCode) {
	$this->last_errorcode = $errorCode;
}
	
	/**
	 * @param $errorMsg
	 */
protected function setErrorMsg($errorMsg) {
	$this->last_errormsg = $errorMsg;
}
	
	/**
	 * @param $ipc
	 */
public function setIpc($ipc) {
	$this->_ipc = $ipc;
}
	
	/**
	 * @return null
	 */
public function getIpc() {
	return $this->_ipc;
}
	
	/**
	 * @param string $ctl
	 */
public function setCtl($ctl = '') {
	$this->_ctl = $ctl;
}
	
	/**
	 * @return null
	 */
public function getCtl() {
	return $this->_ctl;
}
	
	/**
	 * @param string $url\
	 */
public function setUrl($url = '') {
	$this->_url = $url;
}
	
	/**
	 * @return null
	 */
public function getUrl() {
	return $this->_url;
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
	if (! $clobOut) return $clobOut;

	// result set has extra data (junk)
	$fixme = '</script>';
	$pos = strpos($clobOut,$fixme);
	if ($pos > -1) {
		$clobOut = substr($clobOut,0,$pos+strlen($fixme));
	}
	// maybe error/performance report
	else {
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
public function http_connect($db = '*LOCAL', $user, $pw, $options=array()) {
	
	if (!$db) {
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
public function connect($database = '*LOCAL', $user, $password, $options = null){
    return $this->http_connect($database, $user, $password, $options = array());

}
	/**
	 * @return null
	 */
protected function getUser() {
	return $this->_user;
}
	/**
	 * @return null
	 */
protected function getPw() {
	return $this->_pw;
}
	/**
	 * @return null
	 */
protected function getDb() {
	return $this->_db;
}

} //(end of class)