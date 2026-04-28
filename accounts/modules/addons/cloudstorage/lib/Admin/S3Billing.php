<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use DateTime;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\BillingController;

class S3Billing {

    private static $module = 'cloudstorage';

    /**
     * Gather Billing Data.
     *
     * @return object|null
     */
    public function gatherBillingData($packageId)
    {
        $billingData = [];
        $updateResults = [];
        $products = Capsule::table('tblhosting')->where('packageid', $packageId)->where('domainstatus', 'Active')->get();
        $module = DBController::getResult('tbladdonmodules', [
            ['module', '=', 'cloudstorage']
        ]);

        if (count($module) == 0) {
            logModuleCall(self::$module, __FUNCTION__, $packageId, 'Please enable the cloudstorage addon module.');
            exit;
        }
        $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
        $cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
        $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
        $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
        $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
        $storageBaseFeeRaw = $module->where('setting', 'storage_base_fee_cad')->pluck('value')->first();
        $storageOverageRateRaw = $module->where('setting', 'storage_overage_per_gib_cad')->pluck('value')->first();
        $storageBaseFee = (is_numeric($storageBaseFeeRaw) && (float)$storageBaseFeeRaw > 0)
            ? (float)$storageBaseFeeRaw
            : 9.00;
        $storageOverageRate = (is_numeric($storageOverageRateRaw) && (float)$storageOverageRateRaw > 0)
            ? (float)$storageOverageRateRaw
            : 0.008789;
        $currentTime = (new DateTime())->format('Y-m-d H:i:s');
        $currentDate = (new DateTime())->format('Y-m-d');
        $moduleSettings = [
            's3Endpoint' => $s3Endpoint,
            'cephAdminUser' => $cephAdminUser,
            'cephAdminAccessKey' => $cephAdminAccessKey,
            'cephAdminSecretKey' => $cephAdminSecretKey,
            'encryptionKey' => $encryptionKey,
            'storageBaseFee' => $storageBaseFee,
            'storageOverageRate' => $storageOverageRate,
        ];

        foreach ($products as $product) {
            $username = $product->username;
            // get the user from db
            $user = DBController::getUser($username);
            if (is_null($user)) {
                logModuleCall(self::$module, __FUNCTION__, $product->userid, 'User not found in db.');
                continue;
            }
                $baseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($user);
                if (empty($baseUid)) { $baseUid = $username; } // legacy fallback
                $params = [
                    'uid' => (!empty($user->tenant_id) ? ($user->tenant_id . '$' . $baseUid) : $baseUid),
                    'stats' => true
                ];
            $bucketStatsData = AdminOps::getBucketInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
            if ($bucketStatsData['status'] == 'fail' || count($bucketStatsData['data']) == 0) {
                if ($bucketStatsData['status'] == 'fail') {
                    logModuleCall(self::$module, __FUNCTION__, $packageId, $bucketStatsData['message']);
                    continue;
                }
                if (count($bucketStatsData['data']) == 0) {
                    logModuleCall(self::$module, __FUNCTION__, $packageId, 'Buckets not found for user ' . $username);
                }
            }


            $s3buckets = [];
            $totalBucketSize = 0;
            $userId = $user->id;
            foreach ($bucketStatsData['data'] as $bucket) {
                try {
                    $currentBucketSize = $bucket['usage']['rgw.main']['size'] ?? 0;
                    $totalBucketSize += $currentBucketSize;
                    $creationDateTime = new DateTime($bucket['creation_time']);
                    $creationTime = $creationDateTime->format('Y-m-d H:i:s');
                    $bucketId = $this->handleBucketData($userId, $bucket, $moduleSettings, $creationTime);
                    if ($bucketId == 'fail') {
                        continue;
                    }
                    $bucketStatsValues = [
                        'bucket_id' => $bucketId,
                        'user_id' => $userId,
                        'num_objects' => $bucket['usage']['rgw.main']['num_objects'],
                        'size' => $currentBucketSize,
                        'size_actual' => $bucket['usage']['rgw.main']['size_actual'],
                        'size_utilized' => $bucket['usage']['rgw.main']['size_utilized'],
                        'size_kb' => $bucket['usage']['rgw.main']['size_kb'],
                        'size_kb_actual' => $bucket['usage']['rgw.main']['size_kb_actual'],
                        'size_kb_utilized' => $bucket['usage']['rgw.main']['size_kb_utilized'],
                        'created_at' => $currentTime
                    ];

                    DBController::saveBucketStats($bucketStatsValues);

                    // check bucket id record exist
                    $bucketStatsSummary = Capsule::table('s3_bucket_stats_summary')->where([
                        ['user_id', '=', $userId],
                        ['bucket_id', '=', $bucketId],
                    ])
                    ->whereDate('created_at', $currentDate)
                    ->first();

                    if (is_null($bucketStatsSummary)) {
                        DBController::saveBucketStatsSummary([
                            'user_id' => $userId,
                            'bucket_id' => $bucketId,
                            'total_usage' => $currentBucketSize,
                            'created_at' => $currentTime,
                        ]);
                    } else {
                        Capsule::table('s3_bucket_stats_summary')->where([
                            ['user_id', '=', $userId],
                            ['bucket_id', '=', $bucketId],
                        ])
                        ->whereDate('created_at', $currentDate)
                        ->update([
                            'total_usage' => $currentBucketSize
                        ]);
                    }
                    $s3buckets[] = $bucket['id'];

                } catch (Exception $e) {
                    logModuleCall(self::$module, __FUNCTION__, $packageId, $e->getMessage());
                }
            }

            // get the buckets
            $userBuckets = DBController::getResult('s3_buckets', [
                ['user_id', '=', $userId]
            ], [
                's3_id'
            ])->pluck('s3_id')->toArray();

            // check the difference between db buckets and s3 buckets
            $toBeDeleteBuckets = array_diff($userBuckets, $s3buckets);
            $toBeDeleteBuckets = array_values($toBeDeleteBuckets);

            if (count($toBeDeleteBuckets)) {
                // delete the buckets from db
                Capsule::table('s3_buckets')->where('user_id', $userId)->whereIn('s3_id', $toBeDeleteBuckets)->delete();
            }

            // handle tenants
            $totalBucketSize += $this->handleTenants($moduleSettings, $userId, $currentTime);
            $updateResults[] = $this->updateProductPrice($product, $totalBucketSize, $userId, $storageBaseFee, $storageOverageRate);

            $billingData[$username] = ['bucket_size' => $totalBucketSize];
        }

        $this->exportBucketUsage($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $currentTime);

        return ['billingData' => $billingData, 'updateResults' => $updateResults];
    }

