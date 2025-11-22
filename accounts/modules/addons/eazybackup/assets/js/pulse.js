window.Pulse = (function(){
  const MAX_FEED = 3;
  function clampFeed(feed){
    while(feed.length > MAX_FEED){ feed.shift(); }
    return feed;
  }
  function formatWhen(ts){ if(!ts) return ''; const d = new Date((String(ts).length<13? ts*1000: ts)); const diff = Math.max(0, Date.now()-d.getTime()); const h=Math.floor(diff/3600000), m=Math.floor((diff%3600000)/60000); if(h>0) return h+'h'+(m>0?(' '+m+'m'):''); if(m>0) return m+'m'; return 'just now'; }
  function sparkPath(values){ if(!values||values.length===0) return ''; const n=values.length; let d='M0 '+(20-(values[0]*20)).toFixed(2); for(let i=1;i<n;i++){ const x=(i*(100/(n-1))).toFixed(2); const y=(20-(values[i]*20)).toFixed(2); d+=` L${x} ${y}`; } return d; }
  function normalizeDevices(devs){
    try{
      if (Array.isArray(devs)){
        const out={};
        for (const d of devs){ const id=String((d&&d.id)||''); if(!id) continue; out[id]={ name:d.name||d.friendly_name||id, friendly_name:d.friendly_name||d.name||id }; }
        return out;
      }
      if (devs && typeof devs==='object') return devs;
    }catch(_){ }
    return {};
  }
  function component(){
    return {
      connected: false,
      tab: 'errors',
      sse: null,
      pollTimer: null,
      state: { running:0, errors:0, missed:0, warnings:0, devices:{ registered:0, active24h:0 }, incidents:{ errors:[], missed:[], warnings:[] }, feed:[], lastEventTs:0 },
      sparks: { devices: [], items: [] },
      deviceMap: {},
      init(opts){
        try{
          this.opts = opts||{};
          if (opts && opts.deviceMap){ this.deviceMap = normalizeDevices(opts.deviceMap); }
          // Derive serviceId from URLs if not provided
          if (!this.opts.serviceId){
            try{
              const getSid=(u)=>{ try{ const q=new URL(String(u), window.location.href).searchParams; return q.get('serviceid'); }catch(_){ return null; } };
              this.opts.serviceId = getSid(this.opts.sseUrl) || getSid(this.opts.pollUrl) || this.opts.serviceId;
            }catch(_){ }
          }
          try{ window.PulseDeviceMap = this.deviceMap; }catch(_){ }
          // Build sane base link if missing or includes wrong action
          const sanitizeBase = (href)=>{
            try{
              if(!href) return '';
              const url = new URL(href, window.location.href);
              url.searchParams.delete('a');
              return url.toString();
            }catch(_){
              try{
                // Fallback simple strip
                return String(href).replace(/([&?])a=[^&]*/,'').replace(/[?&]$/,'');
              }catch(__){ return String(href||''); }
            }
          };
          const mk = (u)=>{ try{ if(!u) return ''; const a=document.createElement('a'); a.href=String(u); return a.href; }catch(_){ return String(u||''); } };
          let base = (typeof window!=='undefined' && window.EB_MODULE_LINK) ? window.EB_MODULE_LINK : 'index.php?m=eazybackup';
          base = sanitizeBase(base);
          const sep = base.includes('?') ? '&' : '?';

          const sid = (this.opts && this.opts.serviceId) ? (`&serviceid=${encodeURIComponent(String(this.opts.serviceId))}`) : '';
          const sseDefault  = base + sep + 'a=pulse-events'   + sid;
          const pollDefault = base + sep + 'a=pulse-snapshot' + sid;
          const ensureAction = (u, action, def)=>{
            try{
              if(!u) return def;
              const url = new URL(String(u), window.location.href);
              const a = url.searchParams.get('a')||'';
              if (a.toLowerCase() !== action) {
                // Rebuild from base
                return def;
              }
              return url.toString();
            }catch(_){ return def; }
          };

          this.opts.sseUrl  = mk(ensureAction(this.opts.sseUrl,  'pulse-events',  sseDefault));
          this.opts.pollUrl = mk(ensureAction(this.opts.pollUrl, 'pulse-snapshot', pollDefault));
          // basic diagnostics
          try{ console.log('[Pulse] init serviceId=', this.opts.serviceId, 'devs=', Object.keys(this.deviceMap||{}).length); }catch(_){ }
          this.connectSSE();
          this.fetchSnapshot();
        }catch(_){ }
      },
      deviceName(id){ try{ const k=String(id||''); const m=this.deviceMap||{}; return (m[k] && (m[k].name||m[k].friendly_name)) || k; }catch(_){ return String(id||''); } },
      fetchSnapshot(){
        try{
          if(!this.opts || !this.opts.pollUrl){ return; }
          fetch(this.opts.pollUrl, { credentials:'include' })
            .then(r=>r.ok ? r.json() : r.text().then(t=>{ throw new Error(t||('HTTP '+r.status)); }))
            .then(p=>{ this.applySnapshot(p); })
            .catch(err=>{ try{ console.warn('[Pulse] snapshot error', err); }catch(_){ } });
        }catch(_){ }
      },
      connectSSE(){ try{
        if (this.sse){ try{ this.sse.close(); }catch(_){ } this.sse=null; }
        this.sse = new EventSource(this.opts.sseUrl, { withCredentials: true });
        this.sse.onopen = () => { this.connected = true; if(this.pollTimer){ clearInterval(this.pollTimer); this.pollTimer=null; } };
        this.sse.onerror = (e) => { this.connected = false; try{ console.warn('[Pulse] SSE error', e); }catch(_){ } if(!this.pollTimer){ this.pollTimer = setInterval(()=>this.fetchSnapshot(), 10000); } };
        this.sse.onmessage = (e) => {
          try{ const p = JSON.parse(e.data||'{}'); this.route(p); }catch(_){ }
        };
      }catch(_){ this.connected=false; if(!this.pollTimer){ this.pollTimer=setInterval(()=>this.fetchSnapshot(),10000); } }
      },
      route(p){ if(!p) return; if(p.t){ this.state.lastEventTs = Math.max(this.state.lastEventTs||0, Number(p.t)||0); }
        if (p.kind){
          switch(p.kind){
            case 'snapshot': this.applySnapshot(p); break;
            case 'job:start': this.onJobStart(p.job); break;
            case 'job:end': this.onJobEnd(p.job); break;
            case 'device:new': this.onDeviceNew(p.device); break;
            case 'device:removed': this.onDeviceRemoved(p.device); break;
          }
          return;
        }
        // Support raw SSE events from pulse-events: {status, started_at, completed_at, device, device_name, job_id}
        if (p.status){
          const s = String(p.status).toLowerCase();
          if (s === 'running') { this.onJobStart(p); return; }
          if (['success','warning','error','missed','skipped'].includes(s)) { this.onJobEnd(p); return; }
        }
      },
      applySnapshot(p){ try{
        if(p && p.counters){
          const c=p.counters; this.state.running=c.running||0; this.state.errors=c.errors||0; this.state.missed=c.missed||0; this.state.warnings=c.warnings||0; this.state.devices={ registered:(c.devices&&c.devices.registered)||0, active24h:(c.devices&&c.devices.active24h)||0 };
        }
        if(p && p.incidents){ this.state.incidents = { errors:(p.incidents.errors||[]), missed:(p.incidents.missed||[]), warnings:(p.incidents.warnings||[]) }; }
        // When snapshot lands, clear feed waiting message by sending a neutral ping
        if(!Array.isArray(this.state.feed) || this.state.feed.length===0){ this.state.feed = []; }
      }catch(_){ }
      },
      onJobStart(j){ try{ this.state.running = Math.max(0,(this.state.running||0)+1); const dn = (j.device_name|| this.deviceName(j.device)); this.state.feed.push({ t: Date.now(), text:`Job started · ${j.username||''}/${dn||''}`, tone:'neutral' }); clampFeed(this.state.feed); }catch(_){ } },
      onJobEnd(j){ try{
        this.state.running = Math.max(0,(this.state.running||0)-1);
        const dn = (j.device_name|| this.deviceName(j.device));
        const s=(j.status||'').toLowerCase(); if(s==='error'){ this.state.errors++; this.state.incidents.errors = [{...j}, ...this.state.incidents.errors].slice(0,8); this.state.feed.push({ t:Date.now(), text:`Job error · ${j.username||''}/${dn||''}`, tone:'bad' }); }
        else if(s==='warning'){ this.state.warnings++; this.state.incidents.warnings = [{...j}, ...this.state.incidents.warnings].slice(0,8); this.state.feed.push({ t:Date.now(), text:`Job warning · ${j.username||''}/${dn||''}`, tone:'neutral' }); }
        else if(s==='missed' || s==='timeout'){ this.state.missed++; this.state.incidents.missed = [{...j}, ...this.state.incidents.missed].slice(0,8); this.state.feed.push({ t:Date.now(), text:`Job missed · ${j.username||''}/${dn||''}`, tone:'neutral' }); }
        else { this.state.feed.push({ t:Date.now(), text:`Job success · ${j.username||''}/${dn||''}`, tone:'good' }); }
        clampFeed(this.state.feed);
      }catch(_){ } },
      onDeviceNew(d){ try{ this.state.devices.registered = Math.max(0,(this.state.devices.registered||0)+1); this.state.feed.push({ t:Date.now(), text:`Device registered · ${d.id||''}`, tone:'good' }); clampFeed(this.state.feed); }catch(_){ } },
      onDeviceRemoved(d){ try{ this.state.devices.registered = Math.max(0,(this.state.devices.registered||0)-1); this.state.feed.push({ t:Date.now(), text:`Device removed · ${d.id||''}`, tone:'bad' }); clampFeed(this.state.feed); }catch(_){ } },
      visibleIncidents(){ const k=this.tab; const arr=(this.state.incidents&&this.state.incidents[k])||[]; return arr.slice(0,8); },
      linkToLogs(it){ try{ const u = encodeURIComponent(it.username||''); return `${(window.EB_MODULE_LINK||'index.php?m=eazybackup')}&a=console#jobLogs`; }catch(_){ return '#'; } },
      snooze(jobId, minutes){ try{
        const token = (window.csrfToken|| (typeof WHMCS!=='undefined' && WHMCS.csrfToken)) || (document.querySelector('meta[name=csrf-token]')?.content || '');
        fetch((this.opts.pollUrl||'').replace('pulse-snapshot','pulse-snooze'), { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': token }, body: JSON.stringify({ job_id: String(jobId||''), minutes: Number(minutes||0) }) })
        .then(()=>{ ['errors','missed','warnings'].forEach(k=>{ this.state.incidents[k]= (this.state.incidents[k]||[]).filter(x=> String(x.job_id||'')!==String(jobId||'')); }); });
      }catch(_){ }
      },
      formatWhen,
      sparkPath
    };
  }
  // auto-register Alpine component and global fallback
  function register(){
    try{
      if (window.Alpine){ Alpine.data('pulse', component); }
      else { document.addEventListener('alpine:init', () => Alpine.data('pulse', component), { once:true }); }
    }catch(_){ }
    try{ window.pulse = component; }catch(_){ }
  }
  register();
  return { component, formatWhen, sparkPath };
})();


