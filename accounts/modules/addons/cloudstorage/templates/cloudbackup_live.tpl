{assign var=ebLiveRunsUrl value="index.php?m=cloudstorage&page=e3backup&view=runs&job_id="|cat:$job.job_id}

{capture assign=ebE3TitleHtml}
<div class="min-w-0 flex-1">
    <nav class="eb-breadcrumb mb-1" aria-label="Breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="eb-breadcrumb-link">Jobs</a>
        <span class="eb-breadcrumb-separator">/</span>
        <a href="{$ebLiveRunsUrl|escape:'html'}" class="eb-breadcrumb-link">{if $job.name}{$job.name|escape:'html'}{else}Unnamed job{/if}</a>
        <span class="eb-breadcrumb-separator">/</span>
        <a href="{$ebLiveRunsUrl|escape:'html'}" class="eb-breadcrumb-link">Runs</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">{if $is_restore}Live restore{else}Live{/if}</span>
    </nav>
    <div class="flex flex-wrap items-center gap-2.5 gap-y-1">
        <h1 class="eb-app-header-title">{if $job.name}{$job.name|escape:'html'}{else}Unnamed job{/if}</h1>
        <span id="liveHeaderBadge" class="eb-badge eb-badge--info eb-badge--dot">{$run.status|ucfirst|escape:'html'}</span>
    </div>
</div>
{/capture}

{capture assign=ebE3Actions}
    <button
        id="cancelButton"
        type="button"
        onclick="cancelRun('{$run.run_id}')"
        class="eb-btn eb-btn-danger eb-btn-sm hidden"
        style="display: none;"
    >
        Cancel Run
    </button>
{/capture}

