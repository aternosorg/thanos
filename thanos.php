#!/usr/bin/php
<?php

use Aternos\Thanos\Helper;
use Aternos\Thanos\Thanos;
use Aternos\Thanos\World\AnvilWorld;

require_once 'vendor/autoload.php';

if (!isset($argv[1])) {
    exit("Usage: cleanup.php <world> [<output>]\n");
}

$input = $argv[1];
$output = null;
$moveOutput = false;

if (isset($argv[2])) {
    $output = $argv[2];
} else {
    $output = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'thanos-' . uniqid();
    $moveOutput = true;
}

if (!is_dir($input) || count(scandir($input)) === 2) {
    exit('World must be a directory and not empty' . PHP_EOL);
}

if (file_exists($output) && count(scandir($output)) !== 2) {
    exit('Output directory must be empty' . PHP_EOL);
}

if (!file_exists($output)) {
    mkdir($output);
}


$startTime = microtime(true);
$world = new AnvilWorld($input, $output);
$thanos = new Thanos();
$thanos->setMinInhabitedTime(0);
$removedChunks = $thanos->snap($world);
if ($moveOutput) {
    Helper::removeDirectory($input);
    rename($output, $input);
}

echo sprintf('Removed %d chunks in %.2f seconds',
    $removedChunks,
    round(microtime(true) - $startTime, 2)
);
echo PHP_EOL;
