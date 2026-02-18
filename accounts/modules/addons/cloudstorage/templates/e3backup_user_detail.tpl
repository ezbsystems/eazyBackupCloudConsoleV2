<div class="min-h-screen bg-slate-950 text-gray-200" x-data="backupUserDetailApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="users"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1 text-sm">
                        <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white">e3 Cloud Backup</a>
                        <span class="text-slate-600">/</span>
                        <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="text-slate-400 hover:text-white">Users</a>
                        <span class="text-slate-600">/</span>
                        <span class="text-white font-medium" x-text="user.username || 'User Detail'"></span>
                    </div>
                    <h1 class="text-2xl font-semibold text-white">User Detail</h1>
                    <p class="text-xs text-slate-400 mt-1">Review scoped metrics and update this username configuration.</p>
                </div>
                <a href="index.php?m=cloudstorage&page=e3backup&view=users"
                   class="px-4 py-2 rounded-md border border-slate-700 bg-slate-900 text-slate-200 text-sm hover:bg-slate-800">
                    Back to Users
                </a>
            </div>

            <template x-if="loading">
                <div class="py-10 text-center text-slate-400">
                    <svg class="animate-spin h-6 w-6 mx-auto text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
            </template>

            <template x-if="!loading">
                <div class="space-y-6">
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="text-slate-400 text-xs uppercase tracking-wide">Username</div>
                                <div class="text-slate-100 font-medium mt-1" x-text="user.username || '—'"></div>
                            </div>
                            <div>
                                <div class="text-slate-400 text-xs uppercase tracking-wide">Email</div>
                                <div class="text-slate-100 mt-1" x-text="user.email || '—'"></div>
                            </div>
                            <div>
                                <div class="text-slate-400 text-xs uppercase tracking-wide">Tenant</div>
                                <div class="text-slate-100 mt-1" x-text="isMspClient ? (user.tenant_name || 'Direct') : 'Direct'"></div>
                            </div>
                            <div>
                                <div class="text-slate-400 text-xs uppercase tracking-wide">Status</div>
                                <div class="mt-1">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="user.status === 'active' ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-700 text-slate-300'">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="user.status === 'active' ? 'bg-emerald-400' : 'bg-slate-500'"></span>
                                        <span x-text="user.status || 'unknown'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                            <div class="text-xs text-slate-400 uppercase">Vaults</div>
                            <div class="text-xl font-semibold text-slate-100 mt-2" x-text="user.vaults_count ?? 0"></div>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                            <div class="text-xs text-slate-400 uppercase">Jobs</div>
                            <div class="text-xl font-semibold text-slate-100 mt-2" x-text="user.jobs_count ?? 0"></div>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                            <div class="text-xs text-slate-400 uppercase">Agents</div>
                            <div class="text-xl font-semibold text-slate-100 mt-2" x-text="user.agents_count ?? 0"></div>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                            <div class="text-xs text-slate-400 uppercase">Online Devices</div>
                            <div class="text-xl font-semibold text-slate-100 mt-2" x-text="user.online_devices ?? 0"></div>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                            <div class="text-xs text-slate-400 uppercase">Last Backup</div>
                            <div class="text-sm font-medium text-slate-100 mt-2" x-text="formatDate(user.last_backup_at)"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
                            <h2 class="text-lg text-white font-semibold mb-3">Update User</h2>
                            <div x-show="updateMessage" class="mb-3 rounded-md border border-emerald-500/40 bg-emerald-900/20 px-3 py-2 text-sm text-emerald-200" x-text="updateMessage"></div>
                            <div x-show="updateError" class="mb-3 rounded-md border border-rose-500/40 bg-rose-900/20 px-3 py-2 text-sm text-rose-200" x-text="updateError"></div>

                            <form @submit.prevent="updateUser()" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Username</label>
                                    <input type="text" x-model.trim="updateForm.username"
                                           class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <p class="text-xs text-rose-300 mt-1" x-show="updateErrors.username" x-text="updateErrors.username"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Email</label>
                                    <input type="email" x-model.trim="updateForm.email"
                                           class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <p class="text-xs text-rose-300 mt-1" x-show="updateErrors.email" x-text="updateErrors.email"></p>
                                </div>

                                {if $isMspClient}
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Tenant</label>
                                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                                        <button type="button"
                                                @click="isOpen = !isOpen"
                                                class="w-full inline-flex items-center justify-between gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                            <span class="truncate" x-text="updateTenantLabel()"></span>
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
                                             class="absolute left-0 mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                                             style="display: none;">
                                            <div class="px-3 py-2 border-b border-slate-800">
                                                <input type="text" x-model="tenantSearch" placeholder="Search tenants"
                                                       class="w-full rounded-md bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                            </div>
                                            <div class="py-1 max-h-64 overflow-auto">
                                                <button type="button"
                                                        class="w-full px-4 py-2 text-left text-sm transition"
                                                        :class="updateForm.tenant_id === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                                        @click="updateForm.tenant_id=''; isOpen=false;">
                                                    Direct (No Tenant)
                                                </button>
                                                <template x-for="tenant in filteredTenants" :key="'detail-tenant-' + tenant.id">
                                                    <button type="button"
                                                            class="w-full px-4 py-2 text-left text-sm transition"
                                                            :class="String(updateForm.tenant_id) === String(tenant.id) ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                                            @click="updateForm.tenant_id = String(tenant.id); isOpen=false;">
                                                        <span x-text="tenant.name"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-rose-300 mt-1" x-show="updateErrors.tenant_id" x-text="updateErrors.tenant_id"></p>
                                </div>
                                {/if}

                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                                        <button type="button"
                                                @click="isOpen = !isOpen"
                                                class="w-full inline-flex items-center justify-between gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                            <span x-text="updateForm.status === 'disabled' ? 'Disabled' : 'Active'"></span>
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
                                             class="absolute left-0 mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                                             style="display: none;">
                                            <button type="button"
                                                    class="w-full px-4 py-2 text-left text-sm transition"
                                                    :class="updateForm.status === 'active' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                                    @click="updateForm.status='active'; isOpen=false;">
                                                Active
                                            </button>
                                            <button type="button"
                                                    class="w-full px-4 py-2 text-left text-sm transition"
                                                    :class="updateForm.status === 'disabled' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                                    @click="updateForm.status='disabled'; isOpen=false;">
                                                Disabled
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <button type="submit"
                                            :disabled="updating"
                                            class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500 disabled:opacity-60 disabled:cursor-not-allowed">
                                        <span x-show="!updating">Save Changes</span>
                                        <span x-show="updating">Saving...</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="space-y-6">
                            <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
                                <h2 class="text-lg text-white font-semibold mb-3">Reset Password</h2>
                                <div x-show="passwordMessage" class="mb-3 rounded-md border border-emerald-500/40 bg-emerald-900/20 px-3 py-2 text-sm text-emerald-200" x-text="passwordMessage"></div>
                                <div x-show="passwordError" class="mb-3 rounded-md border border-rose-500/40 bg-rose-900/20 px-3 py-2 text-sm text-rose-200" x-text="passwordError"></div>
                                <form @submit.prevent="resetPassword()" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">New Password</label>
                                        <input type="password" x-model="passwordForm.password"
                                               class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                        <p class="text-xs text-rose-300 mt-1" x-show="passwordErrors.password" x-text="passwordErrors.password"></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-300 mb-1">Confirm Password</label>
                                        <input type="password" x-model="passwordForm.password_confirm"
                                               class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                        <p class="text-xs text-rose-300 mt-1" x-show="passwordErrors.password_confirm" x-text="passwordErrors.password_confirm"></p>
                                    </div>
                                    <div>
                                        <button type="submit"
                                                :disabled="resettingPassword"
                                                class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500 disabled:opacity-60 disabled:cursor-not-allowed">
                                            <span x-show="!resettingPassword">Update Password</span>
                                            <span x-show="resettingPassword">Updating...</span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="rounded-xl border border-rose-800/60 bg-rose-950/20 p-5">
                                <h2 class="text-lg text-rose-200 font-semibold mb-2">Delete User</h2>
                                <p class="text-sm text-rose-300/90 mb-4">This permanently removes the username record.</p>
                                <div x-show="deleteError" class="mb-3 rounded-md border border-rose-500/40 bg-rose-900/20 px-3 py-2 text-sm text-rose-200" x-text="deleteError"></div>
                                <button type="button"
                                        @click="deleteUser()"
                                        :disabled="deleting"
                                        class="px-4 py-2 rounded-md bg-rose-700 text-white text-sm font-semibold hover:bg-rose-600 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span x-show="!deleting">Delete User</span>
                                    <span x-show="deleting">Deleting...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

