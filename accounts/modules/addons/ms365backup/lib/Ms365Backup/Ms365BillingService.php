<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Meter + rater for the MS365 Backup WHMCS product.
 */
final class Ms365BillingService
{
    private const MODULE = 'ms365backup';

    /** @return array{services: int, snapshots: int, errors: int} */
    public static function meterAll(): array
    {
        $pids = Ms365BillingConfig::getBillablePids();
        if ($pids === []) {
            return ['services' => 0, 'snapshots' => 0, 'errors' => 0];
        }

        $services = Capsule::table('tblhosting')
            ->select(['id', 'userid', 'packageid', 'domainstatus'])
            ->whereIn('packageid', $pids)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->get();

        $result = ['services' => 0, 'snapshots' => 0, 'errors' => 0];
        foreach ($services as $svc) {
            $result['services']++;
            try {
                $result['snapshots'] += self::meterService((int) $svc->id);
            } catch (\Throwable $e) {
                $result['errors']++;
                self::log('meter_service_exception', ['service_id' => (int) $svc->id], $e->getMessage());
            }
        }

        return $result;
    }

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

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $written = 0;

        $backupUserId = self::resolveBackupUserIdForService($serviceId, $clientId);
        $tenantRecordId = 0;
        if ($backupUserId > 0) {
            $tenantRecordId = (int) Capsule::table('ms365_tenant_records')
                ->where('whmcs_client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->value('id');
        }

        if ($backupUserId > 0) {
            $measure = Ms365UsageMeter::measureBackupUser($clientId, $backupUserId, $tenantRecordId);
            $serviceProtected = (int) $measure['protected_users'];
            $serviceOverageGiB = (int) $measure['onedrive_overage_gib'];

            foreach (Ms365BillingConfig::metricKeys() as $metric) {
                $qty = match ($metric) {
                    Ms365BillingConfig::METRIC_PROTECTED_USERS => $serviceProtected,
                    Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB => $serviceOverageGiB,
                    default => 0,
                };
                Capsule::table('ms365_billing_usage_snapshots')->insert([
                    'service_id' => $serviceId,
                    'client_id' => $clientId,
                    'backup_user_id' => $backupUserId,
                    'metric' => $metric,
                    'qty' => max(0, $qty),
                    'taken_at' => $now,
                ]);
                $written++;
            }

            if ($tenantRecordId > 0) {
                self::persistOneDriveDaily($clientId, $backupUserId, $tenantRecordId, $measure['onedrive_users'] ?? [], $today);
            }

            Capsule::table('ms365_billing_usage_snapshots')->insert([
                'service_id' => $serviceId,
                'client_id' => $clientId,
                'backup_user_id' => 0,
                'metric' => Ms365BillingConfig::METRIC_PROTECTED_USERS,
                'qty' => max(0, $serviceProtected),
                'taken_at' => $now,
            ]);
            Capsule::table('ms365_billing_usage_snapshots')->insert([
                'service_id' => $serviceId,
                'client_id' => $clientId,
                'backup_user_id' => 0,
                'metric' => Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB,
                'qty' => max(0, $serviceOverageGiB),
                'taken_at' => $now,
            ]);
            $written += 2;
        } else {
            foreach (Ms365BillingConfig::metricKeys() as $metric) {
                Capsule::table('ms365_billing_usage_snapshots')->insert([
                    'service_id' => $serviceId,
                    'client_id' => $clientId,
                    'backup_user_id' => 0,
                    'metric' => $metric,
                    'qty' => 0,
                    'taken_at' => $now,
                ]);
                $written++;
            }
        }

        return $written;
    }

    /** @return array{services: int, rated: int, errors: int} */
    public static function rateAll(): array
    {
        $pids = Ms365BillingConfig::getBillablePids();
        if ($pids === []) {
            return ['services' => 0, 'rated' => 0, 'errors' => 0];
        }

        $services = Capsule::table('tblhosting')
            ->whereIn('packageid', $pids)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->get(['id']);

        $result = ['services' => 0, 'rated' => 0, 'errors' => 0];
        foreach ($services as $svc) {
            $result['services']++;
            try {
                $result['rated'] += self::rateService((int) $svc->id);
            } catch (\Throwable $e) {
                $result['errors']++;
                self::log('rate_service_exception', ['service_id' => (int) $svc->id], $e->getMessage());
            }
        }

        return $result;
    }

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

