<?php
declare(strict_types=1);

/**
 * MS365 scheduled overlap skip — contract / smoke checks.
 *
 * Run: php accounts/modules/addons/cloudstorage/tests/ms365_schedule_overlap_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365JobOverlapGuard;
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

assert_true(
    Ms365JobOverlapGuard::ACTIVE_STATUSES === ['queued', 'starting', 'running'],
    'ACTIVE_STATUSES matches agent overlap guard',
);

assert_true(
    !Ms365BatchRunRepository::isScheduleSkipStats(null),
    'isScheduleSkipStats rejects null',
);

assert_true(
    !Ms365BatchRunRepository::isScheduleSkipStats('{"foo":1}'),
    'isScheduleSkipStats rejects unrelated stats_json',
);

assert_true(
    Ms365BatchRunRepository::isScheduleSkipStats('{"ms365_schedule_skip":true}'),
    'isScheduleSkipStats detects schedule skip flag',
);

$slots = Ms365ScheduleAssigner::assignSlots(Ms365ScheduleAssigner::FREQUENCY_TWICE_DAILY);
assert_true(count($slots) === 2, 'Twice daily assigns two slots');

echo "\nManual verification checklist:\n";
echo "  1. Start a long MS365 manual backup for an active scheduled job.\n";
echo "  2. Temporarily set schedule_json.schedule_slots to the current hour:minute.\n";
echo "  3. Run: php modules/addons/cloudstorage/crons/ms365_scheduled_backups.php\n";
echo "  4. Expect activity log: skipped 1 overlapping slot(s); no new ms365_backup_runs children.\n";
echo "  5. Run history shows Skipped row with overlap error_summary.\n";
echo "  6. Manual Run now while backup active still starts a second batch.\n";

exit($failures > 0 ? 1 : 0);
