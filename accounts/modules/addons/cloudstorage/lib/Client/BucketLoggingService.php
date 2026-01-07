<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Request\S3ClientFactory;

class BucketLoggingService
{
    private $endpoint;
    private $region;
    private $module = 'cloudstorage';

    public function __construct(string $endpoint, string $region = 'us-east-1')
    {
        $this->endpoint = $endpoint;
        $this->region = $region ?: 'us-east-1';
    }

    /**
     * Return effective logging status from S3 and DB hints.
     */
    public function getLogging(string $bucketName, int $ownerUserId, string $encryptionKey): array
    {
        $client = $this->makeClientForUser($ownerUserId, $encryptionKey);
        if (!$client) {
            return ['status' => 'fail', 'message' => 'Unable to connect to storage.'];
        }

        try {
            $res = $client->getBucketLogging(['Bucket' => $bucketName]);
        } catch (S3Exception $e) {
            // Ceph may answer 501 when unsupported
            $code = $e->getStatusCode();
            if ($code === 501) {
                return ['status' => 'fail', 'message' => 'Bucket logging is not supported by the storage cluster.'];
            }
            logModuleCall($this->module, __FUNCTION__, [$bucketName], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Unable to fetch logging status.'];
        }

        $enabled = isset($res['LoggingEnabled']);
        $targetBucket = $enabled ? ($res['LoggingEnabled']['TargetBucket'] ?? null) : null;
        $targetPrefix = $enabled ? ($res['LoggingEnabled']['TargetPrefix'] ?? null) : null;

        // DB hints for quick display
        $db = Capsule::table('s3_buckets')->where('name', $bucketName)->first();
        $dbFlag = $db ? (bool)$db->logging_enabled : false;
        $dbTarget = $db ? ($db->logging_target_bucket ?? null) : null;
        $dbPrefix = $db ? ($db->logging_target_prefix ?? null) : null;

        return [
            'status' => 'success',
            'data' => [
                'enabled' => $enabled,
                'target_bucket' => $targetBucket,
                'target_prefix' => $targetPrefix,
                'db' => [
                    'enabled' => $dbFlag,
                    'target_bucket' => $dbTarget,
                    'target_prefix' => $dbPrefix,
                ]
            ]
        ];
    }

    /**
     * Control-plane logging operations: mint a temporary key for the bucket owner via Admin Ops,
     * perform the S3 API call as the owner, then revoke the key.
     *
     * Supports Option B (no persistent keys in DB).
     *
     * @param object $owner s3_users row (must include username and optional tenant_id)
     */
    private function withTemporaryOwnerClient($owner, string $adminAccessKey, string $adminSecretKey, callable $fn): array
    {
        $ownerUsername = (string)\WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($owner);
        $tenantId = (string)($owner->tenant_id ?? '');
        $cephUid = ($tenantId !== '') ? ($tenantId . '$' . $ownerUsername) : $ownerUsername;
        if ($cephUid === '' || $ownerUsername === '') {
            return ['status' => 'fail', 'message' => 'Unable to connect to storage.'];
        }

		$tempAccessKey = '';
        try {
			// SAFETY: Always create/revoke temp keys using a tenant-qualified uid (tenant$uid) and WITHOUT tenant param.
			// This avoids ambiguous AdminOps behaviors where the response may include multiple keys and secrets.
			$tmp = AdminOps::createTempKey($this->endpoint, $adminAccessKey, $adminSecretKey, $cephUid, null);
			if (!is_array($tmp) || ($tmp['status'] ?? '') !== 'success') {
				logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_CREATE_FAILED', ['uid' => $cephUid], $tmp);
				return ['status' => 'fail', 'message' => 'Unable to connect to storage.'];
			}
			$tempAccessKey = (string)($tmp['access_key'] ?? '');
			$tempSecretKey = (string)($tmp['secret_key'] ?? '');
			if ($tempAccessKey === '' || $tempSecretKey === '') {
				logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_PARSE_FAILED', ['uid' => $cephUid], $tmp);
				return ['status' => 'fail', 'message' => 'Unable to connect to storage.'];
			}

            // For non-AWS S3-compatible endpoints, force us-east-1
            $effectiveRegion = $this->region;
            try {
                $host = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: '';
                $isAws = is_string($host) && stripos($host, 'amazonaws.com') !== false;
                if (!$isAws) {
                    $effectiveRegion = 'us-east-1';
                }
            } catch (\Throwable $e) {}

            $client = new S3Client([
                'version' => 'latest',
                'region' => $effectiveRegion ?: 'us-east-1',
                'endpoint' => $this->endpoint,
                'credentials' => [
                    'key' => $tempAccessKey,
                    'secret' => $tempSecretKey,
                ],
                'use_path_style_endpoint' => true,
                'signature_version' => 'v4',
                'http' => [
                    'connect_timeout' => 5.0,
                    'timeout' => 12.0,
                    'read_timeout' => 12.0,
                ],
            ]);

            return $fn($client, $cephUid);
        } catch (\Throwable $e) {
            logModuleCall($this->module, __FUNCTION__ . '_EXCEPTION', ['uid' => $cephUid], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Unable to connect to storage.'];
        } finally {
            if ($tempAccessKey !== '') {
                try {
					$rm = AdminOps::removeKey($this->endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $cephUid, null);
					logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_REMOVED', [
						'uid' => $cephUid,
						'access_key_hint' => substr($tempAccessKey, 0, 4) . '…' . substr($tempAccessKey, -4),
					], $rm);
                } catch (\Throwable $e) {
                    logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_REMOVE_EXCEPTION', ['uid' => $cephUid], $e->getMessage());
                }
            }
        }
    }

