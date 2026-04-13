<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenantTable = MspController::getTenantTableName();
$tenantUsersTable = MspController::getTenantUsersTableName();

$schema = Capsule::schema();
$hasProgressPct = $schema->hasColumn('s3_cloudbackup_runs', 'progress_pct');
$hasRunType = $schema->hasColumn('s3_cloudbackup_runs', 'run_type');
$hasJobTenant = $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
$hasRunIdCol = $schema->hasColumn('s3_cloudbackup_runs', 'run_id');
$hasJobIdPk = $schema->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunAgentUuid = $schema->hasColumn('s3_cloudbackup_runs', 'agent_uuid');

// ── Agent counts (total, online, offline) ──
$agentCount = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->count();

$onlineThreshold = date('Y-m-d H:i:s', strtotime('-15 minutes'));
$onlineAgents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->where('last_seen_at', '>=', $onlineThreshold)
    ->count();
$offlineAgents = $agentCount - $onlineAgents;

// Recent agents for health panel (last 5)
$recentAgents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->orderByDesc('last_seen_at')
    ->limit(5)
    ->get(['hostname', 'agent_uuid', 'agent_os', 'agent_version', 'last_seen_at']);
if (is_object($recentAgents) && method_exists($recentAgents, 'map')) {
    $recentAgents = $recentAgents->map(function ($row) use ($onlineThreshold) {
        $r = (array) $row;
        $r['is_online'] = !empty($r['last_seen_at']) && $r['last_seen_at'] >= $onlineThreshold;
        return $r;
    })->toArray();
}

// ── Tenant counts (MSP only) ──
$tenantCount = 0;
$tenantUserCount = 0;
if ($isMspClient) {
    $tenantCountQuery = Capsule::table($tenantTable . ' as t')
        ->where('t.status', '!=', 'deleted');
    MspController::scopeTenantOwnership($tenantCountQuery, 't', (int)$loggedInUserId);
    $tenantCount = $tenantCountQuery->count();

    $tenantUserCountQuery = Capsule::table($tenantUsersTable . ' as tu')
        ->join($tenantTable . ' as t', 'tu.tenant_id', '=', 't.id')
        ->where('tu.status', 'active');
    MspController::scopeTenantOwnership($tenantUserCountQuery, 't', (int)$loggedInUserId);
    $tenantUserCount = $tenantUserCountQuery->count();
}

// ── Enrollment token count ──
$tokenCount = Capsule::table('s3_agent_enrollment_tokens')
    ->where('client_id', $loggedInUserId)
    ->whereNull('revoked_at')
    ->where(function ($q) {
        $q->whereNull('expires_at')
          ->orWhere('expires_at', '>', Capsule::raw('NOW()'));
    })
    ->count();

// ── Active job count ──
$activeJobCount = 0;
try {
    $jobQuery = Capsule::table('s3_cloudbackup_jobs')
        ->where('client_id', $loggedInUserId)
        ->where('status', 'active');
    $activeJobCount = $jobQuery->count();
} catch (\Exception $e) {
    $activeJobCount = 0;
}

// ── Backup user count ──
$userCount = 0;
try {
    $userCount = Capsule::table('s3_backup_users')
        ->where('client_id', $loggedInUserId)
        ->count();
} catch (\Exception $e) {
    $userCount = 0;
}

// ── 24h job status rollup ──
$status24h = ['success' => 0, 'warning' => 0, 'failed' => 0, 'running' => 0, 'cancelled' => 0];
try {
    $cutoff24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $jobRunJoin24h = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];
    $rows24h = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', $jobRunJoin24h[0], $jobRunJoin24h[1], $jobRunJoin24h[2])
        ->where('j.client_id', $loggedInUserId)
        ->where('r.started_at', '>=', $cutoff24h)
        ->groupBy('r.status')
        ->get([Capsule::raw('r.status'), Capsule::raw('COUNT(*) as cnt')]);
    foreach ($rows24h as $row) {
        $s = strtolower($row->status ?? '');
        if (isset($status24h[$s])) {
            $status24h[$s] = (int) $row->cnt;
        } elseif ($s === 'starting' || $s === 'queued') {
            $status24h['running'] += (int) $row->cnt;
        }
    }
} catch (\Exception $e) {
    // leave defaults
}
$status24hTotal = array_sum($status24h);

// ── Storage used (sum of bytes_processed from latest successful run per job) ──
$storageBytes = 0;
try {
    $runIdRef = $hasRunIdCol ? 'r.run_id' : 'r.id';
    $jobIdRef = $hasJobIdPk ? 'j.job_id' : 'j.id';
    $jobRunJoinStorage = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];

    $latestRuns = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', $jobRunJoinStorage[0], $jobRunJoinStorage[1], $jobRunJoinStorage[2])
        ->where('j.client_id', $loggedInUserId)
        ->where('r.status', 'success')
        ->groupBy('r.job_id')
        ->get([
            'r.job_id',
            Capsule::raw('MAX(r.started_at) as latest_started'),
        ]);

    foreach ($latestRuns as $lr) {
        $jobRunJoinS = $hasJobIdPk ? ['r2.job_id', '=', 'j2.job_id'] : ['r2.job_id', '=', 'j2.id'];
        $row = Capsule::table('s3_cloudbackup_runs as r2')
            ->join('s3_cloudbackup_jobs as j2', $jobRunJoinS[0], $jobRunJoinS[1], $jobRunJoinS[2])
            ->where('j2.client_id', $loggedInUserId)
            ->where('r2.job_id', $lr->job_id)
            ->where('r2.status', 'success')
            ->where('r2.started_at', $lr->latest_started)
            ->first([Capsule::raw('COALESCE(r2.bytes_processed, r2.bytes_transferred, 0) as size_bytes')]);
        if ($row) {
            $storageBytes += (int) $row->size_bytes;
        }
    }
} catch (\Exception $e) {
    $storageBytes = 0;
}
$storageFormatted = HelperController::formatSizeUnitsPlain($storageBytes);

