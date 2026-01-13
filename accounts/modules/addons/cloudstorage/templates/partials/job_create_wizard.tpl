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
                                selectedName: '',
                                tenantFilter: '',
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
                                        this.selectedName = '';
                                        const hid = document.getElementById('localWizardAgentId');
                                        if (hid) hid.value = '';
                                        if (window.localWizardState?.data) {
                                            window.localWizardState.data.agent_id = '';
                                        }
                                    }
                                },
                                choose(opt) {
                                    this.selectedId = opt.id;
                                    this.selectedName = opt.hostname ? (opt.hostname + ' (ID ' + opt.id + ')') : ('Agent #' + opt.id);
                                    const hid = document.getElementById('localWizardAgentId');
                                    if (hid) hid.value = this.selectedId;
                                    if (window.localWizardState?.data) {
                                        window.localWizardState.data.agent_id = this.selectedId;
                                    }
                                    localWizardOnAgentSelected(this.selectedId);
                                    this.isOpen = false;
                                }
                            }" x-init="
                                load();
                                window.addEventListener('tenant-changed', (e) => {
                                    this.tenantFilter = e.detail?.tenantId || '';
                                    this.applyTenantFilter();
                                });
                            ">
                                <label class="block text-sm font-medium text-slate-200 mb-2">Agent</label>
                                <input type="hidden" id="localWizardAgentId">
                                <div class="relative">
                                    <button type="button" class="w-full px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 flex justify-between items-center"
                                            @click="isOpen = !isOpen">
                                        <span x-text="selectedName || (loading ? 'Loading agents…' : 'Select agent')"></span>
                                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-700 rounded-md shadow-lg max-h-60 overflow-auto" style="display:none;">
                                        <div class="p-2 border-b border-slate-800">
                                            <input type="text" x-model="search" placeholder="Search agents..." class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-md text-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                        </div>
                                        <template x-for="opt in filtered" :key="opt.id">
                                            <div class="px-3 py-2 text-slate-200 hover:bg-slate-800 cursor-pointer" @click="choose(opt)">
                                                <span x-text="opt.hostname ? (opt.hostname + ' (ID ' + opt.id + ')') : ('Agent #' + opt.id)"></span>
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
                                <label class="block text-sm font-medium text-slate-200 mb-2">Bucket</label>
                                <div
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
                                        }
                                    }"
                                    @click.away="isOpen=false"
                                >
                                    <input type="hidden" id="localWizardBucketId">
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
                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-slate-200 mb-2">Prefix (optional)</label>
                                    <input id="localWizardPrefix" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="backups/job123/">
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="px-3 py-2 rounded-md border border-slate-700 text-slate-200 hover:border-slate-500" onclick="openInlineBucketCreate()">
                                        Create new bucket
                                    </button>
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
                            if (this.isDiskImage) return 'Select a local disk volume to create an image backup';
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
                                                                <span x-show="vm.disks && vm.disks.length" x-text="vm.disks.length + ' disk(s)'"></span>
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
                                    <span class="text-sm text-slate-300">Local Disk Volumes</span>
                                    <span class="text-xs text-slate-500 ml-auto">Select one volume for disk image backup</span>
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

                                    <!-- DISK IMAGE MODE: Volume cards grid -->
                                    <div x-show="!loading && !error && isDiskImageMode" class="p-4">
                                        <div class="grid grid-cols-2 gap-3">
                                            <template x-for="entry in localVolumes" :key="entry.path">
                                                <button type="button" 
                                                        class="volume-card group p-4 rounded-xl border text-left transition-all"
                                                        :class="selectedVolume === entry.path 
                                                            ? 'border-cyan-500 bg-cyan-500/10 ring-2 ring-cyan-500/40' 
                                                            : 'border-slate-700 bg-slate-800/50 hover:border-slate-600 hover:bg-slate-800'"
                                                        @click="selectVolume(entry)">
                                                    <div class="flex items-start gap-3">
                                                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                                             :class="selectedVolume === entry.path ? 'bg-cyan-500/20' : 'bg-slate-700/50'">
                                                            <svg class="w-6 h-6" :class="selectedVolume === entry.path ? 'text-cyan-400' : 'text-blue-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                                            </svg>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-base font-semibold text-slate-100" x-text="entry.path || entry.name"></p>
                                                            <p class="text-sm text-slate-400 truncate" x-text="entry.label || 'Local Disk'"></p>
                                                            <div class="flex items-center gap-2 mt-1">
                                                                <span class="text-xs text-slate-500" x-text="entry.filesystem || ''"></span>
                                                                <span x-show="entry.size_bytes" class="text-xs text-slate-500">•</span>
                                                                <span x-show="entry.size_bytes" class="text-xs text-slate-500" x-text="formatBytes(entry.size_bytes)"></span>
                                                            </div>
                                                        </div>
                                                        <div class="shrink-0">
                                                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                                                                 :class="selectedVolume === entry.path ? 'border-cyan-500 bg-cyan-500' : 'border-slate-600'">
                                                                <svg x-show="selectedVolume === entry.path" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                        <div x-show="localVolumes.length === 0" class="text-center py-12 text-sm text-slate-500">
                                            No local disk volumes found
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
                                        <input type="hidden" id="localWizardDiskVolume" x-model="selectedVolume">
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
                                            <div class="space-y-2 max-h-48 overflow-y-auto">
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

                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-2">
                                            <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Add manually</label>
                                            <div class="flex gap-2">
                                                <input type="text" x-model="manualPath" class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="C:\Data or /path" @keyup.enter="addManualPath()">
                                                <button type="button" class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm" @click="addManualPath()">Add</button>
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

                    <!-- Step 3 -->
                    <div class="wizard-step hidden" data-step="3">
                        <label class="block text-sm font-medium text-slate-200 mb-2">Schedule</label>
                        <div class="grid md:grid-cols-2 gap-4">
                            <select id="localWizardScheduleType" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                <option value="manual">Manual</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="cron">Cron</option>
                            </select>
                            <input id="localWizardTime" type="time" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" />
                            <select id="localWizardWeekday" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                            <input id="localWizardCron" type="text" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="*/30 * * * *" />
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="wizard-step hidden" data-step="4">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-200 mb-2">Retention</label>
                                <textarea id="localWizardRetention" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" rows="4" placeholder="&#123;&quot;keep_last&quot;:30,&quot;keep_daily&quot;:7&#125;"></textarea>
                                <p class="text-xs text-slate-500 mt-1">JSON or simple values; kept server-side.</p>
                            </div>
                            <div x-data="{ showAdvancedPolicy: false }">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-slate-200">Backup Policy</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer select-none">
                                        <span>Advanced settings</span>
                                        <button @click="showAdvancedPolicy = !showAdvancedPolicy" type="button"
                                                class="relative w-9 h-5 rounded-full transition-colors"
                                                :class="showAdvancedPolicy ? 'bg-cyan-600' : 'bg-slate-700'">
                                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                                  :class="showAdvancedPolicy ? 'translate-x-4' : 'translate-x-0'"></span>
                                        </button>
                                    </label>
                                </div>
                                <textarea id="localWizardPolicy" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" rows="4" placeholder="&#123;&quot;compression&quot;:&quot;none&quot;,&quot;parallel_uploads&quot;:8&#125;"></textarea>
                                <div x-show="showAdvancedPolicy" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                     class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-3" style="display:none;">
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Bandwidth (KB/s)</label>
                                        <input id="localWizardBandwidth" type="number" value="0" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="0 = unlimited">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Parallel uploads</label>
                                        <input id="localWizardParallelism" type="number" value="8" min="1" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="8">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Compression</label>
                                        <select id="localWizardCompression" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                            <option value="none" selected>None</option>
                                            <option value="zstd-default">zstd-default</option>
                                            <option value="pgzip">pgzip</option>
                                            <option value="s2">s2</option>
                                        </select>
                                    </div>
                                </div>
                                <div x-show="showAdvancedPolicy" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                     class="mt-3" style="display:none;">
                                    <label class="inline-flex items-center gap-2">
                                        <input id="localWizardDebugLogs" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                        <span class="text-sm text-slate-200">Enable detailed eazyBackup debug logs</span>
                                    </label>
                                    <p class="text-xs text-slate-500 mt-1">Adds more step-level events to the live progress view for troubleshooting.</p>
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