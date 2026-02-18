<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Admin\Tenant;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit();
}

$action = (string)($_POST['action'] ?? '');
if (!in_array($action, ['create', 'delete'], true)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bad request.'], 200))->send();
    exit();
}

// Resolve the parent storage account user from the product username
$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Something went wrong.'], 200))->send();
    exit();
}

$parentUsername = (string)$product->username;
$parentUser = DBController::getUser($parentUsername);
if (is_null($parentUser)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Your account has been suspended. Please contact support.'], 200))->send();
    exit();
}

$targetUsername = trim((string)($_POST['username'] ?? ''));
if ($targetUsername !== '') {
    $targetUser = Capsule::table('s3_users')
        ->where('username', $targetUsername)
        ->where('parent_id', (int)$parentUser->id)
        ->first(['id', 'is_system_managed', 'manage_locked']);
    if ($targetUser && (!empty($targetUser->manage_locked) || !empty($targetUser->is_system_managed))) {
        (new JsonResponse([
            'status' => 'fail',
            'message' => 'This user is system managed and cannot be modified.'
        ], 200))->send();
        exit();
    }
}

// Basic input validation
if ($action === 'create') {
    $tenantUsername = trim((string)($_POST['username'] ?? ''));
    $permission = trim((string)($_POST['permission'] ?? ''));
    $description = (string)($_POST['description'] ?? '');
    if ($tenantUsername === '' || !in_array($permission, ['read', 'write', 'readwrite', 'full'], true)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Missing or invalid parameters.'], 200))->send();
        exit();
    }
    // Normalize back into request array expected by Tenant::createTenantAccessKey
    $req = [
        'username' => $tenantUsername,
        'permission' => $permission,
        'description' => $description,
    ];
    $result = Tenant::createTenantAccessKey($req, $parentUser);
} else { // delete
    $tenantUsername = trim((string)($_POST['username'] ?? ''));
    $keyId = (int)($_POST['key_id'] ?? 0);
    if ($tenantUsername === '' || $keyId <= 0) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Missing or invalid parameters.'], 200))->send();
        exit();
    }
    $req = [
        'username' => $tenantUsername,
        'key_id' => $keyId,
    ];
    $result = Tenant::deleteTenantAccessKey($req, (int)$parentUser->id);
}

(new JsonResponse($result, 200))->send();
exit();


