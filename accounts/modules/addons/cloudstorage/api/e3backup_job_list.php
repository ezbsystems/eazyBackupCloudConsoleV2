<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

function normalizeHypervJobSignatureValue($value): string
{
    if ($value === null) {
        return '';
    }
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded);
        }
        return $trimmed;
    }
    if (is_array($value) || is_object($value)) {
        return json_encode($value);
    }
    return trim((string) $value);
}

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$tenantFilterRaw = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : null;
$tenantFilter = null;
$agentFilter = isset($_GET['agent_uuid']) ? trim((string) $_GET['agent_uuid']) : null;
$userScopeIdRaw = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$userScopeId = 0;
$scopeStorageTenantId = null;
$scopeUserActive = false;
$hasAgentBackupUserId = Capsule::schema()->hasTable('s3_cloudbackup_agents')
    && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');

if ($userScopeIdRaw !== '' && $userScopeIdRaw !== '0') {
    $tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
    $scopeUserQuery = Capsule::table('s3_backup_users as u')
        ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
        ->where('u.client_id', $clientId);
    if ($hasPublicIdCol && !ctype_digit($userScopeIdRaw)) {
        $scopeUserQuery->where('u.public_id', $userScopeIdRaw);
    } else {
        $scopeUserQuery->where('u.id', (int) $userScopeIdRaw);
    }
    $scopeUser = $scopeUserQuery->select([
            'u.id',
            'u.tenant_id as storage_tenant_id',
            Capsule::raw($tenantOwnerSelect),
            't.status as tenant_status',
        ])
        ->first();

    if (!$scopeUser) {
        (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
        exit;
    }

    if (!$isMsp && !empty($scopeUser->storage_tenant_id)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
        exit;
    }

    if ($isMsp && !empty($scopeUser->storage_tenant_id)) {
        $tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int) (MspController::getMspIdForClient($clientId) ?? 0) : (int) $clientId;
        $tenantClientId = (int) ($scopeUser->tenant_owner_id ?? 0);
        $tenantStatus = strtolower((string) ($scopeUser->tenant_status ?? ''));
        if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
            (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
            exit;
        }
    }

    $userScopeId = (int) $scopeUser->id;
    $scopeStorageTenantId = $scopeUser->storage_tenant_id !== null ? (int) $scopeUser->storage_tenant_id : null;
    $scopeUserActive = true;
    $tenantFilterRaw = null;
    $tenantFilter = null;
}

if ($tenantFilterRaw !== null && $tenantFilterRaw !== '' && $tenantFilterRaw !== 'direct') {
    $tenant = MspController::getTenantByPublicId($tenantFilterRaw, $clientId);
    if (!$tenant) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Tenant not found'], 404))->send();
        exit;
    }
    $tenantFilter = (int) $tenant->id;
}

