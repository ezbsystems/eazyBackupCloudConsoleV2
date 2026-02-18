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

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

$isMspClient = MspController::isMspClient($loggedInUserId);

$user = Capsule::table('s3_backup_users as u')
    ->leftJoin('s3_backup_tenants as t', 'u.tenant_id', '=', 't.id')
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
        't.client_id as tenant_client_id',
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
    $tenantClientId = (int) ($user->tenant_client_id ?? 0);
    $tenantStatus = strtolower((string) ($user->tenant_status ?? ''));
    if ($tenantClientId !== (int) $loggedInUserId || $tenantStatus === 'deleted') {
        header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
        exit;
    }
}

$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'user' => $user,
];

