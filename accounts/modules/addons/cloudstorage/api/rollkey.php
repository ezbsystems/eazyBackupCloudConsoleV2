<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Database\Capsule;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Session timeout.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    // Resolve client ID (WHMCS v8 user->client mapping)
    $loggedInUserId = (int) $ca->getUserID();
    $clientId = 0;
    try {
        $link = Capsule::table('tblusers_clients')->where('userid', $loggedInUserId)->orderBy('owner', 'desc')->first();
        if ($link && isset($link->clientid)) {
            $clientId = (int) $link->clientid;
        }
    } catch (\Throwable $e) {}
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }
    if ($clientId <= 0) {
        $clientId = $loggedInUserId; // legacy fallback
    }

    // Require recent password verification (defense-in-depth)
    $verifiedAt = isset($_SESSION['cloudstorage_pw_verified_at']) ? (int)$_SESSION['cloudstorage_pw_verified_at'] : 0;
    $freshWindow = 15 * 60; // 15 minutes
    if ($verifiedAt <= 0 || (time() - $verifiedAt) > $freshWindow) {
        // Optional context: confirm the client does own the product before prompting
        $ownsE3 = false;
        try {
            $ownsE3 = Capsule::table('tblhosting')->where('userid', $clientId)->where('packageid', $packageId)->count() > 0;
        } catch (\Throwable $__) {}
        $jsonData = [
            'status' => 'fail',
            'message' => 'Please verify your password to roll access keys.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }
    $product = DBController::getProduct($clientId, $packageId);

    if (is_null($product) || empty($product->username)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Something went wrong.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    
    // Get the tenant ID for constructing the full Ceph username
    if (is_null($user) || empty($user->tenant_id)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'User tenant information not found.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }
    
    // Construct the full Ceph username with tenant prefix (prefer RGW-safe ceph_uid)
    $baseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($user);
    if (empty($baseUid)) {
        $baseUid = $username; // legacy fallback
    }
    $cephUsername = $user->tenant_id . '$' . $baseUid;
    
    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Cloudstorage has some issue. Please contact to site admin.'
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
    $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);
    // Use the full Ceph username for the API call
    $result = $bucketObject->updateUserAccessKey($cephUsername, $user->id, $encryptionKey);

    $response = new JsonResponse($result, 200);
    $response->send();
    exit();