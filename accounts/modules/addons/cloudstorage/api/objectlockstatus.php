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
    use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
    use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

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

    // IMPORTANT (multi-tenant RGW):
    // AdminOps credentials can query metadata, but often cannot access tenant buckets over the S3 data-plane.
    // For an accurate Object Lock / emptiness check we should prefer:
    //  - the bucket owner's stored S3 keys (s3_user_access_keys), or
    //  - a short-lived owner key created via AdminOps (fallback).
    $bucketController = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $s3Region);

    $ownerUserId = (int) $bucket->user_id;
    $usedTempKey = false;
    $tempAccessKey = '';
    $tempSecretKey = '';
    $tempKeyUid = '';

    // Try the owner's stored S3 keypair (fast path), but validate it with headBucket.
    // Some installs have stale keys in DB; those can misleadingly produce "empty" status due to swallowed errors.
    $accessKeyPlain = '';
    $secretKeyPlain = '';
    $connectionResult = null;
    try {
        if (!empty($encryptionKey) && Capsule::schema()->hasTable('s3_user_access_keys')) {
            $k = Capsule::table('s3_user_access_keys')->where('user_id', $ownerUserId)->first();
            if ($k && !empty($k->access_key) && !empty($k->secret_key)) {
                $accessKeyPlain = (string) HelperController::decryptKey($k->access_key, (string) $encryptionKey);
                $secretKeyPlain = (string) HelperController::decryptKey($k->secret_key, (string) $encryptionKey);
                if ($accessKeyPlain !== '' && $secretKeyPlain !== '') {
                    $tmpConn = $bucketController->connectS3ClientWithCredentials($accessKeyPlain, $secretKeyPlain);
                    if (($tmpConn['status'] ?? 'fail') === 'success' && !empty($tmpConn['s3client'])) {
                        try {
                            $tmpConn['s3client']->headBucket(['Bucket' => $bucketName]);
                            $connectionResult = $tmpConn;
                        } catch (\Throwable $e) {
                            // invalid/stale key; fall back to temp key
                            $accessKeyPlain = '';
                            $secretKeyPlain = '';
                        }
                    } else {
                        $accessKeyPlain = '';
                        $secretKeyPlain = '';
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        $accessKeyPlain = '';
        $secretKeyPlain = '';
        $connectionResult = null;
    }

    // Fallback: create a temporary owner key via AdminOps
    if ($connectionResult === null) {
        try {
            $ownerRow = Capsule::table('s3_users')->where('id', $ownerUserId)->first();
            $tempKeyUid = DeprovisionHelper::computeCephUid($ownerRow);
            if ($tempKeyUid !== '' && !empty($adminAccessKey) && !empty($adminSecretKey)) {
                $tmp = AdminOps::createTempKey($endpoint, $adminAccessKey, $adminSecretKey, (string)$tempKeyUid, null);
                if (is_array($tmp) && ($tmp['status'] ?? '') === 'success') {
                    $tempAccessKey = (string)($tmp['access_key'] ?? '');
                    $tempSecretKey = (string)($tmp['secret_key'] ?? '');
                    if ($tempAccessKey !== '' && $tempSecretKey !== '') {
                        $usedTempKey = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            $usedTempKey = false;
            $tempAccessKey = '';
            $tempSecretKey = '';
            $tempKeyUid = '';
        }
        $accessKeyPlain = $tempAccessKey;
        $secretKeyPlain = $tempSecretKey;
    }

    if ($connectionResult === null && ($accessKeyPlain === '' || $secretKeyPlain === '')) {
        (new JsonResponse([
            'status' => 'fail',
            'message' => 'Unable to check bucket status at this time. Please try again later.'
        ], 200))->send();
        exit();
    }

    if ($connectionResult === null) {
        $connectionResult = $bucketController->connectS3ClientWithCredentials($accessKeyPlain, $secretKeyPlain);
        if (($connectionResult['status'] ?? 'fail') !== 'success') {
            // Best-effort cleanup if we created a temp key
            if ($usedTempKey && $tempAccessKey !== '' && $tempKeyUid !== '') {
                try { AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyUid, null); } catch (\Throwable $ignored) {}
            }
            (new JsonResponse(['status' => 'fail', 'message' => $connectionResult['message'] ?? 'Unable to connect to storage for status check.'], 200))->send();
            exit();
        }
    }

    $status = $bucketController->getObjectLockEmptyStatus($bucketName);
    if ($status['status'] !== 'success') {
        // Best-effort cleanup if we created a temp key
        if ($usedTempKey && $tempAccessKey !== '' && $tempKeyUid !== '') {
            try { AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyUid, null); } catch (\Throwable $ignored) {}
        }
        $response = new JsonResponse(['status' => 'fail', 'message' => $status['message'] ?? 'Status check failed.'], 200);
        $response->send();
        exit();
    }

    // Best-effort cleanup if we created a temp key
    if ($usedTempKey && $tempAccessKey !== '' && $tempKeyUid !== '') {
        try { AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyUid, null); } catch (\Throwable $ignored) {}
    }

    // Include DB-known object_lock flag for quick UX hints
    $status['data']['object_lock']['db_flag'] = (bool)$bucket->object_lock_enabled;

    $response = new JsonResponse(['status' => 'success', 'data' => $status['data']]);
    $response->send();
    exit();


