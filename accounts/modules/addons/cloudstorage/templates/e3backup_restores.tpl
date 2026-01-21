<div class="min-h-screen bg-slate-950 text-gray-200" x-data="restoresApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="restores"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Restores</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Restore Points</h1>
                <p class="text-xs text-slate-400 mt-1">Restore from snapshots across all agents, even after jobs are deleted.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            {if $isMspClient}
            <div class="flex items-center gap-2">
                <label class="text-sm text-slate-400">Tenant:</label>
                <select x-model="tenantFilter" @change="loadRestorePoints()"
                        class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <option value="">All Tenants</option>
                    <option value="direct">Direct (No Tenant)</option>
                    {foreach from=$tenants item=tenant}
                    <option value="{$tenant->id}">{$tenant->name|escape}</option>
                    {/foreach}
                </select>
            </div>
            {/if}
            <div class="flex items-center gap-2">
                <label class="text-sm text-slate-400">Agent:</label>
                <select x-model="agentFilter" @change="loadRestorePoints()"
                        class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    <option value="">All Agents</option>
                    {foreach from=$agents item=agent}
                    <option value="{$agent->id}">{$agent->hostname|default:"Agent #{$agent->id}"|escape}</option>
                    {/foreach}
                </select>
            </div>
            <div class="flex items-center gap-2 sm:ml-auto">
                <input type="text" placeholder="Search restore points" x-model="searchQuery"
                       class="w-full sm:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            </div>
        </div>

        <!-- Restore Points Table -->
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Snapshot</th>
                        {if $isMspClient}<th class="px-4 py-3 text-left font-medium">Tenant</th>{/if}
                        <th class="px-4 py-3 text-left font-medium">Agent</th>
                        <th class="px-4 py-3 text-left font-medium">Engine</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Source</th>
                        <th class="px-4 py-3 text-left font-medium">Destination</th>
                        <th class="px-4 py-3 text-left font-medium">Completed</th>
                        <th class="px-4 py-3 text-left font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <template x-if="loading">
                        <tr>
                            <td :colspan="{if $isMspClient}9{else}8{/if}" class="px-4 py-8 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && restorePoints.length === 0">
                        <tr>
                            <td :colspan="{if $isMspClient}9{else}8{/if}" class="px-4 py-8 text-center text-slate-400">
                                No restore points found.
                            </td>
                        </tr>
                    </template>
                    <template x-for="point in restorePoints" :key="point.id">
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <div class="text-slate-100 font-medium" x-text="point.job_name || ('Job #' + (point.job_id || '—'))"></div>
                                <div class="text-xs text-slate-500" x-text="point.manifest_id || 'No manifest'"></div>
                                <div class="text-xs text-slate-400" x-show="point.hyperv_vm_name">VM: <span x-text="point.hyperv_vm_name"></span></div>
                            </td>
                            {if $isMspClient}
                            <td class="px-4 py-3 text-slate-300" x-text="point.tenant_name || 'Direct'"></td>
                            {/if}
                            <td class="px-4 py-3 text-slate-300" x-text="point.agent_hostname || ('Agent #' + (point.agent_id || '—'))"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="{ 'bg-sky-500/15 text-sky-200': point.engine === 'kopia', 'bg-purple-500/15 text-purple-200': point.engine === 'disk_image', 'bg-amber-500/15 text-amber-200': point.engine === 'hyperv', 'bg-slate-700 text-slate-300': !point.engine }"
                                      x-text="point.engine || 'unknown'"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="point.status === 'success' ? 'bg-emerald-500/15 text-emerald-200' : (point.status === 'warning' ? 'bg-amber-500/15 text-amber-200' : 'bg-slate-700 text-slate-300')"
                                      x-text="point.status || 'unknown'"></span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                <div class="text-xs" x-text="point.source_display_name || point.source_type || '—'"></div>
                                <div class="text-xs text-slate-500" x-text="point.source_path || ''"></div>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                <div class="text-xs" x-text="point.dest_type || 's3'"></div>
                                <div class="text-xs text-slate-500" x-text="point.dest_bucket_name || point.dest_prefix || point.dest_local_path || ''"></div>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="point.finished_at || point.created_at || '—'"></td>
                            <td class="px-4 py-3">
                                <template x-if="point.hyperv_backup_point_id">
                                    <a :href="'index.php?m=cloudstorage&page=e3backup&view=hyperv_restore&vm_id=' + point.hyperv_vm_id"
                                       class="text-xs px-2 py-1 rounded bg-sky-600/20 border border-sky-500/40 text-sky-300 hover:bg-sky-600/30 hover:border-sky-400 transition">
                                        Hyper-V Restore
                                    </a>
                                </template>
                                <template x-if="!point.hyperv_backup_point_id">
                                    <button @click="openRestoreModal(point)"
                                            class="text-xs px-2 py-1 rounded bg-sky-600/20 border border-sky-500/40 text-sky-300 hover:bg-sky-600/30 hover:border-sky-400 transition">
                                        Restore
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <!-- Restore Wizard Modal -->
    <div id="restorePointModal" class="fixed inset-0 z-[2100] hidden">
        <div class="absolute inset-0 bg-black/75" onclick="closeRestorePointModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-xl rounded-2xl bg-slate-950 border border-slate-800 shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Restore Snapshot</h3>
                        <p class="text-xs text-slate-400 mt-1">Restore from a saved restore point.</p>
                    </div>
                    <button class="text-slate-400 hover:text-white" onclick="closeRestorePointModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">
                        <span class="px-2 py-1 rounded-full border border-slate-700 bg-slate-900" id="restorePointStepLabel">Step 1 of 3</span>
                        <span class="text-slate-300" id="restorePointStepTitle">Confirm Snapshot</span>
                    </div>

                    <div class="space-y-6">
                        <!-- Step 1 -->
                        <div class="restore-point-step" data-step="1">
                            <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3">
                                <div class="text-sm text-slate-200 font-semibold" id="restorePointJobName">Selected restore point</div>
                                <div class="text-xs text-slate-400 mt-1" id="restorePointManifest"></div>
                                <div class="text-xs text-slate-400 mt-1" id="restorePointAgent"></div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="restore-point-step hidden" data-step="2">
                            <label class="block text-sm font-medium text-slate-200 mb-2">Restore Target</label>
                            <div class="space-y-3">
                                <input id="restorePointTargetPath" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100" placeholder="Destination path on agent (e.g., C:\Restores\snapshot)">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                    <input id="restorePointMount" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                    <span>Request mount instead of copy</span>
                                </label>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="restore-point-step hidden" data-step="3">
                            <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3 text-slate-100">
                                <p class="text-sm font-semibold mb-2">Review</p>
                                <div id="restorePointReview" class="text-xs whitespace-pre-wrap leading-5 bg-slate-950 border border-slate-800 rounded-lg p-3 overflow-auto max-h-64"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mt-6">
                        <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="restorePointPrev()">Back</button>
                        <div class="flex gap-2">
                            <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="closeRestorePointModal()">Cancel</button>
                            <button type="button" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-500" onclick="restorePointNext()">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
