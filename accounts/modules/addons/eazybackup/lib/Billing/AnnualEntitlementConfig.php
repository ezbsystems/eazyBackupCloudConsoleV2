<?php

declare(strict_types=1);

namespace EazyBackup\Billing;

class AnnualEntitlementConfig
{
    private const MODE = 'manual';

    /** @var int[] */
    private const BILLABLE_CONFIG_IDS = [67, 88, 89, 91, 60, 97, 99, 102];

    public static function mode(): string
    {
        return self::MODE;
    }

    /**
     * @return int[]
     */
    public static function billableConfigIds(): array
    {
        return self::BILLABLE_CONFIG_IDS;
    }
}
