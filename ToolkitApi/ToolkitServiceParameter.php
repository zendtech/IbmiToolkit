<?php
namespace ToolkitApi;

/**
 * Class ProgramParameter
 *
 * @package ToolkitApi
 */
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

    // @todo do setlen for other program param types, too

    /**
     * @param $type
     * @param $io
     * @param string $comment
     * @param string $varName
     * @param $value
     * @param string $varying
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     * @param int|null $labelSetLen
     * @param int|null $labelLen
     * @param string $ccsidBefore
     * @param string $ccsidAfter
     * @param bool $useHex
     * @throws \Exception
     */
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

    }

    /**
     * @return array
     */
    public function getParamProperities()
    {
        // if varName is empty then set a fallback unique varName.
        if (!$this->varName) {
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
    }

    /**
     * spell it right
     *
     * @return array
     */
    public function getParamProperties()
    {
        return $this->getParamProperities();
    }

    /**
     * set a parameter's properties via an key=>value array structure. Choose any properties to set.
     * map the XML keywords (usually shorter than true class property names) to the class property names.
     *
     * @param array $properties
     */
    public function setParamProperties($properties = array())
    {
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
            }
        }
    }

    /**
     * for unnamed data elements, to provide a unique name initialized by PgmCall method, so make public.
     */
    static function initializeFallbackVarName()
    {
        self::$_fallbackNameSequence = 0; //static variable
    }

    /**
     * for unnamed data elements, provide a unique name: var0, var1, var2...
     *
     * @return string
     */
    protected function getFallbackVarName()
    {
        $varName =  'var' . self::$_fallbackNameSequence++;

        return $varName;
    }

    /**
     * if $value is an array, but not yet a data structure, make a data structure of the array elements.
     *
     * @param $type
     * @param $io
     * @param $comment
     * @param $varName
     * @param $value
     * @param $varying
     * @param $dimension
     * @param $by
     * @param $isArray
     * @param $labelSetLen
     * @param $labelLen
     * @param $ccsidBefore
     * @param $ccsidAfter
     * @param $useHex
     * @return array
     * @throws \Exception
     */
    protected function handleParamValue($type, $io, $comment, $varName, $value, $varying, $dimension, $by, $isArray,
                                        $labelSetLen, $labelLen, $ccsidBefore, $ccsidAfter, $useHex)
    {
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
                throw new \Exception("Empty array passed as value for {$varName}");
            }
        }

        return $value;
    }

    /**
     * @param $value
     */
    public function setParamValue($value)
    {
        $this->data = $value;
    }

    /**
     * set "len label"
     *
     * @param $labelLen
     */
    public function setParamLabelLen($labelLen)
    {
        $this->labelLen = $labelLen;
    }

    /**
     * @param $name
     */
    public function setParamName($name)
    {
        $this->varName = $name;
    }

    /**
     * @return string
     */
    public function getParamName()
    {
        return $this->varName;
    }

    /**
     * @return array
     */
    public function getParamValue()
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getParamDimension()
    {
        return $this->dimension;
    }

    /**
     * @param int $dimension
     * @return $this
     */
    public function setParamDimension($dimension = 0)
    {
        $this->dimension =  $dimension;
        return $this; // fluent interface
    }

    /**
     * for a data structure or other item in an array, set the label for "do until"
     *
     * @param string $label
     * @return $this
     */
    public function setParamLabelCounted($label = '')
    {
        $this->labelDoUntil =  $label;
        return $this; // fluent interface
    }

    /**
     * for a numeric counter field that will determine how many array elements return
     * from a program call, set the label for "enddo". Links up with the Dou label
     * given to the dim'med array element itself.
     *
     * @param string $label
     * @return $this
     */
    public function setParamLabelCounter($label = '')
    {
        $this->labelEndDo =  $label;
        return $this; // fluent interface
    }

    /**
     * "CCSID before" means how to convert to CCSID on the way in to XMLSERVICE, when needed
     *
     * @param string $ccsidBefore
     * @return $this
     */
    public function setParamCcsidBefore($ccsidBefore = '')
    {
        $this->_ccsidBefore = $ccsidBefore;
        return $this; // fluent interface
    }

    /**
     * "CCSID after" means how to convert to CCSID on the way out from XMLSERVICE, when needed
     *
     * @param string $ccsidAfter
     * @return $this
     */
    public function setParamCcsidAfter($ccsidAfter = '')
    {
        $this->_ccsidAfter = $ccsidAfter;
        return $this; // fluent interface
    }

    /**
     * "useHex" controls whether the data will be converted to/from hex
     *
     * @param bool $useHex
     * @return $this
     */
    public function setParamUseHex($useHex = false)
    {
        $this->_useHex = $useHex;
        return $this; // fluent interface
    }

    /**
     * @param string $comment
     * @return $this
     */
    public function setParamComment($comment = '')
    {
        $this->comment = $comment;
        return $this; // fluent interface
    }

    /**
     * @param string $io
     * @return $this
     */
    public function setParamIo($io = 'both')
    {
        $this->io = $io;
        return $this; // fluent interface
    }

    /**
     * @return bool
     */
    public function isDS()
    {
        if ($this->type == "ds") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * setReturnParameter ...
     */
    public function setReturnParameter()
    {
        $this->returnParameter = true;
    }

    /**
     * @return bool
     */
    public function isReturn()
    {
        return $this->returnParameter;
    }

    /**
     * bin2str is used by the 5250 Bridge. It converts a hex string to character string
     * while cleaning up unexpected characters.
     * Original comment: "can not be public. Return XML does not return a type of values."
     *
     * @param $hex_data
     * @return string
     */
    static function bin2str( $hex_data )
    {
        $str='';
        $upto = strlen($hex_data);
        for ($i = 0; $i < $upto; $i+= 2) {
            $hexPair = $hex_data[$i].$hex_data [$i+1];
            /* if hex value starts with 0 (00, 0D, 0A...),
             * assume it's nondisplayable.
             * Replace with a space (hex 20)
             */
             if ($hex_data[$i] == '0') {
                 $hexPair = '20'; // space
             } //(if($hex_data[$i] == '0') )
             // break;
             $str.= chr(hexdec($hexPair));
             //$str.= chr(hexdec($hex_data[$i].$hex_data [$i+1]));
        }

        return $str;
     }
}

