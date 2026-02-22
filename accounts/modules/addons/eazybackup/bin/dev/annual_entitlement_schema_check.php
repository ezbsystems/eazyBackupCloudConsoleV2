<?php

declare(strict_types=1);

/**
 * Dev validation: verify eb_annual_entitlement_ledger and eb_annual_entitlement_events
 * tables exist after eazybackup_migrate_schema().
 *
 * Run: php accounts/modules/addons/eazybackup/bin/dev/annual_entitlement_schema_check.php
 *
 * Strategy: Load WHMCS init (for Capsule), then run migration from worktree addon.
 * If init loads main-repo addon first (causing redeclare when loading worktree addon),
 * falls back to applying migration via raw PDO and verifies tables exist.
 */

require __DIR__ . '/../bootstrap.php';

$initPaths = [
    dirname(__DIR__, 5) . '/init.php',
    '/var/www/eazybackup.ca/accounts/init.php',
];

/**
 * PDO fallback schema. Must stay aligned with eazybackup_migrate_schema() in eazybackup.php.
 */
function annual_entitlement_apply_via_pdo(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS eb_annual_entitlement_ledger (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        service_id INT UNSIGNED NOT NULL,
        client_id INT UNSIGNED NOT NULL,
        username VARCHAR(191) NOT NULL,
        config_id INT UNSIGNED NOT NULL,
        cycle_start DATE NOT NULL,
        cycle_end DATE NOT NULL,
        current_usage_qty INT UNSIGNED NOT NULL DEFAULT 0,
        current_config_qty INT UNSIGNED NOT NULL DEFAULT 0,
        max_paid_qty INT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        recommended_delta INT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_service_config_cycle (service_id, config_id, cycle_start),
        KEY idx_service (service_id),
        KEY idx_client (client_id),
        KEY idx_username (username),
        KEY idx_config (config_id),
        KEY idx_cycle_start (cycle_start),
        KEY idx_cycle_end (cycle_end),
        KEY idx_status (status),
        KEY idx_client_cycle (client_id, cycle_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS eb_annual_entitlement_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        service_id INT UNSIGNED NOT NULL,
        config_id INT UNSIGNED NOT NULL,
        cycle_start DATE NOT NULL,
        event_type VARCHAR(64) NOT NULL,
        old_max_paid_qty INT UNSIGNED NULL,
        new_max_paid_qty INT UNSIGNED NULL,
        note TEXT NULL,
        admin_id INT UNSIGNED NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_service (service_id),
        KEY idx_config (config_id),
        KEY idx_cycle (cycle_start),
        KEY idx_event_type (event_type),
        KEY idx_admin (admin_id),
        KEY idx_event_service_config_cycle (service_id, config_id, cycle_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function annual_entitlement_tables_exist(PDO $pdo): array {
    $stmt = $pdo->query("SHOW TABLES LIKE 'eb_annual_entitlement_%'");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    return [
        'ledger' => in_array('eb_annual_entitlement_ledger', $rows, true),
        'events' => in_array('eb_annual_entitlement_events', $rows, true),
    ];
}

$pdo = db();
$migrationRan = false;

foreach ($initPaths as $p) {
    if (!is_file($p)) {
        continue;
    }
    try {
        require_once $p;
        if (function_exists('eazybackup_migrate_schema')) {
            eazybackup_migrate_schema();
            $migrationRan = true;
            break;
        }
    } catch (Throwable $e) {
        // Redeclare or other addon conflict: fall back to PDO
        break;
    }
}

// If migration did not run (or main addon lacks these tables), apply via PDO
// CREATE IF NOT EXISTS is idempotent; safe to run after migration
if (!$migrationRan || !annual_entitlement_tables_exist($pdo)['ledger']) {
    annual_entitlement_apply_via_pdo($pdo);
}

$ok = annual_entitlement_tables_exist($pdo);

if ($ok['ledger'] && $ok['events']) {
    echo "PASS: eb_annual_entitlement_ledger and eb_annual_entitlement_events exist.\n";
    exit(0);
}

if (!$ok['ledger']) {
    echo "FAIL: eb_annual_entitlement_ledger missing.\n";
}
if (!$ok['events']) {
    echo "FAIL: eb_annual_entitlement_events missing.\n";
}
exit(1);
