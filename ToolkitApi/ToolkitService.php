<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

use ToolkitApi\Toolkit;
/**
 * Class ToolkitService
 *
 * @package ToolkitApi
 */
class ToolkitService
{

    /**
     * need to define this so we get Cw object and not parent object
     *
     * @param string $databaseNameOrResource
     * @param string $userOrI5NamingFlag
     * @param string $password
     * @param string $transportType
     * @param bool|array $isPersistent
     * @return Toolkit
     */
    static function getInstance($databaseNameOrResource = '*LOCAL', $userOrI5NamingFlag = '', $password = '', $transportType = '', $isPersistent = false)
    {
        return new Toolkit($databaseNameOrResource, $userOrI5NamingFlag, $password, $transportType, $isPersistent);
    }

}

/**
 * non-OO logging function ported from CW
 *
 * For CW logging.
 *
 * @param $msg
 */
function logThis($msg)
{
    $logFile = Toolkit::getConfigValue('log','logfile');
    if ($logFile) {
        // it's configured so let's write to it. ("3" means append to a specific file)
        $formattedMsg = "\n" . microDateTime() . ' ' . $msg;
        error_log($formattedMsg, 3, $logFile);
    }
}

/**
 * Used in logThis() above
 *
 * @return string
 */
function microDateTime()
{
    list($microSec, $timeStamp) = explode(" ", microtime());
    return date('j M Y H:i:', $timeStamp) . (date('s', $timeStamp) + $microSec);
}
