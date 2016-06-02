<?php
/* This program is calling a service program (*SRVPGM) function which retrieves QCCSID system value
*/
include_once 'authorization.php';
include_once 'ToolkitService.php';
try {
    $ToolkitServiceObj = ToolkitService::getInstance($db, $user, $pass);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
    exit();
}
$ToolkitServiceObj->setToolkitServiceParams(array('InternalKey' => "/tmp/$user"));
$SysValueName = "QCCSID";
$Err = ' ';
$SysValue = ' ';

$param[] = $ToolkitServiceObj->AddParameterChar('both', 1, 'ErrorCode', 'errcode', $Err);
$param[] = $ToolkitServiceObj->AddParameterChar('both', 10, 'SysValName', 'sysvalname', $SysValueName);
$param[] = $ToolkitServiceObj->AddParameterChar('both', 1024, 'SysValue', 'sysvalue', $SysValue);
$OutputParams = $ToolkitServiceObj->PgmCall('ZSXMLSRV', "ZENDSVR6", $param, NULL, array('func' => 'RTVSYSVAL'));
if (isset($OutputParams['io_param']['sysvalname'])) {
    echo " System value " . $SysValueName . " = " . $OutputParams['io_param']['sysvalue'];
} else
    echo " Operation failed. System value  $SysValueName did not retrieve.";
/*change parameter value and execute again PgmCall()*/
ProgramParameter::UpdateParameterValues($param, array("sysvalname" => "QLANGID"));
$OutputParams = $ToolkitServiceObj->PgmCall('ZSXMLSRV', "ZENDSVR6", $param, NULL, array('func' => 'RTVSYSVAL'));
if (isset($OutputParams['io_param']['sysvalname'])) {
    echo " System value " . $SysValueName . " = " . $OutputParams['io_param']['sysvalue'];
} else {
    echo " Operation failed. System value  $SysValueName did not retrieve.";
}

$ToolkitServiceObj->disconnect();
