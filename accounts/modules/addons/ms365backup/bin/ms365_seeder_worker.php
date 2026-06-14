#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * MS365 tenant seeder CLI worker.
 *
 * Usage: php ms365_seeder_worker.php --run-id=UUID
 */

require_once __DIR__ . '/bootstrap.php';

use Ms365Backup\Seeder\SeederProgressWriter;
use Ms365Backup\Seeder\SeederRunRepository;
use Ms365Backup\Seeder\TenantSeederOrchestrator;

$runId = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--run-id=')) {
        $runId = substr($arg, 9);
    }
}

if ($runId === null || $runId === '') {
    fwrite(STDERR, "Usage: php ms365_seeder_worker.php --run-id=UUID\n");
    exit(1);
}

function ms365_seeder_cli_init_whmcs(): void
{
    $init = dirname(__DIR__, 4) . '/init.php';
    if (!is_file($init)) {
        throw new \RuntimeException('WHMCS init.php not found');
    }
    require_once $init;
    require_once dirname(__DIR__) . '/ms365backup_autoload.php';
}

try {
    ms365_seeder_cli_init_whmcs();

    $run = SeederRunRepository::get($runId);
    if (!$run) {
        throw new \RuntimeException('Seeder run not found: ' . $runId);
    }

    $options = json_decode((string) ($run['options_json'] ?? '{}'), true);
    if (!is_array($options)) {
        $options = [];
    }

    $progress = new SeederProgressWriter($runId);
    $progress->log('Seeder worker started');

    $orchestrator = new TenantSeederOrchestrator($runId, $progress);
    $orchestrator->run($options);

    exit(0);
} catch (\Throwable $e) {
    if (isset($runId)) {
        try {
            ms365_seeder_cli_init_whmcs();
            SeederRunRepository::markError($runId, $e->getMessage());
            $progress = new SeederProgressWriter($runId);
            $progress->write([
                'status' => 'error',
                'phase' => 'error',
                'message' => $e->getMessage(),
            ]);
            $progress->log('Fatal: ' . $e->getMessage());
        } catch (\Throwable $_) {
        }
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
