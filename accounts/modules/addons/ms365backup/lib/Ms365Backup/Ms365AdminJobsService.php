<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365LogFormatter;

require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Ms365BackupBootstrap.php';

/**
 * Admin-facing batch log aggregation for the Jobs tab.
 */
final class Ms365AdminJobsService
{
    /** @return array<string, mixed>|null */
    public static function loadParentRun(string $batchRunId): ?array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $batchRunId)) {
            return null;
        }
        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return null;
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $query = Capsule::table('s3_cloudbackup_runs as r');
        if ($hasJobIdPk) {
            $query->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.job_id');
        } else {
            $query->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id');
        }

        $select = ['r.*', 'j.name as job_name', 'j.client_id'];
        $row = $query
            ->whereRaw('r.run_id = UUID_TO_BIN(?)', [strtolower($batchRunId)])
            ->first($select);
        if (!$row) {
            return null;
        }

        $arr = (array) $row;
        $arr = self::normalizeRowUuids($arr);
        $runType = strtolower((string) ($arr['run_type'] ?? ''));
        $arr['type'] = $runType === 'restore' ? 'restore' : 'backup';

        return $arr;
    }

    /**
     * @return array{
     *   parent: array<string, mixed>,
     *   children: list<array<string, mixed>>,
     *   log_lines: list<string>,
     *   structured_logs: list<array<string, mixed>>
     * }
     */
    public static function aggregateJobLogs(string $batchRunId): array
    {
        cloudstorage_load_ms365backup();
        $parent = self::loadParentRun($batchRunId);
        if ($parent === null) {
            throw new \RuntimeException('Batch run not found.');
        }

        if (Ms365BatchRunRepository::isRestoreBatch($batchRunId)) {
            Ms365BatchRunRepository::syncFromRestoreChildren($batchRunId);
        } else {
            Ms365BatchRunRepository::syncFromChildren($batchRunId);
        }

        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        $childrenDetail = Ms365AdminJobsRepository::getBatchChildrenDetail($batchRunId);
        $childrenById = [];
        foreach ($children as $child) {
            $childrenById[(string) ($child['id'] ?? '')] = $child;
        }

        $logLines = [];
        $structured = [];
        $childIds = array_values(array_filter(array_map(static fn ($c) => (string) ($c['id'] ?? ''), $children)));

        $logLines[] = '=== Batch run ' . $batchRunId . ' ===';
        $logLines[] = 'Job: ' . (string) ($parent['job_name'] ?? '');
        $logLines[] = 'Status: ' . (string) ($parent['status'] ?? '');
        $logLines[] = 'Type: ' . (string) ($parent['type'] ?? 'backup');
        if (!empty($parent['started_at'])) {
            $logLines[] = 'Started: ' . (string) $parent['started_at'];
        }
        if (!empty($parent['finished_at'])) {
            $logLines[] = 'Finished: ' . (string) $parent['finished_at'];
        }
        if (!empty($parent['error_summary'])) {
            $logLines[] = 'Error summary: ' . (string) $parent['error_summary'];
        }
        if (!empty($parent['log_excerpt'])) {
            $logLines[] = '';
            $logLines[] = '--- Parent log excerpt ---';
            $logLines[] = (string) $parent['log_excerpt'];
        }

        foreach ($childrenDetail as $detail) {
            $logLines[] = '';
            $logLines[] = '--- Workload: ' . (string) ($detail['workload_label'] ?? '') . ' ---';
            $logLines[] = 'Child run ID: ' . (string) ($detail['run_id'] ?? '');
            $logLines[] = 'Status: ' . (string) ($detail['status'] ?? '');
            if ((int) ($detail['attempts'] ?? 0) > 0) {
                $logLines[] = 'Attempts: ' . (int) $detail['attempts'] . '/' . (int) ($detail['max_attempts'] ?? 3);
            }
            if (!empty($detail['error_message'])) {
                $logLines[] = 'Error: ' . (string) $detail['error_message'];
            }
            if (!empty($detail['last_error']) && $detail['last_error'] !== ($detail['error_message'] ?? '')) {
                $logLines[] = 'Queue error: ' . (string) $detail['last_error'];
            }
        }

        if (Capsule::schema()->hasTable('ms365_backup_log_lines')) {
            if ($childIds !== []) {
                $logLines[] = '';
                $logLines[] = '=== Progress log lines ===';
                $rows = Capsule::table('ms365_backup_log_lines')
                    ->whereIn('run_id', $childIds)
                    ->orderBy('id', 'asc')
                    ->limit(5000)
                    ->get();
                foreach ($rows as $row) {
                    $line = (array) $row;
                    $child = $childrenById[(string) ($line['run_id'] ?? '')] ?? [];
                    $structured[] = Ms365LogFormatter::toStructuredLog($line, $child);
                    $ts = !empty($line['created_at']) ? gmdate('Y-m-d H:i:s', (int) $line['created_at']) . ' UTC' : '';
                    $level = strtoupper((string) ($line['level'] ?? 'info'));
                    $msg = Ms365LogFormatter::formatMessage($line, $child);
                    $logLines[] = ($ts !== '' ? '[' . $ts . '] ' : '') . '(' . $level . ') ' . $msg;
                }
            }
        }

        if (Capsule::schema()->hasTable('ms365_worker_log_lines') && $childIds !== []) {
            $workerLines = Ms365WorkerLogRepository::getLogLinesForRuns($childIds);
            if ($workerLines !== []) {
                $logLines[] = '';
                $logLines[] = '=== Worker diagnostic log lines ===';
                foreach ($workerLines as $line) {
                    $host = (string) ($line['hostname'] ?? $line['worker_node_id'] ?? '');
                    $ts = !empty($line['created_at']) ? gmdate('Y-m-d H:i:s', (int) $line['created_at']) . ' UTC' : '';
                    $level = strtoupper((string) ($line['level'] ?? 'info'));
                    $hostPrefix = $host !== '' ? '[' . $host . '] ' : '';
                    $logLines[] = ($ts !== '' ? '[' . $ts . '] ' : '') . $hostPrefix . '(' . $level . ') ' . (string) ($line['message'] ?? '');
                    $structured[] = [
                        'ts' => $ts !== '' ? rtrim($ts, ' UTC') : '',
                        'level' => (string) ($line['level'] ?? 'info'),
                        'code' => 'worker',
                        'message' => ($host !== '' ? '[' . $host . '] ' : '') . (string) ($line['message'] ?? ''),
                        'details' => [
                            'run_id' => (string) ($line['run_id'] ?? ''),
                            'worker_node_id' => (string) ($line['worker_node_id'] ?? ''),
                        ],
                    ];
                }
            }
        }

        return self::sanitizeForJson([
            'parent' => $parent,
            'children' => $childrenDetail,
            'log_lines' => $logLines,
            'structured_logs' => $structured,
        ]);
    }

    /**
     * @return array{
     *   log_lines: list<string>,
     *   lines: list<array<string, mixed>>,
     *   nodes: list<array<string, mixed>>,
     *   assignments: list<array<string, mixed>>,
     *   journal_commands: list<string>,
     *   journal_fallback: bool
     * }
     */
    public static function aggregateWorkerLogs(string $batchRunId, bool $allowJournalFallback = true): array
    {
        cloudstorage_load_ms365backup();
        $parent = self::loadParentRun($batchRunId);
        if ($parent === null) {
            throw new \RuntimeException('Batch run not found.');
        }

        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        $childIds = array_values(array_filter(array_map(static fn ($c) => (string) ($c['id'] ?? ''), $children)));

        $assignments = Ms365WorkerLogRepository::getAssignmentsForRuns($childIds);
        $lines = Ms365WorkerLogRepository::getLogLinesForRuns($childIds);
        $logLines = [];
        $nodesById = [];

        foreach ($assignments as $a) {
            $nodesById[(string) ($a['worker_node_id'] ?? '')] = [
                'worker_node_id' => (string) ($a['worker_node_id'] ?? ''),
                'hostname' => (string) ($a['hostname'] ?? ''),
                'proxmox_vmid' => (int) ($a['proxmox_vmid'] ?? 0),
            ];
        }

        $logLines[] = '=== Worker logs for batch ' . $batchRunId . ' ===';
        if ($assignments === []) {
            $logLines[] = '(No worker assignment history recorded for this batch.)';
        } else {
            $logLines[] = '';
            $logLines[] = '--- Worker assignments ---';
            foreach ($assignments as $a) {
                $host = (string) ($a['hostname'] ?? $a['worker_node_id']);
                $claimed = !empty($a['claimed_at']) ? gmdate('Y-m-d H:i:s', (int) $a['claimed_at']) . ' UTC' : '—';
                $released = !empty($a['released_at']) ? gmdate('Y-m-d H:i:s', (int) $a['released_at']) . ' UTC' : 'active';
                $logLines[] = sprintf(
                    '[%s] run=%s claimed=%s released=%s reason=%s',
                    $host,
                    (string) ($a['run_id'] ?? ''),
                    $claimed,
                    $released,
                    (string) ($a['release_reason'] ?? '')
                );
            }
        }

        $journalFallback = false;
        if ($lines === [] && $allowJournalFallback && $assignments !== []) {
            $journalText = ProxmoxProvisioner::fetchBatchWorkerJournal($parent, $childIds, $assignments);
            if ($journalText !== '') {
                $journalFallback = true;
                $logLines[] = '';
                $logLines[] = '--- Journal (Proxmox fallback) ---';
                foreach (explode("\n", $journalText) as $jl) {
                    if (trim($jl) !== '') {
                        $logLines[] = $jl;
                    }
                }
            }
        }

        if ($lines !== []) {
            $logLines[] = '';
            $logLines[] = '--- Ingested worker log lines ---';
            foreach ($lines as $line) {
                $host = (string) ($line['hostname'] ?? $line['worker_node_id'] ?? '');
                $ts = !empty($line['created_at']) ? gmdate('Y-m-d H:i:s', (int) $line['created_at']) . ' UTC' : '';
                $level = strtoupper((string) ($line['level'] ?? 'info'));
                $logLines[] = sprintf(
                    '%s[%s] [%s] (%s) run=%s %s',
                    $ts !== '' ? '[' . $ts . '] ' : '',
                    $host,
                    (string) ($line['run_id'] ?? ''),
                    $level,
                    (string) ($line['run_id'] ?? ''),
                    (string) ($line['message'] ?? '')
                );
                $nid = (string) ($line['worker_node_id'] ?? '');
                if ($nid !== '') {
                    $nodesById[$nid] = [
                        'worker_node_id' => $nid,
                        'hostname' => (string) ($line['hostname'] ?? ''),
                        'proxmox_vmid' => (int) ($line['proxmox_vmid'] ?? 0),
                    ];
                }
            }
        } elseif (!$journalFallback) {
            $logLines[] = '';
            $logLines[] = '(No worker log lines ingested yet. Deploy worker 0.1.24+ or use manual commands below.)';
        }

        $journalCommands = ProxmoxProvisioner::buildJournalCommands($parent, $childIds, $assignments);

        return [
            'log_lines' => $logLines,
            'lines' => $lines,
            'nodes' => array_values($nodesById),
            'assignments' => $assignments,
            'journal_commands' => $journalCommands,
            'journal_fallback' => $journalFallback,
        ];
    }

    private static function binaryToUuid(string $binary): string
    {
        $hex = bin2hex($binary);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /** @param array<string, mixed> $arr */
    private static function normalizeRowUuids(array $arr): array
    {
        foreach (['run_id', 'job_id', 'agent_uuid', 'repo_id'] as $key) {
            if (!isset($arr[$key]) || !is_string($arr[$key])) {
                continue;
            }
            if (strlen($arr[$key]) === 16) {
                $arr[$key] = self::binaryToUuid($arr[$key]);
            }
        }

        return $arr;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function sanitizeForJson($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::sanitizeForJson($v);
            }

            return $out;
        }
        if (is_string($value)) {
            if (strlen($value) === 16 && !mb_check_encoding($value, 'UTF-8')) {
                return self::binaryToUuid($value);
            }
            if (!mb_check_encoding($value, 'UTF-8')) {
                $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

                return is_string($clean) ? $clean : '';
            }
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function parentForApi(string $batchRunId): array
    {
        $parent = self::loadParentRun($batchRunId);
        if ($parent === null) {
            throw new \RuntimeException('Batch run not found.');
        }

        return self::sanitizeForJson($parent);
    }
}
