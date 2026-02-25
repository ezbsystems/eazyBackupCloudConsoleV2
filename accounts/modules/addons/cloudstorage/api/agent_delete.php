<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionSourceService;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond($data, $code = 200)
{
    (new JsonResponse($data, $code))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout'], 200);
}
$clientId = $ca->getUserID();

$agentId = $_POST['agent_id'] ?? null;
if (!$agentId) {
    respond(['status' => 'fail', 'message' => 'agent_id is required'], 400);
}

// Find the agent
$agent = Capsule::table('s3_cloudbackup_agents')->where('id', $agentId)->first();
if (!$agent) {
    respond(['status' => 'fail', 'message' => 'Agent not found'], 404);
}

// Authorization: must be the agent's owner OR an MSP parent
$authorized = false;

if ((int)$agent->client_id === (int)$clientId) {
    // Direct owner
    $authorized = true;
} elseif (MspController::isMspClient($clientId)) {
    // MSP: check if the agent belongs to one of their tenants
    $tenantId = $agent->tenant_id;
    if ($tenantId) {
        $tenant = Capsule::table('s3_backup_tenants')
            ->where('id', $tenantId)
            ->where('parent_client_id', $clientId)
            ->first();
        if ($tenant) {
            $authorized = true;
        }
    }
}

if (!$authorized) {
    respond(['status' => 'fail', 'message' => 'Unauthorized'], 403);
}

try {
    $jobIds = Capsule::table('s3_cloudbackup_jobs')
        ->where('agent_id', $agentId)
        ->where('source_type', 'local_agent')
        ->whereIn('engine', ['kopia', 'disk_image', 'hyperv'])
        ->pluck('id')
        ->toArray();
    foreach ($jobIds as $jid) {
        try {
            KopiaRetentionSourceService::ensureRepoSourceForJob((int) $jid);
        } catch (\Throwable $_) {
            logModuleCall('cloudstorage', 'agent_delete_ensure_source', ['job_id' => $jid], $_->getMessage());
        }
    }
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_delete_ensure_source', ['agent_id' => $agentId], $e->getMessage());
}

try {
    $repoIds = KopiaRetentionSourceService::retireByAgentId((int) $agentId);
    foreach (array_keys($repoIds) as $repoId) {
        KopiaRetentionOperationService::enqueue((int) $repoId, 'retention_apply', ['repo_id' => $repoId], 'agent-delete-' . $agentId . '-' . $repoId);
        KopiaRetentionOperationService::enqueue((int) $repoId, 'maintenance_quick', ['repo_id' => $repoId], 'agent-delete-' . $agentId . '-' . $repoId . '-maintenance');
    }
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_delete_retention_retire_error', ['agent_id' => $agentId], $e->getMessage());
}

// Delete the agent
Capsule::table('s3_cloudbackup_agents')->where('id', $agentId)->delete();

respond(['status' => 'success', 'message' => 'Agent deleted', 'agent_id' => $agentId]);
