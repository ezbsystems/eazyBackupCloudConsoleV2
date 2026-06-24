<?php
declare(strict_types=1);

/**
 * GraphTenantBudgetService shrink/decay tuning — unit checks.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_graph_budget_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\GraphTenantBudgetService;
use Ms365Backup\Ms365EngineConfig;

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

$budgetClass = new ReflectionClass(GraphTenantBudgetService::class);

$decayWindow = $budgetClass->getConstant('DECAY_WINDOW_SECONDS');
assert_true($decayWindow === 600, 'Decay window is 600s (slow recovery)');

$minBudget = $budgetClass->getMethod('minBudget');
$minBudget->setAccessible(true);
assert_true($minBudget->invoke(null, 16) === 4, 'minBudget floor is max/4 for default max=16');
assert_true($minBudget->invoke(null, 16, 10) === 2, 'minBudget drops to 2 under sustained recent_429_count');
assert_true($minBudget->invoke(null, 16, 20) === 1, 'minBudget drops to 1 under heavy recent_429_count');

$shrinkStep = $budgetClass->getMethod('shrinkStep');
$shrinkStep->setAccessible(true);
$floor = $minBudget->invoke(null, 16);
$floorHard = $minBudget->invoke(null, 16, 20);
$shrinkLarge = (int) $shrinkStep->invoke(null, 16, 5, $floor);
assert_true($shrinkLarge >= 4, 'Large delta429 shrinks budget aggressively');
$shrinkSmall = (int) $shrinkStep->invoke(null, 8, 1, $floor);
assert_true($shrinkSmall >= 2, 'Single 429 still shrinks by at least 2');
$shrinkAtFloor = (int) $shrinkStep->invoke(null, 2, 5, $floorHard);
assert_true($shrinkAtFloor <= 1, 'Hard-throttle floor allows budget to reach 1');

$growStep = $budgetClass->getMethod('growStep');
$growStep->setAccessible(true);
assert_true((int) $growStep->invoke(null, 16, 8) === 1, 'Grow step is additive +1 per decay window');
assert_true((int) $growStep->invoke(null, 16, 16) === 0, 'Grow step is zero at max budget');

assert_true(Ms365EngineConfig::perTenantMaxConcurrent() >= 1, 'perTenantMaxConcurrent is positive');

exit($failures > 0 ? 1 : 0);
