(function () {
  'use strict';

  var api = window.MS365_API || '';
  var token = window.MS365_TOKEN || '';
  var pollTimer = null;
  var activeRunId = null;

  function post(op, data) {
    var params = new URLSearchParams();
    Object.keys(data || {}).forEach(function (key) {
      var val = data[key];
      if (val !== undefined && val !== null) {
        params.append(key, String(val));
      }
    });
    params.append('token', token);
    params.append('op', op);
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString(),
      credentials: 'same-origin',
    }).then(function (r) { return r.json(); });
  }

  function get(op, params) {
    var q = new URLSearchParams(params || {});
    q.set('op', op);
    return fetch(api + '&' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function notice(el, html, ok) {
    if (!el) return;
    el.innerHTML = '<div class="alert alert-' + (ok ? 'success' : 'danger') + '">' + html + '</div>';
  }

  function esc(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  var saveBtn = document.getElementById('ms365-seeder-save-config');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      post('seeder_save_config', {
        region: document.getElementById('ms365-seeder-region').value,
        tenant_id: document.getElementById('ms365-seeder-tenant-id').value,
        client_id: document.getElementById('ms365-seeder-client-id').value,
        app_secret: document.getElementById('ms365-seeder-app-secret').value,
      }).then(function (res) {
        notice(document.getElementById('ms365-seeder-config-notice'), res.ok ? (res.message || 'Saved') : (res.error || 'Failed'), res.ok);
      });
    });
  }

  var testBtn = document.getElementById('ms365-seeder-test-auth');
  if (testBtn) {
    testBtn.addEventListener('click', function () {
      post('seeder_test_auth', {}).then(function (res) {
        notice(document.getElementById('ms365-seeder-config-notice'), res.ok ? 'Connected: ' + (res.organization || 'OK') : (res.error || 'Failed'), res.ok);
      });
    });
  }

  var connectBtn = document.getElementById('ms365-seeder-connect-user');
  if (connectBtn) {
    connectBtn.addEventListener('click', function () {
      post('seeder_build_oauth_url', {}).then(function (res) {
        if (res.ok && res.url) {
          window.location.href = res.url;
        } else {
          alert(res.error || 'Could not build OAuth URL');
        }
      });
    });
  }

  var disconnectBtn = document.getElementById('ms365-seeder-disconnect-user');
  if (disconnectBtn) {
    disconnectBtn.addEventListener('click', function () {
      post('seeder_disconnect_user', {}).then(function (res) {
        if (res.ok) {
          document.getElementById('ms365-seeder-user-status').innerHTML = '<span class="text-muted">Not connected</span>';
        }
      });
    });
  }

  var discoverBtn = document.getElementById('ms365-seeder-discover');
  if (discoverBtn) {
    discoverBtn.addEventListener('click', function () {
      var el = document.getElementById('ms365-seeder-targets');
      el.textContent = 'Discovering…';
      get('seeder_discover_targets').then(function (res) {
        if (!res.ok) {
          el.textContent = res.error || 'Discovery failed';
          return;
        }
        el.textContent = 'Targets: ' + (res.users || 0) + ' users, ' + (res.sites || 0) + ' sites, ' + (res.teams || 0) + ' teams';
      });
    });
  }

  function collectWorkloads() {
    var out = {};
    document.querySelectorAll('.ms365-seeder-workload').forEach(function (cb) {
      out[cb.value] = cb.checked ? '1' : '0';
    });
    return out;
  }

  function showProgress(show) {
    var el = document.getElementById('ms365-seeder-progress');
    if (el) el.style.display = show ? '' : 'none';
  }

  function updateProgressUI(data) {
    var statusEl = document.getElementById('ms365-seeder-status');
    var phaseEl = document.getElementById('ms365-seeder-phase');
    var bar = document.getElementById('ms365-seeder-bar');
    var logEl = document.getElementById('ms365-seeder-log');
    if (statusEl) statusEl.textContent = data.status || '—';
    if (phaseEl) phaseEl.textContent = (data.message || data.phase || '—');
    var pct = 0;
    if (data.total > 0) {
      pct = Math.round((data.current / data.total) * 100);
    } else if (data.status === 'success') {
      pct = 100;
    }
    if (bar) bar.style.width = pct + '%';
    if (logEl && data.log_lines && data.log_lines.length) {
      logEl.textContent = data.log_lines.join('\n');
      logEl.scrollTop = logEl.scrollHeight;
    }
  }

  function pollProgress() {
    if (!activeRunId) return;
    get('seeder_progress', { run_id: activeRunId }).then(function (res) {
      if (!res.ok) return;
      if (res.progress) updateProgressUI(res.progress);
      var st = (res.run && res.run.status) || (res.progress && res.progress.status) || '';
      if (st === 'success' || st === 'error' || st === 'cancelled') {
        clearInterval(pollTimer);
        pollTimer = null;
        document.getElementById('ms365-seeder-cancel').style.display = 'none';
        loadRuns();
      }
    });
  }

  var startBtn = document.getElementById('ms365-seeder-start');
  if (startBtn) {
    startBtn.addEventListener('click', function () {
      if (!window.confirm('Start seeding? This will create data in your M365 tenant.')) return;
      startBtn.disabled = true;
      post('seeder_start', {
        profile: document.getElementById('ms365-seeder-profile').value,
        workloads_json: JSON.stringify(collectWorkloads()),
        all_users: document.getElementById('ms365-seeder-all-users').checked ? '1' : '0',
        all_sites: document.getElementById('ms365-seeder-all-sites').checked ? '1' : '0',
        all_teams: document.getElementById('ms365-seeder-all-teams').checked ? '1' : '0',
      }).then(function (res) {
        startBtn.disabled = false;
        if (!res.ok) {
          alert(res.error || 'Failed to start');
          return;
        }
        activeRunId = res.run_id;
        showProgress(true);
        document.getElementById('ms365-seeder-cancel').style.display = '';
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollProgress, 2000);
        pollProgress();
        loadRuns();
      });
    });
  }

  var cancelBtn = document.getElementById('ms365-seeder-cancel');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      if (!activeRunId) return;
      post('seeder_cancel', { run_id: activeRunId }).then(function (res) {
        if (res.ok) pollProgress();
      });
    });
  }

  function loadRuns() {
    get('seeder_list_runs', { limit: '15' }).then(function (res) {
      var tbody = document.getElementById('ms365-seeder-runs-tbody');
      if (!tbody) return;
      if (!res.ok || !res.runs || !res.runs.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No runs yet.</td></tr>';
        return;
      }
      tbody.innerHTML = res.runs.map(function (r) {
        return '<tr><td><code style="font-size:11px">' + esc(r.id) + '</code></td>'
          + '<td>' + esc(r.profile) + '</td><td>' + esc(r.status) + '</td><td>' + esc(r.created_at) + '</td></tr>';
      }).join('');
    });
  }

  function loadStatus() {
    get('seeder_status').then(function (res) {
      if (!res.ok) return;
      if (res.seed_user_upn) {
        document.getElementById('ms365-seeder-user-status').innerHTML = 'Connected: <strong>' + esc(res.seed_user_upn) + '</strong>';
      }
    });
  }

  if (document.getElementById('ms365-seeder-profile')) {
    loadRuns();
    loadStatus();
    var params = new URLSearchParams(window.location.search);
    if (params.get('oauth_ok')) {
      notice(document.getElementById('ms365-seeder-config-notice'), 'Seed user connected.', true);
    }
    if (params.get('oauth_error')) {
      notice(document.getElementById('ms365-seeder-config-notice'), params.get('oauth_error'), false);
    }
  }
})();
