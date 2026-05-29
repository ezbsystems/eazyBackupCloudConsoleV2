<?php
/**
 * Client Area: poll the status of an agent's most recent remote-update job.
 * Used by the Manage Agent drawer to show live download/verify/apply/success
 * progress after an update is requested.
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
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
$tenantTable = MspController::getTenantTableName();

$agentUuid = trim((string) ($_GET['agent_uuid'] ?? ($_POST['agent_uuid'] ?? '')));
if ($agentUuid === '') {
    respond(['status' => 'fail', 'message' => 'agent_uuid is required'], 400);
}

$agent = Capsule::table('s3_cloudbackup_agents')->where('agent_uuid', $agentUuid)->first();
if (!$agent) {
    respond(['status' => 'fail', 'message' => 'Agent not found'], 404);
}

// Authorization: direct owner OR MSP parent of the agent's tenant.
$authorized = false;
if ((int) $agent->client_id === (int) $clientId) {
    $authorized = true;
} elseif (MspController::isMspClient($clientId)) {
    $tenantId = $agent->tenant_id;
    if ($tenantId) {
        $tenantQuery = Capsule::table($tenantTable)->where('id', $tenantId)->where('status', '!=', 'deleted');
        if ($tenantTable === 'eb_tenants') {
            $mspId = MspController::getMspIdForClient((int) $clientId);
            $tenantQuery->where('msp_id', (int) ($mspId ?? 0));
        } else {
            $tenantQuery->where('client_id', (int) $clientId);
        }
        if ($tenantQuery->first()) {
            $authorized = true;
        }
    }
}

if (!$authorized) {
    respond(['status' => 'fail', 'message' => 'Unauthorized'], 403);
}

$job = null;
if (Capsule::schema()->hasTable('s3_agent_update_jobs')) {
    $job = Capsule::table('s3_agent_update_jobs')
        ->where('agent_uuid', $agentUuid)
        ->orderByDesc('id')
        ->first(['id', 'status', 'detail', 'from_version', 'target_version', 'created_at', 'updated_at', 'finished_at']);
}

respond([
    'status' => 'success',
    'agent_version' => $agent->agent_version ?? null,
    'update_job' => $job ? [
        'id' => (int) $job->id,
        'status' => $job->status,
        'detail' => $job->detail,
        'from_version' => $job->from_version,
        'target_version' => $job->target_version,
        'created_at' => $job->created_at,
        'updated_at' => $job->updated_at,
        'finished_at' => $job->finished_at,
    ] : null,
]);
