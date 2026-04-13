<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function userGetFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

function getOnlineThresholdForUserDetail(): int
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

function applyTenantScope($query, string $column, $tenantId): void
{
    if ($tenantId === null) {
        $query->whereNull($column);
    } else {
        $query->where($column, (int) $tenantId);
    }
}

function e3backup_user_detail_schedule_label(?string $type): string
{
    $t = strtolower((string) $type);
    $map = [
        'manual' => 'Manual',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'interval' => 'Interval',
        'cron' => 'Cron',
    ];

    return $map[$t] ?? ($type !== null && trim((string) $type) !== '' ? ucfirst($t) : '—');
}

function e3backup_user_detail_short_uuid(?string $uuid): string
{
    if ($uuid === null || $uuid === '') {
        return '—';
    }
    $uuid = strtolower(trim((string) $uuid));
    if (strlen($uuid) <= 12) {
        return $uuid;
    }

    return substr($uuid, 0, 8) . '…';
}

function e3backup_user_detail_job_mode_label($engine, $backupMode): string
{
    $e = strtolower((string) $engine);
    $m = strtolower((string) $backupMode);
    if ($m !== '') {
        return ucfirst($m);
    }
    if ($e !== '') {
        return ucwords(str_replace('_', ' ', $e));
    }

    return '—';
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    userGetFail('Session timeout', 200);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($clientId);
$tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int)($mspId ?? 0) : (int)$clientId;
$tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
$tenantSelect = 'u.tenant_id';
if ($tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds()) {
    $tenantSelect = Capsule::raw('t.public_id as tenant_id');
}
$userIdRaw = trim((string) ($_GET['user_id'] ?? ''));
$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');

if ($userIdRaw === '') {
    userGetFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

$userSelectCols = [
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
    Capsule::raw($tenantOwnerSelect),
    't.status as tenant_status',
];
if (Capsule::schema()->hasColumn('s3_backup_users', 'backup_type')) {
    $userSelectCols[] = 'u.backup_type';
}
if ($hasPublicIdCol) {
    $userSelectCols[] = 'u.public_id';
}
$userLookup = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.client_id', $clientId);
if ($hasPublicIdCol && !ctype_digit($userIdRaw)) {
    $userLookup->where('u.public_id', $userIdRaw);
} else {
    $userLookup->where('u.id', (int) $userIdRaw);
}
$user = $userLookup->select($userSelectCols)->first();

if (!$user) {
    userGetFail('User not found.', 404);
}
$userId = (int) $user->id;

if (!$isMsp && !empty($user->storage_tenant_id)) {
    userGetFail('User not found.', 404);
}

if ($isMsp && !empty($user->storage_tenant_id)) {
    $tenantClientId = (int) ($user->tenant_owner_id ?? 0);
    $tenantStatus = strtolower((string) ($user->tenant_status ?? ''));
    if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
        userGetFail('User not found.', 404);
    }
}

$storageTenantId = $user->storage_tenant_id !== null ? (int) $user->storage_tenant_id : null;
$tenantPublicId = $user->tenant_id !== null ? (string) $user->tenant_id : null;
$canonicalTenantId = null;
$canonicalTenantPublicId = null;
$canonicalTenantName = null;
$isCanonicalManaged = false;
$storageIdentifier = eb_tenant_storage_identifier_for_user((int) $user->id);
if ($isMsp) {
    $canonicalLink = eb_tenant_storage_links_get_current_link_for_identifier((int) $clientId, $storageIdentifier);
    if ($canonicalLink && !empty($canonicalLink->tenant_id)) {
        $isCanonicalManaged = true;
        $canonicalTenantId = (int) $canonicalLink->tenant_id;
        $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client((int) $clientId, $canonicalTenantId);
        if ($canonicalTenant) {
            $canonicalTenantPublicId = trim((string) ($canonicalTenant->public_id ?? ''));
            $canonicalTenantName = trim((string) ($canonicalTenant->subdomain ?? ''));
            if ($canonicalTenantName === '') {
                $canonicalTenantName = trim((string) ($canonicalTenant->fqdn ?? ''));
            }
            if ($canonicalTenantName === '') {
                $canonicalTenantName = $canonicalTenantPublicId !== '' ? ('Tenant ' . $canonicalTenantPublicId) : 'Tenant';
            }
        } else {
            $canonicalTenantId = null;
            $canonicalTenantPublicId = null;
        }
    }
}
$onlineThresholdSeconds = getOnlineThresholdForUserDetail();
$metrics = [
    'vaults_count' => 0,
    'jobs_count' => 0,
    'agents_count' => 0,
    'online_devices' => 0,
    'last_backup_at' => null,
];

