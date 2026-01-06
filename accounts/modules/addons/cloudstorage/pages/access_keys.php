<?php

    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit;
    }

    // Resolve WHMCS v8 user->client mapping (owner preferred), fallback to legacy session uid.
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
    if ($clientId <= 0) {
        $clientId = $userId; // legacy fallback
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($clientId, $packageId);
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
        ['id', '=', $clientId]
    ]);
    // Option B: treat missing OR invalid/un-decryptable keys as "no keys"
    $hasPrimaryKey = !is_null($accessKey);
    if (!is_null($accessKey)) {
        try {
            $module = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
            $encKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
            $ak = isset($accessKey->access_key) ? HelperController::decryptKey($accessKey->access_key, $encKey) : '';
            $sk = isset($accessKey->secret_key) ? HelperController::decryptKey($accessKey->secret_key, $encKey) : '';
            if (empty($ak) || empty($sk)) {
                $hasPrimaryKey = false;
                $accessKey = null; // show empty state and force user to create a new keypair
            }
        } catch (\Throwable $e) {
            // If we can't validate, fall back to legacy behavior
        }
    }

    return [
        'firstname' => $client->firstname,
        'accessKey' => $accessKey,
        'HAS_PRIMARY_KEY' => $hasPrimaryKey,
        'username' => $username,
        'user' => $user
    ];