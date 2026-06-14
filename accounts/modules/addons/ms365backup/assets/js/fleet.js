(function () {
  'use strict';

  var api = window.MS365_FLEET_API || '';
  var token = window.MS365_TOKEN || '';

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

  function get(op, params) {
    var q = new URLSearchParams(params || {});
    q.set('op', op);
    return fetch(api + '&' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function renderDashboard() {
    var el = document.getElementById('fleet-dashboard');
    if (!el) return;
    get('fleet_summary').then(function (res) {
      if (!res.ok) { el.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>'; return; }
      var s = res.summary || {};
      var target = s.target_release ? s.target_release.version : '—';
      var latest = s.latest_release ? s.latest_release.version : '—';
      var versions = Object.keys(s.version_counts || {}).map(function (v) {
        return esc(v) + ': ' + s.version_counts[v];
      }).join(', ') || '—';
      el.innerHTML =
        '<div class="row">' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.active_nodes) + '</h4><small>Active nodes</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.queued_jobs) + ' / ' + esc(s.running_jobs) + '</h4><small>Queued / running jobs</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.load) + ' / ' + esc(s.capacity) + '</h4><small>Load / capacity</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.engine_mode) + '</h4><small>Engine mode</small></div></div>' +
        '</div>' +
        '<p><strong>Latest release:</strong> ' + esc(latest) + ' &nbsp; <strong>Deploy target:</strong> ' + esc(target) + '</p>' +
        '<p><strong>Node versions:</strong> ' + versions + '</p>';
    });
    var auditEl = document.getElementById('fleet-audit');
    if (auditEl) {
      get('fleet_audit', { limit: 15 }).then(function (res) {
        if (!res.ok || !res.entries || !res.entries.length) {
          auditEl.innerHTML = '<p class="text-muted">No audit entries.</p>';
          return;
        }
        auditEl.innerHTML = '<table class="table table-condensed"><thead><tr><th>Time</th><th>Action</th><th>Message</th></tr></thead><tbody>' +
          res.entries.map(function (e) {
            return '<tr><td>' + esc(new Date((e.created_at || 0) * 1000).toLocaleString()) + '</td><td>' + esc(e.action) + '</td><td>' + esc(e.message) + '</td></tr>';
          }).join('') + '</tbody></table>';
      });
    }
  }

  function nodeLabel(n) {
    var id = String(n.node_id || '');
    var suffix = id.length >= 8 ? id.slice(0, 8) : id;
    return esc(n.hostname) + (suffix ? ' <small class="text-muted">(' + esc(suffix) + '…)</small>' : '');
  }

  function renderNodes() {
    var el = document.getElementById('fleet-nodes');
    if (!el) return;
    get('fleet_nodes').then(function (res) {
      if (!res.ok) { el.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>'; return; }
      var nodes = res.nodes || [];
      if (!nodes.length) { el.innerHTML = '<p class="text-muted">No worker nodes registered.</p>'; return; }
      el.innerHTML = '<table class="table table-striped table-condensed"><thead><tr>' +
        '<th>Hostname</th><th>Status</th><th>Version</th><th>Deploy</th><th>Load</th><th>VMID</th><th>Last HB</th><th></th></tr></thead><tbody>' +
        nodes.map(function (n) {
          var hb = n.last_heartbeat_at ? new Date(n.last_heartbeat_at * 1000).toLocaleString() : '—';
          var status = String(n.status || '').toLowerCase();
          var vmidCell = n.proxmox_vmid ? esc(n.proxmox_vmid) : '—';
          var actions;
          if (status === 'retired') {
            actions = '<button class="btn btn-xs btn-danger fleet-delete" data-id="' + esc(n.node_id) + '">Delete</button>';
          } else {
            actions = '<button class="btn btn-xs btn-default fleet-drain" data-id="' + esc(n.node_id) + '">Drain</button> ' +
              '<button class="btn btn-xs btn-warning fleet-retire" data-id="' + esc(n.node_id) + '">Retire</button>';
            if (!n.proxmox_vmid) {
              actions += ' <button class="btn btn-xs btn-info fleet-set-vmid" data-id="' + esc(n.node_id) + '" data-hostname="' + esc(n.hostname) + '">Set VMID</button>';
            }
          }
          return '<tr><td>' + nodeLabel(n) + '</td><td>' + esc(n.status) + '</td><td>' + esc(n.version || '—') + '</td>' +
            '<td>' + esc(n.deploy_status || '—') + '</td><td>' + esc(n.current_load) + '/' + esc(n.max_concurrent_runs) + '</td>' +
            '<td>' + vmidCell + '</td><td>' + esc(hb) + '</td><td>' + actions + '</td></tr>';
        }).join('') + '</tbody></table>';
      el.querySelectorAll('.fleet-drain').forEach(function (btn) {
        btn.addEventListener('click', function () {
          post('fleet_node_drain', { node_id: btn.getAttribute('data-id') }).then(function (res) {
            if (!res.ok) { alert(res.error || 'Drain failed'); return; }
            renderNodes();
          });
        });
      });
      el.querySelectorAll('.fleet-retire').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!confirm('Retire this node?')) return;
          post('fleet_node_retire', { node_id: btn.getAttribute('data-id') }).then(function (res) {
            if (!res.ok) { alert(res.error || 'Retire failed'); return; }
            renderNodes();
          });
        });
      });
      el.querySelectorAll('.fleet-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!confirm('Permanently delete this retired node record?')) return;
          post('fleet_node_delete', { node_id: btn.getAttribute('data-id') }).then(function (res) {
            if (!res.ok) {
              alert(res.error || 'Delete failed');
              return;
            }
            renderNodes();
          });
        });
      });
      el.querySelectorAll('.fleet-set-vmid').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var host = btn.getAttribute('data-hostname') || 'node';
          var raw = prompt('Proxmox VMID for ' + host + ':');
          if (raw === null) return;
          var vmid = parseInt(String(raw).trim(), 10);
          if (!vmid || vmid <= 0) {
            alert('Enter a positive integer VMID');
            return;
          }
          post('fleet_node_set_vmid', { node_id: btn.getAttribute('data-id'), proxmox_vmid: String(vmid) }).then(function (res) {
            if (!res.ok) {
              alert(res.error || 'Set VMID failed');
              return;
            }
            renderNodes();
          });
        });
      });
    });
  }

  function renderBuilds() {
    var el = document.getElementById('fleet-builds');
    if (!el) return;
    get('worker_build_list').then(function (res) {
      if (!res.ok) { el.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>'; return; }
      var jobs = res.jobs || [];
      el.innerHTML = '<table class="table table-condensed"><thead><tr><th>ID</th><th>Version</th><th>Status</th><th>Step</th><th>Release</th><th></th></tr></thead><tbody>' +
        jobs.map(function (j) {
          return '<tr><td>' + esc(j.id) + '</td><td>' + esc(j.version_label) + '</td><td>' + esc(j.status) + '</td>' +
            '<td>' + esc(j.current_step) + '</td><td>' + esc(j.release_id || '—') + '</td>' +
            '<td><button class="btn btn-xs btn-default fleet-build-view" data-id="' + esc(j.id) + '">View</button></td></tr>';
        }).join('') + '</tbody></table>';
      el.querySelectorAll('.fleet-build-view').forEach(function (btn) {
        btn.addEventListener('click', function () { showBuildDetail(parseInt(btn.getAttribute('data-id'), 10)); });
      });
    });
  }

  function showBuildDetail(jobId) {
    var panel = document.getElementById('fleet-build-detail-panel');
    var detail = document.getElementById('fleet-build-detail');
    if (!panel || !detail) return;
    get('worker_build_status', { job_id: jobId }).then(function (res) {
      if (!res.ok) return;
      var steps = (res.steps || []).map(function (s) {
        return '<li>' + esc(s.step_key) + ': ' + esc(s.status) + ' — ' + esc(s.summary) + '</li>';
      }).join('');
      detail.innerHTML = '<p>Job #' + esc(res.job.id) + ' — ' + esc(res.job.status) + '</p><ul>' + steps + '</ul>' +
        '<pre id="fleet-build-log" style="max-height:300px;overflow:auto;background:#111;color:#eee;padding:8px">Loading log…</pre>';
      panel.style.display = 'block';
      var step = res.job.current_step || 'go_build';
      get('worker_build_log', { job_id: jobId, step: step }).then(function (lr) {
        var logEl = document.getElementById('fleet-build-log');
        if (logEl) logEl.textContent = lr.log || '(empty)';
      });
    });
  }

  function renderDeployments() {
    var sel = document.getElementById('fleet-deploy-release');
    get('worker_release_list').then(function (res) {
      if (sel && res.ok) {
        sel.innerHTML = (res.releases || []).map(function (r) {
          return '<option value="' + esc(r.id) + '">v' + esc(r.version) + ' (' + esc(r.sha256).substring(0, 12) + '…)</option>';
        }).join('') || '<option value="">No releases</option>';
      }
    });
    var el = document.getElementById('fleet-deployments');
    if (!el) return;
    get('worker_deploy_list').then(function (res) {
      if (!res.ok) { el.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>'; return; }
      var jobs = res.jobs || [];
      el.innerHTML = '<table class="table table-condensed"><thead><tr><th>ID</th><th>Release</th><th>Strategy</th><th>Status</th><th>Progress</th></tr></thead><tbody>' +
        jobs.map(function (j) {
          var updated = parseInt(j.nodes_updated, 10) || 0;
          var total = parseInt(j.nodes_total, 10) || 0;
          var progress = total > 0 ? updated + '/' + total : '—';
          if (j.status === 'succeeded' && total > 0 && updated >= total) {
            progress = total + '/' + total + ' ✓';
          }
          return '<tr><td>' + esc(j.id) + '</td><td>' + esc(j.release_id) + '</td><td>' + esc(j.strategy) + '</td>' +
            '<td>' + esc(j.status) + '</td><td>' + esc(progress) + '</td></tr>';
        }).join('') + '</tbody></table>';
    });
  }

  function renderSettings() {
    var el = document.getElementById('fleet-settings');
    if (!el) return;
    get('fleet_settings_get').then(function (res) {
      if (!res.ok) return;
      var s = res.settings || {};
      el.innerHTML = '<dl class="dl-horizontal">' +
        Object.keys(s).map(function (k) { return '<dt>' + esc(k) + '</dt><dd><code>' + esc(s[k]) + '</code></dd>'; }).join('') +
        '</dl>';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    renderDashboard();
    renderNodes();
    renderBuilds();
    renderDeployments();
    renderSettings();

    var buildForm = document.getElementById('fleet-build-form');
    if (buildForm) {
      buildForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var fd = new FormData(buildForm);
        post('worker_build_create', {
          version_label: fd.get('version_label'),
          git_ref: fd.get('git_ref'),
          run_tests: buildForm.querySelector('[name=run_tests]').checked ? '1' : '',
          git_sync: buildForm.querySelector('[name=git_sync]').checked ? '1' : ''
        }).then(function (res) {
          var n = document.getElementById('fleet-build-notice');
          if (n) n.innerHTML = res.ok ? '<div class="alert alert-success">Build job #' + esc(res.job_id) + ' queued.</div>' : '<div class="alert alert-danger">' + esc(res.error) + '</div>';
          renderBuilds();
        });
      });
    }

    var deployForm = document.getElementById('fleet-deploy-form');
    if (deployForm) {
      deployForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var fd = new FormData(deployForm);
        post('worker_deploy_create', {
          release_id: fd.get('release_id'),
          strategy: fd.get('strategy'),
          force_deploy: deployForm.querySelector('[name=force_deploy]').checked ? '1' : ''
        }).then(function (res) {
          var n = document.getElementById('fleet-deploy-notice');
          if (n) n.innerHTML = res.ok ? '<div class="alert alert-success">Deploy job #' + esc(res.deploy_job_id) + ' started.</div>' : '<div class="alert alert-danger">' + esc(res.error) + '</div>';
          renderDeployments();
        });
      });
    }

    var refreshBtn = document.getElementById('fleet-refresh-nodes');
    if (refreshBtn) refreshBtn.addEventListener('click', renderNodes);
    var leaseBtn = document.getElementById('fleet-release-leases');
    if (leaseBtn) leaseBtn.addEventListener('click', function () {
      post('fleet_release_leases').then(function () { renderNodes(); renderDashboard(); });
    });

    setInterval(function () {
      renderDashboard();
      if (document.getElementById('fleet-nodes')) renderNodes();
      if (document.getElementById('fleet-builds')) renderBuilds();
      if (document.getElementById('fleet-deployments')) renderDeployments();
    }, 15000);
  });
})();
