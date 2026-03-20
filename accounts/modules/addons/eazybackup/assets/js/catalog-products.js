(function(){
  function initCatalogProductsPage(){
    const $ = (s,ctx)=> (ctx||document).querySelector(s);
    const $$ = (s,ctx)=> Array.from((ctx||document).querySelectorAll(s));
    const open = el => el && el.classList.remove('hidden');
    const close = el => el && el.classList.add('hidden');
    let activeConfirm = null;

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
    $$('[data-eb-edit-price]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const productId = btn.getAttribute('data-eb-edit-price-product');
        const localPriceId = btn.getAttribute('data-eb-edit-price-local');
        const stripePriceId = btn.getAttribute('data-eb-edit-price-stripe');
        if (window.ebProductPanel && typeof window.ebProductPanel.openEditPrice === 'function') {
          window.ebProductPanel.openEditPrice(productId, localPriceId, stripePriceId);
        } else if (window.ebProductPanel && typeof window.ebProductPanel.openEdit === 'function') {
          window.ebProductPanel.openEdit(productId);
        }
      });
    });
    $$('[data-eb-delete-price]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const priceId = btn.getAttribute('data-eb-delete-price');
        const stripePriceId = btn.getAttribute('data-eb-delete-price-stripe');
        if (window.ebStripeActions && typeof window.ebStripeActions.deletePrice === 'function') {
          window.ebStripeActions.deletePrice(priceId, stripePriceId);
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

    const confirmModalEl = $('#eb-confirm-modal');
    const confirmTitleEl = $('#eb-confirm-title');
    const confirmMessageEl = $('#eb-confirm-message');
    const confirmCancelBtn = $('#eb-confirm-cancel');
    const confirmSubmitBtn = $('#eb-confirm-submit');
    const confirmSubmitLabelEl = $('#eb-confirm-submit-label');
    const confirmSpinnerEl = $('#eb-confirm-spinner');
    const confirmBackdropEl = $('#eb-confirm-backdrop');

    function setConfirmBusy(isBusy){
      if (!confirmSubmitBtn || !confirmCancelBtn) return;
      confirmSubmitBtn.disabled = !!isBusy;
      confirmCancelBtn.disabled = !!isBusy;
      confirmSubmitBtn.classList.toggle('opacity-70', !!isBusy);
      confirmSubmitBtn.classList.toggle('cursor-not-allowed', !!isBusy);
      confirmCancelBtn.classList.toggle('opacity-70', !!isBusy);
      confirmCancelBtn.classList.toggle('cursor-not-allowed', !!isBusy);
      if (confirmSpinnerEl) confirmSpinnerEl.classList.toggle('hidden', !isBusy);
    }

    function teardownConfirm(){
      if (!activeConfirm) return;
      document.removeEventListener('keydown', activeConfirm.onKeydown);
      if (confirmSubmitBtn) confirmSubmitBtn.onclick = null;
      if (confirmCancelBtn) confirmCancelBtn.onclick = null;
      if (confirmBackdropEl) confirmBackdropEl.onclick = null;
      setConfirmBusy(false);
      close(confirmModalEl);
      activeConfirm = null;
    }

    function showConfirmDialog(opts){
      if (!confirmModalEl) return Promise.resolve(window.confirm((opts && opts.message) || 'Are you sure?'));
      if (activeConfirm) {
        try { activeConfirm.resolve(false); } catch(_){}
        teardownConfirm();
      }
      return new Promise((resolve)=>{
        const title = (opts && opts.title) || 'Confirm action';
        const message = (opts && opts.message) || 'Are you sure you want to continue?';
        const confirmLabel = (opts && opts.confirmLabel) || 'Confirm';
        const onConfirm = (opts && opts.onConfirm) || null;

        if (confirmTitleEl) confirmTitleEl.textContent = title;
        if (confirmMessageEl) confirmMessageEl.textContent = message;
        if (confirmSubmitLabelEl) confirmSubmitLabelEl.textContent = confirmLabel;
        setConfirmBusy(false);
        open(confirmModalEl);

        const finish = (result)=>{
          teardownConfirm();
          resolve(result);
        };

        const cancel = ()=>{
          if (activeConfirm && activeConfirm.busy) return;
          finish(false);
        };

        const submit = async ()=>{
          if (activeConfirm && activeConfirm.busy) return;
          if (typeof onConfirm !== 'function') {
            finish(true);
            return;
          }
          activeConfirm.busy = true;
          setConfirmBusy(true);
          try {
            const result = await onConfirm();
            finish(result !== false);
          } catch (e) {
            activeConfirm.busy = false;
            setConfirmBusy(false);
            console.error(e);
          }
        };

        const onKeydown = (event)=>{
          if (event.key === 'Escape') cancel();
        };

        activeConfirm = { resolve, onKeydown, busy: false };
        document.addEventListener('keydown', onKeydown);
        if (confirmCancelBtn) confirmCancelBtn.onclick = cancel;
        if (confirmSubmitBtn) confirmSubmitBtn.onclick = submit;
        if (confirmBackdropEl) confirmBackdropEl.onclick = cancel;
      });
    }

    // Stripe product actions (archive/delete)
    if (!window.ebStripeActions) {
      window.ebStripeActions = {
        async archiveProduct(id){
          try{
            await showConfirmDialog({
              title: 'Archive product',
              message: 'Archive this product? It will no longer be available for new billing. Existing records remain available for history, invoices, subscriptions, and auditability.',
              confirmLabel: 'Archive product',
              onConfirm: async ()=>{
                const token = (document.getElementById('eb-token')||{}).value || '';
                const body = new URLSearchParams({ token, id });
                const res = await fetch(`${modulelink}&a=ph-catalog-product-archive-stripe`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
                const out = await res.json();
                if (out && out.status==='success'){ try{ window.showToast && window.showToast('Product archived','success'); }catch(_){} setTimeout(()=>location.reload(),500); return true; }
                alert('Archive failed'+(out && out.detail ? ': '+out.detail : ''));
                return false;
              }
            });
          } catch(e){ console.error(e); alert('Network error'); }
        },
        async deleteProduct(id){
          try{
            await showConfirmDialog({
              title: 'Delete Stripe product',
              message: 'Delete this product on Stripe? This cannot be undone.',
              confirmLabel: 'Delete product',
              onConfirm: async ()=>{
                const token = (document.getElementById('eb-token')||{}).value || '';
                const body = new URLSearchParams({ token, id });
                const res = await fetch(`${modulelink}&a=ph-catalog-product-delete-stripe`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
                const out = await res.json();
                if (out && out.status==='success'){ try{ window.showToast && window.showToast('Product deleted','success'); }catch(_){} setTimeout(()=>location.reload(),600); return true; }
                alert('Delete failed'+(out && out.detail? ': '+out.detail : ''));
                return false;
              }
            });
          } catch(e){ console.error(e); alert('Network error'); }
        },
        async unarchiveProduct(id){
          try{
            await showConfirmDialog({
              title: 'Unarchive product',
              message: 'Unarchive this product and make it available for new billing again?',
              confirmLabel: 'Unarchive product',
              onConfirm: async ()=>{
                const token = (document.getElementById('eb-token')||{}).value || '';
                const body = new URLSearchParams({ token, id });
                const res = await fetch(`${modulelink}&a=ph-catalog-product-unarchive-stripe`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
                const out = await res.json();
                if (out && out.status==='success'){ try{ window.showToast && window.showToast('Product unarchived','success'); }catch(_){} setTimeout(()=>location.reload(),500); return true; }
                alert('Unarchive failed'+(out && out.detail ? ': '+out.detail : ''));
                return false;
              }
            });
          } catch(e){ console.error(e); alert('Network error'); }
        },
        async deleteDraft(id){
          try {
            await showConfirmDialog({
              title: 'Delete draft product',
              message: 'Delete this draft product and all its prices? This cannot be undone.',
              confirmLabel: 'Delete draft',
              onConfirm: async ()=>{
                const token = (document.getElementById('eb-token')||{}).value || '';
                const body = new URLSearchParams({ token, id: String(id) });
                const res = await fetch(`${modulelink}&a=ph-catalog-product-delete-draft`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
                const out = await res.json();
                if (out && out.status==='success'){ try{ window.showToast && window.showToast('Draft deleted','success'); }catch(_){} setTimeout(()=>location.reload(),500); return true; }
                alert('Delete failed'+(out && out.message ? ': '+out.message : ''));
                return false;
              }
            });
          } catch(e){ console.error(e); alert('Network error'); }
        },
        async deletePrice(id, stripePriceId){
          try {
            const priceId = String(id || '');
            const isStripePrice = !!stripePriceId;
            let activeSubscriptions = 0;
            if (priceId) {
              try {
                const subRes = await fetch(`${modulelink}&a=ph-catalog-price-sub-count&price_id=${encodeURIComponent(priceId)}`, { method:'GET', credentials:'include', headers:{ 'Accept':'application/json' } });
                const subOut = await subRes.json();
                activeSubscriptions = Number(subOut && subOut.active_subscriptions || 0);
              } catch (_) {}
            }
            if (!isStripePrice && activeSubscriptions > 0) {
              try {
                window.showToast && window.showToast('This local-only price is used in active billing and cannot be deleted.', 'warning');
              } catch (_) {}
              return;
            }
            const dialogTitle = isStripePrice ? 'Archive price' : 'Delete price';
            let dialogMessage = 'Delete this local price? This cannot be undone.';
            if (isStripePrice) {
              dialogMessage = 'Archive this published Stripe price? It will remain available for history, invoices, subscriptions, and auditability, but it will no longer be offered for new billing.';
              if (activeSubscriptions > 0) {
                dialogMessage = `Archive this published Stripe price? It is currently used in ${activeSubscriptions} active subscription${activeSubscriptions === 1 ? '' : 's'}. It will remain available for history, invoices, subscriptions, and auditability, but it will no longer be offered for new billing.`;
              }
            }
            await showConfirmDialog({
              title: dialogTitle,
              message: dialogMessage,
              confirmLabel: isStripePrice ? 'Archive price' : 'Delete price',
              onConfirm: async ()=>{
                const token = (document.getElementById('eb-token')||{}).value || '';
                const body = new URLSearchParams({ token, price_id: priceId });
                const res = await fetch(`${modulelink}&a=ph-catalog-price-delete`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
                const out = await res.json();
                if (out && out.status === 'success') {
                  try { window.showToast && window.showToast(isStripePrice ? 'Price archived' : 'Price deleted','success'); } catch(_){}
                  setTimeout(()=>location.reload(), 500);
                  return true;
                }
                if (out && out.message === 'price_in_use') {
                  try { window.showToast && window.showToast((out.detail || 'This price is currently used in active billing.'),'warning'); } catch(_){}
                  return false;
                }
                alert((isStripePrice ? 'Archive failed' : 'Delete failed')+(out && out.detail ? ': '+out.detail : ''));
                return false;
              }
            });
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
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCatalogProductsPage, { once: true });
  } else {
    initCatalogProductsPage();
  }
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
    async openEdit(id){ try{ this.reset(); const res = await fetch(`${this.modulelink}&a=ph-catalog-product-get&id=${encodeURIComponent(id)}`, { method:'GET' }); const out = await res.json(); if(out.status!=='success') throw new Error('bad'); if (out.product && out.product.stripe_product_id) { await this.openEditStripe(out.product.stripe_product_id); return; } this.state.productId = out.product.id; this.state.product = { name: out.product.name||'', description: out.product.description||'', category: out.product.category||'Backup' }; this.state.baseMetric = out.product.base_metric_code || null; this.state.mixedMetrics = !!out.mixed_metrics; this.state.items = (out.prices||[]).map(pr=>({ id: pr.id, label: pr.name||'', billingType: (pr.kind==='metered'?'metered':(pr.kind==='one_time'?'one_time':'per_unit')), metric: pr.metric_code||this.state.baseMetric||'GENERIC', unitLabel: pr.unit_label||'unit', amount: (Number(pr.unit_amount||0)/100), interval: (pr.kind==='one_time'?'none':(pr.interval||'month')), active: !!pr.active })); this.state.step=1; const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.remove('hidden'); }catch(e){ console.error('[eb.catalog] openEdit failed', e); const modal=document.getElementById('eb-create-product-modal'); if(modal) modal.classList.remove('hidden'); } },
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
        const token = (document.getElementById('eb-token')||{}).value || '';
        const payload = { mode, product_id: this.state.productId, product: this.state.product, base_metric_code: this.state.baseMetric || null, items: this.state.items, token };
        let res = await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(payload) });
        let out = await res.json();
        if (out && out.status==='error' && (out.message==='bad_json' || out.message==='empty')) {
          // Fallback: some stacks strip JSON; retry as form-encoded
          const body = new URLSearchParams({ token, payload: JSON.stringify(payload) });
          res = await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body });
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
        if (out.message==='csrf'){ try { window.showToast && window.showToast('Your session expired or the security token was invalid. Refresh the page and try again.', 'warning'); } catch(_){ alert('Your session expired or the security token was invalid. Refresh the page and try again.'); } return; }
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

if (!window.catalogToastManager) {
  window.catalogToastManager = function(){
    return {
      toasts: [],
      init(){
        try {
          var self = this;
          window.__catalogToastMounted = true;
          window.__catalogToastPush = function(detail){ self.push(detail); };
        } catch(_){}
      },
      push(detail){
        var toast = {
          id: Date.now() + Math.random(),
          message: String((detail && detail.message) || ''),
          type: String((detail && detail.type) || 'info'),
          visible: true
        };
        this.toasts.push(toast);
        setTimeout(() => { toast.visible = false; }, 2200);
        setTimeout(() => {
          this.toasts = this.toasts.filter(function(item){ return item.id !== toast.id; });
        }, 3000);
      }
    };
  };
}



// Slide-over panels: Product and Price editors
(function(){
  function showEl(id){ var el=document.getElementById(id); if(el) el.classList.remove('hidden'); }
  function hideEl(id){ var el=document.getElementById(id); if(el) el.classList.add('hidden'); }
  function safeToast(msg, kind){
    var detail = { message: String(msg || ''), type: String(kind || 'info') };
    try {
      if (typeof window.__catalogToastPush === 'function') {
        window.__catalogToastPush(detail);
        return;
      }
      if (typeof window.showToast === 'function') {
        window.showToast(detail.message, detail.type);
        return;
      }
    } catch(_) {
      try{ window.showToast && window.showToast(detail.message, detail.type); }catch(__){}
    }
  }
  function defaultUnitLabel(metric){
    var map = { DEVICE_COUNT:'device', DISK_IMAGE:'machine', HYPERV_VM:'VM', PROXMOX_VM:'VM', VMWARE_VM:'VM', M365_USER:'user', GENERIC:'unit' };
    return map[String(metric || 'GENERIC')] || 'unit';
  }
  function coerceMoney(value){
    var num = Number(value || 0);
    if (!isFinite(num) || num < 0) num = 0;
    return Number(num.toFixed(2));
  }
  function describeSaveError(out){
    if (!out) return 'Save failed';
    if (out.message === 'csrf') return 'Your session expired or the security token was invalid. Refresh the page and try again.';
    if (out.message === 'mismatched_metric') return 'One or more prices use a different product type. Update the price rows to match the selected product type.';
    if (out.message === 'duplicate_pricing_slot') return out.detail ? String(out.detail) : 'Each price needs a unique billing setup.';
    if (out.message === 'stripe_name_exists') return 'A Stripe product with this name already exists. Choose a different name.';
    if (out.message === 'stripe_product_fail') return out.detail ? ('Stripe product error: ' + out.detail) : 'Stripe product creation failed.';
    if (out.message === 'stripe_price_fail') return out.detail ? ('Stripe price creation failed: ' + out.detail) : 'Stripe price creation failed.';
    if (out.message === 'stripe_not_ready') return 'Stripe account not ready. Finish onboarding before saving Stripe products.';
    if (out.message) return String(out.message);
    return 'Save failed';
  }
  function normalizedPricingScheme(value){
    var scheme = String(value || 'per_unit');
    if (scheme === 'tiered_graduated' || scheme === 'tiered_volume') return 'tiered';
    return scheme;
  }
  function normalizedBillingType(metric, billingType){
    var m = String(metric || 'GENERIC');
    var bt = String(billingType || 'per_unit');
    if (m === 'STORAGE_TB') return 'metered';
    if (['DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER'].indexOf(m) !== -1) return 'per_unit';
    if (bt !== 'one_time' && bt !== 'metered') return 'per_unit';
    return bt;
  }
  function normalizedIntervalForSlot(billingType, interval){
    if (String(billingType || '') === 'one_time') return 'none';
    var normalized = String(interval || 'month');
    return normalized && normalized !== 'none' ? normalized : 'month';
  }
  function pricingSlotParts(item, baseMetric){
    var metric = String(baseMetric || (item && item.metric) || 'GENERIC');
    var currency = String((item && item.currency) || 'CAD').toUpperCase();
    var billingType = normalizedBillingType(metric, item && item.billingType);
    var interval = normalizedIntervalForSlot(billingType, item && item.interval);
    var pricingScheme = normalizedPricingScheme(item && item.pricingScheme);
    return {
      metric: metric,
      currency: currency,
      billingType: billingType,
      interval: interval,
      pricingScheme: pricingScheme
    };
  }
  function pricingSlotKey(item, baseMetric){
    var parts = pricingSlotParts(item, baseMetric);
    return [parts.metric, parts.currency, parts.billingType, parts.interval, parts.pricingScheme].join('|');
  }
  function pricingSlotLabel(item, baseMetric){
    var parts = pricingSlotParts(item, baseMetric);
    var intervalLabel = parts.interval === 'none' ? 'one-time' : parts.interval;
    var billingLabel = parts.billingType === 'per_unit' ? 'per-unit' : (parts.billingType === 'metered' ? 'metered' : 'one-time');
    var schemeLabel = parts.pricingScheme === 'tiered' ? 'tiered' : 'flat rate';
    return parts.currency + ' / ' + billingLabel + ' / ' + intervalLabel + ' / ' + schemeLabel;
  }

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
      focusPriceId: null,
      focusPriceKey: null,
      showInactive: false,
      isSaving: false,
      _dirty: false,
      _edited: false,
      init(){ try{ window.ebProductPanel = this; }catch(_){ } },
      open(){ this.isOpen=true; showEl('eb-product-panel'); },
      close(){
        if (this._edited && !this._dirty) {
          if (!window.confirm('Discard unsaved changes to this product?')) return;
        }
        this.isOpen=false;
        hideEl('eb-product-panel');
        if (this._dirty) { window.location.reload(); }
      },
      preset: null,
      reset(){ this.mode='create'; this.productId=null; this.stripeProductId=null; this.product={ name:'', description:'' }; this.baseMetric=null; this.items=[]; this.features=[]; this.lastSeededMetric=null; this.focusPriceId=null; this.focusPriceKey=null; this.showInactive=false; this.isSaving=false; this._dirty=false; this._edited=false; this.preset=null; },
      openCreate(){ this.reset(); this.mode='create'; this.open(); },
      async openEdit(id, focusPriceId){ try{ this.reset(); this.mode='edit'; const res = await fetch(`${this.modulelink}&a=ph-catalog-product-get&id=${encodeURIComponent(id)}`, { method:'GET' }); const out = await res.json(); if(out.status!=='success') throw new Error('bad'); var p=out.product||{}; if (p.stripe_product_id) { await this.openEditStripe(String(p.stripe_product_id), { focusPriceId: focusPriceId ? String(focusPriceId) : null }); return; } this.productId = p.id || id || null; this.product={ name:p.name||'', description:p.description||'' }; this.baseMetric = p.base_metric_code || null; this.items=(out.prices||[]).map(pr=>({ id: pr.id, label: pr.name||'', billingType:(pr.kind==='metered'?'metered':(pr.kind==='one_time'?'one_time':'per_unit')), metric: pr.metric_code||this.baseMetric||'GENERIC', unitLabel: pr.unit_label||(String(pr.metric_code||this.baseMetric||'GENERIC')==='STORAGE_TB'?'GiB':defaultUnitLabel(pr.metric_code||this.baseMetric||'GENERIC')), amount:Number(pr.unit_amount||0)/100, interval:(pr.kind==='one_time'?'none':(pr.interval||'month')), active:!!pr.active, currency:this.currency, pricingScheme: pr.pricing_scheme||'per_unit', tiers: pr.tiers_json ? (function(){ try{ var t=JSON.parse(pr.tiers_json); return t.map(function(r){ return { up_to:r.up_to, unit_amount:r.unit_amount||0, unit_amount_display:Number(r.unit_amount||0)/100, flat_amount:r.flat_amount||0, flat_amount_display:Number(r.flat_amount||0)/100 }; }); }catch(_){ return []; } })() : [] })); this.features=Array.isArray(p.features)?p.features:[]; this.focusPriceId = focusPriceId ? String(focusPriceId) : null; this.items.forEach((_, idx)=>this.normalizePriceRow(idx)); this.open(); }catch(e){ console.error('[eb.catalog] openEdit(panel) failed',e); safeToast('Failed to load product','error'); } },
      async openEditPrice(productId, localPriceId, stripePriceId){
        try {
          const res = await fetch(`${this.modulelink}&a=ph-catalog-product-get&id=${encodeURIComponent(productId)}`, { method:'GET' });
          const out = await res.json();
          if (out.status !== 'success') throw new Error('bad');
          const p = out.product || {};
          const targetLocalPrice = Array.isArray(out.prices) ? out.prices.find(function(pr){ return String(pr.id || '') === String(localPriceId || ''); }) : null;
          const fallbackFocusKey = targetLocalPrice ? pricingSlotKey({
            metric: targetLocalPrice.metric_code || p.base_metric_code || 'GENERIC',
            currency: targetLocalPrice.currency || this.currency,
            billingType: targetLocalPrice.billing_type || (targetLocalPrice.kind === 'metered' ? 'metered' : (targetLocalPrice.kind === 'one_time' ? 'one_time' : 'per_unit')),
            interval: targetLocalPrice.interval || 'month',
            pricingScheme: targetLocalPrice.pricing_scheme || 'per_unit'
          }, p.base_metric_code || targetLocalPrice.metric_code || 'GENERIC') : null;
          if (p.stripe_product_id) {
            await this.openEditStripe(String(p.stripe_product_id), {
              focusPriceId: stripePriceId ? String(stripePriceId) : null,
              focusPriceKey: fallbackFocusKey
            });
            return;
          }
          await this.openEdit(productId, localPriceId ? String(localPriceId) : null);
        } catch (e) {
          console.error('[eb.catalog] openEditPrice(panel) failed', e);
          safeToast('Failed to load price','error');
        }
      },
      clearPriceFocus(){ this.focusPriceId = null; this.focusPriceKey = null; },
      async openEditStripe(spid, focusTarget){ try{ this.reset(); this.mode='editStripe'; this.stripeProductId = spid; this.focusPriceId = focusTarget && focusTarget.focusPriceId ? String(focusTarget.focusPriceId) : null; this.focusPriceKey = focusTarget && focusTarget.focusPriceKey ? String(focusTarget.focusPriceKey) : null; this.showInactive = true; await this.refreshStripePrices(); } catch(e){ console.error('[eb.catalog] openEditStripe(panel) failed',e); safeToast('Failed to load Stripe product','error'); } },
      async refreshStripePrices(){ try{ const token=(document.getElementById('eb-token')||{}).value||''; const activeParam=this.showInactive?'all':'1'; const res=await fetch(`${this.modulelink}&a=ph-catalog-product-get-stripe&id=${encodeURIComponent(this.stripeProductId)}&active=${encodeURIComponent(activeParam)}&token=${encodeURIComponent(token)}`, { method:'GET', credentials:'include' }); const out=await res.json(); if(out.status!=='success') throw new Error('bad'); var p=out.product||{}; this.product={ name:p.name||'', description:p.description||'' }; var bm = p.base_metric_code || ((out.prices||[]).some(pr => (pr.billingType==='metered')) ? 'STORAGE_TB' : 'GENERIC'); this.baseMetric=bm; this.items=(out.prices||[]).map(pr=>({ id: pr.id, label: pr.label||pr.nickname||'', billingType: pr.billingType || 'per_unit', metric: bm, unitLabel: pr.unitLabel || (bm==='STORAGE_TB'?'GiB':defaultUnitLabel(bm)), amount: Number(pr.amount||0), interval: pr.interval || 'month', active: !!pr.active, currency: pr.currency||this.currency, pricingScheme: pr.pricingScheme || 'per_unit' })); this.features=Array.isArray(p.features)?p.features:[]; this.items.forEach((_, idx)=>this.normalizePriceRow(idx)); if (this.focusPriceKey && !this.focusPriceId) { var matched = this.items.find((it)=> this.priceSlotKey(it) === this.focusPriceKey); if (matched && matched.id) this.focusPriceId = String(matched.id); } if (this.focusPriceId && !this.items.some((it)=> String(it.id || '') === String(this.focusPriceId))) { this.focusPriceId = null; } this.open(); } catch(e){ console.error('[eb.catalog] refreshStripePrices failed', e); safeToast('Failed to load Stripe prices','error'); } },
    metricLabel(v){ switch(String(v||'')){ case 'STORAGE_TB': return 'Storage'; case 'DEVICE_COUNT': return 'Device Count'; case 'DISK_IMAGE': return 'Disk Image'; case 'HYPERV_VM': return 'Hyper-V VM'; case 'PROXMOX_VM': return 'Proxmox VM'; case 'VMWARE_VM': return 'VMware VM'; case 'M365_USER': return 'Microsoft 365 User'; case 'GENERIC': return 'Generic'; default: return v; } },
    metricIcon(v){
      switch(String(v||'')){
        case 'STORAGE_TB': return 'storage.svg';
        case 'DEVICE_COUNT': return 'device_endpoint.svg';
        case 'DISK_IMAGE': return 'disk_image.svg';
        case 'HYPERV_VM': return 'hyper-v.svg';
        case 'PROXMOX_VM': return 'sql_server.svg';
        case 'VMWARE_VM': return 'vmware.svg';
        case 'M365_USER': return 'ms365.svg';
        case 'GENERIC': return 'generic_product.svg';
        default: return 'generic_product.svg';
      }
    },
      metricDescription(v){
        switch(String(v||'')){
          case 'STORAGE_TB': return 'Metered billing based on the customer\'s storage consumption. Priced per GiB or TiB.';
          case 'DEVICE_COUNT': return 'Per-unit billing for each backup endpoint (workstation or server) registered in the customer\'s account.';
          case 'DISK_IMAGE': return 'Per-unit billing for each machine protected with disk image backups.';
          case 'HYPERV_VM': return 'Per-unit billing for each Microsoft Hyper-V virtual machine being backed up.';
          case 'PROXMOX_VM': return 'Per-unit billing for each Proxmox virtual machine being backed up.';
          case 'VMWARE_VM': return 'Per-unit billing for each VMware virtual machine being backed up.';
          case 'M365_USER': return 'Per-unit billing for each Microsoft 365 user account protected.';
          case 'GENERIC': return 'Flexible billing for any service you provide \u2014 IT support, antivirus, consulting, or any recurring/one-time charge.';
          default: return '';
        }
      },
      billingLabel(v){ return v==='per_unit'?'Per-unit':(v==='metered'?'Metered':'One-time'); },
      selectProductType(code){ this.baseMetric=code; this._edited=true; if (this.lastSeededMetric!==code) { this.items=[]; this.lastSeededMetric=null; } if (code==='STORAGE_TB' && this.items.length===0){ this.items.push({ label:'Storage', billingType:'metered', metric:'STORAGE_TB', unitLabel:'GiB', amount:0, interval:'month', active:true, currency:this.currency, pricingScheme:'per_unit' }); this.lastSeededMetric='STORAGE_TB'; } else if (this.items.length===0) { var d=[this.metricLabel(code)||'Generic', defaultUnitLabel(code)]; this.items.push({ label:d[0], billingType:'per_unit', metric:code, unitLabel:d[1], amount:0, interval:'month', active:true, currency:this.currency, pricingScheme:'per_unit' }); this.lastSeededMetric=code; } },
      applyPreset(key){
        this.preset = key;
        var presets = {
          eazybackup_cloud_backup: { name:'eazyBackup Cloud Backup', metric:'STORAGE_TB', items:[{ label:'Storage', billingType:'metered', metric:'STORAGE_TB', unitLabel:'GiB', amount:0, interval:'month', active:true, pricingScheme:'per_unit' }] },
          e3_object_storage: { name:'e3 Object Storage', metric:'STORAGE_TB', items:[{ label:'Object Storage', billingType:'metered', metric:'STORAGE_TB', unitLabel:'GiB', amount:0, interval:'month', active:true, pricingScheme:'per_unit' }] },
          workstation_seat: { name:'Workstation Backup Seat', metric:'DEVICE_COUNT', items:[{ label:'Workstation Seat', billingType:'per_unit', metric:'DEVICE_COUNT', unitLabel:'device', amount:0, interval:'month', active:true, pricingScheme:'per_unit' }] },
          custom_service: { name:'Custom Service', metric:'GENERIC', items:[{ label:'Service', billingType:'per_unit', metric:'GENERIC', unitLabel:'unit', amount:0, interval:'month', active:true, pricingScheme:'per_unit' }] },
        };
        var p = presets[key]; if(!p) return;
        this._edited = true;
        this.product.name = p.name;
        this.baseMetric = p.metric;
        this.items = JSON.parse(JSON.stringify(p.items));
        this.lastSeededMetric = p.metric;
      },
      clearPreset(){ this._edited=true; this.preset=null; },
      addEmptyItem(){ this._edited=true; this.focusPriceId = null; this.focusPriceKey = null; const m=this.baseMetric||'GENERIC'; const bt=(m==='STORAGE_TB')?'metered':'per_unit'; const unit=(m==='STORAGE_TB')?'GiB':defaultUnitLabel(m); this.items.push({ label:'', billingType:bt, metric:m, unitLabel:unit, amount:0, interval:'month', active:true, currency:this.currency, pricingScheme:'per_unit' }); },
      removeItem(i){ try{ if(Array.isArray(this.items) && i>=0 && i<this.items.length){ this._edited=true; var removed = this.items[i]; this.items.splice(i,1); if (this.focusPriceId && removed && String(removed.id || '') === String(this.focusPriceId)) { this.focusPriceId = null; this.focusPriceKey = null; } } }catch(_){ } },
      duplicatePrice(i){ try{ const it=this.items[i]; if(!it) return; this._edited=true; this.focusPriceId = null; this.focusPriceKey = null; const cp=JSON.parse(JSON.stringify(it)); delete cp.id; this.items.splice(i+1,0,cp); }catch(_){ } },
      priceSlotKey(it){ return pricingSlotKey(it, this.baseMetric); },
      priceSlotLabel(it){ return pricingSlotLabel(it, this.baseMetric); },
      normalizePriceRow(i){ try{ var it=this.items[i]; if(!it) return; it.amount = coerceMoney(it.amount); it.metric = this.baseMetric || it.metric || 'GENERIC'; if (it.metric === 'STORAGE_TB') { it.billingType = 'metered'; if (it.unitLabel !== 'GiB' && it.unitLabel !== 'TiB') it.unitLabel = 'GiB'; if (it.interval === 'none' || !it.interval) it.interval = 'month'; } else if (it.metric === 'GENERIC') { if (it.billingType !== 'one_time' && it.billingType !== 'per_unit') it.billingType = 'per_unit'; if (it.billingType === 'one_time') it.interval = 'none'; else if (it.interval === 'none' || !it.interval) it.interval = 'month'; if (!it.unitLabel) it.unitLabel = defaultUnitLabel(it.metric); } else { it.billingType = 'per_unit'; if (it.interval === 'none' || !it.interval) it.interval = 'month'; if (!it.unitLabel) it.unitLabel = defaultUnitLabel(it.metric); } }catch(_){ } },
      onInlineBillingTypeChange(i){ this._edited=true; this.normalizePriceRow(i); },
      onPricingSchemeChange(i){
        var it = this.items[i]; if(!it) return;
        this._edited = true;
        if (!it.pricingScheme) it.pricingScheme = 'per_unit';
        if (it.pricingScheme.startsWith('tiered')) {
          if (!it.tiers || it.tiers.length === 0) {
            it.tiers = [
              { up_to: 1024, unit_amount: 0, unit_amount_display: 0, flat_amount: 0, flat_amount_display: 0 },
              { up_to: null, unit_amount: 0, unit_amount_display: 0, flat_amount: 0, flat_amount_display: 0 },
            ];
          }
        }
      },
      addTier(i){
        var it = this.items[i]; if(!it) return;
        this._edited = true;
        if (!it.tiers) it.tiers = [];
        it.tiers.push({ up_to: null, unit_amount: 0, unit_amount_display: 0, flat_amount: 0, flat_amount_display: 0 });
      },
      removeTier(i, ti){
        var it = this.items[i]; if(!it || !it.tiers || it.tiers.length <= 2) return;
        this._edited = true;
        it.tiers.splice(ti, 1);
      },
      async togglePriceActive(i){
        var it = this.items[i]; if(!it) return;
        if (it.active && it.id) {
          try {
            var res = await fetch(`${this.modulelink}&a=ph-catalog-price-sub-count&price_id=${encodeURIComponent(it.id)}`, { method:'GET', credentials:'include' });
            var out = await res.json();
            if (out && out.active_subscriptions > 0) {
              if (!confirm('This price has ' + out.active_subscriptions + ' active subscription(s). Deactivating it may affect billing for those subscribers. Continue?')) return;
            }
          } catch(_) {}
        }
        this._edited = true;
        var nextState = !it.active;
        it.active = nextState;
        if (nextState) {
          var targetKey = this.priceSlotKey(it);
          for (var pi = 0; pi < this.items.length; pi++) {
            if (pi === i) continue;
            if (this.priceSlotKey(this.items[pi]) === targetKey) {
              this.items[pi].active = false;
            }
          }
        }
      },
      validateBeforeSave(){
        if (!this.product || !String(this.product.name||'').trim()) return 'Enter a product name';
        if (!this.baseMetric) return 'Select a product type';
        if (!Array.isArray(this.items) || this.items.length===0) return 'Add at least one price';
        var seenSlots = {};
        for (var i=0;i<this.items.length;i++){
          this.normalizePriceRow(i);
          var it=this.items[i]||{};
          if (!String(it.label||'').trim()) return 'Each price needs a label';
          var scheme = it.pricingScheme || 'per_unit';
          if (!scheme.startsWith('tiered')) {
            if (!(Number(it.amount||0) > 0)) return 'Each price needs an amount greater than 0';
            var cents = Math.round(Number(it.amount||0) * 100);
            if (cents < 50) return 'Stripe requires a minimum amount of $0.50 for price "' + (it.label||'') + '"';
          } else {
            if (!it.tiers || it.tiers.length < 2) return 'Tiered pricing requires at least 2 tiers';
            for (var ti=0; ti<it.tiers.length; ti++){
              var tier = it.tiers[ti];
              if (ti < it.tiers.length - 1 && (!tier.up_to || tier.up_to <= 0)) return 'Each tier except the last needs an upper bound';
              if (Number(tier.unit_amount_display||0) < 0) return 'Tier per-unit amount cannot be negative';
            }
          }
          if (String(it.metric||'') !== String(this.baseMetric||'')) return 'Each price must match the selected product type';
          if (it.metric === 'STORAGE_TB' && it.unitLabel !== 'GiB' && it.unitLabel !== 'TiB') return 'Storage prices must use GiB or TiB';
          var slotKey = this.priceSlotKey(it);
          if (seenSlots[slotKey] !== undefined) return 'Each price needs a unique billing setup. Duplicate setup: ' + this.priceSlotLabel(it);
          seenSlots[slotKey] = i;
        }
        return '';
      },
      async save(mode){ try{
        var validationError = this.validateBeforeSave();
        if (validationError) { safeToast(validationError,'warning'); return; }
        var wasCreate = this.mode === 'create';
        this.isSaving = true;
        if (this.mode==='editStripe') {
          const token=(document.getElementById('eb-token')||{}).value||'';
          const payload={ token, payload: JSON.stringify({ stripe_product_id: this.stripeProductId, product:{ name:this.product.name, description:this.product.description }, items:this.items, currency:this.currency, features:this.features }) };
          const res=await fetch(`${this.modulelink}&a=ph-catalog-product-save-stripe&token=${encodeURIComponent(token)}`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body: new URLSearchParams(payload) });
          const out=await res.json(); if(out && out.status==='success'){ this._dirty=true; await this.refreshStripePrices(); safeToast('Product updated','success'); } else { safeToast(describeSaveError(out),'error'); }
          return;
        }
        // Local create/edit
        const token=(document.getElementById('eb-token')||{}).value||'';
        const body={ mode: mode || 'draft', product_id:(this.mode==='edit'? (this.productId||0):0), product:{ name:this.product.name, description:this.product.description }, base_metric_code:this.baseMetric||null, items:this.items, features:this.features, token };
        const res=await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(body) });
        let out=await res.json();
        if (out && out.status==='error' && (out.message==='bad_json' || out.message==='empty')) {
          const res2=await fetch(`${this.modulelink}&a=ph-catalog-product-save`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: new URLSearchParams({ token, payload: JSON.stringify(body) }) });
          out=await res2.json();
        }
        if (out && out.status==='success'){ this._dirty=true; this.mode='edit'; this.productId = out.product_id || this.productId; if (Array.isArray(out.prices)) { for (var pi=0; pi<out.prices.length; pi++) { if (this.items[pi]) this.items[pi].id = out.prices[pi]; } } safeToast(mode==='publish' ? 'Product published to Stripe' : 'Draft saved','success'); } else { safeToast(describeSaveError(out),'error'); }
      }catch(e){ console.error(e); safeToast('Network error','error'); } finally { this.isSaving = false; } }
    };
  };
})();
