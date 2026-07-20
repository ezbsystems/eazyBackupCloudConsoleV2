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

$resolveLabel = $ref->getMethod('resolveSharePointDriveLabel');
$resolveLabel->setAccessible(true);
$driveId = 'b!4QhyKa8-tEWynEClEl1o_5NqbjTYb1VGsOSs-ZXNBet47NJxJZINR4Q_sTH8rPRj';
$drivePath = $tenantId . '/sites/' . $safeSiteId . '/drives/' . $driveId;
$childRun = [
    'scope_json' => json_encode([
        '_drive_id' => $driveId,
        '_drive_display_name' => 'Shared Documents',
    ], JSON_THROW_ON_ERROR),
];
assert_eq(
    'Shared Documents',
    $resolveLabel->invoke(null, $driveId, $driveId, $drivePath, $childRun),
    'resolveSharePointDriveLabel uses scope _drive_display_name'
);

$drivesPath = $tenantId . '/sites/' . $safeSiteId . '/drives';
assert_true(
    preg_match(
        '#/(mail|calendars?|contacts|tasks|onedrive/content|drives/[^/]+(/content)?|groups/[^/]+/(mail|calendars?)|teams/[^/]+(/channels)?|sites/[^/]+(/drives(/[^/]+(/content)?)?|(/lists(/[^/]+(/items)?)?)?)?)$#',
        $drivesPath,
    ) === 1,
    'sanitized SharePoint drives path matches missing-workload-root pattern'
);

$resolveMailLabel = $ref->getMethod('resolveMailOpaqueLabel');
$resolveMailLabel->setAccessible(true);
$shouldHide = $ref->getMethod('shouldHideEntry');
$shouldHide->setAccessible(true);
$enrichEntries = $ref->getMethod('enrichEntries');
$enrichEntries->setAccessible(true);

$opaqueMsgId = 'AAMkAGVjZGNkNjgyLWI0ZWUtNDRjMy1iNzc3LWM2MmUzYzZlOGJmYwBGAAAAAAB3V4t7mfolRqmlVW5Vax4UBwCZGheBG4SjR6g15N32C-o8AAAAAAEGAACZGheBG4SjR6g15N32C-o8AAJBSNl-AAA=';
$mailInboxPath = $tenantId . '/users/user-1/mail/inbox';
$mailMsgPath = $mailInboxPath . '/' . $opaqueMsgId;

assert_eq(
    '(No subject)',
    $resolveMailLabel->invoke(null, $opaqueMsgId, $opaqueMsgId . '.json', $mailInboxPath, false),
    'opaque mail JSON label is replaced with (No subject)'
);

assert_eq(
    'Quarterly review',
    $resolveMailLabel->invoke(null, 'Quarterly review', $opaqueMsgId . '.json', $mailInboxPath, false),
    'valid mail subject label passes through unchanged'
);

assert_eq(
    'Mail folder',
    $resolveMailLabel->invoke(null, $opaqueMsgId, $opaqueMsgId, $tenantId . '/users/user-1/mail/' . $opaqueMsgId, true),
    'opaque mailbox folder label becomes Mail folder'
);

assert_eq(
    'Email message',
    $resolveMailLabel->invoke(null, $opaqueMsgId, $opaqueMsgId, $mailMsgPath, true),
    'opaque attachment-bearing message folder falls back to Email message'
);

assert_eq(
    'Project kickoff',
    $resolveMailLabel->invoke(null, 'Project kickoff', $opaqueMsgId, $mailInboxPath, true),
    'attachment message folder keeps worker-provided subject'
);

assert_eq(
    'Attachments',
    $resolveMailLabel->invoke(null, 'Folder', 'attachments', $mailMsgPath, true),
    'attachments container is labeled Attachments'
);

assert_true($shouldHide->invoke(null, 'folders.json'), 'folders.json is hidden from browse results');
assert_true($shouldHide->invoke(null, '_browse.json'), '_browse.json is hidden from browse results');

$enriched = $enrichEntries->invoke(null, [
    [
        'name' => $opaqueMsgId . '.json',
        'label' => $opaqueMsgId,
        'path' => $mailInboxPath . '/' . $opaqueMsgId . '.json',
        'has_children' => false,
    ],
    [
        'name' => 'folders.json',
        'label' => 'folders.json',
        'path' => $mailInboxPath . '/folders.json',
        'has_children' => false,
    ],
], $mailInboxPath, null);
assert_eq(1, count($enriched), 'enrichEntries drops hidden catalog files');
assert_eq('(No subject)', $enriched[0]['label'] ?? '', 'enrichEntries applies mail opaque-label guard');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
