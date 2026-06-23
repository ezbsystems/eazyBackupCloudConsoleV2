<?php
declare(strict_types=1);

/**
 * Reaper throttle guards, wedge detection, no_progress refresh, tenant-id parity.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_reaper_throttle_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupRunRepository;
use Ms365Backup\GraphTenantBudgetService;
use Ms365Backup\JobQueueRepository;
use Ms365Backup\Ms365BatchRunRepository;
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
    $hex = substr(md5('ms365_reaper_throttle_' . $suffix), 0, 32);

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
        'items_done' => 0,
        'items_total' => 0,
        'percent' => 0.0,
        'physical_key' => 'user:test-reaper-throttle',
        'resource_type' => 'user',
        'resource_id' => 'user:test-reaper-throttle',
        'graph_id' => 'test-reaper-throttle',
        'user_display_name' => 'Reaper Throttle Test',
        'backup_path' => '/tmp/ms365-reaper-throttle-test',
        'tenant_record_id' => 0,
        'created_at' => $now,
        'updated_at' => $now - 2000,
        'started_at' => $now - 2000,
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
        'claimed_at' => $now - 2000,
        'lease_expires_at' => $now - 60,
        'scheduled_at' => $now - 2000,
        'created_at' => $now - 2000,
        'started_at' => $now - 2000,
        'job_type' => 'backup',
    ], $overrides);
    Capsule::table('ms365_job_queue')->insert($row);
}

function ensureTestNode(string $nodeId, int $now): void
{
    $existing = Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->first();
    if ($existing !== null) {
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'status' => 'active',
            'last_heartbeat_at' => $now,
            'updated_at' => $now,
        ]);

        return;
    }
    Capsule::table('ms365_worker_nodes')->insert([
        'node_id' => $nodeId,
        'hostname' => $nodeId,
        'status' => 'active',
        'max_concurrent_runs' => 4,
        'current_load' => 1,
        'last_heartbeat_at' => $now,
        'registered_at' => $now,
        'updated_at' => $now,
    ]);
}

function cleanupTestRows(array $runIds, ?string $azureTenantId = null, ?string $nodeId = null): void
{
    if ($runIds !== []) {
        Capsule::table('ms365_job_queue')->whereIn('run_id', $runIds)->delete();
        Capsule::table('ms365_backup_runs')->whereIn('id', $runIds)->delete();
    }
    if ($azureTenantId !== null && $azureTenantId !== '' && GraphTenantBudgetService::tableReady()) {
        Capsule::table('ms365_graph_tenant_budget')->where('azure_tenant_id', $azureTenantId)->delete();
    }
    if ($nodeId !== null && $nodeId !== '') {
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->delete();
    }
}

function queueStatus(string $runId): string
{
    return (string) Capsule::table('ms365_job_queue')->where('run_id', $runId)->value('status');
}

$now = time();
$nodeId = 'test-reaper-throttle-node';
$runIds = [];
$azureTenantId = 'test-azure-reaper-' . substr(md5('ms365_reaper_throttle'), 0, 8);

$tenantRecordId = 0;
$tenantRow = Capsule::table('ms365_tenant_records')->where('is_active', 1)->first(['id', 'azure_tenant_id', 'tenant_id']);
if ($tenantRow !== null) {
    $tenantRecordId = (int) ($tenantRow->id ?? 0);
    $fromRecord = trim((string) ($tenantRow->azure_tenant_id ?? $tenantRow->tenant_id ?? ''));
    if ($fromRecord !== '') {
        $azureTenantId = $fromRecord;
    }
}

try {
    ensureTestNode($nodeId, $now);

    if (GraphTenantBudgetService::tableReady() && $tenantRecordId > 0) {
        Capsule::table('ms365_graph_tenant_budget')->updateOrInsert(
            ['azure_tenant_id' => $azureTenantId],
            [
                'graph_budget' => 4,
                'recent_429_count' => 12,
                'last_429_at' => $now - 120,
                'updated_at' => $now,
            ],
        );

        $throttledRunId = test_uuid('release-expired');
        $runIds[] = $throttledRunId;
        insertTestRun($throttledRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => $now - 300,
            'updated_at' => $now - 2000,
        ]);
        insertTestQueue($throttledRunId, $nodeId, ['lease_expires_at' => $now - 60]);
        WorkerClaimService::releaseExpiredLeases();
        assert_true(
            queueStatus($throttledRunId) === 'running',
            'releaseExpiredLeases skips throttle+alive-node run',
        );

        $deadRunId = test_uuid('release-expired-dead');
        $runIds[] = $deadRunId;
        insertTestRun($deadRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => 0,
            'updated_at' => $now - 2000,
        ]);
        insertTestQueue($deadRunId, $nodeId, ['lease_expires_at' => $now - 60]);
        Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->update(['last_429_at' => $now - 5000, 'recent_429_count' => 0]);
        WorkerClaimService::releaseExpiredLeases();
        assert_true(
            queueStatus($deadRunId) === 'queued',
            'releaseExpiredLeases still reaps dead-worker expired lease',
        );

        Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->update(['last_429_at' => $now - 120, 'recent_429_count' => 12]);

        $staleRunId = test_uuid('stale-rows');
        $runIds[] = $staleRunId;
        insertTestRun($staleRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => $now - 300,
            'updated_at' => $now - 2000,
        ]);
        insertTestQueue($staleRunId, $nodeId, ['lease_expires_at' => $now - 60]);
        $beforeStale = queueStatus($staleRunId);
        WorkerClaimService::reconcileZombieRuns(120);
        assert_true(
            queueStatus($staleRunId) === $beforeStale,
            'reconcileZombieRuns staleRows skips throttle+alive-node run',
        );

        $recoverRunId = test_uuid('recover-stale');
        $runIds[] = $recoverRunId;
        insertTestRun($recoverRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => $now - 300,
            'updated_at' => $now - 2000,
        ]);
        insertTestQueue($recoverRunId, $nodeId, [
            'lease_expires_at' => $now - 60,
            'started_at' => $now - 3600,
        ]);
        JobQueueRepository::recoverStaleRunning();
        assert_true(
            queueStatus($recoverRunId) === 'running',
            'recoverStaleRunning skips throttle+alive-node run',
        );

        $wedgeRunId = test_uuid('wedge-throttle');
        $runIds[] = $wedgeRunId;
        insertTestRun($wedgeRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => $now - 300,
            'updated_at' => $now - 30,
            'started_at' => $now - 2000,
            'items_done' => 0,
            'bytes_hashed' => 0,
        ]);
        insertTestQueue($wedgeRunId, $nodeId, ['lease_expires_at' => $now + 3600]);
        $wedgeChild = (array) Capsule::table('ms365_backup_runs')->where('id', $wedgeRunId)->first();
        $wedgeQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $wedgeRunId)->first();
        $isWedgeStuck = (new ReflectionClass(Ms365BatchRunRepository::class))->getMethod('isWedgeStuck');
        $isWedgeStuck->setAccessible(true);
        assert_true(
            !(bool) $isWedgeStuck->invoke(null, $wedgeChild, $now, $wedgeQueue),
            'isWedgeStuck honors throttled-but-alive child',
        );

        $noProgressRunId = test_uuid('no-progress-throttle');
        $runIds[] = $noProgressRunId;
        insertTestRun($noProgressRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => $now - 2000,
            'updated_at' => $now - 2000,
        ]);
        insertTestQueue($noProgressRunId, $nodeId, ['lease_expires_at' => $now + 3600]);
        Ms365RestoreWorkerHooks::onProgress($noProgressRunId, [
            'no_progress' => true,
            'throttle_waiting' => true,
            'graph_429_hits' => 8,
        ]);
        $refreshed = BackupRunRepository::get($noProgressRunId) ?? [];
        assert_true(
            (int) ($refreshed['updated_at'] ?? 0) >= ($now - 5)
            && (int) ($refreshed['last_429_at'] ?? 0) >= ($now - 5),
            'no_progress refreshes updated_at and last_429_at when throttle_waiting',
        );

        $exhaustedRunId = test_uuid('exhausted-throttle');
        $runIds[] = $exhaustedRunId;
        insertTestRun($exhaustedRunId, [
            'tenant_record_id' => $tenantRecordId,
            'status' => 'running',
            'last_429_at' => $now - 300,
            'updated_at' => $now - 30,
        ]);
        insertTestQueue($exhaustedRunId, $nodeId, [
            'status' => 'running',
            'attempts' => 5,
            'max_attempts' => 3,
            'lease_expires_at' => $now + 3600,
        ]);
        $exhaustedBefore = queueStatus($exhaustedRunId);
        WorkerClaimService::reconcileZombieRuns(120);
        assert_true(
            queueStatus($exhaustedRunId) === $exhaustedBefore,
            'exhausted path keeps throttle+alive run via tenant_record_id in select',
        );
    } else {
        echo "SKIP: reaper throttle DB fixtures unavailable\n";
    }
} finally {
    cleanupTestRows($runIds, $azureTenantId, $nodeId);
}

exit($failures > 0 ? 1 : 0);
