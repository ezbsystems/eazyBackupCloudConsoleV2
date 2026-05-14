<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the eazyBackup addon (Phase A).
 *
 * Loads in this order:
 *   1) Addon vendor autoload (PSR-4 for PartnerHub\, EazyBackup\Tests\, etc.)
 *   2) WHMCS core via /var/www/eazybackup.ca/accounts/init.php (sets up Capsule)
 *   3) The addon's main entrypoint (registers eazybackup_* helpers)
 *   4) Production-DB guard
 *   5) Force test-safe addon flags (skip DNS / nginx / cert hops in CI)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "PHPUnit bootstrap must run from CLI.\n");
    exit(2);
}

// Quiet PHP 8 deprecation noise from WHMCS core; tests will still surface real errors.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$addonRoot = dirname(__DIR__);
$repoRoot = dirname($addonRoot, 4);

// 1) Addon vendor autoload
$addonAutoload = $addonRoot . '/vendor/autoload.php';
if (!is_file($addonAutoload)) {
    fwrite(STDERR, "[bootstrap] addon vendor/autoload.php not found at {$addonAutoload}.\n");
    fwrite(STDERR, "[bootstrap] Run `composer install` in {$addonRoot} first.\n");
    exit(2);
}
require_once $addonAutoload;

// 2) WHMCS init.php (sets up Capsule + WHMCS\* namespace classes)
$whmcsInit = $repoRoot . '/accounts/init.php';
if (!is_file($whmcsInit)) {
    fwrite(STDERR, "[bootstrap] WHMCS init.php not found at {$whmcsInit}.\n");
    exit(2);
}
require_once $whmcsInit;

if (!class_exists(\WHMCS\Database\Capsule::class)) {
    fwrite(STDERR, "[bootstrap] WHMCS Capsule class did not load. Is ionCube Loader installed?\n");
    exit(2);
}

// 3) Addon main entrypoint (registers eazybackup_* helpers, including eazybackup_generate_ulid)
$addonEntry = $addonRoot . '/eazybackup.php';
if (is_file($addonEntry)) {
    require_once $addonEntry;
}

// MeteredUsage.php declares namespaced *functions*, not a class — PSR-4 cannot autoload them,
// so require explicitly. Mirrors bin/partnerhub_usage_job.php production usage.
$meteredUsageFile = $addonRoot . '/lib/PartnerHub/MeteredUsage.php';
if (is_file($meteredUsageFile)) {
    require_once $meteredUsageFile;
}

// Several Partner Hub controllers define top-level eb_ph_* helper functions that
// integration tests drive directly. Preload so individual test files don't have to.
foreach ([
    '/pages/partnerhub/StripeWebhookController.php',     // webhook handlers (Phase C/C2)
    '/pages/partnerhub/TenantsController.php',           // shared tenant + plan helpers
    '/pages/partnerhub/UsageController.php',             // usage push orchestration (Phase H)
    '/pages/partnerhub/CatalogPlansController.php',      // plan template + assignment (Phase G)
    '/pages/whitelabel/PublicSignupController.php',      // public signup validators (Phase F)
] as $controllerRel) {
    $controllerPath = $addonRoot . $controllerRel;
    if (is_file($controllerPath)) {
        require_once $controllerPath;
    }
}

// 4) Production-DB guard.
// If EB_TEST_PROD_GUARD_URL is set (e.g. via env or the developer's shell rc), refuse to run
// when tblconfiguration.SystemURL matches it. Failing here is loud and intentional.
$prodGuard = trim((string) getenv('EB_TEST_PROD_GUARD_URL'));
if ($prodGuard !== '') {
    try {
        $systemUrl = (string) (\WHMCS\Database\Capsule::table('tblconfiguration')
            ->where('setting', 'SystemURL')
            ->value('value') ?? '');
        $normalize = static function (string $s): string {
            $s = strtolower(trim($s));
            $s = preg_replace('#^https?://#', '', $s);
            return rtrim((string) $s, "/ \t\n\r\0\x0B");
        };
        if ($normalize($systemUrl) !== '' && $normalize($systemUrl) === $normalize($prodGuard)) {
            fwrite(STDERR, "[bootstrap] PRODUCTION DB GUARD TRIPPED: SystemURL '{$systemUrl}' matches EB_TEST_PROD_GUARD_URL. Refusing to run tests.\n");
            exit(2);
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "[bootstrap] WARNING: production guard check failed: {$e->getMessage()}\n");
    }
}

// 5) Force test-safe addon flags into the in-memory addon-settings cache used by hooks
// (whitelabel pipeline + cert/nginx ops should be no-ops during tests).
$GLOBALS['EB_TEST_ADDON_OVERRIDES'] = [
    'whitelabel_dev_mode' => '1',
    'whitelabel_dev_skip_dns' => '1',
    'whitelabel_dev_skip_nginx' => '1',
    'whitelabel_dev_skip_cert' => '1',
];
