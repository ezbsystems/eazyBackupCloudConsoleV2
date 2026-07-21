<?php

declare(strict_types=1);

/**
 * Unit and contract tests for incomplete Comet job reconciliation.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/incomplete_job_reconciliation_test.php
 */

$root = dirname(__DIR__, 2);
$failures = [];

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

function assert_throws(callable $callback, string $exceptionClass, string $message): void
{
    global $failures;
    try {
        $callback();
        $failures[] = "FAIL: {$message} (no exception thrown)";
    } catch (Throwable $e) {
        if (!is_a($e, $exceptionClass)) {
            $failures[] = "FAIL: {$message} (got " . get_class($e) . ')';
        }
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    global $failures;
    if (!str_contains($haystack, $needle)) {
        $failures[] = "FAIL: {$message} (missing {$needle})";
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    global $failures;
    if (str_contains($haystack, $needle)) {
        $failures[] = "FAIL: {$message} (forbidden {$needle})";
    }
}

$helperPath = $root . '/lib/IncompleteJobReconciliation.php';
if (!is_file($helperPath)) {
    fwrite(STDERR, "FAIL: missing {$helperPath}\n");
    exit(1);
}

require_once $root . '/lib/LiveJobState.php';
require_once $helperPath;

use WHMCS\Module\Addon\Eazybackup\IncompleteJobReconciliation;

$eligibleInput = [
    'GUID' => 'job-1',
    'Username' => 'KashVora',
    'DeviceID' => 'device-1',
    'Classification' => 4001,
    'Status' => 6001,
    'StartTime' => 1_700_000_000,
    'UploadSize' => 100,
    'Progress' => [
        'BytesDone' => 125,
        'SentTime' => 1_700_000_100,
        'RecievedTime' => 1_700_000_200,
    ],
];

$eligible = IncompleteJobReconciliation::classify($eligibleInput);
assert_same(true, $eligible['eligible'], 'active backup is eligible');
assert_same('eligible', $eligible['reason'], 'active backup reason');
assert_same('job-1', $eligible['job']['job_id'], 'GUID is normalized');
assert_same(125, $eligible['job']['bytes'], 'largest progress count wins');
assert_same(1_700_000_200, $eligible['job']['heartbeat_ts'], 'latest heartbeat wins');

$revived = IncompleteJobReconciliation::classify(array_merge(
    $eligibleInput,
    ['GUID' => 'job-revived', 'Status' => 6002]
));
assert_same(true, $revived['eligible'], 'revived backup is eligible');

$terminal = IncompleteJobReconciliation::classify(array_merge(
    $eligibleInput,
    ['GUID' => 'job-terminal', 'Status' => 5000]
));
assert_same(false, $terminal['eligible'], 'terminal job is excluded');
assert_same('terminal_status', $terminal['reason'], 'terminal exclusion reason');

$nonBackup = IncompleteJobReconciliation::classify(array_merge(
    $eligibleInput,
    ['GUID' => 'job-other', 'Classification' => 4100]
));
assert_same(false, $nonBackup['eligible'], 'non-backup job is excluded');
assert_same('non_backup_classification', $nonBackup['reason'], 'classification exclusion reason');

$malformed = IncompleteJobReconciliation::classify([
    'Classification' => 4001,
    'Status' => 6001,
]);
assert_same(false, $malformed['eligible'], 'missing identifiers are excluded');
assert_same('malformed', $malformed['reason'], 'missing identifiers are malformed');

$classified = IncompleteJobReconciliation::classifyResponse([
    $eligibleInput,
    array_merge($eligibleInput, ['GUID' => 'job-terminal', 'Status' => 5000]),
    array_merge($eligibleInput, ['GUID' => 'job-other', 'Classification' => 4100]),
]);
assert_same(1, count($classified['jobs']), 'response returns eligible jobs');
assert_same(1, $classified['counts']['eligible'], 'response counts eligible jobs');
assert_same(1, $classified['counts']['terminal_status'], 'response counts terminal jobs');
assert_same(1, $classified['counts']['non_backup_classification'], 'response counts other jobs');

assert_throws(
    static fn() => IncompleteJobReconciliation::classifyResponse(['jobs' => []]),
    RuntimeException::class,
    'associative response is rejected as malformed'
);

$base = [
    'now' => 1_700_010_000,
    'activity_ts' => 1_700_000_000,
    'stale_secs' => 3600,
    'last_checked_ts' => 0,
    'recheck_secs' => 300,
    'stale_observations' => 0,
    'action_stage' => 'none',
    'next_action_ts' => 0,
    'action_attempts' => 0,
    'max_attempts' => 3,
    'has_cancellation_id' => true,
];

assert_same('strike', IncompleteJobReconciliation::decide($base)['action'], 'first stale observation strikes');
assert_same('cancel', IncompleteJobReconciliation::decide(array_merge(
    $base,
    ['stale_observations' => 1, 'last_checked_ts' => $base['now'] - 301]
))['action'], 'second stale observation cancels');
assert_same('abandon', IncompleteJobReconciliation::decide(array_merge(
    $base,
    [
        'stale_observations' => 2,
        'action_stage' => 'cancel_requested',
        'last_checked_ts' => $base['now'] - 301,
    ]
))['action'], 'later stale observation abandons');
assert_same('abandon', IncompleteJobReconciliation::decide(array_merge(
    $base,
    [
        'stale_observations' => 2,
        'action_stage' => 'cancel_unavailable',
        'last_checked_ts' => $base['now'] - 301,
    ]
))['action'], 'permanently unavailable cancel falls back to abandon later');
assert_same('abandon', IncompleteJobReconciliation::decide(array_merge(
    $base,
    [
        'stale_observations' => 1,
        'last_checked_ts' => $base['now'] - 301,
        'has_cancellation_id' => false,
    ]
))['action'], 'missing cancellation ID abandons after confirmation');
assert_same('fresh', IncompleteJobReconciliation::decide(array_merge(
    $base,
    ['activity_ts' => $base['now'] - 10]
))['action'], 'fresh activity resets state');
assert_same('defer', IncompleteJobReconciliation::decide(array_merge(
    $base,
    ['last_checked_ts' => $base['now'] - 10]
))['action'], 'recheck interval is honored');
assert_same('defer', IncompleteJobReconciliation::decide(array_merge(
    $base,
    ['next_action_ts' => $base['now'] + 10]
))['action'], 'next action time is honored');
assert_same('defer', IncompleteJobReconciliation::decide(array_merge(
    $base,
    ['action_stage' => 'exhausted', 'next_action_ts' => $base['now'] + 10]
))['action'], 'active cooldown defers');
assert_same('cooldown_reset', IncompleteJobReconciliation::decide(array_merge(
    $base,
    ['action_stage' => 'exhausted', 'next_action_ts' => $base['now'] - 1]
))['action'], 'expired cooldown restarts the cycle');
assert_same('exhaust', IncompleteJobReconciliation::decide(array_merge(
    $base,
    ['action_attempts' => 3]
))['action'], 'attempt cap enters cooldown');

IncompleteJobReconciliation::validateAuth([
    'Username' => 'admin',
    'Password' => 'secret',
    'SessionKey' => '',
]);
IncompleteJobReconciliation::validateAuth([
    'Username' => 'admin',
    'Password' => '',
    'SessionKey' => 'session',
]);
assert_throws(
    static fn() => IncompleteJobReconciliation::validateAuth([
        'Username' => '',
        'Password' => 'secret',
        'SessionKey' => '',
    ]),
    RuntimeException::class,
    'missing API username fails'
);
assert_throws(
    static fn() => IncompleteJobReconciliation::validateAuth([
        'Username' => 'admin',
        'Password' => '',
        'SessionKey' => '',
    ]),
    RuntimeException::class,
    'missing API credential fails'
);

$sanitized = IncompleteJobReconciliation::sanitizeError(
    'HTTP 403 Password=secret&SessionKey=session&TOTP=123456 ' . str_repeat('x', 400)
);
assert_same(false, str_contains($sanitized, 'secret'), 'password is removed from errors');
assert_same(false, str_contains($sanitized, 'session'), 'session key is removed from errors');
assert_same(false, str_contains($sanitized, '123456'), 'TOTP is removed from errors');
assert_same(true, strlen($sanitized) <= 255, 'sanitized errors are bounded');
$jsonSanitized = IncompleteJobReconciliation::sanitizeError(
    '{"Password":"json-secret","SessionKey":"json-session","TOTP":"654321"}'
);
assert_same(false, str_contains($jsonSanitized, 'json-secret'), 'JSON password is removed');
assert_same(false, str_contains($jsonSanitized, 'json-session'), 'JSON session key is removed');
assert_same(false, str_contains($jsonSanitized, '654321'), 'JSON TOTP is removed');

IncompleteJobReconciliation::assertNoApiError([
    'Status' => 6001,
    'GUID' => 'job-running',
]);
assert_throws(
    static fn() => IncompleteJobReconciliation::assertNoApiError([
        'Status' => 403,
        'Message' => 'permission denied',
    ]),
    RuntimeException::class,
    'embedded Comet API errors are rejected'
);
IncompleteJobReconciliation::validateMutationResponse([
    'Status' => 200,
    'Message' => 'OK',
]);
assert_throws(
    static fn() => IncompleteJobReconciliation::validateMutationResponse([
        'Status' => 500,
        'Message' => 'failed',
    ]),
    RuntimeException::class,
    'failed mutation response is rejected'
);
assert_throws(
    static fn() => IncompleteJobReconciliation::validateMutationResponse([]),
    RuntimeException::class,
    'mutation response requires a 2xx status'
);
assert_same(true, IncompleteJobReconciliation::isRunningStatus(6001), 'active is running');
assert_same(true, IncompleteJobReconciliation::isRunningStatus(6002), 'revived is running');
assert_same(false, IncompleteJobReconciliation::isRunningStatus(6999), 'unknown 6xxx is not running');
assert_same(true, IncompleteJobReconciliation::isKnownTerminalStatus(7007), 'abandoned is terminal');
assert_same(false, IncompleteJobReconciliation::isKnownTerminalStatus(6999), 'unknown status is not terminal');

$schemaSource = (string)file_get_contents($root . '/eazybackup.php');
assert_contains("'stale_observations'", $schemaSource, 'migration adds stale observations');
assert_contains("'action_stage'", $schemaSource, 'migration adds action stage');
assert_contains("'next_action_ts'", $schemaSource, 'migration adds next action timestamp');
assert_contains("'last_action_error'", $schemaSource, 'migration adds sanitized error');
assert_contains("'eb_monitor_profile_state'", $schemaSource, 'migration adds profile health table');

$monitorSource = (string)file_get_contents($root . '/bin/monitor_stalled_jobs.php');
assert_contains('function ensureReconciliationSchema', $monitorSource, 'monitor protects reconciliation schema');
assert_contains("'/admin/get-jobs-for-date-range'", $monitorSource, 'authoritative endpoint');
assert_contains("'Start' => 0", $monitorSource, 'incomplete start argument');
assert_contains("'End' => 0", $monitorSource, 'incomplete end argument');
assert_contains('EB_RECONCILE_INCOMPLETE_MODE', $monitorSource, 'mode configuration');
assert_contains('function discoverIncompleteJobs', $monitorSource, 'discovery function');
assert_contains('cometAdminAuth($profile)', $monitorSource, 'reuses configured profile credentials');
assert_not_contains('explicitCometAdminAuth', $monitorSource, 'does not require separate Admin credentials');
assert_contains('function processReconciledLiveJobs', $monitorSource, 'enforce lifecycle processor');
assert_contains('function resetReconciliationState', $monitorSource, 'fresh activity reset');
assert_contains('function recordStaleObservation', $monitorSource, 'two-strike persistence');
assert_contains('function recordActionFailure', $monitorSource, 'failed actions preserve lifecycle stage');
assert_contains("'cancel_requested'", $monitorSource, 'cancel stage');
assert_contains("'abandon_requested'", $monitorSource, 'abandon stage');
assert_contains("'exhausted'", $monitorSource, 'cooldown stage');
assert_contains('action_cap_deferred', $monitorSource, 'bounded mutation deferral');
assert_contains('reconcile_action_limit', $monitorSource, 'mutation budget summary');
assert_contains("'profile_failures'", $monitorSource, 'enforcement health is tracked separately');
assert_contains('INSERT INTO comet_jobs', $monitorSource, 'terminal mirror upserts missing jobs');
assert_contains('GET_LOCK', $monitorSource, 'manual runs use a per-profile advisory lock');

$envSource = (string)file_get_contents(dirname($root, 4) . '/.env');
assert_contains(
    "getenv('EB_RECONCILE_INCOMPLETE_MODE') ?: 'off'",
    $monitorSource,
    'code default remains off'
);
assert_same(
    1,
    preg_match('/^EB_RECONCILE_INCOMPLETE_MODE=(off|audit|enforce)\r?$/m', $envSource),
    'deployed mode is valid'
);
assert_contains('EB_RECONCILE_ACTION_LIMIT=25', $envSource, 'safe action limit');
assert_contains('EB_RECONCILE_RETRY_COOLDOWN_SECS=86400', $envSource, 'retry cooldown');

$watchdogSource = (string)file_get_contents($root . '/bin/comet_ws_watchdog.php');
assert_contains('EB_WATCH_RECONCILE_STALE_MIN', $watchdogSource, 'watchdog stale threshold');
assert_contains("'reconcile-stale'", $watchdogSource, 'stale discovery alert');
assert_contains("'reconcile-failed'", $watchdogSource, 'failed discovery alert');
assert_contains(
    'function discoverConfiguredReconciliationProfiles',
    $watchdogSource,
    'reconciliation health does not depend on enabled WebSocket units'
);

$docsSource = (string)file_get_contents($root . '/Docs/EAZYBACKUP_README.md');
assert_contains('AdminGetJobsForDateRange(0, 0)', $docsSource, 'authoritative discovery docs');
assert_contains('EB_RECONCILE_INCOMPLETE_MODE', $docsSource, 'mode docs');
assert_contains('action_cap_deferred', $docsSource, 'action cap troubleshooting docs');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Incomplete job reconciliation PASS\n");
exit(0);
