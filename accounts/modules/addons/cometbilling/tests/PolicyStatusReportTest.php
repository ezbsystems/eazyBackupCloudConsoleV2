<?php
namespace WHMCS\Database {
    class Capsule {
        public static function table(string $table): object {
            return new class {
                public function max(string $column): ?string {
                    return null;
                }
            };
        }
    }
}

namespace {
require_once __DIR__ . '/../lib/ChargeCategoryResolver.php';
require_once __DIR__ . '/../lib/PolicyStatusReport.php';
use CometBilling\PolicyStatusReport;

function assertEq($e, $a, string $m): void {
    if ($e != $a) { fwrite(STDERR, "FAIL $m: expected ".var_export($e,true)." got ".var_export($a,true)."\n"); exit(1); }
    echo "PASS: $m\n";
}

assertEq(3, PolicyStatusReport::severityRank(7001), 'warning rank');
assertEq(4, PolicyStatusReport::severityRank(7002), 'error rank');
assertEq(true, PolicyStatusReport::isWarning(7001), 'is warning');
assertEq(false, PolicyStatusReport::isWarning(7002), 'error not warning-only');
assertEq(true, PolicyStatusReport::isWarningOrError(7001), 'warning in warn/error');
assertEq(true, PolicyStatusReport::isWarningOrError(7002), 'error in warn/error');
assertEq(false, PolicyStatusReport::isWarningOrError(5000), 'success excluded');
assertEq(4, PolicyStatusReport::severityRank(7999), 'unknown positive status is error rank');
assertEq(true, PolicyStatusReport::isWarningOrError(7999), 'unknown positive status is warn/error');

$agg = PolicyStatusReport::aggregateAccountFromSources([
    ['status' => 5000, 'end_time' => 100, 'source_id' => 'a'],
    ['status' => 7001, 'end_time' => 90, 'source_id' => 'b'],
    ['status' => 7002, 'end_time' => 80, 'source_id' => 'c'],
]);
assertEq(7002, $agg['status'], 'worst status wins');
assertEq(1, $agg['warning_source_count'], 'one warning source');
assertEq(1, $agg['error_source_count'], 'one error source');

assertEq('acme', PolicyStatusReport::normalizeAccountKey(' Acme '), 'normalize account');

$activeServiceIndex = PolicyStatusReport::indexActiveServicesRows([
    (object) [
        'tenant_id' => 'Elevate_Law',
        'service_name' => 'Account Elevate_Law - Device a1ed48 - Booster (Microsoft 365) Protected Accounts 8',
        'amount' => '8.00',
        'extra' => json_encode(['Type' => 'booster']),
    ],
    (object) [
        'tenant_id' => 'Elevate_Law',
        'service_name' => 'Account Elevate_Law - Device a1ed48PlanAdvanced Plan ($2/device)',
        'amount' => '2.00',
        'extra' => json_encode(['Type' => 'device']),
    ],
    (object) ['tenant_id' => '', 'service_name' => 'Account Fallback - Proxmox VM', 'amount' => '3.00', 'extra' => json_encode(['Type' => 'booster'])],
]);
assertEq(['m365_accounts', 'devices'], $activeServiceIndex['elevate_law']['categories'], 'elevate categories');
assertEq(10.0, $activeServiceIndex['elevate_law']['amount'], 'elevate total amount');
assertEq(2.0, $activeServiceIndex['elevate_law']['device_amount'], 'elevate device amount');
assertEq(8.0, $activeServiceIndex['elevate_law']['booster_amount'], 'elevate booster amount');
assertEq(2, $activeServiceIndex['elevate_law']['line_count'], 'elevate line count');
assertEq('Fallback', $activeServiceIndex['fallback']['display_name'], 'service name account fallback');
assertEq(['proxmox_vms'], $activeServiceIndex['fallback']['categories'], 'fallback category');
assertEq(3.0, $activeServiceIndex['fallback']['booster_amount'], 'fallback booster amount');
assertEq(0.0, $activeServiceIndex['fallback']['device_amount'], 'fallback device amount');

$accounts = [
    ['server_key'=>'obc','policy_id'=>'9005920f-fa54-4a22-8844-533bda81da4c','username'=>'WarnOnly','status'=>7001,'last_job_time'=>1,'source_count'=>1,'warning_source_count'=>1,'error_source_count'=>0],
    ['server_key'=>'obc','policy_id'=>'9005920f-fa54-4a22-8844-533bda81da4c','username'=>'ErrBilled','status'=>7002,'last_job_time'=>2,'source_count'=>1,'warning_source_count'=>0,'error_source_count'=>1],
    ['server_key'=>'cometbackup','policy_id'=>'0e545d31-e0b3-4b38-8456-0999fa46f588','username'=>'Ok','status'=>5000,'last_job_time'=>3,'source_count'=>1,'warning_source_count'=>0,'error_source_count'=>0],
    ['server_key'=>'obc','policy_id'=>'9005920f-fa54-4a22-8844-533bda81da4c','username'=>'SuccessBilled','status'=>5000,'status_label'=>'success','last_job_time'=>4,'source_count'=>2,'warning_source_count'=>0,'error_source_count'=>0],
];
$billed = [
    'errbilled' => ['categories'=>['devices'],'amount'=>2.0,'device_amount'=>2.0,'booster_amount'=>0.0,'line_count'=>1],
    'successbilled' => ['categories'=>['devices','m365_accounts'],'amount'=>10.0,'device_amount'=>2.0,'booster_amount'=>8.0,'line_count'=>2],
];
$sections = PolicyStatusReport::buildSections($accounts, $billed);
assertEq(1, count($sections['warning_accounts']), 'section A one warning');
assertEq('WarnOnly', $sections['warning_accounts'][0]['username'], 'section A username');
assertEq(1, count($sections['billed_unhealthy']), 'section B one billed unhealthy');
assertEq('ErrBilled', $sections['billed_unhealthy'][0]['username'], 'section B username');
assertEq(2.0, $sections['billed_unhealthy'][0]['billed_device_amount'], 'section B device amount');
assertEq(0.0, $sections['billed_unhealthy'][0]['billed_booster_amount'], 'section B booster amount');
assertEq(1, count($sections['billed_successful']), 'section C one billed successful');
assertEq('SuccessBilled', $sections['billed_successful'][0]['username'], 'section C username');
assertEq(2.0, $sections['billed_successful'][0]['billed_device_amount'], 'section C device amount');
assertEq(8.0, $sections['billed_successful'][0]['billed_booster_amount'], 'section C booster amount');
assertEq(true, PolicyStatusReport::isSuccess(5000), 'isSuccess 5000');
assertEq(false, PolicyStatusReport::isSuccess(7001), 'isSuccess excludes warning');

$csvReport = [
    'warning_accounts' => [[
        'server_label' => 'csw.obcbackup.com',
        'server_key' => 'obc',
        'policy_id' => '9005920f-fa54-4a22-8844-533bda81da4c',
        'username' => 'WarnOnly',
        'warning_source_count' => 1,
        'source_count' => 1,
        'last_job_time' => 1720000000,
        'status' => 7001,
        'status_label' => 'warning',
    ]],
    'billed_unhealthy' => [[
        'server_label' => 'csw.obcbackup.com',
        'server_key' => 'obc',
        'policy_id' => '9005920f-fa54-4a22-8844-533bda81da4c',
        'username' => 'ErrBilled',
        'warning_source_count' => 0,
        'source_count' => 1,
        'last_job_time' => 1720000000,
        'status' => 7002,
        'status_label' => 'error',
        'billed_device_amount' => 2.0,
        'billed_booster_amount' => 8.5,
    ]],
    'billed_successful' => [],
];
$csvA = PolicyStatusReport::buildCsvForSection($csvReport, PolicyStatusReport::SECTION_WARNING);
assertEq(true, str_contains($csvA, 'Username'), 'csv A has username header');
assertEq(false, str_contains($csvA, 'Device charge'), 'csv A omits charge columns');
assertEq(true, str_contains($csvA, 'WarnOnly'), 'csv A has warn username');
$csvB = PolicyStatusReport::buildCsvForSection($csvReport, PolicyStatusReport::SECTION_BILLED_UNHEALTHY);
assertEq(true, str_contains($csvB, 'Device charge'), 'csv B has device charge header');
assertEq(true, str_contains($csvB, 'Booster charge'), 'csv B has booster charge header');
assertEq(true, str_contains($csvB, '8.50'), 'csv B has booster amount');
assertEq(true, PolicyStatusReport::isValidSection('billed_successful'), 'valid section successful');
assertEq(false, PolicyStatusReport::isValidSection('nope'), 'invalid section rejected');
assertEq(true, PolicyStatusReport::isValidGroup(PolicyStatusReport::GROUP_VIRTUAL_SERVER), 'virtual server group valid');
assertEq(
    'a0d772aa-bf57-47f1-86f3-ff078364bee4',
    PolicyStatusReport::POLICY_GROUPS[PolicyStatusReport::GROUP_VIRTUAL_SERVER]['policies']['cometbackup'],
    'virtual server eazybackup policy id'
);
assertEq(
    '77ad6576-912a-4ac7-8cd2-064c7f8907d2',
    PolicyStatusReport::POLICY_GROUPS[PolicyStatusReport::GROUP_VIRTUAL_SERVER]['policies']['obc'],
    'virtual server obc policy id'
);

$hist = PolicyStatusReport::historicalDeviceCharges([]);
assertEq(0.0, $hist['total_amount'], 'empty hist total without members');
assertEq([], $hist['summary'], 'empty hist summary without members');

// Exercise CSV builders with synthetic payload.
$histPayload = [
    'historical_device' => [
        'summary' => [[
            'server_label' => 'csw.obcbackup.com',
            'server_key' => 'obc',
            'policy_id' => '77ad6576-912a-4ac7-8cd2-064c7f8907d2',
            'username' => 'ClaimAcct',
            'first_charge' => '2023-01-01',
            'last_charge' => '2024-06-01',
            'charge_count' => 2,
            'total_amount' => 4.0,
        ]],
        'details' => [[
            'server_label' => 'csw.obcbackup.com',
            'server_key' => 'obc',
            'policy_id' => '77ad6576-912a-4ac7-8cd2-064c7f8907d2',
            'username' => 'ClaimAcct',
            'usage_date' => '2023-01-01',
            'device_id' => 'abc123',
            'item_type' => 'device',
            'item_desc' => 'Device - abc123',
            'amount' => 2.0,
        ]],
    ],
];
$csvHistSum = PolicyStatusReport::buildCsvForSection(
    $histPayload,
    PolicyStatusReport::SECTION_HISTORICAL_DEVICE_SUMMARY
);
assertEq(true, str_contains($csvHistSum, 'First device charge'), 'hist summary header');
assertEq(true, str_contains($csvHistSum, 'ClaimAcct'), 'hist summary username');
assertEq(true, str_contains($csvHistSum, '4.00'), 'hist summary total');
$csvHistDet = PolicyStatusReport::buildCsvForSection(
    $histPayload,
    PolicyStatusReport::SECTION_HISTORICAL_DEVICE_DETAIL
);
assertEq(true, str_contains($csvHistDet, 'Item description'), 'hist detail header');
assertEq(true, str_contains($csvHistDet, '2023-01-01'), 'hist detail date');

assertEq('booster', PolicyStatusReport::activeServiceChargeBucket(
    (object) ['extra' => '{"Type":"booster"}', 'service_name' => 'Account X - Device abc123 - Booster (Microsoft 365)'],
), 'type booster wins over device text');
assertEq('device', PolicyStatusReport::activeServiceChargeBucket(
    (object) ['extra' => '{"Type":"device"}', 'service_name' => 'Account X - Device abc123 Plan'],
), 'type device');

$activeServices = PolicyStatusReport::indexLatestActiveServicesByAccount();
assertEq(['pulled_at' => null, 'by_account' => []], $activeServices, 'empty active services index');

echo "All PolicyStatusReport tests passed.\n";
}
