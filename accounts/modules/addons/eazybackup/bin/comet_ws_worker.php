<?php
declare(strict_types=1);


// Load ONLY the addon vendor autoloader and pin it to the front of SPL stack.
$loader = require __DIR__ . '/../vendor/autoload.php';
if ($loader instanceof \Composer\Autoload\ClassLoader) {
    // Make sure our loader remains first, even if others register later.
    $loader->unregister();
    $loader->register(true); // prepend = true
}

// Bootstrap WHMCS so localAPI/logActivity are available for notifications.
if (!defined('WHMCS')) {
    $whmcsInit = dirname(__DIR__, 4) . '/init.php';
    if (is_file($whmcsInit)) {
        require_once $whmcsInit;
        // WHMCS init pulls in its own Composer loader; ensure ours stays first.
        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            $loader->unregister();
            $loader->register(true);
        }
    } else {
        fwrite(STDERR, "[ws-worker] WHMCS init.php not found at {$whmcsInit}\n");
    }
}

// Runtime guard + forensic: make sure we really have league/uri v7 here.
if (!class_exists(\League\Uri\Http::class, true) || !\method_exists(\League\Uri\Http::class, 'new')) {
    $where = class_exists(\League\Uri\Http::class, false)
        ? (new \ReflectionClass(\League\Uri\Http::class))->getFileName()
        : '(not autoloaded)';
    fwrite(STDERR, "[ws-worker] Need league/uri v7+: Http::new() missing. Loaded from: {$where}\n");
    exit(1);
}

// Amp v3 helpers must be present.
if (!\function_exists('\Amp\delay')) {
    fwrite(STDERR, "[ws-worker] amphp/amp ^3 not loaded\n");
    exit(1);
}

/**
 * EazyBackup — Comet WebSocket Worker (Amp v3 + amphp/websocket v2)
 * One instance per server profile (eazybackup / obc).
 */

use Amp\Websocket\Client\WebsocketHandshake;
use function Amp\Websocket\Client\connect;

// Do NOT load WHMCS bootstrap or other vendor autoloaders to avoid autoloader conflicts.

function logLine(string $profile, string $msg): void {
    fwrite(STDOUT, '[' . $profile . '] ' . $msg . PHP_EOL);
}
define('EB_WS_DEBUG', getenv('EB_WS_DEBUG') === '1');
define('EB_DB_DEBUG', getenv('EB_DB_DEBUG') === '1');
// Mirror notification debug when worker is in debug
if (EB_WS_DEBUG && getenv('EB_NOTIFY_DEBUG') !== '1') {
    putenv('EB_NOTIFY_DEBUG=1');
    $_ENV['EB_NOTIFY_DEBUG'] = '1';
    $_SERVER['EB_NOTIFY_DEBUG'] = '1';
}

function pick(array $src, array $keys, $default = '') {
    foreach ($keys as $k) {
        if (array_key_exists($k, $src) && $src[$k] !== '' && $src[$k] !== null) return $src[$k];
    }
    return $default;
}



// Amp v3 helpers will be referenced via fully-qualified names (\\Amp\\async, \\Amp\\delay, \\Amp\\Future\\awaitAll)
// Websocket client (v2) used via fully-qualified names
use Comet\Server as CometServer;

/////////////////
// .env loader //
/////////////////
function loadEnv(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, " \t\n\r\0\x0B\"'");
        putenv("$k=$v"); $_ENV[$k] = $v; $_SERVER[$k] = $v;
    }
}
function cfg(string $key, string $default = ''): string {
    $v = getenv($key); return $v === false ? $default : $v;
}
function loadCometProfile(string $profile): array {
    $p = "COMET_{$profile}_";
    $url    = cfg($p.'URL');
    $allow  = cfg($p.'ALLOWLIST', '');
    $origin = cfg($p.'ORIGIN', 'https://eazybackup.local');
    if ($url === '') throw new RuntimeException("Missing {$p}URL in .env");
    if ($allow) $url .= (str_contains($url,'?') ? '&' : '?') . 'allowList=' . $allow;
    return [
        'server_id' => $profile,
        'url'       => $url,                // MUST be ws:// or wss://
        'origin'    => $origin,
        'username'  => cfg($p.'USERNAME', ''),
        'authtype'  => cfg($p.'AUTHTYPE', 'Password'),
        'password'  => cfg($p.'PASSWORD', ''),
        'sessionkey'=> cfg($p.'SESSIONKEY', ''),
        'totp'      => cfg($p.'TOTP', ''),
    ];
}

