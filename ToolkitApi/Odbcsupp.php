<?php 
class odbcsupp {

private $last_error = '';

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
  if(is_resource($conn))	
	  return $conn;

	$this->last_error = odbc_errormsg();

   return false;
}

public function disconnect( $conn ){

	if(is_resource($conn))
		odbc_close($conn);

}

public function retrieveError(){

	return $this->last_error;
}
/* this function used for special stored procedure call only  */
public function execXMLStoredProcedure( $conn, $stmt, $bindArray )										
{


	$InternalKey= $bindArray['InternalKey'];
	$ControlKey = $bindArray['ControlKey'];
	$InputXML   = $bindArray['InputXML'];
	$OutputXML  = $bindArray['OutputXML'];
	$disconnect = $bindArray['disconnect'];

	$crsr = odbc_prepare ( $conn, $stmt);
	if( !$crsr ){ 				
		$this->last_error = odbc_errormsg (odbc_error );
		return false;
	}
	
	/* extension problem: sends an warning message into the php_log or to stdout 
	 * about of number of result sets .  ( switch on return code of SQLExecute() 
	 * SQL_SUCCESS_WITH_INFO  */
		$ret = @odbc_execute ( $crsr , array($InternalKey, $ControlKey, $InputXML ));
		if(!$ret){
			$this->last_error = odbc_errormsg  ($conn );
			echo  $this->last_error;	
			return false;
		}
	
	//disconnect operation cause crush in fetch ,
	//nothing appears as sql script.
	 $row='';
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
       		
		$OutputXML = $row;		
	}//!$disconnect
	return $OutputXML;
}


public function executeQuery($conn,  $stmt ){

  	 $crsr = odbc_exec($conn, $stmt);	  
	 if(is_resource($crsr )) {	  		  	  		
	  		while( odbc_fetch_row( $crsr )){	  	
		  		$row = odbc_result($crsr, 1);	
		  		if(!$row) 
		  		   break;  		   	
		  
		  		$Txt[]=  $row;
	  		} 	  	
	  }
	  else 
	  {
	  	 //$err =odbc_error ();
	     $this->last_error = odbc_errormsg ();
	  }	  	
	  return $Txt;
  }
}
?>