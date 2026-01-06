// Versions data store for a single selected object.
// Uses existing versions endpoint and restore API from the WHMCS addon.

export function createVersionsStore(options) {
  const {
    versionsUrl = '/modules/addons/cloudstorage/api/objectversions.php',
    username,
    bucket,
    estimateRow = 44,
  } = options;

  const state = {
    open: false,
    key: '',
    items: [],             // combined versions + delete markers
    loading: false,
    error: null,
    nextKeyMarker: '',
    nextVersionIdMarker: '',
    maxRows: 2000,
  };

  function reset() {
    state.items = [];
    state.loading = false;
    state.error = null;
    state.nextKeyMarker = '';
    state.nextVersionIdMarker = '';
  }

  async function openFor(key) {
    state.key = key;
    state.open = true;
    reset();
    await loadNext();
  }

  async function loadNext() {
    if (state.loading) return;
    state.loading = true;
    state.error = null;
    try {
      const body = new URLSearchParams({
        bucket: bucket || '',
        mode: 'details',
        key: state.key,
        include_details: '1',
      });
      if (username) {
        body.set('username', username);
      }
      if (state.nextKeyMarker) body.set('key_marker', state.nextKeyMarker);
      if (state.nextVersionIdMarker) body.set('version_id_marker', state.nextVersionIdMarker);

      const resp = await fetch(versionsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
      });
      const data = await resp.json();
      if (data && data.redirect) {
        try { window.location.href = data.redirect; } catch (e) {}
        return;
      }
      if (!data || data.status === 'fail') {
        throw new Error((data && data.message) || 'Failed to load versions');
      }

      const rows = [];
      const versions = (data.data && data.data.versions) || [];
      const markers = (data.data && data.data.delete_markers) || [];
      versions.forEach(v => rows.push({ type: 'version', ...v }));
      markers.forEach(m => rows.push({ type: 'delete_marker', ...m }));
      state.items = state.items.concat(rows);
      state.nextKeyMarker = (data.data && data.data.next_key_marker) || '';
      state.nextVersionIdMarker = (data.data && data.data.next_version_id_marker) || '';
      if (state.items.length > state.maxRows) {
        state.items.splice(0, state.items.length - state.maxRows);
      }
    } catch (e) {
      state.error = e.message || String(e);
    } finally {
      state.loading = false;
    }
  }

  async function restore(versionId, metadataDirective = 'COPY') {
    if (!state.key || !versionId) return { ok: false, message: 'Missing key/version' };
    try {
      const body = new URLSearchParams({
        bucket: bucket || '',
        mode: 'restore',
        key: state.key,
        source_version_id: versionId,
        metadata_directive: metadataDirective,
      });
      if (username) {
        body.set('username', username);
      }
      const resp = await fetch(versionsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
      });
      const data = await resp.json();
      if (data && data.status === 'success') {
        return { ok: true, message: data.message || 'Restored' };
      }
      return { ok: false, message: (data && data.message) || 'Restore failed' };
    } catch (e) {
      return { ok: false, message: e.message || String(e) };
    }
  }

  return {
    state,
    openFor,
    loadNext,
    restore,
    reset,
  };
}


