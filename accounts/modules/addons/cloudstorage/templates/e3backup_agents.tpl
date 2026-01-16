<div class="min-h-screen bg-slate-950 text-gray-200" x-data="agentsApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="agents"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        {* Glass panel container *}
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Agents</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Backup Agents</h1>
                <p class="text-xs text-slate-400 mt-1">Manage your backup agents and their configurations.</p>
            </div>
            <div class="flex gap-2 mt-4 sm:mt-0">
                <a href="/client_installer/e3-backup-agent-setup.exe" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-semibold hover:bg-slate-600" target="_blank" rel="noopener">
                    Download Agent
                </a>
                <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="px-4 py-2 rounded-md bg-sky-600 text-white text-sm font-semibold hover:bg-sky-500">
                    Get Enrollment Token
                </a>
            </div>
        </div>

        {if $isMspClient}
        <!-- Tenant Filter (MSP Only) -->
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-3">
                <label class="text-sm text-slate-400">Filter by Tenant:</label>
                <div
                    x-data="{
                        isOpen: false,
                        tenantLabel() {
                            if (!this.tenantFilter) return 'All Agents';
                            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
                            try {
                                const sel = String(this.tenantFilter);
                                const safe = sel.split('\"').join('\\\\\"');
                                const el = document.querySelector('[data-agents-tenant-option=\"' + safe + '\"]');
                                const txt = el ? String(el.textContent || '').trim() : '';
                                if (txt) return txt;
                            } catch (e) {}
                            return 'Tenant ' + this.tenantFilter;
                        }
                    }"
                    class="relative"
                    @click.away="isOpen = false"
                >
                    <button
                        type="button"
                        @click="isOpen = !isOpen"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    >
                        <span class="truncate max-w-[14rem]" x-text="tenantLabel()"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div
                        x-show="isOpen"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 mt-2 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                        style="display: none;"
                    >
                        <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                            Select tenant
                        </div>
                        <div class="py-1 max-h-72 overflow-auto">
                            <button
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm transition"
                                :class="tenantFilter === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="tenantFilter=''; isOpen=false; loadAgents()"
                                data-agents-tenant-option=""
                            >
                                All Agents
                            </button>
                            <button
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm transition"
                                :class="tenantFilter === 'direct' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="tenantFilter='direct'; isOpen=false; loadAgents()"
                                data-agents-tenant-option="direct"
                            >
                                Direct (No Tenant)
                            </button>
                            {foreach from=$tenants item=tenant}
                            <button
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm transition"
                                :class="String(tenantFilter) === String('{$tenant->id}') ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="tenantFilter='{$tenant->id}'; isOpen=false; loadAgents()"
                                data-agents-tenant-option="{$tenant->id}"
                            >
                                {$tenant->name|escape}
                            </button>
                            {/foreach}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Column selector (same Alpine scope as agentsApp()) -->
            <div class="relative sm:ml-auto">
                <button @click="columnsOpen = !columnsOpen" class="text-xs px-3 py-2 rounded bg-slate-900 border border-slate-700 hover:border-slate-500 text-slate-200">
                    Columns ▾
                </button>
                <div x-show="columnsOpen" @click.outside="columnsOpen=false" class="absolute right-0 mt-2 w-56 rounded-lg border border-slate-700 bg-slate-900 shadow-lg p-3 z-10">
                    <label class="flex items-center gap-2 text-xs text-slate-200">
                        <input type="checkbox" class="rounded border-slate-600 bg-slate-950" x-model="showDeviceId">
                        <span>Device ID</span>
                    </label>
                    <label class="mt-2 flex items-center gap-2 text-xs text-slate-200">
                        <input type="checkbox" class="rounded border-slate-600 bg-slate-950" x-model="showDeviceName">
                        <span>Device Name</span>
                    </label>
                </div>
            </div>
        </div>
        {/if}

        <!-- Agents Table -->
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Connection</th>
                        <th class="px-4 py-3 text-left font-medium">ID</th>
                        <th class="px-4 py-3 text-left font-medium">Hostname</th>
                        <th class="px-4 py-3 text-left font-medium" x-show="showDeviceId">Device ID</th>
                        <th class="px-4 py-3 text-left font-medium" x-show="showDeviceName">Device Name</th>
                        {if $isMspClient}
                        <th class="px-4 py-3 text-left font-medium">Tenant</th>
                        {/if}
                        <th class="px-4 py-3 text-left font-medium">Type</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Last Seen</th>
                        <th class="px-4 py-3 text-left font-medium">Created</th>
                        <th class="px-4 py-3 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <template x-if="loading">
                        <tr>
                            <td :colspan="{if $isMspClient}9{else}8{/if} + (showDeviceId ? 1 : 0) + (showDeviceName ? 1 : 0)" class="px-4 py-8 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && agents.length === 0">
                        <tr>
                            <td :colspan="{if $isMspClient}9{else}8{/if} + (showDeviceId ? 1 : 0) + (showDeviceName ? 1 : 0)" class="px-4 py-8 text-center text-slate-400">
                                No agents found. <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="text-sky-400 hover:underline">Generate an enrollment token</a> to add agents.
                            </td>
                        </tr>
                    </template>
                    <template x-for="agent in agents" :key="agent.id">
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                      :class="agent.online_status === 'online' ? 'bg-emerald-500/15 text-emerald-200' : (agent.online_status === 'offline' ? 'bg-rose-500/15 text-rose-200' : 'bg-slate-700 text-slate-300')">
                                    <span class="h-1.5 w-1.5 rounded-full"
                                          :class="agent.online_status === 'online'
                                                ? 'bg-emerald-400 ring-2 ring-emerald-400/20 shadow-[0_0_10px_rgba(52,211,153,0.45)]'
                                                : (agent.online_status === 'offline'
                                                    ? 'bg-rose-400'
                                                    : 'bg-slate-500')"></span>
                                    <span x-text="agent.online_status === 'online' ? 'online' : (agent.online_status === 'offline' ? 'offline' : 'never')"></span>
                                </span>
                                <span class="ml-2 text-xs text-slate-500" x-show="agent.seconds_since_seen !== null && agent.seconds_since_seen !== undefined"
                                      x-text="agent.online_status === 'online' ? '' : '(' + agent.seconds_since_seen + 's)'"></span>
                            </td>
                            <td class="px-4 py-3 text-slate-200" x-text="agent.id"></td>
                            <td class="px-4 py-3 text-slate-200" x-text="agent.hostname || '—'"></td>
                            <td class="px-4 py-3 text-slate-300 font-mono text-xs" x-show="showDeviceId" x-text="agent.device_id || '—'"></td>
                            <td class="px-4 py-3 text-slate-300" x-show="showDeviceName" x-text="agent.device_name || '—'"></td>
                            {if $isMspClient}
                            <td class="px-4 py-3 text-slate-300" x-text="agent.tenant_name || 'Direct'"></td>
                            {/if}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="{ 'bg-sky-500/15 text-sky-200': agent.agent_type === 'workstation', 'bg-violet-500/15 text-violet-200': agent.agent_type === 'server', 'bg-amber-500/15 text-amber-200': agent.agent_type === 'hypervisor' }"
                                      x-text="agent.agent_type || 'workstation'"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                      :class="agent.status === 'active' ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-700 text-slate-300'">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="agent.status === 'active' ? 'bg-emerald-400' : 'bg-slate-500'"></span>
                                    <span x-text="agent.status"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="agent.last_seen_at || '—'"></td>
                            <td class="px-4 py-3 text-slate-300" x-text="agent.created_at"></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <button @click="toggleAgent(agent)" class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 hover:border-slate-500"
                                            x-text="agent.status === 'active' ? 'Disable' : 'Enable'"></button>
                                    <button @click="deleteAgent(agent)" class="text-xs px-2 py-1 rounded bg-rose-900/50 border border-rose-700 hover:border-rose-500 text-rose-200">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>

