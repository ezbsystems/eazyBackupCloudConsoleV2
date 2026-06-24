<?php
declare(strict_types=1);

/**
 * Tenant-aware throttle liveness + workload claim cap.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_tenant_throttle_liveness_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\GraphTenantBudgetService;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365EngineConfig;
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
    $hex = substr(md5('ms365_tenant_throttle_liveness_' . $suffix), 0, 32);

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
        'physical_key' => 'user:test-tenant-throttle',
        'resource_type' => 'user',
        'resource_id' => 'user:test-tenant-throttle',
        'graph_id' => 'test-tenant-throttle',
        'user_display_name' => 'Tenant Throttle Test',
        'backup_path' => '/tmp/ms365-tenant-throttle-test',
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
        'lease_expires_at' => $now + 3600,
        'scheduled_at' => $now - 2000,
        'created_at' => $now - 2000,
        'started_at' => $now - 2000,
        'job_type' => 'backup',
    ], $overrides);
    Capsule::table('ms365_job_queue')->insert($row);
}

function cleanupTestRows(array $runIds, ?string $azureTenantId = null): void
{
    if ($runIds !== []) {
        Capsule::table('ms365_job_queue')->whereIn('run_id', $runIds)->delete();
        Capsule::table('ms365_backup_runs')->whereIn('id', $runIds)->delete();
    }
    if ($azureTenantId !== null && $azureTenantId !== '' && GraphTenantBudgetService::tableReady()) {
        Capsule::table('ms365_graph_tenant_budget')->where('azure_tenant_id', $azureTenantId)->delete();
    }
}

function queueStatus(string $runId): string
{
    return (string) Capsule::table('ms365_job_queue')->where('run_id', $runId)->value('status');
}

$now = time();
$nodeId = 'test-tenant-throttle-node';
$runIds = [];
$azureTenantId = 'test-azure-tenant-' . substr(md5('ms365_tenant_throttle_liveness'), 0, 8);

assert_true(
    Ms365BatchRunRepository::RECENT_THROTTLE_SECONDS === 1200,
    'RECENT_THROTTLE_SECONDS widened to 1200s',
);
assert_true(
    Ms365EngineConfig::perTenantMaxConcurrentWorkloads() >= 1,
    'perTenantMaxConcurrentWorkloads is positive',
);
assert_true(
    Ms365EngineConfig::perTenantMaxConcurrentWorkloads() < Ms365EngineConfig::perTenantMaxConcurrent(),
    'workload claim cap is lower than Graph HTTP budget',
);

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
    if (GraphTenantBudgetService::tableReady() && $tenantRecordId > 0) {
        Capsule::table('ms365_graph_tenant_budget')->updateOrInsert(
            ['azure_tenant_id' => $azureTenantId],
            [
                'graph_budget' => Ms365EngineConfig::perTenantMaxConcurrent(),
                'recent_429_count' => 5,
                'last_429_at' => $now - 300,
                'updated_at' => $now,
            ],
        );

        assert_true(
            GraphTenantBudgetService::recentlyThrottled($azureTenantId, $now, Ms365BatchRunRepository::RECENT_THROTTLE_SECONDS),
            'recentlyThrottled true when tenant last_429_at is fresh',
        );
        assert_true(
            !GraphTenantBudgetService::recentlyThrottled($azureTenantId, $now, 60),
            'recentlyThrottled false outside window',
        );

        $starvedRunId = test_uuid('tenant-throttle-starved');
        $runIds[] = $starvedRunId;
        insertTestRun($starvedRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => $now - 2000,
            'last_progress_at' => $now - 2000,
            'updated_at' => $now - 30,
            'started_at' => $now - 2000,
        ]);
        insertTestQueue($starvedRunId, $nodeId);
        $starvedChild = (array) Capsule::table('ms365_backup_runs')->where('id', $starvedRunId)->first();
        $starvedQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $starvedRunId)->first();
        assert_true(
            !Ms365BatchRunRepository::isThrottledWaitingAlive($starvedChild, $starvedQueue, $now),
            'tenant throttle does not shield sibling with stale per-workload progress',
        );

        WorkerClaimService::releaseOrphanedClaimsForNode($nodeId, 4, 120);
        assert_true(
            queueStatus($starvedRunId) === 'queued',
            'busy-node stale-progress reaper reclaims tenant-hot but progress-stale child',
        );

        $shouldReap = (new ReflectionClass(Ms365BatchRunRepository::class))->getMethod('shouldReapRunningChild');
        $shouldReap->setAccessible(true);

        $starvedRunId2 = test_uuid('tenant-throttle-starved-2');
        $runIds[] = $starvedRunId2;
        insertTestRun($starvedRunId2, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => $now - 2000,
            'last_progress_at' => $now - 2000,
            'updated_at' => $now - 30,
            'started_at' => $now - 2000,
        ]);
        insertTestQueue($starvedRunId2, $nodeId);
        $starvedChild2 = (array) Capsule::table('ms365_backup_runs')->where('id', $starvedRunId2)->first();
        $starvedQueue2 = (array) Capsule::table('ms365_job_queue')->where('run_id', $starvedRunId2)->first();
        assert_true(
            (bool) $shouldReap->invoke(null, $starvedChild2, $starvedQueue2, $now),
            'batch child reaper reclaims tenant-hot sibling with stale progress',
        );

        Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->update(['last_429_at' => $now - 5000, 'updated_at' => $now]);

        $wedgeRunId = test_uuid('tenant-idle-wedge');
        $runIds[] = $wedgeRunId;
        insertTestRun($wedgeRunId, [
            'tenant_record_id' => $tenantRecordId,
            'last_429_at' => 0,
            'last_progress_at' => $now - 2000,
            'updated_at' => $now - 30,
            'started_at' => $now - 2000,
            'items_done' => 0,
            'bytes_hashed' => 0,
        ]);
        insertTestQueue($wedgeRunId, $nodeId);
        $wedgeChild = (array) Capsule::table('ms365_backup_runs')->where('id', $wedgeRunId)->first();
        $wedgeQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $wedgeRunId)->first();
        assert_true(
            !(bool) Ms365BatchRunRepository::isThrottledWaitingAlive($wedgeChild, $wedgeQueue, $now),
            'idle tenant is not throttled-alive',
        );
        assert_true(
            (bool) $shouldReap->invoke(null, $wedgeChild, $wedgeQueue, $now),
            'idle tenant wedge child is still reaped',
        );

        Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->update(['last_429_at' => $now - 120, 'recent_429_count' => 12, 'updated_at' => $now]);

        $graphWedgeRunId = test_uuid('graph-wedge-hot-tenant');
        $runIds[] = $graphWedgeRunId;
        insertTestRun($graphWedgeRunId, [
            'tenant_record_id' => $tenantRecordId,
            'phase' => 'graph_sync',
            'last_429_at' => $now - 5000,
            'last_progress_at' => $now - 2000,
            'updated_at' => $now - 30,
            'started_at' => $now - 2000,
            'items_done' => 0,
            'bytes_hashed' => 0,
        ]);
        insertTestQueue($graphWedgeRunId, $nodeId);
        $graphWedgeChild = (array) Capsule::table('ms365_backup_runs')->where('id', $graphWedgeRunId)->first();
        $graphWedgeQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $graphWedgeRunId)->first();
        assert_true(
            !Ms365BatchRunRepository::isThrottledWaitingAlive($graphWedgeChild, $graphWedgeQueue, $now),
            'graph_sync wedge is not tenant throttle-shielded when its own progress is stale',
        );
        $isWedgeStuck = (new ReflectionClass(Ms365BatchRunRepository::class))->getMethod('isWedgeStuck');
        $isWedgeStuck->setAccessible(true);
        assert_true(
            (bool) $isWedgeStuck->invoke(null, $graphWedgeChild, $now, $graphWedgeQueue),
            'graph_sync wedge is reapable when per-workload progress is stale',
        );

        $activeThrottleRunId = test_uuid('graph-active-throttle');
        $runIds[] = $activeThrottleRunId;
        insertTestRun($activeThrottleRunId, [
            'tenant_record_id' => $tenantRecordId,
            'phase' => 'graph_sync',
            'last_429_at' => $now - 30,
            'last_progress_at' => $now - 60,
            'updated_at' => $now - 30,
            'started_at' => $now - 4000,
            'items_done' => 120,
            'bytes_hashed' => 1024,
        ]);
        insertTestQueue($activeThrottleRunId, $nodeId);
        $activeThrottleChild = (array) Capsule::table('ms365_backup_runs')->where('id', $activeThrottleRunId)->first();
        $activeThrottleQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $activeThrottleRunId)->first();
        assert_true(
            Ms365BatchRunRepository::isThrottledWaitingAlive($activeThrottleChild, $activeThrottleQueue, $now),
            'actively pacing workload remains throttle-shielded with fresh progress',
        );
        assert_true(
            Ms365BatchRunRepository::countsAgainstTenantWorkloadCap($activeThrottleChild, $activeThrottleQueue, $now),
            'actively pacing workload still counts against tenant cap',
        );

        $zombieHeartbeatRunId = test_uuid('graph-zombie-heartbeat');
        $runIds[] = $zombieHeartbeatRunId;
        insertTestRun($zombieHeartbeatRunId, [
            'tenant_record_id' => $tenantRecordId,
            'phase' => 'graph_sync',
            'last_429_at' => $now - 30,
            'last_progress_at' => $now - 2500,
            'updated_at' => $now - 30,
            'started_at' => $now - 4000,
            'items_done' => 0,
            'bytes_hashed' => 0,
            'stats_json' => json_encode(['graph_requests' => 0]),
        ]);
        insertTestQueue($zombieHeartbeatRunId, $nodeId);
        $zombieHeartbeatChild = (array) Capsule::table('ms365_backup_runs')->where('id', $zombieHeartbeatRunId)->first();
        $zombieHeartbeatQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $zombieHeartbeatRunId)->first();
        assert_true(
            !Ms365BatchRunRepository::isThrottledWaitingAlive($zombieHeartbeatChild, $zombieHeartbeatQueue, $now),
            'heartbeat-only throttle on stale enumeration is not shielded',
        );
        assert_true(
            !Ms365BatchRunRepository::countsAgainstTenantWorkloadCap($zombieHeartbeatChild, $zombieHeartbeatQueue, $now),
            'zombie heartbeat slot is excluded from tenant cap',
        );

        $uploadStaleCapRunId = test_uuid('upload-stale-cap');
        $runIds[] = $uploadStaleCapRunId;
        insertTestRun($uploadStaleCapRunId, [
            'tenant_record_id' => $tenantRecordId,
            'phase' => 'kopia_upload',
            'last_429_at' => $now - 30,
            'last_progress_at' => $now - 700,
            'updated_at' => $now - 30,
            'started_at' => $now - 4000,
            'bytes_uploaded' => 1024,
        ]);
        insertTestQueue($uploadStaleCapRunId, $nodeId);
        $uploadStaleCapChild = (array) Capsule::table('ms365_backup_runs')->where('id', $uploadStaleCapRunId)->first();
        $uploadStaleCapQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $uploadStaleCapRunId)->first();
        assert_true(
            !Ms365BatchRunRepository::isThrottledWaitingAlive($uploadStaleCapChild, $uploadStaleCapQueue, $now),
            'upload with stale material progress is not throttle-shielded despite fresh last_429_at',
        );
        assert_true(
            !Ms365BatchRunRepository::countsAgainstTenantWorkloadCap($uploadStaleCapChild, $uploadStaleCapQueue, $now),
            'upload silent past UPLOAD_THROTTLE_PROGRESS_SECONDS is excluded from tenant cap',
        );

        $uploadHot429RunId = test_uuid('upload-hot-429');
        $runIds[] = $uploadHot429RunId;
        insertTestRun($uploadHot429RunId, [
            'tenant_record_id' => $tenantRecordId,
            'phase' => 'kopia_upload',
            'last_429_at' => $now - 30,
            'last_progress_at' => $now - 250,
            'updated_at' => $now - 30,
            'started_at' => $now - 4000,
            'bytes_uploaded' => 1024,
        ]);
        insertTestQueue($uploadHot429RunId, $nodeId);
        $uploadHot429Child = (array) Capsule::table('ms365_backup_runs')->where('id', $uploadHot429RunId)->first();
        $uploadHot429Queue = (array) Capsule::table('ms365_job_queue')->where('run_id', $uploadHot429RunId)->first();
        assert_true(
            !Ms365BatchRunRepository::isThrottledWaitingAlive($uploadHot429Child, $uploadHot429Queue, $now),
            'upload phase is not throttle-shielded by per-child last_429_at alone',
        );

        $uploadHotRunId = test_uuid('upload-hot-tenant');
        $runIds[] = $uploadHotRunId;
        insertTestRun($uploadHotRunId, [
            'tenant_record_id' => $tenantRecordId,
            'phase' => 'kopia_upload',
            'last_429_at' => $now - 5000,
            'last_progress_at' => $now - 3000,
            'updated_at' => $now - 30,
            'started_at' => $now - 4000,
        ]);
        insertTestQueue($uploadHotRunId, $nodeId);
        $uploadHotChild = (array) Capsule::table('ms365_backup_runs')->where('id', $uploadHotRunId)->first();
        $uploadHotQueue = (array) Capsule::table('ms365_job_queue')->where('run_id', $uploadHotRunId)->first();
        assert_true(
            !Ms365BatchRunRepository::isThrottledWaitingAlive($uploadHotChild, $uploadHotQueue, $now),
            'kopia_upload child is not throttle-shielded by hot tenant signal alone',
        );
        assert_true(
            (bool) $shouldReap->invoke(null, $uploadHotChild, $uploadHotQueue, $now),
            'kopia_upload child with stale progress is reapable while tenant is hot',
        );
    } else {
        echo "SKIP: tenant throttle DB fixtures unavailable\n";
    }

    $workloadCap = Ms365EngineConfig::perTenantMaxConcurrentWorkloads();
    if ($tenantRecordId > 0) {
        $capRunIds = [];
        for ($i = 0; $i < $workloadCap; ++$i) {
            $rid = test_uuid('cap-' . $i);
            $capRunIds[] = $rid;
            $runIds[] = $rid;
            insertTestRun($rid, [
                'tenant_record_id' => $tenantRecordId,
                'status' => 'running',
                'updated_at' => $now - 30,
            ]);
            insertTestQueue($rid, 'cap-node-' . $i, [
                'lease_expires_at' => $now + 3600,
                'claimed_at' => $now - 30,
            ]);
        }
        assert_true(
            WorkerClaimService::countRunningForTenant($tenantRecordId) >= $workloadCap,
            'countRunningForTenant reaches workload cap in fixture',
        );
        assert_true(
            WorkerClaimService::countRunningForTenant($tenantRecordId) >= Ms365EngineConfig::perTenantMaxConcurrentWorkloads(),
            'claim gate would block at perTenantMaxConcurrentWorkloads',
        );

        $countBeforeStalled = WorkerClaimService::countRunningForTenant($tenantRecordId);
        $stalledCapRunId = test_uuid('cap-stalled');
        $runIds[] = $stalledCapRunId;
        insertTestRun($stalledCapRunId, [
            'tenant_record_id' => $tenantRecordId,
            'status' => 'running',
            'phase' => 'kopia_upload',
            'last_progress_at' => $now - 3000,
            'updated_at' => $now - 3000,
        ]);
        insertTestQueue($stalledCapRunId, 'cap-node-stalled', [
            'lease_expires_at' => $now + 3600,
            'claimed_at' => $now - 3000,
        ]);
        $countAfterStalled = WorkerClaimService::countRunningForTenant($tenantRecordId);
        assert_true(
            $countAfterStalled === $countBeforeStalled,
            'progress-stale wedged slot is excluded from tenant running count',
        );
    } else {
        echo "SKIP: claim cap fixture needs an active tenant record\n";
    }
} finally {
    cleanupTestRows($runIds, $azureTenantId);
}

exit($failures > 0 ? 1 : 0);
