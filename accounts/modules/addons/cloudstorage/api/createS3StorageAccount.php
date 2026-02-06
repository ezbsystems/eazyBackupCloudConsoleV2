<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Session has been expired.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$userIdV8 = (int) $ca->getUserID();
// Resolve client ID from user link (owner preferred)
$clientId = 0;
try {
    $link = \WHMCS\Database\Capsule::table('tblusers_clients')->where('userid', $userIdV8)->orderBy('owner', 'desc')->first();
    if ($link && isset($link->clientid)) {
        $clientId = (int) $link->clientid;
    }
} catch (\Throwable $e) {}
if ($clientId <= 0 && isset($_SESSION['uid'])) {
    $clientId = (int) $_SESSION['uid'];
}
$userId = $clientId;
$packageId = ProductConfig::$E3_PRODUCT_ID;
$serverId = 5;
$accessKeyFieldId = 54;
$secretKeyFieldId = 55;
$billingCycle = 'Monthly';
$username = $_POST['username'];

// Derive the storage username from the client's email with '@' and '.' stripped.
// This ensures the Ceph RGW uid and WHMCS product username are clean and consistent.
// Example: newuser@mycompany.com → newusermycompanycom
$clientEmail = '';
try {
    $clientEmail = (string) \WHMCS\Database\Capsule::table('tblclients')->where('id', $userId)->value('email');
} catch (\Throwable $e) {}
if ($clientEmail !== '') {
    $username = HelperController::sanitizeEmailForUsername($clientEmail);
}
if ($username === '') {
    $username = preg_replace('/[^a-z0-9-]+/', '', strtolower((string)$_POST['username']));
}
if ($username === '') {
    $username = 'e3user' . $userId;
}

