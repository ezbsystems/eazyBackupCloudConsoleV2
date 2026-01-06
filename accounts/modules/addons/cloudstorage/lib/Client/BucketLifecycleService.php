<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Request\S3ClientFactory;

class BucketLifecycleService
{
	/** @var string */
	private $endpoint;

	/** @var string */
	private $region;

	/** @var string */
	private $module = 'cloudstorage';

	public function __construct(string $endpoint, string $region = 'us-east-1')
	{
		$this->endpoint = $endpoint;
		$this->region = $region ?: 'us-east-1';
	}

	/**
	 * Control-plane lifecycle operations: mint a temporary key for the bucket owner via Admin Ops,
	 * perform the S3 API call as the owner, then revoke the key.
	 *
	 * This supports Option B (no persistent keys in DB until customer explicitly creates them).
	 *
	 * @param object $owner s3_users row (must include username and optional tenant_id)
	 */
	private function withTemporaryOwnerClient($owner, string $adminAccessKey, string $adminSecretKey, callable $fn): array
	{
		$ownerUsername = (string)\WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($owner);
		$tenantId = (string)($owner->tenant_id ?? '');
		$cephUid = ($tenantId !== '') ? ($tenantId . '$' . $ownerUsername) : $ownerUsername;
		if ($cephUid === '' || $ownerUsername === '') {
			return ['status' => 'fail', 'message' => 'Unable to perform lifecycle operation. Please try again later.'];
		}

		$tempAccessKey = '';
		try {
			// Capture existing access keys so we can identify the newly-created temp key reliably.
			$beforeKeys = [];
			try {
				$info = AdminOps::getUserInfo($this->endpoint, $adminAccessKey, $adminSecretKey, $ownerUsername, $tenantId ?: null);
				$data = is_array($info) ? ($info['data'] ?? null) : null;
				if (is_array($data) && isset($data['keys']) && is_array($data['keys'])) {
					foreach ($data['keys'] as $k) {
						if (is_array($k) && !empty($k['access_key'])) {
							$beforeKeys[(string)$k['access_key']] = true;
						}
					}
				}
			} catch (\Throwable $e) {}

			$keys = AdminOps::createKey($this->endpoint, $adminAccessKey, $adminSecretKey, $ownerUsername, $tenantId ?: null);
			if (!is_array($keys) || ($keys['status'] ?? '') !== 'success') {
				logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_CREATE_FAILED', ['uid' => $cephUid], $keys);
				return ['status' => 'fail', 'message' => 'Unable to perform lifecycle operation. Please try again later.'];
			}
			$raw = $keys['data'] ?? [];
			$records = [];
			if (is_array($raw) && isset($raw['keys']) && is_array($raw['keys'])) {
				$records = $raw['keys'];
			} elseif (is_array($raw)) {
				$records = $raw;
			}
			$tempSecretKey = '';
			$newAccessKey = '';
			if (is_array($records) && count($records) > 0) {
				foreach ($records as $r) {
					if (!is_array($r)) { continue; }
					$ak = (string)($r['access_key'] ?? '');
					if ($ak !== '' && !isset($beforeKeys[$ak])) {
						$newAccessKey = $ak;
					}
				}
				if ($newAccessKey === '') {
					$last = end($records);
					if (is_array($last)) {
						$newAccessKey = (string)($last['access_key'] ?? '');
					}
					reset($records);
				}
				foreach ($records as $r) {
					if (!is_array($r)) { continue; }
					if ((string)($r['access_key'] ?? '') === $newAccessKey) {
						$tempAccessKey = (string)($r['access_key'] ?? '');
						$tempSecretKey = (string)($r['secret_key'] ?? '');
						break;
					}
				}
			}
			if ($tempAccessKey === '' || $tempSecretKey === '') {
				logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_PARSE_FAILED', ['uid' => $cephUid], $keys);
				return ['status' => 'fail', 'message' => 'Unable to perform lifecycle operation. Please try again later.'];
			}

			// For non-AWS S3-compatible endpoints, force us-east-1 (RGW/MinIO)
			$effectiveRegion = $this->region;
			try {
				$host = parse_url((string)$this->endpoint, PHP_URL_HOST) ?: '';
				$isAws = is_string($host) && stripos($host, 'amazonaws.com') !== false;
				if (!$isAws) {
					$effectiveRegion = 'us-east-1';
				}
			} catch (\Throwable $e) {
				// ignore
			}

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
			return ['status' => 'fail', 'message' => 'Unable to perform lifecycle operation. Please try again later.'];
		} finally {
			if ($tempAccessKey !== '') {
				try {
					$rm = AdminOps::removeKey($this->endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $ownerUsername, $tenantId ?: null);
					logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_REMOVED', ['uid' => $cephUid], $rm);
				} catch (\Throwable $e) {
					logModuleCall($this->module, __FUNCTION__ . '_TEMPKEY_REMOVE_EXCEPTION', ['uid' => $cephUid], $e->getMessage());
				}
			}
		}
	}

