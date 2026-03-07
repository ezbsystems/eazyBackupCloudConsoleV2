(function(){
  var showEl = function(id){ var el = document.getElementById(id); if(el) el.classList.remove('hidden'); };
  var hideEl = function(id){ var el = document.getElementById(id); if(el) el.classList.add('hidden'); };
  function safeToast(msg,type){ try{ if(window.showToast) window.showToast(msg,type); else alert(msg); }catch(_){ alert(msg); } }

  window.planWizardFactory = function(opts){
    var modulelink = (opts && opts.modulelink) || 'index.php?m=eazybackup';
    var token = (opts && opts.token) || '';
    var currency = (opts && opts.currency) || 'CAD';
    return {
      isOpen: false,
      step: 1,
      isSaving: false,
      planType: '',
      planName: '',
      planDescription: '',
      trialDays: 0,
      billingInterval: 'month',
      resources: [],

      resourceOptions: {
        cloud_backup: [
          { key:'STORAGE_TB', label:'Storage', unit:'GiB', billingType:'metered', required:true, enabled:true, amount:0, qty:1024 },
          { key:'DEVICE_COUNT', label:'Devices', unit:'device', billingType:'per_unit', required:true, enabled:true, amount:0, qty:1 },
          { key:'DISK_IMAGE', label:'Disk Image Backup', unit:'machine', billingType:'per_unit', required:false, enabled:false, amount:0, qty:0 },
          { key:'HYPERV_VM', label:'Hyper-V Virtual Machines', unit:'VM', billingType:'per_unit', required:false, enabled:false, amount:0, qty:0 },
          { key:'PROXMOX_VM', label:'Proxmox Virtual Machines', unit:'VM', billingType:'per_unit', required:false, enabled:false, amount:0, qty:0 },
          { key:'VMWARE_VM', label:'VMware Virtual Machines', unit:'VM', billingType:'per_unit', required:false, enabled:false, amount:0, qty:0 },
          { key:'M365_USER', label:'Microsoft 365 Users', unit:'user', billingType:'per_unit', required:false, enabled:false, amount:0, qty:0 }
        ],
        object_storage: [
          { key:'STORAGE_TB', label:'Object Storage', unit:'GiB', billingType:'metered', required:true, enabled:true, amount:0, qty:1024 }
        ],
        custom_service: [
          { key:'GENERIC', label:'Service', unit:'unit', billingType:'per_unit', required:true, enabled:true, amount:0, qty:1 }
        ]
      },

      open(){
        this.step = 1;
        this.planType = '';
        this.planName = '';
        this.planDescription = '';
        this.trialDays = 0;
        this.billingInterval = 'month';
        this.resources = [];
        this.isOpen = true;
        showEl('eb-wizard-panel');
      },

      close(){
        this.isOpen = false;
        hideEl('eb-wizard-panel');
      },

      selectType(type){
        this.planType = type;
        this.resources = JSON.parse(JSON.stringify(this.resourceOptions[type] || []));
        if (type === 'cloud_backup') { this.planName = 'eazyBackup Cloud Backup'; }
        else if (type === 'object_storage') { this.planName = 'e3 Object Storage'; }
        else if (type === 'custom_service') { this.planName = ''; }
        this.step = 2;
      },

      stepLabel(key){
        if (this.planType === 'custom_service') {
          switch(key){
            case 'GENERIC': return 'Service';
            default: return key;
          }
        }
        switch(key){
          case 'STORAGE_TB': return 'Storage';
          case 'DEVICE_COUNT': return 'Devices';
          case 'DISK_IMAGE': return 'Disk Image';
          case 'HYPERV_VM': return 'Hyper-V';
          case 'PROXMOX_VM': return 'Proxmox';
          case 'VMWARE_VM': return 'VMware';
          case 'M365_USER': return 'M365';
          default: return key;
        }
      },

      isCustomService(){ return this.planType === 'custom_service'; },

      nextStep(){
        if (this.step === 2) {
          var hasEnabled = this.resources.some(function(r){ return r.enabled; });
          if (!hasEnabled) { safeToast('Enable at least one resource', 'warning'); return; }
        }
        if (this.step === 3) {
          for (var i = 0; i < this.resources.length; i++) {
            if (this.resources[i].enabled && this.resources[i].amount <= 0) {
              safeToast('Set a price for each enabled resource', 'warning'); return;
            }
          }
        }
        if (this.step < 4) this.step++;
      },

      prevStep(){ if (this.step > 1) this.step--; },

      enabledResources(){
        return this.resources.filter(function(r){ return r.enabled; });
      },

      totalPreview(){
        var total = 0;
        for (var i = 0; i < this.resources.length; i++) {
          var r = this.resources[i];
          if (!r.enabled) continue;
          if (r.billingType === 'metered') {
            total += (parseFloat(r.amount) || 0);
          } else {
            total += (parseFloat(r.amount) || 0) * (parseInt(r.qty) || 1);
          }
        }
        return currency + ' ' + total.toFixed(2) + ' / ' + this.billingInterval;
      },

      async publish(){
        if (!this.planName.trim()) { safeToast('Plan name is required', 'warning'); return; }
        this.isSaving = true;
        try {
          var enabled = this.enabledResources();
          var productIds = [];

          for (var i = 0; i < enabled.length; i++) {
            var r = enabled[i];
            var prodBody = {
              mode: 'draft',
              product: { name: this.planName + ' — ' + r.label },
              base_metric_code: r.key,
              items: [{
                label: r.label,
                billingType: r.billingType,
                metric: r.key,
                unitLabel: r.unit,
                amount: parseFloat(r.amount) || 0,
                interval: this.billingInterval,
                active: true,
                pricingScheme: 'per_unit',
                currency: currency
              }],
              features: []
            };
            var res = await fetch(modulelink + '&a=ph-catalog-product-save', {
              method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(prodBody)
            });
            var out = await res.json();
            if (out.status === 'success' && out.product_id) {
              productIds.push({ productId: out.product_id, priceIds: out.prices || [], resource: r });
            } else {
              safeToast('Failed to create product for ' + r.label, 'error');
              this.isSaving = false;
              return;
            }
          }

          var planBody = new URLSearchParams({ token: token, name: this.planName, description: this.planDescription || '', trial_days: String(this.trialDays || 0) });
          var planRes = await fetch(modulelink + '&a=ph-plan-template-create', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: planBody });
          var planOut = await planRes.json();
          if (planOut.status !== 'success' || !planOut.id) { safeToast('Failed to create plan', 'error'); this.isSaving = false; return; }

          for (var j = 0; j < productIds.length; j++) {
            var p = productIds[j];
            if (p.priceIds.length > 0) {
              var compBody = new URLSearchParams({
                token: token, plan_id: String(planOut.id), price_id: String(p.priceIds[0]),
                default_qty: String(p.resource.qty || 0), overage_mode: 'bill_all'
              });
              await fetch(modulelink + '&a=ph-plan-component-add', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: compBody });
            }
          }

          safeToast('Plan created with ' + productIds.length + ' product(s)', 'success');
          this.close();
          setTimeout(function(){ location.reload(); }, 500);
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        } finally {
          this.isSaving = false;
        }
      }
    };
  };
})();
