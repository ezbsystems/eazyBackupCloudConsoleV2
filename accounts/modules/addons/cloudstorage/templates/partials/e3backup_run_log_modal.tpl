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
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
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
                        <div class="eb-field-label">Started</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryStarted">-</div>
                    </div>
                    <div>
                        <div class="eb-field-label">Finished</div>
                        <div class="eb-type-body !text-[var(--eb-text-primary)]" id="ebE3RunSummaryFinished">-</div>
                    </div>
                </div>
            </div>

            {* Log toolbar: severity filter + ticket button *}
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div class="inline-flex items-center gap-1" role="group" aria-label="Filter log by severity">
                    <button type="button" id="ebE3RunSev-all" class="eb-btn eb-btn-ghost eb-btn-xs is-active" aria-pressed="true">All</button>
                    <button type="button" id="ebE3RunSev-warning" class="eb-btn eb-btn-ghost eb-btn-xs" aria-pressed="false">Warnings</button>
                    <button type="button" id="ebE3RunSev-error" class="eb-btn eb-btn-ghost eb-btn-xs" aria-pressed="false">Errors</button>
                </div>
                <button type="button" id="ebE3RunTicketBtn" class="eb-btn eb-btn-secondary eb-btn-sm gap-2 hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" />
                    </svg>
                    <span>Open Support Ticket</span>
                </button>
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

            <div class="mb-1">
                <h3 class="eb-type-h3 !mb-2">Backup log</h3>
                <pre id="ebE3RunLogBody" class="eb-type-mono max-h-80 overflow-auto whitespace-pre-wrap rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-base)] p-4 text-[length:var(--eb-type-mono-size)] leading-relaxed text-[var(--eb-text-secondary)]"></pre>
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
