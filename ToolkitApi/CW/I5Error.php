<?php
namespace ToolkitApi\CW;

/**
 * Class to handle errors in a manner similar to the old toolkit.
 * Singleton class because we only hold on to the last error (one).
 *
 */
class I5Error
{
    static $instance = null;
    static protected $_i5Error = array();

    /**
     * @return null
     */
    static function getInstance()
    {
        if(self::$instance == NULL){
            $className = __CLASS__;
            self::$instance=new $className();
        }

        if (self::$instance) {
            return self::$instance;
        }
    }

    /**
     *
     */
    protected function __construct()
    {
        // initialize
        $this->setI5Error(0, 0, '', '');
    }

    /**
     * __toString will make it easy to output an error via echo or print
     *
     * @return string
     */
    public function __toString()
    {
        $err = $this->getI5Error();
        return "i5Error: num={$err['num']} cat={$err['cat']} msg=\"{$err['msg']}\" desc=\"{$err['desc']}\"";
    }

    /**
     * Set error information for last action.
     *
     * @param int    $errNum    Error number (according to old toolkit). Zero/false if no error
     * @param string    $errCat    Category of error
     * @param string $errMsg    Error message (often a CPF code but sometimes just a message)
     * @param string $errDesc   Longer description of error
     * @return void
     */
    public function setI5Error($errNum, $errCat = I5_CAT_PHP, $errMsg = '', $errDesc = '')
    {
        // the array (eventually returned by i5_error()
        // likes to have both numeric and alphanumeric keys.
        $i5ErrorArray = array(0=>$errNum,     1=>$errCat,      2=>$errMsg,    3=>$errDesc,
            'num'=>$errNum, 'cat'=>$errCat, 'msg'=>$errMsg, 'desc'=>$errDesc);

        self::$_i5Error = $i5ErrorArray;
    }

    /**
     * Return i5 error array for most recent action.
     *
     * @return array Error array for most recent action.
     */
    public function getI5Error()
    {
        return self::$_i5Error;
    }
}