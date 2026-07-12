<?php
declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/ms365backup_autoload.php';

/**
 * URL to addon static assets. Admin addonmodules.php runs under /admin/, so assets
 * must be referenced relative to the WHMCS root (../modules/addons/...).
 */
function ms365backup_asset_url(string $relativePath = ''): string
{
    $base = '../modules/addons/ms365backup/';
    return $base . ltrim($relativePath, '/');
}

function ms365backup_config(): array
{
    return [
        'name' => 'MS365 Backup',
        'description' => 'Admin-only Microsoft 365 backup development tool (mail, calendar, contacts, To Do, OneDrive).',
        'version' => '1.52.3',
        'author' => 'eazyBackup',
        'language' => 'english',
        'fields' => [
            'platform_entra_client_id' => [
                'FriendlyName' => 'Platform Entra Client ID',
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'Multi-tenant app used for customer admin consent (e3 UI).',
            ],
            'platform_entra_secret' => [
                'FriendlyName' => 'Platform Entra Client Secret',
                'Type' => 'password',
                'Size' => '64',
                'Description' => 'Stored encrypted when WHMCS encrypt() is available.',
            ],
            'platform_entra_region' => [
                'FriendlyName' => 'Platform Entra Region',
                'Type' => 'dropdown',
                'Options' => 'GlobalPublicCloud,USGovCloud,ChinaCloud,GermanyCloud',
                'Default' => 'GlobalPublicCloud',
            ],
            'platform_entra_redirect_uri' => [
                'FriendlyName' => 'OAuth redirect URI (optional)',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'Customer admin-consent callback only: {SystemURL}/index.php?m=cloudstorage&page=e3backup&view=ms365_connect_callback. Do NOT use the admin Tenant Seeder callback.',
            ],
            'ms365_worker_token' => [
                'FriendlyName' => 'Worker API token',
                'Type' => 'password',
                'Size' => '64',
                'Description' => 'Shared secret for ms365-backup-worker fleet (X-MS365-Worker-Token header). Use the same value on dev and prod.',
            ],
            'ms365_server_environment' => [
                'FriendlyName' => 'Server environment',
                'Type' => 'dropdown',
                'Options' => 'development,production',
                'Default' => 'development',
                'Description' => 'Identifies this WHMCS host. Set production on prod (192.168.92.75); development on the build/deploy console.',
            ],
            'ms365_production_system_url' => [
                'FriendlyName' => 'Production WHMCS base URL',
                'Type' => 'text',
                'Size' => '128',
                'Default' => 'http://192.168.92.75',
                'Description' => 'Dev server only: production WHMCS SystemURL root (web root = accounts dir). Use internal IP on LAN, e.g. http://192.168.92.75 — do not append /accounts.',
            ],
            'ms365_development_system_url' => [
                'FriendlyName' => 'Development WHMCS base URL',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'Prod server only (optional): dev WHMCS URL for pull-based worker release sync fallback.',
            ],
            'ms365_fleet_deploy_shared_secret' => [
                'FriendlyName' => 'Fleet deploy shared secret',
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'M2M auth between dev and prod fleet APIs (X-MS365-Fleet-Deploy-Token). Paste the identical value on both servers (generate with: openssl rand -hex 32).',
            ],
            'ms365_production_release_sync_enabled' => [
                'FriendlyName' => 'Production release pull sync',
                'Type' => 'yesno',
                'Description' => 'Prod only: enable cron pull of worker releases from development when push sync fails.',
            ],
            'ms365_auto_sync_release_to_prod' => [
                'FriendlyName' => 'Auto-sync releases to production',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Dev only: after a successful build publish, push the release artifact to production via fleet_release_upsert.',
            ],
            'ms365_platform_max_concurrent' => [
                'FriendlyName' => 'Platform max concurrent backups',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '100',
            ],
            'ms365_per_tenant_max_concurrent' => [
                'FriendlyName' => 'Max concurrent per Entra tenant',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '96',
            ],
            'ms365_per_tenant_max_concurrent_workloads' => [
                'FriendlyName' => 'Max concurrent workloads per Entra tenant',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '24',
                'Description' => 'Running child workloads per tenant (claim gate). Keep below max concurrent per Entra tenant (Graph HTTP budget).',
            ],
            'ms365_per_client_max_concurrent' => [
                'FriendlyName' => 'Max concurrent per WHMCS client',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '96',
            ],
            'ms365_worker_lease_seconds' => [
                'FriendlyName' => 'Worker lease seconds',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '7200',
                'Description' => 'Claim lease duration; renewed on worker progress/heartbeat for long runs.',
            ],
            'ms365_fair_scheduling_enabled' => [
                'FriendlyName' => 'Fair-share job queue scheduling',
                'Type' => 'yesno',
                'Description' => 'Round-robin claim order across backup batches (e3_batch_run_id) so large jobs do not starve newer jobs.',
            ],
            'ms365_sharding_enabled' => [
                'FriendlyName' => 'Enable large-resource sharding',
                'Type' => 'yesno',
                'Description' => 'Split large drives/sites/mailboxes into parallel shard jobs (Kopia mode).',
            ],
            'ms365_shard_threshold_bytes' => [
                'FriendlyName' => 'Shard threshold bytes',
                'Type' => 'text',
                'Size' => '16',
                'Default' => '107374182400',
                'Description' => 'Inventory size hint above which a resource is sharded (default 100 GiB).',
            ],
            'ms365_shard_target_bytes' => [
                'FriendlyName' => 'Shard target bytes',
                'Type' => 'text',
                'Size' => '16',
                'Default' => '53687091200',
                'Description' => 'Approximate bytes per drive/site shard (default 50 GiB).',
            ],
            'ms365_shard_max_count' => [
                'FriendlyName' => 'Max shards per resource',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '32',
            ],
            'ms365_shard_item_threshold' => [
                'FriendlyName' => 'Shard item-count threshold',
                'Type' => 'text',
                'Size' => '16',
                'Default' => '25000',
                'Description' => 'Shard when inventory item_count hint exceeds this value.',
            ],
            'ms365_shard_target_items' => [
                'FriendlyName' => 'Shard target items',
                'Type' => 'text',
                'Size' => '16',
                'Default' => '15000',
                'Description' => 'Target items per range shard when splitting by item count.',
            ],
            'ms365_list_job_item_threshold' => [
                'FriendlyName' => 'SharePoint list job item threshold',
                'Type' => 'text',
                'Size' => '16',
                'Default' => '50000',
                'Description' => 'Emit a dedicated list:{listId} backup job when inventory item_count exceeds this value.',
            ],
            'ms365_list_shard_item_threshold' => [
                'FriendlyName' => 'SharePoint list shard item threshold',
                'Type' => 'text',
                'Size' => '16',
                'Default' => '500000',
                'Description' => 'Split a list into createdDateTime range shards when item_count exceeds this value.',
            ],
            'ms365_list_shard_target_items' => [
                'FriendlyName' => 'SharePoint list shard target items',
                'Type' => 'text',
                'Size' => '16',
                'Default' => '100000',
                'Description' => 'Target items per list time-range shard.',
            ],
            'ms365_list_shard_max_count' => [
                'FriendlyName' => 'Max shards per SharePoint list',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '16',
            ],
            'ms365_batch_auto_retry_enabled' => [
                'FriendlyName' => 'Batch auto-retry failed shards',
                'Type' => 'yesno',
                'Description' => 'On partial batch failure, re-queue only failed or never-started child workloads on the same batch.',
            ],
            'ms365_batch_auto_retry_max_rounds' => [
                'FriendlyName' => 'Batch auto-retry max rounds',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '2',
                'Description' => 'Maximum batch-level auto-retry rounds per parent run (default 2).',
            ],
            'ms365_graph_pagination_json' => [
                'FriendlyName' => 'Graph pagination limits (JSON)',
                'Type' => 'textarea',
                'Rows' => '6',
                'Cols' => '60',
                'Description' => 'Per-workload max_pages and on_cap (fail|warn_continue). Keys: default, sharepoint, mail, etc.',
            ],
            'ms365_kopia_maintenance_interval_days' => [
                'FriendlyName' => 'Kopia maintenance interval (days)',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '7',
                'Description' => 'Enqueue maintenance_quick for MS365 Kopia repos on this cadence.',
            ],
            'ms365_kopia_workloads_json' => [
                'FriendlyName' => 'Kopia workload flags (JSON)',
                'Type' => 'textarea',
                'Rows' => '4',
                'Cols' => '60',
                'Description' => 'Optional JSON object e.g. {"mail":true,"calendar":true}. Empty = all enabled.',
            ],
            'ms365_archive_export_ttl_days' => [
                'FriendlyName' => 'Archive export TTL (days)',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '7',
                'Description' => 'Days before auto-expiration of MS365 restore archive exports under exports/ prefix.',
            ],
            'proxmox_api_url' => [
                'FriendlyName' => 'Proxmox API URL',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'e.g. https://proxmox.example:8006/api2/json',
            ],
            'proxmox_api_token_id' => [
                'FriendlyName' => 'Proxmox API token ID',
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'API token with VM.Clone on the template VMID, Datastore.Allocate on proxmox_lxc_storage, and VM.Config/PowerMgmt for workers. See Docs/MS365_WORKER_FLEET.md.',
            ],
            'proxmox_api_token_secret' => [
                'FriendlyName' => 'Proxmox API token secret',
                'Type' => 'password',
                'Size' => '64',
            ],
            'proxmox_node' => [
                'FriendlyName' => 'Proxmox node name',
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'Node where the golden LXC template lives; used as clone source when scaling to other cluster nodes.',
            ],
            'proxmox_lxc_template_vmid' => [
                'FriendlyName' => 'Proxmox LXC template VMID',
                'Type' => 'text',
                'Size' => '8',
            ],
            'proxmox_lxc_storage' => [
                'FriendlyName' => 'Proxmox LXC clone storage',
                'Type' => 'text',
                'Size' => '64',
                'Default' => 'local-lvm',
                'Description' => 'Proxmox storage ID for worker rootfs on clone (e.g. ceph-rbd for shared cluster storage, local-lvm for node-local). Golden template should live on this pool for cross-node scale-up.',
            ],
            'ms365_worker_fleet_min_nodes' => [
                'FriendlyName' => 'Worker fleet min nodes',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '2',
            ],
            'ms365_worker_fleet_max_nodes' => [
                'FriendlyName' => 'Worker fleet max nodes',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '20',
            ],
            'ms365_worker_fleet_autoscale_enabled' => [
                'FriendlyName' => 'Worker fleet cron autoscale',
                'Type' => 'yesno',
                'Description' => 'When off (default), the fleet cron does not auto-clone or auto-destroy workers; use manual scale up/down in Worker Fleet → Nodes.',
            ],
            'ms365_worker_fleet_auto_baseline_update' => [
                'FriendlyName' => 'Worker fleet auto baseline update',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'When on (default), idle nodes behind the latest release self-update via heartbeat and cannot claim jobs until current.',
            ],
            'proxmox_cluster_nodes' => [
                'FriendlyName' => 'Proxmox cluster nodes (CSV)',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'Optional comma-separated node names for the manual scale-up picker; empty uses live Proxmox /nodes discovery.',
            ],
            'proxmox_template_vmid_map' => [
                'FriendlyName' => 'Proxmox per-node template map (JSON)',
                'Type' => 'text',
                'Size' => '256',
                'Description' => 'Optional JSON object {"nodeName":9010} for local-storage clusters when cloning to nodes other than the template home.',
            ],
            'proxmox_ssh_target' => [
                'FriendlyName' => 'Proxmox SSH target (optional)',
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'SSH target for post-clone worker env injection when LXC exec API is unavailable, e.g. root@192.168.92.195. Requires passwordless SSH from the WHMCS host.',
            ],
            'proxmox_ssh_identity' => [
                'FriendlyName' => 'Proxmox SSH identity file (optional)',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'Path to private key for proxmox_ssh_target (empty = auto-detect /var/www/.ssh/ms365_proxmox_ed25519, id_rsa, id_ed25519).',
            ],
            'ms365_worker_repo_path' => [
                'FriendlyName' => 'Worker Go repo path',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'Path to ms365-backup-worker on this host (for admin builds).',
            ],
            'ms365_worker_build_go' => [
                'FriendlyName' => 'Go binary path',
                'Type' => 'text',
                'Size' => '64',
                'Description' => 'Optional; default: go from PATH.',
            ],
            'ms365_worker_artifact_dir' => [
                'FriendlyName' => 'Worker artifact directory',
                'Type' => 'text',
                'Size' => '128',
                'Description' => 'Published worker binaries (outside web root).',
            ],
            'ms365_worker_stale_seconds' => [
                'FriendlyName' => 'Stale heartbeat seconds',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '120',
            ],
            'ms365_worker_offline_alert_minutes' => [
                'FriendlyName' => 'Offline alert after minutes',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '10',
            ],
            'ms365_worker_artifact_nonce_ttl' => [
                'FriendlyName' => 'Artifact download nonce TTL (seconds)',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '600',
            ],
            'pid_ms365_backup' => [
                'FriendlyName' => 'MS365 Backup product ID',
                'Type' => 'text',
                'Size' => '8',
                'Description' => 'WHMCS product ID for eazyBackup Microsoft 365 Backup (auto-set by product bootstrap).',
            ],
            'protected_user_price_cad' => [
                'FriendlyName' => 'Protected User price (CAD)',
                'Type' => 'text',
                'Size' => '12',
                'Default' => '0.00',
                'Description' => 'Per Protected User per month; applied via invoice hook (not tblpricing).',
            ],
            'onedrive_included_gib' => [
                'FriendlyName' => 'OneDrive included GiB per user',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '1024',
            ],
            'onedrive_overage_price_per_gib_cad' => [
                'FriendlyName' => 'OneDrive overage price per GiB (CAD)',
                'Type' => 'text',
                'Size' => '12',
                'Default' => '0.00',
            ],
            'ms365_trial_days' => [
                'FriendlyName' => 'Trial length (days)',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '30',
            ],
            'ms365_backup_report_email_template' => [
                'FriendlyName' => 'MS365 Backup Report Email Template',
                'Type' => 'dropdown',
                'Options' => ms365backup_get_email_templates(),
                'Description' => 'WHMCS General template for per-run MS365 backup workload reports.',
            ],
            'ms365_config_option_ids' => [
                'FriendlyName' => 'Config option map (JSON)',
                'Type' => 'textarea',
                'Rows' => '3',
                'Cols' => '60',
                'Description' => 'Auto-maintained JSON map of metric → tblproductconfigoptions.id.',
            ],
            'ms365_platform_tenant_id' => [
                'FriendlyName' => 'MS365 platform RGW tenant ID',
                'Type' => 'text',
                'Size' => '16',
                'Description' => 'Optional Ceph tenant for the global ms365_platform_owner (defaults to primary cluster tenant).',
            ],
        ],
    ];
}

