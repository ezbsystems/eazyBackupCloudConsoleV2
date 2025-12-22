<?php

    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);

    if (is_null($product) || is_null($product->username)) {
        header('Location: index.php?m=cloudstorage&page=s3storage');
        exit;
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    if (is_null($user)) {
        $_SESSION['message'] = 'Your account has been suspended. Please contact support.';
        header('Location: index.php?m=cloudstorage&page=s3storage&status=fail');
        exit();
    }

    $tenants = Capsule::table('s3_users')
        ->select('id', 'username', 'tenant_id')
        ->where('parent_id', $user->id)
        ->orderBy('id', 'DESC')
        ->get();

    foreach ($tenants as $tenant) {
        // Legacy fields for backward compatibility with older templates (no secrets included)
        $keys = Capsule::table('s3_user_access_keys')
            ->select('id as key_id')
            ->where('user_id', $tenant->id)
            ->orderBy('id', 'DESC')
            ->get();

        $userBuckets = DBController::getUserBuckets($tenant->id);
        $totalBucket = $userBuckets->count();
        $totalStorage = 0;
        if ($totalBucket) {
            $bucketIds = $userBuckets->pluck('id')->toArray();
            $bucketStatIds = Capsule::table('s3_bucket_stats')
                ->selectRaw('MAX(id) as id')
                ->whereIn('bucket_id', $bucketIds)
                ->groupBy('bucket_id')
                ->pluck('id')
                ->all();

            // get the total storage
            $totalStorage = Capsule::table('s3_bucket_stats')
                ->whereIn('id', $bucketStatIds)
                ->sum('size');
        }

        // Access Keys v2 (client-facing): subuser-backed keys with description + non-secret hint
        $fkCol = Capsule::schema()->hasColumn('s3_subusers_keys', 'subuser_id') ? 'subuser_id' : 'sub_user_id';
        $accessKeys = Capsule::table('s3_subusers')
            ->select(
                's3_subusers_keys.id as key_id',
                's3_subusers_keys.access_key_hint',
                's3_subusers.permission',
                's3_subusers.description',
                's3_subusers_keys.created_at'
            )
            ->join('s3_subusers_keys', 's3_subusers.id', '=', 's3_subusers_keys.' . $fkCol)
            ->where('s3_subusers.user_id', $tenant->id)
            ->orderBy('s3_subusers_keys.id', 'DESC')
            ->get();

        // Legacy "subusers" for older templates (no secrets included)
        $subusers = Capsule::table('s3_subusers')
            ->select('s3_subusers.subuser', 's3_subusers.permission', 's3_subusers_keys.id as key_id')
            ->join('s3_subusers_keys', 's3_subusers.id', '=', 's3_subusers_keys.' . $fkCol)
            ->where('s3_subusers.user_id', $tenant->id)
            ->orderBy('s3_subusers.id', 'DESC')
            ->get();

        $tenant->total_storage = HelperController::formatSizeUnits($totalStorage);
        $tenant->total_buckets = $totalBucket;
        $tenant->keys = $keys;
        $tenant->subusers = $subusers;
        $tenant->access_keys = $accessKeys;
    }

    return [
        'users' => $tenants
    ];