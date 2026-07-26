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
use Ms365Backup\Ms365BatchClaimRepository;
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
    if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')
        && !array_key_exists('last_progress_at', $overrides)) {
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
assert_true(Ms365BatchRunRepository::isGraphBoundPhase('graph_sync'), 'graph_sync is graph-bound');
assert_true(Ms365BatchRunRepository::isGraphBoundPhase(''), 'empty phase is graph-bound');
assert_true(!Ms365BatchRunRepository::isGraphBoundPhase('prior_snapshot'), 'prior_snapshot is not graph-bound');

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
        && (!Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at') || $lastProgress < $now - 60),
        'no_progress kopia_upload renews lease without refreshing last_progress_at',
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

    $uploadPhaseRunId = test_uuid('upload-phase-guard');
    $runIds[] = $uploadPhaseRunId;
    insertTestRun($uploadPhaseRunId, [
        'phase' => 'upload',
        'percent' => 67.0,
        'items_done' => 5249,
        'items_total' => 5251,
        'bytes_uploaded' => 2199947564,
        'stats_json' => '{"kopia_upload_started_at":' . ($now - 3600) . ',"kopia_snapshot_ms":758000}',
        'updated_at' => $now - 120,
    ]);
    Ms365RestoreWorkerHooks::onProgress($uploadPhaseRunId, [
        'phase' => 'graph_sync',
        'percent' => 35.0,
        'items_done' => 5249,
        'items_total' => 5251,
        'message' => 'Graph sync: sharepoint',
    ]);
    $uploadGuard = BackupRunRepository::get($uploadPhaseRunId) ?? [];
    assert_true(
        ($uploadGuard['phase'] ?? '') === 'upload'
        && (float) ($uploadGuard['percent'] ?? 0) >= 67.0,
        'backupProgress ignores stale graph_sync after Kopia upload started',
    );

    // Soft-abort retry: sticky upload phase + leftover kopia bytes must not block the
    // worker from re-entering graph_sync and refreshing liveness (prod Documents loop).
    $stickyRetryRunId = test_uuid('sticky-upload-retry');
    $runIds[] = $stickyRetryRunId;
    insertTestRun($stickyRetryRunId, [
        'phase' => '',
        'percent' => 67.0,
        'items_done' => 498433,
        'items_total' => 498433,
        'bytes_hashed' => 90075130223,
        'bytes_uploaded' => 4511,
        'stats_json' => '{"kopia_upload_started_at":' . ($now - 7200) . ',"kopia_snapshot_ms":419000}',
        'started_at' => $now - 60,
        'last_progress_at' => $now - 60,
        'updated_at' => $now - 60,
    ]);
    Ms365RestoreWorkerHooks::onProgress($stickyRetryRunId, [
        'phase' => 'graph_sync',
        'percent' => 1.0,
        'items_done' => 498433,
        'items_total' => 498433,
        'message' => 'Graph sync: sharepoint',
    ]);
    $stickyAfter = BackupRunRepository::get($stickyRetryRunId) ?? [];
    $stickyPhase = strtolower(trim((string) ($stickyAfter['phase'] ?? '')));
    $stickyLp = (int) ($stickyAfter['last_progress_at'] ?? 0);
    assert_true(
        ($stickyPhase === 'graph_sync' || $stickyPhase === 'sync')
        && $stickyLp >= $now - 5,
        're-promoted sticky-upload child accepts graph_sync and refreshes last_progress_at',
    );

    // Graph → upload phase advance must refresh liveness so items-complete upload-tail
    // does not soft-abort during Kopia open before the first byte tick.
    $uploadAdvanceRunId = test_uuid('upload-phase-advance-lp');
    $runIds[] = $uploadAdvanceRunId;
    insertTestRun($uploadAdvanceRunId, [
        'phase' => 'graph_sync',
        'items_done' => 1000,
        'items_total' => 1000,
        'bytes_hashed' => 5000,
        'bytes_uploaded' => 0,
        'last_progress_at' => $now - 120,
        'updated_at' => $now - 120,
    ]);
    Ms365RestoreWorkerHooks::onProgress($uploadAdvanceRunId, [
        'phase' => 'kopia_upload',
        'percent' => 40.0,
        'items_done' => 1000,
        'items_total' => 1000,
        'bytes_hashed' => 5000,
        'bytes_uploaded' => 0,
        'message' => 'Uploading snapshot to Kopia repository',
    ]);
    $advanceAfter = BackupRunRepository::get($uploadAdvanceRunId) ?? [];
    assert_true(
        Ms365BatchRunRepository::isUploadLikePhase((string) ($advanceAfter['phase'] ?? ''))
        && (int) ($advanceAfter['last_progress_at'] ?? 0) >= $now - 5,
        'advancing to kopia_upload refreshes last_progress_at',
    );

    // Upload-tail reaper uses 900s, not the 180s UI Active window.
    assert_true(
        Ms365BatchRunRepository::UPLOAD_TAIL_STALE_SECONDS === 900,
        'upload-tail stale seconds aligned with worker upload_stall default',
    );

    $checkpointRunId = test_uuid('checkpoint-liveness');
    $runIds[] = $checkpointRunId;
    insertTestRun($checkpointRunId, [
        'phase' => 'graph_sync',
        'items_done' => 100,
        'items_total' => 100,
        'percent' => 35.0,
        'updated_at' => $now - 900,
        'last_progress_at' => $now - 900,
    ]);
    Ms365RestoreWorkerHooks::onProgress($checkpointRunId, [
        'phase' => 'graph_sync',
        'message' => 'graph_sync checkpoint',
        'items_done' => 100,
        'items_total' => 100,
        'percent' => 35.0,
        'checkpoint_delta_states' => ['mail' => ['folder1' => 'https://delta']],
    ]);
    $checkpointAfter = BackupRunRepository::get($checkpointRunId) ?? [];
    $checkpointLastProgress = (int) ($checkpointAfter['last_progress_at'] ?? 0);
    assert_true(
        $checkpointLastProgress >= $now - 5,
        'graph_sync checkpoint refreshes last_progress_at for throttled enumeration liveness',
    );

    // Regression: graph_sync with items complete + bytes_hashed must NOT use the 600s
    // upload-tail silence (that false-aborted active mail sync on live owners).
    $graphTailBatch = test_uuid('graph-tail-batch');
    $graphTailChild = test_uuid('graph-tail-child');
    $runIds[] = $graphTailChild;
    Capsule::table('ms365_batch_claims')->insert([
        'batch_run_id' => $graphTailBatch,
        'tenant_record_id' => 1,
        'status' => 'running',
        'worker_node_id' => 'liveness-test-node',
        'running_tenant_key' => 1,
        'priority' => 50,
        'attempts' => 1,
        'max_attempts' => 5,
        'claimed_at' => $now - 900,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
        'last_progress_at' => $now,
        'created_at' => $now - 900,
        'updated_at' => $now,
    ]);
    insertTestRun($graphTailChild, [
        'e3_batch_run_id' => $graphTailBatch,
        'phase' => 'graph_sync',
        'items_done' => 847,
        'items_total' => 847,
        'bytes_hashed' => 153818964,
        'bytes_uploaded' => 0,
        'percent' => 35.0,
        'updated_at' => $now - 700,
        'last_progress_at' => $now - 700,
    ]);
    Capsule::table('ms365_job_queue')->insert([
        'run_id' => $graphTailChild,
        'job_type' => 'backup',
        'status' => 'running',
        'priority' => 50,
        'attempts' => 1,
        'max_attempts' => 5,
        'scheduled_at' => $now - 900,
        'claimed_at' => $now - 900,
        'lease_expires_at' => $now + 600,
        'created_at' => $now - 900,
    ]);
    Ms365BatchClaimRepository::reapStalledBatchChildren();
    $graphTailAbort = Capsule::schema()->hasColumn('ms365_backup_runs', 'abort_requested_at')
        ? Capsule::table('ms365_backup_runs')->where('id', $graphTailChild)->value('abort_requested_at')
        : null;
    assert_true(
        Capsule::table('ms365_backup_runs')->where('id', $graphTailChild)->value('status') === 'running'
        && ($graphTailAbort === null || (int) $graphTailAbort <= 0),
        'graph_sync items-complete+bytes does not soft-abort at 700s silence on live owner',
    );
    Capsule::table('ms365_job_queue')->where('run_id', $graphTailChild)->delete();
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $graphTailBatch)->delete();

    // prior_snapshot hub-style posts must not refresh last_progress_at (wedged Kopia open).
    $priorHubRunId = test_uuid('prior-hub-liveness');
    $runIds[] = $priorHubRunId;
    insertTestRun($priorHubRunId, [
        'phase' => 'prior_snapshot',
        'percent' => 1.0,
        'items_done' => 0,
        'items_total' => 0,
        'updated_at' => $now - 900,
        'last_progress_at' => $now - 900,
    ]);
    Ms365RestoreWorkerHooks::onProgress($priorHubRunId, [
        'phase' => 'prior_snapshot',
        'no_progress' => true,
        'message' => 'heartbeat',
        'percent' => 1.0,
        'items_done' => 0,
        'items_total' => 0,
    ]);
    $priorHubAfter = BackupRunRepository::get($priorHubRunId) ?? [];
    $priorHubLastProgress = (int) ($priorHubAfter['last_progress_at'] ?? 0);
    assert_true(
        !Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at') || $priorHubLastProgress < $now - 60,
        'no_progress prior_snapshot hub heartbeat does not refresh last_progress_at',
    );

    $priorBatchStyleRunId = test_uuid('prior-batch-snapshot');
    $runIds[] = $priorBatchStyleRunId;
    insertTestRun($priorBatchStyleRunId, [
        'phase' => 'prior_snapshot',
        'percent' => 1.0,
        'items_done' => 0,
        'items_total' => 0,
        'updated_at' => $now - 900,
        'last_progress_at' => $now - 900,
    ]);
    Ms365RestoreWorkerHooks::onProgress($priorBatchStyleRunId, [
        'phase' => 'prior_snapshot',
        'percent' => 1.0,
        'items_done' => 0,
        'items_total' => 0,
        'message' => 'Prior snapshot: loading',
    ]);
    $priorBatchAfter = BackupRunRepository::get($priorBatchStyleRunId) ?? [];
    $priorBatchLastProgress = (int) ($priorBatchAfter['last_progress_at'] ?? 0);
    assert_true(
        (int) ($priorBatchAfter['updated_at'] ?? 0) >= $now - 5
        && (!Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at') || $priorBatchLastProgress < $now - 60),
        'batch hub snapshot style prior_snapshot does not refresh last_progress_at',
    );

    $priorCounterRunId = test_uuid('prior-counter-liveness');
    $runIds[] = $priorCounterRunId;
    insertTestRun($priorCounterRunId, [
        'phase' => 'prior_snapshot',
        'items_done' => 0,
        'items_total' => 100,
        'percent' => 0.0,
        'updated_at' => $now - 900,
        'last_progress_at' => $now - 900,
    ]);
    Ms365RestoreWorkerHooks::onProgress($priorCounterRunId, [
        'phase' => 'prior_snapshot',
        'items_done' => 5,
        'items_total' => 100,
        'percent' => 5.0,
        'message' => 'Prior snapshot: merging',
    ]);
    $priorCounterAfter = BackupRunRepository::get($priorCounterRunId) ?? [];
    $priorCounterLastProgress = (int) ($priorCounterAfter['last_progress_at'] ?? 0);
    assert_true(
        !Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at') || $priorCounterLastProgress >= $now - 5,
        'prior_snapshot items increase still refreshes last_progress_at',
    );

    $retryCounterRunId = test_uuid('retry-local-counter-liveness');
    $runIds[] = $retryCounterRunId;
    $retryStartedAt = $now - 60;
    insertTestRun($retryCounterRunId, [
        'phase' => 'upload',
        'items_done' => 500000,
        'items_total' => 500000,
        'bytes_hashed' => 120000000000,
        'bytes_uploaded' => 10000000,
        'started_at' => $retryStartedAt,
        'last_progress_at' => $now - 900,
        'updated_at' => $now - 900,
    ]);
    $lowerRetrySample = [
        'phase' => 'kopia_upload',
        'items_done' => 4000,
        'items_total' => 14000,
        'bytes_hashed' => 15000000000,
        'bytes_uploaded' => 500000,
        'message' => 'Uploading snapshot',
    ];
    Ms365RestoreWorkerHooks::onProgress($retryCounterRunId, $lowerRetrySample);
    Capsule::table('ms365_backup_runs')->where('id', $retryCounterRunId)->update([
        'last_progress_at' => $now - 900,
    ]);

    $growingRetrySample = $lowerRetrySample;
    $growingRetrySample['items_done'] = 4200;
    $growingRetrySample['bytes_hashed'] = 16000000000;
    Ms365RestoreWorkerHooks::onProgress($retryCounterRunId, $growingRetrySample);
    $retryAfterGrowth = BackupRunRepository::get($retryCounterRunId) ?? [];
    assert_true(
        (int) ($retryAfterGrowth['bytes_hashed'] ?? 0) === 120000000000
        && (int) ($retryAfterGrowth['items_done'] ?? 0) === 500000
        && (int) ($retryAfterGrowth['last_progress_at'] ?? 0) >= $now - 5,
        'retry-local upload growth refreshes liveness below preserved high-water counters',
    );

    Capsule::table('ms365_backup_runs')->where('id', $retryCounterRunId)->update([
        'last_progress_at' => $now - 900,
    ]);
    Ms365RestoreWorkerHooks::onProgress($retryCounterRunId, $growingRetrySample);
    $retryAfterReplay = BackupRunRepository::get($retryCounterRunId) ?? [];
    assert_true(
        (int) ($retryAfterReplay['last_progress_at'] ?? 0) < $now - 60,
        'identical retry-local upload sample does not hide a wedged upload',
    );
} finally {
    cleanupTestRows($runIds);
}

exit($failures > 0 ? 1 : 0);
