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
                                {elseif $metrics.last_run_status eq 'cancelled' || $metrics.last_run_status eq 'warning' || $metrics.last_run_status eq 'partial_success'}
                                    <span class="eb-badge eb-badge--warning">{if $metrics.last_run_status eq 'partial_success'}Partial Success{else}{$metrics.last_run_status|ucfirst}{/if}</span>
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
                            {capture assign=runSize}{if $run.bytes_transferred|@strlen}{\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnitsPlain($run.bytes_transferred)}{else}—{/if}{/capture}
                            {capture assign=runDuration}{if $run.started_at && $run.finished_at}{assign var="start" value=$run.started_at|strtotime}{assign var="end" value=$run.finished_at|strtotime}{assign var="dur" value=$end-$start}{if $dur < 60}{$dur}s{elseif $dur < 3600}{math equation="floor(x/60)" x=$dur}m {$dur%60}s{else}{math equation="floor(x/3600)" x=$dur}h {math equation="floor((x%3600)/60)" x=$dur}m{/if}{elseif $run.status eq 'running'}Running…{else}—{/if}{/capture}
                            <tr class="run-row cursor-pointer"
                                data-run-id="{$run.run_id}"
                                data-status="{$run.status}"
                                data-started="{if $run.started_at}{$run.started_at|date_format:"%d %b %Y %H:%M:%S"}{else}Queued{/if}"
                                data-finished="{if $run.finished_at}{$run.finished_at|date_format:"%d %b %Y %H:%M:%S"}{else}—{/if}"
                                data-size="{$runSize|trim}"
                                data-duration="{$runDuration|trim}"
                                title="Click to view log">
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
                                    {elseif $run.status eq 'warning' || $run.status eq 'cancelled' || $run.status eq 'partial_success'}
                                        <span class="eb-badge eb-badge--warning">{if $run.status eq 'partial_success'}Partial Success{else}{$run.status|ucfirst}{/if}</span>
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
                                <td>{$runSize}</td>
                                <td>{$runDuration}</td>
                                <td>
                                    {if $run.status eq 'running' || $run.status eq 'starting'}
                                        <div class="flex flex-wrap items-center gap-3">
                                            <a href="index.php?m=cloudstorage&page=e3backup&view=live&run_id={$run.run_id}" class="eb-link">View live</a>
                                            <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="e3RunsOpenLog('{$run.run_id}', this.closest('tr'))">View log</button>
                                        </div>
                                    {else}
                                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" onclick="e3RunsOpenLog('{$run.run_id}', this.closest('tr'))">View log</button>
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
                                {elseif $metrics.last_run_status eq 'cancelled' || $metrics.last_run_status eq 'warning' || $metrics.last_run_status eq 'partial_success'}
                                    <span class="eb-badge eb-badge--warning">{if $metrics.last_run_status eq 'partial_success'}Partial Success{else}{$metrics.last_run_status|ucfirst}{/if}</span>
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
                            {assign var=eng value=$j->engine|default:'sync'|lower}
                            <div class="eb-type-eyebrow !mt-2">Engine: {if $eng == 'kopia' || $eng == 'sync'}File/Folder{elseif $eng == 'disk_image'}Disk Image{elseif $eng == 'hyperv'}Hyper-V{else}{$eng|escape}{/if}</div>
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

{* Shared run-log modal (same partial used by Job Logs + Dashboard history). *}
{include file="modules/addons/cloudstorage/templates/partials/e3backup_run_log_modal.tpl"}

<script>
{if isset($job)}
window.__e3RunsJobName = {$job.name|@json_encode nofilter};
window.__e3RunsAgent = {if isset($job.agent_hostname)}{$job.agent_hostname|@json_encode nofilter}{else}""{/if};
window.__e3RunsUser = {if isset($job.backup_username)}{$job.backup_username|@json_encode nofilter}{else}""{/if};
window.__e3RunsEngine = {if isset($job.is_ms365) && $job.is_ms365}"ms365"{else}{if isset($job.engine)}{$job.engine|@json_encode nofilter}{else}""{/if}{/if};
window.__e3RunsJobId = {$job.job_id|@json_encode nofilter};
{/if}
</script>
<script>
{literal}
function e3RunsOpenLog(runId, row) {
    if (!window.ebE3RunModal) return;
    var meta = {
        jobName: window.__e3RunsJobName || 'Backup run',
        agent: window.__e3RunsAgent || '',
        user: window.__e3RunsUser || '',
        engine: window.__e3RunsEngine || ''
    };
    if (row) {
        meta.status = row.getAttribute('data-status') || '';
        meta.started = row.getAttribute('data-started') || '';
        meta.finished = row.getAttribute('data-finished') || '';
        meta.sizeText = row.getAttribute('data-size') || '';
        meta.durationText = row.getAttribute('data-duration') || '';
    }
    window.ebE3RunModal.open(runId, meta);
}

document.querySelectorAll('tr[data-run-id]').forEach(function (row) {
    row.addEventListener('click', function (event) {
        if (event.target.closest('a,button')) return;
        var runId = row.getAttribute('data-run-id');
        if (runId) e3RunsOpenLog(runId, row);
    });
});
{/literal}
</script>
