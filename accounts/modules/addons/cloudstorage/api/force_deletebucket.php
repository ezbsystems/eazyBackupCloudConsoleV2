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
use WHMCS\User\User;
use WHMCS\TwoFactorAuthentication;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit();
}

// Resolve client ID (WHMCS v8 user->client mapping)
$loggedInUserId = (int) $ca->getUserID(); // WHMCS user id
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

// Require recent password verification (defense-in-depth)
$verifiedAt = isset($_SESSION['cloudstorage_pw_verified_at']) ? (int) $_SESSION['cloudstorage_pw_verified_at'] : 0;
$freshWindow = 15 * 60; // 15 minutes
if ($verifiedAt <= 0 || (time() - $verifiedAt) > $freshWindow) {
    (new JsonResponse([
        'status' => 'fail',
        'message' => 'Please verify your account password to delete this bucket.'
    ], 200))->send();
    exit();
}

// Two-Factor re-check (WHMCS native)
// - If user has 2FA enabled, require a valid token or backup code
try {
    $authUser = User::find($loggedInUserId);
    if ($authUser) {
        $tfa = new TwoFactorAuthentication();
        $tfa->setUser($authUser);
        $tfaEnabled = false;
        try {
            $tfaEnabled = (bool) $tfa->isEnabled();
        } catch (\Throwable $__) {
            $tfaEnabled = false;
        }

        if ($tfaEnabled) {
            $twofaKey = $_POST['twofa_key'] ?? ($_POST['key'] ?? '');
            $twofaBackup = $_POST['twofa_backup_code'] ?? '';
            $twofaKey = is_string($twofaKey) ? trim($twofaKey) : '';
            $twofaBackup = is_string($twofaBackup) ? trim($twofaBackup) : '';

            $twofaOk = false;
            if ($twofaBackup !== '') {
                try {
                    $twofaOk = (bool) $tfa->verifyBackupCode($twofaBackup);
                } catch (\Throwable $__) {
                    $twofaOk = false;
                }
            } else {
                if ($twofaKey === '') {
                    (new JsonResponse([
                        'status' => 'fail',
                        'message' => 'Two-factor authentication is enabled on your account. Please enter your 2FA code to continue.'
                    ], 200))->send();
                    exit();
                }
                // The 2FA module challenge expects request fields; for TOTP the input is commonly named "key".
                $_POST['key'] = $twofaKey;
                $_REQUEST['key'] = $twofaKey;
                try {
                    $twofaOk = (bool) $tfa->validateChallenge();
                } catch (\Throwable $__) {
                    $twofaOk = false;
                }
            }

            if (!$twofaOk) {
                (new JsonResponse([
                    'status' => 'fail',
                    'message' => 'Two-factor verification failed. Please try again.'
                ], 200))->send();
                exit();
            }
        }
    }
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'force_deletebucket_twofa_exception', ['user_id' => $loggedInUserId], $e->getMessage());
    (new JsonResponse([
        'status' => 'fail',
        'message' => 'Unable to verify two-factor authentication at this time. Please try again later.'
    ], 200))->send();
    exit();
}

$bucketName = $_POST['bucket_name'] ?? '';
$bucketName = is_string($bucketName) ? trim($bucketName) : '';
if ($bucketName === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200))->send();
    exit();
}

    // Typed confirmation (single phrase used across delete flows)
    $typed = $_POST['confirm_text'] ?? '';
    $typed = is_string($typed) ? trim($typed) : '';
    $requiredPhrase = 'DELETE BUCKET ' . $bucketName;
    if ($typed !== $requiredPhrase) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Confirmation text does not match. Paste: "' . $requiredPhrase . '"'], 200))->send();
        exit();
    }

