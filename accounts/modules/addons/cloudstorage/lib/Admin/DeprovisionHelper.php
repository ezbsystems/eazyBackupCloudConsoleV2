<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;

/**
 * Helper utilities for Cloud Storage customer deprovision operations.
 * Provides protected resource blocking, user/bucket resolution, and Ceph UID computation.
 */
class DeprovisionHelper
{
    private static $module = 'cloudstorage';

    /**
     * Protected RGW usernames that must never be deleted (exact match).
     */
    private static $protectedUsernames = [
        'eazybackup',
        'eazybackup-backups',
    ];

    /**
     * Protected bucket names that must never be deleted (exact match).
     */
    private static $protectedBuckets = [
        'csw-eazybackup-data',
        'csw-obc-data',
    ];

    /**
     * Check if a username is protected from deletion.
     * Handles both plain usernames and tenant-qualified forms (tenant$username).
     *
     * @param string $username The username or Ceph UID to check
     * @return bool True if protected
     */
    public static function isProtectedUsername(string $username): bool
    {
        // Extract base username if tenant-qualified (tenant$username)
        $baseUsername = $username;
        if (strpos($username, '$') !== false) {
            $parts = explode('$', $username, 2);
            $baseUsername = $parts[1] ?? $username;
        }

        return in_array($baseUsername, self::$protectedUsernames, true);
    }

    /**
     * Check if a bucket name is protected from deletion.
     *
     * @param string $bucketName The bucket name to check
     * @return bool True if protected
     */
    public static function isProtectedBucket(string $bucketName): bool
    {
        return in_array($bucketName, self::$protectedBuckets, true);
    }

    /**
     * Compute the Ceph RGW UID for an s3_users row.
     * If tenant_id is set, returns "tenant_id$username", otherwise just "username".
     *
     * @param object $user The s3_users row object (must have ->username and optionally ->tenant_id)
     * @return string The Ceph UID
     */
    public static function computeCephUid($user): string
    {
        if (!empty($user->tenant_id)) {
            return $user->tenant_id . '$' . $user->username;
        }
        return $user->username;
    }

    /**
     * Resolve a primary s3_users record from either a WHMCS service ID or a username.
     *
     * @param int|null $serviceId WHMCS tblhosting.id
     * @param string|null $username Storage username
     * @return object|null The primary s3_users row or null if not found
     */
    public static function resolvePrimaryUser(?int $serviceId = null, ?string $username = null)
    {
        $storageUsername = null;

        // Resolve username from service ID
        if ($serviceId !== null && $serviceId > 0) {
            $service = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->select('username')
                ->first();
            if ($service && !empty($service->username)) {
                $storageUsername = $service->username;
            }
        }

        // Use provided username if service didn't resolve
        if ($storageUsername === null && !empty($username)) {
            $storageUsername = $username;
        }

        if ($storageUsername === null) {
            return null;
        }

        // Find primary user (one with no parent_id, or the first match if all have parent_id)
        $user = Capsule::table('s3_users')
            ->where('username', $storageUsername)
            ->whereNull('parent_id')
            ->first();

        // If not found as primary, check if it's a sub-tenant and find its parent
        if ($user === null) {
            $subTenant = Capsule::table('s3_users')
                ->where('username', $storageUsername)
                ->whereNotNull('parent_id')
                ->first();

            if ($subTenant !== null && $subTenant->parent_id) {
                // Return the parent as the primary
                $user = Capsule::table('s3_users')
                    ->where('id', $subTenant->parent_id)
                    ->first();
            }
        }

        return $user;
    }

    /**
     * Get all sub-tenants for a primary user.
     *
     * @param int $primaryUserId The primary s3_users.id
     * @return \Illuminate\Support\Collection Collection of s3_users rows
     */
    public static function getSubTenants(int $primaryUserId)
    {
        return Capsule::table('s3_users')
            ->where('parent_id', $primaryUserId)
            ->get();
    }

