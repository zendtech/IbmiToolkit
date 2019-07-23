<?php
require __DIR__ .'/../vendor/autoload.php';

use ToolkitApi\CW;
use ToolkitApi\UserSpace;
use ToolkitApi\DataQueue;
use ToolkitApi\Toolkit;

// some items to turn on and off in the test
$doPcml = true;
$doUserSpace = true;
$doDataQueue = true;
$doPgmCallComplex = true;
$doPgmCallSimple = true;
$doObjectList = true;
$doJobLists = true;
$doJobLogs = true;
$doSpooledFiles = true;
$doAdoptAuthority = true;

ini_set('display_errors', 1);
set_time_limit(480);
//require_once('CW/cw.php'); // don't need if added auto_append in PHP.INI

// Use configurable demo lib/name from toolkit ini
$demoLib = trim(Toolkit::getConfigValue('demo', 'demo_library'));
if (!$demoLib) {
   die('Demo library not set in toolkit.ini.');
}

// Use configurable encoding from toolkit ini
// We use encoding in meta tag so characters appear correctly in browser
$encoding = trim(Toolkit::getConfigValue('system', 'encoding'));
if (!$encoding) {
   die('Encoding not set in toolkit.ini. Example: ISO-8859-1');
}

// optional demo values
$setLibList = trim(Toolkit::getConfigValue('demo', 'initlibl', ''));
$setCcsid = trim(Toolkit::getConfigValue('demo', 'ccsid', ''));
$setJobName = trim(Toolkit::getConfigValue('demo', 'jobname', ''));
$setIdleTimeout = trim(Toolkit::getConfigValue('demo', 'idle_timeout', ''));
$transportType = trim(Toolkit::getConfigValue ( 'demo', 'transport_type', ''));

// optional demo connection values
$private = false; // default
$privateNum = false; // default
$persistent = trim(Toolkit::getConfigValue('demo', 'persistent', false));
if ($persistent) {
	// private can only happen with persistence
    $private = trim(Toolkit::getConfigValue('demo', 'private', false));
    $privateNum = trim(Toolkit::getConfigValue('demo', 'private_num', '0'));
} //(persistent)

$scriptTitle = 'Test script for IBM i Compatibility Wrapper (CW)';

// TODO get test user from toolkit.ini

// this user, TKITU1, should exist on the system
$user = 'TKITU1';
$testPw = 'NICE2USR';

function OkBad($success = false) {
	if ($success) {
		return 'Successful';
	} else {
		return 'Failed with this error: ' . print_r(i5_error(), true);
	}
}

function printArray($array) 
{
	return '<PRE>' . print_r($array, true) . '</PRE>';
}

function h1($headString) {
	return "<h1>$headString</h1>";
}

function h2($headString) {
	return "<h2>$headString</h2>";
}

?>

<html>
<head>

<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $encoding ?>">

<style type="text/css">
body {
	font: 15px;
}

h1 {
	font: 28px arial,sans-serif;
	font-weight: bold;
	border-style: solid;
	color: #2278A5;
	border: 1px 1px 1px 1px;
	padding: 2px 2px 2px 2px;
	margin-bottom: 30px;
}

h2 {
	font: 20px arial,sans-serif;
	font-weight: bold;
	background-color: lightblue; 
}

</style>

<title><?php echo $scriptTitle; ?></title>
</head>
<?php 
echo h1($scriptTitle);

echo h2('Version check');

// display CW version or warning message.
$downloadSite = 'http://www.youngiprofessionals.com/wiki/XMLSERVICE';
$downloadLink = '<a href="' . $downloadSite . '" target="_blank">' . $downloadSite . '</a>';
if (function_exists('i5_version')) {
	echo "You are running CW version <b>" . i5_version() . "</b>.<BR> Any updates will be found at $downloadLink.<BR>";
} else {
	echo "This version of CW is out of date.<BR>Please download the latest CW from $downloadLink.<BR><BR>";
}

echo h2('Connection');

// choose connection function based on persistence choice
$connFunction = ($persistent) ? 'i5_pconnect' : 'i5_connect';

echo "About to connect with $connFunction('', '', '') (feel free to specify a real user here)<BR>";

// options (liblist, ccsid, jobname) can be changed by the user in toolkit.ini.
$options = array();
if ($setLibList) {
	$options[I5_OPTIONS_INITLIBL] = $setLibList;
	echo "I5_OPTIONS_INITLIBL = '$setLibList'<BR>";
}
if ($setCcsid) {
	$options[I5_OPTIONS_RMTCCSID] = $setCcsid;
	echo "I5_OPTIONS_RMTCCSID = '$setCcsid'<BR>";
}

if ($setJobName) {
	$options[I5_OPTIONS_JOBNAME] = $setJobName;
	echo "I5_OPTIONS_JOBNAME = '$setJobName'<BR>";
}

if ($setIdleTimeout) {
	$options[I5_OPTIONS_IDLE_TIMEOUT] = $setIdleTimeout;
	echo "I5_OPTIONS_IDLE_TIMEOUT = '$setIdleTimeout'<BR>";
}

