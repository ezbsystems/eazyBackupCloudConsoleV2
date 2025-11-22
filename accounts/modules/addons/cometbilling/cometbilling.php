<?php
if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

use WHMCS\Database\Capsule;

// Composer autoload (if installed)
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Fallback autoloader for CometBilling classes if composer isn't installed
spl_autoload_register(function ($class) {
    $prefix = 'CometBilling\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $file = __DIR__ . '/lib/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Module config (shows under Setup > Addon Modules > CometBilling)
 * IMPORTANT: The Portal Token must be a Password field so WHMCS encrypts it at rest.
 */
function cometbilling_config()
{
    return [
        'name'        => 'CometBilling',
        'description' => 'Pull Comet Account Portal billing data, store locally, and reconcile.',
        'version'     => '1.0.0',
        'author'      => 'eazyBackup',
        'language'    => 'english',
        'fields'      => [
            'PortalBaseUrl' => [
                'FriendlyName' => 'Account Portal Base URL',
                'Type'         => 'text',
                'Size'         => '60',
                'Default'      => 'https://account.cometbackup.com',
                'Description'  => 'Base URL of the Comet Account Portal.',
            ],
            'PortalAuthType' => [
                'FriendlyName' => 'Auth Type',
                'Type'         => 'dropdown',
                'Options'      => 'token',
                'Default'      => 'token',
                'Description'  => 'Use Company API token.',
            ],
            'PortalToken' => [
                'FriendlyName' => 'Portal Token',
                'Type'         => 'password', // WHMCS will encrypt this
                'Size'         => '80',
                'Default'      => '',
                'Description'  => 'Company API token with permission to run billing reports.',
            ],
            'EnableDailyPull' => [
                'FriendlyName' => 'Enable Daily Pull',
                'Type'         => 'yesno',
                'Description'  => 'Run a daily import during the WHMCS cron.',
            ],
            'HttpTimeoutSeconds' => [
                'FriendlyName' => 'HTTP Timeout (seconds)',
                'Type'         => 'text',
                'Size'         => '6',
                'Default'      => '180',
                'Description'  => 'Increase if large reports time out (cURL error 28).',
            ],
        ],
    ];
}

function cometbilling_activate()
{
    try {
        $schemaFile = __DIR__ . '/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new \RuntimeException('Missing schema.sql');
        }
        $sql = file_get_contents($schemaFile);
        if ($sql) {
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt !== '') {
                    Capsule::connection()->statement($stmt);
                }
            }
        }
        return ['status' => 'success', 'description' => 'CometBilling activated.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
}

function cometbilling_deactivate()
{
    // Keep data by default; if you want a purge flag, add it later.
    return ['status' => 'success', 'description' => 'CometBilling deactivated. Data preserved.'];
}

function cometbilling_upgrade($vars)
{
    // Implement versioned migrations if you later add columns/tables.
    // Use $vars['version'] to branch behavior.
}

/**
 * Admin output router
 */
function cometbilling_output($vars)
{
    $action = $_GET['action'] ?? 'dashboard';
    $baseUrl = 'addonmodules.php?module=cometbilling';

    echo '<div class="tablebg">';
    echo '<h2>Comet Billing</h2>';
    echo '<p><a href="'.$baseUrl.'&action=dashboard">Dashboard</a> | '
        . '<a href="'.$baseUrl.'&action=purchases">Purchases</a> | '
        . '<a href="'.$baseUrl.'&action=usage">Usage</a> | '
        . '<a href="'.$baseUrl.'&action=active_services">Active Services</a> | '
        . '<a href="'.$baseUrl.'&action=reconcile">Reconcile</a> | '
        . '<a href="'.$baseUrl.'&action=keys">API Keys</a></p>';

    switch ($action) {
        case 'pullnow':
            echo '<h3>Manual Pull</h3>';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('check_token') && check_token('WHMCS.admin.default')) {
                if (!defined('COMETBILLING_INLINE')) {
                    define('COMETBILLING_INLINE', true);
                }
                ob_start();
                include __DIR__ . '/bin/portal_pull.php';
                $output = ob_get_clean();
                echo '<pre style="max-height:400px;overflow:auto">' . htmlspecialchars((string)$output) . '</pre>';
                echo '<p><a class="btn btn-default" href="'.$baseUrl.'">Back</a></p>';
            } else {
                echo '<form method="post">' . generate_token('WHMCS.admin.default') . '<button class="btn btn-primary" type="submit">Run Pull Now</button></form>';
            }
            break;
        case 'purchases':
            include __DIR__ . '/templates/admin/purchases.tpl.php';
            break;
        case 'usage':
            include __DIR__ . '/templates/admin/usage.tpl.php';
            break;
        case 'active_services':
            include __DIR__ . '/templates/admin/active_services.tpl.php';
            break;
        case 'reconcile':
            include __DIR__ . '/templates/admin/reconcile.tpl.php';
            break;
        case 'keys':
            include __DIR__ . '/templates/admin/keys.tpl.php';
            break;
        case 'dashboard':
        default:
            include __DIR__ . '/templates/admin/dashboard.tpl.php';
            break;
    }

    echo '</div>';
}

/**
 * Optional: WHMCS Cron integration (runs if EnableDailyPull is ON)
 */
function cometbilling_cron($vars)
{
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cometbilling')
        ->pluck('value', 'setting');

    if (!empty($settings['EnableDailyPull'])) {
        // Run importer
        $cmd = PHP_BINARY . ' ' . __DIR__ . '/bin/portal_pull.php';
        // Non-blocking fire & forget; or just include the script directly.
        if (function_exists('proc_open')) {
            @proc_close(@proc_open($cmd . ' >/dev/null 2>&1 &', [], $pipes));
        } else {
            // Fallback: inline
            include __DIR__ . '/bin/portal_pull.php';
        }
    }
}


