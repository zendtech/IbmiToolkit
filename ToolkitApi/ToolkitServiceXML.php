<?php
namespace ToolkitApi;

/**
 * Class XMLWrapper
 *
 * @package ToolkitApi
 */
class XMLWrapper
{
    protected $encoding ='';
    protected $inputXML;
    protected $outputXML;
    protected $cpfErr; 
    protected $error;
    protected $errorText;
    protected $joblog;
    protected $joblogErrors = array(); // cpf=>msgtext array
    protected $ToolkitSrvObj;
    
    // 'system' type can return CPF error codes.
    protected $_cmdTypes = array('cmd', 'rexx', 'system');
    
    // valid "on" values for varying. Can also be 'off' but that's the default so no reason to specify it
    protected $_varyingTypes = array('on', '2', '4');
    
    // whether CW is being used. 
    protected $_isCw = false; 
    
    /**
     * options can be string or array.
     * If string, assume it's "encoding." Otherwise can be array of options.
     * 
     * @param string $options
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct($options ='', $ToolkitSrvObj = null)
    {
        if (is_string($options)) {
            // $options is a string so it must be encoding (assumption for backwards compatibility)
            $this->encoding = $options;
        } elseif (is_array($options)) {
            // $options is an array so check for its various settings.
            if (isset($options['encoding'])) {
                $this->encoding = $options['encoding'];
            }
        }
        
        if ($ToolkitSrvObj) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;
            $this->_isCw = $this->ToolkitSrvObj->getIsCw();
        }
    }

    /**
     * get option from toolkit object
     *
     * @param $optionName
     * @return bool|void
     */
    protected function getOption($optionName) 
    {
        return $this->ToolkitSrvObj->getOption($optionName);
    }
    
    /**
     * are we using CW
     * 
     * @return bool
     */
    protected function getIsCw() 
    {
        return $this->_isCw;
    }
    
    /**
     * Do any processing on parameter properties to prepare for hex, ccsid.
     * Adds these elements to array: processedData, ccsidStr, hexStr
     * 
     * @todo: This works but is somewhat clumsy to use. Refactor as method of ProgramParam object with XML creator injected into it.
     * 
     * @param array $props
     */
    protected function processParamProps(&$props = array())
    {
        $alphanumeric = true; // start with assumption
        // if type is not alphanumeric/char, get out of here! Hex has no purpose for non-char.
        if (isset($props['type']) && $props['type'] && (strtolower(substr($props['type'], -1)) != 'a')) {
            // there's a data type but it doesn't end with 'a' as in '3a'.
            $alphanumeric = false;
        } 
        
        $data = (isset($props['data'])) ? $props['data'] : '';
        $props['processedData'] = $data; // default
        $props['ccsidStr'] = ''; // default
        $props['hexStr'] = ''; // default
        
        $propUseHexValue = (isset($props['useHex'])) ?  $props['useHex'] : null;
        
        // if alphanumeric (therefore a candidate for hex conversion),
        // and hex is set for this specific property,
        // or it's set globally 
        if ($alphanumeric && ($propUseHexValue || $this->getOption('useHex'))) {
            // convert data to hex
            $props['processedData'] = bin2hex($data);
            
            $props['hexStr'] = " hex='on'";
            
            // check for CCSID before and after. We should expect it since we're using hex.
            // Use property-specific value, if available, otherwise global.
            $ccsidBefore = (isset($props['ccsidBefore']) && $props['ccsidBefore']) ? $props['ccsidBefore'] : $this->getOption('ccsidBefore');
            $ccsidAfter = (isset($props['ccsidAfter']) && $props['ccsidAfter']) ? $props['ccsidAfter'] :  $this->getOption('ccsidAfter');
                    
            $props['ccsidStr'] = " before='$ccsidBefore' after='$ccsidAfter'";
        }
    }
    
    /**
     * for strings such as program and library names and commands,
     * apply global hex settings to a string, then return the encoded string.
     * 
     * @param $string
     * @return string
     */
    protected function encodeString($string) 
    {
        if ($this->getOption('useHex')) {
            return bin2hex($string);
        } else {
            return $string;
        }
    }

