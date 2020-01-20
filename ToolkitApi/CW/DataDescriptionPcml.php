<?php
namespace ToolkitApi\CW;

use ToolkitApi\Toolkit;
use ToolkitApi\ToolkitInterface;

/**
 * Additional functionality for parsing PCML
 */
class DataDescriptionPcml extends DataDescription
{
    // array of simple types, PCML to old toolkit. Used in singlePcmlToArray().
    protected $_pcmlTypeMap = array('char'          => I5_TYPE_CHAR,
        'packed'        => I5_TYPE_PACKED,
        // 4 byte float
        'float'         => I5_TYPE_FLOAT,
        // data structure
        'struct'        => I5_TYPE_STRUCT,
        // omit INT from type map because we'll need program logic to determine if short or regular int.
        'zoned'         => I5_TYPE_ZONED,
        // TODO not sure if byte really maps to binary. No one knows what BYTE really does
        'byte'          => I5_TYPE_BYTE,
    );

    // PCML usage mapping
    protected $_pcmlInoutMap = array('input'         => I5_IN,
        'output'        => I5_OUT,
        'inputoutput'   => I5_INOUT,
        // inherit means inherit from parent element, and if no parent element, do INOUT.
        // TODO implement "inherit" more precisely, checking parent element's usage.
        'inherit'       => I5_INOUT,
    );

    // maintain an array of pcml structures
    protected $_pcmlStructs = array();

    /**
     * Constructor takes a PCML string and converts to an array-based old toolkit data description string.
     *
     * @param string $pcml The string of PCML
     * @param ToolkitInterface $connection connection object for toolkit
     * @throws \Exception
     */
    public function __construct($pcml, ToolkitInterface $connection)
    {
        $this->setConnection($connection);

        // Convert PCML from ANSI format (which old toolkit required) to UTF-8 (which SimpleXML requires).

        $encoding = $connection->getConfigValue('system', 'encoding', 'ISO-8859-1'); // XML encoding

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
        $xmlObj = new \SimpleXMLElement($pcml);

        // get root node and make sure it's named 'pcml'
        if(!isset($xmlObj[0]) || ($xmlObj[0]->getName() != 'pcml')) {
            throw new \Exception("PCML file must contain pcml tag");
        }

        $pcmlObj = $xmlObj[0];

        // get program name, path, etc.
        if(!isset($pcmlObj->program) || (!$pcmlObj->program)) {
            throw new \Exception("PCML file must contain program tag");
        }
        $programNode = $pcmlObj->program;

        $pgmAttrs = $programNode->attributes();

        /**
         * sample:
         * <program name="name"
         * [ entrypoint="entry-point-name" ]
         * [ epccsid="ccsid" ]
         * [ path="path-name" ]
         * [ parseorder="name-list" ]
         * [ returnvalue="{ void | integer }" ]
         * [ threadsafe="{ true | false }" ]>
         * </program>
         */

        // let's focus on name, path, and entrypoint, the only attributes likely to be used here.
        $path = (isset($pgmAttrs['path'])) ? $pgmAttrs['path'] : '';
        $entrypoint = (isset($pgmAttrs['entrypoint'])) ? $pgmAttrs['entrypoint'] : '';

        // Note: if entrypoint is supplied, it's the function in a service program. "name" will be the same as entrypoint.
        // if entrypoint is not supplied, name is the actual program name.
        // Therefore, "name" seems somewhat worthless.

        // break up path, separated now by slashes. can be varied lib and pgm.
        // remove the /qsys.lib that may be in front but only if it's simply qualifying another library. qsys may be the actual program library, too.

        $objArray = $this->splitPcmlProgramPath($path);
        if ($objArray['lib']) {
            $pgmName = "{$objArray['lib']}/{$objArray['obj']}";
        } else {
            $pgmName = $objArray['obj'];
        }

        // now add the entrypoint, if any, as a procedure/function.
        if ($entrypoint) {
            // append the entry point enclosed in parentheses.
            $pgmName .= "($entrypoint)";
        }

        // Now create data description array.
        $dataDescriptionArray = $this->pcmlToArray($xmlObj);

        //Change the encoding back to the one wanted by the user, since SimpleXML encodes its output always in UTF-8
        $pgmName = mb_convert_encoding($pgmName, $encoding, 'UTF-8');
        mb_convert_variables($encoding, 'UTF-8', $dataDescriptionArray);

        // call parent's constructor with:
        //$descObj = new DataDescriptionPcml($description, $connection);
        parent::__construct($pgmName, $dataDescriptionArray, $connection);
    }