if ($transportType) {
	$options[CW_TRANSPORT_TYPE] = $transportType;
	echo "CW_TRANSPORT_TYPE = '$transportType'<BR>";
}

if ($persistent && $private) {
	$options[I5_OPTIONS_PRIVATE_CONNECTION] = $privateNum;
	echo "I5_OPTIONS_PRIVATE_CONNECTION = '$privateNum'<BR>";
} // (private and privateNum)

echo '<BR>';

/*
 * // Optionally re-use an existing database connection for your transport
 * // If you specify a naming mode (i5/sql) in your connection, make sure they match.
 * $namingMode = DB2_I5_NAMING_ON;
 * $existingDb = db2_pconnect('', '','', array('i5_naming' => $namingMode));
 * // Add to existing connection options
 * $options[CW_EXISTING_TRANSPORT_CONN] = $existingDb;
 * $options[CW_EXISTING_TRANSPORT_I5_NAMING] = $namingMode;
*/

$start = microtime(true);

// about to connect. Can use i5_connect or i5_pconnect.
$conn = $connFunction('', '', '', $options);
$end = microtime(true);
$elapsed = $end - $start;
echo "Ran $connFunction function, with options, in $elapsed seconds.<BR>";

// if unable to connect, find out why.
if (!$conn) {
    die('<BR>Could not connect. Reason: ' . printArray(i5_error()));	
} 

echo "Connection object output: '$conn'<BR><BR>";

if ($private) {
	// if a private connection, show what number was used or generated.
    $privateConnNum = i5_get_property(I5_PRIVATE_CONNECTION, $conn);
    echo "Private conn number from i5_get_property(I5_PRIVATE_CONNECTION, \$conn): $privateConnNum<BR><BR>";

    $isNew = i5_get_property(I5_NEW_CONNECTION, $conn);
    echo "Is new connection?: $isNew<BR><BR>";
}

// CONNECTED. 

// check that demo library exists
echo "About to verify that the demo library, '$demoLib', exists.<BR>";
$list = i5_objects_list('QSYS', $demoLib, '*LIB', $conn); 
if (!$list) {
	echo 'Error getting object list: ' . printArray(i5_error()) . '<BR><BR>';
} else {
    if ($listItem = i5_objects_list_read($list)) {
	    echo "Demo library '$demoLib' exists.<BR><BR>";
	} else {
	    die ("<BR>Demo library '$demoLib' NOT found. Ending.");
	}
}

i5_objects_list_close($list);


