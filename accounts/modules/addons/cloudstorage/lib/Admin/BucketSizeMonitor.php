<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use DateTime;

class BucketSizeMonitor {

    private static $module = 'cloudstorage';

    /**
     * Collect bucket sizes from all users and store in history table
     * Enhanced version that collects ALL buckets from Ceph, including non-WHMCS ones
     *
     * @param string $endpoint
     * @param string $adminAccessKey
     * @param string $adminSecretKey
     *
     * @return array
     */
    public static function collectAllBucketSizes($endpoint, $adminAccessKey, $adminSecretKey)
    {
        try {
            $collectedAt = date('Y-m-d H:i:s');
            $totalBuckets = 0;
            $whmcsBuckets = 0;
            $nonWhmcsBuckets = 0;
            $errors = [];
            
            // Get WHMCS bucket mappings for reference (but don't limit collection to them)
            $hasCephUidCol = false;
            try { $hasCephUidCol = Capsule::schema()->hasColumn('s3_users', 'ceph_uid'); } catch (\Throwable $_) {}
            $selectCols = [
                's3_buckets.name as bucket_name',
                's3_users.username as owner_username',
                's3_users.tenant_id',
                's3_buckets.is_active'
            ];
            if ($hasCephUidCol) {
                $selectCols[] = 's3_users.ceph_uid as owner_ceph_uid';
            }
            $whmcsBucketCollection = Capsule::table('s3_buckets')
                ->join('s3_users', 's3_buckets.user_id', '=', 's3_users.id')
                ->select($selectCols)
                ->where('s3_buckets.is_active', 1)
                ->get();

            // Create mapping for WHMCS buckets (for owner resolution)
            $whmcsMapping = [];
            foreach ($whmcsBucketCollection as $bucket) {
                $bucketName = $bucket->bucket_name;
                if (!isset($whmcsMapping[$bucketName])) {
                    $whmcsMapping[$bucketName] = [];
                }
                $whmcsMapping[$bucketName][] = [
                    'owner' => $bucket->owner_username,
                    'ceph_uid' => $bucket->owner_ceph_uid ?? '',
                    'tenant_id' => $bucket->tenant_id
                ];
            }
            
            // Use global bucket listing to get ALL buckets from Ceph
            $params = ['stats' => true];
            
            $bucketInfoResponse = AdminOps::getBucketInfo($endpoint, $adminAccessKey, $adminSecretKey, $params);
            
            if ($bucketInfoResponse['status'] != 'success' || !isset($bucketInfoResponse['data'])) {
                return [
                    'status' => 'fail',
                    'message' => 'Failed to get global bucket list: ' . ($bucketInfoResponse['message'] ?? 'Unknown error')
                ];
            }
            
            $buckets = $bucketInfoResponse['data'];
            
            if (is_array($buckets)) {
                foreach ($buckets as $bucket) {
                    if (!isset($bucket['bucket'])) {
                        continue;
                    }
                    
                    $bucketName = $bucket['bucket'];
                    $cephOwner = $bucket['owner'] ?? 'Unknown';
                    $sizeBytes = 0;
                    $objectCount = 0;
                    
                    // Extract size and object count from usage data
                    if (isset($bucket['usage']['rgw.main'])) {
                        $sizeBytes = $bucket['usage']['rgw.main']['size_actual'] ?? 0;
                        $objectCount = $bucket['usage']['rgw.main']['num_objects'] ?? 0;
                    } elseif (isset($bucket['size'])) {
                        // Fallback to simple size field
                        $sizeBytes = $bucket['size'] ?? 0;
                        $objectCount = $bucket['num_objects'] ?? 0;
                    }
                    
                    // Determine the owner to use for storage
                    $bucketOwner = $cephOwner; // Default to Ceph owner
                    $isWhmcsBucket = false;
                    
                    // Check if this bucket exists in WHMCS for proper owner mapping
                    if (isset($whmcsMapping[$bucketName])) {
                        $isWhmcsBucket = true;
                        
                        // Try to find matching owner based on tenant system
                        $ownerFound = false;
                        foreach ($whmcsMapping[$bucketName] as $whmcsOwner) {
                            $expectedCephOwner = !empty($whmcsOwner['ceph_uid']) ? $whmcsOwner['ceph_uid'] : $whmcsOwner['owner'];
                            $tenantId = $whmcsOwner['tenant_id'];
                            
                            // Build expected Ceph owner format
                            if (!empty($tenantId)) {
                                $expectedCephOwner = $tenantId . '$' . $expectedCephOwner;
                            }
                            
                            // Check if this matches the Ceph owner
                            if ($cephOwner === $expectedCephOwner || $cephOwner === $whmcsOwner['owner']) {
                                $bucketOwner = $whmcsOwner['owner']; // Use WHMCS username for consistency
                                $ownerFound = true;
                                break;
                            }
                        }
                        
                        if (!$ownerFound && count($whmcsMapping[$bucketName]) === 1) {
                            // Single WHMCS owner but no exact match - use WHMCS owner and log
                            $bucketOwner = $whmcsMapping[$bucketName][0]['owner'];
                            error_log("Owner mismatch for bucket {$bucketName}: Ceph={$cephOwner}, WHMCS={$bucketOwner}");
                        }
                    } else {
                        // Bucket not in WHMCS - use Ceph owner as-is
                        error_log("Bucket {$bucketName} exists in Ceph (Size: " . self::formatBytes($sizeBytes) . ", Objects: {$objectCount}) but not in WHMCS database");
                    }
                    
                    $totalBuckets++;
                    if ($isWhmcsBucket) {
                        $whmcsBuckets++;
                    } else {
                        $nonWhmcsBuckets++;
                    }
                    
                    // Validate data before insertion
                    if ($sizeBytes < 0) {
                        error_log("Invalid negative size for bucket {$bucketName}: {$sizeBytes} bytes");
                        $sizeBytes = 0;
                    }
                    
                    if ($objectCount < 0) {
                        error_log("Invalid negative object count for bucket {$bucketName}: {$objectCount}");
                        $objectCount = 0;
                    }
                    
                    // Insert into history table
                    try {
                        DBController::insertRecord('s3_bucket_sizes_history', [
                            'bucket_name' => $bucketName,
                            'bucket_owner' => $bucketOwner,
                            'bucket_size_bytes' => $sizeBytes,
                            'bucket_object_count' => $objectCount,
                            'collected_at' => $collectedAt,
                            'created_at' => $collectedAt
                        ]);
                        
                        // Debug logging for specific buckets
                        if (in_array($bucketName, ['backup', 'theme.hcsite.dev'])) {
                            error_log("DEBUG: Collected {$bucketName} (Owner: {$bucketOwner}): " . 
                                     self::formatBytes($sizeBytes) . " ({$objectCount} objects)");
                        }
                        
                    } catch (\Exception $e) {
                        $errors[] = "Failed to insert data for bucket {$bucketName}: " . $e->getMessage();
                        error_log("Collection error for bucket {$bucketName}: " . $e->getMessage());
                    }
                }
            }
            
            $totalUsers = collect($whmcsBucketCollection)->groupBy('owner_username')->count();
            
            $message = "Successfully collected data for {$totalBuckets} buckets total";
            $message .= " ({$whmcsBuckets} WHMCS buckets, {$nonWhmcsBuckets} non-WHMCS buckets)";
            $message .= " from {$totalUsers} WHMCS users";
            if (count($errors) > 0) {
                $message .= " (with " . count($errors) . " errors)";
            }
            
            return [
                'status' => 'success',
                'message' => $message,
                'total_buckets' => $totalBuckets,
                'whmcs_buckets' => $whmcsBuckets,
                'non_whmcs_buckets' => $nonWhmcsBuckets,
                'total_users' => $totalUsers,
                'errors' => $errors,
                'collected_at' => $collectedAt
            ];
            
        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, [$endpoint], $e->getMessage());
            
            return [
                'status' => 'fail',
                'message' => 'Failed to collect bucket sizes: ' . $e->getMessage(),
                'total_buckets' => 0,
                'whmcs_buckets' => 0,
                'non_whmcs_buckets' => 0,
                'total_users' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Get current bucket sizes with growth metrics (latest data for each bucket)
     *
     * @param string $search Optional search term for filtering
     * @param string $orderBy Column to order by
     * @param string $orderDir Order direction (ASC/DESC)
     *
     * @return array
     */
    public static function getCurrentBucketSizes($search = '', $orderBy = 'bucket_name', $orderDir = 'ASC', $filterType = 'all')
    {
        try {
            // Get WHMCS bucket names for filtering
            $whmcsBucketNames = Capsule::table('s3_buckets')
                ->select('name')
                ->where('is_active', 1)
                ->pluck('name')
                ->toArray();

            // Get the latest collection time for each bucket with parent user information
            $latestDataQuery = Capsule::table('s3_bucket_sizes_history as h1')
                ->select([
                    'h1.bucket_name',
                    'h1.bucket_owner', 
                    'h1.bucket_size_bytes',
                    'h1.bucket_object_count',
                    'h1.collected_at',
                    'owner_user.parent_id as owner_parent_id',
                    'parent_user.username as parent_username',
                    Capsule::raw('CASE WHEN s3_buckets.id IS NOT NULL THEN 1 ELSE 0 END as is_whmcs_bucket')
                ])
                ->join(Capsule::raw('(
                    SELECT bucket_name, bucket_owner, MAX(collected_at) as max_collected_at 
                    FROM s3_bucket_sizes_history 
                    GROUP BY bucket_name, bucket_owner
                ) h2'), function($join) {
                    $join->on('h1.bucket_name', '=', 'h2.bucket_name')
                         ->on('h1.bucket_owner', '=', 'h2.bucket_owner')
                         ->on('h1.collected_at', '=', 'h2.max_collected_at');
                })
                ->leftJoin('s3_users as owner_user', 'h1.bucket_owner', '=', 'owner_user.username')
                ->leftJoin('s3_users as parent_user', 'owner_user.parent_id', '=', 'parent_user.id')
                ->leftJoin('s3_buckets', 'h1.bucket_name', '=', 's3_buckets.name');

            // Apply filter type
            if ($filterType === 'whmcs') {
                $latestDataQuery->whereIn('h1.bucket_name', $whmcsBucketNames);
            } elseif ($filterType === 'non-whmcs') {
                $latestDataQuery->whereNotIn('h1.bucket_name', $whmcsBucketNames);
            }

            // Apply search filter if provided
            if (!empty($search)) {
                $latestDataQuery->where(function($query) use ($search) {
                    $query->where('h1.bucket_name', 'LIKE', '%' . $search . '%')
                          ->orWhere('h1.bucket_owner', 'LIKE', '%' . $search . '%')
                          ->orWhere('parent_user.username', 'LIKE', '%' . $search . '%');
                });
            }

            // Apply ordering (add new growth columns to valid columns)
            $validOrderColumns = ['bucket_name', 'bucket_owner', 'parent_username', 'bucket_size_bytes', 'bucket_object_count', 'collected_at', 'growth_1h', 'growth_24h', 'growth_7d'];
            if (!in_array($orderBy, $validOrderColumns)) {
                $orderBy = 'bucket_name';
            }
            
            if (!in_array(strtoupper($orderDir), ['ASC', 'DESC'])) {
                $orderDir = 'ASC';
            }

            // Check if sorting by growth columns (these need post-query sorting)
            $growthColumns = ['growth_1h', 'growth_24h', 'growth_7d'];
            $isGrowthSort = in_array($orderBy, $growthColumns);
            
            // For growth columns, get unsorted data; for database columns, sort in SQL
            if ($isGrowthSort) {
                // Get unsorted data - we'll sort after calculating growth
                $results = $latestDataQuery->orderBy('h1.bucket_name', 'ASC')->get();
            } else {
                // Sort by database column - handle parent_username special case
                if ($orderBy === 'parent_username') {
                    $results = $latestDataQuery->orderBy('parent_user.username', $orderDir)->get();
                } else {
                    $results = $latestDataQuery->orderBy('h1.' . $orderBy, $orderDir)->get();
                }
            }
            
            // Format the results with growth calculations
            $buckets = [];
            $totalSize = 0;
            $totalObjects = 0;
            
            foreach ($results as $row) {
                // Calculate growth metrics for this bucket
                $growthMetrics = self::calculateBucketGrowth($row->bucket_name, $row->bucket_owner, $row->bucket_size_bytes);
                
                // Ensure growth metrics array has all required keys
                if (!is_array($growthMetrics)) {
                    $growthMetrics = [
                        'growth_1h_bytes' => 0,
                        'growth_1h_formatted' => 'N/A',
                        'growth_1h_percent' => 0,
                        'growth_24h_bytes' => 0,
                        'growth_24h_formatted' => 'N/A',
                        'growth_24h_percent' => 0,
                        'growth_7d_bytes' => 0,
                        'growth_7d_formatted' => 'N/A',
                        'growth_7d_percent' => 0
                    ];
                }
                
                // Determine parent username: if owner_parent_id is NULL, owner is the parent
                $parentUsername = $row->owner_parent_id ? ($row->parent_username ?? $row->bucket_owner) : $row->bucket_owner;
                
                $buckets[] = [
                    'bucket_name' => $row->bucket_name,
                    'bucket_owner' => $row->bucket_owner,
                    'parent_username' => $parentUsername,
                    'bucket_size_bytes' => $row->bucket_size_bytes,
                    'bucket_size_formatted' => self::formatBytes($row->bucket_size_bytes),
                    'bucket_object_count' => $row->bucket_object_count,
                    'last_updated' => $row->collected_at,
                    'is_whmcs_bucket' => (bool)$row->is_whmcs_bucket,
                    'bucket_type' => $row->is_whmcs_bucket ? 'WHMCS Customer' : 'External',
                    // Growth metrics
                    'growth_1h_bytes' => $growthMetrics['growth_1h_bytes'] ?? 0,
                    'growth_1h_formatted' => $growthMetrics['growth_1h_formatted'] ?? 'N/A',
                    'growth_1h_percent' => $growthMetrics['growth_1h_percent'] ?? 0,
                    'growth_24h_bytes' => $growthMetrics['growth_24h_bytes'] ?? 0,
                    'growth_24h_formatted' => $growthMetrics['growth_24h_formatted'] ?? 'N/A',
                    'growth_24h_percent' => $growthMetrics['growth_24h_percent'] ?? 0,
                    'growth_7d_bytes' => $growthMetrics['growth_7d_bytes'] ?? 0,
                    'growth_7d_formatted' => $growthMetrics['growth_7d_formatted'] ?? 'N/A',
                    'growth_7d_percent' => $growthMetrics['growth_7d_percent'] ?? 0
                ];
                
                $totalSize += $row->bucket_size_bytes;
                $totalObjects += $row->bucket_object_count;
            }
            
            // Apply post-query sorting for growth columns
            if ($isGrowthSort) {
                usort($buckets, function($a, $b) use ($orderBy, $orderDir) {
                    $aValue = $a[$orderBy . '_bytes'] ?? 0;
                    $bValue = $b[$orderBy . '_bytes'] ?? 0;
                    
                    if ($orderDir === 'DESC') {
                        return $bValue <=> $aValue;
                    } else {
                        return $aValue <=> $bValue;
                    }
                });
            }
            
            // Calculate WHMCS vs external bucket counts
            $whmcsBuckets = count(array_filter($buckets, fn($b) => $b['is_whmcs_bucket']));
            $externalBuckets = count(array_filter($buckets, fn($b) => !$b['is_whmcs_bucket']));
            
            return [
                'status' => 'success',
                'buckets' => $buckets,
                'summary' => [
                    'total_buckets' => count($buckets),
                    'total_size_bytes' => $totalSize,
                    'total_size_formatted' => self::formatBytes($totalSize),
                    'total_objects' => $totalObjects,
                    'whmcs_buckets' => $whmcsBuckets,
                    'external_buckets' => $externalBuckets
                ]
            ];
            
        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, [$search, $orderBy, $orderDir], $e->getMessage());
            
            return [
                'status' => 'fail',
                'message' => 'Failed to retrieve bucket sizes: ' . $e->getMessage(),
                'buckets' => [],
                'summary' => [
                    'total_buckets' => 0,
                    'total_size_bytes' => 0,
                    'total_size_formatted' => '0 B',
                    'total_objects' => 0
                ]
            ];
        }
    }

