<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function cloudstorage_config()
{
    return [
        'name' => 'Cloud Storage',
        'description' => 'This module show the usage of your buckets.',
        'author' => 'eazybackup',
        'language' => 'english',
        'version' => '1.2.0',
        'fields' => [
            's3_region' => [
                'FriendlyName' => 'S3 Region',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Enter the AWS region string to use for signing (e.g., ca-central-1). Defaults to us-east-1 if empty.'
            ],
            'encryption_key' => [
                'FriendlyName' => 'Encryption Key',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Enter the encryption key.'
            ],
            's3_endpoint' => [
                'FriendlyName' => 'S3 Endpoint',
                'Type' => 'text',
                'Size' => '250',
                'Description' => 'Enter the S3 endpoint.'
            ],
            'ceph_server_ip' => [
                'FriendlyName' => 'Ceph Server Ip',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph server ip.'
            ],
            'ceph_admin_user' => [
                'FriendlyName' => 'Ceph Admin User',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph admin user.'
            ],
            'ceph_access_key' => [
                'FriendlyName' => 'Ceph Access Key',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph access key.'
            ],
            'ceph_secret_key' => [
                'FriendlyName' => 'Ceph Secret Key',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Enter the Ceph secret key.'
            ],
            'turnstile_site_key' => [
                'FriendlyName' => 'Turnstile Site Key',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Cloudflare Turnstile site key used on the signup form.'
            ],
            'turnstile_secret_key' => [
                'FriendlyName' => 'Turnstile Secret Key',
                'Type' => 'password',
                'Size' => '100',
                'Description' => 'Secret key for server-side Turnstile verification.'
            ],
            'default_logging_prefix' => [
                'FriendlyName' => 'Default Logging Prefix',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Default prefix when enabling S3 server access logging (e.g., <bucket>/).',
            ],
            'allow_customer_target_choice' => [
                'FriendlyName' => 'Allow Custom Target Log Bucket',
                'Type' => 'yesno',
                'Description' => 'If unchecked, target bucket will default to <bucket>-logs and cannot be changed by customers.'
            ],
            'cloudbackup_enabled' => [
                'FriendlyName' => 'Enable Cloud Backup',
                'Type' => 'yesno',
                'Description' => 'Enable Cloud-to-Cloud Backup feature for customers.'
            ],
            'cloudbackup_worker_host' => [
                'FriendlyName' => 'Worker Hostname',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Worker VM hostname identifier (e.g., worker-01.internal.e3).'
            ],
            'cloudbackup_global_max_concurrent_jobs' => [
                'FriendlyName' => 'Max Concurrent Jobs',
                'Type' => 'text',
                'Size' => '10',
                'Description' => 'Global maximum number of concurrent backup jobs (integer).'
            ],
            'cloudbackup_global_max_bandwidth_kbps' => [
                'FriendlyName' => 'Max Bandwidth (KB/s)',
                'Type' => 'text',
                'Size' => '20',
                'Description' => 'Global maximum bandwidth limit in KB/s (integer, 0 = unlimited).'
            ],
            'cloudbackup_encryption_key' => [
                'FriendlyName' => 'Cloud Backup Encryption Key',
                'Type' => 'password',
                'Size' => '100',
                'Description' => 'Optional separate encryption key for cloud backup source configs (leave empty to use main encryption key).'
            ],
            'cloudbackup_email_template' => [
                'FriendlyName' => 'Backup Job Notification Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'Select the WHMCS email template from General category to use for backup job notifications.'
            ],
        ]
    ];
}

/**
 * Get a list of email templates from the General category for dropdown selection.
 *
 * @return array
 */
function cloudstorage_get_email_templates()
{
    try {
        if (!function_exists('localAPI')) {
            return [];
        }
        
        $results = localAPI("GetEmailTemplates", ["type" => "general"]);
        
        $templates = ['' => 'None (Disable Email Notifications)'];
        
        if (isset($results["emailtemplates"]["emailtemplate"]) && is_array($results["emailtemplates"]["emailtemplate"])) {
            foreach ($results["emailtemplates"]["emailtemplate"] as $template) {
                if (isset($template["id"]) && isset($template["name"])) {
                    $templates[$template["id"]] = $template["name"];
                }
            }
        }
        
        return $templates;
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'get_email_templates', [], $e->getMessage());
        return ['' => 'Error loading templates'];
    }
}

