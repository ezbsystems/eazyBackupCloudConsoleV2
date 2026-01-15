<?php
/**
 * Collect current usage from all Comet servers.
 * Stores a daily snapshot in cb_server_usage.
 * 
 * Usage:
 *   php collect_usage.php
 *   php collect_usage.php --server=cometbackup
 *   php collect_usage.php --server=obc
 *   php collect_usage.php --verbose
 * 
 * Recommended cron (daily at 1 AM):
 *   0 1 * * * php /path/to/modules/addons/cometbilling/bin/collect_usage.php
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

use WHMCS\Database\Capsule;
use CometBilling\ServerUsageCollector;

// Parse CLI arguments
$verbose = false;
$serverKey = null;

foreach ($argv as $arg) {
    if ($arg === '--verbose' || $arg === '-v') {
        $verbose = true;
    }
    if (str_starts_with($arg, '--server=')) {
        $serverKey = substr($arg, 9);
    }
}

function logMsg(string $msg, bool $verbose): void {
    if ($verbose || php_sapi_name() === 'cli') {
        echo "[collect_usage] " . date('Y-m-d H:i:s') . " - {$msg}\n";
    }
}

logMsg("Starting usage collection...", $verbose);

try {
    // Collect from specified server or all servers
    if ($serverKey) {
        logMsg("Collecting from server: {$serverKey}", $verbose);
        $serverData = ServerUsageCollector::collectFromServer($serverKey);
        $allData = [
            'collected_at' => date('Y-m-d H:i:s'),
            'servers' => [$serverKey => $serverData],
            'errors' => [],
        ];
        // Merge for totals
        foreach (['users', 'devices', 'hyperv_vms', 'vmware_vms', 'proxmox_vms', 
                  'disk_image', 'mssql', 'm365_accounts', 'storage_bytes', 'protected_items'] as $k) {
            $allData[$k] = $serverData[$k] ?? 0;
        }
    } else {
        logMsg("Collecting from all servers...", $verbose);
        $allData = ServerUsageCollector::collectAll();
    }

    // Check for errors
    if (!empty($allData['errors'])) {
        foreach ($allData['errors'] as $srv => $err) {
            logMsg("ERROR on {$srv}: {$err}", true);
        }
    }

    // Log results
    if ($verbose) {
        logMsg("Collection complete:", $verbose);
        logMsg("  Total Users: " . ($allData['users'] ?? 0), $verbose);
        logMsg("  Total Devices: " . ($allData['devices'] ?? 0), $verbose);
        logMsg("  Hyper-V VMs: " . ($allData['hyperv_vms'] ?? 0), $verbose);
        logMsg("  VMware VMs: " . ($allData['vmware_vms'] ?? 0), $verbose);
        logMsg("  Proxmox VMs: " . ($allData['proxmox_vms'] ?? 0), $verbose);
        logMsg("  Disk Image: " . ($allData['disk_image'] ?? 0), $verbose);
        logMsg("  MSSQL: " . ($allData['mssql'] ?? 0), $verbose);
        logMsg("  M365 Accounts: " . ($allData['m365_accounts'] ?? 0), $verbose);
        logMsg("  Storage: " . ServerUsageCollector::formatBytes($allData['storage_bytes'] ?? 0), $verbose);
    }

    // Store per-server snapshots
    $today = date('Y-m-d');
    foreach ($allData['servers'] ?? [] as $srvKey => $srvData) {
        Capsule::table('cb_server_usage')->updateOrInsert(
            ['snapshot_date' => $today, 'server_key' => $srvKey],
            [
                'total_users' => $srvData['users'] ?? 0,
                'total_devices' => $srvData['devices'] ?? 0,
                'hyperv_vms' => $srvData['hyperv_vms'] ?? 0,
                'vmware_vms' => $srvData['vmware_vms'] ?? 0,
                'proxmox_vms' => $srvData['proxmox_vms'] ?? 0,
                'disk_image' => $srvData['disk_image'] ?? 0,
                'mssql' => $srvData['mssql'] ?? 0,
                'm365_accounts' => $srvData['m365_accounts'] ?? 0,
                'storage_bytes' => $srvData['storage_bytes'] ?? 0,
                'protected_items' => $srvData['protected_items'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
        logMsg("Saved snapshot for {$srvKey}", $verbose);
    }

    // Store combined snapshot
    Capsule::table('cb_server_usage_combined')->updateOrInsert(
        ['snapshot_date' => $today],
        [
            'total_servers' => count($allData['servers'] ?? []),
            'total_users' => $allData['users'] ?? 0,
            'total_devices' => $allData['devices'] ?? 0,
            'hyperv_vms' => $allData['hyperv_vms'] ?? 0,
            'vmware_vms' => $allData['vmware_vms'] ?? 0,
            'proxmox_vms' => $allData['proxmox_vms'] ?? 0,
            'disk_image' => $allData['disk_image'] ?? 0,
            'mssql' => $allData['mssql'] ?? 0,
            'm365_accounts' => $allData['m365_accounts'] ?? 0,
            'storage_bytes' => $allData['storage_bytes'] ?? 0,
            'protected_items' => $allData['protected_items'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]
    );
    logMsg("Saved combined snapshot for {$today}", $verbose);

    logMsg("Usage collection complete.", $verbose);
    exit(0);

} catch (\Exception $e) {
    logMsg("FATAL ERROR: " . $e->getMessage(), true);
    exit(1);
}
