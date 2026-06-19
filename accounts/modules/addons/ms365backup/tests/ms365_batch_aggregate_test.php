<?php
declare(strict_types=1);

/**
 * MS365 batch aggregate progress and parent status lock — unit checks.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_batch_aggregate_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365BatchRunRepository;

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

function assert_near(float $actual, float $expected, float $tolerance, string $message): void
{
    global $failures;
    if (abs($actual - $expected) > $tolerance) {
        echo "FAIL: {$message} (expected ~{$expected}, got {$actual})\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

/** @param list<array<string, mixed>> $children */
function child(string $status, float $percent = 0.0, int $itemsTotal = 0, int $itemsDone = 0, string $phase = ''): array
{
    return [
        'status' => $status,
        'percent' => $percent,
        'items_total' => $itemsTotal,
        'items_done' => $itemsDone,
        'phase' => $phase,
        'bytes_hashed' => 0,
        'bytes_uploaded' => 0,
        'items_skipped' => 0,
    ];
}

$largeBatch = array_merge(
    array_fill(0, 14, child('success')),
    array_fill(0, 10, child('error')),
    array_fill(0, 5, child('running', 40.0)),
    array_fill(0, 184, child('queued')),
);
$largeAgg = Ms365BatchRunRepository::computeAggregates($largeBatch);
assert_near((float) $largeAgg['progress_pct'], 12.21, 0.5, 'Large batch progress reflects queued workloads');
assert_true($largeAgg['status'] === 'running', 'Large batch with queued children stays running');
assert_true((int) ($largeAgg['queued_workloads'] ?? 0) === 184, 'Large batch reports queued workload count');

$allTerminal = array_merge(
    array_fill(0, 14, child('success')),
    array_fill(0, 10, child('error')),
    array_fill(0, 189, child('cancelled')),
);
$terminalAgg = Ms365BatchRunRepository::computeAggregates($allTerminal);
assert_near((float) $terminalAgg['progress_pct'], 100.0, 0.01, 'All-terminal batch shows 100% progress');
assert_true($terminalAgg['status'] === 'partial_success', 'All-terminal batch with mixed success+error aggregates to partial_success');

$singleSite = [child('running', 87.0, 1000, 870, 'kopia_upload')];
$singleAgg = Ms365BatchRunRepository::computeAggregates($singleSite);
assert_near((float) $singleAgg['progress_pct'], 87.0, 0.5, 'Single-workload batch uses item-weighted progress');

assert_true(
    Ms365BatchRunRepository::isParentStatusLocked(['status' => 'failed', 'cancel_requested' => 0]),
    'Terminal failed status is locked',
);
assert_true(
    Ms365BatchRunRepository::isParentStatusLocked(['status' => 'running', 'cancel_requested' => 1, 'finished_at' => '2026-06-18 22:16:07']),
    'Watchdog cancel pattern (cancel_requested + finished_at) is locked',
);
assert_true(
    !Ms365BatchRunRepository::isParentStatusLocked(['status' => 'running', 'cancel_requested' => 1]),
    'In-flight user cancel (no finished_at) is not locked',
);
assert_true(
    Ms365BatchRunRepository::isParentStatusLocked(['status' => 'cancelled', 'cancel_requested' => 1, 'finished_at' => '2026-06-18 22:16:07']),
    'Force-cancelled terminal status is locked',
);

exit($failures > 0 ? 1 : 0);
