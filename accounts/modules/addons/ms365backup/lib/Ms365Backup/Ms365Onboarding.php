<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Computed onboarding steps for e3 Microsoft 365 backup (not agent onboarding).
 */
final class Ms365Onboarding
{
    /** @return array<string, mixed> */
    public static function computeForBackupUser(int $clientId, int $backupUserId): array
    {
        $record = $backupUserId > 0
            ? TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            : TenantRecordRepository::getPrimaryForClient($clientId);
        $connected = $record !== null && ($record['connection_status'] ?? '') === 'connected';
        $inventory = CustomerInventoryService::summaryForBackupUser($clientId, $backupUserId);
        $hasInventory = (bool) ($inventory['has_inventory'] ?? false);
        $successRuns = self::successfulRunCount($clientId, $backupUserId);

        $steps = [
            'connect' => [
                'complete' => $connected,
                'label' => 'Connect Microsoft 365',
            ],
            'inventory' => [
                'complete' => $hasInventory,
                'label' => 'Refresh tenant inventory',
            ],
            'first_backup' => [
                'complete' => $successRuns > 0,
                'label' => 'Complete first backup',
                'success_run_count' => $successRuns,
            ],
        ];

        $completed = (int) $connected + (int) $hasInventory + (int) ($successRuns > 0);

        return [
            'steps' => $steps,
            'completed_count' => $completed,
            'total_count' => 3,
            'all_complete' => $completed >= 3,
            'can_start_backup' => $connected && $hasInventory,
        ];
    }

    /** @return array<string, mixed> */
    public static function compute(int $clientId): array
    {
        return self::computeForBackupUser($clientId, 0);
    }

    private static function successfulRunCount(int $clientId, int $backupUserId = 0): int
    {
        if (!class_exists(\WHMCS\Database\Capsule::class) || $clientId <= 0) {
            return 0;
        }

        $q = \WHMCS\Database\Capsule::table('ms365_backup_runs')
            ->where('whmcs_client_id', $clientId)
            ->where('status', 'success');
        if ($backupUserId > 0 && \WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'backup_user_id')) {
            $q->where('backup_user_id', $backupUserId);
        }

        return (int) $q->count();
    }
}
