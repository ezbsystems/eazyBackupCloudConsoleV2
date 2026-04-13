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

$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

$username = $product->username;
$user = DBController::getUser($username);

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenantTable = MspController::getTenantTableName();

// Tenants for MSP filter
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

// Agents for agent filter (client-scoped; tenant filtering handled in UI).
$agentQuery = Capsule::table('s3_cloudbackup_agents as a')
    ->where('a.client_id', $loggedInUserId)
    ->where('a.status', 'active')
    ->orderBy('a.hostname');

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

// Get user tenants (s3_users) for bucket access
$s3Tenants = DBController::getResult('s3_users', [
    ['parent_id', '=', $user->id]
], [
    'id', 'username'
])->pluck('username', 'id')->toArray();

$s3Tenants[$user->id] = $username;
$bucketUserIds = array_keys($s3Tenants);
$buckets = DBController::getUserBuckets($bucketUserIds);

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'agents' => $agents,
    'buckets' => $buckets,
    'token' => $csrfToken,
    's3_user_id' => $user->id,
    'client_id' => $loggedInUserId,
    'usernames' => $s3Tenants, // For inline bucket creation
];
