<?php
declare(strict_types=1);

/**
 * Tenant-batch claim repository and batch progress fan-out.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_batch_claim_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupRunRepository;
use Ms365Backup\DeltaStateRepository;
use Ms365Backup\KopiaRepoBootstrapService;
use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\Ms365EngineConfig;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365RestoreWorkerHooks;
use Ms365Backup\TenantRecordRepository;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerLeaseService;
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
    $hex = substr(md5('ms365_batch_claim_test_' . $suffix . microtime(true)), 0, 32);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

function ensureBatchClaimsTable(): void
{
    if (Capsule::schema()->hasTable('ms365_batch_claims')) {
        return;
    }
    $sqlFile = dirname(__DIR__) . '/sql/upgrade_phase22_tenant_owner.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('Missing upgrade_phase22_tenant_owner.sql');
    }
    $sql = file_get_contents($sqlFile);
    if (!is_string($sql) || $sql === '') {
        throw new RuntimeException('Empty migration SQL');
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '') {
            Capsule::connection()->statement($statement);
        }
    }
}

/** @param array<string, mixed> $overrides */
function insertTestRun(string $runId, array $overrides = []): void
{
    $now = time();
    $row = array_merge([
        'id' => $runId,
        'status' => 'queued',
        'phase' => 'queued',
        'items_done' => 0,
        'items_total' => 100,
        'percent' => 0.0,
        'physical_key' => 'user:batch-claim-test',
        'resource_type' => 'user',
        'resource_id' => 'user:batch-claim-test',
        'graph_id' => 'batch-claim-test',
        'user_display_name' => 'Batch Claim Test',
        'backup_path' => '/tmp/ms365-batch-claim-test',
        'tenant_record_id' => 999001,
        'whmcs_client_id' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ], $overrides);
    Capsule::table('ms365_backup_runs')->insert($row);
}

/** @param array<string, mixed> $overrides */
function insertTestQueue(string $runId, array $overrides = []): void
{
    $now = time();
    $row = array_merge([
        'run_id' => $runId,
        'status' => 'queued',
        'priority' => 50,
        'attempts' => 0,
        'max_attempts' => 5,
        'scheduled_at' => $now,
        'created_at' => $now,
        'job_type' => 'backup',
    ], $overrides);
    Capsule::table('ms365_job_queue')->insert($row);
}

function cleanupBatchTestRows(array $batchRunIds, array $runIds): void
{
    if ($batchRunIds !== []) {
        Capsule::table('ms365_batch_claims')->whereIn('batch_run_id', $batchRunIds)->delete();
    }
    if ($runIds !== []) {
        Capsule::table('ms365_job_queue')->whereIn('run_id', $runIds)->delete();
        Capsule::table('ms365_backup_runs')->whereIn('id', $runIds)->delete();
    }
}

/** @param array<string, mixed> $payload */
function normalizePayloadForCompare(array $payload): array
{
    unset($payload['graph_token'], $payload['graph_tenant_budget'], $payload['lease_expires_at']);
    if (isset($payload['delta_states']) && $payload['delta_states'] instanceof stdClass) {
        $payload['delta_states'] = (array) $payload['delta_states'];
    }
    if (isset($payload['scope']) && is_object($payload['scope'])) {
        $payload['scope'] = (array) $payload['scope'];
    }
    ksort($payload);

    return $payload;
}

/** @param array<string, mixed> $expected @param array<string, mixed> $actual */
function assert_payload_golden(array $expected, array $actual, string $message): void
{
    $normExpected = normalizePayloadForCompare($expected);
    $normActual = normalizePayloadForCompare($actual);
    assert_true($normExpected === $normActual, $message);
}

ensureBatchClaimsTable();
assert_true(Ms365BatchClaimRepository::tableReady(), 'ms365_batch_claims table exists');

assert_true(Ms365EngineConfig::batchHeartbeatGapSeconds() === 180, 'batchHeartbeatGapSeconds default is 180');
assert_true(Ms365EngineConfig::maxBatchesPerNode() === 1, 'maxBatchesPerNode default is 1');
assert_true(Ms365EngineConfig::batchMaxAttempts() === 5, 'batchMaxAttempts default is 5');

$batchRunIds = [];
$runIds = [];
$nodeA = 'test-batch-claim-node-a';
$nodeB = 'test-batch-claim-node-b';
$tenantRecordId = 999001;
$now = time();

