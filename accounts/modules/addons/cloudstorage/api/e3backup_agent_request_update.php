<?php
/**
 * Client Area: request a remote update for a local backup agent.
 *
 * Owners (and MSP parents of the agent's tenant) can queue an update from the
 * "Manage Agent" drawer. Delegates the release lookup, version/online guards,
 * and command/update-job creation to AgentUpdateService.
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/AgentUpdateService.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\AgentUpdateService;

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

$agentUuid = trim((string) ($_POST['agent_uuid'] ?? ''));
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

$result = AgentUpdateService::requestUpdate($agent, 'client', (int) $clientId);
respond($result, 200);
