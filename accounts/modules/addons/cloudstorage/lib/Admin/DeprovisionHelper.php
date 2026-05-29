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
     * Returns the uid exactly as stored — no sanitization.  Legacy users may have
     * email-style uids that genuinely exist in Ceph RGW.
     *
     * @param object $user The s3_users row object (must have ->username and optionally ->tenant_id)
     * @return string The Ceph UID
     */
    public static function computeCephUid($user): string
    {
        $base = '';
        if (is_object($user)) {
            $base = (string)($user->ceph_uid ?? '');
            if ($base === '') {
                $base = (string)($user->username ?? '');
            }
            $tenantId = (string)($user->tenant_id ?? '');
            if ($tenantId !== '') {
                return $tenantId . '$' . $base;
            }
            return $base;
        }
        return '';
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

        // Find primary user (one with no parent_id, or use parent for sub-tenant)
        $user = \WHMCS\Module\Addon\CloudStorage\Client\DBController::getUser($storageUsername);
        if ($user !== null && isset($user->parent_id) && !empty($user->parent_id)) {
            $user = Capsule::table('s3_users')
                ->where('id', $user->parent_id)
                ->first();
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

    /**
     * Reset onboarding for a WHMCS client so a tester can re-run the full
     * sign-up flow against the same client. Idempotent.
     *
     * Actions (each best-effort, none fatal):
     *  - Cancel any tblhosting service whose packageid is e3 Cloud Backup,
     *    e3 Object Storage, or eazyBackup Cloud Backup; clear username.
     *  - Delete s3_cloudbackup_trial_state / usage snapshots / rated lines
     *    for the client.
     *  - Delete cloudstorage_trial_selection row.
     *  - Reset eb_password_onboarding so the Welcome page re-prompts.
     *  - Delete unconsumed cloudstorage_trial_verifications rows.
     *  - Delete s3_backup_users / s3_agent_enrollment_tokens /
     *    s3_cloudbackup_agents for this client (so re-enroll is fresh).
     *
     * Does NOT touch s3_users / RGW buckets - those must go through the
     * proper deprovision queue (with Object Lock checks).
     *
     * @return array<string,int> Counts per action.
     */
    public static function resetOnboarding(int $clientId): array
    {
        $counts = [
            'services_cancelled'   => 0,
            'trial_state_deleted'  => 0,
            'snapshots_deleted'    => 0,
            'rated_lines_deleted'  => 0,
            'trial_selection'      => 0,
            'password_onboarding'  => 0,
            'verifications'        => 0,
            'backup_users'         => 0,
            'enrollment_tokens'    => 0,
            'cloudbackup_agents'   => 0,
            'onboarding_state'     => 0,
        ];

        $module = 'cloudstorage';
        $pids = self::resetOnboardingPids();

        try {
            if (!empty($pids)) {
                $svcIds = Capsule::table('tblhosting')
                    ->where('userid', $clientId)
                    ->whereIn('packageid', $pids)
                    ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
                    ->pluck('id');
                foreach ($svcIds as $sid) {
                    try {
                        Capsule::table('tblhosting')->where('id', $sid)->update([
                            'domainstatus' => 'Cancelled',
                            'username'     => '',
                            'amount'       => 0,
                            'nextduedate'  => null,
                            'nextinvoicedate' => null,
                        ]);
                        $counts['services_cancelled']++;
                    } catch (\Throwable $e) {
                        logModuleCall($module, 'reset_onboarding_service_cancel_fail', ['service_id' => (int) $sid], $e->getMessage(), [], []);
                    }
                }
            }
        } catch (\Throwable $e) {
            logModuleCall($module, 'reset_onboarding_services_query_fail', ['client_id' => $clientId], $e->getMessage(), [], []);
        }

        $tableDeletes = [
            's3_cloudbackup_trial_state'      => ['client_id', 'trial_state_deleted'],
            's3_cloudbackup_usage_snapshots'  => ['client_id', 'snapshots_deleted'],
            's3_cloudbackup_rated_lines'      => ['client_id', 'rated_lines_deleted'],
            'cloudstorage_trial_selection'    => ['client_id', 'trial_selection'],
            's3_backup_users'                 => ['client_id', 'backup_users'],
            's3_agent_enrollment_tokens'      => ['client_id', 'enrollment_tokens'],
            's3_cloudbackup_agents'           => ['client_id', 'cloudbackup_agents'],
            's3_e3backup_onboarding_state'    => ['client_id', 'onboarding_state'],
        ];
        foreach ($tableDeletes as $table => $config) {
            [$column, $key] = $config;
            try {
                if (Capsule::schema()->hasTable($table)) {
                    $counts[$key] = (int) Capsule::table($table)->where($column, $clientId)->delete();
                }
            } catch (\Throwable $e) {
                logModuleCall($module, 'reset_onboarding_delete_fail', ['table' => $table, 'client_id' => $clientId], $e->getMessage(), [], []);
            }
        }

        // Re-enable the "must set password" onboarding prompt.
        try {
            if (Capsule::schema()->hasTable('eb_password_onboarding')) {
                $now = date('Y-m-d H:i:s');
                $exists = Capsule::table('eb_password_onboarding')->where('client_id', $clientId)->exists();
                if ($exists) {
                    Capsule::table('eb_password_onboarding')->where('client_id', $clientId)->update([
                        'must_set'     => 1,
                        'completed_at' => null,
                        'updated_at'   => $now,
                    ]);
                    $counts['password_onboarding'] = 1;
                }
            }
        } catch (\Throwable $e) {
            logModuleCall($module, 'reset_onboarding_password_onboarding_fail', ['client_id' => $clientId], $e->getMessage(), [], []);
        }

        // Delete only unconsumed verification rows.
        try {
            if (Capsule::schema()->hasTable('cloudstorage_trial_verifications')) {
                $counts['verifications'] = (int) Capsule::table('cloudstorage_trial_verifications')
                    ->where('client_id', $clientId)
                    ->whereNull('consumed_at')
                    ->delete();
            }
        } catch (\Throwable $e) {
            logModuleCall($module, 'reset_onboarding_verifications_fail', ['client_id' => $clientId], $e->getMessage(), [], []);
        }

        logModuleCall($module, 'reset_onboarding_complete', ['client_id' => $clientId], $counts, [], []);
        return $counts;
    }

    /**
     * Return the WHMCS product IDs (tblproducts.id) for the cloudstorage
     * addon's three product slots: eazyBackup (Comet), legacy Cloud Storage,
     * and e3 Cloud Backup. Used by both reset-onboarding and the admin
     * customer search so the latter can show only the services that are
     * relevant to deprovision / reset workflows.
     */
    public static function cloudstorageProductIds(): array
    {
        $pids = [];
        foreach (['pid_cloud_backup', 'pid_cloud_storage', 'pid_e3_cloud_backup'] as $setting) {
            try {
                $val = (int) Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudstorage')
                    ->where('setting', $setting)
                    ->value('value');
                if ($val > 0) {
                    $pids[] = $val;
                }
            } catch (\Throwable $e) {
            }
        }
        return array_values(array_unique($pids));
    }

    /**
     * Back-compat alias retained for resetOnboarding(); new callers should
     * use cloudstorageProductIds() directly.
     */
    private static function resetOnboardingPids(): array
    {
        return self::cloudstorageProductIds();
    }

    /**
     * Free-text search across tblclients for the admin Deprovision /
     * Reset Onboarding tools. Matches against client id, email, first
     * + last name, and company name. Each result is augmented with the
     * client's relevant cloudstorage services so the admin can pick
     * the right one without an extra lookup step.
     *
     * @param string $query
     * @param int    $limit  Max clients to return (default 15)
     * @return array<int,array<string,mixed>>
     */
    public static function searchCustomers(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '' || strlen($query) < 2) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $like  = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $numericId = ctype_digit($query) ? (int) $query : null;

        try {
            $base = Capsule::table('tblclients')
                ->select('id', 'firstname', 'lastname', 'companyname', 'email', 'status', 'datecreated')
                ->orderBy('id', 'desc')
                ->limit($limit);

            $base->where(function ($q) use ($like, $numericId) {
                $q->where('email', 'like', $like)
                  ->orWhere('firstname', 'like', $like)
                  ->orWhere('lastname', 'like', $like)
                  ->orWhere('companyname', 'like', $like)
                  ->orWhereRaw('CONCAT(firstname, " ", lastname) LIKE ?', [$like]);
                if ($numericId !== null) {
                    $q->orWhere('id', '=', $numericId);
                }
            });

            $clients = $base->get();
        } catch (\Throwable $e) {
            return [];
        }

        if ($clients->isEmpty()) {
            return [];
        }

        $clientIds = $clients->pluck('id')->all();
        $pids = self::cloudstorageProductIds();

        // Build a map of product names so we can label services in the UI.
        $productNames = [];
        if (!empty($pids)) {
            try {
                $rows = Capsule::table('tblproducts')->whereIn('id', $pids)->select('id', 'name')->get();
                foreach ($rows as $r) { $productNames[(int) $r->id] = (string) $r->name; }
            } catch (\Throwable $e) {
            }
        }

        // Pull all relevant services in one query, then group by client.
        $servicesByClient = [];
        try {
            $svcQuery = Capsule::table('tblhosting')
                ->whereIn('userid', $clientIds)
                ->select('id', 'userid', 'username', 'packageid', 'domainstatus', 'regdate');
            if (!empty($pids)) {
                $svcQuery->whereIn('packageid', $pids);
            }
            $services = $svcQuery->orderBy('id', 'desc')->get();
            foreach ($services as $svc) {
                $cid = (int) $svc->userid;
                if (!isset($servicesByClient[$cid])) {
                    $servicesByClient[$cid] = [];
                }
                $servicesByClient[$cid][] = [
                    'id'           => (int) $svc->id,
                    'username'     => (string) ($svc->username ?? ''),
                    'packageid'    => (int) $svc->packageid,
                    'product'      => $productNames[(int) $svc->packageid] ?? ('Product #' . (int) $svc->packageid),
                    'domainstatus' => (string) ($svc->domainstatus ?? ''),
                    'regdate'      => (string) ($svc->regdate ?? ''),
                ];
            }
        } catch (\Throwable $e) {
        }

        $results = [];
        foreach ($clients as $c) {
            $full = trim(((string) ($c->firstname ?? '')) . ' ' . ((string) ($c->lastname ?? '')));
            $results[] = [
                'id'          => (int) $c->id,
                'name'        => $full !== '' ? $full : null,
                'companyname' => (string) ($c->companyname ?? ''),
                'email'       => (string) ($c->email ?? ''),
                'status'      => (string) ($c->status ?? ''),
                'datecreated' => (string) ($c->datecreated ?? ''),
                'services'    => $servicesByClient[(int) $c->id] ?? [],
            ];
        }

        return $results;
    }
}

