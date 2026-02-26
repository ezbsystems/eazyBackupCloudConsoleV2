<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Immutable source identity for Kopia retention.
 * Builds fingerprints, generates source_uuid, and ensures s3_kopia_repo_sources rows.
 */
class KopiaRetentionSourceService
{
    private const MODULE = 'cloudstorage';

    private const KOPIA_ENGINES = ['kopia', 'disk_image', 'hyperv'];

    /**
     * Build a deterministic fingerprint from engine, agent identity, and source identity.
     * Stable for same inputs; sensitive to source change.
     */
    public static function buildSourceFingerprint(string $engine, string $agentIdentity, string $sourceIdentity): string
    {
        return hash('sha256', strtolower(trim($engine)) . '|' . trim($agentIdentity) . '|' . trim($sourceIdentity));
    }

    /**
     * Generate a new immutable source_uuid (32-char hex).
     */
    public static function generateSourceUuid(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Ensure a source row exists in s3_kopia_repo_sources for the given job.
     * Creates row if missing. Returns source data or null when not applicable.
     *
     * @param string|int $jobId Job ID as UUID string (when UUID schema) or numeric id (legacy)
     * @return array|null ['source_uuid' => string, 'id' => int, ...] or null
     */
    public static function ensureRepoSourceForJob($jobId): ?array
    {
        try {
            if (!Capsule::schema()->hasTable('s3_kopia_repo_sources')
                || !Capsule::schema()->hasTable('s3_kopia_repos')) {
                return null;
            }

            $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
            $hasKopiaJobIdBinary = self::hasBinaryJobIdColumn('s3_kopia_repo_sources');
            $useUuidPath = $hasJobIdPk && $hasKopiaJobIdBinary && is_string($jobId) && UuidBinary::isUuid($jobId);

            return Capsule::connection()->transaction(function () use ($jobId, $hasJobIdPk, $hasKopiaJobIdBinary, $useUuidPath) {
                if ($useUuidPath) {
                    $jobIdNorm = UuidBinary::normalize($jobId);
                    $job = Capsule::table('s3_cloudbackup_jobs')
                        ->whereRaw('job_id = ' . UuidBinary::toDbExpr($jobIdNorm))
                        ->lockForUpdate()
                        ->first();
                } else {
                    $jobIdInt = is_int($jobId) ? $jobId : (int) $jobId;
                    if ($jobIdInt <= 0) {
                        return null;
                    }
                    $job = Capsule::table('s3_cloudbackup_jobs')
                        ->where('id', $jobIdInt)
                        ->lockForUpdate()
                        ->first();
                }

                if (!$job) {
                    return null;
                }

                $sourceType = strtolower(trim((string) ($job->source_type ?? '')));
                $engine = strtolower(trim((string) ($job->engine ?? 'kopia')));
                if ($sourceType !== 'local_agent' || !in_array($engine, self::KOPIA_ENGINES, true)) {
                    return null;
                }

                $repositoryId = trim((string) ($job->repository_id ?? ''));
                if ($repositoryId === '') {
                    return null;
                }

                $repoRow = Capsule::table('s3_kopia_repos')->where('repository_id', $repositoryId)->first();
                if (!$repoRow) {
                    return null;
                }
                $repoId = (int) $repoRow->id;

                $existing = Capsule::table('s3_kopia_repo_sources')
                    ->where('repo_id', $repoId)
                    ->when(
                        $useUuidPath && $hasKopiaJobIdBinary,
                        fn ($q) => $q->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobId))),
                        fn ($q) => $q->where('job_id', $useUuidPath ? $jobId : (int) $jobId)
                    )
                    ->first();

                if ($existing) {
                    $outJobId = $useUuidPath ? $jobId : (int) ($existing->job_id ?? $jobId);
                    return [
                        'id' => (int) $existing->id,
                        'source_uuid' => (string) $existing->source_uuid,
                        'repo_id' => $repoId,
                        'job_id' => $outJobId,
                        'lifecycle' => (string) ($existing->lifecycle ?? 'active'),
                    ];
                }

                $sourceUuid = self::generateSourceUuid();

                $insertJobId = $useUuidPath && $hasKopiaJobIdBinary
                    ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($jobId)))
                    : ($useUuidPath ? $jobId : (int) ($job->id ?? $jobId));

