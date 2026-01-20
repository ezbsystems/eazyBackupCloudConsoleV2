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

// check the username is unique
$user = DBController::getRow('s3_users', [['username', '=', $username]]);
if (!is_null($user)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Please choose a different username.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

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
        'serviceusername' => $username,
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

    $tenantId = HelperController::getUniqueTenantId();
    $cephUid = HelperController::generateCephUserId($username, $tenantId);
    $params = [
        'uid'    => $cephUid,
        'name'   => $username,
        'email'  => $username,
        'tenant' => $tenantId
    ];

    $user = AdminOps::createUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
    try { logModuleCall('cloudstorage', 'adminops_create_user', ['params' => $params], $user); } catch (\Throwable $e) {}
    if ($user['status'] != 'success') {
        try { logModuleCall('cloudstorage', 'adminops_create_user_fail', ['params' => $params], $user); } catch (\Throwable $e) {}
        throw new \Exception($user['message'] ?? 'Admin Ops user creation failed.');
    }

    $s3UserId = DBController::saveUser([
        'username'  => $username,
        'ceph_uid'  => $cephUid,
        'tenant_id' => $tenantId
    ]);

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
