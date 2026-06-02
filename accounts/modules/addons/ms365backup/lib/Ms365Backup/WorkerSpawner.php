<?php
declare(strict_types=1);

namespace Ms365Backup;

final class WorkerSpawner
{
    public static function spawn(string $runId, ?ProgressLogger $logger = null): void
    {
        if (self::isExecDisabled()) {
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

        $logger?->info('Background worker requested', [
            'php' => $php,
            'script' => $script,
            'exec_exit_code' => $exitCode,
            'worker_log' => $logFile,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'Failed to spawn backup worker (exec exit ' . $exitCode . '). See ' . $logFile
            );
        }
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
