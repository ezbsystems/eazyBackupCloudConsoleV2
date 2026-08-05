<?php
namespace CometBilling;

/**
 * Dispute-ready evidence packs for Confirmed overbill findings.
 */
class DisputePackExporter
{
    private const CHUNK_SIZE = 5000;

    private const PER_VM_GUEST_CATEGORIES = [
        'hyperv_vms',
        'vmware_vms',
        'proxmox_vms',
    ];

    public const PER_VM_CHARGE_STATUS = 'per-VM charge (Comet does not identify guest)';

    private static function isPerVmGuestCategory(string $category): bool
    {
        return in_array($category, self::PER_VM_GUEST_CATEGORIES, true);
    }

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
        $cadence = $finding['evidence']['cadence'] ?? [];
        $reversal = $finding['evidence']['reversal'] ?? null;

        $reversalStatus = 'none_found';
        $reversalDetail = '';
        if (is_array($reversal) && $reversal !== []) {
            $reversalStatus = 'offsetting_record_found';
            $reversalDetail = json_encode($reversal);
        }

        $activeServiceName = (string) ($cadence['service_name'] ?? '');
        $activeSnapshotAt = (string) ($cadence['snapshot_at'] ?? '');
        $activeQuantity = $cadence['service_quantity'] ?? null;
        $activeAmount = $cadence['service_amount'] ?? null;
        $activeUnitCost = $cadence['service_unit_cost'] ?? null;
        $activeServiceEvidence = $activeServiceName !== ''
            ? sprintf(
                'Snapshot %s still listed "%s" (quantity=%s, cycle=%d day%s, displayed amount=%s, unit cost=%s, next due=%s)',
                $activeSnapshotAt !== '' ? $activeSnapshotAt : '(unknown)',
                $activeServiceName,
                $activeQuantity !== null ? self::formatNumber((float) $activeQuantity) : '(unknown)',
                $cycleDays,
                $cycleDays === 1 ? '' : 's',
                $activeAmount !== null ? '$' . number_format((float) $activeAmount, 2) : '(unknown)',
                $activeUnitCost !== null ? '$' . number_format((float) $activeUnitCost, 2) : '(unknown)',
                $nextDue !== '' ? $nextDue : '(unknown)'
            )
            : 'No matching Active Services row was available near the debit date';

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
            'category_key' => (string) ($finding['category'] ?? ''),
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
            'active_service_name' => $activeServiceName,
            'active_service_snapshot_at' => $activeSnapshotAt,
            'active_service_quantity' => $activeQuantity,
            'active_service_amount' => $activeAmount,
            'active_service_unit_cost' => $activeUnitCost,
            'active_service_evidence' => $activeServiceEvidence,
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

    /**
     * Group daily findings into one device/service case. For guest VM boosters,
     * same-day Bill History lines are per-VM charges (not duplicates).
     *
     * @param list<array<string, mixed>> $packs
     * @return list<array<string, mixed>>
     */
    public static function groupForDispute(array $packs): array
    {
        $groups = [];

        foreach ($packs as $pack) {
            $isDaily = ($pack['cycle'] ?? '') === 'daily';
            $category = (string) ($pack['category'] ?? '');
            $categoryKey = (string) ($pack['category_key'] ?? $category);
            $perVmGuest = self::isPerVmGuestCategory($categoryKey);
            $baseKey = implode('|', [
                (string) ($pack['account'] ?? ''),
                (string) ($pack['device_id'] ?? ''),
                (string) ($pack['item_desc'] ?? ''),
                $category,
                (string) ($pack['cycle'] ?? ''),
            ]);
            $groupKey = $isDaily
                ? 'daily|' . $baseKey
                : 'dated|' . $baseKey . '|' . (string) ($pack['debit_date'] ?? '');

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'account' => (string) ($pack['account'] ?? ''),
                    'device_id' => (string) ($pack['device_id'] ?? ''),
                    'device_name' => (string) ($pack['device_name'] ?? ''),
                    'item_desc' => (string) ($pack['item_desc'] ?? ''),
                    'category' => $category,
                    'cycle' => (string) ($pack['cycle'] ?? ''),
                    'cycle_days' => (int) ($pack['cycle_days'] ?? 0),
                    'revoked_at' => (string) ($pack['revoked_at'] ?? ''),
                    'registered_at' => (string) ($pack['registered_at'] ?? ''),
                    'expected_billing_end' => (string) ($pack['expected_billing_end'] ?? ''),
                    'next_due_date' => (string) ($pack['next_due_date'] ?? ''),
                    'active_service_name' => (string) ($pack['active_service_name'] ?? ''),
                    'active_service_snapshot_at' => (string) ($pack['active_service_snapshot_at'] ?? ''),
                    'active_service_quantity' => $pack['active_service_quantity'] ?? null,
                    'active_service_amount' => $pack['active_service_amount'] ?? null,
                    'active_service_unit_cost' => $pack['active_service_unit_cost'] ?? null,
                    'active_service_evidence' => (string) ($pack['active_service_evidence'] ?? ''),
                    'reversal_status' => (string) ($pack['reversal_status'] ?? ''),
                    'per_vm_guest' => $perVmGuest,
                    'debit_dates' => [],
                ];
            }

