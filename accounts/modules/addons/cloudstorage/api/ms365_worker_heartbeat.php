<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Fleet\DeployService;
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
$proxmoxVmid = isset($body['proxmox_vmid']) ? (int) $body['proxmox_vmid'] : null;

if ($nodeId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'node_id required'], 400))->send();
    exit;
}

try {
    WorkerNodeRepository::heartbeat($nodeId, $load, $version, $proxmoxVmid > 0 ? $proxmoxVmid : null);
    WorkerClaimService::releaseOrphanedClaimsForNode($nodeId, $load, 120);
    WorkerClaimService::failOrphanedRestoreRunsForNode($nodeId, $load, 180);
    WorkerLeaseService::renewForNode($nodeId);

    $node = WorkerNodeRepository::get($nodeId);
    if ($node === null) {
        (new JsonResponse(['status' => 'error', 'message' => 'node not found'], 404))->send();
        exit;
    }

    if ($deployError !== '') {
        DeployService::markNodeDeployFailed($nodeId, $deployError);
    } elseif ($version !== '') {
        DeployService::reconcileNodeVersion($nodeId, $version);
    }

    $response = ['status' => 'success'];
    $node = WorkerNodeRepository::get($nodeId);
    if ($node !== null) {
        $update = DeployService::updateInstructionForNode($node);
        if ($update !== null) {
            $response['data'] = ['update' => $update];
        }
    }

    (new JsonResponse($response))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
