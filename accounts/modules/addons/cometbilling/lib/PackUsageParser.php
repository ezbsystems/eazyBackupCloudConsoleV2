<?php
namespace CometBilling;

/**
 * Lossless parser for Comet "Packs Used" strings.
 *
 * Examples:
 * - "10,000 Dollars"
 * - "10,000 Dollars (- $1.29 ) 10,000 Dollars (- $0.71 )"
 */
class PackUsageParser
{
    /**
     * @return array{
     *   raw: string|null,
     *   entries: list<array{label: string, denomination: ?int, debit_amount: ?float}>,
     *   primary_denomination: ?int,
     *   parsed_ok: bool
     * }
     */
    public static function parse(?string $raw): array
    {
        $raw = $raw !== null ? trim($raw) : '';
        if ($raw === '') {
            return [
                'raw' => null,
                'entries' => [],
                'primary_denomination' => null,
                'parsed_ok' => false,
            ];
        }

        $entries = [];
        $pattern = '/([\d,]+)\s*Dollars?(?:\s*\(\s*-\s*\$?\s*([\d.]+)\s*\))?/i';
        if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $denomination = (int) str_replace(',', '', $match[1]);
                $debit = isset($match[2]) && $match[2] !== '' ? (float) $match[2] : null;
                $entries[] = [
                    'label' => trim($match[0]),
                    'denomination' => $denomination > 0 ? $denomination : null,
                    'debit_amount' => $debit,
                ];
            }
        }

        $primaryDenomination = $entries[0]['denomination'] ?? null;

        return [
            'raw' => $raw,
            'entries' => $entries,
            'primary_denomination' => $primaryDenomination,
            'parsed_ok' => $entries !== [],
        ];
    }

    public static function hasDebitEvidence(?string $raw, ?float $amountUsed): bool
    {
        $parsed = self::parse($raw);
        if ((float) $amountUsed > 0 && $parsed['parsed_ok']) {
            return true;
        }

        return (float) $amountUsed > 0 && $raw !== null && trim($raw) !== '';
    }
}
