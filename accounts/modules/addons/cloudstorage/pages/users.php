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
        ->select('id', 'username', 'tenant_id', 'is_system_managed', 'system_key', 'manage_locked')
        ->where('parent_id', $user->id)
        ->orderBy('id', 'DESC')
        ->get();

    $rootKey = Capsule::table('s3_user_access_keys')
        ->select('id as key_id', 'access_key_hint', 'created_at', 'is_user_generated')
        ->where('user_id', $user->id)
        ->orderBy('id', 'DESC')
        ->first();
    $rootHasKey = !is_null($rootKey)
        && (int)($rootKey->is_user_generated ?? 0) === 1
        && !empty($rootKey->access_key_hint);

    $rootUser = (object) [
        'id' => $user->id,
        'username' => $user->username,
        'tenant_id' => $user->tenant_id,
        'is_root' => true,
        'display_name' => 'Root user',
        'is_system_managed' => false,
        'system_key' => null,
        'manage_locked' => false,
        'has_root_key' => $rootHasKey,
        'root_access_key_hint' => $rootHasKey ? $rootKey->access_key_hint : '',
        'root_access_key_created_at' => $rootHasKey ? $rootKey->created_at : null,
        'access_keys' => $rootHasKey ? [
            (object) [
                'key_id' => $rootKey->key_id,
                'access_key_hint' => $rootKey->access_key_hint,
                'description' => null,
                'permission' => 'full',
                'created_at' => $rootKey->created_at
            ]
        ] : [],
        'keys' => [],
        'subusers' => []
    ];

    $users = array_merge([$rootUser], $tenants->all());

    foreach ($users as $tenant) {
        $tenant->display_name = $tenant->display_name ?? $tenant->username;
        $tenant->is_root = $tenant->is_root ?? false;
        $tenant->is_system_managed = !empty($tenant->is_system_managed);
        $tenant->manage_locked = !empty($tenant->manage_locked);
        $tenant->system_key = $tenant->system_key ?? null;

        if (!$tenant->is_root) {
            // Legacy fields for backward compatibility with older templates (no secrets included)
            $keys = Capsule::table('s3_user_access_keys')
                ->select('id as key_id')
                ->where('user_id', $tenant->id)
                ->orderBy('id', 'DESC')
                ->get();

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

            $tenant->keys = $keys;
            $tenant->subusers = $subusers;
            $tenant->access_keys = $accessKeys;
        }

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

        $tenant->total_storage = HelperController::formatSizeUnits($totalStorage);
        $tenant->total_buckets = $totalBucket;
    }

    return [
        'users' => $users
    ];