<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Import Comet Account Portal purchase history CSV exports into cb_credit_purchases.
 */
class PurchaseCsvImporter
{
    private const EXPECTED_HEADERS = ['date', 'type', 'item', 'credit amount', 'cost'];
    private const PAYMENT_METHOD = 'Comet CSV Import';
    private const NOTES = 'Imported from Comet CSV';

    /**
     * Import purchases from a Comet CSV export file.
     *
     * @return array{imported: int, skipped: int, lots: int, errors: string[]}
     */
    public static function import(string $path, bool $dryRun = false): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'lots' => 0, 'errors' => []];

        try {
            $parsedRows = self::parseFile($path);
        } catch (\RuntimeException $e) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        foreach ($parsedRows as $index => $row) {
            $lineNum = $index + 2; // 1-based, account for header row
            $purchase = self::parseRow($row, $lineNum, $result['errors']);

            if ($purchase === null) {
                continue;
            }

            if (self::isDuplicate($purchase)) {
                $result['skipped']++;
                continue;
            }

            if (!$dryRun) {
                try {
                    self::persistPurchase($purchase);
                } catch (\Exception $e) {
                    $result['errors'][] = "Row {$lineNum}: " . $e->getMessage();
                    continue;
                }
            }

            $result['imported']++;
            $result['lots'] += self::countLotsForPurchase($purchase);
        }

        return $result;
    }

    /**
     * Insert a purchase record and create FIFO credit lots.
     */
    public static function persistPurchase(array $data): int
    {
        $purchaseId = (int) Capsule::table('cb_credit_purchases')->insertGetId($data);
        CreditLedger::createLotsFromPurchase($purchaseId);
        return $purchaseId;
    }

    /**
     * Read and validate a CSV file; returns associative rows keyed by normalized header names.
     *
     * @return array<int, array<string, string>>
     */
    public static function parseFile(string $path): array
    {
        if (!is_readable($path)) {
            throw new \RuntimeException("Cannot read CSV file: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open CSV file: {$path}");
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false || empty($headerRow)) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or missing a header row.');
        }

        if (isset($headerRow[0])) {
            $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerRow[0]);
        }

        $headerMap = self::buildHeaderMap($headerRow);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (self::isEmptyRow($row)) {
                continue;
            }

            $assoc = [];
            foreach ($headerMap as $canonical => $index) {
                $assoc[$canonical] = trim($row[$index] ?? '');
            }
            $rows[] = $assoc;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Parse a single CSV row into a purchase record, or null to skip silently.
     *
     * @param array<string, string> $row
     * @param string[] $errors
     * @return array<string, mixed>|null
     */
    public static function parseRow(array $row, int $lineNum, array &$errors): ?array
    {
        if (!self::isCustomerPurchase($row['type'] ?? '')) {
            return null;
        }

        $purchasedAt = self::parseDate($row['date'] ?? '');
        if ($purchasedAt === null) {
            $errors[] = "Row {$lineNum}: missing or invalid date.";
            return null;
        }

        $cost = self::parseMoney($row['cost'] ?? '');
        $totalCredit = self::parseMoney($row['credit amount'] ?? '');

        if ($cost <= 0) {
            $errors[] = "Row {$lineNum}: cost must be greater than zero.";
            return null;
        }

        if ($totalCredit < $cost) {
            $errors[] = "Row {$lineNum}: credit amount must be greater than or equal to cost.";
            return null;
        }

        $bonusCredit = round($totalCredit - $cost, 4);
        $packLabel = $row['item'] ?? '';
        $packUnits = self::parsePackUnits($packLabel);
        $normalizedDate = substr($purchasedAt, 0, 10);
        $fingerprint = self::buildFingerprint($normalizedDate, $cost, $totalCredit, $bonusCredit, $packLabel);

        return [
            'purchased_at' => $purchasedAt,
            'currency' => 'USD',
            'pack_label' => $packLabel !== '' ? $packLabel : null,
            'pack_units' => $packUnits,
            'credit_amount' => $cost,
            'bonus_credit' => $bonusCredit,
            'payment_method' => self::PAYMENT_METHOD,
            'receipt_no' => null,
            'external_ref' => 'csv:' . $fingerprint,
            'notes' => self::NOTES,
            'raw_receipt' => json_encode($row),
        ];
    }

    /**
     * @param array<string, mixed> $purchase
     */
    private static function isDuplicate(array $purchase): bool
    {
        if (Capsule::table('cb_credit_purchases')->where('external_ref', $purchase['external_ref'])->exists()) {
            return true;
        }

        return Capsule::table('cb_credit_purchases')
            ->where('purchased_at', $purchase['purchased_at'])
            ->where('credit_amount', $purchase['credit_amount'])
            ->where('bonus_credit', $purchase['bonus_credit'])
            ->exists();
    }

    private static function buildFingerprint(
        string $normalizedDate,
        float $cost,
        float $totalCredit,
        float $bonusCredit,
        string $packLabel
    ): string {
        $payload = implode('|', [
            $normalizedDate,
            self::formatAmount($cost),
            self::formatAmount($totalCredit),
            self::formatAmount($bonusCredit),
            trim($packLabel),
        ]);

        return md5($payload);
    }

    /**
     * @param array<string, mixed> $purchase
     */
    private static function countLotsForPurchase(array $purchase): int
    {
        $lots = 0;
        if ((float) $purchase['credit_amount'] > 0) {
            $lots++;
        }
        if ((float) $purchase['bonus_credit'] > 0) {
            $lots++;
        }
        return $lots;
    }

    /**
     * @param string[] $headerRow
     * @return array<string, int>
     */
    private static function buildHeaderMap(array $headerRow): array
    {
        $normalized = [];
        foreach ($headerRow as $index => $label) {
            $normalized[mb_strtolower(trim($label))] = $index;
        }

        $missing = [];
        foreach (self::EXPECTED_HEADERS as $expected) {
            if (!array_key_exists($expected, $normalized)) {
                $missing[] = $expected;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'CSV header row is missing required columns: ' . implode(', ', $missing)
            );
        }

        $map = [];
        foreach (self::EXPECTED_HEADERS as $expected) {
            $map[$expected] = $normalized[$expected];
        }

        return $map;
    }

    /**
     * @param array<int, string|null> $row
     */
    private static function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private static function formatAmount(float $amount): string
    {
        return number_format($amount, 4, '.', '');
    }

    public static function parseMoney(string $val): float
    {
        $clean = preg_replace('/[^\d.\-]/', '', $val);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return 0.0;
        }

        return (float) $clean;
    }

    public static function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') {
            return null;
        }

        $formats = ['Y-m-d', 'n/j/y', 'n/j/Y', 'm/d/Y', 'm/d/y'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $val);
            if ($dt instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                    return $dt->format('Y-m-d') . ' 00:00:00';
                }
            }
        }

        $timestamp = strtotime($val);
        if ($timestamp !== false) {
            return gmdate('Y-m-d H:i:s', $timestamp);
        }

        return null;
    }

    public static function parsePackUnits(string $item): ?int
    {
        if (preg_match('/([\d,]+)\s*Dollars?/i', $item, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }

        return null;
    }

    public static function isCustomerPurchase(string $type): bool
    {
        return strcasecmp(trim($type), 'Customer Purchase') === 0;
    }

    /**
     * Delete purchases and their associated credit lots.
     *
     * @param int[] $ids Purchase IDs to delete
     * @return int Number of purchases deleted
     */
    public static function deleteByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        Capsule::table('cb_credit_lots')->whereIn('purchase_id', $ids)->delete();
        return Capsule::table('cb_credit_purchases')->whereIn('id', $ids)->delete();
    }
}
