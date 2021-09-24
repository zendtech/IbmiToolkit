<?php
namespace ToolkitApi;

class TmpUserSpace extends UserSpace
{
    private $TMPUSName;

    /**
     * @param ToolkitInterface $Toolkit
     * @param string $UsLib
     * @param int $DftUsSize
     * @throws \Exception
     */
    function __construct($Toolkit, $UsLib = DFTLIB, $DftUsSize = 32700)
    {
        parent::__construct($Toolkit);

        if (!$this->CreateUserSpace($this->generate_name(), $UsLib, $DftUsSize)) {
            throw new \Exception($this->getError());
        } else {
            $this->TMPUSName  = sprintf("%-10s%-10s", $this->getUSName(), $UsLib);
        }

        return $this;
    }

    /**
     * @todo do not delete
     */
    function __destruct()
    {
//        $this->DeleteUserSpace();
    }
}
