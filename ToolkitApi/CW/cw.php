<?php
// cw.php: Compatibility Wrapper for IBM i Toolkit for PHP
require_once 'cwclasses.php';

define('DB2_DEFAULT_USER', 'QTMHHTTP');
define('TO_BE_IMPLEMENTED', '999');

/**
 * @param string $dieOutput
 */
function d($dieOutput = 'x')
{
    if (is_array($dieOutput) || is_object($dieOutput)) {
        $str = '<pre>' . print_r($dieOutput, true) . '</pre>';
    } else {
        $str = $dieOutput;
    }
    die($str);
}

/**
 * create unique IPC code
 *
 * @param $user
 * @param int $connNum
 * @return string
 */
function makeIpc($user, $connNum = 0) {
    $ipcUser = ($user) ? $user : DB2_DEFAULT_USER;
    
    if (!$connNum) {
    	$connNum = uniqid();
    } 
    //$ipc = "/tmp/abc";///tmp/ipc_cw_$ipcUser" . "_" . uniqid();
    $ipc = "/tmp/ipc_cw_$ipcUser" . "_" . $connNum;

    return $ipc;
} //(makeIpc)

/**
 * @param $objName
 * @param string $defaultLib
 * @return array
 */
function splitLibObj($objName, $defaultLib = '') {
    // given an object name that MAY be qualified by a library and slash and perhaps a function,
    // such as "xxx/yyy(zzzz)".
    // split it up and return an array of the form:
    // [lib]=>xxx, [obj]=>yyy, [func]=>zzzz
    // If no library, that part will take the given default or be blank.
    //
    // The last part can be a service program function, if enclosed in parentheses.
    //
    // Uppercase lib and obj values, because on IBM i they are usually uppercase but not func (mixed case).
    $objName = trim($objName);
    
    $result = array('lib'=>'', 'obj'=>'', 'func'=>'');
    $parts = explode('/', $objName);
    if (count($parts) > 1) {
        // both library and object were provided.
        $result['lib'] = strtoupper($parts[0]);
        $obj = $parts[1];
    } else {
        // only object (no lib) given in name.
        $obj = $parts[0];
        // if a default lib, use it.
        if ($defaultLib) {
            $result['lib'] = strtoupper($defaultLib);
        }
    }
    
    // now see if there might be a service program subprocedure in there.
    // look in in parentheses
    if ($obj) {
        list($objLeft, $objFunc) = explode('(', $obj. '('); // hack: added extra '(' to ensure both vars get a value    
    } else {
        list($objLeft, $objFunc) = array('', '');
    }
    $result['obj'] = strtoupper($objLeft);
    if ($objFunc) {
        $result['func'] = trim($objFunc, '()');
    }
    
    
    return $result;
} //(splitLibObj)


/**
 * Creates and logs a new piece of activity and error.
 *
 * @see I5Error::setI5Error()
 * @param $errNum
 * @param string $errCat
 * @param string $errMsg Error message (often a CPF code but sometimes just a message)
 * @param string $errDesc Longer description of error
 * @return void
 */
function i5ErrorActivity($errNum, $errCat = I5_CAT_PHP, $errMsg = '', $errDesc = '')
{
    // set the error setting
    $errorObj = I5Error::getInstance();
    $errorObj->setI5Error($errNum, $errCat, $errMsg, $errDesc);
    
    // now log it if an error.
    if ($errNum != I5_ERR_OK) {
        logThis($errorObj);
    }
    
} //(i5ErrorActivity)

/**
 * Shortcut to i5ErrorActivity when an "AS400" or CPF error occurred.
 *
 * @see I5ErrorActivity()
 * @param string $errMsg Error message (often a CPF code but sometimes just a message)
 * @param string $errDesc Longer description of error
 * @return void
 */
function i5CpfError($errMsg = '', $errDesc = '')
{
    // Shortcut (fewer params) when have an IBM i (AS400) code and message.
    i5ErrorActivity(I5_ERR_PHP_AS400_MESSAGE, I5_CAT_PHP, $errMsg, $errDesc);
    
} //(i5CPFError)

/**
 * Shortcut to i5ErrorActivity when no error occurred. Clears error condition.
 *
 * @see I5ErrorActivity()
 *
 * @return void
 */
function noError()
{
    // Clear any error information.
    i5ErrorActivity(I5_ERR_OK, 0, '', '');
    
} //(noError)

/**
 * return toolkit object on success, false on error
 * if 'persistent' passed in options, do a persistent conn.
 * if CW_EXISTING_TRANSPORT_RESOURCE passed in options, use it as database conn.
 *
 * @param string $host
 * @param string $user
 * @param string $password
 * @param array $options
 * @return mixed
 */
function i5_connect($host='', $user='', $password='', $options=array()) {
    
		
    // ****** special warning. We do not support proprietary codepagefile.
    if (isset($options[I5_OPTIONS_CODEPAGEFILE])) {
        logThis ("Instead of using I5_OPTIONS_CODEPAGEFILE, please use a combination of I5_OPTIONS_RMTCCSID and I5_OPTIONS_LOCALCP as appropriate.");
    }  

    // create "change job" parameters as we go, based on options
    $jobParams = array();
    $libl = array();
    
    // check and store RMTCCSID option
    if (isset($options[I5_OPTIONS_RMTCCSID])) {
    	$rmtCcsid = trim($options[I5_OPTIONS_RMTCCSID]);
    	if (!is_numeric($rmtCcsid)) {
            i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Value of I5_OPTIONS_RMTCCSID must be numeric', 'Value of I5_OPTIONS_RMTCCSID must be numeric');
            return false; 
    	} else {
    		$jobParams['ccsid'] = $rmtCcsid;
    	} //(if !is_numeric)
    		
    } //(if (isset($options[I5_OPTIONS_RMTCCSID])))
 
    // check and handle I5_OPTIONS_INITLIBL option
    if (isset($options[I5_OPTIONS_INITLIBL])) {
    	$initLiblString = trim($options[I5_OPTIONS_INITLIBL]);
    	if (empty($initLiblString) || !is_string($initLiblString)) {
            i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Value of I5_OPTIONS_INITLIBL must be a string', 'Value of I5_OPTIONS_INITLIBL must be a string');
            return false; 
    	} else {
    		// initLibl must be a comma-delimited OR space-delimited (or both) list of libraries.
    	    /* Split the string by any number of commas or space characters,
             * which include " ", \r, \t, \n and \f
             * We can't use explode() because the delimiters may be a combination of space and comma.
             */
            $libl = preg_split('/[\s,]+/', $initLiblString);
    			
    		if (!is_array($libl) || empty($libl)) {
    			// if didn't get an array or it's empty, the string must have been bad.
    		    i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Value of I5_OPTIONS_INITLIBL not a comma-delimited string of libraries', 'Value of I5_OPTIONS_INITLIBL not a comma-delimited string of libraries');
    		    return false;
    		} //(if result wasn't an array)
    	} //(if !is_numeric)
    		
    } //(if (isset($options[I5_OPTIONS_RMTCCSID])))
    
    // check and store CW_PERSISTENT option
    $isPersistent = false; // default
    if (isset($options[CW_PERSISTENT])) {
    	if (!is_bool($options[CW_PERSISTENT])) {
            i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Value of CW_PERSISTENT must be boolean', 'Value of CW_PERSISTENT must be boolean');
            return false; 
    	} else {
    	    $isPersistent = $options[CW_PERSISTENT];
    	} //(if !is_numeric)
    } //(if (isset($options[CW_PERSISTENT])))
    
    $isNewConn = true; //default: it's a new conn, not reusing an old one.
    
    // check and handle I5_OPTIONS_PRIVATE_CONNECTION option
    if (isset($options[I5_OPTIONS_PRIVATE_CONNECTION])) {

    	// only works if connection is persistent, too.
    	if (!$isPersistent) {
    		// not persistent. this is an error.
    		i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'I5_OPTIONS_PRIVATE_CONNECTION was set but connection was not persistent. Try again using i5_pconnect().', 'I5_OPTIONS_PRIVATE_CONNECTION was set but connection was not persistent. Try again using i5_pconnect().');
            return false;
    	} //(!$isPersistent)
    	
    	// verify that the connection value is numeric
    	$privateConnNum = trim($options[I5_OPTIONS_PRIVATE_CONNECTION]);
    	if (!is_numeric($privateConnNum)) {
            i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Value of I5_OPTIONS_PRIVATE_CONNECTION must be numeric', 'Value of I5_OPTIONS_PRIVATE_CONNECTION must be numeric');
            return false; 
    	} //(if !is_numeric)
    	
    	// if a 0 is passed, generate a connection number that will be used in the IPC.
    	if ($privateConnNum == 0) {
    		// generate a number to be saved in connection class
    		// (Old toolkit used job number. New toolkit needs IPC before job is created.)
    		list($microseconds,$seconds) = explode(' ',microtime());
    		// remove decimal points or any other non-numeric from microseconds
    		$microseconds = preg_replace('/\D/', '', $microseconds);
            $privateConnNum = getmypid().$seconds.$microseconds; //getmypid() is a per-process number.
    	} else {
    		// re-using an old (non-zero) connection number. NOT a new connection.
            $isNewConn = false;
            
    	} //(if ($privateConnNum == 0))

    	// Note: if a nonexistent private connection number is passed in, XMLSERVICE will create the IPC.
    	// The old toolkit returned an error. 
    	// We COULD duplicate that "error" behavior by checking for the existence of the number in advance,
    	// but that might harm performance. 

    } // (if (isset($options[I5_OPTIONS_PRIVATE_CONNECTION])))
	
    	
    // check and handle I5_OPTIONS_IDLE_TIMEOUT
    // Number of seconds of not being used after which a persistent/private connection job will end.
    $idleTimeout = 0; // default of 0 means no timeout (infinite wait)
    if (isset($options[I5_OPTIONS_IDLE_TIMEOUT])) {
    
        $idleTimeout = $options[I5_OPTIONS_IDLE_TIMEOUT];
    
    } //(I5_OPTIONS_IDLE_TIMEOUT)
	
    $jobName = ''; // init
    if (isset($options[I5_OPTIONS_JOBNAME])) {
    
        $jobName = trim($options[I5_OPTIONS_JOBNAME]);
    
    } //(I5_OPTIONS_JOBNAME)
    	
    	
    
    // check and store CW_EXISTING_TRANSPORT_CONN option (such as to reuse a db connection)
    $existingTransportResource = null;
    $existingTransportI5NamingFlag = false;
    if (isset($options[CW_EXISTING_TRANSPORT_CONN])) {
    	if (!is_resource($options[CW_EXISTING_TRANSPORT_CONN])) {
            i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Value of CW_EXISTING_TRANSPORT_CONN must be a resource',  'Value of CW_EXISTING_TRANSPORT_CONN must be a resource');
            return false; 
    	} else {
    	    $existingTransportResource = $options[CW_EXISTING_TRANSPORT_CONN];
    	    $existingTransportI5NamingFlag = (isset($options[CW_EXISTING_TRANSPORT_I5_NAMING])) ? $options[CW_EXISTING_TRANSPORT_I5_NAMING] : false;
    	    
    	} //(if (!is_resource))
    } //(if (isset($options[CW_EXISTING_TRANSPORT_CONN])))
    

    
	// check and store CW_TRANSPORT_TYPE, if given. It's optional.
	$transportType = ''; // empty is ok.
	$iniTransportType = isset( $options [CW_TRANSPORT_TYPE]) ? $options [CW_TRANSPORT_TYPE] : getConfigValue('transport', 'transportType', 'ibm_db2');
	if ($iniTransportType) {
		$validTransports = array ('ibm_db2', 'odbc', 'http');
		if (!in_array($iniTransportType, $validTransports)) {
			// invalid transport specified.
			$errmsg = "Invalid CW_TRANSPORT_TYPE option ({$iniTransportType}). Omit or choose between " . explode(', ', $validTransports) . ".";
			i5ErrorActivity ( I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, $errmsg, $errmsg );
			return false;
		} else {
			// valid transport 
			$transportType = $iniTransportType;
		} // (if (!is_resource))
	} //(if (isset($options[CW_EXISTING_TRANSPORT_CONN])))
    
    
    
    // localhost is default if no host was specified. 
    if (!$host) {
        $host = 'localhost';
    }
    // convert host to dbname
    $dbname = getConfigValue('hosts', $host);
    
    if (!$dbname) {
        i5ErrorActivity(I5_CONN_TIMEOUT, I5_CAT_TCPIP, "Undefined host ('$host')", "Try 'localhost' instead, or specify lookup in " . CONFIG_FILE . " ($host=DBNAME).");
        return false;
    }
    
    
    $user = trim($user);
    if (!$user) {
/*        if user was not specified, use '', ''
        which will translate to QTMHHTTP
        when old toolkit is disabled, we won't be able to get 'i5comm.default_user'
        from PHP.INI, and db2 will say QTMHHTTP anyway (default value of i5comm.default.user, too),
        so just use it and document it.
*/        
        // OK. db2 can work with this.
        $user = '';
        $password = '';
        
    } else {
        
        // a user was supplied. Check user. Given the user, we also expect a password.

    	// user/pw rules
    	// TODO share these with i5_adopt_authority
    	
        // forbid QSECOFR and usernames starting with *. (don't want *CURRENT, etc.) 
        // TODO Actually, not sure if QSECOFR and special profiles should be forbidden. Check again with old toolkit.
        if ((strtoupper($user) == 'QSECOFR') || (substr($user, 0, 1) == '*') || empty($password) || (substr($password, 0, 1) == '*')) {
            i5ErrorActivity(I5_ERR_WRONGLOGIN, I5_CAT_PHP, 'Bad login user or password', 'Cannot connect with QSECOFR, blank password, or special profiles');
            return false;
        } //(if QSECOFR)

      
    } //(if !$user)

    
    // Check if INI file has asked us to always close previous connection before initiating new one within a single PHP request/script run. 
    // For compatibility with old toolkit behavior where a new connection would reset library lists and the like.
    // It's false by default for backward compatibility with older releases of CW.
    $forceNew = getConfigValue('cw', 'fullDbClose', false);
    
    // get instance of toolkit (singleton)
    try {
    	 if ($existingTransportResource) {
    	 	// use existing resource
    	 	$tkit = ToolkitServiceCw::getInstance($existingTransportResource, $existingTransportI5NamingFlag, '', '', $isPersistent, $forceNew);
    	 } else {
    	    // specify dbname, user, and password, transport type to create new transport
    	    $tkit = ToolkitServiceCw::getInstance($dbname, $user, $password, $transportType, $isPersistent, $forceNew); 
    	 } //(if ($existingTransportResource))
         
    	 // if getInstance() returned false (unlikely) 
    	 if (!$tkit) {
    	 	setError(I5_ERR_NOTCONNECTED, I5_CAT_PHP, 'Cannot get a connection', 'Cannot get a connection');
    	 } //(if (!$tkit))
    	 
     } catch (Exception $e) {

    // If user or password is wrong, give errNum I5_ERR_WRONGLOGIN with category I5_CAT_PHP.
         // Determine reason for failure.
         // Probably database authentication error or invalid or unreachable database.
        $code = $e->getCode();
        $msg = $e->getMessage();
                     
         switch ($code) {
             case 8001:
                 // Authorization failure on distributed database connection attempt.
                 // Poss. wrong user or password
                 $errNum = I5_ERR_WRONGLOGIN;
                 break;
             case 42705:
                 // db not found in relational directory.
                 // treat as host not found.
                 $errNum = I5_CONN_TIMEOUT;
                 break;
                 
             default:
                 $errNum = I5_ERR_PHP_AS400_MESSAGE;
                 break;
         }    
        i5ErrorActivity($errNum, I5_CAT_PHP,$code, $msg);
        return false;
    }

    // successfully instantiated toolkit connection and instance. Mark it as CW.
    $tkit->setIsCw(true);
    
    
    // override toolkit settings if nec.
    $sbmjobParams = getConfigValue('system', 'sbmjob_params');
    $xmlServiceLib = getConfigValue('system', 'XMLServiceLib', 'ZENDSVR');
    

    $stateless = false; // default

    $cwVersion = i5_version();
    
    // If we have a private conn, create an IPC based on it.
    $connectionMsg = '';
    if (isset($privateConnNum) && $privateConnNum) {
        $ipc = makeIpc($user, $privateConnNum);
        // save private conn number. Can be retrieved later with getPrivateConnNum
        $tkit->setPrivateConnNum($privateConnNum);
        $tkit->setIsNewConn($isNewConn);
		$connectionMsg = "Running statefully with IPC '$ipc', private connection '$privateConnNum'. CW version $cwVersion. Service library: $xmlServiceLib";
        
    } else {
    	
        // Not private. We may be stateless (inline).
        $stateless = getConfigValue('system', 'stateless', false);

        if ($stateless) {
            // don't need IPC if stateless, running in QSQ job.
            $ipc = '';
            $connectionMsg = "Running stateless; no IPC needed. CW version $cwVersion. Service library: $xmlServiceLib";
        } else {
	        // TODO does this make sense? Not stateless but not private? Any purpose to stateless setting in INI file?
        	// Not stateless, so create an IPC
            // TODO this will change based on persistent/nonpersistent logic
    	    // IPC to use in separate toolkit job using just user id and unique additions in makeIpc
            $ipc = makeIpc($user);
			$connectionMsg =  "Not private but not stateless; running with IPC '$ipc'. CW version $cwVersion. Service library: $xmlServiceLib";
        } //(if stateless)
    } //(privateconn and other options for generating IPC)
        
    
    // If INI file tells us to log CW's connection messages, do so.
    $logConnectionMsg = getConfigValue('log', 'logCwConnect', true);
    if ($logConnectionMsg) {
        logThis($connectionMsg);
    } //(if ($logConnectionMsg))

    
    // handle connection options (e.g. encoding). for those options we don't support, write to log.
       

    if ($jobName) {
        // override any values for the last parm of $sbmjobParams
        // check that $sbmjobParams is set and has at least one slash
        if (!isset($sbmjobParams) || empty($sbmjobParams)) {
        	
        	// not specified in .INI file, but may be a default in toolkit itself.
        	$toolkitDefaultSbmjob = $tkit->getToolkitServiceParam('sbmjobParams');

        	if (!$toolkitDefaultSbmjob) {
        	
                i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Job name was set but SBMJOB params were not. Please set SBMJOB params in toolkit.ini', 'Job name was set but SBMJOB params were not. Please set SBMJOB params in toolkit.ini or in ToolkitService.php default settings');
                return false;
        	} else {
        		// use the default as starting point
        		$sbmjobParams = $toolkitDefaultSbmjob;
        	}
        } //(if sbmjobParams not set)
        
        // check that sbmjob params has at least one, but not more than two, slashes in it. Final format: lib/jobd/jobname
        // Break string on forward slash.
        $sbmjobParts = explode('/', $sbmjobParams);
        $numParts = count($sbmjobParts);
        if ($numParts > 3 || $numParts < 1) {
            // should be either 1 or 2 slashes. Not more and not less 
            i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Job name was set but SBMJOB param string was not valid. Should have one or two forward slashes. Please set SBMJOB params in toolkit.ini', 'Job name was set but SBMJOB param string was not valid. Should have one or two forward slashes. Please set SBMJOB params in toolkit.ini');
            return false;
        } //(invalid sbmjob param string)
        
        // replace last part of string with job name.
        $sbmjobParts[2] = $jobName;
        // reconstruct sbmjob param string.
        
        $sbmjobParams = implode('/', $sbmjobParts);
        
    } //(jobName was specified)



        
    // set IPC and other settings
    
    $serviceParams = array('internalKey'       => $ipc,
                           'stateless'         => $stateless);

    // additional settings
    if ($idleTimeout) {
        $serviceParams['idleTimeout'] = $idleTimeout;
    }
    if ($sbmjobParams) {
    	// taking into account jobname preferences
        $serviceParams['sbmjobParams'] = $sbmjobParams;
    }

    // CW always retains data structure hierarchy (integrity) in parameters.
    $serviceParams['dataStructureIntegrity'] = true;

    // these will be in addition to, or overriding, any params set in toolkit service constructor.
    $tkit->setOptions($serviceParams);
    
    // initialize
    $cmdArray = array();

  
    // update the current job with options
    // TODO: do we have to run these if it's an existing IPC/connection, private conn? Perhaps the initialization happened already.
    if (count($jobParams)) {
    	$cmdStr = "CHGJOB";
    	foreach ($jobParams as $name => $value) {
    		$cmdStr .= " $name($value)";
    	}
    	// add string to array of commands to run in one shot
        $cmdArray[] = $cmdStr;	
    }
    
    // update library list
    if (count($libl)) {
    	// this is what the old toolkit seemed to do
    	// TODO send multiple adds in one big XML string
    	foreach ($libl as $lib) {
    		$cmdArray[] = "ADDLIBLE LIB($lib)";
    	}
    	
    } //(if count($libl);

    // run multiple commands if there are any.
    if (count($cmdArray)) {
    	// We collect a success flag but don't examine it.
    	// Old toolkit didn't do anything special if a connection option had invalid values.
    	// We COULD write a message to the log, at best.
        $success = $tkit->ClCommandWithCpf($cmdArray);
    } //(if count($cmdArray))

    
    // return toolkit object for other functions to use
    return $tkit;
    
} //(i5_connect)
/**
 * For persistent connections it's all about the IPC key. Try to call i5_connect's
 * guts but specify the key.
 *
 * @param $host
 * @param $user
 * @param $password
 * @param array $options
 * @return mixed
 */
