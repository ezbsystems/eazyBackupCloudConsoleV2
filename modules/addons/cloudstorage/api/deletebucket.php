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
    $bucketName = $_POST['bucket_name'];

    // check bucket belongs to the logged in user
    $bucket = DBController::getRow('s3_buckets', [
        ['name', '=', $bucketName]
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

    if ($bucket->user_id != $user->id) {
        // get the tenants of the users
        $tenants = DBController::getTenants($user->id, 'id');
        if ($tenants->isEmpty()) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Bucket not found.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
        $tenantIds = $tenants->pluck('id')->toArray();

        if (!in_array($bucket->user_id, $tenantIds)) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Bucket not found.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
    }

    // Enhanced validation for object-locked buckets
    if ($bucket->object_lock_enabled) {
        // Get configuration settings
        $module = DBController::getResult('tbladdonmodules', [
            ['module', '=', 'cloudstorage']
        ]);

        if (count($module) == 0) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }

        $endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
        $adminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
        $adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
        $adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
        $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
        
        // Initialize bucket controller and S3 client connection
        $bucketController = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey);
        $bucketOwner = \WHMCS\Database\Capsule::table('s3_users')->where('id', $bucket->user_id)->first();
        $connectionResult = $bucketController->connectS3Client($bucketOwner->id, $encryptionKey);
        
        if ($connectionResult['status'] != 'success') {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Unable to validate bucket contents. Please try again later.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
        
        // Check if bucket is completely empty
        $emptyCheckResult = $bucketController->isBucketCompletelyEmpty($bucketName);
        
        if ($emptyCheckResult['status'] != 'success' || !$emptyCheckResult['empty']) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Object-locked bucket cannot be deleted: ' . $emptyCheckResult['message']
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
        
        // If we reach here, the object-locked bucket is completely empty and can be deleted
    }

    // update the records from database
    DBController::updateRecord('s3_buckets', [
        'is_active' => 0
    ], [
        ['id', '=', $bucket->id]
    ]);

    // push the bucket to delete job
    DBController::insertRecord('s3_delete_buckets', [
        'user_id' => $bucket->user_id,
        'bucket_name' => $bucketName
    ]);

    $jsonData = [
        'status' => 'success',
        'message' => 'Bucket and contents queued for deletion, removal will now proceed in the background.'
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();