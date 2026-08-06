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
];
$billed = [
    'errbilled' => ['categories'=>['devices'],'amount'=>2.0,'device_amount'=>2.0,'booster_amount'=>0.0,'line_count'=>1],
];
$sections = PolicyStatusReport::buildSections($accounts, $billed);
assertEq(1, count($sections['warning_accounts']), 'section A one warning');
assertEq('WarnOnly', $sections['warning_accounts'][0]['username'], 'section A username');
assertEq(1, count($sections['billed_unhealthy']), 'section B one billed unhealthy');
assertEq('ErrBilled', $sections['billed_unhealthy'][0]['username'], 'section B username');
assertEq(2.0, $sections['billed_unhealthy'][0]['billed_device_amount'], 'section B device amount');
assertEq(0.0, $sections['billed_unhealthy'][0]['billed_booster_amount'], 'section B booster amount');

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