function i5_pconnect($host, $user, $password, $options = array()) {
    
    // for private conns, it's all about the IPC key. Try to call i5_connect's guts but specify the key.

	
    // Includes private connections, too.
    $options[CW_PERSISTENT] = true;
    $conn = i5_connect($host, $user, $password, $options);
    
    return $conn;
    
} //(i5_pconnect)
/**
 * @param int $property
 * @param $tkit
 * @return bool
 */
function i5_get_property($property, $tkit) {
    // $property is integer
    
    if (!in_array($property, array(I5_NEW_CONNECTION, I5_PRIVATE_CONNECTION))) {
       i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Property must be I5_NEW_CONNECTION or I5_PRIVATE_CONNECTION', 'Property must be I5_NEW_CONNECTION or I5_PRIVATE_CONNECTION');
       return false; 
    } //(if !is_numeric)
    if (!is_object($tkit)) {
       i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Connection/toolkit param must be an object', 'Connection/toolkit param must be an object');
       return false; 
    } //(if !is_object)
    
    if ($property == I5_NEW_CONNECTION) {
    	// determine if connection was just created now or if it existed in the past.
    	// Seems only valid for private conns because it'd be possible to predict if initialization was done.
    	return $tkit->isNewConn();
    } //(new connection)    
	

    if ($property == I5_PRIVATE_CONNECTION) {
    	return $tkit->getPrivateConnNum();
    } //(private connection)    
    
    //$isnew = i5_get_property(I5_NEW_CONNECTION, $conn);
    //$privateConnNum = i5_get_property(I5_PRIVATE_CONNECTION, $retcon);    
    
} //(i5_get_property)

/**
 * Close job. not really necessary unless want to close it before script end.
 *
 * this should run automatically when script ends.
 *
 * @param ToolkitServiceCw $connection
 * @return bool
 */
function i5_close(&$connection = null) {
    
    // this should run automatically when script ends.
    
    // if conn not passed in, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }      

    // or explicitly asked to close by INI file.
    $fullDbClose = getConfigValue('cw', 'fullDbClose', false);
    if ($fullDbClose) {
        $connection->disconnect(); // disconnects database/transport
    } 
    
    $connection->__destruct();
    $connection = null;
    
    // TODO try/catch. if fail return false
    return true;
    
} //(i5_close)
/**
 * End job even if persistent connection.
 *
 * @param ToolkitServiceCw $connection
 * @return bool
 */
function i5_pclose(&$connection = null) {
    // end job even if persistent connection.
    
	// if conn not passed in, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
	
    $connection->disconnectPersistent();
    // normal cleanup now
    $connection->__destruct();
    $connection = null;
    // TODO try/catch. if fail return false
    return true;
    
} //(i5_pclose)

/**
 * Changes authority of the connection to a specific user. All actions will be executed as this user from now on.
 *
 * @param string $user
 * @param string $password
 * @param ToolkitServiceCw $connection [optional] the result of i5_connect(), or omit
 * @return boolean True on success, False on failure
 */
function i5_adopt_authority($user, $password, $connection = null)
{
    // if conn not passed in, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    

    // user/pw must be uppercase.
    $user = strtoupper($user);
    $password = strtoupper($password);

    
    // check that username and password vars are OK.
    // forbid QSECOFR and empty password (though special values such as *NOPWDCHK are OK) 
    if ((strtoupper($user) == 'QSECOFR') || empty($password)) {
        i5ErrorActivity(I5_ERR_WRONGLOGIN, I5_CAT_PHP, 'Bad login user or password', '');
        return false;
    } //(if QSECOFR) 
    
    // Get profile handle (checking u/p validity)
    $apiPgm = 'QSYGETPH';
    $apiLib = 'QSYS';
    
    $pwLen = strlen($password);
    $pwCcsid = '-1'; // -1 means 37 or DFTCCSID depending on password level
    
    $paramXml = 
    "<parm io='in' comment='1. user'>
      <data var='user' type='10A' varying='off'>$user</data>
    </parm>
    <parm io='in' comment='2. password'>
      <data var='pw' type='10A' varying='off'>$password</data>
    </parm>
    <parm io='out' comment='3. profile handle'>
      <data var='handleOut' type='12b' comment='really binary data not character' />
    </parm>\n" .    
    // param number 4
    ToolkitServiceCw::getErrorDataStructXml(4) . "\n";

    if (substr($password, 0, 1) != '*') {
    	/* No asterisk at the start, so this is an attempt at a real password, 
    	 * not a special pw value starting with an asterisk such as *NOPWD, *NOPWDCHK, or *NOPWDSTS.
    	 * Therefore, include pw len and CCSID, which must be omitted if pw is a special "*" value.
    	 */
    	 $paramXml .= 
    "<parm io='both' comment='5. length of password. Must be equal to the actual pw length.	'>
      <data var='pwLen' type='10i0'>$pwLen</data>
    </parm>
    <parm io='in' comment='6. CCSID of password'>
      <data var='pwCcsid' type='10i0'>$pwCcsid</data>
    </parm>";
         	
    } // (if (substr($password, 0, 1) != '*'))
     
  
           
    // In case of error, look for CPFs generated by specific other programs.
    // E.g. if userid is wrong, program QSYPHDL may report the CPF in joblog
    //$options = array('otherCpfPrograms' => array('QSYPHDL'));
    
    // now call the API!
    $retPgmArr = $connection->PgmCall($apiPgm, $apiLib, $paramXml, null);//, $options);

    if($connection->getErrorCode()) {
        i5CpfError($connection->getErrorCode(), $connection->getErrorMsg());
        return false;
    }
       
    // get handle from API we called.
    if (isset($retPgmArr['io_param']['handleOut'])) {
        $handle = $retPgmArr['io_param']['handleOut']; // handleOut defined in XML above
    }

    // if anything went wrong
    if (!isset($handle) || empty($handle)) {
    	i5ErrorActivity(I5_ERR_PHP_INTERNAL, I5_CAT_PHP, 'Unable to adopt authority. Check joblogs', '');
    	return false;
    }
    
    // now set the user profile via the handle.
    $apiPgm = 'QWTSETP'; // set profile
    $apiLib = 'QSYS';
    
    $paramXml = 
        "<parm io='in' comment='profile handle'>
            <data var='handleIn' type='12b'>$handle</data>
         </parm>\n" .    
        // error param is number 2
        ToolkitServiceCw::getErrorDataStructXml(2);
        
    // now call the "set handle" API!
    $retPgmArr = $connection->PgmCall($apiPgm, $apiLib, $paramXml);

    if($connection->getErrorCode()) {
        i5CpfError($connection->getErrorCode(), $connection->getErrorMsg());
        return false;
    }
    
    // Now close/release the handle (tidiness--handles are limited resources, about 20,000 per job).
    // Takes about .02 seconds to release handle.
    // If too slow, could combine QWTSETP and QSYRLSPH in a single call,
    // or call this API in *BATCH mode.
    $apiPgm = 'QSYRLSPH'; // release profile handle
    $apiLib = 'QSYS';
    
    $paramXml = 
        "<parm io='in' comment='profile handle'>
            <data var='handleIn' type='12b'>$handle</data>
         </parm>\n" .    
        // error param is number 2
        ToolkitServiceCw::getErrorDataStructXml(2);
        
    // now call the "release handle" API!
    $retPgmArr = $connection->PgmCall($apiPgm, $apiLib, $paramXml);

    if($connection->getErrorCode()) {
        i5CpfError($connection->getErrorCode(), $connection->getErrorMsg());
        return false;
    }
    
        
    // from this point on, no CPFs will happen in this function.
    noError();
    
    return true;
    
} //(i5_adopt_authority)

/**
 * Get array of error information for most recent action. Both numeric and descriptive string indexes are provided.
 *
 * Example:
 * [0] => 312
 * [1] => 9
 * [2] => CPF2292
 * [3] => *SECADM required to create or change user profiles.
 * [num] => 312
 * [cat] => 9
 * [msg] => CPF2292
 * [desc] => *SECADM required to create or change user profiles.
 *
 * @return array Error info for most recent action. See above for example.
 */
function i5_error($connection = null)
{   
    $errorObj = I5Error::getInstance();
    return $errorObj->getI5Error();
    
} //(i5_error)

/**
 * Returns error number for most recent action.
 *
 * @return int Error number equivalent to index 0 or 'num' from i5_error() Zero if no error
 */
function i5_errno($connection = null)
{
    $errArray = i5_error($connection);
    // return error number if it exists, otherwise an 'OK'.
    return (isset($errArray['num'])) ? $errArray['num'] : I5_ERR_OK;

} //(i5_errno)

/**
 * Returns error message or code for most recent action.
 * @return string Message equivalent to index 2 or 'msg' from i5_error()
 * It's often a CPF code.
 * Blank if no error
 */
function i5_errormsg($connection = null)
{
    $errArray = i5_error($connection);
    // return error number if it exists, otherwise an 'OK'.
    return (isset($errArray['msg'])) ? $errArray['msg'] : I5_ERR_OK;
    
} //(i5_errormsg)


// Command


/**
 * i5_command: Call a command with optional input/output parameters
 * Can also call a program that has no parameters
 * @param string $cmdString Basic command that can contain parameters or not, as desired
 * @param array $input Optional array of name => value pairs
 * The name describes the call input parameters. Names should match IBM i CL command parameter names.
 * If the array is empty or not provided, no input parameters are given.
 * Strings are not quoted; this is the user's responsibility.
 * If the value is an array, the list of contained values is passed in a space-delimited string.
 * @param array $output Optional information about how to get output from the command.
 * if value is a string, it's the name of PHP variable to receive the value.
 * example: array('userlibl'=>'usrlibl')
 * if value is an array, it's in the form: array($varName, 'type')
 * examples: array('syslibl' => array('syslibl', 'char(165)'))
 * array('ccsid' => array('myccsid', 'dec(5 0)'))
 * Note: the old toolkit seemed to use the lengths (165), (5 0) but we will ignore lengths. "dec" is used, if present.
 * @param ToolkitServiceCw $connection Optional connection object
 * @return boolean for success/failure
 */
function i5_command($cmdString, $input = array(), $output = array(), $connection = null) {

    // if conn not passed in, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
        
    // check that a string was passed in for command
    if(!$cmdString) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'missing password', 'missing password');
        return false;
    } elseif (!is_string($cmdString)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Command must be a string', 'Command must be a string');
        return false;
    } //(if not a string...)
    
    // start with string as passed in
    $workCmdString = $cmdString;
    
    // Add input params, if any
    if ($input && is_array($input) && count($input)) {
        foreach ($input as $key=>$value) {
            // if $value is itself an array, provide all values delimited by spaces.
            if (is_array($value)) {
                $valueStr = implode(" ", $value);
            } else {
                // just a string or integer
                $valueStr = $value;
            }
            $workCmdString .= " $key($valueStr)";
        } //(foreach input)
    } //(if input array not empty)
    
    // were output parms requested?
    $needOutputParms = ($output && is_array($output) && count($output));
    
    // Add output params, if any
    if ($needOutputParms) {
        
        // build a simple array for re-connecting parms to vars at end of routine
        $simpleParmVarArray = array();
        
        foreach ($output as $parmName=>$varDesc) {
            /* $parmName: name of parameter for CL command.
             * $varDesc:  if a string, it's the name of PHP variable to receive the value.
             *                             example:  array('userlibl'=>'usrlibl')
             *            if array, it's in the form: array($varName, 'type')
             *                             examples: array('syslibl' => array('syslibl', 'char(165)'))
             *                                       array('ccsid'   => array('myccsid', 'dec(5 0)'))
             * Note: the old toolkit seemed to use the lengths (165), (5 0) but we will ignore lengths. "dec" is used, if present.
             */                                   
                      
            $parmName = strtoupper($parmName);
                                    
            if (is_array($varDesc)) {
                // deal with array (see second set of examples above)
                $varDescCount = count($varDesc); 
                if ($varDescCount == 1) {
                	// working with alternate incorrect format array('syslibl'=>'char(165)')
                	// from early versions of CWTEST.PHP. Honor it.
                	$varName = key($varDesc);
                    $typeString = $varDesc[$varName];
                } elseif ($varDescCount == 2) {
                	// correct format ('myccsid', 'dec(5 0)') or the like
                	$varName = $varDesc[0]; // first 
                	$typeString = $varDesc[1]; // second
                } else {
                	// truly invalid array
           	        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, "wrong array size for $parmName", "wrong array size for $parmName");
           	        return false;
                } //(if varDescCount...)
                
                // was 'dec' passed in
                $isDecimalType = (false !== stripos($typeString, 'dec'));
            } else {
                // just a string
                $varName = $varDesc;
                $isDecimalType = false;    
            } //(if is_array($varDesc)
            
            $simpleParmVarArray[$parmName] = $varName; // for later

            // XML wants an "N" if numeric
            $questionString = ($isDecimalType) ? '?N' : '?';
            $outputParmStr = "$parmName($questionString)"; // e.g. CCSID(?N)
            
            $workCmdString .= " $outputParmStr";
        } //(foreach input)
    } //(if $needOutputParms)
    

    // we have our command string to use.
    $finalCmdString = $workCmdString;

    // pass command string into non-fast (but result-returning) or fast (true/false only) method.
    if ($needOutputParms) {

    	// Use slower but improved "result" (REXX) technique
        $result = $connection->ClCommandWithOutput($finalCmdString);
        
        if ($result) {
        	
        	// Command succeeded. Set and export output variables.
            $exportedThem = $connection->setOutputVarsToExport($simpleParmVarArray, $result);
        } else {
        	// command failed; don't try to export its output variables.
        	$exportedThem = false; 
        } //(if $result)

    } else {
        // do fast way without rows but with CPF error if available.
        $result = $connection->ClCommandWithCpf($finalCmdString);
        
    } //$needOutputParms
    
    // if result is false consider it an error.
    if(!$result){
        // TODO if have a CPF, scour job log for msg.
        i5CpfError($connection->getLastError(), "Error with CL Command");
        return false;
    } else {
        noError();       
        return true;    
    } // (if not command)
    
    
} //(i5_command)

