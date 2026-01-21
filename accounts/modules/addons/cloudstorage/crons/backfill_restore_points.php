<?php

require __DIR__ . '/../../../../init.php';

use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

$limit = 500;
$envLimit = getenv('RESTORE_POINTS_BACKFILL_LIMIT');
if ($envLimit !== false && is_numeric($envLimit)) {
    $limit = max(1, (int) $envLimit);
}
if (!empty($argv[1]) && is_numeric($argv[1])) {
    $limit = max(1, (int) $argv[1]);
}

$result = CloudBackupController::backfillRestorePoints($limit);

echo "[restore_points_backfill] limit={$limit} status=" . ($result['status'] ?? 'unknown') . "\n";
if (!empty($result)) {
    echo "[restore_points_backfill] result=" . json_encode($result) . "\n";
}

logModuleCall(
    'cloudstorage',
    'restore_points_backfill',
    ['limit' => $limit],
    $result
);