//////////
// DB  //
//////////
function db(): PDO {
    $dsn  = cfg('DB_DSN',  'mysql:host=127.0.0.1;dbname=whmcs;charset=utf8mb4');
    $user = cfg('DB_USER', 'whmcs'); $pass = cfg('DB_PASS', '');
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

/////////////////////////
// Persistence helpers //
/////////////////////////
function getClientIdForUsername(PDO $pdo, string $username, ?string $profile = null): ?int {
    // Legacy helper, now delegates to resolveWhmcsUser (kept for compatibility)
    $res = resolveWhmcsUser($pdo, $username, $profile);
    return $res['client_id'];
}

function resolveWhmcsUser(PDO $pdo, string $username, ?string $profile = null): array {
    // Return ['client_id'=>?int, 'username'=>?string] with strong disambiguation.
    // Prefer exact-case; if case-insensitive yields multiple client_ids, treat as ambiguous.
    $result = ['client_id' => null, 'username' => null];
    try {
        // Optional scoping by server group (profile → tblservergroups.name)
        $joinGroup = '';
        $andGroup = '';
        if ($profile !== null && $profile !== '') {
            $joinGroup = " JOIN tblproducts p ON p.id = h.packageid JOIN tblservergroups g ON g.id = p.servergroup ";
            $andGroup = " AND g.name = ? ";
        }
        // 0) Prefer existing mapping in comet_users (exact-case, then case-insensitive if unique)
        $stmt = $pdo->prepare("SELECT client_id, username FROM comet_users WHERE BINARY username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['client_id'] > 0) {
            return ['client_id' => (int)$row['client_id'], 'username' => (string)$row['username']];
        }
        $stmt = $pdo->prepare("SELECT DISTINCT client_id, username FROM comet_users WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $distinctClients = array_values(array_unique(array_map(fn($r)=> (int)$r['client_id'], $rows)));
        if (count($distinctClients) === 1 && $distinctClients[0] > 0) {
            // Use whichever username casing is stored
            return ['client_id' => $distinctClients[0], 'username' => (string)($rows[0]['username'] ?? $username)];
        } elseif (count($distinctClients) > 1) {
            // Ambiguous mapping in comet_users; continue to tblhosting resolution instead of bailing out
            if (EB_WS_DEBUG) logLine('resolver', "Ambiguous in comet_users for username={$username}; falling back to tblhosting search");
        }

        // 1) Exact case-sensitive match on Active services (prefer trimmed)
        $sql1 = "SELECT h.userid, h.username FROM tblhosting h" . $joinGroup . " WHERE BINARY TRIM(h.username) = TRIM(?) AND h.domainstatus = 'Active'" . $andGroup . " ORDER BY h.id ASC LIMIT 1";
        $stmt = $pdo->prepare($sql1);
        $params = [$username]; if ($andGroup) { $params[] = $profile; }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { return ['client_id' => (int)$row['userid'], 'username' => (string)$row['username']]; }

        // 2) Exact case-sensitive match on any status
        $sql2 = "SELECT h.userid, h.username FROM tblhosting h" . $joinGroup . " WHERE BINARY TRIM(h.username) = TRIM(?)" . $andGroup . " ORDER BY h.id ASC LIMIT 1";
        $stmt = $pdo->prepare($sql2);
        $params = [$username]; if ($andGroup) { $params[] = $profile; }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { return ['client_id' => (int)$row['userid'], 'username' => (string)$row['username']]; }

        // 3) Case-insensitive match; prefer exact-case among matches; otherwise unique client
        $sql3 = "SELECT h.userid, h.username FROM tblhosting h" . $joinGroup . " WHERE LOWER(h.username) = LOWER(?)" . $andGroup . " ORDER BY (BINARY h.username = ?) DESC, h.id ASC";
        $stmt = $pdo->prepare($sql3);
        $params = [$username]; if ($andGroup) { $params[] = $profile; } $params[] = $username;
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!empty($rows)) {
            $first = $rows[0];
            // Check if only one client_id among all rows
            $distinctClients = array_values(array_unique(array_map(fn($r)=> (int)$r['userid'], $rows)));
            if (count($distinctClients) === 1) {
                return ['client_id' => (int)$first['userid'], 'username' => (string)$first['username']];
            }
            // If multiple clients, but the first row is exact-case match, select it
            if (isset($first['username']) && $first['username'] === $username) {
                return ['client_id' => (int)$first['userid'], 'username' => (string)$first['username']];
            }
        if (EB_WS_DEBUG) { logLine('resolver', "Ambiguous in tblhosting for username={$username} clients=" . json_encode($distinctClients) . ($profile?" profile={$profile}":"")); }
        }
    } catch (Throwable $e) { /* ignore */ }
    // Fallback: retry without server-group scoping if profile scoping yielded nothing
    try {
        // 1) Exact case-sensitive match on Active services
        $sql1 = "SELECT h.userid, h.username FROM tblhosting h WHERE BINARY TRIM(h.username) = TRIM(?) AND h.domainstatus = 'Active' ORDER BY h.id ASC LIMIT 1";
        $stmt = $pdo->prepare($sql1);
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { return ['client_id' => (int)$row['userid'], 'username' => (string)$row['username']]; }

        // 2) Exact case-sensitive match on any status
        $sql2 = "SELECT h.userid, h.username FROM tblhosting h WHERE BINARY TRIM(h.username) = TRIM(?) ORDER BY h.id ASC LIMIT 1";
        $stmt = $pdo->prepare($sql2);
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { return ['client_id' => (int)$row['userid'], 'username' => (string)$row['username']]; }

        // 3) Case-insensitive match; prefer exact-case among matches; otherwise unique client
        $sql3 = "SELECT h.userid, h.username FROM tblhosting h WHERE LOWER(h.username) = LOWER(?) ORDER BY (BINARY h.username = ?) DESC, h.id ASC";
        $stmt = $pdo->prepare($sql3);
        $stmt->execute([$username, $username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!empty($rows)) {
            $first = $rows[0];
            $distinctClients = array_values(array_unique(array_map(fn($r)=> (int)$r['userid'], $rows)));
            if (count($distinctClients) === 1) {
                return ['client_id' => (int)$first['userid'], 'username' => (string)$first['username']];
            }
            if (isset($first['username']) && $first['username'] === $username) {
                return ['client_id' => (int)$first['userid'], 'username' => (string)$first['username']];
            }
            if (EB_WS_DEBUG) { logLine('resolver', "Ambiguous in tblhosting (no profile scope) for username={$username} clients=" . json_encode($distinctClients)); }
        }
    } catch (Throwable $e) { /* ignore */ }

    return $result;
}

function getUsernameForDeviceHash(PDO $pdo, string $deviceHash): ?string {
    try {
        $stmt = $pdo->prepare("SELECT username FROM comet_devices WHERE hash = ? LIMIT 1");
        $stmt->execute([$deviceHash]);
        $u = $stmt->fetchColumn();
        if ($u !== false && $u !== null && $u !== '') { return (string)$u; }
    } catch (Throwable $e) { /* ignore */ }
    return null;
}

function upsertCometDevice(PDO $pdo, string $profile, string $username, string $hash, array $data): void {
    $resolved = $username !== '' ? resolveWhmcsUser($pdo, $username, $profile) : ['client_id'=>null, 'username'=>null];
    // If ambiguous / not found, use 0 as sentinel to satisfy NOT NULL schema
    $clientId = $resolved['client_id'] ?? 0;
    $usernameCanonical = $resolved['username'] ?? ($username !== '' ? $username : null);
    $friendly = '';
    $os = '';
    $arch = '';
    if (isset($data['FriendlyName']) && is_string($data['FriendlyName'])) { $friendly = $data['FriendlyName']; }
    if (isset($data['PlatformVersion']) && is_array($data['PlatformVersion'])) {
        $os = (string)($data['PlatformVersion']['os'] ?? '');
        $arch = (string)($data['PlatformVersion']['arch'] ?? '');
    }
    $contentJson = json_encode($data, JSON_UNESCAPED_SLASHES);
    try {
        $sql = "INSERT INTO comet_devices (id, client_id, username, hash, content, name, platform_os, platform_arch, is_active, created_at, updated_at, revoked_at)
                VALUES (:id, :client_id, :username, :hash, :content, :name, :os, :arch, 1, NOW(), NOW(), NULL)
                ON DUPLICATE KEY UPDATE
                  client_id=VALUES(client_id),
                  username=VALUES(username),
                  content=VALUES(content),
                  name=VALUES(name),
                  platform_os=VALUES(platform_os),
                  platform_arch=VALUES(platform_arch),
                  is_active=1,
                  updated_at=NOW(),
                  revoked_at=NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $hash,
            ':client_id' => $clientId,
            ':username' => $usernameCanonical,
            ':hash' => $hash,
            ':content' => $contentJson,
            ':name' => $friendly !== '' ? $friendly : null,
            ':os' => $os,
            ':arch' => $arch,
        ]);
        if (EB_DB_DEBUG) logLine($profile, "DB upsert DEVICE ok hash={$hash} rc=" . $stmt->rowCount());
    } catch (Throwable $e) {
        logLine($profile, "DB upsert DEVICE ERROR: " . $e->getMessage());
    }
}

function revokeCometDevice(PDO $pdo, string $profile, string $username, string $hash): void {
    try {
        $resolved = $username !== '' ? resolveWhmcsUser($pdo, $username, $profile) : ['client_id'=>null, 'username'=>null];
        $usernameCanonical = $resolved['username'] ?? ($username !== '' ? $username : null);
        $stmt = $pdo->prepare("UPDATE comet_devices SET is_active=0, updated_at=NOW(), revoked_at=NOW(), username=COALESCE(username, :username) WHERE hash=:hash");
        $stmt->execute([':hash' => $hash, ':username' => $usernameCanonical]);
        if (EB_DB_DEBUG) logLine($profile, "DB revoke DEVICE ok hash={$hash} rc=" . $stmt->rowCount());
    } catch (Throwable $e) {
        logLine($profile, "DB revoke DEVICE ERROR: " . $e->getMessage());
    }
}