/**
 * Run semicolon-separated SQL statements from a file (schema or migration).
 */
function ms365backup_run_sql_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        return;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $lines = [];
        foreach (preg_split('/\R/', $stmt) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '--')) {
                continue;
            }
            $lines[] = $line;
        }
        $stmt = trim(implode("\n", $lines));
        if ($stmt === '') {
            continue;
        }
        if (!preg_match('/^\s*(CREATE|ALTER|INSERT|UPDATE|DELETE|DROP|SELECT)\b/i', $stmt)) {
            continue;
        }
        Capsule::connection()->statement($stmt);
    }
}

/**
 * Fresh install: all tables/indexes from schema.sql.
 */
function ms365backup_apply_schema(): void
{
    $schemaFile = __DIR__ . '/schema.sql';
    if (!is_file($schemaFile)) {
        throw new \RuntimeException('Missing schema.sql');
    }
    ms365backup_run_sql_file($schemaFile);
}

/**
 * Ensure migration tracking table exists. On first create for an existing install,
 * backfill all current upgrade scripts as applied so we do not re-run ALTERs.
 */
function ms365backup_ensure_migrations_table(): void
{
    if (Capsule::schema()->hasTable('ms365backup_schema_migrations')) {
        return;
    }

    Capsule::schema()->create('ms365backup_schema_migrations', function ($table) {
        $table->string('migration', 191)->primary();
        $table->timestamp('applied_at')->useCurrent();
    });

    if (!Capsule::schema()->hasTable('ms365_backup_runs')) {
        return;
    }

    $sqlDir = __DIR__ . '/sql';
    if (!is_dir($sqlDir)) {
        return;
    }
    $files = glob($sqlDir . '/upgrade_*.sql') ?: [];
    sort($files, SORT_STRING);
    $now = date('Y-m-d H:i:s');
    foreach ($files as $file) {
        Capsule::table('ms365backup_schema_migrations')->insertOrIgnore([
            'migration' => basename($file),
            'applied_at' => $now,
        ]);
    }
}