    /**
     * Calculate growth metrics for a specific bucket using collection-based approach
     *
     * @param string $bucketName
     * @param string $bucketOwner
     * @param int $currentSize
     * @return array Growth metrics
     */
    private static function calculateBucketGrowth($bucketName, $bucketOwner, $currentSize)
    {
        try {
            // Get the most recent collections for this bucket (excluding current)
            // This approach is more reliable than time-based calculations
            $recentCollections = Capsule::table('s3_bucket_sizes_history')
                ->where('bucket_name', $bucketName)
                ->where('bucket_owner', $bucketOwner)
                ->select(['bucket_size_bytes', 'collected_at'])
                ->orderBy('collected_at', 'DESC')
                ->limit(50) // Get last 50 collections to ensure we have enough historical data
                ->get();
            
            if ($recentCollections->isEmpty()) {
                return self::getEmptyGrowthMetrics();
            }
            
            // Convert to array for easier processing
            $collections = [];
            foreach ($recentCollections as $collection) {
                $collections[] = [
                    'bucket_size_bytes' => $collection->bucket_size_bytes,
                    'collected_at' => $collection->collected_at
                ];
            }
            
            if (empty($collections)) {
                return self::getEmptyGrowthMetrics();
            }
            
            // Define time periods for comparison
            $now = time();
            $time1h = $now - 3600;   // 1 hour ago
            $time24h = $now - 86400; // 24 hours ago  
            $time7d = $now - 604800; // 7 days ago
            
            // Find historical sizes using collection-based approach
            $size1h = self::findHistoricalSize($collections, $time1h);
            $size24h = self::findHistoricalSize($collections, $time24h);
            $size7d = self::findHistoricalSize($collections, $time7d);
            
            // For debugging - log what we're comparing
            if (count($collections) > 0) {
                $currentTime = isset($collections[0]) ? $collections[0]['collected_at'] : 'unknown';
                error_log("Growth calc for {$bucketName}: Current={$currentSize}B, 1h={$size1h}B, 24h={$size24h}B, 7d={$size7d}B, Collections=" . count($collections) . ", Current time={$currentTime}");
            }
            
            // Calculate growth (ensure we don't compare against the same value)
            $growth1h = ($size1h > 0 && $size1h != $currentSize) ? $currentSize - $size1h : 0;
            $growth24h = ($size24h > 0 && $size24h != $currentSize) ? $currentSize - $size24h : 0;
            $growth7d = ($size7d > 0 && $size7d != $currentSize) ? $currentSize - $size7d : 0;
            
            // Calculate percentage growth (avoid division by zero)
            $growthPercent1h = $size1h > 0 ? round(($growth1h / $size1h) * 100, 2) : 0;
            $growthPercent24h = $size24h > 0 ? round(($growth24h / $size24h) * 100, 2) : 0;
            $growthPercent7d = $size7d > 0 ? round(($growth7d / $size7d) * 100, 2) : 0;
            
            return [
                'growth_1h_bytes' => $growth1h,
                'growth_1h_formatted' => self::formatGrowth($growth1h),
                'growth_1h_percent' => $growthPercent1h,
                'growth_24h_bytes' => $growth24h,
                'growth_24h_formatted' => self::formatGrowth($growth24h),
                'growth_24h_percent' => $growthPercent24h,
                'growth_7d_bytes' => $growth7d,
                'growth_7d_formatted' => self::formatGrowth($growth7d),
                'growth_7d_percent' => $growthPercent7d,
                // Debug info
                'debug_collections_count' => count($collections),
                'debug_sizes' => ['current' => $currentSize, '1h' => $size1h, '24h' => $size24h, '7d' => $size7d]
            ];
            
        } catch (\Exception $e) {
            error_log("Growth calculation error for {$bucketName}: " . $e->getMessage());
            return self::getEmptyGrowthMetrics();
        }
    }

