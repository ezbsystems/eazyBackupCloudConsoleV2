<?php
declare(strict_types=1);

/**
 * Unit tests for JobQueueRepository::isNonRetryableError mailbox patterns.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_non_retryable_error_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\JobQueueRepository;
use Ms365Backup\Ms365CustomerError;

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

$mailboxError = 'mail: graph 404 Not Found: {"error":{"code":"MailboxNotEnabledForRESTAPI","message":"The mailbox is either inactive, soft-deleted, or is hosted on-premise."}}';

assert_true(
    JobQueueRepository::isNonRetryableError($mailboxError),
    'MailboxNotEnabledForRESTAPI is non-retryable'
);

assert_true(
    JobQueueRepository::isNonRetryableError('graph 401 after token refresh: empty token'),
    'graph 401 after token refresh remains non-retryable'
);

assert_true(
    !JobQueueRepository::isNonRetryableError('graph 404 Not Found: {"error":{"code":"itemNotFound"}}'),
    'generic graph 404 remains retryable'
);

assert_true(
    !JobQueueRepository::isNonRetryableError('graph 503 Service Unavailable'),
    'graph 503 remains retryable'
);

// Regression: run abef5a51 — the To Do (tasks) endpoint returns a long 401
// UnknownError for no-mailbox users. The RAW error must classify as terminal.
$tasks401 = 'tasks: graph 401 Unauthorized: {"error":{"code":"UnknownError","message":"","innerError":{"date":"2026-06-21T11:48:08","request-id":"4740a606-dfbe-4610-865c-2488141c6a1d","client-request-id":"4740a606-dfbe-4610-865c-2488141c6a1d"}}}';
assert_true(
    JobQueueRepository::isNonRetryableError($tasks401),
    'tasks 401 Unauthorized (raw) is non-retryable'
);

// Guard the failure-report path bug: Ms365CustomerError sanitizes this long
// error to the generic message, which must NOT be classified as non-retryable.
// This is why markFailed() must receive the RAW error, not the customer text.
$sanitized = Ms365CustomerError::message(new \RuntimeException($tasks401));
assert_true(
    $sanitized === 'Something went wrong. Please try again or contact support.',
    'long tasks 401 sanitizes to the generic customer message'
);
assert_true(
    !JobQueueRepository::isNonRetryableError($sanitized),
    'sanitized customer message is NOT classifiable (must classify on raw error)'
);

$directory400 = 'directory: graph 400 Bad Request: {"error":{"code":"Request_UnsupportedQuery","message":"Invalid property: lastModifiedDateTime"}}';
assert_true(
    JobQueueRepository::isNonRetryableError($directory400),
    'directory Request_UnsupportedQuery invalid property is non-retryable'
);

$sharepoint403 = 'sharepoint: graph 403 Forbidden: {"error":{"code":"accessDenied","message":"Access denied"}}';
assert_true(
    JobQueueRepository::isNonRetryableError($sharepoint403),
    'SharePoint accessDenied 403 is non-retryable (safety net)'
);

assert_true(
    JobQueueRepository::isNonRetryableError('graph_sync stalled: no enumeration progress for 2700s'),
    'graph_sync enumeration stall is non-retryable'
);

assert_true(
    JobQueueRepository::isNonRetryableError('Workload stalled during Graph sync'),
    'infra graph_sync stall cap message is non-retryable'
);

$directoryPagination = 'directory: Graph pagination loop suspected: 3 consecutive empty page(s) still have @odata.nextLink [directory:users]';
assert_true(
    Ms365CustomerError::message(new \RuntimeException($directoryPagination))
        === 'Directory sync was interrupted while reading users from Microsoft 365. Please retry the backup.',
    'directory pagination loop maps to friendly directory message'
);

$mailPagination = 'mail: Graph pagination loop detected: identical @odata.nextLink URL repeated';
assert_true(
    Ms365CustomerError::message(new \RuntimeException($mailPagination))
        === 'Backup sync was interrupted while reading data from Microsoft 365. Please retry the backup.',
    'non-directory pagination loop maps to generic sync message'
);

$sharepointDupPage = 'sharepoint: Graph pagination loop detected: page contained only previously seen items [sharepoint:b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF25oTPF0fcbOT5NSyo-X6E33]';
assert_true(
    JobQueueRepository::isNonRetryableError($sharepointDupPage),
    'SharePoint duplicate-only pagination loop is non-retryable (stops batch thrash)'
);

assert_true(
    JobQueueRepository::isNonRetryableError($directoryPagination),
    'directory pagination loop is non-retryable'
);

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
