<?php
declare(strict_types=1);

/**
 * MS365 Kopia repo operation claim/progress/reaper tests.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_repo_ops_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365KopiaRepoOperationService;
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
    $hex = substr(md5('ms365_repo_ops_test_' . $suffix . microtime(true)), 0, 32);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

function ensureSchema(): void
{
    if (!function_exists('cloudstorage_ensure_ms365_repo_ops_schema')) {
        require_once dirname(__DIR__, 2) . '/cloudstorage/cloudstorage.php';
    }
    cloudstorage_ensure_ms365_repo_ops_schema('upgrade');
}

function insertRepo(string $repositoryId): int
{
    $now = date('Y-m-d H:i:s');
    $row = [
        'repository_id' => $repositoryId,
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

/** @param array<string, mixed> $payload */
function insertOp(int $repoId, string $opType, string $status, array $payload = [], ?string $updatedAt = null): int
{
    $token = 'test-op-' . substr(md5((string) microtime(true) . random_int(1, 999999)), 0, 24);
    $now = date('Y-m-d H:i:s');
    $row = [
        'repo_id' => $repoId,
        'op_type' => $opType,
        'status' => $status,
        'attempt_count' => 1,
        'operation_token' => $token,
        'payload_json' => json_encode($payload),
        'created_at' => $now,
        'updated_at' => $updatedAt ?? $now,
    ];
    if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
        $row['claimed_by_node_id'] = null;
    }

    return (int) Capsule::table('s3_kopia_repo_operations')->insertGetId($row);
}

ensureSchema();

$repoId = insertRepo('ms365:test-repo-' . substr(md5((string) microtime(true)), 0, 8));
$nodeA = test_uuid('node_a');
$nodeB = test_uuid('node_b');

$runningId = insertOp($repoId, 'maintenance_quick', 'running', ['engine' => 'ms365', 'e3_job_id' => test_uuid('job')]);
if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
    Capsule::table('s3_kopia_repo_operations')->where('id', $runningId)->update(['claimed_by_node_id' => $nodeA]);
    $row = Capsule::table('s3_kopia_repo_operations')->where('id', $runningId)->first();
    assert_true((string) ($row->claimed_by_node_id ?? '') === $nodeA, 'claimed_by_node_id persisted on running op');
}

$opRow = Capsule::table('s3_kopia_repo_operations')->where('id', $runningId)->first();
if (Capsule::schema()->hasTable('s3_kopia_repo_locks') && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::class)) {
    \WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService::acquire(
        $repoId,
        (string) $opRow->operation_token,
        null,
        600
    );
}

$progressOk = Ms365KopiaRepoOperationService::recordProgress($runningId, $nodeA, 'repo_open', [
    'index_blobs_before' => 6000,
    'acquire_ms' => 1200,
]);
assert_true($progressOk, 'progress accepted for claimed node');

$progressRow = Capsule::table('s3_kopia_repo_operations')->where('id', $runningId)->first();
$result = json_decode((string) ($progressRow->result_json ?? '{}'), true);
assert_true(is_array($result) && ($result['phase'] ?? '') === 'repo_open', 'progress merges result_json phase');
assert_true((int) ($result['index_blobs_before'] ?? 0) === 6000, 'progress merges index_blobs_before');

$wrongNode = Ms365KopiaRepoOperationService::recordProgress($runningId, $nodeB, 'heartbeat', []);
assert_true(!$wrongNode, 'progress rejected for wrong node');

$lockBeforeExpiry = 0;
if (Capsule::schema()->hasTable('s3_kopia_repo_locks')) {
    $lockBefore = Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->first();
    $lockBeforeExpiry = $lockBefore && !empty($lockBefore->expires_at) ? strtotime((string) $lockBefore->expires_at) : 0;
}

$renewOk = Ms365KopiaRepoOperationService::recordProgress($runningId, $nodeA, 'heartbeat', ['tick' => 1]);
assert_true($renewOk, 'heartbeat progress accepted');

if (Capsule::schema()->hasTable('s3_kopia_repo_locks')) {
    $lock = Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->first();
    assert_true($lock !== null, 'lock row exists');
    if ($lock !== null && !empty($lock->expires_at) && $lockBeforeExpiry > 0) {
        $afterExpiry = strtotime((string) $lock->expires_at);
        assert_true($afterExpiry >= $lockBeforeExpiry, 'progress renew extends lock expiry');
    }
}

Ms365KopiaRepoOperationService::markComplete($runningId, 'success', ['phase' => 'complete']);
$completed = Capsule::table('s3_kopia_repo_operations')->where('id', $runningId)->first();
if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
    assert_true($completed !== null && ($completed->claimed_by_node_id === null || $completed->claimed_by_node_id === ''), 'complete clears claimed_by_node_id');
}

$staleId = insertOp($repoId, 'maintenance_full', 'running', ['engine' => 'ms365', 'e3_job_id' => test_uuid('job2')], date('Y-m-d H:i:s', time() - 7200));
if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
    Capsule::table('s3_kopia_repo_operations')->where('id', $staleId)->update(['claimed_by_node_id' => $nodeA]);
}
$reaped = Ms365KopiaRepoOperationService::reapStaleRunningOps();
assert_true($reaped >= 1, 'reaper fails stale running op');
$staleRow = Capsule::table('s3_kopia_repo_operations')->where('id', $staleId)->first();
$staleResult = json_decode((string) ($staleRow->result_json ?? '{}'), true);
assert_true((string) ($staleRow->status ?? '') === 'error', 'stale op marked error');
assert_true(($staleResult['error'] ?? '') === 'orphaned_stale', 'stale op result_json error=orphaned_stale');

$freshErrorRepoId = insertRepo('ms365:test-repo-error-' . substr(md5((string) microtime(true)), 0, 8));
$freshErrorId = insertOp($freshErrorRepoId, 'maintenance_quick', 'error', ['engine' => 'ms365', 'e3_job_id' => test_uuid('job3')], date('Y-m-d H:i:s', time() - 3600));
unset($freshErrorId);
$recentSuppress = Capsule::table('s3_kopia_repo_operations')
    ->where('repo_id', $freshErrorRepoId)
    ->whereIn('op_type', ['maintenance_quick', 'maintenance_full'])
    ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 86400))
    ->where(function ($q): void {
        $q->where('status', 'success')
            ->orWhere(function ($q2): void {
                $q2->whereIn('status', ['queued', 'running'])
                    ->where('updated_at', '>=', date('Y-m-d H:i:s', time() - 2700));
            });
    })
    ->exists();
assert_true(!$recentSuppress, 'terminal error orphan does not suppress scheduling query');

$adminRows = \WHMCS\Module\Addon\CloudStorage\Admin\CloudBackupAdminController::getRepoRetentionOps(['limit' => 5]);
assert_true(is_array($adminRows), 'admin getRepoRetentionOps returns array');
if ($adminRows !== []) {
    $first = $adminRows[0];
    assert_true(array_key_exists('phase', $first), 'admin view includes phase summary');
    assert_true(array_key_exists('duration_seconds', $first), 'admin view includes duration_seconds');
}

echo PHP_EOL . ($failures === 0 ? "ALL TESTS PASSED\n" : "FAILURES: {$failures}\n");
exit($failures === 0 ? 0 : 1);
