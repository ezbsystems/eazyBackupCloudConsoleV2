<?php
/**
 * CLI runner: execute the white-label provisioning pipeline for a tenant.
 *
 * Usage:
 *   php whitelabel_run_pipeline.php <tenant_id>
 *
 * Designed to be spawned as a background process from the web request
 * (intake form POST or loader page GET).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(2);
}

$tenantId = (int)($argv[1] ?? 0);
if ($tenantId <= 0) {
    fwrite(STDERR, "Usage: php whitelabel_run_pipeline.php <tenant_id>\n");
    exit(2);
}

$init = '/var/www/eazybackup.ca/accounts/init.php';
if (!file_exists($init)) {
    fwrite(STDERR, "ERROR: WHMCS init.php not found at {$init}\n");
    exit(2);
}
require_once $init;

use WHMCS\Database\Capsule;

$libDir = __DIR__ . '/../lib/Whitelabel';
foreach (['Builder', 'AwsRoute53', 'HostOps', 'CometTenant', 'WhmcsOps'] as $cls) {
    $f = $libDir . '/' . $cls . '.php';
    if (is_file($f)) {
        require_once $f;
    }
}

$tenant = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
if (!$tenant) {
    fwrite(STDERR, "Tenant {$tenantId} not found.\n");
    exit(3);
}

$hasRunning = Capsule::table('eb_whitelabel_builds')
    ->where('tenant_id', $tenantId)
    ->where('status', 'running')
    ->exists();
if ($hasRunning) {
    fwrite(STDERR, "Tenant {$tenantId} already has a running step; skipping.\n");
    exit(0);
}

$hasQueued = Capsule::table('eb_whitelabel_builds')
    ->where('tenant_id', $tenantId)
    ->where('status', 'queued')
    ->exists();
if (!$hasQueued) {
    fwrite(STDERR, "Tenant {$tenantId} has no queued steps; nothing to do.\n");
    exit(0);
}

$vars = Capsule::table('tbladdonmodules')
    ->where('module', 'eazybackup')
    ->pluck('value', 'setting')
    ->toArray();

try {
    $builder = new \EazyBackup\Whitelabel\Builder($vars);
    $builder->runImmediate($tenantId);
    fwrite(STDOUT, "Pipeline completed for tenant {$tenantId}.\n");
} catch (\Throwable $e) {
    fwrite(STDERR, "Pipeline failed for tenant {$tenantId}: " . $e->getMessage() . "\n");
    try {
        logModuleCall('eazybackup', 'cli_pipeline_error', ['tenant_id' => $tenantId], $e->getMessage());
    } catch (\Throwable $_) {}
    exit(1);
}
