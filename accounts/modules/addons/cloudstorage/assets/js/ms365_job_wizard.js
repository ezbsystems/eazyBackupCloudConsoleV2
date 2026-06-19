(function () {
    'use strict';

    const INVENTORY_SECTIONS = () => (
        (window.ms365JobSelection && window.ms365JobSelection.SECTIONS) || []
    );

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

    const INVENTORY_PROGRESS_LABELS = {
        users: 'Users',
        sites: 'SharePoint sites',
        teams: 'Teams',
        groups: 'Groups',
    };
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
        editMode: false,
        jobId: '',
    };

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
            stepLabels: ['Connect', 'Inventory', 'Schedule', 'Retention'],
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
            inventory: { resources: [] },
            treesBySection: {},
            selection: {},
            expandedKeys: {},
            scopeOverrides: {},
            planWarnings: [],
            planSummary: { runnable: 0, deferred: 0 },
            selectionSummaryGroups: [],
            savedSelectionIds: [],
            searchQuery: '',
            scheduleFrequency: 'once_daily',
            retentionTier: '1y',
            retentionOptions: RETENTION_OPTIONS,
            jobName: 'Microsoft 365 Backup',
            _consentPollTimer: null,
            _consentTimeoutTimer: null,
            _inventoryProgressTimer: null,

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

                this.editMode = !!opts.editMode;
                this.jobId = opts.jobId || '';
                this.step = opts.step || (this.editMode ? 2 : 1);
                this.selection = {};
                this.expandedKeys = {};
                this.scopeOverrides = {};
                this.planWarnings = [];
                this.planSummary = { runnable: 0, deferred: 0 };
                this.selectionSummaryGroups = [];
                this.savedSelectionIds = [];
                this.scheduleFrequency = 'once_daily';
                this.retentionTier = '1y';
                this.jobName = 'Microsoft 365 Backup';
                this.inventory = { resources: [] };

                modal.classList.remove('hidden');
                await this.loadStatus();
                if (this.editMode && this.jobId) {
                    await this.loadJob();
                }
                if (!this.isM365Connected() && this.step > 1) {
                    this.step = 1;
                }

                const autoAdvance = this.isM365Connected() && this.step === 1 && !opts.step;
                const needsFreshInventory = this.isM365Connected() && (this.step >= 2 || autoAdvance);
                if (needsFreshInventory) {
                    this.loading = true;
                    try {
                        const ok = await this.ensureFreshInventory({ silent: true });
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
                this.inventory = { resources: [] };
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
                        this.inventory = { resources: [] };
                        this.treesBySection = {};
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
                if (n === 2 && (!this.inventory.resources || this.inventory.resources.length === 0)) {
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
                                const ok = await this.ensureFreshInventory({ silent: true });
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
                if (this.step === 2 && (this.planSummary.runnable || 0) === 0) {
                    toast('warning', 'No runnable backup workloads match the current selection.');
                    return;
                }
                if (this.step < 4) {
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
                if (this.step === 2) return this.selectionCount() > 0 && (this.planSummary.runnable || 0) > 0;
                if (this.step === 3) return !!this.scheduleFrequency;
                return true;
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
                    phase: 'users',
                    message: 'Discovering Microsoft 365 resources…',
                    detail: '',
                    counts: {},
                    refresh_in_progress: false,
                };
            },

            inventoryProgressMessage() {
                return this.inventoryProgress.message || 'Discovering Microsoft 365 resources…';
            },

            inventoryProgressChips() {
                const counts = this.inventoryProgress.counts || {};
                return Object.keys(INVENTORY_PROGRESS_LABELS)
                    .filter((key) => Number(counts[key]) > 0)
                    .map((key) => ({
                        key,
                        label: INVENTORY_PROGRESS_LABELS[key],
                        count: Number(counts[key]),
                    }));
            },

            startInventoryProgressPoll() {
                this.stopInventoryProgressPoll();
                this.resetInventoryProgress();
                this.inventoryProgress.refresh_in_progress = true;
                this.pollInventoryProgress();
                this._inventoryProgressTimer = setInterval(() => {
                    this.pollInventoryProgress();
                }, INVENTORY_PROGRESS_POLL_MS);
            },

            stopInventoryProgressPoll() {
                if (this._inventoryProgressTimer) {
                    clearInterval(this._inventoryProgressTimer);
                    this._inventoryProgressTimer = null;
                }
                this.inventoryProgress.refresh_in_progress = false;
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
                            refresh_in_progress: this.refreshingInventory,
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
                        this.inventory = data.inventory || { resources: [] };
                        this.rebuildTrees();
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

            async ensureFreshInventory(opts = {}) {
                const silent = !!opts.silent;
                const showSuccessToast = !!opts.showSuccessToast;

                this.inventory = { resources: [] };
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
                    if (data.status === 'success') {
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
                await this.ensureFreshInventory({ showSuccessToast: true });
            },

            async loadJob() {
                this.loading = true;
                try {
                    const res = await fetch(`${apiBase()}ms365_job_get.php?user_id=${encodeURIComponent(this.backupUserId)}&job_id=${encodeURIComponent(this.jobId)}`, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (data.status === 'success' && data.job) {
                        const j = data.job;
                        this.jobName = j.name || 'Microsoft 365 Backup';
                        this.savedSelectionIds = Array.isArray(j.selected_resource_ids) ? [...j.selected_resource_ids] : [];
                        this.scopeOverrides = j.scope_overrides || {};
                        this.scheduleFrequency = j.schedule_frequency || 'once_daily';
                        this.retentionTier = j.retention_tier || '1y';
                        if (this.inventory.resources && this.inventory.resources.length > 0) {
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
                return window.ms365JobSelection.globalCheckState(this.treesBySection, this.selection);
            },

            toggleSelectAllInventory() {
                if (!window.ms365JobSelection) {
                    return;
                }
                this.selection = window.ms365JobSelection.toggleGlobalSelect(
                    this.treesBySection,
                    this.selection,
                );
                this.syncSelectionPayload();
            },

            selectionSummaryRowCount() {
                if (!window.ms365JobSelection) return 0;
                return window.ms365JobSelection.summaryRowCount(this.selectionSummaryGroups);
            },

            rebuildTrees() {
                if (!window.ms365JobSelection) return;
                this.treesBySection = window.ms365JobSelection.buildAllTrees(this.inventory);
            },

            applySavedSelection() {
                if (!window.ms365JobSelection) return;
                this.selection = window.ms365JobSelection.hydrateFromSavedJob(
                    this.inventory,
                    this.savedSelectionIds,
                    this.scopeOverrides,
                );
                this.syncSelectionPayload();
            },

            syncSelectionPayload() {
                if (!window.ms365JobSelection) return;
                const payload = window.ms365JobSelection.buildSavePayload(
                    this.inventory,
                    this.treesBySection,
                    this.selection,
                );
                this.scopeOverrides = payload.scope_overrides;
                this.savedSelectionIds = payload.selected_resource_ids;
                this.selectionSummaryGroups = window.ms365JobSelection.selectionSummary(
                    this.inventory,
                    this.treesBySection,
                    this.selection,
                );
                this.refreshPlan();
            },

            async refreshPlan() {
                if (!this.backupUserId || this.savedSelectionIds.length === 0) {
                    this.planWarnings = [];
                    this.planSummary = { runnable: 0, deferred: 0 };
                    return;
                }
                try {
                    const body = new URLSearchParams({
                        user_id: this.backupUserId,
                        selected_resource_ids: JSON.stringify(this.savedSelectionIds),
                        scope_overrides: JSON.stringify(this.scopeOverrides || {}),
                    });
                    const res = await fetch(`${apiBase()}ms365_job_plan.php`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    });
                    const data = await res.json();
                    if (data.status === 'success' && data.plan) {
                        this.planWarnings = data.plan.warnings || [];
                        this.planSummary = data.plan.summary || { runnable: 0, deferred: 0 };
                    }
                } catch (e) {
                    /* keep last plan */
                }
            },

            sectionHasNodes(sectionKey) {
                const nodes = this.treesBySection[sectionKey] || [];
                return nodes.some((n) => n.depth === 0);
            },

            visibleSectionNodes(sectionKey) {
                const sel = window.ms365JobSelection;
                if (!sel) return [];
                const nodes = this.treesBySection[sectionKey] || [];
                return sel.visibleNodes(nodes, this.selection, this.searchQuery, this.expandedKeys);
            },

            toggleExpandNode(sectionKey, node) {
                if (!node.hasChildren) return;
                this.expandedKeys[node.key] = !this.expandedKeys[node.key];
            },

            isNodeExpanded(node) {
                return !!this.expandedKeys[node.key];
            },

            nodeCheckState(sectionKey, node) {
                const sel = window.ms365JobSelection;
                if (!sel) return 'unchecked';
                const nodes = this.treesBySection[sectionKey] || [];
                if (node.kind === 'parent') {
                    return sel.parentCheckState(nodes, this.selection, node);
                }
                return sel.isChecked(this.selection, node.key) ? 'checked' : 'unchecked';
            },

            toggleTreeNode(sectionKey, node) {
                const sel = window.ms365JobSelection;
                if (!sel) return;
                const nodes = this.treesBySection[sectionKey] || [];
                sel.toggleNode(nodes, this.selection, node);
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
                const nodes = this.treesBySection[sectionKey] || [];
                nodes.forEach((node) => {
                    if (!sel.isChecked(this.selection, node.key)) return;
                    if (node.kind === 'capability' && item.subtitle === node.label) {
                        const parent = nodes.find((n) => n.key === node.parentKey);
                        if (parent && parent.label === item.label) {
                            delete this.selection[node.key];
                        }
                    } else if ((node.kind === 'leaf' || node.kind === 'resource_child') && node.label === item.subtitle && item.label === node.label) {
                        delete this.selection[node.key];
                    } else if (node.kind === 'parent' && item.subtitle === 'All components' && node.label === item.label) {
                        sel.toggleNode(nodes, this.selection, node);
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
                this.saving = true;
                try {
                    const body = new URLSearchParams({
                        user_id: this.backupUserId,
                        name: this.jobName,
                        schedule_frequency: this.scheduleFrequency,
                        retention_tier: this.retentionTier,
                        selected_resource_ids: JSON.stringify(this.savedSelectionIds),
                        scope_overrides: JSON.stringify(this.scopeOverrides || {}),
                    });
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
            if (data.users.length === 1) {
                userId = data.users[0].public_id || String(data.users[0].id);
            } else {
                const names = data.users.map((u, i) => `${i + 1}. ${u.username}`).join('\n');
                const pick = window.prompt(`Select backup user number for this Microsoft 365 job:\n${names}`);
                const idx = parseInt(pick, 10) - 1;
                if (Number.isNaN(idx) || idx < 0 || idx >= data.users.length) {
                    return;
                }
                userId = data.users[idx].public_id || String(data.users[idx].id);
            }
            openMs365JobWizard({ backupUserId: userId });
        } catch (e) {
            toast('error', 'Could not load backup users.');
        }
    };

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

        if (!uid) {
            return;
        }

        window.ms365WizardState.backupUserId = uid;
        const connectOk = params.get('connect_ok') === '1';
        const connectError = params.get('connect_error') || '';

        setTimeout(() => {
            openMs365JobWizard({ backupUserId: uid, step: connectOk ? 2 : 1 });
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
