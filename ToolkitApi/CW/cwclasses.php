<?php
require_once __DIR__ . '/../autoload.php';

// cwclasses.php: classes for Compatibility Wrapper for IBM i Toolkit for PHP

// toolkit path should be defined in PHP.INI. Default: /usr/local/zendsvr/share/ToolkitApi

require_once __DIR__.'/cwconstants.php';

class_alias('ToolkitApi\CW\ToolkitServiceCw','ToolkitServiceCw');
class_alias('ToolkitApi\CW\I5Error','I5Error');
class_alias('ToolkitApi\CW\DataDescription','DataDescription');
class_alias('ToolkitApi\CW\DataDescriptionPcml','DataDescriptionPcml');