<?php
class XMLWrapper {

	private $encoding =''; 	
	private $sendReceiveHex = false;
	private $inputXML;
	private $outputXML;
	private $error;	
	private $joblog;
	private $joblogErrors = array(); // cpf=>msgtext array

	protected $ToolkitSrvObj;
	
	// 'system' provides cpfs.   
	protected $_cmdTypes = array('cmd', 'rexx', 'system'); 
	
	// options can be string or array.
	// If string, assume it's "encoding." Otherwise can be array of options.
	function __construct( $options ='',  ToolkitService $ToolkitSrvObj = null)
	{
		
		if (is_string($options)) {
			// $options is a string so it must be encoding (assumption for backwards compatibility)
		    $this->encoding = $options;	
		} elseif (is_array($options)) {
           
			// $options is an array so check for its various settings.
			
			if (isset($options['encoding'])) {
				$this->encoding = $options['encoding']; 
			}
			if (isset($options['convertToCcsid']) && $options['convertToCcsid']) {
				// Today, convertToCcsid means the XML Wrapper should convert to and from hex.
				// it could mean more in the future.
				$this->sendReceiveHex = true;
			} else {
				$this->sendReceiveHex = false;
			} //(convertToCCsid)
			 
		} //(is_string)s
		

		if ($ToolkitSrvObj instanceof ToolkitService ) {
			$this->ToolkitSrvObj = $ToolkitSrvObj ;
		}
		
		
	} //(__construct)

	// generate first line of XML based on configured encoding
	// it's important to use correct encoding for non-ASCII languages
	protected function xmlStart() 
	{
		return "<?xml version=\"1.0\" encoding=\"$this->encoding\" ?>";
		
	} //(function xmlStart)

	// correct first line if encoding not there.
	// Possibly other cleanups as well,
	// to be done so SimpleXml doesn't choke on it.
	public function cleanXml($xml) 
	{
		// If XMLSERVICE can provide encoding back to us, we can remove these. 
		$tooLittle = '<?xml version="1.0"?>';
		return str_replace($tooLittle,$this->xmlStart(), $xml);
		
		    
	} //(cleanXml)
		
	public function disconnectXMLIn() {		
		$xml = $this->xmlStart(); // start is all we need
		return $xml;
	}
	public function buildXmlIn(array $params = NULL, array $ReturnParams = NULL,
								$pgm, 
								$lib = "",
								$function = NULL )
    {
    	
		$parameters_xml ='';
		$cmd_xml = '';
		$i=0;
		if(isset($params)){
			foreach ( $params as $element ) {
				$ds = false;
				if (isset ( $element ['ds'] )) {
					$param = $element ['ds'];
					$ds = true;
				}
				else {
					$param = $element;
				}			
				$parameters_xml .= $this->FillXmlParamElement ( $ds, $param, $i );		
			}
		}//io parameters
		
		$return_parameters_xml = '';
		if(isset($ReturnParams ))
		{
			foreach($ReturnParams as $element)
			{	
				 $ds = false;			
				 if(isset($element['fields']) && isset($element['ds_descr'])){
				 	$ds = true;			 					 	
				 }
			     $return_parameters_xml .= $this->fillXMLReturnElement($ds,  $element);
			 }
		 }
		 
		 if(trim ($return_parameters_xml)!= ''){		 
		 	$return_parameters_xml ="<return> $return_parameters_xml </return>";
		 }
			   	
	if(!$function)
		$pgmtag = "<pgm name='$pgm' lib='$lib'>";
	else 
		$pgmtag = "<pgm name='$pgm' lib='$lib' func='$function'>";
		
	$xmlIn = $this->xmlStart() . 	
	"<script>
	$cmd_xml
	$pgmtag
	$parameters_xml
	$return_parameters_xml	
	</pgm>
	</script>";
	
	return $xmlIn;
	}
	
	

