<?php 

require_once 'ToolkitService.php';
require_once 'ToolkitServiceParameter.php';

/*
 * Functionality for parsing PCML
 */
class ToolkitPcml
{

	protected $_description = array();
	protected $_originalObjName = '';
	protected $_objInfoArray = array(); // 'lib', 'obj', 'func'
	protected $_connection;
	protected $_isSingleLevelSimpleValue = false;
	protected $_pcmlStructs = array(); // save structs in here
	// TODO a single array of countref names is OK for now, but won't jibe with official PCML logic of infinite hierarchical references.. To find the ref, we'd have to look at current section and work outward. Too much trouble. What we have here will work most of the time.
	protected $_countRefNames = array(); // names of countRef fields (fields containing counts to return from programs)

	protected $_countersAndCounted = array(); // 'CarCount' => 'Cars'.  counter also used as label.
	
	/**
	 * @return array
	 */
	public function getDescription() 
	{
		return $this->_description;
		
	}
	
	/**
	 * @param array $countersAndCounted
	 */
	public function setCountersAndCounted($countersAndCounted = array()) 
	{
		$this->_countersAndCounted = $countersAndCounted;
		
	} //(public function setCountersAndCounted)
	
	/**
	 * Constructor takes a PCML string and converts to an array-based new toolkit parameter array.
	 *
	 * @param string $pcml The string of PCML
	 * @param ToolkitService $connection connection object for toolkit
	 * @param array $countersAndCounted
	 * @throws Exception
	 */
	public function __construct($pcml, ToolkitService $connection, $countersAndCounted = array())
	{

		$this->setConnection($connection);

		$this->setCountersAndCounted($countersAndCounted);
		
		// Convert PCML from ANSI format (which old toolkit required) to UTF-8 (which SimpleXML requires).
		$encoding = getConfigValue('system', 'encoding', 'ISO-8859-1'); // XML encoding

		/*
		 * Look for optionally set <?xml encoding attribute
		 * and change encoding if attribute is set and not UTF-8
		 * or change encoding if attribute is not set and ini encoding is not UTF-8
		 */
		$pcml = trim($pcml);
		$matches = array();
		$regex = '/^<\?xml\s.*?encoding=["\']([^"\']+)["\'].*?\?>/is';
		if (preg_match($regex, $pcml, $matches) && $matches[1] != 'UTF-8') {
			//remove xml-tag
			$pcml = substr($pcml, strlen($matches[0]));
			$pcml = mb_convert_encoding($pcml, 'UTF-8', $matches[1]);
		} elseif ($encoding != 'UTF-8') {
			$pcml = mb_convert_encoding($pcml, 'UTF-8', $encoding);
		}

        //program name is stored as: /pcml/program name="/qsys.lib/eacdemo.lib/teststruc.pgm"
        $xmlObj = new SimpleXMLElement($pcml);

        // get root node and make sure it's named 'pcml'
        if(!isset($xmlObj[0]) || ($xmlObj[0]->getName() != 'pcml')) {
        	throw new Exception("PCML file must contain pcml tag");
        }

        $pcmlObj = $xmlObj[0];

        // get program name, path, etc.
        if(!isset($pcmlObj->program) || (!$pcmlObj->program)) {
        	throw new Exception("PCML file must contain program tag");
        }
        $programNode = $pcmlObj->program;

        $pgmAttrs = $programNode->attributes();


        /*sample:
<program name="name"
[ entrypoint="entry-point-name" ]
[ epccsid="ccsid" ]
[ path="path-name" ]
[ parseorder="name-list" ]
[ returnvalue="{ void | integer }" ]
[ threadsafe="{ true | false }" ]>
</program>*/

        // let's focus on name, path, and entrypoint, the only attributes likely to be used here.
        $givenPgmName = (isset($pgmAttrs['name'])) ? $pgmAttrs['name'] : ''; // ignored!
        $path = (isset($pgmAttrs['path'])) ? $pgmAttrs['path'] : '';
        $entrypoint = (isset($pgmAttrs['entrypoint'])) ? $pgmAttrs['entrypoint'] : '';

        // Note: if entrypoint is supplied, it's the function in a service program. "name" will be the same as entrypoint.
        // if entrypoint is not supplied, name is the actual program name.
        // Therefore, "name" seems somewhat worthless.

        // break up path, separated now by slashes. can be varied lib and pgm.
        // remove the /qsys.lib that may be in front but only if it's simply qualifying another library. qsys may be the actual program library, too.

        $objArray = $this->splitPcmlProgramPath($path);
        
        $pgmLib = ($objArray['lib']) ? $objArray['lib'] : '';
        $pgmName = $objArray['obj'];
        $pgmProcedure = ($entrypoint) ? $entrypoint : ''; // optional procedure (aka function)
        
        // Now create data description array.
        // TODO create separate method to convert PCML to param array.
        $dataDescriptionArray = $this->pcmlToArray($xmlObj);

        //Change the encoding back to the one wanted by the user, since SimpleXML encodes its output always in UTF-8
        $pgmName = mb_convert_encoding($pgmName, $encoding, 'UTF-8');
        mb_convert_variables($encoding, 'UTF-8', $dataDescriptionArray);

        $this->_description  = $dataDescriptionArray;
        
	} //(__construct)

	
	