// ── Live (running/queued) jobs ──
$liveSelect = $hasRunIdCol && $hasJobIdPk ? [
    Capsule::raw('BIN_TO_UUID(r.run_id) as run_id'),
    'r.status',
    'r.started_at',
    'r.bytes_processed',
    'r.bytes_transferred',
    Capsule::raw('BIN_TO_UUID(j.job_id) as job_id'),
    'j.name as job_name',
    'j.engine',
    'a.hostname as agent_hostname',
] : [
    'r.id as run_id',
    'r.run_uuid',
    'r.status',
    'r.started_at',
    'r.bytes_processed',
    'r.bytes_transferred',
    'j.id as job_id',
    'j.name as job_name',
    'j.engine',
    'a.hostname as agent_hostname',
];
if ($hasJobTenant) {
    $liveSelect[] = 'j.tenant_id';
    $liveSelect[] = 't.name as tenant_name';
} else {
    $liveSelect[] = Capsule::raw('NULL as tenant_id');
    $liveSelect[] = Capsule::raw('NULL as tenant_name');
}
$liveSelect[] = $hasProgressPct ? 'r.progress_pct' : Capsule::raw('NULL as progress_pct');
$liveSelect[] = Capsule::raw('NULL as run_type');

$jobRunJoin = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];
$liveRunsQuery = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
    ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid');

if ($hasJobTenant) {
    $liveRunsQuery->leftJoin($tenantTable . ' as t', 'j.tenant_id', '=', 't.id');
}

$orderCol = $hasRunIdCol ? 'r.started_at' : 'r.id';
$liveRuns = $liveRunsQuery
    ->where('j.client_id', $loggedInUserId)
    ->whereIn('r.status', ['running', 'starting', 'queued'])
    ->whereNull('r.finished_at')
    ->orderByDesc($orderCol)
    ->limit(8)
    ->get($liveSelect);

if (is_object($liveRuns) && method_exists($liveRuns, 'map')) {
    $liveRuns = $liveRuns->map(function ($row) {
        return (array) $row;
    })->toArray();
}

// ── Recent completed runs (last 20) ──
$recentSelect = $hasRunIdCol && $hasJobIdPk ? [
    Capsule::raw('BIN_TO_UUID(r.run_id) as run_id'),
    'r.status',
    'r.started_at',
    'r.finished_at',
    'r.bytes_processed',
    'r.bytes_transferred',
    Capsule::raw('BIN_TO_UUID(j.job_id) as job_id'),
    'j.name as job_name',
    'j.engine',
    'a.hostname as agent_hostname',
] : [
    'r.id as run_id',
    'r.status',
    'r.started_at',
    'r.finished_at',
    'r.bytes_processed',
    'r.bytes_transferred',
    'j.id as job_id',
    'j.name as job_name',
    'j.engine',
    'a.hostname as agent_hostname',
];
if ($hasJobTenant) {
    $recentSelect[] = 'j.tenant_id';
    $recentSelect[] = 't.name as tenant_name';
} else {
    $recentSelect[] = Capsule::raw('NULL as tenant_id');
    $recentSelect[] = Capsule::raw('NULL as tenant_name');
}

$recentRunsQuery = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
    ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid');
if ($hasJobTenant) {
    $recentRunsQuery->leftJoin($tenantTable . ' as t', 'j.tenant_id', '=', 't.id');
}
$recentRuns = $recentRunsQuery
    ->where('j.client_id', $loggedInUserId)
    ->whereNotNull('r.finished_at')
    ->orderByDesc('r.finished_at')
    ->limit(20)
    ->get($recentSelect);

if (is_object($recentRuns) && method_exists($recentRuns, 'map')) {
    $recentRuns = $recentRuns->map(function ($row) {
        $r = (array) $row;
        if (!empty($r['started_at']) && !empty($r['finished_at'])) {
            $start = strtotime($r['started_at']);
            $end = strtotime($r['finished_at']);
            $diff = max(0, $end - $start);
            if ($diff >= 3600) {
                $r['duration'] = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
            } elseif ($diff >= 60) {
                $r['duration'] = floor($diff / 60) . 'm ' . ($diff % 60) . 's';
            } else {
                $r['duration'] = $diff . 's';
            }
        } else {
            $r['duration'] = '-';
        }
        $bytes = max((int)($r['bytes_processed'] ?? 0), (int)($r['bytes_transferred'] ?? 0));
        $r['size_formatted'] = $bytes > 0 ? HelperController::formatSizeUnitsPlain($bytes) : '-';
        return $r;
    })->toArray();
}

return [
    'isMspClient'       => $isMspClient,
    'agentCount'        => $agentCount,
    'onlineAgents'      => $onlineAgents,
    'offlineAgents'     => $offlineAgents,
    'recentAgents'      => $recentAgents,
    'tenantCount'       => $tenantCount,
    'tenantUserCount'   => $tenantUserCount,
    'tokenCount'        => $tokenCount,
    'activeJobCount'    => $activeJobCount,
    'userCount'         => $userCount,
    'status24h'         => $status24h,
    'status24hTotal'    => $status24hTotal,
    'storageBytes'      => $storageBytes,
    'storageFormatted'  => $storageFormatted,
    'liveRuns'          => $liveRuns,
    'recentRuns'        => $recentRuns,
];
