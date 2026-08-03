<?php
declare(strict_types=1);

/**
 * MS365 restore snapshot display metadata for the e3 Restore tab.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_restore_snapshot_display_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365RestoreSnapshotService;

$failures = 0;

function assert_eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ")\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$withLabel = Ms365RestoreSnapshotService::displayMetaForTenant('Contoso Ltd');
assert_eq('Contoso Ltd', $withLabel['source_display_name'], 'source uses tenant label');
assert_eq('ms365', $withLabel['source_type'], 'source_type is ms365');
assert_eq('Cloud', $withLabel['agent_hostname'], 'agent is Cloud');
assert_eq('e3', $withLabel['dest_type'], 'dest_type is e3');

$empty = Ms365RestoreSnapshotService::displayMetaForTenant('');
assert_eq('Microsoft 365', $empty['source_display_name'], 'empty label falls back to Microsoft 365');

$nullMeta = Ms365RestoreSnapshotService::displayMetaForTenant(null);
assert_eq('Microsoft 365', $nullMeta['source_display_name'], 'null label falls back to Microsoft 365');

$whitespace = Ms365RestoreSnapshotService::displayMetaForTenant("  \t  ");
assert_eq('Microsoft 365', $whitespace['source_display_name'], 'whitespace-only label falls back');

if ($failures > 0) {
    echo "\n{$failures} failure(s)\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
