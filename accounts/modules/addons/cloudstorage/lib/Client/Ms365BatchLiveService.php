<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Ms365Backup\BackupRunRepository;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\ProgressLogger;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerProcess;
use WHMCS\Database\Capsule;

require_once dirname(__DIR__) . '/Ms365BackupBootstrap.php';

/**
 * Bridges MS365 batch runs into the e3 Cloud Backup live progress / log APIs.
 */
final class Ms365BatchLiveService
{
    public static function isMs365BatchRun(array $run): bool
    {
        return strtolower((string) ($run['engine'] ?? '')) === 'ms365';
    }

    /**
     * @return array<string, mixed>
     */
    public static function aggregateProgress(string $batchRunId, int $clientId, ?array $parentRun = null): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        Ms365BatchRunRepository::updateLiveSnapshot($batchRunId);
        if (Ms365BatchRunRepository::isRestoreBatch($batchRunId)) {
            Ms365BatchRunRepository::syncFromRestoreChildren($batchRunId);
            WorkerClaimService::reconcileStaleRestoreBatch($batchRunId);
            Ms365BatchRunRepository::updateLiveSnapshotForRestore($batchRunId);
        } else {
            Ms365BatchRunRepository::syncFromChildren($batchRunId);
        }

        if ($parentRun === null) {
            $parentRun = self::loadParentRun($batchRunId, $clientId);
        }
        if ($parentRun === null) {
            throw new \RuntimeException('Run not found or access denied.');
        }

        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        $isRestore = Ms365BatchRunRepository::isRestoreBatch($batchRunId);
        $agg = Ms365BatchRunRepository::computeAggregates($children, $isRestore);
        $displayStatus = (string) ($parentRun['status'] ?? $agg['status']);
        if (in_array($displayStatus, ['running', 'starting', 'queued'], true)) {
            $displayStatus = (string) $agg['status'];
            if ($displayStatus === 'queued') {
                $displayStatus = 'running';
            }
        }

        $serverTimezone = date_default_timezone_get() ?: 'UTC';
        $startedAtEpochMs = self::epochMs($parentRun['started_at'] ?? null, $serverTimezone);
        $finishedAtEpochMs = self::epochMs($parentRun['finished_at'] ?? null, $serverTimezone);

        return [
            'status' => $displayStatus,
            'error_summary' => (string) ($parentRun['error_summary'] ?? ''),
            'worker_host' => '',
            'progress_pct' => self::resolveProgressPct($parentRun, $agg),
            'bytes_total' => $parentRun['bytes_total'] ?? $agg['bytes_total'],
            'bytes_transferred' => (int) ($parentRun['bytes_transferred'] ?? $agg['bytes_transferred']),
            'bytes_processed' => (int) ($parentRun['bytes_processed'] ?? $agg['bytes_processed']),
            'objects_total' => (int) ($parentRun['objects_total'] ?? $agg['objects_total']),
            'objects_transferred' => (int) ($parentRun['objects_transferred'] ?? $agg['objects_transferred']),
            'files_done' => (int) $agg['items_done'],
            'files_total' => (int) $agg['items_total'],
            'folders_done' => null,
            'speed_bytes_per_sec' => $parentRun['speed_bytes_per_sec'] ?? null,
            'eta_seconds' => $parentRun['eta_seconds'] ?? null,
            'current_item' => CustomerFacingTextSanitizer::scrubLogMessage(
                (string) ($parentRun['current_item'] ?? $agg['current_item'] ?? '')
            ) ?: null,
            'stage' => self::resolveStageLabel($parentRun, $agg, $isRestore),
            'started_at' => $parentRun['started_at'] ?? null,
            'finished_at' => $parentRun['finished_at'] ?? null,
            'started_at_epoch_ms' => $startedAtEpochMs,
            'finished_at_epoch_ms' => $finishedAtEpochMs,
            'log_excerpt' => '',
            'log_lines' => [],
            'formatted_log_excerpt' => null,
            'entries' => [],
            'log_excerpt_hash' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function aggregateEvents(string $batchRunId, int $clientId, int $sinceId, int $limit, ?\DateTimeZone $userTz = null): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        if (!Capsule::schema()->hasTable('ms365_backup_log_lines')) {
            return [];
        }

        $childIds = array_column(Ms365BatchRunRepository::getBatchChildren($batchRunId), 'id');
        if ($childIds === []) {
            return [];
        }

        $childrenById = [];
        foreach (Ms365BatchRunRepository::getBatchChildren($batchRunId) as $child) {
            $childrenById[(string) $child['id']] = $child;
        }

        $query = Capsule::table('ms365_backup_log_lines')
            ->whereIn('run_id', $childIds)
            ->orderBy('id', 'asc');
        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        $out = [];
        foreach ($query->limit($limit)->get() as $row) {
            $line = (array) $row;
            $child = $childrenById[(string) ($line['run_id'] ?? '')] ?? [];
            $out[] = Ms365LogFormatter::toEvent($line, $child, $userTz);
        }

        return $out;
    }

