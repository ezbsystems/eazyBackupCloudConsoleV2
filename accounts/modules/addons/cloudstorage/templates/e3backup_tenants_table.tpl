{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page" x-data="tenantsApp()">
    <div class="eb-page-inner">
        {assign var="activeNav" value="tenants"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="eb-panel">
            <div class="eb-page-header">
                <div>
                    <div class="eb-breadcrumb">
                        <a href="index.php?m=cloudstorage&page=e3backup" class="eb-breadcrumb-link">e3 Cloud Backup</a>
                        <span class="eb-breadcrumb-separator">/</span>
                        <span class="eb-breadcrumb-current">Tenants</span>
                    </div>
                    <h1 class="eb-page-title">Tenants</h1>
                    <p class="eb-page-description">Manage customer organizations and open tenant details for profile, members, backup users, and policies.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="index.php?m=cloudstorage&page=e3backup&view=tenant_detail&mode=create"
                       class="eb-btn eb-btn-primary eb-btn-sm">
                        + Create Tenant
                    </a>
                </div>
            </div>

            <div class="eb-table-toolbar">
                <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                    <button type="button" @click="isOpen = !isOpen" class="eb-menu-trigger">
                        <span x-text="'Show ' + entriesPerPage"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen" class="eb-dropdown-menu absolute left-0 mt-2 w-40 z-50" style="display: none;">
                        <template x-for="size in [10,25,50,100]" :key="'entries-' + size">
                            <button type="button"
                                    class="eb-menu-option"
                                    :class="entriesPerPage === size ? 'is-active' : ''"
                                    @click="setEntries(size); isOpen = false;">
                                <span x-text="size"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                    <button type="button" @click="isOpen = !isOpen" class="eb-menu-trigger">
                        Columns
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen" class="eb-dropdown-menu absolute left-0 mt-2 w-64 z-50 p-2" style="display: none;">
                        <template x-for="col in availableColumns" :key="'col-' + col.key">
                            <label class="eb-inline-choice justify-between w-full px-2 py-2 cursor-pointer rounded">
                                <span x-text="col.label"></span>
                                <input type="checkbox"
                                       class="eb-check-input"
                                       :checked="columnState[col.key]"
                                       @change="toggleColumn(col.key)">
                            </label>
                        </template>
                    </div>
                </div>

                <div class="flex-1"></div>
                <div class="eb-input-wrap w-full xl:w-80">
                    <div class="eb-input-icon">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text"
                           placeholder="Search tenant, slug, or contact"
                           x-model.debounce.200ms="searchQuery"
                           class="eb-input eb-input-has-icon w-full">
                </div>
            </div>

            <div class="eb-table-shell">
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>
                                <button type="button" class="eb-table-sort-button" @click="sortBy('name')">
                                    Tenant
                                    <span class="eb-sort-indicator" x-text="sortIndicator('name') || '↕'"></span>
                                </button>
                            </th>
                            <template x-if="columnState.contact_name"><th>Contact Name</th></template>
                            <template x-if="columnState.contact_email"><th>Contact Email</th></template>
                            <template x-if="columnState.user_count"><th>Tenant Members</th></template>
                            <template x-if="columnState.agent_count"><th>Agents</th></template>
                            <template x-if="columnState.updated_at"><th>Updated</th></template>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr><td :colspan="visibleColumnCount()">
                                <div class="eb-app-empty"><p class="eb-app-empty-copy">Loading tenants...</p></div>
                            </td></tr>
                        </template>
                        <template x-if="!loading && pagedTenants().length === 0">
                            <tr><td :colspan="visibleColumnCount()">
                                <div class="eb-app-empty">
                                    <div class="eb-app-empty-title">No tenants found</div>
                                    <p class="eb-app-empty-copy">Adjust your search or create a new tenant to get started.</p>
                                </div>
                            </td></tr>
                        </template>
                        <template x-for="tenant in pagedTenants()" :key="'tenant-row-' + tenant.id">
                            <tr class="eb-table-row-clickable" @click="goToDetail(tenant.public_id || tenant.id)">
                                <td>
                                    <div class="eb-table-primary" x-text="tenant.name"></div>
                                    <div class="eb-table-mono" x-text="tenant.slug"></div>
                                </td>
                                <template x-if="columnState.contact_name"><td x-text="tenant.contact_name || '-'"></td></template>
                                <template x-if="columnState.contact_email"><td x-text="tenant.contact_email || '-'"></td></template>
                                <template x-if="columnState.user_count"><td x-text="tenant.user_count || 0"></td></template>
                                <template x-if="columnState.agent_count"><td x-text="tenant.agent_count || 0"></td></template>
                                <template x-if="columnState.updated_at"><td x-text="formatDate(tenant.updated_at)"></td></template>
                                <td>
                                    <span class="eb-badge eb-badge--dot"
                                          :class="tenant.status === 'active' ? 'eb-badge--success' : 'eb-badge--warning'"
                                          x-text="tenant.status"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="eb-table-pagination">
                <div x-text="pageSummary()"></div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="prevPage()" :disabled="currentPage <= 1" class="eb-table-pagination-button">Prev</button>
                    <span style="color: var(--eb-text-secondary)" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                    <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages()" class="eb-table-pagination-button">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
function tenantsApp() {
    return {
        tenants: [],
        loading: true,
        searchQuery: '',
        entriesPerPage: 25,
        currentPage: 1,
        sortKey: 'name',
        sortDirection: 'asc',
        availableColumns: [
            { key: 'contact_name', label: 'Contact Name' },
            { key: 'contact_email', label: 'Contact Email' },
            { key: 'user_count', label: 'Tenant Members' },
            { key: 'agent_count', label: 'Agents' },
            { key: 'updated_at', label: 'Updated' }
        ],
        columnState: {
            contact_name: true,
            contact_email: true,
            user_count: true,
            agent_count: true,
            updated_at: true
        },
        init() { this.loadTenants(); },
        async loadTenants() {
            this.loading = true;
            try {
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_list.php');
                const data = await response.json();
                this.tenants = data.status === 'success' && Array.isArray(data.tenants) ? data.tenants : [];
                this.currentPage = 1;
            } catch (error) {
                this.tenants = [];
            }
            this.loading = false;
        },
        setEntries(value) { this.entriesPerPage = value; this.currentPage = 1; },
        toggleColumn(key) { this.columnState[key] = !this.columnState[key]; },
        sortBy(key) {
            if (this.sortKey === key) this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            else { this.sortKey = key; this.sortDirection = 'asc'; }
        },
        sortIndicator(key) {
            if (this.sortKey !== key) return '';
            return this.sortDirection === 'asc' ? '↑' : '↓';
        },
        filteredTenants() {
            const query = this.searchQuery.trim().toLowerCase();
            let list = this.tenants.slice();
            if (query) {
                list = list.filter((tenant) => {
                    const name = (tenant.name || '').toLowerCase();
                    const slug = (tenant.slug || '').toLowerCase();
                    const email = (tenant.contact_email || '').toLowerCase();
                    const contact = (tenant.contact_name || '').toLowerCase();
                    return name.includes(query) || slug.includes(query) || email.includes(query) || contact.includes(query);
                });
            }
            list.sort((a, b) => {
                let left = a[this.sortKey];
                let right = b[this.sortKey];
                if (this.sortKey === 'updated_at') {
                    left = left ? new Date(left).getTime() : 0;
                    right = right ? new Date(right).getTime() : 0;
                }
                if (left === null || left === undefined) left = '';
                if (right === null || right === undefined) right = '';
                if (typeof left === 'string') left = left.toLowerCase();
                if (typeof right === 'string') right = right.toLowerCase();
                if (left < right) return this.sortDirection === 'asc' ? -1 : 1;
                if (left > right) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
            return list;
        },
        totalPages() {
            const count = this.filteredTenants().length;
            return Math.max(1, Math.ceil(count / this.entriesPerPage));
        },
        pagedTenants() {
            const list = this.filteredTenants();
            const pages = this.totalPages();
            if (this.currentPage > pages) this.currentPage = pages;
            const start = (this.currentPage - 1) * this.entriesPerPage;
            return list.slice(start, start + this.entriesPerPage);
        },
        pageSummary() {
            const total = this.filteredTenants().length;
            if (total === 0) return 'Showing 0 of 0 tenants';
            const start = (this.currentPage - 1) * this.entriesPerPage + 1;
            const end = Math.min(start + this.entriesPerPage - 1, total);
            return 'Showing ' + start + '-' + end + ' of ' + total + ' tenants';
        },
        visibleColumnCount() {
            let count = 2;
            if (this.columnState.contact_name) count += 1;
            if (this.columnState.contact_email) count += 1;
            if (this.columnState.user_count) count += 1;
            if (this.columnState.agent_count) count += 1;
            if (this.columnState.updated_at) count += 1;
            return count;
        },
        prevPage() { if (this.currentPage > 1) this.currentPage -= 1; },
        nextPage() { if (this.currentPage < this.totalPages()) this.currentPage += 1; },
        formatDate(value) {
            if (!value) return 'Never';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        },
        goToDetail(tenantId) {
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=tenant_detail&tenant_id=' + encodeURIComponent(tenantId);
        }
    };
}
</script>
{/literal}
