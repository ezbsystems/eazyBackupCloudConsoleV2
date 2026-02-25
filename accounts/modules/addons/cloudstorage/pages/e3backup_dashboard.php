<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
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

// Get agent count
$agentCount = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->count();

// Get tenant count (MSP only)
$tenantCount = 0;
$tenantUserCount = 0;
if ($isMspClient) {
    $tenantCount = Capsule::table('s3_backup_tenants')
        ->where('client_id', $loggedInUserId)
        ->where('status', '!=', 'deleted')
        ->count();
    
    $tenantUserCount = Capsule::table('s3_backup_tenant_users as tu')
        ->join('s3_backup_tenants as t', 'tu.tenant_id', '=', 't.id')
        ->where('t.client_id', $loggedInUserId)
        ->where('tu.status', 'active')
        ->count();
}

// Get enrollment token count
$tokenCount = Capsule::table('s3_agent_enrollment_tokens')
    ->where('client_id', $loggedInUserId)
    ->whereNull('revoked_at')
    ->where(function ($q) {
        $q->whereNull('expires_at')
          ->orWhere('expires_at', '>', Capsule::raw('NOW()'));
    })
    ->count();

// Live (running/queued) jobs for dashboard
$schema = Capsule::schema();
$hasProgressPct = $schema->hasColumn('s3_cloudbackup_runs', 'progress_pct');
$hasRunType = $schema->hasColumn('s3_cloudbackup_runs', 'run_type');
$hasJobTenant = $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id');

$liveSelect = [
    'r.id',
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
// Optional columns with fallbacks to avoid SQL errors on older schemas
$liveSelect[] = $hasProgressPct ? 'r.progress_pct' : Capsule::raw('NULL as progress_pct');
// For dashboard we don't need run_type; always use NULL to avoid missing-column issues
$liveSelect[] = Capsule::raw('NULL as run_type');

$liveRunsQuery = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
    ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid');

if ($hasJobTenant) {
    $liveRunsQuery->leftJoin('s3_backup_tenants as t', 'j.tenant_id', '=', 't.id');
}

$liveRuns = $liveRunsQuery
    ->where('j.client_id', $loggedInUserId)
    ->whereIn('r.status', ['running', 'starting', 'queued'])
    ->whereNull('r.finished_at')
    ->orderByDesc('r.started_at')
    ->limit(8)
    ->get($liveSelect);

// Normalize to array-of-arrays for Smarty compatibility
if (is_object($liveRuns) && method_exists($liveRuns, 'map')) {
    $liveRuns = $liveRuns->map(function ($row) {
        return (array) $row;
    })->toArray();
}

return [
    'isMspClient' => $isMspClient,
    'agentCount' => $agentCount,
    'tenantCount' => $tenantCount,
    'tenantUserCount' => $tenantUserCount,
    'tokenCount' => $tokenCount,
    'liveRuns' => $liveRuns,
];

