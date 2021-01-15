<?php

/*
 * This sample uses the new SSH transport to connect to a remote IBM i system.
 * You need the package "itoolkit-utils" (for xmlservice-cli) installed on the remote system.
 * On the system running the Toolkit, you need a version of the ssh2 PECL extension installed that has "ssh2_send_eof".
 * (Versions newer than 1.2 should include it.)
 * This uses the API change to provide flexibility in configuring the transport without having to set global options.
 * For example, you can three kinds of authentication methods:
 * - password (default): use the password like other methods.
 * - keyfile: SSH keys (with optional passphrase)
 * - agent: Use the running SSH agent (no need to embed credentials)
 * Alternatively, you can pass in an existing ssh2 resource that you've set up yourself,
 * in case the Toolkit doesn't support the scenario you want.
 */

require_once('ToolkitApi/ToolkitService.php');
try {
	// Letting Toolkit set it up for you:
	$options = array(
		// If this is omitted or set to false, then the Toolkit won't validate the remote fingerprint.
		// You can set this to gain additional security (that the remote server isn't an impostor).
		// A hex string is used. You can get the fingerprint in the required form by running:
		//   $conn = ssh2_connect($server, 22);
		//   echo ssh2_fingerprint($conn, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX);
		"sshFingerprint" => "EADDEA1EB936B3C6F7D7E5A380B6BB607E71259D",
		// By default, the password like other transports is used by default, but you can use another method like so:
		"sshMethod" => "agent",
		// The password is ignored if not using password authentication.
		// No additional parameters are used for the agent.
		// This is ideal because you don't need to embed credentials.
		// Your development environment likely has a working agent too!
		// Want to use SSH keys? Try something like...
		/*
		"sshMethod" => "keyfile",
		"sshPublicKeyFile" => "/home/calvin/.ssh/id_rsa.pub",
		"sshPrivateKeyFile" => "/home/calvin/.ssh/id_rsa",
		// (optional)
		"sshPassphrase" => "dubnobasswithmyheadman"
		 */
		// Override the port number
		"sshPort" => 22,
	);
	$tkobj = ToolkitService::getInstance("hostname", "username", '', "ssh", $options);
	$res = $tkobj->CLInteractiveCommand("DSPLIBL");
	var_dump($res);
	// Setting it up yourself:
	$sshConn = ssh2_connect(hostname, 2222); // for example, custom port
	ssh2_auth_agent($sshConn, "username"); // simple example
	$tkobj = ToolkitService::getInstance($sshConn, "", "", "ssh");
	var_dump($res);
} catch (Exception $e) {
	echo $e->getMessage(), "\n";
}
