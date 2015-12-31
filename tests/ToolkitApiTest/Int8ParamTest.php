<?php
namespace ToolkitApiTest;

use ToolkitApi\Int8Param;
use ToolkitApi\ProgramParameter;

class Int8ParamTest extends \PHPUnit_Framework_TestCase
{
    public function testCanCreateInstance()
    {
        $parameter = new Int8Param('both', 'test comment', 'testVar', 8);
        $this->assertTrue($parameter instanceof ProgramParameter);
    }
}