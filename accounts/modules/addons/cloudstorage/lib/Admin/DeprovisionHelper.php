<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

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
     * @param array $opts Options:
     *   - confirm_bypass_governance: bool (allow governance retention bypass for applicable buckets)
     * @return array Result with status and message
     */
    public static function queueDeprovision(int $primaryUserId, ?int $adminId = null, array $planSnapshot = [], array $opts = []): array
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

            // Optional: determine Object Lock modes for buckets so we can:
            // - set force_bypass_governance on bucket delete jobs (for governance)
            // - revoke access immediately for compliance (non-empty) buckets
            $confirmBypassGovernance = !empty($opts['confirm_bypass_governance']);
            $ola = null;
            $olaSummary = [
                'non_empty_compliance_buckets' => [],
                'non_empty_governance_buckets' => [],
                'non_empty_unknown_mode_buckets' => [],
            ];
            $olaBuckets = [];
            try {
                $bucketNames = [];
                foreach (($plan['buckets'] ?? []) as $b) {
                    if (!empty($b->name)) {
                        $bucketNames[] = (string) $b->name;
                    }
                }
                $ola = self::buildObjectLockAssessmentForBuckets($bucketNames);
                if (is_array($ola) && ($ola['status'] ?? 'fail') === 'success') {
                    $olaSummary = $ola['summary'] ?? $olaSummary;
                    $olaBuckets = $ola['buckets'] ?? [];
                }
            } catch (\Throwable $e) {
                $ola = null;
            }

            $hasComplianceNonEmpty = count(($olaSummary['non_empty_compliance_buckets'] ?? [])) > 0;
            $forceBypassByBucketName = [];
            if ($confirmBypassGovernance && !$hasComplianceNonEmpty) {
                foreach ($plan['buckets'] as $b) {
                    $bn = (string) ($b->name ?? '');
                    if ($bn === '') {
                        continue;
                    }
                    $a = $olaBuckets[$bn] ?? null;
                    if (is_array($a)) {
                        $mode = strtoupper((string) ($a['default_mode'] ?? ''));
                        $isNonEmpty = array_key_exists('empty', $a) ? ($a['empty'] === false) : false;
                        $isLocked = !empty($a['object_lock_enabled']);
                        if ($isLocked && $isNonEmpty && $mode === 'GOVERNANCE') {
                            $forceBypassByBucketName[$bn] = true;
                        }
                    }
                }
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
            $hasDeleteBucketsForceBypass = false;
            try {
                $hasUsersIsActive = Capsule::schema()->hasColumn('s3_users', 'is_active');
            } catch (\Throwable $e) {}
            try {
                $hasBucketsIsActive = Capsule::schema()->hasColumn('s3_buckets', 'is_active');
            } catch (\Throwable $e) {}
            try {
                $hasDeleteBucketsStatus = Capsule::schema()->hasColumn('s3_delete_buckets', 'status');
            } catch (\Throwable $e) {}
            try {
                $hasDeleteBucketsForceBypass = Capsule::schema()->hasColumn('s3_delete_buckets', 'force_bypass_governance');
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
                $hasDeleteBucketsForceBypass,
                $forceBypassByBucketName,
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
                    $bucketName = (string) $bucket->name;
                    $shouldBypass = !empty($forceBypassByBucketName[$bucketName]);
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
                        if ($hasDeleteBucketsForceBypass) {
                            $row['force_bypass_governance'] = $shouldBypass ? 1 : 0;
                        }
                        Capsule::table('s3_delete_buckets')->insert($row);
                    } else {
                        // If an existing queued/running job exists and this deprovision requires governance bypass,
                        // upgrade the job to allow bypass (best-effort).
                        if ($hasDeleteBucketsForceBypass && $shouldBypass) {
                            try {
                                Capsule::table('s3_delete_buckets')
                                    ->where('id', $existingBucket->id)
                                    ->update(['force_bypass_governance' => 1]);
                            } catch (\Throwable $e) {
                                // Best-effort
                            }
                        }
                    }
                }
            });

            logModuleCall(self::$module, 'queueDeprovision', [
                'primary_user_id' => $primaryUserId,
                'admin_id' => $adminId,
                'confirm_bypass_governance' => $confirmBypassGovernance ? 1 : 0,
            ], [
                'job_id' => $jobId,
                'users_deactivated' => count($allUserIds),
                'buckets_queued' => count($plan['buckets']),
            ]);

            // Compliance retention: revoke access immediately (best-effort), but allow deletion to block on retention.
            // We only attempt revocation when Compliance Object Lock buckets are detected as non-empty.
            if ($hasComplianceNonEmpty) {
                try {
                    // Load module settings needed for AdminOps
                    $module = Capsule::table('tbladdonmodules')
                        ->where('module', 'cloudstorage')
                        ->get(['setting', 'value']);

                    $endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
                    $adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
                    $adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();

                    $revokeMetrics = [
                        'revoked_keys' => 0,
                        'revoked_subusers' => 0,
                        'errors' => [],
                    ];

                    if (!empty($endpoint) && !empty($adminAccessKey) && !empty($adminSecretKey)) {
                        // Resolve users to revoke: sub-tenants first, then primary
                        $subTenants = Capsule::table('s3_users')->where('parent_id', $primaryUserId)->get();
                        $primaryUser = Capsule::table('s3_users')->where('id', $primaryUserId)->first();
                        $users = array_merge($subTenants ? $subTenants->all() : [], $primaryUser ? [$primaryUser] : []);

                        foreach ($users as $u) {
                            if (!$u || empty($u->username)) {
                                continue;
                            }
                            $cephUid = self::computeCephUid($u);
                            $info = AdminOps::getUserInfo($endpoint, $adminAccessKey, $adminSecretKey, $cephUid);
                            if (($info['status'] ?? 'fail') !== 'success') {
                                $revokeMetrics['errors'][] = "Failed to get user info for {$cephUid}";
                                continue;
                            }
                            $data = $info['data'] ?? [];

                            // Remove access keys
                            $keys = $data['keys'] ?? [];
                            if (is_array($keys)) {
                                foreach ($keys as $k) {
                                    if (!is_array($k)) {
                                        continue;
                                    }
                                    $accessKey = $k['access_key'] ?? $k['access_key_id'] ?? null;
                                    if (!$accessKey) {
                                        continue;
                                    }
                                    $res = AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $accessKey, $cephUid);
                                    if (($res['status'] ?? 'fail') === 'success') {
                                        $revokeMetrics['revoked_keys'] += 1;
                                    } else {
                                        $revokeMetrics['errors'][] = "Failed to remove key {$accessKey} for {$cephUid}";
                                    }
                                }
                            }

                            // Remove subusers (purge-keys=true)
                            $subusers = $data['subusers'] ?? [];
                            if (is_array($subusers)) {
                                foreach ($subusers as $s) {
                                    if (!is_array($s)) {
                                        continue;
                                    }
                                    $sid = $s['id'] ?? $s['name'] ?? null;
                                    if (!$sid) {
                                        continue;
                                    }
                                    // Ceph returns subuser id as "uid:subuser"; AdminOps expects subuser name (after colon)
                                    $subName = (string) $sid;
                                    if (strpos($subName, ':') !== false) {
                                        $parts = explode(':', $subName);
                                        $subName = end($parts);
                                    }
                                    $subName = trim($subName);
                                    if ($subName === '') {
                                        continue;
                                    }
                                    $res = AdminOps::removeSubUser($endpoint, $adminAccessKey, $adminSecretKey, [
                                        'uid' => $cephUid,
                                        'subuser' => $subName,
                                    ]);
                                    if (($res['status'] ?? 'fail') === 'success') {
                                        $revokeMetrics['revoked_subusers'] += 1;
                                    } else {
                                        $revokeMetrics['errors'][] = "Failed to remove subuser {$subName} for {$cephUid}";
                                    }
                                }
                            }
                        }
                    } else {
                        $revokeMetrics['errors'][] = 'Missing S3/Ceph admin configuration for access revocation.';
                    }

                    // Remove DB-stored access keys immediately as well (best-effort)
                    try {
                        Capsule::table('s3_user_access_keys')->whereIn('user_id', $allUserIds)->delete();
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    logModuleCall(self::$module, 'deprovision_revoke_access', [
                        'job_id' => $jobId,
                        'primary_user_id' => $primaryUserId,
                    ], $revokeMetrics);
                } catch (\Throwable $e) {
                    logModuleCall(self::$module, 'deprovision_revoke_access_exception', [
                        'job_id' => $jobId,
                        'primary_user_id' => $primaryUserId,
                    ], $e->getMessage());
                }
            }

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
                'confirm_bypass_governance' => !empty($opts['confirm_bypass_governance']) ? 1 : 0,
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

    /**
     * Build a lightweight Object Lock + emptiness assessment for a list of buckets.
     *
     * Notes:
     * - Uses admin S3 credentials (does not enumerate entire buckets).
     * - Emptiness is checked using MaxKeys=1 list calls via BucketController::isBucketCompletelyEmpty().
     * - Object Lock policy is checked via BucketController::getBucketObjectLockPolicy().
     *
     * @param array $bucketNames
     * @return array
     */
    public static function buildObjectLockAssessmentForBuckets(array $bucketNames): array
    {
        $bucketNames = array_values(array_unique(array_filter(array_map(function ($n) {
            $n = trim((string) $n);
            return $n !== '' ? $n : null;
        }, $bucketNames))));

        $assessment = [
            'status' => 'success',
            'message' => null,
            'buckets' => [], // keyed by bucket name
            'summary' => [
                'non_empty_compliance_buckets' => [],
                'non_empty_governance_buckets' => [],
                'non_empty_unknown_mode_buckets' => [],
            ],
        ];

        if (empty($bucketNames)) {
            return $assessment;
        }

        // Load module settings
        $module = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->get(['setting', 'value']);

        if (!$module || count($module) === 0) {
            return [
                'status' => 'fail',
                'message' => 'Cloud Storage module is not configured (missing addon settings).',
            ];
        }

        $endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
        $adminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
        $adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
        $adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
        $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

        if (empty($endpoint) || empty($adminAccessKey) || empty($adminSecretKey)) {
            return [
                'status' => 'fail',
                'message' => 'Missing S3/Ceph admin configuration (endpoint/access/secret).',
            ];
        }

        $bucketController = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $s3Region);
        $conn = $bucketController->connectS3ClientAsAdmin();
        if (($conn['status'] ?? 'fail') !== 'success') {
            return [
                'status' => 'fail',
                'message' => $conn['message'] ?? 'Unable to connect to S3 as admin for assessment.',
            ];
        }

        foreach ($bucketNames as $bucketName) {
            $row = [
                'bucket_name' => $bucketName,
                'empty' => null,
                'object_lock_enabled' => false,
                'default_mode' => null,
                'default_retention_days' => null,
                'default_retention_years' => null,
                'notes' => [],
            ];

            try {
                $emptyRes = $bucketController->isBucketCompletelyEmpty($bucketName);
                if (($emptyRes['status'] ?? 'fail') === 'success') {
                    $row['empty'] = (bool) ($emptyRes['empty'] ?? false);
                } else {
                    $row['empty'] = null;
                    $row['notes'][] = $emptyRes['message'] ?? 'Unable to verify emptiness.';
                }
            } catch (\Throwable $e) {
                $row['empty'] = null;
                $row['notes'][] = 'Unable to verify emptiness.';
            }

            try {
                $pol = $bucketController->getBucketObjectLockPolicy($bucketName);
                if (($pol['status'] ?? 'fail') === 'success') {
                    $row['object_lock_enabled'] = (bool) ($pol['enabled'] ?? false);
                    $row['default_mode'] = $pol['default_mode'] ?? null;
                    $row['default_retention_days'] = $pol['default_retention_days'] ?? null;
                    $row['default_retention_years'] = $pol['default_retention_years'] ?? null;
                } else {
                    $row['notes'][] = $pol['message'] ?? 'Unable to read Object Lock configuration.';
                }
            } catch (\Throwable $e) {
                $row['notes'][] = 'Unable to read Object Lock configuration.';
            }

            $assessment['buckets'][$bucketName] = $row;

            // Summaries: only care about non-empty buckets, because empty buckets can be deleted safely.
            if ($row['empty'] === false && $row['object_lock_enabled']) {
                $mode = strtoupper((string) ($row['default_mode'] ?? ''));
                if ($mode === 'COMPLIANCE') {
                    $assessment['summary']['non_empty_compliance_buckets'][] = $bucketName;
                } elseif ($mode === 'GOVERNANCE') {
                    $assessment['summary']['non_empty_governance_buckets'][] = $bucketName;
                } else {
                    $assessment['summary']['non_empty_unknown_mode_buckets'][] = $bucketName;
                }
            }
        }

        return $assessment;
    }
}

