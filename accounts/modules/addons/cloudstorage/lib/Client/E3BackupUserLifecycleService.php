<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use Ms365Backup\Ms365DisconnectService;
use Ms365Backup\TenantRecordRepository;
use WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap;

/**
 * Central cascade for e3 Backup User delete (soft-disable + related cleanup).
 */
final class E3BackupUserLifecycleService
{
    /**
     * @return array<string, mixed>
     */
    public static function preview(int $clientId, int $backupUserId): array
    {
        $user = self::loadUser($clientId, $backupUserId, true);
        if ($user === null) {
            return ['status' => 'fail', 'message' => 'User not found.'];
        }

        return [
            'status' => 'success',
            'dry_run' => true,
            'user' => self::userSummary($user),
            'impact' => self::impactCounts($clientId, $backupUserId),
            'whmcs_service_id' => self::resolveWhmcsServiceId($clientId, $backupUserId, $user),
            'confirm_phrase' => 'DELETE ' . (string) ($user->username ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $auditContext
     * @return array<string, mixed>
     */
    public static function deleteUser(
        int $clientId,
        int $backupUserId,
        string $confirmPhrase,
        bool $dryRun = false,
        array $auditContext = [],
        bool $skipConfirm = false
    ): array {
        $user = self::loadUser($clientId, $backupUserId, true);
        if ($user === null) {
            return ['status' => 'fail', 'message' => 'User not found.'];
        }

        if (self::isUserDeleted($user)) {
            return [
                'status' => 'success',
                'message' => 'User already deleted.',
                'jobs_deleted' => 0,
                'vaults_recycled' => 0,
                'agents_disabled' => 0,
                'tokens_revoked' => 0,
                'ms365_disconnected' => false,
                'whmcs_cancelled' => false,
                'user_disabled' => true,
            ];
        }

        $username = trim((string) ($user->username ?? ''));
        if (!$dryRun && !$skipConfirm && !E3BackupUserScope::validateDeletePhrase($username, $confirmPhrase)) {
            return [
                'status' => 'fail',
                'code' => 'confirm_phrase_mismatch',
                'message' => 'Confirmation phrase does not match. Type: DELETE ' . $username,
            ];
        }

        $impact = self::impactCounts($clientId, $backupUserId);
        if ($dryRun) {
            return [
                'status' => 'success',
                'dry_run' => true,
                'user' => self::userSummary($user),
                'impact' => $impact,
                'whmcs_service_id' => self::resolveWhmcsServiceId($clientId, $backupUserId, $user),
                'confirm_phrase' => 'DELETE ' . $username,
            ];
        }

        E3BackupUserScope::ensureDeletedAtColumn();

        $summary = [
            'jobs_deleted' => 0,
            'vaults_recycled' => 0,
            'agents_disabled' => 0,
            'tokens_revoked' => 0,
            'ms365_disconnected' => false,
            'whmcs_cancelled' => false,
            'user_disabled' => false,
            'job_errors' => [],
        ];

        $jobResults = self::softDeleteJobsForUser($clientId, $backupUserId, $auditContext);
        $summary['jobs_deleted'] = (int) ($jobResults['deleted'] ?? 0);
        $summary['vaults_recycled'] = (int) ($jobResults['vaults_recycled'] ?? 0);
        if (!empty($jobResults['errors'])) {
            $summary['job_errors'] = $jobResults['errors'];

            return [
                'status' => 'fail',
                'message' => 'Failed to delete one or more backup jobs. User was not deleted.',
                'summary' => $summary,
            ];
        }

        $summary['ms365_disconnected'] = self::disconnectMs365IfNeeded($clientId, $backupUserId);

        $agentResult = self::disableAgentsForUser($clientId, $backupUserId);
        $summary['agents_disabled'] = (int) ($agentResult['disabled'] ?? 0);
        $summary['tokens_revoked'] = self::revokeEnrollmentTokens($clientId, $backupUserId);

        $serviceId = self::resolveWhmcsServiceId($clientId, $backupUserId, $user);
        $cancelResult = self::cancelWhmcsService($serviceId, 'e3 Backup User deleted by customer');
        $summary['whmcs_cancelled'] = (bool) ($cancelResult['cancelled'] ?? false);
        $summary['whmcs_cancel_message'] = (string) ($cancelResult['message'] ?? '');

        $now = date('Y-m-d H:i:s');
        $freedUsername = E3BackupUserScope::deletedUsername($username, $backupUserId);
        $update = [
            'status' => 'disabled',
            'username' => $freedUsername,
            'updated_at' => $now,
        ];
        if (E3BackupUserScope::hasDeletedAtColumn()) {
            $update['deleted_at'] = $now;
        }

        Capsule::table('s3_backup_users')
            ->where('id', $backupUserId)
            ->where('client_id', $clientId)
            ->update($update);

        $summary['user_disabled'] = true;
        $summary['original_username'] = $username;
        $summary['freed_username'] = $freedUsername;

        self::writeAuditEvent($clientId, $backupUserId, $auditContext, $summary);

        return [
            'status' => 'success',
            'message' => 'User deleted successfully.',
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string, mixed> $auditContext
     * @return array{deleted: int, vaults_recycled: int, errors: list<string>}
     */
    public static function softDeleteJobsForUser(int $clientId, int $backupUserId, array $auditContext = []): array
    {
        $deleted = 0;
        $vaultsRecycled = 0;
        $errors = [];

        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')
            || !Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            return ['deleted' => 0, 'vaults_recycled' => 0, 'errors' => []];
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $select = $hasJobIdPk
            ? [Capsule::raw('BIN_TO_UUID(job_id) as job_uuid')]
            : ['id as job_uuid'];
        $select[] = 'name';

        $jobs = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->where('status', '!=', 'deleted')
            ->select($select)
            ->get();

        foreach ($jobs as $job) {
            $jobId = trim((string) ($job->job_uuid ?? ''));
            if ($jobId === '') {
                continue;
            }

            $result = CloudBackupController::deleteJob(
                $jobId,
                $clientId,
                '',
                array_merge($auditContext, ['cascade' => 'backup_user_delete']),
                ['skip_confirm' => true, 'skip_notification' => true]
            );

            if (($result['status'] ?? '') !== 'success') {
                $errors[] = $jobId . ': ' . (string) ($result['message'] ?? 'delete failed');
                continue;
            }

            $deleted++;
            if (!empty($result['vault'])) {
                $vaultsRecycled++;
            }
        }

        return ['deleted' => $deleted, 'vaults_recycled' => $vaultsRecycled, 'errors' => $errors];
    }

    public static function disableAgentsForUser(int $clientId, int $backupUserId): array
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_agents')
            || !Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id')) {
            return ['disabled' => 0];
        }

        $now = date('Y-m-d H:i:s');
        $updates = [
            'status' => 'disabled',
            'backup_user_id' => null,
            'updated_at' => $now,
        ];
        if (Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'tenant_id')) {
            $updates['tenant_id'] = null;
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'tenant_user_id')) {
            $updates['tenant_user_id'] = null;
        }

        $disabled = Capsule::table('s3_cloudbackup_agents')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->update($updates);

        return ['disabled' => (int) $disabled];
    }

    public static function revokeEnrollmentTokens(int $clientId, int $backupUserId): int
    {
        if (!Capsule::schema()->hasTable('s3_agent_enrollment_tokens')
            || !Capsule::schema()->hasColumn('s3_agent_enrollment_tokens', 'backup_user_id')) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');

        return (int) Capsule::table('s3_agent_enrollment_tokens')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);
    }

