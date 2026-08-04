<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Reconcile a billing_history multiset against stored usage rows with occurrence tracking.
 */
class UsagePullReconciler
{
    /**
     * @param list<array<string, mixed>> $apiRows
     * @return array{
     *   manifest_id: int,
     *   inserted: int,
     *   updated: int,
     *   total_in_pull: int,
     *   occurrences_assigned: int
     * }
     */
    public static function ingestBillingHistory(array $apiRows, string $pulledAt): array
    {
        self::ensureSchema();

        $manifestId = (int) Capsule::table('cb_portal_pull_manifests')->insertGetId([
            'pulled_at' => $pulledAt,
            'source' => 'billing_history',
            'row_count' => count($apiRows),
            'new_rows' => 0,
            'updated_rows' => 0,
            'checksum' => md5(json_encode($apiRows)),
            'meta' => json_encode(['pulled_at' => $pulledAt]),
            'created_at' => $pulledAt,
        ]);

        $normalized = [];
        foreach ($apiRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $n = UsageNormalizer::normalizeRow($row);
            if (empty($n['usage_date'])) {
                continue;
            }
            $normalized[] = $n;
        }

        $occurrenceCounters = [];
        $finalRows = [];
        foreach ($normalized as $n) {
            $fp = $n['content_fingerprint'];
            $occurrenceCounters[$fp] = ($occurrenceCounters[$fp] ?? 0) + 1;
            $n['occurrence_number'] = $occurrenceCounters[$fp];
            $finalRows[] = $n;
        }

        Capsule::table('cb_credit_usage')->update(['is_present_in_latest_pull' => 0]);

        $inserted = 0;
        $updated = 0;

        foreach ($finalRows as $n) {
            $existing = Capsule::table('cb_credit_usage')
                ->where('content_fingerprint', $n['content_fingerprint'])
                ->where('occurrence_number', $n['occurrence_number'])
                ->first();

            $packParsed = $n['packs_used_parsed'] ?? null;
            $rowData = [
                'usage_date' => $n['usage_date'],
                'posted_at' => $n['posted_at'],
                'tenant_id' => $n['tenant_id'],
                'device_id' => $n['device_id'],
                'item_type' => $n['item_type'],
                'item_desc' => $n['item_desc'],
                'quantity' => $n['quantity'],
                'unit_cost' => $n['unit_cost'],
                'amount' => $n['amount'],
                'packs_used' => $n['packs_used'],
                'packs_used_raw' => $n['packs_used_raw'],
                'packs_used_parsed' => $packParsed !== null ? json_encode($packParsed) : null,
                'raw_row' => json_encode($n['raw_row']),
                'row_fingerprint' => $n['row_fingerprint'],
                'content_fingerprint' => $n['content_fingerprint'],
                'occurrence_number' => $n['occurrence_number'],
                'last_seen_at' => $pulledAt,
                'pull_manifest_id' => $manifestId,
                'is_present_in_latest_pull' => 1,
            ];

            if ($existing) {
                Capsule::table('cb_credit_usage')->where('id', $existing->id)->update($rowData);
                if (empty($existing->first_seen_at)) {
                    Capsule::table('cb_credit_usage')->where('id', $existing->id)->update([
                        'first_seen_at' => $existing->created_at ?? $pulledAt,
                    ]);
                }
                $updated++;
            } else {
                $rowData['first_seen_at'] = $pulledAt;
                $rowData['created_at'] = $pulledAt;
                Capsule::table('cb_credit_usage')->insert($rowData);
                $inserted++;
            }
        }

        Capsule::table('cb_portal_pull_manifests')->where('id', $manifestId)->update([
            'new_rows' => $inserted,
            'updated_rows' => $updated,
        ]);

        return [
            'manifest_id' => $manifestId,
            'inserted' => $inserted,
            'updated' => $updated,
            'total_in_pull' => count($finalRows),
            'occurrences_assigned' => count($finalRows),
        ];
    }

    private static function ensureSchema(): void
    {
        if (!Capsule::schema()->hasTable('cb_portal_pull_manifests')) {
            throw new \RuntimeException('Audit schema missing. Run addon upgrade to 1.0.3+.');
        }
    }
}
