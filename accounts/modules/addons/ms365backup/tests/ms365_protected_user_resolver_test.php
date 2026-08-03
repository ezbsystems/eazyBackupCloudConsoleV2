<?php
declare(strict_types=1);

/**
 * Protected Users automatic billing tests.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupScope;
use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\Ms365UsageMeter;
use Ms365Backup\ProtectedUserResolver;
use Ms365Backup\TenantResource;

$failures = 0;

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

/** @param array<string, int> $expected */
function assert_reconciliation(array $expected, array $result, string $label): void
{
    $recon = $result['reconciliation'] ?? null;
    assert_true(is_array($recon), "{$label}: reconciliation key present");
    if (!is_array($recon)) {
        return;
    }
    foreach ($expected as $key => $value) {
        assert_eq($value, $recon[$key] ?? null, "{$label}: reconciliation.{$key}");
    }
}

/** @return list<string> */
function makeMemberIds(int $count, string $prefix = 'user-'): array
{
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        $ids[] = $prefix . $i;
    }

    return $ids;
}

/** @param list<string> $memberIds */
function buildTeamInventory(string $teamId, string $groupId, string $label, array $memberIds, array $extraUsers = []): array
{
    $resources = [
        TenantResource::build(TenantResource::TYPE_TEAM, $groupId, $label, null, [
            'id' => $teamId,
            'meta' => [
                'group_id' => $groupId,
                'member_azure_ids' => $memberIds,
                'member_count' => count($memberIds),
            ],
        ]),
    ];

    foreach ($extraUsers as $user) {
        $resources[] = $user;
    }

    return ['resources' => $resources];
}

$teamMembers = makeMemberIds(29);
$inventoryTeamOnly = buildTeamInventory('team:grp-tech', 'grp-tech', 'Technical', $teamMembers);

$scopeTeam = [
    'team:grp-tech' => [
        BackupScope::TEAMS_METADATA => true,
        BackupScope::TEAMS_MESSAGES => true,
        BackupScope::FILES => true,
    ],
];

$result = ProtectedUserResolver::resolve($inventoryTeamOnly, ['team:grp-tech'], $scopeTeam);
assert_eq(29, count($result['protected_azure_ids']), 'team with 29 cached members bills 29 protected users');
assert_eq(29, $result['breakdown'][0]['member_count'] ?? 0, 'breakdown shows 29 members for team');

$overlapUserId = 'user-1';
$inventoryDedup = buildTeamInventory('team:grp-tech', 'grp-tech', 'Technical', $teamMembers, [
    TenantResource::build(TenantResource::TYPE_USER, $overlapUserId, 'Overlap User', null, [
        'id' => 'user:' . $overlapUserId,
        'email' => 'overlap@example.com',
        'meta' => ['user_type' => 'Member'],
    ]),
]);
$scopeBoth = $scopeTeam + [
    'user:' . $overlapUserId => [
        BackupScope::MAIL => true,
        BackupScope::CALENDAR => true,
    ],
];
$resultDedup = ProtectedUserResolver::resolve($inventoryDedup, ['team:grp-tech', 'user:' . $overlapUserId], $scopeBoth);
assert_eq(29, count($resultDedup['protected_azure_ids']), 'user also on team is not double-billed');
assert_reconciliation([
    'direct_appearances' => 1,
    'membership_appearances' => 29,
    'duplicate_appearances_removed' => 1,
    'protected_users' => 29,
], $resultDedup, 'direct user overlapping team');

