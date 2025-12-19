<?php

/**
 * Background cron to process bucket deletion queue (s3_delete_buckets).
 * 
 * Updated for deprovision flow:
 * - Uses admin credentials for bucket deletion (allows deletion even after user keys are revoked)
 * - Handles new status column (queued, running, blocked, failed, success)
 * - Implements protected bucket blocking
 * - Detects Object Lock retention and marks jobs as 'blocked' instead of infinite retries
 */

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

// Load DeprovisionHelper for protected resource checks
require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Admin/DeprovisionHelper.php';
use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;

// Max attempts before marking as failed (not blocked)
const MAX_ATTEMPTS = 5;

// Cron safety budgets (cron expected every ~5 minutes)
const JOB_TIME_BUDGET_SECONDS = 60;     // Per bucket job slice
const CRON_TIME_BUDGET_SECONDS = 240;   // Total cron runtime cap
const NO_PROGRESS_MAX_RUNS = 3;         // Fail if no progress across N runs

// Check if new status column exists
$hasStatusColumn = Capsule::schema()->hasColumn('s3_delete_buckets', 'status');

// Progress columns (optional; older installs may not have them yet)
$hasProgressCols = false;
$hasLastSeenNumObjects = false;
$hasLastSeenSizeActual = false;
$hasLastSeenAt = false;
$hasLastProgressAt = false;
$hasNoProgressRuns = false;
try {
    $hasProgressCols = Capsule::schema()->hasColumn('s3_delete_buckets', 'metrics');
    $hasLastSeenNumObjects = Capsule::schema()->hasColumn('s3_delete_buckets', 'last_seen_num_objects');
    $hasLastSeenSizeActual = Capsule::schema()->hasColumn('s3_delete_buckets', 'last_seen_size_actual');
    $hasLastSeenAt = Capsule::schema()->hasColumn('s3_delete_buckets', 'last_seen_at');
    $hasLastProgressAt = Capsule::schema()->hasColumn('s3_delete_buckets', 'last_progress_at');
    $hasNoProgressRuns = Capsule::schema()->hasColumn('s3_delete_buckets', 'no_progress_runs');
} catch (\Throwable $e) {
    // Leave all as false
}

$cronStart = microtime(true);

// Build query based on schema version
if ($hasStatusColumn) {
    $buckets = Capsule::table('s3_delete_buckets')
        ->where('status', 'queued')
        ->where('attempt_count', '<', MAX_ATTEMPTS)
        ->orderBy('created_at', 'asc')
        ->limit(10)  // Process in batches
        ->get();
} else {
    // Legacy query for old schema
    $buckets = DBController::getResult('s3_delete_buckets', [
        ['attempt_count', '<', MAX_ATTEMPTS]
    ]);
}

if (count($buckets) === 0) {
    logModuleCall('cloudstorage', 's3deletebucket_cron', [], 'No bucket delete jobs to process.');
    exit(0);
}

// Get module configuration
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    $response = [
        'message' => 'Cloudstorage module not configured.',
        'status' => 'fail',
    ];
    logModuleCall('cloudstorage', 's3deletebucket_cron', [], $response);
    exit(1);
}

$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

if (empty($s3Endpoint) || empty($cephAdminAccessKey) || empty($cephAdminSecretKey)) {
    logModuleCall('cloudstorage', 's3deletebucket_cron', [], 'Missing S3/Ceph admin configuration');
    exit(1);
}

// Initialize bucket controller with admin credentials
$bucketController = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);

