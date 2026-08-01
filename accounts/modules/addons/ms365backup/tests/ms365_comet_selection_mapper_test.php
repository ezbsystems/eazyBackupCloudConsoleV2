<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_comet_selection_mapper_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\BackupScope;
use Ms365Backup\Comet\CometSelectionMapper;
use Ms365Backup\TenantResource;

$failures = 0;
function assert_true(bool $c, string $m): void
{
    global $failures;
    if (!$c) {
        echo "FAIL: $m\n";
        ++$failures;
        return;
    }
    echo "OK: $m\n";
}

$userGraph = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
$siteGraph = 'contoso.sharepoint.com,bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb,cccccccc-cccc-cccc-cccc-cccccccccccc';

$inventory = [
    'resources' => [
        [
            'id' => 'user:' . $userGraph,
            'resource_type' => TenantResource::TYPE_USER,
            'graph_id' => $userGraph,
            'display_name' => 'Ada',
            'parent_id' => null,
            'access' => [],
            'meta' => [],
        ],
        [
            'id' => 'onedrive:' . $userGraph,
            'resource_type' => TenantResource::TYPE_USER_ONEDRIVE,
            'graph_id' => $userGraph,
            'display_name' => 'Ada OD',
            'parent_id' => 'user:' . $userGraph,
            'access' => [],
            'meta' => [],
        ],
        [
            'id' => 'site:' . $siteGraph,
            'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
            'graph_id' => $siteGraph,
            'display_name' => 'Site',
            'parent_id' => null,
            'access' => [],
            'meta' => ['web_url' => 'https://contoso.sharepoint.com/sites/x'],
        ],
    ],
];

$parsed = [
    'organization' => false,
    'whole_org' => false,
    'backup_options' => [
        $userGraph => 31,
        $siteGraph => 24,
    ],
    'member_backup_options' => [],
];

$result = CometSelectionMapper::map($parsed, $inventory);
$ids = $result['selected_resource_ids'];
assert_true(in_array('user:' . $userGraph, $ids, true), 'user selected');
assert_true(in_array('onedrive:' . $userGraph, $ids, true), 'onedrive selected');
assert_true(in_array('site:' . $siteGraph, $ids, true), 'site selected');

$userScope = $result['scope_overrides']['user:' . $userGraph] ?? [];
assert_true(
    ($userScope[BackupScope::MAIL] ?? false)
    && ($userScope[BackupScope::CALENDAR] ?? false)
    && ($userScope[BackupScope::CONTACTS] ?? false),
    'user full mailbox scopes'
);

$siteScope = $result['scope_overrides']['site:' . $siteGraph] ?? [];
assert_true(
    ($siteScope[BackupScope::FILES] ?? false) && ($siteScope[BackupScope::LISTS] ?? false),
    'site files+lists'
);

assert_true($result['report']['unmatched_backup_option_keys'] === [], 'no unmatched');
assert_true($result['report']['matched_users'] === 1, 'matched_users=1');
assert_true($result['report']['matched_sites'] === 1, 'matched_sites=1');

$unknown = CometSelectionMapper::map([
    'organization' => false,
    'whole_org' => false,
    'backup_options' => ['ffffffff-ffff-ffff-ffff-ffffffffffff' => 31],
    'member_backup_options' => [],
], $inventory);
assert_true(
    in_array('ffffffff-ffff-ffff-ffff-ffffffffffff', $unknown['report']['unmatched_backup_option_keys'], true),
    'unknown GUID unmatched'
);

// Personal OneDrive site → owning mailbox (James-style)
$jamesGraph = 'fa2a9e71-b6ef-4f0e-ab3f-50c4d8d774b2';
$personalSite = 'contoso-my.sharepoint.com,c3608d35-dd2a-4b16-bd4a-ef027c2b96f0,e1a31aab-58ce-4686-962f-b45e3843b44d';
$jamesInventory = [
    'resources' => [
        [
            'id' => 'user:' . $jamesGraph,
            'resource_type' => TenantResource::TYPE_MAILBOX,
            'graph_id' => $jamesGraph,
            'display_name' => 'James Garcesa',
            'email' => 'James-Garcesa@contoso.com',
            'parent_id' => null,
            'access' => [],
            'meta' => ['has_assigned_license' => false],
        ],
    ],
];
$personalMapped = CometSelectionMapper::map(
    [
        'organization' => false,
        'whole_org' => false,
        'backup_options' => [$personalSite => 24],
        'member_backup_options' => [],
    ],
    $jamesInventory,
    [$personalSite => $jamesGraph],
);
assert_true(
    in_array('user:' . $jamesGraph, $personalMapped['selected_resource_ids'], true),
    'personal site maps to owner mailbox'
);
assert_true(
    ($personalMapped['report']['personal_sites_mapped_to_users'] ?? 0) === 1,
    'personal_sites_mapped_to_users=1'
);
assert_true($personalMapped['report']['unmatched_backup_option_keys'] === [], 'personal site not unmatched');
$jamesScope = $personalMapped['scope_overrides']['user:' . $jamesGraph] ?? [];
assert_true(($jamesScope[BackupScope::MAIL] ?? false) === true, 'personal-site owner gets mail scope');

$personalUnmapped = CometSelectionMapper::map(
    [
        'organization' => false,
        'whole_org' => false,
        'backup_options' => [$personalSite => 24],
        'member_backup_options' => [],
    ],
    $jamesInventory,
    [],
);
assert_true(
    in_array($personalSite, $personalUnmapped['report']['unmatched_backup_option_keys'], true),
    'personal site unmatched without owner map'
);

exit($failures > 0 ? 1 : 0);
