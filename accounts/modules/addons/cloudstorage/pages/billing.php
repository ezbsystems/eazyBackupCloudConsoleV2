<?php

    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\BillingController;

    require_once __DIR__ . '/../lib/Provision/E3CloudBackupProductBootstrap.php';
    require_once __DIR__ . '/../lib/Admin/E3CloudBackupPricing.php';
    require_once __DIR__ . '/../lib/Admin/E3CloudBackupBilling.php';
    require_once __DIR__ . '/../../eazybackup/lib/BrokerClientRestrict.php';

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $ca = new ClientArea();
    $loggedInUserId = $ca->getUserID();
    if (eazybackup_client_is_broker((int)$loggedInUserId)) {
        eazybackup_broker_redirect_dashboard();
    }
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || empty($product->username)) {
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
    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $vars['s3_region'] ?? 'ca-central-1');

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

    // e3 Cloud Backup preview (estimated next invoice + trial status).
    $cloudBackupPreview = null;
    $cloudBackupTrialState = null;
    $cloudBackupSuspended = false;
    try {
        $cloudBackupPreview = \WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling::dryRun((int) $loggedInUserId);
        if (!empty($cloudBackupPreview['service_id'])) {
            $cloudBackupTrialState = Capsule::table('s3_cloudbackup_trial_state')
                ->where('service_id', (int) $cloudBackupPreview['service_id'])
                ->first();
            $svc = Capsule::table('tblhosting')->where('id', (int) $cloudBackupPreview['service_id'])->first();
            if ($svc && (string) $svc->domainstatus === 'Suspended') {
                $cloudBackupSuspended = true;
            }
        }
    } catch (\Throwable $e) {
        // Best-effort. Storage billing page should still render if the e3 backup
        // billing layer hits an issue.
        $cloudBackupPreview = null;
    }

    return [
        'firstname' => $client->firstname,
        'billingPeriod' => $billingPeriod,
        'userAmount' => $userAmount,
        'bucketInfo' => $bucketInfo,
        'totalUsage' => $totalUsage,
        'peakUsage' => $peakUsage,
        'bucketStats' => $dailyUsageChart,
        'cloudBackupPreview' => $cloudBackupPreview,
        'cloudBackupTrialState' => $cloudBackupTrialState,
        'cloudBackupSuspended' => $cloudBackupSuspended,
    ];