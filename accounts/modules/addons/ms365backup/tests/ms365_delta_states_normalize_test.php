<?php
declare(strict_types=1);

/**
 * Ensures legacy/corrupt delta_states shapes never reach the Go worker claim payload.
 * Production failure: {"mail":[]} and [] broke ClaimBatch decode → orphaned batch → progress-stale terminal fail.
 */

require_once dirname(__DIR__) . '/lib/Ms365Backup/DeltaStateRepository.php';

use Ms365Backup\DeltaStateRepository;

function assert_true(bool $cond, string $message): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "OK: $message\n";
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $message\n expected=" . json_encode($expected) . "\n actual=" . json_encode($actual) . "\n");
        exit(1);
    }
    echo "OK: $message\n";
}

// Nested empty list (the Deetken claim-breaker)
$normalized = DeltaStateRepository::normalizeStatesForWorker(['mail' => []]);
assert_same([], $normalized, 'nested empty list mail=>[] drops to empty map');

// Outer JSON array []
$normalized = DeltaStateRepository::normalizeStatesForWorker([]);
assert_same([], $normalized, 'outer empty list normalizes to empty');

// List-shaped outer with junk
$normalized = DeltaStateRepository::normalizeStatesForWorker(['https://graph/delta/a', 'https://graph/delta/b']);
assert_same([], $normalized, 'numeric list outer is rejected');

// Valid nested map preserved
$good = [
    'mail' => ['inbox' => 'https://graph/delta/inbox', 'sentitems' => 'https://graph/delta/sent'],
    'onedrive' => ['root' => 'https://graph/delta/od'],
];
assert_same($good, DeltaStateRepository::normalizeStatesForWorker($good), 'valid nested maps preserved');

// Mixed: keep good workload, drop list-shaped; coerce numeric link values to string
$mixed = [
    'mail' => [],
    'calendar' => ['default' => 'https://graph/delta/cal'],
    'contacts' => ['main' => 123, 'other' => 'https://graph/delta/c', 'bad' => ['nested']],
];
$normalized = DeltaStateRepository::normalizeStatesForWorker($mixed);
assert_same(
    [
        'calendar' => ['default' => 'https://graph/delta/cal'],
        'contacts' => ['main' => '123', 'other' => 'https://graph/delta/c'],
    ],
    $normalized,
    'mixed corrupt workloads sanitized'
);

// encodeForWorkerPayload: empty → stdClass for JSON {}
$encoded = DeltaStateRepository::encodeForWorkerPayload(['mail' => []]);
assert_true($encoded instanceof stdClass, 'empty after normalize encodes as stdClass');
assert_same('{}', json_encode($encoded), 'stdClass json_encodes to {}');

$encodedGood = DeltaStateRepository::encodeForWorkerPayload($good);
assert_true(is_array($encodedGood), 'non-empty normalize stays array');
assert_true(strpos(json_encode($encodedGood), '[]') === false, 'encoded payload contains no JSON arrays');

echo "ALL PASSED\n";