/**
 * Enter description here ...
 *
 * @param string $pgmName Lib/Pgm or Pgm or Lib/Pgm(svcfunc)
 * @param array $description Array of program parameter info
 * @param ServiceToolkit $connection Optional toolkit object
 * @return DataDescription|boolean On success, an object containing program definition information, or false on failure
 */
function i5_program_prepare($pgmName, $description, $connection = null) {

    // if conn not passed in, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
    
    // look for params
    if (!isset($pgmName)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing program name', 'Missing program name');
        return false;
    }
    if (!is_string($pgmName)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Program name must be a string', 'Program name must be a string');
        return false;
    }
    if (!isset($description)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing description', 'Missing description');
        return false;
    }
    if (!is_array($description)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Description must be an array', 'Description must be an array');
        return false;
    }
    
    // use object that can transform and check description for us.
    $descObj = new DataDescription($pgmName, $description, $connection);

    noError();
    return $descObj;
    
    // validate description types and structure
//    $correct = $descObj->validate();
    
    
/*    if ($correct) {
        // if OK, return the object itself for use in the program call.
        return $descObj;
    } else {    
        // TODO get actual validation error and set it
        return false;
    }
*/    
} //(i5_program_prepare)

/**
 * return object or false
 *
 * @param string $description Opens a program PCML file and prepares it to be run.
 * @param $connection Results of i5_connect
 * @return bool|\DataDescriptionPcml
 */
function i5_program_prepare_PCML($description = null, $connection = null) {
    /*i5ErrorActivity(TO_BE_IMPLEMENTED, 0, "To be implemented", "To be implemented");
    return false;
    */
    // if conn not passed in, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
    
    // look for params
    
    // PCML should be a string
    if (!isset($description)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing PCML string', 'Missing PCML string');
        return false;
    }
    if (!is_string($description)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'PCML description must be a string', 'PCML description must be a string');
        return false;
    }

    // use object that can transform and check description for us.
    $descObj = new DataDescriptionPcml($description, $connection);

    noError();
    
    return $descObj;
    
    
} //(i5_program_prepare_PCML)

/**
 * Call a program based on a prepare done before.
 * @param DataDescription $program Program object created in the preparation stage.
 * @param array $params Input params with key=>value pairs (possibly nested),
 * keys matching what was specified in prepare stage.
 * @param array $retvals Output params (optional)
 * Fields get created based on names of output parms.
 * @return boolean True if successful, false if not.
 */
function i5_program_call(DataDescription $program, $params, $retvals = array()) {

    
    // TODO check type of $program and give toolkit-like messages
    $inputValues = $params;
    // convert from old to new param format, inserting input values
    $newInputParams = $program->generateNewToolkitParams($inputValues);
    $success = $program->callProgram($newInputParams);

    if ($success) {
        if ($retvals && is_array($retvals)) {

        	  $pgmOutput = $program->getPgmOutput();
            $exportedThem = $program->getConnection()->setOutputVarsToExport($retvals, $pgmOutput);
            //$exportedThem = exportPgmOutputVars($retvals, $pgmOutput);
            if (!$exportedThem) {
                return false;
            }
            
        } //(retvals present)
        
        noError();
        return true;
    } else {
        // TODO if particular xml errors,
        // such as errnoxml = 1000005 or errnoile = 3025 means can't find program,
        // specify them.

    	// Return "toolkit-style" CPF errno codes/messages
        // Set the i5error object based on this.
        $conn = $program->getConnection();
    	if($conn->getErrorCode()) {
            i5CpfError($conn->getErrorCode(), $conn->getErrorMsg());
            return false;
        } //(if error code found)
    	
        return false;
    }// (if success)
} //(i5_program_call)

/**
 * @param $pgm
 * @return bool
 */
function i5_program_close(&$pgm) {
     
    // Not much to do here. Set program object to null.
    $pgm = null;
    
    noError();
    return true;
} //(i5_program_close)

/**
 * return string or false
 *
 * @param $name
 * @param null $connection
 * @return bool|void
 */
function i5_get_system_value($name, $connection = null) {
    
    if (!$connection = verifyConnection($connection)) {
        // still no good for some reason
        return false;
    }
    
    if (!$name) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Name is required.', 'Name is required.');
        return false;
    }
    $obj = new SystemValues( $connection );
    $sysval = $obj->GetSystemValue($name);
    
    if (!$sysval) {
        // something went wrong, or was invalid name
        $cpf = 'Bad CPF happened here.';//$connection->getCPFErr();
        i5CpfError($cpf, $connection->getLastError());
        return false;
    }
    
    // success
    noError();
    return $sysval;
    
} //(i5_get_system_value)

/**
 * for internal use
 *
 * Job Log Constants (i5_jobLog_list) array elements constants
 * I5_LOBJ_MESSAGE_SEVERITY
 * I5_LOBJ_MESSAGE_IDENTIFIER
 * I5_LOBJ_MESSAGE_FILELIBRARY
 * I5_LOBJ_TIMESENT_MICRO
 * I5_LOBJ_MESSAGE_TYPE
 * I5_LOBJ_DATASENT
 * I5_LOBJ_MESSAGE_FILENAME
 * I5_LOBJ_TIMESENT
 * I5_LOBJ_ALERTOPT
 * I5_LOBJ_MSGDTA
 * I5_LOBJ_MSGHLPDTAFMT
 * I5_LOBJ_SNDTYPE
 * I5_LOBJ_SNDPROC
 * I5_LOBJ_RCVMOD
 * I5_LOBJ_PROBLEMID
 * I5_LOBJ_RQSLVL
 * I5_LOBJ_RPLDATA1
 * I5_LOBJ_MSGHLP
 * I5_LOBJ_DFTRPLY
 * I5_LOBJ_SNDPGM
 * I5_LOBJ_RCVTYPE
 * I5_LOBJ_RCVPROC
 * I5_LOBJ_RPLYSTS
 * I5_LOBJ_TXTCCSID
 * I5_LOBJ_MSG
 * I5_LOBJ_MSGHLPDTA
 * I5_LOBJ_SNDNAME
 * I5_LOBJ_SNDMOD
 * I5_LOBJ_RCVPROG
 * I5_LOBJ_MSGFILE
 * I5_LOBJ_RQSSTS
 * I5_LOBJ_DATACCSID
 *
 * @param array $fieldInfo indexes 'var', 'type', 'comment'
 * @return string
 */
function joblogRepeatingFieldXml($fieldInfo) {
    
    $xml = '';
    
    foreach ($fieldInfo as $oneField) {
        $xml .= "<ds var='repeatingFieldInfo' comment='Repeating fields that define the fields we received and contain the data'>
        <data var='offsetToData' type='10i0' comment='Offset to the next field information returned' />
        <data var='fieldInfoLength' type='10i0' comment='Length of field information returned' />
        <data var='key' type='10i0' comment='Identifier field (key)' />
        <data var='type' type='1a' comment='Type of data' />
        <data var='status' type='1a' comment='Status of data' />
        <data var='reserved' type='14h' comment='Reserved' />
        <data var='dataLength' type='10i0' comment='Length of data' />
        <data var='{$oneField['var']}' type='{$oneField['type']}' comment='{$oneField['comment']}' />
        <data var='reserved2' type='{$oneField['reservedLen']}b' comment='Reserved' />
      </ds>";
           
    } //(foreach)
    
    return $xml;
    
} //(joblogRepeatingFieldXml($fieldInfo))

/**
 * Description: Opens job log.
 *
 * Gets a job log for a SPECIFIC JOB.
 *
 * Return Values: The resource for fetching job log list if OK; false if failed.
 * Arguments:
 * elements - JobName, JobUser, JobNumber, (default is current job)
 * MaxMessage (which does NOTHING in old toolkit. Ignored completely.), Direction
 * connection - Result of i5_connect
 * Use i5_jobLog_list_read function to retrieve the job entries from this handle.
 *
 * define('I5_JOBNAME', 'jobName');
 * define('I5_USERNAME', 'username');
 * define('I5_JOBNUMBER', 'jobnumber');
 * define('I5_MAXMESSAGE', 'maxMessage'); // tested this in old toolkit. it's ignored--does nothing in old toolkit.
 * The maximum number of messages to be returned. (-1 means all)
 * define('I5_DIRECTION', 'direction');
 * The direction to list messages. You must use one of these directions:
 * *NEXT Returns messages that are newer than the message specified by the starting message key field.
 * *PRV Returns messages that are older than the messages specified by the starting message key field.
 *
 * See http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Fapis%2FQMHLJOBL.htm
 * API: QMHLJOBL (List job log messages)
 *
 * old toolkit enforced this rule:
 * either all three are blank (current job), or all three must be supplied.
 * if some are blank, get:
 * CPF3658, job name not specified: Either job name 'A10A' is blank, user name 'ASEIDEN' is blank, or job number '' is blank. None of these fields can contain blank
 * With one exception: if only username is supplied, and the others are blank, ignore the username and provide current job's log.
 * (That's what i5 toolkit does)
 *
 * @param array $elements
 * @param null $connection
 * @return ListFromApi
 */
function i5_jobLog_list($elements = array() , $connection = null)
{
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
   
    // check that if element criteria were passed, it's an array (though the array itself is optional)
    if($elements && !is_array($elements)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Criteria elements must be an array', 'Criteria elements must be an array');
        return false;
    }
    // if none are specified, make it *CURRENT
    $jobName = '';
    $jobUser = '';
    $jobNumber = '';
    $direction = '*NEXT'; // default
    if (count($elements)) {
        foreach ($elements as $type=>$value) {
            switch ($type) {
                case I5_JOBNAME:
                    $jobName = $value;
                    break;
                case I5_JOBNUMBER:
                    $jobNumber = $value;
                    break;
                case I5_USERNAME:
                    $jobUser = $value;
                    break;
                case I5_DIRECTION:
                    $direction = $value; //'L' or 'N', perhaps. or *NEXT, *PRV
                    break;
            }
        }
    } //(if count ($elements))
    
    // the way the old toolkit workd: if job# and job name are missing, get current job.
    // (In that situation, user is ignored)
    
    if(empty($jobNumber) && empty($jobName)) {
        list($jobName, $jobNumber, $jobUser) = array_fill(0, 3, '*');
    }

    // now, ALL THREE should be filled one way or another.
    // If one is missing, it's an error
    if(empty($jobName) || empty($jobNumber) || empty($jobUser)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'All three criteria elements must be filled (or all left blank for current job)', 'All three criteria elements must be filled (or all left blank for current job)');
        return false;
    }


        // if jobname or jobnumber is provided then complete job info (all three params) must be provided.
    // otherwise give an I5_ERR_PHP_LIST_PROP error.
    // In other words,
    // (Not documented but shown in old toolkit unit tests.)
    $specifyJobParams = false;
    if (!empty($jobName) || !empty($jobNumber)) {
        // jobName or jobNumber is specified. Must have all three job params.
        if (empty($jobName) || empty($jobNumber) || empty($jobUser)) {
            i5ErrorActivity(I5_ERR_PHP_LIST_PROP, I5_CAT_PHP, 'Incomplete job criteria specified.', 'If jobname or jobnumber are specified, then all three job criteria (jobname, jobnumber, and username) must be specified. You can use * to indicate current job.');
            return false;
        }
        
        // if we didn't error out then we have all three job params.
        $specifyJobParams = true;
        
    } //(jobName or jobNumber given)
       
    $apiPgm = 'QGYOLJBL'; /// job log list
    $apiLib = 'QSYS';
    
    $lengthOfReceiverVariable = 512; // list entry API will have a receiver DS of this length
    
    $outputVarname = 'listinfo';
/*  <parm io='in' comment='7. Qualified job name. Pass blank to ignore.'>
    <ds var='qualifiedJobName' comment='Qualified job name, total length 26'>
     <data var='jobName' type='10a' comment='Job name'>$jobName</data>
     <data var='userName' type='10a' comment='User name'>$jobUserName</data>
     <data var='jobNumber' type='6a' comment='Job number'>$jobNumber</data>
    </ds>
  </parm>
*/
    // make sure these are STRINGS so that they aren't interpreted as octal.
    
    // TODO request all keys once overlay/offset/alpha convert is worked out for this complex API.
//    $keysToRequest = array(0101, 0201, 0301, 0302, 0401, 0402, 0403, 0404, 0501, 0601, 0602, 0603, 0604, 0605, 0607, 0702, 0703, 0704, 0705, 0801, 0901, 1001, 1101, 1201, 1301, 1302, 1303, 1304);
    $keysToRequest = array('0101');

    $fieldInfoToRequest = array(
         array('var'=>'0101', 'type'=>'9a', 'comment'=>'Alert option', 'reservedLen'=>7),
         // 0201 could be 298a or more!
         //array('var'=>'0201', 'type'=>'100b', 'comment'=>'Replacement data or impromptu message text', 'reservedLen'=>7)
         );
    
    $fieldInfoToRequest = array();

    $repeatingXml = joblogRepeatingFieldXml($fieldInfoToRequest);        
    
    $keysToRequestXml = '';
    foreach ($keysToRequest as $keyToRequest) {
        $keysToRequestXml .= "<data type='10i0'>$keyToRequest</data>\n";
    }
    $bytesFromHeaderBeforeKeys = 80;
    $keysToRequest = count($keysToRequest);
    $bytesFromKeys = $keysToRequest * 4;
    $offsetToMsgqField = $bytesFromHeaderBeforeKeys + $bytesFromKeys;  
    $sizeOfMessageInfo = $offsetToMsgqField + 1;
    
        /*<data type='10i0'>0101</data>
        <data type='10i0'>0201</data>
        <data type='10i0'>0301</data>
        <data type='10i0'>0302</data>
        <data type='10i0'>0401</data>
        <data type='10i0'>0402</data>
        <data type='10i0'>0403</data>
        <data type='10i0'>0404</data>
        <data type='10i0'>0501</data>
        <data type='10i0'>0601</data>
        <data type='10i0'>0602</data>
        <data type='10i0'>0603</data>
        <data type='10i0'>0604</data>
        <data type='10i0'>0605</data>
        <data type='10i0'>0607</data>
        <data type='10i0'>0702</data>
        <data type='10i0'>0703</data>
        <data type='10i0'>0704</data>
        <data type='10i0'>0705</data>
        <data type='10i0'>0801</data>
        <data type='10i0'>0901</data>
        <data type='10i0'>1001</data>
        <data type='10i0'>1101</data>
        <data type='10i0'>1201</data>
        <data type='10i0'>1301</data>
        <data type='10i0'>1302</data>
        <data type='10i0'>1303</data>
        <data type='10i0'>1304</data>
   */
   
    $paramXml =
    $connection->getDummyReceiverAndLengthApiXml(1, $lengthOfReceiverVariable) . "\n" .
   // param #3, list info
  $connection->getListInfoApiXml(3) . "\n" .
  $connection->getNumberOfRecordsDesiredApiXml(4) . "\n" .
  "<parm io='in' comment='5. Message selection information' >
    <ds var='msgSelectInfo' comment='Message selection information format'>
      <data type='10a' comment='List direction (*NEXT or *PRV)'>$direction</data>
      <ds var='qualifiedJobName' comment='Qualified job name, total length 26'>
       <data var='jobName' type='10a' comment='Job name'>$jobName</data>
       <data var='userName' type='10a' comment='User name'>$jobUser</data>
       <data var='jobNumber' type='6a' comment='Job number'>$jobNumber</data>
      </ds>
      <data type='16a' comment='Internal job identifier' />
      <data type='4b' comment='Starting message key'>00000000</data>
      <data type='10i0' comment='Maximum message length (poss 50) fields 301-302'>10</data>
      <data type='10i0' comment='Maximum message help length (poss 100) fields 401-404'>20</data>
      <data type='10i0' comment='Offset of identifiers of fields to return'>$bytesFromHeaderBeforeKeys</data>
      <data type='10i0' comment='Number of fields to return'>$keysToRequest</data>
      <data type='10i0' comment='Offset to call message queue name'>$offsetToMsgqField</data>
      <data type='10i0' comment='Size of call message queue name (1 because it is *)'>1</data>
      <ds var='keysToRequest' comment='keys from 101 to 1304 that we are requesting'>
            $keysToRequestXml
      </ds>
      <data type='1a' comment='Call message queue name (* means all)'>*</data>
    </ds>
  </parm>
  <parm io='in' comment='6. Size of message selection information (minimum 85, probably 193)'>
      <data type='10i0' var='sizeOfMsgSelectInfo'>$sizeOfMessageInfo</data>
  </parm>\n" .    
   // param number 7
   ToolkitServiceCw::getErrorDataStructXml(7);
         
    // now call it!
    // pass param xml directly in.
        $retPgmArr = $connection->PgmCall($apiPgm, $apiLib, $paramXml);

        if($connection->getErrorCode()) {
            i5CpfError($connection->getErrorCode(), $connection->getErrorMsg());
            return false;
        }
        
        
        $retArr = $retPgmArr['io_param']['listinfo']; // 'listinfo' defined in getListInfoApiXml()
        
        $totalRecords = $retArr['totalRecords'];
        $requestHandle = $retArr['requestHandle'];
        $listStatus = $retArr['listStatus']; // in case we need it

        
    // receiver data we want to see
    $receiverDs = "
<data type='10i0' comment='Offset to the next entry (but we are only getting one entry at a time)' />
<data type='10i0' comment='Offset to fields returned' />
<data type='10i0' comment='Number of fields returned (should be 28)' />
<data var='1' type='10i0' comment='Message severity' />
<data var='2' type='7a' comment='Message identifier' />
<data var='3' type='2a' comment='Message type' />
<data var='msgKey' type='4b' comment='Message key' />
<data var='4' type='10a' comment='Message file name' />
<data var='5' type='10a' comment='Message file library specified at send time' />
<data var='6' type='7a' comment='Date sent' />
<data var='7' type='6a' comment='Time sent' />
<data var='8' type='6a' comment='Microseconds' />
<data var='threadId' type='8b' comment='Thread ID' />
<data var='reserved' type='4b' comment='Reserved with length 4 perfect' />\n" .
$repeatingXml;
 
    // from this point on, no CPFs will happen in this function.
    noError();

    // make request for objects, but don't return any yet.
    // Get a handle and total number of records.
    // listinfo: totalRecords, firstRecordNumber, requestHandle. if firstRec... < totalRecords then can continue.
    // return I5_ERR_BEOF when went past last record. get CPF GUI0006 when used invalid record#.
    $listObj = new ListFromApi($requestHandle, $totalRecords, $receiverDs, $lengthOfReceiverVariable, $connection);
    return $listObj;
    
    // if get false, check current job for CPF2441, which means "not authorized to job log."          
        
    //CPF2441 not authorized to display job log
    
} //(i5_joblog_list)

