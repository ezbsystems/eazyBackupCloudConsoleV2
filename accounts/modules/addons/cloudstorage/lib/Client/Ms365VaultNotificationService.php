<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Email notifications for MS365 vault lifecycle events.
 */
final class Ms365VaultNotificationService
{
    private const MODULE = 'cloudstorage';

    /**
     * @param array<string, mixed> $vaultResult from softDeleteVaultForJob
     */
    public static function sendJobDeletedNotification(
        int $clientId,
        string $jobName,
        array $vaultResult,
        ?int $backupUserId = null
    ): bool {
        $templateId = trim((string) AgentIngestSupport::getModuleSetting('ms365_vault_delete_email_template', ''));
        if ($templateId === '') {
            return false;
        }

        $templates = function_exists('cloudstorage_get_email_templates') ? cloudstorage_get_email_templates() : [];
        $templateName = $templates[$templateId] ?? null;
        if ($templateName === null || $templateName === '') {
            return false;
        }

        $recycleUrl = '';
        if ($backupUserId !== null && $backupUserId > 0) {
            $publicId = Capsule::table('s3_backup_users')->where('id', $backupUserId)->value('public_id');
            $routeId = $publicId !== null && trim((string) $publicId) !== '' ? (string) $publicId : (string) $backupUserId;
            $recycleUrl = 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id='
                . rawurlencode($routeId) . '#vaults';
        }

        try {
            $payload = [
                'job_name' => $jobName,
                'vault_name' => (string) ($vaultResult['bucket_name'] ?? ''),
                'grace_days' => (int) ($vaultResult['grace_days'] ?? Ms365VaultLifecycleService::getGraceDays()),
                'recycle_teardown_at' => (string) ($vaultResult['recycle_teardown_at'] ?? ''),
                'recycle_bin_url' => $recycleUrl,
            ];
            $sendEmailParams = [
                'messagename' => $templateName,
                'id' => $clientId,
                'customvars' => base64_encode(serialize($payload)),
            ];
            $adminUser = Capsule::table('tbladmins')->orderBy('id', 'asc')->value('username') ?: 'admin';
            $emailResult = localAPI('SendEmail', $sendEmailParams, $adminUser);
            $ok = is_array($emailResult) && (($emailResult['result'] ?? '') === 'success');
            logModuleCall(self::MODULE, 'ms365_vault_delete_SendEmail', $sendEmailParams, $emailResult);

            return $ok;
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'ms365_vault_delete_SendEmail_exception', ['client_id' => $clientId], $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array{id: int, tid: string}|null
     */
    public static function createEarlyDeleteSupportTicket(
        int $clientId,
        string $bucketName,
        int $bucketId,
        int $requestId,
        array $context = []
    ): ?array {
        $reason = trim((string) ($context['reason'] ?? ''));
        $backupUserId = (int) ($context['backup_user_id'] ?? 0);
        $backupUserLabel = '—';
        if ($backupUserId > 0) {
            $row = Capsule::table('s3_backup_users')
                ->where('id', $backupUserId)
                ->first(['username', 'public_id']);
            if ($row !== null) {
                $username = trim((string) ($row->username ?? ''));
                $backupUserLabel = $username !== '' ? $username : (string) ($row->public_id ?? $backupUserId);
            }
        }

        $teardownAt = '';
        $bucket = Capsule::table('s3_buckets')->where('id', $bucketId)->first(['recycle_teardown_at']);
        if ($bucket !== null && !empty($bucket->recycle_teardown_at)) {
            $teardownAt = (string) $bucket->recycle_teardown_at;
        }

        $subject = 'MS365 vault early deletion request: ' . $bucketName;
        $lines = [
            'Hello eazyBackup support,',
            '',
            'I am requesting early permanent deletion of a Microsoft 365 backup vault that is currently in the recycle bin.',
            '',
            '- Vault: ' . $bucketName,
            '- Bucket ID: ' . $bucketId,
            '- Request ID: ' . $requestId,
            '- Backup user: ' . $backupUserLabel,
        ];
        if ($teardownAt !== '') {
            $lines[] = '- Scheduled automatic teardown: ' . $teardownAt;
        }
        if ($reason !== '') {
            $lines[] = '';
            $lines[] = 'Reason:';
            $lines[] = $reason;
        }
        $lines[] = '';
        $lines[] = 'Please review and approve permanent deletion before the grace period ends.';

        try {
            $adminUser = Capsule::table('tbladmins')->orderBy('id', 'asc')->value('username') ?: 'admin';
            $ticketData = [
                'deptid' => 1,
                'subject' => $subject,
                'message' => implode("\n", $lines),
                'clientid' => $clientId,
                'priority' => 'Medium',
                'markdown' => true,
            ];
            $ticketResponse = localAPI('OpenTicket', $ticketData, $adminUser);
            logModuleCall(self::MODULE, 'ms365_early_delete_OpenTicket', $ticketData, $ticketResponse);
            if (is_array($ticketResponse) && ($ticketResponse['result'] ?? '') === 'success') {
                return [
                    'id' => (int) ($ticketResponse['id'] ?? 0),
                    'tid' => (string) ($ticketResponse['tid'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'ms365_early_delete_OpenTicket_exception', ['client_id' => $clientId], $e->getMessage());
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function sendEarlyDeleteOpsNotification(
        int $clientId,
        string $bucketName,
        int $requestId,
        array $context = []
    ): void {
        $internalEmail = trim((string) AgentIngestSupport::getModuleSetting('ms365_vault_early_delete_ops_email', ''));
        if ($internalEmail === '') {
            return;
        }

        try {
            $body = "A customer requested early deletion of an MS365 backup vault.\n\n"
                . "Bucket: {$bucketName}\n"
                . "Client ID: {$clientId}\n"
                . "Request ID: {$requestId}\n"
                . "Requested by User ID: " . (int) ($context['actor_client_user_id'] ?? 0) . "\n"
                . "Time (UTC): " . gmdate('Y-m-d H:i:s') . " UTC\n"
                . "IP: " . ($context['request_ip'] ?? '—') . "\n\n"
                . "Process manually via admin bucket delete / deprovision tooling, then mark request completed.";
            $subject = 'MS365 vault early deletion requested: ' . $bucketName;
            $resp = localAPI('SendEmail', [
                'customtype' => 'general',
                'customsubject' => $subject,
                'custommessage' => nl2br(htmlentities($body)),
                'to' => $internalEmail,
            ]);
            logModuleCall(self::MODULE, 'ms365_early_delete_ops_email', ['to' => $internalEmail], $resp);
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'ms365_early_delete_ops_email_exception', ['to' => $internalEmail], $e->getMessage());
        }
    }
}
