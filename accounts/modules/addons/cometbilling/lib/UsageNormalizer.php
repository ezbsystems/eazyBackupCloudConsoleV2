<?php
namespace CometBilling;

class UsageNormalizer
{
    public static function normalizeRow(array $row): array
    {
        $usageDate = self::toDate(self::pick($row, [
            'Date Added',
            'Date',
            'UsageDate',
            'Usage Date',
        ]));
        $postedAt = self::toDateTime(self::pick($row, [
            'PostedAt',
            'Posted At',
            'Date Posted',
        ]));

        $itemRaw = self::pick($row, ['Item', 'Description', 'Plan']);
        $subItems = self::pick($row, ['Sub-Items', 'Sub Items']);
        $itemDesc = $itemRaw;
        if ($subItems !== null) {
            $itemDesc = trim(($itemDesc ?? '') . ($itemDesc ? ' — ' : '') . $subItems);
        }

        $itemType = self::pick($row, ['Type']);
        if ($itemType === null) {
            $itemType = self::inferItemType($itemRaw);
        } else {
            $itemType = strtolower((string) $itemType);
        }

        $tenantId = self::blankToNull(self::pick($row, [
            'Tenant ID',
            'TenantID',
            'Tenant Id',
            'AccountID',
            'Account ID',
        ]));
        if ($tenantId === null) {
            $tenantId = self::blankToNull(self::pick($row, [
                'Account Name',
                'AccountName',
                'Account',
            ]));
        }

        $deviceId = self::blankToNull(self::pick($row, [
            'Device ID',
            'DeviceID',
            'Device Id',
        ]));
        if ($deviceId === null) {
            $deviceId = self::extractDeviceIdFromText($subItems ?? '') ?? self::extractDeviceIdFromText($itemRaw ?? '');
        }

        $qty = self::toDec(self::pick($row, ['Quantity', 'Qty']));
        $unitCost = self::toDec(self::moneyToNumber(self::pick($row, [
            'UnitCost',
            'Unit Cost',
        ])));
        $amountRaw = self::pick($row, [
            'Amount Used',
            'Amount',
            'Charge',
        ]);
        $amount = self::toDec(self::moneyToNumber($amountRaw) ?? '0');

        $packsUsedRaw = self::pick($row, ['Packs Used', 'PacksUsed']);
        $packParsed = PackUsageParser::parse($packsUsedRaw !== null ? (string) $packsUsedRaw : null);
        $packsUsed = $packParsed['primary_denomination'] !== null
            ? self::toDec((string) $packParsed['primary_denomination'])
            : self::toDec(self::moneyToNumber($packsUsedRaw));

        $normalized = [
            'usage_date' => $usageDate,
            'posted_at' => $postedAt,
            'tenant_id' => $tenantId,
            'device_id' => $deviceId,
            'item_type' => $itemType,
            'item_desc' => $itemDesc,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'packs_used' => $packsUsed,
            'packs_used_raw' => $packsUsedRaw !== null ? (string) $packsUsedRaw : null,
            'packs_used_parsed' => $packParsed,
            'raw_row' => $row,
        ];

        $normalized['content_fingerprint'] = self::contentFingerprint($normalized);
        $normalized['row_fingerprint'] = $normalized['content_fingerprint'];
        $normalized['occurrence_number'] = 1;

        return $normalized;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public static function contentFingerprint(array $normalized): string
    {
        return md5(json_encode([
            $normalized['usage_date'] ?? null,
            $normalized['posted_at'] ?? null,
            $normalized['tenant_id'] ?? null,
            $normalized['device_id'] ?? null,
            $normalized['item_type'] ?? null,
            $normalized['item_desc'] ?? null,
            $normalized['quantity'] ?? null,
            $normalized['unit_cost'] ?? null,
            $normalized['amount'] ?? null,
            $normalized['packs_used_raw'] ?? null,
        ]));
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function pick(array $row, array $keys): mixed
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if ($v === '' || $v === null) {
                continue;
            }
            return $v;
        }

        return null;
    }

    private static function blankToNull(mixed $v): ?string
    {
        if ($v === null || $v === '' || $v === '-') {
            return null;
        }

        return (string) $v;
    }

    private static function extractDeviceIdFromText(string $text): ?string
    {
        if (preg_match('/Device\s+ID:\s*([a-f0-9]+)/i', $text, $m)) {
            return strtolower(trim($m[1]));
        }
        if (preg_match('/Device\s+([a-f0-9]{6,})/i', $text, $m)) {
            return strtolower(trim($m[1]));
        }

        return null;
    }

    private static function inferItemType(?string $item): string
    {
        if ($item === null || $item === '') {
            return 'other';
        }
        if (stripos($item, 'Booster -') === 0 || stripos($item, 'Booster') === 0) {
            return 'booster';
        }
        if (stripos($item, 'Device -') === 0) {
            return 'device';
        }
        if (stripos($item, 'Advanced Plan') !== false || stripos($item, 'Plan') !== false) {
            return 'plan';
        }

        return 'other';
    }

    private static function moneyToNumber(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $v);

        return $clean === '' ? null : $clean;
    }

    private static function toDate(?string $v): ?string
    {
        if (!$v) {
            return null;
        }
        $ts = strtotime($v);

        return $ts ? gmdate('Y-m-d', $ts) : null;
    }

    private static function toDateTime(?string $v): ?string
    {
        if (!$v) {
            return null;
        }
        $ts = strtotime($v);

        return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    private static function toDec(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return number_format((float) $v, 6, '.', '');
    }
}
