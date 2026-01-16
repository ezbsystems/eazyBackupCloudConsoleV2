<div class="min-h-screen bg-slate-950 text-gray-200" x-data="tenantUsersApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="tenant_users"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        {* Glass panel container *}
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="text-slate-400 hover:text-white text-sm">Tenants</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Users</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Tenant Users</h1>
                <p class="text-xs text-slate-400 mt-1">Manage users who can access the tenant portal to manage backups and perform restores.</p>
            </div>
            <button @click="openCreateModal()" class="mt-4 sm:mt-0 px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500">
                + Create User
            </button>
        </div>

        <!-- Tenant Filter -->
        <div class="mb-4 flex items-center gap-4">
            <label class="text-sm text-slate-400">Tenant:</label>
            <select x-model="tenantFilter" @change="loadUsers()" class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="">All Tenants</option>
                {foreach from=$tenants item=tenant}
                <option value="{$tenant->id}">{$tenant->name|escape}</option>
                {/foreach}
            </select>
        </div>

        <!-- Users Table -->
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Name</th>
                        <th class="px-4 py-3 text-left font-medium">Email</th>
                        <th class="px-4 py-3 text-left font-medium">Tenant</th>
                        <th class="px-4 py-3 text-left font-medium">Role</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Last Login</th>
                        <th class="px-4 py-3 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <template x-if="loading">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && users.length === 0">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                No users found. Click "Create User" to add a tenant user.
                            </td>
                        </tr>
                    </template>
                    <template x-for="user in users" :key="user.id">
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3 text-slate-200" x-text="user.name"></td>
                            <td class="px-4 py-3 text-slate-300" x-text="user.email"></td>
                            <td class="px-4 py-3 text-slate-300" x-text="user.tenant_name"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="user.role === 'admin' ? 'bg-violet-500/15 text-violet-200' : 'bg-slate-700 text-slate-300'"
                                      x-text="user.role"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                      :class="user.status === 'active' ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-700 text-slate-300'">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="user.status === 'active' ? 'bg-emerald-400' : 'bg-slate-500'"></span>
                                    <span x-text="user.status"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="user.last_login_at || 'Never'"></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <button @click="openEditModal(user)" class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 hover:border-slate-500">Edit</button>
                                    <button @click="resetPassword(user)" class="text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 hover:border-slate-500">Reset PW</button>
                                    <button @click="deleteUser(user)" class="text-xs px-2 py-1 rounded bg-rose-900/50 border border-rose-700 hover:border-rose-500 text-rose-200">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="showModal = false">
        <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white" x-text="editingUser ? 'Edit User' : 'Create User'"></h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form @submit.prevent="saveUser()" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Tenant <span class="text-rose-400">*</span></label>
                    <select x-model="form.tenant_id" required :disabled="editingUser" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:opacity-50">
                        <option value="">Select Tenant</option>
                        {foreach from=$tenants item=tenant}
                        <option value="{$tenant->id}">{$tenant->name|escape}</option>
                        {/foreach}
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Name <span class="text-rose-400">*</span></label>
                    <input type="text" x-model="form.name" required placeholder="John Smith" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Email <span class="text-rose-400">*</span></label>
                    <input type="email" x-model="form.email" required placeholder="john@example.com" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>

                <template x-if="!editingUser">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Password <span class="text-rose-400">*</span></label>
                        <input type="password" x-model="form.password" required minlength="8" placeholder="Minimum 8 characters" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                </template>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Role</label>
                    <select x-model="form.role" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Admins can manage other users within their tenant.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                    <select x-model="form.status" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="showModal = false" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500" :disabled="saving">
                        <span x-show="!saving" x-text="editingUser ? 'Save Changes' : 'Create User'"></span>
                        <span x-show="saving">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div x-show="showPasswordModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="showPasswordModal = false">
        <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Reset Password</h3>
                <button @click="showPasswordModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form @submit.prevent="doResetPassword()" class="p-6 space-y-4">
                <p class="text-sm text-slate-400">Set a new password for <strong x-text="passwordUser?.name" class="text-white"></strong>.</p>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">New Password <span class="text-rose-400">*</span></label>
                    <input type="password" x-model="newPassword" required minlength="8" placeholder="Minimum 8 characters" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
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
    </div>
</div>

{literal}
<script>
function tenantUsersApp() {
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
        form: {
            tenant_id: '',
            name: '',
            email: '',
            password: '',
            role: 'user',
            status: 'active'
        },
        
        init() {
            // Pre-select tenant from URL if present
            if (this.tenantFilter) {
                this.form.tenant_id = this.tenantFilter;
            }
            this.loadUsers();
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
            this.editingUser = null;
            this.form = { 
                tenant_id: this.tenantFilter || '', 
                name: '', 
                email: '', 
                password: '', 
                role: 'user', 
                status: 'active' 
            };
            this.showModal = true;
        },
        
        openEditModal(user) {
            this.editingUser = user;
            this.form = { 
                tenant_id: user.tenant_id, 
                name: user.name, 
                email: user.email, 
                password: '',
                role: user.role, 
                status: user.status 
            };
            this.showModal = true;
        },
        
        async saveUser() {
            this.saving = true;
            try {
                const endpoint = this.editingUser 
                    ? 'modules/addons/cloudstorage/api/e3backup_tenant_user_update.php'
                    : 'modules/addons/cloudstorage/api/e3backup_tenant_user_create.php';
                
                const params = new URLSearchParams({
                    tenant_id: this.form.tenant_id,
                    name: this.form.name,
                    email: this.form.email,
                    role: this.form.role,
                    status: this.form.status
                });
                
                if (!this.editingUser) {
                    params.set('password', this.form.password);
                } else {
                    params.set('user_id', this.editingUser.id);
                }
                
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showModal = false;
                    this.loadUsers();
                } else {
                    alert(data.message || 'Failed to save user');
                }
            } catch (e) {
                alert('Failed to save user');
            }
            this.saving = false;
        },
        
        resetPassword(user) {
            this.passwordUser = user;
            this.newPassword = '';
            this.showPasswordModal = true;
        },
        
        async doResetPassword() {
            this.resettingPassword = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_user_reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        user_id: this.passwordUser.id,
                        password: this.newPassword
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showPasswordModal = false;
                    alert('Password updated successfully');
                } else {
                    alert(data.message || 'Failed to reset password');
                }
            } catch (e) {
                alert('Failed to reset password');
            }
            this.resettingPassword = false;
        },
        
        async deleteUser(user) {
            if (!confirm(`Delete user ${user.name}? This cannot be undone.`)) return;
            
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_user_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ user_id: user.id })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.loadUsers();
                } else {
                    alert(data.message || 'Failed to delete user');
                }
            } catch (e) {
                alert('Failed to delete user');
            }
        }
    };
}
</script>
{/literal}

