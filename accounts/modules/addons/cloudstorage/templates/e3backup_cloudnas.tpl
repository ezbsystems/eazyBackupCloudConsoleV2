{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3CloudNasBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Cloud NAS</span>
    </div>
{/capture}

{capture assign=ebE3Content}
<div x-data="cloudNAS()" x-init="init()" class="space-y-6">
    <style>
        /* Temporary helper for the legacy Cloud NAS settings partial until it is migrated. */
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
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }
        .toggle-switch.active .toggle-knob {
            transform: translateX(1.25rem);
        }
    </style>

    <div class="eb-page-header">
        <div>
            {$ebE3CloudNasBreadcrumb nofilter}
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="eb-page-title !mb-0">Cloud NAS</h2>
                <span class="eb-badge eb-badge--info">Beta</span>
            </div>
            <p class="eb-page-description">Mount your cloud storage as a local Windows drive, browse point-in-time snapshots, and manage Cloud NAS defaults.</p>
        </div>
        <div class="shrink-0">
            <button
                type="button"
                @click="openMountWizard()"
                :disabled="!hasAgent"
                class="eb-btn eb-btn-primary eb-btn-sm"
                :class="!hasAgent ? 'pointer-events-none opacity-60' : ''"
            >
                Mount Drive
            </button>
        </div>
    </div>

    <div class="eb-alert eb-alert--info">
        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <div class="flex-1">
            <strong class="font-semibold">Cloud NAS Beta.</strong>
            Mount S3 buckets as local Windows drives using the backup agent. No additional software is required.
        </div>
    </div>

    <div class="eb-panel-nav">
        <nav class="flex flex-wrap gap-2" aria-label="Cloud NAS sections">
            <button
                type="button"
                @click="activeTab = 'drives'"
                class="eb-tab"
                :class="{ 'is-active': activeTab === 'drives' }"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44z" />
                </svg>
                <span>My Drives</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'timemachine'"
                class="eb-tab"
                :class="{ 'is-active': activeTab === 'timemachine' }"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>
                <span>Time Machine</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'settings'"
                class="eb-tab"
                :class="{ 'is-active': activeTab === 'settings' }"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                </svg>
                <span>Settings</span>
            </button>
        </nav>
    </div>

    <div x-show="activeTab === 'drives'" x-cloak class="eb-card-raised">
        {include file="modules/addons/cloudstorage/templates/partials/cloudnas_drives.tpl"}
    </div>

    <div x-show="activeTab === 'timemachine'" x-cloak class="eb-card-raised">
        {include file="modules/addons/cloudstorage/templates/partials/cloudnas_timemachine.tpl"}
    </div>

    <div x-show="activeTab === 'settings'" x-cloak class="eb-card-raised">
        {include file="modules/addons/cloudstorage/templates/partials/cloudnas_settings.tpl"}
    </div>

    {include file="modules/addons/cloudstorage/templates/partials/cloudnas_mount_wizard.tpl"}

    <div
        x-show="showConfirmModal"
        x-cloak
        class="fixed inset-0 z-40"
        style="display: none;"
        @keydown.escape.window="closeConfirm()"
    >
        <div class="absolute inset-0 eb-drawer-backdrop" @click="closeConfirm()"></div>

        <div class="relative flex min-h-full items-center justify-center p-4 sm:p-6">
            <div
                class="eb-panel w-full max-w-md !overflow-hidden !p-0"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <div class="flex items-start gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-5">
                    <span
                        class="eb-icon-box shrink-0"
                        :class="confirmDanger ? 'eb-icon-box--danger' : 'eb-icon-box--info'"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="eb-card-title" x-text="confirmTitle"></h3>
                        <p class="eb-card-subtitle">Confirm this Cloud NAS action before proceeding.</p>
                    </div>
                </div>

                <div class="px-6 py-4">
                    <p class="eb-type-body leading-relaxed" x-text="confirmMessage"></p>
                </div>

                <div class="flex justify-end gap-3 border-t border-[var(--eb-border-subtle)] bg-[var(--eb-bg-surface)] px-6 py-4">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeConfirm()">Cancel</button>
                    <button
                        type="button"
                        class="eb-btn eb-btn-sm"
                        :class="confirmDanger ? 'eb-btn-danger-solid' : 'eb-btn-primary'"
                        @click="executeConfirm()"
                    >
                        <span x-text="confirmDanger ? 'Delete' : 'Confirm'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='cloudnas'
    ebE3Title='e3 Cloud Backup'
    ebE3Description='Mount buckets as local Windows drives, browse snapshot history, and manage Cloud NAS behavior.'
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
}

