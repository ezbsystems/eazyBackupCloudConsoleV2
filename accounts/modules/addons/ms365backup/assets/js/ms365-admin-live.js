(function () {
  'use strict';

  var cfg = window.MS365_LIVE || {};
  var apiBase = cfg.apiBase || '';
  var token = cfg.token || '';
  var runId = cfg.runId || '';
  var initialStatus = String(cfg.initialStatus || '').toLowerCase();
  var isRunning = !!cfg.isRunning || ['running', 'starting', 'queued'].indexOf(initialStatus) >= 0;

  var progressInterval = null;
  var eventsInterval = null;
  var lastLogsHash = null;
  var lastEventId = 0;
  var terminalEventSeen = false;
  var isPaused = false;
  var logEntries = [];
  var logSearchQuery = '';
  var logPage = 1;
  var pausedLogBuffer = [];
  var processedLogHashes = new Set();
  var cancelRequestInFlight = false;
  var pendingCancelForce = false;
  var durationStartMs = null;
  var durationEndMs = null;
  var durationTimer = null;
  var currentPct = 0;
  var tweenId = null;

  var LOG_PAGE_SIZE = 200;
  var MAX_STORED_LOG_LINES = 20000;
  var SPEED_STALE_SECONDS = 30;
  var TERMINAL_STATUSES = ['success', 'failed', 'cancelled', 'warning', 'partial_success'];

  var MS365_SPEED_HINTS = {
    items: 'Items enumerated from Microsoft 365',
    graph_requests: 'Microsoft Graph API activity',
    upload: 'Data sent to cloud storage',
    hash: 'Data hashed before deduplication'
  };

  var STATUS_CONFIGS = {
    success: { text: 'Success', bar: 'success', badge: 'eb-badge eb-badge--success eb-badge--dot', stageColor: 'var(--eb-success-text)', dot: 'active' },
    failed: { text: 'Failed', bar: 'failed', badge: 'eb-badge eb-badge--danger eb-badge--dot', stageColor: 'var(--eb-danger-text)', dot: 'error' },
    running: { text: 'Running', bar: 'running', badge: 'eb-badge eb-badge--info eb-badge--dot', stageColor: 'var(--eb-info-text)', dot: 'pending' },
    starting: { text: 'Starting', bar: 'running', badge: 'eb-badge eb-badge--info eb-badge--dot', stageColor: 'var(--eb-info-text)', dot: 'pending' },
    queued: { text: 'Queued', bar: 'running', badge: 'eb-badge eb-badge--warning eb-badge--dot', stageColor: 'var(--eb-warning-text)', dot: 'pending' },
    warning: { text: 'Warning', bar: 'warning', badge: 'eb-badge eb-badge--warning eb-badge--dot', stageColor: 'var(--eb-warning-text)', dot: 'warning' },
    cancelled: { text: 'Cancelled', bar: 'neutral', badge: 'eb-badge eb-badge--neutral eb-badge--dot', stageColor: 'var(--eb-text-muted)', dot: 'inactive' },
    partial_success: { text: 'Partial Success', bar: 'warning', badge: 'eb-badge eb-badge--warning eb-badge--dot', stageColor: 'var(--eb-warning-text)', dot: 'warning' }
  };

  var STAGE_FALLBACKS = {
    running: 'Uploading',
    starting: 'Preparing',
    queued: 'Queued',
    success: 'Completed',
    failed: 'Failed',
    warning: 'Warning',
    cancelled: 'Cancelled',
    partial_success: 'Partial Success'
  };

  var WORKLOAD_STATUS_BADGES = {
    success: 'eb-badge eb-badge--success eb-badge--dot',
    failed: 'eb-badge eb-badge--danger eb-badge--dot',
    error: 'eb-badge eb-badge--danger eb-badge--dot',
    running: 'eb-badge eb-badge--info eb-badge--dot',
    starting: 'eb-badge eb-badge--info eb-badge--dot',
    queued: 'eb-badge eb-badge--warning eb-badge--dot',
    warning: 'eb-badge eb-badge--warning eb-badge--dot',
    partial_success: 'eb-badge eb-badge--warning eb-badge--dot',
    cancelled: 'eb-badge eb-badge--neutral eb-badge--dot'
  };

  function get(op, params) {
    var q = new URLSearchParams(params || {});
    q.set('op', op);
    q.set('run_id', runId);
    return fetch(apiBase + '&' + q.toString(), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); });
  }

  function post(op, data) {
    var body = new URLSearchParams(data || {});
    body.set('token', token);
    body.set('run_id', runId);
    return fetch(apiBase + '&op=' + encodeURIComponent(op), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function formatBytes(bytes) {
    if (!bytes) return '0.00 Bytes';
    var k = 1024;
    var sizes = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    var value = bytes / Math.pow(k, i);
    return value.toFixed(2) + ' ' + sizes[i];
  }

  function formatCount(value) {
    var num = Number(value) || 0;
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
      return new Intl.NumberFormat().format(num);
    }
    return String(num);
  }

  function formatEta(secondsTotal) {
    var s = Math.max(0, Math.floor(Number(secondsTotal) || 0));
    if (s <= 0) return '—';
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    var sec = s % 60;
    var out = '';
    if (h > 0) out += h + 'h ';
    if (m > 0) out += m + 'm ';
    out += sec + 's';
    return out.trim();
  }

  function isMs365SpeedStale(run) {
    var updatedAt = parseInt(run.speed_updated_at, 10) || 0;
    if (!updatedAt) return true;
    return ((Date.now() / 1000) - updatedAt) > SPEED_STALE_SECONDS;
  }

  function formatMs365SpeedDisplay(run) {
    if (isMs365SpeedStale(run)) return '—';
    var kind = run.speed_metric_kind || 'none';
    if (kind === 'upload' || kind === 'hash') {
      var bps = parseInt(run.speed_bytes_per_sec, 10) || 0;
      return bps ? (formatBytes(bps) + '/s') : '—';
    }
    if (kind === 'items') {
      var ips = parseInt(run.items_per_sec, 10) || 0;
      return ips ? (formatCount(ips) + '/s') : '—';
    }
    if (kind === 'graph_requests') {
      var grs = parseInt(run.graph_requests_per_sec, 10) || 0;
      return grs ? (formatCount(grs) + '/s') : '—';
    }
    return '—';
  }

  function ms365SpeedLabel(run) {
    if (run.speed_metric_label) return run.speed_metric_label;
    var kind = run.speed_metric_kind || 'none';
    var labels = { items: 'Items/s', graph_requests: 'Graph requests/s', upload: 'Upload speed', hash: 'Hash speed' };
    return labels[kind] || 'Speed';
  }

  function ms365SpeedHint(run) {
    return MS365_SPEED_HINTS[run.speed_metric_kind || 'none'] || '';
  }

  function setLiveBarFillState(bar, state) {
    if (!bar) return;
    bar.className = 'eb-live-bar-fill';
    if (state === 'running') bar.classList.add('running');
    else if (state === 'failed') bar.classList.add('failed');
    else if (state === 'warning') bar.classList.add('eb-live-bar-fill--warning');
    else if (state === 'neutral') bar.classList.add('eb-live-bar-fill--neutral');
    else if (state === 'indeterminate') bar.classList.add('eb-live-bar-fill--indeterminate');
    else if (state === 'success') bar.classList.add('success');
  }

  function smoothProgressTo(targetPct, duration) {
    duration = duration || 600;
    if (tweenId) cancelAnimationFrame(tweenId);
    var bar = document.getElementById('progressBar');
    var label = document.getElementById('progressPercentValue');
    var start = performance.now();
    var from = Math.max(0, Math.min(100, currentPct));
    var to = Math.max(from, Math.min(100, targetPct));
    function ease(t) { return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t; }
    function step(now) {
      var t = Math.min(1, (now - start) / duration);
      var v = from + (to - from) * ease(t);
      currentPct = v;
      if (bar) {
        bar.style.width = v.toFixed(2) + '%';
        bar.setAttribute('aria-valuenow', v.toFixed(2));
      }
      if (label) label.textContent = v.toFixed(2);
      if (t < 1) tweenId = requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function workloadStatusLabel(status) {
    var normalized = String(status || '').toLowerCase();
    if (!normalized) return 'Unknown';
    if (normalized === 'partial_success') return 'Partial success';
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
  }

  function workloadStatusBadgeClass(status) {
    return WORKLOAD_STATUS_BADGES[String(status || '').toLowerCase()] || 'eb-badge eb-badge--neutral eb-badge--dot';
  }

  function workloadProgressBarState(status) {
    var normalized = String(status || '').toLowerCase();
    if (['running', 'starting', 'queued'].indexOf(normalized) >= 0) return 'running';
    if (['failed', 'error'].indexOf(normalized) >= 0) return 'failed';
    if (['warning', 'partial_success'].indexOf(normalized) >= 0) return 'warning';
    if (normalized === 'cancelled') return 'neutral';
    if (normalized === 'success') return 'success';
    return 'neutral';
  }

  function workloadIsActive(status) {
    return ['running', 'starting', 'queued'].indexOf(String(status || '').toLowerCase()) >= 0;
  }

  function formatMs365WorkloadSummary(run) {
    var total = parseInt(run.total_workloads, 10) || 0;
    if (total <= 0) return '';
    var completed = parseInt(run.completed_workloads, 10) || 0;
    return formatCount(completed) + ' / ' + formatCount(total) + ' complete';
  }

  function formatMs365RunningQueuedSummary(run) {
    var running = parseInt(run.active_running_workloads, 10) || 0;
    var queued = parseInt(run.queued_workloads, 10) || 0;
    var parts = [];
    if (running > 0) parts.push(formatCount(running) + ' running');
    if (queued > 0) parts.push(formatCount(queued) + ' queued');
    return parts.length ? parts.join(' · ') : '—';
  }

  function updateMs365WorkloadsSummary(workloads) {
    var summary = document.getElementById('ms365WorkloadsSummary');
    if (!summary) return;
    var list = Array.isArray(workloads) ? workloads : [];
    if (!list.length) {
      summary.textContent = 'No workloads';
      return;
    }
    var total = list.length;
    var complete = list.filter(function (w) { return ['success', 'cancelled'].indexOf(String(w.status || '').toLowerCase()) >= 0; }).length;
    var failed = list.filter(function (w) { return ['failed', 'error'].indexOf(String(w.status || '').toLowerCase()) >= 0; }).length;
    var running = list.filter(function (w) { return ['running', 'starting'].indexOf(String(w.status || '').toLowerCase()) >= 0; }).length;
    var queued = list.filter(function (w) { return String(w.status || '').toLowerCase() === 'queued'; }).length;
    var text = complete + '/' + total + ' complete';
    if (running > 0) text += ' · ' + running + ' running';
    if (queued > 0) text += ' · ' + queued + ' queued';
    if (failed > 0) text += ' · ' + failed + ' failed';
    summary.textContent = text;
  }

  function updateMs365BatchWorkloadsLine(run) {
    var valueEl = document.getElementById('ms365WorkloadsValue');
    var runningEl = document.getElementById('ms365RunningWorkloadsValue');
    var summary = formatMs365WorkloadSummary(run);
    if (valueEl) valueEl.textContent = summary || '—';
    if (runningEl) runningEl.textContent = formatMs365RunningQueuedSummary(run);
  }

  function formatWorkloadFreshnessAge(seconds) {
    var s = Math.max(0, Math.floor(Number(seconds) || 0));
    if (s < 60) return s + 's';
    if (s < 3600) return Math.floor(s / 60) + 'm';
    var hours = Math.floor(s / 3600);
    var minutes = Math.floor((s % 3600) / 60);
    return minutes > 0 ? (hours + 'h ' + minutes + 'm') : (hours + 'h');
  }

  function formatWorkloadFreshnessLabel(workload) {
    var status = String(workload.status || '').toLowerCase();
    if (['running', 'starting'].indexOf(status) < 0) return null;
    var age = workload.last_progress_age_seconds;
    if (age === null || age === undefined) return null;
    var ageText = formatWorkloadFreshnessAge(age);
    return workload.stalled ? ('No progress ' + ageText) : ('Active ' + ageText + ' ago');
  }

  function syncMs365WorkloadsChrome(live) {
    var dot = document.getElementById('ms365WorkloadsLiveDot');
    if (dot) dot.style.display = live ? '' : 'none';
  }

  function renderWorkloadErrorCell(errorCell, workload) {
    while (errorCell.firstChild) errorCell.removeChild(errorCell.firstChild);
    errorCell.removeAttribute('title');
    var events = Array.isArray(workload.events) ? workload.events : [];
    var fallback = (workload.error || '').trim();
    if (!events.length && !fallback) {
      errorCell.className = 'eb-live-workloads-error is-empty';
      errorCell.textContent = '—';
      return;
    }
    errorCell.className = 'eb-live-workloads-error';
    if (!events.length) {
      errorCell.textContent = fallback;
      if (fallback.length > 120) errorCell.title = fallback;
      return;
    }
    var list = document.createElement('div');
    list.className = 'eb-live-workloads-events';
    events.forEach(function (eventItem) {
      var item = document.createElement('div');
      var level = String(eventItem.level || 'error').toLowerCase();
      item.className = 'eb-live-workloads-event' + (level === 'warning' ? ' is-warning' : '');
      if (eventItem.ts) {
        var ts = document.createElement('span');
        ts.className = 'eb-live-workloads-event-ts';
        ts.textContent = '[' + eventItem.ts + ']';
        item.appendChild(ts);
      }
      var msg = document.createElement('span');
      msg.className = 'eb-live-workloads-event-msg';
      msg.textContent = eventItem.message || '';
      item.appendChild(msg);
      list.appendChild(item);
    });
    errorCell.appendChild(list);
  }

  function renderMs365Workloads(workloads) {
    var tbody = document.getElementById('ms365WorkloadsBody');
    if (!tbody) return;
    var list = Array.isArray(workloads) ? workloads : [];
    updateMs365WorkloadsSummary(list);
    while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
    if (!list.length) {
      var emptyRow = document.createElement('tr');
      var emptyCell = document.createElement('td');
      emptyCell.colSpan = 5;
      emptyCell.className = 'eb-type-caption italic eb-text-muted';
      emptyCell.textContent = 'No workloads found for this run.';
      emptyRow.appendChild(emptyCell);
      tbody.appendChild(emptyRow);
      return;
    }
    list.forEach(function (workload) {
      var row = document.createElement('tr');
      if (workloadIsActive(workload.status)) row.classList.add('eb-live-workloads-row--active');

      var workloadCell = document.createElement('td');
      var typeLine = document.createElement('span');
      typeLine.className = 'eb-live-workloads-type';
      typeLine.textContent = workload.workload_type || 'Workload';
      var nameLine = document.createElement('span');
      nameLine.className = 'eb-live-workloads-name';
      nameLine.textContent = workload.workload_name || '—';
      workloadCell.appendChild(typeLine);
      workloadCell.appendChild(nameLine);

      var statusCell = document.createElement('td');
      var statusWrap = document.createElement('div');
      statusWrap.className = 'eb-live-workloads-status';
      var statusBadge = document.createElement('span');
      statusBadge.className = workloadStatusBadgeClass(workload.status);
      statusBadge.textContent = workloadStatusLabel(workload.status);
      statusWrap.appendChild(statusBadge);
      var freshnessLabel = formatWorkloadFreshnessLabel(workload);
      if (freshnessLabel) {
        var freshnessText = document.createElement('span');
        freshnessText.className = 'eb-live-workloads-freshness-text' + (workload.stalled ? ' is-stalled' : '');
        freshnessText.textContent = freshnessLabel;
        statusWrap.appendChild(freshnessText);
      }
      statusCell.appendChild(statusWrap);

      var phaseCell = document.createElement('td');
      phaseCell.textContent = workload.phase_label || workload.phase || '—';

      var errorCell = document.createElement('td');
      renderWorkloadErrorCell(errorCell, workload);

      var progressCell = document.createElement('td');
      progressCell.className = 'eb-table-cell-numeric';
      var progressWrap = document.createElement('div');
      progressWrap.className = 'eb-live-workloads-progress';
      var progressLabel = document.createElement('span');
      progressLabel.className = 'eb-live-workloads-progress-label';
      progressLabel.textContent = workload.progress_label || '—';
      progressWrap.appendChild(progressLabel);

      var notes = Array.isArray(workload.notes) ? workload.notes.filter(function (n) { return String(n || '').trim() !== ''; }) : [];
      if (notes.length > 0) {
        var notesWrap = document.createElement('div');
        notesWrap.className = 'eb-live-workloads-notes';
        notes.forEach(function (noteText) {
          var noteLine = document.createElement('span');
          noteLine.className = 'eb-live-workloads-note';
          noteLine.textContent = noteText;
          notesWrap.appendChild(noteLine);
        });
        progressWrap.appendChild(notesWrap);
      }

      var itemsTotal = Number(workload.items_total) || 0;
      var percent = Number(workload.percent) || 0;
      if (itemsTotal > 0 || percent > 0) {
        var barShell = document.createElement('div');
        barShell.className = 'eb-live-bar';
        barShell.setAttribute('aria-hidden', 'true');
        var barFill = document.createElement('div');
        barFill.className = 'eb-live-bar-fill';
        barFill.style.width = Math.max(0, Math.min(100, percent)) + '%';
        setLiveBarFillState(barFill, workloadProgressBarState(workload.status));
        barShell.appendChild(barFill);
        progressWrap.appendChild(barShell);
      }
      progressCell.appendChild(progressWrap);

      row.appendChild(workloadCell);
      row.appendChild(statusCell);
      row.appendChild(phaseCell);
      row.appendChild(errorCell);
      row.appendChild(progressCell);
      tbody.appendChild(row);
    });
  }

  function parseRunTimestamp(value) {
    if (!value) return null;
    if (typeof value === 'number' && !isNaN(value)) return value < 1e12 ? value * 1000 : value;
    var raw = String(value).trim();
    if (!raw) return null;
    if (/^\d+$/.test(raw)) {
      var n = parseInt(raw, 10);
      return n < 1e12 ? n * 1000 : n;
    }
    var parsed = Date.parse(raw.indexOf('T') >= 0 ? raw : raw.replace(' ', 'T'));
    return isNaN(parsed) ? null : parsed;
  }

  function resolveRunEpochMs(run, field, epochField) {
    if (!run) return null;
    if (run[epochField] !== undefined && run[epochField] !== null) {
      var n = Number(run[epochField]);
      if (!isNaN(n) && n > 0) return n;
    }
    return parseRunTimestamp(run[field]);
  }

  function formatDurationFromMs(ms) {
    var totalSeconds = Math.max(0, Math.floor(ms / 1000));
    var days = Math.floor(totalSeconds / 86400);
    var hours = Math.floor((totalSeconds % 86400) / 3600);
    var minutes = Math.floor((totalSeconds % 3600) / 60);
    var seconds = totalSeconds % 60;
    var pad = function (v) { return String(v).padStart(2, '0'); };
    var base = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
    return days > 0 ? (days + 'd ' + base) : base;
  }

  function ensureDurationStart(run) {
    if (durationStartMs) return;
    durationStartMs = resolveRunEpochMs(run, 'started_at', 'started_at_epoch_ms') || Date.now();
  }

  function startDurationTicker() {
    if (durationTimer) return;
    durationTimer = setInterval(function () {
      if (!durationStartMs) return;
      var el = document.getElementById('durationValue');
      if (!el) return;
      el.textContent = formatDurationFromMs(Math.max(0, Date.now() - durationStartMs));
    }, 1000);
  }

  function stopDurationTicker() {
    if (durationTimer) {
      clearInterval(durationTimer);
      durationTimer = null;
    }
  }

  function updateDuration(run) {
    var durationValueEl = document.getElementById('durationValue');
    var durationLabelEl = document.getElementById('durationStatLabel');
    if (!durationValueEl || !durationLabelEl) return;
    ensureDurationStart(run);
    var now = Date.now();
    var isTerminal = TERMINAL_STATUSES.indexOf(run.status) >= 0;
    if (isTerminal) {
      if (!durationEndMs) {
        durationEndMs = resolveRunEpochMs(run, 'finished_at', 'finished_at_epoch_ms') || now;
      }
      stopDurationTicker();
      durationLabelEl.textContent = 'Duration';
      durationValueEl.textContent = formatDurationFromMs(Math.max(0, durationEndMs - durationStartMs));
    } else {
      durationEndMs = null;
      durationLabelEl.textContent = 'Elapsed';
      durationValueEl.textContent = formatDurationFromMs(Math.max(0, now - durationStartMs));
      startDurationTicker();
    }
  }

  function updateStatusDisplay(statusConfig) {
    var badgeEl = document.getElementById('liveHeaderBadge');
    var stageDot = document.getElementById('stageStatusDot');
    if (badgeEl) {
      badgeEl.className = statusConfig.badge || 'eb-badge eb-badge--neutral eb-badge--dot';
      badgeEl.textContent = statusConfig.text;
    }
    if (stageDot) {
      var d = statusConfig.dot || 'inactive';
      stageDot.className = 'eb-status-dot eb-status-dot--' + (d === 'pending' ? 'pending' : d === 'active' ? 'active' : d === 'error' ? 'error' : d === 'warning' ? 'warning' : 'inactive');
    }
  }

  function updateStageLabel(run) {
    var stageEl = document.getElementById('stageLabel');
    if (!stageEl) return;
    var fallback = STAGE_FALLBACKS[run.status] || (run.status ? run.status.charAt(0).toUpperCase() + run.status.slice(1) : '');
    stageEl.textContent = run.stage || fallback || 'Pending';
    var cfg = STATUS_CONFIGS[run.status];
    if (cfg) stageEl.style.color = cfg.stageColor;
  }

  function refreshErrorSummary(run) {
    var container = document.getElementById('errorSummaryContainer');
    var text = document.getElementById('errorSummaryText');
    var forceBtn = document.getElementById('forceCancelButton');
    var summary = (run.error_summary || '').trim();
    if (container && text) {
      if (summary) {
        text.textContent = summary;
        container.classList.remove('hidden');
      } else {
        container.classList.add('hidden');
      }
    }
    if (forceBtn) {
      var status = (run.status || '').toLowerCase();
      forceBtn.style.display = summary && ['running', 'starting', 'queued'].indexOf(status) >= 0 ? '' : 'none';
    }
  }

  function syncLogPanelChrome(live) {
    var dot = document.getElementById('logLiveDot');
    var icon = document.getElementById('logStaticIcon');
    var title = document.getElementById('logPanelTitle');
    var pauseBtn = document.getElementById('pauseUpdatesBtn');
    if (title) title.textContent = live ? 'Live Logs' : 'Run Logs';
    if (dot) dot.style.display = live ? '' : 'none';
    if (icon) icon.style.display = live ? 'none' : '';
    if (pauseBtn) pauseBtn.style.display = live ? '' : 'none';
  }

  function setCurrentFileRowVisible(visible) {
    var row = document.getElementById('currentFileRow');
    if (!row) return;
    row.classList.toggle('hidden', !visible);
  }

  function stopPolling() {
    if (progressInterval) {
      clearInterval(progressInterval);
      progressInterval = null;
    }
    if (eventsInterval) {
      clearInterval(eventsInterval);
      eventsInterval = null;
    }
  }

  function updateHealthBanner(run) {
    var banner = document.getElementById('ms365HealthBanner');
    var text = document.getElementById('ms365HealthBannerText');
    if (!banner || !text) return;
    var health = run.health || {};
    var wedged = !!health.wedged_worker;
    var stalled = parseInt(health.stalled_workload_count, 10) || 0;
    var warning = (health.health_warning || '').trim();
    var show = wedged || stalled > 0;
    if (show) {
      text.textContent = warning || ('Worker health issue detected (' + stalled + ' stalled workload' + (stalled === 1 ? '' : 's') + ')');
      banner.classList.remove('hidden');
    } else {
      banner.classList.add('hidden');
      text.textContent = '';
    }
  }

  function updateProgress() {
    if (isPaused) return;
    get('live_progress', { ts: String(Date.now()) }).then(function (data) {
      if (!data || data.status !== 'success' || !data.run) return;
      var run = data.run;
      refreshErrorSummary(run);
      updateHealthBanner(run);

      if (Array.isArray(run.workloads)) renderMs365Workloads(run.workloads);
      updateMs365BatchWorkloadsLine(run);

      var progressPct = 0;
      var apiPct = parseFloat(run.progress_pct);
      if (!isNaN(apiPct) && apiPct > 0) {
        progressPct = apiPct;
      } else {
        var completedWl = parseInt(run.completed_workloads, 10) || 0;
        var totalWl = parseInt(run.total_workloads, 10) || 0;
        if (totalWl > 0) {
          progressPct = Math.min(100, (completedWl / totalWl) * 100);
        } else if (!isNaN(apiPct)) {
          progressPct = Math.max(0, apiPct);
        }
      }

      var progressBar = document.getElementById('progressBar');
      var isFinished = TERMINAL_STATUSES.indexOf(run.status) >= 0;

      if (!isFinished) {
        if (progressPct > 0.01) {
          setLiveBarFillState(progressBar, 'running');
          smoothProgressTo(progressPct);
        } else if (progressBar) {
          progressBar.style.width = '100%';
          progressBar.setAttribute('aria-valuenow', '0.00');
          setLiveBarFillState(progressBar, 'indeterminate');
        }
      } else if (run.status === 'success') {
        smoothProgressTo(100, 800);
        setLiveBarFillState(progressBar, 'success');
      } else if (progressBar) {
        setLiveBarFillState(progressBar, run.status === 'failed' ? 'failed' : run.status === 'cancelled' ? 'neutral' : 'warning');
      }

      var bytesProcessed = run.bytes_processed || run.bytes_transferred || 0;
      var processedEl = document.getElementById('bytesProcessedValue');
      if (processedEl) processedEl.textContent = formatBytes(bytesProcessed);
      var transferredEl = document.getElementById('bytesTransferredValue');
      if (transferredEl) transferredEl.textContent = formatBytes(run.bytes_transferred || 0);

      var uploadedSavingsEl = document.getElementById('uploadedSavings');
      if (uploadedSavingsEl) {
        var byteStatsComparable = run.byte_stats_comparable !== false;
        if (bytesProcessed > 0 && byteStatsComparable) {
          var transferred = run.bytes_transferred || 0;
          var savedBytes = Math.max(0, bytesProcessed - transferred);
          var savedPercent = bytesProcessed > 0 ? (savedBytes / bytesProcessed) * 100 : 0;
          uploadedSavingsEl.textContent = 'Saved: ' + formatBytes(savedBytes) + ' (' + savedPercent.toFixed(1) + '%)';
        } else {
          uploadedSavingsEl.textContent = '';
        }
      }

      var speedValueEl = document.getElementById('speedValue');
      var speedStatLabel = document.getElementById('speedStatLabel');
      if (speedValueEl) {
        if (isFinished) {
          speedValueEl.textContent = '—';
          speedValueEl.classList.remove('highlight');
        } else {
          var speedText = formatMs365SpeedDisplay(run);
          speedValueEl.textContent = speedText;
          speedValueEl.classList.toggle('highlight', speedText !== '—');
        }
      }
      if (speedStatLabel) speedStatLabel.textContent = isFinished ? 'Speed' : ms365SpeedLabel(run);
      var speedHintEl = document.getElementById('speedHint');
      if (speedHintEl) {
        speedHintEl.textContent = (isFinished || isMs365SpeedStale(run)) ? '' : ms365SpeedHint(run);
      }

      var graphActivityStat = document.getElementById('ms365GraphActivityStat');
      var graphRequestsValue = document.getElementById('graphRequestsValue');
      if (graphActivityStat && graphRequestsValue) {
        var graphRequests = parseInt(run.graph_requests_total, 10) || 0;
        var showGraphStat = (run.speed_metric_kind === 'graph_requests' && !isMs365SpeedStale(run))
          || (!run.byte_stats_comparable && !isFinished && graphRequests > 0);
        graphActivityStat.classList.toggle('hidden', !showGraphStat);
        graphRequestsValue.textContent = showGraphStat ? formatCount(graphRequests) : '—';
      }

      var itemsSpeedValueEl = document.getElementById('itemsSpeedValue');
      var itemsSpeedHintEl = document.getElementById('itemsSpeedHint');
      if (itemsSpeedValueEl) {
        if (isFinished) {
          itemsSpeedValueEl.textContent = '—';
          itemsSpeedValueEl.classList.remove('highlight');
          if (itemsSpeedHintEl) itemsSpeedHintEl.textContent = '';
        } else {
          var speedKind = run.speed_metric_kind || 'none';
          if (speedKind === 'items' && !isMs365SpeedStale(run)) {
            itemsSpeedValueEl.textContent = '—';
            itemsSpeedValueEl.classList.remove('highlight');
            if (itemsSpeedHintEl) itemsSpeedHintEl.textContent = '';
          } else {
            var itemsPerSec = (!isMs365SpeedStale(run) && run.items_per_sec) ? (parseInt(run.items_per_sec, 10) || 0) : 0;
            itemsSpeedValueEl.textContent = itemsPerSec ? (formatCount(itemsPerSec) + '/s') : '—';
            itemsSpeedValueEl.classList.toggle('highlight', !!itemsPerSec);
            if (itemsSpeedHintEl) itemsSpeedHintEl.textContent = itemsPerSec ? 'Enumeration rate' : '';
          }
        }
      }

      var graphThrottleHint = document.getElementById('graphThrottleHint');
      var graphThrottleCount = document.getElementById('graphThrottleCount');
      if (graphThrottleHint) {
        var throttled = !!run.graph_throttled;
        var hits429 = parseInt(run.graph_429_hits_total, 10) || 0;
        var ratio = parseFloat(run.graph_429_ratio) || 0;
        var material = throttled || ratio >= 0.05;
        graphThrottleHint.classList.toggle('hidden', !(material && !isFinished));
        if (graphThrottleCount) {
          graphThrottleCount.textContent = hits429 > 0 ? (' (' + formatCount(hits429) + ' rate-limit responses so far)') : '';
        }
      }

      var filesValueEl = document.getElementById('filesValue');
      var foldersValueEl = document.getElementById('foldersValue');
      if (filesValueEl) {
        var filesDone = (run.files_done !== undefined && run.files_done !== null) ? run.files_done : (run.objects_transferred || 0);
        var filesTotal = (run.files_total !== undefined && run.files_total !== null) ? run.files_total : (run.objects_total || 0);
        filesValueEl.textContent = filesTotal > 0 ? (formatCount(filesDone) + ' / ' + formatCount(filesTotal)) : formatCount(filesDone);
      }
      if (foldersValueEl) {
        foldersValueEl.textContent = (run.folders_done !== undefined && run.folders_done !== null) ? formatCount(run.folders_done) : '—';
      }

      var stageEtaEl = document.getElementById('stageEta');
      if (stageEtaEl) {
        if (isFinished) stageEtaEl.textContent = '';
        else if (run.eta_seconds !== undefined && run.eta_seconds !== null && run.eta_seconds >= 0) {
          stageEtaEl.textContent = ' — ETA ' + formatEta(run.eta_seconds);
        } else stageEtaEl.textContent = '';
      }

      var currentItemEl = document.getElementById('currentItem');
      if (currentItemEl) currentItemEl.textContent = run.current_item || '—';

      var detailsStarted = document.getElementById('detailsStartedAt');
      if (detailsStarted && run.started_at) detailsStarted.textContent = run.started_at;
      var detailsFinished = document.getElementById('detailsFinishedAt');
      if (detailsFinished) detailsFinished.textContent = run.finished_at || '—';

      var statusConfig = STATUS_CONFIGS[run.status] || {
        text: run.status ? run.status.charAt(0).toUpperCase() + run.status.slice(1) : 'Unknown',
        bar: 'neutral',
        badge: 'eb-badge eb-badge--neutral eb-badge--dot',
        stageColor: 'var(--eb-text-muted)',
        dot: 'inactive'
      };
      updateStatusDisplay(statusConfig);
      updateStageLabel(run);
      updateDuration(run);

      var cancelButton = document.getElementById('cancelButton');
      if (cancelButton) {
        var shouldShow = ['running', 'starting', 'queued'].indexOf(run.status) >= 0;
        cancelButton.classList.toggle('hidden', !shouldShow);
        cancelButton.style.display = shouldShow ? '' : 'none';
      }

      var newIsRunning = ['running', 'starting', 'queued'].indexOf(run.status) >= 0;
      isRunning = newIsRunning;
      setCurrentFileRowVisible(newIsRunning);
      syncLogPanelChrome(newIsRunning);
      syncMs365WorkloadsChrome(newIsRunning);

      if (TERMINAL_STATUSES.indexOf(run.status) >= 0) {
        stopPolling();
        isRunning = false;
        syncLogPanelChrome(false);
        syncMs365WorkloadsChrome(false);
      }
    }).catch(function (err) {
      console.error('Error updating progress:', err);
    });
  }

  function logEntryKey(entry) {
    return JSON.stringify({ l: entry.level || '', t: entry.ts || '', m: entry.message || '' });
  }

  function trimLogStore() {
    if (logEntries.length > MAX_STORED_LOG_LINES) logEntries.length = MAX_STORED_LOG_LINES;
  }

  function appendLogEntry(entry) {
    var key = logEntryKey(entry);
    if (processedLogHashes.has(key)) return;
    processedLogHashes.add(key);
    if (isPaused) {
      pausedLogBuffer.push(entry);
      return;
    }
    logEntries.unshift(entry);
    trimLogStore();
    if (logPage === 1) renderLogPage();
    else updateLogFooterOnly();
  }

  function normalizeLevel(level) {
    var l = (level || 'info').toLowerCase();
    if (l === 'warning') return 'warn';
    if (['warn', 'error', 'debug', 'ok', 'info'].indexOf(l) >= 0) return l;
    return 'info';
  }

  function getLogPageSlice() {
    var start = (logPage - 1) * LOG_PAGE_SIZE;
    return logEntries.slice(start, start + LOG_PAGE_SIZE);
  }

  function renderLogPage() {
    var liveLogsContainer = document.getElementById('liveLogs');
    if (!liveLogsContainer) return;
    var slice = getLogPageSlice();
    if (logSearchQuery) {
      slice = slice.filter(function (entry) {
        var hay = (entry.message || '') + ' ' + (entry.level || '') + ' ' + (entry.ts || '');
        return hay.toLowerCase().indexOf(logSearchQuery) >= 0;
      });
    }
    while (liveLogsContainer.firstChild) liveLogsContainer.removeChild(liveLogsContainer.firstChild);
    if (!slice.length) {
      var empty = document.createElement('div');
      empty.id = 'liveLogsEmpty';
      empty.className = 'eb-type-caption italic px-4 py-3';
      empty.style.color = 'var(--eb-text-muted)';
      empty.textContent = logSearchQuery ? 'No matches on this page.' : 'Waiting for log data…';
      liveLogsContainer.appendChild(empty);
      updateLogFooterOnly();
      return;
    }
    slice.forEach(function (entry, idx) {
      var line = document.createElement('div');
      line.className = 'eb-log-line' + (logPage === 1 && idx === 0 && !logSearchQuery ? ' is-newest' : '');
      var levelSpan = document.createElement('span');
      levelSpan.className = 'eb-log-level ' + normalizeLevel(entry.level);
      levelSpan.textContent = (entry.level || 'info').toUpperCase();
      var tsSpan = document.createElement('span');
      tsSpan.className = 'eb-log-timestamp';
      tsSpan.textContent = entry.ts ? ('[' + entry.ts + ']') : '';
      var msgSpan = document.createElement('span');
      msgSpan.className = 'eb-log-message';
      msgSpan.textContent = entry.message || '';
      line.appendChild(levelSpan);
      line.appendChild(tsSpan);
      line.appendChild(msgSpan);
      liveLogsContainer.appendChild(line);
    });
    updateLogFooterOnly();
  }

  function updateLogFooterOnly() {
    var total = logEntries.length;
    var totalPages = Math.max(1, Math.ceil(total / LOG_PAGE_SIZE) || 1);
    if (logPage > totalPages) logPage = totalPages;
    var summary = document.getElementById('logFooterSummary');
    var cur = document.getElementById('logPageCurrent');
    var newer = document.getElementById('logPageNewer');
    var older = document.getElementById('logPageOlder');
    var wrap = document.getElementById('logPaginationWrap');
    var startIdx = (logPage - 1) * LOG_PAGE_SIZE;
    var showing = Math.min(LOG_PAGE_SIZE, Math.max(0, total - startIdx));
    if (summary) {
      if (logSearchQuery) summary.textContent = 'Filtering visible page (' + showing + ' line' + (showing === 1 ? '' : 's') + ' shown)';
      else if (total === 0) summary.textContent = '0 lines';
      else if (logPage === 1) summary.textContent = 'Showing latest ' + showing + ' of ' + total + ' line' + (total === 1 ? '' : 's');
      else summary.textContent = 'Showing lines ' + (startIdx + 1) + '–' + (startIdx + showing) + ' of ' + total;
    }
    if (cur) cur.textContent = 'Page ' + logPage + ' / ' + totalPages;
    if (newer) newer.disabled = logPage <= 1;
    if (older) older.disabled = logPage >= totalPages || totalPages <= 1;
    if (wrap) wrap.style.display = total > LOG_PAGE_SIZE ? '' : 'none';
  }

  function setStructuredLogs(entries) {
    if (!Array.isArray(entries) || !entries.length) {
      logEntries = [];
      processedLogHashes.clear();
      logPage = 1;
      renderLogPage();
      return;
    }
    var list = entries.map(function (e) {
      return { level: (e.level || 'info').toLowerCase(), ts: e.time || e.ts || '', message: e.msg || e.message || '' };
    });
    list.reverse();
    logEntries = [];
    processedLogHashes.clear();
    list.forEach(function (entry) {
      var key = logEntryKey(entry);
      if (processedLogHashes.has(key)) return;
      processedLogHashes.add(key);
      logEntries.push(entry);
    });
    trimLogStore();
    logPage = 1;
    renderLogPage();
  }

  function setFormattedLogs(text) {
    var lines = (text || '').split(/\r?\n/).filter(Boolean);
    logEntries = lines.slice().reverse().map(function (line) {
      return { level: 'info', ts: '', message: line };
    });
    processedLogHashes = new Set(logEntries.map(logEntryKey));
    logPage = 1;
    renderLogPage();
  }

  function updateEventLogs() {
    if (isPaused) return;
    var params = { limit: '500', ts: String(Date.now()) };
    if (lastEventId > 0) params.since_id = String(lastEventId);
    get('live_events', params).then(function (d) {
      if (!d || d.status !== 'success' || !Array.isArray(d.events) || !d.events.length) return;
      var newEvents = [];
      d.events.forEach(function (ev) {
        if (terminalEventSeen) return;
        newEvents.push(ev);
        var evId = typeof ev.id === 'number' ? ev.id : parseInt(ev.id, 10);
        if (!isNaN(evId) && evId > lastEventId) lastEventId = evId;
        var evType = (ev.type || '').toLowerCase();
        if (['cancelled', 'summary'].indexOf(evType) >= 0 || /backup cancelled/i.test(ev.message || '')) {
          terminalEventSeen = true;
        }
      });
      newEvents.sort(function (a, b) {
        var ida = typeof a.id === 'number' ? a.id : (parseInt(a.id, 10) || 0);
        var idb = typeof b.id === 'number' ? b.id : (parseInt(b.id, 10) || 0);
        return ida - idb;
      });
      newEvents.forEach(function (ev) {
        appendLogEntry({ id: ev.id || null, level: ev.level || 'info', ts: ev.ts || '', message: ev.message || '' });
      });
    }).catch(function () {});
  }

  function updateFormattedLogs() {
    var params = { ts: String(Date.now()) };
    if (lastLogsHash) params.hash = lastLogsHash;
    get('live_logs', params).then(function (d) {
      if (!d || d.status !== 'success' || d.unchanged) return;
      if (Array.isArray(d.entries) && d.entries.length > 0) {
        setStructuredLogs(d.entries);
        lastLogsHash = d.hash || lastLogsHash;
      } else if (typeof d.formatted_log !== 'undefined') {
        setFormattedLogs(d.formatted_log || '');
        lastLogsHash = d.hash || lastLogsHash;
      }
    }).catch(function () {});
  }

  function clearLogs() {
    logEntries = [];
    pausedLogBuffer = [];
    processedLogHashes.clear();
    logPage = 1;
    lastLogsHash = null;
    lastEventId = 0;
    terminalEventSeen = false;
    renderLogPage();
  }

  function flushPausedLogBuffer() {
    if (!pausedLogBuffer.length) return;
    for (var i = pausedLogBuffer.length - 1; i >= 0; i--) logEntries.unshift(pausedLogBuffer[i]);
    pausedLogBuffer = [];
    trimLogStore();
    if (logPage === 1) renderLogPage();
    else updateLogFooterOnly();
  }

  function togglePauseUpdates() {
    isPaused = !isPaused;
    var btn = document.getElementById('pauseUpdatesBtn');
    var ind = document.getElementById('logPauseIndicator');
    if (btn) {
      btn.textContent = isPaused ? 'Resume' : 'Pause';
      btn.classList.toggle('is-active', isPaused);
    }
    if (ind) ind.style.display = isPaused ? '' : 'none';
    if (!isPaused) {
      flushPausedLogBuffer();
      updateProgress();
      updateEventLogs();
    }
  }

  function showModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function hideModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  function setCancelButtonsBusy(busy) {
    var cancelButton = document.getElementById('cancelButton');
    var forceButton = document.getElementById('forceCancelButton');
    if (cancelButton) {
      cancelButton.disabled = busy;
      cancelButton.textContent = busy ? 'Cancelling...' : 'Cancel Run';
    }
    if (forceButton) {
      forceButton.disabled = busy;
      forceButton.textContent = busy ? 'Cancelling...' : 'Force Cancel';
    }
  }

  function setCancelConfirmModalBusy(busy) {
    var submit = document.getElementById('cancelRunConfirmSubmit');
    var dismiss = document.getElementById('cancelRunConfirmDismiss');
    var closeBtn = document.getElementById('cancelRunConfirmClose');
    var warning = document.getElementById('cancelRunConfirmWarning');
    var progress = document.getElementById('cancelRunConfirmProgress');
    if (submit) {
      submit.disabled = busy;
      submit.textContent = busy ? 'Cancelling...' : (pendingCancelForce ? 'Force Cancel' : 'Confirm Cancel');
    }
    if (dismiss) dismiss.disabled = busy;
    if (closeBtn) closeBtn.disabled = busy;
    if (warning) warning.classList.toggle('hidden', busy);
    if (progress) progress.classList.toggle('hidden', !busy);
  }

  function openCancelConfirmModal(forceCancel) {
    if (!runId || cancelRequestInFlight) return;
    pendingCancelForce = !!forceCancel;
    var title = document.getElementById('cancelRunConfirmTitle');
    var message = document.getElementById('cancelRunConfirmMessage');
    var detail = document.getElementById('cancelRunConfirmDetail');
    var submit = document.getElementById('cancelRunConfirmSubmit');
    if (!title || !message || !detail || !submit) return;
    if (pendingCancelForce) {
      title.textContent = 'Force cancel run?';
      message.textContent = 'This will mark the Microsoft 365 backup cancelled immediately.';
      detail.textContent = 'Use force cancel only when workloads are stuck and a normal cancel is not clearing them.';
      submit.textContent = 'Force Cancel';
    } else {
      title.textContent = 'Cancel run?';
      message.textContent = 'This will stop the active Microsoft 365 backup workloads.';
      detail.textContent = 'Running workloads will be cancelled and workers stopped.';
      submit.textContent = 'Confirm Cancel';
    }
    setCancelConfirmModalBusy(false);
    showModal('cancelRunConfirmModal');
  }

  function closeCancelConfirmModal(forceClose) {
    if (cancelRequestInFlight && !forceClose) return;
    hideModal('cancelRunConfirmModal');
    pendingCancelForce = false;
    setCancelConfirmModalBusy(false);
  }

  function submitCancelRun() {
    if (!runId || cancelRequestInFlight) return;
    cancelRequestInFlight = true;
    setCancelButtonsBusy(true);
    setCancelConfirmModalBusy(true);
    post('live_cancel', { force: pendingCancelForce ? '1' : '0' }).then(function (data) {
      if (!data || data.status !== 'success') {
        throw new Error((data && data.message) ? data.message : 'Cancel request failed');
      }
      closeCancelConfirmModal(true);
      updateProgress();
      updateEventLogs();
      showModal('cancelRunStatusModal');
      var title = document.getElementById('cancelRunStatusTitle');
      var subtitle = document.getElementById('cancelRunStatusSubtitle');
      var message = document.getElementById('cancelRunStatusMessage');
      if (pendingCancelForce) {
        if (title) title.textContent = 'Force cancel submitted';
        if (subtitle) subtitle.textContent = 'The run will refresh shortly.';
        if (message) message.textContent = 'The Microsoft 365 backup was marked for immediate cancellation.';
      } else {
        if (title) title.textContent = 'Cancel request submitted';
        if (subtitle) subtitle.textContent = 'Stopping workloads.';
        if (message) message.textContent = 'Microsoft 365 backup workloads are being cancelled.';
      }
    }).catch(function (error) {
      setCancelConfirmModalBusy(false);
      var warning = document.getElementById('cancelRunConfirmWarning');
      var detail = document.getElementById('cancelRunConfirmDetail');
      if (warning && detail) {
        warning.classList.remove('hidden', 'eb-alert--warning');
        warning.classList.add('eb-alert--danger');
        detail.textContent = 'Failed to cancel run: ' + (error && error.message ? error.message : 'Unknown error');
      }
    }).finally(function () {
      cancelRequestInFlight = false;
      setCancelButtonsBusy(false);
      var warning = document.getElementById('cancelRunConfirmWarning');
      if (warning) {
        warning.classList.remove('eb-alert--danger');
        warning.classList.add('eb-alert--warning');
      }
    });
  }

  function copyLogs() {
    var liveLogsContainer = document.getElementById('liveLogs');
    if (!liveLogsContainer) return;
    var text = Array.prototype.map.call(liveLogsContainer.querySelectorAll('.eb-log-line'), function (row) {
      var lvl = row.querySelector('.eb-log-level');
      var ts = row.querySelector('.eb-log-timestamp');
      var msg = row.querySelector('.eb-log-message');
      return [lvl && lvl.textContent, ts && ts.textContent, msg && msg.textContent].filter(Boolean).join(' ').trim();
    }).join('\n').trim();
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { showModal('copyLogsModal'); });
    }
  }

  function bindEvents() {
    var search = document.getElementById('logSearchInput');
    if (search) {
      search.addEventListener('input', function () {
        logSearchQuery = (search.value || '').toLowerCase().trim();
        renderLogPage();
      });
    }
    var pauseBtn = document.getElementById('pauseUpdatesBtn');
    if (pauseBtn) pauseBtn.addEventListener('click', togglePauseUpdates);
    var copyBtn = document.getElementById('copyLogsBtn');
    if (copyBtn) copyBtn.addEventListener('click', copyLogs);
    var clearBtn = document.getElementById('clearLogsBtn');
    if (clearBtn) clearBtn.addEventListener('click', clearLogs);
    var cancelBtn = document.getElementById('cancelButton');
    if (cancelBtn) cancelBtn.addEventListener('click', function () { openCancelConfirmModal(false); });
    var forceBtn = document.getElementById('forceCancelButton');
    if (forceBtn) forceBtn.addEventListener('click', function () { openCancelConfirmModal(true); });
    var submitBtn = document.getElementById('cancelRunConfirmSubmit');
    if (submitBtn) submitBtn.addEventListener('click', submitCancelRun);
    var newerBtn = document.getElementById('logPageNewer');
    if (newerBtn) newerBtn.addEventListener('click', function () {
      if (logPage > 1) { logPage--; renderLogPage(); }
    });
    var olderBtn = document.getElementById('logPageOlder');
    if (olderBtn) olderBtn.addEventListener('click', function () {
      var totalPages = Math.max(1, Math.ceil(logEntries.length / LOG_PAGE_SIZE) || 1);
      if (logPage < totalPages) { logPage++; renderLogPage(); }
    });
    document.querySelectorAll('[data-dismiss="cancel-modal"]').forEach(function (el) {
      el.addEventListener('click', function () { closeCancelConfirmModal(); });
    });
    document.querySelectorAll('[data-dismiss="cancel-status-modal"]').forEach(function (el) {
      el.addEventListener('click', function () { hideModal('cancelRunStatusModal'); });
    });
    document.querySelectorAll('[data-dismiss="copy-modal"]').forEach(function (el) {
      el.addEventListener('click', function () { hideModal('copyLogsModal'); });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      var confirmModal = document.getElementById('cancelRunConfirmModal');
      if (confirmModal && !confirmModal.classList.contains('hidden')) {
        closeCancelConfirmModal();
        return;
      }
      var statusModal = document.getElementById('cancelRunStatusModal');
      if (statusModal && !statusModal.classList.contains('hidden')) hideModal('cancelRunStatusModal');
    });
  }

  function init() {
    if (!runId || !apiBase) return;
    bindEvents();

    var label = document.getElementById('progressPercentValue');
    if (label) currentPct = parseFloat(label.textContent) || 0;

    var cfgStatus = STATUS_CONFIGS[initialStatus];
    if (cfgStatus) updateStatusDisplay(cfgStatus);

    if (Array.isArray(cfg.initialWorkloads)) renderMs365Workloads(cfg.initialWorkloads);

    clearLogs();
    syncLogPanelChrome(isRunning);
    syncMs365WorkloadsChrome(isRunning);
    setCurrentFileRowVisible(isRunning);

    updateProgress();
    if (isRunning) {
      updateEventLogs();
      progressInterval = setInterval(updateProgress, 2000);
      eventsInterval = setInterval(updateEventLogs, 2000);
    } else {
      updateFormattedLogs();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
