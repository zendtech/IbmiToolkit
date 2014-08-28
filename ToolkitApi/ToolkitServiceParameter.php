<?php

class ProgramParameter
{

	protected  $type;     /*storage */
	protected  $io;       /*in/out/both*/
	protected  $comment;  /*comment*/
    protected  $varName;  /*variable name*/
	protected  $data;      /*value */
	protected  $varying;    /*varying on/varying off */
	protected  $dimension;
	protected  $by;        /* val or ref */
	protected  $isArray;   /* treat as an array of similarly defined data. true or false */
	protected  $labelSetLen;   /* use on an integer field to set length there based on labelLen (see below) */
	protected  $labelLen;  /* use this on a data structure to get the size/length */
	protected  $labelDoUntil = '';   /* use on a data structure array along with 'dim' to to set # of records to return based on labelEndDo (see below) */
	protected  $labelEndDo = '';  /* use this on an integer "count" field to control the number of records to return in n array data structure (see labelDoUntil above) */
	
    // CCSID/hex support
    protected $_ccsidBefore;
    protected $_ccsidAfter;
    protected $_useHex;
	
	// if data field is not named, the toolkit creates a name of the pattern var0, var1, var2...
	static protected $_fallbackNameSequence = 0; // start with zero to give unnamed elements a unique name
	
	
// TODO do setlen for other program param types, too


	// added byval, isarray, labellen, labelsetlen
	function __construct( $type,  $io, $comment='', $varName = '', $value, $varying = 'off', $dimension = 0, $by = 'ref', $isArray = false, $labelSetLen = null, $labelLen = null,
	                      $ccsidBefore = '', $ccsidAfter = '', $useHex = false)
	{

		// some properties are different if value is an array (implement via a data structure).
		$this->type            = (is_array($value)) ? 'ds' : $type;
		// if array, say both, otherwise regular $io value
		$this->io              = (is_array($value)) ? 'both' : $io;
		$this->comment         = $comment;
		$this->varName         = $varName;
		$this->data            = self::handleParamValue($type, $io, $comment, $varName, $value, $varying, $dimension, $by, $isArray,
				                                        $labelSetLen, $labelLen, $ccsidBefore, $ccsidAfter, $useHex); // handles array with original info
		$this->varying          = (is_array($value)) ? 'off' : $varying;
		$this->dimension       = $dimension;
		$this->by              = $by;
		$this->returnParameter = false;
		$this->isArray         = $isArray;
		$this->labelSetLen     = $labelSetLen;
        $this->labelLen        = $labelLen;
        $this->_ccsidBefore    = $ccsidBefore;
        $this->_ccsidAfter     = $ccsidAfter;
        $this->_useHex         = $useHex;    
        
	} //(constructor)

	public function getParamProperities()
	{
		// if varName is empty then set a fallback unique varName.
		if(!$this->varName) {
			$this->varName = $this->getFallbackVarName(); 
		}
		
		return array('type' => $this->type,
			      'io' => $this->io,
			      'comment' => $this->comment,
		          'var' =>  $this->varName,
			      'data' => $this->data,
			      'varying' => $this->varying,
			      'dim' =>  $this->dimension,
		          'by' =>    $this->by,
		          'array' => $this->isArray,
			      'setlen' => $this->labelSetLen,
			      'len'    => $this->labelLen,
			      'dou'    => $this->labelDoUntil,
			      'enddo'  => $this->labelEndDo,
				  'ccsidBefore' => $this->_ccsidBefore,
				  'ccsidAfter'  => $this->_ccsidAfter,
				  'useHex'      => $this->_useHex,
		);

	} //(getParamProperities)

	// spell it right
	public function getParamProperties() {
		return $this->getParamProperities();
	}


        // set a parameter's properties via an key=>value array structure. Choose any properties to set.
	public function setParamProperties($properties = array())
	{
	        // map the XML keywords (usually shorter than true class property names)
	        // to the class property names.

		$map = array('type'    => 'type',
		             'io'       => 'io',
			     'comment'  => 'comment',
			     'var'      => 'varName',
			     'data'     => 'data',
			     'varying'  => 'varying',
			     'dim'      => 'dimension',
			     'by'       => 'by',
			     'array'    => 'isArray',
			     'setlen'   => 'labelSetLen',
			     'len'      => 'labelLen',
			     'dou'      => 'labelDoUntil',
			     'enddo'    => 'labelEndDo',
				 'ccsidBefore' => 'ccsidBefore',
				 'ccsidAfter'  => 'ccsidAfter',
				 'useHex'      => 'useHex',
		);

                // go through all properties and set the ones that are valid,
                // using the mapping above to find the true property name.
                foreach ($properties as $key=>$value) {
                    $propName = isset($map[$key]) ? $map[$key] : '';
                    if ($propName) {
                        // a valid property name was found so set it
                        $this->$propName = $value;
                    } //(if $propName)
                } //(foreach)


	} //(setParamProperties)

