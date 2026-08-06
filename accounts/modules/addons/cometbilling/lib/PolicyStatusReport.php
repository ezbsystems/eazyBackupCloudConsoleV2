<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

final class PolicyStatusReport
{
    public const POLICY_MAP = [
        'obc' => '9005920f-fa54-4a22-8844-533bda81da4c',
        'cometbackup' => '0e545d31-e0b3-4b38-8456-0999fa46f588',
    ];

    public const SERVER_LABELS = [
        'obc' => 'csw.obcbackup.com',
        'cometbackup' => 'csw.eazybackup.ca',
    ];

    public static function severityRank(int $status): int
    {
        if ($status === 5000) return 0;
        if ($status >= 6000 && $status <= 6002) return 1;
        if ($status === 7004) return 2;
        if ($status === 7001) return 3;
        if (in_array($status, [7000, 7002, 7003, 7005, 7006, 7007], true)) return 4;
        if ($status > 0) return 4;
        return -1;
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            5000 => 'success',
            6000, 6001, 6002 => 'running',
            7000 => 'timeout',
            7001 => 'warning',
            7002 => 'error',
            7003 => 'quota',
            7004 => 'missed',
            7005 => 'cancelled',
            7006 => 'already_running',
            7007 => 'abandoned',
            default => 'unknown',
        };
    }

    public static function isWarning(int $status): bool
    {
        return $status === 7001;
    }

    public static function isSuccess(int $status): bool
    {
        return $status === 5000;
    }

    public static function isWarningOrError(int $status): bool
    {
        return self::severityRank($status) >= 3;
    }

    public static function normalizeAccountKey(string $name): string
    {
        return strtolower(trim($name));
    }

    public static function indexLatestActiveServicesByAccount(): array
    {
        $latest = Capsule::table('cb_active_services')->max('pulled_at');
        if (!$latest) {
            return ['pulled_at' => null, 'by_account' => []];
        }
        $rows = Capsule::table('cb_active_services')->where('pulled_at', $latest)->get();
        return ['pulled_at' => $latest, 'by_account' => self::indexActiveServicesRows($rows)];
    }

    public static function indexActiveServicesRows(iterable $rows): array
    {
        $by = [];
        foreach ($rows as $row) {
            $account = trim((string) ($row->tenant_id ?? ''));
            if ($account === '') {
                $sn = (string) ($row->service_name ?? '');
                if (preg_match('/Account\s+([^\-]+)/i', $sn, $m)) {
                    $account = trim($m[1]);
                }
            }
            if ($account === '') {
                continue;
            }
            $key = self::normalizeAccountKey($account);
            if (!isset($by[$key])) {
                $by[$key] = [
                    'categories' => [],
                    'amount' => 0.0,
                    'device_amount' => 0.0,
                    'booster_amount' => 0.0,
                    'line_count' => 0,
                    'display_name' => $account,
                ];
            }
            $serviceName = (string) ($row->service_name ?? '');
            $bucket = self::activeServiceChargeBucket($row, $serviceName);
            $cat = self::activeServiceCategory($bucket, $serviceName);
            if ($cat !== null && !in_array($cat, $by[$key]['categories'], true)) {
                $by[$key]['categories'][] = $cat;
            }
            $amount = (float) ($row->amount ?? 0);
            $by[$key]['amount'] += $amount;
            if ($bucket === 'device') {
                $by[$key]['device_amount'] += $amount;
            } elseif ($bucket === 'booster') {
                $by[$key]['booster_amount'] += $amount;
            }
            $by[$key]['line_count']++;
        }
        return $by;
    }

    /**
     * Classify an Active Services row as device or booster using portal Type when present.
     */
    public static function activeServiceChargeBucket(object $row, ?string $serviceName = null): string
    {
        $extra = $row->extra ?? null;
        if (is_string($extra) && $extra !== '') {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($extra)) {
            $extra = [];
        }
        $type = strtolower(trim((string) ($extra['Type'] ?? '')));
        if ($type === 'device') {
            return 'device';
        }
        if ($type === 'booster') {
            return 'booster';
        }

        $sn = strtolower((string) ($serviceName ?? $row->service_name ?? ''));
        if (str_contains($sn, 'booster') || str_contains($sn, 'office 365')
            || str_contains($sn, 'microsoft 365') || str_contains($sn, 'm365')
            || str_contains($sn, 'hyper-v') || str_contains($sn, 'hyperv')
            || str_contains($sn, 'vmware') || str_contains($sn, 'proxmox')
            || str_contains($sn, 'disk image') || str_contains($sn, 'sql server')) {
            return 'booster';
        }
        if ($type === 'account' || $type === 'serial') {
            return 'other';
        }
        return 'device';
    }

    private static function activeServiceCategory(string $bucket, string $serviceName): ?string
    {
        if ($bucket === 'device') {
            return 'devices';
        }
        if ($bucket === 'booster') {
            $sn = strtolower($serviceName);
            if (str_contains($sn, 'office 365') || str_contains($sn, 'microsoft 365') || str_contains($sn, 'm365')) {
                return 'm365_accounts';
            }
            if (str_contains($sn, 'hyper-v') || str_contains($sn, 'hyperv')) {
                return 'hyperv_vms';
            }
            if (str_contains($sn, 'vmware')) {
                return 'vmware_vms';
            }
            if (str_contains($sn, 'proxmox')) {
                return 'proxmox_vms';
            }
            if (str_contains($sn, 'disk image')) {
                return 'disk_image';
            }
            if (str_contains($sn, 'sql server') || str_contains($sn, 'mssql')) {
                return 'mssql';
            }
            return 'other';
        }
        return null;
    }

    public static function report(): array
    {
        $accounts = [];
        $serverErrors = [];

        foreach (self::POLICY_MAP as $key => $policyId) {
            try {
                $server = ServerUsageCollector::openServer($key);
                if ($server === null) {
                    $serverErrors[$key] = "Cannot connect to Comet server: {$key}";
                    continue;
                }
                $profiles = $server->AdminListUsersFull();
                foreach ($profiles as $profile) {
                    $profilePolicyId = (string) self::profileValue($profile, 'PolicyID');
                    if ($profilePolicyId !== $policyId) {
                        continue;
                    }

                    $sources = [];
                    foreach ((self::profileValue($profile, 'Sources') ?? []) as $sourceId => $source) {
                        $job = $source->Statistics->LastBackupJob ?? null;
                        if ($job === null) {
                            continue;
                        }
                        $status = (int) ($job->Status ?? 0);
                        if ($status <= 0) {
                            continue;
                        }
                        $sources[] = [
                            'status' => $status,
                            'end_time' => (int) ($job->EndTime ?? 0),
                            'start_time' => (int) ($job->StartTime ?? 0),
                            'source_id' => (string) $sourceId,
                        ];
                    }
                    $agg = self::aggregateAccountFromSources($sources);
                    if ($agg === null) {
                        continue;
                    }
                    $accounts[] = [
                        'server_key' => $key,
                        'server_label' => self::SERVER_LABELS[$key] ?? $key,
                        'policy_id' => $policyId,
                        'username' => (string) self::profileValue($profile, 'Username'),
                        'status' => $agg['status'],
                        'status_label' => $agg['status_label'],
                        'last_job_time' => $agg['last_job_time'],
                        'source_count' => $agg['source_count'],
                        'warning_source_count' => $agg['warning_source_count'],
                        'error_source_count' => $agg['error_source_count'],
                    ];
                }
            } catch (\Throwable $e) {
                $serverErrors[$key] = $e->getMessage();
            }
        }

        $activeServices = self::indexLatestActiveServicesByAccount();
        $sections = self::buildSections($accounts, $activeServices['by_account']);

        return $sections + [
            'active_services_pulled_at' => $activeServices['pulled_at'],
            'server_errors' => $serverErrors,
            'account_count' => count($accounts),
            'server_error_count' => count($serverErrors),
        ];
    }

    private static function profileValue($profile, string $key)
    {
        if (is_array($profile)) {
            return $profile[$key] ?? null;
        }

        return is_object($profile) ? ($profile->{$key} ?? null) : null;
    }

    /** @param list<array{status:int,end_time?:int,start_time?:int,source_id?:string}> $sources */
    public static function aggregateAccountFromSources(array $sources): ?array
    {
        $best = null;
        $warn = 0;
        $err = 0;
        $withJob = 0;
        foreach ($sources as $src) {
            $status = (int) ($src['status'] ?? 0);
            if ($status <= 0) {
                continue;
            }
            $withJob++;
            if ($status === 7001) {
                $warn++;
            }
            if (self::severityRank($status) === 4) {
                $err++;
            }
            $end = (int) ($src['end_time'] ?? $src['start_time'] ?? 0);
            if ($best === null
                || self::severityRank($status) > self::severityRank((int) $best['status'])
                || (self::severityRank($status) === self::severityRank((int) $best['status']) && $end > (int) $best['last_job_time'])
            ) {
                $best = [
                    'status' => $status,
                    'last_job_time' => $end,
                    'source_id' => $src['source_id'] ?? null,
                ];
            }
        }
        if ($best === null) {
            return null;
        }
        return [
            'status' => $best['status'],
            'status_label' => self::statusLabel((int) $best['status']),
            'last_job_time' => $best['last_job_time'],
            'source_count' => $withJob,
            'warning_source_count' => $warn,
            'error_source_count' => $err,
        ];
    }

    /**
     * @param list<array<string,mixed>> $accounts
     * @param array<string, array{categories: list<string>, amount: float, device_amount?: float, booster_amount?: float, line_count: int}> $billedByAccount
     */
    public static function buildSections(array $accounts, array $billedByAccount): array
    {
        $warning = [];
        $billedUnhealthy = [];
        $billedSuccessful = [];
        foreach ($accounts as $acct) {
            $status = (int) ($acct['status'] ?? 0);
            if (self::isWarning($status)) {
                $warning[] = $acct;
            }
            $key = self::normalizeAccountKey((string) ($acct['username'] ?? ''));
            if ($key === '' || !isset($billedByAccount[$key])) {
                continue;
            }
            $billed = $billedByAccount[$key];
            $billedFields = [
                'billed_categories' => $billed['categories'],
                'billed_amount' => $billed['amount'],
                'billed_device_amount' => (float) ($billed['device_amount'] ?? 0),
                'billed_booster_amount' => (float) ($billed['booster_amount'] ?? 0),
                'billed_line_count' => $billed['line_count'],
            ];
            if (self::isWarningOrError($status)) {
                $billedUnhealthy[] = $acct + $billedFields;
            }
            if (self::isSuccess($status)) {
                $billedSuccessful[] = $acct + $billedFields;
            }
        }
        return [
            'warning_accounts' => $warning,
            'billed_unhealthy' => $billedUnhealthy,
            'billed_successful' => $billedSuccessful,
        ];
    }
}
