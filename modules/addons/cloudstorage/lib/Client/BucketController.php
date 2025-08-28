<?php


namespace WHMCS\Module\Addon\CloudStorage\Client;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use DateTime;
use GuzzleHttp\Promise;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

class BucketController {

    /**
     * Variable contains the protected buckets
     *
     * @var array
     *
     */
    private $protectedBucketNames = ['csw-eazybackup-data', 'csw-obc-data'];

    /** Addon module name */
    private $module = "cloudstorage";

    /** Ceph Server Endpoint */
    private $endpoint;

    /** Ceph Server Region */
    private $region = 'us-east-1';

    /** Aws\S3\S3Client instance */
    private $s3Client = null;

    /** Aws\S3\S3Client access key */
    private $accessKey = null;

    /** Aws\S3\S3Client secret key */
    private $secretKey = null;

    /** Admin Ops Api Username */
    private $adminUser;

    /** Admin Ops Api Access Key */
    private $adminAccessKey;

    /** Admin Ops Api Secret Key */
    private $adminSecretKey;

    /**
     * Constructor to initialize endpoint
     */
    public function __construct($endpoint = null, $adminUser = null, $adminAccessKey = null, $adminSecretKey = null)
    {
        $this->endpoint = $endpoint;
        $this->adminUser = $adminUser;
        $this->adminAccessKey = $adminAccessKey;
        $this->adminSecretKey = $adminSecretKey;
    }