    /**
     * Control-plane: fetch logging status without requiring stored user keys.
     */
    public function getLoggingWithTempKey(string $bucketName, $owner, string $adminAccessKey, string $adminSecretKey): array
    {
        return $this->withTemporaryOwnerClient($owner, $adminAccessKey, $adminSecretKey, function(S3Client $client, string $cephUid) use ($bucketName){
            try {
                $res = $client->getBucketLogging(['Bucket' => $bucketName]);
            } catch (S3Exception $e) {
                $code = $e->getStatusCode();
                if ($code === 501) {
                    return ['status' => 'fail', 'message' => 'Bucket logging is not supported by the storage cluster.'];
                }
                logModuleCall($this->module, 'getLoggingWithTempKey', ['bucket' => $bucketName, 'uid' => $cephUid], $e->getMessage());
                return ['status' => 'fail', 'message' => 'Unable to fetch logging status.'];
            }

            $enabled = isset($res['LoggingEnabled']);
            $targetBucket = $enabled ? ($res['LoggingEnabled']['TargetBucket'] ?? null) : null;
            $targetPrefix = $enabled ? ($res['LoggingEnabled']['TargetPrefix'] ?? null) : null;

            $db = Capsule::table('s3_buckets')->where('name', $bucketName)->first();
            $dbFlag = $db ? (bool)$db->logging_enabled : false;
            $dbTarget = $db ? ($db->logging_target_bucket ?? null) : null;
            $dbPrefix = $db ? ($db->logging_target_prefix ?? null) : null;

            return [
                'status' => 'success',
                'data' => [
                    'enabled' => $enabled,
                    'target_bucket' => $targetBucket,
                    'target_prefix' => $targetPrefix,
                    'db' => [
                        'enabled' => $dbFlag,
                        'target_bucket' => $dbTarget,
                        'target_prefix' => $dbPrefix,
                    ]
                ]
            ];
        });
    }

