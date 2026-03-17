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
$tenantTable = MspController::getTenantTableName();
$tenantFilterRaw = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : null;
$tenantFilter = null;
$agentFilter = isset($_GET['agent_uuid']) ? trim((string) $_GET['agent_uuid']) : null;

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
    $tenantColumn = $hasJobTenantCol ? 'j.tenant_id' : 'a.tenant_id';
    $tenantSelect = Capsule::raw($tenantColumn . ' as tenant_id');
    $tenantDeletedSelect = Capsule::raw('0 as tenant_deleted');
    if ($isMsp && $tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds()) {
        $tenantSelect = Capsule::raw('CASE WHEN t.id IS NULL THEN NULL ELSE t.public_id END as tenant_id');
        $tenantDeletedSelect = Capsule::raw('CASE WHEN ' . $tenantColumn . ' IS NOT NULL AND t.id IS NULL THEN 1 ELSE 0 END as tenant_deleted');
    }

    $query = Capsule::table('s3_cloudbackup_jobs as j')
        ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted')
        ->select([
            'j.id',
            'j.name',
            'j.source_type',
            'j.source_display_name',
            'j.source_path',
            'j.engine',
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

    $jobs = $query->orderByDesc('j.created_at')->get();

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

    // Attach last run summary per job
    $jobIds = $jobs->pluck('id')->toArray();
    $lastRunByJob = [];
    if (!empty($jobIds)) {
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

    foreach ($jobs as $job) {
        $bucketId = $job->dest_bucket_id ?? null;
        if ($bucketId !== null && isset($bucketNameById[$bucketId])) {
            $job->dest_bucket_name = $bucketNameById[$bucketId];
        }
        $jobId = (int) $job->id;
        $job->last_run = $lastRunByJob[$jobId] ?? null;
        $job->tenant_deleted = (bool) ($job->tenant_deleted ?? false);
        if ($job->tenant_deleted && (!isset($job->tenant_name) || trim((string) $job->tenant_name) === '')) {
            $job->tenant_name = 'Deleted tenant';
        }
        unset($job->storage_tenant_id, $job->agent_tenant_id);
    }

    (new JsonResponse(['status' => 'success', 'jobs' => $jobs], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load jobs'], 500))->send();
}
exit;

