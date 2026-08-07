(function () {
    'use strict';

    const TYPE_USER = 'user';
    const TYPE_MAILBOX = 'mailbox';
    const TYPE_ONEDRIVE = 'user_onedrive';
    const TYPE_SITE = 'sharepoint_site';
    const TYPE_TEAM = 'team';
    const TYPE_CHANNEL = 'team_channel';
    const TYPE_GROUP = 'm365_group';
    const TYPE_PLANNER = 'planner_plan';
    const TYPE_ONENOTE = 'onenote_notebook';
    const TYPE_DIRECTORY = 'directory_baseline';

    const SECTIONS = [
        { key: 'users', label: 'Users & mailboxes', parentTypes: [TYPE_USER, TYPE_MAILBOX] },
        { key: 'sharepoint', label: 'SharePoint sites', parentTypes: [TYPE_SITE] },
        { key: 'teams', label: 'Teams', parentTypes: [TYPE_TEAM] },
        { key: 'groups', label: 'Microsoft 365 groups', parentTypes: [TYPE_GROUP] },
        { key: 'planner', label: 'Planner', parentTypes: [TYPE_PLANNER], flat: true },
        { key: 'onenote', label: 'OneNote', parentTypes: [TYPE_ONENOTE], flat: true },
        { key: 'directory', label: 'Tenant metadata', parentTypes: [TYPE_DIRECTORY], flat: true },
    ];

    const USER_VIRTUAL = [
        { chip: 'Mail', scopeKey: 'mail' },
        { chip: 'Calendar', scopeKey: 'calendar' },
        { chip: 'Contacts', scopeKey: 'contacts' },
        { chip: 'Tasks', scopeKey: 'tasks' },
    ];

    const SITE_VIRTUAL = [
        { chip: 'Files', scopeKey: 'files' },
        { chip: 'Lists', scopeKey: 'lists' },
    ];

    const TEAM_VIRTUAL = [
        { chip: 'Metadata', scopeKey: 'teams_metadata' },
        { chip: 'Messages', scopeKey: 'teams_messages' },
        { chip: 'Files', scopeKey: 'files' },
    ];

    const GROUP_VIRTUAL = [
        { chip: 'Mail', scopeKey: 'mail' },
        { chip: 'Calendar', scopeKey: 'calendar' },
        { chip: 'Files', scopeKey: 'files' },
    ];

    function chipToScopeKey(chip) {
        const normalized = String(chip || '').toLowerCase().replace(/\s+/g, '_');
        const map = {
            mail: 'mail',
            calendar: 'calendar',
            contacts: 'contacts',
            tasks: 'tasks',
            onedrive: 'onedrive',
            files: 'files',
            files_via_sharepoint: 'files',
            lists: 'lists',
            metadata: 'teams_metadata',
            channels: 'teams_messages',
            messages: 'teams_messages',
            planner: 'planner',
            onenote: 'onenote',
        };
        return map[normalized] || normalized;
    }

    function resourcesById(inventory) {
        const map = {};
        const resources = (inventory && inventory.resources) || [];
        resources.forEach((r) => {
            if (r && r.id) map[r.id] = r;
        });
        return map;
    }

    function buildInventoryChildrenIndex(inventory) {
        const index = {};
        const resources = (inventory && inventory.resources) || [];
        resources.forEach((r) => {
            if (!r) return;
            const parentId = r.parent_id || '';
            if (!index[parentId]) index[parentId] = [];
            index[parentId].push(r);
        });
        return index;
    }

    function childrenOf(inventory, parentId, types, childrenIndex) {
        const allowed = types ? new Set(types) : null;
        const indexed = childrenIndex && childrenIndex[parentId || ''];
        const candidates = indexed || ((inventory && inventory.resources) || []).filter((r) => (r.parent_id || '') === (parentId || ''));
        if (!allowed) {
            return candidates;
        }
        return candidates.filter((r) => allowed.has(r.resource_type));
    }

    function buildSectionIndexes(sectionNodes) {
        const byKey = {};
        const childrenByParentKey = {};
        (sectionNodes || []).forEach((node) => {
            byKey[node.key] = node;
            const parentKey = node.parentKey || '';
            if (!childrenByParentKey[parentKey]) childrenByParentKey[parentKey] = [];
            childrenByParentKey[parentKey].push(node);
        });
        return { byKey, childrenByParentKey };
    }

    function buildAllSectionIndexes(treesBySection) {
        const indexes = {};
        SECTIONS.forEach((section) => {
            indexes[section.key] = buildSectionIndexes((treesBySection && treesBySection[section.key]) || []);
        });
        return indexes;
    }

    function shouldShowInSection(resource, sectionKey) {
        if (sectionKey !== 'sharepoint' || !resource || resource.resource_type !== TYPE_SITE) {
            return true;
        }
        if (resource.show_in_sharepoint_section === false) {
            return false;
        }
        if (resource.show_in_sharepoint_section === true) {
            return true;
        }
        if (resource.infrastructure_site === true) {
            return false;
        }
        if (resource.workload_group_connected === true || resource.group_connected === true) {
            return false;
        }
        if (resource.channel_connected === true) {
            return false;
        }
        return true;
    }

    function parentResources(inventory, types, sectionKey) {
        const allowed = new Set(types);
        return ((inventory && inventory.resources) || [])
            .filter((r) => allowed.has(r.resource_type))
            .filter((r) => shouldShowInSection(r, sectionKey))
            .sort((a, b) => String(a.display_name || '').localeCompare(String(b.display_name || ''), undefined, { sensitivity: 'base' }));
    }

    function nodeKey(...parts) {
        return parts.filter(Boolean).join(':');
    }

    function isGuestResource(resource) {
        if (!resource) {
            return false;
        }
        const meta = resource.meta || {};
        const userType = String(meta.user_type || '').toLowerCase();
        if (userType === 'guest') {
            return true;
        }
        const email = String(resource.email || meta.mail || '').toLowerCase();
        return email.includes('#ext#');
    }

    function iconKeyForResource(resource) {
        if (!resource) {
            return 'user';
        }
        const type = resource.resource_type || '';
        if (type === TYPE_USER || type === TYPE_MAILBOX) {
            if (isGuestResource(resource)) {
                return 'guest';
            }
            if (type === TYPE_MAILBOX) {
                return 'mailbox';
            }
            return 'user';
        }
        const map = {
            [TYPE_ONEDRIVE]: 'user_onedrive',
            [TYPE_SITE]: 'sharepoint_site',
            [TYPE_TEAM]: 'team',
            [TYPE_CHANNEL]: 'team_channel',
            [TYPE_GROUP]: 'm365_group',
            [TYPE_PLANNER]: 'planner_plan',
            [TYPE_ONENOTE]: 'onenote_notebook',
            [TYPE_DIRECTORY]: 'directory_baseline',
        };
        return map[type] || 'user';
    }

    function iconKeyForNode(parentResource, scopeKey) {
        if (scopeKey === 'onedrive') {
            return 'user_onedrive';
        }
        return iconKeyForResource(parentResource);
    }

    function buildVirtualNodes(parent, sectionKey, virtualDefs, depth) {
        const isSite = parent.resource_type === TYPE_SITE;
        return virtualDefs.map((def) => {
            let selectable = true;
            let disabledReason = '';
            if (isSite) {
                const capabilityAccess = parent.capability_access || {};
                if (capabilityAccess[def.scopeKey] === false) {
                    selectable = false;
                    disabledReason = parent.disabled_reason || 'Backup app cannot access this capability';
                }
            }
            return {
                key: nodeKey('cap', parent.id, def.scopeKey),
                kind: 'capability',
                sectionKey,
                resourceId: parent.id,
                scopeKey: def.scopeKey,
                label: def.chip,
                subtitle: '',
                parentKey: nodeKey('parent', parent.id),
                depth,
                expanded: false,
                hasChildren: false,
                selectable,
                disabledReason,
                iconKey: iconKeyForNode(parent, def.scopeKey),
            };
        });
    }

    function buildResourceChildNodes(parent, children, sectionKey, depth) {
        return children.map((child) => ({
            key: nodeKey('res', child.id),
            kind: 'resource_child',
            sectionKey,
            resourceId: child.id,
            resourceType: child.resource_type,
            scopeKey: '',
            label: child.display_name || child.id,
            subtitle: child.resource_type === TYPE_ONEDRIVE ? 'OneDrive · Files' : (child.email || ''),
            parentKey: nodeKey('parent', parent.id),
            depth,
            expanded: false,
            hasChildren: false,
            selectable: true,
            iconKey: iconKeyForResource(child),
        }));
    }

    function buildParentNode(resource, sectionKey, hasChildren) {
        const isSite = resource.resource_type === TYPE_SITE;
        const isUserOrMailbox = resource.resource_type === TYPE_USER || resource.resource_type === TYPE_MAILBOX;
        const selectable = (isSite || isUserOrMailbox)
            ? resource.selectable !== false
            : true;
        const disabledReason = (isSite || isUserOrMailbox)
            ? (resource.disabled_reason || '')
            : '';
        return {
            key: nodeKey('parent', resource.id),
            kind: 'parent',
            sectionKey,
            resourceId: resource.id,
            resourceType: resource.resource_type,
            scopeKey: '',
            label: resource.display_name || resource.id,
            subtitle: resource.email || '',
            parentKey: '',
            depth: 0,
            expanded: false,
            hasChildren,
            selectable,
            disabledReason,
            iconKey: iconKeyForResource(resource),
        };
    }

    function buildFlatLeaf(resource, sectionKey) {
        return {
            key: nodeKey('leaf', resource.id),
            kind: 'leaf',
            sectionKey,
            resourceId: resource.id,
            resourceType: resource.resource_type,
            scopeKey: '',
            label: resource.display_name || resource.id,
            subtitle: resource.email || resource.resource_type || '',
            parentKey: '',
            depth: 0,
            expanded: false,
            hasChildren: false,
            selectable: true,
            iconKey: iconKeyForResource(resource),
        };
    }

    function buildSectionTree(inventory, section, childrenIndex) {
        const nodes = [];
        const parents = parentResources(inventory, section.parentTypes, section.key);
        const childIndex = childrenIndex || buildInventoryChildrenIndex(inventory);

        parents.forEach((parent) => {
            if (section.flat) {
                nodes.push(buildFlatLeaf(parent, section.key));
                return;
            }

            let childDefs = [];
            let inventoryChildren = [];

            if (section.key === 'users') {
                childDefs = USER_VIRTUAL;
                inventoryChildren = childrenOf(inventory, parent.id, [TYPE_ONEDRIVE], childIndex);
            } else if (section.key === 'sharepoint') {
                childDefs = SITE_VIRTUAL;
            } else if (section.key === 'teams') {
                childDefs = TEAM_VIRTUAL;
                inventoryChildren = childrenOf(inventory, parent.id, [TYPE_CHANNEL], childIndex);
            } else if (section.key === 'groups') {
                childDefs = GROUP_VIRTUAL;
                inventoryChildren = childrenOf(inventory, parent.id, [TYPE_PLANNER], childIndex);
            }

            const virtualNodes = buildVirtualNodes(parent, section.key, childDefs, 1);
            const childNodes = buildResourceChildNodes(parent, inventoryChildren, section.key, 1);
            const hasChildren = virtualNodes.length > 0 || childNodes.length > 0;
            const parentNode = buildParentNode(parent, section.key, hasChildren);
            nodes.push(parentNode);

            if (hasChildren) {
                virtualNodes.forEach((n) => {
                    if (section.key === 'users' && n.scopeKey === 'onedrive') return;
                    nodes.push(n);
                });
                inventoryChildren.forEach((ch) => {
                    if (ch.resource_type === TYPE_ONEDRIVE) {
                        nodes.push({
                            key: nodeKey('cap', parent.id, 'onedrive'),
                            kind: 'capability',
                            sectionKey: section.key,
                            resourceId: ch.id,
                            childResourceId: ch.id,
                            scopeKey: 'onedrive',
                            label: 'OneDrive',
                            subtitle: 'Files',
                            parentKey: parentNode.key,
                            depth: 1,
                            expanded: false,
                            hasChildren: false,
                            selectable: true,
                            iconKey: 'user_onedrive',
                        });
                    } else {
                        nodes.push({
                            key: nodeKey('res', ch.id),
                            kind: 'resource_child',
                            sectionKey: section.key,
                            resourceId: ch.id,
                            resourceType: ch.resource_type,
                            scopeKey: '',
                            label: ch.display_name || ch.id,
                            subtitle: ch.resource_type === TYPE_CHANNEL ? 'Channel' : '',
                            parentKey: parentNode.key,
                            depth: 1,
                            expanded: false,
                            hasChildren: false,
                            selectable: true,
                            iconKey: iconKeyForResource(ch),
                        });
                    }
                });
            }
        });

        return nodes;
    }

    function buildAllTrees(inventory) {
        const childrenIndex = buildInventoryChildrenIndex(inventory);
        const bySection = {};
        SECTIONS.forEach((section) => {
            bySection[section.key] = buildSectionTree(inventory, section, childrenIndex);
        });
        return bySection;
    }

    function descendantKeys(sectionNodes, parentNode) {
        const keys = [];
        sectionNodes.forEach((n) => {
            if (n.parentKey === parentNode.key || (n.parentKey && n.parentKey.startsWith(parentNode.key + ':'))) {
                keys.push(n.key);
            }
        });
        sectionNodes.forEach((n) => {
            if (n.parentKey === parentNode.key) keys.push(n.key);
        });
        return [...new Set(keys)];
    }

    function getDescendants(sectionNodes, parentKey, indexes) {
        if (indexes && indexes.childrenByParentKey) {
            return indexes.childrenByParentKey[parentKey] || [];
        }
        return sectionNodes.filter((n) => n.parentKey === parentKey);
    }

    function nodeByKey(sectionNodes, key, indexes) {
        if (indexes && indexes.byKey && indexes.byKey[key]) {
            return indexes.byKey[key];
        }
        return sectionNodes.find((n) => n.key === key) || null;
    }

    function isChecked(selection, key) {
        return !!selection[key];
    }

    function setChecked(selection, key, value) {
        if (value) selection[key] = true;
        else delete selection[key];
    }

    function isSharePointListsCapability(node) {
        return node.kind === 'capability' && node.scopeKey === 'lists';
    }

    function selectableChildren(sectionNodes, parentKey, indexes) {
        return getDescendants(sectionNodes, parentKey, indexes).filter((c) => c.selectable !== false);
    }

    function toggleParent(sectionNodes, selection, parentNode, indexes) {
        if (parentNode.selectable === false) {
            return;
        }
        const children = selectableChildren(sectionNodes, parentNode.key, indexes);
        const allChecked = children.every((c) => isChecked(selection, c.key)) && children.length > 0;
        const next = !allChecked;
        if (next) {
            setChecked(selection, parentNode.key, true);
            if (parentNode.sectionKey === 'sharepoint') {
                children.forEach((c) => {
                    if (!isSharePointListsCapability(c)) {
                        setChecked(selection, c.key, true);
                    }
                });
            } else {
                children.forEach((c) => setChecked(selection, c.key, true));
            }
        } else {
            delete selection[parentNode.key];
            children.forEach((c) => setChecked(selection, c.key, false));
        }
    }

    function toggleNode(sectionNodes, selection, node, indexes) {
        if (node.selectable === false) {
            return;
        }
        if (node.kind === 'parent') {
            toggleParent(sectionNodes, selection, node, indexes);
            return;
        }
        const now = !isChecked(selection, node.key);
        setChecked(selection, node.key, now);
        if (node.parentKey) {
            const parent = nodeByKey(sectionNodes, node.parentKey, indexes);
            if (parent) syncParentState(sectionNodes, selection, parent, indexes);
        }
    }

    function syncParentState(sectionNodes, selection, parentNode, indexes) {
        const children = selectableChildren(sectionNodes, parentNode.key, indexes);
        if (children.length === 0) return;
        const checkedCount = children.filter((c) => isChecked(selection, c.key)).length;
        if (checkedCount === children.length) {
            setChecked(selection, parentNode.key, true);
        } else if (checkedCount === 0) {
            delete selection[parentNode.key];
        } else {
            delete selection[parentNode.key];
        }
    }

    function parentCheckState(sectionNodes, selection, parentNode, indexes) {
        const children = selectableChildren(sectionNodes, parentNode.key, indexes);
        if (children.length === 0) {
            return isChecked(selection, parentNode.key) ? 'checked' : 'unchecked';
        }
        const checkedCount = children.filter((c) => isChecked(selection, c.key)).length;
        if (checkedCount === 0 && !isChecked(selection, parentNode.key)) return 'unchecked';
        if (checkedCount === children.length) return 'checked';
        return 'indeterminate';
    }

    function defaultScopeForResourceType(type) {
        if (type === TYPE_USER || type === TYPE_MAILBOX) {
            return { mail: true, calendar: true, contacts: true, tasks: true };
        }
        if (type === TYPE_ONEDRIVE) {
            return { onedrive: true, files: true };
        }
        if (type === TYPE_SITE) {
            return { files: true, lists: false };
        }
        if (type === TYPE_TEAM) {
            return { teams_metadata: true, teams_messages: true, files: true };
        }
        if (type === TYPE_CHANNEL) {
            return { teams_messages: true, files: true };
        }
        if (type === TYPE_GROUP) {
            return { mail: true, calendar: true, files: true };
        }
        if (type === TYPE_PLANNER) {
            return { planner: true };
        }
        if (type === TYPE_ONENOTE) {
            return { onenote: true };
        }
        return {};
    }

    function buildSavePayload(inventory, treesBySection, selection) {
        const byId = resourcesById(inventory);
        const selectedIds = new Set();
        const scopeAccumulator = {};

        function addScope(resourceId, key, enabled) {
            if (!scopeAccumulator[resourceId]) {
                scopeAccumulator[resourceId] = {};
            }
            scopeAccumulator[resourceId][key] = enabled;
        }

        function applyTemplate(resourceId, type) {
            const defaults = defaultScopeForResourceType(type);
            const flags = {};
            Object.keys(defaults).forEach((k) => {
                flags[k] = scopeAccumulator[resourceId] ? !!scopeAccumulator[resourceId][k] : false;
            });
            if (scopeAccumulator[resourceId]) {
                Object.keys(scopeAccumulator[resourceId]).forEach((k) => {
                    flags[k] = !!scopeAccumulator[resourceId][k];
                });
            }
            return flags;
        }

        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            nodes.forEach((node) => {
                if (node.selectable === false) return;
                if (!isChecked(selection, node.key)) return;
                if (node.kind === 'parent') return;

                if (node.kind === 'capability') {
                    const targetId = node.childResourceId || node.resourceId;
                    selectedIds.add(targetId);
                    if (node.scopeKey === 'onedrive') {
                        addScope(targetId, 'onedrive', true);
                        addScope(targetId, 'files', true);
                    } else {
                        addScope(targetId, node.scopeKey, true);
                    }
                    return;
                }

                if (node.kind === 'resource_child' || node.kind === 'leaf') {
                    selectedIds.add(node.resourceId);
                    const res = byId[node.resourceId];
                    if (res) {
                        const defaults = defaultScopeForResourceType(res.resource_type);
                        Object.keys(defaults).forEach((k) => addScope(node.resourceId, k, true));
                    }
                }
            });
        });

        const scopeOverrides = {};
        selectedIds.forEach((id) => {
            const res = byId[id];
            if (!res) return;
            const flags = applyTemplate(id, res.resource_type);
            if (Object.values(flags).some(Boolean)) {
                scopeOverrides[id] = flags;
            }
        });

        return {
            selected_resource_ids: [...selectedIds],
            scope_overrides: scopeOverrides,
        };
    }

    function hydrateFromSavedJob(inventory, selectedIds, scopeOverrides) {
        const selection = {};
        const ids = new Set((selectedIds || []).map(String));
        const overrides = scopeOverrides || {};
        const byId = resourcesById(inventory);
        const trees = buildAllTrees(inventory);

        if (!scopeOverrides || Object.keys(scopeOverrides).length === 0) {
            ids.forEach((id) => {
                const res = byId[id];
                if (!res) return;
                const type = res.resource_type;
                if (type === TYPE_USER || type === TYPE_MAILBOX) {
                    const parentKey = nodeKey('parent', id);
                    selection[parentKey] = true;
                    selection[nodeKey('cap', id, 'mail')] = true;
                    selection[nodeKey('cap', id, 'calendar')] = true;
                } else if (type === TYPE_ONEDRIVE) {
                    const parentId = res.parent_id || '';
                    if (parentId) {
                        selection[nodeKey('cap', parentId, 'onedrive')] = true;
                    }
                } else if (type === TYPE_SITE) {
                    SECTIONS.forEach((section) => {
                        if (section.key !== 'sharepoint') {
                            return;
                        }
                        const nodes = trees[section.key] || [];
                        const filesCap = nodes.find(
                            (n) => n.kind === 'capability' && n.resourceId === id && n.scopeKey === 'files',
                        );
                        if (filesCap) {
                            selection[filesCap.key] = true;
                        }
                    });
                } else {
                    SECTIONS.forEach((section) => {
                        const nodes = trees[section.key] || [];
                        const leaf = nodes.find((n) => n.resourceId === id);
                        if (leaf) selection[leaf.key] = true;
                    });
                }
            });
            return pruneDisabledSelection(trees, selection);
        }

        SECTIONS.forEach((section) => {
            const nodes = trees[section.key] || [];
            nodes.forEach((node) => {
                if (node.selectable === false) {
                    return;
                }
                if (node.kind === 'leaf') {
                    if (ids.has(node.resourceId)) selection[node.key] = true;
                    return;
                }
                if (node.kind === 'capability') {
                    const targetId = node.childResourceId || node.resourceId;
                    const flags = overrides[targetId] || overrides[node.resourceId] || {};
                    if (node.scopeKey === 'onedrive') {
                        if (flags.onedrive || ids.has(targetId)) selection[node.key] = true;
                    } else if (flags[node.scopeKey]) {
                        selection[node.key] = true;
                    }
                    return;
                }
                if (node.kind === 'resource_child') {
                    if (ids.has(node.resourceId)) selection[node.key] = true;
                }
            });
            nodes.filter((n) => n.kind === 'parent').forEach((parent) => {
                syncParentState(nodes, selection, parent, buildSectionIndexes(nodes));
            });
        });

        return pruneDisabledSelection(trees, selection);
    }

    function pruneDisabledSelection(treesBySection, selection) {
        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            const indexes = buildSectionIndexes(nodes);
            nodes.forEach((node) => {
                if (node.selectable === false) {
                    delete selection[node.key];
                }
            });
            nodes.filter((n) => n.kind === 'parent').forEach((parent) => {
                syncParentState(nodes, selection, parent, indexes);
            });
        });

        return selection;
    }

    function selectionSummary(inventory, treesBySection, selection) {
        const groups = [];
        SECTIONS.forEach((section) => {
            const items = [];
            const nodes = treesBySection[section.key] || [];
            const indexes = buildSectionIndexes(nodes);

            if (section.flat) {
                nodes.forEach((node) => {
                    if (node.kind !== 'leaf' || !isChecked(selection, node.key)) return;
                    items.push({
                        label: node.label,
                        subtitle: node.subtitle || '',
                        badges: [],
                    });
                });
            } else {
                nodes.filter((n) => n.kind === 'parent').forEach((parent) => {
                    const children = getDescendants(nodes, parent.key, indexes);
                    const badges = children
                        .filter((c) => (c.kind === 'capability' || c.kind === 'resource_child') && isChecked(selection, c.key))
                        .map((c) => c.label);
                    const hasLeafChildren = children.some((c) => c.kind === 'capability' || c.kind === 'resource_child');
                    const parentOnly = !hasLeafChildren && isChecked(selection, parent.key);
                    if (badges.length === 0 && !parentOnly) return;
                    items.push({
                        label: parent.label,
                        subtitle: parent.subtitle || '',
                        badges,
                    });
                });
            }

            if (items.length > 0) {
                groups.push({ section: section.label, items });
            }
        });
        return groups;
    }

    function summaryRowCount(groups) {
        return (groups || []).reduce((sum, group) => sum + (group.items ? group.items.length : 0), 0);
    }

    function parentHasSelection(nodes, selection, parent, indexes) {
        const children = getDescendants(nodes, parent.key, indexes);
        const hasLeafChildren = children.some((c) => c.kind === 'capability' || c.kind === 'resource_child');
        const parentOnly = !hasLeafChildren && isChecked(selection, parent.key);
        if (parentOnly) {
            return true;
        }
        return children.some((c) => {
            if (c.kind !== 'capability' && c.kind !== 'resource_child') {
                return false;
            }
            return isChecked(selection, c.key);
        });
    }

    function selectionWorkloadCounts(inventory, treesBySection, selection) {
        const counts = {
            protected_accounts: 0,
            users_and_mailboxes: 0,
            users: 0,
            shared_mailboxes: 0,
            guests: 0,
            mail: 0,
            calendar: 0,
            contacts: 0,
            tasks: 0,
            onedrive: 0,
            sharepoint_sites: 0,
            teams: 0,
            groups: 0,
            planner: 0,
            onenote: 0,
            directory: 0,
        };

        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            const indexes = buildSectionIndexes(nodes);

            if (section.flat) {
                nodes.forEach((node) => {
                    if (node.kind !== 'leaf' || !isChecked(selection, node.key)) {
                        return;
                    }
                    if (section.key === 'planner') {
                        counts.planner += 1;
                    } else if (section.key === 'onenote') {
                        counts.onenote += 1;
                    } else if (section.key === 'directory') {
                        counts.directory += 1;
                    }
                });
                return;
            }

            if (section.key === 'users') {
                let usersAndMailboxes = 0;
                nodes.filter((n) => n.kind === 'parent').forEach((parent) => {
                    if (parent.selectable === false) {
                        return;
                    }
                    if (!parentHasSelection(nodes, selection, parent, indexes)) {
                        return;
                    }
                    usersAndMailboxes += 1;
                    if (parent.iconKey === 'guest') {
                        counts.guests += 1;
                    } else if (parent.resourceType === TYPE_MAILBOX) {
                        counts.shared_mailboxes += 1;
                    } else {
                        counts.users += 1;
                    }
                    getDescendants(nodes, parent.key, indexes).forEach((child) => {
                        if (child.kind !== 'capability' || !isChecked(selection, child.key)) {
                            return;
                        }
                        if (child.scopeKey === 'mail') {
                            counts.mail += 1;
                        } else if (child.scopeKey === 'calendar') {
                            counts.calendar += 1;
                        } else if (child.scopeKey === 'contacts') {
                            counts.contacts += 1;
                        } else if (child.scopeKey === 'tasks') {
                            counts.tasks += 1;
                        } else if (child.scopeKey === 'onedrive') {
                            counts.onedrive += 1;
                        }
                    });
                });
                counts.users_and_mailboxes = usersAndMailboxes;
                // Keep legacy key for callers that still read protected_accounts.
                counts.protected_accounts = usersAndMailboxes;
                return;
            }

            if (section.key === 'sharepoint') {
                nodes.filter((n) => n.kind === 'parent').forEach((parent) => {
                    if (parentHasSelection(nodes, selection, parent, indexes)) {
                        counts.sharepoint_sites += 1;
                    }
                });
                return;
            }

            if (section.key === 'teams') {
                nodes.filter((n) => n.kind === 'parent').forEach((parent) => {
                    if (parentHasSelection(nodes, selection, parent, indexes)) {
                        counts.teams += 1;
                    }
                });
                return;
            }

            if (section.key === 'groups') {
                nodes.filter((n) => n.kind === 'parent').forEach((parent) => {
                    if (parentHasSelection(nodes, selection, parent, indexes)) {
                        counts.groups += 1;
                    }
                });
            }
        });

        return counts;
    }

    function selectableLeafKeys(treesBySection) {
        const keys = [];
        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            nodes.forEach((node) => {
                if (node.selectable === false) return;
                if (node.kind === 'capability' || node.kind === 'resource_child' || node.kind === 'leaf') {
                    keys.push(node.key);
                }
            });
        });
        return keys;
    }

    function countInaccessibleSites(inventory) {
        const resources = (inventory && inventory.resources) || [];
        return resources.filter((r) => {
            return r
                && r.resource_type === TYPE_SITE
                && shouldShowInSection(r, 'sharepoint')
                && r.selectable === false;
        }).length;
    }

    function selectAll(treesBySection) {
        const selection = {};
        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            const indexes = buildSectionIndexes(nodes);
            nodes.forEach((node) => {
                if (node.selectable === false) return;
                if (node.kind === 'parent') {
                    const children = selectableChildren(nodes, node.key, indexes);
                    if (children.length > 0) {
                        setChecked(selection, node.key, true);
                        if (section.key === 'sharepoint') {
                            children.forEach((c) => {
                                if (!isSharePointListsCapability(c)) {
                                    setChecked(selection, c.key, true);
                                }
                            });
                        } else {
                            children.forEach((c) => setChecked(selection, c.key, true));
                        }
                    }
                    return;
                }
                if (isSharePointListsCapability(node)) {
                    return;
                }
                if (node.kind === 'capability' || node.kind === 'resource_child' || node.kind === 'leaf') {
                    setChecked(selection, node.key, true);
                }
            });
        });
        return selection;
    }

    function isAnyListsSelected(treesBySection, selection) {
        let found = false;
        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            nodes.forEach((node) => {
                if (isSharePointListsCapability(node) && isChecked(selection, node.key)) {
                    found = true;
                }
            });
        });
        return found;
    }

    function globalCheckState(treesBySection, selection) {
        const keys = selectableLeafKeys(treesBySection);
        if (keys.length === 0) {
            return 'unchecked';
        }
        const checked = keys.filter((key) => isChecked(selection, key)).length;
        if (checked === 0) {
            return 'unchecked';
        }
        if (checked === keys.length) {
            return 'checked';
        }
        return 'indeterminate';
    }

    function toggleGlobalSelect(treesBySection, selection) {
        const state = globalCheckState(treesBySection, selection);
        // Checked or partial → clear all. Unchecked → select all.
        // (Indeterminate click must clear; otherwise the checkbox can only re-select all.)
        if (state === 'checked' || state === 'indeterminate') {
            return {};
        }
        return selectAll(treesBySection);
    }

    function normalizeSearchTokens(searchQuery) {
        const q = (searchQuery || '').toLowerCase().trim();
        if (!q) {
            return [];
        }
        return q.split(/\s+/).filter(Boolean);
    }

    function nodeSearchableText(node) {
        const parts = [node.label, node.subtitle];
        if (node.resourceType) {
            parts.push(node.resourceType);
        }
        return parts.filter(Boolean).join(' ').toLowerCase();
    }

    function textMatchesTokens(text, tokens) {
        if (tokens.length === 0) {
            return true;
        }
        return tokens.every((token) => text.includes(token));
    }

    function branchSearchableText(node, descendants) {
        return [nodeSearchableText(node), ...descendants.map((child) => nodeSearchableText(child))]
            .join(' ');
    }

    function nodeMatchesQuery(node, descendants, tokens) {
        return textMatchesTokens(branchSearchableText(node, descendants), tokens);
    }

    function isFlatSection(sectionNodes) {
        return sectionNodes.length > 0
            && sectionNodes.every((node) => node.depth === 0 && !node.hasChildren);
    }

    function visibleNodes(sectionNodes, selection, searchQuery, expandedKeys, indexes) {
        const tokens = normalizeSearchTokens(searchQuery);
        const expanded = expandedKeys || {};

        if (tokens.length === 0) {
            const visible = [];
            for (let i = 0; i < sectionNodes.length; i += 1) {
                const node = sectionNodes[i];
                if (node.depth === 0) {
                    visible.push(node);
                } else if (node.parentKey && expanded[node.parentKey]) {
                    visible.push(node);
                }
            }
            return visible;
        }

        if (isFlatSection(sectionNodes)) {
            return sectionNodes.filter((node) => textMatchesTokens(nodeSearchableText(node), tokens));
        }

        const visible = [];
        sectionNodes.forEach((node) => {
            if (node.depth !== 0) {
                return;
            }
            const children = getDescendants(sectionNodes, node.key, indexes);
            if (!nodeMatchesQuery(node, children, tokens)) {
                return;
            }
            visible.push(node);
            if (!node.hasChildren) {
                return;
            }
            const parentMatches = textMatchesTokens(nodeSearchableText(node), tokens);
            const branchMatches = textMatchesTokens(branchSearchableText(node, children), tokens);
            children.forEach((child) => {
                const childText = nodeSearchableText(child);
                const childMatchesAll = textMatchesTokens(childText, tokens);
                const childMatchesAny = tokens.some((token) => childText.includes(token));
                if (parentMatches || childMatchesAll || (branchMatches && childMatchesAny)) {
                    visible.push(child);
                }
            });
        });

        return visible;
    }

    function sectionHasVisibleNodes(sectionNodes, searchQuery, expandedKeys, indexes) {
        return visibleNodes(sectionNodes, {}, searchQuery, expandedKeys, indexes).length > 0;
    }

    window.ms365JobSelection = {
        SECTIONS,
        buildAllTrees,
        buildAllSectionIndexes,
        buildSectionIndexes,
        isChecked,
        toggleNode,
        parentCheckState,
        globalCheckState,
        toggleGlobalSelect,
        selectAll,
        isAnyListsSelected,
        buildSavePayload,
        hydrateFromSavedJob,
        selectionSummary,
        summaryRowCount,
        selectionWorkloadCounts,
        visibleNodes,
        sectionHasVisibleNodes,
        normalizeSearchTokens,
        nodeSearchableText,
        textMatchesTokens,
        nodeMatchesQuery,
        getDescendants,
        nodeByKey,
        countInaccessibleSites,
    };
})();
