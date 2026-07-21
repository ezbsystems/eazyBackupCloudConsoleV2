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
 *   EB_OFFLINE_CLEANUP_SECS=3600     // offline device + stale Comet heartbeat => cleanup
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
require_once __DIR__ . '/../lib/LiveJobState.php';
require_once __DIR__ . '/../lib/IncompleteJobReconciliation.php';

use WHMCS\Module\Addon\Eazybackup\IncompleteJobReconciliation;
use WHMCS\Module\Addon\Eazybackup\LiveJobState;

$STALE_SECS    = (int)(getenv('EB_MON_STALE_SECS')    ?: 3600);
$RECHECK_SECS  = (int)(getenv('EB_MON_RECHECK_SECS')  ?: 300);
$MAX_ATTEMPTS  = (int)(getenv('EB_MON_MAX_ATTEMPTS')  ?: 3);
$BATCH_LIMIT   = (int)(getenv('EB_MON_BATCH_LIMIT')   ?: 200);
$OFFLINE_CLEANUP_SECS = (int)(getenv('EB_OFFLINE_CLEANUP_SECS') ?: LiveJobState::offlineCleanupSecs());
$RECONCILE_MODE = strtolower((string)(getenv('EB_RECONCILE_INCOMPLETE_MODE') ?: 'off'));
$RECONCILE_ACTION_LIMIT = max(0, (int)(getenv('EB_RECONCILE_ACTION_LIMIT') ?: 25));
$RECONCILE_RETRY_COOLDOWN_SECS = max(
    3600,
    (int)(getenv('EB_RECONCILE_RETRY_COOLDOWN_SECS') ?: 86400)
);

$pdo = db();

// --- CLI flags (all optional) ---
$cli = getopt('', [
    'dry-run',           // print-only; don't cancel and don't mutate DB
    'profile::',         // restrict to a single COMET profile name
    'stale-secs::',      // override EB_MON_STALE_SECS
    'recheck-secs::',    // override EB_MON_RECHECK_SECS
    'max-attempts::',    // override EB_MON_MAX_ATTEMPTS
    'limit::',           // override EB_MON_BATCH_LIMIT
    'offline-cleanup-secs::', // override EB_OFFLINE_CLEANUP_SECS
    'reconcile-mode::',  // off|audit|enforce
    'reconcile-action-limit::',
    'retry-cooldown-secs::',
    'verbose::',         // verbose: 0/1
]);

$DRY_RUN = isset($cli['dry-run']) || (getenv('EB_MON_DRY_RUN') === '1');

if (isset($cli['stale-secs']))   $STALE_SECS   = (int)$cli['stale-secs'];
if (isset($cli['recheck-secs'])) $RECHECK_SECS = (int)$cli['recheck-secs'];
if (isset($cli['max-attempts'])) $MAX_ATTEMPTS = (int)$cli['max-attempts'];
if (isset($cli['limit']))        $BATCH_LIMIT  = (int)$cli['limit'];
if (isset($cli['offline-cleanup-secs'])) $OFFLINE_CLEANUP_SECS = (int)$cli['offline-cleanup-secs'];
if (isset($cli['reconcile-mode'])) $RECONCILE_MODE = strtolower((string)$cli['reconcile-mode']);
if (isset($cli['reconcile-action-limit'])) {
    $RECONCILE_ACTION_LIMIT = max(0, (int)$cli['reconcile-action-limit']);
}
if (isset($cli['retry-cooldown-secs'])) {
    $RECONCILE_RETRY_COOLDOWN_SECS = max(3600, (int)$cli['retry-cooldown-secs']);
}
if (!in_array($RECONCILE_MODE, ['off', 'audit', 'enforce'], true)) {
    throw new InvalidArgumentException(
        'EB_RECONCILE_INCOMPLETE_MODE must be off, audit, or enforce'
    );
}
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
        $decodedError = json_decode((string)$raw, true);
        $message = is_array($decodedError) && isset($decodedError['Message'])
            ? IncompleteJobReconciliation::sanitizeError((string)$decodedError['Message'])
            : '';
        throw new RuntimeException(
            "HTTP {$http}" . ($message !== '' ? ": {$message}" : ''),
            $http
        );
    }
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        throw new RuntimeException('Comet API returned malformed JSON');
    }
    IncompleteJobReconciliation::assertNoApiError($j);
    return $j;
}

function api(string $base, string $path, array $auth, array $payload): array {
    return httpPostForm($base . $path, array_merge($auth, $payload));
}

function apiMutation(string $base, string $path, array $auth, array $payload): array {
    $response = api($base, $path, $auth, $payload);
    IncompleteJobReconciliation::validateMutationResponse($response);
    return $response;
}

function acquireProfileMonitorLock(PDO $pdo, string $profile): ?string {
    $safeProfile = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $profile) ?: 'unknown';
    $lockName = substr("eb-monitor-{$safeProfile}", 0, 64);
    $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
    $stmt->execute([':lock_name' => $lockName]);
    return (int)$stmt->fetchColumn() === 1 ? $lockName : null;
}

