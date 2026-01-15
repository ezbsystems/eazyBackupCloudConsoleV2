<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-auto">
        
        {assign var="activeNav" value="cloudnas"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}
        
        {* Glass panel container *}
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6"
             x-data="cloudNAS()" x-init="init()">
            
            {* Custom button styles *}
            <style>
                .btn-mount {
                    display: inline-flex; align-items: center; gap: 0.5rem;
                    border-radius: 9999px; padding: 0.5rem 1.25rem;
                    font-size: 0.875rem; font-weight: 600;
                    color: rgb(15 23 42);
                    background-image: linear-gradient(to right, rgb(6 182 212), rgb(59 130 246));
                    box-shadow: 0 1px 2px rgba(0,0,0,0.25);
                    border: 1px solid rgba(6,182,212,0.4);
                    transition: transform .15s ease, box-shadow .2s ease;
                }
                .btn-mount:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(6,182,212,0.25); }
                .btn-mount:active { transform: translateY(0); box-shadow: 0 1px 2px rgba(0,0,0,0.25); }
                .btn-mount:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
                
                .toggle-switch {
                    position: relative;
                    width: 2.75rem;
                    height: 1.5rem;
                    border-radius: 9999px;
                    transition: background-color 0.2s;
                    cursor: pointer;
                }
                .toggle-switch .toggle-knob {
                    position: absolute;
                    top: 0.125rem;
                    left: 0.125rem;
                    width: 1.25rem;
                    height: 1.25rem;
                    background: white;
                    border-radius: 9999px;
                    transition: transform 0.2s;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                }
                .toggle-switch.active .toggle-knob {
                    transform: translateX(1.25rem);
                }
            </style>
            
            {* Header with icon and title *}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-gradient-to-br from-cyan-500/20 to-blue-600/20 border border-cyan-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-cyan-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-semibold text-white flex items-center gap-2">
                            Cloud NAS
                            <span class="inline-flex items-center rounded-full bg-cyan-500/15 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-cyan-200 border border-cyan-400/40">
                                Beta
                            </span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Mount your cloud storage as a local Windows drive</p>
                    </div>
                </div>
                
                {* Quick Mount Button *}
                <button @click="openMountWizard()" :disabled="!hasAgent" class="mt-4 sm:mt-0 btn-mount">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Mount Drive
                </button>
            </div>
            
            {* Beta Notice *}
            <div class="mb-6 rounded-xl border border-cyan-500/30 bg-cyan-500/10 px-4 py-3 text-xs text-cyan-100 flex items-start gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-[2px] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <div>
                    <p class="font-semibold text-cyan-100 text-[0.75rem] uppercase tracking-wide">Cloud NAS Beta</p>
                    <p class="mt-1 text-[0.75rem] leading-relaxed text-cyan-100/90">
                        Mount S3 buckets as local Windows drives using the backup agent. 
                        <a href="https://winfsp.dev/rel/" target="_blank" class="underline hover:text-white">Download WinFSP</a>
                    </p>
                </div>
            </div>
            
            {* Inner Tab Navigation *}
            <div class="mb-6 border-b border-slate-800">
                <nav class="flex gap-1 sm:gap-6 text-sm overflow-x-auto">
                    <button @click="activeTab = 'drives'" 
                            :class="activeTab === 'drives' ? 'text-cyan-400 border-cyan-400' : 'text-slate-400 border-transparent hover:text-slate-200'"
                            class="pb-3 border-b-2 font-medium transition whitespace-nowrap px-2">
                        <span class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                            My Drives
                        </span>
                    </button>
                    <button @click="activeTab = 'timemachine'"
                            :class="activeTab === 'timemachine' ? 'text-cyan-400 border-cyan-400' : 'text-slate-400 border-transparent hover:text-slate-200'"
                            class="pb-3 border-b-2 font-medium transition whitespace-nowrap px-2">
                        <span class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Time Machine
                        </span>
                    </button>
                    <button @click="activeTab = 'settings'"
                            :class="activeTab === 'settings' ? 'text-cyan-400 border-cyan-400' : 'text-slate-400 border-transparent hover:text-slate-200'"
                            class="pb-3 border-b-2 font-medium transition whitespace-nowrap px-2">
                        <span class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.220-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </span>
                    </button>
                </nav>
            </div>
            
            {* Tab Content - My Drives *}
            <div x-show="activeTab === 'drives'" x-cloak>
                {include file="modules/addons/cloudstorage/templates/partials/cloudnas_drives.tpl"}
            </div>
            
            {* Tab Content - Time Machine *}
            <div x-show="activeTab === 'timemachine'" x-cloak>
                {include file="modules/addons/cloudstorage/templates/partials/cloudnas_timemachine.tpl"}
            </div>
            
            {* Tab Content - Settings *}
            <div x-show="activeTab === 'settings'" x-cloak>
                {include file="modules/addons/cloudstorage/templates/partials/cloudnas_settings.tpl"}
            </div>
            
            {* Mount Wizard Modal *}
            {include file="modules/addons/cloudstorage/templates/partials/cloudnas_mount_wizard.tpl"}
            
            {* Confirmation Modal *}
            <div x-show="showConfirmModal" x-cloak
                 class="fixed inset-0 z-[60] overflow-y-auto"
                 @keydown.escape.window="closeConfirm()">
                <div class="flex min-h-screen items-center justify-center p-4">
                    {* Backdrop *}
                    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="closeConfirm()"></div>
                    
                    {* Modal *}
                    <div class="relative w-full max-w-md rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95">
                        
                        {* Icon & Title *}
                        <div class="px-6 pt-6 pb-2">
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center"
                                     :class="confirmDanger ? 'bg-rose-500/20' : 'bg-amber-500/20'">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" 
                                         class="w-6 h-6" :class="confirmDanger ? 'text-rose-400' : 'text-amber-400'">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-white" x-text="confirmTitle"></h3>
                                </div>
                            </div>
                        </div>
                        
                        {* Message *}
                        <div class="px-6 py-4">
                            <p class="text-sm text-slate-300 leading-relaxed" x-text="confirmMessage"></p>
                        </div>
                        
                        {* Actions *}
                        <div class="flex justify-end gap-3 border-t border-slate-800 px-6 py-4 bg-slate-900/50 rounded-b-2xl">
                            <button @click="closeConfirm()" 
                                    type="button"
                                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition">
                                Cancel
                            </button>
                            <button @click="executeConfirm()" 
                                    type="button"
                                    class="px-4 py-2 rounded-lg text-white text-sm font-semibold transition"
                                    :class="confirmDanger ? 'bg-rose-600 hover:bg-rose-500' : 'bg-cyan-600 hover:bg-cyan-500'">
                                <span x-text="confirmDanger ? 'Delete' : 'Confirm'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