// ON TO ACTUAL FUNCTIONALITY
if ($doPcml) {
    echo h2('PCML program calls');
	
	$pcml = '<pcml version="4.0">
   <program name="YYPLUS" entrypoint="YYPLUS"  path="/QSYS.LIB/' . $demoLib . '.LIB/YYSRVNORES.SRVPGM" >
      <data name="START" type="int" length="4" precision="31" usage="inputoutput" />
      <data name="RESULT" type="int" length="4" precision="31" usage="inputoutput" />
   </program>
    </pcml>';

	echo 'About to do simple PCML program prepare.<BR>';
	$pgmHandle = i5_program_prepare_PCML($pcml);

	if (!$pgmHandle) {
		echo 'Error preparing simple PCML program: ' . printArray(i5_error()) . '<BR><BR>';
	} else {
		
		$input = array('START' => '25', 'RESULT' => '0');
		$output = array('START' => 'START', 'RESULT' => 'RESULT');
		echo 'About to do simple PCML program call.<BR>';
		$success = i5_program_call($pgmHandle, $input, $output);
        $result = $output['RESULT'];
	
		if ($success) {
			echo "Success. Output variables: START: $start. RESULT: $result.";
		} else {
			echo "Problem calling PCML-described program. Error: " . print_r(i5_error(), true);
		}
	}

    echo '<BR><BR>';
    
    $pcml = "<pcml version=\"4.0\">
       <struct name=\"S2\">
          <data name=\"ZOND2\" type=\"zoned\" length=\"10\" precision=\"5\" usage=\"inherit\" />
          <data name=\"PACK2\" type=\"packed\" length=\"19\" precision=\"5\" usage=\"inherit\" />
          <data name=\"PACK3\" type=\"packed\" length=\"19\" precision=\"5\" usage=\"inherit\" />
          <data name=\"ALPH2\" type=\"char\" length=\"20\" usage=\"inherit\" />
       </struct>
       <struct name=\"S1\">
          <data name=\"ZOND\" type=\"zoned\" length=\"10\" precision=\"5\" usage=\"inherit\" />
          <data name=\"PACK1\" type=\"packed\" length=\"19\" precision=\"5\" usage=\"inherit\" />
          <data name=\"ALPH1\" type=\"char\" length=\"10\" usage=\"inherit\" />
       </struct>
       <program name=\"TESTSTRUC\" path=\"/QSYS.LIB/{$demoLib}.LIB/TESTSTRUC.PGM\">
          <data name=\"CODE\" type=\"char\" length=\"10\" usage=\"output\" />
          <data name=\"S1\" type=\"struct\" struct=\"S1\" usage=\"inputoutput\" />
          <data name=\"S2\" type=\"struct\" struct=\"S2\" usage=\"inputoutput\" />
          <data name=\"PACK\" type=\"packed\" length=\"1\" precision=\"1\" usage=\"output\" />
          <data name=\"CH10\" type=\"char\" length=\"19\" usage=\"output\" />
          <data name=\"CH11\" type=\"char\" length=\"20\" usage=\"output\" />
          <data name=\"CH12\" type=\"char\" length=\"29\" usage=\"output\" />
          <data name=\"CH13\" type=\"char\" length=\"33\" usage=\"output\" />
       </program>
    </pcml>";
	
	echo 'About to do a complex PCML program prepare.<BR>';
	$pgmHandle = i5_program_prepare_PCML($pcml);

	if ($pgmHandle) {
		echo "Successfully prepared complex PCML program description.<BR>";
	} else {
		echo "Problem while preparing complex PCML program description.<BR>";
	}
	// define some input values
    $pack3value=7789777.44;
    $alph2value=4;
    
    $paramIn = array(
    "S1"=>array("ZOND"=>54.77, "PACK1"=>16.2, "ALPH1"=>"MyValue"),
    "S2"=>array("ZOND2"=>44.66, "PACK2"=>24444.99945, "PACK3"=>$pack3value, "ALPH2"=>$alph2value)
    );
    
    // now we need to define where to place output values; it will create new local variables
    $paramOut = array(
                        "S1"=>"S1_Value", "S2"=>"S2_Value",
                        "CH10"=>"CH10_Value", "CH11"=>"CH11_Value", "CH12"=>"CH12_Value", "CH13"=>"CH13_Value",
                        "CODE"=>"Code_Value", "PACK"=>"Pack"
    );
	echo 'About to do complex PCML program call.';
	$success = i5_program_call($pgmHandle, $paramIn, $paramOut);
    if (function_exists('i5_output')) extract(i5_output()); // i5_output() required if called in a function

    if ($success) {
		echo "Success.";
		echo "<BR>S1: " . var_export($S1_Value, true);
		echo "<BR>S2: " . var_export($S2_Value, true);
		echo "<BR>CH10: " . var_export($CH10_Value, true);
		echo "<BR>CH11: " . var_export($CH11_Value, true);
		echo "<BR>CH12: " . var_export($CH12_Value, true);
		echo "<BR>CH13: " . var_export($CH13_Value, true);
		echo "<BR>Code: " . var_export($Code_Value, true);
		echo "<BR>Pack: " . var_export($Pack, true);
		
	} else {
		echo "Problem calling PCML-described program. Error: " . printArray(i5_error());
	}
}