{capture assign=ebE3Content}
    {assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
    {assign var="showFilesMetric" value=(!$is_restore && $job.engine ne 'disk_image' && $job.engine ne 'hyperv')}

    <div class="eb-live-page">
        <div id="errorSummaryContainer" class="eb-live-alert eb-live-alert--danger hidden" role="status" aria-live="polite">
            <p class="eb-live-alert-title">Startup Error</p>
            <p id="errorSummaryText" class="eb-live-alert-copy"></p>
        </div>

        <section class="eb-live-progress" x-data="{ isRunning: {if $isRunningStatus}true{else}false{/if} }" id="liveProgressStrip">
            <div class="eb-live-progress-top">
                <div class="eb-live-percent" aria-live="polite">
                    <span id="progressPercentValue">{if $run.progress_pct}{$run.progress_pct|string_format:"%.2f"}{else}0.00{/if}</span><span class="unit">%</span>
                </div>
                <div class="eb-live-stage">
                    <span id="stageStatusDot" class="eb-status-dot eb-status-dot--pending"></span>
                    <span id="stageLabel" style="color: var(--eb-info-text); font-weight: 600;">{if $run.stage}{$run.stage|escape:'html'}{else}{$run.status|ucfirst|escape:'html'}{/if}</span>
                    <span id="stageEta" class="eb-live-stage-eta"></span>
                </div>
            </div>

            <div class="eb-live-bar" aria-hidden="true">
                <div
                    class="eb-live-bar-fill running"
                    id="progressBar"
                    style="width: {if $run.progress_pct}{$run.progress_pct}{else}0{/if}%"
                    role="progressbar"
                    aria-label="Backup progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{if $run.progress_pct}{$run.progress_pct|string_format:"%.2f"}{else}0.00{/if}"
                ></div>
            </div>

            <div class="eb-live-stats">
                <div class="eb-live-stat">
                    <div class="eb-live-stat-label">Speed</div>
                    <div class="eb-live-stat-value highlight" id="speedValue">
                        {if $run.speed_bytes_per_sec}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.speed_bytes_per_sec)}/s
                        {else}
                            —
                        {/if}
                    </div>
                    <p id="speedHint" class="eb-live-stat-hint"></p>
                </div>
                <div class="eb-live-stat">
                    <div class="eb-live-stat-label">Processed</div>
                    <div class="eb-live-stat-value" id="bytesProcessedValue">
                        {if $run.bytes_processed}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_processed)}
                        {elseif $run.bytes_transferred}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                        {else}
                            0.00 Bytes
                        {/if}
                    </div>
                </div>
                <div class="eb-live-stat">
                    <div class="eb-live-stat-label">Uploaded</div>
                    <div class="eb-live-stat-value" id="bytesTransferredValue">
                        {if $run.bytes_transferred}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                        {else}
                            0.00 Bytes
                        {/if}
                    </div>
                    <p id="uploadedSavings" class="eb-live-stat-hint"></p>
                </div>
                <div class="eb-live-stat">
                    <div class="eb-live-stat-label">Files</div>
                    <div class="eb-live-stat-value" id="filesValue">{if $showFilesMetric}-{else}—{/if}</div>
                </div>
                <div class="eb-live-stat">
                    <div class="eb-live-stat-label">Folders</div>
                    <div class="eb-live-stat-value" id="foldersValue">{if $showFilesMetric}—{else}—{/if}</div>
                </div>
                <div class="eb-live-stat">
                    <div class="eb-live-stat-label" id="durationStatLabel">{if $isRunningStatus}Elapsed{else}Duration{/if}</div>
                    <div class="eb-live-stat-value mono" id="durationValue">—</div>
                </div>
            </div>

            <div class="eb-live-current-file" id="currentFileRow" x-show="isRunning" x-cloak>
                <span class="file-spinner" aria-hidden="true"></span>
                <span class="file-label">{if $is_restore}Restoring{else}Processing{/if}</span>
                <span id="currentItem" class="file-path">{if $run.current_item}{$run.current_item|escape:'html'}{else}—{/if}</span>
            </div>
            <div id="currentItemEmpty" class="hidden" aria-hidden="true"></div>
        </section>

        <div class="eb-live-details" id="liveDetailsStrip">
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Agent</div>
                <div class="eb-live-detail-value" id="detailsAgent">
                    {if $job.source_type eq 'local_agent'}
                        {if $agent_name}{$agent_name|escape:'html'}{elseif $agent_uuid}{$agent_uuid|escape:'html'}{else}Agent unavailable{/if}
                    {else}
                        Cloud Backup
                    {/if}
                </div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Job</div>
                <div class="eb-live-detail-value min-w-0 max-w-full overflow-hidden text-ellipsis whitespace-nowrap" id="detailsJob">{if $job.name}{$job.name|escape:'html'}{else}Unnamed job{/if}</div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Run ID</div>
                <div class="eb-live-detail-value eb-live-detail-value--mono" id="detailsRunId" title="{$run.run_id|escape:'html'}">{$run.run_id|escape:'html'}</div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Started</div>
                <div class="eb-live-detail-value" id="detailsStartedAt">{$run.started_at|default:'-'|escape:'html'}</div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Finished</div>
                <div class="eb-live-detail-value" id="detailsFinishedAt">{$run.finished_at|default:'-'|escape:'html'}</div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Mode</div>
                <div class="eb-live-detail-value">{$job.backup_mode|default:$job.engine|default:'-'|escape:'html'}</div>
            </div>
            <div class="eb-live-detail">
                <div class="eb-live-detail-label">Destination</div>
                <div class="eb-live-detail-value min-w-0 max-w-full overflow-hidden text-ellipsis whitespace-nowrap">
                    {if isset($job.dest_bucket_name) && $job.dest_bucket_name}
                        {$job.dest_bucket_name|escape:'html'}{if $job.dest_prefix} / {$job.dest_prefix|escape:'html'}{/if}
                    {elseif isset($job.dest_local_path) && $job.dest_local_path}
                        {$job.dest_local_path|escape:'html'}
                    {elseif isset($job.dest_bucket_id) && $job.dest_bucket_id}
                        Bucket #{$job.dest_bucket_id}{if $job.dest_prefix} / {$job.dest_prefix|escape:'html'}{/if}
                    {else}
                        —
                    {/if}
                </div>
            </div>
        </div>

        {if $is_restore && $restore_metadata}
            <div class="eb-live-alert eb-live-alert--success">
                {if $is_hyperv_restore}
                    <p class="eb-live-alert-title">Hyper-V Restore</p>
                    <p class="eb-live-alert-copy">VM <span class="eb-live-inline-strong">{$restore_metadata.vm_name|escape:'html'}</span> to <span class="eb-live-inline-strong">{$restore_metadata.target_path|escape:'html'}</span></p>
                {else}
                    <p class="eb-live-alert-title">Restore</p>
                    <p class="eb-live-alert-copy">Snapshot {$restore_metadata.manifest_id|truncate:16:'...'|escape:'html'} to {$restore_metadata.target_path|escape:'html'}</p>
                {/if}
            </div>
        {/if}

        <div class="eb-live-alert eb-live-alert--warning">
            <p class="eb-live-alert-title">Cloud Backup (Beta)</p>
            <p class="eb-live-alert-copy">Cloud Backup is in beta. Keep a primary backup strategy in place and contact support if you notice any issues.</p>
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
                <input id="logSearchInput" type="search" class="eb-live-log-search" placeholder="Search logs…" autocomplete="off" oninput="setLogSearch(this.value)">
                <button type="button" id="pauseUpdatesBtn" class="eb-log-btn" onclick="togglePauseUpdates()">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="5" y="4" width="4" height="16" rx="1"/><rect x="15" y="4" width="4" height="16" rx="1"/></svg>
                    Pause
                </button>
                <button type="button" class="eb-log-btn" onclick="copyLogs()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Copy
                </button>
                <button type="button" class="eb-log-btn" onclick="clearLogs()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/></svg>
                    Clear
                </button>
                <button id="forceCancelButton" type="button" class="eb-log-btn" style="display: none;" onclick="cancelRun('{$run.run_id}', true)">
                    Force Cancel
                </button>
            </div>

            <div class="eb-live-log-output" id="liveLogs">
                <div class="eb-type-caption italic px-4 py-3" id="liveLogsEmpty" style="color: var(--eb-text-muted);">Waiting for log data…</div>
            </div>

            <div class="eb-live-log-footer">
                <span id="logFooterSummary">0 lines</span>
                <div class="eb-log-page-controls" id="logPaginationWrap">
                    <button type="button" class="eb-log-page-btn" id="logPageNewer" disabled onclick="goLogPage(-1)">← Newer</button>
                    <span class="eb-log-page-current" id="logPageCurrent">Page 1 / 1</span>
                    <button type="button" class="eb-log-page-btn" id="logPageOlder" disabled onclick="goLogPage(1)">Older →</button>
                </div>
            </div>
        </div>
    </div>

    <div id="cancelRunConfirmModal"
         class="fixed inset-0 z-[2200] hidden items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="cancelRunConfirmTitle"
         aria-describedby="cancelRunConfirmMessage">
        <div class="eb-modal-backdrop absolute inset-0" onclick="closeCancelConfirmModal()" aria-hidden="true"></div>
        <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden" onclick="event.stopPropagation()">
            <div class="eb-modal-header">
                <div>
                    <h3 class="eb-modal-title" id="cancelRunConfirmTitle">Cancel run?</h3>
                    <p class="eb-modal-subtitle" id="cancelRunConfirmMessage">This will ask the agent to stop the active run.</p>
                </div>
                <button type="button" class="eb-modal-close" onclick="closeCancelConfirmModal()" aria-label="Close cancel confirmation">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="eb-modal-body">
                <div class="eb-alert eb-alert--warning">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.01M10.29 3.86l-7.5 13A1 1 0 003.66 18h16.68a1 1 0 00.87-1.5l-7.5-13a1 1 0 00-1.74 0z"/>
                    </svg>
                    <div id="cancelRunConfirmDetail">The run will stop on the agent's next command poll.</div>
                </div>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" id="cancelRunConfirmDismiss" onclick="closeCancelConfirmModal()">Keep Running</button>
                <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" id="cancelRunConfirmSubmit" onclick="submitCancelRun()">
                    Confirm Cancel
                </button>
            </div>
        </div>
    </div>

    <div id="cancelRunStatusModal"
         class="fixed inset-0 z-[2200] hidden items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="cancelRunStatusTitle"
         aria-describedby="cancelRunStatusMessage">
        <div class="eb-modal-backdrop absolute inset-0" onclick="closeCancelStatusModal()" aria-hidden="true"></div>
        <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden" onclick="event.stopPropagation()">
            <div class="eb-modal-header">
                <div>
                    <h3 class="eb-modal-title" id="cancelRunStatusTitle">Cancel request submitted</h3>
                    <p class="eb-modal-subtitle" id="cancelRunStatusSubtitle">The run will refresh shortly.</p>
                </div>
                <button type="button" class="eb-modal-close" onclick="closeCancelStatusModal()" aria-label="Close cancel status message">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="eb-modal-body">
                <div id="cancelRunStatusAlert" class="eb-alert eb-alert--info">
                    <svg id="cancelRunStatusIcon" class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z"/>
                    </svg>
                    <div id="cancelRunStatusMessage">The agent will stop the run on its next command poll.</div>
                </div>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" onclick="closeCancelStatusModal()">OK</button>
            </div>
        </div>
    </div>
{/capture}