function ms365backup_migration_applied(string $migration): bool
{
    if (!Capsule::schema()->hasTable('ms365backup_schema_migrations')) {
        return false;
    }

    return Capsule::table('ms365backup_schema_migrations')
        ->where('migration', $migration)
        ->exists();
}

function ms365backup_mark_migration_applied(string $migration): void
{
    ms365backup_ensure_migrations_table();
    Capsule::table('ms365backup_schema_migrations')->insertOrIgnore([
        'migration' => $migration,
        'applied_at' => date('Y-m-d H:i:s'),
    ]);
}

function ms365backup_migration_error_is_benign(\Throwable $e): bool
{
    $msg = $e->getMessage();

    return str_contains($msg, 'Duplicate column')
        || str_contains($msg, 'Duplicate key name')
        || str_contains($msg, 'already exists')
        || preg_match('/\b1060\b/', $msg) === 1
        || preg_match('/\b1061\b/', $msg) === 1;
}

/**
 * Incremental changes for existing databases (enum changes, new columns, etc.).
 * Each file in sql/upgrade_*.sql runs once and is recorded in ms365backup_schema_migrations.
 */
function ms365backup_apply_migrations(): void
{
    $sqlDir = __DIR__ . '/sql';
    if (!is_dir($sqlDir)) {
        return;
    }

    ms365backup_ensure_migrations_table();

    $files = glob($sqlDir . '/upgrade_*.sql') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $name = basename($file);
        if (ms365backup_migration_applied($name)) {
            continue;
        }

        try {
            ms365backup_run_sql_file($file);
            ms365backup_mark_migration_applied($name);
        } catch (\Throwable $e) {
            if (ms365backup_migration_error_is_benign($e)) {
                ms365backup_mark_migration_applied($name);
                continue;
            }
            logActivity('MS365 Backup migration failed: ' . $name . ' — ' . $e->getMessage());
        }
    }
}

