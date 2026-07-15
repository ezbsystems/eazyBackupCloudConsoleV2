<?php
declare(strict_types=1);

/**
 * MS365 restore destination resolver — path classification and target derivation.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_restore_destination_resolver_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365RestoreDestinationResolver;
use Ms365Backup\PhysicalKeyHelper;
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

$tenant = 'contoso.onmicrosoft.com';
$userId = '086a956e-63af-4a32-8cc4-0b760bbf5bd3';
$siteGraphId = 'stchf.sharepoint.com,4258a7df-79cf-40d0-8f64-54b9c55a0af8,e7593a82-5d61-48a6-8b40-cd5f8b654dcf';
$siteSafe = PhysicalKeyHelper::storageSafeId($siteGraphId);
$driveId = 'b!abc123drive';

$sharePointPath = "{$tenant}/sites/{$siteSafe}/drives/{$driveId}/content/IT Testing/Test Document.docx";
$mailPath = "{$tenant}/users/{$userId}/mail/Inbox/message.json";
$oneDrivePath = "{$tenant}/users/{$userId}/onedrive/content/Documents/report.pdf";

assert_eq(Ms365RestoreDestinationResolver::CLASS_SHAREPOINT, Ms365RestoreDestinationResolver::classifyItemPath($sharePointPath), 'SharePoint path classified');
assert_eq(Ms365RestoreDestinationResolver::CLASS_MAILBOX, Ms365RestoreDestinationResolver::classifyItemPath($mailPath), 'Mailbox path classified');
assert_eq(Ms365RestoreDestinationResolver::CLASS_ONEDRIVE, Ms365RestoreDestinationResolver::classifyItemPath($oneDrivePath), 'OneDrive path classified');

$inventory = [
    [
        'id' => TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteGraphId),
        'graph_id' => $siteGraphId,
        'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
        'display_name' => 'STCHF Site',
    ],
    [
        'id' => TenantResource::makeId(TenantResource::TYPE_USER, $userId),
        'graph_id' => $userId,
        'resource_type' => TenantResource::TYPE_USER,
        'display_name' => 'Sue Timanson',
    ],
];

$sharePointItem = [
    'path' => $sharePointPath,
    'child_run_id' => 'run-sp-1',
];
$mailItem = [
    'path' => $mailPath,
    'child_run_id' => 'run-mail-1',
];
$oneDriveItem = [
    'path' => $oneDrivePath,
    'child_run_id' => 'run-od-1',
];

$spTargets = Ms365RestoreDestinationResolver::deriveOriginalTargets([$sharePointItem], $inventory);
assert_eq(1, count($spTargets), 'SharePoint derive yields one target');
assert_eq(TenantResource::TYPE_SHAREPOINT_SITE, $spTargets[0]['resource_type'], 'SharePoint target type');
assert_eq($siteGraphId, $spTargets[0]['graph_id'], 'SharePoint target graph id resolved from safe segment');
assert_eq($driveId, $spTargets[0]['drive_id'] ?? '', 'SharePoint target includes drive id');

$mailTargets = Ms365RestoreDestinationResolver::deriveOriginalTargets([$mailItem], $inventory);
assert_eq(1, count($mailTargets), 'Mailbox derive yields one target');
assert_eq(TenantResource::TYPE_USER, $mailTargets[0]['resource_type'], 'Mailbox target type');
assert_eq($userId, $mailTargets[0]['graph_id'], 'Mailbox target graph id');

$odTargets = Ms365RestoreDestinationResolver::deriveOriginalTargets([$oneDriveItem], $inventory);
assert_eq(1, count($odTargets), 'OneDrive derive yields one target');
assert_eq(TenantResource::TYPE_USER, $odTargets[0]['resource_type'], 'OneDrive target type');
assert_eq($userId, $odTargets[0]['graph_id'], 'OneDrive target graph id');

assert_true(!Ms365RestoreDestinationResolver::canUseAlternateDestination([$sharePointItem, $mailItem]), 'Mixed mail + SharePoint cannot use alternate');

$mixedTargets = Ms365RestoreDestinationResolver::deriveOriginalTargets([$sharePointItem, $mailItem], $inventory);
assert_eq(2, count($mixedTargets), 'Mixed selection original derive yields two targets');
Ms365RestoreDestinationResolver::assertSelectionCompatible(
    [$sharePointItem, $mailItem],
    $mixedTargets,
    Ms365RestoreDestinationResolver::MODE_ORIGINAL,
    $inventory
);
echo "OK: Mixed selection original mode succeeds with two targets\n";

assert_throws(
    static function () use ($mailItem): void {
        Ms365RestoreDestinationResolver::assertSelectionCompatible(
            [$mailItem],
            [[
                'resource_id' => TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteGraphId),
                'graph_id' => $siteGraphId,
                'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
            ]],
            Ms365RestoreDestinationResolver::MODE_ALTERNATE
        );
    },
    'Alternate mail items + sharepoint_site target rejected'
);

assert_throws(
    static function () use ($sharePointItem, $userId): void {
        Ms365RestoreDestinationResolver::assertSelectionCompatible(
            [$sharePointItem],
            [[
                'resource_id' => TenantResource::makeId(TenantResource::TYPE_USER, $userId),
                'graph_id' => $userId,
                'resource_type' => TenantResource::TYPE_USER,
            ]],
            Ms365RestoreDestinationResolver::MODE_ALTERNATE
        );
    },
    'Alternate SharePoint items + user target rejected'
);

assert_throws(
    static function () use ($sharePointItem, $mailItem, $userId): void {
        Ms365RestoreDestinationResolver::assertSelectionCompatible(
            [$sharePointItem, $mailItem],
            [[
                'resource_id' => TenantResource::makeId(TenantResource::TYPE_USER, $userId),
                'graph_id' => $userId,
                'resource_type' => TenantResource::TYPE_USER,
            ]],
            Ms365RestoreDestinationResolver::MODE_ALTERNATE
        );
    },
    'Mixed selection alternate rejected'
);

$filtered = Ms365RestoreDestinationResolver::filterAlternateTargets(
    $inventory,
    Ms365RestoreDestinationResolver::CLASS_SHAREPOINT
);
assert_eq(1, count($filtered), 'Alternate filter returns only SharePoint sites for SharePoint class');
assert_eq(TenantResource::TYPE_SHAREPOINT_SITE, $filtered[0]['resource_type'], 'Filtered resource is sharepoint_site');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
