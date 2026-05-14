/**
 * eazyBackup Protected Items table - row controls.
 *
 * Wires Run / Edit / Delete buttons on each row to the wizard and
 * device-actions endpoint. Rows are emitted by Smarty with data-* attrs.
 */
(function () {
  function ctx() {
    return {
      endpoint: window.EB_DEVICE_ENDPOINT || '',
      serviceId: document.body.getAttribute('data-eb-serviceid') || '',
      username: document.body.getAttribute('data-eb-username') || ''
    };
  }
  function call(action, extra) {
    var c = ctx();
    if (!c.endpoint) return Promise.resolve({ status: 'error', message: 'Endpoint missing' });
    var body = Object.assign({ action: action, serviceId: c.serviceId, username: c.username }, extra || {});
    return fetch(c.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(function (r) { return r.json(); });
  }

  function parseRules(btn) {
    var raw = btn.getAttribute('data-pi-rules') || '[]';
    try {
      var dec = (raw.indexOf('&quot;') >= 0) ? raw.replace(/&quot;/g, '"') : raw;
      var arr = JSON.parse(dec);
      return Array.isArray(arr) ? arr : [];
    } catch (_) { return []; }
  }

  function pickVaultForRun(rules) {
    if (!rules.length) return Promise.resolve(null);
    if (rules.length === 1) return Promise.resolve(rules[0].destination || null);
    return new Promise(function (resolve) {
      var labels = rules.map(function (r, i) { return (i + 1) + '. ' + (r.name || r.destination); }).join('\n');
      var pick = window.prompt('This Protected Item has multiple schedules. Choose a Storage Vault to back up to (1-' + rules.length + '):\n' + labels, '1');
      if (!pick) return resolve(null);
      var idx = parseInt(pick, 10) - 1;
      if (isNaN(idx) || idx < 0 || idx >= rules.length) return resolve(null);
      resolve(rules[idx].destination);
    });
  }

  function onClick(e) {
    var btn = e.target && e.target.closest && e.target.closest('[data-pi-action]');
    if (!btn) return;
    var action = btn.getAttribute('data-pi-action');
    var itemId = btn.getAttribute('data-pi-id') || '';
    var deviceId = btn.getAttribute('data-pi-device') || '';
    var name = btn.getAttribute('data-pi-name') || '';

    if (action === 'edit') {
      e.preventDefault();
      window.openProtectedItemWizard && window.openProtectedItemWizard('edit', { itemId: itemId, deviceId: deviceId });
      return;
    }
    if (action === 'delete') {
      e.preventDefault();
      var rules = parseRules(btn);
      window.dispatchEvent(new CustomEvent('pi-delete:open', { detail: { itemId: itemId, name: name, rules: rules } }));
      return;
    }
    if (action === 'run') {
      e.preventDefault();
      var rules = parseRules(btn);
      pickVaultForRun(rules).then(function (vaultId) {
        if (!vaultId) {
          try { window.showToast && window.showToast('No Storage Vault is configured for this Protected Item. Add a schedule first.', 'warning'); } catch (_) {}
          return;
        }
        if (!deviceId) {
          try { window.showToast && window.showToast('This Protected Item has no owner device.', 'error'); } catch (_) {}
          return;
        }
        btn.disabled = true;
        return call('runBackup', { deviceId: deviceId, protectedItemId: itemId, vaultId: vaultId }).then(function (r) {
          btn.disabled = false;
          if (r && r.status === 'success') {
            try { window.showToast && window.showToast('Backup started.', 'success'); } catch (_) {}
          } else {
            try { window.showToast && window.showToast((r && r.message) || 'Failed to start backup', 'error'); } catch (_) {}
          }
        }).catch(function () {
          btn.disabled = false;
          try { window.showToast && window.showToast('Network error', 'error'); } catch (_) {}
        });
      });
    }
  }

  document.addEventListener('click', onClick);
})();
