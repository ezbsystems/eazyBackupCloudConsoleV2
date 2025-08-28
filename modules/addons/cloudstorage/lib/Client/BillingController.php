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