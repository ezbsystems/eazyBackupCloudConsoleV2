<?php

    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit;
    }

    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        header('Location: index.php?m=cloudstorage&page=s3storage');
        exit;
    }

    $username = $product->username;
    $user = DBController::getUser($username);

    // get user tenants
    $tenants = DBController::getResult('s3_users', [
        ['parent_id', '=', $user->id]
    ], [
        'id', 'username'
    ])->pluck('username', 'id')->toArray();

    $tenants[$user->id] = $username;
    $bucketUserIds = array_keys($tenants);
    $buckets = DBController::getUserBuckets($bucketUserIds);
    $bucketIds = $buckets->pluck('id')->toArray();
    $bucketStatIds = Capsule::table('s3_bucket_stats')
        ->selectRaw('MAX(id) as id')
        ->whereIn('bucket_id', $bucketIds)
        ->groupBy('bucket_id')
        ->pluck('id')
        ->all();

    $stats = [];
    if (count($bucketStatIds)) {
        $stats = Capsule::table('s3_bucket_stats')
            ->selectRaw('size, bucket_id, num_objects, DATE_FORMAT(created_at, "%Y-%m-%d") as last_updated')
            ->whereIn('id', $bucketStatIds)
            ->get()
            ->keyBy('bucket_id')
            ->toArray();
    }

    return [
        'buckets' => $buckets,
        'usernames' => $tenants,
        'stats' => $stats
    ];