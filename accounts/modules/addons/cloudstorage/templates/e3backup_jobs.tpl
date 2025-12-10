<div class="min-h-screen bg-slate-950 text-gray-200" x-data="jobsApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Jobs</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Backup Jobs</h1>
                <p class="text-xs text-slate-400 mt-1">View and filter backup jobs. MSPs can filter by tenant and agent.</p>
            </div>
            <div class="flex gap-2 mt-4 sm:mt-0">
                <!-- Create Job Dropdown -->
                <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
                    <button @click="isOpen = !isOpen" class="px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold inline-flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Create Job
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <!-- Dropdown Menu -->
                    <div x-show="isOpen" 
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                         style="display: none;">
                        <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                            SELECT BACKUP SOURCE
                        </div>
                        <div class="py-1">
                            <!-- Cloud Backup Option -->
                            <button type="button" 
                                    @click="isOpen = false; window.openCloudBackupWizard()"
                                    class="w-full px-4 py-3 flex items-start gap-3 hover:bg-slate-800/60 transition text-left">
                                <div class="w-10 h-10 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-100">Cloud Backup</p>
                                    <p class="text-xs text-slate-400 mt-0.5">S3, AWS, SFTP, Google Drive, Dropbox</p>
                                </div>
                                <svg class="w-4 h-4 text-slate-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <!-- Local Backup Option -->
                            <button type="button" 
                                    @click="isOpen = false; window.openLocalJobWizard()"
                                    class="w-full px-4 py-3 flex items-start gap-3 hover:bg-slate-800/60 transition text-left">
                                <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-100">Local Agent Backup</p>
                                    <p class="text-xs text-slate-400 mt-0.5">File, Disk Image, Windows Agent</p>
                                </div>
                                <svg class="w-4 h-4 text-slate-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {if $isMspClient}
        <!-- Filters (MSP) -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            <div class="flex items-center gap-2">
                <label class="text-sm text-slate-400">Tenant:</label>
                <select x-model="tenantFilter" @change="onTenantChange" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <option value="">All Tenants</option>
                    <option value="direct">Direct (No Tenant)</option>
                    {foreach from=$tenants item=tenant}
                    <option value="{$tenant->id}">{$tenant->name|escape}</option>
                    {/foreach}
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-slate-400">Agent:</label>
                <select x-model="agentFilter" @change="loadJobs" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <option value="">All Agents</option>
                    <template x-for="agent in filteredAgents" :key="agent.id">
                        <option :value="agent.id" x-text="agent.hostname"></option>
                    </template>
                </select>
            </div>
        </div>
        {/if}

        <!-- Jobs Table -->
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Job</th>
                        {if $isMspClient}<th class="px-4 py-3 text-left font-medium">Tenant</th>{/if}
                        <th class="px-4 py-3 text-left font-medium">Agent</th>
                        <th class="px-4 py-3 text-left font-medium">Source</th>
                        <th class="px-4 py-3 text-left font-medium">Engine</th>
                        <th class="px-4 py-3 text-left font-medium">Schedule</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Created</th>
                        <th class="px-4 py-3 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <template x-if="loading">
                        <tr>
                            <td colspan="{if $isMspClient}9{else}8{/if}" class="px-4 py-8 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && jobs.length === 0">
                        <tr>
                            <td colspan="{if $isMspClient}9{else}8{/if}" class="px-4 py-8 text-center text-slate-400">
                                No jobs found.
                            </td>
                        </tr>
                    </template>
                    <template x-for="job in jobs" :key="job.id">
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3 text-slate-200 font-semibold" x-text="job.name"></td>
                            {if $isMspClient}
                            <td class="px-4 py-3 text-slate-300" x-text="job.tenant_name || 'Direct'"></td>
                            {/if}
                            <td class="px-4 py-3 text-slate-300" x-text="job.agent_hostname || ('Agent #' + job.agent_id)"></td>
                            <td class="px-4 py-3 text-slate-300">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="sourceClass(job.source_type)">
                                    <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                                    <span x-text="job.source_type"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="job.engine"></td>
                            <td class="px-4 py-3 text-slate-300" x-text="job.schedule_type"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                      :class="statusClass(job.status)">
                                    <span class="h-1.5 w-1.5 rounded-full"
                                          :class="dotClass(job.status)"></span>
                                    <span x-text="job.status"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="job.created_at"></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <!-- Run Now -->
                                    <button @click="runJob(job.id)" class="p-1.5 rounded hover:bg-slate-700 text-sky-400 hover:text-sky-300" title="Run Now">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </button>
                                    <!-- Pause/Resume -->
                                    <button @click="toggleJobStatus(job.id, job.status)" class="p-1.5 rounded hover:bg-slate-700" :class="job.status === 'active' ? 'text-amber-400 hover:text-amber-300' : 'text-emerald-400 hover:text-emerald-300'" :title="job.status === 'active' ? 'Pause' : 'Resume'">
                                        <svg x-show="job.status === 'active'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                                        </svg>
                                        <svg x-show="job.status !== 'active'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </button>
                                    <!-- Restore -->
                                    <button @click="openRestoreModal(job.id)" class="p-1.5 rounded hover:bg-slate-700 text-emerald-400 hover:text-emerald-300" title="Restore">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                        </svg>
                                    </button>
                                    <!-- View Logs -->
                                    <button @click="viewLogs(job.id)" class="p-1.5 rounded hover:bg-slate-700 text-slate-400 hover:text-slate-300" title="View Logs">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </button>
                                    <!-- Delete -->
                                    <button @click="deleteJob(job.id, job.name)" class="p-1.5 rounded hover:bg-slate-700 text-rose-400 hover:text-rose-300" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Include the Job Creation Wizard Slide-Over -->
{include file="modules/addons/cloudstorage/templates/partials/job_create_wizard.tpl"}

<!-- Restore Wizard Modal -->
<div id="restoreWizardModal" class="fixed inset-0 z-[2100] hidden">
    <div class="absolute inset-0 bg-black/75" onclick="closeRestoreModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800">
                <div>
                    <p class="text-xs uppercase text-slate-400 tracking-wide">Restore</p>
                    <h3 class="text-xl font-semibold text-white">Restore Snapshot</h3>
                    <p class="text-[11px] text-slate-400 mt-1">Select a snapshot (recent run), choose a target path, and optionally request a mount.</p>
                </div>
                <button class="p-2 rounded hover:bg-slate-800 text-slate-400 hover:text-white" onclick="closeRestoreModal()" aria-label="Close wizard">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">
                    <span class="px-2 py-1 rounded-full border border-slate-700 bg-slate-900" id="restoreStepLabel">Step 1 of 3</span>
                    <span class="text-slate-300" id="restoreStepTitle">Select Snapshot</span>
                </div>

                <div class="space-y-6">
                    <!-- Step 1 -->
                    <div class="restore-step" data-step="1">
                        <label class="block text-sm font-medium text-slate-200 mb-2">Snapshot (from recent runs)</label>
                        <div class="mb-3">
                            <select id="restoreRunSelect" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100">
                                <option value="">Loading runs…</option>
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Pick a run whose snapshot you want to restore.</p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="restore-step hidden" data-step="2">
                        <label class="block text-sm font-medium text-slate-200 mb-2">Restore Target</label>
                        <div class="space-y-3">
                            <input id="restoreTargetPath" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100" placeholder="Destination path on agent (e.g., C:\Restores\job123)">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input id="restoreMount" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                <span>Request mount instead of copy</span>
                            </label>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="restore-step hidden" data-step="3">
                        <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3 text-slate-100">
                            <p class="text-sm font-semibold mb-2">Review</p>
                            <div id="restoreReview" class="text-xs whitespace-pre-wrap leading-5 bg-slate-950 border border-slate-800 rounded-lg p-3 overflow-auto max-h-64"></div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mt-6">
                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="restorePrev()">Back</button>
                    <div class="flex gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="closeRestoreModal()">Cancel</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-500" onclick="restoreNext()">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
