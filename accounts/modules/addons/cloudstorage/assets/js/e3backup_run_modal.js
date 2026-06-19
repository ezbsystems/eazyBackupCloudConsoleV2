/**
 * e3backup_run_modal.js
 *
 * Shared run-log modal for the e3 Cloud Backup product. Used by the
 * Dashboard "Recent Backup History" grid and the Job Logs page.
 *
 * Public API (window.ebE3RunModal):
 *   open(runId, meta)  - open the modal for a run, optionally seeding the
 *                        summary header from a `meta` object the caller
 *                        already has (job name, agent, status, times...).
 *   close()            - hide the modal.
 *   setSeverity(sev)   - filter log lines (all | warning | error).
 *
 * Also exposes a tiny status helper (window.ebE3RunStatus) used by the
 * grid + table so badge/dot styling is consistent everywhere. cloudstorage
 * has no global EB.statusDot equivalent, so we provide a local one.
 *
 * Fetches sanitized logs from api/cloudbackup_get_run_logs.php and fires:
 *   eb:e3-run-loaded       detail: { runId, meta }
 *   eb:e3-run-logs-loaded  detail: { runId, rows }
 * so the ticket helper (e3backup_run_ticket.js) can react.
 */
(function () {
    'use strict';

    var LOG_ENDPOINT = 'modules/addons/cloudstorage/api/cloudbackup_get_run_logs.php';

    function engineDisplayLabel(e) {
        switch (String(e || '').toLowerCase()) {
            case 'kopia':
            case 'sync': return 'File/Folder';
            case 'disk_image': return 'Disk Image';
            case 'hyperv': return 'Hyper-V';
            case 'ms365': return 'Microsoft 365';
            default:
                if (!e) return 'File/Folder';
                return String(e).charAt(0).toUpperCase() + String(e).slice(1);
        }
    }

    // ---- Status helper -------------------------------------------------
    var STATUS_MAP = {
        success:          { badge: 'eb-badge--success', dot: 'eb-status-dot--active',   label: 'Success' },
        schedule_skipped: { badge: 'eb-badge--neutral', dot: 'eb-status-dot--inactive', label: 'Skipped' },
        warning:          { badge: 'eb-badge--warning', dot: 'eb-status-dot--warning',  label: 'Warning' },
        partial_success:  { badge: 'eb-badge--warning', dot: 'eb-status-dot--warning',  label: 'Partial Success' },
        failed:           { badge: 'eb-badge--danger',  dot: 'eb-status-dot--error',    label: 'Failed' },
        cancelled:        { badge: 'eb-badge--warning', dot: 'eb-status-dot--inactive', label: 'Cancelled' },
        running:          { badge: 'eb-badge--info',    dot: 'eb-status-dot--pending',  label: 'Running' },
        starting:         { badge: 'eb-badge--info',    dot: 'eb-status-dot--pending',  label: 'Starting' },
        queued:           { badge: 'eb-badge--neutral', dot: 'eb-status-dot--inactive', label: 'Queued' }
    };

    window.ebE3RunStatus = {
        info: function (status, meta) {
            if (meta && meta.schedule_skipped) {
                return STATUS_MAP.schedule_skipped;
            }
            var key = String(status || '').toLowerCase();
            return STATUS_MAP[key] || { badge: 'eb-badge--neutral', dot: 'eb-status-dot--pending', label: (status || 'Unknown') };
        },
        label: function (status, meta) { return this.info(status, meta).label; },
        badgeClass: function (status, meta) { return this.info(status, meta).badge; },
        dotClass: function (status, meta) { return this.info(status, meta).dot; }
    };

    function el(id) { return document.getElementById(id); }
    function show(node) { if (node) node.classList.remove('hidden'); }
    function hide(node) { if (node) node.classList.add('hidden'); }
    function setText(id, text) { var n = el(id); if (n) n.textContent = (text == null ? '' : String(text)); }

    var state = {
        runId: '',
        meta: {},
        rows: [],
        severity: 'all',
        searchQuery: ''
    };

    function levelRank(level) {
        var l = String(level || '').toLowerCase();
        if (l === 'error' || l === 'e' || l === 'fatal' || l === 'critical') return 3;
        if (l === 'warning' || l === 'warn' || l === 'w') return 2;
        return 1;
    }

    function severityKey(level) {
        var l = String(level || '').toLowerCase();
        if (l === 'error' || l === 'e' || l === 'fatal' || l === 'critical') return 'error';
        if (l === 'warning' || l === 'warn' || l === 'w') return 'warning';
        if (l === 'info' || l === 'i') return 'info';
        return 'debug';
    }

    function levelLabel(level) {
        var l = String(level || '').toUpperCase();
        if (l === 'I' || l === 'INFO') return 'Information';
        if (l === 'W' || l === 'WARN' || l === 'WARNING') return 'Warning';
        if (l === 'E' || l === 'ERROR' || l === 'FATAL' || l === 'CRITICAL') return 'Error';
        return level || '';
    }

    function levelClass(level) {
        var key = severityKey(level);
        if (key === 'error') return 'error';
        if (key === 'warning') return 'warn';
        if (key === 'info') return 'info';
        return 'debug';
    }

    function passesSeverity(row) {
        if (state.severity === 'all') return true;
        var rank = levelRank(row.level);
        if (state.severity === 'error') return rank >= 3;
        if (state.severity === 'warning') return rank >= 2;
        return true;
    }

    function passesSearch(row) {
        var q = (state.searchQuery || '').trim().toLowerCase();
        if (!q) return true;
        var hay = ((row.message || '') + ' ' + (row.ts || '') + ' ' + (row.level || '')).toLowerCase();
        return hay.indexOf(q) !== -1;
    }

    function renderEmptyMessage(body, message) {
        body.innerHTML = '';
        var row = document.createElement('div');
        row.className = 'eb-log-line px-3 py-2';
        var m = document.createElement('span');
        m.className = 'eb-log-message text-sm text-[var(--eb-text-secondary)]';
        m.textContent = message;
        row.appendChild(m);
        body.appendChild(row);
    }

    function renderLogs() {
        var body = el('ebE3RunLogBody');
        if (!body) return;
        var filtered = (state.rows || []).filter(function (row) {
            return passesSeverity(row) && passesSearch(row);
        });
        body.innerHTML = '';
        if (!filtered.length) {
            var msg = state.rows.length
                ? 'No log entries match the selected filters.'
                : 'No log data available for this run.';
            renderEmptyMessage(body, msg);
            return;
        }
        filtered.forEach(function (row, idx) {
            var line = document.createElement('div');
            line.className = 'eb-log-line px-3 py-2' + (idx === 0 ? ' is-newest' : '');
            var t = document.createElement('span');
            t.className = 'eb-log-timestamp text-xs shrink-0';
            t.textContent = row.ts ? (String(row.ts).indexOf('[') === 0 ? row.ts : '[' + row.ts + ']') : '';
            var s = document.createElement('span');
            s.className = 'eb-log-level';
            var cls = levelClass(row.level);
            s.classList.add(cls);
            s.textContent = levelLabel(row.level) || cls;
            var m = document.createElement('span');
            m.className = 'eb-log-message text-sm min-w-0 flex-1';
            m.textContent = row.message || '';
            line.dataset.sev = severityKey(row.level);
            line.appendChild(t);
            line.appendChild(s);
            line.appendChild(m);
            body.appendChild(line);
        });
    }

    function renderTransferSummary(summary) {
        var uploadedEl = el('ebE3RunSummaryUploaded');
        var downloadedEl = el('ebE3RunSummaryDownloaded');
        if (!summary) {
            setText('ebE3RunSummaryUploaded', '—');
            setText('ebE3RunSummaryDownloaded', '—');
            return;
        }
        if (uploadedEl) {
            uploadedEl.textContent = summary.uploaded_formatted || '—';
            uploadedEl.classList.toggle('!text-[var(--eb-text-muted)]', summary.uploaded_formatted === '—');
        }
        if (downloadedEl) {
            downloadedEl.textContent = summary.downloaded_formatted || '—';
            downloadedEl.classList.toggle('!text-[var(--eb-text-muted)]', summary.downloaded_formatted === '—');
        }
    }

    function renderSummary() {
        var m = state.meta || {};
        setText('ebE3RunSummaryJob', m.jobName || 'Backup run');
        setText('ebE3RunSummaryAgent', m.agent || '-');
        setText('ebE3RunSummaryUser', m.user || '-');
        setText('ebE3RunSummaryEngine', engineDisplayLabel(m.engine));
        setText('ebE3RunSummaryStarted', m.started || '-');
        setText('ebE3RunSummaryFinished', m.finished || '-');
        setText('ebE3RunSummaryDuration', m.durationText || '-');
        setText('ebE3RunSummarySize', m.sizeText || '-');
        setText('ebE3RunSummaryUploaded', '—');
        setText('ebE3RunSummaryDownloaded', '—');

        var badge = el('ebE3RunSummaryStatus');
        if (badge) {
            var info = window.ebE3RunStatus.info(m.status);
            badge.className = 'eb-badge ' + info.badge;
            badge.textContent = info.label;
        }
    }

    function setSeverity(sev) {
        state.severity = sev || 'all';
        renderLogs();
    }

    function setSearchQuery(q) {
        state.searchQuery = q || '';
        renderLogs();
    }

    function loadLogs() {
        var body = el('ebE3RunLogBody');
        if (body) renderEmptyMessage(body, 'Loading log details...');
        state.rows = [];
        fetch(LOG_ENDPOINT + '?run_uuid=' + encodeURIComponent(state.runId) + '&limit=5000', {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.status === 'success') {
                    var structured = Array.isArray(data.structured_logs) ? data.structured_logs : [];
                    if (!structured.length && data.backup_log) {
                        structured = String(data.backup_log).split('\n').map(function (line) {
                            return { ts: '', level: 'info', message: line };
                        });
                    }
                    state.rows = structured;
                    renderLogs();
                    renderTransferSummary(data.run_summary || null);

                    var valSection = el('ebE3RunValidation');
                    var valBody = el('ebE3RunValidationBody');
                    if (data.has_validation && data.validation_log && valSection && valBody) {
                        valBody.textContent = data.validation_log;
                        show(valSection);
                    } else {
                        hide(valSection);
                    }

                    try {
                        document.dispatchEvent(new CustomEvent('eb:e3-run-logs-loaded', {
                            detail: { runId: state.runId, rows: state.rows }
                        }));
                    } catch (e) {}
                } else {
                    if (body) renderEmptyMessage(body, 'Error: ' + ((data && data.message) || 'Failed to load log details'));
                }
            })
            .catch(function () {
                if (body) renderEmptyMessage(body, 'Error: Unable to load log details. Please try again later.');
            });
    }

    function open(runId, meta) {
        var modal = el('ebE3RunLogModal');
        if (!modal || !runId) return;
        state.runId = String(runId);
        state.meta = meta || {};
        state.searchQuery = '';
        var searchInput = el('ebE3RunLogSearch');
        if (searchInput) searchInput.value = '';
        renderSummary();
        setSeverity('all');
        hide(el('ebE3RunValidation'));

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('eb-modal-open');

        try {
            document.dispatchEvent(new CustomEvent('eb:e3-run-loaded', {
                detail: { runId: state.runId, meta: state.meta, status: state.meta.status || '' }
            }));
        } catch (e) {}

        loadLogs();
    }

    function close() {
        var modal = el('ebE3RunLogModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('eb-modal-open');
    }

    function wire() {
        var modal = el('ebE3RunLogModal');
        if (!modal || modal._ebWired) return;
        modal._ebWired = true;

        modal.querySelectorAll('[data-e3-run-close]').forEach(function (b) {
            b.addEventListener('click', close);
        });
        var searchInput = el('ebE3RunLogSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                setSearchQuery(searchInput.value);
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });
    }

    window.ebE3RunModal = { open: open, close: close, setSeverity: setSeverity };
    window.ebE3OpenRunLog = function (runId) { open(runId, {}); };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire, { once: true });
    } else {
        wire();
    }
})();
