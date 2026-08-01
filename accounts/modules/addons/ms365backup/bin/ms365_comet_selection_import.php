#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Import Comet MS365 Protected Item selection into a new e3 Users backup job.
 *
 * Dry-run by default. Never updates existing jobs (--apply creates only).
 *
 * Usage:
 *   php ms365_comet_selection_import.php \
 *     --comet-profile=/path/profile.json \
 *     --whmcs-userid=2269 \
 *     --service-id=5471 \
 *     --backup-user-id=E0B22D704ECE1A42C08E0AD2C6 \
 *     [--schedule-frequency=once_daily] \
 *     [--timezone=America/Edmonton] \
 *     [--job-name='M365 (imported from Comet)'] \
 *     [--max-unmatched-pct=25] \
 *     [--out-selection=/tmp/selection.json] \
 *     [--apply] \
 *     [--json]
 *
 * Docs: accounts/modules/addons/ms365backup/Docs/COMET_SELECTION_IMPORT.md
 */

require_once __DIR__ . '/bootstrap.php';

use Ms365Backup\Comet\CometSelectionImportService;
use Ms365Backup\Ms365ScheduleAssigner;

function ms365_comet_import_usage(): void
{
    ms365_log_line('Usage: php ms365_comet_selection_import.php --comet-profile=FILE --whmcs-userid=N --service-id=N --backup-user-id=PUBLIC_ID_OR_ID [--apply] [--json]');
    ms365_log_line('See Docs/COMET_SELECTION_IMPORT.md');
}

function ms365_comet_import_init_whmcs(): void
{
    $init = dirname(__DIR__, 4) . '/init.php';
    if (!is_file($init)) {
        throw new \RuntimeException('WHMCS init.php not found at ' . $init);
    }
    require_once $init;
    require_once dirname(__DIR__) . '/ms365backup_autoload.php';
}

$args = array_slice($argv, 1);
if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true)) {
    ms365_comet_import_usage();
    exit($args === [] ? 1 : 0);
}

$profilePath = '';
$clientId = 0;
$serviceId = 0;
$backupUserRef = '';
$scheduleFrequency = Ms365ScheduleAssigner::FREQUENCY_ONCE_DAILY;
$timezone = null;
$jobName = '';
$maxUnmatchedPct = 25.0;
$outSelection = null;
$apply = false;
$asJson = false;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--comet-profile=')) {
        $profilePath = substr($arg, strlen('--comet-profile='));
    } elseif (str_starts_with($arg, '--whmcs-userid=')) {
        $clientId = (int) substr($arg, strlen('--whmcs-userid='));
    } elseif (str_starts_with($arg, '--service-id=')) {
        $serviceId = (int) substr($arg, strlen('--service-id='));
    } elseif (str_starts_with($arg, '--backup-user-id=')) {
        $backupUserRef = substr($arg, strlen('--backup-user-id='));
    } elseif (str_starts_with($arg, '--schedule-frequency=')) {
        $scheduleFrequency = substr($arg, strlen('--schedule-frequency='));
    } elseif (str_starts_with($arg, '--timezone=')) {
        $timezone = substr($arg, strlen('--timezone='));
    } elseif (str_starts_with($arg, '--job-name=')) {
        $jobName = substr($arg, strlen('--job-name='));
    } elseif (str_starts_with($arg, '--max-unmatched-pct=')) {
        $maxUnmatchedPct = (float) substr($arg, strlen('--max-unmatched-pct='));
    } elseif (str_starts_with($arg, '--out-selection=')) {
        $outSelection = substr($arg, strlen('--out-selection='));
    } elseif ($arg === '--apply') {
        $apply = true;
    } elseif ($arg === '--json') {
        $asJson = true;
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        ms365_comet_import_usage();
        exit(1);
    }
}

try {
    ms365_comet_import_init_whmcs();

    $result = CometSelectionImportService::run([
        'profile_path' => $profilePath,
        'client_id' => $clientId,
        'service_id' => $serviceId,
        'backup_user_ref' => $backupUserRef,
        'schedule_frequency' => $scheduleFrequency,
        'timezone' => $timezone,
        'job_name' => $jobName,
        'max_unmatched_pct' => $maxUnmatchedPct,
        'apply' => $apply,
        'out_selection' => $outSelection,
    ]);

    if ($asJson) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    $mode = ($result['dry_run'] ?? true) ? 'DRY-RUN' : 'APPLY';
    ms365_log_line("[{$mode}] backup_user_id={$result['backup_user_id']} public_id={$result['backup_user_public_id']} username={$result['backup_username']}");
    ms365_log_line("Comet source={$result['source_guid']} ({$result['source_description']})");
    $report = is_array($result['report'] ?? null) ? $result['report'] : [];
    ms365_log_line(sprintf(
        'Matched users=%d sites=%d teams=%d groups=%d | personal_sites→users=%d | selected=%d | unmatched BackupOptions=%d/%d (%.2f%%)',
        (int) ($report['matched_users'] ?? 0),
        (int) ($report['matched_sites'] ?? 0),
        (int) ($report['matched_teams'] ?? 0),
        (int) ($report['matched_groups'] ?? 0),
        (int) ($report['personal_sites_mapped_to_users'] ?? 0),
        (int) ($result['selected_count'] ?? 0),
        count($report['unmatched_backup_option_keys'] ?? []),
        (int) ($report['backup_options_total'] ?? 0),
        (float) ($result['unmatched_pct'] ?? 0)
    ));
    foreach (['unmatched_backup_option_keys', 'unmatched_member_roots', 'missing_onedrive_children', 'personal_site_owner_unresolved'] as $key) {
        $list = $report[$key] ?? [];
        if (!is_array($list) || $list === []) {
            continue;
        }
        ms365_log_line($key . ' (' . count($list) . '):');
        foreach (array_slice($list, 0, 40) as $item) {
            ms365_log_line('  - ' . $item);
        }
        if (count($list) > 40) {
            ms365_log_line('  … +' . (count($list) - 40) . ' more');
        }
    }
    if (!empty($result['job_id'])) {
        ms365_log_line('Created job_id=' . $result['job_id']);
    } else {
        ms365_log_line('No job created (dry-run). Re-run with --apply to create a new job.');
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