    /**
     * getPgmTag()
     *
     * return the XML string that specifies a program's name,
     * including potential library, function, and attributes.
     *
     * @param $pgm
     * @param $lib
     * @param $function
     * @return string
     */
    protected function getPgmTag($pgm, $lib, $function)
    {
        // specify opm mode if given
        $opmString = ($this->getOption('v5r4')) ? " mode='opm'" : "";
        
        // get encoded pgm/lib/func names
        $propArray = array();
        $this->processParamProps($propArray);
        
        // if directed to use hex/CCSID, specify "long form" of program tag, with nested name/lib/func tags.
        // If not using hex/CCSID, use "short form" of program tag.
        // Why allow both? For backward compatibility with older (pre-1.6.8) XMLSERVICE that didn't support long form.
        
        if ($propArray['hexStr']) {
        
            // Hex/CCSID, so use long form of pgm tag
            
            // specify function if given
            $encodedFunction = $this->encodeString($function);
            $funcString = ($function) ? " <func{$propArray['ccsidStr']}{$propArray['hexStr']}>$encodedFunction</func>" : "";
            
            //    $pgmtag = "<pgm name='$pgm' lib='$lib'$opmString$funcString>";
            //  split pgm, name, lib into separate lines to can give separate encodings.
            $pgmtag = "<pgm$opmString>
            <name{$propArray['ccsidStr']}{$propArray['hexStr']}>" . $this->encodeString($pgm) . "</name>
            <lib{$propArray['ccsidStr']}{$propArray['hexStr']}>" . $this->encodeString($lib) . "</lib>
            $funcString";
        } else {
            // no hex/CCSID, so use short form of pgm tag
            // (For backward compability with pre-1.6.8 XMLSERVICE)
            $funcString = ($function) ? " func='$function'" : "";
            $pgmtag = "<pgm name='" . $pgm . "' lib='" . $lib . "' " . $opmString . $funcString . ">";
        }
        
        return $pgmtag;
    }
    
    /**
     * Beginning XML tag
     * ensure configured encoding for proper behavior with non-ASCII languages
     * 
     * @return string
     */
    protected function xmlStart()
    {
        return "<?xml version=\"1.0\" encoding=\"$this->encoding\" ?>";
    }
    
    /**
     * For consistently, add commonly used starting and ending tags to input XML
     * 
     * @todo: $xml should be a property of the class (true OO style), then $this->addOuterTags();
     * 
     * @param $inputXml
     * @return string
     */
    protected function addOuterTags($inputXml)
    {
        // start xml with encoding tag
        $finalXml = $this->xmlStart();
        
        // open script tag
        $finalXml .= "\n<script>";
        
        // include override of submit command if specified
        if ($this->getOption('sbmjobCommand')) {
            $sbmJobCommand = $this->getOption('sbmjobCommand');
            $finalXml .= "\n<sbmjob>{$sbmJobCommand}</sbmjob>";
        }
        
        // inner "meat" of the XML
        $finalXml .= "\n$inputXml";
        
        // close script tag
        $finalXml .= "\n</script>";
        
        return $finalXml; 
    }
    
    /**
     * provide XML for disconnecting (beware, reconnecting can be slow)
     * 
     * @return string
     */
    public function disconnectXMLIn()
    {
        $xml = $this->xmlStart(); // start is all we need
        return $xml;
    }

    /**
     * convert xml string to simplexml object
     * 
     * @param $xml
     * @return bool|\SimpleXMLElement
     */
    protected function xmlToObj($xml)
    {
        $xmlobj = simplexml_load_string($xml);
        
        if (!$xmlobj instanceof \SimpleXMLElement) {
            $badXmlLog = '/tmp/bad.xml';
            $this->error = "Unable to parse output XML, which has been logged in $badXmlLog. Possible problems: CCSID, encoding, binary data in an alpha field (use binary/BYTE type instead); if < or > are the problem, consider using CDATA tags.";
            error_log($xml, 3, $badXmlLog);
            return false;
        } 
        
        return $xmlobj;
    }

    /**
     * $info can be 'joblog' (joblog and additional info) or 'conf' (if custom config info set up in PLUGCONF)
     * 
     * @param string $info
     * @param string $jobName
     * @param string $jobUser
     * @param string $jobNumber
     * @return string
     */
    public function diagnosticsXmlIn($info = 'joblog', $jobName = '', $jobUser = '', $jobNumber = '')
    {
        // xml tag
        $xml = $this->xmlStart();
        
        // start of tag and info attribute
        $xml .= "<script><diag info='$info'";
        
        // if specific job requested. (If not then will be current job)
        if ($jobName && $jobUser && $jobNumber) {
            $xml .= " job='$jobName' user='$jobUser' nbr='$jobNumber'";
        }
        
        // end tag
        $xml .= " /></script>";
        
        return $xml;
    }

