<?php
declare(strict_types=1);

namespace Ms365Backup;

final class WorkerSpawner
{
    public static function spawn(string $runId, ?ProgressLogger $logger = null): void
    {
        try {
            JobQueueRepository::enqueue($runId);
        } catch (\Throwable $_) {
            // Queue table may not exist until module upgrade runs.
        }

        if (Ms365EngineConfig::usesKopiaWorker() && !Ms365EngineConfig::usesPhpWorker()) {
            $logger?->info('Run queued for MS365 Kopia worker fleet', ['run_id' => $runId]);
            return;
        }

        if (!Ms365EngineConfig::usesPhpWorker()) {
            return;
        }

        try {
            JobQueueRepository::markRunning($runId);
        } catch (\Throwable $_) {
        }

        if (self::isExecDisabled()) {
            if (Ms365EngineConfig::usesKopiaWorker()) {
                JobQueueRepository::markFailed($runId, 'PHP exec disabled; Kopia worker may process when queued');
                return;
            }
            throw new \RuntimeException(
                'Cannot start backup worker: PHP exec() is disabled. Run manually: '
                . 'php modules/addons/ms365backup/bin/ms365_backup.php run --run-id=' . $runId
            );
        }

        StoragePermissions::ensureWritableBase();

        $php = self::resolvePhpBinary();
        $script = realpath(dirname(__DIR__, 2) . '/bin/ms365_backup.php');
        if ($script === false || !is_executable($script)) {
            throw new \RuntimeException('CLI script not found or not executable: ms365_backup.php');
        }

        $logFile = StorageLayout::BASE_PATH . '/_logs/worker.log';
        $cmd = sprintf(
            'nohup %s %s run --run-id=%s >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($script),
            escapeshellarg($runId),
            escapeshellarg($logFile)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $logger?->info('PHP background worker requested', [
            'php' => $php,
            'script' => $script,
            'exec_exit_code' => $exitCode,
            'worker_log' => $logFile,
            'engine_mode' => Ms365EngineConfig::engineMode(),
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'Failed to spawn backup worker (exec exit ' . $exitCode . '). See ' . $logFile
            );
        }

        if (Ms365EngineConfig::engineMode() !== Ms365EngineConfig::MODE_KOPIA_SHADOW) {
            self::spawnQueueProcessor($logger);
        }
    }

    public static function spawnQueueProcessor(?ProgressLogger $logger = null): void
    {
        if (self::isExecDisabled() || JobQueueRepository::countQueued() < 2) {
            return;
        }

        $php = self::resolvePhpBinary();
        $script = realpath(dirname(__DIR__, 2) . '/bin/ms365_queue_worker.php');
        if ($script === false) {
            return;
        }

        $logFile = StorageLayout::BASE_PATH . '/_logs/queue_worker.log';
        $cmd = sprintf(
            'nohup %s %s 25 >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($script),
            escapeshellarg($logFile),
        );
        exec($cmd);
        $logger?->info('Queue processor requested', ['queue_log' => $logFile]);
    }

    public static function isExecDisabled(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return in_array('exec', $disabled, true);
    }

    public static function resolvePhpBinary(): string
    {
        $candidates = [
            PHP_BINARY ?: '',
            '/usr/bin/php',
            '/usr/bin/php8.2',
            '/usr/bin/php8.3',
            'php',
        ];
        foreach ($candidates as $bin) {
            if ($bin === '') {
                continue;
            }
            if ($bin === 'php' || is_executable($bin)) {
                return $bin;
            }
        }

        return 'php';
    }
}
