<?php
declare(strict_types=1);

/**
 * Autoscale MS365 Kopia worker fleet on Proxmox and recover stale leases.
 * Schedule: every 2–5 minutes via systemd timer or WHMCS cron.
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Fleet\DeployService;
use Ms365Backup\Fleet\FleetAlertService;
use Ms365Backup\Fleet\FleetSettings;
use Ms365Backup\Fleet\RetentionService;
use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\ProxmoxProvisioner;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerNodeRepository;

try {
    WorkerNodeRepository::markOfflineStale(FleetSettings::staleHeartbeatSeconds());
    $reconciled = DeployService::reconcileStuckDeployStatuses();
    $ghostLoads = WorkerClaimService::reconcileGhostNodeLoads();
    $batchesReaped = Ms365BatchClaimRepository::reapStaleBatches();
    $erroredQueued = WorkerClaimService::reconcileQueuedErroredRuns();
    $activeBatches = Ms365BatchRunRepository::reconcileActiveBatches();
    FleetAlertService::checkOfflineNodes();
    FleetAlertService::checkStaleRuns();
    $telemetryPruned = WorkerNodeRepository::pruneTelemetryHistory(48);
    $retentionPruned = RetentionService::prune();
    $result = ProxmoxProvisioner::autoscale();
    echo json_encode([
        'status' => 'ok',
        'result' => $result,
        'deploy_reconciled' => $reconciled,
        'ghost_loads_corrected' => $ghostLoads,
        'batches_reaped' => $batchesReaped,
        'errored_queued_failed' => $erroredQueued,
        'active_batches_reconciled' => $activeBatches,
        'telemetry_pruned' => $telemetryPruned,
        'retention_pruned' => $retentionPruned,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
