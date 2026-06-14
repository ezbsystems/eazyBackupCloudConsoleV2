(function () {
    'use strict';

    const STEP_LABELS = ['Snapshot', 'Browse & select', 'Destination', 'Review'];

    window.ms365RestoreWizardState = {
        backupUserId: '',
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

    window.ms365RestoreWizardApp = function () {
        const browseCache = new Map();

        function browseCacheKey(batchRunId, manifestId, childRunId, path) {
            return [batchRunId, manifestId, childRunId, path || ''].join('|');
        }

        function mapEntryToNode(e, parent, index, depth) {
            const key = parent
                ? parent.key + '-c-' + index + '-' + (e.path || e.name)
                : 'root-' + index;
            return {
                key,
                name: e.name,
                label: e.label || e.name,
                subtitle: e.subtitle || '',
                path: e.path || '',
                type: e.type || (e.has_children ? 'folder' : 'file'),
                has_children: !!e.has_children,
                manifest_id: (parent && parent.manifest_id) || e.manifest_id || '',
                child_run_id: (parent && parent.child_run_id) || e.child_run_id || '',
                parentKey: parent ? parent.key : '',
                depth,
                expanded: false,
                loaded: false,
                loading: false,
            };
        }

        return {
            step: 0,
            stepLabels: STEP_LABELS,
            loading: false,
            starting: false,
            snapshot: null,
            treeNodes: [],
            treeSearch: '',
            selectedItems: [],
            inventoryResources: [],
            targetResource: null,
            backupUserId: '',
            jobId: '',

            init() {
                this.backupUserId = window.ms365RestoreWizardState.backupUserId || '';
                this.jobId = window.ms365RestoreWizardState.jobId || '';
            },

            open(snapshot, backupUserId, jobId) {
                this.snapshot = snapshot;
                this.backupUserId = backupUserId || this.backupUserId;
                this.jobId = jobId || this.jobId;
                this.step = 1;
                this.selectedItems = [];
                this.treeNodes = [];
                this.targetResource = null;
                this.treeSearch = '';
                document.getElementById('ms365RestoreWizardModal').classList.remove('hidden');
                this.loadTreeRoots();
            },

            close() {
                document.getElementById('ms365RestoreWizardModal').classList.add('hidden');
                this.step = 0;
                this.snapshot = null;
            },

            snapshotTitle() {
                if (!this.snapshot) return 'Snapshot';
                return this.snapshot.snapshot_label || this.snapshot.finished_at || 'Snapshot';
            },

            snapshotJobName() {
                return (this.snapshot && this.snapshot.job_name) || '';
            },

            snapshotWorkloadCount() {
                const runs = this.snapshot && this.snapshot.child_runs;
                return Array.isArray(runs) ? runs.length : 0;
            },

            goToStep(n) {
                this.step = n;
                if (n === 2 && this.treeNodes.length === 0) {
                    this.loadTreeRoots();
                }
                if (n === 3 && this.inventoryResources.length === 0) {
                    this.loadInventory();
                }
            },

            nextStep() {
                if (this.step < 4) {
                    this.goToStep(this.step + 1);
                }
            },

            canProceed() {
                if (this.step === 1) return !!this.snapshot;
                if (this.step === 2) return this.selectedItems.length > 0;
                if (this.step === 3) return !!this.targetResource;
                return true;
            },

            async fetchBrowseEntries(node) {
                const batchRunId = this.snapshot.batch_run_id || this.snapshot.id;
                const manifestId = node ? (node.manifest_id || '') : '';
                const childRunId = node ? (node.child_run_id || '') : '';
                const path = node ? (node.path || '') : '';
                const cacheKey = browseCacheKey(batchRunId, manifestId, childRunId, path);
                if (browseCache.has(cacheKey)) {
                    return browseCache.get(cacheKey);
                }
                const params = new URLSearchParams({
                    user_id: this.backupUserId,
                    batch_run_id: batchRunId,
                });
                if (manifestId) params.set('manifest_id', manifestId);
                if (childRunId) params.set('child_run_id', childRunId);
                if (path) params.set('path', path);
                const res = await fetch(apiBase() + 'ms365_restore_browse.php?' + params.toString());
                const data = await res.json();
                if (data.status !== 'success') throw new Error(data.message || 'Browse failed');
                const entries = data.entries || [];
                browseCache.set(cacheKey, entries);
                return entries;
            },

            async loadTreeRoots() {
                if (!this.snapshot || !this.backupUserId) return;
                this.loading = true;
                try {
                    const entries = await this.fetchBrowseEntries(null);
                    this.treeNodes = entries.map((e, i) => mapEntryToNode(e, null, i, 0));
                } catch (e) {
                    toast('error', e.message || 'Failed to load snapshot tree');
                }
                this.loading = false;
            },

            async toggleExpand(node) {
                if (!node.has_children || node.loading) return;
                if (node.expanded) {
                    node.expanded = false;
                    this.pruneChildren(node);
                    return;
                }
                if (node.loaded) {
                    node.expanded = true;
                    return;
                }
                node.loading = true;
                try {
                    const entries = await this.fetchBrowseEntries(node);
                    const children = entries.map((e, i) => mapEntryToNode(e, node, i, node.depth + 1));
                    const idx = this.treeNodes.findIndex((n) => n.key === node.key);
                    this.treeNodes.splice(idx + 1, 0, ...children);
                    node.loaded = true;
                    node.expanded = true;
                } catch (e) {
                    toast('error', e.message || 'Failed to expand folder');
                }
                node.loading = false;
            },

            pruneChildren(node) {
                const toRemove = this.treeNodes.filter((n) => n.key.startsWith(node.key + '-c-'));
                toRemove.forEach((n) => {
                    const i = this.treeNodes.findIndex((x) => x.key === n.key);
                    if (i >= 0) this.treeNodes.splice(i, 1);
                });
            },

            filteredTreeNodes() {
                const q = (this.treeSearch || '').toLowerCase().trim();
                if (!q) return this.treeNodes;
                return this.treeNodes.filter((n) => {
                    const label = (n.label || n.name || '').toLowerCase();
                    const subtitle = (n.subtitle || '').toLowerCase();
                    return label.includes(q) || subtitle.includes(q);
                });
            },

            selectionKey(node) {
                return [node.child_run_id, node.manifest_id, node.path, node.type].join('|');
            },

            isSelected(node) {
                const key = this.selectionKey(node);
                return this.selectedItems.some((s) => s.key === key);
            },

            toggleSelect(node) {
                const key = this.selectionKey(node);
                const idx = this.selectedItems.findIndex((s) => s.key === key);
                if (idx >= 0) {
                    this.selectedItems.splice(idx, 1);
                    return;
                }
                const item = {
                    key,
                    label: node.label || node.name,
                    subtitle: node.subtitle || '',
                    child_run_id: node.child_run_id,
                    manifest_id: node.manifest_id,
                    path: node.type === 'file' || (node.path && node.path.endsWith('.json')) ? node.path : '',
                    path_prefix: node.has_children && node.type !== 'file' ? (node.path ? node.path + '/' : '') : '',
                    type: node.type,
                };
                if (!item.path && !item.path_prefix) {
                    item.path_prefix = node.path || '';
                }
                this.selectedItems.push(item);
            },

            removeSelected(idx) {
                this.selectedItems.splice(idx, 1);
            },

            async loadInventory() {
                try {
                    const params = new URLSearchParams({ user_id: this.backupUserId });
                    const res = await fetch(apiBase() + 'ms365_inventory.php?' + params.toString());
                    const data = await res.json();
                    if (data.status !== 'success') throw new Error(data.message || 'Inventory failed');
                    this.inventoryResources = data.resources || data.inventory?.resources || [];
                } catch (e) {
                    toast('error', e.message || 'Failed to load targets');
                }
            },

            buildSelectionPayload() {
                const snapshot = this.snapshot || {};
                const target = this.targetResource;
                return {
                    snapshot_batch_run_id: String(snapshot.batch_run_id || snapshot.id || ''),
                    conflict_policy: 'skip_duplicates',
                    items: this.selectedItems.map((s) => ({
                        child_run_id: String(s.child_run_id || ''),
                        manifest_id: String(s.manifest_id || ''),
                        path: String(s.path || ''),
                        path_prefix: String(s.path_prefix || ''),
                        type: String(s.type || ''),
                    })),
                    targets: target ? [{
                        resource_id: String(target.id || ''),
                        graph_id: String(target.graph_id || (target.id || '').replace(/^[^:]+:/, '')),
                        resource_type: String(target.resource_type || 'user'),
                    }] : [],
                };
            },

            async startRestore() {
                if (this.starting) return;
                this.starting = true;
                try {
                    const selection = this.buildSelectionPayload();
                    if (!selection.snapshot_batch_run_id) {
                        throw new Error('Restore snapshot is missing. Go back to step 1 and try again.');
                    }
                    if (!selection.items.length) {
                        throw new Error('Select at least one item to restore.');
                    }
                    if (!selection.targets.length) {
                        throw new Error('Select a restore destination.');
                    }
                    const res = await fetch(apiBase() + 'ms365_restore_start.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            user_id: String(this.backupUserId || ''),
                            job_id: String(this.jobId || ''),
                            selection,
                        }),
                    });
                    const data = await res.json();
                    if (data.status !== 'success') throw new Error(data.message || 'Restore start failed');
                    this.close();
                    const runId = data.batch_run_id;
                    const jobId = data.job_id || this.jobId;
                    window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=live&job_id='
                        + encodeURIComponent(jobId) + '&run_id=' + encodeURIComponent(runId);
                } catch (e) {
                    toast('error', e.message || 'Failed to start restore');
                }
                this.starting = false;
            },
        };
    };

    window.openMs365RestoreWizard = function (snapshot, backupUserId, jobId) {
        window.ms365RestoreWizardState.backupUserId = backupUserId || '';
        window.ms365RestoreWizardState.jobId = jobId || '';
        const modal = document.getElementById('ms365RestoreWizardModal');
        if (!modal) return;
        const app = modal._x_dataStack && modal._x_dataStack[0];
        if (app && typeof app.open === 'function') {
            app.open(snapshot, backupUserId, jobId);
        } else {
            document.dispatchEvent(new CustomEvent('ms365-restore-wizard-open', {
                detail: { snapshot, backupUserId, jobId },
            }));
        }
    };

    document.addEventListener('alpine:init', () => {
        document.addEventListener('ms365-restore-wizard-open', (ev) => {
            const modal = document.getElementById('ms365RestoreWizardModal');
            const d = ev.detail || {};
            const app = modal && modal._x_dataStack && modal._x_dataStack[0];
            if (app) app.open(d.snapshot, d.backupUserId, d.jobId);
        });
    });
})();
