(function () {
    'use strict';

    const STEP_LABELS = ['Snapshot', 'Restore method', 'Browse & select', 'Destination', 'Review'];

    const RESTORE_SECTIONS = [
        { key: 'users', label: 'Users & mailboxes' },
        { key: 'sharepoint', label: 'SharePoint sites' },
        { key: 'teams', label: 'Teams' },
        { key: 'groups', label: 'Microsoft 365 groups' },
        { key: 'planner', label: 'Planner' },
        { key: 'onenote', label: 'OneNote' },
        { key: 'directory', label: 'Tenant metadata' },
    ];

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

        function formatFileSize(bytes) {
            const n = Number(bytes) || 0;
            if (n <= 0) return '';
            if (n < 1024) return n + ' B';
            if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
            if (n < 1024 * 1024 * 1024) return (n / (1024 * 1024)).toFixed(1) + ' MB';
            return (n / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
        }

        function mapEntryToNode(e, parent, index, depth) {
            const key = parent
                ? parent.key + '-c-' + index + '-' + (e.path || e.name)
                : 'root-' + index;
            const sectionKey = e.section_key || (parent ? parent.section_key : 'users');
            const isFile = (e.type || '') === 'file';
            const sizeLabel = isFile ? formatFileSize(e.size) : '';
            const subtitle = e.subtitle || sizeLabel || (isFile ? 'File' : '');
            return {
                key,
                name: e.name,
                label: e.label || e.name,
                subtitle,
                path: e.path || '',
                type: e.type || (e.has_children ? 'folder' : 'file'),
                has_children: !!e.has_children,
                size: Number(e.size) || 0,
                manifest_id: e.manifest_id || (parent && parent.manifest_id) || '',
                child_run_id: e.child_run_id || (parent && parent.child_run_id) || '',
                parentKey: parent ? parent.key : '',
                section_key: sectionKey,
                depth,
                expanded: false,
                loaded: false,
                loading: false,
            };
        }

        function emptyFilesPlaceholder(node) {
            const isSharePointFiles = node.label === 'Files' && node.subtitle === 'Document libraries';
            if (node.label !== 'OneDrive' && !isSharePointFiles) {
                return null;
            }
            return {
                key: node.key + '-empty',
                name: '',
                label: 'No files in this snapshot',
                subtitle: isSharePointFiles
                    ? 'SharePoint document libraries were not captured in this snapshot. Run a new backup with Files selected.'
                    : 'OneDrive was not cataloged in this backup. Run a new backup with worker 0.1.25 or later.',
                path: '',
                type: 'info',
                has_children: false,
                size: 0,
                manifest_id: node.manifest_id,
                child_run_id: node.child_run_id,
                parentKey: node.key,
                section_key: node.section_key,
                depth: node.depth + 1,
                expanded: false,
                loaded: true,
                loading: false,
            };
        }

        return {
            step: 0,
            stepLabels: STEP_LABELS,
            restoreSections: RESTORE_SECTIONS,
            loading: false,
            starting: false,
            snapshot: null,
            treeNodes: [],
            treeSearch: '',
            selectedItems: [],
            inventoryResources: [],
            targetResource: null,
            restoreMode: 'tenant',
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
                this.restoreMode = 'tenant';
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
                if (this.restoreMode === 'archive' && n === 4) {
                    return;
                }
                this.step = n;
                if (n === 3 && this.treeNodes.length === 0) {
                    this.loadTreeRoots();
                }
                if (n === 4 && this.restoreMode === 'tenant' && this.inventoryResources.length === 0) {
                    this.loadInventory();
                }
            },

            prevStep() {
                if (this.step <= 1) {
                    return 1;
                }
                let prev = this.step - 1;
                if (this.restoreMode === 'archive' && prev === 4) {
                    prev = 3;
                }
                return prev;
            },

            nextStep() {
                if (this.step >= 5) {
                    return;
                }
                let next = this.step + 1;
                if (this.restoreMode === 'archive' && next === 4) {
                    next = 5;
                }
                this.goToStep(next);
            },

            canProceed() {
                if (this.step === 1) return !!this.snapshot;
                if (this.step === 2) return this.restoreMode === 'tenant' || this.restoreMode === 'archive';
                if (this.step === 3) return this.selectedItems.length > 0;
                if (this.step === 4) return this.restoreMode === 'archive' || !!this.targetResource;
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
                    const entries = await this.fetchBrowseEntries(node);
                    const children = entries.map((e, i) => mapEntryToNode(e, node, i, node.depth + 1));
                    const emptyFiles = emptyFilesPlaceholder(node);
                    if (children.length === 0 && emptyFiles) {
                        children.push(emptyFiles);
                    }
                    const idx = this.treeNodes.findIndex((n) => n.key === node.key);
                    this.treeNodes.splice(idx + 1, 0, ...children);
                    node.expanded = true;
                    return;
                }
                node.loading = true;
                try {
                    const entries = await this.fetchBrowseEntries(node);
                    const children = entries.map((e, i) => mapEntryToNode(e, node, i, node.depth + 1));
                    const emptyFiles = emptyFilesPlaceholder(node);
                    if (children.length === 0 && emptyFiles) {
                        children.push(emptyFiles);
                    }
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

            sectionHasNodes(sectionKey) {
                return this.visibleSectionNodes(sectionKey).length > 0;
            },

            visibleSectionNodes(sectionKey) {
                const nodes = this.filteredTreeNodes();
                const q = (this.treeSearch || '').toLowerCase().trim();
                return nodes.filter((node) => {
                    if (node.section_key !== sectionKey) return false;
                    if (node.depth === 0) return true;
                    if (q) return true;
                    let parentKey = node.parentKey;
                    while (parentKey) {
                        const parent = nodes.find((n) => n.key === parentKey);
                        if (!parent) break;
                        if (!parent.expanded) return false;
                        parentKey = parent.parentKey;
                    }
                    return true;
                });
            },

            selectedSummaryGroups() {
                const groups = {};
                this.selectedItems.forEach((item) => {
                    const section = item.section_label || 'Selected items';
                    if (!groups[section]) {
                        groups[section] = [];
                    }
                    groups[section].push(item);
                });
                return Object.keys(groups).map((section) => ({
                    section,
                    items: groups[section],
                }));
            },

            selectionKey(node) {
                return [node.child_run_id, node.manifest_id, node.path, node.type].join('|');
            },

            isSelected(node) {
                const key = this.selectionKey(node);
                return this.selectedItems.some((s) => s.key === key);
            },

            toggleSelect(node) {
                if (node.type === 'info') return;
                const key = this.selectionKey(node);
                const idx = this.selectedItems.findIndex((s) => s.key === key);
                if (idx >= 0) {
                    this.selectedItems.splice(idx, 1);
                    return;
                }
                const item = {
                    key,
                    label: node.label || node.name,
                    subtitle: node.subtitle || (node.type === 'file' ? formatFileSize(node.size) : ''),
                    section_label: this.sectionLabelForNode(node),
                    child_run_id: node.child_run_id,
                    manifest_id: node.manifest_id,
                    path: node.type === 'file' ? (node.path || '') : ((node.path && node.path.endsWith('.json')) ? node.path : ''),
                    path_prefix: node.type === 'file' ? '' : (node.has_children ? (node.path ? node.path + '/' : '') : (node.path || '')),
                    type: node.type,
                };
                if (!item.path && !item.path_prefix && node.path) {
                    item.path_prefix = node.path;
                }
                this.selectedItems.push(item);
            },

            removeSelected(idx) {
                this.selectedItems.splice(idx, 1);
            },

            sectionLabelForNode(node) {
                const section = RESTORE_SECTIONS.find((s) => s.key === (node.section_key || 'users'));
                return section ? section.label : 'Selected items';
            },

            async loadInventory() {
                try {
                    const params = new URLSearchParams({ user_id: this.backupUserId });
                    const res = await fetch(apiBase() + 'ms365_inventory.php?' + params.toString());
                    const data = await res.json();
                    if (data.status !== 'success') throw new Error(data.message || 'Inventory failed');
                    const resources = data.resources || data.inventory?.resources || [];
                    this.inventoryResources = resources.filter((res) => {
                        if (res.resource_type !== 'sharepoint_site') {
                            return true;
                        }
                        if (res.show_in_sharepoint_section === false) {
                            return false;
                        }
                        if (res.infrastructure_site === true) {
                            return false;
                        }
                        if (res.workload_group_connected === true || res.group_connected === true) {
                            return false;
                        }
                        if (res.channel_connected === true) {
                            return false;
                        }
                        return true;
                    });
                } catch (e) {
                    toast('error', e.message || 'Failed to load targets');
                }
            },

            buildSelectionPayload() {
                const snapshot = this.snapshot || {};
                const target = this.targetResource;
                const isArchive = this.restoreMode === 'archive';
                return {
                    snapshot_batch_run_id: String(snapshot.batch_run_id || snapshot.id || ''),
                    restore_mode: this.restoreMode,
                    conflict_policy: 'skip_duplicates',
                    items: this.selectedItems.map((s) => ({
                        child_run_id: String(s.child_run_id || ''),
                        manifest_id: String(s.manifest_id || ''),
                        path: String(s.path || ''),
                        path_prefix: String(s.path_prefix || ''),
                        type: String(s.type || ''),
                    })),
                    targets: isArchive ? [] : (target ? [{
                        resource_id: String(target.id || ''),
                        graph_id: String(target.graph_id || (target.id || '').replace(/^[^:]+:/, '')),
                        resource_type: String(target.resource_type || 'user'),
                    }] : []),
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
                    if (this.restoreMode === 'tenant' && !selection.targets.length) {
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
