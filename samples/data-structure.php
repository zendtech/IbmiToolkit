// Data structure is easy to add. It's just another parameter.

$param = []; // initialize parameter array

// add parameters as desired
$param[] = $tk->AddParameterChar('in', 10,'Name', 'PTNAME', 'Fred');
$param[] = $tk->AddParameterChar('in', 25,'Address', 'PTADDR', '123 Toolkit Drive');

// DATA STRUCTURE 
// Define the data structure as an array of basic parameter types or other data structures
$ds = [];
$ds[] = $tk->AddParameterChar('in', 21,'Part', 'PTPRT', 'A123');
$ds[] = $tk->AddParameterChar('in', 3,'Vendor', 'PTVEN', '825');
$ds[] = $tk->AddParameterChar('out', 20,'Description', 'PTDES', $out);
$ds[] = $tk->AddParameterZoned('out', 9, 2, 'Price', 'PTPRC', $out);

// Add the data structure as just another element in your main parameter array.
$param[] = $tk->AddDataStruct($ds, 'myds');

// Add additional regular parameters as needed
$param[] = $tk->AddParameterZoned('in', 5, 2, 'Discount', 'PTDISC', '0.24');