// Validate client owns the product
$packageId = ProductConfig::$E3_PRODUCT_ID;
$product = DBController::getProduct($clientId, $packageId);
if (is_null($product) || is_null($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User does not exist.'], 200))->send();
    exit();
}

$username = $product->username;
$user = DBController::getUser($username);
if (is_null($user)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User does not exist.'], 200))->send();
    exit();
}

// Validate bucket ownership (including tenants)
$bucket = DBController::getRow('s3_buckets', [
    ['name', '=', $bucketName]
]);
if (is_null($bucket)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
    exit();
}
if ((int)$bucket->user_id !== (int)$user->id) {
    $tenants = DBController::getTenants($user->id, 'id');
    if ($tenants->isEmpty()) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
        exit();
    }
    $tenantIds = $tenants->pluck('id')->toArray();
    if (!in_array((int)$bucket->user_id, array_map('intval', $tenantIds), true)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
        exit();
    }
}

// Enforce per-bucket 2FA delete protection flag (if used in schema)
if (!empty($bucket->two_factor_delete_enabled)) {
    (new JsonResponse([
        'status' => 'fail',
        'message' => 'Two-factor delete protection is enabled for this bucket. Please disable it before requesting force delete.'
    ], 200))->send();
    exit();
}

// Load module settings
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);
if (count($module) == 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Cloud Storage service error. Please contact support.'], 200))->send();
    exit();
}

$endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$adminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

$emailTemplateId = $module->where('setting', 'object_lock_force_delete_email_template')->pluck('value')->first();
$internalEmail = trim((string) ($module->where('setting', 'object_lock_force_delete_internal_email')->pluck('value')->first() ?? ''));

// Object Lock status:
// IMPORTANT (multi-tenant RGW): admin keys may not have data-plane access to tenant buckets.
// Prefer bucket owner's stored keys, or create a short-lived owner key via AdminOps (fallback).
$bucketController = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $s3Region);

$ownerUserId = (int) $bucket->user_id;
$usedTempKey = false;
$tempAccessKey = '';
$tempSecretKey = '';
$tempKeyUid = '';

// Try stored keys first, but validate with headBucket (DB can contain stale keys).
$accessKeyPlain = '';
$secretKeyPlain = '';
$conn = null;
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
                        $conn = $tmpConn;
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
    $conn = null;
}

// Fallback: temp owner key via AdminOps
if ($conn === null) {
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

if ($conn === null && ($accessKeyPlain === '' || $secretKeyPlain === '')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to connect to storage. Please try again later.'], 200))->send();
    exit();
}

if ($conn === null) {
    $conn = $bucketController->connectS3ClientWithCredentials($accessKeyPlain, $secretKeyPlain);
    if (($conn['status'] ?? 'fail') !== 'success') {
        if ($usedTempKey && $tempAccessKey !== '' && $tempKeyUid !== '') {
            try { AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyUid, null); } catch (\Throwable $ignored) {}
        }
        (new JsonResponse(['status' => 'fail', 'message' => 'Unable to connect to storage. Please try again later.'], 200))->send();
        exit();
    }
}

$statusRes = $bucketController->getObjectLockEmptyStatus($bucketName);
// Best-effort cleanup if we created a temp key
if ($usedTempKey && $tempAccessKey !== '' && $tempKeyUid !== '') {
    try { AdminOps::removeKey($endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyUid, null); } catch (\Throwable $ignored) {}
}
if (($statusRes['status'] ?? 'fail') !== 'success' || empty($statusRes['data'])) {
    (new JsonResponse(['status' => 'fail', 'message' => $statusRes['message'] ?? 'Unable to check bucket status.'], 200))->send();
    exit();
}

$d = $statusRes['data'];
$counts = is_array($d['counts'] ?? null) ? $d['counts'] : [];
$ol = is_array($d['object_lock'] ?? null) ? $d['object_lock'] : [];
$mode = strtoupper((string) ($ol['default_mode'] ?? ''));
$objectLockEnabled = !empty($bucket->object_lock_enabled) || !empty($ol['enabled']);

$legalHolds = (int) ($counts['legal_holds'] ?? 0);
$complianceRetained = (int) ($counts['compliance_retained'] ?? 0);
$governanceRetained = (int) ($counts['governance_retained'] ?? 0);

