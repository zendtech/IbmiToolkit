<?php
namespace ToolkitApi;

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
     * @param ToolkitInterface $ToolkitSrvObj
     */
    public function __construct($requestHandle, $totalRecords, $receiverDs, $lengthOfReceiverVariable, ToolkitInterface $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof Toolkit) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;
        }

        $this->_requestHandle = $requestHandle;
        $this->_receiverSize = $lengthOfReceiverVariable;
        $this->_totalRecords = $totalRecords;
        $this->_nextRecordToRequest = 1; // will request record #1 when someone asks
        $this->_receiverDs = $receiverDs; // will request record #1 when someone asks
    }

    /**
     * @return ToolkitInterface
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
            Toolkit::getErrorDataStructXmlWithCode(7); // param number 7

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
                    </parm>\n" . Toolkit::getErrorDataStructXml(2); // param number 2

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
