<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout'], 200);
}
$clientId = $ca->getUserID();

$agentId = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : 0;
if ($agentId <= 0) {
    respond(['status' => 'fail', 'message' => 'agent_id is required'], 400);
}

if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'volumes_json')) {
    respond(['status' => 'fail', 'message' => 'Volume caching not supported on this server'], 400);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('id', $agentId)
    ->where('client_id', $clientId)
    ->first(['id', 'client_id', 'volumes_json', 'volumes_updated_at']);

if (!$agent) {
    respond(['status' => 'fail', 'message' => 'Agent not found'], 404);
}

$volumes = [];
if (!empty($agent->volumes_json)) {
    $decoded = json_decode($agent->volumes_json, true);
    if (is_array($decoded)) {
        $volumes = $decoded;
    }
}

respond([
    'status' => 'success',
    'agent_id' => $agent->id,
    'updated_at' => $agent->volumes_updated_at,
    'volumes' => $volumes,
]);
exit;

