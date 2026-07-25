<?php
declare(strict_types=1);

use Ms365Backup\Ms365AdminJobsService;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$runId = strtolower(trim((string) ($_GET['run_id'] ?? '')));
if ($runId === '') {
    echo '<div class="alert alert-danger">run_id is required.</div>';
    return;
}

try {
    $parent = Ms365AdminJobsService::requireMs365ParentRun($runId);
} catch (\Throwable $ex) {
    echo '<div class="alert alert-danger">' . $e($ex->getMessage()) . '</div>';
    return;
}

$clientId = (int) ($parent['client_id'] ?? 0);
$clientLabel = 'Client #' . $clientId;
if ($clientId > 0) {
    $clientRow = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->first(['firstname', 'lastname', 'companyname', 'email']);
    if ($clientRow !== null) {
        $row = (array) $clientRow;
        $company = trim((string) ($row['companyname'] ?? ''));
        if ($company !== '') {
            $clientLabel = $company;
        } else {
            $name = trim(((string) ($row['firstname'] ?? '')) . ' ' . ((string) ($row['lastname'] ?? '')));
            if ($name !== '') {
                $clientLabel = $name;
            } elseif (!empty($row['email'])) {
                $clientLabel = (string) $row['email'];
            }
        }
    }
}

$jobName = (string) ($parent['job_name'] ?? 'Unnamed job');
$status = strtolower((string) ($parent['status'] ?? ''));
$isRunningStatus = in_array($status, ['running', 'starting', 'queued'], true);

cloudstorage_load_ms365backup();
$initialWorkloads = [];
$progressPct = (float) ($parent['progress_pct'] ?? 0);
$stage = (string) ($parent['stage'] ?? ucfirst($status !== '' ? $status : 'unknown'));

try {
    $progress = Ms365BatchLiveService::aggregateProgress($runId, $clientId, $parent);
    $initialWorkloads = Ms365BatchLiveService::listWorkloadsForCustomer($runId, $clientId);
    if (isset($progress['progress_pct'])) {
        $progressPct = (float) $progress['progress_pct'];
    }
    if (!empty($progress['stage'])) {
        $stage = (string) $progress['stage'];
    }
    if (!empty($progress['status'])) {
        $status = strtolower((string) $progress['status']);
        $isRunningStatus = in_array($status, ['running', 'starting', 'queued'], true);
    }
} catch (\Throwable $_) {
    // Best-effort initial render.
}

$startedAt = (string) ($parent['started_at'] ?? '');
$finishedAt = (string) ($parent['finished_at'] ?? '');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
$tokensPath = dirname(__DIR__, 3) . '/eazybackup/templates/partials/_ui-tokens.tpl';
$tailwindCss = '../templates/eazyBackup/css/tailwind.css';
$liveJsPath = dirname(__DIR__, 2) . '/assets/js/ms365-admin-live.js';
?>
<?php if (is_file($tokensPath)): ?>
<?php include $tokensPath; ?>
<?php endif; ?>
<link rel="stylesheet" href="<?= $e($tailwindCss) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 5) . '/templates/eazyBackup/css/tailwind.css') ?>">

