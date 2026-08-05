<?php
/**
 * Run: php tests/DisputePackExporterTest.php
 */
namespace WHMCS\Database {
    class Capsule
    {
        /** @var list<object> */
        public static array $deviceRows = [];
        /** @var list<object> */
        public static array $usageRows = [];
        /** @var list<object> */
        public static array $activeRows = [];
        /** @var list<object> */
        public static array $manifestRows = [];

        public static function schema(): object
        {
            return new class {
                public function hasTable(string $table): bool
                {
                    return in_array($table, [
                        'comet_devices',
                        'cb_credit_usage',
                        'cb_active_services',
                        'cb_credit_purchases',
                        'cb_portal_pull_manifests',
                    ], true);
                }

                public function hasColumn(string $table, string $column): bool
                {
                    return $table === 'cb_credit_usage' && $column === 'is_present_in_latest_pull';
                }
            };
        }

        public static function table(string $table): object
        {
            return new class($table) {
                /** @var list<array{0: string, 1: string, 2: mixed}|array{0: string, 1: string, 2: mixed, 3: string}> */
                private array $conditions = [];
                private ?int $offset = null;
                private ?int $limit = null;

                public function __construct(private string $table)
                {
                }

                public function where(mixed $column, mixed $opOrVal = null, mixed $val = null): self
                {
                    if ($column instanceof \Closure) {
                        $nested = new self($this->table);
                        $column($nested);
                        $this->conditions[] = ['__group__', 'group', $nested->conditions, 'and'];
                        return $this;
                    }
                    if ($val === null) {
                        $this->conditions[] = [(string) $column, '=', $opOrVal];
                    } else {
                        $this->conditions[] = [(string) $column, (string) $opOrVal, $val];
                    }
                    return $this;
                }

                public function orWhere(string $column, mixed $opOrVal, mixed $val = null): self
                {
                    if ($val === null) {
                        $this->conditions[] = [$column, '=', $opOrVal, 'or'];
                    } else {
                        $this->conditions[] = [$column, (string) $opOrVal, $val, 'or'];
                    }
                    return $this;
                }

                public function whereBetween(string $column, array $range): self
                {
                    $this->conditions[] = [$column, 'between', $range];
                    return $this;
                }

                public function orderBy(string $column, string $dir = 'asc'): self { return $this; }
                public function offset(int $offset): self { $this->offset = $offset; return $this; }
                public function limit(int $limit): self { $this->limit = $limit; return $this; }
                public function select(mixed $columns = []): self { return $this; }
                public function groupBy(string $column): self { return $this; }
                public function min(string $column): ?string { return '2026-07-01'; }
                public function max(string $column): ?string { return '2026-07-31'; }
                public function pluck(string $column)
                {
                    return new class(['2026-07-07 12:00:00']) {
                        public function __construct(private array $items) {}
                        public function toArray(): array { return $this->items; }
                    };
                }
                public function first(): ?object
                {
                    $rows = $this->get();
                    return $rows[0] ?? null;
                }

                /** @return list<object> */
                public function get(array $columns = []): array
                {
                    $rows = match ($this->table) {
                        'comet_devices' => Capsule::$deviceRows,
                        'cb_credit_usage' => Capsule::$usageRows,
                        'cb_active_services' => Capsule::$activeRows,
                        'cb_portal_pull_manifests' => Capsule::$manifestRows,
                        default => [],
                    };
                    $filtered = array_values(array_filter($rows, function (object $row): bool {
                        foreach ($this->conditions as $condition) {
                            if (($condition[0] ?? '') === '__group__') {
                                if (!$this->matchesGroup($row, $condition[2])) {
                                    return false;
                                }
                                continue;
                            }
                            [$column, $operator, $value] = $condition;
                            if (!$this->matchesCondition($row, $column, $operator, $value)) {
                                return false;
                            }
                        }
                        return true;
                    }));
                    if ($this->offset !== null) {
                        $filtered = array_slice($filtered, $this->offset);
                    }
                    if ($this->limit !== null) {
                        $filtered = array_slice($filtered, 0, $this->limit);
                    }
                    return $filtered;
                }

                private function matchesCondition(object $row, string $column, string $operator, mixed $value): bool
                {
                    $actual = $row->{$column} ?? null;
                    if ($operator === '=' && $actual != $value) {
                        return false;
                    }
                    if ($operator === 'between' && is_array($value)) {
                        if ($actual < $value[0] || $actual > $value[1]) {
                            return false;
                        }
                    }
                    if ($operator === '<' && $actual >= $value) {
                        return false;
                    }
                    if ($operator === '>=' && $actual < $value) {
                        return false;
                    }
                    return true;
                }

                /** @param list<array{0: string, 1: string, 2: mixed, 3?: string}> $groupConditions */
                private function matchesGroup(object $row, array $groupConditions): bool
                {
                    foreach ($groupConditions as [$column, $operator, $value]) {
                        if ($this->matchesCondition($row, $column, $operator, $value)) {
                            return true;
                        }
                    }
                    return false;
                }
            };
        }
    }
}

