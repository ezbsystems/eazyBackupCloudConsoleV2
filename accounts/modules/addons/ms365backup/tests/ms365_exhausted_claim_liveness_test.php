<?php
declare(strict_types=1);

/**
 * reconcileExhaustedRunningClaims must not kill actively running final-attempt jobs.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_exhausted_claim_liveness_test.php
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
$isActive = $ref->getMethod('isActivelyRunningClaim');
$isActive->setAccessible(true);

$now = time();
$row = (object) [
    'queue_status' => 'running',
    'lease_expires_at' => $now + 600,
    'worker_node_id' => 'node-1',
    'backup_updated_at' => $now,
    'last_progress_at' => $now,
    'phase' => 'graph_sync',
];
$cutoff = $now - 120;

assert_true(
    (bool) $isActive->invoke(null, $row, $now, $cutoff),
    'fresh lease + recent progress counts as actively running'
);

exit($failures > 0 ? 1 : 0);
