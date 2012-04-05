<?php
include_once 'ToolkitServiceSet.php';
include_once 'ToolkitServiceXML.php';
include_once 'ToolkitServiceParameter.php';

define('CONFIG_FILE', 'toolkit.ini');

class ToolkitService {
	// changed private to protected	
	protected $XMLServiceLib = XMLSERVICELIB;
	protected $plug    = 'iPLUG512K';//for ibm_db2. for  odbc $plug='iPLUGR512K'.  consider 32k with small data 
    protected $plugSize = '512K'; // 4K, 32K, 512K, 65K, 512K, 1M, 5M, 10M, 15M
    protected $plugPrefix = 'iPLUG'; // db2. for odbc is iPLUGR because ODBC requires Result sets.
	protected $InternalKey = XMLINTERNALKEY;
	protected $ControlKey = '';
	protected $InputXML = '';
	protected $OutputXML = '';
	protected $XMLWrapper = false;
	protected $conn = NULL;	// database connection
	protected $error = '';
	protected $cpfErr = '';
	protected $debug = false;
	protected $requestCdata = true; // whether to ask XMLSERVICE to wrap its output in CDATA to protect reserved XML characters
	protected $v5r4 = false; // whether to ask XMLSERVICE to carefully use features that v5r4 can handle
	protected $convertToCcsid = null; // Note: this feature not complete yet. Specify CCSID for difficult or DBCS CCSID needs.   
	protected $encoding = "ISO-8859-1"; /*English 
	                                    Hebrew:ISO-8859-8 */
	// TODO test mode too see PLUGCFG1 and 2. It's called $subsystem but really parameters for *SBMJOB() control 
	protected $subsystem = "ZENDSVR/ZSVR_JOBD/XTOOLKIT"; // in test mode, use QSVRJOB/QDFTJOBD                                    
    protected $transport = false;
	protected $prestart = false;
	protected $stateless = false;
	protected $performance = false;
	protected $license = false;
	protected $sbmjob = true; // default ZENDSVR subsystem
	protected $idleTimeout = null; // created for Compat. Wrapper (CW)
	protected $db2 = false;
	protected $db = null;
	protected $debugLogFile = '/usr/local/zendsvr/share/ToolkitApi/debug.log';

	protected $_schemaSep = '.'; // schema separator. A dot or slash
    protected $_validSeparators = array('.', '/');
	
	protected $_outputVarsToExport = array();
	protected $_isPersistent = false;
	
	static protected $instance = NULL;

	static function getInstance($database = '*LOCAL', $user = '', $password = '', $extensionPrefix = '', $isPersistent = false)
	{
		
		if(self::$instance == NULL){
			$ToolkitService = __CLASS__;
			self::$instance=new ToolkitService($database, $user, $password, $extensionPrefix, $isPersistent);
		}
		return self::$instance;
	}
	
	public function __destruct(){	
	/* call to disconnect()  function to down connection */		
		if($this->conn == null)
			self::$instance = NULL;
		
	}
	

	/**
	 * Return true if an instance of this object has already been created.
	 * Return false if no instance has been instantiated.
	 * 
	 * Useful when users need to know if a "toolkit connection" has already been made.
	 * Usage:
	 * $isConnected = ToolkitService::hasInstance();
	 * 
	 * @return boolean
	 */
	static function hasInstance() {

		if (isset(self::$instance) && is_object(self::$instance)) {
		    return true;	
		} else {
			return false;
		}
	} //(hasInstance())

	
	// was private. also added default values for user, pw, extension prefix, persistent.
	// if passing an existing resource and naming, don't need the other params.
	protected function __construct($databaseNameOrResource , $userOrI5NamingFlag='' , $password='' , $extensionPrefix='', $isPersistent = false) {
		
		// get settings from INI file
        $xmlServiceLib = getConfigValue('system', 'XMLServiceLib', 'ZENDSVR');
        $debug = getConfigValue('system', 'debug', false);
        $debugLogFile = getConfigValue('system', 'debugLogFile', false);
        $encoding = getConfigValue('system', 'encoding', 'ISO-8859-1'); // XML encoding 
        $sbmjobParams = getConfigValue('system', 'sbmjob_params'); 
        $v5r4 = getConfigValue('system', 'v5r4', false);

        // set service parameters to use in object.
		$serviceParams = array('debug'             => $debug,
                               'debugLogFile'      => $debugLogFile,
                               'XMLServiceLib'     => $xmlServiceLib,
                               'encoding'          => $encoding);
                            
        if ($sbmjobParams) {
        	// optional. Don't specify if not given in INI.
            $serviceParams['subsystem'] = $sbmjobParams;
        } //(if sbmjobParams)

        if ($v5r4) {
        	// optional. Don't specify if not true (default is false).
        	$serviceParams['v5r4'] = $v5r4;
        } //(if sbmjobParams)
        
        
        // set up params in this object. Includes debugging, logging.
        $this->setToolkitServiceParams($serviceParams);
		
		if ($this->debug) {
		    $this->debugLog("Creating new conn with database: '$databaseNameOrResource', user or i5 naming flag: '$userOrI5NamingFlag', ext prefix: '$extensionPrefix', persistence: '$isPersistent'\n");
        }  



		/*
		 * try {
    toolkit->callsomething($xml);
    echo 'Never get here';
}
catch (Exception $e)
{
    echo 'Exception caught: ',  $e->getCode(), " : ", $e->getMessage(),  "\n";
}
		 */
		

		  $this->setdb($extensionPrefix);	

		  // if db resource was passed in
		  if (is_resource($databaseNameOrResource)) {
		      // we already have a db connection, passed in by user.
		      $conn = $databaseNameOrResource;
		      $i5NamingFlag = $userOrI5NamingFlag;
		      // slash if true, dot if false.
		      $schemaSep = ($i5NamingFlag) ? '/' : '.'; 
		      $this->setToolkitServiceParams(array('schemaSep' => $schemaSep) );
              
		      if ($this->debug) {
		          $this->debugLog("Re-using an existing db connection with schema separator: $schemaSep");
		      }
		  	
		  } else { 
              // Resource was not passed. Create a new db connection.
		      $databaseName = $databaseNameOrResource;
		      $user = $userOrI5NamingFlag;
		      if ($this->debug) { 
		  	      $this->debugLog("Going to create a new db connection.");
		      }
		      $this->setIsPersistent($isPersistent);
		  	  $conn = $this->db->connect( $databaseName, $user, $password, array('persistent'=>$this->getIsPersistent()));
		  
		  } //(is_resource)
		  
		  
		  if (!$conn) {
		  	    // added code in addition to message. 
		  	    // need to get TRANSPORT's way to get an error.
		  	    // TODO add other transports, soap, etc. not just db.
		  	    // Should set error in the transport (db2),
		  	    // a common error routine in the base class, let's say.
		  	    // The subclasses can say why.
		  	    // make generic "geterrorcode" and "geterrormessage" methods.
		  	    // base class can have transportStateErrorMessage and tSECode
		  	    // and the adapters fill those in. Not sql-specific.		
		  	    $sqlState = $this->db->retrieveSqlState();
				$this->error = $this->db->retrieveError();
				
				$this->debugLog("Failed to connect. sqlState: $sqlState. error: $this->error");
				// added sqlstate
				throw new Exception($this->error, $sqlState);				 
		  }
		$this->conn = $conn;
		return $this;
	}
	