	// array of simple types, PCML to XMLSERVICE toolkit, with sprintf-style percent formatting.
	protected $_pcmlTypeMap = array('char'    => "%sa",
			                        'packed'  => "%sp%s",
			                        'float'   => "4f",   // 4-byte float
			                        'struct'  => "ds",   // data structure
			// omit INT from type map because we'll need program logic to determine if short or regular int.
			          //              'short'   => "5i0",  // int16, 2 bytes
			          //              'int'     => "10i0", // int32, 4 bytes
			                        'zoned'   => "%ss%s", // e.g. 5s2
			                        'byte'    => "%sb", // binary (hex) 
			                        'hole'          => "%sh", // not a PCML type but XMLSERVICE can handle it, so let's be prepared.
	);
	
    // PCML usage mapping
    protected $_pcmlInoutMap = array('input'         => 'in',
                                     'output'        => 'out',
                                     'inputoutput'   => 'both',
                                     // inherit means inherit from parent element, and if no parent element, do INOUT.
                                     // TODO implement "inherit" more precisely, checking parent element's usage.
                                     'inherit'       => 'both',
                                );


	/**
	 * When we discover a "CountRef" reference in an old toolkit data description, 
	 * retain the name for later use.
	 * 
	 * @param $name
	 */
	protected function addCountRefName($name) {
		// add name to our array.
		$this->_countRefNames[] = $name;
	} //(addCountRefName)
	
	/**
	 * return array of all names of countRef fields that we have found.
	 * 
	 * @return array
	 */
	protected function getCountRefNames() {
		return $this->_countRefNames;
	} //(getCountRefNames())
	

	/**
	 * Store toolkit object that was passed in
	 */
	protected function setConnection($conn = null) {
		$this->_connection = $conn;
	}
	
	/**
	 * Return toolkit object that was set earlier
	 * @return ToolkitService
	 */
	public function getConnection() {
		return $this->_connection;
	}
	