{literal}
<script>
function agentsApp() {
    return {
        agents: [],
        loading: true,
        tenantFilter: '',
        showDeviceId: false,
        showDeviceName: false,
        columnsOpen: false,
        
        init() {
            // Persisted column preferences
            try {
                const saved = JSON.parse(localStorage.getItem('e3_agents_columns') || '{}');
                this.showDeviceId = !!saved.showDeviceId;
                this.showDeviceName = !!saved.showDeviceName;
            } catch (e) {}
            this.initWatches();
            this.loadAgents();
        },
        
        // Alpine v3: use init-time $watch calls (instead of a $watch: {} object which is not supported)
        initWatches() {
            this.$watch('showDeviceId', () => this.persistColumns());
            this.$watch('showDeviceName', () => this.persistColumns());
        },

        persistColumns() {
            try {
                localStorage.setItem('e3_agents_columns', JSON.stringify({
                    showDeviceId: this.showDeviceId,
                    showDeviceName: this.showDeviceName
                }));
            } catch (e) {}
        },
        
        async loadAgents() {
            this.loading = true;
            try {
                let url = 'modules/addons/cloudstorage/api/e3backup_agent_list.php';
                if (this.tenantFilter) {
                    url += '?tenant_id=' + encodeURIComponent(this.tenantFilter);
                }
                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    this.agents = data.agents || [];
                } else {
                    console.error(data.message);
                }
            } catch (e) {
                console.error('Failed to load agents:', e);
            }
            this.loading = false;
        },
        
        async toggleAgent(agent) {
            const newStatus = agent.status === 'active' ? 'disabled' : 'active';
            if (!confirm(`${newStatus === 'disabled' ? 'Disable' : 'Enable'} this agent?`)) return;
            
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_agent_toggle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ agent_id: agent.id, status: newStatus })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.loadAgents();
                } else {
                    alert(data.message || 'Failed to update agent');
                }
            } catch (e) {
                alert('Failed to update agent');
            }
        },
        
        async deleteAgent(agent) {
            if (!confirm('Delete this agent? This will permanently remove it and the device will need to re-enroll.')) return;
            
            try {
                const res = await fetch('modules/addons/cloudstorage/api/agent_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ agent_id: agent.id })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.loadAgents();
                } else {
                    alert(data.message || 'Failed to delete agent');
                }
            } catch (e) {
                alert('Failed to delete agent');
            }
        }
    };
}
</script>
{/literal}

