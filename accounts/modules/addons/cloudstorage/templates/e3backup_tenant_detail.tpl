<div class="min-h-screen bg-slate-950 text-gray-200" x-data="tenantDetailApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="tenants"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1 text-sm">
                        <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white">e3 Cloud Backup</a>
                        <span class="text-slate-600">/</span>
                        <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="text-slate-400 hover:text-white">Tenants</a>
                        <span class="text-slate-600">/</span>
                        <span class="text-white font-medium" x-text="isCreateMode ? 'Create Tenant' : (profileForm.name || 'Tenant Details')"></span>
                    </div>
                    <h1 class="text-2xl font-semibold text-white" x-text="isCreateMode ? 'Create Tenant' : 'Tenant Details'"></h1>
                    <p class="text-xs text-slate-400 mt-1">Manage tenant profile, tenant members, and backup users from one place.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="index.php?m=cloudstorage&page=e3backup&view=tenants"
                       class="px-3 py-2 rounded-md border border-slate-700 bg-slate-900/70 text-sm text-slate-200 hover:bg-slate-800">
                        Back to Tenants
                    </a>
                    <button type="button"
                            x-show="!isCreateMode && tenantId"
                            @click="deleteTenant()"
                            class="px-3 py-2 rounded-md border border-rose-700 bg-rose-900/30 text-sm font-medium text-rose-200 hover:border-rose-500 hover:bg-rose-900/45">
                        Delete Tenant
                    </button>
                </div>
            </div>

            <template x-if="loadingTenant">
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-10 text-center text-slate-400">
                    <svg class="animate-spin h-8 w-8 mx-auto text-violet-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading tenant details...
                </div>
            </template>

            <template x-if="!loadingTenant && tenantNotFound">
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-10 text-center">
                    <p class="text-slate-300 mb-3">Tenant not found for this account.</p>
                    <a href="index.php?m=cloudstorage&page=e3backup&view=tenants"
                       class="inline-flex px-4 py-2 rounded-md bg-violet-600 text-white text-sm font-semibold hover:bg-violet-500">
                        Return to Tenants
                    </a>
                </div>
            </template>

            <template x-if="!loadingTenant && !tenantNotFound">
                <div>
                    <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Tenant Details Tabs">
                        <button type="button"
                                @click="activeTab = 'profile'"
                                class="px-4 py-1.5 rounded-full transition"
                                :class="activeTab === 'profile' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'">
                            Profile
                        </button>
                        <button type="button"
                                @click="activeTab = 'tenant_members'"
                                class="px-4 py-1.5 rounded-full transition"
                                :class="activeTab === 'tenant_members' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'">
                            Tenant Members
                        </button>
                        <button type="button"
                                @click="activeTab = 'backup_users'"
                                class="px-4 py-1.5 rounded-full transition"
                                :class="activeTab === 'backup_users' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'">
                            Backup Users
                        </button>
                        <button type="button"
                                @click="activeTab = 'policies'"
                                class="px-4 py-1.5 rounded-full transition"
                                :class="activeTab === 'policies' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'">
                            Policies
                        </button>
                    </nav>

                    <div class="mt-6">
                        <div x-show="activeTab === 'profile'">
                            <form @submit.prevent="saveProfile()" class="space-y-6">
                                <div x-show="profileError" class="rounded-md border border-rose-500/40 bg-rose-900/20 px-3 py-2 text-sm text-rose-200" x-text="profileError"></div>

                                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                                    <h3 class="text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4">Organization</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Company Name <span class="text-rose-400">*</span></label>
                                            <input type="text" x-model.trim="profileForm.name" required placeholder="Acme Corporation" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Slug <span class="text-slate-500 font-normal" x-show="!isCreateMode">(read-only)</span></label>
                                            <input type="text" x-model.trim="profileForm.slug" :disabled="!isCreateMode" placeholder="acme-corp" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-60 font-mono">
                                            <p class="text-xs text-slate-500 mt-1">URL-friendly identifier. Cannot be changed after creation.</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                                            <select x-model="profileForm.status" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                                <option value="active">Active</option>
                                                <option value="suspended">Suspended</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                                    <h3 class="text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4">Contact Information</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Contact Email <span class="text-rose-400">*</span></label>
                                            <input type="email" x-model.trim="profileForm.contact_email" required placeholder="billing@acme.com" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Contact Name <span class="text-rose-400">*</span></label>
                                            <input type="text" x-model.trim="profileForm.contact_name" required placeholder="Jane Smith" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Phone</label>
                                            <input type="tel" x-model.trim="profileForm.contact_phone" placeholder="+1 (555) 123-4567" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                                    <h3 class="text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4">Billing Address <span class="text-slate-500 font-normal">(optional)</span></h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Address Line 1</label>
                                            <input type="text" x-model.trim="profileForm.address_line1" placeholder="123 Main Street" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Address Line 2</label>
                                            <input type="text" x-model.trim="profileForm.address_line2" placeholder="Suite 100" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">City</label>
                                            <input type="text" x-model.trim="profileForm.city" placeholder="Toronto" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">State / Province</label>
                                            <input type="text" x-model.trim="profileForm.state" placeholder="Ontario" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Postal Code</label>
                                            <input type="text" x-model.trim="profileForm.postal_code" placeholder="M5V 1A1" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Country Code</label>
                                            <input type="text" x-model.trim="profileForm.country" maxlength="2" placeholder="CA" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 uppercase">
                                            <p class="text-xs text-slate-500 mt-1">2-letter ISO code (e.g., CA, US, GB)</p>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="isCreateMode" class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
                                    <h3 class="text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4">Portal Admin Account</h3>

                                    <label class="flex items-center gap-2 text-sm text-slate-300 mb-4 cursor-pointer">
                                        <input type="checkbox" x-model="profileForm.create_admin" class="rounded bg-slate-800 border-slate-600 text-violet-600 focus:ring-violet-500">
                                        Create portal admin user for this tenant
                                    </label>

                                    <div x-show="profileForm.create_admin" x-cloak class="space-y-4 pl-4 border-l border-violet-600/30">
                                        <p class="text-xs text-slate-400">Creates a tenant member that can log in to the tenant portal.</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-slate-300 mb-1">Admin Email</label>
                                                <input type="email" x-model.trim="profileForm.admin_email" :placeholder="profileForm.contact_email || 'admin@acme.com'" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-slate-300 mb-1">Admin Name</label>
                                                <input type="text" x-model.trim="profileForm.admin_name" :placeholder="profileForm.contact_name || 'Admin User'" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                                                <input type="radio" x-model="profileForm.auto_password" value="1" class="bg-slate-800 border-slate-600 text-violet-600 focus:ring-violet-500">
                                                Auto-generate password and email to user
                                            </label>
                                            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                                                <input type="radio" x-model="profileForm.auto_password" value="0" class="bg-slate-800 border-slate-600 text-violet-600 focus:ring-violet-500">
                                                Set password manually
                                            </label>
                                        </div>

                                        <div x-show="profileForm.auto_password === '0'" x-cloak>
                                            <label class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                                            <input type="password" x-model="profileForm.admin_password" minlength="8" placeholder="Minimum 8 characters" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 pt-2">
                                    <button type="button"
                                            @click="window.location.href='index.php?m=cloudstorage&page=e3backup&view=tenants'"
                                            class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            :disabled="savingProfile"
                                            class="px-4 py-2 rounded-md bg-violet-600 text-white text-sm font-semibold hover:bg-violet-500 disabled:opacity-60 disabled:cursor-not-allowed">
                                        <span x-show="!savingProfile" x-text="isCreateMode ? 'Create Tenant' : 'Save Changes'"></span>
                                        <span x-show="savingProfile" x-text="isCreateMode ? 'Creating...' : 'Saving...'"></span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div x-show="activeTab === 'tenant_members'" x-cloak>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">Tenant Members</h3>
                                    <p class="text-xs text-slate-400">Portal access accounts for this tenant.</p>
                                </div>
                                <button type="button"
                                        @click="openCreateMemberModal()"
                                        class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500">
                                    + Create Member
                                </button>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-slate-800">
                                <table class="min-w-full divide-y divide-slate-800 text-sm">
                                    <thead class="bg-slate-900/80 text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium">Name</th>
                                            <th class="px-4 py-3 text-left font-medium">Email</th>
                                            <th class="px-4 py-3 text-left font-medium">Role</th>
                                            <th class="px-4 py-3 text-left font-medium">Status</th>
                                            <th class="px-4 py-3 text-left font-medium">Last Login</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800">
                                        <template x-if="loadingMembers">
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">Loading tenant members...</td>
                                            </tr>
                                        </template>
                                        <template x-if="!loadingMembers && members.length === 0">
                                            <tr>
                                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">No tenant members found.</td>
                                            </tr>
                                        </template>
                                        <template x-for="member in members" :key="'member-' + member.id">
                                            <tr class="hover:bg-slate-800/40">
                                                <td class="px-4 py-3 text-slate-100" x-text="member.name"></td>
                                                <td class="px-4 py-3 text-slate-300" x-text="member.email"></td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                          :class="member.role === 'admin' ? 'bg-violet-500/15 text-violet-200' : 'bg-slate-700 text-slate-300'"
                                                          x-text="member.role"></span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                                          :class="member.status === 'active' ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-700 text-slate-300'">
                                                        <span class="h-1.5 w-1.5 rounded-full" :class="member.status === 'active' ? 'bg-emerald-400' : 'bg-slate-500'"></span>
                                                        <span x-text="member.status"></span>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-slate-300" x-text="member.last_login_at || 'Never'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div x-show="activeTab === 'backup_users'" x-cloak>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">Backup Users</h3>
                                    <p class="text-xs text-slate-400">Backup identities scoped to this tenant.</p>
                                </div>
                                <button type="button"
                                        @click="openCreateModal()"
                                        class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500">
                                    + Create Backup User
                                </button>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-slate-800">
                                <table class="min-w-full divide-y divide-slate-800 text-sm">
                                    <thead class="bg-slate-900/80 text-slate-300">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium">Username</th>
                                            <th class="px-4 py-3 text-left font-medium">Email</th>
                                            <th class="px-4 py-3 text-left font-medium"># Vaults</th>
                                            <th class="px-4 py-3 text-left font-medium"># Jobs</th>
                                            <th class="px-4 py-3 text-left font-medium"># Agents</th>
                                            <th class="px-4 py-3 text-left font-medium">Last Backup</th>
                                            <th class="px-4 py-3 text-left font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800">
                                        <template x-if="loadingBackupUsers">
                                            <tr>
                                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">Loading backup users...</td>
                                            </tr>
                                        </template>
                                        <template x-if="!loadingBackupUsers && backupUsers.length === 0">
                                            <tr>
                                                <td colspan="7" class="px-4 py-8 text-center text-slate-400">No backup users found.</td>
                                            </tr>
                                        </template>
                                        <template x-for="user in backupUsers" :key="'backup-user-' + user.id">
                                            <tr class="hover:bg-slate-800/40">
                                                <td class="px-4 py-3">
                                                    <div class="font-medium text-slate-100" x-text="user.username"></div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-300" x-text="user.email"></td>
                                                <td class="px-4 py-3 text-slate-300" x-text="user.vaults_count"></td>
                                                <td class="px-4 py-3 text-slate-300" x-text="user.jobs_count"></td>
                                                <td class="px-4 py-3 text-slate-300" x-text="user.agents_count"></td>
                                                <td class="px-4 py-3 text-slate-300" x-text="formatDate(user.last_backup_at)"></td>
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
                        </div>

                        <div x-show="activeTab === 'policies'" x-cloak>
                            <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                                <h3 class="text-lg font-semibold text-white mb-2">Policies</h3>
                                <p class="text-sm text-slate-300 mb-4">Tenant-level policy controls are staged for this workspace. Use this tab as the dedicated policy management area.</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-lg border border-slate-800 bg-slate-950/70 p-4">
                                        <p class="text-slate-300 font-medium">Backup Retention</p>
                                        <p class="text-slate-500 text-xs mt-1">Define tenant defaults for retention windows and cleanup strategy.</p>
                                    </div>
                                    <div class="rounded-lg border border-slate-800 bg-slate-950/70 p-4">
                                        <p class="text-slate-300 font-medium">Restore Permissions</p>
                                        <p class="text-slate-500 text-xs mt-1">Control which member roles can initiate restores and recovery actions.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="notification.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg"
         :class="notification.type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'">
        <div class="flex items-center gap-2 text-white text-sm">
            <span x-text="notification.message"></span>
        </div>
    </div>

    <div x-show="showCreateMemberModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="closeCreateMemberModal()">
        <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Create Member</h3>
                <button type="button" @click="closeCreateMemberModal()" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form @submit.prevent="createMember()" class="p-6 space-y-4">
                <div x-show="memberFormError" class="rounded-md border border-rose-500/40 bg-rose-900/20 px-3 py-2 text-sm text-rose-200" x-text="memberFormError"></div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Name <span class="text-rose-400">*</span></label>
                    <input type="text" x-model.trim="memberForm.name" required placeholder="Jane Smith" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Email <span class="text-rose-400">*</span></label>
                    <input type="email" x-model.trim="memberForm.email" required placeholder="jane@example.com" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Password <span class="text-rose-400">*</span></label>
                    <input type="password" x-model="memberForm.password" required minlength="8" placeholder="Minimum 8 characters" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Role</label>
                        <select x-model="memberForm.role" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                        <select x-model="memberForm.status" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="closeCreateMemberModal()" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">Cancel</button>
                    <button type="submit" :disabled="memberSaving" class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span x-show="!memberSaving">Create Member</span>
                        <span x-show="memberSaving">Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {include file="modules/addons/cloudstorage/templates/partials/e3backup_create_user_modal.tpl"
        modalTitle="Create Backup User"
        submitLabel="Create Backup User"
        submittingLabel="Creating..."
        showTenantSelector=true
        lockTenantField=true}