function jobsApp() {
    return {
        jobs: [],
        loading: true,
        tenantFilter: '',
        agentFilter: '',
        agents: {/literal}{if $agents}{$agents|@json_encode}{else}[]{/if}{literal},
        get filteredAgents() {
            if (!this.tenantFilter || this.tenantFilter === 'direct') {
                return this.tenantFilter === 'direct'
                    ? this.agents.filter(a => !a.tenant_id)
                    : this.agents;
            }
            return this.agents.filter(a => String(a.tenant_id) === String(this.tenantFilter));
        },
        init() { this.loadJobs(); },
        onTenantChange() {
            this.agentFilter = '';
            this.loadJobs();
        },
        async loadJobs() {
            this.loading = true;
            try {
                let url = 'modules/addons/cloudstorage/api/e3backup_job_list.php';
                const params = new URLSearchParams();
                if (this.tenantFilter) params.append('tenant_id', this.tenantFilter);
                if (this.agentFilter) params.append('agent_id', this.agentFilter);
                if ([...params].length) url += '?' + params.toString();
                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    this.jobs = data.jobs || [];
                } else {
                    console.error(data.message);
                }
            } catch (e) {
                console.error('Failed to load jobs:', e);
            }
            this.loading = false;
        },
        openCreateJobModal() {
            window.openCreateJobModal();
        },
        // Job action handlers
        async runJob(jobId) {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_start_run.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ job_id: jobId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    const runParam = data.run_uuid || data.run_id;
                    if (window.toast) toast.success('Backup started! Redirecting to progress...');
                    setTimeout(() => {
                        window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=live&run_id=' + runParam;
                    }, 500);
                } else {
                    if (window.toast) toast.error(data.message || 'Failed to start backup');
                    else alert(data.message || 'Failed to start backup');
                }
            } catch (e) {
                if (window.toast) toast.error('Error starting backup');
                else alert('Error starting backup');
            }
        },
        async toggleJobStatus(jobId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'paused' : 'active';
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_update_job.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ job_id: jobId, status: newStatus })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    if (window.toast) toast.success('Job ' + (newStatus === 'paused' ? 'paused' : 'resumed'));
                    this.loadJobs();
                } else {
                    if (window.toast) toast.error(data.message || 'Failed to update job');
                    else alert(data.message || 'Failed to update job');
                }
            } catch (e) {
                if (window.toast) toast.error('Error updating job');
                else alert('Error updating job');
            }
        },
        async deleteJob(jobId, jobName) {
            if (!confirm('Are you sure you want to delete job "' + (jobName || jobId) + '"? This cannot be undone.')) {
                return;
            }
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_delete_job.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ job_id: jobId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    if (window.toast) toast.success('Job deleted');
                    this.loadJobs();
                } else {
                    if (window.toast) toast.error(data.message || 'Failed to delete job');
                    else alert(data.message || 'Failed to delete job');
                }
            } catch (e) {
                if (window.toast) toast.error('Error deleting job');
                else alert('Error deleting job');
            }
        },
        viewLogs(jobId) {
            window.location.href = 'index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs&job_id=' + encodeURIComponent(jobId);
        },
        openRestoreModal(jobId) {
            window.openRestoreModal(jobId);
        },
        statusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'active') return 'bg-emerald-500/15 text-emerald-200';
            if (s === 'paused') return 'bg-amber-500/15 text-amber-200';
            return 'bg-slate-700 text-slate-300';
        },
        dotClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'active') return 'bg-emerald-400';
            if (s === 'paused') return 'bg-amber-400';
            return 'bg-slate-500';
        },
        sourceClass(type) {
            return 'bg-sky-500/15 text-sky-200';
        }
    }
}

// --- MSP Tenant Filter Component ---
function mspTenantFilter() {
    return {
        selectedTenant: '',
        allAgents: [],
        
        init() {
            // Read agents from data attribute
            const agentsData = this.$el.dataset.agents;
            try {
                this.allAgents = agentsData ? JSON.parse(agentsData) : [];
            } catch (e) {
                console.warn('Failed to parse agents data:', e);
                this.allAgents = [];
            }
            
            // Watch for tenant changes and update agent dropdown
            this.$watch('selectedTenant', () => {
                const agentSelect = document.getElementById('agent_id');
                if (agentSelect) {
                    agentSelect.innerHTML = '<option value="">Select an agent</option>';
                    this.filteredAgents.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = a.hostname ? (a.hostname + ' (ID ' + a.id + ')') : ('Agent #' + a.id);
                        agentSelect.appendChild(opt);
                    });
                }
            });
        },
        
        get filteredAgents() {
            if (!this.selectedTenant) return this.allAgents;
            if (this.selectedTenant === 'direct') return this.allAgents.filter(a => !a.tenant_id);
            return this.allAgents.filter(a => String(a.tenant_id) === String(this.selectedTenant));
        }
    };
}

// --- Job Creation Wizard Functions ---

// Cloud Backup Wizard (Slideover)
function openCloudBackupWizard() {
    const panel = document.getElementById('createJobSlideover');
    if (!panel) return;
    panel.style.setProperty('display', 'block', 'important');
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black\\/75');
    if (backdrop) backdrop.style.setProperty('display', 'block', 'important');
    const panelContent = panel.querySelector('.absolute.right-0.top-0');
    if (panelContent) panelContent.style.setProperty('display', 'block', 'important');
    if (window.Alpine) {
        try {
            if (!panel.__x && typeof Alpine.initTree === 'function') {
                Alpine.initTree(panel);
            }
            setTimeout(() => {
                if (panel.__x && panel.__x.$data) {
                    panel.__x.$data.isOpen = true;
                }
            }, 0);
        } catch (e) {}
    }
    applyInitialSourceState();
}

// Legacy alias for backwards compatibility
function openCreateJobModal() {
    openCloudBackupWizard();
}

function closeCreateSlideover() {
    const panel = document.getElementById('createJobSlideover');
    if (!panel) return;
    panel.style.setProperty('display', 'none', 'important');
    if (panel.__x && panel.__x.$data) {
        panel.__x.$data.isOpen = false;
    }
}

function applyInitialSourceState() {
    const sourceType = document.getElementById('sourceType');
    if (sourceType) {
        onSourceTypeChange(sourceType.value);
    }
}

function onSourceTypeChange(value) {
    document.querySelectorAll('.source-type-fields').forEach(el => el.classList.add('hidden'));
    const warning = document.getElementById('sourceAccessWarning');
    if (warning) warning.classList.add('hidden');
    
    if (value === 's3_compatible') {
        document.getElementById('s3Fields')?.classList.remove('hidden');
        if (warning) warning.classList.remove('hidden');
    } else if (value === 'aws') {
        document.getElementById('awsFields')?.classList.remove('hidden');
        if (warning) warning.classList.remove('hidden');
    } else if (value === 'sftp') {
        document.getElementById('sftpFields')?.classList.remove('hidden');
    } else if (value === 'local_agent') {
        document.getElementById('localAgentFields')?.classList.remove('hidden');
    } else if (value === 'google_drive') {
        document.getElementById('gdriveFields')?.classList.remove('hidden');
    } else if (value === 'dropbox') {
        document.getElementById('dropboxFields')?.classList.remove('hidden');
    }
}

function onRetentionModeChange() {
    const modeEl = document.getElementById('retentionMode');
    const container = document.getElementById('retentionValueContainer');
    const help = document.getElementById('retentionHelp');
    if (!modeEl || !container) return;
    const mode = modeEl.value;
    if (mode === 'none') {
        container.classList.add('hidden');
        if (help) help.textContent = '';
    } else {
        container.classList.remove('hidden');
        if (help) {
            if (mode === 'keep_last_n') {
                help.textContent = 'Keep only the N most recent successful backup runs.';
            } else if (mode === 'keep_days') {
                help.textContent = 'Keep backup data for N days.';
            }
        }
    }
}

function showNoScheduleModal(callback) {
    const modal = document.getElementById('noScheduleModal');
    if (!modal) {
        callback();
        return;
    }
    window._noScheduleCallback = callback;
    modal.style.display = 'flex';
    if (modal.__x && modal.__x.$data) {
        modal.__x.$data.open = true;
    }
}

