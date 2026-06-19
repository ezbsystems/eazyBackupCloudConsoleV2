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
use Ms365Backup\ProxmoxProvisioner;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerNodeRepository;

try {
    WorkerNodeRepository::markOfflineStale(FleetSettings::staleHeartbeatSeconds());
    $reconciled = DeployService::reconcileStuckDeployStatuses();
    WorkerClaimService::releaseExpiredLeases();
    WorkerClaimService::recoverStaleRunning();
    foreach (WorkerNodeRepository::activeNodes() as $node) {
        WorkerClaimService::releaseOrphanedClaimsForNode((string) $node['node_id'], (int) ($node['current_load'] ?? 0), 120);
    }
    $zombies = WorkerClaimService::reconcileZombieRuns(120);
    FleetAlertService::checkOfflineNodes();
    FleetAlertService::checkStaleRuns();
    $result = ProxmoxProvisioner::autoscale();
    echo json_encode([
        'status' => 'ok',
        'result' => $result,
        'deploy_reconciled' => $reconciled,
        'zombies_reconciled' => $zombies,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