    // for unnamed data elements, to provide a unique name
    // initialized by PgmCall method, so make public.		
	static function initializeFallbackVarName() {
	
	    self::$_fallbackNameSequence = 0; //static variable
	
    } //(function initializeFallbackVarName)

	protected function getFallbackVarName() {
	
	    // for unnamed data elements, provide a unique name: var0, var1, var2... 
	    // Then increment sequence for next time.
	    // This works better than in the original new toolkit, where the var(n) only was applied if 'var was not set as a variable at all.
	    // whereas often there's a 'var' attribute but it's empty.	
	    $varName =  'var' . self::$_fallbackNameSequence++;
	    return $varName;
	
    } //(function getFallbackVarName() )

 
	protected function handleParamValue($type, $io, $comment, $varName, $value, $varying, $dimension, $by, $isArray,
			                            $labelSetLen, $labelLen, $ccsidBefore, $ccsidAfter, $useHex)
	{
		
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
					$ds[] = new self($type, $io, "{$comment}_$key", "{$varName}_$key", $singleValue, $varying, $dimension, $by, $isArray,
					                 $labelSetLen, $labelLen, $ccsidBefore, $ccsidAfter, $useHex);
				}

                // use the new ds for our value below.
                $value = $ds;

			} else {
				throw new Exception("Empty array passed as value for {$varName}");
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

	public function setParamDimension($dimension = 0)
	{
		$this->dimension =  $dimension;
		return $this; // fluent interface
	}
	
	
	// for a data structure or other item in an array, set the label for "do until"
	public function setParamLabelCounted($label = '')
	{
		$this->labelDoUntil =  $label;
		return $this; // fluent interface
	}

	// for a numeric counter field that will determine how many array elements return from a program call,
	// set the label for "enddo". Links up with the Dou label given to the dim'med array element itself.
	public function setParamLabelCounter($label = '')
	{
		$this->labelEndDo =  $label;
		return $this; // fluent interface
	}

	
	// "CCSID before" means how to convert to CCSID on the way in to XMLSERVICE, when needed 
	public function setParamCcsidBefore($ccsidBefore = '') 
	{
		$this->_ccsidBefore = $ccsidBefore;
		return $this; // fluent interface
	} //(setParamCcsidBefore)


	// "CCSID after" means how to convert to CCSID on the way out from XMLSERVICE, when needed
	public function setParamCcsidAfter($ccsidAfter = '')
	{
		$this->_ccsidAfter = $ccsidAfter;
		return $this; // fluent interface
	} //(setParamCcsidAfter)
	

	// "useHex" controls whether the data will be converted to/from hex
	public function setParamUseHex($useHex = false)
	{
		$this->_useHex = $useHex;
		return $this; // fluent interface
	} //(setParamUseHex)
	
	public function setParamComment($comment = '')
	{
		$this->comment = $comment;
		return $this; // fluent interface
    } //(setParamComment)
	
    public function setParamIo($io = 'both')
    {
        	$this->io = $io;
    	    return $this; // fluent interface
    } //(setParamIo)
    
	public function isDS(){
		if($this->type == "ds") {
		    return true;
		} else {
		    return false;
		}
	} //(public function isDS())

	public function setReturnParameter()
	{
		$this->returnParameter = true;
	}

	public function isReturn()
	{
		return $this->returnParameter;
	}

	// updates $arrParams, so pass it by reference.
	// $arrParms is an array of parameter arrays or objects.
	//
	// Note: no return value. The first parameter, $arrayParams, gets updated.
	static function UpdateParameterValues(&$arrParams, array $arrValues){

		// if either argument is not an array, leave.
		if(!is_array($arrValues) || !is_array($arrParams)) {
			return false;
	    }

        // loop through all values passed in
	    foreach($arrValues as $varName =>$newData){
	    	
	    	// for each value, loop through all params at this level to see if the names match.
	    	// find a param matching value passed in.
			foreach($arrParams as $single)
			{   
                // if a data structure, get inner array and call self recursively.
				if( is_object($single) && $single->isDS()){
					$arr = $single->getParamValue();
					self::UpdateParameterValues( $arr, array ($varName =>$newData));
				} else {
					// regular param, not a ds. could be an array of values, though.
					$paramName =$single->getParamName();

					if($paramName === $varName ){

						//$single->setParamValue(self::handleParamValue($newData)); // if data is an array; not done right
						$single->setParamValue($newData);
						break;
					}
		    	}
		    }
	    }
	} //(UpdateParameterValues)

	
	/* bin2str is used by the 5250 Bridge. It converts a hex string to character string
	 * while cleaning up unexpected characters.
	 * Original comment: "can not be public. Return XML does not return a type of values."
	**/
	static function bin2str( $hex_data )
	{
		
		$str='';
		$upto = strlen($hex_data);
		for($i = 0; $i < $upto; $i+= 2){
			$hexPair = $hex_data[$i].$hex_data [$i+1];
			/* if hex value starts with 0 (00, 0D, 0A...),
			 * assume it's nondisplayable.
			 * Replace with a space (hex 20)
			 */
		 	if($hex_data[$i] == '0') {
		 		$hexPair = '20'; // space
		 	} //(if($hex_data[$i] == '0') ) 
		 	// break;
		 	$str.= chr(hexdec($hexPair));
		 	//$str.= chr(hexdec($hex_data[$i].$hex_data [$i+1]));
		}
		
		return $str;
	 } //(static function bin2str())

	 
	 // ParametersToArray is deprecated. No longer needed
	 static function ParametersToArray( $arrParams = null ){
	 	if(!is_array($arrParams ) && !( $arrParams instanceof ProgramParameter))
	 		return null;
	 
	 	$params = null;
	 
	 	if( $arrParams  instanceof ProgramParameter ){
	 		// single ProgramParameter object
	 		if( $arrParams->isDS()){
	 			$arr = $arrParams->getParamValue();
	 			if($arrParams->isReturn()){
	 				// a "return" DS (didn't work properly)
	 				$params = array('ds'       => 
	 						            array('fields'   => self::ParametersToArray( $arr ),
	 				                          'ds_descr' => $arrParams->getParamProperties()));
	 
	 			}
	 			else // non-return DS. Reduce DS to simple structure('ds'=>valuearray), eliminating any attributes the DS had, such as its name.
	 				$params[] = array('ds'=>  self::ParametersToArray( $arr ));
	 
	 		}
	 		else
	 		{   // single non-DS param
	 			$params[]=$arrParams->getParamProperties();
	 		}
	 	}
	 	else
	 	{   // array of ProgramParameter objects
	 		foreach($arrParams as $single){
	 			if( $single->isDS()){
	 				// element in array is DS
	 				$arr = $single->getParamValue(); // array of values
	 				// Reduce DS to simple structure ('ds'=>valuearray), eliminating any attributes the DS had, such as its name.
	 				$params[] = array('ds'=>  self::ParametersToArray( $arr ));
	 			}
	 			else
	 			{   // regular non-DS parm
	 				$params[]=$single->getParamProperties();
	 			}
	 		}
	 	}
	 	return $params;
	 } // (deprecated method ParametersToArray)
	 
	 
	 
} //(class ProgramParameter)
	