function hideNoScheduleModal() {
    const modal = document.getElementById('noScheduleModal');
    if (!modal) return;
    modal.style.display = 'none';
    if (modal.__x && modal.__x.$data) {
        modal.__x.$data.open = false;
    }
}

function confirmNoScheduleCreate() {
    hideNoScheduleModal();
    if (typeof window._noScheduleCallback === 'function') {
        window._noScheduleCallback();
    }
}

function doCreateJobSubmit(formEl) {
    const formData = new FormData(formEl);
    const sourceType = formData.get('source_type');
    
    let sourceConfig = {};
    let sourceDisplayName = '';
    let sourcePath = '';
    
    if (sourceType === 's3_compatible') {
        sourceConfig = {
            endpoint: formData.get('s3_endpoint'),
            access_key: formData.get('s3_access_key'),
            secret_key: formData.get('s3_secret_key'),
            bucket: formData.get('s3_bucket'),
            region: formData.get('s3_region') || 'ca-central-1'
        };
        sourceDisplayName = formData.get('source_display_name') || formData.get('s3_endpoint');
        sourcePath = formData.get('s3_path') || '';
    } else if (sourceType === 'aws') {
        sourceConfig = {
            access_key: formData.get('aws_access_key'),
            secret_key: formData.get('aws_secret_key'),
            bucket: formData.get('aws_bucket'),
            region: formData.get('aws_region')
        };
        sourceDisplayName = formData.get('aws_display_name') || 'AWS S3';
        sourcePath = formData.get('aws_path') || '';
    } else if (sourceType === 'sftp') {
        sourceConfig = {
            host: formData.get('sftp_host'),
            port: formData.get('sftp_port') || 22,
            user: formData.get('sftp_username'),
            pass: formData.get('sftp_password')
        };
        sourceDisplayName = formData.get('sftp_display_name') || formData.get('sftp_host');
        sourcePath = formData.get('sftp_path') || '';
    } else if (sourceType === 'local_agent') {
        sourceConfig = {
            include_glob: formData.get('local_include_glob'),
            exclude_glob: formData.get('local_exclude_glob'),
            bandwidth_limit_kbps: formData.get('local_bandwidth_limit_kbps')
        };
        sourceDisplayName = 'Local Agent';
        sourcePath = formData.get('local_source_path') || '';
    }
    
    formData.set('source_config', JSON.stringify(sourceConfig));
    formData.set('source_display_name', sourceDisplayName || 'Unnamed Source');
    formData.set('source_path', sourcePath);
    formData.set('engine', sourceType === 'local_agent' ? 'kopia' : 'sync');
    
    const msgEl = document.getElementById('jobCreationMessage');
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_create_job.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeCreateSlideover();
            // Reload jobs list
            if (window.Alpine) {
                const app = document.querySelector('[x-data="jobsApp()"]');
                if (app && app.__x && app.__x.$data) {
                    app.__x.$data.loadJobs();
                }
            }
            if (window.toast) toast.success('Job created successfully!');
        } else {
            if (msgEl) {
                msgEl.textContent = data.message || 'Failed to create job';
                msgEl.classList.remove('hidden');
            }
            if (window.toast) toast.error(data.message || 'Failed to create job');
        }
    })
    .catch(err => {
        if (msgEl) {
            msgEl.textContent = 'Error: ' + err.message;
            msgEl.classList.remove('hidden');
        }
        if (window.toast) toast.error('Error creating job');
    });
}

// Schedule type change handler
document.addEventListener('DOMContentLoaded', function() {
    const scheduleType = document.getElementById('scheduleType');
    const scheduleOptions = document.getElementById('scheduleOptions');
    const weeklyOption = document.getElementById('weeklyOption');
    
    if (scheduleType) {
        scheduleType.addEventListener('change', function() {
            if (this.value === 'manual') {
                scheduleOptions?.classList.add('hidden');
                weeklyOption?.classList.add('hidden');
            } else {
                scheduleOptions?.classList.remove('hidden');
                if (this.value === 'weekly') {
                    weeklyOption?.classList.remove('hidden');
                } else {
                    weeklyOption?.classList.add('hidden');
                }
            }
        });
    }
    
    // Source type change handler
    const sourceType = document.getElementById('sourceType');
    if (sourceType) {
        sourceType.addEventListener('change', function() {
            onSourceTypeChange(this.value);
        });
    }
    
    // Retention mode change handler
    const retentionMode = document.getElementById('retentionMode');
    if (retentionMode) {
        retentionMode.addEventListener('change', onRetentionModeChange);
    }
    
    // Form submission
    const createForm = document.getElementById('createJobForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const scheduleTypeEl = document.getElementById('scheduleType');
            const stype = scheduleTypeEl ? (scheduleTypeEl.value || '').toLowerCase() : 'manual';
            if (stype === 'manual') {
                return showNoScheduleModal(() => doCreateJobSubmit(this));
            }
            doCreateJobSubmit(this);
        });
    }
});

// --- Restore Wizard Functions ---

window.restoreState = { jobId: null, step: 1, totalSteps: 3, runs: [], selectedRunId: '', targetPath: '', mount: false };

function openRestoreModal(jobId) {
    window.restoreState.jobId = jobId;
    window.restoreState.step = 1;
    window.restoreState.selectedRunId = '';
    window.restoreState.targetPath = '';
    window.restoreState.mount = false;
    const modal = document.getElementById('restoreWizardModal');
    if (modal) modal.classList.remove('hidden');
    loadRestoreRuns(jobId);
    updateRestoreView();
}

function closeRestoreModal() {
    const modal = document.getElementById('restoreWizardModal');
    if (modal) modal.classList.add('hidden');
}

function loadRestoreRuns(jobId) {
    const sel = document.getElementById('restoreRunSelect');
    if (sel) {
        sel.innerHTML = '<option value="">Loading runs…</option>';
    }
    fetch('modules/addons/cloudstorage/api/cloudbackup_list_runs.php?job_id=' + encodeURIComponent(jobId))
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                if (sel) sel.innerHTML = '<option value="">Failed to load runs</option>';
                return;
            }
            window.restoreState.runs = data.runs || [];
            if (sel) {
                sel.innerHTML = '';
                if (!window.restoreState.runs.length) {
                    sel.innerHTML = '<option value="">No runs available</option>';
                } else {
                    window.restoreState.runs.forEach((run) => {
                        const opt = document.createElement('option');
                        opt.value = String(run.id);
                        const ts = run.started_at ? (' @ ' + run.started_at) : '';
                        opt.textContent = `Run #${run.id} (${run.status})${ts} ${run.log_ref ? ' – manifest ' + run.log_ref : ''}`;
                        sel.appendChild(opt);
                    });
                }
            }
        })
        .catch(() => {
            if (sel) sel.innerHTML = '<option value="">Failed to load runs</option>';
        });
}

function restoreNext() {
    const st = window.restoreState;
    if (st.step === 1) {
        const sel = document.getElementById('restoreRunSelect');
        st.selectedRunId = sel ? sel.value : '';
        if (!st.selectedRunId) {
            if (window.toast) toast.error('Select a run/snapshot to restore');
            else alert('Select a run/snapshot to restore');
            return;
        }
    } else if (st.step === 2) {
        const tp = document.getElementById('restoreTargetPath');
        st.targetPath = tp ? (tp.value || '') : '';
        st.mount = document.getElementById('restoreMount')?.checked || false;
        if (!st.targetPath) {
            if (window.toast) toast.error('Target path is required');
            else alert('Target path is required');
            return;
        }
    }
    if (st.step < st.totalSteps) {
        st.step += 1;
        if (st.step === st.totalSteps) {
            buildRestoreReview();
        }
        updateRestoreView();
    } else {
        submitRestore();
    }
}

function restorePrev() {
    const st = window.restoreState;
    if (st.step > 1) {
        st.step -= 1;
        updateRestoreView();
    }
}

