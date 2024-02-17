<?php
/* The "local" transport is talks to XMLSERVICE by calling a command on the IBM i. 
   Rather than connecting via a database connection, it will fork a QP0ZSPWP job (by default) under the current user profile.
   Prerequisite: Install the "itoolkit-utils" package from Yum on your IBM i server.

   Advantages: Simple to use; no database connection needed; no additional user/pw authentication. 
   Disadvantages: Runs under web server user (QTMHHTTP by default), which you may or may not want; forks a new job; only works locally on the same IBM i as XMLSERVICE.

   Note: If you already have a database connection, it would be more efficient to pass that in than to use local.
*/
// Example (the parameters of getInstance are the parts to look at)
require_once('ToolkitApi/ToolkitService.php');
try {
	$tkobj = ToolkitService::getInstance("", "", "", "local");
	$res = $tkobj->CLInteractiveCommand("DSPLIBL"); // just one type of command to run
	var_dump($res);
} catch (Exception $e) {
	echo $e->getMessage(), "\n";
}
