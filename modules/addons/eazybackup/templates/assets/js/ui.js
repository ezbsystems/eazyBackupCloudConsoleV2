;(function(){
  if (window.ebShowLoader && window.ebHideLoader) return;
  function ensureStyles(){
    // No-op: Tailwind classes used; keep hook if we need custom CSS later
  }
  function makeOverlay(message){
    const wrap = document.createElement('div');
    wrap.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/60';
    wrap.setAttribute('data-eb-loader', '1');
    wrap.innerHTML = `
      <div class="flex flex-col items-center gap-3 px-6 py-4 rounded-lg border border-slate-700 bg-slate-900/90 shadow-xl">
        <span class="inline-flex h-10 w-10 rounded-full border-2 border-sky-500 border-t-transparent animate-spin"></span>
        ${message ? `<div class="text-slate-200 text-sm">${message}</div>` : ''}
      </div>
    `;
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


document.addEventListener('DOMContentLoaded', function(){
  // Vault stats modal wiring
  const modal = document.getElementById('vault-stats-modal');
  if (!modal) return;
  const close = document.getElementById('vsm-close');
  close && close.addEventListener('click', () => modal.classList.add('hidden'));
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });

  const itemsJson = document.getElementById('vsm-items-json')?.value || '[]';
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
    const num = Number(n)||0;
    const units = ['B','KB','MB','GB','TB','PB'];
    let u = 0; let v = num;
    while (v >= 1024 && u < units.length-1){ v /= 1024; u++; }
    const prec = u === 0 ? 0 : (u <= 2 ? 2 : 2);
    return `${v.toFixed(prec)} ${units[u]}`;
  }
  function dt(ts){ try { const t=parseInt(ts,10); if(!t) return ''; const d=new Date(t*1000); return d.toISOString().replace('T',' ').slice(0,19); } catch(_){ return ''; } }
  function dur(a,b){ const s=Math.max(0, (parseInt(b,10)||0)-(parseInt(a,10)||0)); const h=Math.floor(s/3600), m=Math.floor((s%3600)/60), sec=s%60; return h>0?`${h}:${String(m).padStart(2,'0')}`:`${m}:${String(sec).padStart(2,'0')}`; }

  function openModal(fromBtn){
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
    title && (title.textContent = `Vault usage – ${vaultName}`);
    const summary = document.getElementById('vsm-summary');
    summary && (summary.textContent = `Total size ${fmtBytes(sizeBytes)} (${fmtBytes(currentBytes)} in use by current data) measured at ${dt(me)} (took ${dur(ms,me)})`);

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
            const nm = name ? `"${name}"` : '';
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
          rows.querySelectorAll('.col-span-8 > div[data-guid]')?.forEach(div => {
            const guid = (div.dataset.guid || '').toUpperCase();
            if (!guid) return;
            const nm = idToName.get(guid);
            if (nm) {
              const kind = (div.dataset.kind || '').toUpperCase();
              const nameQuoted = `"${nm}"`;
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
});