function updateRestoreView() {
    const st = window.restoreState;
    document.querySelectorAll('#restoreWizardModal .restore-step').forEach((el) => {
        const s = parseInt(el.getAttribute('data-step'), 10);
        if (s === st.step) el.classList.remove('hidden'); else el.classList.add('hidden');
    });
    const label = document.getElementById('restoreStepLabel');
    const title = document.getElementById('restoreStepTitle');
    if (label) label.textContent = `Step ${st.step} of ${st.totalSteps}`;
    if (title) {
        const titles = {1:'Select Snapshot',2:'Target',3:'Review'};
        title.textContent = titles[st.step] || 'Restore';
    }
}

function buildRestoreReview() {
    const st = window.restoreState;
    const run = (st.runs || []).find(r => String(r.run_uuid || r.id) === String(st.selectedRunId));
    const review = {
        run_uuid: st.selectedRunId,
        manifest_id: run ? (run.log_ref || '') : '',
        target_path: st.targetPath,
        mount: st.mount,
    };
    const el = document.getElementById('restoreReview');
    if (el) {
        el.textContent = JSON.stringify(review, null, 2);
    }
}

function submitRestore() {
    const st = window.restoreState;
    const run = (st.runs || []).find(r => String(r.run_uuid || r.id) === String(st.selectedRunId));
    const manifest = run ? (run.log_ref || '') : '';
    if (!manifest) {
        if (window.toast) toast.error('Selected run has no manifest (log_ref). Cannot restore.');
        else alert('Selected run has no manifest (log_ref). Cannot restore.');
        return;
    }
    
    const payload = {
        backup_run_id: st.selectedRunId,
        target_path: st.targetPath,
        mount: st.mount ? 'true' : 'false',
    };
    
    const submitBtn = document.querySelector('#restoreWizardModal button[onclick*="restoreNext"]');
    const originalText = submitBtn ? submitBtn.textContent : 'Submit';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Starting restore...';
    }
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_start_restore.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            if (window.toast) toast.success('Restore started! Redirecting to progress view...');
            closeRestoreModal();
            
            const restoreRunParam = data.restore_run_uuid || data.restore_run_id;
            if (restoreRunParam) {
                setTimeout(() => {
                    window.location.href = 'index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_live&job_id=' + 
                        encodeURIComponent(data.job_id) + '&run_id=' + encodeURIComponent(restoreRunParam);
                }, 1000);
            }
        } else {
            if (window.toast) toast.error(data.message || 'Failed to start restore');
            else alert(data.message || 'Failed to start restore');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(err => {
        if (window.toast) toast.error('Error starting restore: ' + err);
        else alert('Error starting restore: ' + err);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

// ========================================
// Local Agent Job Wizard Functions
// ========================================

window.localWizardState = {
    step: 1,
    totalSteps: 5,
    data: {
        engine: 'kopia',
        dest_type: 's3',
        bucket_auto_create: true,
        tenant_id: '', // MSP: Tenant scope for the job
    },
    editMode: false,
    jobId: '',
    loading: false,
};

function resetLocalWizardFields() {
    window.localWizardState.data = {
        engine: 'kopia',
        dest_type: 's3',
        bucket_auto_create: true,
        source_paths: [],
        tenant_id: '',
    };
    const idsToClear = [
        'localWizardName','localWizardAgentId','localWizardBucketId','localWizardPrefix',
        'localWizardLocalPath','localWizardSource','localWizardSourcePaths','localWizardInclude','localWizardExclude',
        'localWizardTime','localWizardCron','localWizardRetention','localWizardPolicy',
        'localWizardDiskVolume','localWizardDiskTemp','localWizardTenantId'
    ];
    idsToClear.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const week = document.getElementById('localWizardWeekday');
    if (week) week.value = '1';
    const sched = document.getElementById('localWizardScheduleType');
    if (sched) sched.value = 'manual';
    const bw = document.getElementById('localWizardBandwidth');
    if (bw) bw.value = '0';
    const par = document.getElementById('localWizardParallelism');
    if (par) par.value = '8';
    const comp = document.getElementById('localWizardCompression');
    if (comp) comp.value = 'none';
    const dbg = document.getElementById('localWizardDebugLogs');
    if (dbg) dbg.checked = false;
    const diskFormat = document.getElementById('localWizardDiskFormat');
    if (diskFormat) diskFormat.value = 'vhdx';
    const agentBtn = document.querySelector('#localWizardAgentId')?.parentElement?.querySelector('button span');
    if (agentBtn) agentBtn.textContent = 'Select agent';
    const bucketBtn = document.getElementById('localWizardBucketId')?.parentElement?.querySelector('button .block');
    if (bucketBtn) bucketBtn.textContent = 'Select a bucket';
    localWizardSet('engine', 'kopia');
}

function openLocalJobWizard(opts = {}) {
    const modal = document.getElementById('localJobWizardModal');
    if (!modal) return;
    resetLocalWizardFields();
    window.localWizardState.editMode = !!opts.editMode;
    window.localWizardState.jobId = opts.jobId || '';
    window.localWizardState.loading = !!opts.loading;
    modal.classList.remove('hidden');
    window.localWizardState.step = 1;
    localWizardUpdateView();
    if (opts.job) {
        localWizardFillFromJob(opts.job, opts.source || {});
    }
}

function closeLocalJobWizard() {
    const modal = document.getElementById('localJobWizardModal');
    if (modal) modal.classList.add('hidden');
    window.localWizardState.editMode = false;
    window.localWizardState.jobId = '';
    window.localWizardState.loading = false;
    resetLocalWizardFields();
}

function openLocalJobWizardForEdit(jobId) {
    const modal = document.getElementById('localJobWizardModal');
    if (!modal) return;
    window.localWizardState.loading = true;
    openLocalJobWizard({ editMode: true, jobId, loading: true });
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_job.php?job_id=' + encodeURIComponent(jobId))
        .then((r) => r.json())
        .then((data) => {
            if (data.status !== 'success' || !data.job) {
                window.toast?.error?.(data.message || 'Failed to load job');
                closeLocalJobWizard();
                return;
            }
            const j = data.job;
            const s = data.source || {};
            if ((j.source_type || '').toLowerCase() !== 'local_agent') {
                closeLocalJobWizard();
                openCloudBackupWizard();
                return;
            }
            localWizardFillFromJob(j, s);
        })
        .catch((err) => {
            window.toast?.error?.('Failed to load job: ' + err);
            closeLocalJobWizard();
        })
        .finally(() => {
            window.localWizardState.loading = false;
            localWizardUpdateView();
        });
}

function localWizardSetAgentSelection(agentId, agentLabel) {
    const hid = document.getElementById('localWizardAgentId');
    if (hid) hid.value = agentId || '';
    const btnLabel = hid?.parentElement?.querySelector('button span');
    if (btnLabel && agentLabel) {
        btnLabel.textContent = agentLabel;
    }
    const root = hid?.closest('[x-data]');
    if (root && root.__x && root.__x.$data) {
        try {
            root.__x.$data.selectedId = agentId || '';
            root.__x.$data.selectedName = agentLabel || '';
            setTimeout(() => {
                if (root.__x && root.__x.$data) {
                    root.__x.$data.selectedId = agentId || '';
                    root.__x.$data.selectedName = agentLabel || '';
                }
            }, 150);
        } catch (e) {}
    }
}

function localWizardFillFromJob(j, s) {
    const source = s || {};
    const job = j || {};
    const engineVal = (job.engine || '').toLowerCase();
    if (engineVal === 'disk_image') {
        localWizardSet('engine', 'disk_image');
    } else {
        localWizardSet('engine', job.backup_mode === 'sync' ? 'sync' : 'kopia');
    }
    const nameEl = document.getElementById('localWizardName');
    if (nameEl) nameEl.value = job.name || '';

    const agentLabel = job.agent_hostname
        ? `${job.agent_hostname} (ID ${job.agent_id || ''})`
        : (job.agent_id ? `Agent #${job.agent_id}` : 'Select agent');
    localWizardSetAgentSelection(job.agent_id || '', agentLabel);

    const bucketHidden = document.getElementById('localWizardBucketId');
    if (bucketHidden) {
        bucketHidden.value = job.dest_bucket_id || '';
        const bucketBtnLabel = bucketHidden.parentElement?.querySelector('button .block');
        if (bucketBtnLabel) {
            const name = job.dest_bucket_name || (job.dest_bucket_id ? `Bucket #${job.dest_bucket_id}` : 'Select a bucket');
            bucketBtnLabel.textContent = name;
        }
    }
    const prefixEl = document.getElementById('localWizardPrefix');
    if (prefixEl) prefixEl.value = job.dest_prefix || '';
    const localPathEl = document.getElementById('localWizardLocalPath');
    if (localPathEl) localPathEl.value = job.dest_local_path || '';
    const srcEl = document.getElementById('localWizardSource');
    if (srcEl) srcEl.value = job.source_path || '';
    const pathsHidden = document.getElementById('localWizardSourcePaths');
    let parsedPaths = [];
    if (job.source_paths_json) {
        const parsed = safeParseJSON(job.source_paths_json);
        if (Array.isArray(parsed)) {
            parsedPaths = parsed;
        }
    }
    if (!parsedPaths.length && job.source_path) {
        parsedPaths = [job.source_path];
    }
    if (pathsHidden) {
        pathsHidden.value = JSON.stringify(parsedPaths);
    }
    if (window.localWizardState?.data) {
        window.localWizardState.data.source_paths = parsedPaths;
        window.localWizardState.data.source_path = parsedPaths[0] || job.source_path || '';
    }
    const diskVolEl = document.getElementById('localWizardDiskVolume');
    if (diskVolEl) diskVolEl.value = job.disk_source_volume || '';
    const diskFmtEl = document.getElementById('localWizardDiskFormat');
    if (diskFmtEl) diskFmtEl.value = (job.disk_image_format || 'vhdx');
    const diskTempEl = document.getElementById('localWizardDiskTemp');
    if (diskTempEl) diskTempEl.value = job.disk_temp_dir || '';
    const incEl = document.getElementById('localWizardInclude');
    if (incEl) incEl.value = source.include_glob || job.local_include_glob || '';
    const excEl = document.getElementById('localWizardExclude');
    if (excEl) excEl.value = source.exclude_glob || job.local_exclude_glob || '';
    const bwEl = document.getElementById('localWizardBandwidth');
    if (bwEl) bwEl.value = source.bandwidth_limit_kbps || job.local_bandwidth_limit_kbps || job.bandwidth_limit_kbps || '0';
    const policyObj = job.policy_json ? (safeParseJSON(job.policy_json) || {}) : {};
    const parEl = document.getElementById('localWizardParallelism');
    if (parEl) parEl.value = job.parallelism || policyObj.parallel_uploads || '8';
    const compEl = document.getElementById('localWizardCompression');
    if (compEl) compEl.value = policyObj.compression || 'none';
    const dbgEl = document.getElementById('localWizardDebugLogs');
    if (dbgEl) dbgEl.checked = !!policyObj.debug_logs;
    const schedType = document.getElementById('localWizardScheduleType');
    if (schedType) schedType.value = job.schedule_type || (job.schedule_json?.type) || 'manual';
    const schedTime = document.getElementById('localWizardTime');
    if (schedTime) schedTime.value = job.schedule_time || (job.schedule_json?.time) || '';
    const schedWeek = document.getElementById('localWizardWeekday');
    if (schedWeek) schedWeek.value = job.schedule_weekday || (job.schedule_json?.weekday) || '1';
    const schedCron = document.getElementById('localWizardCron');
    if (schedCron) schedCron.value = job.schedule_cron || (job.schedule_json?.cron) || '';
    const retTxt = document.getElementById('localWizardRetention');
    if (retTxt) {
        const rj = job.retention_json || '';
        retTxt.value = typeof rj === 'string' ? rj : JSON.stringify(rj || {}, null, 2);
    }
    const polTxt = document.getElementById('localWizardPolicy');
    if (polTxt) {
        const pj = job.policy_json || '';
        polTxt.value = typeof pj === 'string' ? pj : JSON.stringify(pj || {}, null, 2);
    }
    if (job.agent_id) {
        localWizardOnAgentSelected(job.agent_id);
    }
    localWizardBuildReview();
}

function localWizardSet(key, val) {
    window.localWizardState.data[key] = val;
    if (key === 'engine') {
        const buttons = document.querySelectorAll('[data-engine-btn]');
        buttons.forEach((btn) => {
            const e = btn.getAttribute('data-engine-btn');
            if (e === val) {
                btn.classList.add('selected', 'ring-2', 'ring-cyan-500', 'border-cyan-500/50', 'bg-slate-800');
            } else {
                btn.classList.remove('selected', 'ring-2', 'ring-cyan-500', 'border-cyan-500/50', 'bg-slate-800');
            }
        });
        window.dispatchEvent(new CustomEvent('engine-changed', { detail: { engine: val } }));
        localWizardUpdateView();
    }
}

const localWizardVolumeState = {
    volumes: [],
    updatedAt: '',
    loading: false,
    lastAgentId: '',
};

function localWizardOnAgentSelected(agentId) {
    if (!agentId) return;
    localWizardVolumeState.lastAgentId = agentId;
    window.dispatchEvent(new CustomEvent('local-agent-selected', { detail: { agentId } }));
    localWizardUpdateView();
}

function localWizardFormatVolumeLabel(v) {
    const parts = [];
    if (v.path) parts.push(v.path);
    if (v.label) parts.push(v.label);
    if (v.size_bytes) parts.push(localWizardFormatBytes(v.size_bytes));
    return parts.join(' — ');
}

function localWizardFormatBytes(n) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let val = Number(n);
    let idx = 0;
    while (val >= 1024 && idx < units.length - 1) {
        val /= 1024;
        idx += 1;
    }
    return `${val.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
}

// Alpine.js component backing the remote filesystem browser
function fileBrowser() {
    return {
        loading: false,
        error: null,
        currentPath: '',
        parentPath: '',
        entries: [],
        selectedPaths: [],
        networkPathsInfo: [],
        manualPath: '',
        agentId: '',
        networkUsername: '',
        networkPassword: '',
        networkDomain: '',
        selectedVolume: '',
        selectedVolumeInfo: null,

        get isDiskImageMode() {
            return window.localWizardState?.data?.engine === 'disk_image';
        },

        get localVolumes() {
            if (this.currentPath !== '') return [];
            return this.entries.filter(e => {
                if (e.icon !== 'drive') return false;
                if (e.is_network) return false;
                if (e.type === 'network') return false;
                if (e.path && e.path.startsWith('\\\\')) return false;
                if (e.unc_path && e.unc_path !== '') return false;
                return true;
            });
        },

        get hasNetworkPaths() {
            return this.selectedPaths.some(path => this.isNetworkPath(path));
        },

        isNetworkPath(path) {
            if (path && path.startsWith('\\\\')) return true;
            return this.networkPathsInfo.some(info => info.path === path && info.is_network);
        },

        selectVolume(entry) {
            this.selectedVolume = entry.path;
            this.selectedVolumeInfo = entry;
            this.syncDiskVolumeToWizard();
        },

        syncDiskVolumeToWizard() {
            const input = document.getElementById('localWizardDiskVolume');
            if (input) input.value = this.selectedVolume || '';
            if (window.localWizardState?.data) {
                window.localWizardState.data.disk_source_volume = this.selectedVolume || '';
            }
        },

        get pathSegments() {
            if (!this.currentPath) return [];
            const sep = this.currentPath.includes('\\') ? '\\' : '/';
            const parts = this.currentPath.split(sep).filter(Boolean);
            let acc = '';
            return parts.map((p, idx) => {
                acc += (idx === 0 && sep === '\\') ? (p + sep) : (sep + p);
                return { name: p, path: acc };
            });
        },

        init() {
            this.agentId = document.getElementById('localWizardAgentId')?.value || '';
            const preset = document.getElementById('localWizardSourcePaths')?.value || '';
            if (preset) {
                try {
                    const parsed = JSON.parse(preset);
                    if (Array.isArray(parsed)) {
                        this.selectedPaths = parsed;
                    }
                } catch (e) {}
            }
            const diskVolume = document.getElementById('localWizardDiskVolume')?.value || '';
            if (diskVolume) {
                this.selectedVolume = diskVolume;
            }
            if (this.agentId) {
                this.loadDirectory('');
            } else {
                this.error = 'Select an agent to browse.';
            }
            window.addEventListener('local-agent-selected', (e) => {
                this.agentId = e.detail?.agentId || '';
                this.selectedPaths = [];
                this.selectedVolume = '';
                this.selectedVolumeInfo = null;
                this.syncToWizard();
                this.syncDiskVolumeToWizard();
                if (this.agentId) {
                    this.loadDirectory('');
                } else {
                    this.error = 'Select an agent to browse.';
                }
            });
            window.addEventListener('refresh-browser', () => {
                const path = this.isDiskImageMode ? '' : (this.currentPath || '');
                this.loadDirectory(path);
            });
            window.addEventListener('engine-changed', () => {
                if (this.isDiskImageMode) {
                    this.selectedPaths = [];
                    this.syncToWizard();
                } else {
                    this.selectedVolume = '';
                    this.selectedVolumeInfo = null;
                    this.syncDiskVolumeToWizard();
                }
                this.loadDirectory('');
            });
        },

        async loadDirectory(path) {
            if (!this.agentId) {
                this.error = 'Select an agent to browse.';
                return;
            }
            this.currentPath = path || '';
            this.parentPath = path ? '' : this.parentPath;
            this.entries = [];
            this.loading = true;
            this.error = null;
            try {
                const resp = await fetch(`modules/addons/cloudstorage/api/agent_browse_filesystem.php?agent_id=${this.agentId}&path=${encodeURIComponent(path || '')}`);
                const text = await resp.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    this.error = `Browse failed (non-JSON response): ${text.slice(0, 120)}...`;
                    return;
                }
                if (data.status === 'success') {
                    const res = data.data || {};
                    this.currentPath = res.path || '';
                    this.parentPath = res.parent || '';
                    this.entries = Array.isArray(res.entries) ? res.entries : [];
                } else {
                    this.error = data.message || 'Failed to load directory';
                }
            } catch (e) {
                this.error = e.message || 'Network error';
            } finally {
                this.loading = false;
                this.syncToWizard();
            }
        },

        navigateTo(path) {
            this.loadDirectory(path || '');
        },

        retry() {
            this.loadDirectory(this.currentPath || '');
        },

        isSelected(path) {
            return this.selectedPaths.includes(path);
        },

        toggleSelection(entry) {
            const path = entry.path;
            if (!path) return;
            if (this.isSelected(path)) {
                this.selectedPaths = this.selectedPaths.filter((p) => p !== path);
                this.networkPathsInfo = this.networkPathsInfo.filter((info) => info.path !== path);
            } else {
                this.selectedPaths = [...this.selectedPaths, path];
                if (entry.is_network || (entry.unc_path && entry.unc_path !== '')) {
                    this.networkPathsInfo.push({
                        path: path,
                        is_network: true,
                        unc_path: entry.unc_path || path
                    });
                }
            }
            this.syncToWizard();
        },

        removeSelection(path) {
            this.selectedPaths = this.selectedPaths.filter((p) => p !== path);
            this.syncToWizard();
        },

        addManualPath() {
            const p = (this.manualPath || '').trim();
            if (!p) return;
            if (!this.selectedPaths.includes(p)) {
                this.selectedPaths.push(p);
            }
            this.manualPath = '';
            this.syncToWizard();
        },

        syncToWizard() {
            const srcInput = document.getElementById('localWizardSource');
            const pathsInput = document.getElementById('localWizardSourcePaths');
            const first = this.selectedPaths[0] || '';
            if (srcInput) srcInput.value = first;
            if (pathsInput) pathsInput.value = JSON.stringify(this.selectedPaths);
            if (window.localWizardState?.data) {
                window.localWizardState.data.source_path = first;
                window.localWizardState.data.source_paths = [...this.selectedPaths];
            }
            this.syncCredentials();
        },

        syncCredentials() {
            if (window.localWizardState?.data && this.hasNetworkPaths) {
                window.localWizardState.data.network_username = this.networkUsername;
                window.localWizardState.data.network_password = this.networkPassword;
                window.localWizardState.data.network_domain = this.networkDomain;
            } else if (window.localWizardState?.data) {
                window.localWizardState.data.network_username = '';
                window.localWizardState.data.network_password = '';
                window.localWizardState.data.network_domain = '';
            }
        },

        formatBytes(n) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let val = Number(n || 0);
            let idx = 0;
            while (val >= 1024 && idx < units.length - 1) {
                val /= 1024;
                idx += 1;
            }
            return `${val.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
        },
    };
}

// Alpine.js component for Hyper-V VM browser
function hypervBrowser() {
    return {
        loading: false,
        error: null,
        vms: [],
        selectedVMs: [],
        agentId: '',

        init() {
            this.agentId = document.getElementById('localWizardAgentId')?.value || '';
            // Load existing selections if any
            const preset = document.getElementById('localWizardHypervVMs')?.value || '';
            if (preset) {
                try {
                    const parsed = JSON.parse(preset);
                    if (Array.isArray(parsed)) {
                        this.selectedVMs = parsed;
                    }
                } catch (e) {}
            }
            if (this.agentId) {
                this.loadVMs();
            } else {
                this.error = 'Select an agent to discover VMs.';
            }
            // Listen for agent selection
            window.addEventListener('local-agent-selected', (e) => {
                this.agentId = e.detail?.agentId || '';
                this.selectedVMs = [];
                this.vms = [];
                this.syncToWizard();
                if (this.agentId) {
                    this.loadVMs();
                } else {
                    this.error = 'Select an agent to discover VMs.';
                }
            });
            // Listen for refresh
            window.addEventListener('refresh-hyperv-vms', () => {
                if (this.agentId) {
                    this.loadVMs();
                }
            });
        },

        async loadVMs() {
            if (!this.agentId) {
                this.error = 'Select an agent to discover VMs.';
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                const resp = await fetch(`modules/addons/cloudstorage/api/agent_list_hyperv_vms.php?agent_id=${this.agentId}`);
                const text = await resp.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    this.error = `Failed to parse response: ${text.slice(0, 120)}...`;
                    return;
                }
                if (data.status === 'success') {
                    this.vms = Array.isArray(data.vms) ? data.vms : [];
                    // Re-validate selected VMs
                    const validIds = this.vms.map(v => v.id);
                    this.selectedVMs = this.selectedVMs.filter(id => validIds.includes(id));
                    this.syncToWizard();
                } else {
                    this.error = data.message || 'Failed to load VMs';
                }
            } catch (e) {
                this.error = e.message || 'Network error';
            } finally {
                this.loading = false;
            }
        },

        isSelected(vmId) {
            return this.selectedVMs.includes(vmId);
        },

        toggleVM(vm) {
            if (this.isSelected(vm.id)) {
                this.selectedVMs = this.selectedVMs.filter(id => id !== vm.id);
            } else {
                this.selectedVMs = [...this.selectedVMs, vm.id];
            }
            this.syncToWizard();
        },

        removeVM(vmId) {
            this.selectedVMs = this.selectedVMs.filter(id => id !== vmId);
            this.syncToWizard();
        },

        selectAllVMs() {
            this.selectedVMs = this.vms.map(v => v.id);
            this.syncToWizard();
        },

        clearSelection() {
            this.selectedVMs = [];
            this.syncToWizard();
        },

        getVMName(vmId) {
            const vm = this.vms.find(v => v.id === vmId);
            return vm ? vm.name : vmId;
        },

        formatMemory(mb) {
            if (!mb) return '';
            if (mb >= 1024) {
                return `${(mb / 1024).toFixed(1)} GB RAM`;
            }
            return `${mb} MB RAM`;
        },

        syncToWizard() {
            const input = document.getElementById('localWizardHypervVMs');
            if (input) {
                input.value = JSON.stringify(this.selectedVMs);
            }
            // Also store full VM data for review
            if (window.localWizardState?.data) {
                window.localWizardState.data.hyperv_vm_ids = [...this.selectedVMs];
                window.localWizardState.data.hyperv_vms = this.selectedVMs.map(id => {
                    const vm = this.vms.find(v => v.id === id);
                    return vm ? { id: vm.id, name: vm.name } : { id };
                });
            }
        },
    };
}

