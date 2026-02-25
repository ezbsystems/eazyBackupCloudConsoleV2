<?php
declare(strict_types=1);

/**
 * Kopia retention release gate: verifies required retention classes exist.
 * Fails if routing, policy, or operation service class is missing.
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_release_gate_test.php
 * (from repo root or worktree root)
 *
 * See: KOPIA_RETENTION_IMPLEMENTATION_TASKS.md
 */

require_once __DIR__ . '/bootstrap.php';

$lib = dirname(__DIR__, 2) . '/lib/Client/';
require_once $lib . 'KopiaRetentionRoutingService.php';
require_once $lib . 'KopiaRetentionPolicyService.php';
require_once $lib . 'KopiaRetentionOperationService.php';

$required = [
    'WHMCS\\Module\\Addon\\CloudStorage\\Client\\KopiaRetentionRoutingService',
    'WHMCS\\Module\\Addon\\CloudStorage\\Client\\KopiaRetentionPolicyService',
    'WHMCS\\Module\\Addon\\CloudStorage\\Client\\KopiaRetentionOperationService',
];

$missing = [];
foreach ($required as $class) {
    if (!class_exists($class, false)) {
        $missing[] = $class;
    }
}

if (!empty($missing)) {
    fwrite(STDERR, "FAIL: Missing retention classes:\n");
    foreach ($missing as $c) {
        fwrite(STDERR, "  - {$c}\n");
    }
    exit(1);
}

echo "PASS: All Kopia retention release gate classes present\n";
exit(0);
