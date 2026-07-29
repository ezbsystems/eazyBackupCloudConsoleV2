<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Client/E3BackupRunListService.php';

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupRunListService;

function assertEq($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

assertEq(
    E3BackupRunListService::WORKLOAD_MS365,
    E3BackupRunListService::categorizeWorkload('ms365', 'ms365', ''),
    'ms365 by source_type'
);
assertEq(
    E3BackupRunListService::WORKLOAD_MS365,
    E3BackupRunListService::categorizeWorkload('', 'ms365', ''),
    'ms365 by engine'
);
assertEq(
    E3BackupRunListService::WORKLOAD_LOCAL_AGENT,
    E3BackupRunListService::categorizeWorkload('local_agent', 'kopia', ''),
    'local_agent by source_type'
);
assertEq(
    E3BackupRunListService::WORKLOAD_LOCAL_AGENT,
    E3BackupRunListService::categorizeWorkload('', 'kopia', 'agent-uuid-1'),
    'local_agent by agent_uuid'
);
assertEq(
    E3BackupRunListService::WORKLOAD_CLOUD_TO_CLOUD,
    E3BackupRunListService::categorizeWorkload('google_drive', 'sync', ''),
    'cloud_to_cloud google_drive'
);

assertEq(
    'Microsoft 365',
    E3BackupRunListService::workloadLabel(E3BackupRunListService::WORKLOAD_MS365, 'ms365', '', ''),
    'ms365 label'
);
assertEq(
    'DESKTOP-01',
    E3BackupRunListService::workloadLabel(E3BackupRunListService::WORKLOAD_LOCAL_AGENT, 'local_agent', '', 'DESKTOP-01'),
    'agent hostname label'
);
assertEq(
    'My Google Drive',
    E3BackupRunListService::workloadLabel(E3BackupRunListService::WORKLOAD_CLOUD_TO_CLOUD, 'google_drive', 'My Google Drive', ''),
    'c2c display name label'
);

$timeCutoffSql = E3BackupRunListService::buildTimeCutoffWhereClause('COALESCE(r.started_at, r.created_at)');
assertEq(
    true,
    str_contains($timeCutoffSql, "r.finished_at IS NULL AND r.status IN ('queued','starting','running')"),
    'time cutoff includes unfinished active runs'
);
assertEq(
    true,
    str_contains($timeCutoffSql, 'COALESCE(r.started_at, r.created_at) >= ?'),
    'time cutoff still applies window to completed runs'
);
assertEq(
    true,
    str_starts_with($timeCutoffSql, '((') && str_ends_with($timeCutoffSql, '))'),
    'time cutoff is fully parenthesized so AND client_id cannot be bypassed by OR'
);
assertEq(
    '((r.finished_at IS NULL AND r.status IN (\'queued\',\'starting\',\'running\')) OR (COALESCE(r.started_at, r.created_at) >= ?))',
    $timeCutoffSql,
    'time cutoff exact SQL shape'
);
$startedOnlySql = E3BackupRunListService::buildTimeCutoffWhereClause('r.started_at');
assertEq(
    true,
    str_contains($startedOnlySql, 'r.started_at >= ?'),
    'time cutoff uses effectiveStarted expression'
);

echo "e3backup_run_list_service_test: OK\n";
