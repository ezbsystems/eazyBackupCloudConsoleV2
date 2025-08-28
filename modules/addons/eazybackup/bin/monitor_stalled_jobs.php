<?php
declare(strict_types=1);


/**
 * EazyBackup — Monitor and clean stalled Comet jobs
 * -------------------------------------------------
 * Strategy:
 *  - For each Comet profile, scan eb_jobs_live for jobs with no upload progress >= STALE_SECONDS
 *  - Confirm via AdminGetJobProperties (fetch UploadSize, EndTime, CancellationID)
 *  - If still running and idle: try AdminJobCancel; else AdminJobAbandon
 *  - On terminal: move to eb_jobs_recent_24h and delete from eb_jobs_live
 *
 * Env toggles (with sensible defaults):
 *   EB_MON_STALE_SECS=3600           // 1h with no bytes uploaded => stalled
 *   EB_MON_RECHECK_SECS=300          // do not recheck the same row too often
 *   EB_MON_MAX_ATTEMPTS=3            // max cancel/abandon attempts per job
 *   EB_MON_BATCH_LIMIT=200           // per profile per run
 *
 * Comet Admin API creds (per profile):
 *   COMET_<profile>_API_BASE="https://csw.example.com/api/v1"
 *   COMET_<profile>_API_USER="whmcsapi"
 *   COMET_<profile>_API_AUTHTYPE="Password"
 *   COMET_<profile>_API_PASSWORD="***"
 *   COMET_<profile>_API_TOTP=""
 *
 * If API_BASE is not set, we derive it from COMET_<profile>_URL by swapping wss://.../api/v1/events/stream -> https://host/api/v1
 */

require __DIR__ . '/bootstrap.php';

$STALE_SECS    = (int)(getenv('EB_MON_STALE_SECS')    ?: 3600);
$RECHECK_SECS  = (int)(getenv('EB_MON_RECHECK_SECS')  ?: 300);
$MAX_ATTEMPTS  = (int)(getenv('EB_MON_MAX_ATTEMPTS')  ?: 3);
$BATCH_LIMIT   = (int)(getenv('EB_MON_BATCH_LIMIT')   ?: 200);

$pdo = db();

// --- CLI flags (all optional) ---
$cli = getopt('', [
    'dry-run',           // print-only; don't cancel and don't mutate DB
    'profile::',         // restrict to a single COMET profile name
    'stale-secs::',      // override EB_MON_STALE_SECS
    'recheck-secs::',    // override EB_MON_RECHECK_SECS
    'max-attempts::',    // override EB_MON_MAX_ATTEMPTS
    'limit::',           // override EB_MON_BATCH_LIMIT
    'verbose::',         // verbose: 0/1
]);

$DRY_RUN = isset($cli['dry-run']) || (getenv('EB_MON_DRY_RUN') === '1');

if (isset($cli['stale-secs']))   $STALE_SECS   = (int)$cli['stale-secs'];
if (isset($cli['recheck-secs'])) $RECHECK_SECS = (int)$cli['recheck-secs'];
if (isset($cli['max-attempts'])) $MAX_ATTEMPTS = (int)$cli['max-attempts'];
if (isset($cli['limit']))        $BATCH_LIMIT  = (int)$cli['limit'];
$VERBOSE = isset($cli['verbose']) ? (int)$cli['verbose'] : (int)(getenv('EB_MON_VERBOSE') ?: 0);

function isDryRun(): bool {
    global $DRY_RUN;
    return (bool)$DRY_RUN;
}
function vout(string $msg): void {
    global $VERBOSE;
    if ($VERBOSE) {
        fwrite(STDERR, rtrim($msg).PHP_EOL);
    }
}

if ($DRY_RUN) {
    fwrite(STDERR, "[monitor] DRY-RUN: no API calls, no DB writes.\n");
    fwrite(STDERR, "[monitor] Use --verbose=1 for more detail. Thresholds: stale>={$STALE_SECS}s, recheck>={$RECHECK_SECS}s, attempts<{$MAX_ATTEMPTS}, limit={$BATCH_LIMIT}\n");
}

/** ---------- helpers ---------- */

function profilesFromEnv(): array {
    $names = [];
    foreach ($_ENV as $k => $v) {
        if (preg_match('/^COMET_([A-Za-z0-9_]+)_(URL|API_BASE)$/', $k, $m)) {
            $names[$m[1]] = true;
        }
    }
    return array_keys($names);
}

