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
    public function send($xmlIn)
    {
        // xmlservice-cli takes no options in regards to statefulness
        // (IpcDir, etc) or byte size
        $ssh_stdio = ssh2_exec($this->conn, "/QOpenSys/pkgs/bin/xmlservice-cli");
        if (!$ssh_stdio) {
            $this->setErrorCode("SSH2_EXEC");
            $this->setErrorMsg("error executing command over SSH");
            return false;
	}
	/* XXX */
        stream_set_blocking($ssh_stdio, true);
        $written = fwrite($ssh_stdio, $xmlIn);
        if ($written === false) {
            $this->setErrorCode("SSH2_FWRITE");
            $this->setErrorMsg("error writing to stdin");
            return false;
        }
        fflush($ssh_stdio);
        ssh2_send_eof($ssh_stdio);
        $xmlOut = stream_get_contents($ssh_stdio);
        return $xmlOut;
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
        // Only post-1.2 versions of ssh2 have ssh2_send_eof
        if (!extension_loaded("ssh2")) {
            $this->setErrorCode("SSH2_NOT_LOADED");
            $this->setErrorMsg("the ssh2 extension isn't loaded");
            return false;
        }
        if (!function_exists("ssh2_send_eof")) {
            $this->setErrorCode("SSH2_NO_SEND_EOF");
            $this->setErrorMsg("the ssh2 extension is too old to support ssh2_end_eof");
            return false;
        }
        // XXX: Set options on ourself here
        $conn = ssh2_connect($server, 22);
        if (!$conn) {
            $this->setErrorCode("SSH2_CONNECT");
            $this->setErrorMsg("error connecting to SSH");
            return false;        
        }
        // XXX: Check fingerprint here
        $fingerprint = ssh2_fingerprint($conn, SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);
        // XXX: Public key auth
        if (!ssh2_auth_password($conn, $user, $password)) {
            $this->setErrorCode("SSH2_AUTH_PASSWORD");
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