    /**
     * given a single ->data or ->struct element, return an array containing its contents as old toolkit-style data description.
     *
     * @param \SimpleXmlElement $dataElement
     * @return array
     */
    public function singlePcmlToArray(\SimpleXmlElement $dataElement)
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

        // should all be data
        if ($tagName == 'data') {

            $type = (isset($attrs['type'])) ? (string) $attrs['type'] : '';

            // if a struct then we need to recurse.
            if ($type != 'struct') {

                // regular type (char, int...), not a struct, so the data element's name is just 'name'.
                $nameName = 'Name';
            } else {
                // it IS a struct.

                // old toolkit uses DSName for a data structure's name.
                $nameName = 'DSName';

                $theStruct = null; // init

                // look for matching struct
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
                }

                // if struct was not found, generate error for log
                if (!$theStruct) {
//                    $this->getConnection->logThis("PCML structure '$structName' not found.");
                    return null;
                }

                // if we got here, we found our struct.

                // count can also be defined at the structure level. If so, it will override count from data level)
                if (isset($structAttrs['count'])) {
                    $count = (string) $structAttrs['count'];
                }

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
                        $subElements[] = $this->singlePcmlToArray($subDataElementXmlObj);
                    }
                }
            }

            $length = (isset($attrs['length'])) ? (string) $attrs['length'] : '';
            $precision = (isset($attrs['precision'])) ? (string) $attrs['precision'] : '';

            //$struct = (isset($attrs['struct'])) ? (string) $attrs['struct'] : ''; // if this is pointing to a struct name

            // find CW data type equivalent of PCML data type
            if (isset($this->_pcmlTypeMap[$type])) {
                // a simple type mapping
                $newType = (string) $this->_pcmlTypeMap[$type];
            } elseif ($type == 'int') {
                // one of the integer types. Need to use length to determine which one.
                if ($length == '2') {
                    $newType = I5_TYPE_SHORT;
                } elseif ($length == '4') {
                    $newType = I5_TYPE_INT;
                } else {
                    $newType = ''; // no match
                }
            } else {
                $newtype = '';

            }

            $newInout = (isset($this->_pcmlInoutMap[$usage])) ? (string) $this->_pcmlInoutMap[$usage] : '';

            // create new length using precision if necessary
            if ($precision) {
                $newLength = "$length.$precision";
            } else {
                $newLength = $length;
            }
        }

        // count
        $newCount = 0; // initialize
        $newCountRef = '';
        if (is_numeric($count) && ($count > 0)) {
            $newCount = $count;
        } elseif (is_string($count) && !empty($count)) {
            // count is character, so it's really a countref
            $newCountRef = $count;
        }

        $element = array();

        $element[$nameName] = $name;

        // if not a struct, provide data type.
        if ($type != 'struct') {
            $element['Type'] = $newType;
        }

        if ($newCount) {
            $element['Count'] = $newCount;
        }
        if ($newCountRef) {
            $element['CountRef'] = $newCountRef;
        }
        if ($newLength) {
            $element['Length'] = $newLength;
        }
        if ($newInout) {
            $element['IO'] = $newInout;
        }

        if (count($subElements)) {
            $element['DSParm'] = $subElements;
        }

        return $element;
    }

    /**
     * given an XML object containing a PCML program definition, return an old toolkit style of data description array.
     *
     * @param \SimpleXMLElement $xmlObj
     * @return array
     */
    public function pcmlToArray(\SimpleXMLElement $xmlObj)
    {
        $dataDescription = array();

        // put structs in its own variable that can be accessed independently.
        $this->_pcmlStructs = $xmlObj->xpath('struct');

        // looking for ->data and ->struct.
        $dataElements = $xmlObj->xpath('program/data');

        if ($dataElements) {
            foreach ($dataElements as $dataElement) {

                $dataDescription[] = $this->singlePcmlToArray($dataElement);
            }
        }

        return $dataDescription;
    }
}
