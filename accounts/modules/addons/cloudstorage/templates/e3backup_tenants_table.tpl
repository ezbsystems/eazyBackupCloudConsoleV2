<div class="min-h-screen bg-slate-950 text-gray-200" x-data="tenantsApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="tenants"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                        <span class="text-slate-600">/</span>
                        <span class="text-white text-sm font-medium">Tenants</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-white">Tenants</h1>
                    <p class="text-xs text-slate-400 mt-1">Manage customer organizations and open tenant details for profile, members, backup users, and policies.</p>
                </div>
                <a href="index.php?m=cloudstorage&page=e3backup&view=tenant_detail&mode=create"
                   class="px-4 py-2 rounded-md bg-violet-600 text-white text-sm font-semibold hover:bg-violet-500">
                    + Create Tenant
                </a>
            </div>

            <div class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
                <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <span x-text="'Show ' + entriesPerPage"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen" class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
                        <template x-for="size in [10,25,50,100]" :key="'entries-' + size">
                            <button type="button"
                                    class="w-full px-4 py-2 text-left text-sm transition"
                                    :class="entriesPerPage === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                    @click="setEntries(size); isOpen = false;">
                                <span x-text="size"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-violet-500">
                        Columns
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen" class="absolute left-0 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden p-2" style="display: none;">
                        <template x-for="col in availableColumns" :key="'col-' + col.key">
                            <label class="flex items-center justify-between rounded px-2 py-2 text-sm hover:bg-slate-800/60 cursor-pointer">
                                <span x-text="col.label"></span>
                                <input type="checkbox"
                                       class="rounded border-slate-600 bg-slate-800 text-violet-500 focus:ring-violet-500"
                                       :checked="columnState[col.key]"
                                       @change="toggleColumn(col.key)">
                            </label>
                        </template>
                    </div>
                </div>

                <div class="flex-1"></div>
                <input type="text"
                       placeholder="Search tenant, slug, or contact"
                       x-model.debounce.200ms="searchQuery"
                       class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-violet-500">
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-800">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900/80 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('name')">
                                    Tenant
                                    <span x-text="sortIndicator('name')"></span>
                                </button>
                            </th>
                            <template x-if="columnState.contact_name"><th class="px-4 py-3 text-left font-medium">Contact Name</th></template>
                            <template x-if="columnState.contact_email"><th class="px-4 py-3 text-left font-medium">Contact Email</th></template>
                            <template x-if="columnState.user_count"><th class="px-4 py-3 text-left font-medium">Tenant Members</th></template>
                            <template x-if="columnState.agent_count"><th class="px-4 py-3 text-left font-medium">Agents</th></template>
                            <template x-if="columnState.updated_at"><th class="px-4 py-3 text-left font-medium">Updated</th></template>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <template x-if="loading">
                            <tr><td :colspan="visibleColumnCount()" class="px-4 py-8 text-center text-slate-400">Loading tenants...</td></tr>
                        </template>
                        <template x-if="!loading && pagedTenants().length === 0">
                            <tr><td :colspan="visibleColumnCount()" class="px-4 py-8 text-center text-slate-400">No tenants found.</td></tr>
                        </template>
                        <template x-for="tenant in pagedTenants()" :key="'tenant-row-' + tenant.id">
                            <tr class="hover:bg-slate-800/50 cursor-pointer" @click="goToDetail(tenant.id)">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-100" x-text="tenant.name"></div>
                                    <div class="text-xs text-slate-500 font-mono" x-text="tenant.slug"></div>
                                </td>
                                <template x-if="columnState.contact_name"><td class="px-4 py-3 text-slate-300" x-text="tenant.contact_name || '-'"></td></template>
                                <template x-if="columnState.contact_email"><td class="px-4 py-3 text-slate-300" x-text="tenant.contact_email || '-'"></td></template>
                                <template x-if="columnState.user_count"><td class="px-4 py-3 text-slate-300" x-text="tenant.user_count || 0"></td></template>
                                <template x-if="columnState.agent_count"><td class="px-4 py-3 text-slate-300" x-text="tenant.agent_count || 0"></td></template>
                                <template x-if="columnState.updated_at"><td class="px-4 py-3 text-slate-300" x-text="formatDate(tenant.updated_at)"></td></template>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="tenant.status === 'active' ? 'bg-emerald-500/15 text-emerald-200' : 'bg-amber-500/15 text-amber-200'">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="tenant.status === 'active' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                                        <span x-text="tenant.status"></span>
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
                <div x-text="pageSummary()"></div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="prevPage()" :disabled="currentPage <= 1" class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">Prev</button>
                    <span class="text-slate-300" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                    <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages()" class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
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
