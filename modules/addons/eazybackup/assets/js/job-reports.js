(() => {
  function fmtBytes(n){ if(!n||n<=0) return '0 B'; const u=['B','KB','MB','GB','TB','PB']; const i=Math.floor(Math.log(n)/Math.log(1024)); return (n/Math.pow(1024,i)).toFixed(i?1:0)+' '+u[i]; }
  function fmtTs(s){ if(!s||s<=0) return '—'; const d=new Date(s*1000); return d.toLocaleString(); }
  function fmtDur(sec){ if(!sec||sec<=0) return '—'; const h=Math.floor(sec/3600); const m=Math.floor((sec%3600)/60); const s=Math.floor(sec%60); return (h?`${h}h `:'')+`${m}m ${s}s`; }

  const endpoint = (window.EB_JOBREPORTS_ENDPOINT || (typeof EB_MODULE_LINK!=='undefined' ? `${EB_MODULE_LINK}&a=job-reports` : 'index.php?m=eazybackup&a=job-reports'));

  async function api(action, payload){
    const res = await fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload||{ action }) });
    return await res.json();
  }

  async function openJobModal(serviceId, username, jobId){
    const modal = document.getElementById('job-report-modal');
    if(!modal) return;
    modal.classList.remove('hidden');
    modal.querySelector('#jrm-title').textContent = `Job`;
    modal.querySelector('#jrm-subtitle').textContent = `${username}`;
    modal.querySelector('#jrm-current-job').value = jobId;
    modal.querySelector('#jrm-service-id').value = String(serviceId||'');
    modal.querySelector('#jrm-username').value = String(username||'');

    // Load summary
    try {
      const det = await api('jobDetail', { action:'jobDetail', serviceId, username, jobId });
      if(det && det.status==='success' && det.job){
        const j = det.job;
        modal.querySelector('#jrm-status').textContent   = j.FriendlyStatus || '';
        modal.querySelector('#jrm-type').textContent     = j.FriendlyJobType || '';
        modal.querySelector('#jrm-device').textContent   = (j.DeviceFriendlyName || j.Device || '');
        modal.querySelector('#jrm-item').textContent     = j.ProtectedItemDescription || '';
        modal.querySelector('#jrm-vault').textContent    = (j.VaultDescription || j.DestinationLocation || '');
        modal.querySelector('#jrm-up').textContent       = fmtBytes(j.UploadSize||0);
        modal.querySelector('#jrm-down').textContent     = fmtBytes(j.DownloadSize||0);
        modal.querySelector('#jrm-size').textContent     = fmtBytes(j.TotalSize||0);
        modal.querySelector('#jrm-start').textContent    = fmtTs(j.StartTime||0);
        modal.querySelector('#jrm-end').textContent      = fmtTs(j.EndTime||0);
        modal.querySelector('#jrm-duration').textContent = fmtDur((j.EndTime||0)-(j.StartTime||0));
        modal.querySelector('#jrm-version').textContent  = j.ClientVersion || '';
        // Title as protected item name
        modal.querySelector('#jrm-title').textContent = j.ProtectedItemDescription || `Job ${jobId}`;
      }
    } catch(_){}

    // Load logs
    try {
      const logs = await api('jobLogEntries', { action:'jobLogEntries', serviceId, username, jobId });
      const box = modal.querySelector('#jrm-logs');
      box.innerHTML = '';
      if(logs && logs.status==='success' && Array.isArray(logs.rows)){
        for(const e of logs.rows){
          const row = document.createElement('div');
          row.className = 'px-3 py-2 grid grid-cols-12 gap-2';
          const t = document.createElement('div'); t.className='col-span-3 text-xs text-slate-400'; t.textContent = fmtTs(e.Time);
          const s = document.createElement('div'); s.className='col-span-2 text-xs';
          const sev = (e.Severity||'').toUpperCase();
          if(sev==='I'){ s.classList.add('text-slate-400'); s.textContent = 'Information'; }
          else if(sev==='W'){ s.classList.add('text-amber-400'); s.textContent = 'Warning'; }
          else if(sev==='E'){ s.classList.add('text-rose-400'); s.textContent = 'Error'; }
          else { s.classList.add('text-slate-300'); s.textContent = e.Severity||''; }
          const m = document.createElement('div'); m.className='col-span-7 text-slate-200'; m.textContent = e.Message;
          row.dataset.sev = (e.Severity||'').toLowerCase();
          row.appendChild(t); row.appendChild(s); row.appendChild(m);
          box.appendChild(row);
        }
      }
    } catch(_){}
  }

  function attachModalControls(){
    const modal = document.getElementById('job-report-modal');
    if(!modal) return;
    const closeBtn = modal.querySelector('#jrm-close');
    closeBtn && closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', (e) => { if(e.target === modal) modal.classList.add('hidden'); });

    const filter = modal.querySelector('#jrm-filter');
    const search = modal.querySelector('#jrm-search');
    const applyFilter = () => {
      const want = (filter.value||'all').toLowerCase();
      const q = (search.value||'').toLowerCase();
      modal.querySelectorAll('#jrm-logs > div').forEach(el => {
        const sevOk = (want==='all') || (el.dataset.sev===want);
        const text = el.textContent.toLowerCase();
        el.style.display = (sevOk && (q==='' || text.includes(q))) ? '' : 'none';
      });
    };
    filter && filter.addEventListener('change', applyFilter);
    search && search.addEventListener('input', applyFilter);
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
    const state = { page:1, pageSize:25, sortBy:'Started', sortDir:'desc', q:'' };
    const serviceId = opts.serviceId; const username = opts.username;
    const thead = el.querySelector('thead'); const tbody = el.querySelector('tbody');
    const colKeys = ['user','id','device','item','vault','ver','type','status','dirs','files','size','vsize','up','down','started','ended','dur'];
    const totalEl = opts.totalEl; const pagerEl = opts.pagerEl; const searchInput = opts.searchInput; const cols = opts.cols || [];

    async function load(){
      const res = await api('listJobs', { action:'listJobs', serviceId, username, page: state.page, pageSize: state.pageSize, sortBy: state.sortBy, sortDir: state.sortDir, q: state.q });
      if(!(res && res.status==='success')) return;
      totalEl && (totalEl.textContent = String(res.total||0));
      tbody.innerHTML = '';
      for(const r of (res.rows||[])){
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-800/60 cursor-pointer';
        tr.setAttribute('data-job-id', r.JobID);
        tr.setAttribute('data-service-id', String(serviceId));
        tr.setAttribute('data-username', String(username));
        const cells = [r.Username, r.JobID, r.Device, r.ProtectedItem, r.StorageVault, r.Version, r.Type, r.Status, r.Directories, r.Files, fmtBytes(r.Size), fmtBytes(r.VaultSize), fmtBytes(r.Uploaded), fmtBytes(r.Downloaded), fmtTs(r.Started), fmtTs(r.Ended), fmtDur(r.Duration)];
        for(let i=0;i<cells.length;i++){
          const td=document.createElement('td'); td.className='px-4 py-3 whitespace-nowrap text-sm';
          td.setAttribute('data-col', colKeys[i]);
          if (i===7) { // Status column
            const status = (r.Status||'').toUpperCase();
            td.textContent = status;
            if (status==='SUCCESS') td.classList.add('text-emerald-400');
            else if (status==='WARNING') td.classList.add('text-amber-400');
            else if (['ERROR','TIMEOUT','CANCELLED','QUOTA_EXCEEDED','ALREADY_RUNNING','ABANDONED'].includes(status)) td.classList.add('text-rose-400');
            else if (['ACTIVE','REVIVED'].includes(status)) td.classList.add('text-sky-400');
          } else {
            td.classList.add('text-gray-300');
            td.textContent = cells[i]!==undefined? String(cells[i]) : '';
          }
          tr.appendChild(td);
        }
        tbody.appendChild(tr);
      }
      renderPager(res.total||0);
      // Apply current column visibility to body cells
      syncColumnVisibility();
    }

    function renderPager(total){
      if(!pagerEl) return;
      const pages = Math.max(1, Math.ceil(total / state.pageSize));
      pagerEl.innerHTML = '';
      const mk = (label, page, disabled=false, current=false) => { const b=document.createElement('button'); b.className = 'px-2 py-1 text-xs rounded '+(current?'bg-sky-700 text-white':'bg-slate-700 text-slate-200')+(disabled?' opacity-50 cursor-not-allowed':''); b.textContent=label; if(!disabled) b.addEventListener('click',()=>{ state.page=page; load(); }); return b; };
      pagerEl.appendChild(mk('Prev', Math.max(1, state.page-1), state.page<=1));
      pagerEl.appendChild(document.createTextNode(` Page ${state.page} / ${pages} `));
      pagerEl.appendChild(mk('Next', Math.min(pages, state.page+1), state.page>=pages));
    }

    // Sorting handlers
    thead.querySelectorAll('th[data-sort]').forEach((th) => {
      th.addEventListener('click', () => {
        const key = th.getAttribute('data-sort');
        if(state.sortBy === key){ state.sortDir = (state.sortDir==='asc'?'desc':'asc'); } else { state.sortBy = key; state.sortDir = 'asc'; }
        load();
      });
    });

    // Search
    searchInput && searchInput.addEventListener('input', () => { state.q = searchInput.value||''; state.page = 1; load(); });

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

    // Public API
    return { reload: load };
  }

  // Expose factory
  window.jobReportsFactory = function(opts){
    attachModalControls();
    attachRowOpeners();
    return { makeJobsTable, openJobModal };
  };

  document.dispatchEvent(new Event('jobReports:ready'));
})();


