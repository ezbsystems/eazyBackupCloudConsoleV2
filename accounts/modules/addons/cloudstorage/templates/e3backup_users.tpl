<div class="min-h-screen bg-slate-950 text-gray-200" x-data="backupUsersApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="users"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                        <span class="text-slate-600">/</span>
                        <span class="text-white text-sm font-medium">Users</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-white">Users</h1>
                    <p class="text-xs text-slate-400 mt-1">Manage backup usernames and monitor scoped activity.</p>
                </div>
                <button type="button"
                        @click="openCreateModal()"
                        class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500">
                    + Add User
                </button>
            </div>

            <div class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
                {if $isMspClient}
                <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                        <span x-text="tenantFilterLabel()"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute left-0 mt-2 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                         style="display: none;">
                        <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                            Filter by tenant
                        </div>
                        <div class="px-4 py-2 border-b border-slate-800">
                            <input type="text" x-model="tenantSearch" placeholder="Search tenants"
                                   class="w-full rounded-md bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div class="py-1 max-h-72 overflow-auto">
                            <button type="button"
                                    class="w-full px-4 py-2 text-left text-sm transition"
                                    :class="tenantFilter === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                    @click="setTenantFilter(''); isOpen=false;">
                                All Tenants
                            </button>
                            <button type="button"
                                    class="w-full px-4 py-2 text-left text-sm transition"
                                    :class="tenantFilter === 'direct' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                    @click="setTenantFilter('direct'); isOpen=false;">
                                Direct (No Tenant)
                            </button>
                            <template x-for="tenant in filteredTenants" :key="'tenant-filter-' + tenant.id">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :class="String(tenantFilter) === String(tenant.id) ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                        @click="setTenantFilter(String(tenant.id)); isOpen=false;">
                                    <span x-text="tenant.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                {/if}

                <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                        <span x-text="'Show ' + entriesPerPage"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                         style="display: none;">
                        <template x-for="size in [10,25,50,100]" :key="'entries-' + size">
                            <button type="button"
                                    class="w-full px-4 py-2 text-left text-sm transition"
                                    :class="entriesPerPage === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                    @click="setEntries(size); isOpen=false;">
                                <span x-text="size"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                        Columns
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute left-0 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden p-2"
                         style="display: none;">
                        <template x-for="col in availableColumns" :key="'col-' + col.key">
                            <label class="flex items-center justify-between rounded px-2 py-2 text-sm hover:bg-slate-800/60 cursor-pointer">
                                <span x-text="col.label"></span>
                                <input type="checkbox"
                                       class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500"
                                       :checked="columnState[col.key]"
                                       @change="toggleColumn(col.key)">
                            </label>
                        </template>
                    </div>
                </div>

                <div class="flex-1"></div>
                <input type="text"
                       placeholder="Search username or email"
                       x-model.debounce.200ms="searchQuery"
                       class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-800">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900/80 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">
                                <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('username')">
                                    Username
                                    <span x-text="sortIndicator('username')"></span>
                                </button>
                            </th>
                            <template x-if="columnState.tenant && isMspClient">
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('tenant_name')">
                                        Tenant
                                        <span x-text="sortIndicator('tenant_name')"></span>
                                    </button>
                                </th>
                            </template>
                            <template x-if="columnState.vaults_count">
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('vaults_count')">
                                        # Vaults
                                        <span x-text="sortIndicator('vaults_count')"></span>
                                    </button>
                                </th>
                            </template>
                            <template x-if="columnState.jobs_count">
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('jobs_count')">
                                        # Jobs
                                        <span x-text="sortIndicator('jobs_count')"></span>
                                    </button>
                                </th>
                            </template>
                            <template x-if="columnState.agents_count">
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('agents_count')">
                                        # Agents
                                        <span x-text="sortIndicator('agents_count')"></span>
                                    </button>
                                </th>
                            </template>
                            <template x-if="columnState.last_backup_at">
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('last_backup_at')">
                                        Last Backup
                                        <span x-text="sortIndicator('last_backup_at')"></span>
                                    </button>
                                </th>
                            </template>
                            <template x-if="columnState.online_devices">
                                <th class="px-4 py-3 text-left font-medium">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('online_devices')">
                                        Online Devices
                                        <span x-text="sortIndicator('online_devices')"></span>
                                    </button>
                                </th>
                            </template>
                            <th class="px-4 py-3 text-left font-medium">
                                <button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="sortBy('status')">
                                    Status
                                    <span x-text="sortIndicator('status')"></span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <template x-if="loading">
                            <tr>
                                <td :colspan="visibleColumnCount()" class="px-4 py-8 text-center text-slate-400">
                                    <svg class="animate-spin h-6 w-6 mx-auto text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && pagedUsers().length === 0">
                            <tr>
                                <td :colspan="visibleColumnCount()" class="px-4 py-8 text-center text-slate-400">
                                    No users found.
                                </td>
                            </tr>
                        </template>
                        <template x-for="user in pagedUsers()" :key="'user-row-' + user.id">
                            <tr class="hover:bg-slate-800/50 cursor-pointer" @click="goToDetail(user.id)">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-100" x-text="user.username"></div>
                                    <div class="text-xs text-slate-500" x-text="user.email"></div>
                                </td>
                                <template x-if="columnState.tenant && isMspClient">
                                    <td class="px-4 py-3 text-slate-300" x-text="user.tenant_name || 'Direct'"></td>
                                </template>
                                <template x-if="columnState.vaults_count">
                                    <td class="px-4 py-3 text-slate-300" x-text="user.vaults_count"></td>
                                </template>
                                <template x-if="columnState.jobs_count">
                                    <td class="px-4 py-3 text-slate-300" x-text="user.jobs_count"></td>
                                </template>
                                <template x-if="columnState.agents_count">
                                    <td class="px-4 py-3 text-slate-300" x-text="user.agents_count"></td>
                                </template>
                                <template x-if="columnState.last_backup_at">
                                    <td class="px-4 py-3 text-slate-300" x-text="formatDate(user.last_backup_at)"></td>
                                </template>
                                <template x-if="columnState.online_devices">
                                    <td class="px-4 py-3 text-slate-300" x-text="user.online_devices"></td>
                                </template>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="user.status === 'active' ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-700 text-slate-300'">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="user.status === 'active' ? 'bg-emerald-400' : 'bg-slate-500'"></span>
                                        <span x-text="user.status"></span>
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
                    <button type="button"
                            @click="prevPage()"
                            :disabled="currentPage <= 1"
                            class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        Prev
                    </button>
                    <span class="text-slate-300" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                    <button type="button"
                            @click="nextPage()"
                            :disabled="currentPage >= totalPages()"
                            class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    {include file="modules/addons/cloudstorage/templates/partials/e3backup_create_user_modal.tpl"
        modalTitle="Add User"
        submitLabel="Create User"
        submittingLabel="Creating..."
        showTenantSelector=true}
