<?php

/**
 * Background cron to process user deprovision queue (s3_delete_users).
 *
 * Flow:
 * 1. Pick up queued/running jobs
 * 2. For each job, resolve primary user + sub-tenants
 * 3. Check if all buckets for those users are deleted (or still queued)
 * 4. If buckets remain, skip (will retry next cron run)
 * 5. If all buckets gone, delete RGW users via AdminOps::removeUser
 * 6. Mark job success/failed/blocked
 *
 * Protected usernames are blocked at queue time (DeprovisionHelper), but we double-check here.
 */

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

// Load DeprovisionHelper
require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Admin/DeprovisionHelper.php';
use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;

// Max attempts before marking as failed
const MAX_ATTEMPTS = 10;

// Schema checks (older installs may not have new columns yet)
$hasS3UsersIsActive = false;
$hasS3UsersDeletedAt = false;
try { $hasS3UsersIsActive = Capsule::schema()->hasColumn('s3_users', 'is_active'); } catch (\Throwable $e) {}
try { $hasS3UsersDeletedAt = Capsule::schema()->hasColumn('s3_users', 'deleted_at'); } catch (\Throwable $e) {}

// Check if table exists
if (!Capsule::schema()->hasTable('s3_delete_users')) {
    exit(0);
}

// Get queued or running jobs
$jobs = Capsule::table('s3_delete_users')
    ->whereIn('status', ['queued', 'running'])
    ->where('attempt_count', '<', MAX_ATTEMPTS)
    ->orderBy('created_at', 'asc')
    ->limit(10)
    ->get();

if ($jobs->isEmpty()) {
    exit(0);
}

// Get module configuration
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    logModuleCall('cloudstorage', 's3deleteuser_cron', [], 'Cloudstorage module not configured.');
    exit(1);
}

$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();

if (empty($s3Endpoint) || empty($cephAdminAccessKey) || empty($cephAdminSecretKey)) {
    logModuleCall('cloudstorage', 's3deleteuser_cron', [], 'Missing S3/Ceph admin configuration');
    exit(1);
}

