<?php
// update of the classic example

require_once 'ToolkitService.php';

$db = '';
$user = '';
$pass = '';

try {
    $conn = ToolkitService::getInstance($db, $user, $pass);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
    exit();
}

$conn->setOptions(array('stateless' => true));

echo "<pre>";
$rows = $conn->CLInteractiveCommand("DSPLIBL");
if (!$rows) {
    echo $conn->getLastError();
} else {
    print_r($rows);
}

echo "</pre>";
?>

Output will look like:
<pre>Array
(
    [0] =>  5770SS1 V7R4M0  190621                    Library List                                          7/02/21 15:10:47        Page    1
    [1] =>                           ASP
    [2] =>    Library     Type       Device      Text Description
    [3] =>    QSYS        SYS                    System Library
    [4] =>    QSYS2       SYS                    System Library for CPI's
    [5] =>    QHLPSYS     SYS
    [6] =>    QUSRSYS     SYS                    System Library for Users
    [7] =>    QGPL        USR                    General Purpose Library
    [8] =>    QTEMP       USR
    [9] =>                           * * * * *  E N D  O F  L I S T I N G  * * * * *
)
</pre>