function upsertCometJobStart(PDO $pdo, string $profile, array $data): void {
    try {
        $jobId   = (string)($data['GUID'] ?? ''); if ($jobId==='') return;
        $user    = (string)($data['Username'] ?? '');
        $clientId = $user !== '' ? (getClientIdForUsername($pdo, $user, $profile) ?? 0) : 0;
        if ($clientId <= 0) return;
        $devId  = (string)($data['DeviceID'] ?? '');
        $vault  = (string)($data['DestinationGUID'] ?? '');
        $itemId = (string)($data['SourceGUID'] ?? '');
        $type   = (int)($data['Classification'] ?? 0);
        $status = (int)($data['Status'] ?? 0);
        $snapId = isset($data['SnapshotID']) ? (string)$data['SnapshotID'] : null;
        $cancel = (string)($data['CancellationID'] ?? '');
        $total  = (int)($data['TotalSize'] ?? 0);
        $files  = (int)($data['TotalFiles'] ?? 0);
        $dirs   = (int)($data['TotalDirectories'] ?? 0);
        $up     = (int)($data['UploadSize'] ?? 0);
        $down   = (int)($data['DownloadSize'] ?? 0);
        $msAc   = (int)($data['TotalAccountsCount'] ?? 0);
        $start  = (int)($data['StartTime'] ?? time());
        $end    = (int)($data['EndTime'] ?? 0);
        $startedAt = date('Y-m-d H:i:s', $start);
        $endedAt   = $end > 0 ? date('Y-m-d H:i:s', $end) : null;
        $lastAt    = $endedAt ?: $startedAt;
        $cometDeviceHash = $devId !== '' ? hash('sha256', (string)$clientId . $devId) : '';
        $content = json_encode($data, JSON_UNESCAPED_SLASHES);
        $sql = "INSERT INTO comet_jobs (id, content, client_id, username, comet_vault_id, comet_device_id, comet_item_id, type, status, comet_snapshot_id, comet_cancellation_id, total_bytes, total_files, total_directories, upload_bytes, download_bytes, total_ms_accounts, started_at, ended_at, last_status_at)
                VALUES (:id,:content,:client_id,:username,:vault,:devhash,:item,:type,:status,:snap,:cancel,:total,:files,:dirs,:up,:down,:ms,:started,:ended,:last)
                ON DUPLICATE KEY UPDATE
                  content=VALUES(content), client_id=VALUES(client_id), username=VALUES(username), comet_vault_id=VALUES(comet_vault_id), comet_device_id=VALUES(comet_device_id), comet_item_id=VALUES(comet_item_id),
                  type=VALUES(type), status=VALUES(status), comet_snapshot_id=VALUES(comet_snapshot_id), comet_cancellation_id=VALUES(comet_cancellation_id),
                  total_bytes=VALUES(total_bytes), total_files=VALUES(total_files), total_directories=VALUES(total_directories), upload_bytes=VALUES(upload_bytes), download_bytes=VALUES(download_bytes),
                  total_ms_accounts=VALUES(total_ms_accounts), started_at=VALUES(started_at), ended_at=VALUES(ended_at), last_status_at=VALUES(last_status_at)";
        $stmt=$pdo->prepare($sql);
        $stmt->execute([
            ':id'=>$jobId, ':content'=>$content, ':client_id'=>$clientId, ':username'=>$user, ':vault'=>$vault, ':devhash'=>$cometDeviceHash, ':item'=>$itemId,
            ':type'=>$type, ':status'=>$status, ':snap'=>$snapId, ':cancel'=>$cancel, ':total'=>$total, ':files'=>$files, ':dirs'=>$dirs, ':up'=>$up, ':down'=>$down, ':ms'=>$msAc,
            ':started'=>$startedAt, ':ended'=>$endedAt, ':last'=>$lastAt,
        ]);
        if (EB_DB_DEBUG) logLine($profile, "DB upsert JOB NEW ok id={$jobId} rc=" . $stmt->rowCount());
    } catch (Throwable $e) { logLine($profile, "DB upsert JOB NEW ERROR: " . $e->getMessage()); }
}

function upsertCometJobComplete(PDO $pdo, string $profile, array $data): void {
    try {
        $jobId = (string)($data['GUID'] ?? ''); if ($jobId==='') return;
        $user  = (string)($data['Username'] ?? '');
        $clientId = $user !== '' ? (getClientIdForUsername($pdo, $user, $profile) ?? 0) : 0;
        if ($clientId <= 0) return;
        $devId  = (string)($data['DeviceID'] ?? '');
        $vault  = (string)($data['DestinationGUID'] ?? '');
        $itemId = (string)($data['SourceGUID'] ?? '');
        $type   = (int)($data['Classification'] ?? 0);
        $status = (int)($data['Status'] ?? 0);
        $snapId = isset($data['SnapshotID']) ? (string)$data['SnapshotID'] : null;
        $cancel = (string)($data['CancellationID'] ?? '');
        $total  = (int)($data['TotalSize'] ?? 0);
        $files  = (int)($data['TotalFiles'] ?? 0);
        $dirs   = (int)($data['TotalDirectories'] ?? 0);
        $up     = (int)($data['UploadSize'] ?? 0);
        $down   = (int)($data['DownloadSize'] ?? 0);
        $msAc   = (int)($data['TotalAccountsCount'] ?? 0);
        $start  = (int)($data['StartTime'] ?? time());
        $end    = (int)($data['EndTime'] ?? time());
        $startedAt = date('Y-m-d H:i:s', $start);
        $endedAt   = $end > 0 ? date('Y-m-d H:i:s', $end) : null;
        $lastAt    = $endedAt ?: $startedAt;
        $cometDeviceHash = $devId !== '' ? hash('sha256', (string)$clientId . $devId) : '';
        $content = json_encode($data, JSON_UNESCAPED_SLASHES);
        $sql = "INSERT INTO comet_jobs (id, content, client_id, username, comet_vault_id, comet_device_id, comet_item_id, type, status, comet_snapshot_id, comet_cancellation_id, total_bytes, total_files, total_directories, upload_bytes, download_bytes, total_ms_accounts, started_at, ended_at, last_status_at)
                VALUES (:id,:content,:client_id,:username,:vault,:devhash,:item,:type,:status,:snap,:cancel,:total,:files,:dirs,:up,:down,:ms,:started,:ended,:last)
                ON DUPLICATE KEY UPDATE
                  content=VALUES(content), client_id=VALUES(client_id), username=VALUES(username), comet_vault_id=VALUES(comet_vault_id), comet_device_id=VALUES(comet_device_id), comet_item_id=VALUES(comet_item_id),
                  type=VALUES(type), status=VALUES(status), comet_snapshot_id=VALUES(comet_snapshot_id), comet_cancellation_id=VALUES(comet_cancellation_id),
                  total_bytes=VALUES(total_bytes), total_files=VALUES(total_files), total_directories=VALUES(total_directories), upload_bytes=VALUES(upload_bytes), download_bytes=VALUES(download_bytes),
                  total_ms_accounts=VALUES(total_ms_accounts), started_at=VALUES(started_at), ended_at=VALUES(ended_at), last_status_at=VALUES(last_status_at)";
        $stmt=$pdo->prepare($sql);
        $stmt->execute([
            ':id'=>$jobId, ':content'=>$content, ':client_id'=>$clientId, ':username'=>$user, ':vault'=>$vault, ':devhash'=>$cometDeviceHash, ':item'=>$itemId,
            ':type'=>$type, ':status'=>$status, ':snap'=>$snapId, ':cancel'=>$cancel, ':total'=>$total, ':files'=>$files, ':dirs'=>$dirs, ':up'=>$up, ':down'=>$down, ':ms'=>$msAc,
            ':started'=>$startedAt, ':ended'=>$endedAt, ':last'=>$lastAt,
        ]);
        if (EB_DB_DEBUG) logLine($profile, "DB upsert JOB END ok id={$jobId} rc=" . $stmt->rowCount());
    } catch (Throwable $e) { logLine($profile, "DB upsert JOB END ERROR: " . $e->getMessage()); }
}

