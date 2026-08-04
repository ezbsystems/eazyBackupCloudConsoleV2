<?php
/**
 * Run: php tests/DeviceMatcherBillingPeriodTest.php
 */
namespace WHMCS\Database {
    class Capsule
    {
        /** @var list<object> */
        public static array $rows = [];

        public static function schema(): object
        {
            return new class {
                public function hasTable(string $table): bool
                {
                    return $table === 'comet_devices';
                }
            };
        }

        public static function table(string $table): object
        {
            return new class {
                public function whereNotNull(string $column): self
                {
                    return $this;
                }

                /** @param list<string> $columns */
                public function select(array $columns): self
                {
                    return $this;
                }

                /** @return list<object> */
                public function get(): array
                {
                    return Capsule::$rows;
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
    assert_eq($registrationAligned['expected_billing_end'], '2026-07-06', 'RegistrationTime determines expected end');
    assert_eq($registrationAligned['billing_status'], 'overbilled_past_grace', 'registration-aligned status');
    assert_eq($registrationAligned['overbill_amount'], 10.0, 'registration-aligned overbill amount');

    $nextDueFallback = $result['portal_only'][1];
    assert_eq($nextDueFallback['registered_at'], null, 'missing RegistrationTime remains null');
    assert_eq($nextDueFallback['expected_billing_end'], '2026-07-07', 'next_due walk-back determines expected end');
    assert_eq($nextDueFallback['expected_billing_end'] === '2026-07-27', false, 'expected end is not revoked-plus-cycle');

    echo "All DeviceMatcher billing period tests passed.\n";
}
