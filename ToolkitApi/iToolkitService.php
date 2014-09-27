<?php
namespace ToolkitApi;

use ToolkitApi\ToolkitService;

/**
 * Class DateTimeApi
 *
 * @package ToolkitApi
 */
class DateTimeApi
{
    protected $ToolkitSrvObj;

    /**
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct(ToolkitService $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof ToolkitService) {
            $this->ToolkitSrvObj = $ToolkitSrvObj ;
        }
    }

    /**
     * from 8-character *DTS format to 17-character full date and time
     * 
     * @param $dtsDateTime
     * @return bool
     */
    public function dtsToYymmdd($dtsDateTime)
    {
        $inputFormat = "*DTS"; // special system format, returned by some APIs.
        $outputFormat = "*YYMD"; // 17 chars long
        $outputVarname = 'datetimeOut';
        
        $apiPgm = 'QWCCVTDT';
        $apiLib = 'QSYS';
        
        $paramXml = "<parm io='in' comment='1. Input format'>
            <data var='formatIn' type='10A' comment='*DTS is system time stamp format'>$inputFormat</data>
            </parm>
            <parm io='in' comment='2. Input variable'>
            <data var='datetimeIn' type='8b'  comment='*DTS format is type 8b (binary)'>$dtsDateTime</data>
            </parm>
            <parm io='in' comment='3. Output format'>
            <data var='formatOut' type='10A' comment='*YYMD means YYYYMMDDHHMMSSmmm (milliseconds)'>$outputFormat</data>
            </parm>
            <parm io='out' comment='4. Output variable'>
            <ds var='$outputVarname' comment='Data structure, total of 17 bytes, to split date/time into YYYYMMDD, HHMMSS, and microseconds, as indicated by *YYMD format'>
            <data var='date' type='8a' comment='YYYYMMDD' />
            <data var='time' type='6a' comment='HHMMSS' />
            <data var='microseconds' type='3a' comment='microsecs (3 digits)' />
            </ds>
            </parm>\n" .
            ToolkitService::getErrorDataStructXml(5); // param number 5
        
        // pass param xml directly in.
        $retPgmArr = $this->ToolkitSrvObj->PgmCall($apiPgm, $apiLib, $paramXml);
        if ($this->ToolkitSrvObj->getErrorCode()) {
            return false;
        }
        
        $retArr = $retPgmArr['io_param'][$outputVarname];
        
        return $retArr;
    }
}

/**
 * Class ListFromApi
 *
 * @package ToolkitApi
 */
class ListFromApi
{
    protected $ToolkitSrvObj;
    protected $_requestHandle;
    protected $_receiverSize;
    protected $_totalRecords = 0;
    protected $_nextRecordToRequest = 0;
    protected $_receiverDs; // innards of a data structure <data>..</data><data>...</data> that we'll wrap in a receiver variable. At this time it must be an XML string.

    // listinfo: totalRecords, firstRecordNumber, requestHandle. if firstRec... < totalRecords then can continue.
    // return I5_ERR_BEOF when went past last record. get CPF GUI0006 when used invalid record#.

    /**
     * @param $requestHandle
     * @param $totalRecords
     * @param $receiverDs
     * @param $lengthOfReceiverVariable
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct($requestHandle, $totalRecords, $receiverDs, $lengthOfReceiverVariable, ToolkitService $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof ToolkitService) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;
        }
        
        $this->_requestHandle = $requestHandle;
        $this->_receiverSize = $lengthOfReceiverVariable;
        $this->_totalRecords = $totalRecords;
        $this->_nextRecordToRequest = 1; // will request record #1 when someone asks
        $this->_receiverDs = $receiverDs; // will request record #1 when someone asks
    }

    /**
     * @return ToolkitService
     */
    public function getConn() 
    {
        return $this->ToolkitSrvObj;
    }
    