/////////////////////////
// Comet Admin helpers  //
/////////////////////////
function whmcsDecryptPassword(string $encrypted): ?string {
    try {
        if (function_exists('localAPI')) {
            $resp = localAPI('DecryptPassword', ['password2' => $encrypted]);
            if (is_array($resp) && isset($resp['password'])) { return (string)$resp['password']; }
        }
    } catch (Throwable $e) { /* ignore */ }
    return null;
}

function cometAdminClientForProfile(PDO $pdo, string $profile): ?CometServer {
    // 1) Try WHMCS server module mapping via server group name == profile
    try {
        $stmt = $pdo->prepare("SELECT s.hostname, s.secure, s.port, s.username, s.password\nFROM tblservergroups g\nJOIN tblservergroupsrel r ON r.groupid = g.id\nJOIN tblservers s ON s.id = r.serverid\nWHERE g.name = ?\nORDER BY s.id ASC\nLIMIT 1");
        $stmt->execute([$profile]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $host = (string)($row['hostname'] ?? '');
            $secureRaw = strtolower((string)($row['secure'] ?? ''));
            $secure = ($secureRaw === '1' || $secureRaw === 'on' || $secureRaw === 'true');
            $port = trim((string)($row['port'] ?? ''));
            $user = (string)($row['username'] ?? '');
            $enc  = (string)($row['password'] ?? '');
            $pass = whmcsDecryptPassword($enc) ?: '';
            // Normalize host
            $hostname = preg_replace(["/^http:\/\//i", "/^https:\/\//i", "/\/$/"], "", $host);
            $scheme = $secure ? 'https' : 'http';
            $base = $scheme . '://' . $hostname . ($port !== '' ? (':' . $port) : '') . '/';
            if (EB_WS_DEBUG) { logLine($profile, "AdminClient(profile) base={$base} user={$user} secureRaw={$secureRaw}"); }
            if ($base !== '' && $user !== '' && $pass !== '') {
                return new CometServer($base, $user, $pass);
            }
        }
    } catch (Throwable $e) { /* ignore and fallback */ }

    // 2) Fallback to .env keys if WHMCS mapping not available
    try {
        $p = "COMET_{$profile}_";
        $base = cfg($p . 'ORIGIN', '');
        $user = cfg($p . 'USERNAME', '');
        $pass = cfg($p . 'PASSWORD', '');
        if ($base === '' || $user === '' || $pass === '') { return null; }
        // Normalize base URL
        $base = rtrim($base, '/') . '/';
        if (EB_WS_DEBUG) { logLine($profile, "AdminClient(.env) base={$base} user={$user}"); }
        return new CometServer($base, $user, $pass);
    } catch (Throwable $e) {
        return null;
    }
}

function cometAdminClientForUsername(PDO $pdo, string $profile, string $username): ?CometServer {
    try {
        $stmt = $pdo->prepare("SELECT s.hostname, s.secure, s.port, s.username, s.password\nFROM tblhosting h\nJOIN tblproducts p ON p.id = h.packageid\nJOIN tblservergroups g ON g.id = p.servergroup\nJOIN tblservergroupsrel r ON r.groupid = g.id\nJOIN tblservers s ON s.id = r.serverid\nWHERE h.username = ?\nORDER BY s.id ASC\nLIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $host = (string)($row['hostname'] ?? '');
            $secureRaw = strtolower((string)($row['secure'] ?? ''));
            $secure = ($secureRaw === '1' || $secureRaw === 'on' || $secureRaw === 'true');
            $port = trim((string)($row['port'] ?? ''));
            $user = (string)($row['username'] ?? '');
            $enc  = (string)($row['password'] ?? '');
            $pass = whmcsDecryptPassword($enc) ?: '';
            $hostname = preg_replace(["/^http:\/\//i", "/^https:\/\//i", "/\/$/"], "", $host);
            $scheme = $secure ? 'https' : 'http';
            $base = $scheme . '://' . $hostname . ($port !== '' ? (':' . $port) : '') . '/';
            if (EB_WS_DEBUG) { logLine($profile, "AdminClient(username) base={$base} user={$user} secureRaw={$secureRaw}"); }
            if ($base !== '' && $user !== '' && $pass !== '') {
                return new CometServer($base, $user, $pass);
            }
        } else {
            if (EB_WS_DEBUG) { logLine($profile, "AdminClient(username) no server for {$username}"); }
        }
    } catch (Throwable $e) { if (EB_WS_DEBUG) { logLine($profile, "AdminClient(username) error: " . $e->getMessage()); } }
    return null;
}

function asArray($maybeObject): array {
    if (is_array($maybeObject)) return $maybeObject;
    if (is_object($maybeObject)) return get_object_vars($maybeObject);
    return [];
}

