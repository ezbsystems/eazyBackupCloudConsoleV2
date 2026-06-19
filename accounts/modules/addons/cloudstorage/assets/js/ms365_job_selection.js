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

    function childrenOf(inventory, parentId, types) {
        const allowed = types ? new Set(types) : null;
        return ((inventory && inventory.resources) || []).filter((r) => {
            if ((r.parent_id || '') !== parentId) return false;
            if (allowed && !allowed.has(r.resource_type)) return false;
            return true;
        });
    }

    function parentResources(inventory, types) {
        const allowed = new Set(types);
        return ((inventory && inventory.resources) || [])
            .filter((r) => allowed.has(r.resource_type))
            .sort((a, b) => String(a.display_name || '').localeCompare(String(b.display_name || ''), undefined, { sensitivity: 'base' }));
    }

    function nodeKey(...parts) {
        return parts.filter(Boolean).join(':');
    }

    function buildVirtualNodes(parent, sectionKey, virtualDefs, depth) {
        return virtualDefs.map((def) => ({
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
            selectable: true,
        }));
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
        }));
    }

    function buildParentNode(resource, sectionKey, hasChildren) {
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
            selectable: true,
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
        };
    }

    function buildSectionTree(inventory, section) {
        const nodes = [];
        const parents = parentResources(inventory, section.parentTypes);

        parents.forEach((parent) => {
            if (section.flat) {
                nodes.push(buildFlatLeaf(parent, section.key));
                return;
            }

            let childDefs = [];
            let inventoryChildren = [];

            if (section.key === 'users') {
                childDefs = USER_VIRTUAL;
                inventoryChildren = childrenOf(inventory, parent.id, [TYPE_ONEDRIVE]);
            } else if (section.key === 'sharepoint') {
                childDefs = SITE_VIRTUAL;
            } else if (section.key === 'teams') {
                childDefs = TEAM_VIRTUAL;
                inventoryChildren = childrenOf(inventory, parent.id, [TYPE_CHANNEL]);
            } else if (section.key === 'groups') {
                childDefs = GROUP_VIRTUAL;
                inventoryChildren = childrenOf(inventory, parent.id, [TYPE_PLANNER]);
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
                        });
                    }
                });
            }
        });

        return nodes;
    }

    function buildAllTrees(inventory) {
        const bySection = {};
        SECTIONS.forEach((section) => {
            bySection[section.key] = buildSectionTree(inventory, section);
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

    function getDescendants(sectionNodes, parentKey) {
        return sectionNodes.filter((n) => n.parentKey === parentKey);
    }

    function isChecked(selection, key) {
        return !!selection[key];
    }

    function setChecked(selection, key, value) {
        if (value) selection[key] = true;
        else delete selection[key];
    }

    function toggleParent(sectionNodes, selection, parentNode) {
        const children = getDescendants(sectionNodes, parentNode.key);
        const allChecked = children.every((c) => isChecked(selection, c.key)) && children.length > 0;
        const next = !allChecked;
        if (next) setChecked(selection, parentNode.key, true);
        else delete selection[parentNode.key];
        children.forEach((c) => setChecked(selection, c.key, next));
    }

    function toggleNode(sectionNodes, selection, node) {
        if (node.kind === 'parent') {
            toggleParent(sectionNodes, selection, node);
            return;
        }
        const now = !isChecked(selection, node.key);
        setChecked(selection, node.key, now);
        if (node.parentKey) {
            const parent = sectionNodes.find((n) => n.key === node.parentKey);
            if (parent) syncParentState(sectionNodes, selection, parent);
        }
    }

    function syncParentState(sectionNodes, selection, parentNode) {
        const children = getDescendants(sectionNodes, parentNode.key);
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

    function parentCheckState(sectionNodes, selection, parentNode) {
        const children = getDescendants(sectionNodes, parentNode.key);
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
            return { files: true, lists: true };
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
                } else {
                    SECTIONS.forEach((section) => {
                        const nodes = trees[section.key] || [];
                        const leaf = nodes.find((n) => n.resourceId === id);
                        if (leaf) selection[leaf.key] = true;
                    });
                }
            });
            return selection;
        }

        SECTIONS.forEach((section) => {
            const nodes = trees[section.key] || [];
            nodes.forEach((node) => {
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
                syncParentState(nodes, selection, parent);
            });
        });

        return selection;
    }

    function selectionSummary(inventory, treesBySection, selection) {
        const groups = [];
        SECTIONS.forEach((section) => {
            const items = [];
            const nodes = treesBySection[section.key] || [];

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
                    const children = getDescendants(nodes, parent.key);
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

    function selectableLeafKeys(treesBySection) {
        const keys = [];
        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            nodes.forEach((node) => {
                if (node.kind === 'capability' || node.kind === 'resource_child' || node.kind === 'leaf') {
                    keys.push(node.key);
                }
            });
        });
        return keys;
    }

    function selectAll(treesBySection) {
        const selection = {};
        SECTIONS.forEach((section) => {
            const nodes = treesBySection[section.key] || [];
            nodes.forEach((node) => {
                if (node.kind === 'parent') {
                    const children = getDescendants(nodes, node.key);
                    if (children.length > 0) {
                        setChecked(selection, node.key, true);
                    }
                }
                if (node.kind === 'capability' || node.kind === 'resource_child' || node.kind === 'leaf') {
                    setChecked(selection, node.key, true);
                }
            });
        });
        return selection;
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
        if (globalCheckState(treesBySection, selection) === 'checked') {
            return {};
        }
        return selectAll(treesBySection);
    }

    function visibleNodes(sectionNodes, selection, searchQuery, expandedKeys) {
        const q = (searchQuery || '').toLowerCase().trim();
        const visible = [];
        const expanded = expandedKeys || {};

        sectionNodes.forEach((node) => {
            if (node.depth === 0) {
                const children = getDescendants(sectionNodes, node.key);
                const hay = [node.label, node.subtitle, ...children.map((c) => c.label)].join(' ').toLowerCase();
                if (q && !hay.includes(q)) return;
                visible.push(node);
                const showChildren = expanded[node.key] || (q !== '');
                if (showChildren && node.hasChildren) {
                    children.forEach((c) => visible.push(c));
                }
            }
        });

        if (!q) {
            return sectionNodes.filter((node) => {
                if (node.depth === 0) return true;
                const parent = sectionNodes.find((p) => p.key === node.parentKey);
                return parent && (expanded[parent.key] || false);
            });
        }

        return visible;
    }

    window.ms365JobSelection = {
        SECTIONS,
        buildAllTrees,
        isChecked,
        toggleNode,
        parentCheckState,
        globalCheckState,
        toggleGlobalSelect,
        selectAll,
        buildSavePayload,
        hydrateFromSavedJob,
        selectionSummary,
        summaryRowCount,
        visibleNodes,
        getDescendants,
    };
})();
