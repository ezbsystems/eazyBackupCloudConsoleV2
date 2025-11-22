<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use DateTime;
use WHMCS\Database\Capsule;

class BillingController {

    /**
     * Calculate Monthly Bill.
     *
     * @param integer $userId
     * @param integer $packageId
     *
     * @return array
     */
    public function calculateBillingMonth($userId, $packageId)
    {
        $billingPeriod = [
            'start' => null,
            'end' => null,
        ];

        // Retrieve the next due date for the user's product
        $product = Capsule::table('tblhosting')
            ->where('userid', $userId)
            ->where('packageid', $packageId)
            ->where('domainstatus', 'Active')
            ->first();


        if ($product && !empty($product->nextduedate)) {
            $nextDueDate = new DateTime($product->nextduedate);
            $billingMonthStart = (clone $nextDueDate)->modify('-1 month');
            $billingMonthEnd = (clone $nextDueDate)->modify('-1 day');
            $billingPeriod['start'] = $billingMonthStart->format('Y-m-d');
            $billingPeriod['end'] = $billingMonthEnd->format('Y-m-d');
        }

        return $billingPeriod;
    }

    /**
     * Calculate a rolling display period that always contains today.
     *
     * - Uses the service's anniversary day (from nextduedate if present, otherwise regdate)
     * - Computes the most recent period start that is <= today, and the end as +1 month - 1 day
     * - Returns an additional end_for_queries set to today so charts stay live
     * - Provides safe fallbacks if dates are missing or malformed
     *
     * @param int $userId
     * @param int $packageId
     * @return array { start: string, end: string, end_for_queries: string }
     */
    public function calculateDisplayPeriod(int $userId, int $packageId): array
    {
        $today = new DateTime(date('Y-m-d'));

        // Fetch product with dates used for anniversary logic
        $product = Capsule::table('tblhosting')
            ->where('userid', $userId)
            ->where('packageid', $packageId)
            ->where('domainstatus', 'Active')
            ->select('nextduedate', 'regdate')
            ->first();

        $anchorDay = (int)$today->format('d');
        if ($product) {
            $anchorDay = $this->resolveAnniversaryDay($product, $anchorDay);
        }

        // Build a start candidate on this month's anchor day; if it lands in the future, step back one month
        $start = $this->buildSafeDate((int)$today->format('Y'), (int)$today->format('m'), $anchorDay);
        if ($start > $today) {
            $oneMonthEarlier = (clone $start)->modify('-1 month');
            $start = $this->buildSafeDate((int)$oneMonthEarlier->format('Y'), (int)$oneMonthEarlier->format('m'), $anchorDay);
        }

        // End is one month minus one day from start
        $end = (clone $start)->modify('+1 month')->modify('-1 day');

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'end_for_queries' => $today->format('Y-m-d')
        ];
    }

    /**
     * Compute a subtle overdue notice for the previous period if the service invoice is overdue.
     * Logic: if nextduedate exists and is in the past, surface the previous period range based on nextduedate.
     *
     * @param int $userId
     * @param int $packageId
     * @return string|null
     */
    public function getOverdueNotice(int $userId, int $packageId): ?string
    {
        $product = Capsule::table('tblhosting')
            ->where('userid', $userId)
            ->where('packageid', $packageId)
            ->where('domainstatus', 'Active')
            ->select('nextduedate')
            ->first();

        if (!$product || empty($product->nextduedate)) {
            return null;
        }

        try {
            $nextDue = new DateTime($product->nextduedate);
        } catch (\Exception $e) {
            return null;
        }

        $today = new DateTime(date('Y-m-d'));
        if ($nextDue >= $today) {
            return null; // Not overdue
        }

        $prevStart = (clone $nextDue)->modify('-1 month');
        $prevEnd = (clone $nextDue)->modify('-1 day');

        $fmtStart = $prevStart->format('j M Y');
        $fmtEnd = $prevEnd->format('j M Y');

        return "Overdue: {$fmtStart} → {$fmtEnd} invoice unpaid";
    }

    /**
     * Determine the anniversary day-of-month from product dates with safe fallback.
     *
     * @param object $product
     * @param int $fallbackDay
     * @return int
     */
    private function resolveAnniversaryDay($product, int $fallbackDay): int
    {
        // Prefer nextduedate day if valid
        if (!empty($product->nextduedate)) {
            try {
                $dt = new DateTime($product->nextduedate);
                return (int)$dt->format('d');
            } catch (\Exception $e) {
                // ignore and try regdate
            }
        }

        if (!empty($product->regdate)) {
            try {
                $dt = new DateTime($product->regdate);
                return (int)$dt->format('d');
            } catch (\Exception $e) {
                // ignore and use fallback
            }
        }

        return $fallbackDay;
    }

    /**
     * Build a safe date for a year, month, and target day by clamping to the month's last day if needed.
     *
     * @param int $year
     * @param int $month
     * @param int $targetDay
     * @return DateTime
     */
    private function buildSafeDate(int $year, int $month, int $targetDay): DateTime
    {
        $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        $safeDay = max(1, min($targetDay, $daysInMonth));
        return new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $safeDay));
    }

    /**
     * Get User Balance.
     *
     * @param integer $userId
     * @param integer $packageId
     *
     * @return number
     */
    public function getBalanceAmount($userId, $packageId)
    {
        $result = Capsule::table('tblhosting')
            ->select('amount')
            ->where('userid', $userId)
            ->where('packageid', $packageId)
            ->first();

        return $result ? $result->amount : 0;
    }



    /**
     * Calculate the previous billing period.
     * Assumes monthly billing based on nextduedate logic similar to calculateBillingMonth.
     *
     * @param integer $userId WHMCS User ID
     * @param integer $packageId Package ID
     * @param string $currentReferencedStartDate The start date of the current billing period being viewed (Y-m-d).
     * @return array ['start' => string|null, 'end' => string|null]
     */
    public function getPreviousBillingPeriod(int $userId, int $packageId, string $currentReferencedStartDate): array
    {
        // Fetch product to confirm it's active; billing cycle details could be used for more advanced logic later
        $product = Capsule::table('tblhosting')
            ->where('userid', $userId)
            ->where('packageid', $packageId)
            ->where('domainstatus', 'Active')
            ->select('nextduedate', 'billingcycle') 
            ->first();

        if (!$product) {
            logModuleCall('cloudstorage', __METHOD__, [$userId, $packageId, $currentReferencedStartDate], "Product not found or inactive when calculating previous period.");
            return ['start' => null, 'end' => null];
        }
        
        try {
            $currentStartDt = new \DateTime($currentReferencedStartDate);
            
            // The previous period ends the day before currentReferencedStartDate.
            // The previous period starts one month before currentReferencedStartDate (assuming monthly).
            $prevPeriodEndDt = (clone $currentStartDt)->modify('-1 day');
            $prevPeriodStartDt = (clone $currentStartDt)->modify('-1 month');

            return [
                'start' => $prevPeriodStartDt->format('Y-m-d'),
                'end' => $prevPeriodEndDt->format('Y-m-d'),
            ];
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', __METHOD__, [$userId, $packageId, $currentReferencedStartDate, $e->getMessage()], "Exception calculating previous period.");
            return ['start' => null, 'end' => null];
        }
    }

}