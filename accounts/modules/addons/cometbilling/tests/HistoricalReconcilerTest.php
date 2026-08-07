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
        /** @var list<object> */
        public static array $inventoryRows = [];
        /** @var list<object> */
        public static array $auditRuns = [];
        /** @var list<object> */
        public static array $auditFindings = [];

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
                        'cb_server_device_inventory',
                        'cb_audit_runs',
                        'cb_audit_findings',
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
                public function whereIn(string $column, array $vals): self
                {
                    $this->conditions[] = [$column, 'in', $vals];
                    return $this;
                }
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
                    $arr = array_map(
                        fn (object $row) => $row->{$column} ?? null,
                        $this->get()
                    );
                    $arr = array_values(array_unique(array_filter($arr, static fn ($v) => $v !== null)));
                    return new class($arr) {
                        public function __construct(private array $items) {}
                        public function toArray(): array { return $this->items; }
                    };
                }
                public function sum(mixed $column): float { return 0.0; }
                public function count(): int
                {
                    return count($this->get());
                }
                public function insertGetId(array $data): int
                {
                    $id = $this->insertId++;
                    if ($this->table === 'cb_audit_runs') {
                        $data['id'] = $id;
                        Capsule::$auditRuns[] = (object) $data;
                    }

                    return $id;
                }
                public function insert(array $data): bool
                {
                    if ($this->table === 'cb_audit_findings') {
                        if (isset($data[0]) && is_array($data[0])) {
                            foreach ($data as $row) {
                                $row['id'] = $this->insertId++;
                                Capsule::$auditFindings[] = (object) $row;
                            }
                        } else {
                            $data['id'] = $this->insertId++;
                            Capsule::$auditFindings[] = (object) $data;
                        }
                    }

                    return true;
                }
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
                        'cb_server_device_inventory' => Capsule::$inventoryRows,
                        'cb_audit_runs' => Capsule::$auditRuns,
                        'cb_audit_findings' => Capsule::$auditFindings,
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
                    if ($operator === '>' && $actual <= $value) {
                        return false;
                    }
                    if ($operator === 'in' && is_array($value) && !in_array($actual, $value, true)) {
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
    require_once __DIR__ . '/../lib/ReversalIndex.php';
    require_once __DIR__ . '/../lib/SourceCoverageReporter.php';
    require_once __DIR__ . '/../lib/OverbillEvidenceEvaluator.php';
    require_once __DIR__ . '/../lib/HistoricalReconciler.php';
    require_once __DIR__ . '/../lib/CanonicalUsage.php';

    use CometBilling\CanonicalUsage;
    use CometBilling\HistoricalReconciler;
    use CometBilling\OverbillEvidenceEvaluator;
    use CometBilling\PackUsageParser;
    use CometBilling\BillingCadenceResolver;
    use CometBilling\ReversalIndex;
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
    assert_eq($over['verdict'], 'confirmed', 'complete row evidence confirms despite range coverage gap');

    $grace = OverbillEvidenceEvaluator::evaluate(Capsule::$usageRows[1], false);
    assert_eq($grace['verdict'], 'not_overbilled', 'revoke day charge not overbilled');

    // Evening local revoke → UTC next calendar day still billable (timezone boundary)
    Capsule::$deviceRows = [
        (object) [
            'hash' => 'boundaryeve12345',
            'username' => 'BoundaryCorp',
            'name' => 'Edge Host',
            'revoked_at' => '2026-07-31 20:43:17',
            'content' => '{}',
        ],
    ];
    Capsule::$activeRows = [
        (object) [
            'id' => 2,
            'pulled_at' => '2026-08-01 12:00:00',
            'service_name' => 'Account BoundaryCorp - Device boundar - Booster Hyper-V',
            'billing_cycle_days' => 1,
            'next_due_date' => '2026-08-01',
            'device_id' => 'bounda',
        ],
    ];
    \CometBilling\LifecycleResolver::clearCache();
    \CometBilling\ServiceIdentityResolver::clearCache();
    \CometBilling\BillingCadenceResolver::clearCache();

    $utcRevokeDay = (object) [
        'id' => 100,
        'usage_date' => '2026-08-01',
        'tenant_id' => 'BoundaryCorp',
        'device_id' => 'boundaryeve12345',
        'item_type' => 'booster',
        'item_desc' => 'Booster - Hyper-V Guest Count',
        'amount' => '0.20',
        'packs_used_raw' => '10,000 Dollars',
        'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
        'raw_row' => json_encode([]),
    ];
    $dayAfterUtcRevoke = (object) [
        'id' => 101,
        'usage_date' => '2026-08-02',
        'tenant_id' => 'BoundaryCorp',
        'device_id' => 'boundaryeve12345',
        'item_type' => 'booster',
        'item_desc' => 'Booster - Hyper-V Guest Count',
        'amount' => '0.20',
        'packs_used_raw' => '10,000 Dollars',
        'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
        'raw_row' => json_encode([]),
    ];

    $withinUtcDay = OverbillEvidenceEvaluator::evaluate($utcRevokeDay, false);
    assert_eq($withinUtcDay['expected_billing_end'], '2026-08-01', 'expected end uses UTC revoke date');
    assert_eq($withinUtcDay['billing_verdict'], 'within_period', 'UTC revoke day charge within period');
    assert_eq($withinUtcDay['verdict'], 'not_overbilled', 'UTC revoke day not confirmed overbill');

    $afterUtcDay = OverbillEvidenceEvaluator::evaluate($dayAfterUtcRevoke, false);
    assert_eq($afterUtcDay['billing_verdict'], 'after_expected_end', 'day after UTC revoke still after expected end');
    assert_eq($afterUtcDay['verdict'], 'confirmed', 'day after UTC revoke still confirmed overbill');

    // ISOB2026-style: active device, inventory still positive → must NOT invent remove_date
    Capsule::$deviceRows = [
        (object) [
            'hash' => '14ea18cd30a9f27a0f4d036c3482020ff9f9290b',
            'username' => 'ISOB2026',
            'name' => 'Boss11.isob.local',
            'revoked_at' => null,
            'content' => json_encode(['RegistrationTime' => strtotime('2026-04-05 19:57:13 UTC')]),
        ],
    ];
    Capsule::$inventoryRows = [
        (object) [
            'device_id' => '14ea18cd30a9f27a0f4d036c3482020ff9f9290b',
            'snapshot_date' => '2026-08-04',
            'vmware_vms' => 1,
            'hyperv_vms' => 0,
        ],
    ];
    Capsule::$activeRows = [
        (object) [
            'id' => 3,
            'pulled_at' => '2026-08-04 19:05:08',
            'service_name' => 'Account ISOB2026 - Device 14ea18 - Booster (VMware) Guest Count 1',
            'billing_cycle_days' => 1,
            'next_due_date' => '2026-08-06',
            'device_id' => '14ea18',
        ],
    ];
    Capsule::$usageRows = [
        (object) [
            'id' => 200,
            'usage_date' => '2026-08-05',
            'tenant_id' => 'ISOB2026',
            'device_id' => '14ea18cd30a9f27a0f4d036c3482020ff9f9290b',
            'item_type' => 'booster',
            'item_desc' => 'Booster - VMware',
            'amount' => '0.16',
            'packs_used_raw' => '10,000 Dollars',
            'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
            'raw_row' => json_encode([]),
        ],
    ];
    \CometBilling\LifecycleResolver::clearCache();
    \CometBilling\ServiceIdentityResolver::clearCache();
    \CometBilling\BillingCadenceResolver::clearCache();

    $activeVmware = OverbillEvidenceEvaluator::evaluate(Capsule::$usageRows[0], false);
    assert_eq($activeVmware['evidence']['lifecycle']['remove_date'] ?? null, null, 'active inventory booster has no remove_date');
    assert_eq($activeVmware['billing_verdict'], 'active_or_unknown', 'active booster not after expected end');
    assert_eq($activeVmware['verdict'] === 'confirmed', false, 'active VMware booster not confirmed overbill');

    // Inventory drop 1→0 still yields remove_date
    Capsule::$inventoryRows = [
        (object) [
            'device_id' => '14ea18cd30a9f27a0f4d036c3482020ff9f9290b',
            'snapshot_date' => '2026-08-03',
            'vmware_vms' => 1,
            'hyperv_vms' => 0,
        ],
        (object) [
            'device_id' => '14ea18cd30a9f27a0f4d036c3482020ff9f9290b',
            'snapshot_date' => '2026-08-04',
            'vmware_vms' => 0,
            'hyperv_vms' => 0,
        ],
    ];
    \CometBilling\LifecycleResolver::clearCache();
    $dropped = \CometBilling\LifecycleResolver::resolve(
        '14ea18cd30a9f27a0f4d036c3482020ff9f9290b',
        'vmware_vms',
        'ISOB2026'
    );
    assert_eq($dropped['remove_date'], '2026-08-03', 'inventory 1→0 uses last positive as remove_date');

    // TechComp-style false positive: inventory hv 1→0 but Active Services still qty=1
    Capsule::$deviceRows = [
        (object) [
            'hash' => '32789ae06f748489ff6e0c6352e8fada6513915f',
            'username' => 'TechCompITSolutions',
            'name' => 'R640-HOME',
            'revoked_at' => null,
            'content' => json_encode(['RegistrationTime' => strtotime('2025-03-10 12:00:00 UTC')]),
        ],
    ];
    Capsule::$inventoryRows = [
        (object) [
            'device_id' => '32789ae06f748489ff6e0c6352e8fada6513915f',
            'snapshot_date' => '2026-08-04',
            'hyperv_vms' => 1,
            'vmware_vms' => 0,
        ],
        (object) [
            'device_id' => '32789ae06f748489ff6e0c6352e8fada6513915f',
            'snapshot_date' => '2026-08-07',
            'hyperv_vms' => 0,
            'vmware_vms' => 0,
        ],
    ];
    Capsule::$activeRows = [
        (object) [
            'id' => 40,
            'pulled_at' => '2026-08-07 11:49:00',
            'service_name' => 'Account TechCompITSolutions - Device 32789a - Booster (Microsoft Hyper-V) Guest Count 1',
            'billing_cycle_days' => 1,
            'next_due_date' => '2026-08-08',
            'quantity' => 1,
            'amount' => 0.1,
            'unit_cost' => 3,
            'device_id' => '',
        ],
    ];
    Capsule::$usageRows = [
        (object) [
            'id' => 501,
            'usage_date' => '2026-08-05',
            'tenant_id' => 'TechCompITSolutions',
            'device_id' => '32789ae06f748489ff6e0c6352e8fada6513915f',
            'item_type' => 'booster',
            'item_desc' => 'Booster - Microsoft Hyper-V',
            'amount' => '0.10',
            'packs_used_raw' => '10,000 Dollars',
            'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
            'raw_row' => json_encode([]),
        ],
    ];
    \CometBilling\LifecycleResolver::clearCache();
    \CometBilling\ServiceIdentityResolver::clearCache();
    \CometBilling\BillingCadenceResolver::clearCache();
    $techComp = OverbillEvidenceEvaluator::evaluate(Capsule::$usageRows[0], true);
    assert_eq($techComp['verdict'] === 'confirmed', false, 'AS-positive Hyper-V not confirmed when inventory drops to 0');
    assert_eq($techComp['billing_verdict'], 'active_or_unknown', 'AS override keeps Hyper-V active_or_unknown');
    assert_eq(
        in_array('inventory_remove_overridden_by_active_services', $techComp['confidence_reasons'], true),
        true,
        'reason notes AS override of inventory remove'
    );

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
    $report = HistoricalReconciler::report('2026-07-01', '2026-07-31', false, false);
    assert_eq($report['summary']['charges_scanned'], 2, 'scans only current usage occurrences');
    assert_eq(CanonicalUsage::hasCanonicalPull(), true, 'hasCanonicalPull true when manifest exists');
    $canonicalRows = CanonicalUsage::query()->get();
    assert_eq(count($canonicalRows), 2, 'canonical query excludes stale rows');
    $occurrences = array_map(static fn (object $row): int => (int) $row->occurrence_number, $canonicalRows);
    sort($occurrences);
    assert_eq($occurrences, [1, 2], 'only current occurrences remain');

    Capsule::$manifestRows = [];
    CanonicalUsage::clearCache();
    assert_eq(CanonicalUsage::hasCanonicalPull(), false, 'hasCanonicalPull false when manifest empty');

    // Korol-style: period-end charge on revoked device uses pre-roll next_due (not post-roll snapshot)
    $korolHash = '69da4c4ae1e1598f9002334ec6894307a2a9cfe0';
    Capsule::$deviceRows = [
        (object) [
            'hash' => $korolHash,
            'username' => 'dr_jacqueline_korol',
            'name' => 'server',
            'revoked_at' => '2026-08-04 10:52:29',
            'content' => json_encode(['RegistrationTime' => strtotime('2023-09-19 16:36:04')]),
        ],
    ];
    Capsule::$activeRows = [
        (object) [
            'id' => 10,
            'pulled_at' => '2026-08-04 19:05:08',
            'service_name' => 'Account dr_jacqueline_korol - Device 69da4cPlanAdvanced Plan ($2/device)',
            'billing_cycle_days' => 30,
            'next_due_date' => '2026-08-05',
            'device_id' => '69da4c',
            'extra' => json_encode(['Type' => 'device']),
        ],
        (object) [
            'id' => 11,
            'pulled_at' => '2026-08-04 19:05:08',
            'service_name' => 'Account dr_jacqueline_korol - Device 69da4c - Booster (Microsoft SQL Server)PlanAdvanced Plan',
            'billing_cycle_days' => 30,
            'next_due_date' => '2026-08-05',
            'device_id' => '69da4c',
            'extra' => json_encode(['Type' => 'booster']),
        ],
        (object) [
            'id' => 12,
            'pulled_at' => '2026-08-05 12:09:17',
            'service_name' => 'Account dr_jacqueline_korol - Device 69da4cPlanAdvanced Plan ($2/device)',
            'billing_cycle_days' => 30,
            'next_due_date' => '2026-09-04',
            'device_id' => '69da4c',
            'extra' => json_encode(['Type' => 'device']),
        ],
        (object) [
            'id' => 13,
            'pulled_at' => '2026-08-05 12:09:17',
            'service_name' => 'Account dr_jacqueline_korol - Device 69da4c - Booster (Microsoft SQL Server)PlanAdvanced Plan',
            'billing_cycle_days' => 30,
            'next_due_date' => '2026-09-04',
            'device_id' => '69da4c',
            'extra' => json_encode(['Type' => 'booster']),
        ],
    ];
    \CometBilling\LifecycleResolver::clearCache();
    \CometBilling\ServiceIdentityResolver::clearCache();
    \CometBilling\BillingCadenceResolver::clearCache();

    $korolDeviceCharge = (object) [
        'id' => 300,
        'usage_date' => '2026-08-05',
        'tenant_id' => 'dr_jacqueline_korol',
        'device_id' => $korolHash,
        'item_type' => 'device',
        'item_desc' => 'Device - dr_jacqueline_korol',
        'amount' => '2.00',
        'packs_used_raw' => '10,000 Dollars',
        'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
        'raw_row' => json_encode([]),
    ];
    $korolMssqlCharge = (object) [
        'id' => 301,
        'usage_date' => '2026-08-05',
        'tenant_id' => 'dr_jacqueline_korol',
        'device_id' => $korolHash,
        'item_type' => 'booster',
        'item_desc' => 'Booster - Microsoft SQL Server',
        'amount' => '1.00',
        'packs_used_raw' => '10,000 Dollars',
        'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
        'raw_row' => json_encode([]),
    ];

    $korolDevice = OverbillEvidenceEvaluator::evaluate($korolDeviceCharge, false);
    assert_eq($korolDevice['expected_billing_end'], '2026-08-05', 'korol device uses pre-roll expected end');
    assert_eq($korolDevice['billing_verdict'], 'within_period', 'korol device period-end charge within period');
    assert_eq($korolDevice['verdict'], 'not_overbilled', 'korol device period-end charge not overbilled');
    assert_eq(
        str_contains((string) ($korolDevice['evidence']['cadence']['service_name'] ?? ''), 'Booster'),
        false,
        'korol device cadence does not bind to mssql booster row'
    );

    $korolMssql = OverbillEvidenceEvaluator::evaluate($korolMssqlCharge, false);
    assert_eq($korolMssql['expected_billing_end'], '2026-08-05', 'korol mssql uses pre-roll expected end');
    assert_eq($korolMssql['verdict'], 'not_overbilled', 'korol mssql period-end charge not overbilled');
    assert_eq(
        str_contains((string) ($korolMssql['evidence']['cadence']['service_name'] ?? ''), 'SQL Server'),
        true,
        'korol mssql cadence binds to mssql booster row'
    );

    // Post-roll only: true residual after expected end still confirms (policy A)
    Capsule::$activeRows = [
        (object) [
            'id' => 20,
            'pulled_at' => '2026-09-05 12:00:00',
            'service_name' => 'Account dr_jacqueline_korol - Device 69da4cPlanAdvanced Plan ($2/device)',
            'billing_cycle_days' => 30,
            'next_due_date' => '2026-09-04',
            'device_id' => '69da4c',
            'extra' => json_encode(['Type' => 'device']),
        ],
    ];
    \CometBilling\BillingCadenceResolver::clearCache();

    $korolResidual = (object) [
        'id' => 302,
        'usage_date' => '2026-09-05',
        'tenant_id' => 'dr_jacqueline_korol',
        'device_id' => $korolHash,
        'item_type' => 'device',
        'item_desc' => 'Device - dr_jacqueline_korol',
        'amount' => '2.00',
        'packs_used_raw' => '10,000 Dollars',
        'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
        'raw_row' => json_encode([]),
    ];
    $residual = OverbillEvidenceEvaluator::evaluate($korolResidual, false);
    assert_eq($residual['billing_verdict'], 'after_expected_end', 'post-roll residual charge after expected end');
    assert_eq($residual['verdict'], 'confirmed', 'post-roll residual charge still confirmed overbill');

    // Persist + load round-trip
    Capsule::$deviceRows = [
        (object) [
            'hash' => 'persistdev123456',
            'username' => 'PersistCorp',
            'name' => 'Host',
            'revoked_at' => '2026-07-06 08:00:00',
            'content' => '{}',
        ],
    ];
    Capsule::$activeRows = [
        (object) [
            'id' => 50,
            'pulled_at' => '2026-07-07 12:00:00',
            'service_name' => 'Account PersistCorp - Device persist - Booster Hyper-V',
            'billing_cycle_days' => 1,
            'next_due_date' => '2026-07-07',
            'device_id' => 'persis',
        ],
    ];
    Capsule::$usageRows = [
        (object) [
            'id' => 500,
            'usage_date' => '2026-07-07',
            'tenant_id' => 'PersistCorp',
            'device_id' => 'persistdev123456',
            'item_type' => 'booster',
            'item_desc' => 'Booster - Hyper-V Guest Count',
            'amount' => '2.50',
            'packs_used_raw' => '10,000 Dollars',
            'packs_used_parsed' => json_encode(PackUsageParser::parse('10,000 Dollars')),
            'raw_row' => json_encode([]),
        ],
    ];
    Capsule::$auditRuns = [];
    Capsule::$auditFindings = [];
    \CometBilling\LifecycleResolver::clearCache();
    \CometBilling\ServiceIdentityResolver::clearCache();
    \CometBilling\BillingCadenceResolver::clearCache();
    ReversalIndex::clear();

    $live = HistoricalReconciler::report('2026-07-01', '2026-07-31', false, true);
    assert_eq($live['audit_run_id'] !== null, true, 'persist creates audit run id');
    assert_eq(count(Capsule::$auditFindings) >= 1, true, 'persist stores findings');

    $loaded = HistoricalReconciler::loadPersistedReport('2026-07-01', '2026-07-31', false);
    assert_eq($loaded !== null, true, 'loadPersistedReport returns report');
    assert_eq((int) ($loaded['audit_run_id'] ?? 0), (int) $live['audit_run_id'], 'loaded run id matches');
    assert_eq((int) ($loaded['summary']['confirmed_count'] ?? 0), (int) ($live['summary']['confirmed_count'] ?? 0), 'loaded summary matches');

    // No near AS snapshot → skip observedDaily (monthly default, low confidence)
    Capsule::$activeRows = [];
    \CometBilling\BillingCadenceResolver::clearCache();
    $noSnap = BillingCadenceResolver::resolve('2026-06-15', 'devices', 'PersistCorp', 'persistdev123456', 'Device - PersistCorp');
    assert_eq($noSnap['observed_daily'], false, 'no near snapshot skips observed daily scan');
    assert_eq($noSnap['confidence'], 'low', 'cadence low confidence without portal anchor');

    echo "\nAll HistoricalReconciler audit tests passed.\n";
}
