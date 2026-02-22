<?php

declare(strict_types=1);

namespace EazyBackup\Billing;

class AnnualEntitlementDecision
{
    public const STATUS_PRORATION_REQUIRED = 'PRORATION_REQUIRED';
    public const STATUS_WITHIN_ENTITLEMENT = 'WITHIN_ENTITLEMENT';

    /**
     * @param int $usageQty   Current usage count
     * @param int $configQty  Reserved for future use (tiered logic)
     * @param int $maxPaidQty Max paid entitlement ceiling
     * @return array{status: string, delta_to_charge: int}
     */
    public function evaluate(int $usageQty, int $configQty, int $maxPaidQty): array
    {
        $usageQty = max(0, $usageQty);
        $maxPaidQty = max(0, $maxPaidQty);

        if ($usageQty > $maxPaidQty) {
            return [
                'status' => self::STATUS_PRORATION_REQUIRED,
                'delta_to_charge' => $usageQty - $maxPaidQty,
            ];
        }
        return [
            'status' => self::STATUS_WITHIN_ENTITLEMENT,
            'delta_to_charge' => 0,
        ];
    }
}
