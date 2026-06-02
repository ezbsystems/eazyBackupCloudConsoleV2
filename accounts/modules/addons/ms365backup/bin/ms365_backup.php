#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * MS365 backup CLI worker.
 *
 * Usage:
 *   php ms365_backup.php test-auth
 *   php ms365_backup.php discover users|sites|teams|inventory
 *   php ms365_backup.php run --run-id=UUID
 *   php ms365_backup.php verify-calendar --user-id=UUID --calendar-id=ID [--json]
 *   php ms365_backup.php check-access users|sites [--limit=25]
 */

require_once __DIR__ . '/bootstrap.php';

use Ms365Backup\BackupOrchestrator;
use Ms365Backup\BackupRunRepository;
use Ms365Backup\CalendarVerifier;
use Ms365Backup\GraphPaginationException;
use Ms365Backup\RunCancelledException;
use Ms365Backup\DiscoveryService;
use Ms365Backup\InventoryService;
use Ms365Backup\GraphClient;
use Ms365Backup\ProgressLogger;
use Ms365Backup\ResourceAccessService;
use Ms365Backup\StorageLayout;
use Ms365Backup\TenantRepository;
use Ms365Backup\TokenProvider;

$args = array_slice($argv, 1);
if ($args === []) {
    ms365_log_line('Usage: php ms365_backup.php <test-auth|discover|run|verify-calendar> [options]');
    exit(1);
}

$command = $args[0];
$runId = null;
$userId = null;
$calendarId = null;
$verifyJson = false;
$checkLimit = 25;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--run-id=')) {
        $runId = substr($arg, 9);
    }
    if (str_starts_with($arg, '--user-id=')) {
        $userId = substr($arg, 10);
    }
    if (str_starts_with($arg, '--calendar-id=')) {
        $calendarId = substr($arg, 14);
    }
    if ($arg === '--json') {
        $verifyJson = true;
    }
    if (str_starts_with($arg, '--limit=')) {
        $checkLimit = max(1, min(50, (int) substr($arg, 8)));
    }
}

function ms365_cli_init_whmcs(): void
{
    $init = dirname(__DIR__, 4) . '/init.php';
    if (!is_file($init)) {
        throw new \RuntimeException('WHMCS init.php not found');
    }
    require_once $init;
    require_once dirname(__DIR__) . '/ms365backup_autoload.php';
}

try {
    ms365_cli_init_whmcs();

    switch ($command) {
        case 'test-auth':
            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
            $graph = new GraphClient($tokens, $creds['region']);
            $org = $graph->get('organization', ['$top' => '1']);
            $name = $org['value'][0]['displayName'] ?? 'unknown';
            ms365_log_line('OK: token acquired for organization: ' . $name);
            exit(0);

        case 'discover':
            $type = $args[1] ?? 'users';
            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
            $graph = new GraphClient($tokens, $creds['region']);
            $storage = new StorageLayout($creds['tenant_id']);
            $discovery = new DiscoveryService($graph, $storage);
            if ($type === 'inventory') {
                $inventory = new InventoryService($graph, $storage, $discovery);
                $data = $inventory->refresh();
                $total = count($data['resources'] ?? []);
                ms365_log_line("OK: inventory refreshed ({$total} resources)");
                exit(0);
            }
            $count = match ($type) {
                'sites' => count($discovery->listSites()),
                'teams' => count($discovery->listTeams()),
                default => count($discovery->listUsers()),
            };
            ms365_log_line("OK: discovered {$count} {$type}");
            exit(0);

        case 'run':
            if ($runId === null || $runId === '') {
                ms365_log_line('Error: --run-id=UUID is required');
                exit(1);
            }
            $lockFile = sys_get_temp_dir() . '/ms365backup_' . preg_replace('/[^a-zA-Z0-9-]/', '', $runId) . '.lock';
            $lock = fopen($lockFile, 'c+');
            if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                ms365_log_line('Error: another worker holds the lock for this run');
                exit(1);
            }

            $run = BackupRunRepository::get($runId);
            if (!$run) {
                ms365_log_line('Error: run not found');
                exit(1);
            }
            if (BackupRunRepository::isCancelled($runId)) {
                ms365_log_line('Run already cancelled');
                exit(0);
            }

            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $logPath = $storage->runDir((string) $run['user_id'], $runId) . '/run.log';
            $logger = new ProgressLogger($runId, $logPath);
            $logger->info('CLI worker started');

            $orchestrator = new BackupOrchestrator($runId, $logger);
            $orchestrator->execute();
            ms365_log_line('OK: backup run completed');
            flock($lock, LOCK_UN);
            fclose($lock);
            exit(0);

        case 'check-access':
            $type = $args[1] ?? 'users';
            if (!in_array($type, ['users', 'sites'], true)) {
                ms365_log_line('Error: type must be users or sites');
                exit(1);
            }
            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
            $graph = new GraphClient($tokens, $creds['region']);
            $storage = new StorageLayout($creds['tenant_id']);
            $access = new ResourceAccessService($graph, $storage);
            $offset = 0;
            $totalUnavailable = 0;
            do {
                $batch = $type === 'sites'
                    ? $access->checkSitesBatch($offset, $checkLimit)
                    : $access->checkUsersBatch($offset, $checkLimit);
                $totalUnavailable += $batch['unavailable_count'];
                $offset = $batch['processed'];
                ms365_log_line("Checked {$batch['processed']}/{$batch['total']} {$type}…");
            } while (!$batch['done']);
            ms365_log_line("OK: access check complete for {$type} ({$totalUnavailable} problematic in last batches)");
            exit(0);

        case 'verify-calendar':
            if ($userId === null || $userId === '' || $calendarId === null || $calendarId === '') {
                ms365_log_line('Error: --user-id= and --calendar-id= are required');
                exit(1);
            }
            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
            $graph = new GraphClient($tokens, $creds['region']);
            $storage = new StorageLayout($creds['tenant_id']);
            $verifier = new CalendarVerifier($graph, $storage);
            $report = $verifier->verify($userId, $calendarId);
            if ($verifyJson) {
                ms365_log_line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                ms365_log_line(CalendarVerifier::formatCliReport($report));
            }
            exit(!empty($report['ok']) ? 0 : 1);

        default:
            ms365_log_line('Unknown command: ' . $command);
            exit(1);
    }
} catch (RunCancelledException $e) {
    ms365_log_line('Run cancelled');
    exit(0);
} catch (GraphPaginationException $e) {
    ms365_log_line('Pagination safety: ' . $e->getMessage());
    exit(1);
} catch (\Throwable $e) {
    ms365_log_line('Error: ' . $e->getMessage());
    if ($runId !== null) {
        try {
            ms365_cli_init_whmcs();
            if (!BackupRunRepository::isCancelled($runId)) {
                BackupRunRepository::update($runId, [
                    'status' => 'error',
                    'error_message' => substr($e->getMessage(), 0, 65000),
                    'finished_at' => time(),
                ]);
            }
        } catch (\Throwable $_) {
        }
    }
    exit(1);
}
