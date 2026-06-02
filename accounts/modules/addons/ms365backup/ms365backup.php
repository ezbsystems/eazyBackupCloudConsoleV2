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
        'version' => '1.5.0',
        'author' => 'eazyBackup',
        'language' => 'english',
        'fields' => [],
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
}

function ms365backup_activate(): array
{
    try {
        ms365backup_apply_schema();
        ms365backup_apply_migrations();
        ms365backup_ensure_storage();
        return ['status' => 'success', 'description' => 'MS365 Backup activated. Configure credentials under Addons → MS365 Backup.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}

function ms365backup_deactivate(): array
{
    return ['status' => 'success', 'description' => 'MS365 Backup deactivated. Backup data preserved.'];
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

    echo '<div class="tablebg">';
    echo '<h2>MS365 Backup <small class="text-muted">(Dev Tool)</small></h2>';

    $pages = [
        'dashboard' => 'Dashboard',
        'discover' => 'Discovery',
        'backup' => 'Backup',
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
        case 'dashboard':
        default:
            require __DIR__ . '/pages/admin/dashboard.php';
            break;
    }

    echo '</div>';
}
