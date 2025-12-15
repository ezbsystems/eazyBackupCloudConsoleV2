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

// Check if new status column exists
$hasStatusColumn = Capsule::schema()->hasColumn('s3_delete_buckets', 'status');

// Build query based on schema version
if ($hasStatusColumn) {
    $buckets = Capsule::table('s3_delete_buckets')
        ->whereIn('status', ['queued', 'running'])
        ->where('attempt_count', '<', MAX_ATTEMPTS)
        ->orderBy('created_at', 'asc')
        ->limit(20)  // Process in batches
        ->get();
} else {
    // Legacy query for old schema
    $buckets = DBController::getResult('s3_delete_buckets', [
        ['attempt_count', '<', MAX_ATTEMPTS]
    ]);
}

if (count($buckets) === 0) {
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
    $bucketId = $bucket->id;
    $userId = $bucket->user_id;
    $bucketName = $bucket->bucket_name;

    // Mark as running
    if ($hasStatusColumn) {
        Capsule::table('s3_delete_buckets')
            ->where('id', $bucketId)
            ->update([
                'status' => 'running',
                'started_at' => date('Y-m-d H:i:s'),
            ]);
    }

    // Check protected bucket
    if (DeprovisionHelper::isProtectedBucket($bucketName)) {
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

    // Build bucket path for AdminOps check
    $bucketPath = $bucketName;
    if (!empty($user->tenant_id)) {
        $bucketPath = $user->tenant_id . '/' . $bucketName;
    }

    // Check if bucket exists on server via AdminOps
    $bucketInfo = AdminOps::getBucketInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, ['bucket' => $bucketPath]);
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
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            DBController::deleteRecord('s3_delete_buckets', [['id', '=', $bucketId]]);
        }

        // Clean up s3_buckets record
        DBController::deleteRecord('s3_buckets', [
            ['name', '=', $bucketName],
            ['user_id', '=', $userId]
        ]);

        // Also update s3_buckets.deleted_at if column exists
        if (Capsule::schema()->hasColumn('s3_buckets', 'deleted_at')) {
            Capsule::table('s3_buckets')
                ->where('name', $bucketName)
                ->where('user_id', $userId)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        continue;
    }

    // Attempt to delete bucket using admin credentials
    $response = $bucketController->deleteBucketAsAdmin($userId, $bucketName, true);

    if ($response['status'] === 'success') {
        logModuleCall('cloudstorage', 's3deletebucket_cron', ['bucket' => $bucketName], 'Bucket deleted successfully.');

        if ($hasStatusColumn) {
            Capsule::table('s3_delete_buckets')
                ->where('id', $bucketId)
                ->update([
                    'status' => 'success',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            DBController::deleteRecord('s3_delete_buckets', [['id', '=', $bucketId]]);
        }

        // Clean up s3_buckets record
        DBController::deleteRecord('s3_buckets', [
            ['name', '=', $bucketName],
            ['user_id', '=', $userId]
        ]);

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
    ], $errorMsg);

    if ($hasStatusColumn) {
        $newStatus = 'queued';  // Will retry
        if ($isBlocked) {
            $newStatus = 'blocked';  // Object Lock retention - won't retry until manually reset
        } elseif ($newAttemptCount >= MAX_ATTEMPTS) {
            $newStatus = 'failed';  // Max attempts reached
        }

        Capsule::table('s3_delete_buckets')
            ->where('id', $bucketId)
            ->update([
                'status' => $newStatus,
                'error' => $errorMsg,
                'attempt_count' => $newAttemptCount,
                'completed_at' => ($newStatus !== 'queued') ? date('Y-m-d H:i:s') : null,
            ]);
    } else {
        // Legacy: just increment attempt count
        DBController::updateRecord('s3_delete_buckets', [
            'attempt_count' => $newAttemptCount
        ], [['id', '=', $bucketId]]);
    }
}

logModuleCall('cloudstorage', 's3deletebucket_cron', ['processed' => count($buckets)], 'Cron run completed.');
