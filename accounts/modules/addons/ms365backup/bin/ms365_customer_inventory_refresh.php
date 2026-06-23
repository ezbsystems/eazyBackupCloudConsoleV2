#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Background worker: full tenant inventory refresh for e3 Cloud Backup.
 *
 * Usage: php ms365_customer_inventory_refresh.php --client-id=N --backup-user-id=N
 */

require_once __DIR__ . '/bootstrap.php';

use Ms365Backup\InventoryBackgroundRefresh;

$clientId = 0;
$backupUserId = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--client-id=')) {
        $clientId = (int) substr($arg, 12);
    }
    if (str_starts_with($arg, '--backup-user-id=')) {
        $backupUserId = (int) substr($arg, 17);
    }
}

if ($clientId <= 0 || $backupUserId <= 0) {
    ms365_log_line('Usage: php ms365_customer_inventory_refresh.php --client-id=N --backup-user-id=N');
    exit(1);
}

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    ms365_log_line('WHMCS init.php not found');
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

// #region agent log
Ms365Backup\Ms365AgentDebugLog::write(
    'ms365_customer_inventory_refresh.php:entry',
    'background worker started',
    ['client_id' => $clientId, 'backup_user_id' => $backupUserId],
    'F',
);
// #endregion

try {
    $result = InventoryBackgroundRefresh::run($clientId, $backupUserId);
    ms365_log_line(sprintf(
        'OK client=%d backup_user=%d resources=%d',
        $clientId,
        $backupUserId,
        (int) ($result['total_resources'] ?? 0),
    ));
    exit(0);
} catch (\Throwable $e) {
    ms365_log_line(sprintf(
        'FAIL client=%d backup_user=%d: %s',
        $clientId,
        $backupUserId,
        $e->getMessage(),
    ));
    exit(1);
}