$groupMembers = makeMemberIds(5, 'grp-user-');
$inventoryTeamAndGroup = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_TEAM, 'grp-shared', 'Shared Team', null, [
            'id' => 'team:grp-shared',
            'meta' => [
                'group_id' => 'grp-shared',
                'member_azure_ids' => $groupMembers,
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_M365_GROUP, 'grp-shared', 'Shared Group', null, [
            'id' => 'group:grp-shared',
            'meta' => [
                'member_azure_ids' => $groupMembers,
            ],
        ]),
    ],
];
$scopeShared = [
    'team:grp-shared' => [BackupScope::FILES => true],
    'group:grp-shared' => [BackupScope::MAIL => true],
];
$resultShared = ProtectedUserResolver::resolve(
    $inventoryTeamAndGroup,
    ['team:grp-shared', 'group:grp-shared'],
    $scopeShared,
);
assert_eq(5, count($resultShared['protected_azure_ids']), 'team + linked group with same members dedupes to 5');
assert_reconciliation([
    'direct_appearances' => 0,
    'membership_appearances' => 10,
    'duplicate_appearances_removed' => 5,
    'protected_users' => 5,
], $resultShared, 'team + group identical members');

$guestId = 'guest-1';
$inventoryGuests = buildTeamInventory('team:grp-g', 'grp-g', 'Guests', ['user-1', $guestId], [
    TenantResource::build(TenantResource::TYPE_USER, $guestId, 'Guest User', null, [
        'id' => 'user:' . $guestId,
        'email' => 'guest_contoso#EXT#@example.com',
        'meta' => ['user_type' => 'Guest'],
    ]),
    TenantResource::build(TenantResource::TYPE_MAILBOX, 'shared-1', 'Shared Mailbox', null, [
        'id' => 'mailbox:shared-1',
        'email' => 'shared@example.com',
        'meta' => ['user_type' => 'Member'],
    ]),
]);
$scopeGuests = [
    'team:grp-g' => [BackupScope::TEAMS_METADATA => true],
    'mailbox:shared-1' => [BackupScope::MAIL => true],
];
$resultGuests = ProtectedUserResolver::resolve(
    $inventoryGuests,
    ['team:grp-g', 'mailbox:shared-1'],
    $scopeGuests,
);
assert_true(!in_array($guestId, $resultGuests['protected_azure_ids'], true), 'guest user is not billed via team membership');
assert_true(!in_array('shared-1', $resultGuests['protected_azure_ids'], true), 'shared mailbox does not bill when personally selected');
assert_eq(1, count($resultGuests['protected_azure_ids']), 'team member user-1 only = 1');

$inventoryLicensedMailbox = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'lic-shared', 'Licensed Shared', null, [
            'id' => 'mailbox:lic-shared',
            'email' => 'licensed-shared@example.com',
            'meta' => ['user_type' => 'Member', 'has_assigned_license' => true],
        ]),
    ],
];
$resultLicensedMailbox = ProtectedUserResolver::resolve(
    $inventoryLicensedMailbox,
    ['mailbox:lic-shared'],
    ['mailbox:lic-shared' => [BackupScope::MAIL => true]],
);
assert_eq(0, count($resultLicensedMailbox['protected_azure_ids']), 'shared mailbox never bills when personally selected');
assert_true(!in_array('lic-shared', $resultLicensedMailbox['protected_azure_ids'], true), 'mailbox Azure ID is not protected');

$channelTeamMembers = makeMemberIds(3);
$inventoryChannel = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_TEAM, 'grp-ch', 'Channel Team', null, [
            'id' => 'team:grp-ch',
            'meta' => [
                'group_id' => 'grp-ch',
                'member_azure_ids' => $channelTeamMembers,
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_TEAM_CHANNEL, 'grp-ch:chan-1', 'General', 'team:grp-ch', [
            'id' => 'channel:grp-ch:chan-1',
            'parent_id' => 'team:grp-ch',
            'meta' => ['group_id' => 'grp-ch'],
        ]),
    ],
];
$scopeChannel = [
    'channel:grp-ch:chan-1' => [BackupScope::TEAMS_MESSAGES => true],
];
$resultChannel = ProtectedUserResolver::resolve($inventoryChannel, ['channel:grp-ch:chan-1'], $scopeChannel);
assert_eq(3, count($resultChannel['protected_azure_ids']), 'channel-only selection inherits team members');

