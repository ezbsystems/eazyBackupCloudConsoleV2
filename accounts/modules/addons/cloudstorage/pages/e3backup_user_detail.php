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

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($loggedInUserId);
$tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int)($mspId ?? 0) : (int)$loggedInUserId;
$tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';

$user = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('u.client_id', $loggedInUserId)
    ->select([
        'u.id',
        'u.client_id',
        'u.tenant_id',
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
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

if (!$isMspClient && !empty($user->tenant_id)) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

if ($isMspClient && !empty($user->tenant_id)) {
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
            $name = 'Tenant #' . (int) $row->id;
        }
        $canonicalTenants[] = [
            'id' => (int) $row->id,
            'name' => $name,
            'subdomain' => (string) ($row->subdomain ?? ''),
            'fqdn' => (string) ($row->fqdn ?? ''),
            'status' => (string) ($row->status ?? ''),
        ];
    }
}
$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

return [
    'isMspClient' => $isMspClient,
    'csrfToken' => $csrfToken,
    'canonicalTenants' => $canonicalTenants,
    'user' => $user,
];

