<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;

/**
 * Pure pricing resolver for the e3 Cloud Backup product.
 *
 * Decision tree for resolve():
 *   1. Per-client effective row in s3_cloudbackup_pricing.
 *   2. Global default row (client_id IS NULL) in s3_cloudbackup_pricing.
 *   3. Fallback to the configured tblpricing row (the native WHMCS config
 *      option price).
 *
 * Tier semantics: VOLUME. All units priced at the unit price of the band
 * the qty fits into. This is the customer-friendly model: "if you have
 * 30 endpoints, you're a tier-2 customer and ALL 30 endpoints are at the
 * tier-2 unit price."
 *
 * tiers_json shape (example, ascending bands):
 *   [
 *     {"max": 10,            "unit": 4.50},
 *     {"min": 11, "max": 50, "unit": 3.75},
 *     {"min": 51,            "unit": 2.95}
 *   ]
 *
 * - "max" absent or null means "and above".
 * - "min" defaults to 1 when omitted on the first band.
 *
 * All methods are stateless and side-effect-free. Designed to be called
 * thousands of times in tight cron loops.
 */
class E3CloudBackupPricing
{
    /**
     * Allowed metric keys. Must stay in sync with the ENUM in
     * cloudstorage_ensure_e3cb_billing_schema().
     */
    public const METRICS = ['endpoint', 'disk_image', 'hyperv_vm', 'proxmox_vm', 'vmware_vm', 'saas_connector'];

    /**
     * Resolve a price for a (clientId, metric, currency, qty, effectiveDate).
     *
     * @return array{
     *   unit_price:float,
     *   line_amount:float,
     *   source:string,
     *   tier_label:?string,
     *   override_id:?int
     * }
     */
    public static function resolve(int $clientId, string $metric, int $currencyId, int $qty, ?string $effectiveDate = null, ?int $serviceId = null): array
    {
        $effectiveDate = $effectiveDate ?: date('Y-m-d');
        $qty = max(0, $qty);

        // 1. Per-client effective row
        $row = self::findActiveRow($clientId, $metric, $currencyId, $effectiveDate);
        if ($row) {
            return self::applyRow($row, $qty, 'client_override');
        }

        // 2. Global default
        $row = self::findActiveRow(null, $metric, $currencyId, $effectiveDate);
        if ($row) {
            return self::applyRow($row, $qty, 'global_default');
        }

        // 3. tblpricing fallback
        $configMap = self::configOptionMapForResolve($metric, $serviceId);
        $configId = (int) ($configMap[$metric] ?? 0);
        $unitPrice = self::tblpricingUnitPrice($configId, $currencyId);

        if ($unitPrice <= 0) {
            $bootstrapPath = dirname(__DIR__) . '/Provision/E3BackupUserProductBootstrap.php';
            if ($metric === 'saas_connector' && is_file($bootstrapPath)) {
                require_once $bootstrapPath;
                $unitPrice = \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::metricDefaultPrice($metric);
            }
            if ($unitPrice <= 0) {
                $unitPrice = \WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap::metricDefaultPrice($metric);
            }
        }

        return [
            'unit_price'  => $unitPrice,
            'line_amount' => self::round2($unitPrice * $qty),
            'source'      => 'tblpricing',
            'tier_label'  => null,
            'override_id' => null,
        ];
    }

