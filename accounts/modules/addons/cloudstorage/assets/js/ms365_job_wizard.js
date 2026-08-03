(function () {
    'use strict';

    const INVENTORY_SECTIONS = () => (
        (window.ms365JobSelection && window.ms365JobSelection.SECTIONS) || []
    );

    const DEFAULT_RETENTION_TIER = '1y';

    const RETENTION_OPTIONS = [
        { id: '1y', title: 'Default — 1 year', description: 'All backups for the last 30 days plus 1 backup per week for 52 weeks.' },
        { id: '2y', title: '2 years', description: 'All backups for the last 30 days plus 1 backup per week for 2 years.' },
        { id: '3y', title: '3 years', description: 'All backups for the last 30 days plus 1 backup per week for 3 years.' },
        { id: '4y', title: '4 years', description: 'All backups for the last 30 days plus 1 backup per week for 4 years.' },
        { id: '5y', title: '5 years', description: 'All backups for the last 30 days plus 1 backup per week for 5 years.' },
        { id: '6y', title: '6 years', description: 'All backups for the last 30 days plus 1 backup per week for 6 years.' },
        { id: '7y', title: '7 years', description: 'All backups for the last 30 days plus 1 backup per week for 7 years.' },
    ];

    const WIZARD_CTX_KEY = 'ms365_wizard_ctx';
    const CONSENT_RESULT_KEY = 'ms365_connect_result';
    const CONSENT_BROADCAST_CHANNEL = 'ms365_connect_result';
    const CONSENT_POLL_MS = 2500;
    const CONSENT_TIMEOUT_MS = 15 * 60 * 1000;
    const INVENTORY_PROGRESS_POLL_MS = 2000;
    const INVENTORY_WORKER_START_TIMEOUT_MS = 15000;
    const INVENTORY_CACHE_TTL_MS = 6 * 60 * 60 * 1000;
    const PLAN_DEBOUNCE_MS = 400;
    const INVENTORY_ROW_HEIGHT = 40;
    const INVENTORY_VIRTUAL_OVERSCAN = 10;

    /** Large inventory/trees live outside Alpine reactivity to avoid deep-proxy cost. */
    const inventoryStash = {
        inventory: { resources: [] },
        treesBySection: {},
        sectionIndexes: {},
        memo: {
            inaccessibleSiteCount: null,
            globalCheckState: null,
        },
    };

    function clearInventoryStash() {
        inventoryStash.inventory = { resources: [] };
        inventoryStash.treesBySection = {};
        inventoryStash.sectionIndexes = {};
        inventoryStash.memo.inaccessibleSiteCount = null;
        inventoryStash.memo.globalCheckState = null;
    }

    function rebuildInventoryStash(inventory) {
        const sel = window.ms365JobSelection;
        inventoryStash.inventory = inventory || { resources: [] };
        if (!sel) {
            inventoryStash.treesBySection = {};
            inventoryStash.sectionIndexes = {};
        } else {
            inventoryStash.treesBySection = sel.buildAllTrees(inventoryStash.inventory);
            inventoryStash.sectionIndexes = sel.buildAllSectionIndexes(inventoryStash.treesBySection);
        }
        inventoryStash.memo.inaccessibleSiteCount = null;
        inventoryStash.memo.globalCheckState = null;
    }

    const INVENTORY_PROGRESS_LABELS = {
        users: 'Users',
        sites: 'SharePoint sites',
        teams: 'Teams',
        groups: 'Groups',
        onedrive: 'OneDrive',
        site_access: 'Site access',
        group_members: 'Group members',
        site_members: 'Site members',
    };
    const INVENTORY_PHASE_ONLY_KEYS = new Set(['onedrive', 'site_access', 'group_members', 'site_members']);
    const MANUAL_REGIONS = ['GlobalPublicCloud', 'USGovernment', 'China', 'Germany'];
    const AUTH_MODE_CUSTOMER = 'customer_app';
    const CONSENT_POPUP_FEATURES = 'width=520,height=720,menubar=no,toolbar=no,location=yes,status=no';

    const DISCONNECT_CONFIRM = {
        title: 'Disconnect Microsoft 365?',
        message: 'Stops scheduled backups and removes the connection from eazyBackup. Backup data in storage is kept. To remove access in Microsoft, an administrator must revoke the app in Entra ID.',
        confirmLabel: 'Disconnect',
    };

    const SWITCH_ORG_CONFIRM = {
        title: 'Connect a different organization?',
        message: 'This replaces the current Microsoft 365 connection for this user. Active backup jobs will be paused and you will need to refresh inventory and review job selections.',
        confirmLabel: 'Continue',
    };

    let activeConsentHandler = null;
    /** Non-reactive: storing a Window in Alpine state breaks when the popup is cross-origin. */
    let activeConsentPopup = null;

    function setConsentPopup(popup) {
        activeConsentPopup = popup || null;
    }

    function clearConsentPopup() {
        activeConsentPopup = null;
    }

    function isConsentPopupClosed() {
        if (!activeConsentPopup) {
            return true;
        }
        try {
            return activeConsentPopup.closed;
        } catch (e) {
            return true;
        }
    }

    function closeConsentPopup() {
        if (!activeConsentPopup) {
            return;
        }
        try {
            if (!activeConsentPopup.closed) {
                activeConsentPopup.close();
            }
        } catch (e) {
            /* ignore cross-origin */
        }
        activeConsentPopup = null;
    }

    window.ms365WizardState = {
        backupUserId: '',
        backupUsername: '',
        editMode: false,
        jobId: '',
    };

    const DEFAULT_JOB_NAME_SUFFIX = 'Microsoft 365 Backup';

    function buildDefaultJobName(username) {
        const clean = String(username || '').trim();
        if (clean !== '') {
            return `${clean} - ${DEFAULT_JOB_NAME_SUFFIX}`;
        }
        return DEFAULT_JOB_NAME_SUFFIX;
    }

    function apiBase() {
        return 'modules/addons/cloudstorage/api/';
    }

    function toast(type, msg) {
        if (window.toast && typeof window.toast[type] === 'function') {
            window.toast[type](msg);
        } else if (typeof window.e3backupNotify === 'function') {
            window.e3backupNotify(type, msg);
        }
    }

    function saveWizardCtx(backupUserId) {
        try {
            sessionStorage.setItem(WIZARD_CTX_KEY, JSON.stringify({
                backupUserId,
                openedAt: Date.now(),
            }));
        } catch (e) {
            /* ignore */
        }
    }

    function readWizardCtx() {
        try {
            const raw = sessionStorage.getItem(WIZARD_CTX_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function clearWizardCtx() {
        try {
            sessionStorage.removeItem(WIZARD_CTX_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function cleanWizardUrlParams() {
        try {
            const url = new URL(window.location.href);
            url.searchParams.delete('ms365_wizard');
            url.searchParams.delete('connect_ok');
            url.searchParams.delete('connect_error');
            const next = url.pathname + url.search + url.hash;
            history.replaceState({}, '', next);
        } catch (e) {
            /* ignore */
        }
    }

    function returnPathForUser(backupUserId) {
        if (backupUserId) {
            return `index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=${encodeURIComponent(backupUserId)}&ms365_wizard=1#jobs`;
        }
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || 'e3backup';
        const view = params.get('view') || 'user_detail';
        return `index.php?m=cloudstorage&page=${encodeURIComponent(page)}&view=${encodeURIComponent(view)}&ms365_wizard=1`;
    }

    async function fetchConsentUrl(backupUserId, consentMode) {
        const body = new URLSearchParams({
            user_id: backupUserId,
            return_path: returnPathForUser(backupUserId),
            consent_mode: consentMode,
        });
        const res = await fetch(`${apiBase()}ms365_connect_start.php`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            if (!data || typeof data !== 'object') {
                return { status: 'fail', message: 'Invalid response from server.' };
            }
            if (!res.ok && !data.message) {
                data.message = `Server error (${res.status}).`;
            }
            return data;
        } catch (e) {
            console.error('ms365_connect_start: expected JSON, got HTTP', res.status, text.slice(0, 300));
            return {
                status: 'fail',
                message: res.status === 401
                    ? 'Your session expired. Refresh the page and sign in again.'
                    : `Could not start Microsoft sign-in (HTTP ${res.status}).`,
            };
        }
    }

    function dispatchConsentResult(data) {
        if (!data || data.type !== 'ms365_connect_result') {
            return;
        }
        if (typeof activeConsentHandler === 'function') {
            activeConsentHandler(data);
        }
    }

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) {
            return;
        }
        dispatchConsentResult(event.data);
    });

    if (typeof BroadcastChannel !== 'undefined') {
        try {
            const consentBroadcast = new BroadcastChannel(CONSENT_BROADCAST_CHANNEL);
            consentBroadcast.addEventListener('message', (event) => {
                dispatchConsentResult(event.data);
            });
        } catch (e) {
            /* ignore */
        }
    }

    window.addEventListener('storage', (event) => {
        if (event.key !== CONSENT_RESULT_KEY || !event.newValue) {
            return;
        }
        try {
            const wrapped = JSON.parse(event.newValue);
            if (wrapped && wrapped.payload) {
                dispatchConsentResult(wrapped.payload);
            }
        } catch (e) {
            /* ignore */
        }
    });

    const DIRECTORY_BASELINE_USE_CASES = [
        {
            useCase: 'Point-in-time tenant catalog',
            value: 'Know exactly which users and groups existed on a given backup date.',
        },
        {
            useCase: 'Audit / compliance',
            value: 'Lightweight export of directory state over time.',
        },
        {
            useCase: 'Incident / support',
            value: 'Compare who was in the tenant then vs now without parsing every mailbox backup.',
        },
        {
            useCase: 'Coverage reference',
            value: 'Includes all users and groups in one job, even ones you did not select for mail or OneDrive backup.',
        },
    ];

    window.ms365WizardApp = function ms365WizardApp() {
        return {
            step: 1,
            stepLabels: ['Connect', 'Inventory', 'Schedule', 'Retention', 'Job name'],
            loading: false,
            saving: false,
            connecting: false,
            awaitingConsent: false,
            consentError: '',
            consentPopupUrl: '',
            refreshingInventory: false,
            inventoryProgress: {
                phase: 'users',
                message: 'Discovering Microsoft 365 resources…',
                detail: '',
                counts: {},
                refresh_in_progress: false,
            },
            disconnecting: false,
            confirmModal: { open: false, title: '', message: '', confirmLabel: '', action: '' },
            directoryInfoModal: { open: false },
            directoryBaselineUseCases: DIRECTORY_BASELINE_USE_CASES,
            editMode: false,
            jobId: '',
            backupUserId: '',
            backupUsername: '',
            status: { connected: false, needs_reconnect: false },
            connectMode: 'automatic',
            manualRegions: MANUAL_REGIONS,
            manualForm: {
                region: 'GlobalPublicCloud',
                client_id: '',
                tenant_id: '',
                app_secret: '',
            },
            manualTesting: false,
            manualSaving: false,
            manualTestPassed: false,
            manualNotice: '',
            manualError: '',
            inventoryMeta: { resourceCount: 0, fetchedAt: null },
            inventoryViewRevision: 0,
            inventoryBackgroundRefreshing: false,
            inventoryRefreshStartedAtMs: null,
            inventoryElapsedTick: 0,
            selection: {},
            expandedKeys: {},
            scopeOverrides: {},
            planWarnings: [],
            planSummary: { runnable: 0, deferred: 0 },
            billingPreview: null,
            billingPreviewLoading: false,
            billingPreviewError: '',
            billingCalcOpen: false,
            selectionWorkloadCounts: {},
            savedSelectionIds: [],
            searchQuery: '',
            inventoryScrollTop: 0,
            inventoryVirtualSlice: { rows: [], paddingTop: 0, paddingBottom: 0, totalHeight: 0 },
            selectionVersion: 0,
            scheduleFrequency: 'once_daily',
            retentionTier: DEFAULT_RETENTION_TIER,
            retentionOptions: RETENTION_OPTIONS,
            defaultRetentionTier: DEFAULT_RETENTION_TIER,
            jobName: DEFAULT_JOB_NAME_SUFFIX,
            _consentPollTimer: null,
            _consentTimeoutTimer: null,
            _inventoryProgressTimer: null,
            _inventoryElapsedTimer: null,
            _planRequestSeq: 0,
            _planDebounceTimer: null,
            _planAbortController: null,

            init() {
                const params = new URLSearchParams(window.location.search);
                const ctx = readWizardCtx();
                const uid = window.ms365WizardState.backupUserId
                    || params.get('user_id')
                    || (ctx && ctx.backupUserId)
                    || '';
                if (params.get('ms365_wizard') === '1' && uid) {
                    this.backupUserId = uid;
                    this.open({
                        backupUserId: uid,
                        step: params.get('connect_ok') ? 2 : 1,
                    });
                    if (params.get('connect_error')) {
                        this.consentError = params.get('connect_error');
                        toast('error', this.consentError);
                    }
                    if (params.get('connect_ok')) {
                        clearWizardCtx();
                    }
                    cleanWizardUrlParams();
                }
            },

            async open(opts = {}) {
                const modal = document.getElementById('ms365JobWizardModal');
                if (!modal) return;

                this.stopConsentWait();
                this.consentError = '';
                this.manualError = '';
                this.manualNotice = '';
                this.manualTestPassed = false;

                this.backupUserId = opts.backupUserId || window.ms365WizardState.backupUserId || '';
                if (!this.backupUserId) {
                    toast('error', 'Select a backup user before creating a Microsoft 365 job.');
                    return;
                }

                window.ms365WizardState.backupUserId = this.backupUserId;
                this.backupUsername = opts.backupUsername
                    || window.ms365WizardState.backupUsername
                    || '';
                await this.resolveBackupUsername();

                this.editMode = !!opts.editMode;
                this.jobId = opts.jobId || '';
                this.step = opts.step || (this.editMode ? 2 : 1);
                this.selection = {};
                this.expandedKeys = {};
                this.scopeOverrides = {};
                this.planWarnings = [];
                this.planSummary = { runnable: 0, deferred: 0 };
                this.billingPreview = null;
                this.billingPreviewLoading = false;
                this.billingPreviewError = '';
                this.billingCalcOpen = false;
                this.selectionWorkloadCounts = {};
                this.savedSelectionIds = [];
                this.searchQuery = '';
                this.inventoryScrollTop = 0;
                this.inventoryVirtualSlice = { rows: [], paddingTop: 0, paddingBottom: 0, totalHeight: 0 };
                this.selectionVersion = 0;
                this.scheduleFrequency = 'once_daily';
                this.retentionTier = DEFAULT_RETENTION_TIER;
                this.jobName = this.defaultJobName();
                clearInventoryStash();
                this.inventoryMeta = { resourceCount: 0, fetchedAt: null };
                this.inventoryViewRevision = 0;
                this.inventoryBackgroundRefreshing = false;
                this.clearInventoryRefreshStart();

                modal.classList.remove('hidden');
                await this.loadStatus();
                if (this.editMode && this.jobId) {
                    await this.loadJob();
                }
                if (!this.isM365Connected() && this.step > 1) {
                    this.step = 1;
                }

                const autoAdvance = this.isM365Connected() && this.step === 1 && !opts.step;
                const needsInventory = this.isM365Connected() && (this.step >= 2 || autoAdvance);
                if (needsInventory) {
                    this.loading = true;
                    try {
                        const ok = await this.bootstrapInventoryForWizard({ autoAdvance });
                        if (ok && autoAdvance) {
                            this.step = 2;
                        } else if (!ok) {
                            this.step = 1;
                        }
                    } finally {
                        this.loading = false;
                    }
                }
            },

            inventoryFetchedAtMs() {
                const at = this.inventoryMeta && this.inventoryMeta.fetchedAt;
                if (!at) {
                    return 0;
                }
                const ms = Date.parse(at);
                return Number.isFinite(ms) ? ms : 0;
            },

            isInventoryCacheFresh() {
                const at = this.inventoryFetchedAtMs();
                return at > 0 && (Date.now() - at) < INVENTORY_CACHE_TTL_MS;
            },

            hasUsableInventory() {
                return (this.inventoryMeta.resourceCount || 0) > 0;
            },

            hasInventoryLoaded() {
                return (this.inventoryMeta.resourceCount || 0) > 0;
            },

            async bootstrapInventoryForWizard(opts = {}) {
                const { autoAdvance = false, forceRefresh = false } = opts;
                if (!forceRefresh) {
                    const loaded = await this.loadInventory({ silent: true });
                    if (loaded && this.hasUsableInventory() && this.isInventoryCacheFresh()) {
                        return true;
                    }
                    if (loaded && this.hasUsableInventory()) {
                        this.startBackgroundInventoryRefresh();
                        return true;
                    }
                }

                return this.ensureFreshInventory({
                    silent: true,
                    clearInventory: !this.hasUsableInventory(),
                });
            },

            async startBackgroundInventoryRefresh() {
                if (this.inventoryBackgroundRefreshing || this.refreshingInventory || !this.backupUserId) {
                    return;
                }
                this.inventoryBackgroundRefreshing = true;
                this.startInventoryProgressPoll();
                try {
                    const body = new URLSearchParams({ user_id: this.backupUserId });
                    const res = await fetch(`${apiBase()}ms365_inventory_refresh.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                    const data = await res.json();
                    const accepted = data.status === 'accepted' || data.refresh_in_progress;
                    if (!(data.status === 'success' || accepted)) {
                        return;
                    }
                    let reloadedListable = false;
                    const deadline = Date.now() + 3600000;
                    const workerStartDeadline = Date.now() + INVENTORY_WORKER_START_TIMEOUT_MS;
                    while (Date.now() < deadline) {
                        await this.pollInventoryProgress();
                        const phase = this.inventoryProgress.phase || '';
                        if ((phase === 'listable' || phase === 'complete') && !reloadedListable) {
                            await this.loadInventory({ silent: true });
                            reloadedListable = true;
                        }
                        if (phase === 'complete') {
                            await this.loadInventory({ silent: true });
                            return;
                        }
                        if (phase === 'error') {
                            return;
                        }
                        if (phase === 'idle' && Date.now() > workerStartDeadline) {
                            return;
                        }
                        await new Promise((resolve) => setTimeout(resolve, INVENTORY_PROGRESS_POLL_MS));
                    }
                } catch (e) {
                    /* keep cached inventory */
                } finally {
                    this.inventoryBackgroundRefreshing = false;
                    this.stopInventoryProgressPoll();
                    await this.pollInventoryProgress();
                }
            },

            isM365Connected() {
                return !!this.status.connected && !this.status.needs_reconnect;
            },

            isManualConnected() {
                return (this.status.connection_auth_mode || '') === AUTH_MODE_CUSTOMER;
            },

            isOAuthConnected() {
                const mode = this.status.connection_auth_mode || '';
                return mode === '' || mode === 'none' || mode === 'platform_consent';
            },

            showConnectModeToggle() {
                if (this.status.connected && this.isOAuthConnected()) {
                    return false;
                }
                return true;
            },

            setConnectMode(mode) {
                if (mode === 'manual' && this.status.connected && this.isOAuthConnected()) {
                    return;
                }
                this.connectMode = mode === 'manual' ? 'manual' : 'automatic';
                this.manualError = '';
                this.manualNotice = '';
                this.manualTestPassed = false;
            },

            clearManualTestPassed() {
                this.manualTestPassed = false;
                this.manualNotice = '';
            },

            isManualFormValid() {
                const clientId = (this.manualForm.client_id || '').trim();
                const tenantId = (this.manualForm.tenant_id || '').trim();
                const preview = this.status.credentials_preview || {};
                const hasSecret = !!(this.manualForm.app_secret || '').trim() || !!preview.has_secret;
                return clientId !== '' && tenantId !== '' && hasSecret;
            },

            canProceedManualConnect() {
                if (this.isM365Connected()) {
                    return true;
                }
                if (!this.isManualFormValid()) {
                    return false;
                }
                if (this.manualTestPassed) {
                    return true;
                }
                // Credentials already saved (e.g. storage bootstrap still pending).
                if (this.isManualConnected()) {
                    const tenantId = (this.status.azure_tenant_id || this.manualForm.tenant_id || '').trim();
                    if (tenantId !== '') {
                        return true;
                    }
                }
                return false;
            },

            initManualFormFromStatus() {
                const preview = this.status.credentials_preview || {};
                this.manualForm.region = preview.region || 'GlobalPublicCloud';
                this.manualForm.client_id = preview.client_id || '';
                this.manualForm.tenant_id = preview.tenant_id || '';
                this.manualForm.app_secret = '';
                if (this.isManualConnected()) {
                    this.connectMode = 'manual';
                } else if (!this.status.connected) {
                    this.connectMode = 'automatic';
                }
            },

            manualSecretPlaceholder() {
                const preview = this.status.credentials_preview || {};
                return preview.has_secret ? '(saved — leave blank to keep)' : '';
            },

            manualFormBody() {
                return new URLSearchParams({
                    user_id: this.backupUserId,
                    region: this.manualForm.region || 'GlobalPublicCloud',
                    client_id: (this.manualForm.client_id || '').trim(),
                    tenant_id: (this.manualForm.tenant_id || '').trim(),
                    app_secret: this.manualForm.app_secret || '',
                });
            },

            async testManualConnect() {
                this.manualTesting = true;
                this.manualError = '';
                this.manualNotice = '';
                try {
                    const res = await fetch(`${apiBase()}ms365_connect_test.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: this.manualFormBody().toString(),
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.manualTestPassed = true;
                        this.manualNotice = 'Connected: ' + (data.organization || 'OK');
                        toast('success', this.manualNotice);
                        return true;
                    }
                    this.manualError = data.message || 'Connection test failed.';
                    toast('error', this.manualError);
                    return false;
                } catch (e) {
                    this.manualError = 'Connection test failed.';
                    toast('error', this.manualError);
                    return false;
                } finally {
                    this.manualTesting = false;
                }
            },

            async saveManualConnect() {
                this.manualSaving = true;
                this.manualError = '';
                this.manualNotice = '';
                try {
                    const res = await fetch(`${apiBase()}ms365_connect_save.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: this.manualFormBody().toString(),
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        if (data.ms365) {
                            this.status = data.ms365;
                        } else {
                            await this.loadStatus();
                        }
                        this.manualTestPassed = true;
                        this.initManualFormFromStatus();
                        await this.handleManualConnectSuccess();
                        return true;
                    }
                    this.manualError = data.message || 'Save failed.';
                    toast('error', this.manualError);
                    return false;
                } catch (e) {
                    this.manualError = 'Save failed.';
                    toast('error', this.manualError);
                    return false;
                } finally {
                    this.manualSaving = false;
                }
            },

            async handleManualConnectSuccess() {
                if (!this.isM365Connected()) {
                    if (this.isManualConnected() && this.canProceedManualConnect()) {
                        return;
                    }
                    this.manualError = this.status.health_error
                        || 'Credentials were saved but the connection could not be verified.';
                    toast('error', this.manualError);
                    return;
                }
                this.manualError = '';
                this.loading = true;
                try {
                    const ok = await this.ensureFreshInventory({ silent: true });
                    if (ok) {
                        this.step = 2;
                        toast('success', 'Microsoft 365 connected.');
                    }
                } finally {
                    this.loading = false;
                }
            },

            handleReconnectRequired(message) {
                this.step = 1;
                this.consentError = message || this.status.health_error || '';
                clearInventoryStash();
                this.inventoryMeta = { resourceCount: 0, fetchedAt: null };
                this.inventoryViewRevision += 1;
                this.updateInventoryVirtualSlice();
                return this.loadStatus();
            },

            confirmDisconnect() {
                this.confirmModal = {
                    open: true,
                    title: DISCONNECT_CONFIRM.title,
                    message: DISCONNECT_CONFIRM.message,
                    confirmLabel: DISCONNECT_CONFIRM.confirmLabel,
                    action: 'disconnect',
                };
            },

            confirmSwitchOrganization() {
                this.confirmModal = {
                    open: true,
                    title: SWITCH_ORG_CONFIRM.title,
                    message: SWITCH_ORG_CONFIRM.message,
                    confirmLabel: SWITCH_ORG_CONFIRM.confirmLabel,
                    action: 'switch',
                };
            },

            closeConfirmModal() {
                if (this.disconnecting) {
                    return;
                }
                this.confirmModal.open = false;
            },

            isDirectoryBaselineNode(node) {
                return !!node && node.resourceType === 'directory_baseline';
            },

            openDirectoryBaselineInfo() {
                this.directoryInfoModal.open = true;
            },

            closeDirectoryBaselineInfo() {
                this.directoryInfoModal.open = false;
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
                    const body = new URLSearchParams({ user_id: this.backupUserId });
                    const res = await fetch(`${apiBase()}ms365_disconnect.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        if (data.ms365) {
                            this.status = data.ms365;
                        } else {
                            await this.loadStatus();
                        }
                        this.initManualFormFromStatus();
                        this.manualTestPassed = false;
                        clearInventoryStash();
                        this.inventoryMeta = { resourceCount: 0, fetchedAt: null };
                        this.inventoryViewRevision += 1;
                        this.selection = {};
                        this.step = 1;
                        this.confirmModal.open = false;
                        toast('success', 'Microsoft 365 disconnected.');
                        return true;
                    }
                    toast('error', data.message || 'Disconnect failed.');
                    return false;
                } catch (e) {
                    toast('error', 'Disconnect failed.');
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

            defaultJobName() {
                return buildDefaultJobName(this.backupUsername);
            },

            async resolveBackupUsername() {
                if ((this.backupUsername || '').trim() !== '') {
                    window.ms365WizardState.backupUsername = this.backupUsername;
                    return this.backupUsername;
                }
                try {
                    const res = await fetch(
                        `${apiBase()}e3backup_user_get.php?user_id=${encodeURIComponent(this.backupUserId)}`,
                        { credentials: 'same-origin' },
                    );
                    const data = await res.json();
                    if (data.status === 'success' && data.user && data.user.username) {
                        this.backupUsername = String(data.user.username).trim();
                        window.ms365WizardState.backupUsername = this.backupUsername;
                    }
                } catch (e) {
                    /* optional */
                }
                return this.backupUsername;
            },

            close() {
                this.stopConsentWait();
                this.stopInventoryProgressPoll();
                this.directoryInfoModal.open = false;
                this.confirmModal.open = false;
                const modal = document.getElementById('ms365JobWizardModal');
                if (modal) modal.classList.add('hidden');
                window.ms365WizardState.editMode = false;
                window.ms365WizardState.jobId = '';
            },

            canGoToStep(n) {
                if (n === 1) return true;
                if (n >= 2) return this.isM365Connected();
                return true;
            },

            goToStep(n) {
                if (!this.canGoToStep(n)) return;
                this.step = n;
                if (n === 2 && !this.hasInventoryLoaded()) {
                    this.loadInventory();
                }
            },

            async nextStep() {
                if (this.step === 1 && !this.isM365Connected()) {
                    if (this.connectMode === 'manual' && this.canProceedManualConnect()) {
                        if (!this.isManualConnected()) {
                            const saved = await this.saveManualConnect();
                            if (!saved) {
                                return;
                            }
                        }
                        if (this.step === 1) {
                            this.loading = true;
                            try {
                                const ok = await this.bootstrapInventoryForWizard({ autoAdvance: true });
                                if (ok || this.isManualConnected()) {
                                    this.step = 2;
                                }
                            } finally {
                                this.loading = false;
                            }
                        }
                        return;
                    }
                    toast('warning', this.status.needs_reconnect
                        ? 'Reconnect Microsoft 365 first.'
                        : (this.connectMode === 'manual'
                            ? 'Test the connection and save credentials before continuing.'
                            : 'Connect Microsoft 365 first.'));
                    return;
                }
                if (this.step === 2 && this.selectionCount() === 0) {
                    toast('warning', 'Select at least one resource.');
                    return;
                }
                if (this.step === 2) {
                    await this.refreshPlanFull();
                    if ((this.planSummary.runnable || 0) === 0) {
                        toast('warning', 'No runnable backup workloads match the current selection.');
                        return;
                    }
                }
                if (this.step === 4) {
                    if (!this.jobName || this.jobName === DEFAULT_JOB_NAME_SUFFIX) {
                        this.jobName = this.defaultJobName();
                    }
                }
                if (this.step < 5) {
                    this.step += 1;
                }
            },

            canProceed() {
                if (this.step === 1) {
                    if (this.connectMode === 'manual') {
                        return this.canProceedManualConnect();
                    }
                    return this.isM365Connected();
                }
                if (this.step === 2) return this.selectionCount() > 0;
                if (this.step === 3) return !!this.scheduleFrequency;
                if (this.step === 4) return !!this.retentionTier;
                if (this.step === 5) return String(this.jobName || '').trim().length > 0;
                return true;
            },

            isRetentionTierEnabled(tierId) {
                return tierId === DEFAULT_RETENTION_TIER;
            },

            selectRetentionTier(tierId) {
                if (!this.isRetentionTierEnabled(tierId)) {
                    return;
                }
                this.retentionTier = tierId;
            },

            async loadStatus() {
                this.loading = true;
                try {
                    const res = await fetch(`${apiBase()}ms365_status.php?user_id=${encodeURIComponent(this.backupUserId)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (data.status === 'success' && data.ms365) {
                        this.status = data.ms365;
                        this.initManualFormFromStatus();
                    }
                } catch (e) {
                    toast('error', 'Failed to load connection status.');
                } finally {
                    this.loading = false;
                }
            },

            stopConsentWait() {
                this.awaitingConsent = false;
                this.connecting = false;
                if (activeConsentHandler) {
                    activeConsentHandler = null;
                }
                if (this._consentPollTimer) {
                    clearInterval(this._consentPollTimer);
                    this._consentPollTimer = null;
                }
                if (this._consentTimeoutTimer) {
                    clearTimeout(this._consentTimeoutTimer);
                    this._consentTimeoutTimer = null;
                }
            },

            startConsentWait() {
                activeConsentHandler = (msg) => this.handleConsentMessage(msg);
                this._consentPollTimer = setInterval(() => this.pollConsentStatus(), CONSENT_POLL_MS);
                this._consentTimeoutTimer = setTimeout(() => this.handleConsentTimeout(), CONSENT_TIMEOUT_MS);
            },

            async handleConsentMessage(msg) {
                if (!this.awaitingConsent) {
                    return;
                }
                if (msg.backupUserId && msg.backupUserId !== this.backupUserId) {
                    return;
                }
                this.stopConsentWait();
                if (msg.status === 'success') {
                    await this.handleConsentSuccess();
                } else {
                    this.consentError = msg.error || 'Microsoft consent was not completed.';
                    toast('error', this.consentError);
                }
            },

            async handleConsentSuccess() {
                await this.loadStatus();
                if (!this.isM365Connected()) {
                    this.consentError = this.status.health_error
                        || 'Microsoft 365 consent completed but the connection could not be verified.';
                    toast('error', this.consentError);
                    return;
                }
                clearWizardCtx();
                this.consentError = '';
                this.loading = true;
                try {
                    const ok = await this.ensureFreshInventory({ silent: true });
                    if (ok) {
                        this.step = 2;
                        toast('success', 'Microsoft 365 connected.');
                    }
                } finally {
                    this.loading = false;
                }
            },

            async finalizeConsentFromServer() {
                await this.loadStatus();
                if (this.isM365Connected()) {
                    await this.handleConsentSuccess();
                    return;
                }
                this.stopConsentWait();
                this.consentError = this.status.health_error
                    || 'Microsoft 365 consent did not complete. Check Platform Entra app settings or try again.';
                toast('error', this.consentError);
            },

            async pollConsentStatus() {
                if (!this.awaitingConsent) {
                    return;
                }
                try {
                    const stored = sessionStorage.getItem(CONSENT_RESULT_KEY);
                    if (stored) {
                        const wrapped = JSON.parse(stored);
                        if (wrapped && wrapped.payload && wrapped.at > (Date.now() - CONSENT_TIMEOUT_MS)) {
                            sessionStorage.removeItem(CONSENT_RESULT_KEY);
                            dispatchConsentResult(wrapped.payload);
                            return;
                        }
                    }
                } catch (e) {
                    /* ignore */
                }
                if (isConsentPopupClosed() && activeConsentPopup !== null) {
                    clearConsentPopup();
                    await this.finalizeConsentFromServer();
                    return;
                }
                try {
                    const res = await fetch(`${apiBase()}ms365_status.php?user_id=${encodeURIComponent(this.backupUserId)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (data.status === 'success' && data.ms365 && data.ms365.connected) {
                        this.stopConsentWait();
                        await this.handleConsentSuccess();
                    }
                } catch (e) {
                    /* retry on next poll */
                }
            },

            handleConsentTimeout() {
                if (!this.awaitingConsent) {
                    return;
                }
                this.stopConsentWait();
                this.consentError = 'Microsoft sign-in timed out. Try again or reopen the Microsoft window.';
                toast('warning', this.consentError);
            },

            cancelConsentWait() {
                this.stopConsentWait();
                this.consentError = '';
                closeConsentPopup();
            },

            reopenConsentPopup() {
                if (!this.consentPopupUrl) {
                    this.connect();
                    return;
                }
                const popup = window.open(this.consentPopupUrl, 'ms365_consent', CONSENT_POPUP_FEATURES);
                if (!popup) {
                    toast('warning', 'Pop-up blocked. Allow pop-ups for this site or use Connect again.');
                    return;
                }
                setConsentPopup(popup);
                this.awaitingConsent = true;
                this.consentError = '';
                this.startConsentWait();
            },

            async connect() {
                this.stopConsentWait();
                this.connecting = true;
                this.consentError = '';

                if (!this.backupUserId) {
                    toast('error', 'Backup user is missing. Close the wizard and reopen it from the user\'s Jobs tab.');
                    this.connecting = false;
                    return;
                }

                saveWizardCtx(this.backupUserId);

                try {
                    await this.loadStatus();
                    if (this.isM365Connected()) {
                        await this.handleConsentSuccess();
                        return;
                    }

                    const data = await fetchConsentUrl(this.backupUserId, 'popup');
                    if (data.status !== 'success' || !data.consent_url) {
                        toast('error', data.message || 'Could not start Microsoft sign-in.');
                        return;
                    }

                    this.consentPopupUrl = data.consent_url;
                    const popup = window.open(data.consent_url, 'ms365_consent', CONSENT_POPUP_FEATURES);

                    if (!popup) {
                        const redirectData = await fetchConsentUrl(this.backupUserId, 'redirect');
                        if (redirectData.status === 'success' && redirectData.consent_url) {
                            window.location.href = redirectData.consent_url;
                            return;
                        }
                        toast('error', 'Pop-up blocked. Allow pop-ups for this site and try again.');
                        return;
                    }

                    setConsentPopup(popup);
                    this.awaitingConsent = true;
                    this.startConsentWait();
                } catch (e) {
                    console.error('ms365 wizard connect failed', e);
                    toast('error', 'Could not start Microsoft sign-in.');
                } finally {
                    if (!this.awaitingConsent) {
                        this.connecting = false;
                    }
                }
            },

            resetInventoryProgress() {
                this.inventoryProgress = {
                    phase: 'idle',
                    message: 'Starting inventory refresh…',
                    detail: '',
                    counts: {},
                    refresh_in_progress: false,
                };
            },

            inventoryRefreshActive() {
                return this.refreshingInventory || this.inventoryBackgroundRefreshing;
            },

            inventoryShowsHeaderStrip() {
                return this.inventoryRefreshActive()
                    && (this.inventoryBackgroundRefreshing || (this.refreshingInventory && this.hasUsableInventory()));
            },

            markInventoryRefreshStart() {
                this.inventoryRefreshStartedAtMs = Date.now();
                this.inventoryElapsedTick = 0;
                this.stopInventoryElapsedTimer();
                this._inventoryElapsedTimer = setInterval(() => {
                    this.inventoryElapsedTick += 1;
                }, 1000);
            },

            clearInventoryRefreshStart() {
                this.inventoryRefreshStartedAtMs = null;
                this.stopInventoryElapsedTimer();
            },

            stopInventoryElapsedTimer() {
                if (this._inventoryElapsedTimer) {
                    clearInterval(this._inventoryElapsedTimer);
                    this._inventoryElapsedTimer = null;
                }
            },

            inventoryElapsedLabel() {
                void this.inventoryElapsedTick;
                const start = this.inventoryRefreshStartedAtMs;
                if (!start) {
                    return '';
                }
                const secs = Math.max(0, Math.floor((Date.now() - start) / 1000));
                if (secs < 60) {
                    return `${secs}s`;
                }
                const mins = Math.floor(secs / 60);
                const rem = secs % 60;
                return `${mins}m ${rem}s`;
            },

            inventoryProgressMessage() {
                const phase = this.inventoryProgress.phase || '';
                if (phase === 'idle') {
                    return 'Starting inventory refresh…';
                }
                return this.inventoryProgress.message || 'Discovering Microsoft 365 resources…';
            },

            inventoryProgressChips() {
                const displayCounts = this.inventoryProgress.display_counts || {};
                const counts = this.inventoryProgress.counts || {};
                const phase = this.inventoryProgress.phase || '';
                const merged = { ...counts };
                if (displayCounts.sites !== undefined && displayCounts.sites !== null) {
                    merged.sites = displayCounts.sites;
                }
                const siteCountReady = ['listable', 'assembling', 'complete'].includes(phase);
                return Object.keys(INVENTORY_PROGRESS_LABELS)
                    .filter((key) => {
                        if (INVENTORY_PHASE_ONLY_KEYS.has(key)) {
                            return phase === key;
                        }
                        if (key === 'sites' && !siteCountReady) {
                            return false;
                        }
                        return Number(merged[key]) > 0;
                    })
                    .map((key) => ({
                        key,
                        label: INVENTORY_PROGRESS_LABELS[key],
                        count: INVENTORY_PHASE_ONLY_KEYS.has(key) ? null : Number(merged[key]),
                    }));
            },

            startInventoryProgressPoll() {
                this.stopInventoryProgressPoll();
                this.resetInventoryProgress();
                this.markInventoryRefreshStart();
                this.inventoryProgress.refresh_in_progress = true;
                this.pollInventoryProgress();
                this._inventoryProgressTimer = setInterval(() => {
                    if (this.inventoryRefreshActive()) {
                        this.pollInventoryProgress();
                    }
                }, INVENTORY_PROGRESS_POLL_MS);
            },

            stopInventoryProgressPoll() {
                if (this._inventoryProgressTimer) {
                    clearInterval(this._inventoryProgressTimer);
                    this._inventoryProgressTimer = null;
                }
                this.inventoryProgress.refresh_in_progress = false;
                this.clearInventoryRefreshStart();
            },

            async pollInventoryProgress() {
                if (!this.backupUserId) {
                    return;
                }
                try {
                    const res = await fetch(
                        `${apiBase()}ms365_inventory_progress.php?user_id=${encodeURIComponent(this.backupUserId)}`,
                        { credentials: 'same-origin' },
                    );
                    const data = await res.json();
                    if (data.status === 'success' && data.progress) {
                        this.inventoryProgress = {
                            ...this.inventoryProgress,
                            ...data.progress,
                            refresh_in_progress: this.inventoryRefreshActive(),
                        };
                    }
                } catch (e) {
                    /* keep last known progress */
                }
            },

            async loadInventory(opts = {}) {
                const silent = !!opts.silent;
                try {
                    const res = await fetch(`${apiBase()}ms365_inventory.php?user_id=${encodeURIComponent(this.backupUserId)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (data.status === 'success') {
                        const inventory = data.inventory || { resources: [] };
                        rebuildInventoryStash(inventory);
                        this.inventoryMeta = {
                            resourceCount: Array.isArray(inventory.resources) ? inventory.resources.length : 0,
                            fetchedAt: inventory.fetched_at || null,
                        };
                        this.inventoryViewRevision += 1;
                        inventoryStash.memo.inaccessibleSiteCount = null;
                        inventoryStash.memo.globalCheckState = null;
                        this.updateInventoryVirtualSlice();
                        if (this.savedSelectionIds.length > 0) {
                            this.applySavedSelection();
                        }
                        return true;
                    }
                    if (data.reconnect_required) {
                        await this.handleReconnectRequired(data.message);
                        if (!silent) {
                            toast('warning', data.message || 'Microsoft 365 must be reconnected.');
                        }
                        return false;
                    }
                    return false;
                } catch (e) {
                    if (!silent) {
                        toast('error', 'Failed to load inventory.');
                    }
                    return false;
                }
            },

            async waitForInventoryRefreshComplete(waitUntil = 'complete') {
                const deadline = Date.now() + 3600000;
                const workerStartDeadline = Date.now() + INVENTORY_WORKER_START_TIMEOUT_MS;
                const donePhases = waitUntil === 'listable' ? ['listable', 'complete'] : ['complete'];
                let reloadedListable = false;
                while (Date.now() < deadline) {
                    await this.pollInventoryProgress();
                    const phase = this.inventoryProgress.phase || '';
                    if (donePhases.includes(phase)) {
                        if (phase === 'listable' && !reloadedListable) {
                            await this.loadInventory({ silent: true });
                            reloadedListable = true;
                        }
                        if (phase === 'complete' || waitUntil === 'listable') {
                            return true;
                        }
                    }
                    if (phase === 'error') {
                        return false;
                    }
                    if (phase === 'idle' && Date.now() > workerStartDeadline) {
                        this.inventoryProgress = {
                            ...this.inventoryProgress,
                            phase: 'error',
                            message: 'Inventory refresh failed',
                            detail: 'Background worker did not start. Please try again.',
                        };
                        return false;
                    }
                    await new Promise((resolve) => setTimeout(resolve, INVENTORY_PROGRESS_POLL_MS));
                }
                return false;
            },

            async ensureFreshInventory(opts = {}) {
                const silent = !!opts.silent;
                const showSuccessToast = !!opts.showSuccessToast;
                const clearInventory = opts.clearInventory !== false;
                const waitUntil = opts.waitUntil || 'complete';

                if (clearInventory) {
                    clearInventoryStash();
                    this.inventoryMeta = { resourceCount: 0, fetchedAt: null };
                    this.inventoryViewRevision += 1;
                }
                this.refreshingInventory = true;
                this.startInventoryProgressPoll();
                try {
                    const body = new URLSearchParams({ user_id: this.backupUserId });
                    const res = await fetch(`${apiBase()}ms365_inventory_refresh.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                    const data = await res.json();
                    const accepted = data.status === 'accepted' || data.refresh_in_progress;
                    if (data.status === 'success' || accepted) {
                        if (accepted) {
                            const completed = await this.waitForInventoryRefreshComplete(waitUntil);
                            if (!completed) {
                                if (!silent) {
                                    toast('error', this.inventoryProgress.message || this.inventoryProgress.detail || 'Inventory refresh failed.');
                                }
                                return false;
                            }
                        }
                        const loaded = await this.loadInventory({ silent });
                        if (loaded) {
                            if (showSuccessToast) {
                                toast('success', 'Inventory refreshed.');
                            }
                            return true;
                        }
                        return false;
                    }
                    if (data.reconnect_required) {
                        await this.handleReconnectRequired(data.message);
                        if (!silent) {
                            toast('warning', data.message || 'Microsoft 365 must be reconnected.');
                        }
                        return false;
                    }
                    if (!silent) {
                        toast('error', data.message || 'Refresh failed.');
                    }
                    return false;
                } catch (e) {
                    if (!silent) {
                        toast('error', 'Refresh failed.');
                    }
                    return false;
                } finally {
                    this.refreshingInventory = false;
                    this.stopInventoryProgressPoll();
                    await this.pollInventoryProgress();
                }
            },

            async refreshInventory() {
                await this.ensureFreshInventory({ showSuccessToast: true, clearInventory: true, waitUntil: 'complete' });
            },

            async loadJob() {
                this.loading = true;
                try {
                    const res = await fetch(`${apiBase()}ms365_job_get.php?user_id=${encodeURIComponent(this.backupUserId)}&job_id=${encodeURIComponent(this.jobId)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (data.status === 'success' && data.job) {
                        const j = data.job;
                        this.jobName = j.name || this.defaultJobName();
                        this.savedSelectionIds = Array.isArray(j.selected_resource_ids) ? [...j.selected_resource_ids] : [];
                        this.scopeOverrides = j.scope_overrides || {};
                        this.scheduleFrequency = j.schedule_frequency || 'once_daily';
                        const loadedTier = j.retention_tier || DEFAULT_RETENTION_TIER;
                        this.retentionTier = this.isRetentionTierEnabled(loadedTier)
                            ? loadedTier
                            : DEFAULT_RETENTION_TIER;
                        if (this.hasInventoryLoaded()) {
                            this.applySavedSelection();
                        }
                    }
                } catch (e) {
                    toast('error', 'Failed to load job.');
                } finally {
                    this.loading = false;
                }
            },

            inventorySections() {
                return INVENTORY_SECTIONS();
            },

            selectionCount() {
                return Object.keys(this.selection).filter((k) => this.selection[k]).length;
            },

            inventoryGlobalCheckState() {
                if (!window.ms365JobSelection) {
                    return 'unchecked';
                }
                if (inventoryStash.memo.globalCheckState !== null) {
                    return inventoryStash.memo.globalCheckState;
                }
                const state = window.ms365JobSelection.globalCheckState(
                    inventoryStash.treesBySection,
                    this.selection,
                );
                inventoryStash.memo.globalCheckState = state;
                return state;
            },

            toggleSelectAllInventory() {
                if (!window.ms365JobSelection) {
                    return;
                }
                this.selection = window.ms365JobSelection.toggleGlobalSelect(
                    inventoryStash.treesBySection,
                    this.selection,
                );
                inventoryStash.memo.globalCheckState = null;
                this.syncSelectionPayload();
            },

            inventoryIconClass(iconKey) {
                if (window.ms365InventoryIcons && typeof window.ms365InventoryIcons.cssClassFor === 'function') {
                    return window.ms365InventoryIcons.cssClassFor(iconKey || 'user');
                }
                return 'ms365-inv-icon ms365-inv-icon--user';
            },

            initInventoryIcon(el, iconKey) {
                if (!el || !window.ms365InventoryIcons) return;
                if (el.dataset.iconKey === iconKey) return;
                el.dataset.iconKey = iconKey || 'user';
                el.innerHTML = window.ms365InventoryIcons.svgFor(iconKey || 'user');
            },

            usesServerSelectAll() {
                return this.isGlobalSelectAll();
            },

            selectionSummaryRowCount() {
                const counts = this.selectionWorkloadCounts || {};
                const accounts = Number(counts.users_and_mailboxes || counts.protected_accounts || 0);
                if (accounts > 0) {
                    return accounts;
                }
                return (this.savedSelectionIds || []).length;
            },

            workloadCount(key) {
                const counts = this.selectionWorkloadCounts || {};
                return Number(counts[key] || 0);
            },

            hasAnyAccountBreakdown() {
                return this.workloadCount('users') > 0
                    || this.workloadCount('shared_mailboxes') > 0
                    || this.workloadCount('guests') > 0;
            },

            hasAnyUserWorkloadCounts() {
                return this.workloadCount('mail') > 0
                    || this.workloadCount('calendar') > 0
                    || this.workloadCount('contacts') > 0
                    || this.workloadCount('tasks') > 0
                    || this.workloadCount('onedrive') > 0;
            },

            rebuildTrees() {
                rebuildInventoryStash(inventoryStash.inventory);
                this.inventoryViewRevision += 1;
                this.updateInventoryVirtualSlice();
            },

            applySavedSelection() {
                if (!window.ms365JobSelection) return;
                this.selection = window.ms365JobSelection.hydrateFromSavedJob(
                    inventoryStash.inventory,
                    this.savedSelectionIds,
                    this.scopeOverrides,
                );
                inventoryStash.memo.globalCheckState = null;
                this.syncSelectionPayload();
            },

            isGlobalSelectAll() {
                return this.inventoryGlobalCheckState() === 'checked';
            },

            abortPlanFetch() {
                if (this._planAbortController) {
                    try {
                        this._planAbortController.abort();
                    } catch (e) {
                        /* ignore */
                    }
                    this._planAbortController = null;
                }
            },

            syncSelectionPayload() {
                if (!window.ms365JobSelection) return;
                const payload = window.ms365JobSelection.buildSavePayload(
                    inventoryStash.inventory,
                    inventoryStash.treesBySection,
                    this.selection,
                );
                this.scopeOverrides = payload.scope_overrides;
                this.savedSelectionIds = payload.selected_resource_ids;
                this.selectionWorkloadCounts = window.ms365JobSelection.selectionWorkloadCounts(
                    inventoryStash.inventory,
                    inventoryStash.treesBySection,
                    this.selection,
                );
                inventoryStash.memo.globalCheckState = null;
                this.selectionVersion += 1;
                this.updateInventoryVirtualSlice();
                this.scheduleRefreshPlan();
            },

            scheduleRefreshPlan() {
                if (this._planDebounceTimer) {
                    clearTimeout(this._planDebounceTimer);
                    this._planDebounceTimer = null;
                }

                // Empty selection: clear immediately (do not wait for debounce / in-flight plan).
                if (!this.backupUserId || this.savedSelectionIds.length === 0) {
                    this._planRequestSeq += 1;
                    this.abortPlanFetch();
                    this.planWarnings = [];
                    this.planSummary = { runnable: 0, deferred: 0 };
                    this.billingPreview = null;
                    this.billingPreviewLoading = false;
                    this.billingPreviewError = '';
                    this.selectionWorkloadCounts = {};
                    return;
                }

                this.billingPreviewLoading = true;
                this.billingPreviewError = '';
                this.abortPlanFetch();
                this._planDebounceTimer = setTimeout(() => {
                    this._planDebounceTimer = null;
                    this.refreshBillingPreview();
                }, PLAN_DEBOUNCE_MS);
            },

            buildPlanRequestBody() {
                const selectAll = this.usesServerSelectAll();
                return {
                    user_id: this.backupUserId,
                    select_all: selectAll,
                    selected_resource_ids: selectAll ? [] : this.savedSelectionIds,
                    scope_overrides: selectAll ? {} : (this.scopeOverrides || {}),
                    billing_exempt_resource_ids: [],
                    billing_exempt_key_present: true,
                };
            },

            async refreshBillingPreview() {
                const seq = ++this._planRequestSeq;
                const selectAll = this.usesServerSelectAll();
                if (!this.backupUserId || (!selectAll && this.savedSelectionIds.length === 0)) {
                    this.billingPreview = null;
                    this.billingPreviewLoading = false;
                    this.billingPreviewError = '';
                    return;
                }

                this.billingPreviewLoading = true;
                this.billingPreviewError = '';
                this.abortPlanFetch();
                const abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
                this._planAbortController = abortController;

                const requestBody = this.buildPlanRequestBody();

                try {
                    const fetchOpts = {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(requestBody),
                    };
                    if (abortController) {
                        fetchOpts.signal = abortController.signal;
                    }
                    const res = await fetch(`${apiBase()}ms365_job_billing_preview.php`, fetchOpts);
                    let data;
                    try {
                        data = await res.json();
                    } catch (parseError) {
                        throw new Error('Invalid billing preview response');
                    }
                    if (seq !== this._planRequestSeq) {
                        return;
                    }
                    if (data.status === 'success' && data.billing) {
                        this.billingPreview = JSON.parse(JSON.stringify(data.billing));
                        this.billingPreviewError = '';
                    } else {
                        this.billingPreviewError = data.message || 'Could not calculate billing estimate.';
                    }
                } catch (e) {
                    if (e && e.name === 'AbortError') {
                        return;
                    }
                    if (seq === this._planRequestSeq) {
                        this.billingPreviewError = 'Could not calculate billing estimate.';
                    }
                } finally {
                    if (seq === this._planRequestSeq) {
                        this.billingPreviewLoading = false;
                        this._planAbortController = null;
                    }
                }
            },

            async refreshPlanFull() {
                const selectAll = this.usesServerSelectAll();
                if (!this.backupUserId || (!selectAll && this.savedSelectionIds.length === 0)) {
                    this.planWarnings = [];
                    this.planSummary = { runnable: 0, deferred: 0 };
                    return;
                }

                try {
                    const res = await fetch(`${apiBase()}ms365_job_plan.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            ...this.buildPlanRequestBody(),
                            summary_only: true,
                        }),
                    });
                    const data = await res.json();
                    if (data.status === 'success' && data.plan) {
                        this.planWarnings = data.plan.warnings || [];
                        this.planSummary = data.plan.summary || { runnable: 0, deferred: 0 };
                    }
                } catch (e) {
                    /* keep last plan summary */
                }
            },

            async refreshPlan() {
                await this.refreshBillingPreview();
                await this.refreshPlanFull();
            },

            sectionHasNodes(sectionKey) {
                const nodes = inventoryStash.treesBySection[sectionKey] || [];
                const indexes = inventoryStash.sectionIndexes[sectionKey];
                const sel = window.ms365JobSelection;
                if (this.inventoryFilterActive() && sel) {
                    return sel.sectionHasVisibleNodes(nodes, this.searchQuery, this.expandedKeys, indexes);
                }
                return nodes.some((n) => n.depth === 0);
            },

            inventoryFilterActive() {
                return (this.searchQuery || '').trim() !== '';
            },

            buildInventoryFlatRows() {
                const sel = window.ms365JobSelection;
                if (!sel) {
                    return [];
                }
                const rows = [];
                this.inventorySections().forEach((section) => {
                    const nodes = inventoryStash.treesBySection[section.key] || [];
                    const indexes = inventoryStash.sectionIndexes[section.key];
                    const visible = sel.visibleNodes(
                        nodes,
                        this.selection,
                        this.searchQuery,
                        this.expandedKeys,
                        indexes,
                    );
                    if (visible.length === 0) {
                        return;
                    }
                    rows.push({
                        type: 'section',
                        key: `section:${section.key}`,
                        sectionKey: section.key,
                        label: section.label,
                    });
                    if (section.key === 'sharepoint' && this.inaccessibleSiteCount() > 0) {
                        rows.push({
                            type: 'sharepoint_notice',
                            key: `section:${section.key}:notice`,
                            count: this.inaccessibleSiteCount(),
                        });
                    }
                    visible.forEach((node) => {
                        rows.push({
                            type: 'node',
                            key: `${section.key}:${node.key}`,
                            sectionKey: section.key,
                            node,
                        });
                    });
                });
                return rows;
            },

            onInventoryScroll(event) {
                const target = event && event.target;
                if (!target) return;
                this.inventoryScrollTop = target.scrollTop || 0;
                if (target.clientHeight > 0) {
                    this._inventoryViewportHeight = target.clientHeight;
                }
                this.updateInventoryVirtualSlice();
            },

            initInventoryScroller() {
                const bind = () => {
                    const el = this.$refs && this.$refs.inventoryScroller;
                    if (!el) return;
                    if (el.clientHeight > 0) {
                        this._inventoryViewportHeight = el.clientHeight;
                    }
                    this.updateInventoryVirtualSlice();
                };
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(bind);
                } else {
                    setTimeout(bind, 0);
                }
            },

            updateInventoryVirtualSlice() {
                const rows = this.buildInventoryFlatRows();
                const rowHeight = INVENTORY_ROW_HEIGHT;
                const viewport = this._inventoryViewportHeight || 480;
                const scrollTop = this.inventoryScrollTop || 0;
                const start = Math.max(0, Math.floor(scrollTop / rowHeight) - INVENTORY_VIRTUAL_OVERSCAN);
                const visibleCount = Math.ceil(viewport / rowHeight) + (INVENTORY_VIRTUAL_OVERSCAN * 2);
                const end = Math.min(rows.length, start + visibleCount);
                this.inventoryVirtualSlice = {
                    rows: rows.slice(start, end),
                    paddingTop: start * rowHeight,
                    paddingBottom: Math.max(0, (rows.length - end) * rowHeight),
                    totalHeight: rows.length * rowHeight,
                };
            },

            inventoryVisibleNodeCount() {
                return this.buildInventoryFlatRows().filter((row) => row.type === 'node').length;
            },

            inaccessibleSiteCount() {
                const sel = window.ms365JobSelection;
                if (!sel) return 0;
                if (inventoryStash.memo.inaccessibleSiteCount !== null) {
                    return inventoryStash.memo.inaccessibleSiteCount;
                }
                const count = sel.countInaccessibleSites(inventoryStash.inventory);
                inventoryStash.memo.inaccessibleSiteCount = count;
                return count;
            },

            visibleSectionNodes(sectionKey) {
                const sel = window.ms365JobSelection;
                if (!sel) return [];
                const nodes = inventoryStash.treesBySection[sectionKey] || [];
                const indexes = inventoryStash.sectionIndexes[sectionKey];
                return sel.visibleNodes(nodes, this.selection, this.searchQuery, this.expandedKeys, indexes);
            },

            toggleExpandNode(sectionKey, node) {
                if (!node.hasChildren) return;
                this.expandedKeys[node.key] = !this.expandedKeys[node.key];
                this.updateInventoryVirtualSlice();
            },

            isNodeExpanded(node) {
                return !!this.expandedKeys[node.key];
            },

            nodeCheckState(sectionKey, node) {
                const sel = window.ms365JobSelection;
                if (!sel) return 'unchecked';
                const nodes = inventoryStash.treesBySection[sectionKey] || [];
                const indexes = inventoryStash.sectionIndexes[sectionKey];
                if (node.kind === 'parent') {
                    return sel.parentCheckState(nodes, this.selection, node, indexes);
                }
                return sel.isChecked(this.selection, node.key) ? 'checked' : 'unchecked';
            },

            toggleTreeNode(sectionKey, node) {
                const sel = window.ms365JobSelection;
                if (!sel) return;
                const nodes = inventoryStash.treesBySection[sectionKey] || [];
                const indexes = inventoryStash.sectionIndexes[sectionKey];
                sel.toggleNode(nodes, this.selection, node, indexes);
                this.syncSelectionPayload();
            },

            setCheckboxIndeterminate(el, state) {
                if (!el) return;
                el.indeterminate = state === 'indeterminate';
                el.checked = state === 'checked';
            },

            removeSummaryItem(sectionKey, item) {
                const sel = window.ms365JobSelection;
                if (!sel) return;
                const nodes = inventoryStash.treesBySection[sectionKey] || [];
                const indexes = inventoryStash.sectionIndexes[sectionKey];
                nodes.forEach((node) => {
                    if (!sel.isChecked(this.selection, node.key)) return;
                    if (node.kind === 'capability' && item.subtitle === node.label) {
                        const parentNode = sel.nodeByKey(nodes, node.parentKey, indexes);
                        if (parentNode && parentNode.label === item.label) {
                            delete this.selection[node.key];
                        }
                    } else if ((node.kind === 'leaf' || node.kind === 'resource_child') && node.label === item.subtitle && item.label === node.label) {
                        delete this.selection[node.key];
                    } else if (node.kind === 'parent' && item.subtitle === 'All components' && node.label === item.label) {
                        sel.toggleNode(nodes, this.selection, node, indexes);
                    }
                });
                this.syncSelectionPayload();
            },

            async save() {
                this.syncSelectionPayload();
                if (this.savedSelectionIds.length === 0) {
                    toast('warning', 'Select at least one resource.');
                    return;
                }
                if ((this.planSummary.runnable || 0) === 0) {
                    toast('warning', 'No runnable backup workloads match the current selection.');
                    return;
                }
                const trimmedName = String(this.jobName || '').trim();
                if (!trimmedName) {
                    toast('warning', 'Job name is required.');
                    this.step = 5;
                    return;
                }
                this.jobName = trimmedName;
                const retentionTier = this.isRetentionTierEnabled(this.retentionTier)
                    ? this.retentionTier
                    : DEFAULT_RETENTION_TIER;
                this.retentionTier = retentionTier;
                this.saving = true;
                try {
                    const browserTz = (typeof Intl !== 'undefined' && Intl.DateTimeFormat)
                        ? Intl.DateTimeFormat().resolvedOptions().timeZone
                        : '';
                    const body = new URLSearchParams({
                        user_id: this.backupUserId,
                        name: this.jobName,
                        schedule_frequency: this.scheduleFrequency,
                        retention_tier: retentionTier,
                        selected_resource_ids: JSON.stringify(this.savedSelectionIds),
                        scope_overrides: JSON.stringify(this.scopeOverrides || {}),
                        billing_exempt_resource_ids: JSON.stringify([]),
                    });
                    if (browserTz) {
                        body.set('timezone', browserTz);
                    }
                    if (this.editMode && this.jobId) {
                        body.set('job_id', this.jobId);
                    }
                    const res = await fetch(`${apiBase()}ms365_job_save.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        toast('success', this.editMode ? 'Job updated.' : 'Job created.');
                        this.close();
                        if (typeof window.e3backupAfterJobSaved === 'function') {
                            window.e3backupAfterJobSaved();
                        } else if (typeof window.e3backupReloadJobs === 'function') {
                            window.e3backupReloadJobs();
                        }
                    } else if (data.reconnect_required) {
                        await this.handleReconnectRequired(data.message);
                        toast('warning', data.message || 'Microsoft 365 must be reconnected.');
                    } else {
                        toast('error', data.message || 'Save failed.');
                    }
                } catch (e) {
                    toast('error', 'Save failed.');
                } finally {
                    this.saving = false;
                }
            },
        };
    };

    function ms365WizardComponent(modal) {
        if (!modal) return null;
        if (typeof Alpine !== 'undefined' && typeof Alpine.$data === 'function') {
            try {
                return Alpine.$data(modal);
            } catch (e) {
                /* not initialized yet */
            }
        }
        return modal._x_dataStack && modal._x_dataStack[0] ? modal._x_dataStack[0] : null;
    }

    window.openMs365JobWizard = function openMs365JobWizard(opts = {}) {
        window.ms365WizardState.backupUserId = opts.backupUserId || window.ms365WizardState.backupUserId || '';
        if (opts.backupUsername) {
            window.ms365WizardState.backupUsername = opts.backupUsername;
        }
        const modal = document.getElementById('ms365JobWizardModal');
        if (!modal) {
            toast('error', 'Microsoft 365 wizard is not loaded on this page.');
            return;
        }
        let component = ms365WizardComponent(modal);
        if (component && typeof component.open === 'function') {
            component.open(opts);
            return;
        }
        if (typeof Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
            Alpine.initTree(modal);
            component = ms365WizardComponent(modal);
            if (component && typeof component.open === 'function') {
                component.open(opts);
            } else {
                toast('error', 'Microsoft 365 wizard failed to initialize.');
            }
        }
    };

    window.openMs365JobWizardForEdit = function openMs365JobWizardForEdit(jobId, backupUserId) {
        openMs365JobWizard({ editMode: true, jobId, backupUserId, step: 2 });
    };

    window.openMs365JobWizardFromJobs = async function openMs365JobWizardFromJobs() {
        try {
            const res = await fetch(`${apiBase()}e3backup_user_list.php`, { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status !== 'success' || !Array.isArray(data.users) || data.users.length === 0) {
                toast('warning', 'Create a backup user first, then open Microsoft 365 backup from that user\'s Jobs tab.');
                return;
            }
            let userId = '';
            let backupUsername = '';
            if (data.users.length === 1) {
                userId = data.users[0].public_id || String(data.users[0].id);
                backupUsername = data.users[0].username || '';
            } else {
                const names = data.users.map((u, i) => `${i + 1}. ${u.username}`).join('\n');
                const pick = window.prompt(`Select backup user number for this Microsoft 365 job:\n${names}`);
                const idx = parseInt(pick, 10) - 1;
                if (Number.isNaN(idx) || idx < 0 || idx >= data.users.length) {
                    return;
                }
                userId = data.users[idx].public_id || String(data.users[idx].id);
                backupUsername = data.users[idx].username || '';
            }
            openMs365JobWizard({ backupUserId: userId, backupUsername });
        } catch (e) {
            toast('error', 'Could not load backup users.');
        }
    };

    function portalMs365JobWizardModal() {
        const modal = document.getElementById('ms365JobWizardModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    }

    document.addEventListener('DOMContentLoaded', portalMs365JobWizardModal);
    if (document.readyState !== 'loading') {
        portalMs365JobWizardModal();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('ms365_wizard') !== '1') {
            return;
        }

        const ctx = readWizardCtx();
        const el = document.querySelector('[data-e3backup-user-detail-app]');
        const uid = el?.getAttribute('data-backup-user-public-id')
            || params.get('user_id')
            || (ctx && ctx.backupUserId)
            || '';
        const backupUsername = el?.getAttribute('data-backup-username') || '';

        if (!uid) {
            return;
        }

        window.ms365WizardState.backupUserId = uid;
        if (backupUsername) {
            window.ms365WizardState.backupUsername = backupUsername;
        }
        const connectOk = params.get('connect_ok') === '1';
        const connectError = params.get('connect_error') || '';

        setTimeout(() => {
            openMs365JobWizard({ backupUserId: uid, backupUsername, step: connectOk ? 2 : 1 });
            if (connectError) {
                toast('error', connectError);
            }
            if (connectOk) {
                clearWizardCtx();
            }
            cleanWizardUrlParams();
        }, 300);
    });
})();
