<?php
namespace ToolkitApi;

/**
 * Class SystemValues
 *
 * @package ToolkitApi
 */
class SystemValues
{
    private $ToolkitSrvObj;
    private $ErrMessage;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = null){
        if ($ToolkitSrvObj instanceof Toolkit) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;
            return $this;
        } else {
            return false;
        }
    }

    /**
     *
     * @todo Deprecate setConnection in future.
     *
     * @param $dbname
     * @param $user
     * @param $pass
     */
    public function setConnection ($dbname , $user, $pass)
    {
        if (!$this->ToolkitSrvObj instanceof Toolkit) {
            $this->ToolkitSrvObj = new Toolkit($dbname, $user, $pass);
        }
    }

    /**
     * @return array|bool
     */
    public function systemValuesList()
    {
        if (!$this->ToolkitSrvObj instanceof Toolkit) {
            return false;
        }

        $tmparray = $this->ToolkitSrvObj->CLInteractiveCommand ('WRKSYSVAL OUTPUT(*PRINT)');

        if (isset($tmparray)) {
            $i = 4;
            while (isset($tmparray [$i + 1])) {
                $tmparr = trim($tmparray [++$i]);
                if (substr($tmparr, 0, 1) == 'Q') {
                    $len = strlen ($tmparr);
                    $sysvals [] = array('Name'         => substr ($tmparr, 0, 10),
                        'CurrentValue' => substr ($tmparr, 15, 32),
                        'ShippedValue' => substr ($tmparr, 47, 32),
                        'Description'  => substr ($tmparr, 79, ($len - 79)));
                }
            }
            return $sysvals;
        } else {
            return false;
        }
    }

    /**
     * @todo QWCRSVAL to work with 2 tiers while retaining good performance
     *
     * @param $sysValueName
     */
    public function getSystemValue($sysValueName)
    {
        if (!$this->ToolkitSrvObj instanceof Toolkit) {
            return false;
        }

        $Err = ' ';
        $SysValue = ' ';
        $params [] = $this->ToolkitSrvObj->AddParameterChar('both', 1, "ErrorCode", 'errorcode', $Err);
        $params [] = $this->ToolkitSrvObj->AddParameterChar('both', 10, "SysValName", 'sysvalname', $sysValueName);
        $params [] = $this->ToolkitSrvObj->AddParameterChar('both', 1024, "SysValue", 'sysval', $SysValue);
        $retArr = $this->ToolkitSrvObj->PgmCall(ZSTOOLKITPGM, $this->ToolkitSrvObj->getOption('HelperLib'), $params, NULL, array('func' => 'RTVSYSVAL'));

        if ($retArr !== false  && isset($retArr['io_param'])) {
            $sysval = $retArr['io_param'];
            if (isset($sysval['sysvalname'])) {
                return $sysval['sysval'];
            } else {
                $this->setError($sysval['errorcode']);
            }
        }
    }

    /**
     * @return mixed
     */
    public function getError() {
        return $this->ErrMessage;
    }

    /**
     * @param $errCode
     */
    private  function setError($errCode)
    {
        if ($errCode == '') /*clear error message*/ {
            $this->ErrMessage = '';
            return;
        }

        if ($errCode == '1') {
            $this->ErrMessage = 'System value data is not available.';
        } else {
            if ($errCode == '2') {
                $this->ErrMessage = 'System value can not be retrieved. ';
            }
        }
    }
}
