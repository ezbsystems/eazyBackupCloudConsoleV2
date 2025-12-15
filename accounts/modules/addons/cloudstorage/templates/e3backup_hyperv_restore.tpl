<div class="min-h-screen bg-slate-950 text-gray-300" x-data="hypervRestoreApp()">
    <!-- Subtle top glow -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    
    <div class="container mx-auto px-4 py-8 relative">
        <!-- Header -->
        <div class="mb-8">
            <nav class="flex items-center gap-2 text-sm text-slate-400 mb-4">
                <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="hover:text-white transition">Jobs</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                {if $vm}
                    <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id={$vm.job_id}" class="hover:text-white transition">{$vm.job_name|escape}</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                {/if}
                <span class="text-slate-300">Restore</span>
            </nav>
            
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">
                        {if $vm}Restore: {$vm.vm_name|escape}{else}Hyper-V Restore{/if}
                    </h1>
                    <p class="text-slate-400 text-sm mt-1">Select a backup point and restore VM disks</p>
                </div>
                {if $vm}
                    <div class="flex items-center gap-3">
                        {if $vm.rct_enabled}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-500/10 text-emerald-300 border border-emerald-400/40">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                RCT Enabled
                            </span>
                        {/if}
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium bg-sky-500/15 text-sky-200 border border-sky-400/40">
                            Gen {$vm.generation}
                        </span>
                    </div>
                {/if}
            </div>
        </div>

        {if !empty($error)}
            <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 mb-6">
                <p class="text-rose-300">{$error|escape}</p>
            </div>
        {elseif !$vm}
            <div class="rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 mb-6">
                <p class="text-rose-300">VM not found or you do not have permission to access it.</p>
            </div>
        {else}
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 p-4">
                    <div class="text-xs uppercase text-slate-400 tracking-wide mb-1">Backup Points</div>
                    <div class="text-2xl font-semibold text-white">{$backupPointCount}</div>
                </div>
                <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 p-4">
                    <div class="text-xs uppercase text-slate-400 tracking-wide mb-1">Full Backups</div>
                    <div class="text-2xl font-semibold text-white">{$fullBackupCount}</div>
                </div>
                <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 p-4">
                    <div class="text-xs uppercase text-slate-400 tracking-wide mb-1">VM Disks</div>
                    <div class="text-2xl font-semibold text-white">{$disks|count}</div>
                </div>
                <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 p-4">
                    <div class="text-xs uppercase text-slate-400 tracking-wide mb-1">Latest Backup</div>
                    <div class="text-lg font-semibold text-white">
                        {if $latestBackup}
                            {$latestBackup.created_at|date_format:"%b %d, %Y"}
                        {else}
                            <span class="text-slate-400">None</span>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] overflow-hidden">
                <!-- Step Indicator -->
                <div class="border-b border-slate-800 px-6 py-4">
                    <div class="flex items-center gap-6">
                        <template x-for="(stepName, idx) in ['Select Backup Point', 'Configure Restore', 'Review & Start']" :key="idx">
                            <div class="flex items-center gap-2">
                                <div :class="step > idx ? 'bg-emerald-600 text-white' : step === idx ? 'bg-sky-600 text-white' : 'bg-slate-800 text-slate-400'"
                                     class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold transition">
                                    <span x-text="idx + 1"></span>
                                </div>
                                <span :class="step >= idx ? 'text-slate-200' : 'text-slate-500'" class="text-sm font-medium" x-text="stepName"></span>
                                <template x-if="idx < 2">
                                    <svg class="w-4 h-4 text-slate-600 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Step 0: Select Backup Point -->
                    <div x-show="step === 0" x-transition>
                        <h3 class="text-lg font-semibold text-white mb-4">Select Backup Point</h3>
                        
                        <!-- Filters -->
                        <div class="flex items-center gap-4 mb-4">
                            <select x-model="typeFilter" @change="loadBackupPoints()" 
                                    class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100">
                                <option value="">All Types</option>
                                <option value="Full">Full Only</option>
                                <option value="Incremental">Incremental Only</option>
                            </select>
                            <span class="text-sm text-slate-400" x-show="!loading">
                                <span x-text="backupPoints.length"></span> backup points
                            </span>
                        </div>

                        <!-- Loading State -->
                        <div x-show="loading" class="flex items-center justify-center py-12">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-sky-500"></div>
                        </div>

                        <!-- Backup Points Table -->
                        <div x-show="!loading && backupPoints.length > 0" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase tracking-wide">
                                        <th class="text-left py-3 px-2 w-8"></th>
                                        <th class="text-left py-3 px-2">Date</th>
                                        <th class="text-left py-3 px-2">Type</th>
                                        <th class="text-left py-3 px-2">Consistency</th>
                                        <th class="text-left py-3 px-2">Size</th>
                                        <th class="text-left py-3 px-2">Disks</th>
                                        <th class="text-left py-3 px-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="bp in backupPoints" :key="bp.id">
                                        <tr class="border-b border-slate-800/50 hover:bg-slate-800/30 transition cursor-pointer"
                                            :class="selectedPoint && selectedPoint.id === bp.id ? 'bg-sky-900/20' : ''"
                                            @click="selectBackupPoint(bp)">
                                            <td class="py-3 px-2">
                                                <input type="radio" :checked="selectedPoint && selectedPoint.id === bp.id"
                                                       class="rounded-full border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500">
                                            </td>
                                            <td class="py-3 px-2 text-slate-100" x-text="formatDate(bp.created_at)"></td>
                                            <td class="py-3 px-2">
                                                <span :class="bp.backup_type === 'Full' ? 'bg-sky-500/15 text-sky-200 border-sky-400/40' : 'bg-amber-500/15 text-amber-200 border-amber-400/40'"
                                                      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border"
                                                      x-text="bp.backup_type"></span>
                                            </td>
                                            <td class="py-3 px-2">
                                                <span :class="bp.consistency_level === 'Application' ? 'text-emerald-300' : 'text-amber-300'"
                                                      x-text="bp.consistency_level || 'Unknown'"></span>
                                            </td>
                                            <td class="py-3 px-2 text-slate-300" x-text="formatBytes(bp.total_size_bytes)"></td>
                                            <td class="py-3 px-2 text-slate-300" x-text="bp.disk_count"></td>
                                            <td class="py-3 px-2">
                                                <template x-if="bp.is_restorable">
                                                    <span class="inline-flex items-center gap-1 text-emerald-300 text-xs">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                                        Ready
                                                    </span>
                                                </template>
                                                <template x-if="!bp.is_restorable">
                                                    <span class="inline-flex items-center gap-1 text-rose-300 text-xs">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                                                        Incomplete
                                                    </span>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div x-show="!loading && backupPoints.length === 0" class="text-center py-12">
                            <svg class="w-12 h-12 mx-auto text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                            <p class="text-slate-400">No backup points available for restore</p>
                        </div>

                        <!-- Pagination -->
                        <div x-show="!loading && totalPages > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-slate-800">
                            <button @click="prevPage()" :disabled="currentPage === 0"
                                    :class="currentPage === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-700'"
                                    class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 text-sm">
                                Previous
                            </button>
                            <span class="text-sm text-slate-400">
                                Page <span x-text="currentPage + 1"></span> of <span x-text="totalPages"></span>
                            </span>
                            <button @click="nextPage()" :disabled="currentPage >= totalPages - 1"
                                    :class="currentPage >= totalPages - 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-700'"
                                    class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 text-sm">
                                Next
                            </button>
                        </div>
                    </div>

                    <!-- Step 1: Configure Restore -->
                    <div x-show="step === 1" x-transition>
                        <h3 class="text-lg font-semibold text-white mb-4">Configure Restore</h3>
                        
                        <!-- Selected Backup Info -->
                        <div class="rounded-xl border border-slate-700 bg-slate-900/50 p-4 mb-6" x-show="selectedPoint">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm text-slate-400">Selected Backup Point</span>
                                <button @click="step = 0" class="text-sky-400 hover:text-sky-300 text-sm">Change</button>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <div class="text-slate-400 text-xs">Date</div>
                                    <div class="text-white" x-text="selectedPoint ? formatDate(selectedPoint.created_at) : ''"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 text-xs">Type</div>
                                    <div class="text-white" x-text="selectedPoint ? selectedPoint.backup_type : ''"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 text-xs">Size</div>
                                    <div class="text-white" x-text="selectedPoint ? formatBytes(selectedPoint.total_size_bytes) : ''"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 text-xs">Disks</div>
                                    <div class="text-white" x-text="selectedPoint ? selectedPoint.disk_count + ' disk(s)' : ''"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Path -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-200 mb-2">Target Path</label>
                            <input type="text" x-model="targetPath" 
                                   placeholder="C:\Restored\{$vm.vm_name|escape}"
                                   class="w-full rounded-lg border border-slate-700 bg-slate-900/60 px-4 py-2.5 text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
                            <p class="text-xs text-slate-400 mt-1">VHDX files will be restored to this directory on the agent machine</p>
                        </div>

                        <!-- Disks to Restore -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-200 mb-2">Disks to Restore</label>
                            <div class="rounded-xl border border-slate-700 bg-slate-900/50 divide-y divide-slate-800">
                                <template x-for="disk in selectedPoint ? Object.keys(selectedPoint.disk_manifests) : []" :key="disk">
                                    <label class="flex items-center gap-3 p-3 hover:bg-slate-800/30 cursor-pointer">
                                        <input type="checkbox" :value="disk" x-model="selectedDisks"
                                               class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500">
                                        <div class="flex-1">
                                            <div class="text-slate-100" x-text="disk.split('\\').pop()"></div>
                                            <div class="text-xs text-slate-400" x-text="disk"></div>
                                        </div>
                                    </label>
                                </template>
                            </div>
                            <div class="flex items-center gap-4 mt-2">
                                <button @click="selectAllDisks()" class="text-sm text-sky-400 hover:text-sky-300">Select All</button>
                                <button @click="selectedDisks = []" class="text-sm text-sky-400 hover:text-sky-300">Select None</button>
                            </div>
                        </div>

                        <!-- Warning for Incremental -->
                        <div x-show="selectedPoint && selectedPoint.backup_type === 'Incremental'" 
                             class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 mb-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div>
                                    <p class="text-amber-200 font-medium">Incremental Backup Selected</p>
                                    <p class="text-amber-300/80 text-sm mt-1">
                                        This is an incremental backup with <span x-text="selectedPoint ? selectedPoint.restore_chain_length : 0"></span> backup(s) in the chain.
                                        For simpler recovery, consider selecting a Full backup point.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Review & Start -->
                    <div x-show="step === 2" x-transition>
                        <h3 class="text-lg font-semibold text-white mb-4">Review & Start Restore</h3>
                        
                        <div class="rounded-xl border border-slate-700 bg-slate-900/50 p-6 mb-6">
                            <dl class="space-y-4">
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">VM Name</dt>
                                    <dd class="text-white">{$vm.vm_name|escape}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">Backup Date</dt>
                                    <dd class="text-white" x-text="selectedPoint ? formatDate(selectedPoint.created_at) : ''"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">Backup Type</dt>
                                    <dd class="text-white" x-text="selectedPoint ? selectedPoint.backup_type : ''"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">Consistency</dt>
                                    <dd class="text-white" x-text="selectedPoint ? selectedPoint.consistency_level : ''"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">Target Path</dt>
                                    <dd class="text-white font-mono text-sm" x-text="targetPath"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">Disks to Restore</dt>
                                    <dd class="text-white" x-text="selectedDisks.length + ' of ' + (selectedPoint ? Object.keys(selectedPoint.disk_manifests).length : 0)"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">Estimated Size</dt>
                                    <dd class="text-white" x-text="selectedPoint ? formatBytes(selectedPoint.total_size_bytes) : ''"></dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-sky-500/40 bg-sky-500/10 p-4 mb-4">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-sky-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <p class="text-sky-200 font-medium">Ready to Restore</p>
                                    <p class="text-sky-300/80 text-sm mt-1">
                                        VHDX files will be restored to the target path. After restore completes, you can manually attach 
                                        the disks to a new or existing VM in Hyper-V Manager.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="border-t border-slate-800 px-6 py-4 flex items-center justify-between">
                    <button x-show="step > 0" @click="step--"
                            class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700 transition">
                        Back
                    </button>
                    <div x-show="step === 0"></div>
                    
                    <div class="flex items-center gap-3">
                        <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id={$vm.job_id}"
                           class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700 transition">
                            Cancel
                        </a>
                        
                        <button x-show="step < 2" @click="nextStep()" :disabled="!canProceed()"
                                :class="canProceed() ? 'bg-sky-600 hover:bg-sky-700' : 'bg-slate-700 cursor-not-allowed'"
                                class="px-4 py-2 rounded-lg text-white transition">
                            Next
                        </button>
                        
                        <button x-show="step === 2" @click="startRestore()" :disabled="restoring"
                                :class="restoring ? 'bg-slate-700 cursor-wait' : 'bg-gradient-to-r from-[#FE5000] via-[#FF7A33] to-[#FF924D] hover:-translate-y-px hover:shadow-lg'"
                                class="px-6 py-2 rounded-lg text-slate-950 font-semibold transition transform ring-1 ring-[#FE5000]/40">
                            <span x-show="!restoring">Start Restore</span>
                            <span x-show="restoring" class="flex items-center gap-2">
                                <span class="animate-spin rounded-full h-4 w-4 border-b-2 border-slate-950"></span>
                                Starting...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

