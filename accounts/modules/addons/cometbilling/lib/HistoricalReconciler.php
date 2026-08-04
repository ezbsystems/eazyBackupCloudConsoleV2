<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Historical overbill detection from cb_credit_usage charge lines vs revoked devices.
 *
 * Compares each charge's usage_date to the expected billing end (same monthly/daily
 * rules as live Reconcile) — not server inventory counts.
 */
class HistoricalReconciler
{
    private const ITEM_TYPES = [
        'devices' => 'Devices',
        'hyperv_vms' => 'Hyper-V VMs',
        'vmware_vms' => 'VMware VMs',
        'proxmox_vms' => 'Proxmox VMs',
        'disk_image' => 'Disk Image',
        'mssql' => 'MS SQL Server',
        'm365_accounts' => 'M365 Accounts',
        'other' => 'Other',
    ];

    private const DAILY_CATEGORIES = [
        'hyperv_vms',
        'vmware_vms',
        'proxmox_vms',
    ];

    private const UI_ROW_CAP = 2000;

    private const CHUNK_SIZE = 5000;

    private const MONTHLY_CYCLE_DAYS = 30;

    /**
     * Build historical overbill report for a date range (inclusive, UTC calendar dates).
     *
     * @return array<string, mixed>
     */
    public static function report(string $fromDate, string $toDate, bool $includeGrace = false): array
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);
        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $revokedIndex = self::loadRevokedDeviceIndex();
        $categoryBuckets = self::emptyCategoryBuckets();

        $summary = [
            'charges_scanned' => 0,
            'matched_revoked' => 0,
            'overbilled_count' => 0,
            'overbilled_amount' => 0.0,
            'expected_grace_count' => 0,
            'unmatched_device_count' => 0,
        ];

        $overbilledRows = [];
        $graceRows = [];
        $offset = 0;

        while (true) {
            $batch = self::fetchUsageChunk($fromDate, $toDate, $offset, self::CHUNK_SIZE);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $summary['charges_scanned']++;
                $evaluated = self::evaluateChargeRow($row, $revokedIndex);

                if ($evaluated === null) {
                    $summary['unmatched_device_count']++;
                    continue;
                }

                $summary['matched_revoked']++;
                $category = $evaluated['category'];
                $status = $evaluated['billing_status'];

                if ($status === 'overbilled_past_grace') {
                    $summary['overbilled_count']++;
                    $summary['overbilled_amount'] += (float) $evaluated['overbill_amount'];
                    $categoryBuckets[$category]['overbilled_count']++;
                    $categoryBuckets[$category]['overbilled_amount'] += (float) $evaluated['overbill_amount'];
                    $overbilledRows[] = $evaluated;
                } else {
                    $summary['expected_grace_count']++;
                    $categoryBuckets[$category]['expected_grace_count']++;
                    if ($includeGrace) {
                        $graceRows[] = $evaluated;
                    }
                }
            }

            if (count($batch) < self::CHUNK_SIZE) {
                break;
            }
            $offset += self::CHUNK_SIZE;
        }

        usort($overbilledRows, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['usage_date'], (string) $a['usage_date']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['device_id'] ?? ''), (string) ($b['device_id'] ?? ''));
        });

        $uiRows = array_slice($overbilledRows, 0, self::UI_ROW_CAP);
        if ($includeGrace) {
            $uiRows = array_merge($uiRows, array_slice($graceRows, 0, max(0, self::UI_ROW_CAP - count($uiRows))));
        }

        $categories = [];
        foreach (self::ITEM_TYPES as $key => $label) {
            $bucket = $categoryBuckets[$key];
            if (
                $bucket['overbilled_count'] === 0
                && $bucket['overbilled_amount'] === 0.0
                && $bucket['expected_grace_count'] === 0
            ) {
                continue;
            }
            $categories[$key] = [
                'label' => $label,
                'overbilled_count' => $bucket['overbilled_count'],
                'overbilled_amount' => round($bucket['overbilled_amount'], 4),
                'expected_grace_count' => $bucket['expected_grace_count'],
            ];
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'summary' => [
                'charges_scanned' => $summary['charges_scanned'],
                'matched_revoked' => $summary['matched_revoked'],
                'overbilled_count' => $summary['overbilled_count'],
                'overbilled_amount' => round($summary['overbilled_amount'], 4),
                'expected_grace_count' => $summary['expected_grace_count'],
                'unmatched_device_count' => $summary['unmatched_device_count'],
                'ui_row_cap' => self::UI_ROW_CAP,
                'ui_rows_shown' => count($uiRows),
                'ui_rows_truncated' => $summary['overbilled_count'] > self::UI_ROW_CAP,
            ],
            'categories' => $categories,
            'rows' => $uiRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function collectOverbilledRows(string $fromDate, string $toDate): array
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);
        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $revokedIndex = self::loadRevokedDeviceIndex();
        $rows = [];
        $offset = 0;

        while (true) {
            $batch = self::fetchUsageChunk($fromDate, $toDate, $offset, self::CHUNK_SIZE);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $evaluated = self::evaluateChargeRow($row, $revokedIndex);
                if ($evaluated !== null && $evaluated['billing_status'] === 'overbilled_past_grace') {
                    $rows[] = $evaluated;
                }
            }

            if (count($batch) < self::CHUNK_SIZE) {
                break;
            }
            $offset += self::CHUNK_SIZE;
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['usage_date'], (string) $a['usage_date']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['device_id'] ?? ''), (string) ($b['device_id'] ?? ''));
        });

        return $rows;
    }

    public static function buildCsv(string $fromDate, string $toDate): string
    {
        $rows = self::collectOverbilledRows($fromDate, $toDate);
        $headers = [
            'usage_date',
            'account',
            'device_id',
            'category',
            'item_desc',
            'amount',
            'revoked_at',
            'registered_at',
            'expected_billing_end',
            'billing_status',
            'overbill_amount',
            'cycle',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv === false ? '' : $csv;
    }

    public static function streamCsv(string $fromDate, string $toDate): void
    {
        $csv = self::buildCsv($fromDate, $toDate);
        $from = self::normalizeDate($fromDate);
        $to = self::normalizeDate($toDate);
        $filename = 'comet-historical-overbill-' . $from . '_to_' . $to . '.csv';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: no-store');
        echo $csv;
        exit;
    }

    /**
     * Resolve date range from preset or explicit from/to.
     *
     * @return array{from: string, to: string, preset: int|string|null}
     */
    public static function resolveDateRange($presetDays, ?string $from, ?string $to): array
    {
        $today = gmdate('Y-m-d');

        if ($from && $to && self::isValidDate($from) && self::isValidDate($to)) {
            return [
                'from' => self::normalizeDate($from),
                'to' => self::normalizeDate($to),
                'preset' => null,
            ];
        }

        if ($presetDays === 'all' || $presetDays === 0 || $presetDays === '0') {
            return [
                'from' => self::earliestUsageDate() ?? '2019-01-01',
                'to' => $today,
                'preset' => 'all',
            ];
        }

        $days = is_numeric($presetDays) ? (int) $presetDays : 90;
        if (!in_array($days, [30, 90, 365], true)) {
            $days = 90;
        }

        return [
            'from' => gmdate('Y-m-d', strtotime("-{$days} days")),
            'to' => $today,
            'preset' => $days,
        ];
    }

    /**
     * Categorize a credit usage row (exposed for tests).
     */
    public static function categorizeCharge(string $itemType, ?string $itemDesc): string
    {
        $desc = strtolower((string) $itemDesc);
        $type = strtolower($itemType);

        if ($type === 'device' || str_contains($desc, 'device -')) {
            return 'devices';
        }
        if (str_contains($desc, 'hyper-v') || str_contains($desc, 'hyperv')) {
            return 'hyperv_vms';
        }
        if (str_contains($desc, 'vmware')) {
            return 'vmware_vms';
        }
        if (str_contains($desc, 'proxmox')) {
            return 'proxmox_vms';
        }
        if (str_contains($desc, 'disk image')) {
            return 'disk_image';
        }
        if (str_contains($desc, 'sql server') || str_contains($desc, 'mssql')) {
            return 'mssql';
        }
        if (str_contains($desc, 'office 365') || str_contains($desc, 'm365') || str_contains($desc, 'microsoft 365')) {
            return 'm365_accounts';
        }

        return 'other';
    }

    /**
     * @param array{by_hash: array<string, array<string, mixed>>} $revokedIndex
     * @return array<string, mixed>|null
     */
    private static function evaluateChargeRow(object $row, array $revokedIndex): ?array
    {
        $deviceId = strtolower(trim((string) ($row->device_id ?? '')));
        if ($deviceId === '') {
            return null;
        }

        $device = $revokedIndex['by_hash'][$deviceId] ?? null;
        if ($device === null || empty($device['revoked_at'])) {
            return null;
        }

        $itemDesc = (string) ($row->item_desc ?? '');
        $itemType = (string) ($row->item_type ?? '');
        $category = self::categorizeCharge($itemType, $itemDesc);
        $usageDate = BillingPeriodCalculator::dateOnly((string) $row->usage_date);
        $revokedAt = BillingPeriodCalculator::dateOnly((string) $device['revoked_at']);
        $registeredAt = $device['registered_at'] ?? null;
        $isDaily = in_array($category, self::DAILY_CATEGORIES, true);
        $cycle = $isDaily ? 'daily' : 'monthly';

        if ($isDaily) {
            $expectedEnd = $revokedAt;
            $billingStatus = $usageDate > $expectedEnd ? 'overbilled_past_grace' : 'expected_grace';
        } else {
            $expectedEnd = BillingPeriodCalculator::deviceExpectedEnd(
                $registeredAt,
                (string) $device['revoked_at'],
                self::MONTHLY_CYCLE_DAYS,
                null
            );
            if ($expectedEnd === null) {
                $expectedEnd = date('Y-m-d', strtotime($revokedAt . ' +' . self::MONTHLY_CYCLE_DAYS . ' days'));
            }
            $billingStatus = $usageDate > $expectedEnd ? 'overbilled_past_grace' : 'expected_grace';
        }

        $amount = (float) ($row->amount ?? 0);
        $account = (string) ($row->tenant_id ?? $device['username'] ?? '');

        return [
            'usage_date' => $usageDate,
            'account' => $account,
            'device_id' => $deviceId,
            'device_name' => $device['name'] ?? null,
            'category' => $category,
            'category_label' => self::ITEM_TYPES[$category] ?? $category,
            'item_desc' => $itemDesc,
            'amount' => $amount,
            'revoked_at' => $revokedAt,
            'registered_at' => $registeredAt,
            'expected_billing_end' => $expectedEnd,
            'billing_status' => $billingStatus,
            'overbill_amount' => $billingStatus === 'overbilled_past_grace' ? $amount : 0.0,
            'cycle' => $cycle,
        ];
    }

    /**
     * @return list<object>
     */
    private static function fetchUsageChunk(string $fromDate, string $toDate, int $offset, int $limit): array
    {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return [];
        }

        $query = Capsule::table('cb_credit_usage')
            ->whereBetween('usage_date', [$fromDate, $toDate])
            ->whereNotNull('device_id')
            ->where('device_id', '!=', '')
            ->orderBy('id');

        if ($offset > 0) {
            $query->offset($offset);
        }

        return $query->limit($limit)->get();
    }

    /**
     * @return array{by_hash: array<string, array<string, mixed>>}
     */
    private static function loadRevokedDeviceIndex(): array
    {
        $byHash = [];

        if (!Capsule::schema()->hasTable('comet_devices')) {
            return ['by_hash' => $byHash];
        }

        $rows = Capsule::table('comet_devices')
            ->whereNotNull('revoked_at')
            ->select(['hash', 'username', 'name', 'revoked_at', 'content'])
            ->get();

        foreach ($rows as $row) {
            $hash = strtolower((string) $row->hash);
            $registrationAt = null;
            $content = json_decode((string) ($row->content ?? ''), true);
            if (is_array($content)) {
                $registrationTime = (int) ($content['RegistrationTime'] ?? 0);
                if ($registrationTime > 0) {
                    $registrationAt = gmdate('Y-m-d', $registrationTime);
                }
            }
            $byHash[$hash] = [
                'hash' => $hash,
                'username' => (string) $row->username,
                'name' => $row->name,
                'revoked_at' => (string) $row->revoked_at,
                'registered_at' => $registrationAt,
            ];
        }

        return ['by_hash' => $byHash];
    }

    /**
     * @return array<string, array{overbilled_count: int, overbilled_amount: float, expected_grace_count: int}>
     */
    private static function emptyCategoryBuckets(): array
    {
        $buckets = [];
        foreach (array_keys(self::ITEM_TYPES) as $key) {
            $buckets[$key] = [
                'overbilled_count' => 0,
                'overbilled_amount' => 0.0,
                'expected_grace_count' => 0,
            ];
        }

        return $buckets;
    }

    private static function earliestUsageDate(): ?string
    {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return null;
        }

        $min = Capsule::table('cb_credit_usage')->min('usage_date');

        return $min ? self::normalizeDate((string) $min) : null;
    }

    private static function normalizeDate(string $date): string
    {
        $ts = strtotime($date);

        return $ts ? gmdate('Y-m-d', $ts) : gmdate('Y-m-d');
    }

    private static function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
