<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Observed billing cadence from active-services snapshots and charge history.
 */
class BillingCadenceResolver
{
    /**
     * @return array{
     *   cycle_days: int,
     *   next_due_date: ?string,
     *   mode: string,
     *   confidence: string,
     *   snapshot_at: ?string,
     *   observed_daily: bool
     * }
     */
    public static function resolve(
        string $usageDate,
        string $category,
        ?string $account,
        ?string $deviceHash,
        ?string $itemDesc
    ): array {
        $portal = self::findPortalAnchor($usageDate, $account, $deviceHash, $itemDesc);
        $observedDaily = self::observedDailyCadence($usageDate, $itemDesc, $account, $deviceHash);

        $cycleDays = (int) ($portal['billing_cycle_days'] ?? 30);
        if ($cycleDays <= 0) {
            $cycleDays = 30;
        }

        $mode = $cycleDays <= 1 ? 'daily' : 'monthly';
        $confidence = $portal['found'] ? 'high' : 'low';

        if ($observedDaily && $mode === 'monthly' && $portal['found'] && $cycleDays <= 1) {
            $mode = 'daily';
            $confidence = 'high';
        }

        if (!$portal['found'] && $observedDaily) {
            $mode = 'daily';
            $cycleDays = 1;
            $confidence = 'medium';
        }

        if ($category === 'account_plan') {
            $mode = $cycleDays <= 1 ? 'daily' : 'monthly';
        }

        return [
            'cycle_days' => $cycleDays,
            'next_due_date' => $portal['next_due_date'],
            'mode' => $mode,
            'confidence' => $confidence,
            'snapshot_at' => $portal['snapshot_at'],
            'observed_daily' => $observedDaily,
        ];
    }

    /**
     * @return array{found: bool, billing_cycle_days: ?int, next_due_date: ?string, snapshot_at: ?string}
     */
    private static function findPortalAnchor(
        string $usageDate,
        ?string $account,
        ?string $deviceHash,
        ?string $itemDesc
    ): array {
        if (!Capsule::schema()->hasTable('cb_active_services')) {
            return ['found' => false, 'billing_cycle_days' => null, 'next_due_date' => null, 'snapshot_at' => null];
        }

        $snapshotAt = PortalUsageExtractor::findSnapshotNear($usageDate . ' 12:00:00');
        if ($snapshotAt === null) {
            return ['found' => false, 'billing_cycle_days' => null, 'next_due_date' => null, 'snapshot_at' => null];
        }

        $row = null;
        if ($deviceHash !== null && $deviceHash !== '') {
            $short = substr($deviceHash, 0, 6);
            $row = Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where('device_id', $deviceHash)
                ->orderBy('id', 'desc')
                ->first();
            if ($row === null) {
                $row = Capsule::table('cb_active_services')
                    ->where('pulled_at', $snapshotAt)
                    ->where('device_id', $short)
                    ->orderBy('id', 'desc')
                    ->first();
            }
            if ($row === null) {
                $row = Capsule::table('cb_active_services')
                    ->where('pulled_at', $snapshotAt)
                    ->where('service_name', 'like', '%Device ' . $short . '%')
                    ->orderBy('id', 'desc')
                    ->first();
            }
        } elseif ($account !== null && $account !== '') {
            $row = Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where('service_name', 'like', '%' . $account . '%')
                ->orderBy('id', 'desc')
                ->first();
        } elseif ($itemDesc !== null) {
            $needle = substr($itemDesc, 0, 40);
            $row = Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where('service_name', 'like', '%' . $needle . '%')
                ->orderBy('id', 'desc')
                ->first();
        }
        if ($row === null) {
            return ['found' => false, 'billing_cycle_days' => null, 'next_due_date' => null, 'snapshot_at' => $snapshotAt];
        }

        return [
            'found' => true,
            'billing_cycle_days' => (int) $row->billing_cycle_days,
            'next_due_date' => (string) $row->next_due_date,
            'snapshot_at' => $snapshotAt,
        ];
    }

    private static function observedDailyCadence(
        string $usageDate,
        ?string $itemDesc,
        ?string $account,
        ?string $deviceHash
    ): bool {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return false;
        }

        $from = date('Y-m-d', strtotime($usageDate . ' -14 days'));
        $query = Capsule::table('cb_credit_usage')
            ->whereBetween('usage_date', [$from, $usageDate]);

        if ($deviceHash) {
            $query->where('device_id', $deviceHash);
        } elseif ($itemDesc) {
            $query->where('item_desc', 'like', '%' . substr($itemDesc, 0, 30) . '%');
        } elseif ($account) {
            $query->where('tenant_id', $account);
        } else {
            return false;
        }

        $dates = $query->orderBy('usage_date')->pluck('usage_date')->toArray();
        if (count($dates) < 3) {
            return false;
        }

        $unique = array_values(array_unique($dates));
        if (count($unique) < 3) {
            return false;
        }

        $gaps = [];
        for ($i = 1; $i < count($unique); $i++) {
            $gaps[] = (strtotime($unique[$i]) - strtotime($unique[$i - 1])) / 86400;
        }

        $avgGap = array_sum($gaps) / count($gaps);

        return $avgGap <= 2.0;
    }
}