foreach ($buckets as $bucket) {
    // Global runtime cap so we don't overlap the next cron cycle
    if ((microtime(true) - $cronStart) >= CRON_TIME_BUDGET_SECONDS) {
        logModuleCall('cloudstorage', 's3deletebucket_cron', ['processed' => count($buckets)], 'Cron time budget reached; stopping early.');
        break;
    }

    $bucketId = $bucket->id;
    $userId = $bucket->user_id;
    $bucketName = $bucket->bucket_name;

    // Claim the job (atomic: only claim if currently queued)
    if ($hasStatusColumn) {
        $claimUpdate = [
            'status' => 'running',
        ];
        if (empty($bucket->started_at)) {
            $claimUpdate['started_at'] = date('Y-m-d H:i:s');
        }
        $claimed = Capsule::table('s3_delete_buckets')
            ->where('id', $bucketId)
            ->where('status', 'queued')
            ->update($claimUpdate);
        if ((int) $claimed === 0) {
            continue;
        }
    }

    $bucketBaseForProtection = $bucketName;
    if (($pos = strrpos($bucketBaseForProtection, '/')) !== false) {
        $bucketBaseForProtection = substr($bucketBaseForProtection, $pos + 1);
    }

    // Check protected bucket (including tenant-qualified paths)
    if (DeprovisionHelper::isProtectedBucket($bucketBaseForProtection)) {
        $errorMsg = "Bucket '{$bucketName}' is protected and cannot be deleted.";
        logModuleCall('cloudstorage', 's3deletebucket_cron', ['bucket' => $bucketName], $errorMsg);

        if ($hasStatusColumn) {
            Capsule::table('s3_delete_buckets')
                ->where('id', $bucketId)
                ->update([
                    'status' => 'blocked',
                    'error' => $errorMsg,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            // Legacy: increment attempt count to eventually stop retries
            DBController::updateRecord('s3_delete_buckets', [
                'attempt_count' => MAX_ATTEMPTS
            ], [['id', '=', $bucketId]]);
        }
        continue;
    }

    // Get the user to determine tenant_id for bucket path
    $user = Capsule::table('s3_users')->where('id', $userId)->first();
    if (is_null($user)) {
        $errorMsg = "User ID {$userId} not found in database.";
        logModuleCall('cloudstorage', 's3deletebucket_cron', ['user_id' => $userId, 'bucket' => $bucketName], $errorMsg);

        if ($hasStatusColumn) {
            Capsule::table('s3_delete_buckets')
                ->where('id', $bucketId)
                ->update([
                    'status' => 'failed',
                    'error' => $errorMsg,
                    'attempt_count' => ($bucket->attempt_count ?? 0) + 1,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        }
        continue;
    }

    $tenantId = trim((string) ($user->tenant_id ?? ''));
    $cephUid = DeprovisionHelper::computeCephUid($user);
    $adminOpsParams = [
        'bucket' => $bucketName,
        'uid' => $cephUid,
        'stats' => true,
    ];

    // Check if bucket exists on server via AdminOps
    $bucketInfo = AdminOps::getBucketInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $adminOpsParams);
    $bucketGone = false;

    if ($bucketInfo['status'] != 'success' && isset($bucketInfo['error'])) {
        if (preg_match('/"Code":"(.*?)"/', $bucketInfo['error'], $matches)) {
            if ($matches[1] == 'NoSuchBucket') {
                $bucketGone = true;
            }
        }
    }

    if ($bucketGone) {
        // Bucket already gone from RGW, just clean up database
        logModuleCall('cloudstorage', 's3deletebucket_cron', ['bucket' => $bucketName], 'Bucket already deleted from RGW, cleaning DB.');

        if ($hasStatusColumn) {
            Capsule::table('s3_delete_buckets')
                ->where('id', $bucketId)
                ->update([
                    'status' => 'success',
                    'error' => null,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            DBController::deleteRecord('s3_delete_buckets', [['id', '=', $bucketId]]);
        }

        // Soft-delete s3_buckets record for audit (keep row)
        try {
            if (Capsule::schema()->hasColumn('s3_buckets', 'deleted_at')) {
                Capsule::table('s3_buckets')
                    ->where('name', $bucketName)
                    ->where('user_id', $userId)
                    ->update([
                        'is_active' => 0,
                        'deleted_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                Capsule::table('s3_buckets')
                    ->where('name', $bucketName)
                    ->where('user_id', $userId)
                    ->update(['is_active' => 0]);
            }
        } catch (\Throwable $e) {
            // Non-fatal: don't block cron completion
        }

        continue;
    }

    // Persist last-seen stats (optional)
    if ($hasStatusColumn && ($hasLastSeenAt || $hasLastSeenNumObjects || $hasLastSeenSizeActual) && isset($bucketInfo['status']) && $bucketInfo['status'] === 'success' && isset($bucketInfo['data'])) {
        $statsRow = null;
        $data = $bucketInfo['data'];
        if (is_array($data) && isset($data['usage'])) {
            $statsRow = $data;
        } elseif (is_array($data)) {
            foreach ($data as $row) {
                if (is_array($row) && ($row['bucket'] ?? null) === $bucketName) {
                    $statsRow = $row;
                    break;
                }
            }
        }
        if (is_array($statsRow)) {
            $usage = $statsRow['usage']['rgw.main'] ?? null;
            if (is_array($usage)) {
                $update = [];
                if ($hasLastSeenAt) {
                    $update['last_seen_at'] = date('Y-m-d H:i:s');
                }
                if ($hasLastSeenNumObjects && array_key_exists('num_objects', $usage)) {
                    $update['last_seen_num_objects'] = (int) $usage['num_objects'];
                }
                if ($hasLastSeenSizeActual && array_key_exists('size_actual', $usage)) {
                    $update['last_seen_size_actual'] = (int) $usage['size_actual'];
                }
                if (!empty($update)) {
                    Capsule::table('s3_delete_buckets')->where('id', $bucketId)->update($update);
                }
            }
        }
    }

    // Attempt to delete bucket using admin credentials, bounded by a time budget
    $deadlineTs = time() + JOB_TIME_BUDGET_SECONDS;
    $response = $bucketController->deleteBucketAsAdminIncremental((int) $userId, (string) $bucketName, [
        'deadline_ts' => $deadlineTs,
        'use_admin_creds' => true,
    ]);

    $respStatus = $response['status'] ?? 'fail';

    if ($respStatus === 'success') {
        logModuleCall('cloudstorage', 's3deletebucket_cron', ['bucket' => $bucketName], 'Bucket deleted successfully.');

        if ($hasStatusColumn) {
            $update = [
                'status' => 'success',
                'error' => null,
                'completed_at' => date('Y-m-d H:i:s'),
            ];
            if ($hasProgressCols) {
                $update['metrics'] = json_encode($response['metrics'] ?? []);
            }
            if ($hasLastProgressAt) {
                $update['last_progress_at'] = date('Y-m-d H:i:s');
            }
            if ($hasNoProgressRuns) {
                $update['no_progress_runs'] = 0;
            }
            Capsule::table('s3_delete_buckets')->where('id', $bucketId)->update($update);
        } else {
            DBController::deleteRecord('s3_delete_buckets', [['id', '=', $bucketId]]);
        }

        // Soft-delete bucket record for audit (keep row)
        try {
            if (Capsule::schema()->hasColumn('s3_buckets', 'deleted_at')) {
                Capsule::table('s3_buckets')
                    ->where('name', $bucketName)
                    ->where('user_id', $userId)
                    ->update([
                        'is_active' => 0,
                        'deleted_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                Capsule::table('s3_buckets')
                    ->where('name', $bucketName)
                    ->where('user_id', $userId)
                    ->update(['is_active' => 0]);
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }

        continue;
    }

    // In-progress slice: requeue without incrementing attempts
    if ($hasStatusColumn && $respStatus === 'in_progress') {
        $metrics = $response['metrics'] ?? [];
        $deletedTotal =
            (int) ($metrics['deleted_current_objects'] ?? 0) +
            (int) ($metrics['deleted_versions'] ?? 0) +
            (int) ($metrics['deleted_delete_markers'] ?? 0) +
            (int) ($metrics['aborted_multipart_uploads'] ?? 0);
        $madeProgress = $deletedTotal > 0;

        $noProgressRuns = (int) ($bucket->no_progress_runs ?? 0);
        if ($hasNoProgressRuns) {
            if ($madeProgress) {
                $noProgressRuns = 0;
            } else {
                $noProgressRuns += 1;
            }
        }

        // If we are repeatedly making no progress, fail with a meaningful message
        if ($hasNoProgressRuns && $noProgressRuns >= NO_PROGRESS_MAX_RUNS) {
            $errorMsg = 'No progress deleting this bucket across ' . NO_PROGRESS_MAX_RUNS . ' runs. This may indicate permissions issues or Object Lock retention.';
            $failUpdate = [
                'status' => 'failed',
                'error' => $errorMsg,
                'attempt_count' => ($bucket->attempt_count ?? 0) + 1,
                'completed_at' => date('Y-m-d H:i:s'),
            ];
            if ($hasProgressCols) {
                $failUpdate['metrics'] = json_encode($metrics);
            }
            if ($hasNoProgressRuns) {
                $failUpdate['no_progress_runs'] = $noProgressRuns;
            }
            Capsule::table('s3_delete_buckets')->where('id', $bucketId)->update($failUpdate);
            logModuleCall('cloudstorage', 's3deletebucket_cron', ['bucket' => $bucketName, 'user_id' => $userId], $errorMsg);
            continue;
        }

        $update = [
            'status' => 'queued',
            'error' => null,
            'completed_at' => null,
        ];
        if ($hasProgressCols) {
            $update['metrics'] = json_encode($metrics);
        }
        if ($hasNoProgressRuns) {
            $update['no_progress_runs'] = $noProgressRuns;
        }
        if ($hasLastProgressAt && $madeProgress) {
            $update['last_progress_at'] = date('Y-m-d H:i:s');
        }
        Capsule::table('s3_delete_buckets')->where('id', $bucketId)->update($update);
        continue;
    }

    // Legacy schema: in-progress slice should not count as a failure
    if (!$hasStatusColumn && $respStatus === 'in_progress') {
        logModuleCall('cloudstorage', 's3deletebucket_cron', ['bucket' => $bucketName, 'user_id' => $userId], 'Deletion in progress (legacy schema).');
        continue;
    }

    // Handle failure
    $errorMsg = $response['message'] ?? 'Unknown error';
    $isBlocked = isset($response['blocked']) && $response['blocked'] === true;
    $newAttemptCount = ($bucket->attempt_count ?? 0) + 1;

    logModuleCall('cloudstorage', 's3deletebucket_cron', [
        'bucket' => $bucketName,
        'user_id' => $userId,
        'attempt' => $newAttemptCount,
        'blocked' => $isBlocked,
        'response' => $response,
    ], $errorMsg);

    if ($hasStatusColumn) {
        $newStatus = 'queued';  // Will retry
        if ($isBlocked) {
            $newStatus = 'blocked';  // Object Lock retention - won't retry until manually reset
        } elseif ($newAttemptCount >= MAX_ATTEMPTS) {
            $newStatus = 'failed';  // Max attempts reached
        }

        $update = [
            'status' => $newStatus,
            'error' => $errorMsg,
            'attempt_count' => $newAttemptCount,
            'completed_at' => ($newStatus !== 'queued') ? date('Y-m-d H:i:s') : null,
        ];
        if ($hasProgressCols) {
            $update['metrics'] = json_encode($response['metrics'] ?? []);
        }
        Capsule::table('s3_delete_buckets')->where('id', $bucketId)->update($update);
    } else {
        // Legacy: just increment attempt count
        DBController::updateRecord('s3_delete_buckets', [
            'attempt_count' => $newAttemptCount
        ], [['id', '=', $bucketId]]);
    }
}

logModuleCall('cloudstorage', 's3deletebucket_cron', ['processed' => count($buckets)], 'Cron run completed.');
