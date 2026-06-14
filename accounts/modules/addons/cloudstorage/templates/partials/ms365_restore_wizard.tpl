<link rel="stylesheet" href="modules/addons/cloudstorage/assets/css/ms365_job_wizard.css?v=3">
<link rel="stylesheet" href="modules/addons/cloudstorage/assets/css/ms365_restore_wizard.css?v=3">

<div id="ms365RestoreWizardModal" class="ms365-job-wizard-modal-host fixed inset-0 z-[2200] hidden" x-data="ms365RestoreWizardApp()" x-cloak>
    <div class="eb-modal-backdrop absolute inset-0" @click="close()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="eb-modal ms365-wizard-dialog relative z-10 flex flex-col overflow-hidden !p-0 max-w-4xl w-full max-h-[90vh]">
            <div class="eb-modal-header shrink-0 !mb-0 px-6 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="eb-type-eyebrow">Microsoft 365</p>
                        <h3 class="eb-modal-title">Restore from backup</h3>
                    </div>
                    <button type="button" class="eb-modal-close" @click="close()" aria-label="Close">&times;</button>
                </div>
                <nav class="flex flex-wrap items-center gap-1">
                    <template x-for="(label, idx) in stepLabels" :key="idx">
                        <span class="flex items-center gap-1">
                            <button type="button"
                                    class="ms365-wizard-crumb flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all"
                                    :class="step === (idx + 1) ? 'is-active' : (step > (idx + 1) ? 'is-complete' : '')"
                                    :disabled="step < (idx + 1)"
                                    @click="step >= (idx + 1) && goToStep(idx + 1)">
                                <span class="ms365-wizard-crumb-step flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold" x-text="idx + 1"></span>
                                <span class="hidden sm:inline" x-text="label"></span>
                            </button>
                            <svg x-show="idx < stepLabels.length - 1" class="h-4 w-4 text-[var(--eb-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </template>
                </nav>
            </div>

            <div class="eb-modal-body flex-1 overflow-y-auto px-6 py-4">
                <div x-show="loading" class="py-12 text-center">
                    <div class="eb-loading-spinner--compact mx-auto"></div>
                    <p class="eb-type-caption mt-4">Loading…</p>
                </div>

                <div x-show="!loading">
                    <div x-show="step === 1 && snapshot" class="space-y-4">
                        <p class="eb-card-subtitle">Confirm the backup snapshot you want to restore from.</p>
                        <div class="eb-card p-4">
                            <div class="font-medium text-[var(--eb-text-primary)]" x-text="snapshotTitle()"></div>
                            <div class="eb-type-caption mt-1" x-text="snapshotJobName()"></div>
                            <div class="eb-type-caption mt-2" x-text="snapshotWorkloadCount() + ' workloads in this snapshot'"></div>
                        </div>
                    </div>

                    <div x-show="step === 2" class="space-y-3">
                        <div class="flex gap-2">
                            <input type="search" class="eb-input flex-1" placeholder="Search tree…" x-model="treeSearch">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 min-h-[320px]">
                            <div class="ms365-inventory-pane border border-[var(--eb-border-default)] rounded-lg overflow-hidden flex flex-col">
                                <div class="eb-menu-label px-3 py-2 border-b border-[var(--eb-border-default)]">Available to restore</div>
                                <div class="flex-1 overflow-y-auto p-2 text-sm" id="ms365RestoreTreeRoot">
                                    <template x-for="node in filteredTreeNodes()" :key="node.key">
                                        <div class="ms365-tree-node" :style="'padding-left:' + (node.depth * 12) + 'px'">
                                            <span class="ms365-tree-toggle-slot">
                                                <span x-show="node.loading" class="ms365-tree-spinner eb-loading-spinner--compact"></span>
                                                <button type="button"
                                                        class="ms365-tree-toggle"
                                                        x-show="node.has_children && !node.loading"
                                                        @click="toggleExpand(node)"
                                                        x-text="node.expanded ? '▼' : '▶'"></button>
                                            </span>
                                            <input type="checkbox" class="eb-checkbox" :checked="isSelected(node)" @change="toggleSelect(node)">
                                            <button type="button"
                                                    class="ms365-tree-label"
                                                    :class="node.loading ? 'is-loading' : ''"
                                                    @click="node.has_children && toggleExpand(node)">
                                                <span class="ms365-tree-label-primary" x-text="node.label || node.name"></span>
                                                <span class="ms365-tree-label-secondary" x-show="node.subtitle" x-text="node.subtitle"></span>
                                            </button>
                                        </div>
                                    </template>
                                    <p x-show="treeNodes.length === 0" class="eb-type-caption text-center py-8">Expand workloads to browse contents.</p>
                                </div>
                            </div>
                            <div class="ms365-selection-pane border border-[var(--eb-border-default)] rounded-lg overflow-hidden flex flex-col">
                                <div class="eb-menu-label px-3 py-2 border-b border-[var(--eb-border-default)]">
                                    Selected for restore (<span x-text="selectedItems.length"></span>)
                                </div>
                                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                                    <template x-if="selectedItems.length === 0">
                                        <p class="eb-type-caption text-center py-8">Select items on the left to restore.</p>
                                    </template>
                                    <template x-for="(item, idx) in selectedItems" :key="'sel-' + idx">
                                        <div class="flex items-center justify-between gap-2 py-1 px-2 text-sm rounded bg-[var(--eb-surface-muted)]">
                                            <div class="min-w-0">
                                                <div class="truncate" x-text="item.label"></div>
                                                <div class="truncate text-xs text-[var(--eb-text-muted)]" x-show="item.subtitle" x-text="item.subtitle"></div>
                                            </div>
                                            <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm shrink-0" @click="removeSelected(idx)">Remove</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="step === 3" class="space-y-3">
                        <p class="eb-card-subtitle">Choose where to restore data in your Microsoft 365 tenant.</p>
                        <div class="max-h-64 overflow-y-auto space-y-2 pr-1">
                            <template x-for="res in inventoryResources" :key="res.id">
                                <button type="button"
                                        class="eb-choice-card w-full text-left cursor-pointer"
                                        :class="targetResource && targetResource.id === res.id ? 'is-selected' : ''"
                                        @click="targetResource = res">
                                    <span class="eb-choice-card-control">
                                        <input type="radio"
                                               name="ms365-restore-target"
                                               class="eb-radio-input"
                                               :checked="targetResource && targetResource.id === res.id"
                                               @change="targetResource = res"
                                               @click.stop>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="eb-choice-card-title block" x-text="res.display_name || res.id"></span>
                                        <span class="eb-choice-card-description block" x-text="res.email || res.resource_type"></span>
                                    </span>
                                </button>
                            </template>
                            <p x-show="inventoryResources.length === 0" class="eb-type-caption text-center py-8">No restore targets found for this tenant.</p>
                        </div>
                    </div>

                    <div x-show="step === 4 && snapshot" class="space-y-4">
                        <div class="eb-alert eb-alert--info">
                            <div class="eb-alert-title">Skip duplicates</div>
                            <p class="eb-type-caption !mt-1">Existing mail, calendar events, and files that match backed-up items will be skipped (not overwritten).</p>
                        </div>
                        <ul class="text-sm space-y-1 text-[var(--eb-text-secondary)]">
                            <li><strong>Snapshot:</strong> <span x-text="snapshotTitle()"></span></li>
                            <li><strong>Items:</strong> <span x-text="selectedItems.length"></span></li>
                            <li><strong>Target:</strong> <span x-text="targetResource ? (targetResource.display_name || targetResource.id) : '—'"></span></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="eb-modal-footer shrink-0 flex items-center justify-between gap-2 px-6 py-4 border-t border-[var(--eb-border-default)]">
                <button type="button" class="eb-btn eb-btn-secondary" @click="step > 1 ? goToStep(step - 1) : close()">Back</button>
                <button type="button" class="eb-btn eb-btn-primary" x-show="step < 4" @click="nextStep()" :disabled="!canProceed()">Next</button>
                <button type="button" class="eb-btn eb-btn-success" x-show="step === 4" @click="startRestore()" :disabled="starting">
                    <span x-text="starting ? 'Starting…' : 'Start restore'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="modules/addons/cloudstorage/assets/js/ms365_restore_wizard.js?v=5"></script>
