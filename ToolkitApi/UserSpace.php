<?php
namespace ToolkitApi;

class UserSpace
{
    private $ToolkitSrvObj;
    private $USName = NULL;
    private $USlib = 'QTEMP';
    // these were private
    protected $CPFErr = '0000000';
    protected $ErrMessage;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof Toolkit) {
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

        $params[] = Toolkit::AddParameterChar('in', 20, "USER SPACE NAME", 'userspacename', $this->getUSFullName());
        $params[] = Toolkit::AddParameterChar('in', 10, "Extended Attribute",'extendedattribute', $extAttrFormatted);
        $params[] = Toolkit::AddParameterInt32('in', "Initial Size", 'initialsize', $InitSize);
        $params[] = Toolkit::AddParameterBin('in', 1, "Initial Value: one byte to fill whole space with", 'initval', $InitValue);
        $params[] = Toolkit::AddParameterChar('in', 10, "Public Authority", 'authority', $authFormatted);
        $params[] = Toolkit::AddParameterChar('in', 50, "Description", 'description', $textDescription);
        $params[] = Toolkit::AddParameterChar('in', 10, "Replace US", 'replaceuserspace', "*NO       ");
        $params[] = Toolkit::AddErrorDataStruct();

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
        $ds[]=Toolkit::AddParameterInt32('out', "Bytes returned", 'ret_bytes', $BytesRet);
        $ds[]=Toolkit::AddParameterInt32('out', "Bytes available", 'bytes_avail', $BytesAv);
        $ds[]=Toolkit::AddParameterInt32('out', "Space size", 'spacesize', $USSize);
        $ds[]=Toolkit::AddParameterChar('out', 1, "Automatic extendibility",'extend_automatic', $Ext);
        $ds[]=Toolkit::AddParameterChar('out', 1, "Initial value", 'initval', $InitVal);
        $ds[]=Toolkit::AddParameterChar('out', 10, "User space library name", 'uslib', $libName);
        //$params[] = array('ds'=>$ds);
        $params[] = Toolkit::AddDataStruct($ds, 'receiver'); // note that ds names are discarded
        $params[] = Toolkit::AddParameterInt32('in', "Length of reciever",'reciver_len', 24);
        $params[] = Toolkit::AddParameterChar('in', 8, "Format name", 'format', "SPCA0100");
        $params[] = Toolkit::AddParameterChar('in', 20, "User space name and library", 'usfullname', $this->getUSFullName());
        $params[] = Toolkit::AddErrorDataStruct();

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
        $params[] = Toolkit::AddParameterChar('in', 20, "User space name", 'userspacename', $this->getUSFullName());
        $params[] = Toolkit::AddErrorDataStruct();

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
    public function WriteUserSpace($startpos, $valuelen, $value)
    {
        //Size ($comment, $varName = '', $labelFindLen = null) {
        $params[] =  Toolkit::AddParameterChar ('in', 20, "User space name and lib", 'usfullname', $this->getUSFullName());
        $params[] =  Toolkit::AddParameterInt32('in', "Starting position",'pos_from', $startpos);
        $params[] =  Toolkit::AddParameterInt32('in', "Length of data", 'dataLen', $valuelen);
        $params[] =  Toolkit::AddParameterChar('in', $valuelen, "Input data", 'data_value', $value);
        $params[] =  Toolkit::AddParameterChar('in', 1, "Force changes to auxiliary storage", 'aux_storage', '0');
        $params[] =  Toolkit::AddErrorDataStruct();
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
    public function WriteUserSpaceCw($startPos, ProgramParameter $param)
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
        $params[] =  Toolkit::AddParameterChar('in', 20,"User space name and lib", 'usfullname', $this->getUSFullName());
        $params[] =  Toolkit::AddParameterInt32('in', "Starting position", 'pos_from', $startPos);
        $params[] =  Toolkit::AddParameterSize("Length of data",'dataLen', $labelForSizeOfInputData);
        $params[] =  $param;
        $params[] =  Toolkit::AddParameterChar('in', 1, "Force changes to auxiliary storage", 'aux_storage', '0');
        $params[] =  Toolkit::AddErrorDataStruct();

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
        $params[] = Toolkit::AddParameterChar('in', 20,  "User space name and library", 'userspacename', $this->getUSFullName());
        $params[] = Toolkit::AddParameterInt32('in',  "From position", 'position_from', $frompos);

        $receiverVarName = 'receiverdata';

        if ($receiveStructure) {
            // must be a ProgramParameter
            if (!is_object($receiveStructure)) {
                throw new \Exception('Parameter 3 passed to ReadUserSpace must be a ProgramParameter object.');
            }

            $labelForSizeOfInputData = 'dssize';
//
            $params[] = Toolkit::AddParameterSize("Length of data", 'dataLen', $labelForSizeOfInputData);

            // wrap simple ds around receive structure so we can assign a varname to retrieve later.
            $receiveDs[] = $receiveStructure;
            $params[] = Toolkit::AddDataStruct($receiveDs, $receiverVarName, 0, '', false, $labelForSizeOfInputData);

        } else {
            // regular readlen, no special structure or size labels.
            $params[] = Toolkit::AddParameterInt32('in',  "Size of data", 'datasize', $readlen);
            $params[] = Toolkit::AddParameterChar('out', $readlen, $receiverVarName, $receiverVarName, $receiveStructure);
        }

        $params[] = Toolkit::AddErrorDataStruct();

        $retPgmArr = $this->ToolkitSrvObj->PgmCall('QUSRTVUS', 'QSYS', $params);

        if ($this->ToolkitSrvObj->verify_CPFError($retPgmArr , "Read user space failed. Error:"))
            return false;
        $retArr = $retPgmArr['io_param'];

        // return our receiverstructure.
        return $retArr[$receiverVarName];
    }

    /*    private function DefineUserSpaceParameters($InitSize, $Auth, $InitChar)
        {
            $params[] = Toolkit::AddParameterChar('in',20, "USER SPACE NAME", 'userspacename', $this->getUSFullName());
            $params[] = Toolkit::AddParameterChar('in',10, "USER TYPE",'userspacetype', "PF        ");
            $params[] = Toolkit::AddParameterInt32('in',  "US SIZE", 'userspacesize', $InitSize);
            $params[] = Toolkit::AddParameterChar('in',1, "INITIALIZATION VALUE", 'initval', $InitChar);
            $params[] = Toolkit::AddParameterChar('in',10, "AUTHORITY", 'authority', $Auth);
            $params[] = Toolkit::AddParameterChar('in',50, "COMMENTS", 'comment', "ZS XML Service");
            $params[] = Toolkit::AddParameterChar('in',10, "Replace US", 'replaceuserspace', "*NO       ");
            $params[] = Toolkit::AddErrorDataStruct();

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
