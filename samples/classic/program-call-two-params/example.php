<?php
/*
RPG program parameters definition
				PLIST
				PARM                    CODE             10
				PARM                    NAME             10

*/

include_once 'authorization.php';
include_once 'ToolkitService.php';
include_once 'helpshow.php';

//The ToolkitService connection method/function uses either IBM_DB2(default)or ODBC extensions to connect
//to IBM i server. In order to switch to ODBC connection assign an "odbc' value to the $extension varibale
//and make sure that the ODBC extension is enabled in the PHP.INI file.
//The ODBC extension usage in ToolkitService is preferable in 2 tier environment: Zend Server running in Windows/Linux
//and accessing database and/or programs in IBM i server
$extension = 'ibm_db2';
try {
    $ToolkitServiceObj = ToolkitService::getInstance($db, $user, $pass, $extension);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
    exit();
}

$ToolkitServiceObj->setToolkitServiceParams(array('InternalKey' => "/tmp/$user"));
$code = $_POST ['code'];
$desc = ' ';

$param[] = $ToolkitServiceObj->AddParameterChar('both', 10, 'CODE', 'CODE', $code);
$param[] = $ToolkitServiceObj->AddParameterChar('both', 10, 'DESC', 'DESC', $desc);
$result = $ToolkitServiceObj->PgmCall("COMMONPGM", "ZENDSVR6", $param, null, null);
if ($result) {
    showTable($result['io_param']);
} else {
    echo "Execution failed.";
}

/* Do not use the disconnect() function for "state full" connection */
$ToolkitServiceObj->disconnect();