	public function __clone ()	{		
		throw new Exception(" Use getInstance() function according to create a new ToolkitService object");	
	}
 
	protected function setdb( $extensionPrefix = '')
	{/*add here code for select another database extension */
	
	    if(trim($extensionPrefix) == ''){
	    	$extension_name_prfx = DBPROTOCOL;
	    }
	    else
	    	$extension_name_prfx = $extensionPrefix;//ibm_db2, odbc. 
	    
		if( $extension_name_prfx === 'ibm_db2'){
			if( function_exists('db2_connect')) {
				
				$this->db2 = true;
			} else { 
				throw new Exception("Extension $extension_name_prfx not loaded.");
			} //(if db2_connect exists)				
		} //(if extensinon prefix is 'ibm_db2')
			
		if($extension_name_prfx === 'odbc'){
			if( function_exists('odbc_connect')) {
				
				$this->db2 = false ;
			} else { 
				throw new Exception("Extension $extension_name_prfx not loaded.");
			} //(odbc_connect exists)					
		} //(if extensinon prefix is 'odbc')
		
		if($this->db2){
			 $this->plugPrefix = 'iPLUG';
		 	 include_once 'Db2supp.php';
		     $this->db  = new db2supp();
		} else {
			 include_once 'Odbcsupp.php';
			 //for odbc will be used another default stored procedure call
			 $this->plugPrefix = 'iPLUGR'; // "R" = "result set" which is how ODBC driver returns param results
			 $this->setToolkitServiceParams(array('plug'=>'iPLUGR512K') );
			 $this->db =  new odbcsupp(); 
		} //(if db2)
		return;		
	}
	
	
	
	
	public function setToolkitServiceParams ( array $XmlServiceOptions )
	{
	   if(isset( $XmlServiceOptions['XMLServiceLib'])){
	  		$this->XMLServiceLib = $XmlServiceOptions['XMLServiceLib'];
	   }
	   
	  if( isset ($XmlServiceOptions['InternalKey'])){
	  	$this->InternalKey = $XmlServiceOptions['InternalKey'];
	  } 

	  // if plug name specified, use it. Otherwise, see if size was specified.
	  // Can generate plug name from the size.
	  if( isset ($XmlServiceOptions['plug'])){
	  	$this->plug = $XmlServiceOptions['plug'];
	  } else {
	  	// plug not set; perhaps plug size was.
	  	if( isset ($XmlServiceOptions['plugSize'])){
		    
	  		$this->plugSize = $XmlServiceOptions ['plugSize'];
	  		// Set plug based on plugSize
  			$this->plug = $this->plugPrefix . $this->plugSize; 
		    
	  	} //(plugSize)
	  } //(if plug)
	  
	  /* reset "Debug" flag of  Toolkit service php class*/
	  if( isset ($XmlServiceOptions['debug'])){
	  	$this->debug  = $XmlServiceOptions['debug'];
	  } 

	  /* reset "DebugLogfile" flag of  Toolkit service php class*/
	  if( isset ($XmlServiceOptions['debugLogFile'])){
	  	$this->debugLogFile  = $XmlServiceOptions['debugLogFile'];
	  } 
	  
	  
	  if( isset ($XmlServiceOptions['encoding']) && ($XmlServiceOptions['encoding'])){
	  	$this->encoding  = $XmlServiceOptions['encoding'];
	  }

	  if( isset ($XmlServiceOptions['schemaSep']) && in_array($XmlServiceOptions['schemaSep'], $this->_validSeparators)) {
	      $this->_schemaSep  = $XmlServiceOptions['schemaSep'];
	  }
	  
	  if( isset ($XmlServiceOptions['subsystem'])){
	  {
	  		if (  strstr($XmlServiceOptions['subsystem'], "/") ){
	  			/* verify that subsystem name and subsytem decscription (and, optionally, job name) 
	  			 * are presented in the string*/	  			
	  		$this->subsystem  = $XmlServiceOptions['subsystem'];
	  		}
	  	}
	  }

          /* reset "prestart" flag of  Toolkit service php class*/
	  if( isset ($XmlServiceOptions['prestart'])){
	  	$this->prestart  = $XmlServiceOptions['prestart'];
	  } 
	  /* reset "stateless" flag of  Toolkit service php class*/
	  if( isset ($XmlServiceOptions['stateless'])){
	  	$this->stateless  = $XmlServiceOptions['stateless'];
	  } 
	  /* reset "sbmjob" flag of  Toolkit service php class*/
	  if( isset ($XmlServiceOptions['sbmjob'])){
	  	$this->sbmjob  = $XmlServiceOptions['sbmjob'];
	  } 
	  /* reset "sbmjobd" flag of  Toolkit service php class*/
	  if( isset ($XmlServiceOptions['sbmjobd'])){
	  	$this->sbmjobd  = $XmlServiceOptions['sbmjobd'];
	  }
	  
	  /* reset "idleTimeout" flag of  Toolkit service php class*/
	  if( isset ($XmlServiceOptions['idleTimeout'])){
	  	$this->idleTimeout  = $XmlServiceOptions['idleTimeout'];
	  } 
 
	  if( isset ($XmlServiceOptions['cdata'])){
	  	$this->requestCdata  = $XmlServiceOptions['cdata'];
	  } 

	  if( isset ($XmlServiceOptions['v5r4'])){
	  	$this->v5r4  = $XmlServiceOptions['v5r4'];
	  }
	   
	  
	  
	} //(setToolkitServiceParameters)
	
