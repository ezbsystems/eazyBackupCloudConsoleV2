<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\BillingController;
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
        $_SESSION['message'] = 'You are not subscribe the product.';
        header('Location: index.php?m=cloudstorage&page=s3storage&status=fail');
        exit;
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    if (is_null($user)) {
        $_SESSION['message'] = 'Your account has been suspended. Please contact support.';
        header('Location: index.php?m=cloudstorage&page=s3storage&status=fail');
        exit();
    }

    $tenants = DBController::getResult('s3_users', [
        ['parent_id', '=', $user->id]
    ], [
        'id', 'username'
    ])->pluck('id', 'username')->toArray();

    $userIds = array_merge(array_values($tenants), [$user->id]);
    $usernames = array_merge([$username], array_keys($tenants));
    if (!empty($_GET['username'])) {
        if (!in_array($_GET['username'], $usernames)) {
            $_SESSION['message'] = 'Selected user is invalid.';
            header('Location: index.php?m=cloudstorage&page=dashboard&status=fail');
            exit();
        }

        if ($_GET['username'] == $username) {
            $userIds = [$user->id];
        } else {
            $userIds = [$tenants[$_GET['username']]];
        }
    }

    $userBuckets = DBController::getUserBuckets($userIds);
    $totalBucketCount = $userBuckets->count();
    $buckets = $userBuckets->isNotEmpty() ? $userBuckets->pluck('name', 'id')->toArray() : [];
    $billingObject = new BillingController();
    $billingPeriod = $billingObject->calculateBillingMonth($loggedInUserId, $packageId);

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey);
    // Get today's usage totals by default (since charts default to "Today")
    $today = date('Y-m-d');
    $totalUsage = $bucketObject->getTotalUsageForBillingPeriod($userIds, $today, $today);
    $bucketInfo = $bucketObject->getTotalBucketSizeForUser($buckets);
    $transferdata = $bucketObject->getUserTransferSummary($userIds);
    $bucketStats = $bucketObject->getUserBucketSummary($userIds, $billingPeriod['start'], $billingPeriod['end']);
    $formattedTotalBucketSize = $bucketObject->getPeakUsage($userIds);
    $dataIngress = HelperController::formatSizeUnits($totalUsage['total_bytes_received']);
    $dataEgress = HelperController::formatSizeUnits($totalUsage['total_bytes_sent']);
    $totalOps = htmlspecialchars($totalUsage['total_ops']);
    $topBuckets = HelperController::sortBucket($bucketInfo['buckets']);
    $dailyUsageChart = HelperController::prepareDailyUsageChart($billingPeriod['start'], $bucketStats);

    return [
        'billingPeriod' => $billingPeriod,
        'bucketStats' => $dailyUsageChart,
        'currentUsage' => HelperController::formatSizeUnits($bucketInfo['total_size']),
        'formattedTotalBucketSize' => $formattedTotalBucketSize,
        'dataEgress' => $dataEgress,
        'dataIngress' => $dataIngress,
        'latestUpdate' => $bucketInfo['latest_update'],
        'transferdata' => $transferdata,
        'topBuckets' => $topBuckets,
        'totalBucketCount' => $totalBucketCount,
        'totalObjects' => $bucketInfo['total_objects'],
        'usernames' => $usernames
    ];

