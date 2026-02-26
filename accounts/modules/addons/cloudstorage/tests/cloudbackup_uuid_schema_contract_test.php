<?php
/**
 * Schema contract tests for cloud backup job/run UUIDv7 identity.
 * Asserts s3_cloudbackup_jobs and s3_cloudbackup_runs use BINARY(16) for
 * job_id and run_id per design. Fails until schema cutover (Task 2+) is applied.
 */

$src = file_get_contents(__DIR__ . '/../cloudstorage.php');
if ($src === false) {
    throw new RuntimeException('failed to read cloudstorage.php');
}

// Scoped to cloud backup jobs table schema only
$jobsCreate = 'create(\'s3_cloudbackup_jobs\'';
$jobsBlock = strpos($src, $jobsCreate);
if ($jobsBlock === false) {
    throw new RuntimeException('s3_cloudbackup_jobs schema create block missing');
}

// Design: job_id is BINARY(16) primary key. No numeric id primary key.
$jobsSchema = substr($src, $jobsBlock, 600);
if (strpos($jobsSchema, "binary(16)") === false && strpos($jobsSchema, "BINARY(16)") === false) {
    throw new RuntimeException('s3_cloudbackup_jobs.job_id must be BINARY(16) (UUID-native)');
}
if (strpos($jobsSchema, "increments('id')") !== false || strpos($jobsSchema, "->id()") !== false) {
    throw new RuntimeException('s3_cloudbackup_jobs must not use numeric id as primary key');
}

// Scoped to cloud backup runs table schema only
$runsCreate = "create('s3_cloudbackup_runs'";
$runsBlock = strpos($src, $runsCreate);
if ($runsBlock === false) {
    throw new RuntimeException('s3_cloudbackup_runs schema create block missing');
}

$runsSchema = substr($src, $runsBlock, 2000);
if (strpos($runsSchema, "binary(16)") === false && strpos($runsSchema, "BINARY(16)") === false) {
    throw new RuntimeException('s3_cloudbackup_runs.run_id must be BINARY(16) (UUID-native)');
}
if (strpos($runsSchema, "unsignedInteger('job_id')") !== false || strpos($runsSchema, "unsignedBigInteger('job_id')") !== false) {
    throw new RuntimeException('s3_cloudbackup_runs.job_id must be BINARY(16) FK, not numeric');
}
if (strpos($runsSchema, "bigIncrements('id')") !== false || strpos($runsSchema, "increments('id')") !== false) {
    throw new RuntimeException('s3_cloudbackup_runs must not use numeric id as primary key');
}

echo "schema-contract-ok\n";
