<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    // CRITICAL: Release session lock IMMEDIATELY after init.php loads.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

    $ca = new ClientArea();
    $loggedInUserId = $ca->getUserID();
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'User does not exist.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    $bucketName = $_POST['bucket_name'] ?? '';

    if (empty($bucketName)) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200);
        $response->send();
        exit();
    }

    // Validate bucket ownership (including tenant buckets)
    $bucket = DBController::getRow('s3_buckets', [
        ['name', '=', $bucketName]
    ]);

    if (is_null($bucket)) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
        $response->send();
        exit();
    }

    if ($bucket->user_id != $user->id) {
        $tenants = DBController::getTenants($user->id, 'id');
        if ($tenants->isEmpty()) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
            $response->send();
            exit();
        }
        $tenantIds = $tenants->pluck('id')->toArray();
        if (!in_array($bucket->user_id, $tenantIds)) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
            $response->send();
            exit();
        }
    }

    // Load module settings
    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Cloud Storage service error. Please contact support.'], 200);
        $response->send();
        exit();
    }

    $endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
    $adminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
    $adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
    $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

    // Initialize bucket controller and S3 client connection
    $bucketController = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $s3Region);
    $bucketOwner = \WHMCS\Database\Capsule::table('s3_users')->where('id', $bucket->user_id)->first();
    $connectionResult = $bucketController->connectS3Client($bucketOwner->id, $encryptionKey);

    if ($connectionResult['status'] != 'success') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Unable to connect to storage for status check.'], 200);
        $response->send();
        exit();
    }

    $status = $bucketController->getObjectLockEmptyStatus($bucketName);
    if ($status['status'] !== 'success') {
        $response = new JsonResponse(['status' => 'fail', 'message' => $status['message'] ?? 'Status check failed.'], 200);
        $response->send();
        exit();
    }

    // Include DB-known object_lock flag for quick UX hints
    $status['data']['object_lock']['db_flag'] = (bool)$bucket->object_lock_enabled;

    $response = new JsonResponse(['status' => 'success', 'data' => $status['data']]);
    $response->send();
    exit();


