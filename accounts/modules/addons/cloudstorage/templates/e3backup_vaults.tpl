{capture assign=ebE3Content}
<div x-data="e3VaultsApp()" x-init="init()" class="eb-section-stack"
     @keydown.escape.window="earlyDeleteModalOpen && !earlyDeleteInProgress && closeEarlyDeleteModal()">
    {include file="$template/includes/ui/page-header.tpl"
        ebPageTitle='Vaults'
        ebPageDescription='All storage destinations across your backup users — Microsoft 365 vaults and other destination buckets.'
    }

    {if $isMspClient}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative shrink-0" @click.away="tenantOpen = false">
            <button type="button" class="eb-app-toolbar-button" @click="tenantOpen = !tenantOpen">
                <span class="text-[var(--eb-text-muted)]">Tenant:</span>
                <span class="font-medium truncate max-w-[10rem]" x-text="tenantLabel()"></span>
                <svg class="h-4 w-4 opacity-70 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                </svg>
            </button>
            <div x-show="tenantOpen" x-cloak x-transition class="eb-menu absolute left-0 z-10 mt-2 w-72 overflow-hidden" style="display:none;">
                <div class="max-h-72 overflow-y-auto p-1">
                    <button type="button" class="eb-menu-item block w-full px-3 py-2 text-left text-sm" :class="tenantFilter === '' ? 'is-active' : ''" @click="pickTenant('')">All tenants</button>
                    <button type="button" class="eb-menu-item block w-full px-3 py-2 text-left text-sm" :class="tenantFilter === 'direct' ? 'is-active' : ''" @click="pickTenant('direct')">Direct (no tenant)</button>
                    <template x-for="t in tenants" :key="t.public_id || t.id">
                        <button type="button"
                                class="eb-menu-item block w-full px-3 py-2 text-left text-sm truncate"
                                :class="String(tenantFilter) === String(t.public_id || t.id) ? 'is-active' : ''"
                                @click="pickTenant(String(t.public_id || t.id))"
                                x-text="t.name"></button>
                    </template>
                </div>
            </div>
        </div>
    </div>
    {/if}

    <template x-if="loading">
        <div class="eb-app-empty">
            <div class="eb-app-empty-title">Loading vaults…</div>
        </div>
    </template>

    <template x-if="!loading">
        <div>
            <div class="eb-segmented mb-4" role="tablist" aria-label="Vault views">
                <button type="button"
                        role="tab"
                        class="eb-segmented-btn"
                        :class="vaultSubTab === 'active' ? 'is-active' : ''"
                        :aria-selected="vaultSubTab === 'active' ? 'true' : 'false'"
                        @click="selectVaultSubTab('active')">
                    Active vaults (<span x-text="ms365VaultsActive().length + legacyVaults().length"></span>)
                </button>
                <button type="button"
                        role="tab"
                        class="eb-segmented-btn"
                        :class="vaultSubTab === 'recycle' ? 'is-active' : ''"
                        :aria-selected="vaultSubTab === 'recycle' ? 'true' : 'false'"
                        @click="selectVaultSubTab('recycle')">
                    Recycle bin (<span x-text="ms365VaultsRecycle().length"></span>)
                </button>
            </div>

            <p class="eb-type-caption mb-4" x-show="vaultSubTab === 'recycle'" x-cloak>
                Vaults remain recoverable until the grace period ends. Permanent deletion is queued automatically afterward. Requests for early deletion are reviewed by platform administrators.
            </p>

            {include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/e3backup_vaults_table.tpl" ebE3VaultsShowUserCol=true}
        </div>
    </template>

    <div x-show="earlyDeleteModalOpen"
         x-cloak
         class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
         style="display:none;"
         @keydown.escape.window="closeEarlyDeleteModal()">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeEarlyDeleteModal()"></div>
        <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden" @click.stop>
            <div class="eb-modal-header">
                <div>
                    <h3 class="eb-modal-title">Request early deletion?</h3>
                    <p class="eb-modal-subtitle">Platform administrators review early deletion requests. You cannot permanently delete vault data yourself.</p>
                </div>
            </div>
            <div class="eb-modal-body space-y-3">
                <div class="text-sm text-[var(--eb-text-secondary)]">
                    Vault: <span class="font-semibold text-[var(--eb-text-primary)]" x-text="earlyDeleteVault?.name || '—'"></span>
                </div>
                <div>
                    <label class="eb-field-label" for="global-early-delete-reason">Reason (optional)</label>
                    <textarea id="global-early-delete-reason" class="eb-input w-full mt-1 min-h-[80px]" x-model="earlyDeleteReason" placeholder="Why do you need this vault removed before the grace period ends?"></textarea>
                </div>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeEarlyDeleteModal()" :disabled="earlyDeleteInProgress">Cancel</button>
                <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="submitEarlyDeleteRequest()" :disabled="earlyDeleteInProgress">
                    <span x-text="earlyDeleteInProgress ? 'Submitting…' : 'Submit request'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='vaults'
    ebE3Title='Vaults'
    ebE3Description='All storage destinations across your backup users.'
    ebE3Content=$ebE3Content
}