    /**
     * Find historical size from collections array based on target time
     *
     * @param array $collections Array of collection records
     * @param int $targetTimestamp Target time as timestamp
     * @return int Bucket size in bytes
     */
    private static function findHistoricalSize($collections, $targetTimestamp)
    {
        $bestMatch = null;
        $bestTimeDiff = PHP_INT_MAX;
        
        foreach ($collections as $collection) {
            $collectionTime = strtotime($collection['collected_at']);
            
            // We want collections that are older than target time
            if ($collectionTime <= $targetTimestamp) {
                $timeDiff = $targetTimestamp - $collectionTime;
                
                // Find the closest collection that's older than target time
                if ($timeDiff < $bestTimeDiff) {
                    $bestTimeDiff = $timeDiff;
                    $bestMatch = $collection;
                }
            }
        }
        
        // If no older collection found, try to find the closest collection within reasonable range
        if (!$bestMatch && count($collections) > 1) {
            // Use the second most recent collection to avoid comparing with current
            $bestMatch = $collections[1];
        }
        
        return $bestMatch ? (int)$bestMatch['bucket_size_bytes'] : 0;
    }
    
    /**
     * Get empty growth metrics array
     *
     * @return array
     */
    private static function getEmptyGrowthMetrics()
    {
        return [
            'growth_1h_bytes' => 0,
            'growth_1h_formatted' => 'N/A',
            'growth_1h_percent' => 0,
            'growth_24h_bytes' => 0,
            'growth_24h_formatted' => 'N/A',
            'growth_24h_percent' => 0,
            'growth_7d_bytes' => 0,
            'growth_7d_formatted' => 'N/A',
            'growth_7d_percent' => 0,
            'debug_collections_count' => 0,
            'debug_sizes' => []
        ];
    }

