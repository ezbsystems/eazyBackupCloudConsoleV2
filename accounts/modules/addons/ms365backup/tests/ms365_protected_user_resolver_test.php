<?php
declare(strict_types=1);

/**
 * Protected Objects / member-based metering tests.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupScope;
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
assert_true(in_array($guestId, $resultGuests['protected_azure_ids'], true), 'guest user counted as protected object via team membership');
assert_true(in_array('shared-1', $resultGuests['protected_azure_ids'], true), 'shared mailbox counted when personally selected');
assert_eq(3, count($resultGuests['protected_azure_ids']), 'team member user-1 + guest + shared mailbox = 3');

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
assert_eq(3, count($resultSite['protected_azure_ids']), 'sharepoint site members become protected objects');
assert_true(in_array('guest-sp', $resultSite['protected_azure_ids'], true), 'sharepoint guest member counted');

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
assert_eq(1, count($resultGuestPersonal['protected_azure_ids']), 'personally selected guest is a protected object');

// Room/equipment-style mailbox (TYPE_MAILBOX, no user_type Guest)
$inventoryRoom = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'room-1', 'Conference Room', null, [
            'id' => 'mailbox:room-1',
            'email' => 'room1@example.com',
            'meta' => ['user_type' => ''],
        ]),
    ],
];
$resultRoom = ProtectedUserResolver::resolve(
    $inventoryRoom,
    ['mailbox:room-1'],
    ['mailbox:room-1' => [BackupScope::CALENDAR => true]],
);
assert_eq(1, count($resultRoom['protected_azure_ids']), 'room mailbox personally selected counts as protected object');

$measure = Ms365UsageMeter::measureSelection($inventoryTeamOnly, ['team:grp-tech'], $scopeTeam);
assert_eq(29, $measure['protected_users'], 'Ms365UsageMeter::measureSelection matches resolver for team-only');

echo $failures === 0 ? "\nAll tests passed.\n" : "\n{$failures} test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
