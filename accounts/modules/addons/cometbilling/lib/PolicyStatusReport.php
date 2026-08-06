<?php
namespace CometBilling;

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

    public static function isWarningOrError(int $status): bool
    {
        return self::severityRank($status) >= 3;
    }

    public static function normalizeAccountKey(string $name): string
    {
        return strtolower(trim($name));
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
     * @param array<string, array{categories: list<string>, amount: float, line_count: int}> $billedByAccount
     */
    public static function buildSections(array $accounts, array $billedByAccount): array
    {
        $warning = [];
        $billedUnhealthy = [];
        foreach ($accounts as $acct) {
            $status = (int) ($acct['status'] ?? 0);
            if (self::isWarning($status)) {
                $warning[] = $acct;
            }
            $key = self::normalizeAccountKey((string) ($acct['username'] ?? ''));
            if ($key !== '' && isset($billedByAccount[$key]) && self::isWarningOrError($status)) {
                $billedUnhealthy[] = $acct + [
                    'billed_categories' => $billedByAccount[$key]['categories'],
                    'billed_amount' => $billedByAccount[$key]['amount'],
                    'billed_line_count' => $billedByAccount[$key]['line_count'],
                ];
            }
        }
        return [
            'warning_accounts' => $warning,
            'billed_unhealthy' => $billedUnhealthy,
        ];
    }
}