function syncUserProtectedItems(PDO $pdo, string $profile, string $username): void {
    if ($username === '') return;
    $resolved = resolveWhmcsUser($pdo, $username, $profile);
    $clientId = $resolved['client_id'];
    $usernameCanonical = $resolved['username'] ?? $username;
    if ($clientId === null) {
        if (EB_DB_DEBUG) logLine($profile, "Items: no client for username={$username}");
        return;
    }
    $client = cometAdminClientForProfile($pdo, $profile);
    if (!$client) { $client = cometAdminClientForUsername($pdo, $profile, $username); }
    if (!$client) { logLine($profile, "Items: no admin client for profile {$profile} (username={$username})"); return; }
    try {
        if (EB_WS_DEBUG) { logLine($profile, "Items: fetching profile for {$username}"); }
        $userProfile = $client->AdminGetUserProfile($username);
    } catch (Throwable $e) {
        logLine($profile, "Items: AdminGetUserProfile failed for {$username}: " . $e->getMessage());
        return;
    }

    $sources = isset($userProfile->Sources) ? asArray($userProfile->Sources) : [];
    if (EB_WS_DEBUG) { logLine($profile, "Items: sources count=" . count($sources) . " user={$username}"); }
    $seenIds = [];
    foreach ($sources as $itemId => $item) {
        // Ensure stdClass → array for JSON encoding consistency
        $itemArr = is_array($item) ? $item : (is_object($item) ? get_object_vars($item) : []);

        // Created / Updated times (normalize seconds/ms; avoid 1970 dates)
        $normalizeTs = function($v): ?int {
            if ($v === null || $v === '' || $v === 'Unknown') return null;
            if (is_numeric($v)) {
                $n = (int)$v;
                if ($n > 1000000000000) { $n = (int) floor($n / 1000); } // ms -> s
                return $n > 0 ? $n : null;
            }
            $ts = @strtotime((string)$v);
            return $ts !== false ? (int)$ts : null;
        };
        $ctTs = $normalizeTs($itemArr['CreateTime'] ?? null);
        $mtTs = $normalizeTs($itemArr['ModifyTime'] ?? null);
        if ($ctTs === null || $ctTs < 946684800) { $ctTs = $mtTs ?? time(); } // if invalid, use modify or now
        if ($mtTs === null || $mtTs < 946684800) { $mtTs = time(); }
        $createdAt = date('Y-m-d H:i:s', $ctTs);
        $updatedAt = date('Y-m-d H:i:s', $mtTs);

        $stats = isset($itemArr['Statistics']) && is_array($itemArr['Statistics']) ? $itemArr['Statistics'] : (isset($itemArr['Statistics']) && is_object($itemArr['Statistics']) ? get_object_vars($itemArr['Statistics']) : []);
        $last = isset($stats['LastBackupJob']) && is_array($stats['LastBackupJob']) ? $stats['LastBackupJob'] : (isset($stats['LastBackupJob']) && is_object($stats['LastBackupJob']) ? get_object_vars($stats['LastBackupJob']) : []);

        $ownerDevice = (string)($itemArr['OwnerDevice'] ?? '');
        $deviceHash = hash('sha256', (string)$clientId . $ownerDevice);

        $protectedItem = [
            'id' => (string)$itemId,
            'client_id' => $clientId,
            'username' => $username,
            'content' => json_encode($itemArr, JSON_UNESCAPED_SLASHES),
            'comet_device_id' => $deviceHash,
            'name' => (string)($itemArr['Description'] ?? ''),
            'type' => (string)($itemArr['Engine'] ?? ''),
            'total_bytes' => (int)($last['TotalSize'] ?? 0),
            'total_files' => (int)($last['TotalFiles'] ?? 0),
            'total_directories' => (int)($last['TotalDirectories'] ?? 0),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'owner_device' => $ownerDevice,
        ];

        try {
            $sql = "INSERT INTO comet_items (id, client_id, username, content, comet_device_id, owner_device, name, type, total_bytes, total_files, total_directories, created_at, updated_at)
                    VALUES (:id, :client_id, :username, :content, :comet_device_id, :owner_device, :name, :type, :total_bytes, :total_files, :total_directories, :created_at, :updated_at)
                    ON DUPLICATE KEY UPDATE
                      updated_at=IF(VALUES(updated_at) > updated_at,
                                     VALUES(updated_at),
                                     IF(VALUES(name) <> name OR VALUES(type) <> type OR VALUES(owner_device) <> owner_device OR
                                        VALUES(total_bytes) <> total_bytes OR VALUES(total_files) <> total_files OR VALUES(total_directories) <> total_directories OR
                                        VALUES(content) <> content,
                                        NOW(),
                                        updated_at)),
                      client_id=VALUES(client_id), username=VALUES(username), content=VALUES(content), comet_device_id=VALUES(comet_device_id), owner_device=VALUES(owner_device),
                      name=VALUES(name), type=VALUES(type), total_bytes=VALUES(total_bytes), total_files=VALUES(total_files),
                      total_directories=VALUES(total_directories)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $protectedItem['id'],
                ':client_id' => $protectedItem['client_id'],
                ':username' => $usernameCanonical,
                ':content' => $protectedItem['content'],
                ':comet_device_id' => $protectedItem['comet_device_id'],
                ':owner_device' => $protectedItem['owner_device'],
                ':name' => $protectedItem['name'],
                ':type' => $protectedItem['type'],
                ':total_bytes' => $protectedItem['total_bytes'],
                ':total_files' => $protectedItem['total_files'],
                ':total_directories' => $protectedItem['total_directories'],
                ':created_at' => $protectedItem['created_at'],
                ':updated_at' => $protectedItem['updated_at'],
            ]);
            if (EB_DB_DEBUG) { logLine($profile, "Items: upsert ok id={$protectedItem['id']} user={$username}"); }
        } catch (Throwable $e) {
            logLine($profile, "Items upsert failed for username={$username} id={$itemId}: " . $e->getMessage());
        }
        $seenIds[] = (string)$itemId;
    }

    // Prune removed items for this user
    if (!empty($seenIds)) {
        try {
            $in = implode(',', array_fill(0, count($seenIds), '?'));
            $params = array_merge([$clientId, $username], $seenIds);
            $stmt = $pdo->prepare("DELETE FROM comet_items WHERE client_id=? AND username=? AND id NOT IN ($in)");
            $stmt->execute($params);
            if (EB_DB_DEBUG) { logLine($profile, "Items: pruned rc=" . $stmt->rowCount() . " user={$username}"); }
        } catch (Throwable $e) {
            // ignore pruning errors
        }
    }
}

