<?php
declare(strict_types=1);

/**
 * Orphan reclaim lease gate + backup completion item reconcile.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_orphan_lease_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupRunRepository;
use Ms365Backup\Ms365RestoreWorkerHooks;
use Ms365Backup\WorkerClaimService;
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

function test_uuid(string $suffix): string
{
    $hex = substr(md5('ms365_orphan_lease_test_' . $suffix), 0, 32);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

/** @param array<string, mixed> $overrides */
function insertTestRun(string $runId, array $overrides = []): void
{
    $now = time();
    $row = array_merge([
        'id' => $runId,
        'status' => 'running',
        'phase' => 'graph_sync',
        'items_done' => 5,
        'items_total' => 100,
        'percent' => 5.0,
        'physical_key' => 'user:test-orphan-lease',
        'resource_type' => 'user',
        'resource_id' => 'user:test-orphan-lease',
        'graph_id' => 'test-orphan-lease',
        'user_display_name' => 'Orphan Lease Test',
        'backup_path' => '/tmp/ms365-orphan-lease-test',
        'created_at' => $now,
        'updated_at' => $now - 300,
        'started_at' => $now - 300,
    ], $overrides);
    Capsule::table('ms365_backup_runs')->insert($row);
}

/** @param array<string, mixed> $overrides */
function insertTestQueue(string $runId, string $nodeId, array $overrides = []): void
{
    $now = time();
    $row = array_merge([
        'run_id' => $runId,
        'status' => 'running',
        'priority' => 100,
        'attempts' => 1,
        'max_attempts' => 5,
        'worker_node_id' => $nodeId,
        'claimed_at' => $now - 200,
        'lease_expires_at' => $now - 60,
        'scheduled_at' => $now - 200,
        'created_at' => $now - 200,
        'started_at' => $now - 200,
        'job_type' => 'backup',
    ], $overrides);
    Capsule::table('ms365_job_queue')->insert($row);
}

function cleanupTestRows(array $runIds): void
{
    if ($runIds === []) {
        return;
    }
    Capsule::table('ms365_job_queue')->whereIn('run_id', $runIds)->delete();
    Capsule::table('ms365_backup_runs')->whereIn('id', $runIds)->delete();
}

function queueStatus(string $runId): string
{
    return (string) Capsule::table('ms365_job_queue')->where('run_id', $runId)->value('status');
}

$nodeId = 'test-orphan-lease-node';
$now = time();
$runIds = [];

try {
    $completeRunId = test_uuid('complete');
    $runIds[] = $completeRunId;
    insertTestRun($completeRunId, [
        'items_done' => 226,
        'items_total' => 346,
        'percent' => 65.0,
        'updated_at' => $now - 30,
    ]);
    insertTestQueue($completeRunId, $nodeId, [
        'lease_expires_at' => $now + 3600,
        'claimed_at' => $now - 30,
    ]);
    Ms365RestoreWorkerHooks::onComplete($completeRunId, [
        'manifest_id' => 'test-manifest',
        'stats_json' => json_encode(['files' => 226, 'bytes_hashed' => 1000, 'bytes_uploaded' => 2000]),
    ]);
    $completed = BackupRunRepository::get($completeRunId) ?? [];
    assert_true(
        (int) ($completed['items_done'] ?? 0) === 346
        && (int) ($completed['items_total'] ?? 0) === 346
        && (float) ($completed['percent'] ?? 0) === 100.0
        && ($completed['status'] ?? '') === 'success',
        'backupComplete reconciles items_done/items_total to coherent 100%',
    );

    $clampRunId = test_uuid('clamp');
    $runIds[] = $clampRunId;
    insertTestRun($clampRunId, [
        'items_done' => 80,
        'items_total' => 100,
        'percent' => 80.0,
        'updated_at' => $now - 10,
    ]);
    insertTestQueue($clampRunId, $nodeId, ['lease_expires_at' => $now + 3600]);
    Ms365RestoreWorkerHooks::onProgress($clampRunId, [
        'phase' => 'graph_sync',
        'message' => 'syncing',
        'items_done' => 150,
        'items_total' => 100,
    ]);
    $clamped = BackupRunRepository::get($clampRunId) ?? [];
    assert_true(
        (int) ($clamped['items_done'] ?? 0) === 100
        && (int) ($clamped['items_total'] ?? 0) === 100,
        'backupProgress clamps items_done to items_total',
    );

    $emptyShardRunId = test_uuid('empty-shard');
    $runIds[] = $emptyShardRunId;
    insertTestRun($emptyShardRunId, [
        'status' => 'running',
        'phase' => 'graph_sync',
        'physical_key' => 'drive:b!empty-shard-test',
        'items_done' => 0,
        'items_total' => 0,
        'percent' => 1.0,
        'updated_at' => $now - 300,
    ]);
    insertTestQueue($emptyShardRunId, $nodeId, ['lease_expires_at' => $now + 3600]);
    Ms365RestoreWorkerHooks::onComplete($emptyShardRunId, [
        'manifest_id' => '',
        'stats_json' => '{"status":"no_changes"}',
    ]);
    $emptyShard = BackupRunRepository::get($emptyShardRunId) ?? [];
    assert_true(
        ($emptyShard['status'] ?? '') === 'success'
        && ($emptyShard['phase'] ?? '') === 'complete'
        && trim((string) ($emptyShard['manifest_id'] ?? '')) === '',
        'backupComplete accepts no_changes graph_sync shard without manifest',
    );

    $revertGuardRunId = test_uuid('revert-guard');
    $runIds[] = $revertGuardRunId;
    insertTestRun($revertGuardRunId, [
        'status' => 'success',
        'phase' => 'complete',
        'percent' => 100.0,
        'finished_at' => $now - 60,
        'stats_json' => '{"status":"no_changes"}',
        'updated_at' => $now - 60,
    ]);
    insertTestQueue($revertGuardRunId, $nodeId, ['lease_expires_at' => $now + 3600]);
    Ms365RestoreWorkerHooks::onProgress($revertGuardRunId, [
        'phase' => 'graph_sync',
        'message' => 'Graph sync: done',
        'percent' => 100.0,
        'items_done' => 0,
        'items_total' => 0,
    ]);
    $revertGuard = BackupRunRepository::get($revertGuardRunId) ?? [];
    assert_true(
        ($revertGuard['status'] ?? '') === 'success'
        && ($revertGuard['phase'] ?? '') === 'complete'
        && (int) ($revertGuard['finished_at'] ?? 0) > 0,
        'backupProgress ignores stale batch hub graph_sync after no_changes complete',
    );
} finally {
    cleanupTestRows($runIds);
}

exit($failures > 0 ? 1 : 0);
