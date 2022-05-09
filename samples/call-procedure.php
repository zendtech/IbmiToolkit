<?php
// from https://www.seidengroup.com/2012/12/27/service-program-procedures-with-php-toolkit-for-ibm-i/

require_once('ToolkitService.php');

// connect to toolkit using DB2 credentials (can also leave blank for default authority)
try {
    $conn = ToolkitService::getInstance('*LOCAL', 'MYUSER', 'MYPASS');
} catch (Exception $e) {
    // Determine reason for failure.
    // Probably database authentication error or invalid or unreachable database.
    $code = $e->getCode();
    $msg = $e->getMessage();

    switch ($code) {
        case 8001:
            // "Authorization failure on distributed database connection attempt"
            // Usually means a wrong DB2 user or password
            echo 'Could not connect due to wrong user or password.';
            break;
        case 42705:
            echo 'Database not found. Try WRKRDBDIRE to check.';
            break; 
        default:
            echo 'Could not connect. Error: ' . $code . ' ' . $msg;
            break;
    } //(switch)
    die; // couldn't connect...handle this however you wish     
} //(try/catch)

// set stateless mode for easy testing (no 'InternalKey' needed).
// (setOptions() introduced in v1.4.0)
$conn->setOptions(array('stateless'=>true));

/* If you wish to test this script but you don't have a real service program,
 * use parseOnly and parseDebugLevel as shown below.
 * No program will be called and you'll get your original values back.
 * Simply uncomment the next line to try this great testing feature of the toolkit.
*/
//$conn->setOptions(array('parseOnly'=>true, 'parseDebugLevel'=>1));

// define several input/output params
$params = []; // start with empty array
$params[] = $conn->AddParameterChar('in', 1,'Division', 'DIV', 'A');
$params[] = $conn->AddParameterChar('in', 6,'Product', 'PROD', '123456');
$params[] = $conn->AddParameterPackDec('both', 7, 2, 'Quantity', 'QTY', '4.53');
$params[] = $conn->AddParameterZoned('out', 5, 2, 'Price', 'PRICE', '0');

// define a procedure return param. Can be any type, even a data structure
$retParam = $conn->AddParameterInt32('out', '4-byte int', 'MYRESULT', '13579');

/* Call service program procedure. 
 * Procedure name is optional and specified in parameter 5, an array containing associative index 'func'.
 * Make sure your procedure name is 100% correct. It is case-sensitive. 
 * If you get an error, look for your procedure name in the output from this command (replacing LIBNAME/PGMNAME with your library and program names):
 * DSPSRVPGM SRVPGM(LIBNAME/PGMNAME) DETAIL(*PROCEXP)
 * In this example, assume your program is MYLIB/MYPGM and has a procedure/function 'myproc'
 */
$result = $conn->PgmCall('MYPGM', 'MYLIB', $params, $retParam, array('func'=>'myproc'));

if (!$result) {
    echo 'Error calling program. Code: ' . $conn->getErrorCode() . ' Msg: ' . $conn->getErrorMsg();
}

echo 'Called program successfully.<BR><BR>';
echo 'Input/output params: QTY: ' . $result['io_param']['QTY'] . ' PRICE: ' . $result['io_param']['PRICE'] . '<BR>'; 
echo 'Procedure return param MYRESULT: ' . $result['retvals']['MYRESULT']; 

/* 
The above will output something like:

Called program successfully.

Input/output params: QTY: 4.53 PRICE: 0.00
Procedure return param MYRESULT: 13579

*/
