<?php


namespace WHMCS\Module\Addon\CloudStorage\Client;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use DateTime;
// NOTE: Previously used for async deletes; deletion is now streamed/bounded.
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
    private $region = 'ca-central-1';

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
    public function __construct($endpoint = null, $adminUser = null, $adminAccessKey = null, $adminSecretKey = null, $region = null)
    {
        $this->endpoint = $endpoint;
        $this->adminUser = $adminUser;
        $this->adminAccessKey = $adminAccessKey;
        $this->adminSecretKey = $adminSecretKey;
        if (!empty($region)) {
            $this->region = $region;
        }
    }

    /**
     * Find peak billable usage using instantaneous billing snapshots (s3_prices).
     * Prefers usage_bytes when that column exists; otherwise converts amount → bytes.
     *
     * @param int   $ownerUserId   s3_users.id of the owner (aggregated across tenants at billing time)
     * @param array $period        ['start' => Y-m-d, 'end' => Y-m-d]
     * @return object              { exact_timestamp, total_size(bytes) }
     */
    public function findPeakBillableUsageFromPrices(int $ownerUserId, array $period)
    {
        $hasUsageBytes = $this->tableHasColumn('s3_prices', 'usage_bytes');

        $query = Capsule::table('s3_prices')
            ->where('user_id', $ownerUserId)
            ->whereDate('created_at', '>=', $period['start'])
            ->whereDate('created_at', '<=', $period['end']);

        if ($hasUsageBytes) {
            $query = $query->orderBy('usage_bytes', 'DESC')->orderBy('amount', 'DESC');
        } else {
            $query = $query->orderBy('amount', 'DESC');
        }

        $row = $query->first();
        if (!$row) {
            return (object)[
                'exact_timestamp' => null,
                'total_size' => 0
            ];
        }

        $bytes = $hasUsageBytes && isset($row->usage_bytes) && $row->usage_bytes !== null
            ? (int)$row->usage_bytes
            : $this->amountToBytes((float)$row->amount);

        return (object)[
            'exact_timestamp' => $row->created_at,
            'total_size' => $bytes
        ];
    }

    /**
     * Build a day-by-day billable usage series from s3_prices for charts.
     * Uses MAX(usage_bytes) per day when present; otherwise MAX(amount) → bytes.
     *
     * @param int    $ownerUserId
     * @param string $start  Y-m-d
     * @param string $end    Y-m-d
     * @return array         [ ['period' => 'Y-m-d', 'total_usage' => bytes], ... ]
     */
    public function getDailyBillableUsageFromPrices(int $ownerUserId, string $start, string $end): array
    {
        $hasUsageBytes = $this->tableHasColumn('s3_prices', 'usage_bytes');

        if ($hasUsageBytes) {
            $rows = Capsule::table('s3_prices')
                ->selectRaw('DATE(created_at) AS day, MAX(usage_bytes) AS max_usage_bytes, MAX(amount) AS max_amount')
                ->where('user_id', $ownerUserId)
                ->whereDate('created_at', '>=', $start)
                ->whereDate('created_at', '<=', $end)
                ->groupBy('day')
                ->orderBy('day', 'ASC')
                ->get();
        } else {
            $rows = Capsule::table('s3_prices')
                ->selectRaw('DATE(created_at) AS day, MAX(amount) AS max_amount')
                ->where('user_id', $ownerUserId)
                ->whereDate('created_at', '>=', $start)
                ->whereDate('created_at', '<=', $end)
                ->groupBy('day')
                ->orderBy('day', 'ASC')
                ->get();
        }

        $series = [];
        foreach ($rows as $r) {
            $bytes = 0;
            if ($hasUsageBytes && isset($r->max_usage_bytes) && $r->max_usage_bytes !== null) {
                $bytes = (int)$r->max_usage_bytes;
            } else {
                $amount = isset($r->max_amount) ? (float)$r->max_amount : 0.0;
                $bytes = $this->amountToBytes($amount);
            }
            $series[] = [
                'period' => $r->day,
                'total_usage' => $bytes
            ];
        }

        return $series;
    }

    /**
     * Convert billing amount → instantaneous usage in bytes.
     * Pricing: $9 covers first 1 TiB; then $0.009765 per additional GiB.
     *
     * @param float $amount
     * @return int  bytes
     */
    private function amountToBytes(float $amount): int
    {
        if ($amount <= 9.0) {
            $usageGiB = 1024.0;
        } else {
            $usageGiB = 1024.0 + (($amount - 9.0) / 0.009765);
        }
        return (int)round($usageGiB * 1024 * 1024 * 1024);
    }

    /**
     * Lightweight INFORMATION_SCHEMA check with per-process static cache.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    private function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = strtolower($table) . '.' . strtolower($column);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $databaseName = Capsule::connection()->getDatabaseName();
            $exists = Capsule::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $databaseName)
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->exists();
            $cache[$key] = $exists;
            return $exists;
        } catch (\Exception $e) {
            // Fail safe if INFORMATION_SCHEMA is not accessible
            $cache[$key] = false;
            return false;
        }
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

		// Require an initialized S3 client (matches production behavior)
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

                // Provide LocationConstraint only for AWS endpoints and non-us-east-1 regions.
                // Many S3-compatible servers (e.g., Ceph RGW/MinIO) reject LocationConstraint.
                $endpointHost = '';
                try {
                    $endpointHost = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: '';
                } catch (\Throwable $e) {
                    $endpointHost = '';
                }
                $isAwsEndpoint = is_string($endpointHost) && stripos($endpointHost, 'amazonaws.com') !== false;
                if ($isAwsEndpoint && !empty($this->region) && strtolower($this->region) !== 'us-east-1') {
                    $bucketOptions['CreateBucketConfiguration'] = [
                        'LocationConstraint' => $this->region,
                    ];
                }

				// Create bucket via tenant S3 credentials (production behavior)
				try {
					$result = $this->s3Client->createBucket($bucketOptions);
					logModuleCall($this->module, __FUNCTION__ . '_BUCKET_CREATED', [
						'bucket_name' => $bucketName,
						'object_lock_enabled' => $enableObjectLocking
					], 'Bucket created successfully on storage');
				} catch (S3Exception $e) {
					logModuleCall($this->module, __FUNCTION__ . '_CREATE_FAILED', [
						'bucket_name' => $bucketName,
						'params' => $bucketOptions,
						'aws_error_code' => $e->getAwsErrorCode(),
						'aws_error_message' => $e->getAwsErrorMessage()
					], 'Bucket creation failed: ' . $e->getMessage());

					return ['status' => 'fail', 'params' => $bucketOptions, 'message' => 'Bucket creation failed. Please try again later.'];
				}

                // Wait until headBucket succeeds before making any follow-up calls (versioning/object lock)
				if (!is_null($this->s3Client)) {
					$maxHeadTries = 10;
					$delaySeconds = 0.1; // start with 100ms
					for ($i = 0; $i < $maxHeadTries; $i++) {
						try {
							$this->s3Client->headBucket(['Bucket' => $bucketName]);
							break; // ready
						} catch (S3Exception $e) {
							// Exponential backoff up to ~2s
							usleep((int)($delaySeconds * 1_000_000));
							$delaySeconds = min($delaySeconds * 2, 2.0);
							if ($i === $maxHeadTries - 1) {
								logModuleCall($this->module, __FUNCTION__ . '_HEAD_TIMEOUT', [
									'bucket_name' => $bucketName,
									'tries' => $maxHeadTries
								], 'headBucket did not succeed in time after creation');
							}
						}
					}
				}

                // If object locking is enabled, enforce versioning being enabled server-side as a safety net
                if ($enableObjectLocking && !$enableVersioning) {
                    $enableVersioning = true;
                    logModuleCall($this->module, __FUNCTION__ . '_FORCE_VERSIONING', [
                        'bucket_name' => $bucketName
                    ], 'Forcing versioning ON because object locking was requested');
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
						// Fallback: attempt enabling versioning with admin S3 credentials
						try {
							$adminS3 = new S3Client([
								'version' => 'latest',
								'region' => $this->region,
								'endpoint' => $this->endpoint,
								'credentials' => [
									'key' => $this->adminAccessKey,
									'secret' => $this->adminSecretKey,
								],
								'use_path_style_endpoint' => true,
								'signature_version' => 'v4'
							]);
							$adminS3->putBucketVersioning([
								'Bucket' => $bucketName,
								'VersioningConfiguration' => [
									'Status' => 'Enabled'
								],
							]);
							logModuleCall($this->module, __FUNCTION__ . '_VERSIONING_ENABLED_ADMIN', [
								'bucket_name' => $bucketName
							], 'Versioning enabled via admin S3 fallback');
						} catch (S3Exception $e2) {
							logModuleCall($this->module, __FUNCTION__ . '_VERSIONING_FAILED_ADMIN', [
								'bucket_name' => $bucketName,
								'aws_error_code' => $e2->getAwsErrorCode()
							], 'Admin S3 versioning enable failed: ' . $e2->getMessage());
							// Do not fail the entire operation if versioning cannot be enabled; continue without versioning
							$enableVersioning = false;
						}
                    }
                }

                // Only set default retention policy if both object locking is enabled AND user requested it
                if ($enableObjectLocking && $setDefaultRetention) {
                    // Small delay to allow bucket.instance/object lock scaffolding to settle on older RGW
                    usleep(500_000); // 500ms
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
                $maxRetries = 10;
                $bucketInfo = null;
                
                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    $bucketInfoResponse = AdminOps::getBucketInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $params);

                    if ($bucketInfoResponse['status'] != 'success') {
                        if ($attempt < $maxRetries) {
                            // exponential-ish backoff capped at 2s
                            usleep(min($attempt * 200_000, 2_000_000));
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
                            // progressive delay: 0.2s, 0.4s, ..., up to 2s
                            usleep(min($attempt * 200_000, 2_000_000));
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
     * Create bucket using admin credentials (control plane).
     *
     * Option B support: bucket creation must work even when the user has not generated keys yet.
     * Strategy:
     *  - Create bucket using admin S3 credentials.
     *  - Link bucket ownership to target user via Admin Ops.
     *  - Apply versioning / object lock config via admin S3.
     *  - Verify via Admin Ops and write DB record.
     *
     * @param object $user s3_users row (must include id, username, tenant_id)
     */
    public function createBucketAsAdmin($user, $bucketName, $enableVersioning = false, $enableObjectLocking = false, $retentionMode = 'GOVERNANCE', $retentionDays = 1, $setDefaultRetention = false)
    {
        logModuleCall($this->module, __FUNCTION__ . '_START', [
            'user_id' => $user->id ?? null,
            'username' => $user->username ?? null,
            'tenant_id' => $user->tenant_id ?? null,
            'bucket_name' => $bucketName,
            'enable_versioning' => $enableVersioning,
            'enable_object_locking' => $enableObjectLocking,
            'set_default_retention' => $setDefaultRetention,
        ], 'Starting admin bucket creation');

        if (in_array($bucketName, $this->protectedBucketNames)) {
            return ['status' => 'fail', 'message' => "The bucket name is invalid and cannot be used."];
        }

        $bucket = DBController::getRow('s3_buckets', [
            ['name', '=', $bucketName]
        ]);
        if (!is_null($bucket)) {
            return ['status' => 'fail', 'message' => "Bucket name unavailable: Bucket names must be unique globally. Please choose a unique name for your bucket to proceed."];
        }

        try {
            // IMPORTANT: RGW Admin Ops /admin/bucket?op=link is not supported on some deployments and can 404.
            // To ensure correct ownership (and keep Option B: no persisted user keys), we:
            //  - create a temporary user key via AdminOps
            //  - create the bucket using that temporary user key (bucket owner becomes the user)
            //  - delete the temporary key immediately

            // RGW uid: prefer s3_users.ceph_uid for new users; fall back to email username for legacy.
            $targetUsername = (string)\WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($user);
            $tenantId = (string)($user->tenant_id ?? '');
            $cephUid = ($tenantId !== '') ? ($tenantId . '$' . $targetUsername) : $targetUsername;
            if ($cephUid === '' || $targetUsername === '') {
                return ['status' => 'fail', 'message' => 'Unable to resolve storage username for bucket ownership.'];
            }

            // Create a short-lived owner key (Option B) safely.
            // SAFETY: Always create/revoke temp keys using a tenant-qualified uid (tenant$uid) WITHOUT tenant param.
            $tempAccessKey = '';
            $tempSecretKey = '';
            $tmp = AdminOps::createTempKey($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $cephUid, null);
            if (!is_array($tmp) || ($tmp['status'] ?? '') !== 'success') {
                logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_CREATE_FAILED', ['uid' => $cephUid], $tmp);
                return ['status' => 'fail', 'message' => 'Unable to create temporary access key for bucket creation. Please contact support.'];
            }
            $tempAccessKey = (string)($tmp['access_key'] ?? '');
            $tempSecretKey = (string)($tmp['secret_key'] ?? '');
            if ($tempAccessKey === '' || $tempSecretKey === '') {
                logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_PARSE_FAILED', ['uid' => $cephUid], $tmp);
                return ['status' => 'fail', 'message' => 'Unable to use temporary access key for bucket creation. Please contact support.'];
            }

            // Build user-scoped S3 client (bucket owner = user)
            $effectiveRegion = $this->region;
            try {
                $host = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: '';
                $isAws = is_string($host) && stripos($host, 'amazonaws.com') !== false;
                if (!$isAws) {
                    $effectiveRegion = 'us-east-1';
                }
            } catch (\Throwable $e) {
                // keep configured region
            }

            $userS3 = new S3Client([
                'version' => 'latest',
                'region' => $effectiveRegion,
                'endpoint' => $this->endpoint,
                'credentials' => [
                    'key' => $tempAccessKey,
                    'secret' => $tempSecretKey,
                ],
                'use_path_style_endpoint' => true,
                'signature_version' => 'v4',
                'http' => [
                    'connect_timeout' => 10.0,
                    'timeout' => 30.0,
                    'read_timeout' => 30.0,
                ],
            ]);

            try {
                // Ensure bucket doesn't already exist
                try {
                    $userS3->headBucket(['Bucket' => $bucketName]);
                    return ['status' => 'fail', 'message' => 'Bucket name unavailable: Bucket names must be unique globally. Please choose a unique name for your bucket to proceed.'];
                } catch (S3Exception $e) {
                    // expected when not found
                }

                $bucketOptions = ['Bucket' => $bucketName];
                if ($enableObjectLocking) {
                    $bucketOptions['ObjectLockEnabledForBucket'] = true;
                }
                // LocationConstraint only for AWS endpoints and non-us-east-1
                $endpointHost = '';
                try { $endpointHost = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: ''; } catch (\Throwable $e) { $endpointHost = ''; }
                $isAwsEndpoint = is_string($endpointHost) && stripos($endpointHost, 'amazonaws.com') !== false;
                if ($isAwsEndpoint && !empty($this->region) && strtolower($this->region) !== 'us-east-1') {
                    $bucketOptions['CreateBucketConfiguration'] = [
                        'LocationConstraint' => $this->region,
                    ];
                }

                $userS3->createBucket($bucketOptions);
                logModuleCall($this->module, __FUNCTION__ . '_BUCKET_CREATED', [
                    'bucket_name' => $bucketName,
                    'object_lock_enabled' => $enableObjectLocking,
                    'uid' => $cephUid,
                ], 'Bucket created via temporary user key');

                // Wait until bucket exists
                $maxHeadTries = 10;
                $delaySeconds = 0.1;
                for ($i = 0; $i < $maxHeadTries; $i++) {
                    try {
                        $userS3->headBucket(['Bucket' => $bucketName]);
                        break;
                    } catch (S3Exception $e) {
                        usleep((int)($delaySeconds * 1_000_000));
                        $delaySeconds = min($delaySeconds * 2, 2.0);
                    }
                }

                // If object locking is enabled, enforce versioning as safety net
                if ($enableObjectLocking && !$enableVersioning) {
                    $enableVersioning = true;
                }
                if ($enableVersioning) {
                    try {
                        $userS3->putBucketVersioning([
                            'Bucket' => $bucketName,
                            'VersioningConfiguration' => [ 'Status' => 'Enabled' ],
                        ]);
                    } catch (S3Exception $e) {
                        logModuleCall($this->module, __FUNCTION__ . '_VERSIONING_FAILED_USER', [
                            'bucket_name' => $bucketName,
                            'uid' => $cephUid,
                            'aws_error_code' => $e->getAwsErrorCode(),
                        ], 'Versioning enable failed via temporary user key: ' . $e->getMessage());
                        $enableVersioning = false;
                    }
                }

                if ($enableObjectLocking && $setDefaultRetention) {
                    usleep(500_000);
                    try {
                        $userS3->putObjectLockConfiguration([
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
                    } catch (S3Exception $e) {
                        logModuleCall($this->module, __FUNCTION__ . '_RETENTION_FAILED_USER', [
                            'bucket_name' => $bucketName,
                            'uid' => $cephUid,
                            'aws_error_code' => $e->getAwsErrorCode(),
                        ], 'Object lock configuration failed via temporary user key: ' . $e->getMessage());
                        return ['status' => 'fail', 'message' => 'Failed to configure object lock.'];
                    }
                }
            } finally {
                // Always try to delete temporary key (Option B: no persisted keys)
                try {
                    $rm = AdminOps::removeKey($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $tempAccessKey, $cephUid, null);
                    logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_REMOVED', [
                        'uid' => $cephUid,
                        'access_key_hint' => substr($tempAccessKey, 0, 4) . '…' . substr($tempAccessKey, -4),
                    ], $rm);
                } catch (\Throwable $e) {
                    logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_REMOVE_EXCEPTION', ['uid' => $cephUid], $e->getMessage());
                }
            }

            // If object locking is enabled, enforce versioning as safety net
            if ($enableObjectLocking && !$enableVersioning) {
                $enableVersioning = true;
            }

            // Verify using Admin Ops and persist to DB
            $params = [
                'bucket' => $bucketName,
                'stats' => true
            ];
            if (!empty($tenantId)) {
                $params['bucket'] = $tenantId . '/' . $bucketName;
            }
            $maxRetries = 10;
            $bucketInfo = null;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $bucketInfoResponse = AdminOps::getBucketInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $params);
                if (($bucketInfoResponse['status'] ?? '') !== 'success') {
                    if ($attempt < $maxRetries) {
                        usleep(min($attempt * 200_000, 2_000_000));
                        continue;
                    }
                    break;
                }
                if ($this->isValidBucketInfo($bucketInfoResponse['data'], $bucketName)) {
                    $bucketInfo = $bucketInfoResponse['data'];
                    break;
                }
                usleep(min($attempt * 200_000, 2_000_000));
            }
            if (is_null($bucketInfo)) {
                return [
                    'status' => 'fail',
                    'message' => 'Bucket creation could not be verified. The bucket may still be propagating through the storage cluster. Please wait a moment and refresh the page.'
                ];
            }

            $creationDateTime = new DateTime($bucketInfo['creation_time']);
            $creationTime = $creationDateTime->format('Y-m-d H:i:s');
            $dbData = [
                'user_id'             => (int)($user->id ?? 0),
                'name'                => $bucketName,
                's3_id'               => $bucketInfo['id'],
                'versioning'          => $enableVersioning ? 'enabled' : 'off',
                'object_lock_enabled' => $enableObjectLocking ? '1' : '0',
                'is_active'           => 1,
                'created_at'          => $creationTime
            ];
            DBController::saveBucket($dbData);

            return ['status' => 'success', 'message' => 'Bucket has been created successfully.'];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__ . '_GENERAL_EXCEPTION', [
                'bucket_name' => $bucketName,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_message' => $e->getAwsErrorMessage()
            ], 'General exception during admin bucket creation: ' . $e->getMessage());
            return ['status' => 'fail', 'message' => 'Bucket creation failed. Please try again later.'];
        }
    }

    /**
     * Set lifecycle to expire noncurrent versions after N days.
     *
     * @param string $bucketName
     * @param int $days
     * @return array
     */
    public function setVersioningRetentionDays($bucketName, $days)
    {
        if (is_null($this->s3Client)) {
            return ['status' => 'fail', 'message' => 'Storage connection not established.'];
        }
        $days = max(1, (int)$days);
        try {
            $this->s3Client->putBucketLifecycleConfiguration([
                'Bucket' => $bucketName,
                'LifecycleConfiguration' => [
                    'Rules' => [[
                        'ID' => 'expire-noncurrent-versions',
                        'Status' => 'Enabled',
                        'NoncurrentVersionExpiration' => [
                            'NoncurrentDays' => $days
                        ],
                    ]]
                ]
            ]);
            return ['status' => 'success', 'message' => 'Lifecycle rule applied.'];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $days], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to apply lifecycle rule.'];
        }
    }

    /**
     * Upsert a lifecycle rule scoped to a prefix to expire current and noncurrent versions after N days.
     * Safe to call multiple times; replaces prior rule with same ID.
     *
     * @param string $bucketName
     * @param string $ruleId
     * @param string $prefix
     * @param int $days
     * @return array
     */
    public function upsertLifecycleRuleForPrefix($bucketName, $ruleId, $prefix, $days)
    {
        if (is_null($this->s3Client)) {
            return ['status' => 'fail', 'message' => 'Storage connection not established.'];
        }
        $days = max(1, (int)$days);
        $prefix = trim((string)$prefix);
        if ($prefix !== '' && substr($prefix, -1) !== '/') {
            $prefix .= '/';
        }
        try {
            // Ensure versioning enabled to honor noncurrent expirations; ignore errors
            try {
                $this->s3Client->putBucketVersioning([
                    'Bucket' => $bucketName,
                    'VersioningConfiguration' => [ 'Status' => 'Enabled' ],
                ]);
            } catch (S3Exception $ignored) {}

            // Load existing lifecycle rules (if any)
            $rules = [];
            try {
                $existing = $this->s3Client->getBucketLifecycleConfiguration(['Bucket' => $bucketName]);
                $rules = $existing['Rules'] ?? [];
            } catch (S3Exception $e) {
                // treat as none configured
                $rules = [];
            }

            // Remove any rule with same ID
            $rules = array_values(array_filter($rules, function($r) use ($ruleId) {
                return ($r['ID'] ?? '') !== $ruleId;
            }));

            // Build new rule (use Prefix filter when provided; otherwise skip Filter to avoid whole-bucket)
            $newRule = [
                'ID' => $ruleId,
                'Status' => 'Enabled',
                // Preserve the live/current object indefinitely. Only expire historical versions.
                'NoncurrentVersionExpiration' => [ 'NoncurrentDays' => $days ],
                'AbortIncompleteMultipartUpload' => [ 'DaysAfterInitiation' => 7 ],
            ];
            // Clean up delete markers when no versions remain (helps avoid tombstones accumulation)
            $newRule['Expiration'] = [ 'ExpiredObjectDeleteMarker' => true ];
            if ($prefix !== '') {
                $newRule['Filter'] = [ 'Prefix' => $prefix ];
            }

            $rules[] = $newRule;

            $this->s3Client->putBucketLifecycleConfiguration([
                'Bucket' => $bucketName,
                'LifecycleConfiguration' => [ 'Rules' => $rules ],
            ]);
            return ['status' => 'success', 'message' => 'Lifecycle rule updated'];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, compact('bucketName','ruleId','prefix','days'), $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to update lifecycle rule'];
        }
    }

    /**
     * Remove a lifecycle rule by ID if present.
     *
     * @param string $bucketName
     * @param string $ruleId
     * @return array
     */
    public function removeLifecycleRuleById($bucketName, $ruleId)
    {
        if (is_null($this->s3Client)) {
            return ['status' => 'fail', 'message' => 'Storage connection not established.'];
        }
        try {
            $existing = $this->s3Client->getBucketLifecycleConfiguration(['Bucket' => $bucketName]);
            $rules = $existing['Rules'] ?? [];
            $newRules = array_values(array_filter($rules, function($r) use ($ruleId) {
                return ($r['ID'] ?? '') !== $ruleId;
            }));
            // If nothing changed, succeed
            if (count($newRules) === count($rules)) {
                return ['status' => 'success', 'message' => 'No lifecycle change'];
            }
            if (empty($newRules)) {
                // Remove entire lifecycle config
                $this->s3Client->deleteBucketLifecycle(['Bucket' => $bucketName]);
                return ['status' => 'success', 'message' => 'Lifecycle configuration removed'];
            }
            $this->s3Client->putBucketLifecycleConfiguration([
                'Bucket' => $bucketName,
                'LifecycleConfiguration' => [ 'Rules' => $newRules ],
            ]);
            return ['status' => 'success', 'message' => 'Lifecycle rule removed'];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, compact('bucketName','ruleId'), $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to remove lifecycle rule'];
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
        // Support tenant$uid format (Ceph RGW).
        // IMPORTANT: Different RGW builds accept either:
        // - uid=<uid>&tenant=<tenant>
        // - uid=<tenant>$<uid> (without tenant param)
        // We resolve the correct identity by probing getUserInfo first and then reusing the working form.
        $rawUsername = (string)$username;
        $identityCandidates = [];
        if (is_string($username) && strpos($username, '$') !== false) {
            // Prefer full uid first (most consistent with our RGW logs / temp-key flows)
            $identityCandidates[] = ['uid' => $rawUsername, 'tenant' => null, 'label' => 'full'];
            $parts = explode('$', $rawUsername, 2);
            if (count($parts) === 2) {
                $t = $parts[0] !== '' ? $parts[0] : null;
                $u = $parts[1];
                $identityCandidates[] = ['uid' => $u, 'tenant' => $t, 'label' => 'split'];
            }
        } else {
            $identityCandidates[] = ['uid' => $rawUsername, 'tenant' => null, 'label' => 'plain'];
        }

        $chosen = null;
        $beforeInfo = null;
        foreach ($identityCandidates as $cand) {
            try {
                $info = AdminOps::getUserInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $cand['uid'], $cand['tenant']);
                if (is_array($info) && ($info['status'] ?? '') === 'success') {
                    $chosen = $cand;
                    $beforeInfo = $info;
                    break;
                }
            } catch (\Throwable $e) {}
        }
        if ($chosen === null) {
            // Fall back to the first candidate; createKey may still succeed even if getUserInfo is blocked/misconfigured.
            $chosen = $identityCandidates[0];
        }

        // Capture existing keys so we can reliably pick the newly-created one (RGW may return all keys).
        $beforeKeys = [];
        try {
            $info = $beforeInfo;
            if (!$info) {
                $info = AdminOps::getUserInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $chosen['uid'], $chosen['tenant']);
            }
            if (is_array($info) && ($info['status'] ?? '') === 'success') {
                $data = $info['data'] ?? null;
                if (is_array($data) && isset($data['keys']) && is_array($data['keys'])) {
                    foreach ($data['keys'] as $k) {
                        if (is_array($k) && !empty($k['access_key'])) {
                            $beforeKeys[(string)$k['access_key']] = true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        $userAccessKey = DBController::getRow('s3_user_access_keys', [
            ['user_id', '=', $userId]
        ], ['access_key', 'secret_key']);
        if (!is_null($userAccessKey)) {
            // Best-effort: attempt to revoke previous key if it can be decrypted.
            // Do NOT block key rotation if the previous key is already invalid/revoked.
            $accessKey = HelperController::decryptKey($userAccessKey->access_key, $encryptionKey);
            if (!empty($accessKey)) {
                $rm = AdminOps::removeKey($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $accessKey, $chosen['uid'], $chosen['tenant']);
                if (($rm['status'] ?? '') !== 'success') {
                    // Log and proceed
                    logModuleCall($this->module, __FUNCTION__ . '_REMOVE_OLDKEY_FAILED', ['user_id' => $userId, 'uid' => $username], $rm);
                }
            }
        }

        $result = AdminOps::createKey($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $chosen['uid'], $chosen['tenant']);
        if ($result['status'] != 'success') {
            return $result;
        }
        $raw = $result['data'] ?? [];
        $records = [];
        if (is_array($raw) && isset($raw['keys']) && is_array($raw['keys'])) {
            $records = $raw['keys'];
        } elseif (is_array($raw)) {
            $records = $raw;
        }

        $accessKey = '';
        $secretKey = '';
        $newAccessKey = '';
        if (is_array($records) && count($records) > 0) {
            // Prefer the truly new key (diff) that also includes a secret_key (RGW typically only returns secret for a fresh key).
            $preferred = null;
            foreach ($records as $r) {
                if (!is_array($r)) { continue; }
                $ak = (string)($r['access_key'] ?? '');
                $sk = (string)($r['secret_key'] ?? '');
                if ($ak !== '' && !isset($beforeKeys[$ak])) {
                    $newAccessKey = $ak;
                    if ($sk !== '') {
                        $preferred = $r;
                    }
                }
            }
            if ($preferred !== null) {
                $accessKey = (string)($preferred['access_key'] ?? '');
                $secretKey = (string)($preferred['secret_key'] ?? '');
            } elseif ($newAccessKey === '') {
                $last = end($records);
                if (is_array($last)) {
                    $newAccessKey = (string)($last['access_key'] ?? '');
                }
                reset($records);
            }
            if ($accessKey === '' || $secretKey === '') {
                foreach ($records as $r) {
                    if (!is_array($r)) { continue; }
                    if ((string)($r['access_key'] ?? '') === $newAccessKey) {
                        $accessKey = (string)($r['access_key'] ?? '');
                        $secretKey = (string)($r['secret_key'] ?? '');
                        break;
                    }
                }
            }
        }

        // Last-resort: re-fetch user info and diff again
        if ($accessKey === '' || $secretKey === '') {
            try {
                $after = AdminOps::getUserInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $chosen['uid'], $chosen['tenant']);
                $data = is_array($after) ? ($after['data'] ?? null) : null;
                if (is_array($data) && isset($data['keys']) && is_array($data['keys'])) {
                    foreach ($data['keys'] as $k) {
                        if (!is_array($k)) { continue; }
                        $ak = (string)($k['access_key'] ?? '');
                        $sk = (string)($k['secret_key'] ?? '');
                        if ($ak !== '' && !isset($beforeKeys[$ak]) && $sk !== '') {
                            $accessKey = $ak;
                            $secretKey = $sk;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        if ($accessKey === '' || $secretKey === '') {
            return ['status' => 'fail', 'message' => 'Create key failed. Please try again or contact support.'];
        }

        // Verify the newly created key actually exists on RGW for this user before storing/returning it.
        // This prevents us from returning a keypair that doesn't match RGW due to identity/response quirks.
        $verifiedExists = false;
        try {
            $after = AdminOps::getUserInfo($this->endpoint, $this->adminAccessKey, $this->adminSecretKey, $chosen['uid'], $chosen['tenant']);
            $data = is_array($after) ? ($after['data'] ?? null) : null;
            if (is_array($data) && isset($data['keys']) && is_array($data['keys'])) {
                foreach ($data['keys'] as $k) {
                    if (!is_array($k)) { continue; }
                    if ((string)($k['access_key'] ?? '') === $accessKey) {
                        $verifiedExists = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {}
        if (!$verifiedExists) {
            $hintFail = (strlen($accessKey) <= 8) ? $accessKey : (substr($accessKey, 0, 4) . '…' . substr($accessKey, -4));
            logModuleCall($this->module, __FUNCTION__ . '_VERIFY_FAILED', ['user_id' => $userId, 'uid' => $rawUsername, 'identity' => $chosen['label'] ?? '', 'access_key_hint' => $hintFail], 'Key returned by createKey was not found in getUserInfo after creation');
            return ['status' => 'fail', 'message' => 'Key creation could not be verified on storage. Please try again.'];
        }
        $hint = (strlen($accessKey) <= 8) ? $accessKey : (substr($accessKey, 0, 4) . '…' . substr($accessKey, -4));
        $accessKeyEncrypted = HelperController::encryptKey($accessKey, $encryptionKey);
        $secretKeyEncrypted = HelperController::encryptKey($secretKey, $encryptionKey);

        DBController::deleteRecord('s3_user_access_keys', [
            ['user_id', '=', $userId]
        ]);

        DBController::insertRecord('s3_user_access_keys', [
            'user_id' => $userId,
            'access_key' => $accessKeyEncrypted,
            'secret_key' => $secretKeyEncrypted,
            'access_key_hint' => $hint,
            'is_user_generated' => 1
        ]);


        return [
            'status' => 'success',
            'message' => 'Access keys updated successfully.',
            // One-time secrets returned for immediate display (do not store plaintext)
            'data' => [
                'access_key' => $accessKey,
                'secret_key' => $secretKey,
                'access_key_hint' => $hint
            ]
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

        [$currentBucketIds, $cutoverDate, $firstSeenSub] = $this->getTransferCutoverContext($userIds);
        $query = Capsule::table('s3_transfer_stats_summary AS t')
            ->selectRaw('
                SUM(t.bytes_sent) as total_bytes_sent,
                SUM(t.bytes_received) as total_bytes_received,
                SUM(t.ops) as total_ops
            ')
            ->whereIn('t.user_id', $userIds);
        $this->applyTransferCutoverFilters($query, $currentBucketIds, $cutoverDate, $firstSeenSub, $startDate, $endDate);

        $transferStatsSummaries = $query->groupBy('t.user_id')->get();

        if (count($transferStatsSummaries)) {
            foreach ($transferStatsSummaries as $transferStatsSummary) {
                $totalBytesSent     += $transferStatsSummary->total_bytes_sent;
                $totalBytesReceived += $transferStatsSummary->total_bytes_received;
                $totalOps           += $transferStatsSummary->total_ops;
            }
        }
        // #region agent log
        try {
            $currentBucketIds = Capsule::table('s3_buckets')
                ->whereIn('user_id', $userIds)
                ->where('is_active', 1)
                ->pluck('id')
                ->all();
            $cutoverDate = null;
            if (!empty($currentBucketIds)) {
                $cutoverDate = Capsule::table('s3_transfer_stats_summary')
                    ->whereIn('user_id', $userIds)
                    ->whereIn('bucket_id', $currentBucketIds)
                    ->selectRaw('MIN(DATE(created_at)) as cutover')
                    ->value('cutover');
            }
            $currentOnlyTotals = Capsule::table('s3_transfer_stats_summary')
                ->selectRaw('SUM(bytes_sent) as total_bytes_sent, SUM(bytes_received) as total_bytes_received, SUM(ops) as total_ops')
                ->whereIn('user_id', $userIds)
                ->when(!empty($startDate), function ($q) use ($startDate) {
                    $q->whereDate('created_at', '>=', $startDate);
                })
                ->when(!empty($endDate), function ($q) use ($endDate) {
                    $q->whereDate('created_at', '<=', $endDate);
                })
                ->when(!empty($currentBucketIds), function ($q) use ($currentBucketIds) {
                    $q->whereIn('bucket_id', $currentBucketIds);
                })
                ->first();
            file_put_contents(
                '/var/www/eazybackup.ca/.cursor/debug.log',
                json_encode([
                    'id' => uniqid('log_', true),
                    'timestamp' => (int)round(microtime(true) * 1000),
                    'location' => 'lib/Client/BucketController.php:getTotalUsageForBillingPeriod',
                    'message' => 'Transfer totals vs current buckets',
                    'data' => [
                        'userIds' => $userIds,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'cutoverDate' => $cutoverDate,
                        'currentBucketIdsCount' => is_array($currentBucketIds) ? count($currentBucketIds) : 0,
                        'totalBytesSent' => $totalBytesSent,
                        'totalBytesReceived' => $totalBytesReceived,
                        'totalOps' => $totalOps,
                        'currentOnlyBytesSent' => $currentOnlyTotals->total_bytes_sent ?? 0,
                        'currentOnlyBytesReceived' => $currentOnlyTotals->total_bytes_received ?? 0,
                        'currentOnlyOps' => $currentOnlyTotals->total_ops ?? 0
                    ],
                    'runId' => 'pre-fix-2',
                    'hypothesisId' => 'H5'
                ]) . PHP_EOL,
                FILE_APPEND
            );
        } catch (\Throwable $e) {}
        // #endregion

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

        $currentBucketIds = Capsule::table('s3_buckets')
            ->whereIn('user_id', $userIds)
            ->where('is_active', 1)
            ->pluck('id')
            ->all();

        $cutoverDate = null;
        if (!empty($currentBucketIds)) {
            $cutoverDate = Capsule::table('s3_bucket_stats_summary')
                ->whereIn('user_id', $userIds)
                ->whereIn('bucket_id', $currentBucketIds)
                ->selectRaw('MIN(DATE(created_at)) as cutover')
                ->value('cutover');
        }

        $bucketStatsSummary = collect();
        if (!empty($currentBucketIds) && !empty($cutoverDate)) {
            // Before cutover: use legacy bucket IDs (not in current list)
            $beforeRows = Capsule::table('s3_bucket_stats_summary AS s')
                ->selectRaw('
                    DATE(s.created_at) AS date,
                    SUM(
                        (
                            SELECT MAX(total_usage)
                            FROM s3_bucket_stats_summary AS inner_s
                            WHERE inner_s.user_id = s.user_id
                            AND inner_s.bucket_id = s.bucket_id
                            AND DATE(inner_s.created_at) = DATE(s.created_at)
                        )
                    ) AS total_usage
                ')
                ->whereIn('s.user_id', $userIds)
                ->whereDate('s.created_at', '>=', $startDate)
                ->whereDate('s.created_at', '<', $cutoverDate)
                ->whereNotIn('s.bucket_id', $currentBucketIds)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get();

            // After cutover: use current bucket IDs only
            $afterRows = Capsule::table('s3_bucket_stats_summary AS s')
                ->selectRaw('
                    DATE(s.created_at) AS date,
                    SUM(
                        (
                            SELECT MAX(total_usage)
                            FROM s3_bucket_stats_summary AS inner_s
                            WHERE inner_s.user_id = s.user_id
                            AND inner_s.bucket_id = s.bucket_id
                            AND DATE(inner_s.created_at) = DATE(s.created_at)
                        )
                    ) AS total_usage
                ')
                ->whereIn('s.user_id', $userIds)
                ->whereDate('s.created_at', '>=', $cutoverDate)
                ->whereDate('s.created_at', '<=', $endDate)
                ->whereIn('s.bucket_id', $currentBucketIds)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get();

            $bucketStatsSummary = $beforeRows->merge($afterRows);
        } else {
            // Fallback to original behavior if cutover cannot be determined
            $bucketStatsSummary = Capsule::table('s3_bucket_stats_summary AS s')
                ->selectRaw('
                    DATE(s.created_at) AS date,
                    SUM(
                        (
                            SELECT MAX(total_usage)
                            FROM s3_bucket_stats_summary AS inner_s
                            WHERE inner_s.user_id = s.user_id
                            AND inner_s.bucket_id = s.bucket_id
                            AND DATE(inner_s.created_at) = DATE(s.created_at)
                        )
                    ) AS total_usage
                ')
                ->whereIn('s.user_id', $userIds)
                ->whereDate('s.created_at', '>=', $startDate)
                ->whereDate('s.created_at', '<=', $endDate)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->limit($limit)
                ->get();
        }

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
            ->whereDate('created_at', '=', $today)
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
        [$currentBucketIds, $cutoverDate, $firstSeenSub] = $this->getTransferCutoverContext($userIds);

        if ($limit == 24 || $limit == 'day') {
            $transferQuery = Capsule::table('s3_transfer_stats_summary AS t')
                ->select(
                    't.created_at as usage_period',
                    Capsule::raw('SUM(t.bytes_sent) as total_bytes_sent'),
                    Capsule::raw('SUM(t.bytes_received) as total_bytes_received'),
                    Capsule::raw('SUM(t.ops) as total_ops'),
                    Capsule::raw('SUM(t.successful_ops) as total_successful_ops')
                )
                ->whereIn('t.user_id', $userIds)
                ->whereDate('t.created_at', date('Y-m-d'));
            $this->applyTransferCutoverFilters($transferQuery, $currentBucketIds, $cutoverDate, $firstSeenSub);
            $transferStatsSummary = $transferQuery
                ->groupBy('usage_period')
                ->orderBy('usage_period', 'ASC')
                ->get();
        } else {
            $periods = HelperController::getDateRange($limit);
            $transferQuery = Capsule::table('s3_transfer_stats_summary AS t')
                ->select(
                    Capsule::raw('DATE_FORMAT(t.created_at, "%Y-%m-%d") AS usage_period'),
                    Capsule::raw('SUM(t.bytes_sent) as total_bytes_sent'),
                    Capsule::raw('SUM(t.bytes_received) as total_bytes_received'),
                    Capsule::raw('SUM(t.ops) as total_ops'),
                    Capsule::raw('SUM(t.successful_ops) as total_successful_ops')
                )
                ->whereIn('t.user_id', $userIds)
                ->whereDate('t.created_at', '>=', $periods['start'])
                ->whereDate('t.created_at', '<=', $periods['end']);
            $this->applyTransferCutoverFilters($transferQuery, $currentBucketIds, $cutoverDate, $firstSeenSub);
            $transferStatsSummary = $transferQuery
                ->groupBy('usage_period')
                ->orderBy('usage_period', 'ASC')
                ->get();
        }
        // #region agent log
        try {
            $rowsCount = is_iterable($transferStatsSummary) ? count($transferStatsSummary) : 0;
            $sumReceived = 0.0;
            $sumSent = 0.0;
            if (is_iterable($transferStatsSummary)) {
                foreach ($transferStatsSummary as $row) {
                    $sumReceived += (float)($row->total_bytes_received ?? 0);
                    $sumSent += (float)($row->total_bytes_sent ?? 0);
                }
            }
            file_put_contents(
                '/var/www/eazybackup.ca/.cursor/debug.log',
                json_encode([
                    'id' => uniqid('log_', true),
                    'timestamp' => (int)round(microtime(true) * 1000),
                    'location' => 'lib/Client/BucketController.php:getUserTransferSummary',
                    'message' => 'Transfer series summary',
                    'data' => [
                        'userIds' => $userIds,
                        'limit' => $limit,
                        'cutoverDate' => $cutoverDate,
                        'currentBucketIdsCount' => is_array($currentBucketIds) ? count($currentBucketIds) : 0,
                        'rowsCount' => $rowsCount,
                        'sumReceived' => $sumReceived,
                        'sumSent' => $sumSent
                    ],
                    'runId' => 'pre-fix-2',
                    'hypothesisId' => 'H5'
                ]) . PHP_EOL,
                FILE_APPEND
            );
        } catch (\Throwable $e) {}
        // #endregion

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
     * Get transfer summary for a custom date range (daily buckets).
     *
     * @param array $userIds
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @return array
     */
    public function getTransferSummaryForRange(array $userIds, string $startDate, string $endDate): array
    {
        [$currentBucketIds, $cutoverDate, $firstSeenSub] = $this->getTransferCutoverContext($userIds);
        $transferQuery = Capsule::table('s3_transfer_stats_summary AS t')
            ->select(
                Capsule::raw('DATE_FORMAT(t.created_at, "%Y-%m-%d") AS usage_period'),
                Capsule::raw('SUM(t.bytes_sent) as total_bytes_sent'),
                Capsule::raw('SUM(t.bytes_received) as total_bytes_received'),
                Capsule::raw('SUM(t.ops) as total_ops'),
                Capsule::raw('SUM(t.successful_ops) as total_successful_ops')
            )
            ->whereIn('t.user_id', $userIds)
            ->whereDate('t.created_at', '>=', $startDate)
            ->whereDate('t.created_at', '<=', $endDate);
        $this->applyTransferCutoverFilters($transferQuery, $currentBucketIds, $cutoverDate, $firstSeenSub);
        $transferStatsSummary = $transferQuery
            ->groupBy('usage_period')
            ->orderBy('usage_period', 'ASC')
            ->get();

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
     * Determine cutover context for transfer summaries.
     *
     * @param array $userIds
     * @return array [currentBucketIds, cutoverDate, firstSeenSubquery]
     */
    private function getTransferCutoverContext(array $userIds): array
    {
        $currentBucketIds = Capsule::table('s3_buckets')
            ->whereIn('user_id', $userIds)
            ->where('is_active', 1)
            ->pluck('id')
            ->all();

        $cutoverDate = null;
        if (!empty($currentBucketIds)) {
            $cutoverDate = Capsule::table('s3_transfer_stats_summary')
                ->whereIn('user_id', $userIds)
                ->whereIn('bucket_id', $currentBucketIds)
                ->selectRaw('MIN(DATE(created_at)) as cutover')
                ->value('cutover');
        }

        $firstSeenSub = Capsule::table('s3_transfer_stats_summary')
            ->selectRaw('bucket_id, MIN(created_at) AS first_seen')
            ->whereIn('user_id', $userIds)
            ->groupBy('bucket_id');

        return [$currentBucketIds, $cutoverDate, $firstSeenSub];
    }

    /**
     * Apply cutover filtering and exclude first snapshot rows on cutover date.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $currentBucketIds
     * @param string|null $cutoverDate
     * @param \Illuminate\Database\Query\Builder $firstSeenSub
     * @param string|null $startDate
     * @param string|null $endDate
     * @return void
     */
    private function applyTransferCutoverFilters($query, array $currentBucketIds, ?string $cutoverDate, $firstSeenSub, ?string $startDate = null, ?string $endDate = null): void
    {
        if (!empty($startDate)) {
            $query->whereDate('t.created_at', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('t.created_at', '<=', $endDate);
        }
        $query->leftJoinSub($firstSeenSub, 'fs', 'fs.bucket_id', '=', 't.bucket_id');

        if (!empty($currentBucketIds) && !empty($cutoverDate)) {
            $query->where(function ($or) use ($cutoverDate, $currentBucketIds) {
                $or->where(function ($before) use ($cutoverDate, $currentBucketIds) {
                    $before->whereDate('t.created_at', '<', $cutoverDate)
                        ->whereNotIn('t.bucket_id', $currentBucketIds);
                })->orWhere(function ($after) use ($cutoverDate, $currentBucketIds) {
                    $after->whereDate('t.created_at', '>=', $cutoverDate)
                        ->whereIn('t.bucket_id', $currentBucketIds);
                });
            });
        }

        if (!empty($cutoverDate)) {
            $query->where(function ($q) use ($cutoverDate) {
                $q->whereNull('fs.first_seen')
                    ->orWhereDate('fs.first_seen', '!=', $cutoverDate)
                    ->orWhere(function ($q2) use ($cutoverDate) {
                        $q2->whereDate('fs.first_seen', '=', $cutoverDate)
                            ->whereRaw('t.created_at <> fs.first_seen');
                    });
            });
        }
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
        $daily = $this->getUserBucketSummary($userIds, $billingPeriod['start'], $billingPeriod['end']);
        if (empty($daily)) {
            return (object)[
                'exact_timestamp' => null,
                'total_size' => 0
            ];
        }
        $peakSize = 0;
        $peakDate = null;
        foreach ($daily as $row) {
            $value = isset($row['total_usage']) ? (float)$row['total_usage'] : 0;
            if ($value >= $peakSize) {
                $peakSize = $value;
                $peakDate = $row['period'] ?? $peakDate;
            }
        }
        return (object)[
            'exact_timestamp' => $peakDate,
            'total_size' => $peakSize
        ];
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
            // If decrypt fails or settings changed, fail fast with actionable guidance
            if (empty($this->accessKey) || empty($this->secretKey)) {
                return [
                    'status' => 'fail',
                    'message' => 'Access keys are missing or invalid. Please create a new access key pair from the Access Keys page.'
                ];
            }
            // For non-AWS S3-compatible endpoints (e.g., Ceph/MinIO), force us-east-1 for SigV4 and bucket creation compatibility.
            $effectiveRegion = $this->region;
            try {
                $host = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: '';
                $isAws = is_string($host) && stripos($host, 'amazonaws.com') !== false;
                if (!$isAws) {
                    $effectiveRegion = 'us-east-1';
                }
            } catch (\Throwable $e) {
                // Default to current region if parsing fails
            }
            $s3ClientConfig = [
                'version' => 'latest',
                'region' => $effectiveRegion,
                'endpoint' => $this->endpoint,
                'credentials' => [
                    'key' => $this->accessKey,
                    'secret' => $this->secretKey,
                ],
                'use_path_style_endpoint' => true,
                'signature_version' => 'v4',
                // Guard against long-running RGW list calls that can block WHMCS.
                // Keep timeouts short; session locks are released before S3 calls
                // in API endpoints, but we still want fast failures.
                'http' => [
                    'connect_timeout' => 5.0,
                    'timeout' => 8.0,
                    'read_timeout' => 8.0,
                ],
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
     * Connect to S3 using admin credentials (for deprovision operations).
     * This allows bucket deletion even when user keys are revoked.
     *
     * @return array
     */
    public function connectS3ClientAsAdmin()
    {
        try {
            if (empty($this->adminAccessKey) || empty($this->adminSecretKey)) {
                return ['status' => 'fail', 'message' => 'Admin credentials not configured.'];
            }

            // For non-AWS S3-compatible endpoints, force us-east-1
            $effectiveRegion = $this->region;
            try {
                $host = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: '';
                $isAws = is_string($host) && stripos($host, 'amazonaws.com') !== false;
                if (!$isAws) {
                    $effectiveRegion = 'us-east-1';
                }
            } catch (\Throwable $e) {
                // Default to current region if parsing fails
            }

            $s3ClientConfig = [
                'version' => 'latest',
                'region' => $effectiveRegion,
                'endpoint' => $this->endpoint,
                'credentials' => [
                    'key' => $this->adminAccessKey,
                    'secret' => $this->adminSecretKey,
                ],
                'use_path_style_endpoint' => true,
                'signature_version' => 'v4',
                'http' => [
                    'connect_timeout' => 10.0,
                    'timeout' => 120.0,  // Longer timeout for admin operations (bulk deletes)
                    'read_timeout' => 120.0,
                ],
            ];

            $this->s3Client = new S3Client($s3ClientConfig);
            $this->accessKey = $this->adminAccessKey;
            $this->secretKey = $this->adminSecretKey;

            return [
                'status' => 'success',
                's3client' => $this->s3Client
            ];
        } catch (\Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [], $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Admin connection failure: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Connect to S3 using explicit access/secret keys.
     *
     * This is used for background operations where AdminOps credentials are available
     * but are not permitted for data-plane access (common in RGW multi-tenant setups).
     *
     * @param string $accessKey
     * @param string $secretKey
     * @param array $opts ['http' => ['connect_timeout'=>float,'timeout'=>float,'read_timeout'=>float]]
     * @return array
     */
    public function connectS3ClientWithCredentials(string $accessKey, string $secretKey, array $opts = []): array
    {
        try {
            $accessKey = trim($accessKey);
            $secretKey = trim($secretKey);
            if ($accessKey === '' || $secretKey === '') {
                return ['status' => 'fail', 'message' => 'Missing S3 access credentials.'];
            }

            // For non-AWS S3-compatible endpoints, force us-east-1
            $effectiveRegion = $this->region;
            try {
                $host = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: '';
                $isAws = is_string($host) && stripos($host, 'amazonaws.com') !== false;
                if (!$isAws) {
                    $effectiveRegion = 'us-east-1';
                }
            } catch (\Throwable $e) {
                // Default to current region if parsing fails
            }

            $http = [
                'connect_timeout' => 10.0,
                'timeout' => 120.0,
                'read_timeout' => 120.0,
            ];
            if (isset($opts['http']) && is_array($opts['http'])) {
                foreach (['connect_timeout', 'timeout', 'read_timeout'] as $k) {
                    if (array_key_exists($k, $opts['http'])) {
                        $http[$k] = $opts['http'][$k];
                    }
                }
            }

            $s3ClientConfig = [
                'version' => 'latest',
                'region' => $effectiveRegion,
                'endpoint' => $this->endpoint,
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => $secretKey,
                ],
                'use_path_style_endpoint' => true,
                'signature_version' => 'v4',
                'http' => $http,
            ];

            $this->s3Client = new S3Client($s3ClientConfig);
            $this->accessKey = $accessKey;
            $this->secretKey = $secretKey;

            return [
                'status' => 'success',
                's3client' => $this->s3Client
            ];
        } catch (\Throwable $e) {
            logModuleCall($this->module, __FUNCTION__, [], $e->getMessage());
            return [
                'status' => 'fail',
                'message' => 'Connection failure. Please try again later or contact support.'
            ];
        }
    }

    /**
     * Delete a bucket using admin credentials (for deprovision).
     * Handles Object Lock buckets by detecting retention errors.
     *
     * @param int $userId The s3_users.id for the bucket owner
     * @param string $bucketName The bucket name
     * @param bool $useAdminCreds Whether to use admin credentials (default true for deprovision)
     * @return array Result with status, message, and optional blocked flag
     */
    public function deleteBucketAsAdmin($userId, $bucketName, $useAdminCreds = true)
    {
        // Check protected bucket names
        if (in_array($bucketName, $this->protectedBucketNames)) {
            return [
                'status' => 'fail',
                'message' => "Bucket '{$bucketName}' is protected and cannot be deleted.",
                'blocked' => true,
            ];
        }

        // Connect as admin if requested
        if ($useAdminCreds) {
            $connResult = $this->connectS3ClientAsAdmin();
            if ($connResult['status'] !== 'success') {
                return $connResult;
            }
        }

        // Try to delete bucket contents
        try {
            $response = $this->deleteBucketContents($bucketName);
            if ($response['status'] == 'fail') {
                // Check if this is an Object Lock retention error
                if ($this->isRetentionError($response['message'] ?? '')) {
                    return [
                        'status' => 'fail',
                        'message' => 'Bucket has Object Lock retention; cannot delete objects until retention expires.',
                        'blocked' => true,
                    ];
                }
                return $response;
            }
        } catch (S3Exception $e) {
            if ($this->isRetentionError($e->getMessage())) {
                return [
                    'status' => 'fail',
                    'message' => 'Object Lock retention prevents deletion: ' . $e->getMessage(),
                    'blocked' => true,
                ];
            }
            throw $e;
        }

        // Delete versions and markers
        try {
            $response = $this->deleteBucketVersionsAndMarkers($bucketName);
            if ($response['status'] == 'fail') {
                if ($this->isRetentionError($response['message'] ?? '')) {
                    return [
                        'status' => 'fail',
                        'message' => 'Cannot delete versioned objects due to Object Lock retention.',
                        'blocked' => true,
                    ];
                }
                return $response;
            }
        } catch (S3Exception $e) {
            if ($this->isRetentionError($e->getMessage())) {
                return [
                    'status' => 'fail',
                    'message' => 'Object Lock retention prevents version deletion: ' . $e->getMessage(),
                    'blocked' => true,
                ];
            }
            throw $e;
        }

        // Handle incomplete multipart uploads
        $response = $this->handleIncompleteMultipartUploads($bucketName);
        if ($response['status'] == 'fail') {
            return $response;
        }

        // Finally delete the bucket itself
        try {
            $this->s3Client->deleteBucket(['Bucket' => $bucketName]);
            $this->s3Client->waitUntil('BucketNotExists', ['Bucket' => $bucketName]);

            return ['status' => 'success', 'message' => 'Bucket deleted successfully.'];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$userId, $bucketName], $e->getMessage());

            if ($this->isRetentionError($e->getMessage())) {
                return [
                    'status' => 'fail',
                    'message' => 'Cannot delete bucket due to Object Lock retention.',
                    'blocked' => true,
                ];
            }

            return ['status' => 'fail', 'message' => 'Failed to delete bucket: ' . $e->getMessage()];
        }
    }

    /**
     * Incremental bucket deletion using admin credentials.
     *
     * This is intended for background cron usage where we must avoid one large bucket
     * monopolizing the cron. Work is bounded by a deadline; when the deadline is reached
     * this returns status=in_progress rather than failing.
     *
     * Return shape:
     * - status: success | in_progress | fail
     * - blocked: bool (Object Lock / retention)
     * - message: string
     * - metrics: array (deleted/aborted counts + elapsed)
     *
     * @param int $userId
     * @param string $bucketName
     * @param array $opts ['deadline_ts' => int, 'use_admin_creds' => bool]
     * @return array
     */
    public function deleteBucketAsAdminIncremental(int $userId, string $bucketName, array $opts = []): array
    {
        $deadlineTs = isset($opts['deadline_ts']) ? (int) $opts['deadline_ts'] : (time() + 60);
        $useAdminCreds = array_key_exists('use_admin_creds', $opts) ? (bool) $opts['use_admin_creds'] : true;
        $bypassGovernanceRetention = !empty($opts['bypass_governance_retention']);

        $metrics = [
            'deleted_current_objects' => 0,
            'deleted_versions' => 0,
            'deleted_delete_markers' => 0,
            'aborted_multipart_uploads' => 0,
            'errors' => [],
            'started_at' => time(),
            'deadline_ts' => $deadlineTs,
        ];

        // Protected bucket names (base bucket only; callers should normalize if needed)
        if (in_array($bucketName, $this->protectedBucketNames, true)) {
            return [
                'status' => 'fail',
                'blocked' => true,
                'message' => "Bucket '{$bucketName}' is protected and cannot be deleted.",
                'metrics' => $metrics,
            ];
        }

        // Connect as admin
        if ($useAdminCreds) {
            $connResult = $this->connectS3ClientAsAdmin();
            if (($connResult['status'] ?? 'fail') !== 'success') {
                return [
                    'status' => 'fail',
                    'blocked' => false,
                    'message' => $connResult['message'] ?? 'Admin connection failure.',
                    'metrics' => $metrics,
                ];
            }
        }

        // Detect Object Lock enablement for better interpretation of delete failures.
        $objectLockEnabled = false;
        try {
            $olc = $this->s3Client->getObjectLockConfiguration(['Bucket' => $bucketName]);
            if (
                isset($olc['ObjectLockConfiguration']['ObjectLockEnabled']) &&
                $olc['ObjectLockConfiguration']['ObjectLockEnabled'] === 'Enabled'
            ) {
                $objectLockEnabled = true;
            }
        } catch (\Throwable $e) {
            $objectLockEnabled = false;
        }

        // Helper to finalize metrics
        $finalize = function (string $status, string $message, bool $blocked = false) use (&$metrics) {
            $metrics['elapsed_seconds'] = max(0, time() - (int) ($metrics['started_at'] ?? time()));
            return [
                'status' => $status,
                'blocked' => $blocked,
                'message' => $message,
                'metrics' => $metrics,
            ];
        };

        // If bucket is already gone, treat as success so the cron can clean up DB state.
        try {
            $this->s3Client->headBucket(['Bucket' => $bucketName]);
        } catch (S3Exception $e) {
            $awsCode = method_exists($e, 'getAwsErrorCode') ? (string) $e->getAwsErrorCode() : '';
            $msg = $e->getMessage();
            if ($awsCode === 'NoSuchBucket' || stripos($msg, 'NoSuchBucket') !== false) {
                return $finalize('success', 'Bucket not found on storage; treating as already deleted.', false);
            }
            // Other headBucket errors should proceed to normal delete path (may be transient).
        } catch (\Throwable $e) {
            // ignore
        }

        // Work slices: current objects, versions/markers, multipart uploads
        try {
            $r1 = $this->deleteBucketContentsPaged($bucketName, $deadlineTs, $bypassGovernanceRetention, $objectLockEnabled);
            $metrics['deleted_current_objects'] += (int) ($r1['deleted'] ?? 0);
            if (($r1['status'] ?? 'fail') === 'blocked') {
                return $finalize('fail', $r1['message'] ?? 'Deletion blocked by Object Lock retention.', true);
            }
            if (($r1['status'] ?? 'fail') === 'in_progress') {
                return $finalize('in_progress', 'Deletion in progress (objects).', false);
            }
            if (($r1['status'] ?? 'fail') === 'fail') {
                return $finalize('fail', $r1['message'] ?? 'Failed deleting bucket contents.', false);
            }

            $r2 = $this->deleteBucketVersionsAndMarkersPaged($bucketName, $deadlineTs, $bypassGovernanceRetention, $objectLockEnabled);
            $metrics['deleted_versions'] += (int) ($r2['deleted_versions'] ?? 0);
            $metrics['deleted_delete_markers'] += (int) ($r2['deleted_delete_markers'] ?? 0);
            if (($r2['status'] ?? 'fail') === 'blocked') {
                return $finalize('fail', $r2['message'] ?? 'Deletion blocked by Object Lock retention.', true);
            }
            if (($r2['status'] ?? 'fail') === 'in_progress') {
                return $finalize('in_progress', 'Deletion in progress (versions/markers).', false);
            }
            if (($r2['status'] ?? 'fail') === 'fail') {
                return $finalize('fail', $r2['message'] ?? 'Failed deleting bucket versions/markers.', false);
            }

            $r3 = $this->abortMultipartUploadsPaged($bucketName, $deadlineTs);
            $metrics['aborted_multipart_uploads'] += (int) ($r3['aborted'] ?? 0);
            if (($r3['status'] ?? 'fail') === 'in_progress') {
                return $finalize('in_progress', 'Deletion in progress (multipart uploads).', false);
            }
            if (($r3['status'] ?? 'fail') === 'fail') {
                // Multipart abort failures are treated as failure (may indicate permissions / transient issues)
                return $finalize('fail', $r3['message'] ?? 'Failed aborting multipart uploads.', false);
            }
        } catch (S3Exception $e) {
            if ($this->isRetentionError($e->getMessage())) {
                return $finalize('fail', 'Object Lock retention prevents deletion: ' . $e->getMessage(), true);
            }
            logModuleCall($this->module, __FUNCTION__, [$userId, $bucketName], $e->getMessage());
            return $finalize('fail', 'Deletion failed: ' . $e->getMessage(), false);
        } catch (\Throwable $e) {
            logModuleCall($this->module, __FUNCTION__, [$userId, $bucketName], $e->getMessage());
            return $finalize('fail', 'Deletion failed: ' . $e->getMessage(), false);
        }

        // If we hit the deadline, don't attempt bucket delete; requeue
        if (time() >= $deadlineTs) {
            return $finalize('in_progress', 'Deletion in progress (time budget reached).', false);
        }

        // Attempt to delete the bucket itself; if still not empty, return in_progress
        try {
            $this->s3Client->deleteBucket(['Bucket' => $bucketName]);
            $this->s3Client->waitUntil('BucketNotExists', ['Bucket' => $bucketName]);
            return $finalize('success', 'Bucket deleted successfully.', false);
        } catch (S3Exception $e) {
            $msg = $e->getMessage();
            if ($this->isRetentionError($msg)) {
                return $finalize('fail', 'Cannot delete bucket due to Object Lock retention.', true);
            }
            // Common eventual condition: bucket still not empty
            $awsCode = method_exists($e, 'getAwsErrorCode') ? (string) $e->getAwsErrorCode() : '';
            if ($awsCode === 'BucketNotEmpty' || stripos($msg, 'BucketNotEmpty') !== false) {
                return $finalize('in_progress', 'Bucket still not empty; will continue next run.', false);
            }
            return $finalize('fail', 'Failed to delete bucket: ' . $msg, false);
        }
    }

    /**
     * Check if an error message indicates Object Lock retention.
     *
     * @param string $message The error message
     * @return bool
     */
    private function isRetentionError(string $message): bool
    {
        // IMPORTANT:
        // - Do NOT treat generic AccessDenied as retention; that can hide misconfig/permission issues.
        // - Retention/LegalHold errors usually include explicit keywords.
        $messageLower = strtolower($message);

        $retentionIndicators = [
            'objectlocked',
            'object lock',
            'object is locked',
            'legal hold',
            'retention',
            'retainuntildate',
            'bypassgovernanceretention',
            'governance',
            'compliance',
            'invalidrequest', // commonly returned for retention constraints
        ];

        foreach ($retentionIndicators as $indicator) {
            if (strpos($messageLower, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * In Object Lock-enabled buckets, DeleteObjects per-item failures are often just "AccessDenied".
     * We treat that as a retention/hold block ONLY when we are operating in an Object Lock context.
     */
    private function isObjectLockAccessDeniedBlock(?string $code, ?string $message, bool $objectLockEnabled): bool
    {
        if (!$objectLockEnabled) {
            return false;
        }
        $c = strtolower((string) ($code ?? ''));
        $m = strtolower((string) ($message ?? ''));
        if ($c === 'accessdenied') {
            return true;
        }
        if ($this->isRetentionError($m)) {
            return true;
        }
        return false;
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


            // Inspect result for per-object errors
            $errors = [];
            if (isset($result['Errors']) && is_array($result['Errors']) && count($result['Errors']) > 0) {
                foreach ($result['Errors'] as $err) {
                    $errors[] = [
                        'Key' => $err['Key'] ?? null,
                        'VersionId' => $err['VersionId'] ?? null,
                        'Code' => $err['Code'] ?? null,
                        'Message' => $err['Message'] ?? null,
                    ];
                }
            }

            $deleted = [];
            if (isset($result['Deleted']) && is_array($result['Deleted'])) {
                foreach ($result['Deleted'] as $d) {
                    $deleted[] = [
                        'Key' => $d['Key'] ?? null,
                        'VersionId' => $d['VersionId'] ?? null,
                        'DeleteMarker' => $d['DeleteMarker'] ?? null
                    ];
                }
            }

            $statusCode = $result['@metadata']['statusCode'] ?? 0;
            $ok = ($statusCode == 200) && count($errors) === 0;
            $partial = ($statusCode == 200) && count($errors) > 0;

            $message = $ok ? 'Delete completed.' : ($partial ? 'Some objects could not be deleted.' : 'Delete request failed.');

            return [
                'status' => $ok ? 'success' : ($partial ? 'partial' : 'fail'),
                'message' => $message,
                'deleted' => $deleted,
                'errors' => $errors
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $objectsToDelete], $e->getMessage());

            return ['status' => 'fail', 'message' => 'File to delete object. Please try again or contact support.'];
        }
    }

    /**
     * List object versions and delete markers for a specific key.
     * Optionally include per-version Legal Hold and Retention details.
     *
     * @param string $bucketName
     * @param string $key
     * @param bool $includeDetails
     * @return array
     */
    public function getObjectVersionsForKey($bucketName, $key, $includeDetails = false)
    {
        $response = [
            'versions' => [],
            'delete_markers' => [],
            'has_versions' => false,
            'has_delete_markers' => false,
        ];

        try {
            $isTruncated = false;
            $keyMarker = null;
            $versionIdMarker = null;
            do {
                $params = [
                    'Bucket' => $bucketName,
                    'Prefix' => $key
                ];
                if ($keyMarker) {
                    $params['KeyMarker'] = $keyMarker;
                }
                if ($versionIdMarker) {
                    $params['VersionIdMarker'] = $versionIdMarker;
                }

                $result = $this->s3Client->listObjectVersions($params);

                // Versions
                if (!empty($result['Versions'])) {
                    foreach ($result['Versions'] as $v) {
                        if (($v['Key'] ?? '') !== $key) {
                            continue; // exact match only
                        }
                        $item = [
                            'Key' => $v['Key'],
                            'VersionId' => $v['VersionId'] ?? null,
                            'IsLatest' => (bool)($v['IsLatest'] ?? false),
                            'LastModified' => isset($v['LastModified']) && $v['LastModified'] instanceof \DateTimeInterface
                                ? $v['LastModified']->format('Y-m-d H:i:s')
                                : (string)($v['LastModified'] ?? ''),
                            'ETag' => $v['ETag'] ?? null,
                            'Size' => $v['Size'] ?? null,
                            'StorageClass' => $v['StorageClass'] ?? null,
                            'IsDeleteMarker' => false,
                        ];

                        if ($includeDetails && !empty($item['VersionId'])) {
                            // Legal Hold
                            try {
                                $lh = $this->s3Client->getObjectLegalHold([
                                    'Bucket' => $bucketName,
                                    'Key' => $key,
                                    'VersionId' => $item['VersionId']
                                ]);
                                $item['LegalHold'] = strtoupper($lh['LegalHold']['Status'] ?? 'OFF');
                            } catch (S3Exception $e) {
                                $item['LegalHold'] = 'OFF';
                            }
                            // Retention
                            try {
                                $ret = $this->s3Client->getObjectRetention([
                                    'Bucket' => $bucketName,
                                    'Key' => $key,
                                    'VersionId' => $item['VersionId']
                                ]);
                                $mode = isset($ret['Retention']['Mode']) ? strtoupper($ret['Retention']['Mode']) : null;
                                $until = $ret['Retention']['RetainUntilDate'] ?? null;
                                $untilStr = null;
                                if ($until instanceof \DateTimeInterface) {
                                    $untilStr = $until->format('Y-m-d H:i:s');
                                } elseif (!empty($until)) {
                                    $untilStr = (string)$until;
                                }
                                $item['Retention'] = [
                                    'Mode' => $mode,
                                    'RetainUntil' => $untilStr
                                ];
                            } catch (S3Exception $e) {
                                $item['Retention'] = null;
                            }
                        }

                        // Only show non-current versions under key in versions list (read-only toggle)
                        if (!$item['IsLatest']) {
                            $response['versions'][] = $item;
                        }
                    }
                }

                // Delete markers
                if (!empty($result['DeleteMarkers'])) {
                    foreach ($result['DeleteMarkers'] as $m) {
                        if (($m['Key'] ?? '') !== $key) {
                            continue;
                        }
                        $response['delete_markers'][] = [
                            'Key' => $m['Key'],
                            'VersionId' => $m['VersionId'] ?? null,
                            'IsLatest' => (bool)($m['IsLatest'] ?? false),
                            'LastModified' => isset($m['LastModified']) && $m['LastModified'] instanceof \DateTimeInterface
                                ? $m['LastModified']->format('Y-m-d H:i:s')
                                : (string)($m['LastModified'] ?? ''),
                            'IsDeleteMarker' => true
                        ];
                    }
                }

                $isTruncated = isset($result['IsTruncated']) ? (bool)$result['IsTruncated'] : false;
                $keyMarker = $result['NextKeyMarker'] ?? null;
                $versionIdMarker = $result['NextVersionIdMarker'] ?? null;
            } while ($isTruncated);

            $response['has_versions'] = count($response['versions']) > 0;
            $response['has_delete_markers'] = count($response['delete_markers']) > 0;

            return [
                'status' => 'success',
                'data' => $response
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $key, $includeDetails], $e->getMessage());
            return [
                'status' => 'fail',
                'message' => 'Unable to list versions for object.'
            ];
        }
    }

    /**
     * Build a Versions Index for a prefix using listObjectVersions.
     * Groups by Key and flattens into parent + child rows suitable for table rendering.
     * Supports pagination via NextKeyMarker and NextVersionIdMarker.
     *
     * @param string $bucketName
     * @param array $options [
     *   'prefix' => string,
     *   'key_marker' => ?string,
     *   'version_id_marker' => ?string,
     *   'max_keys' => int,
     *   'include_deleted' => bool,
     *   'only_with_versions' => bool
     * ]
     * @return array
     */
    public function getVersionsIndex($bucketName, array $options = [])
    {
        $prefix = isset($options['prefix']) ? (string)$options['prefix'] : '';
        $keyMarker = $options['key_marker'] ?? null;
        $versionIdMarker = $options['version_id_marker'] ?? null;
        $maxKeys = isset($options['max_keys']) && is_numeric($options['max_keys']) ? (int)$options['max_keys'] : 1000;
        if ($maxKeys <= 0) {
            $maxKeys = 1000;
        } elseif ($maxKeys > 1000) {
            $maxKeys = 1000;
        }
        $includeDeleted = isset($options['include_deleted']) ? (bool)$options['include_deleted'] : true;
        $onlyWithVersions = isset($options['only_with_versions']) ? (bool)$options['only_with_versions'] : false;

        try {
            $params = [
                'Bucket' => $bucketName,
                'MaxKeys' => $maxKeys
            ];
            if (!empty($prefix)) {
                $params['Prefix'] = $prefix;
            }
            if (!empty($keyMarker)) {
                $params['KeyMarker'] = $keyMarker;
            }
            if (!empty($versionIdMarker)) {
                $params['VersionIdMarker'] = $versionIdMarker;
            }

            $result = $this->s3Client->listObjectVersions($params);

            // Group entries by Key
            $groups = [];
            $hasVersionsByKey = [];
            if (!empty($result['Versions'])) {
                foreach ($result['Versions'] as $v) {
                    $k = $v['Key'] ?? null;
                    if (is_null($k)) {
                        continue;
                    }
                    if (!isset($groups[$k])) {
                        $groups[$k] = [];
                        $hasVersionsByKey[$k] = false;
                    }
                    $hasVersionsByKey[$k] = true;
                    $groups[$k][] = [
                        'type' => 'version',
                        'IsLatest' => (bool)($v['IsLatest'] ?? false),
                        'VersionId' => $v['VersionId'] ?? null,
                        'LastModified' => isset($v['LastModified']) && $v['LastModified'] instanceof \DateTimeInterface ? $v['LastModified']->format('Y-m-d H:i:s') : (string)($v['LastModified'] ?? ''),
                        'ETag' => $v['ETag'] ?? null,
                        'Size' => $v['Size'] ?? null,
                        'StorageClass' => $v['StorageClass'] ?? null,
                        'Owner' => isset($v['Owner']) ? ($v['Owner']['DisplayName'] ?? ($v['Owner']['ID'] ?? '')) : ''
                    ];
                }
            }
            if (!empty($result['DeleteMarkers'])) {
                foreach ($result['DeleteMarkers'] as $m) {
                    $k = $m['Key'] ?? null;
                    if (is_null($k)) {
                        continue;
                    }
                    if (!isset($groups[$k])) {
                        $groups[$k] = [];
                        if (!isset($hasVersionsByKey[$k])) {
                            $hasVersionsByKey[$k] = false;
                        }
                    }
                    $groups[$k][] = [
                        'type' => 'delete_marker',
                        'IsLatest' => (bool)($m['IsLatest'] ?? false),
                        'VersionId' => $m['VersionId'] ?? null,
                        'LastModified' => isset($m['LastModified']) && $m['LastModified'] instanceof \DateTimeInterface ? $m['LastModified']->format('Y-m-d H:i:s') : (string)($m['LastModified'] ?? ''),
                        'ETag' => null,
                        'Size' => null,
                        'StorageClass' => null,
                        'Owner' => ''
                    ];
                }
            }

            // Build flat rows
            $rows = [];
            foreach ($groups as $key => $entries) {
                // Determine if current (latest non-delete) exists
                $hasCurrent = false;
                foreach ($entries as $e) {
                    if ($e['type'] === 'version' && $e['IsLatest'] === true) {
                        $hasCurrent = true;
                        break;
                    }
                }

                // Filter groups by flags
                if ($onlyWithVersions && !$hasVersionsByKey[$key]) {
                    continue;
                }
                if (!$includeDeleted && !$hasCurrent) {
                    // Skip tombstoned keys when includeDeleted=false
                    continue;
                }

                // Sort entries by LastModified desc to compute parent meta and child order
                usort($entries, function ($a, $b) {
                    return strcmp(($b['LastModified'] ?? ''), ($a['LastModified'] ?? ''));
                });

                $latestModified = isset($entries[0]['LastModified']) ? $entries[0]['LastModified'] : '';

                // Parent row for the key
                $rows[] = [
                    'is_parent' => true,
                    'key' => $key,
                    'name' => $key,
                    'modified' => $latestModified,
                    'etag' => '',
                    'size' => '',
                    'storage_class' => '',
                    'owner' => '',
                    'version_id' => '',
                    'deleted' => !$hasCurrent
                ];

                // Revision numbering: newest -> oldest as N, N-1, ..., 1
                $total = count($entries);
                $index = 0;
                foreach ($entries as $e) {
                    $revNumber = $total - $index; // newest gets highest number
                    $label = 'revision #' . $revNumber;
                    $isDeleted = ($e['type'] === 'delete_marker');
                    if ($isDeleted) {
                        $label .= ' (deleted)';
                    }
                    $rows[] = [
                        'is_parent' => false,
                        'key' => $key,
                        'name' => $label,
                        'modified' => $e['LastModified'] ?? '',
                        'etag' => $e['ETag'] ?? '',
                        'size' => is_null($e['Size']) ? '—' : HelperController::formatSizeUnits((int)$e['Size']),
                        'storage_class' => $e['StorageClass'] ?? '',
                        'owner' => $e['Owner'] ?? '',
                        'version_id' => $e['VersionId'] ?? '',
                        'deleted' => $isDeleted
                    ];
                    $index++;
                }
            }

            return [
                'status' => 'success',
                'data' => [
                    'rows' => $rows,
                    'next_key_marker' => $result['NextKeyMarker'] ?? null,
                    'next_version_id_marker' => $result['NextVersionIdMarker'] ?? null
                ]
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $options], $e->getMessage());
            return [
                'status' => 'fail',
                'message' => 'Unable to build versions index.'
            ];
        }
    }

    /**
     * Restore a specific object version by copying it over the same key to create a new current version.
     * Uses single-copy for <= 5 GiB, multipart copy otherwise.
     *
     * @param string $bucketName
     * @param string $key
     * @param string $sourceVersionId
     * @param string $metadataDirective COPY|REPLACE
     * @param array|null $metadata Optional metadata when REPLACE
     * @return array
     */
    public function restoreObjectVersion($bucketName, $key, $sourceVersionId, $metadataDirective = 'COPY', $metadata = null)
    {
        try {
            if (is_null($this->s3Client)) {
                return ['status' => 'fail', 'message' => 'Storage connection not established.'];
            }

            // Head source version to validate and get size/etag/metadata
            try {
                $head = $this->s3Client->headObject([
                    'Bucket' => $bucketName,
                    'Key' => $key,
                    'VersionId' => $sourceVersionId,
                ]);
            } catch (S3Exception $e) {
                return ['status' => 'fail', 'message' => 'Source version not found or not accessible.'];
            }

            // Determine size for single vs multipart copy
            $size = isset($head['ContentLength']) ? (int)$head['ContentLength'] : 0;
            $useMultipart = $size > (5 * 1024 * 1024 * 1024); // > 5 GiB

            $copyParamsBase = [
                'Bucket' => $bucketName,
                'Key' => $key,
                'CopySource' => rawurlencode($bucketName . '/' . $key) . '?versionId=' . rawurlencode($sourceVersionId),
                'MetadataDirective' => ($metadataDirective === 'REPLACE') ? 'REPLACE' : 'COPY',
            ];
            if ($metadataDirective === 'REPLACE' && is_array($metadata) && !empty($metadata)) {
                $copyParamsBase['Metadata'] = $metadata;
            }

            if (!$useMultipart) {
                $result = $this->s3Client->copyObject($copyParamsBase);
                $newVersionId = $result['VersionId'] ?? null;
                return [
                    'status' => 'success',
                    'data' => [
                        'new_version_id' => $newVersionId,
                        'key' => $key,
                        'bucket' => $bucketName,
                    ]
                ];
            }

            // Multipart copy
            $mpu = $this->s3Client->createMultipartUpload([
                'Bucket' => $bucketName,
                'Key' => $key,
                'MetadataDirective' => $copyParamsBase['MetadataDirective'],
            ] + (($metadataDirective === 'REPLACE' && isset($copyParamsBase['Metadata'])) ? ['Metadata' => $copyParamsBase['Metadata']] : []));

            $uploadId = $mpu['UploadId'];
            $partSize = 64 * 1024 * 1024; // 64 MiB default part size
            $parts = [];
            $offset = 0;
            $partNumber = 1;

            while ($offset < $size) {
                $end = min($offset + $partSize - 1, $size - 1);
                $range = 'bytes=' . $offset . '-' . $end;
                $copyPartResult = $this->s3Client->uploadPartCopy([
                    'Bucket' => $bucketName,
                    'Key' => $key,
                    'UploadId' => $uploadId,
                    'PartNumber' => $partNumber,
                    'CopySource' => rawurlencode($bucketName . '/' . $key) . '?versionId=' . rawurlencode($sourceVersionId),
                    'CopySourceRange' => $range,
                ]);
                $parts[] = [
                    'ETag' => $copyPartResult['CopyPartResult']['ETag'],
                    'PartNumber' => $partNumber,
                ];
                $offset = $end + 1;
                $partNumber++;
            }

            $complete = $this->s3Client->completeMultipartUpload([
                'Bucket' => $bucketName,
                'Key' => $key,
                'UploadId' => $uploadId,
                'MultipartUpload' => ['Parts' => $parts],
            ]);

            $newVersionId = $complete['VersionId'] ?? null;
            return [
                'status' => 'success',
                'data' => [
                    'new_version_id' => $newVersionId,
                    'key' => $key,
                    'bucket' => $bucketName,
                ]
            ];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $key, $sourceVersionId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Restore failed: ' . $e->getAwsErrorMessage()];
        } catch (\Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$bucketName, $key, $sourceVersionId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Restore failed.'];
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
                $raw = isset($result['Status']) ? (string)$result['Status'] : '';
                $upper = strtoupper($raw);
                if ($upper === 'ENABLED') {
                    $versionStatus = 'enabled';
                } elseif ($upper === 'SUSPENDED') {
                    $versionStatus = 'suspended';
                } else {
                    $versionStatus = 'off';
                }
            } else {
                $status = 'fail';
                $versionStatus = 'off';
            }

            logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], ['status' => $status, 'version' => $versionStatus]);
            return ['status' => $status, 'version_status' => $versionStatus];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());

            return ['status' => 'fail', 'message' => 'Unable to get the bucket version. Please try again or contact support.'];
        }
    }

    /**
     * Ensure bucket versioning is enabled. Attempts as bucket owner first, then admin fallback.
     *
     * @param string $bucketName
     * @return array ['status' => 'success'|'fail', 'message' => string]
     */
    public function ensureBucketVersioningEnabled($bucketName)
    {
        if (is_null($this->s3Client)) {
            return ['status' => 'fail', 'message' => 'Storage connection not established.'];
        }
        logModuleCall($this->module, __FUNCTION__ . '_START', ['bucket' => $bucketName], 'Attempting to ensure versioning enabled');
        // Check current status
        try {
            $cur = $this->getBucketVersioning($bucketName);
            if (($cur['status'] ?? 'fail') === 'success' && ($cur['version_status'] ?? 'off') === 'enabled') {
                logModuleCall($this->module, __FUNCTION__ . '_ALREADY_ENABLED', ['bucket' => $bucketName], 'Versioning already enabled');
                return ['status' => 'success', 'message' => 'Versioning already enabled'];
            }
        } catch (\Throwable $ignored) {}

        // Try enabling via tenant credentials
        try {
            $this->s3Client->putBucketVersioning([
                'Bucket' => $bucketName,
                'VersioningConfiguration' => [ 'Status' => 'Enabled' ],
            ]);
            logModuleCall($this->module, __FUNCTION__ . '_TENANT_ATTEMPT', ['bucket' => $bucketName], 'putBucketVersioning via tenant attempted');
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__ . '_TENANT_FAIL', ['bucket' => $bucketName, 'aws_error_code' => $e->getAwsErrorCode()], 'Tenant enable failed: ' . $e->getMessage());
            // ignore and try admin fallback
        }
        try {
            $check = $this->getBucketVersioning($bucketName);
            if (($check['status'] ?? 'fail') === 'success' && ($check['version_status'] ?? 'off') === 'enabled') {
                try {
                    Capsule::table('s3_buckets')->where('name', $bucketName)->update(['versioning' => 'enabled']);
                } catch (\Throwable $ignored) {}
                logModuleCall($this->module, __FUNCTION__ . '_TENANT_SUCCESS', ['bucket' => $bucketName], 'Versioning enabled via tenant');
                return ['status' => 'success', 'message' => 'Versioning enabled'];
            }
        } catch (\Throwable $ignored) {}

        // Admin fallback
        try {
            $adminS3 = new S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'endpoint' => $this->endpoint,
                'credentials' => [
                    'key' => $this->adminAccessKey,
                    'secret' => $this->adminSecretKey,
                ],
                'use_path_style_endpoint' => true,
                'signature_version' => 'v4'
            ]);
            $adminS3->putBucketVersioning([
                'Bucket' => $bucketName,
                'VersioningConfiguration' => [ 'Status' => 'Enabled' ],
            ]);
            logModuleCall($this->module, __FUNCTION__ . '_ADMIN_ATTEMPT', ['bucket' => $bucketName], 'putBucketVersioning via admin attempted');
        } catch (S3Exception $e2) {
            logModuleCall($this->module, __FUNCTION__ . '_ADMIN_FAIL', [
                'bucket_name' => $bucketName,
                'aws_error_code' => $e2->getAwsErrorCode()
            ], 'Admin versioning enable failed: ' . $e2->getMessage());
        }

        try {
            $final = $this->getBucketVersioning($bucketName);
            if (($final['status'] ?? 'fail') === 'success' && ($final['version_status'] ?? 'off') === 'enabled') {
                try {
                    Capsule::table('s3_buckets')->where('name', $bucketName)->update(['versioning' => 'enabled']);
                } catch (\Throwable $ignored) {}
                logModuleCall($this->module, __FUNCTION__ . '_ENABLED', ['bucket' => $bucketName], 'Versioning enabled (final)');
                return ['status' => 'success', 'message' => 'Versioning enabled'];
            }
        } catch (\Throwable $ignored) {}

        logModuleCall($this->module, __FUNCTION__ . '_GIVE_UP', ['bucket' => $bucketName], 'Unable to enable bucket versioning');
        return ['status' => 'fail', 'message' => 'Unable to enable bucket versioning'];
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
     * Get bucket Object Lock policy details (enabled + default retention policy).
     *
     * This is intentionally lightweight and does not enumerate objects/versions.
     *
     * @param string $bucketName
     * @return array
     *   - status: success|fail
     *   - enabled: bool
     *   - default_mode: string|null (COMPLIANCE|GOVERNANCE)
     *   - default_retention_days: int|null
     *   - default_retention_years: int|null
     *   - message: string|null
     */
    public function getBucketObjectLockPolicy(string $bucketName): array
    {
        try {
            $olc = $this->s3Client->getObjectLockConfiguration(['Bucket' => $bucketName]);
            $enabled =
                isset($olc['ObjectLockConfiguration']['ObjectLockEnabled']) &&
                $olc['ObjectLockConfiguration']['ObjectLockEnabled'] === 'Enabled';

            $mode = null;
            $days = null;
            $years = null;

            $default = $olc['ObjectLockConfiguration']['Rule']['DefaultRetention'] ?? null;
            if (is_array($default)) {
                if (!empty($default['Mode'])) {
                    $mode = strtoupper((string) $default['Mode']);
                }
                if (isset($default['Days']) && is_numeric($default['Days'])) {
                    $days = (int) $default['Days'];
                }
                if (isset($default['Years']) && is_numeric($default['Years'])) {
                    $years = (int) $default['Years'];
                }
            }

            return [
                'status' => 'success',
                'enabled' => (bool) $enabled,
                'default_mode' => $mode,
                'default_retention_days' => $days,
                'default_retention_years' => $years,
                'message' => null,
            ];
        } catch (S3Exception $e) {
            // Most S3 implementations throw when Object Lock is not enabled/configured on the bucket.
            // Treat that case as "disabled" rather than failing the caller.
            return [
                'status' => 'success',
                'enabled' => false,
                'default_mode' => null,
                'default_retention_days' => null,
                'default_retention_years' => null,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
            return [
                'status' => 'fail',
                'enabled' => false,
                'default_mode' => null,
                'default_retention_days' => null,
                'default_retention_years' => null,
                'message' => 'Unable to read bucket Object Lock configuration.',
            ];
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
            try {
                $awsCode = $e->getAwsErrorCode();
                if ($awsCode === 'InvalidAccessKeyId' || $awsCode === 'SignatureDoesNotMatch') {
                    $message = 'Access keys are missing or invalid. Please create a new access key pair from the Access Keys page.';
                }
            } catch (\Throwable $ignore) {}

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
        // Backwards-compatible wrapper: delete all contents with no time budget.
        $res = $this->deleteBucketContentsPaged($bucketName, PHP_INT_MAX, false, false);
        if (($res['status'] ?? 'fail') === 'success') {
            return ['status' => 'success', 'message' => 'Deleted all contents.'];
        }
        if (($res['status'] ?? 'fail') === 'blocked') {
            return ['status' => 'fail', 'message' => $res['message'] ?? 'Deletion blocked.'];
        }
        return ['status' => 'fail', 'message' => $res['message'] ?? 'Delete bucket contents failed. Please try again or contact support.'];
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
        // Backwards-compatible wrapper: delete all versions/markers with no time budget.
        $res = $this->deleteBucketVersionsAndMarkersPaged($bucketName, PHP_INT_MAX, false, false);
        if (($res['status'] ?? 'fail') === 'success') {
            return ['status' => 'success', 'message' => 'Deleted all versions and markers.'];
        }
        if (($res['status'] ?? 'fail') === 'blocked') {
            return ['status' => 'fail', 'message' => $res['message'] ?? 'Deletion blocked.'];
        }
        return ['status' => 'fail', 'message' => $res['message'] ?? 'Delete bucket versions and markers failed. Please try again or contact support.'];
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
        // Backwards-compatible wrapper: abort all uploads with no time budget.
        $res = $this->abortMultipartUploadsPaged($bucketName, PHP_INT_MAX);
        if (($res['status'] ?? 'fail') === 'success') {
            return ['status' => 'success', 'message' => 'Deleted all versions and markers.'];
        }
        return ['status' => 'fail', 'message' => $res['message'] ?? 'Handle incomplete uploads failed. Please try again or contact support.'];
    }

    /**
     * Paged deletion of current objects with a deadline.
     *
     * @param string $bucketName
     * @param int $deadlineTs
     * @return array ['status' => success|in_progress|fail|blocked, 'deleted' => int, 'message' => string]
     */
    private function deleteBucketContentsPaged(string $bucketName, int $deadlineTs, bool $bypassGovernanceRetention = false, bool $objectLockEnabled = false): array
    {
        $deleted = 0;
        $continuationToken = null;

        try {
            while (true) {
                if (time() >= $deadlineTs) {
                    return ['status' => 'in_progress', 'deleted' => $deleted, 'message' => 'Time budget reached while deleting objects.'];
                }

                $params = [
                    'Bucket' => $bucketName,
                    'MaxKeys' => 1000,
                ];
                if (!empty($continuationToken)) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $objects = $this->s3Client->listObjectsV2($params);
                $keys = [];
                if (!empty($objects['Contents'])) {
                    foreach ($objects['Contents'] as $obj) {
                        if (!empty($obj['Key'])) {
                            $keys[] = ['Key' => $obj['Key']];
                        }
                    }
                }

                if (count($keys) > 0) {
                    $deleteParams = [
                        'Bucket' => $bucketName,
                        'Delete' => ['Objects' => $keys, 'Quiet' => true],
                    ];
                    if ($bypassGovernanceRetention) {
                        $deleteParams['BypassGovernanceRetention'] = true;
                    }
                    $res = $this->s3Client->deleteObjects($deleteParams);
                    if (!empty($res['Deleted']) && is_array($res['Deleted'])) {
                        $deleted += count($res['Deleted']);
                    } else {
                        // Fallback: assume attempted keys were deleted if API doesn't return Deleted list
                        $deleted += count($keys);
                    }

                    // Per-object errors (often where retention appears)
                    if (!empty($res['Errors']) && is_array($res['Errors'])) {
                        foreach ($res['Errors'] as $err) {
                            $code = isset($err['Code']) ? (string) $err['Code'] : null;
                            $msg = isset($err['Message']) ? (string) $err['Message'] : null;
                            if ($this->isObjectLockAccessDeniedBlock($code, $msg, $objectLockEnabled)) {
                                return ['status' => 'blocked', 'deleted' => $deleted, 'message' => 'Object Lock retention prevents deletion of objects.'];
                            }
                        }
                    }
                }

                $isTruncated = isset($objects['IsTruncated']) ? (bool) $objects['IsTruncated'] : false;
                if ($isTruncated && !empty($objects['NextContinuationToken'])) {
                    $continuationToken = $objects['NextContinuationToken'];
                    continue;
                }
                break;
            }

            return ['status' => 'success', 'deleted' => $deleted, 'message' => 'Deleted current objects.'];
        } catch (S3Exception $e) {
            if ($this->isRetentionError($e->getMessage())) {
                return ['status' => 'blocked', 'deleted' => $deleted, 'message' => 'Object Lock retention prevents deletion: ' . $e->getMessage()];
            }
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());
            return ['status' => 'fail', 'deleted' => $deleted, 'message' => 'Delete bucket contents failed. Please try again or contact support.'];
        }
    }

    /**
     * Paged deletion of versions and delete markers with a deadline.
     *
     * @param string $bucketName
     * @param int $deadlineTs
     * @return array ['status' => success|in_progress|fail|blocked, 'deleted_versions' => int, 'deleted_delete_markers' => int, 'message' => string]
     */
    private function deleteBucketVersionsAndMarkersPaged(string $bucketName, int $deadlineTs, bool $bypassGovernanceRetention = false, bool $objectLockEnabled = false): array
    {
        $deletedVersions = 0;
        $deletedMarkers = 0;
        $keyMarker = null;
        $versionIdMarker = null;

        try {
            while (true) {
                if (time() >= $deadlineTs) {
                    return [
                        'status' => 'in_progress',
                        'deleted_versions' => $deletedVersions,
                        'deleted_delete_markers' => $deletedMarkers,
                        'message' => 'Time budget reached while deleting versions/markers.',
                    ];
                }

                $params = [
                    'Bucket' => $bucketName,
                    'MaxKeys' => 1000,
                ];
                if (!empty($keyMarker)) {
                    $params['KeyMarker'] = $keyMarker;
                }
                if (!empty($versionIdMarker)) {
                    $params['VersionIdMarker'] = $versionIdMarker;
                }

                $versions = $this->s3Client->listObjectVersions($params);

                $versionObjs = [];
                $markerObjs = [];

                if (!empty($versions['Versions'])) {
                    foreach ($versions['Versions'] as $v) {
                        if (!empty($v['Key']) && isset($v['VersionId'])) {
                            $versionObjs[] = ['Key' => $v['Key'], 'VersionId' => $v['VersionId']];
                        }
                    }
                }
                if (!empty($versions['DeleteMarkers'])) {
                    foreach ($versions['DeleteMarkers'] as $m) {
                        if (!empty($m['Key']) && isset($m['VersionId'])) {
                            $markerObjs[] = ['Key' => $m['Key'], 'VersionId' => $m['VersionId']];
                        }
                    }
                }

                $processChunks = function (array $objs, string $type) use ($bucketName, $deadlineTs, $bypassGovernanceRetention, $objectLockEnabled, &$deletedVersions, &$deletedMarkers) {
                    if (!count($objs)) {
                        return ['status' => 'success'];
                    }
                    $chunks = array_chunk($objs, 1000);
                    foreach ($chunks as $chunk) {
                        if (time() >= $deadlineTs) {
                            return ['status' => 'in_progress'];
                        }
                        $deleteParams = [
                            'Bucket' => $bucketName,
                            'Delete' => ['Objects' => $chunk, 'Quiet' => true],
                        ];
                        if ($bypassGovernanceRetention) {
                            $deleteParams['BypassGovernanceRetention'] = true;
                        }
                        $res = $this->s3Client->deleteObjects($deleteParams);

                        // Count successful deletes from response (fallback to attempted count)
                        $deletedCount = (!empty($res['Deleted']) && is_array($res['Deleted'])) ? count($res['Deleted']) : count($chunk);
                        if ($type === 'versions') {
                            $deletedVersions += $deletedCount;
                        } else {
                            $deletedMarkers += $deletedCount;
                        }

                        // Per-object errors (often where retention appears)
                        if (!empty($res['Errors']) && is_array($res['Errors'])) {
                            foreach ($res['Errors'] as $err) {
                                $code = isset($err['Code']) ? (string) $err['Code'] : null;
                                $msg = isset($err['Message']) ? (string) $err['Message'] : null;
                                if ($this->isObjectLockAccessDeniedBlock($code, $msg, $objectLockEnabled)) {
                                    return ['status' => 'blocked', 'message' => 'Object Lock retention prevents deletion of versions/markers.'];
                                }
                            }
                        }
                    }
                    return ['status' => 'success'];
                };

                $p1 = $processChunks($versionObjs, 'versions');
                if (($p1['status'] ?? '') === 'blocked') {
                    return [
                        'status' => 'blocked',
                        'deleted_versions' => $deletedVersions,
                        'deleted_delete_markers' => $deletedMarkers,
                        'message' => $p1['message'] ?? 'Object Lock retention prevents deletion of versions/markers.',
                    ];
                }
                if (($p1['status'] ?? '') === 'in_progress') {
                    return [
                        'status' => 'in_progress',
                        'deleted_versions' => $deletedVersions,
                        'deleted_delete_markers' => $deletedMarkers,
                        'message' => 'Time budget reached while deleting versions/markers.',
                    ];
                }

                $p2 = $processChunks($markerObjs, 'markers');
                if (($p2['status'] ?? '') === 'blocked') {
                    return [
                        'status' => 'blocked',
                        'deleted_versions' => $deletedVersions,
                        'deleted_delete_markers' => $deletedMarkers,
                        'message' => $p2['message'] ?? 'Object Lock retention prevents deletion of versions/markers.',
                    ];
                }
                if (($p2['status'] ?? '') === 'in_progress') {
                    return [
                        'status' => 'in_progress',
                        'deleted_versions' => $deletedVersions,
                        'deleted_delete_markers' => $deletedMarkers,
                        'message' => 'Time budget reached while deleting versions/markers.',
                    ];
                }

                $isTruncated = isset($versions['IsTruncated']) ? (bool) $versions['IsTruncated'] : false;
                if ($isTruncated) {
                    $keyMarker = $versions['NextKeyMarker'] ?? null;
                    $versionIdMarker = $versions['NextVersionIdMarker'] ?? null;
                    if (!empty($keyMarker) || !empty($versionIdMarker)) {
                        continue;
                    }
                }
                break;
            }

            return [
                'status' => 'success',
                'deleted_versions' => $deletedVersions,
                'deleted_delete_markers' => $deletedMarkers,
                'message' => 'Deleted versions and markers.',
            ];
        } catch (S3Exception $e) {
            if ($this->isRetentionError($e->getMessage())) {
                return [
                    'status' => 'blocked',
                    'deleted_versions' => $deletedVersions,
                    'deleted_delete_markers' => $deletedMarkers,
                    'message' => 'Object Lock retention prevents version deletion: ' . $e->getMessage(),
                ];
            }
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());
            return [
                'status' => 'fail',
                'deleted_versions' => $deletedVersions,
                'deleted_delete_markers' => $deletedMarkers,
                'message' => 'Delete bucket versions and markers failed. Please try again or contact support.',
            ];
        }
    }

    /**
     * Paged abort of multipart uploads with a deadline.
     *
     * @param string $bucketName
     * @param int $deadlineTs
     * @return array ['status' => success|in_progress|fail, 'aborted' => int, 'message' => string]
     */
    private function abortMultipartUploadsPaged(string $bucketName, int $deadlineTs): array
    {
        $aborted = 0;
        $keyMarker = null;
        $uploadIdMarker = null;

        try {
            while (true) {
                if (time() >= $deadlineTs) {
                    return ['status' => 'in_progress', 'aborted' => $aborted, 'message' => 'Time budget reached while aborting multipart uploads.'];
                }

                $params = [
                    'Bucket' => $bucketName,
                    'MaxUploads' => 1000,
                ];
                if (!empty($keyMarker)) {
                    $params['KeyMarker'] = $keyMarker;
                }
                if (!empty($uploadIdMarker)) {
                    $params['UploadIdMarker'] = $uploadIdMarker;
                }

                $uploads = $this->s3Client->listMultipartUploads($params);
                if (!empty($uploads['Uploads'])) {
                    foreach ($uploads['Uploads'] as $u) {
                        if (time() >= $deadlineTs) {
                            return ['status' => 'in_progress', 'aborted' => $aborted, 'message' => 'Time budget reached while aborting multipart uploads.'];
                        }
                        if (!empty($u['Key']) && !empty($u['UploadId'])) {
                            $this->s3Client->abortMultipartUpload([
                                'Bucket' => $bucketName,
                                'Key' => $u['Key'],
                                'UploadId' => $u['UploadId'],
                            ]);
                            $aborted++;
                        }
                    }
                }

                $isTruncated = isset($uploads['IsTruncated']) ? (bool) $uploads['IsTruncated'] : false;
                if ($isTruncated) {
                    $keyMarker = $uploads['NextKeyMarker'] ?? null;
                    $uploadIdMarker = $uploads['NextUploadIdMarker'] ?? null;
                    if (!empty($keyMarker) || !empty($uploadIdMarker)) {
                        continue;
                    }
                }
                break;
            }

            return ['status' => 'success', 'aborted' => $aborted, 'message' => 'Aborted multipart uploads.'];
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());
            return ['status' => 'fail', 'aborted' => $aborted, 'message' => 'Handle incomplete uploads failed. Please try again or contact support.'];
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
        // #region agent log
        try {
            $dailyCount = is_array($dailyUsageQuery) ? count($dailyUsageQuery) : 0;
            $transferCount = is_array($transferDataQuery) ? count($transferDataQuery) : 0;
            $peakDay = null;
            $peakStorage = 0.0;
            if (is_array($dailyUsageQuery)) {
                foreach ($dailyUsageQuery as $row) {
                    $value = (float)($row->total_storage ?? 0);
                    if ($value >= $peakStorage) {
                        $peakStorage = $value;
                        $peakDay = $row->date ?? $peakDay;
                    }
                }
            }
            file_put_contents(
                '/var/www/eazybackup.ca/.cursor/debug.log',
                json_encode([
                    'id' => uniqid('log_', true),
                    'timestamp' => (int)round(microtime(true) * 1000),
                    'location' => 'lib/Client/BucketController.php:getHistoricalUsage',
                    'message' => 'Historical usage aggregates',
                    'data' => [
                        'userIds' => $userIds,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'dailyCount' => $dailyCount,
                        'transferCount' => $transferCount,
                        'peakDay' => $peakDay,
                        'peakStorage' => $peakStorage
                    ],
                    'runId' => 'pre-fix-2',
                    'hypothesisId' => 'H6'
                ]) . PHP_EOL,
                FILE_APPEND
            );
        } catch (\Throwable $e) {}
        // #endregion

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
     * Get detailed object lock and emptiness status for a bucket.
     * Returns counts for objects, versions, delete markers, multipart uploads, legal holds,
     * counts of versions in COMPLIANCE/GOVERNANCE retention, earliest retain-until, and default mode.
     *
     * PERFORMANCE OPTIMIZATION:
     * - For Object Lock buckets: samples up to $maxVersionsToInspect versions for retention/hold checks
     *   instead of checking every single version (which caused multi-minute delays).
     * - For non-Object Lock buckets: skips retention/hold API calls entirely.
     * - Uses a single page of listObjectsV2 to check if bucket has current objects (fast).
     * - Provides accurate counts for versions/delete markers via pagination.
     *
     * @param string $bucketName
     * @param int $maxExamples Number of example objects to return for each blocker type
     * @param int $maxVersionsToInspect Max versions to check for retention/holds (sampling)
     * @return array
     */
    public function getObjectLockEmptyStatus($bucketName, $maxExamples = 3, $maxVersionsToInspect = 50)
    {
        $result = [
            'object_lock' => [
                'enabled' => false,
                'default_mode' => null,
            ],
            'counts' => [
                'current_objects' => 0,
                'versions' => 0,
                'delete_markers' => 0,
                'multipart_uploads' => 0,
                'legal_holds' => 0,
                'compliance_retained' => 0,
                'governance_retained' => 0,
            ],
            'earliest_retain_until' => null,
            'earliest_retain_until_ts' => null,
            'examples' => [
                'legal_holds' => [],
                'compliance' => [],
                'governance' => [],
                'multipart' => [],
            ],
            'empty' => false,
            'sampled' => false, // Indicates if retention/hold counts are from sampling
        ];

        try {
            // Object lock configuration / default mode (if any)
            $objectLockEnabled = false;
            try {
                $olc = $this->s3Client->getObjectLockConfiguration(['Bucket' => $bucketName]);
                if (
                    isset($olc['ObjectLockConfiguration']['ObjectLockEnabled']) &&
                    $olc['ObjectLockConfiguration']['ObjectLockEnabled'] === 'Enabled'
                ) {
                    $objectLockEnabled = true;
                    $result['object_lock']['enabled'] = true;
                    if (isset($olc['ObjectLockConfiguration']['Rule']['DefaultRetention']['Mode'])) {
                        $result['object_lock']['default_mode'] = $olc['ObjectLockConfiguration']['Rule']['DefaultRetention']['Mode'];
                    }
                }
            } catch (S3Exception $e) {
                // If getObjectLockConfiguration fails, treat as not enabled
            }

            // Fast check for current objects using a single page (MaxKeys=1000)
            // For delete modal we just need to know if objects exist, not an exact count for huge buckets
            try {
                $objects = $this->s3Client->listObjectsV2([
                    'Bucket' => $bucketName,
                    'MaxKeys' => 1000
                ]);
                $currentCount = 0;
                if (!empty($objects['Contents'])) {
                    $currentCount = count($objects['Contents']);
                }
                // If there are more objects, indicate with a "+" suffix logic handled in result
                $hasMoreObjects = isset($objects['IsTruncated']) && $objects['IsTruncated'];
                if ($hasMoreObjects) {
                    // For large buckets, we just need to know there are objects - no need to count all
                    // The UI will show "1000+" which is sufficient for the delete modal
                    $result['counts']['current_objects'] = $currentCount;
                    $result['counts']['current_objects_truncated'] = true;
                } else {
                    $result['counts']['current_objects'] = $currentCount;
                }
            } catch (S3Exception $e) {
                // If S3 listing fails, fall back to last-known DB value (best-effort only)
                try {
                    $bucketRow = Capsule::table('s3_buckets')->where('name', $bucketName)->first();
                    if ($bucketRow) {
                        $statId = Capsule::table('s3_bucket_stats')->where('bucket_id', $bucketRow->id)->max('id');
                        if ($statId) {
                            $stat = Capsule::table('s3_bucket_stats')->where('id', $statId)->first();
                            if ($stat && isset($stat->num_objects)) {
                                $result['counts']['current_objects'] = (int)$stat->num_objects;
                            }
                        }
                    }
                } catch (\Exception $ignored) {
                    // ignore
                }
            }

            // Multipart uploads in progress - use a single page for speed
            try {
                $uploads = $this->s3Client->listMultipartUploads([
                    'Bucket' => $bucketName,
                    'MaxUploads' => 100
                ]);
                if (!empty($uploads['Uploads'])) {
                    $result['counts']['multipart_uploads'] = count($uploads['Uploads']);
                    foreach (array_slice($uploads['Uploads'], 0, $maxExamples) as $u) {
                        $result['examples']['multipart'][] = [
                            'Key' => $u['Key'],
                            'UploadId' => $u['UploadId']
                        ];
                    }
                    // If truncated, there are more uploads
                    if (!empty($uploads['IsTruncated'])) {
                        $result['counts']['multipart_uploads_truncated'] = true;
                    }
                }
            } catch (S3Exception $e) {
                // ignore upload listing errors
            }

            // Versions and delete markers
            // We need accurate counts for these, so we paginate, but we only inspect
            // retention/holds on a sample of versions (first N) to avoid thousands of API calls.
            $earliestTs = null;
            $complianceFound = false;
            $governanceFound = false;
            $versionsInspected = 0;

            try {
                $isTruncated = false;
                $keyMarker = null;
                $versionIdMarker = null;
                $versionsList = []; // Collect versions for sampling
                
                do {
                    $params = [
                        'Bucket' => $bucketName,
                        'MaxKeys' => 1000
                    ];
                    if ($keyMarker) {
                        $params['KeyMarker'] = $keyMarker;
                    }
                    if ($versionIdMarker) {
                        $params['VersionIdMarker'] = $versionIdMarker;
                    }
                    $versions = $this->s3Client->listObjectVersions($params);

                    // Count versions
                    if (!empty($versions['Versions'])) {
                        $result['counts']['versions'] += count($versions['Versions']);
                        
                        // Collect versions for sampling (only if we haven't collected enough)
                        if (count($versionsList) < $maxVersionsToInspect) {
                            $remaining = $maxVersionsToInspect - count($versionsList);
                            $versionsList = array_merge(
                                $versionsList,
                                array_slice($versions['Versions'], 0, $remaining)
                            );
                        }
                    }

                    // Count delete markers
                    if (!empty($versions['DeleteMarkers'])) {
                        $result['counts']['delete_markers'] += count($versions['DeleteMarkers']);
                    }

                    $isTruncated = isset($versions['IsTruncated']) ? (bool)$versions['IsTruncated'] : false;
                    $keyMarker = $versions['NextKeyMarker'] ?? null;
                    $versionIdMarker = $versions['NextVersionIdMarker'] ?? null;
                } while ($isTruncated);

                // Now inspect retention/legal holds ONLY if Object Lock is enabled
                // and only on the sampled versions (not all of them)
                if ($objectLockEnabled && !empty($versionsList)) {
                    $result['sampled'] = (count($versionsList) < $result['counts']['versions']);
                    
                    foreach ($versionsList as $v) {
                        $versionsInspected++;
                        
                        // Check if we have enough examples - can exit early
                        $hasEnoughExamples = (
                            count($result['examples']['compliance']) >= $maxExamples &&
                            count($result['examples']['governance']) >= $maxExamples &&
                            count($result['examples']['legal_holds']) >= $maxExamples
                        );
                        
                        // If we found blockers and have enough examples, we can stop
                        // (we already know deletion will be blocked)
                        if ($hasEnoughExamples && $versionsInspected > 10) {
                            break;
                        }

                        // Inspect retention
                        try {
                            $ret = $this->s3Client->getObjectRetention([
                                'Bucket' => $bucketName,
                                'Key' => $v['Key'],
                                'VersionId' => $v['VersionId']
                            ]);
                            if (!empty($ret['Retention']['Mode'])) {
                                $mode = $ret['Retention']['Mode'];
                                $until = $ret['Retention']['RetainUntilDate'] ?? null;
                                $untilTs = null;
                                if ($until instanceof \DateTimeInterface) {
                                    $untilTs = $until->getTimestamp();
                                } elseif (!empty($until)) {
                                    $untilTs = strtotime((string)$until);
                                }
                                // Count only if retain-until is in the future
                                if ($untilTs && $untilTs > time()) {
                                    if (strtoupper($mode) === 'COMPLIANCE') {
                                        $result['counts']['compliance_retained'] += 1;
                                        $complianceFound = true;
                                        if (count($result['examples']['compliance']) < $maxExamples) {
                                            $result['examples']['compliance'][] = [
                                                'Key' => $v['Key'],
                                                'VersionId' => $v['VersionId'],
                                                'RetainUntil' => $untilTs
                                            ];
                                        }
                                    } elseif (strtoupper($mode) === 'GOVERNANCE') {
                                        $result['counts']['governance_retained'] += 1;
                                        $governanceFound = true;
                                        if (count($result['examples']['governance']) < $maxExamples) {
                                            $result['examples']['governance'][] = [
                                                'Key' => $v['Key'],
                                                'VersionId' => $v['VersionId'],
                                                'RetainUntil' => $untilTs
                                            ];
                                        }
                                    }
                                    if (is_null($earliestTs) || $untilTs < $earliestTs) {
                                        $earliestTs = $untilTs;
                                    }
                                }
                            }
                        } catch (S3Exception $e) {
                            // No retention or not permitted; ignore
                        }

                        // Inspect legal hold
                        try {
                            $lh = $this->s3Client->getObjectLegalHold([
                                'Bucket' => $bucketName,
                                'Key' => $v['Key'],
                                'VersionId' => $v['VersionId']
                            ]);
                            if (!empty($lh['LegalHold']['Status']) && strtoupper($lh['LegalHold']['Status']) === 'ON') {
                                $result['counts']['legal_holds'] += 1;
                                if (count($result['examples']['legal_holds']) < $maxExamples) {
                                    $result['examples']['legal_holds'][] = [
                                        'Key' => $v['Key'],
                                        'VersionId' => $v['VersionId']
                                    ];
                                }
                            }
                        } catch (S3Exception $e) {
                            // No legal hold or not permitted; ignore
                        }
                    }
                }
            } catch (S3Exception $e) {
                // ignore version listing errors
            }

            // Determine mode if default not set but versions indicate one
            if (empty($result['object_lock']['default_mode'])) {
                if ($complianceFound && !$governanceFound) {
                    $result['object_lock']['default_mode'] = 'COMPLIANCE';
                } elseif ($governanceFound && !$complianceFound) {
                    $result['object_lock']['default_mode'] = 'GOVERNANCE';
                }
            }

            // Earliest retain-until formatted
            if (!is_null($earliestTs)) {
                $result['earliest_retain_until'] = gmdate('Y-m-d H:i', $earliestTs) . ' Coordinated Universal Time';
                $result['earliest_retain_until_ts'] = (int) $earliestTs;
            }

            // Empty if everything is zero
            $result['empty'] = (
                $result['counts']['current_objects'] === 0 &&
                $result['counts']['versions'] === 0 &&
                $result['counts']['delete_markers'] === 0 &&
                $result['counts']['multipart_uploads'] === 0 &&
                $result['counts']['legal_holds'] === 0 &&
                $result['counts']['compliance_retained'] === 0 &&
                $result['counts']['governance_retained'] === 0
            );

            return [
                'status' => 'success',
                'data' => $result
            ];
        } catch (\Exception $e) {
            logModuleCall($this->module, __FUNCTION__, $bucketName, $e->getMessage());
            return [
                'status' => 'fail',
                'message' => 'Unable to determine bucket status at this time. Please try again later.'
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
            ->whereDate('created_at', $today)
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
            
            // Build UID parameter (handle tenant users, prefer RGW-safe ceph_uid)
            $baseUid = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($user);
            if (empty($baseUid)) { $baseUid = $username; } // legacy fallback
            $uid = $baseUid;
            if (!empty($user->tenant_id)) {
                $uid = $user->tenant_id . '$' . $baseUid;
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