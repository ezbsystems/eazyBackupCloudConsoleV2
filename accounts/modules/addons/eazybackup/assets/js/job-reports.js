(() => {
  // Use global EB helpers for formatting and status mapping

  const endpoint = (window.EB_JOBREPORTS_ENDPOINT || (typeof EB_MODULE_LINK!=='undefined' ? `${EB_MODULE_LINK}&a=job-reports` : 'index.php?m=eazybackup&a=job-reports'));
  const globalEndpoint = (window.EB_JOBREPORTS_GLOBAL_ENDPOINT || '');

  async function api(action, payload){
    const res = await fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload||{ action }) });
    return await res.json();
  }

  async function apiGlobal(action, payload){
    if (!globalEndpoint) throw new Error('Global endpoint is not configured');
    const res = await fetch(globalEndpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload||{ action }) });
    return await res.json();
  }

  async function apiModal(action, payload){
    const primary = await api(action, payload);
    if (primary && primary.status === 'success') return primary;
    if (globalEndpoint && globalEndpoint !== endpoint) {
      try {
        const secondary = await apiGlobal(action, payload);
        if (secondary && secondary.status === 'success') return secondary;
        return secondary || primary;
      } catch (_) {}
    }
    return primary;
  }

  function ebConfirmDialog(opts){
    return new Promise((resolve) => {
      const root = document.getElementById('jrm-confirm');
      if (!root) { resolve(window.confirm(opts && opts.message || '')); return; }
      const titleEl = root.querySelector('#jrm-confirm-title');
      const msgEl   = root.querySelector('#jrm-confirm-message');
      const okBtn   = root.querySelector('#jrm-confirm-ok');
      const cancelBtn = root.querySelector('#jrm-confirm-cancel');
      const o = opts || {};
      titleEl.textContent = o.title || 'Confirm';
      msgEl.textContent   = o.message || '';
      okBtn.textContent   = o.confirmLabel || 'Confirm';
      cancelBtn.textContent = o.cancelLabel || 'Cancel';
      okBtn.classList.remove('eb-btn-danger', 'eb-btn-primary', 'eb-btn-secondary');
      okBtn.classList.add(o.variant === 'primary' ? 'eb-btn-primary'
                         : o.variant === 'secondary' ? 'eb-btn-secondary'
                         : 'eb-btn-danger');
      cancelBtn.classList.toggle('hidden', o.notice === true);

      const cleanup = (result) => {
        root.classList.add('hidden');
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        root.querySelectorAll('[data-jrm-confirm-dismiss]').forEach(el => el.removeEventListener('click', onCancel));
        document.removeEventListener('keydown', onKey);
        resolve(result);
      };
      const onOk = () => cleanup(true);
      const onCancel = () => cleanup(false);
      const onKey = (e) => {
        if (e.key === 'Escape') onCancel();
        else if (e.key === 'Enter') onOk();
      };
      okBtn.addEventListener('click', onOk);
      cancelBtn.addEventListener('click', onCancel);
      root.querySelectorAll('[data-jrm-confirm-dismiss]').forEach(el => el.addEventListener('click', onCancel));
      document.addEventListener('keydown', onKey);
      root.classList.remove('hidden');
      try { okBtn.focus(); } catch (_) {}
    });
  }

  function ebNoticeDialog(opts){
    const o = opts || {};
    return ebConfirmDialog(Object.assign({
      confirmLabel: 'OK',
      variant: 'primary',
      notice: true,
    }, o));
  }

  function jobLogSeverityLabel(sevRaw){
    const sev = (sevRaw || '').toUpperCase();
    if (sev === 'I') return 'Information';
    if (sev === 'W') return 'Warning';
    if (sev === 'E') return 'Error';
    return String(sevRaw || '');
  }

  function csvEscapeCell(v){
    const s = String(v ?? '');
    if (/[",\r\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
    return s;
  }

  function buildJobLogCsv(rows){
    const header = ['UnixTime', 'Timestamp', 'Severity', 'Message'];
    const lines = [header.map(csvEscapeCell).join(',')];
    for (const e of rows){
      const unix = Number(e.Time) || 0;
      const ts = (window.EB && EB.fmtTs) ? EB.fmtTs(unix) : (unix ? new Date(unix * 1000).toISOString() : '');
      const sev = jobLogSeverityLabel(e.Severity);
      const msg = String(e.Message ?? '');
      lines.push([csvEscapeCell(unix), csvEscapeCell(ts), csvEscapeCell(sev), csvEscapeCell(msg)].join(','));
    }
    return '\ufeff' + lines.join('\r\n');
  }

  function triggerTextDownload(filename, text, mime){
    const blob = new Blob([text], { type: mime || 'text/csv;charset=utf-8' });
    const u = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = u;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(u);
  }

  function exportJobLogFromModal(){
    const modal = document.getElementById('job-report-modal');
    if (!modal) return;
    const rows = modal._ebJobLogRows;
    const jobId = (modal.querySelector('#jrm-current-job') || {}).value || 'job';
    if (!Array.isArray(rows)){
      try { window.showToast?.('Log data is not loaded yet. Open a job report first.', 'info'); } catch (_) { alert('Log data is not loaded yet.'); }
      return;
    }
    const safeId = String(jobId).replace(/[^a-zA-Z0-9._-]+/g, '_').replace(/^_|_$/g, '').slice(0, 80) || 'job';
    const csv = buildJobLogCsv(rows);
    triggerTextDownload(`job-${safeId}-log.csv`, csv, 'text/csv;charset=utf-8');
  }

  async function openJobModal(serviceId, username, jobId){
    const modal = document.getElementById('job-report-modal');
    if(!modal) return;
    modal._ebJobLogRows = [];
    modal.classList.remove('hidden');
    modal.querySelector('#jrm-title').textContent = `Job`;
    modal.querySelector('#jrm-subtitle').textContent = `${username}`;
    modal.querySelector('#jrm-current-job').value = jobId;
    modal.querySelector('#jrm-service-id').value = String(serviceId||'');
    modal.querySelector('#jrm-username').value = String(username||'');
    const cancelBtn = modal.querySelector('#jrm-cancel');
    if (cancelBtn) {
      cancelBtn.classList.add('hidden');
      cancelBtn.disabled = false;
      cancelBtn.textContent = 'Cancel job';
    }

    // Load summary
    try {
      const det = await apiModal('jobDetail', { action:'jobDetail', serviceId, username, jobId });
      if(det && det.status==='success' && det.job){
        const j = det.job;
        const statusEl = modal.querySelector('#jrm-status');
        const label = (window.EB && EB.humanStatus ? EB.humanStatus(j.FriendlyStatus || j.Status || j.StatusCode) : (j.FriendlyStatus || ''));
        if (statusEl) {
          statusEl.textContent = label;
          try {
            var cls = (window.EB && EB.statusText) ? EB.statusText(label) : '';
            statusEl.classList.remove('text-slate-200','text-emerald-400','text-sky-400','text-amber-400','text-rose-400','text-fuchsia-400','text-gray-300','text-green-500','text-sky-500','text-amber-500','text-red-500','text-gray-500','text-gray-400');
            if (cls) statusEl.classList.add(cls);
          } catch(_) {}
        }
        if (cancelBtn) {
          if (String(label).toLowerCase() === 'running') cancelBtn.classList.remove('hidden');
          else cancelBtn.classList.add('hidden');
        }
        modal.querySelector('#jrm-type').textContent     = j.FriendlyJobType || '';
        modal.querySelector('#jrm-device').textContent   = (j.DeviceFriendlyName || j.Device || '');
        modal.querySelector('#jrm-item').textContent     = j.ProtectedItemDescription || '';
        modal.querySelector('#jrm-vault').textContent    = (j.VaultDescription || j.DestinationLocation || '');
        modal.querySelector('#jrm-up').textContent       = (window.EB && EB.fmtBytes ? EB.fmtBytes(j.UploadSize||0) : String(j.UploadSize||0));
        modal.querySelector('#jrm-down').textContent     = (window.EB && EB.fmtBytes ? EB.fmtBytes(j.DownloadSize||0) : String(j.DownloadSize||0));
        modal.querySelector('#jrm-size').textContent     = (window.EB && EB.fmtBytes ? EB.fmtBytes(j.TotalSize||0) : String(j.TotalSize||0));
        modal.querySelector('#jrm-start').textContent    = (window.EB && EB.fmtTs ? EB.fmtTs(j.StartTime||0) : '');
        modal.querySelector('#jrm-end').textContent      = (window.EB && EB.fmtTs ? EB.fmtTs(j.EndTime||0) : '');
        modal.querySelector('#jrm-duration').textContent = (window.EB && EB.fmtDur ? EB.fmtDur((j.EndTime||0)-(j.StartTime||0)) : '');
        modal.querySelector('#jrm-version').textContent  = j.ClientVersion || '';
        // Title as protected item name
        modal.querySelector('#jrm-title').textContent = j.ProtectedItemDescription || `Job ${jobId}`;
        try {
          document.dispatchEvent(new CustomEvent('eb:job-loaded', {
            detail: { serviceId, username, jobId, status: label, job: j }
          }));
        } catch (_) {}
      }
    } catch(_){ }

    // Load logs
    try {
      const logs = await apiModal('jobLogEntries', { action:'jobLogEntries', serviceId, username, jobId });
      const box = modal.querySelector('#jrm-logs');
      box.innerHTML = '';
      if(logs && logs.status==='success' && Array.isArray(logs.rows)){
        modal._ebJobLogRows = logs.rows.slice();
        try {
          document.dispatchEvent(new CustomEvent('eb:job-logs-loaded', {
            detail: { serviceId, username, jobId, rows: modal._ebJobLogRows }
          }));
        } catch (_) {}
        for(const e of logs.rows){
          const row = document.createElement('div');
          row.className = 'eb-log-line px-3 py-2';
          const t = document.createElement('span');
          t.className = 'eb-log-timestamp text-xs shrink-0';
          t.textContent = (window.EB && EB.fmtTs ? EB.fmtTs(e.Time) : '');
          const s = document.createElement('span');
          s.className = 'eb-log-level';
          const sev = (e.Severity||'').toUpperCase();
          if(sev==='I'){ s.classList.add('info'); s.textContent = 'Information'; }
          else if(sev==='W'){ s.classList.add('warn'); s.textContent = 'Warning'; }
          else if(sev==='E'){ s.classList.add('error'); s.textContent = 'Error'; }
          else { s.classList.add('debug'); s.textContent = e.Severity||''; }
          const m = document.createElement('span');
          m.className = 'eb-log-message text-sm min-w-0 flex-1';
          m.textContent = e.Message;
          row.dataset.sev = (e.Severity||'').toLowerCase();
          row.appendChild(t); row.appendChild(s); row.appendChild(m);
          box.appendChild(row);
        }
      }
    } catch(_){ modal._ebJobLogRows = []; }
  }

  // ---------------- Cancellation progress modal ----------------

  const RUNNING_STATUS_CODES = new Set([6000, 6001, 6002]);

  function isRunningJob(job){
    if (!job) return false;
    const code = Number(job.Status);
    if (Number.isFinite(code) && RUNNING_STATUS_CODES.has(code)) return true;
    const label = String(job.FriendlyStatus || '').toLowerCase();
    return label === 'running' || label === 'active' || label === 'revived';
  }

  function ebCancelProgressOpen(){
    const root = document.getElementById('jrm-cancel-progress');
    if (!root) return null;
    const stepsEl   = root.querySelector('#jrm-cancel-steps');
    const overall   = root.querySelector('#jrm-cancel-overall');
    const overallTx = root.querySelector('#jrm-cancel-overall-text');
    const spinner   = root.querySelector('#jrm-cancel-spinner');
    const closeBtn  = root.querySelector('#jrm-cancel-close');
    const doneBtn   = root.querySelector('#jrm-cancel-done');
    const forceBtn  = root.querySelector('#jrm-cancel-force');

    stepsEl.innerHTML = '';
    overall.style.color = 'var(--eb-text-secondary)';
    overallTx.textContent = 'Working...';
    spinner.classList.remove('hidden');
    closeBtn.classList.add('hidden');
    doneBtn.classList.add('hidden');
    forceBtn.classList.add('hidden');
    forceBtn.disabled = false;
    forceBtn.textContent = 'Force cancel';
    root.classList.remove('hidden');

    function makeStepNode(label){
      const li = document.createElement('li');
      li.className = 'flex items-start gap-2';
      const icon = document.createElement('span');
      icon.className = 'mt-0.5 inline-flex h-4 w-4 items-center justify-center shrink-0';
      icon.dataset.role = 'icon';
      icon.innerHTML = pendingIconSvg();
      const text = document.createElement('span');
      text.className = 'min-w-0 flex-1';
      text.style.color = 'var(--eb-text-secondary)';
      text.textContent = label;
      li.appendChild(icon);
      li.appendChild(text);
      return { li, icon, text };
    }

    function pendingIconSvg(){
      return '<svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">'
        + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
        + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>'
        + '</svg>';
    }
    function checkIconSvg(){
      return '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
        + '<path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.5 7.59a1 1 0 0 1-1.426.006l-3.5-3.5a1 1 0 1 1 1.414-1.414l2.793 2.793 6.793-6.883a1 1 0 0 1 1.42-.006z" clip-rule="evenodd"/>'
        + '</svg>';
    }
    function warnIconSvg(){
      return '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
        + '<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.59c.75 1.335-.213 2.99-1.743 2.99H3.482c-1.53 0-2.493-1.655-1.743-2.99L8.257 3.1zM11 13a1 1 0 1 0-2 0 1 1 0 0 0 2 0zm-1-2a1 1 0 0 1-1-1V7a1 1 0 0 1 2 0v3a1 1 0 0 1-1 1z" clip-rule="evenodd"/>'
        + '</svg>';
    }
    function errorIconSvg(){
      return '<svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
        + '<path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.707-10.293a1 1 0 0 0-1.414-1.414L10 8.586 7.707 6.293a1 1 0 0 0-1.414 1.414L8.586 10l-2.293 2.293a1 1 0 1 0 1.414 1.414L10 11.414l2.293 2.293a1 1 0 0 0 1.414-1.414L11.414 10l2.293-2.293z" clip-rule="evenodd"/>'
        + '</svg>';
    }

    function addStep(label){
      const node = makeStepNode(label);
      stepsEl.appendChild(node.li);
      return {
        update(label){ node.text.textContent = label; },
        succeed(label){
          if (label) node.text.textContent = label;
          node.icon.style.color = 'var(--eb-success-text, #34d399)';
          node.icon.innerHTML = checkIconSvg();
        },
        warn(label){
          if (label) node.text.textContent = label;
          node.icon.style.color = 'var(--eb-warning-text, #f59e0b)';
          node.icon.innerHTML = warnIconSvg();
        },
        fail(label){
          if (label) node.text.textContent = label;
          node.icon.style.color = 'var(--eb-danger-text, #f87171)';
          node.icon.innerHTML = errorIconSvg();
        },
      };
    }

    function setOverall(text, tone){
      overallTx.textContent = text || '';
      let color = 'var(--eb-text-secondary)';
      if (tone === 'success') color = 'var(--eb-success-text, #34d399)';
      else if (tone === 'warning') color = 'var(--eb-warning-text, #f59e0b)';
      else if (tone === 'error') color = 'var(--eb-danger-text, #f87171)';
      overall.style.color = color;
    }

    function setBusy(busy){
      if (busy) spinner.classList.remove('hidden');
      else spinner.classList.add('hidden');
    }

    function finish(){
      setBusy(false);
      doneBtn.classList.remove('hidden');
      closeBtn.classList.remove('hidden');
      forceBtn.classList.add('hidden');
    }

    function close(){ root.classList.add('hidden'); }

    function setSubtitle(text){
      const sub = root.querySelector('#jrm-cancel-subtitle');
      if (sub) sub.textContent = text || '';
    }

    function showForceButton(label, onClick){
      forceBtn.textContent = label || 'Force cancel';
      forceBtn.classList.remove('hidden');
      forceBtn.disabled = false;
      const handler = async () => {
        forceBtn.removeEventListener('click', handler);
        forceBtn.disabled = true;
        forceBtn.textContent = 'Working...';
        try { await onClick(); } finally { /* caller controls UI */ }
      };
      forceBtn.addEventListener('click', handler);
    }
    function hideForceButton(){
      forceBtn.classList.add('hidden');
    }

    doneBtn.onclick  = close;
    closeBtn.onclick = close;

    return { addStep, setOverall, setBusy, finish, close, setSubtitle, showForceButton, hideForceButton };
  }

  async function pollUntilNotRunning(serviceId, username, jobId, timeoutMs, intervalMs){
    const deadline = Date.now() + (timeoutMs || 15000);
    const interval = intervalMs || 2000;
    let lastJob = null;
    while (Date.now() < deadline) {
      try {
        const det = await apiModal('jobDetail', { action:'jobDetail', serviceId, username, jobId });
        if (det && det.status === 'success' && det.job) {
          lastJob = det.job;
          if (!isRunningJob(det.job)) return { settled: true, job: det.job };
        }
      } catch (_) {}
      await new Promise(r => setTimeout(r, interval));
    }
    return { settled: false, job: lastJob };
  }

  async function runCancellationFlow(opts){
    const { serviceId, username, jobId, cancelBtn } = opts;
    const ui = ebCancelProgressOpen();
    if (!ui) return;
    if (cancelBtn) { cancelBtn.disabled = true; cancelBtn.textContent = 'Cancelling…'; }
    ui.setSubtitle(`${username} • job ${String(jobId).slice(0,12)}…`);

    const restoreCancelButton = (label) => {
      if (!cancelBtn) return;
      cancelBtn.disabled = false;
      cancelBtn.textContent = label || 'Cancel job';
    };

    const reloadJobModalSummary = () => {
      try { openJobModal(serviceId, username, jobId); } catch (_) {}
    };

    // Step 1: graceful cancel via AdminJobCancel
    const step1 = ui.addStep('Sending cancellation request to the device...');
    let gracefulOk = false;
    let gracefulErr = '';
    try {
      const resp = await apiModal('cancelJob', { action: 'cancelJob', serviceId, username, jobId });
      if (resp && resp.status === 'success') {
        gracefulOk = true;
        step1.succeed('Cancellation request sent to the device.');
      } else {
        gracefulErr = (resp && resp.message) || 'Comet API returned an error.';
        step1.fail('Graceful cancel failed: ' + gracefulErr);
      }
    } catch (e) {
      gracefulErr = e.message || 'Network error';
      step1.fail('Graceful cancel failed: ' + gracefulErr);
    }

    if (gracefulOk) {
      // Step 2: poll for the device to acknowledge
      const step2 = ui.addStep('Waiting for the device to confirm the job has stopped...');
      const result = await pollUntilNotRunning(serviceId, username, jobId, 20000, 2000);
      if (result.settled) {
        const label = result.job && result.job.FriendlyStatus ? result.job.FriendlyStatus : 'Stopped';
        step2.succeed(`Device acknowledged. Job is now: ${label}.`);
        ui.setOverall('Job cancelled successfully.', 'success');
        ui.finish();
        restoreCancelButton();
        reloadJobModalSummary();
        return;
      }
      step2.warn('The device has not confirmed within 20 seconds.');
      ui.setOverall('The device did not acknowledge the cancellation. You can force-mark the job as abandoned on the server.', 'warning');
      ui.setBusy(false);
      ui.showForceButton('Force cancel (mark abandoned)', async () => {
        await runForceCancel(ui, { serviceId, username, jobId, restoreCancelButton, reloadJobModalSummary });
      });
      return;
    }

    // Graceful failed → automatic fallback to AdminJobAbandon, but show progress
    const step3 = ui.addStep('Falling back to force cancel (admin abandon)...');
    try {
      const resp2 = await apiModal('abandonJob', { action: 'abandonJob', serviceId, username, jobId });
      if (resp2 && resp2.status === 'success') {
        step3.succeed(resp2.notice || 'Job force-marked as abandoned on the server.');
        ui.setOverall('Job force-marked as abandoned.', 'warning');
        ui.finish();
        restoreCancelButton();
        reloadJobModalSummary();
      } else {
        const err = (resp2 && resp2.message) || 'Comet API returned an error.';
        step3.fail('Force cancel failed: ' + err);
        ui.setOverall('Cancellation failed. Please contact support if the job remains stuck.', 'error');
        ui.finish();
        restoreCancelButton();
      }
    } catch (e) {
      step3.fail('Force cancel failed: ' + (e.message || 'Network error'));
      ui.setOverall('Cancellation failed. Please contact support if the job remains stuck.', 'error');
      ui.finish();
      restoreCancelButton();
    }
  }

  async function runForceCancel(ui, ctx){
    const { serviceId, username, jobId, restoreCancelButton, reloadJobModalSummary } = ctx;
    ui.setBusy(true);
    ui.setOverall('Force cancelling job on the server...', 'warning');
    const step = ui.addStep('Sending force-cancel (admin abandon) to the Comet server...');
    try {
      const resp = await apiModal('abandonJob', { action: 'abandonJob', serviceId, username, jobId });
      if (resp && resp.status === 'success') {
        step.succeed(resp.notice || 'Job force-marked as abandoned on the server.');
        ui.setOverall('Job force-marked as abandoned.', 'warning');
        ui.hideForceButton();
        ui.finish();
        restoreCancelButton();
        reloadJobModalSummary();
      } else {
        const err = (resp && resp.message) || 'Comet API returned an error.';
        step.fail('Force cancel failed: ' + err);
        ui.setOverall('Cancellation failed. Please contact support if the job remains stuck.', 'error');
        ui.hideForceButton();
        ui.finish();
        restoreCancelButton();
      }
    } catch (e) {
      step.fail('Force cancel failed: ' + (e.message || 'Network error'));
      ui.setOverall('Cancellation failed. Please contact support if the job remains stuck.', 'error');
      ui.hideForceButton();
      ui.finish();
      restoreCancelButton();
    }
  }

  function attachModalControls(){
    const modal = document.getElementById('job-report-modal');
    if(!modal) return;
    const closeBtn = modal.querySelector('#jrm-close');
    closeBtn && closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', (e) => {
      if (e.target === modal || (e.target && e.target.classList && e.target.classList.contains('eb-modal-backdrop'))) {
        modal.classList.add('hidden');
      }
    });

    const filter = modal.querySelector('#jrm-filter');
    const search = modal.querySelector('#jrm-search');
    const applyFilter = () => {
      const want = (filter.value||'all').toLowerCase();
      const q = (search.value||'').toLowerCase();
      modal.querySelectorAll('#jrm-logs > div').forEach(el => {
        const raw = (el.dataset.sev || '').toLowerCase();
        const sevOk = (want === 'all')
          || (want === 'warning' && (raw === 'w' || raw === 'warning'))
          || (want === 'error' && (raw === 'e' || raw === 'error'))
          || (want !== 'warning' && want !== 'error' && raw === want);
        const text = el.textContent.toLowerCase();
        el.style.display = (sevOk && (q==='' || text.includes(q))) ? '' : 'none';
      });
    };
    filter && filter.addEventListener('change', applyFilter);
    search && search.addEventListener('input', applyFilter);

    const exportBtn = modal.querySelector('#jrm-export');
    exportBtn && exportBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      exportJobLogFromModal();
    });

    const cancelBtn = modal.querySelector('#jrm-cancel');
    cancelBtn && cancelBtn.addEventListener('click', async (ev) => {
      ev.preventDefault();
      const ok = await ebConfirmDialog({
        title: 'Cancel running backup job?',
        message: 'A cancellation request will be sent to the device. If the device is offline or unresponsive, you can choose to force-mark the job as abandoned.',
        confirmLabel: 'Cancel job',
        cancelLabel: 'Keep running',
        variant: 'danger',
      });
      if (!ok) return;
      const serviceId = parseInt(modal.querySelector('#jrm-service-id').value || '0', 10);
      const username  = modal.querySelector('#jrm-username').value || '';
      const jobId     = modal.querySelector('#jrm-current-job').value || '';
      if (!serviceId || !username || !jobId) return;
      runCancellationFlow({ serviceId, username, jobId, cancelBtn });
    });
  }

  function attachRowOpeners(){
    // Delegate on any table with [data-job-table]
    document.querySelectorAll('[data-job-table] tbody').forEach(tbody => {
      tbody.addEventListener('click', (e) => {
        let tr = e.target.closest('tr');
        if(!tr) return;
        const jobId = tr.getAttribute('data-job-id');
        const serviceId = tr.getAttribute('data-service-id') || document.body.getAttribute('data-eb-serviceid');
        const username = tr.getAttribute('data-username') || document.body.getAttribute('data-eb-username');
        if(jobId && serviceId && username){ openJobModal(parseInt(serviceId,10), username, jobId); }
      });
    });
  }

  function makeJobsTable(el, opts){
    const state = { page:1, pageSize:10, sortBy:'Started', sortDir:'desc', q:'', statuses: [], facets: { statusCounts: {} } };
    const serviceId = opts.serviceId; const username = opts.username;
    const thead = el.querySelector('thead'); const tbody = el.querySelector('tbody');
    const colKeys = ['user','id','device','item','vault','ver','type','status','dirs','files','size','vsize','up','down','started','ended','dur'];
    const totalEl = opts.totalEl; const pagerEl = opts.pagerEl; const searchInput = opts.searchInput;
    const chipButtons = Array.isArray(opts.chipButtons) ? opts.chipButtons : Array.from(document.querySelectorAll('[data-jobs-status-chip]'));
    const clearBtn = opts.clearBtn || document.getElementById('jobs-clear-filters');
    const summaryEl = opts.summaryEl || document.getElementById('jobs-active-filters');
    let searchDebounce = null;

    function normalizeStatus(label){
      if (window.EB && EB.humanStatus) return EB.humanStatus(label);
      return String(label || '');
    }

    function statusCount(label){
      const key = normalizeStatus(label);
      const n = Number((state.facets && state.facets.statusCounts && state.facets.statusCounts[key]) || 0);
      return Number.isFinite(n) ? n : 0;
    }

    function hasActiveFilters(){
      return !!((state.q || '').trim() || (state.statuses && state.statuses.length));
    }

    function renderFilterSummary(){
      if (!summaryEl) return;
      if (!hasActiveFilters()) {
        summaryEl.textContent = '';
        summaryEl.classList.add('hidden');
        return;
      }
      const parts = [];
      if (state.statuses.length) parts.push(`Status: ${state.statuses.join(', ')}`);
      if ((state.q || '').trim()) parts.push(`Search: "${state.q.trim()}"`);
      summaryEl.textContent = `Filtering by ${parts.join(' + ')}`;
      summaryEl.classList.remove('hidden');
    }

    function renderStatusChips(){
      chipButtons.forEach((btn) => {
        const label = normalizeStatus(btn.getAttribute('data-status') || '');
        const active = state.statuses.includes(label);
        const count = statusCount(label);
        const disabled = count === 0;
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.disabled = disabled;
        btn.classList.remove('border-sky-500/60','bg-sky-500/10','text-sky-200','opacity-50','cursor-not-allowed');
        btn.classList.remove('border-slate-700/80','bg-slate-900/40','text-slate-300','hover:border-slate-600','hover:bg-slate-900/60');
        if (active) btn.classList.add('border-sky-500/60','bg-sky-500/10','text-sky-200');
        else btn.classList.add('border-slate-700/80','bg-slate-900/40','text-slate-300','hover:border-slate-600','hover:bg-slate-900/60');
        if (disabled) btn.classList.add('opacity-50','cursor-not-allowed');
        const countEl = btn.querySelector('[data-jobs-status-count]');
        if (countEl) countEl.textContent = String(count);
      });
      if (clearBtn) {
        clearBtn.classList.toggle('hidden', !hasActiveFilters());
      }
      renderFilterSummary();
    }

    function renderEmptyRow(filtered){
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = colKeys.length;
      td.className = 'px-4 py-10 text-center text-sm text-slate-400';
      td.textContent = filtered ? 'No jobs match current filters.' : 'No jobs found for this user.';
      tr.appendChild(td);
      tbody.appendChild(tr);
    }

    function renderSortIndicators(){
      if (!thead) return;
      thead.querySelectorAll('th[data-sort]').forEach((th) => {
        const indicator = th.querySelector('[data-sort-indicator]');
        if (!indicator) return;
        const key = th.getAttribute('data-sort');
        indicator.textContent = (state.sortBy === key) ? (state.sortDir === 'asc' ? '↑' : '↓') : '';
      });
    }

    function bindFilterControls(){
      chipButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          const label = normalizeStatus(btn.getAttribute('data-status') || '');
          if (!label || btn.disabled) return;
          const idx = state.statuses.indexOf(label);
          if (idx >= 0) state.statuses.splice(idx, 1);
          else state.statuses.push(label);
          state.page = 1;
          renderStatusChips();
          load();
        });
      });
      clearBtn && clearBtn.addEventListener('click', () => {
        state.statuses = [];
        state.q = '';
        state.page = 1;
        if (searchInput) searchInput.value = '';
        renderStatusChips();
        load();
      });
    }

    async function load(){
      const payload = {
        action: 'listJobs',
        serviceId,
        username,
        page: state.page,
        pageSize: state.pageSize,
        sortBy: state.sortBy,
        sortDir: state.sortDir,
        q: state.q,
        statuses: state.statuses.slice(0),
      };
      const res = await api('listJobs', payload);
      if(!(res && res.status==='success')) {
        try { window.showToast?.('Could not refresh jobs. Please try again.', 'error'); } catch(_) {}
        return;
      }
      state.facets = res.facets || { statusCounts: {} };
      totalEl && (totalEl.textContent = String(res.total||0));
      tbody.innerHTML = '';
      const rows = Array.isArray(res.rows) ? res.rows : [];
      if (!rows.length) {
        renderEmptyRow(hasActiveFilters());
      }
      for(const r of rows){
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-800/50 cursor-pointer';
        tr.setAttribute('data-job-id', r.JobID);
        tr.setAttribute('data-service-id', String(serviceId));
        tr.setAttribute('data-username', String(username));
        const cells = [
          r.Username,
          r.JobID,
          r.Device,
          r.ProtectedItem,
          r.StorageVault,
          r.Version,
          r.Type,
          (window.EB && EB.humanStatus ? EB.humanStatus(r.Status || r.StatusCode) : (r.Status||'')),
          r.Directories,
          r.Files,
          (window.EB && EB.fmtBytes ? EB.fmtBytes(r.Size) : String(r.Size||0)),
          (window.EB && EB.fmtBytes ? EB.fmtBytes(r.VaultSize) : String(r.VaultSize||0)),
          (window.EB && EB.fmtBytes ? EB.fmtBytes(r.Uploaded) : String(r.Uploaded||0)),
          (window.EB && EB.fmtBytes ? EB.fmtBytes(r.Downloaded) : String(r.Downloaded||0)),
          (window.EB && EB.fmtTs ? EB.fmtTs(r.Started) : ''),
          (window.EB && EB.fmtTs ? EB.fmtTs(r.Ended) : ''),
          (window.EB && EB.fmtDur ? EB.fmtDur(r.Duration) : '')
        ];
        for(let i=0;i<cells.length;i++){
          const td=document.createElement('td'); td.className='px-4 py-3 whitespace-nowrap text-sm';
          td.setAttribute('data-col', colKeys[i]);
          if (i===7) { // Status column
            const label = cells[i];
            td.textContent = label;
            const dot = (window.EB && EB.statusDot ? EB.statusDot(label) : '');
            if (dot.indexOf('green')>=0) td.classList.add('text-emerald-400');
            else if (dot.indexOf('sky')>=0) td.classList.add('text-sky-400');
            else if (dot.indexOf('amber')>=0) td.classList.add('text-amber-400');
            else if (dot.indexOf('red')>=0) td.classList.add('text-rose-400');
            else if (label === 'Missed') td.classList.add('text-slate-300');
            else td.classList.add('text-slate-300');
          } else {
            td.classList.add('text-slate-300');
            td.textContent = cells[i]!==undefined? String(cells[i]) : '';
          }
          tr.appendChild(td);
        }
        tbody.appendChild(tr);
      }
      renderPager(res.total||0);
      renderSortIndicators();
      renderStatusChips();
      // Apply current column visibility to body cells
      syncColumnVisibility();
    }

    function renderPager(total){
      if(!pagerEl) return;
      const pages = Math.max(1, Math.ceil(total / state.pageSize));
      pagerEl.innerHTML = '';
      const mk = (label, page, disabled=false, current=false) => {
        const b = document.createElement('button');
        b.className = 'px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 text-xs text-slate-200' + (disabled ? ' opacity-50 cursor-not-allowed' : '');
        b.textContent = label;
        if(!disabled) b.addEventListener('click',()=>{ state.page=page; load(); });
        else b.disabled = true;
        return b;
      };
      pagerEl.appendChild(mk('Prev', Math.max(1, state.page-1), state.page<=1));
      const pageText = document.createElement('span');
      pageText.className = 'text-slate-300';
      pageText.textContent = `Page ${state.page} / ${pages}`;
      pagerEl.appendChild(pageText);
      pagerEl.appendChild(mk('Next', Math.min(pages, state.page+1), state.page>=pages));
    }

    // Sorting handlers
    thead.querySelectorAll('th[data-sort]').forEach((th) => {
      th.addEventListener('click', () => {
        const key = th.getAttribute('data-sort');
        if(state.sortBy === key){ state.sortDir = (state.sortDir==='asc'?'desc':'asc'); } else { state.sortBy = key; state.sortDir = 'asc'; }
        renderSortIndicators();
        load();
      });
    });

    // Search
    searchInput && searchInput.addEventListener('input', () => {
      if (searchDebounce) clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        state.q = searchInput.value||'';
        state.page = 1;
        renderStatusChips();
        load();
      }, 200);
    });

    // Page size changes (dispatched from Alpine dropdown)
    window.addEventListener('jobs:pagesize', (e) => {
      const size = parseInt(e.detail, 10);
      if (size && [10, 25, 50, 100].includes(size)) {
        state.pageSize = size;
        state.page = 1;
        load();
      }
    });

    function isVisible(node){
      if(!node) return false;
      if (node.offsetParent !== null) return true;
      const cs = window.getComputedStyle(node);
      return cs && cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0';
    }

    function syncColumnVisibility(){
      if (!isVisible(el) || !isVisible(thead)) return; // don't hide when table is not visible yet
      // Derive visibility from header THs using their data-sort attribute
      const ths = Array.from(thead.querySelectorAll('tr th'));
      const mapSortToKey = {
        Username: 'user', JobID: 'id', Device: 'device', ProtectedItem: 'item', StorageVault: 'vault', Version: 'ver',
        Type: 'type', Status: 'status', Directories: 'dirs', Files: 'files', Size: 'size', VaultSize: 'vsize',
        Uploaded: 'up', Downloaded: 'down', Started: 'started', Ended: 'ended', Duration: 'dur'
      };
      const vis = {};
      ths.forEach(th => {
        const sort = th.getAttribute('data-sort');
        const key = mapSortToKey[sort];
        if (!key) return;
        vis[key] = (th.offsetParent !== null);
      });
      // If nothing mapped or all hidden, show all
      const visKeys = Object.keys(vis);
      const allHidden = visKeys.length>0 && visKeys.every(k => vis[k] === false);
      el.querySelectorAll('tbody tr').forEach(tr => {
        tr.querySelectorAll('td[data-col]').forEach(td => {
          const key = td.getAttribute('data-col');
          td.style.display = (allHidden || vis[key]!==false) ? '' : 'none';
        });
      });
    }

    // Observe header toggles to mirror visibility on body cells
    (function observeHeaderToggles(){
      try {
        const obs = new MutationObserver(() => { syncColumnVisibility(); });
        obs.observe(thead, { attributes:true, subtree:true, attributeFilter:['style','class'] });
      } catch(_){ }
    })();

    // Try resync shortly after load to catch transitions
    setTimeout(syncColumnVisibility, 50);
    setTimeout(syncColumnVisibility, 150);

    // Also resync when the table actually becomes visible (e.g., when its tab is shown)
    (function observeVisibility(){
      try {
        const io = new IntersectionObserver((entries) => {
          for (const entry of entries) {
            if (entry.isIntersecting) {
              syncColumnVisibility();
            }
          }
        }, { root: null, threshold: 0.01 });
        io.observe(el);
      } catch(_){ }
    })();

    // Public API
    bindFilterControls();
    renderStatusChips();
    renderSortIndicators();
    return { reload: load };
  }

  function makeGlobalJobsTable(el, opts){
    const state = {
      page: 1,
      pageSize: 10,
      sortBy: 'Started',
      sortDir: 'desc',
      q: '',
      statuses: [],
      username: '',
      rangeHours: [24, 48, 60, 72].includes(Number(opts.rangeHours)) ? Number(opts.rangeHours) : 24,
      facets: { statusCounts: {}, usernameCounts: {} }
    };
    const thead = el.querySelector('thead');
    const tbody = el.querySelector('tbody');
    const colKeys = ['user','id','device','item','vault','ver','type','status','dirs','files','size','vsize','up','down','started','ended','dur'];
    const totalEl = opts.totalEl;
    const loadingEl = opts.loadingEl;
    const pagerEl = opts.pagerEl;
    const searchInput = opts.searchInput;
    const usernameDropdown = opts.usernameDropdown;
    const usernameMenuLabel = opts.usernameMenuLabel;
    const usernameMenuList = opts.usernameMenuList;
    const chipButtons = Array.isArray(opts.chipButtons) ? opts.chipButtons : Array.from(document.querySelectorAll('[data-jobs-status-chip]'));
    const clearBtn = opts.clearBtn || document.getElementById('jobs-clear-filters');
    const summaryEl = opts.summaryEl || document.getElementById('jobs-active-filters');
    let searchDebounce = null;

    function setLoading(isLoading){
      if (!loadingEl) return;
      loadingEl.classList.toggle('hidden', !isLoading);
      loadingEl.classList.toggle('inline-flex', isLoading);
    }

    function normalizeStatus(label){
      if (window.EB && EB.humanStatus) return EB.humanStatus(label);
      return String(label || '');
    }

    function statusCount(label){
      const key = normalizeStatus(label);
      const n = Number((state.facets && state.facets.statusCounts && state.facets.statusCounts[key]) || 0);
      return Number.isFinite(n) ? n : 0;
    }

    function hasActiveFilters(){
      return !!((state.username || '').trim() || (state.q || '').trim() || (state.statuses && state.statuses.length));
    }

    function renderFilterSummary(){
      if (!summaryEl) return;
      if (!hasActiveFilters()) {
        summaryEl.textContent = '';
        summaryEl.classList.add('hidden');
        return;
      }
      const parts = [];
      if ((state.username || '').trim()) parts.push(`User: ${state.username.trim()}`);
      if (state.statuses.length) parts.push(`Status: ${state.statuses.join(', ')}`);
      if ((state.q || '').trim()) parts.push(`Search: "${state.q.trim()}"`);
      summaryEl.textContent = `Filtering by ${parts.join(' + ')}`;
      summaryEl.classList.remove('hidden');
    }

    function renderStatusChips(){
      chipButtons.forEach((btn) => {
        const label = normalizeStatus(btn.getAttribute('data-status') || '');
        const active = state.statuses.includes(label);
        const count = statusCount(label);
        const disabled = count === 0;
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.disabled = disabled;
        btn.classList.remove('border-sky-500/60','bg-sky-500/10','text-sky-200','opacity-50','cursor-not-allowed');
        btn.classList.remove('border-slate-700/80','bg-slate-900/40','text-slate-300','hover:border-slate-600','hover:bg-slate-900/60');
        if (active) btn.classList.add('border-sky-500/60','bg-sky-500/10','text-sky-200');
        else btn.classList.add('border-slate-700/80','bg-slate-900/40','text-slate-300','hover:border-slate-600','hover:bg-slate-900/60');
        if (disabled) btn.classList.add('opacity-50','cursor-not-allowed');
        const countEl = btn.querySelector('[data-jobs-status-count]');
        if (countEl) countEl.textContent = String(count);
      });
      if (clearBtn) {
        clearBtn.classList.toggle('hidden', !hasActiveFilters());
      }
      renderFilterSummary();
    }

    function renderUsernameDropdown(){
      if (!usernameDropdown) return;
      const counts = state.facets.usernameCounts || {};
      const options = Object.keys(counts).sort();
      usernameDropdown.innerHTML = '';
      const empty = document.createElement('option');
      empty.value = '';
      empty.textContent = 'All users';
      usernameDropdown.appendChild(empty);
      for (const u of options) {
        const opt = document.createElement('option');
        opt.value = u;
        opt.textContent = `${u} (${counts[u] || 0})`;
        usernameDropdown.appendChild(opt);
      }
      usernameDropdown.value = state.username || '';

      if (usernameMenuLabel) {
        usernameMenuLabel.textContent = state.username || 'All users';
      }
      if (!usernameMenuList) return;

      usernameMenuList.innerHTML = '';
      const addMenuItem = (value, label) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        const active = (state.username || '') === value;
        btn.className = 'block w-full rounded-md px-3 py-2 text-left text-sm transition-colors ' +
          (active ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700/70 hover:text-white');
        btn.textContent = label;
        btn.addEventListener('click', () => {
          usernameDropdown.value = value;
          usernameDropdown.dispatchEvent(new Event('change', { bubbles: true }));
          window.dispatchEvent(new CustomEvent('jobs:username-selected'));
        });
        usernameMenuList.appendChild(btn);
      };

      addMenuItem('', 'All users');
      for (const u of options) {
        addMenuItem(u, `${u} (${counts[u] || 0})`);
      }
    }

    function renderEmptyRow(filtered){
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = colKeys.length;
      td.className = 'px-4 py-10 text-center text-sm text-slate-400';
      td.textContent = filtered ? 'No jobs match current filters.' : 'No jobs found.';
      tr.appendChild(td);
      tbody.appendChild(tr);
    }

    function renderSortIndicators(){
      if (!thead) return;
      thead.querySelectorAll('th[data-sort]').forEach((th) => {
        const indicator = th.querySelector('[data-sort-indicator]');
        if (!indicator) return;
        const key = th.getAttribute('data-sort');
        indicator.textContent = (state.sortBy === key) ? (state.sortDir === 'asc' ? '↑' : '↓') : '';
      });
    }

    function bindFilterControls(){
      chipButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          const label = normalizeStatus(btn.getAttribute('data-status') || '');
          if (!label || btn.disabled) return;
          const idx = state.statuses.indexOf(label);
          if (idx >= 0) state.statuses.splice(idx, 1);
          else state.statuses.push(label);
          state.page = 1;
          renderStatusChips();
          load();
        });
      });
      usernameDropdown && usernameDropdown.addEventListener('change', () => {
        state.username = (usernameDropdown.value || '').trim();
        if (usernameMenuLabel) usernameMenuLabel.textContent = state.username || 'All users';
        state.page = 1;
        load();
      });
      clearBtn && clearBtn.addEventListener('click', () => {
        state.statuses = [];
        state.q = '';
        state.username = '';
        state.page = 1;
        if (searchInput) searchInput.value = '';
        if (usernameDropdown) usernameDropdown.value = '';
        if (usernameMenuLabel) usernameMenuLabel.textContent = 'All users';
        renderStatusChips();
        load();
      });
    }

    async function load(){
      setLoading(true);
      try {
        const payload = {
          action: 'listJobsGlobal',
          username: state.username || undefined,
          statuses: state.statuses.slice(0),
          q: state.q,
          rangeHours: state.rangeHours,
          page: state.page,
          pageSize: state.pageSize,
          sortBy: state.sortBy,
          sortDir: state.sortDir
        };
        const res = await apiGlobal('listJobsGlobal', payload);
        if (!(res && res.status === 'success')) {
          try { window.showToast?.('Could not refresh jobs. Please try again.', 'error'); } catch(_) {}
          return;
        }
        state.facets = res.facets || { statusCounts: {}, usernameCounts: {} };
        totalEl && (totalEl.textContent = String(res.total || 0));
        tbody.innerHTML = '';
        const rows = Array.isArray(res.rows) ? res.rows : [];
        if (!rows.length) {
          renderEmptyRow(hasActiveFilters());
        }
        for (const r of rows) {
          const tr = document.createElement('tr');
          tr.className = 'hover:bg-slate-800/50 cursor-pointer';
          tr.setAttribute('data-job-id', r.JobID);
          const rowServiceId = (r.ServiceID != null ? r.ServiceID : r.ServiceId);
          tr.setAttribute('data-service-id', String(rowServiceId != null ? rowServiceId : (opts.serviceId || document.body.getAttribute('data-eb-serviceid') || '')));
          tr.setAttribute('data-username', String(r.Username || ''));
          const cells = [
            r.Username,
            r.JobID,
            r.Device,
            r.ProtectedItem,
            r.StorageVault,
            r.Version,
            r.Type,
            (window.EB && EB.humanStatus ? EB.humanStatus(r.Status || r.StatusCode) : (r.Status || '')),
            r.Directories,
            r.Files,
            (window.EB && EB.fmtBytes ? EB.fmtBytes(r.Size) : String(r.Size || 0)),
            (window.EB && EB.fmtBytes ? EB.fmtBytes(r.VaultSize) : String(r.VaultSize || 0)),
            (window.EB && EB.fmtBytes ? EB.fmtBytes(r.Uploaded) : String(r.Uploaded || 0)),
            (window.EB && EB.fmtBytes ? EB.fmtBytes(r.Downloaded) : String(r.Downloaded || 0)),
            (window.EB && EB.fmtTs ? EB.fmtTs(r.Started) : ''),
            (window.EB && EB.fmtTs ? EB.fmtTs(r.Ended) : ''),
            (window.EB && EB.fmtDur ? EB.fmtDur(r.Duration) : '')
          ];
          for (let i = 0; i < cells.length; i++) {
            const td = document.createElement('td');
            td.className = 'px-4 py-3 whitespace-nowrap text-sm';
            td.setAttribute('data-col', colKeys[i]);
            if (i === 7) {
              const label = cells[i];
              td.textContent = label;
              const dot = (window.EB && EB.statusDot ? EB.statusDot(label) : '');
              if (dot.indexOf('green') >= 0) td.classList.add('text-emerald-400');
              else if (dot.indexOf('sky') >= 0) td.classList.add('text-sky-400');
              else if (dot.indexOf('amber') >= 0) td.classList.add('text-amber-400');
              else if (dot.indexOf('red') >= 0) td.classList.add('text-rose-400');
              else if (label === 'Missed') td.classList.add('text-slate-300');
              else td.classList.add('text-slate-300');
            } else {
              td.classList.add('text-slate-300');
              td.textContent = cells[i] !== undefined ? String(cells[i]) : '';
            }
            tr.appendChild(td);
          }
          tbody.appendChild(tr);
        }
        renderPager(res.total || 0);
        renderSortIndicators();
        renderStatusChips();
        renderUsernameDropdown();
        syncColumnVisibility();
      } finally {
        setLoading(false);
      }
    }

    function renderPager(total){
      if (!pagerEl) return;
      const pages = Math.max(1, Math.ceil(total / state.pageSize));
      pagerEl.innerHTML = '';
      const mk = (label, page, disabled = false, current = false) => {
        const b = document.createElement('button');
        b.className = 'px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 text-xs text-slate-200' + (disabled ? ' opacity-50 cursor-not-allowed' : '');
        b.textContent = label;
        if (!disabled) b.addEventListener('click', () => { state.page = page; load(); });
        else b.disabled = true;
        return b;
      };
      pagerEl.appendChild(mk('Prev', Math.max(1, state.page - 1), state.page <= 1));
      const pageText = document.createElement('span');
      pageText.className = 'text-slate-300';
      pageText.textContent = `Page ${state.page} / ${pages}`;
      pagerEl.appendChild(pageText);
      pagerEl.appendChild(mk('Next', Math.min(pages, state.page + 1), state.page >= pages));
    }

    thead && thead.querySelectorAll('th[data-sort]').forEach((th) => {
      th.addEventListener('click', () => {
        const key = th.getAttribute('data-sort');
        if (state.sortBy === key) { state.sortDir = (state.sortDir === 'asc' ? 'desc' : 'asc'); } else { state.sortBy = key; state.sortDir = 'asc'; }
        renderSortIndicators();
        load();
      });
    });

    searchInput && searchInput.addEventListener('input', () => {
      if (searchDebounce) clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        state.q = searchInput.value || '';
        state.page = 1;
        renderStatusChips();
        load();
      }, 200);
    });

    window.addEventListener('jobs:pagesize', (e) => {
      const size = parseInt(e.detail, 10);
      if (size && [10, 25, 50, 100].includes(size)) {
        state.pageSize = size;
        state.page = 1;
        load();
      }
    });

    window.addEventListener('jobs:rangehours', (e) => {
      const hours = parseInt(e.detail, 10);
      if (hours && [24, 48, 60, 72].includes(hours)) {
        state.rangeHours = hours;
        state.page = 1;
        load();
      }
    });

    function isVisible(node){
      if (!node) return false;
      if (node.offsetParent !== null) return true;
      const cs = window.getComputedStyle(node);
      return cs && cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0';
    }

    function syncColumnVisibility(){
      if (!isVisible(el) || !thead || !isVisible(thead)) return;
      const ths = Array.from(thead.querySelectorAll('tr th'));
      const mapSortToKey = {
        Username: 'user', JobID: 'id', Device: 'device', ProtectedItem: 'item', StorageVault: 'vault', Version: 'ver',
        Type: 'type', Status: 'status', Directories: 'dirs', Files: 'files', Size: 'size', VaultSize: 'vsize',
        Uploaded: 'up', Downloaded: 'down', Started: 'started', Ended: 'ended', Duration: 'dur'
      };
      const vis = {};
      ths.forEach(th => {
        const sort = th.getAttribute('data-sort');
        const key = mapSortToKey[sort];
        if (!key) return;
        vis[key] = (th.offsetParent !== null);
      });
      const visKeys = Object.keys(vis);
      const allHidden = visKeys.length > 0 && visKeys.every(k => vis[k] === false);
      el.querySelectorAll('tbody tr').forEach(tr => {
        tr.querySelectorAll('td[data-col]').forEach(td => {
          const key = td.getAttribute('data-col');
          td.style.display = (allHidden || vis[key] !== false) ? '' : 'none';
        });
      });
    }

    (function observeHeaderToggles(){
      try {
        if (!thead) return;
        const obs = new MutationObserver(() => { syncColumnVisibility(); });
        obs.observe(thead, { attributes: true, subtree: true, attributeFilter: ['style', 'class'] });
      } catch(_) {}
    })();

    setTimeout(syncColumnVisibility, 50);
    setTimeout(syncColumnVisibility, 150);

    (function observeVisibility(){
      try {
        const io = new IntersectionObserver((entries) => {
          for (const entry of entries) {
            if (entry.isIntersecting) syncColumnVisibility();
          }
        }, { root: null, threshold: 0.01 });
        io.observe(el);
      } catch(_) {}
    })();

    bindFilterControls();
    renderStatusChips();
    renderUsernameDropdown();
    renderSortIndicators();
    return { reload: load };
  }

  // Expose factory
  window.jobReportsFactory = function(opts){
    attachModalControls();
    attachRowOpeners();
    return { makeJobsTable, makeGlobalJobsTable, openJobModal };
  };

  document.dispatchEvent(new Event('jobReports:ready'));
})();


