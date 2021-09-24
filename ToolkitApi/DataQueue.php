<?php
namespace ToolkitApi;

/**
 * Class DataQueue
 *
 * @package ToolkitApi
 */
class DataQueue
{
    private $Toolkit;
    private $DataQueueName;
    private $DataQueueLib;
    private $CPFErr = '0000000';
    private $ErrMessage;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof Toolkit) {
            $this->Toolkit = $ToolkitSrvObj ;
            return $this;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->ErrMessage;
    }

    /**
     * @param $DataQName
     * @param $DataQLib
     * @param int $MaxLength
     * @param string $Sequence
     * @param int $KeyLength
     * @param string $Authority
     * @param int $QSizeMaxNumEntries
     * @param int $QSizeInitNumEntries
     * @return bool
     * @throws \Exception
     */
    public function CreateDataQ($DataQName, $DataQLib,
                                $MaxLength=128,
                                $Sequence ='*FIFO', $KeyLength=0,
                                $Authority = '*LIBCRTAUT',
                                $QSizeMaxNumEntries=32999, $QSizeInitNumEntries = 16)
    {
        $this->DataQueueName = $DataQName;
        $this->DataQueueLib = $DataQLib;

        if (strcmp(strtoupper($Sequence), '*KEYED') == 0||
            strcmp(strtoupper($Sequence), '*FIFO')== 0  ||
            strcmp(strtoupper($Sequence), '*LIFO')== 0) {
            $DataQType = $Sequence;
        } else {
            return $this->SetError("Invalid Data Queue type parameter");
        }

        $KeyedSetting = '';

        if (strcmp(strtoupper($Sequence), '*KEYED') == 0) {
            $DQKeylen = min($KeyLength, 256 );
            $KeyedSetting =  "KEYLEN($DQKeylen)";
        }

        // @todo validation: if $KeyLength supplied, sequence must be *KEYED.

        if (is_integer($QSizeMaxNumEntries)) {
            $MaxQSize = $QSizeMaxNumEntries;
        } else {
            if (strcmp($QSizeMaxNumEntries, '*MAX16MB') == 0 || strcmp($QSizeMaxNumEntries, '*MAX2GB')== 0) {
                $MaxQSize = (string) $QSizeMaxNumEntries;
            }
        }

        if ($QSizeInitNumEntries > 0) {
            $InitEntryies = $QSizeInitNumEntries;
        }

        $AdditionalSetting = sprintf("$KeyedSetting SENDERID(*YES) SIZE(%s %d)", $MaxQSize, $InitEntryies);

        ($MaxLength > 64512) ? $MaxLen = 64512: $MaxLen = $MaxLength;

        $cmd  = sprintf("QSYS/CRTDTAQ DTAQ(%s/%s) MAXLEN(%s) SEQ(%s) %s  AUT(%s)",
            $this->DataQueueLib,$this->DataQueueName,
            $MaxLen, $DataQType, $AdditionalSetting, $Authority);

        if (!$this->Toolkit->CLCommand($cmd)) {
            $this->ErrMessage =  "Create Data Queue failed.". $this->Toolkit->getLastError();
            throw new \Exception($this->ErrMessage);
        }
        return true;
    }

    /**
     * @param string $DataQName
     * @param string $DataQLib
     * @return bool
     * @throws \Exception
     */
    public function DeleteDQ($DataQName ='', $DataQLib = '')
    {
        $cmd = sprintf("QSYS/DLTDTAQ DTAQ(%s/%s)",
            ($DataQLib != '' ? $DataQLib : $this->DataQueueLib),
            ($DataQName != NULL ? $DataQName : $this->DataQueueName));

        if (!$this->Toolkit->CLCommand($cmd)) {
            $this->ErrMessage =  "Delete Data Queue failed.". $this->Toolkit->getLastError();
            throw new \Exception($this->ErrMessage);
        }

        return true;
    }

    /**
     * Correct spelling with this alias
     *
     * @param $WaitTime
     * @param string $KeyOrder
     * @param int $KeyLength
     * @param string $KeyData
     * @param string $WithRemoveMsg
     * @return bool
     */
    public function receiveDataQueue($WaitTime, $KeyOrder = '', $KeyLength = 0, $KeyData = '', $WithRemoveMsg = 'N')
    {
        // call misspelled one
        return $this->receieveDataQueue($WaitTime, $KeyOrder, $KeyLength, $KeyData, $WithRemoveMsg);
    }

    /**
     * @param $WaitTime
     * @param string $KeyOrder
     * @param int $KeyLength
     * @param string $KeyData
     * @param string $WithRemoveMsg
     * @return bool
     */
    public function receieveDataQueue($WaitTime, $KeyOrder = '', $KeyLength = 0, $KeyData = '', $WithRemoveMsg = 'N')
    {
        // uses QRCVDTAQ API
        // http://publib.boulder.ibm.com/infocenter/iseries/v5r3/index.jsp?topic=%2Fapis%2Fqrcvdtaq.htm

        $params [] = $this->Toolkit->AddParameterChar('in', 10, 'dqname', 'dqname', $this->DataQueueName);
        $params [] = $this->Toolkit->AddParameterChar('in', 10, 'dqlib', 'dqlib', $this->DataQueueLib);

        // @todo do not hard-code data size. Use system of labels as allowed by XMLSERVICE (as done in CW's i5_dtaq_receive).
        $DataLen = 300;
        $Data = ' ';

        $params [] = $this->Toolkit->AddParameterPackDec('out', 5, 0, 'datalen', 'datalen', $DataLen); // @todo this is output only so no need to specify a value
        $params [] = $this->Toolkit->AddParameterChar('out', (int) $DataLen, 'datavalue', 'datavalue', $Data); // @todo this is output only so no need to specify a value.

        // Wait time: < 0 waits forever. 0 process immed. > 0 is number of seconds to wait.
        $params [] = $this->Toolkit->AddParameterPackDec('in', 5, 0, 'waittime', 'waittime', $WaitTime);

        if (!$KeyLength) {
            // 0, make order, length and data also zero or blank, so thatthey'll be ignored by API. Must send them, though.

            // if an unkeyed queue, API still expects to receive key info,
            // but it must be blank and zero.
            $KeyOrder = ''; // e.g. EQ, other operators, or blank
            $KeyLength = 0;
            $KeyData = '';
        }

        $params [] = $this->Toolkit->AddParameterChar('in', 2, 'keydataorder', 'keydataorder', $KeyOrder);
        $params [] = $this->Toolkit->AddParameterPackDec('in', 3, 0, 'keydatalen', 'keydatalen', $KeyLength);
        $params [] = $this->Toolkit->AddParameterChar('both', (int) $KeyLength, 'keydata', 'keydata', $KeyData);

        $params [] = $this->Toolkit->AddParameterPackDec('in', 3, 0, 'senderinflen', 'senderinflen', 44);
        // Sender info may contain packed data, so don't receive it till we can put it in a data structure.
        // @todo use a data structure to receive sender info as defined in QRCVDTAQ spec.
        $params [] = $this->Toolkit->AddParameterHole(44, 'senderinf');

        // whether to remove message from data queue
        if ($WithRemoveMsg == 'N') {
            $Remove= '*NO       ';
        } else {
            $Remove= '*YES      ';
        }

        $params[] = $this->Toolkit->AddParameterChar('in', 10, 'remove', 'remove', $Remove);
        // @todo note from API manual: If this parameter is not specified, the entire message will be copied into the receiver variable.
        $params[] = $this->Toolkit->AddParameterPackDec('in', 5, 0, 'size of data receiver', 'receiverSize', $DataLen);

        $params[] =  $this->Toolkit->AddErrorDataStructZeroBytes(); // so errors bubble up to joblog

        $retPgmArr = $this->Toolkit->PgmCall('QRCVDTAQ', 'QSYS', $params);
        if (isset($retPgmArr['io_param'])) {
            $DQData = $retPgmArr['io_param'];

            if ($DQData['datalen']> 0) {
                return $DQData;
            }
        }

        return false;
    }

    /**
     * @param $DataQName
     * @param $DataQLib
     */
    public function SetDataQName($DataQName, $DataQLib)
    {
        $this->DataQueueName = $DataQName;
        $this->DataQueueLib = $DataQLib;
    }

    /**
     * @param $DataLen
     * @param $Data
     * @param int $KeyLength
     * @param string $KeyData
     * @return array|bool
     */
    public function SendDataQueue($DataLen, $Data, $KeyLength=0, $KeyData='')
    {
        // QSNDDTAQ API:
        // http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Fapis%2Fqsnddtaq.htm

        $params[] = $this->Toolkit->AddParameterChar('in', 10, 'dqname', 'dqname', $this->DataQueueName);
        $params[] = $this->Toolkit->AddParameterChar('in', 10, 'dqlib', 'dqlib',$this->DataQueueLib);

        $params[] = $this->Toolkit->AddParameterPackDec('in', 5, 0, 'datalen', 'datalen', $DataLen, null);
        $params[] = $this->Toolkit->AddParameterChar('in', $DataLen, 'datavalue','datavalue',  $Data);
        if ($KeyLength > 0 ) {
            $params[] = $this->Toolkit->AddParameterPackDec('in', 3, 0, 'keydatalen', 'keydatalen', $KeyLength, null);
            $params[] = $this->Toolkit->AddParameterChar('in', $KeyLength, 'keydata', 'keydata',  $KeyData);
        }

        $ret = $this->Toolkit->PgmCall('QSNDDTAQ', 'QSYS', $params);

        return $ret;
    }

    /**
     * @param string $KeyOrder
     * @param int $KeyLength
     * @param string $KeyData
     * @return bool
     */
    public function ClearDQ($KeyOrder= '', $KeyLength=0, $KeyData='')
    {
        //QCLRDTAQ
        $params[]=$this->Toolkit->AddParameterChar('in', 10, 'dqname', 'dqname', $this->DataQueueName);
        $params[]=$this->Toolkit->AddParameterChar('in', 10, 'dqlib', 'dqlib', $this->DataQueueLib);
        if ($KeyLength > 0) {
            $params[] = $this->Toolkit->AddParameterChar('in', 2, 'keydataorder', 'keydataorder', $KeyOrder);
            $params[] = $this->Toolkit->AddParameterPackDec('in', 3, 0, 'keydatalen', 'keydatalen', $KeyLength);
            $params[] = $this->Toolkit->AddParameterChar('in', ((int)$KeyLength), 'keydata', 'keydata', $KeyData);
            //$params[] = array('ds'=>$this->Toolkit->GenerateErrorParameter());
            $ds =$this->Toolkit->GenerateErrorParameter();
            $params[] = Toolkit::AddDataStruct($ds);
        }

        $retArr  = $this->Toolkit->PgmCall('QCLRDTAQ', 'QSYS', $params);

        if (isset($retArr['exceptId']) && strcmp ($retArr['exceptId'], '0000000')) {
            $this->CPFErr = $retArr['exceptId'];
            $this->ErrMessage ="Clear Data Queue failed. Error: $this->CPFErr";
            return false;
        }

        return true;
    }
}
