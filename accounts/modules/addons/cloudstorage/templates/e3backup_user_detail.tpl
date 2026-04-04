{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.75 1.03m-3.75-1.03a9.094 9.094 0 0 1-3.75 1.03m7.5-1.03a9.094 9.094 0 0 0-7.5 0m7.5 0A9.094 9.094 0 0 0 12 15.75a9.094 9.094 0 0 0-3.75 2.97m0 0A9.094 9.094 0 0 1 4.5 19.75m3.75-1.03a9.094 9.094 0 0 0-3.75-1.03m3.75 1.03A9.094 9.094 0 0 1 12 15.75m0 0a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Actions}{/capture}

{capture assign=ebE3UserDetailBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-breadcrumb-link">Users</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current" x-text="user.username || 'User Detail'"></span>
    </div>
{/capture}

{capture assign=ebE3UserDetailPageActions}
    <a href="index.php?m=cloudstorage&page=e3backup&view=users"
       class="eb-btn eb-btn-secondary eb-btn-sm">
        Back to Users
    </a>
{/capture}

{capture assign=ebE3Content}
<div x-data="backupUserDetailApp()" class="space-y-6">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3UserDetailBreadcrumb
        ebPageTitle='User Detail'
        ebPageDescription='Review scoped metrics and update this username configuration.'
        ebPageActions=$ebE3UserDetailPageActions
    }

    <template x-if="loading">
        <div class="eb-card !p-10 text-center">
            <span class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-[color:var(--eb-info-border)] border-t-[color:var(--eb-info-icon)]" aria-hidden="true"></span>
        </div>
    </template>

    <template x-if="!loading">
        <div class="space-y-6">
            <div class="eb-card-raised !p-4">
                <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <div class="eb-stat-label">Username</div>
                        <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="user.username || '—'"></div>
                    </div>
                    <div>
                        <div class="eb-stat-label">Email</div>
                        <div class="mt-1 text-[var(--eb-text-primary)]" x-text="user.email || '—'"></div>
                    </div>
                    <div>
                        <div class="eb-stat-label">Tenant</div>
                        <div class="mt-1 text-[var(--eb-text-primary)]" x-text="isMspClient ? (user.canonical_tenant_name || user.tenant_name || 'Direct') : 'Direct'"></div>
                    </div>
                    <div>
                        <div class="eb-stat-label">Status</div>
                        <div class="mt-1">
                            <span class="eb-badge eb-badge--dot"
                                  :class="user.status === 'active' ? 'eb-badge--success' : 'eb-badge--neutral'">
                                <span x-text="user.status || 'unknown'"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div class="eb-stat-card">
                    <div class="eb-stat-label">Vaults</div>
                    <div class="eb-stat-value !text-xl !leading-tight lg:!text-2xl" x-text="user.vaults_count ?? 0"></div>
                </div>
                <div class="eb-stat-card">
                    <div class="eb-stat-label">Jobs</div>
                    <div class="eb-stat-value !text-xl !leading-tight lg:!text-2xl" x-text="user.jobs_count ?? 0"></div>
                </div>
                <div class="eb-stat-card">
                    <div class="eb-stat-label">Agents</div>
                    <div class="eb-stat-value !text-xl !leading-tight lg:!text-2xl" x-text="user.agents_count ?? 0"></div>
                </div>
                <div class="eb-stat-card">
                    <div class="eb-stat-label">Online Devices</div>
                    <div class="eb-stat-value !text-xl !leading-tight lg:!text-2xl" x-text="user.online_devices ?? 0"></div>
                </div>
                <div class="eb-stat-card">
                    <div class="eb-stat-label">Last Backup</div>
                    <div class="mt-1 text-base font-semibold text-[var(--eb-text-primary)]" x-text="formatDate(user.last_backup_at)"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="eb-card overflow-visible">
                    <h2 class="eb-card-title mb-3">Update User</h2>
                    <div x-show="updateMessage" x-cloak class="eb-alert eb-alert--success mb-3" role="status">
                        <div x-text="updateMessage"></div>
                    </div>
                    <div x-show="updateError" x-cloak class="eb-alert eb-alert--danger mb-3" role="alert">
                        <div x-text="updateError"></div>
                    </div>

                    <form @submit.prevent="updateUser()" class="space-y-4">
                        <div>
                            <label class="eb-field-label" for="e3-user-detail-username">Username</label>
                            <input id="e3-user-detail-username" type="text" x-model.trim="updateForm.username" class="eb-input" :class="updateErrors.username ? 'is-error' : ''">
                            <p class="eb-field-error" x-show="updateErrors.username" x-text="updateErrors.username"></p>
                        </div>
                        <div>
                            <label class="eb-field-label" for="e3-user-detail-email">Email</label>
                            <input id="e3-user-detail-email" type="email" x-model.trim="updateForm.email" class="eb-input" :class="updateErrors.email ? 'is-error' : ''">
                            <p class="eb-field-error" x-show="updateErrors.email" x-text="updateErrors.email"></p>
                        </div>

                        {if $isMspClient}
                        <div class="overflow-visible">
                            <label class="eb-field-label">Tenant</label>
                            <div class="relative overflow-visible" x-data="{ isOpen: false }" @click.away="isOpen = false">
                                <button type="button"
                                        @click="isOpen = !isOpen"
                                        class="eb-btn eb-btn-secondary eb-btn-sm flex w-full items-center justify-between gap-2">
                                    <span class="min-w-0 truncate text-left" x-text="updateTenantLabel()"></span>
                                    <svg class="h-4 w-4 shrink-0 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                                     class="eb-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                                     style="display: none;">
                                    <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                        <input type="text" x-model="tenantSearch" placeholder="Search tenants"
                                               class="eb-toolbar-search w-full !py-2 text-sm"
                                               @click.stop>
                                    </div>
                                    <div class="max-h-64 overflow-auto p-1">
                                        <button type="button"
                                                class="eb-menu-option"
                                                :class="updateForm.tenant_id === '' ? 'is-active' : ''"
                                                @click="updateForm.tenant_id=''; isOpen=false;">
                                            Direct (No Tenant)
                                        </button>
                                        <template x-for="tenant in filteredTenants" :key="'detail-tenant-' + (tenant.public_id || tenant.id)">
                                            <button type="button"
                                                    class="eb-menu-option"
                                                    :class="String(updateForm.tenant_id) === String(tenant.public_id || tenant.id) ? 'is-active' : ''"
                                                    @click="updateForm.tenant_id = String(tenant.public_id || tenant.id); isOpen=false;">
                                                <span x-text="tenant.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <p class="eb-field-error" x-show="updateErrors.tenant_id" x-text="updateErrors.tenant_id"></p>
                        </div>
                        {/if}

                        <div class="overflow-visible">
                            <label class="eb-field-label">Status</label>
                            <div class="relative overflow-visible" x-data="{ isOpen: false }" @click.away="isOpen = false">
                                <button type="button"
                                        @click="isOpen = !isOpen"
                                        class="eb-btn eb-btn-secondary eb-btn-sm flex w-full items-center justify-between gap-2">
                                    <span x-text="updateForm.status === 'disabled' ? 'Disabled' : 'Active'"></span>
                                    <svg class="h-4 w-4 shrink-0 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                                     class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                                     style="display: none;">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="updateForm.status === 'active' ? 'is-active' : ''"
                                            @click="updateForm.status='active'; isOpen=false;">
                                        Active
                                    </button>
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="updateForm.status === 'disabled' ? 'is-active' : ''"
                                            @click="updateForm.status='disabled'; isOpen=false;">
                                        Disabled
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                    :disabled="updating"
                                    class="eb-btn eb-btn-primary eb-btn-sm disabled:cursor-not-allowed">
                                <span x-show="!updating">Save Changes</span>
                                <span x-show="updating">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="eb-card overflow-visible">
                        <h2 class="eb-card-title mb-3">Reset Password</h2>
                        <div x-show="passwordMessage" x-cloak class="eb-alert eb-alert--success mb-3" role="status">
                            <div x-text="passwordMessage"></div>
                        </div>
                        <div x-show="passwordError" x-cloak class="eb-alert eb-alert--danger mb-3" role="alert">
                            <div x-text="passwordError"></div>
                        </div>
                        <form @submit.prevent="resetPassword()" class="space-y-4">
                            <div>
                                <label class="eb-field-label" for="e3-user-detail-pw">New Password</label>
                                <input id="e3-user-detail-pw" type="password" x-model="passwordForm.password" class="eb-input" :class="passwordErrors.password ? 'is-error' : ''">
                                <p class="eb-field-error" x-show="passwordErrors.password" x-text="passwordErrors.password"></p>
                            </div>
                            <div>
                                <label class="eb-field-label" for="e3-user-detail-pw2">Confirm Password</label>
                                <input id="e3-user-detail-pw2" type="password" x-model="passwordForm.password_confirm" class="eb-input" :class="passwordErrors.password_confirm ? 'is-error' : ''">
                                <p class="eb-field-error" x-show="passwordErrors.password_confirm" x-text="passwordErrors.password_confirm"></p>
                            </div>
                            <div>
                                <button type="submit"
                                        :disabled="resettingPassword"
                                        class="eb-btn eb-btn-primary eb-btn-sm disabled:cursor-not-allowed">
                                    <span x-show="!resettingPassword">Update Password</span>
                                    <span x-show="resettingPassword">Updating...</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="eb-card-raised border-[color:var(--eb-danger-border)] bg-[color-mix(in_srgb,var(--eb-danger-bg)_35%,var(--eb-bg-raised))]">
                        <h2 class="eb-card-title mb-2 text-[var(--eb-danger-text)]">Delete User</h2>
                        <p class="eb-card-subtitle mb-4 !text-[var(--eb-text-secondary)]">This permanently removes the username record.</p>
                        <div x-show="deleteError" x-cloak class="eb-alert eb-alert--danger mb-3" role="alert">
                            <div x-text="deleteError"></div>
                        </div>
                        <button type="button"
                                @click="deleteUser()"
                                :disabled="deleting"
                                class="eb-btn eb-btn-danger-solid eb-btn-sm disabled:cursor-not-allowed">
                            <span x-show="!deleting">Delete User</span>
                            <span x-show="deleting">Deleting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='users'
    ebE3Title='Backup user'
    ebE3Description='Account details, usage, and administration for this username.'
    ebE3Icon=$ebE3Icon
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

