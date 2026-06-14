<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;
use Ms365Backup\StoragePermissions;
use Ms365Backup\StorageLayout;

final class SeederWorkerSpawner
{
    public static function spawn(string $runId): void
    {
        if (\Ms365Backup\WorkerSpawner::isExecDisabled()) {
            throw new \RuntimeException(
                'Cannot start seeder worker: PHP exec() is disabled. Run manually: '
                . 'php modules/addons/ms365backup/bin/ms365_seeder_worker.php --run-id=' . $runId
            );
        }

        StoragePermissions::ensureWritableBase();

        $php = self::resolvePhpBinary();
        // Seeder/ is one level deeper than lib/Ms365Backup/ (see WorkerSpawner).
        $script = realpath(dirname(__DIR__, 3) . '/bin/ms365_seeder_worker.php');
        if ($script === false || !is_file($script)) {
            throw new \RuntimeException('CLI script not found: ms365_seeder_worker.php');
        }

        $logFile = StorageLayout::BASE_PATH . '/_logs/seeder_worker.log';
        $cmd = sprintf(
            'nohup %s %s --run-id=%s >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($script),
            escapeshellarg($runId),
            escapeshellarg($logFile),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Failed to spawn seeder worker (exec exit ' . $exitCode . ')');
        }
    }

    public static function resolvePhpBinary(): string
    {
        return \Ms365Backup\WorkerSpawner::resolvePhpBinary();
    }
}
