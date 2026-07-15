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
        const BROWSE_PAGE_SIZE = 500;

        function browseCacheKey(batchRunId, manifestId, childRunId, path, offset) {
            return [batchRunId, manifestId, childRunId, path || '', String(offset)].join('|');
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

        function mapLoadMoreNode(parent, page) {
            const loaded = page.offset + page.entries.length;
            const remaining = Math.max(0, page.total_count - loaded);
            return {
                key: parent.key + '-load-more-' + loaded,
                type: 'load_more',
                parentKey: parent.key,
                section_key: parent.section_key || 'users',
                label: 'Load more (' + remaining.toLocaleString() + ' remaining)',
                browseOffset: loaded,
                depth: parent.depth + 1,
                loading: false,
                has_children: false,
            };
        }

        function appendBrowseChildren(node, page, startIndex) {
            const children = page.entries.map((e, i) => mapEntryToNode(e, node, startIndex + i, node.depth + 1));
            const emptyFiles = emptyFilesPlaceholder(node);
            if (children.length === 0 && emptyFiles) {
                children.push(emptyFiles);
            }
            if (page.has_more) {
                children.push(mapLoadMoreNode(node, page));
            }
            return children;
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
            destinationMode: 'original',
            destinationError: '',
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
                this.destinationMode = 'original';
                this.destinationError = '';
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
                if (n === 4 && this.restoreMode === 'tenant') {
                    this.syncDestinationModeOnSelectionChange();
                    this.validateOriginalDestinations();
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
                if (this.step === 4) {
                    if (this.restoreMode === 'archive') return true;
                    if (this.destinationMode === 'original') {
                        return this.validateOriginalDestinations();
                    }
                    return this.canUseAlternateDestination() && !!this.targetResource;
                }
                return true;
            },

            async fetchBrowseEntries(node, offset = 0) {
                const batchRunId = this.snapshot.batch_run_id || this.snapshot.id;
                const manifestId = node ? (node.manifest_id || '') : '';
                const childRunId = node ? (node.child_run_id || '') : '';
                const path = node ? (node.path || '') : '';
                const cacheKey = browseCacheKey(batchRunId, manifestId, childRunId, path, offset);
                if (browseCache.has(cacheKey)) {
                    return browseCache.get(cacheKey);
                }
                const params = new URLSearchParams({
                    user_id: this.backupUserId,
                    batch_run_id: batchRunId,
                    limit: String(BROWSE_PAGE_SIZE),
                    offset: String(offset),
                });
                if (manifestId) params.set('manifest_id', manifestId);
                if (childRunId) params.set('child_run_id', childRunId);
                if (path) params.set('path', path);
                const res = await fetch(apiBase() + 'ms365_restore_browse.php?' + params.toString());
                const data = await res.json();
                if (data.status !== 'success') throw new Error(data.message || 'Browse failed');
                const page = {
                    entries: data.entries || [],
                    total_count: Number(data.total_count) || 0,
                    has_more: !!data.has_more,
                    offset: Number(data.offset) || offset,
                    limit: Number(data.limit) || BROWSE_PAGE_SIZE,
                };
                browseCache.set(cacheKey, page);
                return page;
            },

            async loadMoreBrowse(loadMoreNode) {
                if (loadMoreNode.loading) return;
                const parent = this.treeNodes.find((n) => n.key === loadMoreNode.parentKey);
                if (!parent) return;
                loadMoreNode.loading = true;
                try {
                    const page = await this.fetchBrowseEntries(parent, loadMoreNode.browseOffset);
                    const lmIdx = this.treeNodes.findIndex((n) => n.key === loadMoreNode.key);
                    if (lmIdx < 0) return;
                    this.treeNodes.splice(lmIdx, 1);
                    const childCount = this.treeNodes.filter((n) => n.parentKey === parent.key).length;
                    const newChildren = appendBrowseChildren(parent, page, childCount);
                    this.treeNodes.splice(lmIdx, 0, ...newChildren);
                } catch (e) {
                    toast('error', e.message || 'Failed to load more items');
                }
                loadMoreNode.loading = false;
            },

            async loadTreeRoots() {
                if (!this.snapshot || !this.backupUserId) return;
                this.loading = true;
                try {
                    const page = await this.fetchBrowseEntries(null);
                    this.treeNodes = page.entries.map((e, i) => mapEntryToNode(e, null, i, 0));
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
                    const page = await this.fetchBrowseEntries(node);
                    const children = appendBrowseChildren(node, page, 0);
                    const idx = this.treeNodes.findIndex((n) => n.key === node.key);
                    this.treeNodes.splice(idx + 1, 0, ...children);
                    node.expanded = true;
                    return;
                }
                node.loading = true;
                try {
                    const page = await this.fetchBrowseEntries(node);
                    const children = appendBrowseChildren(node, page, 0);
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
                const toRemove = this.treeNodes.filter((n) => n.key.startsWith(node.key + '-c-') || n.parentKey === node.key || (n.type === 'load_more' && n.parentKey === node.key));
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
                this.syncDestinationModeOnSelectionChange();
            },

            removeSelected(idx) {
                this.selectedItems.splice(idx, 1);
                this.syncDestinationModeOnSelectionChange();
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

            effectivePath(item) {
                const path = String(item.path || '').trim();
                if (path) return path;
                return String(item.path_prefix || '').trim();
            },

            storageSafeId(id) {
                const out = String(id || '').replace(/[^a-zA-Z0-9._-]/g, '_');
                return out || 'unknown';
            },

            classifyItemPath(path) {
                const normalized = String(path || '').trim().replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
                if (!normalized) return '';
                const lower = normalized.toLowerCase();

                if (/\/users\/[^/]+\/onedrive\/content(?:\/|$)/.test(lower)) return 'onedrive';
                if (/\/users\/[^/]+\/(?:mail|calendar|calendars|contacts|tasks)(?:\/|$)/.test(lower)) return 'mailbox';
                if (/\/groups\/[^/]+\/(?:mail|calendar|calendars)(?:\/|$)/.test(lower)) return 'groups';
                if (/\/sites\/[^/]+\/(?:drives|lists)(?:\/|$)/.test(lower)) return 'sharepoint';
                if (/\/teams\/[^/]+(?:\/|$)/.test(lower)) return 'teams';
                if (/\/planner(?:\/|$)/.test(lower)) return 'planner';
                if (/\/onenote(?:\/|$)/.test(lower)) return 'onenote';
                if (/\/drives\/[^/]+\/content(?:\/|$)/.test(lower)) return 'onedrive';
                return '';
            },

            classifyItem(item) {
                return this.classifyItemPath(this.effectivePath(item));
            },

            selectionClasses() {
                const classes = new Set();
                this.selectedItems.forEach((item) => {
                    const className = this.classifyItem(item);
                    if (className) classes.add(className);
                });
                return Array.from(classes);
            },

            canUseAlternateDestination() {
                return this.selectionClasses().length === 1;
            },

            compatibleResourceTypes(selectionClass) {
                const map = {
                    mailbox: ['user', 'mailbox'],
                    onedrive: ['user', 'user_onedrive'],
                    sharepoint: ['sharepoint_site'],
                    teams: ['team'],
                    groups: ['m365_group'],
                    planner: ['planner_plan'],
                    onenote: ['onenote_notebook'],
                };
                return map[selectionClass] || [];
            },

            resourceTypeForClass(className) {
                const map = {
                    mailbox: 'user',
                    onedrive: 'user',
                    sharepoint: 'sharepoint_site',
                    teams: 'team',
                    groups: 'm365_group',
                    planner: 'planner_plan',
                    onenote: 'onenote_notebook',
                };
                return map[className] || 'user';
            },

            makeResourceId(resourceType, graphId) {
                return String(resourceType || 'user') + ':' + String(graphId || '');
            },

            parsePathIdentity(path, className) {
                const normalized = String(path || '').trim().replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');

                let match;
                if (className === 'mailbox' && (match = normalized.match(/\/users\/([^/]+)\/(?:mail|calendar|calendars|contacts|tasks)(?:\/|$)/i))) {
                    return { identity_key: 'user:' + match[1], graph_id: match[1] };
                }
                if (className === 'onedrive') {
                    if ((match = normalized.match(/\/users\/([^/]+)\/onedrive\/content(?:\/|$)/i))) {
                        return { identity_key: 'user:' + match[1], graph_id: match[1] };
                    }
                    if ((match = normalized.match(/\/drives\/([^/]+)\/content(?:\/|$)/i))) {
                        return { identity_key: 'drive:' + match[1], graph_id: match[1], drive_id: match[1] };
                    }
                }
                if (className === 'sharepoint' && (match = normalized.match(/\/sites\/([^/]+)\//i))) {
                    const identity = { identity_key: 'site:' + match[1], graph_id: match[1] };
                    const driveMatch = normalized.match(/\/drives\/([^/]+)\//i);
                    if (driveMatch) identity.drive_id = driveMatch[1];
                    return identity;
                }
                if (className === 'teams' && (match = normalized.match(/\/teams\/([^/]+)(?:\/|$)/i))) {
                    return { identity_key: 'team:' + match[1], graph_id: match[1] };
                }
                if (className === 'groups' && (match = normalized.match(/\/groups\/([^/]+)\/(?:mail|calendar|calendars)(?:\/|$)/i))) {
                    return { identity_key: 'group:' + match[1], graph_id: match[1] };
                }
                if (className === 'planner' && (match = normalized.match(/\/planner\/([^/]+)(?:\/|$)/i))) {
                    return { identity_key: 'planner:' + match[1], graph_id: match[1] };
                }
                if (className === 'onenote' && (match = normalized.match(/\/onenote\/([^/]+)(?:\/|$)/i))) {
                    return { identity_key: 'onenote:' + match[1], graph_id: match[1] };
                }
                throw new Error('Could not determine the original restore location for one or more selected items.');
            },

            resolveGraphId(segment, className) {
                const trimmed = String(segment || '').trim();
                if (!trimmed) {
                    throw new Error('Could not determine the original restore location for one or more selected items.');
                }
                if (className === 'mailbox' || className === 'onedrive') {
                    return trimmed;
                }
                const allowed = this.compatibleResourceTypes(className);
                for (const resource of this.inventoryResources) {
                    const resourceType = String(resource.resource_type || '').toLowerCase();
                    if (!allowed.includes(resourceType)) continue;
                    const graphId = String(resource.graph_id || '');
                    if (graphId === trimmed) return graphId;
                    if (this.storageSafeId(graphId) === trimmed) return graphId;
                    const resourceId = String(resource.id || '');
                    const graphFromId = resourceId.includes(':') ? resourceId.split(':').slice(1).join(':') : '';
                    if (graphFromId === trimmed) return graphId || graphFromId;
                    if (this.storageSafeId(graphFromId) === trimmed) return graphId || graphFromId;
                }
                return trimmed;
            },

            buildTargetFromParsed(parsed, className) {
                const graphId = this.resolveGraphId(parsed.graph_id, className);
                const resourceType = this.resourceTypeForClass(className);
                const target = {
                    resource_id: this.makeResourceId(resourceType, graphId),
                    graph_id: graphId,
                    resource_type: resourceType,
                };
                if (parsed.drive_id) {
                    target.drive_id = String(parsed.drive_id);
                }
                return target;
            },

            derivedOriginalTargets() {
                const targetsByKey = {};
                this.selectedItems.forEach((item) => {
                    const path = this.effectivePath(item);
                    const className = this.classifyItemPath(path);
                    if (!className) {
                        throw new Error('Could not determine the original restore location for one or more selected items.');
                    }
                    const parsed = this.parsePathIdentity(path, className);
                    const childRunId = String(item.child_run_id || '').trim();
                    const targetKey = childRunId ? childRunId + '|' + parsed.identity_key : parsed.identity_key;
                    if (targetsByKey[targetKey]) return;
                    const target = this.buildTargetFromParsed(parsed, className);
                    if (childRunId) target.child_run_id = childRunId;
                    targetsByKey[targetKey] = target;
                });
                const targets = Object.values(targetsByKey);
                if (!targets.length) {
                    throw new Error('Could not determine restore destinations for the selected items.');
                }
                return targets;
            },

            derivedOriginalTargetsSafe() {
                try {
                    return this.derivedOriginalTargets();
                } catch (_e) {
                    return [];
                }
            },

            validateOriginalDestinations() {
                try {
                    this.derivedOriginalTargets();
                    this.destinationError = '';
                    return true;
                } catch (e) {
                    this.destinationError = e.message || 'Could not determine original restore locations.';
                    return false;
                }
            },

            syncDestinationModeOnSelectionChange() {
                if (!this.canUseAlternateDestination()) {
                    if (this.destinationMode === 'alternate') {
                        this.destinationMode = 'original';
                        this.targetResource = null;
                    }
                }
                if (this.destinationMode === 'original') {
                    this.validateOriginalDestinations();
                } else {
                    this.destinationError = '';
                }
            },

            alternateTargetsFiltered() {
                const classes = this.selectionClasses();
                if (classes.length !== 1) return [];
                const allowed = this.compatibleResourceTypes(classes[0]);
                return this.inventoryResources.filter((resource) => allowed.includes(String(resource.resource_type || '').toLowerCase()));
            },

            targetDisplayName(target) {
                const graphId = String(target.graph_id || '');
                const resourceId = String(target.resource_id || '');
                const match = this.inventoryResources.find((resource) => {
                    const invGraphId = String(resource.graph_id || '');
                    const invId = String(resource.id || '');
                    if (graphId && (invGraphId === graphId || this.storageSafeId(invGraphId) === graphId)) return true;
                    if (resourceId && invId === resourceId) return true;
                    return false;
                });
                if (match) return match.display_name || match.id;
                return graphId || resourceId || 'Unknown destination';
            },

            destinationReviewSummary() {
                if (this.destinationMode === 'alternate') {
                    return this.targetResource ? (this.targetResource.display_name || this.targetResource.id) : '—';
                }
                try {
                    return this.derivedOriginalTargets().map((target) => this.targetDisplayName(target)).join(', ');
                } catch (_e) {
                    return '—';
                }
            },

            buildSelectionPayload() {
                const snapshot = this.snapshot || {};
                const isArchive = this.restoreMode === 'archive';
                let targets = [];
                if (!isArchive) {
                    if (this.destinationMode === 'alternate') {
                        const target = this.targetResource;
                        targets = target ? [{
                            resource_id: String(target.id || ''),
                            graph_id: String(target.graph_id || (target.id || '').replace(/^[^:]+:/, '')),
                            resource_type: String(target.resource_type || 'user'),
                        }] : [];
                    } else {
                        targets = this.derivedOriginalTargets().map((target) => ({
                            resource_id: String(target.resource_id || ''),
                            graph_id: String(target.graph_id || ''),
                            resource_type: String(target.resource_type || 'user'),
                            ...(target.drive_id ? { drive_id: String(target.drive_id) } : {}),
                            ...(target.child_run_id ? { child_run_id: String(target.child_run_id) } : {}),
                        }));
                    }
                }
                return {
                    snapshot_batch_run_id: String(snapshot.batch_run_id || snapshot.id || ''),
                    restore_mode: this.restoreMode,
                    destination_mode: this.destinationMode,
                    conflict_policy: 'skip_duplicates',
                    items: this.selectedItems.map((s) => ({
                        child_run_id: String(s.child_run_id || ''),
                        manifest_id: String(s.manifest_id || ''),
                        path: String(s.path || ''),
                        path_prefix: String(s.path_prefix || ''),
                        type: String(s.type || ''),
                    })),
                    targets,
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
                    if (this.restoreMode === 'tenant') {
                        if (this.destinationMode === 'original' && !this.validateOriginalDestinations()) {
                            throw new Error(this.destinationError || 'Could not determine original restore locations.');
                        }
                        if (this.destinationMode === 'alternate' && !selection.targets.length) {
                            throw new Error('Select a restore destination.');
                        }
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
