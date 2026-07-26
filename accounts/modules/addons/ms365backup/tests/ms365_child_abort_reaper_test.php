<?php
declare(strict_types=1);

/**
 * Live-batch child soft-abort and delayed requeue regression.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_child_abort_reaper_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupRunRepository;
use Ms365Backup\ChildAbortRepository;
use Ms365Backup\JobQueueRepository;
use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\Ms365BatchRunRepository;
use WHMCS\Database\Capsule;

$failures = 0;

function abort_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

function abort_uuid(string $suffix): string
{
    $hex = substr(md5('ms365_child_abort_' . $suffix . microtime(true)), 0, 32);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

if (!ChildAbortRepository::columnReady()) {
    $sqlFile = dirname(__DIR__) . '/sql/upgrade_phase23_child_abort.sql';
    if (!is_file($sqlFile)) {
        fwrite(STDERR, "Missing upgrade_phase23_child_abort.sql\n");
        exit(1);
    }
    Capsule::connection()->getPdo()->exec((string) file_get_contents($sqlFile));
}

$batchId = abort_uuid('batch');
$staleChildId = abort_uuid('stale');
$freshChildId = abort_uuid('fresh');
$tenantRecordId = 999003;
$nodeId = 'child-abort-test-node';
$now = time();

try {
    Ms365BatchClaimRepository::enqueueBatch($batchId, $tenantRecordId, 50);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $batchId)->update([
        'status' => 'running',
        'worker_node_id' => $nodeId,
        'running_tenant_key' => $tenantRecordId,
        'claimed_at' => $now - 900,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
        'last_progress_at' => $now,
    ]);

    foreach ([$staleChildId => $now - 950, $freshChildId => $now - 30] as $childId => $lastProgressAt) {
        Capsule::table('ms365_backup_runs')->insert([
            'id' => $childId,
            'e3_batch_run_id' => $batchId,
            'status' => 'running',
            'phase' => 'upload',
            'items_done' => 100,
            'items_total' => 100,
            'bytes_hashed' => 1000,
            'bytes_uploaded' => 500,
            'last_progress_at' => $lastProgressAt,
            'physical_key' => 'site:' . $childId,
            'resource_type' => 'site',
            'resource_id' => 'site:' . $childId,
            'graph_id' => $childId,
            'user_display_name' => 'Child Abort Test',
            'backup_path' => '/tmp/ms365-child-abort-' . $childId,
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => 1,
            'created_at' => $now - 900,
            'updated_at' => $lastProgressAt,
        ]);
        Capsule::table('ms365_job_queue')->insert([
            'run_id' => $childId,
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
    }

    Ms365BatchClaimRepository::reapStalledBatchChildren();
    $staleRow = Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->first();
    abort_assert(
        ($staleRow->status ?? '') === 'running'
        && (int) ($staleRow->abort_requested_at ?? 0) >= $now - 5,
        'live batch stale upload-tail child gets soft abort without requeue',
    );
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $freshChildId)->value('abort_requested_at') === null,
        'fresh sibling on live batch is untouched',
    );
    abort_assert(
        ChildAbortRepository::listAbortRequestedRunIds($batchId) === [$staleChildId],
        'batch progress lists abort_requested child',
    );

    Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->update([
        'abort_requested_at' => $now - ChildAbortRepository::REQUEUE_AFTER_SECONDS - 5,
    ]);
    Ms365BatchClaimRepository::reapStalledBatchChildren();
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->value('status') === 'queued',
        'stale child requeues after abort grace expires',
    );
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $freshChildId)->value('status') === 'running',
        'active sibling stays running after stale child requeue',
    );
    abort_assert(
        !Ms365BatchClaimRepository::shouldPromoteFromBatchProgress($staleChildId),
        'hub progress must not re-promote child progress stale requeue',
    );
    Ms365BatchClaimRepository::promoteBatchChildToRunning($staleChildId, $nodeId);
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->value('status') === 'queued',
        'promoteBatchChildToRunning is a no-op after live-owner stale requeue',
    );

    // Regression: fail/requeue and promote must clear abort so the child is not re-aborted.
    Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->update([
        'status' => 'running',
        'abort_requested_at' => $now,
        'updated_at' => $now,
    ]);
    Capsule::table('ms365_job_queue')->where('run_id', $staleChildId)->update([
        'status' => 'running',
        'attempts' => 1,
        'worker_node_id' => $nodeId,
        'claimed_at' => $now - 60,
        'lease_expires_at' => $now + 600,
    ]);
    abort_assert(
        ChildAbortRepository::listAbortRequestedRunIds($batchId) === [$staleChildId],
        'running child with abort flag is listed for batch progress',
    );

    JobQueueRepository::markFailed($staleChildId, 'simulated worker fail for abort reclaim');
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->value('status') === 'queued',
        'markFailed requeues child for another attempt',
    );
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->value('abort_requested_at') === null,
        'resetForQueueRequeue clears abort_requested_at',
    );

    Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->update([
        'abort_requested_at' => $now,
    ]);
    Ms365BatchClaimRepository::promoteBatchChildToRunning($staleChildId, $nodeId);
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->value('abort_requested_at') === null,
        'promoteBatchChildToRunning clears abort_requested_at',
    );
    abort_assert(
        !in_array($staleChildId, ChildAbortRepository::listAbortRequestedRunIds($batchId), true),
        'promoted child is not in batch abort list',
    );

    Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->update([
        'status' => 'running',
        'abort_requested_at' => $now,
        'updated_at' => $now,
    ]);
    Capsule::table('ms365_job_queue')->where('run_id', $staleChildId)->update([
        'status' => 'queued',
        'worker_node_id' => null,
        'claimed_at' => null,
        'lease_expires_at' => null,
    ]);
    BackupRunRepository::resetForQueueRequeue($staleChildId, $now);
    abort_assert(
        Capsule::table('ms365_backup_runs')->where('id', $staleChildId)->value('abort_requested_at') === null,
        'resetForQueueRequeue clears abort when child was running with abort flag',
    );

    // prior_snapshot: 600s silence on live owner requests soft abort (wedged Kopia open).
    $priorBatchId = abort_uuid('prior-batch');
    $priorStaleChildId = abort_uuid('prior-stale');
    $priorTenantRecordId = 999004;
    Ms365BatchClaimRepository::enqueueBatch($priorBatchId, $priorTenantRecordId, 50);
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $priorBatchId)->update([
        'status' => 'running',
        'worker_node_id' => $nodeId,
        'running_tenant_key' => $priorTenantRecordId,
        'claimed_at' => $now - 900,
        'lease_expires_at' => $now + 600,
        'last_heartbeat_at' => $now,
        'last_progress_at' => $now,
    ]);
    Capsule::table('ms365_backup_runs')->insert([
        'id' => $priorStaleChildId,
        'e3_batch_run_id' => $priorBatchId,
        'status' => 'running',
        'phase' => 'prior_snapshot',
        'items_done' => 0,
        'items_total' => 0,
        'bytes_hashed' => 0,
        'bytes_uploaded' => 0,
        'last_progress_at' => $now - 700,
        'physical_key' => 'user:' . $priorStaleChildId,
        'resource_type' => 'user',
        'resource_id' => 'user:' . $priorStaleChildId,
        'graph_id' => $priorStaleChildId,
        'user_display_name' => 'Prior Snapshot Abort Test',
        'backup_path' => '/tmp/ms365-prior-abort-' . $priorStaleChildId,
        'tenant_record_id' => $priorTenantRecordId,
        'whmcs_client_id' => 1,
        'created_at' => $now - 900,
        'updated_at' => $now - 700,
        'started_at' => $now - 700,
    ]);
    Capsule::table('ms365_job_queue')->insert([
        'run_id' => $priorStaleChildId,
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
    $priorStaleRow = Capsule::table('ms365_backup_runs')->where('id', $priorStaleChildId)->first();
    abort_assert(
        ($priorStaleRow->status ?? '') === 'running'
        && (int) ($priorStaleRow->abort_requested_at ?? 0) >= $now - 5,
        'live batch prior_snapshot child soft-aborts after 600s silence',
    );
    Capsule::table('ms365_job_queue')->where('run_id', $priorStaleChildId)->delete();
    Capsule::table('ms365_backup_runs')->where('id', $priorStaleChildId)->delete();
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $priorBatchId)->delete();
} finally {
    foreach ([$staleChildId, $freshChildId] as $childId) {
        Capsule::table('ms365_job_queue')->where('run_id', $childId)->delete();
        Capsule::table('ms365_backup_runs')->where('id', $childId)->delete();
    }
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $batchId)->delete();
}

exit($failures > 0 ? 1 : 0);
