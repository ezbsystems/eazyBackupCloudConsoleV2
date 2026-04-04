{capture assign=ebE3RestoresBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Restores</span>
    </div>
{/capture}

{capture assign=ebE3Content}
<div x-data="restoresApp()" class="space-y-6">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3RestoresBreadcrumb
        ebPageTitle='Restore Points'
        ebPageDescription='Restore from snapshots across all agents, even after jobs are deleted.'
    }

    <div class="eb-subpanel overflow-visible">
        <div class="space-y-4">
            <div class="space-y-3">
                <div class="eb-table-toolbar">
                    {if $isMspClient}
                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-btn eb-btn-secondary eb-btn-sm min-w-[16rem] justify-between">
                            <span class="truncate" x-text="'Tenant: ' + tenantLabel()"></span>
                            <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition
                             class="eb-menu absolute left-0 z-50 mt-2 w-72 overflow-hidden"
                             style="display: none;">
                            <div class="eb-menu-label">Select tenant</div>
                            <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                <input type="text"
                                       x-model="tenantSearch"
                                       placeholder="Search tenants"
                                       class="eb-toolbar-search w-full !py-2 text-sm"
                                       @click.stop>
                            </div>
                            <div class="max-h-72 overflow-auto p-1">
                                <button type="button" class="eb-menu-option" :class="tenantFilter === '' ? 'is-active' : ''" @click="selectTenant(''); isOpen=false;">All Tenants</button>
                                <button type="button" class="eb-menu-option" :class="tenantFilter === 'direct' ? 'is-active' : ''" @click="selectTenant('direct'); isOpen=false;">Direct (No Tenant)</button>
                                <template x-for="tenant in filteredTenants" :key="tenant.public_id || tenant.id">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="String(tenantFilter) === String(tenant.public_id || tenant.id) ? 'is-active' : ''"
                                            @click="selectTenant(String(tenant.public_id || tenant.id)); isOpen=false;">
                                        <span class="truncate" x-text="tenant.name"></span>
                                    </button>
                                </template>
                                <template x-if="filteredTenants.length === 0">
                                    <div class="px-3 py-2 text-sm text-[var(--eb-text-muted)]">No tenants found</div>
                                </template>
                            </div>
                        </div>
                    </div>
                    {/if}

                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-btn eb-btn-secondary eb-btn-sm min-w-[16rem] justify-between">
                            <span class="truncate" x-text="'Agent: ' + agentLabel()"></span>
                            <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition
                             class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-72 overflow-hidden"
                             style="display: none;">
                            <div class="eb-menu-label">Select agent</div>
                            <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                <input type="text"
                                       x-model="agentSearch"
                                       placeholder="Search agents"
                                       class="eb-toolbar-search w-full !py-2 text-sm"
                                       @click.stop>
                            </div>
                            <div class="max-h-72 overflow-auto p-1">
                                <button type="button" class="eb-menu-option" :class="agentFilter === '' ? 'is-active' : ''" @click="selectAgent(''); isOpen=false;">All Agents</button>
                                <template x-for="agent in filteredAgents" :key="agent.agent_uuid || agent.id">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="String(agentFilter) === String(agent.agent_uuid || '') ? 'is-active' : ''"
                                            @click="selectAgent(String(agent.agent_uuid || '')); isOpen=false;">
                                        <span class="truncate" x-text="agent.hostname || agent.device_name || (agent.agent_uuid || 'Unknown agent')"></span>
                                    </button>
                                </template>
                                <template x-if="filteredAgents.length === 0">
                                    <div class="px-3 py-2 text-sm text-[var(--eb-text-muted)]">No agents found</div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1"></div>

                    <input type="text"
                           placeholder="Search restore points"
                           x-model="searchQuery"
                           class="eb-toolbar-search w-full xl:w-80">
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input type="date" x-model="dateFrom" @change="onDateChange()" class="eb-input w-auto min-w-[10rem]">
                    <span class="eb-type-caption">to</span>
                    <input type="date" x-model="dateTo" @change="onDateChange()" class="eb-input w-auto min-w-[10rem]">
                    <button type="button" @click="clearDateRange()" class="eb-pill">Clear</button>
                </div>
            </div>

            <div class="eb-table-shell">
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>Snapshot</th>
                            {if $isMspClient}<th>Tenant</th>{/if}
                            <th>Agent</th>
                            <th>Engine</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Completed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td :colspan="{if $isMspClient}9{else}8{/if}">
                                    <div class="eb-app-empty !py-8">
                                        <div class="inline-flex items-center gap-3 text-sm text-[var(--eb-text-muted)]">
                                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[color:var(--eb-info-border)] border-t-[color:var(--eb-info-icon)]"></span>
                                            Loading restore points...
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && needsFilter">
                            <tr>
                                <td :colspan="{if $isMspClient}9{else}8{/if}">
                                    <div class="eb-app-empty !py-8">
                                        <div class="eb-app-empty-title">Filters required</div>
                                        <p class="eb-app-empty-copy">Select a tenant, agent, date range, or search term to load restore points.</p>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && !needsFilter && restorePoints.length === 0">
                            <tr>
                                <td :colspan="{if $isMspClient}9{else}8{/if}">
                                    <div class="eb-app-empty !py-8">
                                        <div class="eb-app-empty-title">No restore points found</div>
                                        <p class="eb-app-empty-copy">Try a different search term or widen your date range.</p>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-for="point in restorePoints" :key="point.id">
                            <tr>
                                <td class="eb-table-primary">
                                    <div class="font-medium text-[var(--eb-text-primary)]" x-text="point.job_name || 'Unnamed job'"></div>
                                    <div class="text-xs text-[var(--eb-text-muted)]" x-text="point.manifest_id || 'No manifest'"></div>
                                    <div class="text-xs text-[var(--eb-text-secondary)]" x-show="point.hyperv_vm_name">VM: <span x-text="point.hyperv_vm_name"></span></div>
                                </td>
                                {if $isMspClient}
                                <td x-text="point.tenant_name || (point.tenant_deleted ? 'Deleted tenant' : 'Direct')"></td>
                                {/if}
                                <td x-text="point.agent_hostname || point.agent_uuid || '—'"></td>
                                <td>
                                    <span class="eb-badge"
                                          :class="{ 'eb-badge--info': point.engine === 'kopia', 'eb-badge--premium': point.engine === 'disk_image', 'eb-badge--warning': point.engine === 'hyperv', 'eb-badge--neutral': !point.engine }"
                                          x-text="point.engine || 'unknown'"></span>
                                </td>
                                <td>
                                    <span class="eb-badge"
                                          :class="point.status === 'success' ? 'eb-badge--success' : (point.status === 'warning' ? 'eb-badge--warning' : (point.status === 'metadata_incomplete' ? 'eb-badge--danger' : 'eb-badge--neutral'))"
                                          x-text="point.status || 'unknown'"></span>
                                    <div class="mt-1 text-[11px] text-[var(--eb-warning-text)]" x-show="!point.is_restorable && point.non_restorable_reason" x-text="point.non_restorable_reason"></div>
                                </td>
                                <td>
                                    <div class="text-xs text-[var(--eb-text-secondary)]" x-text="point.source_display_name || point.source_type || '—'"></div>
                                    <div class="text-xs text-[var(--eb-text-muted)]" x-text="point.source_path || ''"></div>
                                </td>
                                <td>
                                    <div class="text-xs text-[var(--eb-text-secondary)]" x-text="point.dest_type || 's3'"></div>
                                    <div class="text-xs text-[var(--eb-text-muted)]" x-text="point.dest_bucket_name || point.dest_prefix || point.dest_local_path || ''"></div>
                                </td>
                                <td x-text="point.finished_at || point.created_at || '—'"></td>
                                <td>
                                    <template x-if="point.hyperv_backup_point_id">
                                        <a :href="'index.php?m=cloudstorage&page=e3backup&view=hyperv_restore&vm_id=' + point.hyperv_vm_id"
                                           class="eb-btn eb-btn-info eb-btn-xs">
                                            Hyper-V Restore
                                        </a>
                                    </template>
                                    <template x-if="!point.hyperv_backup_point_id && String(point.engine || '').toLowerCase() === 'disk_image'">
                                        <span>
                                            <a x-show="point.is_restorable"
                                               :href="'index.php?m=cloudstorage&page=e3backup&view=disk_image_restore&restore_point_id=' + point.id"
                                               class="eb-btn eb-btn-premium eb-btn-xs">
                                                Disk Recovery
                                            </a>
                                            <button x-show="!point.is_restorable"
                                                    type="button"
                                                    class="eb-btn eb-btn-secondary eb-btn-xs disabled"
                                                    :title="point.non_restorable_reason || 'Restore point is not restorable'"
                                                    disabled>
                                                Unavailable
                                            </button>
                                        </span>
                                    </template>
                                    <template x-if="!point.hyperv_backup_point_id && String(point.engine || '').toLowerCase() !== 'disk_image'">
                                        <button @click="openRestoreModal(point)"
                                                class="eb-btn eb-btn-info eb-btn-xs"
                                                :class="point.is_restorable ? '' : 'disabled'"
                                                :title="point.non_restorable_reason || 'Restore point is not restorable'"
                                                :disabled="!point.is_restorable">
                                            <span x-text="point.is_restorable ? 'Restore' : 'Unavailable'"></span>
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-center" x-show="hasMore && !loading" style="display: none;">
                <button type="button" @click="loadRestorePoints(false)" class="eb-btn eb-btn-secondary eb-btn-sm">
                    Load more
                </button>
            </div>
        </div>
    </div>

    <div id="restorePointModal" class="fixed inset-0 z-[2100] hidden">
        <div class="eb-modal-backdrop absolute inset-0" onclick="closeRestorePointModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="eb-modal relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col !p-0 overflow-hidden">
                <div class="eb-modal-header">
                    <div>
                        <h3 class="eb-modal-title">Restore Snapshot</h3>
                        <p class="eb-modal-subtitle">Restore from a saved restore point.</p>
                    </div>
                    <button class="eb-modal-close" onclick="closeRestorePointModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="eb-modal-body flex-1 overflow-y-auto scrollbar-thin-dark">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="eb-badge eb-badge--neutral" id="restorePointStepLabel">Step 1 of 4</span>
                        <span class="eb-type-caption text-[var(--eb-text-secondary)]" id="restorePointStepTitle">Confirm Snapshot</span>
                    </div>

                    <div class="space-y-6">
                        <div class="restore-point-step" data-step="1">
                            <div class="eb-card-raised">
                                <div class="text-sm font-semibold text-[var(--eb-text-primary)]" id="restorePointJobName">Selected restore point</div>
                                <div class="mt-1 text-xs text-[var(--eb-text-muted)]" id="restorePointManifest"></div>
                                <div class="mt-1 text-xs text-[var(--eb-text-muted)]" id="restorePointAgent"></div>
                            </div>
                        </div>

                        <div class="restore-point-step hidden" data-step="2">
                            <label class="eb-field-label">Destination Agent</label>
                            <p class="eb-field-help mb-3">Select the computer where the data should be restored.</p>
                            <div class="space-y-3">
                                <select id="restorePointTargetAgent" class="eb-select">
                                    <option value="">Select an agent</option>
                                </select>
                                <p id="restorePointAgentHint" class="eb-field-help hidden"></p>
                            </div>
                        </div>

                        <div class="restore-point-step hidden" data-step="3">
                            <label class="eb-field-label">Select Items (Optional)</label>
                            <p class="eb-field-help mb-3">Choose specific files or folders to restore. Leave empty to restore the full snapshot.</p>
                            <div class="rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] overflow-hidden">
                                <div class="flex items-center justify-between gap-3 border-b border-[var(--eb-border-subtle)] bg-[var(--eb-bg-chrome)] px-4 py-2">
                                    <div id="restorePointSnapshotBreadcrumbs" class="flex items-center gap-1 text-xs text-[var(--eb-text-secondary)]"></div>
                                    <div id="restorePointSnapshotSelection" class="text-xs text-[var(--eb-text-muted)]">0 selected</div>
                                </div>
                                <div id="restorePointSnapshotStatus" class="px-4 py-2 text-xs text-[var(--eb-text-muted)] hidden"></div>
                                <div class="h-[360px] overflow-y-auto scrollbar-thin-dark">
                                    <div id="restorePointSnapshotList" class="space-y-1 p-2"></div>
                                </div>
                            </div>
                        </div>

                        <div class="restore-point-step hidden" data-step="4">
                            <label class="eb-field-label">Restore Target</label>
                            <div class="space-y-3">
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input id="restorePointTargetPath" type="text" class="eb-input" placeholder="Destination path on agent (e.g., C:\Restores\snapshot)">
                                    <button type="button" onclick="openRestorePointBrowseModal()" class="eb-btn eb-btn-secondary eb-btn-sm">
                                        Browse
                                    </button>
                                </div>
                                <label class="eb-inline-choice">
                                    <input id="restorePointMount" type="checkbox" class="eb-check-input">
                                    <span>Request mount instead of copy</span>
                                </label>
                            </div>
                        </div>

                        <div class="restore-point-step hidden" data-step="5">
                            <div class="eb-card-raised">
                                <p class="eb-card-title mb-2">Review</p>
                                <div id="restorePointReview" class="overflow-auto rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-base)] p-3 text-xs leading-5 text-[var(--eb-text-secondary)] whitespace-pre-wrap max-h-64"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="restorePointPrev()">Back</button>
                    <div class="flex gap-2">
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="closeRestorePointModal()">Cancel</button>
                        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" onclick="restorePointNext()">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="restorePointBrowseModal" class="fixed inset-0 z-[2200] hidden">
        <div class="eb-modal-backdrop absolute inset-0" onclick="closeRestorePointBrowseModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="eb-modal relative z-10 w-full max-w-2xl !p-0 overflow-hidden">
                <div class="eb-modal-header">
                    <div>
                        <h3 class="eb-modal-title">Browse Destination</h3>
                        <p class="eb-modal-subtitle">Select a folder on the destination agent.</p>
                    </div>
                    <button class="eb-modal-close" onclick="closeRestorePointBrowseModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body space-y-3">
                    <div class="flex items-center justify-between gap-3 text-xs text-[var(--eb-text-muted)]">
                        <div id="restorePointBrowsePath">This PC</div>
                        <button type="button" onclick="restorePointBrowseUp()" class="eb-btn eb-btn-secondary eb-btn-xs">Up</button>
                    </div>
                    <div id="restorePointBrowseStatus" class="text-xs text-[var(--eb-text-muted)] hidden"></div>
                    <div class="rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] max-h-72 overflow-auto">
                        <div id="restorePointBrowseList" class="divide-y divide-[var(--eb-border-subtle)]"></div>
                    </div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" onclick="closeRestorePointBrowseModal()" class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</button>
                    <button type="button" onclick="applyRestorePointBrowseSelection()" class="eb-btn eb-btn-primary eb-btn-sm">Use this folder</button>
                </div>
            </div>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='restores'
    ebE3Title='Restore Points'
    ebE3Description='Restore from snapshots across all agents, even after jobs are deleted.'
    ebE3Content=$ebE3Content
}

