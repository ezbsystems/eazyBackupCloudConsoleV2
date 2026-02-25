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

function recreateUuidFirstSchema(): void
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

    $schema->create('s3_cloudbackup_agents', function ($table) {
        $table->string('agent_uuid', 36)->primary();
        $table->unsignedInteger('client_id');
        $table->unsignedInteger('tenant_id')->nullable();
        $table->unsignedInteger('tenant_user_id')->nullable();
        $table->string('agent_token', 191)->unique();
        $table->unsignedInteger('enrollment_token_id')->nullable();
        $table->string('hostname', 191)->nullable();
        $table->string('device_id', 64)->nullable();
        $table->string('install_id', 64)->nullable();
        $table->string('device_name', 191)->nullable();
        $table->string('agent_version', 64)->nullable();
        $table->string('agent_os', 32)->nullable();
        $table->string('agent_arch', 16)->nullable();
        $table->string('agent_build', 64)->nullable();
        $table->dateTime('metadata_updated_at')->nullable();
        $table->enum('status', ['active', 'disabled'])->default('active');
        $table->enum('agent_type', ['workstation', 'server', 'hypervisor'])->default('workstation');
        $table->dateTime('last_seen_at')->nullable();
        $table->text('volumes_json')->nullable();
        $table->dateTime('volumes_updated_at')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent();

        $table->index('client_id');
        $table->index('tenant_id');
        $table->index('tenant_user_id');
        $table->unique(['client_id', 'tenant_id', 'device_id'], 'uniq_agent_device_scope');
    });

    $schema->create('s3_cloudbackup_jobs', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('client_id');
        $table->unsignedInteger('tenant_id')->nullable();
        $table->string('repository_id', 64)->nullable();
        $table->string('agent_uuid', 36)->nullable();
        $table->unsignedInteger('s3_user_id');
        $table->unsignedInteger('dest_bucket_id');
        $table->string('name', 191);
        $table->string('source_type', 32)->default('s3_compatible');
        $table->string('source_display_name', 191);
        $table->mediumText('source_config_enc');
        $table->string('source_path', 1024)->nullable();
        $table->json('source_paths_json')->nullable();
        $table->string('dest_prefix', 1024)->nullable();
        $table->enum('backup_mode', ['sync', 'archive'])->default('sync');
        $table->enum('status', ['active', 'paused', 'deleted'])->default('active');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent();

        $table->index('client_id');
        $table->index('tenant_id');
        $table->index('repository_id');
        $table->index('agent_uuid');
        $table->index('s3_user_id');
        $table->index('dest_bucket_id');
    });

    $schema->create('s3_cloudbackup_runs', function ($table) {
        $table->bigIncrements('id');
        $table->string('run_uuid', 36)->nullable();
        $table->unsignedInteger('job_id');
        $table->string('agent_uuid', 36)->nullable();
        $table->unsignedInteger('tenant_id')->nullable();
        $table->string('repository_id', 64)->nullable();
        $table->string('status', 32)->default('queued');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->timestamp('updated_at')->nullable();

        $table->index('job_id');
        $table->index('agent_uuid');
        $table->index('tenant_id');
        $table->index('repository_id');
        $table->index('status');
        $table->index('run_uuid');
    });

    $schema->create('s3_cloudbackup_run_events', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('run_id');
        $table->dateTime('ts');
        $table->string('type', 32);
        $table->string('level', 16);
        $table->string('code', 64);
        $table->string('message_id', 64);
        $table->mediumText('params_json');
        $table->index(['run_id', 'ts']);
        $table->index(['run_id', 'id']);
    });

    $schema->create('s3_cloudbackup_run_logs', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('run_id');
        $table->timestamp('created_at')->useCurrent();
        $table->string('level', 16)->default('info');
        $table->string('code', 64)->nullable();
        $table->mediumText('message');
        $table->json('details_json')->nullable();
        $table->index(['run_id', 'created_at']);
    });

    $schema->create('s3_cloudbackup_run_commands', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('run_id')->nullable();
        $table->string('agent_uuid', 36)->nullable();
        $table->string('type', 64);
        $table->json('payload_json')->nullable();
        $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
        $table->mediumText('result_message')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('processed_at')->nullable();
        $table->index(['run_id', 'status']);
        $table->index('agent_uuid', 'idx_run_cmd_agent_uuid');
    });

    $schema->create('s3_cloudbackup_restore_points', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('client_id');
        $table->unsignedInteger('tenant_id')->nullable();
        $table->string('repository_id', 64)->nullable();
        $table->unsignedInteger('tenant_user_id')->nullable();
        $table->string('agent_uuid', 36)->nullable();
        $table->unsignedInteger('job_id')->nullable();
        $table->unsignedBigInteger('run_id')->nullable();
        $table->string('manifest_id', 191)->nullable();
        $table->string('source_path', 1024)->nullable();
        $table->string('dest_prefix', 1024)->nullable();
        $table->string('status', 32)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('finished_at')->nullable();

        $table->index('client_id');
        $table->index('tenant_id');
        $table->index('repository_id');
        $table->index('agent_uuid');
        $table->index('manifest_id');
        $table->index('run_id');
    });

    $schema->create('s3_cloudbackup_agent_destinations', function ($table) {
        $table->bigIncrements('id');
        $table->string('agent_uuid', 36);
        $table->unsignedInteger('client_id');
        $table->unsignedInteger('tenant_id')->nullable();
        $table->unsignedInteger('s3_user_id');
        $table->unsignedInteger('dest_bucket_id');
        $table->string('root_prefix', 1024);
        $table->tinyInteger('is_locked')->default(1);
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent();

        $table->unique('agent_uuid');
        $table->unique(['dest_bucket_id', 'root_prefix'], 'uniq_cloudbackup_dest_bucket_prefix');
        $table->index('client_id');
        $table->index('tenant_id');
        $table->index('s3_user_id');
        $table->index('dest_bucket_id');
    });

    $schema->create('s3_cloudbackup_repositories', function ($table) {
        $table->bigIncrements('id');
        $table->string('repository_id', 64)->unique();
        $table->unsignedInteger('client_id');
        $table->unsignedInteger('tenant_id')->nullable();
        $table->unsignedInteger('tenant_user_id')->nullable();
        $table->unsignedInteger('bucket_id');
        $table->string('root_prefix', 1024);
        $table->string('engine', 32)->default('kopia');
        $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent();
    });

    $schema->create('s3_cloudbackup_repository_keys', function ($table) {
        $table->bigIncrements('id');
        $table->string('repository_ref', 64);
        $table->unsignedInteger('key_version')->default(1);
        $table->string('wrap_alg', 64)->default('aes-256-cbc');
        $table->mediumText('wrapped_repo_secret');
        $table->string('kek_ref', 191)->nullable();
        $table->enum('mode', ['managed_recovery', 'strict_customer_managed'])->default('managed_recovery');
        $table->timestamp('created_at')->useCurrent();
        $table->unsignedInteger('created_by')->nullable();
        $table->unique(['repository_ref', 'key_version'], 'uniq_repository_key_version');
    });

    $schema->create('s3_hyperv_vms', function ($table) {
        $table->bigIncrements('id');
        $table->string('agent_uuid', 36);
        $table->string('vm_name', 191);
        $table->string('vm_guid', 64)->nullable();
        $table->string('state', 32)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent();
        $table->index('agent_uuid');
        $table->index('vm_guid');
    });

    $schema->create('s3_hyperv_checkpoints', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('vm_id');
        $table->string('checkpoint_id', 191);
        $table->timestamp('created_at')->useCurrent();
        $table->index('vm_id');
        $table->index('checkpoint_id');
    });

    $schema->create('s3_hyperv_backup_points', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('vm_id');
        $table->unsignedBigInteger('run_id')->nullable();
        $table->string('manifest_id', 191)->nullable();
        $table->string('backup_type', 32)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->index('vm_id');
        $table->index('run_id');
        $table->index('manifest_id');
    });
}

setCloudStorageSetting('agent_uuid_cutover_maintenance_mode', '1');
recreateUuidFirstSchema();
echo "agent-uuid-bigbang-cutover-ok\n";
