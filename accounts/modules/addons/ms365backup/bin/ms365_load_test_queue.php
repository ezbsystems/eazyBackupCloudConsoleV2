#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Staging load test: enqueue N synthetic ms365_job_queue rows (dry-run by default).
 *
 * Usage: php ms365_load_test_queue.php --enqueue=100 [--dry-run]
 */

require_once __DIR__ . '/bootstrap.php';

use Ms365Backup\JobQueueRepository;

$init = dirname(__DIR__, 3) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;

$enqueue = 50;
$dryRun = true;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--enqueue=')) {
        $enqueue = max(1, (int) substr($arg, 10));
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
    }
    if ($arg === '--execute') {
        $dryRun = false;
    }
}

echo "Load test: enqueue={$enqueue} dry_run=" . ($dryRun ? 'yes' : 'no') . PHP_EOL;
if ($dryRun) {
    echo "Pass --execute to insert synthetic queue rows in staging only." . PHP_EOL;
    exit(0);
}

$created = 0;
for ($i = 0; $i < $enqueue; $i++) {
    $runId = sprintf('00000000-0000-4000-8000-%012d', $i);
    try {
        JobQueueRepository::enqueue($runId, 200);
        $created++;
    } catch (\Throwable $_) {
    }
}
echo "Enqueued {$created} synthetic rows." . PHP_EOL;