    /**
     * @return array{
     *   backup_log: string,
     *   validation_log: null,
     *   has_validation: bool,
     *   structured_logs: list<array<string, mixed>>,
     *   run_summary: array<string, mixed>
     * }
     */
    public static function aggregateStructuredLogs(string $batchRunId, int $clientId, ?\DateTimeZone $userTz = null): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        Ms365BatchRunRepository::syncFromChildren($batchRunId);
        $parentRun = self::loadParentRun($batchRunId, $clientId);
        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        $agg = Ms365BatchRunRepository::computeAggregates($children);

        $structuredLogs = [];
        if (Capsule::schema()->hasTable('ms365_backup_log_lines')) {
            $childIds = array_column($children, 'id');
            if ($childIds !== []) {
                $childrenById = [];
                foreach ($children as $child) {
                    $childrenById[(string) $child['id']] = $child;
                }
                $lines = Capsule::table('ms365_backup_log_lines')
                    ->whereIn('run_id', $childIds)
                    ->orderBy('id', 'asc')
                    ->limit(5000)
                    ->get();
                foreach ($lines as $row) {
                    $line = (array) $row;
                    $child = $childrenById[(string) ($line['run_id'] ?? '')] ?? [];
                    $structuredLogs[] = Ms365LogFormatter::toStructuredLog($line, $child, $userTz);
                }
            }
        }

        $formattedBackupLog = implode("\n", array_map(static function (array $row): string {
            $ts = !empty($row['ts']) ? '[' . $row['ts'] . '] ' : '';
            $level = !empty($row['level']) ? '(' . strtoupper((string) $row['level']) . ') ' : '';

            return $ts . $level . (string) ($row['message'] ?? '');
        }, $structuredLogs));

        $bytesTransferred = (int) ($parentRun['bytes_transferred'] ?? $agg['bytes_transferred']);

