<?php

/**
 * Agent Poll Repo Operations API
 *
 * Allows agents to poll for eligible queued repo operations (retention, maintenance).
 * Authenticates agent, claims one eligible operation, acquires repo lock, returns payload.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPayloadBuilder;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPolicyService;

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

if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
    || !Capsule::schema()->hasTable('s3_kopia_repos')
    || !Capsule::schema()->hasTable('s3_kopia_repo_locks')) {
    respond(['status' => 'fail', 'message' => 'Repo operations not supported on this installation'], 200);
}

$agentClientId = (int) $agent->client_id;
$hasAgentTenant = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'tenant_id');
$agentTenantId = $hasAgentTenant && isset($agent->tenant_id) && $agent->tenant_id !== '' && $agent->tenant_id !== null
    ? (int) $agent->tenant_id
    : null;
$agentIdInt = (int) $agent->id;

try {
    $query = Capsule::table('s3_kopia_repo_operations as op')
        ->join('s3_kopia_repos as r', 'op.repo_id', '=', 'r.id')
        ->where('op.status', 'queued')
        ->where('r.client_id', $agentClientId)
        ->orderBy('op.created_at', 'asc')
        ->limit(1);

    $hasRepoTenant = Capsule::schema()->hasColumn('s3_kopia_repos', 'tenant_id');
    if ($hasRepoTenant) {
        if ($agentTenantId !== null) {
            $query->where('r.tenant_id', $agentTenantId);
        } else {
            $query->whereNull('r.tenant_id');
        }
    }

    $op = $query->select([
        'op.id as operation_id',
        'op.repo_id',
        'op.op_type',
        'op.operation_token',
        'r.vault_policy_version_id',
        'r.repository_id',
    ])->lockForUpdate()->first();

    if (!$op) {
        respond(['status' => 'success', 'operation' => null]);
    }

    $repoId = (int) $op->repo_id;
    $operationId = (int) $op->operation_id;
    $operationToken = (string) $op->operation_token;

    $acquired = KopiaRetentionLockService::acquire($repoId, $operationToken, $agentIdInt, 300);
    if (!$acquired) {
        respond(['status' => 'success', 'operation' => null]);
    }

    $affected = Capsule::table('s3_kopia_repo_operations')
        ->where('id', $operationId)
        ->where('status', 'queued')
        ->update([
            'status' => 'running',
            'claimed_by_agent_id' => $agentIdInt,
            'attempt_count' => Capsule::raw('attempt_count + 1'),
            'updated_at' => Capsule::raw('NOW()'),
        ]);

    if ((int) $affected === 0) {
        KopiaRetentionLockService::release($repoId, $operationToken);
        respond(['status' => 'success', 'operation' => null]);
    }

    $effectivePolicy = [
        'hourly' => 24,
        'daily' => 30,
        'weekly' => 8,
        'monthly' => 12,
        'yearly' => 3,
    ];
    if (Capsule::schema()->hasTable('s3_kopia_policy_versions') && !empty($op->vault_policy_version_id)) {
        $policyRow = Capsule::table('s3_kopia_policy_versions')
            ->where('id', (int) $op->vault_policy_version_id)
            ->first();
        if ($policyRow && !empty($policyRow->policy_json)) {
            $decoded = json_decode((string) $policyRow->policy_json, true);
            if (is_array($decoded)) {
                $effectivePolicy = KopiaRetentionPolicyService::resolveEffectivePolicy(null, $decoded, 'active');
            }
        }
    }

    $payload = KopiaRetentionPayloadBuilder::build($repoId, $operationId, $operationToken, $effectivePolicy);
    $payload['op_type'] = (string) $op->op_type;
    $payload['repository_id'] = (string) ($op->repository_id ?? '');

    respond([
        'status' => 'success',
        'operation' => $payload,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_poll_repo_operations', ['agent_id' => $agent->id], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Server error'], 500);
}
