{capture assign=ebE3Actions}
    <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="/client_installer/e3-backup-agent-setup.exe" target="_blank" rel="noopener" class="eb-btn eb-btn-secondary eb-btn-sm">
            Download Agent
        </a>
        <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="eb-btn eb-btn-primary eb-btn-sm">
            Get Enrollment Token
        </a>
    </div>
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3AgentsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Agents</span>
    </div>
{/capture}

{capture assign=ebE3AgentsHeaderActions}
    <span class="eb-badge eb-badge--neutral" x-text="loading ? 'Loading' : (filteredAgents().length + ' agents')"></span>
{/capture}

{capture assign=ebE3Content}
<div x-data="agentsApp()" class="space-y-6">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3AgentsBreadcrumb
        ebPageTitle='Registered Agents'
        ebPageDescription='Manage backup agents, connection state, enrollment, and recovery actions.'
        ebPageActions=$ebE3AgentsHeaderActions
    }

    <div id="services-wrapper" class="eb-subpanel overflow-visible">
        <div class="eb-table-toolbar">
            {if $isMspClient}
            <div class="relative" @click.outside="tenantMenuOpen = false">
                <button
                    type="button"
                    @click="tenantMenuOpen = !tenantMenuOpen"
                    class="eb-btn eb-btn-secondary min-w-[16rem] justify-between"
                >
                    <span class="truncate" x-text="'Tenant: ' + tenantLabel()"></span>
                    <svg class="h-4 w-4 transition-transform" :class="tenantMenuOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="tenantMenuOpen" x-transition class="eb-menu absolute left-0 z-20 mt-2 w-72 overflow-hidden" style="display: none;">
                    <button
                        type="button"
                        class="eb-menu-option"
                        :class="tenantFilter === '' ? 'is-active' : ''"
                        @click="tenantFilter=''; tenantMenuOpen=false; loadAgents()"
                        data-agents-tenant-option=""
                    >
                        All Agents
                    </button>
                    <button
                        type="button"
                        class="eb-menu-option"
                        :class="tenantFilter === 'direct' ? 'is-active' : ''"
                        @click="tenantFilter='direct'; tenantMenuOpen=false; loadAgents()"
                        data-agents-tenant-option="direct"
                    >
                        Direct (No Tenant)
                    </button>
                    {foreach from=$tenants item=tenant}
                    <button
                        type="button"
                        class="eb-menu-option"
                        :class="String(tenantFilter) === String('{$tenant->public_id|escape:'javascript'}') ? 'is-active' : ''"
                        @click="tenantFilter='{$tenant->public_id|escape:'javascript'}'; tenantMenuOpen=false; loadAgents()"
                        data-agents-tenant-option="{$tenant->public_id|escape}"
                    >
                        {$tenant->name|escape}
                    </button>
                    {/foreach}
                </div>
            </div>
            {/if}

            <div class="relative" @click.outside="columnsOpen = false">
                <button type="button" class="eb-btn eb-btn-secondary" @click="columnsOpen = !columnsOpen">
                    <span>Visible Columns</span>
                    <svg class="h-4 w-4 transition-transform" :class="columnsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="columnsOpen" x-transition class="eb-menu absolute left-0 z-20 mt-2 w-56 p-3" style="display: none;">
                    <label class="eb-menu-checklist-item">
                        <span>Device ID</span>
                        <input type="checkbox" class="eb-checkbox" x-model="showDeviceId">
                    </label>
                    <label class="eb-menu-checklist-item mt-2">
                        <span>Device Name</span>
                        <input type="checkbox" class="eb-checkbox" x-model="showDeviceName">
                    </label>
                </div>
            </div>

            <div class="flex-1"></div>

            <input
                type="text"
                x-model.debounce.250ms="searchQuery"
                placeholder="Search agent, hostname, or device"
                class="eb-toolbar-search xl:w-80"
            >
        </div>

        <div class="eb-table-shell overflow-x-auto">
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>Connection</th>
                            <th>Agent UUID</th>
                            <th>Hostname</th>
                            <th x-show="showDeviceId">Device ID</th>
                            <th x-show="showDeviceName">Device Name</th>
                            {if $isMspClient}
                            <th>Tenant</th>
                            {/if}
                            <th>Type</th>
                            <th>Status</th>
                            <th>Last Seen</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td :colspan="{if $isMspClient}9{else}8{/if} + (showDeviceId ? 1 : 0) + (showDeviceName ? 1 : 0)" class="!px-4 !py-10 text-center">
                                    <div class="inline-flex items-center gap-3 text-sm text-[var(--eb-text-muted)]">
                                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-[color:var(--eb-info-border)] border-t-[color:var(--eb-info-icon)]"></span>
                                        Loading agents...
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && filteredAgents().length === 0">
                            <tr>
                                <td :colspan="{if $isMspClient}9{else}8{/if} + (showDeviceId ? 1 : 0) + (showDeviceName ? 1 : 0)" class="!px-4 !py-10 text-center text-sm text-[var(--eb-text-muted)]">
                                    <template x-if="searchQuery.trim()">
                                        <span>No agents match your current search.</span>
                                    </template>
                                    <template x-if="!searchQuery.trim()">
                                        <span>
                                            No agents found.
                                            <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="font-medium text-[var(--eb-info-text)] hover:text-[var(--eb-text-primary)]">Generate an enrollment token</a>
                                            to add agents.
                                        </span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <template x-for="agent in filteredAgents()" :key="agent.agent_uuid || agent.device_id || agent.hostname">
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="eb-badge gap-1.5"
                                              :class="agent.online_status === 'online' ? 'eb-badge--success' : (agent.online_status === 'offline' ? 'eb-badge--danger' : 'eb-badge--default')">
                                            <span class="inline-flex h-2 w-2 rounded-full"
                                                  :class="agent.online_status === 'online' ? 'bg-emerald-400' : (agent.online_status === 'offline' ? 'bg-rose-400' : 'bg-slate-500')"></span>
                                            <span x-text="agent.online_status === 'online' ? 'online' : (agent.online_status === 'offline' ? 'offline' : 'never')"></span>
                                        </span>
                                        <span class="text-xs text-[var(--eb-text-muted)]" x-show="agent.seconds_since_seen !== null && agent.seconds_since_seen !== undefined && agent.online_status !== 'online'" x-text="'(' + agent.seconds_since_seen + 's)'"></span>
                                    </div>
                                </td>
                                <td class="eb-table-mono eb-table-primary" x-text="agent.agent_uuid || '—'"></td>
                                <td class="eb-table-primary" x-text="agent.hostname || '—'"></td>
                                <td class="eb-table-mono" x-show="showDeviceId" x-text="agent.device_id || '—'"></td>
                                <td x-show="showDeviceName" x-text="agent.device_name || '—'"></td>
                                {if $isMspClient}
                                <td x-text="agent.tenant_name || 'Direct'"></td>
                                {/if}
                                <td>
                                    <span class="eb-badge"
                                          :class="agent.agent_type === 'server' ? 'eb-badge--premium' : (agent.agent_type === 'hypervisor' ? 'eb-badge--warning' : 'eb-badge--info')"
                                          x-text="agent.agent_type || 'workstation'"></span>
                                </td>
                                <td>
                                    <span class="eb-badge" :class="agent.status === 'active' ? 'eb-badge--success' : 'eb-badge--default'" x-text="agent.status"></span>
                                </td>
                                <td x-text="agent.last_seen_at || '—'"></td>
                                <td x-text="agent.created_at"></td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="openManage(agent)" class="eb-btn eb-btn-info eb-btn-xs">Manage</button>
                                        <button type="button" @click="toggleAgent(agent)" class="eb-btn eb-btn-secondary eb-btn-xs" x-text="agent.status === 'active' ? 'Disable' : 'Enable'"></button>
                                        <button type="button" @click="deleteAgent(agent)" class="eb-btn eb-btn-danger eb-btn-xs">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
        </div>
    </div>

    <div x-show="manageOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40"
         style="display: none;">
        <div class="absolute inset-0 eb-drawer-backdrop" @click="closeManage()"></div>
        <div class="absolute right-0 top-0 h-full eb-drawer eb-drawer--wide overflow-y-auto">
            <div class="eb-drawer-header">
                <div>
                    <div class="eb-drawer-title">Manage Agent</div>
                    <p class="mt-1 text-sm text-[var(--eb-text-muted)]" x-text="selectedAgent ? (selectedAgent.device_name || selectedAgent.hostname || selectedAgent.agent_uuid || '') : ''"></p>
                </div>
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="closeManage()">Close</button>
            </div>

            <div class="eb-drawer-body space-y-4">
                <div class="eb-card">
                    <div class="eb-card-header">
                        <div>
                            <div class="eb-type-eyebrow">Backup</div>
                            <p class="eb-card-subtitle">Create a new backup job scoped to this agent.</p>
                        </div>
                    </div>
                    <button type="button" class="eb-btn eb-btn-success eb-btn-sm" @click="goToCreateJob(selectedAgent)">
                        Create Backup Job
                    </button>
                </div>

                <div class="eb-card">
                    <div class="eb-card-header">
                        <div>
                            <div class="eb-type-eyebrow">Restore</div>
                            <p class="eb-card-subtitle">Launch restore points filtered to this agent.</p>
                        </div>
                    </div>
                    <button type="button"
                            class="eb-btn eb-btn-info eb-btn-sm"
                            :class="selectedAgent && !selectedAgent.backup_user_route_id ? 'opacity-50 cursor-not-allowed' : ''"
                            :disabled="selectedAgent && !selectedAgent.backup_user_route_id"
                            :title="selectedAgent && !selectedAgent.backup_user_route_id ? 'Agent is not linked to a backup user' : 'Open restore points for this user'"
                            @click="goToRestores(selectedAgent)">
                        Open Restore Points
                    </button>
                </div>

                <div class="eb-card">
                    <div class="eb-card-header">
                        <div>
                            <div class="eb-type-eyebrow">Device Name</div>
                            <p class="eb-card-subtitle">Update the device name shown in the UI.</p>
                        </div>
                    </div>
                    <input type="text" class="eb-input mt-3 w-full" placeholder="Coming soon" disabled>
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm mt-3 cursor-not-allowed opacity-60" disabled>
                        Save
                    </button>
                </div>

                <div class="eb-card">
                    <div class="eb-card-header">
                        <div>
                            <div class="eb-type-eyebrow">Updates</div>
                            <p class="eb-card-subtitle">Trigger an agent policy refresh.</p>
                        </div>
                    </div>
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm cursor-not-allowed opacity-60" disabled>
                        Coming soon
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='agents'
    ebE3Title='Backup Agents'
    ebE3Description='Manage your backup agents and their configurations.'
    ebE3Icon=$ebE3Icon
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

