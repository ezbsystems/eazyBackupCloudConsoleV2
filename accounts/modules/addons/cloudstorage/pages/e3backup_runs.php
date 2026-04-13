<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

function normalizeHypervSignatureValue($value): string
{
    if ($value === null) {
        return '';
    }
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded);
        }
        return $trimmed;
    }
    if (is_array($value) || is_object($value)) {
        return json_encode($value);
    }
    return trim((string) $value);
}

function resolveCanonicalHypervJobId(string $jobId, int $clientId): ?string
{
    if (!UuidBinary::isUuid($jobId)) {
        return null;
    }

    $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    if (!$hasJobIdPk) {
        return null;
    }

    $columns = Capsule::schema()->getColumnListing('s3_cloudbackup_jobs');
    $hasHypervEnabled = in_array('hyperv_enabled', $columns, true);
    $hasHypervConfig = in_array('hyperv_config', $columns, true);
    $hasSourcePathsJson = in_array('source_paths_json', $columns, true);

    $select = [
        Capsule::raw('BIN_TO_UUID(job_id) as job_id'),
        'client_id',
        'status',
        'engine',
        'source_type',
        'agent_uuid',
        'source_path',
        'dest_bucket_id',
        'dest_prefix',
        'created_at',
    ];
    $select[] = $hasHypervEnabled ? 'hyperv_enabled' : Capsule::raw('0 as hyperv_enabled');
    $select[] = $hasHypervConfig ? 'hyperv_config' : Capsule::raw('NULL as hyperv_config');
    $select[] = $hasSourcePathsJson ? 'source_paths_json' : Capsule::raw('NULL as source_paths_json');

    $current = Capsule::table('s3_cloudbackup_jobs')
        ->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobId)))
        ->first($select);

    if (!$current || (int) ($current->client_id ?? 0) !== $clientId) {
        return null;
    }

    $currentEngine = strtolower(trim((string) ($current->engine ?? '')));
    $isHypervLike = $currentEngine === 'hyperv'
        || (int) ($current->hyperv_enabled ?? 0) === 1
        || trim((string) ($current->hyperv_config ?? '')) !== '';
    if (!$isHypervLike || $currentEngine === 'hyperv') {
        return null;
    }

    $sourcePathsSignature = normalizeHypervSignatureValue($current->source_paths_json ?? null);
    $hypervConfigSignature = normalizeHypervSignatureValue($current->hyperv_config ?? null);
    $sourcePath = trim((string) ($current->source_path ?? ''));
    $agentUuid = trim((string) ($current->agent_uuid ?? ''));
    $destBucketId = (string) ($current->dest_bucket_id ?? '');
    $destPrefix = trim((string) ($current->dest_prefix ?? ''));

    $candidateQuery = Capsule::table('s3_cloudbackup_jobs')
        ->where('client_id', $clientId)
        ->where('status', '!=', 'deleted')
        ->whereRaw('job_id != ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobId)))
        ->where('source_type', 'local_agent')
        ->where('engine', 'hyperv');

    if ($agentUuid !== '') {
        $candidateQuery->where('agent_uuid', $agentUuid);
    } else {
        $candidateQuery->whereNull('agent_uuid');
    }
    if ($sourcePath !== '') {
        $candidateQuery->where('source_path', $sourcePath);
    } else {
        $candidateQuery->where(function ($q) {
            $q->whereNull('source_path')->orWhere('source_path', '');
        });
    }
    if ($destBucketId !== '') {
        $candidateQuery->where('dest_bucket_id', $destBucketId);
    } else {
        $candidateQuery->whereNull('dest_bucket_id');
    }
    if ($destPrefix !== '') {
        $candidateQuery->where('dest_prefix', $destPrefix);
    } else {
        $candidateQuery->where(function ($q) {
            $q->whereNull('dest_prefix')->orWhere('dest_prefix', '');
        });
    }
    if ($hasSourcePathsJson) {
        if ($sourcePathsSignature !== '') {
            $candidateQuery->where('source_paths_json', $sourcePathsSignature);
        } else {
            $candidateQuery->where(function ($q) {
                $q->whereNull('source_paths_json')->orWhere('source_paths_json', '');
            });
        }
    }
    if ($hasHypervConfig) {
        if ($hypervConfigSignature !== '') {
            $candidateQuery->where('hyperv_config', $hypervConfigSignature);
        } else {
            $candidateQuery->where(function ($q) {
                $q->whereNull('hyperv_config')->orWhere('hyperv_config', '');
            });
        }
    }

    $candidate = $candidateQuery
        ->orderByDesc('created_at')
        ->first([Capsule::raw('BIN_TO_UUID(job_id) as job_id')]);

    return $candidate && !empty($candidate->job_id) ? (string) $candidate->job_id : null;
}

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
        ->select(Capsule::raw('BIN_TO_UUID(job_id) as job_id'), 'name', 'source_display_name', 'source_type', 'dest_bucket_id', 'dest_prefix', 'engine', 'status', 'schedule_type', 'created_at')
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

$canonicalJobId = resolveCanonicalHypervJobId((string) $jobId, $loggedInUserId);
if ($canonicalJobId !== null && $canonicalJobId !== (string) $jobId) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=runs&job_id=' . urlencode($canonicalJobId));
    exit;
}

// Verify job ownership and get runs
$job = CloudBackupController::getJob($jobId, $loggedInUserId);
if (!$job) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=jobs');
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