function syncUserVaults(PDO $pdo, string $profile, string $username): void {
    if ($username === '') return;

    // Look up WHMCS client (same as items sync)
    $resolved = resolveWhmcsUser($pdo, $username, $profile);
    $clientId = $resolved['client_id'];
    $usernameCanonical = $resolved['username'] ?? $username;
    if ($clientId === null) {
        if (EB_DB_DEBUG) logLine($profile, "Vaults: no client for username={$username}");
        return;
    }

    // Get an admin client
    $client = cometAdminClientForProfile($pdo, $profile);
    if (!$client) { $client = cometAdminClientForUsername($pdo, $profile, $username); }
    if (!$client) { logLine($profile, "Vaults: no admin client for profile {$profile} (username={$username})"); return; }

    // Fetch user profile
    try {
        if (EB_WS_DEBUG) logLine($profile, "Vaults: fetching profile for {$username}");
        $userProfile = $client->AdminGetUserProfile($username);
    } catch (Throwable $e) {
        logLine($profile, "Vaults: AdminGetUserProfile failed for {$username}: " . $e->getMessage());
        return;
    }

    // Destinations map: GUID => DestinationConfig
    $destinations = [];
    if (isset($userProfile->Destinations)) {
        $destinations = asArray($userProfile->Destinations);
    }
    $presentIds = [];

    foreach ($destinations as $guid => $cfg) {
        if (!is_string($guid) || $guid === '') continue;

        $arr   = is_array($cfg) ? $cfg : (is_object($cfg) ? get_object_vars($cfg) : []);
        $name  = (string)($arr['DisplayName'] ?? $arr['Description'] ?? '');
        $type  = (int)   ($arr['DestinationType'] ?? $arr['Type'] ?? 0);

        // Storage limit flags can be named differently across versions
        $hasLimit   = (int) (!empty($arr['LimitStorage']) || !empty($arr['StorageLimitEnabled']));
        $limitBytes = (int)   ($arr['StorageLimitBytes'] ?? $arr['LimitStorageBytes'] ?? 0);

        // Try to extract bucket details for reporting (Comet first, then S3-ish)
        $bucketServer = (string)($arr['CometServer'] ?? $arr['S3CustomHostName'] ?? $arr['S3Hostname'] ?? '');
        $bucketServer = $bucketServer !== '' ? rtrim($bucketServer, '/') . '/' : '';
        $bucketName   = (string)($arr['CometBucket'] ?? $arr['S3BucketName'] ?? $arr['Bucket'] ?? '');
        $bucketKey    = (string)($arr['CometBucketKey'] ?? $arr['S3AccessKey'] ?? $arr['Key'] ?? '');

        $contentJson = json_encode($arr, JSON_UNESCAPED_SLASHES);

        // Compute total_bytes from Statistics when available
        $bytesTotal = 0;
        if (isset($arr['Statistics'])) {
            $stats = is_array($arr['Statistics']) ? $arr['Statistics'] : (is_object($arr['Statistics']) ? get_object_vars($arr['Statistics']) : []);
            // Prefer ClientProvidedSize.Size
            if (isset($stats['ClientProvidedSize'])) {
                $bps = is_array($stats['ClientProvidedSize']) ? $stats['ClientProvidedSize'] : (is_object($stats['ClientProvidedSize']) ? get_object_vars($stats['ClientProvidedSize']) : []);
                $sz = (int)($bps['Size'] ?? 0);
                if ($sz > 0) { $bytesTotal = $sz; }
            }
            // Fallback: sum ClientProvidedContent.Components[*].Bytes
            if ($bytesTotal <= 0 && isset($stats['ClientProvidedContent'])) {
                $cpc = is_array($stats['ClientProvidedContent']) ? $stats['ClientProvidedContent'] : (is_object($stats['ClientProvidedContent']) ? get_object_vars($stats['ClientProvidedContent']) : []);
                $components = [];
                if (isset($cpc['Components'])) {
                    $components = is_array($cpc['Components']) ? $cpc['Components'] : (is_object($cpc['Components']) ? get_object_vars($cpc['Components']) : []);
                }
                if (!empty($components)) {
                    $sum = 0;
                    foreach ($components as $comp) {
                        if (is_array($comp)) { $sum += (int)($comp['Bytes'] ?? 0); }
                        elseif (is_object($comp)) { $compArr = get_object_vars($comp); $sum += (int)($compArr['Bytes'] ?? 0); }
                    }
                    if ($sum > 0) { $bytesTotal = $sum; }
                }
            }
        }

        // Upsert current vault
        try {
            $sql = "INSERT INTO comet_vaults
                      (id, client_id, username, content, name, type, total_bytes, bucket_server, bucket_name, bucket_key, has_storage_limit, storage_limit_bytes, created_at, updated_at, is_active, removed_at)
                    VALUES
                      (:id, :client_id, :username, :content, :name, :type, :total_bytes, :bucket_server, :bucket_name, :bucket_key, :has_limit, :limit_bytes, NOW(), NOW(), 1, NULL)
                    ON DUPLICATE KEY UPDATE
                      client_id=VALUES(client_id),
                      username=VALUES(username),
                      content=VALUES(content),
                      name=VALUES(name),
                      type=VALUES(type),
                      total_bytes=IF(VALUES(total_bytes) > 0, VALUES(total_bytes), total_bytes),
                      bucket_server=IF(VALUES(bucket_server) <> '', VALUES(bucket_server), bucket_server),
                      bucket_name=IF(VALUES(bucket_name) <> '', VALUES(bucket_name), bucket_name),
                      bucket_key=IF(VALUES(bucket_key) <> '', VALUES(bucket_key), bucket_key),
                      has_storage_limit=VALUES(has_storage_limit),
                      storage_limit_bytes=VALUES(storage_limit_bytes),
                      is_active=1,
                      removed_at=NULL,
                      updated_at=NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id'           => $guid,
                ':client_id'    => $clientId,
                ':username'     => $usernameCanonical,
                ':content'      => $contentJson,
                ':name'         => $name,
                ':type'         => $type,
                ':total_bytes'  => $bytesTotal,
                ':bucket_server'=> $bucketServer,
                ':bucket_name'  => $bucketName,
                ':bucket_key'   => $bucketKey,
                ':has_limit'    => $hasLimit,
                ':limit_bytes'  => $limitBytes,
            ]);
            if (EB_DB_DEBUG) logLine($profile, "Vaults: upsert ok guid={$guid} name=\"{$name}\" type={$type}");
        } catch (Throwable $e) {
            logLine($profile, "Vaults: upsert ERROR guid={$guid}: " . $e->getMessage());
        }

        $presentIds[] = $guid;
    }

    // Mark any missing vaults as inactive (soft-delete)
    try {
        if (count($presentIds) > 0) {
            $in = implode(',', array_fill(0, count($presentIds), '?'));
            $sql = "UPDATE comet_vaults
                    SET is_active = 0, removed_at = IFNULL(removed_at, NOW())
                    WHERE username = ? AND is_active = 1 AND id NOT IN ($in)";
            $params = array_merge([$username], $presentIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if (EB_DB_DEBUG) logLine($profile, "Vaults: pruned missing for {$username} rc=" . $stmt->rowCount());
        } else {
            // No destinations at all → mark any existing active vaults as removed
            $stmt = $pdo->prepare("UPDATE comet_vaults
                                   SET is_active = 0, removed_at = IFNULL(removed_at, NOW())
                                   WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            if (EB_DB_DEBUG) logLine($profile, "Vaults: none present, marked all inactive for {$username} rc=" . $stmt->rowCount());
        }
    } catch (Throwable $e) {
        logLine($profile, "Vaults: prune ERROR for {$username}: " . $e->getMessage());
    }
}

function syncUserDevices(PDO $pdo, string $profile, string $username): void {
    if ($username === '') return;

    $resolved = resolveWhmcsUser($pdo, $username, $profile);
    $clientId = $resolved['client_id'];
    if ($clientId === null) {
        if (EB_DB_DEBUG) logLine($profile, "Devices: no client for username={$username} (will upsert with NULL client_id)");
        // Proceed: allow inserts with NULL client_id; username will still be set
    }

    // Get an admin client for this profile/username
    $client = cometAdminClientForProfile($pdo, $profile);
    if (!$client) { $client = cometAdminClientForUsername($pdo, $profile, $username); }
    if (!$client) { logLine($profile, "Devices: no admin client for profile {$profile} (username={$username})"); return; }

    try {
        if (EB_WS_DEBUG) logLine($profile, "Devices: fetching profile for {$username}");
        $userProfile = $client->AdminGetUserProfile($resolved['username'] ?? $username);
    } catch (Throwable $e) {
        logLine($profile, "Devices: AdminGetUserProfile failed for {$username}: " . $e->getMessage());
        return;
    }

    // Devices map: DeviceID (hash) => DeviceConfig
    $devices = [];
    if (isset($userProfile->Devices)) {
        $devices = asArray($userProfile->Devices);
    }

    foreach ($devices as $deviceId => $cfg) {
        if (!is_string($deviceId) || $deviceId === '') continue;
        $arr = is_array($cfg) ? $cfg : (is_object($cfg) ? get_object_vars($cfg) : []);
        // Upsert with current FriendlyName / PlatformVersion
        upsertCometDevice($pdo, $profile, $username, (string)$deviceId, $arr);
    }
}

function upsertLive(PDO $pdo, array $row): void {
    try {
        $sql = "INSERT INTO eb_jobs_live
                (server_id, job_id, username, device, job_type, started_at, bytes_done, throughput_bps, last_update)
                VALUES (:server_id, :job_id, :username, :device, :job_type, :started_at, :bytes_done, :throughput_bps, :last_update)
                ON DUPLICATE KEY UPDATE
                  username=VALUES(username),
                  device=VALUES(device),
                  job_type=VALUES(job_type),
                  bytes_done=VALUES(bytes_done),
                  throughput_bps=VALUES(throughput_bps),
                  last_update=VALUES(last_update)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':server_id'      => $row['server_id'],
            ':job_id'         => $row['job_id'],
            ':username'       => $row['username'] ?? '',
            ':device'         => $row['device'] ?? '',
            ':job_type'       => $row['job_type'] ?? '',
            ':started_at'     => (int)($row['started_at'] ?? time()),
            ':bytes_done'     => (int)($row['bytes_done'] ?? 0),
            ':throughput_bps' => (int)($row['throughput_bps'] ?? 0),
            ':last_update'    => time(),
        ]);
        if (EB_DB_DEBUG) logLine($row['server_id'], "DB upsert LIVE ok job={$row['job_id']} rc=" . $stmt->rowCount());
    } catch (Throwable $e) {
        logLine($row['server_id'], "DB upsert LIVE ERROR: " . $e->getMessage());
    }
}

