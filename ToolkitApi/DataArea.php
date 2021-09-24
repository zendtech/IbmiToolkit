<?php
namespace ToolkitApi;

/**
 * Class DataArea
 *
 * @package ToolkitApi
 */
class DataArea
{
    private $DataAreaName =null;
    private $DataAreaLib  =null;
    private $ToolkitSrvObj=null;
    private $ErrMessage;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof Toolkit) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;
            return $this;
        } else {
            return false;
        }
    }

    /**
     * for *char data area. According to create other data area types
     * use CL command
     *
     * @param string $DataAreaName
     * @param string $DataAreaLib *CURLIB is correct, the default with CRTDTAARA.
     * @param int $size
     * @return bool
     * @throws \Exception
     */
    public function createDataArea($DataAreaName = '', $DataAreaLib = "*CURLIB", $size = 2000)
    {
        if ($DataAreaName !='' && $this->DataAreaName == NULL) { /*was not set before*/
            $this->setDataAreaName($DataAreaName , $DataAreaLib);
        }

        if ($size > 2000 || $size <= 0) {
            $dataAreaLen = 2000;
        } else {
            $dataAreaLen = $size;
        }

        $cmd = sprintf("QSYS/CRTDTAARA DTAARA(%s/%s) TYPE(*CHAR) LEN($dataAreaLen)",
            ($DataAreaLib != '' ? $DataAreaLib : $this->DataAreaLib),
            ($DataAreaName !='' ? $DataAreaName : $this->DataAreaName));

        // @todo get CPF code
        if (!$this->ToolkitSrvObj->CLCommand($cmd)) {
            $this->ErrMessage =  "Create Data Area failed." . $this->ToolkitSrvObj->getLastError();
            throw new \Exception($this->ErrMessage);
        }

        return true;
    }

    /**
     * @return string
     */
    private function getAPIDataAreaName()
    {
        return  (sprintf("%-10s%-10s", $this->DataAreaName, $this->DataAreaLib));
    }

    /**
     * @return null|string
     */
    protected function getQualifiedDataAreaName() {
        // return dtaara name in lib/dtaara format.
        if ($this->DataAreaLib) {
            return "{$this->DataAreaLib}/{$this->DataAreaName}";
        } else {
            // no library (e.g. *LDA dtaara). Return only dtaara name.
            return $this->DataAreaName;
        }
    }

    /**
     * *LIBL to read/write data area. *CURLIB to create.
     *
     * @param $dataAreaName
     * @param string $dataAreaLib
     * @throws \Exception
     */
    public function setDataAreaName($dataAreaName, $dataAreaLib = "*LIBL")
    {
        /**
         * special values:
         * LDA     Local data area
         * GDA     Group data area
         * PDA     Program initialization parameter data area
         */
        $dataAreaName = trim(strtoupper($dataAreaName));

        if ($dataAreaName == '') {
            throw new \Exception("Data Area name parameter should be defined ");
        }

        // no library allowed for these special values.
        if (in_array($dataAreaName, array('*LDA', '*GDA', '*PDA'))) {
            $dataAreaLib = '';
        }

        $this->DataAreaName = $dataAreaName;
        $this->DataAreaLib = $dataAreaLib;
    }

    /**
     * @param int $fromPosition
     * @param string $dataLen
     * @return bool
     */
    public function readDataArea($fromPosition = 1 , $dataLen = '*ALL')
    {
        if (!$this->ToolkitSrvObj instanceof Toolkit)
            return false;

        $Err = ' ';
        $value = '';
        if ($fromPosition == 0) {
            $fromPosition = 1;
        }

        $maxValueSize = 2000; // largest allowed data area size

        $adjustedStartRequested = $fromPosition;
        $adjustedLengthRequested = $dataLen;

        // if data len is *ALL, position and length receive special values..
        if (strtoupper($dataLen) == '*ALL') { // either numeric or *ALL
            $adjustedStartRequested = -1; // *ALL;
            $adjustedLengthRequested = $maxValueSize;
        }

        $toolkit = $this->ToolkitSrvObj;

        /**
         * Retrieve Data Area (QWCRDTAA) API
         * http://publib.boulder.ibm.com/infocenter/iseries/v5r3/index.jsp?topic=%2Fapis%2Fqwcrdtaa.htm
         *
         * Required Parameter Group:
         * 1     Receiver variable     Output     Char(*)
         * 2     Length of receiver variable     Input     Binary(4) Max length is 2000
         * 3     Qualified data area name     Input     Char(20)
         * 4     Starting position     Input     Binary(4)
         * 5     Length of data     Input     Binary(4)
         * 6     Error code     I/O     Char(*)
         *
         *
         * format of receiver variable
         * 0     0     BINARY(4)     Bytes available (The length of all data available to return. All available data is returned if enough space is provided.)
         * 4     4     BINARY(4)     Bytes returned (The length of all data actually returned)
         * 8     8     CHAR(10)     Type of value returned  (*CHAR, *DEC, *LGL)
         * 18     12     CHAR(10)     Library name (blank if *LDA et al.)
         * 28     1C     BINARY(4)     Length of value returned
         * 32     20     BINARY(4)     Number of decimal positions
         * 36     24     CHAR(*)     Value (contents of data area)
         */

        // @todo allow data structure in data area, if packed/binary allowed.

        $receiverVar = array();
        $receiverVar[] = $toolkit->AddParameterInt32('out', 'Bytes available: length of all data available to return', 'bytesAvail', $maxValueSize);
        $receiverVar[] = $toolkit->AddParameterInt32('out', 'Length of all data returned, limited by size of receiver', 'bytesReturned', 0);
        $receiverVar[] = $toolkit->AddParameterChar('out', '10', 'Type of value returned (*CHAR, *DEC, *LGL)', 'Type', '');
        $receiverVar[] = $toolkit->AddParameterChar('out', '10',  'Library where data area was found', 'Library', '');
        $receiverVar[] = $toolkit->AddParameterInt32('out', 'Length of value returned', 'lengthReturned', 0);
        $receiverVar[] = $toolkit->AddParameterInt32('out', 'Number of decimal positions', 'decimalPositions', 0);
        $receiverVar[] = $toolkit->AddParameterChar('out', $maxValueSize, 'Value returned', 'value', ''); // set length to $maxValueSize to be safe

        $receiverLength = $maxValueSize + 36; // 4 4-byte integers + 2 10-byte character strings = 36

        // "NAME      LIB       " no slash. Left-aligned.
        $twentyCharQualifiedName = $this->getAPIDataAreaName();

        $toolkitParams = array();
        $toolkitParams [] = $toolkit->AddDataStruct($receiverVar, 'receiver');
        $toolkitParams [] = $toolkit->AddParameterInt32('in', 'Length of receiver variable', 'receiverLen', $receiverLength);
        $toolkitParams [] = $toolkit->AddParameterChar('in', 20, 'Data area name', 'DtaaraName', $twentyCharQualifiedName);
        // Starting position: The first byte of the data area to be retrieved. A value of 1 will identify the first character in the data area. The maximum value allowed for the starting position is 2000. A value of -1 will return all the characters in the data area.
        $toolkitParams [] = $toolkit->AddParameterInt32('in', 'Starting position requested', 'fromPosition', $adjustedStartRequested);
        $toolkitParams [] = $toolkit->AddParameterInt32('in', 'Data length requested', 'dataLength', $adjustedLengthRequested);
        $toolkitParams [] = $toolkit->AddErrorDataStructZeroBytes(); // so errors bubble up to joblog

        // we're using a data structure here so integrity must be on
        // @todo pass as option on the program call
        $dsIntegrity = $toolkit->getOption('dataStructureIntegrity'); // save original value
        $toolkit->setOptions(array('dataStructureIntegrity'=>true));

        $retPgmArr = $toolkit->PgmCall('QWCRDTAA', '', $toolkitParams);

        $toolkit->setOptions(array('dataStructureIntegrity'=>$dsIntegrity)); // restore original value

        // check for any errors
        if ($toolkit->getErrorCode()) {
            // an error
            return false;
        } else {
            // extricate the data from the receiver variable ds wrapper
            $value = $retPgmArr['io_param']['receiver']['value'];
        }

        return ($value) ? $value : false;
    }

    /**
     * @param $msg
     */
    private function setError($msg){
        $this->ErrMessage = $msg;
    }

    /**
     * @return mixed
     */
    public function getError() {
        return $this->ErrMessage;
    }

    /**
     * @param $value
     * @param int $fromPosition
     * @param int $dataLen
     * @throws \Exception
     */
    public function writeDataArea($value, $fromPosition = 0, $dataLen = 0)
    {
        $substring = ''; // init
        if ($fromPosition > 0) {
            $substring = sprintf("(%d %d)", $fromPosition, $dataLen);
        }

        // @todo use API instead. Handle numeric and character data., *CHAR and *DEC as well.
        // and/or quote the string. Needs to be case-sensitive and handle numeric input.
        $cmd = sprintf("CHGDTAARA DTAARA(%s $substring) VALUE($value)",
            $this->getQualifiedDataAreaName());

        if (!$this->ToolkitSrvObj->CLCommand($cmd)) {
            $this->ErrMessage =  "Write into Data Area failed." . $this->ToolkitSrvObj->getLastError();
            throw new \Exception($this->ErrMessage);
        }
    }

    /**
     * requires explicit library
     *
     * @param string $DataAreaName
     * @param string $DataAreaLib
     * @throws \Exception
     */
    public function deleteDataArea($DataAreaName = '', $DataAreaLib = '')
    {
        $cmd = sprintf("QSYS/DLTDTAARA DTAARA(%s/%s)",
            ($DataAreaLib != '' ? $DataAreaLib : $this->DataAreaLib),
            ($DataAreaName != NULL ? $DataAreaName : $this->DataAreaName));

        if (!$this->ToolkitSrvObj->CLCommand($cmd)) {
            $this->ErrMessage =  "Delete Data Area failed." . $this->ToolkitSrvObj->getLastError();
            throw new \Exception($this->ErrMessage);
        }
    }
}
