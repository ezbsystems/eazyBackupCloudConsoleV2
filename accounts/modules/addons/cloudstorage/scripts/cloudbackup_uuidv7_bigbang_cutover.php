<?php

declare(strict_types=1);

/**
 * UUIDv7 big-bang cutover: drops cloud backup jobs/runs and dependent tables,
 * then recreates via cloudstorage_activate() which performs
 * create('s3_cloudbackup_jobs') and create('s3_cloudbackup_runs') with BINARY(16) keys.
 *
 * Run from CLI only. Destroys all cloud backup job/run data.
 */

use WHMCS\Database\Capsule;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run from CLI.\n");
    exit(1);
}

$defaultInit = '/var/www/eazybackup.ca/accounts/init.php';
$initPath = getenv('WHMCS_INIT_PATH') ?: $defaultInit;
if (!is_file($initPath)) {
    fwrite(STDERR, "WHMCS init.php not found at: {$initPath}\n");
    fwrite(STDERR, "Set WHMCS_INIT_PATH to the correct init.php before running.\n");
    exit(1);
}

require_once $initPath;

if (!class_exists(Capsule::class)) {
    fwrite(STDERR, "WHMCS Capsule runtime not available after bootstrap.\n");
    exit(1);
}

$schema = Capsule::schema();

// Drop order: dependents first (FK to runs), then runs, then dependents of jobs, then jobs
Capsule::statement('SET FOREIGN_KEY_CHECKS=0');

if ($schema->hasTable('s3_cloudbackup_run_events')) {
    $schema->drop('s3_cloudbackup_run_events');
}
if ($schema->hasTable('s3_cloudbackup_run_logs')) {
    $schema->drop('s3_cloudbackup_run_logs');
}
if ($schema->hasTable('s3_cloudbackup_run_commands')) {
    $schema->drop('s3_cloudbackup_run_commands');
}
if ($schema->hasTable('s3_cloudbackup_recovery_tokens')) {
    $schema->drop('s3_cloudbackup_recovery_tokens');
}
if ($schema->hasTable('s3_cloudbackup_restore_points')) {
    $schema->drop('s3_cloudbackup_restore_points');
}
if ($schema->hasTable('s3_hyperv_backup_points')) {
    $schema->drop('s3_hyperv_backup_points');
}
if ($schema->hasTable('s3_hyperv_checkpoints')) {
    $schema->drop('s3_hyperv_checkpoints');
}

$schema->dropIfExists('s3_cloudbackup_runs');

if ($schema->hasTable('s3_hyperv_vms')) {
    $schema->drop('s3_hyperv_vms');
}

$schema->dropIfExists('s3_cloudbackup_jobs');

Capsule::statement('SET FOREIGN_KEY_CHECKS=1');

// Recreate via cloudstorage_activate() which performs create('s3_cloudbackup_jobs')
// and create('s3_cloudbackup_runs') with BINARY(16) UUID-native keys

// Recreate all cloud backup tables via module activate
require_once __DIR__ . '/../cloudstorage.php';
if (!function_exists('cloudstorage_activate')) {
    throw new RuntimeException('cloudstorage_activate() not available');
}
$result = cloudstorage_activate();
if (!is_array($result) || ($result['status'] ?? 'fail') !== 'success') {
    throw new RuntimeException('cloudstorage_activate() failed during cutover rebuild');
}

echo "cloudbackup-uuidv7-bigbang-cutover-ok\n";
