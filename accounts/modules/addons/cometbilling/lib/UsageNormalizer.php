<?php
namespace CometBilling;

class UsageNormalizer
{
    public static function normalizeRow(array $row): array
    {
        $usageDate = self::toDate($row['Date'] ?? $row['UsageDate'] ?? null);
        $postedAt  = self::toDateTime($row['PostedAt'] ?? null);

        $itemType  = strtolower((string)($row['Type'] ?? 'other'));
        $itemDesc  = $row['Description'] ?? ($row['Plan'] ?? null);
        $tenantId  = $row['TenantID'] ?? $row['AccountID'] ?? null;
        $deviceId  = $row['DeviceID'] ?? null;
        $qty       = self::toDec($row['Quantity'] ?? null);
        $unitCost  = self::toDec($row['UnitCost'] ?? null);
        $amount    = self::toDec($row['Amount'] ?? $row['Charge'] ?? 0);
        $packsUsed = self::toDec($row['PacksUsed'] ?? null);

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
            $usageDate,$postedAt,$tenantId,$deviceId,$itemType,$itemDesc,$qty,$unitCost,$amount,$packsUsed
        ]));

        return $normalized;
    }

    private static function toDate(?string $v): ?string
    {
        if (!$v) return null;
        $ts = strtotime($v);
        return $ts ? gmdate('Y-m-d', $ts) : null;
    }

    private static function toDateTime(?string $v): ?string
    {
        if (!$v) return null;
        $ts = strtotime($v);
        return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    private static function toDec($v): ?string
    {
        if ($v === null || $v === '') return null;
        return number_format((float)$v, 6, '.', '');
    }
}


