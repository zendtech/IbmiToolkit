<?php
/* 
 * connect_pdo_odbc.php
 * Create and test a toolkit connection using an existing pdo_odbc connection as its transport.
 * As of 11 Sept 2020, Toolkit does not create its own PDO connection, so you must pass in an existing one (a good practice anyway).
 *
 * IBM i ODBC connection string keywords: 
 * https://www.ibm.com/support/knowledgecenter/en/ssw_ibm_i_71/rzaik/rzaikconnstrkeywordsgeneralprop.htm
 * https://www.ibm.com/support/knowledgecenter/en/ssw_ibm_i_71/rzaik/rzaikconnstrkeywordsservprop.htm
 */

// (adjust path as needed or use autoloader)
require_once('ToolkitApi/ToolkitService.php');

// (adjust parameters as required)
$user = 'myuser'; 
$pw = 'mypass';
$namingMode = 1; // 0 = SQL naming. 1 = System naming (enable library lists)
// Default *LOCAL DSN example. 
// If you wish to use a different host not specified by a DSN in your ODBC config, either create the DSN or specify the driver and host in your connection string.
$dsn = '*LOCAL';
$connString = "odbc:DSN=$dsn;NAM=$namingMode";
$persistence = false;
$dbConnectionType = 'pdo';

// create db connection
try {
    $dbconn = new PDO($connString, $user, $pw, array(
                      PDO::ATTR_PERSISTENT => $persistence,
                      PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
    ));
} catch (PDOException $e) {
    die ('PDO connection failed: ' . $e->getMessage());
}

// use your existing database connection when creating toolkit object
$tkobj = ToolkitService::getInstance($dbconn, $namingMode, '', $dbConnectionType);

// simplest is stateless mode, but could set InternalKey to a directory name instead for a stateful connection.
$tkobj->setOptions(array('stateless' => true));

// (Example command to test toolkit)
// run DSPLIBL and return screen as an array
$result = $tkobj->ClInteractiveCommand('DSPLIBL');
print_r($result);
