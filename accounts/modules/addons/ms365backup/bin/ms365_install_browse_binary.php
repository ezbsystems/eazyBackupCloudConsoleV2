<?php
declare(strict_types=1);

/**
 * Sync ms365-backup-worker browse CLI from latest fleet release.
 *
 * Usage: php modules/addons/ms365backup/bin/ms365_install_browse_binary.php
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

$ok = BrowseBinaryInstaller::syncFromLatestRelease();
echo json_encode(['status' => $ok ? 'ok' : 'failed'], JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
