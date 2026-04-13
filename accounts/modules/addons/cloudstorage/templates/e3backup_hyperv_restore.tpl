{capture assign=ebE3Actions}
    {if $vm}
        <div class="flex flex-wrap items-center justify-end gap-2">
            {if $vm.rct_enabled}
                <span class="eb-badge eb-badge--success eb-badge--dot">RCT Enabled</span>
            {/if}
            <span class="eb-badge eb-badge--info">Gen {$vm.generation}</span>
        </div>
    {/if}
{/capture}

{capture assign=ebE3Content}
<div class="space-y-6" x-data="hypervRestoreApp()">
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="eb-breadcrumb-link">Jobs</a>
        <span class="eb-breadcrumb-separator">/</span>
        {if $vm}
            <a href="{if $vm.backup_user_route_id}index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id={$vm.backup_user_route_id|escape:'url'}#hyperv{else}index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id={$vm.job_id}{/if}" class="eb-breadcrumb-link">{$vm.job_name|escape}</a>
            <span class="eb-breadcrumb-separator">/</span>
        {/if}
        <span class="eb-breadcrumb-current">Restore</span>
    </div>

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
    {elseif !$vm}
        <div class="eb-alert eb-alert--danger">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                <div class="eb-alert-title">Not available</div>
                <p>VM not found or you do not have permission to access it.</p>
            </div>
        </div>
    {else}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="eb-stat-card">
                <div class="eb-stat-label">Backup Points</div>
                <div class="eb-stat-value">{$backupPointCount}</div>
            </div>
            <div class="eb-stat-card">
                <div class="eb-stat-label">Full Backups</div>
                <div class="eb-stat-value">{$fullBackupCount}</div>
            </div>
            <div class="eb-stat-card">
                <div class="eb-stat-label">VM Disks</div>
                <div class="eb-stat-value">{$disks|count}</div>
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
                    <template x-for="(stepName, idx) in ['Select Backup Point', 'Configure Restore', 'Review & Start']" :key="idx">
                        <div class="eb-wizard-step">
                            <div
                                class="eb-wizard-step-dot"
                                :class="step > idx ? 'is-complete' : (step === idx ? 'is-current' : 'is-upcoming')"
                                :aria-current="step === idx ? 'step' : false"
                            >
                                <span x-text="idx + 1"></span>
                            </div>
                            <span
                                class="eb-wizard-step-label"
                                :class="step >= idx ? 'is-strong' : 'is-muted'"
                                x-text="stepName"
                            ></span>
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
                <div x-show="step === 0" x-transition>
                    <h3 class="eb-type-h3 mb-4">Select Backup Point</h3>

                    <div class="mb-4 flex flex-wrap items-center gap-4">
                        <div
                            class="relative z-10"
                            @keydown.escape.window="typeFilterMenuOpen = false"
                            @click.away="typeFilterMenuOpen = false"
                        >
                            <button
                                type="button"
                                class="eb-menu-trigger relative min-w-[12rem] text-left"
                                @click="typeFilterMenuOpen = !typeFilterMenuOpen"
                                :aria-expanded="typeFilterMenuOpen"
                                aria-haspopup="listbox"
                                aria-label="Filter backup points by type"
                            >
                                <span class="block min-w-0 truncate" x-text="typeFilterLabel()"></span>
                                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                    <svg
                                        class="h-5 w-5 shrink-0 text-[var(--eb-text-muted)] transition-transform"
                                        :class="typeFilterMenuOpen && 'rotate-180'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                            <div
                                x-show="typeFilterMenuOpen"
                                x-transition
                                class="eb-dropdown-menu absolute left-0 z-20 mt-1 w-full min-w-[12rem] overflow-hidden"
                                style="display: none;"
                                role="listbox"
                                aria-label="Backup type"
                            >
                                <div class="max-h-60 overflow-auto p-1 text-sm scrollbar_thin">
                                    <button
                                        type="button"
                                        role="option"
                                        class="eb-menu-option"
                                        :class="typeFilter === '' && 'is-active'"
                                        @click="selectTypeFilter('')"
                                    >All types</button>
                                    <button
                                        type="button"
                                        role="option"
                                        class="eb-menu-option"
                                        :class="typeFilter === 'Full' && 'is-active'"
                                        @click="selectTypeFilter('Full')"
                                    >Full only</button>
                                    <button
                                        type="button"
                                        role="option"
                                        class="eb-menu-option"
                                        :class="typeFilter === 'Incremental' && 'is-active'"
                                        @click="selectTypeFilter('Incremental')"
                                    >Incremental only</button>
                                </div>
                            </div>
                        </div>
                        <span class="eb-type-caption" x-show="!loading">
                            <span x-text="backupPoints.length"></span> backup points
                        </span>
                    </div>

                    <div x-show="loading" class="flex items-center justify-center py-12">
                        <div class="eb-loading-spinner--compact" role="status" aria-label="Loading"></div>
                    </div>

                    <div x-show="!loading && backupPoints.length > 0" class="eb-table-shell">
                        <table class="eb-table">
                            <thead>
                                <tr>
                                    <th class="w-8"></th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Consistency</th>
                                    <th>Size</th>
                                    <th>Disks</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="bp in backupPoints" :key="bp.id">
                                    <tr
                                        :class="[
                                            selectedPoint && selectedPoint.id === bp.id ? 'is-selected' : '',
                                            bp.is_restorable ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'
                                        ]"
                                        @click="selectBackupPoint(bp)"
                                    >
                                        <td>
                                            <input
                                                type="radio"
                                                class="eb-radio-input"
                                                :checked="selectedPoint && selectedPoint.id === bp.id"
                                                @click.stop
                                                @change="selectBackupPoint(bp)"
                                            >
                                        </td>
                                        <td class="eb-table-primary" x-text="formatDate(bp.created_at)"></td>
                                        <td>
                                            <span
                                                class="eb-badge"
                                                :class="bp.backup_type === 'Full' ? 'eb-badge--info' : 'eb-badge--warning'"
                                                x-text="bp.backup_type"
                                            ></span>
                                        </td>
                                        <td>
                                            <span
                                                class="text-sm font-medium"
                                                :class="bp.consistency_level === 'Application' ? 'text-[var(--eb-success-text)]' : 'text-[var(--eb-warning-text)]'"
                                                x-text="bp.consistency_level || 'Unknown'"
                                            ></span>
                                        </td>
                                        <td class="text-[var(--eb-text-secondary)]" x-text="formatBytes(bp.total_size_bytes)"></td>
                                        <td class="text-[var(--eb-text-secondary)]" x-text="bp.disk_count"></td>
                                        <td>
                                            <template x-if="bp.is_restorable">
                                                <span class="eb-badge eb-badge--success eb-badge--dot">Ready</span>
                                            </template>
                                            <template x-if="!bp.is_restorable">
                                                <span class="eb-badge eb-badge--danger eb-badge--dot">Incomplete</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="!loading && backupPoints.length === 0" class="eb-app-empty !py-12">
                        <span class="eb-icon-box eb-icon-box--lg eb-icon-box--default mx-auto">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </span>
                        <div class="eb-app-empty-title mt-4">No backup points available</div>
                        <p class="eb-app-empty-copy">There are no restore points for this VM yet.</p>
                    </div>

                    <div x-show="!loading && totalPages > 1" class="eb-table-pagination mt-4 border-t border-[var(--eb-border-subtle)] pt-4">
                        <span class="eb-type-caption">
                            Page <span x-text="currentPage + 1"></span> of <span x-text="totalPages"></span>
                        </span>
                        <div class="eb-table-pagination-actions">
                            <button
                                type="button"
                                class="eb-table-pagination-button"
                                @click="prevPage()"
                                :disabled="currentPage === 0"
                            >Previous</button>
                            <button
                                type="button"
                                class="eb-table-pagination-button"
                                @click="nextPage()"
                                :disabled="currentPage >= totalPages - 1"
                            >Next</button>
                        </div>
                    </div>
                </div>

                <div x-show="step === 1" x-transition>
                    <h3 class="eb-type-h3 mb-4">Configure Restore</h3>

                    <div class="eb-subpanel mb-6" x-show="selectedPoint">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <span class="eb-type-caption">Selected backup point</span>
                            <button type="button" @click="step = 0" class="eb-link text-sm">Change</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                            <div>
                                <div class="eb-type-caption">Date</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedPoint ? formatDate(selectedPoint.created_at) : ''"></div>
                            </div>
                            <div>
                                <div class="eb-type-caption">Type</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedPoint ? selectedPoint.backup_type : ''"></div>
                            </div>
                            <div>
                                <div class="eb-type-caption">Size</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedPoint ? formatBytes(selectedPoint.total_size_bytes) : ''"></div>
                            </div>
                            <div>
                                <div class="eb-type-caption">Disks</div>
                                <div class="mt-1 font-medium text-[var(--eb-text-primary)]" x-text="selectedPoint ? selectedPoint.disk_count + ' disk(s)' : ''"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="eb-field-label" for="hyperv-restore-target-path">Target path</label>
                        <input
                            id="hyperv-restore-target-path"
                            type="text"
                            class="eb-input"
                            x-model="targetPath"
                            placeholder="C:\Restored\{$vm.vm_name|escape}"
                        >
                        <p class="eb-field-help">VHDX files will be restored to this directory on the agent machine.</p>
                    </div>

                    <div class="mb-6">
                        <label class="eb-field-label">Disks to restore</label>
                        <div class="eb-subpanel overflow-hidden !p-0">
                            <template x-for="disk in selectedPoint ? Object.keys(selectedPoint.disk_manifests) : []" :key="disk">
                                <label class="flex cursor-pointer items-center gap-3 border-b border-[var(--eb-border-faint)] p-3 transition-colors last:border-b-0 hover:bg-[var(--eb-bg-hover)]">
                                    <input type="checkbox" class="eb-check-input" :value="disk" x-model="selectedDisks">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-[var(--eb-text-primary)]" x-text="disk.split('\\').pop()"></div>
                                        <div class="eb-type-mono mt-0.5 truncate text-[var(--eb-text-muted)]" x-text="disk"></div>
                                    </div>
                                </label>
                            </template>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            <button type="button" @click="selectAllDisks()" class="eb-link text-sm">Select all</button>
                            <button type="button" @click="selectedDisks = []" class="eb-link text-sm">Select none</button>
                        </div>
                    </div>

                    <div
                        x-show="selectedPoint && selectedPoint.backup_type === 'Incremental'"
                        class="eb-alert eb-alert--warning"
                    >
                        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <div class="eb-alert-title">Incremental backup selected</div>
                            <p class="mt-1">
                                This is an incremental backup with <span x-text="selectedPoint ? selectedPoint.restore_chain_length : 0"></span> backup(s) in the chain.
                                For simpler recovery, consider selecting a full backup point.
                            </p>
                        </div>
                    </div>
                </div>

                <div x-show="step === 2" x-transition>
                    <h3 class="eb-type-h3 mb-4">Review &amp; start restore</h3>

                    <div class="eb-subpanel mb-6">
                        <dl class="eb-kv-list">
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">VM name</span>
                                <span class="eb-kv-value">{$vm.vm_name|escape}</span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Backup date</span>
                                <span class="eb-kv-value" x-text="selectedPoint ? formatDate(selectedPoint.created_at) : ''"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Backup type</span>
                                <span class="eb-kv-value" x-text="selectedPoint ? selectedPoint.backup_type : ''"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Consistency</span>
                                <span class="eb-kv-value" x-text="selectedPoint ? selectedPoint.consistency_level : ''"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Target path</span>
                                <span class="eb-kv-value eb-type-mono max-w-[min(100%,20rem)] break-all" x-text="targetPath"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Disks to restore</span>
                                <span class="eb-kv-value" x-text="selectedDisks.length + ' of ' + (selectedPoint ? Object.keys(selectedPoint.disk_manifests).length : 0)"></span>
                            </div>
                            <div class="eb-kv-row">
                                <span class="eb-kv-label">Estimated size</span>
                                <span class="eb-kv-value" x-text="selectedPoint ? formatBytes(selectedPoint.total_size_bytes) : ''"></span>
                            </div>
                        </dl>
                    </div>

                    <div class="eb-alert eb-alert--info">
                        <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <div class="eb-alert-title">Ready to restore</div>
                            <p class="mt-1">
                                VHDX files will be restored to the target path. After restore completes, you can manually attach
                                the disks to a new or existing VM in Hyper-V Manager.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--eb-border-subtle)] px-6 py-4">
                <div>
                    <button
                        x-show="step > 0"
                        type="button"
                        @click="step--"
                        class="eb-btn eb-btn-secondary eb-btn-sm"
                    >Back</button>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                    <a
                        href="{if $vm.backup_user_route_id}index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id={$vm.backup_user_route_id|escape:'url'}#hyperv{else}index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id={$vm.job_id}{/if}"
                        class="eb-btn eb-btn-secondary eb-btn-sm"
                    >Cancel</a>

                    <button
                        x-show="step < 2"
                        type="button"
                        @click="nextStep()"
                        :disabled="!canProceed()"
                        class="eb-btn eb-btn-primary eb-btn-sm"
                        :class="!canProceed() && 'disabled'"
                    >Next</button>

                    <button
                        x-show="step === 2"
                        type="button"
                        @click="startRestore()"
                        :disabled="restoring"
                        class="eb-btn eb-btn-upgrade eb-btn-sm"
                        :class="restoring && 'cursor-wait'"
                    >
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

