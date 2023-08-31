<?php

/*

This code snippet shows how to set up your parameters to allow an RPG program to return the minimum number of records without having to know the exact number of records in advance.

1. You do need to specify the max number of records that the RPG array can hold.
2. Your RPG program must set a numeric counter variable that the toolkit can use to know how many records to get. Numeric means zoned, packed decimal, etc. 

*/

// Say you have set up data structure params in the $ds variable.
// Add that data structure and the counter field to the parameter array.

$ds = [];
$ds[] = $conn->AddParameterChar('in', 21,'Part', 'PTPRT', 'A123');
$ds[] = $conn->AddParameterChar('in', 3,'Vendor', 'PTVEN', '825');
$ds[] = $conn->AddParameterChar('out', 20,'Description', 'PTDES', $out);
$ds[] = $conn->AddParameterZoned('out', 9, 2, 'Price', 'PTPRC', $out);

// Multi-occurrence data structure with maximum dimension set to 10000 but whose final output count will be determined by MYCOUNTER. Name the counter whatever you wish.
$param[] = $conn->AddDataStruct($ds, 'MULTDS')
                              ->setParamDimension(10000) // if your RPG array has max of 10000 records
                              ->setParamLabelCounted('MYCOUNTER'); // create your own counter name

// COUNTVAR is a counter field. Value is set by RPG/COBOL program. Value will control the number of MULTDS fields that return (see data structure MULTDS below)
// Counter variable doesn't have to match the counted() and counter() labels.
$param[] = $conn->AddParameterZoned('both', 5, 0, 'how many MULTDS array elements actually return', 'COUNTVAR', 6)
                            ->setParamLabelCounter('MYCOUNTER'); // match the counter name you defined on setParamLabelCounted()

// The count identifier you specified ties them together.

// Now when you call your RPG program, the output will be very efficient, because you'll receive only the number of records that the RPG program specified in the counter variable.

?>