try {
    $batch1 = test_uuid('batch-1');
    $batch2 = test_uuid('batch-2');
    $batchRunIds[] = $batch1;
    $batchRunIds[] = $batch2;
    $run1 = test_uuid('child-1');
    $run2 = test_uuid('child-2');
    $runIds[] = $run1;
    $runIds[] = $run2;

    Ms365BatchClaimRepository::enqueueBatch($batch1, $tenantRecordId, 50);
    Ms365BatchClaimRepository::enqueueBatch($batch2, $tenantRecordId, 60);

    insertTestRun($run1, ['e3_batch_run_id' => $batch1]);
    insertTestQueue($run1);
    insertTestRun($run2, ['e3_batch_run_id' => $batch2]);
    insertTestQueue($run2);

    $claimed1 = Ms365BatchClaimRepository::claimForNode($nodeA);
    assert_true($claimed1 !== null && ($claimed1['batch_run_id'] ?? '') === $batch1, 'node A claims first queued batch');

    $activeBatchIds = Ms365BatchClaimRepository::activeBatchRunIdsForNode($nodeA);
    assert_true(in_array($batch1, $activeBatchIds, true), 'activeBatchRunIdsForNode returns running batch id');

    $queueStatusAfterClaim = (string) Capsule::table('ms365_job_queue')->where('run_id', $run1)->value('status');
    $runStatusAfterClaim = (string) Capsule::table('ms365_backup_runs')->where('id', $run1)->value('status');
    assert_true($queueStatusAfterClaim === 'queued', 'child queue remains queued after batch claim');
    assert_true($runStatusAfterClaim === 'queued', 'child run remains queued after batch claim');

    Ms365RestoreWorkerHooks::onBatchProgress($batch1, $nodeA, [
        ['run_id' => $run1, 'phase' => 'graph_sync', 'message' => 'syncing', 'items_done' => 1, 'items_total' => 100],
    ]);
    $queueStatusAfterProgress = (string) Capsule::table('ms365_job_queue')->where('run_id', $run1)->value('status');
    $runStatusAfterProgress = (string) Capsule::table('ms365_backup_runs')->where('id', $run1)->value('status');
    assert_true($queueStatusAfterProgress === 'running', 'child queue promotes to running on first onBatchProgress');
    assert_true($runStatusAfterProgress === 'running', 'child run promotes to running on first onBatchProgress');

    $claimed2 = Ms365BatchClaimRepository::claimForNode($nodeB);
    assert_true($claimed2 === null, 'second tenant batch blocked while first is running (single-owner)');

    $duplicate = Ms365BatchClaimRepository::claimForNode($nodeB);
    assert_true($duplicate === null, 'atomic claim does not hand same tenant to another node');

    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batch1)
        ->update([
            'last_heartbeat_at' => $now - Ms365EngineConfig::batchHeartbeatGapSeconds() - 10,
            'lease_expires_at' => $now - 10,
            'attempts' => 1,
            'max_attempts' => 5,
        ]);
    $reaped = Ms365BatchClaimRepository::reapStaleBatches();
    assert_true($reaped >= 1, 'batch reaper requeues stale heartbeat batch');
    $statusAfterReap = (string) Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batch1)
        ->value('status');
    assert_true($statusAfterReap === 'queued', 'stale batch returns to queued with attempts headroom');

    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batch1)
        ->update([
            'status' => 'running',
            'worker_node_id' => $nodeA,
            'running_tenant_key' => $tenantRecordId,
            'attempts' => 5,
            'max_attempts' => 5,
            'last_heartbeat_at' => $now - Ms365EngineConfig::batchHeartbeatGapSeconds() - 10,
            'lease_expires_at' => $now - 10,
        ]);
    Ms365BatchClaimRepository::reapStaleBatches();
    $terminalStatus = (string) Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batch1)
        ->value('status');
    assert_true($terminalStatus === 'failed', 'batch reaper terminal-fails exhausted attempts');

    $siblingBatch = test_uuid('sibling-batch');
    $activeChild = test_uuid('active-child');
    $staleChild = test_uuid('stale-child');
    $runIds[] = $activeChild;
    $runIds[] = $staleChild;
    Ms365BatchClaimRepository::enqueueBatch($siblingBatch, $tenantRecordId, 55);
    insertTestRun($activeChild, [
        'e3_batch_run_id' => $siblingBatch,
        'status' => 'running',
        'phase' => 'graph_sync',
        'last_progress_at' => $now,
        'updated_at' => $now,
    ]);
    insertTestQueue($activeChild, ['status' => 'running', 'lease_expires_at' => $now + 600]);
    insertTestRun($staleChild, [
        'e3_batch_run_id' => $siblingBatch,
        'status' => 'running',
        'phase' => 'graph_sync',
        'last_progress_at' => $now - Ms365BatchRunRepository::STALE_SILENCE_SECONDS - 60,
        'updated_at' => $now - Ms365BatchRunRepository::STALE_SILENCE_SECONDS - 60,
    ]);
    insertTestQueue($staleChild, ['status' => 'running', 'lease_expires_at' => $now + 600]);
    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $siblingBatch)
        ->update([
            'status' => 'running',
            'worker_node_id' => $nodeA,
            'running_tenant_key' => $tenantRecordId,
            'claimed_at' => $now,
            'lease_expires_at' => $now + 600,
            'last_heartbeat_at' => $now,
        ]);
    $childReaped = Ms365BatchClaimRepository::reapStalledBatchChildren();
    assert_true($childReaped >= 1, 'reapStalledBatchChildren soft-aborts silent child under live owner');
    $staleStatus = (string) Capsule::table('ms365_backup_runs')->where('id', $staleChild)->value('status');
    $activeStatus = (string) Capsule::table('ms365_backup_runs')->where('id', $activeChild)->value('status');
    // Live owners soft-abort first; requeue only after REQUEUE_AFTER_SECONDS grace.
    if ($staleStatus === 'running') {
        $abortAt = (int) Capsule::table('ms365_backup_runs')->where('id', $staleChild)->value('abort_requested_at');
        assert_true($abortAt > 0, 'silent child receives abort_requested_at under live owner');
        Capsule::table('ms365_backup_runs')->where('id', $staleChild)->update([
            'abort_requested_at' => $now - 120,
        ]);
        $childReaped = Ms365BatchClaimRepository::reapStalledBatchChildren();
        assert_true($childReaped >= 1, 'reapStalledBatchChildren requeues after abort grace');
        $staleStatus = (string) Capsule::table('ms365_backup_runs')->where('id', $staleChild)->value('status');
    }
    assert_true($staleStatus === 'queued', 'stale child returns to queued');
    assert_true($activeStatus === 'running', 'active sibling child stays running');
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $siblingBatch)->delete();

    $handoffBatch = test_uuid('handoff-batch');
    $strandedChild = test_uuid('stranded-child');
    $activeChild = test_uuid('handoff-active');
    $batchRunIds[] = $handoffBatch;
    $runIds[] = $strandedChild;
    $runIds[] = $activeChild;
    Ms365BatchClaimRepository::enqueueBatch($handoffBatch, $tenantRecordId, 50);
    // Claimed first; child requeued afterward (scheduled_at > claimed_at) and aged past grace.
    $claimedAt = $now - 900;
    insertTestRun($strandedChild, [
        'e3_batch_run_id' => $handoffBatch,
        'status' => 'queued',
        'phase' => 'upload',
        'bytes_uploaded' => 1000,
        'updated_at' => $now - 600,
    ]);
    insertTestQueue($strandedChild, ['status' => 'queued', 'scheduled_at' => $now - 600]);
    insertTestRun($activeChild, [
        'e3_batch_run_id' => $handoffBatch,
        'status' => 'running',
        'phase' => 'upload',
        'updated_at' => $now,
        'last_progress_at' => $now,
    ]);
    insertTestQueue($activeChild, ['status' => 'running', 'lease_expires_at' => $now + 600]);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $handoffBatch)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $claimedAt,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
    ]);
    $handed = Ms365BatchClaimRepository::reconcileStrandedBatchQueuedChildren();
    assert_true($handed === 0, 'stranded queued child waits while active sibling is still progressing');
    $handoffStatus = (string) Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $handoffBatch)
        ->value('status');
    assert_true($handoffStatus === 'running', 'active owner keeps claim while sibling is progressing');
    $activeAfter = (string) Capsule::table('ms365_backup_runs')->where('id', $activeChild)->value('status');
    assert_true($activeAfter === 'running', 'active child remains running while retry waits');
    Capsule::table('ms365_backup_runs')->where('id', $activeChild)->update([
        'status' => 'success',
        'phase' => 'complete',
        'finished_at' => $now,
        'updated_at' => $now,
    ]);
    Capsule::table('ms365_job_queue')->where('run_id', $activeChild)->update([
        'status' => 'done',
        'worker_node_id' => null,
        'lease_expires_at' => null,
    ]);
    $handedAfterActive = Ms365BatchClaimRepository::reconcileStrandedBatchQueuedChildren();
    assert_true($handedAfterActive >= 1, 'stranded queued child triggers hand-off after active siblings finish');
    $handoffAfterActiveStatus = (string) Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $handoffBatch)
        ->value('status');
    assert_true($handoffAfterActiveStatus === 'queued', 'idle batch claim hands off for retry payload refresh');

    // Live-heartbeating owner during prior-snapshot (children still queued) must NOT be handed off.
    $priorMergeBatch = test_uuid('prior-merge-batch');
    $priorMergeChild = test_uuid('prior-merge-child');
    $batchRunIds[] = $priorMergeBatch;
    $runIds[] = $priorMergeChild;
    Ms365BatchClaimRepository::enqueueBatch($priorMergeBatch, $tenantRecordId, 50);
    insertTestRun($priorMergeChild, [
        'e3_batch_run_id' => $priorMergeBatch,
        'status' => 'queued',
    ]);
    insertTestQueue($priorMergeChild, ['status' => 'queued']);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $priorMergeBatch)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now - 240,
        'last_heartbeat_at' => $now - 30,
        'last_progress_at' => $now - 240,
        'lease_expires_at' => $now + 600,
        'updated_at' => $now,
    ]);
    $priorHanded = Ms365BatchClaimRepository::reconcileIdleOwnedQueuedBatches();
    $priorClaimStatus = (string) Capsule::table('ms365_batch_claims')->where('batch_run_id', $priorMergeBatch)->value('status');
    assert_true($priorHanded === 0, 'live heartbeat owner during prior-merge is not idle-handed-off');
    assert_true($priorClaimStatus === 'running', 'prior-merge claim stays running under fresh heartbeat');
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $priorMergeBatch)->delete();

    // Claim-time semaphore waiters (scheduled_at <= claimed_at) must not thrash the claim.
    $semBatch = test_uuid('sem-wait-batch');
    $semQueued = test_uuid('sem-wait-child');
    $semRunning = test_uuid('sem-run-child');
    $batchRunIds[] = $semBatch;
    $runIds[] = $semQueued;
    $runIds[] = $semRunning;
    Ms365BatchClaimRepository::enqueueBatch($semBatch, $tenantRecordId, 50);
    insertTestRun($semQueued, [
        'e3_batch_run_id' => $semBatch,
        'status' => 'queued',
        'updated_at' => $now - 600,
    ]);
    insertTestQueue($semQueued, ['status' => 'queued', 'scheduled_at' => $now - 600]);
    insertTestRun($semRunning, [
        'e3_batch_run_id' => $semBatch,
        'status' => 'running',
        'updated_at' => $now,
        'last_progress_at' => $now,
    ]);
    insertTestQueue($semRunning, ['status' => 'running', 'lease_expires_at' => $now + 600]);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $semBatch)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now - 120,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
    ]);
    $semHanded = Ms365BatchClaimRepository::reconcileStrandedBatchQueuedChildren();
    assert_true($semHanded === 0, 'claim-time queued semaphore waiters do not hand off');
    $semStatus = (string) Capsule::table('ms365_batch_claims')->where('batch_run_id', $semBatch)->value('status');
    assert_true($semStatus === 'running', 'batch claim stays running for claim-time queue');
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $semBatch)->delete();

    // Idle owner (0 running, only queued) after grace → hand off even when scheduled_at <= claimed_at.
    $idleBatch = test_uuid('idle-owned-batch');
    $idleChild = test_uuid('idle-owned-child');
    $batchRunIds[] = $idleBatch;
    $runIds[] = $idleChild;
    Ms365BatchClaimRepository::enqueueBatch($idleBatch, $tenantRecordId, 50);
    insertTestRun($idleChild, [
        'e3_batch_run_id' => $idleBatch,
        'status' => 'queued',
        'updated_at' => $now - 300,
    ]);
    insertTestQueue($idleChild, ['status' => 'queued', 'scheduled_at' => $now - 600]);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $idleBatch)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now - 30,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
        'attempts' => 1,
        'max_attempts' => 5,
    ]);
    $freshIdle = Ms365BatchClaimRepository::reconcileIdleOwnedQueuedBatches();
    assert_true($freshIdle === 0, 'idle owned hand-off waits for grace period');
    $freshIdleStatus = (string) Capsule::table('ms365_batch_claims')->where('batch_run_id', $idleBatch)->value('status');
    assert_true($freshIdleStatus === 'running', 'fresh idle claim stays running inside grace');
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $idleBatch)->update([
        'claimed_at' => $now - 300,
        // Fresh heartbeat: owner is alive (e.g. prior-snapshot merge) — do not hand off.
        'last_heartbeat_at' => $now - 30,
    ]);
    $aliveIdle = Ms365BatchClaimRepository::reconcileIdleOwnedQueuedBatches();
    assert_true($aliveIdle === 0, 'idle-looking claim with fresh heartbeat is not handed off');
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $idleBatch)->update([
        // Stale heartbeat: owner is truly wedged — hand off.
        'last_heartbeat_at' => $now - Ms365EngineConfig::batchHeartbeatGapSeconds() - 10,
    ]);
    $idleHanded = Ms365BatchClaimRepository::reconcileIdleOwnedQueuedBatches();
    assert_true($idleHanded >= 1, 'idle owned batch with only queued children hands off after grace');
    $idleStatus = (string) Capsule::table('ms365_batch_claims')->where('batch_run_id', $idleBatch)->value('status');
    assert_true($idleStatus === 'queued', 'idle owned claim returns to queued for another worker');

    // Exhausted attempts with pending children: reset budget and continue (do not
    // mid-run fail — that caused ghost reconcile cancels on prod f4bee1e7).
    $exBatch = test_uuid('exhausted-resume-batch');
    $exChild = test_uuid('exhausted-resume-child');
    $batchRunIds[] = $exBatch;
    $runIds[] = $exChild;
    Ms365BatchClaimRepository::enqueueBatch($exBatch, $tenantRecordId, 50);
    insertTestRun($exChild, ['e3_batch_run_id' => $exBatch, 'status' => 'queued']);
    insertTestQueue($exChild, ['status' => 'queued', 'scheduled_at' => $now]);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $exBatch)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now - 60,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
        'attempts' => 5,
        'max_attempts' => 5,
    ]);
    // buildBatchPayload needs tenant context; stub may return null payload — assert claim state.
    WorkerClaimService::resumeOwnedRunningBatch($nodeA);
    $exStatus = (string) Capsule::table('ms365_batch_claims')->where('batch_run_id', $exBatch)->value('status');
    $exAttempts = (int) Capsule::table('ms365_batch_claims')->where('batch_run_id', $exBatch)->value('attempts');
    assert_true($exStatus === 'running', 'exhausted resume with pending children keeps claim running');
    assert_true($exAttempts === 1, 'exhausted resume with pending children resets attempt budget');
    $exChildStatus = (string) Capsule::table('ms365_backup_runs')->where('id', $exChild)->value('status');
    assert_true($exChildStatus === 'queued', 'exhausted resume with pending children does not fail children');
    // Free nodeA before the no-pending exhausted case (getRunningForNode returns one row).
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $exBatch)->update([
        'status' => 'done',
        'worker_node_id' => null,
        'running_tenant_key' => null,
    ]);

    // Exhausted attempts with no pending children: terminal-fail to free the worker.
    $exDoneBatch = test_uuid('exhausted-done-batch');
    $exDoneChild = test_uuid('exhausted-done-child');
    $batchRunIds[] = $exDoneBatch;
    $runIds[] = $exDoneChild;
    Ms365BatchClaimRepository::enqueueBatch($exDoneBatch, $tenantRecordId, 50);
    insertTestRun($exDoneChild, ['e3_batch_run_id' => $exDoneBatch, 'status' => 'success']);
    insertTestQueue($exDoneChild, ['status' => 'done', 'scheduled_at' => $now]);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $exDoneBatch)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now - 60,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
        'attempts' => 5,
        'max_attempts' => 5,
    ]);
    $resumedDone = WorkerClaimService::resumeOwnedRunningBatch($nodeA);
    assert_true($resumedDone === null, 'exhausted resume with no pending children returns null');
    $exDoneStatus = (string) Capsule::table('ms365_batch_claims')->where('batch_run_id', $exDoneBatch)->value('status');
    assert_true($exDoneStatus === 'failed', 'exhausted resume with no pending children terminal-fails');

    Capsule::table('ms365_batch_claims')->where('batch_run_id', $batch1)->delete();
    Ms365BatchClaimRepository::enqueueBatch($batch1, $tenantRecordId, 50);
    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batch1)
        ->update([
            'status' => 'running',
            'worker_node_id' => $nodeA,
            'running_tenant_key' => $tenantRecordId,
            'claimed_at' => $now,
            'lease_expires_at' => $now + 60,
            'last_heartbeat_at' => $now,
            'attempts' => 1,
            'max_attempts' => 5,
        ]);
    Capsule::table('ms365_job_queue')
        ->where('run_id', $run1)
        ->update(['status' => 'running', 'worker_node_id' => $nodeA]);

    $renewed = WorkerLeaseService::renewForBatch($batch1, $nodeA);
    assert_true($renewed, 'renewForBatch extends batch lease');
    $leaseAfter = (int) Ms365BatchClaimRepository::leaseExpiresAt($batch1);
    assert_true($leaseAfter > $now + 30, 'batch lease_expires_at moved forward');

    $liveLease = Ms365BatchClaimRepository::liveBatchLeaseForChildRun($run1);
    assert_true($liveLease !== null && ($liveLease['batch_run_id'] ?? '') === $batch1, 'live batch lease resolves for child run_id');

    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batch1)
        ->update(['status' => 'done', 'worker_node_id' => null, 'running_tenant_key' => null, 'lease_expires_at' => null]);

    $batchPartial = test_uuid('batch-partial');
    $runPartialDone = test_uuid('child-partial-done');
    $runPartialQueued = test_uuid('child-partial-queued');
    $batchRunIds[] = $batchPartial;
    $runIds[] = $runPartialDone;
    $runIds[] = $runPartialQueued;
    Ms365BatchClaimRepository::enqueueBatch($batchPartial, $tenantRecordId, 50);
    insertTestRun($runPartialDone, ['e3_batch_run_id' => $batchPartial, 'status' => 'running']);
    insertTestQueue($runPartialDone, ['status' => 'running', 'worker_node_id' => $nodeA]);
    insertTestRun($runPartialQueued, ['e3_batch_run_id' => $batchPartial]);
    insertTestQueue($runPartialQueued);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $batchPartial)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now,
        'lease_expires_at' => $now + 3600,
        'last_heartbeat_at' => $now,
        'attempts' => 1,
    ]);
    Ms365RestoreWorkerHooks::onBatchComplete($batchPartial, $nodeA, [
        ['run_id' => $runPartialDone, 'manifest_id' => 'm1', 'stats_json' => '{"status":"no_changes"}'],
    ]);
    $claimStatusAfterPartial = (string) Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batchPartial)
        ->value('status');
    assert_true($claimStatusAfterPartial === 'running', 'partial onBatchComplete keeps batch claim running');
    assert_true(
        Ms365BatchClaimRepository::hasLiveLease($batchPartial, $nodeA),
        'partial onBatchComplete preserves live batch lease'
    );

    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batchPartial)
        ->update(['status' => 'done', 'worker_node_id' => null, 'running_tenant_key' => null, 'lease_expires_at' => null]);

    $batchFailGraph = test_uuid('batch-fail-graph');
    $runFailGraph = test_uuid('child-fail-graph');
    $batchRunIds[] = $batchFailGraph;
    $runIds[] = $runFailGraph;
    Ms365BatchClaimRepository::enqueueBatch($batchFailGraph, $tenantRecordId, 50);
    insertTestRun($runFailGraph, [
        'e3_batch_run_id' => $batchFailGraph,
        'status' => 'running',
        'phase' => 'graph_sync',
        'resource_type' => 'team',
        'physical_key' => 'team:fail-graph-test',
    ]);
    insertTestQueue($runFailGraph, [
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'attempts' => 3,
        'max_attempts' => 3,
    ]);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $batchFailGraph)->update([
        'status' => 'running',
        'worker_node_id' => $nodeA,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now,
        'lease_expires_at' => $now + 3600,
        'last_heartbeat_at' => $now,
        'attempts' => 1,
    ]);
    Ms365RestoreWorkerHooks::onBatchComplete($batchFailGraph, $nodeA, [[
        'run_id' => $runFailGraph,
        'status' => 'failed',
        'message' => 'teams: graph 400 Bad Request: Query option \'Top\' is not allowed.',
    ]]);
    $failedRun = BackupRunRepository::get($runFailGraph) ?? [];
    assert_true(
        in_array((string) ($failedRun['status'] ?? ''), ['error', 'failed'], true),
        'onBatchComplete failed child during graph_sync marks run terminal'
    );

    Capsule::table('ms365_job_queue')
        ->where('run_id', $run1)
        ->update(['status' => 'failed']);
    BackupRunRepository::update($run1, ['status' => 'error']);
    $inactiveToken = WorkerClaimService::refreshGraphTokenForRun($run1);
    assert_true(isset($inactiveToken['retry_after']), 'inactive run without batch lease returns retry_after not 500');

    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $batch1)
        ->update([
            'status' => 'running',
            'worker_node_id' => $nodeA,
            'running_tenant_key' => $tenantRecordId,
            'lease_expires_at' => $now + 3600,
            'last_heartbeat_at' => $now,
        ]);
    Capsule::table('ms365_job_queue')
        ->where('run_id', $run1)
        ->update(['status' => 'running', 'worker_node_id' => $nodeA]);
    BackupRunRepository::update($run1, ['status' => 'running', 'items_done' => 0, 'items_total' => 100]);

    $budget = Ms365RestoreWorkerHooks::onBatchProgress($batch1, $nodeA, [
        ['run_id' => $run1, 'phase' => 'graph_sync', 'message' => 'syncing', 'items_done' => 10, 'items_total' => 100],
    ]);
    assert_true($budget >= 0, 'batched progress completes and returns budget hint');
    $updated = BackupRunRepository::get($run1) ?? [];
    assert_true((int) ($updated['items_done'] ?? 0) === 10, 'batched progress fan-out updates child run');

    $queueLease = (int) Capsule::table('ms365_job_queue')->where('run_id', $run1)->value('lease_expires_at');
    $beforeChildRenew = $queueLease;
    Ms365RestoreWorkerHooks::onBatchProgress($batch1, $nodeA, [
        ['run_id' => $run1, 'phase' => 'graph_sync', 'message' => 'syncing', 'items_done' => 11, 'items_total' => 100],
    ]);
    $afterChildRenew = (int) Capsule::table('ms365_job_queue')->where('run_id', $run1)->value('lease_expires_at');
    assert_true($afterChildRenew === $beforeChildRenew, 'batch progress does not renew per-child queue lease');
} finally {
    cleanupBatchTestRows($batchRunIds, $runIds);
}

