<?php
namespace ToolkitApi;

/**
 * Class DateTimeApi
 *
 * @package ToolkitApi
 */
class DateTimeApi
{
    protected $ToolkitSrvObj;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof Toolkit) {
            $this->ToolkitSrvObj = $ToolkitSrvObj ;
        }
    }

    /**
     * from 8-character *DTS format to 17-character full date and time
     *
     * @param $dtsDateTime
     * @return bool
     */
    public function dtsToYymmdd($dtsDateTime)
    {
        $inputFormat = "*DTS"; // special system format, returned by some APIs.
        $outputFormat = "*YYMD"; // 17 chars long
        $outputVarname = 'datetimeOut';

        $apiPgm = 'QWCCVTDT';
        $apiLib = 'QSYS';

        $paramXml = "<parm io='in' comment='1. Input format'>
            <data var='formatIn' type='10A' comment='*DTS is system time stamp format'>$inputFormat</data>
            </parm>
            <parm io='in' comment='2. Input variable'>
            <data var='datetimeIn' type='8b'  comment='*DTS format is type 8b (binary)'>$dtsDateTime</data>
            </parm>
            <parm io='in' comment='3. Output format'>
            <data var='formatOut' type='10A' comment='*YYMD means YYYYMMDDHHMMSSmmm (milliseconds)'>$outputFormat</data>
            </parm>
            <parm io='out' comment='4. Output variable'>
            <ds var='$outputVarname' comment='Data structure, total of 17 bytes, to split date/time into YYYYMMDD, HHMMSS, and microseconds, as indicated by *YYMD format'>
            <data var='date' type='8a' comment='YYYYMMDD' />
            <data var='time' type='6a' comment='HHMMSS' />
            <data var='microseconds' type='3a' comment='microsecs (3 digits)' />
            </ds>
            </parm>\n" .
            Toolkit::getErrorDataStructXml(5); // param number 5

        // pass param xml directly in.
        $retPgmArr = $this->ToolkitSrvObj->PgmCall($apiPgm, $apiLib, $paramXml);
        if ($this->ToolkitSrvObj->getErrorCode()) {
            return false;
        }

        $retArr = $retPgmArr['io_param'][$outputVarname];

        return $retArr;
    }
}
