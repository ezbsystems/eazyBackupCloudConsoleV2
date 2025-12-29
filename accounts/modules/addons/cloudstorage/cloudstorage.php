<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

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
        'version' => '2.1.1',
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
            'cloudbackup_agent_s3_endpoint' => [
                'FriendlyName' => 'Cloud Backup Agent S3 Endpoint',
                'Type' => 'text',
                'Size' => '250',
                'Description' => 'Endpoint used by the Windows backup agent. Leave empty to fall back to the S3 Endpoint.'
            ],
            'cloudbackup_agent_s3_region' => [
                'FriendlyName' => 'Cloud Backup Agent S3 Region',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Optional region used by the Windows backup agent. Leave empty for Ceph/default/auto-detect.'
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
            'trial_verification_email_template' => [
                'FriendlyName' => 'Trial Verification Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'Select the WHMCS email template from the General category to use for e3 trial email verification.',
            ],
            'allow_key_decrypt' => [
                'FriendlyName' => 'Allow Key Decrypt (Client Area)',
                'Type' => 'yesno',
                'Description' => 'If enabled, the Client Area can decrypt and display existing secret keys. Recommended OFF for security.',
            ],
            'pid_cloud_backup' => [
                'FriendlyName' => 'Cloud Backup Product (PID)',
                'Type' => 'text',
                'Size' => '10',
                'Description' => 'WHMCS Product ID for eazyBackup Cloud Backup (used after Welcome selection).',
            ],
            'pid_cloud_storage' => [
                'FriendlyName' => 'Cloud Storage Product (PID)',
                'Type' => 'text',
                'Size' => '10',
                'Description' => 'WHMCS Product ID for e3 Cloud Storage (used after Welcome selection).',
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
            // Google OAuth configuration for Cloud Backup (Drive)
            'cloudbackup_google_client_id' => [
                'FriendlyName' => 'Google OAuth Client ID',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Google Cloud OAuth 2.0 client ID used for Drive connections.'
            ],
            'cloudbackup_google_client_secret' => [
                'FriendlyName' => 'Google OAuth Client Secret',
                'Type' => 'password',
                'Size' => '100',
                'Description' => 'Google Cloud OAuth 2.0 client secret used for Drive connections.'
            ],
            'cloudbackup_google_scopes' => [
                'FriendlyName' => 'Google OAuth Scopes',
                'Type' => 'text',
                'Size' => '200',
                'Default' => 'https://www.googleapis.com/auth/drive.readonly',
                'Description' => 'Space-separated scopes. Default: https://www.googleapis.com/auth/drive.readonly'
            ],
            // Cloud Backup Event Log Controls
            'cloudbackup_event_retention_days' => [
                'FriendlyName' => 'Backup Event Retention (days)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '60',
                'Description' => 'How many days to retain customer-visible backup events.'
            ],
            'cloudbackup_event_max_per_run' => [
                'FriendlyName' => 'Max Events per Run',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '5000',
                'Description' => 'Hard cap on number of events to store per run.'
            ],
            'cloudbackup_event_progress_interval_seconds' => [
                'FriendlyName' => 'Progress Event Interval (s)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '2',
                'Description' => 'Minimum seconds between progress events from worker.'
            ],
            'msp_client_groups' => [
                'FriendlyName' => 'MSP Client Groups',
                'Type'         => 'textarea',
                'Rows'         => '3',
                'Cols'         => '60',
                'Description'  => cloudstorage_AdminGroupsDescription(),
            ],
            // Tenant Portal Email Templates
            'tenant_welcome_email_template' => [
                'FriendlyName' => 'Tenant Welcome Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'Email sent to new tenant admin users when MSP creates their portal account.'
            ],
            'tenant_password_reset_email_template' => [
                'FriendlyName' => 'Tenant Password Reset Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'Email sent when tenant portal users request a password reset.'
            ],
            'e3_enabled_client_groups' => [
                'FriendlyName' => 'e3 Cloud Backup - Allowed Client Groups',
                'Type'         => 'textarea',
                'Rows'         => '3',
                'Cols'         => '60',
                'Description'  => cloudstorage_ClientGroupsCheckboxUI('e3_enabled_client_groups', 'Select client groups that can access the e3 Cloud Backup menu.'),
            ],
        ]
    ];
}

/**
 * Build a checkbox UI for selecting client groups.
 *
 * @param string $settingName The setting key (used for the hidden textarea name suffix)
 * @param string $labelHelper Fallback helper text
 * @return string
 */
