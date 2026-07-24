<?php
declare(strict_types=1);

/**
 * Focused tenant-owner stale recovery and child attempt accounting regression.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_tenant_owner_recovery_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365EngineConfig;
use WHMCS\Database\Capsule;

$failures = 0;

function owner_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

function owner_uuid(string $suffix): string
{
    $hex = substr(md5('ms365_tenant_owner_recovery_' . $suffix . microtime(true)), 0, 32);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

$batchId = owner_uuid('batch');
$runId = owner_uuid('child');
$tenantRecordId = 999002;
$nodeId = 'tenant-owner-recovery-test-node';
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
    Capsule::table('ms365_backup_runs')->insert([
        'id' => $runId,
        'e3_batch_run_id' => $batchId,
        'status' => 'running',
        'phase' => 'graph_sync',
        'items_done' => 100,
        'items_total' => 100,
        'bytes_hashed' => 0,
        'bytes_uploaded' => 0,
        'last_progress_at' => $now - Ms365BatchRunRepository::STALE_SILENCE_SECONDS - 60,
        'physical_key' => 'user:tenant-owner-recovery-test',
        'resource_type' => 'user',
        'resource_id' => 'user:tenant-owner-recovery-test',
        'graph_id' => 'tenant-owner-recovery-test',
        'user_display_name' => 'Tenant Owner Recovery Test',
        'backup_path' => '/tmp/ms365-tenant-owner-recovery-test',
        'tenant_record_id' => $tenantRecordId,
        'whmcs_client_id' => 1,
        'created_at' => $now - 900,
        'updated_at' => $now - Ms365BatchRunRepository::STALE_SILENCE_SECONDS - 60,
    ]);
    Capsule::table('ms365_job_queue')->insert([
        'run_id' => $runId,
        'job_type' => 'backup',
        'status' => 'running',
        'priority' => 50,
        'attempts' => 0,
        'max_attempts' => 5,
        'scheduled_at' => $now - 900,
        'claimed_at' => $now - 900,
        'lease_expires_at' => $now - 10,
        'created_at' => $now - 900,
    ]);

    Ms365BatchClaimRepository::reapStalledBatchChildren();
    if (Capsule::schema()->hasColumn('ms365_backup_runs', 'abort_requested_at')) {
        owner_assert(
            (int) Capsule::table('ms365_backup_runs')->where('id', $runId)->value('abort_requested_at') > 0
            && Capsule::table('ms365_backup_runs')->where('id', $runId)->value('status') === 'running',
            'live tenant owner soft-aborts silent child without immediate requeue',
        );
    } else {
        owner_assert(
            Capsule::table('ms365_backup_runs')->where('id', $runId)->value('status') === 'running',
            'live tenant owner protects silent child from independent requeue',
        );
    }

    Capsule::table('ms365_batch_claims')->where('batch_run_id', $batchId)->update([
        'last_heartbeat_at' => $now - Ms365EngineConfig::batchHeartbeatGapSeconds() - 10,
        'lease_expires_at' => $now - 10,
    ]);
    Ms365BatchClaimRepository::reapStalledBatchChildren();
    owner_assert(
        Capsule::table('ms365_backup_runs')->where('id', $runId)->value('status') === 'queued',
        'silent child requeues after batch owner becomes stale',
    );

    Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
        'status' => 'queued',
        'attempts' => 0,
        'lease_expires_at' => null,
    ]);
    Ms365BatchClaimRepository::promoteBatchChildToRunning($runId, $nodeId);
    owner_assert(
        (int) Capsule::table('ms365_job_queue')->where('run_id', $runId)->value('attempts') === 1,
        'queued to running promotion increments attempt exactly once',
    );
    Ms365BatchClaimRepository::promoteBatchChildToRunning($runId, $nodeId);
    owner_assert(
        (int) Capsule::table('ms365_job_queue')->where('run_id', $runId)->value('attempts') === 1,
        'repeated progress promotion does not increment attempts',
    );
} finally {
    Capsule::table('ms365_job_queue')->where('run_id', $runId)->delete();
    Capsule::table('ms365_backup_runs')->where('id', $runId)->delete();
    Capsule::table('ms365_batch_claims')->where('batch_run_id', $batchId)->delete();
}

exit($failures > 0 ? 1 : 0);