<div class="ms365-admin-live-wrap" style="margin-top:12px">
    <div class="clearfix" style="margin-bottom:12px">
        <div class="pull-left">
            <h3 style="margin:0 0 4px">
                <?= $e($jobName) ?>
                <span id="liveHeaderBadge" class="eb-badge eb-badge--info eb-badge--dot"><?= $e(ucfirst($status)) ?></span>
            </h3>
            <p class="text-muted" style="margin:0">
                Client: <strong><?= $e($clientLabel) ?></strong>
                &nbsp;·&nbsp; Run: <code><?= $e($runId) ?></code>
            </p>
        </div>
        <div class="pull-right" style="margin-top:4px">
            <a href="addonmodules.php?module=ms365backup&action=jobs" class="btn btn-default btn-sm">← Back to Jobs</a>
            <button
                id="cancelButton"
                type="button"
                class="eb-btn eb-btn-danger eb-btn-sm<?= $isRunningStatus ? '' : ' hidden' ?>"
                style="<?= $isRunningStatus ? '' : 'display:none' ?>"
            >Cancel Run</button>
        </div>
    </div>

    <div class="eb-live-page">
        <div id="errorSummaryContainer" class="eb-live-alert eb-live-alert--danger hidden" role="status" aria-live="polite">
            <p class="eb-live-alert-title">Error</p>
            <p id="errorSummaryText" class="eb-live-alert-copy"></p>
        </div>

        <section class="eb-live-progress" id="liveProgressStrip">
            <div class="eb-live-progress-top">
                <div class="eb-live-percent" aria-live="polite">
                    <span id="progressPercentValue"><?= $e(number_format($progressPct, 2)) ?></span><span class="unit">%</span>
                </div>
                <div class="eb-live-stage">
                    <span id="stageStatusDot" class="eb-status-dot eb-status-dot--pending"></span>
                    <span id="stageLabel" style="color: var(--eb-info-text); font-weight: 600;"><?= $e($stage) ?></span>
                    <span id="stageEta" class="eb-live-stage-eta"></span>
                </div>
            </div>
            <div class="eb-live-bar" aria-hidden="true">
                <div
                    class="eb-live-bar-fill running"
                    id="progressBar"
                    style="width: <?= $e((string) max(0, min(100, $progressPct))) ?>%"
                    role="progressbar"
                    aria-label="Backup progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?= $e(number_format($progressPct, 2)) ?>"
                ></div>
            </div>

            <div class="eb-live-stats eb-live-stats--split">
                <div class="eb-live-stats-row">
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label">Workloads</div>
                        <div class="eb-live-stat-value highlight" id="ms365WorkloadsValue">—</div>
                    </div>
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label">Running Workloads</div>
                        <div class="eb-live-stat-value highlight" id="ms365RunningWorkloadsValue">—</div>
                    </div>
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label" id="speedStatLabel">Speed</div>
                        <div class="eb-live-stat-value highlight" id="speedValue">—</div>
                        <p id="speedHint" class="eb-live-stat-hint"></p>
                    </div>
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label">Items/s</div>
                        <div class="eb-live-stat-value highlight" id="itemsSpeedValue">—</div>
                        <p id="itemsSpeedHint" class="eb-live-stat-hint">Enumeration rate</p>
                    </div>
                    <div id="ms365GraphActivityStat" class="eb-live-stat hidden">
                        <div class="eb-live-stat-label">Graph requests</div>
                        <div class="eb-live-stat-value highlight" id="graphRequestsValue">—</div>
                        <p id="graphRequestsHint" class="eb-live-stat-hint">Enumeration activity</p>
                    </div>
                </div>
                <div class="eb-live-stats-row">
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label">Processed</div>
                        <div class="eb-live-stat-value" id="bytesProcessedValue">0.00 Bytes</div>
                    </div>
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label">Uploaded</div>
                        <div class="eb-live-stat-value" id="bytesTransferredValue">0.00 Bytes</div>
                        <p id="uploadedSavings" class="eb-live-stat-hint"></p>
                    </div>
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label">Files</div>
                        <div class="eb-live-stat-value" id="filesValue">-</div>
                        <p id="filesHint" class="eb-live-stat-hint">enumerated so far</p>
                    </div>
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label">Folders</div>
                        <div class="eb-live-stat-value" id="foldersValue">—</div>
                    </div>
                    <div class="eb-live-stat">
                        <div class="eb-live-stat-label" id="durationStatLabel"><?= $isRunningStatus ? 'Elapsed' : 'Duration' ?></div>
                        <div class="eb-live-stat-value mono" id="durationValue">—</div>
                    </div>
                </div>
                <div id="graphThrottleHint" class="eb-live-stat eb-live-stat--full hidden">
                    <p class="eb-live-alert-copy" style="color: var(--eb-warning-text); margin: 0;">
                        Microsoft Graph rate limiting detected<span id="graphThrottleCount"></span>. Backup continues with automatic pacing.
                    </p>
                </div>
            </div>

            <div class="eb-live-current-file<?= $isRunningStatus ? '' : ' hidden' ?>" id="currentFileRow">
                <span class="eb-live-current-file-label">Current item</span>
                <span id="currentItem" class="eb-live-current-file-value">—</span>
            </div>
        </section>

        <div class="eb-live-details" id="liveDetailsStrip">
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Source</div>
                <div class="eb-live-detail-value" id="detailsAgent">Microsoft 365</div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Job</div>
                <div class="eb-live-detail-value min-w-0 max-w-full overflow-hidden text-ellipsis whitespace-nowrap" id="detailsJob"><?= $e($jobName) ?></div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Client</div>
                <div class="eb-live-detail-value" id="detailsClient"><?= $e($clientLabel) ?></div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Started</div>
                <div class="eb-live-detail-value" id="detailsStartedAt"><?= $startedAt !== '' ? $e($startedAt) : '—' ?></div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Finished</div>
                <div class="eb-live-detail-value" id="detailsFinishedAt"><?= $finishedAt !== '' ? $e($finishedAt) : '—' ?></div>
            </div>
        </div>

        <div class="eb-live-log eb-live-workloads" id="ms365WorkloadsPanel">
            <div class="eb-live-log-toolbar">
                <div class="eb-live-log-title">
                    <span id="ms365WorkloadsLiveDot" class="live-dot" style="display: none;" aria-hidden="true"></span>
                    <span>Workloads</span>
                </div>
                <span id="ms365WorkloadsSummary" class="eb-live-workloads-summary"></span>
            </div>
            <div class="eb-table-shell eb-live-workloads-scroll">
                <table class="eb-table min-w-full text-sm">
                    <thead>
                        <tr>
                            <th>Workload</th>
                            <th>Status</th>
                            <th>Phase</th>
                            <th>Error</th>
                            <th class="eb-table-cell-numeric">Progress</th>
                        </tr>
                    </thead>
                    <tbody id="ms365WorkloadsBody">
                        <tr id="ms365WorkloadsEmptyRow">
                            <td colspan="5" class="eb-type-caption italic eb-text-muted">Loading workloads…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="eb-live-log" id="ebLiveLogPanel">
            <div class="eb-live-log-toolbar">
                <div class="eb-live-log-title">
                    <span id="logLiveDot" class="live-dot" style="display: none;" aria-hidden="true"></span>
                    <span id="logPauseIndicator" class="eb-type-caption" style="display: none; color: var(--eb-text-muted);">Paused</span>
                    <svg id="logStaticIcon" class="eb-live-log-title-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span id="logPanelTitle">Live Logs</span>
                </div>
                <input id="logSearchInput" type="search" class="eb-live-log-search" placeholder="Search logs…" autocomplete="off">
                <button type="button" id="pauseUpdatesBtn" class="eb-log-btn">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="5" y="4" width="4" height="16" rx="1"/><rect x="15" y="4" width="4" height="16" rx="1"/></svg>
                    Pause
                </button>
                <button type="button" class="eb-log-btn" id="copyLogsBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Copy
                </button>
                <button type="button" class="eb-log-btn" id="clearLogsBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/></svg>
                    Clear
                </button>
                <button id="forceCancelButton" type="button" class="eb-log-btn" style="display: none;">Force Cancel</button>
            </div>

            <div class="eb-live-log-output" id="liveLogs">
                <div class="eb-type-caption italic px-4 py-3" id="liveLogsEmpty" style="color: var(--eb-text-muted);">Waiting for log data…</div>
            </div>

            <div class="eb-live-log-footer">
                <span id="logFooterSummary">0 lines</span>
                <div class="eb-log-page-controls" id="logPaginationWrap">
                    <button type="button" class="eb-log-page-btn" id="logPageNewer" disabled>← Newer</button>
                    <span class="eb-log-page-current" id="logPageCurrent">Page 1 / 1</span>
                    <button type="button" class="eb-log-page-btn" id="logPageOlder" disabled>Older →</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="cancelRunConfirmModal" class="fixed inset-0 z-[2200] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="eb-modal-backdrop absolute inset-0" data-dismiss="cancel-modal" aria-hidden="true"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden" onclick="event.stopPropagation()">
        <div class="eb-modal-header">
            <div>
                <h3 class="eb-modal-title" id="cancelRunConfirmTitle">Cancel run?</h3>
                <p class="eb-modal-subtitle" id="cancelRunConfirmMessage">This will stop the active Microsoft 365 backup workloads.</p>
            </div>
            <button type="button" class="eb-modal-close" id="cancelRunConfirmClose" data-dismiss="cancel-modal" aria-label="Close cancel confirmation">×</button>
        </div>
        <div class="eb-modal-body">
            <div id="cancelRunConfirmWarning" class="eb-alert eb-alert--warning">
                <div id="cancelRunConfirmDetail">Running workloads will be cancelled and workers stopped.</div>
            </div>
            <div id="cancelRunConfirmProgress" class="eb-alert eb-alert--info hidden">
                <div id="cancelRunConfirmProgressMessage">Cancellation in progress. This may take a few seconds while Microsoft 365 workloads are stopped…</div>
            </div>
        </div>
        <div class="eb-modal-footer">
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" id="cancelRunConfirmDismiss" data-dismiss="cancel-modal">Keep Running</button>
            <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" id="cancelRunConfirmSubmit">Confirm Cancel</button>
        </div>
    </div>
