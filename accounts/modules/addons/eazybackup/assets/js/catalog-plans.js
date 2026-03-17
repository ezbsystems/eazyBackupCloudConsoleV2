(function(){
  var showEl = function(id){ var el = document.getElementById(id); if(el) el.classList.remove('hidden'); };
  var hideEl = function(id){ var el = document.getElementById(id); if(el) el.classList.add('hidden'); };
  function safeToast(msg,type){ try{ if(window.showToast) window.showToast(msg,type); else alert(msg); }catch(_){ alert(msg); } }

  var priceData = [];
  try {
    var selects = document.querySelectorAll('#eb-plan-panel select');
    selects.forEach(function(sel){
      sel.querySelectorAll('option[value]').forEach(function(opt){
        if (opt.value && opt.textContent) {
          priceData.push({ id: opt.value, label: opt.textContent.trim() });
        }
      });
    });
  } catch(_){}

  var cometAccounts = [];
  try {
    var raw = document.getElementById('eb-comet-accounts-json');
    if (raw) cometAccounts = JSON.parse(raw.textContent || '[]');
  } catch(_){}

  window.planPageFactory = function(opts){
    var modulelink = (opts && opts.modulelink) || 'index.php?m=eazybackup';
    var token = (opts && opts.token) || '';
    return {
      statusFilter: 'all',
      searchQuery: '',
      panelMode: 'create',
      panelOpen: false,
      editPlanId: null,
      isSaving: false,
      _dirty: false,
      planData: { name:'', description:'', billing_interval:'month', currency:'CAD', trial_days:0 },
      editComponents: [],
      assignPlanId: null,
      assignPlanName: '',
      assignData: { tenant_id:'', comet_user_id:'', application_fee_percent:null },
      filteredCometAccounts: [],
      subscriptions: [],
      subsLoading: false,

      matchesPlan(name, status){
        if (this.statusFilter !== 'all' && status !== this.statusFilter) return false;
        if (!this.searchQuery) return true;
        try { return String(name||'').toLowerCase().indexOf(String(this.searchQuery).toLowerCase()) >= 0; } catch(_){ return true; }
      },

      openCreate(){
        this.panelMode = 'create';
        this.editPlanId = null;
        this.planData = { name:'', description:'', billing_interval:'month', currency:'CAD', trial_days:0 };
        this.editComponents = [];
        showEl('eb-plan-panel');
      },

      async openEdit(planId){
        try {
          this.panelMode = 'edit';
          this.editPlanId = planId;
          var res = await fetch(modulelink + '&a=ph-plan-template-get&id=' + encodeURIComponent(planId), { method:'GET', credentials:'include' });
          var out = await res.json();
          if (out.status !== 'success') { safeToast('Failed to load plan', 'error'); return; }
          var p = out.plan || {};
          this.planData = {
            name: p.name || '', description: p.description || '',
            billing_interval: p.billing_interval || 'month', currency: p.currency || 'CAD',
            trial_days: parseInt(p.trial_days) || 0
          };
          this.editComponents = (out.components || []).map(function(c){
            return { id: c.id, price_id: String(c.price_id), default_qty: parseInt(c.default_qty)||0, overage_mode: c.overage_mode||'bill_all' };
          });
          showEl('eb-plan-panel');
        } catch(e) { console.error(e); safeToast('Failed to load plan', 'error'); }
      },

      closePanel(){
        hideEl('eb-plan-panel');
        if (this._dirty) { window.location.reload(); }
      },

      addComponent(){
        this.editComponents.push({ id:0, price_id:'', default_qty:0, overage_mode:'bill_all' });
      },

      removeComponent(i){
        if (i >= 0 && i < this.editComponents.length) this.editComponents.splice(i, 1);
      },

      getPriceName(priceId){
        if (!priceId) return '';
        for (var i = 0; i < priceData.length; i++) {
          if (String(priceData[i].id) === String(priceId)) return priceData[i].label;
        }
        return 'Price #' + priceId;
      },

      formatComponentPrice(comp){
        if (!comp.price_id) return '—';
        var label = this.getPriceName(comp.price_id);
        var match = label.match(/([A-Z]{3})\s+([\d.]+)/);
        if (match) {
          var amt = parseFloat(match[2]) || 0;
          var qty = parseInt(comp.default_qty) || 1;
          return match[1] + ' ' + (amt * (qty || 1)).toFixed(2);
        }
        return '—';
      },

      formatTotalPreview(){
        var total = 0;
        var currency = this.planData.currency || 'CAD';
        for (var i = 0; i < this.editComponents.length; i++) {
          var comp = this.editComponents[i];
          if (!comp.price_id) continue;
          var label = this.getPriceName(comp.price_id);
          var match = label.match(/([\d.]+)/);
          if (match) {
            var amt = parseFloat(match[0]) || 0;
            total += amt * (parseInt(comp.default_qty) || 1);
          }
        }
        return currency + ' ' + total.toFixed(2) + ' / ' + this.planData.billing_interval;
      },

      async savePlan(){
        if (!this.planData.name.trim()) { safeToast('Plan name is required', 'warning'); return; }
        this.isSaving = true;
        try {
          if (this.panelMode === 'create') {
            var body = new URLSearchParams({
              token: token, name: this.planData.name, description: this.planData.description || '',
              trial_days: String(this.planData.trial_days || 0)
            });
            var res = await fetch(modulelink + '&a=ph-plan-template-create', { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body: body });
            var out = await res.json();
            if (out.status === 'success' && out.id) {
              this.editPlanId = out.id;
              this.panelMode = 'edit';
              this._dirty = true;
              await this._saveComponents(out.id);
              safeToast('Plan created', 'success');
            } else { safeToast('Failed to create plan', 'error'); }
          } else {
            var payload = {
              plan_id: this.editPlanId, name: this.planData.name, description: this.planData.description || '',
              trial_days: this.planData.trial_days || 0, billing_interval: this.planData.billing_interval,
              currency: this.planData.currency,
              components: this.editComponents.filter(function(c){ return c.price_id; }).map(function(c){ return { id: c.id||0, price_id: parseInt(c.price_id), default_qty: c.default_qty||0, overage_mode: c.overage_mode||'bill_all' }; })
            };
            var res = await fetch(modulelink + '&a=ph-plan-template-update', { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(payload) });
            var out = await res.json();
            if (out.status === 'success') { this._dirty = true; safeToast('Plan updated', 'success'); }
            else { safeToast('Failed to update plan', 'error'); }
          }
        } catch(e){ console.error(e); safeToast('Network error', 'error'); }
        finally { this.isSaving = false; }
      },

      async _saveComponents(planId){
        for (var i = 0; i < this.editComponents.length; i++) {
          var c = this.editComponents[i];
          if (!c.price_id) continue;
          try {
            var body = new URLSearchParams({
              token: token, plan_id: String(planId), price_id: String(c.price_id),
              default_qty: String(c.default_qty||0), overage_mode: c.overage_mode||'bill_all'
            });
            await fetch(modulelink + '&a=ph-plan-component-add', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          } catch(_){}
        }
      },

      async duplicatePlan(planId){
        try {
          var body = new URLSearchParams({ token: token, plan_id: String(planId) });
          var res = await fetch(modulelink + '&a=ph-plan-template-duplicate', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') { safeToast('Plan duplicated', 'success'); setTimeout(function(){ location.reload(); }, 500); }
          else { safeToast('Failed to duplicate', 'error'); }
        } catch(e){ console.error(e); safeToast('Network error', 'error'); }
      },

      async toggleStatus(planId, newStatus){
        try {
          var body = new URLSearchParams({ token: token, plan_id: String(planId), status: newStatus });
          var res = await fetch(modulelink + '&a=ph-plan-template-toggle', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') { safeToast('Status changed', 'success'); setTimeout(function(){ location.reload(); }, 500); }
          else { safeToast('Failed to update status', 'error'); }
        } catch(e){ console.error(e); safeToast('Network error', 'error'); }
      },

      async deletePlan(planId){
        if (!confirm('Delete this plan template? This cannot be undone.')) return;
        try {
          var body = new URLSearchParams({ token: token, plan_id: String(planId) });
          var res = await fetch(modulelink + '&a=ph-plan-template-delete', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') { safeToast('Plan deleted', 'success'); setTimeout(function(){ location.reload(); }, 500); }
          else if (out.message === 'has_active_subscriptions') { safeToast('Cannot delete: ' + out.count + ' active subscription(s)', 'warning'); }
          else { safeToast('Failed to delete', 'error'); }
        } catch(e){ console.error(e); safeToast('Network error', 'error'); }
      },

      openAssign(planId, planName){
        this.assignPlanId = planId;
        this.assignPlanName = planName || 'Plan #' + planId;
        this.assignData = { tenant_id:'', comet_user_id:'', application_fee_percent:null };
        this.filteredCometAccounts = [];
        showEl('eb-assign-plan-modal');
      },

      closeAssign(){ hideEl('eb-assign-plan-modal'); if(this._dirty) location.reload(); },

      onTenantChange(){
        var tenantPublicId = String(this.assignData.tenant_id || '');
        this.filteredCometAccounts = cometAccounts.filter(function(a){ return String(a.tenant_public_id || '') === tenantPublicId; });
        this.assignData.comet_user_id = this.filteredCometAccounts.length === 1 ? this.filteredCometAccounts[0].comet_username : '';
      },

      async submitAssign(){
        if (!this.assignData.tenant_id || !this.assignData.comet_user_id) { safeToast('Select a customer and eazyBackup user', 'warning'); return; }
        this.isSaving = true;
        try {
          var body = new URLSearchParams({
            token: token, plan_id: String(this.assignPlanId),
            tenant_id: String(this.assignData.tenant_id), comet_user_id: this.assignData.comet_user_id
          });
          if (this.assignData.application_fee_percent !== null && this.assignData.application_fee_percent !== '') {
            body.append('application_fee_percent', String(this.assignData.application_fee_percent));
          }
          var res = await fetch(modulelink + '&a=ph-plan-assign', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') { this._dirty = true; safeToast('Subscription created', 'success'); hideEl('eb-assign-plan-modal'); setTimeout(function(){ location.reload(); }, 500); }
          else { safeToast('Failed: ' + (out.message||'error'), 'error'); }
        } catch(e){ console.error(e); safeToast('Network error', 'error'); }
        finally { this.isSaving = false; }
      },

      async openSubs(planId){
        this.subsLoading = true;
        this.subscriptions = [];
        showEl('eb-subs-modal');
        try {
          var res = await fetch(modulelink + '&a=ph-plan-subscriptions-list&plan_id=' + encodeURIComponent(planId), { method:'GET', credentials:'include' });
          var out = await res.json();
          this.subscriptions = out.subscriptions || [];
        } catch(e){ console.error(e); }
        finally { this.subsLoading = false; }
      },

      closeSubs(){ hideEl('eb-subs-modal'); },

      async cancelSubscription(instanceId){
        if (!confirm('Cancel this subscription? The customer will lose access at the end of the billing period.')) return;
        try {
          var body = new URLSearchParams({ token: token, instance_id: String(instanceId) });
          var res = await fetch(modulelink + '&a=ph-plan-subscription-cancel', { method:'POST', credentials:'include', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body });
          var out = await res.json();
          if (out.status === 'success') { safeToast('Subscription cancelled', 'success'); this._dirty = true; this.openSubs(this.assignPlanId || 0); }
          else { safeToast('Failed to cancel', 'error'); }
        } catch(e){ console.error(e); safeToast('Network error', 'error'); }
      }
    };
  };
})();
