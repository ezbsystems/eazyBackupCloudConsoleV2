<?php

    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;

    $ca = new ClientArea();

    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;

    // Resolve client ID from current user (WHMCS v8)
    $userId = (int) $ca->getUserID();
    $clientId = 0;
    try {
        $link = Capsule::table('tblusers_clients')->where('userid', $userId)->orderBy('owner', 'desc')->first();
        if ($link && isset($link->clientid)) {
            $clientId = (int) $link->clientid;
        }
    } catch (\Throwable $e) {}
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }

    $client = DBController::getClient($clientId);
    if (is_null($client)) {
        try { logModuleCall('cloudstorage', 's3storage_client_missing', ['userId' => $userId, 'clientId' => $clientId], ''); } catch (\Throwable $e) {}
        error_log("Error: Client ". $clientId . " record not found.");
        header('location: clientarea.php');
        exit;
    }

    $product = DBController::getProduct($clientId, $packageId);
    try {
        $prodArr = $product ? ['username' => $product->username ?? null] : null;
        logModuleCall('cloudstorage', 's3storage_entry', [
            'userId' => $userId,
            'clientId' => $clientId,
            'packageId' => $packageId,
            'product' => $prodArr,
            'status' => $_GET['status'] ?? ''
        ], '');
    } catch (\Throwable $e) {}
    // Only redirect when a username exists (Ceph user created)
    if (!is_null($product) && !is_null($product->username)) {
        try { logModuleCall('cloudstorage', 's3storage_redirect_dashboard', ['clientId' => $clientId], ''); } catch (\Throwable $e) {}
        header('location: index.php?m=cloudstorage&page=dashboard');
        exit;
    }

    // Prefill suggested username from client email
    $email = '';
    try { $email = (string) $client->email; } catch (\Throwable $e) { $email = ''; }
    $suggested = preg_replace('/[^a-z0-9._@-]+/', '', strtolower($email));

    return [
        'status' => 'success',
        'default_username' => $suggested,
    ];