<?php

    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
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
    $product = DBController::getProduct($clientId, $packageId);
    try {
        $prodArr = $product ? ['username' => $product->username ?? null] : null;
        logModuleCall('cloudstorage', 'dashboard_entry', [
            'userId' => $userId,
            'clientId' => $clientId,
            'packageId' => $packageId,
            'product' => $prodArr
        ], '');
    } catch (\Throwable $e) {}
    if (is_null($product) || is_null($product->username)) {
        try { logModuleCall('cloudstorage', 'dashboard_redirect_s3storage', ['clientId' => $clientId], ''); } catch (\Throwable $e) {}
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
    $displayPeriod = $billingObject->calculateDisplayPeriod($clientId, $packageId);
    $overdueNotice = $billingObject->getOverdueNotice($clientId, $packageId);

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $vars['s3_region'] ?? 'ca-central-1');
    // Get today's usage totals by default (since charts default to "Today")
    $today = date('Y-m-d');
    $totalUsage = $bucketObject->getTotalUsageForBillingPeriod($userIds, $today, $today);
    $bucketInfo = $bucketObject->getTotalBucketSizeForUser($buckets);
    $transferdata = $bucketObject->getUserTransferSummary($userIds);
    // Query through today to keep charts live regardless of invoice state
    // Use real usage snapshots for storage line + peak (honor tenant selection)
    $bucketStats = $bucketObject->getUserBucketSummary($userIds, $displayPeriod['start'], $displayPeriod['end_for_queries']);
    $peakForDisplay = $bucketObject->findPeakBucketUsage($userIds, [
        'start' => $displayPeriod['start'],
        'end' => $displayPeriod['end_for_queries']
    ]);
    $formattedTotalBucketSize = HelperController::formatSizeUnits($peakForDisplay->total_size ?? 0);
    // #region agent log
    try {
        $bucketStatsCount = is_array($bucketStats) ? count($bucketStats) : 0;
        $bucketStatsMax = 0.0;
        $bucketStatsMaxDate = null;
        if (is_array($bucketStats)) {
            foreach ($bucketStats as $row) {
                $value = isset($row['total_usage']) ? (float)$row['total_usage'] : 0.0;
                if ($value >= $bucketStatsMax) {
                    $bucketStatsMax = $value;
                    $bucketStatsMaxDate = $row['period'] ?? $bucketStatsMaxDate;
                }
            }
        }
        file_put_contents(
            '/var/www/eazybackup.ca/.cursor/debug.log',
            json_encode([
                'id' => uniqid('log_', true),
                'timestamp' => (int)round(microtime(true) * 1000),
                'location' => 'pages/dashboard.php:usage_series',
                'message' => 'Daily peak usage inputs',
                'data' => [
                    'selectedUsername' => $_GET['username'] ?? '',
                    'userIds' => $userIds,
                    'periodStart' => $displayPeriod['start'] ?? null,
                    'periodEnd' => $displayPeriod['end_for_queries'] ?? null,
                    'bucketStatsCount' => $bucketStatsCount,
                    'bucketStatsMax' => $bucketStatsMax,
                    'bucketStatsMaxDate' => $bucketStatsMaxDate,
                    'peakDate' => $peakForDisplay->exact_timestamp ?? null,
                    'peakBytes' => $peakForDisplay->total_size ?? 0
                ],
                'runId' => 'pre-fix',
                'hypothesisId' => 'H1'
            ]) . PHP_EOL,
            FILE_APPEND
        );
    } catch (\Throwable $e) {}
    // #endregion
    // #region agent log
    try {
        $peakDateForBreakdown = $peakForDisplay->exact_timestamp ? date('Y-m-d', strtotime($peakForDisplay->exact_timestamp)) : null;
        $perUserPeakTotals = [];
        if (!empty($peakDateForBreakdown) && !empty($userIds)) {
            $rows = Capsule::table('s3_bucket_stats_summary')
                ->selectRaw('user_id, SUM(total_usage) AS total_usage')
                ->whereIn('user_id', $userIds)
                ->whereDate('created_at', '=', $peakDateForBreakdown)
                ->groupBy('user_id')
                ->get();
            foreach ($rows as $row) {
                $perUserPeakTotals[(string)$row->user_id] = (float)($row->total_usage ?? 0);
            }
        }
        file_put_contents(
            '/var/www/eazybackup.ca/.cursor/debug.log',
            json_encode([
                'id' => uniqid('log_', true),
                'timestamp' => (int)round(microtime(true) * 1000),
                'location' => 'pages/dashboard.php:peak_breakdown',
                'message' => 'Peak date per-user totals',
                'data' => [
                    'peakDate' => $peakDateForBreakdown,
                    'userIds' => $userIds,
                    'perUserTotals' => $perUserPeakTotals
                ],
                'runId' => 'pre-fix',
                'hypothesisId' => 'H2'
            ]) . PHP_EOL,
            FILE_APPEND
        );
    } catch (\Throwable $e) {}
    // #endregion
    $dataIngress = HelperController::formatSizeUnits($totalUsage['total_bytes_received']);
    $dataEgress = HelperController::formatSizeUnits($totalUsage['total_bytes_sent']);
    $totalOps = htmlspecialchars($totalUsage['total_ops']);
    $topBuckets = HelperController::sortBucket($bucketInfo['buckets']);
    // #region agent log
    try {
        $transferCount = is_array($transferdata) ? count($transferdata) : 0;
        $transferSumReceived = 0.0;
        $transferSumSent = 0.0;
        $transferMaxReceived = 0.0;
        if (is_array($transferdata)) {
            foreach ($transferdata as $row) {
                $received = isset($row['total_bytes_received']) ? (float)$row['total_bytes_received'] : 0.0;
                $sent = isset($row['total_bytes_sent']) ? (float)$row['total_bytes_sent'] : 0.0;
                $transferSumReceived += $received;
                $transferSumSent += $sent;
                if ($received > $transferMaxReceived) {
                    $transferMaxReceived = $received;
                }
            }
        }
        file_put_contents(
            '/var/www/eazybackup.ca/.cursor/debug.log',
            json_encode([
                'id' => uniqid('log_', true),
                'timestamp' => (int)round(microtime(true) * 1000),
                'location' => 'pages/dashboard.php:transfer_summary',
                'message' => 'Transfer totals for initial render',
                'data' => [
                    'selectedUsername' => $_GET['username'] ?? '',
                    'userIds' => $userIds,
                    'today' => $today,
                    'transferCount' => $transferCount,
                    'transferSumReceived' => $transferSumReceived,
                    'transferSumSent' => $transferSumSent,
                    'transferMaxReceived' => $transferMaxReceived,
                    'totalUsageReceived' => $totalUsage['total_bytes_received'] ?? 0,
                    'totalUsageSent' => $totalUsage['total_bytes_sent'] ?? 0
                ],
                'runId' => 'pre-fix',
                'hypothesisId' => 'H3'
            ]) . PHP_EOL,
            FILE_APPEND
        );
    } catch (\Throwable $e) {}
    // #endregion
    $dailyUsageChart = HelperController::prepareDailyUsageChart($displayPeriod['start'], $bucketStats);

    return [
        'billingPeriod' => $displayPeriod,
        'overdueNotice' => $overdueNotice,
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
        'usernames' => $usernames,
        'PRIMARY_USERNAME' => $username
    ];

