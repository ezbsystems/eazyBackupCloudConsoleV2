<?php

/**
 * Agent Poll Repo Operations API
 *
 * Allows agents to poll for eligible queued repo operations (retention, maintenance).
 * Authenticates agent, claims one eligible operation, acquires repo lock, returns payload.
 */

if (!defined('WHMCS')) {
    require_once __DIR__ . '/../lib/Bootstrap/agent_bootstrap.php';
}
require_once __DIR__ . '/../lib/Client/AgentAuth.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPayloadBuilder;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPolicyService;
use WHMCS\Module\Addon\CloudStorage\Client\AgentAuth;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (!function_exists('respond')) {
    function respond(array $data, int $httpCode = 200): void
    {
        (new JsonResponse($data, $httpCode))->send();
        exit;
    }
}

if (!function_exists('authenticateAgent')) {
    function authenticateAgent(): object
    {
        return AgentAuth::authenticate(fn(array $data, int $code) => respond($data, $code));
    }
}

function cloudstorage_fetch_repo_operation(object $agent): array
{
    if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
        || !Capsule::schema()->hasTable('s3_kopia_repos')
        || !Capsule::schema()->hasTable('s3_kopia_repo_locks')) {
        return ['status' => 'success', 'operation' => null];
    }

    $agentClientId = (int) $agent->client_id;
$hasAgentTenant = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'tenant_id');
$agentTenantId = $hasAgentTenant && isset($agent->tenant_id) && $agent->tenant_id !== '' && $agent->tenant_id !== null
    ? (int) $agent->tenant_id
    : null;
$agentIdInt = (int) $agent->id;

$selectCols = [
    'op.id as operation_id',
    'op.repo_id',
    'op.op_type',
    'op.operation_token',
    'r.vault_policy_version_id',
    'r.repository_id',
    'r.bucket_id',
];
if (Capsule::schema()->hasColumn('s3_kopia_repos', 'root_prefix')) {
    $selectCols[] = 'r.root_prefix';
}

try {
    $result = Capsule::connection()->transaction(function () use (
        $agentClientId,
        $agentTenantId,
        $agentIdInt,
        $selectCols
    ) {
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

        $op = $query->select($selectCols)->lockForUpdate()->first();

        if (!$op) {
            return ['operation' => null];
        }

        $repoId = (int) $op->repo_id;
        $operationId = (int) $op->operation_id;
        $operationToken = (string) $op->operation_token;

        $acquired = KopiaRetentionLockService::acquire($repoId, $operationToken, $agentIdInt, 300);
        if (!$acquired) {
            return ['operation' => null];
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
            return ['operation' => null];
        }

        return ['operation' => $op];
    });

    if ($result['operation'] === null) {
        return ['status' => 'success', 'operation' => null];
    }

    $op = $result['operation'];
    $repoId = (int) $op->repo_id;
    $operationId = (int) $op->operation_id;
    $operationToken = (string) $op->operation_token;

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

    $settingsMap = [];
    if (Capsule::schema()->hasTable('tbladdonmodules')) {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');
        foreach ($settings as $k => $v) {
            $settingsMap[$k] = $v;
        }
    }

    // Destination context and credentials for agent
    $bucketId = (int) ($op->bucket_id ?? 0);
    $bucketName = '';
    $destAccessKey = '';
    $destSecretKey = '';
    if ($bucketId > 0 && Capsule::schema()->hasTable('s3_buckets')) {
        $bucketRow = Capsule::table('s3_buckets')->where('id', $bucketId)->first();
        $bucketName = (string) ($bucketRow->name ?? '');
        if ($bucketRow && !empty($bucketRow->user_id) && Capsule::schema()->hasTable('s3_user_access_keys')) {
            $keys = Capsule::table('s3_user_access_keys')
                ->where('user_id', (int) $bucketRow->user_id)
                ->orderByDesc('id')
                ->first();
            if ($keys && (!empty($keys->access_key) || !empty($keys->secret_key))) {
                $encKeyPrimary = $settingsMap['cloudbackup_encryption_key'] ?? '';
                $encKeySecondary = $settingsMap['encryption_key'] ?? '';
                $accessKeyRaw = (string) ($keys->access_key ?? '');
                $secretKeyRaw = (string) ($keys->secret_key ?? '');
                $decryptWith = function (?string $key) use ($accessKeyRaw, $secretKeyRaw) {
                    $ak = $accessKeyRaw;
                    $sk = $secretKeyRaw;
                    if ($key && $ak) {
                        $ak = HelperController::decryptKey($ak, $key);
                    }
                    if ($key && $sk) {
                        $sk = HelperController::decryptKey($sk, $key);
                    }
                    return [
                        is_string($ak) ? $ak : '',
                        is_string($sk) ? $sk : '',
                    ];
                };
                [$decAkPrimary, $decSkPrimary] = $decryptWith($encKeyPrimary);
                $destAccessKey = is_string($decAkPrimary) ? $decAkPrimary : '';
                $destSecretKey = is_string($decSkPrimary) ? $decSkPrimary : '';
                if ($destAccessKey === '' || $destSecretKey === '') {
                    [$decAkSecondary, $decSkSecondary] = $decryptWith($encKeySecondary);
                    if ($decAkSecondary !== '' && $decSkSecondary !== '') {
                        $destAccessKey = $decAkSecondary;
                        $destSecretKey = $decSkSecondary;
                    }
                }
            }
        }
    }
    $rootPrefix = '';
    if (Capsule::schema()->hasColumn('s3_kopia_repos', 'root_prefix') && isset($op->root_prefix)) {
        $rootPrefix = (string) $op->root_prefix;
    }
    $endpoint = $settingsMap['cloudbackup_agent_s3_endpoint'] ?? $settingsMap['s3_endpoint'] ?? '';
    $region = $settingsMap['cloudbackup_agent_s3_region'] ?? $settingsMap['s3_region'] ?? '';

    $payload = KopiaRetentionPayloadBuilder::build($repoId, $operationId, $operationToken, $effectivePolicy);
    $payload['op_type'] = (string) $op->op_type;
    $payload['repository_id'] = (string) ($op->repository_id ?? '');
    $payload['bucket_id'] = $bucketId;
    $payload['bucket_name'] = $bucketName;
    $payload['root_prefix'] = $rootPrefix;
    $payload['endpoint'] = (string) $endpoint;
    $payload['region'] = (string) $region;
    $payload['dest_access_key'] = $destAccessKey;
    $payload['dest_secret_key'] = $destSecretKey;

    return [
        'status' => 'success',
        'operation' => $payload,
    ];
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'agent_poll_repo_operations', ['agent_id' => $agent->id], $e->getMessage());
        return ['status' => 'fail', 'message' => 'Server error'];
    }
}

if (!defined('AGENT_POLL_FUNCTIONS_ONLY')) {
    $agent = authenticateAgent();
    $result = cloudstorage_fetch_repo_operation($agent);
    if (($result['status'] ?? '') === 'fail') {
        respond($result, 500);
    }
    respond($result);
}
