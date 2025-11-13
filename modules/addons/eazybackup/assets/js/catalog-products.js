(function(){
  const $ = (s,ctx)=> (ctx||document).querySelector(s);
  const $$ = (s,ctx)=> Array.from((ctx||document).querySelectorAll(s));
  const open = el => el.classList.remove('hidden');
  const close = el => el.classList.add('hidden');

  const createModal = $('#eb-create-product-modal');
  const addPriceModal = $('#eb-add-price-modal');
  let currentProductId = null;

  const openCreate = $('#eb-open-create-product');
  if (openCreate) openCreate.addEventListener('click', ()=> {
    if (window.ebProductPanel && typeof window.ebProductPanel.openCreate === 'function') {
      window.ebProductPanel.openCreate();
      return;
    }
    if (window.ebProductWizard && typeof window.ebProductWizard.openCreate === 'function') {
      window.ebProductWizard.openCreate();
    } else {
      open(createModal);
    }
  });
  $$('#eb-create-product-modal [data-eb-close]').forEach(b=>b.addEventListener('click', ()=> close(createModal)));

  $$('#eb-add-price-modal [data-eb-close]').forEach(b=>b.addEventListener('click', ()=> close(addPriceModal)));
  $$('[data-eb-open-add-price]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.getAttribute('data-eb-open-add-price');
      if (window.ebProductWizard && typeof window.ebProductWizard.openAddPrice === 'function') {
        window.ebProductWizard.openAddPrice(id);
        return;
      }
      // Fallback to legacy modal
      currentProductId = id || null;
      const hidden = $('#eb-price-product-id');
      if (hidden) hidden.value = id || '';
      open(addPriceModal);
    });
  });

  $$('[data-eb-edit-product]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.getAttribute('data-eb-edit-product');
      if (window.ebProductPanel && typeof window.ebProductPanel.openEdit === 'function') {
        window.ebProductPanel.openEdit(id);
      } else if (window.ebProductWizard && typeof window.ebProductWizard.openEdit === 'function') {
        window.ebProductWizard.openEdit(id);
      } else {
        open(createModal);
      }
    });
  });

  // Open Stripe-connected product editor from table row
  $$('[data-eb-open-edit-stripe]').forEach(row=>{
    row.addEventListener('click', ()=>{
      const spid = row.getAttribute('data-eb-open-edit-stripe');
      if (window.ebProductPanel && typeof window.ebProductPanel.openEditStripe === 'function') {
        window.ebProductPanel.openEditStripe(spid);
      } else if (window.ebProductWizard && typeof window.ebProductWizard.openEditStripe === 'function') {
        window.ebProductWizard.openEditStripe(spid);
      }
    });
  });

  const modulelink = (function(){
    const a = document.querySelector('a[href*="index.php?m=eazybackup"]');
    // fallback to current
    return a ? a.href.split('&a=')[0] : 'index.php?m=eazybackup';
  })();

  // Stripe product actions (archive/delete)
  if (!window.ebStripeActions) {
    window.ebStripeActions = {
      async archiveProduct(id){
        try{
          const token = (document.getElementById('eb-token')||{}).value || '';
          const body = new URLSearchParams({ token, id });
          const res = await fetch(`${modulelink}&a=ph-catalog-product-archive-stripe`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
          const out = await res.json();
          if (out && out.status==='success'){ try{ window.showToast && window.showToast('Product archived','success'); }catch(_){} setTimeout(()=>location.reload(),500); }
          else { alert('Archive failed'); }
        } catch(e){ console.error(e); alert('Network error'); }
      },
      async deleteProduct(id){
        try{
          if (!confirm('Delete this product on Stripe? This cannot be undone.')) return;
          const token = (document.getElementById('eb-token')||{}).value || '';
          const body = new URLSearchParams({ token, id });
          const res = await fetch(`${modulelink}&a=ph-catalog-product-delete-stripe`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
          const out = await res.json();
          if (out && out.status==='success'){ try{ window.showToast && window.showToast('Product deleted','success'); }catch(_){} setTimeout(()=>location.reload(),600); }
          else { alert('Delete failed'+(out && out.detail? ': '+out.detail : '')); }
        } catch(e){ console.error(e); alert('Network error'); }
      },
      async unarchiveProduct(id){
        try{
          const token = (document.getElementById('eb-token')||{}).value || '';
          const body = new URLSearchParams({ token, id });
          const res = await fetch(`${modulelink}&a=ph-catalog-product-unarchive-stripe`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
          const out = await res.json();
          if (out && out.status==='success'){ try{ window.showToast && window.showToast('Product unarchived','success'); }catch(_){} setTimeout(()=>location.reload(),500); }
          else { alert('Unarchive failed'); }
        } catch(e){ console.error(e); alert('Network error'); }
      }
    };
  }

  // Expose a small helper to split mixed products without embedding object literals in templates (avoids Smarty conflicts)
  if (!window.ebSplitProduct) {
    window.ebSplitProduct = async function(productId, metric){
      try {
        const body = new URLSearchParams({ product_id: String(productId||0), metric_code: String(metric||'') });
        const res = await fetch(`${modulelink}&a=ph-catalog-product-split`, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body });
        const out = await res.json();
        if (out && out.status === 'success') { window.location.reload(); }
        else { alert('Split failed'); }
      } catch (e) { console.error(e); alert('Split failed'); }
    };
  }

  const post = async (action, form) => {
    const body = new FormData(form);
    const res = await fetch(`${modulelink}&a=${action}`, { method:'POST', body });
    return await res.json();
  };

  const createForm = $('#eb-create-product-form');
  if (createForm) createForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      const out = await post('ph-catalog-products-create', createForm);
      if (out.status === 'success') {
        try { window.showToast && window.showToast('Product created','success'); } catch(e){}
        setTimeout(()=>{ window.location.reload(); }, 600);
      } else { console.warn('[eb.catalog] create product error', out); alert('Failed to create product'); }
    } catch (e) { console.error(e); alert('Network error'); }
  });

  const priceForm = $('#eb-add-price-form');
  if (priceForm) priceForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      // Ensure product_id is present even if hidden input missed population
      const pidEl = $('#eb-price-product-id');
      if (pidEl && (!pidEl.value || pidEl.value === '') && currentProductId) {
        pidEl.value = currentProductId;
      }
      if (!pidEl || !pidEl.value) {
        alert('Please click “Add Price” from a specific product first.');
        return;
      }
      const out = await post('ph-catalog-price-create', priceForm);
      if (out.status === 'success') { window.location.reload(); } else { console.warn('[eb.catalog] add price error', out); alert('Failed to add price'); }
    } catch (e) { console.error(e); alert('Network error'); }
  });

  $$('[data-eb-toggle-product]').forEach(chk=>{
    chk.addEventListener('change', async ()=>{
      const body = new FormData();
      body.append('token', ($('input[name=token]')||{}).value || '');
      body.append('id', chk.getAttribute('data-eb-toggle-product'));
      body.append('active', chk.checked ? '1':'0');
      try { await fetch(`${modulelink}&a=ph-catalog-product-toggle`, { method:'POST', body }); } catch(e){}
    });
  });

  $$('[data-eb-toggle-price]').forEach(chk=>{
    chk.addEventListener('change', async ()=>{
      const body = new FormData();
      body.append('token', ($('input[name=token]')||{}).value || '');
      body.append('id', chk.getAttribute('data-eb-toggle-price'));
      body.append('active', chk.checked ? '1':'0');
      try { await fetch(`${modulelink}&a=ph-catalog-price-toggle`, { method:'POST', body }); } catch(e){}
    });
  });
})();

