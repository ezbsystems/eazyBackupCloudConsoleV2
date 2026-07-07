<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Executes scheduled MS365 jobs stored in s3_cloudbackup_jobs.
 */
final class Ms365JobScheduler
{
    /**
     * @return array{started: int, skipped: int}
     */
    public static function runDueJobs(): array
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return ['started' => 0, 'skipped' => 0];
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $jobs = Capsule::table('s3_cloudbackup_jobs')
            ->where('source_type', 'ms365')
            ->where('status', 'active')
            ->get();

        $started = 0;
        $skipped = 0;
        foreach ($jobs as $job) {
            $scheduleJson = json_decode((string) ($job->schedule_json ?? ''), true);
            if (!is_array($scheduleJson)) {
                continue;
            }

            $jobTimezone = trim((string) ($job->timezone ?? ''));
            $minuteKey = Ms365ScheduleAssigner::localMinuteKey($scheduleJson, $now, $jobTimezone !== '' ? $jobTimezone : null);

            $lastKey = (string) ($scheduleJson['last_scheduled_key'] ?? '');
            if ($lastKey === $minuteKey) {
                continue;
            }

            if (!Ms365ScheduleAssigner::isDueNow($scheduleJson, $now, $jobTimezone !== '' ? $jobTimezone : null)) {
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

            $scopeOverrides = CustomerSelectionCodec::normalizeScopeOverrides($scheduleJson['scope_overrides'] ?? []);

            $active = Ms365JobOverlapGuard::findActiveBackupBatch($jobId);
            if ($active !== null) {
                try {
                    Ms365BatchRunRepository::recordScheduledSkip($jobId, $active['run_id']);
                    self::persistScheduledKey($jobId, $scheduleJson, $minuteKey);
                    Ms365CustomerError::log(
                        'Ms365JobScheduler',
                        new \RuntimeException(
                            'Scheduled backup skipped for job ' . $jobId
                            . ': active batch ' . $active['run_id'] . ' (' . $active['status'] . ')',
                        ),
                    );
                    $skipped++;
                } catch (\Throwable $e) {
                    Ms365CustomerError::log('Ms365JobScheduler', $e);
                }
                continue;
            }

            try {
                $inventory = CustomerInventoryService::loadForBackupUser($clientId, $backupUserId);
                $resolved = CustomerSelectionCodec::resolveForExecution(
                    array_values(array_map('strval', $selectedIds)),
                    $scopeOverrides,
                    $inventory,
                );

                CustomerBackupService::startCustomBackup(
                    $clientId,
                    $backupUserId,
                    $resolved['selected_resource_ids'],
                    $jobId,
                    'schedule',
                    $resolved['scope_overrides'],
                );

                self::persistScheduledKey($jobId, $scheduleJson, $minuteKey);
                $started++;
            } catch (\Throwable $e) {
                Ms365CustomerError::log('Ms365JobScheduler', $e);
            }
        }

        return ['started' => $started, 'skipped' => $skipped];
    }

    /** @param array<string, mixed> $scheduleJson */
    private static function persistScheduledKey(string $jobId, array $scheduleJson, string $minuteKey): void
    {
        $scheduleJson['last_scheduled_key'] = $minuteKey;
        Capsule::table('s3_cloudbackup_jobs')
            ->whereRaw('job_id = UUID_TO_BIN(?)', [$jobId])
            ->update([
                'schedule_json' => json_encode($scheduleJson, JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
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