$hasAgentBackupUserId = Capsule::schema()->hasTable('s3_cloudbackup_agents')
    && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');

if (Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
    $agentQuery = Capsule::table('s3_cloudbackup_agents as a')
        ->where('a.client_id', $clientId);
    if ($hasAgentBackupUserId) {
        $agentQuery->where('a.backup_user_id', $userId);
    } else {
        applyTenantScope($agentQuery, 'a.tenant_id', $storageTenantId);
    }
    $agentStats = $agentQuery->select([
        Capsule::raw('COUNT(*) as agents_count'),
        Capsule::raw(
            'SUM(CASE WHEN a.last_seen_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= ' .
            (int) $onlineThresholdSeconds .
            ' THEN 1 ELSE 0 END) as online_devices'
        ),
    ])->first();

    if ($agentStats) {
        $metrics['agents_count'] = (int) ($agentStats->agents_count ?? 0);
        $metrics['online_devices'] = (int) ($agentStats->online_devices ?? 0);
    }
}

if (Capsule::schema()->hasTable('s3_cloudbackup_jobs') && Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
    $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $jobCountExpr = $hasJobIdPk ? 'COUNT(j.job_id)' : 'COUNT(j.id)';
    $hasAgentUuid = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_uuid');
    $hasBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
    $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')
        ->leftJoin('s3_cloudbackup_agents as a', $hasAgentUuid ? 'j.agent_uuid' : 'j.agent_id', '=', $hasAgentUuid ? 'a.agent_uuid' : 'a.id')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted');
    if ($hasBackupUserId) {
        if ($hasAgentBackupUserId) {
            $jobQuery->where(function ($scoped) use ($userId) {
                $scoped->where('j.backup_user_id', $userId)
                    ->orWhere(function ($legacy) use ($userId) {
                        $legacy->whereNull('j.backup_user_id')
                            ->where('a.backup_user_id', $userId);
                    });
            });
        } else {
            $jobQuery->where('j.backup_user_id', $userId);
        }
    } else {
        applyTenantScope($jobQuery, 'a.tenant_id', $storageTenantId);
    }
    $jobStats = $jobQuery->select([
        Capsule::raw($jobCountExpr . ' as jobs_count'),
        Capsule::raw('COUNT(DISTINCT j.dest_bucket_id) as vaults_count'),
    ])->first();

    if ($jobStats) {
        $metrics['jobs_count'] = (int) ($jobStats->jobs_count ?? 0);
        $metrics['vaults_count'] = (int) ($jobStats->vaults_count ?? 0);
    }
}

