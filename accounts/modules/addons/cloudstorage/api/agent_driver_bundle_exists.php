<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\RecoveryMediaBundleService;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function getBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function requestParam(array $body, string $key, $default = null)
{
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $body)) {
        return $body[$key];
    }
    return $default;
}

function authenticateAgent(): object
{
    $agentId = $_SERVER['HTTP_X_AGENT_ID'] ?? null;
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? null;
    if (!$agentId || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->first();
    if (!$agent || (string) $agent->status !== 'active' || (string) $agent->agent_token !== (string) $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }
    Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);
    return $agent;
}

$body = getBodyJson();
$agent = authenticateAgent();

$runId = (int) requestParam($body, 'run_id', 0);
$profile = RecoveryMediaBundleService::normalizeProfile((string) requestParam($body, 'profile', 'essential'));
if ($runId <= 0) {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
}

$res = RecoveryMediaBundleService::bundleExistsForRunDestination((int) $agent->client_id, (int) $agent->id, $runId, $profile);
if (($res['status'] ?? 'fail') === 'unsupported') {
    respond([
        'status' => 'unsupported',
        'message' => (string) ($res['message'] ?? 'Destination not supported'),
        'exists' => false,
        'profile' => $profile,
    ], 200);
}
if (($res['status'] ?? 'fail') !== 'success') {
    respond(['status' => 'fail', 'message' => (string) ($res['message'] ?? 'Failed to check bundle')], 500);
}

respond([
    'status' => 'success',
    'run_id' => $runId,
    'profile' => $profile,
    'exists' => (bool) ($res['exists'] ?? false),
    'object_key' => (string) ($res['object_key'] ?? ''),
]);

