<?php
declare(strict_types=1);

/**
 * Dev harness bootstrap for cloudstorage addon (Kopia retention work).
 * - Loads WHMCS init
 * - Bootstraps cloudstorage module
 * - Fails clearly if WHMCS constant unavailable
 */

$baseDir = dirname(__DIR__, 5);
$initPath = $baseDir . '/init.php';

if (!is_file($initPath)) {
    $mainRepoInit = dirname(dirname(dirname($baseDir))) . '/accounts/init.php';
    if (is_file($mainRepoInit)) {
        $initPath = $mainRepoInit;
    } else {
        fwrite(STDERR, "[bootstrap] WHMCS init.php not found. Tried: {$baseDir}/init.php and {$mainRepoInit}\n");
        exit(1);
    }
}

require_once $initPath;

if (!defined('WHMCS')) {
    fwrite(STDERR, "[bootstrap] WHMCS constant not defined after loading init.php. Cannot proceed.\n");
    exit(1);
}

// Module bootstrap: ensure cloudstorage is loadable (cloudstorage.php guards with WHMCS constant)
$modulePath = dirname(__DIR__, 2) . '/cloudstorage.php';
if (is_file($modulePath)) {
    require_once $modulePath;
}
