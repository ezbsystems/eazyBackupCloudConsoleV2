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

/**
 * Resolve snapshot manifest from a run row (schema may use manifest_id, log_ref, or stats_json).
 */
function extractManifestIdFromRun(object $run): string
{
    if (isset($run->manifest_id) && trim((string) $run->manifest_id) !== '') {
        return trim((string) $run->manifest_id);
    }
    if (isset($run->log_ref) && trim((string) $run->log_ref) !== '') {
        return trim((string) $run->log_ref);
    }
    if (!empty($run->stats_json)) {
        $stats = is_string($run->stats_json) ? json_decode($run->stats_json, true) : $run->stats_json;
        if (is_array($stats) && !empty($stats['manifest_id'])) {
            return trim((string) $stats['manifest_id']);
        }
    }
    return '';
}

/**
 * Build select list and snapshot filter for s3_cloudbackup_runs (manifest column varies by migration).
 *
 * @return array{0: array, 1: callable|null} [select columns, optional query mutator]
 */
function buildSuccessfulRunSnapshotQueryParts(bool $hasRunIdCol, bool $hasRunFinishedAt): array
{
    $hasManifestCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'manifest_id');
    $hasLogRefCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'log_ref');

    $runSelect = ['started_at', 'stats_json'];
    if ($hasRunFinishedAt) {
        $runSelect[] = 'finished_at';
    }
    if ($hasManifestCol) {
        $runSelect[] = 'manifest_id';
    } elseif ($hasLogRefCol) {
        $runSelect[] = 'log_ref';
    }
    if ($hasRunIdCol) {
        $runSelect[] = Capsule::raw('BIN_TO_UUID(run_id) as run_id');
    } else {
        $runSelect[] = 'id as run_id';
    }

    $filter = null;
    if ($hasManifestCol) {
        $filter = static function ($query) {
            $query->whereNotNull('manifest_id')->where('manifest_id', '!=', '');
        };
    } elseif ($hasLogRefCol) {
        $filter = static function ($query) {
            $query->whereNotNull('log_ref')->where('log_ref', '!=', '');
        };
    }

    return [$runSelect, $filter];
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

    // job_id and run_id are BINARY(16) columns. Selecting them raw makes
    // JsonResponse::json_encode() throw "Malformed UTF-8 characters" (HTTP
    // 500), because the 16 binary bytes are not valid UTF-8. Emit their
    // textual UUID form via BIN_TO_UUID() so the payload is JSON-safe.
    $binaryUuidColumns = ['job_id' => true, 'run_id' => true];

    $select = [];
    foreach ($restorePointColumns as $col) {
        if ($col === 'tenant_id') {
            $select[] = 'rp.tenant_id as storage_tenant_id';
            continue;
        }
        $exists = $hasColumnMap
            ? isset($restorePointColumnMap[$col])
            : Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', $col);
        if (!$exists) {
            $select[] = Capsule::raw('NULL as ' . $col);
            continue;
        }
        if (isset($binaryUuidColumns[$col])) {
            // Guard against NULLs and non-16-byte values so a malformed row
            // can't break the whole response.
            $select[] = Capsule::raw(
                "CASE WHEN rp.`{$col}` IS NULL THEN NULL ELSE BIN_TO_UUID(rp.`{$col}`) END as {$col}"
            );
            continue;
        }
        $select[] = 'rp.' . $col;
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

    $includeAllSnapshots = ($jobFilterRaw !== '');
    if (isset($_GET['include_all_snapshots']) && (string) $_GET['include_all_snapshots'] === '0') {
        $includeAllSnapshots = false;
    }

    if ($includeAllSnapshots && $jobFilterRaw !== '' && UuidBinary::isUuid($jobFilterRaw) && $hasJobIdPk) {
        $jobIdNorm = UuidBinary::normalize($jobFilterRaw);
        $jobRow = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->whereRaw('job_id = ' . UuidBinary::toDbExpr($jobIdNorm))
            ->first();

        if (!$jobRow) {
            (new JsonResponse(['status' => 'fail', 'message' => 'Job not found'], 404))->send();
            exit;
        }

        $retentionKeepLast = null;
        if (!empty($jobRow->retention_json)) {
            $retentionJson = is_string($jobRow->retention_json)
                ? json_decode($jobRow->retention_json, true)
                : $jobRow->retention_json;
            if (is_array($retentionJson) && isset($retentionJson['keep_last'])) {
                $retentionKeepLast = (int) $retentionJson['keep_last'];
            }
        }

        $catalogPoints = $query->orderByDesc('rp.created_at')->limit(500)->get();
        foreach ($catalogPoints as $point) {
            $classification = classifyRestorePointRestorable($point);
            $point->is_restorable = (bool) $classification['is_restorable'];
            $point->non_restorable_reason = (string) $classification['reason'];
            $point->tenant_deleted = (bool) ($point->tenant_deleted ?? false);
            if ($point->tenant_deleted && (!isset($point->tenant_name) || trim((string) $point->tenant_name) === '')) {
                $point->tenant_name = 'Deleted tenant';
            }
            $point->in_retention_catalog = true;
            $point->catalog_pruned = false;
            unset($point->storage_tenant_id, $point->agent_tenant_id, $point->backup_user_id, $point->agent_backup_user_id, $point->job_backup_user_id);
        }

        $catalogByManifest = [];
        $catalogByRunId = [];
        $usedCatalogIds = [];
        foreach ($catalogPoints as $point) {
            if (!empty($point->manifest_id)) {
                $catalogByManifest[(string) $point->manifest_id] = $point;
            }
            if (!empty($point->run_id)) {
                $catalogByRunId[(string) $point->run_id] = $point;
            }
        }

        $hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
        $hasRunFinishedAt = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'finished_at');
        [$runSelect, $runSnapshotFilter] = buildSuccessfulRunSnapshotQueryParts($hasRunIdCol, $hasRunFinishedAt);

        $runsQuery = Capsule::table('s3_cloudbackup_runs')
            ->where('status', 'success')
            ->whereRaw('job_id = ' . UuidBinary::toDbExpr($jobIdNorm));
        if ($runSnapshotFilter !== null) {
            $runSnapshotFilter($runsQuery);
        }
        $runs = $runsQuery
            ->orderByDesc($hasRunFinishedAt ? 'finished_at' : 'started_at')
            ->limit(500)
            ->get($runSelect);

        if ($runSnapshotFilter === null) {
            $runs = $runs->filter(static function ($run) {
                return extractManifestIdFromRun($run) !== '';
            })->values();
        }

        $agentHostname = null;
        if (!empty($jobRow->agent_uuid) && $hasAgentsTable) {
            $agentHostname = Capsule::table('s3_cloudbackup_agents')
                ->where('agent_uuid', $jobRow->agent_uuid)
                ->value('hostname');
        }

        $jobName = (string) ($jobRow->name ?? 'Unnamed job');
        $jobEngine = (string) ($jobRow->engine ?? '');
        $jobAgentUuid = (string) ($jobRow->agent_uuid ?? '');

        $merged = [];
        foreach ($runs as $run) {
            $manifestId = extractManifestIdFromRun($run);
            $runId = (string) ($run->run_id ?? '');
            $point = $catalogByManifest[$manifestId] ?? null;
            if ($point === null && $runId !== '') {
                $point = $catalogByRunId[$runId] ?? null;
            }

            if ($point !== null) {
                $catalogId = (int) ($point->id ?? 0);
                if ($catalogId > 0) {
                    $usedCatalogIds[$catalogId] = true;
                }
                $merged[] = $point;
                continue;
            }

            $finishedAt = $hasRunFinishedAt ? ($run->finished_at ?? null) : null;
            if ($finishedAt === null || $finishedAt === '') {
                $finishedAt = $run->started_at ?? null;
            }

            $synthetic = (object) [
                'id' => null,
                'client_id' => $clientId,
                'job_id' => $jobIdNorm,
                'job_name' => $jobName,
                'run_id' => $runId !== '' ? $runId : null,
                'engine' => $jobEngine,
                'status' => 'success',
                'manifest_id' => $manifestId,
                'agent_uuid' => $jobAgentUuid,
                'agent_hostname' => $agentHostname,
                'source_display_name' => $jobRow->source_path ?? null,
                'source_path' => $jobRow->source_path ?? null,
                'finished_at' => $finishedAt,
                'created_at' => $run->started_at ?? $finishedAt,
                'in_retention_catalog' => false,
                'catalog_pruned' => true,
                'is_restorable' => false,
                'non_restorable_reason' => 'Not in retention catalog (snapshot may still exist in repository)',
                'tenant_deleted' => false,
            ];
            $merged[] = $synthetic;
        }

        foreach ($catalogPoints as $point) {
            $catalogId = (int) ($point->id ?? 0);
            if ($catalogId > 0 && !isset($usedCatalogIds[$catalogId])) {
                $merged[] = $point;
            }
        }

        if ($agentFilter !== null && $agentFilter !== '') {
            $merged = array_values(array_filter($merged, static function ($point) use ($agentFilter) {
                return (string) ($point->agent_uuid ?? '') === (string) $agentFilter;
            }));
        }

        if ($engineFilter && is_string($engineFilter)) {
            $merged = array_values(array_filter($merged, static function ($point) use ($engineFilter) {
                return strtolower((string) ($point->engine ?? '')) === strtolower($engineFilter);
            }));
        }

        if ($search !== '') {
            $needle = strtolower($search);
            $merged = array_values(array_filter($merged, static function ($point) use ($needle) {
                $haystacks = [
                    (string) ($point->job_name ?? ''),
                    (string) ($point->manifest_id ?? ''),
                    (string) ($point->hyperv_vm_name ?? ''),
                    (string) ($point->source_display_name ?? ''),
                    (string) ($point->agent_hostname ?? ''),
                    (string) ($point->dest_bucket_name ?? ''),
                ];
                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && strpos(strtolower($haystack), $needle) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        if ($fromDate || $toDate) {
            $merged = array_values(array_filter($merged, static function ($point) use ($fromDate, $toDate) {
                $ts = (string) ($point->finished_at ?? $point->created_at ?? '');
                if ($ts === '') {
                    return false;
                }
                if ($fromDate && $ts < $fromDate) {
                    return false;
                }
                if ($toDate && $ts > $toDate) {
                    return false;
                }
                return true;
            }));
        }

        usort($merged, static function ($a, $b) {
            $aTs = (string) ($a->finished_at ?? $a->created_at ?? '');
            $bTs = (string) ($b->finished_at ?? $b->created_at ?? '');
            return strcmp($bTs, $aTs);
        });

        $beyondRetentionCount = 0;
        $catalogCount = 0;
        foreach ($merged as $point) {
            if (!empty($point->catalog_pruned)) {
                $beyondRetentionCount++;
            }
            if (!empty($point->in_retention_catalog)) {
                $catalogCount++;
            }
        }

        $totalMerged = count($merged);
        $pageSlice = array_slice($merged, $offset, $limit + 1);
        $hasMore = count($pageSlice) > $limit;
        if ($hasMore) {
            $pageSlice = array_slice($pageSlice, 0, $limit);
        }

        (new JsonResponse([
            'status' => 'success',
            'restore_points' => array_values($pageSlice),
            'has_more' => $hasMore,
            'next_offset' => $hasMore ? ($offset + $limit) : null,
            'snapshot_meta' => [
                'total_snapshots' => $totalMerged,
                'catalog_count' => $catalogCount,
                'beyond_retention_count' => $beyondRetentionCount,
                'retention_keep_last' => $retentionKeepLast,
            ],
        ], 200))->send();
        exit;
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
