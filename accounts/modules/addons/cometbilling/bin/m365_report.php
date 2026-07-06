<?php
/**
 * M365 Protected Accounts booster report (CLI).
 *
 * Usage:
 *   php m365_report.php
 *   php m365_report.php --from=2026-06-06 --to=2026-07-06
 *   php m365_report.php --preset=60
 */

$root = dirname(__DIR__, 4);
if (!defined('WHMCS')) {
    require_once $root . '/init.php';
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

spl_autoload_register(function ($class) {
    $prefix = 'CometBilling\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $file = __DIR__ . '/../lib/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use CometBilling\M365BoosterReport;

$from = null;
$to = null;
$preset = null;
$json = false;

foreach ($argv as $index => $arg) {
    if ($index === 0) {
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php m365_report.php [--from=YYYY-MM-DD] [--to=YYYY-MM-DD] [--preset=30|60|90] [--json]\n";
        exit(0);
    }
    if ($arg === '--json') {
        $json = true;
        continue;
    }
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
        continue;
    }
    if (str_starts_with($arg, '--preset=')) {
        $preset = (int) substr($arg, 9);
        continue;
    }
}

$range = M365BoosterReport::resolveDateRange($preset, $from, $to);
$report = M365BoosterReport::report($range['from'], $range['to']);

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

echo "M365 Protected Accounts Booster Report\n";
echo str_repeat('=', 50) . "\n";
echo "Period: {$report['from_date']} to {$report['to_date']} (UTC)\n";

if (!empty($report['message'])) {
    echo "\n{$report['message']}\n";
    exit(1);
}

echo "Snapshot: {$report['snapshot_at']} ({$report['snapshot_count']} snapshots in period)\n";
echo "Line items: {$report['line_count']}\n";
echo "Total protected accounts: {$report['total_accounts']}\n";
echo "Estimated monthly billing: \$" . number_format((float) $report['total_amount'], 2) . "\n\n";

if (empty($report['items'])) {
    echo "No line items.\n";
    exit(0);
}

printf("%-20s %-12s %10s %12s\n", 'Account', 'Device', 'Accounts', 'Amount');
printf("%-20s %-12s %10s %12s\n", str_repeat('-', 20), str_repeat('-', 12), str_repeat('-', 10), str_repeat('-', 12));

foreach ($report['items'] as $item) {
    printf(
        "%-20s %-12s %10d %12s\n",
        substr((string) ($item['account'] ?? '—'), 0, 20),
        substr((string) ($item['device_id'] ?? '—'), 0, 12),
        (int) $item['protected_accounts'],
        '$' . number_format((float) $item['amount'], 2)
    );
}

printf("%-20s %-12s %10d %12s\n", 'TOTAL', '', (int) $report['total_accounts'], '$' . number_format((float) $report['total_amount'], 2));
