<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserLifecycleService;

require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Client/E3BackupUserLifecycleService.php';

/**
 * Admin lifecycle actions for MS365 backup users.
 */
final class Ms365AdminUsersService
{
    /**
     * @return array<string, mixed>
     */
    public static function suspend(int $backupUserId, int $adminId, ?string $notes = null): array
    {
        $user = self::loadBackupUser($backupUserId);
        if ($user === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        $clientId = (int) ($user['client_id'] ?? 0);
        if (Ms365AdminUserControlsRepository::isAdminSuspended($backupUserId)) {
            throw new \RuntimeException('User is already admin-suspended.');
        }

        $priorStatuses = self::pauseMs365Jobs($clientId, $backupUserId);
        Ms365AdminUserControlsRepository::writeSuspension(
            $backupUserId,
            $clientId,
            $adminId,
            $priorStatuses,
            $notes
        );

        $serviceId = Ms365BillingService::resolveServiceIdForBackupUser($clientId, $backupUserId);
        if ($serviceId > 0) {
            Ms365BillingTrial::setServiceStatus(
                $serviceId,
                'Suspended',
                'MS365 Backup suspended by administrator.'
            );
            self::sendServiceEmail($serviceId, 'Service Suspension Notification');
        }

        logModuleCall('ms365backup', 'admin_user_suspend', [
            'backup_user_id' => $backupUserId,
            'client_id' => $clientId,
            'admin_id' => $adminId,
            'jobs_paused' => count($priorStatuses),
        ], 'Suspended', [], []);

        return [
            'backup_user_id' => $backupUserId,
            'status' => 'Suspended',
            'jobs_paused' => count($priorStatuses),
            'service_id' => $serviceId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function unsuspend(int $backupUserId, int $adminId): array
    {
        $user = self::loadBackupUser($backupUserId);
        if ($user === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        $clientId = (int) ($user['client_id'] ?? 0);
        $row = Ms365AdminUserControlsRepository::getRow($backupUserId);
        if ($row === null || empty($row['admin_suspended_at'])) {
            throw new \RuntimeException('User is not admin-suspended.');
        }

        $priorStatuses = Ms365AdminUserControlsRepository::decodePriorJobStatuses(
            isset($row['prior_job_statuses_json']) ? (string) $row['prior_job_statuses_json'] : null
        );
        self::restoreMs365Jobs($clientId, $backupUserId, $priorStatuses);
        Ms365AdminUserControlsRepository::clearSuspension($backupUserId);

        $serviceId = Ms365BillingService::resolveServiceIdForBackupUser($clientId, $backupUserId);
        if ($serviceId > 0) {
            Ms365BillingTrial::setServiceStatus($serviceId, 'Active');
            self::sendServiceEmail($serviceId, 'Service Unsuspension Notification');
        }

        logModuleCall('ms365backup', 'admin_user_unsuspend', [
            'backup_user_id' => $backupUserId,
            'client_id' => $clientId,
            'admin_id' => $adminId,
            'jobs_restored' => count($priorStatuses),
        ], 'Unsuspended', [], []);

        return [
            'backup_user_id' => $backupUserId,
            'status' => 'Active',
            'jobs_restored' => count($priorStatuses),
            'service_id' => $serviceId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function deprovision(int $backupUserId, string $confirmPhrase, int $adminId): array
    {
        $user = self::loadBackupUser($backupUserId);
        if ($user === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        $clientId = (int) ($user['client_id'] ?? 0);
        $username = trim((string) ($user['username'] ?? ''));
        $expected = 'DELETE ' . $username;
        if (trim($confirmPhrase) !== $expected) {
            throw new \RuntimeException('Confirmation phrase does not match. Type: ' . $expected);
        }

        $result = E3BackupUserLifecycleService::deleteUser(
            $clientId,
            $backupUserId,
            $confirmPhrase,
            false,
            ['admin_id' => $adminId, 'source' => 'ms365_admin_users'],
            true
        );

        if (($result['status'] ?? '') !== 'success') {
            throw new \RuntimeException((string) ($result['message'] ?? 'Deprovision failed.'));
        }

        Ms365AdminUserControlsRepository::clearSuspension($backupUserId);

        logModuleCall('ms365backup', 'admin_user_deprovision', [
            'backup_user_id' => $backupUserId,
            'client_id' => $clientId,
            'admin_id' => $adminId,
        ], $result, [], []);

        return [
            'backup_user_id' => $backupUserId,
            'message' => (string) ($result['message'] ?? 'User deleted successfully.'),
            'summary' => $result['summary'] ?? [],
        ];
    }

    /** @return array<string, mixed>|null */
    private static function loadBackupUser(int $backupUserId): ?array
    {
        if ($backupUserId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
            return null;
        }

        $row = Capsule::table('s3_backup_users')
            ->where('id', $backupUserId)
            ->first(['id', 'client_id', 'username', 'status']);

        return $row !== null ? (array) $row : null;
    }

    /**
     * Pause MS365 jobs and return prior statuses keyed by job UUID.
     *
     * @return array<string, string>
     */
    private static function pauseMs365Jobs(int $clientId, int $backupUserId): array
    {
        $prior = [];
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return $prior;
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $select = ['status'];
        if ($hasJobIdPk) {
            $select[] = Capsule::raw('BIN_TO_UUID(job_id) as job_id');
        } else {
            $select[] = 'id as job_id';
        }

        $rows = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('status', '!=', 'deleted')
            ->get($select);

        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $arr = (array) $row;
            $jobId = (string) ($arr['job_id'] ?? '');
            $status = (string) ($arr['status'] ?? 'active');
            if ($jobId === '') {
                continue;
            }
            $prior[$jobId] = $status;
            if ($status === 'paused') {
                continue;
            }
            $q = Capsule::table('s3_cloudbackup_jobs')
                ->where('client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->where('status', '!=', 'deleted');
            if ($hasJobIdPk) {
                $q->whereRaw('job_id = UUID_TO_BIN(?)', [$jobId]);
            } else {
                $q->where('id', $jobId);
            }
            $q->update(['status' => 'paused', 'updated_at' => $now]);
        }

        return $prior;
    }

    /**
     * @param array<string, string> $priorStatuses
     */
    private static function restoreMs365Jobs(int $clientId, int $backupUserId, array $priorStatuses): void
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs') || $priorStatuses === []) {
            return;
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $now = date('Y-m-d H:i:s');
        foreach ($priorStatuses as $jobId => $status) {
            $jobId = trim((string) $jobId);
            $status = trim((string) $status);
            if ($jobId === '' || $status === '' || $status === 'deleted') {
                continue;
            }
            $q = Capsule::table('s3_cloudbackup_jobs')
                ->where('client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
                ->where('status', '!=', 'deleted');
            if ($hasJobIdPk) {
                $q->whereRaw('job_id = UUID_TO_BIN(?)', [$jobId]);
            } else {
                $q->where('id', $jobId);
            }
            $q->update(['status' => $status, 'updated_at' => $now]);
        }
    }

    private static function sendServiceEmail(int $serviceId, string $templateName): void
    {
        if ($serviceId <= 0 || !function_exists('localAPI')) {
            return;
        }

        try {
            localAPI('SendEmail', [
                'messagename' => $templateName,
                'id' => $serviceId,
            ]);
        } catch (\Throwable $e) {
            logModuleCall('ms365backup', 'admin_user_service_email_fail', [
                'service_id' => $serviceId,
                'template' => $templateName,
            ], $e->getMessage(), [], []);
        }
    }
}
