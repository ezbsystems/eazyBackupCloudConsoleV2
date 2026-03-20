{* Shared Job Report Modal (include from profile and dashboard) *}
<div id="job-report-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 eb-modal-backdrop"></div>
  <div class="eb-modal relative mx-auto my-8 w-full max-w-5xl">
    <div class="eb-modal-header">
      <div class="min-w-0">
        <div class="eb-modal-title truncate" id="jrm-title">Job</div>
        <div class="eb-modal-subtitle mt-0.5" id="jrm-subtitle"></div>
      </div>
      <button id="jrm-close" class="eb-modal-close" aria-label="Close job report">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="eb-modal-body space-y-4">
      <div id="jrm-summary" class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <div><div class="eb-text-muted">Status</div><div id="jrm-status" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Type</div><div id="jrm-type" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Device</div><div id="jrm-device" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Protected Item</div><div id="jrm-item" class="truncate text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Vault</div><div id="jrm-vault" class="truncate text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Uploaded</div><div id="jrm-up" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Downloaded</div><div id="jrm-down" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Size</div><div id="jrm-size" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Started</div><div id="jrm-start" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Ended</div><div id="jrm-end" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Duration</div><div id="jrm-duration" class="text-[var(--eb-text-primary)]"></div></div>
        <div><div class="eb-text-muted">Version</div><div id="jrm-version" class="text-[var(--eb-text-primary)]"></div></div>
      </div>
      <div class="eb-table-shell overflow-hidden">
        <div class="eb-table-toolbar flex items-center justify-between px-3 py-2 text-xs">
          <div class="flex items-center gap-2">
            <span class="text-[var(--eb-text-secondary)]">Log entries</span>
            <select id="jrm-filter" class="eb-select py-1 text-xs">
              <option value="all">All</option>
              <option value="warning">Warnings</option>
              <option value="error">Errors</option>
            </select>
          </div>
          <input id="jrm-search" type="text" placeholder="Search…" class="eb-input w-64 py-1 text-xs">
        </div>
        <div id="jrm-logs" class="max-h-96 overflow-y-auto divide-y divide-[var(--eb-border-default)] text-sm"></div>
      </div>
    </div>
  </div>
  <input type="hidden" id="jrm-current-job" value="" />
  <input type="hidden" id="jrm-service-id" value="{$serviceid}" />
  <input type="hidden" id="jrm-username" value="{$username}" />
</div>

