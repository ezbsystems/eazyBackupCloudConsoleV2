<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * CreditLedger - Track credit pack purchases and FIFO consumption.
 * Distinguishes between purchased credits and bonus credits.
 */
class CreditLedger
{
    /**
     * Credit lot types.
     */
    public const TYPE_PURCHASED = 'purchased';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * Create credit lots from a purchase record.
     * Each purchase creates up to TWO lots: purchased + bonus (if any).
     * 
     * @param int $purchaseId ID from cb_credit_purchases
     * @return array Created lot IDs
     */
    public static function createLotsFromPurchase(int $purchaseId): array
    {
        self::ensureTables();

        $purchase = Capsule::table('cb_credit_purchases')
            ->where('id', $purchaseId)
            ->first();

        if (!$purchase) {
            throw new \RuntimeException("Purchase not found: {$purchaseId}");
        }

        $lotIds = [];
        $createdAt = $purchase->purchased_at;

        // Create purchased credit lot
        if ((float)$purchase->credit_amount > 0) {
            $lotIds[] = Capsule::table('cb_credit_lots')->insertGetId([
                'purchase_id' => $purchaseId,
                'lot_type' => self::TYPE_PURCHASED,
                'original_amount' => $purchase->credit_amount,
                'remaining_amount' => $purchase->credit_amount,
                'created_at' => $createdAt,
                'depleted_at' => null,
            ]);
        }

        // Create bonus credit lot (if any)
        if ((float)$purchase->bonus_credit > 0) {
            $lotIds[] = Capsule::table('cb_credit_lots')->insertGetId([
                'purchase_id' => $purchaseId,
                'lot_type' => self::TYPE_BONUS,
                'original_amount' => $purchase->bonus_credit,
                'remaining_amount' => $purchase->bonus_credit,
                'created_at' => $createdAt,
                'depleted_at' => null,
            ]);
        }

        return $lotIds;
    }

    /**
     * Create an opening balance lot (for initial setup).
     * 
     * @param float $purchasedAmount Purchased credit balance
     * @param float $bonusAmount Bonus credit balance
     * @param string|null $asOfDate Date for the opening balance
     * @return array Created lot IDs
     */
    public static function createOpeningBalance(float $purchasedAmount, float $bonusAmount, ?string $asOfDate = null): array
    {
        self::ensureTables();

        $createdAt = $asOfDate ?? date('Y-m-d H:i:s');
        $lotIds = [];

        if ($purchasedAmount > 0) {
            $lotIds[] = Capsule::table('cb_credit_lots')->insertGetId([
                'purchase_id' => null,
                'lot_type' => self::TYPE_PURCHASED,
                'original_amount' => $purchasedAmount,
                'remaining_amount' => $purchasedAmount,
                'created_at' => $createdAt,
                'depleted_at' => null,
            ]);
        }

        if ($bonusAmount > 0) {
            $lotIds[] = Capsule::table('cb_credit_lots')->insertGetId([
                'purchase_id' => null,
                'lot_type' => self::TYPE_BONUS,
                'original_amount' => $bonusAmount,
                'remaining_amount' => $bonusAmount,
                'created_at' => $createdAt,
                'depleted_at' => null,
            ]);
        }

        return $lotIds;
    }

    /**
     * Allocate usage amount using FIFO across available lots.
     * Oldest lots are consumed first.
     * 
     * @param float $amount Amount to allocate
     * @param string|null $usageDate Date of usage
     * @param string|null $description Description of usage
     * @return array Allocations made
     */
    public static function allocateUsage(float $amount, ?string $usageDate = null, ?string $description = null): array
    {
        self::ensureTables();

        if ($amount <= 0) {
            return [];
        }

        $remaining = $amount;
        $allocations = [];
        $usageDate = $usageDate ?? date('Y-m-d');

        // Get available lots in FIFO order (oldest first)
        $lots = Capsule::table('cb_credit_lots')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) break;

