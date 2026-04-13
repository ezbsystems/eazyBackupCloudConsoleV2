<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

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
$jobFilterRaw = trim((string) ($_GET['job_id'] ?? ''));
$userScopeIdRaw = trim((string) ($_GET['user_id'] ?? ''));
$engineFilter = $_GET['engine'] ?? null;
$search = trim((string) ($_GET['search'] ?? ''));
$fromDateRaw = trim((string) ($_GET['from_date'] ?? ''));
$toDateRaw = trim((string) ($_GET['to_date'] ?? ''));
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
if ($limit <= 0 || $limit > 500) {
    $limit = 200;
}
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
if ($offset < 0) {
    $offset = 0;
}

$hasBackupUserPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$userScopeId = 0;
$scopeStorageTenantId = null;
$scopeUserActive = false;

if ($userScopeIdRaw !== '' && $userScopeIdRaw !== '0') {
    $tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
    $scopeUserQuery = Capsule::table('s3_backup_users as u')
        ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
        ->where('u.client_id', $clientId);
    if ($hasBackupUserPublicId && !ctype_digit($userScopeIdRaw)) {
        $scopeUserQuery->where('u.public_id', $userScopeIdRaw);
    } else {
        $scopeUserQuery->where('u.id', (int) $userScopeIdRaw);
    }
    $scopeUser = $scopeUserQuery->select([
        'u.id',
        'u.tenant_id as storage_tenant_id',
        Capsule::raw($tenantOwnerSelect),
        't.status as tenant_status',
    ])->first();

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

function parseDateBound(string $raw, bool $endOfDay): ?string
{
    if ($raw === '') {
        return null;
    }
    try {
        $dt = new \DateTime($raw);
        if ($endOfDay) {
            $dt->setTime(23, 59, 59);
        } else {
            $dt->setTime(0, 0, 0);
        }
        return $dt->format('Y-m-d H:i:s');
    } catch (\Throwable $e) {
        return null;
    }
}

function classifyRestorePointRestorable(object $point): array
{
    $status = strtolower(trim((string) ($point->status ?? '')));
    $engine = strtolower(trim((string) ($point->engine ?? '')));

    if ($status === 'metadata_incomplete') {
        return [
            'is_restorable' => false,
            'reason' => 'Restore metadata is incomplete. Create a fresh disk image backup.',
        ];
    }

    if (!in_array($status, ['success', 'warning'], true)) {
        return [
            'is_restorable' => false,
            'reason' => 'Restore point is not available.',
        ];
    }

    if ($engine === 'disk_image' && trim((string) ($point->disk_layout_json ?? '')) === '') {
        return [
            'is_restorable' => false,
            'reason' => 'Restore point is missing disk layout metadata. Create a fresh disk image backup.',
        ];
    }

    return [
        'is_restorable' => true,
        'reason' => '',
    ];
}

$fromDate = parseDateBound($fromDateRaw, false);
$toDate = parseDateBound($toDateRaw, true);

if (!Capsule::schema()->hasTable('s3_cloudbackup_restore_points')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Restore points not available'], 200))->send();
    exit;
}

try {
    $hasRestoreBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', 'backup_user_id');
    $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $hasJobBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
    $restorePointColumns = [
        'id',
        'client_id',
        'tenant_id',
        'repository_id',
        'tenant_user_id',
        'backup_user_id',
        'agent_uuid',
        'job_id',
        'job_name',
        'run_id',
        'run_uuid',
        'engine',
        'status',
        'manifest_id',
        'source_type',
        'source_display_name',
        'source_path',
        'dest_type',
        'dest_bucket_id',
        'dest_prefix',
        'dest_local_path',
        's3_user_id',
        'hyperv_vm_id',
        'hyperv_vm_name',
        'hyperv_backup_type',
        'hyperv_backup_point_id',
        'disk_manifests_json',
        'disk_layout_json',
        'disk_total_bytes',
        'disk_used_bytes',
        'disk_boot_mode',
        'disk_partition_style',
        'created_at',
        'finished_at',
    ];

    $restorePointColumnMap = [];
    try {
        $restorePointColumnMap = array_fill_keys(
            Capsule::schema()->getColumnListing('s3_cloudbackup_restore_points'),
            true
        );
    } catch (\Throwable $e) {
        $restorePointColumnMap = [];
    }
    $hasColumnMap = !empty($restorePointColumnMap);

    $select = [];
    foreach ($restorePointColumns as $col) {
        if ($col === 'tenant_id') {
            $select[] = 'rp.tenant_id as storage_tenant_id';
            continue;
        }
        $exists = $hasColumnMap
            ? isset($restorePointColumnMap[$col])
            : Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', $col);
        $select[] = $exists ? ('rp.' . $col) : Capsule::raw('NULL as ' . $col);
    }

    $query = Capsule::table('s3_cloudbackup_restore_points as rp')
        ->where('rp.client_id', $clientId);
    $tenantDeletedSelect = Capsule::raw('0 as tenant_deleted');

    $hasAgentsTable = Capsule::schema()->hasTable('s3_cloudbackup_agents');
    $hasAgentBackupUserId = $hasAgentsTable && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');
    if ($hasAgentsTable) {
        $query->leftJoin('s3_cloudbackup_agents as a', 'rp.agent_uuid', '=', 'a.agent_uuid');
        $select[] = 'a.hostname as agent_hostname';
        $select[] = 'a.status as agent_status';
        $select[] = 'a.tenant_id as agent_tenant_id';
        if ($hasAgentBackupUserId) {
            $select[] = 'a.backup_user_id as agent_backup_user_id';
        }
    } else {
        $select[] = Capsule::raw('NULL as agent_hostname');
        $select[] = Capsule::raw('NULL as agent_status');
        $select[] = Capsule::raw('NULL as agent_tenant_id');
        $select[] = Capsule::raw('NULL as agent_backup_user_id');
    }

    $hasJobsTable = Capsule::schema()->hasTable('s3_cloudbackup_jobs');
    if ($hasJobsTable && ($scopeUserActive || $jobFilterRaw !== '')) {
        $query->leftJoin('s3_cloudbackup_jobs as jctx', 'rp.job_id', '=', $hasJobIdPk ? 'jctx.job_id' : 'jctx.id');
        if ($hasJobBackupUserId) {
            $select[] = 'jctx.backup_user_id as job_backup_user_id';
        }
    } else {
        $select[] = Capsule::raw('NULL as job_backup_user_id');
    }

    $hasBucketsTable = Capsule::schema()->hasTable('s3_buckets');
    if ($hasBucketsTable) {
        $query->leftJoin('s3_buckets as b', 'rp.dest_bucket_id', '=', 'b.id');
        $select[] = 'b.name as dest_bucket_name';
    } else {
        $select[] = Capsule::raw('NULL as dest_bucket_name');
    }

    if ($isMsp) {
        $hasTenantsTable = Capsule::schema()->hasTable($tenantTable);
        if ($hasTenantsTable) {
            $query->leftJoin($tenantTable . ' as t', function ($join) use ($tenantTable, $clientId) {
                $join->on('rp.tenant_id', '=', 't.id');
                if ($tenantTable === 'eb_tenants') {
                    $mspId = MspController::getMspIdForClient((int)$clientId);
                    $join->where('t.msp_id', '=', (int)($mspId ?? 0));
                } else {
                    $join->where('t.client_id', '=', (int)$clientId);
                }
            });
            $select[] = 't.name as tenant_name';
            if ($tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds()) {
                $select[] = Capsule::raw('CASE WHEN t.id IS NULL THEN NULL ELSE t.public_id END as tenant_id');
                $tenantDeletedSelect = Capsule::raw('CASE WHEN rp.tenant_id IS NOT NULL AND t.id IS NULL THEN 1 ELSE 0 END as tenant_deleted');
            } else {
                $select[] = 'rp.tenant_id';
            }
        } else {
            $select[] = Capsule::raw('NULL as tenant_name');
            $select[] = 'rp.tenant_id';
        }
        if ($tenantFilterRaw !== null) {
            if ($tenantFilterRaw === 'direct') {
                $query->whereNull('rp.tenant_id');
            } elseif ($tenantFilter !== null) {
                $query->where('rp.tenant_id', $tenantFilter);
            }
        }
    } else {
        $select[] = 'rp.tenant_id';
    }
    $select[] = $tenantDeletedSelect;

    $query->select($select);

    if ($scopeUserActive) {
        if ($hasRestoreBackupUserId) {
            $query->where(function ($scoped) use ($userScopeId, $hasJobBackupUserId, $hasAgentBackupUserId) {
                $scoped->where('rp.backup_user_id', $userScopeId)
                    ->orWhere(function ($legacy) use ($userScopeId, $hasJobBackupUserId, $hasAgentBackupUserId) {
                        $legacy->whereNull('rp.backup_user_id');
                        if ($hasJobBackupUserId && $hasAgentBackupUserId) {
                            $legacy->where(function ($owned) use ($userScopeId) {
                                $owned->where('jctx.backup_user_id', $userScopeId)
                                    ->orWhere(function ($fallback) use ($userScopeId) {
                                        $fallback->whereNull('jctx.backup_user_id')
                                            ->where('a.backup_user_id', $userScopeId);
                                    });
                            });
                        } elseif ($hasJobBackupUserId) {
                            $legacy->where('jctx.backup_user_id', $userScopeId);
                        } elseif ($hasAgentBackupUserId) {
                            $legacy->where('a.backup_user_id', $userScopeId);
                        } else {
                            $legacy->whereRaw('1 = 0');
                        }
                    });
            });
        } else {
            if ($hasJobBackupUserId && $hasAgentBackupUserId) {
                $query->where(function ($scoped) use ($userScopeId) {
                    $scoped->where('jctx.backup_user_id', $userScopeId)
                        ->orWhere(function ($fallback) use ($userScopeId) {
                            $fallback->whereNull('jctx.backup_user_id')
                                ->where('a.backup_user_id', $userScopeId);
                        });
                });
            } elseif ($hasJobBackupUserId) {
                $query->where('jctx.backup_user_id', $userScopeId);
            } elseif ($hasAgentBackupUserId) {
                $query->where('a.backup_user_id', $userScopeId);
            } elseif ($scopeStorageTenantId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereRaw('1 = 0');
            }
        }
    }

    if ($agentFilter !== null && $agentFilter !== '') {
        $query->where('rp.agent_uuid', $agentFilter);
    }

    if ($jobFilterRaw !== '') {
        $jobIdType = null;
        if (isset($restorePointColumnMap['job_id'])) {
            $jobIdTypeRow = Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_restore_points WHERE Field = 'job_id'");
            $jobIdType = strtolower((string) ($jobIdTypeRow->Type ?? ''));
        }
        $isBinaryJobId = $jobIdType !== null && stripos($jobIdType, 'binary') !== false;
        if ($isBinaryJobId) {
            if (!UuidBinary::isUuid($jobFilterRaw)) {
                (new JsonResponse(['status' => 'fail', 'message' => 'job_id must be a valid UUID'], 400))->send();
                exit;
            }
            $query->whereRaw('rp.job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobFilterRaw)));
        } else {
            $query->where('rp.job_id', $jobFilterRaw);
        }
    }

    if ($engineFilter && is_string($engineFilter)) {
        $query->where('rp.engine', $engineFilter);
    }

    if ($fromDate || $toDate) {
        $tsField = Capsule::raw('COALESCE(rp.finished_at, rp.created_at)');
        if ($fromDate) {
            $query->where($tsField, '>=', $fromDate);
        }
        if ($toDate) {
            $query->where($tsField, '<=', $toDate);
        }
    }

    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('rp.job_name', 'like', '%' . $search . '%')
              ->orWhere('rp.manifest_id', 'like', '%' . $search . '%')
              ->orWhere('rp.hyperv_vm_name', 'like', '%' . $search . '%')
              ->orWhere('rp.source_display_name', 'like', '%' . $search . '%')
              ->orWhere('a.hostname', 'like', '%' . $search . '%')
              ->orWhere('b.name', 'like', '%' . $search . '%');
        });
    }

    $points = $query
        ->orderByDesc('rp.created_at')
        ->offset($offset)
        ->limit($limit + 1)
        ->get();

    $hasMore = $points->count() > $limit;
    if ($hasMore) {
        $points = $points->slice(0, $limit)->values();
    }
    foreach ($points as $point) {
        $classification = classifyRestorePointRestorable($point);
        $point->is_restorable = (bool) $classification['is_restorable'];
        $point->non_restorable_reason = (string) $classification['reason'];
        $point->tenant_deleted = (bool) ($point->tenant_deleted ?? false);
        if ($point->tenant_deleted && (!isset($point->tenant_name) || trim((string) $point->tenant_name) === '')) {
            $point->tenant_name = 'Deleted tenant';
        }
        unset($point->storage_tenant_id, $point->agent_tenant_id, $point->backup_user_id, $point->agent_backup_user_id, $point->job_backup_user_id);
    }

    (new JsonResponse([
        'status' => 'success',
        'restore_points' => $points,
        'has_more' => $hasMore,
        'next_offset' => $hasMore ? ($offset + $limit) : null,
    ], 200))->send();
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'e3backup_restore_points_list', [
        'client_id' => $clientId,
        'tenant_filter' => $tenantFilter,
        'tenant_filter_raw' => $tenantFilterRaw,
        'agent_filter' => $agentFilter,
        'job_filter' => $jobFilterRaw,
        'user_scope_id' => $userScopeIdRaw,
        'engine_filter' => $engineFilter,
        'from_date' => $fromDateRaw,
        'to_date' => $toDateRaw,
        'limit' => $limit,
        'offset' => $offset,
    ], $e->getMessage());
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load restore points'], 500))->send();
}

exit;
