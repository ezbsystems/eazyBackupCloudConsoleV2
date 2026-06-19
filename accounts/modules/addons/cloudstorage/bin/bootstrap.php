<?php
declare(strict_types=1);

/**
 * Ops/bootstrap helper for cloudstorage CLI scripts.
 */

$baseDir = dirname(__DIR__, 4);
$initPath = $baseDir . '/init.php';

if (!is_file($initPath)) {
    fwrite(STDERR, "[bootstrap] WHMCS init.php not found at {$initPath}\n");
    exit(1);
}

require_once $initPath;

if (!defined('WHMCS')) {
    fwrite(STDERR, "[bootstrap] WHMCS constant not defined after loading init.php.\n");
    exit(1);
}

$modulePath = dirname(__DIR__) . '/cloudstorage.php';
if (is_file($modulePath)) {
    require_once $modulePath;
}