function localWizardSetDiskVolume(val) {
    const input = document.getElementById('localWizardDiskVolume');
    if (input) {
        input.value = val;
    }
    if (window.localWizardState?.data) {
        window.localWizardState.data.disk_source_volume = val;
    }
    localWizardBuildReview();
}

function localWizardNext() {
    const state = window.localWizardState;
    if (state.loading) return;

    if (state.step === 1 && !localWizardIsStep1Valid()) {
        window.toast?.error?.('Please fill in Job Name, select an Agent, Engine, and Bucket before proceeding');
        return;
    }

    if (state.step < state.totalSteps) {
        state.step += 1;
        if (state.step === state.totalSteps) {
            localWizardBuildReview();
        }
        localWizardUpdateView();
        return;
    }
    localWizardSubmit();
}

function localWizardPrev() {
    const state = window.localWizardState;
    if (state.step > 1) {
        state.step -= 1;
        localWizardUpdateView();
    }
}

function localWizardUpdateView() {
    const state = window.localWizardState;
    const steps = document.querySelectorAll('#localJobWizardModal .wizard-step');
    steps.forEach((el) => {
        const target = parseInt(el.getAttribute('data-step'), 10);
        if (target === state.step) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });

    const crumbs = document.querySelectorAll('#localWizardBreadcrumb .wizard-crumb');
    const step1Valid = localWizardIsStep1Valid();
    crumbs.forEach((crumb) => {
        const stepNum = parseInt(crumb.getAttribute('data-wizard-step'), 10);
        const numBadge = crumb.querySelector('span:first-child');
        const isActive = stepNum === state.step;
        const isComplete = stepNum < state.step;
        const isLocked = stepNum > 1 && !step1Valid;

        crumb.classList.remove('bg-slate-800/50', 'text-slate-300', 'text-slate-500', 'cursor-not-allowed', 'hover:bg-slate-800');
        numBadge.classList.remove('bg-cyan-500', 'bg-emerald-500', 'bg-slate-700', 'text-white', 'text-slate-400');

        if (isActive) {
            crumb.classList.add('bg-slate-800/50', 'text-slate-300');
            numBadge.classList.add('bg-cyan-500', 'text-white');
        } else if (isComplete) {
            crumb.classList.add('text-slate-300', 'hover:bg-slate-800');
            numBadge.classList.add('bg-emerald-500', 'text-white');
        } else if (isLocked) {
            crumb.classList.add('text-slate-500', 'cursor-not-allowed');
            numBadge.classList.add('bg-slate-700', 'text-slate-400');
        } else {
            crumb.classList.add('text-slate-400', 'hover:bg-slate-800');
            numBadge.classList.add('bg-slate-700', 'text-slate-400');
        }

        crumb.disabled = isLocked;
        crumb.style.pointerEvents = isLocked ? 'none' : 'auto';
    });

    const nextBtn = document.getElementById('localWizardNextBtn');
    if (nextBtn) {
        if (state.loading) {
            nextBtn.textContent = 'Loading…';
            nextBtn.disabled = true;
            nextBtn.classList.add('opacity-60', 'cursor-not-allowed');
        } else {
            const canProceed = state.step !== 1 || step1Valid;
            nextBtn.disabled = !canProceed;
            if (canProceed) {
                nextBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            } else {
                nextBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }
            const finalLabel = state.editMode ? 'Save changes' : 'Create job';
            nextBtn.textContent = (state.step === state.totalSteps) ? finalLabel : 'Next';
        }
    }
}

