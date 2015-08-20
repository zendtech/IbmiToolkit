<?php
require_once 'autoload.php';

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
     * @param bool $isPersistent
     * @return bool|null
     */
    static function getInstance($databaseNameOrResource = '*LOCAL', $userOrI5NamingFlag = '', $password = '', $transportType = '', $isPersistent = false)
    {
        return new Toolkit($databaseNameOrResource, $userOrI5NamingFlag, $password, $transportType, $isPersistent);
    }

}