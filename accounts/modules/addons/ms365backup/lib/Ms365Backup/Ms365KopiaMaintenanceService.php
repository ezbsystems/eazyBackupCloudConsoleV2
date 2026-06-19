<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * @deprecated Use Ms365KopiaRepoOperationService. Kept for backward-compatible call sites.
 */
final class Ms365KopiaMaintenanceService
{
    /** @return array{scheduled: int, skipped: int} */
    public static function scheduleDueMaintenance(): array
    {
        return Ms365KopiaRepoOperationService::scheduleDueRetentionAndMaintenance();
    }

    /**
     * @param array<string, mixed> $tenantRow
     */
    public static function scheduleForTenantIfDue(array $tenantRow, ?int $cutoffTs = null): bool
    {
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return false;
        }

        $clientId = (int) ($tenantRow['whmcs_client_id'] ?? 0);
        $backupUserId = (int) ($tenantRow['backup_user_id'] ?? 0);
        if ($clientId <= 0) {
            return false;
        }

        $q = \WHMCS\Database\Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('status', 'active');
        if ($backupUserId > 0 && \WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            $q->where('backup_user_id', $backupUserId);
        }

        $scheduled = false;
        foreach ($q->get(['job_id']) as $job) {
            $hex = is_string($job->job_id ?? null) ? bin2hex($job->job_id) : '';
            if (strlen($hex) !== 32) {
                continue;
            }
            $jobUuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
            if (Ms365KopiaRepoOperationService::scheduleMaintenanceForJob($jobUuid, $tenantRow, $cutoffTs)) {
                $scheduled = true;
            }
        }

        return $scheduled;
    }

    public static function claimNextForWorker(string $nodeId): ?array
    {
        return Ms365KopiaRepoOperationService::claimNextForWorker($nodeId);
    }

    public static function markComplete(int $operationId, string $status = 'success'): void
    {
        Ms365KopiaRepoOperationService::markComplete($operationId, $status);
    }
}
