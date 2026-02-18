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

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);

$tenantFilter = $_GET['tenant_id'] ?? null;
$agentFilter = $_GET['agent_id'] ?? null;
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
    $restorePointColumns = [
        'id',
        'client_id',
        'tenant_id',
        'repository_id',
        'tenant_user_id',
        'agent_id',
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
        $exists = $hasColumnMap
            ? isset($restorePointColumnMap[$col])
            : Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', $col);
        $select[] = $exists ? ('rp.' . $col) : Capsule::raw('NULL as ' . $col);
    }

    $query = Capsule::table('s3_cloudbackup_restore_points as rp')
        ->where('rp.client_id', $clientId);

    $hasAgentsTable = Capsule::schema()->hasTable('s3_cloudbackup_agents');
    if ($hasAgentsTable) {
        $query->leftJoin('s3_cloudbackup_agents as a', 'rp.agent_id', '=', 'a.id');
        $select[] = 'a.hostname as agent_hostname';
        $select[] = 'a.status as agent_status';
        $select[] = 'a.tenant_id as agent_tenant_id';
    } else {
        $select[] = Capsule::raw('NULL as agent_hostname');
        $select[] = Capsule::raw('NULL as agent_status');
        $select[] = Capsule::raw('NULL as agent_tenant_id');
    }

    $hasBucketsTable = Capsule::schema()->hasTable('s3_buckets');
    if ($hasBucketsTable) {
        $query->leftJoin('s3_buckets as b', 'rp.dest_bucket_id', '=', 'b.id');
        $select[] = 'b.name as dest_bucket_name';
    } else {
        $select[] = Capsule::raw('NULL as dest_bucket_name');
    }

    if ($isMsp) {
        $hasTenantsTable = Capsule::schema()->hasTable('s3_backup_tenants');
        if ($hasTenantsTable) {
            $query->leftJoin('s3_backup_tenants as t', 'rp.tenant_id', '=', 't.id');
            $select[] = 't.name as tenant_name';
        } else {
            $select[] = Capsule::raw('NULL as tenant_name');
        }
        if ($tenantFilter !== null) {
            if ($tenantFilter === 'direct') {
                $query->whereNull('rp.tenant_id');
            } elseif ((int) $tenantFilter > 0) {
                $query->where('rp.tenant_id', (int) $tenantFilter);
            }
        }
    }

    $query->select($select);

    if ($agentFilter && (int) $agentFilter > 0) {
        $query->where('rp.agent_id', (int) $agentFilter);
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
        'agent_filter' => $agentFilter,
        'engine_filter' => $engineFilter,
        'from_date' => $fromDateRaw,
        'to_date' => $toDateRaw,
        'limit' => $limit,
        'offset' => $offset,
    ], $e->getMessage());
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load restore points'], 500))->send();
}

exit;
