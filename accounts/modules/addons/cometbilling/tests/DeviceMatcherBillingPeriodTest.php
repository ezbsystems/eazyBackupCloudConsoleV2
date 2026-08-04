<?php
/**
 * Run: php tests/DeviceMatcherBillingPeriodTest.php
 */
namespace WHMCS\Database {
    class Capsule
    {
        /** @var list<object> */
        public static array $rows = [];
        /** @var list<object> */
        public static array $inventoryRows = [];

        public static function schema(): object
        {
            return new class {
                public function hasTable(string $table): bool
                {
                    return in_array($table, ['comet_devices', 'cb_server_device_inventory'], true);
                }
            };
        }

        public static function table(string $table): object
        {
            return new class($table) {
                /** @var list<array{string, string, mixed}> */
                private array $conditions = [];

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

                public function orderBy(string $column, string $direction): self
                {
                    return $this;
                }

                /** @param list<string> $columns */
                public function select(array $columns): self
                {
                    return $this;
                }

                /** @return list<object> */
                public function get(array $columns = []): array
                {
                    $rows = $this->table === 'comet_devices' ? Capsule::$rows : Capsule::$inventoryRows;
                    return array_values(array_filter($rows, function (object $row): bool {
                        foreach ($this->conditions as [$column, $operator, $value]) {
                            $actual = $row->{$column} ?? null;
                            if ($operator === 'not_null' && $actual === null) {
                                return false;
                            }
                            if ($operator === '=' && $actual != $value) {
                                return false;
                            }
                            if ($operator === '<=' && $actual > $value) {
                                return false;
                            }
                            if ($operator === '>' && $actual <= $value) {
                                return false;
                            }
                        }
                        return true;
                    }));
                }

                public function exists(): bool
                {
                    return $this->get() !== [];
                }
            };
        }
    }
}

namespace {
    require_once __DIR__ . '/../lib/BillingPeriodCalculator.php';
    require_once __DIR__ . '/../lib/DeviceMatcher.php';

    use CometBilling\DeviceMatcher;
    use WHMCS\Database\Capsule;

    function assert_eq($actual, $expected, string $label): void
    {
        if ($actual !== $expected) {
            fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n");
            exit(1);
        }
        echo "OK {$label}\n";
    }

    Capsule::$rows = [
        (object) [
            'hash' => '7bef57abc123',
            'username' => 'KaizaCorp',
            'name' => 'Registration-aligned device',
            'revoked_at' => '2026-06-27 12:00:00',
            'content' => json_encode(['RegistrationTime' => strtotime('2026-06-06 00:00:00 UTC')]),
        ],
        (object) [
            'hash' => 'f00baaabc123',
            'username' => 'Fallback Corp',
            'name' => 'Next-due fallback device',
            'revoked_at' => '2026-06-27 12:00:00',
            'content' => '{}',
        ],
    ];

    $result = DeviceMatcher::matchCategory('devices', [
        [
            'account' => 'KaizaCorp',
            'device_id' => '7bef57',
            'next_due_date' => '2026-08-06',
            'billing_cycle_days' => 30,
            'amount' => 10.0,
        ],
        [
            'account' => 'Fallback Corp',
            'device_id' => 'f00baa',
            'next_due_date' => '2026-08-06',
            'billing_cycle_days' => 30,
            'amount' => 10.0,
        ],
    ], [], false);

    $registrationAligned = $result['portal_only'][0];
    assert_eq($registrationAligned['registered_at'], '2026-06-06', 'RegistrationTime exposed as registered_at');
    // With next_due present, period end comes from walk-back (same Jul 6 in this fixture)
    assert_eq($registrationAligned['expected_billing_end'], '2026-07-06', 'next_due walk-back determines expected end');
    assert_eq($registrationAligned['billing_status'], 'overbilled_past_grace', 'past period end is overbilled');
    assert_eq($registrationAligned['overbill_amount'], 10.0, 'overbill amount when past period end');

    $nextDueFallback = $result['portal_only'][1];
    assert_eq($nextDueFallback['registered_at'], null, 'missing RegistrationTime remains null');
    assert_eq($nextDueFallback['expected_billing_end'], '2026-07-06', 'next_due walk-back determines expected end without reg');
    assert_eq($nextDueFallback['expected_billing_end'] === '2026-07-27', false, 'expected end is not revoked-plus-cycle');

