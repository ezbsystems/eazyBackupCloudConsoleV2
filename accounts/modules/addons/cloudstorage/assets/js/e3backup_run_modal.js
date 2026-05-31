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

    // ---- Status helper -------------------------------------------------
    // Maps an e3 run status to an eb-* badge modifier + dot modifier + label.
    var STATUS_MAP = {
        success:          { badge: 'eb-badge--success', dot: 'eb-status-dot--active',   label: 'Success' },
        warning:          { badge: 'eb-badge--warning', dot: 'eb-status-dot--warning',  label: 'Warning' },
        partial_success:  { badge: 'eb-badge--warning', dot: 'eb-status-dot--warning',  label: 'Partial Success' },
        failed:           { badge: 'eb-badge--danger',  dot: 'eb-status-dot--error',    label: 'Failed' },
        cancelled:        { badge: 'eb-badge--warning', dot: 'eb-status-dot--inactive', label: 'Cancelled' },
        running:          { badge: 'eb-badge--info',    dot: 'eb-status-dot--pending',  label: 'Running' },
        starting:         { badge: 'eb-badge--info',    dot: 'eb-status-dot--pending',  label: 'Starting' },
        queued:           { badge: 'eb-badge--neutral', dot: 'eb-status-dot--inactive', label: 'Queued' }
    };

    window.ebE3RunStatus = {
        info: function (status) {
            var key = String(status || '').toLowerCase();
            return STATUS_MAP[key] || { badge: 'eb-badge--neutral', dot: 'eb-status-dot--pending', label: (status || 'Unknown') };
        },
        label: function (status) { return this.info(status).label; },
        badgeClass: function (status) { return this.info(status).badge; },
        dotClass: function (status) { return this.info(status).dot; }
    };

    // ---- DOM helpers ---------------------------------------------------
    function el(id) { return document.getElementById(id); }
    function show(node) { if (node) node.classList.remove('hidden'); }
    function hide(node) { if (node) node.classList.add('hidden'); }
    function setText(id, text) { var n = el(id); if (n) n.textContent = (text == null ? '' : String(text)); }

    var state = {
        runId: '',
        meta: {},
        rows: [],          // structured log rows
        severity: 'all'    // all | warning | error
    };

    function levelRank(level) {
        var l = String(level || '').toLowerCase();
        if (l === 'error' || l === 'e' || l === 'fatal' || l === 'critical') return 3;
        if (l === 'warning' || l === 'warn' || l === 'w') return 2;
        return 1;
    }

    function passesSeverity(row) {
        if (state.severity === 'all') return true;
        var rank = levelRank(row.level);
        if (state.severity === 'error') return rank >= 3;
        if (state.severity === 'warning') return rank >= 2;
        return true;
    }

    function renderLogs() {
        var body = el('ebE3RunLogBody');
        if (!body) return;
        var filtered = (state.rows || []).filter(passesSeverity);
        if (!filtered.length) {
            body.textContent = state.rows.length
                ? 'No log entries match the selected severity.'
                : 'No log data available for this run.';
            return;
        }
        var lines = filtered.map(function (row) {
            var ts = row.ts ? '[' + row.ts + '] ' : '';
            var lvl = row.level ? '(' + String(row.level).toUpperCase() + ') ' : '';
            return ts + lvl + (row.message || '');
        });
        body.textContent = lines.join('\n');
    }

    function renderSummary() {
        var m = state.meta || {};
        setText('ebE3RunSummaryJob', m.jobName || 'Backup run');
        setText('ebE3RunSummaryAgent', m.agent || '-');
        setText('ebE3RunSummaryUser', m.user || '-');
        setText('ebE3RunSummaryEngine', (m.engine || 'File/Folder'));
        setText('ebE3RunSummaryStarted', m.started || '-');
        setText('ebE3RunSummaryFinished', m.finished || '-');
        setText('ebE3RunSummaryDuration', m.durationText || '-');
        setText('ebE3RunSummarySize', m.sizeText || '-');

        var badge = el('ebE3RunSummaryStatus');
        if (badge) {
            var info = window.ebE3RunStatus.info(m.status);
            badge.className = 'eb-badge ' + info.badge;
            badge.textContent = info.label;
        }
    }

    function setSeverity(sev) {
        state.severity = sev;
        ['all', 'warning', 'error'].forEach(function (s) {
            var btn = el('ebE3RunSev-' + s);
            if (btn) {
                btn.classList.toggle('is-active', s === sev);
                btn.setAttribute('aria-pressed', s === sev ? 'true' : 'false');
            }
        });
        renderLogs();
    }

    function loadLogs() {
        var body = el('ebE3RunLogBody');
        if (body) body.textContent = 'Loading log details...';
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
                    if (body) body.textContent = 'Error: ' + ((data && data.message) || 'Failed to load log details');
                }
            })
            .catch(function () {
                if (body) body.textContent = 'Error: Unable to load log details. Please try again later.';
            });
    }

    function open(runId, meta) {
        var modal = el('ebE3RunLogModal');
        if (!modal || !runId) return;
        state.runId = String(runId);
        state.meta = meta || {};
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
        ['all', 'warning', 'error'].forEach(function (s) {
            var btn = el('ebE3RunSev-' + s);
            if (btn) btn.addEventListener('click', function () { setSeverity(s); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });
    }

    window.ebE3RunModal = { open: open, close: close };
    // Brace-free wrapper for inline onclick="" in Smarty templates.
    window.ebE3OpenRunLog = function (runId) { open(runId, {}); };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire, { once: true });
    } else {
        wire();
    }
})();
