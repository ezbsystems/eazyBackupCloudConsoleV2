{* 
    Reusable Bucket Creation Modal
    
    Required Smarty variables:
    - $usernames: Array of tenant usernames for the dropdown
    
    Usage:
    1. Include this partial in your template
    2. Call openBucketCreateModal(callback) from JavaScript
    3. The callback receives the new bucket object on success
*}

<!-- Bucket Creation Modal -->
<div id="bucketCreateModal" 
     x-data="bucketCreateModal()" 
     x-show="isOpen" 
     x-cloak
     class="fixed inset-0 z-[9999] flex items-center justify-center"
     style="display: none;">
    
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" 
         @click="close()"
         x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>
    
    <!-- Modal Panel -->
    <div class="relative w-full max-w-md mx-4 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl"
         x-show="isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop
         @keydown.escape.window="close()">
        
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Create New Bucket</h3>
                    <p class="text-xs text-slate-400">Storage container for your backups</p>
                </div>
            </div>
            <button @click="close()" class="text-slate-400 hover:text-white transition p-1 rounded-lg hover:bg-slate-800">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <!-- Body -->
        <div class="px-5 py-4 space-y-4">
            <!-- Tenant Selection -->
            <div class="relative" @click.away="tenantDropdownOpen = false">
                <label class="block text-sm font-medium text-slate-300 mb-1.5">
                    Tenant <span class="text-rose-400">*</span>
                </label>
                <button type="button"
                        @click="tenantDropdownOpen = !tenantDropdownOpen"
                        class="relative w-full px-3 py-2.5 text-left bg-slate-800 border border-slate-600 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition">
                    <span class="block truncate" x-text="selectedTenant || 'Select a tenant...'"></span>
                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>
                </button>
                <div x-show="tenantDropdownOpen" 
                     x-transition
                     class="absolute z-10 w-full mt-1 bg-slate-800 border border-slate-600 rounded-lg shadow-xl overflow-hidden"
                     style="display: none;">
                    <div class="p-2 border-b border-slate-700">
                        <input type="text" 
                               x-model="tenantSearch" 
                               placeholder="Search tenants..." 
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-md text-slate-200 text-sm focus:outline-none focus:ring-1 focus:ring-cyan-500">
                    </div>
                    <ul class="max-h-48 overflow-y-auto scrollbar_thin">
                        <template x-if="filteredTenants.length === 0">
                            <li class="px-3 py-2 text-sm text-slate-500">No tenants found</li>
                        </template>
                        <template x-for="u in filteredTenants" :key="u">
                            <li @click="selectTenant(u)"
                                class="px-3 py-2 text-sm text-slate-200 cursor-pointer hover:bg-slate-700 transition"
                                :class="{ 'bg-cyan-600/20 text-cyan-300': selectedTenant === u }"
                                x-text="u"></li>
                        </template>
                    </ul>
                </div>
            </div>
            
            <!-- Bucket Name -->
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">
                    Bucket Name <span class="text-rose-400">*</span>
                </label>
                <input type="text" 
                       x-model="bucketName"
                       @input="validateName()"
                       class="w-full px-3 py-2.5 bg-slate-800 border rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition"
                       :class="nameError ? 'border-rose-500' : 'border-slate-600'"
                       placeholder="e.g., backups-company-prod">
                <p class="mt-1 text-xs" :class="nameError ? 'text-rose-400' : 'text-slate-500'">
                    <span x-show="!nameError">Lowercase letters, numbers, dots, and hyphens only (3-63 chars)</span>
                    <span x-show="nameError" x-text="nameError"></span>
                </p>
            </div>
            
            <!-- Versioning Toggle -->
            <div class="flex items-center justify-between p-3 bg-slate-800/50 border border-slate-700 rounded-lg">
                <div>
                    <label class="text-sm font-medium text-slate-200">Enable Versioning</label>
                    <p class="text-xs text-slate-500">Keep previous versions of files</p>
                </div>
                <button type="button" 
                        @click="versioningEnabled = !versioningEnabled"
                        class="relative w-11 h-6 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                        :class="versioningEnabled ? 'bg-cyan-600' : 'bg-slate-600'">
                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                          :class="versioningEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                </button>
            </div>
            
            <!-- Retention Days (shown when versioning enabled) -->
            <div x-show="versioningEnabled" x-transition class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                <label class="block text-sm font-medium text-amber-200 mb-1.5">Keep versions for (days)</label>
                <input type="number" 
                       x-model.number="retentionDays"
                       min="1" 
                       max="365"
                       class="w-32 px-3 py-2 bg-slate-800 border border-slate-600 rounded-lg text-slate-200 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                <p class="mt-1 text-xs text-amber-300/80">Each stored version increases storage usage</p>
            </div>
            
            <!-- Error/Success Message -->
            <div x-show="message" 
                 x-transition
                 class="p-3 rounded-lg text-sm"
                 :class="messageType === 'error' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'">
                <span x-text="message"></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-slate-700 bg-slate-800/30 rounded-b-xl">
            <button type="button" 
                    @click="close()"
                    :disabled="creating"
                    class="px-4 py-2 text-sm font-medium text-slate-300 bg-slate-800 border border-slate-600 rounded-lg hover:bg-slate-700 hover:border-slate-500 transition disabled:opacity-50">
                Cancel
            </button>
            <button type="button" 
                    @click="createBucket()"
                    :disabled="creating || !canSubmit"
                    class="px-5 py-2 text-sm font-medium text-white bg-cyan-600 rounded-lg hover:bg-cyan-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                <svg x-show="creating" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="creating ? 'Creating...' : 'Create Bucket'"></span>
            </button>
        </div>
    </div>