{literal}
<script>
function restoresApp() {
    return {
        restorePoints: [],
        loading: true,
        tenantFilter: '',
        agentFilter: '',
        searchQuery: '',
        dateFrom: '',
        dateTo: '',
        limit: 200,
        offset: 0,
        hasMore: false,
        requireFilters: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        tenants: {/literal}{if $tenants}{$tenants|json_encode nofilter}{else}[]{/if}{literal},
        agents: {/literal}{if $agents}{$agents|json_encode nofilter}{else}[]{/if}{literal},
        tenantSearch: '',
        agentSearch: '',
        searchTimer: null,
        dateTimer: null,

        get hasAnyFilter() {
            return !!(this.tenantFilter || this.agentFilter || this.searchQuery || this.dateFrom || this.dateTo);
        },

        get needsFilter() {
            return this.requireFilters && !this.hasAnyFilter;
        },

        get filteredTenants() {
            const term = (this.tenantSearch || '').toLowerCase().trim();
            if (!term) return this.tenants || [];
            return (this.tenants || []).filter(t => String(t.name || '').toLowerCase().includes(term));
        },

        get filteredAgents() {
            let list = Array.isArray(this.agents) ? this.agents : [];
            {/literal}{if $isMspClient}{literal}
            if (this.tenantFilter) {
                if (this.tenantFilter === 'direct') {
                    list = list.filter(a => !a.tenant_id);
                } else {
                    list = list.filter(a => String(a.tenant_id) === String(this.tenantFilter));
                }
            }
            {/literal}{/if}{literal}
            const term = (this.agentSearch || '').toLowerCase().trim();
            if (!term) return list;
            return list.filter(a => {
                const name = String(a.hostname || '').toLowerCase();
                return name.includes(term) || String(a.agent_uuid || '').includes(term);
            });
        },

        init() {
            try {
                const params = new URLSearchParams(window.location.search);
                const tenant = params.get('tenant_id');
                const agent = params.get('agent_uuid');
                const fromDate = params.get('from_date');
                const toDate = params.get('to_date');
                if (tenant !== null) this.tenantFilter = tenant;
                if (agent !== null) this.agentFilter = agent;
                if (fromDate) this.dateFrom = fromDate;
                if (toDate) this.dateTo = toDate;
            } catch (e) {}
            this.$watch('searchQuery', () => {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.loadRestorePoints(true), 300);
            });
            this.loadRestorePoints(true);
        },

        tenantLabel() {
            if (!this.tenantFilter) return 'All Tenants';
            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
            const match = (this.tenants || []).find(t => String(t.public_id || t.id) === String(this.tenantFilter));
            return match ? match.name : `Tenant ${this.tenantFilter}`;
        },

        agentLabel() {
            if (!this.agentFilter) return 'All Agents';
            const match = (this.agents || []).find(a => String(a.agent_uuid || '') === String(this.agentFilter));
            if (match) return match.hostname || match.device_name || match.agent_uuid;
            return this.agentFilter;
        },

        selectTenant(value) {
            this.tenantFilter = value;
            {/literal}{if $isMspClient}{literal}
            if (this.agentFilter) {
                const stillVisible = this.filteredAgents.some(a => String(a.agent_uuid || '') === String(this.agentFilter));
                if (!stillVisible) this.agentFilter = '';
            }
            {/literal}{/if}{literal}
            this.loadRestorePoints(true);
        },

        selectAgent(value) {
            this.agentFilter = value;
            this.loadRestorePoints(true);
        },

        setQuickRange(days) {
            const to = new Date();
            const from = new Date();
            from.setDate(to.getDate() - (days - 1));
            this.dateFrom = this.formatDate(from);
            this.dateTo = this.formatDate(to);
            this.loadRestorePoints(true);
        },

        clearDateRange() {
            this.dateFrom = '';
            this.dateTo = '';
            this.loadRestorePoints(true);
        },

        onDateChange() {
            clearTimeout(this.dateTimer);
            this.dateTimer = setTimeout(() => this.loadRestorePoints(true), 200);
        },

        formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        async loadRestorePoints(reset = true) {
            if (this.needsFilter) {
                this.restorePoints = [];
                this.loading = false;
                this.hasMore = false;
                this.offset = 0;
                return;
            }
            this.loading = true;
            if (reset) {
                this.restorePoints = [];
                this.offset = 0;
            }
            try {
                const params = new URLSearchParams();
                if (this.tenantFilter) params.set('tenant_id', this.tenantFilter);
                if (this.agentFilter) params.set('agent_uuid', this.agentFilter);
                if (this.searchQuery) params.set('search', this.searchQuery);
                if (this.dateFrom) params.set('from_date', this.dateFrom);
                if (this.dateTo) params.set('to_date', this.dateTo);
                params.set('limit', String(this.limit));
                params.set('offset', String(this.offset));
                let url = 'modules/addons/cloudstorage/api/e3backup_restore_points_list.php';
                const qs = params.toString();
                if (qs) url += '?' + qs;

                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    const rows = data.restore_points || [];
                    this.restorePoints = reset ? rows : [...this.restorePoints, ...rows];
                    this.hasMore = !!data.has_more;
                    this.offset = data.next_offset !== null && data.next_offset !== undefined ? data.next_offset : 0;
                } else {
                    console.error(data.message);
                }
            } catch (e) {
                console.error('Failed to load restore points:', e);
            }
            this.loading = false;
        },

        openRestoreModal(point) {
            if (point && point.is_restorable === false) {
                if (point.non_restorable_reason) {
                    if (window.toast) toast.error(point.non_restorable_reason);
                    else alert(point.non_restorable_reason);
                }
                return;
            }
            window.openRestorePointModal(point);
        }
    };
}

