<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">
        <!-- Navigation Tabs (pill style) -->
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Cloud Backup Navigation">
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_jobs' || empty($smarty.get.view)}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Jobs
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs&job_id={$job.id}"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_runs' || $smarty.get.view == 'cloudbackup_live'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Run History
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_settings'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Settings
                </a>
            </nav>
        </div>
        <!-- Glass panel container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">

        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-3">
            <div class="flex items-center">
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs&job_id={$job.id}" class="mr-4 text-sky-400 hover:text-sky-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h2 class="text-2xl font-semibold text-white flex items-center gap-2">
                    <span>Live Progress: {$job.name}</span>
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
        <div class="mb-6 grid gap-4 md:grid-cols-4">
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
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Bytes Transferred</p>
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
                    <h6 class="text-sm font-medium text-slate-400 mb-2">Bytes Transferred</h6>
                    <div class="text-2xl font-semibold text-slate-300 transition-opacity duration-300" id="bytesTransferred">
                        {if $run.bytes_transferred}
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
                                'cancelled': { color: 'gray', text: 'Cancelled', pulse: false }
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
    fetch('modules/addons/cloudstorage/api/cloudbackup_progress.php?run_id={$run.id}')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.run) {
                const run = data.run;
                
                // Compute progress percentage with sensible fallbacks
                let progressPct = 0;
                const apiPct = parseFloat(run.progress_pct);
                if (!isNaN(apiPct) && apiPct > 0) {
                    progressPct = apiPct;
                } else if (run.bytes_total && run.bytes_total > 0 && run.bytes_transferred >= 0) {
                    progressPct = Math.min(100, (run.bytes_transferred / run.bytes_total) * 100);
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

                const isFinished = ['success','failed','cancelled','warning'].includes(run.status);

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
                
                // Update bytes transferred with 2 decimal places
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
                        'cancelled': { text: 'Cancelled', color: 'gray', pulse: false }
                    };
                    const statusConfig = statusMap[run.status] || { 
                        text: run.status.charAt(0).toUpperCase() + run.status.slice(1), 
                        color: 'gray', 
                        pulse: false 
                    };
                    
                    // Try Alpine.js update first
                    if (window.Alpine) {
                        try {
                            const alpineData = Alpine.$data(statusBadge);
                            if (alpineData) {
                                // Always update status to trigger reactivity
                                alpineData.status = run.status;
                                // Force Alpine to re-evaluate by accessing a reactive property
                                void alpineData.getStatusConfig();
                            }
                        } catch (e) {
                            console.warn('Error updating Alpine.js status:', e);
                        }
                    }
                    
                    // Fallback: directly update DOM elements to ensure UI updates
                    const statusText = statusBadge.querySelector('span.text-sm');
                    const statusDot = statusBadge.querySelector('.w-3.h-3.rounded-full');
                    const pingDot = statusBadge.querySelector('.animate-ping');
                    
                    if (statusText) {
                        statusText.textContent = statusConfig.text;
                    }
                    
                    // Update dot color classes
                    if (statusDot) {
                        // Remove all color classes and animations
                        statusDot.classList.remove('bg-green-500', 'bg-red-500', 'bg-blue-500', 'bg-yellow-500', 'bg-gray-500', 'animate-pulse');
                        
                        // Add appropriate color class based on status
                        const colorClasses = {
                            'green': 'bg-green-500',
                            'red': 'bg-red-500',
                            'blue': 'bg-blue-500',
                            'yellow': 'bg-yellow-500',
                            'gray': 'bg-gray-500'
                        };
                        if (colorClasses[statusConfig.color]) {
                            statusDot.classList.add(colorClasses[statusConfig.color]);
                        }
                        
                        if (statusConfig.pulse) {
                            statusDot.classList.add('animate-pulse');
                        }
                        
                        // Update glow effect
                        const glowColors = {
                            'green': 'rgba(34, 197, 94, 0.6)',
                            'red': 'rgba(239, 68, 68, 0.6)',
                            'blue': 'rgba(59, 130, 246, 0.6)',
                            'yellow': 'rgba(234, 179, 8, 0.6)',
                            'gray': 'rgba(107, 114, 128, 0.6)'
                        };
                        const glowColor = glowColors[statusConfig.color] || glowColors.gray;
                        statusDot.style.boxShadow = '0 0 8px ' + glowColor;
                    }
                    
                    // Update ping animation dot
                    if (pingDot) {
                        pingDot.classList.remove('bg-green-500', 'bg-red-500', 'bg-blue-500', 'bg-yellow-500', 'bg-gray-500');
                        
                        const colorClasses = {
                            'green': 'bg-green-500',
                            'red': 'bg-red-500',
                            'blue': 'bg-blue-500',
                            'yellow': 'bg-yellow-500',
                            'gray': 'bg-gray-500'
                        };
                        if (colorClasses[statusConfig.color]) {
                            pingDot.classList.add(colorClasses[statusConfig.color]);
                        }
                        
                        // Show/hide ping based on pulse
                        if (statusConfig.pulse) {
                            pingDot.style.display = '';
                        } else {
                            pingDot.style.display = 'none';
                        }
                    }
                }
                
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
                if (['success', 'failed', 'cancelled', 'warning'].includes(run.status)) {
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
    
    // Auto-scroll to bottom
    liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
}

function setFormattedLogs(text) {
    const liveLogsContainer = document.getElementById('liveLogs');
    const liveLogsEmpty = document.getElementById('liveLogsEmpty');
    if (!liveLogsContainer) return;
    liveLogsContainer.setAttribute('data-formatted', '1');
    if (liveLogsEmpty) liveLogsEmpty.style.display = 'none';
    // Replace entire content with server-formatted text
    liveLogsContainer.textContent = text || '';
    liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
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

    liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
}

function updateFormattedLogs() {
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_live_logs.php?run_id={$run.id}';
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
    let url = 'modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_id={$run.id}&limit=500';
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
            liveLogsContainer.scrollTop = liveLogsContainer.scrollHeight;
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

function cancelRun(runId) {
    if (!confirm('Are you sure you want to cancel this run?')) {
        return;
    }
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_cancel_run.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([['run_id', runId]])
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Run cancellation requested');
            updateProgress();
        } else {
            alert(data.message || 'Failed to cancel run');
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