{include
    file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage="jobs"
    ebE3Title=""
    ebE3Description=""
    ebE3TitleHtml=$ebE3TitleHtml
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

<script>
let progressInterval;
let eventsInterval;
{assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
let isRunning = {if $isRunningStatus}true{else}false{/if};
const RUN_UUID = '{$run.run_id}';
const SHOW_FILES_METRIC = {if $showFilesMetric}true{else}false{/if};
let lastLogsHash = null;
let lastEventId = 0;
const errorSummaryContainer = document.getElementById('errorSummaryContainer');
const errorSummaryText = document.getElementById('errorSummaryText');
const forceCancelButton = document.getElementById('forceCancelButton');
const LOG_PAGE_SIZE = 200;
const MAX_STORED_LOG_LINES = 20000;

const STATUS_CONFIGS = {
    'success': { text: 'Success', bar: 'success', badge: 'eb-badge eb-badge--success eb-badge--dot', stageColor: 'var(--eb-success-text)', dot: 'active' },
    'failed': { text: 'Failed', bar: 'failed', badge: 'eb-badge eb-badge--danger eb-badge--dot', stageColor: 'var(--eb-danger-text)', dot: 'error' },
    'running': { text: 'Running', bar: 'running', badge: 'eb-badge eb-badge--info eb-badge--dot', stageColor: 'var(--eb-info-text)', dot: 'pending' },
    'starting': { text: 'Starting', bar: 'running', badge: 'eb-badge eb-badge--info eb-badge--dot', stageColor: 'var(--eb-info-text)', dot: 'pending' },
    'queued': { text: 'Queued', bar: 'running', badge: 'eb-badge eb-badge--warning eb-badge--dot', stageColor: 'var(--eb-warning-text)', dot: 'pending' },
    'warning': { text: 'Warning', bar: 'warning', badge: 'eb-badge eb-badge--warning eb-badge--dot', stageColor: 'var(--eb-warning-text)', dot: 'warning' },
    'cancelled': { text: 'Cancelled', bar: 'neutral', badge: 'eb-badge eb-badge--neutral eb-badge--dot', stageColor: 'var(--eb-text-muted)', dot: 'inactive' },
    'partial_success': { text: 'Partial Success', bar: 'warning', badge: 'eb-badge eb-badge--warning eb-badge--dot', stageColor: 'var(--eb-warning-text)', dot: 'warning' }
};

const STAGE_FALLBACKS = {
    'running': 'Uploading',
    'starting': 'Preparing',
    'queued': 'Queued',
    'success': 'Completed',
    'failed': 'Failed',
    'warning': 'Warning',
    'cancelled': 'Cancelled',
    'partial_success': 'Partial Success'
};

const TERMINAL_STATUSES = ['success', 'failed', 'cancelled', 'warning', 'partial_success'];

let durationStartMs = null;
let durationEndMs = null;
let durationTimer = null;
let isPaused = false;
let logEntries = [];
let logSearchQuery = '';
let logPage = 1;
let pausedLogBuffer = [];
let cancelRequestInFlight = false;
let pendingCancelRunId = '';
let pendingCancelForce = false;

let currentPct = (() => {
    const label = document.getElementById('progressPercentValue');
    const txt = (label && label.textContent ? label.textContent : '0') || '0';
    return parseFloat(txt) || 0;
})();
let tweenId;

function smoothProgressTo(targetPct, duration = 600) {
    if (tweenId) cancelAnimationFrame(tweenId);
    const bar = document.getElementById('progressBar');
    const label = document.getElementById('progressPercentValue');
    const start = performance.now();
    const from = Math.max(0, Math.min(100, currentPct));
    const to = Math.max(from, Math.min(100, targetPct));
    const ease = t => (t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t);
    (function step(now) {
        const t = Math.min(1, (now - start) / duration);
        const v = from + (to - from) * ease(t);
        currentPct = v;
        if (bar) {
            bar.style.width = v.toFixed(2) + '%';
            bar.setAttribute('aria-valuenow', v.toFixed(2));
        }
        if (label) {
            label.textContent = v.toFixed(2);
        }
        if (t < 1) tweenId = requestAnimationFrame(step);
    })(start);
}

function setLiveBarFillState(bar, state) {
    if (!bar) return;
    bar.className = 'eb-live-bar-fill';
    if (state === 'running') bar.classList.add('running');
    else if (state === 'failed') bar.classList.add('failed');
    else if (state === 'warning') bar.classList.add('eb-live-bar-fill--warning');
    else if (state === 'neutral') bar.classList.add('eb-live-bar-fill--neutral');
    else if (state === 'indeterminate') bar.classList.add('eb-live-bar-fill--indeterminate');
}

const etaModel = {
    startMs: null,
    predictedTotalSec: null
};

function parseRunTimestamp(value) {
    if (!value) return null;
    const raw = String(value).trim();
    if (!raw) return null;
    if (raw.includes('T')) {
        const parsed = Date.parse(raw);
        return isNaN(parsed) ? null : parsed;
    }
    const iso = raw.replace(' ', 'T') + 'Z';
    const parsed = Date.parse(iso);
    if (!isNaN(parsed)) return parsed;
    const fallback = Date.parse(raw);
    return isNaN(fallback) ? null : fallback;
}

(function initCancelButton() {
    const cancelButton = document.getElementById('cancelButton');
    if (cancelButton) {
        const initialStatus = '{$run.status}';
        const shouldShow = ['running', 'starting', 'queued'].includes(initialStatus);
        if (shouldShow) {
            cancelButton.classList.remove('hidden');
            cancelButton.style.display = '';
        }
    }
})();

(function initBarFromServer() {
    const bar = document.getElementById('progressBar');
    const st = '{$run.status}';
    const cfg = STATUS_CONFIGS[st];
    if (cfg) {
        if (cfg.bar === 'running') setLiveBarFillState(bar, 'running');
        else if (cfg.bar === 'failed') setLiveBarFillState(bar, 'failed');
        else if (cfg.bar === 'warning') setLiveBarFillState(bar, 'warning');
        else if (cfg.bar === 'neutral') setLiveBarFillState(bar, 'neutral');
        else setLiveBarFillState(bar, 'success');
    }
    const dot = document.getElementById('stageStatusDot');
    if (dot && cfg) {
        dot.className = 'eb-status-dot eb-status-dot--' + (cfg.dot === 'pending' ? 'pending' : cfg.dot === 'active' ? 'active' : cfg.dot === 'error' ? 'error' : cfg.dot === 'warning' ? 'warning' : 'inactive');
    }
    const stageEl = document.getElementById('stageLabel');
    if (stageEl && cfg) {
        stageEl.style.color = cfg.stageColor;
    }
})();

function updateProgress() {
    if (isPaused) return;
    const ts = Date.now();
    fetch('modules/addons/cloudstorage/api/cloudbackup_progress.php?run_uuid={$run.run_id}&ts=' + ts, { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.run) {
                const run = data.run;
                refreshErrorSummary(run);

                let progressPct = 0;
                const apiPct = parseFloat(run.progress_pct);
                const bytesProcessedForPct = run.bytes_processed || run.bytes_transferred || 0;
                if (!isNaN(apiPct) && apiPct > 0) {
                    progressPct = apiPct;
                } else if (run.bytes_total && run.bytes_total > 0 && bytesProcessedForPct >= 0) {
                    progressPct = Math.min(100, (bytesProcessedForPct / run.bytes_total) * 100);
                } else if (run.objects_total && run.objects_total > 0 && run.objects_transferred >= 0) {
                    progressPct = Math.min(100, (run.objects_transferred / run.objects_total) * 100);
                } else {
                    if (!etaModel.startMs) {
                        const parsed = parseRunTimestamp(run.started_at);
                        etaModel.startMs = parsed || Date.now();
                    }
                    const nowMs = Date.now();
                    const elapsedSec = Math.max(0, (nowMs - etaModel.startMs) / 1000);
                    const etaSec = (typeof run.eta_seconds === 'number' && run.eta_seconds >= 0) ? run.eta_seconds : null;
                    if (etaSec !== null) {
                        const candidateTotal = elapsedSec + etaSec;
                        if (!etaModel.predictedTotalSec || candidateTotal > etaModel.predictedTotalSec) {
                            etaModel.predictedTotalSec = candidateTotal;
                        }
                        if (etaModel.predictedTotalSec && etaModel.predictedTotalSec > 0) {
                            progressPct = Math.min(99.0, (elapsedSec / etaModel.predictedTotalSec) * 100);
                        }
                    }
                }

                const progressBar = document.getElementById('progressBar');
                const progressPercentValue = document.getElementById('progressPercentValue');

                if (progressPercentValue) {
                    progressPercentValue.textContent = (progressPct > 0 ? progressPct : 0).toFixed(2);
                }

                const isFinished = ['success', 'failed', 'cancelled', 'warning', 'partial_success'].includes(run.status);

                if (!isFinished) {
                    if (progressPct > 0.01) {
                        setLiveBarFillState(progressBar, 'running');
                        smoothProgressTo(progressPct);
                    } else {
                        if (progressBar) {
                            progressBar.style.width = '100%';
                            progressBar.setAttribute('aria-valuenow', '0.00');
                            setLiveBarFillState(progressBar, 'indeterminate');
                        }
                        if (progressPercentValue) {
                            progressPercentValue.textContent = progressPct.toFixed(2);
                        }
                    }
                } else {
                    if (run.status === 'success') {
                        smoothProgressTo(100, 800);
                        setLiveBarFillState(progressBar, 'success');
                    } else if (progressBar) {
                        if (run.status === 'failed') {
                            setLiveBarFillState(progressBar, 'failed');
                        } else if (run.status === 'cancelled') {
                            setLiveBarFillState(progressBar, 'neutral');
                        } else {
                            setLiveBarFillState(progressBar, 'warning');
                        }
                    }
                }

                const bytesProcessed = run.bytes_processed || run.bytes_transferred || 0;
                const processedValueEl = document.getElementById('bytesProcessedValue');
                if (processedValueEl) {
                    processedValueEl.textContent = formatBytes(bytesProcessed);
                }

                const transferredValueEl = document.getElementById('bytesTransferredValue');
                if (transferredValueEl) {
                    transferredValueEl.textContent = formatBytes(run.bytes_transferred || 0);
                }

                const uploadedSavingsEl = document.getElementById('uploadedSavings');
                if (uploadedSavingsEl) {
                    if (bytesProcessed > 0) {
                        const transferred = run.bytes_transferred || 0;
                        const savedBytes = Math.max(0, bytesProcessed - transferred);
                        const savedPercent = bytesProcessed > 0 ? (savedBytes / bytesProcessed) * 100 : 0;
                        uploadedSavingsEl.textContent = 'Saved: ' + formatBytes(savedBytes) + ' (' + savedPercent.toFixed(1) + '%)';
                    } else {
                        uploadedSavingsEl.textContent = '';
                    }
                }

                const speedValueEl = document.getElementById('speedValue');
                if (speedValueEl) {
                    if (isFinished) {
                        speedValueEl.textContent = '—';
                        speedValueEl.classList.remove('highlight');
                    } else {
                        speedValueEl.textContent = run.speed_bytes_per_sec ? (formatBytes(run.speed_bytes_per_sec) + '/s') : '—';
                        if (run.speed_bytes_per_sec) speedValueEl.classList.add('highlight');
                        else speedValueEl.classList.remove('highlight');
                    }
                }
                const speedHintEl = document.getElementById('speedHint');
                if (speedHintEl) {
                    speedHintEl.textContent = (!isFinished && run.speed_bytes_per_sec) ? 'Instantaneous' : '';
                }

                if (SHOW_FILES_METRIC) {
                    const filesValueEl = document.getElementById('filesValue');
                    const foldersValueEl = document.getElementById('foldersValue');
                    if (filesValueEl) {
                        const filesDone = (run.files_done !== undefined && run.files_done !== null) ? run.files_done : (run.objects_transferred || 0);
                        const filesTotal = (run.files_total !== undefined && run.files_total !== null) ? run.files_total : (run.objects_total || 0);
                        if (filesTotal > 0) {
                            filesValueEl.textContent = formatCount(filesDone) + ' / ' + formatCount(filesTotal);
                        } else {
                            filesValueEl.textContent = formatCount(filesDone);
                        }
                    }
                    if (foldersValueEl) {
                        if (run.folders_done !== undefined && run.folders_done !== null) {
                            foldersValueEl.textContent = formatCount(run.folders_done);
                        } else {
                            foldersValueEl.textContent = '—';
                        }
                    }
                }

                const stageEtaEl = document.getElementById('stageEta');
                if (stageEtaEl) {
                    if (isFinished) {
                        stageEtaEl.textContent = '';
                    } else if (run.eta_seconds !== undefined && run.eta_seconds !== null && run.eta_seconds >= 0) {
                        stageEtaEl.textContent = ' — ETA ' + formatEta(run.eta_seconds);
                        stageEtaEl.classList.add('eb-live-value-refresh');
                        setTimeout(() => stageEtaEl.classList.remove('eb-live-value-refresh'), 50);
                    } else {
                        stageEtaEl.textContent = '';
                    }
                }

                const currentItemEl = document.getElementById('currentItem');
                if (currentItemEl) {
                    if (run.current_item) {
                        currentItemEl.textContent = run.current_item;
                    } else {
                        currentItemEl.textContent = '—';
                    }
                }

                const detailsStarted = document.getElementById('detailsStartedAt');
                if (detailsStarted && run.started_at) detailsStarted.textContent = run.started_at;
                const detailsFinished = document.getElementById('detailsFinishedAt');
                if (detailsFinished) detailsFinished.textContent = run.finished_at || '—';

                const statusConfig = STATUS_CONFIGS[run.status] || {
                    text: run.status ? (run.status.charAt(0).toUpperCase() + run.status.slice(1)) : 'Unknown',
                    bar: 'neutral',
                    badge: 'eb-badge eb-badge--neutral eb-badge--dot',
                    stageColor: 'var(--eb-text-muted)',
                    dot: 'inactive'
                };
                updateStatusDisplay(statusConfig);
                updateStageLabel(run);
                updateDuration(run);

                const cancelButton = document.getElementById('cancelButton');
                if (cancelButton) {
                    const shouldShow = ['running', 'starting', 'queued'].includes(run.status);
                    if (shouldShow) {
                        cancelButton.classList.remove('hidden');
                        cancelButton.style.display = '';
                    } else {
                        cancelButton.classList.add('hidden');
                        cancelButton.style.display = 'none';
                    }
                }

                const container = document.getElementById('liveProgressStrip');
                const newIsRunning = ['running', 'starting', 'queued'].includes(run.status);

                if (container && window.Alpine) {
                    try {
                        const containerData = Alpine.$data(container);
                        if (containerData) {
                            containerData.isRunning = newIsRunning;
                        }
                    } catch (e) {
                        console.warn('Error updating Alpine.js isRunning:', e);
                    }
                }
                isRunning = newIsRunning;
                syncLogPanelChrome(newIsRunning);

                if (TERMINAL_STATUSES.includes(run.status)) {
                    if (progressInterval) {
                        clearInterval(progressInterval);
                        progressInterval = null;
                    }
                    if (eventsInterval) {
                        clearInterval(eventsInterval);
                        eventsInterval = null;
                    }
                    isRunning = false;
                    if (container && window.Alpine) {
                        try {
                            const containerData = Alpine.$data(container);
                            if (containerData) {
                                containerData.isRunning = false;
                            }
                        } catch (e) {
                            console.warn('Error updating Alpine.js isRunning on completion:', e);
                        }
                    }
                    syncLogPanelChrome(false);
                }
            }
        })
        .catch(error => {
            console.error('Error updating progress:', error);
        });
}

function formatBytes(bytes) {
    if (bytes === 0) return '0.00 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const value = bytes / Math.pow(k, i);
    return value.toFixed(2) + ' ' + sizes[i];
}

function formatCount(value) {
    const num = Number(value) || 0;
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
        return new Intl.NumberFormat().format(num);
    }
    return String(num);
}