$bigDesc = array(
    array("DSName"=>"BIGDS", "DSParm"=>array(
        array("Name"=>"P1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10, "Count"=>5),
        array("Name"=>"P2C", "IO"=>I5_INOUT,"Type"=>I5_TYPE_LONG, "Length"=>4),
        array("Name"=>"P2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>1, "CountRef"=>"P2C"),
        array("DSName"=>"PS", "Count"=>2, "DSParm"=>array(
            array("Name"=>"PS1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
            array("Name"=>"PS2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
            array("Name"=>"PS3", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10)
        ))
    ))
);

$bigInputValues = array(
    "BIGDS"=>array(
        "P1"=>array("t1", "t2", "t3", "t4", "t5"),
        "P2C"=>2,
        "P2"=>array("a", "b"),
        "PS"=>array(
            array("PS1"=>"test1", "PS2"=>"test2", "PS3"=>"test3"),
            array("PS1"=>"test3", "PS2"=>"test4", "PS3"=>"test5")
        )
    )
);

if ($doUserSpace) {
    echo h2('User spaces');	
        
    $userSpaceName = 'DEMOSPACE';
    $userSpaceLib = $demoLib;
    
    $usObj = new UserSpace($conn);
    $usObj->setUSName($userSpaceName, $userSpaceLib); 
    
    // toolkit does not have an i5_userspace_delete so delete with a command.
    $ret = i5_command("DLTUSRSPC USRSPC($userSpaceLib/$userSpaceName)", $conn);
    if (function_exists('i5_output')) extract(i5_output()); // i5_output() required if called in a function
    
    $status = ($ret) ? 'successfully' : 'badly';
    echo "deleted user space: $status<BR>";
    //$us = $usObj->CreateUserSpace('ALANUS', 'ALAN', $InitSize =1024, $Authority = '*ALL', $InitChar=' ');
    $usProperties = array(I5_NAME=>$userSpaceName, I5_LIBNAME=>$userSpaceLib, I5_INIT_VALUE=>'Y');
    echo "About to create user space.<BR>";
    $us = i5_userspace_create($usProperties, $conn);
    if (!$us) {
        echo "Error returned: " . printArray(i5_error()) . "<BR><BR>";
    } else {
        echo "Success!<BR><BR>";
    }
    
    // prepare userspace for a put
    $us = i5_userspace_prepare("$userSpaceLib/$userSpaceName", $bigDesc, $conn);
    if (!$us) {
        echo "Error returned from user space prepare: " . printArray(i5_error()) . "<BR><BR>";
    } else {
        echo "Success preparing user space.<BR><BR>";
    }
    
    // do the userspace put
    $success = i5_userspace_put($us, $bigInputValues);
    if (!$success) {
        echo "Error returned from user space put: " . printArray(i5_error()) . "<BR><BR>";
    } else {
        echo "Success putting data into user space.<BR><BR>";
    }
    
    // do the userspace get
    // removed counfref because doesn't work when getting.
    $bigDesc = array(
        array("DSName"=>"BIGDS", "DSParm"=>array(
            array("Name"=>"P1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10, "Count"=>5),
            array("Name"=>"P2C", "IO"=>I5_INOUT,"Type"=>I5_TYPE_LONG, "Length"=>4),
            array("Name"=>"P2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>1, "Count"=>2),
            array("DSName"=>"PS", "Count"=>2, "DSParm"=>array(
                array("Name"=>"PS1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
                array("Name"=>"PS2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
                array("Name"=>"PS3", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10)
            ))
        ))
    );
    
    // prepare userspace for a get
    $us = i5_userspace_prepare("$userSpaceLib/$userSpaceName", $bigDesc, $conn);
    if (!$us) {
        echo "Error returned from user space prepare: " . printArray(i5_error()) . "<BR><BR>";
    } else {
        echo "Success preparing user space.<BR><BR>";
    }
    
    $success = i5_userspace_get($us, array("BIGDS"=>"BIGDS"));
    if (function_exists('i5_output')) extract(i5_output()); // i5_output() required if called in a function
    
    if (!$success) {
        echo "Error returned from user space get: " . i5_error() . "<BR><BR>";
    } else {
        echo "Success getting data from user space. BIGDS=" . printArray($BIGDS) . "<BR><BR>";
    }
}

// data queue
if ($doDataQueue) {
	echo h2('Data queues');
	
	$queueName = 'KEYEDQ';
	$keyLen = 10;
	$qObj = new DataQueue($conn);
	echo "<BR>About to delete data queue $queueName. (Will fail if doesn't exist yet)<BR>";
	try {
	    $qObj->DeleteDQ($queueName, $demoLib);
	    echo "Success deleting data queue $queueName.";
	} catch (Exception $e) {
		echo "Error deleting data queue: " . $e . "<BR><BR>";
	}
	
	echo "<BR>About to create data queue $queueName.<BR>";
	try {
	    $qObj->CreateDataQ($queueName, $demoLib, 128, '*KEYED', $keyLen); // length 10 key
	    echo "Success creating data queue $queueName.";
	} catch (Exception $e) {
		echo "Error creating data queue: " . $e . "<BR><BR>";
	}
	
	// test case adapted from p398 of Zend Server 5.1 manual
	$simpleStructure = array(
        'DSName' => 'PS',
        'DSParm' => 
        array(
            array(
            'type' => 0,
            'name' => 'PS1',
            'length' => '10',
            ),
            
            array(
            'type' => 6,
            'name' => 'PS2',
            'length' => '10.4',
            ),
            
            array(
            'type' => 0,
            'name' => 'PS3',
            'length' => '10',
            ),
        )
    );
    
	// prepare
	$queue = i5_dtaq_prepare("$demoLib/$queueName", $simpleStructure, $keyLen);
    if (!$queue) {
    	echo "Error preparing data queue.<BR><BR>";
    }	


    // send
    $key = 'abc';
	$data = array('PS1' => 'test1', 'PS2' => 13.1415, 'PS3' => 'test2');
    
    echo "<BR>About to send simple structure to keyed data queue $queueName with key $key.<BR>";
	$success = i5_dtaq_send($queue, $key, $data);
	
	if (!$success) {
		echo "Error returned from data queue send: " . printArray(i5_error()) . "<BR><BR>";
	} else {
		echo "Success sending data to data queue.<BR><BR>";
	}

    echo "<BR>About to receive simple structure from keyed data queue $queueName with key $key.<BR>";
	$data = i5_dtaq_receive($queue, 'EQ', $key);
	
	// receive
	if (!$data) {
		echo "Error returned from simple data queue receive: " . printArray(i5_error());
	} else {
		echo "Success getting simple data structure from data queue: " . printArray($data);
	}
		
	echo '<BR>';
	
	// unkeyed queue with complex structure
	
	$queueName = 'NEWQ';
	$qObj = new DataQueue($conn);
	echo "<BR>About to delete data queue $queueName. (Will fail if doesn't exist yet)<BR>";
	try {
	    $qObj->DeleteDQ($queueName, $demoLib);
	    echo "Success deleting data queue $queueName.";
	} catch (Exception $e) {
		echo "Error deleting data queue: " . $e . "<BR><BR>";
	}
	
	echo "<BR>About to create data queue $queueName.<BR>";
	try {
	    $qObj->CreateDataQ($queueName, $demoLib);
	    echo "Success creating data queue $queueName.";
	} catch (Exception $e) {
		echo "Error creating data queue: " . $e . "<BR><BR>";
	}
	
	$bigDesc = array(
        array("DSName"=>"BIGDS", "DSParm"=>array(
            array("Name"=>"P1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10, "Count"=>5),
            array("Name"=>"P2C", "IO"=>I5_INOUT,"Type"=>I5_TYPE_LONG, "Length"=>4),
            array("Name"=>"P2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>1, "Count"=>2),
            array("DSName"=>"PS", "Count"=>2, "DSParm"=>array(
                array("Name"=>"PS1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
                array("Name"=>"PS2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
                array("Name"=>"PS3", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10)
            ))
        ))
    );
	
	// prepare
	$queue = i5_dtaq_prepare("$demoLib/$queueName", $bigDesc);
    if (!$queue) {
    	echo "Error preparing data queue.<BR><BR>";
    }	

    // send
    echo "<BR>About to send big data structure to data queue $queueName.<BR>";
	$success = i5_dtaq_send($queue, '', $bigInputValues);
	// 
	if (!$success) {
		echo "Error returned from data queue send: " . i5_error() . "<BR><BR>";
	} else {
		echo "Success sending data to data queue.<BR><BR>";
	}
    
    
    echo "<BR>About to receive big data structure from data queue $queueName.<BR>";
	$data = i5_dtaq_receive($queue);//, $operator = null, $key = '', $timeout = 0)
	
	// receive
	if (!$data) {
		echo "Error returned from data queue receive: " . printArray(i5_error());
	} else {
		echo "Success getting data from data queue: " . printArray($data);
	}
		
	echo '<BR>';
	
	// Now a short-form DQ test
	
	// short-form description
	$littleDesc = array("Name"=>"sometext", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>20);
	$littleInput = "Small text input";
	
	echo "<BR>About to send small short-form data structure to data queue $queueName.<BR>";
	
	// prepare
	$queue = i5_dtaq_prepare("$demoLib/$queueName", $littleDesc);
    if (!$queue) {
    	echo "Error preparing data queue.<BR><BR>";
    }	

    // send
	$success = i5_dtaq_send($queue, '', $littleInput);
	// 
	if (!$success) {
		echo "Error returned from data queue send of small input: " . i5_error() . "<BR><BR>";
	} else {
		echo "Success sending the string '$littleInput' to data queue.<BR><BR>";
	}
    
    echo "<BR>About to receive small data structure from data queue $queueName.<BR>";
	$data = i5_dtaq_receive($queue);//, $operator = null, $key = '', $timeout = 0)
	// receive
	if (!$data) {
		echo "Error returned from data queue receive of small data: " . i5_error() . "<BR><BR>";
	} else {
		echo "Success getting small data from data queue: '$data'<BR><BR>";
	}
	
	echo '<BR><BR>';
}


if ($doObjectList) {
	
	echo h2('Object lists');

	echo "About to do object list with '$demoLib', '*ALL','*PGM'<BR>";
// object list
$list = i5_objects_list($demoLib, '*ALL', '*PGM', $conn); 
if (!$list) {
	echo 'Error getting object list: ' . printArray(i5_error()) . '<BR><BR>';
	
} else {
    
	while ($listItem = i5_objects_list_read($list)) {
			echo printArray($listItem);
	}
	echo 'End of list. Error information: ' . printArray(i5_error()) . '<BR><BR>';
}
i5_objects_list_close($list);


}

if ($doPgmCallSimple) {
	echo h2('Program calls');
    echo 'Program call with simple parameters<BR>';
	
    $progname = "$demoLib/TESTSTP2";
    
    $desc = array(
        array("Name"=>"code", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>"10"),
        array("Name"=>"name", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>"10")
    );
    
    $desc = array(
         0 => array( 'type' => 0, 'name' => 'code', 'length' => 10, 'io' => 3), 
         1 => array( 'type' => 0, 'name' => 'name', 'length' => 10, 'io' => 3)
    );
    
    echo "<b>About to call $progname with two char parameters.</b><BR>";
    
    $prog = i5_program_prepare($progname, $desc);
    if ($prog === FALSE) {
        $errorTab = i5_error();
        echo "Program prepare failed <br>\n";
        var_dump($errorTab);
        die();
    }
    /* Execute Program */
    $params = array("code"=>"123","name"=>"ABC");
    $retvals = array("code"=>"code","name"=>"name");
    $ret = i5_program_call($prog, $params, $retvals) ;
    if (function_exists('i5_output')) extract(i5_output()); // i5_output() required if called in a function
    
    if ($ret === FALSE)
    {
    $errorTab = i5_error();
    echo "FAIL : i5_program_call failure message: " . $conn->getLastError() . " with code <br>";
    var_dump($errorTab);
    }else {
        // success
        echo "Success! The return values are: <br>", "Name: ", $name, "<br> Code: ", $code, "<br><BR>";
    }
    $close_val = i5_program_close ($prog);
    if ($close_val === false)
    {
    print ("FAIL : i5_program_close returned fales, closing an open prog.<br>\n");
    $errorTab = i5_error();
    var_dump($errorTab);
    }


} // (simple call)

// *** data structure call! ***

if ($doPgmCallComplex) {

echo '<BR>Program call with complex parameters<BR>';	
	
$progname = "$demoLib/RPCTEST";

echo "<b>About to call $progname with data structure parameters.</b>";

/*Call a program with parameters that include a DS */

$desc = array(
    array("Name"=>"P1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10, "Count"=>5),
    array("Name"=>"P2C", "IO"=>I5_INOUT,"Type"=>I5_TYPE_LONG, "Length"=>4),
    array("Name"=>"P2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>1, "CountRef"=>"P2C"),
    array("DSName"=>"PS", "Count"=>2, "DSParm"=>array(
        array("Name"=>"PS1", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
        array("Name"=>"PS2", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10),
        array("Name"=>"PS3", "IO"=>I5_INOUT, "Type"=>I5_TYPE_CHAR, "Length"=>10)
    ))
);

$prog = i5_program_prepare($progname, $desc);
if ($prog === FALSE) {
	$errorTab = i5_error();
	echo "Program prepare failed <br>\n";
	var_dump($errorTab);
	die();
}
/* Execute Program */

// The nameless elements in array.
$params1 = array(
array("PS1"=>"test1", "PS2"=>"test2", "PS3"=>"test3"),
array("PS1"=>"test3", "PS2"=>"test4", "PS3"=>"test5")
);

$params2 = array(
"P1"=>array("t1", "t2", "t3", "t4", "t5"),
"P2C"=>2,
"P2"=>array("a", "b"),
"PS"=>$params1);
$retvals = array("P1"=>"P1", "PS"=>"PS", "P2"=>"P2", "P2C"=>"P2C");

$ret = i5_program_call($prog, $params2, $retvals) ;
if (function_exists('i5_output')) extract(i5_output()); // i5_output() required if called in a function

if ($ret === FALSE)
{
$errorTab = i5_error();
echo "FAIL : i5_program_call failure message: " . $conn->getLastError() . " with code <br>";
var_dump($errorTab);
}else {
    // success
    echo "<BR><BR>Success! The return values are: <br>";
    echo "P1 : " . printArray($P1) . "<BR>";
    echo "P2C : " . $P2C . "<BR>";
    echo "P2 : " . printArray($P2) . "<BR>";
    echo "PS: " . printArray($PS) . "<BR>";
}
$close_val = i5_program_close ($prog);
if ($close_val === false)
{
print ("FAIL : i5_program_close returned fales, closing an open prog.<br>\n");
$errorTab = i5_error();
var_dump($errorTab);
}

} //(pgmcall complex)


echo h2('Commands');

$msg = 'HELLO';
$cmdString = "SNDMSG MSG($msg) TOUSR($user)";
$start = microtime(true);
$commandSuccessful = i5_command($cmdString, array(), array(), $conn);
$end = microtime(true);
$elapsed = $end - $start;
echo "Ran command $cmdString using a single string in $elapsed seconds. Return: " . OkBad($commandSuccessful) . "<BR><BR>";

$badUser = 'jerk';
$msg = 'HELLO';
$cmdString = "SNDMSG MSG($msg) TOUSR($badUser)";
$start = microtime(true);
$commandSuccessful = i5_command($cmdString, array(), array(), $conn);
$end = microtime(true);
$elapsed = $end - $start;
echo "Ran command $cmdString using a single string to BAD user in $elapsed seconds.. Return: " . OkBad($commandSuccessful). "<BR>";
if (!$commandSuccessful) {
	echo "Error returned: " . printArray(i5_error()) . "<BR><BR>";
}



$cmdString = 'RTVJOBA';
$input = array();
// we want variable name ccsid to be created
$output = array('ccsid'    => array('ccsid', 'dec(5 0)'),
                'dftccsid' => array('defaultCcsid', 'dec(5 0)'),
                'curuser'=>'currentUser', 'nbr'=>'jobNumber', 'job'=>'jobName', 'user'=>'jobUser',
                               'usrlibl' => 'userLibl');
$start = microtime(true);
$commandSuccessful = i5_command($cmdString, $input, $output, $conn);
if (function_exists('i5_output')) extract(i5_output()); // i5_output() required if called in a function
$end = microtime(true);

$elapsed = $end - $start;

echo "Ran command $cmdString with an output array in $elapsed seconds. Return: " . 
     OkBad($commandSuccessful) . 
     " with CCSID '$ccsid', default CCSID '$defaultCcsid', current user '$currentUser', job name '$jobName', job number '$jobNumber', job user '$jobUser', with user liblist '$userLibl'.<BR><BR>";

// Note: old toolkit cannot get interactive output of this sort (DSPJOBLOG). This is additional functionality of the new toolkit.
$cmdString ="DSPJOBLOG JOB($jobNumber/$jobUser/$jobName)";
echo "About to run " . $cmdString .".<BR>";
$conn->setToolkitServiceParams(array('plugSize'=>'5M')); // bigger to handle large joblog
$interactiveOutput = $conn->CLInteractiveCommand($cmdString);
$conn->setToolkitServiceParams(array('plugSize'=>'512K')); // put back to default
echo printArray($interactiveOutput) . "<BR><BR>";

$msg = 'HELLO_WITH_INPUTS_ARRAY';
$cmdString = "SNDMSG";
$inputs = array('MSG'=>$msg, 'TOUSR'=>$user);
$commandSuccessful = i5_command($cmdString, $inputs);
echo "Ran command $cmdString with an input array: " . printArray($inputs) . "Return:  " . OkBad($commandSuccessful) . ".<BR><BR>";

$msg = "MixedCaseNoSpaces";
$cmdString = "SNDMSG";
$inputs = array('MSG'=>$msg, 'TOUSR'=>$user);
$commandSuccessful = i5_command($cmdString, $inputs);
echo "Ran command $cmdString with an input array: " . printArray($inputs) . "Return:  " . OkBad($commandSuccessful) . ".<BR><BR>";


$msg = "Davey Jones embedded spaces without quotes--caused error in old toolkit";
$cmdString = "SNDMSG";
$inputs = array('MSG'=>$msg, 'TOUSR'=>$user);
$commandSuccessful = i5_command($cmdString, $inputs);
echo "Ran command $cmdString with an input array: " . printArray($inputs) . "Return:  " . OkBad($commandSuccessful) . ".<BR><BR>";

$msg = "O'flanagan single quote--caused error in old toolkit";
$cmdString = "SNDMSG";
$inputs = array('MSG'=>$msg, 'TOUSR'=>$user);
$commandSuccessful = i5_command($cmdString, $inputs);
echo "Ran command $cmdString with an input array: " . printArray($inputs) . "Return: " . OkBad($commandSuccessful) . ".<BR><BR>";

echo h2('Error functions');
echo "Let's test i5_errormsg() and i5_errno()<BR>Get last error message: " . i5_errormsg();
echo "<BR>Get last error number: " . i5_errno(). "<BR><BR>";

echo h2('Get system value');
$start = microtime(true);
$date = i5_get_system_value('QDATE');
$end = microtime(true);
$elapsed = $end - $start;
echo "QDATE system value: '$date', obtained in $elapsed seconds.<BR>";

echo h2('Data areas');
$dtaara = "$demoLib/ALLEYOOP";
$ret = i5_data_area_create($dtaara, 72); 
if ($ret) {
	echo "Created data area $dtaara successfully.<BR>";
} else {
	echo "Could not create data area $dtaara.<BR>";
}

$ret = i5_data_area_delete($dtaara); 
if ($ret) {
	echo "Deleted data area $dtaara successfully.<BR>";
} else {
	echo "Could not delete data area $dtaara.<BR>";
}

$dtaara = 'BETTYBOOP';
$ret = i5_data_area_create($dtaara, 100); 
if ($ret) {
	echo "Created data area $dtaara successfully.<BR>";
} else {
	echo "Could not create data area $dtaara. Reason: " . i5_errormsg() . " (it may already exist)<BR>";
}

$dtaara = 'BETTYBOOP';
$stringToWrite = 'Very nice';
$ret = i5_data_area_write($dtaara, $stringToWrite, 5, 20); 
if ($ret) {
	echo "Wrote '$stringToWrite' to data area $dtaara successfully.<BR>";
	
	// try to read now.
	$start = microtime(true);
	$readData = i5_data_area_read($dtaara, 3, 40);
	$end = microtime(true);
	$elapsed = $end - $start;

    if ($readData) {
    	echo "Read a portion of '$readData' from data area $dtaara successfully in $elapsed seconds.<BR>";
    } else {
    	echo "Could not read from data area $dtaara. Reason: " . i5_errormsg() . "<BR>";
    }	
	
	// try to read now.
	$start = microtime(true);
	$readData = i5_data_area_read($dtaara); // the whole thing
	$end = microtime(true);
	$elapsed = $end - $start;

    if ($readData) {
    	echo "Read ALL of '$readData' from data area $dtaara successfully in $elapsed seconds.<BR>";
    } else {
    	echo "Could not read from data area $dtaara. Reason: " . i5_errormsg() . "<BR>";
    }	
	
    

} else {
	echo "Could not write to data area $dtaara. Reason: " . i5_errormsg() . "<BR>";
}

// job list

if ($doJobLists) {
    echo h2('Job lists');
        
    echo "About to get up to 5 jobs with jobname ZENDSVR (can also do I5_JOBUSER, I5_USERNAME, I5_JOBNUMBER, and I5_JOBTYPE).<BR>";
    
    $list = i5_job_list(array(I5_JOBNAME=>'ZENDSVR'));
    if (!$list) {
        echo 'Error getting job list: ' . printArray(i5_error()) . '<BR>';
    } else {
        $jobCount = 0;
        while (($listItem = i5_job_list_read($list)) && (++$jobCount <= 5)) {
                echo printArray($listItem) .  '<BR>';
        }
        echo 'End of list.<BR><BR>';
    }
    
    i5_job_list_close($list);
    
    // Get info about current job
    echo "Getting information about current job.<BR>";
    $list = i5_job_list();//array(I5_USERNAME=>'*ALL'), $conn);
    if (!$list) {
        echo 'Error getting job list: ' . printArray(i5_error()) . '<BR>';
    } else {
         // should be only one for current job.
        $listItem = i5_job_list_read($list);
        echo "<BR>list item for current job: " . printArray($listItem) . "<BR><BR>"; 
        echo "Job name: {$listItem[I5_JOB_NAME]} user: {$listItem[I5_JOB_USER_NAME]} job number: {$listItem[I5_JOB_NUMBER]}<BR><BR>";
    }
        
    i5_job_list_close($list);
}

if ($doSpooledFiles) {
    echo h2('Spooled Files');
    
    $splUser = 'QTMHHTTP';
    echo "Get up to 5 spooled files for user $splUser<BR>";
    $list = i5_spool_list(array(I5_USERNAME=>$splUser), $conn);
    if (!$list) {
        echo 'Error getting spool list: ' . printArray(i5_error()) . '<BR>';
    } else {
        $spoolCount = 0;
        while (($listItem = i5_spool_list_read($list)) && (++$spoolCount <= 5)) {
            echo "<BR>list item: " . printArray($listItem) . "<BR>";
            echo '<BR>Output data for this spool file: <BR>';
            $data = i5_spool_get_data($listItem['SPLFNAME'],
                                          $listItem['JOBNAME'],
                                          $listItem['USERNAME'],
                                          $listItem['JOBNBR'],
                                          $listItem['SPLFNBR']);
            if (!$data) {
                echo '<BR>No spool data. Error info: ' . printArray(i5_error()) . '<BR>';
            } else {
                echo "<PRE>$data</PRE><BR>";
            }
        }    	
    }
    
    i5_spool_list_close($list);
    
    $outq = 'QGPL/QPRINT';
    echo "<BR>Get up to 5 spooled files for outq $outq (may get permissions message if user's authority is insufficient)<BR>";
    $list = i5_spool_list(array(I5_OUTQ=>$outq), $conn);
    if (!$list) {
        echo 'Error getting spool list: ' . printArray(i5_error()) . '<BR>';
    } else {
    
        $spoolCount = 0;
        while (($listItem = i5_spool_list_read($list)) && (++$spoolCount <= 5)) {
    
            echo "<BR>list item: " . printArray($listItem) . "<BR>";
            echo '<BR>Output data for this spool file: <BR>';
            $data = i5_spool_get_data($listItem['SPLFNAME'],
                                      $listItem['JOBNAME'],
                                      $listItem['USERNAME'],
                                      $listItem['JOBNBR'],
                                      $listItem['SPLFNBR']);
            if (!$data) {
                echo '<BR>No spool data. Error info: ' . printArray(i5_error()) . '<BR>';
            } else {
                echo "<PRE>$data</PRE><BR>";
            }
        }
    }
    
    i5_spool_list_close($list);
}

// job log.
if ($doJobLogs) {
    echo h2('Job logs');	
        
    // Try current job. Good, it works, except for not enough data coming back from PHP wrapper.
    echo "About to get joblog (partial data) for current job<BR>";
    $list = i5_jobLog_list();
    if (!$list) {
        echo 'No joblogs found<BR>';
    } else {
        
        while ($listItem = i5_jobLog_list_read($list)) {
                echo printArray($listItem);
        }
        echo '<BR>End of list.<BR><BR>';
    }
    
    i5_jobLog_list_close($list);
}

if ($doAdoptAuthority) {
	echo h2('Adopt authority');	
	
	// Note: only works if you've defined $user and $testPw, and created the user profile.
	echo "About to adopt authority to user $user<BR>";
	$start = microtime(true);
	
	$success = i5_adopt_authority($user, $testPw);
    $end = microtime(true);
    $elapsed = $end - $start;

	
	if (!$success) {
	    echo "Error adopting authority: " . printArray(i5_error()) . "<BR>";
    } else {
    	echo "Success adopting authority in $elapsed seconds<BR>";
    	
    	echo "About to check current user and other variables after adopting authority.<BR>";
    	
    	$cmdString = 'RTVJOBA';
        $input = array();
        
        $output = array('ccsid'    => array('ccsid', 'dec(5 0)'),
                        'dftccsid' => array('defaultCcsid', 'dec(5 0)'),
                        'curuser'=>'currentUser',
                        'nbr'=>'jobNumber',
                        'job'=>'jobName',
                        'user'=>'jobUser',
                        'usrlibl' => 'userLibl');
        $commandSuccessful = i5_command($cmdString, $input, $output, $conn);
        if (function_exists('i5_output')) extract(i5_output()); // i5_output() required if called in a function
        
        echo "Ran command $cmdString. Return: " . OkBad($commandSuccessful) . 
              " with original job user '$jobUser', current user '$currentUser', CCSID '$ccsid', default CCSID '$defaultCcsid', job name '$jobName', job number '$jobNumber', with user liblist '$userLibl'.<BR><BR>";
    }
}

$ret = i5_close($conn);//$conn optional

echo h2('Connection close');
echo "Closed i5 connection. return status: " . OkBad($ret) . ".";

echo h1('End of script');
?>
</html>
