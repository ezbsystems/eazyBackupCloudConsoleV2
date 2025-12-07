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
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);
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
        $jsonData = [
            'status' => 'fail',
            'message' => $s3Connection['message']
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $prefix = isset($_POST['folder_path']) ? trim($_POST['folder_path']) : '';
    $continuationToken = isset($_POST['continuation_token']) ? trim($_POST['continuation_token']) : '';
    // Cap page size to avoid long, blocking list calls on very large buckets.
    $rawMaxKeys = isset($_POST['max_keys']) ? trim($_POST['max_keys']) : '';
    $maxKeys = 200;
    if ($rawMaxKeys !== '') {
        $parsed = (int)$rawMaxKeys;
        if ($parsed > 0) {
            $maxKeys = min($parsed, 200);
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

    $contents = $bucketObject->listBucketContents($options, $action);

    $jsonData = [
        'continuationToken' => $contents['continuationToken'],
        'count' => $contents['count'],
        'data' => $contents['data'],
        'message' => $contents['message'],
        'status' => $contents['status']
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();