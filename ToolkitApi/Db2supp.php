<?php 
class db2supp {

private $last_error = '';
// added
private $last_sqlstate = '';

// 'persistent' is one option
public function connect($database, $user, $password, $options = null){

	/*
	 * Throw in your "transport/adapter" framework for a real OO look and feel ....
Throw new Exception( "Fail execute ($sql) ".db2_stmt_errormsg(),db2_stmt_error());
... and retrieve via try/catch + Exception methods.
	 */

    $connectFunc = 'db2_connect'; // default
	if ($options) {
		if ((isset($options['persistent'])) && $options['persistent']) {
			$connectFunc = 'db2_pconnect';
		} //(persistent)
	} //($options)
	
	// could be connect or pconnect
	
  $conn = $connectFunc ( $database, $user, $password );

  if(is_resource($conn))
  { 
	  return $conn;
  }
  
	$this->last_error = db2_conn_errormsg();
	// added
	$this->last_sqlstate = db2_conn_error();
	  
   return false;
}

public function disconnect( $conn ){

	if(is_resource($conn))
		db2_close($conn);

}

// disconnect, truly close, a persistent connection.
public function disconnectPersistent( $conn ){

	if(is_resource($conn))
		db2_pclose($conn);

}


public function retrieveError(){

	return $this->last_error;
}

// added
public function retrieveSqlState(){

	return $this->last_sqlstate;
}

/* this function used for special stored procedure call only  */
public function execXMLStoredProcedure( $conn, $stmt, $bindArray )										
{

	$InternalKey= $bindArray['InternalKey'];
	$ControlKey = $bindArray['ControlKey'];
	$InputXML   = $bindArray['InputXML'];
	$OutputXML  = $bindArray['OutputXML'];
	
	// TODO error doesn't properly bubble up to top level.
	// But added some error handling in ToolkitService.php, ExecuteProgram, looking at error code.
	$crsr = @db2_prepare ( $conn, $stmt);
	
	if( !$crsr ){
		$this->last_error = db2_stmt_error (); // changed from conn_error
		return false;
	}

	$ret = db2_bind_param ( $crsr, 1, "InternalKey", DB2_PARAM_IN );					
	if (!$ret){	    	
			$this->last_error =  db2_stmt_errormsg ($crsr);	
			return false;
	    }
	    
		$ret = db2_bind_param ( $crsr, 2, "ControlKey", DB2_PARAM_IN );
		if (!$ret){		
			$this->last_error =  db2_stmt_errormsg ($crsr);
			return false;
		}
		
		$ret = db2_bind_param ( $crsr, 3, "InputXML", DB2_PARAM_IN );		
		if (!$ret){			
			$this->last_error =  db2_stmt_errormsg ($crsr);			
			return false;
		}
		
		$ret = db2_bind_param ( $crsr, 4, "OutputXML", DB2_PARAM_OUT );		
		if(!$ret){			
		    $this->last_error =  db2_stmt_errormsg ($crsr);				
			return false;
		}
		
        $ret = @db2_execute ( $crsr ); // @ so we don't get warning. Deal with !$ret on next line.

        if(!$ret){
			$this->last_error = db2_stmt_error($crsr); // 22003 means XML is too big to get
			return false;
		}
		
		return $OutputXML;
}
/*returns a first column from sql stmt result set*/
public function executeQuery($conn, $stmt ){

     $Txt ='';
	 $crsr = db2_exec($conn, $stmt, array('cursor' => DB2_SCROLLABLE));	  
	 if(is_resource($crsr )) {	  	
	 	while (true)
	  	{  		  		
	  		$row = db2_fetch_row( $crsr );	  		
	  		if(!$row) 
	  		   break;  		   	
	  	
	  		$column = db2_result($crsr, 0);
	  		$Txt[] = $column;
	  		 
	  	}
	  }
	  else 
	 {
	  	 //$err = db2_stmt_error();
	  	 die('db2 error: ' . db2_stmt_error() . ' ' . db2_stmt_errormsg()); 
	     $this->last_error = db2_stmt_errormsg();
	  }
	  	
	  return $Txt;
	}

}
?>