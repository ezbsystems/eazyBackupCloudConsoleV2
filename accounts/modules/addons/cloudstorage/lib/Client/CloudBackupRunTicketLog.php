<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Builds run log text for support tickets (server-side, sanitized).
 */
class CloudBackupRunTicketLog
{
    /**
     * @return array{content:string,filename:string}|null
     */
    public static function buildForRun(string $runId, int $clientId): ?array
    {
        $run = CloudBackupController::getRun($runId, $clientId);
        if (!$run) {
            return null;
        }

        $job = CloudBackupController::getJob($run['job_id'] ?? '', $clientId);
        $jobName = (string) ($job['name'] ?? 'Backup job');
        $engine = CloudBackupEngineLabel::label($run['engine'] ?? ($job['engine'] ?? 'sync'));

        $agentHostname = '';
        $agentUuid = $run['agent_uuid'] ?? ($job['agent_uuid'] ?? null);
        if (!empty($agentUuid)) {
            $agentHostname = (string) (Capsule::table('s3_cloudbackup_agents')
                ->where('agent_uuid', $agentUuid)
                ->where('client_id', $clientId)
                ->value('hostname') ?? '');
        }

        $userTz = TimezoneHelper::resolveUserTimezone($clientId, $run['job_id'] ?? null);
        $startedFmt = !empty($run['started_at'])
            ? TimezoneHelper::formatTimestamp($run['started_at'], $userTz)
            : '';
        $finishedFmt = !empty($run['finished_at'])
            ? TimezoneHelper::formatTimestamp($run['finished_at'], $userTz)
            : '';
        $statusLabel = ucfirst(str_replace('_', ' ', strtolower((string) ($run['status'] ?? ''))));

        $lines = [];
        $lines[] = 'e3 Cloud Backup Run Log';
        $lines[] = 'Run ID:   ' . $runId;
        $lines[] = 'Job:      ' . $jobName;
        if ($agentHostname !== '') {
            $lines[] = 'Agent:    ' . $agentHostname;
        }
        $lines[] = 'Engine:   ' . $engine;
        $lines[] = 'Status:   ' . $statusLabel;
        if ($startedFmt !== '') {
            $lines[] = 'Started:  ' . $startedFmt;
        }
        if ($finishedFmt !== '') {
            $lines[] = 'Finished: ' . $finishedFmt;
        }
        $lines[] = '---';

        $structured = self::fetchStructuredLogs($runId, $run, $userTz, 5000);
        foreach ($structured as $row) {
            $ts = !empty($row['ts']) ? '[' . $row['ts'] . '] ' : '';
            $lvl = !empty($row['level']) ? '(' . strtoupper((string) $row['level']) . ') ' : '';
            $lines[] = $ts . $lvl . (string) ($row['message'] ?? '');
        }

        if (count($lines) <= 8) {
            $sanitized = SanitizedLogFormatter::sanitizeAndStructure(
                $run['log_excerpt'] ?? null,
                $run['status'] ?? null,
                $userTz
            );
            $formatted = trim((string) ($sanitized['formatted_log'] ?? ''));
            if ($formatted !== '') {
                foreach (preg_split('/\r?\n/', $formatted) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $lines[] = $line;
                    }
                }
            }
        }

        $content = CloudBackupEngineLabel::sanitizeText(implode("\n", $lines) . "\n");
        $safeId = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $runId);
        $safeId = trim((string) $safeId, '_');
        if ($safeId === '') {
            $safeId = 'run';
        }
        $filename = 'run-' . substr($safeId, 0, 80) . '.txt';

        return ['content' => $content, 'filename' => $filename];
    }

    /**
     * @return list<array{ts:string,level:string,message:string}>
     */
    private static function fetchStructuredLogs(string $runIdentifier, array $run, \DateTimeZone $userTz, int $limit): array
    {
        $structured = [];
        try {
            if (Capsule::schema()->hasTable('s3_cloudbackup_run_logs')) {
                $logQuery = Capsule::table('s3_cloudbackup_run_logs')
                    ->orderBy('created_at', 'asc')
                    ->limit($limit);
                if (UuidBinary::isUuid($runIdentifier)) {
                    $logQuery->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runIdentifier)));
                } else {
                    $logQuery->where('run_id', $run['id'] ?? 0);
                }
                foreach ($logQuery->get() as $row) {
                    $structured[] = [
                        'ts' => TimezoneHelper::formatTimestamp($row->created_at, $userTz),
                        'level' => (string) ($row->level ?? 'info'),
                        'message' => CloudBackupEngineLabel::sanitizeText((string) ($row->message ?? '')),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        if (!empty($structured) || !Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            return $structured;
        }

        try {
            $eventQuery = Capsule::table('s3_cloudbackup_run_events')
                ->select(['id', 'ts', 'level', 'message_id', 'params_json'])
                ->orderBy('id', 'asc')
                ->limit($limit);
            if (UuidBinary::isUuid($runIdentifier)) {
                $eventQuery->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runIdentifier)));
            } else {
                $eventQuery->where('run_id', $run['id'] ?? 0);
            }
            foreach ($eventQuery->get() as $row) {
                $params = [];
                if (!empty($row->params_json)) {
                    $decoded = json_decode($row->params_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $params = $decoded;
                    }
                }
                $structured[] = [
                    'ts' => TimezoneHelper::formatTimestamp($row->ts, $userTz),
                    'level' => (string) ($row->level ?? 'info'),
                    'message' => CloudBackupEventFormatter::render((string) ($row->message_id ?? ''), $params),
                ];
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        return $structured;
    }
}
