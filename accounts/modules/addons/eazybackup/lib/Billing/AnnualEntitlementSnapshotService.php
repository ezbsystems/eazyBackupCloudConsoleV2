<?php

declare(strict_types=1);

namespace EazyBackup\Billing;

use InvalidArgumentException;

/**
 * Pure service to build ledger rows from input.
 * Uses AnnualCycleWindow and AnnualEntitlementDecision for cycle and entitlement logic.
 */
class AnnualEntitlementSnapshotService
{
    /**
     * Build a ledger row by merging input with cycle window and entitlement decision.
     *
     * Required input keys:
     *   - next_due (string): Next due date in Y-m-d format (e.g. 2026-12-15)
     *
     * Optional input keys (default 0): usage_qty, config_qty, max_paid_qty.
     * Other keys (e.g. service_id, client_id, username, config_id) are passed through.
     *
     * @param array<string, mixed> $in Input row (must include next_due as string)
     * @return array<string, mixed> Merged row including: cycle_start, cycle_end, status,
     *         recommended_delta, recommended_max_paid_qty, plus all input keys
     * @throws InvalidArgumentException if next_due is missing or not a string
     */
    public function buildLedgerRow(array $in): array
    {
        if (!isset($in['next_due'])) {
            throw new InvalidArgumentException('next_due is required');
        }
        if (!is_string($in['next_due'])) {
            throw new InvalidArgumentException('next_due must be a string');
        }

        $cycle = AnnualCycleWindow::fromNextDueDate($in['next_due']);

        $usageQty = (int) ($in['usage_qty'] ?? 0);
        $configQty = (int) ($in['config_qty'] ?? 0);
        $maxPaidQty = (int) ($in['max_paid_qty'] ?? 0);

        $decision = new AnnualEntitlementDecision();
        $eval = $decision->evaluate($usageQty, $configQty, $maxPaidQty);

        $recommendedDelta = $eval['delta_to_charge'];
        $recommendedMaxPaidQty = $eval['status'] === AnnualEntitlementDecision::STATUS_PRORATION_REQUIRED
            ? $usageQty
            : $maxPaidQty;

        return array_merge($in, $cycle, [
            'status' => $eval['status'],
            'recommended_delta' => $recommendedDelta,
            'recommended_max_paid_qty' => $recommendedMaxPaidQty,
        ]);
    }
}
