<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">
        {assign var="activeNav" value="jobs"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}
        <!-- Glass panel container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">

        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-3">
            <div class="flex items-center">
                <a href="index.php?m=cloudstorage&page=e3backup&view=runs&job_id={$job.id}" class="mr-4 text-sky-400 hover:text-sky-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h2 class="text-2xl font-semibold text-white flex items-center gap-2">
                    {if $is_restore}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Restore Progress: {$job.name}</span>
                        <span class="inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-emerald-200 border border-emerald-400/40">
                            Restore
                        </span>
                    {else}
                        <span>Live Progress: {$job.name}</span>
                    {/if}
                    <span class="inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-amber-200 border border-amber-400/40">
                        Beta
                    </span>
                </h2>
            </div>
            <button
                id="cancelButton"
                onclick="cancelRun({$run.id})"
                class="bg-red-700 hover:bg-red-600 text-white px-4 py-2 rounded-md hidden"
                style="display: none;"
            >
                Cancel Run
            </button>
        </div>
        
        {* Restore metadata info box *}
        {if $is_restore && $restore_metadata}
        <div class="mb-6 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-xs text-emerald-100 flex items-start gap-2">
            {if $is_hyperv_restore}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-[2px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
            </svg>
            {else}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-[2px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            {/if}
            <div class="flex-1">
                {if $is_hyperv_restore}
                    <p class="font-semibold text-emerald-100 text-[0.75rem] uppercase tracking-wide">Hyper-V Disk Restore</p>
                    <p class="mt-1 text-[0.75rem] leading-relaxed text-emerald-100/90">
                        Restoring VM <code class="bg-emerald-900/50 px-1 rounded">{$restore_metadata.vm_name}</code>
                        ({$restore_metadata.disk_count} disk{if $restore_metadata.disk_count > 1}s{/if})
                        to <code class="bg-emerald-900/50 px-1 rounded">{$restore_metadata.target_path}</code>
                    </p>
                    {if $restore_metadata.backup_type}
                    <p class="mt-1 text-[0.75rem] leading-relaxed text-emerald-100/70">
                        Backup Type: <span class="font-medium">{$restore_metadata.backup_type}</span>
                        {if $restore_metadata.restore_chain_length > 1}
                        (Chain: {$restore_metadata.restore_chain_length} backups)
                        {/if}
                    </p>
                    {/if}
                {else}
                    <p class="font-semibold text-emerald-100 text-[0.75rem] uppercase tracking-wide">Restore Operation</p>
                    <p class="mt-1 text-[0.75rem] leading-relaxed text-emerald-100/90">
                        Restoring snapshot <code class="bg-emerald-900/50 px-1 rounded">{$restore_metadata.manifest_id|truncate:16:'...'}</code> 
                        to <code class="bg-emerald-900/50 px-1 rounded">{$restore_metadata.target_path}</code>
                    </p>
                {/if}
            </div>
        </div>
        {/if}
        <div class="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-xs text-amber-100 flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-[2px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75M12 15.75h.007M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-semibold text-amber-100 text-[0.75rem] uppercase tracking-wide">Cloud Backup (Beta)</p>
                <p class="mt-1 text-[0.75rem] leading-relaxed text-amber-100/90">
                    Cloud Backup is currently in beta. Functionality may change and occasional issues are expected.
                    Please keep a primary backup strategy in place and contact support if you notice any problems.
                </p>
            </div>
        </div>

        <!-- Compact Metrics Strip -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Status</p>
                <div class="mt-1 flex items-center gap-2">
                    <span id="statusTopDot" class="h-3 w-3 rounded-full status-dot bg-sky-500 shadow-[0_0_8px_rgba(59,130,246,0.6)] animate-status-pulse"></span>
                    <span id="statusTopText" class="text-sm font-semibold text-white">{$run.status|ucfirst}</span>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Processed</p>
                <p class="mt-1 text-2xl font-semibold text-white" id="bytesProcessedTop">
                    {if $run.bytes_processed}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_processed)}
                    {elseif $run.bytes_transferred}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                    {else}
                        0.00 Bytes
                    {/if}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Uploaded</p>
                <p class="mt-1 text-2xl font-semibold text-white" id="bytesTransferredTop">
                    {if $run.bytes_transferred}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                    {else}
                        0.00 Bytes
                    {/if}
                </p>
                <p id="uploadedSavingsTop" class="mt-1 text-xs text-slate-400"></p>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Speed</p>
                <p class="mt-1 text-2xl font-semibold text-white" id="speedTop">
                    {if $run.speed_bytes_per_sec}
                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.speed_bytes_per_sec)}/s
                    {else}
                        -
                    {/if}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">ETA</p>
                <p class="mt-1 text-2xl font-semibold text-white" id="etaTop">
                    {if $run.eta_seconds}
                        {assign var="hours" value=$run.eta_seconds/3600|floor}
                        {assign var="minutes" value=($run.eta_seconds%3600)/60|floor}
                        {assign var="seconds" value=$run.eta_seconds%60|string_format:"%.0f"}
                        {if $hours > 0}{$hours}h {/if}{if $minutes > 0}{$minutes}m {/if}{$seconds}s
                    {else}
                        -
                    {/if}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Duration</p>
                <p class="mt-1 text-2xl font-semibold text-white" id="durationValue">-</p>
                <p id="durationLabel" class="text-xs text-slate-400 mt-1">Elapsed</p>
            </div>
        </div>

        {assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6" x-data="{ isRunning: {if $isRunningStatus}true{else}false{/if} }">
            <div class="mb-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-400">Progress</p>
                        <p class="text-3xl font-semibold text-white" id="progressPercent">
                            {if $run.progress_pct}
                                {$run.progress_pct|string_format:"%.2f"}%
                            {elseif $isRunningStatus}
                                Preparing...
                            {else}
                                0.00%
                            {/if}
                        </p>
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Stage</p>
                        <p id="stageLabel" class="text-lg font-semibold text-white">
                            {if $run.stage}{$run.stage}{else}{$run.status|ucfirst}{/if}
                        </p>
                    </div>
                </div>
                <div class="mt-4 h-5 sm:h-6 rounded-2xl bg-gray-700 relative overflow-hidden">
                    <div
                        class="h-full rounded-2xl transition-all duration-500 ease-out bg-gradient-to-r from-sky-500 to-sky-400 relative"
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
            </div>

            <div class="space-y-4">
                {if $run.current_item}
                    <div>
                        <h6 class="text-sm font-medium text-slate-400 mb-2">Current File</h6>
                        <div class="bg-gray-900 p-3 rounded-md text-sm text-slate-300 font-mono break-all" id="currentItem">
                            {$run.current_item}
                        </div>
                    </div>
                {/if}
                <div x-data="{ open: false }" class="rounded-xl border border-slate-700 bg-slate-900/40">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-sm font-semibold text-slate-200 hover:text-white"
                        @click="open = !open"
                    >
                        <span>Details</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 transition-transform duration-200"
                            :class="open ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak class="px-4 pb-4 text-sm text-slate-400 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Run ID</span>
                            <span class="font-mono text-slate-100 text-xs">{$run.id}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Job</span>
                            <span class="text-slate-100 truncate">{if $job.name}{$job.name}{else}Job #{$job.id}{/if}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Status</span>
                            <span id="detailsStatus" class="text-white font-semibold text-xs">{$run.status|ucfirst}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Run UUID</span>
                            <span class="font-mono text-slate-100 text-xs break-all">{$run.run_uuid|default:$run.id}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Logs Section -->
            <div class="mt-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h6 class="text-sm font-medium text-slate-400">Live Logs</h6>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                        <button
                            type="button"
                            onclick="clearLogs()"
                            class="px-3 py-1 rounded-full border border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500 hover:text-white transition-colors"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            onclick="toggleAutoScrollLogs()"
                            class="px-3 py-1 rounded-full border border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500 hover:text-white transition-colors"
                        >
                            Autoscroll: <span id="autoScrollLabel">On</span>
                        </button>
                        <button
                            type="button"
                            onclick="copyLogs()"
                            class="px-3 py-1 rounded-full border border-slate-700 bg-slate-900/60 text-slate-300 hover:border-slate-500 hover:text-white transition-colors"
                        >
                            Copy
                        </button>
                    </div>
                </div>
                <div class="bg-gray-900 rounded-md p-4 max-h-96 overflow-y-auto font-mono text-xs text-slate-300" id="liveLogs">
                    <div class="text-slate-500 italic" id="liveLogsEmpty">
                        Waiting for log data...
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
let lastLogsHash = null;
let lastEventId = 0;
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
    fetch('modules/addons/cloudstorage/api/cloudbackup_progress.php?run_uuid={$run.run_uuid|default:$run.id}')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.run) {
                const run = data.run;
                
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
                        if (run.started_at) {
                            const parsed = Date.parse(run.started_at);
                            etaModel.startMs = isNaN(parsed) ? Date.now() : parsed;
                        } else {
                            etaModel.startMs = Date.now();
                        }
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

                // Update the label above the bar to show transferred vs total when possible
                if (progressPercent) {
                    if (run.bytes_total && run.bytes_total > 0) {
                        const transferredText = formatBytes(run.bytes_transferred || 0);
                        const totalText = formatBytes(run.bytes_total);
                        progressPercent.textContent = transferredText + ' of ' + totalText + ' (' + progressPct.toFixed(2) + '%)';
                    } else if (progressPct > 0) {
                        progressPercent.textContent = progressPct.toFixed(2) + '%';
                    } else if (['starting', 'queued'].includes(run.status)) {
                        progressPercent.textContent = 'Preparing...';
                    } else {
                        progressPercent.textContent = 'Transferring...';
                    }
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
                
                // Update bytes processed (scanned from source)
                const bytesProcessed = run.bytes_processed || run.bytes_transferred || 0;
                if (bytesProcessed !== undefined && bytesProcessed !== null) {
                    const processedEl = document.getElementById('bytesProcessed');
                    const processedTopEl = document.getElementById('bytesProcessedTop');
                    if (processedEl) {
                        processedEl.textContent = formatBytes(bytesProcessed);
                        processedEl.classList.add('opacity-0');
                        setTimeout(() => processedEl.classList.remove('opacity-0'), 50);
                    }
                    if (processedTopEl) {
                        processedTopEl.textContent = formatBytes(bytesProcessed);
                        processedTopEl.classList.add('opacity-0');
                        setTimeout(() => processedTopEl.classList.remove('opacity-0'), 50);
                    }
                }
                
                // Update bytes uploaded (actual network transfer)
                if (run.bytes_transferred !== undefined && run.bytes_transferred !== null) {
                    const bytesEl = document.getElementById('bytesTransferred');
                    const bytesTopEl = document.getElementById('bytesTransferredTop');
                    if (bytesEl) {
                        bytesEl.textContent = formatBytes(run.bytes_transferred);
                        bytesEl.classList.add('opacity-0');
                        setTimeout(() => bytesEl.classList.remove('opacity-0'), 50);
                    }
                    if (bytesTopEl) {
                        bytesTopEl.textContent = formatBytes(run.bytes_transferred);
                        bytesTopEl.classList.add('opacity-0');
                        setTimeout(() => bytesTopEl.classList.remove('opacity-0'), 50);
                    }
                    
                    // Update dedup savings display in the headline card
                    const uploadedSavingsEl = document.getElementById('uploadedSavingsTop');
                    if (uploadedSavingsEl && bytesProcessed > 0) {
                        const transferred = run.bytes_transferred || 0;
                        const savedBytes = Math.max(0, bytesProcessed - transferred);
                        const savedPercent = bytesProcessed > 0 ? (savedBytes / bytesProcessed) * 100 : 0;
                        if (savedBytes > 0) {
                            uploadedSavingsEl.textContent = 'Saved: ' + formatBytes(savedBytes) + ' (' + savedPercent.toFixed(1) + '%)';
                        } else {
                            uploadedSavingsEl.textContent = 'Saved: ' + formatBytes(0) + ' (' + savedPercent.toFixed(1) + '%)';
                        }
                    } else if (uploadedSavingsEl) {
                        uploadedSavingsEl.textContent = '';
                    }
                }
                
                // Update speed with 2 decimal places
                if (run.speed_bytes_per_sec !== undefined && run.speed_bytes_per_sec !== null) {
                    const speedEl = document.getElementById('speed');
                    const speedTopEl = document.getElementById('speedTop');
                    if (speedEl) {
                        speedEl.textContent = formatBytes(run.speed_bytes_per_sec) + '/s';
                        speedEl.classList.add('opacity-0');
                        setTimeout(() => speedEl.classList.remove('opacity-0'), 50);
                    }
                    if (speedTopEl) {
                        speedTopEl.textContent = formatBytes(run.speed_bytes_per_sec) + '/s';
                        speedTopEl.classList.add('opacity-0');
                        setTimeout(() => speedTopEl.classList.remove('opacity-0'), 50);
                    }
                }
                
                // Update ETA values (top card and panel) with integer seconds
                if (run.eta_seconds !== undefined && run.eta_seconds !== null) {
                    const etaEl = document.getElementById('eta');
                    const etaTopEl = document.getElementById('etaTop');
                    if (etaEl) {
                        etaEl.textContent = formatEta(run.eta_seconds);
                        etaEl.classList.add('opacity-0');
                        setTimeout(() => etaEl.classList.remove('opacity-0'), 50);
                    }
                    if (etaTopEl) {
                        etaTopEl.textContent = formatEta(run.eta_seconds);
                        etaTopEl.classList.add('opacity-0');
                        setTimeout(() => etaTopEl.classList.remove('opacity-0'), 50);
                    }
                }
                
                // Update current item
                if (run.current_item) {
                    const currentItemEl = document.getElementById('currentItem');
                    if (currentItemEl) {
                        currentItemEl.textContent = run.current_item;
                    }
                }
                
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
    if (run.started_at) {
        const parsed = Date.parse(run.started_at);
        if (!isNaN(parsed)) {
            durationStartMs = parsed;
            return;
        }
    }
    durationStartMs = Date.now();
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
            durationEndMs = now;
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
    const detailsStatusEl = document.getElementById('detailsStatus');
    if (statusTextEl) {
        statusTextEl.textContent = statusConfig.text;
    }
    if (detailsStatusEl) {
        detailsStatusEl.textContent = statusConfig.text;
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
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_live_logs.php?run_uuid={$run.run_uuid|default:$run.id}';
    if (lastLogsHash) {
        url += '&hash=' + encodeURIComponent(lastLogsHash);
    }
    fetch(url)
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
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_uuid={$run.run_uuid|default:$run.id}&limit=500';
    if (lastEventId > 0) {
        url += '&since_id=' + encodeURIComponent(String(lastEventId));
    }
    fetch(url)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success' || !Array.isArray(d.events)) return;
            if (d.events.length === 0) return;
            // Render events
            const liveLogsContainer = document.getElementById('liveLogs');
            const liveLogsEmpty = document.getElementById('liveLogsEmpty');
            if (!liveLogsContainer) return;
            if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';
            // If first events load, clear any prior content
            if (lastEventId === 0) {
                while (liveLogsContainer.firstChild) liveLogsContainer.removeChild(liveLogsContainer.firstChild);
            }
            d.events.forEach(ev => {
                // Stop appending once a terminal event is seen (avoid repeated "Backup cancelled.")
                if (terminalEventSeen) return;
                const line = document.createElement('div');
                line.className = 'log-line mb-1';
                const badge = document.createElement('span');
                badge.className = 'log-badge ' + (
                    ev.level === 'error' ? 'log-badge-error' :
                    ev.level === 'warn' ? 'log-badge-warn' :
                    'log-badge-info'
                );
                badge.textContent = (ev.level || 'info').toUpperCase();
                const text = document.createElement('span');
                const ts = ev.ts ? '[' + ev.ts + '] ' : '';
                text.textContent = ts + (ev.message || '');
                text.style.marginLeft = '.5rem';
                line.appendChild(badge);
                line.appendChild(text);
                liveLogsContainer.appendChild(line);
                if (typeof ev.id === 'number' && ev.id > lastEventId) {
                    lastEventId = ev.id;
                }
                // Mark terminal when we encounter cancelled/success/failed/warning
                const evType = (ev.type || '').toLowerCase();
                if (['cancelled','summary'].includes(evType) || /backup cancelled/i.test(ev.message || '')) {
                    terminalEventSeen = true;
                }
            });
            if (autoScrollLogs) {
                liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
            }
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

function cancelRun(runId) {
    if (!confirm('Are you sure you want to cancel this run?')) {
        return;
    }
    
    const btn = document.getElementById('cancelButton');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Cancel requested...';
    }
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_cancel_run.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([['run_uuid', runId]])
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

