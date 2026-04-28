{* Open Support Ticket button for the Job Report Modal toolbar.
   Visibility (Warning/Error/Missed/Timeout only) is controlled by job-ticket.js.
   The matching preview/dedupe panel lives in job-report-modal.tpl as a full-width
   row directly under the log entries toolbar. *}
<button id="jrm-open-ticket"
        type="button"
        class="eb-btn eb-btn-primary eb-btn-xs shrink-0 hidden"
        data-eb-ticket-button
        title="Open a support ticket about this job with the log pre-attached"
        aria-label="Open support ticket about this job">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10z"/>
    </svg>
    <span>Open Support Ticket</span>
</button>