function localWizardIsStep1Valid() {
    const name = document.getElementById('localWizardName')?.value?.trim() || '';
    const agentId = document.getElementById('localWizardAgentId')?.value || '';
    const engine = window.localWizardState?.data?.engine || '';
    const bucketId = document.getElementById('localWizardBucketId')?.value || '';
    return name !== '' && agentId !== '' && engine !== '' && bucketId !== '';
}

function localWizardGoToStep(stepNum) {
    const state = window.localWizardState;
    if (state.loading) return;

    if (stepNum < state.step) {
        state.step = stepNum;
        localWizardUpdateView();
        return;
    }

    if (stepNum > 1 && !localWizardIsStep1Valid()) {
        window.toast?.error?.('Please complete all required fields in Setup before proceeding');
        return;
    }

    if (stepNum <= state.step + 1) {
        state.step = stepNum;
        if (state.step === state.totalSteps) {
            localWizardBuildReview();
        }
        localWizardUpdateView();
    }
}

function localWizardBuildReview() {
    const s = window.localWizardState.data;
    s.agent_id = document.getElementById('localWizardAgentId')?.value || '';
    s.name = document.getElementById('localWizardName')?.value || '';
    s.tenant_id = document.getElementById('localWizardTenantId')?.value || '';
    s.dest_prefix = document.getElementById('localWizardPrefix')?.value || '';
    s.dest_local_path = document.getElementById('localWizardLocalPath')?.value || '';
    s.dest_bucket_id = document.getElementById('localWizardBucketId')?.value || '';
    s.source_path = document.getElementById('localWizardSource')?.value || '';
    const srcPathsRaw = document.getElementById('localWizardSourcePaths')?.value || '[]';
    const srcPathsParsed = safeParseJSON(srcPathsRaw);
    s.source_paths = Array.isArray(srcPathsParsed) ? srcPathsParsed : [];
    s.disk_source_volume = document.getElementById('localWizardDiskVolume')?.value || '';
    s.disk_image_format = document.getElementById('localWizardDiskFormat')?.value || 'vhdx';
    s.disk_temp_dir = document.getElementById('localWizardDiskTemp')?.value || '';
    if ((s.engine || '') === 'disk_image' && !s.source_path) {
        s.source_path = s.disk_source_volume;
    }
    // Hyper-V specific fields
    const hypervVMsRaw = document.getElementById('localWizardHypervVMs')?.value || '[]';
    const hypervVMsParsed = safeParseJSON(hypervVMsRaw);
    s.hyperv_vm_ids = Array.isArray(hypervVMsParsed) ? hypervVMsParsed : [];
    s.hyperv_enable_rct = !!document.getElementById('localWizardHypervEnableRCT')?.checked;
    s.hyperv_consistency_level = document.getElementById('localWizardHypervConsistency')?.value || 'application';
    s.hyperv_quiesce_timeout = parseInt(document.getElementById('localWizardHypervQuiesceTimeout')?.value || '300', 10);
    if ((s.engine || '') === 'hyperv') {
        // Set source path to indicate Hyper-V backup
        s.source_path = 'Hyper-V VMs';
        s.source_paths = s.hyperv_vm_ids;
    }
    s.include = document.getElementById('localWizardInclude')?.value || '';
    s.exclude = document.getElementById('localWizardExclude')?.value || '';
    s.schedule_type = document.getElementById('localWizardScheduleType')?.value || 'manual';
    s.schedule_time = document.getElementById('localWizardTime')?.value || '';
    s.schedule_weekday = document.getElementById('localWizardWeekday')?.value || '';
    s.schedule_cron = document.getElementById('localWizardCron')?.value || '';
    s.schedule_json = {
        type: s.schedule_type,
        time: s.schedule_time,
        weekday: s.schedule_weekday,
        cron: s.schedule_cron,
    };
    const retentionTxt = document.getElementById('localWizardRetention')?.value || '';
    const policyTxt = document.getElementById('localWizardPolicy')?.value || '';
    const parsedPol = policyTxt ? safeParseJSON(policyTxt) : null;
    let policyObj = (parsedPol && typeof parsedPol === 'object') ? parsedPol : {};
    const bwVal = document.getElementById('localWizardBandwidth')?.value || '';
    const parVal = document.getElementById('localWizardParallelism')?.value || '';
    const compVal = document.getElementById('localWizardCompression')?.value || 'none';
    const dbgVal = !!document.getElementById('localWizardDebugLogs')?.checked;
    s.retention_json = retentionTxt ? safeParseJSON(retentionTxt) || retentionTxt : '';
    s.bandwidth_limit_kbps = bwVal;
    s.parallelism = parVal;
    if (compVal) {
        policyObj.compression = compVal;
    }
    if (parVal) {
        const pi = parseInt(parVal, 10);
        if (!isNaN(pi) && pi > 0) {
            policyObj.parallel_uploads = pi;
        }
    }
    if (dbgVal) {
        policyObj.debug_logs = true;
    }
    s.policy_json = policyObj;

    const review = document.getElementById('localWizardReview');
    if (review) {
        const displayData = { ...s };
        if (displayData.engine === 'kopia') {
            displayData.engine = 'eazyBackup (Archive)';
        } else if (displayData.engine === 'sync') {
            displayData.engine = 'eazyBackup (Sync)';
        } else if (displayData.engine === 'disk_image') {
            displayData.engine = 'eazyBackup (Disk Image)';
        } else if (displayData.engine === 'hyperv') {
            displayData.engine = 'Hyper-V VM Backup';
            // Show VM names instead of IDs
            if (displayData.hyperv_vms && displayData.hyperv_vms.length) {
                displayData.selected_vms = displayData.hyperv_vms.map(v => v.name || v.id);
            }
        }
        review.textContent = JSON.stringify(displayData, null, 2);
    }
}

