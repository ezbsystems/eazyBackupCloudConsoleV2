<?php

/**
 * e3backup_backup_history.php
 *
 * Returns the "Recent Backup History" grid model for the e3 Cloud Backup
 * dashboard: one entry per agent, each with a per-day worst-status strip,
 * that day's run list, a last-24h summary, and per-job sub-rows for the
 * drill-down. Lazy-loaded by the dashboard so the initial paint stays fast
 * for MSPs / large accounts.
 *
 * GET params:
 *   days        - history window in calendar days (default 14, clamp 7..31)
 *   tenant_id   - MSP tenant public id (or 'direct') filter
 *   agent_uuid  - limit to a single agent
 *
 * Returns: { status, days[], agents: [ { agent_uuid, hostname, agent_os,
 *            is_online, last24h{}, days[], jobs[] } ], agentTotal }
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

$days = (int) ($_GET['days'] ?? 14);
if ($days < 7) { $days = 7; }
if ($days > 31) { $days = 31; }

$agentFilter = isset($_GET['agent_uuid']) ? trim((string) $_GET['agent_uuid']) : '';
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

// Worst-status priority: lower number = worse (wins the day's dot).
$priority = [
    'failed' => 1,
    'partial_success' => 2,
    'warning' => 3,
    'cancelled' => 4,
    'running' => 5,
    'starting' => 5,
    'queued' => 6,
    'success' => 7,
];
$worse = function ($a, $b) use ($priority) {
    if ($a === null) return $b;
    if ($b === null) return $a;
    $pa = $priority[$a] ?? 99;
    $pb = $priority[$b] ?? 99;
    return $pa <= $pb ? $a : $b;
};

try {
    $schema = Capsule::schema();
    $hasRunIdCol = $schema->hasColumn('s3_cloudbackup_runs', 'run_id');
    $hasJobIdPk = $schema->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $hasJobTenant = $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
    $onlineThresholdSeconds = 180;
    if (function_exists('getModuleSetting')) {
        $onlineThresholdSeconds = (int) getModuleSetting('cloudbackup_agent_online_threshold_seconds', 180);
    }
    if ($onlineThresholdSeconds <= 0) { $onlineThresholdSeconds = 180; }

    // ── Agents for this client ──
    $agentQuery = Capsule::table('s3_cloudbackup_agents')
        ->where('client_id', $clientId)
        ->select([
            'agent_uuid',
            'hostname',
            'agent_os',
            'last_seen_at',
            'tenant_id',
            Capsule::raw('TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) AS seconds_since_seen'),
        ]);
    if ($agentFilter !== '') {
        $agentQuery->where('agent_uuid', $agentFilter);
    }
    if ($hasJobTenant && $tenantFilterRaw !== null) {
        if ($tenantFilterRaw === 'direct') {
            $agentQuery->whereNull('tenant_id');
        } elseif ($tenantFilter !== null) {
            $agentQuery->where('tenant_id', $tenantFilter);
        }
    }
    $agentRows = $agentQuery->orderBy('hostname')->get();
    $agentTotal = count($agentRows);

    // Cap how many agents we hydrate with run history per request.
    $agentCap = 200;
    $agentRows = array_slice($agentRows->all(), 0, $agentCap);

    $agentByUuid = [];
    foreach ($agentRows as $a) {
        $uuid = (string) $a->agent_uuid;
        if ($uuid === '') { continue; }
        $secs = $a->seconds_since_seen !== null ? (int) $a->seconds_since_seen : null;
        $agentByUuid[$uuid] = [
            'agent_uuid' => $uuid,
            'hostname' => (string) ($a->hostname ?? '') ?: 'Unknown host',
            'agent_os' => (string) ($a->agent_os ?? ''),
            'is_online' => !empty($a->last_seen_at) && $secs !== null && $secs <= $onlineThresholdSeconds,
            '_days' => [],   // date => ['status'=>..,'count'=>..,'runs'=>[]]
            '_jobs' => [],   // job_id => ['name'=>.., 'days'=>[date=>status]]
            '_last24h' => ['success' => 0, 'warning' => 0, 'failed' => 0, 'running' => 0, 'cancelled' => 0],
        ];
    }

    // ── Runs in the window ──
    $jobRunJoin = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];
    $cutoff = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $cutoff24h = strtotime('-24 hours');

    $runIdSelect = $hasRunIdCol
        ? Capsule::raw('BIN_TO_UUID(r.run_id) as run_id')
        : Capsule::raw('r.id as run_id');
    $jobIdSelect = $hasJobIdPk
        ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id')
        : Capsule::raw('j.id as job_id');

    $runQuery = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted')
        ->where('r.started_at', '>=', $cutoff)
        ->whereNotNull('j.agent_uuid');
    if ($agentFilter !== '') {
        $runQuery->where('j.agent_uuid', $agentFilter);
    }
    if ($hasJobTenant && $tenantFilterRaw !== null) {
        if ($tenantFilterRaw === 'direct') {
            $runQuery->whereNull('j.tenant_id');
        } elseif ($tenantFilter !== null) {
            $runQuery->where('j.tenant_id', $tenantFilter);
        }
    }
    $runs = $runQuery->orderBy('r.started_at', 'desc')->limit(20000)->get([
        $runIdSelect,
        $jobIdSelect,
        'r.status',
        'r.started_at',
        'r.finished_at',
        'r.bytes_processed',
        'r.bytes_transferred',
        'j.name as job_name',
        'j.agent_uuid',
    ]);

    foreach ($runs as $r) {
        $uuid = (string) $r->agent_uuid;
        if (!isset($agentByUuid[$uuid])) { continue; }
        $status = strtolower((string) $r->status);
        $startedTs = !empty($r->started_at) ? strtotime($r->started_at) : 0;
        $dayKey = $startedTs ? date('Y-m-d', $startedTs) : date('Y-m-d');
        $agent =& $agentByUuid[$uuid];

        // Day rollup
        if (!isset($agent['_days'][$dayKey])) {
            $agent['_days'][$dayKey] = ['status' => null, 'count' => 0, 'runs' => []];
        }
        $agent['_days'][$dayKey]['status'] = $worse($agent['_days'][$dayKey]['status'], $status);
        $agent['_days'][$dayKey]['count']++;
        if (count($agent['_days'][$dayKey]['runs']) < 25) {
            $bytes = max((int) ($r->bytes_processed ?? 0), (int) ($r->bytes_transferred ?? 0));
            $agent['_days'][$dayKey]['runs'][] = [
                'run_id' => (string) ($r->run_id ?? ''),
                'job_name' => (string) ($r->job_name ?? ''),
                'status' => $status,
                'time' => $startedTs ? date('H:i', $startedTs) : '',
                'started_at' => (string) ($r->started_at ?? ''),
                'size' => $bytes > 0 ? HelperController::formatSizeUnitsPlain($bytes) : '-',
            ];
        }

        // Per-job rollup
        $jobId = (string) ($r->job_id ?? '');
        if ($jobId !== '') {
            if (!isset($agent['_jobs'][$jobId])) {
                $agent['_jobs'][$jobId] = ['job_id' => $jobId, 'name' => (string) ($r->job_name ?? ''), 'days' => []];
            }
            $cur = $agent['_jobs'][$jobId]['days'][$dayKey] ?? null;
            $agent['_jobs'][$jobId]['days'][$dayKey] = $worse($cur, $status);
        }

        // Last-24h summary
        if ($startedTs >= $cutoff24h) {
            $bucket = isset($agent['_last24h'][$status]) ? $status : null;
            if ($bucket === null) {
                if ($status === 'partial_success') { $bucket = 'warning'; }
                elseif ($status === 'starting' || $status === 'queued') { $bucket = 'running'; }
            }
            if ($bucket !== null) {
                $agent['_last24h'][$bucket]++;
            }
        }
        unset($agent);
    }

    // ── Build the ordered day list (oldest -> newest) ──
    $dayList = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $dayList[] = date('Y-m-d', strtotime('-' . $i . ' days'));
    }

    // ── Assemble output ──
    $agentsOut = [];
    foreach ($agentByUuid as $uuid => $agent) {
        $daysOut = [];
        foreach ($dayList as $d) {
            $cell = $agent['_days'][$d] ?? null;
            $daysOut[] = [
                'date' => $d,
                'label' => date('M j', strtotime($d)),
                'status' => $cell ? $cell['status'] : null,
                'count' => $cell ? $cell['count'] : 0,
                'runs' => $cell ? $cell['runs'] : [],
            ];
        }

        $jobsOut = [];
        foreach ($agent['_jobs'] as $job) {
            $jobDays = [];
            foreach ($dayList as $d) {
                $jobDays[] = [
                    'date' => $d,
                    'label' => date('M j', strtotime($d)),
                    'status' => $job['days'][$d] ?? null,
                ];
            }
            $jobsOut[] = [
                'job_id' => $job['job_id'],
                'name' => $job['name'],
                'days' => $jobDays,
            ];
        }

        $agentsOut[] = [
            'agent_uuid' => $agent['agent_uuid'],
            'hostname' => $agent['hostname'],
            'agent_os' => $agent['agent_os'],
            'is_online' => $agent['is_online'],
            'last24h' => $agent['_last24h'],
            'days' => $daysOut,
            'jobs' => $jobsOut,
        ];
    }

    (new JsonResponse([
        'status' => 'success',
        'days' => array_map(function ($d) {
            return ['date' => $d, 'label' => date('M j', strtotime($d))];
        }, $dayList),
        'agents' => $agentsOut,
        'agentTotal' => $agentTotal,
    ], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load backup history'], 500))->send();
}
exit;
