<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365FleetRepoOpsService;
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

function insertMs365Repo(string $suffix): int
{
    $now = date('Y-m-d H:i:s');
    $row = [
        'repository_id' => 'ms365:fleet-ui-' . $suffix,
        'client_id' => 1,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (Capsule::schema()->hasColumn('s3_kopia_repos', 'vault_policy_version_id')) {
        $policyId = Capsule::table('s3_kopia_policy_versions')->orderBy('id')->value('id');
        if ($policyId) {
            $row['vault_policy_version_id'] = (int) $policyId;
        }
    }
    return (int) Capsule::table('s3_kopia_repos')->insertGetId($row);
}

function insertOp(int $repoId, string $opType, string $status, array $payload = [], ?array $result = null): int
{
    $now = date('Y-m-d H:i:s');
    $row = [
        'repo_id' => $repoId,
        'op_type' => $opType,
        'status' => $status,
        'attempt_count' => 1,
        'operation_token' => 'fleet-test-' . bin2hex(random_bytes(8)),
        'payload_json' => json_encode($payload),
        'result_json' => $result !== null ? json_encode($result) : null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
        $row['claimed_by_node_id'] = $status === 'running' ? '11111111-1111-1111-1111-111111111111' : null;
    }
    return (int) Capsule::table('s3_kopia_repo_operations')->insertGetId($row);
}

/**
 * Delete a test-inserted op immediately so a live worker cannot claim a
 * leftover `queued` row while the rest of this script still runs.
 */
function deleteOp(int $opId): void
{
    if ($opId > 0) {
        Capsule::table('s3_kopia_repo_operations')->where('id', $opId)->delete();
    }
}

$insertedRepoIds = [];
$insertedOpIds = [];

try {
    $repoId = insertMs365Repo(substr(md5((string) microtime(true)), 0, 8));
    $insertedRepoIds[] = $repoId;

    $runningId = insertOp($repoId, 'maintenance_full', 'running', [
        'engine' => 'ms365',
        'e3_job_id' => 'a98f9943-379a-4197-b63e-384aecbedbe7',
    ], ['phase' => 'pre_open', 'index_blobs_before' => 25000]);
    $insertedOpIds[] = $runningId;

    $doneId = insertOp($repoId, 'maintenance_quick', 'success', ['engine' => 'ms365'], [
        'phase' => 'complete',
        'effective_mode' => 'quick',
        'index_blobs_before' => 100,
        'index_blobs_after' => 80,
    ]);
    $insertedOpIds[] = $doneId;

    $list = Ms365FleetRepoOpsService::listForFleet();
    assert_true(is_array($list['active'] ?? null) && is_array($list['recent'] ?? null) && is_array($list['repos'] ?? null), 'listForFleet shape');
    $activeIds = array_column($list['active'], 'id');
    assert_true(in_array($runningId, $activeIds, true), 'running op in active');
    assert_true(!in_array($doneId, $activeIds, true), 'success op not in active');
    $recentIds = array_column($list['recent'], 'id');
    assert_true(in_array($runningId, $recentIds, true) && in_array($doneId, $recentIds, true), 'both in recent');
    $runningRow = null;
    foreach ($list['active'] as $row) {
        if ((int) $row['id'] === $runningId) {
            $runningRow = $row;
            break;
        }
    }
    assert_true(($runningRow['phase'] ?? '') === 'pre_open', 'phase summarized');
    assert_true((int) ($runningRow['index_blobs_before'] ?? 0) === 25000, 'index_blobs_before summarized');
    assert_true(array_key_exists('index_blobs_after', $runningRow ?? []) && $runningRow['index_blobs_after'] === null, 'index_blobs_after stays null when absent (isset, not array_key_exists)');

    $bad = Ms365FleetRepoOpsService::enqueue(999999999, 'maintenance_full');
    assert_true(($bad['ok'] ?? true) === false, 'enqueue rejects unknown repo');

    // Duplicate active-op guard: repo already has a `running` maintenance_full op ($runningId).
    $dup = Ms365FleetRepoOpsService::enqueue($repoId, 'maintenance_full');
    assert_true(($dup['ok'] ?? true) === false, 'enqueue rejects repo with an active op');
    assert_true(($dup['operation_id'] ?? 0) === $runningId, 'guard reports the existing active operation id');
    assert_true(str_contains((string) ($dup['error'] ?? ''), (string) $runningId), 'guard error message mentions existing operation id');

    // Complete the prior op so the success path below is exercised for real.
    Capsule::table('s3_kopia_repo_operations')->where('id', $runningId)->update([
        'status' => 'success',
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $enq = Ms365FleetRepoOpsService::enqueue($repoId, 'maintenance_full');
    assert_true(($enq['ok'] ?? false) === true, 'enqueue succeeds once no active op remains');
    assert_true(($enq['operation_id'] ?? 0) > 0, 'enqueue returns operation_id');
    $enqueuedOpId = (int) ($enq['operation_id'] ?? 0);
    if ($enqueuedOpId > 0) {
        $insertedOpIds[] = $enqueuedOpId;
    }
    $payload = Capsule::table('s3_kopia_repo_operations')->where('id', $enqueuedOpId)->value('payload_json');
    $decoded = json_decode((string) $payload, true);
    assert_true(($decoded['e3_job_id'] ?? '') === 'a98f9943-379a-4197-b63e-384aecbedbe7', 'enqueue copies e3_job_id');
    assert_true(($decoded['engine'] ?? '') === 'ms365', 'enqueue sets engine=ms365');

    // Delete the just-enqueued `queued` op right away so a live worker cannot claim it
    // while the remainder of this script (and any surrounding suite) still runs.
    deleteOp($enqueuedOpId);

    $nonMs = Capsule::table('s3_kopia_repos')->insertGetId([
        'repository_id' => 'other:fleet-ui-' . substr(md5((string) microtime(true)), 0, 6),
        'client_id' => 1,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $insertedRepoIds[] = (int) $nonMs;
    $rej = Ms365FleetRepoOpsService::enqueue((int) $nonMs, 'maintenance_quick');
    assert_true(($rej['ok'] ?? true) === false, 'enqueue rejects non-ms365 repo');
} finally {
    // Always clean up test rows, on both success and failure paths, so nothing
    // synthetic (and nothing worker-claimable) is left behind in the DB.
    if ($insertedOpIds !== []) {
        Capsule::table('s3_kopia_repo_operations')->whereIn('id', array_unique($insertedOpIds))->delete();
    }
    if ($insertedRepoIds !== []) {
        Capsule::table('s3_kopia_repos')->whereIn('id', array_unique($insertedRepoIds))->delete();
    }
}

echo PHP_EOL . ($failures === 0 ? "ALL TESTS PASSED\n" : "FAILURES: {$failures}\n");
exit($failures === 0 ? 0 : 1);
