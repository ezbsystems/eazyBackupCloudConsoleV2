<?php
declare(strict_types=1);

/**
 * Lightweight watchdog for Comet websocket workers and eb_jobs_live health.
 *
 * Alerts (WHMCS admin email, same recipients as WS stall alerts) when:
 *  - eazybackup-comet-ws@<profile>.service is not active
 *  - eb_jobs_live row count exceeds fleet-wide or per-profile thresholds
 *
 * Env (optional):
 *   EB_WATCH_JOBS_LIVE_MAX=500          Fleet-wide eb_jobs_live row cap
 *   EB_WATCH_JOBS_LIVE_PROFILE_MAX=300  Per server_id row cap
 *   EB_WATCH_STALE_HOURS=24             Stale = no activity within this many hours
 *   EB_WATCH_COOLDOWN_MIN=360           Minutes between repeat alerts for same condition
 *   EB_WATCH_DRY_RUN=1                  Log only; do not send email
 *   COMET_PROFILES=obc,cometbackup      Explicit profile list (else auto-discover)
 */

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/whmcs_cli_context.php';
eb_apply_whmcs_cli_server_context();

if (!defined('WHMCS')) {
    $whmcsInit = dirname(__DIR__, 4) . '/init.php';
    if (is_file($whmcsInit)) {
        require_once $whmcsInit;
    }
}

$JOBS_LIVE_MAX = max(50, (int)(getenv('EB_WATCH_JOBS_LIVE_MAX') ?: 500));
$JOBS_LIVE_PROFILE_MAX = max(50, (int)(getenv('EB_WATCH_JOBS_LIVE_PROFILE_MAX') ?: 300));
$STALE_HOURS = max(1, (int)(getenv('EB_WATCH_STALE_HOURS') ?: 24));
$COOLDOWN_MIN = max(15, (int)(getenv('EB_WATCH_COOLDOWN_MIN') ?: 360));
$DRY_RUN = getenv('EB_WATCH_DRY_RUN') === '1' || in_array('--dry-run', $argv ?? [], true);

$pdo = db();
$profiles = discoverWsProfiles();
$staleSecs = $STALE_HOURS * 3600;
$liveStats = fetchJobsLiveStats($pdo, $staleSecs);

$issues = [];

foreach ($profiles as $profile) {
    $unit = wsUnitName($profile);
    $state = wsServiceActiveState($unit);
    if ($state !== 'active') {
        $issues[] = [
            'kind' => 'service-inactive',
            'profile' => $profile,
            'fingerprint' => sha1("service-inactive|{$profile}|{$state}"),
            'summary' => "Comet WS worker not running ({$unit}: {$state})",
            'detail' => "Expected systemd unit {$unit} to be active. Current state: {$state}.\n"
                . "Job completion events are not being processed; eb_jobs_live will grow until the worker is restarted.\n"
                . "Try: sudo systemctl reset-failed {$unit} && sudo systemctl start {$unit}",
        ];
    }
}

if ($liveStats['total'] > $JOBS_LIVE_MAX) {
    $issues[] = [
        'kind' => 'jobs-live-fleet',
        'profile' => 'fleet',
        'fingerprint' => sha1('jobs-live-fleet|' . $liveStats['total'] . '|' . $JOBS_LIVE_MAX),
        'summary' => "eb_jobs_live fleet total {$liveStats['total']} exceeds threshold {$JOBS_LIVE_MAX}",
        'detail' => formatJobsLiveDetail($liveStats, $staleSecs),
    ];
}

foreach ($liveStats['by_server'] as $serverId => $row) {
    $cnt = (int)($row['cnt'] ?? 0);
    if ($cnt <= $JOBS_LIVE_PROFILE_MAX) {
        continue;
    }
    $issues[] = [
        'kind' => 'jobs-live-profile',
        'profile' => (string)$serverId,
        'fingerprint' => sha1("jobs-live-profile|{$serverId}|{$cnt}|{$JOBS_LIVE_PROFILE_MAX}"),
        'summary' => "eb_jobs_live for '{$serverId}' has {$cnt} rows (threshold {$JOBS_LIVE_PROFILE_MAX})",
        'detail' => formatJobsLiveDetail($liveStats, $staleSecs, (string)$serverId),
    ];
}

$sent = 0;
$suppressed = 0;
foreach ($issues as $issue) {
    if (sendWatchdogAlert($pdo, $issue, $COOLDOWN_MIN, $DRY_RUN)) {
        $sent++;
    } else {
        $suppressed++;
    }
}

$status = $issues === [] ? 'ok' : 'alert';
logLine(sprintf(
    '[watchdog] status=%s profiles=%s jobs_live=%d issues=%d alerts_sent=%d suppressed=%d%s',
    $status,
    implode(',', $profiles),
    $liveStats['total'],
    count($issues),
    $sent,
    $suppressed,
    $DRY_RUN ? ' dry-run' : ''
));

exit(0);