	// TODO make more general, not have to specify each param as a separate class attribute.
	public function getToolkitServiceParam( $paramName ){
		if( $paramName == 'plug' )
			return $this->plug;

	    if( $paramName == 'XMLServiceLib'){
	  		return $this->XMLServiceLib;
	    }
			
	    if( $paramName == 'v5r4'){
	    	return $this->v5r4;
	    }
	    
	    if( $paramName == 'sbmjob'){
	    	return $this->subsystem;
	    }
	     
	    
    	return false;
	}	
	
	public function disconnect()
	{
		$this->PgmCall("OFF", NULL);
	
		$this->db->disconnect($this->conn);
    	$this->conn = null; 
	}

	// same as disconnect but also really close persistent database connection.
	public function disconnectPersistent()
	{
		$this->PgmCall("OFF", NULL);
	
		$this->db->disconnectPersistent($this->conn);
    	$this->conn = null; 
	}
	
    public function debugLog($stringToLog) {

    	if ($this->debug) {
    	    error_log ( "$stringToLog",
	                    3, // means append
	                    $this->debugLogFile );
		} //(debug)
    	
    } //(debugLog)
	
	
	public function isDb2()
	{
	   return $this->db2;	
	}
	
	public function setDb2()
	{
	   return $this->db2 = true;	
	}
	
    public function callTransportOnly()
	{
		$this->transport = true;
		$this->PgmCall("NONE", NULL, NULL ,NULL); 
		$this->transport = false;
	}
	
	public function performanceData()
	{
		$this->performance = true;
		$outputXML = $this->PgmCall("NONE", NULL, NULL ,NULL); 
		$this->performance = false;
		return $outputXML;
	}
	
	public function licenseXMLSERVICE()
	{
		$this->license = true;
		$outputXML = $this->PgmCall("NONE", NULL, NULL ,NULL); 
		$this->license = false;
		return $outputXML;
	}


	// sendRawXml is good for testing XML input and output, or implementing un-wrappered XML.
	// Not needed. ExecuteProgram does this. It sends raw XML in.
/*	public function sendRawXml($xmlIn) {
	
	$this->XMLWrapper = new XMLWrapper( $this->encoding );	

	
	$disconnect = false;
		$OutputXML ='';		
		
		$this->VerifyPLUGName();
		$this->VerifyInternalKey();
	 	
		
		if( $this->db2){
			$stmt =  "call $this->XMLServiceLib{$this->_schemaSep}$this->plug(?,?,?,?)";
		}
		else {	// odbc			
		    $stmt =  "call $this->XMLServiceLib{$this->_schemaSep}$this->plug(?,?,?)";
		}	
		$bindArray = array(
			"InternalKey"=> $this->getInternalKey(),
			"ControlKey" => $this->getControlKey($disconnect),
			"InputXML" => $xmlIn,
		 	"OutputXML"=> '', 
			"disconnect"=>$disconnect
		);

		$OutputXML = $this->db->execXMLStoredProcedure( $this->conn, $stmt, $bindArray );
        return $OutputXML;
		
	}	
*/	
	
	// $options can include 'func' and 'opm'
	public function PgmCall($pgmname, $lib,
	                        $InputParam =  NULL,
							$ReturnValue = NULL, 
							$options = NULL ) {
	
	$this->error = '';
	$disconnect  = false;
    $optional  = false;
	$function = NULL;
	$this->XMLWrapper = new XMLWrapper( array('encoding'       => $this->encoding, 
	                                          'convertToCcsid' => $this->convertToCcsid),
			                                  $this
			 );	
	$InputParamArray = ProgramParameter::ParametersToArray($InputParam);
	$ReturnValueArray = ProgramParameter::ParametersToArray($ReturnValue);

	if( strcmp ( $pgmname, "OFF" ) === 0){
		$disconnect = true;
	}		

    if( strcmp ( $pgmname, "NONE" ) === 0){
		$optional = true;
	}
	
	$OutputParamArray = false;	
	
	if( isset($options['func']))/*call service program*/
	    $function = $options['func'];
	    
	if( $disconnect || $optional) {

		$InputXML = $this->XMLWrapper->disconnectXMLIn();			
	}
	else {
		$InputXML = $this->XMLWrapper->buildXmlIn ($InputParamArray,
												   $ReturnValueArray,
									               $pgmname, $lib, $function );				    
	}	
	// log it
    
	$OutputXML = $this->ExecuteProgram(  $InputXML, $disconnect);

	if ($OutputXML != '') {
		// got results
		$OutputParamArray = $this->XMLWrapper->getParamValueFromXml( $OutputXML );
		if( is_array($OutputParamArray )){			 
		//parse array and update return values by data returned from program
			if(isset($ReturnValueArray )){
				$this->updateRetValueArray($OutputParamArray, $ReturnValueArray);	
			}				
		} else {
			$this->error = $this->XMLWrapper->getLastError();
		}
    }
		
	
	unset ( $this->XMLWrapper );
	/*output array incliudes as parmeters as return values.*/				
	return $OutputParamArray;	 	
	}

	public function getErrorMsg() {
		return $this->error;
	}
	public function getErrorCode() {
		return $this->cpfErr;
	}
	 
	protected function  updateRetValueArray($OutputParamArray , $ReturnValueArray)
	{
		$RetValsArray  = $OutputParamArray['retvals'];
		$RetValsArrayCount = count($ReturnValueArray);
	 	if( $RetValsArrayCount == 0)
	 		 return;
	 	 			
		for($i = 0; $i < $RetValsArrayCount; $i++){
			$name = $ReturnValueArray[$i]['var'];
			if(isset($RetValsArray[$name])){
				$ReturnValueArray[$i]['data'] = $RetValsArray[$name];
			}	
		}		
	}
	
