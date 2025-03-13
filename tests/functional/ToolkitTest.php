<?php

declare(strict_types=1);

namespace ToolkitFunctionalTest;

use PHPUnit\Framework\TestCase;
use ToolkitApi\Toolkit;

final class ToolkitTest extends TestCase
{
    const TEMPORARY_LIBRARY = 'MYLIB1';

    /**
     * @var array
     */
    private $connectionOptions;

    /**
     * @var array
     */
    private $toolkitOptions;

    /**
     * @var bool
     */
    private $mockDb2UsingSqlite;

    public function setUp(): void
    {
        $config = getConfig();

        $this->mockDb2UsingSqlite = $config['db']['mockDb2UsingSqlite'] ?? false;
        $this->connectionOptions = $config['db']['odbc'];
        $this->toolkitOptions = $config['toolkit'];
    }

    /**
     * @throws \Exception
     */
    public function testCanPassPdoOdbcObjectToToolkit()
    {
        $pdo = new \PDO(
            $this->buildDsn('pdo'),
            $this->connectionOptions['username'],
            $this->connectionOptions['password'],
            [
                'platform' => $this->connectionOptions['platform'],
                'platform_options' => [
                    'quote_identifiers' => $this->connectionOptions['platform_options']['quote_identifiers'],
                ]
            ]
);

        $toolkit = new Toolkit($pdo, null, null, 'pdo');
        $toolkit->setOptions($this->toolkitOptions);

        $this->assertInstanceOf(Toolkit::class, $toolkit);
    }

    /**
     * @throws \Exception
     */
    public function testCanPassOdbcResourceToToolkit()
    {
        $connection = odbc_connect($this->buildDsn('odbc'), $this->connectionOptions['username'], $this->connectionOptions['password']);

        if (!$connection) {
            throw new \Exception('Connection failed');
        }
        $toolkit = new Toolkit($connection, null, null, 'odbc');
        $toolkit->setOptions($this->toolkitOptions);

        $this->assertInstanceOf(Toolkit::class, $toolkit);
    }

    /**
     * @throws \Exception
     */
    public function testCanPassOdbcConnectionParametersToToolkit()
    {
        $toolkit = new Toolkit(
            $this->buildDsn('odbc'),
            $this->connectionOptions['username'],
            $this->connectionOptions['password'],
            'odbc'
        );
        $toolkit->setOptions($this->toolkitOptions);

        $this->assertInstanceOf(Toolkit::class, $toolkit);
    }

    /**
     * Builds the appropriate DSN based on configuration.
     */
    private function buildDsn(string $type = 'pdo'): string
    {
        if ($this->mockDb2UsingSqlite) {
            return ($type === 'pdo') ? 'sqlite::memory:' : 'Driver=SQLite3;Database=:memory:';
        }
        return ($type === 'pdo') ? 'odbc:' . $this->connectionOptions['dsn'] : $this->connectionOptions['dsn'];
    }
}