window.restorePointAgents = {/literal}{$agents|json_encode nofilter}{literal};

window.restorePointState = {
    point: null,
    step: 1,
    totalSteps: 4,
    stepSequence: [1, 2, 4, 5],
    targetPath: '',
    mount: false,
    targetAgentUuid: '',
    availableAgents: [],
    agentRequired: false,
    allowSnapshotBrowse: false,
    selectedSnapshotPaths: [],
    snapshotPath: '',
    snapshotParent: '',
    snapshotEntries: [],
    snapshotLoading: false,
    snapshotError: '',
    snapshotAgentUuid: ''
};

function normalizeTenantId(value) {
    if (value === null || value === undefined || value === '') return null;
    const normalized = String(value).trim();
    return normalized === '' ? null : normalized;
}

function getCompatibleAgents(point) {
    const agents = Array.isArray(window.restorePointAgents) ? window.restorePointAgents : [];
    if (!point) return agents;
    const pointTenantId = normalizeTenantId(point.tenant_id);
    if (point.tenant_deleted) {
        return agents.filter((agent) => String(agent.agent_uuid || '') === String(point.agent_uuid || ''));
    }
    return agents.filter((agent) => {
        const agentTenantId = normalizeTenantId(agent.tenant_id);
        if (pointTenantId === null) {
            return agentTenantId === null;
        }
        return agentTenantId === pointTenantId;
    });
}

