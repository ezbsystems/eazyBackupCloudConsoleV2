<?php
declare(strict_types=1);

namespace Ms365Backup;

use PDO;
use WHMCS\Database\Capsule;

final class BackupRunRepository
{
    public static function create(
        string $userId,
        string $userUpn,
        string $displayName,
        string $backupPath,
        bool $backupMail = true,
        bool $backupCalendar = true,
    ): string {
        $scope = new BackupScope([
            BackupScope::MAIL => $backupMail,
            BackupScope::CALENDAR => $backupCalendar,
        ]);

        $resourceType = TenantResource::TYPE_USER;
        $resourceId = TenantResource::makeId($resourceType, $userId);

        $job = new PhysicalBackupJob(
            'user:' . $userId,
            [
                'id' => $resourceId,
                'resource_type' => $resourceType,
                'graph_id' => $userId,
                'display_name' => $displayName,
                'email' => $userUpn,
            ],
            [
                [
                    'id' => $resourceId,
                    'resource_type' => $resourceType,
                    'display_name' => $displayName,
                ],
            ],
            $scope,
            PhysicalBackupJob::STATUS_RUNNABLE,
        );

        $creds = TenantRepository::credentials();
        $storage = new StorageLayout($creds['tenant_id']);
        $runId = self::createFromPhysicalJob($job, $storage);
        if ($backupPath !== '') {
            self::update($runId, ['backup_path' => $backupPath]);
        }

        return $runId;
    }

    public static function createFromPhysicalJob(
        PhysicalBackupJob $job,
        StorageLayout $storage,
        ?int $tenantRecordId = null,
        int $whmcsClientId = 0,
        int $backupUserId = 0,
        ?string $e3JobId = null,
        ?string $e3BatchRunId = null,
    ): string {
        $id = self::uuid();
        $row = self::buildInsertRow(
            $job,
            $storage,
            $id,
            $tenantRecordId,
            $whmcsClientId,
            $backupUserId,
            $e3JobId,
            $e3BatchRunId,
        );
        Capsule::table('ms365_backup_runs')->insert($row);

        return $id;
    }

