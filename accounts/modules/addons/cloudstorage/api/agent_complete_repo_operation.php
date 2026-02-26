<?php

/**
 * Agent Complete Repo Operation API
 *
 * Allows agents to report completion of a repo operation.
 * Authenticates agent, validates operation ownership/token, writes result, releases lock.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function authenticateAgent(): object
{
    $agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentUuid || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

$agent = authenticateAgent();

if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
    || !Capsule::schema()->hasTable('s3_kopia_repo_locks')) {
    respond(['status' => 'fail', 'message' => 'Repo operations not supported on this installation'], 200);
}

$operationId = isset($_POST['operation_id']) ? (int) $_POST['operation_id'] : 0;
$operationToken = isset($_POST['operation_token']) ? trim((string) $_POST['operation_token']) : '';
$status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
$resultJsonRaw = $_POST['result_json'] ?? null;

if ($operationId <= 0 || $operationToken === '') {
    respond(['status' => 'fail', 'message' => 'operation_id and operation_token required'], 200);
}

$allowedStatuses = ['success', 'failed'];
if (!in_array($status, $allowedStatuses, true)) {
    respond(['status' => 'fail', 'message' => 'status must be one of: ' . implode(', ', $allowedStatuses)], 200);
}

$resultJson = null;
if ($resultJsonRaw !== null && $resultJsonRaw !== '') {
    $decoded = json_decode(is_string($resultJsonRaw) ? $resultJsonRaw : json_encode($resultJsonRaw), true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $resultJson = $decoded;
    }
}

try {
    $op = Capsule::table('s3_kopia_repo_operations')
        ->where('id', $operationId)
        ->first();

    if (!$op) {
        respond(['status' => 'fail', 'message' => 'Operation not found'], 200);
    }

    if ((string) $op->operation_token !== $operationToken) {
        respond(['status' => 'fail', 'message' => 'Operation token mismatch'], 403);
    }

    if ((int) $op->claimed_by_agent_id !== (int) $agent->id) {
        respond(['status' => 'fail', 'message' => 'Operation not claimed by this agent'], 403);
    }

    if ((string) $op->status !== 'running') {
        respond(['status' => 'fail', 'message' => 'Operation is not in running state'], 200);
    }

    $repoId = (int) $op->repo_id;

    $update = [
        'status' => $status,
        'updated_at' => Capsule::raw('NOW()'),
    ];
    if ($resultJson !== null) {
        $update['result_json'] = json_encode($resultJson);
    }

    Capsule::table('s3_kopia_repo_operations')
        ->where('id', $operationId)
        ->update($update);

    KopiaRetentionLockService::release($repoId, $operationToken);

    respond(['status' => 'success']);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_complete_repo_operation', ['agent_id' => $agent->id, 'operation_id' => $operationId], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Server error'], 500);
}
