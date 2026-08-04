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

    // 1.0.2 → 1.0.3: audit-grade evidence schema
    if (version_compare($version, '1.0.3', '<')) {
        try {
            if (Capsule::schema()->hasTable('cb_credit_usage')) {
                $sm = Capsule::schema();
                if (!$sm->hasColumn('cb_credit_usage', 'content_fingerprint')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN content_fingerprint CHAR(32) NULL AFTER row_fingerprint'
                    );
                }
                if (!$sm->hasColumn('cb_credit_usage', 'occurrence_number')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN occurrence_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER content_fingerprint'
                    );
                }
                if (!$sm->hasColumn('cb_credit_usage', 'packs_used_raw')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN packs_used_raw VARCHAR(512) NULL AFTER packs_used'
                    );
                }
                if (!$sm->hasColumn('cb_credit_usage', 'packs_used_parsed')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN packs_used_parsed JSON NULL AFTER packs_used_raw'
                    );
                }
                if (!$sm->hasColumn('cb_credit_usage', 'first_seen_at')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN first_seen_at DATETIME NULL AFTER created_at'
                    );
                }
                if (!$sm->hasColumn('cb_credit_usage', 'last_seen_at')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN last_seen_at DATETIME NULL AFTER first_seen_at'
                    );
                }
                if (!$sm->hasColumn('cb_credit_usage', 'pull_manifest_id')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN pull_manifest_id BIGINT UNSIGNED NULL AFTER last_seen_at'
                    );
                }
                if (!$sm->hasColumn('cb_credit_usage', 'is_present_in_latest_pull')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD COLUMN is_present_in_latest_pull TINYINT(1) NOT NULL DEFAULT 1 AFTER pull_manifest_id'
                    );
                }
                Capsule::connection()->statement(
                    'UPDATE cb_credit_usage SET content_fingerprint = row_fingerprint WHERE content_fingerprint IS NULL OR content_fingerprint = ""'
                );
                Capsule::connection()->statement(
                    'UPDATE cb_credit_usage SET first_seen_at = created_at WHERE first_seen_at IS NULL'
                );
                $indexes = Capsule::select("SHOW INDEX FROM cb_credit_usage WHERE Key_name = 'uq_fingerprint'");
                if (!empty($indexes)) {
                    Capsule::connection()->statement('ALTER TABLE cb_credit_usage DROP INDEX uq_fingerprint');
                }
                $indexesOcc = Capsule::select("SHOW INDEX FROM cb_credit_usage WHERE Key_name = 'uq_content_occurrence'");
                if (empty($indexesOcc)) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_usage ADD UNIQUE KEY uq_content_occurrence (content_fingerprint, occurrence_number)'
                    );
                }
            }

            if (!Capsule::schema()->hasTable('cb_portal_pull_manifests')) {
                Capsule::schema()->create('cb_portal_pull_manifests', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('pulled_at');
                    $table->string('source', 32);
                    $table->unsignedInteger('row_count')->default(0);
                    $table->unsignedInteger('new_rows')->default(0);
                    $table->unsignedInteger('updated_rows')->default(0);
                    $table->char('checksum', 32)->nullable();
                    $table->json('meta')->nullable();
                    $table->dateTime('created_at');
                    $table->index('pulled_at');
                });
            }

            if (!Capsule::schema()->hasTable('cb_purchase_import_batches')) {
                Capsule::schema()->create('cb_purchase_import_batches', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('imported_at');
                    $table->string('source_filename', 255)->nullable();
                    $table->date('earliest_date')->nullable();
                    $table->date('latest_date')->nullable();
                    $table->unsignedInteger('row_count')->default(0);
                    $table->unsignedInteger('purchase_count')->default(0);
                    $table->unsignedInteger('refund_count')->default(0);
                    $table->boolean('is_complete')->default(false);
                    $table->text('notes')->nullable();
                    $table->dateTime('created_at');
                });
            }

            if (Capsule::schema()->hasTable('cb_credit_purchases')) {
                $sm = Capsule::schema();
                if (!$sm->hasColumn('cb_credit_purchases', 'record_type')) {
                    Capsule::connection()->statement(
                        "ALTER TABLE cb_credit_purchases ADD COLUMN record_type ENUM('purchase','refund','adjustment') NOT NULL DEFAULT 'purchase' AFTER currency"
                    );
                }
                if (!$sm->hasColumn('cb_credit_purchases', 'import_batch_id')) {
                    Capsule::connection()->statement(
                        'ALTER TABLE cb_credit_purchases ADD COLUMN import_batch_id BIGINT UNSIGNED NULL AFTER record_type'
                    );
                }
            }

            if (!Capsule::schema()->hasTable('cb_audit_runs')) {
                Capsule::schema()->create('cb_audit_runs', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('run_at');
                    $table->date('from_date');
                    $table->date('to_date');
                    $table->json('summary');
                    $table->json('coverage')->nullable();
                    $table->dateTime('created_at');
                    $table->index('run_at');
                });
            }

            if (!Capsule::schema()->hasTable('cb_audit_findings')) {
                Capsule::schema()->create('cb_audit_findings', function ($table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('audit_run_id');
                    $table->unsignedBigInteger('usage_id')->nullable();
                    $table->string('verdict', 32);
                    $table->string('debit_evidence', 16)->default('unclear');
                    $table->string('billing_verdict', 32);
                    $table->decimal('amount', 12, 4);
                    $table->string('account', 128)->nullable();
                    $table->string('device_id', 128)->nullable();
                    $table->string('category', 32)->nullable();
                    $table->date('usage_date');
                    $table->string('item_desc', 255)->nullable();
                    $table->date('expected_billing_end')->nullable();
                    $table->json('confidence_reasons')->nullable();
                    $table->json('evidence');
                    $table->index('audit_run_id');
                    $table->index('verdict');
                    $table->index('usage_date');
                });
            }

            if (!Capsule::schema()->hasTable('cb_ledger_rebuild_batches')) {
                Capsule::schema()->create('cb_ledger_rebuild_batches', function ($table) {
                    $table->bigIncrements('id');
                    $table->dateTime('started_at');
                    $table->dateTime('completed_at')->nullable();
                    $table->date('opening_date');
                    $table->date('closing_date');
                    $table->decimal('opening_credit', 12, 4);
                    $table->decimal('closing_credit', 12, 4)->nullable();
                    $table->boolean('is_complete')->default(false);
                    $table->json('validation')->nullable();
                    $table->dateTime('created_at');
                });
            }

            if (!Capsule::schema()->hasTable('cb_credit_usage_allocations')) {
                Capsule::schema()->create('cb_credit_usage_allocations', function ($table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('usage_id');
                    $table->unsignedBigInteger('lot_id');
                    $table->decimal('amount', 12, 4);
                    $table->decimal('lot_remaining_after', 12, 4);
                    $table->unsignedBigInteger('rebuild_batch_id')->nullable();
                    $table->dateTime('created_at');
                    $table->unique(['usage_id', 'lot_id'], 'uq_usage_lot');
                    $table->index('usage_id');
                });
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }
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
            include __DIR__ . '/templates/admin/usage.tpl.php';
            break;

        case 'm365_report':
            include __DIR__ . '/templates/admin/m365_report.tpl.php';
            break;

        case 'historical_reconcile':
            include __DIR__ . '/templates/admin/historical_reconcile.tpl.php';
            break;

        case 'historical_reconcile_export':
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


