<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

use WHMCS\Database\Capsule;

final class PricingCalculator
{
    /** Return price for +N units of a config option for a service in client's currency/cycle. */
    public static function priceDeltaForConfigOption(int $serviceId, int $optionRelid, int $units): float
    {
        if ($units <= 0) return 0.0;
        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first(['userid','billingcycle']);
        if (!$svc) return 0.0;
        $cycleMap = [
            'Monthly' => 'monthly', 'Annually' => 'annually', 'Yearly' => 'annually', 'Biennially' => 'biennially', 'Triennially' => 'triennially',
            'Quarterly' => 'quarterly', 'Semi-Annually' => 'semiannually', 'Semiannually' => 'semiannually'
        ];
        $cycleKey = $cycleMap[$svc->billingcycle] ?? 'monthly';
        $clientCurrency = (int)(getCurrency($svc->userid)['id'] ?? 1);
        $row = Capsule::table('tblpricing')
            ->where('type','configoptions')
            ->where('relid',$optionRelid)
            ->where('currency',$clientCurrency)
            ->first([$cycleKey.' as amt']);
        $unit = (float)($row->amt ?? 0.0);
        return max(0.0, $unit) * $units;
    }
}


