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

echo "e3backup_run_list_service_test: OK\n";
