<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Fleet\DeployService;
use Ms365Backup\Fleet\WorkerConfigService;
use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\Ms365WorkerApiAuth;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerLeaseService;
use Ms365Backup\WorkerNodeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

header('Content-Type: application/json');

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$body = Ms365WorkerApiAuth::jsonBody($request);
$nodeId = trim((string) ($body['node_id'] ?? $request->headers->get('X-MS365-Worker-Node', '')));
$load = (int) ($body['current_load'] ?? 0);
$version = trim((string) ($body['version'] ?? ''));
$deployError = trim((string) ($body['deploy_error'] ?? ''));
$claimAdmitRejects = max(0, (int) ($body['claim_admit_rejects'] ?? 0));
$proxmoxVmid = isset($body['proxmox_vmid']) ? (int) $body['proxmox_vmid'] : null;
$configVersion = max(0, (int) ($body['config_version'] ?? 0));
$configError = trim((string) ($body['config_error'] ?? ''));
$telemetry = is_array($body['telemetry'] ?? null) ? $body['telemetry'] : [];

if ($nodeId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'node_id required'], 400))->send();
    exit;
}

try {
    $effectiveLoad = WorkerClaimService::effectiveReportedLoad($nodeId, $load);
    WorkerNodeRepository::heartbeat($nodeId, $effectiveLoad, $version, $proxmoxVmid > 0 ? $proxmoxVmid : null, $claimAdmitRejects);
    if ($telemetry !== []) {
        WorkerNodeRepository::recordTelemetry($nodeId, $telemetry);
    }
    if ($configVersion > 0 || $configError !== '') {
        WorkerConfigService::reconcileFromHeartbeat($nodeId, $configVersion, $configError);
    }
    WorkerClaimService::failOrphanedRestoreRunsForNode($nodeId, $effectiveLoad, 180);
    // Release any batch claim whose children are all terminal before renewing
    // leases, so a finished-but-not-completed claim cannot keep renewing itself
    // and block new batches for the tenant.
    Ms365BatchClaimRepository::completeFinishedClaimsForNode($nodeId);
    $activeClaims = WorkerClaimService::activeClaimRunIds($nodeId);
    $batchClaimIds = Ms365BatchClaimRepository::activeBatchRunIdsForNode($nodeId);
    $activeClaims = array_values(array_unique(array_merge(
        $activeClaims,
        $batchClaimIds
    )));
    // #region agent log
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-e8409d.log', json_encode([
        'sessionId' => 'e8409d', 'runId' => 'post-fix', 'hypothesisId' => 'A',
        'location' => 'ms365_worker_heartbeat.php:60', 'message' => 'heartbeat batch renew decision',
        'data' => [
            'node' => substr($nodeId, 0, 8),
            'reported_load' => $load,
            'effective_load' => $effectiveLoad,
            'batch_claims' => array_map(static fn ($b) => substr((string) $b, 0, 8), $batchClaimIds),
            'will_renew_batch' => ($load > 0),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    // #endregion
    // Only renew the batch lease when the worker reports it is actually running a
    // batch (currentLoad() returns >=1 while a batch is active in-memory). A worker
    // that owns a batch in the DB but is NOT executing it (load 0 — e.g. it failed
    // to resume after a restart) must NOT keep its lease alive, otherwise the
    // batch heartbeat stays fresh forever and reapStaleBatches() never reclaims it,
    // stranding the tenant's children with zero progress while idle workers starve.
    if ($load > 0) {
        foreach ($batchClaimIds as $batchRunId) {
            WorkerLeaseService::renewForBatch($batchRunId, $nodeId);
        }
    }
    if ($effectiveLoad > 0) {
        WorkerLeaseService::renewForNode($nodeId);
    }

    $node = WorkerNodeRepository::get($nodeId);
    if ($node === null) {
        (new JsonResponse(['status' => 'error', 'message' => 'node not found'], 404))->send();
        exit;
    }

    if ($deployError !== '') {
        DeployService::markNodeDeployFailed($nodeId, $deployError);
    } elseif ($version !== '') {
        DeployService::reconcileNodeVersion($nodeId, $version);
        DeployService::reconcileActiveDeploy();
    }

    $response = ['status' => 'success'];
    $node = WorkerNodeRepository::get($nodeId);
    $data = [
        'active_claims' => $activeClaims,
    ];
    $shouldDrain = false;
    if ($node !== null) {
        $update = DeployService::updateInstructionForNode($node);
        if ($update !== null) {
            $data['update'] = $update;
            $shouldDrain = !empty($update['drain']);
        } elseif ((string) ($node['status'] ?? '') === 'draining') {
            // Admin-initiated drain with no pending binary update.
            $shouldDrain = true;
        }
        if (DeployService::nodeAwaitingDeploy($node)) {
            $data['awaiting_deploy'] = true;
        }
        $config = WorkerConfigService::configInstructionForNode($node);
        if ($config !== null) {
            $data['config'] = $config;
        }
    }
    if ($shouldDrain) {
        $data['drain'] = true;
    }
    if ($data !== []) {
        $response['data'] = $data;
    }

    (new JsonResponse($response))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
