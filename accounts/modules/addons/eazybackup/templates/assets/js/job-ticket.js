/**
 * job-ticket.js
 *
 * Adds an "Open Support Ticket" button to the Job Report Modal that:
 *   - is only shown for Warning / Error / Missed / Timeout / Cancelled / Running jobs
 *   - composes a polite, pre-filled subject + body via the ticketContext endpoint
 *   - shows up to 3 KB hint links from docs.eazybackup.com (curated + GitBook index)
 *   - warns if a duplicate open ticket already exists for this Job ID (last 7 days)
 *   - stashes log .txt + .csv + meta into sessionStorage and redirects to
 *     submitticket.php?step=2 where ticket-prefill.js drains it.
 */
(function () {
    'use strict';

    var ELIGIBLE = ['Warning', 'Error', 'Missed', 'Timeout', 'Cancelled', 'Running'];
    var SS_PREFIX = 'eb_ticket_';
    var SS_MAX_BYTES = 3 * 1024 * 1024;
    var FILE_MAX_BYTES = 1.5 * 1024 * 1024;

    var endpoint = (window.EB_JOBREPORTS_ENDPOINT
        || (typeof EB_MODULE_LINK !== 'undefined' ? (EB_MODULE_LINK + '&a=job-reports') : 'index.php?m=eazybackup&a=job-reports'));

    var WEB_ROOT = (window.EB_WEB_ROOT || (function () {
        try {
            var a = document.createElement('a');
            a.href = '/';
            return a.origin;
        } catch (_) { return ''; }
    })());

    var current = {
        serviceId: null,
        username: '',
        jobId: '',
        status: '',
        job: null,
        logRows: null,
    };

    var ctxCache = null; // last ticketContext response, keyed by jobId

    function getModal() { return document.getElementById('job-report-modal'); }
    function getButton() { return document.getElementById('jrm-open-ticket'); }
    function getPanel()  { return document.getElementById('jrm-ticket-panel'); }
    function isEligible(status) { return ELIGIBLE.indexOf(String(status || '')) !== -1; }

    function show(el) { if (el) el.classList.remove('hidden'); }
    function hide(el) { if (el) el.classList.add('hidden'); }

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text || '';
    }

    function api(action, payload) {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: action }, payload || {})),
        }).then(function (r) { return r.json(); });
    }

    function severityLabel(sevRaw) {
        var s = String(sevRaw || '').toUpperCase();
        if (s === 'I') return 'INFO';
        if (s === 'W') return 'WARNING';
        if (s === 'E') return 'ERROR';
        return s;
    }

    function fmtTs(unix) {
        if (!unix) return '';
        try {
            var d = new Date(Number(unix) * 1000);
            return d.toISOString().replace('T', ' ').replace(/\.\d+Z$/, ' UTC');
        } catch (_) { return String(unix); }
    }

    function buildLogTxt(rows, meta) {
        var lines = [];
        lines.push('eazyBackup Job Log');
        lines.push('Job ID:         ' + (meta.jobId || ''));
        lines.push('Username:       ' + (meta.username || ''));
        lines.push('Device:         ' + (meta.device || ''));
        lines.push('Protected Item: ' + (meta.item || ''));
        lines.push('Vault:          ' + (meta.vault || ''));
        lines.push('Status:         ' + (meta.status || ''));
        lines.push('Type:           ' + (meta.type || ''));
        lines.push('Started:        ' + fmtTs(meta.started));
        lines.push('Ended:          ' + fmtTs(meta.ended));
        lines.push('---');
        var body = (rows || []).map(function (e) {
            return fmtTs(e.Time) + '  ' + severityLabel(e.Severity).padEnd(7) + '  ' + String(e.Message || '');
        });
        var truncated = false;
        var text = lines.join('\n') + '\n' + body.join('\n') + '\n';
        if (text.length > FILE_MAX_BYTES) {
            // Keep only Warning + Error rows; add header note.
            truncated = true;
            var filtered = (rows || []).filter(function (e) {
                var s = String(e.Severity || '').toUpperCase();
                return s === 'W' || s === 'E';
            }).map(function (e) {
                return fmtTs(e.Time) + '  ' + severityLabel(e.Severity).padEnd(7) + '  ' + String(e.Message || '');
            });
            text = lines.join('\n') + '\n' +
                '(Log truncated; only Warning/Error entries shown. Full log is available in the .csv attachment.)\n' +
                filtered.join('\n') + '\n';
            if (text.length > FILE_MAX_BYTES) {
                text = text.slice(0, FILE_MAX_BYTES - 200) + '\n... (further entries omitted) ...\n';
            }
        }
        return { text: text, truncated: truncated };
    }

    function csvEscape(v) {
        var s = String(v == null ? '' : v);
        if (/[",\r\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
        return s;
    }

    function buildLogCsv(rows) {
        var header = ['UnixTime', 'Timestamp', 'Severity', 'Message'];
        var out = [header.map(csvEscape).join(',')];
        for (var i = 0; i < (rows || []).length; i++) {
            var e = rows[i];
            var unix = Number(e.Time) || 0;
            out.push([
                csvEscape(unix),
                csvEscape(fmtTs(unix)),
                csvEscape(severityLabel(e.Severity)),
                csvEscape(e.Message || ''),
            ].join(','));
        }
        var text = '\ufeff' + out.join('\r\n');
        if (text.length > FILE_MAX_BYTES) {
            text = text.slice(0, FILE_MAX_BYTES - 100) + '\r\n... (truncated) ...\r\n';
        }
        return text;
    }

    function utf8ToBase64(str) {
        try {
            var bytes = new TextEncoder().encode(str);
            var bin = '';
            var chunk = 0x8000;
            for (var i = 0; i < bytes.length; i += chunk) {
                bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
            }
            return btoa(bin);
        } catch (_) {
            return btoa(unescape(encodeURIComponent(str)));
        }
    }

    function safeId(s) {
        return String(s || 'job').replace(/[^a-zA-Z0-9._-]+/g, '_').replace(/^_|_$/g, '').slice(0, 80) || 'job';
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
        var wrap = document.getElementById('jrm-ticket-kb');
        var list = document.getElementById('jrm-ticket-kb-list');
        if (!wrap || !list) return;
        list.innerHTML = '';
        if (!Array.isArray(hints) || hints.length === 0) {
            hide(wrap);
            return;
        }
        hints.slice(0, 3).forEach(function (h) {
            var li = document.createElement('li');
            li.className = 'flex items-start gap-2';
            var a = document.createElement('a');
            a.href = h.url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.className = 'text-sky-400 hover:text-sky-300 underline text-sm';
            a.textContent = h.title || h.url;
            var src = document.createElement('span');
            src.className = 'text-[10px] uppercase tracking-wide rounded px-1.5 py-0.5 ' +
                (h.source === 'curated'
                    ? 'bg-emerald-500/15 text-emerald-300'
                    : 'bg-slate-500/20 text-slate-300');
            src.textContent = h.source === 'curated' ? 'Curated' : 'docs.eazybackup.com';
            li.appendChild(a);
            li.appendChild(src);
            list.appendChild(li);
        });
        show(wrap);
    }

    function renderDuplicate(ticket) {
        var box = document.getElementById('jrm-ticket-dupe');
        if (!box) return;
        if (!ticket || !ticket.id) { hide(box); return; }
        var txt = box.querySelector('[data-dupe-text]');
        var lnk = box.querySelector('[data-dupe-link]');
        if (txt) txt.textContent = 'We already have an open ticket about this job (' + (ticket.tid ? '#' + ticket.tid : 'ticket #' + ticket.id) + ').';
        if (lnk) {
            lnk.href = (WEB_ROOT || '') + '/viewticket.php?tid=' + encodeURIComponent(ticket.tid || '');
            lnk.textContent = 'Open existing ticket';
        }
        show(box);
    }

    function renderPanel(ctx, dupTicket) {
        var panel = getPanel();
        if (!panel) return;
        setText('jrm-ticket-subject', ctx.subject || '');
        renderKbHints(ctx.kbHints || []);
        renderDuplicate(dupTicket || null);
        var err = document.getElementById('jrm-ticket-error');
        if (err) { err.textContent = ''; hide(err); }
        show(panel);
        // While the panel is open, hide the trigger button to declutter.
        hide(getButton());
    }

    function showError(msg) {
        var err = document.getElementById('jrm-ticket-error');
        if (err) { err.textContent = msg || 'Something went wrong, please try again.'; show(err); }
    }

    function clearPanel() {
        hide(getPanel());
        var err = document.getElementById('jrm-ticket-error');
        if (err) { err.textContent = ''; hide(err); }
        // Re-show the trigger button if this job is still eligible.
        if (isEligible(current.status)) show(getButton());
    }

    function evaluateButton() {
        var btn = getButton();
        if (!btn) return;
        if (isEligible(current.status)) {
            show(btn);
        } else {
            hide(btn);
            clearPanel();
        }
    }

    function loadContext() {
        if (!current.jobId || !current.serviceId) return Promise.resolve(null);
        if (ctxCache && ctxCache.jobId === current.jobId) return Promise.resolve(ctxCache.data);
        return api('ticketContext', {
            serviceId: current.serviceId,
            username: current.username,
            jobId: current.jobId,
        }).then(function (res) {
            if (!res || res.status !== 'success') {
                throw new Error((res && res.message) || 'Could not prepare ticket');
            }
            ctxCache = { jobId: current.jobId, data: res };
            return res;
        });
    }

    function loadDuplicate() {
        if (!current.jobId || !current.serviceId) return Promise.resolve(null);
        return api('ticketDuplicateCheck', {
            serviceId: current.serviceId,
            username: current.username,
            jobId: current.jobId,
        }).then(function (res) {
            return (res && res.status === 'success') ? res.ticket : null;
        }).catch(function () { return null; });
    }

    function onOpenClick() {
        var btn = getButton();
        if (!btn) return;
        btn.disabled = true;
        var origText = btn.querySelector('span') ? btn.querySelector('span').textContent : '';
        if (btn.querySelector('span')) btn.querySelector('span').textContent = 'Preparing...';
        Promise.all([loadContext(), loadDuplicate()]).then(function (out) {
            renderPanel(out[0], out[1]);
        }).catch(function (e) {
            showError(e && e.message ? e.message : 'Could not prepare ticket. Please try again.');
            show(getPanel());
        }).finally(function () {
            btn.disabled = false;
            if (btn.querySelector('span')) btn.querySelector('span').textContent = origText || 'Open Support Ticket';
        });
    }

    function onContinueClick() {
        var btn = document.getElementById('jrm-ticket-continue');
        if (btn) btn.disabled = true;
        try {
            if (!ctxCache || ctxCache.jobId !== current.jobId) {
                throw new Error('Ticket context not loaded');
            }
            var ctx = ctxCache.data;
            var modal = getModal();
            var rows = (modal && Array.isArray(modal._ebJobLogRows)) ? modal._ebJobLogRows : (current.logRows || []);
            var meta = ctx.jobMeta || {};
            var jobId = current.jobId;
            var sid = safeId(jobId);

            var txtBuilt = buildLogTxt(rows, meta);
            var csvText = buildLogCsv(rows);

            var files = [
                { name: 'job-' + sid + '.txt', mime: 'text/plain;charset=utf-8',         base64: utf8ToBase64(txtBuilt.text) },
                { name: 'job-' + sid + '.csv', mime: 'text/csv;charset=utf-8',           base64: utf8ToBase64(csvText) },
            ];

            var totalBytes = files.reduce(function (n, f) { return n + (f.base64.length * 0.75); }, 0);
            var oversize = totalBytes > SS_MAX_BYTES;

            if (oversize) {
                downloadFile(files[0].name, txtBuilt.text, 'text/plain;charset=utf-8');
                downloadFile(files[1].name, csvText, 'text/csv;charset=utf-8');
                files = []; // do not stash - too big
            }

            var payload = {
                jobId: jobId,
                deptId: ctx.deptId || 1,
                priority: ctx.priority || 'Medium',
                subject: ctx.subject || '',
                body: ctx.bodyMarkdown || '',
                customFieldId: ctx.customFieldId || 0,
                files: files,
                downloadedFallback: oversize,
                ts: Date.now(),
            };

            try {
                sessionStorage.setItem(SS_PREFIX + jobId, JSON.stringify(payload));
            } catch (e) {
                // sessionStorage quota - downgrade to download fallback
                if (!oversize) {
                    downloadFile(files[0].name, txtBuilt.text, 'text/plain;charset=utf-8');
                    downloadFile(files[1].name, csvText, 'text/csv;charset=utf-8');
                    payload.files = [];
                    payload.downloadedFallback = true;
                    try { sessionStorage.setItem(SS_PREFIX + jobId, JSON.stringify(payload)); } catch (_) {}
                }
            }

            var url = (WEB_ROOT || '') + '/submitticket.php?step=2'
                + '&deptid=' + encodeURIComponent(ctx.deptId || 1)
                + '&subject=' + encodeURIComponent(ctx.subject || '')
                + '&eb_job=' + encodeURIComponent(jobId);
            window.location.href = url;
        } catch (err) {
            if (btn) btn.disabled = false;
            showError(err && err.message ? err.message : 'Could not start the ticket flow.');
        }
    }

    function attach() {
        var btn = getButton();
        if (btn && !btn._ebWired) {
            btn._ebWired = true;
            btn.addEventListener('click', onOpenClick);
        }
        var cont = document.getElementById('jrm-ticket-continue');
        if (cont && !cont._ebWired) {
            cont._ebWired = true;
            cont.addEventListener('click', onContinueClick);
        }
        var cancel = document.getElementById('jrm-ticket-cancel');
        if (cancel && !cancel._ebWired) {
            cancel._ebWired = true;
            cancel.addEventListener('click', clearPanel);
        }
        // When the modal is hidden, also clear the inline panel state.
        var modal = getModal();
        if (modal && !modal._ebTicketHookWired) {
            modal._ebTicketHookWired = true;
            modal.addEventListener('click', function (e) {
                if (e.target === modal || (e.target && e.target.classList && e.target.classList.contains('eb-modal-backdrop'))) {
                    clearPanel();
                }
            });
        }
    }

    document.addEventListener('eb:job-loaded', function (ev) {
        var d = (ev && ev.detail) || {};
        current = {
            serviceId: d.serviceId,
            username: d.username || '',
            jobId: d.jobId || '',
            status: d.status || '',
            job: d.job || null,
            logRows: null,
        };
        ctxCache = null;
        clearPanel();
        attach();
        evaluateButton();
    });

    document.addEventListener('eb:job-logs-loaded', function (ev) {
        var d = (ev && ev.detail) || {};
        if (d.jobId && d.jobId === current.jobId) {
            current.logRows = Array.isArray(d.rows) ? d.rows : null;
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach, { once: true });
    } else {
        attach();
    }
})();