function formatEta(secondsTotal) {
    const s = Math.max(0, Math.floor(Number(secondsTotal) || 0));
    if (s <= 0) return '—';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    let out = '';
    if (h > 0) out += h + 'h ';
    if (m > 0) out += m + 'm ';
    out += sec + 's';
    return out.trim();
}

function syncLogPanelChrome(live) {
    const dot = document.getElementById('logLiveDot');
    const icon = document.getElementById('logStaticIcon');
    const title = document.getElementById('logPanelTitle');
    const pauseBtn = document.getElementById('pauseUpdatesBtn');
    if (title) {
        title.textContent = live ? 'Live Logs' : 'Run Logs';
    }
    if (dot) {
        dot.style.display = live ? '' : 'none';
    }
    if (icon) {
        icon.style.display = live ? 'none' : '';
    }
    if (pauseBtn) {
        pauseBtn.style.display = live ? '' : 'none';
    }
}

function setCancelButtonsBusy(isBusy) {
    const cancelButton = document.getElementById('cancelButton');
    const forceButton = document.getElementById('forceCancelButton');

    if (cancelButton) {
        cancelButton.disabled = isBusy;
        cancelButton.textContent = isBusy ? 'Cancelling...' : 'Cancel Run';
    }
    if (forceButton) {
        forceButton.disabled = isBusy;
        forceButton.textContent = isBusy ? 'Cancelling...' : 'Force Cancel';
    }
}