function releaseProfileMonitorLock(PDO $pdo, string $lockName): void {
    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $stmt->execute([':lock_name' => $lockName]);
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

/** Resolve a terminal Comet status code when the API did not provide one. */
function terminalCometStatusCode(array $row): int {
    $explicit = isset($row['comet_status']) ? (int)$row['comet_status'] : 0;
    if ($explicit > 0 && !in_array($explicit, [6000, 6001, 6002], true)) {
        return $explicit;
    }

    return match (strtolower((string)($row['status'] ?? 'error'))) {
        'success' => 5000,
        'warning' => 7001,
        'missed'  => 7004,
        'skipped' => 7006,
        default   => 7002,
    };
}

/** Finalize a job into recent_24h, synchronize comet_jobs, and remove from live. */
function finalizeJob(PDO $pdo, array $row): void {
    // $row keys: server_id, job_id, username, device, job_type, status, bytes,
    // duration_sec, ended_at, optional comet_status
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

        $endedAt = (int)($row['ended_at'] ?? time());
        $bytes = (int)($row['bytes'] ?? 0);
        $cometStatus = terminalCometStatusCode($row);
        $mirror = $pdo->prepare("
            UPDATE comet_jobs
               SET status = :status_column,
                   ended_at = :ended_column,
                   last_status_at = :status_time,
                   upload_bytes = GREATEST(COALESCE(upload_bytes, 0), :upload_column),
                   content = JSON_SET(
                       COALESCE(content, JSON_OBJECT()),
                       '$.Status', CAST(:status_json AS UNSIGNED),
                       '$.EndTime', CAST(:ended_json AS UNSIGNED),
                       '$.UploadSize', CAST(:upload_json AS UNSIGNED)
                   )
             WHERE id = :job_id
        ");
        $endedSql = gmdate('Y-m-d H:i:s', $endedAt);
        $mirror->execute([
            ':status_column' => $cometStatus,
            ':ended_column'  => $endedSql,
            ':status_time'   => $endedSql,
            ':upload_column' => $bytes,
            ':status_json'   => $cometStatus,
            ':ended_json'    => $endedAt,
            ':upload_json'   => $bytes,
            ':job_id'        => $row['job_id'],
        ]);

        if ($mirror->rowCount() === 0) {
            $properties = isset($row['properties']) && is_array($row['properties'])
                ? $row['properties']
                : [];
            $properties['GUID'] = (string)$row['job_id'];
            $properties['Username'] = (string)($row['username'] ?? '');
            $properties['DeviceID'] = (string)($properties['DeviceID'] ?? ($row['device'] ?? ''));
            $properties['Classification'] = (int)($properties['Classification'] ?? ($row['job_type'] ?? 0));
            $properties['Status'] = $cometStatus;
            $properties['StartTime'] = (int)($properties['StartTime'] ?? ($row['started_at'] ?? $endedAt));
            $properties['EndTime'] = $endedAt;
            $properties['UploadSize'] = max(
                $bytes,
                (int)($properties['UploadSize'] ?? 0)
            );

            $clientId = 0;
            $clientLookup = $pdo->prepare("
                SELECT userid
                  FROM tblhosting
                 WHERE username = :username
                   AND domainstatus = 'Active'
                 LIMIT 1
            ");
            $clientLookup->execute([':username' => (string)($row['username'] ?? '')]);
            $foundClientId = $clientLookup->fetchColumn();
            if ($foundClientId !== false) {
                $clientId = (int)$foundClientId;
            }

            $deviceId = (string)$properties['DeviceID'];
            $insertMirror = $pdo->prepare("
                INSERT INTO comet_jobs
                  (id, content, client_id, username, comet_vault_id,
                   comet_device_id, comet_item_id, type, status,
                   comet_snapshot_id, comet_cancellation_id, total_bytes,
                   total_files, total_directories, upload_bytes, download_bytes,
                   total_ms_accounts, started_at, ended_at, last_status_at)
                VALUES
                  (:id, :content, :client_id, :username, :vault_id,
                   :device_id, :item_id, :type, :status,
                   :snapshot_id, :cancellation_id, :total_bytes,
                   :total_files, :total_directories, :upload_bytes, :download_bytes,
                   :total_ms_accounts, :started_at, :ended_at, :last_status_at)
                ON DUPLICATE KEY UPDATE
                  content = VALUES(content),
                  status = VALUES(status),
                  upload_bytes = GREATEST(upload_bytes, VALUES(upload_bytes)),
                  ended_at = VALUES(ended_at),
                  last_status_at = VALUES(last_status_at)
            ");
            $insertMirror->execute([
                ':id' => (string)$row['job_id'],
                ':content' => json_encode($properties, JSON_UNESCAPED_SLASHES),
                ':client_id' => $clientId,
                ':username' => (string)($row['username'] ?? ''),
                ':vault_id' => (string)($properties['DestinationGUID'] ?? ''),
                ':device_id' => $deviceId !== ''
                    ? hash('sha256', (string)$clientId . $deviceId)
                    : '',
                ':item_id' => (string)($properties['SourceGUID'] ?? ''),
                ':type' => (int)$properties['Classification'],
                ':status' => $cometStatus,
                ':snapshot_id' => isset($properties['SnapshotID'])
                    ? (string)$properties['SnapshotID']
                    : null,
                ':cancellation_id' => (string)($properties['CancellationID'] ?? ''),
                ':total_bytes' => (int)($properties['TotalSize'] ?? 0),
                ':total_files' => (int)($properties['TotalFiles'] ?? 0),
                ':total_directories' => (int)($properties['TotalDirectories'] ?? 0),
                ':upload_bytes' => (int)$properties['UploadSize'],
                ':download_bytes' => (int)($properties['DownloadSize'] ?? 0),
                ':total_ms_accounts' => (int)($properties['TotalAccountsCount'] ?? 0),
                ':started_at' => gmdate('Y-m-d H:i:s', (int)$properties['StartTime']),
                ':ended_at' => $endedSql,
                ':last_status_at' => $endedSql,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Extract the newest Comet progress heartbeat timestamp from job properties */
function progressHeartbeatTs(array $props): int {
    return LiveJobState::progressHeartbeatTs($props);
}

/** Update progress markers in eb_jobs_live */
function touchProgress(PDO $pdo, string $serverId, string $jobId, int $bytes, ?int $activityTs = null): void {
    if ($activityTs !== null && $activityTs > 0) {
        $stmt = $pdo->prepare("
            UPDATE eb_jobs_live
               SET last_bytes = :b,
                   last_bytes_ts = :ts,
                   last_checked_ts = UNIX_TIMESTAMP()
             WHERE server_id=:s AND job_id=:j
        ");
        $stmt->execute([':b'=>$bytes, ':ts'=>$activityTs, ':s'=>$serverId, ':j'=>$jobId]);
        return;
    }

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

/** Record the bootstrap inspection as the first stale-job strike. */
function markBootstrapStrike(PDO $pdo, string $serverId, string $jobId): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET cancel_attempts = GREATEST(cancel_attempts, 1),
               last_checked_ts = UNIX_TIMESTAMP()
         WHERE server_id=:s AND job_id=:j
    ");
    $stmt->execute([':s'=>$serverId, ':j'=>$jobId]);
}

/** Update only last_checked_ts without incrementing attempts (rate-limited). */
function markChecked(PDO $pdo, string $serverId, string $jobId, int $recheckSecs = 300): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET last_checked_ts = UNIX_TIMESTAMP()
         WHERE server_id=:s AND job_id=:j
           AND (last_checked_ts IS NULL OR last_checked_ts = 0 OR last_checked_ts < UNIX_TIMESTAMP() - :recheck)
    ");
    $stmt->execute([':s'=>$serverId, ':j'=>$jobId, ':recheck'=>$recheckSecs]);
}

/** Reset strike/attempt counter after observing progress/heartbeat */
function resetAttempts(PDO $pdo, string $serverId, string $jobId, int $recheckSecs = 300): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET cancel_attempts = 0,
               last_checked_ts = UNIX_TIMESTAMP()
         WHERE server_id=:s AND job_id=:j
           AND (cancel_attempts > 0 OR last_checked_ts IS NULL OR last_checked_ts = 0 OR last_checked_ts < UNIX_TIMESTAMP() - :recheck)
    ");
    $stmt->execute([':s'=>$serverId, ':j'=>$jobId, ':recheck'=>$recheckSecs]);
}

function fetchIncompleteJobs(string $base, array $auth): array {
    $rows = api($base, '/admin/get-jobs-for-date-range', $auth, [
        'Start' => 0,
        'End' => 0,
    ]);
    if (!array_is_list($rows)) {
        throw new RuntimeException('malformed incomplete-jobs response');
    }
    return $rows;
}

/** @return array<string,array{is_active:bool,offline_since:?string}> */
function loadReconciliationDeviceMap(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT username, id, hash, name, is_active, offline_since
          FROM comet_devices
         WHERE revoked_at IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $map = [];
    foreach ($rows as $row) {
        $username = (string)($row['username'] ?? '');
        if ($username === '') {
            continue;
        }
        $state = [
            'is_active' => (int)($row['is_active'] ?? 0) === 1,
            'offline_since' => isset($row['offline_since'])
                ? (string)$row['offline_since']
                : null,
        ];
        foreach (['id', 'hash', 'name'] as $field) {
            $identifier = (string)($row[$field] ?? '');
            if ($identifier !== '') {
                $map[$username . "\0" . $identifier] = $state;
            }
        }
    }
    return $map;
}

/** @return array<string,array<string,mixed>> */
function loadExistingReconciliationRows(PDO $pdo, string $profile): array {
    $stmt = $pdo->prepare("
        SELECT job_id, last_bytes, last_bytes_ts, last_update, last_checked_ts,
               cancel_attempts, stale_observations, action_stage, next_action_ts
          FROM eb_jobs_live
         WHERE server_id = :profile
    ");
    $stmt->execute([':profile' => $profile]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $rows[(string)$row['job_id']] = $row;
    }
    return $rows;
}

function recordProfileDiscoverySuccess(PDO $pdo, string $profile, int $count): void {
    $stmt = $pdo->prepare("
        INSERT INTO eb_monitor_profile_state
          (profile, last_success_ts, last_error_ts, consecutive_failures,
           last_incomplete_count, last_error)
        VALUES (:profile, UNIX_TIMESTAMP(), 0, 0, :count, NULL)
        ON DUPLICATE KEY UPDATE
          last_success_ts = VALUES(last_success_ts),
          consecutive_failures = 0,
          last_incomplete_count = VALUES(last_incomplete_count),
          last_error = NULL
    ");
    $stmt->execute([':profile' => $profile, ':count' => $count]);
}

function recordProfileDiscoveryFailure(PDO $pdo, string $profile, Throwable $error): void {
    $stmt = $pdo->prepare("
        INSERT INTO eb_monitor_profile_state
          (profile, last_success_ts, last_error_ts, consecutive_failures,
           last_incomplete_count, last_error)
        VALUES (:profile, 0, UNIX_TIMESTAMP(), 1, 0, :error)
        ON DUPLICATE KEY UPDATE
          last_error_ts = VALUES(last_error_ts),
          consecutive_failures = consecutive_failures + 1,
          last_error = VALUES(last_error)
    ");
    $stmt->execute([
        ':profile' => $profile,
        ':error' => IncompleteJobReconciliation::sanitizeError($error->getMessage()),
    ]);
}

function upsertDiscoveredIncompleteJob(PDOStatement $stmt, string $profile, array $job): void {
    $startedAt = (int)$job['started_at'];
    $heartbeatTs = (int)$job['heartbeat_ts'];
    $stmt->execute([
        ':server' => $profile,
        ':job' => (string)$job['job_id'],
        ':username' => (string)$job['username'],
        ':device' => (string)$job['device'],
        ':type' => (int)$job['job_type'],
        ':started' => $startedAt,
        ':bytes' => (int)$job['bytes'],
        ':activity' => max($startedAt, $heartbeatTs),
        ':heartbeat' => max(0, $heartbeatTs),
    ]);
}

/**
 * Discover all authoritative incomplete jobs for one Comet profile.
 *
 * Audit mode writes only eb_monitor_profile_state. Enforce mode also
 * normalizes eligible jobs into eb_jobs_live.
 *
 * @return array<string,mixed>
 */
function discoverIncompleteJobs(
    PDO $pdo,
    string $profile,
    string $base,
    array $auth,
    string $mode,
    int $staleSecs,
    int $recheckSecs,
    int $maxAttempts,
    bool $recordHealth = true
): array {
    try {
        $classified = IncompleteJobReconciliation::classifyResponse(
            fetchIncompleteJobs($base, $auth)
        );
        $existingRows = loadExistingReconciliationRows($pdo, $profile);
        $deviceMap = loadReconciliationDeviceMap($pdo);
        $now = time();
        $decisionCounts = [];
        $newCount = 0;
        $trackedCount = 0;

        $upsert = null;
        if ($mode === 'enforce') {
            $upsert = $pdo->prepare("
                INSERT INTO eb_jobs_live
                  (server_id, job_id, username, device, job_type, started_at,
                   bytes_done, throughput_bps, last_update, last_bytes, last_bytes_ts)
                VALUES
                  (:server, :job, :username, :device, :type, :started,
                   :bytes, 0, :activity, :bytes, :heartbeat)
                ON DUPLICATE KEY UPDATE
                  username = VALUES(username),
                  device = VALUES(device),
                  job_type = VALUES(job_type),
                  stale_observations = CASE
                    WHEN VALUES(bytes_done) > last_bytes
                      OR VALUES(last_bytes_ts) > last_bytes_ts THEN 0
                    ELSE stale_observations END,
                  action_stage = CASE
                    WHEN VALUES(bytes_done) > last_bytes
                      OR VALUES(last_bytes_ts) > last_bytes_ts THEN 'none'
                    ELSE action_stage END,
                  cancel_attempts = CASE
                    WHEN VALUES(bytes_done) > last_bytes
                      OR VALUES(last_bytes_ts) > last_bytes_ts THEN 0
                    ELSE cancel_attempts END,
                  next_action_ts = CASE
                    WHEN VALUES(bytes_done) > last_bytes
                      OR VALUES(last_bytes_ts) > last_bytes_ts THEN 0
                    ELSE next_action_ts END,
                  last_action_error = CASE
                    WHEN VALUES(bytes_done) > last_bytes
                      OR VALUES(last_bytes_ts) > last_bytes_ts THEN NULL
                    ELSE last_action_error END,
                  bytes_done = GREATEST(bytes_done, VALUES(bytes_done)),
                  last_bytes_ts = CASE
                    WHEN VALUES(bytes_done) > last_bytes THEN UNIX_TIMESTAMP()
                    ELSE GREATEST(last_bytes_ts, VALUES(last_bytes_ts)) END,
                  last_bytes = GREATEST(last_bytes, VALUES(last_bytes)),
                  last_update = GREATEST(last_update, VALUES(last_update))
            ");
        }

        foreach ($classified['jobs'] as $job) {
            $jobId = (string)$job['job_id'];
            $existing = $existingRows[$jobId] ?? null;
            if ($existing === null) {
                $newCount++;
            } else {
                $trackedCount++;
            }

            $activityTs = max(
                (int)$job['started_at'],
                (int)$job['heartbeat_ts'],
                (int)($existing['last_bytes_ts'] ?? 0),
                (int)($existing['last_update'] ?? 0)
            );
            if ($existing !== null && (int)$job['bytes'] > (int)($existing['last_bytes'] ?? 0)) {
                $activityTs = $now;
            }

            $decision = IncompleteJobReconciliation::decide([
                'now' => $now,
                'activity_ts' => $activityTs,
                'stale_secs' => $staleSecs,
                'last_checked_ts' => (int)($existing['last_checked_ts'] ?? 0),
                'recheck_secs' => $recheckSecs,
                'stale_observations' => (int)($existing['stale_observations'] ?? 0),
                'action_stage' => (string)($existing['action_stage'] ?? 'none'),
                'next_action_ts' => (int)($existing['next_action_ts'] ?? 0),
                'action_attempts' => (int)($existing['cancel_attempts'] ?? 0),
                'max_attempts' => $maxAttempts,
                'has_cancellation_id' => (string)$job['cancellation_id'] !== '',
            ]);
            $action = (string)$decision['action'];
            $decisionCounts[$action] = ($decisionCounts[$action] ?? 0) + 1;

            $deviceKey = (string)$job['username'] . "\0" . (string)$job['device'];
            $deviceState = $deviceMap[$deviceKey] ?? null;
            if ($mode === 'audit') {
                echo json_encode([
                    'event' => 'incomplete_job_audit',
                    'profile' => $profile,
                    'username' => (string)$job['username'],
                    'job_id' => $jobId,
                    'started_at' => (int)$job['started_at'],
                    'job_age_seconds' => max(0, $now - (int)$job['started_at']),
                    'heartbeat_age_seconds' => (int)$job['heartbeat_ts'] > 0
                        ? max(0, $now - (int)$job['heartbeat_ts'])
                        : null,
                    'device_state' => $deviceState === null
                        ? 'unknown'
                        : ($deviceState['is_active'] ? 'online' : 'offline'),
                    'offline_since' => $deviceState['offline_since'] ?? null,
                    'would_action' => $action,
                    'reason' => (string)$decision['reason'],
                ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
            } elseif ($upsert instanceof PDOStatement) {
                upsertDiscoveredIncompleteJob($upsert, $profile, $job);
            }
        }

        if ($recordHealth) {
            recordProfileDiscoverySuccess($pdo, $profile, (int)$classified['total']);
        }
        $summary = [
            'event' => 'incomplete_job_discovery_summary',
            'profile' => $profile,
            'mode' => $mode,
            'returned' => (int)$classified['total'],
            'eligible' => (int)($classified['counts']['eligible'] ?? 0),
            'new' => $newCount,
            'already_tracked' => $trackedCount,
            'classification_counts' => $classified['counts'],
            'decision_counts' => $decisionCounts,
        ];
        echo json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return $summary;
    } catch (Throwable $e) {
        try {
            recordProfileDiscoveryFailure($pdo, $profile, $e);
        } catch (Throwable $stateError) {
            logLine("[{$profile}] reconciliation state error: " .
                IncompleteJobReconciliation::sanitizeError($stateError->getMessage()));
        }
        throw $e;
    }
}

function resetReconciliationState(
    PDO $pdo,
    string $serverId,
    string $jobId,
    int $bytes,
    int $activityTs
): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET bytes_done = GREATEST(bytes_done, :bytes_done),
               last_bytes = GREATEST(last_bytes, :last_bytes),
               last_bytes_ts = GREATEST(last_bytes_ts, :activity),
               last_update = GREATEST(last_update, :activity),
               last_checked_ts = UNIX_TIMESTAMP(),
               stale_observations = 0,
               action_stage = 'none',
               cancel_attempts = 0,
               next_action_ts = 0,
               last_action_error = NULL
         WHERE server_id = :server AND job_id = :job
    ");
    $stmt->execute([
        ':bytes_done' => max(0, $bytes),
        ':last_bytes' => max(0, $bytes),
        ':activity' => max(0, $activityTs),
        ':server' => $serverId,
        ':job' => $jobId,
    ]);
}

function recordStaleObservation(
    PDO $pdo,
    string $serverId,
    string $jobId,
    int $recheckSecs
): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET stale_observations = stale_observations + 1,
               last_checked_ts = UNIX_TIMESTAMP(),
               next_action_ts = UNIX_TIMESTAMP() + :recheck,
               last_action_error = NULL
         WHERE server_id = :server AND job_id = :job
    ");
    $stmt->execute([
        ':recheck' => $recheckSecs,
        ':server' => $serverId,
        ':job' => $jobId,
    ]);
}

function recordActionAttempt(
    PDO $pdo,
    string $serverId,
    string $jobId,
    string $stage,
    int $recheckSecs,
    ?string $error = null
): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET action_stage = :stage,
               cancel_attempts = cancel_attempts + 1,
               last_checked_ts = UNIX_TIMESTAMP(),
               next_action_ts = UNIX_TIMESTAMP() + :recheck,
               last_action_error = :error
         WHERE server_id = :server AND job_id = :job
    ");
    $stmt->execute([
        ':stage' => $stage,
        ':recheck' => $recheckSecs,
        ':error' => $error,
        ':server' => $serverId,
        ':job' => $jobId,
    ]);
}

function recordActionFailure(
    PDO $pdo,
    string $serverId,
    string $jobId,
    int $recheckSecs,
    string $error
): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET cancel_attempts = cancel_attempts + 1,
               last_checked_ts = UNIX_TIMESTAMP(),
               next_action_ts = UNIX_TIMESTAMP() + :recheck,
               last_action_error = :error
         WHERE server_id = :server AND job_id = :job
    ");
    $stmt->execute([
        ':recheck' => $recheckSecs,
        ':error' => $error,
        ':server' => $serverId,
        ':job' => $jobId,
    ]);
}

function recordReconciliationCheckError(
    PDO $pdo,
    string $serverId,
    string $jobId,
    int $recheckSecs,
    string $error
): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET last_checked_ts = UNIX_TIMESTAMP(),
               next_action_ts = UNIX_TIMESTAMP() + :recheck,
               last_action_error = :error
         WHERE server_id = :server AND job_id = :job
    ");
    $stmt->execute([
        ':recheck' => $recheckSecs,
        ':error' => $error,
        ':server' => $serverId,
        ':job' => $jobId,
    ]);
}

function markReconciliationExhausted(
    PDO $pdo,
    string $serverId,
    string $jobId,
    int $cooldownSecs
): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET action_stage = 'exhausted',
               last_checked_ts = UNIX_TIMESTAMP(),
               next_action_ts = UNIX_TIMESTAMP() + :cooldown
         WHERE server_id = :server AND job_id = :job
    ");
    $stmt->execute([
        ':cooldown' => $cooldownSecs,
        ':server' => $serverId,
        ':job' => $jobId,
    ]);
}

function resetExpiredReconciliationCooldown(
    PDO $pdo,
    string $serverId,
    string $jobId,
    int $recheckSecs
): void {
    $stmt = $pdo->prepare("
        UPDATE eb_jobs_live
           SET stale_observations = 1,
               action_stage = 'none',
               cancel_attempts = 0,
               last_checked_ts = UNIX_TIMESTAMP(),
               next_action_ts = UNIX_TIMESTAMP() + :recheck,
               last_action_error = NULL
         WHERE server_id = :server AND job_id = :job
    ");
    $stmt->execute([
        ':recheck' => $recheckSecs,
        ':server' => $serverId,
        ':job' => $jobId,
    ]);
}

function isCometAuthError(Throwable $e): bool {
    return in_array((int)$e->getCode(), [401, 403], true)
        || preg_match('/\b(?:HTTP|Comet API error)\s+(401|403)\b/', $e->getMessage()) === 1;
}

function isCometNotFoundError(Throwable $e): bool {
    return (int)$e->getCode() === 404
        || preg_match('/\b(?:HTTP|Comet API error)\s+404\b/', $e->getMessage()) === 1;
}

function isCometDeviceUnavailableError(Throwable $e): bool {
    return (int)$e->getCode() === 400
        && stripos($e->getMessage(), 'device is unavailable') !== false;
}

/** @param array<string,mixed> $row */
function finalizeMissingCometJob(PDO $pdo, array $row): void {
    $endedAt = time();
    finalizeJob($pdo, [
        'server_id' => (string)$row['server_id'],
        'job_id' => (string)$row['job_id'],
        'username' => (string)$row['username'],
        'device' => (string)$row['device'],
        'job_type' => (string)$row['job_type'],
        'status' => 'error',
        'bytes' => (int)$row['last_bytes'],
        'duration_sec' => max(0, $endedAt - (int)$row['started_at']),
        'ended_at' => $endedAt,
        'comet_status' => 7002,
    ]);
}

/**
 * Enforce reconciliation for WebSocket- and Comet-discovered live jobs.
 *
 * @return array<string,int>
 */
function processReconciledLiveJobs(
    PDO $pdo,
    string $profile,
    string $base,
    array $auth,
    int $staleSecs,
    int $recheckSecs,
    int $maxAttempts,
    int $batchLimit,
    int $offlineCleanupSecs,
    int $actionLimit,
    int $retryCooldownSecs
): array {
    $stmt = $pdo->prepare("
        SELECT server_id, job_id, username, device, job_type, started_at,
               COALESCE(last_bytes, 0) AS last_bytes,
               COALESCE(last_bytes_ts, 0) AS last_bytes_ts,
               COALESCE(last_update, 0) AS last_update,
               COALESCE(last_checked_ts, 0) AS last_checked_ts,
               COALESCE(cancel_attempts, 0) AS cancel_attempts,
               COALESCE(stale_observations, 0) AS stale_observations,
               COALESCE(action_stage, 'none') AS action_stage,
               COALESCE(next_action_ts, 0) AS next_action_ts
          FROM eb_jobs_live
         WHERE server_id = :server
           AND job_type = 4001
           AND (UNIX_TIMESTAMP() - GREATEST(
                 COALESCE(NULLIF(last_bytes_ts, 0), 0),
                 started_at,
                 last_update
               )) >= :stale
           AND COALESCE(next_action_ts, 0) <= UNIX_TIMESTAMP()
           AND (UNIX_TIMESTAMP() - COALESCE(last_checked_ts, 0)) >= :recheck
         ORDER BY last_checked_ts ASC, started_at ASC
         LIMIT :lim
    ");
    $stmt->bindValue(':server', $profile, PDO::PARAM_STR);
    $stmt->bindValue(':stale', $staleSecs, PDO::PARAM_INT);
    $stmt->bindValue(':recheck', $recheckSecs, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $batchLimit, PDO::PARAM_INT);
    $stmt->execute();

    $deviceMap = loadReconciliationDeviceMap($pdo);
    $budget = max(0, $actionLimit);
    $summary = [
        'scanned' => 0,
        'fresh' => 0,
        'strikes' => 0,
        'cancel_attempts' => 0,
        'cancel_unavailable' => 0,
        'abandon_attempts' => 0,
        'finalized' => 0,
        'action_cap_deferred' => 0,
        'exhausted' => 0,
        'errors' => 0,
        'profile_failures' => 0,
        'reconcile_action_limit' => $actionLimit,
    ];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $summary['scanned']++;
        $jobId = (string)$row['job_id'];
        $username = (string)$row['username'];

        try {
            $props = api($base, '/admin/get-job-properties', $auth, [
                'TargetUser' => $username,
                'JobID' => $jobId,
            ]);
        } catch (Throwable $e) {
            if (isCometAuthError($e)) {
                recordProfileDiscoveryFailure($pdo, $profile, $e);
                $summary['profile_failures']++;
                logLine("[{$profile}] reconciliation auth failure; profile processing stopped");
                break;
            }
            if (isCometNotFoundError($e)) {
                finalizeMissingCometJob($pdo, $row);
                $summary['finalized']++;
                logLine("[{$profile}] finalize missing job={$jobId} reason=comet_job_not_found");
                continue;
            }
            $summary['errors']++;
            recordReconciliationCheckError(
                $pdo,
                $profile,
                $jobId,
                $recheckSecs,
                IncompleteJobReconciliation::sanitizeError($e->getMessage())
            );
            continue;
        }

        $status = isset($props['Status']) ? (int)$props['Status'] : null;
        $endTime = (int)($props['EndTime'] ?? 0);
        if ($status === null) {
            $summary['errors']++;
            recordReconciliationCheckError(
                $pdo,
                $profile,
                $jobId,
                $recheckSecs,
                'malformed job properties: missing Status'
            );
            continue;
        }

        $classification = isset($props['Classification']) && is_numeric($props['Classification'])
            ? (int)$props['Classification']
            : null;
        if ($classification !== IncompleteJobReconciliation::BACKUP_CLASSIFICATION) {
            $summary['errors']++;
            recordReconciliationCheckError(
                $pdo,
                $profile,
                $jobId,
                $recheckSecs,
                'job properties classification is missing or not backup'
            );
            continue;
        }

        $label = mapStatusLabel($status);
        if (
            IncompleteJobReconciliation::isKnownTerminalStatus($status)
            || (IncompleteJobReconciliation::isRunningStatus($status) && $endTime > 0)
        ) {
            $endedAt = $endTime > 0 ? $endTime : time();
            $bytes = progressBytesFromProps($props);
            finalizeJob($pdo, [
                'server_id' => $profile,
                'job_id' => $jobId,
                'username' => $username,
                'device' => (string)$row['device'],
                'job_type' => (string)$row['job_type'],
                'status' => normalizeStatusForDb($label),
                'bytes' => max((int)$row['last_bytes'], $bytes),
                'duration_sec' => max(0, $endedAt - (int)$row['started_at']),
                'ended_at' => $endedAt,
                'comet_status' => $status,
                'properties' => $props,
            ]);
            $summary['finalized']++;
            continue;
        }
        if (!IncompleteJobReconciliation::isRunningStatus($status)) {
            $summary['errors']++;
            recordReconciliationCheckError(
                $pdo,
                $profile,
                $jobId,
                $recheckSecs,
                "unrecognized non-terminal Comet status {$status}"
            );
            continue;
        }

        $now = time();
        $bytes = progressBytesFromProps($props);
        $heartbeatTs = progressHeartbeatTs($props);
        $activityTs = max(
            (int)$row['started_at'],
            (int)$row['last_update'],
            (int)$row['last_bytes_ts'],
            $heartbeatTs
        );
        if ($bytes > (int)$row['last_bytes']) {
            $activityTs = $now;
        }

        $decision = IncompleteJobReconciliation::decide([
            'now' => $now,
            'activity_ts' => $activityTs,
            'stale_secs' => $staleSecs,
            'last_checked_ts' => (int)$row['last_checked_ts'],
            'recheck_secs' => $recheckSecs,
            'stale_observations' => (int)$row['stale_observations'],
            'action_stage' => (string)$row['action_stage'],
            'next_action_ts' => (int)$row['next_action_ts'],
            'action_attempts' => (int)$row['cancel_attempts'],
            'max_attempts' => $maxAttempts,
            'has_cancellation_id' => (string)($props['CancellationID'] ?? '') !== '',
        ]);
        $action = (string)$decision['action'];

        if ($action === 'fresh') {
            resetReconciliationState($pdo, $profile, $jobId, $bytes, $activityTs);
            $summary['fresh']++;
            continue;
        }
        if ($action === 'defer') {
            continue;
        }
        if ($action === 'cooldown_reset') {
            resetExpiredReconciliationCooldown($pdo, $profile, $jobId, $recheckSecs);
            $summary['strikes']++;
            continue;
        }
        if ($action === 'exhaust') {
            markReconciliationExhausted($pdo, $profile, $jobId, $retryCooldownSecs);
            $summary['exhausted']++;
            continue;
        }
        if ($action === 'strike') {
            recordStaleObservation($pdo, $profile, $jobId, $recheckSecs);
            $summary['strikes']++;
            continue;
        }

        if ($budget <= 0) {
            $summary['action_cap_deferred']++;
            continue;
        }
        $budget--;

        $endpoint = $action === 'cancel'
            ? '/admin/job/cancel'
            : '/admin/job/abandon';
        $stage = $action === 'cancel'
            ? 'cancel_requested'
            : 'abandon_requested';
        $summary[$action === 'cancel' ? 'cancel_attempts' : 'abandon_attempts']++;

        try {
            apiMutation($base, $endpoint, $auth, [
                'TargetUser' => $username,
                'JobID' => $jobId,
            ]);
            recordActionAttempt($pdo, $profile, $jobId, $stage, $recheckSecs);
        } catch (Throwable $e) {
            if (isCometAuthError($e)) {
                recordProfileDiscoveryFailure($pdo, $profile, $e);
                $summary['profile_failures']++;
                logLine("[{$profile}] reconciliation auth failure; profile processing stopped");
                break;
            }
            if ($action === 'cancel' && isCometDeviceUnavailableError($e)) {
                $summary['cancel_unavailable']++;
                recordActionAttempt(
                    $pdo,
                    $profile,
                    $jobId,
                    'cancel_unavailable',
                    $recheckSecs,
                    IncompleteJobReconciliation::sanitizeError($e->getMessage())
                );
                continue;
            }
            $summary['errors']++;
            recordActionFailure(
                $pdo,
                $profile,
                $jobId,
                $recheckSecs,
                IncompleteJobReconciliation::sanitizeError($e->getMessage())
            );
            continue;
        }

        try {
            $after = api($base, '/admin/get-job-properties', $auth, [
                'TargetUser' => $username,
                'JobID' => $jobId,
            ]);
            $afterStatus = isset($after['Status']) ? (int)$after['Status'] : null;
            $afterEnd = (int)($after['EndTime'] ?? 0);
            $afterLabel = mapStatusLabel($afterStatus);
            if (
                $afterStatus !== null
                && (
                    IncompleteJobReconciliation::isKnownTerminalStatus($afterStatus)
                    || (
                        IncompleteJobReconciliation::isRunningStatus($afterStatus)
                        && $afterEnd > 0
                    )
                )
            ) {
                $endedAt = $afterEnd > 0 ? $afterEnd : time();
                $afterBytes = progressBytesFromProps($after);
                finalizeJob($pdo, [
                    'server_id' => $profile,
                    'job_id' => $jobId,
                    'username' => $username,
                    'device' => (string)$row['device'],
                    'job_type' => (string)$row['job_type'],
                    'status' => normalizeStatusForDb($afterLabel),
                    'bytes' => max((int)$row['last_bytes'], $afterBytes),
                    'duration_sec' => max(0, $endedAt - (int)$row['started_at']),
                    'ended_at' => $endedAt,
                    'comet_status' => $afterStatus,
                    'properties' => $after,
                ]);
                $summary['finalized']++;
            } elseif (
                $afterStatus === null
                || !IncompleteJobReconciliation::isRunningStatus($afterStatus)
            ) {
                $summary['errors']++;
                recordReconciliationCheckError(
                    $pdo,
                    $profile,
                    $jobId,
                    $recheckSecs,
                    $afterStatus === null
                        ? 'malformed post-action properties: missing Status'
                        : "unrecognized post-action Comet status {$afterStatus}"
                );
            }
        } catch (Throwable $e) {
            if (isCometAuthError($e)) {
                recordProfileDiscoveryFailure($pdo, $profile, $e);
                $summary['profile_failures']++;
                logLine("[{$profile}] reconciliation auth failure after action");
                break;
            }
            if (isCometNotFoundError($e)) {
                finalizeMissingCometJob($pdo, $row);
                $summary['finalized']++;
                logLine("[{$profile}] finalize missing post-action job={$jobId} reason=comet_job_not_found");
            } else {
                $summary['errors']++;
                recordReconciliationCheckError(
                    $pdo,
                    $profile,
                    $jobId,
                    $recheckSecs,
                    IncompleteJobReconciliation::sanitizeError($e->getMessage())
                );
            }
        }

        $deviceKey = $username . "\0" . (string)$row['device'];
        $device = $deviceMap[$deviceKey] ?? null;
        $offlineTs = LiveJobState::offlineSinceToUnix($device['offline_since'] ?? null);
        $reason = $device !== null
            && !$device['is_active']
            && $offlineTs > 0
            && ($now - $offlineTs) >= $offlineCleanupSecs
                ? 'offline_stale'
                : 'heartbeat_stale';
        logLine("[{$profile}] reconciliation {$action} job={$jobId} reason={$reason}");
    }

    echo json_encode(array_merge([
        'event' => 'incomplete_job_enforcement_summary',
        'profile' => $profile,
    ], $summary), JSON_UNESCAPED_SLASHES) . PHP_EOL;

    return $summary;
}

/** ---------- core logic ---------- */

function processOfflineLiveJobs(
    PDO $pdo,
    string $profileName,
    string $base,
    array $auth,
    int $OFFLINE_CLEANUP_SECS,
    int $STALE_SECS,
    int $RECHECK_SECS,
    int $MAX_ATTEMPTS,
    int $BATCH_LIMIT,
    bool $DRY_RUN = false
): int {
    $sql = "
      SELECT j.server_id, j.job_id, j.username, j.device, j.job_type, j.started_at,
             COALESCE(j.last_bytes, 0) AS last_bytes,
             COALESCE(j.last_bytes_ts, 0) AS last_bytes_ts,
             j.last_update,
             COALESCE(j.last_checked_ts, 0) AS last_checked_ts,
             COALESCE(j.cancel_attempts, 0) AS cancel_attempts,
             d.offline_since
        FROM eb_jobs_live j
        INNER JOIN comet_devices d
          ON d.revoked_at IS NULL
         AND BINARY d.username = BINARY j.username
         AND (
           BINARY d.hash = BINARY j.device
           OR BINARY d.id = BINARY j.device
           OR BINARY d.name = BINARY j.device
         )
       WHERE j.server_id = :server
         AND j.job_type = 4001
         AND d.is_active = 0
         AND d.offline_since IS NOT NULL
         AND (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(d.offline_since)) >= :offline_secs
         AND (UNIX_TIMESTAMP() - COALESCE(j.last_checked_ts, 0)) >= :recheck
         AND COALESCE(j.cancel_attempts, 0) < :maxAttempts
       ORDER BY j.last_checked_ts ASC
       LIMIT :lim
    ";
    $sel = $pdo->prepare($sql);
    $sel->bindValue(':server', $profileName, PDO::PARAM_STR);
    $sel->bindValue(':offline_secs', $OFFLINE_CLEANUP_SECS, PDO::PARAM_INT);
    $sel->bindValue(':recheck', $RECHECK_SECS, PDO::PARAM_INT);
    $sel->bindValue(':maxAttempts', $MAX_ATTEMPTS, PDO::PARAM_INT);
    $sel->bindValue(':lim', $BATCH_LIMIT, PDO::PARAM_INT);
    $sel->execute();

    $count = 0;
    while ($r = $sel->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        $jobId   = (string)$r['job_id'];
        $user    = (string)$r['username'];
        $device  = (string)$r['device'];
        $started = (int)$r['started_at'];
        $server  = (string)$r['server_id'];

        try {
            $props = api($base, '/admin/get-job-properties', $auth, [
                'TargetUser' => $user,
                'JobID'      => $jobId,
            ]);

            $endTime = (int)($props['EndTime'] ?? 0);
            $statusN = isset($props['Status']) ? (int)$props['Status'] : null;
            $label   = mapStatusLabel($statusN);
            if ($endTime > 0 && $label === 'running') {
                $label = 'finished';
            }

            if ($endTime > 0 || $label !== 'running') {
                $bytes   = (int)($props['UploadSize'] ?? 0);
                if ($bytes <= 0) { $bytes = getJobLogBytes($base, $auth, $user, $jobId); }
                $endedAt = $endTime > 0 ? $endTime : time();
                $dur     = max(0, $endedAt - $started);
                $labelDb = normalizeStatusForDb($label);

                if ($DRY_RUN) {
                    echo json_encode([
                        'profile' => $profileName,
                        'job_id' => $jobId,
                        'would_action' => 'offline_finalize',
                        'status' => $labelDb,
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
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
                        'comet_status' => $statusN,
                        'properties'   => $props,
                        'properties'   => $props,
                    ]);
                    logLine("[{$profileName}] offline finalize job={$jobId} status={$labelDb}");
                }
                continue;
            }

            if (hasFreshHeartbeat($props, $STALE_SECS)) {
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile' => $profileName,
                        'job_id' => $jobId,
                        'would_action' => 'offline_skip_fresh_heartbeat',
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                } else {
                    markChecked($pdo, $server, $jobId, $RECHECK_SECS);
                }
                continue;
            }

            $attempts = (int)($r['cancel_attempts'] ?? 0);
            if ($attempts + 1 < 2) {
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile' => $profileName,
                        'job_id' => $jobId,
                        'would_action' => 'offline_strike',
                        'strike' => $attempts + 1,
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                } else {
                    bumpAttempt($pdo, $server, $jobId);
                }
                continue;
            }

            $canId = (string)($props['CancellationID'] ?? '');
            $didCancel = false;
            if ($canId !== '') {
                if (!$DRY_RUN) {
                    try {
                        apiMutation($base, '/admin/job/cancel', $auth, [
                            'TargetUser' => $user,
                            'JobID'      => $jobId,
                        ]);
                        $didCancel = true;
                        logLine("[{$profileName}] offline cancel sent job={$jobId}");
                    } catch (Throwable $e) {
                        logLine("[{$profileName}] offline cancel failed job={$jobId} err=" . $e->getMessage());
                    }
                } else {
                    echo json_encode([
                        'profile' => $profileName,
                        'job_id' => $jobId,
                        'would_action' => 'offline_cancel',
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                    continue;
                }
            }

            if (!$didCancel) {
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile' => $profileName,
                        'job_id' => $jobId,
                        'would_action' => 'offline_abandon',
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                    continue;
                }
                try {
                    apiMutation($base, '/admin/job/abandon', $auth, [
                        'TargetUser' => $user,
                        'JobID'      => $jobId,
                    ]);
                    logLine("[{$profileName}] offline abandon sent job={$jobId}");
                } catch (Throwable $e) {
                    bumpAttempt($pdo, $server, $jobId);
                    logLine("[{$profileName}] offline abandon failed job={$jobId} err=" . $e->getMessage());
                    continue;
                }
            }

            if (!$DRY_RUN) {
                $props2 = api($base, '/admin/get-job-properties', $auth, [
                    'TargetUser' => $user,
                    'JobID'      => $jobId,
                ]);
                $endTime2 = (int)($props2['EndTime'] ?? 0);
                $statusN2 = isset($props2['Status']) ? (int)$props2['Status'] : null;
                $label2   = mapStatusLabel($statusN2);
                if ($endTime2 > 0) {
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
                        'comet_status' => $statusN2,
                        'properties'   => $props2,
                        'properties'   => $props2,
                    ]);
                    logLine("[{$profileName}] offline finalize job={$jobId} status={$label2Db}");
                } else {
                    bumpAttempt($pdo, $server, $jobId);
                    logLine("[{$profileName}] offline still-running post-cancel/abandon job={$jobId}, attempts+1");
                }
            }
        } catch (Throwable $e) {
            if (!$DRY_RUN) {
                bumpAttempt($pdo, $server, $jobId);
            }
            logLine("[{$profileName}] offline error job={$jobId} " . $e->getMessage());
        }
    }

    if ($count > 0) {
        logLine("[{$profileName}] offline_cleanup scanned={$count}");
    }
    return $count;
}

function processProfile(
    PDO $pdo,
    string $profile,
    int $STALE_SECS,
    int $RECHECK_SECS,
    int $MAX_ATTEMPTS,
    int $BATCH_LIMIT,
    bool $DRY_RUN = false,
    int $OFFLINE_CLEANUP_SECS = 3600,
    string $RECONCILE_MODE = 'off',
    int $RECONCILE_ACTION_LIMIT = 25,
    int $RECONCILE_RETRY_COOLDOWN_SECS = 86400
): void {
    $profileName = $profile;
    $base = cometApiBase($profile);
    if (!$base) {
        logLine("[{$profileName}] SKIP: API base not configured/derivable");
        return;
    }

    // Audit writes profile health only; enforce also writes live-job state.
    if (!$DRY_RUN || $RECONCILE_MODE !== 'off') {
        ensureReconciliationSchema($pdo);
    }

    try {
        $auth = cometAdminAuth($profile);
        IncompleteJobReconciliation::validateAuth($auth);
    } catch (Throwable $e) {
        if ($RECONCILE_MODE !== 'off') {
            recordProfileDiscoveryFailure($pdo, $profileName, $e);
        }
        logLine("[{$profileName}] reconciliation profile failure: " .
            IncompleteJobReconciliation::sanitizeError($e->getMessage()));
        return;
    }
    if (($auth['Username'] ?? '') === '') {
        logLine("[{$profileName}] SKIP: Admin API Username missing");
        return;
    }

    if ($RECONCILE_MODE !== 'off') {
        try {
            $discoverySummary = discoverIncompleteJobs(
                $pdo,
                $profileName,
                $base,
                $auth,
                $RECONCILE_MODE,
                $STALE_SECS,
                $RECHECK_SECS,
                $MAX_ATTEMPTS,
                $RECONCILE_MODE === 'audit'
            );
        } catch (Throwable $e) {
            logLine("[{$profileName}] reconciliation discovery failed: " .
                IncompleteJobReconciliation::sanitizeError($e->getMessage()));
            return;
        }

        if ($RECONCILE_MODE === 'audit') {
            logLine("[{$profileName}] reconciliation audit complete; legacy mutations skipped");
            return;
        }

        $enforcementSummary = processReconciledLiveJobs(
            $pdo,
            $profileName,
            $base,
            $auth,
            $STALE_SECS,
            $RECHECK_SECS,
            $MAX_ATTEMPTS,
            $BATCH_LIMIT,
            $OFFLINE_CLEANUP_SECS,
            $RECONCILE_ACTION_LIMIT,
            $RECONCILE_RETRY_COOLDOWN_SECS
        );
        if ((int)($enforcementSummary['profile_failures'] ?? 0) === 0) {
            recordProfileDiscoverySuccess(
                $pdo,
                $profileName,
                (int)($discoverySummary['returned'] ?? 0)
            );
        }
        return;
    }

    // Bootstrap pass: initialize rows that have never been checked. Do not keep
    // reprocessing zero-byte rows only because last_bytes_ts remains zero.
    $bootSql = "
      SELECT server_id, job_id, username
        FROM eb_jobs_live
       WHERE server_id = :server
         AND COALESCE(last_checked_ts,0)=0
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
                $hbTs = progressHeartbeatTs($propsB);
                $alive = hasFreshHeartbeat($propsB, $STALE_SECS);
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile'=>$profileName,
                        'job_id'=>$bJob,
                        'username'=>$bUser,
                        'would_action'=>'bootstrap_touch_progress',
                        'upload_bytes'=>$bytes,
                        'heartbeat_ts'=>$hbTs,
                        'heartbeat_fresh'=>$alive,
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                } else {
                    touchProgress($pdo, $profileName, $bJob, $bytes, $hbTs > 0 ? $hbTs : null);
                    if ($alive) {
                        resetAttempts($pdo, $profileName, $bJob);
                    }
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
                    markChecked($pdo, $profileName, $bJob, $RECHECK_SECS);
                    resetAttempts($pdo, $profileName, $bJob, $RECHECK_SECS);
                }
            } else {
                if ($DRY_RUN) {
                    echo json_encode([
                        'profile'=>$profileName,
                        'job_id'=>$bJob,
                        'username'=>$bUser,
                        'would_action'=>'bootstrap_strike',
                        'strike'=>1
                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
                } else {
                    markBootstrapStrike($pdo, $profileName, $bJob);
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
         AND (UNIX_TIMESTAMP() - GREATEST(COALESCE(NULLIF(last_bytes_ts,0),0), started_at, last_update)) >= :idle
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
                        'comet_status' => $statusN,
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
                $hbTs = progressHeartbeatTs($props);
                $fresh = hasFreshHeartbeat($props, $STALE_SECS);
                if ($DRY_RUN) {
                    $printDry([
                        'would_action'   => 'touch_progress',
                        'upload_bytes'   => $upload,
                        'heartbeat_ts'   => $hbTs,
                        'heartbeat_fresh'=> $fresh,
                    ]);
                } else {
                    touchProgress($pdo, $server, $jobId, $upload, $hbTs > 0 ? $hbTs : null);
                    if ($fresh) {
                        resetAttempts($pdo, $server, $jobId);
                        logLine("[{$profileName}] progress job={$jobId} bytes={$upload}");
                    } else {
                        logLine("[{$profileName}] stale cumulative bytes job={$jobId} bytes={$upload} hb={$hbTs}");
                    }
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
                    markChecked($pdo, $server, $jobId, $RECHECK_SECS);
                    resetAttempts($pdo, $server, $jobId, $RECHECK_SECS);
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
                        apiMutation($base, '/admin/job/cancel', $auth, [
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
                        apiMutation($base, '/admin/job/abandon', $auth, [
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
                        'comet_status' => $statusN2,
                    ]);
                    logLine("[{$profileName}] finalize job={$jobId} status={$label2Db}");
                } else {
                    // Still running: if device seems offline (no heartbeat), fall back to abandon immediately
                    $alive2 = hasFreshHeartbeat($props2, $RECHECK_SECS);
                    if (!$alive2) {
                        try {
                            apiMutation($base, '/admin/job/abandon', $auth, [
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
                                    'comet_status' => $statusN3,
                                    'properties'   => $props3,
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

    processOfflineLiveJobs(
        $pdo,
        $profileName,
        $base,
        $auth,
        $OFFLINE_CLEANUP_SECS,
        $STALE_SECS,
        $RECHECK_SECS,
        $MAX_ATTEMPTS,
        $BATCH_LIMIT,
        $DRY_RUN
    );

    logLine("[{$profileName}] scanned={$count} done");
}


/** Add reconciliation state storage if missing (safe to call each run). */
function ensureReconciliationSchema(PDO $pdo): void {
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
    if (!isset($cols['stale_observations'])) $alters[] = "ADD COLUMN stale_observations TINYINT UNSIGNED NOT NULL DEFAULT 0";
    if (!isset($cols['action_stage'])) $alters[] = "ADD COLUMN action_stage VARCHAR(24) NOT NULL DEFAULT 'none'";
    if (!isset($cols['next_action_ts'])) $alters[] = "ADD COLUMN next_action_ts INT UNSIGNED NOT NULL DEFAULT 0";
    if (!isset($cols['last_action_error'])) $alters[] = "ADD COLUMN last_action_error VARCHAR(255) NULL";
    if ($alters) {
        $sql = "ALTER TABLE eb_jobs_live " . implode(", ", $alters);
        $pdo->exec($sql);
    }

    $indexes = [];
    $indexRows = $pdo->query("SHOW INDEX FROM eb_jobs_live");
    while ($r = $indexRows->fetch(PDO::FETCH_ASSOC)) {
        $indexes[strtolower((string)$r['Key_name'])] = true;
    }
    if (!isset($indexes['idx_jobs_live_reconcile'])) {
        $pdo->exec(
            "ALTER TABLE eb_jobs_live
             ADD INDEX idx_jobs_live_reconcile
               (server_id, job_type, next_action_ts, last_checked_ts)"
        );
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS eb_monitor_profile_state (
            profile VARCHAR(64) NOT NULL,
            last_success_ts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error_ts INT UNSIGNED NOT NULL DEFAULT 0,
            consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
            last_incomplete_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(255) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (profile)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
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

    $profileLock = acquireProfileMonitorLock($pdo, $profileName);
    if ($profileLock === null) {
        logLine("[{$profileName}] SKIP: another monitor process holds the profile lock");
        continue;
    }
    try {
        processProfile(
            $pdo,
            $profileName,
            $STALE_SECS,
            $RECHECK_SECS,
            $MAX_ATTEMPTS,
            $BATCH_LIMIT,
            isDryRun(),
            $OFFLINE_CLEANUP_SECS,
            $RECONCILE_MODE,
            $RECONCILE_ACTION_LIMIT,
            $RECONCILE_RETRY_COOLDOWN_SECS
        );
    } finally {
        releaseProfileMonitorLock($pdo, $profileLock);
    }
}

if (isset($cli['profile']) && !$anyMatched) {
    fwrite(STDERR, "[monitor] Profile filter '--profile={$cli['profile']}' matched no profiles. Exiting.\n");
}



