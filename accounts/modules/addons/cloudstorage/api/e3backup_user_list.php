<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function userListFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

function getOnlineThresholdSeconds(): int
{
    try {
        $value = (int) Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'cloudbackup_agent_online_threshold_seconds')
            ->value('value');
        if ($value <= 0) {
            return 180;
        }
        return $value;
    } catch (\Throwable $e) {
        return 180;
    }
}

function emptyUserMetrics(): array
{
    return [
        'vaults_count' => 0,
        'jobs_count' => 0,
        'agents_count' => 0,
        'online_devices' => 0,
        'last_backup_at' => null,
    ];
}

function getScopedMetricsByUserId(int $clientId, array $userIds): array
{
    $metricsByUserId = [];
    $onlineThresholdSeconds = getOnlineThresholdSeconds();
    $userIds = array_values(array_unique(array_map('intval', $userIds)));

    if (empty($userIds)) {
        return $metricsByUserId;
    }

    $hasAgents = Capsule::schema()->hasTable('s3_cloudbackup_agents');
    $hasJobs = Capsule::schema()->hasTable('s3_cloudbackup_jobs');
    $hasRuns = Capsule::schema()->hasTable('s3_cloudbackup_runs');
    $hasAgentBackupUserId = $hasAgents && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');
    $hasJobBackupUserId = $hasJobs && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');

    if ($hasAgents && $hasAgentBackupUserId) {
        $agentQuery = Capsule::table('s3_cloudbackup_agents as a')
            ->where('a.client_id', $clientId)
            ->whereIn('a.backup_user_id', $userIds)
            ->select([
                'a.backup_user_id',
                Capsule::raw('COUNT(*) as agents_count'),
                Capsule::raw(
                    'SUM(CASE WHEN a.last_seen_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= ' .
                    (int) $onlineThresholdSeconds .
                    ' THEN 1 ELSE 0 END) as online_devices'
                ),
            ])
            ->groupBy('a.backup_user_id');

        $agentRows = $agentQuery->get();
        foreach ($agentRows as $row) {
            $userId = (int) ($row->backup_user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }
            if (!isset($metricsByUserId[$userId])) {
                $metricsByUserId[$userId] = emptyUserMetrics();
            }
            $metricsByUserId[$userId]['agents_count'] = (int) ($row->agents_count ?? 0);
            $metricsByUserId[$userId]['online_devices'] = (int) ($row->online_devices ?? 0);
        }
    }

    if ($hasJobs && $hasAgents) {
        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $jobCountExpr = $hasJobIdPk ? 'COUNT(j.job_id)' : 'COUNT(j.id)';
        $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')
            ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
            ->where('j.client_id', $clientId)
            ->where('j.status', '!=', 'deleted')
            ->select([
                Capsule::raw($hasJobBackupUserId && $hasAgentBackupUserId
                    ? 'COALESCE(j.backup_user_id, a.backup_user_id) as backup_user_id'
                    : ($hasJobBackupUserId ? 'j.backup_user_id as backup_user_id' : 'a.backup_user_id as backup_user_id')),
                Capsule::raw($jobCountExpr . ' as jobs_count'),
                Capsule::raw('COUNT(DISTINCT j.dest_bucket_id) as vaults_count'),
            ])
            ->groupBy('backup_user_id');

        if ($hasJobBackupUserId && $hasAgentBackupUserId) {
            $jobQuery->where(function ($scoped) use ($userIds) {
                $scoped->whereIn('j.backup_user_id', $userIds)
                    ->orWhere(function ($legacy) use ($userIds) {
                        $legacy->whereNull('j.backup_user_id')
                            ->whereIn('a.backup_user_id', $userIds);
                    });
            });
        } elseif ($hasJobBackupUserId) {
            $jobQuery->whereIn('j.backup_user_id', $userIds);
        } elseif ($hasAgentBackupUserId) {
            $jobQuery->whereIn('a.backup_user_id', $userIds);
        } else {
            $jobQuery = null;
        }

        if ($jobQuery !== null) {
            $jobRows = $jobQuery->get();
            foreach ($jobRows as $row) {
                $userId = (int) ($row->backup_user_id ?? 0);
                if ($userId <= 0) {
                    continue;
                }
                if (!isset($metricsByUserId[$userId])) {
                    $metricsByUserId[$userId] = emptyUserMetrics();
                }
                $metricsByUserId[$userId]['jobs_count'] = (int) ($row->jobs_count ?? 0);
                $metricsByUserId[$userId]['vaults_count'] = (int) ($row->vaults_count ?? 0);
            }
        }
    }

    if ($hasRuns && $hasJobs && $hasAgents) {
        $hasJobIdPkRuns = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $runJobJoin = $hasJobIdPkRuns ? 'j.job_id' : 'j.id';
        $lastBackupQuery = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', $runJobJoin)
            ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
            ->where('j.client_id', $clientId)
            ->whereNotNull('r.finished_at')
            ->whereIn('r.status', ['success', 'warning'])
            ->select([
                Capsule::raw($hasJobBackupUserId && $hasAgentBackupUserId
                    ? 'COALESCE(j.backup_user_id, a.backup_user_id) as backup_user_id'
                    : ($hasJobBackupUserId ? 'j.backup_user_id as backup_user_id' : 'a.backup_user_id as backup_user_id')),
                Capsule::raw('MAX(r.finished_at) as last_backup_at'),
            ])
            ->groupBy('backup_user_id');

        if ($hasJobBackupUserId && $hasAgentBackupUserId) {
            $lastBackupQuery->where(function ($scoped) use ($userIds) {
                $scoped->whereIn('j.backup_user_id', $userIds)
                    ->orWhere(function ($legacy) use ($userIds) {
                        $legacy->whereNull('j.backup_user_id')
                            ->whereIn('a.backup_user_id', $userIds);
                    });
            });
        } elseif ($hasJobBackupUserId) {
            $lastBackupQuery->whereIn('j.backup_user_id', $userIds);
        } elseif ($hasAgentBackupUserId) {
            $lastBackupQuery->whereIn('a.backup_user_id', $userIds);
        } else {
            $lastBackupQuery = null;
        }

        if ($lastBackupQuery !== null) {
            $lastBackupRows = $lastBackupQuery->get();
            foreach ($lastBackupRows as $row) {
                $userId = (int) ($row->backup_user_id ?? 0);
                if ($userId <= 0) {
                    continue;
                }
                if (!isset($metricsByUserId[$userId])) {
                    $metricsByUserId[$userId] = emptyUserMetrics();
                }
                $metricsByUserId[$userId]['last_backup_at'] = $row->last_backup_at ?? null;
            }
        }
    }

    return $metricsByUserId;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    userListFail('Session timeout', 200);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($clientId);
$tenantFilterRaw = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : null;

$tenantFilter = null;
$directOnly = false;

if ($tenantFilterRaw !== null && $tenantFilterRaw !== '') {
    if ($tenantFilterRaw === 'direct') {
        $directOnly = true;
    } else {
        $tenant = MspController::getTenantByPublicId($tenantFilterRaw, $clientId);
        if (!$tenant) {
            userListFail('Tenant not found', 404, ['tenant_id' => 'Tenant not found for this account']);
        }
        $tenantFilter = (int) $tenant->id;
    }
}

if (!$isMsp) {
    if ($tenantFilter !== null || $directOnly) {
        userListFail('Tenant filtering is only available for MSP accounts', 403);
    }
    $directOnly = true;
}

if ($tenantFilter !== null) {
    $tenant = MspController::getTenant($tenantFilter, $clientId);
    if (!$tenant) {
        userListFail('Tenant not found', 404, ['tenant_id' => 'Tenant not found for this account']);
    }
}

$tenantSelect = 'u.tenant_id';
if ($tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds()) {
    $tenantSelect = Capsule::raw('t.public_id as tenant_id');
}

$userQuery = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', function ($join) use ($clientId, $tenantTable, $mspId) {
        $join->on('u.tenant_id', '=', 't.id')
            ->where('t.status', '!=', 'deleted');
        if ($tenantTable === 'eb_tenants') {
            $join->where('t.msp_id', '=', (int)($mspId ?? 0));
        } else {
            $join->where('t.client_id', '=', (int)$clientId);
        }
    })
    ->where('u.client_id', $clientId)
    ->select(array_merge([
        'u.id',
        'u.client_id',
        'u.tenant_id as storage_tenant_id',
        $tenantSelect,
        'u.username',
        'u.email',
        'u.status',
        'u.created_at',
        'u.updated_at',
        't.name as tenant_name',
    ], Capsule::schema()->hasColumn('s3_backup_users', 'public_id') ? ['u.public_id'] : []));

