{* Shared Job Report Modal (include from profile and dashboard) *}
<div id="job-report-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60"></div>
  <div class="relative mx-auto my-8 w-full max-w-5xl bg-slate-900 border border-slate-700 rounded-lg shadow-xl">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
      <div class="min-w-0">
        <div class="text-slate-200 text-lg font-semibold truncate" id="jrm-title">Job</div>
        <div class="text-xs text-slate-400 mt-0.5" id="jrm-subtitle"></div>
      </div>
      <button id="jrm-close" class="text-slate-400 hover:text-slate-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-5 py-4 space-y-4">
      <div id="jrm-summary" class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <div><div class="text-slate-400">Status</div><div id="jrm-status" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Type</div><div id="jrm-type" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Device</div><div id="jrm-device" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Protected Item</div><div id="jrm-item" class="text-slate-200 truncate"></div></div>
        <div><div class="text-slate-400">Vault</div><div id="jrm-vault" class="text-slate-200 truncate"></div></div>
        <div><div class="text-slate-400">Uploaded</div><div id="jrm-up" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Downloaded</div><div id="jrm-down" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Size</div><div id="jrm-size" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Started</div><div id="jrm-start" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Ended</div><div id="jrm-end" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Duration</div><div id="jrm-duration" class="text-slate-200"></div></div>
        <div><div class="text-slate-400">Version</div><div id="jrm-version" class="text-slate-200"></div></div>
      </div>
      <div class="border border-slate-700 rounded overflow-hidden">
        <div class="flex items-center justify-between bg-slate-800/60 px-3 py-2 text-xs">
          <div class="flex items-center gap-2">
            <span class="text-slate-300">Log entries</span>
            <select id="jrm-filter" class="px-2 py-1 rounded bg-slate-900 border border-slate-700 text-slate-200">
              <option value="all">All</option>
              <option value="warning">Warnings</option>
              <option value="error">Errors</option>
            </select>
          </div>
          <input id="jrm-search" type="text" placeholder="Search…" class="px-2 py-1 rounded bg-slate-900 border border-slate-700 text-slate-200 w-64">
        </div>
        <div id="jrm-logs" class="max-h-96 overflow-y-auto divide-y divide-slate-800 text-sm"></div>
      </div>
    </div>
  </div>
  <input type="hidden" id="jrm-current-job" value="" />
  <input type="hidden" id="jrm-service-id" value="{$serviceid}" />
  <input type="hidden" id="jrm-username" value="{$username}" />
</div>

