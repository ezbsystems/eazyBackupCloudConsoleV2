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

        public static function schema(): object
        {
            return new class {
                public function hasTable(string $table): bool
                {
                    return in_array($table, ['comet_devices', 'cb_credit_usage'], true);
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

                public function __construct(private string $table)
                {
                }

                public function whereNotNull(string $column): self
                {
                    $this->conditions[] = [$column, 'not_null', null];
                    return $this;
                }

                public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
                {
                    if ($value === null) {
                        $this->conditions[] = [$column, '=', $operatorOrValue];
                    } else {
                        $this->conditions[] = [$column, (string) $operatorOrValue, $value];
                    }
                    return $this;
                }

                public function whereBetween(string $column, array $range): self
                {
                    $this->conditions[] = [$column, 'between', $range];
                    return $this;
                }

                public function orderBy(string $column, string $direction = 'asc'): self
                {
                    $this->orderColumn = $column;
                    return $this;
                }

                public function offset(int $offset): self
                {
                    $this->offset = $offset;
                    return $this;
                }

                public function limit(int $limit): self
                {
                    $this->limit = $limit;
                    return $this;
                }

                /** @param list<string> $columns */
                public function select(array $columns): self
                {
                    return $this;
                }

                public function min(string $column): ?string
                {
                    return null;
                }

                /** @return list<object> */
                public function get(array $columns = []): array
                {
                    $rows = $this->table === 'comet_devices' ? Capsule::$deviceRows : Capsule::$usageRows;
                    $filtered = array_values(array_filter($rows, function (object $row): bool {
                        foreach ($this->conditions as [$column, $operator, $value]) {
                            $actual = $row->{$column} ?? null;
                            if ($operator === 'not_null' && $actual === null) {
                                return false;
                            }
                            if ($operator === '=' && $actual != $value) {
                                return false;
                            }
                            if ($operator === '!=' && $actual == $value) {
                                return false;
                            }
                            if ($operator === 'between' && is_array($value)) {
                                if ($actual < $value[0] || $actual > $value[1]) {
                                    return false;
                                }
                            }
                        }
                        return true;
                    }));

                    if ($this->orderColumn !== null) {
                        usort($filtered, function (object $a, object $b): int {
                            return ($a->id ?? 0) <=> ($b->id ?? 0);
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
            };
        }
    }
}

namespace {
    require_once __DIR__ . '/../lib/BillingPeriodCalculator.php';
    require_once __DIR__ . '/../lib/HistoricalReconciler.php';

    use CometBilling\HistoricalReconciler;
    use WHMCS\Database\Capsule;

    function assert_eq($actual, $expected, string $label): void
    {
        if ($actual !== $expected) {
            fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n");
            exit(1);
        }
        echo "OK {$label}\n";
    }

    function assert_true(bool $cond, string $label): void
    {
        if (!$cond) {
            fwrite(STDERR, "FAIL {$label}\n");
            exit(1);
        }
        echo "OK {$label}\n";
    }

    // Monthly device: charge after registration-aligned period end → overbilled
    Capsule::$deviceRows = [
        (object) [
            'hash' => 'monthlydev123456',
            'username' => 'MonthlyCorp',
            'name' => 'Monthly Device',
            'revoked_at' => '2026-06-27 12:00:00',
            'content' => json_encode(['RegistrationTime' => strtotime('2026-06-06 00:00:00 UTC')]),
        ],
        (object) [
            'hash' => 'dailyhyperv12345',
            'username' => 'DailyCorp',
            'name' => 'Hyper-V Host',
            'revoked_at' => '2026-07-06 08:00:00',
            'content' => '{}',
        ],
    ];

    Capsule::$usageRows = [
        (object) [
            'id' => 1,
            'usage_date' => '2026-07-10',
            'tenant_id' => 'MonthlyCorp',
            'device_id' => 'monthlydev123456',
            'item_type' => 'device',
            'item_desc' => 'Device - Monthly Device',
            'amount' => '5.00',
        ],
        (object) [
            'id' => 2,
            'usage_date' => '2026-07-06',
            'tenant_id' => 'MonthlyCorp',
            'device_id' => 'monthlydev123456',
            'item_type' => 'device',
            'item_desc' => 'Device - Monthly Device',
            'amount' => '5.00',
        ],
        (object) [
            'id' => 3,
            'usage_date' => '2026-07-07',
            'tenant_id' => 'DailyCorp',
            'device_id' => 'dailyhyperv12345',
            'item_type' => 'booster',
            'item_desc' => 'Booster - Hyper-V Guest Count',
            'amount' => '2.50',
        ],
        (object) [
            'id' => 4,
            'usage_date' => '2026-07-06',
            'tenant_id' => 'DailyCorp',
            'device_id' => 'dailyhyperv12345',
            'item_type' => 'booster',
            'item_desc' => 'Booster - Hyper-V Guest Count',
            'amount' => '2.50',
        ],
        (object) [
            'id' => 5,
            'usage_date' => '2026-07-10',
            'tenant_id' => 'Unknown',
            'device_id' => 'unknowndevice999',
            'item_type' => 'device',
            'item_desc' => 'Device - Unknown',
            'amount' => '3.00',
        ],
    ];

    $report = HistoricalReconciler::report('2026-07-01', '2026-07-31');

    assert_eq($report['summary']['charges_scanned'], 5, 'scans all usage rows with device_id');
    assert_eq($report['summary']['matched_revoked'], 4, 'matches revoked devices only');
    assert_eq($report['summary']['unmatched_device_count'], 1, 'unmatched device not in overbill total');
    assert_eq($report['summary']['overbilled_count'], 2, 'monthly late + daily hyper-v day after revoke');
    assert_eq($report['summary']['overbilled_amount'], 7.5, 'overbill amount sums matched overbilled charges');
    assert_eq($report['summary']['expected_grace_count'], 2, 'grace charges within expected end');

    $overbilled = array_values(array_filter($report['rows'], static fn (array $r): bool => $r['billing_status'] === 'overbilled_past_grace'));
    assert_eq(count($overbilled), 2, 'UI rows contain overbilled lines');

    $monthlyOver = null;
    $dailyOver = null;
    foreach ($overbilled as $row) {
        if ($row['category'] === 'devices') {
            $monthlyOver = $row;
        }
        if ($row['category'] === 'hyperv_vms') {
            $dailyOver = $row;
        }
    }

    assert_true($monthlyOver !== null, 'monthly device overbill row present');
    assert_eq($monthlyOver['expected_billing_end'], '2026-07-06', 'monthly expected end from registration period');
    assert_eq($monthlyOver['billing_status'], 'overbilled_past_grace', 'monthly charge after period end is overbilled');
    assert_eq($monthlyOver['overbill_amount'], 5.0, 'monthly overbill amount equals charge');

    assert_true($dailyOver !== null, 'daily hyper-v overbill row present');
    assert_eq($dailyOver['expected_billing_end'], '2026-07-06', 'daily expected end is revoke date');
    assert_eq($dailyOver['cycle'], 'daily', 'hyper-v categorized as daily cycle');
    assert_eq($dailyOver['overbill_amount'], 2.5, 'daily overbill amount equals charge');

    assert_eq(HistoricalReconciler::categorizeCharge('booster', 'Booster - VMware Guest Count'), 'vmware_vms', 'categorize vmware booster');
    assert_eq(HistoricalReconciler::categorizeCharge('device', 'Device - Host'), 'devices', 'categorize device');

    echo "\nAll HistoricalReconciler tests passed.\n";
}
