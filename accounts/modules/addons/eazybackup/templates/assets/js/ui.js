;(function(){
  if (window.ebShowLoader && window.ebHideLoader) return;
  function ensureStyles(){
    // No-op: Tailwind classes used; keep hook if we need custom CSS later
  }
  function makeOverlay(message){
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/60';
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
      vpanel.classList.remove('translate-x-full');
      try { document.dispatchEvent(new CustomEvent('vault:open', { detail: { id, name } })); } catch(_) {}
    }
    vclose && vclose.addEventListener('click', ()=> vpanel.classList.add('translate-x-full'));
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
    document.querySelectorAll('.open-vault-panel').forEach(btn => btn.addEventListener('click', (e)=>{ e.preventDefault(); openVaultPanel(btn); }));

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
      if (!id) { try{ if(window.showToast) window.showToast('Missing vault id.','error'); }catch(_){} return; }
      if (!newName) { try{ if(window.showToast) window.showToast('Enter a vault name.','warning'); }catch(_){} return; }
      const r = await callVault('updateVault', { vaultId:id, vaultName:newName, vaultQuota:quota });
      try{ if(window.showToast) window.showToast(r.message || (r.status==='success'?'Changes saved.':'Save failed'), r.status==='success'?'success':'error'); }catch(_){ }
      if (r.status==='success') {
        titleName.textContent = newName;
        const btn = document.querySelector('.open-vault-panel[data-vault-id="' + id + '"]');
        if (btn) {
          btn.setAttribute('data-vault-name', newName);
          btn.setAttribute('data-vault-quota-enabled', (!unlimited).toString());
          let bytes = 0; if (!unlimited) { bytes = unit==='TB' ? Math.round(size*(1024**4)) : Math.round(size*(1024**3)); }
          btn.setAttribute('data-vault-quota-bytes', String(bytes));
        }
      }
    });

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
        if (key === 'acct') return (tr.getAttribute('data-acct')||'').toString().toLowerCase();
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
    var saveAllBtn2 = document.getElementById('vault-save-all');
    if (saveAllBtn2) saveAllBtn2.addEventListener('click', async function(){
      if (!currentProfile || !currentVaultId) return; // handled by general save already
      // patch a clone
      try {
        const prof = JSON.parse(JSON.stringify(currentProfile));
        const v = (prof.Destinations && prof.Destinations[currentVaultId]) ? prof.Destinations[currentVaultId] : null;
        if (!v) return;
        if (!override) {
          if ('RetentionPolicy' in v) delete v.RetentionPolicy;
        } else {
          const payload = { Mode: mode, Ranges: (mode===MODES.DELETE_EXCEPT? (ranges||[]).map(r=>clampRange(Object.assign({},r))) : []) };
          v.RetentionPolicy = payload;
        }
        const r = await api('setUserProfile', { profile: prof, hash: currentHash });
        if (r && r.status==='success') {
          currentProfile = prof; currentHash = r.hash || currentHash;
        } else if (r && r.code === 'hash_mismatch') {
          try { if (window.showToast) window.showToast('Profile changed on server; reloaded','warning'); } catch(_){ }
          const rr = await api('getUserProfile', {});
          if (rr && rr.status==='success') { currentProfile = rr.profile; currentHash = rr.hash; }
        }
      } catch(_) {}
    });
    var saveRetentionBtn = document.getElementById('vault-retention-save');
    if (saveRetentionBtn) saveRetentionBtn.addEventListener('click', async function(){
      if (saveAllBtn2) { saveAllBtn2.click(); return; }
      // fallback same as above if general save button not present
      try {
        const prof = JSON.parse(JSON.stringify(currentProfile));
        const v = (prof.Destinations && prof.Destinations[currentVaultId]) ? prof.Destinations[currentVaultId] : null;
        if (!v) return;
        if (!override) { if ('RetentionPolicy' in v) delete v.RetentionPolicy; }
        else { const payload = { Mode: mode, Ranges: (mode===MODES.DELETE_EXCEPT? (ranges||[]).map(r=>clampRange(Object.assign({},r))) : []) }; v.RetentionPolicy = payload; }
        const r = await api('setUserProfile', { profile: prof, hash: currentHash });
        if (r && r.status==='success') { currentProfile = prof; currentHash = r.hash || currentHash; try{ if(window.showToast) window.showToast('Retention saved.','success'); }catch(_){ } }
      } catch(_){}
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
              quotaCell.innerHTML = '<span class="inline-flex items-center gap-2"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-200">'+size+'</span><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-900/40 text-emerald-300">On</span></span>';
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
