<?php
namespace ToolkitApi;

/**
 * Class JobLogs
 *
 * @package ToolkitApi
 */
class JobLogs
{
    private $JOBLIST_RECORD_SIZE = 60;
    private $JOBLOG_RECORD_SIZE  = 80;
    private $Temp_US_Size        = 128000;
    private $ToolkitSrvObj;
    private $TmpUserSpace;
    private $TmpLib = DFTLIB;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     * @param string $tmpUSLib
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = null, $tmpUSLib = DFTLIB)
    {
        if ($ToolkitSrvObj instanceof Toolkit ) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;

            // @todo do not assume a specific plug size.
            $this->ToolkitSrvObj->setOptions(array('plugSize'=>'1M'));

            //do not use a QTEMP as temporary library.
            if (strcmp($tmpUSLib, "QGPL")) {
                $this->TmpLib = $tmpUSLib;
            }

            return $this;
        } else {
            return false;
        }
    }

    /**
     * @param $newSize
     */
    public function setTemp_US_Size($newSize) {
        if ($newSize > 128000) {
            $this->Temp_US_Size = $newSize;
        }
    }

    /**
     * @param null $user
     * @param string $jobstatus
     * @return array|bool
     * @throws \Exception
     */
    public function JobList($user = NULL, $jobstatus = "*ACTIVE")
    {
        if ($user != NULL) {
            $ForUser = sprintf("%-10s", $user);
        } else {
            $ForUser = "*CURRENT  ";
        }

        $JobStatus = sprintf("%-10s", $jobstatus);
        $this->TmpUserSpace = new TmpUserSpace($this->ToolkitSrvObj, $this->TmpLib, $this->Temp_US_Size);
        $FullUSName = $this->TmpUserSpace->getUSFullName();

        $params[]=$this->ToolkitSrvObj->AddParameterChar('input', 20, 'USER SPACE NAME', 'userspacename', $FullUSName);
        $params[]=$this->ToolkitSrvObj->AddParameterChar('input', 10, 'USER NAME', 'username', $ForUser);
        $params[]=$this->ToolkitSrvObj->AddParameterChar('input', 10, 'Job status', 'jobstatus', $JobStatus);

        $ret = $this->ToolkitSrvObj->PgmCall(ZSTOOLKITPGM, $this->ToolkitSrvObj->getOption('HelperLib'), $params, NULL, array ('func' => 'JOBLIST'));
        sleep(1);
        $JobList  = $this->TmpUserSpace->ReadUserSpace(1, $this->TmpUserSpace->RetrieveUserSpaceSize());

        $this->TmpUserSpace->DeleteUserSpace();
        unset($this->TmpUserSpace);
        if (trim($JobList)!='') {
            return (str_split($JobList, $this->JOBLIST_RECORD_SIZE));
        } else {
            return false;
        }
    }

    /**
     * @param $JobListString
     * @return array
     */
    public function createJobListArray($JobListString)
    {
        $JobList = array();
        if (is_array($JobListString)) {
            $i = 0;
            foreach ($JobListString as $element)
            {
                $el = str_split($element, 10);
                $JobList[$i]['JOBNAME']  =$el[0];
                $JobList[$i]['JOBUSER']  =$el[1];
                $JobList[$i]['JOBNUMBER']=$el[2];
                $JobList[$i]['JOBSTATUS']=$el[3];
                $JobList[$i]['JOBSUBS']  =(isset($el[4])) ? $el[4] : ''; // avoid undefined offset
                $JobList[$i]['ACTJOBSTATUS']=(isset($el[5])) ? $el[5] : ''; // avoid undefined offset
                $i++;
            }
        }

        return $JobList;
    }

    /**
     * it seems that all three parms must be entered; it's for a specific job.
     *
     * @param $JobName
     * @param $JobUser
     * @param $JobNumber
     * @param string $direction
     * @return array|bool
     * @throws \Exception
     */
    public function JobLog($JobName, $JobUser, $JobNumber, $direction = 'L')
    {
        if ($JobName=='' ||$JobUser=='' || $JobNumber == '') {
            return false;
        }

        $this->TmpUserSpace = new TmpUserSpace($this->ToolkitSrvObj, $this->TmpLib);
        $FullUSName = $this->TmpUserSpace->getUSFullName();
        $InputArray[]=$this->ToolkitSrvObj->AddParameterChar('input', 20, 'USER SPACE NAME', 'userspacename', $FullUSName);
        $InputArray[]=$this->ToolkitSrvObj->AddParameterChar('input', 10, 'JOB NAME', 'jobname', $JobName);
        $InputArray[]=$this->ToolkitSrvObj->AddParameterChar('input', 10, 'USER NAME', 'username', $JobUser);
        //L from the last log message to the first one
        //from the first message to the last one
        $dir = 'L';

        if (strtoupper($direction) == "N") {
            $dir = $direction;
        }

        $InputArray[]=$this->ToolkitSrvObj->AddParameterChar('input', 6, 'Job Number', 'jobnumber', $JobNumber);
        //From the Last - "L"
        $InputArray[]=$this->ToolkitSrvObj->AddParameterChar('input', 1, 'Direction', 'direction', $dir);
        $ret_code ='0';
        $InputArray[]=$this->ToolkitSrvObj->AddParameterChar('both', 1, 'retcode', 'retcode', $ret_code);

        $OutputArray = $this->ToolkitSrvObj->PgmCall(ZSTOOLKITPGM, $this->ToolkitSrvObj->getOption('HelperLib'), $InputArray, NULL, array('func'=>'JOBLOGINFO'));
        if (isset($OutputArray['io_param']['retcode'])) {
            //may be authorization problem
            if ($OutputArray['io_param']['retcode'] == '1') {
                //No data created in US.
                return false;
            }
        }

        sleep(1);

        $JobLogRows = $this->TmpUserSpace->ReadUserSpace(1, $this->TmpUserSpace->RetrieveUserSpaceSize());
        $this->TmpUserSpace->DeleteUserSpace();
        unset ($this->TmpUserSpace);

        if (trim($JobLogRows) != '') {
            $logArray = str_split($JobLogRows, $this->JOBLOG_RECORD_SIZE);
            return $logArray;
        } else {
            return false;
        }
    }

    /**
     *
     * @todo currentJobLog. retrieve log for it in either direction.
     *
     * @param $JobName
     * @param $JobUser
     * @param $JobNumber
     * @return array|bool
     */
    public function GetJobInfo($JobName, $JobUser, $JobNumber)
    {
        /**
         * used format:JOBI0200
         */
        if ($JobName=='' ||$JobUser=='' || $JobNumber == '') {
            return false; //nothing to show
        }

        $reciever =" ";
        $jobName26 = sprintf("%-10s%-10s%-6s", $JobName, $JobUser, $JobNumber);

        // changed
        $receiverSize = 1000; // 200
        $params[] =  Toolkit::AddParameterChar('input', 26, "QualifiedJobName", 'JobName', trim($jobName26));
        $params[] =  Toolkit::AddParameterChar('both', $receiverSize, "reciever", 'reciever', $reciever);
        $ret = $this->ToolkitSrvObj->PgmCall(ZSTOOLKITPGM, $this->ToolkitSrvObj->getOption('HelperLib'), $params, NULL, array('func'=>'GETJOBINFO'));
        if ($ret && trim($ret['io_param']['reciever'])!='') {
            return ($this->parseJobInfString($ret['io_param']['reciever']));
        }

        return false;
    }

    /**
     * @param $jobinfo
     * @return array
     */
    private function parseJobInfString($jobinfo)
    {
        return array(
            'JobName'=>substr($jobinfo, 0, 10),
            'JobUser'=>substr($jobinfo, 10, 10),
            'JobNumber'=>substr($jobinfo, 20, 6),
            'JobStatus'=>substr($jobinfo, 26, 10),
            'ActJobStat'=>substr($jobinfo, 36, 4),
            'JobType'=>substr($jobinfo, 40, 1),
            'JobRunPriority'=>substr($jobinfo, 41, 5),
            'JobTimeSlice'=>substr($jobinfo, 46, 5),
            'PoolId'=>substr($jobinfo, 51, 5),
            'Functionname'=>substr($jobinfo, 56, 10),
        );
    }
}
