<?php
namespace CometBilling;

/**
 * Compares canonical Bill History spend between two date ranges plus Active Services snapshots.
 */
final class PeriodCompareReport
{
    public const DRIVER_NEW_IN_B = 'new_in_b';
    public const DRIVER_GONE_IN_A = 'gone_in_a';
    public const DRIVER_CONTINUING = 'continuing';

    public const DETAIL_ROW_CAP = 500;
    public const DELTA_FLOOR = 0.01;

    /** @var list<string> */
    public const SPEND_CATEGORIES = [
        'devices',
        'hyperv_vms',
        'vmware_vms',
        'proxmox_vms',
        'disk_image',
        'mssql',
        'm365_accounts',
        'account_plan',
        'other',
    ];

    /** @var list<string> */
    private const SNAPSHOT_COMPARE_KEYS = [
        'devices',
        'hyperv_vms',
        'vmware_vms',
        'proxmox_vms',
        'disk_image',
        'mssql',
        'm365_accounts',
        'account_fees',
        'other_boosters',
    ];

    /**
     * @return array{
     *   period_a: array{from: string, to: string},
     *   period_b: array{from: string, to: string},
     *   spend: array<string, mixed>,
     *   drivers: array<string, mixed>,
     *   snapshots: array<string, mixed>
     * }
     */
    public static function report(
        string $fromA,
        string $toA,
        string $fromB,
        string $toB,
        ?string $bucketFilter = null
    ): array {
        $periodA = self::aggregatePeriod($fromA, $toA);
        $periodB = self::aggregatePeriod($fromB, $toB);

        $spend = self::buildSpendSummary($periodA, $periodB);
        $drivers = self::buildDrivers($periodA, $periodB, $bucketFilter);
        $snapshots = self::buildSnapshotCompare($toA, $toB);

        return [
            'period_a' => ['from' => $fromA, 'to' => $toA],
            'period_b' => ['from' => $fromB, 'to' => $toB],
            'spend' => $spend,
            'drivers' => $drivers,
            'snapshots' => $snapshots,
        ];
    }

    /**
     * Default Period A / B = prior two full calendar months (UTC).
     *
     * @return array{a: array{from: string, to: string}, b: array{from: string, to: string}}
     */
    public static function defaultRanges(): array
    {
        $currentMonthStart = gmdate('Y-m-01');
        $periodBEnd = gmdate('Y-m-d', strtotime($currentMonthStart . ' -1 day'));
        $periodBStart = gmdate('Y-m-01', strtotime($periodBEnd));
        $periodAEnd = gmdate('Y-m-d', strtotime($periodBStart . ' -1 day'));
        $periodAStart = gmdate('Y-m-01', strtotime($periodAEnd));

        return [
            'a' => ['from' => $periodAStart, 'to' => $periodAEnd],
            'b' => ['from' => $periodBStart, 'to' => $periodBEnd],
        ];
    }

