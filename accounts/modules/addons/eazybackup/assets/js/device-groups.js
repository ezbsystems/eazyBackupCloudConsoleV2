(() => {
  'use strict';

  const ENDPOINT = () => (typeof window !== 'undefined' && window.EB_GROUPS_ENDPOINT)
    ? window.EB_GROUPS_ENDPOINT
    : 'index.php?m=eazybackup&a=device-groups';

  function toast(msg, kind) {
    try { window.showToast?.(msg, kind || 'info'); } catch (_) {}
  }

  async function api(action, payload) {
    const body = Object.assign({ action }, payload || {});
    const res = await fetch(ENDPOINT(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    });
    return res.json();
  }

  function normName(s) {
    try {
      return String(s || '').trim().replace(/\s+/g, ' ');
    } catch (_) { return ''; }
  }

  function registerStore() {
    try {
      if (!window.Alpine || typeof Alpine.store !== 'function') return;
      // If it already exists, do nothing (important for hot reload / duplicate includes)
      try { if (Alpine.store('ebDeviceGroups')) return; } catch (_) {}

      Alpine.store('ebDeviceGroups', {
      drawerOpen: false,
      loading: false,
      groups: [],           // [{id,name,color,icon,sort_order,count}]
      assignments: {},      // device_id -> group_id|null
      ver: 0,

      // View mode
      groupBy: (function(){
        try { return localStorage.getItem('ebdg_groupBy') || 'none'; } catch(_) { return 'none'; }
      })(),

      // Collapsed groups state (key -> bool). Includes 'ungrouped' and 'g:<id>' keys.
      collapsedGroups: (function(){
        try {
          const raw = localStorage.getItem('ebdg_collapsed') || '{}';
          const obj = JSON.parse(raw);
          const map = (obj && typeof obj === 'object') ? obj : {};
          // Ungrouped should be visible by default to prevent “blank list” confusion.
          try { delete map.ungrouped; } catch (_) {}
          return map;
        } catch(_) { return {}; }
      })(),

      // Drawer UI state
      search: '',
      creating: false,
      newName: '',
      newColor: '',
      newIcon: '',
      savingCreate: false,

      renameId: null,
      renameValue: '',
      savingRename: false,

      deleteId: null,
      deleteName: '',
      deleteCount: 0,
      deleting: false,

      dragId: null,

      // Inline assignment popover state (single-open)
      assignOpenFor: null,       // device_id
      assignSearch: '',
      assignCreateOpen: false,
      assignCreateName: '',
      savingAssign: false,
      savingAssignBulk: false,

      init() {
        // Close on Esc
        try {
          window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
              if (this.deleteId) { this.cancelDelete(); return; }
              if (this.renameId) { this.cancelRename(); return; }
              if (this.drawerOpen) { this.closeDrawer(); }
            }
          });
        } catch (_) {}
      },

      // ---- Helpers ----
      sortedGroups() {
        const arr = (this.groups || []).slice(0);
        arr.sort((a, b) => {
          const as = Number(a.sort_order || 0), bs = Number(b.sort_order || 0);
          if (as !== bs) return as - bs;
          return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
        });
        return arr;
      },

      filteredGroupsForAssign() {
        const q = (this.assignSearch || '').trim().toLowerCase();
        const arr = this.sortedGroups();
        if (!q) return arr;
        return arr.filter(g => String(g.name || '').toLowerCase().includes(q));
      },

      deviceGroupId(deviceId) {
        try {
          const did = String(deviceId || '');
          if (!did) return null;
          const gid = this.assignments ? this.assignments[did] : null;
          const n = Number(gid);
          return (n && n > 0) ? n : null;
        } catch (_) { return null; }
      },

      deviceGroupName(deviceId) {
        const gid = this.deviceGroupId(deviceId);
        if (!gid) return 'Ungrouped';
        const g = (this.groups || []).find(x => Number(x.id) === gid);
        return (g && g.name) ? String(g.name) : 'Ungrouped';
      },

      // ---- Inline popover ----
      toggleAssignPopover(deviceId) {
        const did = String(deviceId || '');
        if (!did) return;
        if (this.assignOpenFor === did) {
          this.closeAssignPopover();
          return;
        }
        this.assignOpenFor = did;
        this.assignSearch = '';
        this.assignCreateOpen = false;
        this.assignCreateName = '';
        // Ensure groups loaded for the popover list
        try { if (!this.loading && (!this.groups || this.groups.length === 0)) this.load(); } catch (_) {}
        try { this.$nextTick?.(() => document.getElementById('ebdg-assign-search')?.focus()); } catch (_) {}
      },

      closeAssignPopover() {
        this.assignOpenFor = null;
        this.assignSearch = '';
        this.assignCreateOpen = false;
        this.assignCreateName = '';
      },

      openCreateInPopover() {
        this.assignCreateOpen = true;
        this.assignCreateName = '';
        try { this.$nextTick?.(() => document.getElementById('ebdg-assign-create')?.focus()); } catch (_) {}
      },

      // ---- Assignment API ----
      async assignDevice(deviceId, groupId) {
        const did = String(deviceId || '');
        if (!did) return;

        const prev = this.assignments ? this.assignments[did] : null;
        const next = (groupId === null || groupId === undefined || groupId === '' || Number(groupId) <= 0) ? null : Number(groupId);

        // optimistic
        if (!this.assignments || typeof this.assignments !== 'object') this.assignments = {};
        if (next === null) {
          delete this.assignments[did];
        } else {
          this.assignments[did] = next;
        }
        this.touch();

        this.savingAssign = true;
        try {
          const r = await api('assignDevice', { device_id: did, group_id: next });
          if (r && r.status === 'success') {
            toast(next ? 'Device moved.' : 'Device moved to Ungrouped.', 'success');
            this.load(); // refresh counts
            return true;
          }
          // revert
          if (prev === null || prev === undefined || prev === '' || Number(prev) <= 0) delete this.assignments[did];
          else this.assignments[did] = Number(prev);
          this.touch();
          toast((r && r.message) ? r.message : 'Couldn’t move device. Please try again.', 'error');
          return false;
        } catch (_) {
          // revert
          if (prev === null || prev === undefined || prev === '' || Number(prev) <= 0) delete this.assignments[did];
          else this.assignments[did] = Number(prev);
          this.touch();
          toast('Couldn’t move device. Please try again.', 'error');
          return false;
        } finally {
          this.savingAssign = false;
        }
      },

      async createGroupAndAssign(deviceId) {
        const did = String(deviceId || '');
        const name = normName(this.assignCreateName);
        if (!did) return;
        if (!name) { toast('Group name is required.', 'warning'); return; }

        this.savingAssign = true;
        try {
          const r = await api('createGroup', { name });
          if (r && r.status === 'success' && r.group) {
            // update groups list optimistically
            this.groups = (this.groups || []).concat([r.group]).sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
            this.touch();
            // assign immediately
            await this.assignDevice(did, r.group.id);
            this.assignCreateOpen = false;
            this.assignCreateName = '';
            return;
          }
          toast((r && r.message) ? r.message : 'Couldn’t create group. Please try again.', 'error');
        } catch (_) {
          toast('Couldn’t create group. Please try again.', 'error');
        } finally {
          this.savingAssign = false;
        }
      },

      async bulkAssign(deviceIds, groupId) {
        const ids = Array.isArray(deviceIds) ? deviceIds.map(x => String(x || '').trim()).filter(Boolean) : [];
        if (!ids.length) return false;
        const next = (groupId === null || groupId === undefined || groupId === '' || Number(groupId) <= 0) ? null : Number(groupId);

        // optimistic
        if (!this.assignments || typeof this.assignments !== 'object') this.assignments = {};
        const prevMap = {};
        ids.forEach(did => { prevMap[did] = this.assignments[did]; });
        ids.forEach(did => {
          if (next === null) delete this.assignments[did];
          else this.assignments[did] = next;
        });
        this.touch();

        this.savingAssignBulk = true;
        try {
          const r = await api('bulkAssign', { device_ids: ids, group_id: next });
          if (r && r.status === 'success') {
            toast('Devices updated.', 'success');
            this.load();
            return true;
          }
          // revert
          ids.forEach(did => {
            const pv = prevMap[did];
            if (pv === null || pv === undefined || pv === '' || Number(pv) <= 0) delete this.assignments[did];
            else this.assignments[did] = Number(pv);
          });
          this.touch();
          toast((r && r.message) ? r.message : 'Couldn’t update devices. Please try again.', 'error');
          return false;
        } catch (_) {
          ids.forEach(did => {
            const pv = prevMap[did];
            if (pv === null || pv === undefined || pv === '' || Number(pv) <= 0) delete this.assignments[did];
            else this.assignments[did] = Number(pv);
          });
          this.touch();
          toast('Couldn’t update devices. Please try again.', 'error');
          return false;
        } finally {
          this.savingAssignBulk = false;
        }
      },

      touch() {
        try { this.ver = (Number(this.ver) || 0) + 1; } catch (_) { this.ver = 1; }
        try { window.dispatchEvent(new CustomEvent('ebdg:updated', { detail: { ver: this.ver } })); } catch (_) {}
      },

      groupByLabel() {
        return (this.groupBy === 'groups') ? 'Client/Company Groups' : 'None';
      },

      setGroupBy(mode) {
        const v = (mode === 'groups') ? 'groups' : 'none';
        this.groupBy = v;
        try { localStorage.setItem('ebdg_groupBy', v); } catch (_) {}
        try { window.dispatchEvent(new CustomEvent('ebdg:groupby', { detail: { groupBy: v } })); } catch (_) {}
        // Ensure we have data when entering grouped mode
        if (v === 'groups' && !this.loading && (!this.groups || this.groups.length === 0)) {
          try { this.load(); } catch (_) {}
        }
      },

      async openDrawer() {
        this.drawerOpen = true;
        await this.load();
        this.$nextTick?.(() => {
          try { document.getElementById('ebdg-new-name')?.focus(); } catch (_) {}
        });
      },

      closeDrawer() {
        this.drawerOpen = false;
        this.creating = false;
        this.newName = '';
        this.search = '';
        this.cancelRename();
        this.cancelDelete();
      },

      filteredGroups() {
        const q = (this.search || '').trim().toLowerCase();
        if (!q) return this.groups || [];
        return (this.groups || []).filter(g => String(g.name || '').toLowerCase().includes(q));
      },

      groupById(id) {
        const gid = Number(id) || 0;
        return (this.groups || []).find(g => Number(g.id) === gid) || null;
      },

      async load() {
        this.loading = true;
        try {
          const r = await api('list');
          if (r && r.status === 'success') {
            this.groups = Array.isArray(r.groups) ? r.groups : [];
            this.assignments = (r.assignments && typeof r.assignments === 'object') ? r.assignments : {};
            this.touch();
            return;
          }
          toast((r && r.message) ? r.message : 'Failed to load groups.', 'error');
        } catch (_) {
          toast('Failed to load groups.', 'error');
        } finally {
          this.loading = false;
        }
      },

      startCreate() {
        this.creating = true;
        this.newName = '';
        this.newColor = '';
        this.newIcon = '';
        this.$nextTick?.(() => { try { document.getElementById('ebdg-new-name')?.focus(); } catch (_) {} });
      },

      cancelCreate() {
        this.creating = false;
        this.newName = '';
        this.newColor = '';
        this.newIcon = '';
      },

      async create() {
        const name = normName(this.newName);
        if (!name) { toast('Group name is required.', 'warning'); return; }

        this.savingCreate = true;
        try {
          const r = await api('createGroup', { name, color: this.newColor || '', icon: this.newIcon || '' });
          if (r && r.status === 'success' && r.group) {
            this.groups = (this.groups || []).concat([r.group]).sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
            this.cancelCreate();
            this.touch();
            toast('Group created', 'success');
            return;
          }
          toast((r && r.message) ? r.message : 'Couldn’t create group. Please try again.', 'error');
        } catch (_) {
          toast('Couldn’t create group. Please try again.', 'error');
        } finally {
          this.savingCreate = false;
        }
      },

      startRename(id) {
        const g = this.groupById(id);
        if (!g) return;
        this.renameId = Number(g.id);
        this.renameValue = String(g.name || '');
        this.$nextTick?.(() => { try { document.getElementById('ebdg-rename-input-' + this.renameId)?.focus(); } catch (_) {} });
      },

      cancelRename() {
        this.renameId = null;
        this.renameValue = '';
        this.savingRename = false;
      },

      async commitRename() {
        const gid = Number(this.renameId) || 0;
        if (!gid) return;
        const name = normName(this.renameValue);
        if (!name) { toast('Group name is required.', 'warning'); return; }

        this.savingRename = true;
        try {
          const r = await api('renameGroup', { group_id: gid, name });
          if (r && r.status === 'success') {
            this.groups = (this.groups || []).map(g => (Number(g.id) === gid ? Object.assign({}, g, { name }) : g));
            this.cancelRename();
            this.touch();
            toast('Group renamed', 'success');
            return;
          }
          toast((r && r.message) ? r.message : 'Couldn’t rename group. Please try again.', 'error');
        } catch (_) {
          toast('Couldn’t rename group. Please try again.', 'error');
        } finally {
          this.savingRename = false;
        }
      },

      promptDelete(id) {
        const g = this.groupById(id);
        if (!g) return;
        this.deleteId = Number(g.id);
        this.deleteName = String(g.name || '');
        this.deleteCount = Number(g.count || 0);
      },

      cancelDelete() {
        this.deleteId = null;
        this.deleteName = '';
        this.deleteCount = 0;
        this.deleting = false;
      },

      async confirmDelete() {
        const gid = Number(this.deleteId) || 0;
        if (!gid) return;
        this.deleting = true;
        try {
          const r = await api('deleteGroup', { group_id: gid });
          if (r && r.status === 'success') {
            const moved = Number(r.moved || 0);
            this.groups = (this.groups || []).filter(g => Number(g.id) !== gid);
            this.cancelDelete();
            this.touch();
            toast(`Group deleted. ${moved} device(s) moved to Ungrouped.`, 'success');
            // Refresh counts + assignments (cheap, keeps UI truthful)
            this.load();
            return;
          }
          toast((r && r.message) ? r.message : 'Couldn’t delete group. Please try again.', 'error');
        } catch (_) {
          toast('Couldn’t delete group. Please try again.', 'error');
        } finally {
          this.deleting = false;
        }
      },

      // Reorder via HTML5 drag
      dragStart(id) {
        this.dragId = Number(id) || null;
      },
      dragEnd() {
        this.dragId = null;
      },
      async dropBefore(targetId) {
        const src = Number(this.dragId) || 0;
        const tgt = Number(targetId) || 0;
        if (!src || !tgt || src === tgt) return;

        const arr = (this.groups || []).slice(0);
        const srcIdx = arr.findIndex(g => Number(g.id) === src);
        const tgtIdx = arr.findIndex(g => Number(g.id) === tgt);
        if (srcIdx < 0 || tgtIdx < 0) return;

        const [moved] = arr.splice(srcIdx, 1);
        const insertAt = tgtIdx + (srcIdx < tgtIdx ? -1 : 0);
        arr.splice(Math.max(0, insertAt), 0, moved);

        // optimistic
        this.groups = arr;
        this.touch();
        try {
          const ordered_ids = arr.map(g => Number(g.id));
          const r = await api('reorderGroups', { ordered_ids });
          if (r && r.status === 'success') return;
          toast((r && r.message) ? r.message : 'Couldn’t update group order.', 'error');
          // refresh from server to recover
          this.load();
        } catch (_) {
          toast('Couldn’t update group order.', 'error');
          this.load();
        }
      }
      });

      try { Alpine.store('ebDeviceGroups').init?.(); } catch (_) {}
    } catch (_) {}
  }

  function portalDrawerToBody() {
    try {
      const root = document.getElementById('ebdg-drawer-root');
      if (!root) return;
      if (root.parentElement === document.body) return;
      document.body.appendChild(root);
    } catch (_) {}
  }

  // Register in all lifecycle timings:
  // - if script loads before Alpine: alpine:init will fire later
  // - if script loads after Alpine init: queueMicrotask / DOMContentLoaded will register immediately
  document.addEventListener('alpine:init', registerStore);
  try { queueMicrotask(registerStore); } catch (_) { setTimeout(registerStore, 0); }
  document.addEventListener('DOMContentLoaded', registerStore);
  document.addEventListener('DOMContentLoaded', portalDrawerToBody);
})();


