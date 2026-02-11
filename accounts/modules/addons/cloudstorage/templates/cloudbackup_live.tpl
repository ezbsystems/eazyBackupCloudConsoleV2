<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">
        {assign var="activeNav" value="jobs"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}
        <!-- Glass panel container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">

        <div class="space-y-8">
            <!-- Identity strip -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <a href="index.php?m=cloudstorage&page=e3backup&view=runs&job_id={$job.id}" class="text-sky-400 hover:text-sky-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{if $is_restore}Live Restore{else}Live Backup{/if}</p>
                        <h1 class="text-2xl sm:text-3xl font-semibold text-white">
                            {if $job.name}{$job.name}{else}Job #{$job.id}{/if}
                        </h1>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900/70 px-3 py-1 text-xs text-slate-200">
                        <span id="statusTopDot" class="h-2.5 w-2.5 rounded-full status-dot bg-sky-500 shadow-[0_0_8px_rgba(59,130,246,0.6)] animate-status-pulse"></span>
                        <span id="statusTopText" class="font-semibold">{$run.status|ucfirst}</span>
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-800 bg-slate-900/60 px-3 py-1 text-xs text-slate-200">
                        <span class="text-slate-400">Agent:</span>
                        <span class="text-slate-100">
                            {if $agent_name}{$agent_name}{elseif $agent_id}Agent #{$agent_id}{else}Agent unavailable{/if}
                        </span>
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-800 bg-slate-900/60 px-3 py-1 text-xs text-slate-200">
                        <span class="text-slate-400">Job:</span>
                        <span class="text-slate-100">{if $job.name}{$job.name}{else}Job #{$job.id}{/if}</span>
                    </span>
                    <button
                        id="cancelButton"
                        onclick="cancelRun({$run.id})"
                        class="hidden rounded-full border border-rose-500/60 bg-rose-500/10 px-3 py-1 text-xs text-rose-200 hover:border-rose-400 hover:text-white transition-colors"
                        style="display: none;"
                    >
                        Cancel Run
                    </button>
                </div>
            </div>

            <!-- Progress hero -->
            {assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
            <div class="rounded-3xl border border-slate-800/80 bg-slate-900/60 px-6 py-6 shadow-[0_20px_60px_rgba(0,0,0,0.45)]" x-data="{ isRunning: {if $isRunningStatus}true{else}false{/if} }">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Progress</p>
                        <p class="text-5xl sm:text-6xl font-semibold text-white" id="progressPercent">
                            {if $run.progress_pct}
                                {$run.progress_pct|string_format:"%.2f"}%
                            {else}
                                0.00%
                            {/if}
                        </p>
                    </div>
                    <div class="text-left lg:text-right">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Stage</p>
                        <p id="stageLabel" class="text-lg sm:text-xl font-semibold text-white">
                            {if $run.stage}{$run.stage}{else}{$run.status|ucfirst}{/if}
                        </p>
                    </div>
                </div>
                <div class="mt-6 h-4 sm:h-5 rounded-full bg-slate-800/80 relative overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500 ease-out bg-gradient-to-r from-sky-500 to-sky-400 relative"
                        id="progressBar"
                        style="width: {if $run.progress_pct}{$run.progress_pct}{else}0{/if}%"
                        :class="{ 'animate-pulse': isRunning }"
                        role="progressbar"
                        aria-label="Backup progress"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{if $run.progress_pct}{$run.progress_pct|string_format:"%.2f"}{else}0.00{/if}"
                    >
                        <div class="absolute inset-0 progress-stripes" x-show="isRunning"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer"
                             style="animation: shimmer 2s infinite;"
                             x-show="isRunning"></div>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-4 text-xs text-slate-400">
                    <div class="flex items-center gap-2">
                        <span class="uppercase tracking-wide text-slate-500">Status</span>
                        <span id="statusMicroDot" class="h-2 w-2 rounded-full bg-sky-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]"></span>
                        <span id="statusMicroText" class="text-slate-200 font-semibold">{$run.status|ucfirst}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="uppercase tracking-wide text-slate-500">ETA</span>
                        <span class="text-slate-200 font-semibold" id="etaTop">
                            {if $run.eta_seconds}
                                {assign var="hours" value=$run.eta_seconds/3600|floor}
                                {assign var="minutes" value=($run.eta_seconds%3600)/60|floor}
                                {assign var="seconds" value=$run.eta_seconds%60|string_format:"%.0f"}
                                {if $hours > 0}{$hours}h {/if}{if $minutes > 0}{$minutes}m {/if}{$seconds}s
                            {else}
                                -
                            {/if}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="uppercase tracking-wide text-slate-500">Duration</span>
                        <span class="text-slate-200 font-semibold" id="durationValue">-</span>
                        <span id="durationLabel" class="text-slate-500">Elapsed</span>
                    </div>
                </div>
            </div>

            <!-- Telemetry cards -->
            {assign var="showFilesMetric" value=(!$is_restore && $job.engine ne 'disk_image' && $job.engine ne 'hyperv')}
            <div class="grid gap-4 sm:grid-cols-2 xl:{if $showFilesMetric}grid-cols-4{else}grid-cols-3{/if}">
                <div class="rounded-2xl border border-slate-800/80 bg-slate-950/70 px-4 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Speed</p>
                    <p class="mt-2 text-2xl font-semibold text-white" id="speedValue">
                        {if $run.speed_bytes_per_sec}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.speed_bytes_per_sec)}/s
                        {else}
                            -
                        {/if}
                    </p>
                    <p id="speedHint" class="mt-1 text-xs text-slate-500"></p>
                </div>
                <div class="rounded-2xl border border-slate-800/80 bg-slate-950/70 px-4 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Processed</p>
                    <p class="mt-2 text-2xl font-semibold text-white" id="bytesProcessedValue">
                        {if $run.bytes_processed}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_processed)}
                        {elseif $run.bytes_transferred}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                        {else}
                            0.00 Bytes
                        {/if}
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-800/80 bg-slate-950/70 px-4 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Uploaded</p>
                    <p class="mt-2 text-2xl font-semibold text-white" id="bytesTransferredValue">
                        {if $run.bytes_transferred}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                        {else}
                            0.00 Bytes
                        {/if}
                    </p>
                    <p id="uploadedSavings" class="mt-1 text-xs text-slate-500"></p>
                </div>
                {if $showFilesMetric}
                <div class="rounded-2xl border border-slate-800/80 bg-slate-950/70 px-4 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Files & Folders</p>
                    <div class="mt-3 space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Files</span>
                            <span class="text-white font-semibold" id="filesValue">-</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-400">Folders</span>
                            <span class="text-slate-200 font-semibold" id="foldersValue">—</span>
                        </div>
                    </div>
                </div>
                {/if}
            </div>

            <div id="errorSummaryContainer" class="hidden rounded-2xl border border-rose-500/50 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="status" aria-live="polite">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-300">Startup Error</p>
                <p id="errorSummaryText" class="mt-1 text-sm font-semibold text-rose-100"></p>
            </div>

            <!-- Lower section -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-800/80 bg-slate-950/70 px-5 py-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Now backing up</p>
                            <p class="text-sm text-slate-400">Current file</p>
                        </div>
                        <button
                            type="button"
                            onclick="copyCurrentFile()"
                            class="rounded-full border border-slate-700 bg-slate-900/60 px-3 py-1 text-xs text-slate-300 hover:border-slate-500 hover:text-white transition-colors"
                        >
                            Copy
                        </button>
                    </div>
                    <div class="mt-4 rounded-lg border border-slate-800 bg-slate-900/60 px-3 py-3">
                        <div id="currentItem" class="text-xs text-slate-200 font-mono break-words">
                            {if $run.current_item}{$run.current_item}{else}-{/if}
                        </div>
                        <div id="currentItemEmpty" class="text-xs text-slate-500 italic {if $run.current_item}hidden{/if}">
                            Waiting for file updates...
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-2 rounded-2xl border border-slate-800/80 bg-slate-950/70 px-5 py-5">
                    <div x-data="{ tab: 'logs' }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-sm">
                                <button type="button"
                                        class="px-3 py-1 rounded-full border transition-colors"
                                        :class="tab === 'logs' ? 'border-sky-500/60 bg-sky-500/10 text-sky-200' : 'border-slate-700 text-slate-300 hover:border-slate-500 hover:text-white'"
                                        @click="tab = 'logs'">
                                    Live Logs
                                </button>
                                <button type="button"
                                        class="px-3 py-1 rounded-full border transition-colors"
                                        :class="tab === 'details' ? 'border-sky-500/60 bg-sky-500/10 text-sky-200' : 'border-slate-700 text-slate-300 hover:border-slate-500 hover:text-white'"
                                        @click="tab = 'details'">
                                    Details
                                </button>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400" x-show="tab === 'logs'" x-cloak>
                                <div class="relative">
                                    <input id="logSearchInput" type="text" placeholder="Search logs"
                                           oninput="setLogSearch(this.value)"
                                           class="rounded-full bg-slate-900/70 border border-slate-700 px-3 py-1 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                                </div>
                                <button type="button"
                                        id="pauseUpdatesBtn"
                                        onclick="togglePauseUpdates()"
                                        class="px-3 py-1 rounded-full border border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500 hover:text-white transition-colors">
                                    Pause
                                </button>
                                <button type="button"
                                        onclick="toggleAutoScrollLogs()"
                                        class="px-3 py-1 rounded-full border border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500 hover:text-white transition-colors">
                                    Autoscroll: <span id="autoScrollLabel">On</span>
                                </button>
                                <button type="button"
                                        onclick="copyLogs()"
                                        class="px-3 py-1 rounded-full border border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500 hover:text-white transition-colors">
                                    Copy
                                </button>
                                <button type="button"
                                        onclick="clearLogs()"
                                        class="px-3 py-1 rounded-full border border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500 hover:text-white transition-colors">
                                    Clear
                                </button>
                                <button
                                    id="forceCancelButton"
                                    type="button"
                                    onclick="cancelRun({$run.id}, true)"
                                    class="hidden px-3 py-1 rounded-full border border-rose-500 bg-rose-500/10 text-rose-200 hover:border-rose-400 hover:text-white transition-colors"
                                >
                                    Force Cancel
                                </button>
                            </div>
                        </div>

                        <div class="mt-4" x-show="tab === 'logs'" x-cloak>
                            <div class="bg-slate-950/80 rounded-lg border border-slate-800 p-4 max-h-[420px] overflow-y-auto font-mono text-xs text-slate-300" id="liveLogs">
                                <div class="text-slate-500 italic" id="liveLogsEmpty">
                                    Waiting for log data...
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-4" x-show="tab === 'details'" x-cloak>
                            <div class="grid gap-3 sm:grid-cols-2 text-sm text-slate-400">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Status</span>
                                    <span id="detailsStatus" class="text-white font-semibold text-xs">{$run.status|ucfirst}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Agent</span>
                                    <span id="detailsAgent" class="text-slate-100 text-xs">
                                        {if $agent_name}{$agent_name}{elseif $agent_id}Agent #{$agent_id}{else}Agent unavailable{/if}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Job</span>
                                    <span id="detailsJob" class="text-slate-100 text-xs truncate">{if $job.name}{$job.name}{else}Job #{$job.id}{/if}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Run ID</span>
                                    <span id="detailsRunId" class="font-mono text-slate-100 text-xs">{$run.id}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Run UUID</span>
                                    <span id="detailsRunUuid" class="font-mono text-slate-100 text-xs break-all">{$run.run_uuid|default:$run.id}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Started</span>
                                    <span id="detailsStartedAt" class="text-slate-100 text-xs">{$run.started_at|default:'-'}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Finished</span>
                                    <span id="detailsFinishedAt" class="text-slate-100 text-xs">{$run.finished_at|default:'-'}</span>
                                </div>
                            </div>
                            {if $is_restore && $restore_metadata}
                            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-100">
                                {if $is_hyperv_restore}
                                    <p class="font-semibold uppercase tracking-wide text-emerald-200">Hyper-V Restore</p>
                                    <p class="mt-1">VM <span class="font-semibold">{$restore_metadata.vm_name}</span> to <span class="font-semibold">{$restore_metadata.target_path}</span></p>
                                {else}
                                    <p class="font-semibold uppercase tracking-wide text-emerald-200">Restore</p>
                                    <p class="mt-1">Snapshot {$restore_metadata.manifest_id|truncate:16:'...'} to {$restore_metadata.target_path}</p>
                                {/if}
                            </div>
                            {/if}
                            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-100">
                                <p class="font-semibold uppercase tracking-wide text-amber-200">Cloud Backup (Beta)</p>
                                <p class="mt-1">Cloud Backup is in beta. Keep a primary backup strategy in place and contact support if you notice any issues.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
