<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Executes scheduled MS365 jobs stored in s3_cloudbackup_jobs.
 */
final class Ms365JobScheduler
{
    public static function runDueJobs(): int
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return 0;
        }

        $now = new \DateTimeImmutable('now');
        $minuteKey = $now->format('Y-m-d-H-i');

        $jobs = Capsule::table('s3_cloudbackup_jobs')
            ->where('source_type', 'ms365')
            ->where('status', 'active')
            ->get();

        $started = 0;
        foreach ($jobs as $job) {
            $scheduleJson = json_decode((string) ($job->schedule_json ?? ''), true);
            if (!is_array($scheduleJson)) {
                continue;
            }

            $lastKey = (string) ($scheduleJson['last_scheduled_key'] ?? '');
            if ($lastKey === $minuteKey) {
                continue;
            }

            if (!Ms365ScheduleAssigner::isDueNow($scheduleJson, $now)) {
                continue;
            }

            $clientId = (int) ($job->client_id ?? 0);
            $backupUserId = (int) ($job->backup_user_id ?? 0);
            if ($clientId <= 0 || $backupUserId <= 0) {
                continue;
            }

            $jobId = self::binaryJobIdToString($job->job_id ?? '');
            if ($jobId === '') {
                continue;
            }

            $selectedIds = $scheduleJson['selected_resource_ids'] ?? [];
            if (!is_array($selectedIds) || $selectedIds === []) {
                continue;
            }

            try {
                CustomerBackupService::startCustomBackup(
                    $clientId,
                    $backupUserId,
                    array_values(array_map('strval', $selectedIds)),
                    $jobId,
                    'schedule',
                );

                $scheduleJson['last_scheduled_key'] = $minuteKey;
                Capsule::table('s3_cloudbackup_jobs')
                    ->whereRaw('job_id = UUID_TO_BIN(?)', [$jobId])
                    ->update([
                        'schedule_json' => json_encode($scheduleJson, JSON_UNESCAPED_SLASHES),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                $started++;
            } catch (\Throwable $e) {
                Ms365CustomerError::log('Ms365JobScheduler', $e);
            }
        }

        return $started;
    }

    private static function binaryJobIdToString(mixed $binary): string
    {
        if (!is_string($binary) || strlen($binary) !== 16) {
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
