<?php
declare(strict_types=1);

/**
 * Unit tests for Ms365BatchRetryService eligibility and aggregate partial_success.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_batch_retry_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365BatchRetryService;
use Ms365Backup\Ms365BatchRunRepository;

$failures = 0;

function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

/** @param list<array<string, mixed>> $children */
function child(string $status, string $errorMessage = ''): array
{
    return [
        'status' => $status,
        'error_message' => $errorMessage,
    ];
}

assert_true(
    Ms365BatchRetryService::isEligibleForRetry(
        child('cancelled', Ms365BatchRetryService::CANCEL_NEVER_STARTED_MSG)
    ),
    'Cancelled never-started child is eligible',
);

assert_true(
    Ms365BatchRetryService::isEligibleForRetry(
        child('error', 'sharepoint: context deadline exceeded')
    ),
    'Retryable error child is eligible',
);

assert_true(
    !Ms365BatchRetryService::isEligibleForRetry(
        child('error', 'graph 403 access denied')
    ),
    'Non-retryable graph 403 is ineligible',
);

assert_true(
    !Ms365BatchRetryService::isEligibleForRetry(
        child('cancelled', 'Cancelled by user')
    ),
    'User-cancelled child is ineligible',
);

assert_true(
    !Ms365BatchRetryService::isEligibleForRetry(child('success')),
    'Successful child is ineligible',
);

$mixedTerminal = array_merge(
    array_fill(0, 5, child('success')),
    array_fill(0, 2, child('error', 'timeout')),
);
assert_true(
    Ms365BatchRunRepository::aggregateStatus($mixedTerminal) === 'partial_success',
    'Mixed success+error terminal batch is partial_success',
);

$allFailed = array_fill(0, 3, child('error', 'timeout'));
assert_true(
    Ms365BatchRunRepository::aggregateStatus($allFailed) === 'failed',
    'All-error terminal batch stays failed',
);

assert_true(
    Ms365BatchRetryService::currentRetryRound(['stats_json' => json_encode(['ms365_batch_auto_retry_round' => 2])]) === 2,
    'currentRetryRound reads stats_json',
);

exit($failures > 0 ? 1 : 0);
