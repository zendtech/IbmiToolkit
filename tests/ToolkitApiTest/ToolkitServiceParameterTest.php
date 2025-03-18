<?php
namespace ToolkitApiTest;

use PHPUnit\Framework\TestCase;
use ToolkitApi\BinParam;
use ToolkitApi\ProgramParameter;

/**
 * Class ProgramParameterTest
 * @package ToolkitApiTest
 */
final class ProgramParameterTest extends TestCase
{
    /**
     * @var ProgramParameter $programParameter
     */
    protected $programParameter;
    protected $type;
    protected $io;
    protected $size;
    protected $comment;
    protected $var;
    protected $data;
    protected $varying;
    protected $dim;
    protected $by;

    protected function setUp(): void
    {
        $this->size = 20;
        $this->type = sprintf('%dB', $this->size);
        $this->io = 'both';
        $this->comment = 'p1';
        $this->var = 'test';
        $this->data = 'off';
        $this->varying = 0;
        $this->dim = '';
        $this->by = false;
        $this->programParameter = new ProgramParameter(
            $this->type,
            $this->io,
            $this->comment,
            $this->var,
            $this->data,
            $this->varying,
            $this->dim,
            $this->by
        );
    }

    public function testCanGetParameterProperties(): void
    {
        $data1 = $this->programParameter->getParamProperities();
        $data2 = array(
            'type' => $this->type,
            'io' => $this->io,
            'comment' => $this->comment,
            'var' => $this->var,
            'data' => $this->data,
            'varying' => $this->varying,
            'dim' => $this->dim,
            'by' => $this->by,
            'array' => false,
            'setlen' => null,
            'len' => null,
            'dou' => '',
            'enddo' => '',
            'ccsidBefore' => '',
            'ccsidAfter' => '',
            'useHex' => false,
        );

        $this->assertEquals($data1, $data2);
    }

    public function testGetParameterPropertiesFacadeMethodReturnsSameAsRealMethod(): void
    {
        $data1 = $this->programParameter->getParamProperities();
        $data2 = $this->programParameter->getParamProperties();

        $this->assertEquals($data1, $data2);
    }


}

/*class DataStructureTest extends \PHPUnit_Framework_TestCase
{

}

class CharParamTest extends \PHPUnit_Framework_TestCase
{

}

class ZonedParamTest extends \PHPUnit_Framework_TestCase
{

}

class PackedDecParamTest extends \PHPUnit_Framework_TestCase
{

}

class Int32ParamTest extends \PHPUnit_Framework_TestCase
{

}

class SizeParamTest extends \PHPUnit_Framework_TestCase
{

}

class SizePackParamTest extends \PHPUnit_Framework_TestCase
{

}

class Int64ParamTest extends \PHPUnit_Framework_TestCase
{

}

class UInt32ParamTest extends \PHPUnit_Framework_TestCase
{

}

class UInt64ParamTest extends \PHPUnit_Framework_TestCase
{

}

class FloatParamTest extends \PHPUnit_Framework_TestCase
{

}

class RealParamTest extends \PHPUnit_Framework_TestCase
{

}

class HoleParamTest extends \PHPUnit_Framework_TestCase
{
    public function testCanCreateInstance()
    {

    }
}*/

/**
 * Class BinParamTest
 * @package ToolkitApiTest
 */
final class BinParamTest extends TestCase
{
    /**
     * @var BinParam $binParam
     */
    protected $binParam;

    /**
     * @var ProgramParameter $programParameter
     */
    protected $programParameter;

    protected function setUp(): void
    {
        $size = 20;
        $this->binParam = new BinParam('both', $size, 'UncodeSample', 'p1', 'test');
        $type = sprintf('%dB', $size);
        $this->programParameter = new ProgramParameter($type, 'both', 'p1', 'test', 'off', 0, '', false);
    }

    public function testCanConvertBinaryToString(): void
    {
        $hex = '74657374';
        $data1 = $this->binParam->bin2str($hex);
        $data2 = $this->programParameter->bin2str($hex);

        $this->assertEquals($data1, $data2);
     }
}