function cloudstorage_ClientGroupsCheckboxUI(string $settingName, string $labelHelper = 'Comma-separated client group IDs'): string
{
    try {
        $rows = Capsule::table('tblclientgroups')->select('id', 'groupname')->orderBy('id')->get();
        $savedCsv = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $settingName)
            ->value('value');

        $selected = [];
        if (is_string($savedCsv) && $savedCsv !== '') {
            foreach (explode(',', $savedCsv) as $v) {
                $v = trim($v);
                if ($v !== '') {
                    $selected[(int)$v] = true;
                }
            }
        }

        ob_start();
        ?>
<style>
  .eb-group-checkboxes { margin-top:8px; max-height:300px; overflow:auto; background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:12px; }
  .eb-group-checkboxes label { display:flex; align-items:center; gap:8px; padding:6px 8px; margin-bottom:4px; background:#0b1220; border:1px solid #1f2937; border-radius:4px; cursor:pointer; color:#e2e8f0; transition:background-color .15s ease; }
  .eb-group-checkboxes label:hover { background:#111827; }
  .eb-group-checkboxes input[type="checkbox"] { width:16px; height:16px; accent-color:#0ea5e9; cursor:pointer; }
  .eb-group-checkboxes .group-name { flex:1; }
  .eb-group-checkboxes .group-id { color:#64748b; font-size:12px; }
  .eb-muted { color:#94a3b8; font-size:12px; margin-top:6px; }
</style>
<div id="eb-<?=$settingName?>-ui">
  <div class="eb-group-checkboxes">
    <?php if (count($rows) === 0): ?>
      <div style="color:#94a3b8;">No client groups found. Create client groups in WHMCS first.</div>
    <?php else: ?>
      <?php foreach ($rows as $r):
        $gid = (int)$r->id;
        $checked = isset($selected[$gid]) ? 'checked' : '';
      ?>
        <label>
          <input type="checkbox" class="eb-group-cb-<?=$settingName?>" data-id="<?= $gid ?>" <?= $checked ?>>
          <span class="group-name"><?= htmlspecialchars($r->groupname, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="group-id">ID: <?= $gid ?></span>
        </label>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="eb-muted">Check the groups that should be allowed. <?=$labelHelper?></div>
</div>
<script>
(function(){
  function syncCheckboxes<?=$settingName?>(){
    var cbs = document.querySelectorAll('.eb-group-cb-<?=$settingName?>:checked');
    var ids = [];
    for(var i=0;i<cbs.length;i++){ ids.push(cbs[i].getAttribute('data-id')); }
    var val = ids.join(', ');
    var ta = document.querySelector('[name$="[<?=$settingName?>]"]') || document.querySelector('[name="<?=$settingName?>"]');
    if(ta){ ta.value = val; }
  }
  var cbs = document.querySelectorAll('.eb-group-cb-<?=$settingName?>');
  for(var i=0;i<cbs.length;i++){
    cbs[i].addEventListener('change', syncCheckboxes<?=$settingName?>);
  }
  syncCheckboxes<?=$settingName?>();
})();
</script>
        <?php
        return ob_get_clean();
    } catch (\Throwable $e) {
        return $labelHelper;
    }
}

/**
 * Checkbox UI description for selecting MSP client groups.
 * Simplified from dual-pane to checkboxes for better compatibility with WHMCS admin.
 */
function cloudstorage_AdminGroupsDescription(): string
{
    try {
        $rows = Capsule::table('tblclientgroups')->select('id', 'groupname')->orderBy('id')->get();
        $savedCsv = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'msp_client_groups')
            ->value('value');

        $selected = [];
        if (is_string($savedCsv) && $savedCsv !== '') {
            foreach (explode(',', $savedCsv) as $v) {
                $v = trim($v);
                if ($v !== '') {
                    $selected[(int)$v] = true;
                }
            }
        }

        ob_start();
        ?>
<style>
  .eb-msp-checkboxes { margin-top:8px; max-height:300px; overflow:auto; background:#0f172a; border:1px solid #1e293b; border-radius:6px; padding:12px; }
  .eb-msp-checkboxes label { display:flex; align-items:center; gap:8px; padding:6px 8px; margin-bottom:4px; background:#0b1220; border:1px solid #1f2937; border-radius:4px; cursor:pointer; color:#e2e8f0; transition:background-color .15s ease; }
  .eb-msp-checkboxes label:hover { background:#111827; }
  .eb-msp-checkboxes input[type="checkbox"] { width:16px; height:16px; accent-color:#0ea5e9; cursor:pointer; }
  .eb-msp-checkboxes .group-name { flex:1; }
  .eb-msp-checkboxes .group-id { color:#64748b; font-size:12px; }
  .eb-muted { color:#94a3b8; font-size:12px; margin-top:6px; }
</style>
<div id="eb-msp-groups-ui">
  <div class="eb-msp-checkboxes">
    <?php if (count($rows) === 0): ?>
      <div style="color:#94a3b8;">No client groups found. Create client groups in WHMCS first.</div>
    <?php else: ?>
      <?php foreach ($rows as $r): 
        $gid = (int)$r->id;
        $checked = isset($selected[$gid]) ? 'checked' : '';
      ?>
        <label>
          <input type="checkbox" class="eb-msp-cb" data-id="<?= $gid ?>" <?= $checked ?>>
          <span class="group-name"><?= htmlspecialchars($r->groupname, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="group-id">ID: <?= $gid ?></span>
        </label>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="eb-muted">Check the groups that should be treated as MSP accounts.</div>
</div>
<script>
(function(){
  function syncCheckboxes(){
    var cbs = document.querySelectorAll('.eb-msp-cb:checked');
    var ids = [];
    for(var i=0; i<cbs.length; i++){ ids.push(cbs[i].getAttribute('data-id')); }
    var val = ids.join(', ');
    var ta = document.querySelector('[name$="[msp_client_groups]"]') || document.querySelector('[name="msp_client_groups"]');
    if(ta){ ta.value = val; }
  }
  var cbs = document.querySelectorAll('.eb-msp-cb');
  for(var i=0; i<cbs.length; i++){
    cbs[i].addEventListener('change', syncCheckboxes);
  }
  // Initial sync
  syncCheckboxes();
})();
</script>
        <?php
        return ob_get_clean();
    } catch (\Throwable $e) {
        return 'Comma-separated client group IDs that should be treated as MSPs.';
    }
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
 * Create Tenant Portal email templates if they don't exist.
 * Called during module activation.
 */
function cloudstorage_create_email_templates()
{
    try {
        // Template 1: Tenant Portal Welcome Email
        $welcomeExists = Capsule::table('tblemailtemplates')
            ->where('name', 'Tenant Portal Welcome')
            ->where('type', 'general')
            ->exists();

        if (!$welcomeExists) {
            Capsule::table('tblemailtemplates')->insert([
                'type' => 'general',
                'name' => 'Tenant Portal Welcome',
                'subject' => 'Your backup portal account is ready - {$tenant_name}',
                'message' => '<p>Hi {$admin_name},</p>
<p>Your organization <strong>{$tenant_name}</strong> has been set up with cloud backup services.</p>
<div style="background: #f1f5f9; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="margin: 0 0 10px 0;"><strong>Portal URL:</strong><br><a href="{$portal_url}">{$portal_url}</a></p>
    <p style="margin: 0 0 10px 0;"><strong>Email:</strong><br>{$admin_email}</p>
    <p style="margin: 0;"><strong>Temporary Password:</strong><br><code style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px;">{$temp_password}</code></p>
</div>
<p style="color: #ef4444;"><strong>Important:</strong> Please change your password after your first login.</p>
<p>Best regards,<br>{$msp_name}</p>',
                'attachments' => '',
                'fromname' => '',
                'fromemail' => '',
                'disabled' => 0,
                'custom' => 0,
                'language' => '',
                'copyto' => '',
                'blind_copy_to' => '',
                'plaintext' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            logModuleCall('cloudstorage', 'create_email_templates', [], 'Created Tenant Portal Welcome template', [], []);
        }

        // Template 2: Tenant Portal Password Reset Email
        $resetExists = Capsule::table('tblemailtemplates')
            ->where('name', 'Tenant Portal Password Reset')
            ->where('type', 'general')
            ->exists();

        if (!$resetExists) {
            Capsule::table('tblemailtemplates')->insert([
                'type' => 'general',
                'name' => 'Tenant Portal Password Reset',
                'subject' => 'Reset your backup portal password',
                'message' => '<p>Hi {$user_name},</p>
<p>We received a request to reset your password for the backup portal.</p>
<div style="background: #f1f5f9; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p><a href="{$reset_url}" style="display: inline-block; padding: 12px 24px; background: #0ea5e9; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">Reset Your Password</a></p>
    <p style="font-size: 12px; color: #64748b; margin-top: 10px;">Or copy this link: {$reset_url}</p>
</div>
<p>This link will expire in 1 hour.</p>
<p>If you did not request this password reset, you can safely ignore this email.</p>
<p>Best regards,<br>{$company_name}</p>',
                'attachments' => '',
                'fromname' => '',
                'fromemail' => '',
                'disabled' => 0,
                'custom' => 0,
                'language' => '',
                'copyto' => '',
                'blind_copy_to' => '',
                'plaintext' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            logModuleCall('cloudstorage', 'create_email_templates', [], 'Created Tenant Portal Password Reset template', [], []);
        }

    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'create_email_templates', [], 'Error: ' . $e->getMessage(), [], []);
    }
}

/**
 * Generate a UUIDv4 string.
 *
 * @return string
 */
function cloudstorage_generate_uuid()
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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
                // NOTE: tenant_id values are 12-digit numeric strings (e.g. 100000000000..999999999999)
                // which do NOT fit in INT UNSIGNED. Use BIGINT UNSIGNED.
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->tinyInteger('is_active')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('deleted_at')->nullable();

                $table->index('is_active');
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
            // Non-secret UI hint (e.g., "ABCD…WXYZ") so we can display without decrypting.
            $table->string('access_key_hint', 32)->nullable();
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
                // Only set for admin deprovision jobs where governance retention bypass is explicitly confirmed.
                $table->tinyInteger('force_bypass_governance')->default(0);
                $table->enum('status', ['queued', 'running', 'blocked', 'failed', 'success'])->default('queued');
                $table->tinyInteger('attempt_count')->default(0);
                $table->text('error')->nullable();
                // Progress / observability (incremental delete)
                $table->integer('last_seen_num_objects')->nullable();
                $table->bigInteger('last_seen_size_actual')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_progress_at')->nullable();
                $table->tinyInteger('no_progress_runs')->default(0);
                $table->text('metrics')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->index(['status', 'created_at']);
                $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_delete_buckets table', [], []);
        }

        // If the table already exists (common on deactivate/activate), ensure newer columns exist.
        // WHMCS deactivation typically does not drop tables, so create() will be skipped.
        if (Capsule::schema()->hasTable('s3_delete_buckets')) {
            // Ensure status/error/timestamps/index exist for older installs that predate deprovision changes
            $ensureBaseCols = [
                'force_bypass_governance' => function ($table) { $table->tinyInteger('force_bypass_governance')->default(0)->after('bucket_name'); },
                'status' => function ($table) { $table->enum('status', ['queued', 'running', 'blocked', 'failed', 'success'])->default('queued')->after('bucket_name'); },
                'error' => function ($table) { $table->text('error')->nullable()->after('attempt_count'); },
                'started_at' => function ($table) { $table->timestamp('started_at')->nullable()->after('created_at'); },
                'completed_at' => function ($table) { $table->timestamp('completed_at')->nullable()->after('started_at'); },
            ];

            foreach ($ensureBaseCols as $col => $adder) {
                if (!Capsule::schema()->hasColumn('s3_delete_buckets', $col)) {
                    try {
                        Capsule::schema()->table('s3_delete_buckets', function ($table) use ($adder) {
                            $adder($table);
                        });
                        logModuleCall('cloudstorage', 'activate', [], "Ensured {$col} on s3_delete_buckets", [], []);
                    } catch (\Throwable $e) {
                        logModuleCall('cloudstorage', "activate_ensure_s3_delete_buckets_{$col}", [], $e->getMessage(), [], []);
                    }
                }
            }

            // Ensure incremental deletion progress columns
            $progressCols = [
                'last_seen_num_objects' => function ($table) { $table->integer('last_seen_num_objects')->nullable()->after('error'); },
                'last_seen_size_actual' => function ($table) { $table->bigInteger('last_seen_size_actual')->nullable()->after('last_seen_num_objects'); },
                'last_seen_at' => function ($table) { $table->timestamp('last_seen_at')->nullable()->after('last_seen_size_actual'); },
                'last_progress_at' => function ($table) { $table->timestamp('last_progress_at')->nullable()->after('last_seen_at'); },
                'no_progress_runs' => function ($table) { $table->tinyInteger('no_progress_runs')->default(0)->after('last_progress_at'); },
                'metrics' => function ($table) { $table->text('metrics')->nullable()->after('no_progress_runs'); },
            ];

            foreach ($progressCols as $col => $adder) {
                if (!Capsule::schema()->hasColumn('s3_delete_buckets', $col)) {
                    try {
                        Capsule::schema()->table('s3_delete_buckets', function ($table) use ($adder) {
                            $adder($table);
                        });
                        logModuleCall('cloudstorage', 'activate', [], "Ensured {$col} on s3_delete_buckets", [], []);
                    } catch (\Throwable $e) {
                        logModuleCall('cloudstorage', "activate_add_s3_delete_buckets_{$col}", [], $e->getMessage(), [], []);
                    }
                }
            }

            // Ensure helpful index exists (ignore errors if it already exists under a different name)
            try {
                Capsule::schema()->table('s3_delete_buckets', function ($table) {
                    $table->index(['status', 'created_at']);
                });
            } catch (\Throwable $e) {
                // Best-effort
            }
        }

        // Deprovision job queue for user deletion
        if (!Capsule::schema()->hasTable('s3_delete_users')) {
            Capsule::schema()->create('s3_delete_users', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('primary_user_id');
                $table->unsignedInteger('requested_by_admin_id')->nullable();
                $table->enum('status', ['queued', 'running', 'blocked', 'failed', 'success'])->default('queued');
                $table->tinyInteger('attempt_count')->default(0);
                $table->text('error')->nullable();
                $table->text('plan_json')->nullable(); // snapshot of usernames/buckets at confirmation
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->index(['status', 'created_at']);
                $table->index('primary_user_id');
                $table->foreign('primary_user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_delete_users table', [], []);
        }

        // Background prefix delete queue
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
                $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_subusers')) {
            Capsule::schema()->create('s3_subusers', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('subuser');
            $table->enum('permission', ['read', 'write', 'readwrite', 'full'])->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_subusers_keys')) {
            Capsule::schema()->create('s3_subusers_keys', function ($table) {
            $table->increments('id');
            // NOTE: historically some installs used sub_user_id; code uses subuser_id.
            // Prefer subuser_id going forward; upgrade will backfill/alias as needed.
            $table->unsignedInteger('subuser_id');
            $table->string('access_key');
            $table->string('secret_key');
            // Non-secret identifier used for UI display (e.g., "ABCD…WXYZ")
            $table->string('access_key_hint', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('subuser_id');
            $table->foreign('subuser_id')->references('id')->on('s3_subusers')->onDelete('cascade');
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
        if (!Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
            Capsule::schema()->create('s3_cloudbackup_agents', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('tenant_user_id')->nullable();
                $table->string('agent_token', 191);
                $table->unsignedInteger('enrollment_token_id')->nullable();
                $table->string('hostname', 191)->nullable();
                // Stable device identity for re-enroll/rekey/reuse
                $table->string('device_id', 64)->nullable();
                $table->string('install_id', 64)->nullable();
                $table->string('device_name', 191)->nullable();
                $table->enum('status', ['active', 'disabled'])->default('active');
                $table->enum('agent_type', ['workstation', 'server', 'hypervisor'])->default('workstation');
                $table->dateTime('last_seen_at')->nullable();
                $table->text('volumes_json')->nullable();
                $table->dateTime('volumes_updated_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique('agent_token');
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('tenant_user_id');
                $table->index(['client_id', 'tenant_id', 'device_id'], 'idx_agent_device_identity');
            });
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            Capsule::schema()->create('s3_cloudbackup_jobs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('s3_user_id');
            $table->string('name', 191);
            $table->enum('source_type', ['s3_compatible', 'aws', 'sftp', 'google_drive', 'dropbox', 'smb', 'nas', 'local_agent'])->default('s3_compatible');
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
            $table->string('run_uuid', 36)->nullable();
            $table->unsignedInteger('job_id');
            $table->enum('trigger_type', ['manual', 'schedule', 'validation'])->default('manual');
            $table->enum('status', ['queued', 'starting', 'running', 'success', 'warning', 'failed', 'cancelled'])->default('queued');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->decimal('progress_pct', 5, 2)->nullable();
            $table->unsignedBigInteger('bytes_total')->nullable();
            $table->unsignedBigInteger('bytes_transferred')->nullable();     // Actual bytes uploaded to storage
            $table->unsignedBigInteger('bytes_processed')->nullable();       // Bytes read/scanned from source (for dedup)
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
            $table->index('run_uuid');
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

        // Cloud Backup run events table (sanitized, vendor-agnostic events)
        if (!Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            Capsule::schema()->create('s3_cloudbackup_run_events', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('run_id');
                $table->dateTime('ts'); // event timestamp (UTC)
                $table->string('type', 32); // start|progress|warning|error|summary|cancelled|validation_*
                $table->string('level', 16); // info|warn|error
                $table->string('code', 64); // PROGRESS_UPDATE|ERROR_NETWORK|...
                $table->string('message_id', 64); // i18n key
                $table->mediumText('params_json'); // JSON string of params
                $table->index(['run_id', 'ts']);
                $table->index(['run_id', 'id']);
                $table->foreign('run_id')->references('id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_run_events table', [], []);
        }

        // Cloud Backup reusable sources table
        if (!Capsule::schema()->hasTable('s3_cloudbackup_sources')) {
            Capsule::schema()->create('s3_cloudbackup_sources', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->enum('provider', ['google_drive','dropbox','sftp','s3_compatible','aws','smb','nas']);
                $table->string('display_name', 191);
                $table->string('account_email', 191)->nullable();
                $table->text('scopes')->nullable();
                $table->mediumText('refresh_token_enc');
                $table->enum('status', ['active','revoked','error'])->default('active');
                $table->text('meta')->nullable(); // store JSON as text for broader compatibility
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['client_id', 'provider'], 'idx_client_provider');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_sources table', [], []);
        }

        // -----------------------------
        // MSP / Tenant tables
        // -----------------------------
        if (!Capsule::schema()->hasTable('s3_backup_tenants')) {
            Capsule::schema()->create('s3_backup_tenants', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');              // MSP's WHMCS client_id
                $table->string('name', 255);
                $table->string('slug', 100);
                $table->string('ceph_uid', 191)->nullable();       // Ceph RGW user ID
                $table->string('bucket_name', 255)->nullable();    // Optional dedicated bucket
                $table->unsignedBigInteger('storage_quota_bytes')->nullable();
                $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
                $table->text('branding_json')->nullable();         // Logo, colors, support info
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique(['client_id', 'slug']);
                $table->index('client_id');
                $table->index('ceph_uid');
                $table->index('status');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_backup_tenants table', [], []);
        }

        // Add profile/billing columns to s3_backup_tenants for enhanced onboarding
        if (Capsule::schema()->hasTable('s3_backup_tenants')) {
            if (!Capsule::schema()->hasColumn('s3_backup_tenants', 'contact_email')) {
                Capsule::schema()->table('s3_backup_tenants', function ($table) {
                    $table->string('contact_email', 255)->nullable()->after('slug');
                    $table->string('contact_name', 255)->nullable()->after('contact_email');
                    $table->string('contact_phone', 50)->nullable()->after('contact_name');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added contact fields to s3_backup_tenants', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_backup_tenants', 'address_line1')) {
                Capsule::schema()->table('s3_backup_tenants', function ($table) {
                    $table->string('address_line1', 255)->nullable()->after('contact_phone');
                    $table->string('address_line2', 255)->nullable()->after('address_line1');
                    $table->string('city', 100)->nullable()->after('address_line2');
                    $table->string('state', 100)->nullable()->after('city');
                    $table->string('postal_code', 20)->nullable()->after('state');
                    $table->string('country', 2)->nullable()->after('postal_code'); // ISO 3166-1 alpha-2
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added address fields to s3_backup_tenants', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_backup_tenants', 'stripe_customer_id')) {
                Capsule::schema()->table('s3_backup_tenants', function ($table) {
                    $table->string('stripe_customer_id', 255)->nullable()->after('country');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added stripe_customer_id to s3_backup_tenants', [], []);
            }
        }

        if (!Capsule::schema()->hasTable('s3_backup_tenant_users')) {
            Capsule::schema()->create('s3_backup_tenant_users', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->string('email', 255);
                $table->string('password_hash', 255);
                $table->string('name', 255);
                $table->enum('role', ['admin', 'user'])->default('user');
                $table->enum('status', ['active', 'disabled'])->default('active');
                $table->string('password_reset_token', 64)->nullable();
                $table->dateTime('password_reset_expires')->nullable();
                $table->dateTime('last_login_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique(['tenant_id', 'email']);
                $table->index('tenant_id');
                $table->index('email');
                $table->foreign('tenant_id')->references('id')->on('s3_backup_tenants')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_backup_tenant_users table', [], []);
        }

        if (!Capsule::schema()->hasTable('s3_agent_enrollment_tokens')) {
            Capsule::schema()->create('s3_agent_enrollment_tokens', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');              // MSP/client who owns the token
                $table->unsignedInteger('tenant_id')->nullable();  // Scoped to tenant (NULL = direct client)
                $table->string('token', 64);                       // ENR-xxxxxxxx
                $table->string('description', 255)->nullable();    // Friendly label
                $table->unsignedInteger('max_uses')->nullable();   // NULL = unlimited
                $table->unsignedInteger('use_count')->default(0);
                $table->dateTime('expires_at')->nullable();        // NULL = never
                $table->dateTime('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique('token');
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('expires_at');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_agent_enrollment_tokens table', [], []);
        }

        if (!Capsule::schema()->hasTable('s3_backup_usage_snapshots')) {
            Capsule::schema()->create('s3_backup_usage_snapshots', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedBigInteger('storage_bytes')->default(0);
                $table->unsignedInteger('agent_count')->default(0);
                $table->unsignedInteger('disk_image_agent_count')->default(0);
                $table->unsignedInteger('vm_count')->default(0);
                $table->dateTime('calculated_at');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['client_id', 'period_start']);
                $table->index(['tenant_id', 'period_start']);
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_backup_usage_snapshots table', [], []);
        }

        if (!Capsule::schema()->hasTable('s3_msp_portal_domains')) {
            Capsule::schema()->create('s3_msp_portal_domains', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');              // MSP's WHMCS client_id
                $table->string('domain', 255);                     // backup.acmemsp.com
                $table->tinyInteger('is_primary')->default(0);
                $table->tinyInteger('is_verified')->default(0);
                $table->text('branding_json')->nullable();         // Logo, colors override
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique('domain');
                $table->index('client_id');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_msp_portal_domains table', [], []);
        }

        // Extend agents with tenant scoping and enrollment metadata
        if (Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'tenant_id')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->unsignedInteger('tenant_id')->nullable()->after('client_id');
                    $table->index('tenant_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added tenant_id to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'tenant_user_id')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->unsignedInteger('tenant_user_id')->nullable()->after('tenant_id');
                    $table->index('tenant_user_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added tenant_user_id to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_type')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->enum('agent_type', ['workstation', 'server', 'hypervisor'])->default('workstation')->after('status');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_type to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'enrollment_token_id')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->unsignedInteger('enrollment_token_id')->nullable()->after('agent_token');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added enrollment_token_id to s3_cloudbackup_agents', [], []);
            }

            // Device identity fields (for re-enroll/rekey/reuse)
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'device_id')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->string('device_id', 64)->nullable()->after('hostname');
                    $table->index(['client_id', 'tenant_id', 'device_id'], 'idx_agent_device_identity');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added device_id + idx_agent_device_identity to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'install_id')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->string('install_id', 64)->nullable()->after('device_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added install_id to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'device_name')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->string('device_name', 191)->nullable()->after('install_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added device_name to s3_cloudbackup_agents', [], []);
            }
        }

        // Add source_connection_id to jobs if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_connection_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->unsignedInteger('source_connection_id')->nullable()->after('source_config_enc');
                    $table->index('source_connection_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added source_connection_id to s3_cloudbackup_jobs', [], []);
            }
            // Add agent_id for local agent binding (nullable to preserve existing jobs)
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->unsignedInteger('agent_id')->nullable()->after('client_id');
                    $table->index('agent_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_id to s3_cloudbackup_jobs', [], []);
            }
            // Kopia/engine fields for local agent jobs
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'engine')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->enum('engine', ['sync', 'kopia'])->default('sync')->after('backup_mode');
                    $table->enum('dest_type', ['s3', 'local'])->default('s3')->after('dest_prefix');
                    $table->string('dest_local_path', 1024)->nullable()->after('dest_prefix');
                    $table->tinyInteger('bucket_auto_create')->default(0)->after('dest_prefix');
                    $table->json('schedule_json')->nullable()->after('schedule_cron');
                    $table->json('retention_json')->nullable()->after('retention_mode');
                    $table->json('policy_json')->nullable()->after('retention_json');
                    $table->integer('bandwidth_limit_kbps')->nullable()->after('policy_json');
                    $table->integer('parallelism')->nullable()->after('bandwidth_limit_kbps');
                    $table->string('encryption_mode', 64)->nullable()->after('parallelism');
                    $table->string('compression', 64)->nullable()->after('encryption_mode');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added Kopia/engine fields to s3_cloudbackup_jobs', [], []);
            }
        }

        // Ensure agent volume columns exist for UI device picker
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'volumes_json')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->text('volumes_json')->nullable()->after('last_seen_at');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added volumes_json to s3_cloudbackup_agents', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'volumes_updated_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->dateTime('volumes_updated_at')->nullable()->after('volumes_json');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added volumes_updated_at to s3_cloudbackup_agents', [], []);
            }
        }

        // Backfill columns on runs table for agent support and timestamps
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->unsignedInteger('agent_id')->nullable()->after('job_id');
                    $table->index('agent_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_id to s3_cloudbackup_runs', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                    $table->index('updated_at');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added updated_at to s3_cloudbackup_runs', [], []);
            }
            // Kopia/engine metadata on runs
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->enum('engine', ['sync', 'kopia'])->default('sync')->after('trigger_type');
                    $table->json('stats_json')->nullable()->after('speed_bytes_per_sec');
                    $table->json('progress_json')->nullable()->after('stats_json');
                    $table->string('log_ref', 255)->nullable()->after('progress_json');
                    $table->json('policy_snapshot')->nullable()->after('log_ref');
                    $table->string('dest_bucket', 255)->nullable()->after('policy_snapshot');
                    $table->string('dest_prefix', 255)->nullable()->after('dest_bucket');
                    $table->string('dest_local_path', 1024)->nullable()->after('dest_prefix');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added Kopia/engine fields to s3_cloudbackup_runs', [], []);
            }
        }

        // Run logs table for Kopia/local agent
        if (!Capsule::schema()->hasTable('s3_cloudbackup_run_logs')) {
            Capsule::schema()->create('s3_cloudbackup_run_logs', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('run_id');
                $table->timestamp('created_at')->useCurrent();
                $table->string('level', 16)->default('info');
                $table->string('code', 64)->nullable();
                $table->mediumText('message');
                $table->json('details_json')->nullable();
                $table->index(['run_id', 'created_at']);
                $table->foreign('run_id')->references('id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_run_logs table', [], []);
        }

        // Run commands table (for maintenance/restore/cancel extensions)
        if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            Capsule::schema()->create('s3_cloudbackup_run_commands', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('run_id');
                $table->string('type', 64); // cancel|maintenance_quick|maintenance_full|restore
                $table->json('payload_json')->nullable(); // target_path, manifest_id, etc.
                $table->enum('status', ['pending','processing','completed','failed'])->default('pending');
                $table->mediumText('result_message')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->index(['run_id','status']);
                $table->foreign('run_id')->references('id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_run_commands table', [], []);
        }

        // Trial signup email verification table
        if (!Capsule::schema()->hasTable('cloudstorage_trial_verifications')) {
            Capsule::schema()->create('cloudstorage_trial_verifications', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->string('email', 191);
                $table->string('token', 191)->unique();
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('consumed_at')->nullable();

                $table->index('client_id');
                $table->index('email');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created cloudstorage_trial_verifications table', [], []);
        }

        // Trial selection table
        if (!Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            Capsule::schema()->create('cloudstorage_trial_selection', function ($table) {
                $table->unsignedInteger('client_id')->primary();
                $table->string('product_choice', 32);
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('product_choice');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created cloudstorage_trial_selection table', [], []);
        }

        // -----------------------------
        // Hyper-V Backup Engine tables
        // -----------------------------

        // Add hyperv_enabled and hyperv_config columns to jobs table if missing
        if (Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'hyperv_enabled')) {
                Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->boolean('hyperv_enabled')->default(false)->after('compression');
                    $table->json('hyperv_config')->nullable()->after('hyperv_enabled');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added hyperv_enabled and hyperv_config to s3_cloudbackup_jobs', [], []);
            }
        }

        // Add disk_manifests_json column to runs table if missing
        if (Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'disk_manifests_json')) {
                Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->json('disk_manifests_json')->nullable()->after('log_ref');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added disk_manifests_json to s3_cloudbackup_runs', [], []);
            }
        }

        // Hyper-V VM Registry: tracks VMs configured for backup
        if (!Capsule::schema()->hasTable('s3_hyperv_vms')) {
            Capsule::schema()->create('s3_hyperv_vms', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('job_id');
                $table->string('vm_name', 255);
                $table->string('vm_guid', 64)->nullable();
                $table->tinyInteger('generation')->default(2);
                $table->boolean('is_linux')->default(false);
                $table->boolean('integration_services')->default(true);
                $table->boolean('rct_enabled')->default(false);
                $table->boolean('backup_enabled')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique(['job_id', 'vm_guid'], 'uk_job_vm');
                $table->index(['job_id', 'backup_enabled'], 'idx_job_enabled');
                $table->foreign('job_id')->references('id')->on('s3_cloudbackup_jobs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_hyperv_vms table', [], []);
        }

        // Hyper-V Checkpoints: tracks backup reference points for RCT
        if (!Capsule::schema()->hasTable('s3_hyperv_checkpoints')) {
            Capsule::schema()->create('s3_hyperv_checkpoints', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('vm_id');
                $table->unsignedBigInteger('run_id')->nullable();
                $table->string('checkpoint_id', 64);
                $table->string('checkpoint_name', 255)->nullable();
                $table->enum('checkpoint_type', ['Production', 'Standard', 'Reference'])->default('Production');
                $table->json('rct_ids')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('merged_at')->nullable();

                $table->index(['vm_id', 'is_active'], 'idx_vm_active');
                $table->index('run_id', 'idx_run_id');
                $table->foreign('vm_id')->references('id')->on('s3_hyperv_vms')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_hyperv_checkpoints table', [], []);
        }

        // Hyper-V Backup Points: tracks backup metadata for restore
        if (!Capsule::schema()->hasTable('s3_hyperv_backup_points')) {
            Capsule::schema()->create('s3_hyperv_backup_points', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('vm_id');
                $table->unsignedBigInteger('run_id');
                $table->enum('backup_type', ['Full', 'Incremental']);
                $table->string('manifest_id', 128);
                $table->unsignedInteger('parent_backup_id')->nullable();
                $table->json('vm_config_json')->nullable();
                $table->json('disk_manifests')->nullable();
                $table->unsignedBigInteger('total_size_bytes')->nullable();
                $table->unsignedBigInteger('changed_size_bytes')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->enum('consistency_level', ['Crash', 'Application', 'CrashNoCheckpoint'])->default('Application');
                $table->json('warnings_json')->nullable();
                $table->string('warning_code', 64)->nullable();
                $table->boolean('has_warnings')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();

                $table->index(['vm_id', 'created_at'], 'idx_vm_created');
                $table->index('manifest_id', 'idx_manifest');
                $table->index('run_id', 'idx_bp_run_id');
                $table->index('has_warnings', 'idx_has_warnings');
                $table->foreign('vm_id')->references('id')->on('s3_hyperv_vms')->onDelete('cascade');
                $table->foreign('run_id')->references('id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_hyperv_backup_points table', [], []);
        }

        // Add missing columns to s3_hyperv_backup_points if they don't exist
        if (Capsule::schema()->hasTable('s3_hyperv_backup_points')) {
            if (!Capsule::schema()->hasColumn('s3_hyperv_backup_points', 'warnings_json')) {
                Capsule::schema()->table('s3_hyperv_backup_points', function ($table) {
                    $table->json('warnings_json')->nullable()->after('consistency_level');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added warnings_json to s3_hyperv_backup_points', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_hyperv_backup_points', 'warning_code')) {
                Capsule::schema()->table('s3_hyperv_backup_points', function ($table) {
                    $table->string('warning_code', 64)->nullable()->after('warnings_json');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added warning_code to s3_hyperv_backup_points', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_hyperv_backup_points', 'has_warnings')) {
                Capsule::schema()->table('s3_hyperv_backup_points', function ($table) {
                    $table->boolean('has_warnings')->default(false)->after('warning_code');
                    $table->index('has_warnings', 'idx_has_warnings');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added has_warnings to s3_hyperv_backup_points', [], []);
            }
        }

        // Extend engine enum to include disk_image and hyperv if the column exists
        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'engine')) {
            try {
                // Check current enum values by querying column type
                $colType = Capsule::select("SHOW COLUMNS FROM s3_cloudbackup_jobs WHERE Field = 'engine'");
                if (!empty($colType) && isset($colType[0]->Type)) {
                    $typeStr = $colType[0]->Type;
                    // If hyperv is not in the enum, alter the column to add it
                    if (strpos($typeStr, 'hyperv') === false || strpos($typeStr, 'disk_image') === false) {
                        Capsule::statement("ALTER TABLE s3_cloudbackup_jobs MODIFY COLUMN engine ENUM('sync', 'kopia', 'disk_image', 'hyperv') NOT NULL DEFAULT 'sync'");
                        logModuleCall('cloudstorage', 'activate', [], 'Extended engine enum in s3_cloudbackup_jobs to include disk_image and hyperv', [], []);
                    }
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'activate_engine_enum_jobs', [], $e->getMessage(), [], []);
            }
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
            try {
                $colType = Capsule::select("SHOW COLUMNS FROM s3_cloudbackup_runs WHERE Field = 'engine'");
                if (!empty($colType) && isset($colType[0]->Type)) {
                    $typeStr = $colType[0]->Type;
                    if (strpos($typeStr, 'hyperv') === false || strpos($typeStr, 'disk_image') === false) {
                        Capsule::statement("ALTER TABLE s3_cloudbackup_runs MODIFY COLUMN engine ENUM('sync', 'kopia', 'disk_image', 'hyperv') NOT NULL DEFAULT 'sync'");
                        logModuleCall('cloudstorage', 'activate', [], 'Extended engine enum in s3_cloudbackup_runs to include disk_image and hyperv', [], []);
                    }
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'activate_engine_enum_runs', [], $e->getMessage(), [], []);
            }
        }

        // Create Tenant Portal Email Templates if they don't exist
        cloudstorage_create_email_templates();

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
        // Widen s3_users.tenant_id to BIGINT UNSIGNED to safely store 12-digit RGW tenant IDs.
        // This is a non-destructive widening change, but if values were previously inserted into an INT column,
        // MySQL may have clamped them (e.g. to 4294967295). This migration won't "fix" already-clamped values;
        // it prevents further truncation going forward.
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_users') && \WHMCS\Database\Capsule::schema()->hasColumn('s3_users', 'tenant_id')) {
            try {
                $databaseName = \WHMCS\Database\Capsule::connection()->getDatabaseName();
                $col = \WHMCS\Database\Capsule::table('information_schema.COLUMNS')
                    ->select(['DATA_TYPE', 'COLUMN_TYPE', 'IS_NULLABLE'])
                    ->where('TABLE_SCHEMA', $databaseName)
                    ->where('TABLE_NAME', 's3_users')
                    ->where('COLUMN_NAME', 'tenant_id')
                    ->first();

                $dataType = strtolower((string) ($col->DATA_TYPE ?? ''));
                $columnType = strtolower((string) ($col->COLUMN_TYPE ?? ''));
                $isUnsigned = ($columnType !== '' && strpos($columnType, 'unsigned') !== false);

                $needsWiden = in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer'], true);
                $needsUnsignedFix = ($dataType === 'bigint' && !$isUnsigned);
                // If it's a string type, leave it as-is (some installs may have already migrated manually).
                $isStringType = in_array($dataType, ['varchar', 'char', 'text', 'longtext', 'mediumtext'], true);

                if (!$isStringType && ($needsWiden || $needsUnsignedFix)) {
                    \WHMCS\Database\Capsule::statement("ALTER TABLE `s3_users` MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NULL");
                    logModuleCall('cloudstorage', 'upgrade_s3_users_tenant_id_bigint', [
                        'from_data_type' => $dataType,
                        'from_column_type' => $columnType,
                    ], 'Altered s3_users.tenant_id to BIGINT UNSIGNED NULL', [], []);
                }

                // Optional visibility: if we detect clamped INT UNSIGNED max value, log it.
                // (This does not change data; it helps diagnose whether a manual remediation is needed.)
                try {
                    $clampedCount = (int) \WHMCS\Database\Capsule::table('s3_users')->where('tenant_id', 4294967295)->count();
                    if ($clampedCount > 0) {
                        logModuleCall('cloudstorage', 'upgrade_s3_users_tenant_id_clamped_detected', [
                            'clamped_value' => 4294967295,
                            'count' => $clampedCount,
                        ], 'Detected potential clamped tenant_id values from previous INT schema', [], []);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade_s3_users_tenant_id_bigint_fail', [], $e->getMessage(), [], []);
            }
        }

        // Access Keys v2 (client-facing): store description + non-secret key hint for subuser-backed access keys.
        // Also normalize historical column mismatch: s3_subusers_keys.sub_user_id vs subuser_id.
        try {
            $schema = \WHMCS\Database\Capsule::schema();

            if ($schema->hasTable('s3_subusers')) {
                if (!$schema->hasColumn('s3_subusers', 'description')) {
                    $schema->table('s3_subusers', function ($table) {
                        $table->string('description', 255)->nullable()->after('permission');
                    });
                    logModuleCall('cloudstorage', 'upgrade_s3_subusers_add_description', [], 'Added description to s3_subusers', [], []);
                }
            }

            if ($schema->hasTable('s3_subusers_keys')) {
                $hasSubUserId = $schema->hasColumn('s3_subusers_keys', 'sub_user_id');
                $hasSubuserId = $schema->hasColumn('s3_subusers_keys', 'subuser_id');

                if ($hasSubUserId && !$hasSubuserId) {
                    // Add the canonical column name used by app code.
                    $schema->table('s3_subusers_keys', function ($table) {
                        $table->unsignedInteger('subuser_id')->nullable()->after('id');
                        $table->index('subuser_id');
                    });
                    // Backfill from historical column
                    try {
                        \WHMCS\Database\Capsule::statement("UPDATE `s3_subusers_keys` SET `subuser_id` = `sub_user_id` WHERE `subuser_id` IS NULL");
                    } catch (\Throwable $__) {}
                    logModuleCall('cloudstorage', 'upgrade_s3_subusers_keys_add_subuser_id', [], 'Added subuser_id to s3_subusers_keys and backfilled', [], []);
                }

                if (!$schema->hasColumn('s3_subusers_keys', 'access_key_hint')) {
                    $schema->table('s3_subusers_keys', function ($table) {
                        $table->string('access_key_hint', 32)->nullable()->after('secret_key');
                        $table->index('access_key_hint');
                    });
                    logModuleCall('cloudstorage', 'upgrade_s3_subusers_keys_add_hint', [], 'Added access_key_hint to s3_subusers_keys', [], []);
                }

                // Backfill access_key_hint for existing rows (non-secret), using encryption key if available.
                try {
                    $encKey = (string) \WHMCS\Database\Capsule::table('tbladdonmodules')
                        ->where('module', 'cloudstorage')
                        ->where('setting', 'encryption_key')
                        ->value('value');
                    if ($encKey !== '' && $schema->hasColumn('s3_subusers_keys', 'access_key_hint')) {
                        $query = \WHMCS\Database\Capsule::table('s3_subusers_keys')
                            ->select(['id', 'access_key', 'access_key_hint'])
                            ->where(function ($q) {
                                $q->whereNull('access_key_hint')->orWhere('access_key_hint', '=', '');
                            })
                            ->orderBy('id', 'asc');

                        // Chunk by id to avoid memory issues on large installs
                        $lastId = 0;
                        $chunkSize = 250;
                        while (true) {
                            $rows = (clone $query)->where('id', '>', $lastId)->limit($chunkSize)->get();
                            if (!$rows || count($rows) === 0) {
                                break;
                            }
                            foreach ($rows as $r) {
                                $lastId = (int) $r->id;
                                $akEnc = (string) ($r->access_key ?? '');
                                if ($akEnc === '') {
                                    continue;
                                }
                                $ak = '';
                                try {
                                    $ak = (string) \WHMCS\Module\Addon\CloudStorage\Client\HelperController::decryptKey($akEnc, $encKey);
                                } catch (\Throwable $__) {
                                    $ak = '';
                                }
                                $ak = trim($ak);
                                if ($ak === '') {
                                    continue;
                                }
                                $hint = (strlen($ak) <= 8)
                                    ? $ak
                                    : (substr($ak, 0, 4) . '…' . substr($ak, -4));
                                try {
                                    \WHMCS\Database\Capsule::table('s3_subusers_keys')
                                        ->where('id', (int) $r->id)
                                        ->update(['access_key_hint' => $hint]);
                                } catch (\Throwable $__) {}
                            }
                        }
                        logModuleCall('cloudstorage', 'upgrade_s3_subusers_keys_hint_backfill', [], 'Backfilled access_key_hint where possible', [], []);
                    }
                } catch (\Throwable $__) {
                    // ignore
                }
            }

            // Primary user key hint (s3_user_access_keys)
            if ($schema->hasTable('s3_user_access_keys')) {
                if (!$schema->hasColumn('s3_user_access_keys', 'access_key_hint')) {
                    $schema->table('s3_user_access_keys', function ($table) {
                        $table->string('access_key_hint', 32)->nullable()->after('secret_key');
                        $table->index('access_key_hint');
                    });
                    logModuleCall('cloudstorage', 'upgrade_s3_user_access_keys_add_hint', [], 'Added access_key_hint to s3_user_access_keys', [], []);
                }

                // Backfill hints for existing rows (best-effort)
                try {
                    $encKey = (string) \WHMCS\Database\Capsule::table('tbladdonmodules')
                        ->where('module', 'cloudstorage')
                        ->where('setting', 'encryption_key')
                        ->value('value');
                    if ($encKey !== '' && $schema->hasColumn('s3_user_access_keys', 'access_key_hint')) {
                        $q = \WHMCS\Database\Capsule::table('s3_user_access_keys')
                            ->select(['id', 'access_key', 'access_key_hint'])
                            ->where(function ($q2) {
                                $q2->whereNull('access_key_hint')->orWhere('access_key_hint', '=', '');
                            })
                            ->orderBy('id', 'asc');
                        $lastId = 0;
                        $chunkSize = 250;
                        while (true) {
                            $rows = (clone $q)->where('id', '>', $lastId)->limit($chunkSize)->get();
                            if (!$rows || count($rows) === 0) break;
                            foreach ($rows as $r) {
                                $lastId = (int) $r->id;
                                $akEnc = (string) ($r->access_key ?? '');
                                if ($akEnc === '') continue;
                                $ak = '';
                                try {
                                    $ak = (string) \WHMCS\Module\Addon\CloudStorage\Client\HelperController::decryptKey($akEnc, $encKey);
                                } catch (\Throwable $__) { $ak = ''; }
                                $ak = trim($ak);
                                if ($ak === '') continue;
                                $hint = (strlen($ak) <= 8) ? $ak : (substr($ak, 0, 4) . '…' . substr($ak, -4));
                                try {
                                    \WHMCS\Database\Capsule::table('s3_user_access_keys')->where('id', (int)$r->id)->update(['access_key_hint' => $hint]);
                                } catch (\Throwable $__) {}
                            }
                        }
                        logModuleCall('cloudstorage', 'upgrade_s3_user_access_keys_hint_backfill', [], 'Backfilled s3_user_access_keys.access_key_hint where possible', [], []);
                    }
                } catch (\Throwable $__) {}
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'upgrade_access_keys_v2_fail', [], $e->getMessage(), [], []);
        }

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

        // Ensure trial selection table exists
        if (!\WHMCS\Database\Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            \WHMCS\Database\Capsule::schema()->create('cloudstorage_trial_selection', function ($table) {
                $table->unsignedInteger('client_id')->primary();
                $table->string('product_choice', 32);
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('product_choice');
            });
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

        // Ensure source_type enum includes new providers (aws + local_agent) for upgraded installs
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            try {
                // Attempt to alter enum to include aws + local_agent
                \WHMCS\Database\Capsule::statement("
                    ALTER TABLE `s3_cloudbackup_jobs`
                    MODIFY COLUMN `source_type` ENUM('s3_compatible','aws','sftp','google_drive','dropbox','smb','nas','local_agent') NOT NULL DEFAULT 's3_compatible'
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
                $table->enum('source_type', ['s3_compatible', 'aws', 'sftp', 'google_drive', 'dropbox', 'smb', 'nas', 'local_agent'])->default('s3_compatible');
                $table->string('source_display_name', 191);
                $table->mediumText('source_config_enc');
                $table->unsignedInteger('source_connection_id')->nullable();
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
                $table->index('source_connection_id');
                $table->index('dest_bucket_id');
                $table->index(['schedule_type', 'status']);
                $table->foreign('s3_user_id')->references('id')->on('s3_users')->onDelete('cascade');
                $table->foreign('dest_bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            });
        }

        // Ensure sources table exists for upgraded installs
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_sources')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_sources', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->enum('provider', ['google_drive','dropbox','sftp','s3_compatible','aws','smb','nas']);
                $table->string('display_name', 191);
                $table->string('account_email', 191)->nullable();
                $table->text('scopes')->nullable();
                $table->mediumText('refresh_token_enc');
                $table->enum('status', ['active','revoked','error'])->default('active');
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['client_id', 'provider'], 'idx_client_provider');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_sources table', [], []);
        }

        // Add source_connection_id column on upgrade if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_connection_id')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                        $table->unsignedInteger('source_connection_id')->nullable()->after('source_config_enc');
                        $table->index('source_connection_id');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added source_connection_id to s3_cloudbackup_jobs', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_source_connection_id', [], $e->getMessage(), [], []);
                }
            }
        }

        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_runs', function ($table) {
                $table->bigIncrements('id');
                $table->string('run_uuid', 36)->nullable();
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
                $table->unsignedBigInteger('bytes_processed')->nullable();    // Bytes read/scanned from source
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
                $table->index('run_uuid');
                $table->foreign('job_id')->references('id')->on('s3_cloudbackup_jobs')->onDelete('cascade');
            });
        }

        // Add Kopia/engine columns to jobs on upgrade
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'engine')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                        $table->enum('engine', ['sync', 'kopia'])->default('sync')->after('backup_mode');
                        $table->enum('dest_type', ['s3', 'local'])->default('s3')->after('dest_prefix');
                        $table->string('dest_local_path', 1024)->nullable()->after('dest_prefix');
                        $table->tinyInteger('bucket_auto_create')->default(0)->after('dest_prefix');
                        $table->json('schedule_json')->nullable()->after('schedule_cron');
                        $table->json('retention_json')->nullable()->after('retention_mode');
                        $table->json('policy_json')->nullable()->after('retention_json');
                        $table->integer('bandwidth_limit_kbps')->nullable()->after('policy_json');
                        $table->integer('parallelism')->nullable()->after('bandwidth_limit_kbps');
                        $table->string('encryption_mode', 64)->nullable()->after('parallelism');
                        $table->string('compression', 64)->nullable()->after('encryption_mode');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added Kopia/engine fields to s3_cloudbackup_jobs', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_kopia_fields_jobs', [], $e->getMessage(), [], []);
                }
            }
        }

        // Add Kopia/engine columns to runs on upgrade
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                        $table->enum('engine', ['sync', 'kopia'])->default('sync')->after('trigger_type');
                        $table->json('stats_json')->nullable()->after('speed_bytes_per_sec');
                        $table->json('progress_json')->nullable()->after('stats_json');
                        $table->string('log_ref', 255)->nullable()->after('progress_json');
                        $table->json('policy_snapshot')->nullable()->after('log_ref');
                        $table->string('dest_bucket', 255)->nullable()->after('policy_snapshot');
                        $table->string('dest_prefix', 255)->nullable()->after('dest_bucket');
                        $table->string('dest_local_path', 1024)->nullable()->after('dest_prefix');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added Kopia/engine fields to s3_cloudbackup_runs', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_kopia_fields_runs', [], $e->getMessage(), [], []);
                }
            }
        }

        // Add run_uuid to s3_cloudbackup_runs for customer-facing identifiers
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                        $table->string('run_uuid', 36)->nullable()->after('id');
                        $table->index('run_uuid');
                    });
                    // Backfill existing rows with UUIDs
                    \WHMCS\Database\Capsule::table('s3_cloudbackup_runs')
                        ->whereNull('run_uuid')
                        ->orderBy('id')
                        ->chunk(500, function ($runs) {
                            foreach ($runs as $run) {
                                \WHMCS\Database\Capsule::table('s3_cloudbackup_runs')
                                    ->where('id', $run->id)
                                    ->update(['run_uuid' => cloudstorage_generate_uuid()]);
                            }
                        });
                    logModuleCall('cloudstorage', 'upgrade_add_run_uuid', [], 'run_uuid added and backfilled');
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_run_uuid_error', [], $e->getMessage(), [], []);
                }
            }
            // Add bytes_processed column for deduplication progress tracking
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'bytes_processed')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                        $table->unsignedBigInteger('bytes_processed')->nullable()->after('bytes_transferred');
                    });
                    logModuleCall('cloudstorage', 'upgrade_add_bytes_processed', [], 'bytes_processed column added');
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_bytes_processed_error', [], $e->getMessage(), [], []);
                }
            }
            
            // Add run_type column to distinguish backup vs restore runs
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                        $table->string('run_type', 32)->nullable()->after('status');
                    });
                    logModuleCall('cloudstorage', 'upgrade_add_run_type', [], 'run_type column added');
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_run_type_error', [], $e->getMessage(), [], []);
                }
            }
        }

        // Create run logs table if missing on upgrade
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_run_logs')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_run_logs', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('run_id');
                $table->timestamp('created_at')->useCurrent();
                $table->string('level', 16)->default('info');
                $table->string('code', 64)->nullable();
                $table->mediumText('message');
                $table->json('details_json')->nullable();
                $table->index(['run_id', 'created_at']);
                $table->foreign('run_id')->references('id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_run_logs table', [], []);
        }

        // Create run commands table if missing on upgrade
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_run_commands', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('run_id');
                $table->string('type', 64);
                $table->json('payload_json')->nullable();
                $table->enum('status', ['pending','processing','completed','failed'])->default('pending');
                $table->mediumText('result_message')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->index(['run_id','status']);
                $table->foreign('run_id')->references('id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_run_commands table', [], []);
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

        // Ensure trial verification table exists for upgraded installs
        if (!\WHMCS\Database\Capsule::schema()->hasTable('cloudstorage_trial_verifications')) {
            \WHMCS\Database\Capsule::schema()->create('cloudstorage_trial_verifications', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->string('email', 191);
                $table->string('token', 191)->unique();
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('consumed_at')->nullable();

                $table->index('client_id');
                $table->index('email');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created cloudstorage_trial_verifications table', [], []);
        }

        // Ensure run events table exists for upgraded installs
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_run_events', function ($table) {
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
                $table->foreign('run_id')->references('id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_run_events table', [], []);
        }

        // Ensure prefix delete queue exists on upgrades
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_delete_prefixes')) {
            \WHMCS\Database\Capsule::schema()->create('s3_delete_prefixes', function ($table) {
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

        // ---------------------------------------------------
        // Cloud Storage Deprovision: s3_users status columns
        // ---------------------------------------------------
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_users')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_users', 'is_active')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_users', function ($table) {
                        $table->tinyInteger('is_active')->default(1)->after('tenant_id');
                        $table->index('is_active');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added is_active column to s3_users', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_s3_users_is_active', [], $e->getMessage(), [], []);
                }
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_users', 'deleted_at')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_users', function ($table) {
                        $table->timestamp('deleted_at')->nullable()->after('created_at');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added deleted_at column to s3_users', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_s3_users_deleted_at', [], $e->getMessage(), [], []);
                }
            }
        }

        // ---------------------------------------------------
        // Cloud Storage Deprovision: s3_delete_users job queue
        // ---------------------------------------------------
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_delete_users')) {
            try {
                \WHMCS\Database\Capsule::schema()->create('s3_delete_users', function ($table) {
                    $table->increments('id');
                    $table->unsignedInteger('primary_user_id');
                    $table->unsignedInteger('requested_by_admin_id')->nullable();
                    $table->enum('status', ['queued', 'running', 'blocked', 'failed', 'success'])->default('queued');
                    $table->tinyInteger('attempt_count')->default(0);
                    $table->text('error')->nullable();
                    $table->text('plan_json')->nullable(); // snapshot of usernames/buckets at confirmation
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('started_at')->nullable();
                    $table->timestamp('completed_at')->nullable();

                    $table->index(['status', 'created_at']);
                    $table->index('primary_user_id');
                    $table->foreign('primary_user_id')->references('id')->on('s3_users')->onDelete('cascade');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_delete_users table', [], []);
            } catch (\Exception $e) {
                logModuleCall('cloudstorage', 'upgrade_create_s3_delete_users', [], $e->getMessage(), [], []);
            }
        }

        // Add status and error columns to s3_delete_buckets for better tracking
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_delete_buckets')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_delete_buckets', 'status')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_delete_buckets', function ($table) {
                        $table->enum('status', ['queued', 'running', 'blocked', 'failed', 'success'])->default('queued')->after('bucket_name');
                        $table->text('error')->nullable()->after('attempt_count');
                        $table->timestamp('started_at')->nullable()->after('created_at');
                        $table->timestamp('completed_at')->nullable()->after('started_at');
                        $table->index(['status', 'created_at']);
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added status/error columns to s3_delete_buckets', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_s3_delete_buckets_status', [], $e->getMessage(), [], []);
                }
            }

            // Ensure governance bypass flag exists (used only by admin deprovision flow)
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_delete_buckets', 'force_bypass_governance')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_delete_buckets', function ($table) {
                        $table->tinyInteger('force_bypass_governance')->default(0)->after('bucket_name');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added force_bypass_governance to s3_delete_buckets', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_s3_delete_buckets_force_bypass_governance', [], $e->getMessage(), [], []);
                }
            }

            // Add incremental deletion progress columns (defensive per-column upgrades)
            $progressCols = [
                'last_seen_num_objects' => function ($table) { $table->integer('last_seen_num_objects')->nullable()->after('error'); },
                'last_seen_size_actual' => function ($table) { $table->bigInteger('last_seen_size_actual')->nullable()->after('last_seen_num_objects'); },
                'last_seen_at' => function ($table) { $table->timestamp('last_seen_at')->nullable()->after('last_seen_size_actual'); },
                'last_progress_at' => function ($table) { $table->timestamp('last_progress_at')->nullable()->after('last_seen_at'); },
                'no_progress_runs' => function ($table) { $table->tinyInteger('no_progress_runs')->default(0)->after('last_progress_at'); },
                'metrics' => function ($table) { $table->text('metrics')->nullable()->after('no_progress_runs'); },
            ];

            foreach ($progressCols as $col => $adder) {
                if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_delete_buckets', $col)) {
                    try {
                        \WHMCS\Database\Capsule::schema()->table('s3_delete_buckets', function ($table) use ($adder) {
                            $adder($table);
                        });
                        logModuleCall('cloudstorage', 'upgrade', [], "Added {$col} to s3_delete_buckets", [], []);
                    } catch (\Exception $e) {
                        logModuleCall('cloudstorage', "upgrade_add_s3_delete_buckets_{$col}", [], $e->getMessage(), [], []);
                    }
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
    $clientId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    $clientGroupId = 0;
    if ($clientId > 0) {
        try {
            $clientGroupId = (int) Capsule::table('tblclients')->where('id', $clientId)->value('groupid');
        } catch (\Throwable $e) {
            $clientGroupId = 0;
        }
    }
    $e3AllowedGroupsCsv = (string) (Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'e3_enabled_client_groups')
        ->value('value') ?? '');
    $e3Allowed = false;
    if ($clientGroupId > 0 && $e3AllowedGroupsCsv !== '') {
        $ids = array_filter(array_map('trim', explode(',', $e3AllowedGroupsCsv)), function ($v) {
            return $v !== '';
        });
        $ids = array_map('intval', $ids);
        $e3Allowed = in_array($clientGroupId, $ids, true);
    }
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

        case 'verifytrial':
            $pagetitle = 'Verify e3 Trial';
            $templatefile = 'templates/signup';
            $viewVars = require __DIR__ . '/pages/verifytrial.php';
            if (empty($viewVars['TURNSTILE_SITE_KEY'])) {
                $viewVars['TURNSTILE_SITE_KEY'] = $turnstileSiteKey;
            }
            break;

        case 'welcome':
            $pagetitle = 'Welcome to e3';
            $templatefile = 'templates/welcome';
            // Initially no special vars; template will fetch any needed session/user context
            $viewVars = [];
            $clientArea = new \WHMCS\ClientArea();
            if (!$clientArea->isLoggedIn()) {
                $redirectUrl = cloudstorage_get_welcome_sso_redirect();
                if ($redirectUrl) {
                    header("Location: {$redirectUrl}");
                    exit;
                }
                header('Location: clientarea.php');
                exit;
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

        case 'e3backup':
            $view = $_GET['view'] ?? 'dashboard';
            switch ($view) {
                case 'live':
                    $pagetitle = 'e3 Cloud Backup - Live Progress';
                    $templatefile = 'templates/e3backup_live';
                    $viewVars = require 'pages/e3backup_live.php';
                    break;
                case 'agents':
                    $pagetitle = 'e3 Cloud Backup - Agents';
                    $templatefile = 'templates/e3backup_agents';
                    $viewVars = require 'pages/e3backup_agents.php';
                    break;
                case 'tokens':
                    $pagetitle = 'e3 Cloud Backup - Enrollment Tokens';
                    $templatefile = 'templates/e3backup_tokens';
                    $viewVars = require 'pages/e3backup_tokens.php';
                    break;
                case 'tenants':
                    $pagetitle = 'e3 Cloud Backup - Tenants';
                    $templatefile = 'templates/e3backup_tenants';
                    $viewVars = require 'pages/e3backup_tenants.php';
                    break;
                case 'jobs':
                    $pagetitle = 'e3 Cloud Backup - Jobs';
                    $templatefile = 'templates/e3backup_jobs';
                    $viewVars = require 'pages/e3backup_jobs.php';
                    break;
                case 'tenant_users':
                    $pagetitle = 'e3 Cloud Backup - Tenant Users';
                    $templatefile = 'templates/e3backup_tenant_users';
                    $viewVars = require 'pages/e3backup_tenant_users.php';
                    break;
                case 'hyperv':
                    $pagetitle = 'e3 Cloud Backup - Hyper-V';
                    $templatefile = 'templates/e3backup_hyperv';
                    $viewVars = require 'pages/e3backup_hyperv.php';
                    break;
                case 'hyperv_restore':
                    $pagetitle = 'e3 Cloud Backup - Hyper-V Restore';
                    $templatefile = 'templates/e3backup_hyperv_restore';
                    $viewVars = require 'pages/e3backup_hyperv_restore.php';
                    break;
                case 'dashboard':
                default:
                    $pagetitle = 'e3 Cloud Backup';
                    $templatefile = 'templates/e3backup_dashboard';
                    $viewVars = require 'pages/e3backup_dashboard.php';
                    break;
            }
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
            $templatefile = 'templates/users_v2';
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

        case 'oauth_google_start':
            require 'pages/oauth_google_start.php';
            break;

        case 'oauth_google_callback':
            require 'pages/oauth_google_callback.php';
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
                case 'cloudbackup_agents':
                    $pagetitle = 'Backup Agents';
                    $templatefile = 'templates/cloudbackup_agents';
                    $viewVars = require 'pages/cloudbackup_agents.php';
                    break;
                case 'cloudnas':
                    $pagetitle = 'Cloud NAS';
                    $templatefile = 'templates/cloudnas';
                    $viewVars = require 'pages/cloudnas.php';
                    break;
                case 'cloudbackup_settings':
                    $pagetitle = 'Backup Settings';
                    $templatefile = 'templates/cloudbackup_settings';
                    $viewVars = require 'pages/cloudbackup_settings.php';
                    break;
                case 'cloudbackup_hyperv':
                    // Redirect to e3backup hyperv page
                    $jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : '';
                    $redirectUrl = 'index.php?m=cloudstorage&page=e3backup&view=hyperv';
                    if ($jobId) $redirectUrl .= '&job_id=' . $jobId;
                    header('Location: ' . $redirectUrl);
                    exit;
                case 'cloudbackup_hyperv_restore':
                    // Redirect to e3backup hyperv restore page
                    $vmId = isset($_GET['vm_id']) ? (int)$_GET['vm_id'] : '';
                    $redirectUrl = 'index.php?m=cloudstorage&page=e3backup&view=hyperv_restore';
                    if ($vmId) $redirectUrl .= '&vm_id=' . $vmId;
                    header('Location: ' . $redirectUrl);
                    exit;
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
        'vars' => array_merge($viewVars ?? [], [
            'clientGroupId' => $clientGroupId,
            'e3Allowed' => $e3Allowed,
        ]),
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
    // Entry point for the Cloud Storage addon in the WHMCS admin area.
    // Render a simple overview + navigation when no specific action is requested.
    $action = $_REQUEST['action'] ?? '';
    $baseUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage';

    switch ($action) {
        case 'cloudbackup_admin':
            require_once __DIR__ . '/pages/admin/cloudbackup_admin.php';
            cloudstorage_admin_cloudbackup($vars);
            break;
        case 'deprovision':
            require_once __DIR__ . '/pages/admin/deprovision.php';
            cloudstorage_admin_deprovision($vars);
            break;
        case 'reconcile':
            require_once __DIR__ . '/pages/admin/reconcile.php';
            cloudstorage_admin_reconcile($vars);
            break;
        case 'bucket_monitor':
            require_once __DIR__ . '/pages/admin/bucket_monitor.php';
            cloudstorage_admin_bucket_monitor($vars);
            break;
        default:
            // Default overview / entry page with navigation tabs
            echo '<div class="content-padded">';
            echo '<h2 class="page-title">e3 Object Storage</h2>';
            echo '<p class="text-muted">Use the tabs below to access Cloud Storage administration tools.</p>';

            echo '<ul class="nav nav-tabs" style="margin-bottom:15px;">';
            echo '  <li class="active"><a href="' . htmlspecialchars($baseUrl) . '">Overview</a></li>';
            echo '  <li><a href="' . htmlspecialchars($baseUrl . '&action=bucket_monitor') . '">Cloud Storage Bucket Monitor</a></li>';
            echo '  <li><a href="' . htmlspecialchars($baseUrl . '&action=cloudbackup_admin') . '">Cloud Backup Admin</a></li>';
            echo '  <li><a href="' . htmlspecialchars($baseUrl . '&action=deprovision') . '">Deprovision Cloud Storage Customer</a></li>';
            echo '  <li><a href="' . htmlspecialchars($baseUrl . '&action=reconcile') . '">Reconciliation</a></li>';
            echo '</ul>';

            echo '<div class="panel panel-default">';
            echo '  <div class="panel-heading"><i class="fa fa-database"></i> Overview</div>';
            echo '  <div class="panel-body">';
            echo '      <p>This module provides e3 Object Storage management for WHMCS administrators:</p>';
            echo '      <ul>';
            echo '          <li>Cloud Storage Bucket Monitor &mdash; view bucket sizes, growth, and ownership.</li>';
            echo '          <li>Cloud Backup Admin &mdash; manage e3 Cloud Backup jobs and agents.</li>';
            echo '          <li>Deprovision Cloud Storage Customer &mdash; safely remove a customer&apos;s buckets and RGW users.</li>';
            echo '          <li>Reconciliation &mdash; identify RGW buckets/users that don&apos;t map to an active WHMCS e3 service.</li>';
            echo '      </ul>';
            echo '      <p>Select a tab above to get started.</p>';
            echo '  </div>';
            echo '</div>';
            echo '</div>';
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
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=deprovision" class="list-group-item">
            <i class="fa fa-user-times"></i> Deprovision Customer
        </a>
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=reconcile" class="list-group-item">
            <i class="fa fa-exchange"></i> Reconciliation
        </a>
    </div>';
    
    return $sidebar;
}

/**
 * Return how many seconds trial SSO sessions should remain valid.
 *
 * @return int
 */
function cloudstorage_trial_sso_duration(): int
{
    return 7200;
}

/**
 * Persist trial SSO metadata so we can re-issue login tokens for the welcome page.
 *
 * @param int $clientId
 *
 * @return void
 */
function cloudstorage_set_trial_sso_session(int $clientId): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['cloudstorage_trial_sso'] = [
        'client_id'  => $clientId,
        'expires_at' => time() + cloudstorage_trial_sso_duration(),
    ];
}

