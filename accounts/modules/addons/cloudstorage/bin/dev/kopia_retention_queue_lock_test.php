<?php
declare(strict_types=1);

/**
 * Dev test: KopiaRetentionOperationService enqueue dedupe + KopiaRetentionLockService lock lifecycle.
 * - Enqueue: first => success, second same token => duplicate
 * - Lock: acquire true, second different token false, renew true, release true, acquire other token true
 */

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionOperationService.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionLockService.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionLockService;

$allOk = true;

// --- Enqueue dedupe ---
$token = 'test-token-' . uniqid('', true);
$first = KopiaRetentionOperationService::enqueue(1001, 'retention_apply', ['repo_id' => 1001], $token);
$second = KopiaRetentionOperationService::enqueue(1001, 'retention_apply', ['repo_id' => 1001], $token);
$enqueueOk = ($first['status'] === 'success') && ($second['status'] === 'duplicate');
if (!$enqueueOk) {
    echo "ENQUEUE FAIL: first={$first['status']}, second={$second['status']}\n";
    $allOk = false;
}

// --- Lock lifecycle ---
$testRepoId = 999998;
// Best-effort setup cleanup: remove any stale lock row from prior runs
try {
    Capsule::table('s3_kopia_repo_locks')->where('repo_id', $testRepoId)->delete();
} catch (\Throwable $e) {
    // Ignore; table may not exist or DB unavailable in dev
}
$tokenA = 'lock-token-a-' . uniqid('', true);
$tokenB = 'lock-token-b-' . uniqid('', true);

$acquire1 = KopiaRetentionLockService::acquire($testRepoId, $tokenA, 1, 300);
$acquire2 = KopiaRetentionLockService::acquire($testRepoId, $tokenB, 2, 300);
$renew = KopiaRetentionLockService::renew($testRepoId, $tokenA, 300);
$release = KopiaRetentionLockService::release($testRepoId, $tokenA);
$acquire3 = KopiaRetentionLockService::acquire($testRepoId, $tokenB, 2, 300);

$lockOk = $acquire1 && !$acquire2 && $renew && $release && $acquire3;
if (!$lockOk) {
    echo "LOCK FAIL: acquire1=$acquire1, acquire2=" . ($acquire2 ? 'true' : 'false') . ", renew=$renew, release=$release, acquire3=$acquire3\n";
    $allOk = false;
}

KopiaRetentionLockService::release($testRepoId, $tokenB);

echo $allOk ? "PASS\n" : "FAIL\n";
exit($allOk ? 0 : 1);
