<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_admin_jobs_waiting_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365AdminJobsRepository;

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

$runningClaim = Ms365AdminJobsRepository::displayStatusForClaim('running', [
    'status' => 'running',
    'attempts' => 1,
    'tenant_record_id' => 6,
]);
assert_true($runningClaim['status'] === 'running', 'active claim keeps parent running');
assert_true($runningClaim['wait_reason'] === null, 'active claim has no wait_reason');

$queuedNoTenant = Ms365AdminJobsRepository::displayStatusForClaim('running', [
    'status' => 'queued',
    'attempts' => 0,
    'tenant_record_id' => 0,
]);
assert_true($queuedNoTenant['status'] === 'queued', 'queued claim surfaces as queued');
assert_true(is_string($queuedNoTenant['wait_reason']) && $queuedNoTenant['wait_reason'] !== '', 'queued claim has wait_reason');

$failedParent = Ms365AdminJobsRepository::displayStatusForClaim('failed', [
    'status' => 'queued',
    'attempts' => 0,
    'tenant_record_id' => 6,
]);
assert_true($failedParent['status'] === 'failed', 'terminal parent status is not overridden');

// Live case: 5c9ed0ec blocked by tenant 6 running claim when present.
$queuedTenant = Ms365AdminJobsRepository::displayStatusForClaim('running', [
    'status' => 'queued',
    'attempts' => 0,
    'tenant_record_id' => 6,
]);
assert_true($queuedTenant['status'] === 'queued', 'tenant queued claim displays queued');
assert_true(
    str_contains((string) $queuedTenant['wait_reason'], 'Waiting'),
    'tenant queued claim wait_reason mentions Waiting'
);

exit($failures > 0 ? 1 : 0);