$payloadBatchRunIds = [];
$payloadRunIds = [];
$payloadTenantRecordId = 1;

try {
    $tenantRecord = TenantRecordRepository::getById($payloadTenantRecordId);
    assert_true(is_array($tenantRecord), 'tenant record 1 exists for payload perf tests');

    $payloadBatch = test_uuid('payload-batch');
    $payloadBatchRunIds[] = $payloadBatch;
    $childA = test_uuid('payload-child-a');
    $childB = test_uuid('payload-child-b');
    $legacyChild = test_uuid('payload-child-legacy');
    $payloadRunIds[] = $childA;
    $payloadRunIds[] = $childB;
    $payloadRunIds[] = $legacyChild;

    $physicalA = 'user:payload-perf-a';
    $physicalB = 'user:payload-perf-b';
    $physicalLegacy = 'user:payload-perf-legacy';
    $legacyDeltaJson = json_encode(['mail' => ['inbox' => 'https://graph.test/delta/legacy-inbox']], JSON_UNESCAPED_SLASHES);
    $finishedAt = $now - 3600;

    insertTestRun($childA, [
        'tenant_record_id' => $payloadTenantRecordId,
        'e3_batch_run_id' => $payloadBatch,
        'physical_key' => $physicalA,
        'resource_id' => $physicalA,
        'graph_id' => 'payload-perf-a',
        'scope_json' => json_encode(['mail' => true, 'calendar' => true, 'onedrive' => true]),
    ]);
    insertTestRun($childB, [
        'tenant_record_id' => $payloadTenantRecordId,
        'e3_batch_run_id' => $payloadBatch,
        'physical_key' => $physicalB,
        'resource_id' => $physicalB,
        'graph_id' => 'payload-perf-b',
        'scope_json' => json_encode(['mail' => true, 'calendar' => true]),
    ]);
    insertTestRun($legacyChild, [
        'tenant_record_id' => $payloadTenantRecordId,
        'e3_batch_run_id' => $payloadBatch,
        'physical_key' => $physicalLegacy,
        'resource_id' => $physicalLegacy,
        'graph_id' => 'payload-perf-legacy',
        'scope_json' => json_encode(['mail' => true]),
    ]);

    $priorManifestA = test_uuid('prior-manifest-a');
    $priorManifestLegacy = test_uuid('prior-manifest-legacy');
    Capsule::table('ms365_backup_runs')->insert([
        'id' => test_uuid('prior-run-a'),
        'status' => 'success',
        'phase' => 'done',
        'physical_key' => $physicalA,
        'tenant_record_id' => $payloadTenantRecordId,
        'whmcs_client_id' => (int) ($tenantRecord['whmcs_client_id'] ?? 1),
        'manifest_id' => $priorManifestA,
        'finished_at' => $finishedAt,
        'created_at' => $finishedAt,
        'updated_at' => $finishedAt,
    ]);
    Capsule::table('ms365_backup_runs')->insert([
        'id' => test_uuid('prior-run-legacy'),
        'status' => 'success',
        'phase' => 'done',
        'physical_key' => $physicalLegacy,
        'tenant_record_id' => $payloadTenantRecordId,
        'whmcs_client_id' => (int) ($tenantRecord['whmcs_client_id'] ?? 1),
        'manifest_id' => $priorManifestLegacy,
        'delta_states_json' => $legacyDeltaJson,
        'finished_at' => $finishedAt - 60,
        'created_at' => $finishedAt - 60,
        'updated_at' => $finishedAt - 60,
    ]);

    Ms365BatchClaimRepository::enqueueBatch($payloadBatch, $payloadTenantRecordId, 50);
    Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $payloadBatch)
        ->update([
            'status' => 'running',
            'worker_node_id' => 'test-batch-payload-node',
            'running_tenant_key' => $payloadTenantRecordId,
            'claimed_at' => $now,
            'lease_expires_at' => $now + 3600,
            'last_heartbeat_at' => $now,
        ]);

    $childrenRows = Capsule::table('ms365_backup_runs')
        ->where('e3_batch_run_id', $payloadBatch)
        ->orderBy('created_at')
        ->get()
        ->map(static fn ($row) => (array) $row)
        ->all();

    $workerClaimReflection = new ReflectionClass(WorkerClaimService::class);
    $batchContextMethod = $workerClaimReflection->getMethod('batchPayloadContextForTenant');
    $batchContextMethod->setAccessible(true);
    $baseContext = $batchContextMethod->invoke(null, $payloadTenantRecordId);
    assert_true(is_array($baseContext), 'batch payload tenant context resolves');

    $enrichMethod = $workerClaimReflection->getMethod('enrichBatchPayloadContext');
    $enrichMethod->setAccessible(true);

    $batchContext = $enrichMethod->invoke(null, $baseContext, $childrenRows, $payloadTenantRecordId);
    $destinationsByJob = $batchContext['destinations_by_job'] ?? [];
    assert_true(count($destinationsByJob) === 1, 'destination resolves once per batch job id');

    $goldenRuns = [$childA, $childB, $legacyChild];
    foreach ($goldenRuns as $goldenRunId) {
        $row = BackupRunRepository::get($goldenRunId);
        assert_true(is_array($row), 'golden child run exists: ' . $goldenRunId);
        $reference = WorkerClaimService::buildRunPayload($goldenRunId, null);
        $optimized = WorkerClaimService::buildRunPayload($goldenRunId, $batchContext, $row);
        assert_true(is_array($reference) && is_array($optimized), 'reference and batch payloads build for ' . $goldenRunId);
        assert_payload_golden($reference, $optimized, 'batch payload matches per-run golden for ' . $goldenRunId);
    }

    $legacyPayload = WorkerClaimService::buildRunPayload($legacyChild, $batchContext, BackupRunRepository::get($legacyChild));
    assert_true(is_array($legacyPayload), 'legacy delta fallback payload builds');
    $legacyDelta = $legacyPayload['delta_states'] ?? null;
    if ($legacyDelta instanceof stdClass) {
        $legacyDelta = (array) $legacyDelta;
    }
    assert_true(
        is_array($legacyDelta)
        && (($legacyDelta['mail']['inbox'] ?? '') === 'https://graph.test/delta/legacy-inbox'),
        'legacy delta_states_json fallback resolves in batch path',
    );
    assert_true(
        ($legacyPayload['previous_manifest_id'] ?? '') === $priorManifestLegacy,
        'batch prefetch resolves prior manifest id',
    );

    $manifestMap = KopiaRepoBootstrapService::latestManifestForSources(
        $payloadTenantRecordId,
        [$physicalA, $physicalB],
        DeltaStateRepository::computeJobScope('', $payloadTenantRecordId),
    );
    assert_true(
        ($manifestMap[$physicalA] ?? '') === $priorManifestA,
        'latestManifestForSources returns latest manifest per key',
    );

    // Sticky ops soft-abort queue text must clear on payload build so the new owner
    // can promote the child (prod: 5964c88e Ruben after "Ops soft-abort: wedged…").
    if (!Capsule::table('ms365_job_queue')->where('run_id', $childA)->exists()) {
        insertTestQueue($childA, [
            'status' => 'queued',
            'error_message' => 'Ops soft-abort: wedged zero-byte upload after graph_sync',
        ]);
    } else {
        Capsule::table('ms365_job_queue')->where('run_id', $childA)->update([
            'status' => 'queued',
            'error_message' => 'Ops soft-abort: wedged zero-byte upload after graph_sync',
            'worker_node_id' => null,
            'claimed_at' => null,
            'lease_expires_at' => null,
        ]);
    }
    Capsule::table('ms365_backup_runs')->where('id', $childA)->update(['status' => 'queued']);
    assert_true(
        !Ms365BatchClaimRepository::shouldPromoteFromBatchProgress($childA),
        'ops soft-abort queue text blocks hub promote before payload clear',
    );

    $batchPayload = WorkerClaimService::buildBatchPayload($payloadBatch, 'test-batch-payload-node');
    assert_true(
        trim((string) (Capsule::table('ms365_job_queue')->where('run_id', $childA)->value('error_message') ?? '')) === '',
        'buildBatchPayload clears ops soft-abort suppress marker',
    );
    assert_true(
        Ms365BatchClaimRepository::shouldPromoteFromBatchProgress($childA),
        'child is promotable after payload clears suppress marker',
    );
    assert_true(
        isset($batchPayload['graph_token']) && ($batchPayload['graph_token'] ?? '') !== '',
        'batch payload carries a single shared graph token',
    );
    assert_true(count($batchPayload['children'] ?? []) === 3, 'batch payload includes all pending children');
    foreach ($batchPayload['children'] as $childPayload) {
        assert_true(!isset($childPayload['graph_token']), 'child payloads omit per-run graph_token');
    }
} finally {
    cleanupBatchTestRows($payloadBatchRunIds, $payloadRunIds);
    Capsule::table('ms365_backup_runs')
        ->whereIn('physical_key', ['user:payload-perf-a', 'user:payload-perf-b', 'user:payload-perf-legacy'])
        ->where('status', 'success')
        ->delete();
}