	public function getOutputParam(array $OutputArray)
	{
		if( !is_array($OutputArray))
		   return false;
		   
		if( isset($OutputArray['io_param']))
			return $OutputArray['io_param'];

        return false;
	}
	
	// Send any XML to XMLSERVICE toolkit. The XML doesn't have to represent a program.
	protected function ExecuteProgram( $InputXML, $disconnect=false )
	{
	
		$OutputXML ='';		
		$this->error = '';
		
		$this->VerifyPLUGName();
		$this->VerifyInternalKey();
	 	
		
		if( $this->db2){
			$stmt =  "call $this->XMLServiceLib{$this->_schemaSep}$this->plug(?,?,?,?)";
		}
		else {	/*odbc*/			
		    $stmt =  "call $this->XMLServiceLib{$this->_schemaSep}$this->plug(?,?,?)";
		}	
		
		$controlKeyString = $this->getControlKey($disconnect);
		
		$bindArray = array(
			"InternalKey"=> $this->getInternalKey(),
			"ControlKey" => $controlKeyString,
			"InputXML" => $InputXML,
		 	"OutputXML"=> '', 
			"disconnect"=>$disconnect
		);

				
		// if debug mode, log control key, stored procedure statement, and input XML.
		if( $this->debug ) {
	        $this->debugLog ( "\nExec start: " . date("Y-m-d H:i:s") . "\nIPC: '" . $this->getInternalKey() . "'. Control key: $controlKeyString\nStmt: $stmt\nInput XML: $InputXML\n");
	        $start = microtime(true);
		} //(if debug)
		
		// can return false if prepare or exec failed.
		$OutputXML = $this->db->execXMLStoredProcedure( $this->conn, $stmt, $bindArray );

		if ($this->debug && $OutputXML) {
			$end = microtime(true);
			$elapsed = $end - $start;
			$this->debugLog ("Output XML: $OutputXML\nExec end: " .  date("Y-m-d H:i:s") . ". Seconds to execute: $elapsed.\n\n");
		} //(if debug and there's some output XML)

		// if false returned, was a database error (stored proc prepare or execute error)
		if($OutputXML === false) {
			$this->error = $this->db->retrieveError();

			$serviceLibrary = $this->getToolkitServiceParam('XMLServiceLib');
            if ($this->error == 22003) {
                //22003 = On db2_execute, plug was too small to get output XML 
                $plug = $this->getToolkitServiceParam('plug');
                $errorReason = "Error: Most likely, XML was too large for the current plug size. Plug: '$plug'.";
            } elseif ($this->error == 42704) {
            	//42704 = obj not found
            	$errorReason = "Error: Toolkit not found in specified service library ($serviceLibrary).";
            } elseif ($this->error == 42833) {
                //42833 = The qualified object name is inconsistent with the naming option.
                $errorReason = "Error: i5 naming mode is not correct (or doesn't match the naming mode of an existing persistent database connection).";
            } else {
			    $errorReason = "Toolkit request failed. Possible reason: a CCSID not matching that of system, or updated PTFs may be required."; 
	            $errorReason .= " Database code (if any): $this->error";
            } //(if error == 42704)			
			
			logThis($errorReason);
	        die($errorReason);
			
		} //(if($OutputXML === false) )
		
		
		if( $disconnect ) {    
		    $this->db->disconnect($this->conn);
		    
		    if ($this->debug) {
			    $this->debugLog("Db disconnect requested and done.\n");
		    } //(debug)
		} //(disconnect)
			
		return $OutputXML;
					
	} //(ExecuteProgram)
	
	
	// exec could be 'pase', 'system,' 'rexx', or 'cmd'
	// $command can be a string or an array of multiple commands	
	public function CLCommand($command, $exec = '') {
		
		$this->XMLWrapper = new XMLWrapper( array('encoding'       => $this->encoding, 
	                                              'convertToCcsid' => $this->convertToCcsid) );	
		
		$this->error = '';
		$inputXml = $this->XMLWrapper->buildCommandXmlIn($command, $exec);

		// rexx and pase are the ways we might get data back.
		$expectDataOutput = in_array($exec, array('rexx', 'pase'));
        $parentTag = ($exec == 'pase') ? 'sh' : 'cmd';
        
		$this->VerifyPLUGName();
		
		// send the XML, running the command
		$outputXml = $this->ExecuteProgram( $inputXml, false );
		
		// fix encoding if needed. TODO remove if corrected on server end.
		// Fixed on server XMLSERVICE 1.62. don't need anymore.
		//$outputXml = $this->XMLWrapper->cleanXml($outputXml);
		
		// encode any ampersands, which the parser cannot handle.
		// Not needed any longer because XMLSERVICE can wrap output in CDATA tags.
//   	    $cleanOutputXml = preg_replace('/&[^; ]{0,6}.?/e', "((substr('\\0',-1) == ';') ? '\\0' : '&amp;'.substr('\\0',1))", $outputXml);
		
		// get status: error or success, with a real CPF error message, and set the error code/msg.
		$successFlag = $this->XMLWrapper->getCmdResultFromXml( $outputXml, $parentTag);
		
		if ($successFlag) {
			$this->cpfErr = 0;
			$this->error = '';
		} else {
		    $this->error = $this->XMLWrapper->getLastError();	
		}
		
		
 	    if ($successFlag && $expectDataOutput) {

 	    	// if we expect to receive data, extract it from the XML and return it. 	    	
		    $outputParamArray = $this->XMLWrapper->getRowsFromXml( $outputXml, $parentTag);
		    unset($this->XMLWrapper);
		    return $outputParamArray;
	    } else {
		    // don't expect data. Return true/false (success);
		    unset($this->XMLWrapper);
		    return $successFlag;
	    } //(if success and expect data)		
		
	} //(CLCommand)
	
    public function CLInteractiveCommand($command) {

    	return $this->CLCommand($command, 'pase');
    }

