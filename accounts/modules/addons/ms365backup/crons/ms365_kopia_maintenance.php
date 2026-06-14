<?php
declare(strict_types=1);

/**
 * Schedule Kopia maintenance_quick for MS365 customer repositories.
 * Schedule: weekly via systemd timer or WHMCS cron.
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365KopiaMaintenanceService;

try {
    $result = Ms365KopiaMaintenanceService::scheduleDueMaintenance();
    echo json_encode(['status' => 'ok', 'result' => $result], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
