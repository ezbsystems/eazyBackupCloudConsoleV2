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
    assert_true($childReaped >= 1, 'reapStalledBatchChildren requeues silent child');
    $staleStatus = (string) Capsule::table('ms365_backup_runs')->where('id', $staleChild)->value('status');
    $activeStatus = (string) Capsule::table('ms365_backup_runs')->where('id', $activeChild)->value('status');
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
        'claimed_at' => $now,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
    ]);
    $handed = Ms365BatchClaimRepository::reconcileStrandedBatchQueuedChildren();
    assert_true($handed >= 1, 'stranded queued children trigger batch hand-off');
    $handoffStatus = (string) Capsule::table('ms365_batch_claims')
        ->where('batch_run_id', $handoffBatch)
        ->value('status');
    assert_true($handoffStatus === 'queued', 'running batch claim handed off without mass child reset');
    $activeAfter = (string) Capsule::table('ms365_backup_runs')->where('id', $activeChild)->value('status');
    assert_true($activeAfter === 'running', 'active child remains running after hand-off');

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

    $batchPayload = WorkerClaimService::buildBatchPayload($payloadBatch, 'test-batch-payload-node');
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

exit($failures > 0 ? 1 : 0);