if (
    Capsule::schema()->hasTable('s3_cloudbackup_runs') &&
    Capsule::schema()->hasTable('s3_cloudbackup_jobs') &&
    Capsule::schema()->hasTable('s3_cloudbackup_agents')
) {
    $hasAgentUuidRuns = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_uuid');
    $hasJobIdPkRuns = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $runJobJoin = $hasJobIdPkRuns ? 'j.job_id' : 'j.id';
    $hasBackupUserIdRuns = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
    $lastBackupQuery = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', $runJobJoin)
        ->leftJoin('s3_cloudbackup_agents as a', $hasAgentUuidRuns ? 'j.agent_uuid' : 'j.agent_id', '=', $hasAgentUuidRuns ? 'a.agent_uuid' : 'a.id')
        ->where('j.client_id', $clientId)
        ->whereNotNull('r.finished_at')
        ->whereIn('r.status', ['success', 'warning']);
    if ($hasBackupUserIdRuns) {
        if ($hasAgentBackupUserId) {
            $lastBackupQuery->where(function ($scoped) use ($userId) {
                $scoped->where('j.backup_user_id', $userId)
                    ->orWhere(function ($legacy) use ($userId) {
                        $legacy->whereNull('j.backup_user_id')
                            ->where('a.backup_user_id', $userId);
                    });
            });
        } else {
            $lastBackupQuery->where('j.backup_user_id', $userId);
        }
    } else {
        applyTenantScope($lastBackupQuery, 'a.tenant_id', $storageTenantId);
    }
    $lastBackup = $lastBackupQuery->max('r.finished_at');
    if (!empty($lastBackup)) {
        $metrics['last_backup_at'] = $lastBackup;
    }
}

$agentsDetail = [];
$vaultsDetail = [];
$hypervJobs = [];
$hypervVms = [];
$billingKpis = [
    'agents' => (int) $metrics['agents_count'],
    'storage_display' => '—',
    'disk_image_jobs' => 0,
    'hyperv_guests' => 0,
    'proxmox_guests' => 0,
];

$cloudJobsOk = Capsule::schema()->hasTable('s3_cloudbackup_jobs')
    && Capsule::schema()->hasTable('s3_cloudbackup_agents')
    && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_uuid');

