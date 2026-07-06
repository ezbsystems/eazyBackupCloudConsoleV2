<?php
declare(strict_types=1);

/**
 * Unit tests for Ms365CustomerError browse/inventory sanitization.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_customer_error_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365CustomerError;

$failures = 0;

function assert_eq(string $expected, string $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message}\n";
        echo "  expected: {$expected}\n";
        echo "  actual:   {$actual}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$browsePermission = 'Browse failed: 2026/07/06 17:35:00 browse: mkdir /tmp/ms365-browse/cache/fe50605166a418ae2d99f77b58f0b871: permission denied';
assert_eq(
    'Unable to browse backup contents. Please contact support if this continues.',
    Ms365CustomerError::message(new \RuntimeException($browsePermission)),
    'production browse cache permission denied maps to contact-support message'
);

$pathNotFound = 'browse: path not found: contoso.onmicrosoft.com/users/a1b2c3d4-e5f6-7890-abcd-ef1234567890/drives/b2c3d4e5-f6a7-8901-bcde-f12345678901/root';
assert_eq(
    'That folder isn\'t available in this snapshot.',
    Ms365CustomerError::message(new \RuntimeException($pathNotFound)),
    'browse path not found with tenant/guid path maps to folder-not-available message'
);

assert_eq(
    'Microsoft 365 is not connected.',
    Ms365CustomerError::message(new \RuntimeException('Microsoft 365 is not connected.')),
    'safe pass-through for not connected message'
);

$longInternal = 'PutObject failed on s3://e3ms365-contoso-backup/discovery/users.json with AWS HTTP error: Client error: 403 Forbidden at /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/lib/Ms365Backup/StorageLayout.php:142 stack trace follows here with more internal details that should never reach the customer area';
assert_eq(
    'Something went wrong. Please try again or contact support.',
    Ms365CustomerError::message(new \RuntimeException($longInternal)),
    'long internal string maps to generic fallback'
);

$inventoryRaw = 'graph 500 Internal Server Error: {"error":{"code":"generalException","message":"An internal error occurred at login.microsoftonline.com/oauth2/v2.0/token"}}';
assert_eq(
    Ms365CustomerError::message(new \RuntimeException($inventoryRaw)),
    Ms365CustomerError::sanitizeRaw($inventoryRaw),
    'sanitizeRaw() matches message() for inventory detail strings'
);

assert_eq(
    'Something went wrong. Please try again or contact support.',
    Ms365CustomerError::sanitizeRaw($inventoryRaw),
    'sanitizeRaw() sanitizes internal inventory detail'
);

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
