<?php
namespace CometBilling;

class ActiveServicesNormalizer
{
    public static function normalizeRow(array $row, string $pulledAt): array
    {
        $service  = self::pick($row, ['Service','Name','Plan','Product','ServiceName']) ?? 'Unknown';
        $cycleRaw = self::pick($row, ['BillingCycleDays','CycleDays','Billing Cycle (Days)','Cycle (Days)']);
        $cycle    = $cycleRaw !== null ? (int)preg_replace('/[^0-9\-]/', '', (string)$cycleRaw) : 30;

        $nextDueRaw = self::pick($row, ['NextDueDate','Next Due Date','Next Due']);
        $nextDue    = self::toDate($nextDueRaw);

        $unitRaw  = self::pick($row, ['UnitCost','Unit Cost']);
        $qtyRaw   = self::pick($row, ['Quantity','Qty','Units']);
        $amtRaw   = self::pick($row, ['Amount','Charge','Total']);
        $tenantId = self::pick($row, ['TenantID','Tenant Id','AccountID','Account ID']);
        $deviceId = self::pick($row, ['DeviceID','Device Id']);

        $unit   = self::toDec(self::moneyToNumber($unitRaw));
        $qty    = self::toDec($qtyRaw);
        $amount = self::toDec(self::moneyToNumber($amtRaw));

        // Ensure non-null next due date to satisfy NOT NULL schema
        if ($nextDue === null) {
            $nextDue = gmdate('Y-m-d', strtotime($pulledAt));
        }

        $normalized = [
            'pulled_at'           => $pulledAt,
            'service_name'        => $service,
            'billing_cycle_days'  => $cycle,
            'next_due_date'       => $nextDue,
            'unit_cost'           => $unit,
            'quantity'            => $qty,
            'amount'              => $amount,
            'tenant_id'           => $tenantId,
            'device_id'           => $deviceId,
            'extra'               => $row,
        ];

        $normalized['row_fingerprint'] = md5(json_encode([
            $service,$cycle,$nextDue,$unit,$qty,$amount,$tenantId,$deviceId
        ]));

        return $normalized;
    }

    private static function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== '' && $row[$k] !== null) {
                return $row[$k];
            }
        }
        return null;
    }

    private static function moneyToNumber($v): ?string
    {
        if ($v === null || $v === '') return null;
        // remove currency symbols and thousands separators, keep digits, dot, minus
        $clean = preg_replace('/[^0-9\.\-]/', '', (string)$v);
        return $clean === '' ? null : $clean;
    }

    private static function toDate(?string $v): ?string
    {
        if (!$v) return null;
        $ts = strtotime($v);
        return $ts ? gmdate('Y-m-d', $ts) : null;
    }

    private static function toDec($v): ?string
    {
        if ($v === null || $v === '') return null;
        return number_format((float)$v, 6, '.', '');
    }
}