function restoresApp() {
    return {
        restorePoints: [],
        loading: true,
        tenantFilter: '',
        agentFilter: '',
        searchQuery: '',
        searchTimer: null,

        init() {
            try {
                const params = new URLSearchParams(window.location.search);
                const tenant = params.get('tenant_id');
                const agent = params.get('agent_id');
                if (tenant !== null) this.tenantFilter = tenant;
                if (agent !== null) this.agentFilter = agent;
            } catch (e) {}
            this.$watch('searchQuery', () => {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.loadRestorePoints(), 300);
            });
            this.loadRestorePoints();
        },

        async loadRestorePoints() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.tenantFilter) params.set('tenant_id', this.tenantFilter);
                if (this.agentFilter) params.set('agent_id', this.agentFilter);
                if (this.searchQuery) params.set('search', this.searchQuery);
                let url = 'modules/addons/cloudstorage/api/e3backup_restore_points_list.php';
                const qs = params.toString();
                if (qs) url += '?' + qs;

                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    this.restorePoints = data.restore_points || [];
                } else {
                    console.error(data.message);
                }
            } catch (e) {
                console.error('Failed to load restore points:', e);
            }
            this.loading = false;
        },

        openRestoreModal(point) {
            window.openRestorePointModal(point);
        }
    };
}