/**
 * pass list by reference so its current element can be advanced
 *
 * Description: Get an array for a job log entry.
 * Return Values: Array with the message element if OK, false if failed.
 * Arguments: list - Resource returned by i5_jobLog_list function
 *
 * I5_USERNAME=>'QSYS'
 * define('I5_JOBNAME', 'jobName');
 * define('I5_USERNAME', 'username');
 * define('I5_JOBNUMBER', 'jobnumber');
 * define('I5_MAXMESSAGE', 'maxMessage');
 * define('I5_DIRECTION', 'direction');
 * define('I5_USERDATA', 'userdata');
 * define('I5_OUTQ', 'outq');
 * define('I5_STATUS', 'status');
 * define('I5_JOBTYPE', 'jobType');
 *
 * LOGS LOGS LOGS LOGS
 *
 * @param null $list
 * @return bool
 */
function i5_jobLog_list_read(&$list = null)
{
    return listRead($list);
}
    /**
     * Description: Close handle received from i5_jobLog_list().
     * Return Values: Boolean success value
     * Arguments: list - Job list handle as returned by i5_jobLog_list(), passed by reference so it can be closed
     *
     * @param null $list
     * @return bool
     */
function i5_jobLog_list_close(&$list = null)
{
   return listClose($list);   
    
} //(i5_joblog_list_close)
    
    /**
     * Check that we have an instance of the toolkit object, either passed in or available from the toolkit.
     * Return that instance, or false.
     *
     * @param ToolkitServiceCw $connection
     * @return bool|null
     */
function verifyConnection($connection = null) {
    // if conn passed and non-null but it's bad
    if ($connection && !is_a($connection, 'ToolkitService')) {
        i5ErrorActivity(I5_ERR_PHP_HDLCONN, I5_CAT_PHP, 'Connection handle invalid', 'Connection handle invalid');
        return false;
        
    } elseif (!$connection) {
        // not passed in or null.
    	
        // Check if a connection was started. User should start a connection before trying to use it.
        if (!ToolkitServiceCw::hasInstance()) {
            i5ErrorActivity(I5_ERR_PHP_HDLDFT, I5_CAT_PHP, 'No default connection found.', 'Connection has not been initialized. Please connect before using this function.');
            return false;
        } 
        
        // if we thought we had a connection but it's empty.
        if (!$connection = ToolkitServiceCw::getInstance()) {
        	// still no good for some reason
        	i5ErrorActivity(I5_ERR_PHP_HDLCONN, I5_CAT_PHP, 'Connection handle invalid', 'Connection handle invalid');
        	return false;
        }
    } //(verify connection)
    
    // A good connection. Return it.
    return $connection;
} //(verifyConnection)
    
    /**
     * split but, unlike Zend PHP wrapper, keep original indexes.
     *
     * @param $JobListString
     * @return array
     */
function splitJobStringIntoArray($JobListString)
    {            
        // fill jobList with one array entry per job,
        // but break up each job into separate fields.
        $jobList = array();
        if(is_array($JobListString)){
            foreach($JobListString as $element)
            {
                $el = str_split( $element, 10);
                $jobList[] = $el;
            }
        }
        return $jobList;
    }
    
    /**
     * Description: Open active job list.
     * Return Values: The resource for fetching job list if OK and false if failed.
     * An empty list is considered successful.
     * Arguments:
     * elements - JobName, JobUser, JobNumber (*ALL is OK for these), JobType, (default is current job)
     * Direction (I don't think Direction is correct)
     * connection - Result of i5_connect
     *
     * I5_USERNAME=>'QSYS'
     * define('I5_JOBNAME', 'jobName');
     * define('I5_USERNAME', 'username');
     * define('I5_JOBNUMBER', 'jobnumber');
     * define('I5_MAXMESSAGE', 'maxMessage');
     * define('I5_DIRECTION', 'direction');
     * define('I5_USERDATA', 'userdata');
     * define('I5_OUTQ', 'outq');
     * define('I5_STATUS', 'status');
     * define('I5_JOBTYPE', 'jobType'); (actually status)
     *
     * job type
     * * This value lists all job types.
     * A The job is an autostart job.
     * B The job is a batch job.
     * I The job is an interactive job.
     * M The job is a subsystem monitor job.
     * R The job is a spooled reader job.
     * S The job is a system job.
     * W The job is a spooled writer job.
     * X The job is the start-control-program-function (SCPF) system job.
     *
     * @param array $elements
     * @param null $connection
     * @return ListFromApi
     */
function i5_job_list($elements = array(), $connection = null)
{
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
    
    // check that if element criteria were passed, it's an array (though the array itself is optional)
    if($elements && !is_array($elements)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Criteria elements must be an array', 'Criteria elements must be an array');
        return false;
    }
    
    $jobName = '';
    $jobUser = '';
    $jobNumber = '';
    $jobType = '*'; // default to all types
    
    if (count($elements)) {
        foreach ($elements as $type=>$value) {
            switch ($type) {
                case I5_JOBNAME:
                    $jobName = $value;
                    break;
                case I5_JOBNUMBER:
                    $jobNumber = $value;
                    break;
                case I5_USERNAME:
                    $jobUser = $value;
                    break;
                case I5_JOBTYPE:
                    $jobType = $value;
                    break;
            }
        }
    } //(if count(elements))
    
    // if NONE of name/user/number are specified, assume it's the current job.
    // Specify the current job with "*" for job name, and a blank user and number.
    if(empty($jobName) && empty($jobUser) && empty($jobNumber)) {
        $jobName = '*';
    } else {
        // something was specified. Set any non-specified fields to "*ALL".
        $jobName = ($jobName) ? $jobName : '*ALL';
        $jobUser = ($jobUser) ? $jobUser : '*ALL';
        $jobNumber = ($jobNumber) ? $jobNumber : '*ALL';
    }

    
    
    // the job lister doesn't have any error messages.
    i5ErrorActivity(I5_ERR_OK);    
    
    $apiPgm = 'QGYOLJOB'; /// object list
    $apiLib = 'QSYS';
    
    $lengthOfReceiverVariable = 1164; // list entry API will have a receiver DS of this length
    
    $outputVarname = 'listinfo';
    
    $paramXml = "
  <parm io='out' comment='1. receiver. do not receive anything here. Wait till Get List Entry'>
    <ds var='receiver' comment='length $lengthOfReceiverVariable'>
        <data type='1h' comment='dummy. Real receiver will be gotten in list entry API call' />
    </ds>
  </parm>
  <parm io='in' comment='2. Length of receiver variable'>
    <data var='receiverLen' type='10i0' comment='length $lengthOfReceiverVariable'>$lengthOfReceiverVariable</data>
  </parm>
  <parm io='in' comment='3. Format name'>
    <data var='format' type='8a' comment='Format name of data to receive'>OLJB0200</data>
  </parm>
  <parm io='out' comment='4. Receiver variable definition information'>
    <ds var='receiverDefinition' comment='Contains info about what we have received' len='receiverDefinition'>
      <data var='numFieldsReceived' type='10i0' comment='Number of fields returned' />
      <ds var='repeatingFieldInfo' dim='104' comment='Repeating fields that tell us what fields we received and where the data is (offsets)'>
        <data var='fieldInfoLength' type='10i0' comment='Length of field information returned' />
        <data var='key' type='10i0' comment='Key field' />
        <data var='type' type='1a' comment='Type of data' />
        <data var='reserved' type='3h' comment='Reserved' />
        <data var='dataLength' type='10i0' comment='Length of data' />
        <data var='offsetToData' type='10i0' comment='Displacement to data' />
      </ds>
    </ds>
  </parm>

  <parm io='in' comment='5. Length of receiver variable definition information'>
    <data var='receiverDefinitionLength' comment='104 fields (from #101 to #2102) x 20 bytes + 4 header is 2084' type='10i0' setlen='receiverDefinition' />
  </parm>\n" .
  $connection->getListInfoApiXml(6) . "\n" .
  "<parm io='in' comment='7. Number of records to return. Use zero to offload to Get List Entries API'>
    <data var='numRecsDesired' type='10i0'>0</data>
  </parm>
  <parm io='in' comment='8. Sort information' >
    <ds var='sortInfo'>
     <data var='numSortKeys' comment='Number of keys to sort on. Use zero' type='10i0'>0</data>
    </ds>
  </parm>
  <parm io='in' comment='9. Job selection information' >
    <ds var='jobSelectionInfo' comment='Use format OLJS0100 (default)' len='jobSelectionInfo' >
      <data var='JobName' type='10a' comment='Job name'>$jobName</data>
      <data var='JobUser' type='10a' comment='User name'>$jobUser</data>
      <data var='JobNumber' type='6a' comment='Job number'>$jobNumber</data>
      <data var='JobType' type='1a' comment='Job type (*, A, B, I, M, R, S, W, X)' >$jobType</data>
      <data var='reserved' type='1h' comment='Reserved (ignored)' />
      
      <data var='offsetToPrimaryJobStatuses' type='10i0' comment='Offset to primary job status array (0-based)'>60</data>
      <data var='numPrimaryJobStatuses' type='10i0' comment='Number of primary job status entries'>1</data>
      
      <data var='offsetToActiveJobStatuses' type='10i0' comment='Offset to active job status array' />
      <data var='numActiveJobStatuses' type='10i0' comment='Number of active job status entries'>0</data>
      
      <data var='offsetToJobQueueJobs' type='10i0' comment='Offset to jobs on job queue status array' />
      <data var='numJobQueueJobs' type='10i0' comment='Number of jobs on job queue status entries'>0</data>
      
      <data var='offsetToJobQueueNames' type='10i0' comment='Offset to job queue names array' />
      <data var='numJobQueueNames' type='10i0' comment='Number of job queue names entries'>0</data>
      
      <data var='primaryJobStatus' type='10a' comment='Primary job status'>*ACTIVE</data>
          </ds>
  </parm>
  <parm io='in' comment='10. Size of job selection information'>
     <data var='jobSelectionInfoSize' type='10i0' comment='will be 70' setlen='jobSelectionInfo' />
  </parm>

  <parm io='in' comment='11. Number of keyed fields to return'>
     <data var='numKeyedFields' type='10i0'>104</data>
  </parm>

  <parm io='in' comment='12. Array of keys of fields to return'>
    <ds var='keysToRequest' comment='keys from 101 to 2102 that we are requesting'>
      <data type='10i0'>101</data>
      <data type='10i0'>102</data>
      <data type='10i0'>103</data>
      <data type='10i0'>201</data>
      <data type='10i0'>301</data>
      <data type='10i0'>302</data>
      <data type='10i0'>303</data>
      <data type='10i0'>304</data>
      <data type='10i0'>305</data>
      <data type='10i0'>306</data>
      <data type='10i0'>307</data>
      <data type='10i0'>311</data>
      <data type='10i0'>312</data>
      <data type='10i0'>313</data>
      <data type='10i0'>401</data>
      <data type='10i0'>402</data>
      <data type='10i0'>403</data>
      <data type='10i0'>404</data>
      <data type='10i0'>405</data>
      <data type='10i0'>406</data>
      <data type='10i0'>407</data>
      <data type='10i0'>408</data>
      <data type='10i0'>409</data>
      <data type='10i0'>410</data>
      <data type='10i0'>411</data>
      <data type='10i0'>412</data>
      <data type='10i0'>413</data>
      <data type='10i0'>418</data>
      <data type='10i0'>501</data>
      <data type='10i0'>502</data>
      <data type='10i0'>503</data>
      <data type='10i0'>601</data>
      <data type='10i0'>602</data>
      <data type='10i0'>701</data>
      <data type='10i0'>702</data>
      <data type='10i0'>703</data>
      <data type='10i0'>901</data>
      <data type='10i0'>1001</data>
      <data type='10i0'>1002</data>
      <data type='10i0'>1003</data>
      <data type='10i0'>1004</data>
      <data type='10i0'>1005</data>
      <data type='10i0'>1006</data>
      <data type='10i0'>1007</data>
      <data type='10i0'>1008</data>
      <data type='10i0'>1012</data>
      <data type='10i0'>1013</data>
      <data type='10i0'>1014</data>
      <data type='10i0'>1015</data>
      <data type='10i0'>1016</data>
      <data type='10i0'>1201</data>
      <data type='10i0'>1202</data>
      <data type='10i0'>1203</data>
      <data type='10i0'>1204</data>
      <data type='10i0'>1205</data>
      <data type='10i0'>1301</data>
      <data type='10i0'>1302</data>
      <data type='10i0'>1303</data>
      <data type='10i0'>1304</data>
      <data type='10i0'>1305</data>
      <data type='10i0'>1306</data>
      <data type='10i0'>1307</data>
      <data type='10i0'>1401</data>
      <data type='10i0'>1402</data>
      <data type='10i0'>1403</data>
      <data type='10i0'>1404</data>
      <data type='10i0'>1405</data>
      <data type='10i0'>1406</data>
      <data type='10i0'>1501</data>
      <data type='10i0'>1502</data>
      <data type='10i0'>1601</data>
      <data type='10i0'>1602</data>
      <data type='10i0'>1603</data>
      <data type='10i0'>1604</data>
      <data type='10i0'>1605</data>
      <data type='10i0'>1606</data>
      <data type='10i0'>1607</data>
      <data type='10i0'>1608</data>
      <data type='10i0'>1801</data>
      <data type='10i0'>1802</data>
      <data type='10i0'>1803</data>
      <data type='10i0'>1901</data>
      <data type='10i0'>1902</data>
      <data type='10i0'>1903</data>
      <data type='10i0'>1904</data>
      <data type='10i0'>1905</data>
      <data type='10i0'>1906</data>
      <data type='10i0'>1907</data>
      <data type='10i0'>1908</data>
      <data type='10i0'>1909</data>
      <data type='10i0'>1910</data>
      <data type='10i0'>1911</data>
      <data type='10i0'>1982</data>
      <data type='10i0'>2001</data>
      <data type='10i0'>2002</data>
      <data type='10i0'>2003</data>
      <data type='10i0'>2004</data>
      <data type='10i0'>2005</data>
      <data type='10i0'>2006</data>
      <data type='10i0'>2007</data>
      <data type='10i0'>2008</data>
      <data type='10i0'>2009</data>
      <data type='10i0'>2101</data>
      <data type='10i0'>2102</data>
    </ds>
  </parm>\n" .  ToolkitServiceCw::getErrorDataStructXml(13); // param number 13
    
    // now call it!
    // pass param xml directly in.
        $retPgmArr = $connection->PgmCall($apiPgm, $apiLib, $paramXml);

        if($connection->getErrorCode()) {
            // TODO get real message from joblog
            i5ErrorActivity(I5_ERR_PHP_AS400_MESSAGE, I5_CAT_PHP, $connection->getErrorCode(), $connection->getErrorMsg());
            return false;
        }
        
        $retArr = $retPgmArr['io_param']['listinfo']; // 'listinfo' defined in getListInfoApiXml()
        //d($retArr);
        $totalRecords = $retArr['totalRecords'];
        $requestHandle = $retArr['requestHandle'];
        $listStatus = $retArr['listStatus']; // in case we need it

        
    // receiver data we want to see
    $receiverDs = "
      <data var='1' type='10a' comment='Job name used' />
      <data var='2' type='10a' comment='User name used' />
      <data var='3' type='6a' comment='Job number used' />
      <data var='4' type='16b' comment='Internal job identifier' />
      <data var='5' type='10a' comment='Status' />
      <data var='6' type='1a' comment='Job type' />
      <data var='7' type='1a' comment='Job subtype' />
      <data         type='2h' comment='Reserved' />
      <data var='8' type='1a' comment='Job information status' />
      <data         type='3h' comment='Reserved' />
      <data var='101' type='4a' comment='Active job status' />
      <data var='102' type='4a' realtype='1a' comment='Allow multiple threads' />
      <data var='103' type='4a' comment='Active job status for jobs ending' />
      <data var='201' type='12a' realtype='10a' comment='Break message handling' />
      <data var='301' type='4a' realtype='1a' comment='Cancel key (0 or 1)' />
      <data var='302' type='10i0' comment='Coded Character set ID' />
      <data var='303' type='4a' realtype='2a' comment='Country or region ID' />
      <data var='304' type='10i0' comment='Processing unit time used, if less than 2,147,483,647 milliseconds' />
      <data var='305' type='12a' realtype='10a' comment='Current user profile TODO 10a' />
      <data var='306' type='4a' realtype='1a' comment='Completion status (0 or 1)' />
      <data var='307' type='10i0' comment='Current system pool identifier' />
      <data var='311' type='12a' realtype='10a' comment='Character identifier control' />
      <data var='312' type='20u0' comment='Processing unit time used - total for the job' />
      <data var='313' type='20u0' comment='Processing unit time used for database - total for the job' />
      <data var='401' type='16a' realtype='13a' comment='Date and time job became active' />
      <data var='402' type='16a' realtype='13a' comment='Date and time job entered system' />
      <data var='403' type='8b' dtsdate='on' comment='Date and time job is scheduled to run' />
      <data var='404' type='8b' dtsdate='on' comment='Date and time job was put on this job queue' />
      <data var='405' type='4a' comment='Date format' />
      <data var='406' type='4a' realtype='1a' comment='Date separator' />
      <data var='407' type='4a' realtype='1a' comment='DBCS-capable' />
      <data var='408' type='12a' realtype='10a' comment='DDM conversation handling' />
      <data var='409' type='10i0' comment='Default wait' />
      <data var='410' type='16a' realtype='13a' comment='Device recovery action' />
      <data var='411' type='12a' realtype='10a' comment='Device name' />
      <data var='412' type='10i0' comment='Default coded Character set identifier' />
      <data var='413' type='4a' realtype='1a' comment='Decimal format' />
      <data var='418' type='16a' realtype='13a' comment='Date and time job ended' />
      <data var='501' type='10i0' comment='End severity' />
      <data var='502' type='4a' realtype='1a' comment='End status' />
      <data var='503' type='4a' realtype='1a' comment='Exit key' />
      <data var='601' type='12a' realtype='12a' comment='Function name' />
      <data var='602' type='4a' realtype='1a' comment='Function type' />
      <data var='701' type='4a' realtype='1a' comment='Signed-on job' />
      <data var='702' type='12a' realtype='10a' comment='Group profile name' />
      <data var='703' type='152a' realtype='150a' comment='Group profile name - supplemental' />
      <data var='901' type='12a'  realtype='10a' comment='Inquiry message reply' />
      <data var='1001' type='16a' realtype='15a' comment='Job accounting code' />
      <data var='1002' type='8a' realtype='7a' comment='Job date' />
      <data var='1003' type='20a' comment='Job description name - qualified' />
      <data var='1004' type='20a' comment='Job queue name - qualified' />
      <data var='1005' type='4a' realtype='2a' comment='Job queue priority' />
      <data var='1006' type='8a' comment='Job switches' />
      <data var='1007' type='12a' realtype='10a' comment='Job message queue full action' />
      <data var='1008' type='10i0' comment='Job message queue maximum size' />
      <data var='1012' type='12a' realtype='10a' comment='Job user identity' />
      <data var='1013' type='4a' realtype='1a' comment='Job user identity setting' />
      <data var='1014' type='10i0' comment='Job end reason' />
      <data var='1015' type='4a' realtype='1a' comment='Job log pending' />
      <data var='1016' type='10i0' comment='Job type - enhanced' />
      <data var='1201' type='4a' realtype='3a' comment='Language ID' />
      <data var='1202' type='4a' realtype='1a' comment='Logging level' />
      <data var='1203' type='12a' realtype='10a' comment='Logging of CL programs' />
      <data var='1204' type='10i0' comment='Logging severity' />
      <data var='1205' type='12a' realtype='10a' comment='Logging text' />
      <data var='1301' type='8a' comment='Mode name' />
      <data var='1302' type='10i0' comment='Maximum processing unit time' />
      <data var='1303' type='10i0' comment='Maximum temporary storage in kilobytes' />
      <data var='1304' type='10i0' comment='Maximum threads' />
      <data var='1305' type='10i0' comment='Maximum temporary storage in megabytes' />
      <data var='1306' type='12a' realtype='10a' comment='Memory pool name' />
      <data var='1307' type='4a' realtype='1a' comment='Message reply' />
      <data var='1401' type='10i0' comment='Number of auxiliary I/O requests, if less than 2,147,483,647' />
      <data var='1402' type='10i0' comment='Number of interactive transactions' />
      <data var='1403' type='10i0' comment='Number of database lock waits' />
      <data var='1404' type='10i0' comment='Number of internal machine lock waits' />
      <data var='1405' type='10i0' comment='Number of nondatabase lock waits' />
      <data var='1406' type='20u0' comment='Number of auxiliary I/O requests' />
      <data var='1501' type='20a' comment='Output queue name - qualified' />
      <data var='1502' type='4a' realtype='2a' comment='Output queue priority' />
      <data var='1601' type='12a' realtype='10a' comment='Print key format' />
      <data var='1602' type='32a' realtype='30a' comment='Print text' />
      <data var='1603' type='12a' realtype='10a' comment='Printer device name' />
      <data var='1604' type='12a' realtype='10a' comment='Purge' />
      <data var='1605' type='10i0' comment='Product return code' />
      <data var='1606' type='10i0' comment='Program return code' />
      <data var='1607' type='8a' comment='Pending signal set' />
      <data var='1608' type='10i0' comment='Process ID number' />
      <data var='1801' type='10i0' comment='Response time total' />
      <data var='1802' type='10i0' comment='Run priority (job)' />
      <data var='1803' type='80a' comment='Routing data' />
      <data var='1901' type='20a' comment='Sort sequence table - qualified' />
      <data var='1902' type='12a' realtype='10a' comment='Status message handling' />
      <data var='1903' type='12a' realtype='10a' comment='Status of job on the job queue' />
      <data var='1904' type='28a' realtype='26a' comment='Submitter job name - qualified' />
      <data var='1905' type='20a' comment='Submitter message queue name - qualified' />
      <data var='1906' type='20a' comment='Subsystem description name - qualified' />
      <data var='1907' type='10i0' comment='System pool identifier' />
      <data var='1908' type='12a' realtype='10a' comment='Special environment' />
      <data var='1909' type='8b'  comment='Signal blocking mask (was 8a but is a bit field)' />
      <data var='1910' type='10i0' comment='Signal status' />
      <data var='1911' type='32a' realtype='30a' comment='Server type' />
      <data var='1982' type='12a' realtype='10a' comment='Spooled file action' />
      <data var='2001' type='4a' realtype='1a' comment='Time separator' />
      <data var='2002' type='10i0' comment='Time slice' />
      <data var='2003' type='12a' realtype='10a' comment='Time-slice end pool' />
      <data var='2004' type='10i0' comment='Temporary storage used in kilobytes ' />
      <data var='2005' type='10i0' comment='Time spent on database lock waits (erroneously 3a in some documentation)' />
      <data var='2006' type='10i0' comment='Time spent on internal machine lock waits (erroneously 3a in some documentation)' />
      <data var='2007' type='10i0' comment='Time spent on nondatabase lock waits (erroneously 3a in some documentation)' />
      <data var='2008' type='10i0' comment='Thread count' />
      <data var='2009' type='10i0' comment='Temporary storage used in megabytes' />
      <data var='2101' type='24a' comment='Unit of work ID' />
      <data var='2102' type='10i0' comment='User return code' />
        ";

    // from this point on, no CPFs will happen in this function.
    noError();
    
    // make request for objects, but don't return any yet.
    // Get a handle and total number of records.
    // listinfo: totalRecords, firstRecordNumber, requestHandle. if firstRec... < totalRecords then can continue.
    // return I5_ERR_BEOF when went past last record. get CPF GUI0006 when used invalid record#.
    $listObj = new ListFromApi($requestHandle, $totalRecords, $receiverDs, $lengthOfReceiverVariable, $connection);
    return $listObj;
        
} //(i5_job_list)
    
    /**
     * Description: Get an array for an active job entry.
     * Return Values: Array with the job entry element if OK, false if failed.
     * Arguments: List - Resource returned by i5_job_list function
     *
     * @param null $list
     * @return bool
     */