foreach ($jobs as $job) {
    $jobId = $job->id;
    $primaryUserId = $job->primary_user_id;
    $attemptCount = $job->attempt_count ?? 0;

    try {
        // Mark as running
        Capsule::table('s3_delete_users')
            ->where('id', $jobId)
            ->update([
                'status' => 'running',
                'started_at' => date('Y-m-d H:i:s'),
                'attempt_count' => $attemptCount + 1,
                'error' => null,
            ]);

        // Get primary user
        $primaryUser = Capsule::table('s3_users')->where('id', $primaryUserId)->first();
        if (!$primaryUser) {
            // Primary user already deleted from DB, mark job success
            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => 'success',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'error' => 'Primary user already deleted from database.',
                ]);
            logModuleCall('cloudstorage', 's3deleteuser_cron', ['job_id' => $jobId], 'Primary user already gone, marking success.');
            continue;
        }

        // Check protected username
        if (DeprovisionHelper::isProtectedUsername($primaryUser->username)) {
            $errorMsg = "Username '{$primaryUser->username}' is protected and cannot be deleted.";
            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => 'blocked',
                    'error' => $errorMsg,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            logModuleCall('cloudstorage', 's3deleteuser_cron', ['job_id' => $jobId, 'username' => $primaryUser->username], $errorMsg);
            continue;
        }

        // Get sub-tenants
        $subTenants = Capsule::table('s3_users')
            ->where('parent_id', $primaryUserId)
            ->get();

        // Collect all user IDs
        $allUserIds = array_merge([$primaryUserId], $subTenants->pluck('id')->toArray());

        logModuleCall('cloudstorage', 's3deleteuser_cron_job', [
            'job_id' => $jobId,
            'primary_user_id' => $primaryUserId,
            'primary_username' => $primaryUser->username,
            'sub_tenant_count' => $subTenants->count(),
            'user_ids' => $allUserIds,
        ], 'Job started');

        // Check for any protected sub-tenants
        $protectedSubTenant = null;
        foreach ($subTenants as $tenant) {
            if (DeprovisionHelper::isProtectedUsername($tenant->username)) {
                $protectedSubTenant = $tenant->username;
                break;
            }
        }

        if ($protectedSubTenant) {
            $errorMsg = "Sub-tenant '{$protectedSubTenant}' is protected and cannot be deleted.";
            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => 'blocked',
                    'error' => $errorMsg,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            logModuleCall('cloudstorage', 's3deleteuser_cron', ['job_id' => $jobId], $errorMsg);
            continue;
        }

        // Check if all buckets are deleted
        $hasDeleteBucketsStatus = false;
        try { $hasDeleteBucketsStatus = Capsule::schema()->hasColumn('s3_delete_buckets', 'status'); } catch (\Throwable $e) {}

        $pendingBucketJobs = 0;
        if ($hasDeleteBucketsStatus) {
            $pendingBucketJobs = Capsule::table('s3_delete_buckets')
                ->whereIn('user_id', $allUserIds)
                ->whereIn('status', ['queued', 'running'])
                ->count();
        } else {
            $pendingBucketJobs = Capsule::table('s3_delete_buckets')
                ->whereIn('user_id', $allUserIds)
                ->where('attempt_count', '<', 5)
                ->count();
        }

        // Also check for any active buckets still in s3_buckets (if that column exists)
        $activeBuckets = 0;
        $hasBucketsIsActive = false;
        try { $hasBucketsIsActive = Capsule::schema()->hasColumn('s3_buckets', 'is_active'); } catch (\Throwable $e) {}
        if ($hasBucketsIsActive) {
            $activeBuckets = Capsule::table('s3_buckets')
                ->whereIn('user_id', $allUserIds)
                ->where('is_active', 1)
                ->count();
        }

        logModuleCall('cloudstorage', 's3deleteuser_cron_bucket_gate', [
            'job_id' => $jobId,
            'pending_bucket_jobs' => $pendingBucketJobs,
            'active_buckets' => $activeBuckets,
            'has_delete_buckets_status' => $hasDeleteBucketsStatus,
            'has_s3_buckets_is_active' => $hasBucketsIsActive,
        ], 'Bucket gate check');

        if ($pendingBucketJobs > 0 || $activeBuckets > 0) {
            // Buckets still being processed, requeue for next run
            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => 'queued',
                    'error' => "Waiting for bucket deletion: {$pendingBucketJobs} jobs pending, {$activeBuckets} active buckets.",
                ]);
            continue;
        }

        // Check for blocked bucket jobs (Object Lock)
        $blockedBucketJobs = 0;
        if ($hasDeleteBucketsStatus) {
            $blockedBucketJobs = Capsule::table('s3_delete_buckets')
                ->whereIn('user_id', $allUserIds)
                ->where('status', 'blocked')
                ->count();
        }

        if ($blockedBucketJobs > 0) {
            $errorMsg = "{$blockedBucketJobs} bucket(s) are blocked (likely Object Lock retention). Cannot delete users until buckets are cleared.";
            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => 'blocked',
                    'error' => $errorMsg,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            logModuleCall('cloudstorage', 's3deleteuser_cron', ['job_id' => $jobId], $errorMsg);
            continue;
        }

        // All buckets deleted, now delete RGW users
        $allUsersDeleted = true;
        $errors = [];

        // Delete sub-tenants first, then primary
        $usersToDelete = array_merge($subTenants->all(), [$primaryUser]);

        logModuleCall('cloudstorage', 's3deleteuser_cron_delete_begin', [
            'job_id' => $jobId,
            'user_count' => count($usersToDelete),
            'users' => array_map(function ($u) { return ['id' => $u->id, 'username' => $u->username, 'ceph_uid' => DeprovisionHelper::computeCephUid($u)]; }, $usersToDelete),
        ], 'Deleting RGW users');

        foreach ($usersToDelete as $user) {
            $cephUid = DeprovisionHelper::computeCephUid($user);

            // Double-check protected
            if (DeprovisionHelper::isProtectedUsername($user->username)) {
                $errors[] = "Skipped protected user: {$user->username}";
                continue;
            }

            $result = AdminOps::removeUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $cephUid);
            logModuleCall('cloudstorage', 's3deleteuser_cron_delete_user', [
                'job_id' => $jobId,
                'user_id' => $user->id,
                'username' => $user->username,
                'ceph_uid' => $cephUid,
            ], $result);

            if (($result['status'] ?? '') === 'success' || (($result['error_type'] ?? '') === 'NoSuchUser')) {
                // Mark user as deleted in DB (tolerate missing columns)
                $update = [];
                if ($hasS3UsersIsActive) {
                    $update['is_active'] = 0;
                }
                if ($hasS3UsersDeletedAt) {
                    $update['deleted_at'] = date('Y-m-d H:i:s');
                }
                if (!empty($update)) {
                    Capsule::table('s3_users')->where('id', $user->id)->update($update);
                }
                Capsule::table('s3_user_access_keys')->where('user_id', $user->id)->delete();
            } else {
                $allUsersDeleted = false;
                $errors[] = "Failed to delete {$cephUid}: " . ($result['message'] ?? 'Unknown error');
            }
        }

        // Update job status
        if ($allUsersDeleted) {
            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => 'success',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'error' => empty($errors) ? null : implode('; ', $errors),
                ]);
            logModuleCall('cloudstorage', 's3deleteuser_cron_job_done', ['job_id' => $jobId], 'success');
        } else {
            $newAttemptCount = $attemptCount + 1;
            $newStatus = ($newAttemptCount >= MAX_ATTEMPTS) ? 'failed' : 'queued';

            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => $newStatus,
                    'error' => implode('; ', $errors),
                    'completed_at' => ($newStatus === 'failed') ? date('Y-m-d H:i:s') : null,
                ]);
            logModuleCall('cloudstorage', 's3deleteuser_cron_job_done', [
                'job_id' => $jobId,
                'status' => $newStatus,
                'errors' => $errors,
            ], 'not all users deleted');
        }
    } catch (\Throwable $e) {
        // Never leave a job stuck in running if something fatal happens (e.g., schema mismatch)
        $msg = 'Exception: ' . $e->getMessage();
        try {
            Capsule::table('s3_delete_users')
                ->where('id', $jobId)
                ->update([
                    'status' => 'failed',
                    'error' => $msg,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $_) {}
        logModuleCall('cloudstorage', 's3deleteuser_cron_exception', [
            'job_id' => $jobId,
            'primary_user_id' => $primaryUserId,
        ], $msg);
        continue;
    }
}

logModuleCall('cloudstorage', 's3deleteuser_cron', ['processed' => $jobs->count()], 'Cron run completed.');