    /**
     * Call QGYGTLE (get list entry) API using handle and "next record to request."
     * 
     * Note: if get timing problems where no records are returned:
     * Embed the call to the QGYGTLE API in a do-loop that loops until a record is returned.
     * 
     * @return bool Return false when run out of records (get GUI0006 error code).
     */
    public function getNextEntry()
    {
        $apiPgm = 'QGYGTLE';
        $apiLib = 'QSYS';
        
        $receiverDs = $this->_receiverDs;
        $requestHandle = $this->_requestHandle; 
        $lengthOfReceiverVariable = $this->_receiverSize;
        $nextRecordToRequest = $this->_nextRecordToRequest++; // assign to me, then increment for next time
        
        $outputVarname = 'receiver';
        
        $lenLabel= 'size' . $outputVarname;
        
        $paramXml = "<parm io='out' comment='1. receiver data'>
            <ds var='$outputVarname' comment='receiver appropriate to whatever API created the list' len='$lenLabel'>
            $receiverDs
            </ds>
            </parm>
            <parm io='both' comment='2. Length of receiver variable'>
            <data var='receiverLen' type='10i0' setlenx='$lenLabel'>$lengthOfReceiverVariable</data>
            </parm>
            <parm io='in' comment='3. Request handle'>
            <data var='requestHandle' comment='Request handle: binary/hex' type='4b'>$requestHandle</data>
            </parm>\n" .  $this->ToolkitSrvObj->getListInfoApiXml(4) . "\n" .
            "<parm io='in' comment='5. Number of records to return'>
            <data var='numRecsDesired' type='10i0'>1</data>
            </parm>
            <parm io='in' comment='6. Starting record' >
            <data var='startingRecordNum' comment='First entry number to put in receiver var. If getting one record at a time, increment this each time.' type='10i0'>$nextRecordToRequest</data>
            </parm>\n" .
            ToolkitService::getErrorDataStructXmlWithCode(7); // param number 7
    
        // was getErrorDataStructXml
        // pass param xml directly in.
        $retPgmArr = $this->ToolkitSrvObj->PgmCall($apiPgm, $apiLib, $paramXml);
    
        if ($this->ToolkitSrvObj->getErrorCode()) {
            return false;
        }
    
        /* Even when no error reported by XMLSERVICE (->error),
         * we may get a GUI0006 in DS error structure exeption code, since we
         * supplied a full error data structure above (getErrorDataStructXmlWithCode).
         */
        $apiErrCode = $retPgmArr['io_param']['errorDs']['exceptId'];
        
        if ($apiErrCode != '0000000') {
            // Note: caller can check for GUI0006 and GUI0001 (expected when go past last record) vs. any other error (not expected)
            $this->ToolkitSrvObj->setErrorCode($apiErrCode);
            return false;
        }
        
        $retArr = $retPgmArr['io_param'][$outputVarname];
        
        return $retArr;
    }

    /**
     * close the list
     * 
     * @return bool
     */
    public function close()
    {
        // call QGYCLST, the "close list" api.
        $apiPgm = 'QGYCLST';
        $apiLib = 'QSYS';
    
        $requestHandle = $this->_requestHandle;
        
        $paramXml = "<parm io='in' comment='1. request handle'>
                      <data var='requestHandle' comment='Request handle: binary/hex' type='4b'>$requestHandle</data>
                    </parm>\n" . ToolkitService::getErrorDataStructXml(2); // param number 2
        
        // pass param xml directly in.
        $this->ToolkitSrvObj->PgmCall($apiPgm, $apiLib, $paramXml);
        
        // GUI0006 means end of list
        if ($this->ToolkitSrvObj->getErrorCode()) {
           return false;
        } else {
           return true;
        }
    }
}

/**
 * Class UserSpace
 *
 * @package ToolkitApi
 */
class UserSpace {
    private $ToolkitSrvObj;
    private $USName = NULL;
    private $USlib = 'QTEMP';
    // these were private
    protected $CPFErr = '0000000';
    protected $ErrMessage;

    /**
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct(ToolkitService $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof ToolkitService) {
            $this->ToolkitSrvObj = $ToolkitSrvObj ;
        }
        
        return $this;
    }

    /**
     * @return string
     */
    public function getCpfErr()
    { 
        return $this->CPFErr;
    }

    /**
     * @return mixed
     */
    public function getError()
    { 
        return $this->ErrMessage;
    }
    