namespace {
    require_once __DIR__ . '/../lib/BillingPeriodCalculator.php';
    require_once __DIR__ . '/../lib/PackUsageParser.php';
    require_once __DIR__ . '/../lib/ChargeCategoryResolver.php';
    require_once __DIR__ . '/../lib/PortalUsageExtractor.php';
    require_once __DIR__ . '/../lib/ServiceIdentityResolver.php';
    require_once __DIR__ . '/../lib/LifecycleResolver.php';
    require_once __DIR__ . '/../lib/BillingCadenceResolver.php';
    require_once __DIR__ . '/../lib/SourceCoverageReporter.php';
    require_once __DIR__ . '/../lib/OverbillEvidenceEvaluator.php';
    require_once __DIR__ . '/../lib/CanonicalUsage.php';
    require_once __DIR__ . '/../lib/DisputePackExporter.php';

    use CometBilling\CanonicalUsage;
    use CometBilling\DisputePackExporter;
    use CometBilling\OverbillEvidenceEvaluator;
    use CometBilling\PackUsageParser;
    use WHMCS\Database\Capsule;

    function assert_true(bool $cond, string $label): void
    {
        if (!$cond) {
            fwrite(STDERR, "FAIL {$label}\n");
            exit(1);
        }
        echo "OK {$label}\n";
    }

    function assert_eq($a, $b, string $label): void
    {
        if ($a !== $b) {
            fwrite(STDERR, "FAIL {$label}: expected " . var_export($b, true) . ' got ' . var_export($a, true) . "\n");
            exit(1);
        }
        echo "OK {$label}\n";
    }

    Capsule::$deviceRows = [
        (object) [
            'hash' => 'dailyhyperv12345',
            'username' => 'DailyCorp',
            'name' => 'Hyper-V Host',
            'revoked_at' => '2026-07-06 08:00:00',
            'content' => '{}',
        ],
    ];
    Capsule::$activeRows = [
        (object) [
            'id' => 1,
            'pulled_at' => '2026-07-07 12:00:00',
            'service_name' => 'Account DailyCorp - Device dailyhy - Booster Hyper-V',
            'billing_cycle_days' => 1,
            'next_due_date' => '2026-07-07',
            'device_id' => 'dailyh',
            'quantity' => '2.0000',
            'amount' => '2.5000',
            'unit_cost' => '3.0000',
        ],
    ];
    Capsule::$manifestRows = [(object) ['id' => 1]];
    Capsule::$usageRows = [
        (object) [
            'id' => 1,
            'usage_date' => '2026-07-07',
            'tenant_id' => 'DailyCorp',
            'device_id' => 'dailyhyperv12345',
            'item_type' => 'booster',
            'item_desc' => 'Booster - Hyper-V Guest Count',
            'amount' => '2.50',
            'packs_used_raw' => '10,000 Dollars',
            'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
            'raw_row' => json_encode([
                'Item' => 'Booster - Hyper-V Guest Count',
                'Packs Used' => '10,000 Dollars',
                'Amount Used' => '$2.50',
                'Account Name' => 'DailyCorp',
                'Device ID' => 'dailyhyperv12345',
                'Date Added' => '07 Jul 2026',
            ]),
            'is_present_in_latest_pull' => 1,
            'occurrence_number' => 1,
        ],
        (object) [
            'id' => 2,
            'usage_date' => '2026-07-06',
            'tenant_id' => 'DailyCorp',
            'device_id' => 'dailyhyperv12345',
            'item_type' => 'booster',
            'item_desc' => 'Booster - Hyper-V Guest Count',
            'amount' => '2.50',
            'packs_used_raw' => '10,000 Dollars',
            'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
            'raw_row' => json_encode([]),
            'is_present_in_latest_pull' => 1,
            'occurrence_number' => 1,
        ],
    ];

    CanonicalUsage::clearCache();
    \CometBilling\ServiceIdentityResolver::clearCache();
    \CometBilling\BillingCadenceResolver::clearCache();
    \CometBilling\LifecycleResolver::clearCache();