// Alpine wizard for New Product
window.productForm = function initProductForm({ currency = 'CAD', ready = 0 }){
  return {
    modulelink: (function(){ const a=document.querySelector('a[href*="index.php?m=eazybackup"]'); return a?a.href.split('&a=')[0]:'index.php?m=eazybackup'; })(),
    state: {
      step: 1,
      ready: !!ready,
      currency,
      product: { name: '', description: '', category: 'Backup' },
      selectedPreset: '',
      baseMetric: null,
      mixedMetrics: false,
      productId: null,
      stripeProductId: null,
      isStripeRemote: false,
      items: [],
      footer: '',
      lastSeededMetric: null
    },
    init(){ try { window.ebProductWizard = this; } catch(e){} },
    addDefaultForBaseMetric(){
      try {
        if (this.state.items && this.state.items.length > 0) return;
        const m = String(this.state.baseMetric||'');
        if (!m) return;
        if (m === 'STORAGE_TB') { this.addPreset('cloud_storage'); return; }
        const defaults = {
          'DEVICE_COUNT': { label:'Workstation Seat', unitLabel:'device' },
          'DISK_IMAGE': { label:'Disk Image', unitLabel:'machine' },
          'HYPERV_VM': { label:'Hyper-V VM', unitLabel:'VM' },
          'PROXMOX_VM': { label:'Proxmox VM', unitLabel:'VM' },
          'VMWARE_VM': { label:'VMware VM', unitLabel:'VM' },
          'M365_USER': { label:'Microsoft 365 User', unitLabel:'user' },
          'GENERIC': { label:'Generic', unitLabel:'unit' },
        };
        const d = defaults[m] || { label: this.metricLabel(m), unitLabel: 'unit' };
        this.state.items.push({ label: d.label, billingType:'per_unit', metric:m, unitLabel:d.unitLabel, amount:0, interval:'month', active:true });
        this.state.lastSeededMetric = m;
      } catch(_){ }
    },
    reset(){ this.state.step=1; this.state.productId=null; this.state.stripeProductId=null; this.state.isStripeRemote=false; this.state.product={ name:'', description:'', category:'Backup' }; this.state.items=[]; this.state.footer=''; this.state.selectedPreset=''; },
    openCreate(){ this.reset(); const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.remove('hidden'); },
    async openEdit(id){ try{ this.reset(); const res = await fetch(`${this.modulelink}&a=ph-catalog-product-get&id=${encodeURIComponent(id)}`, { method:'GET' }); const out = await res.json(); if(out.status!=='success') throw new Error('bad'); this.state.productId = out.product.id; this.state.product = { name: out.product.name||'', description: out.product.description||'', category: out.product.category||'Backup' }; this.state.baseMetric = out.product.base_metric_code || null; this.state.mixedMetrics = !!out.mixed_metrics; this.state.items = (out.prices||[]).map(pr=>({ id: pr.id, label: pr.name||'', billingType: (pr.kind==='metered'?'metered':(pr.kind==='one_time'?'one_time':'per_unit')), metric: pr.metric_code||this.state.baseMetric||'GENERIC', unitLabel: pr.unit_label||'unit', amount: (Number(pr.unit_amount||0)/100), interval: (pr.kind==='one_time'?'none':(pr.interval||'month')), active: !!pr.active })); this.state.step=1; const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.remove('hidden'); }catch(e){ console.error('[eb.catalog] openEdit failed', e); const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.remove('hidden'); } },
      async openEditStripe(stripeId){
        try {
          this.reset();
          const token = (document.getElementById('eb-token')||{}).value || '';
          const res = await fetch(`${this.modulelink}&a=ph-catalog-product-get-stripe&id=${encodeURIComponent(stripeId)}&token=${encodeURIComponent(token)}`,
            { method:'GET', credentials:'include', headers:{ 'Accept':'application/json' } });
          if (res.redirected || (res.url && /\/login(\?|$)/.test(res.url))) { window.location.href = res.url; return; }
          const ct = (res.headers && res.headers.get('content-type')) || '';
          if (!ct || ct.indexOf('application/json') === -1) { window.location.href = `${this.modulelink}&a=ph-catalog-products`; return; }
          const out = await res.json();
          if (out.status !== 'success') throw new Error('bad');
          this.state.isStripeRemote = true;
          this.state.stripeProductId = out.product.id;
          this.state.product = { name: out.product.name||'', description: out.product.description||'', category: 'Backup' };
          this.state.baseMetric = 'GENERIC';
          this.state.mixedMetrics = !!out.mixed_metrics;
          this.state.items = (out.prices||[]).map(pr=>({ id: pr.id, label: pr.label || pr.nickname || '', billingType: pr.billingType || (pr.kind==='metered'?'metered':(pr.kind==='one_time'?'one_time':'per_unit')), metric: 'GENERIC', unitLabel: pr.unitLabel || 'unit', amount: Number(pr.amount||0), interval: pr.interval || 'month', active: !!pr.active }));
          this.state.step = 1;
          const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.remove('hidden');
        } catch (e) {
          console.error('[eb.catalog] openEditStripe failed', e);
          const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.remove('hidden');
        }
      },
    async openAddPrice(id){ await this.openEdit(id); if(this.state.items.length===0 && this.state.product.category==='Cloud Storage'){ this.addPreset('cloud_storage'); } this.state.step=2; },
    close(){ const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.add('hidden'); },
    addEmptyItem(){ const m = this.state.baseMetric || 'GENERIC'; const bt = (m==='STORAGE_TB') ? 'metered' : 'per_unit'; const unit = (m==='STORAGE_TB') ? '' : 'unit'; this.state.items.push({ label:'', billingType:bt, metric:m, unitLabel:unit, amount:0, interval:'month', active:true }); },
    removeItem(i){ try { if (Array.isArray(this.state.items) && i>=0 && i<this.state.items.length) { this.state.items.splice(i,1); } } catch(e){} },
    onCategoryChange(){ try { if (this.state.product.category === 'Cloud Storage' && this.state.items.length === 0) { this.addPreset('cloud_storage'); } } catch(e){} },
    billingLabel(v){ return v==='per_unit'?'Per-unit':(v==='metered'?'Metered':'One-time'); },
    productTypeLabel(v){ return this.metricLabel(v); },
    metricLabel(v){
      switch(String(v||'')){
        case 'STORAGE_TB': return 'Storage';
        case 'DEVICE_COUNT': return 'Device Count';
        case 'DISK_IMAGE': return 'Disk Image';
        case 'HYPERV_VM': return 'Hyper-V VM';
        case 'PROXMOX_VM': return 'Proxmox VM';
        case 'VMWARE_VM': return 'VMware VM';
        case 'M365_USER': return 'Microsoft 365 User';
        case 'GENERIC': return 'Generic';
        default: return v;
      }
    },
    intervalLabel(v){ return v==='month'?'Month':(v==='year'?'Year':v); },
    isBillingDisabled(i,opt){ const it=this.state.items[i]; if(!it) return false; if(it.metric==='STORAGE_TB'){ return opt!=='metered'; } if(['DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER'].includes(it.metric)){ return opt!=='per_unit'; } return false; },
    allValid(){
      if (!this.state.items || this.state.items.length===0) return false;
      for (const it of this.state.items) {
        if (!it || !it.label || String(it.label).trim()==='') return false;
        if (!(Number(it.amount) > 0)) return false;
        if (it.metric==='STORAGE_TB') {
          if (!(it.unitLabel==='GiB' || it.unitLabel==='TiB')) return false;
        }
      }
      return true;
    },
    next(){ try {
      if (this.state.step === 1) {
        if (this.state.lastSeededMetric !== this.state.baseMetric) { this.state.items = []; }
        if (this.state.items.length === 0) {
          // Prefer explicit Cloud Storage preset, else derive from selected base metric
          if (this.state.product.category === 'Cloud Storage' || this.state.baseMetric === 'STORAGE_TB') {
            this.addPreset('cloud_storage');
          } else {
            this.addDefaultForBaseMetric();
          }
        }
      }
      if (this.state.step < 3) { this.state.step++; }
    } catch(e){} },
    prev(){ if (this.state.step > 1) { this.state.step--; } },
    addPreset(key){
      const cur = this.state.currency || 'CAD';
      if (key==='cloud_storage') { this.state.product.category='Cloud Storage'; this.state.baseMetric='STORAGE_TB'; this.state.items.push({ label:'Storage', billingType:'metered', metric:'STORAGE_TB', unitLabel:'', amount:0.00, interval:'month', active:true }); this.state.selectedPreset=key; this.state.lastSeededMetric='STORAGE_TB'; return; }
      const presets = {
        workstation: { label:'Workstation Seat', metric:'DEVICE_COUNT', unitLabel:'device' },
        disk_image: { label:'Disk Image', metric:'DISK_IMAGE', unitLabel:'machine' },
        hyperv: { label:'Hyper-V VM', metric:'HYPERV_VM', unitLabel:'VM' },
        proxmox: { label:'Proxmox VM', metric:'PROXMOX_VM', unitLabel:'VM' },
        vmware: { label:'VMware VM', metric:'VMWARE_VM', unitLabel:'VM' },
        m365: { label:'Microsoft 365 User', metric:'M365_USER', unitLabel:'user' },
      };
      if (presets[key]) { const p=presets[key]; this.state.baseMetric=p.metric; this.state.items.push({ label:p.label, billingType:'per_unit', metric:p.metric, unitLabel:p.unitLabel, amount:0, interval:'month', active:true }); this.state.selectedPreset=key; this.state.lastSeededMetric=p.metric; }
    },
    onMetricChange(i){ const it=this.state.items[i]; if(!it) return; if (it.metric==='STORAGE_TB'){ it.billingType='metered'; /* unit set via unit dropdown */ } if(['DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER'].includes(it.metric)){ it.billingType='per_unit'; } },
    onBillingTypeChange(i){ const it=this.state.items[i]; if(!it) return; if (it.billingType==='one_time'){ it.interval='none'; } if (it.billingType==='metered'){ if(it.metric!=='STORAGE_TB'){ it.metric='GENERIC'; } } },
    selectProductType(code){ this.state.baseMetric = code; if (code==='STORAGE_TB') { this.state.product.category='Cloud Storage'; } else { this.state.product.category='Backup'; } if (this.state.step===1) { this.state.items=[]; this.state.selectedPreset=''; this.state.lastSeededMetric=null; } },
      async save(mode){
      try{
        if (this.state.isStripeRemote) {
          const token = (document.getElementById('eb-token')||{}).value || '';
          const body = new URLSearchParams({ token, payload: JSON.stringify({ stripe_product_id: this.state.stripeProductId, product: { name: this.state.product.name, description: this.state.product.description }, items: this.state.items, currency: this.state.currency }) });
          const res = await fetch(`${this.modulelink}&a=ph-catalog-product-save-stripe&token=${encodeURIComponent(token)}`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded', 'Accept':'application/json' }, body });
          if (res.redirected || (res.url && /\/login(\?|$)/.test(res.url))) { window.location.href = res.url; return; }
          const ct2 = (res.headers && res.headers.get('content-type')) || '';
          if (!ct2 || ct2.indexOf('application/json') === -1) { window.location.href = `${this.modulelink}&a=ph-catalog-products`; return; }
          const out = await res.json();
          if (out.status==='success'){
            try { window.showToast && window.showToast('Product updated','success'); } catch(_){ }
            setTimeout(()=>{ window.location.href = `${this.modulelink}&a=ph-catalog-products`; }, 600);
            return;
          }
          alert('Save failed');
          return;
        }
        const payload = { mode, product_id: this.state.productId, product: this.state.product, base_metric_code: this.state.baseMetric || null, items: this.state.items };
        let res = await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(payload) });
        let out = await res.json();
        if (out && out.status==='error' && (out.message==='bad_json' || out.message==='empty')) {
          // Fallback: some stacks strip JSON; retry as form-encoded
          const body = new URLSearchParams({ payload: JSON.stringify(payload) });
          res = await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body });
          out = await res.json();
        }
        if (out.status==='success'){
          if (mode==='draft') {
            try { window.showToast && window.showToast('Draft saved','success'); } catch(_){ }
            this.state.footer = 'Draft saved. Reloading…';
            setTimeout(()=>{ window.location.href = `${this.modulelink}&a=ph-catalog-products`; }, 600);
          } else {
            try { window.showToast && window.showToast('Product created','success'); } catch(_){ }
            setTimeout(()=>{ window.location.href = `${this.modulelink}&a=ph-catalog-products`; }, 600);
          }
          return;
        }
        if (out.message==='mismatched_metric'){ alert('One or more price items use a different resource type than this product. Please align metrics or split into a new product.'); return; }
        if (out.message==='stripe_name_exists'){ alert('A Stripe product with this name already exists on the connected account. Please choose a new, unique product name.'); return; }
        if (out.message==='stripe_product_fail'){ alert((out.detail && out.detail.indexOf('Idempotency')>=0) ? 'A Stripe product with this name already exists for the connected account or an earlier attempt created it with different parameters. Please choose a new unique name.' : ('Stripe product error: '+(out.detail||''))); return; }
        if (out.message==='stripe_price_fail'){ alert('Stripe price creation failed: '+(out.detail||'')); return; }
        if (out.message==='stripe_not_ready'){ alert('Stripe account not ready. Finish onboarding before publishing.'); return; }
        alert('Save failed');
      }catch(e){ console.error(e); alert('Network error'); }
    }
  };
}

