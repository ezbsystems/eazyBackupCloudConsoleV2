<div class="min-h-screen bg-slate-950 text-gray-200" x-data="diskImageRestorePage()" x-init="init()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="disk_restore"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Disk Image Recovery</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Disk Image Recovery</h1>
                <p class="text-xs text-slate-400 mt-1">Generate a recovery token and preview the disk layout before a bare-metal restore.</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3">
                <div class="flex flex-wrap gap-3 items-center">
                    {if $isMspClient}
                        <select class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm" x-model="tenantFilter" @change="reload()">
                            <option value="">All tenants</option>
                            <option value="direct">Direct clients</option>
                            {foreach $tenants as $t}
                                <option value="{$t->id}">{$t->name}</option>
                            {/foreach}
                        </select>
                    {/if}
                    <select class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm" x-model="agentFilter" @change="reload()">
                        <option value="">All agents</option>
                        {foreach $agents as $a}
                            <option value="{$a->agent_uuid}">{$a->hostname|default:$a->device_name|default:$a->agent_uuid}</option>
                        {/foreach}
                    </select>
                    <input type="text" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm flex-1" placeholder="Search restore points" x-model="searchQuery" @input.debounce.400ms="reload()" />
                </div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-800/60 text-slate-400">
                        <tr>
                            <th class="text-left px-4 py-3">Restore Point</th>
                            <th class="text-left px-4 py-3">Agent</th>
                            <th class="text-left px-4 py-3">Size</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="point in restorePoints" :key="point.id">
                            <tr class="border-t border-slate-800 hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="text-slate-100 font-medium" x-text="point.job_name || ('Restore Point #' + point.id)"></div>
                                    <div class="text-xs text-slate-500" x-text="point.created_at"></div>
                                </td>
                                <td class="px-4 py-3 text-slate-300" x-text="point.agent_hostname || 'Unknown'"></td>
                                <td class="px-4 py-3 text-slate-300" x-text="formatBytes(point.disk_total_bytes || 0)"></td>
                                <td class="px-4 py-3">
                                    <span class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 text-slate-300" x-text="point.status || 'unknown'"></span>
                                    <div class="text-[11px] text-amber-300 mt-1" x-show="!point.is_restorable && point.non_restorable_reason" x-text="point.non_restorable_reason"></div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button class="text-xs px-3 py-1 rounded border"
                                            :class="point.is_restorable ? 'bg-sky-700/30 border-sky-600 text-sky-200' : 'bg-slate-800 border-slate-700 text-slate-500 cursor-not-allowed'"
                                            @click="selectPoint(point)"
                                            :disabled="!point.is_restorable">
                                        <span x-text="point.is_restorable ? 'Select' : 'Unavailable'"></span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="restorePoints.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">No disk image restore points found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                <h3 class="text-sm font-semibold text-slate-200 mb-3">Selected Restore Point</h3>
                <template x-if="!selectedPoint">
                    <div class="text-sm text-slate-500">Select a disk image restore point to view layout and generate a recovery token.</div>
                </template>
                <template x-if="selectedPoint">
                    <div class="space-y-2">
                        <div class="text-sm text-slate-200 font-medium" x-text="selectedPoint.job_name || ('Restore Point #' + selectedPoint.id)"></div>
                        <div class="text-xs text-slate-500" x-text="selectedPoint.manifest_id"></div>
                        <div class="text-xs text-slate-500">Disk size: <span x-text="formatBytes(selectedPoint.disk_total_bytes || 0)"></span></div>
                        <div class="text-xs text-slate-500">Used: <span x-text="formatBytes(selectedPoint.disk_used_bytes || 0)"></span></div>
                        <div class="text-xs text-slate-500">Boot: <span x-text="selectedPoint.disk_boot_mode || 'unknown'"></span></div>
                        <div class="text-xs text-slate-500">Partition: <span x-text="selectedPoint.disk_partition_style || 'unknown'"></span></div>
                        <div class="text-xs text-amber-300" x-show="!selectedPoint.is_restorable && selectedPoint.non_restorable_reason" x-text="selectedPoint.non_restorable_reason"></div>
                    </div>
                </template>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3" x-show="selectedPoint">
                <h3 class="text-sm font-semibold text-slate-200">Disk Layout</h3>
                <template x-if="layoutPartitions.length === 0">
                    <div class="text-xs text-slate-500">No partition layout metadata available.</div>
                </template>
                <template x-for="part in layoutPartitions" :key="part.index">
                    <div class="flex items-center justify-between text-xs text-slate-400 border border-slate-800 rounded-lg px-2 py-1">
                        <span x-text="part.name || part.path"></span>
                        <span x-text="formatBytes(part.size_bytes || 0)"></span>
                    </div>
                </template>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3" x-show="selectedPoint">
                <h3 class="text-sm font-semibold text-slate-200">Recovery Token</h3>
                <div class="text-xs text-slate-500">Use this code on the recovery media to start a bare‑metal restore.</div>
                <input type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100" placeholder="Token will appear here" x-model="generatedToken" readonly>
                <div class="flex items-center gap-2">
                    <button class="text-xs px-3 py-2 rounded bg-emerald-600/30 border border-emerald-500 text-emerald-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="generateToken()"
                            :disabled="loadingToken || !selectedPoint || !selectedPoint.is_restorable">
                        Generate Token
                    </button>
                    <span class="text-xs text-slate-500" x-text="tokenExpiry"></span>
                </div>
            </div>
        </div>
        </div>
        </div>
    </div>