	// CW version of building input xml. 
	// $params can be an array of ProgramParameter objects, 
	// or 
	// an XML string representing parameters already in XML form ("parm" tags).
	public function buildXmlInCw($params = NULL, array $ReturnParams = NULL,
								$pgm, 
								$lib = "",
								$function = NULL )
    {
    	
    	
    	$parametersXml = ''; // could remain blank if no parameters are passed
    	
    	if (is_string($params)) {
    		
    		// XML for params is being provided raw. Use it.
    	    $parametersXml = $params;
    	    	
    	} elseif (is_array($params) && (!empty($params))) {
    		
    		// an array of ProgramParameter objects. Build XML from it.
			foreach ( $params as $element ) {
				
				// do parm tag with comment and io.
				$elementProps  = $element->getParamProperties(); 

				$parametersXml .= "<parm io='{$elementProps['io']}' comment='{$elementProps['comment']}'>";
				
				// buildParamXml takes one ProgramParameter object and recursively build XML.
				$parametersXml .= $this->buildParamXmlCw($element);
				
				// end parm tag
				$parametersXml .= "</parm>";
				
			} //(foreach $params)
    		
    	} //(is_string/is_array)

		
		$return_parameters_xml = '';
		if(isset($ReturnParams ))
		{
			foreach($ReturnParams as $element)
			{	
				 $ds = false;			
				 if(isset($element['fields']) && isset($element['ds_descr'])){
				 	$ds = true;			 					 	
				 }
			     $return_parameters_xml .= $this->fillXMLReturnElement($ds,  $element);
			 }
		 }
		 
		 if(trim ($return_parameters_xml)!= ''){		 
		 	$return_parameters_xml ="<return> $return_parameters_xml </return>";
		 }
			   	
	if(!$function)
		$pgmtag = "<pgm name='$pgm' lib='$lib'>";
	else 
		$pgmtag = "<pgm name='$pgm' lib='$lib' func='$function'>";
		
	$xmlIn = $this->xmlStart() .	
	"<script>
	$pgmtag
	$parametersXml
	$return_parameters_xml	
	</pgm>
	</script>";
	
	return $xmlIn;
	}


	protected function buildParamXmlCw(ProgramParameter $paramElement) {
		
		$paramXml = '';

		// build start ds tag
		$props  = $paramElement->getParamProperties(); 

		// optional by
		$by = $props['by'];
		$byStr = ($by) ? " by='$by'" : ''; 
		
		$name = $props['var'];
		
		// optional "array" attribute
		$isArray = $props['array'];
		$isArrayStr = ($isArray) ? " array='on'" : '';
		
		// optional dim
		$dim = $props['dim'];
		$dimStr = ($dim) ? " dim='$dim'" : '';
		

		// optional len that checks length of the structure/field to which it's appended
		$labelLen = $props['len'];
		$labelLenStr = ($labelLen) ? " len='$labelLen'" : '';
		
		// it's a data structure
		if ($props['type'] == 'ds') {

			// start ds tag with name and optional dim and by
			$paramXml .= "<ds var='$name'$dimStr$isArrayStr$labelLenStr>";
			
			// get the subfields
			$dsSubFields = $paramElement->getParamValue();
			if (is_array($dsSubFields) && count($dsSubFields)) {

				// recursively build XML from data structure subfields
				foreach ($dsSubFields as $subField) {
					$paramXml .= $this->buildParamXmlCw($subField);
				} //(foreach)

			} //(is_array, count)
			
			// complete the ds tag
			$paramXml .= "</ds>";
			
		} else {
			
			// not a data structure. a regular single field
			
			$type = $props['type'];
			
			// optional varying
			$varying = $props['varying'];
			$varyingStr = ($varying) ? " varying='$varying'" : '';

			// optional setLen to set length value to a numeric field (see 'len' for where the length comes from)
		    $labelSetLen = $props['setlen'];
		    $labelSetLenStr = ($labelSetLen) ? " setlen='$labelSetLen'" : '';
			
			$data = $props['data'];
			if (is_object($data)) {
				// uh-oh. Something wrong
				echo "data is not a string. type is: $type. data is: " . var_export($data, true) . "<BR>";
			}

			if ($this->sendReceiveHex) {
				// if hex format requested 
				
			    $data = bin2hex($data);
			} // (if ($this->sendReceiveHex))
			
			$paramXml .= "<data var='$name' type='$type'$dimStr$byStr$varyingStr$labelSetLenStr>$data</data>";			
			
		} //(if a data structure / else)
		return $paramXml;
		
	} //(buildParamXmlCw)

