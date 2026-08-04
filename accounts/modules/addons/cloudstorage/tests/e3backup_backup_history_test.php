<?php

declare(strict_types=1);

/**
 * Regression: MS365 synthetic row in Recent Backup History grid model.
 *
 * Run: php accounts/modules/addons/cloudstorage/tests/e3backup_backup_history_test.php
 */

require_once __DIR__ . '/../lib/Client/E3BackupHistoryService.php';

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupHistoryService;

$failures = 0;

function history_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

history_assert(
    E3BackupHistoryService::MS365_FILTER_INCLUDE === E3BackupHistoryService::ms365FilterMode(''),
    'empty agent filter includes MS365 row'
);
history_assert(
    E3BackupHistoryService::MS365_FILTER_ONLY === E3BackupHistoryService::ms365FilterMode('__ms365__'),
    'synthetic uuid filter returns MS365 only'
);
history_assert(
    E3BackupHistoryService::MS365_FILTER_SKIP === E3BackupHistoryService::ms365FilterMode('bbf034af-ffe9-473d-916a-ad4350ef892b'),
    'real agent uuid filter skips MS365 row'
);

$ms365 = E3BackupHistoryService::newMs365AgentEntry();
history_assert(
    E3BackupHistoryService::MS365_SYNTHETIC_AGENT_UUID === $ms365['agent_uuid'],
    'MS365 entry uses synthetic agent_uuid'
);
history_assert(
    E3BackupHistoryService::MS365_DISPLAY_NAME === $ms365['hostname'],
    'MS365 entry hostname is Microsoft 365'
);
history_assert(
    true === $ms365['is_online'],
    'MS365 entry is_online so Online only filter keeps it visible'
);

$dayKey = date('Y-m-d', strtotime('2026-08-01 14:30:00'));
$cutoff24h = strtotime('2026-07-31 12:00:00');
$runId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
$jobId = 'job-uuid-ms365-0001';

E3BackupHistoryService::applyRunToAgent(
    $ms365,
    $runId,
    $jobId,
    'Contoso M365 Backup',
    'success',
    '2026-08-01 14:30:00',
    1048576,
    $cutoff24h,
    static fn (int $bytes): string => $bytes . ' B'
);

$dayList = ['2026-07-31', '2026-08-01', '2026-08-02'];
$out = E3BackupHistoryService::assembleAgentOutput($ms365, $dayList);

history_assert(
    E3BackupHistoryService::MS365_SYNTHETIC_AGENT_UUID === $out['agent_uuid'],
    'assembled output preserves synthetic agent_uuid'
);

$aug1 = null;
foreach ($out['days'] as $cell) {
    if ($cell['date'] === '2026-08-01') {
        $aug1 = $cell;
        break;
    }
}
history_assert($aug1 !== null, 'day cell exists for run date');
history_assert(
    $aug1 !== null && $aug1['status'] === 'success',
    'day cell status reflects parent run'
);
history_assert(
    $aug1 !== null && $aug1['count'] === 1,
    'day cell count is 1'
);
history_assert(
    $aug1 !== null
    && isset($aug1['runs'][0])
    && $aug1['runs'][0]['run_id'] === $runId,
    'day cell includes parent run_id'
);
history_assert(
    count($out['jobs']) === 1 && $out['jobs'][0]['job_id'] === $jobId,
    'per-job drill-down includes MS365 job'
);

history_assert(
    E3BackupHistoryService::worseStatus('success', 'failed') === 'failed',
    'worseStatus prefers failed over success'
);
history_assert(
    E3BackupHistoryService::worseStatus('partial_success', 'warning') === 'partial_success',
    'worseStatus prefers partial_success over warning'
);

exit($failures > 0 ? 1 : 0);
