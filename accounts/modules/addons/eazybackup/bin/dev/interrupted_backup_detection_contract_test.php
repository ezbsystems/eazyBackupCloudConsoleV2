<?php

declare(strict_types=1);

/**
 * Contract + unit tests: interrupted backup detection.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/interrupted_backup_detection_contract_test.php
 */

$root = dirname(__DIR__, 2);
require_once $root . '/lib/LiveJobState.php';

use WHMCS\Module\Addon\Eazybackup\LiveJobState;

$failures = [];

function assert_true(bool $cond, string $msg): void {
    global $failures;
    if (!$cond) {
        $failures[] = 'FAIL: ' . $msg;
    }
}

$now = 1_700_000_000;
$grace = LiveJobState::interruptedGraceSecs();

// Grace: online device stays Running
$online = LiveJobState::deriveStatus(true, '2026-01-01 00:00:00', $now);
assert_true($online['status'] === 'Running', 'online device should stay Running');
assert_true($online['status_reason'] === null, 'online device has no status_reason');

// Grace: offline < 5 minutes stays Running
$recentOffline = gmdate('Y-m-d H:i:s', $now - ($grace - 60));
$withinGrace = LiveJobState::deriveStatus(false, $recentOffline, $now);
assert_true($withinGrace['status'] === 'Running', 'offline within grace should stay Running');

// Interrupted after grace
$pastGrace = gmdate('Y-m-d H:i:s', $now - ($grace + 1));
$interrupted = LiveJobState::deriveStatus(false, $pastGrace, $now);
assert_true($interrupted['status'] === 'Interrupted', 'offline past grace should be Interrupted');
assert_true($interrupted['status_reason'] === 'device_offline', 'Interrupted carries device_offline reason');
assert_true($interrupted['offline_since'] !== null, 'Interrupted includes offline_since');

// Reconnect clears Interrupted semantics
$reconnected = LiveJobState::deriveStatus(true, $pastGrace, $now);
assert_true($reconnected['status'] === 'Running', 'reconnected device returns to Running');

// Unknown device match stays Running
$unknown = LiveJobState::deriveStatus(null, $pastGrace, $now);
assert_true($unknown['status'] === 'Running', 'unknown device match stays Running');

// Heartbeat helper
$hb = LiveJobState::progressHeartbeatTs([
    'Progress' => ['SentTime' => 100, 'RecievedTime' => 250],
]);
assert_true($hb === 250, 'progressHeartbeatTs prefers max SentTime/RecievedTime');

$staleHb = LiveJobState::progressHeartbeatTs(['Progress' => []]);
assert_true($staleHb === 0, 'progressHeartbeatTs returns 0 when missing');

$targets = [
    'pulse.php' => [
        'path' => $root . '/pages/console/pulse.php',
        'markers' => [
            'live job state helper' => 'LiveJobState::deriveStatus',
            'device offline fields' => 'd.offline_since',
            'status reason' => 'status_reason',
        ],
    ],
    'LiveJobState.php' => [
        'path' => $root . '/lib/LiveJobState.php',
        'markers' => [
            'grace default' => 'DEFAULT_INTERRUPTED_GRACE_SECS = 300',
            'offline cleanup default' => 'DEFAULT_OFFLINE_CLEANUP_SECS = 3600',
            'derive status' => 'function deriveStatus',
        ],
    ],
    'eazybackup.php schema' => [
        'path' => $root . '/eazybackup.php',
        'markers' => [
            'offline_since column' => "eb_add_column_if_missing('comet_devices','offline_since'",
            'offline_since backfill' => 'offline_since = updated_at',
        ],
    ],
    'comet_ws_worker.php' => [
        'path' => $root . '/bin/comet_ws_worker.php',
        'markers' => [
            'upsert offline_since case' => 'offline_since = CASE',
            'heartbeat offline_since case' => 'offline_since = CASE',
        ],
    ],
    'Comet.php' => [
        'path' => $root . '/lib/Comet.php',
        'markers' => [
            'sync offline_since' => "'offline_since' =>",
        ],
    ],
    'monitor_stalled_jobs.php' => [
        'path' => $root . '/bin/monitor_stalled_jobs.php',
        'markers' => [
            'offline cleanup env' => 'EB_OFFLINE_CLEANUP_SECS',
            'heartbeat timestamp helper' => 'function progressHeartbeatTs',
            'activity timestamp touch' => 'touchProgress(PDO $pdo, string $serverId, string $jobId, int $bytes, ?int $activityTs = null)',
            'offline cleanup path' => 'function processOfflineLiveJobs',
            'stale cumulative guard' => 'stale cumulative bytes',
            'terminal Comet mirror synchronization' => 'UPDATE comet_jobs',
            'terminal mirror EndTime synchronization' => "'$.EndTime'",
        ],
    ],
    'eazybackup-ui-helpers.js' => [
        'path' => $root . '/assets/js/eazybackup-ui-helpers.js',
        'markers' => [
            'interrupted label' => "u === 'INTERRUPTED') return 'Interrupted'",
            'interrupted dot' => 'Interrupted: \'bg-amber-500\'',
            'abandoned numeric status' => "7007: 'Error'",
        ],
    ],
    'dashboard_usage_metrics.php' => [
        'path' => $root . '/pages/console/dashboard_usage_metrics.php',
        'markers' => [
            'interrupted metric key' => "'interrupted' => 0",
            'derive interrupted count' => "LiveJobState::deriveStatus",
        ],
    ],
    'dashboard-usage-cards.js' => [
        'path' => $root . '/templates/assets/js/dashboard-usage-cards.js',
        'markers' => [
            'interrupted legend key' => "'interrupted'",
            'interrupted donut slice' => "['Interrupted', interrupted]",
        ],
    ],
    'dashboard.tpl' => [
        'path' => $root . '/templates/clientarea/dashboard.tpl',
        'markers' => [
            'interrupted chip' => "'Interrupted'",
            'interrupted issue set' => "'Interrupted'",
            'live merge includes interrupted' => "label === 'Interrupted'",
            'timeline uses inherited dashboard method' => 'return this.jobsInLast24h(device) || [];',
        ],
        'forbidden' => [
            'broken Alpine parent traversal' => 'this.$parent && this.$parent.$parent',
        ],
    ],
];

foreach ($targets as $label => $spec) {
    $source = @file_get_contents($spec['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$label} at {$spec['path']}";
        continue;
    }
    foreach (($spec['markers'] ?? []) as $name => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: {$label} missing marker [{$name}]: {$needle}";
        }
    }
    foreach (($spec['forbidden'] ?? []) as $name => $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: {$label} still contains forbidden [{$name}]: {$needle}";
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Interrupted backup detection contract PASS (' . count($targets) . " targets, LiveJobState unit checks)\n");
exit(0);
