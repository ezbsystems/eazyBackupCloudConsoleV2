<link rel="stylesheet" href="modules/addons/cloudstorage/assets/css/ms365_job_wizard.css?v=12">
<link rel="stylesheet" href="modules/addons/cloudstorage/assets/css/ms365_restore_wizard.css?v=4">

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

            <div class="eb-modal-body flex-1 min-h-0 px-6 py-4"
                 :class="step === 2 && !loading ? 'overflow-hidden flex flex-col' : 'overflow-y-auto'">
                <div x-show="loading" class="ms365-inventory-progress py-12 text-center">
                    <div class="eb-loading-spinner--compact mx-auto" role="status" aria-label="Loading"></div>
                    <p class="eb-type-caption mt-4" x-text="refreshingInventory ? inventoryProgressMessage() : 'Loading…'"></p>
                    <div class="flex flex-wrap gap-2 justify-center mt-3" x-show="refreshingInventory && inventoryProgressChips().length > 0">
                        <template x-for="chip in inventoryProgressChips()" :key="chip.key">
                            <span class="eb-badge eb-badge--neutral text-xs" x-text="chip.label + ': ' + chip.count"></span>
                        </template>
                    </div>
                    <p class="eb-type-caption text-[var(--eb-text-muted)] mt-2 max-w-md mx-auto"
                       x-show="refreshingInventory && inventoryProgress.detail && inventoryProgress.phase !== 'error'"
                       x-text="inventoryProgress.detail"></p>
                </div>

                <div x-show="!loading" :class="step === 2 ? 'flex flex-col flex-1 min-h-0' : ''">
                    <!-- Step 1: Connect -->
                    <div x-show="step === 1" class="space-y-4">
                        <div x-show="showConnectModeToggle()" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[var(--eb-border-default)] px-4 py-3">
                            <div>
                                <div class="text-sm font-medium text-[var(--eb-text-primary)]">Connection method</div>
                                <p class="eb-type-caption !mt-0.5" x-text="connectMode === 'automatic' ? 'Sign in with Microsoft admin consent (recommended).' : 'Enter your own Entra app credentials.'"></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-medium" :class="connectMode === 'automatic' ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'">Automatic</span>
                                <button type="button"
                                        class="eb-toggle"
                                        :disabled="isOAuthConnected() && status.connected"
                                        @click="setConnectMode(connectMode === 'automatic' ? 'manual' : 'automatic')"
                                        :aria-pressed="connectMode === 'manual'">
                                    <div class="eb-toggle-track" :class="connectMode === 'manual' && 'is-on'">
                                        <div class="eb-toggle-thumb"></div>
                                    </div>
                                </button>
                                <span class="text-xs font-medium" :class="connectMode === 'manual' ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'">Manual</span>
                            </div>
                        </div>
                        <p class="eb-alert eb-alert--warning !mb-0" x-show="isOAuthConnected() && status.connected && connectMode === 'manual'" x-cloak>
                            Disconnect the current Microsoft 365 connection before switching to manual credentials.
                        </p>

                        <div x-show="connectMode === 'automatic'" class="rounded-lg border border-[var(--eb-border-default)] px-4 py-4">
                            <div class="space-y-4">
                            <p class="eb-card-subtitle !mb-0">Connect your Microsoft 365 organization. Sign in with a Global Administrator account that belongs to the tenant you want to back up and approve access for your organization.</p>
                            <template x-if="status.connected && !status.needs_reconnect && isOAuthConnected()">
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
                            <template x-if="status.needs_reconnect && !awaitingConsent && isOAuthConnected()">
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
                            <div x-show="!status.connected && !status.needs_reconnect && !awaitingConsent" class="pt-2">
                                <button type="button" class="eb-btn eb-btn-primary" @click="connect()" :disabled="connecting">
                                    <span x-text="connecting ? 'Opening Microsoft…' : 'Connect Microsoft 365'"></span>
                                </button>
                            </div>
                            </div>
                        </div>

                        <div x-show="connectMode === 'manual'" class="rounded-lg border border-[var(--eb-border-default)] px-4 py-4 space-y-4">
                            <p class="eb-card-subtitle !mb-0">Register an Entra ID application in your tenant, grant the required application permissions, and enter the credentials below.</p>
                            <template x-if="status.connected && !status.needs_reconnect && isManualConnected()">
                                <div class="eb-alert eb-alert--success">
                                    <div class="eb-alert-title">Connected</div>
                                    <p class="eb-type-caption !mt-1" x-show="status.azure_tenant_id">Tenant: <span x-text="status.azure_tenant_id"></span></p>
                                    <p class="eb-type-caption !mt-1" x-show="status.bucket_name">Storage: <span x-text="status.bucket_name"></span></p>
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm !text-[var(--eb-text-danger)]" @click="confirmDisconnect()" :disabled="disconnecting">
                                            Disconnect
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="status.needs_reconnect && isManualConnected()">
                                <div class="eb-alert eb-alert--warning">
                                    <div class="eb-alert-title">Reconnection required</div>
                                    <p class="eb-type-caption !mt-1" x-text="status.health_error || 'Microsoft 365 credentials could not be verified. Update credentials and save again.'"></p>
                                </div>
                            </template>
                            <div class="grid gap-4 sm:grid-cols-2" x-show="!status.connected || status.needs_reconnect || isManualConnected()">
                                <div class="sm:col-span-2">
                                    <label class="eb-field-label" for="ms365-manual-region">REGION</label>
                                    <select id="ms365-manual-region" class="eb-select w-full mt-1" x-model="manualForm.region" @change="clearManualTestPassed()">
                                        <template x-for="region in manualRegions" :key="region">
                                            <option :value="region" x-text="region"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="eb-field-label" for="ms365-manual-client-id">CLIENT_ID</label>
                                    <input id="ms365-manual-client-id" type="text" class="eb-input w-full mt-1 font-mono text-sm" x-model="manualForm.client_id" autocomplete="off" @input="clearManualTestPassed()">
                                </div>
                                <div>
                                    <label class="eb-field-label" for="ms365-manual-tenant-id">TENANT_ID</label>
                                    <input id="ms365-manual-tenant-id" type="text" class="eb-input w-full mt-1 font-mono text-sm" x-model="manualForm.tenant_id" autocomplete="off" @input="clearManualTestPassed()">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="eb-field-label" for="ms365-manual-app-secret">APP_SECRET</label>
                                    <input id="ms365-manual-app-secret"
                                           type="password"
                                           class="eb-input w-full mt-1"
                                           x-model="manualForm.app_secret"
                                           autocomplete="new-password"
                                           @input="clearManualTestPassed()"
                                           :placeholder="manualSecretPlaceholder()">
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2" x-show="!status.connected || status.needs_reconnect || isManualConnected()">
                                <button type="button" class="eb-btn eb-btn-secondary" @click="testManualConnect()" :disabled="manualTesting || manualSaving">
                                    <span x-text="manualTesting ? 'Testing…' : 'Test connection'"></span>
                                </button>
                                <button type="button" class="eb-btn eb-btn-primary" @click="saveManualConnect()" :disabled="manualSaving || manualTesting">
                                    <span x-text="manualSaving ? 'Saving…' : 'Save credentials'"></span>
                                </button>
                            </div>
                            <div class="eb-alert eb-alert--success" x-show="manualNotice" x-cloak>
                                <div x-text="manualNotice"></div>
                            </div>
                        </div>

                        <p class="text-sm text-[var(--eb-text-danger)]" x-show="consentError && connectMode === 'automatic'" x-text="consentError"></p>
                        <p class="text-sm text-[var(--eb-text-danger)]" x-show="manualError && connectMode === 'manual'" x-text="manualError"></p>
                        <p class="text-sm text-[var(--eb-text-danger)]" x-show="status.health_error && !consentError && !manualError && connectMode === 'automatic'" x-text="status.health_error"></p>
                    </div>

                    <!-- Step 2: Inventory -->
                    <div x-show="step === 2" class="ms365-wizard-step2 flex flex-col flex-1 min-h-0 gap-3 relative">
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
                               x-show="inventoryProgress.detail && inventoryProgress.phase !== 'error'"
                               x-text="inventoryProgress.detail"></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 justify-end">
                            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="refreshInventory()" :disabled="refreshingInventory">
                                <span x-text="refreshingInventory ? 'Refreshing…' : 'Refresh inventory'"></span>
                            </button>
                        </div>
                        <div class="ms365-wizard-step2__grid grid grid-cols-1 lg:grid-cols-2 gap-4 flex-1 min-h-0">
                            <div class="ms365-inventory-pane border border-[var(--eb-border-default)] rounded-lg overflow-hidden flex flex-col">
                                <div class="eb-menu-label px-3 py-2 border-b border-[var(--eb-border-default)]">Tenant inventory</div>
                                <div class="ms365-inventory-search-row px-3 py-2 border-b border-[var(--eb-border-default)] bg-[var(--eb-surface-muted)]"
                                     x-show="inventory.resources && inventory.resources.length > 0">
                                    <div class="ms365-inventory-search-wrap">
                                        <input type="search"
                                               class="eb-input ms365-inventory-search-input"
                                               placeholder="Search users, sites, teams…"
                                               aria-label="Filter tenant inventory"
                                               x-model.debounce.200ms="searchQuery">
                                        <button type="button"
                                                class="ms365-inventory-search-clear"
                                                x-show="inventoryFilterActive()"
                                                @click="searchQuery = ''"
                                                aria-label="Clear search"
                                                title="Clear search">&times;</button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 px-3 py-2 border-b border-[var(--eb-border-default)] bg-[var(--eb-surface-muted)]"
                                     x-show="inventory.resources && inventory.resources.length > 0">
                                    <input type="checkbox"
                                           id="ms365-inventory-select-all"
                                           class="eb-check-input shrink-0"
                                           :checked="inventoryGlobalCheckState() === 'checked'"
                                           x-init="$el.indeterminate = inventoryGlobalCheckState() === 'indeterminate'"
                                           @change="toggleSelectAllInventory(); $el.indeterminate = inventoryGlobalCheckState() === 'indeterminate'; $el.checked = inventoryGlobalCheckState() === 'checked'">
                                    <label for="ms365-inventory-select-all" class="text-sm font-medium text-[var(--eb-text-primary)] cursor-pointer select-none">
                                        Select all resources
                                    </label>
                                </div>
                                <div class="flex-1 overflow-y-auto p-2 space-y-4">
                                    <template x-for="section in inventorySections()" :key="section.key">
                                        <div x-show="sectionHasNodes(section.key)">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)] mb-2 px-1" x-text="section.label"></div>
                                            <p class="text-xs text-[var(--eb-text-muted)] mb-2 px-1"
                                               x-show="section.key === 'sharepoint' && inaccessibleSiteCount() > 0"
                                               x-text="inaccessibleSiteCount() + ' sites cannot be backed up — the app does not have access.'"></p>
                                            <div class="flex flex-col gap-0.5">
                                                <template x-for="node in visibleSectionNodes(section.key)" :key="section.key + '-' + node.key">
                                                    <div class="ms365-tree-node"
                                                         :class="{ 'ms365-tree-node--disabled': node.selectable === false }"
                                                         :style="'padding-left:' + (node.depth * 12) + 'px'">
                                                        <span class="ms365-tree-toggle-slot">
                                                            <button type="button"
                                                                    class="ms365-tree-toggle"
                                                                    x-show="node.hasChildren"
                                                                    @click="toggleExpandNode(section.key, node)"
                                                                    x-text="isNodeExpanded(node) ? '▼' : '▶'"></button>
                                                        </span>
                                                        <input type="checkbox"
                                                               class="eb-check-input shrink-0"
                                                               :disabled="node.selectable === false"
                                                               :title="node.disabledReason || ''"
                                                               :checked="nodeCheckState(section.key, node) === 'checked'"
                                                               x-init="$el.indeterminate = nodeCheckState(section.key, node) === 'indeterminate'"
                                                               @change="toggleTreeNode(section.key, node); $el.indeterminate = nodeCheckState(section.key, node) === 'indeterminate'; $el.checked = nodeCheckState(section.key, node) === 'checked'">
                                                        <button type="button"
                                                                class="ms365-tree-label"
                                                                :class="{ 'ms365-tree-label--disabled': node.selectable === false }"
                                                                @click="node.hasChildren ? toggleExpandNode(section.key, node) : toggleTreeNode(section.key, node)">
                                                            <span class="ms365-tree-label-primary" x-text="node.label"></span>
                                                            <span class="ms365-tree-label-secondary" x-show="node.subtitle" x-text="node.subtitle"></span>
                                                            <span class="ms365-tree-label-secondary ms365-tree-label-reason"
                                                                  x-show="node.selectable === false && node.disabledReason"
                                                                  x-text="node.disabledReason"></span>
                                                        </button>
                                                        <button type="button"
                                                                class="ms365-tree-info-btn"
                                                                x-show="isDirectoryBaselineNode(node)"
                                                                @click.stop="openDirectoryBaselineInfo()"
                                                                @mouseenter.stop="openDirectoryBaselineInfo()"
                                                                aria-label="What is Directory baseline?"
                                                                title="What is Directory baseline?">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <p x-show="!inventory.resources || inventory.resources.length === 0" class="eb-type-caption p-4 text-center">No inventory loaded. Click Refresh inventory.</p>
                                    <p x-show="inventoryFilterActive() && inventory.resources && inventory.resources.length > 0 && inventoryVisibleNodeCount() === 0"
                                       class="eb-type-caption p-4 text-center text-[var(--eb-text-muted)]">No resources match your search.</p>
                                </div>
                            </div>
                            <div class="ms365-selection-pane border border-[var(--eb-border-default)] rounded-lg overflow-hidden flex flex-col">
                                <div class="eb-menu-label px-3 py-2 border-b border-[var(--eb-border-default)]">
                                    Selected for backup (<span x-text="selectionSummaryRowCount()"></span>)
                                </div>
                                <div class="flex-1 overflow-y-auto p-2 space-y-3">
                                    <template x-if="selectionCount() === 0">
                                        <p class="eb-type-caption text-center py-8">Select resources on the left to include them in this backup job.</p>
                                    </template>
                                    <template x-for="group in selectionSummaryGroups" :key="'sum-' + group.section">
                                        <div>
                                            <div class="text-xs font-semibold text-[var(--eb-text-muted)] mb-1 px-1" x-text="group.section"></div>
                                            <template x-for="(item, idx) in group.items" :key="group.section + '-' + idx">
                                                <div class="ms365-job-wizard__summary-item py-1.5 px-2 text-sm rounded bg-[var(--eb-surface-muted)]">
                                                    <div class="min-w-0">
                                                        <div class="truncate font-medium" x-text="item.label"></div>
                                                        <div class="truncate text-xs text-[var(--eb-text-muted)]" x-show="item.subtitle" x-text="item.subtitle"></div>
                                                        <div class="ms365-job-wizard__summary-badges" x-show="item.badges && item.badges.length > 0">
                                                            <template x-for="(badge, bidx) in item.badges" :key="group.section + '-' + idx + '-b-' + bidx">
                                                                <span class="eb-badge eb-badge--neutral text-xs" x-text="badge"></span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <div x-show="planWarnings.length > 0" class="eb-alert eb-alert--warning mt-2">
                                        <div class="eb-alert-title">Duplicate coverage</div>
                                        <ul class="eb-type-caption list-disc pl-4 mt-1 space-y-1">
                                            <template x-for="(warn, widx) in planWarnings" :key="'warn-' + widx">
                                                <li x-text="warn"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ms365-wizard-billing-dock shrink-0">
                            <div class="ms365-wizard-billing-dock__panel">
                            <template x-if="!billingPreview || selectionCount() === 0">
                                <div class="ms365-wizard-billing-dock__placeholder">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Billing estimate</div>
                                    <p class="eb-type-caption text-[var(--eb-text-muted)] mt-1 mb-0">Select resources to see your billing estimate.</p>
                                </div>
                            </template>
                            <template x-if="billingPreview && selectionCount() > 0">
                                <div class="ms365-wizard-billing-dock__content space-y-2">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">
                                            Billing estimate
                                            <span x-show="billingPreview.breakdown && billingPreview.breakdown.length > 0"
                                                  x-text="' · ' + billingPreview.breakdown.length + (billingPreview.breakdown.length === 1 ? ' group' : ' groups')"></span>
                                        </div>
                                        <template x-if="billingPreview.trial_status === 'trialing'">
                                            <span class="eb-badge eb-badge--info text-xs">Trial — $0 until conversion</span>
                                        </template>
                                        <template x-if="billingPreview.inventory_stale || billingPreview.member_resolution_pending">
                                            <span class="eb-badge eb-badge--warning text-xs"
                                                  :title="billingPreview.member_resolution_pending ? 'Team or group member lists could not be loaded. Refresh inventory to update Protected User counts and pricing.' : 'Inventory may be outdated. Refresh inventory for current figures.'"
                                                  x-text="billingPreview.member_resolution_pending ? 'Member counts incomplete' : 'Inventory may be stale'"></span>
                                        </template>
                                    </div>
                                    <div class="ms365-wizard-billing-dock__metrics">
                                        <div class="ms365-wizard-billing-dock__metric">
                                            <span class="ms365-wizard-billing-dock__metric-label">Protected Users</span>
                                            <span class="ms365-wizard-billing-dock__metric-value" x-text="billingPreview.protected_users ?? 0"></span>
                                        </div>
                                        <div class="ms365-wizard-billing-dock__metric">
                                            <span class="ms365-wizard-billing-dock__metric-label">Est. monthly</span>
                                            <span class="ms365-wizard-billing-dock__metric-value">
                                                $<span x-text="Number(billingPreview.pricing?.estimated_monthly_cad || 0).toFixed(2)"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="eb-type-caption text-[var(--eb-text-muted)] mb-0">
                                        Protected Users @ $<span x-text="Number(billingPreview.pricing?.protected_user_price_cad || 0).toFixed(2)"></span>/user
                                        <template x-if="(billingPreview.onedrive_overage_gib || 0) > 0">
                                            <span> · OneDrive overage included</span>
                                        </template>
                                    </p>
                                    <template x-if="billingPreview.breakdown && billingPreview.breakdown.length > 0">
                                        <ul class="ms365-wizard-billing-dock__breakdown eb-type-caption text-[var(--eb-text-muted)] mb-0">
                                            <template x-for="(row, bidx) in billingPreview.breakdown" :key="'dock-bill-' + bidx">
                                                <li x-text="row.label + ' — ' + row.member_count + ' members'"></li>
                                            </template>
                                        </ul>
                                    </template>
                                    <p class="eb-type-caption text-[var(--eb-text-muted)] mb-0" x-show="editMode">
                                        Counts reflect this job&apos;s selection. Your account total may include other active MS365 jobs.
                                    </p>
                                </div>
                            </template>
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

                    <!-- Step 4: Retention -->
                    <div x-show="step === 4" class="space-y-3">
                        <p class="eb-card-subtitle">Choose a retention policy (billing integration coming soon).</p>
                        <template x-for="opt in retentionOptions" :key="opt.id">
                            <button type="button"
                                    class="ms365-option-card w-full text-left"
                                    :class="{
                                        'is-selected': retentionTier === opt.id,
                                        'is-disabled': !isRetentionTierEnabled(opt.id)
                                    }"
                                    :disabled="!isRetentionTierEnabled(opt.id)"
                                    :aria-disabled="!isRetentionTierEnabled(opt.id)"
                                    @click="selectRetentionTier(opt.id)">
                                <span class="block font-medium text-[var(--eb-text-primary)]" x-text="opt.title"></span>
                                <span class="block text-sm text-[var(--eb-text-muted)] mt-1" x-text="opt.description"></span>
                            </button>
                        </template>
                    </div>

                    <!-- Step 5: Job name -->
                    <div x-show="step === 5" class="space-y-3">
                        <p class="eb-card-subtitle">Give this backup job a name you will recognize in your job list and reports.</p>
                        <div>
                            <label class="eb-field-label" for="ms365-job-name">Job name</label>
                            <input id="ms365-job-name" type="text" class="eb-input w-full mt-1" x-model="jobName" :placeholder="defaultJobName()">
                        </div>
                    </div>
                </div>
            </div>

            <div class="eb-modal-footer shrink-0 flex items-center justify-between gap-2 px-6 py-4 border-t border-[var(--eb-border-default)]">
                <button type="button" class="eb-btn eb-btn-secondary" @click="step > 1 ? goToStep(step - 1) : close()" x-text="step > 1 ? 'Back' : 'Cancel'"></button>
                <div class="flex gap-2">
                    <button type="button" class="eb-btn eb-btn-primary" x-show="step < 5" @click="nextStep()" :disabled="!canProceed()">
                        Next
                    </button>
                    <button type="button" class="eb-btn eb-btn-success" x-show="step === 5" @click="save()" :disabled="saving">
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

    <div x-show="directoryInfoModal.open"
         x-cloak
         class="absolute inset-0 z-30 flex items-center justify-center p-4"
         @mouseleave="closeDirectoryBaselineInfo()">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeDirectoryBaselineInfo()"></div>
        <div class="eb-modal relative z-10 max-w-lg w-full"
             @mouseenter="openDirectoryBaselineInfo()"
             @click.stop>
            <div class="eb-modal-header">
                <h3 class="eb-modal-title">Directory baseline</h3>
                <button type="button" class="eb-modal-close" @click="closeDirectoryBaselineInfo()" aria-label="Close">&times;</button>
            </div>
            <div class="eb-modal-body space-y-4">
                <div>
                    <p class="text-sm font-medium text-[var(--eb-text-primary)]">What is this?</p>
                    <p class="text-sm text-[var(--eb-text-muted)] mt-1">
                        A tenant-wide export of your Microsoft 365 directory listing — user and group names, emails, and account metadata from Azure AD. It does not back up mailboxes, files, or other workload content.
                    </p>
                </div>
                <p class="text-sm text-[var(--eb-text-muted)]">
                    Include it if you want a historical record of tenant membership and group inventory alongside your workload backups (common in “Full tenant” presets).
                </p>
                <div class="overflow-x-auto">
                    <table class="ms365-directory-info-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left font-semibold text-[var(--eb-text-primary)]">Use case</th>
                                <th class="text-left font-semibold text-[var(--eb-text-primary)]">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in directoryBaselineUseCases" :key="'dir-info-' + idx">
                                <tr>
                                    <td class="align-top font-medium text-[var(--eb-text-primary)]" x-text="row.useCase"></td>
                                    <td class="align-top text-[var(--eb-text-muted)]" x-text="row.value"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-[var(--eb-text-muted)]">
                    This export is for reference and audit only — it cannot be restored back into Microsoft 365 like mail or files.
                </p>
            </div>
            <div class="eb-modal-footer flex justify-end">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeDirectoryBaselineInfo()">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="modules/addons/cloudstorage/assets/js/ms365_job_selection.js?v=4"></script>
<script src="modules/addons/cloudstorage/assets/js/ms365_job_wizard.js?v=19"></script>
