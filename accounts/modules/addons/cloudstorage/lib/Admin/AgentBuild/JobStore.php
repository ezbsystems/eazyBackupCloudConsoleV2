<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

use Illuminate\Database\Capsule\Manager as Capsule;

class JobStore
{
    /** Ordered list of pipeline step keys. */
    public const STEPS = [
        'git_sync',
        'go_test',
        'linux_build',
        'windows_build',
        'recovery_build',
        'windows_stage',
        'windows_inno',
        'windows_sign',
        'windows_fetch',
        'verify',
        'publish',
    ];

    public static function storageRoot(): string
    {
        return realpath(__DIR__ . '/../../../') . '/storage/builds';
    }

    public static function jobLogDir(int $jobId): string
    {
        $dir = self::storageRoot() . '/' . $jobId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public static function createJob(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Capsule::table('s3_agent_build_jobs')->insertGetId([
            'created_by_admin_id' => $data['admin_id'] ?? null,
            'platform'            => $data['platform'] ?? 'both',
            'git_ref'             => $data['git_ref'] ?? 'main',
            'version_label'       => $data['version_label'] ?? null,
            'flags_json'          => json_encode($data['flags'] ?? new \stdClass()),
            'status'              => 'queued',
            'host_runner'         => gethostname() ?: null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $platform = $data['platform'] ?? 'both';
        $flags    = $data['flags'] ?? [];
        $includeRecovery = !empty($flags['include_recovery']);
        $sign = !empty($flags['sign']);
        $publish = !empty($flags['publish']);
        $runTests = !empty($flags['run_tests']);

        $seq = 0;
        foreach (self::STEPS as $stepKey) {
            $skip = false;
            if ($stepKey === 'go_test' && !$runTests) {
                $skip = true;
            }
            if ($stepKey === 'linux_build' && !in_array($platform, ['linux', 'both'], true)) {
                $skip = true;
            }
            if (in_array($stepKey, ['windows_build', 'windows_stage', 'windows_inno', 'windows_fetch'], true)
                && !in_array($platform, ['windows', 'both', 'recovery_iso'], true)) {
                $skip = true;
            }
            if ($stepKey === 'recovery_build' && !$includeRecovery && $platform !== 'recovery_iso') {
                $skip = true;
            }
            if ($stepKey === 'windows_sign' && !$sign) {
                $skip = true;
            }
            if ($stepKey === 'publish' && !$publish) {
                $skip = true;
            }

            Capsule::table('s3_agent_build_steps')->insert([
                'job_id'     => $id,
                'step_key'   => $stepKey,
                'seq'        => $seq++,
                'status'     => $skip ? 'skipped' : 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $id;
    }

    public static function claimNextQueuedJob(): ?array
    {
        return Capsule::connection()->transaction(function () {
            $row = Capsule::table('s3_agent_build_jobs')
                ->where('status', 'queued')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();
            if (!$row) {
                return null;
            }
            Capsule::table('s3_agent_build_jobs')
                ->where('id', $row->id)
                ->update([
                    'status'     => 'running',
                    'started_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            return (array) $row;
        });
    }

    public static function getJob(int $id): ?array
    {
        $row = Capsule::table('s3_agent_build_jobs')->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public static function listJobs(int $limit = 50): array
    {
        return array_map(static fn($r) => (array) $r, Capsule::table('s3_agent_build_jobs')
            ->orderBy('id', 'desc')->limit($limit)->get()->all());
    }

    public static function steps(int $jobId): array
    {
        return array_map(static fn($r) => (array) $r, Capsule::table('s3_agent_build_steps')
            ->where('job_id', $jobId)->orderBy('seq', 'asc')->get()->all());
    }

    public static function step(int $jobId, string $stepKey): ?array
    {
        $r = Capsule::table('s3_agent_build_steps')
            ->where('job_id', $jobId)->where('step_key', $stepKey)->first();
        return $r ? (array) $r : null;
    }

    public static function updateJob(int $id, array $fields): void
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        Capsule::table('s3_agent_build_jobs')->where('id', $id)->update($fields);
    }

    public static function updateStep(int $jobId, string $stepKey, array $fields): void
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        Capsule::table('s3_agent_build_steps')
            ->where('job_id', $jobId)->where('step_key', $stepKey)->update($fields);
    }

    public static function isCancelRequested(int $jobId): bool
    {
        $row = Capsule::table('s3_agent_build_jobs')->where('id', $jobId)->first();
        return $row && $row->status === 'cancelled';
    }

    public static function recordRelease(array $data): int
    {
        return (int) Capsule::table('s3_agent_releases')->insertGetId(array_merge([
            'created_at'   => date('Y-m-d H:i:s'),
            'published_at' => date('Y-m-d H:i:s'),
            'is_latest'    => 1,
        ], $data));
    }

    public static function clearLatest(string $platform, string $filename): void
    {
        Capsule::table('s3_agent_releases')
            ->where('platform', $platform)
            ->where('artifact_filename', $filename)
            ->update(['is_latest' => 0]);
    }

    public static function listReleases(int $limit = 100): array
    {
        return array_map(static fn($r) => (array) $r, Capsule::table('s3_agent_releases')
            ->orderBy('id', 'desc')->limit($limit)->get()->all());
    }

    /**
     * Validate a semantic version string of the form MAJOR.MINOR.PATCH
     * (optionally with a leading 'v', which is stripped). Returns the
     * normalized "X.Y.Z" string, or null when invalid.
     */
    public static function normalizeSemver(string $v): ?string
    {
        $v = trim($v);
        if ($v !== '' && ($v[0] === 'v' || $v[0] === 'V')) {
            $v = substr($v, 1);
        }
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $v, $m)) {
            return ((int) $m[1]) . '.' . ((int) $m[2]) . '.' . ((int) $m[3]);
        }
        return null;
    }

    /**
     * Suggest the next semantic version by bumping the patch of the highest
     * existing MAJOR.MINOR.PATCH version found across releases and build jobs.
     * Falls back to a sensible default when no semver history exists. Legacy
     * date-style labels (e.g. 2026.05.29-104802) are ignored.
     */
    public static function nextSuggestedVersion(string $default = '1.0.0'): string
    {
        $labels = [];
        try {
            $labels = array_merge(
                Capsule::table('s3_agent_releases')->pluck('version_label')->all(),
                Capsule::table('s3_agent_build_jobs')->pluck('version_label')->all()
            );
        } catch (\Throwable $e) {
            // Tables may not exist yet on a fresh install.
        }

        $best = null; // [major, minor, patch]
        foreach ($labels as $label) {
            $norm = self::normalizeSemver((string) $label);
            if ($norm === null) {
                continue;
            }
            [$maj, $min, $pat] = array_map('intval', explode('.', $norm));
            $cand = [$maj, $min, $pat];
            if ($best === null || self::compareSemverParts($cand, $best) > 0) {
                $best = $cand;
            }
        }

        if ($best === null) {
            return $default;
        }
        return $best[0] . '.' . $best[1] . '.' . ($best[2] + 1);
    }

    /** Compare two [major, minor, patch] arrays. Returns -1, 0, or 1. */
    private static function compareSemverParts(array $a, array $b): int
    {
        for ($i = 0; $i < 3; $i++) {
            $av = (int) ($a[$i] ?? 0);
            $bv = (int) ($b[$i] ?? 0);
            if ($av !== $bv) {
                return $av < $bv ? -1 : 1;
            }
        }
        return 0;
    }
}