function finishJob(PDO $pdo, array $row): void {
    try {
        $del = $pdo->prepare("DELETE FROM eb_jobs_live WHERE server_id=? AND job_id=?");
        $del->execute([$row['server_id'], $row['job_id']]);

        $sql = "INSERT INTO eb_jobs_recent_24h
                (server_id, job_id, username, device, job_type, status, bytes, duration_sec, ended_at)
                VALUES (:server_id, :job_id, :username, :device, :job_type, :status, :bytes, :duration_sec, :ended_at)
                ON DUPLICATE KEY UPDATE
                  username=VALUES(username),
                  device=VALUES(device),
                  job_type=VALUES(job_type),
                  status=VALUES(status),
                  bytes=VALUES(bytes),
                  duration_sec=VALUES(duration_sec),
                  ended_at=VALUES(ended_at)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':server_id'    => $row['server_id'],
            ':job_id'       => $row['job_id'],
            ':username'     => $row['username'] ?? '',
            ':device'       => $row['device'] ?? '',
            ':job_type'     => $row['job_type'] ?? '',
            ':status'       => $row['status'] ?? 'success',
            ':bytes'        => (int)($row['bytes'] ?? 0),
            ':duration_sec' => (int)($row['duration_sec'] ?? 0),
            ':ended_at'     => (int)($row['ended_at'] ?? time()),
        ]);
        if (EB_DB_DEBUG) {
            logLine($row['server_id'], "DB upsert RECENT ok job={$row['job_id']} rc=" . $stmt->rowCount());
            $chk = $pdo->prepare("SELECT username,device,job_type,status,bytes,duration_sec,ended_at FROM eb_jobs_recent_24h WHERE server_id=? AND job_id=?");
            $chk->execute([$row['server_id'], $row['job_id']]);
            logLine($row['server_id'], "DB row: " . json_encode($chk->fetch() ?: []));
        }
    } catch (Throwable $e) {
        logLine($row['server_id'], "DB finish ERROR: " . $e->getMessage());
    }
}

function saveCursor(PDO $pdo, string $profile, int $ts, ?string $jobId): void {
    $pdo->prepare("REPLACE INTO eb_event_cursor (source, last_ts, last_id) VALUES (?, ?, ?)")
        ->execute(['comet-ws:' . $profile, $ts, $jobId]);
}

/////////////////////
// Event mapping   //
/////////////////////
function mapStatus($statusRaw): string {
    // Normalize to int if possible
    $code = is_numeric($statusRaw) ? (int)$statusRaw : null;
    if ($code !== null) {
        // Canonical mapping (JobStatus constants)
        return match ($code) {
            5000 => 'success',          // SUCCESS
            // 6001 ACTIVE, 6002 REVIVED are non-terminal; if seen here, treat conservatively as error
            6001 => 'error',
            6002 => 'error',
            7000 => 'error',            // TIMEOUT → error
            7001 => 'warning',          // WARNING
            7002 => 'error',            // ERROR
            7003 => 'error',            // QUOTA_EXCEEDED → error
            7004 => 'missed',           // MISSED
            7005 => 'error',            // CANCELLED → error
            7006 => 'error',            // ALREADY_RUNNING → error
            7007 => 'error',            // ABANDONED → error
            default => ($code >= 7000 ? 'error' : 'error'),
        };
    }

    // Fallback for rare string statuses
    if (is_string($statusRaw)) {
        $s = strtolower($statusRaw);
        if (str_contains($s, 'warn')) return 'warning';
        if (str_contains($s, 'miss')) return 'missed';
        if (str_contains($s, 'quota')) return 'error';
        if (str_contains($s, 'cancel')) return 'error';
        if (str_contains($s, 'abandon')) return 'error';
        if (str_contains($s, 'already_running')) return 'error';
        if ($s === 'success' || $s === 'ok' || $s === 'completed') return 'success';
    }

    return 'error';
}