// Check for existing s3_users record (active or inactive) by ceph_uid or base username.
// This catches stale records left behind after a cancel→delete cycle.
$existingS3User = null;
try {
    $existingS3User = \WHMCS\Database\Capsule::table('s3_users')
        ->where(function ($q) use ($username) {
            $q->where('ceph_uid', $username)
              ->orWhere('username', $username);
        })
        ->first();
} catch (\Throwable $e) {}
if (!is_null($existingS3User) && ($existingS3User->is_active ?? 1) == 1) {
    // An active user already exists with this username — block signup.
    $jsonData = [
        'status' => 'fail',
        'message' => 'Please choose a different username.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}
// If $existingS3User is set but inactive, we will reactivate it below after Ceph user creation.

$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    $jsonData = [
        'message' => 'Cloud Storage service error. Please contact technical support for assistance.',
        'status' => 'fail',
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}
$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
// NOTE: We store the customer-visible username/email in s3_users.username,
// but we use a RGW-safe uid (no '@') for Ceph Admin Ops.

$adminUser = 'API';
try {
    $order = localAPI('AddOrder', [
        'clientid'     => $userId,
        'pid'          => [$packageId],
        'billingcycle' => ['Monthly'],
        'paymentmethod'=> 'stripe',
        'noinvoice'    => true,
        'noemail'      => true,
    ], $adminUser);
    try { logModuleCall('cloudstorage', 'create_s3_addorder', ['clientId' => $userId], $order); } catch (\Throwable $_) {}
    if (($order['result'] ?? '') !== 'success') {
        throw new \Exception('AddOrder failed: ' . ($order['message'] ?? 'unknown'));
    }

$accept = localAPI('AcceptOrder', [
        'orderid'         => $order['orderid'],
        'autosetup'       => true,
        'sendemail'       => true,
    ], $adminUser);
    try { logModuleCall('cloudstorage', 'create_s3_acceptorder', ['orderId' => $order['orderid']], $accept); } catch (\Throwable $_) {}
    if (($accept['result'] ?? '') !== 'success') {
        throw new \Exception('AcceptOrder failed: ' . ($accept['message'] ?? 'unknown'));
    }

    $serviceId = (int) ($accept['serviceid'] ?? 0);
    $serviceRecord = null;
    if ($serviceId > 0) {
        $serviceRecord = DBController::getRow('tblhosting', [['id', '=', $serviceId]], ['id', 'server']);
    }
    if ($serviceId <= 0 && !empty($order['orderid'])) {
        $serviceRecord = DBController::getRow('tblhosting', [['orderid', '=', (int)$order['orderid']]], ['id', 'server']);
        if ($serviceRecord && isset($serviceRecord->id)) {
            $serviceId = (int) $serviceRecord->id;
        }
    }
    if ($serviceId <= 0 || !$serviceRecord || !isset($serviceRecord->id)) {
        throw new \Exception('Failed to resolve hosting service for order ' . ($order['orderid'] ?? 'unknown'));
    }

    $trialDue = date('Y-m-d', strtotime('+30 days'));
    $update = [
        'domainstatus'    => 'Active',
        'nextduedate'     => $trialDue,
        'nextinvoicedate' => $trialDue,
        'updated_at'      => date('Y-m-d H:i:s'),
    ];
    if (empty($serviceRecord->server)) {
        $update['server'] = $serverId;
    }
    try { logModuleCall('cloudstorage', 'create_s3_hosting_update', ['serviceId' => $serviceId], $update); } catch (\Throwable $_) {}
    DBController::updateRecord('tblhosting', $update, [['id', '=', $serviceId]]);

    // Reuse tenant_id from an existing (inactive) s3_users record when possible,
    // so the customer keeps a consistent identity across cancel→re-signup cycles.
    if ($existingS3User && !empty($existingS3User->tenant_id)) {
        $tenantId = (string) $existingS3User->tenant_id;
    } else {
        $tenantId = HelperController::getUniqueTenantId();
    }
    // $username is already sanitized (email with '@' and '.' stripped) from the top of this file.
    $baseUsername = $username;
    if ($baseUsername === '') {
        $baseUsername = HelperController::generateCephUserId($_POST['username'] ?? '', $tenantId);
    }

    // Final safety net: ensure '@' and '.' are stripped before any Ceph or DB writes.
    $baseUsername = HelperController::sanitizeEmailForUsername($baseUsername);
    if ($baseUsername === '') {
        $baseUsername = 'e3user' . $userId;
    }

    $serviceUsername = !empty($tenantId) ? ($tenantId . '$' . $baseUsername) : $baseUsername;
    // Ensure WHMCS service username matches RGW (tenant-qualified uid).
    try {
        $upd = localAPI('UpdateClientProduct', [
            'serviceid'       => $serviceId,
            'serviceusername' => $serviceUsername,
        ], $adminUser);
        try { logModuleCall('cloudstorage', 'create_s3_update_service_username', ['serviceId' => $serviceId, 'username' => $serviceUsername], $upd); } catch (\Throwable $_) {}
    } catch (\Throwable $e) {
        try { logModuleCall('cloudstorage', 'create_s3_update_service_username_fail', ['serviceId' => $serviceId, 'username' => $serviceUsername], $e->getMessage()); } catch (\Throwable $_) {}
    }
    DBController::updateRecord('tblhosting', ['username' => $serviceUsername], [['id', '=', $serviceId]]);

    $cephUid = $baseUsername;
    $params = [
        'uid'    => $cephUid,
        'name'   => $serviceUsername,
        'email'  => $serviceUsername,
        'tenant' => $tenantId
    ];

    $user = AdminOps::createUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
    try { logModuleCall('cloudstorage', 'adminops_create_user', ['params' => $params], $user); } catch (\Throwable $e) {}
    if ($user['status'] != 'success') {
        try { logModuleCall('cloudstorage', 'adminops_create_user_fail', ['params' => $params], $user); } catch (\Throwable $e) {}
        throw new \Exception($user['message'] ?? 'Admin Ops user creation failed.');
    }

    if ($existingS3User && isset($existingS3User->id)) {
        // Re-provisioning: reactivate the existing s3_users row and purge stale keys.
        $s3UserId = (int) $existingS3User->id;
        \WHMCS\Database\Capsule::table('s3_users')->where('id', $s3UserId)->update([
            'username'   => $serviceUsername,
            'ceph_uid'   => $cephUid,
            'tenant_id'  => $tenantId,
            'is_active'  => 1,
            'deleted_at' => null,
        ]);
        try {
            \WHMCS\Database\Capsule::table('s3_user_access_keys')->where('user_id', $s3UserId)->delete();
        } catch (\Throwable $_) {}
        try { logModuleCall('cloudstorage', 'create_s3_reactivate_user', ['id' => $s3UserId, 'username' => $serviceUsername], 'Reactivated existing s3_users row and purged stale keys'); } catch (\Throwable $_) {}
    } else {
        // Brand-new user — insert a fresh row.
        $s3UserId = DBController::saveUser([
            'username'  => $serviceUsername,
            'ceph_uid'  => $cephUid,
            'tenant_id' => $tenantId
        ]);
    }

    $accessKey = HelperController::encryptKey($user['data']['keys'][0]['access_key'], $encryptionKey);
    $secretKey = HelperController::encryptKey($user['data']['keys'][0]['secret_key'], $encryptionKey);

    // Store non-secret hint for UI display without requiring decrypt
    $akPlain = $user['data']['keys'][0]['access_key'] ?? '';
    $hint = (is_string($akPlain) && strlen($akPlain) > 0)
        ? ((strlen($akPlain) <= 8) ? $akPlain : (substr($akPlain, 0, 4) . '…' . substr($akPlain, -4)))
        : null;
    DBController::insertRecord('s3_user_access_keys', [
        'user_id'        => $s3UserId,
        'access_key'     => $accessKey,
        'secret_key'     => $secretKey,
        'access_key_hint'=> $hint
    ]);
    try { logModuleCall('cloudstorage', 'adminops_create_keys_store', ['s3_user_id' => $s3UserId], ['access_key_len' => strlen($accessKey), 'secret_key_len' => strlen($secretKey)]); } catch (\Throwable $e) {}

    $jsonData = [
        'status' => 'success',
        'message'=> 'e3 storage account has been created successfully.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();

} catch (Exception $e) {
    try { logModuleCall('cloudstorage', 'create_s3_provision_error', ['clientId' => $clientId, 'username' => $username], $e->getMessage()); } catch (\Throwable $_) {}
    $jsonData = [
        'status' => 'fail',
        'message'=> 'An error occurred while creating the account. Please try again later.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}