/**
 * Activate.
 *
 * Called upon activation of the module for the first time.
 * Use this function to perform any database and schema modifications
 * required by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function cloudstorage_activate() {
    try {
        // Log activation start
        logModuleCall('cloudstorage', 'activate', [], 'Starting module activation', [], []);
        
        // Restore settings from backup if they exist
        cloudstorage_restore_settings();
        
        if (!Capsule::schema()->hasTable('s3_users')) {
            Capsule::schema()->create('s3_users', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->string('username');
                $table->unsignedInteger('parent_id')->nullable();
                $table->unsignedInteger('tenant_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_users table', [], []);
        }

        if (!Capsule::schema()->hasTable('s3_prices')) {
            Capsule::schema()->create('s3_prices', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_user_access_keys')) {
            Capsule::schema()->create('s3_user_access_keys', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('access_key', 255)->nullable();
            $table->string('secret_key')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }
        
        if (!Capsule::schema()->hasTable('s3_buckets')) {
            Capsule::schema()->create('s3_buckets', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('name');
            $table->string('s3_id');
            $table->enum('versioning', ['off', 'enabled'])->default('off');
            $table->boolean('object_lock_enabled')->default(0);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
        });
        }

        if (!Capsule::schema()->hasTable('s3_bucket_stats')) {
            Capsule::schema()->create('s3_bucket_stats', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->integer('num_objects')->default(0)->nullable();
            $table->bigInteger('size')->default(0)->nullable();
            $table->bigInteger('size_actual')->default(0)->nullable();
            $table->bigInteger('size_utilized')->default(0)->nullable();
            $table->bigInteger('size_kb')->default(0)->nullable();
            $table->bigInteger('size_kb_actual')->default(0)->nullable();
            $table->bigInteger('size_kb_utilized')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_bucket_stats_summary')) {
            Capsule::schema()->create('s3_bucket_stats_summary', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->bigInteger('total_usage')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_transfer_stats')) {
            Capsule::schema()->create('s3_transfer_stats', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->bigInteger('bytes_sent')->default(0)->nullable();
            $table->bigInteger('bytes_received')->default(0)->nullable();
            $table->bigInteger('ops')->default(0)->nullable();
            $table->bigInteger('successful_ops')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_transfer_stats_summary')) {
            Capsule::schema()->create('s3_transfer_stats_summary', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('bucket_id');
            $table->unsignedInteger('user_id');
            $table->bigInteger('bytes_sent')->default(0)->nullable();
            $table->bigInteger('bytes_received')->default(0)->nullable();
            $table->bigInteger('ops')->default(0)->nullable();
            $table->bigInteger('successful_ops')->default(0)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_delete_buckets')) {
            Capsule::schema()->create('s3_delete_buckets', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('bucket_name');
            $table->tinyInteger('attempt_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_subusers')) {
            Capsule::schema()->create('s3_subusers', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('subuser');
            $table->enum('permission', ['read', 'write', 'readwrite', 'full'])->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_subusers_keys')) {
            Capsule::schema()->create('s3_subusers_keys', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('sub_user_id');
            $table->string('access_key');
            $table->string('secret_key');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sub_user_id')->references('id')->on('s3_subusers')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_bucket_sizes_history')) {
            Capsule::schema()->create('s3_bucket_sizes_history', function ($table) {
            $table->increments('id');
            $table->string('bucket_name');
            $table->string('bucket_owner');
            $table->bigInteger('bucket_size_bytes')->default(0);
            $table->integer('bucket_object_count')->default(0);
            $table->timestamp('collected_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['bucket_name', 'collected_at']);
            $table->index(['bucket_owner', 'collected_at']);
            });
        }

        if (!Capsule::schema()->hasTable('s3_historical_stats')) {
            Capsule::schema()->create('s3_historical_stats', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->date('date'); // Daily date
            $table->bigInteger('total_storage')->default(0)->nullable(); // Current storage snapshot
            $table->bigInteger('bytes_sent')->default(0)->nullable(); // Daily increment
            $table->bigInteger('bytes_received')->default(0)->nullable(); // Daily increment
            $table->bigInteger('operations')->default(0)->nullable(); // Daily increment
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            
            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            $table->unique(['user_id', 'date']); // One record per user per day
            $table->index(['date']);
            });
        }

        // Cloud Backup tables
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            Capsule::schema()->create('s3_cloudbackup_jobs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('s3_user_id');
            $table->string('name', 191);
            $table->enum('source_type', ['s3_compatible', 'aws', 'sftp', 'google_drive', 'dropbox', 'smb', 'nas'])->default('s3_compatible');
            $table->string('source_display_name', 191);
            $table->mediumText('source_config_enc');
            $table->string('source_path', 1024);
            $table->unsignedInteger('dest_bucket_id');
            $table->string('dest_prefix', 1024);
            $table->enum('backup_mode', ['sync', 'archive'])->default('sync');
            $table->enum('schedule_type', ['manual', 'daily', 'weekly', 'cron'])->default('manual');
            $table->time('schedule_time')->nullable();
            $table->tinyInteger('schedule_weekday')->nullable();
            $table->string('schedule_cron', 191)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->tinyInteger('encryption_enabled')->default(0);
            $table->tinyInteger('compression_enabled')->default(0);
            $table->enum('validation_mode', ['none', 'post_run'])->default('none');
            $table->enum('retention_mode', ['none', 'keep_last_n', 'keep_days'])->default('none');
            $table->unsignedInteger('retention_value')->nullable();
            $table->text('notify_override_email')->nullable();
            $table->tinyInteger('notify_on_success')->default(0);
            $table->tinyInteger('notify_on_warning')->default(1);
            $table->tinyInteger('notify_on_failure')->default(1);
            $table->enum('status', ['active', 'paused', 'deleted'])->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('client_id');
            $table->index('s3_user_id');
            $table->index('dest_bucket_id');
            $table->index(['schedule_type', 'status']);
            $table->foreign('s3_user_id')->references('id')->on('s3_users')->onDelete('cascade');
            $table->foreign('dest_bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            Capsule::schema()->create('s3_cloudbackup_runs', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('job_id');
            $table->enum('trigger_type', ['manual', 'schedule', 'validation'])->default('manual');
            $table->enum('status', ['queued', 'starting', 'running', 'success', 'warning', 'failed', 'cancelled'])->default('queued');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->decimal('progress_pct', 5, 2)->nullable();
            $table->unsignedBigInteger('bytes_total')->nullable();
            $table->unsignedBigInteger('bytes_transferred')->nullable();
            $table->unsignedBigInteger('objects_total')->nullable();
            $table->unsignedBigInteger('objects_transferred')->nullable();
            $table->unsignedBigInteger('speed_bytes_per_sec')->nullable();
            $table->unsignedInteger('eta_seconds')->nullable();
            $table->string('current_item', 1024)->nullable();
            $table->string('log_path', 512)->nullable();
            $table->mediumText('log_excerpt')->nullable();
            $table->text('error_summary')->nullable();
            $table->string('worker_host', 191)->nullable();
            $table->tinyInteger('cancel_requested')->default(0);
            $table->enum('validation_mode', ['none', 'post_run'])->default('none');
            $table->enum('validation_status', ['not_run', 'running', 'success', 'failed'])->default('not_run');
            $table->mediumText('validation_log_excerpt')->nullable();

            $table->index('job_id');
            $table->index('status');
            $table->index('started_at');
            $table->foreign('job_id')->references('id')->on('s3_cloudbackup_jobs')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_settings')) {
            Capsule::schema()->create('s3_cloudbackup_settings', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->text('default_notify_emails')->nullable();
            $table->tinyInteger('default_notify_on_success')->default(0);
            $table->tinyInteger('default_notify_on_warning')->default(1);
            $table->tinyInteger('default_notify_on_failure')->default(1);
            $table->string('default_timezone', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique('client_id');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_settings table', [], []);
        }

        logModuleCall('cloudstorage', 'activate', [], 'Module activation completed successfully', [], []);
        
        return [
            'status' => 'success',
            'description' => 'Cloud Storage module activated successfully. All database tables created.'
        ];
    } catch (\Exception $e) {
        $errorMsg = 'Module activation failed: ' . $e->getMessage();
        logModuleCall('cloudstorage', 'activate', [], $errorMsg, [], []);
        
        return [
            'status' => 'error',
            'description' => $errorMsg
        ];
    }
}

/**
 * Deactivate.
 *
 * Called upon deactivation of the module.
 * Use this function to undo any database and schema modifications
 * performed by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function cloudstorage_deactivate() {
    try {
        // Backup settings before deactivation to ensure they persist
        cloudstorage_backup_settings();
        
        logModuleCall('cloudstorage', 'deactivate', [], 'Module deactivated successfully. Settings backed up.', [], []);
        
        return [
            'status' => 'success',
            'description' => 'Cloud Storage module deactivated successfully. Settings have been backed up.'
        ];
    } catch (\Exception $e) {
        $errorMsg = 'Module deactivation failed: ' . $e->getMessage();
        logModuleCall('cloudstorage', 'deactivate', [], $errorMsg, [], []);
        
        return [
            'status' => 'error',
            'description' => $errorMsg
        ];
    }
}

/**
 * Backup module settings to a backup table before deactivation.
 * This ensures settings persist across deactivation/reactivation cycles.
 */
