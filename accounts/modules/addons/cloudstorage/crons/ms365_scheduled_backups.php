<?php

/**
 * Run every minute via WHMCS cron or system cron:
 * php -q /path/to/whmcs/modules/addons/cloudstorage/crons/ms365_scheduled_backups.php
 */

require_once dirname(__DIR__, 4) . '/init.php';
require_once __DIR__ . '/../lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365JobScheduler;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

try {
    $result = Ms365JobScheduler::runDueJobs();
    $started = (int) ($result['started'] ?? 0);
    $skipped = (int) ($result['skipped'] ?? 0);
    if (function_exists('logActivity') && ($started > 0 || $skipped > 0)) {
        $message = 'MS365 scheduled backups: started ' . $started . ' job(s)';
        if ($skipped > 0) {
            $message .= ', skipped ' . $skipped . ' overlapping slot(s)';
        }
        logActivity($message);
    }
} catch (\Throwable $e) {
    if (function_exists('logActivity')) {
        logActivity('MS365 scheduled backups error: ' . $e->getMessage());
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
