<?php
namespace ToolkitApi\CW;

use ToolkitApi\Toolkit;

/**
 * ToolkitServiceCw extends the standard Zend/PHP wrapper
 *                  with specific Compatibility Wrapper (CW) features.
 * @author aseiden
 */
class ToolkitServiceCw extends Toolkit
{
    static $instance = null;

    public function __construct($database, $userOrI5NamingFlag, $password, $extensionPrefix, $isPersistent = false)
    {
        parent::__construct($database, $userOrI5NamingFlag, $password, $extensionPrefix, $isPersistent);
    }

    /**
     * need to define this so we get Cw object and not parent object
     *
     * @param string $databaseNameOrResource
     * @param string $userOrI5NamingFlag
     * @param string $password
     * @param string $extensionPrefix
     * @param bool $isPersistent
     * @param bool $forceNew
     * @return bool|null
     */
    static function getInstance($databaseNameOrResource = '*LOCAL', $userOrI5NamingFlag = '', $password = '', $extensionPrefix = '', $isPersistent = false, $forceNew = false)
    {
        // if we're forcing a new instance, close db conn first if exists.
        if ($forceNew && self::hasInstance() && isset(self::$instance->conn))
        {
            self::$instance->disconnect();
        }

        // if we're forcing a new instance, or an instance hasn't been created yet, create one
        if ($forceNew || self::$instance == NULL) {
            $toolkitService = __CLASS__;
            self::$instance = new $toolkitService($databaseNameOrResource, $userOrI5NamingFlag, $password, $extensionPrefix, $isPersistent);
        }

        if (self::$instance) {
            // instance exists
            return self::$instance;
        } else {
            // some problem
            return false;
        }
    }

    /**
     * Return true if an instance of this object has already been created.
     * Return false if no instance has been instantiated.
     *
     * Same as the method in ToolkitApi\Toolkit.
     * Cwclasses has its own instance variable so we need this method here, too.
     *
     * Useful when users need to know if a "toolkit connection" has already been made.
     * Usage:
     * $isConnected = Toolkit::hasInstance();
     *
     * @return boolean
     */
    static function hasInstance()
    {
        if (isset(self::$instance) && is_object(self::$instance)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $num
     */
    public function setPrivateConnNum($num)
    {
        $this->_privateConnNum = $num;
    }

    /**
     * @return null
     */
    public function getPrivateConnNum()
    {
        return $this->_privateConnNum;
    }

    /**
     * establish whether the connection is new or not. Used by i5_get_property()
     *
     * @param bool $isNew
     */
    public function setIsNewConn($isNew = true)
    {
        $this->_isNewConn = $isNew;
    }

    /**
     * @return bool
     */
    public function isNewConn()
    {
        return $this->_isNewConn;
    }

    /**
     * when script ends, non-persistent connection should close
     */
    public function __destruct()
    {
        /* call to disconnect()  function to down connection */
        $disconnect = false;

        // CW only: if connection is in a separate job and nonpersistent, end job (mimicking behavior of old toolkit)
        if (!$this->isStateless() && !$this->getIsPersistent()) {
            $disconnect = true;
        }

        if ($disconnect) {
            $this->disconnect();
        }

        // parent destruct clears the object
//        parent::__destruct();

        // need to clear extended cwclasses instance as well.
        if ($disconnect) {
            self::$instance = null;
        }
    }

    /**
     * Get the most recent system error code, if available.
     * TODO this may not work because CPFs are done at a class level (data areas etc.)
     */
    public function getCPFErr()
    {
        // TODO get from Verify_CPFError or the other one
        return $this->CPFErr;
    }

    /**
     * After calling a program or command, we can export output as variables.
     * This method creates an array that can later be extracted into variables.
     * param array $outputDesc   Format of output params 'CODE'=>'CODEvar' where the value becomes a PHP var name
     * param array $outputValues     Optional. Array of output values to export
     *
     * @param array $outputDesc
     * @param array $outputValues
     * @return boolean  true on success, false on some error
     */
    public function setOutputVarsToExport(array $outputDesc, array $outputValues)
    {
        // for each piece of output, export it according to var name given in $outputDesc.
        if ($outputValues && is_array($outputValues) && count($outputValues)) {
            // initialize
            $this->_outputVarsToExport = array();

            foreach ($outputValues as $paramName=>$value) {
                if (isset($outputDesc[$paramName])) {
                    $variableNameToExport = $outputDesc[$paramName];
                    // create the global variable named by $ varName.

                    $GLOBALS[$variableNameToExport] = $value;

                    $this->_outputVarsToExport[$variableNameToExport] = $value;
                }
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function getOutputVarsToExport()
    {
        return $this->_outputVarsToExport;
    }

    /**
     * pass in array of job attributes => values to update in the current job.
     * returns true on success, false on failure (failure probably means lack of authority).
     *
     * @param array $attrs
     * @return bool
     */
    public function changeJob(array $attrs)
    {
        $cmdString = 'CHGJOB';
        $success = i5_command($cmdString, $attrs);
        return $success;
    }
}