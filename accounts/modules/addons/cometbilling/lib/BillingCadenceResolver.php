<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Observed billing cadence from active-services snapshots and charge history.
 */
class BillingCadenceResolver
{
    /** @var list<string>|null */
    private static ?array $snapshotList = null;

    /** @var array<string, ?string> */
    private static array $snapshotNearCache = [];

    /** @var array<string, array{found: bool, billing_cycle_days: ?int, next_due_date: ?string, snapshot_at: ?string}> */
    private static array $portalAnchorCache = [];

    /** @var array<string, bool> */
    private static array $dailyCadenceCache = [];

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

        // Expensive charge-history scan — only when portal cadence is unknown.
        $observedDaily = false;
        if (!$portal['found']) {
            $observedDaily = self::observedDailyCadence($usageDate, $itemDesc, $account, $deviceHash);
        }

        $cycleDays = (int) ($portal['billing_cycle_days'] ?? 30);
        if ($cycleDays <= 0) {
            $cycleDays = 30;
        }

        $mode = $cycleDays <= 1 ? 'daily' : 'monthly';
        $confidence = $portal['found'] ? 'high' : 'low';

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

    public static function clearCache(): void
    {
        self::$snapshotList = null;
        self::$snapshotNearCache = [];
        self::$portalAnchorCache = [];
        self::$dailyCadenceCache = [];
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
        $cacheKey = $usageDate . '|' . ($deviceHash ?? '') . '|' . ($account ?? '');
        if (isset(self::$portalAnchorCache[$cacheKey])) {
            return self::$portalAnchorCache[$cacheKey];
        }

        if (!Capsule::schema()->hasTable('cb_active_services')) {
            return self::$portalAnchorCache[$cacheKey] = [
                'found' => false,
                'billing_cycle_days' => null,
                'next_due_date' => null,
                'snapshot_at' => null,
            ];
        }

        $snapshotAt = self::findSnapshotNearCached($usageDate . ' 12:00:00');
        if ($snapshotAt === null) {
            return self::$portalAnchorCache[$cacheKey] = [
                'found' => false,
                'billing_cycle_days' => null,
                'next_due_date' => null,
                'snapshot_at' => null,
            ];
        }

        $row = null;
        if ($deviceHash !== null && $deviceHash !== '') {
            $short = substr($deviceHash, 0, 6);
            $row = Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where(function ($q) use ($deviceHash, $short) {
                    $q->where('device_id', $deviceHash)
                        ->orWhere('device_id', $short);
                })
                ->orderBy('id', 'desc')
                ->first();
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
            return self::$portalAnchorCache[$cacheKey] = [
                'found' => false,
                'billing_cycle_days' => null,
                'next_due_date' => null,
                'snapshot_at' => $snapshotAt,
            ];
        }

        return self::$portalAnchorCache[$cacheKey] = [
            'found' => true,
            'billing_cycle_days' => (int) $row->billing_cycle_days,
            'next_due_date' => (string) $row->next_due_date,
            'snapshot_at' => $snapshotAt,
        ];
    }

    private static function findSnapshotNearCached(string $targetDatetime): ?string
    {
        $day = substr($targetDatetime, 0, 10);
        if (array_key_exists($day, self::$snapshotNearCache)) {
            return self::$snapshotNearCache[$day];
        }

        if (self::$snapshotList === null) {
            if (!Capsule::schema()->hasTable('cb_active_services')) {
                self::$snapshotList = [];
            } else {
                $snapshots = Capsule::table('cb_active_services')
                    ->select('pulled_at')
                    ->groupBy('pulled_at')
                    ->orderBy('pulled_at', 'desc')
                    ->pluck('pulled_at');
                self::$snapshotList = is_array($snapshots) ? $snapshots : $snapshots->toArray();
            }
        }

        if (self::$snapshotList === []) {
            return self::$snapshotNearCache[$day] = null;
        }

        $targetTs = strtotime($targetDatetime);
        $best = null;
        $bestDiff = PHP_INT_MAX;
        foreach (self::$snapshotList as $pulledAt) {
            $diff = abs(strtotime((string) $pulledAt) - $targetTs);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = (string) $pulledAt;
            }
        }

        if ($best === null || $bestDiff > 48 * 3600) {
            return self::$snapshotNearCache[$day] = null;
        }

        return self::$snapshotNearCache[$day] = $best;
    }

    private static function observedDailyCadence(
        string $usageDate,
        ?string $itemDesc,
        ?string $account,
        ?string $deviceHash
    ): bool {
        $cacheKey = ($deviceHash ?? '') . '|' . ($account ?? '') . '|' . substr((string) $itemDesc, 0, 30);
        if (isset(self::$dailyCadenceCache[$cacheKey])) {
            return self::$dailyCadenceCache[$cacheKey];
        }

        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return self::$dailyCadenceCache[$cacheKey] = false;
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
            return self::$dailyCadenceCache[$cacheKey] = false;
        }

        $dates = $query->orderBy('usage_date')->pluck('usage_date');
        $dates = is_array($dates) ? $dates : $dates->toArray();
        if (count($dates) < 3) {
            return self::$dailyCadenceCache[$cacheKey] = false;
        }

        $unique = array_values(array_unique($dates));
        if (count($unique) < 3) {
            return self::$dailyCadenceCache[$cacheKey] = false;
        }

        $gaps = [];
        for ($i = 1; $i < count($unique); $i++) {
            $gaps[] = (strtotime((string) $unique[$i]) - strtotime((string) $unique[$i - 1])) / 86400;
        }

        $avgGap = array_sum($gaps) / count($gaps);

        return self::$dailyCadenceCache[$cacheKey] = ($avgGap <= 2.0);
    }
}