        $window = self::resolveBillingWindow($svc);
        $maxByMetric = self::maxQtyInWindow($serviceId, $window['start'], $window['end'], backupUserId: 0);
        if (empty(array_filter($maxByMetric))) {
            $backupUserId = self::resolveBackupUserIdForService($serviceId, $clientId);
            if ($backupUserId > 0) {
                $live = Ms365UsageMeter::measureBackupUser($clientId, $backupUserId);
                $maxByMetric = [
                    Ms365BillingConfig::METRIC_PROTECTED_USERS => (int) $live['protected_users'],
                    Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB => (int) $live['onedrive_overage_gib'],
                ];
            } else {
                $maxByMetric = array_fill_keys(Ms365BillingConfig::metricKeys(), 0);
            }
        }

        $currencyId = self::clientCurrencyId($clientId);
        $trialStatus = Ms365BillingTrial::status($serviceId);
        $written = 0;

        foreach (Ms365BillingConfig::metricKeys() as $metric) {
            $qty = (int) ($maxByMetric[$metric] ?? 0);
            $unitPrice = Ms365BillingConfig::unitPriceForMetric($metric);
            $lineAmount = round($qty * $unitPrice, 2);
            $source = 'settings';
            if ($trialStatus === 'trialing') {
                $lineAmount = 0.0;
                $source = 'trial_zeroed';
            }

            $row = [
                'service_id' => $serviceId,
                'client_id' => $clientId,
                'metric' => $metric,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_amount' => $lineAmount,
                'currency_id' => $currencyId,
                'billing_window_start' => $window['start'],
                'billing_window_end' => $window['end'],
                'pricing_source' => $source,
                'notes' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $existing = Capsule::table('ms365_billing_rated_lines')
                ->where('service_id', $serviceId)
                ->where('metric', $metric)
                ->where('billing_window_start', $window['start'])
                ->first();
            if ($existing) {
                Capsule::table('ms365_billing_rated_lines')->where('id', $existing->id)->update($row);
            } else {
                $row['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('ms365_billing_rated_lines')->insert($row);
            }
            $written++;
        }

        return $written;
    }

    public static function applyDefaultConfigOptions(int $serviceId): void
    {
        $map = Ms365BillingConfig::getConfigOptionMap($serviceId);
        if ($map === []) {
            return;
        }
        foreach ($map as $metric => $configId) {
            $configId = (int) $configId;
            if ($configId <= 0) {
                continue;
            }
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
                    ->update(['optionid' => $optionId]);
            } else {
                Capsule::table('tblhostingconfigoptions')->insert([
                    'relid' => $serviceId,
                    'configid' => $configId,
                    'optionid' => $optionId,
                    'qty' => 0,
                ]);
            }
        }
    }

