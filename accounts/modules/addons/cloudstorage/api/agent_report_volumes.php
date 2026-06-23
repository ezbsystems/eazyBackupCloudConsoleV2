<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\AgentAuth;

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
    return \WHMCS\Module\Addon\CloudStorage\Client\AgentAuth::authenticate(
        fn(array $data, int $code) => respond($data, $code)
    );
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
    ->where('agent_uuid', $agent->agent_uuid)
    ->update([
        'volumes_json' => json_encode($volumes, JSON_UNESCAPED_SLASHES),
        'volumes_updated_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

respond(['status' => 'success', 'count' => count($volumes)]);
exit;

