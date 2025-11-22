<?php
declare(strict_types=1);

namespace EazyBackup\Notifications;

final class StorageThresholds
{
    public const TIB = 1099511627776; // 2^40

    public static function bytesToTiB(float $bytes): float
    {
        return $bytes / self::TIB;
    }

    /**
     * Return list of milestone TiB K to check from paidTiB upwards given current usage.
     */
    public static function milestonesToCheck(int $paidTiB, float $usageTiB): array
    {
        $maxK = max($paidTiB, (int)ceil($usageTiB));
        $out = [];
        for ($k = $paidTiB; $k <= $maxK; $k++) { $out[] = $k; }
        return $out;
    }

    public static function thresholdTiBForK(int $k, int $percent): float
    {
        return ($percent / 100.0) * (float)$k;
    }
}


