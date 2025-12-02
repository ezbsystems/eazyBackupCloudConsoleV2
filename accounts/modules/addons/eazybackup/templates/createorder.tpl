<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<script src="{$WEB_ROOT}/assets/js/tooltips.js"></script>
<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<div class="min-h-screen bg-slate-950 text-gray-300">
  <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
  <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-relative w-full px-3 py-2 text-slate-300 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">

    {if $showCreateOrderAnnouncement}
      <div
  x-data="{ open: true }"
  x-show="open"
  x-cloak
  x-on:keydown.escape.prevent.stop="open=false; dismissAnnouncement()"
  class="fixed inset-0 z-50 flex items-center justify-center"
  role="dialog"
  aria-modal="true"
  aria-labelledby="order-process-title"
>
  <!-- Backdrop -->
  <div
    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
    @click="open=false; dismissAnnouncement()"
    x-show="open"
    x-transition.opacity
  ></div>

  <!-- Panel -->
  <div
    class="relative w-full max-w-xl mx-4 rounded-2xl bg-[#0b1220] text-gray-100 shadow-2xl ring-1 ring-white/10"
    x-show="open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
  >
    <!-- Close button -->
    <button
      type="button"
      class="absolute top-3 right-3 rounded-lg p-2 text-gray-400 hover:text-white hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-sky-500/50"
      @click="open=false; dismissAnnouncement()"
      aria-label="Close"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
        <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 01-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z" clip-rule="evenodd" />
      </svg>
    </button>

    <!-- Header -->
    <div class="px-6 pt-6">
      <div class="inline-flex items-center gap-2 rounded-full bg-sky-500/10 px-3 py-1 text-xs font-medium text-sky-300 ring-1 ring-inset ring-sky-500/20">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2a10 10 0 100 20 10 10 0 000-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/>
        </svg>
        Announcement
      </div>
      <h3 id="order-process-title" class="mt-3 text-2xl font-semibold tracking-tight text-white">
        New, simplified order process
      </h3>
      <p class="mt-2 text-sm text-gray-300 leading-6">
        One form for all new backup accounts and services. 
      </p>
    </div>

    <!-- Body -->
    <div class="px-6 mt-4 space-y-5 text-[15px] leading-7 text-gray-200">
      <div class="space-y-3">
        <p class="text-gray-300">
          Billing is usage-based for storage, devices, and add-ons. You do not need to select quantities — your actual usage is measured automatically and reflected each billing cycle.
        </p>
        <ul class="space-y-2">
          <li class="flex gap-3">
            <svg class="mt-1 h-4 w-4 flex-none" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2 9 19l11-11-1.4-1.4z"/></svg>
            <span>Storage, devices, and add-ons are tracked continuously.</span>
          </li>
          <li class="flex gap-3">
            <svg class="mt-1 h-4 w-4 flex-none" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.2 9 19l11-11-1.4-1.4z"/></svg>
            <span>No quantity pickers — you only pay for what you use.</span>
          </li>
        </ul>
      </div>

      <div class="border-t border-white/10 pt-4">
        <h4 class="text-sm font-medium text-white">Annual plans & prorating</h4>
        <p class="mt-2 text-gray-300">
          On annual billing, new or changed usage (for example, adding a device or increasing storage) incurs a prorated charge for the days remaining in your annual term.
          Credit is not applied for reduced usage during the term. If you expect usage to fluctuate, monthly billing may be a better fit.
        </p>
      </div>

      <div class="border-t border-white/10 pt-4">
        <p class="text-gray-400 text-sm">
          Questions about short-term or temporary increases? Contact your eazyBackup account manager.
        </p>
      </div>
    </div>

    <!-- Footer -->
    <div class="px-6 pb-6 pt-4 flex flex-col sm:flex-row gap-3 sm:justify-end">
      <button
        type="button"
        class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[#0b1220] focus:ring-sky-500 transition"
        @click="open=false; dismissAnnouncement()"
        x-ref="primaryBtn"
      >
        Got it
      </button>
      {* <a
        href="/knowledgebase/billing-details"
        class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-medium text-sky-300 hover:text-white hover:bg-white/5 ring-1 ring-white/10"
      >
        View billing details
      </a> *}
    </div>
  </div>

        <script>
          function dismissAnnouncement(){
            try {
              const body = new URLSearchParams();
              body.set('announcement_key', '{$createOrderAnnouncementKey|escape:'html'}');
              body.set('token', '{$csrfTokenPlain|escape:'html'}');
              fetch('{$dismissEndpointUrl|escape:'html'}', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(), credentials: 'same-origin' }).catch(()=>{});
            } catch(e) {}
          }
        </script>
      </div>
    {/if}

    <div class="mx-8 space-y-12">
      <div
        class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6 max-w-6xl mx-auto" data-eb-loader-host="1">
        <div class="flex items-center mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
          </svg>      
          <h2 class="text-2xl font-semibold text-white ml-2 flex items-center gap-2">Provision New Services</h2>
        </div>

        <!-- Loading Overlay -->
        {* <div id="loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
          <div class="flex items-center">
                <div class="text-gray-300 text-lg">Please Wait...</div>
                <svg class="animate-spin h-8 w-8 text-gray-300 ml-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div> *}

        <!-- Content Container -->
        <div class="job-row group relative overflow-hidden rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-sm">
          <div
            class="min-h-[calc(100vh-14rem)]" data-eb-loader-host="1">
            <div class="flex flex-col lg:flex-row gap-8" x-data="{
                    loading: false,
                    open: false,
                    // Mode: single | bulk (segmented control)
                    mode: 'single',
                    bulkTab: 'manual', // manual | csv
                    billingTerm: 'monthly',
                    termOpen: false,
                    termOptions: [
                      { value: 'monthly', label: 'Monthly' },
                      { value: 'annual',  label: 'Annual'  },
                    ],                
                    selectedProduct: '{if !empty($POST.product)}{$POST.product}{/if}',
                    selectedName: 'Choose a Service',
                    selectedIcon: '',
                    selectedDesc: '',
                    productType: '',
                    termDisabled : false,
                    // reseller flag from backend
                    isResellerClient: {if $isResellerClient}true{else}false{/if},
                    // payment gating
                    payment: {
                      isStripeDefault: {if $payment.isStripeDefault}true{else}false{/if},
                      hasCardOnFile: {if $payment.hasCardOnFile}true{else}false{/if},
                      showStripeCapture: {if $payment.showStripeCapture}true{else}false{/if},
                      defaultGateway: '{$payment.defaultGateway|escape:'html'}',
                      lastFour: '{$payment.lastFour|escape:'html'}',
                      addCardUrl: '{$payment.addCardUrl|escape:'html'}'
                    },
                    // Bulk state
                    rows: [{ username: '', password: '', email: '', errors: {} }],
                    csvErrors: [],
                    validation: { ok: false, summary: '', estimate: null },
                    prefix: '',
                    progress: { active: false, total: 0, done: 0, failed: 0 },
                    report: { visible: false, items: [] },
                    successMsg: '',
                    confirmOpen: false,
                    confirm: { product: '', term: '', count: 0, start: '' },
                    // email chips
                    emails: [],
                    emailEntry: '',
                    // pricing (pre-formatted)
                    pricing: {
                      60: { monthly: '{$pricing.60.monthly|escape:'html'}', annually: '{$pricing.60.annually|escape:'html'}' },
                      67: { monthly: '{$pricing.67.monthly|escape:'html'}', annually: '{$pricing.67.annually|escape:'html'}' },
                      88: { monthly: '{$pricing.88.monthly|escape:'html'}', annually: '{$pricing.88.annually|escape:'html'}' },
                      91: { monthly: '{$pricing.91.monthly|escape:'html'}', annually: '{$pricing.91.annually|escape:'html'}' },
                      97: { monthly: '{$pricing.97.monthly|escape:'html'}', annually: '{$pricing.97.annually|escape:'html'}' },
                      99: { monthly: '{$pricing.99.monthly|escape:'html'}', annually: '{$pricing.99.annually|escape:'html'}' },
                      102:{ monthly: '{$pricing.102.monthly|escape:'html'}',annually: '{$pricing.102.annually|escape:'html'}' }
                    },
                    // helpers to classify the selected product by PID
                    isMs365() {
                      const pid = parseInt(this.selectedProduct || 0, 10);
                      return [52,57].includes(pid);
                    },
                    isUsage() {
                      const pid = parseInt(this.selectedProduct || 0, 10);
                      // usage-based backup products (eazyBackup / OBC) and whitelabel/other usage SKUs
                      if ([58,60].includes(pid)) return true;
                      // treat anything that is not explicitly MS365 or VM-only as usage
                      if (!this.isMs365() && !this.isVm()) return !!pid;
                      return false;
                    },
                    isVm() {
                      const pid = parseInt(this.selectedProduct || 0, 10);
                      return [53,54].includes(pid);
                    },
                    init() {
                      this.updateProductType();
                      this.$watch('selectedProduct', () => this.updateProductType());
                      // hydrate emails
                      {if !empty($POST.reportemail)} this.addEmailsFromString('{$POST.reportemail|escape:'html'}'); {/if}
                      // Clear disallowed preselection for non-resellers
                      {if !$isResellerClient}
                      if ([60,57,54].includes(parseInt(this.selectedProduct||0,10))) {
                        this.selectedProduct = '';
                        this.selectedName = 'Choose a Service';
                        this.selectedDesc = '';
                        this.selectedIcon = '';
                        this.productType = '';
                      }
                      {/if}
                    },
                    updateProductType() {
                      const pid = parseInt(this.selectedProduct || 0, 10);
                      // reset term lock by default; MS365 will override via forceMonthly()
                      this.termDisabled = false;
                      if (!pid) {
                        this.productType = '';
                        return;
                      }
                      // Microsoft 365 backup products – always monthly
                      if (this.isMs365()) {
                        this.productType = 'ms365';
                        this.forceMonthly();
                        return;
                      }
                      // VM‑only backup products
                      if (this.isVm()) {
                        this.productType = 'vm';
                        return;
                      }
                      // everything else is usage‑based
                      if (this.isUsage()) {
                        this.productType = 'usage';
                        return;
                      }
                      this.productType = '';
                    },
                    forceMonthly() { this.billingTerm = 'monthly'; this.termDisabled = true; this.termOpen = false; },
                    canSubmit() { return !this.loading && (!this.payment.isStripeDefault || this.payment.hasCardOnFile); },
                    termLabel() { return this.billingTerm === 'annual' ? 'year' : 'month'; },
                    priceFor(cid) { const key = this.billingTerm === 'annual' ? 'annually' : 'monthly'; const o = this.pricing[cid]; return o && o[key] ? o[key] : 'Not configured'; },
                    // emails
                    addEmailsFromString(str) {
                      let added = 0;
                      if (!str) return added;
                      const parts = String(str).split(/[\s,;]+/);
                      for (const raw of parts) {
                        const e = raw.trim();
                        if (!e) continue;
                        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) continue;
                        const v = e.toLowerCase();
                        if (!this.emails.includes(v)) { this.emails.push(v); added++; }
                      }
                      this.syncEmailsHidden();
                      return added;
                    },
                    removeEmail(idx) { this.emails.splice(idx,1); this.syncEmailsHidden(); },
                    handleEmailKey(e) { if (['Enter','Tab'].includes(e.key) || e.key===',' || e.key===' ') { e.preventDefault(); const added = this.addEmailsFromString(this.emailEntry); if (added>0) this.emailEntry=''; } },
                    syncEmailsHidden() { const h = document.getElementById('reportemail'); if (h) { h.value = this.emails.join(', '); } },
                    handleEmailBlur() { const added = this.addEmailsFromString(this.emailEntry); if (added>0) { this.emailEntry=''; } },
                    // (Stripe capture removed; use native Add Card page)
                    submitWithLoader(ev) {
                      try {
                        var host = (ev && ev.target) ? ev.target.closest('[data-eb-loader-host]') : null;
                        if (!host) host = document.body;
                        if (window.ebShowLoader) ebShowLoader(host, 'Placing your order...');
                      } catch(_) { }
                    },
                    // Consolidated billing (hydrate from backend)
                    cb: {
                      enabled: {if $cbPref.enabled}true{else}false{/if},
                      dom: {if $cbPref.dom}{$cbPref.dom|escape:'html'}{else}1{/if},
                      tz: '{$cbPref.timezone|escape:'html'}',
                      locked: {if $cbPref.locked}true{else}false{/if},
                      today: '{$cbPref.today|escape:'html'}',
                      summary: '',
                      nfree: 0,
                    },
                    domOpen: false,
                    domError: '',
                    // Date helpers (Toronto assumed)
                    clampDay(y,m,d){
                      const last = new Date(Date.UTC(y, m, 0)).getUTCDate();
                      if (d < 1) d = 1; if (d > last) d = last; return d;
                    },
                    computeNextDue(){
                      if (!this.cb.enabled) { this.cb.summary=''; this.cb.nfree=0; return; }
                      const now = new Date();
                      const y = now.getFullYear();
                      const m = now.getMonth()+1; // 1..12
                      const d = now.getDate();
                      let dom = parseInt(this.cb.dom||0,10);
                      if (!(dom>=1 && dom<=31)) { this.domError = 'Enter a day between 1 and 31'; return; } else { this.domError=''; }
                      let ty=y, tm=m;
                      // Cap initial free period to max one month by using monthly logic for both terms.
                      if (d>dom) { // next month
                        const dt = new Date(y, m, 1);
                        dt.setMonth(dt.getMonth());
                        ty = dt.getFullYear(); tm = dt.getMonth()+1;
                        dom = this.clampDay(ty, tm, dom);
                      } else {
                        dom = this.clampDay(ty, tm, dom);
                      }
                      const cand = new Date(ty, tm-1, dom);
                      const yyyy = cand.getFullYear();
                      const mm = String(cand.getMonth()+1).padStart(2,'0');
                      const dd = String(cand.getDate()).padStart(2,'0');
                      const candStr = yyyy + '-' + mm + '-' + dd;
                      const base = new Date(y, m-1, d);
                      const msPerDay = 24*60*60*1000;
                      const ndays = Math.max(0, Math.floor((cand - base)/msPerDay));
                      this.cb.nfree = ndays;
                      const termLabel = (this.billingTerm==='annual') ? 'Next due (annual)' : 'Next due';
                      this.cb.summary = this.cb.today + ' • Promo — ' + ndays + ' days free • ' + termLabel + ': ' + candStr;
                    },
                    syncHidden(){
                      const a = document.getElementById('cb_enabled');
                      const b = document.getElementById('cb_dom');
                      if (a) a.value = this.cb.enabled ? '1' : '0';
                      if (b) b.value = String(this.cb.dom||'');
                    },
                    init(){
                      this.updateProductType();
                      this.$watch('selectedProduct', () => this.updateProductType());
                      {if !empty($POST.reportemail)} this.addEmailsFromString('{$POST.reportemail|escape:'html'}'); {/if}
                      {if !$isResellerClient}
                      if ([60,57,54].includes(parseInt(this.selectedProduct||0,10))) {
                        this.selectedProduct = '';
                        this.selectedName='Choose a Service'; this.selectedDesc=''; this.selectedIcon=''; this.productType='';
                      }
                      {/if}
                      this.computeNextDue();
                      this.$watch('billingTerm', () => { this.computeNextDue(); });
                      this.$watch('cb.enabled', () => { this.computeNextDue(); this.syncHidden(); });
                      this.$watch('cb.dom', () => { this.computeNextDue(); this.syncHidden(); });
                      this.syncHidden();
                    },
                    // Bulk helpers (UI only)
                    addRow(){ if (this.rows.length < 100) this.rows.push({ username:'', password:'', email:'', errors:{} }); },
                    removeRow(i){ this.rows.splice(i,1); },
                    genPassword(){
                      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%^&*()-_+=';
                      let s=''; for(let i=0;i<16;i++){ s+=chars[Math.floor(Math.random()*chars.length)]; } return s;
                    },
                    fillPasswords(){ this.rows.forEach(r => { if (!r.password) { r.password = this.genPassword(); } }); },
                    copyDown(field){ if(this.rows.length<2) return; const v=this.rows[0][field]||''; this.rows.forEach((r,i)=>{ if(i>0 && !r[field]) { r[field]=v; } }); },
                    genUsernames(){
                      const n = this.rows.length; const digits = String(n).length < 3 ? 3 : String(n).length;
                      let seq=1; this.rows.forEach(r => { if (!r.username) { r.username = (this.prefix?this.prefix+'-':'') + String(seq++).padStart(digits,'0'); } });
                    },
                    csvSelect(e){
                      try {
                        var f = (e && e.target && e.target.files) ? e.target.files[0] : null;
                        if (!f) return;
                        if (f.size > 5*1024*1024) { alert('CSV too large'); return; }
                        var r = new FileReader();
                        r.onload = () => {
                          try {
                            var lines = String(r.result).split(/\r?\n/);
                            if (lines.length > 500) { alert('Too many lines'); return; }
                            var hdr = (lines.shift()||'').trim().toLowerCase();
                            if (hdr !== 'username,password,email') { alert('Invalid header'); return; }
                            var nn = [];
                            for (var i=0;i<lines.length;i++) {
                              var ln = lines[i];
                              if (!ln || !ln.trim()) continue;
                              var m = ln.match(/^\s*&quot;?([^&quot;,]*)&quot;?\s*,\s*&quot;?([^&quot;,]*)&quot;?\s*,\s*&quot;?([^&quot;,]*)&quot;?\s*$/);
                              if (!m) { continue; }
                              var o = {}; o.username = m[1]; o.password = m[2]; o.email = m[3]; o.errors = {};
                              nn.push(o);
                            }
                            if (nn.length > 0) { this.rows = nn.slice(0,100); }
                          } catch(_) { }
                        };
                        r.readAsText(f);
                      } catch(_) { }
                    },
                    async bulkValidate(){
                      this.loading = true; this.validation = { ok:false, summary:'', estimate:null };
                      try{
                        const payload = (function(ctx){ var arr=[]; for (var i=0;i<ctx.rows.length;i++){ var rr=ctx.rows[i]||{}; arr.push({ldelim} username: rr.username||'', password: rr.password||'', email: rr.email||'' {rdelim}); } return {ldelim}
                          mode: 'bulk',
                          product_pid: parseInt(ctx.selectedProduct||0,10),
                          billing_term: (ctx.billingTerm==='annual' ? 'annual' : 'monthly'),
                          consolidated_billing: (ctx.cb.enabled ? 1 : 0),
                          rows: arr
                        {rdelim}; })(this);
                        const res = await fetch('{$modulelink}&a=bulk_validate', {ldelim} method:'POST', headers:{ldelim}'Content-Type':'application/json'{rdelim}, credentials:'same-origin', body: JSON.stringify(payload) {rdelim});
                        const j = await res.json();
                        this.validation.ok = !!j.ok; this.validation.summary = j.summary||''; this.validation.estimate = j.estimate||null;
                        // reset row errors then merge
                        this.rows.forEach(r => r.errors = {});
                        (j.row_errors||[]).forEach(err => { const i = err.index; if (i>=0 && i<this.rows.length) { const m = {}; for (const k in err) { if (k==='index') continue; if (Object.prototype.hasOwnProperty.call(err,k)) { m[k] = err[k]; } } this.rows[i].errors = m; } });
                      } finally { this.loading = false; }
                    },
                    openBulkConfirm(){
                      if (!this.validation.ok || (this.payment.isStripeDefault && !this.payment.hasCardOnFile) || this.progress.active) return;
                      this.confirm.product = this.selectedName || 'Selected Product';
                      this.confirm.term = (this.billingTerm==='annual' ? 'Annual' : 'Monthly');
                      this.confirm.count = (this.rows||[]).length;
                      this.confirm.start = this.cb.enabled ? (this.cb.summary||'') : new Date().toISOString().slice(0,10);
                      this.confirmOpen = true;
                    },
                    async confirmProvision(){
                      this.confirmOpen = false;
                      await this.bulkCreate();
                    },
                    async bulkCreate(){
                      if (!this.validation.ok || (this.payment.isStripeDefault && !this.payment.hasCardOnFile) || this.progress.active) return;
                      this.loading = true;
                      try{
                        // Prepare progress
                        var cleanRows = []; for (var i=0;i<this.rows.length;i++){ var rr=this.rows[i]||{}; cleanRows.push({ username: rr.username||'', password: rr.password||'', email: rr.email||'' }); }
                        this.progress.active = true; this.progress.total = cleanRows.length; this.progress.done = 0; this.progress.failed = 0;
                        this.report.visible = false; this.report.items = []; this.successMsg = '';
                        // Show eb loader
                        var host = null; try { host = document.querySelector('[data-eb-loader-host]'); if (window.ebShowLoader) ebShowLoader(host, 'Provisioning accounts...'); } catch(_){ }
                        // Provision sequentially to reflect real-time progress
                        for (var idx=0; idx<cleanRows.length; idx++){
                          var payloadOne = {}; payloadOne.product_pid = parseInt(this.selectedProduct||0,10); payloadOne.billing_term = (this.billingTerm==='annual' ? 'annual' : 'monthly'); payloadOne.consolidated_billing = (this.cb.enabled ? 1 : 0); payloadOne.rows = [ cleanRows[idx] ];
                          try {
                            const res = await fetch('{$modulelink}&a=bulk_create', {ldelim} method:'POST', headers:{ldelim}'Content-Type':'application/json'{rdelim}, credentials:'same-origin', body: JSON.stringify(payloadOne) {rdelim});
                            const j = await res.json();
                            var item = {}; item.username = cleanRows[idx].username; item.password = cleanRows[idx].password; item.email = cleanRows[idx].email; item.ok = (j && j.ok) ? true : false; this.report.items.push(item);
                            if (item.ok) { this.progress.done++; } else { this.progress.failed++; }
                          } catch(e) {
                            var item2 = {}; item2.username = cleanRows[idx].username; item2.password = cleanRows[idx].password; item2.email = cleanRows[idx].email; item2.ok = false; this.report.items.push(item2);
                            this.progress.failed++;
                          }
                        }
                        // Summary
                        this.successMsg = 'Provisioned ' + this.progress.done + ' of ' + this.progress.total + (this.progress.failed>0 ? (' ('+this.progress.failed+' failed)') : '');
                        this.report.visible = true;
                      } finally { this.loading = false; }
                      // Hide loader
                      try { if (window.ebHideLoader) { if (host) { ebHideLoader(host); } else { ebHideLoader(); } } } catch(_){ }
                      this.progress.active = false;
                    },
                    async copyReport(){
                      try {
                        var lines = ['username,password,email'];
                        for (var i=0;i<this.report.items.length;i++) { var it=this.report.items[i]; if (!it || !it.username) continue; lines.push((it.username||'')+','+(it.password||'')+','+(it.email||'')); }
                        var text = lines.join('\n');
                        if (navigator && navigator.clipboard && navigator.clipboard.writeText) { await navigator.clipboard.writeText(text); alert('Report copied to clipboard'); return; }
                        var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); alert('Report copied to clipboard');
                      } catch(_) { }
                    },
                  }">
              
              <div class="w-full lg:w-1/2 col-form" x-show="mode==='single'" x-transition x-cloak>
                <!-- Mode segmented control (positioned above Choose a Service) -->
                <div class="w-full mb-6">
                  <div class="inline-flex rounded-xl ring-1 ring-white/10 bg-[#0b1220] text-gray-300 shadow-sm overflow-hidden">
                    <button type="button" @click="mode='single'" class="px-4 py-2 text-sm transition" :class="mode==='single' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">Single Account</button>
                    <button type="button" @click="mode='bulk'" class="px-4 py-2 text-sm transition flex items-center gap-2" :class="mode==='bulk' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">Bulk Account Creation <span class="text-[10px] uppercase bg-sky-600/20 text-sky-300 px-2 py-0.5 rounded">Advanced</span></button>
                  </div>
                </div>
                <!-- Loader -->
                <div id="loader" class="loader text-center hidden">
                  <img src="{$BASE_PATH_IMG}/loader.svg" alt="Loading..." class="mx-auto mb-2">
                  <p class="text-gray-300">Processing your request… This may take up to 60 seconds.</p>
                </div>

                <div class="col-form w-full max-w-lg">
                  {if !empty($errors["error"])}
                    <div class="bg-red-700 text-gray-100 px-4 py-3 rounded mb-4">
                      {$errors["error"]}
                    </div>
                  {/if}


                  {* hide until Alpine kicks in *}
                  <style>
                    [x-cloak] {
                      display: none !important;
                    }
                  </style>

                  <form id="createorder" method="post" action="{$modulelink}&a=createorder" class="space-y-4 xl:space-y-8" @submit="loading=true; submitWithLoader($event)">
                    <!-- Product Selection -->
                    <div class="{if !empty($errors['product'])}border-red-500{/if}">
                      <label for="product" class="block text-sm font-medium text-gray-300 mb-1">
                        Choose a Service
                      </label>

                      <div class="relative mb-8">
                        <input type="hidden" id="productSelect" name="product" x-model="selectedProduct">

                        <!-- Closed button -->
                        <button type="button" @click="open = !open"
                          class="flex items-center w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-gray-500 text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition hover:bg-slate-700/50 {if !empty($errors['product'])}border-red-500{/if}">
                          <template x-if="selectedIcon">
                            <span class="flex-shrink-0 mr-3" x-html="selectedIcon"></span>
                          </template>
                          <div class="flex-1 text-left">
                            <div class="text-sm" x-text="selectedName"></div>
                            <template x-if="selectedDesc">
                              <div class="text-xs text-gray-400" x-text="selectedDesc"></div>
                            </template>
                          </div>
                        </button>

                        <!-- Dropdown menu -->
                        <div x-show="open" @click.away="open = false"
                          class="absolute z-10 w-full mt-1 bg-[#151f2e] border border-slate-700 rounded shadow-lg max-h-60 overflow-auto">
                          {foreach $categories.whitelabel as $product}
                            <div @click="
                                              selectedProduct = '{$product.pid}';
                                              selectedName    = '{$product.name}';
                                              selectedDesc    = 'Whitelabel backup client and Control Panel';
                                              selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                                              productType     = 'usage';
                                              open            = false
                                            " class="relative group flex items-start px-3 py-2 cursor-pointer hover:bg-slate-800/80 transition-colors duration-150">
                              
                              <div class="flex-shrink-0">
                                <!-- whitelabel SVG -->
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                  stroke="currentColor" class="w-6 h-6 text-gray-300 mr-3">
                                  <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                                </svg>
                              </div>
                              <div class="ml-1">
                                <div class="text-sm text-gray-100">{$product.name}</div>
                                <div class="text-xs text-gray-400">Whitelabel backup client and Control Panel</div>
                              </div>
                            </div>
                          {/foreach}                      

                          {foreach $categories.usage as $product}
                            {if $isResellerClient || $product.pid != 60}
                              <div @click="
                              selectedProduct = '{$product.pid}';
                                              selectedName    = `{$product.name}`;
                                              selectedDesc    = 'Usage-based billing';
                              selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                                              productType     = 'usage';
                              open            = false
                            " class="relative group flex items-start px-3 py-2 cursor-pointer hover:bg-slate-800/80 transition-colors duration-150">
                              <span class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                                <div class="flex-shrink-0">
                                  {* <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24" height="24" viewBox="0 0 50 50" style="fill:currentColor;" class="mr-3 {if $product.gid == 7}text-sky-500{else}text-orange-600{/if}"><path d="M20.13,32.5c-2.79-1.69-4.53-4.77-4.53-8.04V8.9c0-1.63,0.39-3.19,1.11-4.57L7.54,9.88C4.74,11.57,3,14.65,3,17.92v14.15 c0,1.59,0.42,3.14,1.16,4.5c0.69,1.12,1.67,2.06,2.88,2.74c2.53,1.42,5.51,1.36,7.98-0.15l8.02-4.9L20.13,32.5z M42.84,27.14 l-8.44-5.05v2.29c0,3.25-1.72,6.33-4.49,8.02l-13.84,8.47c1.52,0.93,3.19,1.42-4.87,1.46l8.93,5.41c1.5,0.91,3.19,1.36,4.87,1.36 s3.37-0.45,4.87-1.36l9.08-5.5l3.52-2.13c0.27-0.16,0.53-0.34,0.78-0.54c0.08-0.05,0.16-0.11,0.23-0.16 c0.65-0.53,1.23-1.13,1.71-1.79c0.02-0.03,0.04-0.06,0.06-0.09c0.77-1.19,1.2-2.59,1.19-4.06C46.43,30.85,45.09,28.48,42.84,27.14z M42.46,9.88l-9.57-5.79l-3.02-1.83C29.45,2,29.01,1.79,28.56,1.61c-0.49-0.21-1-0.37-1.51-0.47c-1.84-0.38-3.76-0.08-5.46,0.89 c-2.5,1.43-3.99,3.99-3.99,6.87v9.6l2.8-1.65c2.84-1.67,6.36-1.66,9.19,0.03l14.28,8.54c1.29,0.78,2.35,1.81,3.12,3.02L47,17.92 C47,14.65,45.26,11.57,42.46,9.88z"></path></svg>
                                  *}
                                {if $product.pid == 58}
                                  {* eazyBackup brand icon pid=58 *}
                                  <svg width="24" height="24" viewBox="0 0 256 253" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-3">
                                    <g transform="translate(0 -0)">
                                      <path d="M123.517 0.739711C135.162 3.20131 145.461 10.8791 151.254 21.3116C152.308 23.2457 154.297 27.8758 155.643 31.5682C156.989 35.2606 158.218 38.4841 158.335 38.7186C158.51 38.953 161.261 37.8394 164.421 36.257C173.491 31.6854 178.231 30.2788 185.779 29.9271C191.104 29.6927 193.094 29.8685 197.19 30.9235C209.479 33.9712 219.544 42.1765 224.927 53.4295C227.912 59.5835 228.907 63.8034 229.199 71.7156C229.55 80.1554 228.497 85.372 224.518 94.866C224.518 94.866 221.943 101.02 221.943 101.02C221.943 101.02 229.901 104.889 229.901 104.889C240.961 110.339 246.813 115.79 251.553 125.226C254.771 131.673 255.941 137.007 256 145.563C256 154.472 254.771 159.63 250.968 166.956C246.169 176.275 239.557 182.546 229.375 187.586C225.981 189.227 223.172 190.634 223.113 190.634C223.055 190.693 223.406 192.685 223.933 195.088C224.459 197.726 224.869 202.532 224.869 207.103C224.869 213.726 224.693 215.426 223.289 219.997C217.496 239.221 201.696 251.998 182.561 252.936C174.661 253.288 169.336 252.233 162.373 248.775C155.468 245.375 150.903 241.097 144.993 232.774C142.477 229.14 140.253 226.151 140.078 226.151C139.961 226.151 137.269 228.144 134.109 230.606C127.438 235.881 119.889 239.749 113.686 241.155C107.191 242.562 97.243 242.035 91.0402 239.925C81.0923 236.467 72.6658 228.847 67.8089 218.825C64.766 212.495 63.5957 207.748 62.6009 197.491C62.2498 194.092 61.9572 191.337 61.8401 191.22C61.7816 191.162 58.4462 191.748 54.4085 192.568C37.0289 196.026 23.4529 192.334 12.7443 181.257C2.67933 170.707 -1.53391 155.527 1.50898 140.699C3.38153 131.614 6.77553 125.402 14.5583 116.786C14.5583 116.786 19.7663 111.043 19.7663 111.043C19.7663 111.043 14.6168 105.592 14.6168 105.592C8.64807 99.262 5.72222 95.101 3.38153 89.533C-1.24132 78.3971 -1.12429 64.0378 3.73263 52.9606C7.7118 44.052 15.7286 35.3192 23.8625 31.0993C30.8261 27.4655 36.4437 26.0589 44.168 26.0589C51.8922 26.0589 55.4033 26.8795 64.4149 30.9821C67.9844 32.6232 71.0273 33.854 71.0859 33.7954C71.2029 33.6781 72.7243 30.6891 74.4799 27.1139C79.5708 16.9159 85.6566 10.1172 93.9075 5.48701C97.5356 3.43571 103.27 1.32581 107.366 0.505211C111.17 -0.256689 119.362 -0.139489 123.517 0.739711C123.517 0.739711 123.517 0.739711 123.517 0.739711Z" fill="#FE5000" fill-rule="evenodd" />
                                      <path d="M118.784 71.936C126.976 71.936 134.656 69.632 134.656 52.736C134.656 33.536 115.456 1.14441e-05 71.936 1.14441e-05C33.536 1.14441e-05 0 28.672 0 68.096C0 101.12 23.808 134.4 69.888 134.4C92.928 134.4 129.024 120.064 129.024 100.352C129.024 95.488 124.416 87.552 119.04 87.552C113.664 87.552 104.192 99.072 84.992 99.072C72.96 99.072 56.32 88.32 56.32 75.52C56.32 71.168 60.416 71.936 63.232 71.936C63.232 71.936 118.784 71.936 118.784 71.936C118.784 71.936 118.784 71.936 118.784 71.936ZM70.4 47.36C63.744 47.36 55.04 49.408 55.04 40.192C55.04 32 61.952 23.296 70.4 23.296C79.872 23.296 85.504 30.464 85.504 39.68C85.504 48.896 77.312 47.36 70.4 47.36C70.4 47.36 70.4 47.36 70.4 47.36Z" fill="#FFFFFF" transform="translate(55.352 57.184)" />
                                    </g>
                                  </svg>
                                {elseif $product.pid == 60}
                                  {* OBC brand icon pid=60 *}
                                  <svg width="24" height="24" viewBox="0 0 93.176 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-3">
                                    <defs>
                                      <clipPath id="clip_path_1">
                                        <rect width="93.176" height="32" />
                                      </clipPath>
                                      <linearGradient id="gradient_2" gradientUnits="userSpaceOnUse" x1="95" y1="-0" x2="-5.135" y2="38.4">
                                        <stop offset="0.089" stop-color="#EBAC36" />
                                        <stop offset="0.94" stop-color="#FE5000" />
                                      </linearGradient>
                                      <linearGradient id="gradient_3" gradientUnits="userSpaceOnUse" x1="480.604" y1="0" x2="-0" y2="341.333">
                                        <stop offset="0.086" stop-color="#EBAC36" />
                                        <stop offset="0.979" stop-color="#FE5000" />
                                      </linearGradient>
                                    </defs>
                                    <g clip-path="url(#clip_path_1)">
                                      <path d="M16.446 0C21.0896 0 24.6287 0.764046 27.0634 2.29212C29.498 3.82017 31.1023 5.74135 31.8763 8.05569C32.6503 10.37 33.0371 13.0107 33.0371 15.9777C33.0371 21.7339 31.4894 25.8433 28.3936 28.306C25.2978 30.7687 21.3476 32 16.5427 32C13.9952 32 11.5525 31.6661 9.21461 30.9986C6.87669 30.3308 4.75646 28.7881 2.85387 26.37C0.951289 23.952 0 20.4582 0 15.8885C0 13.6633 0.193484 11.7274 0.580447 10.0807C0.967414 8.43406 1.58011 7.01717 2.41853 5.83001C4.0309 3.51575 6.03022 1.96552 8.4165 1.17931C10.8028 0.393105 13.4793 0 16.446 0ZM16.4943 26.83C19.7835 26.83 22.1296 25.8832 23.5323 23.9896C24.9351 22.0958 25.6365 19.4326 25.6365 15.9999C25.6365 11.9461 24.8463 9.12757 23.2662 7.54452C21.6862 5.96144 19.4288 5.16991 16.4943 5.16991C13.6566 5.16991 11.4235 6.0724 9.79502 7.8774C8.16653 9.68241 7.35228 12.3456 7.35228 15.867C7.35228 19.2402 7.93273 21.9107 9.09363 23.8783C10.2545 25.8461 12.7215 26.83 16.4943 26.83ZM60.7052 15.9554C62.4465 16.7005 63.7122 17.6168 64.5023 18.7044C65.2923 19.7918 65.6875 21.1549 65.6875 22.7937C65.6875 25.446 64.9698 27.5395 63.5347 29.0741C62.0998 30.6086 59.1413 31.376 54.659 31.376C54.659 31.376 36.4715 31.376 36.4715 31.376C36.4715 31.376 36.4715 0.579388 36.4715 0.579388C36.4715 0.579388 53.5063 0.579388 53.5063 0.579388C57.3239 0.579388 60.3086 1.12163 62.4601 2.20612C64.6116 3.29062 65.6875 5.55617 65.6875 9.00275C65.6875 10.5478 65.3003 11.8551 64.5266 12.9248C63.7526 13.9944 62.4789 15.0046 60.7052 15.9554ZM53.4663 13.2367C55.0242 13.2367 56.225 12.9767 57.0691 12.4568C57.9129 11.9368 58.3349 10.9935 58.3349 9.6267C58.3349 7.19032 56.761 5.97212 53.6128 5.97212C53.6128 5.97212 43.3402 5.97212 43.3402 5.97212C43.3402 5.97212 43.3402 13.2367 43.3402 13.2367C43.3402 13.2367 53.4663 13.2367 53.4663 13.2367ZM53.4981 26.0278C55.0136 26.0278 56.1986 25.7587 57.0532 25.2207C57.9077 24.6825 58.3349 23.7261 58.3349 22.3509C58.3349 19.8402 56.771 18.5849 53.6432 18.5849C53.6432 18.5849 43.3402 18.5849 43.3402 18.5849C43.3402 18.5849 43.3402 26.0278 43.3402 26.0278C43.3402 26.0278 53.4981 26.0278 53.4981 26.0278ZM83.3427 31.376C78.6346 31.376 74.894 30.1634 72.1206 27.7385C69.3474 25.3134 67.9608 21.5866 67.9608 16.5578C67.9608 11.6483 69.1298 7.75786 71.4678 4.88652C73.8055 2.0151 77.8285 0.579388 83.5361 0.579388C83.5361 0.579388 95 0.579388 95 0.579388C95 0.579388 95 5.66016 95 5.66016C95 5.66016 83.633 5.66016 83.633 5.66016C80.4405 5.66016 78.2719 6.60755 77.1269 8.50236C75.9822 10.3972 75.4098 12.8964 75.4098 16C75.4098 16 75.4098 17.5222 75.4098 17.5222C75.4098 19.999 75.9178 22.0655 76.9336 23.7217C77.9494 25.378 80.1824 26.206 83.633 26.206C83.633 26.206 95 26.206 95 26.206C95 26.206 95 31.376 95 31.376C95 31.376 83.3427 31.376 83.3427 31.376Z" fill="url(#gradient_2)" />
                                      <path d="M412.907 128.747C398.4 55.36 333.653 0 256 0C194.347 0 140.907 34.9867 114.133 86.08C50.0267 93.0133 0 147.307 0 213.333C0 284.053 57.28 341.333 128 341.333C128 341.333 405.333 341.333 405.333 341.333C464.213 341.333 512 293.547 512 234.667C512 178.347 468.16 132.693 412.907 128.747C412.907 128.747 412.907 128.747 412.907 128.747Z" fill="url(#gradient_3)" fill-rule="evenodd" transform="translate(0 85)" />
                                      <path d="M412.907 128.747C398.4 55.36 333.653 0 256 0C194.347 0 140.907 34.9867 114.133 86.08C50.0267 93.0133 0 147.307 0 213.333C0 284.053 57.28 341.333 128 341.333C128 341.333 405.333 341.333 405.333 341.333C464.213 341.333 512 293.547 512 234.667C512 178.347 468.16 132.693 412.907 128.747C412.907 128.747 412.907 128.747 412.907 128.747Z" fill="url(#gradient_3)" fill-rule="evenodd" transform="translate(0 85)" />
                                      <path d="M412.907 128.747C398.4 55.36 333.653 0 256 0C194.347 0 140.907 34.9867 114.133 86.08C50.0267 93.0133 0 147.307 0 213.333C0 284.053 57.28 341.333 128 341.333C128 341.333 405.333 341.333 405.333 341.333C464.213 341.333 512 293.547 512 234.667C512 178.347 468.16 132.693 412.907 128.747C412.907 128.747 412.907 128.747 412.907 128.747Z" fill="url(#gradient_3)" fill-rule="evenodd" transform="translate(0 85)" />
                                      <path d="M412.907 128.747C398.4 55.36 333.653 0 256 0C194.347 0 140.907 34.9867 114.133 86.08C50.0267 93.0133 0 147.307 0 213.333C0 284.053 57.28 341.333 128 341.333C128 341.333 405.333 341.333 405.333 341.333C464.213 341.333 512 293.547 512 234.667C512 178.347 468.16 132.693 412.907 128.747C412.907 128.747 412.907 128.747 412.907 128.747Z" fill="url(#gradient_3)" fill-rule="evenodd" transform="translate(0 85)" />
                                    </g>
                                  </svg>
                                {/if}
                              </div>
                              <div class="ml-1">
                              <div class="text-sm text-gray-100">{$product.name}</div>
                              <div class="text-xs text-gray-400">{if $product.pid == 60}OBC Branded Backup Client{elseif $product.pid == 58}eazyBackup Branded Backup Client{else}Usage-based billing{/if}</div>
                            </div>
                          </div>
                          {/if}
                        {/foreach}

                        {foreach $categories.ms365 as $product}
                          {if $isResellerClient || $product.pid != 57}
                          <div @click="
                                            selectedProduct = '{$product.pid}';
                                            selectedName    = `{$product.name}`;
                                            selectedDesc    = 'Cloud to Cloud backup for Microsoft 365 data';
                                            selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                                            productType     = 'ms365';
                                            open            = false
                                          " class="relative group flex items-start px-3 py-2 cursor-pointer hover:bg-slate-800/80 transition-colors duration-150">
                            <span
                              class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                            <div class="flex-shrink-0">
                              <!-- eazyBackup (gid=6) orange, OBC (gid=7) sky -->
                              <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24" height="24"
                                viewBox="0 0 50 50" style="fill:currentColor;" class="mr-3 {if $product.gid == 7}text-sky-500{else}text-orange-600{/if}">
                                <path d="M20.13,32.5c-2.79-1.69-4.53-4.77-4.53-8.04V8.9c0-1.63,0.39-3.19,1.11-4.57L7.54,9.88C4.74,11.57,3,14.65,3,17.92v14.15
                                          c0,1.59,0.42,3.14,1.16,4.5c0.69,1.12,1.67,2.06,2.88,2.74c2.53,1.42,5.51,1.36,7.98-0.15l8.02-4.9L20.13,32.5z M42.84,27.14
                                          l-8.44-5.05v2.29c0,3.25-1.72,6.33-4.49,8.02l-13.84,8.47c-1.52,0.93-3.19,1.42-4.87,1.46l8.93,5.41c1.5,0.91,3.19,1.36,4.87,1.36
                                          s3.37-0.45,4.87-1.36l9.08-5.5l3.52-2.13c0.27-0.16,0.53-0.34,0.78-0.54c0.08-0.05,0.16-0.11,0.23-0.16
                                          c0.65-0.53,1.23-1.13,1.71-1.79c0.02-0.03,0.04-0.06,0.06-0.09c0.77-1.19,1.2-2.59,1.19-4.06C46.43,30.85,45.09,28.48,42.84,27.14z
                                          M42.46,9.88l-9.57-5.79l-3.02-1.83C29.45,2,29.01,1.79,28.56,1.61c-0.49-0.21-1-0.37-1.51-0.47c-1.84-0.38-3.76-0.08-5.46,0.89
                                          c-2.5,1.43-3.99,3.99-3.99,6.87v9.6l2.8-1.65c2.84-1.67,6.36-1.66,9.19,0.03l14.28,8.54c1.29,0.78,2.35,1.81,3.12,3.02L47,17.92
                                          C47,14.65,45.26,11.57,42.46,9.88z"></path>
                              </svg>

                            </div>
                            <div class="ml-1">
                              <div class="text-sm text-gray-100">{$product.name}</div>
                              <div class="text-xs text-gray-400">Cloud to Cloud backup for Microsoft 365 data</div>
                            </div>
                          </div>
                          {/if}
                        {/foreach}
                        
                        {foreach $categories.hyperv as $product}
                          {if $isResellerClient || $product.pid != 54}
                          <div @click="
                                            selectedProduct = '{$product.pid}';
                                            selectedName    = `{$product.name}`;
                                            selectedDesc    = 'Backup for Virtual Machines';
                                            selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                                            productType     = 'usage';
                                            open            = false
                                          " class="relative group flex items-start px-3 py-2 cursor-pointer hover:bg-slate-800/80 transition-colors duration-150">
                            <span class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                            <div class="flex-shrink-0 {if $product.gid == 7}text-sky-500{else}text-orange-600{/if}">
                            {if $product.pid == 53}
                              {* eazyBackup brand icon for Virtual Server product *}
                              <svg width="24" height="24" viewBox="0 0 256 253" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-3">
                                <g transform="translate(0 -0)">
                                  <path d="M123.517 0.739711C135.162 3.20131 145.461 10.8791 151.254 21.3116C152.308 23.2457 154.297 27.8758 155.643 31.5682C156.989 35.2606 158.218 38.4841 158.335 38.7186C158.51 38.953 161.261 37.8394 164.421 36.257C173.491 31.6854 178.231 30.2788 185.779 29.9271C191.104 29.6927 193.094 29.8685 197.19 30.9235C209.479 33.9712 219.544 42.1765 224.927 53.4295C227.912 59.5835 228.907 63.8034 229.199 71.7156C229.55 80.1554 228.497 85.372 224.518 94.866C224.518 94.866 221.943 101.02 221.943 101.02C221.943 101.02 229.901 104.889 229.901 104.889C240.961 110.339 246.813 115.79 251.553 125.226C254.771 131.673 255.941 137.007 256 145.563C256 154.472 254.771 159.63 250.968 166.956C246.169 176.275 239.557 182.546 229.375 187.586C225.981 189.227 223.172 190.634 223.113 190.634C223.055 190.693 223.406 192.685 223.933 195.088C224.459 197.726 224.869 202.532 224.869 207.103C224.869 213.726 224.693 215.426 223.289 219.997C217.496 239.221 201.696 251.998 182.561 252.936C174.661 253.288 169.336 252.233 162.373 248.775C155.468 245.375 150.903 241.097 144.993 232.774C142.477 229.14 140.253 226.151 140.078 226.151C139.961 226.151 137.269 228.144 134.109 230.606C127.438 235.881 119.889 239.749 113.686 241.155C107.191 242.562 97.243 242.035 91.0402 239.925C81.0923 236.467 72.6658 228.847 67.8089 218.825C64.766 212.495 63.5957 207.748 62.6009 197.491C62.2498 194.092 61.9572 191.337 61.8401 191.22C61.7816 191.162 58.4462 191.748 54.4085 192.568C37.0289 196.026 23.4529 192.334 12.7443 181.257C2.67933 170.707 -1.53391 155.527 1.50898 140.699C3.38153 131.614 6.77553 125.402 14.5583 116.786C14.5583 116.786 19.7663 111.043 19.7663 111.043C19.7663 111.043 14.6168 105.592 14.6168 105.592C8.64807 99.262 5.72222 95.101 3.38153 89.533C-1.24132 78.3971 -1.12429 64.0378 3.73263 52.9606C7.7118 44.052 15.7286 35.3192 23.8625 31.0993C30.8261 27.4655 36.4437 26.0589 44.168 26.0589C51.8922 26.0589 55.4033 26.8795 64.4149 30.9821C67.9844 32.6232 71.0273 33.854 71.0859 33.7954C71.2029 33.6781 72.7243 30.6891 74.4799 27.1139C79.5708 16.9159 85.6566 10.1172 93.9075 5.48701C97.5356 3.43571 103.27 1.32581 107.366 0.505211C111.17 -0.256689 119.362 -0.139489 123.517 0.739711C123.517 0.739711 123.517 0.739711 123.517 0.739711Z" fill="#FE5000" fill-rule="evenodd" />
                                  <path d="M118.784 71.936C126.976 71.936 134.656 69.632 134.656 52.736C134.656 33.536 115.456 1.14441e-05 71.936 1.14441e-05C33.536 1.14441e-05 0 28.672 0 68.096C0 101.12 23.808 134.4 69.888 134.4C92.928 134.4 129.024 120.064 129.024 100.352C129.024 95.488 124.416 87.552 119.04 87.552C113.664 87.552 104.192 99.072 84.992 99.072C72.96 99.072 56.32 88.32 56.32 75.52C56.32 71.168 60.416 71.936 63.232 71.936C63.232 71.936 118.784 71.936 118.784 71.936C118.784 71.936 118.784 71.936 118.784 71.936ZM70.4 47.36C63.744 47.36 55.04 49.408 55.04 40.192C55.04 32 61.952 23.296 70.4 23.296C79.872 23.296 85.504 30.464 85.504 39.68C85.504 48.896 77.312 47.36 70.4 47.36C70.4 47.36 70.4 47.36 70.4 47.36Z" fill="#FFFFFF" transform="translate(55.352 57.184)" />
                                </g>
                              </svg>
                            {elseif $product.pid == 54}
                              {* OBC brand icon for Virtual Server product *}
                              <svg width="24" height="24" viewBox="0 0 93.176 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-3">
                                <defs>
                                  <clipPath id="clip_path_1">
                                    <rect width="93.176" height="32" />
                                  </clipPath>
                                  <linearGradient id="gradient_2" gradientUnits="userSpaceOnUse" x1="95" y1="-0" x2="-5.135" y2="38.4">
                                    <stop offset="0.089" stop-color="#EBAC36" />
                                    <stop offset="0.94" stop-color="#FE5000" />
                                  </linearGradient>
                                  <linearGradient id="gradient_3" gradientUnits="userSpaceOnUse" x1="480.604" y1="0" x2="-0" y2="341.333">
                                    <stop offset="0.086" stop-color="#EBAC36" />
                                    <stop offset="0.979" stop-color="#FE5000" />
                                  </linearGradient>
                                </defs>
                                <g clip-path="url(#clip_path_1)">
                                  <path d="M16.446 0C21.0896 0 24.6287 0.764046 27.0634 2.29212C29.498 3.82017 31.1023 5.74135 31.8763 8.05569C32.6503 10.37 33.0371 13.0107 33.0371 15.9777C33.0371 21.7339 31.4894 25.8433 28.3936 28.306C25.2978 30.7687 21.3476 32 16.5427 32C13.9952 32 11.5525 31.6661 9.21461 30.9986C6.87669 30.3308 4.75646 28.7881 2.85387 26.37C0.951289 23.952 0 20.4582 0 15.8885C0 13.6633 0.193484 11.7274 0.580447 10.0807C0.967414 8.43406 1.58011 7.01717 2.41853 5.83001C4.0309 3.51575 6.03022 1.96552 8.4165 1.17931C10.8028 0.393105 13.4793 0 16.446 0ZM16.4943 26.83C19.7835 26.83 22.1296 25.8832 23.5323 23.9896C24.9351 22.0958 25.6365 19.4326 25.6365 15.9999C25.6365 11.9461 24.8463 9.12757 23.2662 7.54452C21.6862 5.96144 19.4288 5.16991 16.4943 5.16991C13.6566 5.16991 11.4235 6.0724 9.79502 7.8774C8.16653 9.68241 7.35228 12.3456 7.35228 15.867C7.35228 19.2402 7.93273 21.9107 9.09363 23.8783C10.2545 25.8461 12.7215 26.83 16.4943 26.83ZM60.7052 15.9554C62.4465 16.7005 63.7122 17.6168 64.5023 18.7044C65.2923 19.7918 65.6875 21.1549 65.6875 22.7937C65.6875 25.446 64.9698 27.5395 63.5347 29.0741C62.0998 30.6086 59.1413 31.376 54.659 31.376C54.659 31.376 36.4715 31.376 36.4715 31.376C36.4715 31.376 36.4715 0.579388 36.4715 0.579388C36.4715 0.579388 53.5063 0.579388 53.5063 0.579388C57.3239 0.579388 60.3086 1.12163 62.4601 2.20612C64.6116 3.29062 65.6875 5.55617 65.6875 9.00275C65.6875 10.5478 65.3003 11.8551 64.5266 12.9248C63.7526 13.9944 62.4789 15.0046 60.7052 15.9554ZM53.4663 13.2367C55.0242 13.2367 56.225 12.9767 57.0691 12.4568C57.9129 11.9368 58.3349 10.9935 58.3349 9.6267C58.3349 7.19032 56.761 5.97212 53.6128 5.97212C53.6128 5.97212 43.3402 5.97212 43.3402 5.97212C43.3402 5.97212 43.3402 13.2367 43.3402 13.2367C43.3402 13.2367 53.4663 13.2367 53.4663 13.2367ZM53.4981 26.0278C55.0136 26.0278 56.1986 25.7587 57.0532 25.2207C57.9077 24.6825 58.3349 23.7261 58.3349 22.3509C58.3349 19.8402 56.771 18.5849 53.6432 18.5849C53.6432 18.5849 43.3402 18.5849 43.3402 18.5849C43.3402 18.5849 43.3402 26.0278 43.3402 26.0278C43.3402 26.0278 53.4981 26.0278 53.4981 26.0278ZM83.3427 31.376C78.6346 31.376 74.894 30.1634 72.1206 27.7385C69.3474 25.3134 67.9608 21.5866 67.9608 16.5578C67.9608 11.6483 69.1298 7.75786 71.4678 4.88652C73.8055 2.0151 77.8285 0.579388 83.5361 0.579388C83.5361 0.579388 95 0.579388 95 0.579388C95 0.579388 95 5.66016 95 5.66016C95 5.66016 83.633 5.66016 83.633 5.66016C80.4405 5.66016 78.2719 6.60755 77.1269 8.50236C75.9822 10.3972 75.4098 12.8964 75.4098 16C75.4098 16 75.4098 17.5222 75.4098 17.5222C75.4098 19.999 75.9178 22.0655 76.9336 23.7217C77.9494 25.378 80.1824 26.206 83.633 26.206C83.633 26.206 95 26.206 95 26.206C95 26.206 95 31.376 95 31.376C95 31.376 83.3427 31.376 83.3427 31.376Z" fill="url(#gradient_2)" />
                                  <path d="M412.907 128.747C398.4 55.36 333.653 0 256 0C194.347 0 140.907 34.9867 114.133 86.08C50.0267 93.0133 0 147.307 0 213.333C0 284.053 57.28 341.333 128 341.333C128 341.333 405.333 341.333 405.333 341.333C464.213 341.333 512 293.547 512 234.667C512 178.347 468.16 132.693 412.907 128.747C412.907 128.747 412.907 128.747 412.907 128.747Z" fill="url(#gradient_3)" fill-rule="evenodd" transform="translate(0 85)" />
                                </g>
                              </svg>
                            {else}
                              {* Fallback generic VM/server icon *}
                              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" class="mr-3 size-6">
                                <path d="M5.507 4.048A3 3 0 0 1 7.785 3h8.43a3 3 0 0 1 2.278 1.048l1.722 2.008A4.533 4.533 0 0 0 19.5 6h-15c-.243 0-.482.02-.715.056l1.722-2.008Z" />
                                <path fill-rule="evenodd" d="M1.5 10.5a3 3 0 0 1 3-3h15a3 3 0 1 1 0 6h-15a3 3 0 0 1-3-3Zm15 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm2.25.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM4.5 15a3 3 0 1 0 0 6h15a3 3 0 1 0 0-6h-15Zm11.25 3.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM19.5 18a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" clip-rule="evenodd" />
                              </svg>
                            {/if}
                            </div>
                            <div class="ml-1">
                              <div class="text-sm text-gray-100">{$product.name}</div>
                              <div class="text-xs text-gray-400">Backup for Hyper-V, Proxmox, and VMware, no device charges</div>
                              </div>
                            </div>
                          {/if}
                          {/foreach}

                      </div>

                      {if !empty($errors['product'])}
                        <span id="product-help" class="text-red-500 text-sm">{$errors['product']}</span>
                      {/if}

                    </div>
                  </div>




                  <!-- Consolidated Billing (Future Orders Only) -->
                  <div class="mb-8">
                    <div class="flex items-start">
                      <label class="w-1/4 text-sm font-medium text-gray-300">Consolidated billing</label>
                      <div class="w-3/4 space-y-3">
                        <!-- Toggle switch -->
                        <div class="flex items-center gap-3">
                          <button type="button"
                            :aria-pressed="cb.enabled ? 'true' : 'false'"
                            role="switch"
                            :aria-checked="cb.enabled ? 'true' : 'false'"
                            @click="if(!cb.locked){ cb.enabled = !cb.enabled }"
                            :class="cb.locked ? 'opacity-50 cursor-not-allowed' : ''"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-sky-500/50"
                            :style="cb.enabled ? 'background-color: rgb(2 132 199 / 1)' : 'background-color: rgb(30 41 59 / 1)'"></button>
                          <span class="text-sm text-gray-300" x-text="cb.locked ? 'Enabled' : 'Consolidate billing for future orders.'"></span>
                          <input type="hidden" id="cb_enabled" name="cb_enabled" value="0">
                        </div>

                        <!-- Day picker (1–31) -->
                        <div class="relative" x-show="cb.enabled" x-cloak>
                          <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-300">Day of month</span>
                            <button type="button" @click="!cb.locked && (domOpen=!domOpen)" :disabled="cb.locked"
                              class="px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                              :class="cb.locked ? 'opacity-50 cursor-not-allowed' : ''"
                              x-text="cb.dom"></button>
                            <input type="hidden" id="cb_dom" name="cb_dom" :value="cb.dom">
                          </div>
                          <div x-show="domOpen" @click.away="domOpen=false" x-cloak
                            class="absolute z-10 mt-1 bg-[#151f2e] border border-sky-600 rounded shadow-lg max-h-48 overflow-auto w-40">
                            <template x-for="n in 31" :key="n">
                              <div @click="cb.dom=n; domOpen=false"
                                class="px-3 py-1 cursor-pointer hover:bg-slate-700/50 text-gray-300"
                                x-text="n"></div>
                            </template>
                          </div>
                          <template x-if="domError">
                            <p class="text-red-500 text-xs mt-1" x-text="domError"></p>
                          </template>
                          <p x-show="cb.enabled && !cb.locked" x-cloak class="text-xs text-gray-400 mt-2">Selected billing date applies to this order and all future orders. Existing plans keep their current billing dates. To change past orders, contact Sales.</p>
                        </div>

                        <!-- Summary row -->
                        <div x-show="cb.enabled" x-cloak class="text-sm text-gray-300 select-none">
                          <span x-text="cb.summary"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Username Field -->
                  <div class="mb-8 {if !empty($errors.username)}border-red-500{/if}">
                    <div class="flex items-center">
                      <!-- Label + Tooltip Icon -->
                      <label for="username" class="w-1/4 text-sm font-medium text-gray-300 flex items-center">
                        Username
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                          stroke="currentColor" class="w-5 h-5 text-gray-400 ml-2 cursor-pointer"
                          data-tippy-content="At least 6 characters; letters, numbers, underscore, dot, or dash.">
                          <path stroke-linecap="round" stroke-linejoin="round"
                            d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                      </label>

                      <!-- Input -->
                      <div class="w-3/4">
                        <input type="text" id="username" name="username" placeholder="Username"
                          class="w-full px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-gray-500 text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 {if !empty($errors.username)}border-red-500{/if} transition hover:bg-slate-700/50"
                          {if !empty($POST.username)}value="{$POST.username|escape:"html"}" {/if}>
                        {if !empty($errors.username)}
                          <p class="text-red-500 text-xs mt-1">{$errors.username}</p>
                        {/if}
                      </div>
                    </div>
                  </div>

                  <div x-data="{ldelim} showPassword: false, showConfirmPassword: false {rdelim}"
                    class="mb-8 {if !empty($errors.password) || !empty($errors.confirmpassword)}border-red-500{/if}">
                    <div class="flex items-center">
                      <!-- Left label + tooltip icon -->
                      <label for="password" class="w-1/4 text-sm font-medium text-gray-300 flex items-center">
                        Password
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                          stroke="currentColor" class="w-5 h-5 text-gray-400 ml-2 cursor-pointer"
                          data-tippy-content="At least 8 characters, mix of upper/lowercase, numbers & symbols.">
                          <path stroke-linecap="round" stroke-linejoin="round"
                            d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                      </label>

                      <!-- Inputs -->
                      <div class="w-3/4 flex space-x-4">
                        <!-- Create Password -->
                        <div class="flex-1 relative">
                          <input :type="showPassword ? 'text' : 'password'" id="password" name="password"
                            placeholder="Create password"
                            class="w-full px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-gray-500 text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition hover:bg-slate-700/50 {if !empty($errors.password)}border-red-500{/if}">
                          <button type="button" @click="showPassword = !showPassword" tabindex="-1"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <!-- Hidden Icon -->
                            <svg x-show="!showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                              viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 
                                7.244 19.5 12 19.5c.993 0 1.953-.138 
                                2.863-.395M6.228 6.228A10.451 10.451 0 0 1 
                                12 4.5c4.756 0 8.773 3.162 10.065 
                                7.498a10.522 10.522 0 0 1-4.293 
                                5.774M6.228 6.228l-3.228-3.228m3.228 
                                3.228l3.65 3.65m7.894 7.894L21 
                                21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 
                                1 0-4.243-4.243m4.242 4.242L9.88 
                                9.88" />
                            </svg>
                            <!-- Visible Icon -->
                            <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                              viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 
                                7.51 7.36 4.5 12 4.5c4.638 0 8.573 
                                3.007 9.963 7.178.07.207.07.431 
                                0 .639C20.577 16.49 16.64 19.5 12 
                                19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                              <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                          </button>

                          {if !empty($errors.password)}
                            <p class="text-red-500 text-xs mt-1">{$errors.password}</p>
                          {/if}
                        </div>

                        <!-- Confirm Password -->
                        <div class="flex-1 relative">
                          <input :type="showConfirmPassword ? 'text' : 'password'" id="confirmpassword"
                            name="confirmpassword" placeholder="Confirm password"
                            class="w-full px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-gray-500 text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition hover:bg-slate-700/50 {if !empty($errors.confirmpassword)}border-red-500{/if}">
                          <button type="button" @click="showConfirmPassword = !showConfirmPassword" tabindex="-1"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <!-- Hidden Icon -->
                            <svg x-show="!showConfirmPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                              viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 
                                7.244 19.5 12 19.5c.993 0 1.953-.138 
                                2.863-.395M6.228 6.228A10.451 10.451 0 0 1 
                                12 4.5c4.756 0 8.773 3.162 10.065 
                                7.498a10.522 10.522 0 0 1-4.293 
                                5.774M6.228 6.228l-3.228-3.228m3.228 
                                3.228l3.65 3.65m7.894 7.894L21 
                                21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 
                                1 0-4.243-4.243m4.242 4.242L9.88 
                                9.88" />
                            </svg>
                            <!-- Visible Icon -->
                            <svg x-show="showConfirmPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                              viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 
                                7.51 7.36 4.5 12 4.5c4.638 0 8.573 
                                3.007 9.963 7.178.07.207.07.431 
                                0 .639C20.577 16.49 16.64 19.5 12 
                                19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                              <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                          </button>

                          {if !empty($errors.confirmpassword)}
                            <p class="text-red-500 text-xs mt-1">{$errors.confirmpassword}</p>
                          {/if}
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Reporting‑email (chips) -->
                  <div class="mb-8">
                    <div class="flex items-center">
                      <label for="reportemail" class="w-1/4 text-sm font-medium text-gray-300 flex items-center">
                        Email for Reports
                      </label>
                      <div class="w-3/4">
                        <div class="flex flex-wrap gap-2 mb-2">
                          <template x-for="(e,idx) in emails" :key="e">
                            <span class="inline-flex items-center px-2 py-1 rounded bg-slate-700 text-xs">
                              <span x-text="e"></span>
                              <button type="button" @click="removeEmail(idx)" class="ml-1 text-gray-300 hover:text-white focus:outline-none">&times;</button>
                            </span>
                          </template>
                        </div>
                        <input type="text" id="reportemail_entry" x-model="emailEntry" @keydown="handleEmailKey($event)" @blur="handleEmailBlur()" placeholder="backupreports@example.com"
                          class="w-full px-3 py-2 border {if !empty($errors.reportemail)}border-red-500{else}border-slate-700{/if} rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-gray-500 text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition hover:bg-slate-700/50">
                        <input type="hidden" id="reportemail" name="reportemail" value="{$POST.reportemail|escape:'html'}">
                        <p class="text-xs text-gray-400 mt-1">Enter one or more emails, press enter or tab after each email.</p>
                      </div>
                    </div>

                    {if !empty($errors.reportemail)}
                      <p class="text-red-500 text-xs mt-1">{$errors.reportemail}</p>
                    {/if}
                  </div>

                  {* hide until Alpine initializes *}
                  <style>
                    [x-cloak] {
                      display: none !important;
                    }
                  </style>



                  <!-- Billing Term Selection -->
                  <div class="mb-8">
                    <div class="flex items-center">
                      <label for="billing-term" class="w-1/4 text-sm font-medium text-gray-300">
                        Billing Term
                      </label>
                      <div class="w-3/4 relative">
                        <!-- bind to the root Alpine state -->
                        <input type="hidden" name="billingterm" x-model="billingTerm">

                        <!-- toggle button -->
                        <button type="button" :disabled="termDisabled" @click="!termDisabled && (termOpen = !termOpen)"
                          class="flex items-center w-full px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2.5 text-sm text-slate-100 placeholder:text-gray-500 text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition
                " :class="termDisabled
                  ? 'opacity-60 cursor-not-allowed bg-slate-700 pointer-events-none'
                  : 'hover:bg-slate-700/50'">
                          <span class="flex-1 text-left" x-text="billingTerm
            ? termOptions.find(o => o.value === billingTerm).label
            : 'Select billing term'"></span>
                          <svg class="w-5 h-5 ml-2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                          </svg>
                        </button>

                        <!-- dropdown menu -->
                        <div x-show="termOpen" @click.away="termOpen = false" x-cloak
                          class="absolute z-10 w-full mt-1 bg-[#151f2e] border border-slate-700 rounded shadow-lg">
                          <template x-for="opt in termOptions" :key="opt.value">
                            <div @click="billingTerm = opt.value; termOpen = false"
                              class="relative group flex items-center px-4 py-2 cursor-pointer hover:bg-slate-800/80 transition-colors duration-150">
                              <!-- option label -->
                              <span class="flex-1 ml-2 text-gray-300 group-hover:text-white" x-text="opt.label"></span>
                            </div>
                          </template>
                        </div>


                        <!-- validation error -->
                        {if !empty($errors['billingterm'])}
                          <p class="text-red-500 text-xs mt-1">Please select a billing term.</p>
                        {/if}
                      </div>
                    </div>
                    <!-- Annual fine-print -->
                    <p x-show="billingTerm === 'annual'" x-cloak class="mt-2 ml-1 text-xs text-gray-400">
                      Annual plans are subject to prorated charges for increased usage during the billing term.
                    </p>
                  </div>





                  <!-- Payment method gating / Stripe inline capture -->
                  <div class="mb-8">
                    <div class="flex items-start">
                      <label class="w-1/4 text-sm font-medium text-gray-300">Payment</label>
                      <div class="w-3/4 space-y-2">
                        <template x-if="!payment.isStripeDefault">
                          <div class="text-sm text-gray-300">Your default payment method is <span class="font-medium" x-text="payment.defaultGateway || 'invoice'"></span>. You can proceed.</div>
                        </template>
                        <template x-if="payment.isStripeDefault && payment.hasCardOnFile">
                          <div class="text-sm text-gray-300">Your saved card <span x-text="payment.lastFour ? ('•••• ' + payment.lastFour) : ''"></span> will be used. You can place the order.</div>
                        </template>
                        <template x-if="payment.isStripeDefault && !payment.hasCardOnFile">
                          <div class="space-y-3">
                            <div class="text-sm text-amber-300">A saved card is required to complete the order.</div>
                            <a href="{$payment.addCardExternalUrl|escape:'html'}" class="inline-flex items-center px-3 py-2 rounded bg-sky-600 hover:bg-sky-700">Add Card (Secure)</a>
                            <div class="text-xs text-gray-500">
                              After adding your card, <a href="{$modulelink}&a=createorder" class="text-sky-400 underline">refresh this page</a> to continue.
                            </div>
                          </div>
                        </template>
                      </div>
                    </div>
                  </div>

                  <!-- Billing start date (static) -->
                  <div class="mb-8" :class="cb.enabled ? 'opacity-50 pointer-events-none select-none' : ''">
                    <div class="flex items-center">
                      <label class="w-1/4 text-sm font-medium text-gray-300">
                        Billing start date
                      </label>
                      <div class="w-3/4">
                        <template x-if="isResellerClient && !cb.enabled">
                          <div>
                            <div class="text-xs text-gray-500 line-through" x-text="new Date().toISOString().slice(0,10)"></div>
                            <div class="mt-1 px-3 py-2 border border-slate-700 text-white bg-[#11182759] rounded-lg select-none">
                              <span x-text="(() => { const d = new Date(); d.setDate(d.getDate()+30); return d.toISOString().slice(0,10); })()"></span>
                            </div>
                            <p class="mt-1">
                              <span class="inline-flex items-center rounded-full text-[10px] uppercase bg-sky-600/20 text-sky-300 px-2 py-0.5 rounded px-2.5 py-0.5 text-[11px] font-medium text-emerald-400/75">
                                Reseller Promo Applied — 30 days free
                              </span>
                            </p>
                          </div>
                        </template>
                        <template x-if="!isResellerClient && !cb.enabled">
                          <div>
                            <div class="mt-1 px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded select-none">
                              <span x-text="new Date().toISOString().slice(0,10)"></span>
                            </div>
                          </div>
                        </template>
                        <template x-if="cb.enabled">
                          <div>
                            <div class="mt-1 px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded select-none">
                              <span></span>
                            </div>
                          </div>
                        </template>
                      </div>
                    </div>
                  </div>





                  <!-- Submit Button -->
                  <button type="submit" :disabled="!canSubmit()"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-700"
                    :class="canSubmit() 
                        ? 'bg-green-600 hover:bg-green-700' 
                        : 'opacity-50 cursor-not-allowed bg-green-600'">
                    <!-- swap text -->
                    <span x-text="loading ? 'Processing…' : 'Confirm'"></span>

                    <!-- spinner only when loading -->
                    <svg x-show="loading" class="animate-spin h-5 w-5 ml-2 text-white" xmlns="http://www.w3.org/2000/svg"
                      fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                  </button>
                </form>
              </div>
            
            <!-- Bulk Confirmation Modal (scoped inside main Alpine component) -->
            <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
              <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="confirmOpen=false"></div>
              <div class="relative w-full max-w-lg mx-4 rounded-2xl bg-[#0b1220] text-gray-100 shadow-2xl ring-1 ring-white/10"
                   x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                   x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                <div class="px-6 py-4 border-b border-white/10">
                  <h3 class="text-xl font-semibold">Confirm Provision</h3>
                </div>
                <div class="px-6 py-4 space-y-2 text-sm">
                  <div class="flex justify-between"><span class="text-gray-400">Product</span><span x-text="confirm.product"></span></div>
                  <div class="flex justify-between"><span class="text-gray-400">Billing term</span><span x-text="confirm.term"></span></div>
                  <div class="flex justify-between"><span class="text-gray-400">Accounts</span><span x-text="confirm.count"></span></div>
                  <div class="flex justify-between"><span class="text-gray-400">Billing start</span><span x-text="confirm.start"></span></div>
                </div>
                <div class="px-6 py-4 flex justify-end gap-3 border-t border-white/10">
                  <button type="button" class="px-4 py-2 rounded-xl bg-slate-700 hover:bg-slate-600" @click="confirmOpen=false">Cancel</button>
                  <button type="button" class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-500" @click="confirmProvision()">Confirm</button>
                </div>
              </div>
            </div>
            
            </div>

            <!-- Bulk mode left column -->
            <div class="w-full lg:w-full" x-show="mode==='bulk'" x-transition x-cloak>
              <!-- Mode segmented control (positioned above Choose a Service) -->
              <div class="w-full mb-6">
                <div class="inline-flex rounded-xl ring-1 ring-white/10 bg-[#0b1220] text-gray-300 shadow-sm overflow-hidden">
                  <button type="button" @click="mode='single'" class="px-4 py-2 text-sm transition" :class="mode==='single' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">Single Account</button>
                  <button type="button" @click="mode='bulk'" class="px-4 py-2 text-sm transition flex items-center gap-2" :class="mode==='bulk' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">Bulk Account Creation <span class="text-[10px] uppercase bg-sky-600/20 text-sky-300 px-2 py-0.5 rounded">Advanced</span></button>
                </div>
              </div>
              <!-- Choose a Service (reuse same dropdown, separate input id) -->
              <div class="col-form w-full mb-6">
                <div>
                  <label for="productBulk" class="block text-sm font-medium text-gray-300 mb-1">Choose a Service</label>
                  <div class="relative">
                    <input type="hidden" id="productSelectBulk" name="product_bulk" x-model="selectedProduct">
                    <button type="button" @click="open = !open" class="flex items-center w-full px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                      <template x-if="selectedIcon">
                        <span class="flex-shrink-0 mr-3" x-html="selectedIcon"></span>
                      </template>
                      <div class="flex-1 text-left">
                        <div class="text-sm" x-text="selectedName"></div>
                        <template x-if="selectedDesc">
                          <div class="text-xs text-gray-400" x-text="selectedDesc"></div>
                        </template>
                      </div>
                    </button>
                    <!-- Dropdown menu (bulk: restrict to PIDs 58,60,53,54) -->
                    <div x-show="open" @click.away="open = false" class="absolute z-10 w-full mt-1 bg-[#151f2e] border border-sky-600 rounded shadow-lg max-h-60 overflow-auto">
                      {foreach $categories.usage as $product}
                        {if ($product.pid == 58) || ($isResellerClient && $product.pid == 60)}
                        <div @click="
                          selectedProduct = '{$product.pid}';
                          selectedName    = `{$product.name}`;
                          selectedDesc    = 'Usage-based billing';
                          selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                          productType     = 'usage';
                          open            = false
                        " class="relative group flex items-start px-3 py-2 cursor-pointer">
                          <span class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                          <div class="flex-shrink-0">
                            {if $product.pid == 58}
                              <!-- eazyBackup brand icon pid=58 -->
                              <svg width="24" height="24" viewBox="0 0 256 253" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-3">
                                <g transform="translate(0 -0)">
                                  <path d="M123.517 0.739711C135.162 3.20131 145.461 10.8791 151.254 21.3116C152.308 23.2457 154.297 27.8758 155.643 31.5682C156.989 35.2606 158.218 38.4841 158.335 38.7186C158.51 38.953 161.261 37.8394 164.421 36.257C173.491 31.6854 178.231 30.2788 185.779 29.9271C191.104 29.6927 193.094 29.8685 197.19 30.9235C209.479 33.9712 219.544 42.1765 224.927 53.4295C227.912 59.5835 228.907 63.8034 229.199 71.7156C229.55 80.1554 228.497 85.372 224.518 94.866C224.518 94.866 221.943 101.02 221.943 101.02C221.943 101.02 229.901 104.889 229.901 104.889C240.961 110.339 246.813 115.79 251.553 125.226C254.771 131.673 255.941 137.007 256 145.563C256 154.472 254.771 159.63 250.968 166.956C246.169 176.275 239.557 182.546 229.375 187.586C225.981 189.227 223.172 190.634 223.113 190.634C223.055 190.693 223.406 192.685 223.933 195.088C224.459 197.726 224.869 202.532 224.869 207.103C224.869 213.726 224.693 215.426 223.289 219.997C217.496 239.221 201.696 251.998 182.561 252.936C174.661 253.288 169.336 252.233 162.373 248.775C155.468 245.375 150.903 241.097 144.993 232.774C142.477 229.14 140.253 226.151 140.078 226.151C139.961 226.151 137.269 228.144 134.109 230.606C127.438 235.881 119.889 239.749 113.686 241.155C107.191 242.562 97.243 242.035 91.0402 239.925C81.0923 236.467 72.6658 228.847 67.8089 218.825C64.766 212.495 63.5957 207.748 62.6009 197.491C62.2498 194.092 61.9572 191.337 61.8401 191.22C61.7816 191.162 58.4462 191.748 54.4085 192.568C37.0289 196.026 23.4529 192.334 12.7443 181.257C2.67933 170.707 -1.53391 155.527 1.50898 140.699C3.38153 131.614 6.77553 125.402 14.5583 116.786C14.5583 116.786 19.7663 111.043 19.7663 111.043C19.7663 111.043 14.6168 105.592 14.6168 105.592C8.64807 99.262 5.72222 95.101 3.38153 89.533C-1.24132 78.3971 -1.12429 64.0378 3.73263 52.9606C7.7118 44.052 15.7286 35.3192 23.8625 31.0993C30.8261 27.4655 36.4437 26.0589 44.168 26.0589C51.8922 26.0589 55.4033 26.8795 64.4149 30.9821C67.9844 32.6232 71.0273 33.854 71.0859 33.7954C71.2029 33.6781 72.7243 30.6891 74.4799 27.1139C79.5708 16.9159 85.6566 10.1172 93.9075 5.48701C97.5356 3.43571 103.27 1.32581 107.366 0.505211C111.17 -0.256689 119.362 -0.139489 123.517 0.739711C123.517 0.739711 123.517 0.739711 123.517 0.739711Z" fill="#FE5000" fill-rule="evenodd" />
                                  <path d="M118.784 71.936C126.976 71.936 134.656 69.632 134.656 52.736C134.656 33.536 115.456 1.14441e-05 71.936 1.14441e-05C33.536 1.14441e-05 0 28.672 0 68.096C0 101.12 23.808 134.4 69.888 134.4C92.928 134.4 129.024 120.064 129.024 100.352C129.024 95.488 124.416 87.552 119.04 87.552C113.664 87.552 104.192 99.072 84.992 99.072C72.96 99.072 56.32 88.32 56.32 75.52C56.32 71.168 60.416 71.936 63.232 71.936C63.232 71.936 118.784 71.936 118.784 71.936C118.784 71.936 118.784 71.936 118.784 71.936ZM70.4 47.36C63.744 47.36 55.04 49.408 55.04 40.192C55.04 32 61.952 23.296 70.4 23.296C79.872 23.296 85.504 30.464 85.504 39.68C85.504 48.896 77.312 47.36 70.4 47.36C70.4 47.36 70.4 47.36 70.4 47.36Z" fill="#FFFFFF" transform="translate(55.352 57.184)" />
                                </g>
                              </svg>
                            {elseif $product.pid == 60}
                              <!-- OBC brand icon pid=60 -->
                              <svg width="24" height="24" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-3">
                                <defs>
                                  <clipPath id="clip_path_1"><rect width="256" height="256" /></clipPath>
                                </defs>
                                <g clip-path="url(#clip_path_1)">
                                  <g>
                                    <path d="M123.517 0.748482C135.162 3.23927 145.461 11.0081 151.254 21.5643C152.308 23.5214 154.297 28.2064 155.643 31.9425C156.989 35.6787 158.218 38.9404 158.335 39.1777C158.51 39.4149 161.261 38.2881 164.421 36.6869C173.491 32.0611 178.231 30.6378 185.779 30.282C191.104 30.0448 193.094 30.2227 197.19 31.2902C209.479 34.374 219.544 42.6766 224.927 54.0631C227.912 60.29 228.907 64.56 229.199 72.566C229.55 81.1059 228.497 86.3843 224.518 95.9909C224.518 95.9909 221.943 102.218 221.943 102.218C221.943 102.218 229.901 106.133 229.901 106.133C240.961 111.647 246.813 117.163 251.553 126.711C254.771 133.234 255.941 138.632 256 147.289C256 156.304 254.771 161.523 250.968 168.936C246.169 178.365 239.557 184.711 229.375 189.81C225.981 191.471 223.172 192.895 223.113 192.895C223.055 192.954 223.406 194.97 223.933 197.401C224.459 200.071 224.869 204.934 224.869 209.559C224.869 216.26 224.693 217.98 223.289 222.606C217.496 242.058 201.696 254.986 182.561 255.935C174.661 256.291 169.336 255.224 162.373 251.725C155.468 248.285 150.903 243.956 144.993 235.534C142.477 231.857 140.253 228.833 140.078 228.833C139.961 228.833 137.269 230.849 134.109 233.34C127.438 238.678 119.889 242.592 113.686 244.015C107.191 245.438 97.243 244.905 91.0402 242.77C81.0923 239.271 72.6658 231.561 67.8089 221.42C64.766 215.015 63.5957 210.211 62.6009 199.833C62.2498 196.394 61.9572 193.606 61.8401 193.487C61.7816 193.429 58.4462 194.022 54.4085 194.851C37.0289 198.35 23.4529 194.615 12.7443 183.406C2.67933 172.731 -1.53391 157.371 1.50898 142.367C3.38153 133.175 6.77553 126.889 14.5583 118.171C14.5583 118.171 19.7663 112.36 19.7663 112.36C19.7663 112.36 14.6168 106.844 14.6168 106.844C8.64807 100.439 5.72222 96.2287 3.38153 90.5947C-1.24132 79.3267 -1.12429 64.7972 3.73263 53.5886C7.7118 44.5744 15.7286 35.738 23.8625 31.4681C30.8261 27.7912 36.4437 26.3679 44.168 26.3679C51.8922 26.3679 55.4033 27.1982 64.4149 31.3495C67.9844 33.01 71.0273 34.2554 71.0859 34.1961C71.2029 34.0775 72.7243 31.053 74.4799 27.4354C79.5708 17.1165 85.6566 10.2372 93.9075 5.55207C97.5356 3.47645 103.27 1.34153 107.366 0.511202C111.17 -0.259733 119.362 -0.141143 123.517 0.748482C123.517 0.748482 123.517 0.748482 123.517 0.748482Z" fill="#0EA5E9" fill-rule="evenodd" />
                                  </g>
                                  <rect width="256" height="256" />
                                  <g transform="translate(22 95)">
                                    <g>
                                      <g transform="translate(143.404 0)">
                                        <path d="M57.5965 60.9094L57.5965 66.9999C57.5965 66.9999 32.7652 66.9999 32.7652 66.9999C22.7366 66.9999 14.7685 63.2395 8.86076 58.1556C2.95362 53.0717 0 45.2586 0 34.716C0 24.4236 2.48986 16.2676 7.47021 10.2478C12.4501 4.22806 21.2248 0 33.3833 0C33.3833 0 57.5965 0 57.5965 0L57.5965 12.3124C57.5965 12.3124 33.1773 12.3124 33.1773 12.3124C26.3767 12.3124 21.9636 14.9093 19.5249 18.8818C17.0864 22.8542 15.8673 27.0402 15.8673 33.5466C15.8673 33.5466 15.8673 36.7381 15.8673 36.7381C15.8673 41.9305 17.3611 46.2628 19.5249 49.7349C21.6889 53.2071 26.0334 54.8181 33.3833 54.8181C33.3833 54.8181 57.5965 54.8181 57.5965 54.8181C57.5965 54.8181 57.5965 58.0302 57.5965 60.9094Z" fill="#FFFFFF" fill-rule="evenodd" />
                                      </g>
                                      <path d="M60.1302 39.2777C58.4354 36.969 55.7199 35.0237 51.9848 33.4418C55.7892 31.4233 58.5217 29.2787 60.1818 27.0079C61.842 24.7369 62.6721 22.5609 62.6721 18.6815C62.6394 12.4942 61.8254 9.05339 58.9012 6.21648C55.977 3.37958 51.7471 0.0713317 39.0144 0C38.5305 0 0 8.43166e-05 0 8.43166e-05L0 67C0 67 39.0144 67 39.0144 67C48.6299 67 54.9761 64.5504 58.0547 61.2925C61.1328 58.0346 62.6721 53.5904 62.6721 47.9594C62.6721 44.4802 61.8244 41.5864 60.1302 39.2777C60.1302 39.2777 60.1302 39.2777 60.1302 39.2777ZM36.456 27.6699C39.7977 27.6699 42.3742 27.118 44.1843 26.0141C45.995 24.9103 46.8998 22.9076 46.8998 20.006C46.8998 14.8337 43.5235 12.2475 36.7702 12.2475C36.7702 12.2475 14.7338 12.2475 14.7338 12.2475L14.7338 27.6699L36.456 27.6699C36.456 27.6699 36.456 27.6699 36.456 27.6699ZM44.1503 53.1112C42.3176 54.2535 39.7752 54.8249 36.5235 54.8249C36.5235 54.8249 14.7338 54.8249 14.7338 54.8249L14.7338 39.0238C14.7338 39.0238 36.8356 39.0238 36.8356 39.0238C43.5449 39.0238 46.8998 41.6888 46.8998 47.0189C46.8998 49.9383 45.9835 51.9689 44.1503 53.1112C44.1503 53.1112 44.1503 53.1112 44.1503 53.1112Z" fill="#FFFFFF" fill-rule="evenodd" stroke-width="0" stroke="#FFFFFF" transform="translate(75.099 0)" />
                                      <path d="M53.7541 4.50603C48.9185 1.50201 41.889 0 32.6657 0C26.773 0 21.4568 0.772792 16.7171 2.31838C11.9774 3.86394 8.00629 6.91153 4.80379 11.4612C3.13847 13.7948 1.92151 16.5802 1.1529 19.8174C0.384302 23.0545 0 26.8603 0 31.2347C0 40.2181 1.88948 47.0865 5.66844 51.84C9.44742 56.5935 13.6587 59.6266 18.3024 60.9386C22.946 62.2511 27.7979 62.9074 32.8578 62.9074C42.4012 62.9074 50.2476 60.4871 56.396 55.6459C62.545 50.8045 65.6197 42.7259 65.6197 31.4099C65.6197 25.5772 64.8506 20.386 63.3135 15.8364C61.7764 11.2868 58.5901 7.51006 53.7541 4.50603C53.7541 4.50603 53.7541 4.50603 53.7541 4.50603ZM46.7408 47.1601C43.9546 50.8827 39.2949 52.7442 32.7618 52.7442C25.2679 52.7442 20.368 50.8102 18.0622 46.942C15.7564 43.0736 14.6035 37.8237 14.6035 31.1924C14.6035 24.2699 16.2208 19.0344 19.4553 15.4861C22.6898 11.9376 27.1253 10.1634 32.7618 10.1634C38.5902 10.1634 43.074 11.7194 46.2124 14.8317C49.3509 17.9438 50.9201 23.4845 50.9201 31.4538C50.9201 38.2021 49.527 43.4375 46.7408 47.1601C46.7408 47.1601 46.7408 47.1601 46.7408 47.1601Z" fill="#FFFFFF" fill-rule="evenodd" stroke-width="6" stroke="#FFFFFF" transform="translate(0 2.056)" />
                                    </g>
                                  </g>
                                </g>
                              </svg>
                            {/if}
                          </div>
                          <div class="ml-1">
                            <div class="text-sm text-gray-100">{$product.name}</div>
                            <div class="text-xs text-gray-400">{if $product.pid == 60}OBC Branded Backup Client{elseif $product.pid == 58}eazyBackup Branded Backup Client{else}Usage-based billing{/if}</div>
                          </div>
                        </div>
                        {/if}
                      {/foreach}
                      {foreach $categories.hyperv as $product}
                        {if ($product.pid == 53) || ($isResellerClient && $product.pid == 54)}
                        <div @click="
                          selectedProduct = '{$product.pid}';
                          selectedName    = `{$product.name}`;
                          selectedDesc    = 'Backup for Virtual Machines';
                          selectedIcon    = $event.currentTarget.querySelector('svg').outerHTML;
                          productType     = 'usage';
                          open            = false
                        " class="relative group flex items-start px-3 py-2 cursor-pointer">
                          <span class="absolute left-0 inset-y-0 w-1 bg-sky-500 opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                          <div class="flex-shrink-0 {if $product.gid == 7}text-sky-500{else}text-orange-600{/if}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" class="mr-3 size-6"><path d="M5.507 4.048A3 3 0 0 1 7.785 3h8.43a3 3 0 0 1 2.278 1.048l1.722 2.008A4.533 4.533 0 0 0 19.5 6h-15c-.243 0-.482.02-.715.056l1.722-2.008Z" /><path fill-rule="evenodd" d="M1.5 10.5a3 3 0 0 1 3-3h15a3 3 0 1 1 0 6h-15a3 3 0 0 1-3-3Zm15 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm2.25.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM4.5 15a3 3 0 1 0 0 6h15a3 3 0 1 0 0-6h-15Zm11.25 3.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM19.5 18a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" clip-rule="evenodd" /></svg>
                          </div>
                          <div class="ml-1">
                            <div class="text-sm text-gray-100">{$product.name}</div>
                            <div class="text-xs text-gray-400">Backup for Hyper-V, Proxmox, and VMware, no device charges</div>
                          </div>
                        </div>
                        {/if}
                      {/foreach}
                    </div>
                  </div>
                </div>
              </div>
              <div class="space-y-6">
                <!-- Bulk tabs -->
                <div class="inline-flex rounded-xl ring-1 ring-white/10 bg-[#0b1220] text-gray-300 shadow-sm overflow-hidden">
                  <button type="button" @click="bulkTab='manual'" class="px-4 py-2 text-sm" :class="bulkTab==='manual' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">Manual Entry</button>
                  <button type="button" @click="bulkTab='csv'" class="px-4 py-2 text-sm" :class="bulkTab==='csv' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">CSV Upload</button>
                </div>

                <!-- Shared controls -->
                <div class="grid grid-cols-1 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-white mb-1">Billing Term</label>
                    <div class="flex gap-3">
                      <button type="button" @click="billingTerm='monthly'" class="px-3 py-2 rounded ring-1 ring-white/10" :class="billingTerm==='monthly' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">Monthly</button>
                      <button type="button" @click="billingTerm='annual'" class="px-3 py-2 rounded ring-1 ring-white/10" :class="billingTerm==='annual' ? 'bg-slate-800 text-white' : 'hover:bg-white/5'">Annual</button>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Consolidated Billing</label>
                    <div class="flex items-center gap-3">
                      <button type="button"
                        :aria-pressed="cb.enabled ? 'true' : 'false'"
                        role="switch"
                        :aria-checked="cb.enabled ? 'true' : 'false'"
                        @click="if(!cb.locked){ cb.enabled = !cb.enabled }"
                        :class="cb.locked ? 'opacity-50 cursor-not-allowed' : ''"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-sky-500/50"
                        :style="cb.enabled ? 'background-color: rgb(2 132 199 / 1)' : 'background-color: rgb(30 41 59 / 1)'"></button>
                      <span class="text-sm text-gray-300" x-text="cb.locked ? 'Enabled' : (cb.enabled ? 'Enabled' : 'Off')"></span>
                      <input type="hidden" id="cb_enabled_bulk" name="cb_enabled" :value="cb.enabled ? '1' : '0'">
                    </div>
                    <!-- Day picker (1–31), same as single mode -->
                    <div class="relative mt-3" x-show="cb.enabled" x-cloak>
                      <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-300">Day of month</span>
                        <button type="button" @click="!cb.locked && (domOpen=!domOpen)" :disabled="cb.locked"
                          class="px-3 py-2 border border-slate-700 text-gray-300 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600"
                          :class="cb.locked ? 'opacity-50 cursor-not-allowed' : ''"
                          x-text="cb.dom"></button>
                        <input type="hidden" id="cb_dom_bulk" name="cb_dom" :value="cb.dom">
                      </div>
                      <div x-show="domOpen" @click.away="domOpen=false" x-cloak class="absolute z-10 mt-1 bg-[#151f2e] border border-sky-600 rounded shadow-lg max-h-48 overflow-auto w-40">
                        <template x-for="n in 31" :key="n">
                          <div @click="cb.dom=n; domOpen=false" class="px-3 py-1 cursor-pointer hover:bg-slate-700/50 text-gray-300" x-text="n"></div>
                        </template>
                      </div>
                      <template x-if="domError">
                        <p class="text-red-500 text-xs mt-1" x-text="domError"></p>
                      </template>
                      <p x-show="cb.enabled && !cb.locked" x-cloak class="text-xs text-gray-400 mt-2">Selected billing date applies to this order and all future orders. Existing plans keep their current billing dates. To change past orders, contact Sales.</p>
                    </div>
                    <!-- Summary row -->
                    <div x-show="cb.enabled" x-cloak class="text-sm text-gray-300 select-none mt-2">
                      <span x-text="cb.summary"></span>
                    </div>
                  </div>
                </div>

                <!-- Manual Entry Pane -->
                <div x-show="bulkTab==='manual'" x-transition class="p-4 bg-slate-900/60 rounded-xl ring-1 ring-white/10 shadow">
                  <div class="flex items-center gap-3 mb-3">
                    <input type="text" placeholder="Prefix (optional)" x-model="prefix" class="px-3 py-2 border border-slate-700 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                    <button type="button" @click="genUsernames()" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700">Generate Usernames</button>
                    <button type="button" @click="fillPasswords()" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700">Generate Passwords</button>
                    <button type="button" @click="copyDown('email')" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700">Copy Email Down</button>
                  </div>
                  <div class="space-y-3">
                    <template x-for="(r,idx) in rows" :key="idx">
                      <div class="grid grid-cols-12 gap-3 items-start">
                        <div class="col-span-4">
                          <input type="text" x-model="r.username" placeholder="Username" class="w-full px-3 py-2 border border-slate-700 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                          <template x-if="r.errors && r.errors.username"><p class="text-xs text-red-500 mt-1" x-text="r.errors.username"></p></template>
                        </div>
                        <div class="col-span-4 relative">
                          <input :type="r._show ? 'text' : 'password'" x-model="r.password" placeholder="Password" class="w-full px-3 py-2 border border-slate-700 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                          <button type="button" @click="r._show = !r._show" tabindex="-1"
                            class="absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <!-- Hidden Icon -->
                            <svg x-show="!r._show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                              viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 
                                7.244 19.5 12 19.5c.993 0 1.953-.138 
                                2.863-.395M6.228 6.228A10.451 10.451 0 0 1 
                                12 4.5c4.756 0 8.773 3.162 10.065 
                                7.498a10.522 10.522 0 0 1-4.293 
                                5.774M6.228 6.228l-3.228-3.228m3.228 
                                3.228l3.65 3.65m7.894 7.894L21 
                                21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 
                                1 0-4.243-4.243m4.242 4.242L9.88 
                                9.88" />
                            </svg>
                            <!-- Visible Icon -->
                            <svg x-show="r._show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                              viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-slate-500 w-5 h-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 
                                7.51 7.36 4.5 12 4.5c4.638 0 8.573 
                                3.007 9.963 7.178.07.207.07.431 
                                0 .639C20.577 16.49 16.64 19.5 12 
                                19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                              <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                          </button>
                          <template x-if="r.errors && r.errors.password"><p class="text-xs text-red-500 mt-1" x-text="r.errors.password"></p></template>
                        </div>
                        <div class="col-span-3">
                          <input type="email" x-model="r.email" placeholder="Email" class="w-full px-3 py-2 border border-slate-700 bg-[#11182759] rounded focus:outline-none focus:ring-0 focus:border-sky-600">
                          <template x-if="r.errors && r.errors.email"><p class="text-xs text-red-500 mt-1" x-text="r.errors.email"></p></template>
                        </div>
                        <div class="col-span-1 flex justify-end">
                          <button type="button" @click="removeRow(idx)" class="px-2 py-2 rounded bg-slate-800 hover:bg-slate-700">✕</button>
                        </div>
                      </div>
                    </template>
                  </div>
                  <div class="mt-3">
                    <button type="button" @click="addRow()" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700">Add Row</button>
                  </div>
                  <div class="mt-4 flex gap-3">
                    <button type="button" @click="bulkValidate()" class="px-4 py-2 rounded bg-sky-600 hover:bg-sky-500">Validate List</button>
                    <button type="button" :disabled="!(validation.ok && (!payment.isStripeDefault || payment.hasCardOnFile)) || loading || progress.active" @click="openBulkConfirm()" class="px-4 py-2 rounded bg-green-600 hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed">Provision Accounts</button>
                  </div>
                  <div class="mt-2 text-sm">
                    <template x-if="validation.summary && validation.summary.length">
                      <div x-text="validation.summary"></div>
                    </template>
                    <template x-if="cb.enabled">
                      <div>Next due: <span x-text="(cb.summary||'').replace(/.*Next due:\s*/,'')"></span></div>
                    </template>
                  </div>
                  <!-- Progress bar -->
                  <div class="mt-4" x-show="progress.active" x-cloak>
                    <div class="text-sm text-gray-300 mb-1">Provisioning <span x-text="progress.done"></span> of <span x-text="progress.total"></span><span x-show="progress.failed>0"> — <span class="text-amber-300" x-text="progress.failed + ' failed'"></span></span></div>
                    <div class="w-full h-2 bg-slate-700 rounded">
                      <div class="h-2 bg-sky-600 rounded" :style="'width:' + (progress.total>0 ? Math.floor((progress.done/Math.max(progress.total,1))*100) : 0) + '%'"></div>
                    </div>
                  </div>
                </div>

                <!-- CSV Upload Pane -->
                <div x-show="bulkTab==='csv'" x-transition class="p-4 bg-slate-900/60 rounded-xl ring-1 ring-white/10 shadow">
                  <div class="flex items-center justify-between mb-3">
                    <div class="text-sm">Upload CSV with header: username,password,email</div>
                    <a href="{$modulelink}&a=bulk_csv_sample" class="text-sky-300 text-sm underline">Download sample</a>
                  </div>
                  <input type="file" accept=".csv,text/csv" @change="csvSelect($event)" class="block w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-slate-800 file:text-gray-200 hover:file:bg-slate-700" />
                  <div class="mt-4 flex gap-3">
                    <button type="button" @click="bulkValidate()" class="px-4 py-2 rounded bg-sky-600 hover:bg-sky-500">Validate List</button>
                    <button type="button" :disabled="!(validation.ok && (!payment.isStripeDefault || payment.hasCardOnFile)) || loading || progress.active" @click="openBulkConfirm()" class="px-4 py-2 rounded bg-green-600 hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed">Provision Accounts</button>
                  </div>
                  <div class="mt-2 text-sm">
                    <template x-if="validation.summary && validation.summary.length">
                      <div x-text="validation.summary"></div>
                    </template>
                    <template x-if="cb.enabled">
                      <div>Next due: <span x-text="(cb.summary||'').replace(/.*Next due:\s*/,'')"></span></div>
                    </template>
                  </div>
                  <!-- Progress bar -->
                  <div class="mt-4" x-show="progress.active" x-cloak>
                    <div class="text-sm text-gray-300 mb-1">Provisioning <span x-text="progress.done"></span> of <span x-text="progress.total"></span><span x-show="progress.failed>0"> — <span class="text-amber-300" x-text="progress.failed + ' failed'"></span></span></div>
                    <div class="w-full h-2 bg-slate-700 rounded">
                      <div class="h-2 bg-sky-600 rounded" :style="'width:' + (progress.total>0 ? Math.floor((progress.done/Math.max(progress.total,1))*100) : 0) + '%'"></div>
                    </div>
                  </div>
                </div>

                <!-- Completion report -->
                <div x-show="report.visible" x-cloak class="p-4 bg-slate-900/60 rounded-xl ring-1 ring-white/10 shadow">
                  <div class="flex items-center justify-between mb-3">
                    <div class="text-sm text-gray-200" x-text="successMsg"></div>
                    <button type="button" @click="copyReport()" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700 text-sm">Copy CSV</button>
                  </div>
                  <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr class="text-gray-400">
                          <th class="text-left pr-4 py-2">Username</th>
                          <th class="text-left pr-4 py-2">Password</th>
                          <th class="text-left pr-4 py-2">Email</th>
                          <th class="text-left pr-4 py-2">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <template x-for="(it, i) in report.items" :key="i">
                          <tr>
                            <td class="pr-4 py-1" x-text="it.username"></td>
                            <td class="pr-4 py-1" x-text="it.password"></td>
                            <td class="pr-4 py-1" x-text="it.email"></td>
                            <td class="pr-4 py-1"><span :class="it.ok ? 'text-green-400' : 'text-amber-300'" x-text="it.ok ? 'OK' : 'Failed'"></span></td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          <!-- right column: dynamic price list -->
          <div class="w-full lg:w-1/2 text-gray-400 space-y-4 mt-12" x-show="mode==='single'">
            <!-- Usage-based / eazyBackup / OBC plans -->
            <div x-show="isUsage()">
              <div class="max-w-lg mx-auto pr-6 pl-6 rounded-lg text-gray-300">
                <h3 class="text-xl font-semibold mb-4"
                    x-text="(parseInt(selectedProduct)==60)
                      ? 'OBC Branded Backup Client'
                      : (parseInt(selectedProduct)==58)
                          ? 'eazyBackup Branded Backup Client'
                          : 'Usage-Based Backup'">
                </h3>
                <p class="text-gray-400 mb-4">
                  Your plan includes a <strong x-text="priceFor(67)"></strong> base fee for 1&nbsp;terabyte of storage per <span x-text="termLabel()"></span>. Additional services are billed dynamically according to what you use:
                </p>
                <ul class="list-disc list-inside text-gray-400 mb-4 space-y-1">
                  <li><span class="font-medium">Cloud Storage:</span> <span x-text="priceFor(67)"></span> per terabyte per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">Devices / Endpoints:</span> <span x-text="priceFor(88)"></span> per device per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">Disk Image (Hyper-V):</span> <span x-text="priceFor(91)"></span> per protected machine per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">Hyper-V, Proxmox:</span> <span x-text="priceFor(97)"></span> per virtual machine per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">VMware backups:</span> <span x-text="priceFor(99)"></span> per virtual machine per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">Proxmox Guest VM:</span> <span x-text="priceFor(102)"></span> per virtual machine per <span x-text="termLabel()"></span></li>
                </ul>
                <p class="text-gray-400 mb-4">
                  You're free to use all features; charges automatically adjust each <span x-text="termLabel()"></span> to match your actual consumption.
                </p>
              </div>
            </div>

            <!-- Microsoft 365 backup plans -->
            <div x-show="isMs365()">
              <div class="max-w-lg mx-auto pr-6 pl-6 rounded-lg text-gray-300">
                <h3 class="text-xl font-semibold mb-4">Microsoft 365 Backup Pricing</h3>
                <p class="text-gray-400 mb-4">
                  Your plan includes a <strong x-text="priceFor(60)"></strong> base fee for a single Microsoft 365 user per <span x-text="termLabel()"></span>. Billing for additional users is usage-based:
                </p>
                <ul class="list-disc list-inside text-gray-400 mb-4 space-y-1">
                  <li><span class="font-medium">Per User:</span> <span x-text="priceFor(60)"></span> per protected 365 account per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">Storage:</span> Unlimited per user</li>
                  <li><span class="font-medium">Retention:</span> 1 year (30 daily + 52 weekly snapshots)</li>
                </ul>
                <p class="text-gray-400 mb-4">
                  Longer retention periods are available; contact sales for extended-retention pricing.
                </p>
              </div>
            </div>

            <!-- VM-only backup plans (Hyper-V / Proxmox / VMware) -->
            <div x-show="isVm()">
              <div class="max-w-lg mx-auto pr-6 pl-6 rounded-lg text-gray-300">
                <h3 class="text-xl font-semibold mb-4">Virtual Server Backup</h3>
                <p class="text-gray-400 mb-4">
                  This plan backs up guest virtual machines only (no physical servers or file-and-folder jobs). Pricing is:
                </p>
                <p class="text-gray-400 mb-4">
                  Base storage of <strong x-text="priceFor(67)"></strong> for 1&nbsp;terabyte per <span x-text="termLabel()"></span>, plus:
                </p>
                <ul class="list-disc list-inside text-gray-400 mb-4 space-y-1">
                  <li><span class="font-medium">Cloud Storage:</span> <span x-text="priceFor(67)"></span> per terabyte per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">Hyper-V VMs:</span> <span x-text="priceFor(97)"></span> per VM per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">Proxmox VMs:</span> <span x-text="priceFor(102)"></span> per VM per <span x-text="termLabel()"></span></li>
                  <li><span class="font-medium">VMware VMs:</span> <span x-text="priceFor(99)"></span> per VM per <span x-text="termLabel()"></span></li>
                </ul>
                <p class="text-gray-400 mb-4">
                  You can freely add/remove VMs; charges adjust automatically each <span x-text="termLabel()"></span>.
                </p>
              </div>
            </div>

            <!-- Fallback or placeholder when nothing selected -->
            <div x-show="!selectedProduct">
              <p class="italic text-gray-500">Select a service on the left to see pricing here.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Footer Section -->
{* <footer class="h-36 bg-gray-700 lg:border-t border-slate-700">
  </footer> *}
</div>

{* Initialize Tippy somewhere after your scripts *}
<script>
  document.addEventListener('DOMContentLoaded', function() {
    tippy('[data-tippy-content]', {
      theme: 'light-border',
      placement: 'right',
      delay: [200, 50],
    });
  });
</script>