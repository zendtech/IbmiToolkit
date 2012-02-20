<?php
class ProgramParameter{
	
	private    $type;     /*storage */ 
	private    $io;       /*in/out/both*/ 
	private    $comment;  /*comment*/
    private    $varName;  /*variable name*/
	private    $data;      /*value */
	private    $varing;    /*varing on/varing off */
	private    $dimension;
	protected  $by;        /* val or ref */
	protected  $isArray;   /* treat as an array of similarly defined data. true or false */
	protected  $labelSetLen;   /* use on an integer field to set length there based on labelLen (see below) */
	protected  $labelLen;  /* use this on a data structure to get the size/length */


// TODO do setlen for other program param types, too

/*
 * // today we array of input elements as a ds structure ...
$ds[]=$ToolkitServiceObj->AddParameterChar('both',10,'comment1','d1',"t1");
$ds[]=$ToolkitServiceObj->AddParameterChar('both',10,'comment2','d2',"t2");
$ds[]=$ToolkitServiceObj->AddParameterChar('both',10,'comment3','d3',"t3");
$ds[]=$ToolkitServiceObj->AddParameterChar('both',10,'comment4','d4',"t4");
$ds[]=$ToolkitServiceObj->AddParameterChar('both',10,'comment5','d5',"t5");
$param[] = $ToolkitServiceObj->AddDataStruct($ds,"P1");

// wish we had $value=array(1,2,3,4,5,...)
$param[]=$ToolkitServiceObj->AddParameterChar('both',10,'comment1','P1',array("t1", "t2", "t3", "t4", "t5"));

 *
ds of ds?

1. get value array for ds.
2. <= dim for ds? if not, error.
3. loop through values. 

$ds[]=new DataStructure($parameters1, "{$dsName}_1");
$ds[]=new DataStructure($parameters2, "{$dsName}_2");
$ds[]=new DataStructure($parameters3, "{$dsName}_3");
$ds[]=new DataStructure($parameters4, "{$dsName}_4");
$ds[]=new DataStructure($parameters5, "{$dsName}_5");
$param[] = new DataStructure($ds,$dsName);

// wish we had $value=array(1,2,3,4,5,...)
$param[]=$ToolkitServiceObj->AddParameterChar('both',10,'comment1','P1',array("t1", "t2", "t3", "t4", "t5"));

 *
 *
 *
 *
 */
	
	// added byval, isarray, labellen, labelsetlen  
	function __construct( $type,  $io, $comment='', $varName = '', $value, $varing = 'off', $dimension = 0, $by = 'ref', $isArray = false, $labelSetLen = null, $labelLen = null)
	{

		// some properties are different if value is an array (implement via a data structure).
		$this->type            = (is_array($value)) ? 'ds' : $type;
		// if array, say both, otherwise regular $io value
		$this->io              = (is_array($value)) ? 'both' : $io; 
		$this->comment         = $comment;
		$this->varName         = $varName;
		$this->data            = $this->handleParamValue($type, $io, $comment, $varName, $value, $varing, $dimension, $by, $isArray); // handles array with original info
		$this->varing          = (is_array($value)) ? 'off' : $varing;
		$this->dimension       = $dimension;
		$this->by              = $by;
		$this->returnParameter = false;
		$this->isArray         = $isArray; 
		$this->labelSetLen     = $labelSetLen; 
        $this->labelLen        = $labelLen;
			
	} //(constructor)
	
	public function getParamProperities()
	{
		return array('type' => $this->type,        
					  'io' => $this->io,            
					  'comment' => $this->comment,  
		              'var' =>  $this->varName,     
					  'data' => $this->data,       
					  'varying' => $this->varing,  
					  'dim' =>  $this->dimension,
		              'by' =>    $this->by,
		              'array' => $this->isArray,
				      'setlen' => $this->labelSetLen,
				      'len'    => $this->labelLen,
		);
		
	} //(getParamProperities)
	
	// spell it right
	public function getParamProperties() {
		return $this->getParamProperities();
	}
	