</div>

{literal}
<script>
function diskImageRestorePage() {
    return {
        restorePoints: [],
        selectedPoint: null,
        layoutPartitions: [],
        tenantFilter: '',
        agentFilter: '',
        searchQuery: '',
        generatedToken: '',
        tokenExpiry: '',
        loadingToken: false,
        preselectId: '',

        init() {
            const qs = new URLSearchParams(window.location.search || '');
            this.preselectId = qs.get('restore_point_id') || '';
            this.reload();
        },

        async reload() {
            const params = new URLSearchParams();
            params.set('engine', 'disk_image');
            if (this.tenantFilter) params.set('tenant_id', this.tenantFilter);
            if (this.agentFilter) params.set('agent_uuid', this.agentFilter);
            if (this.searchQuery) params.set('search', this.searchQuery);
            const resp = await fetch(`modules/addons/cloudstorage/api/e3backup_restore_points_list.php?${params.toString()}`);
            const data = await resp.json();
            if (data.status === 'success') {
                this.restorePoints = data.restore_points || [];
                if (this.preselectId) {
                    const match = this.restorePoints.find(p => String(p.id) === String(this.preselectId));
                    if (match) {
                        this.selectPoint(match);
                        this.preselectId = '';
                    }
                }
            }
        },

        selectPoint(point) {
            if (!point || point.is_restorable === false) {
                if (point && point.non_restorable_reason) {
                    alert(point.non_restorable_reason);
                }
                return;
            }
            this.selectedPoint = point;
            this.generatedToken = '';
            this.tokenExpiry = '';
            this.layoutPartitions = [];
            try {
                const layout = point.disk_layout_json ? JSON.parse(point.disk_layout_json) : null;
                if (layout && Array.isArray(layout.partitions)) {
                    this.layoutPartitions = layout.partitions;
                }
            } catch (e) {}
        },

        async generateToken() {
            if (!this.selectedPoint) return;
            this.loadingToken = true;
            try {
                const form = new FormData();
                form.append('restore_point_id', this.selectedPoint.id);
                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_recovery_token_create.php', { method: 'POST', body: form });
                const data = await resp.json();
                if (data.status === 'success') {
                    this.generatedToken = data.token || '';
                    this.tokenExpiry = data.expires_at ? `Expires: ${data.expires_at}` : '';
                } else {
                    const code = data.code || '';
                    if (code === 'schema_upgrade_required') {
                        const missing = Array.isArray(data.missing_columns) && data.missing_columns.length
                            ? ` Missing columns: ${data.missing_columns.join(', ')}.`
                            : '';
                        alert(`Recovery token schema is incomplete on this installation. Please run module upgrade.${missing}`);
                    } else if (code === 'invalid_request') {
                        alert(data.message || 'Missing required request fields.');
                    } else {
                        alert(data.message || 'Failed to generate token');
                    }
                }
            } catch (e) {
                alert('Failed to generate token');
            } finally {
                this.loadingToken = false;
            }
        },

        formatBytes(n) {
            const units = ['B','KB','MB','GB','TB'];
            let val = Number(n || 0);
            let idx = 0;
            while (val >= 1024 && idx < units.length - 1) {
                val /= 1024;
                idx += 1;
            }
            return `${val.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
        }
    }
}
</script>
{/literal}
