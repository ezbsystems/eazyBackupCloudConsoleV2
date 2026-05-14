<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

/**
 * Run a child process, streaming merged stdout/stderr to a log file with
 * line-level redaction of any registered secrets.
 */
class ProcRunner
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

    /**
     * Execute the command (string or array argv). Streams output to $logPath.
     * Returns exit code (-1 if proc_open failed).
     */
    public function run($command, string $logPath, ?string $cwd = null, ?array $env = null, int $timeoutSecs = 7200): int
    {
        $log = fopen($logPath, 'ab');
        if (!$log) {
            return -1;
        }

        $cmdLine = is_array($command) ? self::shellArgs($command) : (string) $command;

        $header = sprintf("\n[%s] $ %s\n", date('Y-m-d H:i:s'), $this->redact($cmdLine));
        fwrite($log, $header);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
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
                fwrite($log, "\n[runner] timeout after {$timeoutSecs}s, killed\n");
                $exitCode = 124;
                break;
            }
        }

        // Drain remaining
        foreach ([$pipes[1], $pipes[2]] as $r) {
            while (($chunk = @fread($r, 8192)) !== false && $chunk !== '') {
                fwrite($log, $this->redact($chunk));
            }
            @fclose($r);
        }
        proc_close($proc);

        fwrite($log, sprintf("[%s] exit=%d\n", date('Y-m-d H:i:s'), $exitCode));
        fclose($log);
        return $exitCode;
    }

    public static function shellArgs(array $argv): string
    {
        return implode(' ', array_map(static function ($a) {
            return escapeshellarg((string) $a);
        }, $argv));
    }

    /** Capture the trimmed stdout of a quick command (no logging). */
    public static function capture(array $argv, ?string $cwd = null): array
    {
        $cmd = self::shellArgs($argv) . ' 2>&1';
        $desc = [1 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $desc, $pipes, $cwd);
        if (!is_resource($proc)) {
            return [-1, ''];
        }
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($proc);
        return [$exit, trim((string) $out)];
    }
}
