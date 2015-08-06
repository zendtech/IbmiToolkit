<?php
// Register special autoloader
spl_autoload_register(function($class){

    // Define classmap
    $classmap = array(
        'ToolkitApi\db2supp'            => __DIR__ . DIRECTORY_SEPARATOR . 'Db2supp.php',
        'ToolkitApi\httpsupp'           => __DIR__ . DIRECTORY_SEPARATOR . 'httpsupp.php',
        'ToolkitApi\DateTimeApi'        => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\ListFromApi'        => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\UserSpace'          => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\TmpUserSpace'       => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\DataQueue'          => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\SpooledFiles'       => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\JobLogs'            => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\ObjectLists'        => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\SystemValues'       => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\DataArea'           => __DIR__ . DIRECTORY_SEPARATOR . 'iToolkitService.php',
        'ToolkitApi\odbcsupp'           => __DIR__ . DIRECTORY_SEPARATOR . 'Odbcsupp.php',
        'ToolkitApi\ToolkitPcml'        => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitPCML.php',
        'ToolkitApi\ProgramParameter'   => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\DataStructure'      => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\CharParam'          => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\ZonedParam'         => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\PackedDecParam'     => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\Int32Param'         => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\SizeParam'          => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\SizePackParam'      => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\Int64Param'         => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\UInt32Param'        => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\UInt64Param'        => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\FloatParam'         => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\RealParam'          => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\HoleParam'          => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\BinParam'           => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceParameter.php',
        'ToolkitApi\XMLWrapper'         => __DIR__ . DIRECTORY_SEPARATOR . 'ToolkitServiceXML.php',
    );

    $file = $classmap[$class];
    if (is_readable($file)) {
        require_once $file;
        return;
    } else {
        throw new Exception("File Not Found");
    }
});