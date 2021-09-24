<?php
namespace ToolkitApi\CW;

use ToolkitApi\DataStructure;
use ToolkitApi\ProgramParameter;
use ToolkitApi\Toolkit;
use ToolkitApi\ToolkitInterface;

/**
 * Object to manage the old toolkit's style of data structure definitions
 */
class DataDescription
{
    protected $_description = array();
    protected $_originalObjName = '';
    protected $_objInfoArray = array(); // 'lib', 'obj', 'func'
    protected $_connection;
    protected $_inputValues = array();
    protected $_pgmOutput = array();
    protected $_isReceiverOnly = false;
    protected $_isSingleLevelSimpleValue = false;
    protected $_pcmlStructs = array(); // save structs in here
    protected $_countRefNames = array(); // names of countRef fields (fields containing counts to return from programs)

    // TODO create methods to handle then make protected
    public $_miscAttributes = array(); // user-defined

    // array of simple types, old to new toolkit, with sprintf-style percent formatting.
    protected $_typeMap = array(I5_TYPE_CHAR    => "%sa",
        I5_TYPE_PACKED  => "%sp%s",
        // 4 byte float
        I5_TYPE_FLOAT   => "4f",
        // data structure
        I5_TYPE_STRUCT  => "ds",
        // int16, 2 bytes
        I5_TYPE_SHORT   => "5i0",
        // int32, 4 bytes
        I5_TYPE_INT     => "10i0",
        I5_TYPE_ZONED   => "%ss%s",
        // TODO not sure if byte really maps to binary. No one knows what BYTE really does
        I5_TYPE_BYTE    => "%sb",
        // hole is exclusive to new toolkit.
        'hole'          => "%sh",
    );

    protected $_inoutMap = array(I5_IN    => 'in',
        I5_OUT   => 'out',
        // INOUT is the same as I5_IN||I5_OUT
        I5_INOUT => 'both',
        I5_BYVAL    => 'val'
    );

    /**
     * Constructor takes an object name (program, data queue, user space) and array-based data description
     * and does some conversions.
     *
     * @param string $objName name of program, data queue, etc. lib/pgm(svcfunc) or the like.
     * @param array $dataDescription array of parameter definitions
     * @param ToolkitInterface $connection connection object for toolkit
     * @internal param I5Error $errorObj during validation we may set properties of this object.
     */
    public function __construct($objName, array $dataDescription, ToolkitInterface $connection)
    {
        if (is_string($objName)) {
            $this->setOriginalObjName($objName);
            $objInfo = splitLibObj($objName);
            $this->setObjInfo($objInfo);
        }

        $this->_description = $dataDescription;

        $this->setConnection($connection);
    }

    /**
     * keep it for safekeeping
     *
     * @param string $originalObjName
     */
    protected function setOriginalObjName($originalObjName = '')
    {
        $this->_originalObjName = $originalObjName;
    }

    /**
     * @return string
     */
    protected function getOriginalObjName()
    {
        return $this->_originalObjName;
    }

    /**
     * When we discover a "CountRef" reference in an old toolkit data description,
     * retain the name for later use.
     *
     * @param $name
     */
    protected function addCountRefName($name)
    {
        // add name to our array.
        $this->_countRefNames[] = $name;
    }

    /**
     * return array of all names of countRef fields that we have found.
     *
     * @return array
     */
    protected function getCountRefNames()
    {
        return $this->_countRefNames;
    }

    /**
     * returns "old toolkit" data description
     *
     * @return array
     */
    public function getOriginalDescription()
    {
        return $this->_description;
    }

    /**
     * resets "old toolkit" data description
     * Accepts one parm, an array.
     *
     * @param $desc
     */
    public function setOriginalDescription($desc)
    {
        $this->_description = $desc;
    }

    /**
     * @param $objInfo
     */
    protected function setObjInfo($objInfo)
    {
        $this->_objInfoArray = $objInfo;
    }

    /**
     * @return array
     */
    public function getObjInfo()
    {
        return $this->_objInfoArray;
    }

