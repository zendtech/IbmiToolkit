<?php
include_once 'ToolkitServiceSet.php';
include_once 'autoload.php';

define('CONFIG_FILE', 'toolkit.ini');

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
     * @param bool $isPersistent
     * @return bool|null
     */
    static function getInstance($databaseNameOrResource = '*LOCAL', $userOrI5NamingFlag = '', $password = '', $transportType = '', $isPersistent = false)
    {
        return new ToolkitApi\Toolkit($databaseNameOrResource, $userOrI5NamingFlag, $password, $transportType, $isPersistent);
    }

    /**
     * Destruct
     */
    public function __destruct()
    {
        // if no transport then remove toolkit instance as well.
        if(!isset($this->conn) || $this->conn == null) {
            self::$instance = NULL;
        }
    }
}

/**
 * @todo integrate these functions into toolkit class. Back-ported from CW.
 *
 * keep non-OO functions for backward compatibility and CW support
 *
 * @param $heading
 * @param $key
 * @param null $default
 * @return bool|null
 */
function getConfigValue($heading, $key, $default = null)
{
    return ToolkitService::getConfigValue($heading, $key, $default);
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
    $logFile = getConfigValue('log','logfile');
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
