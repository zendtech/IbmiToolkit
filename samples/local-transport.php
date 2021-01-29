<?php
// The local transport calls a command on the system to talk to XMLSERVICE.
// This has the advantage of not needing any credentials or database overhead.
// On IBM i, install the "itoolkit-utils" package from Yum.
require_once('ToolkitApi/ToolkitService.php');
try {
	$tkobj = ToolkitService::getInstance("", "", "", "local");
	$res = $tkobj->CLInteractiveCommand("DSPLIBL");
	var_dump($res);
} catch (Exception $e) {
	echo $e->getMessage(), "\n";
}
