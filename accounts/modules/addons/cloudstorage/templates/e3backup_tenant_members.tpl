{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="eb-page" x-data="tenantMembersApp()">
    <div class="eb-page-inner">
        {assign var="activeNav" value="tenant_members"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="eb-panel">
            <div class="eb-page-header">
                <div>
                    <div class="eb-breadcrumb">
                        <a href="index.php?m=cloudstorage&page=e3backup" class="eb-breadcrumb-link">e3 Cloud Backup</a>
                        <span class="eb-breadcrumb-separator">/</span>
                        <a href="index.php?m=eazybackup&a=ph-tenants-manage" class="eb-breadcrumb-link">Partner Hub Tenants</a>
                        <span class="eb-breadcrumb-separator">/</span>
                        <span class="eb-breadcrumb-current">Tenant Members</span>
                    </div>
                    <h1 class="eb-page-title">Tenant Members</h1>
                    <p class="eb-page-description">Manage members who can access the tenant portal to manage backups and perform restores.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="openPartnerHub('members')" class="eb-btn eb-btn-primary eb-btn-sm">
                        Manage in Partner Hub
                    </button>
                </div>
            </div>

            <div class="eb-alert eb-alert--warning mb-5">
                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div>
                    <div class="eb-alert-title">Legacy compatibility view</div>
                    <p>This page remains available for bookmarked legacy URLs. Member create/edit/reset/delete actions are now handled only in Partner Hub to avoid divergent tenant writes.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="index.php?m=eazybackup&a=ph-tenants-manage" class="eb-btn eb-btn-secondary eb-btn-xs">
                            Partner Hub Tenant List
                        </a>
                        <button type="button" @click="openPartnerHub('members')" class="eb-btn eb-btn-secondary eb-btn-xs">
                            Partner Hub Tenant Members
                        </button>
                    </div>
                </div>
            </div>

            <div class="eb-table-toolbar">
                <label class="eb-field-label" style="margin-bottom: 0;">Tenant</label>
                <select x-model="tenantFilter" @change="syncTenantFilterToUrl(); loadUsers()" class="eb-select w-full max-w-xs">
                    <option value="">All Tenants</option>
                    {foreach from=$tenants item=tenant}
                    <option value="{$tenant->public_id|escape}">{$tenant->name|escape}</option>
                    {/foreach}
                </select>
            </div>

            <div class="eb-table-shell">
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Tenant</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Canonical Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td colspan="7">
                                    <div class="eb-app-empty">
                                        <p class="eb-app-empty-copy">Loading members...</p>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && users.length === 0">
                            <tr>
                                <td colspan="7">
                                    <div class="eb-app-empty">
                                        <div class="eb-app-empty-title">No members found</div>
                                        <p class="eb-app-empty-copy">Use Partner Hub to add a tenant member.</p>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-for="user in users" :key="user.id">
                            <tr>
                                <td class="eb-table-primary" x-text="user.name"></td>
                                <td x-text="user.email"></td>
                                <td x-text="user.tenant_name"></td>
                                <td>
                                    <span class="eb-badge"
                                          :class="user.role === 'admin' ? 'eb-badge--premium' : 'eb-badge--neutral'"
                                          x-text="user.role"></span>
                                </td>
                                <td>
                                    <span class="eb-badge eb-badge--dot"
                                          :class="user.status === 'active' ? 'eb-badge--success' : 'eb-badge--neutral'"
                                          x-text="user.status"></span>
                                </td>
                                <td x-text="user.last_login_at || 'Never'"></td>
                                <td>
                                    <button type="button" @click="openPartnerHubForUser(user, 'members')" class="eb-btn eb-btn-secondary eb-btn-xs">
                                        Open in Partner Hub
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm" @click.self="showModal = false">
        <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white" x-text="editingUser ? 'Edit Member' : 'Create Member'"></h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form @submit.prevent="saveUser()" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Tenant <span class="text-rose-400">*</span></label>
                    <select x-model="form.tenant_id" required :disabled="editingUser" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="">Select Tenant</option>
                        {foreach from=$tenants item=tenant}
                        <option value="{$tenant->public_id|escape}">{$tenant->name|escape}</option>
                        {/foreach}
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Name <span class="text-rose-400">*</span></label>
                    <input type="text" x-model="form.name" required placeholder="John Smith" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Email <span class="text-rose-400">*</span></label>
                    <input type="email" x-model="form.email" required placeholder="john@example.com" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
                </div>

                <template x-if="!editingUser">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Password <span class="text-rose-400">*</span></label>
                        <input type="password" x-model="form.password" required minlength="8" placeholder="Minimum 8 characters" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
                    </div>
                </template>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Role</label>
                    <select x-model="form.role" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Admins can manage other members within their tenant.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                    <select x-model="form.status" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="showModal = false" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500" :disabled="saving">
                        <span x-show="!saving" x-text="editingUser ? 'Save Changes' : 'Create Member'"></span>
                        <span x-show="saving">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showPasswordModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm" @click.self="showPasswordModal = false">
        <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Reset Password</h3>
                <button @click="showPasswordModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form @submit.prevent="doResetPassword()" class="p-6 space-y-4">
                <p class="text-sm text-slate-400">Set a new password for <strong x-text="passwordUser?.name" class="text-white"></strong>.</p>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">New Password <span class="text-rose-400">*</span></label>
                    <input type="password" x-model="newPassword" required minlength="8" placeholder="Minimum 8 characters" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition">
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="showPasswordModal = false" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500" :disabled="resettingPassword">
                        <span x-show="!resettingPassword">Reset Password</span>
                        <span x-show="resettingPassword">Resetting...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{literal}
<script>
function tenantMembersApp() {
    return {
        users: [],
        loading: true,
        showModal: false,
        showPasswordModal: false,
        editingUser: null,
        passwordUser: null,
        saving: false,
        resettingPassword: false,
        newPassword: '',
        tenantFilter: new URLSearchParams(window.location.search).get('tenant_id') || '',
        csrfToken: {/literal}{$csrfToken|@json_encode nofilter}{literal} || '',
        form: {
            tenant_id: '',
            name: '',
            email: '',
            password: '',
            role: 'user',
            status: 'active'
        },

        init() {
            if (this.tenantFilter) {
                this.form.tenant_id = this.tenantFilter;
            }
            this.loadUsers();
        },

        openPartnerHub(target = 'members', tenantId = '') {
            const resolvedTenantId = String(tenantId || this.tenantFilter || '').trim();
            if (!resolvedTenantId) {
                window.location.href = 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-members';
                return;
            }

            if (target === 'profile') {
                window.location.href = 'index.php?m=eazybackup&a=ph-tenant&id=' + encodeURIComponent(resolvedTenantId) + '&legacy=e3-tenant-members';
                return;
            }

            if (target === 'storage_users') {
                window.location.href = 'index.php?m=eazybackup&a=ph-tenant-storage-users&id=' + encodeURIComponent(resolvedTenantId) + '&legacy=e3-tenant-members';
                return;
            }

            window.location.href = 'index.php?m=eazybackup&a=ph-tenant-members&id=' + encodeURIComponent(resolvedTenantId) + '&legacy=e3-tenant-members';
        },

        openPartnerHubForUser(user, target = 'members') {
            const tenantId = user && user.tenant_id ? user.tenant_id : this.tenantFilter;
            this.openPartnerHub(target, tenantId);
        },

        syncTenantFilterToUrl() {
            try {
                const nextUrl = new URL(window.location.href);
                if (this.tenantFilter) {
                    nextUrl.searchParams.set('tenant_id', this.tenantFilter);
                } else {
                    nextUrl.searchParams.delete('tenant_id');
                }
                history.replaceState({}, '', nextUrl.toString());
            } catch (e) {
                // Keep list behavior functional even if URL mutation fails.
            }
        },

        async loadUsers() {
            this.loading = true;
            try {
                let url = 'modules/addons/cloudstorage/api/e3backup_tenant_user_list.php';
                if (this.tenantFilter) {
                    url += '?tenant_id=' + encodeURIComponent(this.tenantFilter);
                }
                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    this.users = data.users || [];
                }
            } catch (e) {
                console.error('Failed to load users:', e);
            }
            this.loading = false;
        },

        openCreateModal() {
            this.openPartnerHub('members');
        },

        openEditModal(user) {
            this.openPartnerHubForUser(user, 'members');
        },

        async saveUser() {
            this.saving = true;
            this.openPartnerHub('members', this.form.tenant_id || this.tenantFilter);
        },

        resetPassword(user) {
            this.openPartnerHubForUser(user, 'members');
        },

        async doResetPassword() {
            this.resettingPassword = true;
            this.openPartnerHubForUser(this.passwordUser, 'members');
        },

        async deleteUser(user) {
            this.openPartnerHubForUser(user, 'members');
        }
    };
}
</script>
{/literal}
