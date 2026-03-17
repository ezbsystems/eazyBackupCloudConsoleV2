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
$tenantTable = MspController::getTenantTableName();

// Tenants for MSP filter
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

// Agents for filter.
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

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'agents' => $agents,
];
