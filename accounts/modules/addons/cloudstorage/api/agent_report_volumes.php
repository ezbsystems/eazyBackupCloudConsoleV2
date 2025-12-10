<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function authenticateAgent(): object
{
    $agentId = $_SERVER['HTTP_X_AGENT_ID'] ?? ($_POST['agent_id'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentId || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

$agent = authenticateAgent();

if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'volumes_json')) {
    respond(['status' => 'fail', 'message' => 'Volume caching not supported on this server'], 400);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$volumes = [];
if (is_array($payload) && isset($payload['volumes']) && is_array($payload['volumes'])) {
    $volumes = $payload['volumes'];
}

// Limit payload size defensively
if (count($volumes) > 200) {
    $volumes = array_slice($volumes, 0, 200);
}

Capsule::table('s3_cloudbackup_agents')
    ->where('id', $agent->id)
    ->update([
        'volumes_json' => json_encode($volumes, JSON_UNESCAPED_SLASHES),
        'volumes_updated_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

respond(['status' => 'success', 'count' => count($volumes)]);
exit;