	/**
	 * Control-plane: fetch lifecycle rules without requiring stored user keys.
	 */
	public function getWithTempKey(string $bucketName, $owner, string $adminAccessKey, string $adminSecretKey): array
	{
		return $this->withTemporaryOwnerClient($owner, $adminAccessKey, $adminSecretKey, function(S3Client $client, string $cephUid) use ($bucketName){
			try {
				$res = $client->getBucketLifecycleConfiguration(['Bucket' => $bucketName]);
				$rules = isset($res['Rules']) && is_array($res['Rules']) ? $res['Rules'] : [];
				return ['status' => 'success', 'data' => ['rules' => $rules]];
			} catch (S3Exception $e) {
				$code = $e->getAwsErrorCode();
				if ($code === 'NoSuchLifecycleConfiguration') {
					return ['status' => 'success', 'data' => ['rules' => []]];
				}
				logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
				return ['status' => 'fail', 'message' => 'Unable to fetch lifecycle configuration. Please try again later.'];
			} catch (\Throwable $e) {
				logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
				return ['status' => 'fail', 'message' => 'Unable to fetch lifecycle configuration. Please try again later.'];
			}
		});
	}

	/**
	 * Control-plane: replace lifecycle rules without requiring stored user keys.
	 */
	public function putWithTempKey(string $bucketName, array $rules, $owner, string $adminAccessKey, string $adminSecretKey): array
	{
		return $this->withTemporaryOwnerClient($owner, $adminAccessKey, $adminSecretKey, function(S3Client $client, string $cephUid) use ($bucketName, $rules){
			try {
				// Log a compact request summary
				try {
					$summary = array_map(function($r){
						return [
							'ID' => $r['ID'] ?? '',
							'Status' => $r['Status'] ?? '',
							'hasFilter' => isset($r['Filter']),
							'hasTransition' => isset($r['Transition']),
							'hasNcTransition' => isset($r['NoncurrentVersionTransition']),
							'hasExpiration' => isset($r['Expiration']),
							'hasNcExpiration' => isset($r['NoncurrentVersionExpiration']),
							'hasAbort' => isset($r['AbortIncompleteMultipartUpload']),
						];
					}, $rules);
					logModuleCall($this->module, __FUNCTION__ . '_REQUEST', [
						'bucket' => $bucketName,
						'count' => count($rules),
						'rules' => $summary
					], 'Attempting putBucketLifecycleConfiguration (temp key)');
				} catch (\Throwable $e) {}

				$client->putBucketLifecycleConfiguration([
					'Bucket' => $bucketName,
					'LifecycleConfiguration' => [
						'Rules' => array_values($rules),
					],
				]);
				return ['status' => 'success', 'message' => 'Lifecycle saved.'];
			} catch (S3Exception $e) {
				try {
					$awsCode = method_exists($e, 'getAwsErrorCode') ? $e->getAwsErrorCode() : null;
					$awsMsg = method_exists($e, 'getAwsErrorMessage') ? $e->getAwsErrorMessage() : null;
					$status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null;
					logModuleCall($this->module, 'putWithTempKey_ERROR', [
						'bucket' => $bucketName,
						'uid' => $cephUid,
						'rules_count' => count($rules),
						'aws_error_code' => $awsCode,
						'aws_error_msg' => $awsMsg,
						'http_status' => $status,
						'rules' => $rules,
					], $e->getMessage());
				} catch (\Throwable $ignore) {
					logModuleCall($this->module, 'putWithTempKey_ERROR_FALLBACK', ['bucket' => $bucketName, 'uid' => $cephUid, 'rules_count' => count($rules)], $e->getMessage());
				}
				return ['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'];
			} catch (\Throwable $e) {
				logModuleCall($this->module, 'putWithTempKey_EXCEPTION', ['bucket' => $bucketName, 'uid' => $cephUid, 'rules_count' => count($rules)], $e->getMessage());
				return ['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'];
			}
		});
	}

	/**
	 * Control-plane: delete lifecycle configuration without requiring stored user keys.
	 */
	public function deleteWithTempKey(string $bucketName, $owner, string $adminAccessKey, string $adminSecretKey): array
	{
		return $this->withTemporaryOwnerClient($owner, $adminAccessKey, $adminSecretKey, function(S3Client $client, string $cephUid) use ($bucketName){
			try {
				$client->deleteBucketLifecycle(['Bucket' => $bucketName]);
				return ['status' => 'success', 'message' => 'Lifecycle removed.'];
			} catch (S3Exception $e) {
				logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
				return ['status' => 'fail', 'message' => 'Unable to remove lifecycle configuration. Please try again later.'];
			} catch (\Throwable $e) {
				logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
				return ['status' => 'fail', 'message' => 'Unable to remove lifecycle configuration. Please try again later.'];
			}
		});
	}

