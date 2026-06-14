<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

final class ProcRunner
{
    /** @var string[] */
    private array $secrets = [];

    public function addSecret(?string $value): void
    {
        if ($value !== null && $value !== '' && strlen($value) >= 4) {
            $this->secrets[] = $value;
        }
    }

    public function redact(string $line): string
    {
        foreach ($this->secrets as $s) {
            $line = str_replace($s, '***REDACTED***', $line);
        }

        return $line;
    }

    /** @param string|string[] $command */
    public function run($command, string $logPath, ?string $cwd = null, ?array $env = null, int $timeoutSecs = 3600): int
    {
        $log = fopen($logPath, 'ab');
        if (!$log) {
            return -1;
        }

        $cmdLine = is_array($command) ? self::shellArgs($command) : (string) $command;
        fwrite($log, sprintf("\n[%s] $ %s\n", date('Y-m-d H:i:s'), $this->redact($cmdLine)));

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmdLine, $descriptors, $pipes, $cwd, $env);
        if (!is_resource($proc)) {
            fwrite($log, "[runner] proc_open failed\n");
            fclose($log);

            return -1;
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $start = time();
        $exitCode = -1;
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $w = $e = null;
            $changed = @stream_select($read, $w, $e, 1);
            if ($changed > 0) {
                foreach ($read as $r) {
                    while (($chunk = fread($r, 8192)) !== false && $chunk !== '') {
                        fwrite($log, $this->redact($chunk));
                    }
                }
            }
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }
            if ($timeoutSecs > 0 && (time() - $start) > $timeoutSecs) {
                @proc_terminate($proc, 9);
                fwrite($log, "\n[runner] timeout after {$timeoutSecs}s\n");
                $exitCode = 124;
                break;
            }
        }
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                fclose($p);
            }
        }
        proc_close($proc);
        fclose($log);

        return $exitCode;
    }

    /** @param string[] $argv */
    private static function shellArgs(array $argv): string
    {
        return implode(' ', array_map(static fn ($a) => escapeshellarg((string) $a), $argv));
    }
}