{literal}
<script>
function backupUserDetailApp() {
    return {
        userId: {/literal}{$user->id|intval}{literal},
        isMspClient: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        canonicalTenants: {/literal}{$canonicalTenants|@json_encode nofilter}{literal} || [],
        csrfToken: {/literal}{$csrfToken|@json_encode nofilter}{literal} || '',
        loading: true,
        updating: false,
        resettingPassword: false,
        deleting: false,
        tenantSearch: '',
        user: {
            id: {/literal}{$user->id|intval}{literal},
            username: {/literal}{$user->username|@json_encode nofilter}{literal},
            email: {/literal}{$user->email|@json_encode nofilter}{literal},
            tenant_id: {/literal}{$user->tenant_public_id|@json_encode nofilter}{literal},
            tenant_public_id: {/literal}{$user->tenant_public_id|@json_encode nofilter}{literal},
            tenant_name: {/literal}{$user->tenant_name|@json_encode nofilter}{literal},
            canonical_tenant_id: null,
            canonical_tenant_name: null,
            is_canonical_managed: false,
            status: {/literal}{$user->status|@json_encode nofilter}{literal}
        },
        updateForm: {
            username: '',
            email: '',
            tenant_id: '',
            status: 'active'
        },
        updateErrors: {},
        updateMessage: '',
        updateError: '',
        passwordForm: {
            password: '',
            password_confirm: ''
        },
        passwordErrors: {},
        passwordMessage: '',
        passwordError: '',
        deleteError: '',

        init() {
            this.loadUser();
        },

        get filteredTenants() {
            const query = this.tenantSearch.trim().toLowerCase();
            if (!query) return this.canonicalTenants;
            return this.canonicalTenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(query));
        },

        updateTenantLabel() {
            if (!this.updateForm.tenant_id) return 'Direct (No Tenant)';
            const tenant = this.canonicalTenants.find((item) => String(item.public_id || item.id) === String(this.updateForm.tenant_id));
            return tenant ? tenant.name : 'Select tenant';
        },

        formatDate(value) {
            if (!value) return 'Never';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        },

        assignFormsFromUser() {
            this.updateForm.username = this.user.username || '';
            this.updateForm.email = this.user.email || '';
            this.updateForm.tenant_id = this.user.canonical_tenant_id ? String(this.user.canonical_tenant_id) : '';
            this.updateForm.status = this.user.status || 'active';
        },

        shouldSendCanonicalTenantOnUpdate() {
            if (!this.isMspClient) return false;
            if (this.updateForm.tenant_id) return true;
            return !!this.user.is_canonical_managed;
        },

        async loadUser() {
            this.loading = true;
            try {
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_get.php?user_id=' + encodeURIComponent(this.userId));
                const data = await response.json();
                if (data.status === 'success' && data.user) {
                    this.user = data.user;
                    this.assignFormsFromUser();
                } else {
                    this.updateError = data.message || 'Failed to load user.';
                }
            } catch (error) {
                this.updateError = 'Failed to load user.';
            }
            this.loading = false;
        },

        validateUpdate() {
            this.updateErrors = {};
            if (!this.updateForm.username) {
                this.updateErrors.username = 'Username is required.';
            } else if (!/^[A-Za-z0-9._-]{3,64}$/.test(this.updateForm.username)) {
                this.updateErrors.username = 'Use 3-64 characters with letters, numbers, dots, underscores, or hyphens.';
            }
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!this.updateForm.email) {
                this.updateErrors.email = 'Email is required.';
            } else if (!emailPattern.test(this.updateForm.email)) {
                this.updateErrors.email = 'Please enter a valid email address.';
            }
            return Object.keys(this.updateErrors).length === 0;
        },

        async updateUser() {
            this.updateMessage = '';
            this.updateError = '';
            if (!this.validateUpdate()) return;

            this.updating = true;
            try {
                const body = new URLSearchParams({
                    user_id: String(this.userId),
                    username: this.updateForm.username,
                    email: this.updateForm.email,
                    status: this.updateForm.status
                });
                body.set('token', this.csrfToken);
                if (this.isMspClient && this.shouldSendCanonicalTenantOnUpdate()) {
                    body.set('canonical_tenant_id', this.updateForm.tenant_id ? this.updateForm.tenant_id : 'direct');
                }

                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.updateMessage = data.message || 'User updated successfully.';
                    this.updateErrors = {};
                    await this.loadUser();
                } else {
                    this.updateError = data.message || 'Failed to update user.';
                    this.updateErrors = data.errors || {};
                    if (this.updateErrors.canonical_tenant_id && !this.updateErrors.tenant_id) {
                        this.updateErrors.tenant_id = this.updateErrors.canonical_tenant_id;
                    }
                }
            } catch (error) {
                this.updateError = 'Failed to update user.';
            }
            this.updating = false;
        },

        validatePassword() {
            this.passwordErrors = {};
            if (!this.passwordForm.password) {
                this.passwordErrors.password = 'Password is required.';
            } else if (this.passwordForm.password.length < 8) {
                this.passwordErrors.password = 'Password must be at least 8 characters.';
            }
            if (!this.passwordForm.password_confirm) {
                this.passwordErrors.password_confirm = 'Please confirm the password.';
            } else if (this.passwordForm.password !== this.passwordForm.password_confirm) {
                this.passwordErrors.password_confirm = 'Password confirmation does not match.';
            }
            return Object.keys(this.passwordErrors).length === 0;
        },

        async resetPassword() {
            this.passwordMessage = '';
            this.passwordError = '';
            if (!this.validatePassword()) return;

            this.resettingPassword = true;
            try {
                const body = new URLSearchParams({
                    user_id: String(this.userId),
                    password: this.passwordForm.password,
                    password_confirm: this.passwordForm.password_confirm
                });
                body.set('token', this.csrfToken);
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.passwordMessage = data.message || 'Password updated successfully.';
                    this.passwordErrors = {};
                    this.passwordForm.password = '';
                    this.passwordForm.password_confirm = '';
                } else {
                    this.passwordError = data.message || 'Failed to update password.';
                    this.passwordErrors = data.errors || {};
                }
            } catch (error) {
                this.passwordError = 'Failed to update password.';
            }
            this.resettingPassword = false;
        },

        async deleteUser() {
            this.deleteError = '';
            if (!confirm('Delete this user? This action cannot be undone.')) {
                return;
            }

            this.deleting = true;
            try {
                const body = new URLSearchParams({ user_id: String(this.userId) });
                body.set('token', this.csrfToken);
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();
                if (data.status === 'success') {
                    window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=users';
                } else {
                    this.deleteError = data.message || 'Failed to delete user.';
                }
            } catch (error) {
                this.deleteError = 'Failed to delete user.';
            }
            this.deleting = false;
        }
    };
}
</script>
{/literal}
