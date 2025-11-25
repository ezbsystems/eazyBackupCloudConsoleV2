<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Aws\S3\Exception\S3Exception;
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