// Alpine helper: numeric stepper with min/max/step and safe coercion
if (!window.ebStepper) {
  window.ebStepper = function(opts){
    return {
      value: 0,
      min: isFinite(opts && opts.min) ? Number(opts.min) : -Infinity,
      max: isFinite(opts && opts.max) ? Number(opts.max) : Infinity,
      step: isFinite(opts && opts.step) && Number(opts.step) > 0 ? Number(opts.step) : 1,
      dec(){
        var v = parseFloat(this.value); if (!isFinite(v)) v = 0;
        v = v - this.step;
        if (isFinite(this.min) && v < this.min) v = this.min;
        this.value = Number(v.toFixed(2));
        if (this.$refs && this.$refs.input) { this.$refs.input.value = this.value; this.$refs.input.dispatchEvent(new Event('input', { bubbles: true })); }
      },
      inc(){
        var v = parseFloat(this.value); if (!isFinite(v)) v = 0;
        v = v + this.step;
        if (isFinite(this.max) && v > this.max) v = this.max;
        this.value = Number(v.toFixed(2));
        if (this.$refs && this.$refs.input) { this.$refs.input.value = this.value; this.$refs.input.dispatchEvent(new Event('input', { bubbles: true })); }
      }
    };
  };
}

// Alpine wrapper to avoid Smarty parsing object spread in x-data
if (!window.ebPriceStepper) {
  window.ebPriceStepper = function(step){
    var base = window.ebStepper({ min: 0, step: (isFinite(step)? Number(step): 0.01) || 0.01 });
    base.hovered = '';
    return base;
  };
}



