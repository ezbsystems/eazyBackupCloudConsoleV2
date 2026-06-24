<?php
declare(strict_types=1);

/**
 * Production-side pull sync for worker releases from development WHMCS.
 * Schedule on prod when ms365_production_release_sync_enabled is on.
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Fleet\ReleaseSyncService;

try {
    ms365backup_apply_migrations();
    $result = ReleaseSyncService::pullFromDevelopment();
    echo json_encode(['status' => 'ok', 'result' => $result], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(($result['status'] ?? '') === 'failed' ? 1 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
