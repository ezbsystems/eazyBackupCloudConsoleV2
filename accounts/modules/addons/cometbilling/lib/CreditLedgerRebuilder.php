<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Deterministic credit ledger reconstruction from Bill History + usage events.
 */
class CreditLedgerRebuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function rebuild(string $openingDate, ?string $closingDate = null, bool $dryRun = false): array
    {
        self::ensureSchema();

        $closingDate = $closingDate ?? gmdate('Y-m-d');
        if ($openingDate > $closingDate) {
            [$openingDate, $closingDate] = [$closingDate, $openingDate];
        }

        $batchId = null;
        if (!$dryRun) {
            $batchId = (int) Capsule::table('cb_ledger_rebuild_batches')->insertGetId([
                'started_at' => gmdate('Y-m-d H:i:s'),
                'opening_date' => $openingDate,
                'closing_date' => $closingDate,
                'opening_credit' => 0,
                'is_complete' => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $openingCredit = self::openingCreditBefore($openingDate);
        $purchaseCoverage = self::purchaseCoverage();
        $isComplete = $purchaseCoverage['earliest'] !== null
            && $purchaseCoverage['earliest'] <= $openingDate;

        $usageRows = Capsule::table('cb_credit_usage')
            ->whereBetween('usage_date', [$openingDate, $closingDate])
            ->orderBy('usage_date')
            ->orderBy('id')
            ->get();

        $running = $openingCredit;
        $totalUsage = 0.0;
        $totalPurchases = 0.0;
        $allocations = [];
        $daily = [];

        if (!$dryRun) {
            Capsule::table('cb_credit_lots')->update([
                'remaining_amount' => Capsule::raw('original_amount'),
                'depleted_at' => null,
            ]);
            Capsule::table('cb_credit_usage_allocations')->delete();
            Capsule::table('cb_credit_allocations')->delete();
        }

        foreach ($usageRows as $usage) {
            $amt = (float) $usage->amount;
            if ($amt <= 0) {
                continue;
            }
            $totalUsage += $amt;
            $day = (string) $usage->usage_date;
            $daily[$day] = ($daily[$day] ?? 0) + $amt;

            if (!$dryRun) {
                $eventAlloc = self::allocateEvent((int) $usage->id, $amt, $batchId);
                $allocations[] = $eventAlloc;
            }
            $running -= $amt;
        }

        $purchases = Capsule::table('cb_credit_purchases')
            ->whereBetween(Capsule::raw('DATE(purchased_at)'), [$openingDate, $closingDate])
            ->get();

        foreach ($purchases as $purchase) {
            $credit = (float) $purchase->credit_amount + (float) $purchase->bonus_credit;
            if (Capsule::schema()->hasColumn('cb_credit_purchases', 'record_type')
                && ($purchase->record_type ?? 'purchase') === 'refund') {
                $credit = -abs($credit);
            }
            $totalPurchases += $credit;
            $running += $credit;
        }

        $validation = [
            'opening_credit' => $openingCredit,
            'total_purchases' => round($totalPurchases, 4),
            'total_usage' => round($totalUsage, 4),
            'closing_credit' => round($running, 4),
            'usage_event_count' => count($usageRows),
            'purchase_coverage_complete' => $isComplete,
            'purchase_coverage' => $purchaseCoverage,
        ];

        if (!$dryRun) {
            self::rebuildDailyBalance($openingDate, $closingDate, $openingCredit);
            Capsule::table('cb_ledger_rebuild_batches')->where('id', $batchId)->update([
                'completed_at' => gmdate('Y-m-d H:i:s'),
                'closing_credit' => number_format($running, 4, '.', ''),
                'is_complete' => $isComplete ? 1 : 0,
                'validation' => json_encode($validation),
            ]);
            Settings::setKv('last_balance_recompute_date', $closingDate);
            Settings::setKv('ledger_rebuild_complete', $isComplete ? '1' : '0');
        }

        return [
            'batch_id' => $batchId,
            'dry_run' => $dryRun,
            'validation' => $validation,
            'allocations_created' => count($allocations),
        ];
    }

    /**
     * @return array{earliest: ?string, latest: ?string, count: int}
     */
    public static function purchaseCoverage(): array
    {
        if (!Capsule::schema()->hasTable('cb_credit_purchases')) {
            return ['earliest' => null, 'latest' => null, 'count' => 0];
        }

        $min = Capsule::table('cb_credit_purchases')->min('purchased_at');
        $max = Capsule::table('cb_credit_purchases')->max('purchased_at');
        $count = (int) Capsule::table('cb_credit_purchases')->count();

        return [
            'earliest' => $min ? substr((string) $min, 0, 10) : null,
            'latest' => $max ? substr((string) $max, 0, 10) : null,
            'count' => $count,
        ];
    }

    private static function openingCreditBefore(string $beforeDate): float
    {
        $purchases = (float) Capsule::table('cb_credit_purchases')
            ->where('purchased_at', '<', $beforeDate . ' 00:00:00')
            ->sum(Capsule::raw('credit_amount + bonus_credit'));
        $usage = (float) Capsule::table('cb_credit_usage')
            ->where('usage_date', '<', $beforeDate)
            ->sum('amount');

        return $purchases - $usage;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function allocateEvent(int $usageId, float $amount, ?int $batchId): array
    {
        $remaining = $amount;
        $eventAlloc = [];

        $lots = Capsule::table('cb_credit_lots')
            ->where('remaining_amount', '>', 0)
            ->orderByRaw("FIELD(lot_type, 'purchased', 'adjustment', 'bonus')")
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $available = (float) $lot->remaining_amount;
            $deduct = min($available, $remaining);
            $newRemaining = $available - $deduct;

            Capsule::table('cb_credit_lots')->where('id', $lot->id)->update([
                'remaining_amount' => number_format($newRemaining, 4, '.', ''),
                'depleted_at' => $newRemaining <= 0 ? gmdate('Y-m-d H:i:s') : null,
            ]);

            Capsule::table('cb_credit_usage_allocations')->insert([
                'usage_id' => $usageId,
                'lot_id' => $lot->id,
                'amount' => number_format($deduct, 4, '.', ''),
                'lot_remaining_after' => number_format($newRemaining, 4, '.', ''),
                'rebuild_batch_id' => $batchId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $eventAlloc[] = [
                'lot_id' => $lot->id,
                'amount' => $deduct,
            ];
            $remaining -= $deduct;
        }

        return $eventAlloc;
    }

    private static function rebuildDailyBalance(string $start, string $end, float $opening): void
    {
        $running = $opening;
        $period = new \DatePeriod(
            new \DateTime($start),
            new \DateInterval('P1D'),
            (new \DateTime($end))->modify('+1 day')
        );

        foreach ($period as $d) {
            $day = $d->format('Y-m-d');
            $purchases = (float) Capsule::table('cb_credit_purchases')
                ->whereDate('purchased_at', $day)
                ->sum(Capsule::raw('credit_amount + bonus_credit'));
            $usageAmt = (float) Capsule::table('cb_credit_usage')
                ->where('usage_date', $day)
                ->sum('amount');

            $open = $running;
            $close = $open + $purchases - $usageAmt;
            $running = $close;

            Capsule::table('cb_daily_balance')->updateOrInsert(
                ['balance_date' => $day],
                [
                    'opening_credit' => number_format($open, 4, '.', ''),
                    'purchases_credit' => number_format($purchases, 4, '.', ''),
                    'usage_amount' => number_format($usageAmt, 4, '.', ''),
                    'closing_credit' => number_format($close, 4, '.', ''),
                    'recomputed_at' => gmdate('Y-m-d H:i:s'),
                ]
            );

            if ($usageAmt > 0) {
                CreditLedger::allocateUsage($usageAmt, $day, 'Rebuilt ledger allocation');
            }
        }
    }

    private static function ensureSchema(): void
    {
        $migrate = SchemaMigrator::ensureLatest();
        if (!$migrate['ok'] || !Capsule::schema()->hasTable('cb_ledger_rebuild_batches')) {
            throw new \RuntimeException(
                'Ledger rebuild schema missing: ' . ($migrate['error'] ?? 'run SchemaMigrator::ensureLatest()')
            );
        }
    }
}
