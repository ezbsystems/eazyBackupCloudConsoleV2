<?php
/**
 * Run: php tests/PeriodCompareReportTest.php
 */
require_once __DIR__ . '/../lib/ChargeCategoryResolver.php';
require_once __DIR__ . '/../lib/NewItemsReport.php';
require_once __DIR__ . '/../lib/PeriodCompareReport.php';

use CometBilling\PeriodCompareReport;

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

function assertEq($expected, $actual, string $msg): void
{
    if ($expected != $actual) {
        fwrite(STDERR, "FAIL {$msg}: expected " . var_export($expected, true) . ' got ' . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

$defaults = PeriodCompareReport::defaultRanges();
assertTrue(
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $defaults['a']['from']) === 1,
    'default period A from is Y-m-d'
);
assertTrue(
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $defaults['b']['to']) === 1,
    'default period B to is Y-m-d'
);
assertTrue(
    $defaults['a']['to'] < $defaults['b']['from'],
    'default periods are consecutive months'
);

$resolved = PeriodCompareReport::resolveRanges(null, null, null, null, 'prior_two_months');
assertEq($defaults['a'], $resolved['period_a'], 'prior_two_months preset period A');
assertEq($defaults['b'], $resolved['period_b'], 'prior_two_months preset period B');

$resolvedCustom = PeriodCompareReport::resolveRanges(
    '2025-06-01',
    '2025-06-30',
    '2025-07-01',
    '2025-07-31',
    null
);
assertEq('2025-06-01', $resolvedCustom['period_a']['from'], 'custom period A from');
assertEq('2025-07-31', $resolvedCustom['period_b']['to'], 'custom period B to');

assertEq(50.0, PeriodCompareReport::percentChange(100.0, 150.0), 'percent change +50%');
assertEq(-25.0, PeriodCompareReport::percentChange(100.0, 75.0), 'percent change -25%');
assertTrue(PeriodCompareReport::percentChange(0.0, 10.0) === null, 'percent change from zero is null');

$categoriesA = [
    'devices' => 100.0,
    'hyperv_vms' => 20.0,
    'vmware_vms' => 0.0,
    'proxmox_vms' => 0.0,
    'disk_image' => 0.0,
    'mssql' => 0.0,
    'm365_accounts' => 10.0,
    'account_plan' => 0.0,
    'other' => 0.0,
];
$categoriesB = [
    'devices' => 120.0,
    'hyperv_vms' => 30.0,
    'vmware_vms' => 0.0,
    'proxmox_vms' => 0.0,
    'disk_image' => 0.0,
    'mssql' => 0.0,
    'm365_accounts' => 15.0,
    'account_plan' => 0.0,
    'other' => 0.0,
];
$rows = PeriodCompareReport::buildCategoryRows($categoriesA, $categoriesB);
$deviceRow = null;
foreach ($rows as $row) {
    if ($row['category'] === 'devices') {
        $deviceRow = $row;
        break;
    }
}
assertTrue($deviceRow !== null, 'devices row present');
assertEq(100.0, $deviceRow['amount_a'], 'devices amount A');
assertEq(120.0, $deviceRow['amount_b'], 'devices amount B');
assertEq(20.0, $deviceRow['delta'], 'devices delta');

$identitiesA = [
    'devices|d:abc' => [
        'category' => 'devices',
        'tenant_id' => 'tenant1',
        'device_id' => 'abc',
        'item_desc' => 'Device - abc',
        'amount_sum' => 2.0,
        'charge_count' => 1,
        'qty_max' => 1,
    ],
    'hyperv_vms|d:def' => [
        'category' => 'hyperv_vms',
        'tenant_id' => 'tenant2',
        'device_id' => 'def',
        'item_desc' => 'Hyper-V Guest Count 2',
        'amount_sum' => 6.0,
        'charge_count' => 30,
        'qty_max' => 2,
    ],
];
$identitiesB = [
    'devices|d:abc' => [
        'category' => 'devices',
        'tenant_id' => 'tenant1',
        'device_id' => 'abc',
        'item_desc' => 'Device - abc',
        'amount_sum' => 4.0,
        'charge_count' => 2,
        'qty_max' => 1,
    ],
    'devices|d:xyz' => [
        'category' => 'devices',
        'tenant_id' => 'tenant3',
        'device_id' => 'xyz',
        'item_desc' => 'Device - xyz',
        'amount_sum' => 2.0,
        'charge_count' => 1,
        'qty_max' => 1,
    ],
];

$drivers = PeriodCompareReport::mergeIdentityDrivers($identitiesA, $identitiesB);
assertEq(3, count($drivers), 'three driver rows');

$continuing = null;
$newInB = null;
$goneInA = null;
foreach ($drivers as $driver) {
    if ($driver['driver_class'] === PeriodCompareReport::DRIVER_CONTINUING) {
        $continuing = $driver;
    }
    if ($driver['driver_class'] === PeriodCompareReport::DRIVER_NEW_IN_B) {
        $newInB = $driver;
    }
    if ($driver['driver_class'] === PeriodCompareReport::DRIVER_GONE_IN_A) {
        $goneInA = $driver;
    }
}
assertTrue($continuing !== null, 'continuing driver exists');
assertEq(2.0, $continuing['delta'], 'continuing device delta +2 from double renewal');
assertEq(2, $continuing['charge_count_b'], 'continuing device has 2 charges in B');
assertTrue($newInB !== null, 'new in B driver exists');
assertEq('devices|d:xyz', $newInB['identity_key'], 'new device identity');
assertTrue($goneInA !== null, 'gone in A driver exists');
assertEq('hyperv_vms|d:def', $goneInA['identity_key'], 'gone hyperv identity');
assertEq(-6.0, $goneInA['delta'], 'gone hyperv delta');

$filtered = array_values(array_filter(
    $drivers,
    static fn (array $d): bool => ($d['bucket'] ?? null) === 'devices'
));
assertEq(2, count($filtered), 'bucket filter keeps two device drivers');

$manyDrivers = [];
for ($i = 0; $i < 600; $i++) {
    $manyDrivers[] = [
        'identity_key' => 'devices|d:' . $i,
        'delta' => $i < 50 ? 5.0 : 0.001,
        'driver_class' => PeriodCompareReport::DRIVER_CONTINUING,
    ];
}
$capped = PeriodCompareReport::capDrivers($manyDrivers);
assertEq(PeriodCompareReport::DETAIL_ROW_CAP, count($capped['items']), 'cap at 500 rows');
assertTrue($capped['capped'], 'capped flag set');
assertEq(600, $capped['total'], 'total identities preserved');

echo "All PeriodCompareReport tests passed.\n";
