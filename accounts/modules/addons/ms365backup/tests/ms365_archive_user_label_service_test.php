<?php
declare(strict_types=1);

/**
 * Unit tests for Ms365ArchiveUserLabelService.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_archive_user_label_service_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365ArchiveUserLabelService;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
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
        echo "FAIL: {$message}\n";
        echo '  expected: ' . var_export($expected, true) . "\n";
        echo '  actual:   ' . var_export($actual, true) . "\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$guidA = '5fbc7fbb-1234-5678-9abc-def012345678';
$guidB = '15055fe0-abcd-ef01-2345-6789abcdef01';

$items = [
    [
        'path' => "contoso.onmicrosoft.com/users/{$guidA}/mail/Inbox/message.json",
        'source_path' => "contoso.onmicrosoft.com/users/{$guidB}/onedrive/content/Documents/report.pdf",
    ],
];
$targets = [
    ['graph_id' => $guidA, 'resource_type' => 'user'],
];
$run = ['target_graph_id' => ''];

$ids = Ms365ArchiveUserLabelService::collectUserIds($items, $targets, $run);
assert_true(in_array($guidA, $ids, true), 'collectUserIds finds guid from path');
assert_true(in_array($guidB, $ids, true), 'collectUserIds finds guid from source_path');
assert_true(in_array($guidA, $ids, true), 'collectUserIds includes target graph_id');

assert_eq(
    '5fbc7fbb-1234-5678-9abc-def012345678',
    Ms365ArchiveUserLabelService::normalizeGuidKey('5FBC7FBB-1234-5678-9ABC-DEF012345678'),
    'normalizeGuidKey lowercases valid guid'
);
assert_eq('', Ms365ArchiveUserLabelService::normalizeGuidKey('not-a-guid'), 'normalizeGuidKey rejects invalid guid');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
