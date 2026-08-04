<?php
namespace CometBilling;

/**
 * Counts identities whose first canonical Bill History charge falls in a date range.
 */
final class NewItemsReport
{
    public const BUCKET_DEVICES = 'devices';
    public const BUCKET_BOOSTERS = 'boosters';
    public const BUCKET_M365 = 'm365';

    private const BOOSTER_CATEGORIES = [
        'hyperv_vms',
        'vmware_vms',
        'proxmox_vms',
        'disk_image',
        'mssql',
    ];

    /**
     * @return array{
     *   from: string,
     *   to: string,
     *   counts: array{devices: int, boosters: int, m365: int, m365_hosts: int},
     *   booster_breakdown: array<string, int>,
     *   items: list<array<string, mixed>>
     * }
     */
    public static function report(string $from, string $to, ?string $bucketFilter = null): array
    {
        $firsts = self::firstBilledByIdentity();
        $items = [];
        $counts = [
            self::BUCKET_DEVICES => 0,
            self::BUCKET_BOOSTERS => 0,
            self::BUCKET_M365 => 0,
            'm365_hosts' => 0,
        ];
        $boosterBreakdown = [];
        foreach (self::BOOSTER_CATEGORIES as $cat) {
            $boosterBreakdown[$cat] = 0;
        }

        foreach ($firsts as $row) {
            $firstDate = (string) $row['first_billed'];
            if ($firstDate < $from || $firstDate > $to) {
                continue;
            }

            $bucket = self::bucketForCategory((string) $row['category']);
            if ($bucket === null) {
                continue;
            }

            $protectedAccounts = null;
            if ($bucket === self::BUCKET_M365) {
                $protectedAccounts = self::protectedAccountCount(
                    $row['item_desc'] !== null ? (string) $row['item_desc'] : null,
                    $row['quantity'],
                    (float) $row['amount']
                );
                $counts[self::BUCKET_M365] += $protectedAccounts;
                $counts['m365_hosts']++;
            } else {
                $counts[$bucket]++;
            }

            if ($bucket === self::BUCKET_BOOSTERS) {
                $boosterBreakdown[(string) $row['category']] =
                    ($boosterBreakdown[(string) $row['category']] ?? 0) + 1;
            }

            if ($bucketFilter !== null && $bucketFilter !== '' && $bucketFilter !== 'all'
                && $bucketFilter !== $bucket) {
                continue;
            }

            $qtyDisplay = $row['quantity'];
            if ($bucket === self::BUCKET_M365) {
                $qtyDisplay = $protectedAccounts;
            }

            $items[] = [
                'first_billed' => $firstDate,
                'bucket' => $bucket,
                'category' => $row['category'],
                'category_label' => ChargeCategoryResolver::label((string) $row['category']),
                'tenant_id' => $row['tenant_id'],
                'device_id' => $row['device_id'],
                'item_desc' => $row['item_desc'],
                'quantity' => $qtyDisplay,
                'protected_accounts' => $protectedAccounts,
                'amount' => $row['amount'],
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['first_billed'], (string) $a['first_billed']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) $a['category'], (string) $b['category']);
        });

        return [
            'from' => $from,
            'to' => $to,
            'counts' => $counts,
            'booster_breakdown' => $boosterBreakdown,
            'items' => $items,
        ];
    }

    /**
     * Protected account count from Bill History quantity, "Accounts N" text, or $1/account amount.
     */
    public static function protectedAccountCount(?string $itemDesc, mixed $quantity, float $amount): int
    {
        if ($quantity !== null && $quantity !== '' && is_numeric($quantity) && (float) $quantity > 0) {
            return (int) round((float) $quantity);
        }

        $desc = (string) $itemDesc;
        if (preg_match('/Accounts?\s+(\d+)/i', $desc, $m)) {
            return (int) $m[1];
        }

        if ($amount > 0) {
            return (int) round($amount);
        }

        return 1;
    }

    public static function bucketForCategory(string $category): ?string
    {
        if ($category === 'devices') {
            return self::BUCKET_DEVICES;
        }
        if ($category === 'm365_accounts') {
            return self::BUCKET_M365;
        }
        if (in_array($category, self::BOOSTER_CATEGORIES, true)) {
            return self::BUCKET_BOOSTERS;
        }
        return null;
    }

    public static function identityKey(string $category, ?string $deviceId, ?string $tenantId, ?string $itemDesc): string
    {
        $device = trim((string) $deviceId);
        if ($device !== '') {
            return $category . '|d:' . strtolower($device);
        }
        $tenant = trim((string) $tenantId);
        $desc = trim((string) $itemDesc);
        return $category . '|t:' . strtolower($tenant) . '|' . strtolower($desc);
    }

    /**
     * @return list<array{
     *   category: string,
     *   first_billed: string,
     *   tenant_id: ?string,
     *   device_id: ?string,
     *   item_desc: ?string,
     *   quantity: mixed,
     *   amount: float
     * }>
     */
    private static function firstBilledByIdentity(): array
    {
        // Collapse identical charge lines first, then merge by category+device in PHP.
        $grouped = CanonicalUsage::query()
            ->where('amount', '>', 0)
            ->selectRaw(
                'device_id, tenant_id, item_type, item_desc, '
                . 'MIN(usage_date) AS first_billed, '
                . 'SUBSTRING_INDEX(GROUP_CONCAT(quantity ORDER BY usage_date ASC, id ASC), ",", 1) AS quantity, '
                . 'SUBSTRING_INDEX(GROUP_CONCAT(amount ORDER BY usage_date ASC, id ASC), ",", 1) AS amount'
            )
            ->groupBy('device_id', 'tenant_id', 'item_type', 'item_desc')
            ->orderBy('first_billed', 'asc')
            ->get();

        $firsts = [];
        foreach ($grouped as $row) {
            $category = ChargeCategoryResolver::fromUsageRow(
                (string) ($row->item_type ?? ''),
                $row->item_desc !== null ? (string) $row->item_desc : null
            );
            if (self::bucketForCategory($category) === null) {
                continue;
            }

            $key = self::identityKey(
                $category,
                $row->device_id !== null ? (string) $row->device_id : null,
                $row->tenant_id !== null ? (string) $row->tenant_id : null,
                $row->item_desc !== null ? (string) $row->item_desc : null
            );

            $firstDate = substr((string) $row->first_billed, 0, 10);
            if (isset($firsts[$key]) && (string) $firsts[$key]['first_billed'] <= $firstDate) {
                continue;
            }

            $firsts[$key] = [
                'category' => $category,
                'first_billed' => $firstDate,
                'tenant_id' => $row->tenant_id !== null ? (string) $row->tenant_id : null,
                'device_id' => $row->device_id !== null ? (string) $row->device_id : null,
                'item_desc' => $row->item_desc !== null ? (string) $row->item_desc : null,
                'quantity' => $row->quantity,
                'amount' => (float) ($row->amount ?? 0),
            ];
        }

        return array_values($firsts);
    }
}
