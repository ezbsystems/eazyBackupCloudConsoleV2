<div class="min-h-screen bg-slate-950 text-gray-200" x-data="tenantsApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Tenants</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Tenants</h1>
                <p class="text-xs text-slate-400 mt-1">Manage your customer organizations. Each tenant has isolated storage and their own users.</p>
            </div>
            <button @click="openCreateModal()" class="mt-4 sm:mt-0 px-4 py-2 rounded-md bg-violet-600 text-white text-sm font-semibold hover:bg-violet-500">
                + Create Tenant
            </button>
        </div>

        <!-- Tenants Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-if="loading">
                <div class="col-span-full text-center py-12 text-slate-400">
                    <svg class="animate-spin h-8 w-8 mx-auto text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </template>
            <template x-if="!loading && tenants.length === 0">
                <div class="col-span-full rounded-xl border border-slate-800 bg-slate-900/70 p-8 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto text-slate-600 mb-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                    <p class="text-slate-400 mb-4">No tenants yet. Create your first tenant to start managing customer backups.</p>
                    <button @click="openCreateModal()" class="px-4 py-2 rounded-md bg-violet-600 text-white text-sm font-semibold hover:bg-violet-500">
                        Create First Tenant
                    </button>
                </div>
            </template>
            <template x-for="tenant in tenants" :key="tenant.id">
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-5 hover:border-violet-500/50 transition-colors">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-semibold text-white" x-text="tenant.name"></h3>
                            <p class="text-xs text-slate-500 font-mono" x-text="tenant.slug"></p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                              :class="tenant.status === 'active' ? 'bg-emerald-500/15 text-emerald-200' : 'bg-amber-500/15 text-amber-200'"
                              x-text="tenant.status"></span>
                    </div>
                    <div class="text-xs text-slate-400 mb-3" x-show="tenant.contact_email">
                        <span x-text="tenant.contact_name"></span>
                        <span class="text-slate-600 mx-1">•</span>
                        <span x-text="tenant.contact_email"></span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs mb-4">
                        <div class="bg-slate-800/50 rounded-lg p-2">
                            <span class="text-slate-500">Users</span>
                            <p class="text-white font-semibold" x-text="tenant.user_count || 0"></p>
                        </div>
                        <div class="bg-slate-800/50 rounded-lg p-2">
                            <span class="text-slate-500">Agents</span>
                            <p class="text-white font-semibold" x-text="tenant.agent_count || 0"></p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="openEditModal(tenant)" class="flex-1 text-xs px-3 py-1.5 rounded bg-slate-800 border border-slate-700 hover:border-slate-500 text-slate-200">Edit</button>
                        <a :href="'index.php?m=cloudstorage&page=e3backup&view=tenant_users&tenant_id=' + tenant.id" class="flex-1 text-xs px-3 py-1.5 rounded bg-slate-800 border border-slate-700 hover:border-slate-500 text-slate-200 text-center">Users</a>
                        <button @click="openDeleteModal(tenant)" class="text-xs px-3 py-1.5 rounded bg-rose-900/50 border border-rose-700 hover:border-rose-500 text-rose-200">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Custom scrollbar styles for modal -->
    <style>
        .modal-scroll::-webkit-scrollbar { width: 6px; }
        .modal-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 3px; }
        .modal-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
        .modal-scroll::-webkit-scrollbar-thumb:hover { background: #64748b; }
        .modal-scroll { scrollbar-width: thin; scrollbar-color: #475569 #1e293b; }
    </style>

    <!-- Create/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="showModal = false">
        <div class="w-full max-w-2xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4 flex-shrink-0">
                <h3 class="text-lg font-semibold text-white" x-text="editingTenant ? 'Edit Tenant' : 'Create Tenant'"></h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-white text-2xl">&times;</button>
            </div>
            <form @submit.prevent="saveTenant()" class="p-6 space-y-6 overflow-y-auto flex-1 modal-scroll">
                
                <!-- Organization Section -->
                <div class="border-b border-slate-700 pb-6">
                    <h4 class="text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4">Organization</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-300 mb-1">Company Name <span class="text-rose-400">*</span></label>
                            <input type="text" x-model="form.name" required placeholder="Acme Corporation" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Slug <span class="text-slate-500 font-normal">(auto-generated)</span></label>
                            <input type="text" x-model="form.slug" :disabled="editingTenant" placeholder="acme-corp" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50 font-mono">
                            <p class="text-xs text-slate-500 mt-1">URL-friendly identifier. Cannot be changed after creation.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Status</label>
                            <select x-model="form.status" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information Section -->
                <div class="border-b border-slate-700 pb-6">
                    <h4 class="text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4">Contact Information</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Contact Email <span class="text-rose-400">*</span></label>
                            <input type="email" x-model="form.contact_email" required placeholder="billing@acme.com" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Contact Name <span class="text-rose-400">*</span></label>
                            <input type="text" x-model="form.contact_name" required placeholder="Jane Smith" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-300 mb-1">Phone</label>
                            <input type="tel" x-model="form.contact_phone" placeholder="+1 (555) 123-4567" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                    </div>
                </div>
                
                <!-- Billing Address Section (Collapsible) -->
                <div class="border-b border-slate-700 pb-6" x-data="{ showAddress: false }">
                    <button type="button" @click="showAddress = !showAddress" class="flex items-center gap-2 text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4 hover:text-violet-300">
                        <svg class="w-4 h-4 transition-transform" :class="showAddress && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                        Billing Address <span class="font-normal text-slate-500">(optional)</span>
                    </button>
                    
                    <div x-show="showAddress" x-collapse class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-300 mb-1">Address Line 1</label>
                            <input type="text" x-model="form.address_line1" placeholder="123 Main Street" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-300 mb-1">Address Line 2</label>
                            <input type="text" x-model="form.address_line2" placeholder="Suite 100" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">City</label>
                            <input type="text" x-model="form.city" placeholder="Toronto" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">State/Province</label>
                            <input type="text" x-model="form.state" placeholder="Ontario" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Postal Code</label>
                            <input type="text" x-model="form.postal_code" placeholder="M5V 1A1" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Country Code</label>
                            <input type="text" x-model="form.country" placeholder="CA" maxlength="2" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500 uppercase">
                            <p class="text-xs text-slate-500 mt-1">2-letter ISO code (e.g., CA, US, GB)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Portal Admin Account Section (Only on Create) -->
                <div x-show="!editingTenant" class="pb-4">
                    <h4 class="text-sm font-semibold text-violet-400 uppercase tracking-wide mb-4">Portal Admin Account</h4>
                    
                    <label class="flex items-center gap-2 text-sm text-slate-300 mb-4 cursor-pointer">
                        <input type="checkbox" x-model="form.create_admin" class="rounded bg-slate-800 border-slate-600 text-violet-600 focus:ring-violet-500">
                        Create portal admin user for this tenant
                    </label>
                    
                    <div x-show="form.create_admin" x-collapse class="space-y-4 pl-6  border-violet-600/30">
                        <p class="text-xs text-slate-400">Create an admin user who can access the tenant portal to manage devices, view backups, and initiate restores.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1">Admin Email</label>
                                <input type="email" x-model="form.admin_email" :placeholder="form.contact_email || 'admin@acme.com'" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <p class="text-xs text-slate-500 mt-1">Defaults to contact email if empty</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1">Admin Name</label>
                                <input type="text" x-model="form.admin_name" :placeholder="form.contact_name || 'Admin User'" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <p class="text-xs text-slate-500 mt-1">Defaults to contact name if empty</p>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                                <input type="radio" x-model="form.auto_password" value="1" class="bg-slate-800 border-slate-600 text-violet-600 focus:ring-violet-500">
                                Auto-generate password &amp; email to user
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                                <input type="radio" x-model="form.auto_password" value="0" class="bg-slate-800 border-slate-600 text-violet-600 focus:ring-violet-500">
                                Set password manually
                            </label>
                        </div>
                        
                        <div x-show="form.auto_password === '0'" x-collapse>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                            <input type="password" x-model="form.admin_password" minlength="8" placeholder="Minimum 8 characters" class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-700">
                    <button type="button" @click="showModal = false" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md bg-violet-600 text-white text-sm font-semibold hover:bg-violet-500 disabled:opacity-50" :disabled="saving">
                        <span x-show="!saving" x-text="editingTenant ? 'Save Changes' : 'Create Tenant'"></span>
                        <span x-show="saving">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md rounded-xl border border-rose-700 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Delete Tenant</h3>
                <button @click="showDeleteModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <div class="p-6">
                <p class="text-slate-300 mb-4">Are you sure you want to delete <strong x-text="deletingTenant?.name" class="text-white"></strong>?</p>
                <p class="text-sm text-rose-400 mb-4">This will remove all tenant users and unassign all agents. This action cannot be undone.</p>
                <div class="flex justify-end gap-3">
                    <button @click="showDeleteModal = false" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">Cancel</button>
                    <button @click="deleteTenant()" class="px-4 py-2 rounded-md bg-rose-600 text-white text-sm font-semibold hover:bg-rose-500" :disabled="deleting">
                        <span x-show="!deleting">Delete Tenant</span>
                        <span x-show="deleting">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Notification -->
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
            <svg x-show="notification.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-text="notification.message"></span>
        </div>
    </div>
</div>

{literal}
<script>
function tenantsApp() {
    return {
        tenants: [],
        loading: true,
        showModal: false,
        showDeleteModal: false,
        editingTenant: null,
        deletingTenant: null,
        saving: false,
        deleting: false,
        notification: { show: false, message: '', type: 'success' },
        form: {
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
        
        init() {
            this.loadTenants();
            // Auto-generate slug from name
            this.$watch('form.name', (value) => {
                if (!this.editingTenant) {
                    this.form.slug = value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                }
            });
        },
        
        async loadTenants() {
            this.loading = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_list.php');
                const data = await res.json();
                if (data.status === 'success') {
                    this.tenants = data.tenants || [];
                }
            } catch (e) {
                console.error('Failed to load tenants:', e);
            }
            this.loading = false;
        },
        
        openCreateModal() {
            this.editingTenant = null;
            this.form = {
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
            };
            this.showModal = true;
        },
        
        openEditModal(tenant) {
            this.editingTenant = tenant;
            this.form = {
                name: tenant.name,
                slug: tenant.slug,
                status: tenant.status,
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
            this.showModal = true;
        },
        
        openDeleteModal(tenant) {
            this.deletingTenant = tenant;
            this.showDeleteModal = true;
        },
        
        async saveTenant() {
            this.saving = true;
            try {
                const endpoint = this.editingTenant 
                    ? 'modules/addons/cloudstorage/api/e3backup_tenant_update.php'
                    : 'modules/addons/cloudstorage/api/e3backup_tenant_create.php';
                
                const params = new URLSearchParams({
                    name: this.form.name,
                    slug: this.form.slug,
                    status: this.form.status,
                    contact_email: this.form.contact_email,
                    contact_name: this.form.contact_name,
                    contact_phone: this.form.contact_phone,
                    address_line1: this.form.address_line1,
                    address_line2: this.form.address_line2,
                    city: this.form.city,
                    state: this.form.state,
                    postal_code: this.form.postal_code,
                    country: this.form.country
                });
                
                if (this.editingTenant) {
                    params.set('tenant_id', this.editingTenant.id);
                } else {
                    // Add admin creation params for new tenants
                    params.set('create_admin', this.form.create_admin ? '1' : '0');
                    if (this.form.create_admin) {
                        params.set('admin_email', this.form.admin_email);
                        params.set('admin_name', this.form.admin_name);
                        params.set('auto_password', this.form.auto_password);
                        if (this.form.auto_password === '0') {
                            params.set('admin_password', this.form.admin_password);
                        }
                    }
                }
                
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showModal = false;
                    this.loadTenants();
                    
                    // Show success notification
                    let message = this.editingTenant ? 'Tenant updated successfully' : 'Tenant created successfully';
                    if (data.admin_created) {
                        message += '. Admin user created';
                        if (data.welcome_email_sent) {
                            message += ' and welcome email sent';
                        }
                    }
                    this.showNotification(message, 'success');
                } else {
                    alert(data.message || 'Failed to save tenant');
                }
            } catch (e) {
                alert('Failed to save tenant');
            }
            this.saving = false;
        },
        
        async deleteTenant() {
            this.deleting = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_tenant_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ tenant_id: this.deletingTenant.id })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showDeleteModal = false;
                    this.loadTenants();
                    this.showNotification('Tenant deleted successfully', 'success');
                } else {
                    alert(data.message || 'Failed to delete tenant');
                }
            } catch (e) {
                alert('Failed to delete tenant');
            }
            this.deleting = false;
        },
        
        showNotification(message, type = 'success') {
            this.notification = { show: true, message, type };
            setTimeout(() => {
                this.notification.show = false;
            }, 4000);
        }
    };
}
</script>
{/literal}