    /**
     * 
     * @param $xml
     * @return array
     */
    public function parseDiagnosticsXml($xml)
    {
        $xmlobj = $this->xmlToObj($xml);
        
        if (!$xmlobj) {
            return false;
        }
        
        $diag = $xmlobj->diag;
        
        $info = array();
        
        // jobinfo tag is there with job info as attributes
        if (isset($diag->jobinfo)) {
        
            $tempJobInfo = (array) $diag->jobinfo;
            // ensure that all values are strings. Blanks might have remained Simple XML Objects.
            foreach ($tempJobInfo as $name => $value) {
                $info['jobinfo'][$name] = (string) $value; 
            }
        }
        
        $info['version'] = (isset($diag->version)) ? (string) $diag->version : '';
        $info['joblog'] = (isset($diag->joblog)) ? (string) $diag->joblog : '';
        
        return $info;
        
        /**
         * diag
         * version
         * jobinfo
         * stuff
         * joblog
         *
         * <jobinfo job='QSQSRVR' user='QUSER' nbr='174131'>
         * <jobipc></jobipc>
         * <jobipcskey>FFFFFFFF</jobipcskey>
         * <jobname>QSQSRVR</jobname>
         * <jobuser>QUSER</jobuser>
         * <jobnbr>174131</jobnbr>
         * <jobsts>*ACTIVE</jobsts>
         * <curuser>TKITU1</curuser>
         * <ccsid>37</ccsid>
         * <dftccsid>37</dftccsid>
         * <paseccsid>0</paseccsid>
         * <langid>ENU</langid>
         * <cntryid>US</cntryid>
         * <sbsname>QSYSWRK</sbsname>
         * <sbslib>QSYS</sbslib>
         * <curlib></curlib>
         * <syslibl>QSYS QSYS2 QHLPSYS QUSRSYS</syslibl>
         * <usrlibl>XMLSERVICE</usrlibl>
         * <jobcpffind>see log scan, not error list</jobcpffind>
         * </jobinfo>
         *
         * <joblogscan>
         * these repeat
         * <joblogrec>
         * <jobcpf>CPF2105</jobcpf>
         * <jobtime><![CDATA[11/20/12  17:07:19.666282]]></jobtime>
         * <jobtext><![CDATA[ASEIDEN QREXXMN qrexx_main 80 Object OUTREXX in QTEMP type *FILE not found.]]></jobtext>
         * </joblogrec>
         *
         * <joblog job='QSQSRVR' user='QUSER' nbr='174131'>
         * stuff
         * />
         **/
    }
    
    /**
     * $inputOutputParams can be an array of ProgramParameter objects,
     * or
     * an XML string representing parameters already in XML form ("parm" tags).
     * 
     * @param string|array $inputOutputParams
     * @param array $returnParams
     * @param $pgm Not actually optional, but should be the first option. This
     *        is only optional before the first two arguments can be, but the
     *        API was already made and we don't want to break backwards
     *        compatibility. Consider this a warning to call this correctly.
     * @param string $lib blank library means use current/default or library list
     * @param null $function
     * @return string
     */
    public function buildXmlIn($inputOutputParams = NULL, array $returnParams = NULL,
                    $pgm = "",
                    $lib = "",
                    $function = NULL)
    {
        // initialize XML to empty. Could remain blank if no parameters were passed 
        $parametersXml = ''; 
        $returnParametersXml = '';
        
        // input/output params and return params
        $params = array();
        
        // XML can be passed directly in. If a string, assume we received XML directly.
        if (is_string($inputOutputParams)) {
            // XML for params is being provided raw. Use it.
            $parametersXml = $inputOutputParams;
        } elseif (is_array($inputOutputParams) && (!empty($inputOutputParams))) {
            // an array of ProgramParameter objects. Set tagName to 'parm'
            $params['parm'] = $inputOutputParams;
        }
        
        // Prepare to create XML from return param definitions, too, if they exist.
        if (is_array($returnParams) && (!empty($returnParams))) {
            // tagName is 'return'
            $params['return'] = $returnParams;
        }
        
        // process 'parm' and 'return' in the same manner.
        // $path will be 'parm' or 'return', which are used in creating XML path
        foreach ($params as $tagName=>$paramElements) {
            // within each tag name (parm or return), process each param.
            foreach ($paramElements as $param) {
            
                // do parm tag with comment and io.
                $elementProps  = $param->getParamProperties();
                
                // Use comments only when in debug mode. (Reduce XML sent)
                $commentStr = '';
                if (isset($elementProps['comment']) && $this->getOption('debug')) {
                    $commentStr = " comment='{$elementProps['comment']}'";
                }
                
                // only send io if not the default 'both'. (Reduce XML sent)
                $ioStr = '';
                if (isset($elementProps['io']) && $elementProps['io'] != 'both') {
                    $ioStr = " io='{$elementProps['io']}'";
                }
                
                // The XML tag will be named 'parm' or 'return' (value of $path).
                $parametersXml .= "<{$tagName}{$ioStr}{$commentStr}>";
                
                // buildParamXml takes one ProgramParameter object and recursively build XML.
                $parametersXml .= $this->buildParamXml($param);
                
                // end parm tag
                $parametersXml .= "</{$tagName}>\n";
            }
        }
        
        $pgmtag = $this->getPgmTag($pgm, $lib, $function);

        $xmlIn = "{$pgmtag}\n{$parametersXml}{$returnParametersXml}</pgm>";

        return $this->addOuterTags($xmlIn);
    }
    