</div>

{literal}
<script>
function tenantDetailApp() {
    return {
        tenantId: null,
        isMspClient: true,
        isCreateMode: false,
        loadingTenant: true,
        tenantNotFound: false,
        activeTab: 'profile',

        savingProfile: false,
        deletingTenant: false,
        profileError: '',

        loadingMembers: false,
        loadingBackupUsers: false,
        membersLoaded: false,
        backupUsersLoaded: false,
        members: [],
        backupUsers: [],

        showCreateMemberModal: false,
        showCreateModal: false,
        memberSaving: false,
        saving: false,
        memberFormError: '',
        formErrorMessage: '',
        fieldErrors: {},
        tenantAssignSearch: '',
        tenants: [],

        notification: { show: false, message: '', type: 'success' },

        profileForm: {
            name: '',
            slug: '',
            status: 'active',
            contact_email: '',
            contact_name: '',
            contact_phone: '',
            address_line1: '',
            address_line2: '',
            city: '',
            state: '',
            postal_code: '',
            country: '',
            create_admin: true,
            admin_email: '',
            admin_name: '',
            admin_password: '',
            auto_password: '1'
        },

        memberForm: {
            name: '',
            email: '',
            password: '',
            role: 'user',
            status: 'active'
        },

        form: {
            username: '',
            email: '',
            password: '',
            password_confirm: '',
            status: 'active',
            tenant_id: '',
            encryption_mode: 'managed',
            managed_acknowledged: false,
            strict_acknowledged: false,
            recovery_key_downloaded: false
        },

        init() {
            const params = new URLSearchParams(window.location.search);
            const tenantIdRaw = parseInt(params.get('tenant_id') || '', 10);
            this.tenantId = Number.isFinite(tenantIdRaw) && tenantIdRaw > 0 ? tenantIdRaw : null;
            this.isCreateMode = (params.get('mode') || '').toLowerCase() === 'create';

            this.$watch('profileForm.name', (value) => {
                if (this.isCreateMode) {
                    this.profileForm.slug = String(value || '')
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-|-$/g, '');
                }
            });

            this.$watch('activeTab', (tab) => {
                if (tab === 'tenant_members') {
                    this.loadMembers();
                } else if (tab === 'backup_users') {
                    this.loadBackupUsers();
                }
            });

            if (this.isCreateMode) {
                this.loadingTenant = false;
                return;
            }

            if (!this.tenantId) {
                this.tenantNotFound = true;
                this.loadingTenant = false;
                return;
            }

            this.loadTenant();
        },

        populateFormFromTenant(tenant) {
            this.profileForm = {
                name: tenant.name || '',
                slug: tenant.slug || '',
                status: tenant.status || 'active',
                contact_email: tenant.contact_email || '',
                contact_name: tenant.contact_name || '',
                contact_phone: tenant.contact_phone || '',
                address_line1: tenant.address_line1 || '',
                address_line2: tenant.address_line2 || '',
                city: tenant.city || '',
                state: tenant.state || '',
                postal_code: tenant.postal_code || '',
                country: tenant.country || '',
                create_admin: false,
                admin_email: '',
                admin_name: '',
                admin_password: '',
                auto_password: '1'
            };
        },

        async loadTenant() {
            this.loadingTenant = true;
            this.tenantNotFound = false;
            try {
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_list.php');
                const data = await response.json();
                if (data.status !== 'success') {
                    this.tenantNotFound = true;
                    return;
                }

                const tenants = Array.isArray(data.tenants) ? data.tenants : [];
                const tenant = tenants.find((item) => Number(item.id) === Number(this.tenantId));
                if (!tenant) {
                    this.tenantNotFound = true;
                    return;
                }

                this.populateFormFromTenant(tenant);
                this.tenants = [{ id: String(tenant.id), name: tenant.name || ('Tenant #' + tenant.id) }];
            } catch (error) {
                this.tenantNotFound = true;
            } finally {
                this.loadingTenant = false;
            }
        },

        validateProfileForm() {
            if (!this.profileForm.name.trim()) {
                this.profileError = 'Company name is required.';
                return false;
            }
            if (!this.profileForm.contact_email.trim()) {
                this.profileError = 'Contact email is required.';
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.profileForm.contact_email.trim())) {
                this.profileError = 'Please enter a valid contact email.';
                return false;
            }
            if (!this.profileForm.contact_name.trim()) {
                this.profileError = 'Contact name is required.';
                return false;
            }
            if (this.profileForm.country && !/^[A-Za-z]{2}$/.test(this.profileForm.country.trim())) {
                this.profileError = 'Country must be a 2-letter ISO code.';
                return false;
            }
            if (this.isCreateMode && this.profileForm.create_admin && this.profileForm.auto_password === '0') {
                if (String(this.profileForm.admin_password || '').length < 8) {
                    this.profileError = 'Admin password must be at least 8 characters when set manually.';
                    return false;
                }
            }
            return true;
        },

        async saveProfile() {
            this.profileError = '';
            if (!this.validateProfileForm()) {
                return;
            }

            this.savingProfile = true;
            try {
                const endpoint = this.isCreateMode
                    ? 'modules/addons/cloudstorage/api/e3backup_tenant_create.php'
                    : 'modules/addons/cloudstorage/api/e3backup_tenant_update.php';

                const body = new URLSearchParams({
                    name: this.profileForm.name,
                    slug: this.profileForm.slug,
                    status: this.profileForm.status,
                    contact_email: this.profileForm.contact_email,
                    contact_name: this.profileForm.contact_name,
                    contact_phone: this.profileForm.contact_phone,
                    address_line1: this.profileForm.address_line1,
                    address_line2: this.profileForm.address_line2,
                    city: this.profileForm.city,
                    state: this.profileForm.state,
                    postal_code: this.profileForm.postal_code,
                    country: (this.profileForm.country || '').toUpperCase()
                });

                if (!this.isCreateMode) {
                    body.set('tenant_id', String(this.tenantId));
                } else {
                    body.set('create_admin', this.profileForm.create_admin ? '1' : '0');
                    if (this.profileForm.create_admin) {
                        body.set('admin_email', this.profileForm.admin_email || '');
                        body.set('admin_name', this.profileForm.admin_name || '');
                        body.set('auto_password', this.profileForm.auto_password || '1');
                        if (this.profileForm.auto_password === '0') {
                            body.set('admin_password', this.profileForm.admin_password || '');
                        }
                    }
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();

                if (data.status !== 'success') {
                    this.profileError = data.message || 'Failed to save tenant profile.';
                    return;
                }

                if (this.isCreateMode && data.tenant_id) {
                    window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=tenant_detail&tenant_id=' + encodeURIComponent(data.tenant_id);
                    return;
                }

                await this.loadTenant();
                this.showNotification('Tenant profile updated successfully.', 'success');
            } catch (error) {
                this.profileError = 'Failed to save tenant profile.';
            } finally {
                this.savingProfile = false;
            }
        },

        async deleteTenant() {
            if (!this.tenantId || this.isCreateMode) return;
            if (this.deletingTenant) return;

            const confirmed = window.confirm('Delete tenant "' + (this.profileForm.name || '') + '"? This action cannot be undone.');
            if (!confirmed) return;

            this.deletingTenant = true;
            try {
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ tenant_id: String(this.tenantId) })
                });
                const data = await response.json();
                if (data.status !== 'success') {
                    this.showNotification(data.message || 'Failed to delete tenant.', 'error');
                    return;
                }
                window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=tenants';
            } catch (error) {
                this.showNotification('Failed to delete tenant.', 'error');
            } finally {
                this.deletingTenant = false;
            }
        },

        async loadMembers(forceReload = false) {
            if (!this.tenantId || this.isCreateMode) return;
            if (this.membersLoaded && !forceReload) return;

            this.loadingMembers = true;
            try {
                const response = await fetch(
                    'modules/addons/cloudstorage/api/e3backup_tenant_user_list.php?tenant_id=' + encodeURIComponent(this.tenantId)
                );
                const data = await response.json();
                if (data.status === 'success') {
                    this.members = Array.isArray(data.users) ? data.users : [];
                    this.membersLoaded = true;
                } else {
                    this.members = [];
                }
            } catch (error) {
                this.members = [];
            } finally {
                this.loadingMembers = false;
            }
        },

        async loadBackupUsers(forceReload = false) {
            if (!this.tenantId || this.isCreateMode) return;
            if (this.backupUsersLoaded && !forceReload) return;

            this.loadingBackupUsers = true;
            try {
                const response = await fetch(
                    'modules/addons/cloudstorage/api/e3backup_user_list.php?tenant_id=' + encodeURIComponent(this.tenantId)
                );
                const data = await response.json();
                if (data.status === 'success') {
                    this.backupUsers = Array.isArray(data.users) ? data.users : [];
                    this.backupUsersLoaded = true;
                } else {
                    this.backupUsers = [];
                }
            } catch (error) {
                this.backupUsers = [];
            } finally {
                this.loadingBackupUsers = false;
            }
        },

        get filteredAssignTenants() {
            const search = this.tenantAssignSearch.trim().toLowerCase();
            if (!search) return this.tenants;
            return this.tenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(search));
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

        openCreateMemberModal() {
            this.memberForm = {
                name: '',
                email: '',
                password: '',
                role: 'user',
                status: 'active'
            };
            this.memberFormError = '';
            this.showCreateMemberModal = true;
        },

        closeCreateMemberModal() {
            this.showCreateMemberModal = false;
            this.memberSaving = false;
        },

        async createMember() {
            if (!this.tenantId) return;
            this.memberFormError = '';
            this.memberSaving = true;

            try {
                const body = new URLSearchParams({
                    tenant_id: String(this.tenantId),
                    name: this.memberForm.name || '',
                    email: this.memberForm.email || '',
                    password: this.memberForm.password || '',
                    role: this.memberForm.role || 'user',
                    status: this.memberForm.status || 'active'
                });

                const response = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_user_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();
                if (data.status !== 'success') {
                    this.memberFormError = data.message || 'Failed to create member.';
                    return;
                }

                this.closeCreateMemberModal();
                await this.loadMembers(true);
                await this.loadTenant();
                this.showNotification('Tenant member created successfully.', 'success');
            } catch (error) {
                this.memberFormError = 'Failed to create member.';
            } finally {
                this.memberSaving = false;
            }
        },

        openCreateModal() {
            this.form = {
                username: '',
                password: '',
                password_confirm: '',
                email: '',
                tenant_id: String(this.tenantId || ''),
                status: 'active',
                encryption_mode: 'managed',
                managed_acknowledged: false,
                strict_acknowledged: false,
                recovery_key_downloaded: false
            };
            this.formErrorMessage = '';
            this.fieldErrors = {};
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

        async createUser() {
            if (!this.tenantId) return;
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
                    status: this.form.status || 'active',
                    tenant_id: String(this.tenantId),
                    encryption_mode: this.form.encryption_mode,
                    managed_acknowledged: this.form.managed_acknowledged ? '1' : '0',
                    strict_acknowledged: this.form.strict_acknowledged ? '1' : '0',
                    recovery_key_downloaded: this.form.recovery_key_downloaded ? '1' : '0'
                });

                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();
                if (data.status !== 'success') {
                    this.formErrorMessage = data.message || 'Failed to create backup user.';
                    this.fieldErrors = data.errors || {};
                    return;
                }

                this.closeCreateModal();
                await this.loadBackupUsers(true);
                this.showNotification('Backup user created successfully.', 'success');
            } catch (error) {
                this.formErrorMessage = 'Failed to create backup user.';
            } finally {
                this.saving = false;
            }
        },

        formatDate(value) {
            if (!value) return 'Never';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        },

        showNotification(message, type = 'success') {
            this.notification = { show: true, message, type };
            setTimeout(() => {
                this.notification.show = false;
            }, 3500);
        }
    };
}
</script>
{/literal}
