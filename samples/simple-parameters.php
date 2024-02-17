<?php

// Example with simple parameters.
// This example contains no data structures, arrays, or procedures. Those are illustrated in other examples.

require_once('ToolkitService.php');

// Connect to toolkit using DB2 credentials (can also leave blank for default authority)
// There are also other transports available.. PDO_ODBC and local, to name two (see examples).
$conn = ToolkitService::getInstance('*LOCAL', 'MYUSER', 'MYPASS');

// set stateless mode for easy testing (no 'InternalKey' needed).
$conn->setOptions(array('stateless'=>true));

// Define several input/output params
// (To see all available data types, see: samples/data-types.md) 
$params = []; // start with empty array
$params[] = $conn->AddParameterChar('in', 1,'Division', 'DIV', 'A');
$params[] = $conn->AddParameterChar('in', 6,'Product', 'PROD', '123456');
$params[] = $conn->AddParameterPackDec('both', 7, 2, 'Quantity', 'QTY', '4.53');
$params[] = $conn->AddParameterZoned('out', 5, 2, 'Price', 'PRICE', '0');

// Call program. 
// In this example, assume your program is MYLIB/MYPGM.
$result = $conn->PgmCall('MYPGM', 'MYLIB', $params);

if (!$result) {
    echo 'Error calling program. Code: ' . $conn->getErrorCode() . ' Msg: ' . $conn->getErrorMsg();
} else {
    echo 'Called program successfully.<BR><BR>';
    // Parameter values that are I/O type "out" or "both" will be available in $result['io_param']. 
    echo 'Input/output params: QTY: ' . $result['io_param']['QTY'] . ' PRICE: ' . $result['io_param']['PRICE'] . '<BR>'; 
}

/* 
The above will output something like:

Called program successfully.

Input/output params: QTY: 4.53 PRICE: 0.00

*/
