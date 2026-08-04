<?php
declare(strict_types=1);

/**
 * Prior-attempt max() residue must not pin UI items/graph_requests above the current attempt.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_attempt_progress_residue_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupRunRepository;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365RestoreWorkerHooks;
use WHMCS\Database\Capsule;

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

function test_uuid(string $prefix): string
{
    return sprintf(
        '%s-%04x-%04x-%04x-%04x%08x',
        substr(preg_replace('/[^a-f0-9]/', '', md5($prefix . microtime(true))) ?: 'aaaaaaaa', 0, 8),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0xffffffff)
    );
}

$runId = test_uuid('residue');
$now = time();
$runIds = [$runId];

try {
    Capsule::table('ms365_backup_runs')->insert([
        'id' => $runId,
        'whmcs_client_id' => 1,
        'user_id' => 'residue-user',
        'user_upn' => 'residue@example.test',
        'user_display_name' => 'Residue Test',
        'resource_id' => 'drive:residue',
        'resource_type' => 'sharepoint_site',
        'graph_id' => 'residue-graph',
        'physical_key' => 'drive:residue',
        'status' => 'running',
        'phase' => 'graph_sync',
        'items_done' => 499635,
        'items_total' => 499635,
        'percent' => 99.0,
        'bytes_hashed' => 3027170309251,
        'bytes_uploaded' => 0,
        'engine_mode' => 'kopia',
        'stats_json' => json_encode([
            'graph_requests' => 3550,
            'attempt_progress_started_at' => $now - 100,
            'attempt_progress_phase' => 'graph_sync',
            'attempt_items_done' => 300000,
            'attempt_bytes_hashed' => 0,
            'attempt_bytes_uploaded' => 0,
            'attempt_graph_requests' => 1500,
        ], JSON_UNESCAPED_SLASHES),
        'created_at' => $now - 200,
        'started_at' => $now - 100,
        'updated_at' => $now - 5,
        'last_progress_at' => $now - 5,
    ]);

    $display = Ms365BatchRunRepository::attemptAwareProgressCounters(
        BackupRunRepository::get($runId) ?? []
    );
    assert_true(
        $display['items_done'] === 300000
        && $display['graph_requests'] === 1500
        && $display['bytes_hashed'] === 0,
        'attemptAwareProgressCounters hides prior-attempt residue'
    );

    Ms365RestoreWorkerHooks::onProgress($runId, [
        'phase' => 'graph_sync',
        'message' => 'Graph sync: documents',
        'percent' => 40.0,
        'items_done' => 310000,
        'items_total' => 499635,
        'bytes_hashed' => 0,
        'bytes_uploaded' => 0,
        'graph_requests' => 1600,
    ]);

    $after = BackupRunRepository::get($runId) ?? [];
    $stats = json_decode((string) ($after['stats_json'] ?? '{}'), true) ?: [];
    assert_true(
        (int) ($after['items_done'] ?? 0) === 499635,
        'backupProgress keeps high-water items_done for infra-preserved progress'
    );
    assert_true(
        (int) ($stats['graph_requests'] ?? 0) === 3550
        && (int) ($stats['attempt_graph_requests'] ?? 0) === 1600
        && (int) ($stats['attempt_items_done'] ?? 0) === 310000,
        'backupProgress advances attempt counters while keeping graph_requests high-water'
    );
    $displayAfter = Ms365BatchRunRepository::attemptAwareProgressCounters($after);
    assert_true(
        $displayAfter['items_done'] === 310000
        && $displayAfter['graph_requests'] === 1600,
        'UI counters follow attempt progress after backupProgress'
    );
} finally {
    Capsule::table('ms365_backup_runs')->whereIn('id', $runIds)->delete();
}

exit($failures > 0 ? 1 : 0);
