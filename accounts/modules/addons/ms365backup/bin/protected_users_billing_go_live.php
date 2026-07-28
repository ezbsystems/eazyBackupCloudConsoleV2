<?php
declare(strict_types=1);

/**
 * One-shot dev/prod follow-up after Protected Users billing revision (1.52.34+).
 *
 * 1. WHMCS config option rename (Objects → Protected Users)
 * 2. Daily meter + applyToWhmcs
 * 3. Spot-check philmgbuild / sa_eazybackup / indigoblue counts
 *
 * Usage (from accounts/):
 *   php modules/addons/ms365backup/bin/protected_users_billing_go_live.php
 *   MS365_VERBOSE=1 php modules/addons/ms365backup/bin/protected_users_billing_go_live.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365BillingService;
use Ms365Backup\Ms365ProductBootstrap;
use WHMCS\Database\Capsule;

echo "=== Protected Users billing go-live ===\n\n";

echo "1. Ms365ProductBootstrap\n";
$ms365Report = Ms365ProductBootstrap::ensure('cli_go_live');
echo json_encode($ms365Report, JSON_PRETTY_PRINT) . "\n\n";

if (class_exists(\WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::class)) {
    echo "2. E3BackupUserProductBootstrap\n";
    $e3Report = \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::ensure('cli_go_live');
    echo json_encode($e3Report, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "2. E3BackupUserProductBootstrap — class not loaded (skip)\n\n";
}

echo "3. Config option names (protected_users metric)\n";
try {
    $rows = Capsule::table('tblproductconfigoptions')
        ->whereIn('optionname', ['Protected Users', 'Protected Objects'])
        ->get(['id', 'optionname', 'gid']);
    foreach ($rows as $row) {
        echo sprintf("  configid=%d gid=%d name=%s\n", (int) $row->id, (int) $row->gid, (string) $row->optionname);
    }
} catch (\Throwable $e) {
    echo '  query error: ' . $e->getMessage() . "\n";
}
echo "\n";

echo "4. ms365_billing.php meter + apply\n";
$startedAt = microtime(true);
$meter = Ms365BillingService::meterAll();
$rate = Ms365BillingService::rateAll();
$apply = ['services' => 0, 'updated' => 0, 'errors' => 0];
$pid = \Ms365Backup\Ms365BillingConfig::getPid();
if ($pid > 0) {
    $svcIds = Capsule::table('tblhosting')
        ->where('packageid', $pid)
        ->whereIn('domainstatus', ['Active', 'Suspended'])
        ->pluck('id');
    foreach ($svcIds as $sid) {
        $apply['services']++;
        try {
            $apply['updated'] += Ms365BillingService::applyToWhmcs((int) $sid);
        } catch (\Throwable $e) {
            $apply['errors']++;
            echo '  apply error service ' . (int) $sid . ': ' . $e->getMessage() . "\n";
        }
    }
}
$elapsed = round(microtime(true) - $startedAt, 3);
echo '  meter=' . json_encode($meter) . "\n";
echo '  rate=' . json_encode($rate) . "\n";
echo '  apply=' . json_encode($apply) . " elapsed={$elapsed}s\n\n";

echo "5. analyze_billing_model spot-check\n";
$spotChecks = [
    ['label' => 'philmgbuild', 'backup_user_id' => 19],
    ['label' => 'sa_eazybackup', 'backup_user_id' => 18],
    ['label' => 'indigoblue', 'backup_user_id' => 21],
];
$analyzeBin = __DIR__ . '/analyze_billing_model.php';
foreach ($spotChecks as $check) {
    echo "--- {$check['label']} (backup_user_id={$check['backup_user_id']}) ---\n";
    $cmd = sprintf(
        '%s %s --backup-user-id=%d 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($analyzeBin),
        (int) $check['backup_user_id'],
    );
    passthru($cmd);
    echo "\n";
}

echo "Done.\n";
