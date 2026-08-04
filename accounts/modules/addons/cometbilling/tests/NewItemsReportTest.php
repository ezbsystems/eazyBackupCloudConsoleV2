<?php
/**
 * Run: php tests/NewItemsReportTest.php
 */
require_once __DIR__ . '/../lib/ChargeCategoryResolver.php';
require_once __DIR__ . '/../lib/NewItemsReport.php';

use CometBilling\NewItemsReport;

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

assertTrue(
    NewItemsReport::bucketForCategory('devices') === NewItemsReport::BUCKET_DEVICES,
    'devices bucket'
);
assertTrue(
    NewItemsReport::bucketForCategory('m365_accounts') === NewItemsReport::BUCKET_M365,
    'm365 bucket'
);
assertTrue(
    NewItemsReport::bucketForCategory('hyperv_vms') === NewItemsReport::BUCKET_BOOSTERS,
    'hyperv is booster'
);
assertTrue(
    NewItemsReport::bucketForCategory('disk_image') === NewItemsReport::BUCKET_BOOSTERS,
    'disk image is booster'
);
assertTrue(
    NewItemsReport::bucketForCategory('account_plan') === null,
    'account plan excluded'
);
assertTrue(
    NewItemsReport::bucketForCategory('other') === null,
    'other excluded'
);

$keyA = NewItemsReport::identityKey('devices', 'ABCDEF', 'tenant1', 'Device - foo');
$keyB = NewItemsReport::identityKey('devices', 'abcdef', 'tenant2', 'Device - bar');
assertTrue($keyA === $keyB, 'device identity ignores tenant/desc case when device_id present');

$keyC = NewItemsReport::identityKey('hyperv_vms', '', 'tenant1', 'Hyper-V Guest Count 2');
$keyD = NewItemsReport::identityKey('hyperv_vms', null, 'tenant1', 'Hyper-V Guest Count 2');
assertTrue($keyC === $keyD, 'empty device falls back to tenant+desc');

$keyE = NewItemsReport::identityKey('hyperv_vms', '', 'tenant1', 'Hyper-V Guest Count 3');
assertTrue($keyC !== $keyE, 'different desc without device_id is different identity');

assertTrue(
    NewItemsReport::protectedAccountCount(
        'Booster - Microsoft 365 — Accounts 9 Plan $1.00 per account (charged monthly)',
        null,
        9.0
    ) === 9,
    'parse Accounts 9 from description'
);
assertTrue(
    NewItemsReport::protectedAccountCount('Booster - Microsoft 365 — Accounts 44 Plan', null, 44.0) === 44,
    'parse Accounts 44'
);
assertTrue(
    NewItemsReport::protectedAccountCount('something', 12, 12.0) === 12,
    'prefer numeric quantity column'
);
assertTrue(
    NewItemsReport::protectedAccountCount('no accounts text', null, 7.0) === 7,
    'fallback to amount when $1/account'
);

echo "All NewItemsReport tests passed.\n";
