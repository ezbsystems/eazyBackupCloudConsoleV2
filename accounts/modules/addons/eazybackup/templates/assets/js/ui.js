;(function(){
  if (window.ebShowLoader && window.ebHideLoader) return;
  function ensureStyles(){
    // No-op: Tailwind classes used; keep hook if we need custom CSS later
  }
  function makeOverlay(message){
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-[12000] flex items-center justify-center bg-black/60';
    wrap.setAttribute('data-eb-loader', '1');
    var inner = '';
    inner += '<div class="flex flex-col items-center gap-3 px-6 py-4 rounded-lg border border-slate-700 bg-slate-900/90 shadow-xl">';
    inner += '<span class="inline-flex h-10 w-10 rounded-full border-2 border-sky-500 border-t-transparent animate-spin"></span>';
    if (message) inner += '<div class="text-slate-200 text-sm">' + String(message) + '</div>';
    inner += '</div>';
    wrap.innerHTML = inner;
    return wrap;
  }
  window.ebShowLoader = function(targetEl, message){
    try {
      ensureStyles();
      const host = targetEl || document.body;
      let overlay = host.querySelector(':scope > [data-eb-loader="1"]');
      if (!overlay) {
        overlay = makeOverlay(message);
        // Position relative container if needed
        const computed = getComputedStyle(host);
        if (computed.position === 'static') {
          host.classList.add('relative');
        }
        host.appendChild(overlay);
      } else if (message) {
        const msg = overlay.querySelector('div.text-slate-200');
        if (msg) msg.textContent = message;
      }
      return overlay;
    } catch (_) {}
  };
  window.ebHideLoader = function(targetEl){
    try {
      const host = targetEl || document.body;
      const overlay = host.querySelector(':scope > [data-eb-loader="1"]');
      if (overlay) overlay.remove();
    } catch (_) {}
  };
})();

// Minimal toast helper (shared)
;(function(){
  if (typeof window.showToast === 'function') return;
  window.showToast = function(message, type){
    try {
      var container = document.getElementById('toast-container');
      if (!container) return;
      var el = document.createElement('div');
      el.className = 'px-4 py-2 rounded shadow text-sm text-white ' + (type==='success'?'bg-green-600':(type==='error'?'bg-red-600':(type==='warning'?'bg-yellow-600':'bg-slate-700')));
      el.textContent = String(message||'');
      container.appendChild(el);
      setTimeout(function(){ try{ el.classList.add('opacity-0','transition-opacity','duration-700'); }catch(_){} }, 2200);
      setTimeout(function(){ try{ el.remove(); }catch(_){} }, 3000);
    } catch(_){ }
  }
})();

