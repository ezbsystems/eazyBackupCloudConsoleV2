<?php
declare(strict_types=1);

/**
 * MS365 job timezone resolver tests.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_job_timezone_resolver_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365JobTimezoneResolver;

$failures = 0;

function assert_eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message} (expected {$expected}, got {$actual})\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

assert_eq(
    'America/Vancouver',
    Ms365JobTimezoneResolver::resolveForClient(1, 'America/Vancouver'),
    'requested timezone is honored on create',
);

assert_eq(
    Ms365JobTimezoneResolver::PLATFORM_DEFAULT,
    Ms365JobTimezoneResolver::resolveForClient(1, 'Not/A/Timezone'),
    'invalid requested timezone falls back to platform default',
);

$job = (object) ['timezone' => 'America/Edmonton'];
assert_eq(
    'America/Edmonton',
    Ms365JobTimezoneResolver::resolveForUpdate(1, $job, null),
    'update preserves existing timezone when none posted',
);

assert_eq(
    'America/Winnipeg',
    Ms365JobTimezoneResolver::resolveForUpdate(1, $job, 'America/Winnipeg'),
    'update accepts explicit new timezone',
);

exit($failures > 0 ? 1 : 0);