function openCancelConfirmModal(runId, forceCancel = false) {
    const runIdentifier = (runId || '').trim();
    if (!runIdentifier || cancelRequestInFlight) {
        return;
    }

    pendingCancelRunId = runIdentifier;
    pendingCancelForce = !!forceCancel;

    const modal = document.getElementById('cancelRunConfirmModal');
    const title = document.getElementById('cancelRunConfirmTitle');
    const message = document.getElementById('cancelRunConfirmMessage');
    const detail = document.getElementById('cancelRunConfirmDetail');
    const submit = document.getElementById('cancelRunConfirmSubmit');
    if (!modal || !title || !message || !detail || !submit) {
        return;
    }

    if (pendingCancelForce) {
        title.textContent = 'Force cancel run?';
        message.textContent = 'This will mark the run cancelled immediately if the agent does not respond.';
        detail.textContent = 'Use force cancel only when the run is stuck and a normal cancel is not clearing it.';
        submit.textContent = 'Force Cancel';
    } else {
        title.textContent = 'Cancel run?';
        message.textContent = 'This will ask the agent to stop the active run.';
        detail.textContent = 'The run will stop on the agent\'s next command poll.';
        submit.textContent = 'Confirm Cancel';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeCancelConfirmModal(forceClose = false) {
    if (cancelRequestInFlight && !forceClose) {
        return;
    }
    const modal = document.getElementById('cancelRunConfirmModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    pendingCancelRunId = '';
    pendingCancelForce = false;
}

function openCancelStatusModal(options = {}) {
    const modal = document.getElementById('cancelRunStatusModal');
    const title = document.getElementById('cancelRunStatusTitle');
    const subtitle = document.getElementById('cancelRunStatusSubtitle');
    const message = document.getElementById('cancelRunStatusMessage');
    const alertBox = document.getElementById('cancelRunStatusAlert');
    const icon = document.getElementById('cancelRunStatusIcon');
    if (!modal || !title || !subtitle || !message || !alertBox || !icon) {
        return;
    }

    const variant = options.variant || 'info';
    title.textContent = options.title || 'Cancel request submitted';
    subtitle.textContent = options.subtitle || 'The run will refresh shortly.';
    message.textContent = options.message || '';

    alertBox.className = 'eb-alert';
    if (variant === 'danger') {
        alertBox.classList.add('eb-alert--danger');
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.01M10.29 3.86l-7.5 13A1 1 0 0 0 3.66 18h16.68a1 1 0 0 0 .87-1.5l-7.5-13a1 1 0 0 0-1.74 0z"/>';
    } else if (variant === 'success') {
        alertBox.classList.add('eb-alert--success');
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>';
    } else {
        alertBox.classList.add('eb-alert--info');
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z"/>';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeCancelStatusModal() {
    const modal = document.getElementById('cancelRunStatusModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function cancelRun(runId, forceCancel = false) {
    openCancelConfirmModal(runId, forceCancel);
}

function submitCancelRun() {
    const runIdentifier = pendingCancelRunId;
    const forceCancel = pendingCancelForce;
    if (!runIdentifier || cancelRequestInFlight) {
        return;
    }

    cancelRequestInFlight = true;
    setCancelButtonsBusy(true);

    fetch('modules/addons/cloudstorage/api/cloudbackup_cancel_run.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            run_id: runIdentifier,
            force: forceCancel ? '1' : '0'
        }),
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (!data || data.status !== 'success') {
                throw new Error((data && data.message) ? data.message : 'Cancel request failed');
            }

            closeCancelConfirmModal(true);
            updateProgress();
            updateEventLogs();

            if (forceCancel) {
                openCancelStatusModal({
                    variant: 'success',
                    title: 'Force cancel submitted',
                    subtitle: 'The run will refresh shortly.',
                    message: 'The run was marked for immediate cancellation because the agent did not clear it normally.'
                });
            } else {
                openCancelStatusModal({
                    variant: 'info',
                    title: 'Cancel request submitted',
                    subtitle: 'Waiting for agent acknowledgement.',
                    message: 'The agent will stop the run on its next command poll.'
                });
            }
        })
        .catch(error => {
            console.error('Error cancelling run:', error);
            openCancelStatusModal({
                variant: 'danger',
                title: 'Cancel request failed',
                subtitle: 'The run is still active.',
                message: 'Failed to cancel run: ' + (error && error.message ? error.message : 'Unknown error')
            });
        })
        .finally(() => {
            cancelRequestInFlight = false;
            setCancelButtonsBusy(false);
        });
}

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
        return;
    }
    const modal = document.getElementById('cancelRunConfirmModal');
    if (modal && !modal.classList.contains('hidden')) {
        closeCancelConfirmModal();
        return;
    }
    const statusModal = document.getElementById('cancelRunStatusModal');
    if (statusModal && !statusModal.classList.contains('hidden')) {
        closeCancelStatusModal();
    }
});

