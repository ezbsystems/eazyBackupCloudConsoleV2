<?php

// Usage: php -q accounts/crons/s3cloudbackup_events_prune.php

require_once __DIR__ . '/../../init.php';

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function cb_prune_setting(string $name, int $default): int
{
    $row = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', $name)
        ->first(['value']);
    if ($row && isset($row->value) && is_numeric($row->value)) {
        return max(1, (int) $row->value);
    }
    return $default;
}

function cb_prune_table(string $table, string $tsColumn, string $cutoff, int $batch = 5000): int
{
    if (!Capsule::schema()->hasTable($table)) {
        return 0;
    }
    $totalDeleted = 0;
    do {
        $deleted = Capsule::table($table)
            ->where($tsColumn, '<', $cutoff)
            ->limit($batch)
            ->delete();
        $totalDeleted += $deleted;
    } while ($deleted === $batch);
    return $totalDeleted;
}

function cb_cutoff(int $days): string
{
    return (new DateTime('now', new DateTimeZone('UTC')))
        ->modify('-' . $days . ' days')
        ->format('Y-m-d H:i:s');
}

try {
    // Run events (existing behavior)
    $eventDays = cb_prune_setting('cloudbackup_event_retention_days', 60);
    $n1 = cb_prune_table('s3_cloudbackup_run_events', 'ts', cb_cutoff($eventDays));
    echo "[OK] Pruned {$n1} run_events older than {$eventDays} days.\n";

    // Run logs (new)
    $logDays = cb_prune_setting('cloudbackup_run_logs_retention_days', $eventDays);
    $n2 = cb_prune_table('s3_cloudbackup_run_logs', 'ts', cb_cutoff($logDays));
    echo "[OK] Pruned {$n2} run_logs older than {$logDays} days.\n";

    // Agent/tray health events (new)
    $agentEventsDays = cb_prune_setting('cloudbackup_agent_events_retention_days', 30);
    $n3 = cb_prune_table('s3_cloudbackup_agent_events', 'ts', cb_cutoff($agentEventsDays));
    echo "[OK] Pruned {$n3} agent_events older than {$agentEventsDays} days.\n";

    // Admin verbose log chunks (new)
    $chunkDays = cb_prune_setting('cloudbackup_admin_chunks_retention_days', 14);
    $n4 = cb_prune_table('s3_cloudbackup_admin_log_chunks', 'last_ts', cb_cutoff($chunkDays));
    echo "[OK] Pruned {$n4} admin_log_chunks older than {$chunkDays} days.\n";
} catch (\Exception $e) {
    echo "[ERROR] Prune failed: " . $e->getMessage() . "\n";
    exit(1);
}