        return [
            'backup_log' => $formattedBackupLog,
            'validation_log' => null,
            'has_validation' => false,
            'structured_logs' => $structuredLogs,
            'run_summary' => [
                'bytes_transferred' => $bytesTransferred,
                'bytes_processed' => (int) ($parentRun['bytes_processed'] ?? $agg['bytes_processed']),
                'is_restore' => false,
                'uploaded_formatted' => self::formatBytesHuman($bytesTransferred),
                'downloaded_formatted' => '—',
            ],
        ];
    }

    /**
     * @return array{status: string, message?: string}
     */
    public static function cancelBatch(string $batchRunId, int $clientId, bool $forceCancel = false): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        $parentRun = self::loadParentRun($batchRunId, $clientId);
        if ($parentRun === null) {
            return ['status' => 'fail', 'message' => 'Run not found or access denied'];
        }

        $currentStatus = (string) ($parentRun['status'] ?? '');
        $cancelableStatuses = ['queued', 'starting', 'running'];
        $terminalStatuses = ['success', 'warning', 'failed', 'cancelled', 'partial_success'];

        if ($forceCancel) {
            if (in_array($currentStatus, $terminalStatuses, true)) {
                return ['status' => 'fail', 'message' => 'Run already completed'];
            }
        } elseif (!in_array($currentStatus, $cancelableStatuses, true)) {
            $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
            $aggStatus = Ms365BatchRunRepository::aggregateStatus($children);
            if (!in_array($aggStatus, ['running', 'queued'], true)) {
                return ['status' => 'fail', 'message' => 'Run cannot be cancelled in current status: ' . $currentStatus];
            }
        }

        $cancelledCount = 0;
        foreach (Ms365BatchRunRepository::getBatchChildren($batchRunId) as $child) {
            $childId = (string) ($child['id'] ?? '');
            if ($childId === '' || !BackupRunRepository::isCancellable($childId)) {
                continue;
            }
            if (!BackupRunRepository::requestCancel($childId, 'user')) {
                continue;
            }
            ++$cancelledCount;
            $backupPath = (string) ($child['backup_path'] ?? '');
            if ($backupPath !== '' && is_dir($backupPath)) {
                try {
                    WorkerProcess::terminate($backupPath);
                } catch (\Throwable $e) {
                    // Cancellation is recorded even if the worker cannot be signalled.
                }
            }
            $logPath = $backupPath !== '' ? $backupPath . '/run.log' : null;
            $logger = new ProgressLogger($childId, $logPath);
            $logger->info('Cancellation requested by user');
        }

        $update = ['cancel_requested' => 1];
        if ($forceCancel) {
            $update['status'] = 'cancelled';
            $update['finished_at'] = date('Y-m-d H:i:s');
            $update['error_summary'] = 'Cancellation forced by user';
        } elseif ($cancelledCount > 0) {
            $update['status'] = 'running';
        }

        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = UUID_TO_BIN(?)', [strtolower($batchRunId)])
            ->update($update);

        Ms365BatchRunRepository::syncFromChildren($batchRunId);

        return [
            'status' => 'success',
            'message' => $cancelledCount > 0
                ? 'Cancellation requested for ' . $cancelledCount . ' workload(s).'
                : 'Cancellation requested.',
            'run_id' => $batchRunId,
        ];
    }

    /**
     * Apply aggregated progress fields to a parent run array for Smarty initial render.
     *
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    public static function enrichRunForDisplay(array $run, int $clientId): array
    {
        if (!self::isMs365BatchRun($run)) {
            return $run;
        }
        $batchRunId = (string) ($run['run_id'] ?? '');
        if ($batchRunId === '') {
            return $run;
        }

        try {
            $progress = self::aggregateProgress($batchRunId, $clientId, $run);
            foreach (['progress_pct', 'bytes_processed', 'bytes_transferred', 'bytes_total', 'objects_total', 'objects_transferred', 'speed_bytes_per_sec', 'eta_seconds', 'current_item', 'stage', 'status'] as $key) {
                if (array_key_exists($key, $progress) && $progress[$key] !== null) {
                    $run[$key] = $progress[$key];
                }
            }
        } catch (\Throwable $e) {
            // Best-effort initial render.
        }

        return $run;
    }

    private static function assertBatchOwnership(string $batchRunId, int $clientId): void
    {
        if (self::loadParentRun($batchRunId, $clientId) === null) {
            throw new \RuntimeException('Run not found or access denied.');
        }
    }

    /** @return array<string, mixed>|null */
    private static function loadParentRun(string $batchRunId, int $clientId): ?array
    {
        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $query = Capsule::table('s3_cloudbackup_runs');
        if ($hasJobIdPk) {
            $query->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.job_id');
        } else {
            $query->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id');
        }

        $row = $query
            ->where('s3_cloudbackup_jobs.client_id', $clientId)
            ->whereRaw('s3_cloudbackup_runs.run_id = UUID_TO_BIN(?)', [strtolower($batchRunId)])
            ->first(['s3_cloudbackup_runs.*']);

        if (!$row) {
            return null;
        }

        $arr = (array) $row;
        if (isset($arr['run_id']) && is_string($arr['run_id']) && strlen($arr['run_id']) === 16) {
            $arr['run_id'] = self::binaryToUuid($arr['run_id']);
        }

        return $arr;
    }

    private static function epochMs(mixed $datetime, string $timezone): ?int
    {
        if (empty($datetime)) {
            return null;
        }
        try {
            $dt = new \DateTime((string) $datetime, new \DateTimeZone($timezone));

            return (int) ($dt->getTimestamp() * 1000);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function formatBytesHuman(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        if ($bytes === 0) {
            return '0 B';
        }
        $pow = (int) floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        $value = $bytes / pow(1024, $pow);

        return round($value, $precision) . ' ' . $units[$pow];
    }

    private static function resolveProgressPct(array $parentRun, array $agg): mixed
    {
        $parentPct = $parentRun['progress_pct'] ?? null;
        if ($parentPct !== null && (float) $parentPct > 0) {
            return $parentPct;
        }

        return $agg['progress_pct'] ?? $parentPct;
    }

    /** @param array<string, mixed> $parentRun */
    /** @param array<string, mixed> $agg */
    private static function resolveStageLabel(array $parentRun, array $agg, bool $isRestore): ?string
    {
        $stage = trim((string) ($parentRun['stage'] ?? ''));
        if ($stage === '' && !empty($parentRun['progress_json'])) {
            $decoded = is_string($parentRun['progress_json'])
                ? json_decode($parentRun['progress_json'], true)
                : $parentRun['progress_json'];
            if (is_array($decoded)) {
                $stage = trim((string) ($decoded['stage'] ?? ''));
            }
        }
        if ($stage === '') {
            $stage = trim((string) ($agg['stage'] ?? ''));
        }
        if ($isRestore) {
            $stage = str_replace('Backing up', 'Restoring', $stage);
        }
        $stage = CustomerFacingTextSanitizer::scrubLogMessage($stage);

        return $stage !== '' ? $stage : null;
    }

    private static function binaryToUuid(string $binary): string
    {
        $hex = bin2hex($binary);
        if (strlen($hex) !== 32) {
            return $hex;
        }

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
