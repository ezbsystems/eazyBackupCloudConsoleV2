<?php
/**
 * Run: php tests/PackUsageParserTest.php
 */
namespace {
    require_once __DIR__ . '/../lib/PackUsageParser.php';

    use CometBilling\PackUsageParser;

    function assert_true(bool $cond, string $label): void
    {
        if (!$cond) {
            fwrite(STDERR, "FAIL {$label}\n");
            exit(1);
        }
        echo "OK {$label}\n";
    }

    $simple = PackUsageParser::parse('10,000 Dollars');
    assert_true($simple['parsed_ok'], 'simple pack parses');
    assert_true($simple['primary_denomination'] === 10000, 'denomination 10000');

    $split = PackUsageParser::parse('10,000 Dollars (- $1.29 ) 10,000 Dollars (- $0.71 )');
    assert_true(count($split['entries']) === 2, 'split pack two entries');
    assert_true($split['entries'][0]['debit_amount'] === 1.29, 'first debit amount');

    assert_true(PackUsageParser::hasDebitEvidence('10,000 Dollars', 0.50), 'debit evidence with amount');

    echo "\nAll PackUsageParser tests passed.\n";
}