    /**
     * Update Product Price.
     *
     * @return object|null
     */
    private function updateProductPrice($product, $totalBucketSize, $userId, $baseFee = 9.00, $overageRatePerGiB = 0.008789)
    {
        $result = [
            'userid' => $product->userid,
            'new_amount' => null,
            'update_status' => false
        ];

        // Defensive defaults if callers pass non-positive values
        if (!is_numeric($baseFee) || (float)$baseFee <= 0) {
            $baseFee = 9.00;
        }
        if (!is_numeric($overageRatePerGiB) || (float)$overageRatePerGiB <= 0) {
            $overageRatePerGiB = 0.008789;
        }
        $baseFee = (float)$baseFee;
        $overageRatePerGiB = (float)$overageRatePerGiB;

        $bucketSizeTiB = $totalBucketSize / (1024 * 1024 * 1024 * 1024);
        $amount = $this->computeAmountForBytes($totalBucketSize, $baseFee, $overageRatePerGiB);
        $result['new_amount'] = $amount;

        logModuleCall(self::$module, __FUNCTION__, [
            'user_id' => $userId,
            'service_id' => $product->id ?? null,
            'usage_bytes' => (int)$totalBucketSize,
            'usage_tib' => round($bucketSizeTiB, 6),
            'base_fee_cad' => $baseFee,
            'overage_rate_per_gib_cad' => $overageRatePerGiB,
        ], [
            'computed_amount' => $amount,
        ]);

        try {
            // Record the computed amount snapshot for this run
            DBController::savePrices([
                'user_id' => $userId,
                'amount' => $amount,
                // Persist instantaneous usage bytes if the column exists (DBController will strip if absent)
                'usage_bytes' => (int)$totalBucketSize
            ]);

            // Use a rolling display period decoupled from nextduedate, so overdue cycles do not freeze updates
            $billingController = new BillingController();
            $displayPeriod = $billingController->calculateDisplayPeriod((int)$product->userid, (int)$product->packageid);
            $rangeStart = $displayPeriod['start'] ?? date('Y-m-d', strtotime('-1 month'));
            $rangeEnd = $displayPeriod['end_for_queries'] ?? date('Y-m-d'); // today

            // Capture the in-window MAX before the self-healing recompute, for audit visibility.
            $priorMax = DBController::getHighestAmount($userId, $rangeStart, $rangeEnd);

            // Self-healing recompute: rebuild amount from each in-cycle row's usage_bytes using
            // the current base fee + overage rate so a rate change in addon settings takes effect
            // on the very next cron run. Historical (pre-migration) rows with usage_bytes = 0
            // and rows outside the window are never touched.
            $recompute = $this->recomputeInWindowPrices(
                (int)$userId,
                $rangeStart,
                $rangeEnd,
                $baseFee,
                $overageRatePerGiB
            );

            $highestAmount = DBController::getHighestAmount($userId, $rangeStart, $rangeEnd);
            if (empty($highestAmount)) {
                $highestAmount = $amount;
            }

            Capsule::table('tblhosting')->where('id', $product->id)->update(['amount' => $highestAmount]);
            $result['update_status'] = true;

            logModuleCall(self::$module, 'updateProductPrice_recompute', [
                'user_id' => $userId,
                'service_id' => $product->id ?? null,
                'package_id' => $product->packageid ?? null,
                'window_start' => $rangeStart,
                'window_end' => $rangeEnd,
                'base_fee_cad' => $baseFee,
                'overage_rate_per_gib_cad' => $overageRatePerGiB,
            ], [
                'prior_max' => $priorMax,
                'rows_updated' => $recompute['updated'] ?? 0,
                'recompute_skipped_reason' => $recompute['skipped_reason'] ?? null,
                'post_recompute_max' => $highestAmount,
                'final_amount_written' => $highestAmount,
            ]);
        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, [
                $product,
                $totalBucketSize,
                $userId
            ], $e->getMessage());
        }

