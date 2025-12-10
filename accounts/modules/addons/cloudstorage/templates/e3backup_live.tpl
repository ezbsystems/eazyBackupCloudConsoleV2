<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">
        {assign var="metrics" value=['status' => $run.status]}
        <!-- Glass panel container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">

        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-3">
            <div class="flex items-center">
                <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="mr-4 text-sky-400 hover:text-sky-500">
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
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-[2px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <div>
                <p class="font-semibold text-emerald-100 text-[0.75rem] uppercase tracking-wide">Restore Operation</p>
                <p class="mt-1 text-[0.75rem] leading-relaxed text-emerald-100/90">
                    Restoring snapshot <code class="bg-emerald-900/50 px-1 rounded">{$restore_metadata.manifest_id|truncate:16:'...'}</code> 
                    to <code class="bg-emerald-900/50 px-1 rounded">{$restore_metadata.target_path}</code>
                </p>
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
        <div class="mb-6 grid gap-4 md:grid-cols-5">
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Status</p>
                <p class="mt-1">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                        {if $metrics.status eq 'success'}bg-emerald-500/10 text-emerald-300
                        {elseif $metrics.status eq 'failed'}bg-rose-500/15 text-rose-300
                        {elseif $metrics.status eq 'running' || $metrics.status eq 'starting' || $metrics.status eq 'queued'}bg-sky-500/10 text-sky-300
                        {elseif $metrics.status eq 'cancelled'}bg-amber-500/15 text-amber-300
                        {else}bg-slate-500/15 text-slate-300{/if}">
                        {$metrics.status|ucfirst}
                    </span>
                </p>
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
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Speed</p>
                <p class="mt-1 text-2xl font-semibold text-white">
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
        </div>

        {assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6" x-data="{ isRunning: {if $isRunningStatus}true{else}false{/if} }">
            <div class="mb-6">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-slate-400">Progress</span>
                    <span class="text-sm font-medium text-slate-300 transition-opacity duration-300" id="progressPercent">
                        {if $run.progress_pct}
                            {$run.progress_pct|string_format:"%.2f"}%
                        {elseif $isRunningStatus}
                            Preparing...
                        {else}
                            0.00%
                        {/if}
                    </span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-4 relative overflow-hidden">
                    <div
                        class="h-4 rounded-full transition-all duration-500 ease-out bg-gradient-to-r from-sky-500 to-sky-400 relative"
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                <div>
                    <h6 class="text-sm font-medium text-slate-400 mb-2">Bytes Processed</h6>
                    <div class="text-2xl font-semibold text-slate-300 transition-opacity duration-300" id="bytesProcessed">
                        {if $run.bytes_processed}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_processed)}
                        {elseif $run.bytes_transferred}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                        {else}
                            0.00 Bytes
                        {/if}
                    </div>
                    {if $run.bytes_total}
                        <div class="text-sm text-slate-500 mt-1">
                            of {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_total)}
                        </div>
                    {/if}
                </div>
                <div>
                    <h6 class="text-sm font-medium text-slate-400 mb-2">Bytes Uploaded</h6>
                    <div class="text-2xl font-semibold text-slate-300 transition-opacity duration-300" id="bytesTransferred">
                        {if $run.bytes_transferred}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                        {else}
                            0.00 Bytes
                        {/if}
                    </div>
                    <div class="text-sm text-slate-500 mt-1" id="dedupSavings">
                        {if $run.bytes_processed && $run.bytes_transferred && $run.bytes_processed > 0}
                            {assign var="savings" value=100-($run.bytes_transferred/$run.bytes_processed*100)}
                            {if $savings > 0}
                                <span class="text-emerald-400">{$savings|string_format:"%.1f"}% dedup savings</span>
                            {/if}
                        {/if}
                    </div>
                </div>
                <div>
                    <h6 class="text-sm font-medium text-slate-400 mb-2">Speed</h6>
                    <div class="text-2xl font-semibold text-slate-300 transition-opacity duration-300" id="speed">
                        {if $run.speed_bytes_per_sec}
                            {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.speed_bytes_per_sec)}/s
                        {else}
                            -
                        {/if}
                    </div>
                </div>
                <div>
                    <h6 class="text-sm font-medium text-slate-400 mb-2">ETA</h6>
                    <div class="text-2xl font-semibold text-slate-300 transition-opacity duration-300" id="eta">
                        {if $run.eta_seconds}
                            {assign var="hours" value=$run.eta_seconds/3600|floor}
                            {assign var="minutes" value=($run.eta_seconds%3600)/60|floor}
                            {assign var="seconds" value=$run.eta_seconds%60|string_format:"%.0f"}
                            {if $hours > 0}{$hours}h {/if}{if $minutes > 0}{$minutes}m {/if}{$seconds}s
                        {else}
                            -
                        {/if}
                    </div>
                </div>
                <div>
                    <h6 class="text-sm font-medium text-slate-400 mb-2">Status</h6>
                    <div class="inline-flex items-center gap-2" id="statusBadge" x-data="{ 
                        status: '{$run.status}',
                        getStatusConfig() {
                            const configs = {
                                'success': { color: 'green', text: 'Success', pulse: false },
                                'failed': { color: 'red', text: 'Failed', pulse: false },
                                'running': { color: 'blue', text: 'Running', pulse: true },
                                'starting': { color: 'blue', text: 'Starting', pulse: true },
                                'queued': { color: 'yellow', text: 'Queued', pulse: true },
                                'warning': { color: 'yellow', text: 'Warning', pulse: false },
                                'cancelled': { color: 'gray', text: 'Cancelled', pulse: false },
                                'partial_success': { color: 'yellow', text: 'Partial Success', pulse: false }
                            };
                            return configs[this.status] || { color: 'gray', text: this.status.charAt(0).toUpperCase() + this.status.slice(1), pulse: false };
                        }
                    }" x-init="status = '{$run.status}'">
                        <div class="relative">
                            <div class="w-3 h-3 rounded-full" 
                                 :class="{
                                     'bg-green-500 animate-pulse': getStatusConfig().color === 'green' && getStatusConfig().pulse,
                                     'bg-green-500': getStatusConfig().color === 'green' && !getStatusConfig().pulse,
                                     'bg-red-500': getStatusConfig().color === 'red',
                                     'bg-blue-500 animate-pulse': getStatusConfig().color === 'blue' && getStatusConfig().pulse,
                                     'bg-blue-500': getStatusConfig().color === 'blue' && !getStatusConfig().pulse,
                                     'bg-yellow-500': getStatusConfig().color === 'yellow',
                                     'bg-gray-500': getStatusConfig().color === 'gray'
                                 }"
                                 :style="getStatusConfig().color === 'green' ? 'box-shadow: 0 0 8px rgba(34, 197, 94, 0.6);' : 
                                         getStatusConfig().color === 'red' ? 'box-shadow: 0 0 8px rgba(239, 68, 68, 0.6);' :
                                         getStatusConfig().color === 'blue' ? 'box-shadow: 0 0 8px rgba(59, 130, 246, 0.6);' :
                                         getStatusConfig().color === 'yellow' ? 'box-shadow: 0 0 8px rgba(234, 179, 8, 0.6);' :
                                         'box-shadow: 0 0 8px rgba(107, 114, 128, 0.6);'">
                            </div>
                            <div class="absolute inset-0 w-3 h-3 rounded-full -z-10 animate-ping opacity-75"
                                 x-show="getStatusConfig().pulse"
                                 x-cloak
                                 :class="{
                                     'bg-green-500': getStatusConfig().color === 'green',
                                     'bg-red-500': getStatusConfig().color === 'red',
                                     'bg-blue-500': getStatusConfig().color === 'blue',
                                     'bg-yellow-500': getStatusConfig().color === 'yellow',
                                     'bg-gray-500': getStatusConfig().color === 'gray'
                                 }"></div>
                        </div>
                        <span class="text-sm font-medium text-slate-300" x-text="getStatusConfig().text"></span>
                    </div>
                </div>
            </div>

            {if $run.current_item}
                <div class="mt-6">
                    <h6 class="text-sm font-medium text-slate-400 mb-2">Current File</h6>
                    <div class="bg-gray-900 p-3 rounded-md text-sm text-slate-300 font-mono break-all" id="currentItem">
                        {$run.current_item}
                    </div>
                </div>
            {/if}

            <!-- Live Logs Section -->
            <div class="mt-6">
                <div class="flex justify-between items-center mb-2">
                    <h6 class="text-sm font-medium text-slate-400">Live Logs</h6>
                    <button onclick="clearLogs()" class="text-xs text-slate-500 hover:text-slate-300 transition-colors">
                        Clear
                    </button>
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
</style>

