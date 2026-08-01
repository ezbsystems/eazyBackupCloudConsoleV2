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

exit($failures > 0 ? 1 : 0);
