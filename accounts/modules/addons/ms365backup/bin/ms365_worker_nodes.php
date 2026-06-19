<?php
declare(strict_types=1);

/**
 * List registered MS365 Kopia worker nodes (for ops).
 *
 * Usage: php modules/addons/ms365backup/bin/ms365_worker_nodes.php
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use WHMCS\Database\Capsule;

if (!Capsule::schema()->hasTable('ms365_worker_nodes')) {
    fwrite(STDERR, "ms365_worker_nodes table not found. Run ms365backup module upgrade.\n");
    exit(1);
}

foreach (Capsule::table('ms365_worker_nodes')->orderBy('hostname')->get() as $row) {
    $r = (array) $row;
    echo ($r['hostname'] ?? '?')
        . ' v' . ($r['version'] ?? '?')
        . ' vmid=' . ($r['proxmox_vmid'] ?? '')
        . ' ' . ($r['status'] ?? '')
        . PHP_EOL;
}

exit(0);