function handleEvent(PDO $pdo, string $profile, array $evt): void {
    $ts   = (int)($evt['Timestamp'] ?? $evt['Time'] ?? time());
    $lab  = (string)($evt['TypeString'] ?? '');
    $data = $evt['Data'] ?? ($evt['Payload'] ?? []);
    $actor = (string)($evt['Actor'] ?? '');
    $resourceId = (string)($evt['ResourceID'] ?? '');

    // Ignore non-job noise; allow SEVT_ACCOUNT_UPDATED (used for item sync)
    if ($lab === '' || str_starts_with($lab, 'SEVT_META_') || $lab === 'SEVT_ACCOUNT_LOGIN') {
        if (EB_WS_DEBUG) logLine($profile, "IGNORED {$lab}");
        saveCursor($pdo, $profile, $ts, null);
        return;
    }

    // Pick fields from Data (your samples use these)
    $jobId      = (string) pick($data, ['GUID','JobGUID','JobGuid','JobID','ID','SnapshotID'], '');
    $username   = (string) pick($data, ['Username','Account','AccountName','User'], '');
    $deviceId   = (string) pick($data, ['DeviceID','Device','Hostname','Computer'], '');
    $classification = (string) pick($data, ['Classification','JobType','Type','Class'], ''); // numeric code (e.g., 4001)
    $startTime  = (int)    pick($data, ['StartTime','StartedAt','Start','Begin'], 0);
    $endTime    = (int)    pick($data, ['EndTime','EndedAt','End','Finish'], 0);
    $bytesDone  = (int)    pick($data, ['BytesDone','TransferredBytes','ProcessedBytes'], 0);
    $bps        = (int)    pick($data, ['BytesPerSecond','Throughput','Bps'], 0);
    $uploadSize = (int)    pick($data, ['UploadSize','Bytes','Transferred','DeltaBytes'], 0);
    $totalSize  = (int)    pick($data, ['TotalSize','SourceBytes','ScannedBytes'], 0);
    $statusRaw  =            $data['Status'] ?? ($data['Result'] ?? '');

    $ended_at   = $endTime ?: $ts;
    $duration   = ($startTime && $endTime && $endTime >= $startTime) ? ($endTime - $startTime) : (int) pick($data, ['Duration'], 0);
    $bytesFinal = $uploadSize ?: ($data['Bytes'] ?? 0);
    if (!$bytesFinal && $totalSize) $bytesFinal = $totalSize;

    if (EB_WS_DEBUG) {
        logLine($profile, 'EVT ' . json_encode([
            'TypeString'=>$lab, 'jobId'=>$jobId, 'Username'=>$username, 'DeviceID'=>$deviceId,
            'Class'=>$classification, 'UploadSize'=>$uploadSize, 'TotalSize'=>$totalSize,
            'Duration'=>$duration, 'Status'=>$statusRaw
        ]));
    }

    if ($lab === 'SEVT_DEVICE_NEW') {
        if ($resourceId !== '') {
            if (EB_WS_DEBUG) logLine($profile, "DEVICE NEW user={$username} actor={$actor} hash={$resourceId}");
            $payload = is_array($data) ? $data : [];
            // Prefer the target account's Username over Actor (Actor may be an admin)
            $who = $username !== '' ? $username : $actor;
            upsertCometDevice($pdo, $profile, $who, $resourceId, $payload);
            // Notify: device registered (delegated; ignore failures)
            try {
                @require_once __DIR__ . '/../lib/Notifications/bootstrap.php';
                if (function_exists('eb_notify_device_registered')) {
                    eb_notify_device_registered($pdo, $profile, $who, $resourceId, $payload);
                }
            } catch (Throwable $_) {}
        }
    } elseif ($lab === 'SEVT_DEVICE_REMOVED') {
        if ($resourceId !== '') {
            if (EB_WS_DEBUG) logLine($profile, "DEVICE REMOVED user={$username} actor={$actor} hash={$resourceId}");
            $who = $username !== '' ? $username : $actor;
            revokeCometDevice($pdo, $profile, $who, $resourceId);
        }
    } elseif ($lab === 'SEVT_DEVICE_UPDATED') {
        // Refresh devices for the owning user when device metadata changes (e.g., FriendlyName)
        if ($resourceId !== '') {
            $ownerUser = $username !== '' ? $username : (getUsernameForDeviceHash($pdo, $resourceId) ?? '');
            if ($ownerUser !== '') {
                if (EB_WS_DEBUG) logLine($profile, "DEVICE UPDATED hash={$resourceId} → refresh devices for user={$ownerUser}");
                syncUserDevices($pdo, $profile, $ownerUser);
            } else {
                if (EB_WS_DEBUG) logLine($profile, "DEVICE UPDATED hash={$resourceId} but no owner username found");
            }
        }
    } elseif ($lab === 'SEVT_ACCOUNT_UPDATED') {
        // Prefer the target Username from Data; fallback to Actor (may be an admin)
        $acctUser = $username !== '' ? $username : $actor;
        if ($acctUser !== '') {
            if (EB_WS_DEBUG) logLine($profile, "ACCOUNT UPDATED → sync devices/items/vaults for user={$acctUser}");
            // Refresh devices to capture FriendlyName and platform changes
            syncUserDevices($pdo, $profile, $acctUser);
            // Refresh protected items and vaults
            syncUserProtectedItems($pdo, $profile, $acctUser);
            syncUserVaults($pdo, $profile, $acctUser);
            // Notify: addons may have become enabled (evaluate)
            try {
                @require_once __DIR__ . '/../lib/Notifications/bootstrap.php';
                if (function_exists('eb_notify_account_updated')) {
                    eb_notify_account_updated($pdo, $profile, $acctUser);
                }
            } catch (Throwable $_) {}
        }
    } elseif ($lab === 'SEVT_JOB_NEW') {
        // Treat as job start
        if ($jobId !== '') {
            upsertLive($pdo, [
                'server_id'      => $profile,
                'job_id'         => $jobId,
                'username'       => $username,
                'device'         => $deviceId,          // ID for now; we can enrich later
                'job_type'       => (string)$classification,
                'started_at'     => $startTime ?: $ts,
                'bytes_done'     => $bytesDone,
                'throughput_bps' => $bps,
            ]);
            // Upsert into comet_jobs immediately (running state)
            upsertCometJobStart($pdo, $profile, $data);
            if (EB_WS_DEBUG) logLine($profile, "START NEW job={$jobId}");
        }
    } elseif ($lab === 'SEVT_JOB_COMPLETED' || $lab === 'SEVT_JOB_COMPLETE' || $lab === 'SEVT_JOB_FINISH' || $lab === 'SEVT_JOB_END'
        || $lab === 'SEVT_JOB_FAILED' || $lab === 'SEVT_JOB_CANCELLED' || $lab === 'SEVT_JOB_ABORTED') {
        if ($jobId !== '') {
            $status = mapStatus($statusRaw);
            finishJob($pdo, [
                'server_id'    => $profile,
                'job_id'       => $jobId,
                'username'     => $username,
                'device'       => $deviceId,
                'job_type'     => (string)$classification,
                'status'       => $status,
                'bytes'        => $bytesFinal,
                'duration_sec' => $duration,
                'ended_at'     => $ended_at,
            ]);
            // Upsert final into comet_jobs
            upsertCometJobComplete($pdo, $profile, $data);
            if (EB_WS_DEBUG) logLine($profile, "END job={$jobId} status={$status} lab={$lab}");
            // Fallback username if missing: try comet_jobs
            if ($username === '') {
                try {
                    $st = $pdo->prepare("SELECT username FROM comet_jobs WHERE id=? LIMIT 1");
                    $st->execute([$jobId]);
                    $u = $st->fetchColumn();
                    if ($u) { $username = (string)$u; }
                } catch (Throwable $_) {}
            }
            // Refresh vault usage to ensure comet_vaults reflects latest stats before scanning
            if ($username !== '') {
                try {
                    if (EB_WS_DEBUG) logLine($profile, "syncUserVaults start user={$username}");
                    syncUserVaults($pdo, $profile, (string)$username);
                    if (EB_WS_DEBUG) logLine($profile, "syncUserVaults done user={$username}");
                } catch (Throwable $_) { if (EB_WS_DEBUG) logLine($profile, "syncUserVaults error: " . $_->getMessage()); }
            }
            // Notify: backup completed → quick storage scan for this user
            try {
                @require_once __DIR__ . '/../lib/Notifications/bootstrap.php';
                if (function_exists('eb_notify_backup_completed')) {
                    eb_notify_backup_completed($pdo, $profile, (string)$username);
                }
            } catch (Throwable $_) {}
            // For non-success terminal statuses, schedule a one-shot delayed re-scan to tolerate stat lag
            if ($status !== 'success' && $username !== '') {
                \Amp\async(function() use ($pdo, $profile, $username) {
                    try {
                        if (EB_WS_DEBUG) logLine($profile, "delayed rescan (3s) user={$username}");
                        \Amp\delay(3.0);
                        @require_once __DIR__ . '/../lib/Notifications/bootstrap.php';
                        if (function_exists('eb_notify_backup_completed')) {
                            eb_notify_backup_completed($pdo, $profile, (string)$username);
                        }
                    } catch (Throwable $_) {}
                });
            }
        } else {
            if (EB_WS_DEBUG) logLine($profile, "END without jobId — ignored");
        }
    } else {
        // If Comet adds SEVT_JOB_PROGRESS later, we’ll land here until we map it.
        if (EB_WS_DEBUG) logLine($profile, "IGNORED {$lab} (not mapped)");
    }

    saveCursor($pdo, $profile, $ended_at ?: $ts, $jobId ?: null);
}





/////////////////////
// One profile run //
/////////////////////
function runOneProfile(PDO $pdo, array $cfg): never {
    $profile = $cfg['server_id'];
    $url     = $cfg['url'];     // wss://…/events/stream
    $origin  = $cfg['origin'];
    $user    = $cfg['username'];
    $auth    = $cfg['authtype'];
    $pass    = $cfg['password'];
    $sess    = $cfg['sessionkey'];
    $totp    = $cfg['totp'];

    while (true) {
        try {
            // Build handshake and set Origin header.
            $hs = (new WebsocketHandshake($url))
            ->withHeader('Origin', $origin);

            // Connect (returns Amp\Websocket\Client\Connection)
            $conn = connect($hs);

            // Comet auth: 5 text frames
            $conn->sendText($user);
            $conn->sendText($auth);
            $conn->sendText($pass);
            $conn->sendText($sess);
            $conn->sendText($totp);

            // Expect "200 OK"
            $hello = $conn->receive();
            if ($hello === null) {
                throw new RuntimeException("No auth response from {$profile}");
            }
            $txt = trim($hello->buffer());
            if ($txt !== '200 OK') {
                throw new RuntimeException("Auth failed on {$profile}: {$txt}");
            }

            // Stream events forever
            while ($msg = $conn->receive()) {
                $raw = $msg->buffer();
                if (EB_WS_DEBUG) { logLine($profile, 'RAW ' . substr($raw, 0, 800)); }
                $evt = json_decode($raw, true);            
                if (!is_array($evt)) {
                    error_log("[{$profile}] Non-JSON frame: " . substr($raw, 0, 200));
                    continue;
                }
                handleEvent($pdo, $profile, $evt);
            }
        } catch (Throwable $e) {
            error_log("[{$profile}] WS error: {$e->getMessage()}");
        }
        // backoff
        \Amp\delay(2.0);
    }
}

/////////////////
// Bootstrap   //
/////////////////
loadEnv('/var/www/eazybackup.ca/.env');
$pdo = db();

$one = getenv('COMET_PROFILE'); // e.g., eazybackup / obc (set by systemd)
if ($one) {
    $cfg = loadCometProfile($one);
    runOneProfile($pdo, $cfg);
    exit;
}

// Fallback: run multiple profiles in one process if COMET_PROFILE not set
$profiles = array_filter(array_map('trim', explode(',', cfg('COMET_PROFILES', 'eazybackup,obc'))));
$futures = [];
foreach ($profiles as $p) {
    $cfg = loadCometProfile($p);
$futures[] = \Amp\async(fn() => runOneProfile($pdo, $cfg));
}
\Amp\Future\awaitAll($futures);


