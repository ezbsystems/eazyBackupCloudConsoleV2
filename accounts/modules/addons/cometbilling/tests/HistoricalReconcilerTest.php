<?php
/**
 * Run: php tests/HistoricalReconcilerTest.php
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
                        'cb_audit_runs',
                        'cb_audit_findings',
                        'cb_portal_pull_manifests',
                    ], true);
                }

                public function hasColumn(string $table, string $column): bool
                {
                    if ($table === 'cb_credit_purchases' && $column === 'record_type') {
                        return true;
                    }
                    return $table === 'cb_credit_usage' && $column === 'is_present_in_latest_pull';
                }
            };
        }

        public static function table(string $table): object
        {
            return new class($table) {
                /** @var list<array{0: string, 1: string, 2: mixed}> */
                private array $conditions = [];
                private ?int $offset = null;
                private ?int $limit = null;
                private ?string $orderColumn = null;
                private ?string $orderDir = null;
                private ?int $insertId = 1000;

                public function __construct(private string $table)
                {
                }

                public function whereNotNull(string $column): self { return $this; }
                public function where(string|\Closure $column, mixed $opOrVal = null, mixed $val = null): self
                {
                    if ($column instanceof \Closure) {
                        $nested = new class {
                            /** @var list<array{0: string, 1: string, 2: mixed, 3: string}> */
                            private array $conditions = [];

                            public function where(string $col, mixed $opOrVal = null, mixed $val = null): self
                            {
                                if ($val === null) {
                                    $this->conditions[] = [$col, '=', $opOrVal, 'and'];
                                } else {
                                    $this->conditions[] = [$col, (string) $opOrVal, $val, 'and'];
                                }
                                return $this;
                            }

                            public function orWhere(string $col, mixed $opOrVal = null, mixed $val = null): self
                            {
                                if ($val === null) {
                                    $this->conditions[] = [$col, '=', $opOrVal, 'or'];
                                } else {
                                    $this->conditions[] = [$col, (string) $opOrVal, $val, 'or'];
                                }
                                return $this;
                            }

                            /** @return list<array{0: string, 1: string, 2: mixed, 3: string}> */
                            public function getConditions(): array
                            {
                                return $this->conditions;
                            }
                        };
                        $column($nested);
                        $this->conditions[] = ['__group__', 'group', $nested->getConditions()];
                        return $this;
                    }
                    if ($val === null) {
                        $this->conditions[] = [$column, '=', $opOrVal];
                    } elseif (strtolower((string) $opOrVal) === 'like') {
                        $this->conditions[] = [$column, 'like', $val];
                    } else {
                        $this->conditions[] = [$column, (string) $opOrVal, $val];
                    }
                    return $this;
                }
                public function orWhere(string $column, mixed $opOrVal = null, mixed $val = null): self
                {
                    if ($val === null) {
                        $this->conditions[] = [$column, '=', $opOrVal, 'or'];
                    } elseif (strtolower((string) $opOrVal) === 'like') {
                        $this->conditions[] = [$column, 'like', $val, 'or'];
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
                public function whereDate(string $column, string $date): self { return $this; }
                public function whereIn(string $column, array $vals): self { return $this; }
                public function orderBy(string $column, string $dir = 'asc'): self
                {
                    $this->orderColumn = $column;
                    $this->orderDir = $dir;
                    return $this;
                }
                public function offset(int $offset): self { $this->offset = $offset; return $this; }
                public function limit(int $limit): self { $this->limit = $limit; return $this; }
                public function select(mixed $columns = []): self { return $this; }
                public function groupBy(string $column): self { return $this; }
                public function min(string $column): ?string
                {
                    if ($this->table === 'cb_credit_usage') {
                        return '2026-07-01';
                    }
                    if ($this->table === 'cb_credit_purchases') {
                        return '2025-01-01 00:00:00';
                    }
                    return '2026-07-01';
                }
                public function max(string $column): ?string
                {
                    if ($this->table === 'cb_credit_usage') {
                        return '2026-07-31';
                    }
                    return '2026-07-31';
                }
                public function pluck(string $column)
                {
                    if ($this->table === 'cb_active_services' && $this->conditions === []) {
                        $arr = ['2026-07-07 12:00:00'];
                    } else {
                        $arr = array_map(
                            fn (object $row) => $row->{$column} ?? null,
                            $this->get()
                        );
                    }
                    return new class($arr) {
                        public function __construct(private array $items) {}
                        public function toArray(): array { return $this->items; }
                    };
                }
                public function sum(mixed $column): float { return 0.0; }
                public function count(): int { return 0; }
                public function insertGetId(array $data): int
                {
                    return $this->insertId++;
                }
                public function insert(array $data): bool { return true; }
                public function update(array $data): int { return 0; }
                public function delete(): int { return 0; }
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
                            if (count($condition) === 4 && ($condition[3] ?? null) === 'or') {
                                [$column, $operator, $value] = $condition;
                                if ($this->matchesCondition($row, $column, $operator, $value)) {
                                    continue;
                                }
                                return false;
                            }
                            if (($condition[1] ?? null) === 'group') {
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
                    if ($this->orderColumn !== null) {
                        usort($filtered, function (object $a, object $b): int {
                            $av = $a->{$this->orderColumn} ?? null;
                            $bv = $b->{$this->orderColumn} ?? null;
                            $cmp = $av <=> $bv;
                            return $this->orderDir === 'desc' ? -$cmp : $cmp;
                        });
                    }
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
                    if ($operator === 'like' && is_string($value)) {
                        $pattern = str_replace('%', '', $value);
                        if (!str_contains((string) $actual, $pattern)) {
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

                /** @param list<array{0: string, 1: string, 2: mixed, 3: string}> $groupConditions */
                private function matchesGroup(object $row, array $groupConditions): bool
                {
                    $hasOr = false;
                    foreach ($groupConditions as $groupCondition) {
                        if (($groupCondition[3] ?? 'and') === 'or') {
                            $hasOr = true;
                            break;
                        }
                    }
                    if ($hasOr) {
                        foreach ($groupConditions as [$column, $operator, $value]) {
                            if ($this->matchesCondition($row, $column, $operator, $value)) {
                                return true;
                            }
                        }
                        return false;
                    }
                    foreach ($groupConditions as [$column, $operator, $value]) {
                        if (!$this->matchesCondition($row, $column, $operator, $value)) {
                            return false;
                        }
                    }
                    return true;
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
    require_once __DIR__ . '/../lib/HistoricalReconciler.php';
    require_once __DIR__ . '/../lib/CanonicalUsage.php';

    use CometBilling\CanonicalUsage;
    use CometBilling\HistoricalReconciler;
    use CometBilling\OverbillEvidenceEvaluator;
    use CometBilling\PackUsageParser;
    use WHMCS\Database\Capsule;

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
        ],
    ];

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
            'raw_row' => json_encode(['Item' => 'Booster - Hyper-V', 'Packs Used' => '10,000 Dollars']),
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
        ],
    ];

    $over = OverbillEvidenceEvaluator::evaluate(Capsule::$usageRows[0], false);
    assert_eq($over['billing_verdict'], 'after_expected_end', 'hyper-v day after revoke is after expected end');
    assert_eq($over['debit_evidence'], 'present', 'pack debit evidence present');
    assert_eq(in_array($over['verdict'], ['probable', 'review_required', 'confirmed'], true), true, 'post-period charge flagged');

    $grace = OverbillEvidenceEvaluator::evaluate(Capsule::$usageRows[1], false);
    assert_eq($grace['verdict'], 'not_overbilled', 'revoke day charge not overbilled');

    $report = HistoricalReconciler::report('2026-07-01', '2026-07-31', false, false);
    assert_eq($report['summary']['charges_scanned'], 2, 'scans all usage rows');

    Capsule::$manifestRows = [
        (object) ['id' => 1, 'pulled_at' => '2026-08-01 12:00:00'],
    ];
    Capsule::$usageRows = [
        (object) [
            'id' => 10,
            'occurrence_number' => 1,
            'is_present_in_latest_pull' => 1,
            'usage_date' => '2026-07-07',
            'tenant_id' => 'DailyCorp',
            'device_id' => 'dailyhyperv12345',
        ],
        (object) [
            'id' => 11,
            'occurrence_number' => 2,
            'is_present_in_latest_pull' => 1,
            'usage_date' => '2026-07-07',
            'tenant_id' => 'DailyCorp',
            'device_id' => 'dailyhyperv12345',
        ],
        (object) [
            'id' => 12,
            'occurrence_number' => 1,
            'is_present_in_latest_pull' => 0,
            'usage_date' => '2026-07-06',
            'tenant_id' => 'DailyCorp',
            'device_id' => 'dailyhyperv12345',
        ],
    ];
    CanonicalUsage::clearCache();
    assert_eq(CanonicalUsage::hasCanonicalPull(), true, 'hasCanonicalPull true when manifest exists');
    $canonicalRows = CanonicalUsage::query()->get();
    assert_eq(count($canonicalRows), 2, 'canonical query excludes stale rows');
    $occurrences = array_map(static fn (object $row): int => (int) $row->occurrence_number, $canonicalRows);
    sort($occurrences);
    assert_eq($occurrences, [1, 2], 'only current occurrences remain');

    Capsule::$manifestRows = [];
    CanonicalUsage::clearCache();
    assert_eq(CanonicalUsage::hasCanonicalPull(), false, 'hasCanonicalPull false when manifest empty');

    echo "\nAll HistoricalReconciler audit tests passed.\n";
}
