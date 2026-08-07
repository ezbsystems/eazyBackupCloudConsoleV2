<?php
declare(strict_types=1);

/**
 * One-shot: disable SharePoint Lists on all MS365 jobs and cancel active list-scoped runs.
 *
 * Usage (from accounts/):
 *   php modules/addons/ms365backup/bin/ms365_disable_sharepoint_lists.php --dry-run
 *   php modules/addons/ms365backup/bin/ms365_disable_sharepoint_lists.php --apply
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\BackupRunRepository;
use Ms365Backup\BackupScope;
use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\JobQueueRepository;
use Ms365Backup\TenantResource;
use WHMCS\Database\Capsule;

$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply;

if ($dryRun && !in_array('--dry-run', $argv ?? [], true)) {
    fwrite(STDERR, "Specify --dry-run or --apply\n");
    exit(1);
}

echo '=== SharePoint Lists fleet migration ===' . PHP_EOL;
echo $dryRun ? "Mode: dry-run\n\n" : "Mode: apply\n\n";

/**
 * @param array<string, array<string, bool>> $scopeOverrides
 * @param list<string> $selectedIds
 * @return array{changed: bool, overrides: array<string, array<string, bool>>}
 */
function rewriteScopeOverrides(array $scopeOverrides, array $selectedIds): array
{
    $changed = false;
    $out = $scopeOverrides;

    $siteIds = [];
    foreach (array_keys($out) as $id) {
        if (str_starts_with((string) $id, 'site:')) {
            $siteIds[(string) $id] = true;
        }
    }
    foreach ($selectedIds as $id) {
        if (str_starts_with((string) $id, 'site:')) {
            $siteIds[(string) $id] = true;
        }
    }

    foreach (array_keys($siteIds) as $siteId) {
        $flags = $out[$siteId] ?? BackupScope::forResourceType(TenantResource::TYPE_SHAREPOINT_SITE)->toArray();
        $files = array_key_exists(BackupScope::FILES, $flags)
            ? (bool) $flags[BackupScope::FILES]
            : true;
        if (($flags[BackupScope::LISTS] ?? false) !== false || !array_key_exists(BackupScope::LISTS, $flags)) {
            $changed = true;
        }
        $out[$siteId] = [
            BackupScope::FILES => $files,
            BackupScope::LISTS => false,
        ];
    }

    return ['changed' => $changed, 'overrides' => CustomerSelectionCodec::normalizeScopeOverrides($out)];
}

/**
 * @param array<string, mixed> $scope
 */
function isListsOnlySiteRun(string $physicalKey, array $scope): bool
{
    if (!str_starts_with($physicalKey, 'site:')) {
        return false;
    }
    $files = array_key_exists('files', $scope) ? (bool) $scope['files'] : true;
    $lists = array_key_exists('lists', $scope) ? (bool) $scope['lists'] : false;

    return $lists && !$files;
}

/** @return array<string, mixed> */
function decodeSourceConfig(mixed $enc): array
{
    if (!is_string($enc) || $enc === '') {
        return [];
    }
    try {
        $plain = decrypt($enc);
    } catch (\Throwable $_) {
        return [];
    }
    $decoded = json_decode($plain, true);

    return is_array($decoded) ? $decoded : [];
}

/** @return array<string, mixed> */
function decodeScheduleJson(mixed $raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

$jobsScanned = 0;
$jobsChanged = 0;
$overridesFlipped = 0;

$jobs = Capsule::table('s3_cloudbackup_jobs')
    ->where('source_type', 'ms365')
    ->where('status', '!=', 'deleted')
    ->get();

foreach ($jobs as $job) {
    ++$jobsScanned;
    $schedule = decodeScheduleJson($job->schedule_json ?? null);
    $config = decodeSourceConfig($job->source_config_enc ?? '');
    $selectedIds = CustomerSelectionCodec::normalizeIds(
        $config['selected_resource_ids'] ?? ($schedule['selected_resource_ids'] ?? []),
    );
    $scopeOverrides = CustomerSelectionCodec::normalizeScopeOverrides(
        $config['scope_overrides'] ?? ($schedule['scope_overrides'] ?? []),
    );

    $rewrite = rewriteScopeOverrides($scopeOverrides, $selectedIds);
    if (!$rewrite['changed']) {
        continue;
    }

    ++$jobsChanged;
    $overridesFlipped += count(array_filter(
        array_keys($rewrite['overrides']),
        static fn (string $id): bool => str_starts_with($id, 'site:'),
    ));

    $jobId = (string) ($job->job_id ?? '');
    echo sprintf(
        "job %s client=%d backup_user=%d sites_updated\n",
        $jobId,
        (int) ($job->client_id ?? 0),
        (int) ($job->backup_user_id ?? 0),
    );

    if ($dryRun) {
        continue;
    }

    $newOverrides = $rewrite['overrides'];
    $schedule['scope_overrides'] = $newOverrides;
    $schedule['selected_resource_ids'] = $selectedIds;
    $config['scope_overrides'] = $newOverrides;
    $config['selected_resource_ids'] = $selectedIds;

    $update = [
        'schedule_json' => json_encode($schedule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        'source_config_enc' => encrypt(json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'),
    ];
    Capsule::table('s3_cloudbackup_jobs')
        ->where('job_id', $job->job_id)
        ->update($update);
}

$cancelMessage = 'SharePoint Lists disabled by platform policy';
$runsCancelled = 0;
$cancelIds = [];

if (Capsule::schema()->hasTable('ms365_backup_runs')) {
    $activeRuns = Capsule::table('ms365_backup_runs')
        ->whereIn('status', ['queued', 'running'])
        ->get(['id', 'physical_key', 'scope_json']);

    foreach ($activeRuns as $run) {
        $physical = (string) ($run->physical_key ?? '');
        $scopeRaw = $run->scope_json ?? '{}';
        $scope = is_string($scopeRaw) ? json_decode($scopeRaw, true) : [];
        if (!is_array($scope)) {
            $scope = [];
        }

        $shouldCancel = false;
        if (str_starts_with($physical, 'list:')) {
            $shouldCancel = true;
        } elseif (isListsOnlySiteRun($physical, $scope)) {
            $shouldCancel = true;
        }

        if (!$shouldCancel) {
            continue;
        }

        $runId = (string) ($run->id ?? '');
        if ($runId === '') {
            continue;
        }
        $cancelIds[] = $runId;
        echo sprintf("cancel run %s physical=%s\n", $runId, $physical);

        if ($dryRun) {
            continue;
        }

        BackupRunRepository::update($runId, [
            'status' => 'cancelled',
            'phase' => 'cancelled',
            'error_message' => $cancelMessage,
            'finished_at' => time(),
        ]);
        JobQueueRepository::markCancelled($runId, $cancelMessage);
        ++$runsCancelled;
    }
}

echo PHP_EOL;
echo "jobs_scanned={$jobsScanned} jobs_changed={$jobsChanged} site_overrides_flipped={$overridesFlipped}" . PHP_EOL;
echo 'runs_to_cancel=' . count($cancelIds) . ' runs_cancelled=' . $runsCancelled . PHP_EOL;
echo $dryRun ? "Dry-run complete. Re-run with --apply to persist.\n" : "Migration applied.\n";
