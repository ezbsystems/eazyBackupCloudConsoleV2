<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

/**
 * Sends per-batch MS365 backup workload report emails when a parent run finalizes.
 */
final class Ms365BackupReportEmailService
{
    private const MODULE = 'ms365backup';

    public static function maybeSendForBatch(string $batchRunId, string $aggregateStatus): void
    {
        try {
            if (Ms365BatchRunRepository::isRestoreBatch($batchRunId)) {
                return;
            }

            $status = strtolower(trim($aggregateStatus));
            if (in_array($status, ['running', 'queued'], true)) {
                return;
            }

            $templateSetting = self::setting('ms365_backup_report_email_template');
            if ($templateSetting === '') {
                return;
            }

            $parent = self::loadParentRun($batchRunId);
            if ($parent === null) {
                return;
            }
            if (!empty($parent['notified_at'])) {
                return;
            }

            $job = self::loadJob((string) ($parent['job_id'] ?? ''));
            if ($job === null) {
                return;
            }

            self::ensureCloudStorageLoaded();
            $client = DBController::getClient((int) ($job['client_id'] ?? 0));
            if ($client === null) {
                return;
            }

            $runStatus = (string) ($parent['status'] ?? $status);
            $notifyDecision = self::shouldNotify($runStatus, $job);
            if (!$notifyDecision['send']) {
                self::markNotified($batchRunId);
                logModuleCall(self::MODULE, 'backup_report_email_skip', [
                    'batch_run_id' => $batchRunId,
                    'reason' => $notifyDecision['reason'],
                ], '');
                return;
            }

            $templateName = self::getTemplateName($templateSetting);
            if ($templateName === null) {
                logModuleCall(self::MODULE, 'backup_report_email_error', [
                    'batch_run_id' => $batchRunId,
                    'reason' => 'template_not_found',
                    'template_setting' => $templateSetting,
                ], '');
                return;
            }

            $children = Ms365AdminJobsRepository::getBatchChildrenDetail($batchRunId);
            $reports = self::buildWorkloadReports($children);
            $backupUsername = self::resolveBackupUsername((int) ($job['backup_user_id'] ?? 0));

            $mergeVars = [
                'backup_username' => $backupUsername,
                'job_name' => (string) ($job['name'] ?? ''),
                'run_status' => self::humanizeRunStatus($runStatus),
                'finished_at' => self::formatTimestamp($parent['finished_at'] ?? null),
                'workload_report_html' => $reports['html'],
                'workload_report' => $reports['text'],
            ];

            if (!function_exists('localAPI')) {
                logModuleCall(self::MODULE, 'backup_report_email_error', [
                    'batch_run_id' => $batchRunId,
                    'reason' => 'localAPI_unavailable',
                ], '');
                return;
            }

            $payload = [
                'messagename' => $templateName,
                'id' => (int) ($job['client_id'] ?? 0),
                'customvars' => base64_encode(serialize($mergeVars)),
            ];
            $response = localAPI('SendEmail', $payload);

            logModuleCall(self::MODULE, 'backup_report_email_send', [
                'batch_run_id' => $batchRunId,
                'client_id' => (int) ($job['client_id'] ?? 0),
                'template' => $templateName,
                'recipients' => $notifyDecision['recipients'],
            ], json_encode($response));

            if (($response['result'] ?? '') === 'success') {
                self::markNotified($batchRunId);
            }
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'backup_report_email_error', [
                'batch_run_id' => $batchRunId,
            ], $e->getMessage());
        }
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array{html: string, text: string}
     */
    public static function buildWorkloadReports(array $children): array
    {
        if ($children === []) {
            return ['html' => '', 'text' => ''];
        }

        $textRows = [];
        $htmlRows = '';
        foreach ($children as $child) {
            $workload = (string) ($child['workload_label'] ?? '');
            $status = (string) ($child['status'] ?? '');
            $attempts = self::formatAttempts($child);
            $error = self::formatChildError($child);

            $textRows[] = self::formatPlainTextRow($workload, $status, $attempts, $error);
            $htmlRows .= '<tr>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . self::escapeHtml($workload) . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . self::escapeHtml($status) . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . self::escapeHtml($attempts) . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . self::escapeHtml($error) . '</td>'
                . '</tr>';
        }

        $html = '<table style="border-collapse:collapse;width:100%;font-size:14px;">'
            . '<thead><tr>'
            . '<th style="padding:6px 10px;border:1px solid #ddd;text-align:left;background:#f5f5f5;">Workload</th>'
            . '<th style="padding:6px 10px;border:1px solid #ddd;text-align:left;background:#f5f5f5;">Status</th>'
            . '<th style="padding:6px 10px;border:1px solid #ddd;text-align:left;background:#f5f5f5;">Attempts</th>'
            . '<th style="padding:6px 10px;border:1px solid #ddd;text-align:left;background:#f5f5f5;">Error</th>'
            . '</tr></thead><tbody>' . $htmlRows . '</tbody></table>';

        return [
            'html' => $html,
            'text' => implode("\n", $textRows),
        ];
    }

