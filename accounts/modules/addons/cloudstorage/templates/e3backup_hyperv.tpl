<div class="min-h-screen bg-slate-950 text-gray-200">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        <!-- Navigation Tabs -->
        {assign var="activeNav" value="hyperv"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-white flex items-center gap-2">
                    <svg class="w-7 h-7 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                    </svg>
                    Hyper-V Backup
                </h1>
                <p class="text-xs text-slate-400 mt-1">Manage virtual machine backups with application-consistent snapshots and incremental RCT.</p>
            </div>
        </div>

        {if $selectedJob}
        <!-- Job Details View -->
        <div class="mb-4">
            <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv" class="text-sm text-sky-400 hover:text-sky-300 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Jobs
            </a>
        </div>

        <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">{$selectedJob.name|escape}</h2>
                    <p class="text-sm text-slate-400">Job ID: {$selectedJob.id}</p>
                </div>
                <button onclick="refreshVMDiscovery({$selectedJob.id})" class="px-4 py-2 rounded-md bg-sky-600 text-white text-sm font-semibold hover:bg-sky-500 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Refresh VM Discovery
                </button>
            </div>

            {if $selectedJob.hyperv_config}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <p class="text-slate-400 text-xs uppercase tracking-wide">RCT Enabled</p>
                    <p class="text-white font-medium mt-1">
                        {if $selectedJob.hyperv_config.enable_rct}
                        <span class="inline-flex items-center gap-1 text-emerald-300">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Yes
                        </span>
                        {else}
                        <span class="text-slate-400">No</span>
                        {/if}
                    </p>
                </div>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <p class="text-slate-400 text-xs uppercase tracking-wide">Consistency</p>
                    <p class="text-white font-medium mt-1">{$selectedJob.hyperv_config.consistency_level|default:'Application'|capitalize}</p>
                </div>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <p class="text-slate-400 text-xs uppercase tracking-wide">VMs Configured</p>
                    <p class="text-white font-medium mt-1">{$vms|@count}</p>
                </div>
                <div class="bg-slate-800/50 rounded-lg p-3">
                    <p class="text-slate-400 text-xs uppercase tracking-wide">Backup All VMs</p>
                    <p class="text-white font-medium mt-1">{if $selectedJob.hyperv_config.backup_all_vms}Yes{else}No{/if}</p>
                </div>
            </div>
            {/if}
        </div>

        <!-- VMs Table -->
        <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Virtual Machines</h3>
            </div>
            {if $vms|@count > 0}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-800/50 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">VM Name</th>
                            <th class="px-4 py-3 text-left font-medium">Type</th>
                            <th class="px-4 py-3 text-left font-medium">Disks</th>
                            <th class="px-4 py-3 text-left font-medium">RCT</th>
                            <th class="px-4 py-3 text-left font-medium">Last Backup</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        {foreach $vms as $vm}
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    {if $vm.is_linux}
                                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12.503 18.668s-.218.125-.492.125c-.274 0-.493-.125-.493-.125-.562 0-.887.875-.887 1.5 0 .413.35.82.713 1.083.413.3 1.113.625 1.167.625l.167-.625c.054 0 .754-.325 1.167-.625.363-.263.713-.67.713-1.083 0-.625-.325-1.5-.887-1.5z"/>
                                    </svg>
                                    {else}
                                    <svg class="w-5 h-5 text-sky-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-12.9-1.801"/>
                                    </svg>
                                    {/if}
                                    <div>
                                        <p class="text-white font-medium">{$vm.vm_name|escape}</p>
                                        <p class="text-xs text-slate-500 font-mono">{$vm.vm_guid|escape|truncate:20:'...'}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {if $vm.is_linux}Linux{else}Windows{/if} Gen{$vm.generation}
                            </td>
                            <td class="px-4 py-3 text-slate-300">{$vm.disk_count}</td>
                            <td class="px-4 py-3">
                                {if $vm.rct_enabled}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-300 border border-emerald-400/40">
                                    Enabled
                                </span>
                                {else}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-400">
                                    Disabled
                                </span>
                                {/if}
                            </td>
                            <td class="px-4 py-3">
                                {if $vm.last_backup}
                                <div>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium 
                                        {if $vm.last_backup.type == 'Full'}bg-sky-500/15 text-sky-300{else}bg-amber-500/15 text-amber-300{/if}">
                                        {$vm.last_backup.type}
                                    </span>
                                    <p class="text-xs text-slate-500 mt-1">{$vm.last_backup.created_at|date_format:'%Y-%m-%d %H:%M'}</p>
                                </div>
                                {else}
                                <span class="text-slate-500 text-xs">Never</span>
                                {/if}
                            </td>
                            <td class="px-4 py-3">
                                {if $vm.backup_enabled}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-300">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                    Active
                                </span>
                                {else}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-400">
                                    Excluded
                                </span>
                                {/if}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv_restore&vm_id={$vm.id}" 
                                       class="text-xs px-2 py-1 rounded bg-sky-600/20 border border-sky-500/40 text-sky-300 hover:bg-sky-600/30 hover:border-sky-400 transition flex items-center gap-1"
                                       title="Restore VM Disks">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        Restore
                                    </a>
                                    <button onclick="toggleVMBackup({$vm.id}, {if $vm.backup_enabled}false{else}true{/if})" 
                                            class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 hover:border-slate-500 transition">
                                        {if $vm.backup_enabled}Exclude{else}Include{/if}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="px-6 py-12 text-center">
                <svg class="w-12 h-12 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <p class="text-slate-400">No VMs discovered yet.</p>
                <p class="text-sm text-slate-500 mt-1">Click "Refresh VM Discovery" to scan for virtual machines.</p>
            </div>
            {/if}
        </div>

        {else}
        <!-- Jobs List View -->
        {if $hypervJobs|@count > 0}
        <div class="grid gap-4">
            {foreach $hypervJobs as $job}
            <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id={$job.id}" 
               class="block rounded-xl border border-slate-800/80 bg-slate-900/70 p-6 hover:border-slate-700 transition group">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-slate-800/90 group-hover:bg-slate-700 transition">
                            <svg class="w-6 h-6 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white group-hover:text-sky-300 transition">{$job.name|escape}</h3>
                            <p class="text-sm text-slate-400">{$job.source_display_name|escape|default:'Hyper-V Host'}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium 
                            {if $job.status == 'active'}bg-emerald-500/15 text-emerald-300{else}bg-slate-700 text-slate-400{/if}">
                            <span class="h-1.5 w-1.5 rounded-full {if $job.status == 'active'}bg-emerald-400{else}bg-slate-500{/if}"></span>
                            {$job.status|capitalize}
                        </span>
                        <svg class="w-5 h-5 text-slate-500 group-hover:text-slate-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </a>
            {/foreach}
        </div>
        {else}
        <!-- Empty State -->
        <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
            </svg>
            <h3 class="text-xl font-semibold text-white mb-2">No Hyper-V Jobs Configured</h3>
            <p class="text-slate-400 mb-6">Create a new backup job with Hyper-V as the source type to get started.</p>
            <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" 
               class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-gradient-to-r from-[#FE5000] via-[#FF7A33] to-[#FF924D] text-slate-950 font-semibold text-sm hover:shadow-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create Hyper-V Job
            </a>
        </div>
        {/if}
        {/if}
    </div>
</div>

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

