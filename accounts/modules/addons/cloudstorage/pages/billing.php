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
    // Use display period so header shows the rolling service window, independent of nextduedate
    $billingPeriod = $billingObject->calculateDisplayPeriod($loggedInUserId, $packageId);
    $userAmount = $billingObject->getBalanceAmount($loggedInUserId, $packageId);
    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $vars['s3_region'] ?? 'us-east-1');

    $userBuckets = DBController::getUserBuckets($userIds);
    $buckets = $userBuckets->isNotEmpty() ? $userBuckets->pluck('name', 'id')->toArray() : [];
    $bucketInfo = $bucketObject->getTotalBucketSizeForUser($buckets);
    // Query through today to keep usage totals and charts live
    $totalUsage = $bucketObject->getTotalUsageForBillingPeriod($userIds, $billingPeriod['start'], $billingPeriod['end_for_queries']);
    $peakBillingPeriod = ['start' => $billingPeriod['start'], 'end' => $billingPeriod['end_for_queries']];
    // Use billed instantaneous snapshots for Billable Usage and chart series
    $peakUsage = $bucketObject->findPeakBillableUsageFromPrices((int)$user->id, $peakBillingPeriod);
    $bucketStats = $bucketObject->getDailyBillableUsageFromPrices((int)$user->id, $billingPeriod['start'], $billingPeriod['end_for_queries']);
    // Fill-forward daily storage for the date range so the line continues into the current month
    $dailyUsageChart = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::prepareDailyUsageChart($billingPeriod['start'], $bucketStats);

    return [
        'firstname' => $client->firstname,
        'billingPeriod' => $billingPeriod,
        'userAmount' => $userAmount,
        'bucketInfo' => $bucketInfo,
        'totalUsage' => $totalUsage,
        'peakUsage' => $peakUsage,
        'bucketStats' => $dailyUsageChart
    ];