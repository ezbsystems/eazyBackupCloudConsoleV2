<?php

/**
 * Cloud Backup Email Notification Cron
 * 
 * This cron job checks for completed backup runs that haven't been notified yet
 * and sends email notifications based on job/client settings.
 * 
 * Should be run every 5-10 minutes.
 */

require_once __DIR__ . '/../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupEmailService;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

// Get module config
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    exit("Cloud Storage module not configured\n");
}

$emailTemplateId = $module->where('setting', 'cloudbackup_email_template')->pluck('value')->first();

if (empty($emailTemplateId)) {
    exit("Email template not configured. Skipping notifications.\n");
}

// Find completed runs that haven't been notified yet
// Check runs completed in the last hour that are in final states
$completedRuns = Capsule::table('s3_cloudbackup_runs')
    ->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id')
    ->whereIn('s3_cloudbackup_runs.status', ['success', 'warning', 'failed'])
    ->whereNotNull('s3_cloudbackup_runs.finished_at')
    ->where('s3_cloudbackup_runs.finished_at', '>=', date('Y-m-d H:i:s', strtotime('-1 hour')))
    ->whereNull('s3_cloudbackup_runs.notified_at')
    ->select(
        's3_cloudbackup_runs.id as run_id',
        's3_cloudbackup_runs.status',
        's3_cloudbackup_runs.started_at',
        's3_cloudbackup_runs.finished_at',
        's3_cloudbackup_runs.progress_pct',
        's3_cloudbackup_runs.bytes_total',
        's3_cloudbackup_runs.bytes_transferred',
        's3_cloudbackup_runs.error_summary',
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
    ->get();

// Log how many runs we found for notification
logModuleCall('cloudstorage', 'notify_cron_found', [
    'count' => $completedRuns->count(),
], '');

if ($completedRuns->isEmpty()) {
    exit("No completed runs to notify.\n");
}

echo "Found " . $completedRuns->count() . " completed run(s) to notify.\n";

$notifiedCount = 0;
$errorCount = 0;

foreach ($completedRuns as $runData) {
    try {
        // Per-run log context
        logModuleCall('cloudstorage', 'notify_cron_run', [
            'run_id' => $runData->run_id,
            'job_id' => $runData->job_id,
            'status' => $runData->status,
        ], '');

        // Get client data
        $client = DBController::getClient($runData->client_id);
        if (!$client) {
            echo "Skipping run {$runData->run_id}: Client not found\n";
            logModuleCall('cloudstorage', 'notify_cron_skip', [
                'run_id' => $runData->run_id,
                'reason' => 'client_not_found',
            ], '');
            continue;
        }

        // Convert to arrays for email service
        $run = [
            'id' => $runData->run_id,
            'status' => $runData->status,
            'started_at' => $runData->started_at,
            'finished_at' => $runData->finished_at,
            'progress_pct' => $runData->progress_pct,
            'bytes_total' => $runData->bytes_total,
            'bytes_transferred' => $runData->bytes_transferred,
            'error_summary' => $runData->error_summary,
        ];
        $job = [
            'id' => $runData->job_id,
            'name' => $runData->name,
            'client_id' => $runData->client_id,
            'source_display_name' => $runData->source_display_name,
            'source_type' => $runData->source_type,
            'dest_bucket_id' => $runData->dest_bucket_id,
            'dest_prefix' => $runData->dest_prefix,
            'notify_on_success' => $runData->notify_on_success,
            'notify_on_warning' => $runData->notify_on_warning,
            'notify_on_failure' => $runData->notify_on_failure,
            'notify_override_email' => $runData->notify_override_email,
        ];

        // Send notification
        $result = CloudBackupEmailService::sendRunNotification($run, $job, $client, $emailTemplateId);

        if ($result['status'] === 'success') {
            // Mark as notified
            Capsule::table('s3_cloudbackup_runs')
                ->where('id', $runData->run_id)
                ->update(['notified_at' => date('Y-m-d H:i:s')]);
            
            $notifiedCount++;
            echo "Notified for run {$runData->run_id}: {$result['message']}\n";
            logModuleCall('cloudstorage', 'notify_cron_success', [
                'run_id' => $runData->run_id,
                'message' => $result['message'] ?? '',
            ], '');
        } else {
            echo "Skipped run {$runData->run_id}: {$result['message']}\n";
            logModuleCall('cloudstorage', 'notify_cron_result', [
                'run_id' => $runData->run_id,
                'status' => $result['status'] ?? 'unknown',
                'message' => $result['message'] ?? '',
            ], '');
            if ($result['status'] === 'error') {
                $errorCount++;
            }
        }
    } catch (\Exception $e) {
        $errorCount++;
        echo "Error processing run {$runData->run_id}: " . $e->getMessage() . "\n";
        logModuleCall('cloudstorage', 'notify_cron', ['run_id' => $runData->run_id ?? null], $e->getMessage());
    }
}

echo "Notification cron completed. Notified: {$notifiedCount}, Errors: {$errorCount}\n";

