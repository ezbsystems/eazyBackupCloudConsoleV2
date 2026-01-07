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

    // Resolve client ID (WHMCS v8 user->client mapping)
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

    $product = DBController::getProduct($clientId, $packageId);
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

    // Determine which users have usable access keys (Option B: may be empty for new customers)
    $hasKeysByUserId = [];
    try {
        $keyUserIds = Capsule::table('s3_user_access_keys')
            ->whereIn('user_id', $bucketUserIds)
            ->pluck('user_id')
            ->toArray();
        foreach ($bucketUserIds as $uid) {
            $hasKeysByUserId[(int)$uid] = in_array((int)$uid, array_map('intval', $keyUserIds), true);
        }
    } catch (\Throwable $e) {
        // Best-effort: default to true so we don't hide functionality on DB issues
        foreach ($bucketUserIds as $uid) {
            $hasKeysByUserId[(int)$uid] = true;
        }
    }
    $hasPrimaryKey = (bool)($hasKeysByUserId[(int)$user->id] ?? false);

    // Pending delete jobs (queued/running/blocked) keyed by bucket name
    $deleteJobsByBucketName = [];
    try {
        $bucketNames = $buckets->pluck('name')->toArray();
        $hasDeleteStatus = Capsule::schema()->hasColumn('s3_delete_buckets', 'status');
        if ($hasDeleteStatus && !empty($bucketNames)) {
            $rows = Capsule::table('s3_delete_buckets')
                ->whereIn('bucket_name', $bucketNames)
                ->whereIn('status', ['queued', 'running', 'blocked'])
                ->orderBy('id', 'desc')
                ->get();
            foreach ($rows as $r) {
                $bn = (string) ($r->bucket_name ?? '');
                if ($bn === '') {
                    continue;
                }
                if (!array_key_exists($bn, $deleteJobsByBucketName)) {
                    $deleteJobsByBucketName[$bn] = $r;
                }
            }
        }
    } catch (\Throwable $e) {
        $deleteJobsByBucketName = [];
    }

    // Expose region and optional lifecycle storage classes to template
    $s3Region = 'ca-central-1';
    $lifecycleClasses = [];
    try {
        $moduleRows = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
        if (count($moduleRows) > 0) {
            $regionRow = $moduleRows->where('setting', 's3_region')->pluck('value')->first();
            if (!empty($regionRow)) {
                $s3Region = $regionRow;
            }
            $classesCsv = $moduleRows->where('setting', 'lifecycle_storage_classes')->pluck('value')->first();
            if (!empty($classesCsv)) {
                $parts = array_map('trim', explode(',', $classesCsv));
                foreach ($parts as $p) {
                    if ($p !== '') {
                        $lifecycleClasses[] = $p;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Best-effort only; keep values defaulted for UI
    }

    return [
        'buckets' => $buckets,
        'usernames' => $tenants,
        'stats' => $stats,
        'HAS_PRIMARY_KEY' => $hasPrimaryKey,
        'HAS_KEYS_BY_USER_ID' => $hasKeysByUserId,
        'DELETE_JOBS_BY_BUCKET_NAME' => $deleteJobsByBucketName,
        'S3_REGION' => $s3Region,
        'LIFECYCLE_CLASSES' => $lifecycleClasses
    ];