	// given XMLSERVICE type such as 10i0, 5i0, 4f, 4p2, set data to the corresponding PHP type as closely as possible.
	// Omit alpha/string because that's our default.
	protected function updateType(&$data, $xmlServiceType) 
	{
		$patterns = array('/10i0/', '/5i0/', '/4f/', '/\d*p\d*/');
		$replacements = array('integer', 'integer', 'float', 'float');
		// look for a match
		$newType = preg_replace($patterns, $replacements, $xmlServiceType);
		
		// if a different type is warranted
		if ($newType != $xmlServiceType) {
			settype($data, $newType);
		}
	} //(phpTypeFromXmlServiceType)
	
	// recursive
	protected function getSingleParamFromXmlCw(SimpleXMLElement $simpleXmlElement) {
		
		// if it's too slow to set types, change it to false.
		$setTypes = true;//true;
		
		$element = array();
		
	    // is this a parm, or perhaps a DS??
	    $elementType = $simpleXmlElement->getName(); // name of the tag is data type
	    $elementAttrs = $simpleXmlElement->attributes();
//	    echo ("Type: $elementType. Attrs: " . printArray($elementAttrs) . ".<BR>");
	    
	    // if this is the outer (parm) element, go down one level to either ds or data.
	    if ($elementType == 'parm') {
	    	if (isset($simpleXmlElement->ds)) {
	    		// get ds beneath.
    	        $simpleXmlElement = $simpleXmlElement->ds;
	    	} elseif (isset($simpleXmlElement->data)) {
	    		// get data beneath.
    	        $simpleXmlElement = $simpleXmlElement->data;
	    	}
	    } //(parm)

	    // either way, let's see what we have now.
	    $elementType = $simpleXmlElement->getName();

	    if ($elementType == 'ds') {

	    	// call it $ds for convenience
			$ds = $simpleXmlElement;

			$subElementArray = array();
			
			/// get info about this data structure
			$outerDsAttributes = $ds->attributes();
			$outerDsName = (string) $outerDsAttributes['var'];
			$outerDsIsArray = (isset($outerDsAttributes['array']) && (strtolower($outerDsAttributes['array']) == 'on')); // whether it's to be considered a simple array
			// look for data elements OR another ds under this ds
			//$testFindDs = $ds->xpath('ds');
			//echo "testFindDs: " . var_export($testFindDs, true) . "<BR>";  
			
	        // get every data or ds element under here.
			if ($underDs = $ds->xpath('data|ds')) {
  //             echo "outerdsname: $outerDsName. underDs: " . var_export($underDs, true) . "<BR><BR>";  
				// we have an array of ds elements or  data elements. Loop through them. 
  			    foreach ($underDs as $indexNum=>$subElement) {

					$attrs = $subElement->attributes();
					// if we're to treat outer ds as an array,
					// OR the subparam has no var/name for some reason,
					// use a numeric index as key.
					$givenSubelementName = (isset($attrs['var'])) ? $attrs['var'] : '';
					$givenSubelementName = (string) $givenSubelementName; 
					if ($outerDsIsArray || empty($givenSubelementName)) {
						$subElementName = $indexNum;
					} else {
						// else elememnt has its own var/name.
					    $subElementName = $givenSubelementName;	
					} //(if ($isArray || !isset($subParam['var'])))

			        // is it another ds? get contents recursively.
				    $elType = $subElement->getName();

				    if ($elType == 'ds') {
							
					    $data = $this->getSingleParamFromXmlCw($subElement);
	
				    	// ignore inner array name because we have outer numbering name.
				    	// drill down one level past inner array name.
				    	//echo "Array: " . printArray($data) . " with given name: $givenSubelementName and nametouse: $subElementName<BR>";
				    	$data = $data[$givenSubelementName];

				    } else {
				    	
				    	// single data element. present its value.				    	
				    	$data = (string) $subElement;
				    	
				    	// if data is a DTS date that needs to be converted to a regular date.
				    	// and data is not empty or blank (hex ebcdic)
				    	if (isset($subElement['dtsdate']) && ($subElement['dtsdate'] == 'on')
				    	    && $data && ($data != '4040404040404040')) {
				    		// instantiate dateTimeApi if not instantiated yet in the loop
				    		if (!isset($dateTimeApi)) {
				    			$dateTimeApi = new DateTimeApi($this->ToolkitSrvObj);
				    		}
				    		// replace DTS date with "real" date
				    		$data = $dateTimeApi->dtsToYymmdd($data);	 
				    	} //(if a DTS date) 

				    	// TODO check performance of type casting. 
				    	if ($setTypes) {
							$type = $attrs->type;
							$this->updateType($data, $type);
				    	}
				    	
				    } //(if (isset($subElement->ds)

				    // add data element to ds
					$subElementArray[$subElementName] = $data;
					
			    } //(foreach subparams)
			} //(endif underds)

			// set ds and its contents to be returned to caller
			$element[$outerDsName] = $subElementArray; 
				
			    
		} elseif ($elementType == 'data') {

			// we're exploring a single outer data element. return simple name/value pair
			$attr = $simpleXmlElement->attributes();
			$dataVarName = (string)$attr->var;
			// name=>value
			$element[$dataVarName] = ( string ) $simpleXmlElement;
			
			// other types (old toolkit cast output values as correct types)
			// TODO check performance of type casting.
	        if ($setTypes) { 
			    $type = $attr->type;
			    $this->updateType($data, $type);
	        }
			// Similar to code above in "single data element under a DS"
			// if data is a DTS date that needs to be converted to a regular date.
		    // and data is not empty or blank (hex ebcdic)			
		    // TODO could convert 4040 into blanks
	    	if (isset($attr->dtsdate) && (((string) $attr->dtsdate) == 'on') 
	    	     && $element[$dataVarName] && ($element[$dataVarName] != '4040404040404040')) {
	    		if (!isset($dateTimeApi)) {
	    			$dateTimeApi = new DateTimeApi($this->ToolkitSrvObj);
	    		}
	    		// replace DTS date with "real" date
	    		$element[$dataVarName] = $dateTimeApi->dtsToYymmdd($element[$dataVarName]);	 
	    	} //(if a DTS date) 
			
            
		} //(ds / data)
			
		return $element;
			
	} //(getSingleParamFromXmlCw)

/* sample		
<parm io='both' comment='PS'>
<ds var='PS' array='on'>
<ds var='PS_0'>
<data var='PS1' type='10a' varying='off'>test1</data>
<data var='PS2' type='10a' varying='off'>test2</data>
<data var='PS3' type='10a' varying='off'>test3</data>
</ds>
<ds var='PS_1'>
<data var='PS1' type='10a' varying='off'>test3</data>
<data var='PS2' type='10a' varying='off'>test4</data>
<data var='PS3' type='10a' varying='off'>test5</data>
</ds>
</ds>
</parm>
</pgm>
*/
	