function setLogSearch(value) {
    logSearchQuery = (value || '').toLowerCase().trim();
    renderLogPage();
}

function togglePauseUpdates() {
    isPaused = !isPaused;
    const btn = document.getElementById('pauseUpdatesBtn');
    const ind = document.getElementById('logPauseIndicator');
    if (btn) {
        btn.textContent = isPaused ? 'Resume' : 'Pause';
        btn.classList.toggle('is-active', isPaused);
    }
    if (ind) {
        ind.style.display = isPaused ? '' : 'none';
    }
    if (!isPaused) {
        flushPausedLogBuffer();
        updateProgress();
        updateEventLogs();
    }
}

function flushPausedLogBuffer() {
    if (!pausedLogBuffer.length) return;
    for (let i = pausedLogBuffer.length - 1; i >= 0; i--) {
        logEntries.unshift(pausedLogBuffer[i]);
    }
    pausedLogBuffer = [];
    trimLogStore();
    if (logPage === 1) {
        renderLogPage();
    } else {
        updateLogFooterOnly();
    }
}

function trimLogStore() {
    if (logEntries.length > MAX_STORED_LOG_LINES) {
        logEntries.length = MAX_STORED_LOG_LINES;
    }
}

function logEntryKey(entry) {
    return JSON.stringify({
        l: entry.level || '',
        t: entry.ts || '',
        m: entry.message || ''
    });
}

function appendLogEntry(entry) {
    const key = logEntryKey(entry);
    if (processedLogHashes.has(key)) {
        return;
    }
    processedLogHashes.add(key);

    if (isPaused) {
        pausedLogBuffer.push(entry);
        return;
    }

    logEntries.unshift(entry);
    trimLogStore();

    if (logPage === 1) {
        renderLogPage();
    } else {
        updateLogFooterOnly();
    }
}

function getLogPageSlice() {
    const start = (logPage - 1) * LOG_PAGE_SIZE;
    return logEntries.slice(start, start + LOG_PAGE_SIZE);
}

