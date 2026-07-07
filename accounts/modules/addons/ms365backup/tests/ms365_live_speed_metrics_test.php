<?php
declare(strict_types=1);

/**
 * Unit tests for Ms365LiveSpeedMetrics (phase-aware EMA throughput).
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_live_speed_metrics_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365LiveSpeedMetrics;

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

function assert_eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message}\n";
        echo '  expected: ' . var_export($expected, true) . "\n";
        echo '  actual:   ' . var_export($actual, true) . "\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

/** @param array<string, mixed> $agg */
function agg(array $overrides = []): array
{
    return array_merge([
        'dominant_phase' => 'kopia_upload',
        'byte_stats_comparable' => true,
        'bytes_processed' => 0,
        'bytes_transferred' => 0,
        'objects_transferred' => 0,
        'graph_requests_total' => 0,
        'bytes_total' => 1_000_000_000,
    ], $overrides);
}

$now = 200;
$stats = [
    'ms365_last_ts' => 100,
    'ms365_last_bytes_processed' => 0,
    'ms365_last_bytes' => 0,
    'ms365_last_items' => 0,
    'ms365_speed_last_graph_requests' => 0,
];

$upload = Ms365LiveSpeedMetrics::update($stats, agg([
    'bytes_transferred' => 30_000_000,
]), $now);
assert_eq(Ms365LiveSpeedMetrics::KIND_UPLOAD, $upload['speed_metric_kind'], 'Upload delta selects upload kind');
assert_eq(300_000, $upload['speed_bytes_per_sec'], 'Upload instant on first sample');
assert_true($upload['speed_updated_at'] === $now, 'Upload sets speed_updated_at');

$stats2 = $upload['stats_json'];
$uploadEma = Ms365LiveSpeedMetrics::update($stats2, agg([
    'bytes_transferred' => 60_000_000,
]), $now + 10);
assert_eq(Ms365LiveSpeedMetrics::KIND_UPLOAD, $uploadEma['speed_metric_kind'], 'Continued upload stays upload kind');
assert_eq(1_110_000, $uploadEma['speed_bytes_per_sec'], 'Upload EMA smooths second sample');

$hashOnly = Ms365LiveSpeedMetrics::update([
    'ms365_last_ts' => 100,
    'ms365_last_bytes_processed' => 1_000_000,
    'ms365_last_bytes' => 5_000_000,
    'ms365_last_items' => 0,
    'ms365_speed_last_graph_requests' => 0,
], agg([
    'bytes_processed' => 11_000_000,
    'bytes_transferred' => 5_000_000,
]), $now);
assert_eq(Ms365LiveSpeedMetrics::KIND_HASH, $hashOnly['speed_metric_kind'], 'Flat transferred with growing processed uses hash');
assert_eq(100_000, $hashOnly['speed_bytes_per_sec'], 'Hash speed from processed delta');

$graphItems = Ms365LiveSpeedMetrics::update([
    'ms365_last_ts' => 100,
    'ms365_last_bytes_processed' => 0,
    'ms365_last_bytes' => 0,
    'ms365_last_items' => 100,
    'ms365_speed_last_graph_requests' => 0,
], agg([
    'dominant_phase' => 'graph_sync',
    'byte_stats_comparable' => false,
    'objects_transferred' => 250,
]), $now);
assert_eq(Ms365LiveSpeedMetrics::KIND_ITEMS, $graphItems['speed_metric_kind'], 'Graph phase items delta');
assert_eq(2, $graphItems['items_per_sec'], 'Items per sec EMA first sample');
assert_eq(null, $graphItems['speed_bytes_per_sec'], 'Items kind does not set byte speed column');

$graphReq = Ms365LiveSpeedMetrics::update([
    'ms365_last_ts' => 100,
    'ms365_last_bytes_processed' => 0,
    'ms365_last_bytes' => 0,
    'ms365_last_items' => 50,
    'ms365_speed_last_graph_requests' => 100,
], agg([
    'dominant_phase' => 'graph_sync',
    'byte_stats_comparable' => false,
    'objects_transferred' => 50,
    'graph_requests_total' => 250,
]), $now);
assert_eq(Ms365LiveSpeedMetrics::KIND_GRAPH_REQUESTS, $graphReq['speed_metric_kind'], 'Graph requests when items flat');
assert_eq(2, $graphReq['graph_requests_per_sec'], 'Graph requests per sec');

$flat = Ms365LiveSpeedMetrics::update([
    'ms365_last_ts' => 100,
    'ms365_last_bytes_processed' => 5_000_000,
    'ms365_last_bytes' => 5_000_000,
    'ms365_last_items' => 10,
    'ms365_speed_last_graph_requests' => 0,
    'ms365_speed_ema' => 9_999_999,
    'ms365_speed_metric_kind' => Ms365LiveSpeedMetrics::KIND_HASH,
    'ms365_speed_updated_at' => 150,
], agg([
    'bytes_processed' => 5_000_000,
    'bytes_transferred' => 5_000_000,
    'objects_transferred' => 10,
]), $now);
assert_eq(Ms365LiveSpeedMetrics::KIND_NONE, $flat['speed_metric_kind'], 'Zero delta clears kind');
assert_eq(null, $flat['speed_bytes_per_sec'], 'Zero delta clears byte speed');
assert_true($flat['stats_json']['ms365_speed_ema'] === null, 'Zero delta clears EMA');

$phaseSwitch = Ms365LiveSpeedMetrics::update([
    'ms365_last_ts' => 100,
    'ms365_last_bytes_processed' => 0,
    'ms365_last_bytes' => 0,
    'ms365_last_items' => 0,
    'ms365_speed_last_graph_requests' => 0,
    'ms365_speed_ema' => 500,
    'ms365_speed_metric_kind' => Ms365LiveSpeedMetrics::KIND_ITEMS,
], agg([
    'dominant_phase' => 'kopia_upload',
    'bytes_transferred' => 20_000_000,
]), $now);
assert_eq(Ms365LiveSpeedMetrics::KIND_UPLOAD, $phaseSwitch['speed_metric_kind'], 'Phase switch graph to upload');
assert_eq(200_000, $phaseSwitch['speed_bytes_per_sec'], 'EMA resets on kind change');

assert_eq('Upload speed', Ms365LiveSpeedMetrics::labelForKind(Ms365LiveSpeedMetrics::KIND_UPLOAD), 'Upload label');
assert_eq('Hash speed', Ms365LiveSpeedMetrics::labelForKind(Ms365LiveSpeedMetrics::KIND_HASH), 'Hash label');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
