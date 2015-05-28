<?php
use ToolkitApi\ToolkitService;
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'ToolkitApi' . DIRECTORY_SEPARATOR . 'ToolkitService.php';
echo 'Test ';
if (assert('1.6.1' === ToolkitService::VERSION, 'Version number should be 1.6.1' . PHP_EOL)) {
    echo 'passed.';
}
echo PHP_EOL;