function cloudstorage_backup_settings() {
    try {
        // Create backup table if it doesn't exist
        if (!Capsule::schema()->hasTable('cloudstorage_settings_backup')) {
            Capsule::schema()->create('cloudstorage_settings_backup', function ($table) {
                $table->string('setting', 255)->primary();
                $table->text('value')->nullable();
                $table->timestamp('backed_up_at')->useCurrent();
            });
        }
        
        // Get all current settings from tbladdonmodules
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->get(['setting', 'value']);
        
        // Backup each setting
        foreach ($settings as $setting) {
            $exists = Capsule::table('cloudstorage_settings_backup')
                ->where('setting', $setting->setting)
                ->exists();
            
            if ($exists) {
                Capsule::table('cloudstorage_settings_backup')
                    ->where('setting', $setting->setting)
                    ->update([
                        'value' => $setting->value,
                        'backed_up_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                Capsule::table('cloudstorage_settings_backup')
                    ->insert([
                        'setting' => $setting->setting,
                        'value' => $setting->value,
                        'backed_up_at' => date('Y-m-d H:i:s')
                    ]);
            }
        }
        
        logModuleCall('cloudstorage', 'backup_settings', [], 'Settings backed up: ' . count($settings) . ' settings', [], []);
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'backup_settings', [], 'Error backing up settings: ' . $e->getMessage(), [], []);
        // Don't throw - we don't want to fail deactivation if backup fails
    }
}

/**
 * Restore module settings from backup table after activation.
 * This ensures settings persist across deactivation/reactivation cycles.
 */
function cloudstorage_restore_settings() {
    try {
        // Check if backup table exists
        if (!Capsule::schema()->hasTable('cloudstorage_settings_backup')) {
            return; // No backup to restore
        }
        
        // Get all backed up settings
        $backedUpSettings = Capsule::table('cloudstorage_settings_backup')
            ->get(['setting', 'value']);
        
        if ($backedUpSettings->isEmpty()) {
            return; // No settings to restore
        }
        
        // Restore each setting to tbladdonmodules
        foreach ($backedUpSettings as $backup) {
            $exists = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', $backup->setting)
                ->exists();
            
            if ($exists) {
                // Update existing setting
                Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudstorage')
                    ->where('setting', $backup->setting)
                    ->update(['value' => $backup->value]);
            } else {
                // Insert new setting
                Capsule::table('tbladdonmodules')
                    ->insert([
                        'module' => 'cloudstorage',
                        'setting' => $backup->setting,
                        'value' => $backup->value
                    ]);
            }
        }
        
        logModuleCall('cloudstorage', 'restore_settings', [], 'Settings restored: ' . count($backedUpSettings) . ' settings', [], []);
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'restore_settings', [], 'Error restoring settings: ' . $e->getMessage(), [], []);
        // Don't throw - we don't want to fail activation if restore fails
    }
}

