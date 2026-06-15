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
use Ms365Backup\Ms365EngineConfig;
use WHMCS\Database\Capsule;

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

$check('engine_mode_kopia', Ms365EngineConfig::engineMode() === Ms365EngineConfig::MODE_KOPIA, Ms365EngineConfig::engineMode());

$workerToken = Ms365EngineConfig::workerToken();
$check('worker_token_configured', $workerToken !== '', $workerToken !== '' ? 'set' : 'missing');

$activeNodes = (int) ($summary['active_nodes'] ?? 0);
if ($activeNodes < 1) {
    echo "[WARN] active_worker_nodes — none registered (deploy Proxmox fleet)\n";
} else {
    $check('active_worker_nodes', true, (string) $activeNodes);
}

if (class_exists(Capsule::class) && Capsule::schema()->hasTable('ms365_backup_runs')) {
    $recentSuccess = Capsule::table('ms365_backup_runs')
        ->where('status', 'success')
        ->where('engine_mode', 'kopia')
        ->where('manifest_id', '!=', '')
        ->whereNotNull('manifest_id')
        ->where('finished_at', '>=', time() - 86400 * 7)
        ->count();
    if ($recentSuccess < 1) {
        echo "[WARN] recent_kopia_success — no successful Kopia backup with manifest_id in last 7 days\n";
    } else {
        $check('recent_kopia_success', true, (string) $recentSuccess . ' in 7d');
    }
}

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
