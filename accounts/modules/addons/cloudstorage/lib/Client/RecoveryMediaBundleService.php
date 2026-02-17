<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Aws\S3\S3Client;
use Illuminate\Database\Capsule\Manager as Capsule;

class RecoveryMediaBundleService
{
    private const TABLE = 's3_cloudbackup_driver_bundles';
    private const MODULE = 'cloudstorage';

    public static function ensureSchema(): void
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            Capsule::schema()->create(self::TABLE, function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('agent_id');
                $table->unsignedInteger('run_id')->nullable();
                $table->unsignedInteger('restore_point_id')->nullable();
                $table->string('profile', 20)->default('essential'); // essential|full|broad
                $table->string('bundle_kind', 20)->default('source'); // source|broad
                $table->string('status', 20)->default('ready'); // ready|warning|failed
                $table->string('artifact_name', 191)->nullable();
                $table->text('artifact_url')->nullable();
                $table->string('artifact_path', 255)->nullable();
                $table->unsignedInteger('dest_bucket_id')->nullable();
                $table->unsignedInteger('s3_user_id')->nullable();
                $table->string('dest_prefix', 255)->nullable();
                $table->string('sha256', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->text('warning_message')->nullable();
                $table->dateTime('backup_finished_at')->nullable();
                $table->timestamps();
                $table->index(['client_id', 'agent_id', 'profile', 'backup_finished_at'], 'idx_cab_client_agent_profile_time');
                $table->index(['client_id', 'agent_id', 'created_at'], 'idx_cab_client_agent_created');
                $table->index(['status', 'profile'], 'idx_cab_status_profile');
                $table->index(['dest_bucket_id', 'agent_id', 'profile'], 'idx_cab_dest_bucket_agent_profile');
            });
            return;
        }

        // Online schema compatibility for existing installs.
        try {
            if (!Capsule::schema()->hasColumn(self::TABLE, 'dest_bucket_id')) {
                Capsule::schema()->table(self::TABLE, function ($table) {
                    $table->unsignedInteger('dest_bucket_id')->nullable()->after('artifact_path');
                });
            }
            if (!Capsule::schema()->hasColumn(self::TABLE, 's3_user_id')) {
                Capsule::schema()->table(self::TABLE, function ($table) {
                    $table->unsignedInteger('s3_user_id')->nullable()->after('dest_bucket_id');
                });
            }
            if (!Capsule::schema()->hasColumn(self::TABLE, 'dest_prefix')) {
                Capsule::schema()->table(self::TABLE, function ($table) {
                    $table->string('dest_prefix', 255)->nullable()->after('s3_user_id');
                });
            }
        } catch (\Throwable $e) {
            // Best effort: older engines can fail ADD COLUMN; continue with legacy fields.
        }
    }

    public static function normalizeProfile(string $profile): string
    {
        $p = strtolower(trim($profile));
        if (in_array($p, ['essential', 'full', 'broad'], true)) {
            return $p;
        }
        return 'essential';
    }

    public static function normalizeMode(string $mode): string
    {
        $m = strtolower(trim($mode));
        if (in_array($m, ['fast', 'dissimilar'], true)) {
            return $m;
        }
        return 'fast';
    }

    public static function upsertBundle(array $payload): int
    {
        self::ensureSchema();

        $clientId = (int) ($payload['client_id'] ?? 0);
        $agentId = (int) ($payload['agent_id'] ?? 0);
        if ($clientId <= 0 || $agentId <= 0) {
            throw new \InvalidArgumentException('client_id and agent_id are required');
        }

        $profile = self::normalizeProfile((string) ($payload['profile'] ?? 'essential'));
        $bundleKind = strtolower(trim((string) ($payload['bundle_kind'] ?? 'source')));
        if (!in_array($bundleKind, ['source', 'broad'], true)) {
            $bundleKind = 'source';
        }

        $row = [
            'client_id' => $clientId,
            'tenant_id' => isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null,
            'agent_id' => $agentId,
            'run_id' => isset($payload['run_id']) ? (int) $payload['run_id'] : null,
            'restore_point_id' => isset($payload['restore_point_id']) ? (int) $payload['restore_point_id'] : null,
            'profile' => $profile,
            'bundle_kind' => $bundleKind,
            'status' => (string) ($payload['status'] ?? 'ready'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        foreach ([
            'artifact_name',
            'artifact_url',
            'artifact_path',
            'warning_message',
            'dest_prefix',
        ] as $optKey) {
            if (array_key_exists($optKey, $payload)) {
                $row[$optKey] = $payload[$optKey] === null ? null : (string) $payload[$optKey];
            }
        }
        if (array_key_exists('sha256', $payload)) {
            $row['sha256'] = $payload['sha256'] === null ? null : strtolower((string) $payload['sha256']);
        }
        foreach (['size_bytes', 'dest_bucket_id', 's3_user_id'] as $intKey) {
            if (array_key_exists($intKey, $payload)) {
                $row[$intKey] = $payload[$intKey] === null ? null : (int) $payload[$intKey];
            }
        }
        if (array_key_exists('backup_finished_at', $payload)) {
            $row['backup_finished_at'] = $payload['backup_finished_at'] !== '' ? (string) $payload['backup_finished_at'] : null;
        }

        $existingId = 0;
        if (!empty($row['run_id'])) {
            $existingId = (int) Capsule::table(self::TABLE)
                ->where('client_id', $clientId)
                ->where('agent_id', $agentId)
                ->where('run_id', $row['run_id'])
                ->where('profile', $profile)
                ->value('id');
        }

        if ($existingId > 0) {
            Capsule::table(self::TABLE)->where('id', $existingId)->update($row);
            return $existingId;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        return (int) Capsule::table(self::TABLE)->insertGetId($row);
    }

    public static function getLatestBundleForAgent(int $clientId, int $agentId, string $profile): ?array
    {
        self::ensureSchema();
        $p = self::normalizeProfile($profile);
        $row = Capsule::table(self::TABLE)
            ->where('client_id', $clientId)
            ->where('agent_id', $agentId)
            ->where('profile', $p)
            ->where('status', '!=', 'failed')
            ->orderByDesc(Capsule::raw('COALESCE(backup_finished_at, created_at)'))
            ->orderByDesc('id')
            ->first();
        return $row ? (array) $row : null;
    }

    public static function resolveMediaBuildSelection(int $clientId, int $agentId, string $mode): array
    {
        self::ensureSchema();
        $modeNorm = self::normalizeMode($mode);
        $warning = '';
        $selected = null;

        if ($modeNorm === 'fast') {
            $selected = self::getLatestBundleForAgent($clientId, $agentId, 'essential');
            if (!$selected) {
                $selected = self::getLatestBundleForAgent($clientId, $agentId, 'full');
                if ($selected) {
                    $warning = 'Essential source bundle not found. Falling back to full source bundle.';
                }
            }
        } else {
            $selected = self::getLatestBundleForAgent($clientId, $agentId, 'full');
            if (!$selected) {
                $selected = self::getLatestBundleForAgent($clientId, $agentId, 'essential');
                if ($selected) {
                    $warning = 'Full source bundle not found. Falling back to essential source bundle.';
                }
            }
        }

        $broadUrl = trim((string) self::getModuleSetting('recovery_media_broad_bundle_url', ''));
        $broadSha = trim((string) self::getModuleSetting('recovery_media_broad_bundle_sha256', ''));
        if (!$selected) {
            if ($broadUrl !== '') {
                $warning = 'Source driver bundle not found. Falling back to broad extras pack.';
            } else {
                $warning = 'Source driver bundle not found and no broad extras pack is configured. Media will be created with base ISO drivers only.';
            }
        }

        $baseIsoUrl = trim((string) self::getModuleSetting(
            'recovery_media_base_iso_url',
            'https://accounts.eazybackup.ca/recovery_media/e3-recovery-winpe-prod.iso'
        ));
        $baseIsoSha = trim((string) self::getModuleSetting('recovery_media_base_iso_sha256', ''));

        return [
            'mode' => $modeNorm,
            'base_iso_url' => $baseIsoUrl,
            'base_iso_sha256' => $baseIsoSha,
            'selected_bundle' => $selected,
            'selected_bundle_url' => $selected ? self::resolveBundleDownloadUrl($selected, 12 * 3600) : '',
            'selected_bundle_sha256' => $selected['sha256'] ?? '',
            'selected_bundle_profile' => $selected['profile'] ?? '',
            'broad_extras_url' => $broadUrl,
            'broad_extras_sha256' => $broadSha,
            'warning' => $warning,
            'has_source_bundle' => !empty($selected),
        ];
    }

    public static function buildBundleObjectKey(string $destPrefix, int $agentId, string $profile): string
    {
        $profileNorm = self::normalizeProfile($profile);
        $prefix = trim($destPrefix, '/');
        $suffix = 'driver-bundles/' . $agentId . '/' . $profileNorm . '.zip';
        if ($prefix === '') {
            return $suffix;
        }
        return $prefix . '/' . $suffix;
    }

    public static function resolveRunDestinationContext(int $clientId, int $agentId, int $runId): array
    {
        if ($runId <= 0) {
            return ['status' => 'fail', 'message' => 'run_id is required'];
        }
        $hasRunAgent = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_id');
        $hasJobAgent = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');
        $hasRunDestType = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'dest_type');
        $hasRunDestPrefix = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'dest_prefix');

        $query = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', 'j.id', '=', 'r.job_id')
            ->where('r.id', $runId)
            ->where('j.client_id', $clientId)
            ->select(
                'r.id as run_id',
                'r.job_id',
                'j.client_id',
                'j.dest_type as job_dest_type',
                'j.dest_prefix as job_dest_prefix',
                'j.dest_bucket_id',
                'j.s3_user_id'
            );
        if ($hasRunAgent) {
            $query->addSelect('r.agent_id as run_agent_id');
        }
        if ($hasJobAgent) {
            $query->addSelect('j.agent_id as job_agent_id');
        }
        if ($hasRunDestType) {
            $query->addSelect('r.dest_type as run_dest_type');
        }
        if ($hasRunDestPrefix) {
            $query->addSelect('r.dest_prefix as run_dest_prefix');
        }
        $row = $query->first();
        if (!$row) {
            return ['status' => 'fail', 'message' => 'Run not found'];
        }

        $runAgentId = isset($row->run_agent_id) ? (int) $row->run_agent_id : 0;
        $jobAgentId = isset($row->job_agent_id) ? (int) $row->job_agent_id : 0;
        if (($runAgentId > 0 && $runAgentId !== $agentId) || ($runAgentId <= 0 && $jobAgentId > 0 && $jobAgentId !== $agentId)) {
            return ['status' => 'fail', 'message' => 'Run is not assigned to this agent'];
        }

        $destType = strtolower(trim((string) ($row->run_dest_type ?? $row->job_dest_type ?? 's3')));
        if ($destType !== 's3') {
            return ['status' => 'unsupported', 'message' => 'Driver bundle storage is only supported for S3 destinations'];
        }

        $destBucketId = (int) ($row->dest_bucket_id ?? 0);
        $s3UserId = (int) ($row->s3_user_id ?? 0);
        if ($destBucketId <= 0 || $s3UserId <= 0) {
            return ['status' => 'fail', 'message' => 'Run destination is incomplete'];
        }

        $bucket = Capsule::table('s3_buckets')->where('id', $destBucketId)->first();
        if (!$bucket || trim((string) ($bucket->name ?? '')) === '') {
            return ['status' => 'fail', 'message' => 'Destination bucket not found'];
        }

        $settings = self::getModuleSettingsMap();
        $endpoint = trim((string) ($settings['cloudbackup_agent_s3_endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        }
        if ($endpoint === '') {
            $endpoint = 'https://s3.ca-central-1.eazybackup.com';
        }
        $region = trim((string) ($settings['cloudbackup_agent_s3_region'] ?? ($settings['s3_region'] ?? '')));

        [$decAk, $decSk] = self::decryptAccessKeysForUser($s3UserId, $settings);
        if ($decAk === '' || $decSk === '') {
            return ['status' => 'fail', 'message' => 'Unable to resolve destination credentials'];
        }

        return [
            'status' => 'success',
            'run_id' => (int) $row->run_id,
            'job_id' => (int) $row->job_id,
            'client_id' => (int) $row->client_id,
            'agent_id' => $agentId,
            'dest_type' => 's3',
            'dest_bucket_id' => $destBucketId,
            'dest_bucket_name' => (string) $bucket->name,
            'dest_prefix' => (string) ($row->run_dest_prefix ?? $row->job_dest_prefix ?? ''),
            's3_user_id' => $s3UserId,
            'dest_endpoint' => $endpoint,
            'dest_region' => $region,
            'dest_access_key' => $decAk,
            'dest_secret_key' => $decSk,
        ];
    }

    public static function uploadBundleObjectForRun(
        int $clientId,
        int $agentId,
        int $runId,
        string $profile,
        string $blob,
        string $sha256
    ): array {
        $ctx = self::resolveRunDestinationContext($clientId, $agentId, $runId);
        if (($ctx['status'] ?? 'fail') !== 'success') {
            return $ctx;
        }
        $objectKey = self::buildBundleObjectKey((string) ($ctx['dest_prefix'] ?? ''), $agentId, $profile);
        $s3 = self::connectS3ClientForContext($ctx);
        if (!$s3 instanceof S3Client) {
            return ['status' => 'fail', 'message' => 'Unable to connect destination S3 client'];
        }
        try {
            $s3->putObject([
                'Bucket' => $ctx['dest_bucket_name'],
                'Key' => $objectKey,
                'Body' => $blob,
                'ContentType' => 'application/zip',
                'Metadata' => [
                    'sha256' => strtolower(trim($sha256)),
                    'profile' => self::normalizeProfile($profile),
                ],
            ]);
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => 'Failed to upload bundle object'];
        }

        return [
            'status' => 'success',
            'object_key' => $objectKey,
            'artifact_url' => 's3://' . $ctx['dest_bucket_name'] . '/' . $objectKey,
            'dest_bucket_id' => (int) $ctx['dest_bucket_id'],
            'dest_prefix' => (string) $ctx['dest_prefix'],
            's3_user_id' => (int) $ctx['s3_user_id'],
        ];
    }

    public static function bundleExistsForRunDestination(int $clientId, int $agentId, int $runId, string $profile): array
    {
        $ctx = self::resolveRunDestinationContext($clientId, $agentId, $runId);
        if (($ctx['status'] ?? 'fail') !== 'success') {
            return $ctx;
        }
        $objectKey = self::buildBundleObjectKey((string) ($ctx['dest_prefix'] ?? ''), $agentId, $profile);
        $s3 = self::connectS3ClientForContext($ctx);
        if (!$s3 instanceof S3Client) {
            return ['status' => 'fail', 'message' => 'Unable to connect destination S3 client'];
        }
        try {
            $s3->headObject([
                'Bucket' => $ctx['dest_bucket_name'],
                'Key' => $objectKey,
            ]);
            $exists = true;
        } catch (\Throwable $e) {
            $exists = false;
        }
        return [
            'status' => 'success',
            'exists' => $exists,
            'object_key' => $objectKey,
            'dest_bucket_id' => (int) $ctx['dest_bucket_id'],
            'dest_prefix' => (string) $ctx['dest_prefix'],
        ];
    }

    public static function resolveBundleDownloadUrl(array $bundleRow, int $ttlSeconds = 43200): string
    {
        $artifactPath = trim((string) ($bundleRow['artifact_path'] ?? ''));
        $bucketId = (int) ($bundleRow['dest_bucket_id'] ?? 0);
        $s3UserId = (int) ($bundleRow['s3_user_id'] ?? 0);
        if ($artifactPath === '' || $bucketId <= 0 || $s3UserId <= 0) {
            return (string) ($bundleRow['artifact_url'] ?? '');
        }
        $bucket = Capsule::table('s3_buckets')->where('id', $bucketId)->first();
        if (!$bucket || trim((string) ($bucket->name ?? '')) === '') {
            return (string) ($bundleRow['artifact_url'] ?? '');
        }

        $settings = self::getModuleSettingsMap();
        $endpoint = trim((string) ($settings['cloudbackup_agent_s3_endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        }
        if ($endpoint === '') {
            $endpoint = 'https://s3.ca-central-1.eazybackup.com';
        }
        $region = trim((string) ($settings['cloudbackup_agent_s3_region'] ?? ($settings['s3_region'] ?? '')));
        [$decAk, $decSk] = self::decryptAccessKeysForUser($s3UserId, $settings);
        if ($decAk === '' || $decSk === '') {
            return (string) ($bundleRow['artifact_url'] ?? '');
        }

        $ctx = [
            'dest_endpoint' => $endpoint,
            'dest_region' => $region,
            'dest_access_key' => $decAk,
            'dest_secret_key' => $decSk,
        ];
        $s3 = self::connectS3ClientForContext($ctx);
        if (!$s3 instanceof S3Client) {
            return (string) ($bundleRow['artifact_url'] ?? '');
        }
        try {
            $cmd = $s3->getCommand('GetObject', [
                'Bucket' => (string) $bucket->name,
                'Key' => $artifactPath,
            ]);
            $request = $s3->createPresignedRequest($cmd, '+' . max(60, (int) $ttlSeconds) . ' seconds');
            return (string) $request->getUri();
        } catch (\Throwable $e) {
            return (string) ($bundleRow['artifact_url'] ?? '');
        }
    }

    private static function connectS3ClientForContext(array $ctx): ?S3Client
    {
        $bucketCtl = new BucketController(
            (string) ($ctx['dest_endpoint'] ?? ''),
            null,
            null,
            null,
            (string) ($ctx['dest_region'] ?? '')
        );
        $conn = $bucketCtl->connectS3ClientWithCredentials(
            (string) ($ctx['dest_access_key'] ?? ''),
            (string) ($ctx['dest_secret_key'] ?? '')
        );
        if (!is_array($conn) || ($conn['status'] ?? 'fail') !== 'success' || !($conn['s3client'] ?? null) instanceof S3Client) {
            return null;
        }
        return $conn['s3client'];
    }

    private static function getModuleSettingsMap(): array
    {
        $settings = [];
        try {
            $rows = Capsule::table('tbladdonmodules')
                ->where('module', self::MODULE)
                ->pluck('value', 'setting');
            foreach ($rows as $k => $v) {
                $settings[(string) $k] = $v;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $settings;
    }

    private static function decryptAccessKeysForUser(int $s3UserId, array $settings): array
    {
        if ($s3UserId <= 0) {
            return ['', ''];
        }
        $keys = Capsule::table('s3_user_access_keys')
            ->where('user_id', $s3UserId)
            ->orderByDesc('id')
            ->first();
        if (!$keys) {
            return ['', ''];
        }
        $encKeyPrimary = trim((string) ($settings['cloudbackup_encryption_key'] ?? ''));
        $encKeySecondary = trim((string) ($settings['encryption_key'] ?? ''));
        $accessKeyRaw = (string) ($keys->access_key ?? '');
        $secretKeyRaw = (string) ($keys->secret_key ?? '');

        $decryptWith = static function (?string $key) use ($accessKeyRaw, $secretKeyRaw): array {
            $ak = $accessKeyRaw;
            $sk = $secretKeyRaw;
            if ($key !== null && $key !== '' && $ak !== '') {
                $ak = HelperController::decryptKey($ak, $key);
            }
            if ($key !== null && $key !== '' && $sk !== '') {
                $sk = HelperController::decryptKey($sk, $key);
            }
            return [is_string($ak) ? $ak : '', is_string($sk) ? $sk : ''];
        };

        [$decAk, $decSk] = $decryptWith($encKeyPrimary);
        if ($decAk === '' || $decSk === '') {
            [$decAk2, $decSk2] = $decryptWith($encKeySecondary);
            if ($decAk2 !== '' && $decSk2 !== '') {
                return [$decAk2, $decSk2];
            }
        }
        return [$decAk, $decSk];
    }

    public static function getModuleSetting(string $key, $default = '')
    {
        try {
            $value = Capsule::table('tbladdonmodules')
                ->where('module', self::MODULE)
                ->where('setting', $key)
                ->value('value');
            if ($value === null || $value === '') {
                return $default;
            }
            return $value;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

