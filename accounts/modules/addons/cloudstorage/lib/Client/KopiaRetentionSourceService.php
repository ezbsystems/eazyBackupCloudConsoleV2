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
     * @return array|null ['source_uuid' => string, 'id' => int, ...] or null
     */
    public static function ensureRepoSourceForJob(int $jobId): ?array
    {
        try {
            if (!Capsule::schema()->hasTable('s3_kopia_repo_sources')
                || !Capsule::schema()->hasTable('s3_kopia_repos')) {
                return null;
            }

            $job = Capsule::table('s3_cloudbackup_jobs')->where('id', $jobId)->first();
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
                ->where('job_id', $jobId)
                ->first();

            if ($existing) {
                return [
                    'id' => (int) $existing->id,
                    'source_uuid' => (string) $existing->source_uuid,
                    'repo_id' => $repoId,
                    'job_id' => $jobId,
                    'lifecycle' => (string) ($existing->lifecycle ?? 'active'),
                ];
            }

            $agentIdentity = (string) ($job->agent_id ?? '0');
            $sourceIdentity = self::deriveSourceIdentity($job, $engine);
            $sourceUuid = self::generateSourceUuid();

            Capsule::table('s3_kopia_repo_sources')->insert([
                'repo_id' => $repoId,
                'source_uuid' => $sourceUuid,
                'lifecycle' => 'active',
                'job_id' => $jobId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $row = Capsule::table('s3_kopia_repo_sources')
                ->where('repo_id', $repoId)
                ->where('source_uuid', $sourceUuid)
                ->first();

            return $row ? [
                'id' => (int) $row->id,
                'source_uuid' => (string) $row->source_uuid,
                'repo_id' => $repoId,
                'job_id' => $jobId,
                'lifecycle' => (string) ($row->lifecycle ?? 'active'),
            ] : null;
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, 'ensureRepoSourceForJob', ['job_id' => $jobId], $e->getMessage(), [], []);
            return null;
        }
    }

    /**
     * Derive source identity string from job for fingerprinting.
     */
    private static function deriveSourceIdentity($job, string $engine): string
    {
        if ($engine === 'hyperv') {
            $vms = Capsule::table('s3_hyperv_vms')
                ->where('job_id', $job->id)
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
}