                Capsule::table('s3_kopia_repo_sources')->insert([
                    'repo_id' => $repoId,
                    'source_uuid' => $sourceUuid,
                    'lifecycle' => 'active',
                    'job_id' => $insertJobId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $row = Capsule::table('s3_kopia_repo_sources')
                    ->where('repo_id', $repoId)
                    ->where('source_uuid', $sourceUuid)
                    ->first();

                $outJobId = $row ? ($useUuidPath ? $jobId : (int) ($row->job_id ?? $jobId)) : $jobId;
                return $row ? [
                    'id' => (int) $row->id,
                    'source_uuid' => (string) $row->source_uuid,
                    'repo_id' => $repoId,
                    'job_id' => $outJobId,
                    'lifecycle' => (string) ($row->lifecycle ?? 'active'),
                ] : null;
            });
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'ensureRepoSourceForJob', ['job_id' => $jobId], $e->getMessage(), [], []);
            return null;
        }
    }

    /**
     * Retire sources for a job (lifecycle=retired, optional retired_at).
     * Returns repo_ids of affected repos for enqueue purposes.
     *
     * @param string|int $jobId Job ID as UUID string or numeric id
     * @return array<int> Affected repo_ids
     */
    public static function retireByJobId($jobId): array
    {
        try {
            if (!Capsule::schema()->hasTable('s3_kopia_repo_sources')) {
                return [];
            }
            $lifecycleCol = 'lifecycle';
            if (!Capsule::schema()->hasColumn('s3_kopia_repo_sources', $lifecycleCol)) {
                logModuleCall(self::MODULE, 'retireByJobId', ['job_id' => $jobId], 's3_kopia_repo_sources.lifecycle column missing', [], []);
                return [];
            }

            $hasKopiaJobIdBinary = self::hasBinaryJobIdColumn('s3_kopia_repo_sources');
            $useUuidPath = $hasKopiaJobIdBinary && is_string($jobId) && UuidBinary::isUuid($jobId);

            $query = Capsule::table('s3_kopia_repo_sources')
                ->where($lifecycleCol, 'active');

            if ($useUuidPath) {
                $query->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobId)));
            } else {
                $query->where('job_id', is_int($jobId) ? $jobId : (int) $jobId);
            }

            $repoIds = $query->pluck('repo_id')
                ->unique()
                ->values()
                ->toArray();
            if (empty($repoIds)) {
                return [];
            }
            $now = date('Y-m-d H:i:s');
            $update = ['lifecycle' => 'retired', 'updated_at' => $now];
            if (Capsule::schema()->hasColumn('s3_kopia_repo_sources', 'retired_at')) {
                $update['retired_at'] = $now;
            }
            $updateQuery = Capsule::table('s3_kopia_repo_sources');
            if ($useUuidPath) {
                $updateQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobId)));
            } else {
                $updateQuery->where('job_id', is_int($jobId) ? $jobId : (int) $jobId);
            }
            $updateQuery->update($update);
            return array_map('intval', $repoIds);
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'retireByJobId', ['job_id' => $jobId], $e->getMessage(), [], []);
            return [];
        }
    }

    /**
     * Retire active sources for all kopia-family jobs belonging to an agent.
     * Returns array of repo_id => 1 for unique repos affected.
     *
     * @return array<int, int> [repo_id => 1, ...] for dedupe
     */
    public static function retireByAgentId(int $agentId): array
    {
        try {
            if (!Capsule::schema()->hasTable('s3_kopia_repo_sources')
                || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
                return [];
            }
            $hasAgentIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');
            if (!$hasAgentIdCol) {
                return [];
            }

            $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');

            if ($hasJobIdPk) {
                $jobIds = Capsule::table('s3_cloudbackup_jobs')
                    ->where('agent_id', $agentId)
                    ->where('source_type', 'local_agent')
                    ->whereRaw('LOWER(engine) IN (\'kopia\', \'disk_image\', \'hyperv\')')
                    ->selectRaw('BIN_TO_UUID(job_id) as job_id_uuid')
                    ->pluck('job_id_uuid')
                    ->filter()
                    ->values()
                    ->toArray();
            } else {
                $jobIds = Capsule::table('s3_cloudbackup_jobs')
                    ->where('agent_id', $agentId)
                    ->where('source_type', 'local_agent')
                    ->whereRaw('LOWER(engine) IN (\'kopia\', \'disk_image\', \'hyperv\')')
                    ->pluck('id')
                    ->toArray();
            }

            if (empty($jobIds)) {
                return [];
            }
            $repoIds = [];
            foreach ($jobIds as $jid) {
                foreach (self::retireByJobId($jid) as $rid) {
                    $repoIds[$rid] = 1;
                }
            }
            return $repoIds;
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'retireByAgentId', ['agent_id' => $agentId], $e->getMessage(), [], []);
            return [];
        }
    }

    /**
     * Derive source identity string from job for fingerprinting.
     */
    private static function deriveSourceIdentity($job, string $engine): string
    {
        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $hasHypervJobIdBinary = Capsule::schema()->hasTable('s3_hyperv_vms')
            && Capsule::schema()->hasColumn('s3_hyperv_vms', 'job_id')
            && self::hasBinaryJobIdColumn('s3_hyperv_vms');

        if ($engine === 'hyperv') {
            $jobIdentifier = ($hasJobIdPk && $hasHypervJobIdBinary && isset($job->job_id))
                ? $job->job_id
                : ($job->id ?? null);
            if ($jobIdentifier === null) {
                return 'default';
            }
            $vms = Capsule::table('s3_hyperv_vms')
                ->where('job_id', $jobIdentifier)
                ->where('backup_enabled', true)
                ->orderBy('vm_guid')
                ->pluck('vm_guid')
                ->toArray();
            $vms = array_map('strval', $vms);
            sort($vms);
            return implode(',', $vms) ?: 'default';
        }
        if ($engine === 'disk_image') {
            return trim((string) ($job->disk_source_volume ?? $job->source_path ?? '')) ?: 'default';
        }
        $paths = [];
        if (!empty($job->source_paths_json)) {
            $decoded = json_decode($job->source_paths_json, true);
            if (is_array($decoded)) {
                $paths = array_values(array_map('strval', $decoded));
            }
        }
        if (empty($paths) && !empty($job->source_path)) {
            $paths = [trim((string) $job->source_path)];
        }
        sort($paths);
        return implode('|', $paths) ?: 'default';
    }

    /**
     * Check if given table's job_id column is BINARY(16) (UUID schema).
     */
    private static function hasBinaryJobIdColumn(string $table): bool
    {
        try {
            if (!Capsule::schema()->hasColumn($table, 'job_id')) {
                return false;
            }
            $type = Capsule::schema()->getColumnType($table, 'job_id');
            return $type !== null && stripos((string) $type, 'binary') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