	// CW version of getting output parameters from program call
	public function getParamsFromXmlCw($xml) {

		//$xml = $this->cleanXml($xml); // don't need cleanXml now that XMLSERVICE provides proper encoding on XML, even for errors 
		
		// Replace ampersands with corresponding entity codes. Not needed anymore with data wrapped in CDATA by XMLSERVICE
		
		//$xml = preg_replace('/&[^; ]{0,6}.?/e', "((substr('\\0',-1) == ';') ? '\\0' : '&amp;'.substr('\\0',1))", $xml);
		$xmlobj = simplexml_load_string ( $xml );

		if (! $xmlobj instanceof SimpleXMLElement) {
			$badXmlLog = '/tmp/bad.xml';
			$this->error = "Unable to parse output XML, which has been logged in $badXmlLog. Possible problems: CCSID, encoding, binary data in an alpha field (use binary/BYTE type instead); if < or > are the problem, consider using CDATA tags.";
			error_log ( $xml, 3, $badXmlLog );		
			return false;
		}
		
		if( isset($xmlobj->error))	
		{
			// capture XML error and joblog.
			// In PgmCall method we will try to get a better error than the XML error. 
			$this->error =(string)$xmlobj->error->xmlerrmsg . 
			              ' (' . (string)$xmlobj->error->xmlhint . ')';
			$this->joblog = (string)$xmlobj->joblog;				
			return false; 
		}
		
		$values = array();

		// get all parameters
		$params = $xmlobj->xpath ( '/script/pgm/parm' );

		foreach ( $params as $simpleXmlElement ) {

			// pass parms into a recursive function to get ds/data elements
			
			// process and get value(s) from that parm.
            $paramValue = $this->getSingleParamFromXmlCw($simpleXmlElement);

            // add key/value to values array
            $varName = key($paramValue); 
            $values[$varName] = $paramValue[$varName];
            			
		} //(foreach $params)		

		/*or data structure (or data structure array) or single value may be returned.*/
		$retval_values = array();		
		$ds_retvals = $xmlobj->xpath ( '/script/pgm/return/ds' );
		if( $ds_retvals){
			foreach ($ds_retvals as $simpleXMLElement )
			{			
				$el = $simpleXMLElement->xpath('data');		
				$el_values = $this->readElement($el);		 		
				$retval_values[] = $el_values;		
			
			}
		}
		else
		{		
			$retvals = $xmlobj->xpath ( '/script/pgm/return/data' );
			foreach ($retvals as $simpleXMLElement ){
				$Attr = $simpleXMLElement->attributes();			   
				$retval_values [(string)$Attr->var] = ( string ) $simpleXMLElement;
			}			
		}
		
		$callresults['io_param'] = $values; 
		$callresults['retvals'] =  $retval_values;
		
 	
	   return $callresults;	
	}
	
	
 	private function FillXmlParamElement($ds = false, $params, &$i ) {
		
		if (!$ds) {
			$parameters_xml = $this->createXMLParamElement ($params, $i);			
			$i++;
					
		} else {
			// is a data structure
			// Started to add code to give variable/parm names to data structures.
			//$var = ($params.
			$varStr = '';
			if (isset($var) && $var) {
				$varStr = " var='$var'";
			}
			$parameters_xml = "<parm io='both'><ds$varStr>";
			foreach ( $params as $param ) {				
		        $parameters_xml .= $this->createXMLDataStructElement ($param, $i);				
			    $i++;
			}			
			$parameters_xml .= "</ds> </parm>";			
		}
		
		return $parameters_xml;
	}

	
	