/**
 * Upgrade.
 *
 * Called when the module version increases. Used to apply schema changes.
 *
 * @param array $vars
 * @return array
 */
function cloudstorage_upgrade($vars) {
    try {
        // Add logging columns to s3_buckets if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_buckets')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_buckets', 'logging_enabled')) {
                \WHMCS\Database\Capsule::schema()->table('s3_buckets', function ($table) {
                    $table->tinyInteger('logging_enabled')->default(0);
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_buckets', 'logging_target_bucket')) {
                \WHMCS\Database\Capsule::schema()->table('s3_buckets', function ($table) {
                    $table->string('logging_target_bucket', 255)->nullable();
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_buckets', 'logging_target_prefix')) {
                \WHMCS\Database\Capsule::schema()->table('s3_buckets', function ($table) {
                    $table->string('logging_target_prefix', 255)->nullable();
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_buckets', 'logging_last_synced_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_buckets', function ($table) {
                    $table->timestamp('logging_last_synced_at')->nullable();
                });
            }
        }

        // Add notified_at column to s3_cloudbackup_runs if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'notified_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->timestamp('notified_at')->nullable()->after('finished_at');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'created_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->timestamp('created_at')->useCurrent()->before('started_at');
                });
            }
        }

        // Ensure source_type enum includes 'aws' (modify existing column if needed)
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            try {
                // Attempt to alter enum to include 'aws'
                \WHMCS\Database\Capsule::statement("
                    ALTER TABLE `s3_cloudbackup_jobs`
                    MODIFY COLUMN `source_type` ENUM('s3_compatible','aws','sftp','google_drive','dropbox','smb','nas') NOT NULL DEFAULT 's3_compatible'
                ");
            } catch (\Exception $e) {
                // Safe to ignore if already updated, but log for visibility
                logModuleCall('cloudstorage', 'upgrade_enum_source_type', [], $e->getMessage(), [], []);
            }
        }

        // Add Cloud Backup tables if missing (for existing installations)
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_jobs', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('s3_user_id');
                $table->string('name', 191);
                $table->enum('source_type', ['s3_compatible', 'aws', 'sftp', 'google_drive', 'dropbox', 'smb', 'nas'])->default('s3_compatible');
                $table->string('source_display_name', 191);
                $table->mediumText('source_config_enc');
                $table->string('source_path', 1024);
                $table->unsignedInteger('dest_bucket_id');
                $table->string('dest_prefix', 1024);
                $table->enum('backup_mode', ['sync', 'archive'])->default('sync');
                $table->enum('schedule_type', ['manual', 'daily', 'weekly', 'cron'])->default('manual');
                $table->time('schedule_time')->nullable();
                $table->tinyInteger('schedule_weekday')->nullable();
                $table->string('schedule_cron', 191)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->tinyInteger('encryption_enabled')->default(0);
                $table->tinyInteger('compression_enabled')->default(0);
                $table->enum('validation_mode', ['none', 'post_run'])->default('none');
                $table->enum('retention_mode', ['none', 'keep_last_n', 'keep_days'])->default('none');
                $table->unsignedInteger('retention_value')->nullable();
                $table->text('notify_override_email')->nullable();
                $table->tinyInteger('notify_on_success')->default(0);
                $table->tinyInteger('notify_on_warning')->default(1);
                $table->tinyInteger('notify_on_failure')->default(1);
                $table->enum('status', ['active', 'paused', 'deleted'])->default('active');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->index('client_id');
                $table->index('s3_user_id');
                $table->index('dest_bucket_id');
                $table->index(['schedule_type', 'status']);
                $table->foreign('s3_user_id')->references('id')->on('s3_users')->onDelete('cascade');
                $table->foreign('dest_bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            });
        }

        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_runs', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('job_id');
                $table->enum('trigger_type', ['manual', 'schedule', 'validation'])->default('manual');
                $table->enum('status', ['queued', 'starting', 'running', 'success', 'warning', 'failed', 'cancelled'])->default('queued');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->decimal('progress_pct', 5, 2)->nullable();
                $table->unsignedBigInteger('bytes_total')->nullable();
                $table->unsignedBigInteger('bytes_transferred')->nullable();
                $table->unsignedBigInteger('objects_total')->nullable();
                $table->unsignedBigInteger('objects_transferred')->nullable();
                $table->unsignedBigInteger('speed_bytes_per_sec')->nullable();
                $table->unsignedInteger('eta_seconds')->nullable();
                $table->string('current_item', 1024)->nullable();
                $table->string('log_path', 512)->nullable();
                $table->mediumText('log_excerpt')->nullable();
                $table->text('error_summary')->nullable();
                $table->string('worker_host', 191)->nullable();
                $table->tinyInteger('cancel_requested')->default(0);
                $table->enum('validation_mode', ['none', 'post_run'])->default('none');
                $table->enum('validation_status', ['not_run', 'running', 'success', 'failed'])->default('not_run');
                $table->mediumText('validation_log_excerpt')->nullable();

                $table->index('job_id');
                $table->index('status');
                $table->index('started_at');
                $table->foreign('job_id')->references('id')->on('s3_cloudbackup_jobs')->onDelete('cascade');
            });
        }

        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_settings')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_settings', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->text('default_notify_emails')->nullable();
                $table->tinyInteger('default_notify_on_success')->default(0);
                $table->tinyInteger('default_notify_on_warning')->default(1);
                $table->tinyInteger('default_notify_on_failure')->default(1);
                $table->string('default_timezone', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique('client_id');
            });
        }

        // Add validation_mode to s3_cloudbackup_jobs if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'validation_mode')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                        $table->enum('validation_mode', ['none', 'post_run'])->default('none')->after('compression_enabled');
                    });
                    logModuleCall('cloudstorage', 'upgrade_add_validation_mode', [], 'Added validation_mode column to s3_cloudbackup_jobs', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_validation_mode', [], $e->getMessage(), [], []);
                }
            }
        }

        // Add per_client_max_concurrent_jobs to s3_cloudbackup_settings if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_settings')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_settings', 'per_client_max_concurrent_jobs')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_settings', function ($table) {
                        $table->unsignedInteger('per_client_max_concurrent_jobs')->nullable()->after('default_timezone');
                    });
                    logModuleCall('cloudstorage', 'upgrade_add_per_client_concurrency', [], 'Added per_client_max_concurrent_jobs column to s3_cloudbackup_settings', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_per_client_concurrency', [], $e->getMessage(), [], []);
                }
            }
        }

        return ['status' => 'success'];
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'upgrade', $vars, $e->getMessage());
        return ['status' => 'success'];
    }
}