/**
 * Read the persisted trial SSO metadata if it is still fresh.
 *
 * @return array|null
 */
function cloudstorage_get_trial_sso_session(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $data = $_SESSION['cloudstorage_trial_sso'] ?? null;
    if (!is_array($data)) {
        return null;
    }
    $clientId = (int) ($data['client_id'] ?? 0);
    $expiresAt = (int) ($data['expires_at'] ?? 0);
    if ($clientId <= 0 || $expiresAt < time()) {
        cloudstorage_clear_trial_sso_session();
        return null;
    }
    return [
        'client_id'  => $clientId,
        'expires_at' => $expiresAt,
    ];
}

/**
 * Update the TTL for the persisted trial SSO session.
 *
 * @return void
 */
function cloudstorage_refresh_trial_sso_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['cloudstorage_trial_sso']['client_id'])) {
        $_SESSION['cloudstorage_trial_sso']['expires_at'] = time() + cloudstorage_trial_sso_duration();
    }
}

/**
 * Remove any cached trial SSO metadata.
 *
 * @return void
 */
function cloudstorage_clear_trial_sso_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['cloudstorage_trial_sso']);
}

/**
 * Attempt to rebuild a brief SSO session for the welcome page.
 *
 * @return string|null
 */
function cloudstorage_get_welcome_sso_redirect(): ?string
{
    $session = cloudstorage_get_trial_sso_session();
    if (!$session) {
        return null;
    }
    $adminUser = 'API';
    try {
        $ssoResult = localAPI('CreateSsoToken', [
            'client_id'         => $session['client_id'],
            'destination'       => 'sso:custom_redirect',
            'sso_redirect_path' => 'index.php?m=cloudstorage&page=welcome',
        ], $adminUser);
        if (($ssoResult['result'] ?? '') === 'success' && !empty($ssoResult['redirect_url'])) {
            cloudstorage_refresh_trial_sso_session();
            return $ssoResult['redirect_url'];
        }
        try {
            logModuleCall('cloudstorage', 'welcome_sso_redirect_failed', ['session' => $session], $ssoResult);
        } catch (\Throwable $_) {}
    } catch (\Throwable $e) {
        try {
            logModuleCall('cloudstorage', 'welcome_sso_exception', ['session' => $session], $e->getMessage());
        } catch (\Throwable $_) {}
    }
    cloudstorage_clear_trial_sso_session();
    return null;
}

