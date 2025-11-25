<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\AwsS3Validator;

class CloudBackupController {

    private static $module = 'cloudstorage';

    /**
     * Get all jobs for a client
     *
     * @param int $clientId
     * @return array
     */
    public static function getJobsForClient($clientId)
    {
        try {
            $jobs = Capsule::table('s3_cloudbackup_jobs')
                ->where('client_id', $clientId)
                ->where('status', '!=', 'deleted')
                ->orderBy('created_at', 'desc')
                ->get();

            $jobsWithLastRun = [];
            foreach ($jobs as $job) {
                $jobArray = (array) $job;
                
                // Get last run summary
                $lastRun = Capsule::table('s3_cloudbackup_runs')
                    ->where('job_id', $job->id)
                    ->orderBy('started_at', 'desc')
                    ->first();
                
                $jobArray['last_run'] = $lastRun ? [
                    'status' => $lastRun->status,
                    'started_at' => $lastRun->started_at,
                    'finished_at' => $lastRun->finished_at,
                    'bytes_transferred' => $lastRun->bytes_transferred ?? 0,
                ] : null;

                $jobsWithLastRun[] = $jobArray;
            }

            return $jobsWithLastRun;
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'getJobsForClient', ['client_id' => $clientId], $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single job by ID with ownership verification
     *
     * @param int $jobId
     * @param int $clientId
     * @return array|null
     */
    public static function getJob($jobId, $clientId)
    {
        try {
            $job = Capsule::table('s3_cloudbackup_jobs')
                ->where('id', $jobId)
                ->where('client_id', $clientId)
                ->first();

            if (!$job) {
                return null;
            }

            return (array) $job;
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'getJob', ['job_id' => $jobId, 'client_id' => $clientId], $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new backup job
     *
     * @param array $data
     * @param string $encryptionKey
     * @return array
     */
    public static function createJob($data, $encryptionKey)
    {
        try {
            // Validate required fields
            $required = ['client_id', 's3_user_id', 'name', 'source_type', 'source_display_name', 'source_config', 'source_path', 'dest_bucket_id', 'dest_prefix'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return ['status' => 'fail', 'message' => "Missing required field: {$field}"];
                }
            }

            // Encrypt source config
            $sourceConfigJson = json_encode($data['source_config']);
            $encryptedConfig = HelperController::encryptKey($sourceConfigJson, $encryptionKey);

            // Prepare job data
            $jobData = [
                'client_id' => $data['client_id'],
                's3_user_id' => $data['s3_user_id'],
                'name' => $data['name'],
                'source_type' => $data['source_type'],
                'source_display_name' => $data['source_display_name'],
                'source_config_enc' => $encryptedConfig,
                'source_connection_id' => $data['source_connection_id'] ?? null,
                'source_path' => $data['source_path'],
                'dest_bucket_id' => $data['dest_bucket_id'],
                'dest_prefix' => $data['dest_prefix'],
                'backup_mode' => $data['backup_mode'] ?? 'sync',
                'schedule_type' => $data['schedule_type'] ?? 'manual',
                'schedule_time' => $data['schedule_time'] ?? null,
                'schedule_weekday' => $data['schedule_weekday'] ?? null,
                'schedule_cron' => $data['schedule_cron'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'encryption_enabled' => $data['encryption_enabled'] ?? 0,
                'compression_enabled' => $data['compression_enabled'] ?? 0,
                'validation_mode' => $data['validation_mode'] ?? 'none',
                'retention_mode' => $data['retention_mode'] ?? 'none',
                'retention_value' => $data['retention_value'] ?? null,
                'notify_override_email' => $data['notify_override_email'] ?? null,
                'notify_on_success' => $data['notify_on_success'] ?? 0,
                'notify_on_warning' => $data['notify_on_warning'] ?? 1,
                'notify_on_failure' => $data['notify_on_failure'] ?? 1,
                'status' => $data['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $jobId = Capsule::table('s3_cloudbackup_jobs')->insertGetId($jobData);

            return ['status' => 'success', 'job_id' => $jobId];
        } catch (\Exception $e) {
            $ctx = $data;
            if (isset($ctx['source_config'])) {
                $ctx['source_config'] = '[redacted]';
            }
            logModuleCall(self::$module, 'createJob', $ctx, $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to create job. Please try again later.'];
        }
    }

    /**
     * Update an existing backup job
     *
     * @param int $jobId
     * @param int $clientId
     * @param array $data
     * @param string $encryptionKey
     * @return array
     */
    public static function updateJob($jobId, $clientId, $data, $encryptionKey)
    {
        try {
            // Verify ownership
            $job = self::getJob($jobId, $clientId);
            if (!$job) {
                return ['status' => 'fail', 'message' => 'Job not found or access denied'];
            }

            // Prepare update data
            $updateData = [
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Update fields if provided
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            if (isset($data['source_display_name'])) {
                $updateData['source_display_name'] = $data['source_display_name'];
            }
            if (isset($data['source_config'])) {
                $sourceConfigJson = json_encode($data['source_config']);
                $updateData['source_config_enc'] = HelperController::encryptKey($sourceConfigJson, $encryptionKey);
            }
            if (isset($data['source_path'])) {
                $updateData['source_path'] = $data['source_path'];
            }
            if (array_key_exists('source_connection_id', $data)) {
                $updateData['source_connection_id'] = $data['source_connection_id'];
            }
            if (isset($data['dest_bucket_id'])) {
                $updateData['dest_bucket_id'] = $data['dest_bucket_id'];
            }
            if (isset($data['dest_prefix'])) {
                $updateData['dest_prefix'] = $data['dest_prefix'];
            }
            if (isset($data['backup_mode'])) {
                $updateData['backup_mode'] = $data['backup_mode'];
            }
            if (isset($data['schedule_type'])) {
                $updateData['schedule_type'] = $data['schedule_type'];
            }
            if (isset($data['schedule_time'])) {
                $updateData['schedule_time'] = $data['schedule_time'];
            }
            if (isset($data['schedule_weekday'])) {
                $updateData['schedule_weekday'] = $data['schedule_weekday'];
            }
            if (isset($data['schedule_cron'])) {
                $updateData['schedule_cron'] = $data['schedule_cron'];
            }
            if (isset($data['timezone'])) {
                $updateData['timezone'] = $data['timezone'];
            }
            if (isset($data['retention_mode'])) {
                $updateData['retention_mode'] = $data['retention_mode'];
            }
            if (isset($data['retention_value'])) {
                $updateData['retention_value'] = $data['retention_value'];
            }
            if (isset($data['notify_override_email'])) {
                $updateData['notify_override_email'] = $data['notify_override_email'];
            }
            if (isset($data['notify_on_success'])) {
                $updateData['notify_on_success'] = $data['notify_on_success'];
            }
            if (isset($data['notify_on_warning'])) {
                $updateData['notify_on_warning'] = $data['notify_on_warning'];
            }
            if (isset($data['notify_on_failure'])) {
                $updateData['notify_on_failure'] = $data['notify_on_failure'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            Capsule::table('s3_cloudbackup_jobs')
                ->where('id', $jobId)
                ->where('client_id', $clientId)
                ->update($updateData);

            return ['status' => 'success'];
        } catch (\Exception $e) {
            $ctx = ['job_id' => $jobId, 'client_id' => $clientId, 'data' => $data];
            if (isset($ctx['data']['source_config'])) {
                $ctx['data']['source_config'] = '[redacted]';
            }
            logModuleCall(self::$module, 'updateJob', $ctx, $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to update job. Please try again later.'];
        }
    }

    /**
     * Delete a backup job (soft delete)
     *
     * @param int $jobId
     * @param int $clientId
     * @return array
     */
    public static function deleteJob($jobId, $clientId)
    {
        try {
            // Verify ownership
            $job = self::getJob($jobId, $clientId);
            if (!$job) {
                return ['status' => 'fail', 'message' => 'Job not found or access denied'];
            }

            Capsule::table('s3_cloudbackup_jobs')
                ->where('id', $jobId)
                ->where('client_id', $clientId)
                ->update([
                    'status' => 'deleted',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return ['status' => 'success'];
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'deleteJob', ['job_id' => $jobId, 'client_id' => $clientId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to delete job. Please try again later.'];
        }
    }

    /**
     * Get runs for a specific job
     *
     * @param int $jobId
     * @param int $clientId
     * @return array
     */
    public static function getRunsForJob($jobId, $clientId)
    {
        try {
            // Verify job ownership
            $job = self::getJob($jobId, $clientId);
            if (!$job) {
                return [];
            }

            $runs = Capsule::table('s3_cloudbackup_runs')
                ->where('job_id', $jobId)
                ->orderBy('started_at', 'desc')
                ->get();

            // Convert stdClass objects to arrays for Smarty compatibility
            return array_map(function($item) {
                return (array) $item;
            }, $runs->toArray());
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'getRunsForJob', ['job_id' => $jobId, 'client_id' => $clientId], $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single run by ID with ownership verification
     *
     * @param int $runId
     * @param int $clientId
     * @return array|null
     */
    public static function getRun($runId, $clientId)
    {
        try {
            $run = Capsule::table('s3_cloudbackup_runs')
                ->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id')
                ->where('s3_cloudbackup_runs.id', $runId)
                ->where('s3_cloudbackup_jobs.client_id', $clientId)
                ->select('s3_cloudbackup_runs.*')
                ->first();

            if (!$run) {
                return null;
            }

            return (array) $run;
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'getRun', ['run_id' => $runId, 'client_id' => $clientId], $e->getMessage());
            return null;
        }
    }

    /**
     * Start a new run for a job
     *
     * @param int $jobId
     * @param int $clientId
     * @param string $triggerType
     * @return array
     */
    public static function startRun($jobId, $clientId, $triggerType = 'manual')
    {
        try {
            // Verify job ownership and status
            $job = self::getJob($jobId, $clientId);
            if (!$job) {
                return ['status' => 'fail', 'message' => 'Job not found or access denied'];
            }

            if ($job['status'] !== 'active') {
                return ['status' => 'fail', 'message' => 'Job is not active'];
            }

            // Validate destination bucket still exists and is active for this client's storage user
            $bucket = Capsule::table('s3_buckets')
                ->where('id', $job['dest_bucket_id'])
                ->where('is_active', 1)
                ->first();
            if (!$bucket) {
                return ['status' => 'fail', 'message' => 'Destination bucket not found or inactive'];
            }
            // Optional: ensure ownership alignment (bucket belongs to one of client's storage users)
            $clientUser = Capsule::table('s3_users')
                ->where('id', $job['s3_user_id'] ?? 0)
                ->first();
            if (!$clientUser || (int)$bucket->user_id !== (int)$clientUser->id) {
                return ['status' => 'fail', 'message' => 'Destination bucket ownership mismatch'];
            }

            // Re-validate AWS/S3-compatible source bucket and credentials at start time
            if (in_array($job['source_type'], ['aws', 's3_compatible'])) {
                // Fetch encryption key (query builder cannot be cloned; query separately)
                $ekey = Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudstorage')
                    ->where('setting', 'cloudbackup_encryption_key')
                    ->value('value');
                if (empty($ekey)) {
                    $ekey = Capsule::table('tbladdonmodules')
                        ->where('module', 'cloudstorage')
                        ->where('setting', 'encryption_key')
                        ->value('value');
                }
                if (empty($ekey)) {
                    return ['status' => 'fail', 'message' => 'Encryption key not configured'];
                }
                $dec = self::decryptSourceConfig($job, $ekey);
                if (!is_array($dec)) {
                    return ['status' => 'fail', 'message' => 'Unable to decrypt source configuration'];
                }
                $check = AwsS3Validator::validateBucketExists([
                    'endpoint'   => $dec['endpoint'] ?? null,
                    'region'     => $dec['region'] ?? 'us-east-1',
                    'bucket'     => $dec['bucket'] ?? '',
                    'access_key' => $dec['access_key'] ?? '',
                    'secret_key' => $dec['secret_key'] ?? '',
                ]);
                if (($check['status'] ?? 'fail') !== 'success') {
                    $msg = 'Source bucket validation failed';
                    if (!empty($check['message'])) {
                        $msg .= ': ' . $check['message'];
                    }
                    return ['status' => 'fail', 'message' => $msg];
                }
            }

            $runId = Capsule::table('s3_cloudbackup_runs')->insertGetId([
                'job_id' => $jobId,
                'trigger_type' => $triggerType,
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return ['status' => 'success', 'run_id' => $runId];
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'startRun', ['job_id' => $jobId, 'client_id' => $clientId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to start run. Please try again later.'];
        }
    }

    /**
     * Cancel a running job
     *
     * @param int $runId
     * @param int $clientId
     * @return array
     */
    public static function cancelRun($runId, $clientId)
    {
        try {
            // Verify run ownership
            $run = self::getRun($runId, $clientId);
            if (!$run) {
                return ['status' => 'fail', 'message' => 'Run not found or access denied'];
            }

            // Only allow cancellation of queued, starting, or running runs
            if (!in_array($run['status'], ['queued', 'starting', 'running'])) {
                return ['status' => 'fail', 'message' => 'Run cannot be cancelled in current status'];
            }

            Capsule::table('s3_cloudbackup_runs')
                ->where('id', $runId)
                ->update([
                    'cancel_requested' => 1,
                ]);

            return ['status' => 'success'];
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'cancelRun', ['run_id' => $runId, 'client_id' => $clientId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to cancel run. Please try again later.'];
        }
    }

    /**
     * Decrypt source config for a job
     *
     * @param array $job
     * @param string $encryptionKey
     * @return array|null
     */
    public static function decryptSourceConfig($job, $encryptionKey)
    {
        try {
            if (empty($job['source_config_enc'])) {
                return null;
            }

            $decryptedJson = HelperController::decryptKey($job['source_config_enc'], $encryptionKey);
            return json_decode($decryptedJson, true);
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'decryptSourceConfig', ['job_id' => $job['id'] ?? null], $e->getMessage());
            return null;
        }
    }

    /**
     * Send notification email for a completed run
     * Can be called by worker VM or cron job
     *
     * @param int $runId
     * @param string $emailTemplateId Template ID from config
     * @return array
     */
    public static function sendRunNotification($runId, $emailTemplateId)
    {
        try {
            // Get run with job data
            $run = Capsule::table('s3_cloudbackup_runs')
                ->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id')
                ->where('s3_cloudbackup_runs.id', $runId)
                ->select(
                    's3_cloudbackup_runs.*',
                    's3_cloudbackup_jobs.id as job_id',
                    's3_cloudbackup_jobs.name',
                    's3_cloudbackup_jobs.client_id',
                    's3_cloudbackup_jobs.source_display_name',
                    's3_cloudbackup_jobs.source_type',
                    's3_cloudbackup_jobs.dest_bucket_id',
                    's3_cloudbackup_jobs.dest_prefix',
                    's3_cloudbackup_jobs.notify_on_success',
                    's3_cloudbackup_jobs.notify_on_warning',
                    's3_cloudbackup_jobs.notify_on_failure',
                    's3_cloudbackup_jobs.notify_override_email'
                )
                ->first();

            if (!$run) {
                return ['status' => 'error', 'message' => 'Run not found'];
            }

            // Check if already notified
            if ($run->notified_at) {
                return ['status' => 'skipped', 'message' => 'Already notified'];
            }

            // Get client
            $client = DBController::getClient($run->client_id);
            if (!$client) {
                return ['status' => 'error', 'message' => 'Client not found'];
            }

            // Convert to arrays
            $runArray = (array) $run;
            $jobArray = [
                'id' => $run->job_id,
                'name' => $run->name,
                'client_id' => $run->client_id,
                'source_display_name' => $run->source_display_name,
                'source_type' => $run->source_type,
                'dest_bucket_id' => $run->dest_bucket_id,
                'dest_prefix' => $run->dest_prefix,
                'notify_on_success' => $run->notify_on_success,
                'notify_on_warning' => $run->notify_on_warning,
                'notify_on_failure' => $run->notify_on_failure,
                'notify_override_email' => $run->notify_override_email,
            ];

            // Send notification
            $result = \WHMCS\Module\Addon\CloudStorage\Client\CloudBackupEmailService::sendRunNotification(
                $runArray,
                $jobArray,
                $client,
                $emailTemplateId
            );

            // Mark as notified if successful
            if ($result['status'] === 'success') {
                Capsule::table('s3_cloudbackup_runs')
                    ->where('id', $runId)
                    ->update(['notified_at' => date('Y-m-d H:i:s')]);
            }

            return $result;
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'sendRunNotification', ['run_id' => $runId], $e->getMessage());
            return ['status' => 'error', 'message' => 'Notification failed. Please try again later.'];
        }
    }

    /**
     * Apply retention policy to a job
     * Deletes old backup data based on retention_mode and retention_value
     *
     * @param int $jobId
     * @param string $s3Endpoint
     * @param string $cephAdminUser
     * @param string $cephAdminAccessKey
     * @param string $cephAdminSecretKey
     * @param string $s3Region
     * @param string $encryptionKey
     * @return array
     */
    public static function applyRetentionPolicy($jobId, $s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region, $encryptionKey)
    {
        try {
            // Get job
            $job = Capsule::table('s3_cloudbackup_jobs')
                ->where('id', $jobId)
                ->first();

            if (!$job) {
                return ['status' => 'fail', 'message' => 'Job not found'];
            }

            if ($job->retention_mode === 'none' || empty($job->retention_value)) {
                return ['status' => 'skipped', 'message' => 'No retention policy configured'];
            }

            // Get destination bucket
            $bucket = Capsule::table('s3_buckets')
                ->where('id', $job->dest_bucket_id)
                ->first();

            if (!$bucket) {
                return ['status' => 'fail', 'message' => 'Destination bucket not found'];
            }

            // Get S3 user for bucket access
            $s3User = Capsule::table('s3_users')
                ->where('id', $bucket->user_id)
                ->first();

            if (!$s3User) {
                return ['status' => 'fail', 'message' => 'S3 user not found'];
            }

            // Initialize bucket controller
            $bucketController = new \WHMCS\Module\Addon\CloudStorage\Client\BucketController(
                $s3Endpoint,
                $cephAdminUser,
                $cephAdminAccessKey,
                $cephAdminSecretKey,
                $s3Region
            );

            $s3Connection = $bucketController->connectS3Client($s3User->id, $encryptionKey);
            if ($s3Connection['status'] == 'fail') {
                return ['status' => 'fail', 'message' => 'Failed to connect to S3: ' . $s3Connection['message']];
            }

            $runsToPrune = [];
            $prefix = rtrim($job->dest_prefix, '/') . '/';

            if ($job->retention_mode === 'keep_last_n') {
                // Get all successful runs ordered by started_at desc
                $allRuns = Capsule::table('s3_cloudbackup_runs')
                    ->where('job_id', $jobId)
                    ->where('status', 'success')
                    ->whereNotNull('started_at')
                    ->orderBy('started_at', 'desc')
                    ->get();

                // Keep the N most recent runs, mark others for pruning
                if ($allRuns->count() > $job->retention_value) {
                    $runsToPrune = $allRuns->slice($job->retention_value)->pluck('id')->toArray();
                }
            } elseif ($job->retention_mode === 'keep_days') {
                // Get runs older than retention_value days
                $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$job->retention_value} days"));
                $runsToPrune = Capsule::table('s3_cloudbackup_runs')
                    ->where('job_id', $jobId)
                    ->where('status', 'success')
                    ->where('started_at', '<', $cutoffDate)
                    ->pluck('id')
                    ->toArray();
            }

            if (empty($runsToPrune)) {
                return ['status' => 'success', 'message' => 'No runs to prune'];
            }

            // Get run details to build object keys
            $runs = Capsule::table('s3_cloudbackup_runs')
                ->whereIn('id', $runsToPrune)
                ->get();

            $deletedCount = 0;
            $errorCount = 0;

            foreach ($runs as $run) {
                try {
                    // Build object prefix for this run (typically includes timestamp)
                    // Format: dest_prefix/run_id/ or dest_prefix/YYYY-MM-DD/ or similar
                    // We'll delete all objects under the prefix that match the run's timeframe
                    $runPrefix = $prefix . 'run_' . $run->id . '/';
                    
                    // Alternative: if runs are stored by date, use started_at
                    if ($run->started_at) {
                        $datePrefix = $prefix . date('Y-m-d', strtotime($run->started_at)) . '/';
                        // Try both patterns
                        $prefixesToDelete = [$runPrefix, $datePrefix];
                    } else {
                        $prefixesToDelete = [$runPrefix];
                    }

                    // List and delete objects under each prefix
                    foreach ($prefixesToDelete as $deletePrefix) {
                        $objects = $bucketController->listBucketContents([
                            'bucket' => $bucket->name,
                            'prefix' => $deletePrefix
                        ], 'all');

                        if (!empty($objects['objects'])) {
                            $keysToDelete = [];
                            foreach ($objects['objects'] as $obj) {
                                // Handle both 'Key' and 'name' fields
                                $key = $obj['Key'] ?? $obj['name'] ?? null;
                                if ($key && ($obj['type'] ?? 'file') !== 'folder') {
                                    $keysToDelete[] = ['Key' => $key];
                                }
                            }

                            if (!empty($keysToDelete)) {
                                // Delete in batches of 1000 (S3 limit)
                                $batches = array_chunk($keysToDelete, 1000);
                                foreach ($batches as $batch) {
                                    $deleteResult = $bucketController->deleteBucketObject($bucket->name, $batch);
                                    if ($deleteResult['status'] === 'success' || $deleteResult['status'] === 'partial') {
                                        $deletedCount += count($deleteResult['deleted'] ?? []);
                                        if (!empty($deleteResult['errors'])) {
                                            $errorCount += count($deleteResult['errors']);
                                        }
                                    } else {
                                        $errorCount += count($batch);
                                    }
                                }
                            }
                        }
                    }

                    // Mark run as pruned (we'll add a pruned_at column if needed, or use a status field)
                    // For now, we'll just log that it was pruned
                    Capsule::table('s3_cloudbackup_runs')
                        ->where('id', $run->id)
                        ->update(['updated_at' => date('Y-m-d H:i:s')]);

                } catch (\Exception $e) {
                    $errorCount++;
                    logModuleCall(self::$module, 'applyRetentionPolicy_run', ['run_id' => $run->id], $e->getMessage());
                }
            }

            $message = "Pruned {$deletedCount} objects from " . count($runsToPrune) . " run(s)";
            if ($errorCount > 0) {
                $message .= " ({$errorCount} errors)";
            }

            return [
                'status' => $errorCount === 0 ? 'success' : 'partial',
                'message' => $message,
                'runs_pruned' => count($runsToPrune),
                'objects_deleted' => $deletedCount,
                'errors' => $errorCount
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, 'applyRetentionPolicy', ['job_id' => $jobId], $e->getMessage());
            return ['status' => 'error', 'message' => 'Retention operation failed. Please try again later.'];
        }
    }

    /**
     * Ensure bucket lifecycle matches a job's retention mode when feasible.
     * For keep_days: upsert a per-prefix lifecycle rule to expire current and noncurrent versions after N days.
     * For none: remove the job-scoped lifecycle rule if previously added.
     *
     * This complements, but does not replace, cron-based pruning.
     *
     * @param int $jobId
     * @return array
     */
    public static function manageLifecycleForJob($jobId)
    {
        try {
            // Load module config (admin/API creds)
            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);
            if (count($module) == 0) {
                return ['status' => 'fail', 'message' => 'Cloud Storage module not configured'];
            }
            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
            $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?? 'us-east-1';
            $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

            if (empty($s3Endpoint) || empty($cephAdminAccessKey) || empty($cephAdminSecretKey)) {
                return ['status' => 'fail', 'message' => 'Storage connection not configured'];
            }

            // Load job
            $job = Capsule::table('s3_cloudbackup_jobs')->where('id', $jobId)->first();
            if (!$job) {
                return ['status' => 'fail', 'message' => 'Job not found'];
            }

            $retentionMode = $job->retention_mode ?? 'none';
            $retentionValue = (int)($job->retention_value ?? 0);

            // Need a destination bucket and (ideally) a prefix to scope lifecycle
            $bucketRow = Capsule::table('s3_buckets')->where('id', $job->dest_bucket_id)->first();
            if (!$bucketRow) {
                return ['status' => 'fail', 'message' => 'Destination bucket not found'];
            }
            $bucketName = $bucketRow->name;
            $prefix = trim((string)($job->dest_prefix ?? ''));

            // Connect S3 client as bucket owner
            $bc = new \WHMCS\Module\Addon\CloudStorage\Client\BucketController(
                $s3Endpoint,
                $cephAdminUser,
                $cephAdminAccessKey,
                $cephAdminSecretKey,
                $s3Region
            );
            $conn = $bc->connectS3Client($bucketRow->user_id, $encryptionKey);
            if (!is_array($conn) || ($conn['status'] ?? 'fail') !== 'success') {
                return ['status' => 'fail', 'message' => 'Failed to connect S3 client for lifecycle change'];
            }

            // Unique rule id per job
            $ruleId = 'job-' . $jobId . '-keep-days';

            if ($retentionMode === 'keep_days' && $retentionValue > 0) {
                // Avoid whole-bucket rules when no prefix configured
                if ($prefix === '') {
                    return ['status' => 'skipped', 'message' => 'No dest_prefix; skipping lifecycle to avoid whole-bucket expiration'];
                }
                // Ensure versioning is enabled before applying lifecycle
                $ensure = $bc->ensureBucketVersioningEnabled($bucketName);
                if (($ensure['status'] ?? 'fail') !== 'success') {
                    return ['status' => 'fail', 'message' => 'Failed to enable bucket versioning required for retention'];
                }
                $res = $bc->upsertLifecycleRuleForPrefix($bucketName, $ruleId, $prefix, $retentionValue);
                return $res;
            }

            if ($retentionMode === 'none') {
                // Remove lifecycle rule if present
                $res = $bc->removeLifecycleRuleById($bucketName, $ruleId);
                return $res;
            }

            // Other modes (e.g., keep_last_n) not handled by lifecycle
            return ['status' => 'skipped', 'message' => 'Lifecycle not used for this retention mode'];
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'manageLifecycleForJob', ['job_id' => $jobId], $e->getMessage());
            return ['status' => 'error', 'message' => 'Lifecycle operation failed'];
        }
    }

    /**
     * Ensure bucket versioning is enabled for a bucket by its ID.
     * Attempts with bucket owner's credentials, then admin fallback inside BucketController.
     *
     * @param int $bucketId
     * @return array
     */
    public static function ensureVersioningForBucketId(int $bucketId): array
    {
        try {
            $bucket = Capsule::table('s3_buckets')->where('id', $bucketId)->first();
            if (!$bucket) {
                return ['status' => 'fail', 'message' => 'Bucket not found'];
            }
            logModuleCall(self::$module, __FUNCTION__ . '_START', ['bucket_id' => $bucketId, 'bucket' => $bucket->name ?? null], 'Ensuring versioning enabled');

            // Load module config
            $module = DBController::getResult('tbladdonmodules', [
                ['module', '=', 'cloudstorage']
            ]);
            if (count($module) == 0) {
                return ['status' => 'fail', 'message' => 'Cloud Storage module not configured'];
            }

            $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
            $cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
            $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
            $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
            $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?? 'us-east-1';
            $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

            if (empty($s3Endpoint) || empty($cephAdminAccessKey) || empty($cephAdminSecretKey)) {
                return ['status' => 'fail', 'message' => 'Storage connection not configured'];
            }

            $bc = new \WHMCS\Module\Addon\CloudStorage\Client\BucketController(
                $s3Endpoint,
                $cephAdminUser,
                $cephAdminAccessKey,
                $cephAdminSecretKey,
                $s3Region
            );
            $conn = $bc->connectS3Client((int)$bucket->user_id, $encryptionKey);
            if (!is_array($conn) || ($conn['status'] ?? 'fail') !== 'success') {
                logModuleCall(self::$module, __FUNCTION__ . '_CONNECT_FAIL', ['bucket_id' => $bucketId, 'bucket' => $bucket->name ?? null], 'Could not connect S3 client for bucket owner');
                return ['status' => 'fail', 'message' => 'Could not connect S3 client for bucket owner'];
            }

            $res = $bc->ensureBucketVersioningEnabled($bucket->name);
            logModuleCall(self::$module, __FUNCTION__ . '_RESULT', ['bucket_id' => $bucketId, 'bucket' => $bucket->name ?? null], $res);
            return $res;
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'ensureVersioningForBucketId', ['bucket_id' => $bucketId], $e->getMessage());
            return ['status' => 'error', 'message' => 'Error enforcing bucket versioning'];
        }
    }
}

