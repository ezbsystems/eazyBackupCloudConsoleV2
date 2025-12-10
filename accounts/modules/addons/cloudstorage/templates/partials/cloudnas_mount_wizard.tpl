{* Cloud NAS - Mount Wizard Modal *}

<div x-show="showMountWizard" x-cloak 
     class="fixed inset-0 z-50 overflow-y-auto"
     @keydown.escape.window="showMountWizard = false">
    <div class="flex min-h-screen items-center justify-center p-4">
        {* Backdrop *}
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="showMountWizard = false"></div>
        
        {* Modal *}
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            
            {* Header *}
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-cyan-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                    </svg>
                    <span x-text="newMount.id ? 'Edit Mount' : 'Mount Cloud Drive'"></span>
                </h3>
                <button @click="showMountWizard = false" class="text-slate-400 hover:text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            {* Steps Indicator *}
            <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/50">
                <div class="flex items-center justify-between">
                    <template x-for="(step, i) in ['Select Bucket', 'Configure', 'Review']" :key="i">
                        <div class="flex items-center">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition"
                                     :class="wizardStep > i ? 'bg-cyan-500 text-white' : wizardStep === i ? 'bg-cyan-500/20 text-cyan-400 ring-2 ring-cyan-500' : 'bg-slate-800 text-slate-500'">
                                    <template x-if="wizardStep > i">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </template>
                                    <template x-if="wizardStep <= i">
                                        <span x-text="i + 1"></span>
                                    </template>
                                </div>
                                <span class="ml-2 text-xs hidden sm:inline" :class="wizardStep >= i ? 'text-slate-200' : 'text-slate-500'" x-text="step"></span>
                            </div>
                            <template x-if="i < 2">
                                <div class="w-8 sm:w-12 h-px mx-2" :class="wizardStep > i ? 'bg-cyan-500' : 'bg-slate-700'"></div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
            
            {* Step Content *}
            <div class="px-6 py-6 max-h-[60vh] overflow-y-auto">
                
                {* Step 1: Select Bucket *}
                <div x-show="wizardStep === 0">
                    {* Agent selector *}
                    <div class="mb-5">
                        <label class="text-sm text-slate-300 mb-2 block">Select Agent</label>
                        <div x-data="{ agentOpen: false }" @click.away="agentOpen = false" class="relative">
                            <button @click="agentOpen = !agentOpen"
                                    type="button"
                                    class="w-full flex items-center justify-between rounded-lg bg-slate-900 border border-slate-700 px-3 py-2.5 text-sm text-left hover:border-slate-600 transition">
                                <span x-text="newMount.agent_id ? agents.find(a => a.id == newMount.agent_id)?.hostname || 'Agent #' + newMount.agent_id : 'Select an agent...'" 
                                      :class="newMount.agent_id ? 'text-white' : 'text-slate-400'"></span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" 
                                     class="w-4 h-4 text-slate-400 transition-transform" :class="agentOpen ? 'rotate-180' : ''">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            
                            <div x-show="agentOpen" x-transition
                                 class="absolute z-30 mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 shadow-xl max-h-48 overflow-y-auto">
                                <template x-for="agent in agents" :key="agent.id">
                                    <button @click="newMount.agent_id = agent.id; agentOpen = false"
                                            type="button"
                                            class="w-full px-3 py-2.5 text-sm text-left hover:bg-slate-700 transition flex items-center justify-between"
                                            :class="newMount.agent_id == agent.id ? 'bg-cyan-600/20 text-cyan-300' : 'text-slate-200'">
                                        <div class="flex items-center gap-2">
                                            <span class="relative flex h-2 w-2">
                                                <span :class="agent.status === 'active' ? 'bg-emerald-500' : 'bg-slate-600'" class="relative inline-flex rounded-full h-2 w-2"></span>
                                            </span>
                                            <span x-text="agent.hostname || 'Agent #' + agent.id"></span>
                                        </div>
                                        <svg x-show="newMount.agent_id == agent.id" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    
                    {* Bucket selector *}
                    <div class="mb-5">
                        <label class="text-sm text-slate-300 mb-2 block">Select Bucket</label>
                        <div x-data="{ bucketOpen: false, search: '' }" @click.away="bucketOpen = false" class="relative">
                            <button @click="bucketOpen = !bucketOpen"
                                    type="button"
                                    class="w-full flex items-center justify-between rounded-lg bg-slate-900 border border-slate-700 px-3 py-2.5 text-sm text-left hover:border-slate-600 transition">
                                <span x-text="newMount.bucket || 'Choose a bucket...'" 
                                      :class="newMount.bucket ? 'text-white' : 'text-slate-400'"></span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" 
                                     class="w-4 h-4 text-slate-400 transition-transform" :class="bucketOpen ? 'rotate-180' : ''">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            
                            <div x-show="bucketOpen" x-transition
                                 class="absolute z-30 mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 shadow-xl overflow-hidden">
                                {* Search input *}
                                <div class="p-2 border-b border-slate-700">
                                    <input type="text" x-model="search" placeholder="Search buckets..."
                                           class="w-full rounded-md bg-slate-900 border border-slate-600 px-3 py-2 text-sm text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                                </div>
                                <div class="max-h-48 overflow-y-auto">
                                    <template x-if="loadingBuckets">
                                        <div class="px-3 py-4 text-sm text-slate-400 text-center">Loading buckets...</div>
                                    </template>
                                    <template x-if="!loadingBuckets && buckets.length === 0">
                                        <div class="px-3 py-4 text-sm text-slate-400 text-center">No buckets found</div>
                                    </template>
                                    <template x-for="bucket in buckets.filter(b => !search || b.name.toLowerCase().includes(search.toLowerCase()))" :key="bucket.name">
                                        <button @click="newMount.bucket = bucket.name; bucketOpen = false; search = ''"
                                                type="button"
                                                class="w-full px-3 py-2.5 text-sm text-left hover:bg-slate-700 transition flex items-center justify-between"
                                                :class="newMount.bucket === bucket.name ? 'bg-cyan-600/20 text-cyan-300' : 'text-slate-200'">
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-400">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                                </svg>
                                                <span x-text="bucket.name"></span>
                                            </div>
                                            <svg x-show="newMount.bucket === bucket.name" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {* Prefix input *}
                    <div>
                        <label class="text-sm text-slate-300 mb-2 block">Prefix / Subfolder (optional)</label>
                        <input type="text" x-model="newMount.prefix" placeholder="e.g., documents/ or backups/2024/"
                               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 focus:border-cyan-500">
                        <p class="text-xs text-slate-500 mt-2">Mount only a specific folder within the bucket. Leave empty to mount the entire bucket.</p>
                    </div>
                </div>
                
                {* Step 2: Configure *}
                <div x-show="wizardStep === 1">
                    {* Drive letter selector *}
                    <div class="mb-5">
                        <label class="text-sm text-slate-300 mb-2 block">Drive Letter</label>
                        <div x-data="{ letterOpen: false }" @click.away="letterOpen = false" class="relative">
                            <button @click="letterOpen = !letterOpen"
                                    type="button"
                                    class="w-full flex items-center justify-between rounded-lg bg-slate-900 border border-slate-700 px-3 py-2.5 text-sm text-left hover:border-slate-600 transition">
                                <span class="text-white font-medium" x-text="newMount.drive_letter + ':'"></span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" 
                                     class="w-4 h-4 text-slate-400 transition-transform" :class="letterOpen ? 'rotate-180' : ''">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            
                            <div x-show="letterOpen" x-transition
                                 class="absolute z-30 mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 shadow-xl max-h-48 overflow-y-auto">
                                <div class="grid grid-cols-6 gap-1 p-2">
                                    <template x-for="letter in availableDriveLetters" :key="letter">
                                        <button @click="newMount.drive_letter = letter; letterOpen = false"
                                                type="button"
                                                class="px-3 py-2 text-sm text-center rounded-md transition font-medium"
                                                :class="newMount.drive_letter === letter ? 'bg-cyan-500 text-white' : 'bg-slate-700 text-slate-200 hover:bg-slate-600'">
                                            <span x-text="letter + ':'"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Select which drive letter to use for this mount</p>
                    </div>
                    
                    {* Options *}
                    <div class="space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" x-model="newMount.read_only" class="sr-only peer">
                                <div class="w-5 h-5 rounded border border-slate-600 bg-slate-800 peer-checked:bg-cyan-500 peer-checked:border-cyan-500 transition flex items-center justify-center">
                                    <svg x-show="newMount.read_only" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 text-white">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <span class="text-sm text-slate-300 group-hover:text-white transition">Read-only mode</span>
                                <p class="text-xs text-slate-500">Prevent modifications to files on this drive</p>
                            </div>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" x-model="newMount.persistent" class="sr-only peer">
                                <div class="w-5 h-5 rounded border border-slate-600 bg-slate-800 peer-checked:bg-cyan-500 peer-checked:border-cyan-500 transition flex items-center justify-center">
                                    <svg x-show="newMount.persistent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 text-white">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <span class="text-sm text-slate-300 group-hover:text-white transition">Mount on startup</span>
                                <p class="text-xs text-slate-500">Auto-reconnect this drive when Windows starts</p>
                            </div>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" x-model="newMount.enable_cache" class="sr-only peer">
                                <div class="w-5 h-5 rounded border border-slate-600 bg-slate-800 peer-checked:bg-cyan-500 peer-checked:border-cyan-500 transition flex items-center justify-center">
                                    <svg x-show="newMount.enable_cache" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 text-white">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <span class="text-sm text-slate-300 group-hover:text-white transition">Enable VFS caching</span>
                                <p class="text-xs text-slate-500">Cache files locally for better performance</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                {* Step 3: Review *}
                <div x-show="wizardStep === 2">
                    <div class="rounded-xl bg-slate-900 border border-slate-700 overflow-hidden">
                        <div class="p-4 border-b border-slate-700 bg-slate-800/50">
                            <div class="flex items-center gap-3">
                                <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white font-bold text-xl shadow-lg">
                                    <span x-text="newMount.drive_letter + ':'"></span>
                                </div>
                                <div>
                                    <p class="text-lg font-semibold text-white" x-text="newMount.bucket"></p>
                                    <p class="text-xs text-slate-400" x-text="newMount.prefix || 'Root folder'"></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Agent</span>
                                <span class="text-white" x-text="agents.find(a => a.id == newMount.agent_id)?.hostname || 'Agent #' + newMount.agent_id"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Drive Letter</span>
                                <span class="text-white font-medium" x-text="newMount.drive_letter + ':'"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Access Mode</span>
                                <span class="text-white" x-text="newMount.read_only ? 'Read-only' : 'Read/Write'"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">Auto-mount</span>
                                <span class="text-white" x-text="newMount.persistent ? 'Yes' : 'No'"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-400">VFS Caching</span>
                                <span class="text-white" x-text="newMount.enable_cache ? 'Enabled' : 'Disabled'"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 rounded-lg bg-cyan-500/10 border border-cyan-500/30 p-3">
                        <p class="text-xs text-cyan-200 flex items-start gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mt-0.5 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <span>The drive will be mounted immediately after creation.</span>
                        </p>
                    </div>
                </div>
            </div>
            
            {* Footer *}
            <div class="flex justify-between border-t border-slate-800 px-6 py-4 bg-slate-900/50">
                <button x-show="wizardStep > 0" @click="wizardStep--" 
                        type="button"
                        class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition">
                    Back
                </button>
                <div x-show="wizardStep === 0"></div>
                
                <div class="flex gap-3">
                    <button @click="showMountWizard = false" 
                            type="button"
                            class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition">
                        Cancel
                    </button>
                    <button x-show="wizardStep < 2" @click="wizardStep++" 
                            :disabled="!canProceed"
                            type="button"
                            class="px-5 py-2 rounded-lg bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold shadow-lg shadow-cyan-500/20 transition"
                            style="background: linear-gradient(to right, rgb(8, 145, 178), rgb(37, 99, 235));">
                        Next
                    </button>
                    <button x-show="wizardStep === 2" @click="createMount()" 
                            type="button"
                            class="px-5 py-2 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white text-sm font-semibold shadow-lg shadow-emerald-500/20 transition flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                        </svg>
                        Mount Drive
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