// Parent Jobs UI must reopen when claim recovers from transient infra failure.
$reopenBatch = test_uuid('reopen-parent-batch');
$reopenChild = test_uuid('reopen-parent-child');
try {
    $existingJob = Capsule::table('s3_cloudbackup_runs')
        ->where('engine', 'ms365')
        ->orderByDesc('started_at')
        ->first(['job_id']);
    assert_true($existingJob !== null, 'existing ms365 job available for reopen parent test');
    $runBin = hex2bin(str_replace('-', '', $reopenBatch));
    Capsule::table('s3_cloudbackup_runs')->insert([
        'run_id' => $runBin,
        'job_id' => $existingJob->job_id,
        'trigger_type' => 'manual',
        'status' => 'failed',
        'created_at' => date('Y-m-d H:i:s', $now - 3600),
        'started_at' => date('Y-m-d H:i:s', $now - 3600),
        'finished_at' => date('Y-m-d H:i:s', $now - 600),
        'progress_pct' => 90,
        'error_summary' => 'Batch progress stale (owner heartbeating without progress)',
        'engine' => 'ms365',
    ]);
    insertTestRun($reopenChild, [
        'e3_batch_run_id' => $reopenBatch,
        'status' => 'running',
        'phase' => 'graph_sync',
        'last_progress_at' => $now,
        'updated_at' => $now,
    ]);
    $reopened = Ms365BatchRunRepository::reopenAfterTransientInfraFailure($reopenBatch);
    assert_true($reopened, 'reopenAfterTransientInfraFailure updates locked failed parent');
    $parentAfter = Capsule::table('s3_cloudbackup_runs')
        ->whereRaw('run_id = UUID_TO_BIN(?)', [strtolower($reopenBatch)])
        ->first();
    assert_true(($parentAfter->status ?? '') === 'running', 'parent status reopened to running');
    assert_true(empty($parentAfter->finished_at), 'parent finished_at cleared on reopen');
    assert_true(trim((string) ($parentAfter->error_summary ?? '')) === '', 'parent error_summary cleared on reopen');
} finally {
    Capsule::table('s3_cloudbackup_runs')->whereRaw('run_id = UUID_TO_BIN(?)', [strtolower($reopenBatch)])->delete();
    cleanupBatchTestRows([$reopenBatch], [$reopenChild]);
}

