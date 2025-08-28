<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

    $ca = new ClientArea();
    $loggedInUserId = $ca->getUserID();
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        header('Location: index.php?m=cloudstorage&page=s3storage');
        exit;
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    $accessKey = DBController::getRow('s3_user_access_keys', [
        ['user_id', '=', $user->id]
    ]);
    $client = DBController::getRow('tblclients', [
        ['id', '=', $loggedInUserId]
    ]);

    return [
        'firstname' => $client->firstname,
        'accessKey' => $accessKey,
        'username' => $username,
        'user' => $user
    ];