{capture assign=ebE3Actions}
    {if $entryVm}
        <div class="flex flex-wrap items-center justify-end gap-2">
            {if $entryVm.rct_enabled}
                <span class="eb-badge eb-badge--success eb-badge--dot">RCT Enabled</span>
            {/if}
            <span class="eb-badge eb-badge--info">Gen {$entryVm.generation}</span>
        </div>
    {/if}
{/capture}

{capture assign=ebE3Content}
<div class="space-y-6" x-data="hypervRestoreApp()" x-init="init()">
    {if !empty($error)}
        <div class="eb-alert eb-alert--danger">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                <div class="eb-alert-title">Error</div>
                <p>{$error|escape}</p>
            </div>
        </div>
    {elseif !$job}
        <div class="eb-alert eb-alert--danger">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                <div class="eb-alert-title">Not available</div>
                <p>Job not found or you do not have permission to access it.</p>
            </div>
        </div>
    {else}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="eb-stat-card">
                <div class="eb-stat-label">Snapshots</div>
                <div class="eb-stat-value">{$snapshotCount}</div>
            </div>
            <div class="eb-stat-card">
                <div class="eb-stat-label">Backup Points</div>
                <div class="eb-stat-value">{$backupPointCount}</div>
            </div>
            <div class="eb-stat-card">
                <div class="eb-stat-label">VMs in Job</div>
                <div class="eb-stat-value">{$vmCount}</div>
            </div>
            <div class="eb-stat-card">
                <div class="eb-stat-label">Latest Backup</div>
                <div class="eb-stat-value">
                    {if $latestBackup}
                        {$latestBackup.created_at|date_format:"%b %d, %Y"}
                    {else}
                        <span class="eb-type-caption">None</span>
                    {/if}
                </div>
            </div>
        </div>

        <section class="eb-card-raised !p-0 overflow-hidden">
            <div class="eb-wizard-steps-bar" role="navigation" aria-label="Restore steps">
                <div class="eb-wizard-steps-track">
                    <template x-for="(stepName, idx) in ['Select Snapshot', 'Select VMs &amp; Disks', 'Review &amp; Start']" :key="idx">
                        <div class="eb-wizard-step">
                            <div
                                class="eb-wizard-step-dot"
                                :class="step > idx ? 'is-complete' : (step === idx ? 'is-current' : 'is-upcoming')"
                                :aria-current="step === idx ? 'step' : false"
                            >
                                <span x-text="idx + 1"></span>
                            </div>
                            <span class="eb-wizard-step-label" :class="step >= idx ? 'is-strong' : 'is-muted'" x-html="stepName"></span>
                            <template x-if="idx < 2">
                                <svg class="eb-wizard-steps-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div class="p-6">
                {* ---------- Step 0: Select Snapshot ---------- *}
                <div x-show="step === 0" x-transition>
                    <h3 class="eb-type-h3 mb-4">Select Snapshot</h3>

                    <div class="mb-4 flex flex-wrap items-center gap-4">
                        <span class="eb-type-caption" x-show="!loading">
                            <span x-text="snapshots.length"></span> snapshot(s) available
                        </span>
                    </div>

                    <div x-show="loading" class="flex items-center justify-center py-12">
                        <div class="eb-loading-spinner--compact" role="status" aria-label="Loading"></div>
                    </div>

                    <div x-show="!loading && snapshots.length > 0" class="eb-table-shell">
                        <table class="eb-table">
                            <thead>
                                <tr>
                                    <th class="w-8"></th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Consistency</th>
                                    <th>VMs</th>
                                    <th>Disks</th>
                                    <th>Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="snap in snapshots" :key="snap.run_id">
                                    <tr
                                        :class="selectedSnapshot && selectedSnapshot.run_id === snap.run_id ? 'is-selected' : 'cursor-pointer'"
                                        @click="selectSnapshot(snap)"
                                    >
                                        <td>
                                            <input
                                                type="radio"
                                                class="eb-radio-input"
                                                :checked="selectedSnapshot && selectedSnapshot.run_id === snap.run_id"
                                                @click.stop
                                                @change="selectSnapshot(snap)"
                                            >
                                        </td>
                                        <td class="eb-table-primary" x-text="formatDate(snap.created_at)"></td>
                                        <td>
                                            <span class="eb-badge" :class="snap.backup_type === 'Full' ? 'eb-badge--info' : 'eb-badge--warning'" x-text="snap.backup_type"></span>
                                        </td>
                                        <td>
                                            <span class="text-sm font-medium"
                                                  :class="snap.consistency_level === 'Application' ? 'text-[var(--eb-success-text)]' : 'text-[var(--eb-warning-text)]'"
                                                  x-text="snap.consistency_level || 'Unknown'"></span>
                                        </td>
                                        <td class="text-[var(--eb-text-secondary)]" x-text="snap.vm_count"></td>
                                        <td class="text-[var(--eb-text-secondary)]" x-text="snap.disk_count"></td>
                                        <td class="text-[var(--eb-text-secondary)]" x-text="formatBytes(snap.total_size_bytes)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="!loading && snapshots.length === 0" class="eb-app-empty !py-12">
                        <span class="eb-icon-box eb-icon-box--lg eb-icon-box--default mx-auto">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </span>
                        <div class="eb-app-empty-title mt-4">No snapshots available</div>
                        <p class="eb-app-empty-copy">There are no restore points for this job yet.</p>
                    </div>
                </div>

                {* ---------- Step 1: Select VMs & Disks ---------- *}
                <div x-show="step === 1" x-transition>
                    <h3 class="eb-type-h3 mb-4">Select VMs &amp; Disks</h3>

                    <div class="eb-subpanel mb-6" x-show="selectedSnapshot">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <span class="eb-type-caption">Selected snapshot</span>
                            <button type="button" @click="step = 0" class="eb-link text-sm">Change</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                            <div>
                                <div class="eb-type-caption">Date</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedSnapshot ? formatDate(selectedSnapshot.created_at) : ''"></div>
                            </div>
                            <div>
                                <div class="eb-type-caption">Type</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedSnapshot ? selectedSnapshot.backup_type : ''"></div>
                            </div>
                            <div>
                                <div class="eb-type-caption">VMs in snapshot</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedSnapshot ? selectedSnapshot.vm_count : ''"></div>
                            </div>
                            <div>
                                <div class="eb-type-caption">Total size</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedSnapshot ? formatBytes(selectedSnapshot.total_size_bytes) : ''"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <label class="eb-field-label !mb-0">Guest VMs to restore</label>
                        <button type="button" @click="selectAllVms()" class="eb-link text-sm">Select all</button>
                        <button type="button" @click="selectNoVms()" class="eb-link text-sm">Select none</button>
                        <span class="eb-type-caption ml-auto">
                            <span x-text="selectedVmCount()"></span> of <span x-text="selectedSnapshot ? selectedSnapshot.vms.length : 0"></span> VM(s),
                            <span x-text="selectedDiskCount()"></span> disk(s)
                        </span>
                    </div>

                    <div class="space-y-3">
                        <template x-for="vm in (selectedSnapshot ? selectedSnapshot.vms : [])" :key="vm.backup_point_id">
                            <div class="eb-subpanel !p-0 overflow-hidden"
                                 :class="isVmSelected(vm.backup_point_id) ? 'ring-1 ring-[var(--eb-info-border)]' : ''">
                                <label class="flex cursor-pointer items-center gap-3 border-b border-[var(--eb-border-faint)] p-3 transition-colors hover:bg-[var(--eb-bg-hover)]"
                                       :class="!vm.is_restorable ? 'opacity-60 cursor-not-allowed' : ''">
                                    <input type="checkbox" class="eb-check-input"
                                           :checked="isVmSelected(vm.backup_point_id)"
                                           :disabled="!vm.is_restorable"
                                           @change="toggleVm(vm)">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-[var(--eb-text-primary)]" x-text="vm.vm_name"></span>
                                            <span class="eb-badge eb-badge--neutral" x-text="vm.disk_count + ' disk(s)'"></span>
                                            <template x-if="!vm.is_restorable">
                                                <span class="eb-badge eb-badge--danger eb-badge--dot">Incomplete</span>
                                            </template>
                                        </div>
                                        <div class="eb-type-mono mt-0.5 truncate text-xs text-[var(--eb-text-muted)]">
                                            Restores to: <span x-text="targetPath.replace(/[\\/]+$/, '') + '\\' + subfolderFor(vm.vm_name)"></span>
                                        </div>
                                    </div>
                                </label>

                                <div x-show="isVmSelected(vm.backup_point_id)" class="divide-y divide-[var(--eb-border-faint)]">
                                    <template x-for="disk in diskKeys(vm)" :key="vm.backup_point_id + ':' + disk">
                                        <label class="flex cursor-pointer items-center gap-3 px-3 py-2 pl-10 transition-colors hover:bg-[var(--eb-bg-hover)]">
                                            <input type="checkbox" class="eb-check-input"
                                                   :checked="isDiskSelected(vm.backup_point_id, disk)"
                                                   @change="toggleDisk(vm.backup_point_id, disk)">
                                            <div class="min-w-0 flex-1">
                                                <div class="font-medium text-[var(--eb-text-primary)]" x-text="disk.split('\\').pop()"></div>
                                                <div class="eb-type-mono mt-0.5 truncate text-[var(--eb-text-muted)]" x-text="disk"></div>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-6">
                        <label class="eb-field-label" for="hyperv-restore-target-path">Base target path</label>
                        <input
                            id="hyperv-restore-target-path"
                            type="text"
                            class="eb-input"
                            x-model="targetPath"
                            placeholder="C:\Restored"
                        >
                        <p class="eb-field-help">Each VM is restored into its own subfolder beneath this directory on the agent machine (e.g. <span class="eb-type-mono" x-text="targetPath.replace(/[\\/]+$/, '') + '\\&lt;vm-name&gt;'"></span>).</p>
                    </div>

                    <div x-show="selectedSnapshot && selectedSnapshot.backup_type === 'Incremental'" class="eb-alert eb-alert--warning mt-4">
                        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <div class="eb-alert-title">Incremental snapshot selected</div>
                            <p class="mt-1">This snapshot contains incremental backups. Recovery will replay the full backup chain. For simpler recovery, consider selecting a Full snapshot.</p>
                        </div>
                    </div>
                </div>

                {* ---------- Step 2: Review & Start ---------- *}
                <div x-show="step === 2" x-transition>
                    <h3 class="eb-type-h3 mb-4">Review &amp; start restore</h3>

                    <div class="eb-subpanel mb-6">
                        <dl class="eb-kv-list">
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Job</span>
                                <span class="eb-kv-value">{$job.name|escape}</span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Snapshot date</span>
                                <span class="eb-kv-value" x-text="selectedSnapshot ? formatDate(selectedSnapshot.created_at) : ''"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Backup type</span>
                                <span class="eb-kv-value" x-text="selectedSnapshot ? selectedSnapshot.backup_type : ''"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Base target path</span>
                                <span class="eb-kv-value eb-type-mono max-w-[min(100%,20rem)] break-all" x-text="targetPath"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">VMs to restore</span>
                                <span class="eb-kv-value" x-text="selectedVmCount() + ' VM(s), ' + selectedDiskCount() + ' disk(s)'"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Estimated size</span>
                                <span class="eb-kv-value" x-text="formatBytes(selectedSizeBytes())"></span>
                            </div>
                        </dl>
                    </div>

                    <div class="eb-subpanel mb-6">
                        <div class="eb-type-caption mb-2">Restore plan</div>
                        <div class="space-y-2">
                            <template x-for="vm in selectedVmsList()" :key="'review-' + vm.backup_point_id">
                                <div class="flex items-start justify-between gap-3 border-b border-[var(--eb-border-faint)] pb-2 last:border-b-0">
                                    <div class="min-w-0">
                                        <div class="font-medium text-[var(--eb-text-primary)]" x-text="vm.vm_name"></div>
                                        <div class="eb-type-mono truncate text-xs text-[var(--eb-text-muted)]" x-text="targetPath.replace(/[\\/]+$/, '') + '\\' + subfolderFor(vm.vm_name)"></div>
                                    </div>
                                    <span class="eb-badge eb-badge--neutral shrink-0" x-text="selectedDisksForVm(vm.backup_point_id).length + ' disk(s)'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="eb-alert eb-alert--info">
                        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <div class="eb-alert-title">Ready to restore</div>
                            <p class="mt-1">VHDX files for each selected VM will be restored into its own subfolder under the base target path. After the restore completes, you can attach the disks to a new or existing VM in Hyper-V Manager.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--eb-border-subtle)] px-6 py-4">
                <div>
                    <button x-show="step > 0" type="button" @click="step--" class="eb-btn eb-btn-secondary eb-btn-sm">Back</button>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                    <a href="{if $job.backup_user_route_id}index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id={$job.backup_user_route_id|escape:'url'}#hyperv{else}index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id={$job.id}{/if}"
                       class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</a>

                    <button x-show="step < 2" type="button" @click="nextStep()" :disabled="!canProceed()"
                            class="eb-btn eb-btn-primary eb-btn-sm" :class="!canProceed() && 'disabled'">Next</button>

                    <button x-show="step === 2" type="button" @click="startRestore()" :disabled="restoring"
                            class="eb-btn eb-btn-upgrade eb-btn-sm" :class="restoring && 'cursor-wait'">
                        <span x-show="!restoring">Start Restore</span>
                        <span x-show="restoring" class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-[var(--eb-text-inverse)] border-t-transparent" aria-hidden="true"></span>
                            Starting...
                        </span>
                    </button>
                </div>
            </div>
        </section>
    {/if}