/**
 * Class DataStructure
 *
 * @package ToolkitApi
 */
class DataStructure extends ProgramParameter
{

    /**
     * v1.4.0 added $comment as arg 5, in place of the obsolete $isReturnParam argument.
     * Data structure return values didn't work properly before 1.4.0 anyway.
     *
     * @param $paramsArray
     * @param string $struct_name
     * @param int $dim
     * @param string $comment
     * @param string $by
     * @param bool $isArray
     * @param int|null $labelLen
     * @param string $io
     */
    function __construct($paramsArray, $struct_name ="DataStruct", $dim=0, $comment = '', $by='', $isArray=false, $labelLen = null, $io = 'both')
    {
        parent::__construct("ds", $io, $comment, $struct_name, $paramsArray, 'off', $dim, $by, $isArray, null, $labelLen);
    }
}

/**
 * Class CharParam
 *
 * CharParam can require hex/ccsid conversions, which other types don't.
 *
 * @package ToolkitApi
 */
class CharParam extends ProgramParameter
{
    /**
     * @todo if array. call charparm 5 times with fake field name and coming out, too. (?)
     *
     * @param $io
     * @param $size
     * @param string $comment
     * @param string $varName
     * @param $value
     * @param string $varying
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     * @param string $ccsidBefore
     * @param string $ccsidAfter
     * @param bool $useHex
     */
    function __construct($io, $size, $comment, $varName = '', $value , $varying = 'off', $dimension = 0, $by='', $isArray = false,
                         $ccsidBefore = '', $ccsidAfter = '', $useHex = false)
    {
        $type = sprintf("%dA", $size);
        parent::__construct($type, $io, $comment, $varName, $value, $varying, $dimension, $by, $isArray,
                             null, null, $ccsidBefore, $ccsidAfter, $useHex);
        return $this; // fluent interface
    }
}

/**
 * Class ZonedParam
 *
 * @package ToolkitApi
 */
class ZonedParam extends ProgramParameter
{

    /**
     * @param $io
     * @param $length
     * @param string $scale
     * @param string $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     */
    function __construct($io, $length, $scale, $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false)
    {
        $type = sprintf("%ds%d", $length, $scale);
        parent::__construct($type, $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, null, null, '', '', false);
        return $this;
    }
}

/**
 * Class PackedDecParam
 *
 * @package ToolkitApi
 */
class PackedDecParam extends ProgramParameter
{

