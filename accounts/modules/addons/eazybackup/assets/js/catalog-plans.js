(function(){
  var showEl = function(id){ var el = document.getElementById(id); if(el) el.classList.remove('hidden'); };
  var hideEl = function(id){ var el = document.getElementById(id); if(el) el.classList.add('hidden'); };
  function safeToast(msg,type){ try{ if(window.showToast) window.showToast(msg,type); else alert(msg); }catch(_){ alert(msg); } }

  function parseJsonScript(id){
    try {
      var el = document.getElementById(id);
      if (!el) return [];
      var raw = el.textContent || '';
      return raw ? JSON.parse(raw) : [];
    } catch(_) {
      return [];
    }
  }

  function normalizeCurrency(value){
    value = String(value || 'CAD').trim().toUpperCase();
    return /^[A-Z]{3}$/.test(value) ? value : 'CAD';
  }

  function normalizeInterval(value){
    value = String(value || 'month').trim().toLowerCase();
    return value === 'year' ? 'year' : 'month';
  }

  function normalizeStatus(value){
    value = String(value || 'draft').trim().toLowerCase();
    return ['draft', 'active', 'archived'].indexOf(value) >= 0 ? value : 'draft';
  }

  function formatMoneyCents(cents, currency){
    return normalizeCurrency(currency) + ' ' + ((Number(cents || 0) / 100).toFixed(2));
  }

  function clone(obj){
    return JSON.parse(JSON.stringify(obj));
  }

  function metricLabel(metric){
    switch(String(metric || 'GENERIC')){
      case 'STORAGE_TB': return 'Storage';
      case 'DEVICE_COUNT': return 'Devices';
      case 'DISK_IMAGE': return 'Disk Image';
      case 'HYPERV_VM': return 'Hyper-V';
      case 'PROXMOX_VM': return 'Proxmox';
      case 'VMWARE_VM': return 'VMware';
      case 'M365_USER': return 'Microsoft 365';
      default: return 'Service';
    }
  }

  function metricUnit(metric, fallback){
    if (fallback) return fallback;
    switch(String(metric || 'GENERIC')){
      case 'STORAGE_TB': return 'GiB';
      case 'DEVICE_COUNT': return 'device';
      case 'DISK_IMAGE': return 'machine';
      case 'HYPERV_VM':
      case 'PROXMOX_VM':
      case 'VMWARE_VM': return 'VM';
      case 'M365_USER': return 'user';
      default: return 'unit';
    }
  }

  function billingLabel(type){
    if (type === 'metered') return 'Metered';
    if (type === 'one_time') return 'One-time';
    return 'Per-unit';
  }

  var catalogProducts = parseJsonScript('eb-plan-catalog-json');
  var cometAccounts = parseJsonScript('eb-comet-accounts-json');

  window.planPageFactory = function(opts){
    var modulelink = (opts && opts.modulelink) || 'index.php?m=eazybackup';
    var token = (opts && opts.token) || '';
    return {
      step: 1,
      panelMode: 'create',
      editPlanId: null,
      isSaving: false,
      _dirty: false,
      mobileSummaryOpen: false,
      catalogSearch: '',
      catalogTypeFilter: 'all',
      catalogProducts: clone(catalogProducts),
      planData: {
        name: '',
        description: '',
        billing_interval: 'month',
        currency: 'CAD',
        trial_days: 0,
        status: 'draft'
      },
      editComponents: [],
      assignPlanId: null,
      assignPlanName: '',
      assignData: { tenant_id:'', comet_user_id:'', application_fee_percent:null },
      filteredCometAccounts: [],
      subscriptions: [],
      subsLoading: false,
      subsPlanId: null,
      subscriptionEditor: {
        open: false,
        loading: false,
        saving: false,
        previewLoading: false,
        instanceId: null,
        subscription: null,
        items: [],
        baseItems: [],
        availablePlans: [],
        swapPlanId: '',
        preview: null,
        error: ''
      },

      resetBuilder(){
        this.step = 1;
        this.panelMode = 'create';
        this.editPlanId = null;
        this.mobileSummaryOpen = false;
        this.planData = {
          name: '',
          description: '',
          billing_interval: 'month',
          currency: 'CAD',
          trial_days: 0,
          status: 'draft'
        };
        this.editComponents = [];
        this.catalogSearch = '';
        this.catalogTypeFilter = 'all';
      },

      currentPlanStatus(){
        return normalizeStatus(this.planData.status || 'draft');
      },

      currentPlanStatusLabel(){
        var status = this.currentPlanStatus();
        if (status === 'active') return 'Published';
        if (status === 'archived') return 'Archived';
        return 'Draft';
      },

      currentPlanStatusClass(){
        var status = this.currentPlanStatus();
        if (status === 'active') return 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/20';
        if (status === 'archived') return 'bg-slate-500/15 text-slate-400 ring-1 ring-white/10';
        return 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-400/20';
      },

      openCreate(){
        this.resetBuilder();
        showEl('eb-plan-panel');
      },

      async openEdit(planId){
        try {
          this.resetBuilder();
          this.panelMode = 'edit';
          this.editPlanId = planId;
          var res = await fetch(modulelink + '&a=ph-plan-template-get&id=' + encodeURIComponent(planId), { method:'GET', credentials:'include' });
          var out = await res.json();
          if (out.status !== 'success') {
            safeToast('Failed to load plan', 'error');
            return;
          }
          var p = out.plan || {};
          this.planData = {
            name: p.name || '',
            description: p.description || '',
            billing_interval: normalizeInterval(p.billing_interval || 'month'),
            currency: normalizeCurrency(p.currency || 'CAD'),
            trial_days: parseInt(p.trial_days, 10) || 0,
            status: normalizeStatus(p.status || (p.active ? 'active' : 'draft'))
          };
          this.editComponents = (out.components || []).map(function(c){
            return {
              id: c.id || 0,
              price_id: String(c.price_id || ''),
              product_id: c.product_id ? parseInt(c.product_id, 10) : 0,
              product_name: c.product_name || '',
              product_description: c.product_description || '',
              price_name: c.price_name || '',
              metric_code: c.price_metric || c.metric_code || c.product_base_metric || 'GENERIC',
              billing_type: c.price_kind === 'metered' ? 'metered' : (c.price_kind === 'one_time' ? 'one_time' : 'per_unit'),
              kind: c.price_kind || 'recurring',
              currency: normalizeCurrency(c.price_currency || 'CAD'),
              interval: normalizeInterval(c.price_interval || 'month'),
              unit_amount: parseInt(c.price_amount, 10) || 0,
              unit_label: c.price_unit_label || '',
              default_qty: parseInt(c.default_qty, 10) || 0,
              overage_mode: c.overage_mode || 'bill_all',
              active: !!c.price_active,
              stripe_price_id: c.stripe_price_id || '',
              is_legacy_attached: !(c.price_active)
            };
          });
          this.editComponents.forEach(this.syncComponentContext.bind(this));
          showEl('eb-plan-panel');
        } catch(e) {
          console.error(e);
          safeToast('Failed to load plan', 'error');
        }
      },

      closePanel(){
        hideEl('eb-plan-panel');
        this.mobileSummaryOpen = false;
        if (this._dirty) {
          window.location.reload();
        }
      },

      nextStep(){
        if (this.step === 1) {
          var basicsError = this.validateBasics();
          if (basicsError) {
            safeToast(basicsError, 'warning');
            return;
          }
        }
        if (this.step === 2 && this.editComponents.length === 0) {
          safeToast('Select at least one published recurring price to continue.', 'warning');
          return;
        }
        if (this.step === 3) {
          var componentsError = this.validateComponentInputs();
          if (componentsError) {
            safeToast(componentsError, 'warning');
            return;
          }
        }
        if (this.step < 4) this.step += 1;
      },

      prevStep(){
        if (this.step > 1) this.step -= 1;
      },

      validateBasics(){
        if (!String(this.planData.name || '').trim()) return 'Plan name is required.';
        if ((parseInt(this.planData.trial_days, 10) || 0) < 0) return 'Trial days cannot be negative.';
        this.planData.currency = normalizeCurrency(this.planData.currency || 'CAD');
        this.planData.billing_interval = normalizeInterval(this.planData.billing_interval || 'month');
        this.planData.status = normalizeStatus(this.planData.status || 'draft');
        return '';
      },

      validateComponentInputs(){
        for (var i = 0; i < this.editComponents.length; i++) {
          var comp = this.editComponents[i];
          if (!comp.price_id) return 'Each component must reference a valid price.';
          if ((parseInt(comp.default_qty, 10) || 0) < 0) return 'Included quantity cannot be negative.';
          if (['bill_all', 'cap_at_default'].indexOf(String(comp.overage_mode || 'bill_all')) < 0) return 'Each component needs a valid overage rule.';
        }
        return '';
      },

      validateForPublish(){
        if (this.editComponents.length === 0) return 'Add at least one recurring component before publishing.';
        for (var i = 0; i < this.editComponents.length; i++) {
          var comp = this.editComponents[i];
          if (String(comp.kind || '') === 'one_time' || String(comp.billing_type || '') === 'one_time') {
            return 'One-time prices cannot be used in plan templates.';
          }
          if (normalizeCurrency(comp.currency || 'CAD') !== normalizeCurrency(this.planData.currency || 'CAD')) {
            return 'All recurring components must use the same currency.';
          }
          if (normalizeInterval(comp.interval || 'month') !== normalizeInterval(this.planData.billing_interval || 'month')) {
            return 'All recurring components must use the same billing interval.';
          }
          if (!comp.active || !String(comp.stripe_price_id || '').trim()) {
            return 'Only active published catalog prices can be used when publishing.';
          }
        }
        return '';
      },

      findCatalogProduct(productId){
        productId = parseInt(productId, 10) || 0;
        for (var i = 0; i < this.catalogProducts.length; i++) {
          if (parseInt(this.catalogProducts[i].id, 10) === productId) return this.catalogProducts[i];
        }
        return null;
      },

      findCatalogPrice(priceId){
        priceId = String(priceId || '');
        for (var i = 0; i < this.catalogProducts.length; i++) {
          var product = this.catalogProducts[i];
          var prices = Array.isArray(product.prices) ? product.prices : [];
          for (var j = 0; j < prices.length; j++) {
            if (String(prices[j].id || '') === priceId) {
              return { product: product, price: prices[j] };
            }
          }
        }
        return null;
      },

      syncComponentContext(comp){
        if (!comp || !comp.price_id) return comp;
        var found = this.findCatalogPrice(comp.price_id);
        if (found) {
          comp.product_id = parseInt(found.product.id, 10) || comp.product_id || 0;
          comp.product_name = found.product.name || comp.product_name || '';
          comp.product_description = found.product.description || comp.product_description || '';
          comp.price_name = found.price.name || comp.price_name || '';
          comp.metric_code = found.price.metric_code || comp.metric_code || found.product.base_metric_code || 'GENERIC';
          comp.billing_type = found.price.billing_type || comp.billing_type || 'per_unit';
          comp.kind = found.price.kind || comp.kind || 'recurring';
          comp.currency = normalizeCurrency(found.price.currency || comp.currency || this.planData.currency || 'CAD');
          comp.interval = normalizeInterval(found.price.interval || comp.interval || this.planData.billing_interval || 'month');
          comp.unit_amount = parseInt(found.price.unit_amount, 10) || comp.unit_amount || 0;
          comp.unit_label = found.price.unit_label || comp.unit_label || metricUnit(comp.metric_code, '');
          comp.active = !!found.price.active;
          comp.stripe_price_id = found.price.stripe_price_id || comp.stripe_price_id || '';
          if (comp.is_legacy_attached && found.price.active) {
            comp.is_legacy_attached = false;
          }
        } else {
          comp.metric_code = comp.metric_code || 'GENERIC';
          comp.currency = normalizeCurrency(comp.currency || this.planData.currency || 'CAD');
          comp.interval = normalizeInterval(comp.interval || this.planData.billing_interval || 'month');
          comp.unit_label = comp.unit_label || metricUnit(comp.metric_code, '');
        }
        return comp;
      },

      selectedPriceIds(){
        return this.editComponents.map(function(comp){ return String(comp.price_id || ''); });
      },

      isSelectedPrice(priceId){
        return this.selectedPriceIds().indexOf(String(priceId || '')) >= 0;
      },

      isVisibleArchivedPrice(priceId){
        priceId = String(priceId || '');
        for (var i = 0; i < this.editComponents.length; i++) {
          var comp = this.editComponents[i];
          if (String(comp.price_id || '') === priceId && comp.is_legacy_attached) return true;
        }
        return false;
      },

      filteredCatalogProducts(){
        var query = String(this.catalogSearch || '').trim().toLowerCase();
        var typeFilter = String(this.catalogTypeFilter || 'all');
        var self = this;
        return this.catalogProducts.filter(function(product){
          var metric = String(product.base_metric_code || 'GENERIC');
          if (typeFilter !== 'all' && metric !== typeFilter) return false;
          var matchesText = !query || (String(product.name || '').toLowerCase().indexOf(query) >= 0) || (String(product.description || '').toLowerCase().indexOf(query) >= 0);
          if (!matchesText) {
            var prices = Array.isArray(product.prices) ? product.prices : [];
            matchesText = prices.some(function(price){
              return String(price.name || '').toLowerCase().indexOf(query) >= 0;
            });
          }
          if (!matchesText) return false;
          return self.visiblePricesForProduct(product).length > 0;
        });
      },

      visiblePricesForProduct(product){
        var self = this;
        var planCurrency = normalizeCurrency(this.planData.currency || 'CAD');
        var planInterval = normalizeInterval(this.planData.billing_interval || 'month');
        var prices = Array.isArray(product.prices) ? product.prices : [];
        return prices.filter(function(price){
          var kind = String(price.kind || 'recurring');
          if (kind === 'one_time') return false;
          var matchesPlan = normalizeCurrency(price.currency || 'CAD') === planCurrency && normalizeInterval(price.interval || 'month') === planInterval;
          var selectedArchived = self.isVisibleArchivedPrice(price.id);
          if (!matchesPlan && !selectedArchived) return false;
          if ((!price.active || !String(price.stripe_price_id || '').trim()) && !selectedArchived) return false;
          return true;
        });
      },

      addPriceToPlan(productId, priceId){
        if (this.isSelectedPrice(priceId)) {
          safeToast('That price is already in this plan.', 'info');
          return;
        }
        var found = this.findCatalogPrice(priceId);
        if (!found) {
          safeToast('Unable to load that catalog price.', 'error');
          return;
        }
        var price = found.price;
        if (String(price.kind || '') === 'one_time') {
          safeToast('One-time prices are not supported in plan templates.', 'warning');
          return;
        }
        if (!price.active) {
          safeToast('Archived prices can only remain on existing legacy plans.', 'warning');
          return;
        }
        if (!String(price.stripe_price_id || '').trim()) {
          safeToast('Only published catalog prices can be added to a plan.', 'warning');
          return;
        }
        if (normalizeCurrency(price.currency || 'CAD') !== normalizeCurrency(this.planData.currency || 'CAD')) {
          safeToast('That price uses a different currency.', 'warning');
          return;
        }
        if (normalizeInterval(price.interval || 'month') !== normalizeInterval(this.planData.billing_interval || 'month')) {
          safeToast('That price uses a different billing interval.', 'warning');
          return;
        }
        var defaultQty = String(price.billing_type || '') === 'metered' ? 1024 : 1;
        var component = {
          id: 0,
          price_id: String(price.id || ''),
          product_id: parseInt(productId, 10) || 0,
          product_name: found.product.name || '',
          product_description: found.product.description || '',
          price_name: price.name || '',
          metric_code: price.metric_code || found.product.base_metric_code || 'GENERIC',
          billing_type: price.billing_type || 'per_unit',
          kind: price.kind || 'recurring',
          currency: normalizeCurrency(price.currency || this.planData.currency || 'CAD'),
          interval: normalizeInterval(price.interval || this.planData.billing_interval || 'month'),
          unit_amount: parseInt(price.unit_amount, 10) || 0,
          unit_label: price.unit_label || metricUnit(price.metric_code || found.product.base_metric_code || 'GENERIC', ''),
          default_qty: defaultQty,
          overage_mode: 'bill_all',
          active: !!price.active,
          stripe_price_id: price.stripe_price_id || '',
          is_legacy_attached: false
        };
        this.editComponents.push(component);
        safeToast('Price added to plan.', 'success');
      },

      removeComponent(index){
        if (index >= 0 && index < this.editComponents.length) {
          this.editComponents.splice(index, 1);
        }
      },

      productMetricLabel(product){
        return metricLabel(product.base_metric_code || 'GENERIC');
      },

      metricLabel(metric){
        return metricLabel(metric);
      },

      billingLabel(type){
        return billingLabel(type);
      },

      formatMoneyCents(cents, currency){
        return formatMoneyCents(cents, currency);
      },

      priceBadgeClass(price){
        if (String(price.billing_type || '') === 'metered') return 'bg-sky-500/15 text-sky-300';
        return 'bg-slate-700 text-slate-200';
      },

      componentBadgeClass(comp){
        if (String(comp.billing_type || '') === 'metered') return 'bg-sky-500/15 text-sky-300';
        return 'bg-slate-700 text-slate-200';
      },

      componentPriceText(comp){
        var suffix = normalizeInterval(comp.interval || this.planData.billing_interval || 'month');
        var unit = comp.unit_label || metricUnit(comp.metric_code, '');
        if (String(comp.billing_type || '') === 'metered') {
          return formatMoneyCents(comp.unit_amount || 0, comp.currency || this.planData.currency) + ' / ' + unit;
        }
        return formatMoneyCents(comp.unit_amount || 0, comp.currency || this.planData.currency) + ' / ' + unit + ' / ' + suffix;
      },

      includedLabel(comp){
        var qty = parseInt(comp.default_qty, 10) || 0;
        var unit = comp.unit_label || metricUnit(comp.metric_code, '');
        return qty + ' ' + unit;
      },

      overageLabel(mode){
        return String(mode || 'bill_all') === 'cap_at_default'
          ? 'Do not bill usage above included amount'
          : 'Charge for all usage above included amount';
      },

      overageExample(comp){
        var qty = parseInt(comp.default_qty, 10) || 0;
        var unit = comp.unit_label || metricUnit(comp.metric_code, '');
        if (String(comp.overage_mode || 'bill_all') === 'cap_at_default') {
          return 'Includes ' + qty + ' ' + unit + '. Usage above that limit is not billed on this plan.';
        }
        return 'Includes ' + qty + ' ' + unit + '. Usage above that amount is billed at the selected catalog price.';
      },

      legacyWarning(comp){
        if (!comp.is_legacy_attached) return '';
        return 'This price is archived in the catalog. It remains attached for legacy editing but cannot be newly added or republished until replaced.';
      },

      recurringBaseTotalCents(){
        var total = 0;
        for (var i = 0; i < this.editComponents.length; i++) {
          var comp = this.editComponents[i];
          if (String(comp.billing_type || '') === 'metered') continue;
          total += (parseInt(comp.unit_amount, 10) || 0) * (parseInt(comp.default_qty, 10) || 0);
        }
        return total;
      },

      hasMeteredComponents(){
        return this.editComponents.some(function(comp){ return String(comp.billing_type || '') === 'metered'; });
      },

      reviewRecurringSummary(){
        return formatMoneyCents(this.recurringBaseTotalCents(), this.planData.currency || 'CAD') + ' / ' + normalizeInterval(this.planData.billing_interval || 'month');
      },

      componentSortKey(comp){
        return [String(comp.product_name || ''), String(comp.price_name || ''), String(comp.price_id || '')].join('|');
      },

      sortedComponents(){
        return clone(this.editComponents).sort(function(left, right){
          var a = [String(left.product_name || ''), String(left.price_name || ''), String(left.price_id || '')].join('|').toLowerCase();
          var b = [String(right.product_name || ''), String(right.price_name || ''), String(right.price_id || '')].join('|').toLowerCase();
          if (a < b) return -1;
          if (a > b) return 1;
          return 0;
        });
      },

      createProductUrl(){
        return modulelink + '&a=ph-catalog-products';
      },

      describeSaveError(out){
        if (!out) return 'Save failed.';
        if (out.detail) return out.detail;
        switch(String(out.message || '')){
          case 'currency_mismatch': return 'All recurring components must use the same currency.';
          case 'interval_mismatch': return 'All recurring components must use the same billing interval.';
          case 'one_time_not_supported': return 'One-time prices are not supported in plan templates.';
          case 'archived_price': return 'Archived prices cannot be used when publishing a plan.';
          case 'draft_price': return 'Only published catalog prices can be used when publishing a plan.';
          case 'no_components': return 'Add at least one recurring component before publishing.';
          case 'plan_not_active': return 'Only published plans can be assigned to customers.';
          default: return String(out.message || 'Save failed.');
        }
      },

      async ensureCreatedPlan(){
        if (this.editPlanId) return this.editPlanId;
        var body = new URLSearchParams({
          token: token,
          name: this.planData.name,
          description: this.planData.description || '',
          trial_days: String(this.planData.trial_days || 0),
          billing_interval: this.planData.billing_interval || 'month',
          currency: this.planData.currency || 'CAD',
          status: 'draft'
        });
        var res = await fetch(modulelink + '&a=ph-plan-template-create', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body
        });
        var out = await res.json();
        if (out.status !== 'success' || !out.id) {
          throw new Error(this.describeSaveError(out));
        }
        this.editPlanId = out.id;
        this.panelMode = 'edit';
        return this.editPlanId;
      },

      buildUpdatePayload(targetStatus){
        return {
          plan_id: this.editPlanId,
          name: this.planData.name,
          description: this.planData.description || '',
          trial_days: parseInt(this.planData.trial_days, 10) || 0,
          billing_interval: this.planData.billing_interval || 'month',
          currency: this.planData.currency || 'CAD',
          status: targetStatus,
          components: this.editComponents
            .filter(function(comp){ return comp && comp.price_id; })
            .map(function(comp){
              return {
                id: comp.id || 0,
                price_id: parseInt(comp.price_id, 10),
                default_qty: parseInt(comp.default_qty, 10) || 0,
                overage_mode: comp.overage_mode || 'bill_all'
              };
            })
        };
      },

      async savePlan(targetStatus){
        targetStatus = normalizeStatus(targetStatus || 'draft');
        var basicsError = this.validateBasics();
        if (basicsError) {
          safeToast(basicsError, 'warning');
          this.step = 1;
          return;
        }
        var componentInputError = this.validateComponentInputs();
        if (componentInputError) {
          safeToast(componentInputError, 'warning');
          this.step = 3;
          return;
        }
        if (targetStatus === 'active') {
          var publishError = this.validateForPublish();
          if (publishError) {
            safeToast(publishError, 'warning');
            this.step = 3;
            return;
          }
        }
        this.isSaving = true;
        try {
          await this.ensureCreatedPlan();
          var payload = this.buildUpdatePayload(targetStatus);
          var res = await fetch(modulelink + '&a=ph-plan-template-update', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          var out = await res.json();
          if (out.status !== 'success') {
            safeToast(this.describeSaveError(out), 'error');
            return;
          }
          this.planData.status = targetStatus;
          this._dirty = true;
          safeToast(targetStatus === 'active' ? 'Plan published.' : (targetStatus === 'archived' ? 'Plan archived.' : 'Draft saved.'), 'success');
        } catch (e) {
          console.error(e);
          safeToast(e && e.message ? e.message : 'Network error', 'error');
        } finally {
          this.isSaving = false;
        }
      },

      async duplicatePlan(planId){
        try {
          var body = new URLSearchParams({ token: token, plan_id: String(planId) });
          var res = await fetch(modulelink + '&a=ph-plan-template-duplicate', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') {
            safeToast('Draft copy created.', 'success');
            setTimeout(function(){ location.reload(); }, 500);
          } else {
            safeToast(this.describeSaveError(out), 'error');
          }
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        }
      },

      async toggleStatus(planId, newStatus){
        try {
          var body = new URLSearchParams({ token: token, plan_id: String(planId), status: newStatus });
          var res = await fetch(modulelink + '&a=ph-plan-template-toggle', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') {
            safeToast(newStatus === 'active' ? 'Plan published.' : (newStatus === 'archived' ? 'Plan archived.' : 'Plan moved to draft.'), 'success');
            setTimeout(function(){ location.reload(); }, 500);
          } else {
            safeToast(this.describeSaveError(out), 'error');
          }
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        }
      },

      async deletePlan(planId){
        if (!confirm('Delete this plan template? This cannot be undone.')) return;
        try {
          var body = new URLSearchParams({ token: token, plan_id: String(planId) });
          var res = await fetch(modulelink + '&a=ph-plan-template-delete', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') {
            safeToast('Plan deleted.', 'success');
            setTimeout(function(){ location.reload(); }, 500);
          } else if (out.message === 'has_active_subscriptions') {
            safeToast('Cannot delete: ' + out.count + ' active subscription(s).', 'warning');
          } else {
            safeToast(this.describeSaveError(out), 'error');
          }
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        }
      },

      openAssign(planId, planName, planStatus){
        if (normalizeStatus(planStatus || 'draft') !== 'active') {
          safeToast('Only published plans can be assigned to customers.', 'warning');
          return;
        }
        this.assignPlanId = planId;
        this.assignPlanName = planName || 'Plan #' + planId;
        this.assignData = { tenant_id:'', comet_user_id:'', application_fee_percent:null };
        this.filteredCometAccounts = [];
        showEl('eb-assign-plan-modal');
      },

      closeAssign(){
        hideEl('eb-assign-plan-modal');
        if (this._dirty) location.reload();
      },

      onTenantChange(){
        var tenantPublicId = String(this.assignData.tenant_id || '');
        this.filteredCometAccounts = cometAccounts.filter(function(a){
          return String(a.tenant_public_id || '') === tenantPublicId;
        });
        this.assignData.comet_user_id = this.filteredCometAccounts.length === 1 ? this.filteredCometAccounts[0].comet_username : '';
      },

      async submitAssign(){
        if (!this.assignData.tenant_id || !this.assignData.comet_user_id) {
          safeToast('Select a customer and eazyBackup user.', 'warning');
          return;
        }
        this.isSaving = true;
        try {
          var body = new URLSearchParams({
            token: token,
            plan_id: String(this.assignPlanId),
            tenant_id: String(this.assignData.tenant_id),
            comet_user_id: this.assignData.comet_user_id
          });
          if (this.assignData.application_fee_percent !== null && this.assignData.application_fee_percent !== '') {
            body.append('application_fee_percent', String(this.assignData.application_fee_percent));
          }
          var res = await fetch(modulelink + '&a=ph-plan-assign', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') {
            this._dirty = true;
            safeToast('Subscription created.', 'success');
            hideEl('eb-assign-plan-modal');
            setTimeout(function(){ location.reload(); }, 500);
          } else {
            safeToast(this.describeSaveError(out), 'error');
          }
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        } finally {
          this.isSaving = false;
        }
      },

      async openSubs(planId){
        this.subsPlanId = planId;
        this.subscriptionEditor.open = false;
        this.subsLoading = true;
        this.subscriptions = [];
        showEl('eb-subs-modal');
        try {
          var res = await fetch(modulelink + '&a=ph-plan-subscriptions-list&plan_id=' + encodeURIComponent(planId), { method:'GET', credentials:'include' });
          var out = await res.json();
          this.subscriptions = out.subscriptions || [];
        } catch(e){
          console.error(e);
        } finally {
          this.subsLoading = false;
        }
      },

      closeSubs(){
        this.subscriptionEditor.open = false;
        hideEl('eb-subs-modal');
      },

      resetSubscriptionEditor(){
        this.subscriptionEditor = {
          open: false,
          loading: false,
          saving: false,
          previewLoading: false,
          instanceId: null,
          subscription: null,
          items: [],
          baseItems: [],
          availablePlans: [],
          swapPlanId: '',
          preview: null,
          error: ''
        };
      },

      normalizeEditorItem(raw){
        return {
          plan_component_id: parseInt(raw.plan_component_id, 10) || 0,
          plan_instance_item_id: parseInt(raw.plan_instance_item_id, 10) || 0,
          subscription_item_id: raw.subscription_item_id || '',
          price_id: parseInt(raw.price_id, 10) || 0,
          stripe_price_id: raw.stripe_price_id || '',
          price_name: raw.price_name || '',
          metric_code: raw.metric_code || 'GENERIC',
          kind: raw.kind || 'recurring',
          currency: normalizeCurrency(raw.currency || 'CAD'),
          interval: normalizeInterval(raw.interval || 'month'),
          unit_label: raw.unit_label || metricUnit(raw.metric_code || 'GENERIC', ''),
          unit_amount: parseInt(raw.unit_amount, 10) || 0,
          default_qty: parseInt(raw.default_qty, 10) || 0,
          quantity: parseInt(raw.quantity, 10) || 0,
          editable_quantity: !!raw.editable_quantity,
          removable: raw.removable !== false,
          remove: !!raw.remove
        };
      },

      async openSubscriptionEditor(instanceId){
        this.resetSubscriptionEditor();
        this.subscriptionEditor.open = true;
        this.subscriptionEditor.loading = true;
        try {
          var res = await fetch(modulelink + '&a=ph-plan-subscription-detail&instance_id=' + encodeURIComponent(instanceId), { method:'GET', credentials:'include' });
          var out = await res.json();
          if (out.status !== 'success') {
            this.subscriptionEditor.error = out.message || 'Failed to load subscription.';
            return;
          }
          this.subscriptionEditor.instanceId = instanceId;
          this.subscriptionEditor.subscription = out.subscription || null;
          this.subscriptionEditor.availablePlans = Array.isArray(out.available_plans) ? out.available_plans : [];
          this.subscriptionEditor.items = (out.items || []).map(this.normalizeEditorItem.bind(this));
          this.subscriptionEditor.baseItems = clone(this.subscriptionEditor.items);
        } catch (e) {
          console.error(e);
          this.subscriptionEditor.error = 'Failed to load subscription.';
        } finally {
          this.subscriptionEditor.loading = false;
        }
      },

      applySwapPlan(planId){
        planId = String(planId || '');
        this.subscriptionEditor.swapPlanId = planId;
        this.subscriptionEditor.preview = null;
        if (!planId) {
          this.subscriptionEditor.items = clone(this.subscriptionEditor.baseItems);
          return;
        }
        var targetPlan = this.subscriptionEditor.availablePlans.find(function(plan){
          return String(plan.id || '') === planId;
        });
        if (!targetPlan) return;
        this.subscriptionEditor.items = (targetPlan.components || []).map(this.normalizeEditorItem.bind(this));
      },

      async previewSubscriptionChanges(){
        if (!this.subscriptionEditor.instanceId) return;
        this.subscriptionEditor.previewLoading = true;
        this.subscriptionEditor.preview = null;
        this.subscriptionEditor.error = '';
        try {
          var res = await fetch(modulelink + '&a=ph-plan-subscription-preview', {
            method:'POST',
            credentials:'include',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({
              token: token,
              instance_id: this.subscriptionEditor.instanceId,
              swap_plan_id: this.subscriptionEditor.swapPlanId || '',
              items: this.subscriptionEditor.items
            })
          });
          var out = await res.json();
          if (out.status !== 'success') {
            this.subscriptionEditor.error = out.message || 'Preview failed.';
            return;
          }
          this.subscriptionEditor.preview = out.preview || null;
        } catch (e) {
          console.error(e);
          this.subscriptionEditor.error = 'Preview failed.';
        } finally {
          this.subscriptionEditor.previewLoading = false;
        }
      },

      async saveSubscriptionChanges(){
        if (!this.subscriptionEditor.instanceId) return;
        this.subscriptionEditor.saving = true;
        this.subscriptionEditor.error = '';
        try {
          var res = await fetch(modulelink + '&a=ph-plan-subscription-update', {
            method:'POST',
            credentials:'include',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({
              token: token,
              instance_id: this.subscriptionEditor.instanceId,
              swap_plan_id: this.subscriptionEditor.swapPlanId || '',
              items: this.subscriptionEditor.items
            })
          });
          var out = await res.json();
          if (out.status !== 'success') {
            this.subscriptionEditor.error = out.message || 'Update failed.';
            return;
          }
          safeToast('Subscription updated.', 'success');
          this._dirty = true;
          await this.openSubs(this.subsPlanId || 0);
        } catch (e) {
          console.error(e);
          this.subscriptionEditor.error = 'Update failed.';
        } finally {
          this.subscriptionEditor.saving = false;
        }
      },

      async pauseSubscription(instanceId){
        try {
          var body = new URLSearchParams({ token: token, instance_id: String(instanceId) });
          var res = await fetch(modulelink + '&a=ph-plan-subscription-pause', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') {
            safeToast('Subscription paused.', 'success');
            this._dirty = true;
            this.openSubs(this.subsPlanId || 0);
          } else {
            safeToast(out.message || 'Failed to pause subscription.', 'error');
          }
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        }
      },

      async resumeSubscription(instanceId){
        try {
          var body = new URLSearchParams({ token: token, instance_id: String(instanceId) });
          var res = await fetch(modulelink + '&a=ph-plan-subscription-resume', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') {
            safeToast('Subscription resumed.', 'success');
            this._dirty = true;
            this.openSubs(this.subsPlanId || 0);
          } else {
            safeToast(out.message || 'Failed to resume subscription.', 'error');
          }
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        }
      },

      async cancelSubscription(instanceId){
        if (!confirm('Cancel this subscription? The customer will lose access at the end of the billing period.')) return;
        try {
          var body = new URLSearchParams({ token: token, instance_id: String(instanceId) });
          var res = await fetch(modulelink + '&a=ph-plan-subscription-cancel', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') {
            safeToast('Subscription cancelled.', 'success');
            this._dirty = true;
            this.openSubs(this.subsPlanId || 0);
          } else {
            safeToast('Failed to cancel subscription.', 'error');
          }
        } catch(e){
          console.error(e);
          safeToast('Network error', 'error');
        }
      }
    };
  };
})();
