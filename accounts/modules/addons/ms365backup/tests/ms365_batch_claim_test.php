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
use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\Ms365EngineConfig;
use Ms365Backup\Ms365RestoreWorkerHooks;
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

exit($failures > 0 ? 1 : 0);
