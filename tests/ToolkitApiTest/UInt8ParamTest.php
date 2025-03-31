<?php
namespace ToolkitApiTest;

use PHPUnit\Framework\TestCase;
use ToolkitApi\ProgramParameter;
use ToolkitApi\UInt8Param;

/**
 * Class UInt8ParamTest
 * @package ToolkitApiTest
 */
final class UInt8ParamTest extends TestCase
{
    public function testCanCreateInstance(): void
    {
        $parameter = new UInt8Param('both', 'test comment', 'testVar', 8);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new UInt8Param('in', 'comment 2', 'var2', 10);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new UInt8Param('out', 'comment3', 'var3', 3);
        $this->assertTrue($parameter instanceof ProgramParameter);
    }
}
