<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_comet_office365_selection_parser_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Comet\CometOffice365SelectionParser;

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

$userGuid = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
$siteKey = 'contoso.sharepoint.com,bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb,cccccccc-cccc-cccc-cccc-cccccccccccc';
$groupGuid = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

$custom = [
    'Organization' => false,
    'WholeOrg' => false,
    'BackupOptions' => [
        $userGuid => 31,
        $siteKey => 24,
    ],
    'MemberBackupOptions' => [
        $groupGuid => 31,
    ],
];

$profile = [
    'Username' => 'ITadmin',
    'LocalTimezone' => 'America/Edmonton',
    'Sources' => [
        'src-1' => [
            'Engine' => 'engine1/winmsofficemail',
            'Description' => 'M365',
            'EngineProps' => [
                'CUSTOM_SETTINGV2' => json_encode($custom, JSON_UNESCAPED_SLASHES),
            ],
        ],
    ],
];

$parsed = CometOffice365SelectionParser::parseProfile($profile);
assert_true($parsed['source_guid'] === 'src-1', 'source_guid');
assert_true($parsed['description'] === 'M365', 'description');
assert_true($parsed['organization'] === false && $parsed['whole_org'] === false, 'not whole org');
assert_true(($parsed['backup_options'][$userGuid] ?? 0) === 31, 'user mask 31');
assert_true(($parsed['backup_options'][$siteKey] ?? 0) === 24, 'site mask 24');
assert_true(($parsed['member_backup_options'][$groupGuid] ?? 0) === 31, 'member group mask');
assert_true($parsed['local_timezone'] === 'America/Edmonton', 'timezone');
assert_true($parsed['source_count'] === 1 && $parsed['merged'] === false, 'single source not merged');
assert_true($parsed['source_guids'] === ['src-1'], 'source_guids single');

$threw = false;
try {
    CometOffice365SelectionParser::parseProfile(['Sources' => ['x' => ['Engine' => 'engine1/file']]]);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
assert_true($threw, 'throws when no M365 source');

$asArray = $profile;
$asArray['Sources']['src-1']['EngineProps']['CUSTOM_SETTINGV2'] = $custom;
$parsed2 = CometOffice365SelectionParser::parseProfile($asArray);
assert_true(($parsed2['backup_options'][$userGuid] ?? 0) === 31, 'accepts CUSTOM_SETTINGV2 as array');

$multi = [
    'LocalTimezone' => 'America/Edmonton',
    'Sources' => [
        'src-a' => [
            'Engine' => 'engine1/winmsofficemail',
            'Description' => 'Users',
            'EngineProps' => [
                'CUSTOM_SETTINGV2' => [
                    'Organization' => false,
                    'WholeOrg' => false,
                    'BackupOptions' => [
                        $userGuid => 4, // mail only
                        $siteKey => 8,  // sharepoint
                    ],
                    'MemberBackupOptions' => [
                        $groupGuid => 16,
                    ],
                ],
            ],
        ],
        'src-b' => [
            'Engine' => 'engine1/winmsofficemail',
            'Description' => 'Sites',
            'EngineProps' => [
                'CUSTOM_SETTINGV2' => [
                    'Organization' => false,
                    'WholeOrg' => true,
                    'BackupOptions' => [
                        $userGuid => 16, // onedrive → OR with mail = 20
                        'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee' => 31,
                    ],
                    'MemberBackupOptions' => [
                        $groupGuid => 4, // OR with 16 = 20
                    ],
                ],
            ],
        ],
        'src-file' => [
            'Engine' => 'engine1/file',
            'Description' => 'ignored',
            'EngineProps' => [],
        ],
    ],
];

$firstOnly = CometOffice365SelectionParser::parseProfile($multi, false);
assert_true($firstOnly['source_guid'] === 'src-a', 'first-only keeps src-a');
assert_true($firstOnly['source_count'] === 1, 'first-only source_count=1');
assert_true(($firstOnly['backup_options'][$userGuid] ?? 0) === 4, 'first-only mail mask');
assert_true(!isset($firstOnly['backup_options']['eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee']), 'first-only ignores src-b keys');
assert_true($firstOnly['whole_org'] === false, 'first-only whole_org from src-a');

$merged = CometOffice365SelectionParser::parseProfile($multi, true);
assert_true($merged['merged'] === true && $merged['source_count'] === 2, 'merge two M365 sources');
assert_true($merged['source_guids'] === ['src-a', 'src-b'], 'merge source_guids order');
assert_true($merged['description'] === 'Users + Sites', 'merged description');
assert_true($merged['whole_org'] === true, 'merge ORs WholeOrg');
assert_true(($merged['backup_options'][$userGuid] ?? 0) === 20, 'merge ORs user masks 4|16');
assert_true(($merged['backup_options'][$siteKey] ?? 0) === 8, 'merge keeps site from src-a');
assert_true(($merged['backup_options']['eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'] ?? 0) === 31, 'merge adds src-b user');
assert_true(($merged['member_backup_options'][$groupGuid] ?? 0) === 20, 'merge ORs member masks 16|4');

assert_true(
    CometOffice365SelectionParser::mergeOptionMaps(['a' => 1], ['a' => 4, 'b' => 8]) === ['a' => 5, 'b' => 8],
    'mergeOptionMaps OR'
);

exit($failures > 0 ? 1 : 0);