    public function qshellCommand($command) {

    	// send a command through the QSH interpreter
    	// and interpret error results.
        
    	// Handle errors and combine array-based results into a single string.
    	
    	// TODO consider doubling user-supplied single quotes to escape them in QSH
    	
    	$qshCommand = "QSH CMD('$command')";
    	
    	// will return an array of results.
    	$resultArray = $this->CLInteractiveCommand($qshCommand);
    	
    	if (empty($resultArray) || !is_array($resultArray)) {
    		logThis("Result of QSH command $qshCommand is empty or not an array."); 
    		return false;
    	}
    	
    	// get status line
    	$firstLine = trim($resultArray[0]);
    	
    	/* possible first line:
    	 * QSH0005: Command ended normally with exit status 0.  [means A-OK]
    	 * QSH0005: Command ended normally with exit status 1.  [look for a CPF message in next line]
    	 * QSH0005: Command ended normally with exit status 127. [problem finding command]
    	 * QSH0006: Command was ended by signal number yyy
    	 * QSH0007: Command was ended by an exception [haven't seen this one yet]
    	*/
    	
    	$qshCode = substr($firstLine, 0, 7);
    	
    	switch ($qshCode) {
    		
    		case 'QSH0005':
    			// get status code.
    			// String will be something like: QSH0005: Command ended normally with exit status 1.
    			// But in German: Befehl wurde normal mit Ausfèhrungsstatus &1 beendet.
    			// look for a space (\b is word boundary), then the number, then a period OR another word boundary. 
    			$pattern = '/\b([\d]+)[\b\.]/';
		        // look for a match
		        $numMatches = preg_match($pattern, $firstLine, $matches);
    			if ($numMatches) {
    			    $exitStatus = $matches[1]; // replacement parenthetical bit, i.e. the number.
    			} else {
    			    $this->cpfErr = $qshCode;
    			    $this->error = 'Could not get exit code. Check toolkit error log for error.';
    			    logThis("Result of QSH command $qshCommand was error: $firstLine.");
    				return false;
    			}
    			
    			if ($exitStatus == '0') {
    				
    				// SUCCESS!!!
    				// everything is fine.
    				// Return the rest of the array (without the status line).
    				if (count($resultArray) > 1) {
    					return array_slice($resultArray, 1);
    				} //(count > 1)
    				
    			} else {
    				// look for a CPF code in second line. May not always be there.
    				// will resemble: catsplf: 001-2003 Error CPF3492 found processing spool file QSYSPRT, number 2.
    				//            or: catsplf: 001-2373 Job 579272/QTMHHTP1/WSURVEY400 was not found."
    				// 
    				// TODO extract CPF code.
    				// TODO distinguish between status 1 and 127, if helpful
    				if (isset($resultArray[1])) {
    					$secondLine = trim($resultArray[1]);
    					$this->cpfErr = $secondLine;
    					return false;
    				}
    			} //(exitStatus)	
    			/*} elseif ($exitStatus == '127') {
    				// look for errmsg in second line (e.g. cannot find command)
    			} //(if $exitStatus)
    			*/
    			
    			
    			
    			break;
    			
    		case 'QSH0006':
    		case 'QSH0007':
    			$this->cpfErr = $qshCode;
    			$this->error = 'Check toolkit error log for error.';
    			logThis("Result of QSH command $qshCommand was error: $firstLine.");
    			return false;
    			break;
    	} //(switch $qshcode)
    	
    	
    } //(qshellCommand)
    
    
	// new. uses REXX to return output params and CPF codes
	// Slower than 'cmd' or 'system'
	public function ClCommandWithOutput($command) {

	    return $this->CLCommand($command, 'rexx');
	} //(ClCommandWithOutput)
	
	// new. uses 'system' to return CPF codes
	// slightly slower than regular cmd but faster than rexx
	// (Actually it's faster than cmd in recent tests. It depends, perhaps.)
	// $command can be a string or an array.
	public function ClCommandWithCpf($command) {

		return $this->CLCommand($command, 'system');
	} //(ClCommandWithCpf)
	

		
	static function AddParameter($type, $io, $comment, $varName = '', $value, $varing = 'off', $dimension = 0) {
		return array ('type' => $type,       /*storage*/ 
					  'io' => $io,           /*in/out/both*/ 
					  'comment' => $comment, /*comment*/
		              'var' =>  $varName,    /*variable name*/
					  'data' => $value,      /*value */
					  'varying' => $varing,  /*varing on/varing off */
					  'dim' =>   $dimension);/*number of array elements*/
	}
	
    static function AddParameterChar( $io, $size , $comment,  $varName = '', $value , $varying = 'off',$dimension = 0) {
    		return ( new CharParam( $io, $size , $comment,  $varName, $value , $varying ,$dimension = 0));  		    	
   	}
	
	static function AddParameterInt32( $io,  $comment,  $varName = '', $value, $dimension = 0 ) {
		return(new Int32Param ($io, $comment, $varName, $value, $dimension));    		
	}
    //Size ($comment,  $varName = '', $labelFindLen = null) {
	static function AddParameterSize($comment,  $varName = '', $labelFindLen ) {
		return(new SizeParam ($comment, $varName, $labelFindLen));    		
	}

	//SizePack5 ($comment,  $varName = '', $labelFindLen = null) {
	static function AddParameterSizePack($comment,  $varName = '', $labelFindLen ) {
		return(new SizePackParam ($comment, $varName, $labelFindLen));    		
	}
	
	
	static function AddParameterInt64( $io,  $comment,  $varName = '', $value, $dimension = 0 ) {
		return(new Int64Param( $io, $comment, $varName, $value, $dimension));       			
	}
	
	static function AddParameterUInt32( $io,  $comment,  $varName = '', $value ,$dimension =0) {
		return ( new  UInt32Param ($io, $comment, $varName, $value, 'off', $dimension)) ;
    		
	}
	
	static function AddParameterUInt64( $io,  $comment,  $varName = '', $value,$dimension=0 ) {
		return ( new UInt64Param($io, $comment, $varName, $value, $dimension));   		    	
    		
	}
	static function AddParameterFloat( $io,  $comment,  $varName = '', $value,$dimension=0 ) {
		return( new FloatParam($io, $comment, $varName, $value, $dimension));   	    		
	}
	
