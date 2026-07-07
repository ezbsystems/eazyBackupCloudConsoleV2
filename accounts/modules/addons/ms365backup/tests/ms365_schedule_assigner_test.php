<?php
declare(strict_types=1);

/**
 * MS365 schedule timezone evaluation.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_schedule_assigner_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365ScheduleAssigner;

$failures = 0;

function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$torontoSlots = [
    'schedule_slots' => [['hour' => 20, 'minute' => 35]],
    'timezone' => 'America/Toronto',
];

// 2026-07-07 00:35:00 UTC = 2026-07-06 20:35 EDT
$dueUtc = new DateTimeImmutable('2026-07-07 00:35:00', new DateTimeZone('UTC'));
assert_true(
    Ms365ScheduleAssigner::isDueNow($torontoSlots, $dueUtc),
    'Toronto 20:35 slot is due when UTC is 00:35 next calendar day',
);

// 2026-07-06 20:35 UTC = 16:35 Toronto (EDT) — not due
$notDueUtc = new DateTimeImmutable('2026-07-06 20:35:00', new DateTimeZone('UTC'));
assert_true(
    !Ms365ScheduleAssigner::isDueNow($torontoSlots, $notDueUtc),
    'Toronto 20:35 slot is not due when UTC wall clock is 20:35',
);

$missingTzSlots = [
    'schedule_slots' => [['hour' => 20, 'minute' => 35]],
];
assert_true(
    Ms365ScheduleAssigner::isDueNow($missingTzSlots, $dueUtc),
    'Missing timezone falls back to America/Toronto',
);

assert_true(
    Ms365ScheduleAssigner::localMinuteKey($torontoSlots, $dueUtc) === '2026-07-06-20-35',
    'localMinuteKey uses job timezone not server UTC',
);

$nextMs = Ms365ScheduleAssigner::nextRunEpochMs($torontoSlots, $notDueUtc);
assert_true(
    $nextMs !== null && $nextMs > ($notDueUtc->getTimestamp() * 1000),
    'nextRunEpochMs returns a future instant',
);

// Spring-forward: 2026-03-08 02:30 does not exist in America/Toronto; slot 02:30 should not false-positive at 03:30.
$springSlots = [
    'schedule_slots' => [['hour' => 2, 'minute' => 30]],
    'timezone' => 'America/Toronto',
];
$springAfter = new DateTimeImmutable('2026-03-08 07:30:00', new DateTimeZone('UTC')); // 03:30 EDT
assert_true(
    !Ms365ScheduleAssigner::isDueNow($springSlots, $springAfter),
    'DST spring-forward gap does not false-positive at 03:30 local',
);

exit($failures > 0 ? 1 : 0);
