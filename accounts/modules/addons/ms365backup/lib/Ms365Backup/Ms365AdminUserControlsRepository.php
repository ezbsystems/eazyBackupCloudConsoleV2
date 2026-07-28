<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Admin lock rows for MS365 backup users (suspend / unsuspend).
 */
final class Ms365AdminUserControlsRepository
{
    public const CUSTOMER_BLOCKED_MESSAGE = 'This backup user is suspended by an administrator. Contact support to restore service.';

    public static function ensureTable(): void
    {
        if (Capsule::schema()->hasTable('ms365_admin_user_controls')) {
            return;
        }

        $sqlFile = dirname(__DIR__, 2) . '/sql/upgrade_admin_users_controls.sql';
        if (!is_file($sqlFile)) {
            throw new \RuntimeException('Missing upgrade_admin_users_controls.sql');
        }

        $sql = (string) file_get_contents($sqlFile);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                Capsule::connection()->statement($statement);
            }
        }
    }

    public static function isAdminSuspended(int $backupUserId): bool
    {
        if ($backupUserId <= 0 || !Capsule::schema()->hasTable('ms365_admin_user_controls')) {
            return false;
        }

        return Capsule::table('ms365_admin_user_controls')
            ->where('backup_user_id', $backupUserId)
            ->whereNotNull('admin_suspended_at')
            ->exists();
    }

    public static function assertCustomerAllowed(int $backupUserId): void
    {
        if (self::isAdminSuspended($backupUserId)) {
            throw new \RuntimeException(self::CUSTOMER_BLOCKED_MESSAGE);
        }
    }

    /** @return array<string, mixed>|null */
    public static function getRow(int $backupUserId): ?array
    {
        if ($backupUserId <= 0 || !Capsule::schema()->hasTable('ms365_admin_user_controls')) {
            return null;
        }

        $row = Capsule::table('ms365_admin_user_controls')
            ->where('backup_user_id', $backupUserId)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * @param array<string, string> $priorJobStatuses
     */
    public static function writeSuspension(
        int $backupUserId,
        int $clientId,
        int $adminId,
        array $priorJobStatuses,
        ?string $notes = null
    ): void {
        self::ensureTable();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'client_id' => $clientId,
            'admin_suspended_at' => $now,
            'admin_suspended_by' => $adminId > 0 ? $adminId : null,
            'prior_job_statuses_json' => json_encode($priorJobStatuses, JSON_UNESCAPED_SLASHES),
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'updated_at' => $now,
        ];

        if (self::getRow($backupUserId) !== null) {
            Capsule::table('ms365_admin_user_controls')
                ->where('backup_user_id', $backupUserId)
                ->update($payload);
            return;
        }

        Capsule::table('ms365_admin_user_controls')->insert(array_merge($payload, [
            'backup_user_id' => $backupUserId,
            'created_at' => $now,
        ]));
    }

    public static function clearSuspension(int $backupUserId): void
    {
        if ($backupUserId <= 0 || !Capsule::schema()->hasTable('ms365_admin_user_controls')) {
            return;
        }

        Capsule::table('ms365_admin_user_controls')
            ->where('backup_user_id', $backupUserId)
            ->delete();
    }

    /** @return array<string, string> */
    public static function decodePriorJobStatuses(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $jobId => $status) {
            $jobId = trim((string) $jobId);
            $status = trim((string) $status);
            if ($jobId !== '' && $status !== '') {
                $out[$jobId] = $status;
            }
        }

        return $out;
    }
}
