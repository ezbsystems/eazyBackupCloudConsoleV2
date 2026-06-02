<?php
declare(strict_types=1);

namespace Ms365Backup;

final class WorkerProcess
{
    public static function writePid(string $runDir): void
    {
        if ($runDir === '') {
            return;
        }
        @file_put_contents(rtrim($runDir, '/') . '/worker.pid', (string) getmypid());
    }

    public static function terminate(string $runDir): bool
    {
        $file = rtrim($runDir, '/') . '/worker.pid';
        if (!is_file($file)) {
            return false;
        }
        $pid = (int) trim((string) file_get_contents($file));
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill')) {
            @posix_kill($pid, \SIGTERM);
            return true;
        }
        return false;
    }
}