    /**
     * Get all buckets for a set of user IDs.
     *
     * @param array $userIds Array of s3_users.id values
     * @return \Illuminate\Support\Collection Collection of s3_buckets rows
     */
    public static function getBucketsForUsers(array $userIds)
    {
        if (empty($userIds)) {
            return collect([]);
        }

        return Capsule::table('s3_buckets')
            ->whereIn('user_id', $userIds)
            ->get();
    }

    /**
     * Build a complete deprovision plan for a primary user.
     * Returns array with primary user, sub-tenants, buckets, and any protected resource warnings.
     *
     * @param int $primaryUserId The primary s3_users.id
     * @return array Deprovision plan with keys: primary, sub_tenants, buckets, protected_warnings, can_proceed
     */
    public static function buildDeprovisionPlan(int $primaryUserId): array
    {
        $plan = [
            'primary' => null,
            'sub_tenants' => [],
            'buckets' => [],
            'protected_warnings' => [],
            'can_proceed' => true,
        ];

        // Get primary user
        $primary = Capsule::table('s3_users')->where('id', $primaryUserId)->first();
        if ($primary === null) {
            $plan['can_proceed'] = false;
            $plan['protected_warnings'][] = 'Primary user not found.';
            return $plan;
        }
        $plan['primary'] = $primary;
        $plan['primary']->ceph_uid = self::computeCephUid($primary);

        // Check if primary is protected
        if (self::isProtectedUsername($primary->username)) {
            $plan['can_proceed'] = false;
            $plan['protected_warnings'][] = "Username '{$primary->username}' is protected and cannot be deleted.";
        }

        // Get sub-tenants
        $subTenants = self::getSubTenants($primaryUserId);
        foreach ($subTenants as $tenant) {
            $tenant->ceph_uid = self::computeCephUid($tenant);
            $plan['sub_tenants'][] = $tenant;

            // Check if sub-tenant is protected
            if (self::isProtectedUsername($tenant->username)) {
                $plan['can_proceed'] = false;
                $plan['protected_warnings'][] = "Sub-tenant username '{$tenant->username}' is protected and cannot be deleted.";
            }
        }

        // Collect all user IDs
        $allUserIds = array_merge([$primaryUserId], array_column($plan['sub_tenants'], 'id'));

        // Get all buckets
        $buckets = self::getBucketsForUsers($allUserIds);
        foreach ($buckets as $bucket) {
            $plan['buckets'][] = $bucket;

            // Check if bucket is protected
            if (self::isProtectedBucket($bucket->name)) {
                $plan['can_proceed'] = false;
                $plan['protected_warnings'][] = "Bucket '{$bucket->name}' is protected and cannot be deleted.";
            }
        }

        return $plan;
    }