	/**
	 * @param $path
	 * @return array
	 * @throws Exception
	 */
	public function splitPcmlProgramPath($path) {
		// given a program path that MAY be qualified by a library and slash,
		// such as /QSYS.LIB/*LIBL.LIB/MYPGM.PGM
		// though it QSYS is the program library, QSYS.LIB will only appear once,
		// split it up and return an array of the form:
		// [lib]=>xxx, [obj]=>yyy
		// If no library, that part will be blank.
	
		// Note, library could also be *LIBL.LIB
	
		// Break up path, separated now by slashes. can be varied lib and pgm.
		// remove the /qsys.lib that may be in front but only if it's simply qualifying another library. qsys may be the actual program library, too.
	
		// trim not only spaces, but slashes, from beginning and end.
		// also make uppercase for consistency. (OK to use uppercase--lib and program here, not function)
		$path = strtoupper(trim($path, " /"));
	
		// remove .LIB, .PGM, .SRVPGM that might be extensions for IFS-style file path.
		$path = str_replace(array('.PGM', '.SRVPGM','.LIB'), array('', '', ''), $path);
	
		if(!$path) {
		throw new Exception("PCML program path is required.");
		}
	
		$result = array('lib'=>'', 'obj'=>'');
			$parts = explode('/', $path);
			$numParts = count($parts);
	
		if($numParts > 3) {
		throw new Exception("PCML program path should not have more than 3 slash-delimited parts.");
		}
	
		switch ($numParts) {
			case 3:
			// 3 parts. QSYS, library, and program was provided.
			$result['lib'] = $parts[1];
			$result['obj'] = $parts[2];
			break;
	
			case 2:
			// 2 parts. library, and program were provided.
			$result['lib'] = $parts[0];
			$result['obj'] = $parts[1];
			break;
	
			case 1:
			// 1 part. program was provided.
			$result['pgm'] = $parts[0];
			break;
	
			default:
			throw new Exception("PCML program path has invalid number of parts (<1 or >3).");
			break;
	
		} //(switch)
	
		return $result;
    } //(splitPcmlProgramPath)
	
	
	/**
	 * given a single ->data or ->struct element, return a parameter object in the new toolkit style.
	 * 
	 * @param SimpleXmlElement $dataElement
	 * @return ProgramParameter
	 */
    public function singlePcmlToParam(SimpleXmlElement $dataElement)
    {
    
    	$tagName = $dataElement->getName();
    
    	// get attributes of this element.
    	$attrs = $dataElement->attributes();
    
    	// both struct and data have name, count (optional), usage
    	$name = (isset($attrs['name'])) ? (string) $attrs['name'] : '';
    	$count = (isset($attrs['count'])) ? (string) $attrs['count'] : '';
    	$usage = (isset($attrs['usage'])) ? (string) $attrs['usage'] : '';
    	$structName = (isset($attrs['struct'])) ? (string) $attrs['struct'] : '';
    
    	// fill this if we have a struct
    	$subElements = array();
    
    
    	// each item should have tag name <data>
    	if ($tagName == 'data') {
    
    		$type = (isset($attrs['type'])) ? (string) $attrs['type'] : '';
    
    		// Get initial value, if specified by PCML.
    		$dataValue = (isset($attrs['init'])) ? (string) $attrs['init'] : '';
    		
    		// if a struct then we need to recurse.
    		if ($type == 'struct') {

    			$theStruct = null; // init
    
    			// look for matching struct definition encountered earlier.
    			if ($this->_pcmlStructs) {
    
    				// TODO verify type with is_array and count
    				foreach ($this->_pcmlStructs as $possibleStruct) {
    					$possStructAttrs = $possibleStruct->attributes();
    					if ($possStructAttrs['name'] == $structName) {
    						$theStruct = $possibleStruct;
    						$structAttrs = $possStructAttrs;
    						break;
    					}
    				}
    			} //(if array of structs exists)
    
    			// if struct was not found, generate error for log
    			if (!$theStruct) {
    				//				    $this->getConnection->logThis("PCML structure '$structName' not found.");
    				return null;
    			}
    
    			// if we got here, we found our struct.
    
    			// count can also be defined at the structure level. If so, it will override count from data level)
    			if (isset($structAttrs['count'])) {
    				$count = (string) $structAttrs['count'];
    			} //(if count supplied at structure level)
    
    			// "usage" (in/out/inherit) can be defined here, at the structure level.
    			$structUsage = (isset($structAttrs['usage'])) ? (string) $structAttrs['usage'] : '';
    
    			// if we're not inheriting from our parent data element, but there is a struct usage, use the struct's usage (input, output, or inputoutput).
    			if (!empty($structUsage) && ($structUsage != 'inherit')) {
    				$usage = $structUsage;
    			}
    
    			$structSubDataElementsXmlObj = $theStruct->xpath('data');
    			if ($structSubDataElementsXmlObj) {
    				foreach ($structSubDataElementsXmlObj as $subDataElementXmlObj) {
    
    					if ($subDataElementXmlObj->attributes()->usage == 'inherit') {
    						// subdata is inheriting type from us. Give it to them.
    						$subDataElementXmlObj->attributes()->usage = $usage;
    					}
    
    					// here's where the recursion comes in. Convert data and add to array for our struct.
    					$subElements[] = $this->singlePcmlToParam($subDataElementXmlObj);
    
    				}
    
    			} //(if data elements under this struct xml obj were found)
    
    		} //(if type of data is struct)
    
    		/* explanation of the terms "length" and "precision" in PCML:
    		 * http://publib.boulder.ibm.com/infocenter/iadthelp/v6r0/index.jsp?topic=/com.ibm.etools.iseries.webtools.doc/ref/rdtcattr.htm
    		 * 
    		 * For "int" values, length is the number of bytes; precision represents the number of bits. (Can be ignored here)
    		 * For zoned and packed values, length is the maximum number of digits; precision represents the maximum decimal places.
    		 * 
    		 */
    		$length = (isset($attrs['length'])) ? (string) $attrs['length'] : '';
    		$precision = (isset($attrs['precision'])) ? (string) $attrs['precision'] : '';

    		$passBy = ''; // default of blank will become 'ref'/Reference in XMLSERVICE. Blank is fine here.
    		if (isset($attrs['passby']) && ($attrs['passby'] == 'value')) {
    			$passBy = 'val'; // rare. PCML calls it 'value'. XMLSERVICE calls it 'val'.
    		} //(if (isset($attrs['passby']) &....)

    		
    		// find new toolkit equivalent of PCML data type
    		if (isset($this->_pcmlTypeMap[$type])) {
    			// a simple type mapping
    			$newType = (string) $this->_pcmlTypeMap[$type];
    		} elseif ($type == 'int') {
    			// one of the integer types. Need to use length to determine which one.
    			if ($length == '2') {
    				$newType = '5i0'; // short ints have two bytes
    			} elseif ($length == '4') {
    				$newType = '10i0'; // normal ints have four bytes
    			} else {
    				$newType = ''; // no match
    			} //(length == 2, et al.)
    
    		} else {
    			$newtype = '';
    
    		} //(if (isset($this->_pcmlTypeMap[$type])))
    
    			
    		$newInout = (isset($this->_pcmlInoutMap[$usage])) ? (string) $this->_pcmlInoutMap[$usage] : '';
    
    		// TODO correct all this isArray business. 
    		// Can we manage without isArray? 
    		// well, it's already handled by toolkit....try and see, though.
    		// poss. eliminate extra object levels, at least?
    		
    		// TODO (I think I did it!) handle this better. Use 'dim'.
    		// See if Count can replace $dim as I did.
    		// if $dim, it's an array. Otherwise a single value.
    		if ($count > 1) {
    			// array of dummy values, dimension of $dim
    			// TODO: should be able to use a single ProgramParameter object with the 'dim' value set.
    			//       Or try to fix into a DS mold.
    		//	$dataValue = array_fill(0, $count, '');
    			$isArray = true;
    		} else {
    			// no need for any dummy value.Could be 'init' from above, or leave the default.
//    			$dataValue = '';
    			$isArray = false;
    			// and leave $dim alone to flow into the ProgramParmeter below.
    		} //(dim > 1)
    		
    		
    		// done with $dim. PHP wrapper handles dim differently.
    		$dim = 0;
    		
    		
    
    	} //(tagName == 'data')


    	// TODO I think simply add 'counterLabel' and 'countedLabel'. 
    	
    	// count
    	$newCount = 0; // initialize
    	$countRef = '';
    	// TODO deal with this. Really need a better way to find the counter data elements.
    	// Allow a countref, too, in PCML??? Maybe! Count will be the dim (max) and countref is the actual name.
    	// Some customers have done it wrong. Instead of specifying a field as count, gave max count.
    	// "count can be a number where number defines a fixed, never-changing number of elements in a sized array.
    // OR a data-name where data-name defines the name of a <data> element within the PCML document that will contain, at runtime, the number of elements in the array. The data-name specified can be a fully qualified name or a name that is relative to the current element. In either case, the name must reference a <data> element that is defined with type="int". See Resolving Relative Names for more information about how relative names are resolved.
    // about finding the element: http://pic.dhe.ibm.com/infocenter/iseries/v7r1m0/index.jsp?topic=%2Frzahh%2Flengthprecisionrelative.htm
    	// Names are resolved by seeing if the name can be resolved as a child or descendent of the tag containing the current tag. If the name cannot be resolved at this level, the search continues with the next highest containing tag. This resolution must eventually result in a match of a tag that is contained by either the <pcml> tag or the <rfml> tag, in which case the name is considered to be an absolute name, not a relative name.""
    // Let's simply use $countersAndCounted. If necessary, pre-process PCML to create $countersAndCounted.
    	if (is_numeric($count) && ($count > 0)) {
    		$newCount = $count;
   	} //(count)
    	
    	$element = array();
    	
    	
    	// $subElements are if this is a struct.
    	if (count($subElements)) {
    		$dataValue = $subElements;
    	}
    	

    	//$type,  $io, $comment='', $varName = '', $value, $varing = 'off', $dimension = 0, $by = 'ref')
    	$param = new ProgramParameter(
    			// type
    			sprintf($newType, $length, $precision),
    			// io
    			$newInout,
    			// comment
    			'', // no reason for comment that's identical to varname! wastes XML.
    			// varName
    			$name,
    			// value
    			$dataValue,
    			// varing (varying)
    			'off',
    			// dimension
    			$newCount,
    			// by (val or ref [default is rep])
    			$passBy,
    			// is array says the param is an array of identically typed values that should be indexed numerically.
    			$isArray
    	);

    	if ($this->_countersAndCounted) {
    	
    		// some counters were configured
    		// counter item reference was specified.
    		if(isset($this->_countersAndCounted[$name])) { // counter => counted
    		    $param->setParamLabelCounter($name);
        	}
    	    // counted item reference was specified as value in array.
    	    // look for value ($name). if found, counter is key.
       	if ($counter = array_search($name, $this->_countersAndCounted)) { // counter => counted       		 
    		    $param->setParamLabelCounted($counter);
    	    }
    	} //(if there are counters and counted)
    
    	
    	return $param;
    	
    } //(singlePcmlToParam)
	
	/**
	 * given an XML object containing a PCML program definition, return an old toolkit 
	 * style of data description array.
	 * 
	 * @param SimpleXMLElement $xmlObj
	 * @return array
	 */
    public function pcmlToArray(SimpleXMLElement $xmlObj) {
    
    	$dataDescription = array();
    
    	// put structs in its own variable that can be accessed independently.
    	$this->_pcmlStructs = $xmlObj->xpath('struct');
    
    	// looking for ->data and ->struct.
    	$dataElements = $xmlObj->xpath('program/data');
    
    	if ($dataElements) {
    		foreach ($dataElements as $dataElement) {
    
    			$dataDescription[] = $this->singlePcmlToParam($dataElement);
    		}
    	} //(if dataElements)
    
    	return $dataDescription;
    
    } //(pcmlToArray)
    
    
} //(PCML parsing class)
    
    
	
	