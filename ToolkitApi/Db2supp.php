<?php
namespace ToolkitApi;

/**
 * Class db2supp
 *
 * @todo define common transport class/interface extended/implemented by all transports
 *
 * @package ToolkitApi
 */
class db2supp
{
    private $last_errorcode;
    private $last_errormsg;

    /**
     *
     *
     * @todo Throw in your "transport/adapter" framework for a real OO look and feel ....
     * Throw new Exception("Fail execute ($sql) ".db2_stmt_errormsg(),db2_stmt_error());
     * ... and retrieve via try/catch + Exception methods.
     *
     * @param $database
     * @param $user
     * @param $password
     * @param null $options 'persistent' is one option
     * @return bool
     */
    public function connect($database, $user, $password, $options = null)
    {
        // Compensate for older ibm_db2 driver that may not do this check.
        if ($user && empty($password)) {
            $this->setErrorCode('08001');
            $this->setErrorMsg('Authorization failure on distributed database connection attempt. SQLCODE=-30082');

            return false;
        }

        if ($options) {
            $driver_options = array();

            // Test for existence of driver options
            if (array_key_exists('driver_options', $options)) {
                $driver_options = $options['driver_options'] ?: array();
            }

            if ((isset($options['persistent'])) && $options['persistent']) {
                $conn = db2_pconnect($database, $user, $password, $driver_options);
            } else {
                $conn = db2_connect($database, $user, $password, $driver_options);
            }

            if (is_resource($conn)) {
                return $conn;
            }
        }

        $this->setErrorCode(db2_conn_error());
        $this->setErrorMsg(db2_conn_errormsg());

        return false;
    }

    /**
     * @param $conn
     */
    public function disconnect($conn)
    {
        if (is_resource($conn)) {
            db2_close($conn);
        }
    }

    /**
     * disconnect, truly close, a persistent connection.
     *
     * NOTE: Only available on i5/OS
     *
     * @param $conn
     */
    public function disconnectPersistent($conn)
    {
        if (is_resource($conn)) {
            db2_pclose($conn);
        }
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->last_errorcode;
    }

    /**
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->last_errormsg;
    }

    /**
     * set error code and message based on last db2 prepare or execute error.
     *
     * @todo: consider using GET DIAGNOSTICS for even more message text:
     * http://publib.boulder.ibm.com/infocenter/iseries/v5r4/index.jsp?topic=%2Frzala%2Frzalafinder.htm
     *
     * @param null $stmt
     */
    protected function setStmtError($stmt = null)
    {
        if ($stmt) {
            $this->setErrorCode(db2_stmt_error($stmt));
            $this->setErrorMsg(db2_stmt_errormsg($stmt));
        } else {
            $this->setErrorCode(db2_stmt_error());
            $this->setErrorMsg(db2_stmt_errormsg());
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
     * @param $errorMsg
     */
    protected function setErrorMsg($errorMsg)
    {
        $this->last_errormsg = $errorMsg;
    }

    /**
     * this function used for special stored procedure call only
     *
     * @param $conn
     * @param $sql
     * @return bool
     */
    public function execXMLStoredProcedure($conn, $sql, $bindArray)
    {

        $internalKey = $bindArray['internalKey'];
        $controlKey = $bindArray['controlKey'];
        $inputXml = $bindArray['inputXml'];
        $outputXml = $bindArray['outputXml'];

        // @todo see why error doesn't properly bubble up to top level.
        $crsr = @db2_prepare($conn, $sql);
        if (!$crsr) {
            $this->setStmtError();
            return false;
        }

        // stored procedure takes four parameters. Each 'name' will be bound to a real PHP variable
        $params = array(
            array('position' => 1, 'name' => "internalKey", 'inout' => DB2_PARAM_IN),
            array('position' => 2, 'name' => "controlKey",  'inout' => DB2_PARAM_IN),
            array('position' => 3, 'name' => "inputXml",    'inout' => DB2_PARAM_IN),
            array('position' => 4, 'name' => "outputXml",   'inout' => DB2_PARAM_OUT),
        );

        // bind the four parameters
        foreach ($params as $param) {
            $ret = db2_bind_param ($crsr, $param['position'], $param['name'], $param['inout']);
            if (!$ret) {
                // unable to bind a param. Set error and exit
                $this->setStmtError($crsr);
                return false;
            }
        }

        $ret = @db2_execute($crsr);
        if (!$ret) {
            // execution of XMLSERVICE stored procedure failed.
            $this->setStmtError($crsr);
            return false;
        }

        return $outputXml;
    }

    /**
     * returns a first column from sql stmt result set
     *
     * used in one place: iToolkitService's ReadSPLFData().
     *
     * @todo eliminate this method if possible.
     *
     * @param $conn
     * @param $sql
     * @throws \Exception
     * @return array
     */
    public function executeQuery($conn, $sql)
    {
        $txt = array();
        $stmt = db2_exec($conn, $sql, array('cursor' => DB2_SCROLLABLE));
        if (is_resource($stmt)) {
            if (db2_fetch_row($stmt)) {
                $column = db2_result($stmt, 0);
                $txt[] = $column;
            }
        } else {
            $this->setStmtError();
            Throw new \Exception("Failure executing SQL: ($sql) " . db2_stmt_errormsg(), db2_stmt_error());
        }

        return $txt;
    }
}
