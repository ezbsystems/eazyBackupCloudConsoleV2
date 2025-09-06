<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { require_once __DIR__ . '/../../../../../init.php'; }

/**
 * Helpers
 */
function eb_assert_client() {
    if (!isset($_SESSION['uid']) || !$_SESSION['uid']) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
    return (int)$_SESSION['uid'];
}

function eb_active_usernames_for_client(int $clientId): array {
    try {
        $excludeProductgroupIds = [2, 11];
        $productIds = Capsule::table('tblproducts')
            ->select('id')
            ->whereNotIn('gid', $excludeProductgroupIds)
            ->pluck('id')
            ->toArray();
        return Capsule::table('tblhosting')
            ->select('username')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->pluck('username')
            ->toArray();
    } catch (\Throwable $e) {
        return [];
    }
}

function eb_running_jobs(array $usernames): array {
    if (empty($usernames)) { return []; }
    $rows = Capsule::table('eb_jobs_live as j')
        ->leftJoin('eb_devices_registry as r', function($join){
            $join->on('r.server_id', '=', 'j.server_id')
                 ->on('r.device_id', '=', 'j.device');
        })
        ->whereIn('j.username', $usernames)
        ->select('j.server_id','j.job_id','j.username','j.device','j.job_type','j.started_at','j.last_update','r.friendly_name')
        ->orderBy('j.started_at','desc')
        ->limit(1000)
        ->get();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'            => (string)$r->job_id,
            'job_id'        => (string)$r->job_id,
            'server_id'     => (string)$r->server_id,
            'username'      => (string)$r->username,
            'device'        => (string)$r->device,
            'device_name'   => (string)($r->friendly_name ?? $r->device),
            'status'        => 'Running',
            'started_at'    => (int)$r->started_at,
            'ended_at'      => 0,
        ];
    }
    return $out;
}

function eb_recent_jobs(array $usernames, int $sinceTs): array {
    if (empty($usernames)) { return []; }
    $rows = Capsule::table('eb_jobs_recent_24h as j')
        ->leftJoin('eb_devices_registry as r', function($join){
            $join->on('r.server_id', '=', 'j.server_id')
                 ->on('r.device_id', '=', 'j.device');
        })
        ->whereIn('j.username', $usernames)
        ->where('j.ended_at', '>=', $sinceTs)
        ->orderBy('j.ended_at','desc')
        ->limit(2000)
        ->get();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'            => (string)$r->job_id,
            'job_id'        => (string)$r->job_id,
            'server_id'     => (string)$r->server_id,
            'username'      => (string)$r->username,
            'device'        => (string)$r->device,
            'device_name'   => (string)($r->friendly_name ?? $r->device),
            'status'        => (string)$r->status,
            'started_at'    => 0,
            'ended_at'      => (int)$r->ended_at,
        ];
    }
    return $out;
}

/** JSON snapshot: seeded data for reconnects */
function eb_pulse_snapshot() {
    $clientId = eb_assert_client();
    header('Content-Type: application/json');
    $usernames = eb_active_usernames_for_client($clientId);
    $now = time();
    $since = $now - 24*60*60;
    $running = eb_running_jobs($usernames);
    $recent = eb_recent_jobs($usernames, $since);
    echo json_encode([
        'status' => 'success',
        'jobsRunning' => $running,
        'jobsRecent24h' => $recent,
        't' => $now,
    ]);
    exit;
}

/** SSE stream: small loop emitting deltas */
function eb_pulse_events() {
    $clientId = eb_assert_client();
    // Headers for SSE
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // nginx: disable proxy buffering

    // Streaming hygiene: release session lock, disable buffering/compression
    if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
    if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
    @ob_implicit_flush(true);
    while (ob_get_level() > 0) { @ob_end_flush(); }
    ignore_user_abort(true);

    $usernames = eb_active_usernames_for_client($clientId);
    $start = microtime(true);
    $duration = 27; // seconds
    $tick = 2; // seconds
    $known = [];

    // Send initial snapshot
    $snapshot = [
        'kind' => 'snapshot',
        't' => time(),
        'jobsRunning' => eb_running_jobs($usernames),
        'jobsRecent24h' => [],
    ];
    echo 'data: ' . json_encode($snapshot) . "\n\n";
    @ob_flush(); @flush();

    foreach ($snapshot['jobsRunning'] as $j) { $known[(string)$j['id']] = $j; }

    // Loop and emit deltas
    while ((microtime(true) - $start) < $duration) {
        usleep($tick * 1000 * 1000);
        // Current running set
        $curr = eb_running_jobs($usernames);
        $currMap = [];
        foreach ($curr as $j) { $currMap[(string)$j['id']] = $j; }

        // job:start
        foreach ($currMap as $id => $row) {
            if (!isset($known[$id])) {
                $evt = [ 'kind' => 'job:start', 't' => time(), 'job' => $row ];
                echo 'data: ' . json_encode($evt) . "\n\n";
                $known[$id] = $row;
            }
        }

        // job:end for any that disappeared
        foreach ($known as $id => $prev) {
            if (!isset($currMap[$id])) {
                // Try to enrich with status/ended_at
                $ended = Capsule::table('eb_jobs_recent_24h')->where('job_id', $id)->first();
                $payload = [
                    'id' => (string)$id,
                    'job_id' => (string)$id,
                    'username' => (string)($prev['username'] ?? ''),
                    'device' => (string)($prev['device'] ?? ''),
                    'device_name' => (string)($prev['device_name'] ?? ''),
                    'status' => $ended ? (string)$ended->status : 'Unknown',
                    'started_at' => (int)($prev['started_at'] ?? 0),
                    'ended_at' => $ended ? (int)$ended->ended_at : time(),
                ];
                $evt = [ 'kind' => 'job:end', 't' => time(), 'job' => $payload ];
                echo 'data: ' . json_encode($evt) . "\n\n";
                unset($known[$id]);
            }
        }

        @ob_flush(); @flush();
    }
    // End of stream (client will reconnect)
    exit;
}

/** Optional: incident snooze */
function eb_pulse_snooze() {
    $clientId = eb_assert_client();
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $jobId = isset($payload['job_id']) ? (string)$payload['job_id'] : '';
    $minutes = isset($payload['minutes']) ? max(0, (int)$payload['minutes']) : 0;
    if ($jobId === '' || $minutes <= 0) {
        echo json_encode(['status'=>'error','message'=>'Missing job_id or minutes']);
        exit;
    }
    $until = time() + ($minutes * 60);
    Capsule::table('eb_incident_ack')->updateOrInsert(
        ['client_id' => $clientId, 'job_id' => $jobId],
        ['until_ts' => $until]
    );
    echo json_encode(['status'=>'success']);
    exit;
}