<script>
let progressInterval;
let logsInterval;
let eventsInterval;
{assign var="isRunningStatus" value=($run.status eq 'running' || $run.status eq 'starting' || $run.status eq 'queued')}
let isRunning = {if $isRunningStatus}true{else}false{/if};
let lastLogsHash = null;
let lastEventId = 0;

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
                    
                    // Update dedup savings
                    const dedupEl = document.getElementById('dedupSavings');
                    if (dedupEl && bytesProcessed > 0) {
                        const savings = 100 - (run.bytes_transferred / bytesProcessed * 100);
                        if (savings > 0) {
                            dedupEl.innerHTML = '<span class="text-emerald-400">' + savings.toFixed(1) + '% dedup savings</span>';
                        } else {
                            dedupEl.innerHTML = '';
                        }
                    }
                }
                
                // Update speed with 2 decimal places
                if (run.speed_bytes_per_sec !== undefined && run.speed_bytes_per_sec !== null) {
                    const speedEl = document.getElementById('speed');
                    if (speedEl) {
                        speedEl.textContent = formatBytes(run.speed_bytes_per_sec) + '/s';
                        speedEl.classList.add('opacity-0');
                        setTimeout(() => speedEl.classList.remove('opacity-0'), 50);
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
                
                // Update status badge with Alpine.js - always update to ensure reactivity
                const statusBadge = document.getElementById('statusBadge');
                if (statusBadge) {
                    // Status mapping for fallback
                    const statusMap = {
                        'success': { text: 'Success', color: 'green', pulse: false },
                        'failed': { text: 'Failed', color: 'red', pulse: false },
                        'running': { text: 'Running', color: 'blue', pulse: true },
                        'starting': { text: 'Starting', color: 'blue', pulse: true },
                        'queued': { text: 'Queued', color: 'yellow', pulse: true },
                        'warning': { text: 'Warning', color: 'yellow', pulse: false },
                        'cancelled': { text: 'Cancelled', color: 'gray', pulse: false },
                        'partial_success': { text: 'Partial Success', color: 'yellow', pulse: false }
                    };
                    const statusConfig = statusMap[run.status] || { 
                        text: run.status.charAt(0).toUpperCase() + run.status.slice(1), 
                        color: 'gray', 
                        pulse: false 
                    };
                    if (statusBadge.__x && statusBadge.__x.$data) {
                        statusBadge.__x.$data.status = run.status;
                    } else {
                        const dot = statusBadge.querySelector('.w-3.h-3');
                        const textEl = statusBadge.querySelector('span.text-sm');
                        if (dot) {
                            dot.classList.remove('bg-green-500','bg-red-500','bg-blue-500','bg-yellow-500','bg-gray-500','animate-pulse');
                            if (statusConfig.color === 'green') dot.classList.add('bg-green-500');
                            if (statusConfig.color === 'red') dot.classList.add('bg-red-500');
                            if (statusConfig.color === 'blue') dot.classList.add('bg-blue-500');
                            if (statusConfig.color === 'yellow') dot.classList.add('bg-yellow-500');
                            if (statusConfig.color === 'gray') dot.classList.add('bg-gray-500');
                            if (statusConfig.pulse) dot.classList.add('animate-pulse');
                        }
                        if (textEl) {
                            textEl.textContent = statusConfig.text;
                        }
                    }
                }

                // Handle running/completed state transitions
                if (run.status && ['running','starting','queued'].includes(run.status)) {
                    isRunning = true;
                } else {
                    isRunning = false;
                    clearIntervals();
                    // Ensure final progress and status for terminal states
                    if (run.status === 'success') {
                        smoothProgressTo(100, 800);
                    }
                }

                // Toggle cancel button visibility
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
            }
        })
        .catch(err => {
            console.error('Failed to fetch progress', err);
        });
}

