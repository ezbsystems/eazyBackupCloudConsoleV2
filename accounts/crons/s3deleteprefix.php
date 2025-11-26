<?php

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

// Fetch queued jobs (limit to avoid long runs)
$jobs = Capsule::table('s3_delete_prefixes')->where('status', 'queued')->orderBy('id', 'asc')->limit(10)->get();
if (!count($jobs)) {
    return;
}

// Module settings
$module = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
if (count($module) == 0) {
    logModuleCall('cloudstorage', 's3deleteprefix', [], 'Module not configured');
    return;
}
$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

$bucketCtrl = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);

foreach ($jobs as $job) {
    $jobId = $job->id;
    $userId = $job->user_id;
    $bucket = $job->bucket_name;
    $prefix = $job->prefix;

    // Mark running
    Capsule::table('s3_delete_prefixes')->where('id', $jobId)->update([
        'status' => 'running',
        'started_at' => \Carbon\Carbon::now()
    ]);

    try {
        $conn = $bucketCtrl->connectS3Client($userId, $encryptionKey);
        if (($conn['status'] ?? 'fail') !== 'success') {
            throw new \Exception('S3 connection failed');
        }
        $s3 = $conn['s3client'];

        $deletedCount = 0;
        $listParams = [
            'Bucket' => $bucket,
            'Prefix' => $prefix,
            'MaxKeys' => 1000,
        ];
        do {
            $res = $s3->listObjectsV2($listParams);
            $toDelete = [];
            if (!empty($res['Contents'])) {
                foreach ($res['Contents'] as $obj) {
                    $toDelete[] = ['Key' => $obj['Key']];
                }
            }
            if (count($toDelete)) {
                $s3->deleteObjects([
                    'Bucket' => $bucket,
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

        Capsule::table('s3_delete_prefixes')->where('id', $jobId)->update([
            'status' => 'success',
            'completed_at' => \Carbon\Carbon::now(),
            'metrics' => json_encode(['deleted' => $deletedCount]),
        ]);
    } catch (\Throwable $e) {
        Capsule::table('s3_delete_prefixes')->where('id', $jobId)->update([
            'status' => 'failed',
            'completed_at' => \Carbon\Carbon::now(),
            'error' => substr($e->getMessage(), 0, 1000),
            'attempt_count' => $job->attempt_count + 1
        ]);
        logModuleCall('cloudstorage', 's3deleteprefix', ['job' => $jobId], $e->getMessage());
    }
}


