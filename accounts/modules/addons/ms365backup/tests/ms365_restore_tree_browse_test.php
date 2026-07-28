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

$bangDriveId = 'b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF24Sz0cFE5BDR7H4es0msZTd';
assert_eq($bangDriveId, PhysicalKeyHelper::pathSafeId($bangDriveId), 'pathSafeId preserves bang in SharePoint drive id');
assert_eq(
    'b_Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF24Sz0cFE5BDR7H4es0msZTd',
    PhysicalKeyHelper::storageSafeId($bangDriveId),
    'storageSafeId replaces bang in SharePoint drive id'
);

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

assert_eq(
    'v23-sharepoint-drive-safeid',
    $ref->getConstant('BROWSE_CACHE_NAMESPACE'),
    'browse cache namespace is v23-sharepoint-drive-safeid'
);

$aliasesMethod = $ref->getMethod('sharePointBrowsePathAliases');
$aliasesMethod->setAccessible(true);
$aliases = $aliasesMethod->invoke(null, $rawListsPath, [
    'graph_id' => $rawSiteId,
    'physical_key' => 'site:' . $rawSiteId,
]);

assert_true(in_array($listsPath, $aliases, true), 'sharePointBrowsePathAliases maps raw site id to sanitized lists path');

$driveAliasesMethod = $ref->getMethod('sharePointDrivePathAliases');
$driveAliasesMethod->setAccessible(true);
$bangDriveId = 'b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF24Sz0cFE5BDR7H4es0msZTd';
$safeDriveId = PhysicalKeyHelper::storageSafeId($bangDriveId);
$drivesRoot = $tenantId . '/sites/' . $safeSiteId . '/drives';
$driveAliases = $driveAliasesMethod->invoke(null, $drivesRoot, [
    'physical_key' => 'drive:' . $bangDriveId,
    'scope_json' => json_encode(['_drive_id' => $bangDriveId], JSON_THROW_ON_ERROR),
]);
$wantBangContent = $drivesRoot . '/' . $bangDriveId . '/content';
$wantSafeContent = $drivesRoot . '/' . $safeDriveId . '/content';
assert_true(in_array($wantBangContent, $driveAliases, true), 'sharePointDrivePathAliases includes Go-safeID bang drive content path');
assert_true(in_array($wantSafeContent, $driveAliases, true), 'sharePointDrivePathAliases still includes storageSafeId drive content path');

$wrongSegPath = $drivesRoot . '/' . $safeDriveId . '/content';
$segAliases = $driveAliasesMethod->invoke(null, $wrongSegPath, [
    'physical_key' => 'drive:' . $bangDriveId,
    'scope_json' => json_encode(['_drive_id' => $bangDriveId], JSON_THROW_ON_ERROR),
]);
assert_true(in_array($wantBangContent, $segAliases, true), 'sharePointDrivePathAliases remaps b_ drive segment to b!');

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
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, $opaqueMsgId, $opaqueMsgId, $mailMsgPath, true),
    'opaque attachment-bearing message folder falls back to orphan attachment label'
);

assert_eq(
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, 'Email message', $opaqueMsgId, $mailMsgPath, true),
    'generic Email message on attachment folder is replaced with orphan attachment label'
);

// resolveMailOpaqueLabel() cannot distinguish a real message subject of "Email message" or
// "Mail folder" from stale worker generic fallbacks without reading snapshot metadata.
// On attachment-message folder paths those exact strings are intentionally rewritten.

assert_eq(
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, 'Mail folder', $opaqueMsgId, $mailMsgPath, true),
    'generic Mail folder on attachment folder is replaced with orphan attachment label'
);

assert_eq(
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, '', $opaqueMsgId, $mailMsgPath, true),
    'empty label on attachment folder becomes orphan attachment label'
);

assert_eq(
    'Project kickoff',
    $resolveMailLabel->invoke(null, 'Project kickoff', $opaqueMsgId, $mailMsgPath, true),
    'current layout attachment message folder keeps worker-provided subject'
);

$historicalMailMsgPath = $tenantId . '/users/user-1/mail/messages/inbox/' . $opaqueMsgId;

assert_eq(
    'Project kickoff',
    $resolveMailLabel->invoke(null, 'Project kickoff', $opaqueMsgId, $historicalMailMsgPath, true),
    'historical layout attachment message folder keeps worker-provided subject'
);

assert_eq(
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, $opaqueMsgId, $opaqueMsgId, $historicalMailMsgPath, true),
    'historical layout opaque attachment-bearing message folder falls back to orphan attachment label'
);

assert_eq(
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, 'Email message', $opaqueMsgId, $historicalMailMsgPath, true),
    'historical layout generic Email message on attachment folder is replaced with orphan attachment label'
);

assert_eq(
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, 'Mail folder', $opaqueMsgId, $historicalMailMsgPath, true),
    'historical layout generic Mail folder on attachment folder is replaced with orphan attachment label'
);

assert_eq(
    'Message attachments (metadata unavailable)',
    $resolveMailLabel->invoke(null, '', $opaqueMsgId, $historicalMailMsgPath, true),
    'historical layout empty label on attachment folder becomes orphan attachment label'
);

$mailMsgJsonPath = $mailInboxPath . '/' . $opaqueMsgId . '.json';

assert_eq(
    'Email message',
    $resolveMailLabel->invoke(null, 'Email message', $opaqueMsgId . '.json', $mailMsgJsonPath, false),
    'generic Email message on message JSON file is not rewritten to orphan attachment label'
);

$isMailMsgAttachmentFolder = $ref->getMethod('isMailMessageAttachmentFolderPath');
$isMailMsgAttachmentFolder->setAccessible(true);
assert_true(
    $isMailMsgAttachmentFolder->invoke(null, $historicalMailMsgPath, $opaqueMsgId),
    'isMailMessageAttachmentFolderPath recognizes historical mail/messages layout'
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