    /**
     * Control-plane: enable logging without requiring stored user keys.
     */
    public function enableLoggingWithTempKey(string $sourceBucket, string $targetBucket, string $prefix, $owner, string $adminAccessKey, string $adminSecretKey): array
    {
        $ownerUserId = (int)($owner->id ?? 0);
        return $this->withTemporaryOwnerClient($owner, $adminAccessKey, $adminSecretKey, function(S3Client $client, string $cephUid) use ($sourceBucket, $targetBucket, $prefix, $ownerUserId){
            $targetRecord = Capsule::table('s3_buckets')->where('name', $targetBucket)->first();
            if (!$targetRecord || (int)$targetRecord->user_id !== (int)$ownerUserId) {
                return ['status' => 'fail', 'message' => 'Target log bucket must exist and be owned by you.'];
            }

            $aclOk = $this->ensureLogDeliveryAcl($client, $targetBucket);
            $aclWarning = !$aclOk;

            $prefix = rtrim($prefix ?? '', '/');
            if ($prefix !== '') {
                $prefix .= '/';
            }
            try {
                $client->putBucketLogging([
                    'Bucket' => $sourceBucket,
                    'BucketLoggingStatus' => [
                        'LoggingEnabled' => [
                            'TargetBucket' => $targetBucket,
                            'TargetPrefix' => $prefix,
                            'TargetGrants' => [
                                [
                                    'Grantee' => [
                                        'Type' => 'Group',
                                        'URI' => 'http://acs.amazonaws.com/groups/s3/LogDelivery'
                                    ],
                                    'Permission' => 'WRITE'
                                ],
                                [
                                    'Grantee' => [
                                        'Type' => 'Group',
                                        'URI' => 'http://acs.amazonaws.com/groups/s3/LogDelivery'
                                    ],
                                    'Permission' => 'READ_ACP'
                                ],
                            ],
                        ],
                    ],
                ]);
            } catch (S3Exception $e) {
                logModuleCall($this->module, 'enableLoggingWithTempKey', [$sourceBucket, $targetBucket, $prefix, $cephUid], $e->getMessage());
                return ['status' => 'fail', 'message' => 'Failed to enable logging.'];
            }

            Capsule::table('s3_buckets')
                ->where('name', $sourceBucket)
                ->update([
                    'logging_enabled' => 1,
                    'logging_target_bucket' => $targetBucket,
                    'logging_target_prefix' => $prefix,
                    'logging_last_synced_at' => date('Y-m-d H:i:s'),
                ]);

            $msg = $aclWarning
                ? 'Bucket logging enabled. Note: target ACL could not be confirmed; delivery may take longer or require admin intervention.'
                : 'Bucket logging enabled.';
            return ['status' => 'success', 'message' => $msg];
        });
    }

    /**
     * Control-plane: disable logging without requiring stored user keys.
     */
    public function disableLoggingWithTempKey(string $sourceBucket, $owner, string $adminAccessKey, string $adminSecretKey): array
    {
        return $this->withTemporaryOwnerClient($owner, $adminAccessKey, $adminSecretKey, function(S3Client $client, string $cephUid) use ($sourceBucket){
            try {
                $client->putBucketLogging([
                    'Bucket' => $sourceBucket,
                    'BucketLoggingStatus' => new \stdClass(), // empty structure clears logging
                ]);
            } catch (S3Exception $e) {
                logModuleCall($this->module, 'disableLoggingWithTempKey', [$sourceBucket, $cephUid], $e->getMessage());
                return ['status' => 'fail', 'message' => 'Failed to disable logging.'];
            }

            Capsule::table('s3_buckets')
                ->where('name', $sourceBucket)
                ->update([
                    'logging_enabled' => 0,
                    'logging_target_bucket' => null,
                    'logging_target_prefix' => null,
                    'logging_last_synced_at' => date('Y-m-d H:i:s'),
                ]);

            return ['status' => 'success', 'message' => 'Bucket logging disabled.'];
        });
    }

