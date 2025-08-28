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

    if (empty($_POST['bucket']) || empty($_POST['username'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Invalid request.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    if (empty($_POST['files']) || !is_array($_POST['files'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Please select the file object to delete.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $bucketName = $_POST['bucket'];
    $files = $_POST['files'];
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

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey);
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

    $objectsToDelete = [];
    foreach ($files as $file) {
        $objectsToDelete[] = ['Key' => $file];
    }

    $result = $bucketObject->deleteBucketObject($bucketName, $objectsToDelete);

    $response = new JsonResponse($result, 200);
    $response->send();
    exit();