function cloudstorage_clientarea($vars) {
    $page = $_GET['page'];
    // Preserve module configuration values separately from view variables
    $config = $vars;
    $encryptionKey = $config['encryption_key'];
    $s3Endpoint = $config['s3_endpoint'];
    $cephAdminUser = $config['ceph_admin_user'];
    $cephAdminAccessKey = $config['ceph_access_key'];
    $cephAdminSecretKey = $config['ceph_secret_key'];
    $turnstileSiteKey = $config['turnstile_site_key'] ?? '';
    $turnstileSecretKey = $config['turnstile_secret_key'] ?? '';

    switch ($page) {
        case 'signup':
            $pagetitle = 'e3 Storage Signup';
            $templatefile = 'templates/signup';
            $viewVars = [
                'TURNSTILE_SITE_KEY' => $turnstileSiteKey,
            ];
            break;

        case 'handlesignup':
            $pagetitle = 'e3 Storage Signup';
            // Re-render the same signup template on POST/validation errors
            $templatefile = 'templates/signup';
            // Make Turnstile keys available to the included page
            $routeVars = (function () use ($turnstileSiteKey, $turnstileSecretKey) {
                return require __DIR__ . '/pages/handlesignup.php';
            })();
            $viewVars = is_array($routeVars) ? $routeVars : [];
            if (empty($viewVars['TURNSTILE_SITE_KEY'])) {
                $viewVars['TURNSTILE_SITE_KEY'] = $turnstileSiteKey;
            }
            break;

        case 'test':
            $pagetitle = 'Testing';
            $templatefile = 'templates/dashboard';
            $vars = require 'pages/test.php';
            // $vars = require 'storeexistingaccount.php';
            die('wait test');
            break;

        case 'dashboard':
            $pagetitle = 'Storage Dashboard';
            $templatefile = 'templates/dashboard';
            $viewVars = require 'pages/dashboard.php';
            break;

        case 'access_keys':
            $pagetitle = 'Access Keys';
            $templatefile = 'templates/access_keys';
            $viewVars = require 'pages/access_keys.php';
            break;

        case 'buckets':
            $pagetitle = 'Buckets';
            $templatefile = 'templates/buckets';
            $viewVars = require 'pages/buckets.php';
            break;

        case 'billing':
            $pagetitle = "Billing";
            $templatefile = 'templates/billing';
            $viewVars = require 'pages/billing.php';
            break;

        case 'history':
            $pagetitle = "Usage History";
            $templatefile = 'templates/history';
            $viewVars = require 'pages/history.php';
            break;

        case 'browse':
            $pagetitle = "Browse Bucket";
            $templatefile = 'templates/browse';
            $viewVars = require 'pages/browse.php';
            break;

        case 'subusers':
            $pagetitle = "Sub Users";
            $templatefile = 'templates/subuser';
            $viewVars = require 'pages/subusers.php';
            break;

        case 'users':
            $pagetitle = "Users";
            $templatefile = 'templates/users';
            $viewVars = require 'pages/users.php';
            break;

        case 'savebucket':
            require 'pages/savebucket.php';
            break;

        case 'services':
            $pagetitle = "e3 Cloud Storage";
            $templatefile = 'templates/services';
            $viewVars = require 'pages/services.php';
            break;

        case 'deletebucket':
            require 'pages/deletebucket.php';
            break;

        case 'cloudbackup':
            $view = $_GET['view'] ?? 'cloudbackup_jobs';
            switch ($view) {
                case 'cloudbackup_runs':
                    $pagetitle = 'Backup Run History';
                    $templatefile = 'templates/cloudbackup_runs';
                    $viewVars = require 'pages/cloudbackup_runs.php';
                    break;
                case 'cloudbackup_live':
                    $pagetitle = 'Live Backup Progress';
                    $templatefile = 'templates/cloudbackup_live';
                    $viewVars = require 'pages/cloudbackup_live.php';
                    break;
                case 'cloudbackup_settings':
                    $pagetitle = 'Backup Settings';
                    $templatefile = 'templates/cloudbackup_settings';
                    $viewVars = require 'pages/cloudbackup_settings.php';
                    break;
                case 'cloudbackup_jobs':
                default:
                    $pagetitle = 'Cloud Backup Jobs';
                    $templatefile = 'templates/cloudbackup_jobs';
                    $viewVars = require 'pages/cloudbackup_jobs.php';
                    break;
            }
            break;

        default:
            $pagetitle = 'S3 Storage';
            $templatefile = 'templates/s3storage';
            $viewVars = require 'pages/s3storage.php';
            break;
    }

    if (isset($_SESSION['message'])) {
        $viewVars['message'] = $_SESSION['message'];
        unset($_SESSION['message']);
    }

    return [
        'pagetitle' => $pagetitle,
        'templatefile' => $templatefile,
        'vars' => $viewVars ?? [],
    ];
}

