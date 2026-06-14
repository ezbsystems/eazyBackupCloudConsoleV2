{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--info">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 6h16M4 12h16M4 18h7"/>
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
<div class="space-y-6" x-data="ms365Page()" x-init="init()">
    <div class="eb-alert eb-alert--info">
        <div class="eb-alert-title">Manage jobs from Users</div>
        <p class="eb-type-caption !mt-1">Create and edit Microsoft 365 backup jobs from <strong>Users → [user] → Jobs</strong> using the Create Job menu.</p>
    </div>
    {if $smarty.get.connect_ok}
    <div class="eb-alert eb-alert--success">Microsoft 365 tenant connected. Refresh inventory below, then run your first backup.</div>
    {/if}
    {if $smarty.get.connect_error}
    <div class="eb-alert eb-alert--danger">{$smarty.get.connect_error|escape}</div>
    {/if}

    <section class="eb-card-raised p-6" x-show="isM365Connected() || status.needs_reconnect">
        <h2 class="eb-card-title mb-2">Setup progress</h2>
        <ol class="space-y-3">
            <template x-for="(step, key) in onboardingSteps" :key="key">
                <li class="flex items-center gap-3 text-sm">
                    <span class="eb-badge" :class="step.complete ? 'eb-badge--success' : 'eb-badge--muted'" x-text="step.complete ? 'Done' : 'Pending'"></span>
                    <span x-text="step.label"></span>
                </li>
            </template>
        </ol>
        <p class="eb-card-subtitle mt-3" x-text="onboarding.completed_count + ' of ' + onboarding.total_count + ' steps complete'"></p>
    </section>

    <section class="eb-card-raised p-6">
        <h2 class="eb-card-title mb-2">Connection</h2>
        <p class="eb-card-subtitle mb-4">Connect your Microsoft 365 organization. Sign in with a Global Administrator account that belongs to the tenant you want to back up and approve access for your organization.</p>
        <template x-if="isM365Connected()">
            <div class="eb-alert eb-alert--success mb-4">
                <div class="eb-alert-title">Connected</div>
                <p class="eb-type-caption !mt-1" x-show="status.azure_tenant_id"><strong>Tenant ID:</strong> <span x-text="status.azure_tenant_id"></span></p>
                <p class="eb-type-caption !mt-1" x-show="status.bucket_name"><strong>Storage bucket:</strong> <span x-text="status.bucket_name"></span></p>
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
        <template x-if="status.needs_reconnect">
            <div class="eb-alert eb-alert--warning mb-4">
                <div class="eb-alert-title">Reconnection required</div>
                <p class="eb-type-caption !mt-1" x-text="status.health_error || 'Microsoft 365 access was removed or expired. Reconnect your organization to continue.'"></p>
                <p class="eb-type-caption !mt-1" x-show="status.azure_tenant_id">Previous tenant: <span x-text="status.azure_tenant_id"></span></p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="connect()" :disabled="connecting || disconnecting">
                        <span x-text="connecting ? 'Redirecting…' : 'Reconnect Microsoft 365'"></span>
                    </button>
                    <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm !text-[var(--eb-text-danger)]" @click="confirmDisconnect()" :disabled="disconnecting || connecting">
                        Disconnect
                    </button>
                </div>
            </div>
        </template>
        <template x-if="!status.connected && !status.needs_reconnect">
            <button type="button" class="eb-btn eb-btn-primary" @click="connect()" :disabled="connecting">
                <span x-text="connecting ? 'Redirecting…' : 'Connect Microsoft 365'"></span>
            </button>
        </template>
    </section>

    <section class="eb-card-raised p-6" x-show="isM365Connected()">
        <h2 class="eb-card-title mb-2">Tenant inventory</h2>
        <p class="eb-card-subtitle mb-4">Discover users, sites, and teams from Microsoft Graph before scheduling backups.</p>
        <template x-if="!inventory.has_inventory">
            <div class="eb-alert eb-alert--warning mb-4">No inventory yet. Refresh to load resources from your tenant.</div>
        </template>
        <template x-if="inventory.has_inventory">
            <div class="text-sm space-y-1 mb-4">
                <p><strong>Last refreshed:</strong> <span x-text="formatInventoryTime(inventory.fetched_at)"></span></p>
                <p><strong>Resources:</strong> <span x-text="inventory.total_resources"></span></p>
                <ul class="list-disc pl-5 text-[var(--eb-text-muted)]" x-show="inventoryCountEntries.length">
                    <template x-for="entry in inventoryCountEntries" :key="entry.type">
                        <li><span x-text="entry.type"></span>: <span x-text="entry.count"></span></li>
                    </template>
                </ul>
            </div>
        </template>
        <button type="button" class="eb-btn eb-btn-secondary" @click="refreshInventory()" :disabled="refreshingInventory">
            <span x-text="refreshingInventory ? 'Refreshing…' : 'Refresh inventory'"></span>
        </button>
    </section>

    <section class="eb-card-raised p-6" x-show="isM365Connected()">
        <h2 class="eb-card-title mb-2">Backup</h2>
        <p class="eb-card-subtitle mb-4">Choose a scope preset and start a backup to your dedicated e3 storage bucket.</p>
        <div class="space-y-3 mb-4">
            <template x-for="p in presets" :key="p.id">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="ms365_preset" class="mt-1" :value="p.id" x-model="selectedPreset">
                    <span>
                        <span class="font-medium text-sm" x-text="p.label"></span>
                        <span class="block text-sm text-[var(--eb-text-muted)]" x-text="p.description"></span>
                    </span>
                </label>
            </template>
        </div>
        <button type="button" class="eb-btn eb-btn-primary" @click="startBackup()"
            :disabled="backingUp || !onboarding.can_start_backup"
            :title="!onboarding.can_start_backup ? 'Refresh inventory first' : ''">
            <span x-text="backingUp ? 'Starting…' : 'Start backup'"></span>
        </button>
    </section>

    <section class="eb-card-raised p-6" x-show="isM365Connected()">
        <h2 class="eb-card-title mb-2">Run history</h2>
        <div class="overflow-x-auto">
            <table class="eb-table w-full text-sm">
                <thead>
                    <tr>
                        <th>User / resource</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="run in runs" :key="run.id">
                        <tr class="cursor-pointer hover:bg-[var(--eb-surface-muted)]" @click="toggleRun(run.id)">
                            <td x-text="run.user_display_name || run.graph_id || run.physical_key || '—'"></td>
                            <td><span class="eb-badge" x-text="run.status"></span></td>
                            <td x-text="formatTs(run.created_at)"></td>
                            <td @click.stop>
                                <button type="button" class="eb-btn eb-btn-ghost eb-btn-xs" x-show="run.status === 'error'" @click="retry(run.id)">Retry</button>
                            </td>
                        </tr>
                        <tr x-show="expandedRunId === run.id" x-cloak>
                            <td colspan="4" class="!p-0">
                                <div class="p-4 border-t border-[var(--eb-border)] bg-[var(--eb-surface-muted)]">
                                    <template x-if="runDetail && runDetail.id === run.id">
                                        <div class="space-y-3">
                                            <div class="flex flex-wrap gap-4 text-sm">
                                                <span><strong>Phase:</strong> <span x-text="runDetail.phase || '—'"></span></span>
                                                <span><strong>Progress:</strong> <span x-text="runDetail.percent + '%'"></span></span>
                                                <span x-show="runDetail.physical_key"><strong>Job:</strong> <span class="font-mono text-xs" x-text="runDetail.physical_key"></span></span>
                                            </div>
                                            <div class="eb-live-bar" aria-hidden="true" x-show="['queued','running'].includes(runDetail.status)">
                                                <div class="eb-live-bar-fill running" :style="'width:' + Math.min(100, runDetail.percent) + '%'"></div>
                                            </div>
                                            <p class="text-sm text-[var(--eb-text-danger)]" x-show="runDetail.error_message" x-text="runDetail.error_message"></p>
                                            <div class="eb-live-log">
                                                <div class="eb-live-log-toolbar">
                                                    <div class="eb-live-log-title">Run logs</div>
                                                </div>
                                                <div class="eb-live-log-output max-h-48 overflow-y-auto font-mono text-xs" id="ms365RunLogs">
                                                    <template x-for="line in runLogLines" :key="line.id">
                                                        <div class="eb-log-line px-3 py-1 border-b border-[var(--eb-border)]">
                                                            <span class="text-[var(--eb-text-muted)]" x-text="line.level"></span>
                                                            <span x-text="line.message"></span>
                                                        </div>
                                                    </template>
                                                    <div x-show="!runLogLines.length" class="px-3 py-2 text-[var(--eb-text-muted)] italic">No log lines yet…</div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="loadingRunDetail" class="text-sm text-[var(--eb-text-muted)]">Loading run details…</div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    <section class="eb-card-raised p-6" x-show="status.connected">
        <h2 class="eb-card-title mb-2">Restore (mail)</h2>
        <p class="eb-card-subtitle mb-4">Restore mailbox from the latest backup for a user (non-destructive import).</p>
        <div class="flex flex-wrap gap-2 items-end">
            <div>
                <label class="eb-label">Target user Graph ID</label>
                <input type="text" class="eb-input" x-model="restoreUserId" placeholder="user guid">
            </div>
            <button type="button" class="eb-btn eb-btn-secondary" @click="startRestore()" :disabled="restoring">Restore mailbox</button>
        </div>
    </section>

    <div x-show="confirmModal.open"
         x-cloak
         class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="closeConfirmModal()"
         role="dialog"
         aria-modal="true">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeConfirmModal()" aria-hidden="true"></div>
        <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden max-w-md w-full" @click.stop>
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

    <div x-show="errorModal.open"
         x-cloak
         class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="closeErrorModal()"
         role="dialog"
         aria-modal="true"
         aria-labelledby="ms365ErrorModalTitle">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeErrorModal()" aria-hidden="true"></div>
        <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden"
             @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <div class="eb-modal-header">
                <div>
                    <h3 class="eb-modal-title" id="ms365ErrorModalTitle" x-text="errorModal.title"></h3>
                    <p class="eb-modal-subtitle" x-show="errorModal.subtitle" x-text="errorModal.subtitle"></p>
                </div>
                <button type="button" class="eb-modal-close" @click="closeErrorModal()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="eb-modal-body">
                <div class="eb-alert" :class="errorModal.variant === 'warning' ? 'eb-alert--warning' : 'eb-alert--danger'">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div x-text="errorModal.message"></div>
                </div>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="closeErrorModal()">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
const MS365_DISCONNECT_CONFIRM = {
    title: 'Disconnect Microsoft 365?',
    message: 'Stops scheduled backups and removes the connection from eazyBackup. Backup data in storage is kept. To remove access in Microsoft, an administrator must revoke the app in Entra ID.',
    confirmLabel: 'Disconnect',
};
const MS365_SWITCH_ORG_CONFIRM = {
    title: 'Connect a different organization?',
    message: 'This replaces the current Microsoft 365 connection for this user. Active backup jobs will be paused and you will need to refresh inventory and review job selections.',
    confirmLabel: 'Continue',
};

function ms365Page() {
    return {
        status: {$ms365Status|@json_encode nofilter},
        presets: {$ms365Presets|@json_encode nofilter},
        selectedPreset: 'user_mail_calendar',
        inventory: { has_inventory: false, fetched_at: '', counts: {}, total_resources: 0 },
        onboarding: { steps: {}, completed_count: 0, total_count: 3, can_start_backup: false },
        runs: [],
        connecting: false,
        backingUp: false,
        restoring: false,
        refreshingInventory: false,
        disconnecting: false,
        confirmModal: { open: false, title: '', message: '', confirmLabel: '', action: '' },
        restoreUserId: '',
        expandedRunId: null,
        runDetail: null,
        runLogLines: [],
        runLogSinceId: 0,
        runLogPollTimer: null,
        loadingRunDetail: false,
        errorModal: { open: false, title: 'Something went wrong', subtitle: '', message: '', variant: 'danger' },
        showError(message, title) {
            this.errorModal = {
                open: true,
                title: title || 'Something went wrong',
                subtitle: '',
                message: String(message || 'An unexpected error occurred.'),
                variant: 'danger'
            };
        },
        showNotice(message, title) {
            this.errorModal = {
                open: true,
                title: title || 'Notice',
                subtitle: '',
                message: String(message || ''),
                variant: 'warning'
            };
        },
        closeErrorModal() {
            this.errorModal.open = false;
        },
        confirmDisconnect() {
            this.confirmModal = {
                open: true,
                title: MS365_DISCONNECT_CONFIRM.title,
                message: MS365_DISCONNECT_CONFIRM.message,
                confirmLabel: MS365_DISCONNECT_CONFIRM.confirmLabel,
                action: 'disconnect',
            };
        },
        confirmSwitchOrganization() {
            this.confirmModal = {
                open: true,
                title: MS365_SWITCH_ORG_CONFIRM.title,
                message: MS365_SWITCH_ORG_CONFIRM.message,
                confirmLabel: MS365_SWITCH_ORG_CONFIRM.confirmLabel,
                action: 'switch',
            };
        },
        closeConfirmModal() {
            if (this.disconnecting) {
                return;
            }
            this.confirmModal.open = false;
        },
        async executeConfirmModal() {
            if (this.confirmModal.action === 'switch') {
                await this.executeSwitchOrganization();
            } else {
                await this.executeDisconnect();
            }
        },
        async executeDisconnect() {
            this.disconnecting = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/ms365_disconnect.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.status === 'success') {
                    if (data.ms365) {
                        this.status = data.ms365;
                        if (data.ms365.inventory) this.inventory = data.ms365.inventory;
                        if (data.ms365.onboarding) this.onboarding = data.ms365.onboarding;
                    } else {
                        await this.refreshStatus();
                    }
                    this.inventory = { has_inventory: false, fetched_at: '', counts: {}, total_resources: 0 };
                    this.confirmModal.open = false;
                    this.notifySuccess('Microsoft 365 disconnected.');
                    return true;
                }
                this.showError(data.message || 'Disconnect failed', 'Disconnect failed');
                return false;
            } catch (e) {
                this.showError('Disconnect failed.', 'Disconnect failed');
                return false;
            } finally {
                this.disconnecting = false;
            }
        },
        async executeSwitchOrganization() {
            const ok = await this.executeDisconnect();
            if (ok) {
                this.confirmModal.open = false;
                await this.connect();
            }
        },
        isM365Connected() {
            return !!this.status.connected && !this.status.needs_reconnect;
        },
        notifySuccess(message) {
            const msg = String(message || '');
            try {
                if (typeof e3backupNotify === 'function') {
                    e3backupNotify('success', msg);
                    return;
                }
            } catch (e) { /* fall through */ }
            const wrapId = 'ms365-inline-toasts';
            let wrap = document.getElementById(wrapId);
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.id = wrapId;
                wrap.className = 'fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none';
                document.body.appendChild(wrap);
            }
            const el = document.createElement('div');
            el.className = 'eb-toast eb-toast--success pointer-events-auto';
            el.textContent = msg || 'Success';
            wrap.appendChild(el);
            setTimeout(() => {
                el.classList.add('opacity-0');
                el.style.transition = 'opacity 250ms ease';
                setTimeout(() => el.remove(), 260);
            }, 2600);
        },
        get onboardingSteps() {
            return this.onboarding.steps || {};
        },
        get inventoryCountEntries() {
            const c = this.inventory.counts || {};
            return Object.keys(c).map(type => ({ type, count: c[type] }));
        },
        init() {
            if (this.status.inventory) {
                this.inventory = this.status.inventory;
            }
            if (this.status.onboarding) {
                this.onboarding = this.status.onboarding;
            }
            if (this.isM365Connected()) {
                this.loadRuns();
            }
            const params = new URLSearchParams(window.location.search);
            if (params.get('connect_ok') && !this.inventory.has_inventory) {
                setTimeout(() => this.refreshInventory(), 500);
            }
        },
        async connect() {
            this.connecting = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/ms365_connect_start.php', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.status === 'success' && data.consent_url) {
                    window.location.href = data.consent_url;
                    return;
                }
                this.showError(data.message || 'Failed to start connect', 'Connect failed');
            } finally {
                this.connecting = false;
            }
        },
        async refreshStatus() {
            const res = await fetch('modules/addons/cloudstorage/api/ms365_status.php', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success' && data.ms365) {
                this.status = data.ms365;
                if (data.ms365.inventory) this.inventory = data.ms365.inventory;
                if (data.ms365.onboarding) this.onboarding = data.ms365.onboarding;
            }
        },
        async refreshInventory() {
            this.refreshingInventory = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/ms365_inventory_refresh.php', {
                    method: 'POST',
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.inventory = {
                        has_inventory: true,
                        fetched_at: data.inventory.fetched_at || '',
                        counts: data.inventory.counts || {},
                        total_resources: data.inventory.total_resources || 0,
                        warnings: data.inventory.warnings || data.warnings || []
                    };
                    const warnings = this.inventory.warnings || [];
                    if (warnings.length) {
                        this.showNotice(warnings.join(' '), 'Inventory refreshed with limitations');
                    }
                    await this.refreshStatus();
                } else if (data.reconnect_required) {
                    await this.refreshStatus();
                    this.showNotice(data.message || 'Microsoft 365 must be reconnected.', 'Reconnection required');
                } else {
                    this.showError(data.message || 'Inventory refresh failed', 'Inventory refresh failed');
                }
            } finally {
                this.refreshingInventory = false;
            }
        },
        async loadRuns() {
            const res = await fetch('modules/addons/cloudstorage/api/ms365_runs_list.php?limit=25', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success') {
                this.runs = data.runs || [];
            }
        },
        async startBackup() {
            this.backingUp = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/ms365_start_backup.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'preset=' + encodeURIComponent(this.selectedPreset)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    await this.loadRuns();
                    await this.refreshStatus();
                    this.notifySuccess('Backup queued (' + (data.count || 0) + ' job(s)).');
                } else if (data.reconnect_required) {
                    await this.refreshStatus();
                    this.showNotice(data.message || 'Microsoft 365 must be reconnected.', 'Reconnection required');
                } else {
                    this.showError(data.message || 'Backup failed', 'Backup failed');
                }
            } finally {
                this.backingUp = false;
            }
        },
        async retry(runId) {
            const res = await fetch('modules/addons/cloudstorage/api/ms365_retry_run.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'run_id=' + encodeURIComponent(runId)
            });
            const data = await res.json();
            if (data.status === 'success') {
                await this.loadRuns();
            } else {
                this.showError(data.message || 'Retry failed', 'Retry failed');
            }
        },
        toggleRun(runId) {
            if (this.expandedRunId === runId) {
                this.stopLogPoll();
                this.expandedRunId = null;
                this.runDetail = null;
                this.runLogLines = [];
                return;
            }
            this.expandedRunId = runId;
            this.runDetail = null;
            this.runLogLines = [];
            this.runLogSinceId = 0;
            this.loadRunDetail(runId);
        },
        async loadRunDetail(runId) {
            this.loadingRunDetail = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/ms365_run_detail.php?run_id=' + encodeURIComponent(runId), { credentials: 'same-origin' });
                const data = await res.json();
                if (data.status === 'success') {
                    this.runDetail = data.run;
                    await this.pollRunLogs(runId);
                    if (['queued', 'running'].includes(data.run.status)) {
                        this.startLogPoll(runId);
                    }
                }
            } finally {
                this.loadingRunDetail = false;
            }
        },
        startLogPoll(runId) {
            this.stopLogPoll();
            this.runLogPollTimer = setInterval(() => {
                if (this.expandedRunId !== runId) {
                    this.stopLogPoll();
                    return;
                }
                this.pollRunLogs(runId);
                this.loadRunDetailQuiet(runId);
            }, 2500);
        },
        stopLogPoll() {
            if (this.runLogPollTimer) {
                clearInterval(this.runLogPollTimer);
                this.runLogPollTimer = null;
            }
        },
        async loadRunDetailQuiet(runId) {
            const res = await fetch('modules/addons/cloudstorage/api/ms365_run_detail.php?run_id=' + encodeURIComponent(runId), { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success' && this.expandedRunId === runId) {
                this.runDetail = data.run;
                if (!['queued', 'running'].includes(data.run.status)) {
                    this.stopLogPoll();
                    await this.loadRuns();
                }
            }
        },
        async pollRunLogs(runId) {
            const res = await fetch('modules/addons/cloudstorage/api/ms365_run_logs.php?run_id=' + encodeURIComponent(runId) + '&since_id=' + this.runLogSinceId, { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status === 'success' && this.expandedRunId === runId) {
                const lines = data.lines || [];
                if (lines.length) {
                    this.runLogLines = this.runLogLines.concat(lines);
                    this.runLogSinceId = data.last_id || this.runLogSinceId;
                }
            }
        },
        async startRestore() {
            if (!this.restoreUserId) {
                this.showError('Enter target user Graph ID', 'Restore');
                return;
            }
            this.restoring = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/ms365_restore_start.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'target_graph_id=' + encodeURIComponent(this.restoreUserId)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.notifySuccess('Restore completed: ' + data.restore_run_id);
                } else {
                    this.showError(data.message || 'Restore failed', 'Restore failed');
                }
            } finally {
                this.restoring = false;
            }
        },
        formatTs(ts) {
            if (!ts) return '—';
            const n = Number(ts);
            if (n > 1e12) return new Date(n).toLocaleString();
            return new Date(n * 1000).toLocaleString();
        },
        formatInventoryTime(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            return isNaN(d.getTime()) ? iso : d.toLocaleString();
        }
    };
}
</script>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='ms365'
    ebE3Title='Microsoft 365'
    ebE3Description='Connect your tenant, run backups to dedicated e3 storage, and restore mail.'
}