	protected function handleParamValue($type, $io, $comment, $varName, $value, $varing, $dimension, $by, $isArray) 
	{	
		// TODO remove logging
//		$valLog = (is_array($value)) ? print_r($value, true) : $value;
//	    /error_log('setParamValue name: ' . $this->varName . ' value: ' . $valLog . "\n", 3, '/www/zendsvr/htdocs/phptoolkit/paramvallog.log');	
    		
		// added....
		// if $value is an array, but not yet a data structure, make a data structure of the array elements.
		/// same for $dim
	    if (is_array($value)  && ($type != 'ds')) {
			$count = count($value);
			
			if ($count) {
				$ds = array();
				// make array of parms of the specified type
				foreach ($value as $key=>$singleValue) {
					// use $key as a sequential (probably) unique-ifier, though not strictly necessary
					$ds[] = new self($type, $io, "{$comment}_$key", "{$varName}_$key", $singleValue, $varing, $dimension, $by, $isArray);
				}

                // use the new ds for our value below.
                $value = $ds;
                
			} else {
				throw new Exception("Empty array passed as value for {$this->varName}");
			} //(if count)
			
		} //(is array)

		
		return $value;
		
	} //(setParamValue)

	public function setParamValue($value)
	{
		$this->data = $value;
    }

    // set "len label"
    public function setParamLabelLen($labelLen)
	{
		$this->labelLen = $labelLen;
    }
    
	public function setParamName($name)
	{
		$this->varName = $name;
	}
	
    
	public function getParamName()
	{
		return $this->varName;
	}
	
	public function getParamValue(){
		return $this->data;
	}
	
			
	public function getParamDimension()
	{
		return $this->dimension;
	}
		
	public function isDS(){
		if($this->type == "ds")
			return true;			
		return false;
	}
	
	public function setReturnParameter()
	{
		$this->returnParameter = true;
	}
	
	public function  isReturn()
	{
		return $this->returnParameter;
	}	
	
	// updates $arrParams, so pass it by reference.
	// $arrParms is an array of parameter arrays or objects.
	static function UpdateParameterValues(&$arrParams, array $arrValues){
		
        //echo "<BR>params: <PRE>" . print_r($arrParams, true) . "</PRE><BR> values: <PRE>" .  print_r($arrValues, true) . "</PRE>";   		
		
		// added the params part
		if(!is_array($arrValues) || !is_array($arrParams)) {
			return false;
	    }

	    
	    foreach($arrValues as $varName =>$newData){
	    	
			foreach($arrParams as $single)
			{
				
				
				
/*				if (!is_object($single)) {
					// if not object, could be array for final 
					var_dump($single);
					die;
				}
*/				
				if( is_object($single) && $single->isDS()){
					$arr = $single->getParamValue();
					self::UpdateParameterValues( $arr, array ($varName =>$newData));
				} else {
					// regular param, not a ds. could be an array of values 
					$paramName =$single->getParamName();
					if($paramName === $varName ){
						$single->setParamValue($this->handleParamValue($newData));
						break;
					}
		    	}			
		    }	
	    }		
	}
	
	static function ParametersToArray( $arrParams = null ){
	  if(!is_array($arrParams ) && !( $arrParams instanceof ProgramParameter))
			return null;
			
		$params = null;
		
		if( $arrParams  instanceof ProgramParameter ){
			if( $arrParams->isDS()){
				$arr = $arrParams->getParamValue();
				if($arrParams->isReturn()){
					/*return value definition is a single! parameter*/
					$params = array('ds'=> array('fields'=> self::ParametersToArray( $arr ),
								   	 'ds_descr'=> array('var'=> $arrParams->getParamName(),
									  					'dim'=> $arrParams-> getParamDimension())) );

					
				}
				else
					$params[] = array('ds'=>  self::ParametersToArray( $arr ));
				
			}
			else
			{ 
				$params[]=$arrParams->getParamProperities();
			}
		}
		else 
		{
			foreach($arrParams  as $single){		
				if( $single->isDS()){
					$arr = $single->getParamValue();
					$params[] = array('ds'=>  self::ParametersToArray( $arr ));				
				}	
				else	
				{
					$params[]=$single->getParamProperities();
				}
			}
		}
		return $params;
	}
	/*can not be public. Return XML does not retunrn  a type of values.*/
	static  function bin2str( $hex_data )	
	{			
		$str='';
		$upto = strlen($hex_data);
		for($i = 0; $i < $upto; $i+= 2){
		 	if($hex_data[$i] == '0')
		 	   break;
		 		 	
		 	$str.= chr(hexdec($hex_data[$i].$hex_data [$i+1]));
		}		
		return $str;
	 }	
	
}