// Exhausted-resume claims with pending children must be revived (not permanently stranded).
$exhaustedBatch = test_uuid('exhausted-resume-batch');
$exhaustedChild = test_uuid('exhausted-resume-child');
try {
    Ms365BatchClaimRepository::enqueueBatch($exhaustedBatch, $tenantRecordId, 40);
    insertTestRun($exhaustedChild, [
        'e3_batch_run_id' => $exhaustedBatch,
        'status' => 'queued',
    ]);
    insertTestQueue($exhaustedChild, ['status' => 'queued']);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $exhaustedBatch)->update([
        'status' => 'failed',
        'worker_node_id' => null,
        'running_tenant_key' => null,
        'claimed_at' => null,
        'lease_expires_at' => null,
        'attempts' => 16,
        'max_attempts' => 5,
        'error_message' => 'Batch attempts exhausted while resuming; freeing worker for other claims',
        'updated_at' => $now - 600,
    ]);
    $recoveredExhausted = Ms365BatchClaimRepository::recoverStrandedFailedBatches();
    assert_true($recoveredExhausted >= 1, 'recoverStrandedFailedBatches revives exhausted-resume claims with pending children');
    $claimAfter = Capsule::table('ms365_batch_claims')->where('batch_run_id', $exhaustedBatch)->first();
    assert_true(($claimAfter->status ?? '') === 'queued', 'exhausted-resume claim requeued');
    assert_true((int) ($claimAfter->attempts ?? -1) === 0, 'exhausted-resume claim attempts reset');
} finally {
    cleanupBatchTestRows([$exhaustedBatch], [$exhaustedChild]);
}

