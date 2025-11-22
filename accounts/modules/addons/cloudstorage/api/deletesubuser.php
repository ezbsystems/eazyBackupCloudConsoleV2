<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;

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

    if (!isset($_POST['id']) || empty($_POST['id'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Invalid request data.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);

    if (is_null($product) || empty($product->username)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Product not linked with S3.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

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

    $subuserId = $_POST['id'];

    $subuser = DBController::getRow('s3_subusers', [
        ['user_id', '=', $user->id],
        ['id', '=', $subuserId],
    ]);

    if (is_null($subuser)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Something wrong. Please try again or contact support.'
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
    $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $params = [
        'uid' => $username,
        'subuser' => $subuser->subuser
    ];
    $result = AdminOps::removeSubUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);

    if ($result['status'] == 'success') {
        // remove record from s3_subuser_keys
        DBController::deleteRecord('s3_subusers_keys', [
            ['subuser_id', '=', $subuserId]
        ]);
        // remove record from s3_subusers
        DBController::deleteRecord('s3_subusers', [
            ['id', '=', $subuserId]
        ]);

    }

    $response = new JsonResponse($result, 200);
    $response->send();
    exit();