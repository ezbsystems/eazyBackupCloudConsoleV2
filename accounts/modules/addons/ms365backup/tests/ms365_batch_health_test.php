<?php
declare(strict_types=1);

/**
 * Ms365BatchHealthService — wedge and stalled-workload detection.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_batch_health_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365BatchHealthService;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;

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
    $hex = substr(md5('ms365_batch_health_test_' . $suffix . microtime(true)), 0, 32);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

assert_true(
    Ms365BatchHealthService::isWorkerWedged([
        'disk_critical' => true,
        'claim_admit_rejects' => 0,
        'disk_free_mib' => 60000,
    ]),
    'disk_critical true marks worker wedged'
);

assert_true(
    !Ms365BatchHealthService::isWorkerWedged([
        'disk_critical' => false,
        'claim_admit_rejects' => 12,
        'disk_free_mib' => 2048,
    ]),
    'high admit rejects with low free space is not wedged'
);

assert_true(
    Ms365BatchHealthService::isWorkerWedged([
        'disk_critical' => false,
        'claim_admit_rejects' => 4,
        'disk_free_mib' => 58000,
    ]),
    'high admit rejects with healthy free space marks worker wedged'
);

$warning = Ms365BatchHealthService::formatHealthWarning(true, ['disk_critical' => true], 6);
assert_true(
    is_string($warning)
    && str_contains($warning, 'disk pressure latch')
    && str_contains($warning, '6 workloads with no progress'),
    'health warning combines latch and stalled workloads'
);

assert_true(
    Ms365BatchLiveService::WORKLOAD_ACTIVE_PROGRESS_SECONDS === 180,
    'health service reuses live workload stall threshold'
);

if (Capsule::schema()->hasTable('ms365_backup_runs')
    && Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_batch_run_id')) {
    $batchRunId = test_uuid('stalled');
    $now = time();
    $childId = test_uuid('child');
    Capsule::table('ms365_backup_runs')->insert([
        'id' => $childId,
        'status' => 'running',
        'phase' => 'prior_snapshot',
        'items_done' => 0,
        'items_total' => 10,
        'percent' => 0.0,
        'physical_key' => 'user:health-test',
        'resource_type' => 'user',
        'resource_id' => 'user:health-test',
        'graph_id' => 'health-test',
        'user_display_name' => 'Health Test',
        'backup_path' => '/tmp/ms365-health-test',
        'tenant_record_id' => 999901,
        'whmcs_client_id' => 1,
        'e3_batch_run_id' => $batchRunId,
        'created_at' => $now,
        'updated_at' => $now - Ms365BatchLiveService::WORKLOAD_ACTIVE_PROGRESS_SECONDS - 30,
        'started_at' => $now - Ms365BatchLiveService::WORKLOAD_ACTIVE_PROGRESS_SECONDS - 60,
        'last_progress_at' => $now - Ms365BatchLiveService::WORKLOAD_ACTIVE_PROGRESS_SECONDS - 30,
    ]);
    $stalled = Ms365BatchHealthService::countStalledRunningChildren($batchRunId);
    assert_true($stalled === 1, 'stalled running child counted for batch');

    $freshChildId = test_uuid('fresh');
    Capsule::table('ms365_backup_runs')->insert([
        'id' => $freshChildId,
        'status' => 'running',
        'phase' => 'upload',
        'items_done' => 10,
        'items_total' => 10,
        'percent' => 40.0,
        'physical_key' => 'user:health-fresh',
        'resource_type' => 'user',
        'resource_id' => 'user:health-fresh',
        'graph_id' => 'health-fresh',
        'user_display_name' => 'Health Fresh',
        'backup_path' => '/tmp/ms365-health-fresh',
        'tenant_record_id' => 999901,
        'whmcs_client_id' => 1,
        'e3_batch_run_id' => $batchRunId,
        'created_at' => $now,
        'updated_at' => $now,
        'started_at' => $now - 5,
        // Prior attempt timestamp must not mark a freshly started child stalled.
        'last_progress_at' => $now - Ms365BatchLiveService::WORKLOAD_ACTIVE_PROGRESS_SECONDS - 30,
    ]);
    $stalledAfterFresh = Ms365BatchHealthService::countStalledRunningChildren($batchRunId);
    assert_true($stalledAfterFresh === 1, 'fresh started_at floors stale last_progress_at for health stall count');
    Capsule::table('ms365_backup_runs')->whereIn('id', [$childId, $freshChildId])->delete();
} else {
    echo "SKIP: ms365_backup_runs not available for stalled count integration check\n";
}

exit($failures > 0 ? 1 : 0);
