<?php

// Usage: php -q accounts/crons/s3cloudbackup_events_prune.php

require_once __DIR__ . '/../../init.php';

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

try {
    // Load retention days from addon settings (default 60)
    $retention = 60;
    $row = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'cloudbackup_event_retention_days')
        ->first(['value']);
    if ($row && isset($row->value) && is_numeric($row->value)) {
        $retention = max(1, (int) $row->value);
    }

    // Compute cutoff (UTC)
    $cutoff = (new DateTime('now', new DateTimeZone('UTC')))
        ->modify('-' . $retention . ' days')
        ->format('Y-m-d H:i:s');

    // Delete in batches to avoid long locks
    $batch = 5000;
    do {
        $deleted = Capsule::table('s3_cloudbackup_run_events')
            ->where('ts', '<', $cutoff)
            ->limit($batch)
            ->delete();
    } while ($deleted === $batch); // continue while batches are full

    echo "[OK] Pruned old cloud backup events older than {$retention} days.\n";
} catch (\Exception $e) {
    echo "[ERROR] Prune failed: " . $e->getMessage() . "\n";
    exit(1);
}


