<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

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

$agentUuid = $_POST['agent_uuid'] ?? null;
$revoke = isset($_POST['revoke']) ? (bool)$_POST['revoke'] : false;
if (!$agentUuid) {
    respond(['status' => 'fail', 'message' => 'agent_uuid is required'], 400);
}

$agent = Capsule::table('s3_cloudbackup_agents')->where('agent_uuid', $agentUuid)->first();
if (!$agent || (int)$agent->client_id !== (int)$clientId) {
    respond(['status' => 'fail', 'message' => 'Not found or unauthorized'], 403);
}

$updates = ['status' => 'disabled', 'updated_at' => Capsule::raw('NOW()')];
if ($revoke) {
    $updates['agent_token'] = bin2hex(random_bytes(20));
}

Capsule::table('s3_cloudbackup_agents')->where('agent_uuid', $agentUuid)->update($updates);

respond(['status' => 'success', 'agent_uuid' => $agentUuid, 'revoked' => $revoke]);