function updateLiveLogs() {
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_live_logs.php?run_uuid={$run.run_uuid|default:$run.id}';
    if (lastLogsHash) {
        url += '&after_hash=' + encodeURIComponent(lastLogsHash);
    }
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.logs) {
                const logsEl = document.getElementById('liveLogs');
                const emptyEl = document.getElementById('liveLogsEmpty');
                if (logsEl) {
                    if (emptyEl) emptyEl.style.display = 'none';
                    const frag = document.createDocumentFragment();
                    data.logs.forEach(log => {
                        const line = document.createElement('div');
                        line.className = 'log-line mb-1';
                        const level = (log.level || '').toLowerCase();
                        let badgeClass = 'log-badge-info';
                        if (level === 'error' || level === 'fatal') badgeClass = 'log-badge-error';
                        else if (level === 'warn' || level === 'warning') badgeClass = 'log-badge-warn';
                        else if (level === 'ok' || level === 'success') badgeClass = 'log-badge-ok';
                        {literal}line.innerHTML = `<span class="log-badge ${badgeClass}">${(log.level || 'info').toUpperCase()}</span> ${escapeHtml(log.message || '')}`;{/literal}
                        frag.appendChild(line);
                    });
                    logsEl.appendChild(frag);
                    logsEl.scrollTop = logsEl.scrollHeight;
                }
                if (data.last_hash) {
                    lastLogsHash = data.last_hash;
                }
            }
        })
        .catch(err => {
            console.error('Failed to fetch live logs', err);
        });
}

