<?php

require __DIR__ . '/../vendor/autoload.php';

function getConfig(): array
{
    return require __DIR__ . '/config/db.config.php';
}