	/**
	 * Fetch lifecycle rules for a bucket.
	 *
	 * @param string $bucketName
	 * @param int    $ownerUserId
	 * @param string $encryptionKey
	 * @return array ['status'=>'success','data'=>['rules'=>[]]] or ['status'=>'fail','message'=>...]
	 */
	public function get(string $bucketName, int $ownerUserId, string $encryptionKey): array
	{
		$clientRes = S3ClientFactory::forUser($this->endpoint, $this->region, $ownerUserId, $encryptionKey);
		if (($clientRes['status'] ?? 'fail') !== 'success') {
			return ['status' => 'fail', 'message' => 'Unable to fetch lifecycle configuration. Please try again later.'];
		}

		$client = $clientRes['client'];
		try {
			$res = $client->getBucketLifecycleConfiguration(['Bucket' => $bucketName]);
			$rules = isset($res['Rules']) && is_array($res['Rules']) ? $res['Rules'] : [];
			return ['status' => 'success', 'data' => ['rules' => $rules]];
		} catch (S3Exception $e) {
			$code = $e->getAwsErrorCode();
			if ($code === 'NoSuchLifecycleConfiguration') {
				// Treat as no rules configured
				return ['status' => 'success', 'data' => ['rules' => []]];
			}
			// Log detailed error but return a generic message
			logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
			return ['status' => 'fail', 'message' => 'Unable to fetch lifecycle configuration. Please try again later.'];
		} catch (\Throwable $e) {
			logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
			return ['status' => 'fail', 'message' => 'Unable to fetch lifecycle configuration. Please try again later.'];
		}
	}

	/**
	 * Replace lifecycle rules for a bucket.
	 *
	 * @param string $bucketName
	 * @param array  $rules
	 * @param int    $ownerUserId
	 * @param string $encryptionKey
	 * @return array
	 */
	public function put(string $bucketName, array $rules, int $ownerUserId, string $encryptionKey): array
	{
		$clientRes = S3ClientFactory::forUser($this->endpoint, $this->region, $ownerUserId, $encryptionKey);
		if (($clientRes['status'] ?? 'fail') !== 'success') {
			return ['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'];
		}
		$client = $clientRes['client'];
		try {
			// Log a compact request summary
			try {
				$summary = array_map(function($r){
					return [
						'ID' => $r['ID'] ?? '',
						'Status' => $r['Status'] ?? '',
						'hasFilter' => isset($r['Filter']),
						'hasTransition' => isset($r['Transition']),
						'hasNcTransition' => isset($r['NoncurrentVersionTransition']),
						'hasExpiration' => isset($r['Expiration']),
						'hasNcExpiration' => isset($r['NoncurrentVersionExpiration']),
						'hasAbort' => isset($r['AbortIncompleteMultipartUpload']),
					];
				}, $rules);
				logModuleCall($this->module, __FUNCTION__ . '_REQUEST', [
					'bucket' => $bucketName,
					'count' => count($rules),
					'rules' => $summary
				], 'Attempting putBucketLifecycleConfiguration');
			} catch (\Throwable $e) {}

			$client->putBucketLifecycleConfiguration([
				'Bucket' => $bucketName,
				'LifecycleConfiguration' => [
					'Rules' => array_values($rules),
				],
			]);
			return ['status' => 'success', 'message' => 'Lifecycle saved.'];
		} catch (S3Exception $e) {
			try {
				$awsCode = method_exists($e, 'getAwsErrorCode') ? $e->getAwsErrorCode() : null;
				$awsMsg = method_exists($e, 'getAwsErrorMessage') ? $e->getAwsErrorMessage() : null;
				$status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null;
				logModuleCall($this->module, __FUNCTION__ . '_ERROR', [
					'bucket' => $bucketName,
					'rules_count' => count($rules),
					'aws_error_code' => $awsCode,
					'aws_error_msg' => $awsMsg,
					'http_status' => $status,
				], $e->getMessage());
			} catch (\Throwable $ignore) {
				logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName, 'rules_count' => count($rules)], $e->getMessage());
			}
			return ['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'];
		} catch (\Throwable $e) {
			logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName, 'rules_count' => count($rules)], $e->getMessage());
			return ['status' => 'fail', 'message' => 'Unable to save lifecycle rule. Please try again later.'];
		}
	}

	/**
	 * Delete lifecycle configuration for a bucket.
	 *
	 * @param string $bucketName
	 * @param int    $ownerUserId
	 * @param string $encryptionKey
	 * @return array
	 */
	public function delete(string $bucketName, int $ownerUserId, string $encryptionKey): array
	{
		$clientRes = S3ClientFactory::forUser($this->endpoint, $this->region, $ownerUserId, $encryptionKey);
		if (($clientRes['status'] ?? 'fail') !== 'success') {
		 return ['status' => 'fail', 'message' => 'Unable to remove lifecycle configuration. Please try again later.'];
		}
		$client = $clientRes['client'];
		try {
			$client->deleteBucketLifecycle(['Bucket' => $bucketName]);
			return ['status' => 'success', 'message' => 'Lifecycle removed.'];
		} catch (S3Exception $e) {
			logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
			return ['status' => 'fail', 'message' => 'Unable to remove lifecycle configuration. Please try again later.'];
		} catch (\Throwable $e) {
			logModuleCall($this->module, __FUNCTION__, ['bucket' => $bucketName], $e->getMessage());
			return ['status' => 'fail', 'message' => 'Unable to remove lifecycle configuration. Please try again later.'];
		}
	}
}