try {
    $hasJobTenantCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
    $hasJobRepositoryCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id');
    $hasJobBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
    $hasBackupUserPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
    $tenantColumn = $hasJobTenantCol ? 'j.tenant_id' : 'a.tenant_id';
    $tenantSelect = Capsule::raw($tenantColumn . ' as tenant_id');
    $tenantDeletedSelect = Capsule::raw('0 as tenant_deleted');
    if ($isMsp && $tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds()) {
        $tenantSelect = Capsule::raw('CASE WHEN t.id IS NULL THEN NULL ELSE t.public_id END as tenant_id');
        $tenantDeletedSelect = Capsule::raw('CASE WHEN ' . $tenantColumn . ' IS NOT NULL AND t.id IS NULL THEN 1 ELSE 0 END as tenant_deleted');
    }

    $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $jobIdSelect = $hasJobIdPk
        ? Capsule::raw('BIN_TO_UUID(j.job_id) as id')
        : 'j.id';

    $query = Capsule::table('s3_cloudbackup_jobs as j')
        ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted')
        ->select([
            $jobIdSelect,
            'j.name',
            'j.source_type',
            'j.source_display_name',
            'j.source_path',
            Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_paths_json') ? 'j.source_paths_json' : Capsule::raw('NULL as source_paths_json'),
            'j.engine',
            Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'hyperv_enabled') ? 'j.hyperv_enabled' : Capsule::raw('0 as hyperv_enabled'),
            Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'hyperv_config') ? 'j.hyperv_config' : Capsule::raw('NULL as hyperv_config'),
            'j.backup_mode',
            'j.schedule_type',
            'j.schedule_time',
            'j.schedule_weekday',
            'j.schedule_json',
            'j.status',
            'j.created_at',
            'j.updated_at',
            'j.dest_bucket_id',
            'j.dest_prefix',
            'j.encryption_enabled',
            $hasJobBackupUserId ? 'j.backup_user_id as job_backup_user_id' : Capsule::raw('NULL as job_backup_user_id'),
            $hasAgentBackupUserId ? 'a.backup_user_id as agent_backup_user_id' : Capsule::raw('NULL as agent_backup_user_id'),
            $hasJobRepositoryCol ? 'j.repository_id' : Capsule::raw('NULL as repository_id'),
            'a.agent_uuid',
            'a.hostname as agent_hostname',
            Capsule::raw($tenantColumn . ' as storage_tenant_id'),
            $tenantSelect,
            $tenantDeletedSelect,
            'a.tenant_id as agent_tenant_id',
        ]);

    if ($isMsp) {
        $query->leftJoin($tenantTable . ' as t', function ($join) use ($tenantColumn, $clientId, $tenantTable) {
            $join->on($tenantColumn, '=', 't.id');
            if ($tenantTable === 'eb_tenants') {
                $mspId = MspController::getMspIdForClient((int)$clientId);
                $join->where('t.msp_id', '=', (int)($mspId ?? 0));
            } else {
                $join->where('t.client_id', '=', (int)$clientId);
            }
        })->addSelect('t.name as tenant_name');

        if ($tenantFilterRaw !== null) {
            if ($tenantFilterRaw === 'direct') {
                $query->whereNull($tenantColumn);
            } elseif ($tenantFilter !== null) {
                $query->where($tenantColumn, $tenantFilter);
            }
        }
    }

    if ($agentFilter !== null && $agentFilter !== '') {
        $query->where('j.agent_uuid', $agentFilter);
    }

    if ($scopeUserActive) {
        if ($hasJobBackupUserId) {
            if ($hasAgentBackupUserId) {
                $query->where(function ($scoped) use ($userScopeId) {
                    $scoped->where('j.backup_user_id', $userScopeId)
                        ->orWhere(function ($legacy) use ($userScopeId) {
                            $legacy->whereNull('j.backup_user_id')
                                ->where('a.backup_user_id', $userScopeId);
                        });
                });
            } else {
                $query->where('j.backup_user_id', $userScopeId);
            }
        } else {
            if ($scopeStorageTenantId === null) {
                $query->whereNull('a.tenant_id');
            } else {
                $query->where('a.tenant_id', $scopeStorageTenantId);
            }
        }
    }

    $jobs = $query->orderByDesc('j.created_at')->get();

    $canonicalHypervBySignature = [];
    foreach ($jobs as $job) {
        $engine = strtolower(trim((string) ($job->engine ?? '')));
        if ($engine !== 'hyperv') {
            continue;
        }
        $signature = implode('|', [
            trim((string) ($job->agent_uuid ?? '')),
            trim((string) ($job->source_type ?? '')),
            trim((string) ($job->source_path ?? '')),
            normalizeHypervJobSignatureValue($job->source_paths_json ?? null),
            normalizeHypervJobSignatureValue($job->hyperv_config ?? null),
            (string) ($job->dest_bucket_id ?? ''),
            trim((string) ($job->dest_prefix ?? '')),
        ]);
        if (!isset($canonicalHypervBySignature[$signature])) {
            $canonicalHypervBySignature[$signature] = (string) ($job->id ?? '');
        }
    }

    if (!empty($canonicalHypervBySignature)) {
        $jobs = $jobs->filter(function ($job) use ($canonicalHypervBySignature) {
            $engine = strtolower(trim((string) ($job->engine ?? '')));
            $isHypervLike = $engine === 'hyperv'
                || (int) ($job->hyperv_enabled ?? 0) === 1
                || trim((string) ($job->hyperv_config ?? '')) !== '';
            if (!$isHypervLike || $engine === 'hyperv') {
                return true;
            }

            $signature = implode('|', [
                trim((string) ($job->agent_uuid ?? '')),
                trim((string) ($job->source_type ?? '')),
                trim((string) ($job->source_path ?? '')),
                normalizeHypervJobSignatureValue($job->source_paths_json ?? null),
                normalizeHypervJobSignatureValue($job->hyperv_config ?? null),
                (string) ($job->dest_bucket_id ?? ''),
                trim((string) ($job->dest_prefix ?? '')),
            ]);

            return !isset($canonicalHypervBySignature[$signature]);
        })->values();
    }

    $backupUserRouteById = [];
    $backupUserIds = [];
    foreach ($jobs as $job) {
        $effectiveBackupUserId = (int) ($job->job_backup_user_id ?? 0);
        if ($effectiveBackupUserId <= 0) {
            $effectiveBackupUserId = (int) ($job->agent_backup_user_id ?? 0);
        }
        if ($effectiveBackupUserId > 0) {
            $backupUserIds[] = $effectiveBackupUserId;
        }
    }

    $backupUserIds = array_values(array_unique($backupUserIds));
    if (!empty($backupUserIds)) {
        $backupUserSelect = ['id'];
        if ($hasBackupUserPublicId) {
            $backupUserSelect[] = 'public_id';
        }
        $backupUsers = Capsule::table('s3_backup_users')
            ->whereIn('id', $backupUserIds)
            ->get($backupUserSelect);
        foreach ($backupUsers as $backupUser) {
            $routeId = $hasBackupUserPublicId
                ? trim((string) ($backupUser->public_id ?? ''))
                : '';
            if ($routeId === '') {
                $routeId = (string) ((int) ($backupUser->id ?? 0));
            }
            $backupUserRouteById[(int) $backupUser->id] = $routeId;
        }
    }

    // Attach destination bucket names
    $bucketIds = $jobs->pluck('dest_bucket_id')->filter()->unique()->values()->toArray();
    $bucketNameById = [];
    if (!empty($bucketIds)) {
        $bucketRows = Capsule::table('s3_buckets')
            ->whereIn('id', $bucketIds)
            ->get(['id', 'name']);
        foreach ($bucketRows as $b) {
            $bucketNameById[(int) $b->id] = $b->name;
            $bucketNameById[(string) $b->id] = $b->name;
        }
    }

    $jobIds = $jobs->pluck('id')->filter()->toArray();
    $lastRunByJob = [];
    if (!empty($jobIds)) {
        if ($hasJobIdPk) {
            $binExprs = array_map(function ($uuid) {
                return UuidBinary::toDbExpr(UuidBinary::normalize($uuid));
            }, $jobIds);
            $runs = Capsule::table('s3_cloudbackup_runs')
                ->selectRaw('BIN_TO_UUID(job_id) as job_id_str, status, started_at, finished_at, bytes_transferred')
                ->whereRaw('job_id IN (' . implode(',', $binExprs) . ')')
                ->orderBy('started_at', 'desc')
                ->get();
            foreach ($runs as $r) {
                $jid = $r->job_id_str;
                if (!isset($lastRunByJob[$jid])) {
                    $lastRunByJob[$jid] = [
                        'status' => $r->status,
                        'started_at' => $r->started_at,
                        'finished_at' => $r->finished_at,
                        'bytes_transferred' => $r->bytes_transferred ?? 0,
                    ];
                }
            }
        } else {
            $runs = Capsule::table('s3_cloudbackup_runs')
                ->whereIn('job_id', $jobIds)
                ->orderBy('started_at', 'desc')
                ->get(['job_id', 'status', 'started_at', 'finished_at', 'bytes_transferred']);
            foreach ($runs as $r) {
                $jid = (int) $r->job_id;
                if (!isset($lastRunByJob[$jid])) {
                    $lastRunByJob[$jid] = [
                        'status' => $r->status,
                        'started_at' => $r->started_at,
                        'finished_at' => $r->finished_at,
                        'bytes_transferred' => $r->bytes_transferred ?? 0,
                    ];
                }
            }
        }
    }

    foreach ($jobs as $job) {
        $bucketId = $job->dest_bucket_id ?? null;
        if ($bucketId !== null && isset($bucketNameById[$bucketId])) {
            $job->dest_bucket_name = $bucketNameById[$bucketId];
        }
        $jobId = $hasJobIdPk ? ($job->id ?? '') : (int) ($job->id ?? 0);
        $job->last_run = $lastRunByJob[$jobId] ?? null;
        $job->tenant_deleted = (bool) ($job->tenant_deleted ?? false);
        if ($job->tenant_deleted && (!isset($job->tenant_name) || trim((string) $job->tenant_name) === '')) {
            $job->tenant_name = 'Deleted tenant';
        }
        $effectiveBackupUserId = (int) ($job->job_backup_user_id ?? 0);
        if ($effectiveBackupUserId <= 0) {
            $effectiveBackupUserId = (int) ($job->agent_backup_user_id ?? 0);
        }
        $job->backup_user_route_id = $effectiveBackupUserId > 0
            ? ($backupUserRouteById[$effectiveBackupUserId] ?? null)
            : null;
        unset($job->storage_tenant_id, $job->agent_tenant_id, $job->job_backup_user_id, $job->agent_backup_user_id, $job->source_paths_json, $job->hyperv_enabled, $job->hyperv_config);
    }

    (new JsonResponse(['status' => 'success', 'jobs' => $jobs], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load jobs'], 500))->send();
}
exit;
