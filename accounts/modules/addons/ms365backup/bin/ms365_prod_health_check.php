<?php
declare(strict_types=1);

/**
 * Quick production health check after deploy (no migrations).
 *
 * Usage: php modules/addons/ms365backup/bin/ms365_prod_health_check.php
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;

$checks = [];
$ok = true;
$record = static function (string $name, bool $pass, string $detail = '') use (&$checks, &$ok): void {
    $checks[] = ['name' => $name, 'ok' => $pass, 'detail' => $detail];
    if (!$pass) {
        $ok = false;
    }
};

$record('whmcs_init', defined('WHMCS'));
$record('capsule', class_exists(\WHMCS\Database\Capsule::class));

$paths = [
    'ms365_vendor' => dirname(__DIR__) . '/vendor/autoload.php',
    'ms365_autoload' => dirname(__DIR__) . '/ms365backup_autoload.php',
    'cloudstorage_main' => dirname(__DIR__, 2) . '/cloudstorage/cloudstorage.php',
    'accounts_vendor' => dirname(__DIR__, 4) . '/vendor/autoload.php',
];
foreach ($paths as $name => $path) {
    $record($name, is_file($path), $path);
}

require_once dirname(__DIR__) . '/ms365backup_autoload.php';
$record('kopia_browse_class', class_exists(\Ms365Backup\KopiaSnapshotBrowseService::class));

$browseStatus = \Ms365Backup\Fleet\BrowseBinaryInstaller::status();
$browseSynced = $browseStatus['status'] === 'synced' && $browseStatus['executable'];
$browseDetail = ($browseStatus['dest'] ?? '') . ' status=' . ($browseStatus['status'] ?? 'unknown')
    . ' installed=' . ($browseStatus['installed_version'] ?? 'none')
    . ' target=' . ($browseStatus['target_version'] ?? 'none');
if (!$browseSynced && !empty($browseStatus['hint'])) {
    $browseDetail .= ' hint=' . $browseStatus['hint'];
}
$record('browse_binary', $browseSynced, $browseDetail);

$diagnostics = $browseStatus['diagnostics'] ?? \Ms365Backup\Fleet\BrowseBinaryInstaller::pathDiagnostics((string) ($browseStatus['dest'] ?? ''));
$record(
    'browse_binary_writable',
    (bool) ($diagnostics['can_install'] ?? false),
    'php_user=' . ($diagnostics['php_user'] ?? '?')
        . ' dest_owner=' . ($diagnostics['dest_owner'] ?? 'n/a')
        . ' dest_writable=' . ((isset($diagnostics['dest_writable']) && $diagnostics['dest_writable']) ? 'yes' : 'no')
);

echo json_encode([
    'status' => $ok ? 'ok' : 'failed',
    'checks' => $checks,
    'browse_binary' => $browseStatus,
    'browse_diagnostics' => $diagnostics,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($ok ? 0 : 1);