    /**
     * @param $io
     * @param $length
     * @param string $scale
     * @param string $comment
     * @param string $varName
     * @param mixed $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     * @param int|null $labelSetLen
     */
    function __construct($io, $length, $scale, $comment,  $varName = '', $value, $dimension=0, $by='', $isArray = false,  $labelSetLen = null)
    {
        $type = sprintf("%dp%d", $length, $scale);
        parent::__construct( $type, $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, $labelSetLen, null, '', '', false);
        return $this;
    }
}

/**
 * Class Int32Param
 *
 * @package ToolkitApi
 */
class Int32Param extends ProgramParameter
{
    /**
     * @param $io
     * @param $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     * @param int|null $labelSetLen
     */
     function __construct($io, $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false, $labelSetLen = null)
     {
        parent::__construct('10i0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, $labelSetLen, null);
        return $this;
     }
}

/**
 * Class SizeParam
 *
 * @package ToolkitApi
 */
class SizeParam extends Int32Param
{

    /**
     * @param $comment
     * @param string $varName
     * @param string $labelSetLen
     */
     function __construct($comment, $varName = '', $labelSetLen)
     {
        parent::__construct('in', $comment, $varName, 0,  0, '', false, $labelSetLen);
        return $this;
     }
}

/**
 * Class SizePackParam
 * size can be a pack 5 decimal, too!
 *
 * @package ToolkitApi
 */
class SizePackParam extends PackedDecParam
{
    /**
     * @param $comment
     * @param string $varName
     * @param string $labelSetLen
     */
     function __construct($comment, $varName = '', $labelSetLen)
     {
        parent::__construct('in', 5, 0, $comment, $varName, 0, 0, '', false, $labelSetLen);
        return $this;
     }
}

/**
 * Class Int64Param
 *
 * @package ToolkitApi
 */
class Int64Param extends ProgramParameter
{
    /**
     * @param $io
     * @param $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     */
    function __construct($io, $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false)
    {
        parent::__construct('20i0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray);
        return $this;
    }
}

/**
 * Class UInt32Param
 *
 * @package ToolkitApi
 */
class UInt32Param extends ProgramParameter
{
    /**
     * @param $io
     * @param $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     */
     function __construct($io, $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false)
     {
        parent::__construct('10u0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray);
        return $this;
     }
}

/**
 * Class UInt64Param
 *
 * @package ToolkitApi
 */
class UInt64Param extends ProgramParameter
{
    /**
     * @param $io
     * @param $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     */
    function __construct($io, $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false)
    {
        parent::__construct('20u0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray);
        return $this;
    }
}

/**
 * Class FloatParam
 *
 * @package ToolkitApi
 */
class FloatParam extends ProgramParameter
{
    /**
     * @param $io
     * @param $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     */
    function __construct($io, $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false)
    {
        parent::__construct('4f', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray);
        return $this;
    }
}

/**
 * Class RealParam
 *
 * @package ToolkitApi
 */
class RealParam extends ProgramParameter
{
    /**
     * @param $io
     * @param $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     */
    function __construct($io, $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false)
    {
        parent::__construct('8f', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray);
        return $this;
    }
}

/**
 * Class HoleParam
 * "hole" means, don't return the data where the hole is defined. A way to ignore large amounts of data
 *
 * @package ToolkitApi
 */
class HoleParam extends ProgramParameter
{
    /**
     * @param $length
     * @param string $comment
     */
    function __construct($length, $comment = 'hole')
    {
        $type = sprintf("%dh", $length);
        // note, no varname or value needed because data will be ignored.
        parent::__construct($type, 'in', $comment, '', '', 'off', 0, '', '' );
        return $this;
    }
}

/**
 * Class BinParam
 * binary parameter
 *
 * @package ToolkitApi
 */
class BinParam extends ProgramParameter
{
    /**
     * @param $io
     * @param $size
     * @param string $comment
     * @param string $varName
     * @param $value
     * @param int $dimension
     * @param string $by
     * @param bool $isArray
     */
    function __construct($io, $size , $comment, $varName = '', $value, $dimension=0, $by='', $isArray = false)
    {
        $type = sprintf("%dB", $size);
        parent::__construct($type,  $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray);
        return $this;
    }

    /**
     * @param $hex_data
     * @return string
     */
    static function bin2str($hex_data)
    {
        return parent::bin2str($hex_data);
    }
}
