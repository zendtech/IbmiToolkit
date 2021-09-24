<?php
namespace ToolkitApi;

/**
 * 
 * @todo define common transport class/interface extended/implemented by all transports. They have much in common.
 * 
 * @package ToolkitApi
 */
class LocalSupp
{
    protected $last_errorcode;
    protected $last_errormsg;

    protected $xmlserviceCliPath;

    /**
     * @param $xmlIn
     * @return string|bool
     */
    public function send($xmlIn)
    {
        $descriptorspec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
            2 => array("pipe", "w"), // stderr
        );
        $proc = proc_open($this->getXmlserviceCliPath(), $descriptorspec, $pipes);
        if (!is_resource($proc)) {
            $this->setErrorCode("LOCAL_EXEC");
            $this->setErrorMsg("error executing command on local system");
            return false;
        }
        stream_set_blocking($pipes[0], true); // XXX
        $written = fwrite($pipes[0], $xmlIn);
        fclose($pipes[0]); // close stdin so the process starts to read
        $xmlOut = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        // XXX: Do something with stderr
        fclose($pipes[2]);
        proc_close($proc);
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
     * @param string $path
     */
    public function setXmlserviceCliPath($path)
    {
        $this->xmlserviceCliPath = $path;
    }

    /**
     * @return null
     */
    public function getXmlserviceCliPath()
    {
        return $this->xmlserviceCliPath;
    }

    private function checkCompat()
    {
        if (!extension_loaded("pcntl")) {
            $this->setErrorCode("PCNTL_NOT_LOADED");
            $this->setErrorMsg("the process control extension isn't loaded");
            return false;
        }
        return true;
    }

    /**
     * None of these options matter for a local transport.
     *
     * @param $server
     * @param $user
     * @param $password
     * @param $options
     * @return LocalSupp|bool
     */
    public function connect($server, $user, $password, $options)
    {
        if (!$this->checkCompat()) {
            return false;
        }
        // stateless, we don't have to do anything
        return $this;
    }
    
    public function disconnect()
    {
    }
}
