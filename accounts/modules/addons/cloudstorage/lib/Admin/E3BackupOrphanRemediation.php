<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Admin;

require_once __DIR__ . '/../Client/CloudBackupController.php';
require_once __DIR__ . '/../Client/E3BackupUserLifecycleService.php';
require_once __DIR__ . '/../Client/E3BackupUserScope.php';
require_once __DIR__ . '/../Client/Ms365VaultLifecycleService.php';
require_once __DIR__ . '/../Client/UuidBinary.php';

use Ms365Backup\TenantRecordRepository;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserLifecycleService;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365VaultLifecycleService;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

/**
 * Detect and remediate orphaned e3 resources after hard backup-user deletes.
 */
final class E3BackupOrphanRemediation
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function scan(?int $clientId = null): array
    {
        return [
            'orphan_jobs' => self::findOrphanJobs($clientId),
            'orphan_agents' => self::findOrphanAgents($clientId),
            'orphan_ms365_tenants' => self::findOrphanMs365Tenants($clientId),
            'orphan_vaults' => self::findOrphanVaults($clientId),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function remediate(array $item, bool $dryRun = true): array
    {
        $type = (string) ($item['type'] ?? '');
        if ($type === '') {
            return ['status' => 'fail', 'message' => 'Missing orphan type.'];
        }

        if ($dryRun) {
            return ['status' => 'success', 'dry_run' => true, 'type' => $type, 'item' => $item];
        }

        switch ($type) {
            case 'orphan_job':
                return self::remediateOrphanJob($item);
            case 'orphan_agent':
                return self::remediateOrphanAgent($item);
            case 'orphan_ms365_tenant':
                return self::remediateOrphanMs365Tenant($item);
            case 'orphan_vault':
                return self::remediateOrphanVault($item);
            default:
                return ['status' => 'fail', 'message' => 'Unknown orphan type: ' . $type];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function findOrphanJobs(?int $clientId): array
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')
            || !Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            return [];
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $query = Capsule::table('s3_cloudbackup_jobs as j')
            ->leftJoin('s3_backup_users as u', 'j.backup_user_id', '=', 'u.id')
            ->where('j.status', '!=', 'deleted')
            ->where('j.backup_user_id', '>', 0)
            ->where(function ($q) {
                $q->whereNull('u.id');
                if (E3BackupUserScope::hasDeletedAtColumn()) {
                    $q->orWhereNotNull('u.deleted_at');
                } else {
                    $q->orWhere('u.status', 'disabled');
                }
            });

        if ($clientId !== null && $clientId > 0) {
            $query->where('j.client_id', $clientId);
        }

        $select = [
            'j.client_id',
            'j.backup_user_id',
            'j.name',
        ];
        if ($hasJobIdPk) {
            $select[] = Capsule::raw('BIN_TO_UUID(j.job_id) as job_uuid');
        } else {
            $select[] = 'j.id as job_uuid';
        }

        $rows = [];
        foreach ($query->select($select)->orderBy('j.client_id')->limit(500)->get() as $row) {
            $rows[] = [
                'type' => 'orphan_job',
                'client_id' => (int) $row->client_id,
                'backup_user_id' => (int) $row->backup_user_id,
                'job_id' => (string) $row->job_uuid,
                'job_name' => (string) ($row->name ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function findOrphanAgents(?int $clientId): array
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_agents')
            || !Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id')) {
            return [];
        }

        $query = Capsule::table('s3_cloudbackup_agents as a')
            ->leftJoin('s3_backup_users as u', 'a.backup_user_id', '=', 'u.id')
            ->where('a.backup_user_id', '>', 0)
            ->where(function ($q) {
                $q->whereNull('u.id');
                if (E3BackupUserScope::hasDeletedAtColumn()) {
                    $q->orWhereNotNull('u.deleted_at');
                } else {
                    $q->orWhere('u.status', 'disabled');
                }
            });

        if ($clientId !== null && $clientId > 0) {
            $query->where('a.client_id', $clientId);
        }

        $rows = [];
        foreach ($query->select(['a.client_id', 'a.backup_user_id', 'a.agent_uuid', 'a.hostname', 'a.status'])->limit(500)->get() as $row) {
            $rows[] = [
                'type' => 'orphan_agent',
                'client_id' => (int) $row->client_id,
                'backup_user_id' => (int) $row->backup_user_id,
                'agent_uuid' => (string) $row->agent_uuid,
                'hostname' => (string) ($row->hostname ?? ''),
                'status' => (string) ($row->status ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function findOrphanMs365Tenants(?int $clientId): array
    {
        if (!Capsule::schema()->hasTable('ms365_tenant_records')
            || !Capsule::schema()->hasColumn('ms365_tenant_records', 'backup_user_id')) {
            return [];
        }

        $query = Capsule::table('ms365_tenant_records as t')
            ->leftJoin('s3_backup_users as u', function ($join) {
                $join->on('t.backup_user_id', '=', 'u.id')
                    ->on('t.whmcs_client_id', '=', 'u.client_id');
            })
            ->where('t.is_active', 1)
            ->where('t.backup_user_id', '>', 0)
            ->whereIn('t.connection_status', ['connected', 'action_required'])
            ->where(function ($q) {
                $q->whereNull('u.id');
                if (E3BackupUserScope::hasDeletedAtColumn()) {
                    $q->orWhereNotNull('u.deleted_at');
                } else {
                    $q->orWhere('u.status', 'disabled');
                }
            });

        if ($clientId !== null && $clientId > 0) {
            $query->where('t.whmcs_client_id', $clientId);
        }

        $rows = [];
        foreach ($query->select(['t.id', 't.whmcs_client_id', 't.backup_user_id', 't.azure_tenant_id', 't.connection_status'])->limit(500)->get() as $row) {
            $rows[] = [
                'type' => 'orphan_ms365_tenant',
                'tenant_record_id' => (int) $row->id,
                'client_id' => (int) $row->whmcs_client_id,
                'backup_user_id' => (int) $row->backup_user_id,
                'azure_tenant_id' => (string) ($row->azure_tenant_id ?? ''),
                'connection_status' => (string) ($row->connection_status ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function findOrphanVaults(?int $clientId): array
    {
        if (!Capsule::schema()->hasTable('s3_buckets')
            || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return [];
        }

        $bucketQuery = Capsule::table('s3_buckets as b')
            ->where('b.name', 'like', 'e3ms365-%')
            ->where(function ($q) {
                if (Capsule::schema()->hasColumn('s3_buckets', 'recycle_status')) {
                    $q->where('b.recycle_status', 'active');
                } else {
                    $q->where('b.is_active', 1);
                }
            });

        if ($clientId !== null && $clientId > 0) {
            $bucketQuery->where('b.client_id', $clientId);
        }

        $rows = [];
        foreach ($bucketQuery->select(['b.id', 'b.client_id', 'b.name'])->limit(500)->get() as $bucket) {
            $bucketId = (int) $bucket->id;
            $activeJobs = (int) Capsule::table('s3_cloudbackup_jobs')
                ->where('client_id', (int) $bucket->client_id)
                ->where('dest_bucket_id', $bucketId)
                ->where('status', 'active')
                ->count();
            if ($activeJobs > 0) {
                continue;
            }

            $rows[] = [
                'type' => 'orphan_vault',
                'client_id' => (int) $bucket->client_id,
                'bucket_id' => $bucketId,
                'bucket_name' => (string) $bucket->name,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function remediateOrphanJob(array $item): array
    {
        $clientId = (int) ($item['client_id'] ?? 0);
        $jobId = (string) ($item['job_id'] ?? '');
        if ($clientId <= 0 || $jobId === '') {
            return ['status' => 'fail', 'message' => 'Invalid orphan job payload.'];
        }

        $result = CloudBackupController::deleteJob(
            $jobId,
            $clientId,
            '',
            ['cascade' => 'orphan_remediation'],
            ['skip_confirm' => true, 'skip_notification' => true]
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function remediateOrphanAgent(array $item): array
    {
        $clientId = (int) ($item['client_id'] ?? 0);
        $backupUserId = (int) ($item['backup_user_id'] ?? 0);
        $agentUuid = (string) ($item['agent_uuid'] ?? '');
        if ($clientId <= 0 || $backupUserId <= 0 || $agentUuid === '') {
            return ['status' => 'fail', 'message' => 'Invalid orphan agent payload.'];
        }

        $now = date('Y-m-d H:i:s');
        $updates = [
            'status' => 'disabled',
            'backup_user_id' => null,
            'updated_at' => $now,
        ];
        Capsule::table('s3_cloudbackup_agents')
            ->where('client_id', $clientId)
            ->where('agent_uuid', $agentUuid)
            ->update($updates);

        return ['status' => 'success', 'message' => 'Agent disabled and unassigned.'];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function remediateOrphanMs365Tenant(array $item): array
    {
        $clientId = (int) ($item['client_id'] ?? 0);
        $backupUserId = (int) ($item['backup_user_id'] ?? 0);
        $tenantRecordId = (int) ($item['tenant_record_id'] ?? 0);

        if ($tenantRecordId > 0 && class_exists(TenantRecordRepository::class)) {
            TenantRecordRepository::markDisconnected($tenantRecordId);

            return ['status' => 'success', 'message' => 'MS365 tenant marked disconnected.'];
        }

        if ($clientId > 0 && $backupUserId > 0) {
            $disconnected = E3BackupUserLifecycleService::disconnectMs365IfNeeded($clientId, $backupUserId);

            return [
                'status' => $disconnected ? 'success' : 'fail',
                'message' => $disconnected ? 'MS365 tenant disconnected.' : 'MS365 tenant disconnect failed.',
            ];
        }

        return ['status' => 'fail', 'message' => 'Invalid orphan MS365 tenant payload.'];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function remediateOrphanVault(array $item): array
    {
        $clientId = (int) ($item['client_id'] ?? 0);
        $bucketId = (int) ($item['bucket_id'] ?? 0);
        if ($clientId <= 0 || $bucketId <= 0) {
            return ['status' => 'fail', 'message' => 'Invalid orphan vault payload.'];
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $jobQuery = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('dest_bucket_id', $bucketId)
            ->orderByDesc('updated_at');

        $job = $jobQuery->first();
        if ($job === null) {
            return ['status' => 'fail', 'message' => 'No job found for orphan vault bucket.'];
        }

        $jobId = $hasJobIdPk && !empty($job->job_id)
            ? self::binaryJobIdToString($job->job_id)
            : (string) ($job->id ?? '');
        if ($jobId === '') {
            return ['status' => 'fail', 'message' => 'Could not resolve job id for vault recycle.'];
        }

        $jobRow = CloudBackupController::getJob($jobId, $clientId);
        if ($jobRow === null) {
            return ['status' => 'fail', 'message' => 'Job not found for vault recycle.'];
        }

        $vaultResult = Ms365VaultLifecycleService::softDeleteVaultForJob(
            $jobRow,
            $clientId,
            ['cascade' => 'orphan_remediation']
        );

        return [
            'status' => ($vaultResult['status'] ?? '') === 'success' ? 'success' : 'fail',
            'message' => (string) ($vaultResult['message'] ?? 'Vault recycle attempted.'),
            'vault' => $vaultResult,
        ];
    }

    private static function binaryJobIdToString(mixed $binary): string
    {
        if (!is_string($binary) || strlen($binary) !== 16) {
            if (is_string($binary) && UuidBinary::isUuid($binary)) {
                return UuidBinary::normalize($binary);
            }

            return '';
        }
        $hex = bin2hex($binary);

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
