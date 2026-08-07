<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Evidence-based overbill evaluation for a single usage row.
 */
class OverbillEvidenceEvaluator
{
    /**
     * @return array<string, mixed>
     */
    public static function evaluate(object $row, bool $coverageComplete = false): array
    {
        $usageDate = BillingPeriodCalculator::dateOnly((string) $row->usage_date);
        $amount = (float) ($row->amount ?? 0);
        $itemDesc = (string) ($row->item_desc ?? '');
        $itemType = (string) ($row->item_type ?? '');
        $tenantId = (string) ($row->tenant_id ?? '');
        $deviceIdRaw = (string) ($row->device_id ?? '');
        $packsRaw = (string) ($row->packs_used_raw ?? $row->packs_used ?? '');

        $category = ChargeCategoryResolver::fromUsageRow($itemType, $itemDesc);
        $identity = ServiceIdentityResolver::resolve($deviceIdRaw, $tenantId, $itemDesc);

        $debitEvidence = PackUsageParser::hasDebitEvidence($packsRaw, $amount) ? 'present' : 'absent';
        if ($debitEvidence === 'absent' && $amount > 0) {
            $debitEvidence = 'unclear';
        }

        $reasons = [];
        $cadence = BillingCadenceResolver::resolve(
            $usageDate,
            $category,
            $identity['account'],
            $identity['device_hash'],
            $itemDesc
        );

        $lifecycle = null;
        $expectedEnd = null;
        $billingVerdict = 'not_assessed';

        if ($identity['device_hash'] !== null) {
            $lifecycle = LifecycleResolver::resolve($identity['device_hash'], $category, $identity['account']);
            $expectedEnd = self::expectedBillingEnd($lifecycle, $cadence, $usageDate);

            if ($lifecycle['remove_date'] === null) {
                $billingVerdict = 'active_or_unknown';
                $reasons[] = 'no_lifecycle_stop_date';
            } elseif ($expectedEnd === null) {
                $billingVerdict = 'period_unknown';
                $reasons[] = 'billing_period_unknown';
            } elseif ($usageDate > $expectedEnd) {
                $billingVerdict = 'after_expected_end';
            } else {
                $billingVerdict = 'within_period';
            }
        } elseif ($category === 'account_plan') {
            $billingVerdict = 'account_plan_no_device_lifecycle';
            $reasons[] = 'account_plan_not_revocation_scoped';
        } else {
            $billingVerdict = 'identity_unresolved';
            $reasons[] = 'identity_' . $identity['status'];
        }

        $reversed = null;
        if ($billingVerdict === 'after_expected_end') {
            $reversed = ReversalIndex::lookup($usageDate, $tenantId, $identity['device_hash'])
                ?? self::findReversal($row, $usageDate, $tenantId, $identity['device_hash']);
            if ($reversed) {
                $billingVerdict = 'reversed';
                $reasons[] = 'offsetting_record_found';
            }
        }

        $verdict = self::gradeVerdict(
            $identity,
            $lifecycle,
            $cadence,
            $debitEvidence,
            $billingVerdict,
            $amount,
            $coverageComplete,
            $reasons
        );

        // #region agent log
        $hashForLog = (string) ($identity['device_hash'] ?? $deviceIdRaw);
        if (str_starts_with($hashForLog, '32789ae')
            || str_contains(strtolower($itemDesc), 'hyper-v')
            || ($verdict === 'confirmed' && in_array($category, ['hyperv_vms', 'vmware_vms', 'proxmox_vms'], true))
        ) {
            $asQty = $cadence['service_quantity'] ?? null;
            $lifeSrc = is_array($lifecycle) ? ($lifecycle['source'] ?? null) : null;
            $removeDate = is_array($lifecycle) ? ($lifecycle['remove_date'] ?? null) : null;
            $revokedAt = is_array($lifecycle) ? ($lifecycle['revoked_at'] ?? null) : null;
            self::debugLog('OverbillEvidenceEvaluator.php:evaluate', 'charge_evaluated', 'A', [
                'usage_date' => $usageDate,
                'device_raw' => $deviceIdRaw,
                'device_hash' => $identity['device_hash'] ?? null,
                'identity_status' => $identity['status'] ?? null,
                'category' => $category,
                'item_desc' => $itemDesc,
                'amount' => $amount,
                'cycle' => $cadence['mode'] ?? null,
                'cycle_days' => $cadence['cycle_days'] ?? null,
                'next_due' => $cadence['next_due_date'] ?? null,
                'as_qty' => $asQty,
                'as_snapshot' => $cadence['snapshot_at'] ?? null,
                'as_name' => $cadence['service_name'] ?? null,
                'lifecycle_source' => $lifeSrc,
                'revoked_at' => $revokedAt,
                'remove_date' => $removeDate,
                'expected_end' => $expectedEnd,
                'billing_verdict' => $billingVerdict,
                'verdict' => $verdict,
                'reasons' => $reasons,
                'as_positive_while_removed' => ($removeDate !== null && $asQty !== null && (float) $asQty > 0),
                'hypothesis_D_daily_end_eq_nextdue_minus_1' => (
                    ($cadence['mode'] ?? '') === 'daily'
                    && $expectedEnd !== null
                    && !empty($cadence['next_due_date'])
                    && $expectedEnd === date('Y-m-d', strtotime((string) $cadence['next_due_date'] . ' -1 day'))
                ),
            ]);
        }
        // #endregion

        return [
            'usage_id' => (int) ($row->id ?? 0),
            'usage_date' => $usageDate,
            'account' => $identity['account'] ?? $tenantId,
            'device_id' => $identity['device_hash'] ?? $deviceIdRaw,
            'device_name' => $identity['device_name'],
            'category' => $category,
            'category_label' => ChargeCategoryResolver::label($category),
            'item_desc' => $itemDesc,
            'amount' => $amount,
            'debit_evidence' => $debitEvidence,
            'billing_verdict' => $billingVerdict,
            'verdict' => $verdict,
            'expected_billing_end' => $expectedEnd,
            'registered_at' => $lifecycle['registered_at'] ?? null,
            'revoked_at' => $lifecycle['revoked_at'] ?? null,
            'cycle' => $cadence['mode'],
            'cycle_days' => $cadence['cycle_days'],
            'next_due_date' => $cadence['next_due_date'],
            'overbill_amount' => $verdict === 'confirmed' || $verdict === 'probable' ? $amount : 0.0,
            'confidence_reasons' => $reasons,
            'evidence' => [
                'identity' => $identity,
                'lifecycle' => $lifecycle,
                'cadence' => $cadence,
                'packs_used_raw' => $packsRaw,
                'packs_used_parsed' => json_decode((string) ($row->packs_used_parsed ?? ''), true),
                'raw_row' => json_decode((string) ($row->raw_row ?? ''), true),
                'reversal' => $reversed,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $lifecycle
     * @param array<string, mixed> $cadence
     */
    private static function expectedBillingEnd(?array $lifecycle, array $cadence, string $usageDate): ?string
    {
        if ($lifecycle === null || $lifecycle['remove_date'] === null) {
            return null;
        }

        $removeDate = $lifecycle['remove_date'];
        if ($cadence['mode'] === 'daily') {
            return $removeDate;
        }

        $registeredAt = $lifecycle['registered_at'];
        $nextDue = $cadence['next_due_date'];
        $cycleDays = (int) $cadence['cycle_days'];

        $end = BillingPeriodCalculator::deviceExpectedEnd(
            $registeredAt,
            $removeDate . ' 12:00:00',
            $cycleDays,
            $nextDue
        );

        if ($end !== null) {
            return $end;
        }

        if ($registeredAt !== null) {
            return BillingPeriodCalculator::deviceExpectedEnd(
                $registeredAt,
                $removeDate . ' 12:00:00',
                $cycleDays,
                null
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $identity
     * @param array<string, mixed>|null $lifecycle
     * @param array<string, mixed> $cadence
     * @param list<string> $reasons
     */
    private static function gradeVerdict(
        array $identity,
        ?array $lifecycle,
        array $cadence,
        string $debitEvidence,
        string $billingVerdict,
        float $amount,
        bool $coverageComplete,
        array $reasons
    ): string {
        if ($billingVerdict === 'reversed') {
            return 'reversed';
        }

        if ($billingVerdict !== 'after_expected_end') {
            if ($billingVerdict === 'within_period') {
                return 'not_overbilled';
            }

            return 'review_required';
        }

        if ($amount <= 0) {
            $reasons[] = 'zero_amount';
            return 'review_required';
        }

        if ($debitEvidence !== 'present') {
            $reasons[] = 'debit_evidence_weak';
            return 'review_required';
        }

        $identityOk = in_array($identity['status'], ['exact', 'unique_prefix', 'account_disambiguated'], true);
        if (!$identityOk) {
            $reasons[] = 'identity_not_proven';
            return 'review_required';
        }

        if ($lifecycle === null || $lifecycle['remove_date'] === null) {
            return 'review_required';
        }

        $confirmedLifecycle = in_array($lifecycle['confidence'], ['high', 'medium'], true);
        $confirmedCadence = in_array($cadence['confidence'], ['high', 'medium'], true);

        if ($confirmedLifecycle && $confirmedCadence && $identityOk) {
            return 'confirmed';
        }

        $reasons[] = 'evidence_incomplete';
        return 'review_required';
    }

  /**
     * @return array<string, mixed>|null
     */
    private static function findReversal(
        object $row,
        string $usageDate,
        string $tenantId,
        ?string $deviceHash
    ): ?array
    {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return null;
        }

        $query = CanonicalUsage::query()
            ->where('usage_date', '>=', $usageDate)
            ->where('amount', '<', 0);

        if ($deviceHash) {
            $query->where('device_id', $deviceHash);
        } elseif ($tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        } else {
            return null;
        }

        $match = $query->orderBy('usage_date')->first();
        if ($match) {
            return [
                'usage_id' => $match->id,
                'usage_date' => $match->usage_date,
                'amount' => (float) $match->amount,
            ];
        }

        if (Capsule::schema()->hasTable('cb_credit_purchases')
            && Capsule::schema()->hasColumn('cb_credit_purchases', 'record_type')) {
            $refund = Capsule::table('cb_credit_purchases')
                ->where('record_type', 'refund')
                ->where('purchased_at', '>=', $usageDate . ' 00:00:00')
                ->orderBy('purchased_at')
                ->first();
            if ($refund) {
                return [
                    'purchase_id' => $refund->id,
                    'purchased_at' => $refund->purchased_at,
                    'amount' => (float) $refund->credit_amount,
                ];
            }
        }

        return null;
    }

    // #region agent log
    private static function debugLog(string $location, string $message, string $hypothesisId, array $data): void
    {
        $payload = [
            'sessionId' => 'd2324a',
            'runId' => 'pre-fix',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-d2324a.log', json_encode($payload) . "\n", FILE_APPEND);
    }
    // #endregion
}