function hydrateRestorePointAgents(point) {
    const st = window.restorePointState;
    if (!point) return;
    st.availableAgents = getCompatibleAgents(point);
    const originalAvailable = st.availableAgents.some((agent) => String(agent.agent_uuid || '') === String(point.agent_uuid || ''));
    st.agentRequired = !originalAvailable;
    if (!st.targetAgentUuid) {
        st.targetAgentUuid = originalAvailable ? String(point.agent_uuid || '') : '';
    }
    const select = document.getElementById('restorePointTargetAgent');
    if (select) {
        select.innerHTML = '<option value="">Select an agent</option>';
        st.availableAgents.forEach((agent) => {
            const opt = document.createElement('option');
            opt.value = String(agent.agent_uuid || '');
            opt.textContent = agent.hostname || agent.device_name || (agent.agent_uuid || 'Unknown agent');
            select.appendChild(opt);
        });
        select.value = st.targetAgentUuid || '';
        select.onchange = () => {
            st.targetAgentUuid = select.value || '';
        };
    }
    const hint = document.getElementById('restorePointAgentHint');
    if (hint) {
        if (st.agentRequired) {
            hint.textContent = 'Original agent is unavailable. Select a destination agent.';
            hint.classList.remove('hidden');
        } else {
            hint.textContent = '';
            hint.classList.add('hidden');
        }
    }
}