   public function getCommandXmlInPase( $cmd )
    {     	
    	return $this->buildCommandXmlIn($cmd, 'pase');
    }
    
    // $cmd can be a string or array of multiple commands
    // It's more efficient to run multiple commands with one XMLSERVICE call than to do many calls
   public function buildCommandXmlIn( $cmd, $exec = 'pase')
    {   

    	// always starts the same
    	$xmlIn = $this->xmlStart() .
    	         "<script>";
    	
    	// if a string, make a single-item array from it.
    	if (is_string($cmd)) {
    		$cmdArray = array($cmd);
    	} else {
    		// already multiple.
    		$cmdArray = $cmd;
    	}

    	foreach ($cmdArray as $oneCmd) {
    	
	    	// inner command depends on what was passed in.
	    	if ($exec == 'pase') {
	    		// changed single quotes around $cmd to doubles so that $cmd can contain single quotes.
		    	$xmlIn .= "<sh rows='on'>/QOpenSys/usr/bin/system \"$oneCmd\"</sh>";
	    	} else {

	    	    // check that our exec value is in whitelist.
	    	    // blank is a synonym for default of cmd.
	    	    if ($exec && in_array($exec, $this->_cmdTypes)) {
	    		    $execStr = " exec='$exec'"; // e.g. <cmd exec='rexx'>                		
	    	    } else {
	     		    $execStr = ''; // OK because blank string defaults to cmd on XMLSERVICE side
	    	    } 
	            $xmlIn .= "<cmd$execStr>$oneCmd</cmd>";
	    	} //(if $pase)  

    	} //(foreach $cmdArray)
	    	
        // always ends the same
        $xmlIn .= "</script>";
	    return $xmlIn;	
    }
    
