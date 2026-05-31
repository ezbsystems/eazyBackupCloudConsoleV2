<?php

/**
 * e3backup_run_list.php
 *
 * Server-side, filtered + paginated run-level list across ALL of the
 * client's e3 Cloud Backup jobs. Powers the Job Logs page (one row per run).
 *
 * GET params:
 *   range_hours  - 24 | 48 | 60 | 72   (default 24)
 *   statuses[]   - filter to these run statuses
 *   agent_uuid   - filter to one agent
 *   tenant_id    - MSP tenant public id (or 'direct')
 *   user_id      - backup user (public id or int id) scope
 *   q            - free-text search (job name / agent hostname)
 *   page         - 1-based page (default 1)
 *   pageSize     - 10 | 25 | 50 | 100  (default 25)
 *   sortBy       - started | finished | status | job | agent  (default started)
 *   sortDir      - asc | desc  (default desc)
 *
 * Returns: { status, total, page, pageSize, rows[], facets: { statusCounts } }
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}
$clientId = (int) $ca->getUserID();

// ── Inputs ──
$rangeHours = (int) ($_GET['range_hours'] ?? 24);
if (!in_array($rangeHours, [24, 48, 60, 72], true)) {
    $rangeHours = 24;
}
$statuses = isset($_GET['statuses']) && is_array($_GET['statuses']) ? array_map('strval', $_GET['statuses']) : [];
$validStatuses = ['success', 'warning', 'partial_success', 'failed', 'cancelled', 'running', 'starting', 'queued'];
$statuses = array_values(array_intersect($statuses, $validStatuses));

$agentFilter = isset($_GET['agent_uuid']) ? trim((string) $_GET['agent_uuid']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = (int) ($_GET['pageSize'] ?? 25);
if (!in_array($pageSize, [10, 25, 50, 100], true)) {
    $pageSize = 25;
}
$sortBy = strtolower((string) ($_GET['sortBy'] ?? 'started'));
$sortDir = strtolower((string) ($_GET['sortDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$tenantFilterRaw = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : null;
$tenantFilter = null;
if ($isMsp && $tenantFilterRaw !== null && $tenantFilterRaw !== '' && $tenantFilterRaw !== 'direct') {
    $tenant = MspController::getTenantByPublicId($tenantFilterRaw, $clientId);
    if ($tenant) {
        $tenantFilter = (int) $tenant->id;
    }
}

try {
    $schema = Capsule::schema();
    $hasRunIdCol = $schema->hasColumn('s3_cloudbackup_runs', 'run_id');
    $hasJobIdPk = $schema->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $hasJobTenant = $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
    $hasJobBackupUser = $schema->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
    $hasAgentBackupUser = $schema->hasColumn('s3_cloudbackup_agents', 'backup_user_id');

    $jobRunJoin = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $rangeHours . ' hours'));

    $buildBase = function () use ($jobRunJoin, $clientId, $cutoff, $hasJobTenant, $tenantTable, $tenantFilterRaw, $tenantFilter, $agentFilter, $q, $statuses) {
        $base = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
            ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
            ->where('j.client_id', $clientId)
            ->where('j.status', '!=', 'deleted')
            ->where('r.started_at', '>=', $cutoff);

        if ($hasJobTenant && $tenantFilterRaw !== null) {
            if ($tenantFilterRaw === 'direct') {
                $base->whereNull('j.tenant_id');
            } elseif ($tenantFilter !== null) {
                $base->where('j.tenant_id', $tenantFilter);
            }
        }
        if ($agentFilter !== '') {
            $base->where('j.agent_uuid', $agentFilter);
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $base->where(function ($w) use ($like) {
                $w->where('j.name', 'like', $like)
                  ->orWhere('a.hostname', 'like', $like);
            });
        }
        if (!empty($statuses)) {
            $base->whereIn('r.status', $statuses);
        }
        return $base;
    };

    // ── Status facet counts (ignores the status filter, honours the rest) ──
    $facetQuery = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
        ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted')
        ->where('r.started_at', '>=', $cutoff);
    if ($hasJobTenant && $tenantFilterRaw !== null) {
        if ($tenantFilterRaw === 'direct') {
            $facetQuery->whereNull('j.tenant_id');
        } elseif ($tenantFilter !== null) {
            $facetQuery->where('j.tenant_id', $tenantFilter);
        }
    }
    if ($agentFilter !== '') {
        $facetQuery->where('j.agent_uuid', $agentFilter);
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $facetQuery->where(function ($w) use ($like) {
            $w->where('j.name', 'like', $like)->orWhere('a.hostname', 'like', $like);
        });
    }
    $statusCounts = [];
    foreach ($facetQuery->groupBy('r.status')->get([Capsule::raw('r.status'), Capsule::raw('COUNT(*) as cnt')]) as $row) {
        $statusCounts[strtolower((string) $row->status)] = (int) $row->cnt;
    }

    // ── Total + paginated rows ──
    $total = (int) $buildBase()->count();

    $sortColMap = [
        'started' => 'r.started_at',
        'finished' => 'r.finished_at',
        'status' => 'r.status',
        'job' => 'j.name',
        'agent' => 'a.hostname',
    ];
    $sortCol = $sortColMap[$sortBy] ?? 'r.started_at';

    $runIdSelect = $hasRunIdCol
        ? Capsule::raw('BIN_TO_UUID(r.run_id) as run_id')
        : Capsule::raw('r.id as run_id');

    $userExpr = 'NULL as backup_user_id';
    if ($hasJobBackupUser && $hasAgentBackupUser) {
        $userExpr = 'COALESCE(j.backup_user_id, a.backup_user_id) as backup_user_id';
    } elseif ($hasJobBackupUser) {
        $userExpr = 'j.backup_user_id as backup_user_id';
    } elseif ($hasAgentBackupUser) {
        $userExpr = 'a.backup_user_id as backup_user_id';
    }

    $rows = $buildBase()
        ->orderBy($sortCol, $sortDir)
        ->offset(($page - 1) * $pageSize)
        ->limit($pageSize)
        ->get([
            $runIdSelect,
            'r.status',
            'r.started_at',
            'r.finished_at',
            'r.trigger_type',
            'r.engine',
            'r.bytes_processed',
            'r.bytes_transferred',
            'j.name as job_name',
            'a.hostname as agent_hostname',
            'a.agent_uuid',
            Capsule::raw($userExpr),
        ]);

    // Resolve backup-user usernames in one pass.
    $userIds = [];
    foreach ($rows as $r) {
        if (!empty($r->backup_user_id)) {
            $userIds[] = (int) $r->backup_user_id;
        }
    }
    $usernameById = [];
    if (!empty($userIds)) {
        foreach (Capsule::table('s3_backup_users')->whereIn('id', array_unique($userIds))->get(['id', 'username']) as $u) {
            $usernameById[(int) $u->id] = (string) $u->username;
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $duration = '-';
        if (!empty($r->started_at) && !empty($r->finished_at)) {
            $diff = max(0, strtotime($r->finished_at) - strtotime($r->started_at));
            if ($diff >= 3600) {
                $duration = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
            } elseif ($diff >= 60) {
                $duration = floor($diff / 60) . 'm ' . ($diff % 60) . 's';
            } else {
                $duration = $diff . 's';
            }
        }
        $bytes = max((int) ($r->bytes_processed ?? 0), (int) ($r->bytes_transferred ?? 0));
        $out[] = [
            'run_id' => (string) ($r->run_id ?? ''),
            'status' => (string) $r->status,
            'started_at' => (string) ($r->started_at ?? ''),
            'finished_at' => (string) ($r->finished_at ?? ''),
            'trigger_type' => (string) ($r->trigger_type ?? ''),
            'engine' => (string) ($r->engine ?? 'sync'),
            'job_name' => (string) ($r->job_name ?? ''),
            'agent_hostname' => (string) ($r->agent_hostname ?? ''),
            'agent_uuid' => (string) ($r->agent_uuid ?? ''),
            'username' => !empty($r->backup_user_id) ? ($usernameById[(int) $r->backup_user_id] ?? '') : '',
            'duration' => $duration,
            'size_formatted' => $bytes > 0 ? HelperController::formatSizeUnitsPlain($bytes) : '-',
        ];
    }

    (new JsonResponse([
        'status' => 'success',
        'total' => $total,
        'page' => $page,
        'pageSize' => $pageSize,
        'rangeHours' => $rangeHours,
        'rows' => $out,
        'facets' => ['statusCounts' => $statusCounts],
    ], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load runs'], 500))->send();
}
exit;
