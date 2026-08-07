<?php
declare(strict_types=1);

/**
 * SharePoint Lists opt-in defaults (files on, lists off unless explicit).
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_sharepoint_lists_default_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\AccessResult;
use Ms365Backup\BackupScope;
use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\TenantResource;

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

$siteScope = BackupScope::forResourceType(TenantResource::TYPE_SHAREPOINT_SITE);
assert_true($siteScope->isEnabled(BackupScope::FILES), 'site default files on');
assert_true(!$siteScope->isEnabled(BackupScope::LISTS), 'site default lists off');

$site = [
    'id' => 'site:marketing',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'marketing',
    'display_name' => 'Marketing',
    'access' => [
        'files' => AccessResult::STATUS_AVAILABLE,
        'lists' => AccessResult::STATUS_AVAILABLE,
    ],
];

$selectAll = CustomerSelectionCodec::selectAllFromInventory(['resources' => [$site]]);
$override = $selectAll['scope_overrides']['site:marketing'] ?? [];
assert_true(($override[BackupScope::FILES] ?? false) === true, 'select all sets files on');
assert_true(($override[BackupScope::LISTS] ?? true) === false, 'select all leaves lists off');

$pruned = CustomerSelectionCodec::pruneInaccessibleSiteSelection(
    ['site:marketing'],
    [],
    ['resources' => [$site]],
);
$emptyFlags = $pruned['scope_overrides']['site:marketing'] ?? [];
assert_true(($emptyFlags[BackupScope::FILES] ?? false) === true, 'empty override defaults files on');
assert_true(($emptyFlags[BackupScope::LISTS] ?? true) === false, 'empty override defaults lists off');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
