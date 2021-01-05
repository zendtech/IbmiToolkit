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
            $this->setErrorMsg("the ssh2 extension is too old to support ssh2_send_eof");
            return false;
        }
        // XXX: Set options on ourself here
        $conn = ssh2_connect($server, 22);
        if (!$conn) {
            $this->setErrorCode("SSH2_CONNECT");
            $this->setErrorMsg("error connecting to SSH");
            return false;        
        }
        // XXX: should probably be doing better than SHA1 here, but ssh2
        $remoteFP = ssh2_fingerprint($conn, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX);
        $trustedFP = array_key_exists("sshFingerprint", $options) ? $options["sshFingerprint"] : false;
        if ($trustedFP !== false && $trustedFP !== $remoteFP) {
            $this->setErrorCode("SSH2_FINGERPRINT");
            $this->setErrorMsg("the fingerprint ($remoteFP) differs from the set fingerprint");
            ssh2_disconnect($conn);
            return false;
        }
        $authMethod = array_key_exists("sshMethod", $options) ? $options["sshMethod"] : "password";
        switch ($authMethod) {
        case "keyfile":
            $pub = $options["sshPublicKeyFile"];
            $priv = $options["sshPrivateKeyFile"];
            $passphrase = $options["sshPrivateKeyPassphrase"];
            if (!ssh2_auth_pubkey_file($conn, $user, $pub, $priv, $passphrase)) {
                $this->setErrorCode("SSH2_AUTH_KEYFILE");
                $this->setErrorMsg("error performing keyfile auth over SSH");
                ssh2_disconnect($conn);
                return false;
            }
            break;
        case "agent":
            if (!ssh2_auth_agent($conn, $user)) {
                $this->setErrorCode("SSH2_AUTH_AGENT");
                $this->setErrorMsg("error performing agent auth over SSH");
                ssh2_disconnect($conn);
                return false;
            }
            break;
        case "password":
            if (!ssh2_auth_password($conn, $user, $password)) {
                $this->setErrorCode("SSH2_AUTH_PASSWORD");
                $this->setErrorMsg("error performing password auth over SSH");
                ssh2_disconnect($conn);
                return false;
            }
            break;
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