    /**
     * Create Bucket.
     *
     * @param Collection $user
     * @param string $bucketName
     *
     * @return array
     */
    public function createBucket($user, $bucketName, $enableVersioning = false, $enableObjectLocking = false, $retentionMode = 'GOVERNANCE', $retentionDays = 1, $setDefaultRetention = false)
    {
        // Log entry parameters for debugging
        logModuleCall($this->module, __FUNCTION__ . '_START', [
            'user_id' => $user->id,
            'bucket_name' => $bucketName,
            'enable_versioning' => $enableVersioning,
            'enable_object_locking' => $enableObjectLocking,
            'set_default_retention' => $setDefaultRetention
        ], 'Starting bucket creation process');

        if (in_array($bucketName, $this->protectedBucketNames)) {
            return ['status' => 'fail', 'message' => "The bucket name is invalid and cannot be used."];
        }

        $bucket = DBController::getRow('s3_buckets', [
            ['name', '=', $bucketName]
        ]);

        if (!is_null($bucket)) {
            return ['status' => 'fail', 'message' => "Bucket name unavailable: Bucket names must be unique globally. Please choose a unique name for your bucket to proceed."];
        }

        // Check if S3 client is initialized
        if (is_null($this->s3Client)) {
            logModuleCall($this->module, __FUNCTION__ . '_NO_S3CLIENT', ['user_id' => $user->id], 'S3 Client is not initialized');
            return ['status' => 'fail', 'message' => 'Storage connection not established. Please try again later.'];
        }

        try {
            $userId = $user->id;
            $bucketExist = true;
            $bucketOptions = ['Bucket' => $bucketName];
            
            try {
                $this->s3Client->headBucket($bucketOptions);
                return ['status' => 'fail', 'message' => 'Bucket name unavailable: Bucket names must be unique globally. Please choose a unique name for your bucket to proceed.'];
            } catch (S3Exception $e) {
                $bucketExist = false;
            }

            if (!$bucketExist) {
                if ($enableObjectLocking) {
                    $bucketOptions['ObjectLockEnabledForBucket'] = true;
                }

                try {
                    $result = $this->s3Client->createBucket($bucketOptions);
                    logModuleCall($this->module, __FUNCTION__ . '_BUCKET_CREATED', [
                        'bucket_name' => $bucketName,
                        'object_lock_enabled' => $enableObjectLocking
                    ], 'Bucket created successfully on storage');
                } catch (S3Exception $e) {
                    logModuleCall($this->module, __FUNCTION__ . '_CREATE_FAILED', [
                        'bucket_name' => $bucketName,
                        'aws_error_code' => $e->getAwsErrorCode(),
                        'aws_error_message' => $e->getAwsErrorMessage()
                    ], 'Bucket creation failed: ' . $e->getMessage());

                    return ['status' => 'fail', 'params' => $bucketOptions, 'message' => 'Bucket creation failed. Please try again later.'];
                }

                if ($enableVersioning) {
                    try {
                        $this->s3Client->putBucketVersioning([
                            'Bucket' => $bucketName,
                            'VersioningConfiguration' => [
                                'Status' => 'Enabled'
                            ],
                        ]);
                    } catch (S3Exception $e) {
                        logModuleCall($this->module, __FUNCTION__ . '_VERSIONING_FAILED', [
                            'bucket_name' => $bucketName,
                            'aws_error_code' => $e->getAwsErrorCode()
                        ], 'Versioning enable failed: ' . $e->getMessage());

                        return ['status' => 'fail', 'message' => 'Failed to enable versioning.'];
                    }
                }

                // Only set default retention policy if both object locking is enabled AND user requested it
                if ($enableObjectLocking && $setDefaultRetention) {
                    try {
                        $this->s3Client->putObjectLockConfiguration([
                            'Bucket' => $bucketName,
                            'ObjectLockConfiguration' => [
                                'ObjectLockEnabled' => 'Enabled',
                                'Rule' => [
                                    'DefaultRetention' => [
                                        'Mode' => $retentionMode,
                                        'Days' => $retentionDays
                                    ],
                                ],
                            ],
                        ]);
                        logModuleCall($this->module, __FUNCTION__ . '_RETENTION_SET', [
                            'bucket_name' => $bucketName,
                            'retention_mode' => $retentionMode,
                            'retention_days' => $retentionDays
                        ], 'Default retention policy configured');
                    } catch (S3Exception $e) {
                        logModuleCall($this->module, __FUNCTION__ . '_RETENTION_FAILED', [
                            'bucket_name' => $bucketName,
                            'aws_error_code' => $e->getAwsErrorCode()
                        ], 'Object lock configuration failed: ' . $e->getMessage());

                        return ['status' => 'fail', 'message' => 'Failed to configure object lock.'];
                    }
                }
                
                $params = [
                    'bucket' => $bucketName,
                    'stats' => true
                ];
                
                if (!empty($user->tenant_id)) {
                    $params['bucket'] = $user->tenant_id . '/' . $bucketName;
                }

                // Simple retry mechanism for bucket info verification (mainly for rare consistency delays)
                $maxRetries = 3;
                $bucketInfo = null;
                
                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    $bucketInfoResponse = AdminOps::getBucketInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $params);

                    if ($bucketInfoResponse['status'] != 'success') {
                        if ($attempt < $maxRetries) {
                            sleep(1);
                            continue;
                        }
                        logModuleCall($this->module, __FUNCTION__ . '_ADMIN_API_FAILED', [
                            'bucket_name' => $bucketName,
                            'response' => $bucketInfoResponse
                        ], 'Admin API failed after retries');
                        break;
                    }
                    
                    // Validate that we got meaningful bucket data
                    if ($this->isValidBucketInfo($bucketInfoResponse['data'], $bucketName)) {
                        $bucketInfo = $bucketInfoResponse['data'];
                        break;
                    } else {
                        if ($attempt < $maxRetries) {
                            sleep($attempt); // Progressive delay
                        }
                    }
                }

                // Final validation
                if (is_null($bucketInfo)) {
                    logModuleCall($this->module, __FUNCTION__ . '_VERIFICATION_FAILED', [
                        'bucket_name' => $bucketName,
                        'attempts' => $maxRetries
                    ], 'Failed to verify bucket creation after retries');
                    
                    return [
                        'status' => 'fail', 
                        'message' => 'Bucket creation could not be verified. The bucket may still be propagating through the storage cluster. Please wait a moment and refresh the page.'
                    ];
                }
                
                $creationDateTime = new DateTime($bucketInfo['creation_time']);
                $creationTime = $creationDateTime->format('Y-m-d H:i:s');
                
                $dbData = [
                    'user_id'             => $userId,
                    'name'                => $bucketName,
                    's3_id'               => $bucketInfo['id'],
                    'versioning'          => $enableVersioning ? 'enabled' : 'off',
                    'object_lock_enabled' => $enableObjectLocking ? '1': '0',
                    'is_active'           => 1,
                    'created_at'          => $creationTime
                ];
                
                DBController::saveBucket($dbData);

                logModuleCall($this->module, __FUNCTION__ . '_SUCCESS', [
                    'bucket_name' => $bucketName,
                    'bucket_id' => $bucketInfo['id']
                ], 'Bucket creation completed successfully');
                
                return ['status' => 'success', 'message' => 'Bucket has been created successfully.'];
            } else {
                return ['status' => 'fail', 'message' => 'Bucket name unavailable: Bucket names must be unique globally. Please choose a unique name for your bucket to proceed.'];
            }
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__ . '_GENERAL_EXCEPTION', [
                'bucket_name' => $bucketName,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_message' => $e->getAwsErrorMessage()
            ], 'General S3 exception during bucket creation: ' . $e->getMessage());

            return ['status' => 'fail', 'message' => 'Bucket creation failed. Please try again later.'];
        }
    }

    /**
     * Delete Bucket.
     *
     * @param string $userId
     * @param string $bucketName
     *
     * @return array
     */
    public function deleteBucket($userId, $bucketName)
    {
        if (in_array($bucketName, $this->protectedBucketNames)) {
            return ['status' => 'fail', 'message' => "The bucket name is invalid and cannot be used."];
        }

        // $response = $this->checkAndHandleObjectLock($bucketName);
        // if ($response['status'] == 'fail') {
        //     return $response;
        // }

        $response = $this->deleteBucketContents($bucketName);
        if ($response['status'] == 'fail') {
            return $response;
        }

        $response = $this->deleteBucketVersionsAndMarkers($bucketName);
        if ($response['status'] == 'fail') {
            return $response;
        }

        $response = $this->handleIncompleteMultipartUploads($bucketName);
        if ($response['status'] == 'fail') {
            return $response;
        }

        try {
            $this->s3Client->deleteBucket(['Bucket' => $bucketName]);

            // Wait until the bucket deletion is complete
            $this->s3Client->waitUntil('BucketNotExists', ['Bucket' => $bucketName]);

            // delete the records from database
            DBController::deleteRecord('s3_buckets', [
                ['user_id', '=', $userId],
                ['name', '=', $bucketName]
            ]);

            return ['status' => 'success', 'message' => 'Bucket and contents queued for deletion, removal will now proceed in the background'];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$userId, $bucketName], $e->getMessage());

            return ['status' => 'fail', 'message' => 'An error occurred while deleting the bucket.'];
        }
    }

    /**
     * List Bucket Contents
     *
     * @param array $options
     * @param string $action
     *
     * @return array
     */
    public function listBucketContents(array $options, $action = '')
    {
        if ($action != 'all') {
            return $this->getBucketObjects($options);
        } else {
            return $this->getAllBucketObjects($options);
        }
    }

    /**
     * Update User Access Key
     *
     * @param $username
     * @param $userId
     *
     * @return null
     */
    public function updateUserAccessKey($username, $userId, $encryptionKey)
    {
        $userAccessKey = DBController::getRow('s3_user_access_keys', [
            ['user_id', '=', $userId]
        ], ['access_key', 'secret_key']);
        if (!is_null($userAccessKey)) {
            $accessKey = HelperController::decryptKey($userAccessKey->access_key, $encryptionKey);
            $result = AdminOps::removeKey($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $accessKey);

            if ($result['status'] != 'success') {
                return $result;
            }
        }

        $result = AdminOps::createKey($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $username);
        if ($result['status'] != 'success') {
            return $result;
        }
        $accessKey = $result['data'][0]['access_key'];
        $secretKey = $result['data'][0]['secret_key'];
        $accessKeyEncrypted = HelperController::encryptKey($accessKey, $encryptionKey);
        $secretKeyEncrypted = HelperController::encryptKey($secretKey, $encryptionKey);

        DBController::deleteRecord('s3_user_access_keys', [
            ['user_id', '=', $userId]
        ]);

        DBController::insertRecord('s3_user_access_keys', [
            'user_id' => $userId,
            'access_key' => $accessKeyEncrypted,
            'secret_key' => $secretKeyEncrypted
        ]);


        return [
            'status' => 'success',
            'message' => 'Access keys updated successfully.'
        ];
    }

    /**
     * Get UserUsage Stats.
     *
     * @param string $sessionId
     *
     * @return JSON|null
     */
    public function getUserUsageStats($username)
    {
        // Sanitize the username
        $username = escapeshellarg($username);
        // Calculate the start and end date
        $startDate = date("Y-m-d", strtotime("-30 days")); // Date 30 days ago
        $endDate = date("Y-m-d", strtotime("+1 day")); // Tomorrow's date
        $params = [
            'uid' => $username,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'stats' => true,
            'show_entries' => true
        ];

        $result = AdminOps::getUsage($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $params);

        return $result;
    }

    /**
     * Retrieves the current statistics for a specific bucket.
     *
     * @param array $usageStats, Array of usage statistics.
     * @param string $bucket, Name of the bucket to find stats for.
     * @return array|null The stats for the specified bucket or null if not found.
     */
    public function getCurrentBucketStats($usageStats, $bucket)
    {
        if (!is_array($usageStats)) {
            return null;
        }

        foreach ($usageStats as $stat) {
            if (isset($stat['bucket']) && $stat['bucket'] === $bucket) {
                return $stat;
            }
        }

        return null;
    }

    /**
     * Get Total Usage For Billing Period
     *
     * @param array $userIds
     * @param $startDate
     * @param $endDate
     *
     * @return array
     */
    public function getTotalUsageForBillingPeriod($userIds, $startDate, $endDate)
    {
        $totalBytesSent = 0;
        $totalBytesReceived = 0;
        $totalOps = 0;

        $query = Capsule::table('s3_transfer_stats_summary')
            ->selectRaw('
                SUM(bytes_sent) as total_bytes_sent,
                SUM(bytes_received) as total_bytes_received,
                SUM(ops) as total_ops
            ')
            ->whereIn('user_id', $userIds);

        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $transferStatsSummaries = $query->groupBy('user_id')->get();

        if (count($transferStatsSummaries)) {
            foreach ($transferStatsSummaries as $transferStatsSummary) {
                $totalBytesSent     += $transferStatsSummary->total_bytes_sent;
                $totalBytesReceived += $transferStatsSummary->total_bytes_received;
                $totalOps           += $transferStatsSummary->total_ops;
            }
        }

        return [
            'total_bytes_sent'     => $totalBytesSent,
            'total_bytes_received' => $totalBytesReceived,
            'total_ops'            => $totalOps
        ];
    }


    /**
     * Get User Bucket Summary
     *
     * @param array $userIds
     * @param $startDate
     * @param $endDate
     *
     * @return array
     */
    public function getUserBucketSummary($userIds, $startDate, $endDate)
    {
        if (empty($startDate) || empty($endDate)) {
            return [];
        }

        try {
            $date2 = new DateTime($startDate);
            $date1 = new DateTime($endDate);
        } catch (\Exception $e) {
            return [];
        }

        // Calculate the "limit" (the day difference)
        $limit = $date1->diff($date2)->days;
        if ($limit == 0) {
            $limit = 1;
        }

        // Using a direct SQL approach with a single subquery for better performance
        $query = Capsule::table('s3_bucket_stats_summary AS s')
            ->selectRaw('
                s.usage_day AS date,
                SUM(
                    (
                        SELECT MAX(total_usage)
                        FROM s3_bucket_stats_summary AS inner_s
                        WHERE inner_s.user_id = s.user_id
                        AND inner_s.bucket_id = s.bucket_id
                        AND inner_s.usage_day = s.usage_day
                    )
                ) AS total_usage
            ')
            ->whereIn('s.user_id', $userIds)
            ->where('s.usage_day', '>=', $startDate)
            ->where('s.usage_day', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->limit($limit);

        $bucketStatsSummary = $query->get();

        $aggregatedBucketSummary = [];
        foreach ($bucketStatsSummary as $row) {
            $aggregatedBucketSummary[] = [
                'period'      => $row->date,
                'total_usage' => $row->total_usage
            ];
        }

        return $aggregatedBucketSummary;
    }

    /**
     * Get today's peak usage
     *
     * @return string
     */
    public function getPeakUsage($userIds)
    {
        $today = date('Y-m-d');
        $peakUsage = Capsule::table('s3_bucket_stats_summary')
            ->selectRaw('SUM(total_usage) AS total_size')
            ->whereIn('user_id', $userIds)
            ->where('usage_day', '=', $today)
            ->first();

        return $peakUsage->total_size ? HelperController::formatSizeUnits($peakUsage->total_size) : '0 Bytes';
    }


    /**
     * Get User Transfer Summary
     *
     * @param array $userIds
     * @param integer $limit
     *
     * @return array
     */
    public function getUserTransferSummary($userIds, $limit = 24)
    {
        if ($limit == 24 || $limit == 'day') {
            $transferStatsSummary = Capsule::table('s3_transfer_stats_summary')
                ->select(
                    'created_at as usage_period',
                    Capsule::raw('SUM(bytes_sent) as total_bytes_sent'),
                    Capsule::raw('SUM(bytes_received) as total_bytes_received'),
                    Capsule::raw('SUM(ops) as total_ops'),
                    Capsule::raw('SUM(successful_ops) as total_successful_ops')
                )
                ->whereIn('user_id', $userIds)
                ->whereDate('created_at', date('Y-m-d'))
                ->groupBy('usage_period')
                ->orderBy('usage_period', 'ASC')
                ->get();
        } else {
            $periods = HelperController::getDateRange($limit);
            $transferStatsSummary = Capsule::table('s3_transfer_stats_summary')
                ->select(
                    Capsule::raw('DATE_FORMAT(created_at, "%Y-%m-%d") AS usage_period'),
                    Capsule::raw('SUM(bytes_sent) as total_bytes_sent'),
                    Capsule::raw('SUM(bytes_received) as total_bytes_received'),
                    Capsule::raw('SUM(ops) as total_ops'),
                    Capsule::raw('SUM(successful_ops) as total_successful_ops')
                )
                ->whereIn('user_id', $userIds)
                ->whereDate('created_at', '>=', $periods['start'])
                ->whereDate('created_at', '<=', $periods['end'])
                ->groupBy('usage_period')
                ->orderBy('usage_period', 'ASC')
                ->get();
        }

        $aggregatedTransferSummary = [];

        foreach ($transferStatsSummary as $row) {
            $aggregatedTransferSummary[] = [
                'period' => $row->usage_period,
                'total_bytes_sent' => $row->total_bytes_sent,
                'total_bytes_received' => $row->total_bytes_received,
                'total_ops' => $row->total_ops,
                'total_successful_ops' => $row->total_successful_ops
            ];
        }

        return $aggregatedTransferSummary;
    }

    /**
     * Get Total Bucket Size For User
     *
     * @param array $buckets
     *
     * @return array
     */
    public function getTotalBucketSizeForUser($buckets)
    {
        if (count($buckets) == 0) {
            return [
                'total_size' => 0,
                'total_objects' => 0,
                'latest_update' => null
            ];
        }
        $bucketIds = array_keys($buckets);
        $bucketStatIds = Capsule::table('s3_bucket_stats')
            ->selectRaw('MAX(id) as id')
            ->whereIn('bucket_id', $bucketIds)
            ->groupBy('bucket_id')
            ->pluck('id')
            ->all();

        if (count($bucketStatIds) == 0) {
            return [
                'total_size' => 0,
                'total_objects' => 0,
                'latest_update' => null
            ];
        }

        $totalSize = 0;
        $totalObjects = 0;
        $lastUpdated = null;
        $buckets = array_map(function ($value) {
            return ['name' => $value, 'size' => 0];
        }, $buckets);

        $bucketStats = Capsule::table('s3_bucket_stats')
            ->selectRaw('size, bucket_id, num_objects, DATE_FORMAT(created_at, "%Y-%m-%d") as last_updated')
            ->whereIn('id', $bucketStatIds)
            ->get();

        foreach ($bucketStats as $stat) {
            if (!is_null($stat->size)) {
                $totalSize += $stat->size;
                $lastUpdated = $stat->last_updated;
            }
            $buckets[$stat->bucket_id]['size'] = $stat->size;
            $totalObjects += $stat->num_objects;
        }

        return [
            'buckets' => $buckets,
            'total_size' => $totalSize,
            'total_objects' => $totalObjects,
            'latest_update' => $lastUpdated
        ];
    }

    /**
     * Get Bucket Peak Usage and Time.
     *
     * @param array $userIds
     * @param array $billingPeriod
     *
     * @return object|null
    */
    public function findPeakBucketUsage($userIds, $billingPeriod)
    {
        return Capsule::table('s3_bucket_stats_summary')
            ->selectRaw('
                usage_day AS exact_timestamp,
                SUM(total_usage) AS total_size
            ')
            ->whereIn('user_id', $userIds)
            ->where('usage_day', '>=', $billingPeriod['start'])
            ->where('usage_day', '<=', $billingPeriod['end'])
            ->groupBy('exact_timestamp')
            ->orderBy('total_size', 'DESC')
            ->first();
    }

    /**
     * List Incomplete Multipart Uploads
     *
     * @param $s3Client
     * @param $bucket
     *
     * @return array
     */
    public function listIncompleteMultipartUploads($s3Client, $bucket)
    {
        $incompleteUploads = [];
        try {
            // List all multipart uploads
            $result = $s3Client->listMultipartUploads(['Bucket' => $bucket]);
            if (isset($result['Uploads']) && !empty($result['Uploads'])) {
                foreach ($result['Uploads'] as $upload) {
                    $uploadID = $upload['UploadId'];
                    $key = $upload['Key'];
                    // List all parts of this multipart upload
                    $partsResult = $s3Client->listParts([
                        'Bucket'   => $bucket,
                        'Key'      => $key,
                        'UploadId' => $uploadID,
                    ]);
                    $totalSize = 0;
                    foreach ($partsResult['Parts'] as $part) {
                        $totalSize += $part['Size'];
                    }
                    // Add to the list of incomplete uploads
                    $incompleteUploads[] = [
                        'Key' => $key,
                        'UploadId' => $uploadID,
                        'Size' => $totalSize
                    ];
                }
            }
        } catch (AwsException $e) {
            return [];
        }

        return $incompleteUploads;
    }

    /**
     * Make Bucket Public
     *
     * @param $s3Client
     * @param $bucketName
     *
     * @return string
     */
    public function makeBucketPublic($s3Client, $bucketName)
    {
        $publicPolicy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'AddPerm',
                    'Effect' => 'Allow',
                    'Principal' => '*',
                    'Action' => 's3:GetObject',
                    'Resource' => "arn:aws:s3:::$bucketName/*"
                ]
            ]
        ]);

        try {
            $s3Client->putBucketPolicy([
                'Bucket' => $bucketName,
                'Policy' => $publicPolicy,
            ]);

            return "Bucket is now public.";
        } catch (S3Exception $e) {
            return "Error making bucket public.";
        }
    }

    /**
     * Make Bucket Private
     *
     * @param $s3Client
     * @param $bucketName
     *
     * @return string
     */
    public function makeBucketPrivate($s3Client, $bucketName)
    {
        try {
            $s3Client->deleteBucketPolicy([
                'Bucket' => $bucketName,
            ]);

            return "Bucket is now private.";
        } catch (S3Exception $e) {
            return "Error making bucket private.";
        }
    }

    /**
     * Create Connection with S3 Client
     *
     * @param $userId
     *
     * @return object|array
     */
    public function connectS3Client($userId, $encryptionKey)
    {
        try {
            $userAccessKey = DBController::getRow('s3_user_access_keys', [
                ['user_id', '=', $userId]
            ]);

            if (is_null($userAccessKey)) {
                return ['status' => 'fail', 'message' => 'Access keys missing.'];
            }

            $this->accessKey = HelperController::decryptKey($userAccessKey->access_key, $encryptionKey);
            $this->secretKey = HelperController::decryptKey($userAccessKey->secret_key, $encryptionKey);
            $s3ClientConfig = [
                'version' => 'latest',
                'region' => $this->region,
                'endpoint' => $this->endpoint,
                'credentials' => [
                    'key' => $this->accessKey,
                    'secret' => $this->secretKey,
                ],
                'use_path_style_endpoint' => true
            ];

            $this->s3Client = new S3Client($s3ClientConfig);

            return [
                'status' => 'success',
                's3client' => $this->s3Client
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$userId, $encryptionKey], $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Connection failure. Please try again later or contact support.'
            ];
        }
    }

    /**
     * Delete Bucket Object
     *
     * @param string $bucketName
     * @param string $fileKey
     * @param string $version
     *
     * @return array
     */
    public function deleteBucketObject($bucketName, $objectsToDelete)
    {
        try {
            $result = $this->s3Client->deleteObjects([
                'Bucket' => $bucketName,
                'Delete' => [
                    'Objects' => $objectsToDelete,
                    'Quiet' => false
                ]
            ]);


            if ($result['@metadata']['statusCode'] == 200) {
                $message = 'File has been deleted successfully.';
            } else {
                $message = 'Delete request failed.';
            }

            return ['status' => 'success', 'message' => $message];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $objectsToDelete], $e->getMessage());

            return ['status' => 'fail', 'message' => 'File to delete object. Please try again or contact support.'];
        }
    }

    /**
     * Save Uploaded Files
     *
     * @param string $bucketName
     * @param string $key
     * @param string $sourceFile
     *
     * @return array
     */
    public function saveUploadedFiles($bucketName, $key, $sourceFile)
    {
        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $bucketName,
                'Key'    => $key,
                'SourceFile' => $sourceFile,
                'ACL'    => 'public-read'
            ]);

            $message = "File uploaded successfully. File URL: " . $result['ObjectURL'];

            return ['status' => 'success', 'message' => $message];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $key, $sourceFile], $e->getMessage());

            return ['status' => 'fail', 'message' => 'Failed to upload file. Please try again or contact support.'];
        }
    }

    /**
     * Get the bucket objects
     *
     * @param string $bucketName
     *
     * @return array
     */
    public function getBucketVersioning($bucketName)
    {
        try {
            $result = $this->s3Client->getBucketVersioning([
                'Bucket' => $bucketName
            ]);

            if ($result['@metadata']['statusCode'] == 200) {
                $status = 'success';
                $versionStatus = isset($result['Status']) ? 'enabled' : 'off';
            } else {
                $status = 'fail';
                $versionStatus = 'off';
            }

            return ['status' => $status, 'version_status' => $versionStatus];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());

            return ['status' => 'fail', 'message' => 'Unable to get the bucket version. Please try again or contact support.'];
        }
    }

     /**
     * Get the bucket object lock configuration
     *
     * @param string $bucketName
     *
     * @return array
     */
    public function getBucketObjectLockConfiguration($bucketName)
    {
        try {
            $this->s3Client->getObjectLockConfiguration([
                'Bucket' => $bucketName
            ]);

            return ['status' => 'success', 'object_lock_enabled' => '1'];
        } catch (S3Exception $e) {
            return ['status' => 'fail', 'object_lock_enabled' => '0'];
        }
    }

    /**
     * Get the bucket objects
     *
     * @param array $options
     *
     * @return array
     */
    private function getBucketObjects($options)
    {
        $params = [
            'Bucket' => $options['bucket'],
            'Delimiter' => $options['delimiter']
        ];

        if (!empty($options['max_keys']) && is_numeric($options['max_keys']) && $options['max_keys'] > 0) {
            $params['MaxKeys'] = $options['max_keys'];
        }

        if (!empty($options['prefix'])) {
            $params['Prefix'] = $options['prefix'];
        }

        if (!empty($options['continuation_token'])) {
            $params['ContinuationToken'] = $options['continuation_token'];
        }

        $objects = [];
        $count = 0;
        $continuationToken = null;

        try {
            $result = $this->s3Client->listObjectsV2($params);

            if (isset($result['CommonPrefixes'])) {
                foreach ($result['CommonPrefixes'] as $prefix) {
                    if (!isset($prefix['Prefix'])) {
                        continue;
                    }
                    $objects[] = [
                        'name' => $prefix['Prefix'],
                        'size' => '0',
                        'type' => 'folder',
                        'modified' => ''
                    ];
                    $count++;
                }
            }

            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $content) {
                    if (!isset($content['Key'])) {
                        continue;
                    }
                    $objects[] = [
                       'name' => $content['Key'],
                       'size' => HelperController::formatSizeUnits($content['Size']),
                       'type' => 'file',
                       'modified' => $content['LastModified']->format("d M Y")
                    ];
                    $count++;
                }
            }

            if ($result['IsTruncated']) {
                $continuationToken = $result['NextContinuationToken'];
            }
            $status = 'success';
            $message = 'Objects retrieved successfully.';
        } catch (S3Exception $e) {
            $status = 'fail';
            $message = 'Something went wrong. Please try again later or contact support.';

            logModuleCall($this->module, __FUNCTION__, $options, $e->getMessage());
        }

        return [
            'continuationToken' => $continuationToken,
            'count' => $count,
            'data' => $objects,
            'message' => $message,
            'status' => $status
        ];
    }

    /**
     * Get all the bucket objects
     *
     * @param array $options
     *
     * @return array
     */
    private function getAllBucketObjects($options)
    {
        $objects = [];
        $count = 0;
        $continuationToken = null;
        $params = [
            'Bucket' => $options['bucket'],
            'Delimiter' => $options['delimiter']
        ];

        if (!empty($options['max_keys']) && is_numeric($options['max_keys']) && $options['max_keys'] > 0) {
            $params['MaxKeys'] = $options['max_keys'];
        }

        if (!empty($options['prefix'])) {
            $params['Prefix'] = $options['prefix'];
        }

        if (!empty($options['continuation_token'])) {
            $params['ContinuationToken'] = $options['continuation_token'];
        }

        try {
            do {
                if ($continuationToken) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $this->s3Client->listObjectsV2($params);

                if (isset($result['CommonPrefixes'])) {
                    foreach ($result['CommonPrefixes'] as $prefix) {
                        if (!isset($prefix['Prefix'])) {
                            continue;
                        }
                        $objects[] = [
                            'name' => $prefix['Prefix'],
                            'size' => '0',
                            'type' => 'folder',
                            'modified' => ''
                        ];
                        $count++;
                    }
                }

                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $content) {
                        if (!isset($content['Key'])) {
                            continue;
                        }
                        $objects[] = [
                           'name' => $content['Key'],
                           'size' => HelperController::formatSizeUnits($content['Size']),
                           'type' => 'file',
                           'modified' => $content['LastModified']->format("d M Y")
                        ];
                        $count++;
                    }
                }

                // Check if there's a continuation token for the next page
                $continuationToken = $result['NextContinuationToken'] ?? null;

            } while ($continuationToken);

            $status = 'success';
            $message = 'Objects retrieved successfully.';

        } catch (S3Exception $e) {
            $status = 'fail';
            $message = 'Something went wrong. Please try again later or contact support.';

            logModuleCall($this->module, __FUNCTION__, $options, $e->getMessage());
        }

        return [
            'continuationToken' => $continuationToken,
            'count' => $count,
            'data' => $objects,
            'message' => $message,
            'status' => $status
        ];
    }

    /**
     * Check Object Lock for Bucket
     *
     * @param string $bucketName
     *
     * @return array
     */
    private function checkAndHandleObjectLock($bucketName)
    {
        $status = 'success';
        $message = 'Handle object lock disabled.';
        try {
            $objectLockConfig = $this->s3Client->getObjectLockConfiguration(['Bucket' => $bucketName]);
            if (
                isset($objectLockConfig['ObjectLockConfiguration']['ObjectLockEnabled']) &&
                $objectLockConfig['ObjectLockConfiguration']['ObjectLockEnabled'] === 'Enabled'
            ) {
                $status = 'fail';
                $message = 'Attempt to delete bucket with Object Lock enabled: '. $bucketName;
            }
        } catch (S3Exception $e) {
            $message = "Proceeding with deletion as Object Lock configuration not found for bucket: $bucketName";
            $status = 'fail';

            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => $message
            ];
        }

        return [
            'status' => $status,
            'message' => $message
        ];
    }

    /**
     * Delete Bucket Contents
     *
     * @param string $bucketName
     *
     * @return array
     */
    private function deleteBucketContents($bucketName)
    {
        try {
            $deletePromises = [];

            do {
                $objects = $this->s3Client->listObjectsV2([
                    'Bucket' => $bucketName,
                ]);

                if (!empty($objects['Contents'])) {
                    $keys = array_map(function ($object) {
                        return ['Key' => $object['Key']];
                    }, $objects['Contents']);

                    $deletePromises[] = $this->s3Client->deleteObjectsAsync([
                        'Bucket' => $bucketName,
                        'Delete' => ['Objects' => $keys],
                    ]);
                }
            } while (isset($objects['IsTruncated']) && $objects['IsTruncated']);

            // Wait for all delete promises to complete
            Promise\all($deletePromises)->wait();

            return [
                'status' => 'success',
                'message' => 'Deleted all contents.',
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Delete bucket contenat failed. Please try again or contact support.',
            ];
        }
    }

    /**
     * Delete Bucket Versions and Markers
     *
     * @param string $bucketName
     *
     * @return array
     */
    private function deleteBucketVersionsAndMarkers($bucketName)
    {
        try {
            $deletePromises = [];

            do {
                $versions = $this->s3Client->listObjectVersions([
                    'Bucket' => $bucketName,
                ]);

                $objectsToDelete = [];

                if (!empty($versions['Versions'])) {
                    foreach ($versions['Versions'] as $version) {
                        $objectsToDelete[] = [
                            'Key'       => $version['Key'],
                            'VersionId' => $version['VersionId'],
                        ];
                    }
                }

                if (!empty($versions['DeleteMarkers'])) {
                    foreach ($versions['DeleteMarkers'] as $marker) {
                        $objectsToDelete[] = [
                            'Key'       => $marker['Key'],
                            'VersionId' => $marker['VersionId'],
                        ];
                    }
                }

                if (!empty($objectsToDelete)) {
                    $chunks = array_chunk($objectsToDelete, 1000);

                    foreach ($chunks as $chunk) {
                        $deletePromises[] = $this->s3Client->deleteObjectsAsync([
                            'Bucket' => $bucketName,
                            'Delete' => ['Objects' => $chunk],
                        ]);
                    }
                }
            } while (isset($versions['IsTruncated']) && $versions['IsTruncated']);

            // Wait for all delete promises to complete
            Promise\all($deletePromises)->wait();

            return [
                'status' => 'success',
                'message' => 'Deleted all versions and markers.',
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Delete bucket versions and markers failed. Please try again or contact support.',
            ];
        }
    }

    /**
     * Handle Incomplete Multipart Uploads
     *
     * @param string $bucketName
     *
     * @return array
     */
    private function handleIncompleteMultipartUploads($bucketName)
    {
        try {
            $deletePromises = [];
            // Abort all incomplete multipart uploads
            do {
                $uploads = $this->s3Client->listMultipartUploads([
                    'Bucket' => $bucketName,
                ]);

                if (!empty($uploads['Uploads'])) {
                    foreach ($uploads['Uploads'] as $upload) {
                        $deletePromises[] = $this->s3Client->abortMultipartUploadAsync([
                            'Bucket'   => $bucketName,
                            'Key'      => $upload['Key'],
                            'UploadId' => $upload['UploadId'],
                        ]);
                    }
                }
            } while (isset($uploads['IsTruncated']) && $uploads['IsTruncated']);

            // Wait for all delete promises to complete
            Promise\all($deletePromises)->wait();

            return [
                'status' => 'success',
                'message' => 'Deleted all versions and markers.'
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Handle incomplete uploads failed. Please try again or contact support.'
            ];
        }
    }

    /**
     * Get historical usage data for specified users within a date range
     *
     * @param array $userIds Array of user IDs
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return array Historical usage data
     */
    public function getHistoricalUsage($userIds, $startDate, $endDate)
    {
        // Get daily usage data for charts (total_storage) - FIXED: Aggregate by date
        $dailyUsageQuery = Capsule::table('s3_historical_stats')
            ->select('date', Capsule::raw('SUM(total_storage) as total_storage'))
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        // Get transfer data for charts (bytes_sent, bytes_received, operations) - FIXED: Aggregate by date
        $transferDataQuery = Capsule::table('s3_historical_stats')
            ->select('date', 
                Capsule::raw('SUM(bytes_sent) as bytes_sent'),
                Capsule::raw('SUM(bytes_received) as bytes_received'),
                Capsule::raw('SUM(operations) as operations')
            )
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        // Find peak usage (date and size of the highest storage day) - FIXED: Use aggregated totals
        $peakUsageQuery = Capsule::table('s3_historical_stats')
            ->select('date', Capsule::raw('SUM(total_storage) as total_storage'))
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('total_storage', 'desc')
            ->orderBy('date', 'asc')
            ->first();

        // Convert peak usage to expected format
        $peakUsage = null;
        if ($peakUsageQuery) {
            $peakUsage = (object)[
                'date' => $peakUsageQuery->date,
                'size' => $peakUsageQuery->total_storage
            ];
        }

        // Calculate summary statistics for the period (Ingress, Egress, Operations) - This was already correct
        $summary = Capsule::table('s3_historical_stats')
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw(
                'SUM(bytes_sent) as total_bytes_sent,
                 SUM(bytes_received) as total_bytes_received,
                 SUM(operations) as total_operations'
            )
            ->first();

        // Ensure summary object has defaults if no data is found
        if (!$summary) {
            $summary = (object)[
                'total_bytes_sent' => 0,
                'total_bytes_received' => 0,
                'total_operations' => 0,
            ];
        } else {
            // Ensure all expected fields are present, even if SUM returns NULL (e.g., no matching rows)
            $summary->total_bytes_sent = $summary->total_bytes_sent ?? 0;
            $summary->total_bytes_received = $summary->total_bytes_received ?? 0;
            $summary->total_operations = $summary->total_operations ?? 0;
        }

        return [
            'daily_usage'   => $dailyUsageQuery,
            'transfer_data' => $transferDataQuery,
            'peak_usage'    => $peakUsage, // Now correctly aggregated by date
            'summary'       => $summary    // This was already correct
        ];
    }

    /**
     * Check if bucket is completely empty (no objects, versions, delete markers, multipart uploads)
     * This is required before deleting object-locked buckets
     *
     * @param string $bucketName
     * @return array
     */
    public function isBucketCompletelyEmpty($bucketName)
    {
        try {
            // Check for regular objects
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $bucketName,
                'MaxKeys' => 1
            ]);
            
            if (!empty($objects['Contents'])) {
                return [
                    'status' => 'fail', 
                    'empty' => false,
                    'message' => 'Bucket contains objects that must be deleted first.'
                ];
            }

            // Check for object versions and delete markers
            $versions = $this->s3Client->listObjectVersions([
                'Bucket' => $bucketName,
                'MaxKeys' => 1
            ]);
            
            if (!empty($versions['Versions']) || !empty($versions['DeleteMarkers'])) {
                return [
                    'status' => 'fail', 
                    'empty' => false,
                    'message' => 'Bucket contains object versions or delete markers that must be removed first.'
                ];
            }

            // Check for incomplete multipart uploads
            $uploads = $this->s3Client->listMultipartUploads([
                'Bucket' => $bucketName,
                'MaxUploads' => 1
            ]);
            
            if (!empty($uploads['Uploads'])) {
                return [
                    'status' => 'fail', 
                    'empty' => false,
                    'message' => 'Bucket contains incomplete multipart uploads that must be aborted first.'
                ];
            }

            return [
                'status' => 'success', 
                'empty' => true,
                'message' => 'Bucket is completely empty and can be deleted.'
            ];

        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());
            
            return [
                'status' => 'fail', 
                'empty' => false,
                'message' => 'Unable to verify bucket contents. Please try again later.'
            ];
        }
    }

    /**
     * Update historical stats for a user
     * This should be called daily via a cron job
     *
     * @param int $userId User ID
     * @return void
     */
    public function updateHistoricalStats($userId)
    {
        $today = date('Y-m-d');

        // Get user information
        $user = Capsule::table('s3_users')->where('id', $userId)->first();
        if (!$user) {
            logModuleCall('cloudstorage', 'updateHistoricalStats', ['userId' => $userId], 'User not found');
            return;
        }

        $username = $user->username;

        // First, try to get today's data from existing summary tables (if billing system has run)
        $todayStorageUsage = Capsule::table('s3_bucket_stats_summary')
            ->where('user_id', $userId)
            ->where('usage_day', $today)
            ->sum('total_usage') ?? 0;

        $todayTransferStats = Capsule::table('s3_transfer_stats_summary')
            ->where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->select(
                Capsule::raw('SUM(bytes_sent) as total_bytes_sent'),
                Capsule::raw('SUM(bytes_received) as total_bytes_received'),
                Capsule::raw('SUM(ops) as total_ops')
            )
            ->first();

        // If we have recent summary data, use it (preferred method)
        if ($todayStorageUsage > 0 || ($todayTransferStats && ($todayTransferStats->total_bytes_sent > 0 || $todayTransferStats->total_bytes_received > 0))) {
            $totalStorage = $todayStorageUsage;
            $dailyBytesSent = $todayTransferStats->total_bytes_sent ?? 0;
            $dailyBytesReceived = $todayTransferStats->total_bytes_received ?? 0;
            $dailyOperations = $todayTransferStats->total_ops ?? 0;

            logModuleCall(
                'cloudstorage',
                'updateHistoricalStats',
                [
                    'userId' => $userId,
                    'username' => $username,
                    'method' => 'summary_tables',
                    'data' => [
                        'storage' => $totalStorage,
                        'bytes_sent' => $dailyBytesSent,
                        'bytes_received' => $dailyBytesReceived,
                        'operations' => $dailyOperations
                    ]
                ],
                'Using existing summary table data (billing system has run)'
            );
        } else {
            // Fallback: Get fresh data from Ceph RGW and calculate increments
            
            // Build UID parameter (handle tenant users)
            $uid = $username;
            if (!empty($user->tenant_id)) {
                $uid = $user->tenant_id . '$' . $username;
            }

            // Get current storage usage from Ceph RGW
            $totalStorage = 0;
            $bucketParams = [
                'uid' => $uid,
                'stats' => true
            ];
            
            $bucketStatsResponse = AdminOps::getBucketInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $bucketParams);
            
            if ($bucketStatsResponse['status'] == 'success' && isset($bucketStatsResponse['data'])) {
                foreach ($bucketStatsResponse['data'] as $bucket) {
                    if (isset($bucket['usage']['rgw.main']['size'])) {
                        $totalStorage += $bucket['usage']['rgw.main']['size'];
                    }
                }
            }

            // Get cumulative transfer totals from Ceph RGW
            $currentBytesSent = 0;
            $currentBytesReceived = 0;
            $currentOperations = 0;

            $transferParams = [
                'uid' => $uid,
                'show_entries' => true
            ];

            $transferResponse = AdminOps::getUsage($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $transferParams);
            
            if ($transferResponse['status'] == 'success' && isset($transferResponse['data']['entries'])) {
                foreach ($transferResponse['data']['entries'] as $entry) {
                    if (isset($entry['buckets'])) {
                        foreach ($entry['buckets'] as $bucketData) {
                            if (isset($bucketData['categories'])) {
                                foreach ($bucketData['categories'] as $category) {
                                    $currentBytesSent += $category['bytes_sent'] ?? 0;
                                    $currentBytesReceived += $category['bytes_received'] ?? 0;
                                    $currentOperations += $category['ops'] ?? 0;
                                }
                            }
                        }
                    }
                }
            }

            // Get yesterday's cumulative totals from historical stats
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $yesterdayRecord = Capsule::table('s3_historical_stats')
                ->where('user_id', $userId)
                ->where('date', '<=', $yesterday)
                ->orderBy('date', 'desc')
                ->first();

            // Calculate daily increments
            if ($yesterdayRecord) {
                // Get yesterday's cumulative totals from transfer_stats (find the last record before today)
                $lastTransferStats = Capsule::table('s3_transfer_stats')
                    ->where('user_id', $userId)
                    ->whereDate('created_at', '<', $today)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($lastTransferStats) {
                    $dailyBytesSent = max(0, $currentBytesSent - $lastTransferStats->bytes_sent);
                    $dailyBytesReceived = max(0, $currentBytesReceived - $lastTransferStats->bytes_received);
                    $dailyOperations = max(0, $currentOperations - $lastTransferStats->ops);
                } else {
                    // No previous transfer stats, use current totals
                    $dailyBytesSent = $currentBytesSent;
                    $dailyBytesReceived = $currentBytesReceived;
                    $dailyOperations = $currentOperations;
                }
            } else {
                // No previous historical record, use current totals as daily increments
                $dailyBytesSent = $currentBytesSent;
                $dailyBytesReceived = $currentBytesReceived;
                $dailyOperations = $currentOperations;
            }

            logModuleCall(
                'cloudstorage',
                'updateHistoricalStats',
                [
                    'userId' => $userId,
                    'username' => $username,
                    'uid' => $uid,
                    'method' => 'ceph_rgw_calculation',
                    'current_totals' => [
                        'bytes_sent' => $currentBytesSent,
                        'bytes_received' => $currentBytesReceived,
                        'operations' => $currentOperations
                    ],
                    'daily_increments' => [
                        'bytes_sent' => $dailyBytesSent,
                        'bytes_received' => $dailyBytesReceived,
                        'operations' => $dailyOperations
                    ],
                    'total_storage' => $totalStorage
                ],
                'Calculated increments from Ceph RGW (no recent summary data available)'
            );
        }

        // Prepare data for insertion/update
        $data = [
            'user_id' => $userId,
            'date' => $today,
            'total_storage' => $totalStorage,
            'bytes_sent' => $dailyBytesSent,
            'bytes_received' => $dailyBytesReceived,
            'operations' => $dailyOperations,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Insert or update the historical record
        Capsule::table('s3_historical_stats')
            ->updateOrInsert(
                ['user_id' => $userId, 'date' => $today],
                $data
            );
    }

    /**
     * Get historical stats for all users
     * This should be called daily via a cron job
     *
     * @return void
     */
    public function updateAllHistoricalStats()
    {
        $users = Capsule::table('s3_users')
            ->select('id')
            ->get();

        foreach ($users as $user) {
            $this->updateHistoricalStats($user->id);
        }
    }

    /**
     * Validates the bucket info response from the admin API to ensure it contains meaningful data.
     *
     * @param array $bucketInfo The bucket info response from AdminOps::getBucketInfo.
     * @param string $bucketName The name of the bucket being created.
     * @return bool True if the bucket info is valid, false otherwise.
     */
    private function isValidBucketInfo($bucketInfo, $bucketName)
    {
        // Basic checks for existence and structure
        if (!is_array($bucketInfo) || empty($bucketInfo)) {
            return false;
        }

        // Check for the essential 'id' field - this is critical for bucket identification
        if (!isset($bucketInfo['id']) || empty($bucketInfo['id'])) {
            return false;
        }

        // Check for 'creation_time' field - this should be present for a valid bucket
        if (!isset($bucketInfo['creation_time']) || empty($bucketInfo['creation_time'])) {
            return false;
        }

        // Check if usage data key exists (can be empty for new buckets)
        if (!isset($bucketInfo['usage'])) {
            return false;
        }

        // Check if bucket_quota exists and has proper structure
        if (!isset($bucketInfo['bucket_quota']) || !is_array($bucketInfo['bucket_quota'])) {
            return false;
        }

        // Check if owner exists (important for access control)
        if (!isset($bucketInfo['owner']) || empty($bucketInfo['owner'])) {
            return false;
        }

        return true;
    }

}