        return $result;
    }

    /**
     * Compute the billable monthly amount (CAD) for a given usage in bytes,
     * using the configured base fee and per-GiB overage rate.
     *
     * Pricing model:
     *   - <= 1 TiB: flat $baseFee (covers the first 1 TiB)
     *   - > 1 TiB:  $baseFee + (excess GiB * $overageRatePerGiB)
     *
     * Result is rounded UP to the next cent to match prior behavior. The math
     * here is intentionally mirrored in recomputeInWindowPrices()'s SQL so the
     * single-row PHP path and the bulk SQL path produce identical cents.
     *
     * @param int|float $bytes
     * @param float $baseFee
     * @param float $overageRatePerGiB
     * @return float
     */
    private function computeAmountForBytes($bytes, $baseFee, $overageRatePerGiB)
    {
        $bytes = (int)$bytes;
        $tib = $bytes / (1024 * 1024 * 1024 * 1024);
        $gib = $bytes / (1024 * 1024 * 1024);

        if ($tib <= 1) {
            $amount = (float)$baseFee;
        } else {
            $amount = (float)$baseFee + ($gib - 1024) * (float)$overageRatePerGiB;
        }

        return ceil($amount * 100) / 100;
    }

    /**
     * Recompute s3_prices.amount for one user's in-cycle snapshots from each
     * row's stored usage_bytes using the live base fee + overage rate.
     *
     * Atomic single-statement UPDATE. Restricted to:
     *   - the given user_id,
     *   - rows where usage_bytes > 0 (excludes pre-migration / default rows),
     *   - rows whose created_at falls within the rolling display window.
     *
     * The CASE expression mirrors computeAmountForBytes() exactly:
     *   - usage_bytes <= 1 TiB (1024^4 = 1099511627776) -> flat base fee
     *   - otherwise           -> base + (gib - 1024) * rate
     * CEIL(... * 100) / 100 mirrors PHP's ceil($amount * 100) / 100.
     *
     * @param int $userId
     * @param string $rangeStart  Y-m-d
     * @param string $rangeEnd    Y-m-d
     * @param float $baseFee
     * @param float $overageRatePerGiB
     * @return array{updated:int, skipped_reason?:string}
     */
    private function recomputeInWindowPrices($userId, $rangeStart, $rangeEnd, $baseFee, $overageRatePerGiB)
    {
        try {
            if (!Capsule::schema()->hasColumn('s3_prices', 'usage_bytes')) {
                return ['updated' => 0, 'skipped_reason' => 'usage_bytes_missing'];
            }
        } catch (\Throwable $e) {
            return ['updated' => 0, 'skipped_reason' => 'schema_check_failed'];
        }

        $sql = "
            UPDATE s3_prices
            SET amount = CEIL(
                CASE
                    WHEN usage_bytes <= 1099511627776
                        THEN ?
                    ELSE ? + ((usage_bytes / 1073741824.0) - 1024) * ?
                END * 100
            ) / 100
            WHERE user_id = ?
              AND usage_bytes > 0
              AND created_at >= ?
              AND created_at <= ?
        ";

        try {
            $updated = Capsule::connection()->affectingStatement($sql, [
                (float)$baseFee,
                (float)$baseFee,
                (float)$overageRatePerGiB,
                (int)$userId,
                $rangeStart . ' 00:00:00',
                $rangeEnd   . ' 23:59:59',
            ]);
            return ['updated' => (int)$updated];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'recomputeInWindowPrices_fail', [
                'user_id' => $userId,
                'window_start' => $rangeStart,
                'window_end' => $rangeEnd,
                'base_fee_cad' => $baseFee,
                'overage_rate_per_gib_cad' => $overageRatePerGiB,
            ], $e->getMessage());
            return ['updated' => 0, 'skipped_reason' => 'sql_error'];
        }
    }

    /**
     * Export Bucket Usage.
     *
     * @return object|null
     */
    private function exportBucketUsage($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $currentTime)
    {
        $params = [
            'show_entries' => true
        ];

        $data = AdminOps::getUsage($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);

        if (isset($data['data']['entries'])) {
            $bucketUsageRecords = [];
            foreach ($data['data']['entries'] as $entry) {
                foreach ($entry['buckets'] as $bucketData) {
                    $bucketName = $bucketData['bucket'];
                    $owner = $bucketData['owner'];

                    if ($bucketName === "" || $bucketName === "-") {
                        continue;
                    }

                    // Use composite key (owner|bucket) to avoid cross-user collisions on same bucket name
                    $compositeKey = $owner . '|' . $bucketName;

                    if (!isset($bucketUsageRecords[$compositeKey])) {
                        $bucketUsageRecords[$compositeKey] = [
                            'owner' => $owner,
                            'bucket' => $bucketName,
                            'bytes_sent' => 0,
                            'bytes_received' => 0,
                            'ops' => 0,
                            'successful_ops' => 0
                        ];
                    }

                    foreach ($bucketData['categories'] as $category) {
                        $bucketUsageRecords[$compositeKey]['bytes_sent'] += $category['bytes_sent'];
                        $bucketUsageRecords[$compositeKey]['bytes_received'] += $category['bytes_received'];
                        $bucketUsageRecords[$compositeKey]['ops'] += $category['ops'];
                        $bucketUsageRecords[$compositeKey]['successful_ops'] += $category['successful_ops'];
                    }

                }
            }

            if (count($bucketUsageRecords)) {
                foreach ($bucketUsageRecords as $compositeKey => $bucketUsageRecord) {
                    $bucketName = $bucketUsageRecord['bucket'];
                    $owner = $bucketUsageRecord['owner'];

                    // Resolve owner user by matching RGW uid to (tenant_id'$'username) or username (no tenant)
                    $ownerUser = Capsule::table('s3_users')
                        ->select('id', 'username', 'tenant_id')
                        ->whereRaw("(CASE WHEN (tenant_id IS NULL OR tenant_id = '') THEN username ELSE CONCAT(tenant_id, '$', username) END) = ?", [$owner])
                        ->first();

                    $bucketQuery = Capsule::table('s3_buckets')->select('id', 'user_id')->where('name', $bucketName);
                    if (!is_null($ownerUser)) {
                        $bucketQuery->where('user_id', $ownerUser->id);
                    }
                    $bucket = $bucketQuery->first();
                    if (is_null($bucket)) {
                        // No exact match for this owner+bucket; skip to avoid misattribution
                        continue;
                    }
                    $userId = $bucket->user_id;

                    $transferStats = Capsule::table('s3_transfer_stats')->where([
                        ['user_id', '=', $userId],
                        ['bucket_id', '=', $bucket->id],
                    ])
                    ->orderBy('id', 'desc')
                    ->first();

                    if (!is_null($transferStats)) {
                        $bytesSentSummary = $bucketUsageRecord['bytes_sent'] - $transferStats->bytes_sent;
                        $bytesReceivedSummary = $bucketUsageRecord['bytes_received'] - $transferStats->bytes_received;
                        $opsSummary = $bucketUsageRecord['ops'] - $transferStats->ops;
                        $successfulOpsSummary = $bucketUsageRecord['successful_ops'] - $transferStats->successful_ops;
                    } else {
                        $bytesSentSummary = $bucketUsageRecord['bytes_sent'];
                        $bytesReceivedSummary = $bucketUsageRecord['bytes_received'];
                        $opsSummary = $bucketUsageRecord['ops'];
                        $successfulOpsSummary = $bucketUsageRecord['successful_ops'];
                    }

                    $transferStats = [
                        'user_id' => $userId,
                        'bucket_id' => $bucket->id,
                        'bytes_sent' => $bucketUsageRecord['bytes_sent'],
                        'bytes_received' => $bucketUsageRecord['bytes_received'],
                        'ops' => $bucketUsageRecord['ops'],
                        'successful_ops' => $bucketUsageRecord['successful_ops'],
                        'created_at' => $currentTime
                    ];

                    $transferStatsSummary = [
                        'user_id' => $userId,
                        'bucket_id' => $bucket->id,
                        'bytes_sent' => $bytesSentSummary,
                        'bytes_received' => $bytesReceivedSummary,
                        'ops' => $opsSummary,
                        'successful_ops' => $successfulOpsSummary,
                        'created_at' => $currentTime
                    ];

                    DBController::saveTransferStats($transferStats);
                    DBController::saveTransferStatsSummary($transferStatsSummary);
                }
            }

        }
    }

    /**
    * Handle Bucket Data
    *
    * @param integer $userId
    * @param array $bucket
    * @param array $moduleSettings
    *
    * @return string
    */
    protected function handleBucketData($userId, $bucket, $moduleSettings, $creationTime)
    {
        $bucketStringId = $bucket['id'];
        $bucketName = $bucket['bucket'];

        $bucketObject = new BucketController(
            $moduleSettings['s3Endpoint'],
            $moduleSettings['cephAdminUser'],
            $moduleSettings['cephAdminAccessKey'],
            $moduleSettings['cephAdminSecretKey'],
            $moduleSettings['s3_region'] ?? 'ca-central-1'
        );
        $s3Connection = $bucketObject->connectS3Client($userId, $moduleSettings['encryptionKey']);
        if ($s3Connection['status'] == 'fail') {
            logModuleCall(self::$module, __FUNCTION__, $bucket, $s3Connection['message']);

            return 'fail';
        }

        $bucket = DBController::getBucket($bucketStringId);

        if (is_null($bucket)) {
            // get the bucket versioning details
            $bucketVersioning = $bucketObject->getBucketVersioning($bucketName);

            if ($bucketVersioning['status'] == 'fail') {
                logModuleCall(self::$module, __FUNCTION__, $bucket, $bucketVersioning['message']);

                return 'fail';
            }

            // get the bucket object lock configuration
            $bucketObjectLockConfiguration = $bucketObject->getBucketObjectLockConfiguration($bucketName);

            $bucketId = DBController::saveBucket([
                'user_id'             => $userId,
                'name'                => $bucketName,
                's3_id'               => $bucketStringId,
                'versioning'          => $bucketVersioning['version_status'],
                'object_lock_enabled' => $bucketObjectLockConfiguration['object_lock_enabled'],
                'is_active'           => 1,
                'created_at'          => $creationTime
            ]);
        } else {
            $bucketId = $bucket->id;
        }

        return $bucketId;
    }

    /**
    * Handle Tenants
    *
    * @param array $moduleSettings
    * @param integer $parentUserId
    * @param string $currentDateTime
    *
    * @return string
    */
    protected function handleTenants($moduleSettings, $parentUserId, $currentDateTime)
    {
        $totalBucketSize = 0;
        try {
            $tenantCols = ['id', 'username', 'tenant_id'];
            try {
                if (Capsule::schema()->hasColumn('s3_users', 'ceph_uid')) {
                    $tenantCols[] = 'ceph_uid';
                }
            } catch (\Throwable $_) {}
            $tenants = Capsule::table('s3_users')
                ->select($tenantCols)
                ->where('parent_id', $parentUserId)
                ->get();

            foreach($tenants as $tenant) {
                $username = $tenant->username;
                $baseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($tenant);
                if (empty($baseUid)) { $baseUid = $username; } // legacy fallback
                $params = [
                    'uid' => $baseUid,
                    'stats' => true
                ];
                if (!empty($tenant->tenant_id)) {
                    $params['uid'] = $tenant->tenant_id . '$' . $baseUid;
                }
                $bucketStatsData = AdminOps::getBucketInfo($moduleSettings['s3Endpoint'], $moduleSettings['cephAdminAccessKey'], $moduleSettings['cephAdminSecretKey'], $params);
                if ($bucketStatsData['status'] == 'fail' || count($bucketStatsData['data']) == 0) {
                    if ($bucketStatsData['status'] == 'fail') {
                        logModuleCall(self::$module, __FUNCTION__, $parentUserId, $bucketStatsData['message']);
                        continue;
                    }
                    if (count($bucketStatsData['data']) == 0) {
                        logModuleCall(self::$module, __FUNCTION__, $parentUserId, 'Buckets not found for tenant ' . $username);
                    }
                }
                $userId = $tenant->id;
                $s3buckets = [];
                foreach ($bucketStatsData['data'] as $bucket) {
                    try {
                        $currentBucketSize = $bucket['usage']['rgw.main']['size'] ?? 0;
                        $totalBucketSize += $currentBucketSize;
                        $creationDateTime = new DateTime($bucket['creation_time']);
                        $creationTime = $creationDateTime->format('Y-m-d H:i:s');
                        $bucketStringId = $bucket['id'];
                        $bucketName = $bucket['bucket'];

                        $bucketObject = new BucketController(
                            $moduleSettings['s3Endpoint'],
                            $moduleSettings['cephAdminUser'],
                            $moduleSettings['cephAdminAccessKey'],
                            $moduleSettings['cephAdminSecretKey'],
                            $moduleSettings['s3_region'] ?? 'ca-central-1'
                        );
                        $s3Connection = $bucketObject->connectS3Client($userId, $moduleSettings['encryptionKey']);
                        if ($s3Connection['status'] == 'fail') {
                            logModuleCall(self::$module, __FUNCTION__, [$parentUserId, $userId], $s3Connection['message']);
                            continue;
                        }

                        // check bucket exist in db
                        $dbBucket = DBController::getBucket($bucketStringId);

                        if (is_null($dbBucket)) {
                            // get the bucket versioning details
                            $bucketVersioning = $bucketObject->getBucketVersioning($bucketName);

                            if ($bucketVersioning['status'] == 'fail') {
                                logModuleCall(self::$module, __FUNCTION__, $parentUserId, $bucketVersioning['message']);
                                continue;
                            }

                            // get the bucket object lock configuration
                            $bucketObjectLockConfiguration = $bucketObject->getBucketObjectLockConfiguration($bucketName);
                            $bucketId = DBController::saveBucket([
                                'user_id'             => $userId,
                                'name'                => $bucketName,
                                's3_id'               => $bucketStringId,
                                'versioning'          => $bucketVersioning['version_status'],
                                'object_lock_enabled' => $bucketObjectLockConfiguration['object_lock_enabled'],
                                'is_active'           => 1,
                                'created_at'          => $creationTime
                            ]);
                        } else {
                            $bucketId = $dbBucket->id;
                        }
                        $currentDate = (new DateTime($currentDateTime))->format('Y-m-d');
                        $bucketStatsValues = [
                            'bucket_id' => $bucketId,
                            'user_id' => $userId,
                            'num_objects' => $bucket['usage']['rgw.main']['num_objects'],
                            'size' => $currentBucketSize,
                            'size_actual' => $bucket['usage']['rgw.main']['size_actual'],
                            'size_utilized' => $bucket['usage']['rgw.main']['size_utilized'],
                            'size_kb' => $bucket['usage']['rgw.main']['size_kb'],
                            'size_kb_actual' => $bucket['usage']['rgw.main']['size_kb_actual'],
                            'size_kb_utilized' => $bucket['usage']['rgw.main']['size_kb_utilized'],
                            'created_at' => $currentDateTime
                        ];

                        DBController::saveBucketStats($bucketStatsValues);

                        // check bucket id record exist
                        $bucketStatsSummary = Capsule::table('s3_bucket_stats_summary')->where([
                            ['user_id', '=', $userId],
                            ['bucket_id', '=', $bucketId],
                        ])
                        ->whereDate('created_at', $currentDate)
                        ->first();

                        if (is_null($bucketStatsSummary)) {
                            DBController::saveBucketStatsSummary([
                                'user_id' => $userId,
                                'bucket_id' => $bucketId,
                                'total_usage' => $currentBucketSize,
                                'created_at' => $currentDateTime,
                            ]);
                        } else {
                            Capsule::table('s3_bucket_stats_summary')->where([
                                ['user_id', '=', $userId],
                                ['bucket_id', '=', $bucketId],
                            ])
                            ->whereDate('created_at', $currentDate)
                            ->update([
                                'total_usage' => $currentBucketSize
                            ]);
                        }
                        $s3buckets[] = $bucket['id'];

                    } catch (Exception $e) {
                        logModuleCall(self::$module, __FUNCTION__, $parentUserId, $e->getMessage());
                    }
                }

                // get the buckets
                $userBuckets = DBController::getResult('s3_buckets', [
                    ['user_id', '=', $userId]
                ], [
                    's3_id'
                ])->pluck('s3_id')->toArray();

                // check the difference between db buckets and s3 buckets
                $toBeDeleteBuckets = array_diff($userBuckets, $s3buckets);
                $toBeDeleteBuckets = array_values($toBeDeleteBuckets);

                if (count($toBeDeleteBuckets)) {
                    // delete the buckets from db
                    Capsule::table('s3_buckets')->where('user_id', $userId)->whereIn('s3_id', $toBeDeleteBuckets)->delete();
                }
            }

        } catch (Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, $params, $e->getMessage());
        }

        return $totalBucketSize;
    }
}