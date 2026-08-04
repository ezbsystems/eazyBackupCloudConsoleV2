<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Recent Backup History grid model (per-agent / MS365 synthetic row rollups).
 */
final class E3BackupHistoryService
{
    public const MS365_SYNTHETIC_AGENT_UUID = '__ms365__';
    public const MS365_DISPLAY_NAME = 'Microsoft 365';
    public const WORKLOAD_MS365 = 'ms365';

  /** @var array<string, int> */
    private const STATUS_PRIORITY = [
        'failed' => 1,
        'partial_success' => 2,
        'warning' => 3,
        'cancelled' => 4,
        'running' => 5,
        'starting' => 5,
        'queued' => 6,
        'success' => 7,
    ];

    public const MS365_FILTER_SKIP = 'skip';
    public const MS365_FILTER_INCLUDE = 'include';
    public const MS365_FILTER_ONLY = 'only';

    public static function ms365FilterMode(string $agentFilter): string
    {
        if ($agentFilter === self::MS365_SYNTHETIC_AGENT_UUID) {
            return self::MS365_FILTER_ONLY;
        }
        if ($agentFilter !== '') {
            return self::MS365_FILTER_SKIP;
        }

        return self::MS365_FILTER_INCLUDE;
    }

    /**
     * @return array{
     *   agent_uuid: string,
     *   hostname: string,
     *   agent_os: string,
     *   is_online: bool,
     *   _days: array<string, array{status: ?string, count: int, runs: list<array<string, mixed>>}>,
     *   _jobs: array<string, array{job_id: string, name: string, days: array<string, ?string>}>,
     *   _last24h: array{success: int, warning: int, failed: int, running: int, cancelled: int}
     * }
     */
    public static function newAgentEntry(string $agentUuid, string $hostname, string $agentOs, bool $isOnline): array
    {
        return [
            'agent_uuid' => $agentUuid,
            'hostname' => $hostname,
            'agent_os' => $agentOs,
            'is_online' => $isOnline,
            '_days' => [],
            '_jobs' => [],
            '_last24h' => ['success' => 0, 'warning' => 0, 'failed' => 0, 'running' => 0, 'cancelled' => 0],
        ];
    }

    public static function newMs365AgentEntry(): array
    {
        return self::newAgentEntry(
            self::MS365_SYNTHETIC_AGENT_UUID,
            self::MS365_DISPLAY_NAME,
            self::MS365_DISPLAY_NAME,
            true
        );
    }

    public static function worseStatus(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }
        $pa = self::STATUS_PRIORITY[$a] ?? 99;
        $pb = self::STATUS_PRIORITY[$b] ?? 99;

