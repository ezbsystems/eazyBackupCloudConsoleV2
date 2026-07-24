<?php
declare(strict_types=1);

/**
 * Customer-safe MS365 workload recovery projection.
 *
 * Run: php accounts/modules/addons/cloudstorage/tests/ms365_workload_recovery_projection_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/lib/Client/Ms365BatchLiveService.php';

use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;

$failures = 0;

function projection_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$raw504 = 'calendar: graph 504 Gateway Timeout: {"error":{"code":"UnknownError","request-id":"internal-id"}}';
$format = new ReflectionMethod(Ms365BatchLiveService::class, 'formatCustomerWorkloadError');
$format->setAccessible(true);
$friendly = $format->invoke(null, ['status' => 'queued'], ['error_message' => $raw504]);
projection_assert(
    $friendly === 'Microsoft 365 temporarily timed out. Waiting to retry.',
    'Graph 504 queue error is customer-safe and has no internal Queue prefix',
);

$hasSuppressHelper = method_exists(Ms365BatchLiveService::class, 'shouldSuppressClaimTimeRecovery');
projection_assert($hasSuppressHelper, 'claim-time recovery suppression helper exists');
if ($hasSuppressHelper) {
    $suppress = new ReflectionMethod(Ms365BatchLiveService::class, 'shouldSuppressClaimTimeRecovery');
    $suppress->setAccessible(true);
    projection_assert(
        $suppress->invoke(null, [
            'scheduled_at' => 100,
            'error_message' => 'Worker drain hand-off',
        ], [
            'status' => 'running',
            'claimed_at' => 200,
        ]) === true,
        'claim-time handoff text is historical after a new owner starts',
    );
    projection_assert(
        $suppress->invoke(null, [
            'scheduled_at' => 300,
            'error_message' => $raw504,
        ], [
            'status' => 'running',
            'claimed_at' => 200,
        ]) === false,
        'post-claim Graph timeout remains visible as retry state',
    );
}

exit($failures > 0 ? 1 : 0);