    /**
     * Do all that's necessary to convert a single parameter into XML.
     * Can call itself recursively for infinitely deep data structures.
     * 
     * @param ProgramParameter $paramElement
     * @return string
     */
    protected function buildParamXml(ProgramParameter $paramElement)
    {
        $paramXml = '';
        $specialOuterDsName = '';
        
        // build start ds tag
        $props  = $paramElement->getParamProperties();
        
        // optional by
        $by = $props['by'];
        $byStr = ($by) ? " by='$by'" : '';
        
        $name = $props['var'];
        
        // optional "array" attribute
        $isArray = $props['array'];
        $isArrayStr = ($isArray) ? " array='on'" : '';
        
        // optional dim (goes best with multi but could exist on its own, too)
        $dim = $props['dim'];
        $dimStr = ($dim) ? " dim='$dim'" : '';
        
        // if dim>0 and array integrity is specified 
        $isMulti = ($dim && $this->getOption('arrayIntegrity'));
        /* if a multiple occurrence DS or scalar field.
        * later we will wrap an additional DS around it
        * The inner DS or scalar field will be a template with a 'dim' attribute to be expanded on output from XMLSERVICE.
        */
        /* if we add an outer DS that's "multi," don't bother to give the inner (original) ds a name.
        * The inner ds will be repeated many times and its name will be replaced on output by numeric indexes.
        *  So no need to include an inner ds name.
        */
        if ($isMulti) {
            $specialOuterDsName = $name;
            $innerName = '';
        } else {
            // not multi. Use normal inner name.
            $innerName = $name; // starts with space, directly following "<data"
        }
        
        // optional dou (do until)
        $dou = $props['dou'];
        $douStr = ($dou) ? " dou='$dou'" : ''; 
        
        // optional len that checks length of the structure/field to which it's appended
        $labelLen = $props['len'];
        $labelLenStr = ($labelLen) ? " len='$labelLen'" : '';
        
        // it's a data structure
        if ($props['type'] == 'ds') {
            // start ds tag with name and optional dim and by
            $innerNameStr = ($innerName) ? " var='$innerName'" : '';
            $paramXml .= "<ds$innerNameStr$dimStr$douStr$isArrayStr$labelLenStr>\n";
            
            // get the subfields
            $dsSubFields = $paramElement->getParamValue();
            if (is_array($dsSubFields) && count($dsSubFields)) {
                // recursively build XML from each data structure subfield
                foreach ($dsSubFields as $subField) {                    
                    $paramXml .= $this->buildParamXml($subField);
                }
            }
            
            // complete the ds tag
            $paramXml .= "</ds>\n";
        } else {
            // not a data structure. a regular single field
            
            $type = $props['type'];
            
            // optional varying
            // varying only inserted if set on/2/4 (default is off, so we can let XMLSERVICE supply the default behavior if off). The less XML we create and send, the more efficient we will be.
            $varyingStr = '';
            if (isset($props['varying'])) {
                // a valid non-off value, so add the varying attribute.
                if (in_array($props['varying'], $this->_varyingTypes)) {
                    $varyingStr = " varying='{$props['varying']}'";
                }
            }
            
            // optional enddo
            $enddo = $props['enddo'];
            $enddoStr = ($enddo) ? " enddo='$enddo'" : '';
            
            // optional setLen to set length value to a numeric field (see 'len' for where the length comes from)
            $labelSetLen = $props['setlen'];
            $labelSetLenStr = ($labelSetLen) ? " setlen='$labelSetLen'" : '';
            
            $data = $props['data'];
            if (is_object($data)) {
                // uh-oh. Something wrong
                echo "data is not a string. type is: $type. data is: " . var_export($data, true) . "<BR>";
            }
            
            // get hex/ccsid information to include in the data tag
            $this->processParamProps($props);
            $ccsidHexStr = "{$props['ccsidStr']}{$props['hexStr']}";
            $processedData = $props['processedData'];
            
            // Google Code issue 11
            // Use short type tag when it's empty
            if ($processedData === '') {
                $dataEndTag = " />";
            } else {
                $dataEndTag = ">$processedData</data>";
            }
            
            // use the old, inefficient "repeat item with sequential numbering of field name" technique if backwards compatibility is desired
            $useOldDimWay = ($dim && !$isMulti);
            if ($useOldDimWay) {
                //  Backward compatibility technique
                // a flattened group of data fields with sequentially increasing names
                foreach (range(1, $dim) as $sequence) {        
                    // only difference is the $sequence inserted after $innerNameStr    
                    // and no $dimStr. Because we're physically repeating the line
                    // And always need the name specified with sequence.    
                    $paramXml .= "<data var='$innerName$sequence' type='$type'$ccsidHexStr$enddoStr$byStr$varyingStr$labelSetLenStr$dataEndTag";
                }
            } else {
                // Not dim or perhaps dim and multi 
                // Use new, efficient, good way. Only one line needed with $dimStr, which XMLSERVICE will expand for us.
                $innerNameStr = ($innerName) ? " var='$innerName'" : ''; // only need name if exists. If not then it's probably a "multi" and doesn't need an inner name.
                $paramXml .= "<data$innerNameStr type='$type'$ccsidHexStr$dimStr$enddoStr$byStr$varyingStr$labelSetLenStr$dataEndTag";
            }
        }
        
        // if a multi-occurrence DS or scalar field, wrap in an identially named "array" DS shell.
        // The "array" indicator will help us parse the results on the way out. 
        if ($isMulti) {
            $paramXml = "\n\n<ds var='$specialOuterDsName' comment='Multi-occur container' array='on'>\n{$paramXml}\n</ds>";        
        } elseif ($dim) {
            // if not multi but regular old-style dim, an ordinary <ds> tag will do to contain all the <data> elements. 
            $paramXml = "\n\n<ds comment='old-style repeated data array container'>\n{$paramXml}\n</ds>";
        }
        
        return $paramXml;
    }
    