        return $pa <= $pb ? $a : $b;
    }

    /**
     * @param array{
     *   _days: array<string, array{status: ?string, count: int, runs: list<array<string, mixed>>}>,
     *   _jobs: array<string, array{job_id: string, name: string, days: array<string, ?string>}>,
     *   _last24h: array{success: int, warning: int, failed: int, running: int, cancelled: int}
     * } $agent
     */
    public static function applyRunToAgent(
        array &$agent,
        string $runId,
        string $jobId,
        string $jobName,
        string $status,
        ?string $startedAt,
        int $bytes,
        int $cutoff24h,
        ?callable $formatSize = null,
        ?callable $epochMs = null
    ): void {
        $status = strtolower($status);
        $startedTs = !empty($startedAt) ? (int) strtotime((string) $startedAt) : 0;
        $dayKey = $startedTs ? date('Y-m-d', $startedTs) : date('Y-m-d');

        if (!isset($agent['_days'][$dayKey])) {
            $agent['_days'][$dayKey] = ['status' => null, 'count' => 0, 'runs' => []];
        }
        $agent['_days'][$dayKey]['status'] = self::worseStatus($agent['_days'][$dayKey]['status'], $status);
        $agent['_days'][$dayKey]['count']++;
        if (count($agent['_days'][$dayKey]['runs']) < 25) {
            $size = '-';
            if ($bytes > 0 && $formatSize !== null) {
                $size = (string) $formatSize($bytes);
            }
            $agent['_days'][$dayKey]['runs'][] = [
                'run_id' => $runId,
                'job_name' => $jobName,
                'status' => $status,
                'time' => $startedTs ? date('H:i', $startedTs) : '',
                'started_at' => (string) ($startedAt ?? ''),
                'started_at_epoch_ms' => $epochMs !== null ? $epochMs($startedAt) : null,
                'size' => $size,
            ];
        }

        if ($jobId !== '') {
            if (!isset($agent['_jobs'][$jobId])) {
                $agent['_jobs'][$jobId] = ['job_id' => $jobId, 'name' => $jobName, 'days' => []];
            }
            $cur = $agent['_jobs'][$jobId]['days'][$dayKey] ?? null;
            $agent['_jobs'][$jobId]['days'][$dayKey] = self::worseStatus($cur, $status);
        }

        if ($startedTs >= $cutoff24h) {
            $bucket = isset($agent['_last24h'][$status]) ? $status : null;
            if ($bucket === null) {
                if ($status === 'partial_success') {
                    $bucket = 'warning';
                } elseif ($status === 'starting' || $status === 'queued') {
                    $bucket = 'running';
                }
            }
            if ($bucket !== null) {
                $agent['_last24h'][$bucket]++;
            }
        }
    }

    /**
     * @param list<string> $dayList
     * @param array{
     *   agent_uuid: string,
     *   hostname: string,
     *   agent_os: string,
     *   is_online: bool,
     *   _days: array<string, array{status: ?string, count: int, runs: list<array<string, mixed>>}>,
     *   _jobs: array<string, array{job_id: string, name: string, days: array<string, ?string>}>,
     *   _last24h: array{success: int, warning: int, failed: int, running: int, cancelled: int}
     * } $agent
     * @return array{
     *   agent_uuid: string,
     *   hostname: string,
     *   agent_os: string,
     *   is_online: bool,
     *   last24h: array{success: int, warning: int, failed: int, running: int, cancelled: int},
     *   days: list<array{date: string, label: string, status: ?string, count: int, runs: list<array<string, mixed>>}>,
     *   jobs: list<array{job_id: string, name: string, days: list<array{date: string, label: string, status: ?string}>}>
     * }
     */
    public static function assembleAgentOutput(array $agent, array $dayList): array
    {
        $daysOut = [];
        foreach ($dayList as $d) {
            $cell = $agent['_days'][$d] ?? null;
            $daysOut[] = [
                'date' => $d,
                'label' => date('M j', strtotime($d)),
                'status' => $cell ? $cell['status'] : null,
                'count' => $cell ? $cell['count'] : 0,
                'runs' => $cell ? $cell['runs'] : [],
            ];
        }

        $jobsOut = [];
        foreach ($agent['_jobs'] as $job) {
            $jobDays = [];
            foreach ($dayList as $d) {
                $jobDays[] = [
                    'date' => $d,
                    'label' => date('M j', strtotime($d)),
                    'status' => $job['days'][$d] ?? null,
                ];
            }
            $jobsOut[] = [
                'job_id' => $job['job_id'],
                'name' => $job['name'],
                'days' => $jobDays,
            ];
        }

        return [
            'agent_uuid' => $agent['agent_uuid'],
            'hostname' => $agent['hostname'],
            'agent_os' => $agent['agent_os'],
            'is_online' => $agent['is_online'],
            'last24h' => $agent['_last24h'],
            'days' => $daysOut,
            'jobs' => $jobsOut,
        ];
    }

    /**
     * @return list<string>
     */
    public static function buildDayList(int $days): array
    {
        $dayList = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayList[] = date('Y-m-d', strtotime('-' . $i . ' days'));
        }

        return $dayList;
    }

    /**
     * @param mixed $query
     */
    public static function applyMs365JobScope($query, bool $hasJobSourceType, bool $hasJobEngine): void
    {
        $query->where(function ($w) use ($hasJobSourceType, $hasJobEngine) {
            $applied = false;
            if ($hasJobEngine) {
                $w->where('j.engine', self::WORKLOAD_MS365);
                $applied = true;
            }
            if ($hasJobSourceType) {
                if ($applied) {
                    $w->orWhere('j.source_type', self::WORKLOAD_MS365);
                } else {
                    $w->where('j.source_type', self::WORKLOAD_MS365);
                    $applied = true;
                }
            }
            if (!$applied) {
                $w->whereRaw('1 = 0');
            }
        });
    }
}
