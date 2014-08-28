<?php

class odbcsupp {

private $last_errorcode = ''; // SQL State
private $last_errormsg = ''; // SQL Code with message
	
// 'persistent' is one option
public function connect($database, $user, $password, $options = null){
	
	$connectFunc = 'odbc_connect'; // default
	if ($options) {
		if ((isset($options['persistent'])) && $options['persistent']) {
			$connectFunc = 'odbc_pconnect';
		} //(persistent)
	} //($options)
	
  // could be connect or pconnect
  $conn = $connectFunc ( $database, $user, $password );
  
  if (is_resource($conn)) {	
	  return $conn;
	  
  } else {

  	$this->setError();
    return false;
  }  //(if is resource)
  
} //(function connect)

public function disconnect( $conn ){

	if(is_resource($conn))
		odbc_close($conn);

}


public function getErrorCode(){

	return $this->last_errorcode;
}

// added
public function getErrorMsg(){

	return $this->last_errormsg;
}

protected function setError($conn = null) {
	// set error code and message based on last odbc connection/prepare/execute error.

	// TODO: consider using GET DIAGNOSTICS for even more message text:
	// http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Frzala%2Frzalafinder.htm

	if ($conn) {
		// specific connection resource was provided
		$this->setErrorCode(odbc_error($conn));
		$this->setErrorMsg(odbc_errormsg($conn));
	} else {
		// no specific statemtent. Get last error
		$this->setErrorCode(odbc_error());
		$this->setErrorMsg(odbc_errormsg());
	} //(if ($stmt))

} //(setStmtError($stmt = null))

protected function setErrorCode($errorCode) {
	$this->last_errorcode = $errorCode;
}


protected function setErrorMsg($errorMsg) {
	$this->last_errormsg = $errorMsg;
}


/* this function used for special stored procedure call only  */
public function execXMLStoredProcedure( $conn, $stmt, $bindArray )										
{


	$internalKey= $bindArray['internalKey'];
	$controlKey = $bindArray['controlKey'];
	$inputXml   = $bindArray['inputXml'];
	$outputXml  = $bindArray['outputXml'];
	$disconnect = $bindArray['disconnect'];

	$crsr = odbc_prepare ( $conn, $stmt);
	if( !$crsr ){ 				
		$this->setError($conn);
		return false;
	}
	
	/* extension problem: sends an warning message into the php_log or to stdout 
	 * about of number of result sets .  ( switch on return code of SQLExecute() 
	 * SQL_SUCCESS_WITH_INFO  */
		$ret = @odbc_execute ( $crsr , array($internalKey, $controlKey, $inputXml ));
		if(!$ret){
			$this->setError($conn);
				
			return "ODBC error code: " . $this->getErrorCode() . ' msg: ' . $this->getErrorMsg();
		}
	
	//disconnect operation cause crush in fetch ,
	//nothing appears as sql script.
	 $row='';
	 $outputXML = '';
	 if(!$disconnect){			
        while( odbc_fetch_row($crsr)) {
    		$tmp = odbc_result($crsr, 1);
	    		if($tmp){
	    		/*because of some problem in odbc Blob transfering 
	    		 * shoudl be executed some "clean" in returned data */
	    		if(strstr($tmp , "</script>")){
	    			$stopFetch = true;
	    			$pos = strpos($tmp, "</script>");
					$pos += strlen("</script>");
					$row .= substr($tmp,0,$pos);
					break;
	    		}
	    		else
	    			$row .=$tmp;
    		}
       }//while
       		
		$outputXML = $row;		
	}//!$disconnect
	return $outputXML;
}


public function executeQuery($conn,  $stmt ){

  	 $crsr = odbc_exec($conn, $stmt);	  
	 if(is_resource($crsr )) {	  		  	  		
	  		while( odbc_fetch_row( $crsr )){	  	
		  		$row = odbc_result($crsr, 1);	
		  		if(!$row) 
		  		   break;  		   	
		  
		  		$Txt[]=  $row;
	  		} //(while) 	  	
	  } else {
	     $this->setError($conn);
	  }	//(is resource)  	
	  
	  return $Txt;
	  
  } //(function execute)
} //(end of class)
