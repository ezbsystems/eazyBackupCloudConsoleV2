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
            $notifyDecision = CloudBackupNotificationPolicy::resolve($job, (string) ($run['status'] ?? ''), $client);

            logModuleCall(self::$module, 'email_notify_recipients', [
                'run_id' => $run['id'] ?? null,
                'job_id' => $job['id'] ?? null,
                'status' => $run['status'] ?? null,
                'notify_on_success' => (int) $notifyDecision['notify_on_success'],
                'notify_on_warning' => (int) $notifyDecision['notify_on_warning'],
                'notify_on_failure' => (int) $notifyDecision['notify_on_failure'],
                'recipients' => $notifyDecision['recipients'],
                'template' => $templateName,
            ], '');

            if (!$notifyDecision['send']) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => $notifyDecision['reason'],
                    'run_id' => $run['id'] ?? null,
                    'job_id' => $job['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => self::skipMessageForReason($notifyDecision['reason'])];
            }

            $notifyEmails = $notifyDecision['recipients'];

            if (empty($notifyEmails)) {
                logModuleCall(self::$module, 'email_notify_skip', [
                    'reason' => 'no_recipients',
                    'run_id' => $run['id'] ?? null,
                    'job_id' => $job['id'] ?? null,
                ], '');
                return ['status' => 'skipped', 'message' => 'No email addresses configured'];
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
     * Map policy skip reason to a user-facing message.
     */
    private static function skipMessageForReason(string $reason): string
    {
        $map = [
            'notifications_disabled' => 'Backup user notifications disabled',
            'no_recipients' => 'No email addresses configured',
            'success_disabled' => 'Success notifications disabled',
            'warning_disabled' => 'Warning notifications disabled',
            'failure_disabled' => 'Failure notifications disabled',
            'cancelled_disabled' => 'Cancelled notifications disabled',
        ];

        return $map[$reason] ?? 'Notification skipped';
    }
}

