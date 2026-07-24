<?php

declare(strict_types=1);

/**
 * Regression: last_run ROW_NUMBER ordering prefers unfinished active runs
 * over newer terminal schedule-skip rows.
 *
 * Run: php accounts/modules/addons/cloudstorage/tests/e3backup_job_list_last_run_test.php
 */

require_once __DIR__ . '/../lib/Client/E3BackupJobListHelper.php';

$failures = 0;

function last_run_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

/**
 * Mirror ROW_NUMBER() OVER (... ORDER BY ...) priority in PHP for fixture runs.
 *
 * @param list<array{run_id: string, status: string, started_at: string, finished_at: ?string, schedule_skipped?: bool}> $runs
 */
function pick_last_run_for_job(array $runs, bool $uuidPk = true): ?array
{
    if ($runs === []) {
        return null;
    }
    $orderClause = e3backup_last_run_row_number_order_clause($uuidPk);
    last_run_assert(
        str_contains($orderClause, "CASE WHEN finished_at IS NULL AND status IN ('queued','starting','running') THEN 0 ELSE 1 END"),
        'order clause prefers unfinished active runs'
    );

    usort($runs, static function (array $a, array $b): int {
        $aActive = ($a['finished_at'] === null && in_array($a['status'], ['queued', 'starting', 'running'], true)) ? 0 : 1;
        $bActive = ($b['finished_at'] === null && in_array($b['status'], ['queued', 'starting', 'running'], true)) ? 0 : 1;
        if ($aActive !== $bActive) {
            return $aActive <=> $bActive;
        }
        $startedCmp = strcmp($b['started_at'], $a['started_at']);
        if ($startedCmp !== 0) {
            return $startedCmp;
        }

        return strcmp($b['run_id'], $a['run_id']);
    });

    return $runs[0];
}

$activeParent = [
    'run_id' => 'bbf034af-ffe9-473d-916a-ad4350ef892b',
    'status' => 'running',
    'started_at' => '2026-07-22 19:09:00',
    'finished_at' => null,
];
$newerSkip = [
    'run_id' => 'cb7b0dd5-0000-4000-8000-000000000001',
    'status' => 'success',
    'started_at' => '2026-07-23 19:37:00',
    'finished_at' => '2026-07-23 19:37:05',
    'schedule_skipped' => true,
];

$picked = pick_last_run_for_job([$newerSkip, $activeParent]);
last_run_assert(
    $picked !== null && $picked['run_id'] === $activeParent['run_id'],
    'active parent wins over newer schedule-skip last_run'
);

$pickedLegacy = pick_last_run_for_job([$newerSkip, $activeParent], false);
last_run_assert(
    $pickedLegacy !== null && $pickedLegacy['run_id'] === $activeParent['run_id'],
    'legacy PK order clause keeps same active-run preference'
);

last_run_assert(
    str_contains(e3backup_last_run_row_number_order_clause(true), 'run_id DESC'),
    'uuid PK path orders by run_id DESC'
);
last_run_assert(
    str_contains(e3backup_last_run_row_number_order_clause(false), 'id DESC'),
    'legacy PK path orders by id DESC'
);

exit($failures > 0 ? 1 : 0);
