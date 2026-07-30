<?php
declare(strict_types=1);

/**
 * Unit tests for empty-list catalog acceptance filtering.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_empty_list_acceptance_filter_test.php
 */

$failures = 0;

function assert_eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message}\n";
        echo '  expected: ' . var_export($expected, true) . "\n";
        echo '  actual:   ' . var_export($actual, true) . "\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

/**
 * Mirrors ms365_shard_completeness_acceptance.php catalog-disabled filter.
 *
 * @param list<array<string, mixed>> $entries
 * @return list<array<string, mixed>>
 */
function filter_catalog_disabled_empty_lists(array $entries): array
{
    return array_values(array_filter(
        $entries,
        static fn (array $e): bool => ($e['selectable'] ?? true) === false
            && str_contains((string) ($e['subtitle'] ?? ''), 'catalog captured'),
    ));
}

$insightLike = [
    ['name' => 'list-a', 'selectable' => false, 'subtitle' => 'Empty list — catalog captured'],
    ['name' => 'list-b', 'subtitle' => ''], // physical list with items
    ['name' => 'list-c', 'selectable' => true, 'subtitle' => 'Empty list — catalog captured'],
    ['name' => 'list-d', 'selectable' => false, 'subtitle' => 'something else'],
];
$disabled = filter_catalog_disabled_empty_lists($insightLike);
assert_eq(1, count($disabled), 'Insight-like browse counts only true catalog-disabled rows');
assert_eq('list-a', $disabled[0]['name'] ?? null, 'keeps the catalog-only list name');

$auditAggregate = [];
for ($i = 0; $i < 27; $i++) {
    $auditAggregate[] = [
        'name' => 'empty-' . $i,
        'selectable' => false,
        'subtitle' => 'Empty list — catalog captured',
    ];
}
$auditAggregate[] = ['name' => 'with-items', 'subtitle' => ''];
$aggDisabled = filter_catalog_disabled_empty_lists($auditAggregate);
assert_eq(27, count($aggDisabled), 'aggregate of 27 catalog-only lists meets acceptance threshold');
assert_eq(true, count($aggDisabled) >= 27, 'acceptance threshold >=27');

if ($failures > 0) {
    echo "\n{$failures} failure(s)\n";
    exit(1);
}
echo "\nAll empty-list acceptance filter tests passed.\n";