    /**
     * @return array{
     *   period_a: array{from: string, to: string},
     *   period_b: array{from: string, to: string}
     * }
     */
    public static function resolveRanges(
        ?string $fromA,
        ?string $toA,
        ?string $fromB,
        ?string $toB,
        ?string $preset = null
    ): array {
        if ($preset === 'prior_two_months') {
            $defaults = self::defaultRanges();
            return [
                'period_a' => $defaults['a'],
                'period_b' => $defaults['b'],
            ];
        }

        if (self::isValidDate($fromA) && self::isValidDate($toA)
            && self::isValidDate($fromB) && self::isValidDate($toB)) {
            return [
                'period_a' => ['from' => self::normalizeDate($fromA), 'to' => self::normalizeDate($toA)],
                'period_b' => ['from' => self::normalizeDate($fromB), 'to' => self::normalizeDate($toB)],
            ];
        }

        $defaults = self::defaultRanges();
        return [
            'period_a' => $defaults['a'],
            'period_b' => $defaults['b'],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $identitiesA
     * @param array<string, array<string, mixed>> $identitiesB
     * @return list<array<string, mixed>>
     */
    public static function mergeIdentityDrivers(array $identitiesA, array $identitiesB): array
    {
        $drivers = [];
        $allKeys = array_unique(array_merge(array_keys($identitiesA), array_keys($identitiesB)));

        foreach ($allKeys as $key) {
            $rowA = $identitiesA[$key] ?? null;
            $rowB = $identitiesB[$key] ?? null;
            $amountA = $rowA !== null ? (float) ($rowA['amount_sum'] ?? 0) : 0.0;
            $amountB = $rowB !== null ? (float) ($rowB['amount_sum'] ?? 0) : 0.0;
            $delta = round($amountB - $amountA, 2);

            $meta = $rowB ?? $rowA;
            if ($meta === null) {
                continue;
            }

            if ($rowA !== null && $rowB !== null) {
                $driverClass = self::DRIVER_CONTINUING;
            } elseif ($rowB !== null) {
                $driverClass = self::DRIVER_NEW_IN_B;
            } else {
                $driverClass = self::DRIVER_GONE_IN_A;
            }

            $drivers[] = [
                'identity_key' => $key,
                'driver_class' => $driverClass,
                'category' => (string) ($meta['category'] ?? 'other'),
                'category_label' => ChargeCategoryResolver::label((string) ($meta['category'] ?? 'other')),
                'bucket' => NewItemsReport::bucketForCategory((string) ($meta['category'] ?? 'other')),
                'tenant_id' => $meta['tenant_id'] ?? null,
                'device_id' => $meta['device_id'] ?? null,
                'item_desc' => $meta['item_desc'] ?? null,
                'amount_a' => round($amountA, 2),
                'amount_b' => round($amountB, 2),
                'delta' => $delta,
                'charge_count_a' => $rowA !== null ? (int) ($rowA['charge_count'] ?? 0) : 0,
                'charge_count_b' => $rowB !== null ? (int) ($rowB['charge_count'] ?? 0) : 0,
                'qty_max_a' => $rowA !== null ? ($rowA['qty_max'] ?? null) : null,
                'qty_max_b' => $rowB !== null ? ($rowB['qty_max'] ?? null) : null,
            ];
        }

        usort($drivers, static function (array $a, array $b): int {
            $cmp = abs((float) $b['delta']) <=> abs((float) $a['delta']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) $a['identity_key'], (string) $b['identity_key']);
        });

        return $drivers;
    }

    /**
     * @param list<array<string, mixed>> $drivers
     * @return array{items: list<array<string, mixed>>, total: int, capped: bool}
     */
    public static function capDrivers(array $drivers, int $cap = self::DETAIL_ROW_CAP): array
    {
        $total = count($drivers);
        if ($total <= $cap) {
            return ['items' => $drivers, 'total' => $total, 'capped' => false];
        }

        $priority = [];
        $rest = [];
        foreach ($drivers as $driver) {
            if (abs((float) ($driver['delta'] ?? 0)) >= self::DELTA_FLOOR) {
                $priority[] = $driver;
            } else {
                $rest[] = $driver;
            }
        }

        $selected = $priority;
        if (count($selected) < $cap) {
            $remaining = $cap - count($selected);
            $selected = array_merge($selected, array_slice($rest, 0, $remaining));
        } else {
            $selected = array_slice($selected, 0, $cap);
        }

        return ['items' => $selected, 'total' => $total, 'capped' => true];
    }

    public static function percentChange(float $amountA, float $amountB): ?float
    {
        if (abs($amountA) < 0.00001) {
            return $amountB > 0 ? null : 0.0;
        }
        return round((($amountB - $amountA) / $amountA) * 100, 1);
    }

    /**
     * @param array<string, float> $categoriesA
     * @param array<string, float> $categoriesB
     * @return list<array<string, mixed>>
     */
    public static function buildCategoryRows(array $categoriesA, array $categoriesB): array
    {
        $rows = [];
        foreach (self::SPEND_CATEGORIES as $category) {
            $amountA = round((float) ($categoriesA[$category] ?? 0), 2);
            $amountB = round((float) ($categoriesB[$category] ?? 0), 2);
            $delta = round($amountB - $amountA, 2);
            $rows[] = [
                'category' => $category,
                'category_label' => ChargeCategoryResolver::label($category),
                'amount_a' => $amountA,
                'amount_b' => $amountB,
                'delta' => $delta,
                'pct_change' => self::percentChange($amountA, $amountB),
            ];
        }
        return $rows;
    }

    /**
     * @return array{
     *   categories: array<string, float>,
     *   total: float,
     *   identities: array<string, array<string, mixed>>
     * }
     */
    private static function aggregatePeriod(string $from, string $to): array
    {
        $categories = array_fill_keys(self::SPEND_CATEGORIES, 0.0);
        $identities = [];

        $rows = CanonicalUsage::query()
            ->where('amount', '>', 0)
            ->where('usage_date', '>=', $from)
            ->where('usage_date', '<=', $to)
            ->orderBy('usage_date')
            ->orderBy('id')
            ->get(['device_id', 'tenant_id', 'item_type', 'item_desc', 'quantity', 'amount']);

        foreach ($rows as $row) {
            $category = ChargeCategoryResolver::fromUsageRow(
                (string) ($row->item_type ?? ''),
                $row->item_desc !== null ? (string) $row->item_desc : null
            );
            if (!isset($categories[$category])) {
                $category = 'other';
            }

            $amount = (float) ($row->amount ?? 0);
            $categories[$category] += $amount;

            $deviceId = $row->device_id !== null ? (string) $row->device_id : null;
            $tenantId = $row->tenant_id !== null ? (string) $row->tenant_id : null;
            $itemDesc = $row->item_desc !== null ? (string) $row->item_desc : null;
            $key = NewItemsReport::identityKey($category, $deviceId, $tenantId, $itemDesc);

            if (!isset($identities[$key])) {
                $identities[$key] = [
                    'category' => $category,
                    'tenant_id' => $tenantId,
                    'device_id' => $deviceId,
                    'item_desc' => $itemDesc,
                    'amount_sum' => 0.0,
                    'charge_count' => 0,
                    'qty_max' => 0.0,
                ];
            }

            $identities[$key]['amount_sum'] += $amount;
            $identities[$key]['charge_count']++;
            $qty = $row->quantity;
            if ($qty !== null && $qty !== '' && is_numeric($qty)) {
                $identities[$key]['qty_max'] = max(
                    (float) $identities[$key]['qty_max'],
                    (float) $qty
                );
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'categories' => $categories,
            'total' => round(array_sum($categories), 2),
            'identities' => $identities,
        ];
    }

    /**
     * @param array<string, mixed> $periodA
     * @param array<string, mixed> $periodB
     */
    private static function buildSpendSummary(array $periodA, array $periodB): array
    {
        /** @var array<string, float> $categoriesA */
        $categoriesA = $periodA['categories'];
        /** @var array<string, float> $categoriesB */
        $categoriesB = $periodB['categories'];

        return [
            'total_a' => round((float) $periodA['total'], 2),
            'total_b' => round((float) $periodB['total'], 2),
            'delta' => round((float) $periodB['total'] - (float) $periodA['total'], 2),
            'categories' => self::buildCategoryRows($categoriesA, $categoriesB),
        ];
    }

    /**
     * @param array<string, mixed> $periodA
     * @param array<string, mixed> $periodB
     */
    private static function buildDrivers(array $periodA, array $periodB, ?string $bucketFilter): array
    {
        /** @var array<string, array<string, mixed>> $identitiesA */
        $identitiesA = $periodA['identities'];
        /** @var array<string, array<string, mixed>> $identitiesB */
        $identitiesB = $periodB['identities'];

        $drivers = self::mergeIdentityDrivers($identitiesA, $identitiesB);

        if ($bucketFilter !== null && $bucketFilter !== '' && $bucketFilter !== 'all') {
            $drivers = array_values(array_filter(
                $drivers,
                static function (array $driver) use ($bucketFilter): bool {
                    return ($driver['bucket'] ?? null) === $bucketFilter;
                }
            ));
        }

        $capped = self::capDrivers($drivers);

        return [
            'items' => $capped['items'],
            'total_identities' => $capped['total'],
            'capped' => $capped['capped'],
        ];
    }

    private static function buildSnapshotCompare(string $toA, string $toB): array
    {
        $targetA = $toA . ' 23:59:59';
        $targetB = $toB . ' 23:59:59';

        $snapA = PortalUsageExtractor::findSnapshotNear($targetA);
        $snapB = PortalUsageExtractor::findSnapshotNear($targetB);

        $totalsA = $snapA !== null
            ? PortalUsageExtractor::stripItems(PortalUsageExtractor::getSnapshot($snapA))
            : null;
        $totalsB = $snapB !== null
            ? PortalUsageExtractor::stripItems(PortalUsageExtractor::getSnapshot($snapB))
            : null;

        $rows = [];
        foreach (self::SNAPSHOT_COMPARE_KEYS as $key) {
            $countA = $totalsA !== null ? (float) ($totalsA[$key]['count'] ?? 0) : null;
            $countB = $totalsB !== null ? (float) ($totalsB[$key]['count'] ?? 0) : null;
            $amountA = $totalsA !== null ? (float) ($totalsA[$key]['amount'] ?? 0) : null;
            $amountB = $totalsB !== null ? (float) ($totalsB[$key]['amount'] ?? 0) : null;

            $rows[] = [
                'key' => $key,
                'label' => self::snapshotLabel($key),
                'count_a' => $countA,
                'count_b' => $countB,
                'count_delta' => ($countA !== null && $countB !== null) ? round($countB - $countA, 2) : null,
                'amount_a' => $amountA,
                'amount_b' => $amountB,
                'amount_delta' => ($amountA !== null && $amountB !== null) ? round($amountB - $amountA, 2) : null,
            ];
        }

        $totalAmountA = $totalsA !== null ? (float) ($totalsA['total_amount'] ?? 0) : null;
        $totalAmountB = $totalsB !== null ? (float) ($totalsB['total_amount'] ?? 0) : null;

        return [
            'snapshot_a' => $snapA,
            'snapshot_b' => $snapB,
            'target_a' => $targetA,
            'target_b' => $targetB,
            'rows' => $rows,
            'total_amount_a' => $totalAmountA,
            'total_amount_b' => $totalAmountB,
            'total_amount_delta' => ($totalAmountA !== null && $totalAmountB !== null)
                ? round($totalAmountB - $totalAmountA, 2)
                : null,
        ];
    }

    private static function snapshotLabel(string $key): string
    {
        if ($key === 'account_fees') {
            return 'Account fees';
        }
        if ($key === 'other_boosters') {
            return 'Other boosters';
        }
        return ChargeCategoryResolver::label($key);
    }

    private static function isValidDate(?string $date): bool
    {
        if ($date === null || $date === '') {
            return false;
        }
        $normalized = self::normalizeDate($date);
        return $normalized === $date || preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1;
    }

    private static function normalizeDate(string $date): string
    {
        return substr(trim($date), 0, 10);
    }
}
