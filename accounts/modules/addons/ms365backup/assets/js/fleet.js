(function () {
  'use strict';

  var api = window.MS365_FLEET_API || '';
  var token = window.MS365_TOKEN || '';
  var fleetMeta = window.MS365_FLEET_META || {};
  var fleetTarget = window.MS365_FLEET_TARGET || 'development';

  function withFleet(params) {
    var p = params || {};
    // Do not overwrite an explicit fleet param (e.g. fleet_set_target passes the new target).
    if (fleetMeta.can_select_fleet && p.fleet == null) {
      p.fleet = fleetTarget;
    }
    return p;
  }

  function post(op, data) {
    var body = new URLSearchParams(withFleet(data || {}));
    body.set('token', token);
    return fetch(api + '&op=' + encodeURIComponent(op), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function get(op, params) {
    var q = new URLSearchParams(withFleet(params || {}));
    q.set('op', op);
    return fetch(api + '&' + q.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function formatMib(mib) {
    if (mib == null || mib === '') return '—';
    var n = Number(mib);
    if (!isFinite(n)) return '—';
    if (n >= 1024) return (n / 1024).toFixed(1) + ' GiB';
    return n + ' MiB';
  }

  function formatPct(pct) {
    if (pct == null || pct === '') return '—';
    var n = Number(pct);
    return isFinite(n) ? n.toFixed(1) + '%' : '—';
  }

  function telemetryCell(n) {
    if (!n.telemetry_at) return '<span class="text-muted">—</span>';
    var cpu = formatPct(n.cpu_pct);
    var mem = '—';
    if (n.mem_used_mib != null && n.mem_total_mib != null) {
      mem = esc(n.mem_used_mib) + '/' + esc(n.mem_total_mib) + ' MiB';
    }
    var disk = '—';
    if (n.disk_free_mib != null && n.disk_total_mib != null) {
      disk = esc(n.disk_free_mib) + '/' + esc(n.disk_total_mib) + ' MiB free';
    }
    return '<small>CPU ' + cpu + '<br>RAM ' + mem + '<br>Disk ' + disk + '</small>';
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
      var staleJobs = (s.stale_running_jobs || 0) + ' / ' + (s.exhausted_jobs || 0);
      var staleClass = (s.stale_running_jobs || 0) > 0 || (s.exhausted_jobs || 0) > 0 ? ' well-warning' : '';
      var queuedBreakdown = Object.keys(s.queued_by_keytype || {}).map(function (k) {
        return esc(k) + ': ' + s.queued_by_keytype[k];
      }).join(', ') || '—';
      el.innerHTML =
        '<div class="row">' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.active_nodes) + '</h4><small>Active nodes</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.queued_jobs) + ' / ' + esc(s.running_jobs) + '</h4><small>Queued / running jobs</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center' + staleClass + '"><h4>' + esc(staleJobs) + '</h4><small>Stale / exhausted jobs</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.load) + ' / ' + esc(s.capacity) + ' (' + esc(s.utilization_pct) + '%)</h4><small>Load / capacity</small></div></div>' +
        '</div>' +
        '<div class="row">' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(formatPct(s.avg_cpu_pct)) + '</h4><small>Avg CPU (fleet)</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.mem_used_mib != null ? s.mem_used_mib : '—') + ' / ' + esc(s.mem_total_mib != null ? s.mem_total_mib : '—') + '</h4><small>RAM MiB (sum active)</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.disk_free_mib != null ? s.disk_free_mib : '—') + '</h4><small>Disk free MiB (sum)</small></div></div>' +
        '<div class="col-md-3"><div class="well text-center"><h4>' + esc(s.telemetry_fresh_nodes || 0) + '/' + esc(s.telemetry_fresh_nodes != null ? s.active_nodes : '—') + '</h4><small>Nodes reporting telemetry</small></div></div>' +
        '</div>' +
        '<div class="row">' +
        '<div class="col-md-12"><div class="well text-center"><h4>' + esc(s.engine_mode) + '</h4><small>Engine mode</small></div></div>' +
        '</div>' +
        '<p><strong>Latest release:</strong> ' + esc(latest) + ' &nbsp; <strong>Deploy target:</strong> ' + esc(target) + '</p>' +
        '<p><strong>Node versions:</strong> ' + versions + '</p>' +
        '<p><strong>Concurrency limits:</strong> platform ' + esc(s.platform_max_concurrent) +
        ', per-tenant ' + esc(s.per_tenant_max_concurrent) + ', per-client ' + esc(s.per_client_max_concurrent) + '</p>' +
        '<p><strong>Queued by workload type:</strong> ' + queuedBreakdown + '</p>' +
        '<p><strong>Claim admit rejects (last heartbeat):</strong> ' + esc(s.claim_admit_rejects || 0) + '</p>';
      var buildVersionInput = document.querySelector('#fleet-build-form [name=version_label]');
      if (buildVersionInput && s.suggest_next_version) {
        buildVersionInput.placeholder = s.suggest_next_version;
      }
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

  function formatConfigCell(n) {
    var status = String(n.config_effective_status || n.config_status || '—').toLowerCase();
    var applied = Number(n.config_version) || 0;
    var target = Number(n.target_config_version) || 0;
    var latest = Number(n.latest_config_version) || 0;
    var labelClass = {
      current: 'success',
      outdated: 'warning',
      pending: 'info',
      applying: 'info',
      failed: 'danger'
    }[status] || 'default';
    var detail = '';
    if (status === 'outdated' && latest > 0) {
      detail = 'v' + applied + ', latest v' + latest;
    } else if (target > 0 && applied < target) {
      detail = 'v' + applied + ' \u2192 v' + target;
    } else if (applied > 0) {
      detail = 'v' + applied;
    } else if (latest > 0) {
      detail = 'none, latest v' + latest;
    }
    var html = '<span class="label label-' + labelClass + '">' + esc(status) + '</span>';
    if (detail) {
      html += ' <small class="text-muted">(' + esc(detail) + ')</small>';
    }
    if (n.config_error) {
      html += ' <span class="text-danger" title="' + esc(n.config_error) + '">!</span>';
    }
    return html;
  }

  function renderNodes() {
    var el = document.getElementById('fleet-nodes');
    if (!el) return;
    loadScaleNodes();
    get('fleet_nodes').then(function (res) {
      if (!res.ok) { el.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>'; return; }
      var nodes = res.nodes || [];
      if (!nodes.length) { el.innerHTML = '<p class="text-muted">No worker nodes registered.</p>'; return; }
      el.innerHTML = '<table class="table table-striped table-condensed"><thead><tr>' +
        '<th>Hostname</th><th>Status</th><th>Version</th><th>Deploy</th><th>Config</th><th>Load</th><th>Telemetry</th><th>PVE node</th><th>VMID</th><th>Last HB</th><th></th></tr></thead><tbody>' +
        nodes.map(function (n) {
          var hb = n.last_heartbeat_at ? new Date(n.last_heartbeat_at * 1000).toLocaleString() : '—';
          var status = String(n.status || '').toLowerCase();
          var statusLabel = status === 'stopped' ? '<span class="label label-default">stopped</span>' : esc(n.status);
          var pveNode = n.proxmox_node ? esc(n.proxmox_node) : '—';
          var vmidCell = n.proxmox_vmid ? esc(n.proxmox_vmid) : '—';
          var configLabel = formatConfigCell(n);
          var actions;
          if (status === 'retired') {
            actions = '<button class="btn btn-xs btn-danger fleet-delete" data-id="' + esc(n.node_id) + '">Delete</button>';
          } else if (status === 'stopped') {
            actions = '<button class="btn btn-xs btn-success fleet-start" data-id="' + esc(n.node_id) + '">Start</button> ' +
              '<button class="btn btn-xs btn-warning fleet-retire" data-id="' + esc(n.node_id) + '">Retire</button>';
          } else if (status === 'draining') {
            actions = '<button class="btn btn-xs btn-success fleet-activate" data-id="' + esc(n.node_id) + '">Activate</button> ' +
              '<button class="btn btn-xs btn-default fleet-stop" data-id="' + esc(n.node_id) + '">Stop</button> ' +
              '<button class="btn btn-xs btn-warning fleet-retire" data-id="' + esc(n.node_id) + '">Retire</button>';
          } else {
            actions = '<button class="btn btn-xs btn-default fleet-drain" data-id="' + esc(n.node_id) + '">Drain</button> ' +
              '<button class="btn btn-xs btn-default fleet-stop" data-id="' + esc(n.node_id) + '">Stop</button> ' +
              '<button class="btn btn-xs btn-warning fleet-retire" data-id="' + esc(n.node_id) + '">Retire</button>';
            if (!n.proxmox_vmid) {
              actions += ' <button class="btn btn-xs btn-info fleet-set-vmid" data-id="' + esc(n.node_id) + '" data-hostname="' + esc(n.hostname) + '">Set VMID</button>';
            }
          }
          return '<tr><td>' + nodeLabel(n) + '</td><td>' + statusLabel + '</td><td>' + esc(n.version || '—') + '</td>' +
            '<td>' + esc(n.deploy_status || '—') + '</td><td>' + configLabel + '</td><td>' + esc(n.current_load) + '/' + esc(n.max_concurrent_runs) + '</td>' +
            '<td>' + telemetryCell(n) + '</td>' +
            '<td>' + pveNode + '</td>' +
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
      el.querySelectorAll('.fleet-activate').forEach(function (btn) {
        btn.addEventListener('click', function () {
          post('fleet_node_activate', { node_id: btn.getAttribute('data-id') }).then(function (res) {
            if (!res.ok) { alert(res.error || 'Activate failed'); return; }
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
      el.querySelectorAll('.fleet-stop').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!confirm('Stop this worker container? It can be started again later.')) return;
          post('fleet_node_stop', { node_id: btn.getAttribute('data-id') }).then(function (res) {
            if (!res.ok) { alert(res.error || 'Stop failed'); return; }
            renderNodes();
          });
        });
      });
      el.querySelectorAll('.fleet-start').forEach(function (btn) {
        btn.addEventListener('click', function () {
          post('fleet_node_start', { node_id: btn.getAttribute('data-id') }).then(function (res) {
            if (!res.ok) { alert(res.error || 'Start failed'); return; }
            renderNodes();
          });
        });
      });
    });
  }

  function loadScaleNodes() {
    var sel = document.getElementById('fleet-scale-node');
    if (!sel) return;
    get('fleet_proxmox_nodes').then(function (res) {
      if (!res.ok || !res.nodes || !res.nodes.length) {
        sel.innerHTML = '<option value="">(configure Proxmox or proxmox_cluster_nodes)</option>';
        return;
      }
      var current = sel.value;
      sel.innerHTML = res.nodes.map(function (name) {
        return '<option value="' + esc(name) + '">' + esc(name) + '</option>';
      }).join('');
      if (current) sel.value = current;
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
      el.innerHTML = '<table class="table table-condensed"><thead><tr><th>ID</th><th>Version</th><th>Strategy</th><th>Status</th><th>Progress</th></tr></thead><tbody>' +
        jobs.map(function (j) {
          var updated = parseInt(j.nodes_updated, 10) || 0;
          var total = parseInt(j.nodes_total, 10) || 0;
          var progress = total > 0 ? updated + '/' + total : '—';
          if (j.status === 'succeeded' && total > 0 && updated >= total) {
            progress = total + '/' + total + ' ✓';
          }
          var versionLabel = j.release_version ? 'v' + j.release_version : ('#' + j.release_id);
          return '<tr><td>' + esc(j.id) + '</td><td>' + esc(versionLabel) + '</td><td>' + esc(j.strategy) + '</td>' +
            '<td>' + esc(j.status) + '</td><td>' + esc(progress) + '</td></tr>';
        }).join('') + '</tbody></table>';
    });
  }

  var fleetNodesCache = [];

  function renderSettings() {
    var el = document.getElementById('fleet-settings');
    var editor = document.getElementById('fleet-config-editor');
    var rollout = document.getElementById('fleet-config-rollout');
    var notice = document.getElementById('fleet-config-notice');
    if (!el) return;
    get('fleet_settings_get').then(function (res) {
      if (!res.ok) return;
      var s = res.settings || {};
      el.innerHTML = '<dl class="dl-horizontal">' +
        Object.keys(s).map(function (k) { return '<dt>' + esc(k) + '</dt><dd><code>' + esc(s[k]) + '</code></dd>'; }).join('') +
        '</dl>';
    });
    get('fleet_config_get').then(function (res) {
      if (!res.ok) {
        if (notice) notice.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>';
        return;
      }
      if (editor) {
        editor.value = res.yaml || '';
        var ver = document.getElementById('fleet-config-version');
        if (ver) ver.textContent = res.version ? ('v' + res.version) : 'template (unsaved)';
      }
      fleetNodesCache = (res.status && res.status.nodes) ? res.status.nodes : [];
      renderConfigRollout(res);
    });
    get('fleet_nodes').then(function (res) {
      if (res.ok && res.nodes) {
        fleetNodesCache = res.nodes;
        renderConfigNodeCheckboxes();
      }
    });
  }

  function renderConfigRollout(configRes) {
    var statusEl = document.getElementById('fleet-config-status');
    if (!statusEl || !configRes || !configRes.status) return;
    var st = configRes.status;
    var counts = st.status_counts || {};
    statusEl.innerHTML = '<p><strong>Saved version:</strong> v' + esc(st.current_version || 0) +
      ' &nbsp; <strong>Rollout:</strong> current ' + esc(counts.current || 0) +
      ', outdated ' + esc(counts.outdated || 0) +
      ', pending ' + esc(counts.pending || 0) + ', applying ' + esc(counts.applying || 0) +
      ', failed ' + esc(counts.failed || 0) + '</p>';
    var versionInput = document.getElementById('fleet-config-rollout-version');
    if (versionInput && st.current_version) versionInput.value = st.current_version;
    renderConfigNodeCheckboxes();
  }

  function renderConfigNodeCheckboxes() {
    var box = document.getElementById('fleet-config-nodes');
    if (!box) return;
    var nodes = fleetNodesCache || [];
    if (!nodes.length) {
      box.innerHTML = '<p class="text-muted">No nodes.</p>';
      return;
    }
    box.innerHTML = nodes.filter(function (n) { return n.status !== 'retired'; }).map(function (n) {
      var cfgStatus = esc(n.config_effective_status || n.config_status || '—');
      var cfgDetail = '';
      if (n.config_version) {
        cfgDetail = ' v' + esc(n.config_version);
      }
      if (n.config_effective_status === 'outdated' && n.latest_config_version) {
        cfgDetail += ', latest v' + esc(n.latest_config_version);
      } else if (n.target_config_version && n.config_version !== n.target_config_version) {
        cfgDetail += ' \u2192 v' + esc(n.target_config_version);
      }
      return '<label class="checkbox" style="display:block;margin:2px 0">' +
        '<input type="checkbox" class="fleet-config-node" value="' + esc(n.node_id) + '"> ' +
        esc(n.hostname) + ' <small class="text-muted">(' + esc(n.status) + ', load ' + esc(n.current_load) + '/' + esc(n.max_concurrent_runs) + ', ' + cfgStatus + cfgDetail + ')</small></label>';
    }).join('');
  }

  function selectedConfigNodeIds() {
    var ids = [];
    document.querySelectorAll('.fleet-config-node:checked').forEach(function (cb) {
      ids.push(cb.value);
    });
    return ids;
  }

  function bindConfigEditor() {
    var validateBtn = document.getElementById('fleet-config-validate');
    var saveBtn = document.getElementById('fleet-config-save');
    var rolloutBtn = document.getElementById('fleet-config-rollout-btn');
    var notice = document.getElementById('fleet-config-notice');
    var editor = document.getElementById('fleet-config-editor');
    if (validateBtn && editor) {
      validateBtn.addEventListener('click', function () {
        post('fleet_config_save', { yaml: editor.value, validate_only: '1' }).then(function (res) {
          if (!notice) return;
          if (!res.ok) {
            notice.innerHTML = '<div class="alert alert-danger">' + esc((res.errors || [res.error]).join('; ')) + '</div>';
            return;
          }
          notice.innerHTML = '<div class="alert alert-success">YAML is valid.</div>';
          if (res.yaml) editor.value = res.yaml;
        });
      });
    }
    if (saveBtn && editor) {
      saveBtn.addEventListener('click', function () {
        post('fleet_config_save', { yaml: editor.value }).then(function (res) {
          if (!notice) return;
          if (!res.ok) {
            notice.innerHTML = '<div class="alert alert-danger">' + esc((res.errors || [res.error]).join('; ')) + '</div>';
            return;
          }
          notice.innerHTML = '<div class="alert alert-success">Saved config v' + esc(res.version) + '.</div>';
          renderSettings();
        });
      });
    }
    if (rolloutBtn) {
      rolloutBtn.addEventListener('click', function () {
        var versionInput = document.getElementById('fleet-config-rollout-version');
        var strategySel = document.getElementById('fleet-config-rollout-strategy');
        var version = versionInput ? parseInt(versionInput.value, 10) : 0;
        var strategy = strategySel ? strategySel.value : 'explicit';
        var payload = { config_version: String(version), strategy: strategy };
        if (strategy === 'explicit') {
          var ids = selectedConfigNodeIds();
          if (!ids.length) {
            alert('Select at least one node');
            return;
          }
          payload.node_ids = ids.join(',');
        }
        post('fleet_config_rollout', payload).then(function (res) {
          if (!notice) return;
          if (!res.ok) {
            notice.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>';
            return;
          }
          notice.innerHTML = '<div class="alert alert-success">Rollout v' + esc(res.config_version) + ' to ' + esc(res.nodes_targeted) + ' node(s), ' + esc(res.nodes_pending) + ' pending.</div>';
          renderSettings();
          renderNodes();
        });
      });
    }
    document.querySelectorAll('.fleet-config-preset').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var preset = btn.getAttribute('data-preset');
        var strategySel = document.getElementById('fleet-config-rollout-strategy');
        if (strategySel) strategySel.value = preset;
        document.querySelectorAll('.fleet-config-node').forEach(function (cb) {
          var row = cb.closest('label');
          var text = row ? row.textContent : '';
          if (preset === 'all') {
            cb.checked = true;
          } else if (preset === 'idle') {
            cb.checked = /load 0\//.test(text);
          } else if (preset === 'canary') {
            cb.checked = false;
          }
        });
        if (preset === 'canary' && document.querySelector('.fleet-config-node')) {
          document.querySelector('.fleet-config-node').checked = true;
        }
      });
    });
  }

  function updateFleetTargetUi() {
    var detail = document.getElementById('fleet-target-detail');
    if (detail) {
      var label = fleetTarget === 'production' ? 'Production fleet' : 'Development fleet';
      var remote = fleetTarget === 'production';
      detail.textContent = 'Viewing: ' + label + (remote && fleetMeta.production_system_url ? ' — worker API ' + fleetMeta.production_system_url : '');
    }
    document.querySelectorAll('.fleet-target-btn').forEach(function (btn) {
      var active = btn.getAttribute('data-fleet') === fleetTarget;
      btn.classList.toggle('btn-primary', active);
      btn.classList.toggle('btn-default', !active);
    });
    var prodWarn = document.getElementById('fleet-scale-prod-warning');
    var prodUrl = document.getElementById('fleet-scale-prod-url');
    if (prodWarn) {
      prodWarn.style.display = fleetTarget === 'production' ? 'block' : 'none';
    }
    if (prodUrl && fleetMeta.production_system_url) {
      prodUrl.textContent = fleetMeta.production_system_url;
    }
  }

  function bindFleetTargetSelector() {
    document.querySelectorAll('.fleet-target-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = btn.getAttribute('data-fleet') || 'development';
        if (next === fleetTarget) return;
        post('fleet_set_target', { fleet: next }).then(function (res) {
          if (!res.ok) {
            alert(res.error || 'Failed to switch fleet target');
            return;
          }
          fleetTarget = (res.meta && res.meta.active_fleet) ? res.meta.active_fleet : next;
          window.MS365_FLEET_TARGET = fleetTarget;
          if (res.meta) {
            fleetMeta = res.meta;
            window.MS365_FLEET_META = fleetMeta;
          }
          updateFleetTargetUi();
          renderDashboard();
          renderNodes();
          renderDeployments();
          renderSettings();
        });
      });
    });
    updateFleetTargetUi();
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindFleetTargetSelector();
    renderDashboard();
    renderNodes();
    renderBuilds();
    renderDeployments();
    renderSettings();
    bindConfigEditor();

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
          if (!n) return;
          if (!res.ok) {
            n.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>';
            return;
          }
          var msg = 'Deploy job #' + esc(res.deploy_job_id) + ' started for v' + esc(res.target_version || '?') + '.';
          if ((res.nodes_needing_update || 0) === 0) {
            msg = 'All ' + esc(res.already_current || res.nodes_total) + ' node(s) already run v' + esc(res.target_version || '?') + ' — nothing to deploy.';
          } else {
            msg += ' ' + esc(res.nodes_needing_update) + ' node(s) pending update.';
          }
          n.innerHTML = '<div class="alert alert-success">' + msg + '</div>';
          renderDeployments();
          renderNodes();
        });
      });
    }

    var refreshBtn = document.getElementById('fleet-refresh-nodes');
    if (refreshBtn) refreshBtn.addEventListener('click', renderNodes);
    var leaseBtn = document.getElementById('fleet-release-leases');
    if (leaseBtn) leaseBtn.addEventListener('click', function () {
      post('fleet_release_leases').then(function () { renderNodes(); renderDashboard(); });
    });

    var scaleForm = document.getElementById('fleet-scale-form');
    if (scaleForm) {
      scaleForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var fd = new FormData(scaleForm);
        var node = String(fd.get('proxmox_node') || '').trim();
        var count = String(fd.get('count') || '1');
        if (!node) {
          alert('Select a Proxmox node');
          return;
        }
        post('fleet_scale_up', { proxmox_node: node, count: count }).then(function (res) {
          var n = document.getElementById('fleet-scale-notice');
          if (!n) return;
          if (!res.ok) {
            n.innerHTML = '<div class="alert alert-danger">' + esc(res.error) + '</div>';
            return;
          }
          var created = (res.result && res.result.created) ? res.result.created : [];
          var failed = (res.result && res.result.failed) ? res.result.failed : [];
          var errs = (res.result && res.result.errors) ? res.result.errors : [];
          var msg = 'Cloned ' + created.length + ' worker(s) on ' + esc(node) + '.';
          if (created.length) {
            var details = created.map(function (c) {
              var v = c.verification || {};
              var line = 'VMID ' + esc(c.vmid) + ' → ' + esc(c.node_id || '?');
              if (v.whmcs_status) line += ' (' + esc(v.whmcs_status);
              if (v.version) line += ', v' + esc(v.version);
              if (v.whmcs_status) line += ')';
              if (v.warnings && v.warnings.length) line += ' — ' + esc(v.warnings.join('; '));
              return line;
            });
            msg += '<ul class="list-unstyled" style="margin:8px 0 0"><li>' + details.join('</li><li>') + '</li></ul>';
          }
          if (failed.length || errs.length) {
            var failMsgs = failed.length
              ? failed.map(function (f) { return esc(f.message || 'failed'); })
              : errs.map(esc);
            msg += ' Failed: ' + failMsgs.join('; ');
          }
          var alertType = (failed.length || errs.length) && !created.length ? 'danger' : ((failed.length || errs.length) ? 'warning' : 'success');
          n.innerHTML = '<div class="alert alert-' + alertType + '">' + msg + '</div>';
          renderNodes();
          renderDashboard();
        });
      });
    }

    setInterval(function () {
      renderDashboard();
      if (document.getElementById('fleet-nodes')) renderNodes();
      if (document.getElementById('fleet-builds')) renderBuilds();
      if (document.getElementById('fleet-deployments')) renderDeployments();
      if (document.getElementById('fleet-config-editor')) renderSettings();
    }, 15000);
  });
})();