if ($cloudJobsOk) {
    $hasJobIdPkDetail = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $hasBackupUserIdDetail = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
    $hasHypervEnabledDetail = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'hyperv_enabled');
    $hasHypervConfigDetail = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'hyperv_config');

    $jobRowQuery = Capsule::table('s3_cloudbackup_jobs as j')
        ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted');
    if ($hasBackupUserIdDetail) {
        if ($hasAgentBackupUserId) {
            $jobRowQuery->where(function ($scoped) use ($userId) {
                $scoped->where('j.backup_user_id', $userId)
                    ->orWhere(function ($legacy) use ($userId) {
                        $legacy->whereNull('j.backup_user_id')
                            ->where('a.backup_user_id', $userId);
                    });
            });
        } else {
            $jobRowQuery->where('j.backup_user_id', $userId);
        }
    } else {
        applyTenantScope($jobRowQuery, 'a.tenant_id', $storageTenantId);
    }

    $jobIdSelect = $hasJobIdPkDetail
        ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id')
        : Capsule::raw('CAST(j.id AS CHAR) as job_id');

    $jobCollection = $jobRowQuery->select([
        $jobIdSelect,
        'j.name',
        'j.engine',
        'j.backup_mode',
        'j.schedule_type',
        'j.status as job_status',
        'j.dest_bucket_id',
        'j.dest_prefix',
        $hasHypervEnabledDetail ? 'j.hyperv_enabled' : Capsule::raw('0 as hyperv_enabled'),
        $hasHypervConfigDetail ? 'j.hyperv_config' : Capsule::raw('NULL as hyperv_config'),
        'a.agent_uuid',
        'a.hostname as agent_hostname',
    ])->orderBy('j.name')->get();

    foreach ($jobCollection as $jr) {
        $eng = strtolower((string) ($jr->engine ?? ''));
        $isHypervJob = $eng === 'hyperv'
            || (int) ($jr->hyperv_enabled ?? 0) === 1
            || trim((string) ($jr->hyperv_config ?? '')) !== '';
        if ($eng === 'disk_image') {
            $billingKpis['disk_image_jobs']++;
        }
        if ($isHypervJob) {
            $jobIdValue = (string) ($jr->job_id ?? '');
            $hypervJobs[] = [
                'job_id' => $jobIdValue,
                'name' => (string) ($jr->name ?? ''),
                'status' => (string) ($jr->job_status ?? 'active'),
                'agent_uuid' => (string) ($jr->agent_uuid ?? ''),
                'agent_hostname' => (string) ($jr->agent_hostname ?? ''),
            ];
        }
    }

    if ($hasJobIdPkDetail && Capsule::schema()->hasTable('s3_hyperv_vms')) {
        $hypervJobsByNormalizedId = [];
        foreach ($hypervJobs as $jobMeta) {
            $jid = (string) ($jobMeta['job_id'] ?? '');
            if (!UuidBinary::isUuid($jid)) {
                continue;
            }
            $hypervJobsByNormalizedId[UuidBinary::normalize($jid)] = $jobMeta;
        }
        if (!empty($hypervJobsByNormalizedId)) {
            $inList = [];
            foreach (array_keys($hypervJobsByNormalizedId) as $norm) {
                $inList[] = UuidBinary::toDbExpr($norm);
            }
            $billingKpis['hyperv_guests'] = (int) Capsule::table('s3_hyperv_vms')
                ->whereRaw('job_id IN (' . implode(',', $inList) . ')')
                ->count();

            $vmRows = Capsule::table('s3_hyperv_vms')
                ->whereRaw('job_id IN (' . implode(',', $inList) . ')')
                ->orderBy('vm_name', 'asc')
                ->get([
                    'id',
                    Capsule::raw('BIN_TO_UUID(job_id) as job_id'),
                    'vm_name',
                    'vm_guid',
                    'generation',
                    'is_linux',
                    'rct_enabled',
                    'backup_enabled',
                ]);

            $vmIds = [];
            foreach ($vmRows as $vmRow) {
                $vmIds[] = (int) $vmRow->id;
            }

            $diskCountsByVm = [];
            if (!empty($vmIds) && Capsule::schema()->hasTable('s3_hyperv_vm_disks')) {
                $diskCountRows = Capsule::table('s3_hyperv_vm_disks')
                    ->whereIn('vm_id', $vmIds)
                    ->groupBy('vm_id')
                    ->get([
                        'vm_id',
                        Capsule::raw('COUNT(*) as disk_count'),
                    ]);
                foreach ($diskCountRows as $diskCountRow) {
                    $diskCountsByVm[(int) $diskCountRow->vm_id] = (int) ($diskCountRow->disk_count ?? 0);
                }
            }

            $latestBackupByVm = [];
            if (!empty($vmIds) && Capsule::schema()->hasTable('s3_hyperv_backup_points')) {
                $latestBackupRows = Capsule::table('s3_hyperv_backup_points')
                    ->whereIn('vm_id', $vmIds)
                    ->orderBy('vm_id', 'asc')
                    ->orderBy('created_at', 'desc')
                    ->get([
                        'vm_id',
                        'backup_type',
                        'created_at',
                        'consistency_level',
                    ]);
                foreach ($latestBackupRows as $latestBackupRow) {
                    $vmId = (int) ($latestBackupRow->vm_id ?? 0);
                    if ($vmId <= 0 || isset($latestBackupByVm[$vmId])) {
                        continue;
                    }
                    $latestBackupByVm[$vmId] = [
                        'type' => (string) ($latestBackupRow->backup_type ?? ''),
                        'created_at' => $latestBackupRow->created_at,
                        'consistency_level' => (string) ($latestBackupRow->consistency_level ?? 'Application'),
                    ];
                }
            }

            foreach ($vmRows as $vmRow) {
                $jobIdValue = (string) ($vmRow->job_id ?? '');
                $jobMeta = UuidBinary::isUuid($jobIdValue)
                    ? ($hypervJobsByNormalizedId[UuidBinary::normalize($jobIdValue)] ?? null)
                    : null;
                $vmId = (int) ($vmRow->id ?? 0);
                $hypervVms[] = [
                    'id' => $vmId,
                    'job_id' => $jobIdValue,
                    'job_name' => (string) (($jobMeta['name'] ?? '') ?: 'Hyper-V Job'),
                    'agent_uuid' => (string) ($jobMeta['agent_uuid'] ?? ''),
                    'agent_hostname' => (string) ($jobMeta['agent_hostname'] ?? ''),
                    'vm_name' => (string) ($vmRow->vm_name ?? ''),
                    'vm_guid' => (string) ($vmRow->vm_guid ?? ''),
                    'generation' => (int) ($vmRow->generation ?? 0),
                    'is_linux' => (bool) ($vmRow->is_linux ?? false),
                    'rct_enabled' => (bool) ($vmRow->rct_enabled ?? false),
                    'backup_enabled' => (bool) ($vmRow->backup_enabled ?? false),
                    'disk_count' => (int) ($diskCountsByVm[$vmId] ?? 0),
                    'last_backup' => $latestBackupByVm[$vmId] ?? null,
                ];
            }
        }
    }

    $bucketIds = [];
    foreach ($jobCollection as $jr) {
        $bid = $jr->dest_bucket_id ?? null;
        if ($bid !== null && $bid !== '') {
            $bucketIds[(int) $bid] = true;
        }
    }
    $bucketNameById = [];
    if (!empty($bucketIds)) {
        $ids = array_keys($bucketIds);
        $bucketRows = Capsule::table('s3_buckets')->whereIn('id', $ids)->get(['id', 'name', 'created_at']);
        foreach ($bucketRows as $b) {
            $bucketNameById[(int) $b->id] = [
                'name' => (string) ($b->name ?? ''),
                'created_at' => $b->created_at ?? null,
            ];
        }
    }

    $jobsUsingBucket = [];
    foreach ($jobCollection as $jr) {
        $bid = $jr->dest_bucket_id ?? null;
        if ($bid !== null && $bid !== '') {
            $ik = (int) $bid;
            $jobsUsingBucket[$ik] = ($jobsUsingBucket[$ik] ?? 0) + 1;
        }
    }

    foreach (array_keys($bucketIds) as $bid) {
        $meta = $bucketNameById[$bid] ?? ['name' => 'Vault ' . $bid, 'created_at' => null];
        $path = '';
        foreach ($jobCollection as $jr) {
            if ((int) ($jr->dest_bucket_id ?? 0) !== (int) $bid) {
                continue;
            }
            $p = trim((string) ($jr->dest_prefix ?? ''));
            if ($p !== '') {
                $path = '/' . ltrim($p, '/');
                break;
            }
        }
        $createdOut = null;
        if (!empty($meta['created_at'])) {
            try {
                $createdOut = (new \DateTimeImmutable((string) $meta['created_at']))->format('Y-m-d');
            } catch (\Throwable $e) {
                $createdOut = null;
            }
        }
        $vaultsDetail[] = [
            'id' => $bid,
            'name' => $meta['name'],
            'provider_label' => 'eazyBackup Cloud',
            'bucket_path' => $path !== '' ? $path : '—',
            'storage_used_display' => '—',
            'created' => $createdOut,
            'jobs_using' => (int) ($jobsUsingBucket[$bid] ?? 0),
        ];
    }

    $jobsByAgent = [];
    foreach ($jobCollection as $jr) {
        $bId = $jr->dest_bucket_id ?? null;
        $bName = '—';
        if ($bId !== null && $bId !== '' && isset($bucketNameById[(int) $bId])) {
            $bName = $bucketNameById[(int) $bId]['name'];
        }
        $au = (string) ($jr->agent_uuid ?? '');
        if ($au === '') {
            $au = '_unassigned';
        }
        if (!isset($jobsByAgent[$au])) {
            $jobsByAgent[$au] = [];
        }
        $jobsByAgent[$au][] = (object) array_merge(
            (array) $jr,
            ['dest_bucket_name' => $bName]
        );
    }

    if (Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
        $aq = Capsule::table('s3_cloudbackup_agents as a')
            ->where('a.client_id', $clientId);
        if ($hasAgentBackupUserId) {
            $aq->where('a.backup_user_id', $userId);
        } else {
            applyTenantScope($aq, 'a.tenant_id', $storageTenantId);
        }
        $agentList = $aq->orderBy('a.hostname')->get([
            'a.agent_uuid',
            'a.hostname',
            'a.agent_type',
            'a.status',
            'a.last_seen_at',
            Capsule::raw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) as seconds_since_seen'),
        ]);

        foreach ($agentList as $ar) {
            $auKey = (string) $ar->agent_uuid;
            $jobRowsForAgent = $jobsByAgent[$auKey] ?? [];
            $online = false;
            $offlineDays = null;
            if (!empty($ar->last_seen_at)) {
                $secs = isset($ar->seconds_since_seen) ? (int) $ar->seconds_since_seen : null;
                $online = $secs !== null && $secs <= (int) $onlineThresholdSeconds;
                if (!$online) {
                    $ts = strtotime((string) $ar->last_seen_at);
                    if ($ts !== false) {
                        $offlineDays = max(0, (int) floor((time() - $ts) / 86400));
                    }
                }
            }
            $miniJobs = [];
            foreach ($jobRowsForAgent as $mj) {
                $miniJobs[] = [
                    'name' => (string) ($mj->name ?? ''),
                    'dest_label' => (string) ($mj->dest_bucket_name ?? '—'),
                    'mode' => e3backup_user_detail_job_mode_label($mj->engine ?? null, $mj->backup_mode ?? null),
                    'schedule' => e3backup_user_detail_schedule_label($mj->schedule_type ?? null),
                    'last_run' => '—',
                    'status_label' => ucfirst(strtolower((string) ($mj->job_status ?? 'active'))),
                    'status_tone' => strtolower((string) ($mj->job_status ?? 'active')),
                ];
            }
            $agentsDetail[] = [
                'agent_uuid' => $auKey,
                'hostname' => (string) ($ar->hostname ?? '—'),
                'uuid_short' => e3backup_user_detail_short_uuid($auKey),
                'agent_type' => (string) ($ar->agent_type ?? 'unknown'),
                'status' => (string) ($ar->status ?? 'unknown'),
                'last_seen_at' => $ar->last_seen_at,
                'online' => $online,
                'offline_days' => $offlineDays,
                'jobs_count' => count($miniJobs),
                'jobs' => $miniJobs,
            ];
        }
    }
}

