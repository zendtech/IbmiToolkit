<?php
namespace ToolkitApiTest;

use PHPUnit\Framework\TestCase;
use ToolkitApi\Int8Param;
use ToolkitApi\ProgramParameter;

/**
 * Class Int8ParamTest
 * @package ToolkitApiTest
 */
final class Int8ParamTest extends TestCase
{
    public function testCanCreateInstance(): void
    {
        $parameter = new Int8Param('both', 'test comment', 'testVar', 8);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new Int8Param('in', 'comment 2', 'var2', 10);
        $this->assertTrue($parameter instanceof ProgramParameter);

        $parameter = new Int8Param('out', 'comment3', 'var3', 3);
        $this->assertTrue($parameter instanceof ProgramParameter);
    }
}
