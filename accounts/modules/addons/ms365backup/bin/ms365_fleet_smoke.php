<?php
declare(strict_types=1);

/**
 * Fleet smoke checks (run on WHMCS host).
 * Usage: php ms365_fleet_smoke.php
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Fleet\ArtifactService;
use Ms365Backup\Fleet\FleetSettings;
use Ms365Backup\Fleet\FleetSummaryService;
use Ms365Backup\Fleet\ReleaseRepository;

$ok = true;
$check = static function (string $name, bool $pass, string $detail = '') use (&$ok): void {
    echo ($pass ? '[OK] ' : '[FAIL] ') . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    if (!$pass) {
        $ok = false;
    }
};

ms365backup_apply_migrations();

$repo = FleetSettings::repoPath();
$check('repo_path', is_dir($repo), $repo);

$artifactRoot = FleetSettings::artifactRoot();
$check('artifact_root', is_dir($artifactRoot) || @mkdir($artifactRoot, 0750, true), $artifactRoot);

$summary = FleetSummaryService::summary();
$check('fleet_summary', isset($summary['active_nodes']), 'nodes=' . ($summary['active_nodes'] ?? 0));

$latest = ReleaseRepository::latest();
if ($latest) {
    $nonce = ArtifactService::issueNonce((int) $latest['id'], '00000000-0000-0000-0000-000000000000');
    $verified = ArtifactService::verifyNonce($nonce);
    $check('artifact_nonce', $verified !== null && $verified['release_id'] === (int) $latest['id']);
    $check('artifact_file', is_file((string) $latest['artifact_path']), (string) ($latest['version'] ?? ''));
} else {
    echo "[SKIP] artifact_nonce — no releases yet\n";
}

exit($ok ? 0 : 1);
