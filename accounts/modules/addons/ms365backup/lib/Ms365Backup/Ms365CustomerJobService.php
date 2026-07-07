<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * CRUD for MS365 jobs stored in s3_cloudbackup_jobs (e3 Cloud Backup).
 */
final class Ms365CustomerJobService
{
    public const SOURCE_TYPE = 'ms365';
    public const ENGINE = 'ms365';

    /**
     * @param array{
     *   name?: string,
     *   selected_resource_ids: list<string>,
     *   scope_overrides?: array<string, array<string, bool>>,
     *   schedule_frequency: string,
     *   retention_tier?: string,
     *   timezone?: string,
     * } $payload
     * @return array{job_id: string}
     */
    public static function create(int $clientId, int $backupUserId, array $payload): array
    {
        self::assertMs365JobSchemaReady();
        self::validatePayload($payload);
        $record = self::requireConnectedTenant($clientId, $backupUserId);

        $inventory = self::loadInventory($clientId, $backupUserId);
        $scopeOverrides = CustomerSelectionCodec::normalizeScopeOverrides($payload['scope_overrides'] ?? []);
        CustomerSelectionCodec::validate($payload['selected_resource_ids'], $scopeOverrides, $inventory);

        $timezone = Ms365JobTimezoneResolver::resolveForClient(
            $clientId,
            isset($payload['timezone']) ? (string) $payload['timezone'] : null,
        );
        $schedulePayload = Ms365ScheduleAssigner::buildSchedulePayload(
            (string) $payload['schedule_frequency'],
            $timezone,
        );

        $jobId = self::newJobUuid();
        $retentionTier = (string) ($payload['retention_tier'] ?? Ms365RetentionTierPolicyService::DEFAULT_TIER);
        if (!Ms365RetentionTierPolicyService::isValidTier($retentionTier)) {
            $retentionTier = Ms365RetentionTierPolicyService::DEFAULT_TIER;
        }

        $bucketRes = self::provisionJobBucket($clientId, $backupUserId, $jobId);
        $destBucketId = (int) ($bucketRes['bucket_id'] ?? 0);
        $s3UserId = (int) ($bucketRes['owner_user_id'] ?? 0);
        if ($destBucketId <= 0 || $s3UserId <= 0) {
            throw new \RuntimeException('Backup storage is not ready. Please try again in a moment.');
        }

        $recordWithBucket = Ms365JobDestinationService::tenantRecordWithBucket($record, [
            'id' => $destBucketId,
            'name' => (string) ($bucketRes['bucket_name'] ?? ''),
            'user_id' => $s3UserId,
        ]);
        KopiaRepoBootstrapService::ensureForJob($recordWithBucket, $jobId, $retentionTier);

        $ms365Json = self::buildMs365ScheduleJson($record, $payload, $schedulePayload);
        $now = date('Y-m-d H:i:s');
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = 'Microsoft 365 Backup';
        }