    // Device where RegistrationTime period disagrees with portal next_due (ssi.chirosuite-style)
    Capsule::$rows[] = (object) [
        'hash' => 'd1ebef440455',
        'username' => 'ssi.chirosuite',
        'name' => 'r1.aflsrv',
        'revoked_at' => '2026-07-31 20:43:17',
        'content' => json_encode(['RegistrationTime' => strtotime('2024-05-03 20:11:48 UTC')]),
    ];
    $chiroResult = DeviceMatcher::matchCategory('devices', [[
        'account' => 'ssi.chirosuite',
        'device_id' => 'd1ebef',
        'next_due_date' => '2026-08-22',
        'billing_cycle_days' => 30,
        'amount' => 2.0,
    ]], [], false);
    $chiro = $chiroResult['portal_only'][0];
    assert_eq($chiro['expected_billing_end'], '2026-08-22', 'device expected end follows portal next_due period');
    assert_eq($chiro['billing_status'], 'expected_grace', 'device still within portal billing period');
    assert_eq($chiro['overbill_amount'], 0.0, 'device not overbilled before next due advances');
    $boosterResult = DeviceMatcher::matchCategory('hyperv_vms', [[
        'account' => 'KaizaCorp',
        'device_id' => '7bef57',
        'next_due_date' => '2026-08-06',
        'billing_cycle_days' => 1,
        'amount' => 12.50,
    ]], [], false, '2026-08-04');

    $booster = $boosterResult['portal_only'][0];
    assert_eq($booster['expected_billing_end'], '2026-06-27', 'daily booster expected end is revoke date');
    assert_eq($booster['billing_cycle_days'], 1, 'daily booster billing cycle is daily');
    assert_eq($booster['billing_status'], 'overbilled_past_grace', 'revoked daily booster is overbilled');
    assert_eq($booster['overbill_amount'], 12.50, 'revoked daily booster overbill amount');

    Capsule::$rows[] = (object) [
        'hash' => 'c74a4fd85344',
        'username' => 'MeasurementsO365',
        'name' => 'MeasurementsO365',
        'revoked_at' => '2026-08-03 19:28:15',
        'content' => json_encode(['RegistrationTime' => strtotime('2025-09-27 00:00:00 UTC')]),
    ];

    $m365Result = DeviceMatcher::matchCategory('m365_accounts', [[
        'account' => 'MeasurementsO365',
        'device_id' => 'c74a4f',
        'next_due_date' => '2026-08-24',
        'billing_cycle_days' => 30,
        'amount' => 31.0,
        'quantity' => 31,
    ]], [], false, '2026-08-04');

    $m365 = $m365Result['portal_only'][0];
    assert_eq($m365['billing_cycle_days'], 30, 'M365 keeps monthly cycle from portal');
    assert_eq($m365['billing_status'], 'expected_grace', 'M365 still within current monthly period');
    assert_eq($m365['overbill_amount'], 0.0, 'M365 not overbilled before next due');
    assert_eq($m365['expected_billing_end'] === '2026-08-03', false, 'M365 expected end is not revoke day');

    Capsule::$inventoryRows = [
        (object) ['device_id' => 'inventory-device', 'snapshot_date' => '2026-07-01', 'hyperv_vms' => 1],
        (object) ['device_id' => 'inventory-device', 'snapshot_date' => '2026-07-02', 'hyperv_vms' => 0],
    ];
    $inventoryBoosterResult = DeviceMatcher::matchCategory('hyperv_vms', [[
        'account' => 'Inventory Corp',
        'device_id' => 'inventory',
        'next_due_date' => '2026-08-06',
        'billing_cycle_days' => 1,
        'amount' => 8.00,
    ]], [[
        'device_id' => 'inventory-device',
        'username' => 'Inventory Corp',
        'friendly_name' => 'Inventory device',
        'hyperv_vms' => 0,
    ]], false, '2026-08-04');

    $inventoryBooster = $inventoryBoosterResult['portal_only'][0];
    assert_eq($inventoryBooster['expected_billing_end'], '2026-07-01', 'inventory disappearance uses last positive date');
    assert_eq($inventoryBooster['billing_cycle_days'], 1, 'inventory daily booster cycle is daily');
    assert_eq($inventoryBooster['billing_status'], 'overbilled_past_grace', 'inventory disappeared daily booster is overbilled');

    echo "All DeviceMatcher billing period tests passed.\n";
}
