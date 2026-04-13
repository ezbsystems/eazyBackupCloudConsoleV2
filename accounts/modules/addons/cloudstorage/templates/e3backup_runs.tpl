{capture assign=ebE3RunsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        {if isset($job)}
            <a href="index.php?m=cloudstorage&page=e3backup&view=runs" class="eb-breadcrumb-link">Run history</a>
            <span class="eb-breadcrumb-separator">/</span>
            <span class="eb-breadcrumb-current">{$job.name|escape:'html'}</span>
        {else}
            <span class="eb-breadcrumb-current">Run history</span>
        {/if}
    </div>
{/capture}

{capture assign=ebE3RunsHeaderActions}
    <span class="eb-badge eb-badge--warning">Beta</span>
{/capture}

{if isset($job)}
    {capture assign=ebE3RunsPageTitle}Run history: {$job.name|escape:'html'}{/capture}
{else}
    {assign var=ebE3RunsPageTitle value='Run history'}
{/if}

{capture assign=ebE3Content}
<div class="space-y-6">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3RunsBreadcrumb
        ebPageTitle=$ebE3RunsPageTitle
        ebPageDescription='Cloud Backup run history, validation, and log excerpts. Runs remain available while the job exists.'
        ebPageActions=$ebE3RunsHeaderActions
    }

    <div class="eb-alert eb-alert--warning">
        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <div>
            <div class="eb-alert-title">Cloud Backup (beta)</div>
            <p class="eb-type-caption !mt-1 !text-[var(--eb-warning-text)]">
                Functionality may change and occasional issues are expected. Keep a primary backup strategy in place and contact support if you notice problems.
            </p>
        </div>
    </div>

    {if isset($job)}
        <div class="grid gap-4 md:grid-cols-4">
            <div class="eb-card !p-4">
                <div class="eb-stat-label">Total runs</div>
                <div class="eb-type-stat">{if isset($metrics.total_runs)}{$metrics.total_runs}{else}{if isset($runs)}{count($runs)}{else}0{/if}{/if}</div>
            </div>
            <div class="eb-card !p-4" style="border-color: var(--eb-success-border);">
                <div class="eb-stat-label !text-[var(--eb-success-text)]">Success (24h)</div>
                <div class="eb-type-stat !text-[var(--eb-success-text)]">{if isset($metrics.success_24h)}{$metrics.success_24h}{else}0{/if}</div>
            </div>
            <div class="eb-card !p-4" style="border-color: var(--eb-danger-border);">
                <div class="eb-stat-label !text-[var(--eb-danger-text)]">Failed (24h)</div>
                <div class="eb-type-stat !text-[var(--eb-danger-text)]">{if isset($metrics.failed_24h)}{$metrics.failed_24h}{else}0{/if}</div>
            </div>
            <div class="eb-card !p-4">
                <div class="eb-stat-label">Last run</div>
                <div class="eb-type-body !mt-1 !text-[var(--eb-text-secondary)]">
                    {if isset($metrics.last_run_started_at) && $metrics.last_run_started_at}
                        {$metrics.last_run_started_at|date_format:"%d %b %Y %H:%M"}
                        {if $metrics.last_run_status}
                            <span class="ml-2 align-middle">
                                {if $metrics.last_run_status eq 'success'}
                                    <span class="eb-badge eb-badge--success">{$metrics.last_run_status|ucfirst}</span>
                                {elseif $metrics.last_run_status eq 'failed'}
                                    <span class="eb-badge eb-badge--danger">{$metrics.last_run_status|ucfirst}</span>
                                {elseif $metrics.last_run_status eq 'cancelled'}
                                    <span class="eb-badge eb-badge--warning">{$metrics.last_run_status|ucfirst}</span>
                                {else}
                                    <span class="eb-badge eb-badge--neutral">{$metrics.last_run_status|ucfirst}</span>
                                {/if}
                            </span>
                        {/if}
                    {else}
                        <span class="eb-type-caption">—</span>
                    {/if}
                </div>
            </div>
        </div>

        <div class="eb-subpanel">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <h6 class="eb-field-label">Source</h6>
                    <div class="eb-type-body !text-[var(--eb-text-primary)] !font-medium">{$job.source_display_name}</div>
                </div>
                <div>
                    <h6 class="eb-field-label">Destination</h6>
                    <div class="eb-type-body !text-[var(--eb-text-primary)] !font-medium">
                        {if isset($job.dest_bucket_name) && $job.dest_bucket_name}
                            {$job.dest_bucket_name}{if $job.dest_prefix} / {$job.dest_prefix}{/if}
                        {else}
                            Bucket #{$job.dest_bucket_id}{if $job.dest_prefix} / {$job.dest_prefix}{/if}
                        {/if}
                    </div>
                </div>
                <div>
                    <h6 class="eb-field-label">Schedule</h6>
                    <div class="eb-type-body !text-[var(--eb-text-primary)] !font-medium">{$job.schedule_type|ucfirst}</div>
                </div>
            </div>
        </div>

        <div class="eb-table-shell">
            <table class="eb-table">
                <thead>
                    <tr>
                        <th>Date/time</th>
                        <th>Trigger</th>
                        <th>Status</th>
                        <th>Validation</th>
                        <th>Bytes transferred</th>
                        <th>Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {if count($runs) > 0}
                        {foreach from=$runs item=run}
                            <tr class="run-row cursor-pointer" data-run-id="{$run.run_id}" title="Click to view log">
                                <td class="eb-table-primary">
                                    {if $run.started_at}
                                        {$run.started_at|date_format:"%d %b %Y %H:%M:%S"}
                                    {else}
                                        Queued
                                    {/if}
                                </td>
                                <td>{$run.trigger_type|ucfirst}</td>
                                <td>
                                    {if $run.status eq 'success'}
                                        <span class="eb-badge eb-badge--success">{$run.status|ucfirst}</span>
                                    {elseif $run.status eq 'failed'}
                                        <span class="eb-badge eb-badge--danger">{$run.status|ucfirst}</span>
                                    {elseif $run.status eq 'running' || $run.status eq 'starting'}
                                        <span class="eb-badge eb-badge--info">{$run.status|ucfirst}</span>
                                    {elseif $run.status eq 'warning' || $run.status eq 'cancelled'}
                                        <span class="eb-badge eb-badge--warning">{$run.status|ucfirst}</span>
                                    {else}
                                        <span class="eb-badge eb-badge--neutral">{$run.status|ucfirst}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $run.validation_mode eq 'post_run'}
                                        {if $run.validation_status eq 'success'}
                                            <span class="eb-badge eb-badge--success" title="Validation passed">✓ Valid</span>
                                        {elseif $run.validation_status eq 'failed'}
                                            <span class="eb-badge eb-badge--danger" title="Validation failed">✗ Failed</span>
                                        {elseif $run.validation_status eq 'running'}
                                            <span class="eb-badge eb-badge--info">Running…</span>
                                        {else}
                                            <span class="eb-badge eb-badge--neutral">Pending</span>
                                        {/if}
                                    {else}
                                        <span class="eb-type-caption">—</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $run.bytes_transferred|@strlen}
                                        {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                                    {else}
                                        —
                                    {/if}
                                </td>
                                <td>
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
                                        Running…
                                    {else}
                                        —
                                    {/if}
                                </td>
                                <td>
                                    {if $run.status eq 'running'}
                                        <div class="flex flex-wrap items-center gap-3">
                                            <a href="index.php?m=cloudstorage&page=e3backup&view=live&run_id={$run.run_id}" class="eb-link">View live</a>
                                            <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="showRunDetails('{$run.run_id}')">View log</button>
                                        </div>
                                    {else}
                                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="showRunDetails('{$run.run_id}')">View log</button>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="7" class="!p-0">
                                <div class="eb-app-empty">
                                    <div class="eb-app-empty-title">No runs for this job</div>
                                    <p class="eb-app-empty-copy">Runs will appear here after the job executes.</p>
                                </div>
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>
    {else}
        <div class="grid gap-4 md:grid-cols-5">
            <div class="eb-card !p-4">
                <div class="eb-stat-label">Total jobs</div>
                <div class="eb-type-stat">{if isset($metrics.total_jobs)}{$metrics.total_jobs}{else}{if isset($jobs)}{count($jobs)}{else}0{/if}{/if}</div>
            </div>
            <div class="eb-card !p-4"{if isset($metrics.active) && $metrics.active > 0} style="border-color: var(--eb-success-border);"{/if}>
                <div class="eb-stat-label{if isset($metrics.active) && $metrics.active > 0} !text-[var(--eb-success-text)]{/if}">Active</div>
                <div class="eb-type-stat">{if isset($metrics.active)}{$metrics.active}{else}0{/if}</div>
            </div>
            <div class="eb-card !p-4"{if isset($metrics.paused) && $metrics.paused > 0} style="border-color: var(--eb-warning-border);"{/if}>
                <div class="eb-stat-label{if isset($metrics.paused) && $metrics.paused > 0} !text-[var(--eb-warning-text)]{/if}">Paused</div>
                <div class="eb-type-stat">{if isset($metrics.paused)}{$metrics.paused}{else}0{/if}</div>
            </div>
            <div class="eb-card !p-4"{if isset($metrics.failed_24h) && $metrics.failed_24h > 0} style="border-color: var(--eb-danger-border);"{/if}>
                <div class="eb-stat-label{if isset($metrics.failed_24h) && $metrics.failed_24h > 0} !text-[var(--eb-danger-text)]{/if}">Failed (24h)</div>
                <div class="eb-type-stat">{if isset($metrics.failed_24h)}{$metrics.failed_24h}{else}0{/if}</div>
            </div>
            <div class="eb-card !p-4">
                <div class="eb-stat-label">Last run</div>
                <div class="eb-type-body !mt-1 !text-[var(--eb-text-secondary)]">
                    {if isset($metrics.last_run_started_at) && $metrics.last_run_started_at}
                        {$metrics.last_run_started_at|date_format:"%d %b %Y %H:%M"}
                        {if $metrics.last_run_status}
                            <span class="ml-2 align-middle">
                                {if $metrics.last_run_status eq 'success'}
                                    <span class="eb-badge eb-badge--success">{$metrics.last_run_status|ucfirst}</span>
                                {elseif $metrics.last_run_status eq 'failed'}
                                    <span class="eb-badge eb-badge--danger">{$metrics.last_run_status|ucfirst}</span>
                                {elseif $metrics.last_run_status eq 'cancelled'}
                                    <span class="eb-badge eb-badge--warning">{$metrics.last_run_status|ucfirst}</span>
                                {else}
                                    <span class="eb-badge eb-badge--neutral">{$metrics.last_run_status|ucfirst}</span>
                                {/if}
                            </span>
                        {/if}
                    {else}
                        <span class="eb-type-caption">Never</span>
                    {/if}
                </div>
            </div>
        </div>

        <div class="eb-subpanel">
            <p class="eb-type-body !mb-4 !text-[var(--eb-text-secondary)]">Select a backup job to view its run history.</p>
            {if isset($jobs) && count($jobs) > 0}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {foreach from=$jobs item=j}
                        <a href="index.php?m=cloudstorage&page=e3backup&view=runs&job_id={$j->job_id}" class="eb-card !block !no-underline transition-shadow hover:shadow-[var(--eb-shadow-md)]">
                            <div class="eb-card-title">{$j->name}</div>
                            <div class="eb-type-caption !mt-1">{$j->source_display_name}</div>
                            <div class="eb-type-eyebrow !mt-2">Engine: {$j->engine|default:'sync'|upper}</div>
                            <div class="eb-type-caption !mt-2">
                                Dest bucket
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
                <div class="eb-app-empty">
                    <div class="eb-app-empty-title">No backup jobs</div>
                    <p class="eb-app-empty-copy">Create a job from a user detail page to see runs here.</p>
                </div>
            {/if}
        </div>
    {/if}
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage=''
    ebE3Title='Run history'
    ebE3Description='Inspect backup runs, validation, and logs.'
    ebE3Content=$ebE3Content
}