	static function AddParameterReal( $io,  $comment,  $varName = '', $value,$dimension=0 ) {
		return  ( new RealParam($io, $comment, $varName, $value, $dimension));	
 	}
 	
    static function AddParameterPackDec( $io, $length ,$scale , $comment,  $varName = '', $value, $dimension=0) {    		    	
    	return (new PackedDecParam($io, $length ,$scale , $comment,  $varName, $value, $dimension));	
	}
	
    static function AddParameterZoned( $io, $length ,$scale , $comment,  $varName = '', $value, $dimension=0) {    		    	
    	return (new ZonedParam($io, $length ,$scale , $comment,  $varName , $value, $dimension));		
	}

	// "hole" paramter is for data to ignore
	static function AddParameterHole( $size , $comment='hole') {
    		return ( new HoleParam( $size, $comment));  		    	
   	}
	
	
    static function AddParameterBin( $io, $size , $comment,  $varName = '', $value,$dimension =0) {    		    	
    	return (new BinParam($io, $size , $comment,  $varName, $value,$dimension));		
	}
	static function AddParameterArray($array){
		foreach ($array as $element)
		{
			$params[] = self::AddParameter($element['type'],
										   $element['io'],
										   $element['comment'], 
				        			       $element['var'], 
				        			       $element['data'],
				        			       $element['varing'],
				        			       $element['dim']);
		}	
		return $params;
	}
	
    static function AddReturnParameter($descr, $var, $data) {
		return array ('descr' => $descr,  'var' => $var, 'data' => $data );
	}

	static function AddDataStruct(array $parameters, $name='struct_name', $dim=0, $by='', $isArray=false, $labelLen = null){
		return (new DataStructure($parameters, $name, $dim, $isReturnParam = false, $by, $isArray, $labelLen));
	}

	// added. 
	static function AddErrorDataStruct(){
		return (new DataStructure(self::GenerateErrorParameter(), 'errorDs', 0));
	}
	
	// use this one when you need a zero-byte error structure,
	// which is useful to force errors to bubble up to joblog,
	// where you can get more information than in the structure.
	static function AddErrorDataStructZeroBytes(){
		return (new DataStructure(self::GenerateErrorParameterZeroBytes(), 'errorDs', 0));
	}
	
	
	
	// pure XML version
	// Pass in $paramNum to get a numeric parameter number for the comment.
/*	static function getErrorDataStructXml($paramNum = 0) {
		$paramNumStr = ($paramNum) ? ($paramNum . '.') : '';
		return "<parm io='both' comment='$paramNumStr Error code structure'>
                 <ds var='errorDs'>
                   <data var='errbytes' type='10i0' comment='Size of DS'>144</data>
                   <data var='err_bytes_avail' type='10i0' comment='if non-zero, an error occurred' />
                   <data var='exceptId' type='7A' varying='off' comment='CPF code'>0000000</data>
                   <data var='reserved' type='1h' varying='off' />
                   <data var='excData' type='128a' varying='off' comment='replacement data. Not sure we want it. Causes problems in XML.' />
                 </ds>
              </parm>";
	}
*/	
	// use a zero (0) bytes length to force errors to bubble up to job. It's easier for us to get full message text from joblog that XMLSERVICE toolkit provides.
	// Anyway, the QSNDDTAQ API doesn't have an error struct, so this way we can be consistent---get all errors in joblog.
	static function getErrorDataStructXml($paramNum = 0) {
		$paramNumStr = ($paramNum) ? ($paramNum . '.') : '';
		return "<parm io='both' comment='$paramNumStr Error code structure'>
                 <ds var='errorDs'>
                   <data var='errbytes' type='10i0' comment='Size of DS. Use 0 to force errors to bubble up to the job'>0</data>
                 </ds>
              </parm>";
	}
	
		
	
	// this DS is common to many IBM i APIs.
	static function getListInfoApiXml($paramNum = 0) 
	{
		$paramNumStr = ($paramNum) ? ($paramNum . '.') : '';
		
		return "<parm io='out' comment='$paramNumStr List information'>
		    <ds var='listinfo' comment='Open list information format (common to all lists)'>
		     <data var='totalRecords' comment='Total records' type='10i0' />
		     <data var='returnedRecords' comment='Records returned' type='10i0' />
		     <data var='requestHandle' comment='Request handle: binary/hex' type='4b' />
		     <data var='recordLength' comment='Record length' type='10i0' />
		     <data var='infoComplete' comment='Information complete indicator. C=complete, I=incomplete, P=partial, more to get in Get List Entries' type='1a' />
		     <data var='timeAndDateCreated' comment='Time and date created' type='13a' />
		     <data var='listStatus' comment='List status indicator' type='1a' />
		     <data var='reserved' comment='Reserved' type='1h' />
		     <data var='lengthReturned' comment='Length of information returned' type='10i0' />
		     <data var='firstRecordNumber' comment='Number of first record returned in receiver variable' type='10i0' />
		     <data var='reserved2' comment='Reserved (another one)' type='40h' />
		    </ds> 
		  </parm>";
	} //(getListApiXml)

	// this DS is common to many IBM i APIs.
	static function getNumberOfRecordsDesiredApiXml($paramNum = 0) 
	{
		$paramNumStr = ($paramNum) ? ($paramNum . '.') : '';
		
		return "<parm io='in' comment='$paramNumStr Number of records to return. Use zero to offload to Get List Entries API'>
                  <data var='numRecsDesired' type='10i0'>0</data>
                </parm>";
	} //(getNumberOfRecordsDesiredApiXml)
	
	// this DS is common to many IBM i APIs.
	static function getSortInformationApiXml($paramNum = 0) 
	{
		$paramNumStr = ($paramNum) ? ($paramNum . '.') : '';
		
		// assume no sort is required. Could add a sort in future if needed.
		return "<parm io='in' comment='$paramNumStr Sort information' >
                 <ds var='sortInfo'>
                   <data var='numSortKeys' comment='Number of keys to sort on. Use zero' type='10i0'>0</data>
                 </ds>
               </parm>
		";
	} //(getSortInformationApiXml)
	
