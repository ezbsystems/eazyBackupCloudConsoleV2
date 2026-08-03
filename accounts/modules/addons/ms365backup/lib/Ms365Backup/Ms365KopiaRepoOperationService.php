<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPolicyService;

/**
 * Schedules Kopia retention and maintenance for MS365 job repositories.
 */
final class Ms365KopiaRepoOperationService
{
    /** @return array{scheduled: int, skipped: int} */
    public static function scheduleDueMaintenance(): array
    {
        return self::scheduleDueForAllJobs(false);
    }

    /** @return array{scheduled: int, skipped: int} */
    public static function scheduleDueRetentionAndMaintenance(): array
    {
        return self::scheduleDueForAllJobs(true);
    }

    /**
     * @param array<string, mixed> $tenantRow
     */
    public static function scheduleForTenantBatchSuccess(array $tenantRow, ?string $e3JobId = null): void
    {
        if ($e3JobId !== null && $e3JobId !== '') {
            self::scheduleRetentionForJob($e3JobId, $tenantRow);
            self::scheduleMaintenanceForJob($e3JobId, $tenantRow);

            return;
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return;
        }

        $clientId = (int) ($tenantRow['whmcs_client_id'] ?? 0);
        $backupUserId = (int) ($tenantRow['backup_user_id'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $q = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('status', 'active');
        if ($backupUserId > 0 && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            $q->where('backup_user_id', $backupUserId);
        }

        foreach ($q->get() as $job) {
            $jobUuid = self::jobUuidFromRow($job);
            if ($jobUuid === '') {
                continue;
            }
            self::scheduleRetentionForJob($jobUuid, $tenantRow);
            self::scheduleMaintenanceForJob($jobUuid, $tenantRow);
        }
    }

  /**
     * @param array<string, mixed> $tenantRow
     */
    public static function scheduleRetentionForJob(string $e3JobId, array $tenantRow): bool
    {
        $ctx = self::repoContextForJob($e3JobId, $tenantRow);
        if ($ctx === null) {
            return false;
        }

        $token = 'ms365-retention-' . $ctx['repo_id'] . '-' . substr(hash('sha256', $e3JobId), 0, 12) . '-' . gmdate('o-\WW');

        $result = KopiaRetentionOperationService::enqueue(
            $ctx['repo_id'],
            'retention_apply',
            ['repo_id' => $ctx['repo_id'], 'engine' => 'ms365', 'e3_job_id' => $e3JobId],
            $token,
        );

        return in_array((string) ($result['status'] ?? ''), ['success', 'duplicate'], true);
    }

    /**
     * @param array<string, mixed> $tenantRow
     */
    public static function scheduleMaintenanceForJob(string $e3JobId, array $tenantRow, ?int $cutoffTs = null): bool
    {
        $ctx = self::repoContextForJob($e3JobId, $tenantRow);
        if ($ctx === null) {
            return false;
        }

        $repoId = $ctx['repo_id'];
        $intervalDays = Ms365EngineConfig::kopiaMaintenanceIntervalDays();
        $cutoffTs = $cutoffTs ?? (time() - ($intervalDays * 86400));
        $progressGrace = Ms365EngineConfig::kopiaRepoOpProgressGraceSeconds();
        $freshCutoff = date('Y-m-d H:i:s', time() - $progressGrace);

        $recent = Capsule::table('s3_kopia_repo_operations')
            ->where('repo_id', $repoId)
            ->whereIn('op_type', ['maintenance_quick', 'maintenance_full'])
            ->where('created_at', '>=', date('Y-m-d H:i:s', $cutoffTs))
            ->where(function ($q) use ($freshCutoff): void {
                $q->where('status', 'success')
                    ->orWhere(function ($q2) use ($freshCutoff): void {
                        $q2->whereIn('status', ['queued', 'running'])
                            ->where('updated_at', '>=', $freshCutoff);
                    });
            })
            ->exists();
        if ($recent) {
            return false;
        }

        $token = 'ms365-maint-' . $repoId . '-' . gmdate('o-\WW');
        $result = KopiaRetentionOperationService::enqueue(
            $repoId,
            'maintenance_quick',
            ['repo_id' => $repoId, 'engine' => 'ms365', 'e3_job_id' => $e3JobId],
            $token,
        );

        return in_array((string) ($result['status'] ?? ''), ['success', 'duplicate'], true);
    }

    /**
     * Claim the next queued repo operation for an MS365 repository.
     *
     * @return array<string, mixed>|null
     */
    public static function claimNextForWorker(string $nodeId): ?array
    {
        if ($nodeId === ''
            || !Capsule::schema()->hasTable('s3_kopia_repo_operations')
            || !Capsule::schema()->hasTable('s3_kopia_repos')) {
            return null;
        }

        $lockTableReady = Capsule::schema()->hasTable('s3_kopia_repo_locks');
        $lockTtl = Ms365EngineConfig::kopiaRepoOpLockTtlSeconds();
        $hasNodeColumn = Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id');

        return Capsule::connection()->transaction(function () use ($nodeId, $lockTableReady, $lockTtl, $hasNodeColumn): ?array {
            $candidates = Capsule::table('s3_kopia_repo_operations as op')
                ->join('s3_kopia_repos as r', 'r.id', '=', 'op.repo_id')
                ->where('op.status', 'queued')
                ->whereIn('op.op_type', ['retention_apply', 'maintenance_quick', 'maintenance_full'])
                ->where('r.repository_id', 'like', 'ms365:%')
                ->orderByRaw("FIELD(op.op_type, 'retention_apply', 'maintenance_quick', 'maintenance_full')")
                ->orderBy('op.created_at')
                ->select(['op.*', 'r.repository_id', 'r.client_id', 'r.tenant_id', 'r.vault_policy_version_id'])
                ->lockForUpdate()
                ->limit(25)
                ->get();

            foreach ($candidates as $op) {
                $claimed = self::tryClaimCandidate($op, $nodeId, $lockTableReady, $lockTtl, $hasNodeColumn);
                if ($claimed !== null) {
                    return $claimed;
                }
            }

            return null;
        });
    }

    /**
     * Record mid-run progress for a claimed repo operation; renews the repo lock.
     *
     * @param array<string, mixed> $fields
     */
    public static function recordProgress(int $operationId, string $nodeId, string $phase, array $fields = []): bool
    {
        if ($operationId <= 0 || $nodeId === ''
            || !Capsule::schema()->hasTable('s3_kopia_repo_operations')) {
            return false;
        }

        $op = Capsule::table('s3_kopia_repo_operations')->where('id', $operationId)->first();
        if ($op === null || (string) ($op->status ?? '') !== 'running') {
            return false;
        }
        if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
            $claimedBy = trim((string) ($op->claimed_by_node_id ?? ''));
            if ($claimedBy !== '' && $claimedBy !== $nodeId) {
                return false;
            }
        }

        $existing = [];
        if (!empty($op->result_json)) {
            $decoded = json_decode((string) $op->result_json, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $merged = array_merge($existing, $fields, [
            'phase' => $phase,
            'progress_at' => gmdate('c'),
        ]);
        $update = [
            'result_json' => json_encode($merged),
            'updated_at' => Capsule::raw('NOW()'),
        ];
        Capsule::table('s3_kopia_repo_operations')
            ->where('id', $operationId)
            ->where('status', 'running')
            ->update($update);

        if (Capsule::schema()->hasTable('s3_kopia_repo_locks')
            && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::class)) {
            $lockTtl = Ms365EngineConfig::kopiaRepoOpLockTtlSeconds();
            \WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::renew(
                (int) $op->repo_id,
                (string) $op->operation_token,
                $lockTtl
            );
        }

        return true;
    }

    /**
     * @param array<string, mixed> $result
     */
    public static function markComplete(int $operationId, string $status = 'success', array $result = []): void
    {
        if ($operationId <= 0) {
            return;
        }

        $op = Capsule::table('s3_kopia_repo_operations')->where('id', $operationId)->first();
        $update = [
            'status' => $status === 'success' ? 'success' : 'error',
            'updated_at' => Capsule::raw('NOW()'),
        ];
        if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
            $update['claimed_by_node_id'] = null;
        }
        if ($result !== [] && Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'result_json')) {
            $existing = [];
            if ($op !== null && !empty($op->result_json)) {
                $decoded = json_decode((string) $op->result_json, true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }
            $update['result_json'] = json_encode(array_merge($existing, $result));
        }
        Capsule::table('s3_kopia_repo_operations')
            ->where('id', $operationId)
            ->update($update);

        if ($op !== null
            && Capsule::schema()->hasTable('s3_kopia_repo_locks')
            && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::class)) {
            \WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::release(
                (int) $op->repo_id,
                (string) $op->operation_token
            );
        }
    }