// Drain hand-off reclaim must not burn attempt budget (disk hard-pressure cycles).
$drainBatch = test_uuid('drain-batch');
$drainChild = test_uuid('drain-child');
$drainNode = 'test-node-drain-' . substr(md5((string) microtime(true)), 0, 8);
$runIds[] = $drainChild;
try {
    Ms365BatchClaimRepository::enqueueBatch($drainBatch, $tenantRecordId, 40);
    insertTestRun($drainChild, [
        'e3_batch_run_id' => $drainBatch,
        'status' => 'queued',
    ]);
    insertTestQueue($drainChild);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $drainBatch)->update([
        'status' => 'queued',
        'worker_node_id' => null,
        'running_tenant_key' => null,
        'claimed_at' => null,
        'attempts' => 3,
        'max_attempts' => 5,
        'error_message' => 'Worker drain hand-off',
        'updated_at' => $now,
    ]);
    $drainClaimed = Ms365BatchClaimRepository::claimForNode($drainNode);
    assert_true($drainClaimed !== null && ($drainClaimed['batch_run_id'] ?? '') === $drainBatch, 'drain hand-off batch is reclaimable');
    $drainAttempts = (int) Capsule::table('ms365_batch_claims')->where('batch_run_id', $drainBatch)->value('attempts');
    assert_true($drainAttempts === 3, 'drain hand-off reclaim does not increment attempts');
} finally {
    cleanupBatchTestRows([$drainBatch], [$drainChild]);
}