if (!$objectLockEnabled) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Force delete is only available for Object Lock buckets.'], 200))->send();
    exit();
}
if ($legalHolds > 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Deletion blocked: bucket contains versions under Legal Hold. Remove legal holds and try again.'], 200))->send();
    exit();
}
if ($complianceRetained > 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Deletion blocked: bucket contains object versions under Compliance retention and cannot be force deleted.'], 200))->send();
    exit();
}
// Force delete applies when Governance retention is present, or the bucket default mode is Governance.
if ($governanceRetained <= 0 && $mode !== 'GOVERNANCE') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Governance bypass delete is only available for Governance mode buckets.'], 200))->send();
    exit();
}

// Queue/upgrade a deletion job with Governance bypass
$hasStatusColumn = false;
$hasForceBypassGovernance = false;
$hasRequestedAction = false;
$hasRequestedByClient = false;
$hasRequestedByUser = false;
$hasRequestedByContact = false;
$hasRequestedAt = false;
$hasRequestIp = false;
$hasRequestUa = false;
$hasAuditJson = false;
$hasRetryAfter = false;
$hasBlockedReason = false;
$hasEarliestTs = false;
$hasCompletedAt = false;
try { $hasStatusColumn = Capsule::schema()->hasColumn('s3_delete_buckets', 'status'); } catch (\Throwable $e) {}
try { $hasForceBypassGovernance = Capsule::schema()->hasColumn('s3_delete_buckets', 'force_bypass_governance'); } catch (\Throwable $e) {}
try { $hasRequestedAction = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_action'); } catch (\Throwable $e) {}
try { $hasRequestedByClient = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_by_client_id'); } catch (\Throwable $e) {}
try { $hasRequestedByUser = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_by_user_id'); } catch (\Throwable $e) {}
try { $hasRequestedByContact = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_by_contact_id'); } catch (\Throwable $e) {}
try { $hasRequestedAt = Capsule::schema()->hasColumn('s3_delete_buckets', 'requested_at'); } catch (\Throwable $e) {}
try { $hasRequestIp = Capsule::schema()->hasColumn('s3_delete_buckets', 'request_ip'); } catch (\Throwable $e) {}
try { $hasRequestUa = Capsule::schema()->hasColumn('s3_delete_buckets', 'request_ua'); } catch (\Throwable $e) {}
try { $hasAuditJson = Capsule::schema()->hasColumn('s3_delete_buckets', 'audit_json'); } catch (\Throwable $e) {}
try { $hasRetryAfter = Capsule::schema()->hasColumn('s3_delete_buckets', 'retry_after'); } catch (\Throwable $e) {}
try { $hasBlockedReason = Capsule::schema()->hasColumn('s3_delete_buckets', 'blocked_reason'); } catch (\Throwable $e) {}
try { $hasEarliestTs = Capsule::schema()->hasColumn('s3_delete_buckets', 'earliest_retain_until_ts'); } catch (\Throwable $e) {}
try { $hasCompletedAt = Capsule::schema()->hasColumn('s3_delete_buckets', 'completed_at'); } catch (\Throwable $e) {}

$contactId = 0;
if (isset($_SESSION['contactid']) && (int)$_SESSION['contactid'] > 0) {
    $contactId = (int) $_SESSION['contactid'];
} elseif (isset($_SESSION['cid']) && (int)$_SESSION['cid'] > 0) {
    $contactId = (int) $_SESSION['cid'];
}

$audit = [
    'source' => 'client_governance_bypass_deletebucket',
    'confirm_phrase' => $requiredPhrase,
    'governance_retained_count' => $governanceRetained,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
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
if ($hasRequestedAction) { $jobRow['requested_action'] = 'force_delete'; }
if ($hasRequestedByClient) { $jobRow['requested_by_client_id'] = (int) $clientId; }
if ($hasRequestedByUser) { $jobRow['requested_by_user_id'] = (int) $loggedInUserId; }
if ($hasRequestedByContact) { $jobRow['requested_by_contact_id'] = $contactId > 0 ? (int) $contactId : null; }
if ($hasRequestedAt) { $jobRow['requested_at'] = gmdate('Y-m-d H:i:s'); }
if ($hasRequestIp) { $jobRow['request_ip'] = $_SERVER['REMOTE_ADDR'] ?? null; }
if ($hasRequestUa) { $jobRow['request_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? null; }
if ($hasAuditJson) { $jobRow['audit_json'] = json_encode($audit); }
if ($hasForceBypassGovernance) { $jobRow['force_bypass_governance'] = 1; }
if ($hasStatusColumn) { $jobRow['status'] = 'queued'; }
if ($hasRetryAfter) { $jobRow['retry_after'] = null; }
if ($hasBlockedReason) { $jobRow['blocked_reason'] = null; }
if ($hasEarliestTs) { $jobRow['earliest_retain_until_ts'] = null; }
if ($hasCompletedAt) { $jobRow['completed_at'] = null; }

try {
    if ($existingJob && isset($existingJob->id)) {
        if ($hasStatusColumn && ($existingJob->status ?? '') === 'running') {
            (new JsonResponse(['status' => 'fail', 'message' => 'Deletion is already in progress for this bucket.'], 200))->send();
            exit();
        }
        Capsule::table('s3_delete_buckets')->where('id', (int)$existingJob->id)->update($jobRow);
        $jobId = (int) $existingJob->id;
    } else {
        $insert = $jobRow;
        if (!isset($insert['attempt_count'])) {
            $insert['attempt_count'] = 0;
        }
        if (!isset($insert['created_at'])) {
            $insert['created_at'] = gmdate('Y-m-d H:i:s');
        }
        $jobId = (int) Capsule::table('s3_delete_buckets')->insertGetId($insert);
    }
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'force_deletebucket_queue', ['bucket' => $bucketName, 'user_id' => (int)$bucket->user_id], $e->getMessage());
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to queue force delete at this time. Please try again later.'], 200))->send();
    exit();
}

// Send customer notification email (optional but recommended)
$customerEmailOk = false;
try {
    $templates = function_exists('cloudstorage_get_email_templates') ? cloudstorage_get_email_templates() : [];
    $templateName = $templates[$emailTemplateId] ?? null;
    if (!empty($templateName)) {
        $sendEmailParams = [
            'messagename' => $templateName,
            'id'          => (int) $clientId,
            'customvars'  => base64_encode(serialize([
                'bucket_name' => $bucketName,
                'requested_at' => gmdate('Y-m-d H:i') . ' Coordinated Universal Time',
                'requested_action' => 'force_delete',
                'governance_bypass' => '1',
            ])),
        ];
        $emailResult = localAPI('SendEmail', $sendEmailParams, $adminUser);
        $customerEmailOk = (is_array($emailResult) && (($emailResult['result'] ?? '') === 'success'));
        logModuleCall('cloudstorage', 'force_deletebucket_SendEmailCustomer', $sendEmailParams, $emailResult);
    }
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'force_deletebucket_SendEmailCustomer_exception', ['bucket' => $bucketName], $e->getMessage());
}

// Internal notification (optional)
try {
    if ($internalEmail !== '') {
        $subject = 'Force delete requested (Governance bypass): ' . $bucketName;
        $body = "A customer requested a Governance bypass (force delete).\n\n"
            . "Bucket: {$bucketName}\n"
            . "Client ID: {$clientId}\n"
            . "Requested by User ID: {$loggedInUserId}\n"
            . "Job ID: {$jobId}\n"
            . "Time (UTC): " . gmdate('Y-m-d H:i') . " UTC\n"
            . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '—') . "\n"
            . "Customer email sent: " . ($customerEmailOk ? 'Yes' : 'No/Not configured') . "\n";
        $resp = localAPI('SendEmail', [
            'customtype' => 'general',
            'customsubject' => $subject,
            'custommessage' => nl2br(htmlentities($body)),
            'to' => $internalEmail,
        ]);
        logModuleCall('cloudstorage', 'force_deletebucket_SendEmailInternal', ['to' => $internalEmail, 'subject' => $subject], $resp);
    }
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'force_deletebucket_SendEmailInternal_exception', ['to' => $internalEmail], $e->getMessage());
}

(new JsonResponse([
    'status' => 'success',
    'message' => 'Force delete queued. Removal will proceed in the background.',
    'delete_job' => [
        'id' => $jobId,
        'status' => 'queued',
        'force_bypass_governance' => 1,
    ],
], 200))->send();
exit();