    /** @return int operations reaped */
    public static function reapStaleRunningOps(): int
    {
        if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')) {
            return 0;
        }

        $graceSeconds = Ms365EngineConfig::kopiaRepoOpProgressGraceSeconds();
        $lockTableReady = Capsule::schema()->hasTable('s3_kopia_repo_locks');
        $hasNodeColumn = Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id');
        $now = time();

        $rows = Capsule::table('s3_kopia_repo_operations as op')
            ->join('s3_kopia_repos as r', 'r.id', '=', 'op.repo_id')
            ->where('op.status', 'running')
            ->where('r.repository_id', 'like', 'ms365:%')
            ->select(['op.*', 'r.repository_id'])
            ->get();

        $reaped = 0;
        foreach ($rows as $row) {
            $updatedAt = (string) ($row->updated_at ?? '');
            $updatedTs = $updatedAt !== '' ? strtotime($updatedAt) : 0;
            $staleProgress = $updatedTs <= 0 || ($now - $updatedTs) > $graceSeconds;

            $lockExpired = true;
            if ($lockTableReady) {
                $lock = Capsule::table('s3_kopia_repo_locks')
                    ->where('repo_id', (int) $row->repo_id)
                    ->where('lock_token', (string) $row->operation_token)
                    ->first();
                if ($lock !== null) {
                    $expiryTs = $lock->expires_at ? strtotime((string) $lock->expires_at) : 0;
                    $lockExpired = $expiryTs <= 0 || $expiryTs < $now;
                }
            }

            if (!$staleProgress && !$lockExpired) {
                continue;
            }

            $payload = self::decodeJsonObject($row->payload_json ?? null);
            $result = array_merge(self::decodeJsonObject($row->result_json ?? null), [
                'error' => 'orphaned_stale',
                'reaped_at' => gmdate('c'),
                'stale_progress' => $staleProgress,
                'lock_expired' => $lockExpired,
            ]);

            $update = [
                'status' => 'error',
                'result_json' => json_encode($result),
                'updated_at' => Capsule::raw('NOW()'),
            ];
            if ($hasNodeColumn) {
                $update['claimed_by_node_id'] = null;
            }
            Capsule::table('s3_kopia_repo_operations')
                ->where('id', (int) $row->id)
                ->where('status', 'running')
                ->update($update);

            if ($lockTableReady && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::class)) {
                \WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::release(
                    (int) $row->repo_id,
                    (string) $row->operation_token
                );
            }

