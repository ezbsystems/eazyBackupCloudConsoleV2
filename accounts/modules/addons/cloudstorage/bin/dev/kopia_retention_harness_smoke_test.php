<?php
declare(strict_types=1);

/**
 * Smoke test for cloudstorage dev harness.
 * Asserts cloudstorage module loads correctly and cloudstorage_config is available.
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_harness_smoke_test.php
 * (from repo root or worktree root)
 */

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('cloudstorage_config')) {
    fwrite(STDERR, "[smoke] cloudstorage module not loaded: cloudstorage_config() does not exist.\n");
    exit(1);
}

echo "PASS\n";
exit(0);