    /**
     * @param array $pgmOutput
     */
    protected function setPgmOutput($pgmOutput = array())
    {
        $this->_pgmOutput = $pgmOutput;
    }

    /**
     * @return array
     */
    public function getPgmOutput()
    {
        return $this->_pgmOutput;
    }

    /**
     * @param array $inputValues
     */
    protected function setInputValues($inputValues = array())
    {
        $this->_inputValues = $inputValues;
    }

    /**
     * @param bool $isReceiverOnly
     */
    public function setIsReceiverOnly($isReceiverOnly = false)
    {
        // turn this on when want to use default input variables because we're only RECEIVING data from a program call.
        $this->_isReceiverOnly = $isReceiverOnly;
    }

    /**
     * @return bool
     */
    public function getIsReceiverOnly()
    {
        return $this->_isReceiverOnly;
    }

    /**
     * @param bool $isSingleLevelSimpleValue
     */
    public function setIsSingleLevelSimpleValue($isSingleLevelSimpleValue = false)
    {
        // turn this on when want to accept a parameter that's a single-level description array with a single value.
        $this->_isSingleLevelSimpleValue = $isSingleLevelSimpleValue;
    }

    /**
     * @return bool
     */
    public function getIsSingleLevelSimpleValue()
    {
        return $this->_isSingleLevelSimpleValue;
    }

    /**
     * if null, assume we only want a description, no values.
     *
     * @return array
     */
    public function getInputValues()
    {
        return $this->_inputValues;
    }

    /**
     * @param null $conn
     */
    protected function setConnection($conn = null)
    {
        $this->_connection = $conn;
    }

    /**
     * Return toolkit object that was passed in
     * @return ToolkitInterface
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * Validate that the array is correct with correct data types, etc.
     * @return boolean   True if validates successfully, false if any invalid elements.
     */
    public function validate()
    {
        // TODO add validation as needed
        $objInfo = $this->getObjInfo();

        return true;
    }

    public function splitPcmlProgramPath($path)
    {
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

        if (!$path) {
            throw new \Exception("PCML program path is required.");
        }

        $result = array('lib'=>'', 'obj'=>'');
        $parts = explode('/', $path);
        $numParts = count($parts);

        if ($numParts > 3) {
            throw new \Exception("PCML program path should not have more than 3 slash-delimited parts.");
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
                throw new \Exception("PCML program path has invalid number of parts (<1 or >3).");
                break;
        }

        return $result;
    }

    /**
     * Given an array key name, recursively search the input values array
     * and return the value associated with the key name provided.
     *
     * @param string $searchKey   key to search for
     * @return string|array|false  value found in input array for array key. false if failed
     */
    protected function findValueInArray($searchKey, $valueArray)
    {
        $connection = $this->getConnection();

        // ensure that array is not empty
        if (!count($valueArray)) {
            i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, "Array of input values must not be empty", "Array of input values must not be empty");
            return false;
        }

        foreach ($valueArray as $key=>$value) {
            // a match was found!
            if ($key == $searchKey) {
//                $valToLog = (is_array($value)) ? print_r($value, true) : $value;
//                 $connection->logThis("findValueInArray: searchKey: $searchKey. value found: $valToLog");
                return $value;
            }
        }