{literal}
<script>
function backupUserDetailApp() {
    return {
        userId: {/literal}{$user->id|intval}{literal},
        isMspClient: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        tenants: {/literal}{$tenants|@json_encode nofilter}{literal} || [],
        loading: true,
        updating: false,
        resettingPassword: false,
        deleting: false,
        tenantSearch: '',
        user: {
            id: {/literal}{$user->id|intval}{literal},
            username: {/literal}{$user->username|@json_encode nofilter}{literal},
            email: {/literal}{$user->email|@json_encode nofilter}{literal},
            tenant_id: {/literal}{$user->tenant_id|@json_encode nofilter}{literal},
            tenant_name: {/literal}{$user->tenant_name|@json_encode nofilter}{literal},
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
            if (!query) return this.tenants;
            return this.tenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(query));
        },

        updateTenantLabel() {
            if (!this.updateForm.tenant_id) return 'Direct (No Tenant)';
            const tenant = this.tenants.find((item) => String(item.id) === String(this.updateForm.tenant_id));
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
            this.updateForm.tenant_id = this.user.tenant_id ? String(this.user.tenant_id) : '';
            this.updateForm.status = this.user.status || 'active';
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
                if (this.isMspClient) {
                    body.set('tenant_id', this.updateForm.tenant_id);
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

