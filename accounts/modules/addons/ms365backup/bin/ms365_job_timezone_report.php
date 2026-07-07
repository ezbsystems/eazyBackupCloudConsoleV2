<?php
declare(strict_types=1);

/**
 * Report MS365 job timezones (support / migration visibility).
 *
 * Run: php accounts/modules/addons/ms365backup/bin/ms365_job_timezone_report.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use WHMCS\Database\Capsule;

if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
    fwrite(STDERR, "s3_cloudbackup_jobs not found\n");
    exit(1);
}

$rows = Capsule::table('s3_cloudbackup_jobs')
    ->where('source_type', 'ms365')
    ->where('status', '!=', 'deleted')
    ->orderBy('client_id')
    ->orderBy('name')
    ->get(['client_id', 'name', 'timezone', 'schedule_time', 'status']);

$byTz = [];
foreach ($rows as $row) {
    $tz = trim((string) ($row->timezone ?? ''));
    if ($tz === '') {
        $tz = '(empty)';
    }
    $byTz[$tz] = ($byTz[$tz] ?? 0) + 1;
}

echo "MS365 job timezone report\n";
echo str_repeat('-', 40) . "\n";
foreach ($byTz as $tz => $count) {
    echo sprintf("%-28s %5d\n", $tz, $count);
}
echo str_repeat('-', 40) . "\n";
echo 'Total jobs: ' . count($rows) . "\n\n";

foreach ($rows as $row) {
    echo sprintf(
        "client=%d  %-36s  tz=%-24s  schedule=%s  status=%s\n",
        (int) $row->client_id,
        substr((string) $row->name, 0, 36),
        (string) ($row->timezone ?? ''),
        (string) ($row->schedule_time ?? ''),
        (string) ($row->status ?? ''),
    );
}
