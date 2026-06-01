{**
 * eazyBackup Protected Item delete confirmation modal.
 * Logic: assets/js/pi-delete-modal.js (piDeleteModal factory).
 * Opened via window event 'pi-delete:open'.
 *}
<div x-data="piDeleteModal()" x-init="init()" x-cloak @keydown.escape.window="close()">
  <div x-show="isOpen"
       x-transition.opacity
       class="fixed inset-0 z-[60] flex items-start justify-center p-4 overflow-y-auto"
       style="display: none;">
    <div class="eb-modal-backdrop fixed inset-0" @click="close()"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 w-full my-6">
      <div class="eb-modal-header">
        <div>
          <h2 class="eb-modal-title">Delete Protected Item</h2>
          <p class="eb-modal-subtitle">This cannot be undone.</p>
        </div>
        <button type="button" class="eb-modal-close" @click="close()" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body">
        <p class="eb-type-body mb-3">
          You are about to delete the Protected Item
          <strong x-text="name"></strong>.
        </p>
        <p class="eb-type-body mb-3">
          Backup data in the Storage Vault will be retained for the lenth of time determined by your Vault retention policy.
        </p>
        <div x-show="!itemId" class="eb-alert eb-alert--danger mb-3">
          <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z"/></svg>
          <div>
            <div class="eb-alert-title">This Protected Item cannot be deleted right now.</div>
            <p class="text-sm">We couldn&rsquo;t identify the item on the server. Please refresh the page and try again.</p>
          </div>
        </div>
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
        <p class="eb-type-body font-mono select-all mt-1 mb-2" x-text="(name || '').toUpperCase()"></p>
        <input type="text" class="eb-input" x-model="confirmText" :placeholder="name">
      </div>
      <div class="eb-modal-footer">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="close()">Cancel</button>
        <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="doDelete()" :disabled="!matches() || submitting">
          <span x-show="!submitting">Delete Protected Item</span>
          <span x-show="submitting">Deleting&hellip;</span>
        </button>
      </div>
    </div>
  </div>
</div>
