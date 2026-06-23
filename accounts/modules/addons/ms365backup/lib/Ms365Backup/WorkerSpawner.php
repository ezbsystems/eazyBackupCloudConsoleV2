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

        $logger?->info('Run queued for MS365 Kopia worker fleet', ['run_id' => $runId]);
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
            '/usr/bin/php8.2',
            '/usr/bin/php8.3',
            '/usr/bin/php',
            'php',
        ];
        foreach ($candidates as $bin) {
            if ($bin === '' || self::isFpmBinary($bin)) {
                continue;
            }
            if ($bin === 'php' || is_executable($bin)) {
                return $bin;
            }
        }

        return 'php';
    }

    private static function isFpmBinary(string $bin): bool
    {
        return str_contains(strtolower($bin), 'fpm');
    }
}
