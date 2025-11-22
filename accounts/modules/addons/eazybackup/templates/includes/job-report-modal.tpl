{literal}
<script>
  function jobReportModal() {
    return {
      open: false,
      loading: false,
      error: '',
      jobId: '',
      serviceId: '',
      modulelink: '',
      props: null,
      entries: [],
      rawLog: '',
      get formattedEntries() {
        try {
          if (Array.isArray(this.entries) && this.entries.length) {
            return this.entries.map(function(e){ return { time: jobReportModal.helpers.formatTs(e.Timestamp || e.Time || 0), sev: String(e.Severity||'').charAt(0).toUpperCase(), msg: (e.Message || e.Text || '') }; });
          }
          if (typeof this.rawLog === 'string' && this.rawLog.length) {
            return this.rawLog.split(/\r?\n/).filter(Boolean).map(function(line){
              var m = line.match(/^\[(.*?)\]\s*\((.)\)\s*(.*)$/);
              if (m) { return { time: m[1], sev: m[2].toUpperCase(), msg: m[3] }; }
              return { time: '', sev: 'I', msg: line };
            });
          }
        } catch (_) {}
        return [];
      },
      openWith: function(jobId, serviceId) {
        this.open = true; this.error=''; this.props=null; this.entries=[]; this.rawLog='';
        this.jobId = String(jobId||''); this.serviceId = String(serviceId||'');
        try { this.modulelink = (this.$root && this.$root.dataset && this.$root.dataset.modulelink) ? this.$root.dataset.modulelink : ''; } catch(_) { this.modulelink = ''; }
        this.fetchReport();
      },
      close: function() { this.open = false; },
      fetchReport: async function() {
        try {
          this.loading = true; this.error='';
          var headers = { 'Content-Type': 'application/json' };
          if (window.csrfToken) headers['X-CSRF-Token'] = window.csrfToken;
          var url = (this.modulelink || '') + '&a=api';
          var body = JSON.stringify({ action: 'getJobReport', serviceId: this.serviceId, jobId: this.jobId });
          var res = await fetch(url, { method:'POST', headers: headers, body: body });
          var data = await res.json();
          if (data && data.status === 'success') {
            this.props = data.properties || null;
            this.entries = Array.isArray(data.entries) ? data.entries : [];
            this.rawLog = (typeof data.log === 'string') ? data.log : '';
            // Fallback: if structured entries are not available, fetch raw log
            if ((!this.entries || this.entries.length === 0) && !this.rawLog) {
              try {
                var rawRes = await fetch(url, { method:'POST', headers: headers, body: JSON.stringify({ action:'getJobLogRaw', serviceId: this.serviceId, jobId: this.jobId }) });
                var rawData = await rawRes.json();
                if (rawData && rawData.status === 'success' && typeof rawData.log === 'string') {
                  this.rawLog = rawData.log;
                }
              } catch (_) {}
            }
          } else {
            this.error = (data && data.message) ? data.message : 'Failed to load job report.';
          }
        } catch (_) {
          this.error = 'Network error.';
        }
        this.loading = false;
      },
      // Helpers (single source of truth)
      formatTs: function(ts) { return jobReportModal.helpers.formatTs(ts); },
      hBytes: function(b) { return jobReportModal.helpers.hBytes(b); },
      statusText: function(code) { return jobReportModal.helpers.statusText(code); },
      statusClass: function(code) { return jobReportModal.helpers.statusClass(code); },
      severityClass: function(s) { return jobReportModal.helpers.severityClass(s); },
      typeText: function(p) { return jobReportModal.helpers.typeText(p); },
      deviceName: function(p){ return (p && (p.DeviceName||p.DeviceID)) || ''; },
      sourceText: function(p){ return (p && (p.SourceDescription||p.SourceName)) || ''; },
      destinationName: function(p){ return (p && (p.DestinationDescription||p.DestinationName)) || ''; },
      protectedItemTitle: function(){ try{ return this.sourceText(this.props)||''; }catch(_){ return ''; } }
    };
  }
  jobReportModal.helpers = {
    formatTs: function(ts){ try { var d = new Date((Number(ts)||0)*1000); if (isNaN(d)) return ''; return d.toISOString().replace('T',' ').substring(0,19); } catch(_){ return ''; } },
    hBytes: function(b){ try { var v=Number(b)||0; var u=['B','KB','MB','GB','TB','PB']; var i=0; while(v>=1024&&i<u.length-1){v=v/1024;i++;} return (i===0? v : v.toFixed(2))+' '+u[i]; } catch(_){ return String(b); } },
    statusText: function(code){ var s=Number(code)||0; if(s>=5000&&s<6000) return 'Success'; if(s===7001) return 'Warning'; if(s===7002||s===7003) return 'Error'; if(s===7004||s===7006) return 'Skipped'; if(s===7000) return 'Timeout'; if(s>=6000&&s<7000) return 'Running'; return 'Unknown'; },
    statusClass: function(code){ var t=this.statusText(code); if(t==='Success') return 'bg-green-900/50 text-green-300'; if(t==='Warning') return 'bg-amber-900/40 text-amber-300'; if(t==='Error') return 'bg-rose-900/40 text-rose-300'; return 'bg-gray-700 text-gray-300'; },
    severityClass: function(s){ var L=String(s||'').charAt(0).toUpperCase(); if(L==='E') return 'text-rose-400'; if(L==='W') return 'text-amber-400'; return 'text-slate-300'; },
    typeText: function(p){ if (!p) return ''; if (typeof p.Classification==='number') return p.Classification===2?'Restore':'Backup'; var t=(p.Type||'').toString().toLowerCase(); return t==='restore'?'Restore':'Backup'; }
  };

  // Global trigger binding so any page can open the modal via data-open-job-report
  document.addEventListener('click', function(e){
    var el = e.target && e.target.closest ? e.target.closest('[data-open-job-report]') : null;
    if (!el) return;
    e.preventDefault();
    var jobId = el.getAttribute('data-job-id') || (el.dataset ? el.dataset.jobId : '');
    var serviceId = el.getAttribute('data-service-id') || (el.dataset ? el.dataset.serviceId : '');
    window.dispatchEvent(new CustomEvent('open-job-modal', { detail: { jobId: jobId, serviceId: serviceId } }));
  });
