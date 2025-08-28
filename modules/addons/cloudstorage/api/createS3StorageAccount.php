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

$userId = $ca->getUserID();
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

    if ($user['status'] != 'success') {
        $jsonData = [
            'message' => $user['message'],
            'status' => 'fail',
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

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

    $jsonData = [
        'status' => 'success',
        'message' => 'S3 storage account has been created successfully.'
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