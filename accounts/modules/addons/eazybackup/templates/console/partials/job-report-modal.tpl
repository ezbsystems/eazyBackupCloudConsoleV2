{* Shared Job Report Modal (include from profile and dashboard) *}
<div id="job-report-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 eb-modal-backdrop"></div>
  <div class="eb-modal relative mx-auto my-8 w-full max-w-5xl">
    <div class="eb-modal-header">
      <div class="min-w-0">
        <div class="eb-modal-title truncate" id="jrm-title">Job</div>
        <div class="eb-modal-subtitle mt-0.5" id="jrm-subtitle"></div>
      </div>
      <div class="flex items-center gap-2">
      <button id="jrm-cancel" type="button"
              class="eb-btn eb-btn-danger eb-btn-sm hidden"
              title="Cancel this running backup job">
        Cancel job
      </button>
      <button id="jrm-close" class="eb-modal-close" aria-label="Close job report">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
      </div>
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
        <div class="eb-table-toolbar flex flex-col gap-2 px-3 py-2 text-xs sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-wrap items-center gap-2"
               x-data="{
                 open: false,
                 sel: 'all',
                 labels: { all: 'All', warning: 'Warnings', error: 'Errors' },
                 pick(v) {
                   this.sel = v;
                   this.open = false;
                   const s = document.getElementById('jrm-filter');
                   if (s) { s.value = v; s.dispatchEvent(new Event('change', { bubbles: true })); }
                 }
               }"
               @click.away="open = false"
               @keydown.escape.window="open = false">
            <span class="text-[var(--eb-text-secondary)]">Log entries</span>
            <div class="relative">
              <button type="button"
                      class="eb-menu-trigger py-1 text-xs"
                      :aria-expanded="open ? 'true' : 'false'"
                      aria-haspopup="listbox"
                      @click="open = !open">
                <span x-text="labels[sel] || 'All'"></span>
                <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 0 1 1.414 0L10 10.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
              <div x-show="open" x-cloak
                   x-transition:enter="transition ease-out duration-100"
                   x-transition:enter-start="opacity-0 scale-95"
                   x-transition:enter-end="opacity-100 scale-100"
                   x-transition:leave="transition ease-in duration-75"
                   x-transition:leave-start="opacity-100 scale-100"
                   x-transition:leave-end="opacity-0 scale-95"
                   class="eb-dropdown-menu absolute left-0 z-50 mt-1 w-40 overflow-hidden"
                   role="listbox"
                   style="display:none;">
                <button type="button" role="option" class="eb-menu-option" :class="sel === 'all' ? 'is-active' : ''" @click="pick('all')">All</button>
                <button type="button" role="option" class="eb-menu-option" :class="sel === 'warning' ? 'is-active' : ''" @click="pick('warning')">Warnings</button>
                <button type="button" role="option" class="eb-menu-option" :class="sel === 'error' ? 'is-active' : ''" @click="pick('error')">Errors</button>
              </div>
            </div>
            {* Hidden shim that keeps the existing job-reports.js applyFilter() listener working. *}
            <select id="jrm-filter" class="hidden" aria-hidden="true" tabindex="-1">
              <option value="all">All</option>
              <option value="warning">Warnings</option>
              <option value="error">Errors</option>
            </select>
          </div>
          <div class="flex flex-wrap items-center gap-2 min-w-0">
            <button id="jrm-export" type="button" class="eb-btn eb-btn-outline eb-btn-xs shrink-0" title="Download all entries for this job as a CSV file" aria-label="Export job log as CSV">
              Export CSV
            </button>
            {include file="modules/addons/eazybackup/templates/console/partials/job-report-ticket-button.tpl"}
            <input id="jrm-search" type="text" placeholder="Search…" class="eb-input min-w-0 w-full py-1 text-xs sm:w-64">
          </div>
        </div>

        {* Full-width preview/dedupe panel; toggled by job-ticket.js *}
        <div id="jrm-ticket-panel"
             class="hidden border-t border-[var(--eb-border-default)] px-3 py-3 text-sm"
             role="region"
             aria-label="Support ticket preview">
          <div id="jrm-ticket-dupe" class="hidden mb-3 rounded border border-amber-500/40 bg-amber-500/10 p-2 text-amber-200 text-xs">
            <span data-dupe-text>We already have an open ticket about this job.</span>
            <a data-dupe-link href="#" class="underline ml-1" target="_blank" rel="noopener">Open existing ticket</a>
          </div>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="min-w-0">
              <div class="eb-text-muted text-xs">Subject</div>
              <div id="jrm-ticket-subject" class="text-[var(--eb-text-primary)] truncate"></div>
            </div>
            <div id="jrm-ticket-kb" class="hidden min-w-0">
              <div class="eb-text-muted text-xs mb-1">Try this first - related help articles</div>
              <ul id="jrm-ticket-kb-list" class="space-y-1"></ul>
            </div>
          </div>

          <div id="jrm-ticket-error" class="hidden mt-2 text-rose-400 text-xs"></div>

          <div class="mt-3 flex items-center justify-end gap-2">
            <button id="jrm-ticket-cancel" type="button" class="eb-btn eb-btn-outline eb-btn-xs">Cancel</button>
            <button id="jrm-ticket-continue" type="button" class="eb-btn eb-btn-primary eb-btn-xs">Continue to ticket</button>
          </div>
        </div>

        <div id="jrm-logs" class="max-h-96 overflow-y-auto divide-y divide-[var(--eb-border-default)] text-sm"></div>
      </div>
    </div>
  </div>
  <input type="hidden" id="jrm-current-job" value="" />
  <input type="hidden" id="jrm-service-id" value="{$serviceid}" />
  <input type="hidden" id="jrm-username" value="{$username}" />
</div>