$inventorySite = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'site-1', 'Standalone Site', null, [
            'id' => 'site:site-1',
            'meta' => [
                'member_azure_ids' => ['sp-user-1', 'sp-user-2', 'guest-sp'],
                'member_count' => 3,
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'guest-sp', 'SP Guest', null, [
            'id' => 'user:guest-sp',
            'email' => 'guest_sp#EXT#@example.com',
            'meta' => ['user_type' => 'Guest'],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'sp-user-1', 'SP User 1', null, [
            'id' => 'user:sp-user-1',
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'sp-user-2', 'SP User 2', null, [
            'id' => 'user:sp-user-2',
            'meta' => ['user_type' => 'Member'],
        ]),
    ],
];
$scopeSite = [
    'site:site-1' => [BackupScope::FILES => true],
];
$resultSite = ProtectedUserResolver::resolve($inventorySite, ['site:site-1'], $scopeSite);
assert_eq(2, count($resultSite['protected_azure_ids']), 'sharepoint site members exclude guests');
assert_true(!in_array('guest-sp', $resultSite['protected_azure_ids'], true), 'sharepoint guest member is not billed');

// Cross-source dedupe: personal + team + site
$alice = 'alice-1';
$inventoryCross = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_USER, $alice, 'Alice', null, [
            'id' => 'user:' . $alice,
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_TEAM, 'grp-cross', 'Cross Team', null, [
            'id' => 'team:grp-cross',
            'meta' => [
                'group_id' => 'grp-cross',
                'member_azure_ids' => [$alice, 'bob-1'],
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'site-cross', 'Cross Site', null, [
            'id' => 'site:site-cross',
            'meta' => [
                'member_azure_ids' => [$alice, 'carol-1'],
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'bob-1', 'Bob', null, [
            'id' => 'user:bob-1',
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'carol-1', 'Carol', null, [
            'id' => 'user:carol-1',
            'meta' => ['user_type' => 'Member'],
        ]),
    ],
];
$scopeCross = [
    'user:' . $alice => [BackupScope::MAIL => true],
    'team:grp-cross' => [BackupScope::TEAMS_MESSAGES => true],
    'site:site-cross' => [BackupScope::FILES => true],
];
$resultCross = ProtectedUserResolver::resolve(
    $inventoryCross,
    ['user:' . $alice, 'team:grp-cross', 'site:site-cross'],
    $scopeCross,
);
assert_eq(3, count($resultCross['protected_azure_ids']), 'alice+bob+carol deduped across personal/team/site');
assert_reconciliation([
    'direct_appearances' => 1,
    'membership_appearances' => 4,
    'duplicate_appearances_removed' => 2,
    'protected_users' => 3,
], $resultCross, 'direct + team + sharepoint cross-source');

// Personally selected guest
$inventoryGuestPersonal = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_USER, 'guest-solo', 'Solo Guest', null, [
            'id' => 'user:guest-solo',
            'email' => 'solo#EXT#@example.com',
            'meta' => ['user_type' => 'Guest'],
        ]),
    ],
];
$resultGuestPersonal = ProtectedUserResolver::resolve(
    $inventoryGuestPersonal,
    ['user:guest-solo'],
    ['user:guest-solo' => [BackupScope::MAIL => true]],
);
assert_eq(0, count($resultGuestPersonal['protected_azure_ids']), 'personally selected guest is not billed');

// Room-style mailbox (option 2: all TYPE_MAILBOX treated as shared; true room-never-bill awaits Places API)
$inventoryRoom = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'room-1', 'Conference Room', null, [
            'id' => 'mailbox:room-1',
            'email' => 'room1@example.com',
            'meta' => ['user_type' => ''],
        ]),
    ],
];
$resultRoomMailbox = ProtectedUserResolver::resolve(
    $inventoryRoom,
    ['mailbox:room-1'],
    ['mailbox:room-1' => [BackupScope::CALENDAR => true]],
);
assert_eq(0, count($resultRoomMailbox['protected_azure_ids']), 'room-style shared mailbox does not bill when personally selected');