{literal}
<script>
function cloudNAS() {
    return {
        // Tab state
        activeTab: 'drives',
        
        // Agent state
        hasAgent: false,
        agents: [],
        selectedAgentUuid: '',
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
            agent_uuid: ''
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
                return this.newMount.bucket !== '' && this.newMount.agent_uuid !== '';
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
        
        // Start polling for status updates while mount actions are in progress
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
        
        // Check if there are active mount state transitions and refresh if needed
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
                        this.selectedAgentUuid = activeAgent.agent_uuid || '';
                        this.newMount.agent_uuid = this.selectedAgentUuid;
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
        
        // Fetch available drive letters from the server.
        // The server combines the host's in-use drives (reported by the agent)
        // with existing Cloud NAS mounts to produce usable letters only.
        async calculateAvailableDriveLetters() {
            const uuid = this.selectedAgentUuid || '';
            if (!uuid) {
                this.availableDriveLetters = 'ZYXWVUTSRQPONMLKJIHGFED'.split('');
                return;
            }
            try {
                const res = await fetch(`modules/addons/cloudstorage/api/cloudnas_available_drives.php?agent_uuid=${encodeURIComponent(uuid)}`);
                const data = await res.json();
                if (data.status === 'success' && Array.isArray(data.available)) {
                    this.availableDriveLetters = data.available;
                } else {
                    const usedLetters = this.mounts.map(m => m.drive_letter.toUpperCase());
                    this.availableDriveLetters = 'ZYXWVUTSRQPONMLKJIHGFED'.split('').filter(l => !usedLetters.includes(l));
                }
            } catch (e) {
                console.error('Failed to fetch available drives:', e);
                const usedLetters = this.mounts.map(m => m.drive_letter.toUpperCase());
                this.availableDriveLetters = 'ZYXWVUTSRQPONMLKJIHGFED'.split('').filter(l => !usedLetters.includes(l));
            }
            if (this.availableDriveLetters.length > 0 && !this.newMount.drive_letter) {
                this.newMount.drive_letter = this.availableDriveLetters[0];
            }
        },
        
        // Open mount wizard
        async openMountWizard() {
            this.wizardStep = 0;
            this.newMount = {
                bucket: '',
                prefix: '',
                drive_letter: this.availableDriveLetters[0] || 'Z',
                read_only: this.settings.default_read_only,
                persistent: this.settings.auto_mount,
                enable_cache: true,
                cache_mode: this.settings.cache_mode,
                agent_uuid: this.selectedAgentUuid || ''
            };
            this.showMountWizard = true;
            await this.calculateAvailableDriveLetters();
            if (this.availableDriveLetters.length > 0) {
                this.newMount.drive_letter = this.availableDriveLetters[0];
            }
        },
        
        // Create mount
        async createMount() {
            try {
                const payload = {
                    ...this.newMount,
                    agent_uuid: this.selectedAgentUuid || '',
                };
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_create_mount.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showMountWizard = false;
                    await this.loadMounts();
                    await this.mountDrive(data.mount_id);
                    this.calculateAvailableDriveLetters();
                } else {
                    this.showToast(data.message || 'Failed to create mount', 'error');
                }
            } catch (e) {
                console.error('Failed to create mount:', e);
                this.showToast('Failed to create mount configuration', 'error');
            }
        },
        
        // Start the mount immediately from the client area.
        async mountDrive(mountId) {
            try {
                const mount = this.mounts.find(m => m.id === mountId);
                const driveLabel = mount?.drive_letter || this.newMount.drive_letter || '';
                if (mount) mount.status = 'mounting';
                
                const res = await fetch('modules/addons/cloudstorage/api/cloudnas_mount.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mount_id: mountId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast(`Drive ${driveLabel}: mounting in progress.`, 'success');
                    await this.loadMounts();
                } else {
                    if (mount) {
                        mount.status = 'error';
                        mount.error = data.message || 'Mount failed';
                    }
                    this.showToast(data.message || 'Failed to mount drive', 'error');
                    await this.loadMounts();
                    this.calculateAvailableDriveLetters();
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
                agent_uuid: mount.agent_uuid || ''
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
                        agent_uuid: this.selectedAgentUuid || ''
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
                        agent_uuid: this.selectedAgentUuid || ''
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
        
        // Toast notification with DOM fallback when window.toast is unavailable
        showToast(message, type = 'info') {
            if (window.toast) {
                if (type === 'success') window.toast.success(message);
                else if (type === 'error') window.toast.error(message);
                else window.toast.info(message);
                return;
            }
            const colors = {
                success: { bg: 'var(--eb-success-bg, #ecfdf5)', border: 'var(--eb-success-border, #10b981)', text: 'var(--eb-success-text, #065f46)' },
                error:   { bg: 'var(--eb-danger-bg, #fef2f2)',  border: 'var(--eb-danger-border, #ef4444)',  text: 'var(--eb-danger-text, #991b1b)' },
                info:    { bg: 'var(--eb-info-bg, #eff6ff)',     border: 'var(--eb-info-border, #3b82f6)',    text: 'var(--eb-info-text, #1e40af)' }
            };
            const c = colors[type] || colors.info;
            const el = document.createElement('div');
            el.setAttribute('role', 'alert');
            el.style.cssText = `position:fixed;top:1rem;right:1rem;z-index:9999;max-width:24rem;padding:0.75rem 1rem;border-radius:0.5rem;border:1px solid ${c.border};background:${c.bg};color:${c.text};font-size:0.875rem;box-shadow:0 4px 12px rgba(0,0,0,.12);opacity:0;transition:opacity .2s;`;
            el.textContent = message;
            document.body.appendChild(el);
            requestAnimationFrame(() => { el.style.opacity = '1'; });
            setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 250); }, 5000);
        }
    };
}
</script>
{/literal}
