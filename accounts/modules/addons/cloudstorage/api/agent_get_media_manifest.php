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

function authenticateAgent(): object
{
    $agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? null;
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? null;
    if (!$agentUuid || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->first();
    if (!$agent || (string) $agent->status !== 'active' || (string) $agent->agent_token !== (string) $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }
    Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);
    return $agent;
}

$agent = authenticateAgent();
$body = getBodyJson();
$mode = RecoveryMediaBundleService::normalizeMode((string) ($body['mode'] ?? 'fast'));
$sourceAgentId = isset($body['source_agent_id']) ? (int) $body['source_agent_id'] : 0;
if ($sourceAgentId <= 0) {
    $sourceAgentId = (int) $agent->id;
}

// Prevent cross-client access.
$sourceAgent = Capsule::table('s3_cloudbackup_agents')
    ->where('id', $sourceAgentId)
    ->where('client_id', (int) $agent->client_id)
    ->first();
if (!$sourceAgent) {
    respond(['status' => 'fail', 'message' => 'Source agent not found'], 404);
}

try {
    $selection = RecoveryMediaBundleService::resolveMediaBuildSelection((int) $agent->client_id, $sourceAgentId, $mode);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_get_media_manifest', [
        'agent_uuid' => (string) $agent->agent_uuid,
        'source_agent_id' => $sourceAgentId,
        'mode' => $mode,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Failed to resolve media manifest'], 500);
}

respond([
    'status' => 'success',
    'manifest' => [
        'mode' => $selection['mode'],
        'source_agent_id' => $sourceAgentId,
        'source_agent_hostname' => (string) ($sourceAgent->hostname ?? ''),
        'base_iso_url' => $selection['base_iso_url'],
        'base_iso_sha256' => $selection['base_iso_sha256'],
        'source_bundle_url' => $selection['selected_bundle_url'],
        'source_bundle_sha256' => $selection['selected_bundle_sha256'],
        'source_bundle_profile' => $selection['selected_bundle_profile'],
        'broad_extras_url' => $selection['broad_extras_url'],
        'broad_extras_sha256' => $selection['broad_extras_sha256'],
        'has_source_bundle' => (bool) $selection['has_source_bundle'],
        'warning' => $selection['warning'],
    ],
]);

