<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_comet_personal_site_resolver_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Comet\CometPersonalSiteResolver;

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

assert_true(
    CometPersonalSiteResolver::isPersonalSiteKey(
        'ncsaca-my.sharepoint.com,c3608d35-dd2a-4b16-bd4a-ef027c2b96f0,e1a31aab-58ce-4686-962f-b45e3843b44d'
    ),
    'detects -my.sharepoint personal site key'
);
assert_true(
    !CometPersonalSiteResolver::isPersonalSiteKey(
        'ncsaca.sharepoint.com,ee743440-fa9a-41c0-b5c1-4da130a37302,a1628cad-3a4b-4f49-812a-31ae8535d98e'
    ),
    'rejects normal sharepoint site key'
);
assert_true(
    !CometPersonalSiteResolver::isPersonalSiteKey('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'),
    'rejects plain GUID'
);

$keys = CometPersonalSiteResolver::personalSiteKeysFromBackupOptions([
    'ncsaca-my.sharepoint.com,aaa,bbb' => 24,
    'ncsaca.sharepoint.com,ccc,ddd' => 24,
    'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => 31,
]);
assert_true($keys === ['ncsaca-my.sharepoint.com,aaa,bbb'], 'extracts only personal site keys');

assert_true(
    CometPersonalSiteResolver::emailToPersonalPath('James-Garcesa@ncsa.ca') === 'james-garcesa_ncsa_ca',
    'email encodes to personal path'
);
assert_true(
    CometPersonalSiteResolver::personalPathFromWebUrl(
        'https://ncsaca-my.sharepoint.com/personal/james-garcesa_ncsa_ca'
    ) === 'james-garcesa_ncsa_ca',
    'webUrl yields personal path'
);

$matched = CometPersonalSiteResolver::matchOwnerInInventory(
    [[
        'id' => 'user:fa2a9e71-b6ef-4f0e-ab3f-50c4d8d774b2',
        'resource_type' => 'mailbox',
        'graph_id' => 'fa2a9e71-b6ef-4f0e-ab3f-50c4d8d774b2',
        'display_name' => 'James Garcesa',
        'email' => 'James-Garcesa@ncsa.ca',
        'meta' => [],
    ]],
    'James Garcesa',
    'https://ncsaca-my.sharepoint.com/personal/james-garcesa_ncsa_ca',
);
assert_true($matched === 'fa2a9e71-b6ef-4f0e-ab3f-50c4d8d774b2', 'inventory match by personal path');

exit($failures > 0 ? 1 : 0);