        $insert = [
            'job_id' => self::uuidToBinary($jobId),
            'client_id' => $clientId,
            's3_user_id' => $s3UserId,
            'backup_user_id' => $backupUserId,
            'name' => $name,
            'source_type' => self::SOURCE_TYPE,
            'source_display_name' => 'Microsoft 365',
            'source_config_enc' => encrypt(json_encode([
                'tenant_record_id' => (int) $record['id'],
                'selected_resource_ids' => $payload['selected_resource_ids'],
                'scope_overrides' => $scopeOverrides,
            ], JSON_UNESCAPED_SLASHES)),
            'source_path' => '',
            'dest_bucket_id' => $destBucketId,
            'dest_prefix' => '',
            'backup_mode' => 'archive',
            'schedule_type' => 'daily',
            'schedule_time' => sprintf('%02d:%02d:00', $schedulePayload['schedule_slots'][0]['hour'], $schedulePayload['schedule_slots'][0]['minute']),
            'timezone' => $schedulePayload['timezone'],
            'schedule_json' => json_encode($ms365Json, JSON_UNESCAPED_SLASHES),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'retention_json')) {
            $insert['retention_json'] = json_encode(
                Ms365RetentionTierPolicyService::retentionJsonForTier($retentionTier),
                JSON_UNESCAPED_SLASHES
            );
        }

        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'engine')) {
            $insert['engine'] = self::ENGINE;
        }

        Capsule::table('s3_cloudbackup_jobs')->insert($insert);
        self::assertJobMarkedMs365($clientId, $backupUserId, $jobId);
        self::ensureWhmcsServiceForBackupUser($clientId, $backupUserId);

        return ['job_id' => $jobId];
    }

    private static function ensureWhmcsServiceForBackupUser(int $clientId, int $backupUserId): void
    {
        $provisionerPath = dirname(__DIR__, 3) . '/cloudstorage/lib/Provision/Provisioner.php';
        if (!is_file($provisionerPath)) {
            return;
        }
        try {
            require_once $provisionerPath;
            if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Provision\\Provisioner')) {
                \WHMCS\Module\Addon\CloudStorage\Provision\Provisioner::ensureMs365ServiceForBackupUser($clientId, $backupUserId);
            }
        } catch (\Throwable $e) {
            try {
                logModuleCall('ms365backup', 'ensure_whmcs_service_fail', [
                    'client_id' => $clientId,
                    'backup_user_id' => $backupUserId,
                ], $e->getMessage(), [], []);
            } catch (\Throwable $_) {
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{job_id: string}
     */
    public static function update(int $clientId, int $backupUserId, string $jobId, array $payload): array
    {
        self::assertMs365JobSchemaReady();
        $job = self::getJobRow($clientId, $backupUserId, $jobId);
        if ($job === null) {
            throw new \RuntimeException('Job not found.');
        }

        self::validatePayload($payload);
        $record = self::requireConnectedTenant($clientId, $backupUserId);
        $inventory = self::loadInventory($clientId, $backupUserId);
        $scopeOverrides = CustomerSelectionCodec::normalizeScopeOverrides($payload['scope_overrides'] ?? []);
        CustomerSelectionCodec::validate($payload['selected_resource_ids'], $scopeOverrides, $inventory);

        $timezone = Ms365JobTimezoneResolver::resolveForUpdate(
            $clientId,
            $job,
            isset($payload['timezone']) ? (string) $payload['timezone'] : null,
        );
        $schedulePayload = Ms365ScheduleAssigner::buildSchedulePayload(
            (string) $payload['schedule_frequency'],
            $timezone,
        );
        $ms365Json = self::buildMs365ScheduleJson($record, $payload, $schedulePayload);
        $newTier = (string) ($payload['retention_tier'] ?? Ms365RetentionTierPolicyService::DEFAULT_TIER);
        if (!Ms365RetentionTierPolicyService::isValidTier($newTier)) {
            $newTier = Ms365RetentionTierPolicyService::DEFAULT_TIER;
        }
        $oldTier = Ms365JobDestinationService::retentionTierForJob($job);

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = (string) ($job->name ?? 'Microsoft 365 Backup');
        }

        $update = [
            'name' => $name,
            'source_config_enc' => encrypt(json_encode([
                'tenant_record_id' => (int) $record['id'],
                'selected_resource_ids' => $payload['selected_resource_ids'],
                'scope_overrides' => $scopeOverrides,
            ], JSON_UNESCAPED_SLASHES)),
            'schedule_time' => sprintf('%02d:%02d:00', $schedulePayload['schedule_slots'][0]['hour'], $schedulePayload['schedule_slots'][0]['minute']),
            'timezone' => $schedulePayload['timezone'],
            'schedule_json' => json_encode($ms365Json, JSON_UNESCAPED_SLASHES),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'retention_json')) {
            $update['retention_json'] = json_encode(
                Ms365RetentionTierPolicyService::retentionJsonForTier($newTier),
                JSON_UNESCAPED_SLASHES
            );
        }

        Capsule::table('s3_cloudbackup_jobs')
            ->whereRaw('job_id = ' . self::uuidToDbExpr($jobId))
            ->where('client_id', $clientId)
            ->update($update);

        if ($newTier !== $oldTier) {
            $bucket = Ms365JobDestinationService::bucketForJob($job, $record);
            $recordWithBucket = Ms365JobDestinationService::tenantRecordWithBucket($record, $bucket);
            if (!Ms365JobDestinationService::isLegacySharedBucket($job, $record)) {
                KopiaRepoBootstrapService::pinRetentionTierForJob($recordWithBucket, $jobId, $newTier);
            }
            Ms365KopiaRepoOperationService::scheduleRetentionForJob($jobId, $record);
        }

        return ['job_id' => $jobId];
    }

    /** @return array<string, mixed>|null */
    public static function getForClient(int $clientId, int $backupUserId, string $jobId): ?array
    {
        $job = self::getJobRow($clientId, $backupUserId, $jobId);
        if ($job === null) {
            return null;
        }

        $ms365 = self::decodeMs365Json($job->schedule_json ?? null);
        $config = self::decodeSourceConfig($job->source_config_enc ?? '');
        $scopeOverrides = CustomerSelectionCodec::normalizeScopeOverrides(
            $config['scope_overrides'] ?? ($ms365['scope_overrides'] ?? []),
        );

        return [
            'job_id' => $jobId,
            'name' => (string) ($job->name ?? ''),
            'source_type' => self::SOURCE_TYPE,
            'status' => (string) ($job->status ?? ''),
            'selected_resource_ids' => $config['selected_resource_ids'] ?? ($ms365['selected_resource_ids'] ?? []),
            'scope_overrides' => $scopeOverrides,
            'schedule_frequency' => (string) ($ms365['schedule_frequency'] ?? Ms365ScheduleAssigner::FREQUENCY_ONCE_DAILY),
            'schedule_slots' => $ms365['schedule_slots'] ?? [],
            'timezone' => (string) ($job->timezone ?? ($ms365['timezone'] ?? Ms365JobTimezoneResolver::PLATFORM_DEFAULT)),
            'retention_tier' => (string) ($ms365['retention_tier'] ?? '1y'),
            'tenant_record_id' => (int) ($ms365['tenant_record_id'] ?? 0),
            'connected' => true,
        ];
    }

    /** @return object|null */
    public static function getJobRow(int $clientId, int $backupUserId, string $jobId)
    {
        if (!self::isUuid($jobId)) {
            return null;
        }

        $q = Capsule::table('s3_cloudbackup_jobs')
            ->whereRaw('job_id = ' . self::uuidToDbExpr($jobId))
            ->where('client_id', $clientId)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('status', '!=', 'deleted');

        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            $q->where('backup_user_id', $backupUserId);
        }

        return $q->first();
    }

    /**
     * @return array{run_ids: list<string>, batch_run_id: string, count: int}
     */
    public static function runNow(int $clientId, int $backupUserId, string $jobId): array
    {
        $job = self::getJobRow($clientId, $backupUserId, $jobId);
        if ($job === null) {
            throw new \RuntimeException('Job not found.');
        }

        $ms365 = self::decodeMs365Json($job->schedule_json ?? null);
        $config = self::decodeSourceConfig($job->source_config_enc ?? '');
        $selectedIds = $ms365['selected_resource_ids'] ?? [];
        if (!is_array($selectedIds) || $selectedIds === []) {
            $selectedIds = $config['selected_resource_ids'] ?? [];
        }
        $scopeOverrides = CustomerSelectionCodec::normalizeScopeOverrides(
            $config['scope_overrides'] ?? ($ms365['scope_overrides'] ?? []),
        );

        $inventory = CustomerInventoryService::loadForBackupUser($clientId, $backupUserId);
        $resolved = CustomerSelectionCodec::resolveForExecution(
            is_array($selectedIds) ? $selectedIds : [],
            $scopeOverrides,
            $inventory,
        );

        return CustomerBackupService::startCustomBackup(
            $clientId,
            $backupUserId,
            $resolved['selected_resource_ids'],
            $jobId,
            'manual',
            $resolved['scope_overrides'],
        );
    }

    /** @param array<string, mixed> $payload */
    private static function assertMs365JobSchemaReady(): void
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            throw new \RuntimeException('Backup jobs are not available on this server.');
        }

        foreach (['source_type', 'engine'] as $column) {
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_jobs', $column)) {
                continue;
            }
            $meta = Capsule::select("SHOW COLUMNS FROM s3_cloudbackup_jobs WHERE Field = ?", [$column]);
            $typeStr = strtolower((string) ($meta[0]->Type ?? ''));
            if ($typeStr !== '' && str_contains($typeStr, 'enum(') && !str_contains($typeStr, "'ms365'")) {
                throw new \RuntimeException(
                    'Microsoft 365 jobs require a database upgrade. Update the cloudstorage addon or run sql/upgrade_ms365_job_source.sql.',
                );
            }
        }
    }

    private static function assertJobMarkedMs365(int $clientId, int $backupUserId, string $jobId): void
    {
        $q = Capsule::table('s3_cloudbackup_jobs')
            ->whereRaw('job_id = ' . self::uuidToDbExpr($jobId))
            ->where('client_id', $clientId);
        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            $q->where('backup_user_id', $backupUserId);
        }
        $row = $q->first(['source_type', 'engine']);
        if ($row === null) {
            return;
        }
        if (strtolower((string) ($row->source_type ?? '')) === self::SOURCE_TYPE) {
            return;
        }

        throw new \RuntimeException(
            'Microsoft 365 job could not be saved correctly. The database may need sql/upgrade_ms365_job_source.sql applied.',
        );
    }

    /** @param array<string, mixed> $payload */
    private static function validatePayload(array $payload): void
    {
        if (!isset($payload['selected_resource_ids']) || !is_array($payload['selected_resource_ids'])) {
            throw new \RuntimeException('Select at least one resource to back up.');
        }
        if ($payload['selected_resource_ids'] === []) {
            throw new \RuntimeException('Select at least one resource to back up.');
        }
    }

    /** @return array<string, mixed> */
    private static function requireConnectedTenant(int $clientId, int $backupUserId): array
    {
        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        if ($record === null) {
            throw new \RuntimeException('Connect Microsoft 365 before creating a backup job.');
        }
        $status = (string) ($record['connection_status'] ?? '');
        if ($status === 'action_required') {
            throw new Ms365ReconnectRequiredException(Ms365ConnectionGuard::RECONNECT_MESSAGE);
        }
        if ($status !== 'connected') {
            throw new \RuntimeException('Connect Microsoft 365 before creating a backup job.');
        }

        return $record;
    }

    /** @return array<string, mixed> */
    private static function loadInventory(int $clientId, int $backupUserId): array
    {
        $data = CustomerInventoryService::loadForBackupUser($clientId, $backupUserId);
        if (empty($data['resources'])) {
            throw new \RuntimeException('Refresh tenant inventory before saving the job.');
        }

        return $data;
    }

    /**
     * @param list<string> $selectedIds
     * @param array<string, mixed> $inventory
     * @param array<string, array<string, bool>> $scopeOverrides
     */
    private static function validateSelection(array $selectedIds, array $inventory, array $scopeOverrides = []): void
    {
        CustomerSelectionCodec::validate($selectedIds, $scopeOverrides, $inventory);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $schedulePayload
     * @return array<string, mixed>
     */
    private static function buildMs365ScheduleJson(array $record, array $payload, array $schedulePayload): array
    {
        return array_merge($schedulePayload, [
            'ms365' => true,
            'tenant_record_id' => (int) $record['id'],
            'selected_resource_ids' => array_values($payload['selected_resource_ids']),
            'scope_overrides' => CustomerSelectionCodec::normalizeScopeOverrides($payload['scope_overrides'] ?? []),
            'retention_tier' => (string) ($payload['retention_tier'] ?? '1y'),
            'last_scheduled_key' => '',
        ]);
    }

    /** @return array<string, mixed> */
    private static function decodeMs365Json(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private static function decodeSourceConfig(mixed $enc): array
    {
        if (!is_string($enc) || $enc === '') {
            return [];
        }
        try {
            $plain = decrypt($enc);
        } catch (\Throwable $_) {
            return [];
        }
        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function newJobUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function isUuid(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    private static function uuidToBinary(string $uuid): string
    {
        $hex = str_replace('-', '', strtolower($uuid));

        return hex2bin($hex);
    }

    private static function uuidToDbExpr(string $uuid): string
    {
        $norm = strtolower(trim($uuid));

        return "UUID_TO_BIN('" . addslashes($norm) . "')";
    }

    /**
     * @return array{bucket_id: int, bucket_name: string, owner_user_id: int}
     */
    private static function provisionJobBucket(int $clientId, int $backupUserId, string $jobId): array
    {
        $bootstrapPath = dirname(__DIR__, 3) . '/cloudstorage/lib/Client/Ms365StorageBootstrapService.php';
        if (!is_file($bootstrapPath)) {
            throw new \RuntimeException('MS365 storage bootstrap is not available.');
        }
        require_once $bootstrapPath;
        $res = \WHMCS\Module\Addon\CloudStorage\Client\Ms365StorageBootstrapService::ensureForJob(
            $clientId,
            $backupUserId,
            $jobId
        );
        if (($res['status'] ?? '') !== 'success' || empty($res['bucket']) || empty($res['owner_user'])) {
            throw new \RuntimeException((string) ($res['message'] ?? 'Failed to provision backup storage for job.'));
        }

        return [
            'bucket_id' => (int) ($res['bucket']->id ?? 0),
            'bucket_name' => (string) ($res['bucket']->name ?? ''),
            'owner_user_id' => (int) ($res['owner_user']->id ?? 0),
        ];
    }
}
