<?php
declare(strict_types=1);

/**
 * Unit tests for SharePoint restore browse path encoding.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_restore_tree_browse_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\PhysicalKeyHelper;

$failures = 0;

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

function assert_true(bool $value, string $message): void
{
    assert_eq(true, $value, $message);
}

$rawSiteId = 'stchf.sharepoint.com,4258a7df-79cf-40d0-8f64-54b9c55a0af8,e7593a82-5d61-48a6-8b40-cd5f8b654dcf';
$safeSiteId = 'stchf.sharepoint.com_4258a7df-79cf-40d0-8f64-54b9c55a0af8_e7593a82-5d61-48a6-8b40-cd5f8b654dcf';

assert_eq($safeSiteId, PhysicalKeyHelper::storageSafeId($rawSiteId), 'storageSafeId replaces commas in Graph site id');

$tenantId = '4728969e-5eff-4981-b0c6-46eadac79cfe';
$listsPath = $tenantId . '/sites/' . $safeSiteId . '/lists';
$rawListsPath = $tenantId . '/sites/' . $rawSiteId . '/lists';

assert_true(
    preg_match(
        '#/(mail|calendars?|contacts|tasks|onedrive/content|drives/[^/]+(/content)?|groups/[^/]+/(mail|calendars?)|teams/[^/]+(/channels)?|sites/[^/]+(/lists(/[^/]+(/items)?)?)?)$#',
        $listsPath,
    ) === 1,
    'sanitized SharePoint lists path matches missing-workload-root pattern'
);

assert_true(
    preg_match(
        '#/(mail|calendars?|contacts|tasks|onedrive/content|drives/[^/]+(/content)?|groups/[^/]+/(mail|calendars?)|teams/[^/]+(/channels)?|sites/[^/]+(/lists(/[^/]+(/items)?)?)?)$#',
        $rawListsPath,
    ) === 1,
    'raw comma SharePoint lists path matches missing-workload-root pattern'
);

$ref = new ReflectionClass(\Ms365Backup\RestoreTreeBrowseService::class);
$aliasesMethod = $ref->getMethod('sharePointBrowsePathAliases');
$aliasesMethod->setAccessible(true);
$aliases = $aliasesMethod->invoke(null, $rawListsPath, [
    'graph_id' => $rawSiteId,
    'physical_key' => 'site:' . $rawSiteId,
]);

assert_true(in_array($listsPath, $aliases, true), 'sharePointBrowsePathAliases maps raw site id to sanitized lists path');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