</div>

{literal}
<script>
// Bucket Create Modal Alpine Component
function bucketCreateModal() {
    return {
        isOpen: false,
        creating: false,
        
        // Tenant dropdown state
        tenantDropdownOpen: false,
        tenantSearch: '',
        allTenants: [{/literal}{foreach from=$usernames item=username name=userloop}'{$username|escape:'javascript'}'{if !$smarty.foreach.userloop.last},{/if}{/foreach}{literal}],
        selectedTenant: '',
        
        // Form fields
        bucketName: '',
        versioningEnabled: false,
        retentionDays: 30,
        nameError: '',
        message: '',
        messageType: '',
        onSuccessCallback: null,
        
        get filteredTenants() {
            if (!this.tenantSearch) return this.allTenants;
            const q = this.tenantSearch.toLowerCase();
            return this.allTenants.filter(u => u.toLowerCase().includes(q));
        },
        
        get canSubmit() {
            return this.selectedTenant && 
                   this.bucketName.length >= 3 && 
                   !this.nameError;
        },
        
        selectTenant(tenant) {
            this.selectedTenant = tenant;
            this.tenantDropdownOpen = false;
            this.tenantSearch = '';
        },
        
        open(callback) {
            this.reset();
            this.onSuccessCallback = callback || null;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },
        
        reset() {
            this.selectedTenant = '';
            this.tenantDropdownOpen = false;
            this.tenantSearch = '';
            this.bucketName = '';
            this.versioningEnabled = false;
            this.retentionDays = 30;
            this.nameError = '';
            this.message = '';
            this.messageType = '';
            this.creating = false;
        },
        
        validateName() {
            const name = this.bucketName.trim();
            this.nameError = '';
            
            if (name.length === 0) return;
            
            if (name.length < 3) {
                this.nameError = 'Name must be at least 3 characters';
                return;
            }
            if (name.length > 63) {
                this.nameError = 'Name must be 63 characters or less';
                return;
            }
            if (!/^[a-z0-9]/.test(name)) {
                this.nameError = 'Must start with a lowercase letter or number';
                return;
            }
            if (!/[a-z0-9]$/.test(name)) {
                this.nameError = 'Must end with a lowercase letter or number';
                return;
            }
            if (!/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/.test(name) && name.length > 2) {
                this.nameError = 'Only lowercase letters, numbers, dots, and hyphens allowed';
                return;
            }
            if (/\.\./.test(name)) {
                this.nameError = 'Cannot contain consecutive dots';
                return;
            }
        },
        
        async createBucket() {
            if (!this.canSubmit) return;
            
            this.creating = true;
            this.message = '';
            
            try {
                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_create_bucket.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        bucket_name: this.bucketName.trim(),
                        username: this.selectedTenant,
                        versioning_enabled: this.versioningEnabled ? '1' : '0',
                        retention_days: this.versioningEnabled ? this.retentionDays : 0
                    })
                });
                
                const data = await resp.json();
                
                if (data.status === 'success' && data.bucket) {
                    this.message = 'Bucket created successfully!';
                    this.messageType = 'success';
                    
                    // Call the success callback with the new bucket
                    if (typeof this.onSuccessCallback === 'function') {
                        this.onSuccessCallback(data.bucket);
                    }
                    
                    // Close modal after short delay
                    setTimeout(() => {
                        this.close();
                    }, 800);
                    
                } else {
                    this.message = data.message || 'Failed to create bucket';
                    this.messageType = 'error';
                }
            } catch (e) {
                this.message = 'Network error: ' + e.message;
                this.messageType = 'error';
            } finally {
                this.creating = false;
            }
        }
    };
}

// Global function to open the modal
function openBucketCreateModal(callback) {
    const modalEl = document.getElementById('bucketCreateModal');
    if (modalEl && modalEl._x_dataStack) {
        modalEl._x_dataStack[0].open(callback);
    }
}
</script>
{/literal}
