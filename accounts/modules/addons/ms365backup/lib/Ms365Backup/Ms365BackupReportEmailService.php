<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Mail\Emailer;
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
            $notifyDecision = \WHMCS\Module\Addon\CloudStorage\Client\CloudBackupNotificationPolicy::resolve($job, $runStatus, $client);
            if (!$notifyDecision['send']) {
                if (self::tryClaimNotification($batchRunId)) {
                    logModuleCall(self::MODULE, 'backup_report_email_skip', [
                        'batch_run_id' => $batchRunId,
                        'reason' => $notifyDecision['reason'],
                    ], '');
                }
                return;
            }

            $templateRow = self::loadTemplateRow($templateSetting);
            if ($templateRow === null) {
                logModuleCall(self::MODULE, 'backup_report_email_error', [
                    'batch_run_id' => $batchRunId,
                    'reason' => 'template_not_found',
                    'template_setting' => $templateSetting,
                ], '');
                return;
            }

            if (!self::tryClaimNotification($batchRunId)) {
                return;
            }

            $children = Ms365AdminJobsRepository::getBatchChildrenDetail($batchRunId);
            $reports = self::buildWorkloadReports($children);
            $backupUsername = self::resolveBackupUsername((int) ($job['backup_user_id'] ?? 0));
            $recipients = self::normalizeRecipients($notifyDecision['recipients']);
            if ($recipients === []) {
                self::clearNotified($batchRunId);
                return;
            }

            $mergeVars = self::buildMergeVars(
                $job,
                $parent,
                $client,
                $backupUsername,
                $runStatus,
                $reports,
            );

            if (!class_exists(Emailer::class)) {
                self::clearNotified($batchRunId);
                logModuleCall(self::MODULE, 'backup_report_email_error', [
                    'batch_run_id' => $batchRunId,
                    'reason' => 'mailer_unavailable',
                ], '');
                return;
            }

            $sent = self::sendTemplateToRecipients(
                (string) ($templateRow->name ?? ''),
                (int) ($job['client_id'] ?? 0),
                $mergeVars,
                $recipients,
            );

            logModuleCall(self::MODULE, 'backup_report_email_send', [
                'batch_run_id' => $batchRunId,
                'client_id' => (int) ($job['client_id'] ?? 0),
                'template' => (string) ($templateRow->name ?? ''),
                'recipients' => $recipients,
            ], $sent ? 'success' : 'fail');

            if (!$sent) {
                self::clearNotified($batchRunId);
            }
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'backup_report_email_error', [
                'batch_run_id' => $batchRunId,
            ], $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $parent
     * @param array{html: string, text: string} $reports
     * @return array<string, string>
     */
    public static function buildMergeVars(
        array $job,
        array $parent,
        object $client,
        string $backupUsername,
        string $runStatus,
        array $reports,
    ): array {
        return [
            'backup_username' => $backupUsername,
            'job_name' => (string) ($job['name'] ?? ''),
            'run_status' => self::humanizeRunStatus($runStatus),
            'finished_at' => self::formatTimestamp($parent['finished_at'] ?? null),
            'workload_report_html' => $reports['html'],
            'workload_report' => $reports['text'],
            'client_first_name' => trim((string) ($client->firstname ?? '')),
            'client_last_name' => trim((string) ($client->lastname ?? '')),
            'client_name' => trim((string) (($client->firstname ?? '') . ' ' . ($client->lastname ?? ''))),
            'client_email' => trim((string) ($client->email ?? '')),
            'client_company_name' => trim((string) ($client->companyname ?? '')),
        ];
    }

    /**
     * @param array<string, string> $mergeVars
     * @return array{subject: string, message: string}
     */
    public static function renderTemplateContent(string $subject, string $message, array $mergeVars): array
    {
        $subject = html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [
            'subject' => self::applyMergeFields($subject, $mergeVars),
            'message' => self::applyMergeFields($message, $mergeVars),
        ];
    }

    /** @param list<string> $recipients @return list<string> */
    public static function normalizeRecipients(array $recipients): array
    {
        $seen = [];
        $normalized = [];
        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) $recipient));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $normalized[] = $email;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $mergeVars
     * @param list<string> $recipients
     */
    private static function sendTemplateToRecipients(
        string $templateName,
        int $clientId,
        array $mergeVars,
        array $recipients,
    ): bool {
        if ($templateName === '' || $clientId <= 0 || $recipients === []) {
            return false;
        }

        $emailer = Emailer::factoryByTemplate($templateName, $clientId);
        self::setNonClientEmail($emailer, true);

        $message = $emailer->getMessage();
        foreach (['to', 'cc', 'bcc'] as $type) {
            $message->clearRecipients($type);
        }
        foreach ($recipients as $recipient) {
            $message->addRecipient('to', $recipient, '');
        }

        foreach ($mergeVars as $key => $value) {
            $emailer->assign((string) $key, $value);
        }

        try {
            $emailer->send();

            return true;
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'backup_report_email_error', [
                'reason' => 'emailer_send_failed',
                'template' => $templateName,
                'client_id' => $clientId,
                'recipients' => $recipients,
            ], $e->getMessage());

            return false;
        }
    }

    private static function setNonClientEmail(Emailer $emailer, bool $enabled): void
    {
        $reflection = new \ReflectionObject($emailer);
        $property = $reflection->getProperty('isNonClientEmail');
        $property->setAccessible(true);
        $property->setValue($emailer, $enabled);
    }

    /** @param array<string, string> $mergeVars */
    private static function applyMergeFields(string $template, array $mergeVars): string
    {
        $rendered = $template;
        foreach ($mergeVars as $key => $value) {
            $rendered = str_replace('{$' . $key . '}', $value, $rendered);
        }

        return $rendered;
    }

    /** @return object|null */
    private static function loadTemplateRow(string $templateSetting): ?object
    {
        if ($templateSetting === '') {
            return null;
        }

        try {
            $query = Capsule::table('tblemailtemplates')->where('type', 'general');
            if (ctype_digit($templateSetting)) {
                $query->where('id', (int) $templateSetting);
            } else {
                $query->where('name', $templateSetting);
            }

            return $query->first(['id', 'name', 'subject', 'message']);
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'loadTemplateRow', ['template_setting' => $templateSetting], $e->getMessage());

            return null;
        }
    }

    private static function tryClaimNotification(string $batchRunId): bool
    {
        if (!Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'notified_at')) {
            return true;
        }

        $updated = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = UUID_TO_BIN(\'' . addslashes(strtolower($batchRunId)) . '\')')
            ->whereNull('notified_at')
            ->update(['notified_at' => date('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    private static function clearNotified(string $batchRunId): void
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
        $query->update(['notified_at' => null]);
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