function getRestorePointAgentLabel(agentUuid) {
    if (!agentUuid) return '';
    const agents = Array.isArray(window.restorePointAgents) ? window.restorePointAgents : [];
    const found = agents.find((agent) => String(agent.agent_uuid || '') === String(agentUuid));
    if (found) {
        return found.hostname || found.device_name || found.agent_uuid || '';
    }
    return agentUuid;
}

function getRestorePointStepId() {
    const st = window.restorePointState;
    if (!Array.isArray(st.stepSequence) || st.stepSequence.length === 0) {
        return st.step;
    }
    const idx = Math.max(0, st.step - 1);
    return st.stepSequence[idx] || st.step;
}

function initSnapshotBrowser() {
    const st = window.restorePointState;
    if (!st.allowSnapshotBrowse) {
        setSnapshotStatus('Snapshot browsing is not available for this restore point.', 'info');
        renderSnapshotList();
        return;
    }
    if (!st.targetAgentUuid) {
        setSnapshotStatus('Select a destination agent to browse the snapshot.', 'info');
        renderSnapshotList();
        return;
    }
    if (st.snapshotAgentUuid !== st.targetAgentUuid) {
        st.snapshotAgentUuid = st.targetAgentUuid;
        st.snapshotPath = '';
        st.snapshotParent = '';
        st.snapshotEntries = [];
        st.snapshotError = '';
    }
    if (!st.snapshotLoading && st.snapshotEntries.length === 0) {
        loadSnapshotEntries('');
        return;
    }
    renderSnapshotList();
}

function setSnapshotStatus(message, kind) {
    const st = window.restorePointState;
    st.snapshotError = kind === 'error' ? message : '';
    const statusEl = document.getElementById('restorePointSnapshotStatus');
    if (!statusEl) return;
    if (!message) {
        statusEl.textContent = '';
        statusEl.classList.add('hidden');
        return;
    }
    statusEl.textContent = message;
    statusEl.classList.remove('hidden');
    statusEl.classList.toggle('text-[var(--eb-danger-text)]', kind === 'error');
    statusEl.classList.toggle('text-[var(--eb-text-muted)]', kind !== 'error');
}

function loadSnapshotEntries(path) {
    const st = window.restorePointState;
    if (!st.point || !st.targetAgentUuid) {
        setSnapshotStatus('Select a destination agent to browse the snapshot.', 'info');
        return;
    }
    st.snapshotLoading = true;
    setSnapshotStatus('Loading snapshot entries...', 'info');
    renderSnapshotList();
    const qs = new URLSearchParams();
    qs.set('agent_uuid', st.targetAgentUuid);
    qs.set('restore_point_id', String(st.point.id));
    if (path) qs.set('path', path);
    const url = `modules/addons/cloudstorage/api/agent_browse_snapshot.php?${qs.toString()}`;
    fetch(url)
        .then(res => res.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                setSnapshotStatus(`Browse failed (non-JSON response): ${text.slice(0, 120)}...`, 'error');
                return;
            }
            if (data.status === 'success') {
                const res = data.data || {};
                if (res.error) {
                    st.snapshotEntries = [];
                    st.snapshotPath = res.path || '';
                    st.snapshotParent = res.parent || '';
                    setSnapshotStatus(res.error, 'error');
                } else {
                    st.snapshotPath = res.path || '';
                    st.snapshotParent = res.parent || '';
                    st.snapshotEntries = Array.isArray(res.entries) ? res.entries : [];
                    setSnapshotStatus('', 'info');
                }
            } else {
                setSnapshotStatus(data.message || 'Failed to load snapshot entries', 'error');
            }
        })
        .catch(err => {
            setSnapshotStatus(err?.message || 'Network error', 'error');
        })
        .finally(() => {
            st.snapshotLoading = false;
            renderSnapshotList();
        });
}

