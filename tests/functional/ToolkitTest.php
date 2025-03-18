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

    protected function setUp(): void
    {
        $config = getConfig();

        $this->connectionOptions = $config['db']['odbc'];
        $this->toolkitOptions = $config['toolkit'];
    }

    /**
     * @throws \Exception
     */
    public function testCanPassPdoOdbcObjectToToolkit(): void
    {
        $pdo = new \PDO(
            'odbc:' . $this->connectionOptions['dsn'],
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
    public function testCanPassOdbcResourceToToolkit(): void
    {
        $connection = odbc_connect($this->connectionOptions['dsn'], $this->connectionOptions['username'], $this->connectionOptions['password']);

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
    public function testCanPassOdbcConnectionParametersToToolkit(): void
    {
        $toolkit = new Toolkit(
            $this->connectionOptions['dsn'],
            $this->connectionOptions['username'],
            $this->connectionOptions['password'],
            'odbc'
        );
        $toolkit->setOptions($this->toolkitOptions);

        $this->assertInstanceOf(Toolkit::class, $toolkit);
    }
}