	// this DS is common to many IBM i APIs.
	static function getDummyReceiverAndLengthApiXml($paramNum = 1, $lengthOfReceiverVariable) 
	{
		$paramNumStr = $paramNum . '.';
		$paramNumStrNext = ($paramNum + 1) . '.';
		
		// assume no sort is required. Could add a sort in future if needed.
		return "<parm io='out' comment='$paramNumStr receiver. do not actually receive anything here. Wait till Get List Entry'>
                  <ds var='receiver' comment='length $lengthOfReceiverVariable'>
                    <data type='1h' comment='dummy. Real receiver will be gotten in list entry API call' />
                  </ds>
                </parm>
                <parm io='in' comment='$paramNumStrNext Length of receiver variable (actual structure to be given in Get List Entry)'>
                  <data var='receiverLen' type='10i0' comment='length $lengthOfReceiverVariable'>$lengthOfReceiverVariable</data>
                </parm>";
	} //(getSortInformationApiXml)
	
	
	
	
	static function AddRetValDataStruct(array $parameters, $name='struct_name', $dim=0){
		return (new DataStructure($parameters, $name, $dim, true));
	}	

	public function getLastError() {
		return $this->error;
	}
	
	public function isError() {
		if($this->error != '')
			return true;
		return false;
	}

	// changed. was private
	protected function getInternalKey(){
		return $this->InternalKey;
	}
	
	// construct a string of space-delimited control keys based on properties of this class.
	protected function getControlKey($disconnect = false){

		$key = ''; // initialize
		
		if( $disconnect ){
		    return "*immed";
		}
		/*
		if(?) *justproc
		if(?) *debug
		if(?) *debugproc
		if(?) *nostart
		if(?) *rpt*/
	    
        // Idle timeout supported by XMLSERVICE 1.62
	    // setting idle for *sbmjob protects the time taken by program calls
	    // Do that with *idle(30/kill) or whatever the time in seconds.
   	    if( trim($this->idleTimeout ) != '' ) {
			$key .= " *idle($this->idleTimeout/kill)"; // ends idle only, but could end MSGW with *call(30/kill)
	    }

	    // if cdata requested, request it. XMLSERVICE will then wrap all output in CDATA tags.
	    if( $this->requestCdata ) {
	    	//$onOff = ($this->requestCdata) ? 'on' : 'off';
			$key .= " *cdata";
	    }

	    
	    
/* may be used an additional set of parameters
	// get performance last call data (no XML calls)
	    if ($this->performance) {
			return "*rpt";
		}
		// get license information (no XML calls)
	    if ($this->license) {
			return "*license";
		}
		// check proc call speed (no XML calls)
	    if ($this->transport) {
			return "*justproc";
		}

	
		// Attributes:
		$key = "*none";
		// stateless calls in stored procedure job
		 * 
		 * Add *here, which will run everything inside the current PHP/transport job
		 * without spawning or submitting a separate XTOOLKIT job.
		 */
	    if ($this->stateless) {
			$key .= " *here";
			
	    } else {
	    	// not stateless, so could make sense to supply *sbmjob parameters for spawning a separate job.
		    if( trim($this->subsystem ) != '' ) {
			   $key .= " *sbmjob($this->subsystem)";
	        } //(if subsystem *sbmjob specified)
	    	
		} //(if stateless)

/*		
		// not allow child spawn or sbmjob
	    if ($this->prestart) {
			$key .= " *nostart";
		}
		// add spawn and sbmjob not allowed
	    if ($this->sbmjob){ 
			$key .= " *sbmjob";
			if ($this->sbmjobd) {
				$key .= " ($this->sbmjobd)";
			}
		}
		*/
		return trim($key); // trim off any extra blanks on beginning or end

	} //(getControlKey)
	
//to set a plug name use function setToolkitServiceParams('plug'=>'iPLUGR512K')
 
	protected function VerifyPLUGName ()
	{
		// if plug already set, don't need to set it now.
		if($this->plug != ''){
		    return;
		}
		//Sets the default plug.    
		$size = 512;
		$Add = 'K';//or M

		/*4, 32, 65, 512 K*/
       /* 1M, 5M, 10M up to 15M ...*/
		
		//in case that following SQL error error:
		//Length in a varying-length or LOB host variable not valid. SQLCODE=-311
		//verify that all last blob ptfs are applied on i5 machine
		//set the $this->plug = "iPLUG4K";, 
		//it calls the program that returns data via char storage
		$this->plug = $this->plugPrefix() . $size . $Add;
		
		//$this->plug = "iPLUG512K";
	    //$this->plug = "iPLUG1M";
		//$this->plug = "iPLUG4K";
		
	} //(VerifyPLUGName)
	

    // Ensures that an IPC has been set
	protected function VerifyInternalKey(){
            // if we are running in stateless mode, there's no need for an IPC key.
	    if ($this->stateless) {
	    	$this->InternalKey = '';
  	        return;
	    }
	
 		if(trim($this->InternalKey) == ''){	 		
	 		if(session_id() != '' )/*if programmer already started session, use it*/
	 			$this->InternalKey  = "/tmp/".session_id();
	 		else	 		
				$this->InternalKey  = "/tmp/". $this->generate_name();
		}
	}
  protected function getXmlOut() {
		return $this->OutputXML;
	}
		
  public function GetConnection()
  {
  	return  $this->conn;
  }

  public function generate_name()
	{/*move to i5 side*/
		$localtime = localtime();
	    $rndName = sprintf( "ZS%d%d%d%d", 
					 $localtime[0],/*s*/
					 $localtime[1],/*min*/
					 $localtime[2],/*our*/
					 $localtime[3] /*day*/					 
					  );
		return $rndName;
 }
 /*creates Data structure that going to be used in lot of
  * i5 API's for error handling                         */
  public function GenerateErrorParameter()
  {  	
	    $ErrBytes   = 144;
		$ErrBytesAv = 144;
		$ErrCPF     = '0000000';
		$ErrRes     = ' ';  
		$ErrEx      = ' ';
		// changed $this to self so can work in static context
		$ds[] = self::AddParameterInt32('in',  "Bytes provided", 'errbytes', $ErrBytes);
		$ds[] = self::AddParameterInt32('out', "Bytes available",'err_bytes_avail', $ErrBytesAv);
		$ds[] = self::AddParameterChar('out',7, "Exception ID",   'exceptId', $ErrCPF);
		$ds[] = self::AddParameterChar('out',1, "Reserved",       'reserved', $ErrRes);
		$ds[] = self::AddParameterHole('out',128, "Exception data", 'excData',  $ErrEx); // can be bad XML so make it a hole	
		return $ds;
  }

