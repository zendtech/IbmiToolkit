<?php
/*
RPG program parameters definition
	INCHARA        S              1a
	INCHARB        S              1a
	INDEC1         S              7p 4
	INDEC2         S             12p 2
	INDS1          DS
	DSCHARA                      1a
	DSCHARB                      1a
	DSDEC1                       7p 4
	DSDEC2                      12p 2
*/
include_once 'authorization.php';
include_once 'ToolkitService.php';
include_once 'helpshow.php';
try {
    $ToolkitServiceObj = ToolkitService::getInstance($db, $user, $pass);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
    exit();
}
$ToolkitServiceObj->setToolkitServiceParams(array('InternalKey' => "/tmp/$user"));

$IOParam['var1'] = array("in" => "Y", "out" => "");
$param[] = $ToolkitServiceObj->AddParameterChar('both', 1, 'INCHARA', 'var1', $IOParam['var1']['in']);
$IOParam['var2'] = array("in" => "Z", "out" => "");
$param[] = $ToolkitServiceObj->AddParameterChar('both', 1, 'INCHARB', 'var2', $IOParam['var2']['in']);
$IOParam['var3'] = array("in" => "001.0001", "out" => "");
$param[] = $ToolkitServiceObj->AddParameterPackDec('both', 7, 4, 'INDEC1', 'var3', '001.0001');
$IOParam['var4'] = array("in" => "0000000003.04", "out" => "");
$param[] = $ToolkitServiceObj->AddParameterPackDec('both', 12, 2, 'INDEC2', 'var4', '0000000003.04');
$IOParam['ds1'] = array("in" => "A", "out" => "");
$ds[] = $ToolkitServiceObj->AddParameterChar('both', 1, 'DSCHARA', 'ds1', 'A');
$IOParam['ds2'] = array("in" => "B", "out" => "");
$ds[] = $ToolkitServiceObj->AddParameterChar('both', 1, 'DSCHARB', 'ds2', 'B');
$IOParam['ds3'] = array("in" => "005.0007", "out" => "");
$ds[] = $ToolkitServiceObj->AddParameterPackDec('both', 7, 4, 'DSDEC1', 'ds3', '005.0007');
$IOParam['ds4'] = array("in" => "0000000006.08", "out" => "");
$ds[] = $ToolkitServiceObj->AddParameterPackDec('both', 12, 2, 'DSDEC1', 'ds4', '0000000006.08');
//$param[] = array('ds'=>$ds);
$param[] = $ToolkitServiceObj->AddDataStruct($ds);
$result = $ToolkitServiceObj->PgmCall('ZZCALL', "ZENDSVR6", $param, null, null);
if ($result) {
    /*update parameters array by return values */
    foreach ($IOParam as $key => &$element) {
        $element['out'] = $result['io_param'][$key];
    }
    echo "<br>";
    showTableWithHeader(array("Parameter name", "Input value", "Output value"), $IOParam);
} else {
    echo "Execution failed.";
}

/* Do not use the disconnect() function for "state full" connection */
$ToolkitServiceObj->disconnect();
