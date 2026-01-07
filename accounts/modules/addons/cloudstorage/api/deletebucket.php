<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
    use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200);
        $response->send();
        exit();
    }

    // Resolve client ID (WHMCS v8 user->client mapping)
    $loggedInUserId = (int) $ca->getUserID();
    $clientId = 0;
    try {
        $link = Capsule::table('tblusers_clients')->where('userid', $loggedInUserId)->orderBy('owner', 'desc')->first();
        if ($link && isset($link->clientid)) {
            $clientId = (int) $link->clientid;
        }
    } catch (\Throwable $e) {}
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }
    if ($clientId <= 0) {
        $clientId = $loggedInUserId; // legacy fallback
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($clientId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'User does not exist.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    $bucketName = $_POST['bucket_name'] ?? '';
    $bucketName = is_string($bucketName) ? trim($bucketName) : '';
    if ($bucketName === '') {
        (new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200))->send();
        exit();
    }

    // check bucket belongs to the logged in user
    $bucket = DBController::getRow('s3_buckets', [
        ['name', '=', $bucketName]
    ]);

    if (is_null($bucket)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Bucket not found.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    if ($bucket->user_id != $user->id) {
        // get the tenants of the users
        $tenants = DBController::getTenants($user->id, 'id');
        if ($tenants->isEmpty()) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Bucket not found.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
        $tenantIds = $tenants->pluck('id')->toArray();

        if (!in_array($bucket->user_id, $tenantIds)) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Bucket not found.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
    }

    // Enforce 2FA delete protection if enabled for this bucket
    // Note: if the column/setting doesn't exist, this will evaluate as false
    if (!empty($bucket->two_factor_delete_enabled)) {
        (new JsonResponse([
            'status' => 'fail',
            'message' => 'Two-factor delete protection is enabled for this bucket. Please disable it before requesting deletion.'
        ], 200))->send();
        exit();
    }

    // Get configuration settings
    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        (new JsonResponse([
            'status' => 'fail',
            'message' => 'Cloud Storage service error. Please contact technical support for assistance.'
        ], 200))->send();
        exit();
    }

    $endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
    $adminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
    $adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

    // IMPORTANT (multi-tenant RGW):
    // AdminOps credentials can query metadata, but often cannot access tenant buckets over S3.
    // For accurate Object Lock scheduling decisions, use the bucket owner's S3 keypair
    // (stored in s3_user_access_keys) or fall back to a short-lived owner key via AdminOps.
    $bucketController = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $s3Region);

    $ownerUserId = (int) $bucket->user_id;
    $usedTempKey = false;
    $tempAccessKey = '';
    $tempSecretKey = '';
    $tempKeyUid = '';

    // Try stored keys first, but validate with headBucket (DB can contain stale keys).
    $accessKeyPlain = '';
    $secretKeyPlain = '';
    $connectionResult = null;
    try {
        if (!empty($encryptionKey) && Capsule::schema()->hasTable('s3_user_access_keys')) {
            $k = Capsule::table('s3_user_access_keys')->where('user_id', $ownerUserId)->first();
            if ($k && !empty($k->access_key) && !empty($k->secret_key)) {
                $accessKeyPlain = (string) HelperController::decryptKey($k->access_key, (string) $encryptionKey);
                $secretKeyPlain = (string) HelperController::decryptKey($k->secret_key, (string) $encryptionKey);
                if ($accessKeyPlain !== '' && $secretKeyPlain !== '') {
                    $tmpConn = $bucketController->connectS3ClientWithCredentials($accessKeyPlain, $secretKeyPlain);
                    if (($tmpConn['status'] ?? 'fail') === 'success' && !empty($tmpConn['s3client'])) {
                        try {
                            $tmpConn['s3client']->headBucket(['Bucket' => $bucketName]);
                            $connectionResult = $tmpConn;
                        } catch (\Throwable $e) {
                            $accessKeyPlain = '';
                            $secretKeyPlain = '';
                        }
                    } else {
                        $accessKeyPlain = '';
                        $secretKeyPlain = '';
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        $accessKeyPlain = '';
        $secretKeyPlain = '';
        $connectionResult = null;
    }

    // Fallback: temp owner key via AdminOps
    if ($connectionResult === null) {
        try {
            $ownerRow = Capsule::table('s3_users')->where('id', $ownerUserId)->first();
            $tempKeyUid = DeprovisionHelper::computeCephUid($ownerRow);
            if ($tempKeyUid !== '' && !empty($adminAccessKey) && !empty($adminSecretKey)) {
                $tmp = AdminOps::createTempKey($endpoint, $adminAccessKey, $adminSecretKey, (string)$tempKeyUid, null);
                if (is_array($tmp) && ($tmp['status'] ?? '') === 'success') {
                    $tempAccessKey = (string)($tmp['access_key'] ?? '');
                    $tempSecretKey = (string)($tmp['secret_key'] ?? '');
                    if ($tempAccessKey !== '' && $tempSecretKey !== '') {
                        $usedTempKey = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            $usedTempKey = false;
            $tempAccessKey = '';
            $tempSecretKey = '';
            $tempKeyUid = '';
        }
        $accessKeyPlain = $tempAccessKey;
        $secretKeyPlain = $tempSecretKey;
    }

    if ($connectionResult === null && ($accessKeyPlain === '' || $secretKeyPlain === '')) {
        (new JsonResponse([
            'status' => 'fail',
            'message' => 'Unable to connect to storage for deletion request. Please try again later.'
        ], 200))->send();
        exit();
    }

    if ($connectionResult === null) {
        $connectionResult = $bucketController->connectS3ClientWithCredentials($accessKeyPlain, $secretKeyPlain);
        if (($connectionResult['status'] ?? 'fail') !== 'success') {
            if ($usedTempKey && $tempAccessKey !== '' && $tempKeyUid !== '') {
                try { AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyUid, null); } catch (\Throwable $ignored) {}
            }
            (new JsonResponse([
                'status' => 'fail',
                'message' => 'Unable to connect to storage for deletion request. Please try again later.'
            ], 200))->send();
            exit();
        }
    }

    // Inspect Object Lock/emptiness state for scheduling
    $statusRes = $bucketController->getObjectLockEmptyStatus($bucketName);
    // Best-effort cleanup if we created a temp key
    if ($usedTempKey && $tempAccessKey !== '' && $tempKeyUid !== '') {
        try { AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyUid, null); } catch (\Throwable $ignored) {}
    }
    if (($statusRes['status'] ?? 'fail') !== 'success' || empty($statusRes['data'])) {
        (new JsonResponse([
            'status' => 'fail',
            'message' => $statusRes['message'] ?? 'Unable to check Object Lock status at this time. Please try again later.'
        ], 200))->send();
        exit();
    }

    $d = $statusRes['data'];
    $counts = is_array($d['counts'] ?? null) ? $d['counts'] : [];
    $ol = is_array($d['object_lock'] ?? null) ? $d['object_lock'] : [];
    $objectLockEnabled = !empty($bucket->object_lock_enabled) || !empty($ol['enabled']);
    $legalHolds = (int) ($counts['legal_holds'] ?? 0);
    $complianceRetained = (int) ($counts['compliance_retained'] ?? 0);
    $governanceRetained = (int) ($counts['governance_retained'] ?? 0);
    $earliestTs = isset($d['earliest_retain_until_ts']) ? (int) $d['earliest_retain_until_ts'] : 0;
    $earliestHuman = isset($d['earliest_retain_until']) ? (string) $d['earliest_retain_until'] : '';

    $now = time();
    $jobStatus = 'queued';
    $blockedReason = null;
    $retryAfter = null; // Y-m-d H:i:s (UTC)
    $message = 'Deletion queued. Bucket removal will proceed in the background.';

    if ($objectLockEnabled) {
        if ($legalHolds > 0) {
            $jobStatus = 'blocked';
            $blockedReason = 'legal_hold';
            $retryAfter = gmdate('Y-m-d H:i:s', $now + 86400); // re-check daily
            $message = 'Pending deletion — blocked indefinitely by Legal Hold. Remove legal holds to proceed. We will re-check periodically.';
        } elseif ($complianceRetained > 0) {
            $jobStatus = 'blocked';
            $blockedReason = 'compliance_retention';
            $retryAfter = $earliestTs > 0 ? gmdate('Y-m-d H:i:s', $earliestTs) : gmdate('Y-m-d H:i:s', $now + 86400);
            $when = $earliestTs > 0 ? gmdate('Y-m-d', $earliestTs) : 'a future date';
            $message = 'Pending deletion — Compliance retention. Earliest possible deletion: ' . $when . '.';
        } elseif ($governanceRetained > 0) {
            $jobStatus = 'blocked';
            $blockedReason = 'governance_retention';
            $retryAfter = $earliestTs > 0 ? gmdate('Y-m-d H:i:s', $earliestTs) : gmdate('Y-m-d H:i:s', $now + 21600);
            $when = $earliestTs > 0 ? gmdate('Y-m-d', $earliestTs) : 'a future date';
            $message = 'Pending deletion — Governance retention. Earliest possible deletion: ' . $when . '.';
        } else {
            $jobStatus = 'queued';
            $blockedReason = null;
            $retryAfter = null;
            $message = 'Deletion queued. Bucket removal will proceed in the background.';
        }
    }

    // Insert or update a deletion job (dedupe)
    $hasStatusColumn = false;
    $hasForceBypassGovernance = false;
    $hasRetryAfter = false;
    $hasBlockedReason = false;
    $hasEarliestTs = false;
    $hasRequestedAction = false;
    $hasRequestedByClient = false;
    $hasRequestedByUser = false;
    $hasRequestedByContact = false;
    $hasRequestedAt = false;
    $hasRequestIp = false;
    $hasRequestUa = false;
    $hasAuditJson = false;
    $hasCompletedAt = false;
    try { $hasStatusColumn = Capsule::schema()->hasColumn('s3_delete_buckets', 'status'); } catch (\Throwable $e) {}
    try { $hasForceBypassGovernance = Capsule::schema()->hasColumn('s3_delete_buckets', 'force_bypass_governance'); } catch (\Throwable $e) {}
    try { $hasRetryAfter = Capsule::schema()->hasColumn('s3_delete_buckets', 'retry_after'); } catch (\Throwable $e) {}
    try { $hasBlockedReason = Capsule::schema()->hasColumn('s3_delete_buckets', 'blocked_reason'); } catch (\Throwable $e) {}
    try { $hasEarliestTs = Capsule::schema()->hasColumn('s3_delete_buckets', 'earliest_retain_until_ts'); } catch (\Throwable $e) {}
    try { $hasRequestedAction = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_action'); } catch (\Throwable $e) {}
    try { $hasRequestedByClient = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_by_client_id'); } catch (\Throwable $e) {}
    try { $hasRequestedByUser = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_by_user_id'); } catch (\Throwable $e) {}
    try { $hasRequestedByContact = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_by_contact_id'); } catch (\Throwable $e) {}
    try { $hasRequestedAt = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_at'); } catch (\Throwable $e) {}
    try { $hasRequestIp = Capsule::schema()->hasColumn('s3_delete_buckets', 'request_ip'); } catch (\Throwable $e) {}
    try { $hasRequestUa = Capsule::schema()->hasColumn('s3_delete_buckets', 'request_ua'); } catch (\Throwable $e) {}
    try { $hasAuditJson = Capsule::schema()->hasColumn('s3_delete_buckets', 'audit_json'); } catch (\Throwable $e) {}
    try { $hasCompletedAt = Capsule::schema()->hasColumn('s3_delete_buckets', 'completed_at'); } catch (\Throwable $e) {}

    $contactId = 0;
    if (isset($_SESSION['contactid']) && (int)$_SESSION['contactid'] > 0) {
        $contactId = (int) $_SESSION['contactid'];
    } elseif (isset($_SESSION['cid']) && (int)$_SESSION['cid'] > 0) {
        $contactId = (int) $_SESSION['cid'];
    }

    $audit = [
        'source' => 'client_deletebucket',
        'object_lock_enabled' => $objectLockEnabled ? 1 : 0,
        'blocked_reason' => $blockedReason,
        'counts' => [
            'legal_holds' => $legalHolds,
            'compliance_retained' => $complianceRetained,
            'governance_retained' => $governanceRetained,
        ],
        'earliest_retain_until_ts' => $earliestTs > 0 ? $earliestTs : null,
    ];

    $existingJob = null;
    try {
        $q = Capsule::table('s3_delete_buckets')
            ->where('user_id', (int) $bucket->user_id)
            ->where('bucket_name', $bucketName);
        if ($hasStatusColumn) {
            $q->whereIn('status', ['queued', 'running', 'blocked']);
        }
        $existingJob = $q->orderBy('id', 'desc')->first();
    } catch (\Throwable $e) {
        $existingJob = null;
    }

    $jobRow = [
        'user_id' => (int) $bucket->user_id,
        'bucket_name' => $bucketName,
    ];
    if ($hasRequestedAction) { $jobRow['requested_action'] = 'delete'; }
    if ($hasRequestedByClient) { $jobRow['requested_by_client_id'] = (int) $clientId; }
    if ($hasRequestedByUser) { $jobRow['requested_by_user_id'] = (int) $loggedInUserId; }
    if ($hasRequestedByContact) { $jobRow['requested_by_contact_id'] = $contactId > 0 ? (int) $contactId : null; }
    if ($hasRequestedAt) { $jobRow['requested_at'] = gmdate('Y-m-d H:i:s'); }
    if ($hasRequestIp) { $jobRow['request_ip'] = $_SERVER['REMOTE_ADDR'] ?? null; }
    if ($hasRequestUa) { $jobRow['request_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? null; }
    if ($hasRetryAfter) { $jobRow['retry_after'] = $retryAfter; }
    if ($hasBlockedReason) { $jobRow['blocked_reason'] = $blockedReason; }
    if ($hasEarliestTs) { $jobRow['earliest_retain_until_ts'] = $earliestTs > 0 ? $earliestTs : null; }
    if ($hasAuditJson) { $jobRow['audit_json'] = json_encode($audit); }
    if ($hasForceBypassGovernance) { $jobRow['force_bypass_governance'] = 0; }
    if ($hasStatusColumn) { $jobRow['status'] = $jobStatus; }

    // Use 'error' column to surface blocked reason in admin visibility (optional)
    try {
        if (Capsule::schema()->hasColumn('s3_delete_buckets', 'error')) {
            $jobRow['error'] = ($jobStatus === 'blocked') ? $message : null;
        }
    } catch (\Throwable $e) {}
    if ($hasCompletedAt) {
        $jobRow['completed_at'] = null;
    }

    try {
        if ($existingJob && isset($existingJob->id)) {
            // If already running, don't override status; just return current state
            if ($hasStatusColumn && ($existingJob->status ?? '') === 'running') {
                (new JsonResponse([
                    'status' => 'success',
                    'message' => 'Deletion is already in progress for this bucket.',
                    'delete_job' => [
                        'id' => (int) $existingJob->id,
                        'status' => (string) $existingJob->status,
                        'blocked_reason' => $blockedReason,
                        'retry_after' => $retryAfter,
                        'earliest_retain_until_ts' => $earliestTs > 0 ? $earliestTs : null,
                        'earliest_retain_until' => $earliestHuman ?: null,
                    ],
                ], 200))->send();
                exit();
            }
            Capsule::table('s3_delete_buckets')->where('id', (int) $existingJob->id)->update($jobRow);
            $jobId = (int) $existingJob->id;
        } else {
            $insert = $jobRow;
            // Legacy schema support
            if (!isset($insert['attempt_count'])) {
                $insert['attempt_count'] = 0;
            }
            if (!isset($insert['created_at'])) {
                $insert['created_at'] = gmdate('Y-m-d H:i:s');
            }
            $jobId = (int) Capsule::table('s3_delete_buckets')->insertGetId($insert);
        }

        (new JsonResponse([
            'status' => 'success',
            'message' => $message,
            'delete_job' => [
                'id' => $jobId,
                'status' => $jobStatus,
                'blocked_reason' => $blockedReason,
                'retry_after' => $retryAfter,
                'earliest_retain_until_ts' => $earliestTs > 0 ? $earliestTs : null,
                'earliest_retain_until' => $earliestHuman ?: null,
                'object_lock_enabled' => $objectLockEnabled ? 1 : 0,
            ],
        ], 200))->send();
        exit();
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'deletebucket_queue', ['bucket' => $bucketName, 'user_id' => (int)$bucket->user_id], $e->getMessage());
        (new JsonResponse([
            'status' => 'fail',
            'message' => 'Unable to queue deletion at this time. Please try again later.'
        ], 200))->send();
        exit();
    }