            self::maybeRequeueMaintenanceOp($row, $payload);
            ++$reaped;
        }

        return $reaped;
    }

    /** @return array{scheduled: int, skipped: int} */
    private static function scheduleDueForAllJobs(bool $includeRetention): array
    {
        if (!Capsule::schema()->hasTable('ms365_tenant_records')
            || !Capsule::schema()->hasTable('s3_kopia_repos')
            || !Capsule::schema()->hasTable('s3_kopia_repo_operations')
            || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return ['scheduled' => 0, 'skipped' => 0];
        }

        $intervalDays = Ms365EngineConfig::kopiaMaintenanceIntervalDays();
        $cutoff = time() - ($intervalDays * 86400);
        $scheduled = 0;
        $skipped = 0;

        $jobs = Capsule::table('s3_cloudbackup_jobs')
            ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('status', 'active')
            ->get(['job_id', 'schedule_json']);

        foreach ($jobs as $job) {
            $jobUuid = self::jobUuidFromRow($job);
            if ($jobUuid === '') {
                ++$skipped;
                continue;
            }
            $ms365 = [];
            if (!empty($job->schedule_json)) {
                $decoded = json_decode((string) $job->schedule_json, true);
                if (is_array($decoded)) {
                    $ms365 = $decoded;
                }
            }
            $tenantRecordId = (int) ($ms365['tenant_record_id'] ?? 0);
            $tenantRow = $tenantRecordId > 0 ? TenantRecordRepository::getById($tenantRecordId) : null;
            if ($tenantRow === null) {
                ++$skipped;
                continue;
            }
            if (($tenantRow['connection_status'] ?? '') !== 'connected') {
                ++$skipped;
                continue;
            }
            try {
                $didWork = false;
                if ($includeRetention) {
                    $didWork = self::scheduleRetentionForJob($jobUuid, $tenantRow) || $didWork;
                }
                if (self::scheduleMaintenanceForJob($jobUuid, $tenantRow, $cutoff)) {
                    $didWork = true;
                }
                if ($didWork) {
                    ++$scheduled;
                } else {
                    ++$skipped;
                }
            } catch (\Throwable $e) {
                ++$skipped;
                logActivity('MS365 Kopia repo op skipped for job: ' . $e->getMessage());
            }
        }

        return ['scheduled' => $scheduled, 'skipped' => $skipped];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function tryClaimCandidate(
        object $op,
        string $nodeId,
        bool $lockTableReady,
        int $lockTtl,
        bool $hasNodeColumn
    ): ?array {
        $operationToken = (string) $op->operation_token;
        if ($lockTableReady && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::class)) {
            $acquired = \WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::acquire(
                (int) $op->repo_id,
                $operationToken,
                null,
                $lockTtl
            );
            if (!$acquired) {
                return null;
            }
        }

        $update = [
            'status' => 'running',
            'attempt_count' => Capsule::raw('attempt_count + 1'),
            'updated_at' => Capsule::raw('NOW()'),
        ];
        if ($hasNodeColumn) {
            $update['claimed_by_node_id'] = $nodeId;
        }
        $updated = Capsule::table('s3_kopia_repo_operations')
            ->where('id', $op->id)
            ->where('status', 'queued')
            ->update($update);
        if ($updated === 0) {
            if ($lockTableReady && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::class)) {
                \WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::release((int) $op->repo_id, $operationToken);
            }

            return null;
        }

        $payload = self::decodeJsonObject($op->payload_json ?? null);
        $e3JobId = trim((string) ($payload['e3_job_id'] ?? ''));
        $tenantRecordId = (int) ($op->tenant_id ?? 0);
        $record = $tenantRecordId > 0 ? TenantRecordRepository::getById($tenantRecordId) : null;
        if ($record === null) {
            self::failOperation((int) $op->id, (int) $op->repo_id, $operationToken, $lockTableReady, $hasNodeColumn);

            return null;
        }

        if ($e3JobId !== '') {
            $dest = Ms365JobDestinationService::resolveForJobId($e3JobId, $record);
        } else {
            $dest = Ms365JobDestinationService::resolveLegacyTenantBucket($record);
        }

        $effectivePolicy = self::effectivePolicyForOp($op, $dest['kopia_repo_id']);

        return [
            'operation_id' => (int) $op->id,
            'op_type' => (string) $op->op_type,
            'operation_token' => $operationToken,
            'repository_id' => (string) $op->repository_id,
            'tenant_record_id' => $tenantRecordId,
            'e3_job_id' => $e3JobId,
            'whmcs_client_id' => (int) ($record['whmcs_client_id'] ?? 0),
            'azure_tenant_id' => (string) (TenantRecordRepository::platformCredentials($record)['tenant_id'] ?? ''),
            'dest_endpoint' => $dest['endpoint'],
            'dest_region' => $dest['region'],
            'dest_bucket' => $dest['bucket'],
            'dest_prefix' => $dest['prefix'],
            'dest_access_key' => $dest['access_key'],
            'dest_secret_key' => $dest['secret_key'],
            'repo_password' => $dest['repo_password'],
            'kopia_repo_id' => $dest['kopia_repo_id'],
            'effective_policy' => $effectivePolicy,
            'worker_node_id' => $nodeId,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function maybeRequeueMaintenanceOp(object $row, array $payload): void
    {
        $opType = (string) ($row->op_type ?? '');
        if (!in_array($opType, ['maintenance_quick', 'maintenance_full'], true)) {
            return;
        }
        if (($payload['engine'] ?? '') !== 'ms365') {
            return;
        }
        $attempts = (int) ($row->attempt_count ?? 0);
        if ($attempts >= Ms365EngineConfig::kopiaRepoOpRequeueMaxAttempts()) {
            return;
        }
        $e3JobId = trim((string) ($payload['e3_job_id'] ?? ''));
        if ($e3JobId === '') {
            return;
        }

        $token = 'ms365-maint-requeue-' . (int) $row->id . '-' . substr(hash('sha256', (string) $row->operation_token), 0, 12);
        KopiaRetentionOperationService::enqueue(
            (int) $row->repo_id,
            $opType,
            ['repo_id' => (int) $row->repo_id, 'engine' => 'ms365', 'e3_job_id' => $e3JobId, 'requeued_from' => (int) $row->id],
            $token,
        );
    }

    /**
     * @param array<string, mixed> $tenantRow
     * @return array{repo_id: int, repository_id: string}|null
     */
    private static function repoContextForJob(string $e3JobId, array $tenantRow): ?array
    {
        $dest = Ms365JobDestinationService::resolveForJobId($e3JobId, $tenantRow);
        $repositoryId = (string) ($dest['kopia_repo_id'] ?? '');
        if ($repositoryId === '') {
            return null;
        }
        $repoRow = Capsule::table('s3_kopia_repos')->where('repository_id', $repositoryId)->first();
        if ($repoRow === null) {
            return null;
        }

        return [
            'repo_id' => (int) $repoRow->id,
            'repository_id' => $repositoryId,
        ];
    }

    /**
     * @return array{hourly: int, daily: int, weekly: int, monthly: int, yearly: int}
     */
    private static function effectivePolicyForOp(object $op, string $repositoryId): array
    {
        if (!empty($op->vault_policy_version_id) && Capsule::schema()->hasTable('s3_kopia_policy_versions')) {
            $policyRow = Capsule::table('s3_kopia_policy_versions')
                ->where('id', (int) $op->vault_policy_version_id)
                ->first();
            if ($policyRow && !empty($policyRow->policy_json)) {
                $decoded = json_decode((string) $policyRow->policy_json, true);
                if (is_array($decoded)) {
                    return KopiaRetentionPolicyService::resolveEffectivePolicy(null, $decoded, 'active');
                }
            }
        }

        return KopiaRepoBootstrapService::effectivePolicyForRepositoryId($repositoryId);
    }

    private static function failOperation(
        int $operationId,
        int $repoId,
        string $token,
        bool $lockTableReady,
        bool $hasNodeColumn = false
    ): void {
        $update = [
            'status' => 'error',
            'updated_at' => Capsule::raw('NOW()'),
        ];
        if ($hasNodeColumn) {
            $update['claimed_by_node_id'] = null;
        }
        Capsule::table('s3_kopia_repo_operations')->where('id', $operationId)->update($update);
        if ($lockTableReady && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::class)) {
            \WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::release($repoId, $token);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJsonObject(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function jobUuidFromRow(object $job): string
    {
        if (!isset($job->job_id)) {
            return '';
        }
        $raw = $job->job_id;
        if (is_string($raw) && strlen($raw) === 16) {
            $hex = bin2hex($raw);

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
        }

        return is_string($raw) ? $raw : '';
    }
}
