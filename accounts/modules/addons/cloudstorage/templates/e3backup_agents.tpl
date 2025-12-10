<div class="min-h-screen bg-slate-950 text-gray-200" x-data="agentsApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
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
                <a href="/client_installer/e3-backup-agent.exe" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-semibold hover:bg-slate-600" target="_blank" rel="noopener">
                    Download Agent
                </a>
                <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="px-4 py-2 rounded-md bg-sky-600 text-white text-sm font-semibold hover:bg-sky-500">
                    Get Enrollment Token
                </a>
            </div>
        </div>

        {if $isMspClient}
        <!-- Tenant Filter (MSP Only) -->
        <div class="mb-4 flex items-center gap-4">
            <label class="text-sm text-slate-400">Filter by Tenant:</label>
            <select x-model="tenantFilter" @change="loadAgents()" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">All Agents</option>
                <option value="direct">Direct (No Tenant)</option>
                {foreach from=$tenants item=tenant}
                <option value="{$tenant->id}">{$tenant->name|escape}</option>
                {/foreach}
            </select>
        </div>
        {/if}

        <!-- Agents Table -->
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">ID</th>
                        <th class="px-4 py-3 text-left font-medium">Hostname</th>
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
                            <td colspan="{if $isMspClient}8{else}7{/if}" class="px-4 py-8 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && agents.length === 0">
                        <tr>
                            <td colspan="{if $isMspClient}8{else}7{/if}" class="px-4 py-8 text-center text-slate-400">
                                No agents found. <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="text-sky-400 hover:underline">Generate an enrollment token</a> to add agents.
                            </td>
                        </tr>
                    </template>
                    <template x-for="agent in agents" :key="agent.id">
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3 text-slate-200" x-text="agent.id"></td>
                            <td class="px-4 py-3 text-slate-200" x-text="agent.hostname || '—'"></td>
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
                                    <button @click="revokeAgent(agent)" class="text-xs px-2 py-1 rounded bg-rose-900/50 border border-rose-700 hover:border-rose-500 text-rose-200">Revoke</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
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
        
        init() {
            this.loadAgents();
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
        
        async revokeAgent(agent) {
            if (!confirm('Revoke this agent? This will permanently invalidate its token.')) return;
            
            try {
                const res = await fetch('modules/addons/cloudstorage/api/agent_disable.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ agent_id: agent.id, revoke: '1' })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.loadAgents();
                } else {
                    alert(data.message || 'Failed to revoke agent');
                }
            } catch (e) {
                alert('Failed to revoke agent');
            }
        }
    };
}
</script>
{/literal}

