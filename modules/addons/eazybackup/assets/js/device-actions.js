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
  const executeBtn = document.querySelector('#device-slide-panel .mt-3 button#btn-run-backup');
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
});