// Alpine retention factory (define early so x-data="retention()" is available before Alpine initializes)
try {
  window.retention = function(){
    return {
      state: { override:false, mode:802, ranges:[], defaultMode:801, defaultRanges:[] },
      showAccountPolicy: false,
      newRange: { Type:900, Jobs:1, Days:0, Weeks:0, Months:0, Years:0, Timestamp:0, WeekOffset:0, MonthOffset:1, YearOffset:1 },
      init: function(){
        // Keep a direct pointer to the active retention component for save-time fallback.
        try { window.__ebRetentionComponent = this; window.__ebRetentionState = this.state; } catch(_) {}
      },
      labelFor: function(t){ var m={900:'Most recent X jobs',901:'Newer than date',902:'Jobs since (relative)',903:'First job for last X days',905:'First job for last X months',906:'First job for last X weeks',907:'At most one per day (last X jobs)',908:'At most one per week (last X jobs)',909:'At most one per month (last X jobs)',910:'At most one per year (last X jobs)',911:'First job for last X years'}; return m[t]||('Type '+t); },
      summaryFor: function(r){
        if(!r||!r.Type) return '';
        var t=r.Type, badge='['+this.labelFor(t)+'] ';
        if(t===900) return badge + 'Keep the most recent '+(r.Jobs||1)+' backup jobs';
        if(t===907) return badge + 'Keep the last '+(r.Jobs||1)+' backups (max one per day)';
        if(t===908) return badge + 'Keep the last '+(r.Jobs||1)+' backups (max one per week)';
        if(t===909) return badge + 'Keep the last '+(r.Jobs||1)+' backups (max one per month)';
        if(t===910) return badge + 'Keep the last '+(r.Jobs||1)+' backups (max one per year)';
        if(t===901){ var ts=parseInt(r.Timestamp||0,10); var dt = ts? new Date(ts*1000).toISOString().slice(0,16).replace('T',' '):'set date'; return badge + 'Keep backups newer than '+dt; }
        if(t===902){ var d=r.Days||0,w=r.Weeks||0,m=r.Months||0,y=r.Years||0; var parts=[]; if(y) parts.push(y+' year'+(y>1?'s':'')); if(m) parts.push(m+' month'+(m>1?'s':'')); if(w) parts.push(w+' week'+(w>1?'s':'')); if(d) parts.push(d+' day'+(d>1?'s':'')); var txt = parts.length? ('the last '+parts.join(', ')) : 'a recent period'; return badge + 'Keep backups from '+txt; }
        if(t===903) return badge + 'Keep the first job for each of the last '+(r.Days||1)+' day(s)';
        if(t===905) return badge + 'Keep the first job for the last '+(r.Months||1)+' month(s) (offset '+(r.MonthOffset||0)+')';
        if(t===906) return badge + 'Keep the first job for the last '+(r.Weeks||1)+' week(s) (offset '+(r.WeekOffset||0)+')';
        if(t===911) return badge + 'Keep the first job for the last '+(r.Years||1)+' year(s) (offset '+(r.YearOffset||0)+')';
        return badge;
      },
      formattedPolicyLines: function(){ var out=[]; if(this.state.mode===801){ out.push('<li>[Mode] Keep everything (no deletions)</li>'); } else { (this.state.ranges||[]).forEach((r)=>{ out.push('<li>'+this.summaryFor(r).replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</li>'); }); } return out; },
      formattedDefaultPolicyLines: function(){ var out=[]; if(this.state.defaultMode===801){ out.push('<li>[Mode] Keep everything (no deletions)</li>'); } else { (this.state.defaultRanges||[]).forEach((r)=>{ out.push('<li>'+this.summaryFor(r).replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</li>'); }); } return out; },
      formattedEffectivePolicyLines: function(){ return (this.state.override ? this.formattedPolicyLines() : this.formattedDefaultPolicyLines()); },
      addRangeFromNew: function(){ var r=JSON.parse(JSON.stringify(this.newRange)); if((r.Type===900||r.Type===907||r.Type===908||r.Type===909||r.Type===910) && (!r.Jobs||r.Jobs<1)) r.Jobs=1; this.state.ranges.push(r); this.newRange={ Type:900, Jobs:1, Days:0, Weeks:0, Months:0, Years:0, Timestamp:0, WeekOffset:0, MonthOffset:1, YearOffset:1 }; },
      removeRange: function(i){ try{ this.state.ranges.splice(i,1); }catch(_){} }
    };
  };
} catch(_) {}

document.addEventListener('DOMContentLoaded', function(){
  // (retention() factory already defined above with default policy support)
  // Vault stats modal wiring
  const modal = document.getElementById('vault-stats-modal');
  if (!modal) return;
  const close = document.getElementById('vsm-close');
  close && close.addEventListener('click', () => modal.classList.add('hidden'));
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });

  var itemsJsonEl = document.getElementById('vsm-items-json');
  const itemsJson = (itemsJsonEl && itemsJsonEl.value) ? itemsJsonEl.value : '[]';
  let protectedItems = [];
  try { protectedItems = JSON.parse(itemsJson) || []; } catch(_) {}
  const idToName = new Map();
  (protectedItems || []).forEach(pi => {
    const id = (pi.id || pi.itemid || '').toUpperCase();
    if (id) idToName.set(id, pi.name || pi.description || pi.Description || id);
  });

  // Endpoint context (for fallback name lookup)
  const deviceEndpoint = window.EB_DEVICE_ENDPOINT || '';
  const svcId = document.body.getAttribute('data-eb-serviceid') || '';
  const uname = document.body.getAttribute('data-eb-username') || '';
  let fetchedAllNames = false;
  let fetchAllNamesPromise = null;
  async function fetchAllItemNames(){
    if (fetchedAllNames) return true;
    if (!deviceEndpoint || !svcId || !uname) return false;
    if (!fetchAllNamesPromise) {
      fetchAllNamesPromise = (async ()=>{
        try {
          const res = await fetch(deviceEndpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'listAllProtectedItems', serviceId: svcId, username: uname }) });
          const data = await res.json();
          if (data && data.status === 'success' && Array.isArray(data.items)) {
            data.items.forEach(it => {
              const id = (it.id || '').toUpperCase();
              if (id && !idToName.has(id)) idToName.set(id, it.name || id);
            });
            fetchedAllNames = true;
            return true;
          }
        } catch (_) {}
        return false;
      })();
    }
    return fetchAllNamesPromise;
  }

  function fmtBytes(n){
    var num = Number(n)||0;
    var units = ['B','KB','MB','GB','TB','PB'];
    var u = 0; var v = num;
    while (v >= 1024 && u < units.length-1){ v /= 1024; u++; }
    var prec = u === 0 ? 0 : (u <= 2 ? 2 : 2);
    return v.toFixed(prec) + ' ' + units[u];
  }
  function dt(ts){ try { var t=parseInt(ts,10); if(!t) return ''; var d=new Date(t*1000); return d.toISOString().replace('T',' ').slice(0,19); } catch(_){ return ''; } }
  function dur(a,b){ var s=Math.max(0, (parseInt(b,10)||0)-(parseInt(a,10)||0)); var h=Math.floor(s/3600), m=Math.floor((s%3600)/60), sec=s%60; return h>0?(h+':' + String(m).padStart(2,'0')):(m+':' + String(sec).padStart(2,'0')); }

  function openModal(fromBtn){
    try {
      var sidBtn = fromBtn.getAttribute('data-service-id')||'';
      var unBtn  = fromBtn.getAttribute('data-username')||'';
      if (sidBtn) document.body.setAttribute('data-eb-serviceid', sidBtn);
      if (unBtn)  document.body.setAttribute('data-eb-username', unBtn);
    } catch(_){}
    const vaultId = fromBtn.getAttribute('data-vault-id')||'';
    const vaultName = fromBtn.getAttribute('data-vault-name')||vaultId;
    const sizeBytes = parseInt(fromBtn.getAttribute('data-size-bytes')||'0',10);
    const ms = parseInt(fromBtn.getAttribute('data-measure-start')||'0',10);
    const me = parseInt(fromBtn.getAttribute('data-measure-end')||'0',10);
    // Components JSON is in sibling <script.eb-components>
    let components = [];
    try {
      const scr = fromBtn.parentElement.querySelector('script.eb-components');
      if (scr && scr.textContent) {
        let txt = scr.textContent.trim();
        if (txt.startsWith('&quot;') || txt.includes('&quot;')) { txt = txt.replace(/&quot;/g, '"'); }
        const raw = JSON.parse(txt);
        components = Array.isArray(raw) ? raw : [];
      }
    } catch(_){ components = []; }

    const list = Array.isArray(components) ? components : [];

    const currentBytes = (()=>{
      let sum = 0;
      (list || []).forEach(c => {
        if (!c || typeof c.Bytes !== 'number' || !Array.isArray(c.UsedBy)) return;
        if (c.UsedBy.some(u => (u||'').toUpperCase().startsWith('CURRENT/'))) sum += c.Bytes;
      });
      return sum;
    })();

    // Header summary
    const title = document.getElementById('vsm-title');
    title && (title.textContent = 'Vault usage – ' + vaultName);
    const summary = document.getElementById('vsm-summary');
    summary && (summary.textContent = 'Total size ' + fmtBytes(sizeBytes) + ' (' + fmtBytes(currentBytes) + ' in use by current data) measured at ' + dt(me) + ' (took ' + dur(ms,me) + ')');

    // Rows
    const rows = document.getElementById('vsm-rows');
    if (rows) {
      rows.innerHTML='';
      const missing = new Set();
      (list || [])
        .filter(c => c && typeof c.Bytes === 'number')
        .sort((a,b)=>b.Bytes - a.Bytes)
        .forEach(c => {
          const r = document.createElement('div');
          r.className = 'grid grid-cols-12 gap-0 px-3 py-2 text-sm text-slate-200';
          const left = document.createElement('div');
          left.className = 'col-span-4 font-mono';
          left.textContent = fmtBytes(c.Bytes);
          const right = document.createElement('div');
          right.className = 'col-span-8 space-y-0.5';
          (Array.isArray(c.UsedBy) ? c.UsedBy : []).forEach(u => {
            const txt = (u||'').toString();
            const parts = txt.split('/');
            const kind = (parts[0] || '').toUpperCase();
            const guid = (parts[1] || '').toUpperCase();
            let name = guid ? (idToName.get(guid) || '') : '';
            const div = document.createElement('div');
            div.dataset.guid = guid;
            div.dataset.kind = kind;
            if (!name && guid) { missing.add(guid); name = guid; }
            const nm = name ? '"' + name + '"' : '';
            div.textContent = [nm, kind].filter(Boolean).join(' ');
            right.appendChild(div);
          });
          r.appendChild(left); r.appendChild(right);
          rows.appendChild(r);
        });

      // If any names were missing, fetch mapping once and update
      if (missing.size > 0) {
        fetchAllItemNames().then(ok => {
          if (!ok) return;
          var nameNodes = rows.querySelectorAll('.col-span-8 > div[data-guid]');
          nameNodes && nameNodes.forEach(div => {
            const guid = (div.dataset.guid || '').toUpperCase();
            if (!guid) return;
            const nm = idToName.get(guid);
            if (nm) {
              const kind = (div.dataset.kind || '').toUpperCase();
              const nameQuoted = '"' + nm + '"';
              div.textContent = [nameQuoted, kind].filter(Boolean).join(' ');
            }
          });
        });
      }
    }

    modal.classList.remove('hidden');
  }

  document.querySelectorAll('.eb-stats-btn').forEach(btn => {
    btn.addEventListener('click', (e) => { e.preventDefault(); openModal(btn); });
  });

  // Vault slide-over wiring
  const vpanel = document.getElementById('vault-slide-panel');
  const vbackdrop = document.getElementById('vault-panel-backdrop');
  if (vpanel) {
    const vclose = document.getElementById('vault-panel-close');
    const idEl = document.getElementById('vault-mgr-id');
    const nameEl = document.getElementById('vault-mgr-name');
    const titleName = document.getElementById('vault-panel-name');
    const unlimitedEl = document.getElementById('vault-quota-unlimited2');
    const sizeEl = document.getElementById('vault-quota-size2');
    const unitEl = document.getElementById('vault-quota-unit2');
    const saveNameBtn = document.getElementById('vault-save-name');
    const saveQuotaBtn = document.getElementById('vault-save-quota');
    const saveAllBtn = document.getElementById('vault-save-all');
    const delBtn = document.getElementById('vault-delete');
    const delWrap = document.getElementById('vault-delete-confirm');
    const delCancel = document.getElementById('vault-delete-cancel');
    const delConfirm = document.getElementById('vault-delete-confirm-btn');
    const delPwd = document.getElementById('vault-delete-password');

    // Close panel function - uses Alpine.js events
    function closeVaultPanel() {
      window.dispatchEvent(new CustomEvent('vault-panel:close'));
    }
    // Open panel function - uses Alpine.js events
    function openVaultPanelView() {
      window.dispatchEvent(new CustomEvent('vault-panel:open'));
    }

    function openVaultPanel(btn){
      try {
        var sidBtn = btn.getAttribute('data-service-id')||'';
        var unBtn  = btn.getAttribute('data-username')||'';
        if (sidBtn) document.body.setAttribute('data-eb-serviceid', sidBtn);
        if (unBtn)  document.body.setAttribute('data-eb-username', unBtn);
      } catch(_){}
      const id = btn.getAttribute('data-vault-id')||'';
      const name = btn.getAttribute('data-vault-name')||id;
      const enabled = (btn.getAttribute('data-vault-quota-enabled') === '1' || btn.getAttribute('data-vault-quota-enabled') === 'true');
      const qbytes = parseInt(btn.getAttribute('data-vault-quota-bytes')||'0',10);
      idEl && (idEl.value = id);
      nameEl && (nameEl.value = name);
      titleName && (titleName.textContent = name);
      if (unlimitedEl) unlimitedEl.checked = !(enabled && qbytes>0);
      if (sizeEl && unitEl) {
        var pickedUnit = 'GB';
        if (enabled && qbytes>0) {
          if (qbytes >= (1024**4)) { sizeEl.value = Math.round(qbytes / (1024**4)); pickedUnit = 'TB'; }
          else { sizeEl.value = Math.round(qbytes / (1024**3)); pickedUnit = 'GB'; }
        } else { sizeEl.value=''; pickedUnit='GB'; }
        // Update hidden input value and the Alpine dropdown state so the label reflects the unit
        function applyUnit(u){
          try {
            unitEl.value = u;
            var wrapper = unitEl.parentElement;
            // Update Alpine component state when available
            var unitCmp = wrapper && wrapper.__x && wrapper.__x.$data;
            if (unitCmp) unitCmp.unit = u;
            // Update visible label immediately as a fallback
            var btn = wrapper && wrapper.querySelector('button');
            var label = btn && btn.querySelector('span');
            if (label) label.textContent = u;
          } catch(_){}
        }
        applyUnit(pickedUnit);
        // Retry a few times to win against late Alpine init or rerender
        (function retryApply(n){
          if (n <= 0) return;
          setTimeout(function(){ applyUnit(pickedUnit); retryApply(n-1); }, 60);
        })(4);
        sizeEl.disabled = unlimitedEl.checked; unitEl.disabled = unlimitedEl.checked;
        // Apply initial visual state for Unlimited
        try {
          const on = !!unlimitedEl.checked;
          if (on) {
            sizeEl.classList.remove('bg-slate-800');
            sizeEl.classList.add('bg-slate-900','opacity-60','cursor-not-allowed');
          } else {
            sizeEl.classList.remove('bg-slate-900','opacity-60','cursor-not-allowed');
            sizeEl.classList.add('bg-slate-800');
          }
          var unitBtn = unitEl.parentElement && unitEl.parentElement.querySelector('button');
          if (unitBtn) {
            unitBtn.disabled = on;
            if (on) {
              unitBtn.classList.add('opacity-60','cursor-not-allowed');
              unitBtn.classList.remove('bg-slate-800');
              unitBtn.classList.add('bg-slate-900');
            } else {
              unitBtn.classList.remove('opacity-60','cursor-not-allowed','bg-slate-900');
              unitBtn.classList.add('bg-slate-800');
            }
          }
        } catch(_){}
      }
      // Use Alpine.js event to open the panel
      openVaultPanelView();
      try { document.dispatchEvent(new CustomEvent('vault:open', { detail: { id, name } })); } catch(_) {}
    }
    // Note: Close button and backdrop clicks are now handled by Alpine.js in the template
    unlimitedEl && unlimitedEl.addEventListener('change', ()=>{
      if (sizeEl && unitEl) {
        const on = !!unlimitedEl.checked;
        sizeEl.disabled = on; unitEl.disabled = on;
        // style darken
        if (on) {
          sizeEl.classList.remove('bg-slate-800'); sizeEl.classList.add('bg-slate-900','opacity-60','cursor-not-allowed');
        } else {
          sizeEl.classList.remove('bg-slate-900','opacity-60','cursor-not-allowed'); sizeEl.classList.add('bg-slate-800');
        }
        // disable unit dropdown button inside Alpine widget
        try {
          var btn = unitEl.parentElement && unitEl.parentElement.querySelector('button');
          if (btn) {
            btn.disabled = on;
            if (on) {
              btn.classList.add('opacity-60','cursor-not-allowed');
              btn.classList.remove('bg-slate-800');
              btn.classList.add('bg-slate-900');
            } else {
              btn.classList.remove('opacity-60','cursor-not-allowed','bg-slate-900');
              btn.classList.add('bg-slate-800');
            }
          }
        } catch(_){}
        if (on) { sizeEl.value=''; }
      }
    });
    // Use event delegation for vault panel buttons so dynamically created buttons also work
    document.querySelectorAll('.open-vault-panel').forEach(btn => btn.addEventListener('click', (e)=>{ e.preventDefault(); openVaultPanel(btn); }));
    // Also handle configure-vault-button clicks (the edit pencil in quota column) with event delegation
    document.addEventListener('click', (e) => {
      const configBtn = e.target.closest('.configure-vault-button');
      if (configBtn) {
        e.preventDefault();
        openVaultPanel(configBtn);
      }
    });

    async function callVault(action, extra={}){
      try {
        const endpoint = (window.EB_DEVICE_ENDPOINT || '').replace('&a=device-actions','&a=api');
        const modulelink = vpanel.getAttribute('data-modulelink') || (window.EB_DEVICE_ENDPOINT || '');
        const url = modulelink.includes('&a=') ? modulelink : (window.EB_DEVICE_ENDPOINT || '');
        const postUrl = url.replace('&a=device-actions','&a=api');
        const body = Object.assign({ action, serviceId: document.body.getAttribute('data-eb-serviceid'), username: document.body.getAttribute('data-eb-username') }, extra);
        const res = await fetch(postUrl, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
        return res.json();
      } catch (e) { return { status:'error', message: e.message || 'Request failed' }; }
    }

    function emitVaultToast(type, message){
      try {
        window.dispatchEvent(new CustomEvent('vault:toast', { detail: { type: String(type || 'info'), message: String(message || '') } }));
      } catch(_) {}
    }

    saveAllBtn && saveAllBtn.addEventListener('click', async ()=>{
      const id = (idEl && idEl.value) ? idEl.value : '';
      const newName = (nameEl && nameEl.value ? nameEl.value : '').trim();
      const unlimited = !!(unlimitedEl && unlimitedEl.checked);
      const size = parseFloat((sizeEl && sizeEl.value) ? sizeEl.value : '0');
      const unit = (unitEl && unitEl.value) ? unitEl.value : 'GB';
      const quota = unlimited ? { unlimited:true } : { unlimited:false, size:size, unit:unit };
      // Retention save guard: warn on KEEP_EVERYTHING
      try {
        var el = document.getElementById('vault-retention-tab');
        if (el && el.__x) {
          var cmp = el.__x.$data;
          if (cmp && cmp.state && cmp.state.override && Number(cmp.state.mode) === 801) {
            var ok = window.confirm('“Keep everything” keeps all backups forever. Your storage bill may increase indefinitely.\n\nProceed?');
            if (!ok) return;
          }
        }
      } catch(_){}
      if (!id) { emitVaultToast('error', 'Missing vault id.'); return; }
      if (!newName) { emitVaultToast('warning', 'Enter a vault name.'); return; }
      const r = await callVault('updateVault', { vaultId:id, vaultName:newName, vaultQuota:quota });
      emitVaultToast((r && r.status==='success') ? 'success' : 'error', (r && r.message) ? r.message : ((r && r.status==='success') ? 'Changes saved.' : 'Save failed.'));
      if (r.status==='success') {
        titleName.textContent = newName;
        // Calculate new quota bytes
        let newQuotaBytes = 0;
        if (!unlimited) {
          newQuotaBytes = unit==='TB' ? Math.round(size*(1024**4)) : Math.round(size*(1024**3));
        }
        const quotaEnabled = !unlimited && newQuotaBytes > 0;
        
        // Update the manage button data attributes
        const btn = document.querySelector('.open-vault-panel[data-vault-id="' + id + '"]');
        if (btn) {
          btn.setAttribute('data-vault-name', newName);
          btn.setAttribute('data-vault-quota-enabled', quotaEnabled.toString());
          btn.setAttribute('data-vault-quota-bytes', String(newQuotaBytes));
        }
        
        // Update the table row dynamically
        updateVaultTableRow(id, newName, quotaEnabled, newQuotaBytes);
        
        // Update the billing summary card
        updateBillingSummary();
      }
    });
    
    // Helper function to format bytes to human-readable size
    function formatBytes(bytes, decimals = 2) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + sizes[i];
    }
    
    // Update a single vault row in the table
    function updateVaultTableRow(vaultId, newName, quotaEnabled, quotaBytes) {
      const esc = (str) => (window.CSS && CSS.escape ? CSS.escape(str) : str);
      let targetRow = document.querySelector(`tr[data-vault-id="${esc(vaultId)}"]`);
      if (!targetRow) {
        const btn = document.querySelector(`.open-vault-panel[data-vault-id="${esc(vaultId)}"]`);
        if (btn) targetRow = btn.closest('tr');
      }
      if (!targetRow) return;

      const usedBytes = parseInt(targetRow.getAttribute('data-used-bytes') || '0', 10);
      const acct = targetRow.getAttribute('data-account') || targetRow.getAttribute('data-acct') || '';
      const serviceId = targetRow.getAttribute('data-service-id') || '';
      const username = targetRow.getAttribute('data-username') || '';

      // Update data attributes
      targetRow.setAttribute('data-quota-bytes', String(quotaBytes));
      targetRow.setAttribute('data-name', newName);

      // Update the vault name cell (best-effort)
      const nameCell = targetRow.querySelector('td[x-show="cols.name"]') || targetRow.querySelector('td:nth-child(2)');
      if (nameCell) nameCell.textContent = newName;

      // Update the quota cell
      const quotaCell = targetRow.querySelector('[data-cell="quota"]') || targetRow.querySelector('td[x-show="cols.quota"]');
      if (quotaCell) {
        if (!quotaEnabled || quotaBytes === 0) {
          quotaCell.innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300">Unlimited</span>';
        } else {
          const quotaFormatted = formatBytes(quotaBytes, 2);
          quotaCell.innerHTML = `
            <div class="flex flex-col gap-1">
              <span class="inline-flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-200" title="Exact quota: ${quotaFormatted}">${quotaFormatted}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-900/40 text-emerald-300">On</span>
                <button type="button" class="configure-vault-button ml-1 p-1.5 rounded hover:bg-slate-700 text-slate-300"
                    title="Edit quota"
                    data-vault-id="${vaultId}"
                    data-vault-name="${newName}"
                    data-vault-quota-enabled="true"
                    data-vault-quota-bytes="${quotaBytes}"
                    data-service-id="${serviceId}"
                    data-username="${username}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232a2.5 2.5 0 113.536 3.536L7.5 20.036 3 21l.964-4.5L15.232 5.232z"/></svg>
                </button>
              </span>
              <span class="text-[10px] text-slate-500">Per-vault limit</span>
            </div>`;
        }
      }

      // Update the usage cell
      const usageCell = targetRow.querySelector('[data-cell="usage"]') || targetRow.querySelector('td[x-show="cols.usage"]');
      if (usageCell) {
        if (!quotaEnabled || quotaBytes === 0) {
          usageCell.innerHTML = `
            <div class="w-56">
              <div class="h-2.5 w-full rounded bg-slate-800/70 overflow-hidden">
                <div class="h-full w-1/3 bg-gradient-to-r from-slate-600/40 via-slate-500/40 to-slate-600/40 animate-pulse"></div>
              </div>
              <div class="mt-1 text-xs text-slate-500">Usage unavailable (no quota)</div>
            </div>`;
        } else {
          let pct = (usedBytes / quotaBytes) * 100;
          if (pct > 100) pct = 100;
          if (pct < 0) pct = 0;
          const pctColor = pct < 70 ? 'bg-emerald-500' : (pct < 90 ? 'bg-amber-500' : 'bg-rose-500');
          usageCell.innerHTML = `
            <div class="w-56">
              <div class="h-2.5 w-full rounded bg-slate-800/70 overflow-hidden" title="${formatBytes(usedBytes, 2)} of ${formatBytes(quotaBytes, 2)} (${pct.toFixed(1)}%)">
                <div class="h-full transition-[width] duration-500 ${pctColor}" style="width: ${pct}%;"></div>
              </div>
              <div class="mt-1 text-xs text-slate-400">${formatBytes(usedBytes, 2)} / ${formatBytes(quotaBytes, 2)} (${pct.toFixed(1)}%)</div>
            </div>`;
        }
      }

      // Update the manage button attributes
      const manageBtn = targetRow.querySelector('.open-vault-panel[data-vault-id="' + vaultId + '"]');
      if (manageBtn) {
        manageBtn.setAttribute('data-vault-name', newName);
        manageBtn.setAttribute('data-vault-quota-enabled', quotaEnabled.toString());
        manageBtn.setAttribute('data-vault-quota-bytes', String(quotaBytes));
        if (serviceId) manageBtn.setAttribute('data-service-id', serviceId);
        if (username) manageBtn.setAttribute('data-username', username);
      }

      // Update account grouping totals if present
      if (acct) {
        updateAccountGroup(acct);
      }
    }

    // Update per-account header & summary rows (aggregated view)
    function updateAccountGroup(acct) {
      if (!acct) return;
      const rows = document.querySelectorAll(`tr[data-account="${acct}"]`);
      if (!rows.length) return;
      let totalUsedBytes = 0;
      let totalQuotaBytes = 0;
      let count = 0;
      rows.forEach(r => {
        count++;
        const used = parseInt(r.getAttribute('data-used-bytes') || '0', 10);
        const quota = parseInt(r.getAttribute('data-quota-bytes') || '0', 10);
        totalUsedBytes += used;
        if (quota > 0) totalQuotaBytes += quota;
      });
      const tbBytes = 1024 * 1024 * 1024 * 1024;
      const billableTB = totalQuotaBytes > 0 ? Math.ceil(totalQuotaBytes / tbBytes) : 0;
      const billableText = billableTB > 0 ? `${billableTB} TB` : '—';

      // Flat table view: keep every vault row's Billing cell in sync for this account.
      rows.forEach(r => {
        const billingCell = r.querySelector('td[x-show="cols.billing"]');
        if (billingCell) billingCell.textContent = billableText;
      });

      const header = document.querySelector(`tr[data-account-header="${acct}"]`);
      if (header) {
        header.setAttribute('data-total-quota-bytes', String(totalQuotaBytes));
        header.setAttribute('data-total-used-bytes', String(totalUsedBytes));
        const quotaSpan = header.querySelector('.acct-total-quota');
        if (quotaSpan) quotaSpan.textContent = `Total Quota: ${formatBytes(totalQuotaBytes, 2)}`;
        const billableSpan = header.querySelector('.acct-billable');
        if (billableSpan) billableSpan.textContent = `Billable: ${billableText}`;
        const badge = header.querySelector('.acct-count-badge');
        if (badge) badge.textContent = `${count} vault${count !== 1 ? 's' : ''}`;
      }

      const summary = document.querySelector(`tr[data-account-summary="${acct}"]`);
      if (summary) {
        const usedEl = summary.querySelector('.acct-summary-used');
        if (usedEl) usedEl.textContent = `Total Used: ${formatBytes(totalUsedBytes, 2)}`;
        const quotaEl = summary.querySelector('.acct-summary-quota');
        if (quotaEl) quotaEl.textContent = `Total Quota: ${formatBytes(totalQuotaBytes, 2)}`;
        const billableEl = summary.querySelector('.acct-summary-billable');
        if (billableEl) billableEl.textContent = `Billable: ${billableText}`;
      }
    }
    
    // Update the billing summary card (per-user view)
    function updateBillingSummary() {
      // Recalculate totals from all vault rows
      const allRows = document.querySelectorAll('tr[data-used-bytes][data-quota-bytes]');
      let totalUsedBytes = 0;
      let totalQuotaBytes = 0;
      let vaultCount = 0;
      let quotaEnabledCount = 0;
      
      allRows.forEach(row => {
        vaultCount++;
        const usedBytes = parseInt(row.getAttribute('data-used-bytes') || '0', 10);
        const quotaBytes = parseInt(row.getAttribute('data-quota-bytes') || '0', 10);
        totalUsedBytes += usedBytes;
        if (quotaBytes > 0) {
          totalQuotaBytes += quotaBytes;
          quotaEnabledCount++;
        }
      });
      
      // Calculate billable TB tier (round up to nearest 1TB)
      const tbBytes = 1024 * 1024 * 1024 * 1024; // 1TB in bytes
      const billableTB = totalQuotaBytes > 0 ? Math.ceil(totalQuotaBytes / tbBytes) : 0;
      const billableText = billableTB > 0 ? `${billableTB} TB` : '—';

      // Single-user storage table: repeat account-level billable on each vault row.
      // Skip this in multi-account views, where Billing is updated per account group.
      const hasAccountScopedRows = !!document.querySelector('tr[data-account]');
      if (!hasAccountScopedRows) {
        document.querySelectorAll('tr[data-used-bytes][data-quota-bytes] td[x-show="cols.billing"]').forEach(cell => {
          cell.textContent = billableText;
        });
      }
      
      // Update summary card elements
      const summaryCard = document.querySelector('.bg-gradient-to-r.from-slate-800\\/80');
      if (summaryCard) {
        // Update vault count text
        const vaultCountEl = summaryCard.querySelector('p.text-xs.text-slate-400');
        if (vaultCountEl) {
          vaultCountEl.textContent = `${vaultCount} vault${vaultCount !== 1 ? 's' : ''}${quotaEnabledCount > 0 ? `, ${quotaEnabledCount} with quota enabled` : ''}`;
        }
        
        // Update Total Used
        const totalUsedEl = summaryCard.querySelectorAll('.flex.flex-col span.text-slate-200')[0];
        if (totalUsedEl) totalUsedEl.textContent = formatBytes(totalUsedBytes, 2);
        
        // Update Total Quota
        const totalQuotaEl = summaryCard.querySelectorAll('.flex.flex-col span.text-slate-200')[1];
        if (totalQuotaEl) {
          totalQuotaEl.innerHTML = totalQuotaBytes > 0 
            ? formatBytes(totalQuotaBytes, 2) 
            : '<span class="text-slate-400">No quotas set</span>';
        }
        
        // Update Billable Tier
        const billableEl = summaryCard.querySelector('.text-emerald-400.font-bold');
        if (billableEl) {
          billableEl.innerHTML = billableTB > 0 
            ? `${billableTB} TB` 
            : '<span class="text-slate-400 text-sm font-normal">—</span>';
        }
        
        // Update the explanation text
        const explanationDiv = summaryCard.querySelector('.mt-3.pt-3.border-t');
        if (explanationDiv) {
          if (totalQuotaBytes > 0) {
            explanationDiv.classList.remove('hidden');
            explanationDiv.style.display = '';
            const explanationP = explanationDiv.querySelector('p');
            if (explanationP) {
              explanationP.innerHTML = `
                <svg class="inline h-3.5 w-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Your total quota across all vaults is <strong class="text-slate-300">${formatBytes(totalQuotaBytes, 2)}</strong>. 
                Billing is calculated by summing all vault quotas, then rounding up to the nearest 1TB tier 
                (<strong class="text-emerald-400">${billableTB} TB</strong>).
              `;
            }
          } else {
            explanationDiv.style.display = 'none';
          }
        }
      }
    }

    delBtn && delBtn.addEventListener('click', ()=>{ if (delWrap) delWrap.classList.toggle('hidden'); });
    delCancel && delCancel.addEventListener('click', ()=>{ if (delWrap) delWrap.classList.add('hidden'); if (delPwd) delPwd.value=''; });
    delConfirm && delConfirm.addEventListener('click', async ()=>{
      const id = (idEl && idEl.value) ? idEl.value : '';
      const pwd = ((delPwd && delPwd.value) ? delPwd.value : '').trim();
      if (!pwd) { try{ if(window.showToast) window.showToast('Enter your account password.','warning'); }catch(_){} return; }
      const r = await callVault('deleteVault', { vaultId:id, password:pwd });
      try{ if(window.showToast) window.showToast(r.message || (r.status==='success'?'Vault deleted.':'Delete failed'), r.status==='success'?'success':'error'); }catch(_){}
      if (r.status==='success') { setTimeout(()=>window.location.reload(), 800); }
    });
  }

  // Simple client-side sort for vaults table
  try {
    var tbl = document.getElementById('vaults-table');
    if (tbl) {
      var thead = tbl.querySelector('thead');
      var tbody = tbl.querySelector('tbody');
      var sortState = { key: '', dir: 1 };
      function getVal(tr, key){
        var v = tr.getAttribute('data-' + key) || '';
        if (key === 'stored' || key === 'usage' || key === 'quota') {
          if (key === 'stored') return parseFloat(tr.getAttribute('data-stored-bytes')||'0');
          if (key === 'quota') return parseFloat(tr.getAttribute('data-quota-bytes')||'0');
          if (key === 'usage') return parseFloat(tr.getAttribute('data-usage-pct')||'0');
        }
        if (key === 'init') return parseInt(tr.getAttribute('data-init-ts')||'0',10)||0;
        if (key === 'type') return (tr.getAttribute('data-type')||'').toString();
        if (key === 'id') return (tr.getAttribute('data-id')||'').toString();
        if (key === 'name') return (tr.getAttribute('data-name')||'').toString().toLowerCase();
        if (key === 'acct') return (tr.getAttribute('data-acct')||tr.getAttribute('data-account')||'').toString().toLowerCase();
        return v;
      }
      function applySort(key){
        if (sortState.key === key) sortState.dir = -sortState.dir; else { sortState.key = key; sortState.dir = 1; }
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function(a,b){
          var va = getVal(a, key); var vb = getVal(b, key);
          if (typeof va === 'number' || typeof vb === 'number') {
            va = Number(va)||0; vb = Number(vb)||0; return (va - vb) * sortState.dir;
          } else {
            va = String(va); vb = String(vb); return va.localeCompare(vb) * sortState.dir;
          }
        });
        // Re-append in sorted order
        rows.forEach(function(r){ tbody.appendChild(r); });
        // Visual indicator (toggle caret) minimal: add data-order attr
        try {
          thead.querySelectorAll('[data-sort]').forEach(function(th){ th.removeAttribute('data-order'); });
          var th = thead.querySelector('[data-sort="'+key+'"]'); if (th) th.setAttribute('data-order', sortState.dir>0?'asc':'desc');
        } catch(_){ }
      }
      thead && thead.querySelectorAll('[data-sort]').forEach(function(th){
        th.addEventListener('click', function(){ applySort(th.getAttribute('data-sort')); });
      });
    }
  } catch(_){ }

  // Retention builder (plain JS)
  function initRetentionBuilder() {
    // endpoint will be resolved per-call; keep fallback here
    var modulelinkFallback = (window.EB_DEVICE_ENDPOINT || '');

    const MODES = { KEEP_EVERYTHING:801, DELETE_EXCEPT:802 };
    const TYPES = {
      MOST_RECENT_X_JOBS:900,
      NEWER_THAN_X:901,
      JOBS_SINCE:902,
      FIRST_JOB_FOR_EACH_LAST_X_DAYS:903,
      FIRST_JOB_FOR_LAST_X_MONTHS:905,
      FIRST_JOB_FOR_LAST_X_WEEKS:906,
      LAST_X_BACKUPS_EACH_DAY:907,
      LAST_X_BACKUPS_EACH_WEEK:908,
      LAST_X_BACKUPS_EACH_MONTH:909,
      LAST_X_BACKUPS_EACH_YEAR:910,
      FIRST_JOB_FOR_LAST_X_YEARS:911,
    };
    const TYPE_FIELDS = {
      900:['Jobs'],
      901:['Timestamp'],
      902:['Days','Weeks','Months','Years','WeekOffset','MonthOffset','YearOffset'],
      903:['Days'],
      905:['Months','MonthOffset'],
      906:['Weeks','WeekOffset'],
      907:['Jobs'],
      908:['Jobs'],
      909:['Jobs'],
      910:['Jobs'],
      911:['Years','YearOffset']
    };

    async function api(action, payload){
      // Resolve endpoint
      var postUrl = (window.EB_DEVICE_ENDPOINT || modulelinkFallback || '');
      // Resolve serviceId/username at call-time
      var sid = document.body.getAttribute('data-eb-serviceid') || '';
      var un  = document.body.getAttribute('data-eb-username') || '';
      if (!sid || !un) {
        if (typeof window.EB_SERVICE_ID !== 'undefined' && window.EB_SERVICE_ID) sid = String(window.EB_SERVICE_ID);
        if (typeof window.EB_USERNAME !== 'undefined' && window.EB_USERNAME) un = String(window.EB_USERNAME);
      }
      const body = Object.assign({ action: action, serviceId: sid, username: un }, (payload||{}));
      const res = await fetch(postUrl, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
      return res.json();
    }

    function emitRetentionToast(type, message){
      try {
        window.dispatchEvent(new CustomEvent('retention:toast', { detail: { type: String(type || 'info'), message: String(message || '') } }));
      } catch(_) {}
    }

    function syncRetentionStateFromAlpine(){
      try {
        var elRT = document.getElementById('vault-retention-tab');
        var st = null;
        // Alpine v2 internal shape
        try { st = (elRT && elRT.__x && elRT.__x.$data && elRT.__x.$data.state) ? elRT.__x.$data.state : null; } catch(_) {}
        // Alpine v3 public helper (if available)
        if (!st) {
          try {
            if (elRT && window.Alpine && typeof window.Alpine.$data === 'function') {
              var d = window.Alpine.$data(elRT);
              if (d && d.state) st = d.state;
            }
          } catch(_) {}
        }
        // Alpine v3 internal stack shape
        if (!st) {
          try {
            if (elRT && Array.isArray(elRT._x_dataStack)) {
              for (var i = 0; i < elRT._x_dataStack.length; i++) {
                var ds = elRT._x_dataStack[i];
                if (ds && ds.state) { st = ds.state; break; }
              }
            }
          } catch(_) {}
        }
        // Last-resort pointer captured in component init()
        if (!st) {
          try {
            if (window.__ebRetentionComponent && window.__ebRetentionComponent.state) st = window.__ebRetentionComponent.state;
            else if (window.__ebRetentionState) st = window.__ebRetentionState;
          } catch(_) {}
        }
        if (!st) return;
        override = !!st.override;
        mode = (Number(st.mode) === MODES.DELETE_EXCEPT) ? MODES.DELETE_EXCEPT : MODES.KEEP_EVERYTHING;
        ranges = Array.isArray(st.ranges) ? JSON.parse(JSON.stringify(st.ranges)) : [];
      } catch(_) {}
    }

    async function saveRetentionPolicy(){
      if (!currentVaultId) return false;
      // The retention editor is Alpine-driven; sync live UI state before building payload.
      syncRetentionStateFromAlpine();
      const payloadRanges = (override && mode===MODES.DELETE_EXCEPT ? (ranges||[]).map(r=>clampRange(Object.assign({},r))) : []);
      const r = await api('setVaultRetention', {
        vaultId: currentVaultId,
        override: !!override,
        mode: mode,
        ranges: payloadRanges,
        hash: currentHash
      });
      if (r && r.status === 'success') {
        currentHash = r.hash || currentHash;
        try {
          const v = (currentProfile && currentProfile.Destinations && currentProfile.Destinations[currentVaultId]) ? currentProfile.Destinations[currentVaultId] : null;
          if (v) {
            if (!override) { if ('RetentionPolicy' in v) delete v.RetentionPolicy; }
            else { v.RetentionPolicy = { Mode: mode, Ranges: payloadRanges }; }
          }
        } catch(_) {}
        emitRetentionToast('success', 'Retention saved.');
        return true;
      }
      if (r && r.code === 'hash_mismatch') {
        emitRetentionToast('warning', 'Profile changed on server; reloaded.');
        const rr = await api('getUserProfile', {});
        if (rr && rr.status === 'success') { currentProfile = rr.profile; currentHash = rr.hash; }
        return false;
      }
      emitRetentionToast('error', (r && r.message) ? r.message : 'Failed to save retention.');
      return false;
    }

    function fmtPolicy(policy){
      if (!policy || !policy.Mode) return ['No retention policy'];
      if (policy.Mode === MODES.KEEP_EVERYTHING) return ['Keep everything'];
      const out = ['Delete everything except:'];
      const ranges = Array.isArray(policy.Ranges)?policy.Ranges:[];
      ranges.forEach(r => {
        const t = r.Type;
        if (t===TYPES.MOST_RECENT_X_JOBS) out.push('- Most recent ' + (r.Jobs||1) + ' jobs');
        else if (t===TYPES.NEWER_THAN_X) out.push('- Newer than ' + new Date((r.Timestamp||0)*1000).toISOString().slice(0,19).replace('T',' '));
        else if (t===TYPES.JOBS_SINCE) out.push('- Jobs since Days='+(r.Days||0)+' Weeks='+(r.Weeks||0)+' Months='+(r.Months||0)+' Years='+(r.Years||0));
        else if (t===TYPES.FIRST_JOB_FOR_EACH_LAST_X_DAYS) out.push('- First job for each of the last ' + (r.Days||1) + ' days');
        else if (t===TYPES.FIRST_JOB_FOR_LAST_X_MONTHS) out.push('- First job for last ' + (r.Months||1) + ' months (offset ' + (r.MonthOffset||0) + ')');
        else if (t===TYPES.FIRST_JOB_FOR_LAST_X_WEEKS) out.push('- First job for last ' + (r.Weeks||1) + ' weeks (offset ' + (r.WeekOffset||0) + ')');
        else if (t===TYPES.LAST_X_BACKUPS_EACH_DAY) out.push('- Last ' + (r.Jobs||1) + ' backups (one per day)');
        else if (t===TYPES.LAST_X_BACKUPS_EACH_WEEK) out.push('- Last ' + (r.Jobs||1) + ' backups (one per week)');
        else if (t===TYPES.LAST_X_BACKUPS_EACH_MONTH) out.push('- Last ' + (r.Jobs||1) + ' backups (one per month)');
        else if (t===TYPES.LAST_X_BACKUPS_EACH_YEAR) out.push('- Last ' + (r.Jobs||1) + ' backups (one per year)');
        else if (t===TYPES.FIRST_JOB_FOR_LAST_X_YEARS) out.push('- First job for last ' + (r.Years||1) + ' years (offset ' + (r.YearOffset||0) + ')');
      });
      return out;
    }

    let currentProfile = null;
    let currentHash = '';
    let currentVaultId = '';
    let defaultPolicy = null;
    let vaultPolicy = null;
    let override = false;
    let mode = MODES.KEEP_EVERYTHING;
    let ranges = [];

    document.addEventListener('vault:open', async (ev)=>{
      currentVaultId = (ev && ev.detail && ev.detail.id) ? ev.detail.id : '';
      try {
        const r = await api('getUserProfile', {});
        if (r && r.status==='success' && r.profile && r.hash) {
          currentProfile = r.profile; currentHash = r.hash;
          const v = (currentProfile.Destinations && currentProfile.Destinations[currentVaultId]) ? currentProfile.Destinations[currentVaultId] : null;
          defaultPolicy = ((v && v.DefaultRetention) ? v.DefaultRetention : (currentProfile.DefaultRetention || null));
          vaultPolicy = (v && v.RetentionPolicy) ? v.RetentionPolicy : null;
          override = !!(vaultPolicy);
          const effective = override ? vaultPolicy : defaultPolicy;
          mode = (effective && typeof effective.Mode !== 'undefined') ? effective.Mode : MODES.KEEP_EVERYTHING;
          ranges = (effective && Array.isArray(effective.Ranges)) ? JSON.parse(JSON.stringify(effective.Ranges)) : [];
          // notify Alpine template
          try {
            var defMode = (defaultPolicy && typeof defaultPolicy.Mode !== 'undefined') ? defaultPolicy.Mode : MODES.KEEP_EVERYTHING;
            var defRanges = (defaultPolicy && Array.isArray(defaultPolicy.Ranges)) ? JSON.parse(JSON.stringify(defaultPolicy.Ranges)) : [];
            var elRT = document.getElementById('vault-retention-tab');
            if (elRT && elRT.__x && elRT.__x.$data && elRT.__x.$data.state) {
              try {
                elRT.__x.$data.state.override = override;
                elRT.__x.$data.state.mode = mode;
                elRT.__x.$data.state.ranges = JSON.parse(JSON.stringify(ranges || []));
                elRT.__x.$data.state.defaultMode = defMode;
                elRT.__x.$data.state.defaultRanges = JSON.parse(JSON.stringify(defRanges || []));
              } catch (_) {}
            }
            window.dispatchEvent(new CustomEvent('retention:update', { detail:{ override:override, mode:mode, ranges:ranges, defaultMode:defMode, defaultRanges:defRanges } }));
            // Fallback: immediately render Effective policy text when override is OFF
            if (!override) {
              try {
                var ul = document.querySelector('#vault-retention-tab .sticky ul');
                if (ul) {
                  var lines = fmtPolicy(defaultPolicy || { Mode:defMode, Ranges:defRanges });
                  var html = '';
                  for (var i=0;i<lines.length;i++) { html += '<li>' + String(lines[i]) + '</li>'; }
                  ul.innerHTML = html;
                }
              } catch(_) {}
            }
          } catch(_){ }
          // Alpine-driven UI; no imperative render
        } else {
          try{ if(window.showToast) window.showToast((r && r.message) ? r.message : 'Failed to load profile','error'); }catch(_){}
        }
      } catch (e) { try{ if(window.showToast) window.showToast('Failed to load profile','error'); }catch(_){} }
    });

    function clampRange(r){
      if ('Jobs' in r) r.Jobs = Math.max(1, parseInt(r.Jobs||'1',10));
      if ('Days' in r) r.Days = Math.max(1, parseInt(r.Days||'1',10));
      if ('Weeks' in r) r.Weeks = Math.max(1, parseInt(r.Weeks||'1',10));
      if ('Months' in r) r.Months = Math.max(1, parseInt(r.Months||'1',10));
      if ('Years' in r) r.Years = Math.max(1, parseInt(r.Years||'1',10));
      if ('WeekOffset' in r) r.WeekOffset = Math.max(0, Math.min(6, parseInt(r.WeekOffset||'0',10)));
      if ('MonthOffset' in r) r.MonthOffset = Math.max(1, Math.min(31, parseInt(r.MonthOffset||'1',10)));
      if ('YearOffset' in r) r.YearOffset = Math.max(0, parseInt(r.YearOffset||'0',10));
      if ('Timestamp' in r) r.Timestamp = Math.max(0, parseInt(r.Timestamp||'0',10));
      return r;
    }

    function renderRetention(){
      const root = document.querySelector('#vault-slide-panel [x-data]');
      const tabDanger = document.getElementById('vault-retention-tab'); // not used, keep for parity
      const tabRet = document.getElementById('vault-retention-tab');
      if (!tabRet) return;
      // Build retention content (DOM API to avoid template parsing issues)
      const host = tabRet;
      host.textContent = '';
      const frag = document.createDocumentFragment();

      // Header
      const h = document.createElement('h4');
      h.className = 'text-slate-200 font-semibold mb-2';
      h.textContent = 'Retention';
      frag.appendChild(h);

      // Badge
      const badgeWrap = document.createElement('div');
      badgeWrap.className = 'mb-2';
      const badge = document.createElement('span');
      badge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' + (override ? 'bg-amber-900/40 text-amber-300' : 'bg-slate-700 text-slate-300');
      badge.textContent = override ? 'Overrides account default' : 'Inherits account default';
      badgeWrap.appendChild(badge);
      frag.appendChild(badgeWrap);

      // Override toggle
      const toggleRow = document.createElement('div');
      toggleRow.className = 'flex items-center gap-2 mb-3';
      const chk = document.createElement('input');
      chk.id = 'ret-override';
      chk.type = 'checkbox';
      chk.className = 'h-4 w-4 rounded border-slate-500 bg-slate-600 text-sky-600';
      chk.checked = !!override;
      const lbl = document.createElement('label');
      lbl.setAttribute('for','ret-override');
      lbl.className = 'text-sm text-slate-300';
      lbl.textContent = 'Override account retention for this vault';
      toggleRow.appendChild(chk);
      toggleRow.appendChild(lbl);
      frag.appendChild(toggleRow);
      chk.addEventListener('change', function(){ override = !!chk.checked; renderRetention(); });

      // Content holders
      const content = document.createElement('div');
      content.id = 'ret-content';
      const summary = document.createElement('div');
      summary.id = 'ret-summary';
      summary.className = 'mt-3';
      frag.appendChild(content);
      frag.appendChild(summary);

      host.appendChild(frag);
      const effective = override ? { Mode:mode, Ranges:ranges } : (defaultPolicy || {Mode:MODES.KEEP_EVERYTHING, Ranges:[]});

      if (!override) {
        const bullets = fmtPolicy(effective);
        const ul = document.createElement('ul'); ul.className = 'list-disc ml-5 text-sm text-slate-300';
        bullets.forEach(b=>{ const li=document.createElement('li'); li.textContent=b; ul.appendChild(li); });
        content.appendChild(ul);
        return;
      }

      // Builder: Mode select
      const modeRow = document.createElement('div');
      modeRow.className = 'mb-3';
      const modeLbl = document.createElement('label');
      modeLbl.className = 'block text-sm text-slate-300 mb-1';
      modeLbl.textContent = 'Mode';
      const selMode = document.createElement('select');
      selMode.id = 'ret-mode';
      selMode.className = 'w-64 px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm';
      const optKeep = document.createElement('option'); optKeep.value = String(MODES.KEEP_EVERYTHING); optKeep.textContent = 'Keep everything';
      const optDel = document.createElement('option'); optDel.value = String(MODES.DELETE_EXCEPT); optDel.textContent = 'Delete everything except…';
      selMode.appendChild(optKeep); selMode.appendChild(optDel);
      selMode.value = String(mode);
      modeRow.appendChild(modeLbl); modeRow.appendChild(selMode);
      content.appendChild(modeRow);
      selMode.addEventListener('change', ()=>{ mode = parseInt(selMode.value,10); renderRetention(); });

      if (mode === MODES.DELETE_EXCEPT) {
        // Ranges repeater
        const wrap = document.createElement('div'); wrap.className = 'space-y-3';
        (ranges||[]).forEach((rg, idx)=>{ wrap.appendChild(renderRange(rg, idx)); });
        const add = document.createElement('button'); add.type='button'; add.className='px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded text-sm'; add.textContent='Add range';
        add.addEventListener('click', ()=>{ ranges.push({ Type:TYPES.MOST_RECENT_X_JOBS, Jobs:1 }); renderRetention(); });
        content.appendChild(wrap); content.appendChild(add);
      }

      // Live summary
      const bullets = fmtPolicy(effective);
      const ul = document.createElement('ul'); ul.className = 'list-disc ml-5 text-sm text-slate-300';
      bullets.forEach(b=>{ const li=document.createElement('li'); li.textContent=b; ul.appendChild(li); });
      summary.appendChild(ul);

      function renderRange(rg, idx){
        const row = document.createElement('div'); row.className='border border-slate-700 rounded p-3';
        // Type select
        const top = document.createElement('div');
        top.className='flex items-center gap-3 mb-2';
        const typeLbl = document.createElement('label'); typeLbl.className='text-sm text-slate-300'; typeLbl.textContent='Type';
        const sel = document.createElement('select'); sel.className='px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm ret-type';
        const opts = [
          [900,'Most recent X jobs'],
          [901,'Newer than X (date)'],
          [902,'Jobs since (relative)'],
          [903,'First job for each of last X days'],
          [905,'First job for last X months'],
          [906,'First job for last X weeks'],
          [907,'Last X backups (one per day)'],
          [908,'Last X backups (one per week)'],
          [909,'Last X backups (one per month)'],
          [910,'Last X backups (one per year)'],
          [911,'First job for last X years']
        ];
        opts.forEach(function(p){ var o=document.createElement('option'); o.value=String(p[0]); o.textContent=p[1]; sel.appendChild(o); });
        sel.value = String(rg.Type||TYPES.MOST_RECENT_X_JOBS);
        const removeBtn = document.createElement('button'); removeBtn.type='button'; removeBtn.className='ml-auto px-2.5 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-xs ret-remove'; removeBtn.textContent='Remove';
        removeBtn.addEventListener('click', function(){ ranges.splice(idx,1); renderRetention(); });
        top.appendChild(typeLbl); top.appendChild(sel); top.appendChild(removeBtn);
        row.appendChild(top);

        // Dynamic inputs
        const fields = document.createElement('div'); fields.className='grid grid-cols-2 gap-3';
        row.appendChild(fields);
        function rebuild(){
          rg.Type = parseInt(sel.value,10);
          fields.innerHTML='';
          (TYPE_FIELDS[rg.Type]||[]).forEach(key => {
            const wrap = document.createElement('div');
            wrap.innerHTML = '<label class="block text-xs text-slate-400 mb-1">' + key + '</label>';
            if (key === 'Timestamp') {
              const inp = document.createElement('input'); inp.type='datetime-local'; inp.className='w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm';
              if (rg.Timestamp) { const d=new Date(rg.Timestamp*1000); inp.value = d.toISOString().slice(0,16); }
              inp.addEventListener('change', ()=>{ const v=inp.value; const ms=Date.parse(v); rg.Timestamp = isNaN(ms)?0:Math.floor(ms/1000); });
              wrap.appendChild(inp);
            } else {
              const inp = document.createElement('input'); inp.type='number'; inp.min='0'; inp.className='w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm'; inp.value = rg[key]||'';
              inp.addEventListener('change', ()=>{ rg[key] = parseInt(inp.value||'0',10)||0; });
              wrap.appendChild(inp);
            }
            fields.appendChild(wrap);
          });
        }
        sel.addEventListener('change', rebuild);
        rebuild();
        return row;
      }
    }

    // Save retention
    var saveRetentionBtn = document.getElementById('vault-retention-save');
    if (saveRetentionBtn) saveRetentionBtn.addEventListener('click', async function(){
      try {
        await saveRetentionPolicy();
      } catch(_){ emitRetentionToast('error', 'Failed to save retention.'); }
    });
  }
  // initialize retention builder wiring
  initRetentionBuilder();

  // Simple event handlers to mutate Alpine state for add/remove
  try {
    var rt = document.getElementById('vault-retention-tab');
    if (rt) {
      rt.addEventListener('ret-add-new', function(){
        try { var cmp = rt.__x.$data; if (!cmp || !cmp.state) return; var nr = cmp.newRange || { Type:900, Jobs:1 }; cmp.state.ranges.push(JSON.parse(JSON.stringify(nr))); } catch(_){}
      });
      rt.addEventListener('ret-remove', function(ev){
        try { var cmp = rt.__x.$data; if (!cmp || !cmp.state) return; var i = (ev && ev.detail && typeof ev.detail.i==='number') ? ev.detail.i : -1; if (i>=0) cmp.state.ranges.splice(i,1); } catch(_){}
      });
    }
  } catch(_){}
});

// Hydrate aggregated vaults page: resolve accurate quota/usage per row from live profile (per service)
(function(){
  try {
    var buttons = document.querySelectorAll('.open-vault-panel');
    if (!buttons || buttons.length === 0) return;
    // Group by (serviceId, username), track rows to update
    var groups = {};
    buttons.forEach(function(btn){
      var tr = btn.closest('tr'); if (!tr) return;
      var sid = btn.getAttribute('data-service-id') || (tr.getAttribute('data-service-id')||'');
      var un  = btn.getAttribute('data-username')  || (tr.getAttribute('data-username')||'');
      var vid = tr.getAttribute('data-vault-id') || btn.getAttribute('data-vault-id') || '';
      if (!sid || !un || !vid) return;
      var key = sid+'\t'+un;
      if (!groups[key]) groups[key] = { sid:sid, un:un, rows:[] };
      groups[key].rows.push({ tr:tr, btn:btn, vid:vid });
    });
    var endpoint = window.EB_DEVICE_ENDPOINT || '';
    async function loadProfile(sid, un){
      try {
        const res = await fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'getUserProfile', serviceId:sid, username:un }) });
        return res.json();
      } catch (e) { return null; }
    }
    var keys = Object.keys(groups);
    if (keys.length > 0) {
      try { document.dispatchEvent(new CustomEvent('vaults:hydrate-start')); } catch(_){ }
    }
    var done = 0;
    keys.forEach(function(key){
      var g = groups[key];
      loadProfile(g.sid, g.un).then(function(r){
        if (!r || r.status !== 'success' || !r.profile) return;
        var prof = r.profile;
        var dests = (prof && prof.Destinations) ? prof.Destinations : {};
        (g.rows||[]).forEach(function(entry){
          var tr = entry.tr, btn = entry.btn, vid = entry.vid;
          var v = dests && dests[vid] ? dests[vid] : null;
          if (!v) return;
          // quota
          var qEnabled = !!(v.StorageLimitEnabled);
          var qBytes = parseInt(v.StorageLimitBytes||0,10)||0;
          var quotaCell = tr.querySelector('[data-cell="quota"]');
          if (quotaCell) {
            if (!qEnabled || !qBytes) {
              quotaCell.innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300">Unlimited</span>';
            } else {
              var size = (function(n){ var x=Number(n)||0, u=['B','KB','MB','GB','TB','PB'], i=0; while(x>=1024&&i<u.length-1){x/=1024;i++;} return x.toFixed(i?2:0)+' '+u[i]; })(qBytes);
              var vaultName = (btn && btn.getAttribute('data-vault-name')) ? btn.getAttribute('data-vault-name') : vid;
              var safeName = String(vaultName || '').replace(/"/g, '&quot;');
              quotaCell.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-200">'+size+'</span><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-900/40 text-emerald-300">On</span><button type="button" class="configure-vault-button ml-1 p-1.5 rounded hover:bg-slate-700 text-slate-300" title="Edit quota" data-vault-id="'+vid+'" data-vault-name="'+safeName+'" data-vault-quota-enabled="true" data-vault-quota-bytes="'+qBytes+'" data-service-id="'+g.sid+'" data-username="'+g.un+'"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232a2.5 2.5 0 113.536 3.536L7.5 20.036 3 21l.964-4.5L15.232 5.232z"/></svg></button></span>';
            }
          }
          // usage
          var usedBytes = 0; var usedEnd = 0;
          if (v.Statistics && v.Statistics.ClientProvidedSize && typeof v.Statistics.ClientProvidedSize.Size === 'number') usedBytes = v.Statistics.ClientProvidedSize.Size;
          else if (v.ClientProvidedSize && typeof v.ClientProvidedSize.Size === 'number') usedBytes = v.ClientProvidedSize.Size;
          else if (v.Size && typeof v.Size.Size === 'number') usedBytes = v.Size.Size;
          else if (typeof v.Size === 'number') usedBytes = v.Size;
          if (v.Statistics && v.Statistics.ClientProvidedSize && typeof v.Statistics.ClientProvidedSize.MeasureCompleted === 'number') usedEnd = v.Statistics.ClientProvidedSize.MeasureCompleted;
          else if (v.ClientProvidedSize && typeof v.ClientProvidedSize.MeasureCompleted === 'number') usedEnd = v.ClientProvidedSize.MeasureCompleted;
          var usageCell = tr.querySelector('[data-cell="usage"]');
          if (usageCell) {
            if (!qEnabled || !qBytes) {
              usageCell.innerHTML = '<div class="w-56"><div class="h-2.5 w-full rounded bg-slate-800/70 overflow-hidden"><div class="h-full w-1/3 bg-gradient-to-r from-slate-600/40 via-slate-500/40 to-slate-600/40 animate-pulse"></div></div><div class="mt-1 text-xs text-slate-500">Usage unavailable (no quota)</div></div>';
            } else {
              var pct = qBytes ? (100*usedBytes/qBytes) : 0; if (pct<0) pct=0; if (pct>100) pct=100;
              var color = (pct<70)?'bg-emerald-500':((pct<90)?'bg-amber-500':'bg-rose-500');
              var fmt = function(n){ var x=Number(n)||0, u=['B','KB','MB','GB','TB','PB'], i=0; while(x>=1024&&i<u.length-1){x/=1024;i++;} return x.toFixed(i?2:0)+' '+u[i]; };
              var title = fmt(usedBytes)+' of '+fmt(qBytes)+' ('+pct.toFixed(1)+'%)';
              usageCell.innerHTML = '<div class="w-56"><div class="h-2.5 w-full rounded bg-slate-800/70 overflow-hidden" title="'+title+'"><div class="h-full transition-[width] duration-500 '+color+'" style="width:'+pct+'%"></div></div><div class="mt-1 text-xs text-slate-400">'+fmt(usedBytes)+' / '+fmt(qBytes)+' ('+pct.toFixed(1)+'%)</div></div>';
            }
          }
          // also update Manage button attrs for accurate prefill
          if (btn) {
            btn.setAttribute('data-vault-quota-enabled', String(qEnabled));
            btn.setAttribute('data-vault-quota-bytes', String(qBytes));
          }
        });
      }).finally(function(){
        done++;
        if (done >= keys.length) {
          try { document.dispatchEvent(new CustomEvent('vaults:hydrate-end')); } catch(_){ }
        }
      });
    });
  } catch(_){ }
})();
