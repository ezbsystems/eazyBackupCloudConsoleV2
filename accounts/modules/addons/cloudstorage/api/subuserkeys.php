<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
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

    if (
        !isset($_POST['action']) || !isset($_POST['id']) ||
        empty($_POST['action']) || empty($_POST['id']) ||
        !in_array($_POST['action'], ['rollkeys', 'viewkeys'])
    ) {
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

    $action = $_POST['action'];
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


    if ($action == 'viewkeys') {
        $keys = DBController::getRow('s3_subusers_keys', [
            ['subuser_id', '=', $subuserId]
        ]);

        if (is_null($keys)) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Keys are missing. Please contact support.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }

        $encryptionKey = DBController::getRow('tbladdonmodules', [
            ['module', '=', 'cloudstorage'],
            ['setting', '=', 'encryption_key']
        ]);

        $decryptedAccessKey = HelperController::decryptKey($keys->access_key, $encryptionKey->value);
        $decryptedSecretKey = HelperController::decryptKey($keys->secret_key, $encryptionKey->value);
        $keys->access_key = $decryptedAccessKey;
        $keys->secret_key = $decryptedSecretKey;
        $keys->subuser = $subuser->subuser;
        $jsonData = [
            'data' => $keys,
            'message' => 'Keys has been fetched successfully.',
            'status' => 'success'
        ];
    } else {
        // roll the keys
        $jsonData = [
            'data' => [],
            'message' => 'Keys has been updated successfully.',
            'status' => 'success'
        ];
    }

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();