<?php
declare(strict_types=1);

/**
 * Personal backup selectability (non-backupable directory accounts).
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_personal_backup_selectability_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\ProtectedUserResolver;
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

/** NGI-style service account: unlicensed, empty mail, no OneDrive. */
$dirSyncId = 'dirsync-service';
$licensedUserId = 'licensed-user-1';
$inventory = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_USER, $dirSyncId, 'DirSync service account', null, [
            'id' => 'user:' . $dirSyncId,
            'meta' => ['user_type' => 'Member', 'mail' => '', 'has_assigned_license' => false],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, $licensedUserId, 'Licensed User', null, [
            'id' => 'user:' . $licensedUserId,
            'email' => 'user@example.com',
            'meta' => ['user_type' => 'Member', 'mail' => 'user@example.com', 'has_assigned_license' => true],
        ]),
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'noreply', 'no-reply', null, [
            'id' => 'mailbox:noreply',
            'meta' => ['user_type' => 'Member', 'mail' => '', 'has_assigned_license' => false],
        ]),
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'helpdesk', 'Helpdesk', null, [
            'id' => 'mailbox:helpdesk',
            'email' => 'helpdesk@example.com',
            'meta' => ['mail' => 'helpdesk@example.com'],
        ]),
    ],
];

$resources = TenantResource::enrichPersonalBackupSelectability($inventory['resources']);

$dirSync = $resources[0];
assert_true(($dirSync['selectable'] ?? true) === false, 'DirSync row is not selectable');
assert_eq('No mailbox or OneDrive to back up', $dirSync['disabled_reason'] ?? '', 'DirSync disabled reason');

$licensed = $resources[1];
assert_true(($licensed['selectable'] ?? false) === true, 'licensed user is selectable');

$noReply = $resources[2];
assert_true(($noReply['selectable'] ?? true) === false, 'empty-mail mailbox is not selectable');

$helpdesk = $resources[3];
assert_true(($helpdesk['selectable'] ?? false) === true, 'mailbox with mail is selectable');

assert_true(
    !TenantResource::isPersonallyBackupable($dirSync, $resources),
    'isPersonallyBackupable false for DirSync'
);
assert_true(
    TenantResource::isPersonallyBackupable($licensed, $resources),
    'isPersonallyBackupable true for licensed user'
);

$selectAll = CustomerSelectionCodec::selectAllFromInventory(['resources' => $resources]);
assert_true(
    !in_array('user:' . $dirSyncId, $selectAll['selected_resource_ids'], true),
    'select all skips DirSync user'
);
assert_true(
    !in_array('mailbox:noreply', $selectAll['selected_resource_ids'], true),
    'select all skips empty-mail mailbox'
);
assert_true(
    in_array('user:' . $licensedUserId, $selectAll['selected_resource_ids'], true),
    'select all includes licensed user'
);
assert_true(
    in_array('mailbox:helpdesk', $selectAll['selected_resource_ids'], true),
    'select all includes mailbox with mail'
);

$teamId = 'grp-all-users';
$teamInventory = [
    'resources' => array_merge($resources, [
        TenantResource::build(TenantResource::TYPE_TEAM, $teamId, 'All Users', null, [
            'id' => 'team:' . $teamId,
            'meta' => [
                'group_id' => $teamId,
                'member_azure_ids' => [$dirSyncId, $licensedUserId],
            ],
        ]),
    ]),
];
$scopeTeam = [
    'team:' . $teamId => ['teams_messages' => true],
];
$resultTeam = ProtectedUserResolver::resolve(
    $teamInventory,
    ['team:' . $teamId],
    $scopeTeam,
);
assert_eq(1, count($resultTeam['protected_azure_ids']), 'team membership excludes non-backupable DirSync');
assert_true(
    in_array($licensedUserId, $resultTeam['protected_azure_ids'], true),
    'team membership bills licensed user'
);
assert_true(
    !in_array($dirSyncId, $resultTeam['protected_azure_ids'], true),
    'team membership does not bill DirSync'
);

$userWithOdId = 'od-user';
$odInventory = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_USER, $userWithOdId, 'OD User', null, [
            'id' => 'user:' . $userWithOdId,
            'meta' => ['user_type' => 'Member', 'mail' => '', 'has_assigned_license' => false],
        ]),
        TenantResource::build(TenantResource::TYPE_USER_ONEDRIVE, $userWithOdId, 'OD User Drive', 'user:' . $userWithOdId, [
            'id' => 'onedrive:' . $userWithOdId,
            'parent_id' => 'user:' . $userWithOdId,
        ]),
    ],
];
$odResources = TenantResource::enrichPersonalBackupSelectability($odInventory['resources']);
assert_true(
    TenantResource::isPersonallyBackupable($odResources[0], $odResources),
    'user with OneDrive child is backupable without license or mail'
);

echo $failures === 0 ? "\nAll tests passed.\n" : "\n{$failures} test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
