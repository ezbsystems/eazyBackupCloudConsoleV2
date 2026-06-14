<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use WHMCS\Database\Capsule;

final class BuildJobStore
{
    public const STEPS = ['validate', 'git_sync', 'go_test', 'go_build', 'checksum', 'publish'];

    public static function jobLogDir(int $jobId): string
    {
        $dir = FleetSettings::buildStorageRoot() . '/' . $jobId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }

    public static function createJob(array $data): int
    {
        $now = time();
        $flags = $data['flags'] ?? [];
        $runTests = !array_key_exists('run_tests', $flags) || !empty($flags['run_tests']);
        $gitSync = !empty($flags['git_sync']);

        $id = (int) Capsule::table('ms365_worker_build_jobs')->insertGetId([
            'created_by_admin_id' => $data['admin_id'] ?? null,
            'git_ref' => (string) ($data['git_ref'] ?? 'main'),
            'version_label' => (string) ($data['version_label'] ?? ''),
            'flags_json' => json_encode($flags),
            'status' => 'queued',
            'host_runner' => gethostname() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $seq = 0;
        foreach (self::STEPS as $stepKey) {
            $skip = false;
            if ($stepKey === 'go_test' && !$runTests) {
                $skip = true;
            }
            if ($stepKey === 'git_sync' && !$gitSync) {
                $skip = true;
            }
            Capsule::table('ms365_worker_build_steps')->insert([
                'job_id' => $id,
                'step_key' => $stepKey,
                'seq' => $seq++,
                'status' => $skip ? 'skipped' : 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $id;
    }

    public static function claimNextQueuedJob(): ?array
    {
        return Capsule::connection()->transaction(function () {
            $row = Capsule::table('ms365_worker_build_jobs')
                ->where('status', 'queued')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if (!$row) {
                return null;
            }
            $now = time();
            Capsule::table('ms365_worker_build_jobs')->where('id', $row->id)->update([
                'status' => 'running',
                'started_at' => $now,
                'updated_at' => $now,
            ]);

            return (array) $row;
        });
    }

    public static function getJob(int $id): ?array
    {
        $row = Capsule::table('ms365_worker_build_jobs')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /** @return list<array<string, mixed>> */
    public static function steps(int $jobId): array
    {
        return Capsule::table('ms365_worker_build_steps')
            ->where('job_id', $jobId)
            ->orderBy('seq')
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public static function listRecent(int $limit = 25): array
    {
        return Capsule::table('ms365_worker_build_jobs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public static function updateJob(int $id, array $fields): void
    {
        $fields['updated_at'] = time();
        Capsule::table('ms365_worker_build_jobs')->where('id', $id)->update($fields);
    }

    public static function updateStep(int $jobId, string $stepKey, array $fields): void
    {
        $fields['updated_at'] = time();
        Capsule::table('ms365_worker_build_steps')
            ->where('job_id', $jobId)
            ->where('step_key', $stepKey)
            ->update($fields);
    }

    public static function tailLog(int $jobId, string $stepKey, int $offset = 0): string
    {
        $path = self::jobLogDir($jobId) . '/' . $stepKey . '.log';
        if (!is_file($path)) {
            return '';
        }
        $content = (string) file_get_contents($path);

        return $offset > 0 && $offset < strlen($content) ? substr($content, $offset) : $content;
    }
}