<!-- Run details modal -->
<div id="runDetailsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6">
    <div class="eb-modal-backdrop fixed inset-0" onclick="closeModal('runDetailsModal')" role="presentation"></div>
    <div class="eb-modal relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden">
        <div class="eb-modal-header !shrink-0">
            <div>
                <h2 class="eb-modal-title">Run details</h2>
                <p class="eb-modal-subtitle">Backup and validation log excerpts</p>
            </div>
            <button type="button" class="eb-modal-close" onclick="closeModal('runDetailsModal')" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="runDetailsContent" class="eb-modal-body min-h-0 flex-1 overflow-y-auto">
            <div id="runDetailsLog" class="mb-4">
                <h3 class="eb-type-h3 !mb-2">Backup log</h3>
                <pre class="eb-type-mono max-h-64 overflow-auto whitespace-pre-wrap rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-base)] p-4 text-[length:var(--eb-type-mono-size)] leading-relaxed text-[var(--eb-text-secondary)]" id="logExcerpt"></pre>
            </div>
            <div id="runDetailsValidation" class="hidden">
                <h3 class="eb-type-h3 !mb-2">Validation log</h3>
                <pre class="eb-type-mono max-h-64 overflow-auto whitespace-pre-wrap rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-base)] p-4 text-[length:var(--eb-type-mono-size)] leading-relaxed text-[var(--eb-text-secondary)]" id="validationLogExcerpt"></pre>
            </div>
        </div>
    </div>
