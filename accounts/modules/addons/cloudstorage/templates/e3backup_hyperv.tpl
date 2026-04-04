{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--info">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h14M5 12a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2M5 12a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2m-2-4h.01M17 16h.01"/>
        </svg>
    </span>
{/capture}

{capture assign=ebE3Actions}
    {if $selectedJob}
        <button type="button" onclick="refreshVMDiscovery('{$selectedJob.job_id|escape:'javascript'}')" class="eb-btn eb-btn-info eb-btn-sm">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
            </svg>
            <span>Refresh VM Discovery</span>
        </button>
    {/if}
{/capture}

{capture assign=ebE3Content}
<div class="space-y-6">
    {if $selectedJob}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv" class="eb-btn eb-btn-ghost eb-btn-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span>Back to Jobs</span>
            </a>
            <span class="eb-badge eb-badge--neutral">{$vms|@count} VMs</span>
        </div>

        <section class="eb-card-raised !p-0 overflow-hidden">
            <div class="eb-card-header eb-card-header--divided !mb-0">
                <div>
                    <h2 class="eb-card-title">{$selectedJob.name|escape}</h2>
                    <p class="eb-card-subtitle">Manage virtual machine backups with application-consistent snapshots and incremental RCT for this job.</p>
                </div>
            </div>

            {if $selectedJob.hyperv_config}
                <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-4">
                    <div class="eb-card">
                        <div class="eb-type-eyebrow">RCT Enabled</div>
                        <div class="mt-3">
                            {if $selectedJob.hyperv_config.enable_rct}
                                <span class="eb-badge eb-badge--success">
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Yes</span>
                                </span>
                            {else}
                                <span class="eb-badge eb-badge--neutral">No</span>
                            {/if}
                        </div>
                    </div>
                    <div class="eb-card">
                        <div class="eb-type-eyebrow">Consistency</div>
                        <div class="mt-3 text-sm font-medium text-[var(--eb-text-primary)]">{$selectedJob.hyperv_config.consistency_level|default:'Application'|capitalize}</div>
                    </div>
                    <div class="eb-card">
                        <div class="eb-type-eyebrow">VMs Configured</div>
                        <div class="mt-3 text-sm font-medium text-[var(--eb-text-primary)]">{$vms|@count}</div>
                    </div>
                    <div class="eb-card">
                        <div class="eb-type-eyebrow">Backup All VMs</div>
                        <div class="mt-3 text-sm font-medium text-[var(--eb-text-primary)]">{if $selectedJob.hyperv_config.backup_all_vms}Yes{else}No{/if}</div>
                    </div>
                </div>
            {/if}
        </section>

        <section class="eb-table-shell">
            <div class="eb-card-header eb-card-header--divided !mb-0">
                <div>
                    <h2 class="eb-card-title">Virtual Machines</h2>
                    <p class="eb-card-subtitle">Review discovered VMs, restore disks, and control whether each machine is included in backups.</p>
                </div>
            </div>

            {if $vms|@count > 0}
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>VM Name</th>
                            <th>Type</th>
                            <th>Disks</th>
                            <th>RCT</th>
                            <th>Last Backup</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $vms as $vm}
                            <tr>
                                <td class="eb-table-primary">
                                    <div class="flex items-center gap-3">
                                        <span class="eb-icon-box eb-icon-box--sm {if $vm.is_linux}eb-icon-box--orange{else}eb-icon-box--info{/if}">
                                            {if $vm.is_linux}
                                                <svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M12.503 18.668s-.218.125-.492.125c-.274 0-.493-.125-.493-.125-.562 0-.887.875-.887 1.5 0 .413.35.82.713 1.083.413.3 1.113.625 1.167.625l.167-.625c.054 0 .754-.325 1.167-.625.363-.263.713-.67.713-1.083 0-.625-.325-1.5-.887-1.5z"/>
                                                </svg>
                                            {else}
                                                <svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-12.9-1.801"/>
                                                </svg>
                                            {/if}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-medium text-[var(--eb-text-primary)]">{$vm.vm_name|escape}</div>
                                            <div class="eb-table-mono mt-1">{$vm.vm_guid|escape|truncate:20:'...'}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-[var(--eb-text-secondary)]">
                                    {if $vm.is_linux}Linux{else}Windows{/if} Gen{$vm.generation}
                                </td>
                                <td class="text-[var(--eb-text-secondary)]">{$vm.disk_count}</td>
                                <td>
                                    {if $vm.rct_enabled}
                                        <span class="eb-badge eb-badge--success">Enabled</span>
                                    {else}
                                        <span class="eb-badge eb-badge--neutral">Disabled</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $vm.last_backup}
                                        <div class="space-y-1">
                                            <span class="eb-badge {if $vm.last_backup.type == 'Full'}eb-badge--info{else}eb-badge--warning{/if}">{$vm.last_backup.type}</span>
                                            <div class="text-xs text-[var(--eb-text-muted)]">{$vm.last_backup.created_at|date_format:'%Y-%m-%d %H:%M'}</div>
                                        </div>
                                    {else}
                                        <span class="text-xs text-[var(--eb-text-muted)]">Never</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $vm.backup_enabled}
                                        <span class="eb-badge eb-badge--success eb-badge--dot">Active</span>
                                    {else}
                                        <span class="eb-badge eb-badge--neutral">Excluded</span>
                                    {/if}
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv_restore&vm_id={$vm.id}" class="eb-btn eb-btn-info eb-btn-xs" title="Restore VM Disks">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
                                            </svg>
                                            <span>Restore</span>
                                        </a>
                                        <button type="button" onclick="toggleVMBackup({$vm.id}, {if $vm.backup_enabled}false{else}true{/if})" class="eb-btn {if $vm.backup_enabled}eb-btn-secondary{else}eb-btn-success{/if} eb-btn-xs">
                                            {if $vm.backup_enabled}Exclude{else}Include{/if}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            {else}
                <div class="eb-app-empty !py-12">
                    <span class="eb-icon-box eb-icon-box--lg eb-icon-box--default mx-auto">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/>
                        </svg>
                    </span>
                    <div class="eb-app-empty-title mt-4">No VMs discovered yet</div>
                    <p class="eb-app-empty-copy">Click "Refresh VM Discovery" to scan for virtual machines.</p>
                </div>
            {/if}
        </section>
    {else}
        {if $hypervJobs|@count > 0}
            <div class="grid gap-4 xl:grid-cols-2">
                {foreach $hypervJobs as $job}
                    <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id={$job.job_id}" class="eb-card block">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-4">
                                <span class="eb-icon-box eb-icon-box--info">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h14M5 12a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2M5 12a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2"/>
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="eb-card-title">{$job.name|escape}</div>
                                    <p class="eb-card-subtitle">{$job.source_display_name|escape|default:'Hyper-V Host'}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="eb-badge {if $job.status == 'active'}eb-badge--success eb-badge--dot{else}eb-badge--neutral{/if}">{$job.status|capitalize}</span>
                                <svg class="h-4 w-4 text-[var(--eb-text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                {/foreach}
            </div>
        {else}
            <section class="eb-subpanel">
                <div class="eb-app-empty !py-12">
                    <span class="eb-icon-box eb-icon-box--lg eb-icon-box--default mx-auto">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h14M5 12a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2M5 12a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                    </span>
                    <div class="eb-app-empty-title mt-4">No Hyper-V Jobs Configured</div>
                    <p class="eb-app-empty-copy">Create a new backup job with Hyper-V as the source type to get started.</p>
                    <div class="mt-6">
                        <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="eb-btn eb-btn-orange eb-btn-sm">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Create Hyper-V Job</span>
                        </a>
                    </div>
                </div>
            </section>
        {/if}
    {/if}
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='hyperv'
    ebE3Title='Hyper-V Backup'
    ebE3Description='Manage virtual machine backups with application-consistent snapshots and incremental RCT.'
    ebE3Icon=$ebE3Icon
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

{literal}
<script>
async function refreshVMDiscovery(jobId) {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Scanning...';
    
    try {
        // This would trigger the agent to discover VMs
        // For now, just reload the page
        alert('VM discovery will be triggered on the next agent check-in. Please wait a moment and refresh the page.');
        window.location.reload();
    } catch (e) {
        alert('Discovery failed: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function toggleVMBackup(vmId, enable) {
    try {
        const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_hyperv_toggle_vm.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ vm_id: vmId, enabled: enable ? '1' : '0' })
        });
        const data = await res.json();
        if (data.status !== 'success') {
            alert(data.message || 'Failed to update VM');
            return;
        }
        window.location.reload();
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>
{/literal}
