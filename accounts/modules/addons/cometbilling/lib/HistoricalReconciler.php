<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Audit-grade historical overbill analysis with persisted evidence findings.
 */
class HistoricalReconciler
{
    private const UI_ROW_CAP = 2000;

    private const CHUNK_SIZE = 5000;

    /**
     * @return array<string, mixed>
     */
    public static function report(string $fromDate, string $toDate, bool $includeGrace = false, bool $persist = false): array
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);
        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        // Warm shared caches once per request.
        ServiceIdentityResolver::loadIndex();
        BillingCadenceResolver::clearCache();
        LifecycleResolver::clearCache();

        $coverage = SourceCoverageReporter::report($fromDate, $toDate);
        $coverageComplete = (bool) $coverage['complete_overlap'];

        $summary = [
            'charges_scanned' => 0,
            'confirmed_count' => 0,
            'confirmed_amount' => 0.0,
            'probable_count' => 0,
            'probable_amount' => 0.0,
            'review_required_count' => 0,
            'not_overbilled_count' => 0,
            'reversed_count' => 0,
            'unmatched_device_count' => 0,
            'expected_grace_count' => 0,
            'overbilled_count' => 0,
            'overbilled_amount' => 0.0,
            'matched_revoked' => 0,
        ];

        $categoryBuckets = self::emptyCategoryBuckets();
        $findings = [];
        $offset = 0;

        while (true) {
            $batch = self::fetchUsageChunk($fromDate, $toDate, $offset, self::CHUNK_SIZE);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $summary['charges_scanned']++;
                $evaluated = OverbillEvidenceEvaluator::evaluate($row, $coverageComplete);

                if (($evaluated['evidence']['identity']['status'] ?? '') === 'unmatched'
                    && empty($evaluated['device_id'])) {
                    $summary['unmatched_device_count']++;
                }

                if (!empty($evaluated['revoked_at'])) {
                    $summary['matched_revoked']++;
                }

                $verdict = $evaluated['verdict'];
                $cat = $evaluated['category'];

                if ($verdict === 'confirmed') {
                    $summary['confirmed_count']++;
                    $summary['confirmed_amount'] += (float) $evaluated['amount'];
                    $summary['overbilled_count']++;
                    $summary['overbilled_amount'] += (float) $evaluated['amount'];
                    $categoryBuckets[$cat]['overbilled_count']++;
                    $categoryBuckets[$cat]['overbilled_amount'] += (float) $evaluated['amount'];
                    $findings[] = $evaluated;
                } elseif ($verdict === 'probable') {
                    $summary['probable_count']++;
                    $summary['probable_amount'] += (float) $evaluated['amount'];
                    $categoryBuckets[$cat]['probable_count'] = ($categoryBuckets[$cat]['probable_count'] ?? 0) + 1;
                    $categoryBuckets[$cat]['probable_amount'] = ($categoryBuckets[$cat]['probable_amount'] ?? 0) + (float) $evaluated['amount'];
                    $findings[] = $evaluated;
                } elseif ($verdict === 'review_required' && $evaluated['billing_verdict'] === 'after_expected_end') {
                    $summary['review_required_count']++;
                    if ($includeGrace) {
                        $findings[] = $evaluated;
                    }
                } elseif ($verdict === 'not_overbilled') {
                    $summary['not_overbilled_count']++;
                    $summary['expected_grace_count']++;
                    $categoryBuckets[$cat]['expected_grace_count']++;
                    if ($includeGrace) {
                        $findings[] = $evaluated;
                    }
                } elseif ($verdict === 'reversed') {
                    $summary['reversed_count']++;
                } else {
                    $summary['review_required_count']++;
                }
            }

            if (count($batch) < self::CHUNK_SIZE) {
                break;
            }
            $offset += self::CHUNK_SIZE;
        }

        usort($findings, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['usage_date'], (string) $a['usage_date']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['device_id'] ?? ''), (string) ($b['device_id'] ?? ''));
        });

        $uiRows = array_slice($findings, 0, self::UI_ROW_CAP);
        $auditRunId = null;

        if ($persist && Capsule::schema()->hasTable('cb_audit_runs')) {
            $auditRunId = self::persistRun($fromDate, $toDate, $summary, $coverage, $findings);
        }

        $categories = [];
        foreach (ChargeCategoryResolver::CATEGORIES as $key => $label) {
            $bucket = $categoryBuckets[$key];
            if ($bucket['overbilled_count'] === 0 && $bucket['overbilled_amount'] === 0.0
                && ($bucket['expected_grace_count'] ?? 0) === 0
                && ($bucket['probable_count'] ?? 0) === 0) {
                continue;
            }
            $categories[$key] = [
                'label' => $label,
                'overbilled_count' => $bucket['overbilled_count'],
                'overbilled_amount' => round($bucket['overbilled_amount'], 4),
                'probable_count' => $bucket['probable_count'] ?? 0,
                'probable_amount' => round($bucket['probable_amount'] ?? 0.0, 4),
                'expected_grace_count' => $bucket['expected_grace_count'],
            ];
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'audit_run_id' => $auditRunId,
            'coverage' => $coverage,
            'summary' => array_merge($summary, [
                'ui_row_cap' => self::UI_ROW_CAP,
                'ui_rows_shown' => count($uiRows),
                'ui_rows_truncated' => count($findings) > self::UI_ROW_CAP,
            ]),
            'categories' => $categories,
            'rows' => $uiRows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function collectOverbilledRows(string $fromDate, string $toDate): array
    {
        $report = self::report($fromDate, $toDate, false, false);

        return array_values(array_filter($report['rows'], static fn (array $r): bool => in_array(
            $r['verdict'] ?? '',
            ['confirmed', 'probable'],
            true
        )));
    }

    public static function buildCsv(string $fromDate, string $toDate): string
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);
        $report = self::report($fromDate, $toDate, true, false);

        $headers = [
            'verdict', 'debit_evidence', 'billing_verdict', 'usage_date', 'account', 'device_id',
            'category', 'item_desc', 'amount', 'overbill_amount', 'revoked_at', 'registered_at',
            'expected_billing_end', 'cycle', 'cycle_days', 'next_due_date', 'confidence_reasons',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($report['rows'] as $row) {
            if (!in_array($row['verdict'] ?? '', ['confirmed', 'probable', 'review_required'], true)) {
                continue;
            }
            $line = [];
            foreach ($headers as $h) {
                if ($h === 'confidence_reasons') {
                    $line[] = implode('; ', $row['confidence_reasons'] ?? []);
                } else {
                    $line[] = $row[$h] ?? '';
                }
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
        $filename = 'comet-audit-overbill-' . $from . '_to_' . $to . '.csv';

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

    public static function categorizeCharge(string $itemType, ?string $itemDesc): string
    {
        return ChargeCategoryResolver::fromUsageRow($itemType, $itemDesc);
    }

    /**
     * @param list<array<string, mixed>> $findings
     */
    private static function persistRun(
        string $fromDate,
        string $toDate,
        array $summary,
        array $coverage,
        array $findings
    ): ?int {
        $runId = (int) Capsule::table('cb_audit_runs')->insertGetId([
            'run_at' => gmdate('Y-m-d H:i:s'),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'summary' => json_encode($summary),
            'coverage' => json_encode($coverage),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        foreach ($findings as $f) {
            if (!in_array($f['verdict'] ?? '', ['confirmed', 'probable', 'review_required'], true)) {
                continue;
            }
            Capsule::table('cb_audit_findings')->insert([
                'audit_run_id' => $runId,
                'usage_id' => $f['usage_id'] ?? null,
                'verdict' => $f['verdict'],
                'debit_evidence' => $f['debit_evidence'],
                'billing_verdict' => $f['billing_verdict'],
                'amount' => number_format((float) $f['amount'], 4, '.', ''),
                'account' => $f['account'],
                'device_id' => $f['device_id'],
                'category' => $f['category'],
                'usage_date' => $f['usage_date'],
                'item_desc' => $f['item_desc'],
                'expected_billing_end' => $f['expected_billing_end'],
                'confidence_reasons' => json_encode($f['confidence_reasons'] ?? []),
                'evidence' => json_encode($f['evidence'] ?? []),
            ]);
        }

        return $runId;
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
            ->orderBy('id');

        if ($offset > 0) {
            $query->offset($offset);
        }

        $results = $query->limit($limit)->get();

        return is_array($results) ? $results : $results->all();
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private static function emptyCategoryBuckets(): array
    {
        $buckets = [];
        foreach (array_keys(ChargeCategoryResolver::CATEGORIES) as $key) {
            $buckets[$key] = [
                'overbilled_count' => 0,
                'overbilled_amount' => 0.0,
                'probable_count' => 0,
                'probable_amount' => 0.0,
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
