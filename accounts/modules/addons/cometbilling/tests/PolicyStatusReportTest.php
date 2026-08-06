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

$agg = PolicyStatusReport::aggregateAccountFromSources([
    ['status' => 5000, 'end_time' => 100, 'source_id' => 'a'],
    ['status' => 7001, 'end_time' => 90, 'source_id' => 'b'],
    ['status' => 7002, 'end_time' => 80, 'source_id' => 'c'],
]);
assertEq(7002, $agg['status'], 'worst status wins');
assertEq(1, $agg['warning_source_count'], 'one warning source');
assertEq(1, $agg['error_source_count'], 'one error source');

assertEq('acme', PolicyStatusReport::normalizeAccountKey(' Acme '), 'normalize account');

$accounts = [
    ['server_key'=>'obc','policy_id'=>'9005920f-fa54-4a22-8844-533bda81da4c','username'=>'WarnOnly','status'=>7001,'last_job_time'=>1,'source_count'=>1,'warning_source_count'=>1,'error_source_count'=>0],
    ['server_key'=>'obc','policy_id'=>'9005920f-fa54-4a22-8844-533bda81da4c','username'=>'ErrBilled','status'=>7002,'last_job_time'=>2,'source_count'=>1,'warning_source_count'=>0,'error_source_count'=>1],
    ['server_key'=>'cometbackup','policy_id'=>'0e545d31-e0b3-4b38-8456-0999fa46f588','username'=>'Ok','status'=>5000,'last_job_time'=>3,'source_count'=>1,'warning_source_count'=>0,'error_source_count'=>0],
];
$billed = [
    'errbilled' => ['categories'=>['devices'],'amount'=>2.0,'line_count'=>1],
];
$sections = PolicyStatusReport::buildSections($accounts, $billed);
assertEq(1, count($sections['warning_accounts']), 'section A one warning');
assertEq('WarnOnly', $sections['warning_accounts'][0]['username'], 'section A username');
assertEq(1, count($sections['billed_unhealthy']), 'section B one billed unhealthy');
assertEq('ErrBilled', $sections['billed_unhealthy'][0]['username'], 'section B username');

$activeServices = PolicyStatusReport::indexLatestActiveServicesByAccount();
assertEq(['pulled_at' => null, 'by_account' => []], $activeServices, 'empty active services index');

echo "All PolicyStatusReport tests passed.\n";
}
