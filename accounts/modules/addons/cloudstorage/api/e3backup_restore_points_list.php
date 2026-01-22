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

$fromDate = parseDateBound($fromDateRaw, false);
$toDate = parseDateBound($toDateRaw, true);

if (!Capsule::schema()->hasTable('s3_cloudbackup_restore_points')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Restore points not available'], 200))->send();
    exit;
}

try {
    $query = Capsule::table('s3_cloudbackup_restore_points as rp')
        ->leftJoin('s3_cloudbackup_agents as a', 'rp.agent_id', '=', 'a.id')
        ->leftJoin('s3_buckets as b', 'rp.dest_bucket_id', '=', 'b.id')
        ->where('rp.client_id', $clientId)
        ->select([
            'rp.id',
            'rp.client_id',
            'rp.tenant_id',
            'rp.tenant_user_id',
            'rp.agent_id',
            'rp.job_id',
            'rp.job_name',
            'rp.run_id',
            'rp.run_uuid',
            'rp.engine',
            'rp.status',
            'rp.manifest_id',
            'rp.source_type',
            'rp.source_display_name',
            'rp.source_path',
            'rp.dest_type',
            'rp.dest_bucket_id',
            'rp.dest_prefix',
            'rp.dest_local_path',
            'rp.s3_user_id',
            'rp.hyperv_vm_id',
            'rp.hyperv_vm_name',
            'rp.hyperv_backup_type',
            'rp.hyperv_backup_point_id',
            'rp.disk_manifests_json',
            'rp.created_at',
            'rp.finished_at',
            'a.hostname as agent_hostname',
            'a.status as agent_status',
            'a.tenant_id as agent_tenant_id',
            'b.name as dest_bucket_name',
        ]);

    if ($isMsp) {
        $query->leftJoin('s3_backup_tenants as t', 'rp.tenant_id', '=', 't.id')
              ->addSelect('t.name as tenant_name');
        if ($tenantFilter !== null) {
            if ($tenantFilter === 'direct') {
                $query->whereNull('rp.tenant_id');
            } elseif ((int) $tenantFilter > 0) {
                $query->where('rp.tenant_id', (int) $tenantFilter);
            }
        }
    }

    if ($agentFilter && (int) $agentFilter > 0) {
        $query->where('rp.agent_id', (int) $agentFilter);
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

    (new JsonResponse([
        'status' => 'success',
        'restore_points' => $points,
        'has_more' => $hasMore,
        'next_offset' => $hasMore ? ($offset + $limit) : null,
    ], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load restore points'], 500))->send();
}

exit;
