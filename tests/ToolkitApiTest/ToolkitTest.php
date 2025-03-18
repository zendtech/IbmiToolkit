<?php
namespace ToolkitApiTest;

use PHPUnit\Framework\TestCase;
use ToolkitApi\BinParam;
use ToolkitApi\CharParam;
use ToolkitApi\DataArea;
use ToolkitApi\FloatParam;
use ToolkitApi\HoleParam;
use ToolkitApi\Int16Param;
use ToolkitApi\Int32Param;
use ToolkitApi\Int64Param;
use ToolkitApi\Int8Param;
use ToolkitApi\PackedDecParam;
use ToolkitApi\RealParam;
use ToolkitApi\SizePackParam;
use ToolkitApi\SizeParam;
use ToolkitApi\Toolkit;
use ToolkitApi\ToolkitInterface;
use ToolkitApi\UInt16Param;
use ToolkitApi\UInt32Param;
use ToolkitApi\UInt64Param;
use ToolkitApi\UInt8Param;
use ToolkitApi\ZonedParam;
use Exception;

/**
 * Class ToolkitTest
 * @package ToolkitApiTest
 */
final class ToolkitTest extends TestCase
{
    /**
     * @var ToolkitInterface $toolkit
     */
    protected $toolkit;

    protected function setUp(): void
    {
        $this->toolkit = new Toolkit('*LOCAL', '0', 'testPwd', 'http', false);
    }

    public function testCanAddSigned8ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterInt8('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof Int8Param);
    }

    public function testCanAddSigned16ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterInt16('both', 'test comment', 'testVar', 10);

        $this->assertTrue($parameter instanceof Int16Param);
    }

    public function testCanAddSigned32ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterInt32('both', 'test comment', 'testVar', 100);

        $this->assertTrue($parameter instanceof Int32Param);
    }

    public function testCanAddSigned64ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterInt64('both', 'test comment', 'testVar', 1000);

        $this->assertTrue($parameter instanceof Int64Param);
    }

    public function testCanAddUnsigned8ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterUInt8('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof UInt8Param);
    }

    public function testCanAddUnsigned16ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterUInt16('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof UInt16Param);
    }

    public function testCanAddUnsigned32ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterUInt32('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof UInt32Param);
    }

    public function testCanAddUnsigned64ByteIntegerParameter(): void
    {
        $parameter = $this->toolkit->AddParameterUInt64('both', 'test comment', 'testVar', 8);

        $this->assertTrue($parameter instanceof UInt64Param);
    }

    public function testCanAddCharacterParameter(): void
    {
        $parameter = $this->toolkit->AddParameterChar('both', 10, 'CODE', 'CODE', 'code');

        $this->assertTrue($parameter instanceof CharParam);
    }

    public function testCanAddFloatParameter(): void
    {
        $parameter = $this->toolkit->AddParameterFloat('both', 'test comment', 'varName', 'false');

        $this->assertTrue($parameter instanceof FloatParam);
    }

    public function testCanAddRealParameter(): void
    {
        $parameter = $this->toolkit->AddParameterReal('both', 'test comment', 'varName', 'testValue');

        $this->assertTrue($parameter instanceof RealParam);
    }

    public function testCanAddPackedDecimalParameter(): void
    {
        $parameter = $this->toolkit->AddParameterPackDec('both', 7,4, 'INDEC1', 'var3', '001.0001');

        $this->assertTrue($parameter instanceof PackedDecParam);
    }

    public function testCanAddZonedParameter(): void
    {
        $parameter = $this->toolkit->AddParameterZoned('both', 12, 2, 'Check amount', 'amount', '2000.25');

        $this->assertTrue($parameter instanceof ZonedParam);
    }

    public function testCanAddParameterHole(): void
    {
        $parameter = $this->toolkit->AddParameterHole(12, 'hole');

        $this->assertTrue($parameter instanceof HoleParam);
    }

    public function testCanAddBinaryParameter(): void
    {
        $parameter = $this->toolkit->AddParameterBin('both', 20, 'UncodeSample', 'p1', 'test');

        $this->assertTrue($parameter instanceof BinParam);
    }

    public function testCanAddParameterSize(): void
    {
        $size = $this->toolkit->AddParameterSize('test comment', 'varName', 3);

        $this->assertTrue($size instanceof SizeParam);
    }

    public function testCanAddParameterSizePack(): void
    {
        $parameter = $this->toolkit->AddParameterSizePack('test comment', 'varName', 4);

        $this->assertTrue($parameter instanceof SizePackParam);
    }

    public function testCanSetPersistent(): void
    {
        $isPersistent = false;

        $this->toolkit->setIsPersistent($isPersistent);

        $this->assertEquals($isPersistent, $this->toolkit->getIsPersistent());
    }

    public function testCanReturnScriptAbsolutePath(): void
    {
        $path = Toolkit::classPath();

        $this->assertEquals($path, $this->toolkit->classPath());
    }

    public function testCanGetPhpOperatingSystem(): void
    {
        $os = php_uname('s');

        $this->assertEquals($os, $this->toolkit->getPhpOperatingSystem());
    }

    public function testCanTellIfPhpIsRunningOnIbmI(): void
    {
        $isRunningOnIbmI = (php_uname('s') === 'OS400');

        $this->assertEquals($isRunningOnIbmI, $this->toolkit->isPhpRunningOnIbmI());
    }

    public function testDatabaseNameOrResourceIsNotBoolean(): void
    {
        $resource = false;
        $this->expectException(Exception::class);
        new Toolkit($resource);
    }

    public function testDatabaseNameOrResourceIsNotFloat(): void
    {
        $resource = 1.81;
        $this->expectException(Exception::class);
        new Toolkit($resource);
    }

    public function testDatabaseNameOrResourceIsNotObject(): void
    {
        $resource = new DataArea();
        $this->expectException(Exception::class);
        new Toolkit($resource);
    }

    public function testDatabaseNameOrResourceIsNotInteger(): void
    {
        $resource = 12;
        $this->expectException(Exception::class);
        new Toolkit($resource);
    }

    public function testDatabaseNameOrResourceIsNotArray(): void
    {
        $resource = array(1, 2, 3);
        $this->expectException(Exception::class);
        new Toolkit($resource);
    }

    public function testDatabaseNameOrResourceIsNotNull(): void
    {
        $resource = null;
        $this->expectException(Exception::class);
        new Toolkit($resource);
    }

}