function renderLogPage() {
    const liveLogsContainer = document.getElementById('liveLogs');
    if (!liveLogsContainer) return;

    liveLogsContainer.removeAttribute('data-formatted');

    let slice = getLogPageSlice();
    if (logSearchQuery) {
        slice = slice.filter(entry => {
            const hay = (entry.message || '') + ' ' + (entry.level || '') + ' ' + (entry.ts || '');
            return hay.toLowerCase().includes(logSearchQuery);
        });
    }

    while (liveLogsContainer.firstChild) {
        liveLogsContainer.removeChild(liveLogsContainer.firstChild);
    }

    if (!slice.length) {
        const empty = document.createElement('div');
        empty.id = 'liveLogsEmpty';
        empty.className = 'eb-type-caption italic px-4 py-3';
        empty.style.color = 'var(--eb-text-muted)';
        empty.textContent = logSearchQuery ? 'No matches on this page.' : 'Waiting for log data…';
        liveLogsContainer.appendChild(empty);
        updateLogFooterOnly();
        return;
    }

    slice.forEach((entry, idx) => {
        const line = document.createElement('div');
        line.className = 'eb-log-line' + (logPage === 1 && idx === 0 && !logSearchQuery ? ' is-newest' : '');

        const levelNorm = normalizeLevel(entry.level);
        const levelSpan = document.createElement('span');
        levelSpan.className = 'eb-log-level ' + levelNorm;
        levelSpan.textContent = (entry.level || 'info').toUpperCase();

        const tsSpan = document.createElement('span');
        tsSpan.className = 'eb-log-timestamp';
        tsSpan.textContent = entry.ts ? '[' + entry.ts + ']' : '';

        const msgSpan = document.createElement('span');
        msgSpan.className = 'eb-log-message';
        msgSpan.textContent = entry.message || '';

        line.appendChild(levelSpan);
        line.appendChild(tsSpan);
        line.appendChild(msgSpan);
        liveLogsContainer.appendChild(line);
    });

    updateLogFooterOnly();
}

function normalizeLevel(level) {
    const l = (level || 'info').toLowerCase();
    if (l === 'warning') return 'warn';
    if (l === 'warn' || l === 'error' || l === 'debug' || l === 'ok' || l === 'info') return l;
    return 'info';
}

function updateLogFooterOnly() {
    const total = logEntries.length;
    const totalPages = Math.max(1, Math.ceil(total / LOG_PAGE_SIZE) || 1);
    if (logPage > totalPages) {
        logPage = totalPages;
    }

    const summary = document.getElementById('logFooterSummary');
    const cur = document.getElementById('logPageCurrent');
    const newer = document.getElementById('logPageNewer');
    const older = document.getElementById('logPageOlder');
    const wrap = document.getElementById('logPaginationWrap');

    const startIdx = (logPage - 1) * LOG_PAGE_SIZE;
    const showing = Math.min(LOG_PAGE_SIZE, Math.max(0, total - startIdx));

    if (summary) {
        if (logSearchQuery) {
            summary.textContent = 'Filtering visible page (' + showing + ' line' + (showing === 1 ? '' : 's') + ' shown)';
        } else if (total === 0) {
            summary.textContent = '0 lines';
        } else if (logPage === 1) {
            summary.textContent = 'Showing latest ' + showing + ' of ' + total + ' line' + (total === 1 ? '' : 's');
        } else {
            const from = startIdx + 1;
            const to = startIdx + showing;
            summary.textContent = 'Showing lines ' + from + '–' + to + ' of ' + total;
        }
    }

    if (cur) {
        cur.textContent = 'Page ' + logPage + ' / ' + totalPages;
    }
    if (newer) {
        newer.disabled = logPage <= 1;
    }
    if (older) {
        older.disabled = logPage >= totalPages || totalPages <= 1;
    }
    if (wrap) {
        wrap.style.display = total > LOG_PAGE_SIZE ? '' : 'none';
    }
}

function goLogPage(delta) {
    const totalPages = Math.max(1, Math.ceil(logEntries.length / LOG_PAGE_SIZE) || 1);
    const next = logPage + delta;
    if (next < 1 || next > totalPages) return;
    logPage = next;
    renderLogPage();
}

let processedLogHashes = new Set();

function setFormattedLogs(text) {
    const lines = (text || '').split(/\r?\n/).filter(Boolean);
    logEntries = lines
        .slice()
        .reverse()
        .map(line => ({
            level: 'info',
            ts: '',
            message: line
        }));
    processedLogHashes = new Set(logEntries.map(logEntryKey));
    logPage = 1;
    renderLogPage();
}

function setStructuredLogs(entries) {
    if (!Array.isArray(entries) || !entries.length) {
        logEntries = [];
        processedLogHashes.clear();
        logPage = 1;
        renderLogPage();
        return;
    }
    const list = entries.map(e => ({
        level: (e.level || 'info').toLowerCase(),
        ts: e.time || e.ts || '',
        message: e.msg || e.message || ''
    }));
    list.reverse();
    logEntries = [];
    processedLogHashes.clear();
    list.forEach(entry => {
        const key = logEntryKey(entry);
        if (processedLogHashes.has(key)) return;
        processedLogHashes.add(key);
        logEntries.push(entry);
    });
    trimLogStore();
    logPage = 1;
    renderLogPage();
}

function updateFormattedLogs() {
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_live_logs.php?run_uuid={$run.run_id}&ts=' + Date.now();
    if (lastLogsHash) {
        url += '&hash=' + encodeURIComponent(lastLogsHash);
    }
    fetch(url, { cache: 'no-store' })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success' && !d.unchanged) {
                if (Array.isArray(d.entries) && d.entries.length > 0) {
                    setStructuredLogs(d.entries);
                    lastLogsHash = d.hash || lastLogsHash;
                } else if (typeof d.formatted_log !== 'undefined') {
                    setFormattedLogs(d.formatted_log || '');
                    lastLogsHash = d.hash || lastLogsHash;
                }
            }
        })
        .catch(() => {});
}

let terminalEventSeen = false;