    public static function disconnectMs365IfNeeded(int $clientId, int $backupUserId): bool
    {
        if (!class_exists(TenantRecordRepository::class)) {
            return false;
        }

        try {
            $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
            if ($record === null) {
                return false;
            }

            $status = (string) ($record['connection_status'] ?? '');
            if (!in_array($status, ['connected', 'action_required'], true)) {
                return false;
            }

            if (!class_exists(Ms365DisconnectService::class)) {
                $tenantRecordId = (int) ($record['id'] ?? 0);
                if ($tenantRecordId > 0) {
                    TenantRecordRepository::markDisconnected($tenantRecordId);
                }

                return true;
            }

            Ms365DisconnectService::disconnectForBackupUser($clientId, $backupUserId);

            return true;
        } catch (\Throwable $e) {
            try {
                logModuleCall('cloudstorage', 'backup_user_disconnect_ms365', [
                    'client_id' => $clientId,
                    'backup_user_id' => $backupUserId,
                ], $e->getMessage());
            } catch (\Throwable $_) {
            }

            return false;
        }
    }

    /**
     * @param object $user
     */
    private static function resolveWhmcsServiceId(int $clientId, int $backupUserId, $user): int
    {
        $fromUser = (int) ($user->whmcs_service_id ?? 0);
        if ($fromUser > 0) {
            return $fromUser;
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username === '') {
            return 0;
        }

        $pid = (int) E3BackupUserProductBootstrap::getPid();
        if ($pid <= 0) {
            return 0;
        }

        try {
            return (int) Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('packageid', $pid)
                ->where('username', $username)
                ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
                ->orderByDesc('id')
                ->value('id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array{cancelled: bool, message: string}
     */
    public static function cancelWhmcsService(int $serviceId, string $reason): array
    {
        if ($serviceId <= 0) {
            return ['cancelled' => false, 'message' => 'No linked WHMCS service.'];
        }

        try {
            $row = Capsule::table('tblhosting')->where('id', $serviceId)->first(['domainstatus']);
            if ($row === null) {
                return ['cancelled' => false, 'message' => 'WHMCS service not found.'];
            }

            $status = (string) ($row->domainstatus ?? '');
            if (!in_array($status, ['Active', 'Suspended', 'Pending'], true)) {
                return ['cancelled' => false, 'message' => 'Service already inactive: ' . $status];
            }

            if (function_exists('localAPI')) {
                $api = localAPI('AddCancelRequest', [
                    'serviceid' => $serviceId,
                    'type' => 'Immediate',
                    'reason' => $reason,
                ]);
                if (($api['result'] ?? '') !== 'success') {
                    Capsule::table('tblhosting')->where('id', $serviceId)->update([
                        'domainstatus' => 'Cancelled',
                    ]);

                    return [
                        'cancelled' => true,
                        'message' => 'Fallback cancel applied: ' . (string) ($api['message'] ?? 'API error'),
                    ];
                }
            } else {
                Capsule::table('tblhosting')->where('id', $serviceId)->update([
                    'domainstatus' => 'Cancelled',
                ]);
            }

            $after = (string) Capsule::table('tblhosting')->where('id', $serviceId)->value('domainstatus');
            if ($after !== 'Cancelled') {
                Capsule::table('tblhosting')->where('id', $serviceId)->update([
                    'domainstatus' => 'Cancelled',
                ]);
            }

            return ['cancelled' => true, 'message' => 'WHMCS cancellation completed.'];
        } catch (\Throwable $e) {
            try {
                logModuleCall('cloudstorage', 'backup_user_cancel_whmcs', ['service_id' => $serviceId], $e->getMessage());
            } catch (\Throwable $_) {
            }

            return ['cancelled' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{jobs: int, agents: int, tokens: int, ms365_connected: bool, vaults: int}
     */
    public static function impactCounts(int $clientId, int $backupUserId): array
    {
        $jobs = 0;
        $agents = 0;
        $tokens = 0;
        $vaults = 0;
        $ms365Connected = false;

        if (Capsule::schema()->hasTable('s3_cloudbackup_jobs')
            && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            $jobs = (int) Capsule::table('s3_cloudbackup_jobs')
                ->where('client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->where('status', '!=', 'deleted')
                ->count();

            $vaults = (int) Capsule::table('s3_cloudbackup_jobs')
                ->where('client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->where('status', '!=', 'deleted')
                ->where('dest_bucket_id', '>', 0)
                ->distinct()
                ->count('dest_bucket_id');
        }

        if (Capsule::schema()->hasTable('s3_cloudbackup_agents')
            && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id')) {
            $agents = (int) Capsule::table('s3_cloudbackup_agents')
                ->where('client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->count();
        }

        if (Capsule::schema()->hasTable('s3_agent_enrollment_tokens')
            && Capsule::schema()->hasColumn('s3_agent_enrollment_tokens', 'backup_user_id')) {
            $tokens = (int) Capsule::table('s3_agent_enrollment_tokens')
                ->where('client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->whereNull('revoked_at')
                ->count();
        }

        if (class_exists(TenantRecordRepository::class)) {
            $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
            if ($record !== null) {
                $ms365Connected = in_array((string) ($record['connection_status'] ?? ''), ['connected', 'action_required'], true);
            }
        }

        return [
            'jobs' => $jobs,
            'agents' => $agents,
            'tokens' => $tokens,
            'ms365_connected' => $ms365Connected,
            'vaults' => $vaults,
        ];
    }

    private static function loadUser(int $clientId, int $backupUserId, bool $includeDeleted = false): ?object
    {
        if ($backupUserId <= 0) {
            return null;
        }

        $query = Capsule::table('s3_backup_users')
            ->where('id', $backupUserId)
            ->where('client_id', $clientId);

        if (!$includeDeleted) {
            E3BackupUserScope::applyNotDeletedScope($query, '');
        }

        $cols = ['id', 'client_id', 'username', 'status'];
        if (Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')) {
            $cols[] = 'whmcs_service_id';
        }
        if (E3BackupUserScope::hasDeletedAtColumn()) {
            $cols[] = 'deleted_at';
        }

        return $query->first($cols) ?: null;
    }

    private static function isUserDeleted(object $user): bool
    {
        return E3BackupUserScope::isDeletedUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    private static function userSummary(object $user): array
    {
        return [
            'id' => (int) ($user->id ?? 0),
            'username' => (string) ($user->username ?? ''),
            'status' => (string) ($user->status ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $auditContext
     * @param array<string, mixed> $summary
     */
    private static function writeAuditEvent(int $clientId, int $backupUserId, array $auditContext, array $summary): void
    {
        Ms365VaultLifecycleService::writeAuditEvent(
            $clientId,
            $backupUserId,
            'e3_backup_user_deleted',
            'backup_user',
            (string) $backupUserId,
            $auditContext,
            $summary
        );
    }
}
