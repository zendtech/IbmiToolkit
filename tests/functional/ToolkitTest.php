<?php

declare(strict_types=1);

namespace ToolkitFunctionalTest;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ToolkitApi\Toolkit;

final class ToolkitTest extends TestCase
{
    const TEMPORARY_LIBRARY = 'MYLIB1';

    private $connectionOptions;

    public function setUp(): void
    {
        $this->connectionOptions = [
            'dsn' => '*LOCAL',
            'user' => '*CURRENT',
            'password' => '',
        ];

        $rpgSource = dirname(__DIR__ . '../') . DIRECTORY_SEPARATOR . 'ADDONE.RPGLE';
        $this->runShellCommand("system \"CRTLIB LIB(" . self::TEMPORARY_LIBRARY . ")");
        $this->runShellCommand("system \"CRTBNDRPG PGM(" . self::TEMPORARY_LIBRARY . "/ADDONE) SRCSTMF('$rpgSource')\"");
    }

    public function tearDown(): void
    {
        $this->runShellCommand("system \"DLTLIB LIB(" . self::TEMPORARY_LIBRARY . ")");
    }

    private function runShellCommand(string $command): string
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(0);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    public function testCanCallRpgProgramByPassingOdbcResourceObjectToToolkit()
    {
        $connection = odbc_connect($this->connectionOptions['dsn'], $this->connectionOptions['user'], $this->connectionOptions['password']);

        if (!$connection) {
            throw new \Exception('Connection failed');
        }
        $toolkit = new Toolkit($connection, null, null, 'odbc');

        $param[] = $toolkit::AddParameterPackDec('both', 10, 0, 'NUMBER', 'NUMBER', 0);
        $result = $toolkit->pgmCall("ADDONE", self::TEMPORARY_LIBRARY, $param, null, null);
        if (!$result) {
            throw new \Exception('Program call failed');
        }
        $out = $result['io_param'];

        $loadId = 0;
        foreach ($out as $key => $value) {
            $loadId = $value;
        }
        $this->assertTrue(strlen($loadId) > 0);
    }

//    public function testCanCallRpgProgramByPassingOdbcConnectionParametersToToolkit()
//    {
//        $toolkit = new Toolkit($this->connectionOptions['dsn'], $this->connectionOptions['user'], $this->connectionOptions['password'], 'odbc');
//
//        $param[] = $toolkit::AddParameterPackDec('both', 10, 0, 'NUMBER', 'NUMBER', 0);
//        $result = $toolkit->pgmCall("ADDONE", self::TEMPORARY_LIBRARY, $param, null, null);
//        if (!$result) {
//            throw new \Exception('Program call failed');
//        }
//        $out = $result['io_param'];
//
//        $loadId = 0;
//        foreach ($out as $key => $value) {
//            $loadId = $value;
//        }
//        $this->assertTrue(strlen($loadId) > 0);
//    }

}
