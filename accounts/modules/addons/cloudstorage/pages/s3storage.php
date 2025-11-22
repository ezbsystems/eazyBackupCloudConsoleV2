<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;

    $ca = new ClientArea();

    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();
    $client = DBController::getClient($loggedInUserId);

    if (is_null($client)) {
        error_log("Error: User ". $loggedInUserId . " record not found.");
        header('location: clientarea.php');
        exit;
    }

    $product = DBController::getProduct($loggedInUserId, $packageId);

    if (!is_null($product)) {
        header('location: index.php?m=cloudstorage&page=dashboard');
        exit;
    }

    return [
        'status' => 'success'
    ];