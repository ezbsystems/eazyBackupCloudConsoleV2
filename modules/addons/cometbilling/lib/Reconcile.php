<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

class Reconcile
{
    public static function chargesByType(string $fromDate, string $toDate): array
    {
        $rows = Capsule::table('cb_credit_usage')
            ->select('item_type', Capsule::raw('SUM(amount) as amt'))
            ->whereBetween('usage_date', [$fromDate, $toDate])
            ->groupBy('item_type')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->item_type] = (float)$r->amt;
        }
        return $out;
    }

    public static function purchasesTotal(string $fromDate, string $toDate): float
    {
        $sum = Capsule::table('cb_credit_purchases')
            ->whereBetween(Capsule::raw('DATE(purchased_at)'), [$fromDate, $toDate])
            ->sum(Capsule::raw('credit_amount + bonus_credit'));
        return (float)$sum;
    }

    // Placeholder for future in-depth checks
    public static function detectCatchUpCharges(string $fromDate, string $toDate): array
    {
        $rows = Capsule::table('cb_credit_usage')
            ->whereBetween('usage_date', [$fromDate, $toDate])
            ->whereNotNull('posted_at')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $lag = strtotime((string)$r->posted_at) - strtotime((string)$r->usage_date . ' 00:00:00');
            if ($lag > 7 * 86400) { // > 7 days
                $out[] = $r;
            }
        }
        return $out;
    }
}