    /**
     * @param list<PhysicalBackupJob> $jobs
     * @return list<string> run ids in the same order as runnable jobs
     */
    public static function createManyFromPhysicalJobs(
        array $jobs,
        StorageLayout $storage,
        ?int $tenantRecordId = null,
        int $whmcsClientId = 0,
        int $backupUserId = 0,
        ?string $e3JobId = null,
        ?string $e3BatchRunId = null,
    ): array {
        if ($jobs === []) {
            return [];
        }
        $rows = [];
        $runIds = [];
        foreach ($jobs as $job) {
            if (!$job->isRunnable()) {
                continue;
            }
            $id = self::uuid();
            $rows[] = self::buildInsertRow(
                $job,
                $storage,
                $id,
                $tenantRecordId,
                $whmcsClientId,
                $backupUserId,
                $e3JobId,
                $e3BatchRunId,
            );
            $runIds[] = $id;
        }
        self::insertMany($rows);

        return $runIds;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function insertMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Capsule::table('ms365_backup_runs')->insert($chunk);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildInsertRow(
        PhysicalBackupJob $job,
        StorageLayout $storage,
        string $id,
        ?int $tenantRecordId = null,
        int $whmcsClientId = 0,
        int $backupUserId = 0,
        ?string $e3JobId = null,
        ?string $e3BatchRunId = null,
    ): array {
        $now = time();
        $resourceType = $job->resourceType();
        $graphId = $job->graphId();
        $scope = $job->scope;

        $userId = '';
        $userUpn = '';
        $displayName = $job->displayName();

        if (in_array($resourceType, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
            $userId = $graphId;
            $userUpn = $job->email();
        }

        $runDir = $storage->runDirForJob($job->physicalKey, $id);

        $logicalJson = json_encode($job->logicalSources, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $scopePayload = $scope->toArray();
        $primaryMeta = is_array($job->primaryResource['meta'] ?? null) ? $job->primaryResource['meta'] : [];
        if (!empty($primaryMeta['drive_id'])) {
            $scopePayload['_drive_id'] = (string) $primaryMeta['drive_id'];
        }
        if (!empty($primaryMeta['display_name']) && !empty($primaryMeta['drive_id'])) {
            $scopePayload['_drive_display_name'] = (string) $primaryMeta['display_name'];
        }
        if (!empty($primaryMeta['site_id'])) {
            $scopePayload['_site_id'] = (string) $primaryMeta['site_id'];
        } elseif (!$job->isShard() && $job->parentPhysicalKey() !== PhysicalKeyHelper::baseKey($job->physicalKey)) {
            $scopePayload['_site_id'] = str_starts_with($job->parentPhysicalKey(), 'site:')
                ? substr($job->parentPhysicalKey(), 5)
                : $job->parentPhysicalKey();
        } elseif (str_starts_with($job->physicalKey, 'list:')) {
            $parent = $job->parentPhysicalKey();
            if (str_starts_with($parent, 'site:')) {
                $scopePayload['_site_id'] = substr($parent, 5);
            }
        }
        if (!empty($primaryMeta['list_id'])) {
            $scopePayload['_list_id'] = (string) $primaryMeta['list_id'];
        } elseif (str_starts_with($job->physicalKey, 'list:')) {
            $scopePayload['_list_id'] = substr(PhysicalKeyHelper::baseKey($job->physicalKey), 5);
        }
        if (!empty($primaryMeta['excluded_list_ids']) && is_array($primaryMeta['excluded_list_ids'])) {
            $scopePayload['_excluded_list_ids'] = array_values(array_filter(
                array_map('strval', $primaryMeta['excluded_list_ids']),
                static fn (string $id): bool => $id !== '',
            ));
        }
        if ($job->isShard()) {
            $scopePayload['_shard'] = [
                'parent_physical_key' => $job->parentPhysicalKey(),
                'index' => $job->shardIndex,
                'total' => $job->shardTotal,
            ];
            if (!empty($primaryMeta['shard_kind'])) {
                $scopePayload['_shard']['kind'] = (string) $primaryMeta['shard_kind'];
            }
            if (!empty($primaryMeta['shard_segment'])) {
                $scopePayload['_shard']['segment'] = (string) $primaryMeta['shard_segment'];
            }
        }
        $scopeJson = json_encode($scopePayload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $row = [
            'id' => $id,
            'user_id' => $userId,
            'user_upn' => $userUpn,
            'user_display_name' => $displayName,
            'resource_id' => $job->resourceId(),
            'resource_type' => $resourceType,
            'graph_id' => $graphId,
            'physical_key' => $job->physicalKey,
            'status' => 'queued',
            'phase' => '',
            'items_done' => 0,
            'items_total' => 0,
            'percent' => 0,
            'backup_path' => $runDir,
            'backup_mail' => $scope->isEnabled(BackupScope::MAIL) ? 1 : 0,
            'backup_calendar' => $scope->isEnabled(BackupScope::CALENDAR) ? 1 : 0,
            'scope_json' => is_string($scopeJson) ? $scopeJson : $scope->toJson(),
            'logical_sources_json' => is_string($logicalJson) ? $logicalJson : '[]',
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => $whmcsClientId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($backupUserId > 0 && self::hasBackupUserIdColumn()) {
            $row['backup_user_id'] = $backupUserId;
        }
        if ($e3JobId !== null && $e3JobId !== '' && self::hasE3JobIdColumn()) {
            $row['e3_job_id'] = $e3JobId;
        }
        if ($e3BatchRunId !== null && $e3BatchRunId !== '' && self::hasE3BatchRunIdColumn()) {
            $row['e3_batch_run_id'] = $e3BatchRunId;
        }

        return $row;
    }

    private static ?bool $hasBackupUserIdColumn = null;
    private static ?bool $hasE3JobIdColumn = null;
    private static ?bool $hasE3BatchRunIdColumn = null;

    private static function hasBackupUserIdColumn(): bool
    {
        if (self::$hasBackupUserIdColumn === null) {
            self::$hasBackupUserIdColumn = Capsule::schema()->hasColumn('ms365_backup_runs', 'backup_user_id');
        }

        return self::$hasBackupUserIdColumn;
    }

    private static function hasE3JobIdColumn(): bool
    {
        if (self::$hasE3JobIdColumn === null) {
            self::$hasE3JobIdColumn = Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_job_id');
        }

        return self::$hasE3JobIdColumn;
    }

    private static function hasE3BatchRunIdColumn(): bool
    {
        if (self::$hasE3BatchRunIdColumn === null) {
            self::$hasE3BatchRunIdColumn = Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_batch_run_id');
        }

        return self::$hasE3BatchRunIdColumn;
    }

    public static function get(string $id): ?array
    {
        $row = Capsule::table('ms365_backup_runs')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public static function getForClient(string $id, int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }
        $row = Capsule::table('ms365_backup_runs')
            ->where('id', $id)
            ->where('whmcs_client_id', $clientId)
            ->first();

        return $row ? (array) $row : null;
    }

    public static function update(string $id, array $fields): void
    {
        $fields['updated_at'] = time();
        Capsule::table('ms365_backup_runs')->where('id', $id)->update($fields);
    }

    /** Reset a child backup run so a requeued job can be claimed again. */
    public static function resetForQueueRequeue(string $id, int $now = 0): void
    {
        if ($now <= 0) {
            $now = time();
        }
        self::update($id, [
            'status' => 'queued',
            'phase' => '',
            'percent' => 0,
            'items_done' => 0,
            'items_total' => 0,
            'error_message' => null,
            'finished_at' => null,
            'updated_at' => $now,
        ]);
    }

    public static function isCancelled(string $id): bool
    {
        $run = self::get($id);

        return $run !== null && ($run['status'] ?? '') === 'cancelled';
    }

    public static function isCancellable(string $id): bool
    {
        $run = self::get($id);
        if ($run === null) {
            return false;
        }

        return in_array($run['status'] ?? '', ['queued', 'running'], true);
    }

    public static function requestCancel(string $id, string $cancelledBy = 'administrator'): bool
    {
        if (!self::isCancellable($id)) {
            return false;
        }
        $label = $cancelledBy === 'user' ? 'Cancelled by user' : 'Cancelled by administrator';
        self::update($id, [
            'status' => 'cancelled',
            'phase' => 'cancelled',
            'error_message' => $label,
            'finished_at' => time(),
        ]);
        JobQueueRepository::markCancelled($id, $label);

        return true;
    }

    /**
     * Cancel all queued/running child workloads for a batch in a few SQL statements.
     * Used by user cancel and batch reconcile — avoids per-child SELECT/UPDATE loops.
     */
    public static function bulkCancelBatchChildren(string $batchRunId, string $cancelledBy = 'user'): int
    {
        if ($batchRunId === ''
            || !Capsule::schema()->hasTable('ms365_backup_runs')
            || !Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_batch_run_id')) {
            return 0;
        }

        $activeIds = Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->whereIn('status', ['queued', 'running'])
            ->pluck('id')
            ->all();
        if ($activeIds === []) {
            return 0;
        }

        $label = $cancelledBy === 'user' ? 'Cancelled by user' : 'Cancelled by administrator';
        $now = time();
        Capsule::table('ms365_backup_runs')
            ->whereIn('id', $activeIds)
            ->update([
                'status' => 'cancelled',
                'phase' => 'cancelled',
                'error_message' => $label,
                'finished_at' => $now,
            ]);
        JobQueueRepository::markCancelledMany($activeIds, $label);

        return count($activeIds);
    }

    public static function setPhase(string $id, string $phase, ?int $done = null, ?int $total = null): void
    {
        if (self::isCancelled($id)) {
            throw new RunCancelledException('Backup cancelled by administrator');
        }
        $fields = ['phase' => $phase, 'status' => 'running'];
        if ($done !== null) {
            $fields['items_done'] = $done;
        }
        if ($total !== null) {
            $fields['items_total'] = $total;
        }
        if ($total !== null && $total > 0 && $done !== null) {
            $fields['percent'] = min(100, round(($done / $total) * 100, 2));
        }
        $run = self::get($id);
        if ($run && ($run['started_at'] ?? null) === null) {
            $fields['started_at'] = time();
        }
        self::update($id, $fields);
    }

    /** @return list<array<string, mixed>> */
    public static function listRecent(int $limit = 25): array
    {
        return Capsule::table('ms365_backup_runs')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public static function listRecentForClient(int $clientId, int $limit = 25): array
    {
        if ($clientId <= 0) {
            return [];
        }

        return Capsule::table('ms365_backup_runs')
            ->where('whmcs_client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function searchForClient(int $clientId, ?string $status = null, ?int $since = null, int $limit = 50): array
    {
        if ($clientId <= 0) {
            return [];
        }
        $q = Capsule::table('ms365_backup_runs')->where('whmcs_client_id', $clientId);
        if ($status !== null && $status !== '') {
            $q->where('status', $status);
        }
        if ($since !== null && $since > 0) {
            $q->where('created_at', '>=', $since);
        }

        return $q->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public static function getFromPdo(PDO $pdo, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM ms365_backup_runs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function updatePdo(PDO $pdo, string $id, array $fields): void
    {
        $fields['updated_at'] = time();
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
        $vals[] = $id;
        $pdo->prepare('UPDATE ms365_backup_runs SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
