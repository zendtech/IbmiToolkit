<?php
namespace ToolkitApi;

/**
 * Class SpooledFiles
 *
 * @package ToolkitApi
 */
class SpooledFiles
{
    private $SPOOLFILELIST_SIZE = 98;
    private $ToolkitSrvObj;
    private $TMPFName;          /*multi user*/
    private $TmpLib = DFTLIB;   /*multi user. QTemp impossible to use(?)Opened in amother job*/
    private $TmpUserSpace;      /*LISTSPLF function filles this object by the spool file list information*/
    private $ErrMessage;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     * @param null $UserLib
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = NULL, $UserLib = NULL)
    {
        if ($ToolkitSrvObj instanceof Toolkit) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;

            // @todo do not assume a specific plug size.
            $this->ToolkitSrvObj->setOptions(array('plugSize'=>'1M'));

            $this->TMPFName = $this->ToolkitSrvObj->generate_name();

            //do not use a QTEMP as temporary library. [not sure why not--A.S.]
            if ($UserLib != NULL && strcmp($UserLib, "QGPL")) {
                $this->TmpLib = $UserLib;
            }

            return $this;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        // empty
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->ErrMessage;
    }

    /**
     * @param string $UserName
     * @return array|bool|null
     * @throws \Exception
     */
    public function GetSPLList($UserName = "*CURRENT")
    {
        $list = NULL;
        $this->clearError();

        $this->TmpUserSpace = new TmpUserSpace($this->ToolkitSrvObj, DFTLIB);

        $UsFullName = $this->TmpUserSpace->getUSFullName();
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 20, "User Space Name", 'userspacename', $UsFullName);
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 10, "User Name", 'username', $UserName);
        //$retval[]= $this->ToolkitSrvObj->AddReturnParameter('10i0', 'retval', 0);
        $retval[]= $this->ToolkitSrvObj->AddParameterInt32('out', 'retval', 'retval', 0);

        $this->ToolkitSrvObj->PgmCall(ZSTOOLKITPGM, $this->ToolkitSrvObj->getOption('HelperLib'),
            $params, $retval,
            array ('func' => 'LISTSPLF' ) );

        if (!$this->ToolkitSrvObj->isError()) {
            sleep(1);
            $readlen = $this->TmpUserSpace->RetrieveUserSpaceSize();
            $SpoolFilesList = $this->TmpUserSpace->ReadUserSpace(1, $readlen);
            $this->TmpUserSpace->DeleteUserSpace();

            if (trim($SpoolFilesList) > 0)
            {
                $list = $this->buildSpoolList(str_split($SpoolFilesList, $this->SPOOLFILELIST_SIZE));
                return $list;
            } else {
                $this->setError("No spooled files found for ".$UserName);
                return NULL; //no spollfiles!
            }
        } else {
            $this->setError($this->ToolkitSrvObj->getLastError());
        }

        unset($this->TmpUserSpace);
        return  false;
    }

    /**
     * @param $SpoolInfListArr
     * @return array|null
     */
    private function buildSpoolList($SpoolInfListArr )
    {
        $list= NULL;

        foreach($SpoolInfListArr as $tmparr)
        {
            $list[] = array('Number' => substr($tmparr, 0, 4),
                'Name' => substr($tmparr, 4, 10),
                'JobNumber' => substr($tmparr, 14, 6),
                'JobName' =>  substr($tmparr, 20,10),
                'JobUser' =>  substr($tmparr, 30, 10),
                'UserData' =>  substr($tmparr, 40, 10),
                'QueueName' =>  substr($tmparr, 50, 20),
                'TotalPages' =>  substr($tmparr, 70, 5),
                'Status' =>  substr($tmparr, 75, 10),
                'DateOpen'=> substr($tmparr, 85, 7),
                'TimeOpen'=>substr($tmparr, 92, 6)
            );
        }

        return $list;
    }

    /**
     * multi user using!
     *
     * @param $SplfName
     * @param $SplfNbr
     * @param $JobNmbr
     * @param $JobName
     * @param $JobUser
     * @param string $TMPFName
     * @return mixed|string
     */
    public function GetSPLF($SplfName , $SplfNbr, $JobNmbr, $JobName, $JobUser, $TMPFName='')
    {
        $this->clearError();
        if ($TMPFName != '') {
            $this->TMPFName = $TMPFName;
        }

        // @todo under the flag for current Object???
        $crtf = "CRTDUPOBJ OBJ(ZSF255) FROMLIB(" . $this->ToolkitSrvObj->getOption('HelperLib') . ") OBJTYPE(*FILE) TOLIB($this->TmpLib) NEWOBJ($this->TMPFName)";
        $this->ToolkitSrvObj->ClCommandWithCpf($crtf); // was ClCommand

        // clear the temp file
        $clrpfm = "CLRPFM $this->TmpLib/$this->TMPFName";
        $this->ToolkitSrvObj->ClCommandWithCpf($clrpfm); // these all were CLCommand but we need CPFs.

        // copy spooled file to temp file
        $cmd = sprintf ("CPYSPLF FILE(%s) TOFILE($this->TmpLib/$this->TMPFName) JOB(%s/%s/%s) SPLNBR(%s)",
            $SplfName, trim($JobNmbr), trim($JobUser), trim($JobName), $SplfNbr);

        $this->ToolkitSrvObj->ClCommandWithCpf($cmd);
        sleep(1);
        // read the data from temp file
        $Txt = $this->ReadSPLFData();

        // delete temp file
        $dltf   = "DLTF FILE($this->TmpLib/$this->TMPFName)";
        $this->ToolkitSrvObj->ClCommandWithCpf($dltf);

        return $Txt;
    }

    /**
     * @return mixed|string
     * @throws \Exception
     */
    private function ReadSPLFData()
    {
        $Txt='';
        $schemaSep = $this->ToolkitSrvObj->getOption('schemaSep'); // . or /
        $stmt = "SELECT ZSF255 FROM {$this->TmpLib}{$schemaSep}{$this->TMPFName} FOR FETCH ONLY";

        try {
            $Txt = $this->ToolkitSrvObj->executeQuery($stmt);
        } catch(Exception $e) {
            $this->setError("ReadSPLFData() error:" . $e->getMessage());
        }

        return $Txt;
    }

    /**
     * @param $msg
     */
    private function setError($msg)
    {
        $this->ErrMessage = $msg;
    }

    /**
     *
     */
    private function clearError()
    {
        $this->ErrMessage = '';
    }
}
