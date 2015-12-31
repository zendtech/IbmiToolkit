<?php
namespace ToolkitApiTest;

use ToolkitApi\ProgramParameter;
use ToolkitApi\UInt8Param;

class UInt8ParamTest extends \PHPUnit_Framework_TestCase
{
    public function testCanCreateInstance()
    {
        $parameter = new UInt8Param('both', 'test comment', 'testVar', 8);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new UInt8Param('in', 'comment 2', 'var2', 10);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new UInt8Param('out', 'comment3', 'var3', 3);
        $this->assertTrue($parameter instanceof ProgramParameter);
    }
}