/** Pick profiles to scan based on CLI, COMET_PROFILES, or env keys */
function resolveProfiles(array $cli): array {
    // 1) Explicit CLI filter
    if (isset($cli['profile']) && is_string($cli['profile']) && $cli['profile'] !== '') {
        return [trim($cli['profile'])];
    }
    // 2) COMET_PROFILES=eazybackup,obc,primary
    $csv = trim(cfg('COMET_PROFILES', ''));
    if ($csv !== '') {
        $arr = array_filter(array_map('trim', explode(',', $csv)));
        if (!empty($arr)) return $arr;
    }
    // 3) Fallback: discover any COMET_<name>_(URL|API_BASE) in env
    $auto = profilesFromEnv();
    return $auto;
}

/** Case-insensitive lookup for COMET_<profile>_<SUFFIX> in env */
function cfgProfile(string $profile, string $suffix, string $default = ''): string {
    $candidates = [
        "COMET_{$profile}_{$suffix}",
        'COMET_' . strtoupper($profile) . '_' . strtoupper($suffix),
        'COMET_' . strtolower($profile) . '_' . strtoupper($suffix),
        'COMET_' . strtoupper($profile) . '_' . strtolower($suffix),
    ];
    foreach ($candidates as $k) {
        $v = getenv($k);
        if ($v !== false && $v !== '') return (string)$v;
    }
    // Fallback: scan $_ENV case-insensitively
    $pattern = '/^COMET_'.preg_quote($profile, '/').'_'.$suffix.'$/i';
    foreach ($_ENV as $k => $v) {
        if (preg_match($pattern, (string)$k)) {
            return (string)$v;
        }
    }
    return $default;
}

function deriveApiBaseFromWsUrl(string $ws): ?string {
    if ($ws === '') return null;
    $p = parse_url($ws);
    if (!$p || empty($p['host'])) return null;
    // Scheme: wss -> https, ws -> http
    $scheme = ($p['scheme'] ?? 'https');
    if (str_starts_with($scheme, 'ws')) {
        $scheme = ($scheme === 'wss') ? 'https' : 'http';
    }
    $host   = $p['host'];
    $port   = isset($p['port']) ? ':' . $p['port'] : '';
    // Use /api/v1 as base if path contains /api/...
    $basePath = '/api/v1';
    return "{$scheme}://{$host}{$port}{$basePath}";
}

function cometApiBase(string $profile): ?string {
    $explicit = cfgProfile($profile, 'API_BASE', '');
    if ($explicit !== '') return rtrim($explicit, '/');
    $wsUrl = cfgProfile($profile, 'URL', '');
    return deriveApiBaseFromWsUrl($wsUrl);
}

function cometAdminAuth(string $profile): array {
    // Prefer explicit admin creds; fallback to websocket creds if needed.
    $user = cfgProfile($profile, 'API_USER',     cfgProfile($profile, 'USERNAME',  ''));
    $aut  = cfgProfile($profile, 'API_AUTHTYPE', cfgProfile($profile, 'AUTHTYPE',  'Password'));
    $pwd  = cfgProfile($profile, 'API_PASSWORD', cfgProfile($profile, 'PASSWORD',  ''));
    $key  = cfgProfile($profile, 'API_SESSIONKEY', cfgProfile($profile, 'SESSIONKEY', ''));
    $totp = cfgProfile($profile, 'API_TOTP',     cfgProfile($profile, 'TOTP',      ''));

    return [
        'Username'   => $user,
        'AuthType'   => $aut,
        'Password'   => $pwd,
        'SessionKey' => $key,
        'TOTP'       => $totp,
    ];
}

function httpPostForm(string $url, array $params): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("curl error: {$err}");
    }
    curl_close($ch);
    if ($http < 200 || $http >= 300) {
        throw new RuntimeException("HTTP {$http}: {$raw}");
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : ['raw' => $raw];
}

function api(string $base, string $path, array $auth, array $payload): array {
    return httpPostForm($base . $path, array_merge($auth, $payload));
}