{capture assign=ebE3HypervRestoreTitle}{if $vm}Restore: {$vm.vm_name|escape}{else}Hyper-V Restore{/if}{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='hyperv'
    ebE3Title=$ebE3HypervRestoreTitle
    ebE3Description='Select a backup point and restore VM disks.'
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

<script>
function hypervRestoreApp() {
    return {
        step: 0,
        loading: true,
        backupPoints: [],
        selectedPoint: null,
        vmId: {$vmId|default:0},
        vmData: {$vm|json_encode nofilter},
        targetPath: 'C:\\Restored\\{$vm.vm_name|default:'VM'|escape:'javascript'}',
        selectedDisks: [],
        typeFilter: '',
        typeFilterMenuOpen: false,
        currentPage: 0,
        pageSize: 20,
        total: 0,
        restoring: false,

        get totalPages() {
            return Math.ceil(this.total / this.pageSize);
        },

        typeFilterLabel() {
            if (this.typeFilter === 'Full') {
                return 'Full only';
            }
            if (this.typeFilter === 'Incremental') {
                return 'Incremental only';
            }
            return 'All types';
        },

        selectTypeFilter(value) {
            const changed = this.typeFilter !== value;
            this.typeFilter = value;
            this.typeFilterMenuOpen = false;
            if (changed) {
                this.loadBackupPoints();
            }
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