            $date = (string) ($pack['debit_date'] ?? '');
            if (!isset($groups[$groupKey]['debit_dates'][$date])) {
                $groups[$groupKey]['debit_dates'][$date] = [
                    'debit_date' => $date,
                    'amount_per_occurrence' => (float) ($pack['amount_used'] ?? 0),
                    'packs_used' => (string) ($pack['packs_used'] ?? ''),
                    'pack_debited' => (string) ($pack['pack_debited'] ?? ''),
                    'occurrences' => [],
                ];
            }

            $occurrenceIndex = count($groups[$groupKey]['debit_dates'][$date]['occurrences']);
            if ($perVmGuest) {
                $status = self::PER_VM_CHARGE_STATUS;
            } else {
                $status = $occurrenceIndex === 0
                    ? 'confirmed debit'
                    : 'duplicate debit pending Comet confirmation';
            }
            $groups[$groupKey]['debit_dates'][$date]['occurrences'][] = [
                'usage_id' => (int) ($pack['usage_id'] ?? 0),
                'amount' => (float) ($pack['amount_used'] ?? 0),
                'status' => $status,
            ];
        }

        $cases = [];
        foreach ($groups as $group) {
            $perVmGuest = !empty($group['per_vm_guest']);
            ksort($group['debit_dates']);
            $group['debit_dates'] = array_values($group['debit_dates']);
            $group['distinct_debit_dates'] = count($group['debit_dates']);
            $group['occurrence_count'] = 0;
            $group['duplicate_pending_count'] = 0;
            $confirmedAmount = 0.0;
            $pendingAmount = 0.0;
            $multiVmDays = 0;

            foreach ($group['debit_dates'] as &$dateRow) {
                $count = count($dateRow['occurrences']);
                $unitAmount = (float) $dateRow['amount_per_occurrence'];
                $dayTotal = 0.0;
                foreach ($dateRow['occurrences'] as $occurrence) {
                    $dayTotal += (float) ($occurrence['amount'] ?? 0);
                }
                $dateRow['occurrence_count'] = $count;
                if ($perVmGuest) {
                    $dateRow['confirmed_amount'] = number_format($dayTotal, 2, '.', '');
                    $dateRow['duplicate_pending_count'] = 0;
                    $dateRow['duplicate_pending_amount'] = '0.00';
                    if ($count > 1) {
                        $multiVmDays++;
                    }
                } else {
                    $dateRow['confirmed_amount'] = number_format($unitAmount, 2, '.', '');
                    $dateRow['duplicate_pending_count'] = max(0, $count - 1);
                    $dateRow['duplicate_pending_amount'] = number_format(
                        $unitAmount * max(0, $count - 1),
                        2,
                        '.',
                        ''
                    );
                }
                $group['occurrence_count'] += $count;
                if ($perVmGuest) {
                    $confirmedAmount += $dayTotal;
                } else {
                    $group['duplicate_pending_count'] += max(0, $count - 1);
                    $confirmedAmount += $unitAmount;
                    $pendingAmount += $unitAmount * max(0, $count - 1);
                }
            }
            unset($dateRow);

            $group['first_debit_date'] = (string) ($group['debit_dates'][0]['debit_date'] ?? '');
            $last = $group['debit_dates'][count($group['debit_dates']) - 1] ?? [];
            $group['last_debit_date'] = (string) ($last['debit_date'] ?? '');
            $group['confirmed_amount'] = number_format($confirmedAmount, 2, '.', '');
            $group['duplicate_pending_amount'] = number_format($pendingAmount, 2, '.', '');

            if ($group['cycle'] === 'daily') {
                $service = $group['active_service_name'] !== ''
                    ? '"' . $group['active_service_name'] . '"'
                    : 'the ' . $group['item_desc'] . ' service';
                $snapshotDate = $group['active_service_snapshot_at'] !== ''
                    ? substr($group['active_service_snapshot_at'], 0, 10)
                    : '(snapshot date unavailable)';
                $group['claim'] = sprintf(
                    'Device %s was revoked on %s, but Comet’s Active Services report still listed %s on %s, and Bill History recorded daily charges on %d date%s from %s through %s.',
                    $group['device_id'] !== '' ? $group['device_id'] : '(unknown)',
                    $group['revoked_at'] !== '' ? $group['revoked_at'] : '(unknown)',
                    $service,
                    $snapshotDate,
                    $group['distinct_debit_dates'],
                    $group['distinct_debit_dates'] === 1 ? '' : 's',
                    $group['first_debit_date'],
                    $group['last_debit_date']
                );
                if ($perVmGuest && $multiVmDays > 0) {
                    $group['claim'] .= ' On '
                        . $multiVmDays
                        . ' date'
                        . ($multiVmDays === 1 ? '' : 's')
                        . ', Bill History lists multiple identical guest-booster lines without guest names or IDs; we treat each line as one selected VM charged for that day.';
                }
            } else {
                $firstDate = $group['debit_dates'][0] ?? [];
                $group['claim'] = sprintf(
                    '%s Comet debited $%s / %s for device %s after that device was revoked on %s and after its monthly paid-through date of %s, with no later offsetting credit found.',
                    $group['first_debit_date'],
                    (string) ($firstDate['confirmed_amount'] ?? '0.00'),
                    (string) ($firstDate['packs_used'] ?? 'unknown pack'),
                    $group['device_id'] !== '' ? $group['device_id'] : '(unknown)',
                    $group['revoked_at'] !== '' ? $group['revoked_at'] : '(unknown)',
                    $group['expected_billing_end'] !== '' ? $group['expected_billing_end'] : '(unknown)'
                );
            }

            $cases[] = $group;
        }

        usort($cases, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['last_debit_date'], (string) $a['last_debit_date']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['account'], (string) $b['account']);
        });

        return $cases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function collectCases(string $fromDate, string $toDate): array
    {
        return self::groupForDispute(self::collectConfirmed($fromDate, $toDate));
    }

    public static function buildCsv(string $fromDate, string $toDate): string
    {
        $cases = self::collectCases($fromDate, $toDate);
        $headers = [
            'claim',
            'account',
            'device_id',
            'device_name',
            'item_desc',
            'category',
            'cycle',
            'revoked_at',
            'registered_at',
            'expected_billing_end',
            'active_service_snapshot_at',
            'active_service_name',
            'active_service_quantity',
            'active_service_amount',
            'active_service_evidence',
            'debit_date',
            'confirmed_amount',
            'occurrence_count',
            'duplicate_pending_count',
            'duplicate_pending_amount',
            'packs_used',
            'pack_debited',
            'occurrence_statuses',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($cases as $case) {
            foreach ($case['debit_dates'] as $dateRow) {
                $row = array_merge($case, $dateRow, [
                    'occurrence_statuses' => implode(
                        '; ',
                        array_map(
                            static fn (array $occurrence): string => (string) $occurrence['status'],
                            $dateRow['occurrences']
                        )
                    ),
                ]);
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $row[$h] ?? '';
                }
                fputcsv($fh, $line);
            }
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
        $cases = self::collectCases($fromDate, $toDate);
        $generatedAt = gmdate('Y-m-d H:i:s') . ' UTC';
        $confirmedAmount = 0.0;
        $pendingAmount = 0.0;
        $occurrenceCount = 0;
        $pendingCount = 0;
        foreach ($cases as $case) {
            $confirmedAmount += (float) ($case['confirmed_amount'] ?? 0);
            $pendingAmount += (float) ($case['duplicate_pending_amount'] ?? 0);
            $occurrenceCount += (int) ($case['occurrence_count'] ?? 0);
            $pendingCount += (int) ($case['duplicate_pending_count'] ?? 0);
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>Comet Overbilling ' . htmlspecialchars($fromDate) . ' to ' . htmlspecialchars($toDate) . '</title>';
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
            .active{background:#eff6ff;border:1px solid #bfdbfe;padding:10px 12px;margin:12px 0;font-size:13px}
            .debits{width:100%;border-collapse:collapse;margin-top:12px;font-size:12px}
            .debits th,.debits td{border:1px solid #d1d5db;padding:7px;text-align:left}
            .debits th{background:#f3f4f6}
            .pending{color:#b45309;font-weight:600}
            .actions{margin:0 0 18px}
            @media print{.actions{display:none} body{margin:12px}}
        </style></head><body>';
        $html .= '<div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>';
        $html .= '<h1>Comet Overbilling</h1>';
        $html .= '<div class="meta">Period: ' . htmlspecialchars($fromDate) . ' to ' . htmlspecialchars($toDate)
            . ' &middot; Generated: ' . htmlspecialchars($generatedAt) . '</div>';
        $html .= '<div class="summary"><strong>' . count($cases) . '</strong> dispute case(s); '
            . '<strong>' . $occurrenceCount . '</strong> Bill History occurrence(s)<br>'
            . 'Overcharged amount: <strong>$' . number_format($confirmedAmount, 2) . '</strong>';
        if ($pendingAmount > 0) {
            $html .= '<br>Potential duplicate amount pending Comet confirmation: <strong>$'
                . number_format($pendingAmount, 2) . '</strong> (' . $pendingCount . ' occurrence(s))';
        }
        $html .= '</div>';

        if ($cases === []) {
            $html .= '<p>No confirmed overbill findings in this period.</p>';
        }

        foreach ($cases as $i => $case) {
            $n = $i + 1;
            $html .= '<section class="case">';
            $html .= '<h2>#' . $n . ' ' . htmlspecialchars((string) $case['account'])
                . ' — ' . htmlspecialchars((string) $case['item_desc'])
                . ' — $' . htmlspecialchars((string) $case['confirmed_amount']) . '</h2>';
            $html .= '<div class="claim">' . htmlspecialchars((string) $case['claim']) . '</div>';
            $html .= '<dl>';
            $html .= '<dt>Account</dt><dd>' . htmlspecialchars((string) $case['account']) . '</dd>';
            $html .= '<dt>Device ID</dt><dd>' . htmlspecialchars((string) $case['device_id']) . '</dd>';
            $html .= '<dt>Device name</dt><dd>' . htmlspecialchars((string) $case['device_name']) . '</dd>';
            $html .= '<dt>Item</dt><dd>' . htmlspecialchars((string) $case['item_desc']) . '</dd>';
            $html .= '<dt>Category</dt><dd>' . htmlspecialchars((string) $case['category']) . '</dd>';
            $html .= '<dt>Revoked</dt><dd>' . htmlspecialchars((string) $case['revoked_at']) . '</dd>';
            $html .= '<dt>Billing cycle</dt><dd>' . htmlspecialchars((string) $case['cycle']) . '</dd>';
            $html .= '<dt>Expected billing end</dt><dd>' . htmlspecialchars((string) $case['expected_billing_end']) . '</dd>';
            $html .= '</dl>';
            $html .= '<div class="active"><strong>Comet Active Services:</strong> '
                . htmlspecialchars((string) $case['active_service_evidence']) . '</div>';
            $html .= '<table class="debits"><thead><tr>'
                . '<th>Debit date</th><th>Amount</th><th>Pack debited</th><th>Occurrence</th><th>Status</th>'
                . '</tr></thead><tbody>';
            foreach ($case['debit_dates'] as $dateRow) {
                foreach ($dateRow['occurrences'] as $occurrenceIndex => $occurrence) {
                    $isPendingDuplicate = str_contains(
                        (string) ($occurrence['status'] ?? ''),
                        'duplicate debit pending'
                    );
                    $html .= '<tr' . ($isPendingDuplicate ? ' class="pending"' : '') . '>';
                    $html .= '<td>' . htmlspecialchars((string) $dateRow['debit_date']) . '</td>';
                    $html .= '<td>$' . number_format((float) $occurrence['amount'], 2) . '</td>';
                    $html .= '<td>' . htmlspecialchars((string) $dateRow['pack_debited']) . '</td>';
                    $html .= '<td>' . ($occurrenceIndex + 1) . ' of ' . count($dateRow['occurrences']) . '</td>';
                    $html .= '<td>' . htmlspecialchars((string) $occurrence['status']) . '</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</tbody></table>';
            $html .= '<p><strong>Overcharged amount:</strong> $'
                . htmlspecialchars((string) $case['confirmed_amount']);
            if ((float) $case['duplicate_pending_amount'] > 0) {
                $html .= ' &middot; <span class="pending">Potential duplicate amount: $'
                    . htmlspecialchars((string) $case['duplicate_pending_amount'])
                    . ' pending Comet confirmation</span>';
            }
            $html .= '</p>';
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

    private static function formatNumber(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private static function normalizeDate(string $date): string
    {
        $ts = strtotime($date);

        return $ts ? gmdate('Y-m-d', $ts) : gmdate('Y-m-d');
    }
}