    /**
     * Get bucket size at a specific time (DEPRECATED - replaced by collection-based approach)
     *
     * @param string $bucketName
     * @param string $bucketOwner
     * @param string $targetTime
     * @return int Bucket size in bytes
     */
    private static function getBucketSizeAtTime($bucketName, $bucketOwner, $targetTime)
    {
        try {
            $size = Capsule::table('s3_bucket_sizes_history')
                ->where('bucket_name', $bucketName)
                ->where('bucket_owner', $bucketOwner)
                ->where('collected_at', '<=', $targetTime)
                ->orderBy('collected_at', 'DESC')
                ->limit(1)
                ->value('bucket_size_bytes');
            
            return $size ? (int)$size : 0;
            
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Format growth value with appropriate sign and color indication
     *
     * @param int $growthBytes
     * @return string Formatted growth string
     */
    private static function formatGrowth($growthBytes)
    {
        if ($growthBytes == 0) {
            return '0 B';
        } elseif ($growthBytes > 0) {
            return '+' . self::formatBytes($growthBytes);
        } else {
            return '-' . self::formatBytes(abs($growthBytes));
        }
    }

    /**
     * Get historical bucket size data for charts (optimized with daily aggregation)
     *
     * @param int $days Number of days to look back
     * @param string $bucketName Optional specific bucket name
     * @param string $bucketOwner Optional specific bucket owner
     *
     * @return array
     */
    public static function getHistoricalBucketData($days = 30, $bucketName = '', $bucketOwner = '')
    {
        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));

            // IMPORTANT:
            // We always record multiple collection points per bucket per day.
            // To avoid dramatically overcounting (e.g., 24 collections x 30 days),
            // we FIRST collapse to one value per bucket per day (MAX(size)),
            // THEN sum across buckets per day.

            // Step 1: per-bucket, per-day MAX(size)
            $baseQuery = Capsule::table('s3_bucket_sizes_history')
                ->select([
                    Capsule::raw('DATE(collected_at) as date'),
                    'bucket_name',
                    Capsule::raw('MAX(bucket_size_bytes) as max_size_bytes'),
                ])
                ->where('collected_at', '>=', $startDate);

            if (!empty($bucketName)) {
                $baseQuery->where('bucket_name', $bucketName);
            }

            if (!empty($bucketOwner)) {
                $baseQuery->where('bucket_owner', $bucketOwner);
            }

            $perBucketDaily = $baseQuery
                ->groupBy('bucket_name', Capsule::raw('DATE(collected_at)'))
                ->orderBy('date', 'ASC')
                ->get();

            // Step 2: aggregate per day across all buckets
            $totalsByDate = [];
            foreach ($perBucketDaily as $row) {
                $date = $row->date;
                if (!isset($totalsByDate[$date])) {
                    $totalsByDate[$date] = [
                        'total_size_bytes' => 0,
                        'bucket_count' => 0,
                    ];
                }
                $totalsByDate[$date]['total_size_bytes'] += (int) $row->max_size_bytes;
                $totalsByDate[$date]['bucket_count']++;
            }

            ksort($totalsByDate);

            // Format data for ApexCharts with timestamps
            $totalSizeChart = [];
            $individualBuckets = [];

            foreach ($totalsByDate as $date => $agg) {
                $timestamp = strtotime($date . ' 12:00:00') * 1000; // Use noon for consistent display

                $totalSizeChart[] = [
                    'x' => $timestamp,
                    'y' => (int) $agg['total_size_bytes'],
                ];
            }
            
            // For individual bucket data (if specific bucket requested), get daily max per bucket
            if (!empty($bucketName) || !empty($bucketOwner)) {
                $individualQuery = Capsule::table('s3_bucket_sizes_history')
                    ->select([
                        'bucket_name',
                        Capsule::raw('DATE(collected_at) as date'),
                        Capsule::raw('MAX(bucket_size_bytes) as max_size_bytes')
                    ])
                    ->where('collected_at', '>=', $startDate);
                
                if (!empty($bucketName)) {
                    $individualQuery->where('bucket_name', $bucketName);
                }
                
                if (!empty($bucketOwner)) {
                    $individualQuery->where('bucket_owner', $bucketOwner);
                }
                
                $individualResults = $individualQuery
                    ->groupBy(['bucket_name', Capsule::raw('DATE(collected_at)')])
                    ->orderBy('bucket_name')
                    ->orderBy('date')
                    ->get();
                
                foreach ($individualResults as $row) {
                    if (!isset($individualBuckets[$row->bucket_name])) {
                        $individualBuckets[$row->bucket_name] = [];
                    }
                    
                    $timestamp = strtotime($row->date . ' 12:00:00') * 1000;
                    $individualBuckets[$row->bucket_name][] = [
                        'x' => $timestamp,
                        'y' => (int)$row->max_size_bytes
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'total_size_chart' => $totalSizeChart,
                'individual_buckets' => $individualBuckets,
                'data_points' => count($totalSizeChart),
                'optimized' => true
            ];
            
        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, [$days, $bucketName, $bucketOwner], $e->getMessage());
            
            return [
                'status' => 'fail',
                'message' => 'Failed to retrieve historical data: ' . $e->getMessage(),
                'total_size_chart' => [],
                'individual_buckets' => [],
                'data_points' => 0
            ];
        }
    }

