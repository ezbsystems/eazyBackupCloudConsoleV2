/**
 * e3backup_run_ticket.js
 *
 * Run-scoped "Open Support Ticket" handoff for the e3 Cloud Backup run-log
 * modal (e3backup_run_log_modal.tpl). Mirrors the eazybackup job-ticket.js
 * flow but keyed on a run UUID:
 *
 *   - shows the ticket button only for warning/failed/cancelled/
 *     partial_success/running runs
 *   - fetches subject/body/KB hints + duplicate check from
 *     api/e3backup_run_ticket_context.php
 *   - builds a .txt log file from the structured log rows the modal loaded
 *   - stashes the payload in sessionStorage['eb_ticket_<runId>'] and
 *     redirects to submitticket.php?step=2&eb_job=<runId>, where the theme's
 *     ticket-prefill.js drains it (reused as-is; only the custom field differs)
 */
(function () {
    'use strict';

    var ELIGIBLE = ['warning', 'failed', 'cancelled', 'partial_success', 'running'];
    var SS_PREFIX = 'eb_ticket_';
    var SS_MAX_BYTES = 3 * 1024 * 1024;
    var FILE_MAX_BYTES = 1.5 * 1024 * 1024;
    var CTX_ENDPOINT = 'modules/addons/cloudstorage/api/e3backup_run_ticket_context.php';

    var WEB_ROOT = (window.EB_WEB_ROOT || (function () {
        try { var a = document.createElement('a'); a.href = '/'; return a.origin; } catch (_) { return ''; }
    })());

    var current = { runId: '', status: '', meta: {}, rows: null };
    var ctxCache = null;

    function el(id) { return document.getElementById(id); }
    function show(n) { if (n) n.classList.remove('hidden'); }
    function hide(n) { if (n) n.classList.add('hidden'); }
    function setText(id, t) { var n = el(id); if (n) n.textContent = t || ''; }
    function isEligible(status) { return ELIGIBLE.indexOf(String(status || '').toLowerCase()) !== -1; }

    function engineLabel(e) {
        switch (String(e || '').toLowerCase()) {
            case 'kopia':
            case 'sync': return 'File/Folder';
            case 'disk_image': return 'Disk Image';
            case 'hyperv': return 'Hyper-V';
            default:
                if (!e) return 'File/Folder';
                return String(e).charAt(0).toUpperCase() + String(e).slice(1);
        }
    }

    function api(action) {
        return fetch(CTX_ENDPOINT + '?run_uuid=' + encodeURIComponent(current.runId) + '&action=' + encodeURIComponent(action), {
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    function safeId(s) {
        return String(s || 'run').replace(/[^a-zA-Z0-9._-]+/g, '_').replace(/^_|_$/g, '').slice(0, 80) || 'run';
    }

    function utf8ToBase64(str) {
        try {
            var bytes = new TextEncoder().encode(str);
            var bin = '', chunk = 0x8000;
            for (var i = 0; i < bytes.length; i += chunk) {
                bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
            }
            return btoa(bin);
        } catch (_) {
            return btoa(unescape(encodeURIComponent(str)));
        }
    }

    function buildLogTxt(rows, meta) {
        var lines = [];
        lines.push('e3 Cloud Backup Run Log');
        lines.push('Run ID:   ' + (current.runId || ''));
        lines.push('Job:      ' + (meta.jobName || ''));
        lines.push('Agent:    ' + (meta.agent || ''));
        lines.push('Engine:   ' + engineLabel(meta.engine || meta.engineInternal || ''));
        lines.push('Status:   ' + (meta.status || ''));
        lines.push('Started:  ' + (meta.started || ''));
        lines.push('Finished: ' + (meta.finished || ''));
        lines.push('---');
        var body = (rows || []).map(function (e) {
            var ts = e.ts ? '[' + e.ts + '] ' : '';
            var lvl = e.level ? '(' + String(e.level).toUpperCase() + ') ' : '';
            return ts + lvl + String(e.message || '');
        });
        var text = lines.join('\n') + '\n' + body.join('\n') + '\n';
        if (text.length > FILE_MAX_BYTES) {
            text = text.slice(0, FILE_MAX_BYTES - 200) + '\n... (further entries omitted) ...\n';
        }
        return text;
    }

    function downloadFile(filename, text, mime) {
        try {
            var blob = new Blob([text], { type: mime || 'text/plain;charset=utf-8' });
            var u = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = u; a.download = filename; a.rel = 'noopener';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(u);
        } catch (_) {}
    }

    function renderKbHints(hints) {
        var wrap = el('ebE3RunTicketKb');
        var list = el('ebE3RunTicketKbList');
        if (!wrap || !list) return;
        list.innerHTML = '';
        if (!Array.isArray(hints) || hints.length === 0) { hide(wrap); return; }
        hints.slice(0, 3).forEach(function (h) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = h.url; a.target = '_blank'; a.rel = 'noopener';
            a.className = 'eb-link text-sm';
            a.textContent = h.title || h.url;
            li.appendChild(a);
            list.appendChild(li);
        });
        show(wrap);
    }

    function renderDuplicate(ticket) {
        var box = el('ebE3RunTicketDupe');
        if (!box) return;
        if (!ticket || !ticket.id) { hide(box); return; }
        var txt = box.querySelector('[data-dupe-text]');
        var lnk = box.querySelector('[data-dupe-link]');
        if (txt) txt.textContent = 'We already have an open ticket about this run (' + (ticket.tid ? '#' + ticket.tid : 'ticket #' + ticket.id) + ').';
        if (lnk) lnk.href = (WEB_ROOT || '') + '/viewticket.php?tid=' + encodeURIComponent(ticket.tid || '');
        show(box);
    }

    function showError(msg) {
        var err = el('ebE3RunTicketError');
        if (err) { err.textContent = msg || 'Something went wrong, please try again.'; show(err); }
    }

    function clearPanel() {
        hide(el('ebE3RunTicketPanel'));
        var err = el('ebE3RunTicketError');
        if (err) { err.textContent = ''; hide(err); }
        if (isEligible(current.status)) show(el('ebE3RunTicketBtn'));
    }

    function evaluateButton() {
        var btn = el('ebE3RunTicketBtn');
        if (!btn) return;
        if (isEligible(current.status)) { show(btn); } else { hide(btn); clearPanel(); }
    }

    function onOpenClick() {
        var btn = el('ebE3RunTicketBtn');
        if (!btn) return;
        btn.disabled = true;
        Promise.all([api('context'), api('duplicate')]).then(function (out) {
            var ctx = out[0];
            if (!ctx || ctx.status !== 'success') {
                throw new Error((ctx && ctx.message) || 'Could not prepare ticket');
            }
            ctxCache = { runId: current.runId, data: ctx };
            setText('ebE3RunTicketSubject', ctx.subject || '');
            renderKbHints(ctx.kbHints || []);
            renderDuplicate(out[1] && out[1].status === 'success' ? out[1].ticket : null);
            var err = el('ebE3RunTicketError');
            if (err) { err.textContent = ''; hide(err); }
            show(el('ebE3RunTicketPanel'));
            hide(btn);
        }).catch(function (e) {
            showError(e && e.message ? e.message : 'Could not prepare ticket. Please try again.');
            show(el('ebE3RunTicketPanel'));
        }).finally(function () {
            btn.disabled = false;
        });
    }

    function onContinueClick() {
        var btn = el('ebE3RunTicketContinue');
        if (btn) btn.disabled = true;
        try {
            if (!ctxCache || ctxCache.runId !== current.runId) {
                throw new Error('Ticket context not loaded');
            }
            var ctx = ctxCache.data;
            var meta = ctx.runMeta || current.meta || {};
            var rows = current.rows || [];
            var runId = current.runId;
            var sid = safeId(runId);

            // Run log is attached server-side on TicketOpen (see eb_e3_run_ticket_attachment.php).
            // Browser DataTransfer prefill often creates broken attachment rows (dl.php 500).
            var payload = {
                runId: runId,
                deptId: ctx.deptId || 1,
                priority: ctx.priority || 'Medium',
                subject: ctx.subject || '',
                body: ctx.bodyMarkdown || '',
                customFieldId: ctx.customFieldId || 0,
                files: [],
                serverAttach: true,
                ts: Date.now()
            };

            try {
                sessionStorage.setItem(SS_PREFIX + runId, JSON.stringify(payload));
            } catch (e) {
                var txt = buildLogTxt(rows, meta);
                downloadFile('run-' + sid + '.txt', txt, 'text/plain');
                payload.downloadedFallback = true;
                try { sessionStorage.setItem(SS_PREFIX + runId, JSON.stringify(payload)); } catch (_) {}
            }

            var url = (WEB_ROOT || '') + '/submitticket.php?step=2'
                + '&deptid=' + encodeURIComponent(ctx.deptId || 1)
                + '&subject=' + encodeURIComponent(ctx.subject || '')
                + '&eb_job=' + encodeURIComponent(runId);
            window.location.href = url;
        } catch (err) {
            if (btn) btn.disabled = false;
            showError(err && err.message ? err.message : 'Could not start the ticket flow.');
        }
    }

    function wire() {
        var btn = el('ebE3RunTicketBtn');
        if (btn && !btn._ebWired) { btn._ebWired = true; btn.addEventListener('click', onOpenClick); }
        var cont = el('ebE3RunTicketContinue');
        if (cont && !cont._ebWired) { cont._ebWired = true; cont.addEventListener('click', onContinueClick); }
        var cancel = el('ebE3RunTicketCancel');
        if (cancel && !cancel._ebWired) { cancel._ebWired = true; cancel.addEventListener('click', clearPanel); }
    }

    document.addEventListener('eb:e3-run-loaded', function (ev) {
        var d = (ev && ev.detail) || {};
        current = { runId: d.runId || '', status: (d.meta && d.meta.status) || d.status || '', meta: d.meta || {}, rows: null };
        ctxCache = null;
        clearPanel();
        wire();
        evaluateButton();
    });

    document.addEventListener('eb:e3-run-logs-loaded', function (ev) {
        var d = (ev && ev.detail) || {};
        if (d.runId && d.runId === current.runId) {
            current.rows = Array.isArray(d.rows) ? d.rows : null;
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire, { once: true });
    } else {
        wire();
    }
})();