            $available = (float)$lot->remaining_amount;
            $toDeduct = min($available, $remaining);

            // Update lot remaining amount
            $newRemaining = $available - $toDeduct;
            Capsule::table('cb_credit_lots')
                ->where('id', $lot->id)
                ->update([
                    'remaining_amount' => $newRemaining,
                    'depleted_at' => $newRemaining <= 0 ? date('Y-m-d H:i:s') : null,
                ]);

            // Record the allocation
            $allocations[] = [
                'lot_id' => $lot->id,
                'lot_type' => $lot->lot_type,
                'amount' => $toDeduct,
                'lot_remaining_after' => $newRemaining,
            ];

            $remaining -= $toDeduct;
        }

        // Record in allocations log
        if (!empty($allocations)) {
            Capsule::table('cb_credit_allocations')->insert([
                'usage_date' => $usageDate,
                'total_amount' => $amount - $remaining, // Amount successfully allocated
                'description' => $description,
                'allocations' => json_encode($allocations),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'requested' => $amount,
            'allocated' => $amount - $remaining,
            'shortfall' => $remaining,
            'allocations' => $allocations,
        ];
    }

    /**
     * Get current balance by lot type.
     * 
     * @return array ['purchased' => X, 'bonus' => Y, 'total' => Z]
     */
    public static function getCurrentBalance(): array
    {
        self::ensureTables();

        $balances = Capsule::table('cb_credit_lots')
            ->select('lot_type', Capsule::raw('SUM(remaining_amount) as balance'))
            ->where('remaining_amount', '>', 0)
            ->groupBy('lot_type')
            ->pluck('balance', 'lot_type')
            ->toArray();

        return [
            'purchased' => (float)($balances[self::TYPE_PURCHASED] ?? 0),
            'bonus' => (float)($balances[self::TYPE_BONUS] ?? 0),
            'adjustment' => (float)($balances[self::TYPE_ADJUSTMENT] ?? 0),
            'total' => array_sum(array_map('floatval', $balances)),
        ];
    }

    /**
     * Get original totals by lot type.
     * 
     * @return array
     */
    public static function getOriginalTotals(): array
    {
        self::ensureTables();

        $totals = Capsule::table('cb_credit_lots')
            ->select('lot_type', Capsule::raw('SUM(original_amount) as total'))
            ->groupBy('lot_type')
            ->pluck('total', 'lot_type')
            ->toArray();

        return [
            'purchased' => (float)($totals[self::TYPE_PURCHASED] ?? 0),
            'bonus' => (float)($totals[self::TYPE_BONUS] ?? 0),
            'adjustment' => (float)($totals[self::TYPE_ADJUSTMENT] ?? 0),
            'total' => array_sum(array_map('floatval', $totals)),
        ];
    }

    /**
     * Get total consumed by lot type.
     * 
     * @return array
     */
    public static function getConsumed(): array
    {
        $original = self::getOriginalTotals();
        $remaining = self::getCurrentBalance();

        return [
            'purchased' => $original['purchased'] - $remaining['purchased'],
            'bonus' => $original['bonus'] - $remaining['bonus'],
            'adjustment' => $original['adjustment'] - $remaining['adjustment'],
            'total' => $original['total'] - $remaining['total'],
        ];
    }

    /**
     * Get active (non-depleted) lots.
     * 
     * @return array
     */
    public static function getActiveLots(): array
    {
        self::ensureTables();

        return Capsule::table('cb_credit_lots')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Get all lots with optional filtering.
     * 
     * @param bool $includeDepeleted Include fully consumed lots
     * @param int $limit
     * @return array
     */
    public static function getLots(bool $includeDepleted = true, int $limit = 100): array
    {
        self::ensureTables();

        $query = Capsule::table('cb_credit_lots')
            ->leftJoin('cb_credit_purchases', 'cb_credit_lots.purchase_id', '=', 'cb_credit_purchases.id')
            ->select([
                'cb_credit_lots.*',
                'cb_credit_purchases.pack_label',
                'cb_credit_purchases.receipt_no',
            ])
            ->orderBy('cb_credit_lots.created_at', 'desc');

        if (!$includeDepleted) {
            $query->where('cb_credit_lots.remaining_amount', '>', 0);
        }

        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Estimate days until credit depletion based on recent usage.
     * 
     * @param int $lookbackDays Days to calculate average usage
     * @return array ['daily_burn' => X, 'days_remaining' => Y, 'depletion_date' => 'YYYY-MM-DD']
     */
    public static function estimateRunway(int $lookbackDays = 30): array
    {
        self::ensureTables();

        $balance = self::getCurrentBalance();
        
        // Calculate average daily usage from cb_daily_balance
        $fromDate = date('Y-m-d', strtotime("-{$lookbackDays} days"));
        
        $usage = Capsule::table('cb_daily_balance')
            ->where('balance_date', '>=', $fromDate)
            ->sum('usage_amount');

        $days = Capsule::table('cb_daily_balance')
            ->where('balance_date', '>=', $fromDate)
            ->count();

        $dailyBurn = $days > 0 ? (float)$usage / $days : 0;
        $daysRemaining = $dailyBurn > 0 ? floor($balance['total'] / $dailyBurn) : null;
        $depletionDate = $daysRemaining !== null ? date('Y-m-d', strtotime("+{$daysRemaining} days")) : null;

        return [
            'current_balance' => $balance['total'],
            'daily_burn' => round($dailyBurn, 2),
            'days_remaining' => $daysRemaining,
            'depletion_date' => $depletionDate,
            'lookback_days' => $lookbackDays,
            'actual_days_with_data' => $days,
        ];
    }

    /**
     * Check if we're currently consuming bonus credits (purchased depleted).
     * 
     * @return bool
     */
    public static function isUsingBonusCredits(): bool
    {
        $balance = self::getCurrentBalance();
        return $balance['purchased'] <= 0 && $balance['bonus'] > 0;
    }

    /**
     * Ensure credit tracking tables exist.
     */
    private static function ensureTables(): void
    {
        // cb_credit_lots table
        if (!Capsule::schema()->hasTable('cb_credit_lots')) {
            Capsule::schema()->create('cb_credit_lots', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('purchase_id')->nullable();
                $table->enum('lot_type', ['purchased', 'bonus', 'adjustment'])->default('purchased');
                $table->decimal('original_amount', 12, 4);
                $table->decimal('remaining_amount', 12, 4);
                $table->dateTime('created_at');
                $table->dateTime('depleted_at')->nullable();
                $table->index('remaining_amount');
                $table->index('lot_type');
                $table->index('created_at');
            });
        }

        // cb_credit_allocations table (log of usage allocations)
        if (!Capsule::schema()->hasTable('cb_credit_allocations')) {
            Capsule::schema()->create('cb_credit_allocations', function ($table) {
                $table->bigIncrements('id');
                $table->date('usage_date');
                $table->decimal('total_amount', 12, 4);
                $table->string('description', 255)->nullable();
                $table->json('allocations')->nullable();
                $table->dateTime('created_at');
                $table->index('usage_date');
            });
        }
    }

    /**
     * Synchronize lots from existing purchases that don't have lots yet.
     * Useful for initial setup or after adding historical purchases.
     * 
     * @return int Number of purchases processed
     */
    public static function syncLotsFromPurchases(): int
    {
        self::ensureTables();

        // Find purchases without associated lots
        $purchases = Capsule::table('cb_credit_purchases')
            ->leftJoin('cb_credit_lots', 'cb_credit_purchases.id', '=', 'cb_credit_lots.purchase_id')
            ->whereNull('cb_credit_lots.id')
            ->select('cb_credit_purchases.id')
            ->get();

        $count = 0;
        foreach ($purchases as $p) {
            self::createLotsFromPurchase($p->id);
            $count++;
        }

        return $count;
    }
}
