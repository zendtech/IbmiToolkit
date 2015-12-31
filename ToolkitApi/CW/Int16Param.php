<?php
namespace ToolkitApi;

class Int16Param extends ProgramParameter
{
    public function __construct($io, $comment, $varName='', $value, $dimension=0, $by='', $isArray = false, $labelSetLen=null)
    {
        parent::__construct('5i0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, $labelSetLen, null);

        return $this;
    }
}