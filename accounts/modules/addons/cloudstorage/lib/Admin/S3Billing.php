<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use DateTime;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\BillingController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

class S3Billing {

    private static $module = 'cloudstorage';

    /**
     * Whether a WHMCS Cloud Storage service is marked complimentary (billing exempt).
     */
    public static function isServiceBillingExempt(int $serviceId): bool
    {
        if ($serviceId <= 0) {
            return false;
        }
        try {
            if (!Capsule::schema()->hasTable('s3_billing_flags')) {
                return false;
            }
            $row = Capsule::table('s3_billing_flags')->where('service_id', $serviceId)->first();
            return $row !== null && (int) ($row->billing_exempt ?? 0) === 1;
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'isServiceBillingExempt_fail', ['service_id' => $serviceId], $e->getMessage());
            return false;
        }
    }

    /**
     * Force $0 on the WHMCS service and zero in-window s3_prices snapshots.
     * Used by the admin exempt toggle (immediate) and by the billing cron.
     *
     * @param object $product tblhosting row (needs id, userid, packageid)
     * @param int $s3UserId primary s3_users.id for price snapshots
     * @return array{update_status:bool, in_window_rows_zeroed:int, final_amount_written:float}
     */
    public function applyComplimentaryBilling($product, int $s3UserId): array
    {
        $result = [
            'update_status' => false,
            'in_window_rows_zeroed' => 0,
            'final_amount_written' => 0.00,
        ];
        try {
            $billingController = new BillingController();
            $displayPeriod = $billingController->calculateDisplayPeriod((int) $product->userid, (int) $product->packageid);
            $rangeStart = $displayPeriod['start'] ?? date('Y-m-d', strtotime('-1 month'));
            $rangeEnd = $displayPeriod['end_for_queries'] ?? date('Y-m-d');

            DBController::savePrices([
                'user_id' => $s3UserId,
                'amount' => 0.00,
                'usage_bytes' => 0,
            ]);
            $zeroed = $this->zeroInWindowPrices($s3UserId, $rangeStart, $rangeEnd);
            Capsule::table('tblhosting')->where('id', $product->id)->update(['amount' => 0.00]);

            $result['update_status'] = true;
            $result['in_window_rows_zeroed'] = (int) ($zeroed['updated'] ?? 0);
            $result['final_amount_written'] = 0.00;
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'applyComplimentaryBilling_fail', [
                'service_id' => $product->id ?? null,
                'user_id' => $s3UserId,
            ], $e->getMessage());
        }
        return $result;
    }

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
                    $bucketName = (string) ($bucket['bucket'] ?? '');
                    if (self::isMs365BillingExemptBucket($bucketName)) {
                        continue;
                    }
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

        $this->collectMs365VaultStatsForDisplay($moduleSettings, $currentTime);

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

        // Complimentary / billing-exempt services: always $0, skip MAX-over-window.
        if (self::isServiceBillingExempt((int) ($product->id ?? 0))) {
            $applied = $this->applyComplimentaryBilling($product, (int) $userId);
            $result['new_amount'] = 0.00;
            $result['update_status'] = (bool) ($applied['update_status'] ?? false);
            logModuleCall(self::$module, 'updateProductPrice_billing_exempt', [
                'user_id' => $userId,
                'service_id' => $product->id ?? null,
                'package_id' => $product->packageid ?? null,
                'usage_bytes' => (int) $totalBucketSize,
            ], [
                'in_window_rows_zeroed' => $applied['in_window_rows_zeroed'] ?? 0,
                'final_amount_written' => 0.00,
            ]);
            return $result;
        }

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
            $billingController = new BillingController();
            $displayPeriod = $billingController->calculateDisplayPeriod((int)$product->userid, (int)$product->packageid);
            $rangeStart = $displayPeriod['start'] ?? date('Y-m-d', strtotime('-1 month'));
            $rangeEnd = $displayPeriod['end_for_queries'] ?? date('Y-m-d');

            // Live zero usage: no base fee; bypass MAX-over-window so stale $9 snapshots cannot resurrect.
            if ((int) $totalBucketSize <= 0) {
                DBController::savePrices([
                    'user_id' => $userId,
                    'amount' => 0.00,
                    'usage_bytes' => 0,
                ]);
                $zeroed = $this->zeroInWindowPrices((int) $userId, $rangeStart, $rangeEnd);
                Capsule::table('tblhosting')->where('id', $product->id)->update(['amount' => 0.00]);
                $result['update_status'] = true;
                logModuleCall(self::$module, 'updateProductPrice_zero_usage', [
                    'user_id' => $userId,
                    'service_id' => $product->id ?? null,
                    'package_id' => $product->packageid ?? null,
                    'window_start' => $rangeStart,
                    'window_end' => $rangeEnd,
                ], [
                    'in_window_rows_zeroed' => $zeroed['updated'] ?? 0,
                    'final_amount_written' => 0.00,
                ]);
                return $result;
            }

            // Record the computed amount snapshot for this run
            DBController::savePrices([
                'user_id' => $userId,
                'amount' => $amount,
                // Persist instantaneous usage bytes if the column exists (DBController will strip if absent)
                'usage_bytes' => (int)$totalBucketSize
            ]);

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
     * Pricing model (usage-gated):
     *   - 0 bytes: $0 (no base fee until billable usage exists)
     *   - > 0 and <= 1 TiB: flat $baseFee (covers the first 1 TiB)
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
        if ($bytes <= 0) {
            return 0.00;
        }
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
     *   - usage_bytes = 0 -> $0
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
                    WHEN usage_bytes = 0
                        THEN 0
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
     * Zero in-window s3_prices snapshots for a user with live zero billable usage.
     * Clears stale base-fee rows from the prior pricing model within the display window.
     *
     * @return array{updated:int, skipped_reason?:string}
     */
    private function zeroInWindowPrices($userId, $rangeStart, $rangeEnd)
    {
        try {
            $updated = Capsule::table('s3_prices')
                ->where('user_id', (int) $userId)
                ->where('created_at', '>=', $rangeStart . ' 00:00:00')
                ->where('created_at', '<=', $rangeEnd . ' 23:59:59')
                ->update(['amount' => 0.00]);
            return ['updated' => (int) $updated];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'zeroInWindowPrices_fail', [
                'user_id' => $userId,
                'window_start' => $rangeStart,
                'window_end' => $rangeEnd,
            ], $e->getMessage());
            return ['updated' => 0, 'skipped_reason' => 'sql_error'];
        }
    }

    /**
     * Export per-bucket transfer usage into s3_transfer_stats(_summary).
     *
     * IMPORTANT: Query RGW usage per uid. A cluster-wide /admin/usage?show-entries
     * dump returns ~2x the true totals for some tenanted buckets (verified against
     * per-uid usage and on-disk size). Collecting per uid avoids that inflation.
     *
     * @param string $s3Endpoint
     * @param string $cephAdminAccessKey
     * @param string $cephAdminSecretKey
     * @param string $currentTime
     * @return void
     */
    private function exportBucketUsage($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $currentTime)
    {
        $users = Capsule::table('s3_users')
            ->select('id', 'username', 'tenant_id', 'ceph_uid', 'is_active')
            ->when(
                Capsule::schema()->hasColumn('s3_users', 'is_active'),
                function ($q) {
                    $q->where(function ($inner) {
                        $inner->where('is_active', 1)->orWhereNull('is_active');
                    });
                }
            )
            ->get();

        foreach ($users as $user) {
            $uid = $this->resolveUsageUid($user);
            if ($uid === '') {
                continue;
            }

            $data = AdminOps::getUsage($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, [
                'uid' => $uid,
                'show_entries' => true,
            ]);

            if (($data['status'] ?? '') !== 'success' || empty($data['data']['entries'])) {
                continue;
            }

            $bucketUsageRecords = [];
            foreach ($data['data']['entries'] as $entry) {
                foreach (($entry['buckets'] ?? []) as $bucketData) {
                    $bucketName = (string) ($bucketData['bucket'] ?? '');
                    if ($bucketName === '' || $bucketName === '-') {
                        continue;
                    }

                    if (!isset($bucketUsageRecords[$bucketName])) {
                        $bucketUsageRecords[$bucketName] = [
                            'bucket' => $bucketName,
                            'bytes_sent' => 0,
                            'bytes_received' => 0,
                            'ops' => 0,
                            'successful_ops' => 0,
                        ];
                    }

                    foreach (($bucketData['categories'] ?? []) as $category) {
                        $bucketUsageRecords[$bucketName]['bytes_sent'] += (int) ($category['bytes_sent'] ?? 0);
                        $bucketUsageRecords[$bucketName]['bytes_received'] += (int) ($category['bytes_received'] ?? 0);
                        $bucketUsageRecords[$bucketName]['ops'] += (int) ($category['ops'] ?? 0);
                        $bucketUsageRecords[$bucketName]['successful_ops'] += (int) ($category['successful_ops'] ?? 0);
                    }
                }
            }

            foreach ($bucketUsageRecords as $bucketUsageRecord) {
                $bucket = Capsule::table('s3_buckets')
                    ->select('id', 'user_id')
                    ->where('name', $bucketUsageRecord['bucket'])
                    ->where('user_id', (int) $user->id)
                    ->first();
                if ($bucket === null) {
                    continue;
                }

                $this->persistTransferUsageSample(
                    (int) $user->id,
                    (int) $bucket->id,
                    $bucketUsageRecord,
                    $currentTime
                );
            }
        }
    }

    /**
     * Build the RGW uid used for Admin Ops usage queries.
     */
    private function resolveUsageUid($user): string
    {
        $base = HelperController::stripCephTenantPrefix(HelperController::resolveCephBaseUid($user));
        if ($base === '') {
            return '';
        }
        $tenantId = trim((string) ($user->tenant_id ?? ''));
        if ($tenantId !== '') {
            return $tenantId . '$' . $base;
        }
        return $base;
    }

    /**
     * Persist one cumulative usage snapshot + delta summary row for a bucket.
     */
    private function persistTransferUsageSample(int $userId, int $bucketId, array $usage, string $currentTime): void
    {
        $bytesSent = max(0, (int) ($usage['bytes_sent'] ?? 0));
        $bytesReceived = max(0, (int) ($usage['bytes_received'] ?? 0));
        $ops = max(0, (int) ($usage['ops'] ?? 0));
        $successfulOps = max(0, (int) ($usage['successful_ops'] ?? 0));

        $last = Capsule::table('s3_transfer_stats')
            ->where('user_id', $userId)
            ->where('bucket_id', $bucketId)
            ->orderBy('id', 'desc')
            ->first();

        if ($last !== null) {
            // Usage trim / baseline correction (e.g. after switching off a doubled
            // cluster-wide dump): never store a negative "delta".
            $bytesSentSummary = max(0, $bytesSent - (int) $last->bytes_sent);
            $bytesReceivedSummary = max(0, $bytesReceived - (int) $last->bytes_received);
            $opsSummary = max(0, $ops - (int) $last->ops);
            $successfulOpsSummary = max(0, $successfulOps - (int) $last->successful_ops);
        } else {
            $bytesSentSummary = $bytesSent;
            $bytesReceivedSummary = $bytesReceived;
            $opsSummary = $ops;
            $successfulOpsSummary = $successfulOps;
        }

        DBController::saveTransferStats([
            'user_id' => $userId,
            'bucket_id' => $bucketId,
            'bytes_sent' => $bytesSent,
            'bytes_received' => $bytesReceived,
            'ops' => $ops,
            'successful_ops' => $successfulOps,
            'created_at' => $currentTime,
        ]);

        // Skip zero-delta rows after a baseline correction to avoid chart clutter.
        if ($last !== null
            && $bytesSentSummary === 0
            && $bytesReceivedSummary === 0
            && $opsSummary === 0
            && $successfulOpsSummary === 0
        ) {
            return;
        }

        DBController::saveTransferStatsSummary([
            'user_id' => $userId,
            'bucket_id' => $bucketId,
            'bytes_sent' => $bytesSentSummary,
            'bytes_received' => $bytesReceivedSummary,
            'ops' => $opsSummary,
            'successful_ops' => $successfulOpsSummary,
            'created_at' => $currentTime,
        ]);
    }

    /**
     * Detect whether stored cumulative transfer stats are ~2x live per-uid RGW usage.
     */
    public static function isApproximatelyDoubled(int $stored, int $live, float $tolerance = 0.05): bool
    {
        if ($live <= 0 || $stored <= 0) {
            return false;
        }
        $ratio = $stored / $live;
        return $ratio >= (2.0 - $tolerance) && $ratio <= (2.0 + $tolerance);
    }

    /**
     * Repair buckets whose transfer cumulatives were inflated by the old cluster-wide
     * usage dump. Halves summary deltas and rewrites cumulative snapshots to match
     * live per-uid RGW usage.
     *
     * @return array{checked:int, repaired:int, buckets:array<int,string>}
     */
    public function repairDoubledTransferStats($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey): array
    {
        $result = ['checked' => 0, 'repaired' => 0, 'buckets' => []];

        $latest = Capsule::select("
            SELECT t.bucket_id, t.user_id, t.bytes_sent, t.bytes_received, t.ops, t.successful_ops,
                   b.name AS bucket_name, u.username, u.tenant_id, u.ceph_uid
            FROM s3_transfer_stats t
            INNER JOIN (
                SELECT bucket_id, MAX(id) AS id FROM s3_transfer_stats GROUP BY bucket_id
            ) latest ON latest.id = t.id
            INNER JOIN s3_buckets b ON b.id = t.bucket_id
            INNER JOIN s3_users u ON u.id = t.user_id
        ");

        foreach ($latest as $row) {
            $result['checked']++;
            $uid = $this->resolveUsageUid($row);
            if ($uid === '') {
                continue;
            }

            $data = AdminOps::getUsage($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, [
                'uid' => $uid,
                'show_entries' => true,
            ]);
            if (($data['status'] ?? '') !== 'success') {
                continue;
            }

            $liveSent = 0;
            $liveReceived = 0;
            $liveOps = 0;
            $liveSuccessfulOps = 0;
            foreach (($data['data']['entries'] ?? []) as $entry) {
                foreach (($entry['buckets'] ?? []) as $bucketData) {
                    if ((string) ($bucketData['bucket'] ?? '') !== (string) $row->bucket_name) {
                        continue;
                    }
                    foreach (($bucketData['categories'] ?? []) as $category) {
                        $liveSent += (int) ($category['bytes_sent'] ?? 0);
                        $liveReceived += (int) ($category['bytes_received'] ?? 0);
                        $liveOps += (int) ($category['ops'] ?? 0);
                        $liveSuccessfulOps += (int) ($category['successful_ops'] ?? 0);
                    }
                }
            }

            $sentDoubled = self::isApproximatelyDoubled((int) $row->bytes_sent, $liveSent);
            $recvDoubled = self::isApproximatelyDoubled((int) $row->bytes_received, $liveReceived);
            if (!$sentDoubled && !$recvDoubled) {
                continue;
            }

            $bucketId = (int) $row->bucket_id;
            Capsule::table('s3_transfer_stats_summary')
                ->where('bucket_id', $bucketId)
                ->update([
                    'bytes_sent' => Capsule::raw('GREATEST(0, FLOOR(bytes_sent / 2))'),
                    'bytes_received' => Capsule::raw('GREATEST(0, FLOOR(bytes_received / 2))'),
                    'ops' => Capsule::raw('GREATEST(0, FLOOR(ops / 2))'),
                    'successful_ops' => Capsule::raw('GREATEST(0, FLOOR(successful_ops / 2))'),
                ]);

            Capsule::table('s3_transfer_stats')
                ->where('bucket_id', $bucketId)
                ->update([
                    'bytes_sent' => Capsule::raw('GREATEST(0, FLOOR(bytes_sent / 2))'),
                    'bytes_received' => Capsule::raw('GREATEST(0, FLOOR(bytes_received / 2))'),
                    'ops' => Capsule::raw('GREATEST(0, FLOOR(ops / 2))'),
                    'successful_ops' => Capsule::raw('GREATEST(0, FLOOR(successful_ops / 2))'),
                ]);

            // Anchor the latest cumulative exactly to live RGW so the next cron
            // delta starts from the correct baseline.
            $latestId = Capsule::table('s3_transfer_stats')
                ->where('bucket_id', $bucketId)
                ->orderBy('id', 'desc')
                ->value('id');
            if ($latestId) {
                Capsule::table('s3_transfer_stats')->where('id', $latestId)->update([
                    'bytes_sent' => $liveSent,
                    'bytes_received' => $liveReceived,
                    'ops' => $liveOps,
                    'successful_ops' => $liveSuccessfulOps,
                ]);
            }

            $result['repaired']++;
            $result['buckets'][$bucketId] = (string) $row->bucket_name;
            logModuleCall(self::$module, 'repairDoubledTransferStats', [
                'bucket_id' => $bucketId,
                'bucket' => $row->bucket_name,
                'uid' => $uid,
                'db_sent' => (int) $row->bytes_sent,
                'live_sent' => $liveSent,
                'db_received' => (int) $row->bytes_received,
                'live_received' => $liveReceived,
            ], 'halved transfer history and re-anchored cumulative');
        }

        return $result;
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
                if (Capsule::schema()->hasColumn('s3_users', 'system_key')) {
                    $tenantCols[] = 'system_key';
                }
            } catch (\Throwable $_) {}
            $tenants = Capsule::table('s3_users')
                ->select($tenantCols)
                ->where('parent_id', $parentUserId)
                ->get();

            foreach($tenants as $tenant) {
                if (self::isMs365PlatformStorageUser($tenant)) {
                    continue;
                }
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
                        $bucketName = (string) ($bucket['bucket'] ?? '');
                        if (self::isMs365BillingExemptBucket($bucketName)) {
                            continue;
                        }
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

    /**
     * Record RGW usage for e3ms365-* platform buckets in s3_bucket_stats_summary for vault UI display.
     * Does not affect customer billing totals.
     */
    protected function collectMs365VaultStatsForDisplay(array $moduleSettings, string $currentTime): void
    {
        try {
            if (!Capsule::schema()->hasColumn('s3_users', 'system_key')) {
                return;
            }

            $platformUser = Capsule::table('s3_users')
                ->where('system_key', 'ms365_platform_owner')
                ->first();

            if (!$platformUser) {
                return;
            }

            $baseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($platformUser);
            if (empty($baseUid)) {
                $baseUid = (string) ($platformUser->username ?? '');
            }
            if ($baseUid === '') {
                return;
            }

            $params = [
                'uid' => !empty($platformUser->tenant_id)
                    ? ($platformUser->tenant_id . '$' . $baseUid)
                    : $baseUid,
                'stats' => true,
            ];

            $bucketStatsData = AdminOps::getBucketInfo(
                $moduleSettings['s3Endpoint'],
                $moduleSettings['cephAdminAccessKey'],
                $moduleSettings['cephAdminSecretKey'],
                $params
            );

            if (($bucketStatsData['status'] ?? '') === 'fail' || empty($bucketStatsData['data'])) {
                if (($bucketStatsData['status'] ?? '') === 'fail') {
                    logModuleCall(
                        self::$module,
                        __FUNCTION__,
                        'ms365_platform_owner',
                        $bucketStatsData['message'] ?? 'Failed to list MS365 platform buckets'
                    );
                }
                return;
            }

            $currentDate = (new DateTime())->format('Y-m-d');
            $userId = (int) $platformUser->id;

            foreach ($bucketStatsData['data'] as $bucket) {
                $bucketName = (string) ($bucket['bucket'] ?? '');
                try {
                    if (!self::isMs365BillingExemptBucket($bucketName)) {
                        continue;
                    }

                    $currentBucketSize = (int) ($bucket['usage']['rgw.main']['size'] ?? 0);
                    $creationDateTime = new DateTime($bucket['creation_time']);
                    $creationTime = $creationDateTime->format('Y-m-d H:i:s');
                    $bucketId = $this->resolveMs365VaultBucketId($userId, $bucket, $moduleSettings, $creationTime);
                    if ($bucketId === 'fail' || (int) $bucketId <= 0) {
                        continue;
                    }
                    $bucketId = (int) $bucketId;

                    $bucketStatsValues = [
                        'bucket_id' => $bucketId,
                        'user_id' => $userId,
                        'num_objects' => $bucket['usage']['rgw.main']['num_objects'] ?? 0,
                        'size' => $currentBucketSize,
                        'size_actual' => $bucket['usage']['rgw.main']['size_actual'] ?? $currentBucketSize,
                        'size_utilized' => $bucket['usage']['rgw.main']['size_utilized'] ?? $currentBucketSize,
                        'size_kb' => $bucket['usage']['rgw.main']['size_kb'] ?? 0,
                        'size_kb_actual' => $bucket['usage']['rgw.main']['size_kb_actual'] ?? 0,
                        'size_kb_utilized' => $bucket['usage']['rgw.main']['size_kb_utilized'] ?? 0,
                        'created_at' => $currentTime,
                    ];

                    DBController::saveBucketStats($bucketStatsValues);

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
                                'total_usage' => $currentBucketSize,
                            ]);
                    }
                } catch (\Exception $e) {
                    logModuleCall(self::$module, __FUNCTION__, $bucketName, $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, 'ms365_platform_owner', $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $bucket
     * @return int|string
     */
    protected function resolveMs365VaultBucketId(int $userId, array $bucket, array $moduleSettings, string $creationTime)
    {
        $bucketStringId = (string) ($bucket['id'] ?? '');
        $bucketName = (string) ($bucket['bucket'] ?? '');

        if ($bucketStringId !== '') {
            $existing = DBController::getBucket($bucketStringId);
            if ($existing !== null) {
                return (int) $existing->id;
            }
        }

        if ($bucketName !== '') {
            $byName = Capsule::table('s3_buckets')
                ->where('user_id', $userId)
                ->where('name', $bucketName)
                ->value('id');
            if ($byName !== null) {
                return (int) $byName;
            }
        }

        return $this->handleBucketData($userId, $bucket, $moduleSettings, $creationTime);
    }

    private static function isMs365BillingExemptBucket(string $bucketName): bool
    {
        return str_starts_with(strtolower(trim($bucketName)), 'e3ms365-');
    }

    private static function isMs365PlatformStorageUser(object $user): bool
    {
        return (string) ($user->system_key ?? '') === 'ms365_platform_owner';
    }
}