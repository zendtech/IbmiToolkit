<?php
namespace ToolkitApi;

/**
 * Class odbcsupp
 *
 * @package ToolkitApi
 */
class odbcsupp
{
    private $last_errorcode;
    private $last_errormsg;
    private $database;
    private $user;
    private $password;
    private $options;

    /**
     * @param $database
     * @param $user
     * @param $password
     * @param null $options
     */
    public function __construct($database, $user, $password, $options = null)
    {
        $this->database = $database;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
    }

    /**
     * 
     * @todo should perhaps handle this method differently if $options are not passed
     *
     * @return bool|resource
     */
    public function connect()
    {
        if ($this->options) {
            if ((isset($this->options['persistent'])) && $this->options['persistent']) {
                $conn = odbc_pconnect($this->database, $this->user, $this->password);
            } else {
                $conn = odbc_connect($this->database, $this->user, $this->password);
            }

            if (is_resource($conn)) {
                return $conn;
            }
        }
        
        $this->setError();
        return false;
    }

    /**
     * @param $conn
     */
    public function disconnect($conn)
    {
        if (is_resource($conn)) {
            odbc_close($conn);
        }
    }

    /**
     * set error code and message based on last odbc connection/prepare/execute error.
     * 
     * @todo: consider using GET DIAGNOSTICS for even more message text:
     * http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Frzala%2Frzalafinder.htm
     * 
     * @param null $conn
     */
    protected function setError($conn = null)
    {
        // is conn resource provided, or do we get last error?
        if ($conn) {
            $this->setErrorCode(odbc_error($conn));
            $this->setErrorMsg(odbc_errormsg($conn));
        } else {
            $this->setErrorCode(odbc_error());
            $this->setErrorMsg(odbc_errormsg());
        }
    }

    /**
     * @param $errorCode
     */
    protected function setErrorCode($errorCode)
    {
        $this->last_errorcode = $errorCode;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->last_errorcode;
    }

    /**
     * @param $errorMsg
     */
    protected function setErrorMsg($errorMsg)
    {
        $this->last_errormsg = $errorMsg;
    }

    /**
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->last_errormsg;
    }
    
    /**
     * this function used for special stored procedure call only
     * 
     * @param $conn
     * @param $stmt
     * @param $bindArray
     * @return string
     */
    public function execXMLStoredProcedure($conn, $stmt, $bindArray)
    {
        $crsr = odbc_prepare($conn, $stmt);
        
        if (!$crsr) { 
            $this->setError($conn);
            return false;
        }
        
        // extension problem: sends warning message into the php_log or stdout 
        // about number of result sets. (switch on return code of SQLExecute() 
        // SQL_SUCCESS_WITH_INFO
        if (!@odbc_execute($crsr , array($bindArray['internalKey'], $bindArray['controlKey'], $bindArray['inputXml']))) {
            $this->setError($conn);
            return "ODBC error code: " . $this->getErrorCode() . ' msg: ' . $this->getErrorMsg();
        }
        
        // disconnect operation cause crush in fetch, nothing appears as sql script.
        $row='';
        $outputXML = '';
        if (!$bindArray['disconnect']) {
            while (odbc_fetch_row($crsr)) {
                $tmp = odbc_result($crsr, 1);
                
                if ($tmp) {
                    // because of ODBC problem blob transferring should execute some "clean" on returned data
                    if (strstr($tmp , "</script>")) {
                        $pos = strpos($tmp, "</script>");
                        $pos += strlen("</script>"); // @todo why append this value?
                        $row .= substr($tmp, 0, $pos);
                        break;
                    } else {
                        $row .= $tmp;
                    }
                }
            }
            $outputXML = $row;
        }
        
        return $outputXML;
    }

    /**
     * @param $conn
     * @param $stmt
     * @return array
     */
    public function executeQuery($conn, $stmt)
    {
        $crsr = odbc_exec($conn, $stmt);
        
        if (is_resource($crsr)) {      
            while (odbc_fetch_row($crsr)) {  
                $row = odbc_result($crsr, 1);
                
                if (!$row) {
                    break;
                }
                
                $Txt[]=  $row;
            }
        } else {
            $this->setError($conn);
        }
        
        return $Txt;
    }
}