<script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>
<script>
window.__ebE3VaultsTenants = {$tenants|@json_encode nofilter};
</script>
<script>
{literal}
function e3VaultsApp() {
    return {
        loading: true,
        vaultsActive: [],
        vaultsRecycle: [],
        legacyVaultsList: [],
        graceDays: 30,
        tenantFilter: '',
        tenants: window.__ebE3VaultsTenants || [],
        tenantOpen: false,
        vaultSubTab: 'active',
        vaultSearchQuery: '',
        vaultSortKey: 'name',
        vaultSortDirection: 'asc',
        vaultCurrentPage: 1,
        vaultEntriesPerPage: 25,
        earlyDeleteModalOpen: false,
        earlyDeleteVault: null,
        earlyDeleteReason: '',
        earlyDeleteInProgress: false,
        vaultColsOpen: false,
        showUserCol: true,
        cols: {
            user: true,
            retention: true,
            protection: false,
            stored: true,
            source_job: true,
            bucket_path: false,
            jobs_using: true,
            created: true,
            days_left: true,
        },

        init() {
            try {
                if (window.EB && window.EB.bindCols) {
                    window.EB.bindCols(this, 'e3-vaults-global');
                }
            } catch (e) {}
            this.reload();
        },

        tenantLabel() {
            if (!this.tenantFilter) return 'All tenants';
            if (this.tenantFilter === 'direct') return 'Direct (no tenant)';
            var t = (this.tenants || []).find(function (x) {
                return String(x.public_id || x.id) === String(this.tenantFilter);
            }.bind(this));
            return t && t.name ? t.name : 'Tenant';
        },

        pickTenant(value) {
            this.tenantFilter = value;
            this.tenantOpen = false;
            this.vaultCurrentPage = 1;
            this.reload();
        },

        reload() {
            this.loading = true;
            var params = new URLSearchParams();
            if (this.tenantFilter) params.set('tenant_id', this.tenantFilter);
            fetch('modules/addons/cloudstorage/api/e3backup_vault_list.php?' + params.toString(), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then((data) => {
                    if (data && data.status === 'success') {
                        this.vaultsActive = data.vaults_active || [];
                        this.vaultsRecycle = data.vaults_recycle || [];
                        this.legacyVaultsList = data.legacy_vaults || [];
                        this.graceDays = data.grace_days || 30;
                    } else {
                        this.vaultsActive = [];
                        this.vaultsRecycle = [];
                        this.legacyVaultsList = [];
                    }
                })
                .catch(() => {
                    this.vaultsActive = [];
                    this.vaultsRecycle = [];
                    this.legacyVaultsList = [];
                })
                .finally(() => { this.loading = false; });
        },

        ms365VaultsActive() {
            return Array.isArray(this.vaultsActive) ? this.vaultsActive : [];
        },

        ms365VaultsRecycle() {
            return Array.isArray(this.vaultsRecycle) ? this.vaultsRecycle : [];
        },

        legacyVaults() {
            return Array.isArray(this.legacyVaultsList) ? this.legacyVaultsList : [];
        },

        userVaultsUrl(vault) {
            var routeId = vault && (vault.user_route_id || vault.backup_user_id);
            if (!routeId) return '#';
            return 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id='
                + encodeURIComponent(String(routeId)) + '#vaults';
        },

        selectVaultSubTab(tab) {
            this.vaultSubTab = tab === 'recycle' ? 'recycle' : 'active';
            this.vaultCurrentPage = 1;
            this.vaultSearchQuery = '';
            this.vaultSortKey = this.vaultSubTab === 'recycle' ? 'days_remaining' : 'name';
            this.vaultSortDirection = this.vaultSubTab === 'recycle' ? 'asc' : 'asc';
        },

        setVaultEntries(value) {
            this.vaultEntriesPerPage = Number(value) || 25;
            this.vaultCurrentPage = 1;
        },

        vaultSourceList() {
            if (this.vaultSubTab === 'recycle') return this.ms365VaultsRecycle();
            return this.ms365VaultsActive().concat(this.legacyVaults());
        },

        vaultEmptyCopy() {
            return 'Microsoft 365 vaults and other destination buckets appear here when backup jobs use them.';
        },

        vaultJobsUsingDisplay(vault) {
            if (vault.is_ms365) {
                return vault.jobs_using ? vault.jobs_using : '—';
            }
            return vault.jobs_using != null ? vault.jobs_using : '—';
        },

        vaultMatchesSearch(vault, query) {
            var fields = [
                vault.username,
                vault.tenant_name,
                vault.name,
                vault.retention_tier,
                vault.protection_label,
                vault.storage_used_display,
                vault.job_name,
                vault.provider_label,
                vault.bucket_path,
                vault.created,
                String(vault.jobs_using ?? ''),
            ];
            if (this.vaultSubTab === 'recycle') {
                fields.push(String(vault.days_remaining ?? ''));
                fields.push(vault.recycle_teardown_at);
            }
            return fields.some(function (field) {
                return String(field || '').toLowerCase().includes(query);
            });
        },

        filteredVaults() {
            var query = this.vaultSearchQuery.trim().toLowerCase();
            var list = this.vaultSourceList().slice();
            if (query) {
                list = list.filter((vault) => this.vaultMatchesSearch(vault, query));
            }
            var key = this.vaultSortKey;
            list.sort((a, b) => {
                var left;
                var right;
                if (key === 'days_remaining') {
                    left = a.days_remaining ?? 99999;
                    right = b.days_remaining ?? 99999;
                } else if (key === 'storage') {
                    left = a.storage_used_bytes ?? 0;
                    right = b.storage_used_bytes ?? 0;
                } else if (key === 'jobs_using') {
                    left = a.jobs_using ?? 0;
                    right = b.jobs_using ?? 0;
                } else if (key === 'created') {
                    left = a.created ? new Date(a.created).getTime() : 0;
                    right = b.created ? new Date(b.created).getTime() : 0;
                } else {
                    left = a[key] ?? '';
                    right = b[key] ?? '';
                    if (typeof left === 'string') left = left.toLowerCase();
                    if (typeof right === 'string') right = right.toLowerCase();
                }
                if (left < right) return this.vaultSortDirection === 'asc' ? -1 : 1;
                if (left > right) return this.vaultSortDirection === 'asc' ? 1 : -1;
                return 0;
            });
            return list;
        },

        vaultSortBy(key) {
            if (this.vaultSortKey === key) {
                this.vaultSortDirection = this.vaultSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.vaultSortKey = key;
                this.vaultSortDirection = key === 'storage' || key === 'days_remaining' || key === 'jobs_using' || key === 'created' ? 'desc' : 'asc';
            }
            this.vaultCurrentPage = 1;
        },

        vaultSortIndicator(key) {
            if (this.vaultSortKey !== key) return '';
            return this.vaultSortDirection === 'asc' ? '↑' : '↓';
        },

        vaultTotalPages() {
            return Math.max(1, Math.ceil(this.filteredVaults().length / this.vaultEntriesPerPage));
        },

        pagedVaults() {
            var list = this.filteredVaults();
            var pages = this.vaultTotalPages();
            if (this.vaultCurrentPage > pages) this.vaultCurrentPage = pages;
            var start = (this.vaultCurrentPage - 1) * this.vaultEntriesPerPage;
            return list.slice(start, start + this.vaultEntriesPerPage);
        },

        vaultPageSummary() {
            var total = this.filteredVaults().length;
            if (total === 0) return 'Showing 0 of 0 vaults';
            var start = (this.vaultCurrentPage - 1) * this.vaultEntriesPerPage + 1;
            var end = Math.min(start + this.vaultEntriesPerPage - 1, total);
            return 'Showing ' + start + '–' + end + ' of ' + total + ' vaults';
        },

        formatDateShort(value) {
            if (!value) return 'Never';
            var date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString();
        },

        openEarlyDeleteModal(vault) {
            this.earlyDeleteVault = vault || null;
            this.earlyDeleteReason = '';
            this.earlyDeleteModalOpen = true;
        },

        closeEarlyDeleteModal(force) {
            if (force === undefined) force = false;
            if (this.earlyDeleteInProgress && !force) return;
            this.earlyDeleteModalOpen = false;
            this.earlyDeleteVault = null;
            this.earlyDeleteReason = '';
        },

        submitEarlyDeleteRequest() {
            var vault = this.earlyDeleteVault;
            if (!vault || !vault.id || this.earlyDeleteInProgress) return;
            var self = this;
            this.earlyDeleteInProgress = true;
            var params = {
                bucket_id: String(vault.id),
                user_id: String(vault.user_route_id || vault.backup_user_id || ''),
            };
            if (this.earlyDeleteReason.trim()) {
                params.reason = this.earlyDeleteReason.trim();
            }
            fetch('modules/addons/cloudstorage/api/ms365_vault_request_early_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        if (typeof e3backupNotify === 'function') {
                            e3backupNotify('success', data.message || 'Early deletion request submitted');
                        }
                        self.closeEarlyDeleteModal(true);
                        self.reload();
                    } else if (typeof e3backupNotify === 'function') {
                        e3backupNotify('error', data.message || 'Failed to submit request');
                    }
                })
                .catch(function () {
                    if (typeof e3backupNotify === 'function') {
                        e3backupNotify('error', 'Failed to submit request');
                    }
                })
                .finally(function () {
                    self.earlyDeleteInProgress = false;
                });
        },
    };
}
{/literal}
</script>