/** Extract running progress bytes from GetJobProperties (Progress.BytesDone) */
function progressBytesFromProps(array $props): int {
    // Known structure: { Progress: { BytesDone: <int>, ... } }
    if (isset($props['Progress']) && is_array($props['Progress'])) {
        $p = $props['Progress'];
        if (isset($p['BytesDone']) && is_numeric($p['BytesDone'])) {
            return (int)$p['BytesDone'];
        }
        // Some builds might use lowercase or nested fields; be defensive
        foreach (['bytesdone','bytes','uploaded','UploadSize'] as $k) {
            if (isset($p[$k]) and is_numeric($p[$k])) return (int)$p[$k];
        }
    }
    // Fallback to top-level UploadSize if present
    if (isset($props['UploadSize']) && is_numeric($props['UploadSize'])) {
        return (int)$props['UploadSize'];
    }
    return 0;
}

/** Try to extract the largest byte count from a job log payload */
function parseLargestBytesFromLog(array $log): int {
    $max = 0;
    // Common fields sometimes present
    foreach (['UploadSize','BytesUploaded','Bytes'] as $k) {
        if (isset($log[$k]) && is_numeric($log[$k])) {
            $max = max($max, (int)$log[$k]);
        }
    }
    // Scan arrays of entries/lines
    $lines = [];
    if (isset($log['Entries']) && is_array($log['Entries'])) $lines = $log['Entries'];
    if (isset($log['Lines']) && is_array($log['Lines'])) $lines = array_merge($lines, $log['Lines']);
    foreach ($lines as $ln) {
        $txt = '';
        if (is_array($ln)) {
            $txt = (string)($ln['Message'] ?? $ln['Text'] ?? $ln['msg'] ?? '');
            foreach (['UploadSize','Bytes','Transferred','DeltaBytes'] as $k) {
                if (isset($ln[$k]) && is_numeric($ln[$k])) { $max = max($max, (int)$ln[$k]); }
            }
        } else if (is_string($ln)) {
            $txt = $ln;
        }
        if ($txt !== '') {
            if (preg_match_all('/(bytes|uploaded|upload)[^0-9]{0,16}([0-9]{1,20})/i', $txt, $m)) {
                foreach ($m[2] as $n) { $max = max($max, (int)$n); }
            }
        }
    }
    return $max;
}

