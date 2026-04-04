{capture assign=ebE3Actions}
    <button type="button"
            onclick="window.dispatchEvent(new CustomEvent('eb-e3-user-create-open'))"
            class="eb-btn eb-btn-primary eb-btn-sm">
        Add User
    </button>
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.75 1.03m-3.75-1.03a9.094 9.094 0 0 1-3.75 1.03m7.5-1.03a9.094 9.094 0 0 0-7.5 0m7.5 0A9.094 9.094 0 0 0 12 15.75a9.094 9.094 0 0 0-3.75 2.97m0 0A9.094 9.094 0 0 1 4.5 19.75m3.75-1.03a9.094 9.094 0 0 0-3.75-1.03m3.75 1.03A9.094 9.094 0 0 1 12 15.75m0 0a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3UsersBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Users</span>
    </div>
{/capture}

{capture assign=ebE3UsersHeaderActions}
    <span class="eb-badge eb-badge--neutral" x-text="loading ? 'Loading users' : (filteredUsers().length + ' users')"></span>
{/capture}

{capture assign=ebE3Content}
<div x-data="backupUsersApp()" @eb-e3-user-create-open.window="openCreateModal()" class="space-y-6">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3UsersBreadcrumb
        ebPageTitle='User Directory'
        ebPageDescription='Manage backup usernames, tenant scope, and account activity.'
        ebPageActions=$ebE3UsersHeaderActions
    }

    <div class="eb-subpanel overflow-visible">
        <div class="eb-table-toolbar">
            {if $isMspClient}
            <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                <button type="button"
                        @click="isOpen = !isOpen"
                        class="eb-btn eb-btn-secondary eb-btn-sm min-w-[16rem] justify-between">
                    <span class="truncate" x-text="'Tenant: ' + tenantFilterLabel()"></span>
                    <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="isOpen"
                     x-transition
                     class="eb-menu absolute left-0 z-20 mt-2 w-72 overflow-hidden"
                     style="display: none;">
                    <div class="eb-menu-label">Filter by tenant</div>
                    <div class="border-b border-[var(--eb-border-subtle)] p-2">
                        <input type="search"
                               x-model="tenantSearch"
                               placeholder="Search tenants"
                               class="eb-toolbar-search w-full !py-2 text-sm"
                               @click.stop>
                    </div>
                    <div class="max-h-72 overflow-auto p-1">
                        <button type="button"
                                class="eb-menu-option"
                                :class="tenantFilter === '' ? 'is-active' : ''"
                                @click="setTenantFilter(''); isOpen=false;">
                            All Tenants
                        </button>
                        <button type="button"
                                class="eb-menu-option"
                                :class="tenantFilter === 'direct' ? 'is-active' : ''"
                                @click="setTenantFilter('direct'); isOpen=false;">
                            Direct (No Tenant)
                        </button>
                        <template x-for="tenant in filteredTenants" :key="'tenant-filter-' + (tenant.public_id || tenant.id)">
                            <button type="button"
                                    class="eb-menu-option"
                                    :class="String(tenantFilter) === String(tenant.public_id || tenant.id) ? 'is-active' : ''"
                                    @click="setTenantFilter(String(tenant.public_id || tenant.id)); isOpen=false;">
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
                        class="eb-btn eb-btn-secondary eb-btn-sm">
                    <span x-text="'Show ' + entriesPerPage"></span>
                    <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="isOpen"
                     x-transition
                     class="eb-dropdown-menu absolute left-0 z-20 mt-2 w-40 overflow-hidden"
                     style="display: none;">
                    <template x-for="size in [10,25,50,100]" :key="'entries-' + size">
                        <button type="button"
                                class="eb-menu-option"
                                :class="entriesPerPage === size ? 'is-active' : ''"
                                @click="setEntries(size); isOpen=false;">
                            <span x-text="size"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                <button type="button"
                        @click="isOpen = !isOpen"
                        class="eb-btn eb-btn-secondary eb-btn-sm">
                    <span>Columns</span>
                    <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="isOpen"
                     x-transition
                     class="eb-menu absolute left-0 z-20 mt-2 w-64 p-3"
                     style="display: none;">
                    <div class="eb-menu-label">Visible columns</div>
                    <div class="eb-menu-checklist mt-2">
                        <template x-for="col in availableColumns" :key="'col-' + col.key">
                            <label class="eb-menu-checklist-item">
                                <span x-text="col.label"></span>
                                <input type="checkbox"
                                       class="eb-check-input"
                                       :checked="columnState[col.key]"
                                       @change="toggleColumn(col.key)">
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex-1"></div>

            <input type="search"
                   placeholder="Search username, email, or tenant"
                   x-model.debounce.200ms="searchQuery"
                   class="eb-toolbar-search w-full xl:w-80">
        </div>

        <div x-show="canonicalTenantLoadError"
             x-cloak
             class="eb-alert eb-alert--warning"
             role="status">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.29 3.86l-7.5 13A1 1 0 0 0 3.66 18.5h16.68a1 1 0 0 0 .87-1.64l-7.5-13a1 1 0 0 0-1.74 0Z" />
            </svg>
            <div x-text="canonicalTenantLoadError"></div>
        </div>

        <template x-if="loading">
            <div class="eb-card !p-6 text-center">
                <div class="inline-flex items-center gap-3 text-sm text-[var(--eb-text-muted)]">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-[color:var(--eb-info-border)] border-t-[color:var(--eb-info-icon)]"></span>
                    Loading users...
                </div>
            </div>
        </template>

        <template x-if="!loading && pagedUsers().length === 0">
            <div class="eb-app-empty">
                <div class="eb-app-empty-title" x-text="searchQuery.trim() ? 'No matching users found' : 'No users found'"></div>
                <p class="eb-app-empty-copy" x-text="searchQuery.trim() ? 'Try a different search term or clear your filters.' : 'Create your first backup user to get started.'"></p>
            </div>
        </template>

        <template x-if="!loading && pagedUsers().length > 0">
            <div>
                <div class="eb-table-shell overflow-x-auto">
                    <table class="eb-table">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" class="eb-table-sort-button" @click="sortBy('username')">
                                        Username
                                        <span class="eb-sort-indicator" x-text="sortIndicator('username')"></span>
                                    </button>
                                </th>
                                <template x-if="columnState.tenant && isMspClient">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('tenant_name')">
                                            Tenant
                                            <span class="eb-sort-indicator" x-text="sortIndicator('tenant_name')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.vaults_count">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('vaults_count')">
                                            # Vaults
                                            <span class="eb-sort-indicator" x-text="sortIndicator('vaults_count')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.jobs_count">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('jobs_count')">
                                            # Jobs
                                            <span class="eb-sort-indicator" x-text="sortIndicator('jobs_count')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.agents_count">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('agents_count')">
                                            # Agents
                                            <span class="eb-sort-indicator" x-text="sortIndicator('agents_count')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.last_backup_at">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('last_backup_at')">
                                            Last Backup
                                            <span class="eb-sort-indicator" x-text="sortIndicator('last_backup_at')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.online_devices">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('online_devices')">
                                            Online Devices
                                            <span class="eb-sort-indicator" x-text="sortIndicator('online_devices')"></span>
                                        </button>
                                    </th>
                                </template>
                                <th>
                                    <button type="button" class="eb-table-sort-button" @click="sortBy('status')">
                                        Status
                                        <span class="eb-sort-indicator" x-text="sortIndicator('status')"></span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="user in pagedUsers()" :key="'user-row-' + user.id">
                                <tr class="cursor-pointer" @click="goToDetail(user.id)">
                                    <td class="eb-table-primary">
                                        <div class="font-medium text-[var(--eb-text-primary)]" x-text="user.username"></div>
                                        <div class="text-xs text-[var(--eb-text-muted)]" x-text="user.email"></div>
                                    </td>
                                    <template x-if="columnState.tenant && isMspClient">
                                        <td x-text="user.tenant_name || 'Direct'"></td>
                                    </template>
                                    <template x-if="columnState.vaults_count">
                                        <td x-text="user.vaults_count"></td>
                                    </template>
                                    <template x-if="columnState.jobs_count">
                                        <td x-text="user.jobs_count"></td>
                                    </template>
                                    <template x-if="columnState.agents_count">
                                        <td x-text="user.agents_count"></td>
                                    </template>
                                    <template x-if="columnState.last_backup_at">
                                        <td x-text="formatDate(user.last_backup_at)"></td>
                                    </template>
                                    <template x-if="columnState.online_devices">
                                        <td x-text="user.online_devices"></td>
                                    </template>
                                    <td>
                                        <span class="eb-badge eb-badge--dot"
                                              :class="user.status === 'active' ? 'eb-badge--success' : 'eb-badge--neutral'">
                                            <span x-text="user.status"></span>
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="eb-table-pagination">
                    <span x-text="pageSummary()"></span>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="prevPage()"
                                :disabled="currentPage <= 1"
                                class="eb-table-pagination-button">
                            Prev
                        </button>
                        <span class="text-[var(--eb-text-primary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                        <button type="button"
                                @click="nextPage()"
                                :disabled="currentPage >= totalPages()"
                                class="eb-table-pagination-button">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {include file="modules/addons/cloudstorage/templates/partials/e3backup_create_user_modal.tpl"
        modalTitle="Add User"
        submitLabel="Create User"
        submittingLabel="Creating..."
        showTenantSelector=true}
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='users'
    ebE3Title='Users'
    ebE3Description='Manage backup usernames and monitor scoped activity.'
    ebE3Icon=$ebE3Icon
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