    /** @param array<string, mixed> $child */
    public static function formatChildError(array $child): string
    {
        $parts = [];
        $runError = trim((string) ($child['error_message'] ?? ''));
        $queueError = trim((string) ($child['queue_error'] ?? $child['last_error'] ?? ''));
        if ($runError !== '') {
            $parts[] = $runError;
        }
        if ($queueError !== '' && $queueError !== $runError) {
            $parts[] = 'Queue: ' . $queueError;
        }

        $skipped = $child['workload_skipped'] ?? [];
        if (is_array($skipped) && $skipped !== []) {
            $skipParts = [];
            foreach ($skipped as $key => $reason) {
                $skipParts[] = (string) $key . '=' . (string) $reason;
            }
            if ($skipParts !== []) {
                $parts[] = 'Skipped: ' . implode(', ', $skipParts);
            }
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /** @param array<string, mixed> $child */
    public static function formatAttempts(array $child): string
    {
        $attempts = (int) ($child['attempts'] ?? 0);
        $maxAttempts = (int) ($child['max_attempts'] ?? 3);
        $text = $attempts . '/' . $maxAttempts;
        $queueStatus = trim((string) ($child['queue_status'] ?? ''));
        if ($queueStatus !== '') {
            $text .= ' (' . $queueStatus . ')';
        }

        return $text;
    }

    /** @return array<string, mixed>|null */
    private static function loadParentRun(string $batchRunId): ?array
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return null;
        }

        $hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
        $query = Capsule::table('s3_cloudbackup_runs');
        if ($hasRunIdPk) {
            $query->whereRaw('run_id = UUID_TO_BIN(\'' . addslashes(strtolower($batchRunId)) . '\')');
        } else {
            $query->where('id', $batchRunId);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $arr = (array) $row;
        if ($hasRunIdPk && isset($arr['run_id']) && is_string($arr['run_id']) && strlen($arr['run_id']) === 16) {
            $arr['run_id'] = self::binaryToUuid($arr['run_id']);
        }
        if (isset($arr['job_id']) && is_string($arr['job_id']) && strlen($arr['job_id']) === 16) {
            $arr['job_id'] = self::binaryToUuid($arr['job_id']);
        }

        return $arr;
    }

    /** @return array<string, mixed>|null */
    private static function loadJob(string $jobId): ?array
    {
        if (!self::isUuid($jobId) || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return null;
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $query = Capsule::table('s3_cloudbackup_jobs');
        if ($hasJobIdPk) {
            $query->whereRaw('job_id = UUID_TO_BIN(\'' . addslashes(strtolower($jobId)) . '\')');
        } else {
            $query->where('id', $jobId);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $arr = (array) $row;
        if ($hasJobIdPk && isset($arr['job_id']) && is_string($arr['job_id']) && strlen($arr['job_id']) === 16) {
            $arr['job_id'] = self::binaryToUuid($arr['job_id']);
        }

        return $arr;
    }

    private static function resolveBackupUsername(int $backupUserId): string
    {
        if ($backupUserId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
            return '—';
        }

        $row = Capsule::table('s3_backup_users')
            ->where('id', $backupUserId)
            ->first(['username', 'public_id']);
        if ($row === null) {
            return '—';
        }

        $username = trim((string) ($row->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        $publicId = trim((string) ($row->public_id ?? ''));

        return $publicId !== '' ? $publicId : '—';
    }

    /**
     * @param array<string, mixed> $job
     * @return array{send: bool, reason: string, recipients: list<string>}
     */
    private static function shouldNotify(string $runStatus, array $job): array
    {
        $defaultNotifyOnSuccess = 1;
        $defaultNotifyOnWarning = 1;
        $defaultNotifyOnFailure = 1;
        $notifyEmails = [];

        $settings = Capsule::table('s3_cloudbackup_settings')
            ->where('client_id', (int) ($job['client_id'] ?? 0))
            ->first();
        if ($settings !== null) {
            if (!empty($settings->default_notify_emails)) {
                $notifyEmails = self::parseEmailList((string) $settings->default_notify_emails);
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

        $jobNOS = isset($job['notify_on_success']) && $job['notify_on_success'] !== null
            ? (int) $job['notify_on_success'] : null;
        $jobNOW = isset($job['notify_on_warning']) && $job['notify_on_warning'] !== null
            ? (int) $job['notify_on_warning'] : null;
        $jobNOF = isset($job['notify_on_failure']) && $job['notify_on_failure'] !== null
            ? (int) $job['notify_on_failure'] : null;

        $notifyOnSuccess = $defaultNotifyOnSuccess;
        $notifyOnWarning = $defaultNotifyOnWarning;
        $notifyOnFailure = $defaultNotifyOnFailure;
        if ($jobNOS !== null) {
            $notifyOnSuccess = $jobNOS;
        }
        if ($jobNOW !== null) {
            $notifyOnWarning = $jobNOW;
        }
        if ($jobNOF !== null) {
            $notifyOnFailure = $jobNOF;
        }
        if ($jobNOS === 0 && $jobNOW === 0 && $jobNOF === 0) {
            $notifyOnSuccess = $defaultNotifyOnSuccess;
            $notifyOnWarning = $defaultNotifyOnWarning;
            $notifyOnFailure = $defaultNotifyOnFailure;
        }

        if (!empty($job['notify_override_email'])) {
            $notifyEmails = self::parseEmailList((string) $job['notify_override_email']);
        }
        if ($notifyEmails === []) {
            self::ensureCloudStorageLoaded();
            $client = DBController::getClient((int) ($job['client_id'] ?? 0));
            if ($client !== null && !empty($client->email)) {
                $notifyEmails = [(string) $client->email];
            }
        }

        if ($notifyEmails === []) {
            return ['send' => false, 'reason' => 'no_recipients', 'recipients' => []];
        }

        $status = strtolower($runStatus);
        if ($status === 'success' && !$notifyOnSuccess) {
            return ['send' => false, 'reason' => 'success_disabled', 'recipients' => $notifyEmails];
        }
        if (in_array($status, ['partial_success', 'warning'], true) && !$notifyOnWarning) {
            return ['send' => false, 'reason' => 'warning_disabled', 'recipients' => $notifyEmails];
        }
        if ($status === 'failed' && !$notifyOnFailure) {
            return ['send' => false, 'reason' => 'failure_disabled', 'recipients' => $notifyEmails];
        }
        if ($status === 'cancelled' && !$notifyOnFailure) {
            return ['send' => false, 'reason' => 'cancelled_disabled', 'recipients' => $notifyEmails];
        }

        return ['send' => true, 'reason' => '', 'recipients' => $notifyEmails];
    }

    private static function markNotified(string $batchRunId): void
    {
        if (!Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'notified_at')) {
            return;
        }

        $query = Capsule::table('s3_cloudbackup_runs');
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id')) {
            $query->whereRaw('run_id = UUID_TO_BIN(\'' . addslashes(strtolower($batchRunId)) . '\')');
        } else {
            $query->where('id', $batchRunId);
        }
        $query->update(['notified_at' => date('Y-m-d H:i:s')]);
    }

    private static function getTemplateName(string $templateSetting): ?string
    {
        if ($templateSetting === '') {
            return null;
        }

        if (ctype_digit($templateSetting)) {
            try {
                $template = Capsule::table('tblemailtemplates')
                    ->where('id', (int) $templateSetting)
                    ->where('type', 'general')
                    ->first(['name']);
                if ($template !== null && !empty($template->name)) {
                    return (string) $template->name;
                }
            } catch (\Throwable $e) {
                logModuleCall(self::MODULE, 'getTemplateName', ['template_id' => $templateSetting], $e->getMessage());
            }

            return null;
        }

        return $templateSetting;
    }

    private static function setting(string $key, string $default = ''): string
    {
        try {
            $value = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', $key)
                ->value('value');

            return $value !== null ? (string) $value : $default;
        } catch (\Throwable $_) {
            return $default;
        }
    }

    /** @return list<string> */
    private static function parseEmailList(string $emailList): array
    {
        if ($emailList === '') {
            return [];
        }

        $decoded = json_decode($emailList, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }

        $emails = preg_split('/[;,]+/', $emailList) ?: [];

        return array_values(array_filter(array_map('trim', $emails)));
    }

    private static function humanizeRunStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'partial_success' => 'Partial success',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private static function formatTimestamp(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        $ts = strtotime((string) $value);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : 'N/A';
    }

    private static function formatPlainTextRow(string $workload, string $status, string $attempts, string $error): string
    {
        $workloadCol = self::padPlainColumn($workload, 36);
        $statusCol = self::padPlainColumn($status, 14);
        $attemptsCol = self::padPlainColumn($attempts, 16);

        return $workloadCol . ' | ' . $statusCol . ' | ' . $attemptsCol . ' | ' . $error;
    }

    private static function padPlainColumn(string $value, int $width): string
    {
        if (strlen($value) >= $width) {
            return substr($value, 0, $width);
        }

        return str_pad($value, $width);
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function ensureCloudStorageLoaded(): void
    {
        if (!function_exists('cloudstorage_load_ms365backup')) {
            $bootstrap = dirname(__DIR__, 3) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
            if (is_file($bootstrap)) {
                require_once $bootstrap;
            }
        }
        if (function_exists('cloudstorage_load_ms365backup')) {
            cloudstorage_load_ms365backup();
        }
    }

    private static function isUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        );
    }

    private static function binaryToUuid(string $binary): string
    {
        $hex = bin2hex($binary);
        if (strlen($hex) !== 32) {
            return $hex;
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