</div>
{/capture}

{capture assign=ebE3HypervRestoreTitle}{if $job}Restore: {$job.name|escape}{else}Hyper-V Restore{/if}{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='hyperv'
    ebE3Title=$ebE3HypervRestoreTitle
    ebE3Description='Select a snapshot, choose one or more VMs, and restore their disks.'
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

{literal}
<script>
function hypervRestoreApp() {
    return {
        step: 0,
        loading: true,
        snapshots: [],
        selectedSnapshot: null,
        // vmSelections keyed by backup_point_id -> selected disk paths + vm metadata
        vmSelections: {},
        entryVmId: {/literal}{$vmId|default:0}{literal},
        jobId: {/literal}{$job.id|default:''|@json_encode nofilter}{literal},
        targetPath: 'C:\\Restored',
        restoring: false,

        init() {
            this.loadSnapshots();
        },

        async loadSnapshots() {
            this.loading = true;
            try {
                const params = new URLSearchParams({ vm_id: this.entryVmId });
                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_hyperv_snapshots.php?' + params);
                const data = await resp.json();
                if (data.status === 'success') {
                    this.snapshots = data.snapshots || [];
                } else {
                    console.error('Failed to load snapshots:', data.message);
                    this.snapshots = [];
                }
            } catch (err) {
                console.error('Error loading snapshots:', err);
                this.snapshots = [];
            } finally {
                this.loading = false;
            }
        },

        selectSnapshot(snap) {
            this.selectedSnapshot = snap;
            // Default: select every restorable VM and all of its disks.
            this.vmSelections = {};
            (snap.vms || []).forEach((vm) => {
                if (vm.is_restorable) {
                    this.vmSelections[vm.backup_point_id] = {
                        disks: Object.keys(vm.disk_manifests || {}),
                        vm: vm,
                    };
                }
            });
        },

        isVmSelected(bpId) {
            return !!this.vmSelections[bpId];
        },

        toggleVm(vm) {
            if (!vm.is_restorable) return;
            if (this.isVmSelected(vm.backup_point_id)) {
                delete this.vmSelections[vm.backup_point_id];
            } else {
                this.vmSelections[vm.backup_point_id] = {
                    disks: Object.keys(vm.disk_manifests || {}),
                    vm: vm,
                };
            }
        },

        isDiskSelected(bpId, disk) {
            const sel = this.vmSelections[bpId];
            return !!sel && sel.disks.includes(disk);
        },

        toggleDisk(bpId, disk) {
            const sel = this.vmSelections[bpId];
            if (!sel) return;
            if (sel.disks.includes(disk)) {
                sel.disks = sel.disks.filter((d) => d !== disk);
                // Deselecting the last disk deselects the VM entirely.
                if (sel.disks.length === 0) {
                    delete this.vmSelections[bpId];
                }
            } else {
                sel.disks = [...sel.disks, disk];
            }
        },

        selectAllVms() {
            if (!this.selectedSnapshot) return;
            this.selectedSnapshot.vms.forEach((vm) => {
                if (vm.is_restorable) {
                    this.vmSelections[vm.backup_point_id] = {
                        disks: Object.keys(vm.disk_manifests || {}),
                        vm: vm,
                    };
                }
            });
        },

        selectNoVms() {
            this.vmSelections = {};
        },

        selectedVmCount() {
            return Object.keys(this.vmSelections).length;
        },

        selectedDiskCount() {
            return Object.values(this.vmSelections).reduce((acc, sel) => acc + sel.disks.length, 0);
        },

        selectedDisksForVm(bpId) {
            const sel = this.vmSelections[bpId];
            return sel ? sel.disks : [];
        },

        selectedVmsList() {
            return Object.values(this.vmSelections).map((sel) => sel.vm);
        },

        diskKeys(vm) {
            return Object.keys((vm && vm.disk_manifests) || {});
        },

        selectedSizeBytes() {
            return Object.values(this.vmSelections).reduce((acc, sel) => acc + (sel.vm.total_size_bytes || 0), 0);
        },

        subfolderFor(name) {
            const cleaned = String(name || '').replace(/[<>:"/\\|?*\x00-\x1F]/g, '_').trim().replace(/^\.+|\.+$/g, '');
            return cleaned === '' ? 'vm' : cleaned;
        },

        canProceed() {
            if (this.step === 0) {
                return this.selectedSnapshot !== null;
            }
            if (this.step === 1) {
                return this.targetPath.trim() !== '' && this.selectedVmCount() > 0 && this.selectedDiskCount() > 0;
            }
            return true;
        },

        nextStep() {
            if (this.canProceed() && this.step < 2) {
                this.step++;
            }
        },

        async startRestore() {
            if (this.restoring || this.selectedVmCount() === 0) return;
            this.restoring = true;
            try {
                const vms = Object.entries(this.vmSelections).map(([bpId, sel]) => ({
                    backup_point_id: Number(bpId),
                    disks: sel.disks,
                }));
                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_hyperv_start_restore.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        target_path: this.targetPath,
                        vms: JSON.stringify(vms),
                    })
                });
                const data = await resp.json();
                if (data.status === 'success') {
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
                month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
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
{/literal}