function ms365backup_ensure_storage(): void
{
    \Ms365Backup\StorageLayout::ensureBase();
    \Ms365Backup\StoragePermissions::applyToTree(\Ms365Backup\StorageLayout::BASE_PATH);
    $logsDir = \Ms365Backup\StorageLayout::BASE_PATH . '/_logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0770, true);
    }
    \Ms365Backup\StoragePermissions::applyToTree($logsDir);
    foreach ([
        __DIR__ . '/storage/worker-releases',
        __DIR__ . '/storage/worker-builds',
    ] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }
}

/**
 * Backup module settings before deactivation so WHMCS clearing tbladdonmodules does not lose them.
 */
function ms365backup_backup_settings(): void
{
    try {
        if (!Capsule::schema()->hasTable('ms365backup_settings_backup')) {
            Capsule::schema()->create('ms365backup_settings_backup', function ($table) {
                $table->string('setting', 255)->primary();
                $table->text('value')->nullable();
                $table->timestamp('backed_up_at')->useCurrent();
            });
        }

        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'ms365backup')
            ->get(['setting', 'value']);

        foreach ($settings as $setting) {
            $exists = Capsule::table('ms365backup_settings_backup')
                ->where('setting', $setting->setting)
                ->exists();

            if ($exists) {
                Capsule::table('ms365backup_settings_backup')
                    ->where('setting', $setting->setting)
                    ->update([
                        'value' => $setting->value,
                        'backed_up_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                Capsule::table('ms365backup_settings_backup')
                    ->insert([
                        'setting' => $setting->setting,
                        'value' => $setting->value,
                        'backed_up_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        }

        logModuleCall('ms365backup', 'backup_settings', [], 'Settings backed up: ' . count($settings) . ' settings', [], []);
    } catch (\Throwable $e) {
        logModuleCall('ms365backup', 'backup_settings', [], 'Error backing up settings: ' . $e->getMessage(), [], []);
    }
}

/**
 * Restore module settings from backup after activation (WHMCS wipes tbladdonmodules on deactivate).
 */
function ms365backup_restore_settings(): void
{
    try {
        if (!Capsule::schema()->hasTable('ms365backup_settings_backup')) {
            return;
        }

        $backedUpSettings = Capsule::table('ms365backup_settings_backup')
            ->get(['setting', 'value']);

        if ($backedUpSettings->isEmpty()) {
            return;
        }

        foreach ($backedUpSettings as $backup) {
            $exists = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', $backup->setting)
                ->exists();

            if ($exists) {
                Capsule::table('tbladdonmodules')
                    ->where('module', 'ms365backup')
                    ->where('setting', $backup->setting)
                    ->update(['value' => $backup->value]);
            } else {
                Capsule::table('tbladdonmodules')
                    ->insert([
                        'module' => 'ms365backup',
                        'setting' => $backup->setting,
                        'value' => $backup->value,
                    ]);
            }
        }

        logModuleCall('ms365backup', 'restore_settings', [], 'Settings restored: ' . count($backedUpSettings) . ' settings', [], []);
    } catch (\Throwable $e) {
        logModuleCall('ms365backup', 'restore_settings', [], 'Error restoring settings: ' . $e->getMessage(), [], []);
    }
}

function ms365backup_get_email_templates(): array
{
    try {
        if (!function_exists('localAPI')) {
            return ['' => 'None (Disable Email Notifications)'];
        }

        $results = localAPI('GetEmailTemplates', ['type' => 'general']);
        $templates = ['' => 'None (Disable Email Notifications)'];

        if (isset($results['emailtemplates']['emailtemplate']) && is_array($results['emailtemplates']['emailtemplate'])) {
            foreach ($results['emailtemplates']['emailtemplate'] as $template) {
                if (isset($template['id'], $template['name'])) {
                    $templates[$template['id']] = $template['name'];
                }
            }
        }

        return $templates;
    } catch (\Throwable $e) {
        logModuleCall('ms365backup', 'get_email_templates', [], $e->getMessage());
        return ['' => 'Error loading templates'];
    }
}

function ms365backup_create_email_templates(): void
{
    try {
        $exists = Capsule::table('tblemailtemplates')
            ->where('name', 'MS365 Backup Report')
            ->where('type', 'general')
            ->exists();

        if ($exists) {
            return;
        }

        Capsule::table('tblemailtemplates')->insert([
            'type' => 'general',
            'name' => 'MS365 Backup Report',
            'subject' => 'Microsoft 365 backup {$job_name} — {$run_status}',
            'message' => '<p>Your Microsoft 365 backup <strong>{$job_name}</strong> for account <strong>{$backup_username}</strong> has completed.</p>
<p>Status: {$run_status} · Finished: {$finished_at}</p>
{$workload_report_html}',
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
        logModuleCall('ms365backup', 'create_email_templates', [], 'Created MS365 Backup Report template', [], []);
    } catch (\Throwable $e) {
        logModuleCall('ms365backup', 'create_email_templates', [], $e->getMessage());
    }
}

function ms365backup_activate(): array
{
    try {
        ms365backup_restore_settings();
        ms365backup_apply_schema();
        ms365backup_apply_migrations();
        ms365backup_ensure_storage();
        ms365backup_bootstrap_billing('activate');
        ms365backup_create_email_templates();
        return ['status' => 'success', 'description' => 'MS365 Backup activated. Module settings restored from backup when available.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}

function ms365backup_deactivate(): array
{
    try {
        ms365backup_backup_settings();
        logModuleCall('ms365backup', 'deactivate', [], 'Module deactivated successfully. Settings backed up.', [], []);

        return ['status' => 'success', 'description' => 'MS365 Backup deactivated. Backup data preserved. Module settings backed up for reactivation.'];
    } catch (\Throwable $e) {
        $errorMsg = 'Module deactivation failed: ' . $e->getMessage();
        logModuleCall('ms365backup', 'deactivate', [], $errorMsg, [], []);

        return ['status' => 'error', 'description' => $errorMsg];
    }
}

/**
 * Called by WHMCS when module version in ms365backup_config() is increased (module stays active).
 * Deactivate + reactivate runs ms365backup_activate() only, not this hook.
 */
function ms365backup_upgrade(array $vars): void
{
    try {
        ms365backup_apply_schema();
        ms365backup_apply_migrations();
        ms365backup_ensure_storage();
        ms365backup_bootstrap_billing('upgrade');
        ms365backup_create_email_templates();
    } catch (\Throwable $e) {
        logActivity('MS365 Backup upgrade error: ' . $e->getMessage());
    }
}

function ms365backup_bootstrap_billing(string $context): void
{
    try {
        \Ms365Backup\Ms365ProductBootstrap::ensure($context);
    } catch (\Throwable $e) {
        logActivity('MS365 Backup product bootstrap (' . $context . '): ' . $e->getMessage());
    }
}

function ms365backup_sidebar(array $vars): string
{
    $base = ($_SERVER['PHP_SELF'] ?? 'addonmodules.php') . '?module=ms365backup';
    return '<div class="list-group">'
        . '<a href="' . $base . '" class="list-group-item"><i class="fa fa-cog"></i> Dashboard</a>'
        . '<a href="' . $base . '&action=discover" class="list-group-item"><i class="fa fa-search"></i> Discovery</a>'
        . '<a href="' . $base . '&action=backup" class="list-group-item"><i class="fa fa-download"></i> Backup</a>'
        . '<a href="' . $base . '&action=seeder" class="list-group-item"><i class="fa fa-database"></i> Tenant Seeder</a>'
        . '<a href="' . $base . '&action=fleet" class="list-group-item"><i class="fa fa-server"></i> Worker Fleet</a>'
        . '<a href="' . $base . '&action=jobs" class="list-group-item"><i class="fa fa-list"></i> Jobs</a>'
        . '<a href="' . $base . '&action=trials" class="list-group-item"><i class="fa fa-clock-o"></i> Trials</a>'
        . '<a href="' . $base . '&action=provision" class="list-group-item"><i class="fa fa-user-plus"></i> Provision Customer</a>'
        . '<a href="' . $base . '&action=tenant_export" class="list-group-item"><i class="fa fa-key"></i> Tenant Export</a>'
        . '</div>';
}

function ms365backup_output(array $vars): void
{
    $action = (string) ($_GET['action'] ?? 'dashboard');
    $baseUrl = 'addonmodules.php?module=ms365backup';

    // JSON / bare responses must discard WHMCS admin chrome already in the buffer.
    if ($action === 'api' || $action === 'seeder_oauth_callback' || $action === 'fleet_remote') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if ($action === 'api') {
            require __DIR__ . '/pages/admin/api.php';
            exit;
        }
        if ($action === 'fleet_remote') {
            require __DIR__ . '/pages/admin/fleet_remote.php';
            exit;
        }
        require __DIR__ . '/pages/admin/seeder_oauth_callback.php';
        exit;
    }

    // Page-local AJAX (ms365_action) must not be wrapped in admin tab chrome.
    if (isset($_REQUEST['ms365_action']) && in_array($action, ['provision', 'trials', 'tenant_export'], true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!isset($_SESSION['adminid']) || (int) $_SESSION['adminid'] <= 0) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['status' => 'fail', 'message' => 'Admin login required.']);
            exit;
        }
        if ($action === 'provision') {
            require __DIR__ . '/pages/admin/provision.php';
            ms365backup_admin_provision($vars);
        } elseif ($action === 'tenant_export') {
            require __DIR__ . '/pages/admin/tenant_export.php';
            ms365backup_admin_tenant_export($vars);
        } else {
            require __DIR__ . '/pages/admin/trials.php';
            ms365backup_admin_trials($vars);
        }
        exit;
    }

    if (!isset($_SESSION['adminid']) || (int) $_SESSION['adminid'] <= 0) {
        echo '<div class="alert alert-danger">Admin login required.</div>';
        return;
    }

    $tableBgClass = in_array($action, ['provision', 'tenant_export'], true) ? 'tablebg table-bg-overflow-visible' : 'tablebg';
    echo '<div class="' . $tableBgClass . '">';
    echo '<h2>MS365 Backup <small class="text-muted">(Dev Tool)</small></h2>';

    $pages = [
        'dashboard' => 'Dashboard',
        'discover' => 'Discovery',
        'backup' => 'Backup',
        'seeder' => 'Tenant Seeder',
        'fleet' => 'Worker Fleet',
        'jobs' => 'Jobs',
        'trials' => 'Trials',
        'provision' => 'Provision Customer',
        'tenant_export' => 'Tenant Export',
    ];
    echo '<p style="margin-bottom:15px">';
    foreach ($pages as $key => $label) {
        $active = ($action === $key || ($action === 'dashboard' && $key === 'dashboard' && !isset($_GET['action']))) ? ' btn-primary' : ' btn-default';
        if ($action === 'run') {
            $active = ' btn-default';
        }
        $href = $key === 'dashboard' ? $baseUrl : $baseUrl . '&action=' . $key;
        echo '<a href="' . htmlspecialchars($href) . '" class="btn' . $active . '">' . htmlspecialchars($label) . '</a> ';
    }
    echo '</p>';

    switch ($action) {
        case 'discover':
            require __DIR__ . '/pages/admin/discover.php';
            break;
        case 'backup':
            require __DIR__ . '/pages/admin/backup.php';
            break;
        case 'run':
            require __DIR__ . '/pages/admin/run.php';
            break;
        case 'seeder':
            require __DIR__ . '/pages/admin/seeder.php';
            break;
        case 'fleet':
            require __DIR__ . '/pages/admin/fleet.php';
            break;
        case 'jobs':
            require __DIR__ . '/pages/admin/jobs.php';
            break;
        case 'trials':
            require __DIR__ . '/pages/admin/trials.php';
            ms365backup_admin_trials($vars);
            break;
        case 'provision':
            require __DIR__ . '/pages/admin/provision.php';
            ms365backup_admin_provision($vars);
            break;
        case 'tenant_export':
            require __DIR__ . '/pages/admin/tenant_export.php';
            ms365backup_admin_tenant_export($vars);
            break;
        case 'dashboard':
        default:
            require __DIR__ . '/pages/admin/dashboard.php';
            break;
    }

    echo '</div>';
}

/**
 * WHMCS client area output.
 *
 * @param array<string, mixed> $vars
 */
function ms365backup_clientarea(array $vars): array
{
    if (!isset($_SESSION['uid']) || (int) $_SESSION['uid'] <= 0) {
        return [
            'pagetitle' => 'Microsoft 365 Backup',
            'templatefile' => '',
            'requirelogin' => true,
            'vars' => [],
        ];
    }

    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}
