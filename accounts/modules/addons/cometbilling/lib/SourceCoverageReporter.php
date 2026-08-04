<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Source coverage windows for audit-grade findings.
 */
class SourceCoverageReporter
{
    /**
     * @return array<string, mixed>
     */
    public static function report(string $fromDate, string $toDate): array
    {
        $usageMin = self::usageMinDate('usage_date');
        $usageMax = self::usageMaxDate('usage_date');
        $activeMin = self::minSnapshotDate();
        $activeMax = self::maxSnapshotDate();
        $purchaseMin = self::minDate('cb_credit_purchases', 'purchased_at', true);
        $purchaseMax = self::maxDate('cb_credit_purchases', 'purchased_at', true);
        $inventoryMin = self::minDate('cb_server_device_inventory', 'snapshot_date');
        $inventoryMax = self::maxDate('cb_server_device_inventory', 'snapshot_date');

        $completeOverlap = true;
        $gaps = [];

        if ($usageMin === null || $usageMin > $fromDate || $usageMax === null || $usageMax < $toDate) {
            $completeOverlap = false;
            $gaps[] = 'usage_history_incomplete';
        }
        if ($activeMin === null || $activeMin > $fromDate || $activeMax === null || $activeMax < $toDate) {
            $completeOverlap = false;
            $gaps[] = 'active_services_incomplete';
        }
        if ($purchaseMin === null) {
            $completeOverlap = false;
            $gaps[] = 'bill_history_missing';
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'complete_overlap' => $completeOverlap,
            'gaps' => $gaps,
            'usage' => ['min' => $usageMin, 'max' => $usageMax],
            'active_services' => ['min' => $activeMin, 'max' => $activeMax],
            'purchases' => ['min' => $purchaseMin, 'max' => $purchaseMax],
            'inventory' => ['min' => $inventoryMin, 'max' => $inventoryMax],
        ];
    }

    private static function usageMinDate(string $column): ?string
    {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return null;
        }
        $val = CanonicalUsage::query()->min($column);
        if ($val === null) {
            return null;
        }

        return (string) $val;
    }

    private static function usageMaxDate(string $column): ?string
    {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return null;
        }
        $val = CanonicalUsage::query()->max($column);
        if ($val === null) {
            return null;
        }

        return (string) $val;
    }

    private static function minDate(string $table, string $column, bool $datetime = false): ?string
    {
        if (!Capsule::schema()->hasTable($table)) {
            return null;
        }
        $val = Capsule::table($table)->min($column);
        if ($val === null) {
            return null;
        }
        if ($datetime) {
            return substr((string) $val, 0, 10);
        }

        return (string) $val;
    }

    private static function maxDate(string $table, string $column, bool $datetime = false): ?string
    {
        if (!Capsule::schema()->hasTable($table)) {
            return null;
        }
        $val = Capsule::table($table)->max($column);
        if ($val === null) {
            return null;
        }
        if ($datetime) {
            return substr((string) $val, 0, 10);
        }

        return (string) $val;
    }

    private static function minSnapshotDate(): ?string
    {
        if (!Capsule::schema()->hasTable('cb_active_services')) {
            return null;
        }
        $val = Capsule::table('cb_active_services')->min('pulled_at');

        return $val ? substr((string) $val, 0, 10) : null;
    }

    private static function maxSnapshotDate(): ?string
    {
        if (!Capsule::schema()->hasTable('cb_active_services')) {
            return null;
        }
        $val = Capsule::table('cb_active_services')->max('pulled_at');

        return $val ? substr((string) $val, 0, 10) : null;
    }
}
