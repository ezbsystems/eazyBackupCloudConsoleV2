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
        'version' => '2.2.0',
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
            'turnstile_use_invisible' => [
                'FriendlyName' => 'Turnstile: use invisible widget',
                'Type' => 'yesno',
                'Description' => 'Match your Cloudflare widget type for this site key. OFF = visible challenge (typical for staging/dev). ON = invisible widget (typical for production — no visible CAPTCHA row).',
            ],
            'trial_verification_email_template' => [
                'FriendlyName' => 'Trial Verification Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'Select the WHMCS email template from the General category to use for e3 trial email verification.',
            ],
            'trial_existing_account_email_template' => [
                'FriendlyName' => 'Trial Existing Account Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'Optional. When a trial signup is attempted with an email that already belongs to a WHMCS client, this General-category template is sent to that client with a login link. Leave blank to skip this notification.',
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
            'storage_base_fee_cad' => [
                'FriendlyName' => 'Storage Base Fee (CAD / month)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '9.00',
                'Description' => 'Flat monthly base fee covering the first 1 TiB of object storage usage. Default: 9.00.',
            ],
            'storage_overage_per_gib_cad' => [
                'FriendlyName' => 'Storage Overage Rate (CAD / GiB / month)',
                'Type' => 'text',
                'Size' => '12',
                'Default' => '0.008789',
                'Description' => 'Per-GiB rate billed for usage above the first 1 TiB. Default: 0.008789 (≈ $9 per TiB).',
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
                'Default' => '30',
                'Description' => 'Minimum seconds between progress events from worker/agent.'
            ],
            'cloudbackup_event_progress_pct_step' => [
                'FriendlyName' => 'Progress Event Pct Step',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '1.0',
                'Description' => 'Minimum percent change between progress events (in addition to the time interval).'
            ],
            'cloudbackup_run_logs_retention_days' => [
                'FriendlyName' => 'Run Logs Retention (days)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '60',
                'Description' => 'How many days to retain s3_cloudbackup_run_logs rows.'
            ],
            'cloudbackup_agent_events_retention_days' => [
                'FriendlyName' => 'Agent Events Retention (days)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '90',
                'Description' => 'How many days to retain s3_cloudbackup_agent_events rows.'
            ],
            'cloudbackup_agent_events_max_per_day_per_agent' => [
                'FriendlyName' => 'Agent Events Max/Day/Agent',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '1000',
                'Description' => 'Hard cap on agent_events rows accepted per agent per UTC day.'
            ],
            'cloudbackup_chunks_max_per_run' => [
                'FriendlyName' => 'Admin Log Chunks Max/Run',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '60',
                'Description' => 'Maximum admin-only verbose log chunks accepted per run (~1 MB each).'
            ],
            'cloudbackup_admin_chunks_retention_days' => [
                'FriendlyName' => 'Admin Log Chunks Retention (days)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '14',
                'Description' => 'How many days to retain s3_cloudbackup_admin_log_chunks rows.'
            ],
            'cloudbackup_min_local_agent_version' => [
                'FriendlyName' => 'Minimum Local Agent Version',
                'Type' => 'text',
                'Size' => '20',
                'Default' => '',
                'Description' => 'Strict minimum local backup agent version. Older agents are rejected with HTTP 426. Leave blank to disable the gate.'
            ],
            'cloudbackup_agent_fast_bootstrap' => [
                'FriendlyName' => 'Agent Fast Bootstrap',
                'Type' => 'yesno',
                'Description' => 'Use lightweight PHP bootstrap for high-frequency agent poll endpoints (skips full WHMCS init). Env CLOUDBACKUP_AGENT_FAST_BOOTSTRAP overrides.'
            ],
            'cloudbackup_redis_liveness_enabled' => [
                'FriendlyName' => 'Redis Agent Liveness',
                'Type' => 'yesno',
                'Description' => 'Write agent online heartbeats to Redis (SETEX) with debounced MySQL rollup. Requires CLOUDBACKUP_REDIS_URL.'
            ],
            'cloudbackup_liveness_redis_ttl' => [
                'FriendlyName' => 'Redis Liveness TTL (seconds)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '180',
                'Description' => 'Redis key TTL for agent:liveness:{uuid}. Should match or exceed cloudbackup_agent_online_threshold_seconds.'
            ],
            'cloudbackup_agent_heartbeat_debounce_seconds' => [
                'FriendlyName' => 'Agent Heartbeat Debounce (seconds)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '60',
                'Description' => 'Minimum seconds between MySQL last_seen_at updates per agent. Redis liveness is written more frequently when enabled.'
            ],
            'cloudbackup_agent_command_poll_secs' => [
                'FriendlyName' => 'Agent Command Poll Hint (seconds)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '15',
                'Description' => 'Server-driven idle poll interval hint returned by agent_poll.php (next_poll_secs).'
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
            'kopia_vault_default_retention_policy_json' => [
                'FriendlyName' => 'Kopia Vault Default Retention Policy JSON',
                'Type' => 'text',
                'Size' => '250',
                'Description' => 'Pinned to repos at creation; Comet-style tiers in JSON.',
            ],
            'ms365_vault_recycle_grace_days' => [
                'FriendlyName' => 'MS365 Vault Recycle Grace (days)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '30',
                'Description' => 'Days MS365 backup vaults remain in the recycle bin after job deletion before physical teardown.',
            ],
            'ms365_vault_delete_email_template' => [
                'FriendlyName' => 'MS365 Vault Delete Notification Email Template',
                'Type' => 'dropdown',
                'Options' => cloudstorage_get_email_templates(),
                'Description' => 'WHMCS General template emailed to the account owner when an MS365 job and vault are soft-deleted.',
            ],
            'ms365_vault_early_delete_ops_email' => [
                'FriendlyName' => 'MS365 Early Delete Ops Email',
                'Type' => 'text',
                'Size' => '191',
                'Description' => 'Optional internal email for MS365 vault early-deletion requests. Leave blank to skip.',
            ],
            // ==== Agent Builds (e3 local agent build automation) ====
            'agent_build_repo_path' => [
                'FriendlyName' => 'Agent Repo Path',
                'Type' => 'text',
                'Size' => '191',
                'Default' => '/var/www/eazybackup.ca/e3-backup-agent',
                'Description' => 'Local checkout of the e3-backup-agent repository on the WHMCS host.',
            ],
            'agent_build_default_git_ref' => [
                'FriendlyName' => 'Default Git Ref',
                'Type' => 'text',
                'Size' => '100',
                'Default' => 'main',
                'Description' => 'Default branch/tag to build when admin does not override.',
            ],
            'agent_build_publish_dir' => [
                'FriendlyName' => 'Publish Directory',
                'Type' => 'text',
                'Size' => '191',
                'Default' => '/var/www/eazybackup.ca/accounts/client_installer',
                'Description' => 'Where signed artifacts are copied for customer download.',
            ],
            'agent_build_windows_host' => [
                'FriendlyName' => 'Windows Build Host',
                'Type' => 'text',
                'Size' => '100',
                'Default' => '192.168.92.210',
                'Description' => 'Hostname or IP for the Windows build/sign host (Server 2025).',
            ],
            'agent_build_windows_user' => [
                'FriendlyName' => 'Windows Build User',
                'Type' => 'text',
                'Size' => '100',
                'Default' => 'Administrator',
                'Description' => 'SSH user on the Windows build host.',
            ],
            'agent_build_windows_ssh_key' => [
                'FriendlyName' => 'Windows SSH Key Path',
                'Type' => 'text',
                'Size' => '191',
                'Default' => '/root/.ssh/windows_server_ed25519',
                'Description' => 'Path on the WHMCS host to the SSH private key for the Windows build host.',
            ],
            'agent_build_windows_work_dir' => [
                'FriendlyName' => 'Windows Work Directory',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'C:\\E3Build',
                'Description' => 'Staging directory on the Windows build host (per-job subdirectories created here).',
            ],
            'agent_build_iscc_path' => [
                'FriendlyName' => 'Inno Setup Compiler Path (Windows)',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'C:\\Program Files (x86)\\Inno Setup 6\\ISCC.exe',
                'Description' => 'Full path to ISCC.exe on the Windows build host.',
            ],
            'agent_build_signing_enabled' => [
                'FriendlyName' => 'Enable Code Signing',
                'Type' => 'yesno',
                'Description' => 'If enabled, run AzureSignTool against produced Windows binaries and the installer.',
            ],
            'agent_build_azure_tenant_id' => [
                'FriendlyName' => 'Azure Tenant ID',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Azure AD tenant ID used to authenticate AzureSignTool.',
            ],
            'agent_build_azure_client_id' => [
                'FriendlyName' => 'Azure Client ID',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Azure AD application (client) ID with Sign+Get on the Key Vault certificate.',
            ],
            'agent_build_azure_client_secret' => [
                'FriendlyName' => 'Azure Client Secret',
                'Type' => 'password',
                'Size' => '191',
                'Description' => 'Client secret for the Azure AD app. Stored encrypted by WHMCS.',
            ],
            'agent_build_azure_kv_url' => [
                'FriendlyName' => 'Azure Key Vault URL',
                'Type' => 'text',
                'Size' => '191',
                'Description' => 'Example: https://eazybackup-kv.vault.azure.net/',
            ],
            'agent_build_azure_kv_cert_name' => [
                'FriendlyName' => 'Azure Key Vault Certificate Name',
                'Type' => 'text',
                'Size' => '100',
                'Description' => 'Certificate name in the Key Vault to use for Authenticode signing.',
            ],
            'agent_build_signing_timestamp_url' => [
                'FriendlyName' => 'Signing Timestamp URL',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'http://timestamp.digicert.com',
                'Description' => 'RFC 3161 timestamp authority used by AzureSignTool.',
            ],
            'agent_build_azuresigntool_path' => [
                'FriendlyName' => 'AzureSignTool Path (Windows)',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'C:\\Tools\\AzureSignTool\\AzureSignTool.exe',
                'Description' => 'Full path to AzureSignTool.exe on the Windows build host.',
            ],
            // ==== e3 Cloud Backup product + billing ====
            'pid_e3_cloud_backup' => [
                'FriendlyName' => 'e3 Cloud Backup Product (PID)',
                'Type' => 'text',
                'Size' => '10',
                'Description' => 'WHMCS Product ID for the new e3 Cloud Backup product. Auto-filled on activation; admin can override if you have a manually created product to point to.',
            ],
            'e3cb_config_option_ids' => [
                'FriendlyName' => 'e3 Cloud Backup Config Option IDs (JSON)',
                'Type' => 'text',
                'Size' => '250',
                'Description' => 'Auto-managed JSON map of metric -> tblproductconfigoptions.id (endpoint, disk_image, hyperv_vm, proxmox_vm, vmware_vm). Do not edit unless reconciling.',
            ],
            'e3cb_trial_days' => [
                'FriendlyName' => 'e3 Cloud Backup Trial Days',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '30',
                'Description' => 'Free trial period (days) for new e3 Cloud Backup signups. Storage and compute are both free during this window.',
            ],
            'e3cb_included_endpoints' => [
                'FriendlyName' => 'e3 Cloud Backup - Included Endpoints',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '0',
                'Description' => 'Number of endpoints included for free per service (subtracted from billable qty before pricing).',
            ],
            'e3cb_trial_includes_storage' => [
                'FriendlyName' => 'Trial Includes Storage',
                'Type' => 'yesno',
                'Description' => 'If enabled, the storage base fee + overage are also waived during the trial period.',
            ],
            'e3cb_post_trial_no_payment_action' => [
                'FriendlyName' => 'Post-Trial Action (No Payment Method)',
                'Type' => 'dropdown',
                'Options' => 'suspend,terminate',
                'Default' => 'suspend',
                'Description' => 'What to do when a trial ends and the client has no payment method on file. suspend = keep data, freeze service; terminate = irreversibly remove (NOT recommended).',
            ],
            'e3cb_currency_id' => [
                'FriendlyName' => 'e3 Cloud Backup Currency ID',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '1',
                'Description' => 'tblcurrencies.id used for the e3 Cloud Backup product pricing rows. Default 1 (typically CAD).',
            ],
            // ==== e3 Backup User (unified per-user product) ====
            'pid_e3_backup_user' => [
                'FriendlyName' => 'e3 Backup User Product (PID)',
                'Type' => 'text',
                'Size' => '10',
                'Description' => 'WHMCS Product ID for the unified e3 Backup User product (one service per s3_backup_users row). Auto-filled on activation.',
            ],
            'e3bu_config_option_ids' => [
                'FriendlyName' => 'e3 Backup User Config Option IDs (JSON)',
                'Type' => 'text',
                'Size' => '350',
                'Description' => 'Auto-managed JSON map of metric -> tblproductconfigoptions.id for the unified product. Do not edit unless reconciling.',
            ],
            'e3_backup_user_unified_enabled' => [
                'FriendlyName' => 'e3 Backup User Unified Provisioning',
                'Type' => 'yesno',
                'Description' => 'When enabled, new backup users are provisioned on the unified e3 Backup User product. Existing grandfathered services are unchanged.',
            ],
            'e3backup_beta_hosts' => [
                'FriendlyName' => 'e3 Cloud Backup - Beta Hosts',
                'Type' => 'text',
                'Size' => '191',
                'Default' => 'dev.eazybackup.ca',
                'Description' => 'Comma-separated list of HTTP_HOST values where the e3 Cloud Backup card is visible on the Welcome page by default.',
            ],
            'e3backup_beta_client_ids' => [
                'FriendlyName' => 'e3 Cloud Backup - Beta Client IDs',
                'Type' => 'text',
                'Size' => '191',
                'Description' => 'Comma-separated WHMCS client IDs allowed to see the e3 Cloud Backup card regardless of host (early-access allowlist).',
            ],
            'e3backup_beta_admin_override' => [
                'FriendlyName' => 'e3 Cloud Backup - Honour ?eb_beta=1 for Admins',
                'Type' => 'yesno',
                'Description' => 'If enabled, signed-in WHMCS admins (or SSO impersonation sessions) can force the e3 Cloud Backup card to appear by appending ?eb_beta=1.',
            ],
            'e3cb_beta_free_billing' => [
                'FriendlyName' => 'e3 Cloud Backup - Beta (zero all compute charges)',
                'Type' => 'yesno',
                'Description' => 'While enabled, e3 Cloud Backup compute lines (devices, disk image, guest VMs) are invoiced at $0.00 for ALL clients, both existing customers and new trials. Invoices are still generated and usage is still metered and recorded - only the billable amount is forced to zero. Object storage consumption is unaffected.',
            ],
            'trial_skip_verification_emails' => [
                'FriendlyName' => 'Trial - Skip Email Verification (Emails)',
                'Type' => 'textarea',
                'Rows' => '3',
                'Cols' => '60',
                'Description' => 'Comma- or newline-separated email addresses whose trial signups bypass the email-verification step. ONLY honoured when HTTP_HOST is in e3backup_beta_hosts (dev only).',
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
 * Check if a unique index already exists for agents.agent_uuid.
 */
function cloudstorage_has_unique_agent_uuid_index(): bool
{
    try {
        $databaseName = Capsule::connection()->getDatabaseName();
        $count = Capsule::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', 's3_cloudbackup_agents')
            ->where('COLUMN_NAME', 'agent_uuid')
            ->where('NON_UNIQUE', 0)
            ->count();
        return (int) $count > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Generate a unique UUID for s3_cloudbackup_agents.agent_uuid.
 *
 * @throws RuntimeException
 */
function cloudstorage_generate_unique_agent_uuid_value(int $maxAttempts = 10): string
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $candidate = cloudstorage_generate_uuid();
        $exists = Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $candidate)
            ->exists();
        if (!$exists) {
            return $candidate;
        }
    }
    throw new RuntimeException('failed to generate unique agent_uuid after retries');
}

/**
 * Generate a ULID (Crockford Base32, 26 chars) for use as a public identifier.
 * Compatible with eazybackup_generate_ulid() but self-contained so the
 * cloudstorage module can run migrations independently.
 */
function cloudstorage_generate_ulid(): string
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    try {
        $time = (int) floor(microtime(true) * 1000);
    } catch (\Throwable $__) {
        $time = (int) (time() * 1000);
    }
    $timeBytes = '';
    for ($i = 5; $i >= 0; $i--) {
        $timeBytes .= chr(($time >> ($i * 8)) & 0xFF);
    }
    try {
        $rand = random_bytes(10);
    } catch (\Throwable $__) {
        $rand = substr(hash('sha256', uniqid('', true), true), 0, 10);
    }
    $bin = $timeBytes . $rand;
    $bits = '';
    for ($i = 0; $i < 16; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0; $i < 26; $i++) {
        $chunk = substr($bits, $i * 5, 5);
        if ($chunk === '') {
            $chunk = '00000';
        }
        $idx = bindec(str_pad($chunk, 5, '0'));
        $out .= $alphabet[$idx];
    }
    return $out;
}

/**
 * Backfill public_id for s3_backup_users rows that are missing one.
 */
function cloudstorage_backfill_backup_user_public_ids(string $context = 'activate'): void
{
    if (!Capsule::schema()->hasTable('s3_backup_users') || !Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
        return;
    }
    $rows = Capsule::table('s3_backup_users')
        ->where(function ($q) {
            $q->whereNull('public_id')->orWhere('public_id', '');
        })
        ->orderBy('id', 'asc')
        ->get(['id']);

    foreach ($rows as $row) {
        $rowId = (int) ($row->id ?? 0);
        if ($rowId <= 0) {
            continue;
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $publicId = cloudstorage_generate_ulid();
            try {
                $updated = Capsule::table('s3_backup_users')
                    ->where('id', $rowId)
                    ->where(function ($q) {
                        $q->whereNull('public_id')->orWhere('public_id', '');
                    })
                    ->update(['public_id' => $publicId]);
                if ((int) $updated > 0) {
                    break;
                }
            } catch (\Throwable $__) {
            }
        }
    }
    logModuleCall('cloudstorage', $context, [], 'Backfilled public_id on s3_backup_users', [], []);
}

/**
 * Repair agent_uuid schema + backfill for existing installs.
 *
 * This is intentionally idempotent and safe to run multiple times.
 */
function cloudstorage_repair_agent_uuid_schema(string $context = 'activate'): void
{
    $schema = Capsule::schema();
    if (!$schema->hasTable('s3_cloudbackup_agents')) {
        return;
    }

    if (!$schema->hasColumn('s3_cloudbackup_agents', 'agent_uuid')) {
        $schema->table('s3_cloudbackup_agents', function ($table) {
            $table->string('agent_uuid', 36)->nullable()->after('id');
        });
        logModuleCall('cloudstorage', $context, [], 'Added missing agent_uuid column to s3_cloudbackup_agents', [], []);
    }

    // Backfill empty agent_uuid values in chunks.
    $backfilled = 0;
    $lastId = 0;
    $chunk = 500;
    while (true) {
        $rows = Capsule::table('s3_cloudbackup_agents')
            ->where('id', '>', $lastId)
            ->where(function ($q) {
                $q->whereNull('agent_uuid')->orWhere('agent_uuid', '');
            })
            ->orderBy('id', 'asc')
            ->limit($chunk)
            ->get(['id']);
        if (!$rows || count($rows) === 0) {
            break;
        }
        foreach ($rows as $row) {
            $lastId = (int) $row->id;
            $uuid = cloudstorage_generate_unique_agent_uuid_value();
            $backfilled += (int) Capsule::table('s3_cloudbackup_agents')
                ->where('id', (int) $row->id)
                ->where(function ($q) {
                    $q->whereNull('agent_uuid')->orWhere('agent_uuid', '');
                })
                ->update(['agent_uuid' => $uuid]);
        }
    }

    // Resolve duplicate non-empty UUIDs before applying a unique index.
    $duplicateValues = Capsule::table('s3_cloudbackup_agents')
        ->select('agent_uuid', Capsule::raw('COUNT(*) as dup_count'))
        ->whereNotNull('agent_uuid')
        ->where('agent_uuid', '!=', '')
        ->groupBy('agent_uuid')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('agent_uuid')
        ->toArray();
    $deduped = 0;
    foreach ($duplicateValues as $dupUuid) {
        $ids = Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', (string) $dupUuid)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->toArray();
        for ($i = 1; $i < count($ids); $i++) {
            $newUuid = cloudstorage_generate_unique_agent_uuid_value();
            $deduped += (int) Capsule::table('s3_cloudbackup_agents')
                ->where('id', (int) $ids[$i])
                ->where('agent_uuid', (string) $dupUuid)
                ->update(['agent_uuid' => $newUuid]);
        }
    }

    if (!cloudstorage_has_unique_agent_uuid_index()) {
        $schema->table('s3_cloudbackup_agents', function ($table) {
            $table->unique('agent_uuid', 'uniq_cloudbackup_agents_agent_uuid');
        });
        logModuleCall('cloudstorage', $context, [], 'Added unique index for s3_cloudbackup_agents.agent_uuid', [], []);
    }

    logModuleCall('cloudstorage', $context, [], [
        'agent_uuid_backfilled' => $backfilled,
        'agent_uuid_deduped' => $deduped,
    ], [], []);
}

/**
 * Ensure a column exists on an existing table.
 */
function cloudstorage_ensure_table_column(string $tableName, string $columnName, callable $adder, string $context = 'activate'): void
{
    $schema = Capsule::schema();
    if (!$schema->hasTable($tableName) || $schema->hasColumn($tableName, $columnName)) {
        return;
    }

    try {
        $schema->table($tableName, function ($table) use ($adder) {
            $adder($table);
        });
        logModuleCall('cloudstorage', $context, [], "Ensured {$columnName} on {$tableName}", [], []);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', "{$context}_ensure_{$tableName}_{$columnName}", [], $e->getMessage(), [], []);
    }
}

/**
 * Ensure an index exists. Duplicate/index-exists failures are ignored.
 */
function cloudstorage_ensure_table_index(string $tableName, callable $indexer, string $indexLabel, string $context = 'activate'): void
{
    $schema = Capsule::schema();
    if (!$schema->hasTable($tableName)) {
        return;
    }

    try {
        $schema->table($tableName, function ($table) use ($indexer) {
            $indexer($table);
        });
        logModuleCall('cloudstorage', $context, [], "Ensured {$indexLabel} on {$tableName}", [], []);
    } catch (\Throwable $e) {
        // Best effort only; index may already exist under the same or another compatible name.
    }
}

/**
 * Ensure Hyper-V schema objects exist and are updated additively.
 */
/**
 * Ensure the schema for the new e3 Cloud Backup billing subsystem exists.
 * Creates four tables:
 *   - s3_cloudbackup_usage_snapshots  (hourly metered qty per metric per service)
 *   - s3_cloudbackup_pricing          (per-client / global price overrides)
 *   - s3_cloudbackup_rated_lines      (computed monthly amount per metric per window)
 *   - s3_cloudbackup_trial_state      (trialing -> converted / suspended_no_payment lifecycle)
 *
 * Idempotent: safe to invoke on every activate and upgrade.
 */
function cloudstorage_ensure_e3cb_billing_schema(string $context = 'activate'): void
{
    $schema = Capsule::schema();
    $metricEnum = "ENUM('endpoint','disk_image','hyperv_vm','proxmox_vm','vmware_vm','saas_connector')";

    // --- s3_cloudbackup_usage_snapshots ---
    if (!$schema->hasTable('s3_cloudbackup_usage_snapshots')) {
        try {
            $schema->create('s3_cloudbackup_usage_snapshots', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('service_id');
                $table->unsignedInteger('client_id');
                $table->enum('metric', ['endpoint', 'disk_image', 'hyperv_vm', 'proxmox_vm', 'vmware_vm', 'saas_connector']);
                $table->unsignedBigInteger('backup_user_id')->default(0);
                $table->unsignedInteger('qty')->default(0);
                $table->timestamp('taken_at')->useCurrent();
                $table->index(['service_id', 'metric', 'taken_at'], 'idx_e3cb_usage_service_metric_time');
                $table->index(['client_id', 'taken_at'], 'idx_e3cb_usage_client_time');
                $table->index(['service_id', 'backup_user_id', 'metric'], 'idx_e3cb_usage_service_user_metric');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_cloudbackup_usage_snapshots', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_cloudbackup_usage_snapshots", [], $e->getMessage(), [], []);
        }
    } else {
        try {
            if (!$schema->hasColumn('s3_cloudbackup_usage_snapshots', 'backup_user_id')) {
                $schema->table('s3_cloudbackup_usage_snapshots', function ($table) {
                    $table->unsignedBigInteger('backup_user_id')->default(0)->after('client_id');
                    $table->index(['service_id', 'backup_user_id', 'metric'], 'idx_e3cb_usage_service_user_metric');
                });
                logModuleCall('cloudstorage', $context, [], 'Added backup_user_id to s3_cloudbackup_usage_snapshots', [], []);
            }
            $col = Capsule::selectOne("SHOW COLUMNS FROM `s3_cloudbackup_usage_snapshots` LIKE 'metric'");
            $type = is_object($col) ? (string) ($col->Type ?? '') : '';
            if ($type !== '' && strpos($type, "'saas_connector'") === false) {
                Capsule::statement(
                    "ALTER TABLE `s3_cloudbackup_usage_snapshots` MODIFY `metric` {$metricEnum} NOT NULL"
                );
                logModuleCall('cloudstorage', $context, [], 'Added saas_connector to s3_cloudbackup_usage_snapshots.metric', [], []);
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_extend_usage_snapshots", [], $e->getMessage(), [], []);
        }
    }

    // --- s3_cloudbackup_pricing ---
    if (!$schema->hasTable('s3_cloudbackup_pricing')) {
        try {
            $schema->create('s3_cloudbackup_pricing', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id')->nullable();
                $table->enum('metric', ['endpoint', 'disk_image', 'hyperv_vm', 'proxmox_vm', 'vmware_vm', 'saas_connector']);
                $table->enum('mode', ['flat_unit', 'tiered', 'flat_monthly']);
                $table->decimal('unit_price', 12, 4)->nullable();
                $table->json('tiers_json')->nullable();
                $table->decimal('flat_monthly', 12, 4)->nullable();
                $table->unsignedInteger('currency_id')->default(1);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by_admin')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['client_id', 'metric'], 'idx_e3cb_pricing_client_metric');
                $table->index(['metric', 'effective_from'], 'idx_e3cb_pricing_metric_eff');
            });
            // Unique key including the nullable client_id - MySQL treats multiple NULLs
            // as distinct, which is fine since we only ever have one global default row
            // per (metric, currency_id, effective_from). Enforce uniqueness at the app
            // level instead.
            logModuleCall('cloudstorage', $context, [], 'Created s3_cloudbackup_pricing', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_cloudbackup_pricing", [], $e->getMessage(), [], []);
        }
    } else {
        try {
            $col = Capsule::selectOne("SHOW COLUMNS FROM `s3_cloudbackup_pricing` LIKE 'metric'");
            $type = is_object($col) ? (string) ($col->Type ?? '') : '';
            if ($type !== '' && strpos($type, "'saas_connector'") === false) {
                Capsule::statement(
                    "ALTER TABLE `s3_cloudbackup_pricing` MODIFY `metric` {$metricEnum} NOT NULL"
                );
                logModuleCall('cloudstorage', $context, [], 'Added saas_connector to s3_cloudbackup_pricing.metric', [], []);
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_extend_pricing_metric_enum", [], $e->getMessage(), [], []);
        }
    }

    // --- s3_cloudbackup_rated_lines ---
    if (!$schema->hasTable('s3_cloudbackup_rated_lines')) {
        try {
            $schema->create('s3_cloudbackup_rated_lines', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('service_id');
                $table->unsignedInteger('client_id');
                $table->enum('metric', ['endpoint', 'disk_image', 'hyperv_vm', 'proxmox_vm', 'vmware_vm', 'saas_connector']);
                $table->unsignedBigInteger('backup_user_id')->default(0);
                $table->unsignedInteger('qty')->default(0);
                $table->decimal('unit_price', 12, 4)->default(0);
                $table->string('tier_label', 64)->nullable();
                $table->decimal('line_amount', 12, 2)->default(0);
                $table->unsignedInteger('currency_id')->default(1);
                $table->date('billing_window_start');
                $table->date('billing_window_end');
                $table->enum('pricing_source', [
                    'client_override',
                    'global_default',
                    'tblpricing',
                    'flat_monthly',
                    'trial_zeroed',
                    'beta_zeroed',
                ]);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['service_id', 'billing_window_start'], 'idx_e3cb_rated_service_window');
                $table->index(['client_id', 'billing_window_start'], 'idx_e3cb_rated_client_window');
                $table->unique(['service_id', 'metric', 'billing_window_start', 'backup_user_id'], 'uniq_e3cb_rated_service_metric_window');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_cloudbackup_rated_lines', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_cloudbackup_rated_lines", [], $e->getMessage(), [], []);
        }
    } else {
        // Extend existing installs: the global beta-billing override writes a
        // 'beta_zeroed' pricing_source, which must be a permitted ENUM value.
        try {
            $col = Capsule::selectOne("SHOW COLUMNS FROM `s3_cloudbackup_rated_lines` LIKE 'pricing_source'");
            $type = is_object($col) ? (string) ($col->Type ?? '') : '';
            if ($type !== '' && strpos($type, "'beta_zeroed'") === false) {
                Capsule::statement(
                    "ALTER TABLE `s3_cloudbackup_rated_lines` MODIFY `pricing_source` "
                    . "ENUM('client_override','global_default','tblpricing','flat_monthly','trial_zeroed','beta_zeroed') NOT NULL"
                );
                logModuleCall('cloudstorage', $context, [], "Added beta_zeroed to s3_cloudbackup_rated_lines.pricing_source", [], []);
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_extend_rated_lines_enum", [], $e->getMessage(), [], []);
        }
        try {
            if (!$schema->hasColumn('s3_cloudbackup_rated_lines', 'backup_user_id')) {
                $schema->table('s3_cloudbackup_rated_lines', function ($table) {
                    $table->unsignedBigInteger('backup_user_id')->default(0)->after('client_id');
                });
                logModuleCall('cloudstorage', $context, [], 'Added backup_user_id to s3_cloudbackup_rated_lines', [], []);
            }
            $col = Capsule::selectOne("SHOW COLUMNS FROM `s3_cloudbackup_rated_lines` LIKE 'metric'");
            $type = is_object($col) ? (string) ($col->Type ?? '') : '';
            if ($type !== '' && strpos($type, "'saas_connector'") === false) {
                Capsule::statement(
                    "ALTER TABLE `s3_cloudbackup_rated_lines` MODIFY `metric` {$metricEnum} NOT NULL"
                );
                logModuleCall('cloudstorage', $context, [], 'Added saas_connector to s3_cloudbackup_rated_lines.metric', [], []);
            }
            try {
                $schema->table('s3_cloudbackup_rated_lines', function ($table) {
                    $table->dropUnique('uniq_e3cb_rated_service_metric_window');
                });
            } catch (\Throwable $_) {
            }
            try {
                $schema->table('s3_cloudbackup_rated_lines', function ($table) {
                    $table->unique(
                        ['service_id', 'metric', 'billing_window_start', 'backup_user_id'],
                        'uniq_e3cb_rated_service_metric_window'
                    );
                });
            } catch (\Throwable $_) {
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_extend_rated_lines_backup_user", [], $e->getMessage(), [], []);
        }
    }

    // --- s3_cloudbackup_trial_state ---
    if (!$schema->hasTable('s3_cloudbackup_trial_state')) {
        try {
            $schema->create('s3_cloudbackup_trial_state', function ($table) {
                $table->unsignedInteger('service_id')->primary();
                $table->unsignedInteger('client_id');
                $table->timestamp('trial_started_at')->useCurrent();
                $table->timestamp('trial_ends_at')->nullable();
                $table->enum('status', ['trialing', 'converted', 'suspended_no_payment', 'cancelled'])->default('trialing');
                $table->timestamp('converted_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('payment_method_seen_at')->nullable();
                $table->timestamp('last_evaluated_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('client_id', 'idx_e3cb_trial_client');
                $table->index('status', 'idx_e3cb_trial_status');
                $table->index('trial_ends_at', 'idx_e3cb_trial_ends');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_cloudbackup_trial_state', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_cloudbackup_trial_state", [], $e->getMessage(), [], []);
        }
    }

    // --- s3_e3backup_onboarding_state ---
    // Tracks per-client first-run state for the e3 Cloud Backup getting-started
    // experience: when did the customer click Download? did they dismiss the
    // guided tour? has the tour been completed? These are bits we can't derive
    // from agents / jobs / runs tables.
    if (!$schema->hasTable('s3_e3backup_onboarding_state')) {
        try {
            $schema->create('s3_e3backup_onboarding_state', function ($table) {
                $table->unsignedInteger('client_id')->primary();
                $table->timestamp('download_clicked_at')->nullable();
                $table->timestamp('tour_started_at')->nullable();
                $table->timestamp('tour_completed_at')->nullable();
                $table->timestamp('tour_dismissed_at')->nullable();
                $table->timestamp('first_job_tour_started_at')->nullable();
                $table->timestamp('first_job_tour_completed_at')->nullable();
                $table->timestamp('first_job_tour_dismissed_at')->nullable();
                $table->timestamp('last_visited_getting_started_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_e3backup_onboarding_state', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_e3backup_onboarding_state", [], $e->getMessage(), [], []);
        }
    } else {
        // Extend existing installs with the first-job tour columns (added after the
        // initial onboarding rollout). These are the write-once timestamps for the
        // second guided tour that fires on Users / User Detail when the customer
        // has installed an agent but not yet created their first backup job.
        try {
            foreach (['first_job_tour_started_at', 'first_job_tour_completed_at', 'first_job_tour_dismissed_at'] as $col) {
                if (!$schema->hasColumn('s3_e3backup_onboarding_state', $col)) {
                    $schema->table('s3_e3backup_onboarding_state', function ($table) use ($col) {
                        $table->timestamp($col)->nullable()->after('tour_dismissed_at');
                    });
                    logModuleCall('cloudstorage', $context, [], "Added {$col} to s3_e3backup_onboarding_state", [], []);
                }
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_extend_s3_e3backup_onboarding_state", [], $e->getMessage(), [], []);
        }
    }
}

/**
 * Auto-provision the e3 Cloud Backup WHMCS product + config option group +
 * 5 metric options + base tblpricing rows. Wraps the bootstrap class so the
 * activation routine has a stable function reference.
 */
function cloudstorage_ensure_e3cb_product(string $context = 'activate'): void
{
    try {
        $cls = '\\WHMCS\\Module\\Addon\\CloudStorage\\Provision\\E3CloudBackupProductBootstrap';
        if (!class_exists($cls)) {
            $path = __DIR__ . '/lib/Provision/E3CloudBackupProductBootstrap.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (class_exists($cls)) {
            $cls::ensure($context);
        }
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', "{$context}_e3cb_product_bootstrap_exception", [], $e->getMessage(), [], []);
    }
}

/**
 * Auto-provision the unified e3 Backup User WHMCS product + all metric config options.
 */
function cloudstorage_ensure_e3bu_product(string $context = 'activate'): void
{
    try {
        $cls = '\\WHMCS\\Module\\Addon\\CloudStorage\\Provision\\E3BackupUserProductBootstrap';
        if (!class_exists($cls)) {
            $path = __DIR__ . '/lib/Provision/E3BackupUserProductBootstrap.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (class_exists($cls)) {
            $cls::ensure($context);
        }
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', "{$context}_e3bu_product_bootstrap_exception", [], $e->getMessage(), [], []);
    }
}

/**
 * Backfill encryption_mode on existing s3_backup_users rows (grandfather-safe).
 * local -> strict; cloud_only / both -> managed.
 */
function cloudstorage_backfill_backup_user_encryption_mode(string $context = 'activate'): void
{
    if (!Capsule::schema()->hasTable('s3_backup_users')
        || !Capsule::schema()->hasColumn('s3_backup_users', 'encryption_mode')) {
        return;
    }
    try {
        if (Capsule::schema()->hasColumn('s3_backup_users', 'backup_type')) {
            Capsule::table('s3_backup_users')
                ->where('backup_type', 'local')
                ->update(['encryption_mode' => 'strict']);
            Capsule::table('s3_backup_users')
                ->whereIn('backup_type', ['cloud_only', 'both'])
                ->update(['encryption_mode' => 'managed']);
        }
        logModuleCall('cloudstorage', $context, [], 'Backfilled encryption_mode on s3_backup_users', [], []);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', "{$context}_backfill_encryption_mode_fail", [], $e->getMessage(), [], []);
    }
}

function cloudstorage_ensure_eb_run_id_custom_field(string $context = 'activate'): void
{
    try {
        $exists = Capsule::table('tblcustomfields')
            ->where('type', 'support')
            ->where('fieldname', 'eb_run_id')
            ->exists();
        if ($exists) {
            return;
        }
        Capsule::table('tblcustomfields')->insert([
            'type'        => 'support',
            'relid'       => 1,
            'fieldname'   => 'eb_run_id',
            'fieldtype'   => 'text',
            'description' => 'Cloud Backup Run ID (auto-populated by e3 Cloud Backup)',
            'fieldoptions'=> '',
            'regexpr'     => '',
            'adminonly'   => 'on',
            'required'    => '',
            'showorder'   => '',
            'showinvoice' => '',
            'sortorder'   => 0,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        logModuleCall('cloudstorage', $context, [], 'Created eb_run_id support custom field', [], []);
    } catch (\Throwable $e) {
        try {
            logModuleCall('cloudstorage', $context, [], 'eb_run_id custom field ensure failed: ' . $e->getMessage(), [], []);
        } catch (\Throwable $__) {
        }
    }
}

function cloudstorage_ensure_hyperv_schema(string $context = 'activate'): void
{
    $schema = Capsule::schema();

    if ($schema->hasTable('s3_cloudbackup_jobs')) {
        cloudstorage_ensure_table_column('s3_cloudbackup_jobs', 'hyperv_enabled', function ($table) {
            $table->boolean('hyperv_enabled')->default(false);
        }, $context);
        cloudstorage_ensure_table_column('s3_cloudbackup_jobs', 'hyperv_config', function ($table) {
            $table->json('hyperv_config')->nullable();
        }, $context);
    }

    if ($schema->hasTable('s3_cloudbackup_runs')) {
        cloudstorage_ensure_table_column('s3_cloudbackup_runs', 'disk_manifests_json', function ($table) {
            $table->json('disk_manifests_json')->nullable();
        }, $context);
    }

    foreach (['s3_cloudbackup_jobs', 's3_cloudbackup_runs'] as $engineTable) {
        if (!$schema->hasTable($engineTable) || !$schema->hasColumn($engineTable, 'engine')) {
            continue;
        }
        try {
            $columnMeta = Capsule::select("SHOW COLUMNS FROM `{$engineTable}` WHERE Field = 'engine'");
            $typeStr = strtolower((string) ($columnMeta[0]->Type ?? ''));
            if ($typeStr !== '' && strpos($typeStr, "enum(") !== false && (strpos($typeStr, "'disk_image'") === false || strpos($typeStr, "'hyperv'") === false)) {
                Capsule::statement("ALTER TABLE `{$engineTable}` MODIFY COLUMN `engine` ENUM('sync', 'kopia', 'disk_image', 'hyperv') NOT NULL DEFAULT 'sync'");
                logModuleCall('cloudstorage', $context, [], "Extended engine enum on {$engineTable}", [], []);
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_extend_engine_enum_{$engineTable}", [], $e->getMessage(), [], []);
        }
    }

    if (!$schema->hasTable('s3_hyperv_vms') && $schema->hasTable('s3_cloudbackup_jobs')) {
        try {
            $schema->create('s3_hyperv_vms', function ($table) {
                $table->increments('id');
                $table->char('job_id', 16)->charset('binary');
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
                $table->foreign('job_id')->references('job_id')->on('s3_cloudbackup_jobs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_hyperv_vms table', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_hyperv_vms", [], $e->getMessage(), [], []);
        }
    }

    $hypervVmColumns = [
        'job_id' => function ($table) { $table->char('job_id', 16)->charset('binary'); },
        'vm_name' => function ($table) { $table->string('vm_name', 255); },
        'vm_guid' => function ($table) { $table->string('vm_guid', 64)->nullable(); },
        'generation' => function ($table) { $table->tinyInteger('generation')->default(2); },
        'is_linux' => function ($table) { $table->boolean('is_linux')->default(false); },
        'integration_services' => function ($table) { $table->boolean('integration_services')->default(true); },
        'rct_enabled' => function ($table) { $table->boolean('rct_enabled')->default(false); },
        'backup_enabled' => function ($table) { $table->boolean('backup_enabled')->default(true); },
        'created_at' => function ($table) { $table->timestamp('created_at')->useCurrent(); },
        'updated_at' => function ($table) { $table->timestamp('updated_at')->useCurrent(); },
    ];
    foreach ($hypervVmColumns as $columnName => $adder) {
        cloudstorage_ensure_table_column('s3_hyperv_vms', $columnName, $adder, $context);
    }
    cloudstorage_ensure_table_index('s3_hyperv_vms', function ($table) {
        $table->unique(['job_id', 'vm_guid'], 'uk_job_vm');
    }, 'uk_job_vm', $context);
    cloudstorage_ensure_table_index('s3_hyperv_vms', function ($table) {
        $table->index(['job_id', 'backup_enabled'], 'idx_job_enabled');
    }, 'idx_job_enabled', $context);

    if (!$schema->hasTable('s3_hyperv_vm_disks') && $schema->hasTable('s3_hyperv_vms')) {
        try {
            $schema->create('s3_hyperv_vm_disks', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('vm_id');
                $table->string('disk_path', 1024);
                $table->enum('controller_type', ['SCSI', 'IDE'])->default('SCSI');
                $table->integer('controller_number')->default(0);
                $table->integer('controller_location')->default(0);
                $table->enum('vhd_format', ['VHDX', 'VHD'])->default('VHDX');
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedBigInteger('used_bytes')->nullable();
                $table->boolean('rct_enabled')->default(false);
                $table->string('current_rct_id', 128)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('vm_id', 'idx_vm_id');
                $table->foreign('vm_id')->references('id')->on('s3_hyperv_vms')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_hyperv_vm_disks table', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_hyperv_vm_disks", [], $e->getMessage(), [], []);
        }
    }

    $hypervDiskColumns = [
        'vm_id' => function ($table) { $table->unsignedInteger('vm_id'); },
        'disk_path' => function ($table) { $table->string('disk_path', 1024); },
        'controller_type' => function ($table) { $table->enum('controller_type', ['SCSI', 'IDE'])->default('SCSI'); },
        'controller_number' => function ($table) { $table->integer('controller_number')->default(0); },
        'controller_location' => function ($table) { $table->integer('controller_location')->default(0); },
        'vhd_format' => function ($table) { $table->enum('vhd_format', ['VHDX', 'VHD'])->default('VHDX'); },
        'size_bytes' => function ($table) { $table->unsignedBigInteger('size_bytes')->nullable(); },
        'used_bytes' => function ($table) { $table->unsignedBigInteger('used_bytes')->nullable(); },
        'rct_enabled' => function ($table) { $table->boolean('rct_enabled')->default(false); },
        'current_rct_id' => function ($table) { $table->string('current_rct_id', 128)->nullable(); },
        'created_at' => function ($table) { $table->timestamp('created_at')->useCurrent(); },
        'updated_at' => function ($table) { $table->timestamp('updated_at')->useCurrent(); },
    ];
    foreach ($hypervDiskColumns as $columnName => $adder) {
        cloudstorage_ensure_table_column('s3_hyperv_vm_disks', $columnName, $adder, $context);
    }
    cloudstorage_ensure_table_index('s3_hyperv_vm_disks', function ($table) {
        $table->index('vm_id', 'idx_vm_id');
    }, 'idx_vm_id', $context);

    if (!$schema->hasTable('s3_hyperv_checkpoints') && $schema->hasTable('s3_hyperv_vms')) {
        try {
            $schema->create('s3_hyperv_checkpoints', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('vm_id');
                $table->char('run_id', 16)->charset('binary')->nullable();
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
            logModuleCall('cloudstorage', $context, [], 'Created s3_hyperv_checkpoints table', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_hyperv_checkpoints", [], $e->getMessage(), [], []);
        }
    }

    $hypervCheckpointColumns = [
        'vm_id' => function ($table) { $table->unsignedInteger('vm_id'); },
        'run_id' => function ($table) { $table->char('run_id', 16)->charset('binary')->nullable(); },
        'checkpoint_id' => function ($table) { $table->string('checkpoint_id', 64); },
        'checkpoint_name' => function ($table) { $table->string('checkpoint_name', 255)->nullable(); },
        'checkpoint_type' => function ($table) { $table->enum('checkpoint_type', ['Production', 'Standard', 'Reference'])->default('Production'); },
        'rct_ids' => function ($table) { $table->json('rct_ids')->nullable(); },
        'is_active' => function ($table) { $table->boolean('is_active')->default(true); },
        'created_at' => function ($table) { $table->timestamp('created_at')->useCurrent(); },
        'merged_at' => function ($table) { $table->timestamp('merged_at')->nullable(); },
    ];
    foreach ($hypervCheckpointColumns as $columnName => $adder) {
        cloudstorage_ensure_table_column('s3_hyperv_checkpoints', $columnName, $adder, $context);
    }
    cloudstorage_ensure_table_index('s3_hyperv_checkpoints', function ($table) {
        $table->index(['vm_id', 'is_active'], 'idx_vm_active');
    }, 'idx_vm_active', $context);
    cloudstorage_ensure_table_index('s3_hyperv_checkpoints', function ($table) {
        $table->index('run_id', 'idx_run_id');
    }, 'idx_run_id', $context);

    if (!$schema->hasTable('s3_hyperv_backup_points') && $schema->hasTable('s3_hyperv_vms') && $schema->hasTable('s3_cloudbackup_runs')) {
        try {
            $schema->create('s3_hyperv_backup_points', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('vm_id');
                $table->char('run_id', 16)->charset('binary');
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
                $table->foreign('run_id')->references('run_id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_hyperv_backup_points table', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_hyperv_backup_points", [], $e->getMessage(), [], []);
        }
    }

    $hypervBackupPointColumns = [
        'vm_id' => function ($table) { $table->unsignedInteger('vm_id'); },
        'run_id' => function ($table) { $table->char('run_id', 16)->charset('binary'); },
        'backup_type' => function ($table) { $table->enum('backup_type', ['Full', 'Incremental']); },
        'manifest_id' => function ($table) { $table->string('manifest_id', 128); },
        'parent_backup_id' => function ($table) { $table->unsignedInteger('parent_backup_id')->nullable(); },
        'vm_config_json' => function ($table) { $table->json('vm_config_json')->nullable(); },
        'disk_manifests' => function ($table) { $table->json('disk_manifests')->nullable(); },
        'total_size_bytes' => function ($table) { $table->unsignedBigInteger('total_size_bytes')->nullable(); },
        'changed_size_bytes' => function ($table) { $table->unsignedBigInteger('changed_size_bytes')->nullable(); },
        'duration_seconds' => function ($table) { $table->unsignedInteger('duration_seconds')->nullable(); },
        'consistency_level' => function ($table) { $table->enum('consistency_level', ['Crash', 'Application', 'CrashNoCheckpoint'])->default('Application'); },
        'warnings_json' => function ($table) { $table->json('warnings_json')->nullable(); },
        'warning_code' => function ($table) { $table->string('warning_code', 64)->nullable(); },
        'has_warnings' => function ($table) { $table->boolean('has_warnings')->default(false); },
        'created_at' => function ($table) { $table->timestamp('created_at')->useCurrent(); },
        'expires_at' => function ($table) { $table->timestamp('expires_at')->nullable(); },
    ];
    foreach ($hypervBackupPointColumns as $columnName => $adder) {
        cloudstorage_ensure_table_column('s3_hyperv_backup_points', $columnName, $adder, $context);
    }
    cloudstorage_ensure_table_index('s3_hyperv_backup_points', function ($table) {
        $table->index(['vm_id', 'created_at'], 'idx_vm_created');
    }, 'idx_vm_created', $context);
    cloudstorage_ensure_table_index('s3_hyperv_backup_points', function ($table) {
        $table->index('manifest_id', 'idx_manifest');
    }, 'idx_manifest', $context);
    cloudstorage_ensure_table_index('s3_hyperv_backup_points', function ($table) {
        $table->index('run_id', 'idx_bp_run_id');
    }, 'idx_bp_run_id', $context);
    cloudstorage_ensure_table_index('s3_hyperv_backup_points', function ($table) {
        $table->index('has_warnings', 'idx_has_warnings');
    }, 'idx_has_warnings', $context);

    if (!$schema->hasTable('s3_hyperv_instant_restore_sessions') && $schema->hasTable('s3_hyperv_backup_points')) {
        try {
            $schema->create('s3_hyperv_instant_restore_sessions', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('backup_point_id');
                $table->string('target_host', 255)->nullable();
                $table->string('restored_vm_name', 255)->nullable();
                $table->enum('session_type', ['NBD', 'iSCSI', 'Direct'])->default('NBD');
                $table->string('nbd_address', 64)->nullable();
                $table->string('iscsi_target_iqn', 255)->nullable();
                $table->string('differential_vhdx_path', 1024)->nullable();
                $table->enum('status', ['Starting', 'Active', 'Migrating', 'Completed', 'Failed'])->default('Starting');
                $table->integer('migration_progress')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->index('status', 'idx_status');
                $table->index('backup_point_id', 'idx_backup_point');
                $table->foreign('backup_point_id')->references('id')->on('s3_hyperv_backup_points')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_hyperv_instant_restore_sessions table', [], []);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', "{$context}_create_s3_hyperv_instant_restore_sessions", [], $e->getMessage(), [], []);
        }
    }

    $hypervRestoreSessionColumns = [
        'backup_point_id' => function ($table) { $table->unsignedInteger('backup_point_id'); },
        'target_host' => function ($table) { $table->string('target_host', 255)->nullable(); },
        'restored_vm_name' => function ($table) { $table->string('restored_vm_name', 255)->nullable(); },
        'session_type' => function ($table) { $table->enum('session_type', ['NBD', 'iSCSI', 'Direct'])->default('NBD'); },
        'nbd_address' => function ($table) { $table->string('nbd_address', 64)->nullable(); },
        'iscsi_target_iqn' => function ($table) { $table->string('iscsi_target_iqn', 255)->nullable(); },
        'differential_vhdx_path' => function ($table) { $table->string('differential_vhdx_path', 1024)->nullable(); },
        'status' => function ($table) { $table->enum('status', ['Starting', 'Active', 'Migrating', 'Completed', 'Failed'])->default('Starting'); },
        'migration_progress' => function ($table) { $table->integer('migration_progress')->default(0); },
        'error_message' => function ($table) { $table->text('error_message')->nullable(); },
        'started_at' => function ($table) { $table->timestamp('started_at')->useCurrent(); },
        'completed_at' => function ($table) { $table->timestamp('completed_at')->nullable(); },
    ];
    foreach ($hypervRestoreSessionColumns as $columnName => $adder) {
        cloudstorage_ensure_table_column('s3_hyperv_instant_restore_sessions', $columnName, $adder, $context);
    }
    cloudstorage_ensure_table_index('s3_hyperv_instant_restore_sessions', function ($table) {
        $table->index('status', 'idx_status');
    }, 'idx_status', $context);
    cloudstorage_ensure_table_index('s3_hyperv_instant_restore_sessions', function ($table) {
        $table->index('backup_point_id', 'idx_backup_point');
    }, 'idx_backup_point', $context);
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

        cloudstorage_ensure_eb_run_id_custom_field('activate');

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
                // Opaque per-owner storage suffix used by the e3 Cloud Backup
                // bootstrap to compose customer-private bucket / uid names
                // (e.g. "e3cb-<token>"). Replaces the previous client-id based
                // names which let outsiders count signups from bucket names.
                'external_token' => function ($table) { $table->string('external_token', 40)->nullable()->after('manage_locked'); },
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
            try {
                if (Capsule::schema()->hasColumn('s3_users', 'external_token')) {
                    Capsule::schema()->table('s3_users', function ($table) {
                        $table->unique('external_token', 'idx_s3_users_external_token');
                    });
                }
            } catch (\Throwable $e) { /* index already exists */ }
        }

        if (!Capsule::schema()->hasTable('s3_prices')) {
            Capsule::schema()->create('s3_prices', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->unsignedBigInteger('usage_bytes')->nullable()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('s3_users')->onDelete('cascade');
            $table->index(['user_id', 'created_at'], 'idx_s3_prices_user_created');
            });
        } else {
            try {
                if (!Capsule::schema()->hasColumn('s3_prices', 'usage_bytes')) {
                    Capsule::schema()->table('s3_prices', function ($table) {
                        $table->unsignedBigInteger('usage_bytes')->nullable()->default(0)->after('amount');
                    });
                }
            } catch (\Throwable $e) { /* column exists or unsupported */ }
            try {
                Capsule::schema()->table('s3_prices', function ($table) {
                    $table->index(['user_id', 'created_at'], 'idx_s3_prices_user_created');
                });
            } catch (\Throwable $e) { /* index already exists */ }
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
                $table->string('agent_uuid', 36);
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('tenant_user_id')->nullable();
                $table->unsignedInteger('backup_user_id')->nullable();
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

                $table->unique('agent_uuid');
                $table->unique('agent_token');
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('tenant_user_id');
                $table->index('backup_user_id');
                $table->unique(['client_id', 'tenant_id', 'device_id'], 'uniq_agent_device_scope');
            });
        }
        cloudstorage_repair_agent_uuid_schema('activate');

        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            Capsule::schema()->create('s3_cloudbackup_jobs', function ($table) {
            $table->char('job_id', 16)->charset('binary')->primary();  // BINARY(16) UUIDv7 PK per design
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('tenant_id')->nullable();
            $table->string('repository_id', 64)->nullable();
            $table->unsignedInteger('s3_user_id');
            $table->unsignedInteger('backup_user_id')->nullable();
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
            $table->index('backup_user_id');
            $table->index('dest_bucket_id');
            $table->index(['schedule_type', 'status']);
            $table->foreign('s3_user_id')->references('id')->on('s3_users')->onDelete('cascade');
            $table->foreign('dest_bucket_id')->references('id')->on('s3_buckets')->onDelete('cascade');
            });
        }

        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            Capsule::schema()->create('s3_cloudbackup_runs', function ($table) {
            $table->char('run_id', 16)->charset('binary')->primary();  // BINARY(16) UUIDv7 PK
            $table->char('job_id', 16)->charset('binary');              // BINARY(16) FK -> jobs.job_id
            $table->unsignedInteger('tenant_id')->nullable();
            $table->string('repository_id', 64)->nullable();
            $table->enum('trigger_type', ['manual', 'schedule', 'validation'])->default('manual');
            $table->enum('status', ['queued', 'starting', 'running', 'success', 'warning', 'failed', 'cancelled', 'partial_success'])->default('queued');
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
            $table->foreign('job_id')->references('job_id')->on('s3_cloudbackup_jobs')->onDelete('cascade');
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
                $table->char('run_id', 16)->charset('binary');  // BINARY(16) FK -> runs.run_id
                $table->dateTime('ts'); // event timestamp (UTC)
                $table->string('type', 32); // start|progress|warning|error|summary|cancelled|validation_*
                $table->string('level', 16); // info|warn|error
                $table->string('code', 64); // PROGRESS_UPDATE|ERROR_NETWORK|...
                $table->string('message_id', 64); // i18n key
                $table->mediumText('params_json'); // JSON string of params
                $table->index(['run_id', 'ts']);
                $table->index(['run_id', 'id']);
                $table->foreign('run_id')->references('run_id')->on('s3_cloudbackup_runs')->onDelete('cascade');
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
                $table->unsignedInteger('backup_user_id')->nullable();
                $table->string('agent_uuid', 36)->nullable();
                $table->char('job_id', 16)->charset('binary')->nullable();   // BINARY(16) UUIDv7 FK -> jobs.job_id
                $table->string('job_name', 191)->nullable();
                $table->char('run_id', 16)->charset('binary')->nullable();   // BINARY(16) UUIDv7 FK -> runs.run_id
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
                $table->index('backup_user_id');
                $table->index('agent_uuid');
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
        if (Capsule::schema()->hasTable('s3_cloudbackup_restore_points') && !Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', 'backup_user_id')) {
            Capsule::schema()->table('s3_cloudbackup_restore_points', function ($table) {
                $table->unsignedInteger('backup_user_id')->nullable()->after('tenant_user_id');
                $table->index('backup_user_id');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Added backup_user_id to s3_cloudbackup_restore_points', [], []);
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
        // MSP / Tenant tables (legacy — skip if eazybackup has created eb_tenants)
        // -----------------------------
        if (!Capsule::schema()->hasTable('eb_tenants')) {
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
        }

        if (!Capsule::schema()->hasTable('eb_tenant_users')) {
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
        }

        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            Capsule::schema()->create('s3_backup_users', function ($table) {
                $table->increments('id');
                $table->char('public_id', 26)->nullable();
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->string('username', 191);
                $table->string('password_hash', 255);
                $table->string('email', 255);
                $table->enum('status', ['active', 'disabled'])->default('active');
                $table->enum('backup_type', ['cloud_only', 'local', 'both'])->default('both');
                $table->enum('encryption_mode', ['managed', 'strict'])->default('managed');
                $table->unsignedBigInteger('whmcs_service_id')->nullable();
                $table->tinyInteger('notifications_enabled')->default(1);
                $table->text('notify_emails')->nullable();
                $table->tinyInteger('notify_on_success')->default(0);
                $table->tinyInteger('notify_on_warning')->default(1);
                $table->tinyInteger('notify_on_failure')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique('public_id');
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
                'public_id' => function ($table) { $table->char('public_id', 26)->nullable(); },
                'client_id' => function ($table) { $table->unsignedInteger('client_id'); },
                'tenant_id' => function ($table) { $table->unsignedInteger('tenant_id')->nullable(); },
                'username' => function ($table) { $table->string('username', 191); },
                'password_hash' => function ($table) { $table->string('password_hash', 255); },
                'email' => function ($table) { $table->string('email', 255); },
                'status' => function ($table) { $table->enum('status', ['active', 'disabled'])->default('active'); },
                'backup_type' => function ($table) { $table->enum('backup_type', ['cloud_only', 'local', 'both'])->default('both'); },
                'encryption_mode' => function ($table) { $table->enum('encryption_mode', ['managed', 'strict'])->default('managed'); },
                'whmcs_service_id' => function ($table) { $table->unsignedBigInteger('whmcs_service_id')->nullable(); },
                'notifications_enabled' => function ($table) { $table->tinyInteger('notifications_enabled')->default(1); },
                'notify_emails' => function ($table) { $table->text('notify_emails')->nullable(); },
                'notify_on_success' => function ($table) { $table->tinyInteger('notify_on_success')->default(0); },
                'notify_on_warning' => function ($table) { $table->tinyInteger('notify_on_warning')->default(1); },
                'notify_on_failure' => function ($table) { $table->tinyInteger('notify_on_failure')->default(1); },
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
            try {
                Capsule::schema()->table('s3_backup_users', function ($table) {
                    $table->unique('public_id');
                });
            } catch (\Throwable $e) { /* index exists */ }
            cloudstorage_backfill_backup_user_public_ids('activate');
            cloudstorage_backfill_backup_user_encryption_mode('activate');
        }

        if (!Capsule::schema()->hasTable('s3_agent_enrollment_tokens')) {
            Capsule::schema()->create('s3_agent_enrollment_tokens', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('client_id');              // MSP/client who owns the token
                $table->unsignedInteger('tenant_id')->nullable();  // Scoped to tenant (NULL = direct client)
                $table->unsignedInteger('backup_user_id')->nullable(); // Scoped to backup user
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
                $table->index('backup_user_id');
                $table->index('expires_at');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_agent_enrollment_tokens table', [], []);
        }
        if (Capsule::schema()->hasTable('s3_agent_enrollment_tokens')) {
            if (!Capsule::schema()->hasColumn('s3_agent_enrollment_tokens', 'backup_user_id')) {
                Capsule::schema()->table('s3_agent_enrollment_tokens', function ($table) {
                    $table->unsignedInteger('backup_user_id')->nullable()->after('tenant_id');
                    $table->index('backup_user_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added backup_user_id to s3_agent_enrollment_tokens', [], []);
            }
        }

        if (!Capsule::schema()->hasTable('s3_agent_login_sessions')) {
            Capsule::schema()->create('s3_agent_login_sessions', function ($table) {
                $table->increments('id');
                $table->string('session_token', 64);
                $table->unsignedInteger('client_id');
                $table->string('hostname', 255)->nullable();
                $table->string('device_id', 128)->nullable();
                $table->string('install_id', 128)->nullable();
                $table->string('device_name', 255)->nullable();
                $table->string('agent_version', 64)->nullable();
                $table->string('agent_os', 32)->nullable();
                $table->string('agent_arch', 32)->nullable();
                $table->string('agent_build', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->dateTime('expires_at');
                $table->dateTime('consumed_at')->nullable();

                $table->unique('session_token');
                $table->index('client_id');
                $table->index('expires_at');
                $table->index('consumed_at');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_agent_login_sessions table', [], []);
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
                $table->char('session_run_id', 16)->charset('binary')->nullable();  // BINARY(16) UUIDv7 FK -> runs.run_id
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
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id')) {
                Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                    $table->unsignedInteger('backup_user_id')->nullable()->after('tenant_user_id');
                    $table->index('backup_user_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added backup_user_id to s3_cloudbackup_agents', [], []);
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
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->unsignedInteger('backup_user_id')->nullable()->after('s3_user_id');
                    $table->index('backup_user_id');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added backup_user_id to s3_cloudbackup_jobs', [], []);
            }
            // Add agent_uuid for local agent binding (nullable to preserve existing jobs)
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_uuid')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                    $table->string('agent_uuid', 36)->nullable()->after('client_id');
                    $table->index('agent_uuid');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_uuid to s3_cloudbackup_jobs', [], []);
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
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_uuid')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->string('agent_uuid', 36)->nullable()->after('job_id');
                    $table->index('agent_uuid');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_uuid to s3_cloudbackup_runs', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                    $table->index('updated_at');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added updated_at to s3_cloudbackup_runs', [], []);
            }
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'last_heartbeat_at')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->timestamp('last_heartbeat_at')->nullable()->after('updated_at');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added last_heartbeat_at to s3_cloudbackup_runs', [], []);
                try {
                    \WHMCS\Database\Capsule::statement(
                        'UPDATE s3_cloudbackup_runs SET last_heartbeat_at = COALESCE(updated_at, started_at, created_at) WHERE last_heartbeat_at IS NULL'
                    );
                } catch (\Throwable $e) {
                    // Best effort backfill.
                }
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

        // Composite indexes for MSP-scale access patterns (idempotent).
        // - restore points: scoped, time-ordered list query on the Restore tab.
        // - runs: latest-run-per-job window query on the Jobs tab.
        cloudstorage_ensure_table_index('s3_cloudbackup_restore_points', function ($table) {
            $table->index(['client_id', 'backup_user_id', 'created_at'], 'idx_rp_client_user_created');
        }, 'idx_rp_client_user_created');

        cloudstorage_ensure_table_index('s3_cloudbackup_runs', function ($table) {
            $table->index(['job_id', 'started_at'], 'idx_runs_job_started');
        }, 'idx_runs_job_started');

        cloudstorage_ensure_table_index('s3_cloudbackup_runs', function ($table) {
            $table->index(['status', 'last_heartbeat_at'], 'idx_runs_status_heartbeat');
        }, 'idx_runs_status_heartbeat');

        cloudstorage_ensure_table_index('s3_cloudbackup_runs', function ($table) {
            $table->index(['agent_uuid', 'status', 'last_heartbeat_at'], 'idx_runs_agent_status_heartbeat');
        }, 'idx_runs_agent_status_heartbeat');

        if (!Capsule::schema()->hasTable('s3_cloudbackup_agent_destinations')) {
            Capsule::schema()->create('s3_cloudbackup_agent_destinations', function ($table) {
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
                $table->foreign('agent_uuid')->references('agent_uuid')->on('s3_cloudbackup_agents')->onDelete('cascade');
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
                $table->char('run_id', 16)->charset('binary');  // BINARY(16) FK -> runs.run_id
                $table->timestamp('created_at')->useCurrent();
                $table->string('level', 16)->default('info');
                $table->string('code', 64)->nullable();
                $table->mediumText('message');
                $table->json('details_json')->nullable();
                $table->index(['run_id', 'created_at']);
                $table->foreign('run_id')->references('run_id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_run_logs table', [], []);
        }

        // Run commands table (for maintenance/restore/cancel extensions)
        if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            Capsule::schema()->create('s3_cloudbackup_run_commands', function ($table) {
                $table->bigIncrements('id');
                $table->char('run_id', 16)->charset('binary')->nullable();  // BINARY(16) FK -> runs.run_id; nullable for browse commands
                $table->string('type', 64); // cancel|maintenance_quick|maintenance_full|restore
                $table->json('payload_json')->nullable(); // target_path, manifest_id, etc.
                $table->enum('status', ['pending','processing','completed','failed'])->default('pending');
                $table->mediumText('result_message')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->index(['run_id','status']);
                $table->foreign('run_id')->references('run_id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_run_commands table', [], []);
        }

        // Add agent_uuid to run_commands for browse/discovery commands (not tied to a run)
        if (Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            if (!Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
                Capsule::schema()->table('s3_cloudbackup_run_commands', function ($table) {
                    $table->string('agent_uuid', 36)->nullable()->after('run_id');
                    $table->index('agent_uuid', 'idx_agent_uuid');
                });
                logModuleCall('cloudstorage', 'activate', [], 'Added agent_uuid to s3_cloudbackup_run_commands', [], []);
            }
            // Make run_id nullable - browse/discovery commands don't have a run_id
            // Skip if run_id is already BINARY(16) (UUID cutover schema)
            try {
                $col = Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_run_commands WHERE Field = 'run_id'");
                if ($col && stripos((string) ($col->Type ?? ''), 'binary') === false) {
                    Capsule::statement('ALTER TABLE s3_cloudbackup_run_commands MODIFY run_id BIGINT UNSIGNED NULL');
                    logModuleCall('cloudstorage', 'activate', [], 'Made run_id nullable in s3_cloudbackup_run_commands', [], []);
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'activate', [], 'run_id nullable modification skipped: ' . $e->getMessage(), [], []);
            }
        }

        // Agent/tray health events (non-run lifecycle)
        if (!Capsule::schema()->hasTable('s3_cloudbackup_agent_events')) {
            Capsule::schema()->create('s3_cloudbackup_agent_events', function ($table) {
                $table->bigIncrements('id');
                $table->string('agent_uuid', 36);
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('backup_user_id')->nullable();
                $table->dateTime('ts');
                $table->enum('source', ['agent', 'tray'])->default('agent');
                $table->enum('level', ['info', 'warn', 'error'])->default('info');
                $table->string('code', 64);
                $table->string('message_id', 64);
                $table->mediumText('params_json')->nullable();
                $table->string('dedupe_key', 191)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['agent_uuid', 'ts'], 'idx_agent_ts');
                $table->index(['client_id', 'ts'], 'idx_client_ts');
                $table->index(['agent_uuid', 'dedupe_key', 'ts'], 'idx_agent_dedupe_ts');
                $table->index(['source', 'ts'], 'idx_source_ts');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_agent_events table', [], []);
        }

        // Admin-only verbose run log chunks (gzipped)
        if (!Capsule::schema()->hasTable('s3_cloudbackup_admin_log_chunks')) {
            Capsule::schema()->create('s3_cloudbackup_admin_log_chunks', function ($table) {
                $table->bigIncrements('id');
                $table->char('run_id', 16)->charset('binary');
                $table->unsignedInteger('chunk_seq');
                $table->enum('source', ['agent', 'tray', 'run'])->default('run');
                $table->dateTime('first_ts');
                $table->dateTime('last_ts');
                $table->string('encoding', 16)->default('gzip');
                $table->binary('content_blob');
                $table->unsignedInteger('line_count')->default(0);
                $table->unsignedInteger('byte_count')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['run_id', 'chunk_seq'], 'uniq_run_chunk');
                $table->index(['run_id', 'first_ts'], 'idx_run_first_ts');
                $table->foreign('run_id')->references('run_id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            // Switch the BLOB column to LONGBLOB explicitly (Laravel ->binary() defaults to BLOB which caps at 64KB)
            try {
                Capsule::statement('ALTER TABLE s3_cloudbackup_admin_log_chunks MODIFY content_blob LONGBLOB NOT NULL');
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'activate', [], 's3_cloudbackup_admin_log_chunks MODIFY content_blob LONGBLOB skipped: ' . $e->getMessage(), [], []);
            }
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_cloudbackup_admin_log_chunks table', [], []);
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
        // Kopia Retention (Option 2) tables
        // -----------------------------
        if (!Capsule::schema()->hasTable('s3_kopia_policy_versions')) {
            Capsule::schema()->create('s3_kopia_policy_versions', function ($table) {
                $table->bigIncrements('id');
                $table->json('policy_json');
                $table->unsignedInteger('schema_version')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->index('schema_version');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_kopia_policy_versions table', [], []);
        }
        if (!Capsule::schema()->hasTable('s3_kopia_repos')) {
            Capsule::schema()->create('s3_kopia_repos', function ($table) {
                $table->bigIncrements('id');
                $table->string('repository_id', 64)->unique();
                $table->unsignedBigInteger('vault_policy_version_id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('bucket_id');
                $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('bucket_id');
                $table->index('vault_policy_version_id');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_kopia_repos table', [], []);
        }
        if (!Capsule::schema()->hasTable('s3_kopia_repo_sources')) {
            Capsule::schema()->create('s3_kopia_repo_sources', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('repo_id');
                $table->string('source_uuid', 64);
                $table->enum('lifecycle', ['active', 'retired', 'expired'])->default('active');
                $table->char('job_id', 16)->charset('binary')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->timestamp('retired_at')->nullable();
                $table->unique(['repo_id', 'source_uuid']);
                $table->index(['repo_id', 'lifecycle']);
                $table->index('job_id');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_kopia_repo_sources table', [], []);
        }
        if (Capsule::schema()->hasTable('s3_kopia_repo_sources') && !Capsule::schema()->hasColumn('s3_kopia_repo_sources', 'retired_at')) {
            Capsule::schema()->table('s3_kopia_repo_sources', function ($table) {
                $table->timestamp('retired_at')->nullable()->after('updated_at');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Added retired_at to s3_kopia_repo_sources', [], []);
        }
        if (Capsule::schema()->hasTable('s3_kopia_repo_sources') && !Capsule::schema()->hasColumn('s3_kopia_repo_sources', 'lifecycle')) {
            Capsule::schema()->table('s3_kopia_repo_sources', function ($table) {
                $table->enum('lifecycle', ['active', 'retired', 'expired'])->default('active')->after('source_uuid');
                $table->index(['repo_id', 'lifecycle']);
            });
            logModuleCall('cloudstorage', 'activate', [], 'Added lifecycle to s3_kopia_repo_sources', [], []);
        }
        if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')) {
            Capsule::schema()->create('s3_kopia_repo_operations', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('repo_id');
                $table->string('op_type', 64);
                $table->string('status', 32)->default('queued');
                $table->unsignedInteger('claimed_by_agent_id')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->string('operation_token', 128)->unique();
                $table->json('payload_json')->nullable();
                $table->json('result_json')->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['status', 'created_at']);
                $table->index(['repo_id', 'status']);
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_kopia_repo_operations table', [], []);
        }
        if (!Capsule::schema()->hasTable('s3_kopia_repo_locks')) {
            Capsule::schema()->create('s3_kopia_repo_locks', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('repo_id')->unique();
                $table->string('lock_token', 128);
                $table->unsignedInteger('claimed_by_agent_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('expires_at');
            });
            logModuleCall('cloudstorage', 'activate', [], 'Created s3_kopia_repo_locks table', [], []);
        }

        cloudstorage_ensure_hyperv_schema('activate');
        cloudstorage_ensure_agent_build_schema('activate');
        cloudstorage_ensure_agent_update_schema('activate');
        cloudstorage_ensure_ms365_vault_lifecycle_schema('activate');
        cloudstorage_ensure_e3cb_billing_schema('activate');
        cloudstorage_ensure_e3cb_product('activate');
        cloudstorage_ensure_e3bu_product('activate');

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
        cloudstorage_ensure_eb_run_id_custom_field('upgrade');

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

        // Ensure s3_prices.usage_bytes exists so the billing recompute can rebuild amounts
        // from stored usage when the storage rate or base fee changes.
        try {
            $schema = \WHMCS\Database\Capsule::schema();
            if ($schema->hasTable('s3_prices')) {
                if (!$schema->hasColumn('s3_prices', 'usage_bytes')) {
                    $schema->table('s3_prices', function ($table) {
                        $table->unsignedBigInteger('usage_bytes')->nullable()->default(0)->after('amount');
                    });
                    logModuleCall('cloudstorage', 'upgrade_s3_prices_add_usage_bytes', [], 'Added usage_bytes column to s3_prices', [], []);
                }
                try {
                    $schema->table('s3_prices', function ($table) {
                        $table->index(['user_id', 'created_at'], 'idx_s3_prices_user_created');
                    });
                } catch (\Throwable $__) { /* index already exists */ }
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'upgrade_s3_prices_add_usage_bytes_fail', [], $e->getMessage(), [], []);
        }

        // Phase 1 guardrails: schema additions for system-managed users, tenant snapshots, and agent destinations.
        try {
            $schema = \WHMCS\Database\Capsule::schema();
            cloudstorage_repair_agent_uuid_schema('upgrade');

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
                if (!$schema->hasColumn('s3_users', 'external_token')) {
                    $schema->table('s3_users', function ($table) {
                        $table->string('external_token', 40)->nullable()->after('manage_locked');
                    });
                }

                try {
                    if ($schema->hasColumn('s3_users', 'external_token')) {
                        $schema->table('s3_users', function ($table) { $table->unique('external_token', 'idx_s3_users_external_token'); });
                    }
                } catch (\Throwable $__) {}

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

            if ($schema->hasTable('s3_cloudbackup_restore_points') && !$schema->hasColumn('s3_cloudbackup_restore_points', 'backup_user_id')) {
                $schema->table('s3_cloudbackup_restore_points', function ($table) {
                    $table->unsignedInteger('backup_user_id')->nullable()->after('tenant_user_id');
                    $table->index('backup_user_id');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added backup_user_id to s3_cloudbackup_restore_points', [], []);
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
                    $table->foreign('agent_uuid')->references('agent_uuid')->on('s3_cloudbackup_agents')->onDelete('cascade');
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
                if (!$schema->hasColumn('s3_cloudbackup_jobs', 'job_id')) {
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
            }

            // Backfill runs.tenant_id from jobs.tenant_id snapshots.
            if (
                $schema->hasTable('s3_cloudbackup_runs') &&
                $schema->hasTable('s3_cloudbackup_jobs') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'tenant_id') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'job_id') &&
                $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id')
            ) {
                if (!$schema->hasColumn('s3_cloudbackup_runs', 'run_id')) {
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
            }

            // Backfill runs.repository_id from jobs.repository_id snapshots.
            if (
                $schema->hasTable('s3_cloudbackup_runs') &&
                $schema->hasTable('s3_cloudbackup_jobs') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'repository_id') &&
                $schema->hasColumn('s3_cloudbackup_runs', 'job_id') &&
                $schema->hasColumn('s3_cloudbackup_jobs', 'repository_id')
            ) {
                if (!$schema->hasColumn('s3_cloudbackup_runs', 'run_id')) {
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
                $rpRunJoin = $schema->hasColumn('s3_cloudbackup_runs', 'run_id') ? 'r.run_id' : 'r.id';
                while (true) {
                    $rows = \WHMCS\Database\Capsule::table('s3_cloudbackup_restore_points as rp')
                        ->join('s3_cloudbackup_runs as r', $rpRunJoin, '=', 'rp.run_id')
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

            if (
                $schema->hasTable('s3_cloudbackup_restore_points') &&
                $schema->hasTable('s3_cloudbackup_jobs') &&
                $schema->hasColumn('s3_cloudbackup_restore_points', 'backup_user_id') &&
                $schema->hasColumn('s3_cloudbackup_restore_points', 'job_id') &&
                $schema->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')
            ) {
                $updatedRestoreUsersFromJobs = 0;
                $lastRestoreUserId = 0;
                $chunk = 500;
                $rpJobJoin = $schema->hasColumn('s3_cloudbackup_jobs', 'job_id') ? 'j.job_id' : 'j.id';
                while (true) {
                    $rows = \WHMCS\Database\Capsule::table('s3_cloudbackup_restore_points as rp')
                        ->join('s3_cloudbackup_jobs as j', $rpJobJoin, '=', 'rp.job_id')
                        ->where('rp.id', '>', $lastRestoreUserId)
                        ->whereNull('rp.backup_user_id')
                        ->whereNotNull('j.backup_user_id')
                        ->select(['rp.id as restore_id', 'j.backup_user_id'])
                        ->orderBy('rp.id', 'asc')
                        ->limit($chunk)
                        ->get();
                    if (!$rows || count($rows) === 0) {
                        break;
                    }
                    foreach ($rows as $row) {
                        $lastRestoreUserId = (int) $row->restore_id;
                        try {
                            $updatedRestoreUsersFromJobs += (int) \WHMCS\Database\Capsule::table('s3_cloudbackup_restore_points')
                                ->where('id', (int) $row->restore_id)
                                ->whereNull('backup_user_id')
                                ->update(['backup_user_id' => (int) $row->backup_user_id]);
                        } catch (\Throwable $__) {}
                    }
                }
                logModuleCall('cloudstorage', 'upgrade_backfill_restore_points_backup_user_id_jobs', [], ['updated' => $updatedRestoreUsersFromJobs], [], []);
            }

            if (
                $schema->hasTable('s3_cloudbackup_restore_points') &&
                $schema->hasTable('s3_cloudbackup_agents') &&
                $schema->hasColumn('s3_cloudbackup_restore_points', 'backup_user_id') &&
                $schema->hasColumn('s3_cloudbackup_restore_points', 'agent_uuid') &&
                $schema->hasColumn('s3_cloudbackup_agents', 'backup_user_id')
            ) {
                $updatedRestoreUsersFromAgents = 0;
                $lastRestoreAgentUserId = 0;
                $chunk = 500;
                while (true) {
                    $rows = \WHMCS\Database\Capsule::table('s3_cloudbackup_restore_points as rp')
                        ->join('s3_cloudbackup_agents as a', 'a.agent_uuid', '=', 'rp.agent_uuid')
                        ->where('rp.id', '>', $lastRestoreAgentUserId)
                        ->whereNull('rp.backup_user_id')
                        ->whereNotNull('a.backup_user_id')
                        ->select(['rp.id as restore_id', 'a.backup_user_id'])
                        ->orderBy('rp.id', 'asc')
                        ->limit($chunk)
                        ->get();
                    if (!$rows || count($rows) === 0) {
                        break;
                    }
                    foreach ($rows as $row) {
                        $lastRestoreAgentUserId = (int) $row->restore_id;
                        try {
                            $updatedRestoreUsersFromAgents += (int) \WHMCS\Database\Capsule::table('s3_cloudbackup_restore_points')
                                ->where('id', (int) $row->restore_id)
                                ->whereNull('backup_user_id')
                                ->update(['backup_user_id' => (int) $row->backup_user_id]);
                        } catch (\Throwable $__) {}
                    }
                }
                logModuleCall('cloudstorage', 'upgrade_backfill_restore_points_backup_user_id_agents', [], ['updated' => $updatedRestoreUsersFromAgents], [], []);
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

        // Kopia Retention (Option 2) tables
        $kopiaSchema = \WHMCS\Database\Capsule::schema();
        if (!$kopiaSchema->hasTable('s3_kopia_policy_versions')) {
            $kopiaSchema->create('s3_kopia_policy_versions', function ($table) {
                $table->bigIncrements('id');
                $table->json('policy_json');
                $table->unsignedInteger('schema_version')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->index('schema_version');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_kopia_policy_versions table', [], []);
        }
        if (!$kopiaSchema->hasTable('s3_kopia_repos')) {
            $kopiaSchema->create('s3_kopia_repos', function ($table) {
                $table->bigIncrements('id');
                $table->string('repository_id', 64)->unique();
                $table->unsignedBigInteger('vault_policy_version_id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('bucket_id');
                $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('client_id');
                $table->index('tenant_id');
                $table->index('bucket_id');
                $table->index('vault_policy_version_id');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_kopia_repos table', [], []);
        }
        if (!$kopiaSchema->hasTable('s3_kopia_repo_sources')) {
            $kopiaSchema->create('s3_kopia_repo_sources', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('repo_id');
                $table->string('source_uuid', 64);
                $table->enum('lifecycle', ['active', 'retired', 'expired'])->default('active');
                $table->char('job_id', 16)->charset('binary')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->timestamp('retired_at')->nullable();
                $table->unique(['repo_id', 'source_uuid']);
                $table->index(['repo_id', 'lifecycle']);
                $table->index('job_id');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_kopia_repo_sources table', [], []);
        }
        if ($kopiaSchema->hasTable('s3_kopia_repo_sources') && !$kopiaSchema->hasColumn('s3_kopia_repo_sources', 'retired_at')) {
            $kopiaSchema->table('s3_kopia_repo_sources', function ($table) {
                $table->timestamp('retired_at')->nullable()->after('updated_at');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Added retired_at to s3_kopia_repo_sources', [], []);
        }
        if ($kopiaSchema->hasTable('s3_kopia_repo_sources') && !$kopiaSchema->hasColumn('s3_kopia_repo_sources', 'lifecycle')) {
            $kopiaSchema->table('s3_kopia_repo_sources', function ($table) {
                $table->enum('lifecycle', ['active', 'retired', 'expired'])->default('active')->after('source_uuid');
                $table->index(['repo_id', 'lifecycle']);
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Added lifecycle to s3_kopia_repo_sources', [], []);
        }
        if (!$kopiaSchema->hasTable('s3_kopia_repo_operations')) {
            $kopiaSchema->create('s3_kopia_repo_operations', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('repo_id');
                $table->string('op_type', 64);
                $table->string('status', 32)->default('queued');
                $table->unsignedInteger('claimed_by_agent_id')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->string('operation_token', 128)->unique();
                $table->json('payload_json')->nullable();
                $table->json('result_json')->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['status', 'created_at']);
                $table->index(['repo_id', 'status']);
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_kopia_repo_operations table', [], []);
        }
        if (!$kopiaSchema->hasTable('s3_kopia_repo_locks')) {
            $kopiaSchema->create('s3_kopia_repo_locks', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('repo_id')->unique();
                $table->string('lock_token', 128);
                $table->unsignedInteger('claimed_by_agent_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index('expires_at');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_kopia_repo_locks table', [], []);
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
                    MODIFY COLUMN `source_type` ENUM('s3_compatible','aws','sftp','google_drive','dropbox','smb','nas','local_agent','ms365') NOT NULL DEFAULT 's3_compatible'
                ");
            } catch (\Exception $e) {
                // Safe to ignore if already updated, but log for visibility
                logModuleCall('cloudstorage', 'upgrade_enum_source_type', [], $e->getMessage(), [], []);
            }
        }

        foreach (['s3_cloudbackup_jobs', 's3_cloudbackup_runs'] as $engineTable) {
            if (!\WHMCS\Database\Capsule::schema()->hasTable($engineTable)
                || !\WHMCS\Database\Capsule::schema()->hasColumn($engineTable, 'engine')) {
                continue;
            }
            try {
                $columnMeta = \WHMCS\Database\Capsule::select("SHOW COLUMNS FROM `{$engineTable}` WHERE Field = 'engine'");
                $typeStr = strtolower((string) ($columnMeta[0]->Type ?? ''));
                if ($typeStr !== '' && strpos($typeStr, "enum(") !== false && strpos($typeStr, "'ms365'") === false) {
                    \WHMCS\Database\Capsule::statement("ALTER TABLE `{$engineTable}` MODIFY COLUMN `engine` ENUM('sync', 'kopia', 'disk_image', 'hyperv', 'ms365') NOT NULL DEFAULT 'sync'");
                    logModuleCall('cloudstorage', 'upgrade_extend_engine_enum_ms365', [], "Added ms365 to {$engineTable}.engine", [], []);
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade_extend_engine_enum_ms365_fail', ['table' => $engineTable], $e->getMessage(), [], []);
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
                $table->unsignedInteger('backup_user_id')->nullable();
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
                $table->index('backup_user_id');
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
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_jobs', function ($table) {
                        $table->unsignedInteger('backup_user_id')->nullable()->after('s3_user_id');
                        $table->index('backup_user_id');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added backup_user_id to s3_cloudbackup_jobs', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_backup_user_id', [], $e->getMessage(), [], []);
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
                $table->enum('status', ['queued', 'starting', 'running', 'success', 'warning', 'failed', 'cancelled', 'partial_success'])->default('queued');
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
            // Ensure status enum includes partial_success (Hyper-V multi-VM partial outcomes).
            try {
                $colType = \WHMCS\Database\Capsule::select("SHOW COLUMNS FROM s3_cloudbackup_runs WHERE Field = 'status'");
                if (!empty($colType)) {
                    $type = strtolower((string) ($colType[0]->Type ?? ''));
                    if (strpos($type, "enum(") !== false && strpos($type, "'partial_success'") === false) {
                        \WHMCS\Database\Capsule::statement("ALTER TABLE s3_cloudbackup_runs MODIFY COLUMN status ENUM('queued','starting','running','success','warning','failed','cancelled','partial_success') NOT NULL DEFAULT 'queued'");
                        logModuleCall('cloudstorage', 'upgrade_extend_runs_status_enum', [], 'Extended runs.status enum to include partial_success', [], []);
                    }
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade_extend_runs_status_enum_fail', [], $e->getMessage(), [], []);
            }

            // Phase 2A (beta hardening): widen narrow columns the agent now writes to so
            // that strict-mode MySQL inserts do not start silently failing on customer
            // DBs. These ALTERs are idempotent (LIKE-checked) and run once at upgrade.
            $widenChecks = [
                // table => [column, target type, predicate (sub-string of current Type
                // that means we should leave it alone)]
                ['table' => 's3_cloudbackup_run_events',   'col' => 'message_id',    'type' => 'VARCHAR(128)', 'skip_if' => 'varchar(128'],
                ['table' => 's3_cloudbackup_run_events',   'col' => 'level',         'type' => 'VARCHAR(16)',  'skip_if' => 'varchar(16'],
                ['table' => 's3_cloudbackup_run_events',   'col' => 'type',          'type' => 'VARCHAR(32)',  'skip_if' => 'varchar(32'],
                ['table' => 's3_cloudbackup_run_events',   'col' => 'code',          'type' => 'VARCHAR(128)', 'skip_if' => 'varchar(128'],
                ['table' => 's3_cloudbackup_run_commands', 'col' => 'type',          'type' => 'VARCHAR(64)',  'skip_if' => 'varchar(64'],
                ['table' => 's3_cloudbackup_runs',         'col' => 'current_item',  'type' => 'VARCHAR(1024)', 'skip_if' => 'varchar(1024'],
            ];
            foreach ($widenChecks as $w) {
                try {
                    if (!\WHMCS\Database\Capsule::schema()->hasTable($w['table'])) {
                        continue;
                    }
                    $col = \WHMCS\Database\Capsule::select(
                        "SHOW COLUMNS FROM `{$w['table']}` WHERE Field = ?",
                        [$w['col']]
                    );
                    if (empty($col)) {
                        continue;
                    }
                    $type = strtolower((string) ($col[0]->Type ?? ''));
                    if ($type === '' || strpos($type, $w['skip_if']) !== false) {
                        continue;
                    }
                    // Only widen, never narrow: only proceed when the column is a
                    // smaller varchar than the target.
                    if (strpos($type, 'varchar(') !== 0) {
                        continue;
                    }
                    \WHMCS\Database\Capsule::statement(
                        "ALTER TABLE `{$w['table']}` MODIFY COLUMN `{$w['col']}` {$w['type']}"
                    );
                    logModuleCall(
                        'cloudstorage',
                        'upgrade_widen_column',
                        ['table' => $w['table'], 'col' => $w['col'], 'from' => $type, 'to' => $w['type']],
                        'Widened column for strict-mode safety',
                        [],
                        []
                    );
                } catch (\Throwable $e) {
                    logModuleCall('cloudstorage', 'upgrade_widen_column_fail', $w, $e->getMessage(), [], []);
                }
            }

            // Ensure run_commands.status ENUM includes 'cancelled' so a server-side
            // cancel from a guard / overlap-rejection path can be recorded without
            // truncation when strict mode is on.
            try {
                $colType = \WHMCS\Database\Capsule::select("SHOW COLUMNS FROM s3_cloudbackup_run_commands WHERE Field = 'status'");
                if (!empty($colType)) {
                    $type = strtolower((string) ($colType[0]->Type ?? ''));
                    if (strpos($type, "enum(") !== false && strpos($type, "'cancelled'") === false) {
                        \WHMCS\Database\Capsule::statement("ALTER TABLE s3_cloudbackup_run_commands MODIFY COLUMN status ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending'");
                        logModuleCall('cloudstorage', 'upgrade_extend_run_commands_status_enum', [], 'Extended run_commands.status enum to include cancelled', [], []);
                    }
                }
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade_extend_run_commands_status_enum_fail', [], $e->getMessage(), [], []);
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
                    // Backfill existing rows with UUIDs (legacy schema only)
                    if (!$schema->hasColumn('s3_cloudbackup_runs', 'run_id')) {
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
                    }
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

        // Create agent/tray health events table if missing on upgrade
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_agent_events')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_agent_events', function ($table) {
                $table->bigIncrements('id');
                $table->string('agent_uuid', 36);
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('tenant_id')->nullable();
                $table->unsignedInteger('backup_user_id')->nullable();
                $table->dateTime('ts');
                $table->enum('source', ['agent', 'tray'])->default('agent');
                $table->enum('level', ['info', 'warn', 'error'])->default('info');
                $table->string('code', 64);
                $table->string('message_id', 64);
                $table->mediumText('params_json')->nullable();
                $table->string('dedupe_key', 191)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['agent_uuid', 'ts'], 'idx_agent_ts');
                $table->index(['client_id', 'ts'], 'idx_client_ts');
                $table->index(['agent_uuid', 'dedupe_key', 'ts'], 'idx_agent_dedupe_ts');
                $table->index(['source', 'ts'], 'idx_source_ts');
            });
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_agent_events table', [], []);
        }

        // Create admin verbose log chunks table if missing on upgrade
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_admin_log_chunks')) {
            \WHMCS\Database\Capsule::schema()->create('s3_cloudbackup_admin_log_chunks', function ($table) {
                $table->bigIncrements('id');
                $table->char('run_id', 16)->charset('binary');
                $table->unsignedInteger('chunk_seq');
                $table->enum('source', ['agent', 'tray', 'run'])->default('run');
                $table->dateTime('first_ts');
                $table->dateTime('last_ts');
                $table->string('encoding', 16)->default('gzip');
                $table->binary('content_blob');
                $table->unsignedInteger('line_count')->default(0);
                $table->unsignedInteger('byte_count')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['run_id', 'chunk_seq'], 'uniq_run_chunk');
                $table->index(['run_id', 'first_ts'], 'idx_run_first_ts');
                $table->foreign('run_id')->references('run_id')->on('s3_cloudbackup_runs')->onDelete('cascade');
            });
            try {
                \WHMCS\Database\Capsule::statement('ALTER TABLE s3_cloudbackup_admin_log_chunks MODIFY content_blob LONGBLOB NOT NULL');
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade', [], 's3_cloudbackup_admin_log_chunks MODIFY content_blob LONGBLOB skipped: ' . $e->getMessage(), [], []);
            }
            logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_cloudbackup_admin_log_chunks table', [], []);
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

        // Add agent_uuid to run_commands for browse/discovery commands (not tied to a run)
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_run_commands', function ($table) {
                    $table->string('agent_uuid', 36)->nullable()->after('run_id');
                    $table->index('agent_uuid', 'idx_run_cmd_agent_uuid');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Added agent_uuid to s3_cloudbackup_run_commands', [], []);
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
                $table->unsignedInteger('backup_user_id')->nullable();
                $table->string('agent_uuid', 36)->nullable();
                $table->char('job_id', 16)->charset('binary')->nullable();   // BINARY(16) UUIDv7 FK -> jobs.job_id
                $table->string('job_name', 191)->nullable();
                $table->char('run_id', 16)->charset('binary')->nullable();   // BINARY(16) UUIDv7 FK -> runs.run_id
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
                $table->index('backup_user_id');
                $table->index('agent_uuid');
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
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_restore_points', 'backup_user_id')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_restore_points', function ($table) {
                        $table->unsignedInteger('backup_user_id')->nullable()->after('tenant_user_id');
                        $table->index('backup_user_id');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added backup_user_id to s3_cloudbackup_restore_points', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_restore_points_backup_user_id_error', [], $e->getMessage(), [], []);
                }
            }

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

        // Composite indexes for MSP-scale access patterns (idempotent). Kept in
        // sync with cloudstorage_activate() so production code deploys that run
        // the upgrade routine (version bump) get the same indexes as a fresh
        // activate. The helper swallows "index already exists" errors.
        //   - restore points: scoped, time-ordered Restore-tab list query.
        //   - runs: latest-run-per-job window query on the Jobs tab.
        cloudstorage_ensure_table_index('s3_cloudbackup_restore_points', function ($table) {
            $table->index(['client_id', 'backup_user_id', 'created_at'], 'idx_rp_client_user_created');
        }, 'idx_rp_client_user_created', 'upgrade');

        cloudstorage_ensure_table_index('s3_cloudbackup_runs', function ($table) {
            $table->index(['job_id', 'started_at'], 'idx_runs_job_started');
        }, 'idx_runs_job_started', 'upgrade');

        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_runs')
            && !\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'last_heartbeat_at')) {
            try {
                \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_runs', function ($table) {
                    $table->timestamp('last_heartbeat_at')->nullable()->after('updated_at');
                });
                \WHMCS\Database\Capsule::statement(
                    'UPDATE s3_cloudbackup_runs SET last_heartbeat_at = COALESCE(updated_at, started_at, created_at) WHERE last_heartbeat_at IS NULL'
                );
                logModuleCall('cloudstorage', 'upgrade', [], 'Added last_heartbeat_at to s3_cloudbackup_runs', [], []);
            } catch (\Throwable $e) {
                logModuleCall('cloudstorage', 'upgrade_last_heartbeat_at_fail', [], $e->getMessage(), [], []);
            }
        }

        cloudstorage_ensure_table_index('s3_cloudbackup_runs', function ($table) {
            $table->index(['status', 'last_heartbeat_at'], 'idx_runs_status_heartbeat');
        }, 'idx_runs_status_heartbeat', 'upgrade');

        cloudstorage_ensure_table_index('s3_cloudbackup_runs', function ($table) {
            $table->index(['agent_uuid', 'status', 'last_heartbeat_at'], 'idx_runs_agent_status_heartbeat');
        }, 'idx_runs_agent_status_heartbeat', 'upgrade');

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
                $table->char('session_run_id', 16)->charset('binary')->nullable();  // BINARY(16) UUIDv7 FK -> runs.run_id
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
                    $table->char('public_id', 26)->nullable();
                    $table->unsignedInteger('client_id');
                    $table->unsignedInteger('tenant_id')->nullable();
                    $table->string('username', 191);
                    $table->string('password_hash', 255);
                    $table->string('email', 255);
                    $table->enum('status', ['active', 'disabled'])->default('active');
                    $table->enum('backup_type', ['cloud_only', 'local', 'both'])->default('both');
                    $table->enum('encryption_mode', ['managed', 'strict'])->default('managed');
                    $table->unsignedBigInteger('whmcs_service_id')->nullable();
                    $table->tinyInteger('notifications_enabled')->default(1);
                    $table->text('notify_emails')->nullable();
                    $table->tinyInteger('notify_on_success')->default(0);
                    $table->tinyInteger('notify_on_warning')->default(1);
                    $table->tinyInteger('notify_on_failure')->default(1);
                    $table->timestamp('created_at')->useCurrent();
                    $table->timestamp('updated_at')->useCurrent();
                    $table->unique('public_id');
                    $table->unique(['client_id', 'tenant_id', 'username'], 'uniq_backup_users_scope_username');
                    $table->index('client_id');
                    $table->index('tenant_id');
                    $table->index('status');
                    $table->index('email');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_backup_users table', [], []);
            }

            $backupUserColDefs = [
                'public_id' => function ($table) { $table->char('public_id', 26)->nullable(); },
                'client_id' => function ($table) { $table->unsignedInteger('client_id'); },
                'tenant_id' => function ($table) { $table->unsignedInteger('tenant_id')->nullable(); },
                'username' => function ($table) { $table->string('username', 191); },
                'password_hash' => function ($table) { $table->string('password_hash', 255); },
                'email' => function ($table) { $table->string('email', 255); },
                'status' => function ($table) { $table->enum('status', ['active', 'disabled'])->default('active'); },
                'backup_type' => function ($table) { $table->enum('backup_type', ['cloud_only', 'local', 'both'])->default('both'); },
                'encryption_mode' => function ($table) { $table->enum('encryption_mode', ['managed', 'strict'])->default('managed'); },
                'whmcs_service_id' => function ($table) { $table->unsignedBigInteger('whmcs_service_id')->nullable(); },
                'notifications_enabled' => function ($table) { $table->tinyInteger('notifications_enabled')->default(1); },
                'notify_emails' => function ($table) { $table->text('notify_emails')->nullable(); },
                'notify_on_success' => function ($table) { $table->tinyInteger('notify_on_success')->default(0); },
                'notify_on_warning' => function ($table) { $table->tinyInteger('notify_on_warning')->default(1); },
                'notify_on_failure' => function ($table) { $table->tinyInteger('notify_on_failure')->default(1); },
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
            try {
                $schema->table('s3_backup_users', function ($table) {
                    $table->unique('public_id');
                });
            } catch (\Throwable $e) {}
            cloudstorage_backfill_backup_user_public_ids('upgrade');
            cloudstorage_backfill_backup_user_encryption_mode('upgrade');
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'upgrade_s3_backup_users_fail', [], $e->getMessage(), [], []);
        }

        // Add backup_user_id to agents and enrollment tokens for per-user scoping
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_cloudbackup_agents', function ($table) {
                        $table->unsignedInteger('backup_user_id')->nullable()->after('tenant_user_id');
                        $table->index('backup_user_id');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added backup_user_id to s3_cloudbackup_agents', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_agents_backup_user_id', [], $e->getMessage(), [], []);
                }
            }
        }
        if (\WHMCS\Database\Capsule::schema()->hasTable('s3_agent_enrollment_tokens')) {
            if (!\WHMCS\Database\Capsule::schema()->hasColumn('s3_agent_enrollment_tokens', 'backup_user_id')) {
                try {
                    \WHMCS\Database\Capsule::schema()->table('s3_agent_enrollment_tokens', function ($table) {
                        $table->unsignedInteger('backup_user_id')->nullable()->after('tenant_id');
                        $table->index('backup_user_id');
                    });
                    logModuleCall('cloudstorage', 'upgrade', [], 'Added backup_user_id to s3_agent_enrollment_tokens', [], []);
                } catch (\Exception $e) {
                    logModuleCall('cloudstorage', 'upgrade_add_tokens_backup_user_id', [], $e->getMessage(), [], []);
                }
            }
        }
        if (!\WHMCS\Database\Capsule::schema()->hasTable('s3_agent_login_sessions')) {
            try {
                \WHMCS\Database\Capsule::schema()->create('s3_agent_login_sessions', function ($table) {
                    $table->increments('id');
                    $table->string('session_token', 64);
                    $table->unsignedInteger('client_id');
                    $table->string('hostname', 255)->nullable();
                    $table->string('device_id', 128)->nullable();
                    $table->string('install_id', 128)->nullable();
                    $table->string('device_name', 255)->nullable();
                    $table->string('agent_version', 64)->nullable();
                    $table->string('agent_os', 32)->nullable();
                    $table->string('agent_arch', 32)->nullable();
                    $table->string('agent_build', 64)->nullable();
                    $table->timestamp('created_at')->useCurrent();
                    $table->dateTime('expires_at');
                    $table->dateTime('consumed_at')->nullable();

                    $table->unique('session_token');
                    $table->index('client_id');
                    $table->index('expires_at');
                    $table->index('consumed_at');
                });
                logModuleCall('cloudstorage', 'upgrade', [], 'Created s3_agent_login_sessions table', [], []);
            } catch (\Exception $e) {
                logModuleCall('cloudstorage', 'upgrade_create_agent_login_sessions', [], $e->getMessage(), [], []);
            }
        }

        cloudstorage_ensure_hyperv_schema('upgrade');
        cloudstorage_ensure_agent_build_schema('upgrade');
        cloudstorage_ensure_agent_update_schema('upgrade');
        cloudstorage_ensure_ms365_vault_lifecycle_schema('upgrade');
        cloudstorage_ensure_e3cb_billing_schema('upgrade');
        cloudstorage_ensure_e3cb_product('upgrade');
        cloudstorage_ensure_e3bu_product('upgrade');
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
    $turnstileUseInvisible = (($config['turnstile_use_invisible'] ?? '') === 'on');

    switch ($page) {
        case 'signup':
            $pagetitle = 'e3 Storage Signup';
            $templatefile = 'templates/signup';
            $requireLogin = false;
            $viewVars = [
                'TURNSTILE_SITE_KEY' => $turnstileSiteKey,
                'TURNSTILE_USE_INVISIBLE' => $turnstileUseInvisible,
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
            $viewVars['TURNSTILE_USE_INVISIBLE'] = $turnstileUseInvisible;
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
            $viewVars['TURNSTILE_USE_INVISIBLE'] = $turnstileUseInvisible;
            break;

        case 'verifytrial':
            $pagetitle = 'Verify e3 Trial';
            $templatefile = 'templates/signup';
            $requireLogin = false;
            $viewVars = require __DIR__ . '/pages/verifytrial.php';
            if (empty($viewVars['TURNSTILE_SITE_KEY'])) {
                $viewVars['TURNSTILE_SITE_KEY'] = $turnstileSiteKey;
            }
            $viewVars['TURNSTILE_USE_INVISIBLE'] = $turnstileUseInvisible;
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
            // Layered beta gate for the e3 Cloud Backup card.
            try {
                require_once __DIR__ . '/lib/Beta/BetaGate.php';
                $welcomeClientId = (int) $clientArea->getUserID();
                $viewVars['EB_SHOW_E3_BACKUP'] = \WHMCS\Module\Addon\CloudStorage\Beta\BetaGate::isE3BackupVisible($welcomeClientId);
            } catch (\Throwable $e) {
                $viewVars['EB_SHOW_E3_BACKUP'] = false;
            }
            // Portal-password gate (Round 2): if the client still needs to set
            // their client-area password, block product selection behind a
            // modal that fires the cached-plaintext set_portal_password flow.
            // The flag is sticky for the welcome session (we also re-arm it
            // here if a cached plaintext is missing) so the modal still pops
            // for sessions that pre-date this feature.
            try {
                $welcomeClientId = isset($welcomeClientId) ? (int) $welcomeClientId : (int) $clientArea->getUserID();
                $needsPwSet = false;
                if (function_exists('eazybackup_must_set_password')) {
                    $needsPwSet = (bool) eazybackup_must_set_password($welcomeClientId);
                }
                $viewVars['ebMustSetPortalPassword'] = $needsPwSet;
                $viewVars['ebPortalPasswordCached']  = !empty($_SESSION['eb_portal_password_for_provision']);
            } catch (\Throwable $e) {
                $viewVars['ebMustSetPortalPassword'] = false;
                $viewVars['ebPortalPasswordCached']  = false;
            }
            try {
                $welcomeClientId = isset($welcomeClientId) ? (int) $welcomeClientId : (int) $clientArea->getUserID();
                require_once __DIR__ . '/lib/Client/WelcomeClientState.php';
                $viewVars['ebHideLegacyCloudBackupCard'] = \WHMCS\Module\Addon\CloudStorage\Client\WelcomeClientState::clientHasLegacyCometBackup($welcomeClientId);
                $viewVars['ebWelcomeExistingClient']     = \WHMCS\Module\Addon\CloudStorage\Client\WelcomeClientState::isWelcomeExistingClient($welcomeClientId);
            } catch (\Throwable $e) {
                $viewVars['ebHideLegacyCloudBackupCard'] = false;
                $viewVars['ebWelcomeExistingClient']     = false;
            }
            try {
                require_once __DIR__ . '/lib/Provision/E3BackupUserProductBootstrap.php';
                $viewVars['ebWelcomeUnifiedEnabled'] = \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::isUnifiedEnabled();
            } catch (\Throwable $e) {
                $viewVars['ebWelcomeUnifiedEnabled'] = false;
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
            $viewParam = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
            $view = ($viewParam !== '' && $viewParam !== 'home') ? $viewParam : 'dashboard';

            // Smart landing: empty/default/home view resolves to the correct onboarding
            // entry point (MS365 GS > agent GS > dashboard).
            if ($viewParam === '' || $viewParam === 'home') {
                try {
                    $landingClientId = 0;
                    $caLanding = new \WHMCS\ClientArea();
                    if ($caLanding->isLoggedIn()) {
                        $landingClientId = (int) $caLanding->getUserID();
                    }
                    if ($landingClientId > 0) {
                        $statePath = __DIR__ . '/lib/Client/E3BackupClientState.php';
                        if (is_file($statePath)) {
                            require_once $statePath;
                        }
                        if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\E3BackupClientState')) {
                            $landingUrl = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState::resolveLandingUrl($landingClientId);
                            $dashboardUrl = 'index.php?m=cloudstorage&page=e3backup';
                            if ($landingUrl !== $dashboardUrl) {
                                header('Location: ' . $landingUrl);
                                exit;
                            }
                        }
                    }
                } catch (\Throwable $_) {
                }
            }

            // Legacy e3 tenant views (tenants / tenant_detail / tenant_members /
            // tenant_users) were replaced by Partner Hub (m=eazybackup). The old
            // templates have been removed; forward any bookmarked legacy URLs to
            // the canonical Partner Hub routes so they don't dead-end.
            if (in_array($view, ['tenants', 'tenant_detail', 'tenant_members', 'tenant_users'], true)) {
                $ca = new \WHMCS\ClientArea();
                if (!$ca->isLoggedIn()) {
                    header('Location: clientarea.php');
                    exit;
                }
                $loggedInUserId = (int) $ca->getUserID();
                if (!\WHMCS\Module\Addon\CloudStorage\Client\MspController::isMspClient($loggedInUserId)) {
                    header('Location: index.php?m=cloudstorage&page=e3backup');
                    exit;
                }
                $tenantPublicId = \WHMCS\Module\Addon\CloudStorage\Client\MspController::resolveTenantPublicIdForClient((string) ($_GET['tenant_id'] ?? ''), $loggedInUserId) ?? '';
                $mode = strtolower(trim((string) ($_GET['mode'] ?? '')));

                $targetUrl = 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenants';
                if ($view === 'tenant_detail') {
                    if ($mode === 'create') {
                        $targetUrl = 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-create';
                    } elseif ($tenantPublicId !== '') {
                        $targetUrl = 'index.php?m=eazybackup&a=ph-tenant&id=' . rawurlencode($tenantPublicId) . '&legacy=e3-tenant-detail';
                    }
                } elseif ($view === 'tenant_members' || $view === 'tenant_users') {
                    $targetUrl = $tenantPublicId !== ''
                        ? 'index.php?m=eazybackup&a=ph-tenant-members&id=' . rawurlencode($tenantPublicId) . '&legacy=e3-tenant-members'
                        : 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-members';
                } elseif ($tenantPublicId !== '') {
                    $targetUrl = 'index.php?m=eazybackup&a=ph-tenant&id=' . rawurlencode($tenantPublicId) . '&legacy=e3-tenants';
                }

                header('Location: ' . $targetUrl);
                exit;
            }

            // Pre-compute onboarding/admin state once for every e3backup view so
            // the shell + sidebar can render their first-run UI without each
            // page handler having to remember to do it. Read-only and cheap.
            $ebE3OnboardingShared = [
                'ebE3OnboardingState'     => null,
                'ebE3OnboardingCompleted' => 0,
                'ebE3OnboardingTotal'     => 4,
                'ebE3OnboardingComplete'  => false,
                'ebE3OnboardingHidden'    => false,
                'ebE3HasAgents'           => false,
                'ebIsAdminSession'        => !empty($_SESSION['adminid']),
                'ebMs365Only'             => false,
                'ebMs365OnboardingState'  => null,
                'ebMs365OnboardingCompleted' => 0,
                'ebMs365OnboardingTotal'  => 3,
                'ebMs365OnboardingComplete' => false,
                'ebMs365OnboardingHidden' => true,
                'ebMs365ShowGettingStarted' => false,
                'ebHasE3AgentProduct'     => false,
                'ebHasMs365Product'       => false,
                'ebHasCloudStorageProduct' => false,
                'ebShowEnableAgentCard'   => false,
                'ebShowEnableMs365Card'   => false,
                'ebGsActiveWorkload'      => 'local',
                'ebGsIntent'              => 'local',
                'ebGsUserId'              => '',
                'ebGsCompleted'             => 0,
                'ebGsTotal'                 => 4,
                'ebGsComplete'              => false,
                'ebGsHidden'                => true,
            ];
            try {
                $obStatePath = __DIR__ . '/lib/Client/OnboardingState.php';
                if (is_file($obStatePath)) {
                    require_once $obStatePath;
                }
                $obClientId = 0;
                try {
                    $caE3 = new \WHMCS\ClientArea();
                    if ($caE3->isLoggedIn()) {
                        $obClientId = (int) $caE3->getUserID();
                    }
                } catch (\Throwable $_) {
                    $obClientId = 0;
                }
                if ($obClientId > 0 && class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\OnboardingState')) {
                    $obState = \WHMCS\Module\Addon\CloudStorage\Client\OnboardingState::compute($obClientId);
                    $ebE3OnboardingShared['ebE3OnboardingState']     = $obState;
                    $ebE3OnboardingShared['ebE3OnboardingCompleted'] = (int) ($obState['completed_count'] ?? 0);
                    $ebE3OnboardingShared['ebE3OnboardingTotal']     = (int) ($obState['total_count'] ?? 4);
                    $ebE3OnboardingShared['ebE3OnboardingComplete']  = (bool) ($obState['all_complete'] ?? false);
                    $ebE3OnboardingShared['ebE3HasAgents']           = !empty($obState['steps']['agent_online']['complete']);
                    // Hide the Getting Started link once everything is done AND
                    // the customer dismissed/completed the tour.
                    $ebE3OnboardingShared['ebE3OnboardingHidden'] = (
                        !empty($obState['all_complete'])
                        && (!empty($obState['tour_completed']) || !empty($obState['tour_dismissed']))
                    );
                }

                $e3AccessPath = __DIR__ . '/lib/Client/E3BackupAccess.php';
                if (is_file($e3AccessPath)) {
                    require_once $e3AccessPath;
                }
                $e3StatePath = __DIR__ . '/lib/Client/E3BackupClientState.php';
                if (is_file($e3StatePath)) {
                    require_once $e3StatePath;
                }
                $ms365Autoload = __DIR__ . '/../ms365backup/ms365backup_autoload.php';
                if (is_file($ms365Autoload)) {
                    require_once $ms365Autoload;
                }
                if ($obClientId > 0 && class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\E3BackupAccess')) {
                    $ebE3OnboardingShared['ebMs365Only'] = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess::clientIsMs365Only($obClientId);
                    if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\E3BackupClientState')) {
                        $legacyAgent = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState::clientHasE3AgentProduct($obClientId);
                        $localEntitled = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState::clientHasLocalAgentEntitlement($obClientId);
                        $ebE3OnboardingShared['ebHasE3AgentProduct'] = $localEntitled;
                        // #region agent log
                        @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-991471.log', json_encode([
                            'sessionId' => '991471',
                            'timestamp' => (int) round(microtime(true) * 1000),
                            'location' => 'cloudstorage.php:ebHasE3AgentProduct',
                            'message' => 'sidebar_agent_product_flags',
                            'data' => [
                                'client_id' => $obClientId,
                                'legacy_agent_product' => $legacyAgent,
                                'local_agent_entitled' => $localEntitled,
                                'eb_has_agents' => $ebE3OnboardingShared['ebE3HasAgents'],
                            ],
                            'hypothesisId' => 'H1',
                        ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
                        // #endregion
                        $ebE3OnboardingShared['ebHasMs365Product'] = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState::clientHasMs365Product($obClientId);
                        $ebE3OnboardingShared['ebHasCloudStorageProduct'] = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState::clientHasCloudStorageProduct($obClientId);
                    }
                    $defaultBu = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess::defaultBackupUser($obClientId);
                    if ($defaultBu && class_exists('\\Ms365Backup\\Ms365Onboarding')) {
                        $msOb = \Ms365Backup\Ms365Onboarding::computeForBackupUser($obClientId, (int) $defaultBu['id']);
                        $ebE3OnboardingShared['ebMs365OnboardingState'] = $msOb;
                        $ebE3OnboardingShared['ebMs365OnboardingCompleted'] = (int) ($msOb['completed_count'] ?? 0);
                        $ebE3OnboardingShared['ebMs365OnboardingTotal'] = (int) ($msOb['total_count'] ?? 3);
                        $ebE3OnboardingShared['ebMs365OnboardingComplete'] = (bool) ($msOb['all_complete'] ?? false);
                        $ebE3OnboardingShared['ebMs365OnboardingHidden'] = !empty($msOb['all_complete']);
                        $ebE3OnboardingShared['ebMs365ShowGettingStarted'] = (
                            $ebE3OnboardingShared['ebHasMs365Product'] && empty($msOb['all_complete'])
                        );
                    }
                    if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\E3BackupClientState')) {
                        $ebE3OnboardingShared['ebShowEnableAgentCard'] = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState::showEnableAgentCard(
                            $obClientId,
                            !empty($ebE3OnboardingShared['ebMs365OnboardingComplete'])
                        );
                        $ebE3OnboardingShared['ebShowEnableMs365Card'] = \WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState::showEnableMs365Card(
                            $obClientId,
                            !empty($ebE3OnboardingShared['ebE3OnboardingComplete'])
                        );
                    }

                    // Unified Getting Started hub descriptor (active workload pill + sidebar).
                    $gsIntent = 'local';
                    $gsUserRouteId = '';
                    $encryptionMode = 'managed';
                    if ($defaultBu) {
                        $gsUserRouteId = ($defaultBu['public_id'] ?? '') !== ''
                            ? (string) $defaultBu['public_id']
                            : (string) $defaultBu['id'];
                        $encryptionMode = strtolower(trim((string) ($defaultBu['encryption_mode'] ?? 'managed')));
                        if ($encryptionMode !== 'strict') {
                            $encryptionMode = 'managed';
                        }
                        try {
                            if (\WHMCS\Database\Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
                                $productChoice = strtolower(trim((string) \WHMCS\Database\Capsule::table('cloudstorage_trial_selection')
                                    ->where('client_id', $obClientId)
                                    ->value('product_choice')));
                                if (in_array($productChoice, ['e3backup', 'e3_backup', 'e3-backup', 'cloudbackup_e3', 'backup', 'cloudbackup'], true)) {
                                    $gsIntent = 'local';
                                } elseif (in_array($productChoice, ['ms365', 'm365'], true)) {
                                    $gsIntent = 'ms365';
                                } elseif (in_array($productChoice, ['cloud2cloud', 'cloud-to-cloud'], true)) {
                                    $gsIntent = 'saas';
                                }
                            }
                        } catch (\Throwable $_) {
                            // keep default intent
                        }
                        if ($encryptionMode === 'strict') {
                            $gsIntent = 'local';
                        } elseif ($gsIntent === 'local'
                            && !empty($ebE3OnboardingShared['ebHasMs365Product'])
                            && empty($ebE3OnboardingShared['ebMs365OnboardingComplete'])) {
                            $gsIntent = 'ms365';
                        }
                    }
                    $ebE3OnboardingShared['ebGsIntent'] = $gsIntent;
                    $ebE3OnboardingShared['ebGsActiveWorkload'] = $gsIntent;
                    $ebE3OnboardingShared['ebGsUserId'] = $gsUserRouteId;
                    if ($gsIntent === 'ms365') {
                        $ebE3OnboardingShared['ebGsCompleted'] = (int) $ebE3OnboardingShared['ebMs365OnboardingCompleted'];
                        $ebE3OnboardingShared['ebGsTotal'] = (int) $ebE3OnboardingShared['ebMs365OnboardingTotal'];
                        $ebE3OnboardingShared['ebGsComplete'] = !empty($ebE3OnboardingShared['ebMs365OnboardingComplete']);
                        $ebE3OnboardingShared['ebGsHidden'] = !empty($ebE3OnboardingShared['ebMs365OnboardingHidden']);
                    } elseif ($gsIntent === 'saas') {
                        $ebE3OnboardingShared['ebGsCompleted'] = 0;
                        $ebE3OnboardingShared['ebGsTotal'] = 0;
                        $ebE3OnboardingShared['ebGsComplete'] = false;
                        $ebE3OnboardingShared['ebGsHidden'] = false;
                    } else {
                        $ebE3OnboardingShared['ebGsCompleted'] = (int) $ebE3OnboardingShared['ebE3OnboardingCompleted'];
                        $ebE3OnboardingShared['ebGsTotal'] = (int) $ebE3OnboardingShared['ebE3OnboardingTotal'];
                        $ebE3OnboardingShared['ebGsComplete'] = !empty($ebE3OnboardingShared['ebE3OnboardingComplete']);
                        $ebE3OnboardingShared['ebGsHidden'] = !empty($ebE3OnboardingShared['ebE3OnboardingHidden']);
                    }
                }
            } catch (\Throwable $e) {
                // best-effort - leave defaults
            }

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
                    $templatefile = 'templates/e3backup_live';
                    $viewVars = require 'pages/e3backup_live.php';
                    break;
                case 'agents':
                    $pagetitle = 'e3 Cloud Backup - Agents';
                    $templatefile = 'templates/e3backup_agents';
                    $viewVars = require 'pages/e3backup_agents.php';
                    break;
                case 'vaults':
                    $pagetitle = 'e3 Cloud Backup - Vaults';
                    $templatefile = 'templates/e3backup_vaults';
                    $viewVars = require 'pages/e3backup_vaults.php';
                    break;
                case 'tokens':
                    $pagetitle = 'e3 Cloud Backup - Enrollment Tokens';
                    $templatefile = 'templates/e3backup_tokens';
                    $viewVars = require 'pages/e3backup_tokens.php';
                    break;
                case 'jobs':
                    $extraQs = [];
                    parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $extraQs);
                    unset($extraQs['m'], $extraQs['page'], $extraQs['view']);
                    $target = 'index.php?m=cloudstorage&page=e3backup&view=users';
                    if (!empty($extraQs)) {
                        $target .= '&' . http_build_query($extraQs);
                    }
                    header('Location: ' . $target);
                    exit;
                case 'job_logs':
                    $pagetitle = 'e3 Cloud Backup - Job Logs';
                    $templatefile = 'templates/e3backup_job_logs';
                    $viewVars = require 'pages/e3backup_job_logs.php';
                    break;
                case 'runs':
                    $target = 'index.php?m=cloudstorage&page=e3backup&view=job_logs';
                    if (!empty($_GET['job_id'])) {
                        $target .= '&job_id=' . rawurlencode((string) $_GET['job_id']);
                    }
                    header('Location: ' . $target);
                    exit;
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
                case 'getting_started':
                    $pagetitle = 'e3 Cloud Backup - Getting Started';
                    $templatefile = 'templates/e3backup_getting_started';
                    $viewVars = require 'pages/e3backup_getting_started.php';
                    break;
                case 'ms365_getting_started':
                    $bootstrapPath = __DIR__ . '/lib/Provision/E3BackupUserProductBootstrap.php';
                    if (is_file($bootstrapPath)) {
                        require_once $bootstrapPath;
                    }
                    if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Provision\\E3BackupUserProductBootstrap')
                        && \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::isUnifiedEnabled()) {
                        $redirectParams = [
                            'm' => 'cloudstorage',
                            'page' => 'e3backup',
                            'view' => 'getting_started',
                            'intent' => 'ms365',
                        ];
                        $userIdRaw = trim((string) ($_GET['user_id'] ?? ''));
                        if ($userIdRaw !== '') {
                            $redirectParams['user_id'] = $userIdRaw;
                        }
                        header('Location: index.php?' . http_build_query($redirectParams));
                        exit;
                    }
                    $pagetitle = 'Microsoft 365 Backup - Getting Started';
                    $templatefile = 'templates/e3backup_ms365_getting_started';
                    $viewVars = require 'pages/e3backup_ms365_getting_started.php';
                    break;
                case 'ms365_connect_callback':
                    require 'pages/e3backup_ms365_connect_callback.php';
                    exit;
                case 'enable_agent_backup':
                    $pagetitle = 'e3 Cloud Backup - Enable Workstation & Server Backup';
                    $templatefile = 'templates/e3backup_enable_agent_backup';
                    $viewVars = require 'pages/e3backup_enable_agent_backup.php';
                    break;
                case 'enable_ms365_backup':
                    $pagetitle = 'Microsoft 365 Backup - Enable';
                    $templatefile = 'templates/e3backup_enable_ms365_backup';
                    $viewVars = require 'pages/e3backup_enable_ms365_backup.php';
                    break;
                case 'dashboard':
                default:
                    $pagetitle = 'e3 Cloud Backup';
                    $templatefile = 'templates/e3backup_dashboard';
                    $viewVars = require 'pages/e3backup_dashboard.php';
                    break;
            }
            // Merge the shared onboarding vars into every e3backup view.
            // Individual pages can override any key by setting it themselves.
            if (is_array($viewVars)) {
                $viewVars = $viewVars + $ebE3OnboardingShared;
            } else {
                $viewVars = $ebE3OnboardingShared;
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
        case 'agent_builds':
            require_once __DIR__ . '/pages/admin/agent_builds.php';
            cloudstorage_admin_agent_builds($vars);
            break;
        case 'cloudbackup_trials':
            require_once __DIR__ . '/pages/admin/cloudbackup_trials.php';
            cloudstorage_admin_cloudbackup_trials($vars);
            break;
        case 'cloudbackup_pricing':
            require_once __DIR__ . '/pages/admin/cloudbackup_pricing.php';
            cloudstorage_admin_cloudbackup_pricing($vars);
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
            echo '  <li><a href="' . htmlspecialchars($baseUrl . '&action=agent_builds') . '">Agent Builds</a></li>';
            echo '  <li><a href="' . htmlspecialchars($baseUrl . '&action=cloudbackup_trials') . '">Cloud Backup Trials</a></li>';
            echo '  <li><a href="' . htmlspecialchars($baseUrl . '&action=cloudbackup_pricing') . '">Cloud Backup Pricing</a></li>';
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
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=agent_builds" class="list-group-item">
            <i class="fa fa-cogs"></i> Agent Builds
        </a>
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=cloudbackup_trials" class="list-group-item">
            <i class="fa fa-hourglass-half"></i> Cloud Backup Trials
        </a>
        <a href="' . $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=cloudbackup_pricing" class="list-group-item">
            <i class="fa fa-tags"></i> Cloud Backup Pricing
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

/**
 * Ensure schema for the e3 agent build automation system.
 *
 * Creates s3_agent_build_jobs, s3_agent_build_steps, s3_agent_releases tables
 * (idempotent) and the storage/builds directory used for per-step log files.
 */
function cloudstorage_ensure_agent_build_schema(string $context = 'activate'): void
{
    try {
        $schema = \WHMCS\Database\Capsule::schema();

        if (!$schema->hasTable('s3_agent_build_jobs')) {
            $schema->create('s3_agent_build_jobs', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('created_by_admin_id')->nullable();
                $t->enum('platform', ['linux', 'windows', 'both', 'recovery_iso'])->default('both');
                $t->string('git_ref', 191)->default('main');
                $t->string('git_commit', 64)->nullable();
                $t->string('version_label', 64)->nullable();
                $t->text('flags_json')->nullable();
                $t->enum('status', ['queued', 'running', 'succeeded', 'failed', 'cancelled'])->default('queued');
                $t->string('current_step', 64)->nullable();
                $t->text('error_message')->nullable();
                $t->string('host_runner', 191)->nullable();
                $t->dateTime('started_at')->nullable();
                $t->dateTime('ended_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->timestamp('updated_at')->useCurrent();
                $t->index('status');
                $t->index('created_at');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_build_jobs', [], []);
        }

        if (!$schema->hasTable('s3_agent_build_steps')) {
            $schema->create('s3_agent_build_steps', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('job_id');
                $t->string('step_key', 64);
                $t->unsignedInteger('seq')->default(0);
                $t->enum('status', ['pending', 'running', 'succeeded', 'failed', 'skipped'])->default('pending');
                $t->integer('exit_code')->nullable();
                $t->dateTime('started_at')->nullable();
                $t->dateTime('ended_at')->nullable();
                $t->string('log_path', 1024)->nullable();
                $t->unsignedBigInteger('bytes_logged')->default(0);
                $t->text('summary')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->timestamp('updated_at')->useCurrent();
                $t->index(['job_id', 'seq']);
                $t->unique(['job_id', 'step_key']);
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_build_steps', [], []);
        }

        if (!$schema->hasTable('s3_agent_releases')) {
            $schema->create('s3_agent_releases', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('job_id')->nullable();
                $t->enum('platform', ['linux', 'windows', 'recovery_iso'])->default('linux');
                $t->string('artifact_filename', 191);
                $t->string('version_label', 64)->nullable();
                $t->string('git_commit', 64)->nullable();
                $t->string('sha256', 64)->nullable();
                $t->unsignedBigInteger('size_bytes')->nullable();
                $t->string('signed_subject', 255)->nullable();
                $t->dateTime('signed_at')->nullable();
                $t->tinyInteger('is_latest')->default(0);
                $t->string('download_url', 1024)->nullable();
                $t->dateTime('published_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['platform', 'is_latest']);
                $t->index('published_at');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_releases', [], []);
        }

        // Extend platform enum to include recovery_media (recovery media creator).
        if ($schema->hasTable('s3_agent_releases')) {
            try {
                \WHMCS\Database\Capsule::connection()->statement(
                    "ALTER TABLE `s3_agent_releases` MODIFY `platform` ENUM('linux','windows','recovery_iso','recovery_media') NOT NULL DEFAULT 'linux'"
                );
            } catch (\Throwable $e) {
                // Already migrated or DB engine does not support MODIFY — ignore.
            }
        }

        if (!$schema->hasTable('s3_agent_deployments')) {
            $schema->create('s3_agent_deployments', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('job_id')->nullable();
                $t->string('version_label', 64)->nullable();
                $t->string('git_commit', 64)->nullable();
                $t->enum('status', ['draft', 'active', 'superseded'])->default('draft');
                $t->text('artifacts_json')->nullable();
                $t->unsignedInteger('created_by_admin_id')->nullable();
                $t->dateTime('activated_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index('status');
                $t->index('created_at');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_deployments', [], []);
        }

        if (!$schema->hasTable('s3_agent_deploy_artifacts')) {
            $schema->create('s3_agent_deploy_artifacts', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('deployment_id');
                $t->string('artifact_key', 64);
                $t->string('platform', 32);
                $t->string('latest_filename', 191);
                $t->string('versioned_filename', 191);
                $t->string('sha256', 64)->nullable();
                $t->unsignedBigInteger('size_bytes')->nullable();
                $t->string('signed_subject', 255)->nullable();
                $t->dateTime('signed_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index('deployment_id');
                $t->unique(['deployment_id', 'artifact_key']);
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_deploy_artifacts', [], []);
        }

        if (!$schema->hasTable('s3_agent_deploy_sync_runs')) {
            $schema->create('s3_agent_deploy_sync_runs', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('deployment_id')->nullable();
                $t->enum('status', ['running', 'succeeded', 'failed', 'skipped'])->default('running');
                $t->text('detail')->nullable();
                $t->dateTime('started_at')->nullable();
                $t->dateTime('ended_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index('deployment_id');
                $t->index('created_at');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_deploy_sync_runs', [], []);
        }

        if (!$schema->hasTable('s3_agent_deploy_downloads')) {
            $schema->create('s3_agent_deploy_downloads', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('deployment_id');
                $t->string('artifact_key', 64);
                $t->string('nonce', 128)->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->dateTime('downloaded_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index('deployment_id');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_deploy_downloads', [], []);
        }

        // Ensure storage/builds directory exists with reasonable perms
        $storageDir = __DIR__ . '/storage/builds';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0750, true);
        }
    } catch (\Throwable $e) {
        try {
            logModuleCall('cloudstorage', 'ensure_agent_build_schema', [], $e->getMessage(), [], []);
        } catch (\Throwable $_) {}
    }
}

/**
 * MS365 vault recycle bin: bucket lifecycle columns, audit events, early-delete requests.
 */
function cloudstorage_ensure_ms365_vault_lifecycle_schema(string $context = 'activate'): void
{
    try {
        $schema = \WHMCS\Database\Capsule::schema();

        if ($schema->hasTable('s3_buckets')) {
            $bucketCols = [
                'recycle_started_at' => function ($table) {
                    $table->timestamp('recycle_started_at')->nullable();
                },
                'recycle_teardown_at' => function ($table) {
                    $table->timestamp('recycle_teardown_at')->nullable();
                },
                'recycled_from_job_id' => function ($table) {
                    $table->binary('recycled_from_job_id', 16)->nullable();
                },
            ];
            foreach ($bucketCols as $col => $adder) {
                if (!$schema->hasColumn('s3_buckets', $col)) {
                    $schema->table('s3_buckets', $adder);
                    logModuleCall('cloudstorage', $context, [], "Added s3_buckets.{$col}", [], []);
                }
            }
            if (!$schema->hasColumn('s3_buckets', 'recycle_status')) {
                \WHMCS\Database\Capsule::statement(
                    "ALTER TABLE `s3_buckets` ADD COLUMN `recycle_status` ENUM('active','recycle','pending_delete','deleted') NOT NULL DEFAULT 'active' AFTER `is_active`"
                );
                logModuleCall('cloudstorage', $context, [], 'Added s3_buckets.recycle_status', [], []);
            }
        }

        if (!$schema->hasTable('s3_cloudbackup_audit_events')) {
            $schema->create('s3_cloudbackup_audit_events', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('backup_user_id')->nullable();
                $table->string('event_type', 64);
                $table->string('entity_type', 32)->nullable();
                $table->string('entity_id', 64)->nullable();
                $table->unsignedInteger('actor_client_user_id')->nullable();
                $table->unsignedInteger('actor_contact_id')->nullable();
                $table->string('request_ip', 64)->nullable();
                $table->text('request_ua')->nullable();
                $table->mediumText('payload_json')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['client_id', 'created_at']);
                $table->index(['event_type', 'created_at']);
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_cloudbackup_audit_events', [], []);
        }

        if (!$schema->hasTable('s3_ms365_vault_deletion_requests')) {
            $schema->create('s3_ms365_vault_deletion_requests', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('bucket_id');
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('backup_user_id')->nullable();
                $table->binary('job_id', 16)->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed'])->default('pending');
                $table->unsignedInteger('requested_by_user_id')->nullable();
                $table->timestamp('requested_at')->useCurrent();
                $table->text('reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->index(['bucket_id', 'status']);
                $table->index(['client_id', 'status']);
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_ms365_vault_deletion_requests', [], []);
        }
    } catch (\Throwable $e) {
        try {
            logModuleCall('cloudstorage', 'ensure_ms365_vault_lifecycle_schema', [], $e->getMessage(), [], []);
        } catch (\Throwable $_) {
        }
    }
}

/**
 * Create the s3_agent_update_jobs table used to track remote agent-update
 * requests end-to-end (queued -> downloading -> verifying -> applying ->
 * restarting -> verifying_online -> success|failed|timeout). One row is created
 * per update request from the client drawer or admin Agents page; the agent
 * reports progress against it and the server marks success once the agent comes
 * back online reporting the target version.
 */
function cloudstorage_ensure_agent_update_schema(string $context = 'activate'): void
{
    try {
        $schema = \WHMCS\Database\Capsule::schema();

        if (!$schema->hasTable('s3_agent_update_jobs')) {
            $schema->create('s3_agent_update_jobs', function ($t) {
                $t->bigIncrements('id');
                $t->string('agent_uuid', 36);
                $t->unsignedBigInteger('command_id')->nullable();
                $t->unsignedBigInteger('release_id')->nullable();
                $t->string('platform', 32)->nullable();
                $t->string('from_version', 64)->nullable();
                $t->string('target_version', 64)->nullable();
                $t->string('download_url', 1024)->nullable();
                $t->string('sha256', 64)->nullable();
                $t->unsignedBigInteger('size_bytes')->nullable();
                $t->enum('status', [
                    'queued', 'downloading', 'verifying', 'applying',
                    'restarting', 'verifying_online', 'success', 'failed', 'timeout',
                ])->default('queued');
                $t->text('detail')->nullable();
                // Who initiated the update: a WHMCS client (portal) or an admin.
                $t->enum('requested_by_type', ['client', 'admin'])->nullable();
                $t->unsignedInteger('requested_by_id')->nullable();
                $t->dateTime('started_at')->nullable();
                $t->dateTime('finished_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->timestamp('updated_at')->useCurrent();
                $t->index('agent_uuid');
                $t->index('status');
                $t->index('command_id');
                $t->index('created_at');
            });
            logModuleCall('cloudstorage', $context, [], 'Created s3_agent_update_jobs', [], []);
        }
    } catch (\Throwable $e) {
        try {
            logModuleCall('cloudstorage', 'ensure_agent_update_schema', [], $e->getMessage(), [], []);
        } catch (\Throwable $_) {}
    }
}
