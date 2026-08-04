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
        $packsUsed = self::toDec(self::moneyToNumber(self::pick($row, [
            'Packs Used',
            'PacksUsed',
        ])));

        $normalized = [
            'usage_date' => $usageDate,
            'posted_at'  => $postedAt,
            'tenant_id'  => $tenantId,
            'device_id'  => $deviceId,
            'item_type'  => $itemType,
            'item_desc'  => $itemDesc,
            'quantity'   => $qty,
            'unit_cost'  => $unitCost,
            'amount'     => $amount,
            'packs_used' => $packsUsed,
            'raw_row'    => $row,
        ];

        $normalized['row_fingerprint'] = md5(json_encode([
            $usageDate, $postedAt, $tenantId, $deviceId, $itemType, $itemDesc,
            $qty, $unitCost, $amount, $packsUsed,
        ]));

        return $normalized;
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

    private static function inferItemType(?string $item): string
    {
        if ($item === null || $item === '') {
            return 'other';
        }
        if (stripos($item, 'Booster -') === 0) {
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
