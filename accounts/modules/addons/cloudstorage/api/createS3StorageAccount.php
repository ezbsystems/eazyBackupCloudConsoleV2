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
$result = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $username);
try { logModuleCall('cloudstorage', 'adminops_get_user_info', ['username' => $username], $result); } catch (\Throwable $e) {}

if ($result['status'] == 'success') {
    $jsonData = [
        'message' => 'The username already exists in the Ceph RGW. Please choose a different username.',
        'status' => 'fail',
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

try {
    $tenantId = HelperController::getUniqueTenantId();
    $params = [
        'uid'  => $username,
        'name' => $username,
        'tenant' => $tenantId
    ];

    $user = AdminOps::createUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
    try { logModuleCall('cloudstorage', 'adminops_create_user', ['params' => $params], $user); } catch (\Throwable $e) {}

    if ($user['status'] != 'success') {
        try { logModuleCall('cloudstorage', 'adminops_create_user_fail', ['params' => $params], $user); } catch (\Throwable $e) {}
        $jsonData = [
            'message' => $user['message'],
            'status' => 'fail',
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    // If a hosting record already exists for this client+package (from order flow), update it; else create it
    $existing = DBController::getRow('tblhosting', [
        ['userid', '=', $userId],
        ['packageid', '=', $packageId],
    ], ['id','server','username','domainstatus'], 'id', 'DESC');
    if ($existing && isset($existing->id)) {
        try { logModuleCall('cloudstorage', 'hosting_update', ['id' => (int)$existing->id, 'userId' => $userId, 'packageId' => $packageId], ['server' => $existing->server, 'username' => $existing->username, 'domainstatus' => $existing->domainstatus]); } catch (\Throwable $e) {}
        $update = [
            'username' => $username,
            'domainstatus' => 'Active',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($existing->server)) {
            $update['server'] = $serverId;
        }
        DBController::updateRecord('tblhosting', $update, [ ['id', '=', (int)$existing->id] ]);
        $hostingId = (int) $existing->id;
    } else {
        try { logModuleCall('cloudstorage', 'hosting_insert', ['userId' => $userId, 'packageId' => $packageId], []); } catch (\Throwable $e) {}
        $hostingId = DBController::insertGetId('tblhosting', [
            'userid' => $userId,
            'packageid' => $packageId,
            'server' => $serverId,
            'username' => $username,
            'regdate' => date('Y-m-d'),
            'nextduedate' => date('Y-m-d', strtotime('+1 month')),
            'billingcycle' => $billingCycle,
            'domainstatus' => 'Active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    $s3UserId = DBController::saveUser([
        'username' => $username,
        'tenant_id' => $tenantId
    ]);

    $accessKey = HelperController::encryptKey($user['data']['keys'][0]['access_key'], $encryptionKey);
    $secretKey = HelperController::encryptKey($user['data']['keys'][0]['secret_key'], $encryptionKey);

    DBController::insertRecord('s3_user_access_keys', [
        'user_id' => $s3UserId,
        'access_key' => $accessKey,
        'secret_key' => $secretKey
    ]);
    try { logModuleCall('cloudstorage', 'adminops_create_keys_store', ['s3_user_id' => $s3UserId], ['access_key_len' => strlen($accessKey), 'secret_key_len' => strlen($secretKey)]); } catch (\Throwable $e) {}

    $jsonData = [
        'status' => 'success',
        'message' => 'e3 storage account has been created successfully.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();

} catch (Exception $e) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'An error occurred while creating the account. Please try again later.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}