</script>
{/literal}

<!-- Shared Job Report Modal (reusable across pages) -->
<div x-data="jobReportModal()" @open-job-modal.window="openWith($event.detail.jobId, $event.detail.serviceId)" data-modulelink="{$modulelink}">
  <div x-show="open" x-transition x-cloak class="fixed inset-0 z-[100] flex items-center justify-center">
    <div class="absolute inset-0 bg-black/60" @click="close()"></div>
    <div class="relative bg-gray-800 border border-gray-700 rounded-lg shadow-xl w-[90vw] max-w-3xl max-h-[80vh] overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
        <h3 class="text-sm font-semibold text-gray-200">Job Report <span class="text-gray-400" x-text="protectedItemTitle()"></span></h3>
        <button @click="close()" class="text-gray-400 hover:text-gray-200">✕</button>
      </div>
      <div class="p-4 overflow-y-auto max-h-[70vh]">
        <template x-if="error">
          <div class="text-red-400 text-sm" x-text="error"></div>
        </template>
        <template x-if="loading">
          <div class="space-y-2">
            <div class="h-4 bg-gray-700 rounded animate-pulse"></div>
            <div class="h-4 bg-gray-700 rounded animate-pulse"></div>
            <div class="h-4 bg-gray-700 rounded animate-pulse"></div>
          </div>
        </template>
        <template x-if="!loading && props">
          <div class="space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs text-gray-300">
              <div><span class="text-gray-400">Status:</span> <span :class="statusClass(props.Status)" class="px-1.5 py-0.5 rounded" x-text="statusText(props.Status)"></span></div>
              <div><span class="text-gray-400">Type:</span> <span x-text="typeText(props)"></span></div>
              <div><span class="text-gray-400">Device:</span> <span x-text="deviceName(props)"></span></div>
              <div><span class="text-gray-400">Source:</span> <span x-text="sourceText(props)"></span></div>
              <div><span class="text-gray-400">Destination:</span> <span x-text="destinationName(props)"></span></div>
              <div><span class="text-gray-400">Start:</span> <span x-text="formatTs(props.StartTime||0)"></span></div>
              <div><span class="text-gray-400">End:</span> <span x-text="formatTs(props.EndTime||0)"></span></div>
              <div><span class="text-gray-400">Uploaded:</span> <span x-text="hBytes(props.UploadSize || props.Uploaded || 0)"></span></div>
              <div><span class="text-gray-400">Downloaded:</span> <span x-text="hBytes(props.DownloadSize || props.Downloaded || 0)"></span></div>
              <div><span class="text-gray-400">Total Size:</span> <span x-text="hBytes(props.TotalSize || 0)"></span></div>
            </div>
            <div>
              <div class="text-xs text-gray-400 mb-1">Log Entries</div>
              <div class="bg-gray-900/60 border border-gray-700 rounded p-2 max-h-64 overflow-y-auto text-[11px] leading-5">
                <template x-for="(ln, idx) in formattedEntries" :key="idx">
                  <div class="whitespace-pre-wrap">
                    <span class="text-slate-400" x-text="'[' + ln.time + ']' "></span>
                    <span :class="severityClass(ln.sev)" x-text="' (' + ln.sev + ') '"></span>
                    <span :class="severityClass(ln.sev)" x-text="ln.msg"></span>
                  </div>
                </template>
                <template x-if="!formattedEntries || formattedEntries.length===0">
                  <div class="text-gray-500">No log entries.</div>
                </template>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>

  {literal}
  <script>
  function jobReportModal() {
    return {
      open: false,
      loading: false,
      error: '',
      jobId: '',
      serviceId: '',
      modulelink: '',
      props: null,
      entries: [],
      rawLog: '',
      get formattedEntries() {
        try {
          if (Array.isArray(this.entries) && this.entries.length) {
            return this.entries.map(e => ({ time: this.formatTs(e.Timestamp || e.Time || 0), sev: String(e.Severity||'').charAt(0).toUpperCase(), msg: (e.Message || e.Text || '') }));
          }
          if (typeof this.rawLog === 'string' && this.rawLog.length) {
            return this.rawLog.split(/\r?\n/).filter(Boolean).map(line => {
              const m = line.match(/^\[(.*?)\]\s*\((.)\)\s*(.*)$/);
              if (m) { return { time: m[1], sev: m[2].toUpperCase(), msg: m[3] }; }
              return { time: '', sev: 'I', msg: line };
            });
          }
        } catch (_) {}
        return [];
      },
      openWith(jobId, serviceId) {
        this.open = true; this.error=''; this.props=null; this.entries=[]; this.rawLog='';
        this.jobId = String(jobId||''); this.serviceId = String(serviceId||'');
        try { this.modulelink = (this.$root && this.$root.dataset && this.$root.dataset.modulelink) ? this.$root.dataset.modulelink : ''; } catch(_) { this.modulelink = ''; }
        this.fetchReport();
      },
      close() { this.open = false; },
      async fetchReport() {
        try {
          this.loading = true; this.error='';
          const headers = { 'Content-Type': 'application/json' };
          if (window.csrfToken) headers['X-CSRF-Token'] = window.csrfToken;
          const url = (this.modulelink || '') + '&a=api';
          const body = JSON.stringify({ action: 'getJobReport', serviceId: this.serviceId, jobId: this.jobId });
          const res = await fetch(url, { method:'POST', headers, body });
          const data = await res.json();
          if (data && data.status === 'success') {
            this.props = data.properties || null;
            this.entries = Array.isArray(data.entries) ? data.entries : [];
            this.rawLog = (typeof data.log === 'string') ? data.log : '';
          } else {
            this.error = (data && data.message) ? data.message : 'Failed to load job report.';
          }
        } catch (_) {
          this.error = 'Network error.';
        }
        this.loading = false;
      },
      // Helpers (single source of truth)
      formatTs(ts) { try { const d = new Date((Number(ts)||0)*1000); if (isNaN(d)) return ''; return d.toISOString().replace('T',' ').substring(0,19); } catch(_){ return ''; } },
      hBytes(b) {
        try { var v = Number(b)||0; var u=['B','KB','MB','GB','TB','PB']; var i=0; while(v>=1024&&i<u.length-1){v=v/1024;i++;} return (i===0? v : v.toFixed(2))+' '+u[i]; } catch(_){ return String(b); }
      },
      statusText(code) { const s=Number(code)||0; if(s>=5000&&s<6000) return 'Success'; if(s===7001) return 'Warning'; if(s===7002||s===7003) return 'Error'; if(s===7004||s===7006) return 'Skipped'; if(s===7000) return 'Timeout'; if(s>=6000&&s<7000) return 'Running'; return 'Unknown'; },
      statusClass(code) { const t=this.statusText(code); if(t==='Success') return 'bg-green-900/50 text-green-300'; if(t==='Warning') return 'bg-amber-900/40 text-amber-300'; if(t==='Error') return 'bg-rose-900/40 text-rose-300'; return 'bg-gray-700 text-gray-300'; },
      severityClass(s) { const L=String(s||'').charAt(0).toUpperCase(); if(L==='E') return 'text-rose-400'; if(L==='W') return 'text-amber-400'; return 'text-slate-300'; },
      typeText(p) { if (!p) return ''; if (typeof p.Classification==='number') return p.Classification===2?'Restore':'Backup'; const t=(p.Type||'').toString().toLowerCase(); return t==='restore'?'Restore':'Backup'; },
      deviceName(p){ return (p && (p.DeviceName||p.DeviceID)) || ''; },
      sourceText(p){ return (p && (p.SourceDescription||p.SourceName)) || ''; },
      destinationName(p){ return (p && (p.DestinationDescription||p.DestinationName)) || ''; },
      protectedItemTitle(){ try{ return this.sourceText(this.props)||''; }catch(_){ return ''; } }
    };
  }
  </script>
  {/literal}
</div>