function renderSnapshotList() {
    const st = window.restorePointState;
    const listEl = document.getElementById('restorePointSnapshotList');
    if (!listEl) return;
    listEl.innerHTML = '';
    renderSnapshotBreadcrumbs();

    updateSnapshotSelectionCount();

    if (st.snapshotLoading) {
        return;
    }

    if (!st.allowSnapshotBrowse || !st.targetAgentUuid) {
        return;
    }

    const statusEl = document.getElementById('restorePointSnapshotStatus');
    if (statusEl && statusEl.textContent) {
        // Status is handled separately (no list rendering when showing error)
    }

    if (!Array.isArray(st.snapshotEntries) || st.snapshotEntries.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'eb-app-empty !py-6';
        empty.textContent = st.snapshotError ? 'Unable to browse snapshot.' : 'No entries found.';
        listEl.appendChild(empty);
        return;
    }

    if (st.snapshotPath) {
        const upRow = document.createElement('button');
        upRow.type = 'button';
        upRow.className = 'flex w-full items-center gap-3 rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-input)] px-3 py-2 text-left text-[var(--eb-text-secondary)] transition hover:bg-[var(--eb-bg-hover)]';
        upRow.addEventListener('click', () => loadSnapshotEntries(st.snapshotParent || ''));
        upRow.innerHTML = `
            <div class="flex h-8 w-8 items-center justify-center rounded-[var(--eb-radius-md)] bg-[var(--eb-bg-chrome)]">
                <svg class="h-4 w-4 text-[var(--eb-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
            </div>
            <span class="text-sm text-[var(--eb-text-secondary)]">..</span>
        `;
        listEl.appendChild(upRow);
    }

    st.snapshotEntries.forEach((entry) => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 rounded-[var(--eb-radius-md)] border border-transparent px-3 py-2 transition hover:bg-[var(--eb-bg-hover)]';
        if (isSnapshotSelected(entry.path)) {
            row.classList.add('border-[var(--eb-info-border)]', 'bg-[var(--eb-info-bg)]');
        }

        const checkboxWrap = document.createElement('label');
        checkboxWrap.className = 'flex h-5 w-5 cursor-pointer items-center justify-center rounded-[var(--eb-radius-sm)] border';
        checkboxWrap.className += isSnapshotSelected(entry.path)
            ? ' border-[var(--eb-info-border)] bg-[var(--eb-info-icon)]'
            : ' border-[var(--eb-border-default)] bg-[var(--eb-bg-input)]';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'hidden';
        checkbox.checked = isSnapshotSelected(entry.path);
        checkbox.addEventListener('change', () => toggleSnapshotSelection(entry.path));
        checkboxWrap.appendChild(checkbox);
        checkboxWrap.insertAdjacentHTML('beforeend', `
            <svg class="h-3 w-3 text-white ${isSnapshotSelected(entry.path) ? '' : 'hidden'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
        `);
        row.appendChild(checkboxWrap);

        const nameBtn = document.createElement('button');
        nameBtn.type = 'button';
        nameBtn.className = 'flex flex-1 items-center gap-3 text-left cursor-pointer';
        if (entry.is_dir) {
            nameBtn.addEventListener('click', () => loadSnapshotEntries(entry.path));
        }
        const icon = entry.is_dir
            ? `<svg class="h-5 w-5 text-[var(--eb-warning-icon)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>`
            : `<svg class="h-5 w-5 text-[var(--eb-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>`;
        nameBtn.innerHTML = `
            <div class="flex h-8 w-8 items-center justify-center rounded-[var(--eb-radius-md)] bg-[var(--eb-bg-chrome)]">
                ${icon}
            </div>
            <div class="flex-1 min-w-0">
                <p class="truncate text-sm text-[var(--eb-text-primary)]">${entry.name || entry.path || 'Unnamed'}</p>
                <p class="text-xs text-[var(--eb-text-muted)]">${entry.is_dir ? 'Folder' : formatSnapshotBytes(entry.size || 0)}</p>
            </div>
            ${entry.is_dir ? '<svg class="h-4 w-4 text-[var(--eb-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>' : ''}
        `;
        row.appendChild(nameBtn);

        listEl.appendChild(row);
    });
}

function renderSnapshotBreadcrumbs() {
    const st = window.restorePointState;
    const crumbsEl = document.getElementById('restorePointSnapshotBreadcrumbs');
    if (!crumbsEl) return;
    crumbsEl.innerHTML = '';

    const rootBtn = document.createElement('button');
    rootBtn.type = 'button';
    rootBtn.className = 'rounded-[var(--eb-radius-sm)] px-2 py-1 transition hover:bg-[var(--eb-bg-hover)]';
    rootBtn.textContent = 'Snapshot root';
    rootBtn.addEventListener('click', () => loadSnapshotEntries(''));
    crumbsEl.appendChild(rootBtn);

    const segments = getSnapshotPathSegments(st.snapshotPath || '');
    segments.forEach((segment) => {
        const sep = document.createElement('span');
        sep.className = 'text-[var(--eb-text-muted)]';
        sep.textContent = '/';
        crumbsEl.appendChild(sep);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'truncate rounded-[var(--eb-radius-sm)] px-2 py-1 transition hover:bg-[var(--eb-bg-hover)] max-w-[140px]';
        btn.textContent = segment.name;
        btn.addEventListener('click', () => loadSnapshotEntries(segment.path));
        crumbsEl.appendChild(btn);
    });
}