{literal}
<script>
function cloudNAS() {
    return {
        // Tab state
        activeTab: 'drives',
        
        // Agent state
        hasAgent: false,
        agents: [],
        selectedAgentId: null,
        agentOnline: false,
        
        // Mounts data
        mounts: [],
        loadingMounts: false,
        
        // Buckets data
        buckets: [],
        loadingBuckets: false,
        
        // Time Machine
        backupJobs: [],
        snapshots: [],
        selectedJobId: null,
        selectedSnapshot: null,
        timeSliderValue: 0,
        mountedSnapshot: null,
        
        // Settings
        settings: {
            cache_mode: 'full',
            cache_size_gb: 10,
            bandwidth_limit_enabled: false,
            bandwidth_download_kbps: 0,
            bandwidth_upload_kbps: 0,
            auto_mount: true,
            default_read_only: false
        },
        settingsSaved: false,
        
        // Mount wizard state
        showMountWizard: false,
        wizardStep: 0,
        newMount: {
            bucket: '',
            prefix: '',
            drive_letter: 'Z',
            read_only: false,
            persistent: true,
            enable_cache: true,
            cache_mode: 'full',
            agent_id: null
        },
        
        // Available drive letters
        availableDriveLetters: [],
        
        // Status polling
        statusPollInterval: null,
        
        // Confirmation modal
        showConfirmModal: false,
        confirmTitle: '',
        confirmMessage: '',
        confirmAction: null,
        confirmDanger: false,
        
        // Computed
        get totalMountedStorage() {
            const mounted = this.mounts.filter(m => m.status === 'mounted');
            if (!mounted.length) return '—';
            // This would need real data from the agent
            return mounted.length + ' drive(s)';
        },
        
        get cacheUsed() {
            // This would need real data from the agent
            return '0 GB';
        },
        
        get canProceed() {
            if (this.wizardStep === 0) {
                return this.newMount.bucket !== '' && this.newMount.agent_id !== null;
            }
            if (this.wizardStep === 1) {
                return this.newMount.drive_letter !== '';
            }
            return true;
        },
        
        // Initialize
        async init() {
            await this.loadAgents();
            await this.loadMounts();
            await this.loadBuckets();
            await this.loadSettings();
            await this.loadBackupJobs();
            this.calculateAvailableDriveLetters();
            this.startStatusPolling();
        },
        
        // Start polling for status updates when mounts are pending
        startStatusPolling() {
            // Clear any existing interval
            if (this.statusPollInterval) {
                clearInterval(this.statusPollInterval);
            }
            // Poll every 3 seconds
            this.statusPollInterval = setInterval(() => {
                this.checkPendingMounts();
            }, 3000);
        },
        
        // Check if there are pending mounts and refresh if needed
        async checkPendingMounts() {
            const pendingMounts = this.mounts.filter(m => 
                m.status === 'mounting' || m.status === 'unmounting'
            );
            if (pendingMounts.length > 0) {
                await this.loadMounts();
                this.calculateAvailableDriveLetters();
            }
        },
        
        // Load agents
        async loadAgents() {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/agent_list.php');
                const data = await res.json();
                if (data.status === 'success') {
                    this.agents = data.agents || [];
                    this.hasAgent = this.agents.length > 0;
                    // Auto-select first active agent
                    const activeAgent = this.agents.find(a => a.status === 'active');
                    if (activeAgent) {
                        this.selectedAgentId = activeAgent.id;
                        this.newMount.agent_id = activeAgent.id;
                        this.agentOnline = true;
                    }
                }
            } catch (e) {
                console.error('Failed to load agents:', e);
            }
        },
        
        // Load mounts
        async loadMounts() {
            this.loadingMounts = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_list_mounts.php');
                const data = await res.json();
                if (data.status === 'success') {
                    this.mounts = data.mounts || [];
                }
            } catch (e) {
                console.error('Failed to load mounts:', e);
            }
            this.loadingMounts = false;
        },
        
        // Load buckets
        async loadBuckets() {
            this.loadingBuckets = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/bucket_list.php');
                const data = await res.json();
                if (data.status === 'success') {
                    this.buckets = data.buckets || [];
                }
            } catch (e) {
                console.error('Failed to load buckets:', e);
            }
            this.loadingBuckets = false;
        },
        
        // Load settings
        async loadSettings() {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_settings.php');
                const data = await res.json();
                if (data.status === 'success' && data.settings) {
                    this.settings = { ...this.settings, ...data.settings };
                }
            } catch (e) {
                console.error('Failed to load settings:', e);
            }
        },
        
        // Save settings
        async saveSettings() {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.settings)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.settingsSaved = true;
                    this.showToast('Settings saved successfully', 'success');
                    setTimeout(() => { this.settingsSaved = false; }, 3000);
                } else {
                    this.showToast(data.message || 'Failed to save settings', 'error');
                }
            } catch (e) {
                console.error('Failed to save settings:', e);
                this.showToast('Failed to save settings', 'error');
            }
        },
        
        // Load backup jobs for time machine
        async loadBackupJobs() {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_list_jobs.php');
                const data = await res.json();
                if (data.status === 'success') {
                    // Filter to only show eazyBackup Archive jobs (engine=kopia in DB) which have snapshots
                    this.backupJobs = (data.jobs || []).filter(j => j.engine === 'kopia');
                }
            } catch (e) {
                console.error('Failed to load backup jobs:', e);
            }
        },
        
        // Load snapshots for selected job
        async loadSnapshots() {
            if (!this.selectedJobId) {
                this.snapshots = [];
                return;
            }
            try {
                const res = await fetch(`modules/addons/cloudstorage/api/cloudbackup_list_snapshots.php?job_id=${this.selectedJobId}`);
                const data = await res.json();
                if (data.status === 'success') {
                    this.snapshots = data.snapshots || [];
                    this.timeSliderValue = 0;
                    if (this.snapshots.length > 0) {
                        this.selectedSnapshot = this.snapshots[0];
                    }
                }
            } catch (e) {
                console.error('Failed to load snapshots:', e);
            }
        },
        
        // Select snapshot from slider
        selectSnapshotFromSlider() {
            if (this.snapshots.length > 0 && this.timeSliderValue >= 0) {
                this.selectedSnapshot = this.snapshots[this.timeSliderValue];
            }
        },
        
        // Select snapshot
        selectSnapshot(snapshot) {
            this.selectedSnapshot = snapshot;
            const idx = this.snapshots.findIndex(s => s.manifest_id === snapshot.manifest_id);
            if (idx >= 0) this.timeSliderValue = idx;
        },
        
        // Calculate available drive letters
        calculateAvailableDriveLetters() {
            const usedLetters = this.mounts.map(m => m.drive_letter.toUpperCase());
            const allLetters = 'ZYXWVUTSRQPONMLKJIHGFED'.split('');
            this.availableDriveLetters = allLetters.filter(l => !usedLetters.includes(l));
            if (this.availableDriveLetters.length > 0 && !this.newMount.drive_letter) {
                this.newMount.drive_letter = this.availableDriveLetters[0];
            }
        },
        
        // Open mount wizard
        openMountWizard() {
            this.wizardStep = 0;
            this.newMount = {
                bucket: '',
                prefix: '',
                drive_letter: this.availableDriveLetters[0] || 'Z',
                read_only: this.settings.default_read_only,
                persistent: this.settings.auto_mount,
                enable_cache: true,
                cache_mode: this.settings.cache_mode,
                agent_id: this.selectedAgentId
            };
            this.showMountWizard = true;
        },
        
        // Create mount
        async createMount() {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_create_mount.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.newMount)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showMountWizard = false;
                    await this.loadMounts();
                    this.calculateAvailableDriveLetters();
                    this.showToast('Mount configuration created', 'success');
                    // Auto-mount the new drive
                    if (data.mount_id) {
                        await this.mountDrive(data.mount_id);
                    }
                } else {
                    this.showToast(data.message || 'Failed to create mount', 'error');
                }
            } catch (e) {
                console.error('Failed to create mount:', e);
                this.showToast('Failed to create mount configuration', 'error');
            }
        },
        
        // Mount drive
        async mountDrive(mountId) {
            try {
                const mount = this.mounts.find(m => m.id === mountId);
                if (mount) mount.status = 'mounting';
                
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_mount.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mount_id: mountId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast(`Drive ${mount?.drive_letter}: mounting...`, 'success');
                    // Poll for status
                    setTimeout(() => this.loadMounts(), 2000);
                } else {
                    if (mount) mount.status = 'unmounted';
                    this.showToast(data.message || 'Failed to mount drive', 'error');
                }
            } catch (e) {
                console.error('Failed to mount drive:', e);
                this.showToast('Failed to mount drive', 'error');
                await this.loadMounts();
            }
        },
        
        // Unmount drive
        async unmountDrive(mountId) {
            try {
                const mount = this.mounts.find(m => m.id === mountId);
                if (mount) mount.status = 'unmounting';
                
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_unmount.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mount_id: mountId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast('Drive unmounted', 'success');
                    await this.loadMounts();
                } else {
                    this.showToast(data.message || 'Failed to unmount drive', 'error');
                    await this.loadMounts();
                }
            } catch (e) {
                console.error('Failed to unmount drive:', e);
                this.showToast('Failed to unmount drive', 'error');
                await this.loadMounts();
            }
        },
        
        // Delete mount configuration
        async deleteMount(mountId) {
            this.showConfirm(
                'Delete Mount Configuration',
                'Are you sure you want to delete this mount? The drive will be unmounted if currently active.',
                () => this.confirmDeleteMount(mountId),
                true
            );
        },
        
        // Actually delete the mount after confirmation
        async confirmDeleteMount(mountId) {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_delete_mount.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mount_id: mountId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast('Mount configuration deleted', 'success');
                    await this.loadMounts();
                    this.calculateAvailableDriveLetters();
                } else {
                    this.showToast(data.message || 'Failed to delete mount', 'error');
                }
            } catch (e) {
                console.error('Failed to delete mount:', e);
                this.showToast('Failed to delete mount configuration', 'error');
            }
        },
        
        // Edit mount (opens wizard in edit mode)
        editMount(mountId) {
            const mount = this.mounts.find(m => m.id === mountId);
            if (!mount) return;
            
            this.newMount = {
                id: mount.id,
                bucket: mount.bucket_name,
                prefix: mount.prefix || '',
                drive_letter: mount.drive_letter,
                read_only: mount.read_only,
                persistent: mount.persistent,
                enable_cache: mount.cache_mode !== 'off',
                cache_mode: mount.cache_mode,
                agent_id: mount.agent_id
            };
            this.wizardStep = 1; // Skip bucket selection for edits
            this.showMountWizard = true;
        },
        
        // Mount snapshot (Time Machine)
        async mountSnapshot() {
            if (!this.selectedSnapshot) return;
            
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_mount_snapshot.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        job_id: this.selectedJobId,
                        manifest_id: this.selectedSnapshot.manifest_id,
                        agent_id: this.selectedAgentId
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.mountedSnapshot = {
                        drive_letter: data.drive_letter,
                        snapshot_date: this.selectedSnapshot.created_at,
                        manifest_id: this.selectedSnapshot.manifest_id
                    };
                    this.showToast(`Snapshot mounted as ${data.drive_letter}:`, 'success');
                } else {
                    this.showToast(data.message || 'Failed to mount snapshot', 'error');
                }
            } catch (e) {
                console.error('Failed to mount snapshot:', e);
                this.showToast('Failed to mount snapshot', 'error');
            }
        },
        
        // Unmount snapshot
        async unmountSnapshot() {
            if (!this.mountedSnapshot) return;
            
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_unmount_snapshot.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        manifest_id: this.mountedSnapshot.manifest_id,
                        agent_id: this.selectedAgentId
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast('Snapshot unmounted', 'success');
                    this.mountedSnapshot = null;
                } else {
                    this.showToast(data.message || 'Failed to unmount snapshot', 'error');
                }
            } catch (e) {
                console.error('Failed to unmount snapshot:', e);
                this.showToast('Failed to unmount snapshot', 'error');
            }
        },
        
        // Format date helper
        formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        // Show confirmation modal
        showConfirm(title, message, action, danger = false) {
            this.confirmTitle = title;
            this.confirmMessage = message;
            this.confirmAction = action;
            this.confirmDanger = danger;
            this.showConfirmModal = true;
        },
        
        // Execute the confirmed action
        executeConfirm() {
            if (this.confirmAction && typeof this.confirmAction === 'function') {
                this.confirmAction();
            }
            this.closeConfirm();
        },
        
        // Close confirmation modal
        closeConfirm() {
            this.showConfirmModal = false;
            this.confirmAction = null;
        },
        
        // Toast notification
        showToast(message, type = 'info') {
            if (window.toast) {
                if (type === 'success') window.toast.success(message);
                else if (type === 'error') window.toast.error(message);
                else window.toast.info(message);
            } else {
                console.log(`[${type}] ${message}`);
            }
        }
    };
}
</script>
{/literal}

