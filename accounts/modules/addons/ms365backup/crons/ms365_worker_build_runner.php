<?php
declare(strict_types=1);

/**
 * MS365 worker build runner — processes one queued build job at a time.
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Fleet\BuildJobStore;
use Ms365Backup\Fleet\BuildRunner;
use Ms365Backup\Fleet\DeployService;
use Ms365Backup\Fleet\FleetSettings;

$cacheParent = FleetSettings::repoPath();
foreach ([
    'GOCACHE' => dirname($cacheParent) . '/.gocache',
    'GOMODCACHE' => dirname($cacheParent) . '/.gomodcache',
    'GOTMPDIR' => dirname($cacheParent) . '/.gotmp',
] as $envKey => $defaultPath) {
    if (getenv($envKey) === false || getenv($envKey) === '') {
        @mkdir($defaultPath, 0775, true);
        putenv($envKey . '=' . $defaultPath);
    }
}

$lockPath = FleetSettings::buildStorageRoot() . '/.runner.lock';
if (!is_dir(dirname($lockPath))) {
    @mkdir(dirname($lockPath), 0750, true);
}
$lockHandle = fopen($lockPath, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit(0);
}

try {
    $job = BuildJobStore::claimNextQueuedJob();
    if (!$job) {
        exit(0);
    }
    try {
        (new BuildRunner())->run($job);
        $flags = json_decode((string) ($job['flags_json'] ?? '{}'), true);
        if (is_array($flags) && !empty($flags['auto_deploy'])) {
            $updated = BuildJobStore::getJob((int) $job['id']);
            $releaseId = (int) ($updated['release_id'] ?? 0);
            if ($releaseId > 0) {
                DeployService::startDeploy(
                    $releaseId,
                    (string) ($flags['deploy_strategy'] ?? 'rolling'),
                    false,
                    null,
                    isset($job['created_by_admin_id']) ? (int) $job['created_by_admin_id'] : null
                );
            }
        }
    } catch (\Throwable $e) {
        BuildJobStore::updateJob((int) $job['id'], [
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'ended_at' => time(),
        ]);
        logActivity('MS365 worker build failed: ' . $e->getMessage());
    }
} finally {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}
