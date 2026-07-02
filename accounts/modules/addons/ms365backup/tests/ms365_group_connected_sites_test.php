<?php
declare(strict_types=1);

/**
 * SharePoint display metadata enrichment tests.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_group_connected_sites_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\RelationshipResolver;
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

$teamId = 'team:group-1';
$siteId = 'site:site-1';
$groupId = 'group:group-2';
$channelSiteId = 'site:channel-site-1';
$infraSiteId = 'site:infra-1';

$resources = [
    TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'site-1', 'All Company', null, [
        'id' => $siteId,
        'email' => 'https://tenant.sharepoint.com/sites/allcompany',
        'meta' => ['web_url' => 'https://tenant.sharepoint.com/sites/allcompany'],
    ]),
    TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'comm-root', 'Communication site', null, [
        'id' => 'site:comm-root',
        'email' => 'https://tenant.sharepoint.com',
        'meta' => ['web_url' => 'https://tenant.sharepoint.com'],
    ]),
    TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'team-site-1', 'Digital Initiative Public Relations', null, [
        'id' => 'site:team-site-1',
        'email' => 'https://tenant.sharepoint.com/sites/DigitalInitiativePublicRelations9',
        'meta' => ['web_url' => 'https://tenant.sharepoint.com/sites/DigitalInitiativePublicRelations9'],
    ]),
    TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'infra-1', 'Team Site', null, [
        'id' => $infraSiteId,
        'email' => 'https://tenant.sharepoint.com/sites/contentTypeHub',
        'meta' => ['web_url' => 'https://tenant.sharepoint.com/sites/contentTypeHub'],
    ]),
    TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'channel-site-1', 'General Files', null, [
        'id' => $channelSiteId,
    ]),
    TenantResource::build(TenantResource::TYPE_TEAM, 'group-1', 'All Company Team', null, [
        'id' => $teamId,
        'meta' => ['sharepoint_site_id' => 'site-1', 'group_id' => 'group-1'],
    ]),
    TenantResource::build(TenantResource::TYPE_M365_GROUP, 'group-2', 'Marketing Group', null, [
        'id' => $groupId,
        'meta' => ['sharepoint_site_id' => 'site-2'],
    ]),
];

$relationships = [
    [
        'from_id' => $teamId,
        'rel' => RelationshipResolver::REL_FILES_IN_SITE,
        'to_id' => 'site:team-site-1',
        'physical_key' => 'site:team-site-1',
    ],
    [
        'from_id' => $groupId,
        'rel' => RelationshipResolver::REL_FILES_IN_SITE,
        'to_id' => $siteId,
        'physical_key' => 'site:site-1',
    ],
    [
        'from_id' => 'channel:group-1:general',
        'rel' => RelationshipResolver::REL_FILES_IN_SITE,
        'to_id' => $channelSiteId,
        'physical_key' => 'site:channel-site-1',
    ],
];

$resolver = new RelationshipResolver();
$links = $resolver->filesInSiteLinks($relationships);
assert_true(isset($links[$siteId]) && in_array($groupId, $links[$siteId], true), 'filesInSiteLinks maps group to site');

$enriched = TenantResource::enrichSharePointDisplayMetadata($resources, $relationships);
$byId = [];
foreach ($enriched as $resource) {
    $byId[(string) $resource['id']] = $resource;
}

assert_true(($byId[$siteId]['workload_group_connected'] ?? false) === true, 'group-backed site flagged');
assert_true(($byId[$siteId]['show_in_sharepoint_section'] ?? true) === false, 'group-backed site hidden from SharePoint section');
assert_true(($byId['site:team-site-1']['team_connected'] ?? false) === true, 'team-backed site flagged');
assert_true(($byId['site:team-site-1']['show_in_sharepoint_section'] ?? false) === true, 'team-backed site remains in SharePoint section');
assert_true(($byId['site:comm-root']['show_in_sharepoint_section'] ?? false) === true, 'communication site remains visible');
assert_true(($byId[$infraSiteId]['infrastructure_site'] ?? false) === true, 'contentTypeHub flagged as infrastructure');
assert_true(($byId[$infraSiteId]['show_in_sharepoint_section'] ?? true) === false, 'contentTypeHub hidden from SharePoint section');
assert_true(($byId[$channelSiteId]['show_in_sharepoint_section'] ?? true) === false, 'channel site hidden from SharePoint section');

$displayCounts = TenantResource::displayCounts($enriched);
assert_true($displayCounts['sites'] === 2, 'display site count includes communication + team-backed site only');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
