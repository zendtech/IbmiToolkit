<?php

use ToolkitApi\httpsupp;

class HttpSuppTest extends PHPUnit_Framework_TestCase
{
    public function testCanSetIpc()
    {
        $ipc = 'test';

        $httpsupp = new httpsupp();
        $httpsupp->setIpc($ipc);

        $this->assertEquals($ipc, $httpsupp->getIpc());
    }
    
    public function testIsIpcSet()
    {
        $ipc = 'test';
        
        $httpsupp = new httpsupp();
        $httpsupp->setIpc($ipc);
        
        $this->assertEquals($ipc, $httpsupp->getIpc());
    }
}