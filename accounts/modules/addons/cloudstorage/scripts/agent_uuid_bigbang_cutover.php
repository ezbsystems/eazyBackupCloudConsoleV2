<?php

declare(strict_types=1);

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

function setCloudStorageSetting(string $key, string $value): void
{
    $exists = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', $key)
        ->exists();

    if ($exists) {
        Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $key)
            ->update(['value' => $value]);
        return;
    }

    Capsule::table('tbladdonmodules')->insert([
        'module' => 'cloudstorage',
        'setting' => $key,
        'value' => $value,
    ]);
}

function dropCloudBackupTables(): void
{
    $schema = Capsule::schema();

    $dropTables = [
        's3_cloudbackup_agent_destinations',
        's3_cloudbackup_run_commands',
        's3_cloudbackup_run_logs',
        's3_cloudbackup_run_events',
        's3_cloudbackup_restore_points',
        's3_cloudbackup_runs',
        's3_cloudbackup_jobs',
        's3_cloudbackup_repositories',
        's3_cloudbackup_repository_keys',
        's3_cloudbackup_settings',
        's3_cloudbackup_sources',
        's3_cloudbackup_recovery_tokens',
        's3_cloudbackup_recovery_exchange_limits',
        's3_hyperv_backup_points',
        's3_hyperv_checkpoints',
        's3_hyperv_vms',
        's3_cloudbackup_agents',
    ];

    Capsule::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($dropTables as $table) {
        if ($schema->hasTable($table)) {
            $schema->drop($table);
        }
    }
    Capsule::statement('SET FOREIGN_KEY_CHECKS=1');
}

function recreateSchemaViaModuleActivate(): void
{
    require_once __DIR__ . '/../cloudstorage.php';
    if (!function_exists('cloudstorage_activate')) {
        throw new RuntimeException('cloudstorage_activate() not available');
    }
    $result = cloudstorage_activate();
    if (!is_array($result) || ($result['status'] ?? 'fail') !== 'success') {
        throw new RuntimeException('cloudstorage_activate() failed during cutover rebuild');
    }
}

function assertColumnsExist(string $table, array $columns): void
{
    $schema = Capsule::schema();
    if (!$schema->hasTable($table)) {
        throw new RuntimeException("missing table {$table} after cutover rebuild");
    }
    foreach ($columns as $column) {
        if (!$schema->hasColumn($table, $column)) {
            throw new RuntimeException("missing required column {$table}.{$column} after cutover rebuild");
        }
    }
}

function assertUuidOnlyContractsForCurrentApis(): void
{
    assertColumnsExist('s3_cloudbackup_jobs', ['agent_uuid']);
    assertColumnsExist('s3_cloudbackup_runs', ['agent_uuid']);
    assertColumnsExist('s3_cloudbackup_run_commands', ['agent_uuid']);
    assertColumnsExist('s3_cloudbackup_restore_points', ['agent_uuid']);
    assertColumnsExist('s3_cloudbackup_agent_destinations', ['agent_uuid']);

    // Ensure critical write-path contracts are present after rebuild.
    assertColumnsExist('s3_cloudbackup_jobs', [
        'schedule_type', 'schedule_time', 'schedule_weekday', 'schedule_cron', 'schedule_json',
        'retention_mode', 'retention_value', 'retention_json', 'policy_json',
        'encryption_mode', 'compression', 'engine', 'dest_type',
    ]);
    assertColumnsExist('s3_cloudbackup_runs', [
        'status', 'progress_pct', 'bytes_transferred', 'bytes_processed', 'bytes_total',
        'objects_transferred', 'objects_total', 'speed_bytes_per_sec', 'eta_seconds',
        'current_item', 'log_excerpt', 'error_summary', 'validation_status',
        'validation_log_excerpt', 'run_uuid',
    ]);
}

function generateUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function runUuidEnrollmentDestinationSmoke(): void
{
    require_once __DIR__ . '/../lib/Client/CloudBackupBootstrapService.php';

    $schema = Capsule::schema();
    if (!$schema->hasColumn('s3_cloudbackup_agents', 'agent_uuid')) {
        throw new RuntimeException('smoke check failed: agents.agent_uuid missing');
    }
    if (!$schema->hasColumn('s3_cloudbackup_agent_destinations', 'agent_uuid')) {
        throw new RuntimeException('smoke check failed: destinations.agent_uuid missing');
    }

    $agentUuid = generateUuid();
    $agentToken = bin2hex(random_bytes(20));
    $destInserted = false;
    $agentId = 0;

    try {
        $agentId = (int) Capsule::table('s3_cloudbackup_agents')->insertGetId([
            'agent_uuid' => $agentUuid,
            'client_id' => 1,
            'tenant_id' => null,
            'tenant_user_id' => null,
            'agent_token' => $agentToken,
            'hostname' => 'cutover-smoke-host',
            'device_id' => 'cutover-smoke-device',
            'status' => 'active',
            'agent_type' => 'workstation',
            'created_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ]);

        Capsule::statement('SET FOREIGN_KEY_CHECKS=0');
        $payload = [
            'agent_uuid' => $agentUuid,
            'client_id' => 1,
            'tenant_id' => null,
            's3_user_id' => 0,
            'dest_bucket_id' => 0,
            'root_prefix' => 'cutover-smoke-prefix',
            'is_locked' => 1,
            'created_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ];
        Capsule::table('s3_cloudbackup_agent_destinations')->insert($payload);
        $destInserted = true;
        Capsule::statement('SET FOREIGN_KEY_CHECKS=1');

        $res = \WHMCS\Module\Addon\CloudStorage\Client\CloudBackupBootstrapService::ensureAgentDestination($agentUuid);
        if (($res['status'] ?? 'fail') !== 'success') {
            throw new RuntimeException('smoke check failed: ensureAgentDestination did not succeed for UUID path');
        }
        $dest = $res['destination'] ?? null;
        if (!$dest || (string) ($dest->agent_uuid ?? '') !== $agentUuid) {
            throw new RuntimeException('smoke check failed: destination not resolved by agent_uuid');
        }
    } finally {
        try {
            Capsule::statement('SET FOREIGN_KEY_CHECKS=0');
            if ($destInserted) {
                Capsule::table('s3_cloudbackup_agent_destinations')->where('agent_uuid', $agentUuid)->delete();
            }
            if ($agentId > 0) {
                Capsule::table('s3_cloudbackup_agents')->where('id', $agentId)->delete();
            }
            Capsule::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable $cleanupError) {
            // Ignore cleanup failures; main result already emitted.
        }
    }
}

setCloudStorageSetting('agent_uuid_cutover_maintenance_mode', '1');
dropCloudBackupTables();
recreateSchemaViaModuleActivate();
assertUuidOnlyContractsForCurrentApis();
runUuidEnrollmentDestinationSmoke();
echo "agent-uuid-bigbang-cutover-ok\n";