    /**
     * Queue a deprovision job for a primary user.
     * Inserts into s3_delete_users and flips is_active flags.
     *
     * @param int $primaryUserId The primary s3_users.id
     * @param int|null $adminId The WHMCS admin ID requesting the deprovision
     * @param array $planSnapshot The deprovision plan snapshot for audit
     * @return array Result with status and message
     */
    public static function queueDeprovision(int $primaryUserId, ?int $adminId = null, array $planSnapshot = []): array
    {
        try {
            // Build and validate plan
            $plan = self::buildDeprovisionPlan($primaryUserId);

            if (!$plan['can_proceed']) {
                return [
                    'status' => 'fail',
                    'message' => 'Cannot proceed: ' . implode(' ', $plan['protected_warnings']),
                ];
            }

            // Check if already queued
            $existing = Capsule::table('s3_delete_users')
                ->where('primary_user_id', $primaryUserId)
                ->whereIn('status', ['queued', 'running'])
                ->first();

            if ($existing) {
                return [
                    'status' => 'fail',
                    'message' => 'A deprovision job is already queued or running for this user.',
                ];
            }

            // Collect all user IDs
            $allUserIds = array_merge([$primaryUserId], array_column($plan['sub_tenants'], 'id'));

            $hasUsersIsActive = false;
            $hasBucketsIsActive = false;
            $hasDeleteBucketsStatus = false;
            try {
                $hasUsersIsActive = Capsule::schema()->hasColumn('s3_users', 'is_active');
            } catch (\Throwable $e) {}
            try {
                $hasBucketsIsActive = Capsule::schema()->hasColumn('s3_buckets', 'is_active');
            } catch (\Throwable $e) {}
            try {
                $hasDeleteBucketsStatus = Capsule::schema()->hasColumn('s3_delete_buckets', 'status');
            } catch (\Throwable $e) {}

            // Use WHMCS Capsule connection transaction API (Capsule::beginTransaction is not available)
            $jobId = null;
            Capsule::connection()->transaction(function () use (
                $primaryUserId,
                $adminId,
                $planSnapshot,
                $plan,
                $allUserIds,
                $hasUsersIsActive,
                $hasBucketsIsActive,
                $hasDeleteBucketsStatus,
                &$jobId
            ) {
                // Insert deprovision job
                $jobId = Capsule::table('s3_delete_users')->insertGetId([
                    'primary_user_id' => $primaryUserId,
                    'requested_by_admin_id' => $adminId,
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'plan_json' => json_encode($planSnapshot ?: $plan),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                // Flip is_active = 0 for all users (if column exists)
                if ($hasUsersIsActive) {
                    Capsule::table('s3_users')
                        ->whereIn('id', $allUserIds)
                        ->update(['is_active' => 0]);
                }

                // Flip is_active = 0 for all buckets (if column exists)
                if ($hasBucketsIsActive) {
                    Capsule::table('s3_buckets')
                        ->whereIn('user_id', $allUserIds)
                        ->update(['is_active' => 0]);
                }

                // Queue bucket deletions (dedupe)
                foreach ($plan['buckets'] as $bucket) {
                    $q = Capsule::table('s3_delete_buckets')
                        ->where('user_id', $bucket->user_id)
                        ->where('bucket_name', $bucket->name);
                    if ($hasDeleteBucketsStatus) {
                        $q->whereIn('status', ['queued', 'running']);
                    } else {
                        // Legacy table: no status column
                        $q->where('attempt_count', '<', 5);
                    }
                    $existingBucket = $q->first();

                    if (!$existingBucket) {
                        $row = [
                            'user_id' => $bucket->user_id,
                            'bucket_name' => $bucket->name,
                            'attempt_count' => 0,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        if ($hasDeleteBucketsStatus) {
                            $row['status'] = 'queued';
                        }
                        Capsule::table('s3_delete_buckets')->insert($row);
                    }
                }
            });

            logModuleCall(self::$module, 'queueDeprovision', [
                'primary_user_id' => $primaryUserId,
                'admin_id' => $adminId,
            ], [
                'job_id' => $jobId,
                'users_deactivated' => count($allUserIds),
                'buckets_queued' => count($plan['buckets']),
            ]);

            return [
                'status' => 'success',
                'message' => 'Deprovision job queued successfully.',
                'job_id' => $jobId,
                'users_deactivated' => count($allUserIds),
                'buckets_queued' => count($plan['buckets']),
            ];

        } catch (\Exception $e) {
            logModuleCall(self::$module, 'queueDeprovision', [
                'primary_user_id' => $primaryUserId,
                'admin_id' => $adminId,
            ], $e->getMessage());

            return [
                'status' => 'fail',
                'message' => 'Failed to queue deprovision: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get WHMCS service info for a storage username.
     *
     * @param string $username The storage username
     * @return object|null The tblhosting row or null
     */
    public static function getServiceForUsername(string $username)
    {
        return Capsule::table('tblhosting')
            ->where('username', $username)
            ->select('id', 'userid', 'username', 'domainstatus', 'packageid', 'regdate', 'nextduedate')
            ->first();
    }

    /**
     * Get WHMCS client info for a client ID.
     *
     * @param int $clientId The WHMCS client ID
     * @return object|null The tblclients row or null
     */
    public static function getClientInfo(int $clientId)
    {
        return Capsule::table('tblclients')
            ->where('id', $clientId)
            ->select('id', 'firstname', 'lastname', 'companyname', 'email', 'status')
            ->first();
    }
}

