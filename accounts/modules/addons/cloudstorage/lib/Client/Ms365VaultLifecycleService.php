<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * MS365 per-job vault (e3ms365-* bucket) recycle-bin lifecycle.
 */
final class Ms365VaultLifecycleService
{
    private const MODULE = 'cloudstorage';

    public static function getGraceDays(): int
    {
        $raw = AgentIngestSupport::getModuleSetting('ms365_vault_recycle_grace_days', '30');
        $days = (int) $raw;
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 365) {
            $days = 365;
        }

        return $days;
    }

    public static function isMs365VaultBucketName(string $bucketName): bool
    {
        return Ms365PlatformStorageService::isMs365BillingExemptBucketName($bucketName);
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $auditContext
     * @return array{status: string, message?: string, bucket_id?: int, recycle_teardown_at?: string, grace_days?: int}
     */
    public static function softDeleteVaultForJob(array $job, int $clientId, array $auditContext = []): array
    {
        if (!CloudBackupController::isMs365CloudBackupJob($job)) {
            return ['status' => 'skipped', 'message' => 'Not an MS365 job'];
        }

        $bucketId = (int) ($job['dest_bucket_id'] ?? 0);
        if ($bucketId <= 0) {
            return ['status' => 'skipped', 'message' => 'Job has no destination bucket'];
        }

        $bucket = Capsule::table('s3_buckets')->where('id', $bucketId)->first();
        if ($bucket === null) {
            return ['status' => 'skipped', 'message' => 'Destination bucket not found'];
        }

        $bucketName = (string) ($bucket->name ?? '');
        if (!self::isMs365VaultBucketName($bucketName)) {
            return ['status' => 'skipped', 'message' => 'Bucket is not an MS365 vault'];
        }

        $recycleStatus = (string) ($bucket->recycle_status ?? 'active');
        if ($recycleStatus !== '' && $recycleStatus !== 'active') {
            return ['status' => 'skipped', 'message' => 'Vault already in recycle or pending delete'];
        }

        $jobId = (string) ($job['job_id'] ?? '');
        if ($jobId !== '' && self::otherActiveJobsUseBucket($bucketId, $jobId, $clientId)) {
            return ['status' => 'skipped', 'message' => 'Other active jobs still use this vault'];
        }

        $graceDays = self::getGraceDays();
        $now = new \DateTimeImmutable('now');
        $teardownAt = $now->modify('+' . $graceDays . ' days');

        $update = [
            'recycle_started_at' => $now->format('Y-m-d H:i:s'),
            'recycle_teardown_at' => $teardownAt->format('Y-m-d H:i:s'),
            'recycle_status' => 'recycle',
            'is_active' => 0,
        ];
        if ($jobId !== '' && UuidBinary::isUuid($jobId) && Capsule::schema()->hasColumn('s3_buckets', 'recycled_from_job_id')) {
            $update['recycled_from_job_id'] = Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($jobId)));
        }

        Capsule::table('s3_buckets')->where('id', $bucketId)->update($update);

        self::writeAuditEvent($clientId, (int) ($job['backup_user_id'] ?? 0) ?: null, 'ms365_vault_recycled', 'bucket', (string) $bucketId, $auditContext, [
            'bucket_name' => $bucketName,
            'job_id' => $jobId,
            'job_name' => (string) ($job['name'] ?? ''),
            'grace_days' => $graceDays,
            'recycle_teardown_at' => $teardownAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'status' => 'success',
            'bucket_id' => $bucketId,
            'bucket_name' => $bucketName,
            'recycle_teardown_at' => $teardownAt->format('Y-m-d H:i:s'),
            'grace_days' => $graceDays,
        ];
    }

    /**
     * @return array{vaults_active: list<array<string, mixed>>, vaults_recycle: list<array<string, mixed>>}
     */
    public static function listVaultsForBackupUser(int $clientId, int $backupUserId): array
    {
        $active = [];
        $recycle = [];

        if ($backupUserId <= 0 || $clientId <= 0) {
            return ['vaults_active' => $active, 'vaults_recycle' => $recycle];
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs') || !Capsule::schema()->hasTable('s3_buckets')) {
            return ['vaults_active' => $active, 'vaults_recycle' => $recycle];
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $hasBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
        $hasRecycleStatus = Capsule::schema()->hasColumn('s3_buckets', 'recycle_status');

        $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')
            ->where('j.client_id', $clientId);
        if ($hasBackupUserId) {
            $jobQuery->where('j.backup_user_id', $backupUserId);
        }
        $jobQuery->where(function ($q) {
            $q->where('j.source_type', 'ms365')->orWhere('j.engine', 'ms365');
        });

        $jobCols = ['j.name', 'j.status as job_status', 'j.schedule_json', 'j.dest_bucket_id'];
        if ($hasJobIdPk) {
            $jobCols[] = Capsule::raw('BIN_TO_UUID(j.job_id) as job_id');
        } else {
            $jobCols[] = 'j.id as job_id';
        }
        $jobs = $jobQuery->get($jobCols);

        $bucketIds = [];
        $jobByBucket = [];
        foreach ($jobs as $job) {
            $bid = (int) ($job->dest_bucket_id ?? 0);
            if ($bid <= 0) {
                continue;
            }
            $bucketIds[$bid] = true;
            if (!isset($jobByBucket[$bid])) {
                $jobByBucket[$bid] = $job;
            }
        }

        $allBucketIds = array_keys($bucketIds);
        $usageByBucket = self::bucketUsageMap($allBucketIds);
        $earlyDeleteStatus = self::earlyDeleteStatusMap($allBucketIds);

        if (empty($allBucketIds)) {
            return ['vaults_active' => $active, 'vaults_recycle' => $recycle];
        }

        $buckets = Capsule::table('s3_buckets')->whereIn('id', $allBucketIds)->get();

        foreach ($buckets as $bucket) {
            $bid = (int) ($bucket->id ?? 0);
            $name = (string) ($bucket->name ?? '');
            if (!self::isMs365VaultBucketName($name)) {
                continue;
            }

            $job = $jobByBucket[$bid] ?? null;
            $meta = self::formatVaultRow($bucket, $job, $usageByBucket[$bid] ?? null, $earlyDeleteStatus[$bid] ?? null);

            $status = (string) ($bucket->recycle_status ?? 'active');
            if ($status === 'recycle' || $status === 'pending_delete') {
                $recycle[] = $meta;
            } elseif ($status === 'active' || $status === '') {
                $active[] = $meta;
            }
        }

        return ['vaults_active' => $active, 'vaults_recycle' => $recycle];
    }

    /**
     * @return array{vaults_active: list<array<string, mixed>>, vaults_recycle: list<array<string, mixed>>, legacy_vaults: list<array<string, mixed>>, grace_days: int}
     */
    public static function listVaultsForClient(int $clientId, ?int $tenantFilter = null, bool $directOnly = false): array
    {
        $active = [];
        $recycle = [];
        $legacyVaults = [];
        $graceDays = self::getGraceDays();

        if ($clientId <= 0) {
            return [
                'vaults_active' => $active,
                'vaults_recycle' => $recycle,
                'legacy_vaults' => $legacyVaults,
                'grace_days' => $graceDays,
            ];
        }

        $seenMs365BucketIds = [];
        $seenLegacyBucketIds = [];

        foreach (self::getInScopeBackupUsers($clientId, $tenantFilter, $directOnly) as $user) {
            $backupUserId = (int) $user['id'];
            $enrichFields = [
                'username' => (string) $user['username'],
                'user_route_id' => !empty($user['public_id']) ? (string) $user['public_id'] : (string) $backupUserId,
                'backup_user_id' => $backupUserId,
                'tenant_name' => $user['tenant_name'] ?? null,
            ];

            $vaultData = self::listVaultsForBackupUser($clientId, $backupUserId);
            foreach ($vaultData['vaults_active'] as $vault) {
                $bid = (int) ($vault['id'] ?? 0);
                if ($bid > 0 && isset($seenMs365BucketIds[$bid])) {
                    continue;
                }
                if ($bid > 0) {
                    $seenMs365BucketIds[$bid] = true;
                }
                $active[] = array_merge($vault, $enrichFields);
            }
            foreach ($vaultData['vaults_recycle'] as $vault) {
                $bid = (int) ($vault['id'] ?? 0);
                if ($bid > 0 && isset($seenMs365BucketIds[$bid])) {
                    continue;
                }
                if ($bid > 0) {
                    $seenMs365BucketIds[$bid] = true;
                }
                $recycle[] = array_merge($vault, $enrichFields);
            }

            foreach (self::legacyVaultsForBackupUser($clientId, $backupUserId, $user['storage_tenant_id'] ?? null) as $vault) {
                $bid = (int) ($vault['id'] ?? 0);
                if ($bid > 0 && isset($seenLegacyBucketIds[$bid])) {
                    continue;
                }
                if ($bid > 0) {
                    $seenLegacyBucketIds[$bid] = true;
                }
                $legacyVaults[] = array_merge($vault, $enrichFields);
            }
        }

        return [
            'vaults_active' => $active,
            'vaults_recycle' => $recycle,
            'legacy_vaults' => $legacyVaults,
            'grace_days' => $graceDays,
        ];
    }

    public static function queueExpiredVaultsForTeardown(): int
    {
        if (!Capsule::schema()->hasColumn('s3_buckets', 'recycle_status')) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $buckets = Capsule::table('s3_buckets')
            ->where('recycle_status', 'recycle')
            ->whereNotNull('recycle_teardown_at')
            ->where('recycle_teardown_at', '<=', $now)
            ->where('name', 'like', 'e3ms365-%')
            ->get();

        $queued = 0;
        foreach ($buckets as $bucket) {
            if (self::queueBucketTeardown($bucket)) {
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{status: string, message?: string, request_id?: int}
     */
    public static function requestEarlyDeletion(int $bucketId, int $clientId, array $context = []): array
    {
        if ($bucketId <= 0) {
            return ['status' => 'fail', 'message' => 'Invalid bucket'];
        }

        $bucket = Capsule::table('s3_buckets')->where('id', $bucketId)->first();
        if ($bucket === null) {
            return ['status' => 'fail', 'message' => 'Vault not found'];
        }

        $bucketName = (string) ($bucket->name ?? '');
        if (!self::isMs365VaultBucketName($bucketName)) {
            return ['status' => 'fail', 'message' => 'Not an MS365 vault'];
        }

        if ((string) ($bucket->recycle_status ?? '') !== 'recycle') {
            return ['status' => 'fail', 'message' => 'Vault is not in the recycle bin'];
        }

        if (!self::clientOwnsMs365Vault($bucketId, $clientId)) {
            return ['status' => 'fail', 'message' => 'Access denied'];
        }

        if (!Capsule::schema()->hasTable('s3_ms365_vault_deletion_requests')) {
            return ['status' => 'fail', 'message' => 'Early deletion is not available'];
        }

        $existing = Capsule::table('s3_ms365_vault_deletion_requests')
            ->where('bucket_id', $bucketId)
            ->where('status', 'pending')
            ->first();
        if ($existing !== null) {
            return [
                'status' => 'success',
                'message' => 'A pending request already exists',
                'request_id' => (int) $existing->id,
            ];
        }

        $backupUserId = (int) ($context['backup_user_id'] ?? 0) ?: null;
        $jobIdBytes = null;
        $jobId = (string) ($context['job_id'] ?? '');
        $insert = [
            'bucket_id' => $bucketId,
            'client_id' => $clientId,
            'backup_user_id' => $backupUserId,
            'status' => 'pending',
            'requested_by_user_id' => (int) ($context['actor_client_user_id'] ?? 0) ?: null,
            'requested_at' => date('Y-m-d H:i:s'),
            'reason' => isset($context['reason']) ? trim((string) $context['reason']) : null,
        ];
        if ($jobId !== '' && UuidBinary::isUuid($jobId)) {
            $insert['job_id'] = Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($jobId)));
        }

        $requestId = (int) Capsule::table('s3_ms365_vault_deletion_requests')->insertGetId($insert);

        self::writeAuditEvent($clientId, $backupUserId, 'ms365_early_delete_requested', 'bucket', (string) $bucketId, $context, [
            'bucket_name' => $bucketName,
            'request_id' => $requestId,
        ]);

        Ms365VaultNotificationService::sendEarlyDeleteOpsNotification($clientId, $bucketName, $requestId, $context);

        $ticket = Ms365VaultNotificationService::createEarlyDeleteSupportTicket(
            $clientId,
            $bucketName,
            $bucketId,
            $requestId,
            $context
        );

        $response = [
            'status' => 'success',
            'request_id' => $requestId,
            'message' => 'Early deletion request submitted.',
        ];
        if (is_array($ticket) && !empty($ticket['tid'])) {
            $response['ticket'] = $ticket;
            $response['message'] = 'Early deletion request submitted. Support ticket #' . $ticket['tid'] . ' opened.';
        } elseif (is_array($ticket) && !empty($ticket['id'])) {
            $response['ticket'] = $ticket;
            $response['message'] = 'Early deletion request submitted. Support ticket opened.';
        }

        return $response;
    }

    public static function validateDeleteJobPhrase(string $jobName, string $confirmPhrase): bool
    {
        $expected = 'DELETE ' . trim($jobName);
        return strcasecmp(trim($confirmPhrase), $expected) === 0;
    }

    /**
     * @param array<string, mixed> $auditContext
     * @param array<string, mixed> $payload
     */
    public static function writeAuditEvent(
        int $clientId,
        ?int $backupUserId,
        string $eventType,
        ?string $entityType,
        ?string $entityId,
        array $auditContext,
        array $payload = []
    ): void {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_audit_events')) {
            return;
        }

        try {
            Capsule::table('s3_cloudbackup_audit_events')->insert([
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'actor_client_user_id' => (int) ($auditContext['actor_client_user_id'] ?? 0) ?: null,
                'actor_contact_id' => (int) ($auditContext['actor_contact_id'] ?? 0) ?: null,
                'request_ip' => isset($auditContext['request_ip']) ? (string) $auditContext['request_ip'] : null,
                'request_ua' => isset($auditContext['request_ua']) ? (string) $auditContext['request_ua'] : null,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'writeAuditEvent', ['event' => $eventType], $e->getMessage());
        }
    }

    private static function otherActiveJobsUseBucket(int $bucketId, string $excludeJobId, int $clientId): bool
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return false;
        }

        $query = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('dest_bucket_id', $bucketId)
            ->where('status', 'active');

        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id') && UuidBinary::isUuid($excludeJobId)) {
            $query->whereRaw('job_id != ' . UuidBinary::toDbExpr(UuidBinary::normalize($excludeJobId)));
        }

        return $query->exists();
    }

    private static function clientOwnsMs365Vault(int $bucketId, int $clientId): bool
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return false;
        }

        return Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('dest_bucket_id', $bucketId)
            ->exists();
    }

    /**
     * @param object $bucket
     */
    private static function queueBucketTeardown($bucket): bool
    {
        $bucketId = (int) ($bucket->id ?? 0);
        $bucketName = (string) ($bucket->name ?? '');
        $userId = (int) ($bucket->user_id ?? 0);
        if ($bucketId <= 0 || $bucketName === '' || $userId <= 0) {
            return false;
        }

        $hasStatusColumn = Capsule::schema()->hasColumn('s3_delete_buckets', 'status');
        $existing = Capsule::table('s3_delete_buckets')
            ->where('user_id', $userId)
            ->where('bucket_name', $bucketName);
        if ($hasStatusColumn) {
            $existing->whereIn('status', ['queued', 'running', 'blocked']);
        }
        if ($existing->exists()) {
            Capsule::table('s3_buckets')->where('id', $bucketId)->update(['recycle_status' => 'pending_delete']);
            return false;
        }

        $row = [
            'user_id' => $userId,
            'bucket_name' => $bucketName,
            'attempt_count' => 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        if ($hasStatusColumn) {
            $row['status'] = 'queued';
        }
        if (Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_action')) {
            $row['requested_action'] = 'delete';
        }
        if (Capsule::schema()->hasColumn('s3_delete_buckets', 'audit_json')) {
            $row['audit_json'] = json_encode([
                'source' => 'ms365_vault_recycle_teardown',
                'bucket_id' => $bucketId,
            ]);
        }

        Capsule::table('s3_delete_buckets')->insert($row);
        Capsule::table('s3_buckets')->where('id', $bucketId)->update(['recycle_status' => 'pending_delete']);

        return true;
    }

    /**
     * @param list<int> $bucketIds
     * @return array<int, int>
     */
    private static function bucketUsageMap(array $bucketIds): array
    {
        $map = [];
        if (empty($bucketIds) || !Capsule::schema()->hasTable('s3_bucket_stats_summary')) {
            return $map;
        }

        $rows = Capsule::table('s3_bucket_stats_summary')
            ->whereIn('bucket_id', $bucketIds)
            ->orderBy('created_at', 'desc')
            ->get(['bucket_id', 'total_usage']);

        foreach ($rows as $row) {
            $bid = (int) ($row->bucket_id ?? 0);
            if ($bid > 0 && !isset($map[$bid])) {
                $map[$bid] = (int) ($row->total_usage ?? 0);
            }
        }

        return $map;
    }

    /**
     * @param list<int> $bucketIds
     * @return array<int, string>
     */
    private static function earlyDeleteStatusMap(array $bucketIds): array
    {
        $map = [];
        if (empty($bucketIds) || !Capsule::schema()->hasTable('s3_ms365_vault_deletion_requests')) {
            return $map;
        }

        $rows = Capsule::table('s3_ms365_vault_deletion_requests')
            ->whereIn('bucket_id', $bucketIds)
            ->orderBy('id', 'desc')
            ->get(['bucket_id', 'status']);

        foreach ($rows as $row) {
            $bid = (int) ($row->bucket_id ?? 0);
            if ($bid > 0 && !isset($map[$bid])) {
                $map[$bid] = (string) ($row->status ?? '');
            }
        }

        return $map;
    }

    /**
     * @return list<array{id: int, public_id: string, username: string, storage_tenant_id: int|null, tenant_name: string|null}>
     */
    private static function getInScopeBackupUsers(int $clientId, ?int $tenantFilter, bool $directOnly): array
    {
        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            return [];
        }

        $isMsp = MspController::isMspClient($clientId);
        $tenantTable = MspController::getTenantTableName();
        $mspId = MspController::getMspIdForClient($clientId);
        $hasPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');

        $userQuery = Capsule::table('s3_backup_users as u')
            ->leftJoin($tenantTable . ' as t', function ($join) use ($clientId, $tenantTable, $mspId) {
                $join->on('u.tenant_id', '=', 't.id')
                    ->where('t.status', '!=', 'deleted');
                if ($tenantTable === 'eb_tenants') {
                    $join->where('t.msp_id', '=', (int) ($mspId ?? 0));
                } else {
                    $join->where('t.client_id', '=', (int) $clientId);
                }
            })
            ->where('u.client_id', $clientId);
        E3BackupUserScope::applyNotDeletedScope($userQuery, 'u');

        $userQuery->select(array_merge([
                'u.id',
                'u.username',
                'u.tenant_id as storage_tenant_id',
                't.name as tenant_name',
            ], $hasPublicId ? ['u.public_id'] : []));

        if ($directOnly) {
            $userQuery->whereNull('u.tenant_id');
        } elseif ($tenantFilter !== null) {
            $userQuery->where('u.tenant_id', $tenantFilter);
        } elseif ($isMsp) {
            $userQuery->where(function ($q) {
                $q->whereNull('u.tenant_id')->orWhereNotNull('t.id');
            });
        }

        $users = [];
        foreach ($userQuery->orderBy('u.username')->get() as $user) {
            $users[] = [
                'id' => (int) $user->id,
                'public_id' => $hasPublicId ? (string) ($user->public_id ?? '') : '',
                'username' => (string) $user->username,
                'storage_tenant_id' => $user->storage_tenant_id !== null ? (int) $user->storage_tenant_id : null,
                'tenant_name' => $user->tenant_name ?? null,
            ];
        }

        return $users;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function legacyVaultsForBackupUser(int $clientId, int $backupUserId, ?int $storageTenantId): array
    {
        $vaults = [];
        if ($backupUserId <= 0
            || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')
            || !Capsule::schema()->hasTable('s3_cloudbackup_agents')
            || !Capsule::schema()->hasTable('s3_buckets')) {
            return $vaults;
        }

        $hasJobBackupUser = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
        $hasAgentBackupUser = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');

        $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')
            ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_uuid', '=', 'a.agent_uuid')
            ->where('j.client_id', $clientId)
            ->where('j.status', '!=', 'deleted');

        if ($hasJobBackupUser) {
            if ($hasAgentBackupUser) {
                $jobQuery->where(function ($scoped) use ($backupUserId) {
                    $scoped->where('j.backup_user_id', $backupUserId)
                        ->orWhere(function ($legacy) use ($backupUserId) {
                            $legacy->whereNull('j.backup_user_id')
                                ->where('a.backup_user_id', $backupUserId);
                        });
                });
            } else {
                $jobQuery->where('j.backup_user_id', $backupUserId);
            }
        } elseif ($hasAgentBackupUser) {
            if ($storageTenantId === null) {
                $jobQuery->whereNull('a.tenant_id');
            } else {
                $jobQuery->where('a.tenant_id', $storageTenantId);
            }
        } else {
            return $vaults;
        }

        $jobCollection = $jobQuery->get(['j.dest_bucket_id', 'j.dest_prefix']);

        $bucketIds = [];
        $jobsUsingBucket = [];
        foreach ($jobCollection as $jr) {
            $bid = $jr->dest_bucket_id ?? null;
            if ($bid !== null && $bid !== '') {
                $ik = (int) $bid;
                $bucketIds[$ik] = true;
                $jobsUsingBucket[$ik] = ($jobsUsingBucket[$ik] ?? 0) + 1;
            }
        }

        if (empty($bucketIds)) {
            return $vaults;
        }

        $bucketRows = Capsule::table('s3_buckets')
            ->whereIn('id', array_keys($bucketIds))
            ->get(['id', 'name', 'created_at']);

        foreach ($bucketRows as $b) {
            $bid = (int) ($b->id ?? 0);
            $name = (string) ($b->name ?? '');
            if ($name === '' || self::isMs365VaultBucketName($name)) {
                continue;
            }

            $path = '';
            foreach ($jobCollection as $jr) {
                if ((int) ($jr->dest_bucket_id ?? 0) !== $bid) {
                    continue;
                }
                $p = trim((string) ($jr->dest_prefix ?? ''));
                if ($p !== '') {
                    $path = '/' . ltrim($p, '/');
                    break;
                }
            }

            $createdOut = null;
            if (!empty($b->created_at)) {
                try {
                    $createdOut = (new \DateTimeImmutable((string) $b->created_at))->format('Y-m-d');
                } catch (\Throwable $e) {
                    $createdOut = null;
                }
            }

            $vaults[] = [
                'id' => $bid,
                'name' => $name,
                'provider_label' => 'eazyBackup Cloud',
                'bucket_path' => $path !== '' ? $path : '—',
                'storage_used_display' => '—',
                'created' => $createdOut,
                'jobs_using' => (int) ($jobsUsingBucket[$bid] ?? 0),
                'is_ms365' => false,
                'retention_tier' => '—',
                'protection_label' => '—',
                'job_name' => '—',
            ];
        }

        return $vaults;
    }

    /**
     * @param object $bucket
     * @param object|null $job
     * @return array<string, mixed>
     */
    private static function formatVaultRow($bucket, $job, ?int $usageBytes, ?string $earlyDeleteStatus): array
    {
        $bid = (int) ($bucket->id ?? 0);
        $name = (string) ($bucket->name ?? '');
        $schedule = [];
        if ($job !== null) {
            $decoded = json_decode((string) ($job->schedule_json ?? ''), true);
            if (is_array($decoded)) {
                $schedule = $decoded;
            }
        }

        $retentionTier = (string) ($schedule['retention_tier'] ?? '—');
        $teardownAt = $bucket->recycle_teardown_at ?? null;
        $daysRemaining = null;
        if ($teardownAt) {
            $ts = strtotime((string) $teardownAt);
            if ($ts !== false) {
                $daysRemaining = max(0, (int) ceil(($ts - time()) / 86400));
            }
        }

        return [
            'id' => $bid,
            'name' => $name,
            'provider_label' => 'Microsoft 365 Backup',
            'protection_label' => 'Versioning enabled',
            'immutability_label' => 'None',
            'retention_tier' => $retentionTier !== '' ? $retentionTier : '—',
            'storage_used_bytes' => $usageBytes,
            'storage_used_display' => $usageBytes !== null ? self::formatBytes($usageBytes) : '—',
            'created' => $bucket->created_at ?? null,
            'job_name' => $job !== null ? (string) ($job->name ?? '—') : '—',
            'job_id' => $job !== null ? (string) ($job->job_id ?? '') : '',
            'recycle_status' => (string) ($bucket->recycle_status ?? 'active'),
            'recycle_started_at' => $bucket->recycle_started_at ?? null,
            'recycle_teardown_at' => $teardownAt,
            'days_remaining' => $daysRemaining,
            'early_delete_request_status' => $earlyDeleteStatus ?? null,
            'is_ms365' => true,
            'jobs_using' => $job !== null ? 1 : 0,
        ];
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KiB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MiB';
        }
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GiB';
    }
}