    public static function applyToWhmcs(int $serviceId): int
    {
        $map = Ms365BillingConfig::getConfigOptionMap($serviceId);
        if ($map === []) {
            return 0;
        }
        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$svc) {
            return 0;
        }
        $window = self::resolveBillingWindow($svc);
        $rated = Capsule::table('ms365_billing_rated_lines')
            ->where('service_id', $serviceId)
            ->where('billing_window_start', $window['start'])
            ->get();
        if ($rated->isEmpty()) {
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
                    'relid' => $serviceId,
                    'configid' => $configId,
                    'optionid' => $optionId,
                    'qty' => $qty,
                ]);
            }
            $updated++;
        }

        $billableTotal = 0.0;
        foreach ($rated as $r) {
            $billableTotal += (float) ($r->line_amount ?? 0.0);
        }
        try {
            Capsule::table('tblhosting')->where('id', $serviceId)->update([
                'amount' => round($billableTotal, 2),
            ]);
        } catch (\Throwable $_) {
        }

        return $updated;
    }

    /**
     * Usage summary for a backup user (customer UI + admin).
     *
     * @return array<string, mixed>
     */
    public static function usageSummaryForBackupUser(int $clientId, int $backupUserId): array
    {
        $serviceId = self::resolveServiceIdForBackupUser($clientId, $backupUserId);
        $svc = $serviceId > 0
            ? Capsule::table('tblhosting')->where('id', $serviceId)->first()
            : null;
        $window = $svc ? self::resolveBillingWindow($svc) : self::defaultWindow();

        $live = Ms365UsageMeter::measureBackupUser($clientId, $backupUserId);
        $peakProtected = $live['protected_users'];
        $peakOverageGiB = $live['onedrive_overage_gib'];
        if ($serviceId > 0) {
            $peakProtected = max(
                $peakProtected,
                self::maxMetricForBackupUser($serviceId, $backupUserId, Ms365BillingConfig::METRIC_PROTECTED_USERS, $window['start'], $window['end']),
            );
            $peakOverageGiB = max(
                $peakOverageGiB,
                self::maxMetricForBackupUser($serviceId, $backupUserId, Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB, $window['start'], $window['end']),
            );
        }

        $trialStatus = $serviceId > 0 ? Ms365BillingTrial::status($serviceId) : null;
        $protectedPrice = Ms365BillingConfig::protectedUserPriceCad();
        $overagePrice = Ms365BillingConfig::onedriveOveragePricePerGibCad();
        $wouldBill = ($peakProtected * $protectedPrice) + ($peakOverageGiB * $overagePrice);
        if ($trialStatus === 'trialing') {
            $wouldBill = 0.0;
        }

        $onedriveRows = Capsule::table('ms365_onedrive_usage_daily')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->where('collected_date', date('Y-m-d'))
            ->orderByDesc('overage_bytes')
            ->get();

        if ($onedriveRows->isEmpty() && !empty($live['onedrive_users'])) {
            $onedriveRows = collect($live['onedrive_users']);
        }

        return [
            'client_id' => $clientId,
            'backup_user_id' => $backupUserId,
            'service_id' => $serviceId,
            'trial_status' => $trialStatus,
            'billing_window' => $window,
            'protected_users' => [
                'current' => (int) $live['protected_users'],
                'peak_in_period' => $peakProtected,
            ],
            'onedrive_overage_gib' => [
                'current' => (int) $live['onedrive_overage_gib'],
                'peak_in_period' => $peakOverageGiB,
            ],
            'included_gib_per_user' => Ms365BillingConfig::onedriveIncludedGib(),
            'pricing' => [
                'protected_user_price_cad' => $protectedPrice,
                'onedrive_overage_per_gib_cad' => $overagePrice,
                'estimated_period_total_cad' => round($wouldBill, 2),
            ],
            'onedrive_users' => $onedriveRows instanceof \Illuminate\Support\Collection
                ? $onedriveRows->map(static fn ($r) => is_array($r) ? $r : (array) $r)->values()->all()
                : array_map(static fn ($r) => (array) $r, $onedriveRows->all()),
            'inventory_stale' => (bool) ($live['inventory_stale'] ?? false),
        ];
    }

    /** @return array{start: string, end: string} */
    public static function resolveBillingWindow(object $svc): array
    {
        $end = '';
        if (!empty($svc->nextduedate) && $svc->nextduedate !== '0000-00-00') {
            $end = (string) $svc->nextduedate;
        }
        if ($end === '') {
            return self::defaultWindow();
        }
        $endTs = strtotime($end);
        if ($endTs === false) {
            return self::defaultWindow();
        }

        return [
            'start' => date('Y-m-d', strtotime('-1 month', $endTs)),
            'end' => date('Y-m-d', $endTs),
        ];
    }

    /** @return array{start: string, end: string} */
    public static function defaultWindow(): array
    {
        return [
            'start' => date('Y-m-d', strtotime('-30 days')),
            'end' => date('Y-m-d'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function maxQtyInWindow(int $serviceId, string $startDate, string $endDate, int $backupUserId = 0): array
    {
        $out = array_fill_keys(Ms365BillingConfig::metricKeys(), 0);
        $q = Capsule::table('ms365_billing_usage_snapshots')
            ->select(['metric', Capsule::raw('MAX(qty) as max_qty')])
            ->where('service_id', $serviceId)
            ->where('backup_user_id', $backupUserId)
            ->where('taken_at', '>=', $startDate . ' 00:00:00')
            ->where('taken_at', '<=', $endDate . ' 23:59:59')
            ->groupBy('metric');
        foreach ($q->get() as $r) {
            $out[(string) $r->metric] = (int) ($r->max_qty ?? 0);
        }

        return $out;
    }

    public static function maxMetricForBackupUser(
        int $serviceId,
        int $backupUserId,
        string $metric,
        string $startDate,
        string $endDate,
    ): int {
        try {
            return (int) Capsule::table('ms365_billing_usage_snapshots')
                ->where('service_id', $serviceId)
                ->where('backup_user_id', $backupUserId)
                ->where('metric', $metric)
                ->where('taken_at', '>=', $startDate . ' 00:00:00')
                ->where('taken_at', '<=', $endDate . ' 23:59:59')
                ->max('qty');
        } catch (\Throwable $_) {
            return 0;
        }
    }

    public static function clientCurrencyId(int $clientId): int
    {
        try {
            $cur = (int) Capsule::table('tblclients')->where('id', $clientId)->value('currency');
            if ($cur > 0) {
                return $cur;
            }
        } catch (\Throwable $_) {
        }

        return 1;
    }

    /**
     * Estimate the next invoice for a client (admin trials preview).
     *
     * @return array{
     *   client_id: int,
     *   service_id: int,
     *   currency_id: int,
     *   trial_status: string|null,
     *   has_payment_method: bool,
     *   window: array{start: string, end: string},
     *   lines: list<array{metric: string, metric_label: string, qty: int, unit_price: float, line_amount: float, source: string}>,
     *   total_billable: float,
     *   total_if_paid: float
     * }
     */
    public static function dryRun(int $clientId, ?int $serviceId = null): array
    {
        $pid = Ms365BillingConfig::getPid();
        $svc = null;
        if ($serviceId !== null && $serviceId > 0) {
            $svc = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->where('userid', $clientId)
                ->first();
        }
        if (!$svc && $pid > 0) {
            $svc = Capsule::table('tblhosting')
                ->where('userid', $clientId)
                ->where('packageid', $pid)
                ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
                ->orderByDesc('id')
                ->first();
        }

        $serviceId = $svc ? (int) $svc->id : 0;
        $currencyId = self::clientCurrencyId($clientId);
        $trialStatus = $serviceId > 0 ? Ms365BillingTrial::status($serviceId) : null;
        $window = $svc ? self::resolveBillingWindow($svc) : self::defaultWindow();

        $maxByMetric = $serviceId > 0
            ? self::maxQtyInWindow($serviceId, $window['start'], $window['end'], backupUserId: 0)
            : [];
        if (empty(array_filter($maxByMetric))) {
            $live = Ms365UsageMeter::measureClient($clientId);
            $maxByMetric = [
                Ms365BillingConfig::METRIC_PROTECTED_USERS => (int) $live['protected_users'],
                Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB => (int) $live['onedrive_overage_gib'],
            ];
        }

        $lines = [];
        $totalBillable = 0.0;
        $totalIfPaid = 0.0;
        foreach (Ms365BillingConfig::metricKeys() as $metric) {
            $qty = (int) ($maxByMetric[$metric] ?? 0);
            $unitPrice = Ms365BillingConfig::unitPriceForMetric($metric);
            $lineAmount = round($qty * $unitPrice, 2);
            $totalIfPaid += $lineAmount;

            $effective = $lineAmount;
            $source = 'settings';
            if ($trialStatus === 'trialing') {
                $effective = 0.0;
                $source = 'trial_zeroed';
            }
            $totalBillable += $effective;

            $lines[] = [
                'metric' => $metric,
                'metric_label' => Ms365BillingConfig::metricFriendlyName($metric),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_amount' => $effective,
                'source' => $source,
            ];
        }

        return [
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'currency_id' => $currencyId,
            'trial_status' => $trialStatus,
            'has_payment_method' => Ms365BillingTrial::clientHasCard($clientId),
            'window' => $window,
            'lines' => $lines,
            'total_billable' => round($totalBillable, 2),
            'total_if_paid' => round($totalIfPaid, 2),
        ];
    }

    public static function linkServiceToTenantRecords(int $clientId, int $serviceId): void
    {
        if ($clientId <= 0 || $serviceId <= 0) {
            return;
        }
        try {
            Capsule::table('ms365_tenant_records')
                ->where('whmcs_client_id', $clientId)
                ->where('is_active', 1)
                ->update(['whmcs_service_id' => $serviceId, 'updated_at' => time()]);
        } catch (\Throwable $_) {
        }
    }

    public static function linkServiceToBackupUser(int $clientId, int $backupUserId, int $serviceId): void
    {
        if ($clientId <= 0 || $backupUserId <= 0 || $serviceId <= 0) {
            return;
        }
        try {
            Capsule::table('ms365_tenant_records')
                ->where('whmcs_client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->where('is_active', 1)
                ->update(['whmcs_service_id' => $serviceId, 'updated_at' => time()]);
        } catch (\Throwable $_) {
        }
    }

    public static function resolveServiceIdForBackupUser(int $clientId, int $backupUserId): int
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            return 0;
        }
        try {
            if (Capsule::schema()->hasTable('s3_backup_users')
                && Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')) {
                $fromUser = (int) Capsule::table('s3_backup_users')
                    ->where('id', $backupUserId)
                    ->where('client_id', $clientId)
                    ->where('whmcs_service_id', '>', 0)
                    ->value('whmcs_service_id');
                if ($fromUser > 0) {
                    return $fromUser;
                }
            }
        } catch (\Throwable $_) {
        }
        try {
            $fromTenant = (int) Capsule::table('ms365_tenant_records')
                ->where('whmcs_client_id', $clientId)
                ->where('backup_user_id', $backupUserId)
                ->where('is_active', 1)
                ->where('whmcs_service_id', '>', 0)
                ->orderByDesc('id')
                ->value('whmcs_service_id');
            if ($fromTenant > 0) {
                return $fromTenant;
            }
        } catch (\Throwable $_) {
        }

        $pid = Ms365BillingConfig::getPid();
        if ($pid <= 0) {
            return 0;
        }
        $username = (string) Capsule::table('s3_backup_users')
            ->where('id', $backupUserId)
            ->where('client_id', $clientId)
            ->value('username');
        if ($username === '') {
            return 0;
        }

        return (int) Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('packageid', $pid)
            ->where('username', $username)
            ->whereIn('domainstatus', ['Active', 'Suspended', 'Pending'])
            ->orderByDesc('id')
            ->value('id');
    }

    public static function resolveBackupUserIdForService(int $serviceId, int $clientId = 0): int
    {
        if ($serviceId <= 0) {
            return 0;
        }
        if ($clientId <= 0) {
            $clientId = (int) Capsule::table('tblhosting')->where('id', $serviceId)->value('userid');
        }
        if ($clientId <= 0) {
            return 0;
        }
        try {
            $fromTenant = (int) Capsule::table('ms365_tenant_records')
                ->where('whmcs_service_id', $serviceId)
                ->where('whmcs_client_id', $clientId)
                ->where('is_active', 1)
                ->where('backup_user_id', '>', 0)
                ->orderByDesc('id')
                ->value('backup_user_id');
            if ($fromTenant > 0) {
                return $fromTenant;
            }
        } catch (\Throwable $_) {
        }

        $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$svc || trim((string) ($svc->username ?? '')) === '') {
            return 0;
        }

        return (int) Capsule::table('s3_backup_users')
            ->where('client_id', $clientId)
            ->where('username', (string) $svc->username)
            ->value('id');
    }

    /** @param list<array<string, mixed>> $rows */
    private static function persistOneDriveDaily(
        int $clientId,
        int $backupUserId,
        int $tenantRecordId,
        array $rows,
        string $collectedDate,
    ): void {
        foreach ($rows as $row) {
            $azureUserId = (string) ($row['azure_user_id'] ?? '');
            if ($azureUserId === '') {
                continue;
            }
            $payload = [
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'tenant_record_id' => $tenantRecordId,
                'azure_user_id' => $azureUserId,
                'upn' => (string) ($row['upn'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'drive_id' => (string) ($row['drive_id'] ?? ''),
                'used_bytes' => (int) ($row['used_bytes'] ?? 0),
                'included_bytes' => (int) ($row['included_bytes'] ?? 0),
                'overage_bytes' => (int) ($row['overage_bytes'] ?? 0),
                'collected_date' => $collectedDate,
            ];
            $existing = Capsule::table('ms365_onedrive_usage_daily')
                ->where('backup_user_id', $backupUserId)
                ->where('azure_user_id', $azureUserId)
                ->where('collected_date', $collectedDate)
                ->first();
            if ($existing) {
                Capsule::table('ms365_onedrive_usage_daily')->where('id', $existing->id)->update($payload);
            } else {
                Capsule::table('ms365_onedrive_usage_daily')->insert($payload);
            }
        }
    }

    /** @param mixed $payload */
    private static function log(string $action, array $context, $payload): void
    {
        try {
            logModuleCall(self::MODULE, $action, $context, $payload, [], []);
        } catch (\Throwable $_) {
        }
    }
}
