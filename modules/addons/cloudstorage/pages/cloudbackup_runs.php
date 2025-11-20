<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
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

$jobId = $_GET['job_id'] ?? null;
if (!$jobId) {
    // No job selected: show job selector instead of redirecting
    $jobs = Capsule::table('s3_cloudbackup_jobs')
        ->where('client_id', $loggedInUserId)
        ->where('status', '!=', 'deleted')
        ->orderBy('created_at', 'desc')
        ->get();

    // Attach destination bucket names for display
    try {
        $bucketIds = $jobs->pluck('dest_bucket_id')->filter()->unique()->values()->toArray();
        if (!empty($bucketIds)) {
            $bucketRows = Capsule::table('s3_buckets')
                ->whereIn('id', $bucketIds)
                ->get(['id', 'name']);
            $bucketNameById = [];
            foreach ($bucketRows as $b) {
                $bucketNameById[(int)$b->id] = $b->name;
                $bucketNameById[(string)$b->id] = $b->name;
            }
            foreach ($jobs as $j) {
                $bid = $j->dest_bucket_id ?? null;
                if ($bid !== null && isset($bucketNameById[$bid])) {
                    $j->dest_bucket_name = $bucketNameById[$bid];
                }
            }
        }
    } catch (\Exception $e) {
        // Non-fatal; names just won't be shown
    }

	// Compute lightweight aggregate metrics over jobs for the selector view
	$now = new DateTimeImmutable('now');
	$since = $now->sub(new DateInterval('P1D'));
	$totalJobs = $jobs->count();
	$active = 0;
	$paused = 0;
	$failed24h = 0;
	$lastRunAt = null;
	$lastRunStatus = null;
	foreach ($jobs as $j) {
		$status = $j->status ?? '';
		if ($status === 'active') $active++;
		elseif ($status === 'paused') $paused++;

		// If last_run is available on this row (depends on schema), attempt to parse
		$lr = $j->last_run ?? null;
		if ($lr && (is_array($lr) || is_object($lr))) {
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
	];

	return [
		'jobs' => $jobs,
		'metrics' => $metrics,
	];
}

// Verify job ownership and get runs
$job = CloudBackupController::getJob($jobId, $loggedInUserId);
if (!$job) {
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

// Attach destination bucket name to the selected job
try {
    if (isset($job['dest_bucket_id'])) {
        $bucketRow = Capsule::table('s3_buckets')
            ->where('id', $job['dest_bucket_id'])
            ->first(['name']);
        if ($bucketRow && isset($bucketRow->name)) {
            $job['dest_bucket_name'] = $bucketRow->name;
        }
    }
} catch (\Exception $e) {
    // ignore; UI will fallback to bucket ID
}

$runs = CloudBackupController::getRunsForJob($jobId, $loggedInUserId);

// Compute run metrics for selected job
$now = new DateTimeImmutable('now');
$since = $now->sub(new DateInterval('P1D'));
$totalRuns = is_array($runs) ? count($runs) : (is_object($runs) && method_exists($runs, 'count') ? $runs->count() : 0);
$success24 = 0;
$failed24 = 0;
$lastRunAt = null;
$lastRunStatus = null;
foreach ($runs as $r) {
	$status = is_array($r) ? ($r['status'] ?? '') : ($r->status ?? '');
	$startedRaw = is_array($r) ? ($r['started_at'] ?? null) : ($r->started_at ?? null);
	if ($startedRaw) {
		try {
			$dt = new DateTimeImmutable((string)$startedRaw);
		} catch (\Exception $e) {
			$dt = null;
		}
		if ($dt) {
			if ($dt >= $since) {
				if ($status === 'success') $success24++;
				if ($status === 'failed') $failed24++;
			}
			if ($lastRunAt === null || $dt > $lastRunAt) {
				$lastRunAt = $dt;
				$lastRunStatus = $status;
			}
		}
	}
}
$metrics = [
	'total_runs' => $totalRuns,
	'success_24h' => $success24,
	'failed_24h' => $failed24,
	'last_run_status' => $lastRunStatus,
	'last_run_started_at' => $lastRunAt ? $lastRunAt->format('Y-m-d H:i:s') : null,
];

return [
    'job' => $job,
    'runs' => $runs,
	'metrics' => $metrics,
];