function i5_job_list_read(&$list = null)
{
    return listRead($list);
    
} //(i5_job_list_read)
    
    /**
     * Description: Close handle received from i5_job_list(). Return Values: Boolean success value
     * Arguments: list- Job list handle as returned by 15_job_list()
     *
     * @param null $list
     * @return bool
     */
function i5_job_list_close(&$list= null)
{
    return listClose($list);
        
} //(i5_job_list_close)
    
    /**
     * Creates data area
     *
     * @todo get better error codes even though a service program is used. Try job log.
     *
     * @param string $name Lib/Name of data area to create (lib is optional)
     * @param int $size Size in bytes
     * @param ToolkitServiceCw $connection
     * @return boolean True if successful, false if not.
     */
function i5_data_area_create($name, $size, $connection = null) {
 
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
        
    if (!$name) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Name is required.', 'Name is required.');
        return false;
    }
    if (!$size) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Size is required.', 'Size is required.');
        return false;
    }
    
    // error if size is not numeric
    if (!is_int($size) && !ctype_digit($size)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Size must be an integer.', 'Size must be an integer.');
        return false;
    }
    // params OK at this point
    
    // Split library (use *CURLIB if no library specified) and program names
    $libAndObj = splitLibObj($name, '*CURLIB');
    
    $dataAreaObj = new DataArea($connection);
    try {
        $dataAreaObj->createDataArea($libAndObj['obj'], $libAndObj['lib'], $size);
    } catch (Exception $e) {
        i5CpfError('Error creating data area', $e->getMessage());
        return false;
    }
    
    return true;
    
} //(i5_data_area_create)
    
    /**
     * Reads data from data area. Offset and length can be optional together.
     *
     * @param string $name Name of data area
     * @param int|ToolkitServiceCw $offsetOrConnection Offset to read from (omit to read all) OR connection
     * @param int $length Length of data to read (-1 means all)
     * @param ToolkitServiceCw $connection
     * @return string|boolean Returns data if read successful, false if read failed (including when offset is wrong)
     */
function i5_data_area_read($name, $offsetOrConnection = null, $length = null, $connection = null)
{
    
    if (is_numeric($length)){
        // not null
        // assume offset and length are both provided, since they come as a pair.
        $offset = $offsetOrConnection;
    } else {
        // length unspecified. Assume offset isn't, either.
        $offset = null;
        $length = null;
        $connection = $offsetOrConnection;
    }
    
    
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
        
    if (!$name) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Name is required.', 'Name is required.');
        return false;
    }

    // error if offset or length are not numeric
    if ($offset && !is_int($offset) && !ctype_digit($offset)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Offset must be an integer.', 'Offset must be an integer.');
        return false;
    }
    if ($length && !is_int($length) && !ctype_digit($length)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Length must be an integer.', 'Length must be an integer.');
        return false;
    }
    
    
    // params OK at this point

    // adjust length for "all"
    $lengthToUse = $length;
    if (!$length || ($length <= 0)) {
    	// length not provided or 0 or -1: use *ALL
    	$lengthToUse = '*ALL';
    } //(length absent or <= 0)
    
    
    // If a library (optional--slash delimited), separate it.
    // use *LIBL if no library specified
    $name = strtoupper($name);
    $libAndObj = splitLibObj($name, '*LIBL');     
    
    try {
        	$dataAreaObj = new DataArea($connection);
            $dataAreaObj->setDataAreaName($libAndObj['obj'], $libAndObj['lib']);
        	
            $value = $dataAreaObj->readDataArea($offset, $lengthToUse);

    } catch (Exception $e) {
        i5CpfError('Error reading from data area', $e->getMessage());
        return false;
    }
    
    if ($value) {
        return $value;
    } else {
        i5CpfError('Could not read from data area.', $dataAreaObj->getError());
        return false;
    }
    
} //(i5_data_area_read)
    
    /**
     * Writes data to data area. Offset and length can be specified together, as a pair, or not at all.
     *
     * @param string $name Name of data area
     * @param string $value Data to write
     * @param int|ToolkitServiceCw $offsetOrConnection Offset a which to write data (omit to start from beginning) OR connection
     * @param int $length Length of data to write. "If value is shorter than length it is padded to the length. If it's longer it is truncated."
     * @param ToolkitServiceCw $connection
     * @return boolean True on success, false on failure
     */
function i5_data_area_write($name, $value, $offsetOrConnection = null, $length = null, $connection = null)
{
    if (isset($length)) {
        // assume offset and length are both provided, since they come as a pair.
         $offset = $offsetOrConnection;
    } else {
        // length unspecified. Assume offset isn't, either.
        $offset = null;
        $connection = $offsetOrConnection;
    }
    
    
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
        
    if (!$name) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Name is required.', 'Name is required.');
        return false;
    }

    if (!$value) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Value is required.', 'Value is required.');
        return false;
    }
    
    // error if offset or length are not numeric
    if ($offset && !is_int($offset) && !ctype_digit($offset)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Offset must be an integer.', 'Offset must be an integer.');
        return false;
    }
    if ($length && !is_int($length) && !ctype_digit($length)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Length must be an integer.', 'Length must be an integer.');
        return false;
    }
    
    // params OK at this point
    
    // if the value isn't surrounded by single quotes, do it, to avoid an error if embedded spaces are present).
    // (This could be left up to users, but it's easy for us to do. See how this goes.)
    $value = "'" . trim($value, "'") . "'";        
    
    // Split library (use *LIBL if no library specified) and program names
    $name = strtoupper($name);
    $libAndObj = splitLibObj($name, '*LIBL');
    
    $dataAreaObj = new DataArea($connection);
    $dataAreaObj->setDataAreaName($libAndObj['obj'], $libAndObj['lib']);
    try {
        $dataAreaObj->writeDataArea($value, $offset, $length);
    } catch (Exception $e) {
        i5CpfError('Error writing to data area', $e->getMessage());
        return false;
    }
    
    return true;
    
} //(i5_data_area_write)
    
    /**
     * Deletes data area
     * @param string $name Name of data area to delete. Possibly lib/name.
     * @param ToolkitServiceCw $connection
     * @return boolean True if successful, false if not.
     */
function i5_data_area_delete($name, $connection = null)
{
  	if (!$connection = verifyConnection($connection)) {
        // still no good for some reason
        return false;
    }
    
    if (!$name) {
        i5ErrorActivity(I5_ERR_PHP_NBPARAM_BAD, I5_CAT_PHP, 'Name is required.', 'Name is required.');
        return false;
    }
    
    // params OK at this point
    
    // Split library (use *LIBL if no library specified) and program names
    $libAndObj = splitLibObj($name, '*LIBL');
        
    $dataAreaObj = new DataArea($connection);
    try {
        $dataAreaObj->deleteDataArea($libAndObj['obj'], $libAndObj['lib']);
    } catch (Exception $e) {
        i5CpfError('Error deleting data area', $e->getMessage());
        return false;
    }
    
    return true;
    
} //(i5_data_area_delete)
    
    /**
     * Description: Create an spool file lists, of certain output queue or for all queues.
     * Return Values: resource if OK, false if failed
     * Arguments:
     * UNDOCUMENTED: jobnumber, jobname, too!!!!! but if specify jobname, must specify all three (name, number, user)
     * "*" for all three will give current job.
     * outq - qualified (optional library included) name for the output queue containing the spool file
     * userdata - the user-supplied key data for the spool file.
     * All keys are optional and can be provided together
     *
     * @param array $description The data by which the sppol files will be filtered, array with following keys: username - username that created the job
     * @param null $connection result of i5_connect
     * @return ListFromApi
     */
