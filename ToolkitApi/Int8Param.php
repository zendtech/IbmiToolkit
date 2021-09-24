<?php
namespace ToolkitApi;

/**
 * Class Int8Param
 * @package ToolkitApi
 */
class Int8Param extends ProgramParameter
{
    /**
     * Int8Param constructor.
     * @param $io
     * @param $comment
     * @param string $varName
     * @param string $value
     * @param int $dimension
     * @param string $by
     * @param bool|false $isArray
     * @param null $labelSetLen
     */
    public function __construct($io, $comment, $varName='', $value='', $dimension=0, $by='', $isArray=false, $labelSetLen=null)
    {
        parent::__construct('3i0', $io, $comment, $varName, $value, 'off', $dimension, $by, $isArray, $labelSetLen, null);

        return $this;
    }
}