// Select-all-style: many exempt mailboxes + N users
$selectAllUsers = [];
$selectAllScopes = [];
$selectAllExempt = [];
for ($i = 1; $i <= 10; $i++) {
    $uid = 'sel-user-' . $i;
    $selectAllUsers[] = TenantResource::build(TenantResource::TYPE_USER, $uid, 'User ' . $i, null, [
        'id' => 'user:' . $uid,
        'meta' => ['user_type' => 'Member'],
    ]);
    $selectAllScopes['user:' . $uid] = [BackupScope::MAIL => true];
}
for ($i = 1; $i <= 50; $i++) {
    $mid = 'sel-mbox-' . $i;
    $mboxId = 'mailbox:' . $mid;
    $selectAllUsers[] = TenantResource::build(TenantResource::TYPE_MAILBOX, $mid, 'Mailbox ' . $i, null, [
        'id' => $mboxId,
        'email' => "helpdesk{$i}@example.com",
        'meta' => ['user_type' => 'SharedMailbox'],
    ]);
    $selectAllScopes[$mboxId] = [BackupScope::MAIL => true];
    $selectAllExempt[] = $mboxId;
}
$inventorySelectAll = ['resources' => $selectAllUsers];
$selectAllIds = array_keys($selectAllScopes);
$resultSelectAll = ProtectedUserResolver::resolve(
    $inventorySelectAll,
    $selectAllIds,
    $selectAllScopes,
    null,
    $selectAllExempt,
    true,
);
assert_eq(10, count($resultSelectAll['protected_azure_ids']), 'select-all bills TYPE_USER members only');

$selectAllUsers[] = TenantResource::build(TenantResource::TYPE_MAILBOX, 'guest-mbox', 'Guest Mailbox', null, [
    'id' => 'mailbox:guest-mbox',
    'email' => 'guest_ext#EXT#@example.com',
    'meta' => ['user_type' => 'Guest'],
]);
$selectAllScopes['mailbox:guest-mbox'] = [BackupScope::MAIL => true];
$inventorySelectAll = ['resources' => $selectAllUsers];

// Membership list containing mailbox Azure ID does not add mailbox via membership
$inventoryTeamWithMailboxMember = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_TEAM, 'grp-mbox', 'Team With Mailbox', null, [
            'id' => 'team:grp-mbox',
            'meta' => [
                'group_id' => 'grp-mbox',
                'member_azure_ids' => ['member-user-1', 'mbox-member-1'],
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'member-user-1', 'Member User', null, [
            'id' => 'user:member-user-1',
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'mbox-member-1', 'Shared In Team', null, [
            'id' => 'mailbox:mbox-member-1',
            'email' => 'sharedinteam@example.com',
        ]),
    ],
];
$resultTeamMailboxMember = ProtectedUserResolver::resolve(
    $inventoryTeamWithMailboxMember,
    ['team:grp-mbox'],
    ['team:grp-mbox' => [BackupScope::TEAMS_MESSAGES => true]],
);
assert_true(in_array('member-user-1', $resultTeamMailboxMember['protected_azure_ids'], true), 'team member user bills');
assert_true(!in_array('mbox-member-1', $resultTeamMailboxMember['protected_azure_ids'], true), 'mailbox Azure ID in team roster does not bill via membership');
assert_eq(1, count($resultTeamMailboxMember['protected_azure_ids']), 'membership excludes mailbox principals');