function i5_spool_list($description = array(), $connection = null)
{
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
    

    // check that criteria description is an array (OK if empty)
    if($description && !is_array($description)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Criteria elements must be an array', 'Criteria elements must be an array');
        return false;
    }
    
    $userData = '';
    $outq = '';
    $userName = '';
    $jobUserName = '';
    $jobName = '';
    $jobNumber = '';

    // use constants:
    //I5_USERDATA, I5_OUTQ, I5_USERNAME    
    
    if (count($description)) {
        foreach ($description as $type=>$value) {
            switch ($type) {
                case I5_USERDATA:
                    $userData = $value;
                    break;
                case I5_OUTQ:
                    $outq = $value;
                    break;
                case I5_USERNAME:
                    $userName = $value;
                    $jobUserName = $value;
                    break;
                case I5_JOBNAME:
                    $jobName = $value;
                    break;
                case I5_JOBNUMBER:
                    $jobNumber = $value;
                    break;
            }
        }
    } //(if count($description))
    
    
    // if jobname or jobnumber is provided then complete job info (all three params) must be provided.
    // otherwise give an I5_ERR_PHP_LIST_PROP error.
    // In other words,
    // (Not documented but shown in old toolkit unit tests.)
    $specifyJobParams = false;
    if (!empty($jobName) || !empty($jobNumber)) {
        // jobName or jobNumber is specified. Must have all three job params.
        if (empty($jobName) || empty($jobNumber) || empty($jobUserName)) {
            i5ErrorActivity(I5_ERR_PHP_LIST_PROP, I5_CAT_PHP, 'Incomplete job criteria specified.', 'If jobname or jobnumber are specified, then all three job criteria (jobname, jobnumber, and username) must be specified. You can use * to indicate current job.');
            return false;
        }
        
        // if we didn't error out then we have all three job params.
        $specifyJobParams = true;
        
    } //(jobName or jobNumber given)
    
    // if we're not specifying complete job info, pass all three of those parms as blank.
    if (!$specifyJobParams) {
        $jobName = '';
        $jobNumber = '';
        $jobUserName = '';
    } //(if specifyJobParams)
    
    // Set any non-specified fields to "*ALL".
    $userData = ($userData) ? $userData : '*ALL';
    $outq = ($outq) ? $outq : '*ALL';
    $userName = ($userName) ? $userName : '*ALL';
           
    // split up outq in case a library was specified
    $outqName = '';
    $outqLibName = '';
    // if an outq was specified but it's not *ALL
    if ($outq) {
        $objInfo = splitLibObj(strtoupper($outq)); // IBM i objects are usually upper case
        $outqName = $objInfo['obj'];
        $outqLibName = $objInfo['lib'];
        // if outq is not *ALL, it should have a library name
        if (($outq != '*ALL') && empty($outqLibName)) {
            // if no libname, result set will be empty, so might as well alert the user.
            i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing outq library', 'You specified an outq but did not qualify it with a library name.');
            return false;
        }
    } //(if $outq)
    
    
    $apiPgm = 'QGYOLSPL'; /// spool file list
    $apiLib = 'QSYS';
    
    $lengthOfReceiverVariable = 136; // list entry API will have a receiver DS of this length
    
    $outputVarname = 'listinfo';

    $paramXml =
    $connection->getDummyReceiverAndLengthApiXml(1, $lengthOfReceiverVariable) . "\n" .
   // param #3, list info
  $connection->getListInfoApiXml(3) . "\n" .
  $connection->getNumberOfRecordsDesiredApiXml(4) . "\n" .
  $connection->getSortInformationApiXml(5) . "\n" .
  "<parm io='in' comment='6. Filter information' >
    <ds var='filterInfo' comment='Use format OSPF0100' len='dummy' >
      <data type='10i0' comment='Number of user names'>1</data>
      <data type='10a' comment='User name'>$userName</data>
      <data type='2h' comment='Reserved' />
      <data type='10i0' comment='Number of output queue names'>1</data>
      <data type='10a' comment='Output queue name'>$outqName</data>
      <data type='10a' comment='Output queue library name'>$outqLibName</data>
      <data type='10a' comment='Form type'>*ALL</data>
      <data type='10a' comment='User-specified data'>$userData</data>
      <data type='10i0' comment='Number of statuses'>1</data>
      <data type='10a' comment='Spooled file status'>*ALL</data>
      <data type='2h' comment='Reserved' />
      <data type='10i0' comment='Number of printer device names'>1</data>
      <data type='10a' comment='Printer device name'>*ALL</data>
      <data type='2h' comment='Reserved' />
     </ds>
  </parm>
  <parm io='in' comment='7. Qualified job name. Pass blank to ignore.'>
    <ds var='qualifiedJobName' comment='Qualified job name, total length 26'>
     <data var='jobName' type='10a' comment='Job name'>$jobName</data>
     <data var='userName' type='10a' comment='User name'>$jobUserName</data>
     <data var='jobNumber' type='6a' comment='Job number'>$jobNumber</data>
    </ds>
  </parm>

  <parm io='in' comment='8. Format of the generated list.'>
     <data var='listFormat' type='8a'>OSPL0300</data>
  </parm>\n" .  
   // param number 9
   ToolkitServiceCw::getErrorDataStructXml(9) .
  "\n<parm io='in' comment='10. Format name'>
    <data var='format' type='8a' comment='Format name of data to receive'>OSPF0100</data>
  </parm>";
      
    // now call it!
    // pass param xml directly in.
        $retPgmArr = $connection->PgmCall($apiPgm, $apiLib, $paramXml);

        if($connection->getErrorCode()) {
            i5CpfError($connection->getErrorCode(), $connection->getErrorMsg());
            return false;
        }
        
        
        $retArr = $retPgmArr['io_param']['listinfo']; // 'listinfo' defined in getListInfoApiXml()
        //d($retArr);
        $totalRecords = $retArr['totalRecords'];
        $requestHandle = $retArr['requestHandle'];
        $listStatus = $retArr['listStatus']; // in case we need it

        
    // receiver data we want to see
    $receiverDs = "
      <data var='JOBNAME' type='10a' comment='Job name' />
      <data var='USERNAME' type='10a' comment='User name' />
      <data var='JOBNBR' type='6a' comment='Job number' />
      <data var='SPLFNAME' type='10a' comment='Spooled file name' />
      <data var='SPLFNBR' type='10i0' comment='Spooled file number' />
      <data var='SPLFSTAT' type='10i0' comment='Spooled file status' />
      <data var='DATEOPEN' type='7a' comment='Date file was opened (created)' />
      <data var='TIMEOPEN' type='6a' comment='Time file was opened (created)' />
      <data var='SCHED' type='1a' comment='Spooled file schedule' />
      <data var='SYSTNAME' type='10a' comment='Spooled file system name' />
      <data var='USERDATA' type='10a' comment='User-specified data' />
      <data var='FORMTYPE' type='10a' comment='Form type' />
      <data var='OUTQNAME' type='10a' comment='Output queue name' />
      <data var='OUTQLIB' type='10a' comment='Output queue library name' />
      <data var='STORPOOL' type='10i0' comment='Auxiliary storage pool' />
      <data var='SPLFSIZE' type='10i0' comment='Size of spooled file' />
      <data var='SPLFMULT' type='10i0' comment='Spooled file size multiplier' />
      <data var='PAGES' type='10i0' comment='Total pages' />
      <data var='COPILEFT' type='10i0' comment='Copies left to produce' />
      <data var='PRIORITY' type='1a' comment='Priority' />
      <data var='reserved' type='3h' comment='Reserved' />
      <data var='INETPRINT' type='10i0' comment='Internet print protocol job identifier' />";

    // from this point on, no CPFs will happen in this function.
    noError();
        
    // make request for objects, but don't return any yet.
    // Get a handle and total number of records.
    // listinfo: totalRecords, firstRecordNumber, requestHandle. if firstRec... < totalRecords then can continue.
    // return I5_ERR_BEOF when went past last record. get CPF GUI0006 when used invalid record#.
    $listObj = new ListFromApi($requestHandle, $totalRecords, $receiverDs, $lengthOfReceiverVariable, $connection);
    return $listObj;
    

} //(i5_spool_list)

/**
 * Description: Gets spool file data from the queue.
 * Return Values: next spool file data array in the list, or false if queue is empty.
 * The data will be formated using SPLF0300 format. See following link for more details: http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=/apis/QUSLSPL.htm
 * Arguments: Spool_list resource received from i5_spool_list
 *
 * @param null $list
 * @return bool
 */
function i5_spool_list_read(&$list = null)
{
    return listRead($list);
    
} //(i5_spool_list_read)
    
    /**
     * void i5_spool_list_close(resource spool_list)
     *
     * @param null $list
     * @return bool
     */
function i5_spool_list_close($list = null)
{
    return listClose($list);
    
} //(i5_spool_list_close)
    
    /**
     * string i5_spool_get_data(string spool_name, string jobname, string username, integer job_number, integer spool_id [,string filename])
     * Description: Get the data from the spool file.
     * Return Values: If no filename passed as parameter, a string on success, false on failure. If filename passed, return true on success, false on failure.
     * Arguments:
     *
     * @param string $spoolName The spool file name
     * @param string $jobName The name of the job that created the file
     * @param string $userName The username of the job that created the file
     * @param string $jobNumber The number of the job that created the file
     * @param string $spoolNumber ID of the spool file in the queue (NOTE: *LAST is ignored by old toolkit and new toolkit. Gets converted to a 1.)
     * @param string $fileName IFS filename to store the data. If not provided, the data is returned as string
     * @param null $connection
     * @return bool|string
     */
function i5_spool_get_data($spoolName, $jobName, $userName, $jobNumber, $spoolNumber, $fileName = '', $connection = null)
{
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
    
    if (!is_numeric($spoolNumber)) {
        // could be *NEXT or other misguided parameter. Use a '1' for compatibility.
        $connection->logThis("Spool file number value '$spoolNumber' is unsupported. Please supply a number. The value '1' will be used.");
        $spoolNumber = 1;
    }
    
    //  2< /dev/null means to send STDERR messages to the bit bucket (delete). 
    // We do this to avoid bogus warnings such as 
    // "CPC2206:  Ownership of object QZSHSYSTEM in QTEMP type *USRSPC changed"
    // and the generation of extra spool files containing that warning.
    $cmdString = "catsplf -j {$jobNumber}/{$userName}/{$jobName} {$spoolName} {$spoolNumber}  2> /dev/null ";

    
    // TODO Would be better if Qshellcommand or interactive could get contents of txt file to avoid giving permissions to QTMHHTTP web user, and to work in 2-tier setups.
    // XMLSERVICE may provide this feature in the future.
    
    // if a filename was given, ask QSH to copy the data directly into that file.
    // Currently this only works when run directly on an IBM i.
    if ($fileName && $connection->isPhpRunningOnIbmI()) {
    	
    	    // create file if it does not exist.
    	    // In case it did exist, change its CCSID to the one used by PHP.
    	    // QIBM_PASE_CCSID=819
    	    		
    	    $ccsid = $connection->getPhpCcsid();
    	    	    
    	    // command to set up IFS file with proper CCSID
    	    // Qshell allows multiple commands separated by semicolon
    	    $ccsidCommand = "touch -C $ccsid $fileName;setccsid $ccsid $fileName";
    	    $result = $connection->qshellCommand($ccsidCommand);

    	    if ($result === false) {
    	        
    	    	        	// error writing file, possibly.
    	    	    
    	    	        	// if get this then it's a file problem:
    	    	    	    //<row><![CDATA[QSH0005: Command ended normally with exit status 2.]]></row>
    	    	    	    //<row><![CDATA[qsh: 001-0055 Error found creating file /tmpxyz/alanspool14.txt. No such path or directory.]]></row>
    	    	    
    	    	    	    $errMsg = $connection->getErrorMsg();
    	    	    	    $errCode = $connection->getErrorCode();
    	    	    	    // bad! Could not write file. Old toolkit used CPF9898 so let's also use it.
    	    	    	    i5CpfError('CPF9898', "Could not get spool file and write to '$fileName'. Reason: $errCode");
    	    	    	    return false;
    	    } //(false)

    	    // Redirect output to file we created/specified. Can't single quotes around file name in case it contains spaces because CMD(' already has single quotes
    	    // Avoid paths containing spaces!
    	    $cmdString .= " > $fileName";

    	    $result = $connection->qshellCommand($cmdString);
    	    	    
    	    if ($result === false) {

    	    	    // error writing data to file, possibly.
    	    	    
    	    	    // if get this then it's a file problem:
    	    	    //<row><![CDATA[QSH0005: Command ended normally with exit status 2.]]></row>
    	    	    //<row><![CDATA[qsh: 001-0055 Error found creating file /tmpxyz/alanspool14.txt. No such path or directory.]]></row>
    	    	    
    	    	    // or: Reason: catsplf: 001-2373 Job 956066/CP40B/APRTCHK was not found." }
    	    	    $errMsg = $connection->getErrorMsg();
    	    	    $errCode = $connection->getErrorCode();
    	    	    // bad! Could not write file. Old toolkit used CPF9898 so let's also use it.
    	    	    i5CpfError('CPF9898', "Could not get spool file and write to '$fileName'. Reason: $errCode");
    	    	    return false;

    	    } else {
    	    	    // successfully wrote to IBM i file
    	    	    return true;
    	    }//(if ($result === false))
    	    	    
    } else {
    	
    	    // either no filename supplied or not on IBM i.
    	    // need to get the data either way.
        $result = $connection->qshellCommand($cmdString);
    	
        // if got here, and no error, we expect an array.
    	    if (!is_array($result)) {
       	    // not an array. Probably a "false."
    		    // Report the error.
    		    $errMsg = $connection->getErrorMsg();
    		    if (empty($errMsg)) {
    			    $errMsg = 'Could not read spooled file. Check user permissions or see error code for details.';
    		    } //(if (empty($errMsg)))
    		 
    		    i5CpfError($connection->getErrorCode(), $errMsg);
    		    return false;
    	    } //(if is_array)

    	    // We got data successfully.
    	    // consolidate into a string with 0D0A separators
    	    $resultString = trim(implode("\r\n", $result));
    	    	
    	    // if we're to write data to a local file (non-IBM i), do it.
    	    if ($fileName) {
    	    	
    	        	/*
    	    	     * Not IBM i but filename was supplied
    	    	     * Since PHP not running on IBM i, PHP won't be able to a retrieve file from IFS later.
    	    	     * So write to local file system instead.
    	    	     * In the future, if XMLSERVICE can retrieve an IBM i IFS file,
    	    	     * PHP will be able to read the file back and this behavior can change.
    	    	     * Note: we're keeping backward compatibility with original CW behavior here, not wanting to break anyone's application.
    	    	     */
    	    	     $bytesWritten = file_put_contents($fileName, $resultString);
    	    	     if ($bytesWritten) {
    	    	     	 // successfully wrote to file
    	    	         return true;
    	    	     } else {
    	    		     // bad! Could not write file locally. Old toolkit used CPF9898 so let's also use it.
    	    		     i5CpfError('CPF9898', "Could not write to file '$fileName'.");
    	    		     return false;
    	    		     
    	    	     } //(if !$bytesWritten)

    	    } else {
    	    	    
    	    	    // no file name supplied.     
    	    	    // Return string to caller.
    	    	    return $resultString;
    	    	
    	    } //(if $fileName)
    			
     	    
    } //(if ($fileName))
    
    
} //(i5_spool_get_data)


// Object listings
/*resource i5_objects_list(string library, [string name, string type, resource connection])
 Description: Open an object list.
 Return Values: Resource for fetch if everything is OK, false on error.
 Arguments:
 library - Library name (can be also *CURLIB or I5_CURLIB). How about *LIBL? Try it
 name - Name or wildcard of objects to read, default is "*ALL".
 type - Object type to fetch (*ALL or I5_ALL_OBJECTS for all)
 connection - Connection - result of i5_connect*/
    
    /**
     * @param $library
     * @param string $name
     * @param string $type
     * @param null $connection
     * @return bool|ListFromApi
     */
function i5_objects_list($library, $name = '*ALL', $type = '*ALL', $connection = null)
{

// if object does not exist: No error. Get a resource but then an empty list.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
 
    // TODO more type testing needed.    
    if($name && !is_string($name)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Object name must be a string', 'Object name must be a string');
        return false;
    }
    if(!$library) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Library is required.', 'Library is required.');
        return false;
    }

    $apiPgm = 'QGYOLOBJ'; /// object list
    $apiLib = 'QSYS';
    
    $lengthOfReceiverVariable = 672; // list entry API will have a receiver DS of this length
    
    $outputVarname = 'listinfo';
    
    $paramXml = "
     <parm io='out' comment='1. receiver. do not receive anything here. Wait till Get List Entry'>
    <data type='1h' comment='use hole type because do not expect data at this stage' />
  </parm>
  <parm io='in' comment='2. Length of receiver variable'>
    <data var='receiverLen' type='10i0' comment='format 0700 should have size $lengthOfReceiverVariable.'>$lengthOfReceiverVariable</data>
  </parm>\n" .  $connection->getListInfoApiXml(3) . "\n" .
  "<parm io='in' comment='4. Number of records to return. Use zero to offload to Get List Entries API'>
    <data var='numRecsDesired' type='10i0'>0</data>
  </parm>
  <parm io='in' comment='5. Sort information' >
    <ds var='sortInfo'>
     <data var='numSortKeys' comment='Number of keys to sort on. Use zero' type='10i0'>0</data>
    </ds>
  </parm>
  <parm io='in' comment='6. Object and library name'>
    <ds var='objectAndLibrary'>
     <data var='object' type='10a'>$name</data>
     <data var='library' type='10a'>$library</data>
    </ds>
  </parm>
  <parm io='in' comment='7. Object type'>
    <data var='objType' type='10a'>$type</data>
  </parm>
  <parm io='in' comment='8. Authority control'>
    <ds var='authorityControlFormat'>
     <data var='lengthOfFormat' type='10i0' comment='28 is minimum size: no authorities'>28</data>
     <data var='callLevel' type='10i0' comment='Call level'>0</data>
     <data var='offsetObjAuth' type='10i0' comment='Displacement to object authorities'>0</data>
     <data var='numObjAuth' type='10i0' comment='Number of object authorities'>0</data>
     <data var='offsetLibAuth' type='10i0' comment='Displacement to library authorities'>0</data>
     <data var='numLibAuth' type='10i0' comment='Number of library authorities'>0</data>
     <data var='reserved' type='10i0' comment='Reserved'></data>
    </ds>
  </parm>
  <parm io='in' comment='9. Selection control'>
    <ds var='selectionControlFormat'>
     <data var='lengthOfFormat' type='10i0' comment='21 is minimum length: one selection value'>21</data>
     <data var='selectOmitStatus' type='10i0' comment='0=select on status, 1=omit'>0</data>
     <data var='offsetStatuses' type='10i0' comment='Displacement to statuses'>20</data>
     <data var='numStatuses' type='10i0' comment='Number of statuses. minimum=1'>1</data>
     <data var='reserved' type='10i0' comment='Reserved'></data>
     <data var='status' type='1a' comment='One status field, with * meaning all'>*</data>
    </ds>
  </parm>
  <parm io='in' comment='10. Number of keyed fields to return'>
     <data var='numKeyedFields' type='10i0'>1</data>
  </parm>
  <parm io='in' comment='11. Array of keys of fields to return'>
    <ds var='keysToRequest'>
     <data var='key' type='10i0' comment='use key field format 0700 because it has everything.'>0700</data>
    </ds>
  </parm>\n" .  ToolkitServiceCw::getErrorDataStructXml(12); // param number 12
    
    // now call it!
    // pass param xml directly in.
        $retPgmArr = $connection->PgmCall($apiPgm, $apiLib, $paramXml);
