<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Prefetched reversal lookups for a date range (avoids per-row DB queries).
 */
class ReversalIndex
{
    /** @var array<string, array<string, mixed>> device hash => reversal */
    private static array $byDevice = [];

    /** @var array<string, array<string, mixed>> tenant => reversal */
    private static array $byTenant = [];

    private static ?array $refund = null;

    private static bool $loaded = false;

    private static ?bool $hasPurchasesTable = null;

    private static ?bool $hasRecordTypeColumn = null;

    public static function clear(): void
    {
        self::$byDevice = [];
        self::$byTenant = [];
        self::$refund = null;
        self::$loaded = false;
    }

    public static function warm(string $fromDate, string $toDate): void
    {
        self::clear();
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            self::$loaded = true;

            return;
        }

        $rows = CanonicalUsage::query()
            ->whereBetween('usage_date', [$fromDate, $toDate])
            ->where('amount', '<', 0)
            ->orderBy('usage_date')
            ->orderBy('id')
            ->get(['id', 'usage_date', 'amount', 'device_id', 'tenant_id']);

        $rows = is_array($rows) ? $rows : $rows->all();
        foreach ($rows as $row) {
            $entry = [
                'usage_id' => (int) $row->id,
                'usage_date' => (string) $row->usage_date,
                'amount' => (float) $row->amount,
            ];
            $deviceId = (string) ($row->device_id ?? '');
            if ($deviceId !== '' && !isset(self::$byDevice[$deviceId])) {
                self::$byDevice[$deviceId] = $entry;
            }
            $tenant = (string) ($row->tenant_id ?? '');
            if ($tenant !== '' && !isset(self::$byTenant[$tenant])) {
                self::$byTenant[$tenant] = $entry;
            }
        }

        if (self::hasRefundSupport()) {
            $refund = Capsule::table('cb_credit_purchases')
                ->where('record_type', 'refund')
                ->where('purchased_at', '>=', $fromDate . ' 00:00:00')
                ->where('purchased_at', '<=', $toDate . ' 23:59:59')
                ->orderBy('purchased_at')
                ->first();
            if ($refund) {
                self::$refund = [
                    'purchase_id' => $refund->id,
                    'purchased_at' => $refund->purchased_at,
                    'amount' => (float) $refund->credit_amount,
                ];
            }
        }

        self::$loaded = true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function lookup(string $usageDate, string $tenantId, ?string $deviceHash): ?array
    {
        if (!self::$loaded) {
            return null;
        }

        if ($deviceHash !== null && $deviceHash !== '' && isset(self::$byDevice[$deviceHash])) {
            $match = self::$byDevice[$deviceHash];
            if ($match['usage_date'] >= $usageDate) {
                return $match;
            }
        }

        if ($tenantId !== '' && isset(self::$byTenant[$tenantId])) {
            $match = self::$byTenant[$tenantId];
            if ($match['usage_date'] >= $usageDate) {
                return $match;
            }
        }

        if (self::$refund !== null) {
            $refundDate = substr((string) self::$refund['purchased_at'], 0, 10);
            if ($refundDate >= $usageDate) {
                return self::$refund;
            }
        }

        return null;
    }

    private static function hasRefundSupport(): bool
    {
        if (self::$hasPurchasesTable === null) {
            self::$hasPurchasesTable = Capsule::schema()->hasTable('cb_credit_purchases');
        }
        if (!self::$hasPurchasesTable) {
            self::$hasRecordTypeColumn = false;

            return false;
        }
        if (self::$hasRecordTypeColumn === null) {
            self::$hasRecordTypeColumn = Capsule::schema()->hasColumn('cb_credit_purchases', 'record_type');
        }

        return self::$hasRecordTypeColumn;
    }
}