/** Fetch job log and extract best-effort uploaded bytes */
function getJobLogBytes(string $base, array $auth, string $user, string $jobId): int {
    try {
        $log = api($base, '/admin/get-job-log', $auth, [ 'TargetUser' => $user, 'JobID' => $jobId ]);
        return parseLargestBytesFromLog($log);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Map internal labels to DB enum: success|error|warning|missed|skipped */
function normalizeStatusForDb(string $label): string {
    $s = strtolower($label);

    // direct matches
    if (in_array($s, ['success','error','warning','missed','skipped'], true)) return $s;

    // edge → enum
    if ($s === 'finished' || $s === 'complete' || $s === 'completed') return 'success';
    if ($s === 'cancelled' || $s === 'canceled') return 'error';
    if ($s === 'abandoned') return 'error';
    if ($s === 'already_running') return 'error';
    if ($s === 'failed_quota' || $s === 'timeout') return 'error';
    if ($s === 'running' || $s === 'unknown') return 'error';

    return 'error';
}
/** Determine if a running job has a fresh heartbeat via Progress timestamps */
function hasFreshHeartbeat(array $props, int $thresholdSeconds): bool {
    $p = $props['Progress'] ?? [];
    if (!is_array($p)) return false;
    $sent = isset($p['SentTime']) ? (int)$p['SentTime'] : 0;
    $recv = isset($p['RecievedTime']) ? (int)$p['RecievedTime'] : 0; // spelling per API
    $ts = max($sent, $recv);
    if ($ts <= 0) return false;
    return (time() - $ts) <= max(5, $thresholdSeconds);
}

/** Map Comet numeric Status to our label (extend as needed) */
function mapStatusLabel(?int $code): string {
    if ($code === null) return 'unknown';

    return match ($code) {
        5000 => 'success',
        6001 => 'running',      // ACTIVE
        6002 => 'running',      // REVIVED (normalize to active)
        7000 => 'timeout',      // will normalize to error for DB
        7001 => 'warning',
        7002 => 'error',
        7003 => 'failed_quota', // will normalize to error for DB
        7004 => 'missed',
        7005 => 'cancelled',    // will normalize to error for DB
        7006 => 'already_running', // will normalize to error for DB
        7007 => 'abandoned',    // will normalize to error for DB
        default => ($code >= 6000 ? 'running' : 'error'),
    };
}

/** Finalize a job into recent_24h and remove from live */
function finalizeJob(PDO $pdo, array $row): void {
    // $row keys: server_id, job_id, username, device, job_type, status, bytes, duration_sec, ended_at
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare("DELETE FROM eb_jobs_live WHERE server_id=? AND job_id=?");
        $del->execute([$row['server_id'], $row['job_id']]);

        $ins = $pdo->prepare("
            INSERT INTO eb_jobs_recent_24h
              (server_id, job_id, username, device, job_type, status, bytes, duration_sec, ended_at, created_at)
            VALUES
              (:server_id, :job_id, :username, :device, :job_type, :status, :bytes, :duration_sec, :ended_at, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
              username=VALUES(username),
              device=VALUES(device),
              job_type=VALUES(job_type),
              status=VALUES(status),
              bytes=VALUES(bytes),
              duration_sec=VALUES(duration_sec),
              ended_at=VALUES(ended_at)
        ");
        $ins->execute([
            ':server_id'    => $row['server_id'],
            ':job_id'       => $row['job_id'],
            ':username'     => $row['username'] ?? '',
            ':device'       => $row['device']   ?? '',
            ':job_type'     => $row['job_type'] ?? '',
            ':status'       => $row['status']   ?? 'unknown',
            ':bytes'        => (int)($row['bytes'] ?? 0),
            ':duration_sec' => (int)($row['duration_sec'] ?? 0),
            ':ended_at'     => (int)($row['ended_at'] ?? time()),
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Update progress markers in eb_jobs_live */
function touchProgress(PDO $pdo, string $serverId, string $jobId, int $bytes): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET last_bytes = :b,
               last_bytes_ts = UNIX_TIMESTAMP(),
               last_checked_ts = UNIX_TIMESTAMP()
         WHERE server_id=:s AND job_id=:j
    ");
    $stmt->execute([':b'=>$bytes, ':s'=>$serverId, ':j'=>$jobId]);
}

/** Bump check + attempts */
function bumpAttempt(PDO $pdo, string $serverId, string $jobId): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET cancel_attempts = cancel_attempts + 1,
               last_checked_ts = UNIX_TIMESTAMP()
         WHERE server_id=:s AND job_id=:j
    ");
    $stmt->execute([':s'=>$serverId, ':j'=>$jobId]);
}

/** Update only last_checked_ts without incrementing attempts */
function markChecked(PDO $pdo, string $serverId, string $jobId): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET last_checked_ts = UNIX_TIMESTAMP()
         WHERE server_id=:s AND job_id=:j
    ");
    $stmt->execute([':s'=>$serverId, ':j'=>$jobId]);
}

/** Reset strike/attempt counter after observing progress/heartbeat */
function resetAttempts(PDO $pdo, string $serverId, string $jobId): void {
    $stmt = $pdo->prepare("\n        UPDATE eb_jobs_live\n           SET cancel_attempts = 0,\n               last_checked_ts = UNIX_TIMESTAMP()\n         WHERE server_id=:s AND job_id=:j\n    ");
    $stmt->execute([':s'=>$serverId, ':j'=>$jobId]);
}

/** ---------- core logic ---------- */

function processProfile(
    PDO $pdo,
    string $profile,
    int $STALE_SECS,
    int $RECHECK_SECS,
    int $MAX_ATTEMPTS,
    int $BATCH_LIMIT,
    bool $DRY_RUN = false
): void {
    $profileName = $profile;
    $base = cometApiBase($profile);
    if (!$base) {
        logLine("[{$profileName}] SKIP: API base not configured/derivable");
        return;
    }
    $auth = cometAdminAuth($profile);
    if (($auth['Username'] ?? '') === '') {
        logLine("[{$profileName}] SKIP: Admin API Username missing");
        return;
    }

    // Ensure live table has our helper columns (safe no-op if already there)
    if (!$DRY_RUN) {
        ensureLiveExtensions($pdo);
    }

    // Bootstrap pass: initialize progress markers for rows missing timestamps
    $bootSql = "
      SELECT server_id, job_id, username
        FROM eb_jobs_live
       WHERE server_id = :server
         AND (COALESCE(last_bytes_ts,0)=0 OR COALESCE(last_checked_ts,0)=0)
       ORDER BY started_at ASC
       LIMIT :lim
    ";
    $boot = $pdo->prepare($bootSql);
    $boot->bindValue(':server', $profileName, PDO::PARAM_STR);
    $boot->bindValue(':lim',    $BATCH_LIMIT, PDO::PARAM_INT);
    $boot->execute();
    while ($b = $boot->fetch(PDO::FETCH_ASSOC)) {
        $bJob = (string)$b['job_id'];
        $bUser= (string)$b['username'];
        try {
            // Prefer Progress.BytesDone from properties for accurate running bytes
            $propsB = api($base, '/admin/get-job-properties', $auth, [ 'TargetUser' => $bUser, 'JobID' => $bJob ]);
            $bytes = progressBytesFromProps($propsB);
            $alive = hasFreshHeartbeat($propsB, $RECHECK_SECS);
            if ($bytes <= 0) {
                $bytes = getJobLogBytes($base, $auth, $bUser, $bJob);
            }
            if ($bytes > 0) {
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile'=>$profileName,
                        'job_id'=>$bJob,
                        'username'=>$bUser,
                        'would_action'=>'bootstrap_touch_progress',
                        'upload_bytes'=>$bytes
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                } else {
                    touchProgress($pdo, $profileName, $bJob, $bytes);
                    resetAttempts($pdo, $profileName, $bJob);
                }
            } else if ($alive) {
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile'=>$profileName,
                        'job_id'=>$bJob,
                        'username'=>$bUser,
                        'would_action'=>'bootstrap_heartbeat_fresh'
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                } else {
                    markChecked($pdo, $profileName, $bJob);
                    resetAttempts($pdo, $profileName, $bJob);
                }
            } else {
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile'=>$profileName,
                        'job_id'=>$bJob,
                        'username'=>$bUser,
                        'would_action'=>'bootstrap_mark_checked'
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                } else {
                    markChecked($pdo, $profileName, $bJob);
                }
            }
        } catch (Throwable $e) {
            if ($DRY_RUN) {
                echo json_encode([
                    'profile'=>$profileName,
                    'job_id'=>$bJob,
                    'username'=>$bUser,
                    'would_action'=>'bootstrap_error',
                    'error'=>$e->getMessage()
                ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
            }
        }
    }

    // Select candidates: idle >= STALE_SECS, not hammered recently, under attempt cap
    $sql = "
      SELECT server_id, job_id, username, device, job_type, started_at,
             COALESCE(last_bytes, 0) AS last_bytes,
             COALESCE(last_bytes_ts, 0) AS last_bytes_ts,
             last_update,
             COALESCE(last_checked_ts, 0) AS last_checked_ts,
             COALESCE(cancel_attempts, 0) AS cancel_attempts
        FROM eb_jobs_live
       WHERE server_id = :server
         AND job_type = 4001
         AND (UNIX_TIMESTAMP() - GREATEST(NULLIF(last_bytes_ts,0), started_at, last_update)) >= :idle
         AND (UNIX_TIMESTAMP() - COALESCE(last_checked_ts,0)) >= :recheck
         AND COALESCE(cancel_attempts,0) < :maxAttempts
       ORDER BY last_checked_ts ASC
       LIMIT :lim
    ";
    $sel = $pdo->prepare($sql);
    $sel->bindValue(':server', $profileName, PDO::PARAM_STR);
    $sel->bindValue(':idle',   $STALE_SECS, PDO::PARAM_INT);
    $sel->bindValue(':recheck',$RECHECK_SECS, PDO::PARAM_INT);
    $sel->bindValue(':maxAttempts', $MAX_ATTEMPTS, PDO::PARAM_INT);
    $sel->bindValue(':lim',    $BATCH_LIMIT, PDO::PARAM_INT);
    $sel->execute();

    $count = 0;
    while ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        $jobId   = (string)$r['job_id'];
        $user    = (string)$r['username'];
        $device  = (string)$r['device'];
        $started = (int)$r['started_at'];
        $server  = (string)$r['server_id'];

        // Helper for consistent dry-run output lines
        $printDry = function (array $extra) use ($profileName, $r, $jobId, $user, $device, $STALE_SECS) {
            $sinceTs = (int)($r['last_bytes_ts'] ?: 0);
            $lastUpd = (int)($r['last_update']   ?: 0);
            $idleRef = max($sinceTs, $lastUpd, (int)$r['started_at']);
            $idleSec = max(0, time() - $idleRef);

            $row = array_merge([
                'profile'           => $profileName,
                'job_id'            => $jobId,
                'username'          => $user,
                'device'            => $device,
                'job_type'          => (string)$r['job_type'],
                'last_bytes'        => (int)$r['last_bytes'],
                'last_bytes_ts'     => (int)$r['last_bytes_ts'],
                'last_update'       => (int)$r['last_update'],
                'started_at'        => (int)$r['started_at'],
                'idle_seconds'      => $idleSec,
                'stale_threshold'   => $STALE_SECS,
            ], $extra);

            echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        };

        try {
            // 1) Confirm state + read UploadSize / EndTime / CancellationID
            $props = api($base, '/admin/get-job-properties', $auth, [
                'TargetUser' => $user,
                'JobID'      => $jobId,
            ]);

            $endTime = (int)($props['EndTime'] ?? 0);
            $statusN = isset($props['Status']) ? (int)$props['Status'] : null;
            $label   = mapStatusLabel($statusN);

            // Trust EndTime over ambiguous status
            if ($endTime > 0 && $label === 'running') {
                $label = 'finished';
            }

            if ($endTime > 0 || $label !== 'running') {
                // Terminal -> would finalize
                $bytes   = (int)($props['UploadSize'] ?? 0);
                if ($bytes <= 0) { $bytes = getJobLogBytes($base, $auth, $user, $jobId); }
                $endedAt = $endTime > 0 ? $endTime : time();
                $dur     = max(0, $endedAt - $started);
                $labelDb = normalizeStatusForDb($label);

                if ($DRY_RUN) {
                    $printDry([
                        'would_action'   => 'finalize',
                        'status'         => $labelDb,
                        'bytes'          => $bytes,
                        'duration_sec'   => $dur,
                        'ended_at'       => $endedAt,
                    ]);
                } else {
                    finalizeJob($pdo, [
                        'server_id'    => $server,
                        'job_id'       => $jobId,
                        'username'     => $user,
                        'device'       => $device,
                        'job_type'     => (string)$r['job_type'],
                        'status'       => $labelDb,
                        'bytes'        => $bytes,
                        'duration_sec' => $dur,
                        'ended_at'     => $endedAt,
                    ]);
                    logLine("[{$profileName}] finalize by REST job={$jobId} status={$labelDb}");
                }
                continue;
            }

            // Still running → check for byte progress / heartbeat
            $upload = progressBytesFromProps($props);
            // If properties don't expose progress, peek at job log for bytes
            if ($upload <= (int)$r['last_bytes']) {
                $upload = max($upload, getJobLogBytes($base, $auth, $user, $jobId));
            }
            $alive = hasFreshHeartbeat($props, $RECHECK_SECS);
            if ($upload > (int)$r['last_bytes']) {
                if ($DRY_RUN) {
                    $printDry([
                        'would_action'   => 'touch_progress',
                        'upload_bytes'   => $upload,
                    ]);
                } else {
                    touchProgress($pdo, $server, $jobId, $upload);
                    resetAttempts($pdo, $server, $jobId);
                    logLine("[{$profileName}] progress job={$jobId} bytes={$upload}");
                }
                continue;
            }

            // Heartbeat but no byte progress: treat as alive; reset attempts and skip
            if ($alive) {
                if ($DRY_RUN) {
                    $printDry([
                        'would_action' => 'heartbeat_fresh'
                    ]);
                } else {
                    markChecked($pdo, $server, $jobId);
                    resetAttempts($pdo, $server, $jobId);
                }
                continue;
            }

            // No progress ≥ STALE_SECS → try cancel, else abandon
            $canId = (string)($props['CancellationID'] ?? '');
            $didCancel = false;

            // Two-strike policy: on first miss, record a strike and skip cancel
            $attempts = (int)($r['cancel_attempts'] ?? 0);
            if ($attempts + 1 < 2) {
                if ($DRY_RUN) {
                    $printDry([
                        'would_action' => 'strike',
                        'strike'       => $attempts + 1
                    ]);
                } else {
                    bumpAttempt($pdo, $server, $jobId);
                }
                continue;
            }

            if ($canId !== '') {
                if ($DRY_RUN) {
                    $printDry([
                        'would_action' => 'cancel',
                        'note'         => 'CancellationID present; would call /admin/job/cancel',
                    ]);
                    // In dry-run: skip re-read and any counters; move to next job
                    continue;
                } else {
                    try {
                        api($base, '/admin/job/cancel', $auth, [
                            'TargetUser' => $user,
                            'JobID'      => $jobId,
                        ]);
                        $didCancel = true;
                        logLine("[{$profileName}] cancel sent job={$jobId}");
                    } catch (Throwable $e) {
                        logLine("[{$profileName}] cancel failed job={$jobId} err=" . $e->getMessage());
                    }
                }
            }

            if (!$didCancel) {
                if ($DRY_RUN) {
                    $printDry([
                        'would_action' => 'abandon',
                        'note'         => 'No CancellationID or cancel failed; would call /admin/job/abandon',
                    ]);
                    // In dry-run: skip re-read and any counters; move to next job
                    continue;
                } else {
                    try {
                        api($base, '/admin/job/abandon', $auth, [
                            'TargetUser' => $user,
                            'JobID'      => $jobId,
                        ]);
                        logLine("[{$profileName}] abandon sent job={$jobId}");
                    } catch (Throwable $e) {
                        // Could not abandon → count attempt and move on
                        bumpAttempt($pdo, $server, $jobId);
                        logLine("[{$profileName}] abandon failed job={$jobId} err=" . $e->getMessage());
                        continue;
                    }
                }
            }

            // Re-read to see if terminal now (real mode only)
            if (!$DRY_RUN) {
                $props2  = api($base, '/admin/get-job-properties', $auth, [
                    'TargetUser' => $user,
                    'JobID'      => $jobId,
                ]);
                $endTime2 = (int)($props2['EndTime'] ?? 0);
                $statusN2 = isset($props2['Status']) ? (int)$props2['Status'] : null;
                $label2   = mapStatusLabel($statusN2);

                if ($endTime2 > 0) {
                    // Trust EndTime
                    $label2 = ($label2 === 'running') ? 'finished' : $label2;
                }

                if ($endTime2 > 0 || $label2 !== 'running') {
                    $bytes2 = (int)($props2['UploadSize'] ?? 0);
                    if ($bytes2 <= 0) { $bytes2 = getJobLogBytes($base, $auth, $user, $jobId); }
                    $ended  = $endTime2 > 0 ? $endTime2 : time();
                    $dur2   = max(0, $ended - $started);
                    if ($label2 === 'running') { $label2 = $didCancel ? 'cancelled' : 'abandoned'; }
                    $label2Db = normalizeStatusForDb($label2);

                    finalizeJob($pdo, [
                        'server_id'    => $server,
                        'job_id'       => $jobId,
                        'username'     => $user,
                        'device'       => $device,
                        'job_type'     => (string)$r['job_type'],
                        'status'       => $label2Db,
                        'bytes'        => $bytes2,
                        'duration_sec' => $dur2,
                        'ended_at'     => $ended,
                    ]);
                    logLine("[{$profileName}] finalize job={$jobId} status={$label2Db}");
                } else {
                    // Still running: if device seems offline (no heartbeat), fall back to abandon immediately
                    $alive2 = hasFreshHeartbeat($props2, $RECHECK_SECS);
                    if (!$alive2) {
                        try {
                            api($base, '/admin/job/abandon', $auth, [
                                'TargetUser' => $user,
                                'JobID'      => $jobId,
                            ]);
                            // Re-read once after abandon
                            $props3  = api($base, '/admin/get-job-properties', $auth, [
                                'TargetUser' => $user,
                                'JobID'      => $jobId,
                            ]);
                            $endTime3 = (int)($props3['EndTime'] ?? 0);
                            $statusN3 = isset($props3['Status']) ? (int)$props3['Status'] : null;
                            $label3   = mapStatusLabel($statusN3);
                            if ($endTime3 > 0) { $label3 = ($label3 === 'running') ? 'finished' : $label3; }
                            if ($endTime3 > 0 || $label3 !== 'running') {
                                $bytes3 = (int)($props3['UploadSize'] ?? 0);
                                if ($bytes3 <= 0) { $bytes3 = getJobLogBytes($base, $auth, $user, $jobId); }
                                $ended3  = $endTime3 > 0 ? $endTime3 : time();
                                $dur3    = max(0, $ended3 - $started);
                                $label3Db = normalizeStatusForDb($label3 === 'running' ? 'abandoned' : $label3);
                                finalizeJob($pdo, [
                                    'server_id'    => $server,
                                    'job_id'       => $jobId,
                                    'username'     => $user,
                                    'device'       => $device,
                                    'job_type'     => (string)$r['job_type'],
                                    'status'       => $label3Db,
                                    'bytes'        => $bytes3,
                                    'duration_sec' => $dur3,
                                    'ended_at'     => $ended3,
                                ]);
                                logLine("[{$profileName}] finalize after abandon job={$jobId} status={$label3Db}");
                                return; // proceed next job
                            }
                        } catch (Throwable $e) {
                            // fall through to strike logic
                        }
                    }
                    // Still running (device may be online or abandon failed). Count attempt and move on.
                    bumpAttempt($pdo, $server, $jobId);
                    logLine("[{$profileName}] still-running post-cancel/abandon job={$jobId}, attempts+1");
                }
            }

        } catch (Throwable $e) {
            if ($DRY_RUN) {
                $printDry([
                    'would_action' => 'error',
                    'error'        => $e->getMessage(),
                ]);
            } else {
                bumpAttempt($pdo, $server, $jobId);
                logLine("[{$profileName}] error job={$jobId} " . $e->getMessage());
            }
        }
    }
    if ($DRY_RUN && $count === 0) {
        // We queried but found no rows to scan for this profile
        echo json_encode([
            'profile'         => $profileName,
            'message'         => 'no_candidates_meeting_thresholds',
            'stale_threshold' => $STALE_SECS,
            'recheck_secs'    => $RECHECK_SECS,
            'limit'           => $BATCH_LIMIT
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    

    logLine("[{$profileName}] scanned={$count} done");
}


/** Add helper columns to eb_jobs_live if missing (safe to call each run) */
function ensureLiveExtensions(PDO $pdo): void {
    $cols = [];
    $res = $pdo->query("SHOW COLUMNS FROM eb_jobs_live");
    while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
        $cols[strtolower($r['Field'])] = true;
    }
    $alters = [];
    if (!isset($cols['last_bytes']))      $alters[] = "ADD COLUMN last_bytes BIGINT NOT NULL DEFAULT 0";
    if (!isset($cols['last_bytes_ts']))   $alters[] = "ADD COLUMN last_bytes_ts INT NOT NULL DEFAULT 0";
    if (!isset($cols['cancel_attempts'])) $alters[] = "ADD COLUMN cancel_attempts TINYINT NOT NULL DEFAULT 0";
    if (!isset($cols['last_checked_ts'])) $alters[] = "ADD COLUMN last_checked_ts INT NOT NULL DEFAULT 0";
    if ($alters) {
        $sql = "ALTER TABLE eb_jobs_live " . implode(", ", $alters);
        $pdo->exec($sql);
    }
}

/** ---------- main ---------- */

// Build profile list
$profiles = resolveProfiles($cli);
if ($VERBOSE) {
    vout('[monitor] profiles=' . json_encode($profiles));
}

if (empty($profiles)) {
    fwrite(STDERR, "[monitor] No profiles configured/loaded. Exiting.\n");
    exit(0);
}

// Ensure flag for later message
$anyMatched = false;

foreach ($profiles as $p) {
    $profileName = (string)(is_array($p) ? ($p['name'] ?? $p['server_id'] ?? $p['id'] ?? (string)reset($p)) : $p);

    if (isset($cli['profile']) && $cli['profile'] !== $profileName) {
        continue;
    }
    $anyMatched = true;

    processProfile(
        $pdo,
        $profileName,
        $STALE_SECS,
        $RECHECK_SECS,
        $MAX_ATTEMPTS,
        $BATCH_LIMIT,
        isDryRun()
    );
}

if (isset($cli['profile']) && !$anyMatched) {
    fwrite(STDERR, "[monitor] Profile filter '--profile={$cli['profile']}' matched no profiles. Exiting.\n");
}



