<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit;
    }
    $loggedInUserId = $ca->getUserID();
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || empty($product->username)) {
        header('Location: index.php?m=cloudstorage&page=s3storage');
        exit;
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    if (is_null($user)) {
        $_SESSION['message'] = 'Your account has been suspended. Please contact support.';
        header('Location: index.php?m=cloudstorage&page=s3storage');
        exit;
    }

    $subusers = DBController::getResult('s3_subusers', [
        ['user_id', $user->id]
    ], [
        'subuser', 'permission'
    ]);

    return [
        'subusers' => $subusers
    ];

    // // $username = 'subuserdemo';
    // $params = [
    //     'uid' => $username,
    //     'subuser' => 'subuserdemo'
    // ];
    // $response = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $username);
    // // $response = AdminOps::removeSubUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
    // echo "<pre>"; print_r($response);die;