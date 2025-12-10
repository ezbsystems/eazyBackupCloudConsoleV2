{* Cloud NAS - Settings Tab *}

<div class="max-w-2xl space-y-6">
    
    {* VFS Cache Settings *}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
        <h3 class="text-lg font-semibold text-white mb-1 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-sky-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
            VFS Cache
        </h3>
        <p class="text-xs text-slate-400 mb-5">Configure local caching for improved performance when accessing cloud files</p>
        
        <div class="space-y-5">
            {* Cache Mode Dropdown *}
            <div>
                <label class="text-sm text-slate-300 mb-2 block">Default Cache Mode</label>
                <div x-data="{ cacheModeOpen: false }" class="relative">
                    <button @click="cacheModeOpen = !cacheModeOpen"
                            @click.away="cacheModeOpen = false"
                            type="button"
                            class="w-full flex items-center justify-between rounded-lg bg-slate-800 border border-slate-700 px-3 py-2.5 text-sm text-left hover:border-slate-600 transition">
                        <span class="text-white">
                            <span x-show="settings.cache_mode === 'off'">Off (Direct streaming)</span>
                            <span x-show="settings.cache_mode === 'minimal'">Minimal (Read-only cache)</span>
                            <span x-show="settings.cache_mode === 'writes'">Writes (Cache writes locally)</span>
                            <span x-show="settings.cache_mode === 'full'">Full (Maximum performance)</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" 
                             class="w-4 h-4 text-slate-400 transition-transform" :class="cacheModeOpen ? 'rotate-180' : ''">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    
                    <div x-show="cacheModeOpen" x-transition
                         class="absolute z-20 mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 shadow-xl overflow-hidden">
                        <button @click="settings.cache_mode = 'off'; cacheModeOpen = false"
                                type="button"
                                class="w-full px-3 py-3 text-sm text-left hover:bg-slate-700 transition border-b border-slate-700"
                                :class="settings.cache_mode === 'off' ? 'bg-cyan-600/20' : ''">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-white">Off</span>
                                <svg x-show="settings.cache_mode === 'off'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Stream files directly from cloud without caching</p>
                        </button>
                        <button @click="settings.cache_mode = 'minimal'; cacheModeOpen = false"
                                type="button"
                                class="w-full px-3 py-3 text-sm text-left hover:bg-slate-700 transition border-b border-slate-700"
                                :class="settings.cache_mode === 'minimal' ? 'bg-cyan-600/20' : ''">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-white">Minimal</span>
                                <svg x-show="settings.cache_mode === 'minimal'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Cache file metadata for faster directory listings</p>
                        </button>
                        <button @click="settings.cache_mode = 'writes'; cacheModeOpen = false"
                                type="button"
                                class="w-full px-3 py-3 text-sm text-left hover:bg-slate-700 transition border-b border-slate-700"
                                :class="settings.cache_mode === 'writes' ? 'bg-cyan-600/20' : ''">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-white">Writes</span>
                                <svg x-show="settings.cache_mode === 'writes'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Cache writes locally before uploading</p>
                        </button>
                        <button @click="settings.cache_mode = 'full'; cacheModeOpen = false"
                                type="button"
                                class="w-full px-3 py-3 text-sm text-left hover:bg-slate-700 transition"
                                :class="settings.cache_mode === 'full' ? 'bg-cyan-600/20' : ''">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-white">Full</span>
                                <svg x-show="settings.cache_mode === 'full'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Full caching for maximum performance (recommended)</p>
                        </button>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-2">Full mode provides the best performance for frequently accessed files</p>
            </div>
            
            {* Cache Size *}
            <div>
                <label class="text-sm text-slate-300 mb-2 block">Cache Size Limit</label>
                <div class="flex items-center gap-3">
                    <input type="number" 
                           x-model.number="settings.cache_size_gb" 
                           min="1" 
                           max="500"
                           class="w-28 rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 focus:border-cyan-500">
                    <span class="text-slate-400 text-sm">GB</span>
                </div>
                <p class="text-xs text-slate-500 mt-2">Maximum disk space to use for VFS cache</p>
            </div>
        </div>
    </div>
    
    {* Bandwidth Settings *}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
        <h3 class="text-lg font-semibold text-white mb-1 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-amber-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            Bandwidth
        </h3>
        <p class="text-xs text-slate-400 mb-5">Limit bandwidth usage to prevent network saturation</p>
        
        <div class="space-y-5">
            {* Enable toggle *}
            <div class="flex items-center justify-between">
                <div>
                    <label class="text-sm text-slate-300">Enable Bandwidth Limiting</label>
                    <p class="text-xs text-slate-500 mt-1">Prevent Cloud NAS from saturating your connection</p>
                </div>
                <button @click="settings.bandwidth_limit_enabled = !settings.bandwidth_limit_enabled"
                        type="button"
                        class="toggle-switch"
                        :class="settings.bandwidth_limit_enabled ? 'bg-cyan-600 active' : 'bg-slate-700'">
                    <span class="toggle-knob"></span>
                </button>
            </div>
            
            {* Bandwidth limits *}
            <div x-show="settings.bandwidth_limit_enabled" x-transition class="pl-4 border-l-2 border-slate-700 space-y-4">
                <div>
                    <label class="text-sm text-slate-300 mb-2 block">Download Limit</label>
                    <div class="flex items-center gap-3">
                        <input type="number" 
                               x-model.number="settings.bandwidth_download_kbps" 
                               min="0"
                               class="w-28 rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 focus:border-cyan-500">
                        <span class="text-slate-400 text-sm">KB/s</span>
                        <span class="text-slate-600 text-xs">(0 = unlimited)</span>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-300 mb-2 block">Upload Limit</label>
                    <div class="flex items-center gap-3">
                        <input type="number" 
                               x-model.number="settings.bandwidth_upload_kbps" 
                               min="0"
                               class="w-28 rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 focus:border-cyan-500">
                        <span class="text-slate-400 text-sm">KB/s</span>
                        <span class="text-slate-600 text-xs">(0 = unlimited)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {* Default Mount Options *}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
        <h3 class="text-lg font-semibold text-white mb-1 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
            </svg>
            Default Mount Options
        </h3>
        <p class="text-xs text-slate-400 mb-5">Default settings applied when creating new mounts</p>
        
        <div class="space-y-5">
            {* Auto-mount toggle *}
            <div class="flex items-center justify-between">
                <div>
                    <label class="text-sm text-slate-300">Auto-mount on Windows startup</label>
                    <p class="text-xs text-slate-500 mt-1">Reconnect drives automatically when the agent starts</p>
                </div>
                <button @click="settings.auto_mount = !settings.auto_mount"
                        type="button"
                        class="toggle-switch"
                        :class="settings.auto_mount ? 'bg-cyan-600 active' : 'bg-slate-700'">
                    <span class="toggle-knob"></span>
                </button>
            </div>
            
            {* Read-only toggle *}
            <div class="flex items-center justify-between">
                <div>
                    <label class="text-sm text-slate-300">Default to read-only</label>
                    <p class="text-xs text-slate-500 mt-1">Prevent accidental modifications to cloud files</p>
                </div>
                <button @click="settings.default_read_only = !settings.default_read_only"
                        type="button"
                        class="toggle-switch"
                        :class="settings.default_read_only ? 'bg-cyan-600 active' : 'bg-slate-700'">
                    <span class="toggle-knob"></span>
                </button>
            </div>
        </div>
    </div>
    
    {* Agent Selection *}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
        <h3 class="text-lg font-semibold text-white mb-1 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-emerald-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
            </svg>
            Default Agent
        </h3>
        <p class="text-xs text-slate-400 mb-4">Select which agent to use for mount operations</p>
        
        {* Agent Dropdown *}
        <div x-data="{ agentDropdownOpen: false }" class="relative">
            <button @click="agentDropdownOpen = !agentDropdownOpen"
                    @click.away="agentDropdownOpen = false"
                    type="button"
                    class="w-full flex items-center justify-between rounded-lg bg-slate-800 border border-slate-700 px-3 py-2.5 text-sm text-left hover:border-slate-600 transition">
                <span x-text="selectedAgentId ? agents.find(a => a.id == selectedAgentId)?.hostname || 'Agent #' + selectedAgentId : 'Select an agent...'" 
                      :class="selectedAgentId ? 'text-white' : 'text-slate-400'"></span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" 
                     class="w-4 h-4 text-slate-400 transition-transform" :class="agentDropdownOpen ? 'rotate-180' : ''">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            
            <div x-show="agentDropdownOpen" x-transition
                 class="absolute z-20 mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 shadow-xl max-h-60 overflow-y-auto">
                <template x-if="agents.length === 0">
                    <div class="px-3 py-4 text-sm text-slate-400 text-center">
                        No agents available. <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_agents" class="text-cyan-400 hover:underline">Set up an agent</a>
                    </div>
                </template>
                <template x-for="agent in agents" :key="agent.id">
                    <button @click="selectedAgentId = agent.id; agentDropdownOpen = false"
                            type="button"
                            class="w-full px-3 py-2.5 text-sm text-left hover:bg-slate-700 transition flex items-center justify-between"
                            :class="selectedAgentId == agent.id ? 'bg-cyan-600/20 text-cyan-300' : 'text-slate-200'">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span :class="agent.status === 'active' ? 'bg-emerald-500' : 'bg-slate-600'" class="relative inline-flex rounded-full h-2 w-2"></span>
                            </span>
                            <span x-text="agent.hostname || 'Agent #' + agent.id" class="truncate"></span>
                        </div>
                        <svg x-show="selectedAgentId == agent.id" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </button>
                </template>
            </div>
        </div>
    </div>
    
    {* Save Button *}
    <div class="flex items-center gap-4">
        <button @click="saveSettings()" 
                class="px-6 py-2.5 rounded-lg bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-semibold shadow-lg shadow-cyan-500/20 transition">
            Save Settings
        </button>
        <span x-show="settingsSaved" x-transition class="text-sm text-emerald-400 flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            Saved
        </span>
    </div>
</div>