   private function FillXMLReturnElement( $ds=false , $ReturnParams  ){
    	
    	if( !is_array($ReturnParams ))
    	   return false;
    	   
    	$parameters_xml = '';   
    	if( $ds )/*data structrure */
    	{
    		if( isset( $ReturnParams  ['ds_descr']['dim']) && 
    		 		   $ReturnParams  ['ds_descr']['dim'] > 0 )
    			$dim ="dim='".$ReturnParams  ['ds_descr']['dim']."'";
    			
    		if( isset( $ReturnParams  ['ds_descr']['var']))    				  
    			$name = $ReturnParams  ['ds_descr']['var'];
    		
    	    if(trim($name)!=='')
    	    	$dsname = "var='$name'";	
    	    		
    		$params = $ReturnParams   ['fields'];    		
    		$parameters_xml .= "<ds $dim $dsname>";
	    	 
	    	foreach( $params as $param) {	    	
		  		$parameters_xml .= $this->createXMLReturnElement ($param);							
	    	}
    		$parameters_xml .= "</ds>";
    	}
    	else
    	{ 
            $param = $ReturnParams;	      			
			$parameters_xml .= $this->createXMLReturnElement ($param);		
    	} 
    	 
        return $parameters_xml;
    }
    
    private function createXMLReturnElement ($param)
	{
		if(!is_array($param))
		 return '';
		 
		$var = $param ['var'];
		$data = $param ['data'];
		$descr = $param ['type'];
		$varying = '';
		if($param ['varying']== 'on')
			$varying = " varying='on'";
			
		return  "<data type='$descr' var='$var' $varying >$data</data>\n"; 
	}	
	
	private function createXMLParamElement ($param, $i)
	{
		if(!is_array($param))
		   return ''; 

		  
			$comment = $param ['comment'];
			$data = $param ['data'];
			
			if ($this->sendReceiveHex) {
				// if hex format requested 
			    $data = bin2hex($data);
			} // (if ($this->sendReceiveHex))
			
			$type = $param ['type'];
			$io   = $param ['io'];
			if (isset($param ['var'])){
				$var   = $param ['var'];
			}
			else
				$var   = 'var'.$i;
			$type = $param ['type'];
			$varying = '';
			if($param ['varying']== 'on')
				$varying = " varying='on'";
				
			$parameter_xml = "<parm comment='$comment' io='$io'> ";
			$count =1; //one parameter 	
			$addDimenstion='';
		 	if($param['dim'] != 0) {			 		
				$count = $param['dim'];
				$parameter_xml .= "<ds>";
		 	}
		 		 		 			
			for($j =1; $j<= $count ; $j++){/*for array definition */
				 if($count > 1)
				 	$addDimenstion = $j;
				$parameter_xml .="<data type='$type' var='$var$addDimenstion' $varying>$data</data>";
			}	
					
			if($param['dim'] != 0)	
				$parameter_xml .= "</ds>";	 		  
		
			$parameter_xml .="  </parm>";	
			return $parameter_xml;
				
	}	
	
	private function createXMLDataStructElement ($param, $i)
	{
		if(!is_array($param))
		   return ''; 
		   $parameter_xml='';
		   
			$comment = $param ['comment'];
			$data = $param ['data'];
			if ($this->sendReceiveHex) {
				// if hex format requested 
			    $data = bin2hex($data);
			} // (if ($this->sendReceiveHex))
			
			
			$type = $param ['type'];
			$io   = $param ['io'];
			if (isset($param ['var'])){
				$var   = $param ['var'];
			}
			else
				$var   = 'var'.$i;
			$type = $param ['type'];
			$varying = '';
			if($param ['varying']== 'on')
				$varying = " varying='on'";
		
				
			if($param['dim'] != 0)
				$count =$param['dim'];
			else 	 $count = 1;
			
            $addDimenstion='';
			for($j=0; $j< $count ; $j++){			
				
			/* If parameter values includes special characters there is a need to surround them with an XML tag CDATA. 
			 * $parameters_xml = "<parm comment='$comment' io='$io'>
  		 </parm>";*/	
				if($count > 1)
					$addDimenstion = $j;//should have a different identificaion
						
				$parameter_xml .= "<data type='$type' var='$var$addDimenstion' comment='$comment'>$data</data>";
			}
			return $parameter_xml;  	
				
	}   

