document.addEventListener('DOMContentLoaded', function () {
  if (typeof window.showToast !== 'function') {
    window.showToast = function(message, type) {
      try {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const id = 't' + Date.now();
        const base = 'px-4 py-2 rounded shadow text-sm text-white';
        let color = 'bg-slate-700';
        if (type === 'success') color = 'bg-green-600';
        if (type === 'error') color = 'bg-red-600';
        if (type === 'warning') color = 'bg-yellow-600';
        const el = document.createElement('div');
        el.id = id;
        el.className = base + ' ' + color;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => { el.classList.add('opacity-0', 'transition-opacity', 'duration-700'); }, 2200);
        setTimeout(() => { el.remove(); }, 3000);
      } catch (_) {}
    }
  }
  const panel = document.getElementById('device-slide-panel');
  if (!panel) return;

  const closeBtn = document.getElementById('device-panel-close');
  const titleEl = document.getElementById('device-panel-title');
  const deviceNameEl = document.getElementById('device-panel-name');
  const vaultMenuBtn = document.getElementById('vault-menu-button');
  const vaultMenu = document.getElementById('vault-menu');
  const vaultSelected = document.getElementById('vault-selected');
  const endpoint = window.EB_DEVICE_ENDPOINT;
  const serviceId = document.body.getAttribute('data-eb-serviceid');
  const username = document.body.getAttribute('data-eb-username');

  let currentDeviceId = '';
  let currentDeviceName = '';
  let currentDeviceOnline = false;
  let currentVaultId = '';

  function openPanel(deviceId, deviceName) {
    currentDeviceId = deviceId;
    currentDeviceName = deviceName;
    if (titleEl) titleEl.textContent = 'Manage Device';
    if (deviceNameEl) deviceNameEl.textContent = deviceName || '';
    panel.classList.remove('translate-x-full');
    // Load protected items for this device
    loadProtectedItems();
  }
  function closePanel() { panel.classList.add('translate-x-full'); }

  document.querySelectorAll('[data-action="open-device-panel"]').forEach(btn => {
    btn.addEventListener('click', () => {
      currentDeviceOnline = (btn.getAttribute('data-device-online') === '1');
      openPanel(btn.getAttribute('data-device-id'), btn.getAttribute('data-device-name'));
    });
  });
  closeBtn && closeBtn.addEventListener('click', closePanel);

  // Vault menu interactions
  if (vaultMenuBtn && vaultMenu) {
    vaultMenuBtn.addEventListener('click', () => { vaultMenu.classList.toggle('hidden'); });
    document.addEventListener('click', (e) => {
      if (!vaultMenu.contains(e.target) && e.target !== vaultMenuBtn) { vaultMenu.classList.add('hidden'); }
    });
    vaultMenu.querySelectorAll('[data-vault-id]').forEach(item => {
      item.addEventListener('click', () => {
        currentVaultId = item.getAttribute('data-vault-id');
        if (vaultSelected) vaultSelected.textContent = item.getAttribute('data-vault-name') || currentVaultId;
        vaultMenu.classList.add('hidden');
      });
    });
  }

  // Helpers
  async function call(action, extra = {}) {
    const body = Object.assign({ action, serviceId, username, deviceId: currentDeviceId }, extra);
    const res = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return res.json();
  }
  function toast(msg, kind) { try { window.showToast?.(msg, kind || 'info'); } catch (_) {} }

  // Buttons
  async function loadProtectedItems() {
    try {
      const r = await call('listProtectedItems');
      const ul = document.getElementById('pi-list');
      if (!ul) return;
      ul.innerHTML = '';
      if (r && r.status === 'success' && Array.isArray(r.items) && r.items.length) {
        r.items.forEach(it => {
          const li = document.createElement('li');
          const a = document.createElement('a');
          a.href = '#';
          a.className = 'block px-3 py-2 hover:bg-slate-700';
          a.textContent = it.name || it.id;
          a.dataset.protectedItemId = it.id;
          a.addEventListener('click', (e) => {
            e.preventDefault();
            const btn = document.getElementById('pi-menu-button');
            const sel = document.getElementById('pi-selected');
            btn && btn.click();
            if (sel) sel.textContent = it.name || it.id;
            document.getElementById('sel-protected-item')?.remove();
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = 'sel-protected-item';
            hidden.value = it.id;
            panel.appendChild(hidden);
          });
          li.appendChild(a);
          ul.appendChild(li);
        });
      } else {
        const li = document.createElement('li');
        li.innerHTML = '<span class="block px-3 py-2 text-slate-400">No protected items found.</span>';
        ul.appendChild(li);
      }
    } catch (_) {
      // ignore
    }
  }
  document.getElementById('btn-update-software')?.addEventListener('click', async () => {
    if (!currentDeviceOnline) { toast('Device must be online to update.', 'warning'); return; }
    const ok = await tailwindConfirm('Update Software', 'Request the device to update Comet client now?');
    if (!ok) return;
    const r = await call('updateSoftware');
    toast(r.message || (r.status === 'success' ? 'Update requested.' : 'Update failed'), r.status === 'success' ? 'success' : 'error');
  });
  document.getElementById('btn-uninstall-software')?.addEventListener('click', async () => {
    if (!currentDeviceOnline) { toast('Device must be online to uninstall.', 'warning'); return; }
    const ok = await tailwindConfirm('Uninstall Software', 'Uninstall the Comet client from this device?');
    if (!ok) return;
    const r = await call('uninstallSoftware', { removeConfig: false });
    toast(r.message || (r.status === 'success' ? 'Uninstall requested.' : 'Uninstall failed'), r.status === 'success' ? 'success' : 'error');
  });
  document.getElementById('btn-revoke-device')?.addEventListener('click', async () => {
    const ok = await tailwindConfirm('Revoke Device', 'Revoke this device from the user account?');
    if (!ok) return;
    const r = await call('revokeDevice');
    toast(r.message || (r.status === 'success' ? 'Device revoked.' : 'Revoke failed'), r.status === 'success' ? 'success' : 'error');
    if (r.status === 'success') { setTimeout(()=>window.location.reload(), 800); }
  });
  document.getElementById('btn-apply-retention')?.addEventListener('click', async () => {
    if (!currentVaultId) { toast('Select a Storage Vault first.', 'warning'); return; }
    const r = await call('applyRetention', { vaultId: currentVaultId });
    toast(r.message || (r.status === 'success' ? 'Retention requested.' : 'Retention failed'), r.status === 'success' ? 'success' : 'error');
  });
  document.getElementById('btn-reindex-vault')?.addEventListener('click', async () => {
    if (!currentVaultId) { toast('Select a Storage Vault first.', 'warning'); return; }
    const ok = await tailwindConfirm('Reindex Storage Vault', 'Reindex may take hours and will lock the Storage Vault. Continue?');
    if (!ok) return;
    const r = await call('reindexVault', { vaultId: currentVaultId });
    toast(r.message || (r.status === 'success' ? 'Reindex requested.' : 'Reindex failed'), r.status === 'success' ? 'success' : 'error');
  });
  // Reveal Run Backup controls on first button, and execute from footer button
  const revealBtn = document.querySelector('#device-slide-panel #btn-run-backup');
  const executeBtn = document.querySelector('#device-slide-panel .mt-3 button#btn-run-backup-exec');
  if (revealBtn && executeBtn && revealBtn !== executeBtn) {
    revealBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const block = document.querySelector('#device-slide-panel .mt-3.border-t');
      if (block) { block.classList.remove('hidden'); }
    });
  }
  executeBtn?.addEventListener('click', async () => {
    const pi = document.getElementById('sel-protected-item')?.value || '';
    // second vault menu selection
    const vSelName = document.getElementById('vault-selected-2')?.textContent || '';
    let v = currentVaultId || '';
    if (!v && vSelName) {
      const active = document.querySelector('#vault-menu-2 [data-vault-id][data-selected="1"]');
      if (active) v = active.getAttribute('data-vault-id');
    }
    if (!pi || !v) { toast('Select protected item and vault.', 'warning'); return; }
    const r = await call('runBackup', { protectedItemId: pi, vaultId: v });
    toast(r.message || (r.status === 'success' ? 'Backup requested.' : 'Backup failed'), r.status === 'success' ? 'success' : 'error');
  });
  document.getElementById('btn-rename-device')?.addEventListener('click', async () => {
    const newName = (document.getElementById('inp-rename-device')?.value || '').trim();
    if (!newName) { toast('Enter a new device name.', 'warning'); return; }
    const r = await call('renameDevice', { newName });
    toast(r.message || (r.status === 'success' ? 'Renamed.' : 'Rename failed'), r.status === 'success' ? 'success' : 'error');
    if (r.status === 'success') { setTimeout(()=>window.location.reload(), 800); }
  });

  async function tailwindConfirm(title, message) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60';
      overlay.innerHTML = `
        <div class="bg-slate-800/95 border border-slate-700 rounded-lg shadow-xl w-full max-w-md">
          <div class="px-5 py-4 border-b border-slate-700">
            <h3 class="text-slate-200 text-base font-semibold">${title}</h3>
          </div>
          <div class="px-5 py-4 text-slate-300 text-sm">${message}</div>
          <div class="px-5 py-3 border-t border-slate-700 flex justify-end gap-2">
            <button class="px-4 py-2 text-slate-300 hover:text-white" data-cmd="cancel">Cancel</button>
            <button class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded" data-cmd="ok">Confirm</button>
          </div>
        </div>`;
      function cleanup() { try { overlay.remove(); } catch (_) {} }
      overlay.addEventListener('click', (e) => {
        const cmd = e.target.getAttribute('data-cmd');
        if (cmd === 'ok') { cleanup(); resolve(true); }
        if (cmd === 'cancel' || e.target === overlay) { cleanup(); resolve(false); }
      });
      document.body.appendChild(overlay);
    });
  }

  // Wire second vault menu
  (function wireVault2(){
    const root = document.getElementById('vault-menu-2');
    const btn  = document.getElementById('vault-menu-button-2');
    const sel  = document.getElementById('vault-selected-2');
    if (!root || !btn || !sel) return;
    root.querySelectorAll('[data-vault-id]').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        root.querySelectorAll('[data-vault-id]').forEach(x => x.removeAttribute('data-selected'));
        a.setAttribute('data-selected','1');
        sel.textContent = a.getAttribute('data-vault-name') || a.getAttribute('data-vault-id');
        currentVaultId = a.getAttribute('data-vault-id');
        btn.click();
      });
    });
  })();

  // Restore wizard wiring (Step 1 & 2)
  const restoreBtn = document.getElementById('open-restore');
  const restoreModal = document.getElementById('restore-wizard');
  const rsClose = document.getElementById('restore-close');
  const rsStep1 = document.getElementById('restore-step1');
  const rsStep2 = document.getElementById('restore-step2');
  const rsBack = document.getElementById('restore-back');
  const rsNext = document.getElementById('restore-next');
  const rsStart = document.getElementById('restore-start');
  const rsVaultList = document.getElementById('rs-vault-list');
  const rsItemList = document.getElementById('rs-item-list');
  const rsSnapshots = document.getElementById('rs-snapshots');
  const rsSelectedVault = document.getElementById('rs-selected-vault');
  const rsVaultMenuBtn = document.getElementById('rs-vault-menu-btn');
  const rsVaultMenuList = document.getElementById('rs-vault-menu-list');
  const rsVaultSelectedLabel = document.getElementById('rs-vault-selected-label');
  const rsSelectedItem = document.getElementById('rs-selected-item');
  const rsSelectedSnap = document.getElementById('rs-selected-snapshot');
  const rsEngine = document.getElementById('rs-selected-engine');
  const rsDev = document.getElementById('rs-device-id');
  const rsHint = document.getElementById('rs-engine-hint');

  function rsShow(step) {
    try { rsStep1 && rsStep1.classList.toggle('hidden', step !== 1); } catch (_) {}
    try { rsStep2 && rsStep2.classList.toggle('hidden', step !== 2); } catch (_) {}
    try { rsBack && rsBack.classList.toggle('hidden', step === 1); } catch (_) {}
    try { rsNext && rsNext.classList.toggle('hidden', step !== 1); } catch (_) {}
    try { rsStart && rsStart.classList.add('hidden'); } catch (_) {}
  }

  if (restoreBtn && restoreModal) {
    restoreBtn.addEventListener('click', () => {
      // Resolve any elements that might not have been present earlier
      try {
        rsShow(1);
        if (rsSelectedVault) rsSelectedVault.value = '';
        if (rsSelectedItem) rsSelectedItem.value = '';
        if (rsSelectedSnap) rsSelectedSnap.value = '';
        if (rsEngine) rsEngine.value = '';
        if (rsDev) rsDev.value = currentDeviceId;
        // Wire Alpine-style vault menu
        if (rsVaultMenuList) {
          const seen = new Set();
          rsVaultMenuList.querySelectorAll('[data-rs-vault-id]')?.forEach(a => {
            const vid = a.getAttribute('data-rs-vault-id');
            if (!vid || seen.has(vid)) return; // dedupe
            seen.add(vid);
            a.addEventListener('click', async (e) => {
              e.preventDefault();
              const name = a.getAttribute('data-rs-vault-name') || a.textContent.trim();
              if (rsSelectedVault) rsSelectedVault.value = vid;
              if (rsVaultSelectedLabel) rsVaultSelectedLabel.textContent = name;
              await rsLoadItems();
              await rsLoadSnapshots();
              rsShow(2);
            }, { once:false });
          });
        }
      } catch (_) {}
      restoreModal.classList.remove('hidden');
    });
    rsClose?.addEventListener('click', () => { restoreModal.classList.add('hidden'); });
    rsBack?.addEventListener('click', () => { rsShow(1); });
    rsNext?.addEventListener('click', async () => {
      await rsLoadItems();
      await rsLoadSnapshots();
      rsShow(2);
    });
  }

  // Delegated handler as a fallback in case the direct binding didn’t attach
  document.addEventListener('click', (e) => {
    const trg = e.target && (e.target.id === 'open-restore' ? e.target : e.target.closest && e.target.closest('#open-restore'));
    if (!trg) return;
    e.preventDefault();
    let modal = document.getElementById('restore-wizard');
    if (!modal) {
      // Build a minimal modal so the feature works even if template markup is absent
      const m = document.createElement('div');
      m.id = 'restore-wizard';
      m.className = 'fixed inset-0 z-50';
      m.innerHTML = `
        <div class="absolute inset-0 bg-black bg-opacity-60"></div>
        <div class="relative mx-auto my-6 w-full max-w-3xl bg-slate-900 border border-slate-700 rounded-lg shadow-xl">
          <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
            <h3 class="text-slate-200 text-lg font-semibold">Restore Wizard</h3>
            <button id="restore-close" class="text-slate-400 hover:text-slate-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-5 py-4">
            <div id="restore-step1">
              <div class="text-sm text-slate-300 mb-2">Select a Storage Vault</div>
              <div id="rs-vault-list" class="grid grid-cols-1 md:grid-cols-2 gap-2"></div>
            </div>
            <div id="restore-step2" class="hidden">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><div class="text-sm text-slate-300 mb-2">Protected Items</div><div id="rs-item-list" class="border border-slate-700 rounded bg-slate-900/40 max-h-60 overflow-y-auto text-sm text-slate-200"></div></div>
                <div><div class="text-sm text-slate-300 mb-2">Snapshots</div><div id="rs-snapshots" class="border border-slate-700 rounded bg-slate-900/40 max-h-60 overflow-y-auto text-sm text-slate-200"></div></div>
              </div>
              <div id="rs-engine-hint" class="mt-3 text-xs text-slate-400"></div>
              <div id="rs-methods" class="mt-3 hidden"><div id="rs-method-options" class="space-y-2 text-sm text-slate-200"></div>
                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div><label class="block text-sm text-slate-300 mb-1">Destination path</label><input id="rs-dest" type="text" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200"></div>
                  <div><label class="block text-sm text-slate-300 mb-1">Overwrite</label><select id="rs-overwrite" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200"><option value="none">Never</option><option value="ifNewer">If newer</option><option value="ifDifferent">If different</option><option value="always">Always</option></select></div>
                </div>
              </div>
            </div>
            <div class="flex justify-between items-center mt-4 border-t border-slate-800 pt-3">
              <button id="restore-back" class="px-4 py-2 text-slate-300">Back</button>
              <div class="space-x-2">
                <button id="restore-next" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded">Next</button>
                <button id="restore-start" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded hidden">Start Restore</button>
              </div>
            </div>
          </div>
        </div>
        <input type="hidden" id="rs-selected-vault" value=""><input type="hidden" id="rs-selected-item" value=""><input type="hidden" id="rs-selected-snapshot" value=""><input type="hidden" id="rs-selected-engine" value=""><input type="hidden" id="rs-device-id" value="">
      `;
      document.body.appendChild(m);
      modal = m;
      // Populate vaults into Alpine menu if present (fallback modal only)
      const sourceVaults = document.querySelectorAll('#vault-menu-2 [data-vault-id], #vault-menu [data-vault-id]');
      const list = modal.querySelector('#rs-vault-list');
      const alpineList = modal.querySelector('#rs-vault-menu-list ul');
      if (alpineList && sourceVaults && sourceVaults.length) {
        const seen = new Set();
        sourceVaults.forEach(a => {
          const vid = a.getAttribute('data-vault-id');
          if (!vid || seen.has(vid)) return; seen.add(vid);
          const li = document.createElement('li');
          li.innerHTML = `<a href="#" class="block px-3 py-2 hover:bg-slate-700" data-rs-vault-id="${vid}" data-rs-vault-name="${a.getAttribute('data-vault-name') || a.textContent.trim()}">${a.getAttribute('data-vault-name') || a.textContent.trim()}</a>`;
          alpineList.appendChild(li);
        });
      } else if (list && sourceVaults && sourceVaults.length) {
        // very old fallback grid layout
        const seen = new Set();
        sourceVaults.forEach(a => {
          const vid = a.getAttribute('data-vault-id');
          if (!vid || seen.has(vid)) return; seen.add(vid);
          const btn = document.createElement('button');
          btn.className = 'text-left px-3 py-2 rounded border border-slate-600 bg-slate-800 hover:bg-slate-700 text-slate-200';
          btn.setAttribute('data-rs-vault-id', vid);
          btn.setAttribute('data-rs-vault-name', a.getAttribute('data-vault-name') || a.textContent.trim());
          btn.textContent = a.getAttribute('data-vault-name') || a.textContent.trim();
          list.appendChild(btn);
        });
      }
      // Rebind core refs
      try {
        rsClose = document.getElementById('restore-close');
        rsStep1 = document.getElementById('restore-step1');
        rsStep2 = document.getElementById('restore-step2');
        rsBack = document.getElementById('restore-back');
        rsNext = document.getElementById('restore-next');
        rsStart = document.getElementById('restore-start');
        rsVaultList = document.getElementById('rs-vault-list');
        rsItemList = document.getElementById('rs-item-list');
        rsSnapshots = document.getElementById('rs-snapshots');
        rsSelectedVault = document.getElementById('rs-selected-vault');
        rsSelectedItem = document.getElementById('rs-selected-item');
        rsSelectedSnap = document.getElementById('rs-selected-snapshot');
        rsEngine = document.getElementById('rs-selected-engine');
        rsDev = document.getElementById('rs-device-id');
        rsHint = document.getElementById('rs-engine-hint');
      } catch (_){}
    }
    try {
      rsShow(1);
      if (rsSelectedVault) rsSelectedVault.value = '';
      if (rsSelectedItem) rsSelectedItem.value = '';
      if (rsSelectedSnap) rsSelectedSnap.value = '';
      if (rsEngine) rsEngine.value = '';
      if (rsDev) rsDev.value = currentDeviceId;
    } catch (_) {}
    modal.classList.remove('hidden');
  });

  async function rsLoadItems() {
    try {
      rsItemList.innerHTML = '<div class="px-3 py-2 text-slate-400">Loading items…</div>';
      const r = await call('listProtectedItems');
      const items = (r && r.items) ? r.items : [];
      if (!items.length) { rsItemList.innerHTML = '<div class="px-3 py-2 text-slate-400">No protected items for this device.</div>'; return; }
      rsItemList.innerHTML = '';
      items.forEach(it => {
        const a = document.createElement('a');
        a.href = '#';
        a.className = 'block px-3 py-2 hover:bg-slate-800';
        a.textContent = it.name || it.id;
        a.dataset.sourceId = it.id;
        a.addEventListener('click', (e) => {
          e.preventDefault();
          rsSelectedItem.value = it.id;
          // highlight
          rsItemList.querySelectorAll('a').forEach(x => x.classList.remove('bg-slate-800'));
          a.classList.add('bg-slate-800');
          // update snapshots panel selection highlighting
          rsFilterSnapshots();
        });
        rsItemList.appendChild(a);
      });
    } catch (_) {
      rsItemList.innerHTML = '<div class="px-3 py-2 text-rose-400">Failed to load items.</div>';
    }
  }

  let rsSnapshotsData = [];
  async function rsLoadSnapshots() {
    try {
      rsSnapshots.innerHTML = '<div class="px-3 py-2 text-slate-400">Loading snapshots…</div>';
      const vaultId = rsSelectedVault.value;
      if (!vaultId) { rsSnapshots.innerHTML = '<div class="px-3 py-2 text-slate-400">Select a vault first.</div>'; return; }
      const res = await fetch(endpoint, { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ action:'vaultSnapshots', serviceId, username, deviceId: currentDeviceId, vaultId }) });
      const data = await res.json();
      const arr = (data && data.snapshots && data.snapshots.Snapshots) ? data.snapshots.Snapshots : [];
      rsSnapshotsData = arr;
      rsRenderSnapshots();
    } catch (_) {
      rsSnapshots.innerHTML = '<div class="px-3 py-2 text-rose-400">Failed to load snapshots.</div>';
    }
  }

  function rsRenderSnapshots() {
    const sourceId = rsSelectedItem.value;
    const subset = rsSnapshotsData.filter(s => (s.Source === sourceId));
    if (!subset.length) { rsSnapshots.innerHTML = '<div class="px-3 py-2 text-slate-400">Select a protected item to view snapshots.</div>'; return; }
    rsSnapshots.innerHTML = '';
    subset.sort((a,b)=> (b.CreateTime||0)-(a.CreateTime||0));
    subset.forEach(s => {
      const row = document.createElement('a');
      row.href = '#';
      row.className = 'block px-3 py-2 hover:bg-slate-800';
      const when = s.CreateTime ? new Date(s.CreateTime*1000).toLocaleString() : s.Snapshot;
      row.textContent = `${when}`;
      row.addEventListener('click', (e)=>{
        e.preventDefault();
        rsSelectedSnap.value = s.Snapshot;
        rsEngine.value = s.EngineType || '';
        rsHint.textContent = s.EngineType ? `Engine: ${s.EngineType}` : '';
        rsSnapshots.querySelectorAll('a').forEach(x => x.classList.remove('bg-slate-800'));
        row.classList.add('bg-slate-800');
        rsStart.classList.remove('hidden');
        rsBuildMethodOptions();
      });
      rsSnapshots.appendChild(row);
    });
  }

  function rsFilterSnapshots() { rsRenderSnapshots(); }

  // Basic method selection (engine1/file only for this step)
  rsStart?.addEventListener('click', async () => {
    // For now, default to Files & Folders restore with minimal overwrite logic
    const engine = (rsEngine.value || '').toLowerCase();
    const sourceId = rsSelectedItem.value;
    const vaultId = rsSelectedVault.value;
    const snapshot = rsSelectedSnap.value;
    if (!sourceId || !vaultId) { toast('Select vault, item and snapshot.', 'warning'); return; }

    // Read from method UI
    const destPath = document.getElementById('rs-dest')?.value || '';
    const overwrite = document.getElementById('rs-overwrite')?.value || 'none';
    const method = document.querySelector('input[name="rs-method"]:checked')?.value || 'file';

    // Choose RESTORETYPE based on engine
    let type = 0; // RESTORETYPE_FILE default
    if (engine === 'engine1/windisk') { type = 4; } // RESTORETYPE_WINDISK
    if (method === 'archive') { type = 5; } // RESTORETYPE_FILE_ARCHIVE
    if (method === 'simulate') { type = 1; } // RESTORETYPE_NULL (dry-run)
    // engine1/hyperv will get richer options in later step

    try {
      const res = await fetch(endpoint, { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ action:'runRestore', serviceId, username, deviceId: currentDeviceId, sourceId, vaultId, snapshot, type, destPath, overwrite }) });
      const data = await res.json();
      toast(data.message || (data.status === 'success' ? 'Restore requested.' : 'Restore failed'), data.status === 'success' ? 'success' : 'error');
      if (data.status === 'success') { restoreModal.classList.add('hidden'); }
    } catch (_) {
      toast('Network error while requesting restore.', 'error');
    }
  });

  function rsBuildMethodOptions() {
    const engine = (rsEngine.value || '').toLowerCase();
    const box = document.getElementById('rs-methods');
    const wrap = document.getElementById('rs-method-options');
    if (!box || !wrap) return;
    wrap.innerHTML = '';
    let options = [];
    if (engine === 'engine1/file' || engine === '') {
      options = [
        { v:'file', label:'Files and Folders: restore backed up files' },
        { v:'archive', label:'Compressed archive (tar/zip): create an archive' },
        { v:'simulate', label:'Simulate restore only (no files written)' }
      ];
    } else if (engine === 'engine1/windisk') {
      options = [
        { v:'file', label:'Files and Folders: restore disk image files' }
      ];
    } else if (engine === 'engine1/hyperv') {
      options = [
        { v:'file', label:'Files and Folders: restore Hyper‑V files' }
        // Future: add hypervisor direct restore option
      ];
    } else {
      options = [ { v:'file', label:'Files and Folders' } ];
    }
    options.forEach((opt, idx) => {
      const id = `rs-m-${opt.v}`;
      const row = document.createElement('label');
      row.className = 'flex items-center gap-2 text-slate-200 cursor-pointer';
      row.innerHTML = `<input type="radio" name="rs-method" id="${id}" value="${opt.v}" ${idx===0?'checked':''} class="rounded border-slate-600 bg-slate-700 text-sky-600"> <span>${opt.label}</span>`;
      wrap.appendChild(row);
    });
    box.classList.remove('hidden');
  }
});