function safeParseJSON(txt) {
    try {
        return JSON.parse(txt);
    } catch (e) {
        return null;
    }
}

function localWizardSubmit() {
    const s = window.localWizardState.data;
    const isEdit = !!window.localWizardState.editMode;
    if (!s.name) {
        window.toast?.error?.('Job name is required') || alert('Job name is required');
        return;
    }
    if (!s.agent_id) {
        window.toast?.error?.('Agent ID is required') || alert('Agent ID is required');
        return;
    }
    if (!s.dest_bucket_id) {
        window.toast?.error?.('Bucket ID is required') || alert('Bucket ID is required');
        return;
    }
    if ((s.engine || '') === 'disk_image' && !s.disk_source_volume) {
        window.toast?.error?.('Disk volume is required for disk image backups') || alert('Disk volume is required');
        return;
    }
    if ((s.engine || '') === 'hyperv' && (!s.hyperv_vm_ids || s.hyperv_vm_ids.length === 0)) {
        window.toast?.error?.('Please select at least one VM to backup') || alert('Please select at least one VM');
        return;
    }
    
    // Determine source display name based on engine
    let sourceDisplayName = 'Local Agent';
    let sourcePath = s.source_path || '';
    if (s.engine === 'disk_image') {
        sourceDisplayName = 'Disk Image';
        sourcePath = s.disk_source_volume || s.source_path || '';
    } else if (s.engine === 'hyperv') {
        const vmNames = (s.hyperv_vms || []).map(v => v.name).filter(Boolean);
        sourceDisplayName = 'Hyper-V: ' + (vmNames.length > 2 ? vmNames.slice(0, 2).join(', ') + '...' : vmNames.join(', '));
        sourcePath = 'Hyper-V VMs (' + s.hyperv_vm_ids.length + ')';
    }
    
    const payload = {
        name: s.name,
        source_type: 'local_agent',
        source_display_name: sourceDisplayName,
        source_path: sourcePath,
        source_paths: Array.isArray(s.source_paths) ? s.source_paths : (s.source_path ? [s.source_path] : []),
        dest_bucket_id: s.dest_bucket_id,
        dest_prefix: s.dest_prefix || '',
        backup_mode: s.engine === 'sync' ? 'sync' : 'archive',
        engine: s.engine || 'kopia',
        agent_id: s.agent_id,
        dest_type: 's3',
        tenant_id: s.tenant_id || '',
        schedule_json: s.schedule_json && typeof s.schedule_json === 'object' ? JSON.stringify(s.schedule_json) : '',
        retention_json: (s.retention_json && typeof s.retention_json === 'object') ? JSON.stringify(s.retention_json) : (typeof s.retention_json === 'string' ? s.retention_json : ''),
        policy_json: (s.policy_json && typeof s.policy_json === 'object') ? JSON.stringify(s.policy_json) : (typeof s.policy_json === 'string' ? s.policy_json : ''),
        bandwidth_limit_kbps: s.bandwidth_limit_kbps || '',
        parallelism: s.parallelism || '',
        encryption_mode: s.encryption_mode || 'repokey',
        compression: s.compression || '',
        retention_mode: 'none',
        retention_value: '',
        schedule_type: s.schedule_type || 'manual',
        schedule_time: s.schedule_time || '',
        schedule_weekday: s.schedule_weekday || '',
        schedule_cron: s.schedule_cron || '',
        local_include_glob: s.include || '',
        local_exclude_glob: s.exclude || '',
        disk_source_volume: s.disk_source_volume || '',
        disk_image_format: s.disk_image_format || '',
        disk_temp_dir: s.disk_temp_dir || '',
        network_username: s.network_username || '',
        network_password: s.network_password || '',
        network_domain: s.network_domain || '',
    };
    
    // Add Hyper-V specific fields
    if (s.engine === 'hyperv') {
        payload.hyperv_enabled = '1';
        payload.hyperv_vm_ids = JSON.stringify(s.hyperv_vm_ids || []);
        payload.hyperv_config = JSON.stringify({
            vms: s.hyperv_vm_ids || [],
            enable_rct: s.hyperv_enable_rct !== false,
            consistency_level: s.hyperv_consistency_level || 'application',
            quiesce_timeout_seconds: s.hyperv_quiesce_timeout || 300,
            backup_all_vms: false,
            exclude_vms: [],
        });
    }
    if (isEdit) {
        payload.job_id = window.localWizardState.jobId;
    }

    const opts = {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload),
    };
    const endpoint = isEdit
        ? 'modules/addons/cloudstorage/api/cloudbackup_update_job.php'
        : 'modules/addons/cloudstorage/api/cloudbackup_create_job.php';
    if (isEdit && !payload.job_id) {
        window.toast?.error?.('Missing job ID for update') || alert('Missing job ID for update');
        return;
    }
    fetch(endpoint, opts)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                window.toast?.success?.(isEdit ? 'Local agent job updated' : 'Local agent job created') || alert(isEdit ? 'Job updated!' : 'Job created!');
                closeLocalJobWizard();
                // Reload jobs list
                if (window.Alpine) {
                    const app = document.querySelector('[x-data="jobsApp()"]');
                    if (app && app.__x && app.__x.$data) {
                        app.__x.$data.loadJobs();
                    }
                }
            } else {
                window.toast?.error?.(data.message || (isEdit ? 'Failed to update job' : 'Failed to create job')) || alert(data.message || 'Failed');
            }
        })
        .catch(err => {
            window.toast?.error?.('Error ' + (isEdit ? 'updating' : 'creating') + ' job: ' + err) || alert('Error: ' + err);
        });
}

function openInlineBucketCreate() {
    const toggle = document.querySelector('#inlineCreateBucketMsg');
    if (toggle) {
        toggle.classList.remove('hidden');
    }
    const btn = document.querySelector('[onclick=\"createBucketInline().finally(() => creating=false)\"]');
    if (btn) {
        btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Add event listeners on DOM load
document.addEventListener('DOMContentLoaded', () => {
    const nameInput = document.getElementById('localWizardName');
    if (nameInput) {
        nameInput.addEventListener('input', () => {
            localWizardUpdateView();
        });
    }

    const bucketInput = document.getElementById('localWizardBucketId');
    if (bucketInput) {
        const observer = new MutationObserver(() => {
            localWizardUpdateView();
        });
        observer.observe(bucketInput, { attributes: true, attributeFilter: ['value'] });
        bucketInput.addEventListener('change', () => localWizardUpdateView());
    }
});
</script>
{/literal}

