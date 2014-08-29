<?php
namespace ToolkitApi;

class db2supp {

// TODO define common transport class/interface extended/implemented by all transports 	
// They have a lot in common.	
	
private $last_errorcode = ''; // SQL State
private $last_errormsg = ''; // SQL Code with message

// 'persistent' is one option
public function connect($database, $user, $password, $options = null){

	/*
	 * TODO Throw in your "transport/adapter" framework for a real OO look and feel ....
Throw new Exception( "Fail execute ($sql) ".db2_stmt_errormsg(),db2_stmt_error());
... and retrieve via try/catch + Exception methods.
	 */
	
	// check for blank password with non-blank user. If so, throw the same error that wrong password would generate.
	// Compensate for older ibm_db2 driver that may not do this check.
	if ($user && empty($password)) {
		
		$this->setErrorCode('08001');
		$this->setErrorMsg('Authorization failure on distributed database connection attempt. SQLCODE=-30082');
		
		return false;
		
	} //(if (empty($password)))
	
	
    $connectFunc = 'db2_connect'; // default
	if ($options) {
		if ((isset($options['persistent'])) && $options['persistent']) {
			$connectFunc = 'db2_pconnect';
		} //(persistent)
	} //($options)
	
	// could be connect or pconnect
    $conn = $connectFunc ( $database, $user, $password );

    if(is_resource($conn)) {
	    return $conn;
    }
  
    // error  
    $this->setErrorCode(db2_conn_error());
    $this->setErrorMsg(db2_conn_errormsg());
	  
    return false;
   
} //(public function connect($database, $user, $password, $options = null))

public function disconnect( $conn ){

	if(is_resource($conn))
		db2_close($conn);

}

// disconnect, truly close, a persistent connection.
public function disconnectPersistent( $conn ){

	if(is_resource($conn))
		db2_pclose($conn);

}


public function getErrorCode(){

	return $this->last_errorcode;
}

// added
public function getErrorMsg(){

	return $this->last_errormsg;
}

protected function setStmtError($stmt = null) {
	// set error code and message based on last db2 prepare or execute error.
	
	// TODO: consider using GET DIAGNOSTICS for even more message text:
	// http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Frzala%2Frzalafinder.htm
	if ($stmt) {
		// specific statement resource was provided
	    $this->setErrorCode(db2_stmt_error($stmt));
	    $this->setErrorMsg(db2_stmt_errormsg($stmt));
	} else {
		// no specific statemtent. Get last error
		$this->setErrorCode(db2_stmt_error());
		$this->setErrorMsg(db2_stmt_errormsg());	
	} //(if ($stmt))
	
} //(setStmtError($stmt = null))

protected function setErrorCode($errorCode) {
	$this->last_errorcode = $errorCode;
}


protected function setErrorMsg($errorMsg) {
	$this->last_errormsg = $errorMsg;
}


/* this function used for special stored procedure call only  */
public function execXMLStoredProcedure( $conn, $sql, $bindArray )										
{
	
	$internalKey= $bindArray['internalKey'];
	$controlKey = $bindArray['controlKey'];
	$inputXml   = $bindArray['inputXml'];
	$outputXml  = $bindArray['outputXml'];
	
	// TODO error doesn't properly bubble up to top level.
	// But added some error handling in ToolkitService.php, ExecuteProgram, looking at error code.
	$crsr = @db2_prepare ( $conn, $sql);
	
	// if the prepare failed
	if( !$crsr ) {
		$this->setStmtError();
		return false;
	} //(if( !$crsr ))

	// stored procedure takes four parameters. Each 'name' will be bound to a real PHP variable
	$params = array(
			       array('position' => 1, 'name' => "internalKey", 'inout' => DB2_PARAM_IN),
			       array('position' => 2, 'name' => "controlKey",  'inout' => DB2_PARAM_IN),
			       array('position' => 3, 'name' => "inputXml",    'inout' => DB2_PARAM_IN),
			       array('position' => 4, 'name' => "outputXml",   'inout' => DB2_PARAM_OUT),
			       );
	
	// bind the four parameters
	foreach ($params as $param) {
		
		$ret = db2_bind_param ( $crsr, $param['position'], $param['name'], $param['inout'] );
		if (!$ret) {
			// unable to bind a param. Set error and exit	
			$this->setStmtError($crsr);
			return false;
		} //(if (!$ret))
			
	} //(foreach ($params...))
	

	// execute the stored procedure.
    // @ hides any warnings. Deal with !$ret on next line.
    $ret = @db2_execute ( $crsr ); 

    if(!$ret) {
    	// execution of XMLSERVICE stored procedure failed.
    	$this->setStmtError($crsr); // set error and exit
		return false;
	} //(if(!$ret) {)
	
	return $outputXml;
		
} //(public function execXMLStoredProcedure)
/*returns a first column from sql stmt result set*/
// used in one place: iToolkitService's ReadSPLFData().
// TODO eliminate this method if possible.
public function executeQuery($conn, $sql )
{

     $Txt ='';
	 $stmt = db2_exec($conn, $sql, array('cursor' => DB2_SCROLLABLE));	  
	 if(is_resource($stmt )) {	  	
	 	  while (true) {  		  		
	  		$row = db2_fetch_row( $stmt );	  		
	  		if(!$row) 
	  		   break;  		   	
	  	
	  		$column = db2_result($stmt, 0);
	  		$Txt[] = $column;
	  		 
	  	  } // while
	 } else {
	  	 //$err = db2_stmt_error();
	     $this->setStmtError();
	     Throw new \Exception( "Failure executing SQL: ($sql) ".db2_stmt_errormsg(), db2_stmt_error()); 
	 } //(if is resource)
	  	
	  return $Txt;
} //(public function executeQuery)

} //(end of class)