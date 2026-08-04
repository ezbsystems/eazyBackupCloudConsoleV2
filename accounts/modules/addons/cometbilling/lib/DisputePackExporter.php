<?php
namespace CometBilling;

/**
 * Dispute-ready evidence packs for Confirmed overbill findings.
 */
class DisputePackExporter
{
    private const CHUNK_SIZE = 5000;

    /**
     * @return list<array<string, mixed>>
     */
    public static function collectConfirmed(string $fromDate, string $toDate): array
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

        $coverage = SourceCoverageReporter::report($fromDate, $toDate);
        $coverageComplete = (bool) $coverage['complete_overlap'];

        $packs = [];
        $offset = 0;
        while (true) {
            $batch = self::fetchUsageChunk($fromDate, $toDate, $offset, self::CHUNK_SIZE);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $evaluated = OverbillEvidenceEvaluator::evaluate($row, $coverageComplete);
                if (($evaluated['verdict'] ?? '') !== 'confirmed') {
                    continue;
                }
                $packs[] = self::packFromFinding($evaluated);
            }

            if (count($batch) < self::CHUNK_SIZE) {
                break;
            }
            $offset += self::CHUNK_SIZE;
        }

        usort($packs, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['debit_date'] ?? $b['usage_date'] ?? ''), (string) ($a['debit_date'] ?? $a['usage_date'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['account'], (string) $b['account']);
        });

        return $packs;
    }

    /**
     * @param array<string, mixed> $finding
     * @return array<string, mixed>
     */
    public static function packFromFinding(array $finding): array
    {
        $raw = $finding['evidence']['raw_row'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $packsUsed = (string) ($finding['evidence']['packs_used_raw']
            ?? $raw['Packs Used']
            ?? $raw['PacksUsed']
            ?? '');
        $packParsed = $finding['evidence']['packs_used_parsed'] ?? null;
        if (!is_array($packParsed)) {
            $packParsed = PackUsageParser::parse($packsUsed !== '' ? $packsUsed : null);
        }
        $packDenomination = $packParsed['primary_denomination'] ?? null;
        $packLabel = $packsUsed !== '' ? $packsUsed : '(none)';
        if ($packDenomination !== null) {
            $packLabel = number_format((int) $packDenomination) . ' Dollars pack';
            if ($packsUsed !== '' && $packsUsed !== $packLabel) {
                $packLabel .= ' (Packs Used: ' . $packsUsed . ')';
            }
        }

        $amountUsed = number_format((float) ($finding['amount'] ?? 0), 2, '.', '');
        $deviceId = (string) ($finding['device_id'] ?? '');
        $account = (string) ($finding['account'] ?? '');
        $item = (string) ($finding['item_desc'] ?? ($raw['Item'] ?? ''));
        $debitDate = (string) ($finding['usage_date'] ?? '');
        $revokedAt = (string) ($finding['revoked_at'] ?? '');
        $expectedEnd = (string) ($finding['expected_billing_end'] ?? '');
        $cycle = (string) ($finding['cycle'] ?? '');
        $cycleDays = (int) ($finding['cycle_days'] ?? 0);
        $nextDue = (string) ($finding['next_due_date'] ?? '');
        $deviceName = (string) ($finding['device_name'] ?? '');
        $identity = $finding['evidence']['identity'] ?? [];
        $reversal = $finding['evidence']['reversal'] ?? null;

        $reversalStatus = 'none_found';
        $reversalDetail = '';
        if (is_array($reversal) && $reversal !== []) {
            $reversalStatus = 'offsetting_record_found';
            $reversalDetail = json_encode($reversal);
        }

        $claim = sprintf(
            '%s Comet debited $%s / %s for device %s after that device was revoked on %s and after its %s paid-through date of %s, with no later offsetting credit found.',
            $debitDate,
            $amountUsed,
            $packsUsed !== '' ? $packsUsed : 'unknown pack',
            $deviceId !== '' ? $deviceId : '(unknown)',
            $revokedAt !== '' ? $revokedAt : '(unknown)',
            $cycle !== '' ? $cycle : 'billing-cycle',
            $expectedEnd !== '' ? $expectedEnd : '(unknown)'
        );

        return [
            'claim' => $claim,
            'usage_id' => (int) ($finding['usage_id'] ?? 0),
            'account' => $account,
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'item_desc' => $item,
            'category' => (string) ($finding['category_label'] ?? $finding['category'] ?? ''),
            'debit_date' => $debitDate,
            'usage_date' => $debitDate,
            'amount_used' => $amountUsed,
            'packs_used' => $packsUsed,
            'pack_debited' => $packLabel,
            'debit_evidence' => (string) ($finding['debit_evidence'] ?? ''),
            'revoked_at' => $revokedAt,
            'registered_at' => (string) ($finding['registered_at'] ?? ''),
            'cycle' => $cycle,
            'cycle_days' => $cycleDays,
            'next_due_date' => $nextDue,
            'expected_billing_end' => $expectedEnd,
            'billing_verdict' => (string) ($finding['billing_verdict'] ?? ''),
            'identity_status' => (string) ($identity['status'] ?? ''),
            'identity_match_method' => (string) ($identity['match_method'] ?? ''),
            'reversal_status' => $reversalStatus,
            'reversal_detail' => $reversalDetail,
            'evidence_1_comet_debit' => sprintf(
                'debit date=%s account=%s device=%s item=%s',
                $debitDate,
                $account,
                $deviceId,
                $item
            ),
            'evidence_2_amount_pack' => sprintf(
                'amount=$%s pack_debited=%s',
                $amountUsed,
                $packLabel
            ),
            'evidence_3_revocation' => sprintf(
                'Device revoked %s (name=%s, registered=%s)',
                $revokedAt !== '' ? $revokedAt : '(unknown)',
                $deviceName !== '' ? $deviceName : '(unknown)',
                (string) ($finding['registered_at'] ?? '') !== '' ? (string) $finding['registered_at'] : '(unknown)'
            ),
            'evidence_4_billing_period' => sprintf(
                'Cycle=%s (%d days), next_due=%s, expected_billing_end=%s',
                $cycle !== '' ? $cycle : '(unknown)',
                $cycleDays,
                $nextDue !== '' ? $nextDue : '(unknown)',
                $expectedEnd !== '' ? $expectedEnd : '(unknown)'
            ),
            'evidence_5_after_expected_end' => sprintf(
                'Debit date %s is after expected billing end %s',
                $debitDate,
                $expectedEnd !== '' ? $expectedEnd : '(unknown)'
            ),
            'evidence_6_no_reversal' => $reversalStatus === 'none_found'
                ? 'No offsetting negative usage or refund matched after the charge'
                : 'Offsetting record present: ' . $reversalDetail,
        ];
    }

    public static function buildCsv(string $fromDate, string $toDate): string
    {
        $packs = self::collectConfirmed($fromDate, $toDate);
        $headers = [
            'claim',
            'account',
            'device_id',
            'device_name',
            'item_desc',
            'category',
            'debit_date',
            'amount_used',
            'packs_used',
            'pack_debited',
            'debit_evidence',
            'revoked_at',
            'registered_at',
            'cycle',
            'cycle_days',
            'next_due_date',
            'expected_billing_end',
            'billing_verdict',
            'reversal_status',
            'evidence_1_comet_debit',
            'evidence_2_amount_pack',
            'evidence_3_revocation',
            'evidence_4_billing_period',
            'evidence_5_after_expected_end',
            'evidence_6_no_reversal',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($packs as $pack) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $pack[$h] ?? '';
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv === false ? '' : $csv;
    }

    public static function buildHtml(string $fromDate, string $toDate): string
    {
        $fromDate = self::normalizeDate($fromDate);
        $toDate = self::normalizeDate($toDate);
        $packs = self::collectConfirmed($fromDate, $toDate);
        $generatedAt = gmdate('Y-m-d H:i:s') . ' UTC';
        $totalAmount = 0.0;
        foreach ($packs as $pack) {
            $totalAmount += (float) ($pack['amount_used'] ?? 0);
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>Comet Overbilling Dispute Pack ' . htmlspecialchars($fromDate) . ' to ' . htmlspecialchars($toDate) . '</title>';
        $html .= '<style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:24px;color:#111;line-height:1.45}
            h1{font-size:22px;margin:0 0 8px}
            .meta{color:#555;margin-bottom:20px;font-size:13px}
            .summary{margin:16px 0 24px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0}
            .case{border:1px solid #d1d5db;padding:16px;margin:0 0 18px;page-break-inside:avoid}
            .case h2{font-size:16px;margin:0 0 10px}
            .claim{background:#fff7ed;border:1px solid #fed7aa;padding:10px 12px;margin:0 0 12px;font-size:14px}
            dl{display:grid;grid-template-columns:180px 1fr;gap:6px 12px;margin:0;font-size:13px}
            dt{font-weight:600;color:#374151}
            dd{margin:0}
            .evidence{margin-top:12px}
            .evidence li{margin:0 0 6px}
            .actions{margin:0 0 18px}
            @media print{.actions{display:none} body{margin:12px}}
        </style></head><body>';
        $html .= '<div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>';
        $html .= '<h1>Comet Overbilling Dispute Pack</h1>';
        $html .= '<div class="meta">Period: ' . htmlspecialchars($fromDate) . ' to ' . htmlspecialchars($toDate)
            . ' &middot; Generated: ' . htmlspecialchars($generatedAt)
            . '<br>Evidence is assembled from Comet Bill History, device revocation records, and observed billing cadence. '
            . 'This proves Comet recorded a pack debit after the paid-through date; Comet does not expose an independent account-balance API.</div>';
        $html .= '<div class="summary"><strong>' . count($packs) . '</strong> confirmed overbill charge(s); '
            . 'total Amount Used <strong>$' . number_format($totalAmount, 2) . '</strong></div>';

        if ($packs === []) {
            $html .= '<p>No confirmed overbill findings in this period.</p>';
        }

        foreach ($packs as $i => $pack) {
            $n = $i + 1;
            $html .= '<section class="case">';
            $html .= '<h2>#' . $n . ' ' . htmlspecialchars((string) $pack['account'])
                . ' — ' . htmlspecialchars((string) $pack['item_desc'])
                . ' — $' . htmlspecialchars((string) $pack['amount_used']) . '</h2>';
            $html .= '<div class="claim">' . htmlspecialchars((string) $pack['claim']) . '</div>';
            $html .= '<dl>';
            $html .= '<dt>Account</dt><dd>' . htmlspecialchars((string) $pack['account']) . '</dd>';
            $html .= '<dt>Device ID</dt><dd>' . htmlspecialchars((string) $pack['device_id']) . '</dd>';
            $html .= '<dt>Device name</dt><dd>' . htmlspecialchars((string) $pack['device_name']) . '</dd>';
            $html .= '<dt>Item</dt><dd>' . htmlspecialchars((string) $pack['item_desc']) . '</dd>';
            $html .= '<dt>Category</dt><dd>' . htmlspecialchars((string) $pack['category']) . '</dd>';
            $html .= '</dl>';
            $html .= '<ol class="evidence">';
            $html .= '<li><strong>Comet debit:</strong> ' . htmlspecialchars((string) $pack['evidence_1_comet_debit']) . '</li>';
            $html .= '<li><strong>Amount / pack:</strong> ' . htmlspecialchars((string) $pack['evidence_2_amount_pack']) . '</li>';
            $html .= '<li><strong>Revocation:</strong> ' . htmlspecialchars((string) $pack['evidence_3_revocation']) . '</li>';
            $html .= '<li><strong>Billing period:</strong> ' . htmlspecialchars((string) $pack['evidence_4_billing_period']) . '</li>';
            $html .= '<li><strong>After expected end:</strong> ' . htmlspecialchars((string) $pack['evidence_5_after_expected_end']) . '</li>';
            $html .= '<li><strong>No reversal:</strong> ' . htmlspecialchars((string) $pack['evidence_6_no_reversal']) . '</li>';
            $html .= '</ol>';
            $html .= '</section>';
        }

        $html .= '</body></html>';

        return $html;
    }

    public static function streamCsv(string $fromDate, string $toDate): void
    {
        $csv = self::buildCsv($fromDate, $toDate);
        $from = self::normalizeDate($fromDate);
        $to = self::normalizeDate($toDate);
        $filename = 'comet-dispute-pack-' . $from . '_to_' . $to . '.csv';

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

    public static function streamHtml(string $fromDate, string $toDate): void
    {
        $html = self::buildHtml($fromDate, $toDate);
        $from = self::normalizeDate($fromDate);
        $to = self::normalizeDate($toDate);
        $filename = 'comet-dispute-pack-' . $from . '_to_' . $to . '.html';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    }

    /**
     * @return list<object>
     */
    private static function fetchUsageChunk(string $fromDate, string $toDate, int $offset, int $limit): array
    {
        if (!\WHMCS\Database\Capsule::schema()->hasTable('cb_credit_usage')) {
            return [];
        }

        $query = CanonicalUsage::query()
            ->whereBetween('usage_date', [$fromDate, $toDate])
            ->orderBy('id');

        if ($offset > 0) {
            $query->offset($offset);
        }

        $results = $query->limit($limit)->get();

        return is_array($results) ? $results : $results->all();
    }

    private static function normalizeDate(string $date): string
    {
        $ts = strtotime($date);

        return $ts ? gmdate('Y-m-d', $ts) : gmdate('Y-m-d');
    }
}