	/* parse returned  xml */
	/* put all element attributes in array with name ('var') as index */
	private function readElement( array $el){
		$el_values= null;		
 		foreach ( $el as $element ){
 		   $Attr = $element->attributes();
 		   $data = (string) $element;

 		   if ($this->sendReceiveHex) {
				// convert back from hex 
			    $data = pack("H*" , $data); 
		    } // (if ($this->sendReceiveHex))
 		   
 		   $el_values [(string)$Attr->var] = $data;
 		}
	  return $el_values;								
	}
	
	public function getParamValueFromXml($xml) {		
		$xmlobj = @simplexml_load_string ( $xml );
		if (! $xmlobj instanceof SimpleXMLElement) {
			$this->error = "Can't read output xml";	
			error_log ( $xml, 3, '/tmp/bad.xml' );		
			return false;
		}
		
		if( isset($xmlobj->error))	
		{
/*			TODO consider using 
			<errnoile> if not 0. It can have a CPE prepended for a "CPF-style" message.
			
			Added xmlhint as a helpful message.
*/			
			$this->error =(string)$xmlobj->error->xmlerrmsg . 
			              ' (' . (string)$xmlobj->error->xmlhint . ')';			
			return false; 
		}
        // TODO if not a parm, also be able to parse. 		
		$values = array();
		$params = $xmlobj->xpath ( '/script/pgm/parm' );
		foreach ( $params as $simpleXMLElement ) {
			if (isset ( $simpleXMLElement->ds )) {
				// data structure with multiple values				
				$ds = $simpleXMLElement->ds;	
				$el = $ds->xpath('data'); // all nodes of type data under the ds	
				$addval = $this->readElement($el);
				// add array of values to array.
				$values = array_merge($values, $addval);
				
			} else {
				// a single value   
				$Attr = $simpleXMLElement->data->attributes();
				$data = ( string ) $simpleXMLElement->data;
 		        
				if ($this->sendReceiveHex) {
				    // convert back from hex 
			        $data = pack("H*" , $data); 
		        } // (if ($this->sendReceiveHex))
				
		        // add this single value to array
				$values [(string)$Attr->var] = $data;
					
			} //(if (isset ( $simpleXMLElement->ds )))			
		} //(foreach ( $params as $simpleXMLElement ))		
		
		/*or data structure (or data structure array) or single value may be returned.*/
		// TODO this "return ds" code may need work
		$retval_values = array();		
		$ds_retvals = $xmlobj->xpath ( '/script/pgm/return/ds' ); // all return ds nodes
		if( $ds_retvals){
			foreach ($ds_retvals as $simpleXMLElement )
			{			
				$el = $simpleXMLElement->xpath('data');		
				$el_values = $this->readElement($el);		 		
				$retval_values[] = $el_values;		
			
			} // (foreach)
		} else {
			// no return ds'es. Look at single data items.		
			$retvals = $xmlobj->xpath ( '/script/pgm/return/data' ); // all return data nodes
			foreach ($retvals as $simpleXMLElement ){
				$Attr = $simpleXMLElement->attributes();

				$data = ( string ) $simpleXMLElement;
				if ($this->sendReceiveHex) {
			        $data = pack("H*" , $data); // convert back from hex 
		        } // (if ($this->sendReceiveHex))
				
				$retval_values [(string)$Attr->var] = $data;
			} //(foreach)			
		}
		
		$callresults['io_param'] = $values; 
		$callresults['retvals'] =  $retval_values;
		
 	
	   return $callresults;	
	}

