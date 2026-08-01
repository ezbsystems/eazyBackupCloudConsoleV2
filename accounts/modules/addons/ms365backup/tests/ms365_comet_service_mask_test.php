<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_comet_service_mask_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Comet\CometServiceMask;

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

$d31 = CometServiceMask::decode(31);
assert_true(
    $d31['calendar'] && $d31['contacts'] && $d31['mail'] && $d31['sharepoint'] && $d31['onedrive'],
    '31 = all bits'
);

$d24 = CometServiceMask::decode(24);
assert_true(!$d24['mail'] && $d24['sharepoint'] && $d24['onedrive'], '24 = SP+OD');

$d28 = CometServiceMask::decode(28);
assert_true($d28['mail'] && $d28['sharepoint'] && $d28['onedrive'] && !$d28['calendar'], '28 = mail+SP+OD');

$d0 = CometServiceMask::decode(0);
assert_true(!$d0['mail'] && !$d0['onedrive'], '0 = none');

exit($failures > 0 ? 1 : 0);