(new JsonResponse([
    'status' => 'success',
    'user' => [
        'id' => (int) $user->id,
        'public_id' => (string) ($user->public_id ?? ''),
        'client_id' => (int) $user->client_id,
        'tenant_id' => $tenantPublicId,
        'tenant_public_id' => $tenantPublicId,
        'is_canonical_managed' => $isCanonicalManaged,
        'canonical_tenant_id' => $canonicalTenantPublicId,
        'canonical_tenant_public_id' => $canonicalTenantPublicId,
        'username' => (string) $user->username,
        'email' => (string) $user->email,
        'status' => (string) $user->status,
        'backup_type' => (string) ($user->backup_type ?? 'both'),
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'tenant_name' => $user->tenant_name ?? null,
        'canonical_tenant_name' => $canonicalTenantName,
        'vaults_count' => $metrics['vaults_count'],
        'jobs_count' => $metrics['jobs_count'],
        'agents_count' => $metrics['agents_count'],
        'online_devices' => $metrics['online_devices'],
        'last_backup_at' => $metrics['last_backup_at'],
        'metrics_mode' => 'derived_scope',
        'agents_detail' => $agentsDetail,
        'vaults_detail' => $vaultsDetail,
        'hyperv_jobs_count' => count($hypervJobs),
        'hyperv_jobs' => $hypervJobs,
        'hyperv_vms' => $hypervVms,
        'billing_kpis' => $billingKpis,
    ],
], 200))->send();
exit;