if ($directOnly) {
    $userQuery->whereNull('u.tenant_id');
} elseif ($tenantFilter !== null) {
    $userQuery->where('u.tenant_id', $tenantFilter);
} elseif ($isMsp) {
    $userQuery->where(function ($q) {
        $q->whereNull('u.tenant_id')->orWhereNotNull('t.id');
    });
}

$users = $userQuery->orderBy('u.username')->get();
$userIds = [];
foreach ($users as $user) {
    $userIds[] = (int) $user->id;
}
$metricsByUserId = getScopedMetricsByUserId($clientId, $userIds);

$normalizedUsers = [];
foreach ($users as $user) {
    $scopeMetrics = $metricsByUserId[(int) $user->id] ?? emptyUserMetrics();

    $normalizedUsers[] = [
        'id' => (int) $user->id,
        'public_id' => (string) ($user->public_id ?? ''),
        'client_id' => (int) $user->client_id,
        'tenant_id' => $user->tenant_id !== null ? (string) $user->tenant_id : null,
        'username' => (string) $user->username,
        'email' => (string) $user->email,
        'status' => (string) $user->status,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'tenant_name' => $user->tenant_name ?? null,
        'vaults_count' => (int) $scopeMetrics['vaults_count'],
        'jobs_count' => (int) $scopeMetrics['jobs_count'],
        'agents_count' => (int) $scopeMetrics['agents_count'],
        'last_backup_at' => $scopeMetrics['last_backup_at'],
        'online_devices' => (int) $scopeMetrics['online_devices'],
        'metrics_mode' => 'derived_user',
    ];
}

(new JsonResponse([
    'status' => 'success',
    'users' => $normalizedUsers,
    'is_msp' => $isMsp,
    'online_threshold_seconds' => getOnlineThresholdSeconds(),
], 200))->send();
exit;

