// Objects data store for Tailwind + Alpine virtual grid
// Uses existing WHMCS cloudstorage endpoints

import { createVirtualizer, throttle } from './virtual-grid.js';

export function createObjectsStore(options) {
  const {
    apiUrl = '/modules/addons/cloudstorage/api/bucketobjects.php',
    username,
    bucket,
    prefix = '',
    pageSize = 250,
    scrollEstimate = 48,
  } = options;

  const state = {
    items: [],                  // loaded rows (files and folders)
    continuationToken: '',      // server paging token
    loading: false,
    error: null,
    sortKey: 'name',            // name|size|modified
    sortDir: 'asc',             // asc|desc
    filter: '',
    selected: new Set(),        // Set<key>
    focusedIndex: 0,
    prefix,
    maxRows: 5000,              // trim to avoid DOM/memory bloat
    virtual: { items: [], totalSize: 0, scrollTop: 0 },
    ready: false,
  };

  let scrollEl = null;
  let virtualizer = null;

  function setScrollContainer(el) {
    scrollEl = el;
  }

  function getCount() {
    // Apply filter if needed
    if (state.filter) {
      return state.items.filter(r => (r.name || '').toLowerCase().includes(state.filter)).length;
    }
    return state.items.length;
  }

  async function initVirtualizer() {
    if (!scrollEl) return;
    virtualizer = await createVirtualizer({
      scrollContainer: scrollEl,
      getCount,
      estimateSize: scrollEstimate,
      overscan: 12,
      onChange({ items, totalSize, scrollTop }) {
        state.virtual.items = items;
        state.virtual.totalSize = totalSize;
        state.virtual.scrollTop = scrollTop;
      },
    });
  }

  function getVisibleRows() {
    // Map virtual items to row data + offset
    return state.virtual.items.map(v => {
      const row = state.items[v.index];
      return row
        ? { ...row, __index: v.index, __offset: v.start, __size: v.size }
        : { name: '', type: '', size: '', modified: '', __index: v.index, __offset: v.start, __size: v.size, empty: true };
    });
  }

  function applyRowTrim() {
    if (state.items.length > state.maxRows) {
      const remove = state.items.length - state.maxRows;
      state.items.splice(0, remove);
      // also adjust selected: remove keys that were trimmed if we had their names
    }
  }

  async function fetchPage(token = '') {
    state.loading = true;
    state.error = null;
    try {
      const body = new URLSearchParams({
        bucket: bucket || '',
        folder_path: state.prefix || '',
        max_keys: String(pageSize),
      });
      // Only send username when explicitly provided (avoid empty string mismatch)
      if (username) {
        body.set('username', username);
      }
      if (token) body.set('continuation_token', token);
      const resp = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
      });
      const data = await resp.json();
      if (data && data.redirect) {
        try { window.location.href = data.redirect; } catch (e) {}
        return;
      }
      console.log('Objects store: API response', { status: data?.status, message: data?.message, dataLength: Array.isArray(data?.data) ? data.data.length : undefined, data: data });
      if (!data || data.status === 'fail') {
        throw new Error((data && data.message) || 'Failed to load objects');
      }
      const rows = Array.isArray(data.data) ? data.data : [];
      console.log('Objects store: Parsed rows', rows.length, rows);
      // normalize rows
      rows.forEach(r => {
        r.key = r.name;
      });
      state.items = state.items.concat(rows);
      state.continuationToken = data.continuationToken || '';
      applyRowTrim();
      console.log('Objects store: Final state.items.length', state.items.length);
      // Refresh virtualizer if it exists
      if (virtualizer) {
        virtualizer.refresh();
        console.log('Objects store: Virtualizer refreshed after data load');
      }
    } catch (e) {
      state.error = e.message || String(e);
      console.error('Objects store: fetchPage error', state.error);
      // Re-throw so callers can handle fallback behavior
      throw e;
    } finally {
      state.loading = false;
    }
  }

  async function reset(newPrefix) {
    state.items = [];
    state.continuationToken = '';
    state.prefix = newPrefix ?? state.prefix ?? '';
    state.selected.clear();
    state.focusedIndex = 0;
    state.error = null;
    state.ready = false;
    try {
      await fetchPage('');
    } catch (e) {
      // Keep state.error populated; let caller know this reset experienced a failure
    }
    state.ready = true;
    console.log('Objects store: reset complete', { itemsCount: state.items.length, hasVirtualizer: !!virtualizer });
    if (virtualizer) {
      virtualizer.refresh();
      console.log('Objects store: virtualizer refreshed');
    }
  }

  async function loadNext() {
    if (state.loading) return;
    if (!state.continuationToken) return;
    await fetchPage(state.continuationToken);
    if (virtualizer) virtualizer.refresh();
  }

  function toggleSelect(key) {
    if (!key) return;
    if (state.selected.has(key)) state.selected.delete(key);
    else state.selected.add(key);
  }
  function clearSelection() { state.selected.clear(); }
  function isSelected(key) { return state.selected.has(key); }

  // Keyboard navigation
  function focusNext() {
    state.focusedIndex = Math.min(state.items.length - 1, state.focusedIndex + 1);
  }
  function focusPrev() {
    state.focusedIndex = Math.max(0, state.focusedIndex - 1);
  }

  // Sorting (client-side on loaded rows)
  function setSort(key) {
    if (state.sortKey === key) {
      state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      state.sortKey = key;
      state.sortDir = 'asc';
    }
    const dir = state.sortDir === 'asc' ? 1 : -1;
    const keySel = key;
    state.items.sort((a, b) => {
      const av = (a[keySel] || '').toString().toLowerCase();
      const bv = (b[keySel] || '').toString().toLowerCase();
      if (av < bv) return -1 * dir;
      if (av > bv) return 1 * dir;
      return 0;
    });
    if (virtualizer) virtualizer.refresh();
  }

  // Search filter (client-side on loaded rows); future: server q param
  function setFilter(text) {
    state.filter = (text || '').trim().toLowerCase();
  }
  function filteredItems() {
    if (!state.filter) return state.items;
    const f = state.filter;
    return state.items.filter(r => (r.name || '').toLowerCase().includes(f));
  }

  // Infinite loader trigger helper
  const onNearBottom = throttle(() => {
    if (!scrollEl) return;
    const remaining = (scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight);
    if (remaining < 800) {
      loadNext();
    }
  }, 200);

  function attachScrollListener() {
    if (!scrollEl) return;
    // IntersectionObserver sentinel
    try {
      const sentinel = document.createElement('div');
      sentinel.setAttribute('data-sentinel', '1');
      sentinel.style.height = '1px';
      sentinel.style.width = '100%';
      sentinel.style.position = 'absolute';
      sentinel.style.top = '0';
      sentinel.style.left = '0';
      // place near end of content space
      sentinel.style.transform = 'translateY(999999px)';
      scrollEl.appendChild(sentinel);
      const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            loadNext();
          }
        });
      }, { root: scrollEl, rootMargin: '1200px 0px', threshold: 0 });
      io.observe(sentinel);
      scrollEl._objIO = io;
      scrollEl._objSentinel = sentinel;
    } catch (e) {
      // Fallback to scroll threshold
      scrollEl.addEventListener('scroll', onNearBottom, { passive: true });
    }
  }
  function detachScrollListener() {
    if (!scrollEl) return;
    try {
      if (scrollEl._objIO && scrollEl._objSentinel) {
        scrollEl._objIO.unobserve(scrollEl._objSentinel);
        scrollEl.removeChild(scrollEl._objSentinel);
      }
    } catch (e) {}
    scrollEl.removeEventListener('scroll', onNearBottom);
  }

  function getVirtualizer() {
    return virtualizer;
  }

  return {
    state,
    setScrollContainer,
    initVirtualizer,
    getVirtualizer,
    getVisibleRows,
    reset,
    loadNext,
    toggleSelect,
    clearSelection,
    isSelected,
    focusNext,
    focusPrev,
    setSort,
    setFilter,
    attachScrollListener,
    detachScrollListener,
  };
}


