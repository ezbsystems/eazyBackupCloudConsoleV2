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
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

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

    $errors = [];
    if (!isset($_POST['subuser']) || empty($_POST['subuser'])) {
        $errors['subuser_error'] = 'Please enter the subuser.';
    }

    if (!isset($_POST['permission']) || empty($_POST['permission']) || !in_array($_POST['permission'], ['read', 'write', 'readwrite', 'full'])) {
        $errors['permission_error'] = 'Please select the appropriate permission.';
    }

    if (count($errors)) {
        $jsonData = [
            'status' => 'fail',
            'errors' => $errors,
            'message' => 'Invalid data.'
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
            'message' => 'Your account has been suspended. Please contact support.'
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
    $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
    $subuser = $_POST['subuser'];
    $access = $_POST['permission'];

    $params = [
        'uid' => $username,
        'subuser' => $subuser,
        'access' => $access
    ];
    $result = AdminOps::createSubUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
    if ($result['status'] == 'success') {
        // insert record into s3_subusers
        $subuserId = DBController::insertGetId('s3_subusers', [
            'user_id' => $user->id,
            'subuser' => $subuser,
            'permission' => $access
        ]);

        // get the userinfo to retrieve the subuser keys
        $userinfo = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $username);
        $accessKey = $secretKey = '';
        if ($userinfo['status'] == 'success') {
            $keys = $userinfo['data']['keys'];
            $searchUser = $username.':'.$subuser;
            $userData = null;

            foreach ($keys as $item) {
                if ($item['user'] === $searchUser) {
                    $accessKey = $item['access_key'];
                    $secretKey = $item['secret_key'];
                    break;
                }
            }
        }

        if (!empty($accessKey) && !empty($secretKey)) {
            $accessKey = HelperController::encryptKey($accessKey ,$encryptionKey);
            $secretKey = HelperController::encryptKey($secretKey ,$encryptionKey);

            // insert record into s3_subuser_keys
            DBController::insertRecord('s3_subusers_keys', [
                'subuser_id' => $subuserId,
                'access_key' => $accessKey,
                'secret_key' => $secretKey,
            ]);
        }
    }

    $response = new JsonResponse($result, 200);
    $response->send();
    exit();;