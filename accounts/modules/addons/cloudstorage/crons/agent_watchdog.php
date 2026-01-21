<?php

require __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;

function getIntEnv(string $key, int $default): int
{
    $val = getenv($key);
    if ($val === false) {
        return $default;
    }

    $val = (int) $val;
    return $val > 0 ? $val : $default;
}

function getBoolEnv(string $key, bool $default): bool
{
    $val = getenv($key);
    if ($val === false) {
        return $default;
    }
    $normalized = strtolower(trim((string) $val));
    if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
        return false;
    }
    if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
        return true;
    }
    return $default;
}

function getModuleSetting(string $key, $default = null)
{
    try {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $key)
            ->value('value');
        return ($val !== null && $val !== '') ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function getAgentTimingConfig(): array
{
    $defaultWatchdog = 720;
    $defaultReclaim = 180;
    $defaultReclaimEnabled = true;

    $dbWatchdog = (int) getModuleSetting('cloudbackup_agent_watchdog_timeout_seconds', $defaultWatchdog);
    $dbReclaim = (int) getModuleSetting('cloudbackup_agent_reclaim_grace_seconds', $defaultReclaim);
    $dbReclaimEnabledRaw = getModuleSetting('cloudbackup_agent_reclaim_enabled', $defaultReclaimEnabled ? '1' : '0');
    $dbReclaimEnabled = !in_array(strtolower((string) $dbReclaimEnabledRaw), ['0', 'false', 'off', 'no'], true);

    $watchdog = getIntEnv('AGENT_WATCHDOG_TIMEOUT_SECONDS', $dbWatchdog);
    $reclaim = getIntEnv('AGENT_RECLAIM_GRACE_SECONDS', $dbReclaim);
    $reclaimEnabled = getBoolEnv('AGENT_RECLAIM_ENABLED', $dbReclaimEnabled);

    if ($reclaim >= $watchdog) {
        $reclaim = max(60, (int) floor($watchdog * 0.25));
        if ($reclaim >= $watchdog) {
            $reclaim = max(60, $watchdog - 60);
        }
    }

    return [
        'watchdog_timeout_seconds' => $watchdog,
        'reclaim_grace_seconds' => $reclaim,
        'reclaim_enabled' => $reclaimEnabled,
    ];
}

function formatHeartbeat(?string $heartbeat): string
{
    return $heartbeat ?: 'unknown';
}

$timing = getAgentTimingConfig();
$hasUpdatedAtColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');
$heartbeatExpr = $hasUpdatedAtColumn ? "COALESCE(r.updated_at, r.started_at, r.created_at)" : "COALESCE(r.started_at, r.created_at)";

$result = Capsule::connection()->transaction(function () use ($timing, $heartbeatExpr, $hasUpdatedAtColumn) {
    $staleRuns = Capsule::table('s3_cloudbackup_runs as r')
        ->select('r.*', Capsule::raw("$heartbeatExpr as last_heartbeat_at"))
        ->whereIn('r.status', ['starting', 'running'])
        ->whereRaw("TIMESTAMPDIFF(SECOND, $heartbeatExpr, NOW()) > ?", [$timing['watchdog_timeout_seconds']])
        ->lockForUpdate()
        ->get();

    $processed = [];
    $events = [];

    foreach ($staleRuns as $run) {
        $lastHeartbeat = $run->last_heartbeat_at ?? null;
        $message = 'Agent offline / no heartbeat since ' . formatHeartbeat($lastHeartbeat);
        $isCancelRequested = !empty($run->cancel_requested);
        $updateStatus = $isCancelRequested ? 'cancelled' : 'failed';
        $summary = $isCancelRequested ? ('Cancellation requested; ' . $message) : $message;

        $update = [
            'status' => $updateStatus,
            'error_summary' => $summary,
            'finished_at' => Capsule::raw('NOW()'),
        ];

        if ($hasUpdatedAtColumn) {
            $update['updated_at'] = Capsule::raw('NOW()');
        }

        Capsule::table('s3_cloudbackup_runs')
            ->where('id', $run->id)
            ->update($update);

        $events[] = [
            'run_id' => $run->id,
            'ts' => date('Y-m-d H:i:s.u'),
            'type' => $isCancelRequested ? 'cancelled' : 'error',
            'level' => $isCancelRequested ? 'warn' : 'error',
            'code' => $isCancelRequested ? 'CANCELLED' : 'AGENT_OFFLINE',
            'message_id' => $isCancelRequested ? 'CANCELLED' : 'AGENT_OFFLINE',
            'params_json' => json_encode([
                'last_heartbeat_at' => $lastHeartbeat,
                'watchdog_timeout_seconds' => $timing['watchdog_timeout_seconds'],
                'cancel_requested' => $isCancelRequested ? 1 : 0,
            ]),
        ];

        $processed[] = [
            'run_id' => $run->id,
            'agent_id' => $run->agent_id ?? null,
            'last_heartbeat_at' => $lastHeartbeat,
            'status' => $updateStatus,
        ];
    }

    if (!empty($events)) {
        Capsule::table('s3_cloudbackup_run_events')->insert($events);
    }

    return [
        'processed' => $processed,
        'count' => count($processed),
    ];
});

echo "[agent_watchdog] watchdog_timeout_seconds={$timing['watchdog_timeout_seconds']} reclaim_grace_seconds={$timing['reclaim_grace_seconds']}\n";
echo "[agent_watchdog] processed stale runs: {$result['count']}\n";

if (!empty($result['processed'])) {
    foreach ($result['processed'] as $run) {
        echo sprintf(
            " - run_id=%s agent_id=%s status=%s last_heartbeat=%s\n",
            $run['run_id'],
            $run['agent_id'] ?? 'null',
            $run['status'] ?? 'unknown',
            formatHeartbeat($run['last_heartbeat_at'] ?? null)
        );
    }
}

logModuleCall(
    'cloudstorage',
    'agent_watchdog',
    [
        'watchdog_timeout_seconds' => $timing['watchdog_timeout_seconds'],
        'reclaim_grace_seconds' => $timing['reclaim_grace_seconds'],
    ],
    $result
);

