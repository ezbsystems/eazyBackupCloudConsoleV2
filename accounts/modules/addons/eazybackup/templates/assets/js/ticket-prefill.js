/**
 * ticket-prefill.js
 *
 * Loaded by accounts/templates/eazyBackup/supportticketsubmit-steptwo.tpl.
 *
 * When the URL contains ?eb_job=<id>, this script:
 *   - reads the payload that job-ticket.js stashed into sessionStorage
 *   - ensures the message body is populated (fallback if Smarty didn't render it)
 *   - reconstructs File objects from base64 and assigns them to #inputAttachments
 *     using DataTransfer (the only browser-supported way to populate <input type=file>)
 *   - writes the JobID into a hidden customfield[<id>] input so the new ticket
 *     is filed against the eb_job_id custom field for dedupe / enrichment
 *   - reveals the green "log auto-attached" banner inside the form
 *   - falls back to auto-downloading the files + showing an amber banner if
 *     DataTransfer is unavailable or the payload had to be downloaded earlier
 */
(function () {
    'use strict';

    var SS_PREFIX = 'eb_ticket_';

    function qs(name) {
        try {
            var m = new URLSearchParams(window.location.search).get(name);
            return m == null ? '' : String(m);
        } catch (_) { return ''; }
    }

    function bytesFromB64(b64) {
        try {
            var bin = atob(b64);
            var out = new Uint8Array(bin.length);
            for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
            return out;
        } catch (_) { return null; }
    }

    function makeFile(spec) {
        if (!spec || !spec.name || !spec.base64) return null;
        var bytes = bytesFromB64(spec.base64);
        if (!bytes) return null;
        try {
            return new File([bytes], spec.name, { type: spec.mime || 'application/octet-stream' });
        } catch (_) {
            try {
                var blob = new Blob([bytes], { type: spec.mime || 'application/octet-stream' });
                blob.name = spec.name; blob.lastModifiedDate = new Date();
                return blob;
            } catch (_) { return null; }
        }
    }

    function downloadBlob(name, bytes, mime) {
        try {
            var blob = new Blob([bytes], { type: mime || 'application/octet-stream' });
            var u = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = u; a.download = name; a.rel = 'noopener';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(u);
        } catch (_) {}
    }

    function attachFiles(files) {
        var input = document.getElementById('inputAttachments');
        if (!input || !files || !files.length) return false;
        try {
            if (typeof DataTransfer === 'undefined') return false;
            var dt = new DataTransfer();
            files.forEach(function (f) { if (f) dt.items.add(f); });
            input.files = dt.files;
            // Fire change so any listeners (validation/UI) react
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return input.files && input.files.length === files.length;
        } catch (_) { return false; }
    }

    function showSuccessBanner(filesCount, serverAttach) {
        var box = document.getElementById('ebTicketAttachBanner');
        if (!box) return;
        box.classList.remove('hidden');
        box.classList.remove('border-amber-500/40','bg-amber-500/10','text-amber-200');
        box.classList.add('border-emerald-500/40','bg-emerald-500/10','text-emerald-200');
        if (serverAttach) {
            box.textContent = 'Your backup run log will be attached automatically when you submit this ticket. You can add more files or comments below if needed.';
            return;
        }
        box.textContent = filesCount === 1
            ? 'Backup log auto-attached. You can add more files or comments before submitting.'
            : 'Backup log auto-attached (' + filesCount + ' files). You can add more files or comments before submitting.';
    }

    function showFallbackBanner(message) {
        var box = document.getElementById('ebTicketAttachBanner');
        if (!box) return;
        box.classList.remove('hidden');
        box.classList.remove('border-emerald-500/40','bg-emerald-500/10','text-emerald-200');
        box.classList.add('border-amber-500/40','bg-amber-500/10','text-amber-200');
        box.textContent = message || 'We downloaded your backup log. Please attach it using the "Add File" button below.';
    }

    function ensureExtraSlot() {
        // The existing helper in steptwo.tpl appends an additional file input.
        try {
            if (typeof window.extraTicketAttachment === 'function') {
                window.extraTicketAttachment();
            }
        } catch (_) {}
    }

    function setHiddenCustomField(fieldId, jobId) {
        if (!fieldId || !jobId) return;
        var form = document.querySelector('form[action*="step=3"], form');
        if (!form) return;
        var name = 'customfield[' + fieldId + ']';
        if (form.querySelector('[name="' + CSS.escape(name) + '"]')) return; // already present
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = jobId;
        form.appendChild(input);
    }

    function clearTicketFileInputs() {
        try {
            document.querySelectorAll('#inputAttachments, #fileUploadsContainer input[type="file"]').forEach(function (input) {
                try {
                    input.value = '';
                    if (typeof DataTransfer !== 'undefined') {
                        var dt = new DataTransfer();
                        input.files = dt.files;
                    }
                } catch (_) {}
            });
        } catch (_) {}
    }

    function wireServerAttachFormGuard() {
        var form = document.querySelector('form[action*="step=3"], form');
        if (!form || form._ebServerAttachGuard) return;
        form._ebServerAttachGuard = true;
        form.addEventListener('submit', function () {
            clearTicketFileInputs();
        }, true);
    }

    function ensureBodyAndSubject(payload) {
        try {
            var subject = document.getElementById('inputSubject');
            if (subject && !subject.value && payload.subject) subject.value = payload.subject;
            var msg = document.getElementById('inputMessage');
            if (msg && !msg.value.trim() && payload.body) msg.value = payload.body;
        } catch (_) {}
    }

    function run() {
        var jobId = qs('eb_job');
        if (!jobId) return;
        var key = SS_PREFIX + jobId;
        var raw;
        try { raw = sessionStorage.getItem(key); } catch (_) { raw = null; }
        if (!raw) return;
        var payload;
        try { payload = JSON.parse(raw); } catch (_) { payload = null; }
        if (!payload) { try { sessionStorage.removeItem(key); } catch (_) {} return; }

        ensureBodyAndSubject(payload);
        setHiddenCustomField(payload.customFieldId, jobId);

        if (payload.downloadedFallback) {
            showFallbackBanner('Your backup log was downloaded automatically because it was large. Please attach the downloaded files using the "Add File" button below.');
            try { sessionStorage.removeItem(key); } catch (_) {}
            return;
        }

        if (payload.serverAttach) {
            showSuccessBanner(0, true);
            clearTicketFileInputs();
            wireServerAttachFormGuard();
            try { sessionStorage.removeItem(key); } catch (_) {}
            return;
        }

        var fileSpecs = (payload.files || []).filter(Boolean);
        if (!fileSpecs.length) {
            try { sessionStorage.removeItem(key); } catch (_) {}
            return;
        }

        // Build File[] objects.
        var files = fileSpecs.map(makeFile).filter(Boolean);
        if (!files.length) {
            // Couldn't decode anything - offer download fallback
            fileSpecs.forEach(function (s) {
                var bytes = bytesFromB64(s.base64);
                if (bytes) downloadBlob(s.name, bytes, s.mime);
            });
            showFallbackBanner();
            try { sessionStorage.removeItem(key); } catch (_) {}
            return;
        }

        // Attach the first file directly into the existing #inputAttachments slot.
        var firstOk = attachFiles([files[0]]);

        // For each additional file, spawn a new slot via the existing helper and assign there.
        var tail = files.slice(1);
        var extraOk = true;
        tail.forEach(function (f) {
            ensureExtraSlot();
            var inputs = document.querySelectorAll('#fileUploadsContainer input[type="file"]');
            var slot = inputs[inputs.length - 1];
            if (!slot) { extraOk = false; return; }
            try {
                var dt = new DataTransfer();
                dt.items.add(f);
                slot.files = dt.files;
                slot.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (_) { extraOk = false; }
        });

        if (firstOk && extraOk) {
            showSuccessBanner(files.length);
            try { sessionStorage.removeItem(key); } catch (_) {}
        } else {
            // Could only partially attach - download the rest
            fileSpecs.forEach(function (s) {
                var bytes = bytesFromB64(s.base64);
                if (bytes) downloadBlob(s.name, bytes, s.mime);
            });
            showFallbackBanner();
            try { sessionStorage.removeItem(key); } catch (_) {}
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
})();
