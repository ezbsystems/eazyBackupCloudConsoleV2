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
        'version'     => '1.0.3',
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
        // CREATE TABLE IF NOT EXISTS does not ALTER existing tables — apply
        // incremental migrations so deactivate/reactivate still upgrades schema.
        $migrate = \CometBilling\SchemaMigrator::ensureLatest();
        if (!$migrate['ok']) {
            throw new \RuntimeException($migrate['error'] ?? 'Schema migration failed');
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
    $version = $vars['version'] ?? '0';

    // 1.0.0 → 1.0.1: unique usage_date on allocations
    if (version_compare($version, '1.0.1', '<')) {
        try {
            if (Capsule::schema()->hasTable('cb_credit_allocations')) {
                $indexes = Capsule::select("SHOW INDEX FROM cb_credit_allocations WHERE Key_name = 'uq_usage_date'");
                if (empty($indexes)) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_allocations ADD UNIQUE KEY uq_usage_date (usage_date)'
                    );
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal; ensureTables will retry on next allocation
        }
    }

    // 1.0.1 → 1.0.2: per-device server inventory for reconcile drill-down
    if (version_compare($version, '1.0.2', '<')) {
        try {
            if (!Capsule::schema()->hasTable('cb_server_device_inventory')) {
                Capsule::schema()->create('cb_server_device_inventory', function ($table) {
                    $table->bigIncrements('id');
                    $table->date('snapshot_date');
                    $table->string('server_key', 64);
                    $table->string('username', 255)->default('');
                    $table->string('device_id', 64);
                    $table->string('friendly_name', 255)->nullable();
                    $table->unsignedInteger('hyperv_vms')->default(0);
                    $table->unsignedInteger('vmware_vms')->default(0);
                    $table->unsignedInteger('proxmox_vms')->default(0);
                    $table->unsignedInteger('disk_image')->default(0);
                    $table->unsignedInteger('mssql')->default(0);
                    $table->unsignedInteger('m365_accounts')->default(0);
                    $table->dateTime('created_at');
                    $table->unique(['snapshot_date', 'server_key', 'device_id'], 'uq_snapshot_device');
                    $table->index('snapshot_date');
                    $table->index('server_key');
                });
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    // Always idempotent — CREATE IF NOT EXISTS does not ALTER existing tables,
    // and WHMCS may bump version without applying ALTERs after deactivate/reactivate.
    \CometBilling\SchemaMigrator::ensureLatest();
}

/**
 * Release PHP session lock so other admin tabs stay responsive during long jobs.
 */
function cometbilling_releaseSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * Resolve a PHP CLI binary suitable for background scripts.
 * Under PHP-FPM, PHP_BINARY is php-fpm and cannot execute CLI scripts.
 */
function cometbilling_phpCliBinary(): string
{
    $candidates = [];

    if (defined('PHP_BINARY') && PHP_BINARY !== '') {
        $candidates[] = PHP_BINARY;
    }

    $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $candidates[] = '/usr/bin/php' . $version;
    $candidates[] = '/usr/local/bin/php' . $version;
    $candidates[] = '/usr/bin/php';
    $candidates[] = '/usr/local/bin/php';

    $which = trim((string) @shell_exec('command -v php 2>/dev/null'));
    if ($which !== '') {
        $candidates[] = $which;
    }

    $seen = [];
    foreach ($candidates as $bin) {
        if ($bin === '' || isset($seen[$bin])) {
            continue;
        }
        $seen[$bin] = true;

        $base = basename($bin);
        if (stripos($base, 'php-fpm') !== false) {
            continue;
        }
        if (!is_executable($bin)) {
            continue;
        }

        return $bin;
    }

    return 'php';
}

/**
 * Spawn a CLI script in the background; returns false if spawn failed.
 *
 * @param string $scriptBasename e.g. portal_pull.php
 * @param string|null $jobKey cb_settings job prefix, e.g. portal_pull
 */
function cometbilling_spawnCli(string $scriptBasename, ?string $jobKey = null): bool
{
    cometbilling_releaseSession();

    $script = __DIR__ . '/bin/' . $scriptBasename;
    if (!is_file($script)) {
        return false;
    }

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/' . pathinfo($scriptBasename, PATHINFO_FILENAME) . '.log';
    $phpBinary = cometbilling_phpCliBinary();
    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($script)
        . ' >> ' . escapeshellarg($logFile) . ' 2>&1';

    if ($jobKey !== null && class_exists(\CometBilling\Settings::class)) {
        \CometBilling\Settings::markJobRunning($jobKey);
    }

    if (!function_exists('proc_open')) {
        return false;
    }

    // Refuse to "succeed" with an FPM binary — fall back to inline execution
    if (stripos(basename($phpBinary), 'php-fpm') !== false) {
        return false;
    }

    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ];
    $proc = @proc_open(['/bin/bash', '-c', $cmd . ' &'], $descriptors, $pipes);
    if (!is_resource($proc)) {
        return false;
    }
    proc_close($proc);

    return true;
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
    echo '<p style="margin-bottom: 15px;">'
        . '<a href="'.$baseUrl.'&action=dashboard" class="btn btn-default">Dashboard</a> '
        . '<a href="'.$baseUrl.'&action=sync" class="btn btn-default">Data Sync</a> '
        . '<a href="'.$baseUrl.'&action=reconcile" class="btn btn-default">Reconcile</a> '
        . '<a href="'.$baseUrl.'&action=historical_reconcile" class="btn btn-default">Historical Reconcile</a> '
        . '<a href="'.$baseUrl.'&action=credit_lots" class="btn btn-default">Credit Lots</a> '
        . '<a href="'.$baseUrl.'&action=allocations" class="btn btn-default">Allocations</a> '
        . '<a href="'.$baseUrl.'&action=purchases" class="btn btn-default">Purchases</a> '
        . '<a href="'.$baseUrl.'&action=active_services" class="btn btn-default">Active Services</a> '
        . '<a href="'.$baseUrl.'&action=usage" class="btn btn-default">Usage History</a> '
        . '<a href="'.$baseUrl.'&action=new_items" class="btn btn-default">New Items</a> '
        . '<a href="'.$baseUrl.'&action=period_compare" class="btn btn-default">Period Compare</a> '
        . '<a href="'.$baseUrl.'&action=policy_status" class="btn btn-default">Policy Status</a> '
        . '<a href="'.$baseUrl.'&action=m365_report" class="btn btn-default">M365 Report</a>'
        . '</p>';

    switch ($action) {
        case 'pullnow':
            echo '<h3>Manual Pull</h3>';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('check_token') && check_token('WHMCS.admin.default')) {
                $spawned = cometbilling_spawnCli('portal_pull.php', 'portal_pull');
                if ($spawned) {
                    echo '<div class="successbox">Portal pull started in the background. '
                        . 'Other admin pages will remain responsive. '
                        . 'Check <a href="' . $baseUrl . '&action=sync">Data Sync</a> for status (refresh after a minute).</div>';
                } else {
                    cometbilling_releaseSession();
                    if (!defined('COMETBILLING_INLINE')) {
                        define('COMETBILLING_INLINE', true);
                    }
                    ob_start();
                    include __DIR__ . '/bin/portal_pull.php';
                    $output = ob_get_clean();
                    echo '<pre style="max-height:400px;overflow:auto">' . htmlspecialchars((string)$output) . '</pre>';
                }
                echo '<p><a class="btn btn-default" href="' . $baseUrl . '&action=sync">Data Sync</a> '
                    . '<a class="btn btn-default" href="' . $baseUrl . '">Dashboard</a></p>';
            } else {
                echo '<form method="post">' . generate_token('WHMCS.admin.default') . '<button class="btn btn-primary" type="submit">Run Pull Now</button></form>';
                echo '<p class="text-muted">Runs in the background so other admin tabs stay responsive.</p>';
            }
            break;
            
        case 'purchases':
            include __DIR__ . '/templates/admin/purchases.tpl.php';
            break;
            
        case 'usage':
            // Large date ranges can return tens of thousands of rows.
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            include __DIR__ . '/templates/admin/usage.tpl.php';
            break;

        case 'new_items':
            // Full-history scan for first-billed dates — release session so other admin tabs stay responsive.
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            include __DIR__ . '/templates/admin/new_items.tpl.php';
            break;

        case 'period_compare':
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            include __DIR__ . '/templates/admin/period_compare.tpl.php';
            break;

        case 'policy_status':
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            include __DIR__ . '/templates/admin/policy_status.tpl.php';
            break;

        case 'policy_status_csv':
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            try {
                $section = isset($_GET['section']) ? (string) $_GET['section'] : '';
                $group = isset($_GET['group']) ? (string) $_GET['group'] : \CometBilling\PolicyStatusReport::GROUP_M365;
                if (!\CometBilling\PolicyStatusReport::isValidSection($section)) {
                    throw new \InvalidArgumentException(
                        'Invalid section. Use warning_accounts, billed_unhealthy, billed_successful, historical_device_summary, or historical_device_detail.'
                    );
                }
                if (!\CometBilling\PolicyStatusReport::isValidGroup($group)) {
                    throw new \InvalidArgumentException(
                        'Invalid group. Use m365 or virtual_server.'
                    );
                }
                \CometBilling\PolicyStatusReport::streamCsv($section, $group);
            } catch (\Throwable $e) {
                echo '<div class="errorbox">Policy Status CSV export failed: '
                    . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<p><a class="btn btn-default" href="' . $baseUrl
                    . '&action=policy_status">Back to Policy Status</a></p>';
            }
            break;

        case 'm365_report':
            include __DIR__ . '/templates/admin/m365_report.tpl.php';
            break;

        case 'historical_reconcile':
            // Long audit scan — release session so other admin tabs stay responsive.
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            include __DIR__ . '/templates/admin/historical_reconcile.tpl.php';
            break;

        case 'historical_reconcile_export':
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            try {
                $from = !empty($_GET['from']) ? (string) $_GET['from'] : null;
                $to = !empty($_GET['to']) ? (string) $_GET['to'] : null;
                $range = \CometBilling\HistoricalReconciler::resolveDateRange(null, $from, $to);
                \CometBilling\HistoricalReconciler::streamCsv($range['from'], $range['to']);
            } catch (\Throwable $e) {
                echo '<div class="errorbox">Export failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<p><a href="' . $baseUrl . '&action=historical_reconcile" class="btn btn-default">Back to Historical Reconcile</a></p>';
            }
            break;

        case 'historical_reconcile_dispute_csv':
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            try {
                $from = !empty($_GET['from']) ? (string) $_GET['from'] : null;
                $to = !empty($_GET['to']) ? (string) $_GET['to'] : null;
                $range = \CometBilling\HistoricalReconciler::resolveDateRange(null, $from, $to);
                \CometBilling\DisputePackExporter::streamCsv($range['from'], $range['to']);
            } catch (\Throwable $e) {
                echo '<div class="errorbox">Dispute pack CSV export failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<p><a href="' . $baseUrl . '&action=historical_reconcile" class="btn btn-default">Back to Historical Reconcile</a></p>';
            }
            break;

        case 'historical_reconcile_dispute_pdf':
            cometbilling_releaseSession();
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            try {
                $from = !empty($_GET['from']) ? (string) $_GET['from'] : null;
                $to = !empty($_GET['to']) ? (string) $_GET['to'] : null;
                $range = \CometBilling\HistoricalReconciler::resolveDateRange(null, $from, $to);
                \CometBilling\DisputePackExporter::streamHtml($range['from'], $range['to']);
            } catch (\Throwable $e) {
                echo '<div class="errorbox">Dispute pack PDF export failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<p><a href="' . $baseUrl . '&action=historical_reconcile" class="btn btn-default">Back to Historical Reconcile</a></p>';
            }
            break;
            
        case 'active_services':
            include __DIR__ . '/templates/admin/active_services.tpl.php';
            break;
            
        case 'reconcile':
            include __DIR__ . '/templates/admin/reconcile.tpl.php';
            break;

        case 'reconcile_export_overbilled':
            try {
                $snapshotDate = !empty($_GET['snapshot_date']) ? (string) $_GET['snapshot_date'] : null;
                \CometBilling\Reconciler::streamOverbilledPastGraceCsv($snapshotDate);
            } catch (\Throwable $e) {
                echo '<div class="errorbox">Export failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<p><a href="' . $baseUrl . '&action=reconcile" class="btn btn-default">Back to Reconciliation</a></p>';
            }
            break;
            
        case 'reconcile_view':
            $reportId = (int)($_GET['id'] ?? 0);
            if ($reportId > 0) {
                $saved = \CometBilling\Reconciler::getReport($reportId);
                if ($saved) {
                    $report = \CometBilling\Reconciler::reportFromSaved($saved);
                    $exportSnapshot = $report['snapshot_date'] ?? null;
                    echo '<h3>Reconciliation Report #' . $reportId . '</h3>';
                    echo '<p>Generated: ' . htmlspecialchars($saved->report_date) . '</p>';
                    echo '<p><a class="btn btn-default" href="' . $baseUrl
                        . '&action=reconcile_export_overbilled'
                        . ($exportSnapshot ? '&snapshot_date=' . urlencode((string) $exportSnapshot) : '')
                        . '">Export overbilled past grace (CSV)</a></p>';
                    include __DIR__ . '/templates/admin/reconcile_report_partial.tpl.php';
                    echo '<p><a href="'.$baseUrl.'&action=reconcile" class="btn btn-default">Back to Reconciliation</a></p>';
                } else {
                    echo '<div class="errorbox">Report not found.</div>';
                }
            }
            break;

        case 'sync':
            include __DIR__ . '/templates/admin/sync.tpl.php';
            break;

        case 'allocations':
            include __DIR__ . '/templates/admin/allocations.tpl.php';
            break;
            
        case 'credit_lots':
            include __DIR__ . '/templates/admin/credit_lots.tpl.php';
            break;
            
        case 'collect_usage':
            echo '<h3>Collect Server Usage</h3>';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('check_token') && check_token('WHMCS.admin.default')) {
                $serverKey = $_POST['server_key'] ?? 'all';
                if ($serverKey === 'all' && cometbilling_spawnCli('collect_usage.php', 'collect_usage')) {
                    echo '<div class="successbox">Usage collection started in the background. '
                        . 'Check <a href="' . $baseUrl . '&action=sync">Data Sync</a> for status.</div>';
                } else {
                    cometbilling_releaseSession();
                    $cometAutoload = dirname(__DIR__, 2) . '/servers/comet/vendor/autoload.php';
                    if (file_exists($cometAutoload)) {
                        require_once $cometAutoload;
                    }
                    try {
                        if ($serverKey && $serverKey !== 'all') {
                            $data = \CometBilling\ServerUsageCollector::collectFromServer($serverKey);
                            echo '<div class="successbox">Collected usage from ' . htmlspecialchars($serverKey) . '</div>';
                        } else {
                            $data = \CometBilling\ServerUsageCollector::collectAll();
                            echo '<div class="successbox">Collected usage from all servers</div>';
                        }
                        echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
                    } catch (\Exception $e) {
                        echo '<div class="errorbox">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
                echo '<p><a class="btn btn-default" href="' . $baseUrl . '&action=sync">Data Sync</a> '
                    . '<a class="btn btn-default" href="' . $baseUrl . '">Dashboard</a></p>';
            } else {
                echo '<form method="post">' . generate_token('WHMCS.admin.default');
                echo '<p>Server: <select name="server_key">';
                echo '<option value="all">All Servers</option>';
                echo '<option value="cometbackup">cometbackup</option>';
                echo '<option value="obc">obc</option>';
                echo '</select></p>';
                echo '<button class="btn btn-primary" type="submit">Collect Now</button></form>';
            }
            break;
            
        case 'keys':
            echo '<h3>API Keys</h3>';
            echo '<div class="infobox">Multi-account API key management is coming soon. Portal authentication currently uses the token configured in the addon module settings.</div>';
            echo '<p><a href="'.$baseUrl.'" class="btn btn-default">Back</a></p>';
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
 * - Pulls Portal data (active services)
 * - Collects server usage snapshots
 */
function cometbilling_cron($vars)
{
    $settings = \CometBilling\Settings::getAddonSettings();

    if (empty($settings['EnableDailyPull'])) {
        return;
    }

    $runScript = function (string $scriptPath, string $label) {
        $cmd = escapeshellarg(cometbilling_phpCliBinary()) . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
        $output = [];
        $exitCode = 0;

        if (function_exists('proc_open')) {
            exec($cmd, $output, $exitCode);
        } else {
            if (!defined('COMETBILLING_INLINE')) {
                define('COMETBILLING_INLINE', true);
            }
            ob_start();
            include $scriptPath;
            $output = [ob_get_clean()];
            $exitCode = 0;
        }

        if ($exitCode !== 0) {
            $msg = '[CometBilling] ' . $label . ' failed (exit ' . $exitCode . '): ' . implode("\n", array_slice($output, -5));
            if (function_exists('logActivity')) {
                logActivity($msg);
            }
        }
    };

    $runScript(__DIR__ . '/bin/portal_pull.php', 'Portal pull');
    $runScript(__DIR__ . '/bin/collect_usage.php', 'Server usage collection');
}


