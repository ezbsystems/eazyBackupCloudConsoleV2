<?php
declare(strict_types=1);

/**
 * Diagnose ms365-backup-worker browse binary install prerequisites.
 *
 * Usage: php modules/addons/ms365backup/bin/ms365_browse_binary_diag.php
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
use Ms365Backup\Fleet\FleetSettings;
use Ms365Backup\Fleet\ReleaseRepository;

ms365backup_apply_migrations();

$latest = ReleaseRepository::latest();
$repo = FleetSettings::repoPath();
$artifactRoot = FleetSettings::artifactRoot();
$dest = rtrim($repo, '/') . '/ms365-backup-worker';
$status = BrowseBinaryInstaller::status();

$lines = [
    'repo_path' => $repo,
    'artifact_root' => $artifactRoot,
    'dest' => $dest,
    'browse_status' => $status,
];

if ($latest === null) {
    $lines['latest_release'] = null;
    $lines['failure'] = 'no_release_row';
    $lines['hint'] = 'Run: php ' . dirname(__FILE__) . '/../crons/ms365_worker_release_sync.php';
    echo json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$version = (string) ($latest['version'] ?? '');
$src = trim((string) ($latest['artifact_path'] ?? ''));
$alt = $version !== '' ? rtrim($artifactRoot, '/') . '/' . $version . '/ms365-backup-worker' : '';

$lines['latest_release'] = [
    'id' => (int) ($latest['id'] ?? 0),
    'version' => $version,
    'artifact_path' => $src,
    'sha256' => (string) ($latest['sha256'] ?? ''),
];
$lines['artifact_alt_path'] = $alt;
$lines['source_exists'] = $src !== '' && is_file($src);
$lines['source_readable'] = $src !== '' && is_readable($src);
$lines['artifact_alt_exists'] = $alt !== '' && is_file($alt);
$lines['repo_dir_exists'] = is_dir($repo);
$lines['repo_dir_writable'] = is_dir($repo) && is_writable($repo);
$lines['repo_parent_writable'] = is_writable(dirname($repo));
$lines['dest_exists'] = is_file($dest);
$lines['dest_executable'] = is_executable($dest);

$failure = null;
if ($src === '' || !is_file($src)) {
    $failure = 'artifact_missing';
    $lines['hint'] = 'Release row exists but artifact file is missing (rsync --delete may have removed storage/worker-releases). '
        . 'Run: php ' . dirname(__FILE__) . '/../crons/ms365_worker_release_sync.php';
} elseif (!is_readable($src)) {
    $failure = 'artifact_not_readable';
} elseif (!is_dir($repo) && !is_writable(dirname($repo))) {
    $failure = 'repo_path_not_writable';
    $lines['hint'] = 'mkdir/chown ' . dirname($repo) . ' for www-data';
} elseif (!is_dir($repo) && !@mkdir($repo, 0755, true) && !is_dir($repo)) {
    $failure = 'repo_path_mkdir_failed';
} elseif ($status['status'] !== 'synced') {
    $failure = 'browse_' . $status['status'];
    $lines['hint'] = 'Run: php ' . dirname(__FILE__) . '/ms365_install_browse_binary.php';
} else {
    $lines['can_install'] = true;
}

$lines['failure'] = $failure;

echo json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failure === null ? 0 : 1);
