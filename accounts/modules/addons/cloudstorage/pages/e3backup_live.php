<?php

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;
use WHMCS\Database\Capsule;

$packageId = ProductConfig::e3CloudBackupPid();
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    header('Location: index.php?m=cloudstorage&page=welcome');
    exit;
}

$runIdentifier = $_GET['run_uuid'] ?? ($_GET['run_id'] ?? null);
if (!$runIdentifier) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=jobs');
    exit;
}

// Verify run ownership
$run = CloudBackupController::getRun($runIdentifier, $loggedInUserId);
if (!$run) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=jobs');
    exit;
}

// Get job details
$job = CloudBackupController::getJob($run['job_id'], $loggedInUserId) ?? [];

$isMs365Batch = Ms365BatchLiveService::isMs365BatchRun($run);
$sourceLabel = $isMs365Batch ? 'Microsoft 365' : null;
if ($isMs365Batch) {
    $run = Ms365BatchLiveService::enrichRunForDisplay($run, (int) $loggedInUserId);
}

// Resolve agent display name (if available)
$agentName = null;
$agentUuid = $job['agent_uuid'] ?? ($run['agent_uuid'] ?? null);
if (!empty($agentUuid)) {
    try {
        $agentRow = Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $agentUuid)
            ->first(['hostname']);
        if ($agentRow && !empty($agentRow->hostname)) {
            $agentName = $agentRow->hostname;
        }
    } catch (\Throwable $e) {
        // Best-effort only; leave agentName null
    }
}

// Detect if this is a restore run
$isRestore = false;
$isHypervRestore = false;
$restoreMetadata = null;

// Check run_type column if it exists
if (!empty($run['run_type'])) {
    if ($run['run_type'] === 'restore') {
        $isRestore = true;
    } elseif ($run['run_type'] === 'hyperv_restore') {
        $isRestore = true;
        $isHypervRestore = true;
    } elseif ($run['run_type'] === 'disk_restore') {
        $isRestore = true;
    }
}

// Also check stats_json for restore metadata
if (!empty($run['stats_json'])) {
    $statsJson = is_string($run['stats_json']) ? json_decode($run['stats_json'], true) : $run['stats_json'];
    if (json_last_error() === JSON_ERROR_NONE) {
        if (!empty($statsJson['type']) && $statsJson['type'] === 'restore') {
            $isRestore = true;
            $restoreMetadata = $statsJson;
        } elseif (!empty($statsJson['type']) && $statsJson['type'] === 'hyperv_restore') {
            $isRestore = true;
            $isHypervRestore = true;
            $restoreMetadata = $statsJson;
        } elseif (!empty($statsJson['type']) && $statsJson['type'] === 'ms365_restore') {
            $isRestore = true;
            $restoreMetadata = $statsJson;
        }
    }
}

// Resolve a customer-safe destination label. Never expose raw Ceph bucket ids.
//  - Restores: where the data is written back to (e.g. the Microsoft 365 account).
//  - Backups: the destination bucket *name* (or local path), never the numeric id.
$destinationHeading = 'Destination';
$destinationLabel = '';
if ($isRestore) {
    $destinationHeading = 'Restore to';
    if ($isMs365Batch) {
        try {
            $destinationLabel = \Ms365Backup\Ms365BatchRunRepository::restoreTargetSummary((string) ($run['run_id'] ?? ''));
        } catch (\Throwable $e) {
            $destinationLabel = '';
        }
        if ($destinationLabel === '') {
            $destinationLabel = 'Microsoft 365 account';
        }
    } elseif (is_array($restoreMetadata) && !empty($restoreMetadata['target_path'])) {
        $destinationLabel = (string) $restoreMetadata['target_path'];
    }
} else {
    if (!empty($job['dest_local_path'])) {
        $destinationLabel = (string) $job['dest_local_path'];
    } elseif (!empty($job['dest_bucket_id'])) {
        try {
            $bucketName = Capsule::table('s3_buckets')
                ->where('id', (int) $job['dest_bucket_id'])
                ->value('name');
            if (!empty($bucketName)) {
                $destinationLabel = (string) $bucketName;
            }
        } catch (\Throwable $e) {
            $destinationLabel = '';
        }
    }
    if ($destinationLabel !== '' && !empty($job['dest_prefix'])) {
        $destinationLabel .= ' / ' . (string) $job['dest_prefix'];
    }
}

$serverTimezone = date_default_timezone_get() ?: 'UTC';
$startedAtEpochMs = null;
$finishedAtEpochMs = null;
if (!empty($run['started_at'])) {
    try {
        $dt = new \DateTime((string) $run['started_at'], new \DateTimeZone($serverTimezone));
        $startedAtEpochMs = (int) ($dt->getTimestamp() * 1000);
    } catch (\Throwable $e) {
        $startedAtEpochMs = null;
    }
}
if (!empty($run['finished_at'])) {
    try {
        $dt = new \DateTime((string) $run['finished_at'], new \DateTimeZone($serverTimezone));
        $finishedAtEpochMs = (int) ($dt->getTimestamp() * 1000);
    } catch (\Throwable $e) {
        $finishedAtEpochMs = null;
    }
}

return [
    'run' => $run,
    'job' => $job,
    'agent_name' => $agentName,
    'agent_uuid' => $agentUuid,
    'is_ms365_batch' => $isMs365Batch,
    'source_label' => $sourceLabel,
    'is_restore' => $isRestore,
    'is_hyperv_restore' => $isHypervRestore,
    'restore_metadata' => $restoreMetadata,
    'destination_heading' => $destinationHeading,
    'destination_label' => $destinationLabel,
    'server_timezone' => $serverTimezone,
    'started_at_epoch_ms' => $startedAtEpochMs,
    'finished_at_epoch_ms' => $finishedAtEpochMs,
];

