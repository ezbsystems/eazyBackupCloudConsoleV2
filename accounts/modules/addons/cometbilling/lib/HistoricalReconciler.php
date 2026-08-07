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

    private const PERSIST_CHUNK = 200;

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

        ServiceIdentityResolver::loadIndex();
        BillingCadenceResolver::clearCache();
        LifecycleResolver::clearCache();
        CanonicalUsage::clearCache();
        ReversalIndex::warm($fromDate, $toDate);

        $coverage = SourceCoverageReporter::report($fromDate, $toDate);
        $coverageComplete = (bool) $coverage['complete_overlap'];

        $summary = self::emptySummary();
        $categoryBuckets = self::emptyCategoryBuckets();
        $findings = [];
        $afterId = 0;

        while (true) {
            $batch = self::fetchUsageChunk($fromDate, $toDate, $afterId, self::CHUNK_SIZE);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $afterId = (int) ($row->id ?? $afterId);
                $summary['charges_scanned']++;
                $evaluated = OverbillEvidenceEvaluator::evaluate($row, $coverageComplete);
                self::accumulateFinding($summary, $categoryBuckets, $findings, $evaluated, $includeGrace);
            }

            if (count($batch) < self::CHUNK_SIZE) {
                break;
            }
        }

        ReversalIndex::clear();

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
            $summary['include_grace'] = $includeGrace;
            $auditRunId = self::persistRun($fromDate, $toDate, $summary, $coverage, $findings);
        }

        return self::buildReportPayload($fromDate, $toDate, $summary, $categoryBuckets, $coverage, $uiRows, $findings, $auditRunId);
    }

    /**
     * Load the latest persisted audit for a date range (fast HTTP path).
     *
     * @return array<string, mixed>|null
     */
    public static function loadPersistedReport(string $fromDate, string $toDate, bool $includeGrace = false): ?array
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);
        if (!Capsule::schema()->hasTable('cb_audit_runs')) {
            return null;
        }

        $run = Capsule::table('cb_audit_runs')
            ->where('from_date', $fromDate)
            ->where('to_date', $toDate)
            ->orderBy('id', 'desc')
            ->first();

        if ($run === null) {
            return null;
        }

        $summary = json_decode((string) $run->summary, true);
        if (!is_array($summary)) {
            $summary = self::emptySummary();
        }
        $coverage = json_decode((string) ($run->coverage ?? ''), true);
        if (!is_array($coverage)) {
            $coverage = [];
        }

        $runIncludeGrace = (bool) ($summary['include_grace'] ?? false);
        $showGrace = $includeGrace || $runIncludeGrace;
        $rows = self::loadFindingsForRun((int) $run->id, $showGrace, self::UI_ROW_CAP);
        $totalFindings = self::countFindingsForRun((int) $run->id, $showGrace);
        $categoryBuckets = self::categoryBucketsFromRun((int) $run->id);

        return self::buildReportPayload(
            $fromDate,
            $toDate,
            $summary,
            $categoryBuckets,
            $coverage,
            $rows,
            [],
            (int) $run->id,
            $totalFindings
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadFindingsForRun(int $auditRunId, bool $includeGrace, ?int $limit = null): array
    {
        if (!Capsule::schema()->hasTable('cb_audit_findings')) {
            return [];
        }

        $query = Capsule::table('cb_audit_findings')
            ->where('audit_run_id', $auditRunId)
            ->orderBy('usage_date', 'desc')
            ->orderBy('id', 'desc');

        if (!$includeGrace) {
            $query->whereIn('verdict', ['confirmed', 'probable']);
        } else {
            $query->whereIn('verdict', ['confirmed', 'probable', 'review_required']);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $dbRows = $query->get();
        $dbRows = is_array($dbRows) ? $dbRows : $dbRows->all();

        return array_map([self::class, 'findingFromDbRow'], $dbRows);
    }

    public static function countFindingsForRun(int $auditRunId, bool $includeGrace): int
    {
        if (!Capsule::schema()->hasTable('cb_audit_findings')) {
            return 0;
        }

        $query = Capsule::table('cb_audit_findings')->where('audit_run_id', $auditRunId);
        if (!$includeGrace) {
            $query->whereIn('verdict', ['confirmed', 'probable']);
        } else {
            $query->whereIn('verdict', ['confirmed', 'probable', 'review_required']);
        }

        return (int) $query->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function collectOverbilledRowsForRun(int $auditRunId): array
    {
        return array_values(array_filter(
            self::loadFindingsForRun($auditRunId, false),
            static fn (array $r): bool => in_array($r['verdict'] ?? '', ['confirmed', 'probable'], true)
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function collectOverbilledRows(string $fromDate, string $toDate): array
    {
        $run = self::latestRunForRange($fromDate, $toDate);
        if ($run !== null) {
            return self::collectOverbilledRowsForRun((int) $run->id);
        }

        return [];
    }

    public static function buildCsvForRun(int $auditRunId, bool $includeGrace = true): string
    {
        $rows = self::loadFindingsForRun($auditRunId, $includeGrace);

        return self::rowsToCsv($rows);
    }

    public static function buildCsv(string $fromDate, string $toDate): string
    {
        $run = self::latestRunForRange($fromDate, $toDate);
        if ($run === null) {
            throw new \RuntimeException('No saved audit run for this date range. Run an audit first.');
        }

        return self::buildCsvForRun((int) $run->id, true);
    }

    public static function streamCsvForRun(int $auditRunId, ?string $fromDate = null, ?string $toDate = null): void
    {
        $csv = self::buildCsvForRun($auditRunId, true);
        if ($fromDate === null || $toDate === null) {
            $run = Capsule::table('cb_audit_runs')->where('id', $auditRunId)->first();
            $fromDate = $run ? (string) $run->from_date : 'unknown';
            $toDate = $run ? (string) $run->to_date : 'unknown';
        }
        self::emitCsvDownload($csv, $fromDate, $toDate);
    }

    public static function streamCsv(string $fromDate, string $toDate): void
    {
        $run = self::latestRunForRange($fromDate, $toDate);
        if ($run === null) {
            throw new \RuntimeException('No saved audit run for this date range. Run an audit first.');
        }
        self::streamCsvForRun((int) $run->id, (string) $run->from_date, (string) $run->to_date);
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
     * @param array<string, mixed> $summary
     * @param array<string, array<string, float|int>> $categoryBuckets
     * @param list<array<string, mixed>> $findings
     */
    private static function accumulateFinding(
        array &$summary,
        array &$categoryBuckets,
        array &$findings,
        array $evaluated,
        bool $includeGrace
    ): void {
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

    /**
     * @param array<string, mixed> $summary
     * @param array<string, array<string, float|int>> $categoryBuckets
     * @param list<array<string, mixed>> $uiRows
     * @param list<array<string, mixed>> $allFindings
     * @return array<string, mixed>
     */
    private static function buildReportPayload(
        string $fromDate,
        string $toDate,
        array $summary,
        array $categoryBuckets,
        array $coverage,
        array $uiRows,
        array $allFindings,
        ?int $auditRunId,
        ?int $totalFindingsOverride = null
    ): array {
        $totalFindings = $totalFindingsOverride ?? count($allFindings);
        $categories = [];
        foreach (ChargeCategoryResolver::CATEGORIES as $key => $label) {
            $bucket = $categoryBuckets[$key] ?? self::emptyCategoryBucket();
            if ($bucket['overbilled_count'] === 0 && $bucket['overbilled_amount'] === 0.0
                && ($bucket['expected_grace_count'] ?? 0) === 0
                && ($bucket['probable_count'] ?? 0) === 0) {
                continue;
            }
            $categories[$key] = [
                'label' => $label,
                'overbilled_count' => $bucket['overbilled_count'],
                'overbilled_amount' => round((float) $bucket['overbilled_amount'], 4),
                'probable_count' => $bucket['probable_count'] ?? 0,
                'probable_amount' => round((float) ($bucket['probable_amount'] ?? 0.0), 4),
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
                'ui_rows_truncated' => $totalFindings > self::UI_ROW_CAP,
                'total_findings' => $totalFindings,
            ]),
            'categories' => $categories,
            'rows' => $uiRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function findingFromDbRow(object $row): array
    {
        $evidence = json_decode((string) ($row->evidence ?? ''), true);
        if (!is_array($evidence)) {
            $evidence = [];
        }
        $reasons = json_decode((string) ($row->confidence_reasons ?? ''), true);
        if (!is_array($reasons)) {
            $reasons = [];
        }

        $category = (string) ($row->category ?? 'other');
        $lifecycle = $evidence['lifecycle'] ?? [];
        $cadence = $evidence['cadence'] ?? [];
        $identity = $evidence['identity'] ?? [];

        return [
            'usage_id' => (int) ($row->usage_id ?? 0),
            'usage_date' => (string) $row->usage_date,
            'account' => (string) ($row->account ?? ''),
            'device_id' => (string) ($row->device_id ?? ''),
            'device_name' => $identity['device_name'] ?? null,
            'category' => $category,
            'category_label' => ChargeCategoryResolver::label($category),
            'item_desc' => (string) ($row->item_desc ?? ''),
            'amount' => (float) $row->amount,
            'debit_evidence' => (string) ($row->debit_evidence ?? ''),
            'billing_verdict' => (string) ($row->billing_verdict ?? ''),
            'verdict' => (string) $row->verdict,
            'expected_billing_end' => $row->expected_billing_end,
            'registered_at' => $lifecycle['registered_at'] ?? null,
            'revoked_at' => $lifecycle['revoked_at'] ?? null,
            'cycle' => $cadence['mode'] ?? null,
            'cycle_days' => $cadence['cycle_days'] ?? null,
            'next_due_date' => $cadence['next_due_date'] ?? null,
            'confidence_reasons' => $reasons,
            'evidence' => $evidence,
        ];
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

        $batch = [];
        foreach ($findings as $f) {
            if (!in_array($f['verdict'] ?? '', ['confirmed', 'probable', 'review_required'], true)) {
                continue;
            }
            $batch[] = [
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
            ];
            if (count($batch) >= self::PERSIST_CHUNK) {
                Capsule::table('cb_audit_findings')->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            Capsule::table('cb_audit_findings')->insert($batch);
        }

        return $runId;
    }

    /**
     * @return list<object>
     */
    private static function fetchUsageChunk(string $fromDate, string $toDate, int $afterId, int $limit): array
    {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return [];
        }

        $query = CanonicalUsage::query()
            ->whereBetween('usage_date', [$fromDate, $toDate])
            ->orderBy('id');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $results = $query->limit($limit)->get();

        return is_array($results) ? $results : $results->all();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function rowsToCsv(array $rows): string
    {
        $headers = [
            'verdict', 'debit_evidence', 'billing_verdict', 'usage_date', 'account', 'device_id',
            'category', 'item_desc', 'amount', 'overbill_amount', 'revoked_at', 'registered_at',
            'expected_billing_end', 'cycle', 'cycle_days', 'next_due_date', 'confidence_reasons',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            if (!in_array($row['verdict'] ?? '', ['confirmed', 'probable', 'review_required'], true)) {
                continue;
            }
            $line = [];
            foreach ($headers as $h) {
                if ($h === 'confidence_reasons') {
                    $line[] = implode('; ', $row['confidence_reasons'] ?? []);
                } elseif ($h === 'overbill_amount') {
                    $line[] = in_array($row['verdict'] ?? '', ['confirmed', 'probable'], true)
                        ? ($row['amount'] ?? 0)
                        : 0;
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

    private static function emitCsvDownload(string $csv, string $fromDate, string $toDate): void
    {
        $filename = 'comet-audit-overbill-' . $fromDate . '_to_' . $toDate . '.csv';

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

    public static function latestRunForRange(string $fromDate, string $toDate): ?object
    {
        if (!Capsule::schema()->hasTable('cb_audit_runs')) {
            return null;
        }

        return Capsule::table('cb_audit_runs')
            ->where('from_date', self::normalizeDate($fromDate))
            ->where('to_date', self::normalizeDate($toDate))
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * @return array<string, int|float>
     */
    private static function emptySummary(): array
    {
        return [
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
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private static function emptyCategoryBuckets(): array
    {
        $buckets = [];
        foreach (array_keys(ChargeCategoryResolver::CATEGORIES) as $key) {
            $buckets[$key] = self::emptyCategoryBucket();
        }

        return $buckets;
    }

    /**
     * @return array{overbilled_count: int, overbilled_amount: float, probable_count: int, probable_amount: float, expected_grace_count: int}
     */
    private static function emptyCategoryBucket(): array
    {
        return [
            'overbilled_count' => 0,
            'overbilled_amount' => 0.0,
            'probable_count' => 0,
            'probable_amount' => 0.0,
            'expected_grace_count' => 0,
        ];
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private static function categoryBucketsFromRun(int $auditRunId): array
    {
        $buckets = self::emptyCategoryBuckets();
        if (!Capsule::schema()->hasTable('cb_audit_findings')) {
            return $buckets;
        }

        $rows = Capsule::table('cb_audit_findings')
            ->where('audit_run_id', $auditRunId)
            ->get(['category', 'verdict', 'amount']);

        $rows = is_array($rows) ? $rows : $rows->all();
        foreach ($rows as $row) {
            $cat = (string) ($row->category ?? 'other');
            if (!isset($buckets[$cat])) {
                $buckets[$cat] = self::emptyCategoryBucket();
            }
            $verdict = (string) $row->verdict;
            $amount = (float) $row->amount;
            if ($verdict === 'confirmed') {
                $buckets[$cat]['overbilled_count']++;
                $buckets[$cat]['overbilled_amount'] += $amount;
            } elseif ($verdict === 'probable') {
                $buckets[$cat]['probable_count'] = ($buckets[$cat]['probable_count'] ?? 0) + 1;
                $buckets[$cat]['probable_amount'] = ($buckets[$cat]['probable_amount'] ?? 0) + $amount;
            } elseif ($verdict === 'review_required') {
                // counted in summary only
            }
        }

        return $buckets;
    }

    private static function earliestUsageDate(): ?string
    {
        if (!Capsule::schema()->hasTable('cb_credit_usage')) {
            return null;
        }
        $min = CanonicalUsage::query()->min('usage_date');

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
