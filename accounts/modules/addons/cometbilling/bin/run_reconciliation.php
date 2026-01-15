<?php
/**
 * Run reconciliation: Compare Comet Server usage vs Portal billing.
 * 
 * Usage:
 *   php run_reconciliation.php
 *   php run_reconciliation.php --save        # Save report to database
 *   php run_reconciliation.php --verbose     # Show detailed output
 *   php run_reconciliation.php --json        # Output as JSON
 * 
 * Recommended cron (weekly on Monday at 3 AM):
 *   0 3 * * 1 php /path/to/modules/addons/cometbilling/bin/run_reconciliation.php --save
 */

$root = dirname(__DIR__, 4); // up to WHMCS root (accounts/)
if (!defined('WHMCS')) {
    require_once $root . '/init.php';
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Also require the comet server module for the Comet SDK
$cometAutoload = $root . '/modules/servers/comet/vendor/autoload.php';
if (file_exists($cometAutoload)) {
    require_once $cometAutoload;
}

use CometBilling\Reconciler;

// Parse CLI arguments
$verbose = false;
$save = false;
$jsonOutput = false;

foreach ($argv as $arg) {
    if ($arg === '--verbose' || $arg === '-v') {
        $verbose = true;
    }
    if ($arg === '--save' || $arg === '-s') {
        $save = true;
    }
    if ($arg === '--json' || $arg === '-j') {
        $jsonOutput = true;
    }
}

function logMsg(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo "[reconciliation] " . date('Y-m-d H:i:s') . " - {$msg}\n";
    }
}

if (!$jsonOutput) {
    logMsg("Starting reconciliation...");
}

try {
    // Run comparison
    $report = Reconciler::compare();

    // JSON output mode
    if ($jsonOutput) {
        echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
        exit($report['overall_status'] === 'ok' ? 0 : 1);
    }

    // Text output
    logMsg("Reconciliation complete.");
    logMsg("");
    logMsg("=== RECONCILIATION REPORT ===");
    logMsg("Server collected: " . ($report['server_collected_at'] ?? 'N/A'));
    logMsg("Portal snapshot:  " . ($report['portal_snapshot_at'] ?? 'N/A'));
    logMsg("Overall status:   " . strtoupper($report['overall_status']));
    logMsg("");

    // Show item comparison
    logMsg("ITEM COMPARISON:");
    logMsg(str_pad("Item", 20) . str_pad("Server", 10) . str_pad("Portal", 10) . str_pad("Variance", 10) . "Status");
    logMsg(str_repeat("-", 60));

    foreach ($report['items'] as $key => $item) {
        $variance = $item['variance'] >= 0 ? "+{$item['variance']}" : "{$item['variance']}";
        $statusIcon = match($item['status']) {
            'ok' => '✓',
            'over_billed' => '⚠️ OVER',
            'under_billed' => '⚠️ UNDER',
            default => '?',
        };
        
        logMsg(
            str_pad($item['label'], 20) .
            str_pad($item['server'], 10) .
            str_pad($item['portal'], 10) .
            str_pad($variance, 10) .
            $statusIcon
        );
    }

    logMsg("");

    // Show summary
    $summary = $report['summary'];
    logMsg("SUMMARY:");
    logMsg("  OK: {$summary['ok']}");
    logMsg("  Over-billed: {$summary['over_billed']}");
    logMsg("  Under-billed: {$summary['under_billed']}");

    if (!empty($summary['server_errors'])) {
        logMsg("");
        logMsg("SERVER ERRORS:");
        foreach ($summary['server_errors'] as $srv => $err) {
            logMsg("  {$srv}: {$err}");
        }
    }

    // Verbose output
    if ($verbose) {
        logMsg("");
        logMsg("SERVER DETAILS:");
        logMsg("  Total users: " . ($report['server_raw']['total_users'] ?? 0));
        logMsg("  Total devices: " . ($report['server_raw']['total_devices'] ?? 0));
        logMsg("  Total protected items: " . ($report['server_raw']['total_protected_items'] ?? 0));
        logMsg("  Storage: " . ($report['server_raw']['storage_human'] ?? 'N/A'));

        logMsg("");
        logMsg("PORTAL DETAILS:");
        logMsg("  Snapshot rows: " . ($report['portal_raw']['raw_rows'] ?? 0));
        logMsg("  Total billable amount: $" . number_format($report['portal_raw']['total_amount'] ?? 0, 2));
        logMsg("  Account fees: $" . number_format($report['portal_raw']['account_fees'] ?? 0, 2));
        logMsg("  Server licenses: $" . number_format($report['portal_raw']['server_licenses'] ?? 0, 2));
    }

    // Save report if requested
    if ($save) {
        $reportId = Reconciler::saveReport($report);
        logMsg("");
        logMsg("Report saved with ID: {$reportId}");
    }

    logMsg("");
    logMsg("Done.");

    // Exit code based on status
    exit($report['overall_status'] === 'ok' ? 0 : 1);

} catch (\Exception $e) {
    logMsg("FATAL ERROR: " . $e->getMessage());
    if ($verbose) {
        logMsg("Stack trace: " . $e->getTraceAsString());
    }
    exit(2);
}