// TODO add comment 
class DataStructure extends ProgramParameter {
		function __construct( $paramsArray , $struct_name ="DataStruct", $dim=0 , $isReturnParam = false, $by='', $isArray=false, $labelLen = null ){
			parent::__construct( "ds",  "both", $struct_name, $struct_name, $paramsArray , 'off', $dim, $by, $isArray, null, $labelLen );
			
//        error_log('DataStructure name: ' . $struct_name . ' value: ' . print_r($paramsArray, true) . "\n", 3, '/www/zendsvr/htdocs/phptoolkit/paramvallog.log');	
			
			if( $isReturnParam) 
				$this->setReturnParameter();
		}
}




class CharParam extends ProgramParameter{
	// todo if array. call charparm 5 times with fake field name
	// and coming out, too.
	function __construct($io, $size , $comment,  $varName = '', $value , $varying = 'off',$dimension = 0, $by='', $isArray = false)
	{
		$type = sprintf("%dA", $size);
		parent::__construct( $type,  $io, $comment, $varName, $value, $varying, $dimension, $by, $isArray );
		return $this;
	}
}

class ZonedParam extends ProgramParameter{
	function __construct($io, $length ,$scale , $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false)
	{
		$type = sprintf("%ds%d", $length, $scale);
		parent::__construct( $type,  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray );
		return $this;
	}
}

class PackedDecParam extends ProgramParameter {
	function __construct($io, $length ,$scale , $comment,  $varName = '', $value,$dimension=0, $by='', $isArray = false,  $labelSetLen = null) {    		    	
    	$type = sprintf("%dp%d", $length, $scale);
		parent::__construct( $type,  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, $labelSetLen, null   );
		return $this;
	}
}

class Int32Param extends ProgramParameter{
	 function __construct($io,  $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false, $labelSetLen = null) {       
		parent::__construct(  '10i0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, $labelSetLen, null  );
		return $this;
	 }
}

class SizeParam extends Int32Param{
	 function __construct($comment,  $varName = '', $labelSetLen) {       
		parent::__construct( 'in', $comment, $varName, 0, 0, '', false, $labelSetLen );
		return $this;
	 }
}

// size can be a pack 5 decimal, too!
class SizePackParam extends PackedDecParam{
	 function __construct($comment,  $varName = '', $labelSetLen) {       
		parent::__construct( 'in', 5, 0, $comment, $varName, 0, 0, '', false, $labelSetLen );
		return $this;
	 }
}


class Int64Param extends ProgramParameter{
	function __construct( $io,  $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false){
		parent::__construct('20i0',  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray );
		return $this;
	}
}
class UInt32Param extends ProgramParameter{
	 function __construct($io,  $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false) {       
		parent::__construct(  '10u0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray );
		return $this;
	 }
}
class UInt64Param extends ProgramParameter{
	function __construct( $io,  $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false){
		parent::__construct('20u0',  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray );
		return $this;
	}
}
class FloatParam extends ProgramParameter{
	function __construct( $io,  $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false){
		parent::__construct('4f', $io, $comment, $varName, $value, 'off',$dimension, $by, $isArray );
		return $this;
	}
}
class RealParam extends ProgramParameter{
	function __construct( $io,  $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false){
		parent::__construct('8f',  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray );
		return $this;
	}
}

// "hole" means, don't return the data where the hole is defined. A way to ignore large amounts of data
class HoleParam extends ProgramParameter{
	function __construct( $length, $comment = 'hole'){
		$type = sprintf("%dh", $length);
		// note, no varname or value needed because data will be ignored.
		parent::__construct($type, 'in', $comment, '', '', 'off', 0, '', '' );
		return $this;
	}
}


/*bynary parameter*/
class BinParam extends ProgramParameter{
	
	function __construct ($io, $size , $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false) {    		    	
    	$type = sprintf("%dB", $size);
		parent::__construct( $type,  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray );
	return $this;
	}
	
	static function  bin2str( $hex_data ){
		return parent::bin2str($hex_data);
	}	
}

?>