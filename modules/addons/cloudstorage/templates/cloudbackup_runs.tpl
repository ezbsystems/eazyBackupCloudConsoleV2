<div class="min-h-screen bg-[#11182759] text-gray-300">
    <div class="container mx-auto px-4 pb-8">
        <!-- Navigation Tabs -->
        <div class="mb-6 border-b border-slate-700">
            <nav class="flex space-x-8" aria-label="Cloud Backup Navigation">
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs"
                   class="py-4 px-1 border-b-2 font-medium text-sm {if $smarty.get.view == 'cloudbackup_jobs' or empty($smarty.get.view)}border-sky-500 text-sky-400{else}border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-300{/if}">
                    Jobs
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs"
                   class="py-4 px-1 border-b-2 font-medium text-sm {if $smarty.get.view == 'cloudbackup_runs'}border-sky-500 text-sky-400{else}border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-300{/if}">
                    Run History
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings"
                   class="py-4 px-1 border-b-2 font-medium text-sm {if $smarty.get.view == 'cloudbackup_settings'}border-sky-500 text-sky-400{else}border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-300{/if}">
                    Settings
                </a>
            </nav>
        </div>

        {if isset($job)}
        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-6">
            <div class="flex items-center">
                <h2 class="text-2xl font-semibold text-white">Run History: {$job.name}</h2>
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
                    <span class="text-md font-medium text-slate-300">Bucket #{$job.dest_bucket_id} / {$job.dest_prefix}</span>
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
                                        {if $run.status eq 'success'}bg-green-700 text-green-200
                                        {elseif $run.status eq 'failed'}bg-red-700 text-red-200
                                        {elseif $run.status eq 'running'}bg-blue-700 text-blue-200
                                        {elseif $run.status eq 'warning'}bg-yellow-700 text-yellow-200
                                        {else}bg-gray-700 text-gray-200{/if}">
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
                                    {if $run.bytes_transferred}
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

        <div class="bg-slate-800 rounded-lg border border-slate-700 shadow-lg p-6">
            <p class="text-slate-300 mb-4">Select a backup job to view its run history:</p>
            {if isset($jobs) && count($jobs) > 0}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {foreach from=$jobs item=j}
                        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs&job_id={$j->id}" class="block bg-slate-700 hover:bg-slate-600 border border-slate-600 rounded-md p-4">
                            <div class="text-white font-semibold">{$j->name}</div>
                            <div class="text-sm text-slate-300 mt-1">{$j->source_display_name}</div>
                            <div class="text-xs text-slate-400 mt-1">Dest Bucket #{$j->dest_bucket_id}{if $j->dest_prefix} / {$j->dest_prefix}{/if}</div>
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
    
    // Fetch formatted logs from API
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_run_logs.php?run_id=' + encodeURIComponent(runId))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Display formatted backup log
                if (data.backup_log) {
                    logExcerpt.textContent = data.backup_log;
                } else {
                    logExcerpt.textContent = 'No backup log data available for this run.';
                }
                
                // Display validation log if available
                if (data.has_validation && data.validation_log && validationSection && validationLogExcerpt) {
                    validationLogExcerpt.textContent = data.validation_log;
                    validationSection.classList.remove('hidden');
                } else if (validationSection) {
                    validationSection.classList.add('hidden');
                }
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

