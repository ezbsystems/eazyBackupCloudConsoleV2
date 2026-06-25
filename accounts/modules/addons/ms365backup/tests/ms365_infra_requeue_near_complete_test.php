<?php
declare(strict_types=1);

/**
 * Near-complete runs must not hit the infra-requeue stall cap from platform churn.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_infra_requeue_near_complete_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\WorkerClaimService;

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

$ref = new ReflectionClass(WorkerClaimService::class);
$isNear = $ref->getMethod('isNearCompleteRun');
$isNear->setAccessible(true);
$shouldFail = $ref->getMethod('shouldFailInfrastructureStalledRun');
$shouldFail->setAccessible(true);

assert_true(
    (bool) $isNear->invoke(null, ['items_done' => 6126, 'items_total' => 6127]),
    '6126/6127 is near-complete'
);
assert_true(
    !(bool) $isNear->invoke(null, ['items_done' => 100, 'items_total' => 6127]),
    '100/6127 is not near-complete'
);

$runId = 'cbc2e842-7d22-7706-614d-e7ff0e825a15';
$wouldFail = (bool) $shouldFail->invoke(null, $runId);
if ($wouldFail) {
    $run = \Ms365Backup\BackupRunRepository::get($runId);
    $near = $run !== null
        && (int) ($run['items_total'] ?? 0) > 0
        && (int) ($run['items_done'] ?? 0) >= (int) ($run['items_total'] ?? 0) - 1;
    assert_true(!$near, 'shouldFailInfrastructureStalledRun false when fixture is near-complete');
} else {
    assert_true(true, 'shouldFailInfrastructureStalledRun returned false for fixture run');
}

exit($failures > 0 ? 1 : 0);