function getSnapshotPathSegments(rawPath) {
    if (!rawPath) return [];
    const parts = String(rawPath).split('/').filter(Boolean);
    let acc = '';
    return parts.map((part) => {
        acc = acc ? `${acc}/${part}` : part;
        return { name: part, path: acc };
    });
}

function isSnapshotSelected(path) {
    const st = window.restorePointState;
    return st.selectedSnapshotPaths.includes(path);
}

function toggleSnapshotSelection(path) {
    const st = window.restorePointState;
    if (!path) return;
    if (isSnapshotSelected(path)) {
        st.selectedSnapshotPaths = st.selectedSnapshotPaths.filter((p) => p !== path);
    } else {
        st.selectedSnapshotPaths = [...st.selectedSnapshotPaths, path];
    }
    updateSnapshotSelectionCount();
    renderSnapshotList();
}

function updateSnapshotSelectionCount() {
    const st = window.restorePointState;
    const el = document.getElementById('restorePointSnapshotSelection');
    if (el) {
        el.textContent = `${st.selectedSnapshotPaths.length} selected`;
    }
}

function formatSnapshotBytes(n) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let val = Number(n);
    let idx = 0;
    while (val >= 1024 && idx < units.length - 1) {
        val /= 1024;
        idx += 1;
    }
    const precision = idx === 0 ? 0 : 1;
    return `${val.toFixed(precision)} ${units[idx]}`;
}

window.restorePointBrowseState = {
    path: '',
    parent: '',
    entries: [],
    loading: false,
    error: '',
    agentUuid: ''
};

function openRestorePointBrowseModal() {
    const st = window.restorePointState;
    if (!st.targetAgentUuid) {
        if (window.toast) toast.error('Select a destination agent first');
        else alert('Select a destination agent first');
        return;
    }
    const bs = window.restorePointBrowseState;
    if (bs.agentUuid !== st.targetAgentUuid) {
        bs.path = '';
        bs.parent = '';
        bs.entries = [];
    }
    bs.agentUuid = st.targetAgentUuid;
    const modal = document.getElementById('restorePointBrowseModal');
    if (modal) modal.classList.remove('hidden');
    loadRestorePointBrowsePath(bs.path || '');
}

function closeRestorePointBrowseModal() {
    const modal = document.getElementById('restorePointBrowseModal');
    if (modal) modal.classList.add('hidden');
}

function setRestorePointBrowseStatus(message, kind) {
    const bs = window.restorePointBrowseState;
    bs.error = kind === 'error' ? message : '';
    const statusEl = document.getElementById('restorePointBrowseStatus');
    if (!statusEl) return;
    if (!message) {
        statusEl.textContent = '';
        statusEl.classList.add('hidden');
        return;
    }
    statusEl.textContent = message;
    statusEl.classList.remove('hidden');
    statusEl.classList.toggle('text-[var(--eb-danger-text)]', kind === 'error');
    statusEl.classList.toggle('text-[var(--eb-text-muted)]', kind !== 'error');
}

function loadRestorePointBrowsePath(path) {
    const bs = window.restorePointBrowseState;
    if (!bs.agentUuid) {
        setRestorePointBrowseStatus('Select a destination agent first.', 'error');
        return;
    }
    bs.loading = true;
    setRestorePointBrowseStatus('Loading folders...', 'info');
    renderRestorePointBrowseList();
    const url = `modules/addons/cloudstorage/api/agent_browse_filesystem.php?agent_uuid=${encodeURIComponent(bs.agentUuid)}&path=${encodeURIComponent(path || '')}`;
    fetch(url)
        .then(res => res.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                setRestorePointBrowseStatus(`Browse failed (non-JSON response): ${text.slice(0, 120)}...`, 'error');
                return;
            }
            if (data.status === 'success') {
                const res = data.data || {};
                if (res.error) {
                    bs.entries = [];
                    bs.path = res.path || '';
                    bs.parent = res.parent || '';
                    setRestorePointBrowseStatus(res.error, 'error');
                } else {
                    bs.path = res.path || '';
                    bs.parent = res.parent || '';
                    bs.entries = Array.isArray(res.entries) ? res.entries : [];
                    setRestorePointBrowseStatus('', 'info');
                }
            } else {
                setRestorePointBrowseStatus(data.message || 'Failed to load directory', 'error');
            }
        })
        .catch(err => {
            setRestorePointBrowseStatus(err?.message || 'Network error', 'error');
        })
        .finally(() => {
            bs.loading = false;
            renderRestorePointBrowseList();
        });
}