    /**
     * Format bytes into human readable format
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    private static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format bytes into human readable format (public helper)
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    public static function formatBytesPublic($bytes, $precision = 2)
    {
        return self::formatBytes($bytes, $precision);
    }

    /**
     * Get collection statistics
     *
     * @return array
     */
    public static function getCollectionStats()
    {
        try {
            $stats = Capsule::table('s3_bucket_sizes_history')
                ->select([
                    Capsule::raw('COUNT(*) as total_records'),
                    Capsule::raw('COUNT(DISTINCT bucket_name) as unique_buckets'),
                    Capsule::raw('COUNT(DISTINCT bucket_owner) as unique_owners'),
                    Capsule::raw('MIN(collected_at) as first_collection'),
                    Capsule::raw('MAX(collected_at) as last_collection')
                ])
                ->first();
            
            return [
                'status' => 'success',
                'stats' => [
                    'total_records' => $stats->total_records ?? 0,
                    'unique_buckets' => $stats->unique_buckets ?? 0,
                    'unique_owners' => $stats->unique_owners ?? 0,
                    'first_collection' => $stats->first_collection,
                    'last_collection' => $stats->last_collection
                ]
            ];
            
        } catch (\Exception $e) {
            logModuleCall(self::$module, __FUNCTION__, [], $e->getMessage());
            
            return [
                'status' => 'fail',
                'message' => 'Failed to retrieve collection stats: ' . $e->getMessage(),
                'stats' => [
                    'total_records' => 0,
                    'unique_buckets' => 0,
                    'unique_owners' => 0,
                    'first_collection' => null,
                    'last_collection' => null
                ]
            ];
        }
    }