        // if failed, return null
        // $connection->logThis("findValueInArray: searchKey: $searchKey. no value found");
        return false;
    }

    /**
     * Process a single parameter definition, converting from old toolkit to new toolkit style.
     *
     * Each description item contains:
     * Name - name of the field
     * Type - type of the field, can be one of Data types
     * Length
     * for CHAR, BYTE - integer describing length. Length can be number or name of the variable holding the length in the data structure.
     * for PACKED, ZONED - string "NUMBER.NUMBER" defining length and precision
     * for STRUCT - array containing data definition of the structure
     * Data structure is defined via PHP as follows:
     * DSName - name of the parameter
     * DSParm (optional) - array of the parameter of the Data structure. Each parameter is defined by a data definition in the same format as described here.
     * for INT, FLOAT - ignored
     * IO - can be one of I/O values (I5_IN|I5_OUT, I5_INOUT). Default is I5_IN
     * count (optional) - repetition count if the field is an array
     * countRef (optional) - reference to the repetition count if the field is an array
     *
     * @param array $oldDataDescription
     * @param null $inputValues
     * @return bool|DataStructure|ProgramParameter array of new or, if a problem, false.
     */
    protected function oldToNewDescriptionItem($oldDataDescription = array(), $inputValues = null)
    {
        // pass in old, return new

        if (!$oldDataDescription || !is_array($oldDataDescription) || !count($oldDataDescription)) {
            return false;
        }

        // $inputValues can be a branch of the overall input tree, if this function was called recursively.

        // if building a "receive-only" data structure, we can use default values.
        $useDefaultValues = $this->getIsReceiverOnly();

        // convert keys to lowercase to make case-insensitive
        // because key case is usually inconsistent with toolkit
        // PHP function array_change_key_case() works on top level of array.
        $old = array_change_key_case($oldDataDescription, CASE_LOWER);

        // can initialize with saved array if not planning to use default values anyway
        if (!$inputValues && !$useDefaultValues) {
            $inputValues = $this->getInputValues();
        }

        $connection = $this->getConnection();
//        $connection->logThis("desc in: " . print_r($old, true) . " value array in: " . print_r($inputValues, true));

//        $connection->logThis("use default values? $useDefaultValues");

        // get count/dim (0 if none)
        $dim = 0; // default
        $nameContainingCount = ''; // default
        if (isset($old['count']) && $old['count']) {
            // This param contains a count value
            $dim = $old['count'];
        } elseif (isset($old['countref']) && $old['countref']) {
            // This param refers to another parameter that contains a count value (or will after program execution).
            // countref takes value from a param value
            // Find the input array entry that countref references, and assign its value to $dim.
            // Note that this value will probably change in an RPG/COBOL program
            // according to the actual number of records returned.
            $nameContainingCount = trim($old['countref']);
            // save this countref name for later use (we will later set a label on the countref field itself)
            $this->addCountRefName($nameContainingCount);

            // get value of actual field referred to by countref. This will be starting count for dim='count', for initializing occur arrays in called program.
            // findInputValueByName() searches recursively for the desired key.
            $countRefValue = $this->findInputValueByName($nameContainingCount, $this->getInputValues()); // getInputValues() goes to original input array to search all params for the countref field

            if (!$countRefValue) {
                // value not found for countRef. Use default of 999 for backwards compatibility with older CW versions.
                // (If the called program sets the field referred to by countRef ('dou/enddo' value)to a number higher than 999, the limit will still be 999.)
                $countRefValue = 999;
            }

            // if key found, value must be numeric (numeric string or integer) because it'll be our count.
            if (is_numeric($countRefValue)) {
                $dim = trim($countRefValue);
            } else {
                // error! not found, or an array or possibly a non-numeric string.
                i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, "Value for CountRef, field $nameContainingCount, must exist and be numeric", "Value for CountRef, field $nameContainingCount, must exist and be numeric");
                return false;
            }
        }

        // input or output. default to input.
        // May have multiple values OR'ed together.
        $io = (isset($old['io']) && $old['io']) ? $old['io'] : I5_IN;
        // get new IO from old. Try one at a time.
        $inOutStuff = $io & I5_INOUT; // two bits. could be IN, OUT, or INOUT
        $byVal = $io & I5_BYVAL;

        $newInout = (isset($this->_inoutMap[$inOutStuff])) ? $this->_inoutMap[$inOutStuff] : 'in';
        $newBy = (isset($this->_inoutMap[$byVal])) ? $this->_inoutMap[$byVal] : ''; // default is blank

        // get name
        $name = (isset($old['name']) && $old['name']) ? $old['name'] : '';

        // get dsname (only present if a data structure)
        $dsName = (isset($old['dsname']) && $old['dsname']) ? $old['dsname'] : '';

        if ($dsName) {
            // data structure detected!

            // See if there is a DSParm containing subfields.
            $dsParm = (isset($old['dsparm']) && $old['dsparm']) ? $old['dsparm'] : '';

            // check that it's an array, which dsParm should be
            if (!is_array($dsParm)) {
                i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'DSParm must be an array', 'DSParm must be an array');
                return false;
            }

            // check that it's non-empty
            if (!count($dsParm)) {
                i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, 'DSParm must not be empty', 'DSParm must not be empty');
                return false;
            }

            /* For the data structure $dsParm,
             * get array of subfield input values
             *
             * The array may contain real values or default values.
             */


            /* $useDefaultValues may be true in two situations:
                    1. We're calling "receive data queue" or other similar function
                       where the data ALWAYS starts empty, and that has requested default values
                    2. We are doing a normal program call but the associated input/output values are not present.
            */
            if ($useDefaultValues) {
                /**
                 * Create values to fill array hinted at by dsParm.
                 */
                $dsData = array();

                // loop through subfields of the data structure

                foreach ($dsParm as $subParm) {

                    // for each subfield, find a name (regular name or data structure name)
                    // and initialize its value ($dsData) to blanks.
                    // This is the process of creating an array of values.
                    $subParm = array_change_key_case($subParm, CASE_LOWER); // for consistency
                    // look for name or dsname
                    $subname = '';
                    if (isset($subParm['name'])) {
                        $subname = $subParm['name'];
                    } elseif (isset($subParm['dsname'])) {
                        $subname = $subParm['dsname'];
                    }
                    // At this point we have a subfield name, derived from
                    if (!$subname) {
                        i5ErrorActivity(I5_ERR_PARAMNOTFOUND, I5_CAT_PHP, "Subfield of $dsName does not have a name itself", "Subfield of $dsName does not have a name itself");
                        return false;
                    }
                    $dsData[$subname] = ''; // default value. TODO Could do 0 or something for numeric
                }
            } else {
                // default values not requested.

                // For this parameter that's a data structure,
                // Look up its value (could be another data structure or a single value)
                // in the input array, based on data structure name.
                $dsData = $this->findValueInArray($dsName, $inputValues);
                // Compare with false since this is what findValueInArray returns on error
                // this will prevent things such as empty arrays from causing errors
                if ($dsData === false) {
                    // ds has no description to match value!
                    i5ErrorActivity(I5_ERR_PARAMNOTFOUND, I5_CAT_PHP, "Requested parameter '$dsName' does not exist in the input data", "Requested parameter $dsName does not exist in the input data");
                    return false;
                }
            }

            /**
             * dim > 1
             * We will create an array of description items.
             * If we are using default values then we need to wrap it in an "array" data structure
             * because later it will come back to us, as output, with all elements generated
             * within that structure.
             *
             * If we are not using default values, let's assume that data for the array has been provided to us
             * fully formed.
             *
             */
            if ($dim > 1) {
                // Array requested.
                // Step 1: expand the data into an array.
                //         This occurs whether we're using default values or not.
                //         If we had real data, assume it's already an array.

                // if we use default values, treat as a single DS but employ the "dim" attribute to tell XMLSERVICE to provide output of dim/array of DS.
                // if we use real values, expect an array of arrays (the fully formed array of data)
                //    to exist, and dsData is the outer array to be looped through.

                // if not default values then expand to array
                $expandToArray = !$useDefaultValues;
                // If we are expanding to a fully data-populated array then we don't need to tell XMLSERVICE
                // to dimension anything via the 'dim' tag.
                // If we plan to pass one array element representing the whole,
                // Then pass the dim value to XMLSERVICE so that XMLSERVICE can provide output
                // that's dim'med to the proper size.
                if ($expandToArray) {
                    $dimTagValue = 0;
                } else {
                    // use the 'dim' tag instead of expanding it with full data
                    $dimTagValue = $dim;
                    // Put an outer array around our single element so it can survive the "foreach" below.
                    $dsData = array(0 => $dsData);
                }

                // Now, whether we used default values or not,
                // Create an array of identical DS'es.
                // We do this by looping through the values,
                // associating each value with its "description" (name, data type, etc.)
                // If default values, make an array of one record, but specify 'dim', too.

                // TODO check that count of ds values (each a ds) does not exceed dim.

                /**
                 * $connection->logThis("array of DS found. dsName: $dsName. dsData: " . print_r($dsData, true) . ' dsParm: ' . print_r($dsParm, true));
                 */

                // Step 2: Associate description with data for each element of the DS
                //                     In this case, we have real data so we loop through each array element.
                //                     If default values, works the same but with only one element.
                // treat each ds and data array separately.
                // loop through input values array for DS
                $dsDataValues = array();
                foreach ($dsData as $numericIndex=>$dsDataSingle) {
                    // work with a single data structure
                    // *** dsParm (structure) will be the same for each ds in the array ****
                    // recursively handle each ds in array and add to dsDataValue array.

                    // Handle one data array element at a time

                    // We will have something like:
                    // dsparm:
                    /*                    array (
                                               array ("Name"=>"PS1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
                                               array ("Name"=>"PS2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
                                               array ("Name"=>"PS3", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10)
                                               );
                    */
                    // dsdata:
                    /*                    array(
                                              array("PS1"=>"test1", "PS2"=>"test2", "PS3"=>"test3"),
                                              array("PS1"=>"test3", "PS2"=>"test4", "PS3"=>"test5")
                                              );
                    */

//                    $connection->logThis("recursively handle a single DS from an array");
                    // pass in full description of DS, including DS name etc., but with count = 1.
                    $singleDsDesc = $old;
                    unset($singleDsDesc['count'],$singleDsDesc['countref']); // single now
                    // On output we will use numeric index (0, 1, 2, 3...) as "name" of array elements
                    //
                    $singleDsDesc['dsname'] = $dsName;
                    // pass in the ds elements and the input data for it.
                    // Get back the new-style description item.
                    $newStyleItem =  $this->oldToNewDescriptionItem($singleDsDesc,array($dsName=>$dsDataSingle));

                    // dim tag tells XMLSERVICE to expand this element to a certain maximum size on output
                    // (after the RPG/COBOL completes) to hold the output array.
                    if ($dimTagValue) {
                        $newStyleItem->setParamDimension($dimTagValue); // dim gets set at inner level

                        // if a "do until" label is supplied, the actual array size will be determined
                        // by the value of a "count" field defined in the RPG/COBOL.
                        // Set a label to identify the count field. The same label will be attached
                        // to the count field to match them up.
                        if (isset($nameContainingCount) && $nameContainingCount) {
                            $newStyleItem->setParamProperties(array('dou'=>$nameContainingCount));
                        } //(if ($nameContainingCount))

                    }
                    $dsDataValues[] = $newStyleItem;
                }

                // create a special "array" ds to house the array of DSes.
                // Very important. The output parser will see this DS labeled [array='true'] and know that we have an array of DSes inside.
                $new = new DataStructure($dsDataValues, $dsName, 0 , false, '', true); // 'true' says this outer structure is an array
            } else {
                // not dim, not an array of DSes. A single DS.
                foreach ($dsParm as $singleParm) {
                    // singleParm is something like this:
                    /**
                     * [Name] => PS1
                     * [IO] => 3
                     * [Type] => 0
                     * [Length] => 10
                     */

                    // do it within ds
                    // Use recursion to get each subfield.
                    $dataValue[] = $this->oldToNewDescriptionItem($singleParm, $dsData);
                }

                // create a ds based on individual parms gathered above.
                $new = new DataStructure($dataValue, $dsName);
            }
        }  else {
            // *** not a data structure. A regular element (could be an array or single value). ***

            // data type. don't check for "empty" or $old[['type'], because 0 is legit (CHAR).
            $type = (isset($old['type'])) ? $old['type'] : '0';
            // get new type from old
            $newType = ($this->_typeMap[$type]) ? $this->_typeMap[$type] : '';

            // get length if there is one
            $length = (isset($old['length']) && $old['length']) ? $old['length'] : '';
            // split into whole and decimal parts. may be like "3" or "3.1"
            list($whole, $dec) = explode('.', $length) + array('', ''); // extra array avoids Undefined index errors

            // TODO: apply same dim/array logic here that was used above for data structure dim/arrays.
            //       But the need is not as urgent here because single-element arrays are not as big (XML-wise)
            //       as data structures. Also, multiple occurrence data structures are more prevalent
            //       than are single-element arrays.
            if ($useDefaultValues) {
                // if $dim, it's an array. Otherwise a single value.
                if ($dim > 1) {
                    // array of dummy values, dimension of $dim
                    // TODO: should be able to use a single ProgramParameter object with the 'dim' value set.
                    //       Or try to fix into a DS mold.
                    $dataValue = array_fill(0, $dim, '');
                    $isArray = true;
                } else {
                    // single dummy value
                    $dataValue = '';
                    $isArray = false;
                    // and leave $dim alone to flow into the ProgramParmeter below.
                }

            } else {
                // real values
                $dataValue = $this->findValueInArray($name, $inputValues);
                // make sure array count doesn't exceed $dim (planned count).
                $isArray = is_array($dataValue);
                if ($isArray) {
                    if (count($dataValue) > $dim) {
                        i5ErrorActivity(I5_ERR_ENDOFOCC, I5_CAT_PHP, "Number of array elements in $name greater than the maximum, $dim, set in the description", "Number of array elements in $name greater than the maximum, $dim, set in the description");
                        return false;
                    }
                }
            }

            // done with $dim. PHP wrapper handles dim differently.
            $dim = 0;

            //$type,  $io, $comment='', $varName = '', $value, $varing = 'off', $dimension = 0, $by = 'ref')
            $new = new ProgramParameter(
            // type
                sprintf($newType, $whole, $dec),
                // io
                $newInout,
                // comment
                $name,
                // varName
                $name,
                // value
                $dataValue,
                // varing
                'off',
                // dimension
                $dim,
                // by (val or ref)
                $newBy,
                // is array says the param is an array
                $isArray
            );
            // $loggableValue = (is_array($dataValue)) ? print_r($dataValue, true) : $dataValue;
            // $connection->logThis("just said new ProgramParameter with name: $name and dataValue: $loggableValue");
        }

        return $new;
    }

    /**
     * Take the previously given data description and match up with values and output params
     * to make a parameter array/object that can be presented to a program or data queue, etc.
     * If any error or validation problem occurs (such as a value not matching data type), return false.
     *
     *
     * @param array $input                 Data input params
     * @param array $description[optional] Description of data, if want to specify explicitly
     *                                     rather than to use description from class member.
     * @return array|boolean Return the param array or, if an error occurs, false.
     */
    public function generateNewToolkitParams($input = array(), $description = null)
    {
        $paramsForNewToolkit = array();

        // store input value array for safekeeping.
        // could be blank.
        $this->setInputValues($input);

        // use specified description if available, otherwise class member description.
        $description = ($description) ? ($description) : $this->_description;

        // do one at a time
        foreach ($description as $key=>$descParam) {
            // convert top-level keys to lower case for consistency.
            $descParam = array_change_key_case($descParam, CASE_LOWER);

            // if an input value wasn't specified for a param definition/description,
            // set a flag to provide default input values (often used as a placeholder).

            $needDefaultInput = false; // default

            // if not globally specified as needing default input values
            if (!$this->getIsReceiverOnly()) {

                // desc name can be given under the index "name" or "dsname".
                if ((isset($descParam['name']) && $descParam['name'])) {
                    $name = $descParam['name'];
                } elseif (isset($descParam['dsname']) && $descParam['dsname']) {
                    $name = $descParam['dsname'];
                } else {
                    i5ErrorActivity(I5_ERR_PHP_TYPEPARAM, I5_CAT_PHP, "Parameter name in description is missing or blank",  "Parameter name in description is missing or blank");
                    return false;
                }

                // if corresponding input param not given, set flag for using default.
                $needDefaultInput = !isset($input[$name]);
            }

            if ($needDefaultInput) {
                // only set this if didn't already turn on receiver-only globally. We don't want to interfere then.
                $this->setIsReceiverOnly(true);
            }

            // Build array of new style parameters
            // as we convert each param from old toolkit style to new.
            $paramsForNewToolkit[] = $this->oldToNewDescriptionItem($descParam);

            if ($needDefaultInput) {
                // revert back to false.
                $this->setIsReceiverOnly(false);
            }
        }

        // Determine if any "CountRef" field references were found.
        // If so, apply "enddo" label to those countref fields that were referenced.
        if ($countRefNames = $this->getCountRefNames()) {
            foreach ($countRefNames as $countRefName) {

                // for each countRefName, find data definition (new toolkit params now)
                // where that fieldname is defined.
                // If not defined, write error to error log.
                // If found, set the enddo label there.
                foreach ($paramsForNewToolkit as $param) {
                    // check name of param for a match
                    if ($param->getParamName() == $countRefName) {
                        if (!$param->isDS()) {
                            // Good, we found matching field name that's a regular field.
                            // Use the count ref name as the label.
                            // Should work as long as there aren't two identically named countRef fields.
                            $param->setParamProperties(array('enddo'=>$countRefName));
                        } else {
                            // no good. Count must be in a regular variable, not a DS.
                            $this->getConnection()->logThis("countRef $countRefName cannot be specified by a data structure. Must be a scalar variable type to hold the count.");
                        }
                        break; // we found what we wanted
                    }
                }
            }
        }

        // TODO: when count/dim is specified, use it as max array size. otherwise error 38 should occur.

        return $paramsForNewToolkit;
    }

    // TODO perhaps callProgram should stand on its own in the regular CW class.
    //      It's not really part of the data description.

    /**
     * Given input values and an output array in a particular format,
     * and having captured the data description and program info
     * in the constructor, and having converted the data desc and interpolated the values,
     * Now we call the program and return an array of output variables.
     *
     * @param array $newInputParams   New-style toolkit array of params including values
     *                                (use generateNewToolkitParams($inputValues) to create it)
     * @return boolean             true if OK, false if it didn't succeed.
     */
    public function callProgram($newInputParams = array())
    {
        $pgmInfo = $this->getObjInfo();
        $pgmName = $pgmInfo['obj'];
        $lib =     $pgmInfo['lib'];
        $func =    $pgmInfo['func'];
        $options = array();

        // if a service program subprocedure (function) is defined
        if ($func) {
            $options['func'] = $func;
        }

        $pgmCallOutput = $this->getConnection()->PgmCall($pgmName,
            $lib,
            $newInputParams,
            // null for returnvalue var because old toolkit doesn't handle return values (as far as we can tell)
            null,
            $options);

        // TODO handle errors
        $conn = $this->getConnection();
        if ($pgmCallOutput) {
            $outputParams = $conn->getOutputParam($pgmCallOutput);
            $this->setPgmOutput($outputParams);
            return true;
        } else {
            return false;
        }
    }

    /**
     * proxies to full function.
     *
     * @return mixed
     */
    public function getOutput()
    {
        return $this->getConnection()->getOutputVarsToExport();
    }

    /**
     * search through entire input array for the value indicated by name.
     *
     * @param $name
     * @param $inputArray
     * @return bool
     */
    protected function findInputValueByName( $name, $inputArray )
    {
        foreach($inputArray as $key=>$value){
            if($key === $name) { // use === because plain == allowed numeric indexes to be equal to names
                return $value;
            }
            if (is_array($value)) {
                if (($result = $this->findInputValueByName($name,$value)) !== false) {
                    return $result;
                }
            }
        }

        return false;
    }
}