/**
 * Admin Area Output.
 *
 * Called when the addon module is accessed via the admin area.
 * Should return HTML output for display to the admin user.
 *
 * @param array $vars Common variables
 *
 * @return string
 */
function cloudstorage_output($vars)
{
    // Clean any output buffer to prevent language files from outputting
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $action = $_REQUEST['action'] ?? 'bucket_monitor';
    
    switch ($action) {
        case 'cloudbackup_admin':
            require_once __DIR__ . '/pages/admin/cloudbackup_admin.php';
            cloudstorage_admin_cloudbackup($vars);
            break;
        case 'bucket_monitor':
        default:
            require_once __DIR__ . '/pages/admin/bucket_monitor.php';
            cloudstorage_admin_bucket_monitor($vars);
            break;
    }
}

/**
 * Admin Area Sidebar.
 *
 * @param array $vars Common variables
 *
 * @return string
 */
function cloudstorage_sidebar($vars)
{
    $sidebar = '<div class="list-group">
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=bucket_monitor" class="list-group-item">
            <i class="fa fa-database"></i> Bucket Monitor
        </a>
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=cloudbackup_admin" class="list-group-item">
            <i class="fa fa-cloud-upload"></i> Cloud Backup Admin
        </a>
    </div>';
    
    return $sidebar;
}

