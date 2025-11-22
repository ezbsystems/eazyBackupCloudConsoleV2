<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class CloudBackupEmailService {

    private static $module = 'cloudstorage';

    /**
     * Build a newline-delimited, sanitized event report for a run using the event formatter.
     *
     * @param int $runId
     * @param int $limit
     * @return string
     */
    private static function buildEventReport($runId, $limit = 500)
    {
        try {
            $events = Capsule::table('s3_cloudbackup_run_events')
                ->where('run_id', (int) $runId)
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get(['ts', 'level', 'message_id', 'params_json']);

            if ($events->isEmpty()) {
                return '';
            }

            $lines = [];
            foreach ($events as $ev) {
                $level = strtoupper($ev->level ?? 'INFO');
                // Render human-friendly message from message_id + params
                $params = [];
                if (!empty($ev->params_json)) {
                    $tmp = json_decode($ev->params_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                        $params = $tmp;
                    }
                }
                $msg = CloudBackupEventFormatter::render((string)($ev->message_id ?? ''), $params);
                // Build "[LEVEL][YYYY-MM-DD HH:MM:SS] message" line
                $ts = $ev->ts ? date('Y-m-d H:i:s', strtotime($ev->ts)) : '';
                $lines[] = sprintf('%s[%s] %s', $level, $ts, $msg);
            }

            return implode("\n", $lines);
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'buildEventReport', ['run_id' => $runId], $e->getMessage());
            return '';
        }
    }

    /**
     * Get email template name from config setting (supports ID or name)
     *
     * @param string $templateSetting Template ID or name from config
     * @return string|null Template name or null if not found
     */
    private static function getTemplateName($templateSetting)
    {
        if (empty($templateSetting)) {
            return null;
        }

        // If numeric, look up by ID
        if (ctype_digit($templateSetting)) {
            try {
                $template = Capsule::table('tblemailtemplates')
                    ->where('id', (int)$templateSetting)
                    ->where('type', 'general')
                    ->first(['name']);
                
                if ($template && !empty($template->name)) {
                    return $template->name;
                }
            } catch (\Exception $e) {
                logModuleCall(self::$module, 'getTemplateName', ['template_id' => $templateSetting], $e->getMessage());
            }
        }

        // Otherwise assume it's a template name
        return $templateSetting;
    }

    /**
     * Send notification email for a backup run
     *
     * @param array $run Run data from database
     * @param array $job Job data from database
     * @param array $client Client data
     * @param string $templateSetting Template ID or name from config
     * @return array Result array with status and message
     */
    public static function sendRunNotification($run, $job, $client, $templateSetting)
    {
        try {
            // Initial context logging
            logModuleCall(self::$module, 'email_notify_start', [
                'run_id' => $run['id'] ?? null,
                'job_id' => $job['id'] ?? null,
                'status' => $run['status'] ?? null,
                'template_setting' => $templateSetting,
            ], '');

            // Check if email notifications are enabled
            if (empty($templateSetting)) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => 'template_not_configured',
                    'run_id' => $run['id'] ?? null,
                    'job_id' => $job['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => 'Email template not configured'];
            }

            // Get template name
            $templateName = self::getTemplateName($templateSetting);
            if (!$templateName) {
                logModuleCall(self::$module, 'email_notify_error', [
                    'reason' => 'template_not_found',
                    'template_setting' => $templateSetting,
                ], '');
                return ['status' => 'error', 'message' => 'Email template not found'];
            }

            // Determine if we should send based on run status and settings
            $notifyEmails = [];

            // Load client-level defaults (for recipients and toggles)
            $defaultNotifyOnSuccess = 1;
            $defaultNotifyOnWarning = 1;
            $defaultNotifyOnFailure = 1;

            $settings = Capsule::table('s3_cloudbackup_settings')
                ->where('client_id', $job['client_id'])
                ->first();

            if ($settings) {
                if (isset($settings->default_notify_emails) && !empty($settings->default_notify_emails)) {
                    $notifyEmails = self::parseEmailList($settings->default_notify_emails);
                }
                if (isset($settings->default_notify_on_success)) {
                    $defaultNotifyOnSuccess = (int) $settings->default_notify_on_success;
                }
                if (isset($settings->default_notify_on_warning)) {
                    $defaultNotifyOnWarning = (int) $settings->default_notify_on_warning;
                }
                if (isset($settings->default_notify_on_failure)) {
                    $defaultNotifyOnFailure = (int) $settings->default_notify_on_failure;
                }
            }

            // Resolve toggles:
            // - If job-level toggles are explicitly set (non-null), they take precedence
            // - Otherwise, fall back to client defaults
            // - Safety: if ALL job-level toggles evaluate to 0 (legacy jobs), prefer client defaults
            $jobNOS = isset($job['notify_on_success']) && $job['notify_on_success'] !== null ? (int) $job['notify_on_success'] : null;
            $jobNOW = isset($job['notify_on_warning']) && $job['notify_on_warning'] !== null ? (int) $job['notify_on_warning'] : null;
            $jobNOF = isset($job['notify_on_failure']) && $job['notify_on_failure'] !== null ? (int) $job['notify_on_failure'] : null;

            // Start with client defaults
            $notifyOnSuccess = $defaultNotifyOnSuccess;
            $notifyOnWarning = $defaultNotifyOnWarning;
            $notifyOnFailure = $defaultNotifyOnFailure;

            // Apply job-level values if present
            if ($jobNOS !== null) $notifyOnSuccess = $jobNOS;
            if ($jobNOW !== null) $notifyOnWarning = $jobNOW;
            if ($jobNOF !== null) $notifyOnFailure = $jobNOF;

            // If all job-level toggles are explicitly zero, assume legacy "unset" and inherit client defaults
            if ($jobNOS === 0 && $jobNOW === 0 && $jobNOF === 0) {
                $notifyOnSuccess = $defaultNotifyOnSuccess;
                $notifyOnWarning = $defaultNotifyOnWarning;
                $notifyOnFailure = $defaultNotifyOnFailure;
                logModuleCall(self::$module, 'email_notify_fallback', [
                    'reason' => 'all_job_toggles_zero_legacy',
                    'client_defaults' => [
                        'success' => $defaultNotifyOnSuccess,
                        'warning' => $defaultNotifyOnWarning,
                        'failure' => $defaultNotifyOnFailure,
                    ],
                ], '');
            }

            // Resolve recipients: job override > client defaults > client primary email
            if (!empty($job['notify_override_email'])) {
                $notifyEmails = self::parseEmailList($job['notify_override_email']);
            }
            if (empty($notifyEmails)) {
                // Fallback to client email
                $notifyEmails = [$client->email];
            }

            // Log resolved recipients and toggles
            logModuleCall(self::$module, 'email_notify_recipients', [
                'run_id' => $run['id'] ?? null,
                'job_id' => $job['id'] ?? null,
                'status' => $run['status'] ?? null,
                'notify_on_success' => (int) $notifyOnSuccess,
                'notify_on_warning' => (int) $notifyOnWarning,
                'notify_on_failure' => (int) $notifyOnFailure,
                'recipients' => $notifyEmails,
                'template' => $templateName,
            ], '');

            if (empty($notifyEmails)) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => 'no_recipients',
                    'run_id' => $run['id'] ?? null,
                    'job_id' => $job['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => 'No email addresses configured'];
            }

            // Check if we should send based on status
            if ($run['status'] === 'success' && !$notifyOnSuccess) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => 'success_disabled',
                    'run_id' => $run['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => 'Success notifications disabled'];
            }
            if ($run['status'] === 'warning' && !$notifyOnWarning) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => 'warning_disabled',
                    'run_id' => $run['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => 'Warning notifications disabled'];
            }
            if ($run['status'] === 'failed' && !$notifyOnFailure) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => 'failure_disabled',
                    'run_id' => $run['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => 'Failure notifications disabled'];
            }
            // Treat cancelled as failure-equivalent for notification toggles by default
            if ($run['status'] === 'cancelled' && !$notifyOnFailure) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => 'cancelled_disabled',
                    'run_id' => $run['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => 'Cancelled notifications disabled'];
            }

            // Prepare merge variables
            // Resolve destination bucket name for email merge vars
            $destBucketName = null;
            try {
                if (!empty($job['dest_bucket_id'])) {
                    $destBucketName = Capsule::table('s3_buckets')
                        ->where('id', (int) $job['dest_bucket_id'])
                        ->value('name');
                }
            } catch (\Exception $e) {
                logModuleCall(self::$module, 'email_lookup_bucket', ['dest_bucket_id' => $job['dest_bucket_id'] ?? null], $e->getMessage());
            }

            $mergeVars = [
                'job_name' => $job['name'],
                'job_id' => $job['id'],
                'run_id' => $run['id'],
                'run_status' => ucfirst($run['status']),
                'source_display_name' => $job['source_display_name'],
                'source_type' => $job['source_type'],
                'dest_bucket_id' => $job['dest_bucket_id'],
                'dest_bucket_name' => $destBucketName ?: ('Bucket #' . ($job['dest_bucket_id'] ?? '')),
                'dest_prefix' => $job['dest_prefix'],
                'started_at' => $run['started_at'] ? date('Y-m-d H:i:s', strtotime($run['started_at'])) : 'N/A',
                'finished_at' => $run['finished_at'] ? date('Y-m-d H:i:s', strtotime($run['finished_at'])) : 'N/A',
                'bytes_transferred' => $run['bytes_transferred'] ?? 0,
                'bytes_total' => $run['bytes_total'] ?? 0,
                'files_transferred' => $run['objects_transferred'] ?? 0,
                'files_total' => $run['objects_total'] ?? 0,
                'progress_pct' => $run['progress_pct'] ?? 0,
                'error_summary' => $run['error_summary'] ?? '',
                'client_id' => $job['client_id'],
                'client_name' => trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? '')),
                'client_email' => $client->email ?? '',
            ];

            // Format bytes for display
            if ($mergeVars['bytes_transferred'] > 0) {
                $mergeVars['bytes_transferred_formatted'] = HelperController::formatSizeUnits($mergeVars['bytes_transferred']);
            } else {
                $mergeVars['bytes_transferred_formatted'] = '0 Bytes';
            }

            if ($mergeVars['bytes_total'] > 0) {
                $mergeVars['bytes_total_formatted'] = HelperController::formatSizeUnits($mergeVars['bytes_total']);
            } else {
                $mergeVars['bytes_total_formatted'] = 'N/A';
            }

            // Calculate duration
            if ($run['started_at'] && $run['finished_at']) {
                $start = strtotime($run['started_at']);
                $end = strtotime($run['finished_at']);
                $duration = $end - $start;
                $hours = floor($duration / 3600);
                $minutes = floor(($duration % 3600) / 60);
                $seconds = $duration % 60;
                $durationStr = '';
                if ($hours > 0) $durationStr .= $hours . 'h ';
                if ($minutes > 0) $durationStr .= $minutes . 'm ';
                $durationStr .= $seconds . 's';
                $mergeVars['duration'] = $durationStr;
            } else {
                $mergeVars['duration'] = 'N/A';
            }

            // Add sanitized event report as a merge field
			$mergeVars['job_log_report'] = self::buildEventReport((int) $run['id']);
			// HTML-preformatted variant with <br> tags
			if (!empty($mergeVars['job_log_report'])) {
				$mergeVars['job_log_report_html'] = nl2br(htmlspecialchars($mergeVars['job_log_report'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
			} else {
				$mergeVars['job_log_report_html'] = '';
			}

            // Send email to each recipient
            $results = [];
            foreach ($notifyEmails as $email) {
                $email = trim($email);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $payload = [
                    'messagename' => $templateName,
                    'id' => $job['client_id'], // Associate with client for merge fields
                    'customvars' => base64_encode(serialize($mergeVars)),
                ];

                // For custom recipients, we may need to use customtype
                // But first try with messagename and id
                if (!function_exists('localAPI')) {
                    logModuleCall(self::$module, 'email_notify_error', [
                        'reason' => 'localAPI_unavailable',
                    ], '');
                    return ['status' => 'error', 'message' => 'localAPI function not available'];
                }

                $response = localAPI('SendEmail', $payload);
                
                $results[] = [
                    'email' => $email,
                    'response' => $response,
                ];

                logModuleCall(self::$module, 'sendRunNotification', [
                    'run_id' => $run['id'],
                    'job_id' => $job['id'],
                    'email' => $email,
                    'template' => $templateName,
                ], json_encode($response));
            }

            // Check if any emails were sent successfully
            $successCount = 0;
            foreach ($results as $result) {
                if (isset($result['response']['result']) && $result['response']['result'] === 'success') {
                    $successCount++;
                }
            }

            if ($successCount > 0) {
                return [
                    'status' => 'success',
                    'message' => "Sent to {$successCount} recipient(s)",
                    'results' => $results,
                ];
            } else {
                logModuleCall(self::$module, 'email_notify_error', [
                    'reason' => 'no_successful_responses',
                    'results' => $results,
                ], '');
                return [
                    'status' => 'error',
                    'message' => 'Failed to send emails',
                    'results' => $results,
                ];
            }
        } catch (\Exception $e) {
            logModuleCall(self::$module, 'sendRunNotification', [
                'run_id' => $run['id'] ?? null,
                'job_id' => $job['id'] ?? null,
            ], $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Parse comma or semicolon separated email list
     *
     * @param string $emailList
     * @return array
     */
    private static function parseEmailList($emailList)
    {
        if (empty($emailList)) {
            return [];
        }

        // Try JSON first (if stored as JSON array)
        $decoded = json_decode($emailList, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_filter(array_map('trim', $decoded));
        }

        // Otherwise parse as comma/semicolon separated
        $emails = preg_split('/[;,]+/', $emailList);
        return array_filter(array_map('trim', $emails));
    }
}

