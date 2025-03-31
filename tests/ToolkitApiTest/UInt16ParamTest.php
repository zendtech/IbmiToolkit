<?php
namespace ToolkitApiTest;

use PHPUnit\Framework\TestCase;
use ToolkitApi\ProgramParameter;
use ToolkitApi\UInt16Param;

/**
 * Class UInt16ParamTest
 * @package ToolkitApiTest
 */
final class UInt16ParamTest extends TestCase
{
    public function testCanCreateInstance(): void
    {
        $parameter = new UInt16Param('both', 'test comment', 'testVar', 8);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new UInt16Param('in', 'comment 2', 'var2', 10);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new UInt16Param('out', 'comment3', 'var3', 3);
        $this->assertTrue($parameter instanceof ProgramParameter);
    }
}