.animate-shimmer {
    animation: shimmer 2s infinite;
}

@keyframes stripes {
    0% { background-position: 0 0; }
    100% { background-position: 40px 0; }
}
.progress-stripes {
    background-image: linear-gradient(
        45deg,
        rgba(255,255,255,0.12) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255,255,255,0.12) 50%,
        rgba(255,255,255,0.12) 75%,
        transparent 75%,
        transparent
    );
    background-size: 40px 40px;
    animation: stripes 1s linear infinite;
}

@keyframes pulse-glow {
    0%, 100% {
        opacity: 0.5;
    }
    50% {
        opacity: 0.8;
    }
}

.progress-bar-glow {
    animation: pulse-glow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
.log-line { word-break: break-word; }
.log-badge { display:inline-flex; align-items:center; gap:.25rem; border-radius:.375rem; padding:.125rem .375rem; font-size:.675rem; font-weight:600; }
.log-badge-error { background-color: rgba(244,63,94,0.15); color: #fecaca; }   /* rose */
.log-badge-warn { background-color: rgba(245,158,11,0.15); color: #fde68a; }    /* amber */
.log-badge-info { background-color: rgba(148,163,184,0.15); color: #cbd5e1; }   /* slate */
.log-badge-ok { background-color: rgba(16,185,129,0.1); color: #a7f3d0; }       /* emerald */
.status-dot {
    transition: transform 0.2s ease;
}
.animate-status-pulse {
    animation: statusPulse 1.8s ease-in-out infinite;
}
@keyframes statusPulse {
    0% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.35); opacity: 0.45; }
    100% { transform: scale(1); opacity: 0.8; }
}
@media (prefers-reduced-motion: reduce) {
    .animate-status-pulse {
        animation: none;
    }
}
</style>

<script>
let progressInterval;
let logsInterval;
let eventsInterval;
{assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
let isRunning = {if $isRunningStatus}true{else}false{/if};
const RUN_UUID = '{$run.run_uuid|default:""}';
let lastLogsHash = null;
let lastEventId = 0;
const errorSummaryContainer = document.getElementById('errorSummaryContainer');
const errorSummaryText = document.getElementById('errorSummaryText');
const forceCancelButton = document.getElementById('forceCancelButton');
const STATUS_CONFIGS = {
    'success': { text: 'Success', color: 'green', pulse: false },
    'failed': { text: 'Failed', color: 'red', pulse: false },
    'running': { text: 'Running', color: 'blue', pulse: true },
    'starting': { text: 'Starting', color: 'blue', pulse: true },
    'queued': { text: 'Queued', color: 'yellow', pulse: true },
    'warning': { text: 'Warning', color: 'yellow', pulse: false },
    'cancelled': { text: 'Cancelled', color: 'gray', pulse: false },
    'partial_success': { text: 'Partial Success', color: 'yellow', pulse: false }
};
const STATUS_DOT_CLASSES = {
    'green': 'bg-green-500',
    'red': 'bg-red-500',
    'blue': 'bg-blue-500',
    'yellow': 'bg-yellow-500',
    'gray': 'bg-gray-500'
};
const STATUS_GLOW_COLORS = {
    'green': 'rgba(34, 197, 94, 0.6)',
    'red': 'rgba(239, 68, 68, 0.6)',
    'blue': 'rgba(59, 130, 246, 0.6)',
    'yellow': 'rgba(234, 179, 8, 0.6)',
    'gray': 'rgba(107, 114, 128, 0.6)'
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
let autoScrollLogs = true;
let isPaused = false;
let logEntries = [];
let logSearchQuery = '';
const MAX_LOG_LINES = 800;

// Smoothly tween the bar to a target percentage
let currentPct = (() => {
    const label = document.getElementById('progressPercent');
    const txt = (label && label.textContent ? label.textContent.replace('%','') : '0') || '0';
    return parseFloat(txt) || 0;
})();
let tweenId;
function smoothProgressTo(targetPct, duration = 600) {
    if (tweenId) cancelAnimationFrame(tweenId);
    const bar = document.getElementById('progressBar');
    const label = document.getElementById('progressPercent');
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
            label.textContent = v.toFixed(2) + '%';
        }
        if (t < 1) tweenId = requestAnimationFrame(step);
    })(start);
}

// ETA-based progress fallback model (monotonic, time-based)
const etaModel = {
    startMs: null,              // epoch ms when run started
    predictedTotalSec: null     // non-decreasing predicted total duration (sec)
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

// Initialize cancel button visibility on page load
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

function updateProgress() {
    if (isPaused) return;
    const ts = Date.now();
    fetch('modules/addons/cloudstorage/api/cloudbackup_progress.php?run_uuid={$run.run_uuid|default:$run.id}&ts=' + ts, { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.run) {
                const run = data.run;
                refreshErrorSummary(run);
                
                // Compute progress percentage with sensible fallbacks
                // Use bytes_processed (scanned from source) for progress, not bytes_transferred (uploaded)
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
                    // Time-based fallback using ETA (monotonic)
                    // Seed start time from server if available
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
                            // Cap below 100 to avoid showing completion prematurely; success handler will set 100%
                            progressPct = Math.min(99.0, (elapsedSec / etaModel.predictedTotalSec) * 100);
                        }
                    }
                }
                const progressBar = document.getElementById('progressBar');
                const progressPercent = document.getElementById('progressPercent');

                // Update the main percent display
                if (progressPercent) {
                    const pctText = (progressPct > 0 ? progressPct : 0).toFixed(2) + '%';
                    progressPercent.textContent = pctText;
                }

                const isFinished = ['success','failed','cancelled','warning','partial_success'].includes(run.status);

                if (!isFinished) {
                    // Running state
                    if (progressPct > 0.01) {
                        // Determinate mode (real percentage available)
                        // Ensure running gradient
                        if (progressBar) {
                            progressBar.classList.remove('from-emerald-500','to-emerald-400','from-slate-600/40','via-slate-500/40','to-slate-600/40');
                            progressBar.classList.add('from-sky-500','to-sky-400');
                        }
                        smoothProgressTo(progressPct);
                        if (progressBar) {
                            // brief glow on update
                            progressBar.style.boxShadow = '0 0 10px rgba(56, 189, 248, 0.5)';
                            setTimeout(() => { if (progressBar) progressBar.style.boxShadow = ''; }, 300);
                        }
                    } else {
                        // Indeterminate mode (no percentage available) - show full-width pulsing bar
                        if (progressBar) {
                            progressBar.style.width = '100%';
                            progressBar.setAttribute('aria-valuenow', '0.00');
                            progressBar.classList.remove('from-sky-500','to-sky-400','from-emerald-500','to-emerald-400');
                            progressBar.classList.add('from-slate-600/40','via-slate-500/40','to-slate-600/40');
                        }
                        if (progressPercent) {
                            progressPercent.textContent = progressPct.toFixed(2) + '%';
                        }
                    }
                } else {
                    // On completion, ensure final visual state
                    if (run.status === 'success') {
                        smoothProgressTo(100, 800);
                        if (progressBar) {
                            progressBar.classList.remove('animate-pulse', 'progress-stripes', 'from-slate-600/40','via-slate-500/40','to-slate-600/40');
                            // shift to success gradient
                            progressBar.classList.remove('from-sky-500','to-sky-400');
                            progressBar.classList.add('from-emerald-500','to-emerald-400');
                            progressBar.style.boxShadow = '0 0 10px rgba(16, 185, 129, 0.5)';
                            setTimeout(() => { if (progressBar) progressBar.style.boxShadow = ''; }, 400);
                        }
                    } else {
                        // Stop animations for non-success terminal states
                        if (progressBar) {
                            progressBar.classList.remove('animate-pulse', 'progress-stripes', 'from-slate-600/40','via-slate-500/40','to-slate-600/40');
                        }
                    }
                }
                
                // Logs are rendered exclusively from the sanitized event stream (see updateEventLogs).
                
                // Update telemetry tiles
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
                    speedValueEl.textContent = run.speed_bytes_per_sec ? (formatBytes(run.speed_bytes_per_sec) + '/s') : '-';
                }
                const speedHintEl = document.getElementById('speedHint');
                if (speedHintEl) {
                    speedHintEl.textContent = run.speed_bytes_per_sec ? 'Instantaneous' : '';
                }

                const filesValueEl = document.getElementById('filesValue');
                const foldersValueEl = document.getElementById('foldersValue');
                if (filesValueEl) {
                    const filesDone = (run.files_done !== undefined && run.files_done !== null)
                        ? run.files_done
                        : (run.objects_transferred || 0);
                    const filesTotal = (run.files_total !== undefined && run.files_total !== null)
                        ? run.files_total
                        : (run.objects_total || 0);
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
                
                // Update ETA values (top card and panel) with integer seconds
                const etaTopEl = document.getElementById('etaTop');
                if (isFinished) {
                    if (etaTopEl) etaTopEl.textContent = '-';
                } else if (run.eta_seconds !== undefined && run.eta_seconds !== null) {
                    if (etaTopEl) {
                        etaTopEl.textContent = formatEta(run.eta_seconds);
                        etaTopEl.classList.add('opacity-0');
                        setTimeout(() => etaTopEl.classList.remove('opacity-0'), 50);
                    }
                }
                
                // Update current item
                const currentItemEl = document.getElementById('currentItem');
                const currentItemEmpty = document.getElementById('currentItemEmpty');
                if (currentItemEl) {
                    if (run.current_item) {
                        currentItemEl.textContent = run.current_item;
                        if (currentItemEmpty) currentItemEmpty.classList.add('hidden');
                    } else {
                        currentItemEl.textContent = '-';
                        if (currentItemEmpty) currentItemEmpty.classList.remove('hidden');
                    }
                }

                const detailsStarted = document.getElementById('detailsStartedAt');
                if (detailsStarted && run.started_at) detailsStarted.textContent = run.started_at;
                const detailsFinished = document.getElementById('detailsFinishedAt');
                if (detailsFinished) detailsFinished.textContent = run.finished_at || '-';
                
                const statusConfig = STATUS_CONFIGS[run.status] || {
                    text: run.status ? (run.status.charAt(0).toUpperCase() + run.status.slice(1)) : 'Unknown',
                    color: 'gray',
                    pulse: false
                };
                updateStatusDisplay(statusConfig);
                updateStageLabel(run);
                updateDuration(run);
                
                // Update cancel button visibility
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
                
                // Update isRunning state for animations
                const container = document.querySelector('[x-data*="isRunning"]');
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
                
                // Stop polling if finished
                if (['success', 'failed', 'cancelled', 'warning', 'partial_success'].includes(run.status)) {
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
                    
                    // If status changed to success, log it
                    if (run.status === 'success') {
                        console.log('Backup completed successfully');
                    }
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
    if (s <= 0) return '-';
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    let out = '';
    if (h > 0) out += h + 'h ';
    if (m > 0) out += m + 'm ';
    out += sec + 's';
    return out.trim();
}

function setLogSearch(value) {
    logSearchQuery = (value || '').toLowerCase().trim();
    renderLogEntries();
}

function togglePauseUpdates() {
    isPaused = !isPaused;
    const btn = document.getElementById('pauseUpdatesBtn');
    if (btn) btn.textContent = isPaused ? 'Resume' : 'Pause';
    if (!isPaused) {
        updateProgress();
        updateEventLogs();
    }
}

function appendLogEntry(entry) {
    logEntries.push(entry);
    if (logEntries.length > MAX_LOG_LINES) {
        logEntries = logEntries.slice(logEntries.length - MAX_LOG_LINES);
    }
}

function renderLogEntries() {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    if (!liveLogsContainer) return;
    liveLogsContainer.removeAttribute('data-formatted');
    while (liveLogsContainer.firstChild) liveLogsContainer.removeChild(liveLogsContainer.firstChild);

    let filtered = logEntries;
    if (logSearchQuery) {
        filtered = logEntries.filter(entry => {
            const hay = (entry.message || '') + ' ' + (entry.level || '') + ' ' + (entry.ts || '');
            return hay.toLowerCase().includes(logSearchQuery);
        });
    }
    if (!filtered.length) {
        if (liveLogsEmpty) liveLogsEmpty.style.display = 'block';
        return;
    }
    if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';

    filtered.forEach(entry => {
        const line = document.createElement('div');
        line.className = 'log-line mb-1';
        const badge = document.createElement('span');
        badge.className = 'log-badge ' + (
            entry.level === 'error' ? 'log-badge-error' :
            entry.level === 'warn' ? 'log-badge-warn' :
            entry.level === 'ok' ? 'log-badge-ok' : 'log-badge-info'
        );
        badge.textContent = (entry.level || 'info').toUpperCase();
        const text = document.createElement('span');
        const ts = entry.ts ? '[' + entry.ts + '] ' : '';
        text.textContent = ts + (entry.message || '');
        text.style.marginLeft = '.5rem';
        line.appendChild(badge);
        line.appendChild(text);
        liveLogsContainer.appendChild(line);
    });
    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function copyCurrentFile() {
    const currentItemEl = document.getElementById('currentItem');
    if (!currentItemEl) return;
    const text = (currentItemEl.textContent || '').trim();
    if (!text || text === '-') return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'absolute';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
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
    const durationLabelEl = document.getElementById('durationLabel');
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
}

function updateStatusDisplay(statusConfig) {
    const statusTextEl = document.getElementById('statusTopText');
    const statusDotEl = document.getElementById('statusTopDot');
    const microTextEl = document.getElementById('statusMicroText');
    const microDotEl = document.getElementById('statusMicroDot');
    const detailsStatusEl = document.getElementById('detailsStatus');
    if (statusTextEl) {
        statusTextEl.textContent = statusConfig.text;
    }
    if (detailsStatusEl) {
        detailsStatusEl.textContent = statusConfig.text;
    }
    if (microTextEl) {
        microTextEl.textContent = statusConfig.text;
    }
    if (!statusDotEl) return;
    statusDotEl.className = 'h-3 w-3 rounded-full status-dot';
    const colorClass = STATUS_DOT_CLASSES[statusConfig.color] || STATUS_DOT_CLASSES.gray;
    statusDotEl.classList.add(colorClass);
    if (statusConfig.pulse) {
        statusDotEl.classList.add('animate-status-pulse');
    } else {
        statusDotEl.classList.remove('animate-status-pulse');
    }
    const glowColor = STATUS_GLOW_COLORS[statusConfig.color] || STATUS_GLOW_COLORS.gray;
    statusDotEl.style.boxShadow = '0 0 8px ' + glowColor;
    if (microDotEl) {
        microDotEl.className = 'h-2 w-2 rounded-full';
        microDotEl.classList.add(colorClass);
        microDotEl.style.boxShadow = '0 0 6px ' + glowColor;
    }
}

// Track processed log entries to prevent duplicates
let processedLogHashes = new Set();

function updateLiveLogs(logLines) {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    
    if (!liveLogsContainer) return;

    // If formatted logs are in use (we set textContent wholesale), skip incremental appends
    if (liveLogsContainer.getAttribute('data-formatted') === '1') {
        return;
    }
    
    // Remove empty state message if logs exist
    if (logLines && logLines.length > 0 && liveLogsEmpty) {
        liveLogsEmpty.style.display = 'none';
    }
    
    if (!logLines || logLines.length === 0) {
        return;
    }
    
    // Process each log line
    logLines.forEach((logEntry, index) => {
        if (!logEntry || typeof logEntry !== 'object') return;
        
        // Create a hash for this log entry to prevent duplicates
        const logHash = JSON.stringify(logEntry);
        if (processedLogHashes.has(logHash)) {
            return; // Skip duplicate
        }
        processedLogHashes.add(logHash);
        
        // Extract log data
        const msg = logEntry.msg || logEntry.message || '';
        const time = logEntry.time || '';
        const level = (logEntry.level || 'info').toLowerCase();
        
        // Format timestamp
        let timestamp = '';
        if (time) {
            try {
                const date = new Date(time);
                timestamp = date.toLocaleTimeString();
            } catch (e) {
                timestamp = time;
            }
        }
        
        // Format message - convert rclone messages to user-friendly format
        let formattedMsg = msg;
        const replacements = [
            [/Starting sync/i, '🔄 Starting backup'],
            [/Starting copy/i, '🔄 Starting copy'],
            [/There was nothing to transfer/i, '✅ No files to transfer'],
            [/nothing to transfer/i, '✅ No files to transfer'],
            [/Completed sync/i, '✅ Backup completed'],
            [/Transferred:/i, '📤 Transferred:'],
            [/error/i, '❌ Error'],
            [/failed/i, '❌ Failed'],
        ];
        
        replacements.forEach(([pattern, replacement]) => {
            formattedMsg = formattedMsg.replace(pattern, replacement);
        });
        
        // Determine log entry color based on level
        let logColor = 'text-slate-300';
        if (level === 'error') {
            logColor = 'text-red-400';
        } else if (level === 'warning') {
            logColor = 'text-yellow-400';
        } else if (formattedMsg.includes('✅') || formattedMsg.includes('Success')) {
            logColor = 'text-green-400';
        } else if (formattedMsg.includes('🔄') || formattedMsg.includes('📤')) {
            logColor = 'text-blue-400';
        }
        
        // Create log entry element
        const logEntryEl = document.createElement('div');
        logEntryEl.className = 'mb-1 ' + logColor;
        logEntryEl.style.wordBreak = 'break-word';
        
        let logText = '';
        if (timestamp) {
            logText = '[' + timestamp + '] ';
        }
        logText += formattedMsg;
        
        logEntryEl.textContent = logText;
        
        // Append to container
        liveLogsContainer.appendChild(logEntryEl);
    });
    
    // Auto-scroll to bottom when enabled
    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function setFormattedLogs(text) {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    if (!liveLogsContainer) return;
    liveLogsContainer.setAttribute('data-formatted', '1');
    if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';
    // Replace entire content with server-formatted text
    liveLogsContainer.textContent = text || '';
    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function setStructuredLogs(entries) {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    if (!liveLogsContainer) return;
    liveLogsContainer.removeAttribute('data-formatted');
    if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';

    // Replace entire content
    while (liveLogsContainer.firstChild) liveLogsContainer.removeChild(liveLogsContainer.firstChild);

    entries.forEach(e => {
        const line = document.createElement('div');
        line.className = 'log-line mb-1';

        const time = e.time ? '[' + e.time + '] ' : '';
        const badge = document.createElement('span');
        badge.className = 'log-badge ' + (
            e.level === 'error' ? 'log-badge-error' :
            e.level === 'warn' ? 'log-badge-warn' :
            e.level === 'ok' ? 'log-badge-ok' : 'log-badge-info'
        );
        badge.textContent = (e.level || 'info').toUpperCase();

        const text = document.createElement('span');
        text.textContent = (time + (e.message || ''));
        text.style.marginLeft = '.5rem';

        line.appendChild(badge);
        line.appendChild(text);
        liveLogsContainer.appendChild(line);
    });

    if (autoScrollLogs) {
        liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
    }
}

function updateFormattedLogs() {
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_live_logs.php?run_uuid={$run.run_uuid|default:$run.id}&ts=' + Date.now();
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

// Prefer sanitized event stream over logs when available
let terminalEventSeen = false;
function updateEventLogs() {
    if (isPaused) return;
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_uuid={$run.run_uuid|default:$run.id}&limit=500&ts=' + Date.now();
    if (lastEventId > 0) {
        url += '&since_id=' + encodeURIComponent(String(lastEventId));
    }
    fetch(url, { cache: 'no-store' })
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success' || !Array.isArray(d.events)) return;
            if (d.events.length === 0) return;
            d.events.forEach(ev => {
                // Stop appending once a terminal event is seen (avoid repeated "Backup cancelled.")
                if (terminalEventSeen) return;
                appendLogEntry({
                    id: ev.id || null,
                    level: ev.level || 'info',
                    ts: ev.ts || '',
                    message: ev.message || ''
                });
                if (typeof ev.id === 'number' && ev.id > lastEventId) {
                    lastEventId = ev.id;
                }
                // Mark terminal when we encounter cancelled/success/failed/warning
                const evType = (ev.type || '').toLowerCase();
                if (['cancelled','summary'].includes(evType) || /backup cancelled/i.test(ev.message || '')) {
                    terminalEventSeen = true;
                }
            });
            renderLogEntries();
        })
        .catch(() => {});
}

function clearLogs() {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    
    if (liveLogsContainer) {
        // Remove all log entries except the empty state message
        while (liveLogsContainer.firstChild) {
            liveLogsContainer.removeChild(liveLogsContainer.firstChild);
        }
        
        // Show empty state
        if (liveLogsEmpty) {
            liveLogsEmpty.style.display = 'block';
        }
    }
    
    // Clear processed hashes
    processedLogHashes.clear();
    logEntries = [];
}

function toggleAutoScrollLogs() {
    autoScrollLogs = !autoScrollLogs;
    const label = document.getElementById('autoScrollLabel');
    if (label) {
        label.textContent = autoScrollLogs ? 'On' : 'Off';
    }
}

function copyLogs() {
    const liveLogsContainer = document.getElementById('liveLogs');
    if (!liveLogsContainer) return;
    const text = Array.from(liveLogsContainer.children)
        .map(child => child.textContent || '')
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

function cancelRun(runId, force = false) {
    const promptText = force
        ? 'The run appears stuck. Force cancellation to clear it?'
        : 'Are you sure you want to cancel this run?';
    if (!confirm(promptText)) {
        return;
    }
    
    const btn = document.getElementById('cancelButton');
    if (btn) {
        btn.disabled = true;
        btn.textContent = force ? 'Force cancel requested...' : 'Cancel requested...';
    }
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_cancel_run.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([
            ['run_id', runId],
            ...(RUN_UUID ? [['run_uuid', RUN_UUID]] : []),
            ['force', force ? '1' : '0'],
        ])
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Run cancellation requested');
            updateProgress();
        } else {
            alert(data.message || 'Failed to cancel run');
            if (btn) { btn.disabled = false; btn.textContent = 'Cancel Run'; }
        }
    });
}

// Start polling if run is still active - update every 2 seconds
// Also poll if status is queued/starting/running to catch quick completions
{if $run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued'}
    // Initial update immediately to catch any status changes
    updateProgress();
    // Fetch sanitized events
    updateEventLogs();
    // Then poll every 2 seconds
    progressInterval = setInterval(updateProgress, 2000);
    // Poll events every 2 seconds
    eventsInterval = setInterval(updateEventLogs, 2000);
{else}
    // Even if status appears finished, do one check to ensure it's up to date
    // This handles cases where status changed between page load and script execution
    updateProgress();
{/if}
</script>

