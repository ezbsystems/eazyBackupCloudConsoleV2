<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class CloudBackupEmailService {

    private static $module = 'cloudstorage';

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
            // Check if email notifications are enabled
            if (empty($templateSetting)) {
                return ['status' => 'skipped', 'message' => 'Email template not configured'];
            }

            // Get template name
            $templateName = self::getTemplateName($templateSetting);
            if (!$templateName) {
                return ['status' => 'error', 'message' => 'Email template not found'];
            }

            // Determine if we should send based on run status and job settings
            $shouldSend = false;
            $notifyEmails = [];

            // Get notification settings (job override or client defaults)
            $notifyOnSuccess = $job['notify_on_success'] ?? 0;
            $notifyOnWarning = $job['notify_on_warning'] ?? 1;
            $notifyOnFailure = $job['notify_on_failure'] ?? 1;

            // Get email addresses (job override or client defaults)
            if (!empty($job['notify_override_email'])) {
                $notifyEmails = self::parseEmailList($job['notify_override_email']);
            } else {
                // Get client default emails
                $settings = Capsule::table('s3_cloudbackup_settings')
                    ->where('client_id', $job['client_id'])
                    ->first();
                
                if ($settings && !empty($settings->default_notify_emails)) {
                    $notifyEmails = self::parseEmailList($settings->default_notify_emails);
                } else {
                    // Fallback to client email
                    $notifyEmails = [$client->email];
                }
            }

            if (empty($notifyEmails)) {
                return ['status' => 'skipped', 'message' => 'No email addresses configured'];
            }

            // Check if we should send based on status
            if ($run['status'] === 'success' && !$notifyOnSuccess) {
                return ['status' => 'skipped', 'message' => 'Success notifications disabled'];
            }
            if ($run['status'] === 'warning' && !$notifyOnWarning) {
                return ['status' => 'skipped', 'message' => 'Warning notifications disabled'];
            }
            if ($run['status'] === 'failed' && !$notifyOnFailure) {
                return ['status' => 'skipped', 'message' => 'Failure notifications disabled'];
            }

            // Prepare merge variables
            $mergeVars = [
                'job_name' => $job['name'],
                'job_id' => $job['id'],
                'run_id' => $run['id'],
                'run_status' => ucfirst($run['status']),
                'source_display_name' => $job['source_display_name'],
                'source_type' => $job['source_type'],
                'dest_bucket_id' => $job['dest_bucket_id'],
                'dest_prefix' => $job['dest_prefix'],
                'started_at' => $run['started_at'] ? date('Y-m-d H:i:s', strtotime($run['started_at'])) : 'N/A',
                'finished_at' => $run['finished_at'] ? date('Y-m-d H:i:s', strtotime($run['finished_at'])) : 'N/A',
                'bytes_transferred' => $run['bytes_transferred'] ?? 0,
                'bytes_total' => $run['bytes_total'] ?? 0,
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