// Slide-over panels: Product and Price editors
(function(){
  function showEl(id){ var el=document.getElementById(id); if(el) el.classList.remove('hidden'); }
  function hideEl(id){ var el=document.getElementById(id); if(el) el.classList.add('hidden'); }
  function safeToast(msg, kind){ try{ window.showToast && window.showToast(msg, kind||'info'); }catch(_){}}

  window.productPanelFactory = function(opts){
    var currency = (opts && opts.currency) || 'CAD';
    var ready = !!(opts && opts.ready);
    return {
      modulelink: (function(){ const a=document.querySelector('a[href*="index.php?m=eazybackup"]'); return a?a.href.split('&a=')[0]:'index.php?m=eazybackup'; })(),
      isOpen: false,
      mode: 'create', // 'create' | 'edit' | 'editStripe'
      currency: currency,
      ready: ready,
      product: { name:'', description:'' },
      baseMetric: null,
      items: [],
      features: [],
      lastSeededMetric: null,
      showInactive: false,
      init(){ try{ window.ebProductPanel = this; }catch(_){ } },
      open(){ this.isOpen=true; showEl('eb-product-panel'); },
      close(){ this.isOpen=false; hideEl('eb-product-panel'); },
      reset(){ this.mode='create'; this.product={ name:'', description:'' }; this.baseMetric=null; this.items=[]; this.features=[]; this.lastSeededMetric=null; },
      openCreate(){ this.reset(); this.mode='create'; this.open(); },
      async openEdit(id){ try{ this.reset(); this.mode='edit'; const res = await fetch(`${this.modulelink}&a=ph-catalog-product-get&id=${encodeURIComponent(id)}`, { method:'GET' }); const out = await res.json(); if(out.status!=='success') throw new Error('bad'); var p=out.product||{}; this.product={ name:p.name||'', description:p.description||'' }; this.baseMetric = p.base_metric_code || null; this.items=(out.prices||[]).map(pr=>({ id: pr.id, label: pr.name||'', billingType:(pr.kind==='metered'?'metered':(pr.kind==='one_time'?'one_time':'per_unit')), metric: pr.metric_code||this.baseMetric||'GENERIC', unitLabel: pr.unit_label||'unit', amount:Number(pr.unit_amount||0)/100, interval:(pr.kind==='one_time'?'none':(pr.interval||'month')), active:!!pr.active })); this.features=Array.isArray(p.features)?p.features:[]; this.open(); }catch(e){ console.error('[eb.catalog] openEdit(panel) failed',e); safeToast('Failed to load product','error'); } },
      async openEditStripe(spid){ try{ this.reset(); this.mode='editStripe'; this.stripeProductId = spid; this.showInactive = false; await this.refreshStripePrices(); } catch(e){ console.error('[eb.catalog] openEditStripe(panel) failed',e); safeToast('Failed to load Stripe product','error'); } },
      async refreshStripePrices(){ try{ const token=(document.getElementById('eb-token')||{}).value||''; const activeParam=this.showInactive?'all':'1'; const res=await fetch(`${this.modulelink}&a=ph-catalog-product-get-stripe&id=${encodeURIComponent(this.stripeProductId)}&active=${encodeURIComponent(activeParam)}&token=${encodeURIComponent(token)}`, { method:'GET', credentials:'include' }); const out=await res.json(); if(out.status!=='success') throw new Error('bad'); var p=out.product||{}; this.product={ name:p.name||'', description:p.description||'' }; var bm = p.base_metric_code || ((out.prices||[]).some(pr => (pr.billingType==='metered')) ? 'STORAGE_TB' : 'GENERIC'); this.baseMetric=bm; this.items=(out.prices||[]).map(pr=>({ id: pr.id, label: pr.label||pr.nickname||'', billingType: pr.billingType || 'per_unit', metric: bm, unitLabel: pr.unitLabel || (bm==='STORAGE_TB'?'GiB':'unit'), amount: Number(pr.amount||0), interval: pr.interval || 'month', active: !!pr.active, currency: pr.currency||this.currency })); this.features=Array.isArray(p.features)?p.features:[]; this.open(); } catch(e){ console.error('[eb.catalog] refreshStripePrices failed', e); safeToast('Failed to load Stripe prices','error'); } },
      metricLabel(v){ switch(String(v||'')){ case 'STORAGE_TB': return 'Storage'; case 'DEVICE_COUNT': return 'Device Count'; case 'DISK_IMAGE': return 'Disk Image'; case 'HYPERV_VM': return 'Hyper-V VM'; case 'PROXMOX_VM': return 'Proxmox VM'; case 'VMWARE_VM': return 'VMware VM'; case 'M365_USER': return 'Microsoft 365 User'; case 'GENERIC': return 'Generic'; default: return v; } },
      billingLabel(v){ return v==='per_unit'?'Per-unit':(v==='metered'?'Metered':'One-time'); },
      selectProductType(code){ this.baseMetric=code; if (this.lastSeededMetric!==code) { this.items=[]; this.lastSeededMetric=null; } if (code==='STORAGE_TB' && this.items.length===0){ this.items.push({ label:'Storage', billingType:'metered', metric:'STORAGE_TB', unitLabel:'', amount:0, interval:'month', active:true }); this.lastSeededMetric='STORAGE_TB'; } else if (this.items.length===0) { const map={ DEVICE_COUNT:['Workstation Seat','device'], DISK_IMAGE:['Disk Image','machine'], HYPERV_VM:['Hyper-V VM','VM'], PROXMOX_VM:['Proxmox VM','VM'], VMWARE_VM:['VMware VM','VM'], M365_USER:['Microsoft 365 User','user'], GENERIC:['Generic','unit'] }; var d=map[code]||['Generic','unit']; this.items.push({ label:d[0], billingType:'per_unit', metric:code, unitLabel:d[1], amount:0, interval:'month', active:true }); this.lastSeededMetric=code; } },
      addEmptyItem(){ const m=this.baseMetric||'GENERIC'; const bt=(m==='STORAGE_TB')?'metered':'per_unit'; const unit=(m==='STORAGE_TB')?'':'unit'; this.items.push({ label:'', billingType:bt, metric:m, unitLabel:unit, amount:0, interval:'month', active:true }); },
      duplicatePrice(i){ try{ const it=this.items[i]; if(!it) return; const cp=JSON.parse(JSON.stringify(it)); delete cp.id; this.items.splice(i+1,0,cp); }catch(_){ } },
      openPrice(i){ try{ if (!window.ebPricePanel || typeof window.ebPricePanel.open!=='function') return; window.ebPricePanel.open(this, i); }catch(_){ } },
      async save(){ try{
        if (!this.product || !this.product.name || String(this.product.name).trim()===''){ safeToast('Enter a product name','warning'); return; }
        if (this.mode==='editStripe') {
          const token=(document.getElementById('eb-token')||{}).value||'';
          const payload={ token, payload: JSON.stringify({ stripe_product_id: this.stripeProductId, product:{ name:this.product.name, description:this.product.description }, items:this.items, currency:this.currency, features:this.features }) };
          const res=await fetch(`${this.modulelink}&a=ph-catalog-product-save-stripe&token=${encodeURIComponent(token)}`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body: new URLSearchParams(payload) });
          const out=await res.json(); if(out && out.status==='success'){ safeToast('Product updated','success'); setTimeout(()=>location.reload(),600); } else { safeToast('Save failed','error'); }
          return;
        }
        // Local create/edit
        const body={ mode:'draft', product_id:(this.mode==='edit'? (this.productId||0):0), product:{ name:this.product.name }, base_metric_code:this.baseMetric||null, items:this.items, features:this.features };
        const res=await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(body) });
        let out=await res.json();
        if (out && out.status==='error' && (out.message==='bad_json' || out.message==='empty')) {
          const res2=await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: new URLSearchParams({ payload: JSON.stringify(body) }) });
          out=await res2.json();
        }
        if (out && out.status==='success'){ safeToast(this.mode==='create'?'Product saved':'Product updated','success'); setTimeout(()=>location.reload(),600); } else { safeToast('Save failed','error'); }
      }catch(e){ console.error(e); safeToast('Network error','error'); } }
    };
  };

  window.pricePanelFactory = function(){
    return {
      owner: null,
      index: -1,
      row: { label:'', amount:0, interval:'month', unitLabel:'', billingType:'per_unit', currency:'' },
      qty: 1,
      init(){ try{ window.ebPricePanel = this; }catch(_){ } },
      open(owner, index){ this.owner=owner; this.index=index; this.row=JSON.parse(JSON.stringify(owner.items[index]||{ label:'', amount:0, interval:'month' })); showEl('eb-price-panel'); },
      close(){ hideEl('eb-price-panel'); },
      unitLabelDisplay(){ try{ if (!this.owner) return (this.row.unitLabel||'unit'); var bm=this.owner.baseMetric||'GENERIC'; if (bm==='STORAGE_TB') return this.row.unitLabel||'GiB'; var map={ DEVICE_COUNT:'device', DISK_IMAGE:'machine', HYPERV_VM:'VM', PROXMOX_VM:'VM', VMWARE_VM:'VM', M365_USER:'user', GENERIC:'unit' }; return this.row.unitLabel || map[bm] || 'unit'; }catch(_){ return this.row.unitLabel||'unit'; } },
      calcSubtotal(){ var a=parseFloat(this.row.amount||0); var q=parseFloat(this.qty||0); if(!isFinite(a)) a=0; if(!isFinite(q)) q=0; return a*q; },
      fmtMoney(n){ var v=Number(n||0); if(!isFinite(v)) v=0; return '$' + v.toFixed(2); },
      save(){ try{ if (!this.owner || this.index<0) return; this.owner.items[this.index]=JSON.parse(JSON.stringify(this.row)); this.close(); }catch(_){ } }
    };
  };
})();