window.restorePointState = { point: null, step: 1, totalSteps: 3, targetPath: '', mount: false };

function openRestorePointModal(point) {
    window.restorePointState.point = point;
    window.restorePointState.step = 1;
    window.restorePointState.targetPath = '';
    window.restorePointState.mount = false;
    const modal = document.getElementById('restorePointModal');
    if (modal) modal.classList.remove('hidden');
    updateRestorePointView();
}

function closeRestorePointModal() {
    const modal = document.getElementById('restorePointModal');
    if (modal) modal.classList.add('hidden');
}

function restorePointNext() {
    const st = window.restorePointState;
    if (st.step === 2) {
        const tp = document.getElementById('restorePointTargetPath');
        st.targetPath = tp ? (tp.value || '') : '';
        st.mount = document.getElementById('restorePointMount')?.checked || false;
        if (!st.targetPath) {
            if (window.toast) toast.error('Target path is required');
            else alert('Target path is required');
            return;
        }
    }
    if (st.step < st.totalSteps) {
        st.step += 1;
        if (st.step === st.totalSteps) {
            buildRestorePointReview();
        }
        updateRestorePointView();
    } else {
        submitRestorePoint();
    }
}

function restorePointPrev() {
    const st = window.restorePointState;
    if (st.step > 1) {
        st.step -= 1;
        updateRestorePointView();
    }
}

function updateRestorePointView() {
    const st = window.restorePointState;
    document.querySelectorAll('#restorePointModal .restore-point-step').forEach((el) => {
        const s = parseInt(el.getAttribute('data-step'), 10);
        if (s === st.step) el.classList.remove('hidden'); else el.classList.add('hidden');
    });
    const label = document.getElementById('restorePointStepLabel');
    const title = document.getElementById('restorePointStepTitle');
    if (label) label.textContent = `Step ${st.step} of ${st.totalSteps}`;
    if (title) {
        title.textContent = st.step === 1 ? 'Confirm Snapshot' : (st.step === 2 ? 'Restore Target' : 'Review');
    }
    if (st.step === 1 && st.point) {
        const jobName = document.getElementById('restorePointJobName');
        const manifest = document.getElementById('restorePointManifest');
        const agent = document.getElementById('restorePointAgent');
        if (jobName) jobName.textContent = st.point.job_name || `Job #${st.point.job_id || '—'}`;
        if (manifest) manifest.textContent = `Manifest: ${st.point.manifest_id || '—'}`;
        if (agent) agent.textContent = `Agent: ${st.point.agent_hostname || ('Agent #' + (st.point.agent_id || '—'))}`;
    }
}

function buildRestorePointReview() {
    const st = window.restorePointState;
    const review = {
        restore_point_id: st.point?.id,
        job_name: st.point?.job_name,
        manifest_id: st.point?.manifest_id,
        target_path: st.targetPath,
        mount: st.mount,
    };
    const el = document.getElementById('restorePointReview');
    if (el) {
        el.textContent = JSON.stringify(review, null, 2);
    }
}

function submitRestorePoint() {
    const st = window.restorePointState;
    if (!st.point || !st.point.id) {
        if (window.toast) toast.error('Restore point is missing');
        else alert('Restore point is missing');
        return;
    }
    const data = new URLSearchParams();
    data.set('restore_point_id', String(st.point.id));
    data.set('target_path', st.targetPath || '');
    data.set('mount', st.mount ? 'true' : 'false');

    const submitBtn = document.querySelector('#restorePointModal button[onclick*="restorePointNext"]');
    const originalText = submitBtn ? submitBtn.textContent : 'Submit';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Starting restore...';
    }

    fetch('modules/addons/cloudstorage/api/cloudbackup_start_restore.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.status === 'success') {
            closeRestorePointModal();
            const restoreRunParam = resp.restore_run_uuid || resp.restore_run_id;
            if (restoreRunParam) {
                setTimeout(() => {
                    window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=live&job_id=' +
                        encodeURIComponent(resp.job_id) + '&run_id=' + encodeURIComponent(restoreRunParam);
                }, 500);
            }
        } else {
            if (window.toast) toast.error(resp.message || 'Failed to start restore');
            else alert(resp.message || 'Failed to start restore');
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
</script>
{/literal}
