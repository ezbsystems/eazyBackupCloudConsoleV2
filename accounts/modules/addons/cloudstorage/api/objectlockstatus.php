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
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
        exit();
    }

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

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($clientId, $packageId);
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

    // Initialize bucket controller and S3 client connection (admin creds; Option B does not require user keys)
    $bucketController = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $s3Region);
    $connectionResult = $bucketController->connectS3ClientAsAdmin();

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


