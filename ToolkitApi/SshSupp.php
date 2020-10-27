<?php
namespace ToolkitApi;

/**
 * 
 * @todo define common transport class/interface extended/implemented by all transports. They have much in common.
 * 
 * @package ToolkitApi
 */
class SshSupp
{
    protected $last_errorcode;
    protected $last_errormsg;

    protected $conn;

    /**
     * @param $xmlIn
     * @return string|bool
     */
    public function send($xmlIn, $byteSize)
    {
        // xmlservice-cli takes no options in regards to statefulness
        // (IpcDir, etc) or byte size
        $exec_res = ssh2_exec($this->conn, "/QOpenSys/pkgs/bin/xmlservice-cli");
        if (!$exec_res) {
            $this->setErrorMsg("error executing command over SSH");
            return false;
        }
        fwrite($exec_res, $xmlIn);
        
        return stream_get_contents($exec_res);
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
     * @param string $server
     * @param $user
     * @param $password
     * @return SshSupp|bool
     */
    public function connect($server, $user, $password, $options = array())
    {
        // XXX: Set options on ourself here
        $conn = ssh2_connect($server, 22);
        if (!$conn) {
            $this->setErrorMsg("error connecting to SSH");
            return false;        
        }
        // XXX: Check fingerprint here
        // XXX: Public key auth
        if (!ssh2_auth_password($conn, $user, $password) {
            $this->setErrorMsg("error performing password auth over SSH");
            return false;        
        }
        
        $this->conn = $conn;
    
        return $this;
    }
    
    public function disconnect()
    {
        if (is_resource($this->conn)) {
            ssh2_disconnect($this->conn);
        }
    }
}