function renderRestorePointBrowseList() {
    const bs = window.restorePointBrowseState;
    const listEl = document.getElementById('restorePointBrowseList');
    if (!listEl) return;
    listEl.innerHTML = '';

    const pathEl = document.getElementById('restorePointBrowsePath');
    if (pathEl) {
        pathEl.textContent = bs.path || 'This PC';
    }

    if (bs.loading) {
        return;
    }

    const entries = Array.isArray(bs.entries) ? bs.entries.filter(e => e && e.is_dir) : [];
    if (entries.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'px-4 py-3 text-xs text-[var(--eb-text-muted)]';
        empty.textContent = bs.error ? 'Unable to browse folders.' : 'No folders found.';
        listEl.appendChild(empty);
        return;
    }

    entries.forEach((entry) => {
        const row = document.createElement('div');
        row.className = 'cursor-pointer px-4 py-2 text-sm text-[var(--eb-text-secondary)] transition hover:bg-[var(--eb-bg-hover)]';
        row.textContent = entry.name || entry.path || 'Unnamed';
        row.addEventListener('click', () => loadRestorePointBrowsePath(entry.path || ''));
        listEl.appendChild(row);
    });
}

function restorePointBrowseUp() {
    const bs = window.restorePointBrowseState;
    if (bs.parent !== undefined) {
        loadRestorePointBrowsePath(bs.parent || '');
    }
}

function applyRestorePointBrowseSelection() {
    const bs = window.restorePointBrowseState;
    const input = document.getElementById('restorePointTargetPath');
    if (input) {
        input.value = bs.path || '';
    }
    closeRestorePointBrowseModal();
}

function openRestorePointModal(point) {
    window.restorePointState.point = point;
    window.restorePointState.step = 1;
    window.restorePointState.targetPath = '';
    window.restorePointState.mount = false;
    window.restorePointState.targetAgentUuid = '';
    window.restorePointState.availableAgents = [];
    window.restorePointState.agentRequired = false;
    window.restorePointState.allowSnapshotBrowse = !!point && String(point.engine || '').toLowerCase() === 'kopia';
    window.restorePointState.stepSequence = window.restorePointState.allowSnapshotBrowse ? [1, 2, 3, 4, 5] : [1, 2, 4, 5];
    window.restorePointState.totalSteps = window.restorePointState.stepSequence.length;
    window.restorePointState.selectedSnapshotPaths = [];
    window.restorePointState.snapshotPath = '';
    window.restorePointState.snapshotParent = '';
    window.restorePointState.snapshotEntries = [];
    window.restorePointState.snapshotLoading = false;
    window.restorePointState.snapshotError = '';
    window.restorePointState.snapshotAgentUuid = '';
    hydrateRestorePointAgents(point);
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
    const stepId = getRestorePointStepId();
    if (stepId === 2) {
        const agentSel = document.getElementById('restorePointTargetAgent');
        st.targetAgentUuid = agentSel ? (agentSel.value || '') : '';
        if (!st.targetAgentUuid) {
            if (window.toast) toast.error('Select a destination agent');
            else alert('Select a destination agent');
            return;
        }
    }
    if (stepId === 4) {
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
        if (getRestorePointStepId() === 5) {
            buildRestorePointReview();
        }
        updateRestorePointView();
        return;
    }
    submitRestorePoint();
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
    const stepId = getRestorePointStepId();
    document.querySelectorAll('#restorePointModal .restore-point-step').forEach((el) => {
        const s = parseInt(el.getAttribute('data-step'), 10);
        if (s === stepId) el.classList.remove('hidden'); else el.classList.add('hidden');
    });
    const label = document.getElementById('restorePointStepLabel');
    const title = document.getElementById('restorePointStepTitle');
    if (label) label.textContent = `Step ${st.step} of ${st.totalSteps}`;
    if (title) {
        title.textContent = stepId === 1
            ? 'Confirm Snapshot'
            : (stepId === 2 ? 'Destination Agent' : (stepId === 3 ? 'Select Items' : (stepId === 4 ? 'Restore Target' : 'Review')));
    }
    if (stepId === 1 && st.point) {
        const jobName = document.getElementById('restorePointJobName');
        const manifest = document.getElementById('restorePointManifest');
        const agent = document.getElementById('restorePointAgent');
        if (jobName) jobName.textContent = st.point.job_name || 'Unnamed job';
        if (manifest) manifest.textContent = `Manifest: ${st.point.manifest_id || '—'}`;
        if (agent) agent.textContent = `Agent: ${st.point.agent_hostname || st.point.agent_uuid || '—'}`;
    }
    if (stepId === 2) {
        hydrateRestorePointAgents(st.point);
        const agentSelect = document.getElementById('restorePointTargetAgent');
        if (agentSelect) {
            agentSelect.value = st.targetAgentUuid || '';
        }
    }
    if (stepId === 3) {
        initSnapshotBrowser();
    }
}

function buildRestorePointReview() {
    const st = window.restorePointState;
    const agentLabel = getRestorePointAgentLabel(st.targetAgentUuid);
    const review = {
        restore_point_id: st.point?.id,
        job_name: st.point?.job_name,
        manifest_id: st.point?.manifest_id,
        target_agent_uuid: st.targetAgentUuid || '',
        target_agent: agentLabel || '',
        target_path: st.targetPath,
        mount: st.mount,
    };
    if (Array.isArray(st.selectedSnapshotPaths) && st.selectedSnapshotPaths.length > 0) {
        review.selected_paths = st.selectedSnapshotPaths;
    }
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
    if (st.targetAgentUuid) {
        data.set('target_agent_uuid', String(st.targetAgentUuid));
    }
    data.set('target_path', st.targetPath || '');
    data.set('mount', st.mount ? 'true' : 'false');
    if (Array.isArray(st.selectedSnapshotPaths) && st.selectedSnapshotPaths.length > 0) {
        data.set('selected_paths', JSON.stringify(st.selectedSnapshotPaths));
    }

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
            const restoreRunParam = resp.restore_run_id;
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
