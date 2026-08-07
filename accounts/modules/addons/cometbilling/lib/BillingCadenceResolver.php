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

    /** @var array<string, list<string>> */
    private static array $snapshotsNearCache = [];

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
     *   observed_daily: bool,
     *   service_name: ?string,
     *   service_quantity: ?float,
     *   service_amount: ?float,
     *   service_unit_cost: ?float
     * }
     */
    public static function resolve(
        string $usageDate,
        string $category,
        ?string $account,
        ?string $deviceHash,
        ?string $itemDesc
    ): array {
        $portal = self::findPortalAnchor($usageDate, $category, $account, $deviceHash, $itemDesc);

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
            'service_name' => $portal['service_name'] ?? null,
            'service_quantity' => $portal['quantity'] ?? null,
            'service_amount' => $portal['amount'] ?? null,
            'service_unit_cost' => $portal['unit_cost'] ?? null,
        ];
    }

    public static function clearCache(): void
    {
        self::$snapshotList = null;
        self::$snapshotsNearCache = [];
        self::$portalAnchorCache = [];
        self::$dailyCadenceCache = [];
    }

    /**
     * @return array{
     *   found: bool,
     *   billing_cycle_days: ?int,
     *   next_due_date: ?string,
     *   snapshot_at: ?string,
     *   service_name?: ?string,
     *   quantity?: ?float,
     *   amount?: ?float,
     *   unit_cost?: ?float
     * }
     */
    private static function findPortalAnchor(
        string $usageDate,
        string $category,
        ?string $account,
        ?string $deviceHash,
        ?string $itemDesc
    ): array {
        $cacheKey = $usageDate . '|' . $category . '|' . ($deviceHash ?? '') . '|' . ($account ?? '');
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

        $snapshots = self::findSnapshotsNearCached($usageDate . ' 12:00:00');
        if ($snapshots === []) {
            return self::$portalAnchorCache[$cacheKey] = [
                'found' => false,
                'billing_cycle_days' => null,
                'next_due_date' => null,
                'snapshot_at' => null,
            ];
        }

        $candidates = [];
        foreach ($snapshots as $snapshotAt) {
            $row = self::findCategoryRowInSnapshot($snapshotAt, $deviceHash, $account, $itemDesc, $category);
            if ($row === null) {
                continue;
            }
            $candidates[] = [
                'row' => $row,
                'snapshot_at' => $snapshotAt,
                'next_due_date' => (string) $row->next_due_date,
            ];
        }

        $selected = self::selectPreRollAnchor($candidates, $usageDate);
        if ($selected === null) {
            return self::$portalAnchorCache[$cacheKey] = [
                'found' => false,
                'billing_cycle_days' => null,
                'next_due_date' => null,
                'snapshot_at' => $snapshots[0] ?? null,
            ];
        }

        $row = $selected['row'];

        return self::$portalAnchorCache[$cacheKey] = [
            'found' => true,
            'billing_cycle_days' => (int) $row->billing_cycle_days,
            'next_due_date' => $selected['next_due_date'],
            'snapshot_at' => $selected['snapshot_at'],
            'service_name' => isset($row->service_name) ? (string) $row->service_name : null,
            'quantity' => isset($row->quantity) ? (float) $row->quantity : null,
            'amount' => isset($row->amount) ? (float) $row->amount : null,
            'unit_cost' => isset($row->unit_cost) ? (float) $row->unit_cost : null,
        ];
    }

    /**
     * Prefer pre-roll next_due (>= usage_date); among ties pick nearest snapshot.
     *
     * @param list<array{row: object, snapshot_at: string, next_due_date: string}> $candidates
     * @return array{row: object, snapshot_at: string, next_due_date: string}|null
     */
    private static function selectPreRollAnchor(array $candidates, string $usageDate): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $usageTs = strtotime($usageDate . ' 12:00:00');
        $preRoll = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['next_due_date'] >= $usageDate
        ));

        if ($preRoll !== []) {
            usort($preRoll, static function (array $a, array $b) use ($usageTs): int {
                $dueCmp = strcmp($a['next_due_date'], $b['next_due_date']);
                if ($dueCmp !== 0) {
                    return $dueCmp;
                }

                $diffA = abs(strtotime($a['snapshot_at']) - $usageTs);
                $diffB = abs(strtotime($b['snapshot_at']) - $usageTs);
                if ($diffA !== $diffB) {
                    return $diffA <=> $diffB;
                }

                return strcmp($b['snapshot_at'], $a['snapshot_at']);
            });

            return $preRoll[0];
        }

        $pool = $candidates;
        usort($pool, static function (array $a, array $b) use ($usageTs): int {
            $diffA = abs(strtotime($a['snapshot_at']) - $usageTs);
            $diffB = abs(strtotime($b['snapshot_at']) - $usageTs);
            if ($diffA !== $diffB) {
                return $diffA <=> $diffB;
            }

            return strcmp($b['snapshot_at'], $a['snapshot_at']);
        });

        return $pool[0];
    }

    private static function findCategoryRowInSnapshot(
        string $snapshotAt,
        ?string $deviceHash,
        ?string $account,
        ?string $itemDesc,
        string $category
    ): ?object {
        $rows = self::fetchDeviceRowsInSnapshot($snapshotAt, $deviceHash, $account, $itemDesc);
        if ($rows === []) {
            return null;
        }

        $matched = array_values(array_filter(
            $rows,
            static fn (object $row): bool => self::rowMatchesCategory($row, $category)
        ));

        if ($matched !== []) {
            return $matched[0];
        }

        return $rows[0] ?? null;
    }

    /**
     * @return list<object>
     */
    private static function normalizeRows(mixed $rows): array
    {
        if (is_array($rows)) {
            return $rows;
        }
        if (is_object($rows) && method_exists($rows, 'all')) {
            return $rows->all();
        }

        return [];
    }

    /**
     * @return list<object>
     */
    private static function fetchDeviceRowsInSnapshot(
        string $snapshotAt,
        ?string $deviceHash,
        ?string $account,
        ?string $itemDesc
    ): array {
        if ($deviceHash !== null && $deviceHash !== '') {
            $short = substr($deviceHash, 0, 6);
            $rows = Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where(function ($q) use ($deviceHash, $short) {
                    $q->where('device_id', $deviceHash)
                        ->orWhere('device_id', $short);
                })
                ->orderBy('id', 'desc')
                ->get();
            $rows = self::normalizeRows($rows);
            if ($rows !== []) {
                return $rows;
            }

            return self::normalizeRows(Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where('service_name', 'like', '%Device ' . $short . '%')
                ->orderBy('id', 'desc')
                ->get());
        }

        if ($account !== null && $account !== '') {
            return self::normalizeRows(Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where('service_name', 'like', '%' . $account . '%')
                ->orderBy('id', 'desc')
                ->get());
        }

        if ($itemDesc !== null) {
            $needle = substr($itemDesc, 0, 40);

            return self::normalizeRows(Capsule::table('cb_active_services')
                ->where('pulled_at', $snapshotAt)
                ->where('service_name', 'like', '%' . $needle . '%')
                ->orderBy('id', 'desc')
                ->get());
        }

        return [];
    }

    private static function rowMatchesCategory(object $row, string $category): bool
    {
        $serviceName = (string) ($row->service_name ?? '');
        $type = self::activeServiceType($row);

        if ($category === 'devices') {
            if ($type === 'device') {
                return true;
            }
            if ($type === 'booster') {
                return false;
            }

            return str_contains(strtolower($serviceName), 'device')
                && !str_contains(strtolower($serviceName), 'booster');
        }

        if ($type === 'booster') {
            return ChargeCategoryResolver::fromServiceName($serviceName) === $category;
        }

        return ChargeCategoryResolver::fromServiceName($serviceName) === $category;
    }

    private static function activeServiceType(object $row): ?string
    {
        $extra = $row->extra ?? null;
        if (is_string($extra)) {
            $decoded = json_decode($extra, true);

            return is_array($decoded) ? ($decoded['Type'] ?? null) : null;
        }
        if (is_array($extra)) {
            return $extra['Type'] ?? null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function findSnapshotsNearCached(string $targetDatetime): array
    {
        $day = substr($targetDatetime, 0, 10);
        if (array_key_exists($day, self::$snapshotsNearCache)) {
            return self::$snapshotsNearCache[$day];
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
            return self::$snapshotsNearCache[$day] = [];
        }

        $targetTs = strtotime($targetDatetime);
        $within = [];
        foreach (self::$snapshotList as $pulledAt) {
            $diff = abs(strtotime((string) $pulledAt) - $targetTs);
            if ($diff <= 48 * 3600) {
                $within[] = [
                    'pulled_at' => (string) $pulledAt,
                    'diff' => $diff,
                ];
            }
        }

        usort($within, static fn (array $a, array $b): int => $a['diff'] <=> $b['diff']);

        return self::$snapshotsNearCache[$day] = array_map(
            static fn (array $item): string => $item['pulled_at'],
            $within
        );
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
        $query = CanonicalUsage::query()
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
