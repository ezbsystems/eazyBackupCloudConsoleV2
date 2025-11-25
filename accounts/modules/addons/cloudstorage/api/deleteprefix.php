<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

// Expected POST: bucket, username, prefix
if (empty($_POST['bucket']) || empty($_POST['username']) || !isset($_POST['prefix'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid request.'], 200))->send();
    exit;
}

$bucketName = trim($_POST['bucket']);
$browseUser = trim($_POST['username']);
$prefix = trim($_POST['prefix']);
$prefix = ltrim($prefix, '/'); // normalize

if ($prefix === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid prefix.'], 200))->send();
    exit;
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User not exist.'], 200))->send();
    exit;
}

$username = $product->username;
$user = DBController::getUser($username);
if (is_null($user)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Your account has been suspended. Please contact support.'], 200))->send();
    exit;
}
$userId = $user->id;
if ($username !== $browseUser) {
    $tenant = DBController::getRow('s3_users', [
        ['username', '=', $browseUser],
        ['parent_id', '=', $userId],
    ]);
    if (is_null($tenant)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Invalid browse user.'], 200))->send();
        exit;
    }
    $username = $browseUser;
    $userId = $tenant->id;
}

// Verify bucket belongs to user
$bucket = DBController::getRow('s3_buckets', [
    ['name', '=', $bucketName],
    ['user_id', '=', $userId],
    ['is_active', '=', '1']
]);
if (is_null($bucket)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
    exit;
}

// Load module settings (endpoint, credentials, region, encryption key)
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);
if (count($module) == 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Cloudstorage module is not configured.'], 200))->send();
    exit;
}

$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

$bucketCtrl = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);

try {
    // Establish tenant-scoped S3 client
    $conn = $bucketCtrl->connectS3Client($userId, $encryptionKey);
    if (($conn['status'] ?? 'fail') !== 'success') {
        (new JsonResponse(['status' => 'fail', 'message' => $conn['message'] ?? 'Storage connection failed.'], 200))->send();
        exit;
    }
    $s3 = $conn['s3client'];

    $deletedCount = 0;
    $startTime = microtime(true);
    $maxSeconds = 10; // time budget for inline delete
    $timedOut = false;

    $listParams = [
        'Bucket' => $bucketName,
        'Prefix' => $prefix,
        'MaxKeys' => 1000,
    ];

    do {
        // Check time budget before each page
        if ((microtime(true) - $startTime) > $maxSeconds) {
            $timedOut = true;
            break;
        }

        $res = $s3->listObjectsV2($listParams);
        $toDelete = [];
        if (!empty($res['Contents'])) {
            foreach ($res['Contents'] as $obj) {
                if (!isset($obj['Key'])) {
                    continue;
                }
                $toDelete[] = ['Key' => $obj['Key']];
            }
        }

        if (count($toDelete)) {
            $s3->deleteObjects([
                'Bucket' => $bucketName,
                'Delete' => ['Objects' => $toDelete, 'Quiet' => true]
            ]);
            $deletedCount += count($toDelete);
        }

        if ($res['IsTruncated'] ?? false) {
            $listParams['ContinuationToken'] = $res['NextContinuationToken'];
        } else {
            break;
        }
    } while (true);

    $hasMore = ($res['IsTruncated'] ?? false) === true;

    if ($timedOut && $hasMore) {
        // Ensure queue table exists (in case upgrade not run yet)
        if (!Capsule::schema()->hasTable('s3_delete_prefixes')) {
            Capsule::schema()->create('s3_delete_prefixes', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id');
                $table->string('bucket_name', 255);
                $table->string('prefix', 1024);
                $table->enum('status', ['queued','running','success','failed'])->default('queued');
                $table->tinyInteger('attempt_count')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error')->nullable();
                $table->text('metrics')->nullable();
                $table->index(['bucket_name', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        // Queue remaining work for cron
        Capsule::table('s3_delete_prefixes')->insert([
            'user_id' => $userId,
            'bucket_name' => $bucketName,
            'prefix' => $prefix,
            'status' => 'queued',
            'attempt_count' => 0,
            'created_at' => \Carbon\Carbon::now(),
        ]);

        (new JsonResponse([
            'status' => 'success',
            'message' => 'Large delete started and will continue in background.',
        ], 200))->send();
        exit;
    }

    // Completed within request time budget (or there was nothing to delete)
    (new JsonResponse([
        'status' => 'success',
        'message' => $deletedCount > 0 ? 'Folder deleted.' : 'Nothing to delete under this folder.',
    ], 200))->send();
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'deleteprefix_inline', [
        'bucket' => $bucketName,
        'prefix' => $prefix,
        'user_id' => $userId,
    ], $e->getMessage());

    (new JsonResponse(['status' => 'fail', 'message' => 'Could not delete folder. Please try again later.'], 200))->send();
}
exit;


