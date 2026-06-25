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

{capture assign=ebE3AgentsHeaderActions}
    <span class="eb-badge eb-badge--neutral" x-text="loading ? 'Loading' : (filteredAgents().length + ' agents')"></span>
{/capture}

{capture assign=ebE3Content}
<div x-data="agentsApp()"
     class="space-y-6"
     @keydown.escape.window="deleteModalOpen && !deleteSubmitting && closeDeleteAgentModal()">
    {* Themed toast stack (replaces browser alert() for agent action feedback) *}
    <div x-cloak
         style="position:fixed; top:1rem; right:1rem; z-index:9999; display:flex; flex-direction:column; gap:0.5rem; max-width:380px;">
        <template x-for="toast in agentToasts" :key="toast.id">
            <div :class="'eb-toast eb-toast--' + toast.variant"
                 x-transition.opacity
                 role="status"
                 @click="dismissAgentToast(toast.id)"
                 style="cursor:pointer;">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path x-show="toast.variant === 'success'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    <path x-show="toast.variant === 'danger'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    <path x-show="toast.variant === 'warning'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    <path x-show="toast.variant === 'info'" stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
    {include file="$template/includes/ui/page-header.tpl"
        ebPageTitle='Registered Agents'
        ebPageDescription='Manage backup agents, connection state, enrollment, and recovery actions.'
        ebPageActions=$ebE3AgentsHeaderActions
    }

    {* Tab switch: Agents table vs Enrollment Tokens (moved here from a
       top-level sidebar item). *}
    <div class="eb-tab-bar" role="tablist">
        <button type="button"
                class="eb-tab"
                :class="activeTab === 'agents' ? 'is-active' : ''"
                @click="activeTab = 'agents'"
                role="tab"
                :aria-selected="activeTab === 'agents'">
            <svg class="eb-tab-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
            </svg>
            Agents
        </button>
        <button type="button"
                class="eb-tab"
                :class="activeTab === 'tokens' ? 'is-active' : ''"
                @click="activeTab = 'tokens'"
                role="tab"
                :aria-selected="activeTab === 'tokens'">
            <svg class="eb-tab-icon" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
                <path d="M480-80 120-280v-400l360-200 360 200v400L480-80ZM364-590q23-24 53-37t63-13q33 0 63 13t53 37l120-67-236-131-236 131 120 67Zm76 396v-131q-54-14-87-57t-33-98q0-11 1-20.5t4-19.5l-125-70v263l240 133Zm96.5-229.5Q560-447 560-480t-23.5-56.5Q513-560 480-560t-56.5 23.5Q400-513 400-480t23.5 56.5Q447-400 480-400t56.5-23.5ZM520-194l240-133v-263l-125 70q3 10 4 19.5t1 20.5q0 55-33 98t-87 57v131Z" />
            </svg>
            Enrollment Tokens
        </button>
    </div>

    <div x-show="activeTab === 'agents'">
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
                        <span>Agent UUID</span>
                        <input type="checkbox" class="eb-checkbox" x-model="showAgentUuid">
                    </label>
                    <label class="eb-menu-checklist-item mt-2">
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
                            <th x-show="showAgentUuid">Agent UUID</th>
                            <th>Hostname</th>
                            <th x-show="showDeviceId">Device ID</th>
                            <th x-show="showDeviceName">Device Name</th>
                            {if $isMspClient}
                            <th>Tenant</th>
                            {/if}
                            <th>Type</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Last Seen</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td :colspan="{if $isMspClient}9{else}8{/if} + (showAgentUuid ? 1 : 0) + (showDeviceId ? 1 : 0) + (showDeviceName ? 1 : 0)" class="!px-4 !py-10 text-center">
                                    <div class="inline-flex items-center gap-3 text-sm text-[var(--eb-text-muted)]">
                                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-[color:var(--eb-info-border)] border-t-[color:var(--eb-info-icon)]"></span>
                                        Loading agents...
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && filteredAgents().length === 0">
                            <tr>
                                <td :colspan="{if $isMspClient}9{else}8{/if} + (showAgentUuid ? 1 : 0) + (showDeviceId ? 1 : 0) + (showDeviceName ? 1 : 0)" class="!px-4 !py-10">
                                    <template x-if="searchQuery.trim()">
                                        <div class="eb-app-empty">
                                            <div class="eb-app-empty-title">No agents match your search</div>
                                            <p class="eb-app-empty-copy">Try a different search term or clear the filter.</p>
                                        </div>
                                    </template>
                                    <template x-if="!searchQuery.trim()">
                                        <div class="eb-app-empty">
                                            <span class="eb-icon-box eb-icon-box--lg eb-icon-box--default mb-3" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                                </svg>
                                            </span>
                                            <div class="eb-app-empty-title">No agents enrolled yet</div>
                                            <p class="eb-app-empty-copy">
                                                Install the e3 Backup Agent on the computer you want to protect.
                                                Sign in with your portal email + password from the installer; the agent will appear here once it connects.
                                            </p>
                                            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                                                <button type="button"
                                                        class="eb-btn eb-btn-primary eb-btn-sm"
                                                        onclick="window.dispatchEvent(new Event('open-e3-download-flyout'))">
                                                    Download Agent
                                                </button>
                                                <a href="index.php?m=cloudstorage&page=e3backup&view=getting_started" class="eb-btn eb-btn-secondary eb-btn-sm">
                                                    Open Getting Started
                                                </a>
                                            </div>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <template x-for="agent in filteredAgents()" :key="agent.agent_uuid || agent.device_id || agent.hostname">
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="eb-status-dot"
                                              :class="agent.online_status === 'online' ? 'eb-status-dot--active' : (agent.online_status === 'offline' ? 'eb-status-dot--error' : 'eb-status-dot--inactive')"
                                              :title="agent.online_status === 'online' ? 'Online' : (agent.online_status === 'offline' ? 'Offline' : 'Never connected')"
                                              :aria-label="agent.online_status === 'online' ? 'Online' : (agent.online_status === 'offline' ? 'Offline' : 'Never connected')"></span>
                                        <span class="text-xs text-[var(--eb-text-muted)]" x-show="agent.seconds_since_seen !== null && agent.seconds_since_seen !== undefined && agent.online_status !== 'online'" x-text="'(' + agent.seconds_since_seen + 's)'"></span>
                                    </div>
                                </td>
                                <td class="eb-table-mono eb-table-primary" x-show="showAgentUuid" x-text="agent.agent_uuid || '—'"></td>
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
                                <td class="eb-table-primary">
                                    <div class="flex items-center gap-2">
                                        <span x-text="agent.agent_version || '—'"></span>
                                        <span class="eb-badge eb-badge--warning"
                                              x-show="agent.update_available" x-cloak
                                              title="A newer version is available">Update</span>
                                    </div>
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
                                                        class="eb-menu-item w-full"
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
                                                            class="eb-menu-item is-warning w-full"
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
                                                            class="eb-menu-item w-full"
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
                                                        class="eb-menu-item is-danger w-full"
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
    </div>

    <div x-show="activeTab === 'tokens'" x-cloak>
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_tokens_panel.tpl"
            isMspClient=$isMspClient
            tenants=$tenants
            token=$token
        }
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
                            <p class="eb-card-subtitle">Remotely update the backup agent to the latest version.</p>
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt class="eb-kv-label">Installed</dt>
                            <dd class="eb-kv-value" x-text="(selectedAgent && selectedAgent.agent_version) ? selectedAgent.agent_version : 'Unknown'"></dd>
                        </div>
                        <div>
                            <dt class="eb-kv-label">Latest</dt>
                            <dd class="eb-kv-value" x-text="(selectedAgent && selectedAgent.latest_version) ? selectedAgent.latest_version : '—'"></dd>
                        </div>
                    </dl>

                    <!-- Live progress while an update is running -->
                    <div class="mt-3" x-show="isUpdateActive()" x-cloak>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-[var(--eb-text-muted)]" x-text="updateStatusText()"></span>
                        </div>
                        <div class="eb-progress-track">
                            <div class="eb-progress-fill" :style="updateProgressStyle()"></div>
                        </div>
                    </div>

                    <!-- Terminal result alert (current drawer session only) -->
                    <div class="mt-3" x-show="showUpdateResult && updateJob && ['success','failed','timeout'].includes(updateJob.status)" x-cloak>
                        <div class="eb-alert" :class="updateJob && updateJob.status === 'success' ? 'eb-alert--success' : 'eb-alert--danger'">
                            <div>
                                <div class="eb-alert-title" x-text="updateJob && updateJob.status === 'success' ? 'Update complete' : 'Update failed'"></div>
                                <p x-text="updateJob ? (updateJob.detail || '') : ''"></p>
                            </div>
                        </div>
                    </div>

                    <button type="button"
                            class="eb-btn eb-btn-primary eb-btn-sm mt-3"
                            :class="canRequestUpdate() ? '' : 'opacity-50 cursor-not-allowed'"
                            :disabled="!canRequestUpdate()"
                            @click="requestUpdate(selectedAgent)">
                        <span x-text="updateButtonLabel()"></span>
                    </button>

                    <p class="mt-2 text-xs text-[var(--eb-text-muted)]"
                       x-show="selectedAgent && selectedAgent.update_supported === false" x-cloak>
                        Remote update is not available for this agent's platform.
                    </p>
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
        showAgentUuid: false,
        showDeviceId: false,
        showDeviceName: false,
        columnsOpen: false,
        manageOpen: false,
        selectedAgent: null,
        deleteModalOpen: false,
        agentPendingDelete: null,
        deleteSubmitting: false,
        updateSubmitting: false,
        updateJob: null,
        showUpdateResult: false,
        updatePollTimer: null,
        agentToasts: [],
        _agentToastSeq: 0,
        activeTab: 'agents',

        init() {
            // Persisted column preferences
            try {
                const saved = JSON.parse(localStorage.getItem('e3_agents_columns') || '{}');
                this.showAgentUuid = saved.showAgentUuid === true;
                this.showDeviceId = !!saved.showDeviceId;
                this.showDeviceName = !!saved.showDeviceName;
            } catch (e) {}
            this.initWatches();
            this.loadAgents();
        },
        
        // Alpine v3: use init-time $watch calls (instead of a $watch: {} object which is not supported)
        initWatches() {
            this.$watch('showAgentUuid', () => this.persistColumns());
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
                    showAgentUuid: this.showAgentUuid,
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
            this.showUpdateResult = false;
            this.updateJob = null;
            const priorJob = agent && agent.update_job ? agent.update_job : null;
            if (priorJob && this.UPDATE_ACTIVE_STATES.includes(priorJob.status)) {
                this.updateJob = priorJob;
                this.startUpdatePolling(agent.agent_uuid);
            }
        },

        closeManage() {
            this.manageOpen = false;
            this.selectedAgent = null;
            this.stopUpdatePolling();
            this.updateJob = null;
            this.showUpdateResult = false;
            this.updateSubmitting = false;
        },

        // ---- Remote agent update ----
        UPDATE_ACTIVE_STATES: ['queued', 'downloading', 'verifying', 'applying', 'restarting', 'verifying_online'],

        isUpdateActive() {
            return !!(this.updateJob && this.UPDATE_ACTIVE_STATES.includes(this.updateJob.status));
        },

        canRequestUpdate() {
            if (!this.selectedAgent) return false;
            if (this.selectedAgent.update_supported === false) return false;
            if (this.updateSubmitting || this.isUpdateActive()) return false;
            return !!this.selectedAgent.update_available;
        },

        updateButtonLabel() {
            if (this.updateSubmitting) return 'Queuing…';
            if (this.isUpdateActive()) return 'Update in progress…';
            if (this.selectedAgent && this.selectedAgent.update_available && this.selectedAgent.latest_version) {
                return 'Update to ' + this.selectedAgent.latest_version;
            }
            return 'Up to date';
        },

        updateStatusText() {
            if (!this.updateJob) return '';
            const labels = {
                queued: 'Queued — waiting for agent…',
                downloading: 'Downloading update…',
                verifying: 'Verifying download…',
                applying: 'Applying update…',
                restarting: 'Restarting service…',
                verifying_online: 'Waiting for agent to come back online…'
            };
            return labels[this.updateJob.status] || (this.updateJob.detail || this.updateJob.status);
        },

        updateProgressPercent() {
            const map = {
                queued: 10, downloading: 40, verifying: 60,
                applying: 80, restarting: 90, verifying_online: 95,
                success: 100, failed: 100, timeout: 100
            };
            return this.updateJob ? (map[this.updateJob.status] || 0) : 0;
        },

        updateProgressStyle() {
            return 'width: ' + this.updateProgressPercent() + '%; background: var(--eb-info-strong);';
        },

        async requestUpdate(agent) {
            if (!agent || !this.canRequestUpdate()) return;
            this.updateSubmitting = true;
            this.showUpdateResult = false;
            try {
                const payload = new URLSearchParams();
                payload.append('agent_uuid', agent.agent_uuid || '');
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_agent_request_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.updateJob = { id: data.update_job_id, status: 'queued', detail: 'Update queued', target_version: data.target_version };
                    this.agentsNotify('success', 'Update queued for ' + (data.target_version || 'latest') + '.');
                    this.startUpdatePolling(agent.agent_uuid);
                } else {
                    this.agentsNotify('error', data.message || 'Failed to queue update.');
                }
            } catch (e) {
                this.agentsNotify('error', 'Failed to queue update.');
            } finally {
                this.updateSubmitting = false;
            }
        },

        startUpdatePolling(agentUuid) {
            this.stopUpdatePolling();
            if (!agentUuid) return;
            this.updatePollTimer = setInterval(() => this.pollUpdateStatus(agentUuid), 3000);
        },

        stopUpdatePolling() {
            if (this.updatePollTimer) {
                clearInterval(this.updatePollTimer);
                this.updatePollTimer = null;
            }
        },

        async pollUpdateStatus(agentUuid) {
            try {
                const url = 'modules/addons/cloudstorage/api/e3backup_agent_update_status.php?agent_uuid=' + encodeURIComponent(agentUuid);
                const res = await fetch(url);
                const data = await res.json();
                if (data.status !== 'success') return;
                this.updateJob = data.update_job || this.updateJob;
                if (this.updateJob && !this.UPDATE_ACTIVE_STATES.includes(this.updateJob.status)) {
                    this.stopUpdatePolling();
                    this.showUpdateResult = true;
                    if (this.updateJob.status === 'success') {
                        this.agentsNotify('success', 'Agent updated successfully.');
                    } else if (this.updateJob.status === 'failed' || this.updateJob.status === 'timeout') {
                        this.agentsNotify('error', this.updateJob.detail || 'Agent update did not complete.');
                    }
                    await this.loadAgents();
                    if (this.manageOpen && this.selectedAgent) {
                        const refreshed = this.agents.find((a) => a.agent_uuid === agentUuid);
                        if (refreshed) this.selectedAgent = refreshed;
                    }
                }
            } catch (e) {
                // Transient; keep polling.
            }
        },

        agentsNotify(type, message) {
            // Render an in-page themed toast (semantic theme) rather than a
            // browser-native alert(), so update feedback matches the client area.
            const variant = (type === 'error' || type === 'danger') ? 'danger'
                : (type === 'warning') ? 'warning'
                : (type === 'info') ? 'info'
                : 'success';
            const id = ++this._agentToastSeq;
            this.agentToasts.push({ id: id, variant: variant, message: String(message || '') });
            const ttl = variant === 'danger' ? 7000 : 4500;
            setTimeout(() => this.dismissAgentToast(id), ttl);
        },

        dismissAgentToast(id) {
            this.agentToasts = this.agentToasts.filter(t => t.id !== id);
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
            const backupUserRouteId = agent.backup_user_route_id ? String(agent.backup_user_route_id) : '';
            if (!backupUserRouteId) {
                e3backupNotify('error', 'Agent is not linked to a backup user.');
                return;
            }
            const isMsp = {/literal}{if $isMspClient}true{else}false{/if}{literal};
            const params = new URLSearchParams();
            params.set('user_id', backupUserRouteId);
            params.set('open_create', '1');
            params.set('prefill_source', 'local_agent');
            if (agent.agent_uuid) params.set('prefill_agent_uuid', String(agent.agent_uuid));
            if (isMsp) {
                if (agent.tenant_id) {
                    params.set('tenant_id', String(agent.tenant_id));
                } else {
                    params.set('tenant_id', 'direct');
                }
            }
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=user_detail&'
                + params.toString() + '#jobs';
        }
    };
}
</script>
{/literal}
