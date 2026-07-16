(function () {
  'use strict';

  var api = window.MS365_JOBS_API || '';
  var token = window.MS365_TOKEN || '';
  var currentPage = 1;
  var currentFilters = {};
  var logModalText = '';
  var logModalFilename = 'ms365-logs.txt';

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

  function isCancellableStatus(status) {
    var s = String(status || '').toLowerCase();
    return s === 'queued' || s === 'starting' || s === 'running';
  }

  function updateBulkToolbar() {
    var checked = document.querySelectorAll('.ms365-jobs-select:checked');
    var count = checked.length;
    var countEl = document.getElementById('ms365-jobs-selected-count');
    var toggle = document.getElementById('ms365-jobs-bulk-toggle');
    if (countEl) countEl.textContent = String(count);
    if (toggle) toggle.disabled = count === 0;
    var selectAll = document.getElementById('ms365-jobs-select-all');
    var rowBoxes = document.querySelectorAll('.ms365-jobs-select');
    if (selectAll && rowBoxes.length) {
      selectAll.checked = count > 0 && count === rowBoxes.length;
      selectAll.indeterminate = count > 0 && count < rowBoxes.length;
    }
  }

  function selectedRunIds() {
    return Array.prototype.map.call(
      document.querySelectorAll('.ms365-jobs-select:checked'),
      function (el) { return el.value; }
    ).filter(Boolean);
  }

  function clearSelection() {
    document.querySelectorAll('.ms365-jobs-select').forEach(function (el) {
      el.checked = false;
    });
    var selectAll = document.getElementById('ms365-jobs-select-all');
    if (selectAll) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
    }
    updateBulkToolbar();
  }

  function summarizeCancelResult(res) {
    if (!res || !res.ok) {
      return res && res.error ? res.error : 'Cancel request failed';
    }
    var cancelled = res.cancelled_count || 0;
    var skipped = res.skipped_count || 0;
    var parts = [];
    if (cancelled > 0) {
      parts.push(cancelled + ' cancelled');
    }
    if (skipped > 0) {
      parts.push(skipped + ' skipped');
    }
    if (!parts.length) {
      return 'No batches were cancelled';
    }
    var summary = parts.join(', ');
    var detail = (res.results || []).filter(function (r) { return !r.ok; }).map(function (r) {
      return (r.batch_run_id || '').slice(0, 8) + '…: ' + (r.message || 'skipped');
    });
    if (detail.length) {
      summary += ' — ' + detail.slice(0, 3).join('; ');
    }
    return summary;
  }

  function cancelBatches(ids, confirmMessage) {
    if (!ids.length) return;
    if (!window.confirm(confirmMessage)) return;
    post('jobs_cancel_batches', { batch_run_ids_json: JSON.stringify(ids) }).then(function (res) {
      alert(summarizeCancelResult(res));
      clearSelection();
      loadJobs();
    });
  }

  function buildActionsDropdown(row) {
    var rid = row.run_id || '';
    var status = String(row.status || '').toLowerCase();
    var cancellable = isCancellableStatus(status);
    var cancelPending = !!row.cancel_requested && cancellable;
    var cancelItem;
    if (cancelPending) {
      cancelItem = '<li class="disabled"><a href="#" class="text-muted" title="Cancellation already requested">Cancelling…</a></li>';
    } else if (cancellable) {
      cancelItem = '<li class="ms365-batch-cancel-wrap"><a href="#" class="ms365-batch-cancel" data-run-id="' + esc(rid) + '">Cancel</a></li>';
    } else {
      cancelItem = '<li class="disabled"><a href="#" class="text-muted" title="Only queued, starting, or running batches can be cancelled">Cancel</a></li>';
    }
    return '<div class="btn-group">' +
      '<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">' +
      'Actions <span class="caret"></span></button>' +
      '<ul class="dropdown-menu dropdown-menu-right">' +
      '<li><a href="#" class="ms365-job-logs" data-run-id="' + esc(rid) + '">Job logs</a></li>' +
      '<li><a href="#" class="ms365-worker-logs" data-run-id="' + esc(rid) + '">Worker logs</a></li>' +
      '<li><a href="#" class="ms365-batch-detail" data-run-id="' + esc(rid) + '">Detail</a></li>' +
      '<li class="divider"></li>' +
      cancelItem +
      '</ul></div>';
  }

  function statusBadge(status) {
    var s = String(status || '').toLowerCase();
    var cls = 'default';
    if (s === 'running' || s === 'starting' || s === 'queued') cls = 'info';
    else if (s === 'success') cls = 'success';
    else if (s === 'failed' || s === 'error') cls = 'danger';
    else if (s === 'partial_success' || s === 'warning') cls = 'warning';
    else if (s === 'cancelled') cls = 'default';
    return '<span class="label label-' + cls + '">' + esc(status || '—') + '</span>';
  }

  function formatDuration(sec) {
    if (sec == null || sec === '') return '—';
    var n = parseInt(sec, 10);
    if (isNaN(n) || n < 0) return '—';
    if (n < 60) return n + 's';
    var m = Math.floor(n / 60);
    var s = n % 60;
    if (m < 60) return m + 'm ' + s + 's';
    var h = Math.floor(m / 60);
    m = m % 60;
    return h + 'h ' + m + 'm';
  }

  function formatTs(val) {
    if (!val) return '—';
    try {
      return new Date(val).toLocaleString();
    } catch (e) {
      return esc(val);
    }
  }

  function collectFilters(form) {
    var data = {};
    if (!form) return data;
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'submit' || el.type === 'button') return;
      var v = (el.value || '').trim();
      if (v !== '') data[el.name] = v;
    });
    return data;
  }

  function bindTableActions(wrap) {
    if (!wrap) return;
    wrap.querySelectorAll('.ms365-copy-run').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-run-id') || '';
        if (navigator.clipboard && id) {
          navigator.clipboard.writeText(id);
        }
      });
    });
    wrap.querySelectorAll('.ms365-job-logs').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        openJobLogs(link.getAttribute('data-run-id'));
      });
    });
    wrap.querySelectorAll('.ms365-worker-logs').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        openWorkerLogs(link.getAttribute('data-run-id'));
      });
    });
    wrap.querySelectorAll('.ms365-batch-detail').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        openDetail(link.getAttribute('data-run-id'));
      });
    });
    wrap.querySelectorAll('.ms365-batch-cancel').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var id = link.getAttribute('data-run-id') || '';
        if (!id) return;
        cancelBatches([id], 'Cancel this batch? Active workloads will stop cooperatively.');
      });
    });
    wrap.querySelectorAll('.ms365-jobs-select').forEach(function (cb) {
      cb.addEventListener('change', updateBulkToolbar);
    });
    var selectAll = document.getElementById('ms365-jobs-select-all');
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        var checked = selectAll.checked;
        wrap.querySelectorAll('.ms365-jobs-select').forEach(function (el) {
          el.checked = checked;
        });
        updateBulkToolbar();
      });
    }
    updateBulkToolbar();
  }

  function renderTable(res) {
    var wrap = document.getElementById('ms365-jobs-table-wrap');
    var pag = document.getElementById('ms365-jobs-pagination');
    if (!wrap) return;
    if (!res.ok) {
      wrap.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Failed to load jobs') + '</div>';
      if (pag) pag.innerHTML = '';
      updateBulkToolbar();
      return;
    }
    var rows = res.rows || [];
    if (!rows.length) {
      wrap.innerHTML = '<p class="text-muted">No MS365 batch runs found.</p>';
      if (pag) pag.innerHTML = '';
      updateBulkToolbar();
      return;
    }
    wrap.innerHTML =
      '<table class="table table-striped table-condensed table-hover"><thead><tr>' +
      '<th style="width:28px"><input type="checkbox" id="ms365-jobs-select-all" title="Select all on this page"></th>' +
      '<th>Client</th><th>Job</th><th>Protected</th><th>OD Overage</th><th>Type</th><th>Run ID</th><th>Status</th>' +
      '<th>Started</th><th>Duration</th><th>Progress</th><th>Actions</th>' +
      '</tr></thead><tbody>' +
      rows.map(function (row) {
        var rid = row.run_id || '';
        var pct = row.progress_pct != null && row.progress_pct !== ''
          ? Math.round(parseFloat(row.progress_pct)) + '%'
          : (row.wait_reason ? 'Waiting' : '—');
        var billing = row.billing || {};
        var protectedUsers = billing.protected_users != null ? billing.protected_users : '—';
        var odOverage = billing.onedrive_overage_gib != null ? billing.onedrive_overage_gib : '—';
        var trialHint = billing.trial_status === 'trialing' ? ' <span class="label label-info">trial</span>' : '';
        var childHint = (row.child_count || 0) > 0
          ? ' <small class="text-muted">(' + esc(row.child_count) + ' workloads' +
            ((row.failed_child_count || 0) > 0 ? ', ' + esc(row.failed_child_count) + ' failed' : '') + ')</small>'
          : '';
        var statusHtml = statusBadge(row.status);
        if (row.cancel_requested && isCancellableStatus(row.status)) {
          statusHtml += ' <small class="text-muted">(cancelling)</small>';
        }
        var statusNote = row.wait_reason
          ? '<br><small class="text-muted">' + esc(row.wait_reason).slice(0, 100) + '</small>'
          : (row.error_summary ? '<br><small class="text-danger">' + esc(row.error_summary).slice(0, 80) + '</small>' : '');
        return '<tr data-run-id="' + esc(rid) + '">' +
          '<td><input type="checkbox" class="ms365-jobs-select" value="' + esc(rid) + '"></td>' +
          '<td>' + esc(row.client_name) + trialHint + '</td>' +
          '<td>' + esc(row.job_name) + childHint + '</td>' +
          '<td>' + esc(protectedUsers) + '</td>' +
          '<td>' + esc(odOverage) + ' GiB</td>' +
          '<td>' + esc(row.type || 'backup') + '</td>' +
          '<td><code style="font-size:11px">' + esc(rid) + '</code> ' +
          '<button type="button" class="btn btn-xs btn-default ms365-copy-run" data-run-id="' + esc(rid) + '" title="Copy run ID">Copy</button></td>' +
          '<td>' + statusHtml + statusNote + '</td>' +
          '<td>' + esc(formatTs(row.started_at)) + '</td>' +
          '<td>' + esc(formatDuration(row.duration_seconds)) + '</td>' +
          '<td>' + esc(pct) + '</td>' +
          '<td style="white-space:nowrap">' + buildActionsDropdown(row) + '</td></tr>';
      }).join('') +
      '</tbody></table>';

    bindTableActions(wrap);

    if (pag) {
      var total = res.total || 0;
      var page = res.page || 1;
      var perPage = res.per_page || 50;
      var pages = Math.max(1, Math.ceil(total / perPage));
      var parts = ['<span class="text-muted">' + total + ' run(s)</span>'];
      if (pages > 1) {
        parts.push(' <div class="btn-group btn-group-xs">');
        if (page > 1) {
          parts.push('<button type="button" class="btn btn-default ms365-page" data-page="' + (page - 1) + '">Prev</button>');
        }
        parts.push('<span class="btn btn-default disabled">' + page + ' / ' + pages + '</span>');
        if (page < pages) {
          parts.push('<button type="button" class="btn btn-default ms365-page" data-page="' + (page + 1) + '">Next</button>');
        }
        parts.push('</div>');
      }
      pag.innerHTML = parts.join('');
      pag.querySelectorAll('.ms365-page').forEach(function (btn) {
        btn.addEventListener('click', function () {
          currentPage = parseInt(btn.getAttribute('data-page'), 10) || 1;
          clearSelection();
          loadJobs();
        });
      });
    }
  }

  function loadJobs() {
    var wrap = document.getElementById('ms365-jobs-table-wrap');
    if (wrap) wrap.innerHTML = '<p class="text-muted">Loading jobs…</p>';
    var params = Object.assign({}, currentFilters, { page: currentPage, per_page: 50 });
    get('jobs_list', params).then(renderTable);
  }

  function showLogModal(title, text, filename) {
    logModalText = text || '';
    logModalFilename = filename || 'ms365-logs.txt';
    var titleEl = document.getElementById('ms365-jobs-log-modal-title');
    var content = document.getElementById('ms365-jobs-log-content');
    var search = document.getElementById('ms365-jobs-log-search');
    if (titleEl) titleEl.textContent = title;
    if (content) content.textContent = logModalText;
    if (search) search.value = '';
    if (typeof jQuery !== 'undefined') {
      jQuery('#ms365-jobs-log-modal').modal('show');
    }
  }

  function buildLogText(lines) {
    return (lines || []).map(function (line) {
      return line;
    }).join('\n');
  }

  function openJobLogs(batchRunId) {
    if (!batchRunId) return;
    get('jobs_batch_logs', { batch_run_id: batchRunId }).then(function (res) {
      if (!res.ok) {
        alert(res.error || 'Failed to load job logs');
        return;
      }
      var summaryEl = document.getElementById('ms365-jobs-log-summary');
      if (summaryEl) {
        var p = res.parent || {};
        summaryEl.innerHTML =
          '<p><strong>Run:</strong> <code>' + esc(batchRunId) + '</code> &nbsp; ' +
          '<strong>Status:</strong> ' + statusBadge(p.status) + ' &nbsp; ' +
          '<strong>Type:</strong> ' + esc(p.type || '') + '</p>' +
          (p.error_summary ? '<p class="text-danger"><strong>Error:</strong> ' + esc(p.error_summary) + '</p>' : '');
      }
      var lines = res.log_lines || [];
      showLogModal('Job logs — ' + batchRunId, buildLogText(lines), 'ms365-job-logs-' + batchRunId + '.txt');
    });
  }

  function openWorkerLogs(batchRunId) {
    if (!batchRunId) return;
    get('jobs_worker_logs', { batch_run_id: batchRunId }).then(function (res) {
      if (!res.ok) {
        alert(res.error || 'Failed to load worker logs');
        return;
      }
      var summaryEl = document.getElementById('ms365-jobs-log-summary');
      if (summaryEl) {
        var nodes = res.nodes || [];
        var cmds = res.journal_commands || [];
        var html = '<p><strong>Worker nodes:</strong> ' +
          (nodes.length ? nodes.map(function (n) {
            return esc(n.hostname || n.worker_node_id) + (n.proxmox_vmid ? ' (VMID ' + esc(n.proxmox_vmid) + ')' : '');
          }).join(', ') : '<span class="text-muted">none recorded</span>') + '</p>';
        if (res.journal_fallback) {
          html += '<p class="text-warning"><small>Journal fetched from Proxmox (fallback).</small></p>';
        }
        if (cmds.length) {
          html += '<details><summary>Manual journalctl commands</summary><pre style="font-size:11px">' +
            cmds.map(esc).join('\n') + '</pre></details>';
        }
        summaryEl.innerHTML = html;
      }
      var lines = res.log_lines || [];
      showLogModal('Worker logs — ' + batchRunId, buildLogText(lines), 'ms365-worker-logs-' + batchRunId + '.txt');
    });
  }

  function formatDurationMs(ms) {
    if (ms === null || ms === undefined || ms < 0) return '—';
    var sec = Math.round(ms / 1000);
    if (sec < 60) return sec + 's';
    var min = Math.floor(sec / 60);
    var rem = sec % 60;
    if (min < 60) return rem > 0 ? (min + 'm ' + rem + 's') : (min + 'm');
    var hr = Math.floor(min / 60);
    min = min % 60;
    return min > 0 ? (hr + 'h ' + min + 'm') : (hr + 'h');
  }

  function formatChildError(c) {
    var parts = [];
    var runError = (c.error_message || '').trim();
    var queueError = (c.queue_error || c.last_error || '').trim();
    if (runError) {
      parts.push(runError);
    }
    if (queueError && queueError !== runError) {
      parts.push('Queue: ' + queueError);
    }
    var skipped = c.workload_skipped || {};
    var skipKeys = Object.keys(skipped);
    if (skipKeys.length) {
      parts.push('Skipped: ' + skipKeys.map(function (k) {
        return k + '=' + skipped[k];
      }).join(', '));
    }
    return parts.join(' · ') || '—';
  }

  function openDetail(batchRunId) {
    if (!batchRunId) return;
    var body = document.getElementById('ms365-jobs-detail-body');
    if (body) body.innerHTML = '<p class="text-muted">Loading…</p>';
    if (typeof jQuery !== 'undefined') {
      jQuery('#ms365-jobs-detail-modal').modal('show');
    }
    get('jobs_batch_detail', { batch_run_id: batchRunId }).then(function (res) {
      if (!body) return;
      if (!res.ok) {
        body.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Failed') + '</div>';
        return;
      }
      var children = res.children || [];
      if (!children.length) {
        body.innerHTML = '<p class="text-muted">No child workloads found for this batch.</p>';
        return;
      }
      body.innerHTML =
        '<table class="table table-condensed"><thead><tr>' +
        '<th>Workload</th><th>Child run ID</th><th>Status</th><th>Phase</th><th>Graph</th><th>Kopia</th><th>Attempts</th><th>Error</th>' +
        '</tr></thead><tbody>' +
        children.map(function (c) {
          return '<tr><td>' + esc(c.workload_label) + '</td>' +
            '<td><code style="font-size:11px">' + esc(c.run_id) + '</code></td>' +
            '<td>' + statusBadge(c.status) + '</td>' +
            '<td><small>' + esc(c.phase || '') + '</small></td>' +
            '<td><small>' + esc(formatDurationMs(c.graph_sync_ms)) + '</small></td>' +
            '<td><small>' + esc(formatDurationMs(c.kopia_snapshot_ms)) + '</small></td>' +
            '<td>' + esc(c.attempts) + '/' + esc(c.max_attempts) +
            (c.queue_status ? ' <small>(' + esc(c.queue_status) + ')</small>' : '') + '</td>' +
            '<td><small>' + esc(formatChildError(c)) + '</small></td></tr>';
        }).join('') +
        '</tbody></table>';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ms365-jobs-filters');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        currentFilters = collectFilters(form);
        currentPage = 1;
        clearSelection();
        loadJobs();
      });
    }
    var reset = document.getElementById('ms365-jobs-reset');
    if (reset) {
      reset.addEventListener('click', function () {
        if (form) form.reset();
        currentFilters = {};
        currentPage = 1;
        clearSelection();
        loadJobs();
      });
    }
    var refresh = document.getElementById('ms365-jobs-refresh');
    if (refresh) refresh.addEventListener('click', loadJobs);

    var bulkCancel = document.getElementById('ms365-jobs-bulk-cancel');
    if (bulkCancel) {
      bulkCancel.addEventListener('click', function (e) {
        e.preventDefault();
        var ids = selectedRunIds();
        if (!ids.length) return;
        cancelBatches(ids, 'Cancel ' + ids.length + ' selected batch(es)?');
      });
    }

    var logSearch = document.getElementById('ms365-jobs-log-search');
    if (logSearch) {
      logSearch.addEventListener('input', function () {
        var content = document.getElementById('ms365-jobs-log-content');
        if (!content) return;
        var q = logSearch.value.trim().toLowerCase();
        if (!q) {
          content.textContent = logModalText;
          return;
        }
        content.textContent = logModalText.split('\n').filter(function (line) {
          return line.toLowerCase().indexOf(q) !== -1;
        }).join('\n');
      });
    }
    var download = document.getElementById('ms365-jobs-log-download');
    if (download) {
      download.addEventListener('click', function () {
        var content = document.getElementById('ms365-jobs-log-content');
        var text = content ? content.textContent : logModalText;
        var blob = new Blob([text], { type: 'text/plain' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = logModalFilename;
        a.click();
        URL.revokeObjectURL(a.href);
      });
    }

    updateBulkToolbar();
    loadJobs();
  });
})();
