<?php
declare(strict_types=1);

/**
 * Delta reset tombstones and legacy cutoff behavior.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_delta_reset_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\DeltaStateRepository;
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

function ensureDeltaResetsTable(): void
{
    if (Capsule::schema()->hasTable('ms365_delta_resets')) {
        return;
    }
    $sqlFile = dirname(__DIR__) . '/sql/upgrade_phase25_delta_resets.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('Missing upgrade_phase25_delta_resets.sql');
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

function test_uuid(string $suffix): string
{
    $hex = substr(md5('ms365_delta_reset_test_' . $suffix . microtime(true)), 0, 32);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

ensureDeltaResetsTable();
assert_true(DeltaStateRepository::resetsTableReady(), 'ms365_delta_resets table exists');

$tenantRecordId = 999901;
$e3JobId = test_uuid('job');
$physicalKey = 'drive:' . test_uuid('drive');
$now = time();
$resetRows = [];
$runRows = [];
$deltaRows = [];

try {
    $oldFinishedAt = $now - 7200;
    $newFinishedAt = $now - 60;
    $legacyDelta = json_encode(['sharepoint' => [$physicalKey => 'https://graph.test/delta/old']], JSON_UNESCAPED_SLASHES);
    $newDelta = json_encode(['sharepoint' => [$physicalKey => 'https://graph.test/delta/new']], JSON_UNESCAPED_SLASHES);

    $oldRunId = test_uuid('old-run');
    $newRunId = test_uuid('new-run');
    $runRows[] = $oldRunId;
    $runRows[] = $newRunId;
    Capsule::table('ms365_backup_runs')->insert([
        [
            'id' => $oldRunId,
            'status' => 'success',
            'phase' => 'done',
            'physical_key' => $physicalKey,
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => 1,
            'e3_job_id' => $e3JobId,
            'delta_states_json' => $legacyDelta,
            'finished_at' => $oldFinishedAt,
            'created_at' => $oldFinishedAt,
            'updated_at' => $oldFinishedAt,
        ],
        [
            'id' => $newRunId,
            'status' => 'success',
            'phase' => 'done',
            'physical_key' => $physicalKey,
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => 1,
            'e3_job_id' => $e3JobId,
            'delta_states_json' => $newDelta,
            'finished_at' => $newFinishedAt,
            'created_at' => $newFinishedAt,
            'updated_at' => $newFinishedAt,
        ],
    ]);

    DeltaStateRepository::saveStates($tenantRecordId, $physicalKey, [
        'sharepoint' => [$physicalKey => 'https://graph.test/delta/canonical'],
    ], $e3JobId);
    $deltaRows[] = [
        'tenant_record_id' => $tenantRecordId,
        'physical_key' => $physicalKey,
        'e3_job_id' => $e3JobId,
    ];

    $reflection = new ReflectionClass(WorkerClaimService::class);
    $latestMethod = $reflection->getMethod('latestDeltaStates');
    $latestMethod->setAccessible(true);
    $legacyBefore = $latestMethod->invoke(null, $tenantRecordId, $physicalKey, $e3JobId);
    if ($legacyBefore instanceof stdClass) {
        $legacyBefore = (array) $legacyBefore;
    }
    assert_true(
        is_array($legacyBefore)
        && (($legacyBefore['sharepoint'][$physicalKey] ?? '') === 'https://graph.test/delta/new'),
        'legacy fallback prefers newest successful run before reset',
    );

    DeltaStateRepository::recordReset($tenantRecordId, $physicalKey, $e3JobId, 'test reset', 'phpunit');
    $resetAt = (int) Capsule::table('ms365_delta_resets')
        ->where('tenant_record_id', $tenantRecordId)
        ->where('physical_key', $physicalKey)
        ->orderByDesc('reset_at')
        ->value('reset_at');
    $deleted = DeltaStateRepository::clearCanonicalForSource($tenantRecordId, $physicalKey, $e3JobId);
    assert_true($deleted >= 1, 'clearCanonicalForSource deletes canonical rows');
    assert_true(DeltaStateRepository::getStatesForSource($tenantRecordId, $physicalKey, $e3JobId) === [], 'canonical state absent after reset');

    $legacyAfter = $latestMethod->invoke(null, $tenantRecordId, $physicalKey, $e3JobId);
    if ($legacyAfter instanceof stdClass) {
        $legacyAfter = (array) $legacyAfter;
    }
    assert_true(is_array($legacyAfter) && $legacyAfter === [], 'legacy fallback suppressed for runs at or before reset');

    $postResetRunId = test_uuid('post-reset-run');
    $runRows[] = $postResetRunId;
    $postResetFinished = $resetAt + 5;
    Capsule::table('ms365_backup_runs')->insert([
        'id' => $postResetRunId,
        'status' => 'success',
        'phase' => 'done',
        'physical_key' => $physicalKey,
        'tenant_record_id' => $tenantRecordId,
        'whmcs_client_id' => 1,
        'e3_job_id' => $e3JobId,
        'delta_states_json' => json_encode(['sharepoint' => [$physicalKey => 'https://graph.test/delta/post-reset']], JSON_UNESCAPED_SLASHES),
        'finished_at' => $postResetFinished,
        'created_at' => $postResetFinished,
        'updated_at' => $postResetFinished,
    ]);
    $legacyPostReset = $latestMethod->invoke(null, $tenantRecordId, $physicalKey, $e3JobId);
    if ($legacyPostReset instanceof stdClass) {
        $legacyPostReset = (array) $legacyPostReset;
    }
    assert_true(
        is_array($legacyPostReset)
        && (($legacyPostReset['sharepoint'][$physicalKey] ?? '') === 'https://graph.test/delta/post-reset'),
        'successful run after reset may become legacy fallback again',
    );

    DeltaStateRepository::clearCanonicalForSource($tenantRecordId, $physicalKey, $e3JobId);
    $canonicalStates = [
        'sharepoint' => [$physicalKey => 'https://graph.test/delta/canonical-after-reset'],
    ];
    DeltaStateRepository::saveStates($tenantRecordId, $physicalKey, $canonicalStates, $e3JobId);
    $canonical = DeltaStateRepository::getStatesForSource($tenantRecordId, $physicalKey, $e3JobId);
    assert_true(
        ($canonical['sharepoint'][$physicalKey] ?? '') === 'https://graph.test/delta/canonical-after-reset',
        'canonical state after reset takes precedence over legacy fallback',
    );

    $otherJobId = test_uuid('other-job');
    $otherKey = 'drive:' . test_uuid('other-drive');
    DeltaStateRepository::recordReset($tenantRecordId, $otherKey, $otherJobId, 'scope test', 'phpunit');
    $scopedReset = DeltaStateRepository::resetActiveAt($tenantRecordId, $otherKey, $e3JobId);
    assert_true($scopedReset === null, 'reset tombstones are job-scoped');
} finally {
    if ($deltaRows !== []) {
        foreach ($deltaRows as $keys) {
            Capsule::table('ms365_delta_state')
                ->where('tenant_record_id', $keys['tenant_record_id'])
                ->where('physical_key', $keys['physical_key'])
                ->where('e3_job_id', $keys['e3_job_id'])
                ->delete();
        }
    }
    if ($runRows !== []) {
        Capsule::table('ms365_backup_runs')->whereIn('id', $runRows)->delete();
    }
    Capsule::table('ms365_delta_resets')
        ->where('tenant_record_id', $tenantRecordId)
        ->whereIn('physical_key', array_filter([$physicalKey, $otherKey ?? '']))
        ->delete();
}

exit($failures > 0 ? 1 : 0);
