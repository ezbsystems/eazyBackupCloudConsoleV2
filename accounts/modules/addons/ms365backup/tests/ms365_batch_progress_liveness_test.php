<?php
declare(strict_types=1);

/**
 * Upload-phase liveness on no_progress heartbeats (isUploadLikePhase).
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_batch_progress_liveness_test.php
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

function test_uuid(string $suffix): string
{
    $hex = substr(md5('ms365_batch_progress_liveness_' . $suffix . microtime(true)), 0, 32);

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
        'phase' => 'kopia_upload',
        'items_done' => 10,
        'items_total' => 100,
        'percent' => 10.0,
        'physical_key' => 'user:batch-liveness-test',
        'resource_type' => 'user',
        'resource_id' => 'user:batch-liveness-test',
        'graph_id' => 'batch-liveness-test',
        'user_display_name' => 'Batch Liveness Test',
        'backup_path' => '/tmp/ms365-batch-liveness-test',
        'created_at' => $now - 600,
        'updated_at' => $now - 600,
        'started_at' => $now - 600,
    ], $overrides);
    if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
        $row['last_progress_at'] = $now - 600;
    }
    Capsule::table('ms365_backup_runs')->insert($row);
}

function cleanupTestRows(array $runIds): void
{
    if ($runIds === []) {
        return;
    }
    Capsule::table('ms365_backup_runs')->whereIn('id', $runIds)->delete();
}

assert_true(Ms365BatchRunRepository::isUploadLikePhase('kopia_upload'), 'kopia_upload is upload-like');
assert_true(Ms365BatchRunRepository::isUploadLikePhase('upload'), 'upload is upload-like');
assert_true(!Ms365BatchRunRepository::isUploadLikePhase('graph_sync'), 'graph_sync is not upload-like');
assert_true(!Ms365BatchRunRepository::isUploadLikePhase('prior_snapshot'), 'prior_snapshot is not upload-like');

$runIds = [];
$now = time();

try {
    $uploadRunId = test_uuid('upload-liveness');
    $runIds[] = $uploadRunId;
    insertTestRun($uploadRunId, [
        'phase' => 'kopia_upload',
        'updated_at' => $now - 900,
    ]);

    Ms365RestoreWorkerHooks::onProgress($uploadRunId, [
        'phase' => 'kopia_upload',
        'no_progress' => true,
        'message' => 'heartbeat',
    ]);

    $after = BackupRunRepository::get($uploadRunId) ?? [];
    $lastProgress = (int) ($after['last_progress_at'] ?? 0);
    assert_true(
        ($after['status'] ?? '') === 'running'
        && (int) ($after['updated_at'] ?? 0) >= $now - 5
        && (!Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at') || $lastProgress >= $now - 5),
        'no_progress kopia_upload refreshes liveness without fatal',
    );

    $teamsRunId = test_uuid('teams-liveness');
    $runIds[] = $teamsRunId;
    insertTestRun($teamsRunId, [
        'phase' => 'graph_sync',
        'resource_type' => 'team',
        'physical_key' => 'team:teams-liveness-test',
        'percent' => 1.0,
        'items_done' => 0,
        'items_total' => 0,
        'updated_at' => $now - 900,
    ]);

    Ms365RestoreWorkerHooks::onProgress($teamsRunId, [
        'phase' => 'graph_sync',
        'no_progress' => true,
        'message' => 'heartbeat',
        'graph_requests' => 42,
    ]);

    $teamsAfter = BackupRunRepository::get($teamsRunId) ?? [];
    $teamsLastProgress = (int) ($teamsAfter['last_progress_at'] ?? 0);
    assert_true(
        (int) ($teamsAfter['updated_at'] ?? 0) >= $now - 5
        && (!Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at') || $teamsLastProgress >= $now - 5),
        'no_progress graph_sync refreshes liveness for long Teams enumeration',
    );
    $teamsStats = json_decode((string) ($teamsAfter['stats_json'] ?? ''), true) ?: [];
    assert_true((int) ($teamsStats['graph_requests'] ?? 0) === 42, 'no_progress graph_sync preserves graph_requests stats');

    $batchStyleRunId = test_uuid('teams-batch-snapshot');
    $runIds[] = $batchStyleRunId;
    insertTestRun($batchStyleRunId, [
        'phase' => 'graph_sync',
        'resource_type' => 'team',
        'physical_key' => 'team:batch-style-test',
        'percent' => 1.0,
        'items_done' => 0,
        'items_total' => 0,
        'updated_at' => $now - 900,
    ]);

    Ms365RestoreWorkerHooks::onProgress($batchStyleRunId, [
        'phase' => 'graph_sync',
        'percent' => 1.0,
        'items_done' => 0,
        'items_total' => 0,
        'message' => 'Graph sync: teams',
    ]);

    $batchAfter = BackupRunRepository::get($batchStyleRunId) ?? [];
    $batchLastProgress = (int) ($batchAfter['last_progress_at'] ?? 0);
    assert_true(
        (int) ($batchAfter['updated_at'] ?? 0) >= $now - 5
        && (!Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at') || $batchLastProgress >= $now - 5),
        'batch hub snapshot style graph_sync refreshes last_progress_at',
    );
} finally {
    cleanupTestRows($runIds);
}

exit($failures > 0 ? 1 : 0);
