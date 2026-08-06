<?php

declare(strict_types=1);

/**
 * Contract tests for Comet websocket recovery policy.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/websocket_recovery_policy_test.php
 */

$root = dirname(__DIR__, 2);
$failures = [];

require_once $root . '/vendor/autoload.php';

function assert_same($expected, $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        $failures[] = sprintf(
            'FAIL: %s (expected %s, got %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        );
    }
}

function assert_true(bool $value, string $message): void
{
    assert_same(true, $value, $message);
}

function assert_false(bool $value, string $message): void
{
    assert_same(false, $value, $message);
}

require_once $root . '/lib/WebsocketRecoveryPolicy.php';

use WHMCS\Module\Addon\Eazybackup\WebsocketRecoveryPolicy;

$config = [
    'idle_alert_after' => 2,
    'recovery_deadline_secs' => 60,
    'connect_backoff_base' => 2,
    'connect_backoff_max' => 32,
    'idle_reconnect_delay' => 2,
    'connect_alert_after' => 3,
];

$state = WebsocketRecoveryPolicy::initialState();
$now = 1_700_000_000;

$firstRecycle = WebsocketRecoveryPolicy::onIdleRecycle($state, $now, 300, $config);
assert_false($firstRecycle['should_alert'], 'single idle recycle does not alert');
assert_same('idle-recycle', $firstRecycle['log'], 'idle recycle log kind');
assert_same(2, $firstRecycle['reconnect_delay'], 'idle recycle uses fast reconnect');

WebsocketRecoveryPolicy::onAuthenticated($state, $now + 5);
$effects = WebsocketRecoveryPolicy::onStreamEvent($state, 'SEVT_META_HELLO', $now + 6);
assert_true($effects['recovery_complete'], 'hello completes recovery');
assert_same(0, $state['consecutive_idle_recycles'], 'hello resets idle recycle counter');

$state = WebsocketRecoveryPolicy::initialState();
WebsocketRecoveryPolicy::onIdleRecycle($state, $now, 300, $config);
$secondRecycle = WebsocketRecoveryPolicy::onIdleRecycle($state, $now + 300, 300, $config);
assert_true($secondRecycle['should_alert'], 'repeated idle recycle alerts');
assert_same(
    WebsocketRecoveryPolicy::ALERT_IDLE_RECYCLE,
    $secondRecycle['alert_kind'],
    'repeated idle recycle alert kind'
);

$state = WebsocketRecoveryPolicy::initialState();
$connect1 = WebsocketRecoveryPolicy::onConnectFailure($state, $now, 'connection refused', $config);
assert_false($connect1['should_alert'], 'first connect failure does not alert');
assert_same(2, $connect1['reconnect_delay'], 'first connect backoff is base');

WebsocketRecoveryPolicy::onConnectFailure($state, $now + 1, 'connection refused', $config);
$connect3 = WebsocketRecoveryPolicy::onConnectFailure($state, $now + 2, 'connection refused', $config);
assert_true($connect3['should_alert'], 'third connect failure alerts');
assert_same(8, $connect3['reconnect_delay'], 'connect backoff grows exponentially');

$state = WebsocketRecoveryPolicy::initialState();
WebsocketRecoveryPolicy::onAuthenticated($state, $now);
$deadline = WebsocketRecoveryPolicy::recoveryDeadlineExceeded($state, $now + 30, $config);
assert_same(null, $deadline, 'recovery deadline not exceeded yet');

$deadlineExceeded = WebsocketRecoveryPolicy::recoveryDeadlineExceeded($state, $now + 61, $config);
assert_true($deadlineExceeded['should_alert'] ?? false, 'recovery deadline exceeded alerts');
assert_same(
    WebsocketRecoveryPolicy::ALERT_RECOVERY_FAILED,
    $deadlineExceeded['alert_kind'] ?? '',
    'recovery deadline alert kind'
);

// Healthy session that later idle-recycles must not look like a failed recovery.
$state = WebsocketRecoveryPolicy::initialState();
WebsocketRecoveryPolicy::onAuthenticated($state, $now);
WebsocketRecoveryPolicy::onStreamEvent($state, 'SEVT_META_HELLO', $now + 1);
WebsocketRecoveryPolicy::onIdleRecycle($state, $now + 301, 300, $config);
$afterHealthyIdle = WebsocketRecoveryPolicy::recoveryDeadlineExceeded($state, $now + 301, $config);
assert_same(null, $afterHealthyIdle, 'healthy idle recycle does not trigger recovery-failed');

$timeout = new RuntimeException('cancelled', 0, new Amp\TimeoutException('No application messages received for 300s'));
assert_true(
    WebsocketRecoveryPolicy::isApplicationMessageIdleTimeout($timeout),
    'detects application-message idle timeout'
);

$payload = WebsocketRecoveryPolicy::persistencePayload('cometbackup', $state, $now + 61);
assert_same('cometbackup', $payload['profile'], 'persistence includes profile');
assert_false((bool)$payload['session_healthy'], 'persistence records unhealthy session');

$workerSource = (string)file_get_contents($root . '/bin/comet_ws_worker.php');
assert_true(
    str_contains($workerSource, 'WebsocketRecoveryPolicy'),
    'worker integrates recovery policy'
);
assert_true(
    str_contains($workerSource, 'flushCursor'),
    'worker flushes cursor on disconnect'
);
assert_true(
    str_contains($workerSource, 'offline_strikes'),
    'worker uses device offline hysteresis'
);
assert_true(
    str_contains($workerSource, "persistWsRecoveryState(\$profile, \$recoveryState, 'heartbeat')"),
    'worker refreshes recovery state on heartbeat'
);

$healthSource = (string)file_get_contents($root . '/bin/check_ws_health.sh');
assert_true(
    str_contains($healthSource, 'WS idle-recycle'),
    'health script ignores recovered idle recycle'
);

$watchdogSource = (string)file_get_contents($root . '/bin/comet_ws_watchdog.php');
assert_true(
    str_contains($watchdogSource, 'ws-recovery-stale'),
    'watchdog checks websocket recovery freshness'
);

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Websocket recovery policy PASS\n");
exit(0);
