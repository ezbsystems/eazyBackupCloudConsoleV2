<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use DateTime;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Admin\ClusterManager;
use WHMCS\Module\Addon\CloudStorage\MigrationController;
use Predis\Client as RedisClient;

class S3Billing {

    private static $module = 'cloudstorage';
    private static $hasSourceAliasSummary = null; // memoize schema check

    /**
     * Gather Billing Data.
     *
     * @return array
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
        // Global addon settings (not cluster-specific)
        $cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
        $encryptionKey  = $module->where('setting', 'encryption_key')->pluck('value')->first();

        // Determine default cluster (fallback to legacy addon settings if table empty)
        $defaultCluster = ClusterManager::getDefaultCluster();
        if (!$defaultCluster) {
            $defaultCluster = (object) [
                'cluster_alias'     => 'old_ceph_cluster',
                's3_endpoint'       => $module->where('setting', 's3_endpoint')->pluck('value')->first(),
                'admin_access_key'  => $module->where('setting', 'ceph_access_key')->pluck('value')->first(),
                'admin_secret_key'  => $module->where('setting', 'ceph_secret_key')->pluck('value')->first(),
            ];
        }

        $currentTime  = (new DateTime())->format('Y-m-d H:i:s');
        $currentDate  = (new DateTime())->format('Y-m-d');

        // Track clusters used during this run so we can export usage per-cluster later
        $clustersUsed = [];

        foreach ($products as $product) {
            // Skip frozen clients to avoid partials
            if ($this->isClientFrozen((int)$product->userid)) {
                continue;
            }

            Capsule::connection()->transaction(function() use (
                $product,
                $defaultCluster,
                $cephAdminUser,
                $encryptionKey,
                $currentTime,
                $currentDate,
                &$clustersUsed,
                &$billingData,
                &$updateResults
            ){
                // Determine which Ceph cluster this client should use
                $backendAlias    = MigrationController::getBackendForClient($product->userid);
                $clusterDetails  = ClusterManager::getClusterByAlias($backendAlias);
                if (!$clusterDetails) {
                    $clusterDetails = $defaultCluster;
                }

                $s3Endpoint         = $clusterDetails->s3_endpoint;
                $cephAdminAccessKey = $clusterDetails->admin_access_key;
                $cephAdminSecretKey = $clusterDetails->admin_secret_key;

                // Track clusters so we can run exportBucketUsage per cluster later
                $clustersUsed[$clusterDetails->cluster_alias] = [
                    'endpoint' => $s3Endpoint,
                    'access'   => $cephAdminAccessKey,
                    'secret'   => $cephAdminSecretKey,
                ];

                $moduleSettings = [
                    's3Endpoint'           => $s3Endpoint,
                    'cephAdminUser'        => $cephAdminUser,
                    'cephAdminAccessKey'   => $cephAdminAccessKey,
                    'cephAdminSecretKey'   => $cephAdminSecretKey,
                    'encryptionKey'        => $encryptionKey,
                    'clusterAlias'         => $clusterDetails->cluster_alias ?? 'unknown',
                ];
                $username = $product->username;
                // get the user from db
                $user = DBController::getUser($username);
                if (is_null($user)) {
                    logModuleCall(self::$module, __FUNCTION__, $product->userid, 'User not found in db.');
                    return; // transaction scope
                }
            $params = [
                'uid' => $username,
                'stats' => true
            ];
            if (!empty($user->tenant_id)) {
                $params['uid'] = $user->tenant_id . '$' . $username;
            }
                $bucketStatsData = AdminOps::getBucketInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
                if ($bucketStatsData['status'] == 'fail' || count($bucketStatsData['data']) == 0) {
                    if ($bucketStatsData['status'] == 'fail') {
                        logModuleCall(self::$module, __FUNCTION__, $packageId, $bucketStatsData['message']);
                        return; // transaction scope
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
                    // Idempotent daily write using updateOrInsert with usage_day
                    $upsert = [
                        'total_usage' => $currentBucketSize,
                        'usage_day'   => $currentDate,
                        'created_at'  => $currentTime,
                    ];
                    if ($this->hasSourceAliasColumnOnSummary()) {
                        $upsert['source_alias'] = $clusterDetails->cluster_alias ?? 'unknown';
                    }
                    Capsule::table('s3_bucket_stats_summary')->updateOrInsert(
                        [
                            'user_id'   => $userId,
                            'bucket_id' => $bucketId,
                            'usage_day' => $currentDate,
                        ],
                        $upsert
                    );
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
                $updateResults[] = $this->updateProductPrice($product, $totalBucketSize, $userId);

                $billingData[$username] = ['bucket_size' => $totalBucketSize];
            }); // end transaction per product
        }

        // Export transfer usage for each cluster we interacted with in this run
        foreach ($clustersUsed as $clusterAlias => $clusterInfo) {
            $this->exportBucketUsage($clusterInfo['endpoint'], $clusterInfo['access'], $clusterInfo['secret'], $clusterAlias, $currentTime);
        }

        return ['billingData' => $billingData, 'updateResults' => $updateResults];
    }

    /**
     * Update Product Price.
     *
     * @return array
     */
    private function updateProductPrice($product, $totalBucketSize, $userId)
    {
        $result = [
            'userid' => $product->userid,
            'new_amount' => null,
            'update_status' => false
        ];

        // Convert bucket size from bytes to TiB and GiB
        $bucketSizeTiB = $totalBucketSize / (1024 * 1024 * 1024 * 1024);
        $bucketSizeGiB = $totalBucketSize / (1024 * 1024 * 1024); // Convert to GiB for precise charging after 1TiB

        // Ensure a minimum charge for 1 TiB
        if ($bucketSizeTiB <= 1) {
            $amount = 9.00; // Base fee for up to 1 TiB
        } else {
            // Subtract 1 TiB (in GiB) from total usage
            $excessGiB = $bucketSizeGiB - 1024;
            // Fee per additional GiB
            $additionalCharge = $excessGiB * 0.009765;
            // Total amount including base fee and additional charge
            $amount = 9 + $additionalCharge;
        }

        // Round up to the nearest cent
        $amount = ceil($amount * 100) / 100;
        $result['new_amount'] = $amount;

        try {
            $nextDueDate = new DateTime($product->nextduedate);
            // Calculate the start and end dates of the billing month
            $billingMonthStart = (clone $nextDueDate)->modify('-1 month');
            $billingMonthEnd = (clone $nextDueDate)->modify('-1 day');
            DBController::savePrices([
                'user_id' => $userId,
                'amount' => $amount
            ]);

            $highestAmount = DBController::getHighestAmount($userId, $billingMonthStart->format('Y-m-d'), $billingMonthEnd->format('Y-m-d'));
            Capsule::table('tblhosting')->where('id', $product->id)->update(['amount' => $highestAmount]);
            $result['update_status'] = true;
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
     * Export Bucket Usage.
     *
     * @return void
     */
    private function exportBucketUsage($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $clusterAlias, $currentTime)
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

                    // check bucket record exist
                    if (!isset($bucketUsageRecords[$bucketName])) {
                        $bucketUsageRecords[$bucketName] = [
                            'owner' => $owner,
                            'bytes_sent' => 0,
                            'bytes_received' => 0,
                            'ops' => 0,
                            'successful_ops' => 0
                        ];
                    }

                    foreach ($bucketData['categories'] as $category) {
                        $bucketUsageRecords[$bucketName]['bytes_sent'] += $category['bytes_sent'];
                        $bucketUsageRecords[$bucketName]['bytes_received'] += $category['bytes_received'];
                        $bucketUsageRecords[$bucketName]['ops'] += $category['ops'];
                        $bucketUsageRecords[$bucketName]['successful_ops'] += $category['successful_ops'];
                    }

                }
            }

