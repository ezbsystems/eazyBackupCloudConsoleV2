<link rel="stylesheet" href="modules/addons/cloudstorage/assets/css/ms365_job_wizard.css?v=3">

<div id="ms365JobWizardModal" class="ms365-job-wizard-modal-host fixed inset-0 z-[2200] hidden" x-data="ms365WizardApp()" x-cloak>
    <div class="eb-modal-backdrop absolute inset-0" @click="close()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="eb-modal ms365-wizard-dialog relative z-10 flex flex-col overflow-hidden !p-0 max-w-4xl w-full max-h-[90vh]">
            <div class="eb-modal-header shrink-0 !mb-0 px-6 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="eb-type-eyebrow">Microsoft 365</p>
                        <h3 class="eb-modal-title" x-text="editMode ? 'Edit backup job' : 'New Microsoft 365 backup'"></h3>
                    </div>
                    <button type="button" class="eb-modal-close" @click="close()" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <nav class="flex flex-wrap items-center gap-1">
                    <template x-for="(label, idx) in stepLabels" :key="idx">
                        <span class="flex items-center gap-1">
                            <button type="button"
                                    class="ms365-wizard-crumb flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all"
                                    :class="step === (idx + 1) ? 'is-active' : (step > (idx + 1) ? 'is-complete' : '')"
                                    :disabled="!canGoToStep(idx + 1)"
                                    @click="goToStep(idx + 1)">
                                <span class="ms365-wizard-crumb-step flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold" x-text="idx + 1"></span>
                                <span class="hidden sm:inline" x-text="label"></span>
                            </button>
                            <svg x-show="idx < stepLabels.length - 1" class="h-4 w-4 text-[var(--eb-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </template>
                </nav>
            </div>

            <div class="eb-modal-body flex-1 overflow-y-auto px-6 py-4">
                <div x-show="loading" class="ms365-inventory-progress py-12 text-center">
                    <div class="eb-loading-spinner--compact mx-auto" role="status" aria-label="Loading"></div>
                    <p class="eb-type-caption mt-4" x-text="refreshingInventory ? inventoryProgressMessage() : 'Loading…'"></p>
                    <div class="flex flex-wrap gap-2 justify-center mt-3" x-show="refreshingInventory && inventoryProgressChips().length > 0">
                        <template x-for="chip in inventoryProgressChips()" :key="chip.key">
                            <span class="eb-badge eb-badge--neutral text-xs" x-text="chip.label + ': ' + chip.count"></span>
                        </template>
                    </div>
                    <p class="eb-type-caption text-[var(--eb-text-muted)] mt-2 max-w-md mx-auto"
                       x-show="refreshingInventory && inventoryProgress.detail"
                       x-text="inventoryProgress.detail"></p>
                </div>

                <div x-show="!loading">
                    <!-- Step 1: Connect -->
                    <div x-show="step === 1" class="space-y-4">
                        <p class="eb-card-subtitle">Connect your Microsoft 365 organization. Sign in with a Global Administrator account that belongs to the tenant you want to back up and approve access for your organization.</p>
                        <template x-if="status.connected && !status.needs_reconnect">
                            <div class="eb-alert eb-alert--success">
                                <div class="eb-alert-title">Connected</div>
                                <p class="eb-type-caption !mt-1" x-show="status.azure_tenant_id">Tenant: <span x-text="status.azure_tenant_id"></span></p>
                                <p class="eb-type-caption !mt-1" x-show="status.bucket_name">Storage: <span x-text="status.bucket_name"></span></p>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="confirmSwitchOrganization()" :disabled="disconnecting || connecting">
                                        Connect a different organization
                                    </button>
                                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm !text-[var(--eb-text-danger)]" @click="confirmDisconnect()" :disabled="disconnecting || connecting">
                                        Disconnect
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template x-if="status.needs_reconnect && !awaitingConsent">
                            <div class="eb-alert eb-alert--warning">
                                <div class="eb-alert-title">Reconnection required</div>
                                <p class="eb-type-caption !mt-1" x-text="status.health_error || 'Microsoft 365 access was removed or expired. Reconnect your organization to continue.'"></p>
                                <p class="eb-type-caption !mt-1" x-show="status.azure_tenant_id">Previous tenant: <span x-text="status.azure_tenant_id"></span></p>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="connect()" :disabled="connecting || disconnecting">
                                        <span x-text="connecting ? 'Opening Microsoft…' : 'Reconnect Microsoft 365'"></span>
                                    </button>
                                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm !text-[var(--eb-text-danger)]" @click="confirmDisconnect()" :disabled="disconnecting || connecting">
                                        Disconnect
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div x-show="awaitingConsent" x-cloak class="eb-alert eb-alert--info">
                            <div class="flex items-start gap-3">
                                <div class="eb-loading-spinner--compact shrink-0 mt-0.5" role="status" aria-label="Waiting for Microsoft sign-in"></div>
                                <div class="min-w-0">
                                    <div class="eb-alert-title">Waiting for Microsoft sign-in</div>
                                    <p class="eb-type-caption !mt-1">Complete admin consent in the popup window. This wizard will continue automatically when your tenant is connected.</p>
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="reopenConsentPopup()">Reopen Microsoft window</button>
                                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" @click="cancelConsentWait()">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <template x-if="!status.connected && !status.needs_reconnect && !awaitingConsent">
                            <button type="button" class="eb-btn eb-btn-primary" @click="connect()" :disabled="connecting">
                                <span x-text="connecting ? 'Opening Microsoft…' : 'Connect Microsoft 365'"></span>
                            </button>
                        </template>
                        <p class="text-sm text-[var(--eb-text-danger)]" x-show="consentError" x-text="consentError"></p>
                        <p class="text-sm text-[var(--eb-text-danger)]" x-show="status.health_error && !consentError" x-text="status.health_error"></p>
                    </div>

                    <!-- Step 2: Inventory -->
                    <div x-show="step === 2" class="space-y-3 relative">
                        <div x-show="refreshingInventory && !loading"
                             x-cloak
                             class="ms365-inventory-progress absolute inset-0 z-10 flex flex-col items-center justify-center rounded-lg bg-[var(--eb-surface-base)]/90 px-6 text-center">
                            <div class="eb-loading-spinner--compact" role="status" aria-label="Refreshing inventory"></div>
                            <p class="eb-type-caption mt-4" x-text="inventoryProgressMessage()"></p>
                            <div class="flex flex-wrap gap-2 justify-center mt-3" x-show="inventoryProgressChips().length > 0">
                                <template x-for="chip in inventoryProgressChips()" :key="'refresh-' + chip.key">
                                    <span class="eb-badge eb-badge--neutral text-xs" x-text="chip.label + ': ' + chip.count"></span>
                                </template>
                            </div>
                            <p class="eb-type-caption text-[var(--eb-text-muted)] mt-2 max-w-md"
                               x-show="inventoryProgress.detail"
                               x-text="inventoryProgress.detail"></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 justify-between">
                            <input type="search" class="eb-input flex-1 min-w-[12rem]" placeholder="Search resources…" x-model="searchQuery">
                            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="refreshInventory()" :disabled="refreshingInventory">
                                <span x-text="refreshingInventory ? 'Refreshing…' : 'Refresh inventory'"></span>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 min-h-[22rem]">
                            <div class="ms365-inventory-pane border border-[var(--eb-border-default)] rounded-lg overflow-hidden flex flex-col">
                                <div class="eb-menu-label px-3 py-2 border-b border-[var(--eb-border-default)]">Tenant inventory</div>
                                <div class="flex-1 overflow-y-auto p-2 space-y-4">
                                    <template x-for="section in inventorySections" :key="section.key">
                                        <div x-show="filteredResources(section.types).length > 0">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)] mb-2 px-1" x-text="section.label"></div>
                                            <div class="flex flex-col gap-1.5">
                                            <template x-for="res in filteredResources(section.types)" :key="res.id">
                                                <label class="ms365-inventory-row flex items-start gap-2 p-2 rounded-lg cursor-pointer hover:bg-[var(--eb-surface-muted)]">
                                                    <input type="checkbox" class="eb-check-input mt-1" :value="res.id" :checked="selectedIds.includes(res.id)" @change="toggleResource(res.id)">
                                                    <span class="flex-1 min-w-0">
                                                        <span class="block text-sm text-[var(--eb-text-primary)] truncate" x-text="res.display_name || res.id"></span>
                                                        <span class="block text-xs text-[var(--eb-text-muted)] truncate" x-show="res.email" x-text="res.email"></span>
                                                        <span class="flex flex-wrap gap-1 mt-1">
                                                            <template x-for="chip in (res.capability_chips || [])" :key="chip">
                                                                <span class="eb-badge eb-badge--neutral text-[10px]" x-text="chip"></span>
                                                            </template>
                                                        </span>
                                                    </span>
                                                </label>
                                            </template>
                                            </div>
                                        </div>
                                    </template>
                                    <p x-show="!inventory.resources || inventory.resources.length === 0" class="eb-type-caption p-4 text-center">No inventory loaded. Click Refresh inventory.</p>
                                </div>
                            </div>
                            <div class="ms365-selection-pane border border-[var(--eb-border-default)] rounded-lg overflow-hidden flex flex-col">
                                <div class="eb-menu-label px-3 py-2 border-b border-[var(--eb-border-default)]">
                                    Selected for backup (<span x-text="selectedIds.length"></span>)
                                </div>
                                <div class="flex-1 overflow-y-auto p-2 space-y-3">
                                    <template x-if="selectedIds.length === 0">
                                        <p class="eb-type-caption text-center py-8">Select resources on the left to include them in this backup job.</p>
                                    </template>
                                    <template x-for="section in inventorySections" :key="'sel-' + section.key">
                                        <div x-show="selectedInSection(section.types).length > 0">
                                            <div class="text-xs font-semibold text-[var(--eb-text-muted)] mb-1 px-1" x-text="section.label"></div>
                                            <template x-for="res in selectedInSection(section.types)" :key="'s-' + res.id">
                                                <div class="flex items-center justify-between gap-2 py-1 px-2 text-sm rounded bg-[var(--eb-surface-muted)]">
                                                    <span class="truncate" x-text="res.display_name || res.id"></span>
                                                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm shrink-0" @click="toggleResource(res.id)">Remove</button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Schedule -->
                    <div x-show="step === 3" class="space-y-3">
                        <p class="eb-card-subtitle">Backups run automatically in the evening. Start times are assigned by the system between 7:00 PM and 11:59 PM.</p>
                        <button type="button"
                                class="ms365-option-card w-full text-left"
                                :class="scheduleFrequency === 'once_daily' ? 'is-selected' : ''"
                                @click="scheduleFrequency = 'once_daily'">
                            <span class="block font-medium text-[var(--eb-text-primary)]">Once daily</span>
                            <span class="block text-sm text-[var(--eb-text-muted)] mt-1">Included in the base price.</span>
                        </button>
                        <button type="button"
                                class="ms365-option-card w-full text-left"
                                :class="scheduleFrequency === 'twice_daily' ? 'is-selected' : ''"
                                @click="scheduleFrequency = 'twice_daily'">
                            <span class="block font-medium text-[var(--eb-text-primary)]">Twice daily</span>
                            <span class="block text-sm text-[var(--eb-text-muted)] mt-1">Extra fee applies.</span>
                        </button>
                    </div>

                    <!-- Step 4: Retention placeholder -->
                    <div x-show="step === 4" class="space-y-3">
                        <p class="eb-card-subtitle">Choose a retention policy (billing integration coming soon).</p>
                        <template x-for="opt in retentionOptions" :key="opt.id">
                            <button type="button"
                                    class="ms365-option-card w-full text-left"
                                    :class="retentionTier === opt.id ? 'is-selected' : ''"
                                    @click="retentionTier = opt.id">
                                <span class="block font-medium text-[var(--eb-text-primary)]" x-text="opt.title"></span>
                                <span class="block text-sm text-[var(--eb-text-muted)] mt-1" x-text="opt.description"></span>
                            </button>
                        </template>
                        <div class="pt-2">
                            <label class="eb-field-label">Job name</label>
                            <input type="text" class="eb-input w-full" x-model="jobName" placeholder="Microsoft 365 Backup">
                        </div>
                    </div>
                </div>
            </div>

            <div class="eb-modal-footer shrink-0 flex items-center justify-between gap-2 px-6 py-4 border-t border-[var(--eb-border-default)]">
                <button type="button" class="eb-btn eb-btn-secondary" @click="step > 1 ? goToStep(step - 1) : close()" x-text="step > 1 ? 'Back' : 'Cancel'"></button>
                <div class="flex gap-2">
                    <button type="button" class="eb-btn eb-btn-primary" x-show="step < 4" @click="nextStep()" :disabled="!canProceed()">
                        Next
                    </button>
                    <button type="button" class="eb-btn eb-btn-success" x-show="step === 4" @click="save()" :disabled="saving">
                        <span x-text="saving ? 'Saving…' : (editMode ? 'Save job' : 'Create job')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="confirmModal.open" x-cloak class="absolute inset-0 z-20 flex items-center justify-center p-4">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeConfirmModal()"></div>
        <div class="eb-modal relative z-10 max-w-md w-full">
            <div class="eb-modal-header">
                <h3 class="eb-modal-title" x-text="confirmModal.title"></h3>
                <button type="button" class="eb-modal-close" @click="closeConfirmModal()" aria-label="Close">&times;</button>
            </div>
            <div class="eb-modal-body">
                <p class="text-sm text-[var(--eb-text-muted)]" x-text="confirmModal.message"></p>
            </div>
            <div class="eb-modal-footer flex justify-end gap-2">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeConfirmModal()" :disabled="disconnecting">Cancel</button>
                <button type="button"
                        class="eb-btn eb-btn-sm"
                        :class="confirmModal.action === 'disconnect' ? 'eb-btn-danger-solid' : 'eb-btn-primary'"
                        @click="executeConfirmModal()"
                        :disabled="disconnecting">
                    <span x-text="disconnecting ? 'Working…' : confirmModal.confirmLabel"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="modules/addons/cloudstorage/assets/js/ms365_job_wizard.js?v=9"></script>
