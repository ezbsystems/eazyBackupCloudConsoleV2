<?php
declare(strict_types=1);

/**
 * Sync ms365-backup-worker browse CLI from fleet release.
 *
 * Usage:
 *   php modules/addons/ms365backup/bin/ms365_install_browse_binary.php
 *   php modules/addons/ms365backup/bin/ms365_install_browse_binary.php --release-id=94
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Fleet\BrowseBinaryInstaller;

ms365backup_apply_migrations();

$releaseId = 0;
foreach ($argv as $arg) {
    if (preg_match('/^--release-id=(\d+)$/', $arg, $m)) {
        $releaseId = (int) $m[1];
    }
}

$result = $releaseId > 0
    ? BrowseBinaryInstaller::syncFromRelease($releaseId)
    : BrowseBinaryInstaller::syncFromFleetTarget();

if (!$result['ok']) {
    fwrite(STDERR, "Browse binary sync failed: " . ($result['error'] ?: 'unknown') . "\n");
    fwrite(STDERR, "Run: php " . __DIR__ . "/ms365_browse_binary_diag.php\n");
}
echo json_encode(['status' => $result['ok'] ? 'ok' : 'failed', 'browse_sync' => $result], JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
