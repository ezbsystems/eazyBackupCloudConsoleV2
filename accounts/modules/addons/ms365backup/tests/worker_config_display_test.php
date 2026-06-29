<?php
declare(strict_types=1);

/**
 * Guard WorkerConfigService::effectiveNodeConfigDisplay semantics.
 */
require_once __DIR__ . '/../lib/Ms365Backup/Fleet/WorkerConfigService.php';

use Ms365Backup\Fleet\WorkerConfigService;

function assertDisplay(array $node, int $latest, string $wantStatus): void
{
    $got = WorkerConfigService::effectiveNodeConfigDisplay($node, $latest);
    $status = (string) ($got['config_effective_status'] ?? '');
    if ($status !== $wantStatus) {
        fwrite(STDERR, 'expected ' . $wantStatus . ', got ' . $status . ' for ' . json_encode($node) . PHP_EOL);
        exit(1);
    }
}

assertDisplay(['config_version' => 8, 'config_status' => 'current'], 8, 'current');
assertDisplay(['config_version' => 3, 'config_status' => 'current'], 8, 'outdated');
assertDisplay(['config_version' => 0, 'config_status' => 'current'], 8, 'outdated');
assertDisplay(['config_version' => 3, 'target_config_version' => 8, 'config_status' => 'pending'], 8, 'pending');
assertDisplay(['config_version' => 3, 'target_config_version' => 8, 'config_status' => 'applying'], 8, 'applying');
assertDisplay(['config_version' => 8, 'target_config_version' => 8, 'config_status' => 'current'], 8, 'current');
assertDisplay(['config_version' => 3, 'config_status' => 'failed'], 8, 'failed');
assertDisplay(['config_version' => 8, 'config_status' => 'current'], 0, 'current');

echo "worker_config_display_test: ok\n";
