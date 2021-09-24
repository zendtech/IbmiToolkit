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
                        'InternalKey' => "/tmp/alan2",
));

// # 'idleTimeout' => $idleTimeoutSeconds 

// 'idleTimeout' will cause a stateful toolkit job to end after the specified number of seconds of inactivity.
// This option will tell XMLSERVICE to use timeout setting *idle(3600/kill). For advanced timeout options, see: http://yips.idevcloud.com/wiki/index.php/XMLService/XMLSERVICETIMEOUT

// In the example below, the XMLSERVICE job will end after 3600 seconds (one hour) of not being used. 
$conn->setOptions(array('stateless' => false, 
                        'InternalKey' => "/tmp/alan2",
                        'idleTimeout' => 3600
));
  

?>


