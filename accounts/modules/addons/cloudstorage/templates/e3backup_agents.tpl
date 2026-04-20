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
<div x-data="agentsApp()"
     class="space-y-6"
     @keydown.escape.window="deleteModalOpen && !deleteSubmitting && closeDeleteAgentModal()">
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
                                <td class="text-right" @click.stop>
                                    <div class="inline-flex justify-end" x-data="agentRowActionsDropdown()">
                                        <button type="button"
                                                x-ref="trigger"
                                                class="eb-btn eb-btn-icon eb-btn-sm"
                                                @click.stop="toggle($refs.trigger)"
                                                :aria-expanded="isOpen"
                                                aria-haspopup="menu"
                                                :aria-label="'Actions for ' + (agent.hostname || agent.agent_uuid || 'agent')">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <circle cx="12" cy="5" r="1"></circle>
                                                <circle cx="12" cy="12" r="1"></circle>
                                                <circle cx="12" cy="19" r="1"></circle>
                                            </svg>
                                        </button>
                                        <template x-teleport="body">
                                            <div x-show="isOpen"
                                                 x-cloak
                                                 x-transition
                                                 class="eb-dropdown-menu fixed z-[2100] w-52 overflow-hidden"
                                                 style="display: none;"
                                                 :style="menuPositionStyle"
                                                 role="menu"
                                                 @click.outside="if (!$refs.trigger || !$refs.trigger.contains($event.target)) closeMenu()"
                                                 @keydown.escape.window="if (isOpen) closeMenu()">
                                                <div class="eb-menu-label">Actions</div>
                                                <button type="button"
                                                        role="menuitem"
                                                        class="eb-menu-item"
                                                        @click="closeMenu(); openManage(agent)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.292.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.139.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.634 6.634 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.077-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    </svg>
                                                    Manage
                                                </button>
                                                <template x-if="agent.status === 'active'">
                                                    <button type="button"
                                                            role="menuitem"
                                                            class="eb-menu-item is-warning"
                                                            @click="closeMenu(); toggleAgent(agent)">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                            <rect x="6" y="4" width="4" height="16"></rect>
                                                            <rect x="14" y="4" width="4" height="16"></rect>
                                                        </svg>
                                                        Disable
                                                    </button>
                                                </template>
                                                <template x-if="agent.status !== 'active'">
                                                    <button type="button"
                                                            role="menuitem"
                                                            class="eb-menu-item"
                                                            style="color: var(--eb-success-text);"
                                                            @click="closeMenu(); toggleAgent(agent)">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                            <polygon points="5 3 19 12 5 21"></polygon>
                                                        </svg>
                                                        Enable
                                                    </button>
                                                </template>
                                                <div class="eb-menu-divider"></div>
                                                <button type="button"
                                                        role="menuitem"
                                                        class="eb-menu-item is-danger"
                                                        @click="closeMenu(); openDeleteAgentModal(agent)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </div>
                                        </template>
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

    <div x-show="deleteModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="presentation">
        <div class="eb-modal-backdrop fixed inset-0" @click="!deleteSubmitting && closeDeleteAgentModal()" aria-hidden="true"></div>
        <div class="eb-modal eb-modal--confirm relative z-10 w-full"
             role="dialog"
             aria-modal="true"
             aria-labelledby="e3-agents-delete-modal-title"
             @click.outside="!deleteSubmitting && closeDeleteAgentModal()">
            <div class="eb-modal-header">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--danger shrink-0" aria-hidden="true">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 id="e3-agents-delete-modal-title" class="eb-modal-title">Delete agent?</h2>
                        <p class="eb-modal-subtitle mt-1 truncate"
                           x-show="agentPendingDelete && (agentPendingDelete.hostname || agentPendingDelete.device_name || agentPendingDelete.agent_uuid)"
                           x-text="agentPendingDelete ? (agentPendingDelete.hostname || agentPendingDelete.device_name || agentPendingDelete.agent_uuid || '') : ''"></p>
                    </div>
                </div>
                <button type="button"
                        class="eb-modal-close"
                        :disabled="deleteSubmitting"
                        @click="closeDeleteAgentModal()"
                        aria-label="Close">&times;</button>
            </div>
            <div class="eb-modal-body">
                <p class="eb-type-body">This will permanently remove this agent and the device will need to re-enroll.</p>
            </div>
            <div class="eb-modal-footer">
                <button type="button"
                        class="eb-btn eb-btn-secondary eb-btn-sm"
                        :disabled="deleteSubmitting"
                        @click="closeDeleteAgentModal()">
                    Cancel
                </button>
                <button type="button"
                        class="eb-btn eb-btn-danger-solid eb-btn-sm"
                        :disabled="deleteSubmitting"
                        @click="confirmDeleteAgent()">
                    <span x-text="deleteSubmitting ? 'Deleting…' : 'Delete agent'"></span>
                </button>
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
/**
 * Row actions menu for agents: teleports to body + fixed positioning so it is not clipped by .eb-table-shell overflow-x-auto.
 */
function agentRowActionsDropdown() {
    return {
        isOpen: false,
        menuPositionStyle: '',
        MENU_WIDTH_PX: 208,
        GAP_PX: 8,
        closeMenu() {
            this.isOpen = false;
            this.menuPositionStyle = '';
        },
        toggle(triggerEl) {
            if (this.isOpen) {
                this.closeMenu();
                return;
            }
            if (!triggerEl) {
                return;
            }
            const rect = triggerEl.getBoundingClientRect();
            const margin = 8;
            let left = rect.right - this.MENU_WIDTH_PX;
            if (left < margin) {
                left = margin;
            }
            const maxLeft = window.innerWidth - this.MENU_WIDTH_PX - margin;
            if (left > maxLeft) {
                left = Math.max(margin, maxLeft);
            }
            let top = rect.bottom + this.GAP_PX;
            const estH = 220;
            const vwH = window.innerHeight;
            if (top + estH > vwH - margin) {
                top = Math.max(margin, rect.top - estH - this.GAP_PX);
            }
            this.menuPositionStyle =
                'top:' + Math.round(top) + 'px;left:' + Math.round(left) + 'px;width:' + this.MENU_WIDTH_PX + 'px';
            this.isOpen = true;
        }
    };
}

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
        deleteModalOpen: false,
        agentPendingDelete: null,
        deleteSubmitting: false,

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
        
        openDeleteAgentModal(agent) {
            this.agentPendingDelete = agent || null;
            this.deleteModalOpen = true;
        },

        closeDeleteAgentModal() {
            if (this.deleteSubmitting) {
                return;
            }
            this.deleteModalOpen = false;
            this.agentPendingDelete = null;
        },

        async confirmDeleteAgent() {
            const agent = this.agentPendingDelete;
            if (!agent || this.deleteSubmitting) {
                return;
            }
            this.deleteSubmitting = true;
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
                    this.deleteModalOpen = false;
                    this.agentPendingDelete = null;
                    await this.loadAgents();
                } else {
                    alert(data.message || 'Failed to delete agent');
                }
            } catch (e) {
                alert('Failed to delete agent');
            } finally {
                this.deleteSubmitting = false;
            }
        },

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
