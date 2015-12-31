<?php
namespace ToolkitApiTest;

use ToolkitApi\Int16Param;
use ToolkitApi\Int8Param;
use ToolkitApi\Toolkit;
use ToolkitApi\UInt16Param;
use ToolkitApi\UInt8Param;

/**
 * Class ToolkitTest
 * @package ToolkitApiTest
 */
class ToolkitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Toolkit $toolkit
     */
    protected $toolkit;

    public function setUp()
    {
        $this->toolkit = new Toolkit('*LOCAL', '0', 'testPwd', 'http', false);
    }

    public function testCanAddSigned8ByteIntegerParameter()
    {
        $parameter = $this->toolkit->AddParameterInt8('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof Int8Param);
    }

    public function testCanAddSigned16ByteIntegerParameter()
    {
        $parameter = $this->toolkit->AddParameterInt16('both', 'test comment', 'testVar', 10);

        $this->assertTrue($parameter instanceof Int16Param);
    }

    public function testCanAddUnsigned8ByteIntegerParameter()
    {
        $parameter = $this->toolkit->AddParameterUInt8('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof UInt8Param);
    }

    public function testCanAddUnsigned16ByteIntegerParameter()
    {
        $parameter = $this->toolkit->AddParameterUInt16('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof UInt16Param);
    }
}