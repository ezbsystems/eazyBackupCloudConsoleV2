<?php

declare(strict_types=1);

/**
 * Contract test: pulse snapshot polling and dashboard timeline identity.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/pulse_timeline_contract_test.php
 */

$root = dirname(__DIR__, 2);

$targets = [
    'pulse.php' => [
        'path' => $root . '/pages/console/pulse.php',
        'markers' => [
            'client scoped running jobs' => 'eb_running_jobs_for_client',
            'comet_devices join' => 'comet_devices as d',
            'composite job id' => '$serverId . \':\' . $jobId',
            'running-only snapshot' => 'jobsRunning',
            'interrupted derivation' => 'LiveJobState::deriveStatus',
            'offline since field' => 'offline_since',
        ],
        'forbidden' => [
            'sse stream' => 'function eb_pulse_events',
            'snooze endpoint' => 'function eb_pulse_snooze',
            'recent jobs in snapshot' => 'jobsRecent24h',
            'devices registry join' => 'eb_devices_registry',
        ],
    ],
    'eazybackup.php routes' => [
        'path' => $root . '/eazybackup.php',
        'markers' => [
            'snapshot route' => 'a"] == "pulse-snapshot"',
            'retired SSE tombstone route' => 'a"] == "pulse-events"',
            'retired SSE no-content response' => 'http_response_code(204)',
            'username index' => 'idx_username ON eb_jobs_live (username)',
        ],
        'forbidden' => [
            'snooze route' => 'pulse-snooze',
        ],
    ],
    'pulse-events.js' => [
        'path' => $root . '/assets/js/pulse-events.js',
        'markers' => [
            'visibility polling' => "document.visibilityState",
            '10 second interval' => 'POLL_MS = 10000',
            'device_name preserved' => 'device_name',
            'server_id preserved' => 'server_id',
            'snapshot event' => "emit('eb:pulse-snapshot'",
            'overlap guard' => 'inFlight',
            'backoff' => 'backoffMs',
        ],
        'forbidden' => [
            'event source' => 'EventSource',
            'sse endpoint' => 'a=pulse-events',
        ],
    ],
    'dashboard-timeline.js' => [
        'path' => $root . '/assets/js/dashboard-timeline.js',
        'markers' => [
            'device hash key' => 'deviceHash',
            'composite id helper' => 'compositeId',
            'authoritative snapshot' => 'resetFromSnapshot',
        ],
        'forbidden' => [
            'device name key comment' => 'device friendly name',
            'delta pulse listener' => "kind === 'job:start'",
        ],
    ],
    'dashboard.tpl' => [
        'path' => $root . '/templates/clientarea/dashboard.tpl',
        'markers' => [
            'reactive timeline jobs' => 'timelineJobs()',
            'timeline version dependency' => 'void this.timelineVer',
            'device hash lookup' => 'device.hash',
            'rolling 24h position' => 'dayAgo = now - (24 * 60 * 60 * 1000)',
            'snapshot endpoint only' => 'EB_PULSE_SNAPSHOT',
        ],
        'forbidden' => [
            'sse endpoint' => 'EB_PULSE_ENDPOINT',
            'inline non-reactive jobs24h' => 'jobs24h()',
        ],
    ],
];

$failures = [];

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

if (is_file($root . '/assets/js/pulse.js')) {
    $failures[] = 'FAIL: orphaned assets/js/pulse.js still exists';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Pulse timeline contract PASS (" . count($targets) . " targets)\n");
exit(0);