{literal}
<script>
function agentsApp() {
    return {
        agents: [],
        loading: true,
        tenants: {/literal}{if $tenants}{$tenants|json_encode nofilter}{else}[]{/if}{literal},
        tenantFilter: '',
        searchQuery: '',
        tenantMenuOpen: false,
        showDeviceId: false,
        showDeviceName: false,
        columnsOpen: false,
        manageOpen: false,
        selectedAgent: null,
        
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

        tenantLabel() {
            if (!this.tenantFilter) return 'All Agents';
            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
            const match = (this.tenants || []).find(t => String(t.public_id || t.id) === String(this.tenantFilter));
            return match ? match.name : `Tenant ${this.tenantFilter}`;
        },

        normalizedSearchValue(value) {
            return String(value || '').toLowerCase();
        },

        filteredAgents() {
            const term = this.normalizedSearchValue(this.searchQuery).trim();
            if (!term) {
                return this.agents;
            }

            return this.agents.filter((agent) => {
                return [
                    agent.agent_uuid,
                    agent.hostname,
                    agent.device_id,
                    agent.device_name,
                    agent.tenant_name,
                    agent.agent_type,
                    agent.status,
                    agent.online_status
                ].some((value) => this.normalizedSearchValue(value).includes(term));
            });
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
                const payload = new URLSearchParams({ status: newStatus });
                payload.append('agent_uuid', agent.agent_uuid || '');
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_agent_toggle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
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
                const payload = new URLSearchParams();
                payload.append('agent_uuid', agent.agent_uuid || '');
                const res = await fetch('modules/addons/cloudstorage/api/agent_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
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
        ,
        openManage(agent) {
            this.selectedAgent = agent;
            this.manageOpen = true;
        },

        closeManage() {
            this.manageOpen = false;
            this.selectedAgent = null;
        },

        goToRestores(agent) {
            if (!agent) return;
            const backupUserRouteId = agent.backup_user_route_id ? String(agent.backup_user_route_id) : '';
            if (!backupUserRouteId) {
                if (typeof e3backupNotify === 'function') {
                    e3backupNotify('error', 'This agent is not linked to a backup user yet.');
                } else {
                    alert('This agent is not linked to a backup user yet.');
                }
                return;
            }
            const params = new URLSearchParams();
            params.set('user_id', backupUserRouteId);
            if (agent.agent_uuid) params.set('agent_uuid', String(agent.agent_uuid));
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=user_detail&' + params.toString() + '#restore';
        },

        goToCreateJob(agent) {
            if (!agent) return;
            const isMsp = {/literal}{if $isMspClient}true{else}false{/if}{literal};
            const params = [];
            params.push('open_create=1');
            params.push('prefill_source=local_agent');
            if (agent.agent_uuid) params.push('prefill_agent_uuid=' + encodeURIComponent(agent.agent_uuid));
            if (isMsp) {
                if (agent.tenant_id) {
                    params.push('tenant_id=' + encodeURIComponent(agent.tenant_id));
                } else {
                    params.push('tenant_id=direct');
                }
            }
            const qs = params.length ? '&' + params.join('&') : '';
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=jobs' + qs;
        }
    };
}
</script>
{/literal}
