{**
 * eazyBackup Protected Item delete confirmation modal.
 * Opened via window event 'pi-delete:open' detail: { itemId, name, rules: [{name}] }.
 *}
{literal}
<div x-data="{
  open: false,
  itemId: '',
  name: '',
  rules: [],
  confirmText: '',
  submitting: false,
  init() {
    var self = this;
    window.addEventListener('pi-delete:open', function(e){
      var d = (e && e.detail) || {};
      self.itemId = d.itemId || '';
      self.name = d.name || '';
      self.rules = Array.isArray(d.rules) ? d.rules : [];
      self.confirmText = '';
      self.submitting = false;
      self.open = true;
    });
  },
  matches() { return this.confirmText.trim() === this.name.trim() && this.name; },
  async doDelete() {
    if (!this.matches() || this.submitting) return;
    this.submitting = true;
    var endpoint = window.EB_DEVICE_ENDPOINT || '';
    var serviceId = document.body.getAttribute('data-eb-serviceid') || '';
    var username = document.body.getAttribute('data-eb-username') || '';
    try {
      var res = await fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'piDelete', serviceId: serviceId, username: username, itemId: this.itemId, hash: window.__ebProfileHash || '' }) });
      var data = await res.json();
      if (data && data.status === 'success') {
        try { window.showToast && window.showToast('Protected Item deleted.', 'success'); } catch(_){ }
        this.open = false;
        try { window.location.reload(); } catch(_){ }
      } else {
        try { window.showToast && window.showToast((data && data.message) || 'Failed to delete', 'error'); } catch(_){ }
        this.submitting = false;
      }
    } catch (e) {
      try { window.showToast && window.showToast('Network error', 'error'); } catch(_){ }
      this.submitting = false;
    }
  }
}" x-cloak>
  <div x-show="open" class="fixed inset-0 z-[60] flex items-start justify-center p-4 overflow-y-auto" x-transition.opacity>
    <div class="eb-modal-backdrop fixed inset-0" @click="open=false"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 w-full my-6">
      <div class="eb-modal-header">
        <div>
          <h2 class="eb-modal-title">Delete Protected Item</h2>
          <p class="eb-modal-subtitle">This cannot be undone.</p>
        </div>
        <button class="eb-modal-close" @click="open=false">&times;</button>
      </div>
      <div class="eb-modal-body">
        <p class="eb-type-body mb-3">
          You are about to delete the Protected Item
          <strong x-text="name"></strong>. Backup data already in the Storage Vault will not be removed,
          but the Protected Item configuration will be deleted.
        </p>
        <div x-show="rules.length" class="eb-alert eb-alert--warning mb-3">
          <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z"/></svg>
          <div>
            <div class="eb-alert-title">The following schedules will also be removed:</div>
            <ul class="list-disc pl-5">
              <template x-for="(r, i) in rules" :key="'dr-'+i"><li x-text="r.name || r.id"></li></template>
            </ul>
          </div>
        </div>
        <label class="eb-field-label">Type the Protected Item name to confirm</label>
        <input type="text" class="eb-input" x-model="confirmText" :placeholder="name">
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="open=false">Cancel</button>
        <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="doDelete()" :disabled="!matches() || submitting">
          <span x-show="!submitting">Delete Protected Item</span>
          <span x-show="submitting">Deleting&hellip;</span>
        </button>
      </div>
    </div>
  </div>
</div>
{/literal}
