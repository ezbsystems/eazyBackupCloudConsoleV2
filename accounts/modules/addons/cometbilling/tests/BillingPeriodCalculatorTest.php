<?php
/**
 * Run: php tests/BillingPeriodCalculatorTest.php
 */
require_once __DIR__ . '/../lib/BillingPeriodCalculator.php';

use CometBilling\BillingPeriodCalculator;

function assert_eq($actual, $expected, string $label): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "OK {$label}\n";
}

// Worked example from spec: reg 2026-07-06, revoke 2026-08-01, cycle 30 → end 2026-08-05
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-01', 30, null),
    '2026-08-05',
    'reg mid-cycle expected end'
);

// Must NOT be revoked+30 (that would be 2026-08-31)
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-01', 30, null) === '2026-08-31',
    false,
    'not revoked-plus-cycle'
);

// Boundary: revoke on expected end still in period
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-05', 30, null),
    '2026-08-05',
    'revoke on period end day'
);

// Next period: revoke day after end uses following period
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-06', 30, null),
    '2026-09-05', // next period start 2026-08-06 + 30 days
    'revoke starts next period'
);

// No reg: walk back from next_due. next_due=2026-08-06, cycle=30 → period [2026-07-07, 2026-08-06]
// revoke 2026-06-27 is in the prior period ending 2026-07-06
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd(null, '2026-06-27', 30, '2026-08-06'),
    '2026-07-06',
    'next_due walk-back period containing revoke'
);

// Status: next_due after expected end → overbilled
assert_eq(
    BillingPeriodCalculator::deviceBillingStatus('2026-07-27', '2026-08-06'),
    'overbilled_past_grace',
    'device overbilled'
);
assert_eq(
    BillingPeriodCalculator::deviceBillingStatus('2026-08-06', '2026-08-06'),
    'expected_grace',
    'device still in period'
);
assert_eq(
    BillingPeriodCalculator::deviceBillingStatus(null, '2026-08-06'),
    'unknown',
    'device unknown'
);

// Booster daily
assert_eq(
    BillingPeriodCalculator::boosterBillingStatus('2026-07-06', '2026-07-06'),
    'expected_grace',
    'booster last day still expected'
);
assert_eq(
    BillingPeriodCalculator::boosterBillingStatus('2026-07-06', '2026-07-07'),
    'overbilled_past_grace',
    'booster day after remove overbilled'
);
assert_eq(
    BillingPeriodCalculator::boosterBillingStatus(null, '2026-07-07'),
    'unknown',
    'booster unknown'
);

echo "All BillingPeriodCalculator tests passed.\n";