//var_dump($retPgmArr);
//die;
 // there's a problem parsing the output xml.
        
        
        if ($connection->getErrorCode()) {
            i5ErrorActivity(I5_ERR_PHP_AS400_MESSAGE, I5_CAT_PHP, $connection->getErrorCode(), $connection->getErrorMsg());
            return false;
        }
        
       
        $retArr = $retPgmArr['io_param']['listinfo']; // 'listinfo' defined in getListInfoApiXml()
        $totalRecords = $retArr['totalRecords'];
        $requestHandle = $retArr['requestHandle'];
        $listStatus = $retArr['listStatus']; // in case we need it

        
    // object data we want to see
    $receiverDs = "<data var='NAME' comment='Object name' type='10a'></data>
      <data var='LIBRARY' comment='Library name' type='10a'></data>
      <data var='TYPE' comment='Object type' type='10a'></data>
      <data var='STATUS' comment='Information status. Blank means success' type='1a'></data>
      <data var='reserved' comment='Reserved' type='1h'></data>
      <data var='numFields' comment='Number of fields returned (should be 1)' type='10i0'></data>
     
      <data var='fieldInfoLen' comment='Length of this info struct' type='10i0'></data>
      <data var='keyField' comment='Key field for field returned' type='10i0'></data>
      <data var='dataType' comment='Type of data: B, C, S (structured)' type='1a'></data>
      <data var='reserved' comment='Reserved' type='3h'></data>
      <data var='dataLen' comment='Length of data returned (620)' type='10i0'></data>

      <data var='infoStatus' comment='Info status for requested key field (start of data)' type='1a'></data>
      <data var='EXT_ATTR' comment='Extended attribute' type='10a'></data>
      <data var='DESCRIP' comment='Text description' type='50a'></data>
      <data var='USR_ATTR' comment='User-defined attribute' type='10a'></data>
      <data var='FILER1' comment='Filler. Library list order and reserved' type='9h'></data>
      <data var='AUX_POOL' comment='Object auxiliary storage pool (ASP) number' type='10i0'></data>
      <data var='OWNER' comment='Object owner' type='10a' />
      <data var='DOMAIN' comment='Object domain (*U=user, *S=system)' type='2a'></data>
      <data var='CRE_DAT' dtsdate='on' comment='Creation date and time (system format, use QWCCVTDT)' type='8b'></data>

      <data var='CHG_DAT' dtsdate='on' comment='Change date and time (system format, use QWCCVTDT)' type='8b'></data>
      <data var='STORAGE' comment='Storage' type='10a'/>
      <data var='COMPRESS' comment='Object compression status' type='1a'></data>
      <data var='ALWPGMCHG' comment='Allow change by program' type='1a'></data>
      <data var='PGM_CHG' comment='Changed by program' type='1a'></data>
      <data var='AUDIT' comment='Object auditing value' type='10a'></data>
      <data var='FILLER2' comment='Filler (digitally signed stuff, reserved, library ASP)' type='9h' />

      <data var='SRC_FILE' comment='Source file name' type='10a' />
      <data var='SRC_LIB' comment='Source file library name' type='10a' />
      <data var='SRC_MBR' comment='Source file member name' type='10a' />
      <data var='SRC_TIME' comment='Source file updated date and time (CYYMMDDHHMMSS)' type='13a' />
      <data var='CREATOR' comment='User profile of creator' type='10a' />
      <data var='SYS_NAME' comment='System where object was created' type='8a' />
      <data var='SYS_LEVEL' comment='System level' type='9a' />
      <data var='COMPILER' comment='Compiler' type='16a' />
      <data var='OBJ_LEVEL' comment='Object level' type='8a' />
      <data var='USR_CHG' comment='User changed' type='1a' />
      <data var='LIC_PGM' comment='Licensed program' type='16a' />
      <data var='PTF' comment='Program temporary fix (PTF)' type='10a' />
      <data var='APAR' comment='Authorized program analysis report (APAR)' type='10a' />
      <data var='FILER3' comment='Filler: Primary Group, reserved, optimum space align, reserved' type='21h' />

      <data var='SAV_TIME' dtsdate='on' comment='Object saved date and time' type='8b' />
      <data var='RST_TIME' dtsdate='on' comment='Object restored date and time' type='8b' />
      <data var='SAV_SIZE' comment='Saved size' type='10i0' />
      <data var='SAV_MLTP' comment='Saved size multiplier' type='10i0' />
      <data var='SAV_SEQNBR' comment='Save sequence number' type='10i0' />
      <data var='SAV_CMD' comment='Save command' type='10a' />
      <data var='SAV_VOLID' comment='Save volume ID' type='71a' />

      <data var='SAV_DEV' comment='Save device' type='10a' />
      <data var='SAV_FIL' comment='Save file name' type='10a' />
      <data var='SAV_LIB' comment='Save file library name' type='10a' />
      <data var='SAV_LABEL' comment='Save label' type='17a' />
      <data var='SAV_ACTTIM' comment='Save active date and time' type='8b' />
      <data var='FILER4' comment='Filler from Journal status to end of format' type='44h' />

      <data var='USE_TIME' dtsdate='on' comment='Last-used date and time' type='8b' />
      <data var='RESET_TIME' dtsdate='on' comment='Reset date and time' type='8b' />
      <data var='USE_DAYS' comment='Days-used count' type='10i0' />
      <data var='USE_TIME' comment='Usage information updated' type='1a' />
      <data var='FILER5' comment='Filler to end of format (object and lib ASP, reserved)' type='23h' />
      <data var='OBJ_SIZE' comment='Object size' type='10i0' />
      <data var='SIZE_MLTP' comment='Object size multiplier' type='10i0' />
      <data var='OVF_ASP' comment='Object overflowed auxiliary storage pool (ASP)
indicator' type='1a' />
      <data var='FILER6' comment='Filler to end of format' type='63h' />
    ";

    // from this point on, no CPFs will happen in this function.
    noError();
    
    // make request for objects, but don't return any yet.
    // Get a handle and total number of records.
    // listinfo: totalRecords, firstRecordNumber, requestHandle. if firstRec... < totalRecords then can continue.
    // when reading list, return I5_ERR_BEOF when went past last record. get CPF GUI0006 when used invalid record#.
    $listObj = new ListFromApi($requestHandle, $totalRecords, $receiverDs, $lengthOfReceiverVariable, $connection);
    return $listObj;
    
 
/*    $objectList = $objectObj->getObjectListCw($name, $library, $type);
    return $objectList;    
*/    
} //(i5_objects_list)
    
    /**
     * array i5_objects_list_read (resource list)
     * Description: Get an array for an object list entries.
     * Return Values: Array with the object element if OK; false if failed.
     * Arguments: List - Resource returned by i5_objects_list
     *
     * generic list reader
     * pass list by reference so its current element can be advanced
     *
     * @param $list
     * @return bool
     */
function listRead(&$list)
{
    $connection = $list->getConn();
    if (!$connection) {
        return false;
    }    

    // try to get the next list entry
    // Note: this "list" logic is generic for all sorts of API lists.
    $entry = $list->getNextEntry();
    
    if (!$entry) {
        // no entry received
        // if no error, or GUI0006/1 "end of file"
        // we simply have no more records to receive.
        $errorCode = $connection->getErrorCode();
        if ($errorCode == '' || $errorCode == 'GUI0006' || $errorCode == 'GUI0001') {
            i5ErrorActivity(I5_ERR_BEOF, I5_CAT_PHP, 'No more entries.', 'No more entries.');
        } else {
            // a real error.
             i5CpfError($errorCode, $connection->getErrorMsg());
        } //(errorcode check)
        
        return false;
        
    } //(no entry)
    
    // We have an entry
    noError();
    return $entry;
    
    
} //(listRead)
    /**
     * generic list close-er
     * pass list by reference so list can be deactivated
     *
     * @param $list
     * @return bool
     */
function listClose(&$list)
{

    
    // if no list, return true, because it's not open.
    if (!$list) {
        noError();
        return true;
    }

    $connection = $list->getConn();
    
    // close the list
    $success = $list->close();
    
    if ($success) {
        noError();
        $list = null; // deactivate list variable since we closed the list.
    } else {
        // error closing list. Provide error code/message.
           i5CpfError($connection->getErrorCode(), $connection->getErrorMsg());
           return false;
    }

    return $success;

} //(listClose)
    
    /**
     * pass list by reference so its current element can be advanced
     *
     * @param $list
     * @return bool
     */
function i5_objects_list_read(&$list)
{
    return listRead($list);
    
} //(i5_objects_list_read)
    
    /**
     * bool i5_ objects_list_close (resource handle)
     * Description: Close handle received from i5_ objects_list ().
     * Return Values: Boolean success value
     * Arguments: handle - Object list handle as returned by i5_ objects_list ()
     */
function i5_objects_list_close(&$list)
{
    return listClose($list);
    
} //(i5_objects_list_close)

// data queues
// Note: there's no "dtaq_create" or "dtaq_delete" in the old toolkit.
    
    /**
     * resource i5_dtaq_prepare(string name, array description [,int key][,resource connection])
     * Description: Opens a data queue with optional description.
     * Return Values: Resource if OK, false if failed.
     * Arguments:
     * $name - The queue name
     * $description - Data description in format defined by program_prepare. For more, see PHP Toolkit Data Description.
     * [$key - key size - for keyed DataQ (can be omitted)]
     * [$connection - Connection - result of i5_connect]
     *
     * @param $name
     * @param $description
     * @param int $keySizeOrConnection
     * @param ToolkitServiceCw $connection
     * @return DataDescription
     */
function i5_dtaq_prepare($name, $description, $keySizeOrConnection = 0, ToolkitServiceCw $connection = null)
{

    $keySize = 0; // init
    // user is allowed to omit $keySize, so there may be a variable number of parameters
        
    // if $connection was passed, we know 4 args were passed.
    // If not, we need to check.
    if ($connection) {

        // connection was passed, so 3rd param is keySize.
        $keySize = $keySizeOrConnection;
        
    } else {
        $numArgs = func_num_args();
           
        if ($numArgs == 3) {
    
            // either key or connection was passed. Figure out which one.
            if (is_numeric($keySizeOrConnection)) {
                // numeric. Assume it's the key size.
                $keySize = $keySizeOrConnection;
                $connection = null;
            } else {
                // not numeric. keySize not used.
              $keySize = 0;
              // assume the third param is connection.
              $connection = $keySizeOrConnection;
          } //(is_numeric($keySizeOrConnection))
        }
    } //(if $connection)    
    
    // if conn not passed in, or passed as null/0, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
    
    // look for params
    if (!isset($name)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing data queue name', 'Missing data queue name');
        return false;
    }
    if (!is_string($name)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Data queue name must be a string', 'Data queue name must be a string');
        return false;
    }
    if (!isset($description)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing description', 'Missing description');
        return false;
    }
    if (!is_array($description)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Description must be an array', 'Description must be an array');
        return false;
    }

    // key size must be numeric if exists
    if (!empty($keySize) && !is_numeric($keySize)) {
           i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Key size must be numeric', 'Key size must be numeric');
        return false;
    }

    // avoid case sensitivity problems with a copy of array with lower-case keys.
    $lowerDesc = array_change_key_case($description, CASE_LOWER);
    
    // Some of the following should also apply to user spaces, where
    // multiple parameters get condensed into one.
    
    // If there's a single DS given, not wrapped in an array, remove the DS entirely, because according to documented use cases (e.g. p398 of Zend Server manual),
    // the old toolkit will treat the DS contents as individual elements.
    if (isset($lowerDesc['dsname'])) {
        if (isset($lowerDesc['dsparm'])) {
            // use array from value of dsparm.
            $lowerDesc = $lowerDesc['dsparm'];
        } else {
               i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Description has DSName but not DSParm', 'Description has DSName but not DSParm');
            return false;
        } //(if dsparm is set)
    } //(if dsname is set)
    
    // If data queue's data description is only one level deep (allowed in old toolkit), add a level so that it can sail through the conversion process.
    // that is, if the Name is given on the first level, not inside another array.
    // We'll remove this level later.
    // Example: description can be $desc = array("Name" => "MyVal", "Type" => I5_TYPE_CHAR, "length" => 10);
    //          with input value:        $value = 'abcd';
    $isSingleLevelSimpleValue = false;
    // if first element in array is NOT another array
    
    if (!is_array(current($lowerDesc))) {

    	// A single-level description passed to data queue prepare. Expect a single value for receive or send.
        $isSingleLevelSimpleValue = true;
        // wrap in an array. 
        $description = array($lowerDesc);    
        //$connection->logThis("Single-level description passed to data queue prepare. Expect a single value for receive or send.");
        
    } else {
        // normal. not single-level. No need to wrap desc in an array.
        $description = $lowerDesc;
    } //(outer layer is not an array)
    
    // use object that can transform and check description for us.
    $descObj = new DataDescription($name, $description, $connection);
    
    $descObj->setIsSingleLevelSimpleValue($isSingleLevelSimpleValue);
    
    // keysize
    if ($keySize) {
        $descObj->_miscAttributes['keySize'] = $keySize;
    }

    noError();
    return $descObj;
    
} //(i5_dtaq_prepare)
    
    /**
     * mixed i5_dtaq_receive(resource queue[, string/int operator, string key][, int timeout])
     * Description: Reads data from the data queue.
     * Return Values: False if could not read because of error or timeout, the data read from the queue otherwise.
     * Arguments:
     * queue - resource received from dtaq_open
     * operator:
     * "EQ"
     * "GT"
     * "LT"
     * "GE"
     * "LE"
     * key- key value to look for
     * timeout - timeout value in seconds
     *
     * @param $queue
     * @param string $operatorOrTimeout
     * @param string $key
     * @param int $timeout
     */
function i5_dtaq_receive($queue, $operatorOrTimeout = '', $key = '', $timeout = 0)
{
    // if <=2 params are received, the second one is timeout.
    // if >2 params are received, the second one is operator.
    $numArgs = func_num_args();
    if ($numArgs <= 2) {
        $timeout = $operatorOrTimeout;
        $operator = '';
    } else {
        // > 2
        $operator = $operatorOrTimeout;
    }
    
    if (isset($queue->_miscAttributes['keySize'])) {
        $keySize = $queue->_miscAttributes['keySize'];
    }
        
    if (!isset($queue)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing data queue description', 'Missing data queue description');
        return false;
    }
    if (!is_object($queue)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Data queue description must be a description resource', 'Data queue description must be a description resource');
        return false;
    }
    
    if ($operator && !is_string($operator)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Operator must be a string', 'Operator must be a string');
        return false;
    }
    
    if ($key && (!is_string($key) && !is_numeric($key))) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Key must be a string or number', 'Key must be a string or number');
        return false;
    }
    
    if ($timeout && !is_numeric($timeout)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Timeout must be numeric', 'Timeout must be numeric');
        return false;
    }
    
    $conn = $queue->getConnection();  
    
    // get lib and obj
    $queueInfo = $queue->getObjInfo();
    
    // convert from old to new param format, inserting input values
    $queue->setIsReceiverOnly(true); // receiver only, so it will use default/blank input values.
    
    $labelForSizeOfInputData = 'dssize';
    $receiverVarName = 'receiverdata';
    
    $oldDescription = $queue->getOriginalDescription();
    
    // wrap data var in a ds so we can set size and get the result easily
    // and also to wrap "loose" params passed in
    $wrappedOldToolkitParams = array(array('DSName'=>$receiverVarName, 'DSParm'=>$oldDescription));
    // use null because we're creating a receiver with no data yet.
    $newInputParams = $queue->generateNewToolkitParams(null, $wrappedOldToolkitParams);
    
    // set back to default (false) for future use
    $queue->setIsReceiverOnly(false); 
    
    if (!$newInputParams) {
        // some problem converting
        return false;
    }

    // only want one param, the data queue description
       $receiveStructure = $newInputParams[0];
    
        $toolkitParams = array();
        $toolkitParams [] = $conn->AddParameterChar ( 'in', 10, 'dqname', 'dqname', $queueInfo['obj']);
        $toolkitParams [] = $conn->AddParameterChar ( 'in', 10, 'dqlib', 'dqlib', $queueInfo['lib']);
        
        $toolkitParams [] = $conn->AddParameterPackDec ( 'out', 5, 0, 'datalen', 'datalen', 100); // output so value doesn't matter
        // wrap receiver var in a ds so we can set size and get the result easily
        
        // update "label for size of structure" in structure, so XMLSERVICE can get its size
        $receiveStructure->setParamLabelLen($labelForSizeOfInputData);
        $toolkitParams[] = $receiveStructure;//$receiveDs[] =

        $toolkitParams [] = $conn->AddParameterPackDec ( 'in', 5, 0, 'waittime', 'waittime', $timeout );

        // not supporting sender info.
        $senderInfLen = 0;
        $senderInf = '';
        
        // if no keysize, first optional parameter group should be all zero or blank so the API will ignore it.
        if (!isset($keySize) || empty($keySize)) {
            $operator = '';
            $keySize = 0;
            $key = '';
        }
            
        $toolkitParams [] = $conn->AddParameterChar ( 'in', 2, 'keyorder', 'keyorder', $operator );
        $toolkitParams [] = $conn->AddParameterPackDec ( 'in', 3, 0, 'keydatalen', 'keydatalen', $keySize  );
        $toolkitParams [] = $conn->AddParameterChar ( 'both', ( int ) $keySize , 'keydata', 'keydata', $key);
        $senderInf = ' ';
        $toolkitParams [] = $conn->AddParameterPackDec ( 'in', 3, 0, 'senderinflen', 'senderinflen', $senderInfLen );
        $toolkitParams [] = $conn->AddParameterHole ( 'out', 1, 'senderinf', 'senderinf', $senderInf);
            
/*            if( $WithRemoveMsg == 'N')
                $Remove= '*NO       ';
            else     
                $Remove= '*YES      ';
*/
        
        $remove = '*YES      '; // default. no param for this in old toolkit
        $toolkitParams [] = $conn->AddParameterChar ( 'in', 10, 'remove', 'remove',  $remove );
        $toolkitParams [] = $conn->AddParameterSizePack ( 'in', 'receiversize', $labelForSizeOfInputData);
        //$toolkitParams [] = $conn->AddErrorDataStruct();    
        $toolkitParams [] = $conn->AddErrorDataStructZeroBytes(); // so errors bubble up to joblog
        
        $retPgmArr = $conn->PgmCall ( 'QRCVDTAQ', 'QSYS', $toolkitParams);

    // check for any errors
    if ($conn->getErrorCode()) {
    	// an error
        i5CpfError($conn->getErrorCode(), $conn->getErrorMsg());
        return false;
    } else {
        // extricate the data from the receiver variable ds wrapper
        $outputArray = $retPgmArr['io_param'][$receiverVarName];  

        // shorthand, if description was a single character desc, return that char.

        // was description / prepare a single-level array, implying a single value to be passed in?
        $isSingleLevelSimpleValue = $queue->getIsSingleLevelSimpleValue();
        
        // if description/prepare was a single-level array, that is, a single value, then we expect that user passed in a value of the right type.
        // 1. get the name of field.
        // 2. extract data from that field name.
        if ($isSingleLevelSimpleValue) {
            $oldDescription = $queue->getOriginalDescription();
    
            // extract the important array from shell array
            $name = '';
            reset($oldDescription); // just in case
            if (($innerDesc = current($oldDescription)) !== false) {
                // there's the inner array. make keys lower case
                $innerDesc = array_change_key_case($innerDesc, CASE_LOWER);
                // get the field name
                $name = (isset($innerDesc['name'])) ? $innerDesc['name'] : '';
            }  
            
            if ($name) {
                // have the field name. return data found under that name.
                $singleValue = (isset($outputArray[$name])) ? $outputArray[$name] : '';  
                $returnVal = $singleValue;
                
            } else {
                i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Single-level description is missing a field name.');
                return false;    
            }
    
            // TODO also check for data type

        } else {
            // normal multi-level description
            $returnVal = $outputArray;
            
        } //(if single level simple value)

        noError();
        return $returnVal;
    } //(if ($conn->getErrorCode()))
    
        
} //(i5_dtaq_receive)