<script>
function hypervRestoreApp() {
    return {
        step: 0,
        loading: true,
        backupPoints: [],
        selectedPoint: null,
        vmId: {$vmId|default:0},
        vmData: {$vm|json_encode nofilter},
        targetPath: 'C:\\Restored\\{$vm.vm_name|escape:'javascript'|default:'VM'}',
        selectedDisks: [],
        typeFilter: '',
        currentPage: 0,
        pageSize: 20,
        total: 0,
        restoring: false,

        get totalPages() {
            return Math.ceil(this.total / this.pageSize);
        },

        init() {
            console.log('hypervRestoreApp init', { vmId: this.vmId, vmData: this.vmData });
            if (this.vmId > 0) {
                this.loadBackupPoints();
            }
        },

        async loadBackupPoints() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    vm_id: this.vmId,
                    limit: this.pageSize,
                    offset: this.currentPage * this.pageSize
                });
                if (this.typeFilter) {
                    params.set('type', this.typeFilter);
                }
                
                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_hyperv_backup_points.php?' + params);
                const data = await resp.json();
                
                if (data.status === 'success') {
                    this.backupPoints = data.backup_points || [];
                    this.total = data.total || 0;
                } else {
                    console.error('Failed to load backup points:', data.message);
                    this.backupPoints = [];
                }
            } catch (err) {
                console.error('Error loading backup points:', err);
                this.backupPoints = [];
            } finally {
                this.loading = false;
            }
        },

        selectBackupPoint(bp) {
            if (!bp.is_restorable) {
                return;
            }
            this.selectedPoint = bp;
            this.selectedDisks = Object.keys(bp.disk_manifests || {});
        },

        selectAllDisks() {
            if (this.selectedPoint) {
                this.selectedDisks = Object.keys(this.selectedPoint.disk_manifests || {});
            }
        },

        canProceed() {
            if (this.step === 0) {
                return this.selectedPoint !== null && this.selectedPoint.is_restorable;
            }
            if (this.step === 1) {
                return this.targetPath.trim() !== '' && this.selectedDisks.length > 0;
            }
            return true;
        },

        nextStep() {
            if (this.canProceed() && this.step < 2) {
                this.step++;
            }
        },

        prevPage() {
            if (this.currentPage > 0) {
                this.currentPage--;
                this.loadBackupPoints();
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages - 1) {
                this.currentPage++;
                this.loadBackupPoints();
            }
        },

        async startRestore() {
            if (this.restoring || !this.selectedPoint) return;
            
            this.restoring = true;
            try {
                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_hyperv_start_restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        backup_point_id: this.selectedPoint.id,
                        target_path: this.targetPath,
                        disk_filter: JSON.stringify(this.selectedDisks)
                    })
                });
                const data = await resp.json();
                
                if (data.status === 'success') {
                    // Redirect to live progress page
                    const runParam = data.restore_run_uuid || data.restore_run_id;
                    window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=live&job_id=' + 
                        encodeURIComponent(data.job_id) + '&run_id=' + encodeURIComponent(runParam);
                } else {
                    alert('Failed to start restore: ' + (data.message || 'Unknown error'));
                    this.restoring = false;
                }
            } catch (err) {
                console.error('Error starting restore:', err);
                alert('Error starting restore: ' + err.message);
                this.restoring = false;
            }
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        formatBytes(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    };
}
</script>