</div>

{literal}
<script>
function backupUsersApp() {
    return {
        users: [],
        loading: true,
        saving: false,
        isMspClient: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        tenants: {/literal}{$tenants|@json_encode nofilter}{literal} || [],
        tenantFilter: '',
        tenantSearch: '',
        tenantAssignSearch: '',
        searchQuery: '',
        entriesPerPage: 25,
        currentPage: 1,
        sortKey: 'username',
        sortDirection: 'asc',
        showCreateModal: false,
        formErrorMessage: '',
        fieldErrors: {},
        form: {
            username: '',
            password: '',
            password_confirm: '',
            email: '',
            tenant_id: '',
            encryption_mode: 'managed',
            managed_acknowledged: false,
            strict_acknowledged: false,
            recovery_key_downloaded: false
        },
        availableColumns: [
            { key: 'tenant', label: 'Tenant' },
            { key: 'vaults_count', label: '# Vaults' },
            { key: 'jobs_count', label: '# Jobs' },
            { key: 'agents_count', label: '# Agents' },
            { key: 'last_backup_at', label: 'Last Backup' },
            { key: 'online_devices', label: 'Online Devices' }
        ],
        columnState: {
            tenant: true,
            vaults_count: true,
            jobs_count: true,
            agents_count: true,
            last_backup_at: true,
            online_devices: true
        },

        init() {
            if (!this.isMspClient) {
                this.columnState.tenant = false;
            }
            this.loadUsers();
        },

        get filteredTenants() {
            const search = this.tenantSearch.trim().toLowerCase();
            if (!search) return this.tenants;
            return this.tenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(search));
        },

        get filteredAssignTenants() {
            const search = this.tenantAssignSearch.trim().toLowerCase();
            if (!search) return this.tenants;
            return this.tenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(search));
        },

        tenantFilterLabel() {
            if (this.tenantFilter === '') return 'All Tenants';
            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
            const tenant = this.tenants.find((item) => String(item.id) === String(this.tenantFilter));
            return tenant ? tenant.name : 'Tenant';
        },

        createTenantLabel() {
            if (!this.form.tenant_id) return 'Direct (No Tenant)';
            const tenant = this.tenants.find((item) => String(item.id) === String(this.form.tenant_id));
            return tenant ? tenant.name : 'Select tenant';
        },

        generateRecoveryKey() {
            if (window.crypto && window.crypto.getRandomValues) {
                const bytes = new Uint8Array(32);
                window.crypto.getRandomValues(bytes);
                return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
            }
            return Math.random().toString(36).slice(2) + Date.now().toString(36);
        },

        downloadRecoveryKey() {
            if (this.form.encryption_mode !== 'strict' || this.form.recovery_key_downloaded) {
                return;
            }

            const keyValue = this.generateRecoveryKey();
            const content = [
                'eazyBackup Recovery Key',
                '',
                'User: ' + (this.form.username || ''),
                'Email: ' + (this.form.email || ''),
                'Generated At: ' + new Date().toISOString(),
                '',
                'Recovery Key:',
                keyValue,
                '',
                'Important: This key is shown once and not stored by eazyBackup.'
            ].join('\n');

            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            const filenameSafeUser = (this.form.username || 'backup-user').replace(/[^a-zA-Z0-9._-]+/g, '-');
            anchor.href = url;
            anchor.download = filenameSafeUser + '-recovery-key.txt';
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            URL.revokeObjectURL(url);

            this.form.recovery_key_downloaded = true;
            if (this.fieldErrors.recovery_key_downloaded) {
                delete this.fieldErrors.recovery_key_downloaded;
            }
        },

        setTenantFilter(value) {
            this.tenantFilter = value;
            this.currentPage = 1;
            this.loadUsers();
        },

        setEntries(value) {
            this.entriesPerPage = value;
            this.currentPage = 1;
        },

        toggleColumn(key) {
            this.columnState[key] = !this.columnState[key];
        },

        sortBy(key) {
            if (this.sortKey === key) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDirection = 'asc';
            }
        },

        sortIndicator(key) {
            if (this.sortKey !== key) return '';
            return this.sortDirection === 'asc' ? '↑' : '↓';
        },

        filteredUsers() {
            const query = this.searchQuery.trim().toLowerCase();
            let list = this.users.slice();
            if (query) {
                list = list.filter((user) => {
                    const username = (user.username || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    const tenantName = (user.tenant_name || '').toLowerCase();
                    return username.includes(query) || email.includes(query) || tenantName.includes(query);
                });
            }

            list.sort((a, b) => {
                let left = a[this.sortKey];
                let right = b[this.sortKey];

                if (this.sortKey === 'last_backup_at') {
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
            const count = this.filteredUsers().length;
            return Math.max(1, Math.ceil(count / this.entriesPerPage));
        },

        pagedUsers() {
            const list = this.filteredUsers();
            const pages = this.totalPages();
            if (this.currentPage > pages) this.currentPage = pages;
            const start = (this.currentPage - 1) * this.entriesPerPage;
            return list.slice(start, start + this.entriesPerPage);
        },

        pageSummary() {
            const total = this.filteredUsers().length;
            if (total === 0) return 'Showing 0 of 0 users';
            const start = (this.currentPage - 1) * this.entriesPerPage + 1;
            const end = Math.min(start + this.entriesPerPage - 1, total);
            return `Showing ${start}-${end} of ${total} users`;
        },

        visibleColumnCount() {
            let count = 2; // username + status
            if (this.isMspClient && this.columnState.tenant) count += 1;
            if (this.columnState.vaults_count) count += 1;
            if (this.columnState.jobs_count) count += 1;
            if (this.columnState.agents_count) count += 1;
            if (this.columnState.last_backup_at) count += 1;
            if (this.columnState.online_devices) count += 1;
            return count;
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage -= 1;
        },

        nextPage() {
            if (this.currentPage < this.totalPages()) this.currentPage += 1;
        },

        formatDate(value) {
            if (!value) return 'Never';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        },

        goToDetail(userId) {
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' + encodeURIComponent(userId);
        },

        openCreateModal() {
            this.form = {
                username: '',
                password: '',
                password_confirm: '',
                email: '',
                tenant_id: '',
                encryption_mode: 'managed',
                managed_acknowledged: false,
                strict_acknowledged: false,
                recovery_key_downloaded: false
            };
            this.formErrorMessage = '';
            this.fieldErrors = {};
            this.tenantAssignSearch = '';
            this.showCreateModal = true;
        },

        closeCreateModal() {
            if (this.form.encryption_mode === 'strict' && !this.form.recovery_key_downloaded) {
                const proceed = window.confirm('You haven\'t downloaded the recovery key. If you continue, you will not be able to recover encrypted data later.');
                if (!proceed) {
                    return;
                }
            }
            this.showCreateModal = false;
            this.saving = false;
        },

        validateCreateForm() {
            this.fieldErrors = {};

            if (!this.form.username) {
                this.fieldErrors.username = 'Username is required.';
            } else if (!/^[A-Za-z0-9._-]{3,64}$/.test(this.form.username)) {
                this.fieldErrors.username = 'Use 3-64 characters with letters, numbers, dots, underscores, or hyphens.';
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!this.form.email) {
                this.fieldErrors.email = 'Email is required.';
            } else if (!emailPattern.test(this.form.email)) {
                this.fieldErrors.email = 'Please enter a valid email address.';
            }

            if (this.form.encryption_mode === 'managed') {
                if (!this.form.password) {
                    this.fieldErrors.password = 'Password is required.';
                } else if (this.form.password.length < 8) {
                    this.fieldErrors.password = 'Password must be at least 8 characters.';
                }

                if (!this.form.password_confirm) {
                    this.fieldErrors.password_confirm = 'Please confirm your password.';
                } else if (this.form.password !== this.form.password_confirm) {
                    this.fieldErrors.password_confirm = 'Password confirmation does not match.';
                }
            }

            if (this.form.encryption_mode === 'managed') {
                if (!this.form.managed_acknowledged) {
                    this.fieldErrors.managed_acknowledged = 'Please acknowledge managed recovery.';
                }
            } else if (this.form.encryption_mode === 'strict') {
                if (!this.form.recovery_key_downloaded) {
                    this.fieldErrors.recovery_key_downloaded = 'Download the recovery key before creating this user.';
                }
                if (!this.form.strict_acknowledged) {
                    this.fieldErrors.strict_acknowledged = 'Please acknowledge strict mode requirements.';
                }
            }

            return Object.keys(this.fieldErrors).length === 0;
        },

        async loadUsers() {
            this.loading = true;
            try {
                let url = 'modules/addons/cloudstorage/api/e3backup_user_list.php';
                const params = new URLSearchParams();
                if (this.isMspClient && this.tenantFilter !== '') {
                    params.set('tenant_id', this.tenantFilter);
                }
                const query = params.toString();
                if (query) {
                    url += '?' + query;
                }

                const response = await fetch(url);
                const data = await response.json();
                if (data.status === 'success') {
                    this.users = Array.isArray(data.users) ? data.users : [];
                    this.currentPage = 1;
                } else {
                    this.users = [];
                    this.formErrorMessage = data.message || 'Failed to load users.';
                }
            } catch (error) {
                this.users = [];
                this.formErrorMessage = 'Failed to load users.';
            }
            this.loading = false;
        },

        async createUser() {
            this.formErrorMessage = '';
            if (!this.validateCreateForm()) {
                return;
            }

            this.saving = true;
            try {
                let passwordToSend = this.form.password;
                let passwordConfirmToSend = this.form.password_confirm;
                if (this.form.encryption_mode === 'strict') {
                    const generated = this.generateRecoveryKey().slice(0, 24);
                    passwordToSend = generated;
                    passwordConfirmToSend = generated;
                }

                const body = new URLSearchParams({
                    username: this.form.username,
                    password: passwordToSend,
                    password_confirm: passwordConfirmToSend,
                    email: this.form.email,
                    status: 'active',
                    encryption_mode: this.form.encryption_mode,
                    managed_acknowledged: this.form.managed_acknowledged ? '1' : '0',
                    strict_acknowledged: this.form.strict_acknowledged ? '1' : '0',
                    recovery_key_downloaded: this.form.recovery_key_downloaded ? '1' : '0'
                });

                if (this.isMspClient && this.form.tenant_id) {
                    body.set('tenant_id', this.form.tenant_id);
                }

                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();

                if (data.status === 'success') {
                    this.closeCreateModal();
                    await this.loadUsers();
                } else {
                    this.formErrorMessage = data.message || 'Failed to create user.';
                    this.fieldErrors = data.errors || {};
                }
            } catch (error) {
                this.formErrorMessage = 'Failed to create user.';
            }
            this.saving = false;
        }
    };
}
</script>
{/literal}