/** @return list<string> */
function discoverWsProfiles(): array
{
    $names = [];

    $csv = trim(cfg('COMET_PROFILES', ''));
    if ($csv !== '') {
        foreach (array_filter(array_map('trim', explode(',', $csv))) as $p) {
            if ($p !== '') {
                $names[strtolower($p)] = true;
            }
        }
    }

    foreach ($_ENV as $k => $_v) {
        if (preg_match('/^COMET_([A-Za-z0-9_]+)_(URL|API_BASE)$/', (string)$k, $m)) {
            $names[strtolower($m[1])] = true;
        }
    }

    $out = @shell_exec('systemctl list-unit-files --type=service --no-pager --no-legend 2>/dev/null');
    if (is_string($out) && $out !== '') {
        foreach (explode("\n", $out) as $line) {
            if (preg_match('/^eazybackup-comet-ws@([^.]+)\.service\b/', trim($line), $m)) {
                $names[strtolower($m[1])] = true;
            }
        }
    }

    $profiles = array_keys($names);
    sort($profiles);

    $profiles = array_values(array_filter($profiles, 'wsProfileIsWatched'));

    if ($profiles === []) {
        foreach (['obc', 'cometbackup'] as $fallback) {
            if (wsProfileIsWatched($fallback)) {
                $profiles[] = $fallback;
            }
        }
    }

    return $profiles;
}

function wsProfileIsWatched(string $profile): bool
{
    $unit = wsUnitName($profile);
    $enabled = trim((string)@shell_exec('systemctl is-enabled ' . escapeshellarg($unit) . ' 2>&1'));
    if ($enabled === '' || $enabled === 'masked' || $enabled === 'disabled' || $enabled === 'not-found') {
        return false;
    }

    return true;
}

function wsUnitName(string $profile): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_.-]+/', '', $profile) ?: 'unknown';

    return 'eazybackup-comet-ws@' . $safe . '.service';
}

function wsServiceActiveState(string $unit): string
{
    $cmd = 'systemctl is-active ' . escapeshellarg($unit) . ' 2>/dev/null';
    $state = trim((string)@shell_exec($cmd));

    return $state !== '' ? $state : 'unknown';
}

/**
 * @return array{total: int, stale_total: int, by_server: array<string, array{cnt: int, stale_cnt: int, newest_activity: int}>}
 */
function fetchJobsLiveStats(PDO $pdo, int $staleSecs): array
{
    $now = time();
    $cutoff = $now - $staleSecs;

    $rows = $pdo->query(
        'SELECT server_id,
                COUNT(*) AS cnt,
                SUM(CASE WHEN GREATEST(started_at, last_update, COALESCE(last_bytes_ts, 0)) < '
        . (int)$cutoff . ' THEN 1 ELSE 0 END) AS stale_cnt,
                MAX(GREATEST(started_at, last_update, COALESCE(last_bytes_ts, 0))) AS newest_activity
           FROM eb_jobs_live
          GROUP BY server_id'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byServer = [];
    $total = 0;
    $staleTotal = 0;
    foreach ($rows as $row) {
        $sid = (string)($row['server_id'] ?? '');
        $cnt = (int)($row['cnt'] ?? 0);
        $stale = (int)($row['stale_cnt'] ?? 0);
        $byServer[$sid] = [
            'cnt' => $cnt,
            'stale_cnt' => $stale,
            'newest_activity' => (int)($row['newest_activity'] ?? 0),
        ];
        $total += $cnt;
        $staleTotal += $stale;
    }

    return [
        'total' => $total,
        'stale_total' => $staleTotal,
        'by_server' => $byServer,
    ];
}

/**
 * @param array{total: int, stale_total: int, by_server: array<string, array{cnt: int, stale_cnt: int, newest_activity: int}>} $stats
 */
function formatJobsLiveDetail(array $stats, int $staleSecs, ?string $onlyServer = null): string
{
    $lines = [
        'eb_jobs_live summary:',
        '  fleet total: ' . $stats['total'],
        '  stale (no activity in ' . (int)($staleSecs / 3600) . 'h): ' . $stats['stale_total'],
        '',
        'Per server_id:',
    ];

    foreach ($stats['by_server'] as $serverId => $row) {
        if ($onlyServer !== null && $serverId !== $onlyServer) {
            continue;
        }
        $newest = (int)($row['newest_activity'] ?? 0);
        $newestStr = $newest > 0 ? gmdate('Y-m-d H:i:s', $newest) . ' UTC' : 'n/a';
        $lines[] = sprintf(
            '  %s: %d rows (%d stale), newest activity %s',
            $serverId,
            (int)$row['cnt'],
            (int)$row['stale_cnt'],
            $newestStr
        );
    }

    $lines[] = '';
    $lines[] = 'Stale rows usually mean the Comet websocket worker stopped removing completed jobs.';
    $lines[] = 'Check: systemctl status eazybackup-comet-ws@<profile>.service';

    return implode("\n", $lines);
}

function addonSetting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare(
        "SELECT value FROM tbladdonmodules WHERE module = 'eazybackup' AND setting = ? LIMIT 1"
    );
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();

    return ($v === false || $v === null) ? $default : (string)$v;
}

function addonBool(PDO $pdo, string $key, bool $default = false): bool
{
    $v = strtolower(trim(addonSetting($pdo, $key, $default ? 'on' : '')));

    return in_array($v, ['1', 'on', 'yes', 'true'], true);
}

/** @return list<string> */
function watchdogRecipients(PDO $pdo): array
{
    $csv = trim(addonSetting($pdo, 'ws_alert_admin_email', ''));
    $candidates = [];
    if ($csv !== '') {
        $candidates = preg_split('/[\s,;]+/', $csv) ?: [];
    } else {
        try {
            $rows = $pdo->query(
                "SELECT email FROM tbladmins WHERE disabled = 0 AND email IS NOT NULL AND TRIM(email) <> ''"
            )->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $candidates = array_map('strval', $rows);
        } catch (Throwable $e) {
            $candidates = [];
        }
    }

    $valid = [];
    foreach ($candidates as $email) {
        $email = strtolower(trim((string)$email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $valid[$email] = true;
        }
    }

    return array_keys($valid);
}

function watchdogStateFile(string $kind, string $profile): string
{
    $safeKind = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $kind) ?: 'unknown';
    $safeProfile = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $profile) ?: 'unknown';

    return rtrim(sys_get_temp_dir(), '/') . "/eazybackup-ws-watchdog-{$safeKind}-{$safeProfile}.json";
}

