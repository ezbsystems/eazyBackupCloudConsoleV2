<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Trial lifecycle for the MS365 Backup WHMCS product (e3cb-style).
 */
final class Ms365BillingTrial
{
    private const MODULE = 'ms365backup';

    public static function startTrial(int $serviceId, int $clientId, ?int $trialDays = null): void
    {
        if ($serviceId <= 0 || $clientId <= 0) {
            return;
        }
        $days = $trialDays !== null ? max(0, (int) $trialDays) : Ms365BillingConfig::trialDays($serviceId);
        if ($days <= 0) {
            $days = 30;
        }
        try {
            if (Capsule::table('ms365_billing_trial_state')->where('service_id', $serviceId)->exists()) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $ends = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            Capsule::table('ms365_billing_trial_state')->insert([
                'service_id' => $serviceId,
                'client_id' => $clientId,
                'trial_started_at' => $now,
                'trial_ends_at' => $ends,
                'status' => 'trialing',
                'last_evaluated_at' => $now,
                'notes' => "Trial started ({$days} days).",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            self::pushNextDueDate($serviceId, $days);
            self::log('trial_started', ['service_id' => $serviceId, 'client_id' => $clientId, 'days' => $days], 'ok');
        } catch (\Throwable $e) {
            self::log('trial_start_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    public static function status(int $serviceId): ?string
    {
        if ($serviceId <= 0) {
            return null;
        }
        try {
            $row = Capsule::table('ms365_billing_trial_state')->where('service_id', $serviceId)->first();
            if (!$row) {
                return null;
            }

            return (string) ($row->status ?? '');
        } catch (\Throwable $_) {
            return null;
        }
    }

    /** @return array{evaluated: int, converted: int, suspended: int, errors: int} */
    public static function evaluateAll(): array
    {
        $result = ['evaluated' => 0, 'converted' => 0, 'suspended' => 0, 'errors' => 0];
        try {
            $rows = Capsule::table('ms365_billing_trial_state')
                ->whereIn('status', ['trialing', 'suspended_no_payment'])
                ->get();
        } catch (\Throwable $e) {
            self::log('evaluate_all_query_fail', [], $e->getMessage());

            return $result;
        }

        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $result['evaluated']++;
            try {
                $serviceId = (int) $row->service_id;
                $clientId = (int) $row->client_id;
                $status = (string) $row->status;
                $trialEnds = (string) ($row->trial_ends_at ?? '');
                $trialOver = $trialEnds !== '' && substr($trialEnds, 0, 10) <= $today;

                if ($status === 'trialing') {
                    if (!$trialOver) {
                        self::touch($serviceId);
                        continue;
                    }
                    if (self::clientHasCard($clientId)) {
                        self::convert($serviceId, $clientId, 'trial_end_with_card');
                        $result['converted']++;
                    } else {
                        self::suspend($serviceId, $clientId, 'trial_end_no_card');
                        $result['suspended']++;
                    }
                } elseif ($status === 'suspended_no_payment' && self::clientHasCard($clientId)) {
                    self::convert($serviceId, $clientId, 'reactivate_card_added');
                    $result['converted']++;
                } else {
                    self::touch($serviceId);
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                self::log('evaluate_row_fail', ['service_id' => $row->service_id ?? null], $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Force one specific service through evaluation (admin trials page / reactivation).
     */
    public static function evaluateService(int $serviceId, ?string $forceTo = null): string
    {
        try {
            $row = Capsule::table('ms365_billing_trial_state')->where('service_id', $serviceId)->first();
            if (!$row) {
                return 'no_trial_state';
            }
            $clientId = (int) $row->client_id;
            if ($forceTo === 'converted') {
                self::convert($serviceId, $clientId, 'admin_force_convert');

                return 'converted';
            }
            if ($forceTo === 'cancelled') {
                self::cancel($serviceId, $clientId, 'admin_force_cancel');

                return 'cancelled';
            }
            if ($forceTo === 'suspended_no_payment') {
                self::suspend($serviceId, $clientId, 'admin_force_suspend');

                return 'suspended_no_payment';
            }

            $today = date('Y-m-d');
            $status = (string) $row->status;
            $trialEnds = (string) ($row->trial_ends_at ?? '');
            $trialOver = $trialEnds !== '' && substr($trialEnds, 0, 10) <= $today;

            if ($status === 'trialing' && $trialOver) {
                if (self::clientHasCard($clientId)) {
                    self::convert($serviceId, $clientId, 'trial_end_with_card');

                    return 'converted';
                }
                self::suspend($serviceId, $clientId, 'trial_end_no_card');

                return 'suspended_no_payment';
            }
            if ($status === 'suspended_no_payment' && self::clientHasCard($clientId)) {
                self::convert($serviceId, $clientId, 'reactivate_card_added');

                return 'converted';
            }
            self::touch($serviceId);

            return $status;
        } catch (\Throwable $e) {
            self::log('evaluate_service_fail', ['service_id' => $serviceId], $e->getMessage());

            return 'error';
        }
    }

    public static function convert(int $serviceId, int $clientId, string $reason): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            Capsule::table('ms365_billing_trial_state')
                ->where('service_id', $serviceId)
                ->update([
                    'status' => 'converted',
                    'last_evaluated_at' => $now,
                    'notes' => 'Converted: ' . $reason,
                    'updated_at' => $now,
                ]);
            self::anchorNextDueDate($serviceId);
            self::setServiceStatus($serviceId, 'Active');
            Ms365BillingService::rateService($serviceId);
            Ms365BillingService::applyToWhmcs($serviceId);
            self::log('trial_converted', ['service_id' => $serviceId, 'client_id' => $clientId, 'reason' => $reason], 'ok');
        } catch (\Throwable $e) {
            self::log('trial_convert_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    public static function suspend(int $serviceId, int $clientId, string $reason): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            Capsule::table('ms365_billing_trial_state')
                ->where('service_id', $serviceId)
                ->update([
                    'status' => 'suspended_no_payment',
                    'last_evaluated_at' => $now,
                    'notes' => 'Suspended: ' . $reason,
                    'updated_at' => $now,
                ]);
            self::setServiceStatus($serviceId, 'Suspended', 'MS365 Backup trial ended without a payment method on file.');
            self::log('trial_suspended', ['service_id' => $serviceId, 'client_id' => $clientId, 'reason' => $reason], 'ok');
        } catch (\Throwable $e) {
            self::log('trial_suspend_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    public static function cancel(int $serviceId, int $clientId, string $reason): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            Capsule::table('ms365_billing_trial_state')
                ->where('service_id', $serviceId)
                ->update([
                    'status' => 'cancelled',
                    'last_evaluated_at' => $now,
                    'notes' => 'Cancelled: ' . $reason,
                    'updated_at' => $now,
                ]);
            self::setServiceStatus($serviceId, 'Cancelled', 'Admin cancellation: ' . $reason);
            self::log('trial_cancelled', ['service_id' => $serviceId, 'client_id' => $clientId, 'reason' => $reason], 'ok');
        } catch (\Throwable $e) {
            self::log('trial_cancel_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    public static function clientHasCard(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }
        try {
            if (Capsule::schema()->hasTable('tblpaymethods')) {
                if (Capsule::table('tblpaymethods')
                    ->where('userid', $clientId)
                    ->whereNull('deleted_at')
                    ->whereIn('payment_type', ['CreditCard', 'RemoteCreditCard'])
                    ->exists()) {
                    return true;
                }
            }
        } catch (\Throwable $_) {
        }
        try {
            $resp = localAPI('GetPayMethods', ['clientid' => $clientId]);
            if (($resp['result'] ?? '') === 'success' && !empty($resp['paymethods']) && is_array($resp['paymethods'])) {
                foreach ($resp['paymethods'] as $pm) {
                    $ptype = strtolower((string) ($pm['payment_type'] ?? ''));
                    if ($ptype === 'creditcard' || $ptype === 'remotecreditcard') {
                        return true;
                    }
                }
            }
        } catch (\Throwable $_) {
        }

        return false;
    }

    public static function setServiceStatus(int $serviceId, string $status, ?string $note = null): void
    {
        try {
            $update = ['domainstatus' => $status];
            if ($status === 'Suspended' && $note !== null) {
                $update['suspendreason'] = $note;
            } elseif ($status === 'Active') {
                $update['suspendreason'] = '';
            }
            Capsule::table('tblhosting')->where('id', $serviceId)->update($update);
        } catch (\Throwable $e) {
            self::log('set_service_status_fail', ['service_id' => $serviceId, 'status' => $status], $e->getMessage());
        }
    }

    private static function pushNextDueDate(int $serviceId, int $trialDays): void
    {
        try {
            $nextDue = new \DateTimeImmutable('today');
            $nextDue = $nextDue->add(new \DateInterval('P' . max(1, $trialDays) . 'D'));
            $formatted = $nextDue->format('Y-m-d');
            Capsule::table('tblhosting')->where('id', $serviceId)->update([
                'nextduedate' => $formatted,
                'nextinvoicedate' => $formatted,
            ]);
        } catch (\Throwable $e) {
            self::log('push_next_due_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    private static function anchorNextDueDate(int $serviceId): void
    {
        try {
            $today = date('Y-m-d');
            Capsule::table('tblhosting')->where('id', $serviceId)->update([
                'nextduedate' => $today,
                'nextinvoicedate' => $today,
            ]);
        } catch (\Throwable $e) {
            self::log('anchor_due_fail', ['service_id' => $serviceId], $e->getMessage());
        }
    }

    private static function touch(int $serviceId): void
    {
        try {
            Capsule::table('ms365_billing_trial_state')->where('service_id', $serviceId)->update([
                'last_evaluated_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $_) {
        }
    }

    /** @param mixed $payload */
    private static function log(string $action, array $context, $payload): void
    {
        try {
            logModuleCall(self::MODULE, $action, $context, $payload, [], []);
        } catch (\Throwable $_) {
        }
    }
}