    // @todo in cw.php, user space api should do an error action with CPF code if needed
/*    private function verify_CPFError($retPgmArr, $functionErrMsg)
    {
        // it's an error if we didn't get output array at all
        if (!is_array($retPgmArr)){
            $this->ErrMessage = $functionErrMsg . $this->ToolkitSrvObj->getLastError();
            return true;
        }
           
        $retArr = $retPgmArr['io_param'];
        
        // get errorDs from named ds (CW style) or directly (PHP toolkit style)
        $errorDs = (isset($retArr['errorDs'])) ? $retArr['errorDs'] : $retArr; 
        // if there's a CPF-style error code
        if (isset($errorDs) && ($errorDs['exceptId'] != '0000000')){
                $this->CPFErr = $errorDs['exceptId'];
                // @todo future, get actual error text from joblog
                $this->ErrMessage = $functionErrMsg . $this->CPFErr;
            return true;  //some problem
        } else {
                // no CPF error detected.
                $this->CPFErr = '0000000';
                $this->ErrMessage = '';
                return false;
        }
         

    } //(verify_CPFError)
*/

    /**
     * @param null $UserSpaceName
     * @param null $USLib
     * @param int $InitSize
     * @param string $publicAuthority
     * @param string $InitValue
     * @param string $extendedAttribute
     * @param string $textDescription
     * @return bool
     */
    public function CreateUserSpace($UserSpaceName = NULL, $USLib = NULL, $InitSize =1024, $publicAuthority = '*ALL', $InitValue=' ', 
                                    $extendedAttribute='PF', $textDescription='ZS XML Service User Space')
    {    
        // @todo check that 1 <= InitSize <= 16776704 
        
       // set defaults in case blank is passed in
       $InitSize = ($InitSize) ? $InitSize : 1024;
       $publicAuthority = ($publicAuthority) ? $publicAuthority : '*ALL';
       $InitValue= ($InitValue) ? $InitValue : '00'; // single binary hex value X'00, most efficient initialization, according to documentation of QUSCRTUS
       $extendedAttribute = ($extendedAttribute) ? $extendedAttribute : 'PF';
       $textDescription = ($textDescription) ? $textDescription : 'ZS XML Service User Space';
                                        
        // format the user space name and library into 20 char format
        $this->setUSName($UserSpaceName, $USLib);
        
        // format extended attribute into proper format (left-aligned)
        $extAttrFormatted = sprintf("%-10s", $extendedAttribute);
        
        // format authority into proper format (left-aligned)
        $authFormatted = sprintf("%-10s", $publicAuthority);
        
        $params[] = ToolkitService::AddParameterChar('in', 20, "USER SPACE NAME", 'userspacename', $this->getUSFullName());
        $params[] = ToolkitService::AddParameterChar('in', 10, "Extended Attribute",'extendedattribute', $extAttrFormatted);
        $params[] = ToolkitService::AddParameterInt32('in', "Initial Size", 'initialsize', $InitSize);
        $params[] = ToolkitService::AddParameterBin('in', 1, "Initial Value: one byte to fill whole space with", 'initval', $InitValue);
        $params[] = ToolkitService::AddParameterChar('in', 10, "Public Authority", 'authority', $authFormatted);
        $params[] = ToolkitService::AddParameterChar('in', 50, "Description", 'description', $textDescription);
        $params[] = ToolkitService::AddParameterChar('in', 10, "Replace US", 'replaceuserspace', "*NO       ");
        $params[] = ToolkitService::AddErrorDataStruct();
        
//        $params = $this->DefineUserSpaceParameters($InitSize, $Auth, $InitChar);
        $retPgmArr = $this->ToolkitSrvObj->PgmCall('QUSCRTUS', 'QSYS', $params);
        
        if ($this->ToolkitSrvObj->verify_CPFError($retPgmArr , "Create user space failed.")) {
            return false;
        }
        
        return true; //created
    }