function shouldSendWatchdogAlert(string $kind, string $profile, string $fingerprint, int $cooldownMin): bool
{
    $path = watchdogStateFile($kind, $profile);
    $cooldown = $cooldownMin * 60;
    $now = time();
    if (!is_file($path)) {
        return true;
    }
    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return true;
    }
    $prevFingerprint = (string)($data['fingerprint'] ?? '');
    $prevTs = (int)($data['ts'] ?? 0);
    if ($prevFingerprint !== $fingerprint) {
        return true;
    }

    return ($now - $prevTs) >= $cooldown;
}

function markWatchdogAlertSent(string $kind, string $profile, string $fingerprint): void
{
    $path = watchdogStateFile($kind, $profile);
    $payload = json_encode(['fingerprint' => $fingerprint, 'ts' => time()], JSON_UNESCAPED_SLASHES);
    if ($payload !== false) {
        @file_put_contents($path, $payload);
    }
}

/**
 * @param array{kind: string, profile: string, fingerprint: string, summary: string, detail: string} $issue
 */
function sendWatchdogAlert(PDO $pdo, array $issue, int $cooldownMin, bool $dryRun): bool
{
    if (!addonBool($pdo, 'ws_alert_enabled', true)) {
        logLine('[watchdog] alert suppressed: ws_alert_enabled is off');
        return false;
    }

    $kind = (string)$issue['kind'];
    $profile = (string)$issue['profile'];
    $fingerprint = (string)$issue['fingerprint'];

    if (!shouldSendWatchdogAlert($kind, $profile, $fingerprint, $cooldownMin)) {
        return false;
    }

    if ($dryRun) {
        logLine('[watchdog] dry-run would alert: ' . $issue['summary']);
        return false;
    }

    if (!function_exists('localAPI')) {
        logLine('[watchdog] alert suppressed: WHMCS localAPI unavailable');
        return false;
    }

    $recipients = watchdogRecipients($pdo);
    if ($recipients === []) {
        logLine('[watchdog] alert suppressed: no admin recipients');
        return false;
    }

    $host = gethostname() ?: php_uname('n');
    $subject = '[EazyBackup] Comet WS watchdog: ' . $issue['summary'];
    $body = "The eazyBackup Comet websocket watchdog detected a condition that can cause database slowdowns if left unresolved.\n\n"
        . "Host: {$host}\n"
        . "Profile: {$profile}\n"
        . "Condition: {$kind}\n"
        . "Time: " . gmdate('Y-m-d H:i:s') . " UTC\n\n"
        . $issue['detail'] . "\n";

    $sent = false;
    foreach ($recipients as $to) {
        $resp = @localAPI('SendAdminEmail', [
            'customsubject' => $subject,
            'custommessage' => $body,
            'to' => $to,
        ]);
        if (!is_array($resp) || ($resp['result'] ?? '') !== 'success') {
            $resp = @localAPI('SendEmail', [
                'customtype' => 'general',
                'customsubject' => $subject,
                'custommessage' => $body,
                'to' => $to,
            ]);
        }
        if (is_array($resp) && ($resp['result'] ?? '') === 'success') {
            $sent = true;
        }
    }

    if ($sent) {
        markWatchdogAlertSent($kind, $profile, $fingerprint);
        logLine('[watchdog] alert sent: ' . $issue['summary']);
    } else {
        logLine('[watchdog] alert send failed: ' . $issue['summary']);
    }

    return $sent;
}
