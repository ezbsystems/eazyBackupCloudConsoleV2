{*
    Shared e3 Cloud Backup run-log modal.

    Include this once per page that needs to open run logs:
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_run_log_modal.tpl"}

    Open it from JS with:  window.ebE3RunModal.open(runId, meta)
    or from a brace-free inline handler with: ebE3OpenRunLog('<run-uuid>')

    The modal pulls sanitized logs from api/cloudbackup_get_run_logs.php and
    offers a one-click "Open Support Ticket" prefill handoff (run-scoped).
*}
<div id="ebE3RunLogModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6">
    <div class="eb-modal-backdrop fixed inset-0" data-e3-run-close role="presentation"></div>
    <div class="eb-modal relative z-10 flex max-h-[88vh] w-full max-w-4xl flex-col overflow-hidden">
        <div class="eb-modal-header !shrink-0">
            <div class="min-w-0">
                <h2 class="eb-modal-title truncate"><span id="ebE3RunSummaryJob">Backup run</span></h2>
                <p class="eb-modal-subtitle">
                    <span id="ebE3RunSummaryUser">-</span>
                    <span class="eb-text-muted"> &middot; </span>
                    <span id="ebE3RunSummaryAgent">-</span>
                </p>
            </div>
            <button type="button" class="eb-modal-close" data-e3-run-close aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="eb-modal-body min-h-0 flex-1 overflow-y-auto">
            {* Summary grid *}
            <div class="eb-subpanel !mb-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div>
                        <div class="eb-field-label">Status</div>
                        <span id="ebE3RunSummaryStatus" class="eb-badge eb-badge--neutral">-</span>
                    </div>
                    <div>
                        <div class="eb-field-label">Engine</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryEngine">-</div>
                    </div>
                    <div>
                        <div class="eb-field-label">Duration</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryDuration">-</div>
                    </div>
                    <div>
                        <div class="eb-field-label">Size</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummarySize">-</div>
                    </div>
                    <div>
                        <div class="eb-field-label">Uploaded</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryUploaded">-</div>
                    </div>
                    <div>
                        <div class="eb-field-label">Downloaded</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryDownloaded">-</div>
                    </div>
                    <div>
                        <div class="eb-field-label">Started</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryStarted">-</div>
                    </div>
                    <div>
                        <div class="eb-field-label">Finished</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryFinished">-</div>
                    </div>
                </div>
            </div>

            <div class="eb-table-shell overflow-hidden !mb-3">
                <div class="eb-table-toolbar flex flex-col gap-2 px-3 py-2 text-xs sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2"
                         x-data="{
                           open: false,
                           sel: 'all',
                           labels: { all: 'All', warning: 'Warnings', error: 'Errors' },
                           pick(v) {
                             this.sel = v;
                             this.open = false;
                             if (window.ebE3RunModal && window.ebE3RunModal.setSeverity) {
                               window.ebE3RunModal.setSeverity(v);
                             }
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
                    </div>
                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                        <button type="button" id="ebE3RunTicketBtn" class="eb-btn eb-btn-outline eb-btn-xs shrink-0 gap-2 hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" />
                            </svg>
                            <span>Open Support Ticket</span>
                        </button>
                        <input id="ebE3RunLogSearch" type="text" placeholder="Search…" class="eb-input min-w-0 w-full py-1 text-xs sm:w-64">
                    </div>
                </div>

            {* Inline ticket preview panel (drained by e3backup_run_ticket.js) *}
            <div id="ebE3RunTicketPanel" class="eb-card !p-4 !mb-3 hidden">
                <div class="eb-field-label">New support ticket</div>
                <div class="eb-type-body !mt-1 !text-[var(--eb-text-primary)] font-medium" id="ebE3RunTicketSubject"></div>
                <div id="ebE3RunTicketDupe" class="eb-alert eb-alert--warning !mt-3 hidden">
                    <div>
                        <p class="eb-type-caption" data-dupe-text></p>
                        <a class="eb-link" data-dupe-link target="_blank" rel="noopener">Open existing ticket</a>
                    </div>
                </div>
                <div id="ebE3RunTicketKb" class="!mt-3 hidden">
                    <div class="eb-field-label">Related help articles</div>
                    <ul id="ebE3RunTicketKbList" class="!mt-1 space-y-1"></ul>
                </div>
                <div id="ebE3RunTicketError" class="eb-type-caption !mt-2 !text-[var(--eb-danger-text)] hidden"></div>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" id="ebE3RunTicketContinue" class="eb-btn eb-btn-primary eb-btn-sm">Continue to ticket</button>
                    <button type="button" id="ebE3RunTicketCancel" class="eb-btn eb-btn-ghost eb-btn-sm">Cancel</button>
                </div>
            </div>

            <div id="ebE3RunLogBody" class="max-h-96 overflow-y-auto divide-y divide-[var(--eb-border-default)] text-sm"></div>
            </div>
            <div id="ebE3RunValidation" class="mt-4 hidden">
                <h3 class="eb-type-h3 !mb-2">Validation log</h3>
                <pre id="ebE3RunValidationBody" class="eb-type-mono max-h-64 overflow-auto whitespace-pre-wrap rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-base)] p-4 text-[length:var(--eb-type-mono-size)] leading-relaxed text-[var(--eb-text-secondary)]"></pre>
            </div>
        </div>
    </div>
</div>

<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/e3backup_run_modal.js"></script>
<script src="{$WEB_ROOT}/modules/addons/cloudstorage/assets/js/e3backup_run_ticket.js"></script>
