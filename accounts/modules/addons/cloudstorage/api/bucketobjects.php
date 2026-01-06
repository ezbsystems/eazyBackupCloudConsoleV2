<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    // CRITICAL: Release session lock IMMEDIATELY after init.php loads.
    // WHMCS uses file-based sessions that block ALL concurrent requests.
    // We must close the session before doing ANY slow operations.
    // The session data is still readable after this call, just not writable.
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Save any session changes made by init.php, then release lock
        session_write_close();
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Database\Capsule;

    if (!isset($_POST['bucket']) || empty($_POST['bucket'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Bucket is missing.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $ca = new ClientArea();
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

    $product = DBController::getProduct($clientId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'User not exist.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $username = $product->username;
    $productUsername = $product->username;
    // Allow missing or empty username to mean "current product user" (align with objectversions.php)
    $browseUser = (isset($_POST['username']) && $_POST['username'] !== '')
        ? $_POST['username']
        : $product->username;
    $user = DBController::getUser($username);
    if (is_null($user)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Your account has been suspended. Please contact support.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $userId = $user->id;

    if ($username !== $browseUser) {
        $tenant = DBController::getRow('s3_users', [
            ['username', '=', $browseUser],
            ['parent_id', '=', $userId],
        ]);

        if (is_null($tenant)) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Invalid browse user.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }

        $username = $browseUser;
        $userId = $tenant->id;
    }

    $bucketName = $_POST['bucket'];

    // check bucket belongs to the logged in user
    $bucket = DBController::getRow('s3_buckets', [
        ['name', '=', $bucketName],
        ['user_id', '=', $userId]
    ]);

    if (is_null($bucket)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Bucket not found.'
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
            'message' => 'Cloudstorage has some issue. Please contact to site admin.',
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
    $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);
    $s3Connection = $bucketObject->connectS3Client($userId, $encryptionKey);

    if ($s3Connection['status'] == 'fail') {
        // If keys are missing/invalid, instruct UI to redirect to key creation.
        $redirect = null;
        if (stripos((string)$s3Connection['message'], 'Access keys') !== false) {
            $redirect = ($browseUser !== $productUsername)
                ? '/index.php?m=cloudstorage&page=users'
                : '/index.php?m=cloudstorage&page=access_keys';
        }
        $jsonData = [
            'status' => 'fail',
            'message' => $s3Connection['message'],
            'redirect' => $redirect,
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $prefix = isset($_POST['folder_path']) ? trim($_POST['folder_path']) : '';
    $continuationToken = isset($_POST['continuation_token']) ? trim($_POST['continuation_token']) : '';
    // Cap page size to avoid long, blocking list calls on very large buckets.
    // Default reduced to 50 for faster initial loads on large buckets.
    $rawMaxKeys = isset($_POST['max_keys']) ? trim($_POST['max_keys']) : '';
    $maxKeys = 50;
    if ($rawMaxKeys !== '') {
        $parsed = (int)$rawMaxKeys;
        if ($parsed > 0) {
            $maxKeys = min($parsed, 100); // Hard cap at 100
        }
    }
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    $options = [
        'bucket' => $bucketName,
        'prefix' => $prefix,
        'max_keys' => $maxKeys,
        'continuation_token' => $continuationToken,
        'delimiter' => '/'
    ];

    // Server-side cache for bucket listings (30 second TTL)
    // This dramatically improves performance for large buckets by avoiding repeated S3 calls
    $cacheKey = 'bucket_list_' . md5($bucketName . '|' . $prefix . '|' . $continuationToken . '|' . $maxKeys);
    $cacheTtl = 30; // seconds
    $cacheDir = sys_get_temp_dir() . '/cloudstorage_cache';
    $cacheFile = $cacheDir . '/' . $cacheKey . '.json';
    $contents = null;

    // Allow caller to bypass cache (e.g., immediately after a delete)
    $noCache = isset($_POST['nocache']) && (string)$_POST['nocache'] === '1';

    // Try to read from cache
    if (!$noCache && file_exists($cacheFile)) {
        $cacheData = @file_get_contents($cacheFile);
        if ($cacheData !== false) {
            $cached = @json_decode($cacheData, true);
            if (is_array($cached) && isset($cached['expires']) && $cached['expires'] > time()) {
                $contents = $cached['data'];
            }
        }
    }

    // If not cached or expired, fetch from S3
    if ($contents === null) {
        $contents = $bucketObject->listBucketContents($options, $action);

        // If auth failed, include a redirect for the UI.
        if (isset($contents['status']) && $contents['status'] === 'fail') {
            $msg = (string)($contents['message'] ?? '');
            if (stripos($msg, 'Access keys are missing or invalid') !== false || stripos($msg, 'Access keys missing') !== false) {
                $contents['redirect'] = ($browseUser !== $productUsername)
                    ? '/index.php?m=cloudstorage&page=users'
                    : '/index.php?m=cloudstorage&page=access_keys';
            }
        }
        
        // Cache successful responses
        if (isset($contents['status']) && $contents['status'] === 'success') {
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            $cachePayload = json_encode([
                'expires' => time() + $cacheTtl,
                'data' => $contents
            ]);
            @file_put_contents($cacheFile, $cachePayload, LOCK_EX);
        }
    }

    $jsonData = [
        'continuationToken' => $contents['continuationToken'],
        'count' => $contents['count'],
        'data' => $contents['data'],
        'message' => $contents['message'],
        'status' => $contents['status'],
        'redirect' => $contents['redirect'] ?? null,
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();