            if (count($bucketUsageRecords)) {
                foreach ($bucketUsageRecords as $bucketName => $bucketUsageRecord) {
                    $bucket = Capsule::table('s3_buckets')->select('id', 'user_id')->where('name', $bucketName)->first();
                    if (is_null($bucket)) {
                        continue;
                    }
                    $userId = $bucket->user_id;

                    // Authoritative cluster check: resolve WHMCS client for this bucket's owner and skip if not on this cluster
                    $s3User = Capsule::table('s3_users')->select('username')->where('id', $userId)->first();
                    if ($s3User && isset($s3User->username)) {
                        $hosting = Capsule::table('tblhosting')->where('username', $s3User->username)->first();
                        if ($hosting && isset($hosting->userid)) {
                            $authoritativeAlias = MigrationController::getBackendForClient((int)$hosting->userid);
                            if ($authoritativeAlias !== $clusterAlias) {
                                // Skip usage from non-authoritative cluster during migration overlap
                                continue;
                            }
                        }
                    }

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
     * Check if a client is frozen by consulting Redis ak_state hash.
     */
    private function isClientFrozen(int $clientId): bool
    {
        try {
            // Load redis connection details from addon config (same as MigrationController)
            $rows = Capsule::table('tbladdonmodules')->where('module', 'cloudstorage')->get(['setting','value']);
            $map = [];
            foreach ($rows as $r) { $map[$r->setting] = $r->value; }
            $host = $map['redis_host'] ?? '127.0.0.1';
            $port = !empty($map['redis_port']) && ctype_digit((string)$map['redis_port']) ? (int)$map['redis_port'] : 6379;
            $redis = new RedisClient(['scheme' => 'tcp', 'host' => $host, 'port' => $port]);
            $akStateHash = 'ak_state:' . $clientId;
            // If any key is frozen -> treat client as frozen
            $states = $redis->hvals($akStateHash);
            if (!$states) { return false; }
            foreach ($states as $state) {
                if ($state === 'frozen') { return true; }
            }
        } catch (\Throwable $e) {
            // On errors, default to not-frozen to avoid skipping billing unintentionally
        }
        return false;
    }

    /**
     * Detect once if s3_bucket_stats_summary has source_alias column.
     */
    private function hasSourceAliasColumnOnSummary(): bool
    {
        if (self::$hasSourceAliasSummary !== null) {
            return (bool) self::$hasSourceAliasSummary;
        }
        try {
            $columns = Capsule::connection()->getDoctrineSchemaManager()->listTableColumns('s3_bucket_stats_summary');
            self::$hasSourceAliasSummary = array_key_exists('source_alias', $columns);
        } catch (\Throwable $e) {
            self::$hasSourceAliasSummary = false;
        }
        return (bool) self::$hasSourceAliasSummary;
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

        $bucketObject = new BucketController($moduleSettings['s3Endpoint'], $moduleSettings['cephAdminUser'], $moduleSettings['cephAdminAccessKey'], $moduleSettings['cephAdminSecretKey']);
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
            $tenants = Capsule::table('s3_users')
                ->select('id', 'username', 'tenant_id')
                ->where('parent_id', $parentUserId)
                ->get();

            foreach($tenants as $tenant) {
                $username = $tenant->username;
                $params = [
                    'uid' => $username,
                    'stats' => true
                ];
                if (!empty($tenant->tenant_id)) {
                    $params['uid'] = $tenant->tenant_id . '$' . $username;
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

                        $bucketObject = new BucketController($moduleSettings['s3Endpoint'], $moduleSettings['cephAdminUser'], $moduleSettings['cephAdminAccessKey'], $moduleSettings['cephAdminSecretKey']);
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
                        $upsert = [
                            'total_usage' => $currentBucketSize,
                            'usage_day'   => $currentDate,
                            'created_at'  => $currentDateTime,
                        ];
                        if ($this->hasSourceAliasColumnOnSummary()) {
                            $upsert['source_alias'] = $moduleSettings['clusterAlias'] ?? 'unknown';
                        }
                        Capsule::table('s3_bucket_stats_summary')->updateOrInsert(
                            [
                                'user_id'   => $userId,
                                'bucket_id' => $bucketId,
                                'usage_day' => $currentDate,
                            ],
                            $upsert
                        );
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