</div>

<script>
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('flex');
}

function showRunDetails(runId) {
    const modal = document.getElementById('runDetailsModal');
    const logExcerpt = document.getElementById('logExcerpt');
    const validationLogExcerpt = document.getElementById('validationLogExcerpt');
    const validationSection = document.getElementById('runDetailsValidation');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    logExcerpt.textContent = 'Loading log details...';
    if (validationLogExcerpt) {
        validationLogExcerpt.textContent = '';
    }
    if (validationSection) {
        validationSection.classList.add('hidden');
    }

    fetch('modules/addons/cloudstorage/api/cloudbackup_get_run_logs.php?run_uuid=' + encodeURIComponent(runId) + '&limit=5000')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const structured = Array.isArray(data.structured_logs) ? data.structured_logs : [];
                const lines = structured.map(row => {
                    const ts = row.ts ? '[' + row.ts + '] ' : '';
                    const lvl = row.level ? '(' + row.level + ') ' : '';
                    return ts + lvl + (row.message || '');
                });
                const fallbackLog = data.backup_log || '';
                logExcerpt.textContent = lines.length > 0 ? lines.join('\n') : (fallbackLog || 'No log data available for this run.');

                if (data.has_validation && data.validation_log) {
                    if (validationSection) validationSection.classList.remove('hidden');
                    validationLogExcerpt.textContent = data.validation_log;
                } else {
                    if (validationSection) validationSection.classList.add('hidden');
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

document.querySelectorAll('tr[data-run-id]').forEach(row => {
    row.addEventListener('click', event => {
        if (event.target.closest('a,button')) {
            return;
        }
        const runId = row.getAttribute('data-run-id');
        if (runId) {
            showRunDetails(runId);
        }
    });
});
</script>