    /**
     * Debug growth calculations for a specific bucket
     *
     * @param string $bucketName
     * @param string $bucketOwner
     * @return array Debug information
     */
    public static function debugBucketGrowth($bucketName, $bucketOwner)
    {
        try {
            // Get all collections for this bucket (last 100 to see full history)
            $collections = Capsule::table('s3_bucket_sizes_history')
                ->where('bucket_name', $bucketName)
                ->where('bucket_owner', $bucketOwner)
                ->select(['bucket_size_bytes', 'collected_at', 'bucket_object_count'])
                ->orderBy('collected_at', 'DESC')
                ->limit(100)
                ->get()
                ->toArray();
            
            if (empty($collections)) {
                return [
                    'status' => 'fail',
                    'message' => 'No historical data found for bucket',
                    'bucket_name' => $bucketName,
                    'bucket_owner' => $bucketOwner
                ];
            }
            
            $currentSize = $collections[0]['bucket_size_bytes'];
            $currentTime = $collections[0]['collected_at'];
            
            // Define time periods
            $now = time();
            $time1h = $now - 3600;   // 1 hour ago
            $time24h = $now - 86400; // 24 hours ago  
            $time7d = $now - 604800; // 7 days ago
            
            // Find historical sizes and their timestamps
            $historical = [
                '1h' => self::findHistoricalSizeWithDetails($collections, $time1h),
                '24h' => self::findHistoricalSizeWithDetails($collections, $time24h),
                '7d' => self::findHistoricalSizeWithDetails($collections, $time7d)
            ];
            
            // Calculate growths
            $growths = [];
            foreach ($historical as $period => $hist) {
                if ($hist['size'] > 0 && $hist['size'] != $currentSize) {
                    $growthBytes = $currentSize - $hist['size'];
                    $growthPercent = round(($growthBytes / $hist['size']) * 100, 2);
                    $growths[$period] = [
                        'growth_bytes' => $growthBytes,
                        'growth_formatted' => self::formatGrowth($growthBytes),
                        'growth_percent' => $growthPercent . '%',
                        'baseline_size' => $hist['size'],
                        'baseline_time' => $hist['time'],
                        'time_diff_hours' => round((strtotime($currentTime) - strtotime($hist['time'])) / 3600, 2)
                    ];
                } else {
                    $growths[$period] = [
                        'growth_bytes' => 0,
                        'growth_formatted' => 'N/A',
                        'growth_percent' => '0%',
                        'baseline_size' => $hist['size'],
                        'baseline_time' => $hist['time'],
                        'reason' => $hist['size'] == $currentSize ? 'Same as current size' : 'No historical data'
                    ];
                }
            }
            
            return [
                'status' => 'success',
                'bucket_name' => $bucketName,
                'bucket_owner' => $bucketOwner,
                'current' => [
                    'size_bytes' => $currentSize,
                    'size_formatted' => self::formatBytes($currentSize),
                    'time' => $currentTime,
                    'objects' => $collections[0]['bucket_object_count']
                ],
                'growth_calculations' => $growths,
                'total_collections' => count($collections),
                'collection_timeline' => array_slice(array_map(function($c) {
                    return [
                        'time' => $c['collected_at'],
                        'size' => self::formatBytes($c['bucket_size_bytes']),
                        'size_bytes' => $c['bucket_size_bytes'],
                        'objects' => $c['bucket_object_count']
                    ];
                }, $collections), 0, 10), // Show last 10 collections
                'debug_timestamps' => [
                    'now' => date('Y-m-d H:i:s', $now),
                    '1h_ago' => date('Y-m-d H:i:s', $time1h),
                    '24h_ago' => date('Y-m-d H:i:s', $time24h),
                    '7d_ago' => date('Y-m-d H:i:s', $time7d)
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'message' => 'Debug failed: ' . $e->getMessage(),
                'bucket_name' => $bucketName,
                'bucket_owner' => $bucketOwner
            ];
        }
    }
    
    /**
     * Find historical size with detailed information for debugging
     *
     * @param array $collections Array of collection records
     * @param int $targetTimestamp Target time as timestamp
     * @return array Size and details
     */
    private static function findHistoricalSizeWithDetails($collections, $targetTimestamp)
    {
        $bestMatch = null;
        $bestTimeDiff = PHP_INT_MAX;
        
        foreach ($collections as $collection) {
            $collectionTime = strtotime($collection['collected_at']);
            
            // We want collections that are older than target time
            if ($collectionTime <= $targetTimestamp) {
                $timeDiff = $targetTimestamp - $collectionTime;
                
                // Find the closest collection that's older than target time
                if ($timeDiff < $bestTimeDiff) {
                    $bestTimeDiff = $timeDiff;
                    $bestMatch = $collection;
                }
            }
        }
        
        // If no older collection found, try to find the closest collection within reasonable range
        if (!$bestMatch && count($collections) > 1) {
            // Use the second most recent collection to avoid comparing with current
            $bestMatch = $collections[1];
        }
        
        return [
            'size' => $bestMatch ? (int)$bestMatch['bucket_size_bytes'] : 0,
            'time' => $bestMatch ? $bestMatch['collected_at'] : 'N/A',
            'found' => $bestMatch !== null
        ];
    }
} 