$resetBatchRunIds = [];
$resetRunIds = [];
try {
    if (!Capsule::schema()->hasTable('ms365_delta_resets')) {
        $sqlFile = dirname(__DIR__) . '/sql/upgrade_phase25_delta_resets.sql';
        $sql = file_get_contents($sqlFile);
        if (is_string($sql) && $sql !== '') {
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    Capsule::connection()->statement($statement);
                }
            }
        }
    }

    $resetBatch = test_uuid('reset-batch');
    $resetChild = test_uuid('reset-child');
    $resetBatchRunIds[] = $resetBatch;
    $resetRunIds[] = $resetChild;
    $resetTenant = 1;
    $resetJobId = test_uuid('reset-job');
    $resetPhysical = 'drive:' . test_uuid('reset-drive');
    $resetFinished = time() - 3600;

    insertTestRun($resetChild, [
        'tenant_record_id' => $resetTenant,
        'e3_batch_run_id' => $resetBatch,
        'physical_key' => $resetPhysical,
        'resource_id' => $resetPhysical,
        'graph_id' => 'reset-drive',
        'e3_job_id' => $resetJobId,
        'scope_json' => json_encode(['files' => true, '_site_id' => 'site-reset', '_drive_id' => substr($resetPhysical, 6)]),
    ]);
    insertTestQueue($resetChild);

    Capsule::table('ms365_backup_runs')->insert([
        'id' => test_uuid('reset-legacy-run'),
        'status' => 'success',
        'phase' => 'done',
        'physical_key' => $resetPhysical,
        'tenant_record_id' => $resetTenant,
        'whmcs_client_id' => 1,
        'e3_job_id' => $resetJobId,
        'delta_states_json' => json_encode(['sharepoint' => [substr($resetPhysical, 6) => 'https://graph.test/delta/pre-reset']], JSON_UNESCAPED_SLASHES),
        'finished_at' => $resetFinished,
        'created_at' => $resetFinished,
        'updated_at' => $resetFinished,
    ]);

    DeltaStateRepository::recordReset($resetTenant, $resetPhysical, $resetJobId, 'batch claim test', 'phpunit');
    DeltaStateRepository::clearCanonicalForSource($resetTenant, $resetPhysical, $resetJobId);

    $payload = WorkerClaimService::buildRunPayload($resetChild);
    $delta = $payload['delta_states'] ?? null;
    if ($delta instanceof stdClass) {
        $delta = (array) $delta;
    }
    assert_true(is_array($delta) && $delta === [], 'batch claim omits pre-reset legacy SharePoint delta after tombstone');

    Capsule::table('ms365_backup_runs')
        ->where('physical_key', $resetPhysical)
        ->where('status', 'success')
        ->delete();
    Capsule::table('ms365_delta_resets')
        ->where('tenant_record_id', $resetTenant)
        ->where('physical_key', $resetPhysical)
        ->delete();
} finally {
    cleanupBatchTestRows($resetBatchRunIds, $resetRunIds);
}

exit($failures > 0 ? 1 : 0);
