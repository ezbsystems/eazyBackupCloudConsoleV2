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

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);

    if (is_null($product) || is_null($product->username)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Something went wrong.'
        ];

       $response = new JsonResponse($jsonData, 200);
       $response->send();
       exit();
    }

    $username = $product->username;
    // Log context before attempting decryption (no secrets)
    logModuleCall(
        'cloudstorage',
        'DecryptKeysRequest',
        [
            'userId' => $loggedInUserId,
            'username' => $username,
        ],
        null,
        null,
        []
    );
    $user = DBController::getUser($username);
    $accessKey = DBController::getRow('s3_user_access_keys', [
        ['user_id', '=', $user->id]
    ]);

    if (is_null($accessKey)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Key does not exist.'
        ];

       $response = new JsonResponse($jsonData, 200);
       $response->send();
       exit();
    }

    $encryptionKey = DBController::getRow('tbladdonmodules', [
        ['module', '=', 'cloudstorage'],
        ['setting', '=', 'encryption_key']
    ]);

    $decryptedAccessKey = HelperController::decryptKey($accessKey->access_key, $encryptionKey->value);
    $decryptedSecretKey = HelperController::decryptKey($accessKey->secret_key, $encryptionKey->value);
    $accessKey->access_key = $decryptedAccessKey;
    $accessKey->secret_key = $decryptedSecretKey;

    // Log successful decryption (only metadata, not actual keys)
    logModuleCall(
        'cloudstorage',
        'DecryptKeysSuccess',
        [
            'userId' => $loggedInUserId,
            'username' => $username,
            'accessKeyId' => $accessKey->id ?? null,
        ],
        'Decryption completed',
        null,
        []
    );

    $jsonData = [
        'status' => 'success',
        'message' => 'Successfully decrypted keys.',
        'keys' => $accessKey
    ];

    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();