class DataStructure extends ProgramParameter {

	// v1.4.0 added $comment as arg 5, in place of the obsolete $isReturnParam argument. Data structure return values didn't work properly before 1.4.0 anyway.
	function __construct( $paramsArray , $struct_name ="DataStruct", $dim=0 , $comment = '', $by='', $isArray=false, $labelLen = null, $io = 'both' ){
		parent::__construct( "ds",  $io, $comment, $struct_name, $paramsArray , 'off', $dim, $by, $isArray, null, $labelLen );
	}
}


class CharParam extends ProgramParameter{
	// todo if array. call charparm 5 times with fake field name
	// and coming out, too. (?)
	// CharParam can require hex/ccsid conversions, which other types don't.
	function __construct($io, $size , $comment,  $varName = '', $value , $varying = 'off',$dimension = 0, $by='', $isArray = false,
			             $ccsidBefore = '', $ccsidAfter = '', $useHex = false)
	{
		$type = sprintf("%dA", $size);
		parent::__construct( $type,  $io, $comment, $varName, $value, $varying, $dimension, $by, $isArray,
				             null, null, $ccsidBefore, $ccsidAfter, $useHex);
		return $this; // fluent interface
	}
}

class ZonedParam extends ProgramParameter{
	function __construct($io, $length ,$scale , $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false)
	{
		$type = sprintf("%ds%d", $length, $scale);
		parent::__construct( $type,  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, null, null, '', '', false);
		return $this;
	}
}

class PackedDecParam extends ProgramParameter {
	function __construct($io, $length, $scale , $comment,  $varName = '', $value,$dimension=0, $by='', $isArray = false,  $labelSetLen = null) {
    	$type = sprintf("%dp%d", $length, $scale);
		parent::__construct( $type,  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, $labelSetLen, null, '', '', false);
		return $this;
	}
}

// TODO continue wiht $isMulti

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


/*binary parameter*/
class BinParam extends ProgramParameter {

	function __construct ($io, $size , $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false) {
    	$type = sprintf("%dB", $size);
		parent::__construct( $type,  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray );
	    return $this;
	}  //(function __construct)

	static function  bin2str( $hex_data ){
		return parent::bin2str($hex_data);
	}
	
} //(class BinParam extends ProgramParameter)


