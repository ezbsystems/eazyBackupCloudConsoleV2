<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Provision\E3CloudBackupProductBootstrap;

/**
 * Meter + Rater for the e3 Cloud Backup product.
 *
 * Read-only path: meterAll() + rateAll() write into the s3_cloudbackup_*
 * tables but never touch WHMCS billing structures. The Phase 3 hook and
 * applyToWhmcs() handle WHMCS writes.
 *
 * Public surface:
 *   - meterService(int $serviceId)           // capture a usage snapshot now
 *   - meterAll()                             // bulk pass for the hourly cron
 *   - rateService(int $serviceId)            // compute rated_lines for current window
 *   - rateAll()                              // bulk pass for the hourly cron
 *   - dryRun(int $clientId): array           // structured "next invoice preview"
 *   - applyDefaultConfigOptions(int $svcId)  // Phase 3 - upsert qty=0 rows
 *   - applyToWhmcs(int $serviceId)           // Phase 3 - write tblhostingconfigoptions.qty
 */
class E3CloudBackupBilling
{
    /** Module logging tag (lines up with all other cloudstorage module calls). */
    private const MODULE = 'cloudstorage';

    /**
     * Walk every active e3 Cloud Backup service and capture a snapshot.
     *
     * @return array{services:int, snapshots:int, errors:int}
     */
    public static function meterAll(): array
    {
        $pid = E3CloudBackupProductBootstrap::getPid();
        if ($pid <= 0) {
            return ['services' => 0, 'snapshots' => 0, 'errors' => 0];
        }
        $services = Capsule::table('tblhosting')
            ->select(['id', 'userid', 'packageid', 'domainstatus', 'nextduedate'])
            ->where('packageid', $pid)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->get();

        $result = ['services' => 0, 'snapshots' => 0, 'errors' => 0];
        foreach ($services as $svc) {
            $result['services']++;
            try {
                $written = self::meterService((int) $svc->id);
                $result['snapshots'] += $written;
            } catch (\Throwable $e) {
                $result['errors']++;
                self::log('meter_service_exception', ['service_id' => (int) $svc->id], $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Capture a single usage snapshot for one service. Returns the number of
     * snapshot rows written (one per metric).
     */
    public static function meterService(int $serviceId): int
    {
        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$svc) {
            return 0;
        }
        $clientId = (int) ($svc->userid ?? 0);
        if ($clientId <= 0) {
            return 0;
        }

        $counts = self::measureForClient($clientId);
        $now = date('Y-m-d H:i:s');
        $written = 0;
        foreach ($counts as $metric => $qty) {
            try {
                Capsule::table('s3_cloudbackup_usage_snapshots')->insert([
                    'service_id' => $serviceId,
                    'client_id'  => $clientId,
                    'metric'     => $metric,
                    'qty'        => max(0, (int) $qty),
                    'taken_at'   => $now,
                ]);
                $written++;
            } catch (\Throwable $e) {
                self::log('meter_insert_fail', [
                    'service_id' => $serviceId,
                    'metric'     => $metric,
                ], $e->getMessage());
            }
        }
        return $written;
    }

    /**
     * Compute the raw qty for each metric for one client. Used by both the
     * cron and the dry-run preview.
     *
     * @return array<string,int>
     */
    public static function measureForClient(int $clientId): array
    {
        $counts = array_fill_keys(E3CloudBackupPricing::METRICS, 0);

        // Endpoints = active local-agent enrollments
        try {
            if (Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
                $counts['endpoint'] = (int) Capsule::table('s3_cloudbackup_agents')
                    ->where('client_id', $clientId)
                    ->where('status', 'active')
                    ->count();
            }
        } catch (\Throwable $e) {
            self::log('meter_endpoint_fail', ['client_id' => $clientId], $e->getMessage());
        }

        // Disk image sources = active jobs with engine='disk_image'
        try {
            if (Capsule::schema()->hasTable('s3_cloudbackup_jobs') && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'engine')) {
                $counts['disk_image'] = (int) Capsule::table('s3_cloudbackup_jobs')
                    ->where('client_id', $clientId)
                    ->where('engine', 'disk_image')
                    ->where('status', 'active')
                    ->count();
            }
        } catch (\Throwable $e) {
            self::log('meter_disk_image_fail', ['client_id' => $clientId], $e->getMessage());
        }

        // Hyper-V VMs = s3_hyperv_vms.backup_enabled rows whose parent job belongs to this client
        try {
            if (Capsule::schema()->hasTable('s3_hyperv_vms') && Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
                $counts['hyperv_vm'] = (int) Capsule::table('s3_hyperv_vms as v')
                    ->join('s3_cloudbackup_jobs as j', 'j.job_id', '=', 'v.job_id')
                    ->where('j.client_id', $clientId)
                    ->where('j.status', 'active')
                    ->where('v.backup_enabled', 1)
                    ->count();
            }
        } catch (\Throwable $e) {
            self::log('meter_hyperv_fail', ['client_id' => $clientId], $e->getMessage());
        }

        // proxmox_vm and vmware_vm are stubbed at 0 - agent doesn't surface them yet.
        return $counts;
    }

    /**
     * Walk every active e3 Cloud Backup service and rate it for the current
     * billing window.
     *
     * @return array{services:int, rated:int, errors:int}
     */
    public static function rateAll(): array
    {
        $pid = E3CloudBackupProductBootstrap::getPid();
        if ($pid <= 0) {
            return ['services' => 0, 'rated' => 0, 'errors' => 0];
        }
        $services = Capsule::table('tblhosting')
            ->select(['id', 'userid', 'packageid', 'domainstatus', 'nextduedate', 'regdate'])
            ->where('packageid', $pid)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->get();

        $result = ['services' => 0, 'rated' => 0, 'errors' => 0];
        foreach ($services as $svc) {
            $result['services']++;
            try {
                $written = self::rateService((int) $svc->id);
                $result['rated'] += $written;
            } catch (\Throwable $e) {
                $result['errors']++;
                self::log('rate_service_exception', ['service_id' => (int) $svc->id], $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Compute and upsert one rated_lines row per metric for the given service's
     * current billing window. Returns the number of rated lines written.
     */
    public static function rateService(int $serviceId): int
    {
        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$svc) {
            return 0;
        }
        $clientId = (int) ($svc->userid ?? 0);
        if ($clientId <= 0) {
            return 0;
        }

        $currencyId = self::clientCurrencyId($clientId);
        $window = self::resolveBillingWindow($svc);

        // Pull MAX(qty) in window per metric.
        $maxByMetric = self::maxQtyInWindow($serviceId, $window['start'], $window['end']);

        // If no snapshot exists in the window yet, fall back to a synchronous
        // measurement so the dry-run / preview never returns "no data".
        if (empty(array_filter($maxByMetric))) {
            $maxByMetric = self::measureForClient($clientId);
        }

        $includedEndpoints = (int) self::getSetting('e3cb_included_endpoints', 0);
        $trialStatus = self::trialStatus($serviceId);
        $betaFreeBilling = self::isBetaFreeBillingEnabled();

        $written = 0;
        foreach (E3CloudBackupPricing::METRICS as $metric) {
            $qty = (int) ($maxByMetric[$metric] ?? 0);
            $billableQty = $qty;
            if ($metric === 'endpoint' && $includedEndpoints > 0) {
                $billableQty = max(0, $qty - $includedEndpoints);
            }

            $resolved = E3CloudBackupPricing::resolve($clientId, $metric, $currencyId, $billableQty, $window['end']);
            $unitPrice = (float) $resolved['unit_price'];
            $lineAmount = (float) $resolved['line_amount'];
            $tierLabel = $resolved['tier_label'];
            $source = (string) $resolved['source'];

            if ($trialStatus === 'trialing') {
                // Zero the bill but keep the computed unit price visible.
                $lineAmount = 0.0;
                $source = 'trial_zeroed';
            } elseif ($betaFreeBilling) {
                // Global beta: zero the bill for all clients (existing +
                // converted) while keeping the computed unit price visible.
                $lineAmount = 0.0;
                $source = 'beta_zeroed';
            }

            try {
                $existing = Capsule::table('s3_cloudbackup_rated_lines')
                    ->where('service_id', $serviceId)
                    ->where('metric', $metric)
                    ->where('billing_window_start', $window['start'])
                    ->first();

                $row = [
                    'service_id'          => $serviceId,
                    'client_id'           => $clientId,
                    'metric'              => $metric,
                    'qty'                 => $billableQty,
                    'unit_price'          => $unitPrice,
                    'tier_label'          => $tierLabel,
                    'line_amount'         => $lineAmount,
                    'currency_id'         => $currencyId,
                    'billing_window_start' => $window['start'],
                    'billing_window_end'  => $window['end'],
                    'pricing_source'      => $source,
                    'notes'               => $includedEndpoints > 0 && $metric === 'endpoint'
                        ? "raw_qty={$qty}, included={$includedEndpoints}"
                        : null,
                    'updated_at'          => date('Y-m-d H:i:s'),
                ];

                if ($existing) {
                    Capsule::table('s3_cloudbackup_rated_lines')
                        ->where('id', $existing->id)
                        ->update($row);
                } else {
                    $row['created_at'] = date('Y-m-d H:i:s');
                    Capsule::table('s3_cloudbackup_rated_lines')->insert($row);
                }
                $written++;
            } catch (\Throwable $e) {
                self::log('rate_upsert_fail', [
                    'service_id' => $serviceId,
                    'metric'     => $metric,
                ], $e->getMessage());
            }
        }

        return $written;
    }

    /**
     * Return a human-readable "next invoice" preview for one client.
     *
     * @return array{
     *   client_id:int,
     *   service_id:int,
     *   currency_id:int,
     *   trial_status:?string,
     *   window:array{start:string,end:string},
     *   lines:array<int,array{
     *     metric:string,
     *     metric_label:string,
     *     qty:int,
     *     unit_price:float,
     *     line_amount:float,
     *     tier_label:?string,
     *     source:string
     *   }>,
     *   total_billable:float,
     *   total_if_paid:float
     * }
     */
    public static function dryRun(int $clientId): array
    {
        $pid = E3CloudBackupProductBootstrap::getPid();
        $svc = null;
        if ($pid > 0) {
            $svc = Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('packageid', $pid)
                ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
                ->orderBy('id', 'desc')
                ->first();
        }

        $serviceId = $svc ? (int) $svc->id : 0;
        $currencyId = self::clientCurrencyId($clientId);
        $trialStatus = $serviceId > 0 ? self::trialStatus($serviceId) : null;
        $window = $svc ? self::resolveBillingWindow($svc) : self::defaultWindow();

        $counts = self::measureForClient($clientId);
        $includedEndpoints = (int) self::getSetting('e3cb_included_endpoints', 0);

        $lines = [];
        $totalBillable = 0.0;
        $totalIfPaid = 0.0;
        foreach (E3CloudBackupPricing::METRICS as $metric) {
            $qty = (int) ($counts[$metric] ?? 0);
            $billableQty = $qty;
            if ($metric === 'endpoint' && $includedEndpoints > 0) {
                $billableQty = max(0, $qty - $includedEndpoints);
            }
            $resolved = E3CloudBackupPricing::resolve($clientId, $metric, $currencyId, $billableQty, $window['end']);
            $lineAmount = (float) $resolved['line_amount'];
            $totalIfPaid += $lineAmount;

            $effective = $lineAmount;
            $source = (string) $resolved['source'];
            if ($trialStatus === 'trialing') {
                $effective = 0.0;
                $source = 'trial_zeroed';
            } elseif (self::isBetaFreeBillingEnabled()) {
                $effective = 0.0;
                $source = 'beta_zeroed';
            }
            $totalBillable += $effective;

            $lines[] = [
                'metric'       => $metric,
                'metric_label' => E3CloudBackupProductBootstrap::metricFriendlyName($metric),
                'qty'          => $billableQty,
                'raw_qty'      => $qty,
                'unit_price'   => (float) $resolved['unit_price'],
                'line_amount'  => $effective,
                'tier_label'   => $resolved['tier_label'],
                'source'       => $source,
            ];
        }

        return [
            'client_id'    => $clientId,
            'service_id'   => $serviceId,
            'currency_id'  => $currencyId,
            'trial_status' => $trialStatus,
            'window'       => $window,
            'lines'        => $lines,
            'total_billable' => round($totalBillable, 2),
            'total_if_paid'  => round($totalIfPaid, 2),
        ];
    }

    /**
     * Upsert tblhostingconfigoptions rows for each metric with qty=0 so the
     * line items exist from day 1. Mirrors eazybackup_apply_default_config_options.
     */
    public static function applyDefaultConfigOptions(int $serviceId): void
    {
        $map = E3CloudBackupProductBootstrap::getConfigOptionMap();
        if (empty($map)) {
            return;
        }
        foreach ($map as $metric => $configId) {
            $configId = (int) $configId;
            if ($configId <= 0) {
                continue;
            }
            try {
                $subId = (int) Capsule::table('tblproductconfigoptionssub')
                    ->where('configid', $configId)
                    ->orderBy('sortorder', 'asc')
                    ->orderBy('id', 'asc')
                    ->value('id');
                $optionId = $subId > 0 ? $subId : $configId;
                $exists = Capsule::table('tblhostingconfigoptions')
                    ->where('relid', $serviceId)
                    ->where('configid', $configId)
                    ->exists();
                if ($exists) {
                    // Don't clobber an existing qty (the rater may have set it).
                    Capsule::table('tblhostingconfigoptions')
                        ->where('relid', $serviceId)
                        ->where('configid', $configId)
                        ->update(['optionid' => $optionId]);
                } else {
                    Capsule::table('tblhostingconfigoptions')->insert([
                        'relid'    => $serviceId,
                        'configid' => $configId,
                        'optionid' => $optionId,
                        'qty'      => 0,
                    ]);
                }
            } catch (\Throwable $e) {
                self::log('apply_default_config_options_fail', [
                    'service_id' => $serviceId,
                    'metric'     => $metric,
                    'config_id'  => $configId,
                ], $e->getMessage());
            }
        }
    }

    /**
     * Phase 3 write path: copy rated qty into tblhostingconfigoptions for every
     * metric. Native WHMCS billing uses this to compute the recurring amount.
     *
     * @return int Number of rows updated.
     */
    public static function applyToWhmcs(int $serviceId): int
    {
        $map = E3CloudBackupProductBootstrap::getConfigOptionMap();
        if (empty($map)) {
            return 0;
        }
        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$svc) {
            return 0;
        }
        $window = self::resolveBillingWindow($svc);

        $rated = Capsule::table('s3_cloudbackup_rated_lines')
            ->where('service_id', $serviceId)
            ->where('billing_window_start', $window['start'])
            ->get();
        if (count($rated) === 0) {
            return 0;
        }
        $byMetric = [];
        foreach ($rated as $r) {
            $byMetric[(string) $r->metric] = $r;
        }

        $updated = 0;
        foreach ($map as $metric => $configId) {
            $configId = (int) $configId;
            if ($configId <= 0) {
                continue;
            }
            $rateRow = $byMetric[$metric] ?? null;
            $qty = $rateRow ? (int) $rateRow->qty : 0;

            try {
                $subId = (int) Capsule::table('tblproductconfigoptionssub')
                    ->where('configid', $configId)
                    ->orderBy('sortorder', 'asc')
                    ->orderBy('id', 'asc')
                    ->value('id');
                $optionId = $subId > 0 ? $subId : $configId;

                $exists = Capsule::table('tblhostingconfigoptions')
                    ->where('relid', $serviceId)
                    ->where('configid', $configId)
                    ->exists();
                if ($exists) {
                    Capsule::table('tblhostingconfigoptions')
                        ->where('relid', $serviceId)
                        ->where('configid', $configId)
                        ->update(['optionid' => $optionId, 'qty' => $qty]);
                } else {
                    Capsule::table('tblhostingconfigoptions')->insert([
                        'relid'    => $serviceId,
                        'configid' => $configId,
                        'optionid' => $optionId,
                        'qty'      => $qty,
                    ]);
                }
                $updated++;
            } catch (\Throwable $e) {
                self::log('apply_to_whmcs_fail', [
                    'service_id' => $serviceId,
                    'metric'     => $metric,
                ], $e->getMessage());
            }
        }
        return $updated;
    }

    // ---------- helpers ----------

    /**
     * Resolve the rolling billing window for a service. Uses nextduedate when
     * present (true monthly cycle anchor), otherwise a 30-day rolling window.
     *
     * @return array{start:string,end:string}
     */
    public static function resolveBillingWindow(object $svc): array
    {
        $end = '';
        if (!empty($svc->nextduedate) && $svc->nextduedate !== '0000-00-00') {
            $end = (string) $svc->nextduedate;
        }
        if ($end === '') {
            // 30-day rolling window ending today
            return self::defaultWindow();
        }
        try {
            $endTs = strtotime($end);
            $startTs = strtotime('-1 month', $endTs);
            return [
                'start' => date('Y-m-d', $startTs),
                'end'   => date('Y-m-d', $endTs),
            ];
        } catch (\Throwable $e) {
            return self::defaultWindow();
        }
    }

    private static function defaultWindow(): array
    {
        return [
            'start' => date('Y-m-d', strtotime('-30 days')),
            'end'   => date('Y-m-d'),
        ];
    }

    /**
     * Pull MAX(qty) per metric in the given inclusive date window.
     *
     * @return array<string,int>
     */
    public static function maxQtyInWindow(int $serviceId, string $startDate, string $endDate): array
    {
        $out = array_fill_keys(E3CloudBackupPricing::METRICS, 0);
        try {
            $rows = Capsule::table('s3_cloudbackup_usage_snapshots')
                ->select(['metric', Capsule::raw('MAX(qty) as max_qty')])
                ->where('service_id', $serviceId)
                ->where('taken_at', '>=', $startDate . ' 00:00:00')
                ->where('taken_at', '<=', $endDate . ' 23:59:59')
                ->groupBy('metric')
                ->get();
            foreach ($rows as $r) {
                $out[(string) $r->metric] = (int) ($r->max_qty ?? 0);
            }
        } catch (\Throwable $e) {
            self::log('max_qty_window_fail', [
                'service_id' => $serviceId,
                'start'      => $startDate,
                'end'        => $endDate,
            ], $e->getMessage());
        }
        return $out;
    }

    /**
     * Return s3_cloudbackup_trial_state.status for a service, or null if no row.
     */
    public static function trialStatus(int $serviceId): ?string
    {
        try {
            $row = Capsule::table('s3_cloudbackup_trial_state')->where('service_id', $serviceId)->first();
            return $row ? (string) $row->status : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Read the client's currency ID from tblclients (falls back to setting,
     * then to 1).
     */
    public static function clientCurrencyId(int $clientId): int
    {
        try {
            $cur = (int) Capsule::table('tblclients')->where('id', $clientId)->value('currency');
            if ($cur > 0) {
                return $cur;
            }
        } catch (\Throwable $e) {
        }
        $configured = (int) self::getSetting('e3cb_currency_id', 1);
        return $configured > 0 ? $configured : 1;
    }

    /**
     * Global beta switch. When enabled, every e3 Cloud Backup compute line is
     * invoiced at $0.00 for ALL clients (existing + trial), while usage and
     * rated lines continue to be recorded. Object storage is unaffected.
     */
    public static function isBetaFreeBillingEnabled(): bool
    {
        $val = self::getSetting('e3cb_beta_free_billing', '');
        return in_array(strtolower((string) $val), ['on', 'yes', '1', 'true'], true);
    }

    private static function getSetting(string $key, $default = null)
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', $key)
                ->value('value');
            return ($val !== null && $val !== '') ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private static function log(string $event, array $context, $payload): void
    {
        try {
            logModuleCall(self::MODULE, $event, $context, $payload, [], []);
        } catch (\Throwable $_) {
        }
    }
}
