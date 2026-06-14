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
        'version' => '1.17.0',
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
            'ms365_engine_mode' => [
                'FriendlyName' => 'Backup engine mode',
                'Type' => 'dropdown',
                'Options' => 'php,kopia,kopia_shadow',
                'Default' => 'php',
                'Description' => 'php = legacy PHP worker; kopia = Proxmox Go worker fleet; kopia_shadow = PHP then re-queue for Kopia comparison.',
            ],
            'ms365_worker_token' => [
                'FriendlyName' => 'Worker API token',
                'Type' => 'password',
                'Size' => '64',
                'Description' => 'Shared secret for ms365-backup-worker fleet (X-MS365-Worker-Token header).',
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
                'Default' => '3',
            ],
            'ms365_per_client_max_concurrent' => [
                'FriendlyName' => 'Max concurrent per WHMCS client',
                'Type' => 'text',
                'Size' => '4',
                'Default' => '10',
            ],
            'ms365_worker_lease_seconds' => [
                'FriendlyName' => 'Worker lease seconds',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '7200',
                'Description' => 'Claim lease duration; renewed on worker progress/heartbeat for long runs.',
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
                'Default' => '16',
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
            ],
            'proxmox_lxc_template_vmid' => [
                'FriendlyName' => 'Proxmox LXC template VMID',
                'Type' => 'text',
                'Size' => '8',
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
        if ($stmt !== '') {
            Capsule::connection()->statement($stmt);
        }
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
 * Incremental changes for existing databases (enum changes, new columns, etc.).
 * Runs every file in sql/ matching upgrade_*.sql in sorted order.
 */
function ms365backup_apply_migrations(): void
{
    $sqlDir = __DIR__ . '/sql';
    if (!is_dir($sqlDir)) {
        return;
    }
    $files = glob($sqlDir . '/upgrade_*.sql') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        try {
            ms365backup_run_sql_file($file);
        } catch (\Throwable $e) {
            // Idempotent migrations (e.g. enum already includes a value) may fail on re-run.
            logActivity('MS365 Backup migration skipped or already applied: ' . basename($file) . ' — ' . $e->getMessage());
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

function ms365backup_activate(): array
{
    try {
        ms365backup_restore_settings();
        ms365backup_apply_schema();
        ms365backup_apply_migrations();
        ms365backup_ensure_storage();
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
    } catch (\Throwable $e) {
        logActivity('MS365 Backup upgrade error: ' . $e->getMessage());
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
        . '</div>';
}

function ms365backup_output(array $vars): void
{
    if (!isset($_SESSION['adminid']) || (int) $_SESSION['adminid'] <= 0) {
        echo '<div class="alert alert-danger">Admin login required.</div>';
        return;
    }

    $action = (string) ($_GET['action'] ?? 'dashboard');
    $baseUrl = 'addonmodules.php?module=ms365backup';

    if ($action === 'api') {
        require __DIR__ . '/pages/admin/api.php';
        exit;
    }

    if ($action === 'seeder_oauth_callback') {
        require __DIR__ . '/pages/admin/seeder_oauth_callback.php';
        exit;
    }

    echo '<div class="tablebg">';
    echo '<h2>MS365 Backup <small class="text-muted">(Dev Tool)</small></h2>';

    $pages = [
        'dashboard' => 'Dashboard',
        'discover' => 'Discovery',
        'backup' => 'Backup',
        'seeder' => 'Tenant Seeder',
        'fleet' => 'Worker Fleet',
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

    header('Location: index.php?m=cloudstorage&page=e3backup&view=ms365');
    exit;
}
