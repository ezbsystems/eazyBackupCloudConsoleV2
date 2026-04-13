<?php

require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';

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

$userIdRaw = trim((string) ($_GET['user_id'] ?? ''));
$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
if ($userIdRaw === '') {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($loggedInUserId);
$tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int)($mspId ?? 0) : (int)$loggedInUserId;
$tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
$tenantPublicIdSelect = ($tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds())
    ? Capsule::raw('t.public_id as tenant_public_id')
    : Capsule::raw('u.tenant_id as tenant_public_id');

$selectCols = array_merge([
    'u.id',
    'u.client_id',
    'u.tenant_id as storage_tenant_id',
    $tenantPublicIdSelect,
    'u.username',
    'u.email',
    'u.status',
    'u.created_at',
    'u.updated_at',
    't.name as tenant_name',
    Capsule::raw($tenantOwnerSelect),
    't.status as tenant_status',
], Capsule::schema()->hasColumn('s3_backup_users', 'backup_type') ? ['u.backup_type'] : [],
   $hasPublicIdCol ? ['u.public_id'] : []);

$userLookup = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.client_id', $loggedInUserId);
if ($hasPublicIdCol && !ctype_digit($userIdRaw)) {
    $userLookup->where('u.public_id', $userIdRaw);
} else {
    $userLookup->where('u.id', (int) $userIdRaw);
}
$user = $userLookup->select($selectCols)->first();

if (!$user) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}
$userId = (int) $user->id;

if (!$isMspClient && !empty($user->storage_tenant_id)) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

if ($isMspClient && !empty($user->storage_tenant_id)) {
    $tenantClientId = (int) ($user->tenant_owner_id ?? 0);
    $tenantStatus = strtolower((string) ($user->tenant_status ?? ''));
    if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
        header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
        exit;
    }
}

$canonicalTenants = [];
if ($isMspClient) {
    $rows = Capsule::table('eb_whitelabel_tenants')
        ->where('client_id', $loggedInUserId)
        ->whereNotIn('status', ['deleted', 'removing'])
        ->orderBy('subdomain', 'asc')
        ->orderBy('id', 'asc')
        ->get([
            'id',
            'public_id',
            'subdomain',
            'fqdn',
            'status',
        ]);
    foreach ($rows as $row) {
        if (!eb_tenant_storage_links_is_assignable_tenant_status((string) ($row->status ?? ''))) {
            continue;
        }
        $name = trim((string) ($row->subdomain ?? ''));
        if ($name === '') {
            $name = trim((string) ($row->fqdn ?? ''));
        }
        if ($name === '') {
            $name = 'Tenant';
        }
        $publicId = trim((string) ($row->public_id ?? ''));
        if ($publicId === '') {
            continue;
        }
        $canonicalTenants[] = [
            'id' => $publicId,
            'public_id' => $publicId,
            'name' => $name,
            'subdomain' => (string) ($row->subdomain ?? ''),
            'fqdn' => (string) ($row->fqdn ?? ''),
            'status' => (string) ($row->status ?? ''),
        ];
    }
}
$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

$username = $product->username;
$s3AccountUser = DBController::getUser($username);

$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

$storageTenantIdForScope = $user->storage_tenant_id !== null ? (int) $user->storage_tenant_id : null;
$hasAgentBackupUserIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');

$agentQuery = Capsule::table('s3_cloudbackup_agents as a')
    ->where('a.client_id', $loggedInUserId)
    ->where('a.status', 'active')
    ->orderBy('a.hostname');

if ($hasAgentBackupUserIdCol) {
    $agentQuery->where('a.backup_user_id', $userId);
} else {
    if ($storageTenantIdForScope === null) {
        $agentQuery->whereNull('a.tenant_id');
    } else {
        $agentQuery->where('a.tenant_id', (int) $storageTenantIdForScope);
    }
}

$agentSelect = [
    'a.agent_uuid',
    'a.hostname',
    'a.device_name',
    'a.tenant_id',
    'a.status',
    'a.last_seen_at',
    Capsule::raw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) as seconds_since_seen'),
];

if ($isMspClient && $tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds()) {
    $agentQuery->leftJoin('eb_tenants as t', function ($join) use ($loggedInUserId) {
        $join->on('a.tenant_id', '=', 't.id');
        $mspId = MspController::getMspIdForClient((int) $loggedInUserId);
        $join->where('t.msp_id', '=', (int) ($mspId ?? 0));
    });
    $agentSelect[3] = Capsule::raw('t.public_id as tenant_id');
}

$agents = $agentQuery->get($agentSelect);

$onlineThresholdSeconds = 180;
try {
    $configuredThreshold = (int) Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'cloudbackup_agent_online_threshold_seconds')
        ->value('value');
    if ($configuredThreshold > 0) {
        $onlineThresholdSeconds = $configuredThreshold;
    }
} catch (\Throwable $e) {
}

foreach ($agents as $agent) {
    $lastSeenAt = trim((string) ($agent->last_seen_at ?? ''));
    $secondsSinceSeen = isset($agent->seconds_since_seen) ? (int) $agent->seconds_since_seen : null;
    if ($lastSeenAt === '') {
        $agent->online_status = 'never';
        continue;
    }
    $agent->online_status = ($secondsSinceSeen !== null && $secondsSinceSeen <= $onlineThresholdSeconds) ? 'online' : 'offline';
}

$s3Tenants = DBController::getResult('s3_users', [
    ['parent_id', '=', $s3AccountUser->id],
], [
    'id', 'username',
])->pluck('username', 'id')->toArray();

$s3Tenants[$s3AccountUser->id] = $username;
$bucketUserIds = array_keys($s3Tenants);
$buckets = DBController::getUserBuckets($bucketUserIds);
$initialRestoreJobId = trim((string) ($_GET['restore_job_id'] ?? ''));

return [
    'isMspClient' => $isMspClient,
    'csrfToken' => $csrfToken,
    'token' => $csrfToken,
    'canonicalTenants' => $canonicalTenants,
    'user' => $user,
    'tenants' => $tenants,
    'agents' => $agents,
    'buckets' => $buckets,
    'client_id' => $loggedInUserId,
    's3_user_id' => $s3AccountUser->id,
    'backup_user_id' => $user->id,
    'backup_user_public_id' => (string) ($user->public_id ?? ''),
    'initial_restore_job_id' => $initialRestoreJobId,
    'usernames' => $s3Tenants,
];
