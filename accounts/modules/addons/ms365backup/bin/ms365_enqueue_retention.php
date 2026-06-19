#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * One-time / admin helper: enqueue retention_apply for all MS365 Kopia repos.
 *
 * Usage: php bin/ms365_enqueue_retention.php [--dry-run]
 */

$root = dirname(__DIR__);
$init = dirname($root, 3) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once $root . '/ms365backup_autoload.php';

use Ms365Backup\Ms365CustomerJobService;
use Ms365Backup\Ms365KopiaRepoOperationService;
use Ms365Backup\TenantRecordRepository;
use WHMCS\Database\Capsule;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$enqueued = 0;
$skipped = 0;

if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
    fwrite(STDERR, "s3_cloudbackup_jobs not found\n");
    exit(1);
}

$jobs = Capsule::table('s3_cloudbackup_jobs')
    ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
    ->where('status', 'active')
    ->get(['job_id', 'schedule_json']);

foreach ($jobs as $job) {
    $hex = is_string($job->job_id ?? null) ? bin2hex($job->job_id) : '';
    if (strlen($hex) !== 32) {
        ++$skipped;
        continue;
    }
    $jobUuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    $ms365 = json_decode((string) ($job->schedule_json ?? ''), true);
    $tenantRecordId = is_array($ms365) ? (int) ($ms365['tenant_record_id'] ?? 0) : 0;
    $tenantRow = $tenantRecordId > 0 ? TenantRecordRepository::getById($tenantRecordId) : null;
    if ($tenantRow === null) {
        ++$skipped;
        continue;
    }
    if ($dryRun) {
        echo "would enqueue retention for job {$jobUuid}\n";
        ++$enqueued;
        continue;
    }
    if (Ms365KopiaRepoOperationService::scheduleRetentionForJob($jobUuid, $tenantRow)) {
        ++$enqueued;
        echo "enqueued retention for job {$jobUuid}\n";
    } else {
        ++$skipped;
    }
}

echo json_encode(['enqueued' => $enqueued, 'skipped' => $skipped, 'dry_run' => $dryRun], JSON_PRETTY_PRINT) . PHP_EOL;