</div>

<div id="cancelRunStatusModal" class="fixed inset-0 z-[2200] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="eb-modal-backdrop absolute inset-0" data-dismiss="cancel-status-modal" aria-hidden="true"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden" onclick="event.stopPropagation()">
        <div class="eb-modal-header">
            <div>
                <h3 class="eb-modal-title" id="cancelRunStatusTitle">Cancel request submitted</h3>
                <p class="eb-modal-subtitle" id="cancelRunStatusSubtitle">The run will refresh shortly.</p>
            </div>
            <button type="button" class="eb-modal-close" data-dismiss="cancel-status-modal" aria-label="Close">×</button>
        </div>
        <div class="eb-modal-body">
            <div id="cancelRunStatusAlert" class="eb-alert eb-alert--info">
                <div id="cancelRunStatusMessage">Microsoft 365 backup workloads are being cancelled.</div>
            </div>
        </div>
        <div class="eb-modal-footer">
            <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" data-dismiss="cancel-status-modal">OK</button>
        </div>
    </div>
</div>

<div id="copyLogsModal" class="fixed inset-0 z-[2200] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="eb-modal-backdrop absolute inset-0" data-dismiss="copy-modal" aria-hidden="true"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden" onclick="event.stopPropagation()">
        <div class="eb-modal-header">
            <div>
                <h3 class="eb-modal-title">Logs copied</h3>
                <p class="eb-modal-subtitle">Log lines were copied to your clipboard.</p>
            </div>
            <button type="button" class="eb-modal-close" data-dismiss="copy-modal" aria-label="Close">×</button>
        </div>
        <div class="eb-modal-footer">
            <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" data-dismiss="copy-modal">OK</button>
        </div>
    </div>
</div>

<script>
window.MS365_LIVE = <?= json_encode([
    'apiBase' => $apiBase,
    'token' => $token,
    'runId' => $runId,
    'clientLabel' => $clientLabel,
    'jobName' => $jobName,
    'initialStatus' => $status,
    'initialWorkloads' => $initialWorkloads,
    'isRunning' => $isRunningStatus,
], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-admin-live.js')) ?>?v=<?= (int) @filemtime($liveJsPath) ?>"></script>
