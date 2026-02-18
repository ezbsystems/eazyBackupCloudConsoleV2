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
        'version' => '2.1.10',
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
            // Ceph Pool Monitor (capacity forecasting)
            'ceph_pool_monitor_enabled' => [
                'FriendlyName' => 'Enable Ceph Pool Monitor',
                'Type' => 'yesno',
                'Description' => 'If enabled, a cron can collect Ceph pool usage for forecasting (recommended ON if this WHMCS host has access to Ceph CLI).',
            ],
            'ceph_pool_monitor_pool_name' => [
                'FriendlyName' => 'Ceph Pool Name to Monitor',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'default.rgw.buckets.data',
                'Description' => 'Ceph pool name to track for capacity forecasting (e.g., default.rgw.buckets.data).',
            ],
            'ceph_cli_path' => [
                'FriendlyName' => 'Ceph CLI Path',
                'Type' => 'text',
                'Size' => '191',
                'Default' => '/usr/bin/ceph',
                'Description' => 'Path to the ceph CLI binary on this server (used by the pool monitor cron).',
            ],
            'ceph_cli_args' => [
                'FriendlyName' => 'Ceph CLI Extra Args',
                'Type' => 'text',
                'Size' => '191',
                'Description' => 'Optional extra args for ceph CLI (space-separated). Example: --conf=/etc/ceph/ceph.conf --name=client.admin --keyring=/etc/ceph/ceph.client.admin.keyring',
            ],
            'ceph_pool_monitor_source' => [
                'FriendlyName' => 'Ceph Pool Monitor Source',
                'Type' => 'dropdown',
                'Options' => [
                    'cli' => 'Ceph CLI (local)',
                    'prometheus' => 'Prometheus (Ceph metrics)',
                ],
                'Default' => 'cli',
                'Description' => 'Select how pool usage should be collected. Prometheus is recommended when Ceph CLI is not available on this host.',
            ],
            'prometheus_base_url' => [
                'FriendlyName' => 'Prometheus Base URL',
                'Type' => 'text',
                'Size' => '191',
                'Description' => 'Base URL for the Prometheus *server API* (must support /api/v1/query and /api/v1/query_range). Example: https://prometheus.internal:9090. Do NOT use an exporter /metrics URL (like :9283).',
            ],
            'prometheus_bearer_token' => [
                'FriendlyName' => 'Prometheus Bearer Token',
                'Type' => 'password',
                'Size' => '191',
                'Description' => 'Optional bearer token for Prometheus API authentication.',
            ],
            'prometheus_basic_auth_user' => [
                'FriendlyName' => 'Prometheus Basic Auth User',
                'Type' => 'text',
                'Size' => '191',
                'Description' => 'Optional basic auth username for Prometheus.',
            ],
            'prometheus_basic_auth_pass' => [
                'FriendlyName' => 'Prometheus Basic Auth Password',
                'Type' => 'password',
                'Size' => '191',
                'Description' => 'Optional basic auth password for Prometheus.',
            ],
            'prometheus_verify_tls' => [
                'FriendlyName' => 'Prometheus Verify TLS',
                'Type' => 'yesno',
                'Description' => 'If enabled, TLS certificates are verified for HTTPS Prometheus URLs. Disable only for internal/self-signed endpoints.',
            ],
            'prometheus_pool_used_query' => [
                'FriendlyName' => 'Prometheus Pool Used Query',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'ceph_pool_stored{pool="{{pool}}"}',
                'Description' => 'PromQL returning pool used bytes. Use {{pool}} placeholder for pool name. If your exporter differs, adjust this.',
            ],
            'prometheus_pool_max_avail_query' => [
                'FriendlyName' => 'Prometheus Pool Max Avail Query',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'ceph_pool_max_avail{pool="{{pool}}"}',
                'Description' => 'PromQL returning pool max available bytes. Use {{pool}} placeholder for pool name.',
            ],
            'prometheus_backfill_days' => [
                'FriendlyName' => 'Prometheus Backfill Days',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '90',
                'Description' => 'When the pool history table is empty, the cron will backfill this many days from Prometheus into ceph_pool_usage_history.',
            ],
            'prometheus_backfill_step_seconds' => [
                'FriendlyName' => 'Prometheus Backfill Step (seconds)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '3600',
                'Description' => 'Prometheus query_range step size in seconds for backfill (e.g., 3600 = hourly).',
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
            'object_lock_force_delete_email_template' => [
                'FriendlyName' => 'Object Lock Force Delete Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'Select the WHMCS email template from the General category to notify customers when a Governance bypass (force delete) is requested.',
            ],
            'object_lock_force_delete_internal_email' => [
                'FriendlyName' => 'Object Lock Force Delete Internal Email',
                'Type' => 'text',
                'Size' => '191',
                'Description' => 'Optional internal notification recipient for force-delete requests (e.g., support@yourdomain.tld).',
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
            'recovery_media_broad_bundle_url' => [
                'FriendlyName' => 'Recovery Media Broad Driver Bundle URL',
                'Type' => 'text',
                'Size' => '250',
                'Description' => 'Optional public URL to a ZIP containing broad fallback drivers for Recovery Media builds.'
            ],
            'recovery_media_broad_bundle_sha256' => [
                'FriendlyName' => 'Recovery Media Broad Driver Bundle SHA256',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'Optional SHA256 checksum (hex) for the broad driver bundle ZIP.'
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
                $table->string('ceph_uid', 191)->nullable(); // RGW-safe user id (no email chars)
                $table->unsignedInteger('parent_id')->nullable();
                // NOTE: tenant_id values are 12-digit numeric strings (e.g. 100000000000..999999999999)
                // which do NOT fit in INT UNSIGNED. Use BIGINT UNSIGNED.
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->tinyInteger('is_active')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('deleted_at')->nullable();

                $table->index('is_active');
                $table->index('ceph_uid');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_users table', [], []);
        }

        // Ensure newer columns exist on s3_users (common on deactivate/activate where table is kept)
        if (Capsule::schema()->hasTable('s3_users')) {
            $s3UserColDefs = [
                'ceph_uid'   => function ($table) { $table->string('ceph_uid', 191)->nullable()->after('username'); },
                'is_active'  => function ($table) { $table->tinyInteger('is_active')->default(1)->after('tenant_id'); },
                'is_system_managed' => function ($table) { $table->tinyInteger('is_system_managed')->default(0)->after('is_active'); },
                'system_key' => function ($table) { $table->string('system_key', 64)->nullable()->after('is_system_managed'); },
                'manage_locked' => function ($table) { $table->tinyInteger('manage_locked')->default(0)->after('system_key'); },
                'deleted_at' => function ($table) { $table->timestamp('deleted_at')->nullable()->after('created_at'); },
            ];
            foreach ($s3UserColDefs as $col => $adder) {
                if (!Capsule::schema()->hasColumn('s3_users', $col)) {
                    try {
                        Capsule::schema()->table('s3_users', function ($table) use ($adder) {
                            $adder($table);
                        });
                        logModuleCall('cloudstorage', 'activate', [], "Ensured {$col} on s3_users", [], []);
                    } catch (\Throwable $e) {
                        logModuleCall('cloudstorage', "activate_ensure_s3_users_{$col}", [], $e->getMessage(), [], []);
                    }
                }
            }
            // Ensure indexes exist (best-effort; ignore if they already exist)
            try {
                if (!Capsule::schema()->hasColumn('s3_users', 'is_active')) {
                    // Column must exist for index, skip
                } else {
                    Capsule::schema()->table('s3_users', function ($table) {
                        $table->index('is_active');
                    });
                }
            } catch (\Throwable $e) { /* index already exists */ }
            try {
                if (Capsule::schema()->hasColumn('s3_users', 'ceph_uid')) {
                    Capsule::schema()->table('s3_users', function ($table) {
                        $table->index('ceph_uid');
                    });
                }
            } catch (\Throwable $e) { /* index already exists */ }
            try {
                if (Capsule::schema()->hasColumn('s3_users', 'manage_locked')) {
                    Capsule::schema()->table('s3_users', function ($table) {
                        $table->index('manage_locked');
                    });
                }
            } catch (\Throwable $e) { /* index already exists */ }
            try {
                if (Capsule::schema()->hasColumn('s3_users', 'system_key')) {
                    Capsule::schema()->table('s3_users', function ($table) {
                        $table->index('system_key');
                    });
                }
            } catch (\Throwable $e) { /* index already exists */ }
            try {
                if (Capsule::schema()->hasColumn('s3_users', 'parent_id') && Capsule::schema()->hasColumn('s3_users', 'system_key')) {
                    Capsule::schema()->table('s3_users', function ($table) {
                        $table->index(['parent_id', 'system_key'], 'idx_s3_users_parent_system_key');
                    });
                }
            } catch (\Throwable $e) { /* index already exists */ }
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
            // Track whether the key was explicitly created by the user via UI (vs auto-provisioned).
            $table->tinyInteger('is_user_generated')->default(0);

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
                // Request/audit metadata (client-initiated deletion UX)
                $table->string('requested_action', 32)->default('delete'); // delete|force_delete|empty
                $table->unsignedInteger('requested_by_client_id')->nullable();
                $table->unsignedInteger('requested_by_user_id')->nullable();
                $table->unsignedInteger('requested_by_contact_id')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->string('request_ip', 64)->nullable();
                $table->text('request_ua')->nullable();
                // Object Lock scheduling fields
                $table->timestamp('retry_after')->nullable();
                $table->string('blocked_reason', 64)->nullable(); // legal_hold|compliance_retention|governance_retention|unknown
                $table->bigInteger('earliest_retain_until_ts')->nullable(); // epoch seconds
                $table->text('audit_json')->nullable();
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
                $table->index(['bucket_name', 'status']);
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

            // Ensure request/audit + scheduling columns for client-initiated delete UX
            $deleteReqCols = [
                'requested_action' => function ($table) { $table->string('requested_action', 32)->default('delete')->after('bucket_name'); },
                'requested_by_client_id' => function ($table) { $table->unsignedInteger('requested_by_client_id')->nullable()->after('requested_action'); },
                'requested_by_user_id' => function ($table) { $table->unsignedInteger('requested_by_user_id')->nullable()->after('requested_by_client_id'); },
                'requested_by_contact_id' => function ($table) { $table->unsignedInteger('requested_by_contact_id')->nullable()->after('requested_by_user_id'); },
                'requested_at' => function ($table) { $table->timestamp('requested_at')->nullable()->after('requested_by_contact_id'); },
                'request_ip' => function ($table) { $table->string('request_ip', 64)->nullable()->after('requested_at'); },
                'request_ua' => function ($table) { $table->text('request_ua')->nullable()->after('request_ip'); },
                'retry_after' => function ($table) { $table->timestamp('retry_after')->nullable()->after('request_ua'); },
                'blocked_reason' => function ($table) { $table->string('blocked_reason', 64)->nullable()->after('retry_after'); },
                'earliest_retain_until_ts' => function ($table) { $table->bigInteger('earliest_retain_until_ts')->nullable()->after('blocked_reason'); },
                'audit_json' => function ($table) { $table->text('audit_json')->nullable()->after('earliest_retain_until_ts'); },
            ];
            foreach ($deleteReqCols as $col => $adder) {
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
                    $table->index(['bucket_name', 'status']);
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

        // Ceph pool usage history (for capacity forecasting)
        if (!Capsule::schema()->hasTable('ceph_pool_usage_history')) {
            Capsule::schema()->create('ceph_pool_usage_history', function ($table) {
                $table->bigIncrements('id');
                $table->string('pool_name', 191);
                $table->bigInteger('used_bytes')->default(0);
                $table->bigInteger('max_avail_bytes')->default(0);
                $table->bigInteger('capacity_bytes')->default(0);
                $table->decimal('percent_used', 8, 4)->default(0);
                $table->timestamp('collected_at')->useCurrent();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['pool_name', 'collected_at']);
                $table->index(['collected_at']);
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
            $table->unsignedInteger('tenant_id')->nullable();
            $table->string('repository_id', 64)->nullable();
            $table->unsignedInteger('s3_user_id');
            $table->string('name', 191);
            $table->enum('source_type', ['s3_compatible', 'aws', 'sftp', 'google_drive', 'dropbox', 'smb', 'nas', 'local_agent'])->default('s3_compatible');
            $table->string('source_display_name', 191);
            $table->mediumText('source_config_enc');
            $table->string('source_path', 1024);
            $table->json('source_paths_json')->nullable();
            $table->unsignedInteger('dest_bucket_id');
            $table->string('dest_prefix', 1024);
            $table->enum('backup_mode', ['sync', 'archive'])->default('sync');
            $table->enum('schedule_type', ['manual', 'hourly', 'daily', 'weekly', 'cron'])->default('manual');
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
            $table->index('tenant_id');
            $table->index('repository_id');
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
            $table->unsignedInteger('tenant_id')->nullable();
            $table->string('repository_id', 64)->nullable();
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
            $table->index('tenant_id');
            $table->index('repository_id');
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

        // Cloud Backup restore points table (persistent restore registry)
        if (!Capsule::schema()->hasTable('s3_cloudbackup_restore_points')) {
            Capsule::schema()->create('s3_cloudbackup_restore_points', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->string('repository_id', 64)->nullable();
                $table->unsignedInteger('tenant_user_id')->nullable();
                $table->unsignedInteger('agent_id')->nullable();
                $table->unsignedInteger('job_id')->nullable();
                $table->string('job_name', 191)->nullable();
                $table->unsignedBigInteger('run_id')->nullable();
                $table->string('run_uuid', 36)->nullable();
                $table->string('engine', 32)->nullable();
                $table->string('status', 32)->nullable();
                $table->string('manifest_id', 191)->nullable();
                $table->string('source_type', 32)->nullable();
                $table->string('source_display_name', 191)->nullable();
                $table->string('source_path', 1024)->nullable();
                $table->string('dest_type', 32)->nullable();
                $table->unsignedInteger('dest_bucket_id')->nullable();
                $table->string('dest_prefix', 1024)->nullable();
                $table->string('dest_local_path', 1024)->nullable();
                $table->unsignedInteger('s3_user_id')->nullable();
                $table->unsignedInteger('hyperv_vm_id')->nullable();
                $table->string('hyperv_vm_name', 191)->nullable();
                $table->string('hyperv_backup_type', 32)->nullable();
                $table->unsignedBigInteger('hyperv_backup_point_id')->nullable();
                $table->mediumText('disk_manifests_json')->nullable();
                $table->mediumText('disk_layout_json')->nullable();
                $table->unsignedBigInteger('disk_total_bytes')->nullable();
                $table->unsignedBigInteger('disk_used_bytes')->nullable();
                $table->string('disk_boot_mode', 16)->nullable(); // uefi|bios|unknown
                $table->string('disk_partition_style', 16)->nullable(); // gpt|mbr|unknown
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('finished_at')->nullable();

                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('repository_id');
                $table->index('agent_id');
                $table->index('manifest_id');
                $table->index('run_id');
                $table->index('hyperv_vm_id');
                $table->index('hyperv_backup_point_id');
                $table->index('disk_partition_style');
                $table->unique(['client_id', 'manifest_id'], 'uniq_restore_manifest');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_restore_points table', [], []);
        }
        if (Capsule::schema()->hasTable('s3_cloudbackup_restore_points') && !Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', 'repository_id')) {
            Capsule::schema()->table('s3_cloudbackup_restore_points', function ($table) {
                $table->string('repository_id', 64)->nullable()->after('tenant_id');
                $table->index('repository_id');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Added repository_id to s3_cloudbackup_restore_points', [], []);
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

        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            Capsule::schema()->create('s3_backup_users', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->string('username', 191);
                $table->string('password_hash', 255);
                $table->string('email', 255);
                $table->enum('status', ['active', 'disabled'])->default('active');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique(['client_id', 'tenant_id', 'username'], 'uniq_backup_users_scope_username');
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('status');
                $table->index('email');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_backup_users table', [], []);
        }

        if (Capsule::schema()->hasTable('s3_backup_users')) {
            $backupUserColDefs = [
                'client_id' => function ($table) { $table->unsignedInteger('client_id'); },
                'tenant_id' => function ($table) { $table->unsignedInteger('tenant_id')->nullable(); },
                'username' => function ($table) { $table->string('username', 191); },
                'password_hash' => function ($table) { $table->string('password_hash', 255); },
                'email' => function ($table) { $table->string('email', 255); },
                'status' => function ($table) { $table->enum('status', ['active', 'disabled'])->default('active'); },
                'created_at' => function ($table) { $table->timestamp('created_at')->useCurrent(); },
                'updated_at' => function ($table) { $table->timestamp('updated_at')->useCurrent(); },
            ];
            foreach ($backupUserColDefs as $col => $adder) {
                if (!Capsule::schema()->hasColumn('s3_backup_users', $col)) {
                    try {
                        Capsule::schema()->table('s3_backup_users', function ($table) use ($adder) {
                            $adder($table);
                        });
                        logModuleCall('cloudstorage', 'activate', [], "Ensured {$col} on s3_backup_users", [], []);
                    } catch (\Throwable $e) {
                        logModuleCall('cloudstorage', "activate_ensure_s3_backup_users_{$col}", [], $e->getMessage(), [], []);
                    }
                }
            }
            try {
                Capsule::schema()->table('s3_backup_users', function ($table) {
                    $table->unique(['client_id', 'tenant_id', 'username'], 'uniq_backup_users_scope_username');
                });
            } catch (\Throwable $e) { /* index exists */ }
            try {
                Capsule::schema()->table('s3_backup_users', function ($table) {
                    $table->index('client_id');
                });
            } catch (\Throwable $e) { /* index exists */ }
            try {
                Capsule::schema()->table('s3_backup_users', function ($table) {
                    $table->index('tenant_id');
                });
            } catch (\Throwable $e) { /* index exists */ }
            try {
                Capsule::schema()->table('s3_backup_users', function ($table) {
                    $table->index('status');
                });
            } catch (\Throwable $e) { /* index exists */ }
            try {
                Capsule::schema()->table('s3_backup_users', function ($table) {
                    $table->index('email');
                });
            } catch (\Throwable $e) { /* index exists */ }
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

        if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
            Capsule::schema()->create('s3_cloudbackup_recovery_tokens', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('tenant_user_id')->nullable();
                $table->unsignedBigInteger('restore_point_id');
                $table->string('token', 32); // non-sensitive legacy identifier
                $table->string('token_hash', 64)->nullable();
                $table->string('description', 255)->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('used_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->unsignedInteger('exchange_count')->default(0);
                $table->unsignedInteger('failed_attempts')->default(0);
                $table->dateTime('last_failed_at')->nullable();
                $table->string('last_failed_ip', 45)->nullable();
                $table->dateTime('locked_until')->nullable();
                $table->string('session_token', 64)->nullable();
                $table->dateTime('session_expires_at')->nullable();
                $table->unsignedBigInteger('session_run_id')->nullable();
                $table->string('created_ip', 45)->nullable();
                $table->string('created_user_agent', 255)->nullable();
                $table->dateTime('exchanged_at')->nullable();
                $table->string('exchanged_ip', 45)->nullable();
                $table->string('exchanged_user_agent', 255)->nullable();
                $table->dateTime('started_at')->nullable();
                $table->string('started_ip', 45)->nullable();
                $table->string('started_user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique('token');
                $table->unique('token_hash');
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('restore_point_id');
                $table->index('expires_at');
                $table->index('used_at');
                $table->index('session_token');
                $table->index('locked_until');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_recovery_tokens table', [], []);
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_exchange_limits')) {
            Capsule::schema()->create('s3_cloudbackup_recovery_exchange_limits', function ($table) {
                $table->bigIncrements('id');
                $table->string('ip_hash', 64);
                $table->unsignedInteger('attempt_count')->default(0);
                $table->dateTime('window_started_at')->nullable();
                $table->dateTime('locked_until')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->unique('ip_hash');
                $table->index('locked_until');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_recovery_exchange_limits table', [], []);
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
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_version')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->string('agent_version', 64)->nullable()->after('device_name');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_version to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_os')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->string('agent_os', 32)->nullable()->after('agent_version');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_os to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_arch')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->string('agent_arch', 16)->nullable()->after('agent_os');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_arch to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_build')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->string('agent_build', 64)->nullable()->after('agent_arch');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_build to s3_cloudbackup_agents', [], []);
            }
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'metadata_updated_at')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->dateTime('metadata_updated_at')->nullable()->after('agent_build');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added metadata_updated_at to s3_cloudbackup_agents', [], []);
            }
        }

        // Add source_connection_id to jobs if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->unsignedInteger('tenant_id')->nullable()->after('client_id');
                    $table->index('tenant_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added tenant_id to s3_cloudbackup_jobs', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->string('repository_id', 64)->nullable()->after('tenant_id');
                    $table->index('repository_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added repository_id to s3_cloudbackup_jobs', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_connection_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->unsignedInteger('source_connection_id')->nullable()->after('source_config_enc');
                    $table->index('source_connection_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added source_connection_id to s3_cloudbackup_jobs', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_paths_json')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->json('source_paths_json')->nullable()->after('source_path');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added source_paths_json to s3_cloudbackup_jobs', [], []);
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
            // Local agent bandwidth limit (separate from cloud job bandwidth_limit_kbps)
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'local_bandwidth_limit_kbps')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->unsignedInteger('local_bandwidth_limit_kbps')->nullable()->default(0)->after('compression');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added local_bandwidth_limit_kbps to s3_cloudbackup_jobs', [], []);
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
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'tenant_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->unsignedInteger('tenant_id')->nullable()->after('job_id');
                    $table->index('tenant_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added tenant_id to s3_cloudbackup_runs', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'repository_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->string('repository_id', 64)->nullable()->after('tenant_id');
                    $table->index('repository_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added repository_id to s3_cloudbackup_runs', [], []);
            }
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

        if (!Capsule::schema()->hasTable('s3_cloudbackup_agent_destinations')) {
            Capsule::schema()->create('s3_cloudbackup_agent_destinations', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('agent_id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('s3_user_id');
                $table->unsignedInteger('dest_bucket_id');
                $table->string('root_prefix', 1024);
                $table->tinyInteger('is_locked')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique('agent_id');
                $table->unique(['dest_bucket_id', 'root_prefix'], 'uniq_cloudbackup_dest_bucket_prefix');
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('s3_user_id');
                $table->index('dest_bucket_id');
                $table->foreign('agent_id')->references('id')->on('s3_cloudbackup_agents')->onDelete('cascade');
                $table->foreign('s3_user_id')->references('id')->on('s3_users')->onDelete('cascade');
                $table->foreign('dest_bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_agent_destinations table', [], []);
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_repositories')) {
            Capsule::schema()->create('s3_cloudbackup_repositories', function ($table) {
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

                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('tenant_user_id');
                $table->index('bucket_id');
                $table->index('status');
                $table->index(['client_id', 'tenant_id', 'bucket_id']);
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_repositories table', [], []);
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_repository_keys')) {
            Capsule::schema()->create('s3_cloudbackup_repository_keys', function ($table) {
                $table->bigIncrements('id');
                $table->string('repository_ref', 64);
                $table->unsignedInteger('key_version')->default(1);
                $table->string('wrap_alg', 64)->default('aes-256-cbc');
                $table->mediumText('wrapped_repo_secret');
                $table->string('kek_ref', 191)->nullable();
                $table->enum('mode', ['managed_recovery', 'strict_customer_managed'])->default('managed_recovery');
                $table->timestamp('created_at')->useCurrent();
                $table->unsignedInteger('created_by')->nullable();

                $table->index('repository_ref');
                $table->index('key_version');
                $table->index('mode');
                $table->unique(['repository_ref', 'key_version'], 'uniq_repository_key_version');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_repository_keys table', [], []);
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

        // Add agent_id to run_commands for browse/discovery commands (not tied to a run)
        if (Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
                Capsule::schema()->table('s3_cloudbackup_run_commands', function ($table) {
                    $table->unsignedInteger('agent_id')->nullable()->after('run_id');
                    $table->index('agent_id', 'idx_agent_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_id to s3_cloudbackup_run_commands', [], []);
            }
            // Make run_id nullable - browse/discovery commands don't have a run_id
            // Note: This requires raw SQL as Laravel doesn't support modifying nullable on existing columns easily
            try {
                Capsule::statement('ALTER TABLE s3_cloudbackup_run_commands MODIFY run_id BIGINT UNSIGNED NULL');
                logModuleCall('cloudstorage', 'activate', [], 'Made run_id nullable in s3_cloudbackup_run_commands', [], []);
            } catch (\Throwable $e) {
                // Ignore if already nullable or if there's a constraint issue
                logModuleCall('cloudstorage', 'activate', [], 'run_id nullable modification skipped: ' . $e->getMessage(), [], []);
            }
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
                // Storage tier: 'trial_limited' (free, 1TiB cap) or 'trial_unlimited' (CC provided, no cap)
                $table->string('storage_tier', 32)->nullable();
                // Trial status flag for admin visibility: 'trial' or 'paid'
                $table->string('trial_status', 16)->default('trial');
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

        // Phase 1 guardrails: schema additions for system-managed users, tenant snapshots, and agent destinations.
        try {
            $schema = \WHMCS\Database\Capsule::schema();

            if ($schema->hasTable('s3_users')) {
                if (!$schema->hasColumn('s3_users', 'is_system_managed')) {
                    $schema->table('s3_users', function ($table) {
                        $table->tinyInteger('is_system_managed')->default(0)->after('is_active');
                    });
                }
                if (!$schema->hasColumn('s3_users', 'system_key')) {
                    $schema->table('s3_users', function ($table) {
                        $table->string('system_key', 64)->nullable()->after('is_system_managed');
                    });
                }
                if (!$schema->hasColumn('s3_users', 'manage_locked')) {
                    $schema->table('s3_users', function ($table) {
                        $table->tinyInteger('manage_locked')->default(0)->after('system_key');
                    });
                }

                try {
                    if ($schema->hasColumn('s3_users', 'manage_locked')) {
                        $schema->table('s3_users', function ($table) { $table->index('manage_locked'); });
                    }
                } catch (\Throwable $__) {}
                try {
                    if ($schema->hasColumn('s3_users', 'system_key')) {
                        $schema->table('s3_users', function ($table) { $table->index('system_key'); });
                    }
                } catch (\Throwable $__) {}
                try {
                    if ($schema->hasColumn('s3_users', 'parent_id') && $schema->hasColumn('s3_users', 'system_key')) {
                        $schema->table('s3_users', function ($table) { $table->index(['parent_id', 'system_key'], 'idx_s3_users_parent_system_key'); });
                    }
                } catch (\Throwable $__) {}
            }

            if ($schema->hasTable('s3_cloudbackup_jobs') && !$schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id')) {
                $schema->table('s3_cloudbackup_jobs', function ($table) {
                    $table->unsignedInteger('tenant_id')->nullable()->after('client_id');
                    $table->index('tenant_id');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added tenant_id to s3_cloudbackup_jobs', [], []);
            }

            if ($schema->hasTable('s3_cloudbackup_runs') && !$schema->hasColumn('s3_cloudbackup_runs', 'tenant_id')) {
                $schema->table('s3_cloudbackup_runs', function ($table) {
                    $table->unsignedInteger('tenant_id')->nullable()->after('job_id');
                    $table->index('tenant_id');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added tenant_id to s3_cloudbackup_runs', [], []);
            }

            if ($schema->hasTable('s3_cloudbackup_jobs') && !$schema->hasColumn('s3_cloudbackup_jobs', 'repository_id')) {
                $schema->table('s3_cloudbackup_jobs', function ($table) {
                    $table->string('repository_id', 64)->nullable()->after('tenant_id');
                    $table->index('repository_id');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added repository_id to s3_cloudbackup_jobs', [], []);
            }

            if ($schema->hasTable('s3_cloudbackup_runs') && !$schema->hasColumn('s3_cloudbackup_runs', 'repository_id')) {
                $schema->table('s3_cloudbackup_runs', function ($table) {
                    $table->string('repository_id', 64)->nullable()->after('tenant_id');
                    $table->index('repository_id');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added repository_id to s3_cloudbackup_runs', [], []);
            }

            if ($schema->hasTable('s3_cloudbackup_restore_points') && !$schema->hasColumn('s3_cloudbackup_restore_points', 'repository_id')) {
                $schema->table('s3_cloudbackup_restore_points', function ($table) {
                    $table->string('repository_id', 64)->nullable()->after('tenant_id');
                    $table->index('repository_id');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added repository_id to s3_cloudbackup_restore_points', [], []);
            }

            if (!$schema->hasTable('s3_cloudbackup_repositories')) {
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

                    $table->index('client_id');
                    $table->index('tenant_id');
                    $table->index('tenant_user_id');
                    $table->index('bucket_id');
                    $table->index('status');
                    $table->index(['client_id', 'tenant_id', 'bucket_id']);
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_repositories table', [], []);
            }

            if (!$schema->hasTable('s3_cloudbackup_repository_keys')) {
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

                    $table->index('repository_ref');
                    $table->index('key_version');
                    $table->index('mode');
                    $table->unique(['repository_ref', 'key_version'], 'uniq_repository_key_version');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_repository_keys table', [], []);
            }

            if (
                !$schema->hasTable('s3_cloudbackup_agent_destinations')
                && $schema->hasTable('s3_cloudbackup_agents')
                && $schema->hasTable('s3_users')
                && $schema->hasTable('s3_buckets')
            ) {
                $schema->create('s3_cloudbackup_agent_destinations', function ($table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('agent_id');
                    $table->unsignedInteger('client_id');
                    $table->unsignedInteger('tenant_id')->nullable();
                    $table->unsignedInteger('s3_user_id');
                    $table->unsignedInteger('dest_bucket_id');
                    $table->string('root_prefix', 1024);
                    $table->tinyInteger('is_locked')->default(1);
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('updated_at')->useCurrent();

                    $table->unique('agent_id');
                    $table->unique(['dest_bucket_id', 'root_prefix'], 'uniq_cloudbackup_dest_bucket_prefix');
                    $table->index('client_id');
                    $table->index('tenant_id');
                    $table->index('s3_user_id');
                    $table->index('dest_bucket_id');
                    $table->foreign('agent_id')->references('id')->on('s3_cloudbackup_agents')->onDelete('cascade');
                    $table->foreign('s3_user_id')->references('id')->on('s3_users')->onDelete('cascade');
                    $table->foreign('dest_bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_agent_destinations table', [], []);
            }

            // Backfill jobs.tenant_id from current agent mapping where available.
            if (
                $schema->hasTable('s3_cloudbackup_jobs') &&
                $schema->hasTable('s3_cloudbackup_agents') &&
                $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id') &&
                $schema->hasColumn('s3_cloudbackup_jobs', 'agent_id') &&
                $schema->hasColumn('s3_cloudbackup_agents', 'tenant_id')
            ) {
                $updatedJobs = 0;
                $lastJobId = 0;
                $chunk = 500;
                while (true) {
                    $rows = \WHMCS\Database\Capsule::table('s3_cloudbackup_jobs as j')
                        ->join('s3_cloudbackup_agents as a', 'a.id', '=', 'j.agent_id')
                        ->where('j.id', '>', $lastJobId)
                        ->whereNull('j.tenant_id')
                        ->select(['j.id as job_id', 'a.tenant_id as agent_tenant_id'])
                        ->orderBy('j.id', 'asc')
                        ->limit($chunk)
                        ->get();
                    if (!$rows || count($rows) === 0) {
                        break;
                    }
                    foreach ($rows as $row) {
                        $lastJobId = (int) $row->job_id;
                        try {
                            $updatedJobs += (int) \WHMCS\Database\Capsule::table('s3_cloudbackup_jobs')
                                ->where('id', (int) $row->job_id)
                                ->whereNull('tenant_id')
                                ->update(['tenant_id' => $row->agent_tenant_id !== null ? (int) $row->agent_tenant_id : null]);
                        } catch (\Throwable $__) {}
                    }
                }
                logModuleCall('cloudstorage', 'upgrade_backfill_jobs_tenant_id', [], ['updated' => $updatedJobs], [], []);
            }

            // Backfill runs.tenant_id from jobs.tenant_id snapshots.
            if (
                $schema->hasTable('s3_cloudbackup_runs') &&
                $schema->hasTable('s3_cloudbackup_jobs') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'tenant_id') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'job_id') &&
                $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id')
            ) {
                $updatedRuns = 0;
                $lastRunId = 0;
                $chunk = 500;
                while (true) {
                    $rows = \WHMCS\Database\Capsule::table('s3_cloudbackup_runs as r')
                        ->join('s3_cloudbackup_jobs as j', 'j.id', '=', 'r.job_id')
                        ->where('r.id', '>', $lastRunId)
                        ->whereNull('r.tenant_id')
                        ->select(['r.id as run_id', 'j.tenant_id as job_tenant_id'])
                        ->orderBy('r.id', 'asc')
                        ->limit($chunk)
                        ->get();
                    if (!$rows || count($rows) === 0) {
                        break;
                    }
                    foreach ($rows as $row) {
                        $lastRunId = (int) $row->run_id;
                        try {
                            $updatedRuns += (int) \WHMCS\Database\Capsule::table('s3_cloudbackup_runs')
                                ->where('id', (int) $row->run_id)
                                ->whereNull('tenant_id')
                                ->update(['tenant_id' => $row->job_tenant_id !== null ? (int) $row->job_tenant_id : null]);
                        } catch (\Throwable $__) {}
                    }
                }
                logModuleCall('cloudstorage', 'upgrade_backfill_runs_tenant_id', [], ['updated' => $updatedRuns], [], []);
            }

            // Backfill runs.repository_id from jobs.repository_id snapshots.
            if (
                $schema->hasTable('s3_cloudbackup_runs') &&
                $schema->hasTable('s3_cloudbackup_jobs') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'repository_id') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'job_id') &&
                $schema->hasColumn('s3_cloudbackup_jobs', 'repository_id')
            ) {
                $updatedRunRepos = 0;
                $lastRunRepoId = 0;
                $chunk = 500;
                while (true) {
                    $rows = \WHMCS\Database\Capsule::table('s3_cloudbackup_runs as r')
                        ->join('s3_cloudbackup_jobs as j', 'j.id', '=', 'r.job_id')
                        ->where('r.id', '>', $lastRunRepoId)
                        ->whereNull('r.repository_id')
                        ->whereNotNull('j.repository_id')
                        ->select(['r.id as run_id', 'j.repository_id as job_repository_id'])
                        ->orderBy('r.id', 'asc')
                        ->limit($chunk)
                        ->get();
                    if (!$rows || count($rows) === 0) {
                        break;
                    }
                    foreach ($rows as $row) {
                        $lastRunRepoId = (int) $row->run_id;
                        try {
                            $updatedRunRepos += (int) \WHMCS\Database\Capsule::table('s3_cloudbackup_runs')
                                ->where('id', (int) $row->run_id)
                                ->whereNull('repository_id')
                                ->update(['repository_id' => (string) $row->job_repository_id]);
                        } catch (\Throwable $__) {}
                    }
                }
                logModuleCall('cloudstorage', 'upgrade_backfill_runs_repository_id', [], ['updated' => $updatedRunRepos], [], []);
            }

            // Backfill restore_points.repository_id from runs.repository_id snapshots.
            if (
                $schema->hasTable('s3_cloudbackup_restore_points') &&
                $schema->hasTable('s3_cloudbackup_runs') &&
                $schema->hasColumn('s3_cloudbackup_restore_points', 'repository_id') &&
                $schema->hasColumn('s3_cloudbackup_restore_points', 'run_id') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'repository_id')
            ) {
                $updatedRestoreRepos = 0;
                $lastRestoreId = 0;
                $chunk = 500;
                while (true) {
                    $rows = \WHMCS\Database\Capsule::table('s3_cloudbackup_restore_points as rp')
                        ->join('s3_cloudbackup_runs as r', 'r.id', '=', 'rp.run_id')
                        ->where('rp.id', '>', $lastRestoreId)
                        ->whereNull('rp.repository_id')
                        ->whereNotNull('r.repository_id')
                        ->select(['rp.id as restore_id', 'r.repository_id as run_repository_id'])
                        ->orderBy('rp.id', 'asc')
                        ->limit($chunk)
                        ->get();
                    if (!$rows || count($rows) === 0) {
                        break;
                    }
                    foreach ($rows as $row) {
                        $lastRestoreId = (int) $row->restore_id;
                        try {
                            $updatedRestoreRepos += (int) \WHMCS\Database\Capsule::table('s3_cloudbackup_restore_points')
                                ->where('id', (int) $row->restore_id)
                                ->whereNull('repository_id')
                                ->update(['repository_id' => (string) $row->run_repository_id]);
                        } catch (\Throwable $__) {}
                    }
                }
                logModuleCall('cloudstorage', 'upgrade_backfill_restore_points_repository_id', [], ['updated' => $updatedRestoreRepos], [], []);
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'upgrade_phase1_schema_guardrails_fail', [], $e->getMessage(), [], []);
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

                // Track whether the key was explicitly created by the user via UI (vs auto-provisioned).
                if (!$schema->hasColumn('s3_user_access_keys', 'is_user_generated')) {
                    $schema->table('s3_user_access_keys', function ($table) {
                        $table->tinyInteger('is_user_generated')->default(0)->after('created_at');
                    });
                    logModuleCall('cloudstorage', 'upgrade_s3_user_access_keys_add_is_user_generated', [], 'Added is_user_generated to s3_user_access_keys', [], []);
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

        // Add 'hourly' to schedule_type ENUM in s3_cloudbackup_jobs
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            try {
                $databaseName = \WHMCS\Database\Capsule::connection()->getDatabaseName();
                $col = \WHMCS\Database\Capsule::table('information_schema.COLUMNS')
                    ->select(['COLUMN_TYPE'])
                    ->where('TABLE_SCHEMA', $databaseName)
                    ->where('TABLE_NAME', 's3_cloudbackup_jobs')
                    ->where('COLUMN_NAME', 'schedule_type')
                    ->first();
                
                $columnType = strtolower((string) ($col->COLUMN_TYPE ?? ''));
                // Check if 'hourly' is missing from the ENUM
                if ($columnType !== '' && strpos($columnType, 'hourly') === false) {
                    \WHMCS\Database\Capsule::statement("ALTER TABLE `s3_cloudbackup_jobs` MODIFY COLUMN `schedule_type` ENUM('manual','hourly','daily','weekly','cron') NOT NULL DEFAULT 'manual'");
                    logModuleCall('cloudstorage', 'upgrade_schedule_type_add_hourly', [], 'Added hourly to s3_cloudbackup_jobs.schedule_type ENUM', [], []);
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade_schedule_type_add_hourly_fail', [], $e->getMessage(), [], []);
            }
        }

        // Ensure trial selection table exists
        if (!\WHMCS\Database\Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            \WHMCS\Database\Capsule::schema()->create('cloudstorage_trial_selection', function ($table) {
                $table->unsignedInteger('client_id')->primary();
                $table->string('product_choice', 32);
                // Storage tier: 'trial_limited' (free, 1TiB cap) or 'trial_unlimited' (CC provided, no cap)
                $table->string('storage_tier', 32)->nullable();
                // Trial status flag for admin visibility: 'trial' or 'paid'
                $table->string('trial_status', 16)->default('trial');
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('product_choice');
            });
        }

        // Add storage_tier column to cloudstorage_trial_selection if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('cloudstorage_trial_selection', 'storage_tier')) {
                \WHMCS\Database\Capsule::schema()->table('cloudstorage_trial_selection', function ($table) {
                    $table->string('storage_tier', 32)->nullable()->after('product_choice');
                });
                logModuleCall('cloudstorage', 'upgrade_trial_selection_add_storage_tier', [], 'Added storage_tier column', [], []);
            }
        }

        // Add trial_status column to cloudstorage_trial_selection if missing
        if (\WHMCS\Database\Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('cloudstorage_trial_selection', 'trial_status')) {
                \WHMCS\Database\Capsule::schema()->table('cloudstorage_trial_selection', function ($table) {
                    $table->string('trial_status', 16)->default('trial')->after('storage_tier');
                });
                try {
                    \WHMCS\Database\Capsule::table('cloudstorage_trial_selection')
                        ->whereNull('trial_status')
                        ->update(['trial_status' => 'trial']);
                } catch (\Throwable $e) {
                    logModuleCall('cloudstorage', 'upgrade_trial_selection_trial_status_backfill_fail', [], $e->getMessage(), [], []);
                }
                logModuleCall('cloudstorage', 'upgrade_trial_selection_add_trial_status', [], 'Added trial_status column', [], []);
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
                $table->unsignedInteger('tenant_id')->nullable();
                $table->string('repository_id', 64)->nullable();
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
                $table->enum('schedule_type', ['manual', 'hourly', 'daily', 'weekly', 'cron'])->default('manual');
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
                $table->index('tenant_id');
                $table->index('repository_id');
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
                $table->unsignedInteger('tenant_id')->nullable();
                $table->string('repository_id', 64)->nullable();
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
                $table->index('tenant_id');
                $table->index('repository_id');
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
            // Ensure engine enum includes disk_image and hyperv on upgraded installs.
            try {
                $colType = \WHMCS\Database\Capsule::select("SHOW COLUMNS FROM s3_cloudbackup_runs WHERE Field = 'engine'");
                if (!empty($colType)) {
                    $type = strtolower((string) ($colType[0]->Type ?? ''));
                    if (strpos($type, "enum(") !== false && (strpos($type, "'disk_image'") === false || strpos($type, "'hyperv'") === false)) {
                        \WHMCS\Database\Capsule::statement("ALTER TABLE s3_cloudbackup_runs MODIFY COLUMN engine ENUM('sync', 'kopia', 'disk_image', 'hyperv') NOT NULL DEFAULT 'sync'");
                        logModuleCall('cloudstorage', 'upgrade_extend_runs_engine_enum', [], 'Extended runs.engine enum to include disk_image and hyperv', [], []);
                    }
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade_extend_runs_engine_enum_fail', [], $e->getMessage(), [], []);
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

        // Add agent_id to run_commands for browse/discovery commands (not tied to a run)
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_run_commands', function ($table) {
                    $table->unsignedInteger('agent_id')->nullable()->after('run_id');
                    $table->index('agent_id', 'idx_run_cmd_agent_id');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added agent_id to s3_cloudbackup_run_commands', [], []);
            }
            // Make run_id nullable - browse/discovery commands don't have a run_id
            try {
                \WHMCS\Database\Capsule::statement('ALTER TABLE s3_cloudbackup_run_commands MODIFY run_id BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                // Ignore if already nullable
            }
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

        // Ensure restore points table exists for upgraded installs
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_restore_points')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_restore_points', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->string('repository_id', 64)->nullable();
                $table->unsignedInteger('tenant_user_id')->nullable();
                $table->unsignedInteger('agent_id')->nullable();
                $table->unsignedInteger('job_id')->nullable();
                $table->string('job_name', 191)->nullable();
                $table->unsignedBigInteger('run_id')->nullable();
                $table->string('run_uuid', 36)->nullable();
                $table->string('engine', 32)->nullable();
                $table->string('status', 32)->nullable();
                $table->string('manifest_id', 191)->nullable();
                $table->string('source_type', 32)->nullable();
                $table->string('source_display_name', 191)->nullable();
                $table->string('source_path', 1024)->nullable();
                $table->string('dest_type', 32)->nullable();
                $table->unsignedInteger('dest_bucket_id')->nullable();
                $table->string('dest_prefix', 1024)->nullable();
                $table->string('dest_local_path', 1024)->nullable();
                $table->unsignedInteger('s3_user_id')->nullable();
                $table->unsignedInteger('hyperv_vm_id')->nullable();
                $table->string('hyperv_vm_name', 191)->nullable();
                $table->string('hyperv_backup_type', 32)->nullable();
                $table->unsignedBigInteger('hyperv_backup_point_id')->nullable();
                $table->mediumText('disk_manifests_json')->nullable();
                $table->mediumText('disk_layout_json')->nullable();
                $table->unsignedBigInteger('disk_total_bytes')->nullable();
                $table->unsignedBigInteger('disk_used_bytes')->nullable();
                $table->string('disk_boot_mode', 16)->nullable();
                $table->string('disk_partition_style', 16)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('finished_at')->nullable();

                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('repository_id');
                $table->index('agent_id');
                $table->index('manifest_id');
                $table->index('run_id');
                $table->index('hyperv_vm_id');
                $table->index('hyperv_backup_point_id');
                $table->index('disk_partition_style');
                $table->unique(['client_id', 'manifest_id'], 'uniq_restore_manifest');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_restore_points table', [], []);
        }

        // Add disk layout metadata columns on upgrades
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_restore_points')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', 'disk_layout_json')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_restore_points', function ($table) {
                        $table->mediumText('disk_layout_json')->nullable()->after('disk_manifests_json');
                        $table->unsignedBigInteger('disk_total_bytes')->nullable()->after('disk_layout_json');
                        $table->unsignedBigInteger('disk_used_bytes')->nullable()->after('disk_total_bytes');
                        $table->string('disk_boot_mode', 16)->nullable()->after('disk_used_bytes');
                        $table->string('disk_partition_style', 16)->nullable()->after('disk_boot_mode');
                        $table->index('disk_partition_style');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added disk layout columns to s3_cloudbackup_restore_points', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_restore_points_disk_layout_error', [], $e->getMessage(), [], []);
                }
            }
        }

        // Ensure recovery tokens table exists on upgrades
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_recovery_tokens', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('tenant_user_id')->nullable();
                $table->unsignedBigInteger('restore_point_id');
                $table->string('token', 32);
                $table->string('token_hash', 64)->nullable();
                $table->string('description', 255)->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('used_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->unsignedInteger('exchange_count')->default(0);
                $table->unsignedInteger('failed_attempts')->default(0);
                $table->dateTime('last_failed_at')->nullable();
                $table->string('last_failed_ip', 45)->nullable();
                $table->dateTime('locked_until')->nullable();
                $table->string('session_token', 64)->nullable();
                $table->dateTime('session_expires_at')->nullable();
                $table->unsignedBigInteger('session_run_id')->nullable();
                $table->string('created_ip', 45)->nullable();
                $table->string('created_user_agent', 255)->nullable();
                $table->dateTime('exchanged_at')->nullable();
                $table->string('exchanged_ip', 45)->nullable();
                $table->string('exchanged_user_agent', 255)->nullable();
                $table->dateTime('started_at')->nullable();
                $table->string('started_ip', 45)->nullable();
                $table->string('started_user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->unique('token');
                $table->unique('token_hash');
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('restore_point_id');
                $table->index('expires_at');
                $table->index('used_at');
                $table->index('session_token');
                $table->index('locked_until');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_recovery_tokens table', [], []);
        }

        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_recovery_exchange_limits')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_recovery_exchange_limits', function ($table) {
                $table->bigIncrements('id');
                $table->string('ip_hash', 64);
                $table->unsignedInteger('attempt_count')->default(0);
                $table->dateTime('window_started_at')->nullable();
                $table->dateTime('locked_until')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->unique('ip_hash');
                $table->index('locked_until');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_recovery_exchange_limits table', [], []);
        }

        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'token_hash')) {
                $tokenHashAdded = false;
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                        $table->string('token_hash', 64)->nullable()->after('token');
                    });
                    $tokenHashAdded = true;
                } catch (\Throwable $__e) {
                    // Fallback for database engines/installations where doctrine alter with "after" fails.
                    try {
                        \WHMCS\Database\Capsule::statement("ALTER TABLE `s3_cloudbackup_recovery_tokens` ADD COLUMN `token_hash` VARCHAR(64) NULL");
                        $tokenHashAdded = true;
                    } catch (\Throwable $__e2) {
                        logModuleCall('cloudstorage', 'upgrade_add_recovery_token_hash_fail', [], [
                            'primary_error' => $__e->getMessage(),
                            'fallback_error' => $__e2->getMessage(),
                        ], [], []);
                    }
                }
                try {
                    \WHMCS\Database\Capsule::statement("UPDATE `s3_cloudbackup_recovery_tokens` SET `token_hash` = SHA2(UPPER(TRIM(`token`)), 256) WHERE (`token_hash` IS NULL OR `token_hash` = '') AND `token` IS NOT NULL AND `token` != ''");
                } catch (\Throwable $__e) {
                    logModuleCall('cloudstorage', 'upgrade_backfill_recovery_token_hash_fail', [], $__e->getMessage(), [], []);
                }
                if ($tokenHashAdded) {
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added token_hash to s3_cloudbackup_recovery_tokens', [], []);
                }
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'exchange_count')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->unsignedInteger('exchange_count')->default(0)->after('revoked_at');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'failed_attempts')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->unsignedInteger('failed_attempts')->default(0)->after('exchange_count');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'last_failed_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->dateTime('last_failed_at')->nullable()->after('failed_attempts');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'last_failed_ip')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->string('last_failed_ip', 45)->nullable()->after('last_failed_at');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'locked_until')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->dateTime('locked_until')->nullable()->after('last_failed_ip');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'created_ip')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->string('created_ip', 45)->nullable()->after('created_at');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'created_user_agent')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->string('created_user_agent', 255)->nullable()->after('created_ip');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'exchanged_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->dateTime('exchanged_at')->nullable()->after('created_user_agent');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'exchanged_ip')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->string('exchanged_ip', 45)->nullable()->after('exchanged_at');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'exchanged_user_agent')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->string('exchanged_user_agent', 255)->nullable()->after('exchanged_ip');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'started_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->dateTime('started_at')->nullable()->after('exchanged_user_agent');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'started_ip')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->string('started_ip', 45)->nullable()->after('started_at');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'started_user_agent')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->string('started_user_agent', 255)->nullable()->after('started_ip');
                });
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'updated_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_recovery_tokens', function ($table) {
                    $table->timestamp('updated_at')->nullable()->after('started_user_agent');
                });
            }
            try {
                \WHMCS\Database\Capsule::statement("ALTER TABLE `s3_cloudbackup_recovery_tokens` ADD UNIQUE INDEX `uniq_recovery_token_hash` (`token_hash`)");
            } catch (\Throwable $__e) {
                // Ignore if index already exists or cannot be created due to duplicate historical data.
            }
            try {
                \WHMCS\Database\Capsule::statement("ALTER TABLE `s3_cloudbackup_recovery_tokens` ADD INDEX `idx_recovery_locked_until` (`locked_until`)");
            } catch (\Throwable $__e) {
                // Ignore if index already exists.
            }
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
            // Add ceph_uid for RGW-safe user IDs (email usernames can be rejected by RGW dashboard UI)
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_users', 'ceph_uid')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_users', function ($table) {
                        $table->string('ceph_uid', 191)->nullable()->after('username');
                        $table->index('ceph_uid');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added ceph_uid column to s3_users', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_s3_users_ceph_uid', [], $e->getMessage(), [], []);
                }
            }
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

            // Ensure request/audit + scheduling columns (defensive per-column upgrades)
            $deleteReqCols = [
                'requested_action' => function ($table) { $table->string('requested_action', 32)->default('delete')->after('bucket_name'); },
                'requested_by_client_id' => function ($table) { $table->unsignedInteger('requested_by_client_id')->nullable()->after('requested_action'); },
                'requested_by_user_id' => function ($table) { $table->unsignedInteger('requested_by_user_id')->nullable()->after('requested_by_client_id'); },
                'requested_by_contact_id' => function ($table) { $table->unsignedInteger('requested_by_contact_id')->nullable()->after('requested_by_user_id'); },
                'requested_at' => function ($table) { $table->timestamp('requested_at')->nullable()->after('requested_by_contact_id'); },
                'request_ip' => function ($table) { $table->string('request_ip', 64)->nullable()->after('requested_at'); },
                'request_ua' => function ($table) { $table->text('request_ua')->nullable()->after('request_ip'); },
                'retry_after' => function ($table) { $table->timestamp('retry_after')->nullable()->after('request_ua'); },
                'blocked_reason' => function ($table) { $table->string('blocked_reason', 64)->nullable()->after('retry_after'); },
                'earliest_retain_until_ts' => function ($table) { $table->bigInteger('earliest_retain_until_ts')->nullable()->after('blocked_reason'); },
                'audit_json' => function ($table) { $table->text('audit_json')->nullable()->after('earliest_retain_until_ts'); },
            ];
            foreach ($deleteReqCols as $col => $adder) {
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

            // Ensure helpful index exists (ignore errors if it already exists under a different name)
            try {
                \WHMCS\Database\Capsule::schema()->table('s3_delete_buckets', function ($table) {
                    $table->index(['bucket_name', 'status']);
                });
            } catch (\Throwable $e) {
                // Best-effort
            }
        }

        // Ceph pool usage history table (for capacity forecasting)
        try {
            $schema = \WHMCS\Database\Capsule::schema();
            if (!$schema->hasTable('ceph_pool_usage_history')) {
                $schema->create('ceph_pool_usage_history', function ($table) {
                    $table->bigIncrements('id');
                    $table->string('pool_name', 191);
                    $table->bigInteger('used_bytes')->default(0);
                    $table->bigInteger('max_avail_bytes')->default(0);
                    $table->bigInteger('capacity_bytes')->default(0);
                    $table->decimal('percent_used', 8, 4)->default(0);
                    $table->timestamp('collected_at')->useCurrent();
                    $table->timestamp('created_at')->useCurrent();

                    $table->index(['pool_name', 'collected_at']);
                    $table->index(['collected_at']);
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created ceph_pool_usage_history table', [], []);
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'upgrade_ceph_pool_usage_history_fail', [], $e->getMessage(), [], []);
        }

        // Username management table for e3 Cloud Backup users
        try {
            $schema = \WHMCS\Database\Capsule::schema();
            if (!$schema->hasTable('s3_backup_users')) {
                $schema->create('s3_backup_users', function ($table) {
                    $table->increments('id');
                    $table->unsignedInteger('client_id');
                    $table->unsignedInteger('tenant_id')->nullable();
                    $table->string('username', 191);
                    $table->string('password_hash', 255);
                    $table->string('email', 255);
                    $table->enum('status', ['active', 'disabled'])->default('active');
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('updated_at')->useCurrent();
                    $table->unique(['client_id', 'tenant_id', 'username'], 'uniq_backup_users_scope_username');
                    $table->index('client_id');
                    $table->index('tenant_id');
                    $table->index('status');
                    $table->index('email');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_backup_users table', [], []);
            }

            $backupUserColDefs = [
                'client_id' => function ($table) { $table->unsignedInteger('client_id'); },
                'tenant_id' => function ($table) { $table->unsignedInteger('tenant_id')->nullable(); },
                'username' => function ($table) { $table->string('username', 191); },
                'password_hash' => function ($table) { $table->string('password_hash', 255); },
                'email' => function ($table) { $table->string('email', 255); },
                'status' => function ($table) { $table->enum('status', ['active', 'disabled'])->default('active'); },
                'created_at' => function ($table) { $table->timestamp('created_at')->useCurrent(); },
                'updated_at' => function ($table) { $table->timestamp('updated_at')->useCurrent(); },
            ];
            foreach ($backupUserColDefs as $col => $adder) {
                if (!$schema->hasColumn('s3_backup_users', $col)) {
                    try {
                        $schema->table('s3_backup_users', function ($table) use ($adder) {
                            $adder($table);
                        });
                        logModuleCall('cloudstorage', 'upgrade', [], "Added {$col} to s3_backup_users", [], []);
                    } catch (\Throwable $e) {
                        logModuleCall('cloudstorage', "upgrade_add_s3_backup_users_{$col}", [], $e->getMessage(), [], []);
                    }
                }
            }

            try {
                $schema->table('s3_backup_users', function ($table) {
                    $table->unique(['client_id', 'tenant_id', 'username'], 'uniq_backup_users_scope_username');
                });
            } catch (\Throwable $e) {}
            try {
                $schema->table('s3_backup_users', function ($table) {
                    $table->index('client_id');
                });
            } catch (\Throwable $e) {}
            try {
                $schema->table('s3_backup_users', function ($table) {
                    $table->index('tenant_id');
                });
            } catch (\Throwable $e) {}
            try {
                $schema->table('s3_backup_users', function ($table) {
                    $table->index('status');
                });
            } catch (\Throwable $e) {}
            try {
                $schema->table('s3_backup_users', function ($table) {
                    $table->index('email');
                });
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'upgrade_s3_backup_users_fail', [], $e->getMessage(), [], []);
        }

        return ['status' => 'success'];
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'upgrade', $vars, $e->getMessage());
        return ['status' => 'success'];
    }
}

function cloudstorage_clientarea($vars) {
    $page = $_GET['page'] ?? '';
    // WHMCS addon client area routes require login by default unless explicitly disabled.
    // Trial signup + verification must be accessible without an existing session.
    $requireLogin = true;
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
            $requireLogin = false;
            $viewVars = [
                'TURNSTILE_SITE_KEY' => $turnstileSiteKey,
            ];
            break;

        case 'handlesignup':
            $pagetitle = 'e3 Storage Signup';
            // Re-render the same signup template on POST/validation errors
            $templatefile = 'templates/signup';
            $requireLogin = false;
            // Make Turnstile keys available to the included page
            $routeVars = (function () use ($turnstileSiteKey, $turnstileSecretKey) {
                return require __DIR__ . '/pages/handlesignup.php';
            })();
            $viewVars = is_array($routeVars) ? $routeVars : [];
            if (empty($viewVars['TURNSTILE_SITE_KEY'])) {
                $viewVars['TURNSTILE_SITE_KEY'] = $turnstileSiteKey;
            }
            break;

        case 'resendtrial':
            $pagetitle = 'e3 Storage Signup';
            $templatefile = 'templates/signup';
            $requireLogin = false;
            $routeVars = (function () use ($turnstileSiteKey, $turnstileSecretKey) {
                return require __DIR__ . '/pages/resendtrial.php';
            })();
            $viewVars = is_array($routeVars) ? $routeVars : [];
            if (empty($viewVars['TURNSTILE_SITE_KEY'])) {
                $viewVars['TURNSTILE_SITE_KEY'] = $turnstileSiteKey;
            }
            break;

        case 'verifytrial':
            $pagetitle = 'Verify e3 Trial';
            $templatefile = 'templates/signup';
            $requireLogin = false;
            $viewVars = require __DIR__ . '/pages/verifytrial.php';
            if (empty($viewVars['TURNSTILE_SITE_KEY'])) {
                $viewVars['TURNSTILE_SITE_KEY'] = $turnstileSiteKey;
            }
            break;

        case 'welcome':
            $pagetitle = 'Welcome to e3';
            $templatefile = 'templates/welcome';
            // Initially no special vars; template will fetch any needed session/user context
            $tokenPlain = function_exists('generate_token') ? generate_token('plain') : '';
            $viewVars = [
                'csrfToken' => $tokenPlain,
                'token' => $tokenPlain,
            ];
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
                case 'users':
                    $pagetitle = 'e3 Cloud Backup - Users';
                    $templatefile = 'templates/e3backup_users';
                    $viewVars = require 'pages/e3backup_users.php';
                    break;
                case 'user_detail':
                    $pagetitle = 'e3 Cloud Backup - User Detail';
                    $templatefile = 'templates/e3backup_user_detail';
                    $viewVars = require 'pages/e3backup_user_detail.php';
                    break;
                case 'live':
                    $pagetitle = 'e3 Cloud Backup - Live Progress';
                    $templatefile = 'templates/cloudbackup_live';
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
                case 'restores':
                    $pagetitle = 'e3 Cloud Backup - Restores';
                    $templatefile = 'templates/e3backup_restores';
                    $viewVars = require 'pages/e3backup_restores.php';
                    break;
                case 'runs':
                    $pagetitle = 'e3 Cloud Backup - Run History';
                    $templatefile = 'templates/e3backup_runs';
                    $viewVars = require 'pages/e3backup_runs.php';
                    break;
                case 'tenant_users':
                    $pagetitle = 'e3 Cloud Backup - Tenant Users';
                    $templatefile = 'templates/e3backup_tenant_users';
                    $viewVars = require 'pages/e3backup_tenant_users.php';
                    break;
                case 'cloudnas':
                    $pagetitle = 'e3 Cloud Backup - Cloud NAS';
                    $templatefile = 'templates/e3backup_cloudnas';
                    $viewVars = require 'pages/cloudnas.php';
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
                case 'disk_image_restore':
                    $pagetitle = 'e3 Cloud Backup - Disk Image Restore';
                    $templatefile = 'templates/e3backup_disk_image_restore';
                    $viewVars = require 'pages/e3backup_disk_image_restore.php';
                    break;
                case 'recovery_media':
                    $pagetitle = 'e3 Cloud Backup - Recovery Media';
                    $templatefile = 'templates/e3backup_recovery_media';
                    $viewVars = require 'pages/e3backup_recovery_media.php';
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
            // Legacy cloudbackup routes removed
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            exit;

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
        'requirelogin' => $requireLogin,
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

