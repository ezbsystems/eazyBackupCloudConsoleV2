<?php

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

$username = $product->username;
$user = DBController::getUser($username);

// Get user tenants
$tenants = DBController::getResult('s3_users', [
    ['parent_id', '=', $user->id]
], [
    'id', 'username'
])->pluck('username', 'id')->toArray();

$tenants[$user->id] = $username;
$bucketUserIds = array_keys($tenants);
$buckets = DBController::getUserBuckets($bucketUserIds);

// Create a lookup array for bucket names by bucket ID (use both string and int keys for safety)
$bucketNamesById = [];
foreach ($buckets as $bucket) {
    $bucketNamesById[(int)$bucket->id] = $bucket->name;
    $bucketNamesById[(string)$bucket->id] = $bucket->name; // Also add as string for lookup
}

// Get all jobs for this client
$jobs = CloudBackupController::getJobsForClient($loggedInUserId);

// Add bucket name to each job
foreach ($jobs as &$job) {
    $bucketId = isset($job['dest_bucket_id']) ? $job['dest_bucket_id'] : null;
    if ($bucketId !== null) {
        // Try both int and string lookups
        $bucketName = $bucketNamesById[(int)$bucketId] ?? $bucketNamesById[(string)$bucketId] ?? null;
        $job['dest_bucket_name'] = $bucketName ?: 'Unknown Bucket (ID: ' . $bucketId . ')';
    } else {
        $job['dest_bucket_name'] = 'No Bucket';
    }
}

// Compute summary metrics for the jobs page
// - total jobs, active, paused
// - failed in last 24h (using last_run if available)
// - last run status/time across all jobs
// - unique sources protected (by source_display_name + source_type)
$now = new DateTimeImmutable('now');
$since = $now->sub(new DateInterval('P1D'));
$totalJobs = is_array($jobs) ? count($jobs) : 0;
$active = 0;
$paused = 0;
$failed24h = 0;
$lastRunAt = null;
$lastRunStatus = null;
$sourceKeys = [];

foreach ($jobs as $j) {
	$status = $j['status'] ?? ($j->status ?? '');
	if ($status === 'active') {
		$active++;
	} elseif ($status === 'paused') {
		$paused++;
	}

	// Track unique sources
	$sdn = $j['source_display_name'] ?? ($j->source_display_name ?? '');
	$stype = $j['source_type'] ?? ($j->source_type ?? '');
	if ($sdn !== '' || $stype !== '') {
		$sourceKeys[$sdn . '|' . $stype] = true;
	}

	// Consider last run
	$lr = $j['last_run'] ?? ($j->last_run ?? null);
	if ($lr) {
		$lrStatus = is_array($lr) ? ($lr['status'] ?? null) : ($lr->status ?? null);
		$lrStartedAtRaw = is_array($lr) ? ($lr['started_at'] ?? null) : ($lr->started_at ?? null);
		if ($lrStartedAtRaw) {
			try {
				$lrStartedAt = new DateTimeImmutable((string)$lrStartedAtRaw);
			} catch (\Exception $e) {
				$lrStartedAt = null;
			}
			if ($lrStartedAt) {
				if ($lrStatus === 'failed' && $lrStartedAt >= $since) {
					$failed24h++;
				}
				if ($lastRunAt === null || $lrStartedAt > $lastRunAt) {
					$lastRunAt = $lrStartedAt;
					$lastRunStatus = (string)$lrStatus;
				}
			}
		}
	}
}

$metrics = [
	'total_jobs' => $totalJobs,
	'active' => $active,
	'paused' => $paused,
	'failed_24h' => $failed24h,
	'last_run_status' => $lastRunStatus,
	'last_run_started_at' => $lastRunAt ? $lastRunAt->format('Y-m-d H:i:s') : null,
	'unique_sources' => count($sourceKeys),
];

return [
    'jobs' => $jobs,
    'buckets' => $buckets,
    's3_user_id' => $user->id,
    'client_id' => $loggedInUserId,
	'metrics' => $metrics,
	// expose tenant usernames for inline bucket creation combobox
	'usernames' => $tenants,
];

