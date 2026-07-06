<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Report on Booster (Microsoft 365) Protected Accounts from portal active-services snapshots.
 */
class M365BoosterReport
{
    private const SQL_LIKE_PATTERN = '%Booster (Microsoft 365) Protected Accounts%';

    private const COUNT_REGEX = '/Booster\s*\(Microsoft\s+365\)\s+Protected\s+Accounts\s+(\d+)/i';

    /**
     * Build report for a date range (inclusive, UTC calendar dates).
     *
     * @return array{
     *   from_date: string,
     *   to_date: string,
     *   snapshot_at: ?string,
     *   snapshot_count: int,
     *   line_count: int,
     *   total_accounts: int,
     *   total_amount: float,
     *   items: array<int, array<string, mixed>>,
     *   message: ?string
     * }
     */
    public static function report(string $fromDate, string $toDate): array
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $snapshotCount = self::countSnapshotsInRange($fromDate, $toDate);
        $snapshotAt = self::findLatestSnapshotInRange($fromDate, $toDate);

        $result = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'snapshot_at' => $snapshotAt,
            'snapshot_count' => $snapshotCount,
            'line_count' => 0,
            'total_accounts' => 0,
            'total_amount' => 0.0,
            'items' => [],
            'message' => null,
        ];

        if ($snapshotAt === null) {
            $result['message'] = 'No portal snapshot in this period. Run a portal pull from Data Sync.';
            return $result;
        }

        $rows = self::getM365Rows($snapshotAt);
        $items = [];
        $totalAccounts = 0;
        $totalAmount = 0.0;

        foreach ($rows as $row) {
            $serviceName = (string) ($row->service_name ?? '');
            if (!self::isM365ProtectedAccountsRow($serviceName)) {
                continue;
            }

            $count = self::parseProtectedCount($serviceName, $row->quantity ?? null);
            $amount = (float) ($row->amount ?? 0);
            $parsed = self::parseAccountDevice($serviceName);

            $items[] = [
                'service_name' => $serviceName,
                'account' => $parsed['account'],
                'device_id' => $parsed['device_id'] ?: ($row->device_id ?? null),
                'tenant_id' => $row->tenant_id ?? null,
                'protected_accounts' => $count,
                'amount' => $amount,
                'unit_cost' => (float) ($row->unit_cost ?? 0),
            ];

            $totalAccounts += $count;
            $totalAmount += $amount;
        }

        usort($items, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['account'] ?? ''), (string) ($b['account'] ?? ''));
        });

        $result['line_count'] = count($items);
        $result['total_accounts'] = $totalAccounts;
        $result['total_amount'] = round($totalAmount, 2);
        $result['items'] = $items;

        return $result;
    }

    /**
     * Latest pulled_at within [fromDate 00:00:00, toDate 23:59:59] UTC.
     */
    public static function findLatestSnapshotInRange(string $fromDate, string $toDate): ?string
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);

        $latest = Capsule::table('cb_active_services')
            ->where('pulled_at', '>=', $fromDate . ' 00:00:00')
            ->where('pulled_at', '<=', $toDate . ' 23:59:59')
            ->max('pulled_at');

        return $latest ? (string) $latest : null;
    }

    /**
     * Number of distinct snapshots in the date range.
     */
    public static function countSnapshotsInRange(string $fromDate, string $toDate): int
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);

        return (int) Capsule::table('cb_active_services')
            ->where('pulled_at', '>=', $fromDate . ' 00:00:00')
            ->where('pulled_at', '<=', $toDate . ' 23:59:59')
            ->distinct()
            ->count('pulled_at');
    }

    /**
     * Rows pre-filtered by SQL LIKE for a specific snapshot.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getM365Rows(string $pulledAt)
    {
        return Capsule::table('cb_active_services')
            ->where('pulled_at', $pulledAt)
            ->where('service_name', 'like', self::SQL_LIKE_PATTERN)
            ->orderBy('service_name')
            ->get();
    }

    public static function isM365ProtectedAccountsRow(string $serviceName): bool
    {
        return (bool) preg_match(self::COUNT_REGEX, $serviceName);
    }

    public static function parseProtectedCount(string $serviceName, $quantity): int
    {
        if ($quantity !== null && $quantity !== '') {
            $q = (int) round((float) $quantity);
            if ($q > 0) {
                return $q;
            }
        }

        if (preg_match(self::COUNT_REGEX, $serviceName, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @return array{account: ?string, device_id: ?string}
     */
    public static function parseAccountDevice(string $serviceName): array
    {
        $info = ['account' => null, 'device_id' => null];

        if (preg_match('/Account\s+([^\-]+)/i', $serviceName, $m)) {
            $info['account'] = trim($m[1]);
        }
        if (preg_match('/Device\s+([a-f0-9]+)/i', $serviceName, $m)) {
            $info['device_id'] = trim($m[1]);
        }

        return $info;
    }

    /**
     * Resolve from/to dates from preset days or explicit GET params.
     *
     * @return array{from: string, to: string, preset: ?int}
     */
    public static function resolveDateRange(?int $presetDays, ?string $from, ?string $to): array
    {
        $today = gmdate('Y-m-d');

        if ($from && $to && self::isValidDate($from) && self::isValidDate($to)) {
            return [
                'from' => self::normalizeDate($from),
                'to' => self::normalizeDate($to),
                'preset' => null,
            ];
        }

        $days = $presetDays ?? 30;
        if (!in_array($days, [30, 60, 90], true)) {
            $days = 30;
        }

        return [
            'from' => gmdate('Y-m-d', strtotime("-{$days} days")),
            'to' => $today,
            'preset' => $days,
        ];
    }

    private static function normalizeDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts ? gmdate('Y-m-d', $ts) : gmdate('Y-m-d');
    }

    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d instanceof \DateTime && $d->format('Y-m-d') === $date;
    }
}
