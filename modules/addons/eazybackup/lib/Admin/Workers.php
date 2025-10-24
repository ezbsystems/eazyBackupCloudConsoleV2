<?php

declare(strict_types=1);

namespace Eazybackup\Admin;

/**
 * Admin Workers status reader (read-only systemctl show with strict allow-list + tiny cache).
 */
class Workers
{
    /**
     * Default allow-list (key => [label, unit]).
     * @return array<string,array{label:string,unit:string}>
     */
    public static function defaultAllowList(): array
    {
        return [
            'cometbackup' => [
                'label' => 'Comet WebSocket – Primary',
                'unit'  => 'eazybackup-comet-ws@cometbackup.service',
            ],
            'obc' => [
                'label' => 'Comet WebSocket – OBC',
                'unit'  => 'eazybackup-comet-ws@obc.service',
            ],
        ];
    }

    /**
     * Optional config allow-list override.
     * Supports PHP array at /etc/eazybackup/whmcs-workers.php or JSON at /etc/eazybackup/whmcs-workers.json
     * Shape: [ key => [ 'label' => string, 'unit' => string ] ]
     * @return array<string,array{label:string,unit:string}>
     */
    public static function loadConfigAllowList(): array
    {
        $out = [];
        try {
            $phpCfg = '/etc/eazybackup/whmcs-workers.php';
            $jsonCfg = '/etc/eazybackup/whmcs-workers.json';
            if (is_file($phpCfg) && is_readable($phpCfg)) {
                $data = include $phpCfg;
                if (is_array($data)) {
                    $out = self::normalizeAllowList($data);
                }
            } elseif (is_file($jsonCfg) && is_readable($jsonCfg)) {
                $raw = (string) @file_get_contents($jsonCfg);
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    $out = self::normalizeAllowList($data);
                }
            }
        } catch (\Throwable $__) {
            $out = [];
        }
        return $out;
    }

    /**
     * Normalize allow-list structure.
     * @param array $in
     * @return array<string,array{label:string,unit:string}>
     */
    private static function normalizeAllowList(array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            if (is_array($v) && isset($v['label'], $v['unit'])) {
                $label = (string) $v['label'];
                $unit  = (string) $v['unit'];
            } elseif (is_string($v)) {
                $label = (string) $k;
                $unit  = $v;
            } else {
                continue;
            }
            if ($label !== '' && $unit !== '') {
                $out[(string)$k] = ['label' => $label, 'unit' => $unit];
            }
        }
        return $out;
    }

    /**
     * Compose final allow-list (defaults merged with optional config; config overrides same keys).
     * @return array<string,array{label:string,unit:string}>
     */
    public static function getAllowList(): array
    {
        $base = self::defaultAllowList();
        $cfg  = self::loadConfigAllowList();
        foreach ($cfg as $k => $row) {
            $base[$k] = $row;
        }
        return $base;
    }

    /**
     * Cache file path (prefer tmpfs /run, fallback to PHP temp dir).
     */
    private static function cacheFile(): string
    {
        $dir = '/run/eazybackup';
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        }
        return $dir . DIRECTORY_SEPARATOR . 'workers.json';
    }

    /**
     * Read cache if fresh (<= $ttl seconds).
     * @param int $ttl
     * @return array|null
     */
    private static function readCache(int $ttl = 10): ?array
    {
        try {
            $file = self::cacheFile();
            if (!is_file($file)) return null;
            $mtime = (int) @filemtime($file);
            if ($mtime > 0 && (time() - $mtime) <= $ttl) {
                $raw = (string) @file_get_contents($file);
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    $data['cached'] = true;
                    return $data;
                }
            }
        } catch (\Throwable $__) {
            // ignore
        }
        return null;
    }

    /**
     * Write cache file (best effort).
     * @param array $data
     * @return void
     */
    private static function writeCache(array $data): void
    {
        try {
            $file = self::cacheFile();
            // Do not persist the 'cached' flag
            $out = $data;
            unset($out['cached']);
            @file_put_contents($file, json_encode($out));
        } catch (\Throwable $__) {
            // ignore
        }
    }

    /**
     * Public: List workers with status and metadata.
     * @return array{checkedAtIso:string,serverTimeIso:string,cached:bool,workers:array<int,array<string,mixed>>,error?:string}
     */
    public static function list(): array
    {
        // Try cache first
        $cached = self::readCache(10);
        if (is_array($cached)) {
            return $cached;
        }

        $allow = self::getAllowList();
        $workers = [];
        $errors = 0;
        foreach ($allow as $key => $row) {
            $label = (string)($row['label'] ?? (string)$key);
            $unit  = (string)($row['unit'] ?? '');
            if ($unit === '') {
                $workers[] = self::unknownRow($key, $label, $unit, 'Unit not specified');
                $errors++;
                continue;
            }
            $w = self::readUnit($key, $label, $unit);
            if (isset($w['error']) && $w['error'] !== '') {
                $errors++;
            }
            $workers[] = $w;
        }

        $nowIso = gmdate('Y-m-d H:i:s');
        $resp = [
            'checkedAtIso' => $nowIso,
            'serverTimeIso' => $nowIso,
            'cached' => false,
            'workers' => $workers,
        ];
        if ($errors === count($workers)) {
            $resp['error'] = 'Unable to read systemd status';
        }
        self::writeCache($resp);
        return $resp;
    }

    /**
     * Build an unknown/gray row.
     * @param string $key
     * @param string $label
     * @param string $unit
     * @param string $msg
     * @return array
     */
    private static function unknownRow(string $key, string $label, string $unit, string $msg): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'unit' => $unit,
            'activeState' => 'unknown',
            'subState' => 'unknown',
            'loadState' => 'unknown',
            'unitFileState' => 'unknown',
            'mainPid' => 0,
            'sinceEpochMs' => 0,
            'since' => '',
            'uptimeSeconds' => 0,
            'lastExitCode' => null,
            'lastExitStatus' => null,
            'restartCount' => null,
            'fragmentPath' => null,
            'color' => 'gray',
            'error' => $msg,
        ];
    }

    /**
     * Read a unit via systemctl show and map to worker row.
     * @param string $key
     * @param string $label
     * @param string $unit
     * @return array<string,mixed>
     */
    private static function readUnit(string $key, string $label, string $unit): array
    {
        $res = self::systemctlShow($unit);
        if (!$res['ok']) {
            return self::unknownRow($key, $label, $unit, trim((string)$res['stderr']) !== '' ? trim((string)$res['stderr']) : ('Exit code ' . (string)$res['code']));
        }
        $out = [];
        $rawLines = preg_split('/\r?\n/', (string)$res['stdout']);
        foreach ($rawLines as $line) {
            if (!is_string($line) || $line === '') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = substr($line, 0, $pos);
            $v = substr($line, $pos + 1);
            $out[$k] = $v;
        }

        $active = (string)($out['ActiveState'] ?? 'unknown');
        $sub    = (string)($out['SubState'] ?? 'unknown');
        $load   = (string)($out['LoadState'] ?? 'unknown');
        $unitFileState = (string)($out['UnitFileState'] ?? 'unknown');
        $pid = (int)($out['MainPID'] ?? 0);
        $code = isset($out['ExecMainCode']) ? (int)$out['ExecMainCode'] : null;
        $status = isset($out['ExecMainStatus']) ? (int)$out['ExecMainStatus'] : null;
        $restarts = isset($out['NRestarts']) ? (int)$out['NRestarts'] : null;
        $fragment = isset($out['FragmentPath']) ? (string)$out['FragmentPath'] : null;

        $sinceUs = 0;
        if (isset($out['ActiveEnterTimestampUSec']) && ctype_digit((string)$out['ActiveEnterTimestampUSec'])) {
            $sinceUs = (int)$out['ActiveEnterTimestampUSec'];
        }
        $sinceEpochMs = $sinceUs > 0 ? (int) floor($sinceUs / 1000) : 0; // convert usec → msec
        $sinceIso = $sinceEpochMs > 0 ? gmdate('Y-m-d H:i:s', (int) floor($sinceEpochMs / 1000)) : '';
        $uptime = 0;
        if ($sinceEpochMs > 0) {
            $uptime = max(0, (int) floor((microtime(true) * 1000 - $sinceEpochMs) / 1000));
        }

        // Color mapping
        $color = 'gray';
        $error = '';
        if ($load !== 'loaded') {
            $color = 'red';
            $error = 'Unit not loaded';
        } elseif ($active === 'active' && $sub === 'running') {
            $color = 'green';
        } elseif ($active === 'active' && $sub !== 'running') {
            $color = 'yellow';
        } elseif (in_array($active, ['failed','inactive'], true)) {
            $color = 'red';
        }

        return [
            'key' => $key,
            'label' => $label,
            'unit' => $unit,
            'activeState' => $active,
            'subState' => $sub,
            'loadState' => $load,
            'unitFileState' => $unitFileState,
            'mainPid' => $pid,
            'sinceEpochMs' => $sinceEpochMs,
            'since' => $sinceIso,
            'uptimeSeconds' => $uptime,
            'lastExitCode' => $code,
            'lastExitStatus' => $status,
            'restartCount' => $restarts,
            'fragmentPath' => $fragment,
            'color' => $color,
            'error' => $error,
        ];
    }

    /**
     * Run systemctl show and capture stdout/stderr/exit code.
     * @param string $unit
     * @return array{ok:bool,stdout:string,stderr:string,code:int}
     */
    private static function systemctlShow(string $unit): array
    {
        $bin = is_file('/bin/systemctl') ? '/bin/systemctl' : (is_file('/usr/bin/systemctl') ? '/usr/bin/systemctl' : 'systemctl');
        $props = 'ActiveState,SubState,MainPID,ExecMainCode,ExecMainStatus,ActiveEnterTimestampUSec,LoadState,UnitFileState,NRestarts,FragmentPath';
        return self::runCmd([$bin, 'show', '--property=' . $props, '--', $unit], 2);
    }

    /**
     * proc_open runner to capture stderr and enforce a short timeout.
     * @param array $cmd
     * @param int $timeoutSec
     * @return array{ok:bool,stdout:string,stderr:string,code:int}
     */
    private static function runCmd(array $cmd, int $timeoutSec = 2): array
    {
        $spec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = ['LANG' => 'C', 'LC_ALL' => 'C'];
        $proc = @proc_open($cmd, $spec, $pipes, null, $env);
        if (!is_resource($proc)) {
            return ['ok' => false, 'stdout' => '', 'stderr' => 'proc_open failed', 'code' => -1];
        }
        foreach ($pipes as $i => $p) { if (is_resource($p)) { stream_set_blocking($p, true); } }
        $start = microtime(true);
        $stdout = '';
        $stderr = '';
        while (true) {
            if (isset($pipes[1])) { $stdout .= @fread($pipes[1], 8192) ?: ''; }
            if (isset($pipes[2])) { $stderr .= @fread($pipes[2], 8192) ?: ''; }
            $eof1 = !isset($pipes[1]) || feof($pipes[1]);
            $eof2 = !isset($pipes[2]) || feof($pipes[2]);
            if ($eof1 && $eof2) break;
            if ((microtime(true) - $start) > $timeoutSec) {
                @proc_terminate($proc);
                $stderr .= "\nTIMEOUT";
                break;
            }
            usleep(10000);
        }
        foreach ($pipes as $p) { if (is_resource($p)) { @fclose($p); } }
        $code = (int) @proc_close($proc);
        return ['ok' => ($code === 0), 'stdout' => $stdout, 'stderr' => $stderr, 'code' => $code];
    }
}


