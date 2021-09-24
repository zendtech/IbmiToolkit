<?php
include_once 'authorization.php';
include_once 'ToolkitService.php';

try {
    $obj = ToolkitService::getInstance($db, $user, $pass);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
    exit();
}

$obj->setToolkitServiceParams(array('InternalKey' => "/tmp/$user",
                                    'debug'       => true,
                                    'plug'        => "iPLUG32K"));
$cmd = "addlible ZENDSVR";
$obj->CLCommand($cmd);
echo "<pre>";
$rows = $obj->CLInteractiveCommand("DSPLIBL");
/*$rows = $obj->CLInteractiveCommand("WRKSYSVAL OUTPUT(*PRINT)");*/
if (!$rows) {
    echo $obj->getLastError();
} else {
    var_dump($rows);
}

echo "</pre>";

/* Do not use the disconnect() function for "state full" connection */
$obj->disconnect();