    /**
     * @return array|bool
     */
    public function RetrieveUserSpaceAttr()
    {    
        $BytesRet = 0;
        $BytesAv = 25;
        $USSize = 0;
        $Ext = ' ';
        $InitVal = ' ';
        $libName = ' ';
        
        /*Reciever var*/
        $ds[]=ToolkitService::AddParameterInt32('out', "Bytes returned", 'ret_bytes', $BytesRet);
        $ds[]=ToolkitService::AddParameterInt32('out', "Bytes available", 'bytes_avail', $BytesAv);
        $ds[]=ToolkitService::AddParameterInt32('out', "Space size", 'spacesize', $USSize);
        $ds[]=ToolkitService::AddParameterChar('out', 1, "Automatic extendibility",'extend_automatic', $Ext);
        $ds[]=ToolkitService::AddParameterChar('out', 1, "Initial value", 'initval', $InitVal);
        $ds[]=ToolkitService::AddParameterChar('out', 10, "User space library name", 'uslib', $libName);
        //$params[] = array('ds'=>$ds);
        $params[] = ToolkitService::AddDataStruct($ds, 'receiver'); // note that ds names are discarded
        $params[] = ToolkitService::AddParameterInt32('in', "Length of reciever",'reciver_len', 24);
        $params[] = ToolkitService::AddParameterChar('in', 8, "Format name", 'format', "SPCA0100");
        $params[] = ToolkitService::AddParameterChar('in', 20, "User space name and library", 'usfullname', $this->getUSFullName());      
        $params[] = ToolkitService::AddErrorDataStruct();

        $retPgmArr = $this->ToolkitSrvObj->PgmCall('QUSRUSAT', 'QSYS', $params);

        if ($this->ToolkitSrvObj->verify_CPFError($retPgmArr, "Retrieve user space attributes failed. Error: ")) {
            return false;
        }
        
        // If data structure integrity turned off, ds'es are discarded when reading output. The subfields become independent.
        // So 'receiver' may not exist. But this changes with data structure integrity, so allow for receiver var.
        if (isset($retPgmArr['io_param']['receiver'])) {
            // receiver ds var does exist.
            $retArr = $retPgmArr['io_param']['receiver'];
        } else {
            // ds subfields are directly under io_param.
            $retArr = $retPgmArr['io_param'];
        }

        // return selected values from return array.
        return array("Space Size"=>$retArr['spacesize'],
                     "Automatic extendibility"=> $retArr['extend_automatic'],
                     "Initial value"=>$retArr['initval'],
                     "User space library name"=>$retArr['uslib']);
    }

    /**
     * @return int
     */
    public function RetrieveUserSpaceSize()
    {
        $ret = $this->RetrieveUserSpaceAttr();
        return (isset($ret['Space Size'])? $ret['Space Size']: -1); // -1 is an error condition
    }

    /**
     * @return bool
     */
    public function DeleteUserSpace()
    {
        $params[] = ToolkitService::AddParameterChar('in', 20, "User space name", 'userspacename', $this->getUSFullName());    
        $params[] = ToolkitService::AddErrorDataStruct();
        
        $retPgmArr = $this->ToolkitSrvObj->PgmCall('QUSDLTUS', 'QSYS', $params);
        
        if ($this->ToolkitSrvObj->verify_CPFError($retPgmArr, "Delete user space failed. Error:")) {
            return false;
        }
            
        return true;
    }
    
    /**
     * @todo write binary data?
     * 
     * @param int $startpos
     * @param $valuelen
     * @param $value
     * @return bool
     */
    public function WriteUserSpace($startpos = 1, $valuelen, $value)
    {
        //Size ($comment, $varName = '', $labelFindLen = null) {
        $params[] =  ToolkitService::AddParameterChar ('in', 20, "User space name and lib", 'usfullname', $this->getUSFullName());    
        $params[] =  ToolkitService::AddParameterInt32('in', "Starting position",'pos_from', $startpos);
        $params[] =  ToolkitService::AddParameterInt32('in', "Length of data", 'dataLen', $valuelen);
        $params[] =  ToolkitService::AddParameterChar('in', $valuelen, "Input data", 'data_value', $value);
        $params[] =  ToolkitService::AddParameterChar('in', 1, "Force changes to auxiliary storage", 'aux_storage', '0');
        $params[] =  ToolkitService::AddErrorDataStruct();
        $retPgmArr = $this->ToolkitSrvObj->PgmCall('QUSCHGUS', 'QSYS', $params);
        
        if ($this->ToolkitSrvObj->verify_CPFError($retPgmArr , "Write into User Space failed. Error:")) {
            return false;
        }
        
        return true;
    }

