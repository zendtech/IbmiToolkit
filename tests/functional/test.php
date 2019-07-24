<?php
require __DIR__ . '/../../vendor/autoload.php';
use ToolkitApi\Toolkit;

echo "Starting tests...<br>";

$odbcConfig = [
    'dsn' => '*LOCAL',
    'user' => 'WEBTEST',
    'password' => 'T2P6@$62#6',
];
$connection = odbc_connect($odbcConfig['dsn'], $odbcConfig['user'], $odbcConfig['password']);

$toolkit = new Toolkit($connection, null, null, 'odbc');

$param[] = $toolkit::AddParameterPackDec('both', 9, 0, 'SRN', 'SRN', 0);
$result = $toolkit->pgmCall("SCNXTSRN", 'SSC', $param, null, null);
if (!$result) {
    throw new \Exception('Generate new load id failed');
}
$out = $result['io_param'];

$loadId = 0;
foreach ($out as $key => $value) {
    $loadId = $value;
}

if (strlen($loadId) > 0) {
    echo "Successfully called RPG program by passing resource object to toolkit!<br>";
}

$toolkit = new Toolkit($odbcConfig['dsn'], $odbcConfig['user'], $odbcConfig['password'], 'odbc');
$param[] = $toolkit::AddParameterPackDec('both', 9, 0, 'SRN', 'SRN', 0);
$result = $toolkit->pgmCall("SCNXTSRN", 'SSC', $param, null, null);
if (!$result) {
    throw new \Exception('Generate new load id failed');
}
$out = $result['io_param'];

$loadId = 0;
foreach ($out as $key => $value) {
    $loadId = $value;
}
var_dump($loadId);
