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
assert_true((int) ($largeAgg['completed_workloads'] ?? 0) === 14, 'Large batch reports completed workload count');
assert_true((int) ($largeAgg['active_running_workloads'] ?? 0) === 5, 'Large batch reports active running workload count');
assert_true((int) ($largeAgg['total_workloads'] ?? 0) === 213, 'Large batch reports total workload count');

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

$speedEta = Ms365BatchRunRepository::computeSpeedAndEta(0, 100, 1_000_000_000, 10_000_000_000, 110);
assert_true(($speedEta['speed'] ?? 0) === 100_000_000, 'Speed uses bytes_processed delta');
assert_true(($speedEta['eta_seconds'] ?? 0) === 90, 'ETA based on remaining processed bytes');

$noSpeed = Ms365BatchRunRepository::computeSpeedAndEta(500, 100, 500, 1000, 105);
assert_true($noSpeed['speed'] === null, 'Zero byte delta yields null speed');

$now = time();
assert_true(
    Ms365BatchRunRepository::isWorkerAlive(['status' => 'running', 'last_progress_at' => $now - 60, 'updated_at' => $now - 600], ['status' => 'running', 'lease_expires_at' => 0], $now),
    'Recent last_progress_at counts as alive',
);
assert_true(
    !Ms365BatchRunRepository::isWorkerAlive(['status' => 'running', 'last_progress_at' => $now - 600, 'updated_at' => $now - 60], ['status' => 'running', 'lease_expires_at' => $now - 10], $now),
    'Stale last_progress_at with expired lease is not alive',
);
assert_true(
    Ms365BatchRunRepository::progressFreshnessAt(['last_progress_at' => $now - 120, 'updated_at' => $now - 30]) === $now - 120,
    'progressFreshnessAt prefers last_progress_at',
);
assert_true(
    Ms365BatchRunRepository::progressFreshnessAt(['updated_at' => $now - 90]) === $now - 90,
    'progressFreshnessAt falls back to updated_at',
);

$throttleChildren = [
    array_merge(child('running', 5.0, 0, 0, 'graph_sync'), ['stats_json' => json_encode(['graph_429_hits' => 12])]),
    child('success'),
];
$throttleAgg = Ms365BatchRunRepository::computeAggregates($throttleChildren);
assert_true((int) ($throttleAgg['graph_429_hits_total'] ?? 0) === 12, 'Batch aggregate sums graph_429_hits');
assert_true(empty($throttleAgg['byte_stats_comparable']), 'Graph sync workloads make byte stats incomparable');

$uploadChildren = [
    child('running', 80.0, 1000, 800, 'kopia_upload'),
    child('success'),
];
$uploadAgg = Ms365BatchRunRepository::computeAggregates($uploadChildren);
assert_true(!empty($uploadAgg['byte_stats_comparable']), 'Kopia upload workloads allow comparable byte stats');
assert_true(($uploadAgg['dominant_phase'] ?? '') === 'kopia_upload', 'Single kopia_upload child sets dominant_phase');

$mixedPhaseChildren = [
    child('running', 50.0, 100, 50, 'graph_sync'),
    child('running', 50.0, 100, 50, 'kopia_upload'),
    child('running', 50.0, 100, 50, 'kopia_upload'),
];
$mixedPhaseAgg = Ms365BatchRunRepository::computeAggregates($mixedPhaseChildren);
assert_true(($mixedPhaseAgg['dominant_phase'] ?? '') === 'kopia_upload', 'Dominant phase is majority running phase');

$itemsSpeed = Ms365BatchRunRepository::computeItemsSpeed(100, 100, 250, 110);
assert_true($itemsSpeed === 15, 'Items speed uses objects_transferred delta');

$throttleWindow = (new ReflectionClass(Ms365BatchRunRepository::class))
    ->getMethod('computeWindowedGraphThrottled');
$throttleWindow->setAccessible(true);
$windowNow = time();
$windowResult = $throttleWindow->invoke(null, ['ms365_graph_429_hits_total' => 10], 15, $windowNow);
assert_true(!empty($windowResult['throttled']), 'Windowed throttle activates on new 429 total');
$windowStale = $throttleWindow->invoke(
    null,
    ['ms365_graph_429_hits_total' => 15, 'ms365_graph_throttle_at' => $windowNow - 300],
    15,
    $windowNow
);
assert_true(empty($windowStale['throttled']), 'Windowed throttle clears after window expires');

exit($failures > 0 ? 1 : 0);
