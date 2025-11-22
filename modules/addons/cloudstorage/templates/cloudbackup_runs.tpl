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
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_runs'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
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

        {if isset($job)}
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-6">
            <div class="flex items-center">
                <h2 class="text-2xl font-semibold text-white">Run History: {$job.name}</h2>
            </div>
        </div>
        <!-- Summary Metrics Band (per-job runs) -->
        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Total Runs</p>
                <p class="mt-1 text-2xl font-semibold text-white">{if isset($metrics.total_runs)}{$metrics.total_runs}{else}{if isset($runs)}{count($runs)}{else}0{/if}{/if}</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-3">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wide">Success (24h)</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-100">{if isset($metrics.success_24h)}{$metrics.success_24h}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/5 px-4 py-3">
                <p class="text-xs font-medium text-rose-300 uppercase tracking-wide">Failed (24h)</p>
                <p class="mt-1 text-2xl font-semibold text-rose-100">{if isset($metrics.failed_24h)}{$metrics.failed_24h}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 px-4 py-3">
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Last Run</p>
                <p class="mt-1 text-sm text-slate-200">
                    {if isset($metrics.last_run_started_at) && $metrics.last_run_started_at}
                        {$metrics.last_run_started_at|date_format:"%d %b %Y %H:%M"}
                        {if $metrics.last_run_status}
                            <span class="ml-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-medium
                                {if $metrics.last_run_status eq 'success'}bg-emerald-500/10 text-emerald-300
                                {elseif $metrics.last_run_status eq 'failed'}bg-rose-500/15 text-rose-300
                                {elseif $metrics.last_run_status eq 'cancelled'}bg-amber-500/15 text-amber-300
                                {else}bg-slate-500/15 text-slate-300{/if}">
                                {$metrics.last_run_status|ucfirst}
                            </span>
                        {/if}
                    {else}
                        <span class="text-slate-500">-</span>
                    {/if}
                </p>
            </div>
        </div>

        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <h6 class="text-sm font-medium text-slate-400">Source</h6>
                    <span class="text-md font-medium text-slate-300">{$job.source_display_name}</span>
                </div>
                <div>
                    <h6 class="text-sm font-medium text-slate-400">Destination</h6>
                    <span class="text-md font-medium text-slate-300">
                        {if isset($job.dest_bucket_name) && $job.dest_bucket_name}
                            {$job.dest_bucket_name}{if $job.dest_prefix} / {$job.dest_prefix}{/if}
                        {else}
                            Bucket #{$job.dest_bucket_id}{if $job.dest_prefix} / {$job.dest_prefix}{/if}
                        {/if}
                    </span>
                </div>
                <div>
                    <h6 class="text-sm font-medium text-slate-400">Schedule</h6>
                    <span class="text-md font-medium text-slate-300">{$job.schedule_type|ucfirst}</span>
                </div>
            </div>
        </div>

        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg overflow-hidden">
            <table class="min-w-full divide-y divide-slate-700">
                <thead class="bg-slate-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Date/Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Trigger</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Validation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Bytes Transferred</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-slate-800 divide-y divide-slate-700">
                    {if count($runs) > 0}
                        {foreach from=$runs item=run}
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                    {if $run.started_at}
                                        {$run.started_at|date_format:"%d %b %Y %H:%M:%S"}
                                    {else}
                                        Queued
                                    {/if}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                    {$run.trigger_type|ucfirst}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {if $run.status eq 'success'}bg-emerald-500/10 text-emerald-300
                                        {elseif $run.status eq 'failed'}bg-rose-500/15 text-rose-300
                                        {elseif $run.status eq 'running' || $run.status eq 'starting'}bg-sky-500/10 text-sky-300
                                        {elseif $run.status eq 'warning'}bg-amber-500/15 text-amber-300
                                        {elseif $run.status eq 'cancelled'}bg-amber-500/15 text-amber-300
                                        {else}bg-slate-500/15 text-slate-300{/if}">
                                        {$run.status|ucfirst}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {if $run.validation_mode eq 'post_run'}
                                        {if $run.validation_status eq 'success'}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-700 text-green-200" title="Validation passed">✓ Valid</span>
                                        {elseif $run.validation_status eq 'failed'}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-700 text-red-200" title="Validation failed">✗ Failed</span>
                                        {elseif $run.validation_status eq 'running'}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-700 text-blue-200">Running...</span>
                                        {else}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-200">Pending</span>
                                        {/if}
                                    {else}
                                        <span class="text-xs text-slate-500">-</span>
                                    {/if}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                    {if $run.bytes_transferred|@strlen}
                                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                                    {else}
                                        -
                                    {/if}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                    {if $run.started_at && $run.finished_at}
                                        {assign var="start" value=$run.started_at|strtotime}
                                        {assign var="end" value=$run.finished_at|strtotime}
                                        {assign var="duration" value=$end-$start}
                                        {if $duration < 60}
                                            {$duration}s
                                        {elseif $duration < 3600}
                                            {math equation="floor(x/60)" x=$duration}m {$duration%60}s
                                        {else}
                                            {math equation="floor(x/3600)" x=$duration}h {math equation="floor((x%3600)/60)" x=$duration}m
                                        {/if}
                                    {elseif $run.status eq 'running'}
                                        Running...
                                    {else}
                                        -
                                    {/if}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    {if $run.status eq 'running'}
                                        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_live&run_id={$run.id}" class="text-sky-400 hover:text-sky-500">View Live</a>
                                    {elseif $run.log_excerpt}
                                        <button onclick="showRunDetails({$run.id})" class="text-sky-400 hover:text-sky-500">View Details</button>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-slate-400">No runs found for this job.</td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>
        {else}
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-6">
            <div class="flex items-center">
                <h2 class="text-2xl font-semibold text-white">Run History</h2>
            </div>
        </div>
        <!-- Summary Metrics Band (all jobs aggregate) -->
        <div class="mb-6 grid gap-4 md:grid-cols-5">
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border border-slate-800/80">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase text-slate-400">Total Jobs</p>
                <p class="mt-1 text-2xl font-semibold text-white">{if isset($metrics.total_jobs)}{$metrics.total_jobs}{else}{if isset($jobs)}{count($jobs)}{else}0{/if}{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border
                {if isset($metrics.active) && $metrics.active > 0}
                    border-emerald-500/35
                {else}
                    border-slate-800/80
                {/if}">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase
                    {if isset($metrics.active) && $metrics.active > 0} text-emerald-200 {else} text-slate-400 {/if}">
                    Active
                </p>
                <p class="mt-1 text-2xl font-semibold text-slate-50">{if isset($metrics.active)}{$metrics.active}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border
                {if isset($metrics.paused) && $metrics.paused > 0}
                    border-amber-500/35
                {else}
                    border-slate-800/80
                {/if}">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase
                    {if isset($metrics.paused) && $metrics.paused > 0} text-amber-200 {else} text-slate-400 {/if}">
                    Paused
                </p>
                <p class="mt-1 text-2xl font-semibold text-slate-50">{if isset($metrics.paused)}{$metrics.paused}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border
                {if isset($metrics.failed_24h) && $metrics.failed_24h > 0}
                    border-rose-500/35 shadow-[0_0_24px_rgba(248,113,113,0.25)]
                {else}
                    border-slate-800/80
                {/if}">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase
                    {if isset($metrics.failed_24h) && $metrics.failed_24h > 0} text-rose-300 {else} text-slate-400 {/if}">
                    Failed (24h)
                </p>
                <p class="mt-1 text-2xl font-semibold text-slate-50">{if isset($metrics.failed_24h)}{$metrics.failed_24h}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border border-slate-800/80">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase text-slate-400">Last Run</p>
                <p class="mt-1 text-sm text-slate-200">
                    {if isset($metrics.last_run_started_at) && $metrics.last_run_started_at}
                        {$metrics.last_run_started_at|date_format:"%d %b %Y %H:%M"}
                        {if $metrics.last_run_status}
                            <span class="ml-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-medium
                                {if $metrics.last_run_status eq 'success'}bg-emerald-500/10 text-emerald-300
                                {elseif $metrics.last_run_status eq 'failed'}bg-rose-500/15 text-rose-300
                                {elseif $metrics.last_run_status eq 'cancelled'}bg-amber-500/15 text-amber-300
                                {else}bg-slate-500/15 text-slate-300{/if}">
                                {$metrics.last_run_status|ucfirst}
                            </span>
                        {/if}
                    {else}
                        <span class="text-slate-500">Never</span>
                    {/if}
                </p>
            </div>
        </div>

        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6">
            <p class="text-slate-300 mb-4">Select a backup job to view its run history:</p>
            {if isset($jobs) && count($jobs) > 0}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {foreach from=$jobs item=j}
                        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs&job_id={$j->id}" class="block bg-slate-700 hover:bg-slate-600 border border-slate-600 rounded-md p-4">
                            <div class="text-white font-semibold">{$j->name}</div>
                            <div class="text-sm text-slate-300 mt-1">{$j->source_display_name}</div>
                            <div class="text-xs text-slate-400 mt-2">
                                Dest Bucket 
                                {if isset($j->dest_bucket_name) && $j->dest_bucket_name}
                                    {$j->dest_bucket_name}
                                {else}
                                    #{$j->dest_bucket_id}
                                {/if}
                                {if $j->dest_prefix} / {$j->dest_prefix}{/if}
                            </div>
                        </a>
                    {/foreach}
                </div>
            {else}
                <div class="text-slate-400">No backup jobs found. Create a job first on the Jobs tab.</div>
            {/if}
        </div>
        {/if}
    </div>
</div>

<!-- Run Details Modal -->
<div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 hidden" id="runDetailsModal">
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-3xl max-h-[85vh] overflow-y-auto p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-white">Run Details</h2>
            <button type="button" onclick="closeModal('runDetailsModal')" class="text-slate-300 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="runDetailsContent" class="text-slate-300">
            <div id="runDetailsLog" class="mb-4">
                <h3 class="text-lg font-semibold mb-2">Backup Log</h3>
                <pre class="bg-gray-900 p-4 rounded-md overflow-x-auto text-sm whitespace-pre-wrap" id="logExcerpt"></pre>
            </div>
            <div id="runDetailsValidation" class="hidden">
                <h3 class="text-lg font-semibold mb-2">Validation Log</h3>
                <pre class="bg-gray-900 p-4 rounded-md overflow-x-auto text-sm whitespace-pre-wrap" id="validationLogExcerpt"></pre>
            </div>
        </div>
    </div>
</div>

<script>
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function showRunDetails(runId) {
    const modal = document.getElementById('runDetailsModal');
    const content = document.getElementById('runDetailsContent');
    const logExcerpt = document.getElementById('logExcerpt');
    const validationLogExcerpt = document.getElementById('validationLogExcerpt');
    const validationSection = document.getElementById('runDetailsValidation');
    
    // Show modal and set loading state
    modal.classList.remove('hidden');
    logExcerpt.textContent = 'Loading log details...';
    if (validationLogExcerpt) {
        validationLogExcerpt.textContent = '';
    }
    if (validationSection) {
        validationSection.classList.add('hidden');
    }
    
    // Fetch sanitized events instead of raw/formatted rclone logs
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_id=' + encodeURIComponent(runId) + '&limit=1000')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const events = Array.isArray(data.events) ? data.events : [];
                if (events.length === 0) {
                    logExcerpt.textContent = 'No event log data available for this run.';
                } else {
                    // Render events as lines: [ts] MESSAGE
                    const lines = events.map(ev => {
                        const ts = ev.ts ? '[' + ev.ts + '] ' : '';
                        return ts + (ev.message || '');
                    });
                    logExcerpt.textContent = lines.join('\n');
                }
                // Hide validation block in event mode (optional future enhancement: show validation events)
                if (validationSection) validationSection.classList.add('hidden');
            } else {
                logExcerpt.textContent = 'Error: ' + (data.message || 'Failed to load log details');
                if (window.toast) {
                    window.toast.error(data.message || 'Failed to load log details');
                }
            }
        })
        .catch(error => {
            console.error('Error fetching run logs:', error);
            logExcerpt.textContent = 'Error: Unable to load log details. Please try again later.';
            if (window.toast) {
                window.toast.error('Failed to load log details');
            }
        });
}
</script>