   // gets any error code and sets it in toolkit.
   // returns true or false depending on interpretation of status
   public function getCmdResultFromXml($xml, $parentTag = 'sh') {
   	   $this->error = '';

	   $xmlobj = simplexml_load_string ( $xml );
	   if (! $xmlobj instanceof SimpleXMLElement) {
	   	    /*bad xml returned*/
			$this->error = "Can't read output xml";	
			error_log ( $xml, 3, '/tmp/bad.xml' );		
			return false;
		}
		
		
		$retval = true; // assume OK unless error found. Was false, but sh doesn't return success flag.
		$params = $xmlobj->xpath ( "/script/$parentTag" );

		foreach ( $params as $simpleXMLElement ) {

			// Note: there may be multiple ->error tags
			
			if (isset ( $simpleXMLElement->error )){//*** error
				
				
				// Note: there may be multiple ->error tags
				
				// As of XMLSERVICE 1.60:
				// array element[0] is probably the *** error text.
				// array element[1] may be the CPF code.
				if (isset($simpleXMLElement->error[1])) {
				    $this->error = (string) $simpleXMLElement->error[1];	
				} else {
					$this->error = "Command execute failed.";
				}
				
				//$err = (string )$simpleXMLElement->error;
                // there's an error code of some kind. use it.
                // A change: get rid of standard msg. Only return the err, which may be a CPF.
				//$this->error = "Command execute failed. ".$err;
				
				$retval = false;
				break;				
			}

			// Older style. Now, *** error appears in ->error tag
			if(strstr(((string)$simpleXMLElement),"*** error")){
				// this message is a generic failure so it's OK.				
				$this->error = "Command execute failed. ";
				$retval = false;
				break;	
		    }
			
		    // in the case of interactive commands, there may be no "success" msg.
			if (isset ( $simpleXMLElement->success )){								
				$retval = true;
				break;
			} 
			
			// Older style. Now, +++ success appears in ->success tag			
			if(strstr(((string)$simpleXMLElement),"+++ success")){										
				$retval = true;
				break;	
			}
			
		}

		return $retval;
   }
	
   // getting rows from command output.
   public function getRowsFromXml($xml, $parent = 'sh')
   {
   	
		$this->error = '';
   		$values = array();
		/* bad xml returned*/
		$xmlobj = @simplexml_load_string ( $xml );
		if (! $xmlobj instanceof SimpleXMLElement) {
		
			$this->error = "Can't read output xml";
			return false;
		}
		$params = $xmlobj->xpath ( "/script/$parent/row" );

		foreach ( $params as $simpleXMLElement ) {
			if ($parent == 'sh') {
				
				$data =  ( string )$simpleXMLElement;
			    if ($this->sendReceiveHex) {
			        $data = pack("H*" , $data); // convert back from hex 
		        } // (if ($this->sendReceiveHex))
				
			    $values[]= $data; // add to array of 'sh' values.
			    
			} elseif ($parent == 'cmd' ) {
				// attribute 'desc' will hold the output field name to which the data belongs.
				$outputFieldName = (string) $simpleXMLElement->data->attributes()->desc;
				// get the data and trim any spaces or line feeds.
				
				$data = ( string )$simpleXMLElement->data;
				
			    if ($this->sendReceiveHex) {
			        $data = pack("H*" , $data); // convert back from hex 
		        } // (if ($this->sendReceiveHex))
				
			    $values[$outputFieldName]= trim($data, " \n"); // with 'cmd', each piece of data is named. Trim spaces and end-of-line chars
			} //(if parent == 'sh')			
		}
		
		if(count($params)==0){
			$this->error = "No rows";	
			$params = $xmlobj->xpath ( "/script/$parent" );
			foreach ( $params as $simpleXMLElement ) {
				if(strstr(((string)$simpleXMLElement),"+++ no output")){
					$this->error = "Command does not return output";
				}
				break;	    	
			}
		}
		
    return $values;	
  }	
	
  // return error message text
  public function getLastError()
  {
  	return $this->error;
  }
  public function getLastJoblog()
  {
  	return $this->joblog;
  }
  
      
} //(class XMLWrapper)