function updateEventLogs() {
    if (isPaused) return;
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_uuid={$run.run_id}&limit=500&ts=' + Date.now();
    if (lastEventId > 0) {
        url += '&since_id=' + encodeURIComponent(String(lastEventId));
    }
    fetch(url, { cache: 'no-store' })
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success' || !Array.isArray(d.events)) return;
            if (d.events.length === 0) return;

            const newEvents = [];
            d.events.forEach(ev => {
                if (terminalEventSeen) return;
                newEvents.push(ev);
                if (typeof ev.id === 'number' && ev.id > lastEventId) {
                    lastEventId = ev.id;
                }
                const evType = (ev.type || '').toLowerCase();
                if (['cancelled', 'summary'].includes(evType) || /backup cancelled/i.test(ev.message || '')) {
                    terminalEventSeen = true;
                }
            });

            newEvents.sort((a, b) => {
                const ida = typeof a.id === 'number' ? a.id : 0;
                const idb = typeof b.id === 'number' ? b.id : 0;
                return ida - idb;
            });

            newEvents.forEach(ev => {
                appendLogEntry({
                    id: ev.id || null,
                    level: ev.level || 'info',
                    ts: ev.ts || '',
                    message: ev.message || ''
                });
            });
        })
        .catch(() => {});
}

function clearLogs() {
    logEntries = [];
    pausedLogBuffer = [];
    processedLogHashes.clear();
    logPage = 1;
    lastLogsHash = null;
    lastEventId = 0;
    terminalEventSeen = false;
    renderLogPage();
}

function copyLogs() {
    const liveLogsContainer = document.getElementById('liveLogs');
    if (!liveLogsContainer) return;
    const text = Array.from(liveLogsContainer.querySelectorAll('.eb-log-line'))
        .map(row => {
            const lvl = row.querySelector('.eb-log-level');
            const ts = row.querySelector('.eb-log-timestamp');
            const msg = row.querySelector('.eb-log-message');
            return [lvl && lvl.textContent, ts && ts.textContent, msg && msg.textContent].filter(Boolean).join(' ').trim();
        })
        .join('\n')
        .trim();
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Logs copied to clipboard');
        }).catch(() => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }

    function fallbackCopy(value) {
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'absolute';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Logs copied to clipboard');
    }
}

function refreshErrorSummary(run) {
    if (!errorSummaryContainer || !errorSummaryText) {
        return;
    }
    const summary = (run.error_summary || '').trim();
    if (summary) {
        errorSummaryText.textContent = summary;
        errorSummaryContainer.classList.remove('hidden');
    } else {
        errorSummaryContainer.classList.add('hidden');
    }
    if (forceCancelButton) {
        const status = (run.status || '').toLowerCase();
        const shouldShowForce = summary && ['running', 'starting', 'queued'].includes(status);
        forceCancelButton.style.display = shouldShowForce ? '' : 'none';
    }
}

function formatDurationFromMs(ms) {
    const totalSeconds = Math.max(0, Math.floor(ms / 1000));
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const pad = value => String(value).padStart(2, '0');
    const base = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
    if (days > 0) {
        return days + 'd ' + base;
    }
    return base;
}

function ensureDurationStart(run) {
    if (durationStartMs) return;
    const parsed = parseRunTimestamp(run.started_at);
    durationStartMs = parsed || Date.now();
}

function startDurationTicker() {
    if (durationTimer) return;
    durationTimer = setInterval(() => {
        if (!durationStartMs) return;
        const durationValueEl = document.getElementById('durationValue');
        if (!durationValueEl) return;
        const elapsed = Math.max(0, Date.now() - durationStartMs);
        durationValueEl.textContent = formatDurationFromMs(elapsed);
    }, 1000);
}

function stopDurationTicker() {
    if (durationTimer) {
        clearInterval(durationTimer);
        durationTimer = null;
    }
}

function updateDuration(run) {
    const durationValueEl = document.getElementById('durationValue');
    const durationLabelEl = document.getElementById('durationStatLabel');
    if (!durationValueEl || !durationLabelEl) return;
    ensureDurationStart(run);
    const now = Date.now();
    const isTerminal = TERMINAL_STATUSES.includes(run.status);
    if (isTerminal) {
        if (!durationEndMs) {
            const finished = parseRunTimestamp(run.finished_at);
            durationEndMs = finished || now;
        }
        stopDurationTicker();
        durationLabelEl.textContent = 'Duration';
        const elapsed = Math.max(0, durationEndMs - durationStartMs);
        durationValueEl.textContent = formatDurationFromMs(elapsed);
    } else {
        durationEndMs = null;
        durationLabelEl.textContent = 'Elapsed';
        const elapsed = Math.max(0, now - durationStartMs);
        durationValueEl.textContent = formatDurationFromMs(elapsed);
        startDurationTicker();
    }
}

function toTitleCase(text) {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function updateStageLabel(run) {
    const stageEl = document.getElementById('stageLabel');
    if (!stageEl) return;
    const fallback = STAGE_FALLBACKS[run.status] || toTitleCase(run.status || '');
    stageEl.textContent = run.stage || fallback || 'Pending';
    const cfg = STATUS_CONFIGS[run.status];
    if (cfg) {
        stageEl.style.color = cfg.stageColor;
    }
}

function updateStatusDisplay(statusConfig) {
    const badgeEl = document.getElementById('liveHeaderBadge');
    const stageDot = document.getElementById('stageStatusDot');
    if (badgeEl) {
        badgeEl.className = statusConfig.badge || 'eb-badge eb-badge--neutral eb-badge--dot';
        badgeEl.textContent = statusConfig.text;
    }
    if (stageDot) {
        const d = statusConfig.dot || 'inactive';
        stageDot.className = 'eb-status-dot eb-status-dot--' + (d === 'pending' ? 'pending' : d === 'active' ? 'active' : d === 'error' ? 'error' : d === 'warning' ? 'warning' : 'inactive');
    }
}

(function applyInitialStatusBadge() {
    const cfg = STATUS_CONFIGS['{$run.status}'];
    if (cfg) {
        updateStatusDisplay(cfg);
    }
})();

{if $run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued'}
    clearLogs();
    syncLogPanelChrome(true);
    updateProgress();
    updateEventLogs();
    progressInterval = setInterval(updateProgress, 2000);
    eventsInterval = setInterval(updateEventLogs, 2000);
{else}
    clearLogs();
    syncLogPanelChrome(false);
    updateProgress();
    updateFormattedLogs();
{/if}
</script>