// billing_exempt_resource_ids are ignored — mailboxes never bill
$inventoryMailboxBilling = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_USER, 'base-user', 'Base User', null, [
            'id' => 'user:base-user',
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'receipts', 'Receipts', null, [
            'id' => 'mailbox:receipts',
            'email' => 'receipts@example.com',
        ]),
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'referrals', 'Referrals', null, [
            'id' => 'mailbox:referrals',
            'email' => 'referrals@example.com',
        ]),
    ],
];
$scopeMailboxBilling = [
    'user:base-user' => [BackupScope::MAIL => true],
    'mailbox:receipts' => [BackupScope::MAIL => true],
    'mailbox:referrals' => [BackupScope::MAIL => true],
];
$resultMailboxBilling = ProtectedUserResolver::resolve(
    $inventoryMailboxBilling,
    array_keys($scopeMailboxBilling),
    $scopeMailboxBilling,
    null,
    ['mailbox:receipts'],
    true,
);
assert_eq(1, count($resultMailboxBilling['protected_azure_ids']), 'selected shared mailboxes do not bill; user only');
assert_true(!in_array('receipts', $resultMailboxBilling['protected_azure_ids'], true), 'shared mailbox never bills');
assert_true(!in_array('referrals', $resultMailboxBilling['protected_azure_ids'], true), 'unlicensed shared mailbox never bills');

$inventoryPending = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_TEAM, 'grp-pending', 'Pending Team', null, [
            'id' => 'team:grp-pending',
            'meta' => ['group_id' => 'grp-pending'],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'solo-1', 'Solo User', null, [
            'id' => 'user:solo-1',
            'meta' => ['user_type' => 'Member'],
        ]),
    ],
];
$scopePending = [
    'team:grp-pending' => [BackupScope::TEAMS_MESSAGES => true],
    'user:solo-1' => [BackupScope::MAIL => true],
];
$resultPending = ProtectedUserResolver::resolve(
    $inventoryPending,
    ['team:grp-pending', 'user:solo-1'],
    $scopePending,
    null,
);
assert_true($resultPending['member_resolution_pending'], 'unresolved team membership sets pending flag');
assert_eq(1, count($resultPending['protected_azure_ids']), 'unresolved membership counts resolved data only');
assert_reconciliation([
    'direct_appearances' => 1,
    'membership_appearances' => 0,
    'duplicate_appearances_removed' => 0,
    'protected_users' => 1,
], $resultPending, 'unresolved membership');

$inventoryEmptyGroupCache = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_M365_GROUP, 'grp-empty', 'Empty Group', null, [
            'id' => 'group:grp-empty',
            'meta' => [
                'member_azure_ids' => [],
                'members_fetched_at' => '2026-07-22T12:00:00Z',
            ],
        ]),
    ],
];
$scopeEmptyGroup = [
    'group:grp-empty' => [BackupScope::MAIL => true],
];
$resultEmptyGroup = ProtectedUserResolver::resolve(
    $inventoryEmptyGroupCache,
    ['group:grp-empty'],
    $scopeEmptyGroup,
    null,
);
assert_eq(0, count($resultEmptyGroup['protected_azure_ids']), 'empty cached group members resolves to zero without Graph');
assert_true(!$resultEmptyGroup['member_resolution_pending'], 'empty cached group is not pending');

$selectAllPayload = CustomerSelectionCodec::selectAllFromInventory($inventorySelectAll);
assert_eq(0, count($selectAllPayload['billing_exempt_resource_ids']), 'selectAllFromInventory returns empty billing_exempt list');
$resultSelectAllCodec = ProtectedUserResolver::resolve(
    $inventorySelectAll,
    $selectAllPayload['selected_resource_ids'],
    $selectAllPayload['scope_overrides'],
    null,
    $selectAllPayload['billing_exempt_resource_ids'],
    true,
);
assert_eq(10, count($resultSelectAllCodec['protected_azure_ids']), 'select all bills TYPE_USER members only');

$measure = Ms365UsageMeter::measureSelection($inventoryTeamOnly, ['team:grp-tech'], $scopeTeam);
assert_eq(29, $measure['protected_users'], 'Ms365UsageMeter::measureSelection matches resolver for team-only');
assert_reconciliation([
    'direct_appearances' => 0,
    'membership_appearances' => 29,
    'duplicate_appearances_removed' => 0,
    'protected_users' => 29,
], $measure, 'Ms365UsageMeter passes reconciliation');

echo $failures === 0 ? "\nAll tests passed.\n" : "\n{$failures} test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