    /**
     * given XMLSERVICE type such as 10i0, 5i0, 4f, 4p2, set data to the corresponding PHP type as closely as possible.
     * Omit alpha/string because that's our default.
     * 
     * @param $data
     * @param $xmlServiceType
     */
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
    }
    
    /**
     * Parse a piece of XML representing a single parameter (whether output or return type).
     * Can all itself recursively for infinitely deep data structures.
     * Returns an array containing nested key/value pairs for the parameter.
     * 
     * typical XML program call
     * 
     * <script>
     *   <pgm name='ZZCALL'> 
     *      <parm>
     *       <data type='1A'>a</data>
     *      </parm> 
     *      <parm>
     *       <data type='2s'>23</data>
     *      </parm> 
     *      <return>
     *       <data type='10i0' comment='Could be a data structure, too'>0</data>
     *      </return> 
     *   </pgm> 
     * </script>
     * 
     * @param \SimpleXMLElement $simpleXmlElement
     * @return array
     */
    protected function getSingleParamFromXml(\SimpleXMLElement $simpleXmlElement)
    {
        // if it's too slow to set types, change it to false.
        $setTypes = $this->getIsCw(); // do it if in CW mode because old toolkit did return correct types
        
        $element = array();
        
        // is this a parm, or perhaps a DS??
        $elementType = $simpleXmlElement->getName(); // "name" of the XML tag is element type
        
        // if this is the outer (parm or return) element, go down one level to either ds or data.
        if ($elementType == 'parm' || $elementType == 'return') {
            if (isset($simpleXmlElement->ds)) {
                // get ds beneath.
                $simpleXmlElement = $simpleXmlElement->ds;
            } elseif (isset($simpleXmlElement->data)) {
                // get data beneath.
                $simpleXmlElement = $simpleXmlElement->data;
            }
        }
        
        // now we should be at a ds or data level. let's see what we have now.
        $elementType = $simpleXmlElement->getName();
        
        if ($elementType == 'ds') {
            // call it $ds for convenience
            $ds = $simpleXmlElement;
            
            $subElementArray = array();
            
            /// get info about this data structure
            $outerDsAttributes = $ds->attributes();
            $outerDsName = (string) $outerDsAttributes['var'];
            // determine whether this element is to be considered a simple array
            $outerDsIsArray = (isset($outerDsAttributes['array']) && (strtolower($outerDsAttributes['array']) == 'on'));
            $outerDsIsDim = (isset($outerDsAttributes['dim']) && ($outerDsAttributes['dim'] > 1));
            // look for data elements OR another ds under this ds
            // get every data or ds element under here.
            if ($underDs = $ds->xpath('data|ds')) {
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
                    }
                
                    // is it another ds? 
                    $elType = $subElement->getName(); // getName returns type
                
                    if ($elType == 'ds') {
                        // yes, another DS. Get contents recursively.
                        $data = $this->getSingleParamFromXml($subElement);
                
                        // ignore inner array name because we have outer numbering name.
                        // drill down one level past inner array name.
                        //echo "Array: " . printArray($data) . " with given name: $givenSubelementName and nametouse: $subElementName<BR>";
                        $data = $data[$givenSubelementName];
                
                    } else {
                        // single data element. present its value.
                        $data = (string) $subElement;
                
                        // if $attrs['hex'] == on, decode the data.
                        if (isset($attrs['hex']) && $attrs['hex'] == 'on') {
                            $data = pack("H*" , $data); // reverse of bin2hex()
                        }
                        
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
                
                        // @todo check performance of type casting.
                        if ($setTypes) {
                            $type = $attrs->type;
                            $this->updateType($data, $type);
                        }
                
                    }
                    // add data element to ds
                    $subElementArray[$subElementName] = $data;
                }
            }
            
            // Set ds and its contents to be returned to caller
            // though if in "old" DS mode, don't include DS structure--just add elements individually (single level).
            // check condition: if dataStructureIntegrity global setting or NEED that integrity to interpret array/dim
            if ($this->getOption('dataStructureIntegrity') || $outerDsIsArray || $outerDsIsDim) {
            // keep $outerDsName as index for inner array
            $element[$outerDsName] = $subElementArray;
            } else {
                /* no data structure integrity requested.
                 * Return without a name. Later that will be intepreted as being able to flatten the structure.
                 * This non-inegrity feature included for backward compatibility.
                 */
                 // 
                 $element[''] = $subElementArray;
            }
        } elseif ($elementType == 'data') {
        
            // we're exploring a single outer data element. return simple name/value pair
            $attr = $simpleXmlElement->attributes();
            $dataVarName = (string)$attr->var;
            // name=>value
            $element[$dataVarName] = (string) $simpleXmlElement;
            
            // if $attr['hex'] == on, decode the data.
            if (isset($attr['hex']) && $attr['hex'] == 'on') {
                $element[$dataVarName] = pack("H*" , $element[$dataVarName]); // reverse of bin2hex()
            }
            
            // other types (old toolkit cast output values as correct types)
            if ($setTypes) {
                $type = $attr->type;
                $this->updateType($data, $type); // @todo interesting--shouldn't this be $element[$dataVarName], not $data?
            }
            
            // Similar to code above in "single data element under a DS"
            // if data is a DTS date that needs to be converted to a regular date.
            // and data is not empty or blank (hex ebcdic)
            // @todo could convert 4040 into blanks
            if (isset($attr->dtsdate) && (((string) $attr->dtsdate) == 'on')
             && $element[$dataVarName] && ($element[$dataVarName] != '4040404040404040')) {
                
                if (!isset($dateTimeApi)) {
                    $dateTimeApi = new DateTimeApi($this->ToolkitSrvObj);
                }
                
                // replace DTS date with "real" date
                $element[$dataVarName] = $dateTimeApi->dtsToYymmdd($element[$dataVarName]);
            } 
        } 
        
        return $element;
    } 
    
    /**
     * Given the full XML received from XMLSERVICE program call,
     * parse it into an array of parameter key/value pairs (can be nested if complex DSes) ready to use.
     * The array will contain both 'io_param' (regular output params) and 'retvals' (return param) elements.
     * 
     * sample
     * <parm io='both' comment='PS'>
     *  <ds var='PS' array='on'>
     *      <ds var='PS_0'>
     *          <data var='PS1' type='10a' varying='off'>test1</data>
     *          <data var='PS2' type='10a' varying='off'>test2</data>
     *          <data var='PS3' type='10a' varying='off'>test3</data>
     *      </ds>
     *      <ds var='PS_1'>
     *          <data var='PS1' type='10a' varying='off'>test3</data>
     *          <data var='PS2' type='10a' varying='off'>test4</data>
     *          <data var='PS3' type='10a' varying='off'>test5</data>
     *      </ds>
     *  </ds>
     * </parm>
     * </pgm>
     * 
     * @param $xml
     * @return array
     */
    public function getParamsFromXml($xml)
    {
        // initialize results array to empty arrays for both regular in/out params and return params
        $callResults = array('io_param'=>array(), 'retvals'=>array());
        
        $xmlobj = simplexml_load_string($xml);
        
        // note: outer, ignored node name will be <script> (if successful call)
        //                                     or <report> (if unsuccessful)
        // Outer node is discarded in parsed XML objects.
        
        if (!$xmlobj instanceof \SimpleXMLElement) {
            $badXmlLog = '/tmp/bad.xml';
            $this->error = "Unable to parse output XML, which has been logged in $badXmlLog. Possible problems: CCSID, encoding, binary data in an alpha field (use binary/BYTE type instead); if < or > are the problem, consider using CDATA tags.";
            error_log($xml, 3, $badXmlLog);
            return false;
        }
        
        if (isset($xmlobj->error))
        {
            // capture XML error and joblog.
            // In PgmCall method we will try to get a better error than the XML error.
            $this->error =(string)$xmlobj->error->xmlerrmsg .
                      ' (' . (string)$xmlobj->error->xmlhint . ')';
            $this->joblog = (string)$xmlobj->joblog;
            return false;
        }
        
        // array to provide both 'io_param/parm' and 'retvals/return' parameter types.
        $xmlParamElements = array();        
        
        // get XML elements for regular input/output parameters
        $xmlParamElements['io_param'] = $xmlobj->xpath('/script/pgm/parm');
        // get XML elements for return parameter(s) 
        $xmlParamElements['retvals'] = $xmlobj->xpath('/script/pgm/return');
        
        // read through elements
        foreach ($xmlParamElements as $paramType=>$elements) {
            // within a given element type (parm or return)
            foreach ($elements as $simpleXmlElement) {
                // pass parms into a recursive function to get ds/data elements
                // process and get value(s) from that parm.
                $param = $this->getSingleParamFromXml($simpleXmlElement);
                
                // Append this single element (a key=>value pair) to the proper array.
                // $paramType will be 'io_param' or 'retvals'.
                $paramName = key($param);
                $paramValue = $param[$paramName];
                
                // if it's a flattened data structure (i.e. without data structure integrity),
                // add the individual elements instead of the structure.
                if ($paramName) {
                    $callResults[$paramType][$paramName] = $paramValue;
                } else {
                    // structure name is blank. Append array elements as if they were separate values.
                    // (Backward compability with original new toolkit behavior)
                    if ($paramValue) { // nonempty
                        foreach ($paramValue as $subFieldName=>$subFieldValue) {
                            $callResults[$paramType][$subFieldName] = $subFieldValue;
                        }
                    }
                }
            }
        }
        
        return $callResults;
    }

    /**
     * @param $cmd
     * @return string
     */
    public function getCommandXmlInPase($cmd)
    {
        return $this->buildCommandXmlIn($cmd, 'pase');
    }
    
    /**
     * It's more efficient to run multiple commands with one XMLSERVICE call than to do many calls
     * 
     * @param array $cmd string will be turned into an array
     * @param string $exec
     * @return string
     */
    public function buildCommandXmlIn($cmd, $exec = 'pase')
    {
        $xmlIn = '';
        
        // get hex/ccsid information to pass along on the command.
        $propArray = array();
        $this->processParamProps($propArray);
        
        $ccsidHexStr = "{$propArray['ccsidStr']}{$propArray['hexStr']}"; 
        
        // if a string, make a single-item array from it.
        if (is_string($cmd)) {
            $cmdArray = array($cmd);
        } else {
            // already multiple.
            $cmdArray = $cmd;
        }
        
        foreach ($cmdArray as $oneCmd) {
            // Run PASE utility 'system', which runs an IBM i native command.
            // We call this an "interactive" command because it can return output from DSP* interactive commands.
            // Use double quotes around $cmd so that $cmd can safely contain single quotes.
            if ($exec == 'pase') {
                // -i is nice. @todo test speed
                $oneCmd = '/QOpenSys/usr/bin/system "' . $oneCmd . '"';
            }
            
            // if need to convert to hex, do so.
            $encodedCmd = $this->encodeString($oneCmd);
            
            // with pase and pasecmd is <sh, not <cmd.            
            // inner command depends on what was passed in.
            if ($exec == 'pase' || $exec == 'pasecmd') {
                $xmlIn .= "<sh rows='on'$ccsidHexStr>$encodedCmd</sh>";
            } else {
                // Not a "<sh>" tag. Try one of the cmd exec= command styles.
                
                // check that our exec value is in whitelist.
                // blank is a synonym for default of cmd.
                if ($exec && in_array($exec, $this->_cmdTypes)) {
                    $execStr = " exec='$exec'"; // e.g. <cmd exec='rexx'>
                } else {
                     $execStr = ''; // OK because blank string defaults to cmd on XMLSERVICE side
                }
                $xmlIn .= "<cmd$execStr$ccsidHexStr>$encodedCmd</cmd>";
            }
        }
        
        return $this->addOuterTags($xmlIn);
    }
    
    /**
     * gets any error code and sets it in toolkit.
     * returns true or false depending on interpretation of status
     * 
     * @param $xml
     * @param string $parentTag
     * @return bool
     */
    public function getCmdResultFromXml($xml, $parentTag = 'sh') {
        $this->error = '';
        
        $xmlobj = simplexml_load_string($xml);
        if (!$xmlobj instanceof \SimpleXMLElement) {
            /*bad xml returned*/
            $this->error = "Can't read output xml";
            error_log($xml, 3, '/tmp/bad.xml');
            
            return false;
        }
        
        $retval = true; // assume OK unless error found. Was false, but sh doesn't return success flag.
        $params = $xmlobj->xpath("/script/$parentTag");
        
        foreach ($params as $simpleXMLElement) {
            // Note: there may be multiple ->error tags
            if (isset($simpleXMLElement->error)) {//*** error
                
                // newer way in XMLSERVICE 
                ///bookstore/book[last()]
                
                // get last joblogscan/joblogrec nested tags in the XML section. Returned as one-element array. function 'current' returns the one element.
                $lastJoblogTag = current($simpleXMLElement->xpath("joblogscan/joblogrec[last()]"));
                
                // if last job log tag contained a real CPF-style code, of length 7 (e.g. CPF1234), parsed for us.
                if ($lastJoblogTag && isset($lastJoblogTag->jobcpf) && (strlen($lastJoblogTag->jobcpf) == 7)) {
                    $this->cpfErr = (string) $lastJoblogTag->jobcpf;
                    $this->error = $this->cpfErr; // ->error has ambiguous meaning. Include for backward compatibility.
                    
                    $this->errorText = (string) $lastJoblogTag->jobtext;
                
                    $retval = false;
                    break;
                }
                
                /**
                 * <joblogscan>
                 * <joblogrec>
                 * <jobcpf>CPF1124</jobcpf>
                 * <jobtime><![CDATA[10/11/12  19:33:54.102423]]></jobtime>
                 * <jobtext><![CDATA[Job 127257/ASEIDEN/XTOOLKIT started on 10/11/12 at Job 127257/ASEIDEN/XTOOLKIT submitted.]]></jobtext>
                 * </joblogrec>
                 * <joblogrec>
                 * <jobcpf>*NONE</jobcpf>
                 * <jobtime><![CDATA[10/11/12  19:33:54.102423]]></jobtime>
                 * <jobtext><![CDATA[CALL PGM(XMLSERVICE/XMLSERVICE) PARM('/tmp/ASEIDEN_abc')]]></jobtext>
                 * </joblogrec>
                 * <joblogrec>
                 * <jobcpf>CPF3142</jobcpf>
                 * <jobtime><![CDATA[10/11/12  19:33:54.102423]]></jobtime>
                 * <jobtext><![CDATA[PLUGILE ILECMDEXC 5116 File NONEXIST in library *LIBL not found.]]></jobtext>
                 * </joblogrec>
                 * </joblogscan>
                 */
                // xpath(joblogscan/joblogrec[last()])
                // then ->jobcpf and ->jobtext
                
                // Note: there may be multiple ->error tags
                
                // As of XMLSERVICE 1.60:
                // array element[0] is probably the *** error text.
                // array element[1] may be the CPF code (not likely).
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
            if (strstr(((string)$simpleXMLElement),"*** error")){
                // this message is a generic failure so it's OK.
                $this->error = "Command execute failed. ";
                $retval = false;
                break;
            }
            
            // in the case of interactive commands, there may be no "success" msg.
            if (isset($simpleXMLElement->success)) {
                $retval = true;
                break;
            }
            
            // Older style. Now, +++ success appears in ->success tag
            if (strstr(((string)$simpleXMLElement),"+++ success")) {
                $retval = true;
                break;
            }
        }
        
        return $retval;
    }
    
    /**
     * Get rows from command output.
     * 
     * @param $xml
     * @param string $parent
     * @return array
     */
    public function getRowsFromXml($xml, $parent = 'sh')
    {
        $this->error = '';
        $values = array();
        
        $xmlobj = @simplexml_load_string($xml);
        if (!$xmlobj instanceof \SimpleXMLElement) {
            /* bad xml returned*/
            $this->error = "Can't read output xml";
            return false;
        }
        
        // get all rows of data
        $params = $xmlobj->xpath("/script/$parent/row");
        
        // get data for each row found.
        foreach ($params as $simpleXMLElement) {
            // If hex, get data from script->$parent->hex
            // Then can un-hex (pack) that and do simplexml load string on it, and proceed as below afterward.
            // Examples from XMLSERVICE documentation: http://174.79.32.155/wiki/index.php/XMLSERVICE/XMLSERVICECCSID
            // if hex info was sent back
            
            //  <sh rows='on' hex='on' before='819/37' after='37/819'>
            //      <row><hex>746F74616C2031363636313034</hex></row>
            //  </sh>
                
            
            /*  <cmd exec='rexx' hex='on' before='819/37' after='37/819'>
            *       <success><![CDATA[+++ success RTVJOBA USRLIBL(?) SYSLIBL(?)]]></success>
            *       <row><data desc='USRLIBL'><hex><![CDATA[5147504C20202020202020]]></hex></data></row>
            *       <row><data desc='SYSLIBL'><hex><![CDATA[5153595320202020202020]]></hex></data></row>
            *   </cmd>
            */
            
            
            // if there's a data element, go down one level to it (->data).
            $element = (isset($simpleXMLElement->data)) ? $simpleXMLElement->data : $simpleXMLElement;
            
            // if a hex tag is present, de-hex (pack) the data inside that hex tag; otherwise, use the outer element's value directly.
            $data = (isset($element->hex)) ? pack("H*", (string)$element->hex) : (string)$element;
            
            // process data string received, then add to $values array.
            
            // rows will either be from parent 'cmd' or 'sh'.
            if ($parent == 'cmd') {
                // for RTV* commands,
                // attribute 'desc' of data tag will hold the output field name to which the data belongs.
                $outputFieldName = (string) $element->attributes()->desc;
                
                $data = trim($data, " \n"); // trim spaces and end-of-line chars of data that comes from REXX RTV* commands ('cmd')
                
                $values[$outputFieldName] = $data;
            } else {
                // 'sh'
                $values[] = $data; // no particular index 
            }
        }
        
        if (count($params)==0) {
            $this->error = "No rows";
            $params = $xmlobj->xpath("/script/$parent");
            foreach ($params as $simpleXMLElement) {
                if (strstr(((string)$simpleXMLElement),"+++ no output")) {
                    $this->error = "Command does not return output";
                }
                break;
            }
        }
        
        return $values;
    }

    /**
     * @return string
     */
    public function getErrorCode() {
        return (isset($this->cpfErr)) ? $this->cpfErr : '';
    }

    /**
     * @return string
     */
    public function getErrorMsg() {
        return (isset($this->errorText)) ? $this->errorText : '';
    }

    /**
     * return error message text (?)
     * 
     * @return mixed
     */
    public function getLastError()
    {
        return $this->error;
    }

    /**
     * @return mixed
     */
    public function getLastJoblog()
    {
        return $this->joblog;
    }
}
