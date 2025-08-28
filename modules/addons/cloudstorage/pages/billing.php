<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\BillingController;

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $ca = new ClientArea();
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        header('Location: index.php?m=cloudstorage&page=s3storage');
        exit;
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    $client = DBController::getRow('tblclients', [
        ['id', '=', $loggedInUserId]
    ]);

    $tenants = DBController::getResult('s3_users', [
        ['parent_id', '=', $user->id]
    ], [
        'id', 'username'
    ])->pluck('id', 'username')->toArray();

    $userIds = array_merge(array_values($tenants), [$user->id]);

    $billingObject = new BillingController();
    $billingPeriod = $billingObject->calculateBillingMonth($loggedInUserId, $packageId);
    $userAmount = $billingObject->getBalanceAmount($loggedInUserId, $packageId);
    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey);

    $userBuckets = DBController::getUserBuckets($userIds);
    $buckets = $userBuckets->isNotEmpty() ? $userBuckets->pluck('name', 'id')->toArray() : [];
    $bucketInfo = $bucketObject->getTotalBucketSizeForUser($buckets);
    $totalUsage = $bucketObject->getTotalUsageForBillingPeriod($userIds, $billingPeriod['start'], $billingPeriod['end']);
    $peakUsage = $bucketObject->findPeakBucketUsage($userIds, $billingPeriod);
    $bucketStats = $bucketObject->getUserBucketSummary($userIds, $billingPeriod['start'], $billingPeriod['end']);

    return [
        'firstname' => $client->firstname,
        'billingPeriod' => $billingPeriod,
        'userAmount' => $userAmount,
        'bucketInfo' => $bucketInfo,
        'totalUsage' => $totalUsage,
        'peakUsage' => $peakUsage,
        'bucketStats' => $bucketStats
    ];