    /**
     * Find the currently-effective pricing row for a given scope.
     *
     * @param int|null $clientId NULL = the "global default" row scope.
     */
    public static function findActiveRow(?int $clientId, string $metric, int $currencyId, string $effectiveDate): ?object
    {
        try {
            $q = Capsule::table('s3_cloudbackup_pricing')
                ->where('metric', $metric)
                ->where('currency_id', $currencyId)
                ->where('effective_from', '<=', $effectiveDate)
                ->where(function ($w) use ($effectiveDate) {
                    $w->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveDate);
                });
            if ($clientId === null) {
                $q->whereNull('client_id');
            } else {
                $q->where('client_id', $clientId);
            }
            $row = $q->orderBy('effective_from', 'desc')->orderBy('id', 'desc')->first();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param object $row     s3_cloudbackup_pricing row
     * @param int    $qty     measured qty
     * @param string $source  client_override or global_default
     *
     * @return array{
     *   unit_price:float,
     *   line_amount:float,
     *   source:string,
     *   tier_label:?string,
     *   override_id:?int
     * }
     */
    private static function applyRow(object $row, int $qty, string $source): array
    {
        $mode = (string) ($row->mode ?? 'flat_unit');
        $overrideId = (int) ($row->id ?? 0);

        if ($mode === 'flat_monthly') {
            $monthly = (float) ($row->flat_monthly ?? 0.0);
            return [
                'unit_price'  => $monthly,
                'line_amount' => self::round2($monthly),
                'source'      => 'flat_monthly',
                'tier_label'  => 'flat monthly',
                'override_id' => $overrideId,
            ];
        }

        if ($mode === 'tiered') {
            $tiers = self::normaliseTiers($row->tiers_json ?? null);
            if (!empty($tiers)) {
                $band = self::pickBand($tiers, $qty);
                if ($band !== null) {
                    return [
                        'unit_price'  => (float) $band['unit'],
                        'line_amount' => self::round2((float) $band['unit'] * $qty),
                        'source'      => $source,
                        'tier_label'  => self::bandLabel($band),
                        'override_id' => $overrideId,
                    ];
                }
            }
            // Fall through to flat_unit treatment if tiers are malformed/empty.
        }

        // flat_unit
        $unit = (float) ($row->unit_price ?? 0.0);
        return [
            'unit_price'  => $unit,
            'line_amount' => self::round2($unit * $qty),
            'source'      => $source,
            'tier_label'  => null,
            'override_id' => $overrideId,
        ];
    }

    /**
     * Look up the configured unit price from tblpricing for the metric's
     * config option sub-option. Currency-aware. Returns 0.0 if not found.
     */
    public static function tblpricingUnitPrice(int $configId, int $currencyId): float
    {
        if ($configId <= 0) {
            return 0.0;
        }
        try {
            $subId = (int) Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $configId)
                ->orderBy('sortorder', 'asc')
                ->orderBy('id', 'asc')
                ->value('id');
            if ($subId <= 0) {
                return 0.0;
            }
            $row = Capsule::table('tblpricing')
                ->where('type', 'configoptions')
                ->where('currency', $currencyId)
                ->where('relid', $subId)
                ->first();
            if (!$row) {
                return 0.0;
            }
            $monthly = isset($row->monthly) ? (float) $row->monthly : 0.0;
            return $monthly < 0 ? 0.0 : $monthly;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Return tiers as a sorted array of bands with normalised min/max/unit keys.
     *
     * @param mixed $raw JSON string OR already-decoded array
     * @return array<int,array{min:int,max:?int,unit:float}>
     */
    public static function normaliseTiers($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = null;
        }
        if (!is_array($decoded)) {
            return [];
        }
        $bands = [];
        foreach ($decoded as $band) {
            if (!is_array($band)) {
                continue;
            }
            $unit = isset($band['unit']) ? (float) $band['unit'] : 0.0;
            if ($unit < 0) {
                $unit = 0.0;
            }
            $min = isset($band['min']) && $band['min'] !== null ? (int) $band['min'] : 0;
            $max = isset($band['max']) && $band['max'] !== null && $band['max'] !== '' ? (int) $band['max'] : null;
            if ($min <= 0) {
                $min = 1;
            }
            $bands[] = ['min' => $min, 'max' => $max, 'unit' => $unit];
        }
        usort($bands, function ($a, $b) {
            return $a['min'] <=> $b['min'];
        });
        return $bands;
    }

    /**
     * Pick the band a qty fits into using volume semantics. Returns null if no
     * band matches.
     *
     * @param array<int,array{min:int,max:?int,unit:float}> $bands
     */
    public static function pickBand(array $bands, int $qty): ?array
    {
        if ($qty <= 0) {
            return $bands[0] ?? null;
        }
        foreach ($bands as $band) {
            $min = (int) $band['min'];
            $max = $band['max'];
            if ($qty >= $min && ($max === null || $qty <= (int) $max)) {
                return $band;
            }
        }
        // Above all bands - use the last (top) band.
        return $bands[count($bands) - 1] ?? null;
    }

    private static function bandLabel(array $band): string
    {
        $min = (int) $band['min'];
        $max = $band['max'];
        if ($max === null) {
            return "tier {$min}+";
        }
        return "tier {$min}-" . (int) $max;
    }

    private static function round2(float $v): float
    {
        return round($v, 2);
    }

    /** @return array<string,int> */
    private static function configOptionMapForResolve(string $metric, ?int $serviceId = null): array
    {
        $bootstrapPath = dirname(__DIR__) . '/Provision/E3BackupUserProductBootstrap.php';
        if (is_file($bootstrapPath)) {
            require_once $bootstrapPath;
        }
        if ($serviceId !== null && $serviceId > 0
            && class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Provision\\E3BackupUserProductBootstrap')) {
            return \WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap::resolveE3cbConfigOptionMap($serviceId);
        }
        return \WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap::getConfigOptionMap();
    }
}
