(function () {
  'use strict';

  var api = window.MS365_DEPROVISION_API || '';
  var token = window.MS365_TOKEN || '';
  var deepLinkUserId = parseInt(window.MS365_DEPROVISION_DEEP_LINK_USER_ID, 10) || 0;

  var state = {
    client: null,
    users: [],
    selectedUserId: 0,
    preview: null,
    expectedPhrase: ''
  };

  var searchTimer = null;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function get(op, params) {
    var q = new URLSearchParams(params || {});
    q.set('op', op);
    return fetch(api + '&' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function post(op, data) {
    var body = new URLSearchParams(data || {});
    body.set('token', token);
    return fetch(api + '&op=' + encodeURIComponent(op), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function show(el) { if (el) el.style.display = ''; }
  function hide(el) { if (el) el.style.display = 'none'; }

  function statusBadge(status) {
    var s = String(status || '').toLowerCase();
    var cls = 'default';
    if (s === 'active') cls = 'success';
    else if (s === 'suspended') cls = 'warning';
    else if (s === 'disabled' || s === 'cancelled' || s === 'terminated') cls = 'danger';
    return '<span class="label label-' + cls + '">' + esc(status || '—') + '</span>';
  }

  function renderClientResults(clients) {
    var wrap = document.getElementById('ms365-dep-client-results');
    if (!wrap) return;
    if (!clients || !clients.length) {
      wrap.style.display = 'none';
      wrap.innerHTML = '';
      return;
    }
    wrap.innerHTML = clients.map(function (c) {
      return '<a href="#" class="list-group-item ms365-dep-pick-client" data-client-id="' + esc(c.client_id) + '">' +
        '<strong>' + esc(c.client_name) + '</strong> <small class="text-muted">#' + esc(c.client_id) + '</small><br>' +
        '<small class="text-muted">' + esc(c.email || '') + '</small></a>';
    }).join('');
    wrap.style.display = 'block';
    wrap.querySelectorAll('.ms365-dep-pick-client').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var clientId = parseInt(el.getAttribute('data-client-id'), 10);
        loadUsersForClient(clientId);
        wrap.style.display = 'none';
        var input = document.getElementById('ms365-dep-client-search');
        if (input) input.value = '';
      });
    });
  }

  function renderUsersTable(payload) {
    var wrap = document.getElementById('ms365-dep-users-wrap');
    var label = document.getElementById('ms365-dep-client-label');
    if (!wrap) return;

    state.client = payload.client || null;
    state.users = payload.users || [];

    if (label && state.client) {
      label.textContent = ' — ' + (state.client.client_name || '') + ' (#' + (state.client.client_id || '') + ')';
    }

    if (!state.users.length) {
      wrap.innerHTML = '<p class="text-muted">No active e3 Backup Users found for this client.</p>';
      return;
    }

    wrap.innerHTML =
      '<table class="table table-striped table-condensed"><thead><tr>' +
      '<th>Service ID</th><th>Username</th><th>Status</th><th>Backup type</th><th>MS365</th><th>Jobs</th><th>Vaults</th><th></th>' +
      '</tr></thead><tbody>' +
      state.users.map(function (row) {
        var ms365 = row.ms365_connected ? '<span class="label label-info">Connected</span>' : '<span class="text-muted">—</span>';
        return '<tr>' +
          '<td>' + (row.whmcs_service_id ? esc(row.whmcs_service_id) : '<span class="text-muted">—</span>') + '</td>' +
          '<td><code>' + esc(row.username) + '</code></td>' +
          '<td>' + statusBadge(row.status) + '</td>' +
          '<td>' + esc(row.backup_type || '—') + '</td>' +
          '<td>' + ms365 + '</td>' +
          '<td>' + esc(row.job_count) + '</td>' +
          '<td>' + esc(row.vault_count) + '</td>' +
          '<td><button type="button" class="btn btn-xs btn-danger ms365-dep-select-user" data-id="' + esc(row.backup_user_id) + '">Select</button></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';

    wrap.querySelectorAll('.ms365-dep-select-user').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        loadPreview(id);
      });
    });
  }

  function renderPreview(preview) {
    var wrap = document.getElementById('ms365-dep-preview-wrap');
    var confirmWrap = document.getElementById('ms365-dep-confirm-wrap');
    var hint = document.getElementById('ms365-dep-confirm-hint');
    var phraseInput = document.getElementById('ms365-dep-confirm-phrase');
    var executeBtn = document.getElementById('ms365-dep-execute-btn');
    if (!wrap) return;

    state.preview = preview;
    state.expectedPhrase = preview.confirm_phrase || '';
    state.selectedUserId = preview.backup_user_id || 0;

    var user = preview.user || {};
    var client = preview.client || {};
    var jobs = preview.jobs || [];
    var vaults = preview.vaults || [];
    var impact = preview.impact || {};
    var willCancel = preview.will_cancel || {};
    var willNot = preview.will_not_touch || {};
    var grace = preview.vault_recycle_grace_days || 30;

    var jobsHtml = jobs.length
      ? '<ul class="list-unstyled" style="margin-bottom:0">' + jobs.map(function (j) {
          return '<li><code>' + esc(j.source_type) + '</code> — ' + esc(j.name || j.job_id) +
            ' <small class="text-muted">(' + esc(j.status) + ')</small></li>';
        }).join('') + '</ul>'
      : '<span class="text-muted">None</span>';

    var vaultsHtml = vaults.length
      ? '<ul class="list-unstyled" style="margin-bottom:0">' + vaults.map(function (v) {
          return '<li><code>' + esc(v.name) + '</code> <small class="text-muted">' + esc(v.size_display || '') +
            (v.is_ms365_vault ? ' · MS365 vault' : '') + '</small></li>';
        }).join('') + '</ul>'
      : '<span class="text-muted">None</span>';

    wrap.innerHTML =
      '<div class="row">' +
        '<div class="col-md-6">' +
          '<h4 style="margin-top:0">Target</h4>' +
          '<table class="table table-condensed"><tbody>' +
            '<tr><th>Client</th><td>' + esc(client.client_name) + ' <small class="text-muted">#' + esc(client.client_id) + '</small></td></tr>' +
            '<tr><th>Backup user</th><td><code>' + esc(user.username) + '</code> <small class="text-muted">id ' + esc(user.backup_user_id) + '</small></td></tr>' +
            '<tr><th>WHMCS service</th><td>' + (willCancel.service_id ? esc(willCancel.service_id) + ' ' + statusBadge(willCancel.service_status) : '<span class="text-muted">—</span>') + '</td></tr>' +
            '<tr><th>Backup type</th><td>' + esc(user.backup_type || '—') + '</td></tr>' +
          '</tbody></table>' +
        '</div>' +
        '<div class="col-md-6">' +
          '<h4 style="margin-top:0">Will <span class="text-danger">deprovision</span></h4>' +
          '<ul>' +
            '<li>Cancel <strong>' + esc(willCancel.product || 'e3 Backup User') + '</strong> WHMCS service (Immediate)</li>' +
            '<li>Soft-delete <strong>' + esc(impact.jobs || 0) + '</strong> backup job(s)</li>' +
            '<li>Recycle <strong>' + esc(vaults.length) + '</strong> storage vault(s) — physical teardown after ' + esc(grace) + ' day grace</li>' +
            (impact.ms365_connected ? '<li>Disconnect MS365 tenant for this backup user</li>' : '') +
            ((impact.agents || 0) > 0 ? '<li>Disable <strong>' + esc(impact.agents) + '</strong> agent(s)</li>' : '') +
            ((impact.tokens || 0) > 0 ? '<li>Revoke <strong>' + esc(impact.tokens) + '</strong> enrollment token(s)</li>' : '') +
            '<li>Soft-disable backup user record (<code>deleted_at</code>)</li>' +
          '</ul>' +
        '</div>' +
      '</div>' +
      '<div class="row" style="margin-top:10px">' +
        '<div class="col-md-6">' +
          '<h4 style="margin-top:0">Jobs to delete</h4>' + jobsHtml +
        '</div>' +
        '<div class="col-md-6">' +
          '<h4 style="margin-top:0">Vaults to recycle</h4>' + vaultsHtml +
        '</div>' +
      '</div>' +
      '<div class="alert alert-info" style="margin-top:15px;margin-bottom:0">' +
        '<strong>Will NOT touch:</strong> ' + esc(willNot.object_storage_product || 'e3 Object Storage') +
        (willNot.object_storage_service_id ? ' (service #' + esc(willNot.object_storage_service_id) + ' ' + esc(willNot.object_storage_service_status) + ')' : '') +
        '. ' + esc(willNot.note || '') +
      '</div>';

    if (hint) {
      hint.textContent = 'Type exactly: ' + state.expectedPhrase;
    }
    if (phraseInput) {
      phraseInput.value = '';
      phraseInput.placeholder = state.expectedPhrase;
    }
    if (executeBtn) {
      executeBtn.disabled = true;
    }
    show(confirmWrap);
  }

  function loadUsersForClient(clientId) {
    hide(document.getElementById('ms365-dep-search-panel'));
    hide(document.getElementById('ms365-dep-preview-panel'));
    show(document.getElementById('ms365-dep-list-panel'));
    var wrap = document.getElementById('ms365-dep-users-wrap');
    if (wrap) wrap.innerHTML = '<p class="text-muted">Loading…</p>';
    get('deprovision_list_users', { client_id: clientId }).then(function (res) {
      if (!res || !res.ok) {
        if (wrap) wrap.innerHTML = '<div class="alert alert-danger">' + esc((res && res.error) || 'Failed to load users') + '</div>';
        return;
      }
      renderUsersTable(res);
    });
  }

  function loadPreview(backupUserId) {
    hide(document.getElementById('ms365-dep-list-panel'));
    hide(document.getElementById('ms365-dep-search-panel'));
    show(document.getElementById('ms365-dep-preview-panel'));
    var wrap = document.getElementById('ms365-dep-preview-wrap');
    if (wrap) wrap.innerHTML = '<p class="text-muted">Loading preview…</p>';
    hide(document.getElementById('ms365-dep-confirm-wrap'));
    get('deprovision_preview', { backup_user_id: backupUserId }).then(function (res) {
      if (!res || !res.ok) {
        if (wrap) wrap.innerHTML = '<div class="alert alert-danger">' + esc((res && res.error) || 'Preview failed') + '</div>';
        return;
      }
      renderPreview(res.preview || {});
    });
  }

  function executeDeprovision() {
    var phraseInput = document.getElementById('ms365-dep-confirm-phrase');
    var executeBtn = document.getElementById('ms365-dep-execute-btn');
    var statusEl = document.getElementById('ms365-dep-execute-status');
    var phrase = phraseInput ? phraseInput.value.trim() : '';
    if (!state.selectedUserId || phrase !== state.expectedPhrase) {
      alert('Confirmation phrase does not match.');
      return;
    }
    if (!window.confirm('Permanently deprovision ' + (state.preview && state.preview.user ? state.preview.user.username : 'this user') + '?')) {
      return;
    }
    if (executeBtn) executeBtn.disabled = true;
    if (statusEl) statusEl.textContent = 'Working…';
    post('deprovision_execute', { backup_user_id: state.selectedUserId, confirm_phrase: phrase }).then(function (res) {
      if (res && res.ok) {
        if (statusEl) statusEl.textContent = res.message || 'Deprovisioned.';
        alert(res.message || 'User deprovisioned successfully.');
        window.location.href = 'addonmodules.php?module=ms365backup&action=users';
        return;
      }
      if (executeBtn) executeBtn.disabled = false;
      if (statusEl) statusEl.textContent = '';
      alert((res && res.error) || 'Deprovision failed');
    });
  }

  function resetToSearch() {
    state.client = null;
    state.users = [];
    state.selectedUserId = 0;
    state.preview = null;
    hide(document.getElementById('ms365-dep-list-panel'));
    hide(document.getElementById('ms365-dep-preview-panel'));
    show(document.getElementById('ms365-dep-search-panel'));
  }

  document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('ms365-dep-client-search');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        var q = searchInput.value.trim();
        if (q.length < 2) {
          renderClientResults([]);
          return;
        }
        searchTimer = setTimeout(function () {
          get('deprovision_client_search', { q: q }).then(function (res) {
            renderClientResults((res && res.ok && res.clients) ? res.clients : []);
          });
        }, 250);
      });
    }

    var serviceForm = document.getElementById('ms365-dep-service-form');
    if (serviceForm) {
      serviceForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var serviceId = parseInt((document.getElementById('ms365-dep-service-id') || {}).value, 10);
        if (!serviceId) {
          alert('Enter a WHMCS service ID.');
          return;
        }
        hide(document.getElementById('ms365-dep-search-panel'));
        hide(document.getElementById('ms365-dep-preview-panel'));
        show(document.getElementById('ms365-dep-list-panel'));
        var wrap = document.getElementById('ms365-dep-users-wrap');
        if (wrap) wrap.innerHTML = '<p class="text-muted">Looking up service…</p>';
        get('deprovision_lookup_service', { service_id: serviceId }).then(function (res) {
          if (!res || !res.ok) {
            if (wrap) wrap.innerHTML = '<div class="alert alert-danger">' + esc((res && res.error) || 'Lookup failed') + '</div>';
            return;
          }
          renderUsersTable(res);
        });
      });
    }

    var backSearch = document.getElementById('ms365-dep-back-search');
    if (backSearch) backSearch.addEventListener('click', resetToSearch);

    var backList = document.getElementById('ms365-dep-back-list');
    if (backList) {
      backList.addEventListener('click', function () {
        hide(document.getElementById('ms365-dep-preview-panel'));
        if (state.client && state.client.client_id) {
          loadUsersForClient(state.client.client_id);
        } else {
          resetToSearch();
        }
      });
    }

    var phraseInput = document.getElementById('ms365-dep-confirm-phrase');
    var executeBtn = document.getElementById('ms365-dep-execute-btn');
    if (phraseInput && executeBtn) {
      phraseInput.addEventListener('input', function () {
        executeBtn.disabled = phraseInput.value.trim() !== state.expectedPhrase;
      });
      executeBtn.addEventListener('click', executeDeprovision);
    }

    if (deepLinkUserId > 0) {
      loadPreview(deepLinkUserId);
    }
  });
})();
