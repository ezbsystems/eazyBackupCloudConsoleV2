<?php
declare(strict_types=1);

/**
 * Restore job fan-out and source reference validation.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_restore_job_service_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\RestoreJobService;
use Ms365Backup\SharePointShardSourceResolver;

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

function assert_eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

function assert_throws(callable $fn, string $message): void
{
    global $failures;
    try {
        $fn();
        echo "FAIL: {$message} (expected exception)\n";
        ++$failures;
    } catch (\Throwable $e) {
        echo "OK: {$message}\n";
    }
}

$batchId = 'batch-restore-fanout-test';
$tenant = ['id' => 1, 'whmcs_client_id' => 1];
$logicalFolder = 'contoso.onmicrosoft.com/sites/site-safe/drives/drive-safe/content';

SharePointShardSourceResolver::seedBatchChildrenCache($batchId, [
    [
        'id' => 'child-shard-5',
        'status' => 'success',
        'e3_batch_run_id' => $batchId,
        'physical_key' => 'drive:drive-safe#shard:5',
        'manifest_id' => 'manifest-5',
        'scope_json' => json_encode(['_site_id' => 'site-graph', '_drive_id' => 'drive-safe']),
    ],
    [
        'id' => 'child-shard-11',
        'status' => 'success',
        'e3_batch_run_id' => $batchId,
        'physical_key' => 'drive:drive-safe#shard:11',
        'manifest_id' => 'manifest-11',
        'scope_json' => json_encode(['_site_id' => 'site-graph', '_drive_id' => 'drive-safe']),
    ],
]);

$folderSelection = [
    'type' => 'folder',
    'path_prefix' => $logicalFolder . '/',
    'child_run_id' => 'child-shard-5',
    'manifest_id' => 'manifest-5',
    'source_refs' => [
        [
            'child_run_id' => 'child-shard-5',
            'manifest_id' => 'manifest-5',
            'source_path' => 'content',
        ],
        [
            'child_run_id' => 'child-shard-11',
            'manifest_id' => 'manifest-11',
            'source_path' => '.shards/11',
        ],
    ],
];

$expanded = RestoreJobService::expandAndValidateSelectionItems($batchId, [$folderSelection], $tenant);
assert_eq(2, count($expanded), 'folder selection fans out to one item per shard');
assert_eq('manifest-5', $expanded[0]['manifest_id'], 'first shard manifest preserved');
assert_eq('content', $expanded[0]['source_path'], 'first shard source path preserved');
assert_eq($logicalFolder, $expanded[0]['logical_path'], 'first shard logical path preserved');
assert_eq('manifest-11', $expanded[1]['manifest_id'], 'second shard manifest preserved');
assert_eq('.shards/11', $expanded[1]['source_path'], 'second shard source path preserved');

$tampered = $folderSelection;
$tampered['source_refs'][1]['manifest_id'] = 'forged-manifest';
assert_throws(
    static function () use ($batchId, $tampered, $tenant): void {
        RestoreJobService::expandAndValidateSelectionItems($batchId, [$tampered], $tenant);
    },
    'tampered source reference rejected'
);

$fileSelection = [
    'type' => 'file',
    'path' => $logicalFolder . '/doc-a.pdf',
    'source_refs' => [
        [
            'child_run_id' => 'child-shard-5',
            'manifest_id' => 'manifest-5',
            'source_path' => 'content/doc-a.pdf',
        ],
    ],
];
$fileExpanded = RestoreJobService::expandAndValidateSelectionItems($batchId, [$fileSelection], $tenant);
assert_eq(1, count($fileExpanded), 'file selection stays single item');
assert_eq('content/doc-a.pdf', $fileExpanded[0]['source_path'], 'file keeps primary source path');
assert_eq($logicalFolder . '/doc-a.pdf', $fileExpanded[0]['logical_path'], 'file keeps logical path');

SharePointShardSourceResolver::clearBatchChildrenCache();

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
