<?php 

// new customers don't need the path
require_once("ToolkitService.php");

$tkconn = ToolkitService::getInstance('', '', '');

$tkconn->setOptions(array('stateless'=>true));

$program = "MYPGM";
$library = "MYLIB";
 
 // Add Parameters
$param = array();
$param[] = $tkconn->AddParameterChar('in',8, 'JobNum', 'JobNum', $JobNum);
$param[] = $tkconn->AddParameterChar('in', 30, 'Version', 'Version', $Version);
$param[] = $tkconn->AddParameterChar('both', 1, 'PrcOption', 'PrcOption', $PrcOption);
$param[] = $tkconn->AddParameterChar('out', 1, 'bn1hld2', 'bn1hld2', $_bn1hld2);
$param[] = $tkconn->AddParameterZoned('both', 5, 0, 'how many array elements should return', 'linecount', 4)//$linecount)
                  ->setParamLabelCounter('ABC'); // designates this param as an official counter

$ds[] = $tkconn->AddParameterChar('out', 8, 'bn2job', 'bn2job');
$ds[] = $tkconn->AddParameterChar('out', 3, 'bn2fsseq1', 'bn2fsseq1');
$ds[] = $tkconn->AddParameterChar('out', 3, 'bn2fsseq2', 'bn2fsseq2');
$ds[] = $tkconn->AddParameterChar('out', 30, 'bn2fsver1', 'bn2fsver1');
$ds[] = $tkconn->AddParameterChar('out', 30, 'bn2fsver2', 'bn2fsver2');
$ds[] = $tkconn->AddParameterChar('out', 4, 'bn2seq', 'bn2seq');
$ds[] = $tkconn->AddParameterChar('out', 60, 'bn2line1', 'bn2line1');
$ds[] = $tkconn->AddParameterChar('out', 60, 'bn2line2', 'bn2line2');
$ds[] = $tkconn->AddParameterChar('out', 1, 'bn2bucket1', 'bn2bucket1');
$ds[] = $tkconn->AddParameterChar('out', 1, 'bn2bucket2', 'bn2bucket2');
$ds[] = $tkconn->AddParameterChar('out', 1, 'bn2lincde1', 'bn2lincde1');
$ds[] = $tkconn->AddParameterChar('out', 1, 'bn2lincde2', 'bn2lincde2');
$ds[] = $tkconn->AddParameterChar('out', 1, 'bn2invcde1', 'bn2invcde1');
$ds[] = $tkconn->AddParameterChar('out', 1, 'bn2invcde2', 'bn2invcde2');
$ds[] = $tkconn->AddParameterChar('out', 9, 'bn2invid1', 'bn2invid1');
$ds[] = $tkconn->AddParameterChar('out', 9, 'bn2invid2', 'bn2invid2');
$param[] = $tkconn->AddDataStruct($ds, 'DATADS')
                  ->setParamDimension(10000) // specify maximum array size
                  ->setParamLabelCounted('ABC'); // tells toolkit which counter field to use so it can return only that number of rows.
 
$programReturn = $tkconn->PgmCall($program, $library, $param);

if (!$programReturn) {
    echo 'Error calling program. Code: ' . $tkconn->getErrorCode() .' Msg: '. $tkconn->getErrorMsg();
}

$returnValues = $programReturn['io_param'];

var_dump($returnValues);

