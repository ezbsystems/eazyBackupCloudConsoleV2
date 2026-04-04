{capture assign=ebE3DiskImageRestoreBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Disk Image Recovery</span>
    </div>
{/capture}

{capture assign=ebE3Content}
<div x-data="diskImageRestorePage()" x-init="init()" class="space-y-6">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3DiskImageRestoreBreadcrumb
        ebPageTitle='Disk Image Recovery'
        ebPageDescription='Generate a recovery token and preview the disk layout before a bare-metal restore.'
    }

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="eb-subpanel">
                <div class="eb-table-toolbar">
                    {if $isMspClient}
                        <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                            <button type="button"
                                    @click="isOpen = !isOpen"
                                    class="eb-btn eb-btn-secondary eb-btn-sm min-w-[14rem] justify-between">
                                <span class="truncate" x-text="'Tenant: ' + tenantLabel()"></span>
                                <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="isOpen"
                                 x-transition
                                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-72 overflow-hidden"
                                 style="display: none;">
                                <div class="eb-menu-label">Select tenant</div>
                                <div class="max-h-72 overflow-auto p-1">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="tenantFilter === '' ? 'is-active' : ''"
                                            @click="tenantFilter=''; isOpen=false; reload()">
                                        All tenants
                                    </button>
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="tenantFilter === 'direct' ? 'is-active' : ''"
                                            @click="tenantFilter='direct'; isOpen=false; reload()">
                                        Direct clients
                                    </button>
                                    <template x-for="tenant in tenants" :key="tenant.id || tenant.public_id">
                                        <button type="button"
                                                class="eb-menu-option"
                                                :class="String(tenantFilter) === String(tenant.id || tenant.public_id || '') ? 'is-active' : ''"
                                                @click="tenantFilter = String(tenant.id || tenant.public_id || ''); isOpen=false; reload()">
                                            <span x-text="tenant.name || ('Tenant ' + (tenant.id || tenant.public_id || ''))"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    {/if}
                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-btn eb-btn-secondary eb-btn-sm min-w-[14rem] justify-between">
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
                            <div class="max-h-72 overflow-auto p-1">
                                <button type="button"
                                        class="eb-menu-option"
                                        :class="agentFilter === '' ? 'is-active' : ''"
                                        @click="agentFilter=''; isOpen=false; reload()">
                                    All agents
                                </button>
                                <template x-for="agent in agents" :key="agent.agent_uuid || agent.id">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="String(agentFilter) === String(agent.agent_uuid || '') ? 'is-active' : ''"
                                            @click="agentFilter = String(agent.agent_uuid || ''); isOpen=false; reload()">
                                        <span x-text="agent.hostname || agent.device_name || agent.agent_uuid || 'Unknown agent'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <input type="text"
                           class="eb-toolbar-search w-full md:flex-1"
                           placeholder="Search restore points"
                           x-model="searchQuery"
                           @input.debounce.400ms="reload()" />
                </div>
            </div>

            <div class="eb-table-shell">
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>Restore Point</th>
                            <th>Agent</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="point in restorePoints" :key="point.id">
                            <tr>
                                <td class="eb-table-primary">
                                    <div class="font-medium text-[var(--eb-text-primary)]" x-text="point.job_name || ('Restore Point #' + point.id)"></div>
                                    <div class="text-xs text-[var(--eb-text-muted)]" x-text="point.created_at"></div>
                                </td>
                                <td x-text="point.agent_hostname || 'Unknown'"></td>
                                <td x-text="formatBytes(point.disk_total_bytes || 0)"></td>
                                <td>
                                    <span class="eb-badge eb-badge--neutral" x-text="point.status || 'unknown'"></span>
                                    <div class="mt-1 text-[11px] text-[var(--eb-warning-text)]" x-show="!point.is_restorable && point.non_restorable_reason" x-text="point.non_restorable_reason"></div>
                                </td>
                                <td class="text-right">
                                    <button class="eb-btn eb-btn-info eb-btn-xs"
                                            :class="point.is_restorable ? '' : 'disabled'"
                                            @click="selectPoint(point)"
                                            :disabled="!point.is_restorable">
                                        <span x-text="point.is_restorable ? 'Select' : 'Unavailable'"></span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="restorePoints.length === 0">
                            <td colspan="5">
                                <div class="eb-app-empty !py-8">
                                    <div class="eb-app-empty-title">No disk image restore points found</div>
                                    <p class="eb-app-empty-copy">Adjust your filters or wait for a new disk image backup to complete.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="eb-card">
                <div class="eb-card-header">
                    <div>
                        <div class="eb-card-title">Selected Restore Point</div>
                        <p class="eb-card-subtitle">Preview the selected image before generating a recovery token.</p>
                    </div>
                </div>
                <template x-if="!selectedPoint">
                    <div class="eb-app-empty !py-8">
                        <div class="eb-app-empty-title">No restore point selected</div>
                        <p class="eb-app-empty-copy">Select a disk image restore point to view layout and generate a recovery token.</p>
                    </div>
                </template>
                <template x-if="selectedPoint">
                    <div class="eb-kv-list">
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Job</span>
                            <span class="eb-kv-value" x-text="selectedPoint.job_name || ('Restore Point #' + selectedPoint.id)"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Manifest</span>
                            <span class="eb-kv-value eb-type-mono" x-text="selectedPoint.manifest_id"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Disk size</span>
                            <span class="eb-kv-value" x-text="formatBytes(selectedPoint.disk_total_bytes || 0)"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Used</span>
                            <span class="eb-kv-value" x-text="formatBytes(selectedPoint.disk_used_bytes || 0)"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Boot</span>
                            <span class="eb-kv-value" x-text="selectedPoint.disk_boot_mode || 'unknown'"></span>
                        </div>
                        <div class="eb-kv-row">
                            <span class="eb-kv-label">Partition</span>
                            <span class="eb-kv-value" x-text="selectedPoint.disk_partition_style || 'unknown'"></span>
                        </div>
                        <div class="text-xs text-[var(--eb-warning-text)]" x-show="!selectedPoint.is_restorable && selectedPoint.non_restorable_reason" x-text="selectedPoint.non_restorable_reason"></div>
                    </div>
                </template>
            </div>

            <div class="eb-card" x-show="selectedPoint">
                <div class="eb-card-header">
                    <div>
                        <div class="eb-card-title">Disk Layout</div>
                        <p class="eb-card-subtitle">Partition metadata reported by the selected restore point.</p>
                    </div>
                </div>
                <template x-if="layoutPartitions.length === 0">
                    <div class="eb-app-empty !py-8">
                        <div class="eb-app-empty-title">No partition metadata available</div>
                        <p class="eb-app-empty-copy">This restore point does not include a browsable partition layout.</p>
                    </div>
                </template>
                <template x-for="part in layoutPartitions" :key="part.index">
                    <div class="eb-kv-row rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] px-3 py-2 text-sm">
                        <span class="eb-kv-value" x-text="part.name || part.path"></span>
                        <span class="eb-kv-label" x-text="formatBytes(part.size_bytes || 0)"></span>
                    </div>
                </template>
            </div>

            <div class="eb-card" x-show="selectedPoint">
                <div class="eb-card-header">
                    <div>
                        <div class="eb-card-title">Recovery Token</div>
                        <p class="eb-card-subtitle">Use this code on the recovery media to start a bare-metal restore.</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <input type="text"
                           class="eb-input eb-type-mono"
                           placeholder="Token will appear here"
                           x-model="generatedToken"
                           readonly>
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="eb-btn eb-btn-success eb-btn-sm"
                                @click="generateToken()"
                                :disabled="loadingToken || !selectedPoint || !selectedPoint.is_restorable">
                            Generate Token
                        </button>
                        <span class="eb-type-caption" x-text="tokenExpiry"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='disk_restore'
    ebE3Title='Disk Image Recovery'
    ebE3Description='Generate a recovery token and preview the disk layout before a bare-metal restore.'
    ebE3Content=$ebE3Content
}

{literal}
<script>
function diskImageRestorePage() {
    return {
        restorePoints: [],
        selectedPoint: null,
        layoutPartitions: [],
        tenants: {/literal}{if $tenants}{$tenants|json_encode nofilter}{else}[]{/if}{literal},
        agents: {/literal}{if $agents}{$agents|json_encode nofilter}{else}[]{/if}{literal},
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
        tenantLabel() {
            if (!this.tenantFilter) return 'All tenants';
            if (this.tenantFilter === 'direct') return 'Direct clients';
            const match = (this.tenants || []).find((tenant) => String(tenant.id || tenant.public_id || '') === String(this.tenantFilter));
            return match ? (match.name || `Tenant ${this.tenantFilter}`) : this.tenantFilter;
        },
        agentLabel() {
            if (!this.agentFilter) return 'All agents';
            const match = (this.agents || []).find((agent) => String(agent.agent_uuid || '') === String(this.agentFilter));
            if (!match) return this.agentFilter;
            return match.hostname || match.device_name || match.agent_uuid || 'Unknown agent';
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
