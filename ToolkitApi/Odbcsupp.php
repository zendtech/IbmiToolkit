<?php
namespace ToolkitApi;

/**
 * Class odbcsupp
 *
 * @package ToolkitApi
 */
class odbcsupp
{
    /**
     * @var string
     */
    private $last_errorcode;

    /**
     * @var string
     */
    private $last_errormsg;

    /**
     * 
     * @todo should perhaps handle this method differently if $options are not passed
     *
     * @param string $database
     * @param string $user
     * @param string $password
     * @param array|null $options
     * @return bool|resource
     */
    public function connect($database, $user, $password, $options = null)
    {
        if ($options) {
            if ((isset($options['persistent'])) && $options['persistent']) {
                $conn = odbc_pconnect($database, $user, $password);
            } else {
                $conn = odbc_connect($database, $user, $password);
            }

            if (is_resource($conn)) {
                return $conn;
            }
        }
        
        $this->setError();
        return false;
    }

    /**
     * @param resource $conn
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
     * @param resource|null $conn
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
     * @param string $errorCode
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
     * @param string $errorMsg
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
     * @param resource $conn
     * @param string $stmt
     * @param array $bindArray
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
     * @param resource $conn
     * @param string $stmt
     * @return array
     */
    public function executeQuery($conn, $stmt)
    {
        $txt = array();
        $crsr = odbc_exec($conn, $stmt);
        
        if (is_resource($crsr)) {      
            while (odbc_fetch_row($crsr)) {  
                $row = odbc_result($crsr, 1);
                
                if (!$row) {
                    break;
                }
                
                $txt[]=  $row;
            }
        } else {
            $this->setError($conn);
        }
        
        return $txt;
    }
}