{literal}
<script>
function backupUsersApp() {
    return {
        users: [],
        loading: true,
        saving: false,
        isMspClient: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        csrfToken: {/literal}{$csrfToken|@json_encode nofilter}{literal} || '',
        tenants: {/literal}{$tenants|@json_encode nofilter}{literal} || [],
        canonicalTenants: [],
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
        canonicalTenantLoadError: '',
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
            } else {
                this.loadCanonicalTenants();
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
            if (!search) return this.canonicalTenants;
            return this.canonicalTenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(search));
        },

        tenantFilterLabel() {
            if (this.tenantFilter === '') return 'All Tenants';
            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
            const tenant = this.tenants.find((item) => String(item.public_id || item.id) === String(this.tenantFilter));
            return tenant ? tenant.name : 'Tenant';
        },

        createTenantLabel() {
            if (!this.form.tenant_id) return 'Direct (No Tenant)';
            const tenant = this.canonicalTenants.find((item) => String(item.public_id || item.id) === String(this.form.tenant_id));
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

        async loadCanonicalTenants() {
            if (!this.isMspClient) {
                this.canonicalTenants = [];
                this.canonicalTenantLoadError = '';
                return;
            }
            try {
                const response = await fetch('index.php?m=eazybackup&a=ph-tenant-storage-links');
                const data = await response.json();
                if (data.status === 'success' && Array.isArray(data.tenants)) {
                    this.canonicalTenants = data.tenants.map((tenant) => ({
                        id: tenant.public_id || tenant.id,
                        public_id: tenant.public_id || tenant.id,
                        name: tenant.name || tenant.subdomain || tenant.fqdn || 'Tenant'
                    }));
                    this.canonicalTenantLoadError = '';
                    return;
                }
            } catch (error) {}
            this.canonicalTenants = [];
            this.canonicalTenantLoadError = 'Canonical tenant list unavailable. Only direct assignment is currently available.';
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
                body.set('token', this.csrfToken);

                if (this.isMspClient) {
                    body.set('canonical_tenant_id', this.form.tenant_id ? this.form.tenant_id : 'direct');
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
                    if (this.fieldErrors.canonical_tenant_id && !this.fieldErrors.tenant_id) {
                        this.fieldErrors.tenant_id = this.fieldErrors.canonical_tenant_id;
                    }
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