/**
 * bool i5_dtaq_send(resource queue, string key, mixed data)
 * Description: Puts data to the data queue.
 * Return Values: False if could not be written because of error, true
 * otherwise. Arguments: queue - resource received from dtaq_open key - key
 * value to look for data - data to put into the queue The data should conform
 * to the description format, and can be either in flat array or key->value
 * pair array. Yes, "flat array" should work. This is a single-level array
 * description, in which case the data will be just a string value or some
 * scalar value.
 *
 * @param $queue
 * @param string $key
 * @param $data
 * @return bool
 */
function i5_dtaq_send($queue, $key='', $data)
{
    if (!$queue) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing data queue description', 'Missing data queue description');
        return false;
    }
    if (!is_object($queue)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Data queue description must be a description resource', 'Data queue description must be a description resource');
        return false;
    }
    if (!is_string($key)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Key must be a string if it is supplied.', 'Key must be a string if it is supplied.');
        return false;
    }

    if (isset($queue->_miscAttributes['keySize'])) {
        $keySize = $queue->_miscAttributes['keySize'];
    }

    $conn = $queue->getConnection();  
    
    // get lib and obj
    $queueInfo = $queue->getObjInfo();
    
    // is the description / prepare a single-level array, implying a single value to be passed in?
    $isSingleLevelSimpleValue = $queue->getIsSingleLevelSimpleValue();
    
    $oldDescription = $queue->getOriginalDescription();
    
    // if description/prepare was a single-level array, that is, a single value, then we expect that user passed in a value of the right type.
    // 1. get the name of field.
    // 2. make "data" into an array with field name as key.
    if ($isSingleLevelSimpleValue) {
        
        // Get name of single field from data description
        $name = '';
        reset($oldDescription); // just in case
        // extract the important array from shell array
        if (($innerDesc = current($oldDescription)) !== false) {
            // there's the inner array. make keys lower case
            $innerDesc = array_change_key_case($innerDesc, CASE_LOWER);
            $name = (isset($innerDesc['name'])) ? $innerDesc['name'] : '';
        }  
        
        if ($name) {
            // have the field name. make a data input array with it.
            $data = array($name=>$data);
        } else {
            i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Single-level description is missing a field name.');    
        }

        // TODO also check that data type in description matches data type in $data.
                
    } //(if single level simple value)

    $labelForSizeOfInputData = 'dssize';
    $dsVarName = 'datavalue';
    
    // wrap data var in a ds so we can set size and get the result easily
    // and also to wrap "loose" params passed in
    $wrappedOldToolkitParams = array(array('DSName'=>$dsVarName, 'DSParm'=>$oldDescription));

    // wrap the data, too, so it'll match up
    $data = array($dsVarName => $data);
    
    $newInputParams = $queue->generateNewToolkitParams($data, $wrappedOldToolkitParams);

    if (!$newInputParams) {
        // some problem converting
        return false;
    }

    // only want one param, the data queue data description
    $sendStructure = $newInputParams[0];

    //QSNDDTAQ
                
    $toolkitParams = array();
    $toolkitParams [] = $conn->AddParameterChar ( 'in', 10, 'dqname', 'dqname', $queueInfo['obj']);
    $toolkitParams [] = $conn->AddParameterChar ( 'in', 10, 'dqlib', 'dqlib', $queueInfo['lib']);
    $toolkitParams [] = $conn->AddParameterSizePack('in', 'datalen', $labelForSizeOfInputData);
    //$sendDs[] = $sendStructure;

    // update "label for size of structure" in structure, so XMLSERVICE can get its size
    $sendStructure->setParamLabelLen($labelForSizeOfInputData);
    $toolkitParams[] = $sendStructure;//$conn->AddDataStruct($sendDs, $dsVarName, 0, '', false, $labelForSizeOfInputData);//
    
    
    // if no keysize (came from preparation of queue),
    // the first optional parameter group should be all zero or blank so the API will ignore it.
    if (!isset($keySize) || empty($keySize)) {
        $keySize = 0;
        $key = '';
    }
    $toolkitParams[] = $conn->AddParameterPackDec('in', 3, 0, 'keydatalen','keydatalen', $keySize);        
    $toolkitParams[] = $conn->AddParameterChar('in', $keySize, 'keydata','keydata',  $key);

    // P.S. No error struct with QSNDDTAQ
    $retPgmArr = $conn->PgmCall ( 'QSNDDTAQ', 'QSYS', $toolkitParams);
        
    if($conn->getErrorCode()) {
       i5CpfError($conn->getErrorCode(), $conn->getErrorMsg());	
       return false;
    }
        
    //io_param]
    if (isset($retPgmArr['io_param'])) {
        noError();
        return true;
    }

} //(i5_dtaq_send)
    
    /**
     * bool i5_dtaq_close(resource queue)
     * Description: Free program resource handle.
     * Return Values: Bool success value.
     * Arguments: queue - (pass by reference so it can be changed) resource received from dtaq_open
     *
     * @param $queue
     * @return bool
     */
function i5_dtaq_close(&$queue)
{
  noError();
  $queue = null;
  return true;
} //(i5_dtaq_close)

// user spaces
    
    /**
     * resource i5_userspace_prepare(string name, array description [, resource connection]).
     * Description: Opens a user space and prepares it to be run.
     * Return Values: Resource if open succeeded, false if open failed.
     * Arguments:
     * name - User space name in library/object format
     * description - Data description in format defined by program_prepare. See PHP Toolkit Data Description.
     * connection - Result of i5_connect
     *
     * @param $name
     * @param $description
     * @param null $connection
     * @return bool|DataDescription
     */
function i5_userspace_prepare($name, $description, $connection = null)
{
    // if conn not passed in, get instance of toolkit. If can't be obtained, return false.
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    
    
    // look for params
    if (!isset($name)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing user space name', 'Missing user space name');
        return false;
    }
    if (!is_string($name)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'User space name must be a string', 'User space name must be a string');
        return false;
    }
    if (!isset($description)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing description', 'Missing description');
        return false;
    }
    if (!is_array($description)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Description must be an array', 'Description must be an array');
        return false;
    }
    
    // use object that can transform and check description for us.
    $descObj = new DataDescription($name, $description, $connection);

    noError();
    return $descObj;

} //(i5_userspace_prepare)
    
    /**
     * bool i5_userspace_put(resource user space, params)
     * Description: Add user space data
     * Return Values: Boolean success value.
     * Arguments:
     * user - space User Space resource opened by i5_userspace_prepare
     * params - Input params according to description. If given as flat array, then parameters are assigned in order (not sure about this)
     *
     * Write date to a user space based on a prepare done before.
     * @param DataDescription $userspace Userspace object created in the preparation stage.
     * @param array $params Input params with key=>value pairs (possibly nested),
     * keys matching what was specified in prepare stage.
     * @return boolean True if successful, false if not.
     */
function i5_userspace_put($userspace, $params)
{
    
    if (!isset($userspace)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing user space description', 'Missing user space description');
        return false;
    }
    if (!is_object($userspace)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'User space description must be a description resource', 'User space description must be a description resource');
        return false;
    }
    if (!isset($params)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Params are required', 'Params are required');
        return false;
    }
    if (!is_array($params)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Params must be an array', 'Params must be an array');
        return false;
    }

    $conn = $userspace->getConnection();
    
    
    // get lib and obj
    $usInfo = $userspace->getObjInfo();
    // format the user space name and library into 20 char format                                        
    $usObj = new UserSpace();
    $usObj->setUSName($usInfo['obj'], $usInfo['lib']);    

    $labelForSizeOfInputData = 'dssize';
    $dsVarName = 'datavalue';
    
    // wrap data var in a ds so we can set size and get the result easily
    // and also to wrap "loose" params passed in
    $oldDescription = $userspace->getOriginalDescription();
    $wrappedOldToolkitParams = array(array('DSName'=>$dsVarName, 'DSParm'=>$oldDescription));

    // wrap the data, too, so it'll match up
    $data = array($dsVarName => $params);
    
    // convert from old to new param format, inserting input values
    $newInputParams = $userspace->generateNewToolkitParams($data, $wrappedOldToolkitParams);

    if (!$newInputParams) {
        // some problem converting
        return false;
    }

    // only want one param, the user space data description
    $sendStructure = $newInputParams[0];
    

       $toolkitParams = array();
    $toolkitParams[] =  ToolkitService::AddParameterChar ('in', 20,"User space name and lib",'usfullname', $usObj->getUSFullName() );    
    $toolkitParams[] =  ToolkitService::AddParameterInt32('in', "Starting position",'pos_from', 1);
    $toolkitParams[] =  ToolkitService::AddParameterSize("Length of data",'dataLen', $labelForSizeOfInputData);
    // update "label for size of structure" in structure, so XMLSERVICE can get its size
    $sendStructure->setParamLabelLen($labelForSizeOfInputData);
    $toolkitParams[] = $sendStructure;
    
    $toolkitParams[] =  ToolkitService::AddParameterChar('in', 1, "Force changes to auxiliary storage",'aux_storage' ,'0');
    $toolkitParams[] =  ToolkitService::AddErrorDataStructZeroBytes();
    
    // write to the user space
    $retPgmArr = $conn->PgmCall('QUSCHGUS', 'QSYS', $toolkitParams);
    
    // check for any errors
    if ($conn->getErrorCode()) {
        i5CpfError($conn->getErrorCode(), $conn->getErrorMsg());
        return false;
    } else {
        noError();
        return true;
    }        
} //(i5_userspace_put)
    
    /**
     * resource i5_userspace_get(resource user space, array params)
     * Retrieve user space data.
     *
     * @param $userspace User Space resource opened by i5_userspace_prepare
     * @param $params output params with php variable names.
     * @param int $offset Offset from the beginning of the user space, of the data to get. Not documented in Zend documentation.
     * @return bool
     */
function i5_userspace_get($userspace, $params, $offset = 1)
{
    // check parameters
	if (!isset($userspace)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Missing user space description', 'Missing user space description');
        return false;
    }
    if (!is_object($userspace)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'User space description must be a description resource', 'User space description must be a description resource');
        return false;
    }
    if (!isset($params)) {
        i5ErrorActivity(I5_ERR_PHP_ELEMENT_MISSING, I5_CAT_PHP, 'Params are required', 'Params are required');
        return false;
    }
    if (!is_array($params)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Params must be an array', 'Params must be an array');
        return false;
    }

    $outputParams = $params;
    
    $conn = $userspace->getConnection();  
    
    // get lib and obj
    $usInfo = $userspace->getObjInfo();
    // format the user space name and library into 20 char format                                        
    $usObj = new UserSpace();
    $usObj->setUSName($usInfo['obj'], $usInfo['lib']);    
    
    // TODO be OK when no values are passed.
    
    // convert from old to new param format, inserting input values, when later call generateNewToolkitParams.
    // It won't affect the "new toolkit" style params that we specify directly.
    $userspace->setIsReceiverOnly(true); // receiver only, so it will use default/blank input values.

    $labelForSizeOfInputData = 'dssize';
    $receiverVarName = 'receiverdata';
    
    $oldDescription = $userspace->getOriginalDescription();
    
    // wrap data var in a ds so we can set size and get the result easily
    // and also to wrap "loose" params passed in
    $wrappedOldToolkitParams = array(array('DSName'=>$receiverVarName, 'DSParm'=>$oldDescription));
    // use null because we're creating a receiver with no data yet.
    $newInputParams = $userspace->generateNewToolkitParams(null, $wrappedOldToolkitParams);
    
    if (!$newInputParams) {
        // some problem converting
        return false;
    }

    // only want one param, the data queue description
    $receiveStructure = $newInputParams[0];
        
    $toolkitParams = array();
    $toolkitParams[] = ToolkitService::AddParameterChar( 'in', 20,  "User space name and library", 'userspacename', $usObj->getUSFullName());    
    $toolkitParams[] = ToolkitService::AddParameterInt32('in',  "From position", 'position_from', $offset);
    $toolkitParams[] = ToolkitService::AddParameterSize("Length of data",'dataLen', $labelForSizeOfInputData);
    // update "label for size of structure" in structure, so XMLSERVICE can get its size
    $receiveStructure->setParamLabelLen($labelForSizeOfInputData);
    $toolkitParams[] = $receiveStructure;//$receiveDs[] =
    $toolkitParams[] = ToolkitService::AddErrorDataStructZeroBytes();
    // read from the user space
    $retPgmArr = $conn->PgmCall('QUSRTVUS', 'QSYS', $toolkitParams);    

    // check for any errors
    //if( $conn->verify_CPFError($retPgmArr , "User space get failed.")) {
    if ($conn->getErrorCode()) {
        i5CpfError($conn->getErrorCode(), $conn->getErrorMsg());
        return false;
    } else {
        // extricate the data from the receiver variable ds wrapper
        $outputArray = $retPgmArr['io_param'][$receiverVarName];  

        // export vars then return true
        if ($outputArray) {
               //$exportedThem = exportPgmOutputVars($outputParams, $outputArray);
               $exportedThem = $userspace->getConnection()->setOutputVarsToExport($outputParams, $outputArray);
            if (!$exportedThem) {
                return false;
            }
            
        } //(retvals present)

        noError();
        return true;
        
    } //(if no errors)
    
} //(i5_userspace_get)
    
    /**
     * bool i5_userspace_create(properties[, resource connection]).
     * Description: Creates a new user space object.
     * Return Values: Boolean success value
     * Arguments:
     * properties -
     * I5_INITSIZE - The initial size of the user space being created. This value must be from 1 byte to 16, 776, 704 bytes.
     * I5_DESCRIPTION - user space description
     * I5_INIT_VALUE - The initial value of all bytes in the user space.
     * I5_EXTEND_ATTRIBUT - extended attribute. The extended attribute must be a valid *NAME. For example, an object type of *FILE has an extended attribute of PF (physical file), LF (logical file), DSPF (display file), SAVF (save file), and so on.
     * I5_AUTHORITY - The authority you give users who do not have specific private or group authority to the user space
     * I5_LIBNAME - Library name where the user space is located
     * I5_NAME - User space name (10 char max)
     * connection - Result of i5_connect
     *
     * example:
     * $property = array(
     * I5_INITSIZE=>10,
     * I5_DESCRIPTION=>"Created by PHP",
     * I5_INIT_VALUE=>"A",
     * I5_EXTEND_ATTRIBUT=>"File",
     * I5_AUTHORITY=>"*ALL",
     * I5_LIBNAME=>"PHPDEMO",
     * I5_NAME=>"USERSPACE"
     * );
     *
     * @param array $properties
     * @param null $connection
     * @return bool
     */
function i5_userspace_create($properties = array(), $connection = null)
{
    if (!$connection = verifyConnection($connection)) {
        return false;
    }    

    // check incoming $properties array
    if (!$properties || !is_array($properties)) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Properties must be an array', 'Properties must be an array');
        return false;
        
    }
    
    // name and library are the only required properties
    if(!isset($properties[I5_LIBNAME]) || !$properties[I5_NAME]) {
        i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'Library and name are required', 'Library and name are required');
        return false;
    }

    // TODO check initsize numeric, init_value single char, nonblank for other supplied properties
    
    // get values in fields to pass to toolkit
    $possibleProperties = array(I5_INITSIZE, I5_DESCRIPTION, I5_INIT_VALUE, I5_EXTEND_ATTRIBUT, I5_AUTHORITY, I5_LIBNAME, I5_NAME);
    
    $options = array();
    foreach ($possibleProperties as $possProp) {
        // assign either the propery or '' to each property in array
        $options[$possProp] = (isset($properties[$possProp])) ? $properties[$possProp] : '';
    }
    
    // use Zend API toolkit create user space method
    $usObj = new UserSpace($connection);
    $success = $usObj->CreateUserSpace($options[I5_NAME], $options[I5_LIBNAME], $options[I5_INITSIZE], $options[I5_AUTHORITY],
                                       $options[I5_INIT_VALUE], $options[I5_EXTEND_ATTRIBUT], $options[I5_DESCRIPTION]);    

    if (!$success) {
        i5CpfError($connection->getErrorCode(), $connection->getErrorMsg());
        return false;
    } else {        
        noError();                                       
        return true;
    } //(if !$success))
} //(i5_userspace_create)
    
    /**
     * note: i5_userspace_delete does not exist in old toolkit
     *
     * @return bool
     */
function i5_open() {
    i5ErrorActivity(TO_BE_IMPLEMENTED, 0, "Record level access has not been implemented.", "Record level access has not been implemented.");
	return false;
} //(i5_open())
    
    /**
     * i5_output() returns variables from i5_program_call, i5_userspace_get, and sometimes i5_command.
     *
     * @return bool
     */
function i5_output() {

	// get connection
	if (!$connection = verifyConnection()) {
        return false;
    }    
	
    // return array of variables. Calling routine can do extract() with it.
	return $connection->getOutputVarsToExport();
	
} //(i5_output())
    
    /**
     * Return version number of CW and PHP toolkit front-end.
     * 
     * @return string
     */
function i5_version() {
    return ToolkitService::getFrontEndVersion();
}
