<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_comet_selection_import_service_test.php
 */

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Comet\CometSelectionImportService;

$failures = 0;
function assert_true(bool $c, string $m): void
{
    global $failures;
    if (!$c) {
        echo "FAIL: $m\n";
        ++$failures;
        return;
    }
    echo "OK: $m\n";
}

assert_true(CometSelectionImportService::unmatchedPct(0, 100) === 0.0, '0% unmatched');
assert_true(abs(CometSelectionImportService::unmatchedPct(25, 100) - 25.0) < 0.001, '25% unmatched');
assert_true(CometSelectionImportService::unmatchedPct(5, 0) === 0.0, 'empty total → 0%');

exit($failures > 0 ? 1 : 0);