    /**
     * CW version. $param must be an array of ProgramParameter objects or a single ProgramParameter object.
     * 
     * @param int $startPos
     * @param ProgramParameter $param
     * @return bool
     */
    public function WriteUserSpaceCw($startPos = 1, ProgramParameter $param)
    {
        /*
         if (!is_object($param) && !is_array($param)) {
            throw new \Exception('Parameter passed to WriteUserSpaceCw must be an array or ProgramParameter object.');
        } 
        */

        // @todo any error, write to toolkit log.
        
        $labelForSizeOfInputData = 'dssize';
        $param->setParamLabelLen($labelForSizeOfInputData);
    //Size ($comment,  $varName = '', $labelFindLen = null) {
        $params[] =  ToolkitService::AddParameterChar('in', 20,"User space name and lib", 'usfullname', $this->getUSFullName());    
        $params[] =  ToolkitService::AddParameterInt32('in', "Starting position", 'pos_from', $startPos);
        $params[] =  ToolkitService::AddParameterSize("Length of data",'dataLen', $labelForSizeOfInputData);
        $params[] =  $param;
        $params[] =  ToolkitService::AddParameterChar('in', 1, "Force changes to auxiliary storage", 'aux_storage', '0');
        $params[] =  ToolkitService::AddErrorDataStruct();
        
        $retPgmArr = $this->ToolkitSrvObj->PgmCall('QUSCHGUS', 'QSYS', $params);
    
        if ($this->ToolkitSrvObj->getErrorCode()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * if receiveDescription given, readlen = 0
     * 
     * @param int $frompos
     * @param int $readlen
     * @param null $receiveStructure
     * @return bool
     * @throws \Exception
     */
    public function ReadUserSpace($frompos=1, $readlen = 0, $receiveStructure = null)
    {
        //how to see via green screen DSPF STMF('/QSYS.lib/qgpl.lib/ZS14371311.usrspc')
        
        $dataRead = ' ';
        $params[] = ToolkitService::AddParameterChar('in', 20,  "User space name and library", 'userspacename', $this->getUSFullName());
        $params[] = ToolkitService::AddParameterInt32('in',  "From position", 'position_from', $frompos);

        $receiverVarName = 'receiverdata';
        
        if ($receiveStructure) {
            // must be a ProgramParameter
            if (!is_object($receiveStructure)) {
                throw new \Exception('Parameter 3 passed to ReadUserSpace must be a ProgramParameter object.');
            }
            
            $labelForSizeOfInputData = 'dssize';
//
             $params[] = ToolkitService::AddParameterSize("Length of data", 'dataLen', $labelForSizeOfInputData);

             // wrap simple ds around receive structure so we can assign a varname to retrieve later.
             $receiveDs[] = $receiveStructure;
             $params[] = ToolkitService::AddDataStruct($receiveDs, $receiverVarName, 0, '', false, $labelForSizeOfInputData);

        } else {
            // regular readlen, no special structure or size labels.
            $params[] = ToolkitService::AddParameterInt32('in',  "Size of data", 'datasize', $readlen);
            $params[] = ToolkitService::AddParameterChar('out', $readlen, $receiverVarName, $receiverVarName, $receiveStructure);
        }
            
        $params[] = ToolkitService::AddErrorDataStruct();
                
        $retPgmArr = $this->ToolkitSrvObj->PgmCall('QUSRTVUS', 'QSYS', $params);
        
        if ($this->ToolkitSrvObj->verify_CPFError($retPgmArr , "Read user space failed. Error:"))
            return false;
        $retArr = $retPgmArr['io_param'];
        
        // return our receiverstructure.
        return $retArr[$receiverVarName];
    }

/*    private function DefineUserSpaceParameters($InitSize, $Auth, $InitChar) 
    {             
        $params[] = ToolkitService::AddParameterChar('in',20, "USER SPACE NAME", 'userspacename', $this->getUSFullName());
        $params[] = ToolkitService::AddParameterChar('in',10, "USER TYPE",'userspacetype', "PF        ");
        $params[] = ToolkitService::AddParameterInt32('in',  "US SIZE", 'userspacesize', $InitSize);
        $params[] = ToolkitService::AddParameterChar('in',1, "INITIALIZATION VALUE", 'initval', $InitChar);
        $params[] = ToolkitService::AddParameterChar('in',10, "AUTHORITY", 'authority', $Auth);
        $params[] = ToolkitService::AddParameterChar('in',50, "COMMENTS", 'comment', "ZS XML Service");
        $params[] = ToolkitService::AddParameterChar('in',10, "Replace US", 'replaceuserspace', "*NO       ");
        $params[] = ToolkitService::AddErrorDataStruct();

        return $params;
    }
*/

    /**
     * @return null|string
     */
    protected function generate_name()
    {
        $localtime = localtime();
        $this->USName = sprintf("ZS%d%d%d%d", 
                     $localtime[0],/*sec*/
                     $localtime[1],/*min*/
                     $localtime[2],/*our*/
                     $localtime[3] /*day*/
                      );
        return $this->USName;
    }

    /**
     * @param null $name
     * @param null $lib
     */
    public function setUSName($name = NULL, $lib = NULL)
    {
        if ($name === NULL) {
            $this->USName = $this->generate_name ();
        } else {
            $this->USName = $name;
        }
        
        if ($lib === NULL) {
            $this->USlib = DFTLIB;
        } else {
            $this->USlib = $lib;
        }
    }

    /**
     * @return null
     */
    public function getUSName()
    {
        return $this->USName;
    }
    
    /**
     * Name and Library
     * 
     * @return null|string
     */
    public function getUSFullName()
    {
        if ($this->USName != null) {
            return  sprintf("%-10s%-10s", $this->USName, $this->USlib);
        }
            
        return NULL;
    }    
}

/**
 * Class TmpUserSpace
 *
 * @package ToolkitApi
 */
class TmpUserSpace extends UserSpace
{
    private $TMPUSName;

    /**
     * @param ToolkitService $ToolkitService
     * @param string $UsLib
     * @param int $DftUsSize
     * @throws \Exception
     */
    function __construct($ToolkitService, $UsLib = DFTLIB, $DftUsSize = 32700)
    {        
        parent::__construct($ToolkitService);
        
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

/**
 * Class DataQueue
 *
 * @package ToolkitApi
 */
class DataQueue
{
    private $ToolkitService;
    private $DataQueueName;
    private $DataQueueLib;
    private $CPFErr = '0000000';
    private $ErrMessage;

    /**
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct(ToolkitService $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof ToolkitService) {
            $this->ToolkitService = $ToolkitSrvObj ;
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
             
        if (!$this->ToolkitService->CLCommand($cmd)) {
            $this->ErrMessage =  "Create Data Queue failed.". $this->ToolkitService->getLastError();
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
                       
        if (!$this->ToolkitService->CLCommand($cmd)) {
            $this->ErrMessage =  "Delete Data Queue failed.". $this->ToolkitService->getLastError();
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
        
        $params [] = $this->ToolkitService->AddParameterChar('in', 10, 'dqname', 'dqname', $this->DataQueueName);
        $params [] = $this->ToolkitService->AddParameterChar('in', 10, 'dqlib', 'dqlib', $this->DataQueueLib);
        
        // @todo do not hard-code data size. Use system of labels as allowed by XMLSERVICE (as done in CW's i5_dtaq_receive).
        $DataLen = 300;
        $Data = ' ';
        
        $params [] = $this->ToolkitService->AddParameterPackDec('out', 5, 0, 'datalen', 'datalen', $DataLen); // @todo this is output only so no need to specify a value
        $params [] = $this->ToolkitService->AddParameterChar('out', (int) $DataLen, 'datavalue', 'datavalue', $Data); // @todo this is output only so no need to specify a value.
        
        // Wait time: < 0 waits forever. 0 process immed. > 0 is number of seconds to wait.
        $params [] = $this->ToolkitService->AddParameterPackDec('in', 5, 0, 'waittime', 'waittime', $WaitTime);
    
        if (!$KeyLength) {
            // 0, make order, length and data also zero or blank, so thatthey'll be ignored by API. Must send them, though.
            
            // if an unkeyed queue, API still expects to receive key info, 
            // but it must be blank and zero.
            $KeyOrder = ''; // e.g. EQ, other operators, or blank
            $KeyLength = 0; 
            $KeyData = '';
        }
            
        $params [] = $this->ToolkitService->AddParameterChar('in', 2, 'keydataorder', 'keydataorder', $KeyOrder);
        $params [] = $this->ToolkitService->AddParameterPackDec('in', 3, 0, 'keydatalen', 'keydatalen', $KeyLength);
        $params [] = $this->ToolkitService->AddParameterChar('both', (int) $KeyLength, 'keydata', 'keydata', $KeyData);
            
        $params [] = $this->ToolkitService->AddParameterPackDec('in', 3, 0, 'senderinflen', 'senderinflen', 44);
        // Sender info may contain packed data, so don't receive it till we can put it in a data structure.
        // @todo use a data structure to receive sender info as defined in QRCVDTAQ spec.
        $params [] = $this->ToolkitService->AddParameterHole(44, 'senderinf');

        // whether to remove message from data queue
        if ($WithRemoveMsg == 'N') {
            $Remove= '*NO       ';
        } else {
            $Remove= '*YES      ';
        }
        
        $params[] = $this->ToolkitService->AddParameterChar('in', 10, 'remove', 'remove', $Remove);
        // @todo note from API manual: If this parameter is not specified, the entire message will be copied into the receiver variable.
        $params[] = $this->ToolkitService->AddParameterPackDec('in', 5, 0, 'size of data receiver', 'receiverSize', $DataLen);

        $params[] =  $this->ToolkitService->AddErrorDataStructZeroBytes(); // so errors bubble up to joblog
        
        $retPgmArr = $this->ToolkitService->PgmCall('QRCVDTAQ', 'QSYS', $params);
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
        
        $params[] = $this->ToolkitService->AddParameterChar('in', 10, 'dqname', 'dqname', $this->DataQueueName);
        $params[] = $this->ToolkitService->AddParameterChar('in', 10, 'dqlib', 'dqlib',$this->DataQueueLib);
         
        $params[] = $this->ToolkitService->AddParameterPackDec('in', 5, 0, 'datalen', 'datalen', $DataLen, null);
        $params[] = $this->ToolkitService->AddParameterChar('in', $DataLen, 'datavalue','datavalue',  $Data);
        if ($KeyLength > 0 ) {
            $params[] = $this->ToolkitService->AddParameterPackDec('in', 3, 0, 'keydatalen', 'keydatalen', $KeyLength, null);        
            $params[] = $this->ToolkitService->AddParameterChar('in', $KeyLength, 'keydata', 'keydata',  $KeyData);
        }
        
        $ret = $this->ToolkitService->PgmCall('QSNDDTAQ', 'QSYS', $params);
        
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
        $params[]=$this->ToolkitService->AddParameterChar('in', 10, 'dqname', 'dqname', $this->DataQueueName);
        $params[]=$this->ToolkitService->AddParameterChar('in', 10, 'dqlib', 'dqlib', $this->DataQueueLib);
        if ($KeyLength > 0) {
            $params[] = $this->ToolkitService->AddParameterChar('in', 2, 'keydataorder', 'keydataorder', $KeyOrder);
            $params[] = $this->ToolkitService->AddParameterPackDec('in', 3, 0, 'keydatalen', 'keydatalen', $KeyLength);
            $params[] = $this->ToolkitService->AddParameterChar('in', ((int)$KeyLength), 'keydata', 'keydata', $KeyData);
            //$params[] = array('ds'=>$this->ToolkitService->GenerateErrorParameter());
            $ds =$this->ToolkitService->GenerateErrorParameter(); 
            $params[] = ToolkitService::AddDataStruct($ds);
        }
        
        $retArr  = $this->ToolkitService->PgmCall('QCLRDTAQ', 'QSYS', $params);
        
        if (isset($retArr['exceptId']) && strcmp ($retArr['exceptId'], '0000000')) {
            $this->CPFErr = $retArr['exceptId'];
            $this->ErrMessage ="Clear Data Queue failed. Error: $this->CPFErr";
            return false; 
        }
            
        return true;
    }  
}

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
     * @param ToolkitService $ToolkitSrvObj
     * @param null $UserLib
     */
    public function __construct(ToolkitService $ToolkitSrvObj = NULL, $UserLib = NULL)
    {
        if ($ToolkitSrvObj instanceof ToolkitService) {
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
    public function __destructor() 
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
     * @param ToolkitService $ToolkitSrvObj
     * @param string $tmpUSLib
     */
    public function __construct(ToolkitService $ToolkitSrvObj = null, $tmpUSLib = DFTLIB)
    {
        if ($ToolkitSrvObj instanceof ToolkitService ) {
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
        $params[] =  ToolkitService::AddParameterChar('input', 26, "QualifiedJobName", 'JobName', trim($jobName26));        
        $params[] =  ToolkitService::AddParameterChar('both', $receiverSize, "reciever", 'reciever', $reciever);                                
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

/**
 * Class ObjectLists
 *
 * @package ToolkitApi
 */
class ObjectLists
{
    private $OBJLLISTREC_SIZE = 30;
    private $ToolkitSrvObj;
    private $TmpUserSpace ;
    private $ErrMessage;

    /**
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct(ToolkitService $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof ToolkitService) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;
            return $this;
        } else {
            return false;
        }
    }
    
    /**
     * @param string $object = *ALL, *name, *generic name
     * @param string $library = *ALL, *name, *generic name
     * @param string $objecttype = *ALL, *type
     * @return array|bool
     * @throws \Exception
     */
    public function getObjectList($object = '*ALL', $library = '*LIBL', $objecttype = '*ALL')
    {
        $ObjName = $object;
        $ObjLib  = $library;
        $ObjType = $objecttype;
        
        $this->TmpUserSpace = new TmpUserSpace($this->ToolkitSrvObj);
        
        $UsFullName = $this->TmpUserSpace->getUSFullName();

        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 20, "User Space Name", 'userspacename', $UsFullName);
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 10, "Object name", 'objectname', $ObjName);
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 10, "Object library", 'objectlib', $ObjLib);
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 10, "Object Type", 'objecttype', $ObjType);
        $this->ToolkitSrvObj->PgmCall(ZSTOOLKITPGM, $this->ToolkitSrvObj->getOption('HelperLib'), $params, NULL, array('func' => 'OBJLST'));
        
        $ObjList  = $this->TmpUserSpace->ReadUserSpace(1, $this->TmpUserSpace->RetrieveUserSpaceSize());
        $this->TmpUserSpace->DeleteUserSpace();
        
        unset($this->TmpUserSpace);
        
        if (trim($ObjList)!='') {
            return (str_split($ObjList, $this->OBJLLISTREC_SIZE));
        } else {
            return false;
        }
    } 
}

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
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct(ToolkitService $ToolkitSrvObj = null){
        if ($ToolkitSrvObj instanceof ToolkitService) {
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
        if (!$this->ToolkitSrvObj instanceof ToolkitService) {
            $this->ToolkitSrvObj = new ToolkitService($dbname, $user, $pass);
        }
    }

    /**
     * @return array|bool
     */
    public function systemValuesList()
    {
        if (!$this->ToolkitSrvObj instanceof ToolkitService) {
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
        if (!$this->ToolkitSrvObj instanceof ToolkitService) {
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
     * @param ToolkitService $ToolkitSrvObj
     */
    public function __construct(ToolkitService $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof ToolkitService) {
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
        if (!$this->ToolkitSrvObj instanceof ToolkitService) 
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
