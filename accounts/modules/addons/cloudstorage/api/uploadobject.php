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

    if (empty($_POST['bucket']) || empty($_POST['username'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Invalid request.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    if (!isset($_FILES['uploadedFiles'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Please upload the file.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    if ($_FILES['uploadedFiles']['error'] > 0) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Error while uploading the file.'
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
    $browseUser = $_POST['username'];
    $username = $product->username;
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

    if ($username != $browseUser) {
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

    // Get optional path parameters for folder upload support
    $relativePath = isset($_POST['relativePath']) ? trim($_POST['relativePath']) : '';
    $folderPath = isset($_POST['folder_path']) ? trim($_POST['folder_path']) : '';

    // check bucket belongs to the logged in user
    $bucket = DBController::getRow('s3_buckets', [
        ['name', '=', $bucketName],
        ['user_id', '=', $userId],
        ['is_active', '=', '1']
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

    // Build the S3 key with folder path and relative path support
    $fileName = $_FILES['uploadedFiles']['name'];
    
    // Sanitize relative path (remove leading/trailing slashes, prevent directory traversal)
    $relativePath = trim($relativePath, '/\\');
    $relativePath = preg_replace('/\.\.\/|\.\.\\\\/', '', $relativePath); // Remove ../ patterns
    
    // Sanitize folder path
    $folderPath = trim($folderPath, '/\\');
    $folderPath = preg_replace('/\.\.\/|\.\.\\\\/', '', $folderPath);
    
    // Build the full key
    $keyParts = array_filter([$folderPath, $relativePath, $fileName], function($part) {
        return $part !== '' && $part !== null;
    });
    $key = implode('/', $keyParts);
    
    $result = $bucketObject->saveUploadedFiles($bucketName, $key, $_FILES['uploadedFiles']['tmp_name']);
    $response = new JsonResponse($result, 200);
    $response->send();
    exit();