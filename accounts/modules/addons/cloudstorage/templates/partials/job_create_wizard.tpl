<!-- Create Job Slide-Over (dynamically populated) -->
<div id="createJobSlideover" x-data="{ isOpen: false }" x-show="isOpen" class="fixed inset-0 z-50" style="display: none;">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/75"
         x-show="isOpen"
         x-transition.opacity
         onclick="closeCreateSlideover()"></div>
    <!-- Panel -->
    <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-slate-950 border-l border-slate-800/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] overflow-y-auto"
         x-show="isOpen"
         x-transition:enter="transform transition ease-in-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in-out duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
        <div class="flex items-center justify-between p-4 border-b border-slate-700">
            <h3 class="text-lg font-semibold text-white">Create Backup Job</h3>
            <button class="text-slate-300 hover:text-white" onclick="closeCreateSlideover()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-4">
        <style>
        #createJobSlideover ::placeholder { color: #94a3b8; opacity: 1; }
        /* Panel + form controls - dark slate theme */
        #createJobSlideover .border-slate-700 { border-color: rgba(51,65,85,1); }
        #createJobSlideover input[type="text"],
        #createJobSlideover input[type="password"],
        #createJobSlideover input[type="number"],
        #createJobSlideover input[type="time"],
        #createJobSlideover select,
        #createJobSlideover textarea {
            background-color: rgb(15 23 42) !important; /* bg-slate-900 */
            border-color: rgba(51,65,85,1) !important;        /* border-slate-700 */
            color: #e2e8f0 !important;                        /* text-slate-200 */
        }
        #createJobSlideover input:focus,
        #createJobSlideover select:focus,
        #createJobSlideover textarea:focus {
            outline: none !important;
            border-color: rgb(14 165 233 / 1) !important;     /* border-sky-500 */
            
        }
        /* Common dropdown panels */
        #createJobSlideover .dropdown-surface {
            background-color: rgb(2 6 23);                    /* bg-slate-950 */
            border-color: rgba(51,65,85,1);
        }

        /* Scrollbars (ensure available on all pages using this template) */
        .scrollbar_thin {
            /* Firefox */
            scrollbar-width: thin;
            scrollbar-color: #4a5568 #192331;
        }
        .scrollbar_thin::-webkit-scrollbar { width: 8px; height: 8px; }
        .scrollbar_thin::-webkit-scrollbar-track { background: #192331; border-radius: 4px; }
        .scrollbar_thin::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; border: 1px solid #2d3748; }
        .scrollbar_thin::-webkit-scrollbar-thumb:hover { background: #5a6478; }
        .scrollbar_thin::-webkit-scrollbar-thumb:active { background: #6b7486; }

        /* Slightly slimmer + darker variant for modal panels */
        .scrollbar-thin-dark {
            /* Firefox */
            scrollbar-width: thin;
            scrollbar-color: #334155 #0b1220;
        }
        .scrollbar-thin-dark::-webkit-scrollbar { width: 6px; height: 6px; }
        .scrollbar-thin-dark::-webkit-scrollbar-track { background: #0b1220; border-radius: 9999px; }
        .scrollbar-thin-dark::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; border: 1px solid #0f172a; }
        .scrollbar-thin-dark::-webkit-scrollbar-thumb:hover { background: #475569; }
        </style>
        <div id="jobCreationMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden"></div>
        <form id="createJobForm">
            
            <input type="hidden" name="client_id" value="{$client_id}">
            <input type="hidden" name="s3_user_id" value="{$s3_user_id}">
            
            {if $isMspClient}
            <!-- MSP: Tenant Selection (filters agent dropdown) -->
            <div class="mb-4 p-4 bg-slate-800/50 rounded-lg border border-slate-700" 
                 x-data="mspTenantFilter()" 
                 data-agents='{if $agents}{$agents|@json_encode}{else}[]{/if}'>
                <label class="block text-xs uppercase tracking-wide font-semibold text-emerald-400 mb-2">MSP: Scope to Tenant</label>
                <select x-model="selectedTenant" name="wizard_tenant_id" class="w-full bg-slate-900 text-slate-200 border border-slate-700 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All / Direct (No Tenant Filter)</option>
                    <option value="direct">Direct Only (No Tenant)</option>
                    {foreach from=$tenants item=tenant}
                    <option value="{$tenant->id}">{$tenant->name|escape}</option>
                    {/foreach}
                </select>
                <p class="text-xs text-slate-500 mt-1">Select a tenant to filter available agents. The job will be associated with the agent's tenant.</p>
            </div>
            {/if}
            
            <!-- Step 1: Source Type -->
            <div class="mb-4" x-data="{
                isOpen: false,
                selected: 's3_compatible',
                options: [
                    { value: 's3_compatible', label: 'S3-Compatible Storage' },
                    { value: 'aws', label: 'Amazon S3 (AWS)' },
                    { value: 'sftp', label: 'SFTP/SSH Server' },
                    { value: 'google_drive', label: 'Google Drive' },
                    { value: 'dropbox', label: 'Dropbox' },
                    { value: 'local_agent', label: 'Local Agent (Windows)' }
                ],
                labelFor(val) {
                    const o = this.options.find(opt => opt.value === val);
                    return o ? o.label : val;
                }
            }" x-init="
                selected = $refs.nativeSelect.value || selected;
                $refs.nativeSelect.addEventListener('change', () => { selected = $refs.nativeSelect.value; });
            ">
                <label class="block text-sm font-medium text-slate-300 mb-2">Source Type</label>
                <!-- Hidden native select preserved for existing JS listeners and form submission -->
                <select name="source_type" id="sourceType" x-ref="nativeSelect" class="hidden" required>
                    <option value="s3_compatible">S3-Compatible Storage</option>
                    <option value="aws">Amazon S3 (AWS)</option>
                    <option value="sftp">SFTP/SSH Server</option>
                    <option value="google_drive">Google Drive</option>
                    <option value="dropbox">Dropbox</option>
                    <option value="local_agent">Local Agent (Windows)</option>
                </select>
                <!-- Alpine-powered dropdown UI -->
                <div class="relative">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                        <span class="block truncate" x-text="labelFor(selected)"></span>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </button>
                    <div x-show="isOpen" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                        <ul class="py-1 overflow-auto text-base max-h-60 rounded-md border border-slate-600 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                            <template x-for="opt in options" :key="opt.value">
                                <li @click="selected = opt.value; $refs.nativeSelect.value = opt.value; $refs.nativeSelect.dispatchEvent(new Event('change')); isOpen = false"
                                    class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700"
                                    :class="{ 'bg-gray-700 text-white': selected === opt.value }"
                                    x-text="opt.label">
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 2: Source Details -->
            <div id="sourceDetails">
                <!-- Security Warning: Read-Only Access Keys -->
                <div id="sourceAccessWarning" class="mb-4 p-4 bg-slate-800 border border-gray-600 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-bold text-gray-200 mb-1">Use READ-ONLY Access Keys</h3>
                            
                            <ul class="text-xs text-gray-300 list-disc list-inside space-y-1">
                                <li>Do not use access keys with write, delete, or modify permissions</li>
                                <li>Create dedicated read-only access keys specifically for backups</li>
                                <li>Using write-enabled keys is not required and is not recommended</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- S3-Compatible fields -->
                <div id="s3Fields" class="source-type-fields">
                    <input type="hidden" name="source_display_name" id="sourceDisplayName">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Endpoint URL</label>
                        <input type="text" name="s3_endpoint" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="https://s3.storageprovider.com" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="text" name="s3_region" value="ca-central-1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="ca-central-1" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" name="s3_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" name="s3_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <input type="text" name="s3_bucket" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" name="s3_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/">
                    </div>
                </div>

                <!-- AWS fields -->
                <div id="awsFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="aws_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g., AWS S3 Production" required>
                    </div>
                    <div class="mb-4" x-data="{
                        isOpen: false,
                        search: '',
                        selected: 'ca-central-1',
                        regions: [
                            { code: 'us-east-1', name: 'US East (N. Virginia)' },
                            { code: 'us-east-2', name: 'US East (Ohio)' },
                            { code: 'us-west-1', name: 'US West (N. California)' },
                            { code: 'us-west-2', name: 'US West (Oregon)' },
                            { code: 'ca-central-1', name: 'Canada (Central)' },
                            { code: 'eu-central-1', name: 'Europe (Frankfurt)' },
                            { code: 'eu-west-1', name: 'Europe (Ireland)' },
                            { code: 'eu-west-2', name: 'Europe (London)' },
                            { code: 'eu-west-3', name: 'Europe (Paris)' },
                            { code: 'eu-north-1', name: 'Europe (Stockholm)' },
                            { code: 'eu-south-1', name: 'Europe (Milan)' },
                            { code: 'ap-south-1', name: 'Asia Pacific (Mumbai)' },
                            { code: 'ap-south-2', name: 'Asia Pacific (Hyderabad)' },
                            { code: 'ap-southeast-1', name: 'Asia Pacific (Singapore)' },
                            { code: 'ap-southeast-2', name: 'Asia Pacific (Sydney)' },
                            { code: 'ap-southeast-3', name: 'Asia Pacific (Jakarta)' },
                            { code: 'ap-southeast-4', name: 'Asia Pacific (Melbourne)' },
                            { code: 'ap-northeast-1', name: 'Asia Pacific (Tokyo)' },
                            { code: 'ap-northeast-2', name: 'Asia Pacific (Seoul)' },
                            { code: 'ap-northeast-3', name: 'Asia Pacific (Osaka)' },
                            { code: 'sa-east-1', name: 'South America (São Paulo)' },
                            { code: 'me-south-1', name: 'Middle East (Bahrain)' },
                            { code: 'me-central-1', name: 'Middle East (UAE)' },
                            { code: 'af-south-1', name: 'Africa (Cape Town)' }
                        ],
                        get filtered() {
                            if (!this.search) return this.regions;
                            const q = this.search.toLowerCase();
                            return this.regions.filter(r => r.code.toLowerCase().includes(q) || r.name.toLowerCase().includes(q));
                        }
                    }" @click.away="isOpen=false">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="hidden" name="aws_region" :value="selected">
                        <div class="relative">
                            <button type="button" @click="isOpen=!isOpen" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selected"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </span>
                            </button>
                            <div x-show="isOpen" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search regions..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                                    <template x-for="r in filtered" :key="r.code">
                                        <li @click="selected=r.code; isOpen=false" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700">
                                            <span x-text="r.code + ' — ' + r.name"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" name="aws_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" name="aws_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4" x-data="{
                        isOpen:false, loading:false, search:'', buckets:[], selected:'',
                        async load() {
                            const ak = (document.querySelector('input[name=aws_access_key]')?.value || '').trim();
                            const sk = (document.querySelector('input[name=aws_secret_key]')?.value || '').trim();
                            const rg = (document.querySelector('input[name=aws_region]')?.value || '').trim();
                            if (!ak || !sk || !rg) { if (window.toast) window.toast.error('Enter Access Key, Secret, and Region'); return; }
                            this.loading = true;
                            try {
                                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_list_aws_buckets.php', {
                                    method:'POST',
                                    headers: new Headers([['Content-Type','application/x-www-form-urlencoded']]),
                                    body: new URLSearchParams([['access_key', ak], ['secret_key', sk], ['region', rg], ['filter_region', '1']])
                                });
                                const data = await resp.json();
                                if (data.status === 'success') {
                                    this.buckets = (data.buckets || []).map(b => b.name);
                                    this.isOpen = true;
                                    if (!this.buckets.length && window.toast) window.toast.info('No buckets found in this region');
                                } else {
                                    if (window.toast) window.toast.error(data.message || 'Failed to load buckets');
                                }
                            } catch (e) {
                                if (window.toast) window.toast.error('Error loading buckets');
                            } finally {
                                this.loading = false;
                            }
                        },
                        get filtered() {
                            if (!this.search) return this.buckets;
                            const q = this.search.toLowerCase();
                            return this.buckets.filter(n => n.toLowerCase().includes(q));
                        }
                    }" @click.away="isOpen=false">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <div class="flex gap-2">
                            <input type="text" name="aws_bucket" x-model="selected" class="flex-1 bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="Select or type a bucket..." required>
                            <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500" @click="load()" :disabled="loading">
                                <span x-show="!loading">Load buckets</span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </div>
                        <div x-show="isOpen" class="relative mt-2">
                            <div class="absolute z-10 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                                    <template x-for="b in filtered" :key="b">
                                        <li @click="selected=b; isOpen=false" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="b"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" name="aws_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/">
                    </div>
                </div>

                <!-- SFTP fields -->
                <div id="sftpFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="sftp_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g., Customer NAS" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Hostname</label>
                        <input type="text" name="sftp_host" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Port</label>
                        <input type="number" name="sftp_port" value="22" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
                        <input type="text" name="sftp_username" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                        <input type="password" name="sftp_password" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Remote Path</label>
                        <input type="text" name="sftp_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="/backups/" required>
                    </div>
                </div>

                <!-- Local Agent fields -->
                <div id="localAgentFields" class="source-type-fields hidden">
                    <div class="mb-4" x-data="{
                        options: [],
                        loading: false,
                        async load() {
                            this.loading = true;
                            try {
                                const resp = await fetch('modules/addons/cloudstorage/api/agent_list.php');
                                const data = await resp.json();
                                if (data.status === 'success') {
                                    this.options = (data.agents || []).filter(a => a.status === 'active');
                                }
                            } catch (e) {
                            } finally {
                                this.loading = false;
                            }
                        }
                    }" x-init="load()">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Agent</label>
                        <select name="agent_id" id="agent_id" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                            <option value="">Select an agent</option>
                            <template x-for="a in options" :key="a.id">
                                <option :value="a.id" x-text="a.hostname ? (a.hostname + ' (ID ' + a.id + ')') : ('Agent #' + a.id)"></option>
                            </template>
                        </select>
                        <p class="text-xs text-slate-400 mt-1" x-show="!loading && options.length === 0">No active agents found. Create an agent first.</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Local Source Path</label>
                        <input type="text" name="local_source_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="C:\Data" />
                    </div>
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Include (glob, optional)</label>
                            <input type="text" name="local_include_glob" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="**\\*.docx" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Exclude (glob, optional)</label>
                            <input type="text" name="local_exclude_glob" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="**\\node_modules\\**" />
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bandwidth Limit (KB/s, optional)</label>
                        <input type="number" name="local_bandwidth_limit_kbps" min="0" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="0 = unlimited" />
                    </div>
                    <p class="text-xs text-slate-400">Local Agent jobs run on your Windows agent. Ensure the path exists on that machine.</p>
                </div>

                <!-- Google Drive fields -->
                <div id="gdriveFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Google Drive Connection</label>
                        <div x-data="{
                            loading:false, open:false, search:'', selected:null, options:[],
                            async load() {
                                this.loading = true;
                                try {
                                    const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_list_sources.php?provider=google_drive');
                                    const data = await resp.json();
                                    if (data.status === 'success') {
                                        this.options = (data.sources || []);
                                        this.open = true;
                                        if (!this.options.length && window.toast) window.toast.info('No Google Drive connections yet');
                                    } else {
                                        if (window.toast) window.toast.error(data.message || 'Failed to load connections');
                                    }
                                } catch (e) {
                                    if (window.toast) window.toast.error('Error loading connections');
                                } finally {
                                    this.loading = false;
                                }
                            },
                            choose(opt) {
                                this.selected = opt;
                                this.open = false;
                                // Scope to the create form's Google Drive section so we don't hit the edit field
                                const input = document.querySelector('#gdriveFields input[name=source_connection_id]');
                                if (input) input.value = opt.id;
                                const disp = document.querySelector('input[name=source_display_name]');
                                if (disp && !disp.value) disp.value = opt.display_name || (opt.account_email || 'Google Drive');
                            },
                            get filtered() {
                                if (!this.search) return this.options;
                                const q = this.search.toLowerCase();
                                return this.options.filter(o => (o.display_name || '').toLowerCase().includes(q) || (o.account_email || '').toLowerCase().includes(q));
                            }
                        }" @click.away="open=false">
                            <input type="hidden" name="source_connection_id" value="">
                            <input type="hidden" name="gdrive_team_drive" value="">
                            <div class="flex gap-2 mb-2">
                                <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500" @click="load()" :disabled="loading">
                                    <span x-show="!loading">Load connections</span>
                                    <span x-show="loading">Loading…</span>
                                </button>
                                <a href="index.php?m=cloudstorage&page=oauth_google_start" class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700">Connect Google Drive</a>
                            </div>
                            <p class="text-xs text-slate-400">
                                By connecting, you allow eazyBackup to view and download your Google Drive files for backup and restore. We don’t use this data for ads or resale, and you can disconnect at any time in Settings or your Google Account.
                            </p>
                            <div>
                                <div class="relative">
                                    <button type="button" @click="open=!open" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                        <span class="block truncate" x-text="selected ? ((selected.display_name || selected.account_email) || ('ID '+selected.id)) : 'Select a connection'"></span>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        </span>
                                    </button>
                                    <div x-show="open" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                                        <div class="p-2">
                                            <input type="text" x-model="search" placeholder="Search connections..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                        </div>
                                        <ul class="py-1 overflow-auto text-base max-h-60 focus:outline-none sm:text-sm scrollbar_thin">
                                            <template x-for="opt in filtered" :key="opt.id">
                                                <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700">
                                                    <span x-text="(opt.display_name || opt.account_email) || ('ID '+opt.id)"></span>
                                                    <span class="ml-2 text-[11px] text-slate-400" x-text="opt.account_email"></span>
                                                </li>
                                            </template>
                                            <template x-if="filtered.length === 0">
                                                <li class="px-4 py-2 text-gray-400">No connections.</li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">Select a saved Google Drive connection or click Connect to add one.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <!-- Hidden fields populated by the Drive picker -->
                        <input type="hidden" name="gdrive_root_folder_id" value="">
                        <input type="hidden" name="gdrive_selected_id" value="">
                        <input type="hidden" name="gdrive_selected_type" value="">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-300">Select from Google Drive</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Choose a folder or a single file to back up.</p>
                            </div>
                            <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500"
                                    onclick="openDrivePicker('create')">
                                Browse Drive
                            </button>
                        </div>
                    </div>
                    <!-- Path is not used for Google Drive selection via picker -->
                </div>

                <!-- Dropbox fields -->
                <div id="dropboxFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="dropbox_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="e.g., Dropbox Team" />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Token</label>
                        <input type="text" name="dropbox_token" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Root Path (optional)</label>
                        <input type="text" name="dropbox_root" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="/folder/" />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path (optional)</label>
                        <input type="text" name="dropbox_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="/folder/sub/" />
                    </div>
                </div>
            </div>

            <!-- Step 3: Destination -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Destination Bucket</label>
                <div
                    x-data="{
                        isOpen: false,
                        search: '',
                        selectedId: '',
                        selectedName: '',
                        options: [
                            {foreach from=$buckets item=bucket name=bloop}
                                { id: '{$bucket->id}', name: '{$bucket->name|escape:'javascript'}' }{if !$smarty.foreach.bloop.last},{/if}
                            {/foreach}
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$root.querySelector('select[data-dest-bucket-src]');
                            if (sel) {
                                sel.value = this.selectedId;
                                sel.dispatchEvent(new Event('change'));
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Hidden input used by form submit -->
                    <input type="hidden" name="dest_bucket_id" :value="selectedId" required>

                    <!-- Hidden/disabled select kept for internal syncing and for edit panel population -->
                    <select data-dest-bucket-src name="dest_bucket_id" class="hidden" disabled>
                        {foreach from=$buckets item=bucket}
                            <option value="{$bucket->id}">{$bucket->name}</option>
                        {/foreach}
                    </select>

                    <div class="relative">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName || 'Select a bucket'"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <!-- Dropdown panel -->
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No buckets found.</li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <!-- Sync Alpine state with hidden select on init and when changed externally -->
                    <div x-init="
                        (function() {
                            const sel = $root.querySelector('select[data-dest-bucket-src]');
                            if (sel) {
                                sel.addEventListener('change', () => {
                                    const o = sel.options[sel.selectedIndex];
                                    selectedId = o ? String(o.value) : '';
                                    selectedName = o ? o.text : '';
                                });
                                if (sel.options.length) {
                                    sel.selectedIndex = 0;
                                    sel.dispatchEvent(new Event('change'));
                                }
                            }
                        })()
                    "></div>
                </div>
            </div>
            <!-- Inline Bucket Creation -->
            <div class="mb-4" x-data="{ open:false, creating:false }">
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-700 px-3 py-1.5 text-xs text-slate-300 hover:text-white hover:border-slate-500 transition"
                        @click="open = !open">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6" />
                    </svg>
                    <span x-text="open ? `Hide create bucket` : `Don't have a bucket? Create one`"></span>
                </button>
                <div class="mt-3 rounded-lg border border-slate-700 bg-slate-900/50 p-3 space-y-3" x-show="open" x-cloak>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Bucket Name</label>
                        <input type="text" id="inline_bucket_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:border-sky-600" placeholder="e.g., backups-company-prod">
                        <p class="mt-1 text-[11px] text-slate-400">Lowercase letters, numbers, and hyphens only.</p>
                    </div>
                    <!-- Tenant selection combobox (Alpine) -->
                    <div
                        x-data="{
                            isOpen: false,
                            selectedUsername: '',
                            searchTerm: '',
                            usernames: [
                                {foreach from=$usernames item=username name=userloop}
                                    '{$username|escape:'javascript'}'{if !$smarty.foreach.userloop.last},{/if}
                                {/foreach}
                            ],
                            get filteredUsernames() {
                                if (this.searchTerm === '') return this.usernames;
                                return this.usernames.filter(u => u.toLowerCase().includes(this.searchTerm.toLowerCase()));
                            }
                        }"
                        @click.away="isOpen = false"
                    >
                        <label for="inline_username" class="block text-xs font-medium text-slate-300 mb-1">Select Tenant</label>
                        <input type="hidden" id="inline_username" x-model="selectedUsername">
                        <div class="relative">
                            <button @click="isOpen = !isOpen" type="button" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selectedUsername || 'Select a tenant'"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                            <div x-show="isOpen"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg"
                                 style="display: none;">
                                <div class="p-2">
                                    <input type="text" x-model="searchTerm" placeholder="Search tenants..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin" role="listbox">
                                    <template x-if="filteredUsernames.length === 0">
                                        <li class="px-4 py-2 text-gray-400">No tenants found.</li>
                                    </template>
                                    <template x-for="u in filteredUsernames" :key="u">
                                        <li @click="selectedUsername = u; isOpen = false"
                                            class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700"
                                            x-text="u">
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" id="inline_bucket_versioning" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                            <span class="text-sm text-slate-300">Enable versioning</span>
                        </label>
                        <div class="mt-2" x-data="{ v:false }" x-init="$watch(() => { const el = document.getElementById('inline_bucket_versioning'); return el ? !!el.checked : false; }, val => v = !!val)">
                            <div x-show="v" x-cloak>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Keep previous versions for (days)</label>
                                <input type="number" min="1" value="30" id="inline_bucket_retention_days" class="w-40 bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:border-sky-600">
                                <p class="mt-1 text-[11px] text-amber-300/90">Each stored version increases usage and may impact monthly billing.</p>
                            </div>
                        </div>
                    </div>
                    <div id="inlineCreateBucketMsg" class="hidden text-xs"></div>
                    <div class="flex justify-end">
                        <button type="button" class="btn-run-now"
                                :disabled="creating"
                                @click="creating=true; createBucketInline().finally(() => creating=false)">
                            <span x-show="!creating">Create bucket</span>
                            <span x-show="creating">Creating…</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Destination Prefix</label>
                <input type="text" name="dest_prefix" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/source-name/" required>
            </div>

            <!-- Step 4: Backup Mode -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Backup Mode</label>
                <div
                    x-data="{
                        isOpen:false,
                        search:'',
                        selectedId:'sync',
                        selectedName:'Sync (Incremental)',
                        options:[
                            { id:'sync', name:'Sync (Incremental)' },
                            { id:'archive', name:'Archive (Compressed)' }
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$refs.real;
                            if (sel) {
                                sel.value = this.selectedId;
                                try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Real form control kept hidden for FormData compatibility -->
                    <select id="backupMode" name="backup_mode" class="hidden" x-ref="real">
                        <option value="sync" selected>Sync (Incremental)</option>
                        <option value="archive">Archive (Compressed)</option>
                    </select>
                    <div class="relative">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No options.</li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div x-init="
                        (function(){
                            const sel = $refs.real;
                            if (sel) {
                                const o = sel.options[sel.selectedIndex];
                                selectedId = o ? String(o.value) : 'sync';
                                selectedName = o ? o.text : 'Sync (Incremental)';
                            }
                        })()
                    "></div>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    <strong>Sync:</strong> Transfers files incrementally, preserving structure. <strong>Archive:</strong> Creates a compressed archive file per run.
                </p>
            </div>

            <!-- Step 4b: Encryption -->
            {* <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="encryption_enabled" value="1" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                    <span class="ml-2 text-sm font-medium text-slate-300">Enable Encryption</span>
                </label>
                <p class="mt-1 ml-6 text-xs text-slate-400">
                    Encrypts backup data.<strong>Warning:</strong> Encryption cannot be disabled after enabling. Ensure you keep your encryption password secure.
                </p>
            </div> *}

            <!-- Step 4c: Validation -->
            {* <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="validation_enabled" value="1" id="validationEnabled" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                    <span class="ml-2 text-sm font-medium text-slate-300">Enable Post-Run Validation</span>
                </label>
                <p class="mt-1 ml-6 text-xs text-slate-400">
                    Runs check after each backup to verify data integrity. This may increase backup time but ensures data consistency.
                </p>
            </div> *}

            <!-- Step 4d: Retention Policy -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Retention Policy</label>
                <div
                    x-data="{
                        isOpen:false,
                        search:'',
                        selectedId:'none',
                        selectedName:'No Retention',
                        options:[
                            { id:'none', name:'No Retention' },
                            { id:'keep_last_n', name:'Keep Last N Runs' },
                            { id:'keep_days', name:'Keep for N Days' }
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$refs.real;
                            if (sel) {
                                sel.value = this.selectedId;
                                try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Hidden native select preserved for JS and payload -->
                    <select name="retention_mode" id="retentionMode" class="hidden" x-ref="real" onchange="onRetentionModeChange()">
                        <option value="none">No Retention</option>
                        <option value="keep_last_n">Keep Last N Runs</option>
                        <option value="keep_days">Keep for N Days</option>
                    </select>
                    <div class="relative">
                        <button type="button"
                                @click="selectedId = ($refs.real?.value || selectedId); selectedName = ($refs.real?.options[$refs.real.selectedIndex]?.text || selectedName); isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No options.</li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div x-init="
                        (function(){
                            const sel = $refs.real;
                            if (sel) {
                                const o = sel.options[sel.selectedIndex];
                                selectedId = o ? String(o.value) : 'none';
                                selectedName = o ? o.text : 'No Retention';
                                sel.addEventListener('change', () => {
                                    const oc = sel.options[sel.selectedIndex];
                                    selectedId = oc ? String(oc.value) : 'none';
                                    selectedName = oc ? oc.text : 'No Retention';
                                });
                            }
                        })()
                    "></div>
                </div>
                <div id="retentionValueContainer" class="mt-2 hidden">
                    <input type="number" name="retention_value" id="retentionValue" min="1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="Enter number">
                    <p class="mt-1 text-xs text-slate-400" id="retentionHelp"></p>
                </div>
            </div>

            <!-- Step 5: Schedule -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Schedule</label>
                <div
                    x-data="{
                        isOpen:false,
                        search:'',
                        selectedId:'manual',
                        selectedName:'Manual Only',
                        options:[
                            { id:'manual', name:'Manual Only' },
                            { id:'daily', name:'Daily' },
                            { id:'weekly', name:'Weekly' }
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$refs.real;
                            if (sel) {
                                sel.value = this.selectedId;
                                try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Real form control kept hidden for FormData + existing JS listeners -->
                    <select id="scheduleType" name="schedule_type" class="hidden" x-ref="real">
                        <option value="manual" selected>Manual Only</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                    <div class="relative">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No options.</li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div x-init="
                        (function(){
                            const sel = $refs.real;
                            if (sel) {
                                const o = sel.options[sel.selectedIndex];
                                selectedId = o ? String(o.value) : 'manual';
                                selectedName = o ? o.text : 'Manual Only';
                            }
                        })()
                    "></div>
                </div>
            </div>
            <div id="scheduleOptions" class="mb-4 hidden">
                <div class="mb-2">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Time</label>
                    <input type="time" name="schedule_time" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                </div>
                <div id="weeklyOption" class="mb-2 hidden">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Weekday</label>
                    <select name="schedule_weekday" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                        <option value="7">Sunday</option>
                    </select>
                </div>
            </div>

            <!-- Job Name -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Job Name</label>
                <input type="text" name="name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
            </div>

            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeCreateSlideover()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">Create Job</button>
            </div>
        </form>

        <!-- No Schedule confirmation modal -->
        <div id="noScheduleModal" x-data="{ open:false }" x-show="open" class="fixed inset-0 z-[9998] flex items-center justify-center" style="display:none;">
            <div class="absolute inset-0 bg-black/60" @click="open=false" onclick="hideNoScheduleModal()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900/90 shadow-2xl p-5">
                <div class="flex items-start gap-3">
                    <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/15 text-amber-300 border border-amber-400/30">
                        !
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-white">Create without a schedule?</h3>
                        <p class="mt-1 text-sm text-slate-300">
                            This backup will not run automatically. You can add a schedule later from the job settings.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 rounded-md border border-slate-600 text-slate-200 hover:border-slate-500" @click="open=false" onclick="hideNoScheduleModal()">Cancel</button>
                    <button type="button" class="px-4 py-2 rounded-md bg-emerald-600 text-white hover:bg-emerald-700" onclick="confirmNoScheduleCreate()">Create without schedule</button>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Local Agent Job Wizard Modal -->
<div id="localJobWizardModal" class="fixed inset-0 z-[2000] hidden">
    <div class="absolute inset-0 bg-black/75" onclick="closeLocalJobWizard()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-5xl h-[85vh] rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-800 shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs uppercase text-slate-400 tracking-wide">Local Agent</p>
                        <h3 class="text-xl font-semibold text-white">Backup Job Wizard</h3>
                    </div>
                    <button class="icon-btn" onclick="closeLocalJobWizard()" aria-label="Close wizard">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <!-- Breadcrumb Navigation -->
                <nav id="localWizardBreadcrumb" class="flex items-center gap-1">
                    <button type="button" data-wizard-step="1" onclick="localWizardGoToStep(1)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-active="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-cyan-500 text-white group-[.is-active]:bg-cyan-500 group-[.is-locked]:bg-slate-700 group-[.is-complete]:bg-emerald-500">1</span>
                        <span class="hidden sm:inline">Setup</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="2" onclick="localWizardGoToStep(2)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">2</span>
                        <span class="hidden sm:inline">Source</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="3" onclick="localWizardGoToStep(3)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">3</span>
                        <span class="hidden sm:inline">Schedule</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="4" onclick="localWizardGoToStep(4)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">4</span>
                        <span class="hidden sm:inline">Policy</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="5" onclick="localWizardGoToStep(5)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">5</span>
                        <span class="hidden sm:inline">Review</span>
                    </button>
                </nav>
            </div>

            <div class="px-6 py-4 overflow-y-auto flex-1 scrollbar-thin-dark">

                <div class="space-y-6">
                    <!-- Step 1 -->
                    <div class="wizard-step" data-step="1">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-200 mb-2">Job Name</label>
                                <input id="localWizardName" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="My local backup">
                            </div>
                            {if $isMspClient}
                            <!-- MSP: Tenant Selector -->
                            <div x-data="{
                                isOpen: false,
                                search: '',
                                selectedId: '',
                                selectedName: '',
                                options: [
                                    { id: '', name: 'No Tenant (Direct)' },
                                    {foreach from=$tenants item=tenant name=tenantloop}
                                        { id: '{$tenant->id}', name: '{$tenant->name|escape:'javascript'}' }{if !$smarty.foreach.tenantloop.last},{/if}
                                    {/foreach}
                                ],
                                get filtered() {
                                    const q = (this.search || '').toLowerCase();
                                    if (!q) return this.options;
                                    return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                                },
                                choose(opt) {
                                    if (!opt) return;
                                    this.selectedId = String(opt.id || '');
                                    this.selectedName = opt.name || '';
                                    const hid = document.getElementById('localWizardTenantId');
                                    if (hid) hid.value = this.selectedId;
                                    if (window.localWizardState?.data) {
                                        window.localWizardState.data.tenant_id = this.selectedId;
                                    }
                                    // Filter agents based on tenant selection
                                    this.filterAgentsByTenant(this.selectedId);
                                    this.isOpen = false;
                                },
                                filterAgentsByTenant(tenantId) {
                                    // Dispatch event to filter agent list
                                    window.dispatchEvent(new CustomEvent('tenant-changed', { detail: { tenantId } }));
                                }
                            }" @click.away="isOpen = false" x-init="selectedName = 'No Tenant (Direct)'">
                                <label class="block text-sm font-medium text-slate-200 mb-2">
                                    <span class="text-emerald-400 text-xs uppercase tracking-wide font-semibold">MSP:</span> Scope to Tenant
                                </label>
                                <input type="hidden" id="localWizardTenantId" x-model="selectedId">
                                <div class="relative">
                                    <button type="button"
                                            @click="isOpen = !isOpen"
                                            class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-slate-700 rounded-lg shadow-sm cursor-default focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                        <span class="block truncate" x-text="selectedName || 'No Tenant (Direct)'"></span>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    </button>
                                    <div x-show="isOpen" 
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         class="absolute z-20 mt-1 w-full bg-slate-900 border border-slate-700 rounded-lg shadow-xl overflow-hidden" 
                                         style="display: none;">
                                        <div class="p-2 border-b border-slate-800">
                                            <input type="text" x-model="search" placeholder="Search tenants..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-md text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                        </div>
                                        <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                            <template x-for="opt in filtered" :key="opt.id">
                                                <li @click="choose(opt)" 
                                                    class="px-3 py-2 text-slate-200 cursor-pointer select-none hover:bg-slate-800 transition"
                                                    :class="selectedId === String(opt.id) ? 'bg-cyan-500/20 text-cyan-200' : ''">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded bg-slate-700/50 flex items-center justify-center shrink-0" :class="opt.id ? 'bg-violet-500/20' : 'bg-slate-700/50'">
                                                            <svg x-show="opt.id" class="w-3.5 h-3.5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                            </svg>
                                                            <svg x-show="!opt.id" class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 12H4" />
                                                            </svg>
                                                        </div>
                                                        <span x-text="opt.name"></span>
                                                    </div>
                                                </li>
                                            </template>
                                            <template x-if="filtered.length === 0">
                                                <li class="px-3 py-2 text-slate-500 text-center">No tenants found.</li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Select a tenant to scope this backup job. The job will be associated with the tenant's agents.</p>
                            </div>
                            {/if}
                            <div class="md:col-span-2" x-data="{ showAdvanced: false }">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-200">Backup Engine</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                                        <span>Advanced</span>
                                        <button @click="showAdvanced = !showAdvanced" type="button" 
                                                class="relative w-9 h-5 rounded-full transition-colors"
                                                :class="showAdvanced ? 'bg-cyan-600' : 'bg-slate-700'">
                                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                                  :class="showAdvanced ? 'translate-x-4' : 'translate-x-0'"></span>
                                        </button>
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <!-- File Backup (Archive) - Primary option -->
                                    <button type="button" data-engine-btn="kopia" 
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700 bg-slate-800/50 hover:bg-slate-800 hover:border-slate-600 transition-all text-center"
                                            onclick="localWizardSet('engine','kopia')">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center group-[.selected]:bg-cyan-500/20">
                                            <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">File Backup</p>
                                            <p class="text-[10px] text-slate-500">(Archive)</p>
                                        </div>
                                    </button>

                                    <!-- Disk Image -->
                                    <button type="button" data-engine-btn="disk_image" 
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700 bg-slate-800/50 hover:bg-slate-800 hover:border-slate-600 transition-all text-center"
                                            onclick="localWizardSet('engine','disk_image')">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center group-[.selected]:bg-cyan-500/20">
                                            <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">Disk Image</p>
                                            <p class="text-[10px] text-slate-500">(Full Disk)</p>
                                        </div>
                                    </button>

                                    <!-- Hyper-V Backup -->
                                    <button type="button" data-engine-btn="hyperv" 
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700 bg-slate-800/50 hover:bg-slate-800 hover:border-slate-600 transition-all text-center"
                                            onclick="localWizardSet('engine','hyperv')">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center group-[.selected]:bg-cyan-500/20">
                                            <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">Hyper-V</p>
                                            <p class="text-[10px] text-slate-500">(VM Backup)</p>
                                        </div>
                                    </button>

                                    <!-- File Backup (Sync) - Advanced option -->
                                    <button type="button" data-engine-btn="sync" 
                                            x-show="showAdvanced"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700 bg-slate-800/50 hover:bg-slate-800 hover:border-slate-600 transition-all text-center"
                                            onclick="localWizardSet('engine','sync')">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center group-[.selected]:bg-cyan-500/20">
                                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">File Backup</p>
                                            <p class="text-[10px] text-slate-500">(Sync)</p>
                                        </div>
                                    </button>
                                </div>
                            </div>
                            <div x-data="{
                                allAgents: [],
                                options: [],
                                search: '',
                                loading: false,
                                isOpen: false,
                                selectedId: '',
                                selectedAgent: null,
                                tenantFilter: '',
                                init() {
                                    this.load();
                                    const component = this;
                                    window.addEventListener('tenant-changed', (e) => {
                                        component.tenantFilter = e.detail?.tenantId || '';
                                        component.applyTenantFilter();
                                    });
                                },
                                get filtered() {
                                    const q = (this.search || '').toLowerCase();
                                    if (!q) return this.options;
                                    return (this.options || []).filter(a => {
                                        const id = String(a.id || '');
                                        const hostname = String(a.hostname || '');
                                        const label = hostname ? (hostname + ' (ID ' + id + ')') : ('Agent #' + id);
                                        return label.toLowerCase().includes(q);
                                    });
                                },
                                agentLabel(a) {
                                    return a.hostname ? (a.hostname + ' (ID ' + a.id + ')') : ('Agent #' + a.id);
                                },
                                statusBadgeClass(status) {
                                    if (status === 'online') return 'bg-emerald-500/15 text-emerald-200';
                                    if (status === 'offline') return 'bg-rose-500/15 text-rose-200';
                                    return 'bg-slate-700 text-slate-300';
                                },
                                statusDotClass(status) {
                                    if (status === 'online') return 'bg-emerald-400 ring-2 ring-emerald-400/20 shadow-[0_0_10px_rgba(52,211,153,0.45)]';
                                    if (status === 'offline') return 'bg-rose-400';
                                    return 'bg-slate-500';
                                },
                                statusText(status) {
                                    if (status === 'online') return 'online';
                                    if (status === 'offline') return 'offline';
                                    return 'never';
                                },
                                async load() {
                                    this.loading = true;
                                    try {
                                        const resp = await fetch('modules/addons/cloudstorage/api/agent_list.php');
                                        const data = await resp.json();
                                        if (data.status === 'success') {
                                            this.allAgents = (data.agents || []).filter(a => a.status === 'active');
                                            this.applyTenantFilter();
                                        }
                                    } catch (e) {} finally { this.loading = false; }
                                },
                                applyTenantFilter() {
                                    if (!this.tenantFilter) {
                                        this.options = this.allAgents;
                                    } else if (this.tenantFilter === 'direct') {
                                        this.options = this.allAgents.filter(a => !a.tenant_id);
                                    } else {
                                        this.options = this.allAgents.filter(a => String(a.tenant_id) === String(this.tenantFilter));
                                    }
                                    // Reset selection if current agent doesn't match filter
                                    if (this.selectedId && !this.options.find(a => String(a.id) === String(this.selectedId))) {
                                        this.selectedId = '';
                                        this.selectedAgent = null;
                                        const hid = document.getElementById('localWizardAgentId');
                                        if (hid) hid.value = '';
                                        if (window.localWizardState?.data) {
                                            window.localWizardState.data.agent_id = '';
                                        }
                                    }
                                    // If a selection exists (e.g. deep-link prefill), refresh selectedAgent from loaded data
                                    // so we display the correct live online_status badge instead of a placeholder.
                                    if (this.selectedId) {
                                        const match = this.options.find(a => String(a.id) === String(this.selectedId));
                                        if (match) {
                                            this.selectedAgent = match;
                                        }
                                    }
                                },
                                choose(opt) {
                                    this.selectedId = opt.id;
                                    this.selectedAgent = opt;
                                    const hid = document.getElementById('localWizardAgentId');
                                    if (hid) hid.value = this.selectedId;
                                    if (window.localWizardState?.data) {
                                        window.localWizardState.data.agent_id = this.selectedId;
                                    }
                                    localWizardOnAgentSelected(this.selectedId);
                                    this.isOpen = false;
                                }
                            }" x-init="init()">
                                <label class="block text-sm font-medium text-slate-200 mb-2">Agent</label>
                                <input type="hidden" id="localWizardAgentId">
                                <div class="relative">
                                    <button type="button" class="w-full px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 flex justify-between items-center"
                                            @click="isOpen = !isOpen">
                                        <span class="flex items-center gap-2">
                                            <template x-if="selectedAgent">
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                                      :class="statusBadgeClass(selectedAgent.online_status)">
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusDotClass(selectedAgent.online_status)"></span>
                                                    <span x-text="statusText(selectedAgent.online_status)"></span>
                                                </span>
                                            </template>
                                            <span x-text="selectedAgent ? agentLabel(selectedAgent) : (loading ? 'Loading agents…' : 'Select agent')"></span>
                                        </span>
                                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-700 rounded-md shadow-lg max-h-60 overflow-auto" style="display:none;">
                                        <div class="p-2 border-b border-slate-800">
                                            <input type="text" x-model="search" placeholder="Search agents..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-md text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                        </div>
                                        <template x-for="opt in filtered" :key="opt.id">
                                            <div class="px-3 py-2 text-slate-200 hover:bg-slate-800 cursor-pointer flex items-center gap-2" @click="choose(opt)">
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                                      :class="statusBadgeClass(opt.online_status)">
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusDotClass(opt.online_status)"></span>
                                                    <span x-text="statusText(opt.online_status)"></span>
                                                </span>
                                                <span x-text="agentLabel(opt)"></span>
                                            </div>
                                        </template>
                                        <div class="px-3 py-2 text-slate-500 text-xs" x-show="!loading && filtered.length===0">No active agents found.</div>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Select your registered local agent.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-200 mb-2">Destination</label>
                                <div class="flex gap-2">
                                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 opacity-100" disabled>e3 (only)</button>
                                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-500 cursor-not-allowed" disabled>Local (disabled)</button>
                                </div>
                            </div>
                            <!-- Disk image fields moved to Step 2 -->
                            <div id="localWizardS3Fields">
                                <label class="block text-sm font-medium text-slate-200 mb-1">
                                    e3 Storage Location
                                    <span class="ml-1 text-xs font-normal text-slate-400">(Policy Managed)</span>
                                </label>
                                <p class="text-xs text-slate-500 mb-2">Bucket and root prefix are assigned automatically from the enrolled agent destination policy.</p>
                                <div
                                    id="localWizardBucketDropdown"
                                    class="hidden"
                                    x-data="{
                                        isOpen: false,
                                        search: '',
                                        selectedId: '',
                                        selectedName: '',
                                        options: [
                                            {foreach from=$buckets item=bucket name=bloopWizard}
                                                { id: '{$bucket->id}', name: '{$bucket->name|escape:'javascript'}' }{if !$smarty.foreach.bloopWizard.last},{/if}
                                            {/foreach}
                                        ],
                                        get filtered() {
                                            const q = (this.search || '').toLowerCase();
                                            if (!q) return this.options;
                                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                                        },
                                        choose(opt) {
                                            if (!opt) return;
                                            this.selectedId = String(opt.id || '');
                                            this.selectedName = opt.name || '';
                                            const hid = document.getElementById('localWizardBucketId');
                                            if (hid) hid.value = this.selectedId;
                                            this.isOpen = false;
                                            // Update breadcrumb state when bucket changes
                                            if (typeof localWizardUpdateView === 'function') localWizardUpdateView();
                                        },
                                        addBucket(bucket) {
                                            if (!bucket || !bucket.id) return;
                                            this.options.push({ id: String(bucket.id), name: bucket.name || '' });
                                            this.selectedId = String(bucket.id);
                                            this.selectedName = bucket.name || '';
                                            const hid = document.getElementById('localWizardBucketId');
                                            if (hid) hid.value = this.selectedId;
                                            if (typeof localWizardUpdateView === 'function') localWizardUpdateView();
                                        }
                                    }"
                                    @click.away="isOpen=false"
                                >
                                    <input type="hidden" id="localWizardBucketId">
                                    <div class="relative">
                                        <button type="button"
                                                @click="isOpen = !isOpen"
                                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-slate-700 rounded-lg shadow-sm cursor-default focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                            <span class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                                <span class="block truncate" x-text="selectedName || 'Choose where to store your backups'"></span>
                                            </span>
                                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        </button>
                                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                                            <div class="p-2">
                                                <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                            </div>
                                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                                <template x-for="opt in filtered" :key="opt.id">
                                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                                </template>
                                                <template x-if="filtered.length === 0">
                                                    <li class="px-4 py-2 text-gray-400">No buckets found.</li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 hidden">
                                    <label class="block text-sm font-medium text-slate-200 mb-2">Directory (optional)</label>
                                    <input id="localWizardPrefix" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="backups/job123/">
                                </div>
                                <div class="mt-3 hidden">
                                    <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-slate-600 text-slate-400 hover:text-sky-400 hover:border-sky-500/50 transition text-sm" onclick="openBucketCreateModal(onLocalWizardBucketCreated)">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Create new bucket
                                    </button>
                                </div>
                                <div class="mt-2 rounded-lg border border-slate-700 bg-slate-900/60 p-3 space-y-2">
                                    <div>
                                        <div class="text-xs text-slate-400">Bucket</div>
                                        <div id="localWizardBucketLabel" class="text-sm text-slate-100">Auto-assigned from selected agent</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-slate-400">Prefix</div>
                                        <div id="localWizardPrefixLabel" class="text-sm text-slate-100">Device-scoped immutable prefix</div>
                                    </div>
                                </div>
                            </div>
                            <div id="localWizardLocalFields" class="hidden">
                                <label class="block text-sm font-medium text-slate-200 mb-2">Local Destination Path</label>
                                <input id="localWizardLocalPath" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="E.g. D:\Backups">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="wizard-step hidden" data-step="2" x-data="{ 
                        currentEngine: window.localWizardState?.data?.engine || 'kopia',
                        init() {
                            this.currentEngine = window.localWizardState?.data?.engine || 'kopia';
                            window.addEventListener('engine-changed', (e) => {
                                this.currentEngine = e.detail?.engine || 'kopia';
                            });
                        },
                        get isDiskImage() { return this.currentEngine === 'disk_image'; },
                        get isHyperV() { return this.currentEngine === 'hyperv'; },
                        get stepLabel() {
                            if (this.isHyperV) return 'VM Selection';
                            if (this.isDiskImage) return 'Volume Selection';
                            return 'Source selection';
                        },
                        get stepDescription() {
                            if (this.isHyperV) return 'Select one or more Hyper-V virtual machines to back up';
                            if (this.isDiskImage) return 'Select a local disk to create a bare‑metal image backup';
                            return 'Browse your agent and select folders to back up';
                        }
                    }">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-200" x-text="stepLabel"></label>
                                <p class="text-xs text-slate-500" x-text="stepDescription"></p>
                            </div>
                            <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800 transition" @click="$dispatch('refresh-browser'); $dispatch('refresh-hyperv-vms')">Refresh</button>
                        </div>

                        <!-- Hidden inputs for all source types (must exist outside templates) -->
                        <input type="hidden" id="localWizardHypervVMs" />
                        <input type="hidden" id="localWizardSource" />
                        <input type="hidden" id="localWizardSourcePaths" />

                        <!-- HYPER-V VM BROWSER (Wrapper for conditional visibility) -->
                        <template x-if="isHyperV">
                        <div x-data="hypervBrowser()" x-init="init()" class="grid lg:grid-cols-3 gap-4">
                            
                            <!-- VM Grid -->
                            <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                                <!-- Header -->
                                <div class="flex items-center gap-2 px-4 py-3 bg-slate-800/60 border-b border-slate-800">
                                    <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3" />
                                    </svg>
                                    <span class="text-sm text-slate-300">Hyper-V Virtual Machines</span>
                                    <span class="text-xs text-slate-500 ml-auto" x-text="selectedVMs.length + ' selected'"></span>
                                </div>

                                <div class="h-[420px] overflow-y-auto scrollbar_thin">
                                    <!-- Loading State -->
                                    <div x-show="loading" class="flex items-center justify-center h-full py-12">
                                        <div class="text-center">
                                            <svg class="animate-spin h-8 w-8 text-blue-500 mx-auto mb-2" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <p class="text-sm text-slate-400">Discovering VMs...</p>
                                            <p class="text-xs text-slate-500 mt-1">This may take a few seconds</p>
                                        </div>
                                    </div>

                                    <!-- Error State -->
                                    <div x-show="error && !loading" class="px-4 py-6 text-center">
                                        <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-6 h-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-red-400" x-text="error"></p>
                                        <button type="button" class="mt-3 px-3 py-2 rounded-lg bg-slate-800 text-slate-200 text-xs hover:bg-slate-700" @click="loadVMs()">Retry</button>
                                    </div>

                                    <!-- VM Cards Grid -->
                                    <div x-show="!loading && !error" class="p-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <template x-for="vm in vms" :key="vm.id">
                                                <button type="button" 
                                                        class="vm-card group p-4 rounded-xl border text-left transition-all"
                                                        :class="isSelected(vm.id) 
                                                            ? 'border-blue-500 bg-blue-500/10 ring-2 ring-blue-500/40' 
                                                            : 'border-slate-700 bg-slate-800/50 hover:border-slate-600 hover:bg-slate-800'"
                                                        @click="toggleVM(vm)">
                                                    <div class="flex items-start gap-3">
                                                        <!-- VM Icon -->
                                                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                                             :class="isSelected(vm.id) ? 'bg-blue-500/20' : 'bg-slate-700/50'">
                                                            <svg class="w-6 h-6" :class="vm.state === 'Running' ? 'text-emerald-400' : (vm.state === 'Off' ? 'text-slate-400' : 'text-amber-400')" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7" />
                                                            </svg>
                                                        </div>
                                                        <!-- VM Info -->
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-base font-semibold text-slate-100 truncate" x-text="vm.name"></p>
                                                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                                <!-- State Badge -->
                                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                                      :class="vm.state === 'Running' ? 'bg-emerald-500/15 text-emerald-300' : (vm.state === 'Off' ? 'bg-slate-700 text-slate-400' : 'bg-amber-500/15 text-amber-300')">
                                                                    <span class="w-1.5 h-1.5 rounded-full" :class="vm.state === 'Running' ? 'bg-emerald-400' : (vm.state === 'Off' ? 'bg-slate-500' : 'bg-amber-400')"></span>
                                                                    <span x-text="vm.state"></span>
                                                                </span>
                                                                <!-- Generation -->
                                                                <span class="text-[10px] text-slate-500" x-text="'Gen ' + (vm.generation || 2)"></span>
                                                                <!-- OS Type -->
                                                                <span x-show="vm.is_linux" class="text-[10px] text-slate-500">• Linux</span>
                                                                <span x-show="!vm.is_linux" class="text-[10px] text-slate-500">• Windows</span>
                                                            </div>
                                                            <div class="flex items-center gap-3 mt-1 text-[10px] text-slate-500">
                                                                <span x-show="vm.cpu_count" x-text="vm.cpu_count + ' vCPU'"></span>
                                                                <span x-show="vm.memory_mb" x-text="formatMemory(vm.memory_mb)"></span>
                                                                <span x-show="Array.isArray(vm.disks) && vm.disks.length" x-text="(Array.isArray(vm.disks) ? vm.disks.length : 0) + ' disk(s)'"></span>
                                                                <span x-show="(!vm.disks || vm.disks.length === 0) && (vm.disk_count !== null && vm.disk_count !== undefined)" class="text-[10px] text-slate-500" x-text="vm.disk_count + ' disk(s)'"></span>
                                                                <span x-show="(!vm.disks || vm.disks.length === 0) && (vm.disk_count === null || vm.disk_count === undefined) && isSelected(vm.id)" class="text-[10px] text-slate-500">Fetching disk info...</span>
                                                            </div>
                                                        </div>
                                                        <!-- Checkbox -->
                                                        <div class="shrink-0">
                                                            <div class="w-5 h-5 rounded border-2 flex items-center justify-center"
                                                                 :class="isSelected(vm.id) ? 'border-blue-500 bg-blue-500' : 'border-slate-600'">
                                                                <svg x-show="isSelected(vm.id)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- RCT Status -->
                                                    <div class="mt-3 pt-3 border-t border-slate-700/50 flex items-center justify-between">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-[10px] text-slate-500">RCT (Incremental):</span>
                                                            <span x-show="vm.rct_enabled" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-emerald-500/15 text-emerald-300">
                                                                Enabled
                                                            </span>
                                                            <span x-show="!vm.rct_enabled" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-slate-700 text-slate-400">
                                                                Disabled
                                                            </span>
                                                        </div>
                                                        <span x-show="vm.integration_services" class="text-[10px] text-emerald-400">✓ Integration Services</span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                        <div x-show="vms.length === 0" class="text-center py-12">
                                            <div class="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mx-auto mb-4">
                                                <svg class="w-8 h-8 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6" />
                                                </svg>
                                            </div>
                                            <p class="text-sm text-slate-500">No Hyper-V VMs found</p>
                                            <p class="text-xs text-slate-600 mt-1">Ensure Hyper-V is enabled and VMs exist on the agent</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hyper-V Sidebar -->
                            <div class="space-y-4">
                                <!-- Selected VMs Summary -->
                                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Selected VMs</h4>
                                        <span class="text-xs text-blue-300" x-text="selectedVMs.length"></span>
                                    </div>
                                    <div class="space-y-2 max-h-48 overflow-y-auto">
                                        <template x-for="vmId in selectedVMs" :key="vmId">
                                            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-slate-800/60">
                                                <svg class="w-3 h-3 text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6" />
                                                </svg>
                                                <span class="text-xs text-slate-200 truncate flex-1" x-text="getVMName(vmId)"></span>
                                                <button type="button" class="p-1 hover:bg-slate-700 rounded" @click="removeVM(vmId)">
                                                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                        <div x-show="selectedVMs.length === 0" class="text-center text-xs text-slate-500 py-4">
                                            No VMs selected yet
                                        </div>
                                    </div>
                                    <button x-show="vms.length > 0 && selectedVMs.length < vms.length" type="button" 
                                            class="mt-3 w-full px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs transition"
                                            @click="selectAllVMs()">
                                        Select All VMs
                                    </button>
                                    <button x-show="selectedVMs.length > 0" type="button" 
                                            class="mt-2 w-full px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs transition"
                                            @click="clearSelection()">
                                        Clear Selection
                                    </button>
                                </div>

                                <!-- Backup Options -->
                                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3">
                                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Backup Options</h4>
                                    
                                    <!-- Enable RCT -->
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="localWizardHypervEnableRCT" checked 
                                               class="w-4 h-4 text-blue-600 bg-slate-800 border-slate-600 rounded focus:ring-blue-500 focus:ring-2">
                                        <div>
                                            <span class="text-sm text-slate-200">Enable RCT (Incremental)</span>
                                            <p class="text-[10px] text-slate-500">Use Resilient Change Tracking for fast incremental backups</p>
                                        </div>
                                    </label>

                                    <!-- Consistency Level -->
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Consistency Level</label>
                                        <select id="localWizardHypervConsistency" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500/50">
                                            <option value="application">Application-consistent (VSS)</option>
                                            <option value="crash">Crash-consistent (Faster)</option>
                                        </select>
                                        <p class="text-[10px] text-slate-500 mt-1">Application-consistent uses VSS for quiesced snapshots</p>
                                    </div>

                                    <!-- Quiesce Timeout -->
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Quiesce Timeout (seconds)</label>
                                        <input type="number" id="localWizardHypervQuiesceTimeout" value="300" min="30" max="1800"
                                               class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500/50">
                                        <p class="text-[10px] text-slate-500 mt-1">Maximum time to wait for VSS quiesce</p>
                                    </div>
                                </div>

                                <!-- Hyper-V Info -->
                                <div class="rounded-xl border border-blue-500/30 bg-blue-900/20 p-4">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-blue-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="text-xs text-blue-200 font-medium">Hyper-V Backup</p>
                                            <p class="text-[10px] text-blue-300/70 mt-1">VMs are backed up using VSS-aware checkpoints. RCT enables efficient incremental backups by tracking changed blocks.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </template>

                        <!-- FILE/DISK IMAGE BROWSER (hidden in Hyper-V mode) -->
                        <template x-if="!isHyperV">
                        <div x-data="fileBrowser()" x-init="init()" class="grid lg:grid-cols-3 gap-4">
                            <!-- File/Volume browser -->
                            <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                                <!-- Breadcrumb - hidden in disk image mode -->
                                <div x-show="!isDiskImageMode" class="flex items-center gap-1 px-4 py-2 bg-slate-800/60 border-b border-slate-800 overflow-x-auto text-xs text-slate-300">
                                    <button type="button" class="px-2 py-1 rounded hover:bg-slate-700 transition" @click="navigateTo('')">This PC</button>
                                    <template x-for="(segment, idx) in pathSegments" :key="idx">
                                        <div class="flex items-center shrink-0">
                                            <svg class="w-4 h-4 text-slate-600 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <button type="button" class="px-2 py-1 rounded hover:bg-slate-700 transition truncate max-w-[120px]" x-text="segment.name" @click="navigateTo(segment.path)"></button>
                                        </div>
                                    </template>
                                </div>

                                <!-- Header for disk image mode -->
                                <div x-show="isDiskImageMode" class="flex items-center gap-2 px-4 py-3 bg-slate-800/60 border-b border-slate-800">
                                    <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                    </svg>
                                    <span class="text-sm text-slate-300">Local Disks</span>
                                    <span class="text-xs text-slate-500 ml-auto">Select one disk for bare‑metal backup</span>
                                </div>

                                <div class="h-[420px] overflow-y-auto scrollbar_thin">
                                    <div x-show="loading" class="flex items-center justify-center h-full py-12">
                                        <div class="text-center">
                                            <svg class="animate-spin h-8 w-8 text-cyan-500 mx-auto mb-2" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <p class="text-sm text-slate-400">Loading...</p>
                                        </div>
                                    </div>

                                    <div x-show="error && !loading" class="px-4 py-6 text-center">
                                        <p class="text-sm text-red-400" x-text="error"></p>
                                        <button type="button" class="mt-3 px-3 py-2 rounded-lg bg-slate-800 text-slate-200 text-xs" @click="retry()">Retry</button>
                                    </div>

                                    <!-- DISK IMAGE MODE: Disk cards grid -->
                                    <div x-show="!loading && !error && isDiskImageMode" class="p-4">
                                        <div class="grid grid-cols-2 gap-3">
                                            <template x-for="entry in localVolumes" :key="entry.path">
                                                <button type="button" 
                                                        class="volume-card group p-4 rounded-xl border text-left transition-all"
                                                        :class="isVolumeEntrySelected(entry)
                                                            ? 'border-cyan-500 bg-cyan-500/10 ring-2 ring-cyan-500/40' 
                                                            : 'border-slate-700 bg-slate-800/50 hover:border-slate-600 hover:bg-slate-800'"
                                                        @click="selectVolume(entry)">
                                                    <div class="flex items-start gap-3">
                                                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                                             :class="isVolumeEntrySelected(entry) ? 'bg-cyan-500/20' : 'bg-slate-700/50'">
                                                            <svg class="w-6 h-6" :class="isVolumeEntrySelected(entry) ? 'text-cyan-400' : 'text-blue-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                                            </svg>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-base font-semibold text-slate-100" x-text="entry.name || entry.path"></p>
                                                            <p class="text-sm text-slate-400 truncate" x-text="entry.model || entry.path"></p>
                                                            <div class="flex items-center gap-2 mt-1">
                                                                <span x-show="entry.size_bytes" class="text-xs text-slate-500" x-text="formatBytes(entry.size_bytes)"></span>
                                                                <span x-show="entry.partition_style" class="text-xs text-slate-500">•</span>
                                                                <span x-show="entry.partition_style" class="text-xs text-slate-500" x-text="String(entry.partition_style || '').toUpperCase()"></span>
                                                            </div>
                                                            <div class="mt-2 space-y-1 text-[10px] text-slate-500" x-show="entry.partitions && entry.partitions.length">
                                                                <template x-for="part in entry.partitions" :key="part.path || part.name">
                                                                    <div class="flex items-center justify-between">
                                                                        <span class="truncate" x-text="part.name || part.path"></span>
                                                                        <span class="ml-2" x-text="formatBytes(part.size_bytes || 0)"></span>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                        <div class="shrink-0">
                                                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                                                                 :class="isVolumeEntrySelected(entry) ? 'border-cyan-500 bg-cyan-500' : 'border-slate-600'">
                                                                <svg x-show="isVolumeEntrySelected(entry)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                        <div x-show="localVolumes.length === 0" class="text-center py-12 text-sm text-slate-500">
                                            No disks were returned. If this machine has local disks, try again or check the agent.
                                        </div>
                                    </div>

                                    <!-- FILE BACKUP MODE: Standard folder browser -->
                                    <div x-show="!loading && !error && !isDiskImageMode" class="p-2 space-y-1">
                                        <button x-show="parentPath || currentPath" type="button" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800/60 text-left transition" @click="navigateTo(parentPath || '')">
                                            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                                </svg>
                                            </div>
                                            <span class="text-sm text-slate-400" x-text="parentPath ? '..' : 'This PC'"></span>
                                        </button>

                                        <!-- Root ("This PC"): Drive cards (no checkboxes) -->
                                        <template x-if="isBrowseRoot">
                                            <div class="p-4">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <template x-for="entry in rootBrowseDrives" :key="entry.path">
                                                        <button type="button"
                                                                class="volume-card group p-4 rounded-xl border text-left transition-all border-slate-700 bg-slate-800/50 hover:border-cyan-500 hover:bg-slate-800"
                                                                @click="navigateTo(entry.path)">
                                                            <div class="flex items-start gap-3">
                                                                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                                                     :class="entry.is_network ? 'bg-purple-500/10' : 'bg-blue-500/10'">
                                                                    <svg x-show="!entry.is_network" class="w-6 h-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                                                                    </svg>
                                                                    <svg x-show="entry.is_network" class="w-6 h-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                                                    </svg>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-base font-semibold text-slate-100 truncate" x-text="entry.name || entry.path"></p>
                                                                    <p class="text-xs text-slate-400 truncate" x-text="entry.is_network ? (entry.unc_path || 'Network Drive') : (entry.label || 'Local Disk')"></p>
                                                                    <p class="text-[10px] text-slate-500 mt-1">Click to browse folders</p>
                                                                </div>
                                                                <div class="shrink-0 pt-1">
                                                                    <svg class="w-4 h-4 text-slate-500 group-hover:text-cyan-300 group-hover:translate-x-0.5 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </template>
                                                </div>
                                                <div x-show="rootBrowseDrives.length === 0" class="text-center py-12 text-sm text-slate-500">
                                                    No drives found
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Inside a drive/folder: checkbox selector list -->
                                        <template x-if="!isBrowseRoot">
                                            <div class="space-y-1">
                                                <template x-for="entry in entries" :key="entry.path">
                                                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800/60 transition" :class="isSelected(entry.path) ? 'bg-cyan-500/10 ring-1 ring-cyan-500/40' : ''">
                                                        <label class="w-5 h-5 flex items-center justify-center rounded border cursor-pointer" :class="isSelected(entry.path) ? 'bg-cyan-500 border-cyan-500' : 'border-slate-600'">
                                                            <input type="checkbox" class="hidden" :checked="isSelected(entry.path)" @change="toggleSelection(entry)">
                                                            <svg x-show="isSelected(entry.path)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </label>
                                                        <button type="button" class="flex-1 flex items-center gap-3 text-left cursor-pointer" @click="entry.is_dir ? navigateTo(entry.path) : null">
                                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-800">
                                                                <template x-if="entry.is_network">
                                                                    <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                                                    </svg>
                                                                </template>
                                                                <template x-if="entry.is_dir && !entry.is_network">
                                                                    <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                                                    </svg>
                                                                </template>
                                                                <template x-if="!entry.is_dir && !entry.is_network">
                                                                    <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                                    </svg>
                                                                </template>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm text-slate-100 truncate" x-text="entry.name"></p>
                                                                <p class="text-xs text-slate-500" x-text="entry.is_network ? (entry.unc_path || 'Network Drive') : (entry.is_dir ? 'Folder' : formatBytes(entry.size))"></p>
                                                            </div>
                                                            <svg x-show="entry.is_dir" class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <div x-show="entries.length === 0 && !loading && !error" class="text-center py-12 text-sm text-slate-500">
                                            This folder is empty
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar -->
                            <div class="space-y-4">
                                <!-- DISK IMAGE MODE: Volume Selection Summary & Options -->
                                <template x-if="isDiskImageMode">
                                    <div class="space-y-4">
                                        <!-- Selected Volume -->
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Selected Volume</h4>
                                            <div x-show="selectedVolume" class="flex items-center gap-3 p-3 rounded-lg bg-cyan-500/10 border border-cyan-500/30">
                                                <div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                                    </svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-cyan-100" x-text="selectedVolume"></p>
                                                    <p class="text-xs text-cyan-300/70" x-text="selectedVolumeInfo?.label || 'Local Disk'"></p>
                                                </div>
                                            </div>
                                            <div x-show="!selectedVolume" class="text-center text-xs text-slate-500 py-4">
                                                Select a volume from the list
                                            </div>
                                        </div>

                                        <!-- Hidden inputs for disk image data -->
                                        <input type="hidden" id="localWizardDiskVolume">
                                        <input type="hidden" id="localWizardDiskVolumeSelect" value="">

                                        <!-- Image Format -->
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <label class="block text-xs uppercase tracking-wide text-slate-400 mb-2">Image Format</label>
                                            <select id="localWizardDiskFormat" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                                <option value="vhdx">VHDX (Windows)</option>
                                                <option value="raw">Raw (Linux)</option>
                                            </select>
                                            <p class="text-xs text-slate-500 mt-2">VHDX recommended for Windows, Raw for Linux systems.</p>
                                        </div>

                                        <!-- Temp Directory -->
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <label class="block text-xs uppercase tracking-wide text-slate-400 mb-2">Temp Directory (optional)</label>
                                            <input id="localWizardDiskTemp" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="C:\ProgramData\E3Backup\runs\tmp">
                                            <p class="text-xs text-slate-500 mt-2">Temporary storage for the disk image. Ensure enough free space.</p>
                                        </div>
                                    </div>
                                </template>

                                <!-- FILE BACKUP MODE: Folder Selection Summary -->
                                <template x-if="!isDiskImageMode">
                                    <div class="space-y-4">
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Selected</h4>
                                                <span class="text-xs text-cyan-300" x-text="selectedPaths.length"></span>
                                            </div>
                                            <div class="space-y-2 max-h-48 overflow-y-auto scrollbar-thin-dark">
                                                <template x-for="path in selectedPaths" :key="path">
                                                    <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-slate-800/60">
                                                        <svg class="w-3 h-3 text-cyan-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                                        </svg>
                                                        <span class="text-xs text-slate-200 truncate flex-1" x-text="path"></span>
                                                        <button type="button" class="p-1 hover:bg-slate-700 rounded" @click="removeSelection(path)">
                                                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                                <div x-show="selectedPaths.length === 0" class="text-center text-xs text-slate-500 py-4">
                                                    No folders selected yet
                                                </div>
                                            </div>
                                        </div>

                                        <div x-show="!isDiskImageMode" class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-2">
                                            <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Add manually</label>
                                            <div class="flex gap-2">
                                                <input type="text" x-model="manualPath" :disabled="isDiskImageMode" class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50 disabled:opacity-50 disabled:cursor-not-allowed" placeholder="C:\Data or /path" @keyup.enter="addManualPath()">
                                                <button type="button" :disabled="isDiskImageMode" class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm disabled:opacity-50 disabled:cursor-not-allowed" @click="addManualPath()">Add</button>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3">
                                            <div>
                                                <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Include globs</label>
                                                <textarea id="localWizardInclude" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" rows="2" placeholder="**"></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Exclude globs</label>
                                                <textarea id="localWizardExclude" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" rows="2" placeholder="**/temp/**"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Network Credentials (only for file backup mode) -->
                                <div x-show="hasNetworkPaths && !isDiskImageMode" class="rounded-xl border border-purple-500/30 bg-purple-900/20 p-4 space-y-3">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                        </svg>
                                        <h4 class="text-sm font-medium text-purple-200">Network Share Credentials</h4>
                                    </div>
                                    <p class="text-xs text-purple-300/70">Your selection includes network paths. The agent will need credentials to access these locations when running as a service.</p>
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Username</label>
                                            <input type="text" x-model="networkUsername" id="localWizardNetworkUsername" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="DOMAIN\username or user@domain.com" @input="syncCredentials()">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Password</label>
                                            <input type="password" x-model="networkPassword" id="localWizardNetworkPassword" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" :placeholder="(window.localWizardState?.editMode && window.localWizardState?.data?.has_network_password) ? 'Leave blank to keep current' : 'Network password'" @input="syncCredentials()">
                                            <template x-if="window.localWizardState?.editMode && window.localWizardState?.data?.has_network_password">
                                                <p class="text-xs text-emerald-400 mt-1">A password is saved. Leave blank to keep the current password.</p>
                                            </template>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Domain (optional)</label>
                                            <input type="text" x-model="networkDomain" id="localWizardNetworkDomain" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="MYDOMAIN" @input="syncCredentials()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </template>
                    </div>

                    <!-- Step 3: Schedule -->
                    <div class="wizard-step hidden" data-step="3" x-data="localWizardScheduleUI()" x-init="init()">
                        <label class="block text-sm font-medium text-slate-200 mb-3">Schedule</label>
                        
                        <!-- Hidden inputs for compatibility -->
                        <input type="hidden" id="localWizardTime" x-model="computedTime">
                        <input type="hidden" id="localWizardWeekday" x-model="firstSelectedWeekday">
                        <input type="hidden" id="localWizardCron" x-model="cronExpr">
                        
                        <div class="space-y-4">
                            <!-- Schedule Type (Custom Alpine Dropdown) -->
                            <div @click.away="scheduleDropdownOpen = false">
                                <label class="block text-xs text-slate-400 mb-1.5">Schedule Type</label>
                                <!-- Hidden input for form compatibility -->
                                <input type="hidden" id="localWizardScheduleType" x-model="scheduleType">
                                <div class="relative">
                                    <button type="button"
                                            @click="scheduleDropdownOpen = !scheduleDropdownOpen"
                                            class="relative w-full px-4 py-2.5 text-left bg-slate-800 border border-slate-700 rounded-lg shadow-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50 transition-colors hover:border-slate-600">
                                        <span class="flex items-center gap-3">
                                            <!-- Icon based on selected type -->
                                            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                                  :class="{
                                                      'bg-slate-700/50 text-slate-400': scheduleType === 'manual',
                                                      'bg-amber-500/20 text-amber-400': scheduleType === 'hourly',
                                                      'bg-sky-500/20 text-sky-400': scheduleType === 'daily',
                                                      'bg-violet-500/20 text-violet-400': scheduleType === 'weekly',
                                                      'bg-emerald-500/20 text-emerald-400': scheduleType === 'cron'
                                                  }">
                                                <template x-if="scheduleType === 'manual'">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                                                    </svg>
                                                </template>
                                                <template x-if="scheduleType === 'hourly'">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </template>
                                                <template x-if="scheduleType === 'daily'">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                                                    </svg>
                                                </template>
                                                <template x-if="scheduleType === 'weekly'">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                                    </svg>
                                                </template>
                                                <template x-if="scheduleType === 'cron'">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                                                    </svg>
                                                </template>
                                            </span>
                                            <span class="block truncate text-slate-100" x-text="scheduleTypeLabels[scheduleType] || 'Select schedule'"></span>
                                        </span>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                            <svg class="w-5 h-5 text-slate-400 transition-transform duration-200" :class="scheduleDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </span>
                                    </button>
                                    
                                    <!-- Dropdown Panel -->
                                    <div x-show="scheduleDropdownOpen"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                         class="absolute z-20 mt-2 w-full bg-slate-900 border border-slate-700 rounded-xl shadow-xl overflow-hidden"
                                         style="display: none;">
                                        <ul class="py-1">
                                            <!-- Manual -->
                                            <li @click="selectScheduleType('manual')"
                                                class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                :class="scheduleType === 'manual' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                <span class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                                                    </svg>
                                                </span>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium">Manual</p>
                                                    <p class="text-xs text-slate-500">Run on demand only</p>
                                                </div>
                                                <svg x-show="scheduleType === 'manual'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </li>
                                            <!-- Hourly -->
                                            <li @click="selectScheduleType('hourly')"
                                                class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                :class="scheduleType === 'hourly' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                <span class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </span>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium">Hourly</p>
                                                    <p class="text-xs text-slate-500">Run every hour at a set minute</p>
                                                </div>
                                                <svg x-show="scheduleType === 'hourly'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </li>
                                            <!-- Daily -->
                                            <li @click="selectScheduleType('daily')"
                                                class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                :class="scheduleType === 'daily' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                <span class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                                                    </svg>
                                                </span>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium">Daily</p>
                                                    <p class="text-xs text-slate-500">Run once per day at a set time</p>
                                                </div>
                                                <svg x-show="scheduleType === 'daily'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </li>
                                            <!-- Weekly -->
                                            <li @click="selectScheduleType('weekly')"
                                                class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                :class="scheduleType === 'weekly' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                <span class="w-8 h-8 rounded-lg bg-violet-500/20 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                                    </svg>
                                                </span>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium">Weekly</p>
                                                    <p class="text-xs text-slate-500">Run on selected days of the week</p>
                                                </div>
                                                <svg x-show="scheduleType === 'weekly'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </li>
                                            <!-- Cron -->
                                            <li @click="selectScheduleType('cron')"
                                                class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                :class="scheduleType === 'cron' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                <span class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0">
                                                    <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                                                    </svg>
                                                </span>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium">Custom (Cron)</p>
                                                    <p class="text-xs text-slate-500">Advanced cron expression</p>
                                                </div>
                                                <svg x-show="scheduleType === 'cron'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hourly: Minute selector -->
                            <div x-show="scheduleType === 'hourly'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                <label class="block text-xs text-slate-400 mb-2">Run at minute</label>
                                <div class="flex items-center gap-3">
                                    <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800 w-32">
                                        <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease" @click="hourlyMinute = Math.max(0, hourlyMinute - 1)">−</button>
                                        <input x-model.number="hourlyMinute" type="number" min="0" max="59" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                        <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase" @click="hourlyMinute = Math.min(59, hourlyMinute + 1)">+</button>
                                    </div>
                                    <span class="text-sm text-slate-400">past each hour</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-2">Job will run every hour at the specified minute (e.g., :15 means 1:15, 2:15, 3:15...)</p>
                            </div>
                            
                            <!-- Daily: Hour + Minute -->
                            <div x-show="scheduleType === 'daily'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                <label class="block text-xs text-slate-400 mb-2">Run daily at</label>
                                <div class="flex items-center gap-2">
                                    <!-- Hour stepper -->
                                    <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                        <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease hour" @click="dailyHour = (dailyHour - 1 + 24) % 24">−</button>
                                        <input x-model.number="dailyHour" type="number" min="0" max="23" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                        <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase hour" @click="dailyHour = (dailyHour + 1) % 24">+</button>
                                    </div>
                                    <span class="text-lg text-slate-400 font-medium">:</span>
                                    <!-- Minute stepper -->
                                    <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                        <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease minute" @click="dailyMinute = (dailyMinute - 1 + 60) % 60">−</button>
                                        <input x-model.number="dailyMinute" type="number" min="0" max="59" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                        <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase minute" @click="dailyMinute = (dailyMinute + 1) % 60">+</button>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 mt-2">Uses 24-hour format (e.g., 14:30 = 2:30 PM)</p>
                            </div>
                            
                            <!-- Weekly: Day checkboxes + Hour + Minute -->
                            <div x-show="scheduleType === 'weekly'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4 space-y-4">
                                <div>
                                    <label class="block text-xs text-slate-400 mb-2">Run on these days</label>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="day in weekDays" :key="day.value">
                                            <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-all"
                                                   :class="selectedWeekdays.includes(day.value) ? 'border-cyan-500 bg-cyan-500/20 text-cyan-200' : 'border-slate-600 bg-slate-800 text-slate-300 hover:border-slate-500'">
                                                <input type="checkbox" class="sr-only" :value="day.value" :checked="selectedWeekdays.includes(day.value)" @change="toggleWeekday(day.value)">
                                                <span class="text-sm font-medium" x-text="day.short"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-400 mb-2">At time</label>
                                    <div class="flex items-center gap-2">
                                        <!-- Hour stepper -->
                                        <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease hour" @click="weeklyHour = (weeklyHour - 1 + 24) % 24">−</button>
                                            <input x-model.number="weeklyHour" type="number" min="0" max="23" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase hour" @click="weeklyHour = (weeklyHour + 1) % 24">+</button>
                                        </div>
                                        <span class="text-lg text-slate-400 font-medium">:</span>
                                        <!-- Minute stepper -->
                                        <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease minute" @click="weeklyMinute = (weeklyMinute - 1 + 60) % 60">−</button>
                                            <input x-model.number="weeklyMinute" type="number" min="0" max="59" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase minute" @click="weeklyMinute = (weeklyMinute + 1) % 60">+</button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2">Uses 24-hour format</p>
                                </div>
                            </div>
                            
                            <!-- Cron: Expression input -->
                            <div x-show="scheduleType === 'cron'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                <label class="block text-xs text-slate-400 mb-2">Cron Expression</label>
                                <input type="text" x-model="cronExpr" placeholder="*/30 * * * *"
                                       class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2.5 text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                <p class="text-xs text-slate-500 mt-2">Standard cron format: minute hour day-of-month month day-of-week</p>
                            </div>
                            
                            <!-- Manual info -->
                            <div x-show="scheduleType === 'manual'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-slate-200">Run on demand only</p>
                                        <p class="text-xs text-slate-500 mt-1">This job will only run when you manually trigger it using the "Run Now" button.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Retention & Policy -->
                    <div class="wizard-step hidden" data-step="4" x-data="localWizardRetentionUI()" x-init="init()">
                        <!-- Hidden input for form compatibility -->
                        <input type="hidden" id="localWizardRetention" x-model="retentionJson">
                        <input type="hidden" id="localWizardPolicy">
                        
                        <div class="space-y-6">
                            <!-- Retention Policy Builder -->
                            <div>
                                <label class="block text-sm font-medium text-slate-200 mb-3">Retention Policy</label>
                                
                                <!-- Retention Mode Dropdown -->
                                <div @click.away="retentionDropdownOpen = false" class="mb-4">
                                    <div class="relative">
                                        <button type="button"
                                                @click="retentionDropdownOpen = !retentionDropdownOpen"
                                                class="relative w-full px-4 py-2.5 text-left bg-slate-800 border border-slate-700 rounded-lg shadow-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50 transition-colors hover:border-slate-600">
                                            <span class="flex items-center gap-3">
                                                <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                                      :class="{
                                                          'bg-slate-700/50 text-slate-400': mode === 'none',
                                                          'bg-sky-500/20 text-sky-400': mode === 'keep_last',
                                                          'bg-violet-500/20 text-violet-400': mode === 'keep_within',
                                                          'bg-amber-500/20 text-amber-400': mode === 'keep_daily'
                                                      }">
                                                    <template x-if="mode === 'none'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="mode === 'keep_last'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="mode === 'keep_within'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="mode === 'keep_daily'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </template>
                                                </span>
                                                <span class="block truncate text-slate-100" x-text="modeLabels[mode] || 'Select retention policy'"></span>
                                            </span>
                                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200" :class="retentionDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </span>
                                        </button>
                                        
                                        <!-- Dropdown Panel -->
                                        <div x-show="retentionDropdownOpen"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                             x-transition:leave="transition ease-in duration-150"
                                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                             x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                             class="absolute z-20 mt-2 w-full bg-slate-900 border border-slate-700 rounded-xl shadow-xl overflow-hidden"
                                             style="display: none;">
                                            <ul class="py-1">
                                                <!-- No Retention -->
                                                <li @click="selectMode('none')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'none' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">No Retention</p>
                                                        <p class="text-xs text-slate-500">Keep all backups indefinitely</p>
                                                    </div>
                                                    <svg x-show="mode === 'none'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                                <!-- Keep Last N -->
                                                <li @click="selectMode('keep_last')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'keep_last' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">Keep last ... Backups</p>
                                                        <p class="text-xs text-slate-500">Keep a fixed number of most recent backups</p>
                                                    </div>
                                                    <svg x-show="mode === 'keep_last'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                                <!-- Keep Within -->
                                                <li @click="selectMode('keep_within')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'keep_within' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-violet-500/20 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">Keep all backups in the last...</p>
                                                        <p class="text-xs text-slate-500">Keep backups within a time window</p>
                                                    </div>
                                                    <svg x-show="mode === 'keep_within'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                                <!-- Keep Daily -->
                                                <li @click="selectMode('keep_daily')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'keep_daily' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">Keep last ... backup at most one per day</p>
                                                        <p class="text-xs text-slate-500">Thin backups to one per day, keeping N days</p>
                                                    </div>
                                                    <svg x-show="mode === 'keep_daily'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep Last N Panel -->
                                <div x-show="mode === 'keep_last'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                    <label class="block text-xs text-slate-400 mb-2">Number of backups to keep</label>
                                    <div class="flex items-center gap-3">
                                        <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800 w-36">
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease" @click="keepLast = Math.max(1, keepLast - 1); syncToState()">−</button>
                                            <input x-model.number="keepLast" @input="syncToState()" type="number" min="1" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase" @click="keepLast++; syncToState()">+</button>
                                        </div>
                                        <span class="text-sm text-slate-400">backups</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2">Older backups beyond this count will be automatically removed.</p>
                                </div>
                                
                                <!-- Keep Within Panel -->
                                <div x-show="mode === 'keep_within'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                    <label class="block text-xs text-slate-400 mb-3">Keep all backups within (choose one)</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <!-- Days -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Days</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease days" @click="setWithinUnit('d', Math.max(0, withinDays - 1))">−</button>
                                                <input x-model.number="withinDays" @input="setWithinUnit('d', withinDays)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'd'" :class="withinUnit && withinUnit !== 'd' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase days" @click="setWithinUnit('d', withinDays + 1)">+</button>
                                            </div>
                                        </div>
                                        <!-- Weeks -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Weeks</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease weeks" @click="setWithinUnit('w', Math.max(0, withinWeeks - 1))">−</button>
                                                <input x-model.number="withinWeeks" @input="setWithinUnit('w', withinWeeks)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'w'" :class="withinUnit && withinUnit !== 'w' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase weeks" @click="setWithinUnit('w', withinWeeks + 1)">+</button>
                                            </div>
                                        </div>
                                        <!-- Months -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Months</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease months" @click="setWithinUnit('m', Math.max(0, withinMonths - 1))">−</button>
                                                <input x-model.number="withinMonths" @input="setWithinUnit('m', withinMonths)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'm'" :class="withinUnit && withinUnit !== 'm' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase months" @click="setWithinUnit('m', withinMonths + 1)">+</button>
                                            </div>
                                        </div>
                                        <!-- Years -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Years</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease years" @click="setWithinUnit('y', Math.max(0, withinYears - 1))">−</button>
                                                <input x-model.number="withinYears" @input="setWithinUnit('y', withinYears)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'y'" :class="withinUnit && withinUnit !== 'y' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase years" @click="setWithinUnit('y', withinYears + 1)">+</button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-3">All backups within this time window will be kept. Only one unit can be used at a time.</p>
                                </div>
                                
                                <!-- Keep Daily Panel -->
                                <div x-show="mode === 'keep_daily'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                    <label class="block text-xs text-slate-400 mb-2">Number of daily backups to keep</label>
                                    <div class="flex items-center gap-3">
                                        <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800 w-36">
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease" @click="keepDaily = Math.max(1, keepDaily - 1); syncToState()">−</button>
                                            <input x-model.number="keepDaily" @input="syncToState()" type="number" min="1" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase" @click="keepDaily++; syncToState()">+</button>
                                        </div>
                                        <span class="text-sm text-slate-400">daily backups</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2">Keeps one backup per day for the specified number of days. Multiple backups on the same day are thinned.</p>
                                </div>
                            </div>
                            
                            <!-- Advanced Settings (unchanged structure) -->
                            <div x-data="{ showAdvancedPolicy: false }">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-200">Advanced Settings</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer select-none">
                                        <span>Show advanced</span>
                                        <button @click="showAdvancedPolicy = !showAdvancedPolicy" type="button"
                                                class="relative w-9 h-5 rounded-full transition-colors"
                                                :class="showAdvancedPolicy ? 'bg-cyan-600' : 'bg-slate-700'">
                                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                                  :class="showAdvancedPolicy ? 'translate-x-4' : 'translate-x-0'"></span>
                                        </button>
                                    </label>
                                </div>
                                <div x-show="showAdvancedPolicy" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                     class="rounded-xl border border-slate-700 bg-slate-900/50 p-4 space-y-4" style="display:none;">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Bandwidth (KB/s)</label>
                                            <input id="localWizardBandwidth" type="number" value="0" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="0 = unlimited">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Parallel uploads</label>
                                            <input id="localWizardParallelism" type="number" value="16" min="1" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="16">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Compression</label>
                                            <select id="localWizardCompression" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                                <option value="zstd-default" selected>zstd-default</option>
                                                <option value="none">None</option>
                                                <option value="pgzip">pgzip</option>
                                                <option value="s2">s2</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="inline-flex items-center gap-2">
                                            <input id="localWizardDebugLogs" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                            <span class="text-sm text-slate-200">Enable detailed eazyBackup debug logs</span>
                                        </label>
                                        <p class="text-xs text-slate-500 mt-1">Adds more step-level events to the live progress view for troubleshooting.</p>
                                    </div>
                                    <div>
                                        <label class="inline-flex items-center gap-2">
                                            <input id="localWizardParallelDiskReads" type="checkbox" class="rounded border-slate-600 bg-slate-800" checked>
                                            <span class="text-sm text-slate-200">Enable parallel disk reads</span>
                                        </label>
                                        <p class="text-xs text-slate-500 mt-1">Recommended for Disk Image and Hyper-V. Disable if the disk is under heavy load.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="wizard-step hidden" data-step="5">
                        <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3 text-slate-100">
                            <p class="text-sm font-semibold mb-2">Review</p>
                            <pre id="localWizardReview" class="text-xs whitespace-pre-wrap leading-5 bg-slate-950 border border-slate-800 rounded-lg p-3 overflow-auto max-h-64"></pre>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex justify-between items-center px-6 py-4 border-t border-slate-800 shrink-0 bg-slate-950">
                <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100" onclick="localWizardPrev()">Back</button>
                <div class="flex gap-2">
                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100" onclick="closeLocalJobWizard()">Cancel</button>
                        <button type="button" id="localWizardNextBtn" data-local-wizard-next class="px-4 py-2 rounded-lg bg-sky-600 text-white" onclick="localWizardNext()">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>