  // specify zero bytes so error bubbles up to joblog where we can get description, etc.
  public function GenerateErrorParameterZeroBytes()
  {  	
	    $ErrBytes   = 0;
		// changed $this to self so can work in static context
		$ds[] = self::AddParameterInt32('in',  "Bytes provided (zero makes errors bubble up to joblog)", 'errbytes', $ErrBytes);
		return $ds;
  }
  
  
  	public function verify_CPFError( $retPgmArr, $functionErrMsg )	 
	{
		// it's an error if we didn't get output array at all
		// in that case, look for general "error" material
		
		// $functionErrMsg is obsolete now.
		
        if(!is_array($retPgmArr)){        	
        	$this->error = $this->getLastError();
            return true;
        }
           
		$retArr = $retPgmArr['io_param'];		 

		// get errorDs from named ds (CW style) or directly (PHP toolkit style)
		$errorDs = (isset($retArr['errorDs'])) ? $retArr['errorDs'] : $retArr; 
		
		// If there's an error structure and some error info was returned.
		// (err_bytes_avail is the official, reliable way to check for an error.)
		if( isset($errorDs) && ($errorDs['err_bytes_avail'] > 0)){		    	
		    $this->cpfErr = $errorDs['exceptId'];
		    // TODO future, get actual error text from joblog
		    $this->error = $functionErrMsg;	    	
			return true;  //some problem
		} else {
				// no CPF error detected.
				$this->cpfErr = '0000000';
				$this->error = '';
				return false;
	    }
	} //(verify_CPFError)
  
  
  public function ParseErrorParameter( array $Error )
  {
  	if(!is_array($Error))
  	    return false;
  	
	// If there's an error structure and some error info was returned.
	// (err_bytes_avail is the official, reliable way to check for an error.)
  	if( isset ($Error['exceptId']) && ($Error['err_bytes_avail'] > 0)){
  		$CPFErr = $Error['exceptId'];	
  		/*Add here array parse if need */
  	}
  	return $CPFErr;
  }
  //for sql calls via already opened connection.
  public function getSQLConnection()
  {
		return $this->conn;	
   }
   
  public function executeQuery($stmt){ 
  	 $Txt = $this->db->executeQuery($this->getConnection(), $stmt);
	
  	 if(!is_array($Txt)){
  	
  	   		$this->error = $this->db->retrieveError();
  	   		// TODO need a message to throw
  	   		throw new Exception($this->error);
  	 } 	 
     return $Txt;
  }

  	public function setIsPersistent($isPersistent = false) 
	{
		if (is_bool($isPersistent)) {
			$this->_isPersistent = $isPersistent;
		} else {
			throw new Exception("setIsPersistent: boolean expected");
		}
	} //(setIsPersistent)

	public function getIsPersistent() 
	{
		return $this->_isPersistent;
	} //(setIsPersistent)

	/* Method: getJobAttributes() 
	 * 
	 * Retrieve several attributes of the current job.
	 * Return array of attributes (key/value pairs) or false if unsuccessful.
	 * Purpose: 1. Helps user find toolkit job; identifies libraries and CCSID used by toolkit connection
	 *          2. Shows example of getting output from CL commands
	 * Sample output:
	 * Array(
          [JOB] => QSQSRVR
          [USER] => QUSER
          [NBR] => 240164
          [CURUSER] => QTMHHTTP
          [SYSLIBL] => QSYS       QSYS2      QHLPSYS    QUSRSYS    DBU80      QSYS38
          [CURLIB] => *NONE
          [USRLIBL] => QTEMP      QGPL       MYUTIL
          [LANGID] => ENU
          [CNTRYID] => US
          [CCSID] => 37
          [DFTCCSID] => 37)
	 */
	public function getJobAttributes() {
		 
	    // Retrieve job attributes. Note: the CCSID attributes use (?N), not (?), because they are numeric.
        $cmdString = 'RTVJOBA JOB(?) USER(?) NBR(?) CURUSER(?) SYSLIBL(?) CURLIB(?) USRLIBL(?) LANGID(?) CNTRYID(?) CCSID(?N) DFTCCSID(?N)';

        // Send the command; get output array of key/value pairs. Example: CURUSER=>FRED, ...
        $outputArray = $this->ClCommandWithOutput($cmdString);
       
        return $outputArray;
       
	} //(public function getJobAttributes())
	
  
} //(class ToolkitService)

// Class ends here. 

// TODO integrate these functions into toolkit class. Back-ported from CW.

	// return value from toolkit config file, 
    // or a default value, or
    // false if not found.
    function getConfigValue($heading, $key, $default = null) {
		// TODO store once in a registry or the like
		
		// true means use headings
		$config = parse_ini_file(CONFIG_FILE, true);
		if (isset($config[$heading][$key])) {
		    return $config[$heading][$key];
		} elseif (isset($default)) {
		    return $default;
		} else {
			return false;
		}
	
    } //(getConfigValue)
	
	
	function logThis($msg) {
	    $logFile = getConfigValue('log','logfile');
	    if ($logFile) {
		    // it's configured so let's write to it. ("3" means write to a specific file)
		    $formattedMsg = "\n" . microDateTime() . ' ' . $msg; 
		    error_log($formattedMsg, 3, $logFile); 
	    }
	    
    } //(logThis)
    
    function microDateTime()
    {
        list($microSec, $timeStamp) = explode(" ", microtime());
        return date('j M Y H:i:', $timeStamp) . (date('s', $timeStamp) + $microSec);
    }
   