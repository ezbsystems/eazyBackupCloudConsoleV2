<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Admin/S3Billing.php';

use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;

function assertFloatEq(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.001) {
        fwrite(STDERR, "FAIL {$label}: expected {$expected}, got {$actual}\n");
        exit(1);
    }
}

$billing = new S3Billing();
$ref = new ReflectionClass($billing);
$method = $ref->getMethod('computeAmountForBytes');
$method->setAccessible(true);

$baseFee = 9.00;
$rate = 0.008789;
$oneTib = 1099511627776;
$twoTib = $oneTib * 2;

assertFloatEq(0.00, (float) $method->invoke($billing, 0, $baseFee, $rate), '0 bytes -> 0');
assertFloatEq(9.00, (float) $method->invoke($billing, 1, $baseFee, $rate), '1 byte -> base fee');
assertFloatEq(9.00, (float) $method->invoke($billing, $oneTib, $baseFee, $rate), '1 TiB -> base fee');

$expectedTwoTib = ceil(($baseFee + (2048 - 1024) * $rate) * 100) / 100;
assertFloatEq($expectedTwoTib, (float) $method->invoke($billing, $twoTib, $baseFee, $rate), '2 TiB -> base + overage');

// Parity guard: representative values should match SQL CASE logic in recomputeInWindowPrices.
$samples = [0, 1, 1024, 1073741824, $oneTib, $oneTib + 1, $twoTib];
foreach ($samples as $bytes) {
    $php = (float) $method->invoke($billing, $bytes, $baseFee, $rate);
    $sqlEquivalent = sqlCaseEquivalent((int) $bytes, $baseFee, $rate);
    assertFloatEq($sqlEquivalent, $php, "parity bytes={$bytes}");
}

echo "s3billing_usage_gated_test: OK\n";

/**
 * Mirror recomputeInWindowPrices() CASE (including usage_bytes = 0 branch).
 */
function sqlCaseEquivalent(int $bytes, float $baseFee, float $rate): float
{
    if ($bytes === 0) {
        return 0.00;
    }
    if ($bytes <= 1099511627776) {
        $amount = $baseFee;
    } else {
        $gib = $bytes / 1073741824.0;
        $amount = $baseFee + ($gib - 1024) * $rate;
    }
    return ceil($amount * 100) / 100;
}
