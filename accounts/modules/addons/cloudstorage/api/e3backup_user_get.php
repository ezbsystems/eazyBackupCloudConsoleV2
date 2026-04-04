<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

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
$userId = (int) ($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    userGetFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

$user = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('u.client_id', $clientId)
    ->select([
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
    ])
    ->first();

if (!$user) {
    userGetFail('User not found.', 404);
}

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

if (Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
    $agentQuery = Capsule::table('s3_cloudbackup_agents as a')
        ->where('a.client_id', $clientId);
    applyTenantScope($agentQuery, 'a.tenant_id', $storageTenantId);
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
    $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')
        ->leftJoin('s3_cloudbackup_agents as a', $hasAgentUuid ? 'j.agent_uuid' : 'j.agent_id', '=', $hasAgentUuid ? 'a.agent_uuid' : 'a.id')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted');
    applyTenantScope($jobQuery, 'a.tenant_id', $storageTenantId);
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
    $hasJobIdPkRuns = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $runJobJoin = $hasJobIdPkRuns ? 'j.job_id' : 'j.id';
    $lastBackupQuery = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', $runJobJoin)
        ->leftJoin('s3_cloudbackup_agents as a', $hasAgentUuid ? 'j.agent_uuid' : 'j.agent_id', '=', $hasAgentUuid ? 'a.agent_uuid' : 'a.id')
        ->where('j.client_id', $clientId)
        ->whereNotNull('r.finished_at')
        ->whereIn('r.status', ['success', 'warning']);
    applyTenantScope($lastBackupQuery, 'a.tenant_id', $storageTenantId);
    $lastBackup = $lastBackupQuery->max('r.finished_at');
    if (!empty($lastBackup)) {
        $metrics['last_backup_at'] = $lastBackup;
    }
}

(new JsonResponse([
    'status' => 'success',
    'user' => [
        'id' => (int) $user->id,
        'client_id' => (int) $user->client_id,
        'tenant_id' => $tenantPublicId,
        'tenant_public_id' => $tenantPublicId,
        'is_canonical_managed' => $isCanonicalManaged,
        'canonical_tenant_id' => $canonicalTenantPublicId,
        'canonical_tenant_public_id' => $canonicalTenantPublicId,
        'username' => (string) $user->username,
        'email' => (string) $user->email,
        'status' => (string) $user->status,
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
    ],
], 200))->send();
exit;