function updateEventLogs() {
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_uuid={$run.run_uuid|default:$run.id}&limit=500';
    if (lastEventId) {
        url += '&after_event_id=' + encodeURIComponent(lastEventId);
    }
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.events) {
                const logsEl = document.getElementById('liveLogs');
                const emptyEl = document.getElementById('liveLogsEmpty');
                if (logsEl) {
                    if (emptyEl) emptyEl.style.display = 'none';
                    const frag = document.createDocumentFragment();
                    data.events.forEach(ev => {
                        const line = document.createElement('div');
                        line.className = 'log-line mb-1';
                        const level = (ev.level || '').toLowerCase();
                        let badgeClass = 'log-badge-info';
                        if (level === 'error' || level === 'fatal') badgeClass = 'log-badge-error';
                        else if (level === 'warn' || level === 'warning') badgeClass = 'log-badge-warn';
                        else if (level === 'ok' || level === 'success') badgeClass = 'log-badge-ok';
                        {literal}const ts = ev.timestamp ? `[${ev.timestamp}] ` : '';{/literal}
                        {literal}line.innerHTML = `<span class="log-badge ${badgeClass}">${(ev.level || 'info').toUpperCase()}</span> ${ts}${escapeHtml(ev.message || '')}`;{/literal}
                        frag.appendChild(line);
                        if (ev.id && ev.id > lastEventId) {
                            lastEventId = ev.id;
                        }
                    });
                    logsEl.appendChild(frag);
                    logsEl.scrollTop = logsEl.scrollHeight;
                }
            }
        })
        .catch(err => {
            console.error('Failed to fetch run events', err);
        });
}

function clearLogs() {
    const logsEl = document.getElementById('liveLogs');
    const emptyEl = document.getElementById('liveLogsEmpty');
    if (logsEl) {
        logsEl.innerHTML = '';
    }
    if (emptyEl) {
        emptyEl.style.display = '';
    }
    lastLogsHash = null;
    lastEventId = 0;
}

function cancelRun(runId) {
    if (!confirm('Are you sure you want to cancel this run?')) return;
    fetch('modules/addons/cloudstorage/api/cloudbackup_cancel_run.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ run_id: runId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            if (window.toast) toast.success('Cancellation requested');
            clearIntervals();
            updateProgress(); // refresh state
        } else {
            if (window.toast) toast.error(data.message || 'Failed to cancel run');
        }
    })
    .catch(err => {
        if (window.toast) toast.error('Error cancelling run');
    });
}

function clearIntervals() {
    if (progressInterval) clearInterval(progressInterval);
    if (logsInterval) clearInterval(logsInterval);
    if (eventsInterval) clearInterval(eventsInterval);
}

function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatBytes(bytes) {
    if (!bytes || isNaN(bytes)) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const val = bytes / Math.pow(k, i);
    return val.toFixed(i === 0 ? 0 : 2) + ' ' + sizes[i];
}

function formatEta(seconds) {
    if (seconds === null || seconds === undefined || isNaN(seconds)) return '-';
    const sec = Math.max(0, Math.floor(seconds));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    let parts = [];
    if (h > 0) parts.push(h + 'h');
    if (m > 0) parts.push(m + 'm');
    parts.push(s + 's');
    return parts.join(' ');
}

// Initialize polling
document.addEventListener('DOMContentLoaded', () => {
    updateProgress();
    updateEventLogs();
    if (isRunning) {
        progressInterval = setInterval(updateProgress, 2500);
        logsInterval = setInterval(updateLiveLogs, 4000);
        eventsInterval = setInterval(updateEventLogs, 3200);
    } else {
        // Still fetch logs/events once for terminal runs
        updateLiveLogs();
    }
});
</script>

