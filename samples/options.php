<?php

// options.php: Options that can be passed in the toolkit option array to setOptions().
// Note that setOptions() is an alias for the older setToolkitServiceParams() method, so they are identical in behavior.

// # 'stateless' => true | false 

// 'stateless' is the most important option. Although false is the default value, true is much better for most users.
// 'stateless' => true is usually the better choice. XMLSERVICE will run in an existing database job without creating additional jobs.
// 'stateless' => false (default) results in a stateful connection where XMLSERVICE launches a separate job that stays running until ended. 
//                      When using stateless => false, remember to choose an InternalKey.
// Example of 'stateless' => true, which is best for most uses.
$conn->setOptions(array('stateless' => true));  // specify true unless you have a good reason not to.

// # 'InternalKey' => $artibitraryDirectory 

// 'InternalKey', combined with turning on stateful mode, will cause a stateful toolkit job to be identified uniquely by the system.
// Its value should be an empty directory, typically under /tmp.
// Any toolkit script specifying this InternalKey value and stateful mode will return to the same XMLSERVICE job later.
// The job will remain running until ended *IMMED or timed out using a timeout option (see below)
// Note: omitting InternalKey in stateful mode would cause a default key of '/tmp/Toolkit' to be used, which would mean everyone is sharing a single XMLSERVICE job.
$conn->setOptions(array('stateless' => false, 
                        'InternalKey' => "/tmp/alan2"
));

// # 'idleTimeout' => $idleTimeoutSeconds 

// 'idleTimeout' will cause a stateful toolkit job to end after the specified number of seconds of inactivity.
// This option will tell XMLSERVICE to use timeout setting *idle(3600/kill). For advanced timeout options, see: http://yips.idevcloud.com/wiki/index.php/XMLService/XMLSERVICETIMEOUT

// In the example below, the XMLSERVICE job will end after 3600 seconds (one hour) of not being used. 
$conn->setOptions(array('stateless' => false, 
                        'InternalKey' => "/tmp/alan2",
                        'idleTimeout' => 3600
));
  
// # 'customControl' => $customControlKeys

// 'customControl' is useful when you need to send control keys supported by XMLSERVICE but that aren't directly exposed by the PHP Toolkit. 
// Helpful for the less popular control keys that you might still need from time to time.
// To send multiple keys, include a space between each.

// Example #1: *java when calling an RPG program that includes java. 

$conn->setOptions(array('stateless' => true, 
                        'customControl'=>'*java'
));

// Example #2: *call and *sbmjob. Useful for setting a timeout on a hanging or looping RPG program when called in stateful (IPC) mode.
$conn->setOptions(array('stateless'    => false, // explicitly be stateful when using this timeout option
                        'internalKey'  => '/tmp/tkitjob2', // arbitrary directory name under /tmp to represent unique job  
                        'customControl'=> '*call(15/kill/server) *sbmjob', // return control to PHP after waiting 15 seconds. CPF is available in error code as well. 
));


// # 'debug' => true or false
// # 'debugLogFile' => path of log file
// For both, defaults are set in toolkit.ini under the [system] group.

// The debug log, when enabled, logs all XML sent/received, as well as connection information, and timing of each request.
// It is invaluable for troubleshooting performance and program calls.
// Make sure the PHP user (probably QTMHHTTP if running from the web) has *RW access to the file,
// and, if the file doesn't yet exist, *RWX access to the parent directory so PHP can create the file. 
// The file can get large, so we recommend setting debug => false when logging is not needed.

$conn->setOptions(array('debug' => true, 
                        'debugLogFile'=>'/my/path/tkit_debug.log'
));


?>