    /**
     * Enable S3 server access logging.
     */
    public function enableLogging(string $sourceBucket, string $targetBucket, string $prefix, int $ownerUserId, string $encryptionKey): array
    {
        $client = $this->makeClientForUser($ownerUserId, $encryptionKey);
        if (!$client) {
            return ['status' => 'fail', 'message' => 'Unable to connect to storage.'];
        }

        // Ensure target bucket exists and is owned by the same customer
        $targetRecord = Capsule::table('s3_buckets')->where('name', $targetBucket)->first();
        if (!$targetRecord || (int)$targetRecord->user_id !== (int)$ownerUserId) {
            return ['status' => 'fail', 'message' => 'Target log bucket must exist and be owned by you.'];
        }

        // Ensure ACL grants for log delivery on target bucket
        $aclOk = $this->ensureLogDeliveryAcl($client, $targetBucket);
        $aclWarning = false;
        if (!$aclOk) {
            // Proceed to attempt enabling logging anyway; some RGW builds accept TargetGrants alone.
            $aclWarning = true;
        }

        // Enable logging on the source bucket
        $prefix = rtrim($prefix ?? '', '/');
        if ($prefix !== '') {
            $prefix .= '/';
        }
        try {
            $client->putBucketLogging([
                'Bucket' => $sourceBucket,
                'BucketLoggingStatus' => [
                    'LoggingEnabled' => [
                        'TargetBucket' => $targetBucket,
                        'TargetPrefix' => $prefix,
                        'TargetGrants' => [
                            [
                                'Grantee' => [
                                    'Type' => 'Group',
                                    'URI' => 'http://acs.amazonaws.com/groups/s3/LogDelivery'
                                ],
                                'Permission' => 'WRITE'
                            ],
                            [
                                'Grantee' => [
                                    'Type' => 'Group',
                                    'URI' => 'http://acs.amazonaws.com/groups/s3/LogDelivery'
                                ],
                                'Permission' => 'READ_ACP'
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$sourceBucket, $targetBucket, $prefix], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to enable logging.'];
        }

        // Persist DB hints
        Capsule::table('s3_buckets')
            ->where('name', $sourceBucket)
            ->update([
                'logging_enabled' => 1,
                'logging_target_bucket' => $targetBucket,
                'logging_target_prefix' => $prefix,
                'logging_last_synced_at' => date('Y-m-d H:i:s'),
            ]);

        $msg = $aclWarning
            ? 'Bucket logging enabled. Note: target ACL could not be confirmed; delivery may take longer or require admin intervention.'
            : 'Bucket logging enabled.';
        return ['status' => 'success', 'message' => $msg];
    }

    /**
     * Disable S3 server access logging.
     */
    public function disableLogging(string $sourceBucket, int $ownerUserId, string $encryptionKey): array
    {
        $client = $this->makeClientForUser($ownerUserId, $encryptionKey);
        if (!$client) {
            return ['status' => 'fail', 'message' => 'Unable to connect to storage.'];
        }

        try {
            $client->putBucketLogging([
                'Bucket' => $sourceBucket,
                'BucketLoggingStatus' => new \stdClass(), // empty structure clears logging
            ]);
        } catch (S3Exception $e) {
            logModuleCall($this->module, __FUNCTION__, [$sourceBucket], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to disable logging.'];
        }

        Capsule::table('s3_buckets')
            ->where('name', $sourceBucket)
            ->update([
                'logging_enabled' => 0,
                'logging_target_bucket' => null,
                'logging_target_prefix' => null,
                'logging_last_synced_at' => date('Y-m-d H:i:s'),
            ]);

        return ['status' => 'success', 'message' => 'Bucket logging disabled.'];
    }

    private function makeClientForUser(int $userId, string $encryptionKey): ?S3Client
    {
        $res = S3ClientFactory::forUser($this->endpoint, $this->region, $userId, $encryptionKey);
        if (($res['status'] ?? 'fail') !== 'success') {
            return null;
        }
        return $res['client'];
    }

    private function ensureLogDeliveryAcl(S3Client $client, string $bucket): bool
    {
        // Wait for the bucket to be fully live before attempting ACL
        if (!$this->waitForBucket($client, $bucket, 10)) {
            return false;
        }

        // Try up to 6 attempts with small backoff to account for propagation
        $delay = 0.2; // seconds
        for ($i = 0; $i < 6; $i++) {
            // First try canned ACL which Ceph RGW typically supports
            try {
                $client->putBucketAcl([
                    'Bucket' => $bucket,
                    'ACL' => 'log-delivery-write',
                ]);
                return true;
            } catch (S3Exception $e) {
                // If the bucket isn't quite ready, wait and retry
                $code = (int)($e->getStatusCode() ?? 0);
                $awsCode = (string)$e->getAwsErrorCode();
                if ($code === 404 || stripos($awsCode, 'NoSuchBucket') !== false) {
                    if (!$this->waitForBucket($client, $bucket, 3)) {
                        usleep((int)($delay * 1_000_000));
                        $delay = min($delay * 2, 3.0);
                        continue;
                    }
                }
                // fallback to explicit grants
                try {
                    $acl = $client->getBucketAcl(['Bucket' => $bucket]);
                    $ownerId = $acl['Owner']['ID'] ?? ($acl['Owner']['DisplayName'] ?? '');
                    $client->putBucketAcl([
                        'Bucket' => $bucket,
                        'AccessControlPolicy' => [
                            'Grants' => [
                                [
                                    'Grantee' => [
                                        'Type' => 'Group',
                                        'URI' => 'http://acs.amazonaws.com/groups/s3/LogDelivery'
                                    ],
                                    'Permission' => 'WRITE'
                                ],
                                [
                                    'Grantee' => [
                                        'Type' => 'Group',
                                        'URI' => 'http://acs.amazonaws.com/groups/s3/LogDelivery'
                                    ],
                                    'Permission' => 'READ_ACP'
                                ],
                            ],
                            // Some RGW builds may not require Owner when using grant headers, but include when available
                            'Owner' => $ownerId !== '' ? [ 'ID' => $ownerId ] : null,
                        ],
                    ]);
                    return true;
                } catch (S3Exception $e2) {
                    // If still failing due to readiness, back off and retry
                    $code2 = (int)($e2->getStatusCode() ?? 0);
                    $awsCode2 = (string)$e2->getAwsErrorCode();
                    if ($code2 === 404 || stripos($awsCode2, 'NoSuchBucket') !== false) {
                        if (!$this->waitForBucket($client, $bucket, 3)) {
                            usleep((int)($delay * 1_000_000));
                            $delay = min($delay * 2, 3.0);
                            continue;
                        }
                    } else {
                        // One more fallback: header-style grants without owner
                        try {
                            $client->putBucketAcl([
                                'Bucket' => $bucket,
                                'GrantWrite' => 'uri="http://acs.amazonaws.com/groups/s3/LogDelivery"',
                                'GrantReadACP' => 'uri="http://acs.amazonaws.com/groups/s3/LogDelivery"',
                            ]);
                            return true;
                        } catch (S3Exception $e3) {
                            logModuleCall($this->module, __FUNCTION__, [$bucket], $e2->getMessage() . ' | fallback: ' . $e3->getMessage());
                            // backoff and retry loop
                            usleep((int)($delay * 1_000_000));
                            $delay = min($delay * 2, 3.0);
                            continue;
                        }
                    }
                }
            }
        }

        logModuleCall($this->module, __FUNCTION__, [$bucket], 'Failed to apply log-delivery ACL after retries');
        return false;
    }

    private function waitForBucket(S3Client $client, string $bucket, int $maxAttempts = 10): bool
    {
        $delay = 0.2; // seconds
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $client->headBucket(['Bucket' => $bucket]);
                return true;
            } catch (S3Exception $e) {
                usleep((int)($delay * 1_000_000));
                $delay = min($delay * 2, 3.0);
            }
        }
        return false;
    }
}


