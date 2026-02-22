<?php

declare(strict_types=1);

/**
 * Dev test: AnnualCycleWindow::fromNextDueDate() behavior.
 * Run: php bin/dev/annual_cycle_window_test.php
 */

require __DIR__ . '/../bootstrap.php';

use EazyBackup\Billing\AnnualCycleWindow;

$ok = true;

// Approved plan: fromNextDueDate('2026-12-15') -> cycle_end='2026-12-15', cycle_start='2025-12-16'
$got = AnnualCycleWindow::fromNextDueDate('2026-12-15');
if ($got['cycle_end'] !== '2026-12-15' || $got['cycle_start'] !== '2025-12-16') {
    echo "FAIL: fromNextDueDate('2026-12-15') expected cycle_end=2026-12-15 cycle_start=2025-12-16, got " . json_encode($got) . "\n";
    $ok = false;
}

// Boundary: Jan 1
$got = AnnualCycleWindow::fromNextDueDate('2025-01-01');
if ($got['cycle_end'] !== '2025-01-01' || $got['cycle_start'] !== '2024-01-02') {
    echo "FAIL: fromNextDueDate('2025-01-01') expected cycle_end=2025-01-01 cycle_start=2024-01-02, got " . json_encode($got) . "\n";
    $ok = false;
}

// Leap-day: 2024-02-29 -> cycle_end=2024-02-29, cycle_start=2023-03-02
$got = AnnualCycleWindow::fromNextDueDate('2024-02-29');
if ($got['cycle_end'] !== '2024-02-29' || $got['cycle_start'] !== '2023-03-02') {
    echo "FAIL: fromNextDueDate('2024-02-29') expected cycle_end=2024-02-29 cycle_start=2023-03-02, got " . json_encode($got) . "\n";
    $ok = false;
}

// Invalid input: expect InvalidArgumentException
$invalidInputs = ['2026-12-32', '2026-02-30', 'not-a-date', '', '2026/12/15'];
foreach ($invalidInputs as $input) {
    try {
        AnnualCycleWindow::fromNextDueDate($input);
        echo "FAIL: fromNextDueDate('$input') expected InvalidArgumentException, got no exception\n";
        $ok = false;
    } catch (InvalidArgumentException $e) {
        // expected
    }
}

if ($ok) {
    echo "PASS\n";
    exit(0);
}

exit(1);