    $finding = OverbillEvidenceEvaluator::evaluate(Capsule::$usageRows[0], false);
    assert_eq($finding['verdict'], 'confirmed', 'fixture is confirmed');
    $pack = DisputePackExporter::packFromFinding($finding);
    assert_true(str_starts_with($pack['claim'], '2026-07-07 Comet debited'), 'claim starts with debit date');
    assert_true(str_contains($pack['claim'], '$2.50'), 'claim includes amount');
    assert_true(str_contains($pack['claim'], '2026-07-06'), 'claim includes revoke date');
    assert_true(!str_contains($pack['claim'], 'on account '), 'claim omits account clause');
    assert_eq($pack['packs_used'], '10,000 Dollars', 'packs used captured');
    assert_true(str_contains($pack['pack_debited'], '10,000 Dollars'), 'pack debited label present');
    assert_eq($pack['reversal_status'], 'none_found', 'no reversal');
    assert_true(str_contains($pack['evidence_1_comet_debit'], 'debit date=2026-07-07'), 'evidence 1 debit date');
    assert_true(str_contains($pack['evidence_2_amount_pack'], 'amount=$2.50'), 'evidence 2 amount');
    assert_true(str_contains($pack['evidence_2_amount_pack'], 'pack_debited='), 'evidence 2 pack');
    assert_true(str_contains($pack['evidence_3_revocation'], '2026-07-06'), 'evidence 3 revoke');
    assert_true(str_contains($pack['evidence_5_after_expected_end'], 'Debit date'), 'evidence 5 uses debit date');
    assert_true(str_contains($pack['evidence_6_no_reversal'], 'No offsetting'), 'evidence 6 reversal');
    assert_true(str_contains($pack['active_service_evidence'], 'Booster Hyper-V'), 'pack includes Active Services evidence');

    $duplicate = $pack;
    $duplicate['usage_id'] = 3;
    $nextDay = $pack;
    $nextDay['usage_id'] = 4;
    $nextDay['debit_date'] = '2026-07-08';
    $nextDay['usage_date'] = '2026-07-08';
    $cases = DisputePackExporter::groupForDispute([$pack, $duplicate, $nextDay]);
    assert_eq(count($cases), 1, 'daily findings grouped into one device case');
    assert_eq($cases[0]['distinct_debit_dates'], 2, 'daily case counts distinct dates');
    assert_eq($cases[0]['occurrence_count'], 3, 'daily case counts all API occurrences');
    assert_eq($cases[0]['duplicate_pending_count'], 1, 'second identical occurrence is pending');
    assert_eq($cases[0]['confirmed_amount'], '5.00', 'conservative total counts one debit per day');
    assert_eq($cases[0]['duplicate_pending_amount'], '2.50', 'duplicate amount shown separately');
    assert_true(str_contains($cases[0]['claim'], 'Active Services report still listed'), 'daily claim includes Active Services');
    assert_true(str_contains($cases[0]['claim'], '2 dates from 2026-07-07 through 2026-07-08'), 'daily claim includes debit timeline');
    assert_eq($cases[0]['debit_dates'][0]['occurrences'][1]['status'], 'duplicate debit pending Comet confirmation', 'duplicate occurrence labeled pending');

    $csv = DisputePackExporter::buildCsv('2026-07-01', '2026-07-31');
    assert_true(str_contains($csv, 'claim'), 'csv has claim header');
    assert_true(str_contains($csv, 'active_service_evidence'), 'csv has Active Services evidence');
    assert_true(str_contains($csv, 'duplicate_pending_amount'), 'csv has pending duplicate amount');
    assert_true(str_contains($csv, 'occurrence_statuses'), 'csv has occurrence statuses');
    assert_true(str_contains($csv, 'DailyCorp'), 'csv includes confirmed account');
    assert_true(!str_contains($csv, 'identity_status'), 'csv omits identity columns');
    assert_true(!str_contains($csv, 'usage_id'), 'csv omits usage_id');
    $lines = array_values(array_filter(explode("\n", trim($csv))));
    assert_eq(count($lines), 2, 'csv has header + one confirmed row');

    $html = DisputePackExporter::buildHtml('2026-07-01', '2026-07-31');
    assert_true(str_contains($html, 'Print / Save as PDF'), 'html has print control');
    assert_true(str_contains($html, 'Comet Overbilling</h1>'), 'html uses revised heading');
    assert_true(!str_contains($html, 'Comet Overbilling Dispute Pack</h1>'), 'old heading removed');
    assert_true(!str_contains($html, 'Evidence is assembled from'), 'introductory evidence disclaimer removed');
    assert_true(str_contains($html, 'Comet Active Services'), 'html includes Comet Active Services');
    assert_true(str_contains($html, 'Overcharged amount'), 'html shows overcharged amount');
    assert_true(!str_contains($html, 'Conservative confirmed amount'), 'html no longer says Conservative confirmed amount');
    assert_true(!preg_match('/— confirmed \$/', $html), 'case heading omits confirmed word');
    assert_true(str_contains($html, 'Potential duplicate amount'), 'html shows pending duplicate amount');
    assert_true(!str_contains($html, 'Usage ID'), 'html omits usage id');
    assert_true(!str_contains($html, 'Identity'), 'html omits identity');
    assert_true(!str_contains($html, '<strong>1. '), 'html does not double-number');

    echo "\nAll DisputePackExporter tests passed.\n";
}
