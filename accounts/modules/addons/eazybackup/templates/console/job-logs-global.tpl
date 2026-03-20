{**
 * eazyBackup Global Job Logs
 * UI with visual parity to user-profile Job Logs
 *
 * @copyright Copyright (c) eazyBackup Systems Ltd. 2024
 * @license https://www.eazybackup.com/terms/eula
 *}

{literal}
<style>
 [x-cloak] { display: none !important; }
</style>
{/literal}

<div id="global-jobs-page" class="eb-page">
  {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

  <div class="eb-page-inner">
    <div x-data="{ 
      sidebarOpen: true,
      sidebarCollapsed: localStorage.getItem('eb_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
      toggleCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('eb_sidebar_collapsed', this.sidebarCollapsed);
      },
      handleResize() {
        if (window.innerWidth < 1360 && !this.sidebarCollapsed) {
          this.sidebarCollapsed = true;
        }
      }
    }"
    x-init="window.addEventListener('resize', () => handleResize())"
    class="eb-panel !p-0">
      <div class="eb-app-shell">
        {include file="modules/addons/eazybackup/templates/clientarea/partials/sidebar.tpl" ebSidebarPage='job-logs'}

        <main class="eb-app-main">
          <div class="eb-app-header">
            <div class="eb-app-header-copy">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="eb-app-header-icon h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
              </svg>
              <h1 class="eb-app-header-title">Job Logs</h1>
            </div>
          </div>

          <div class="eb-app-body">
            <div class="eb-subpanel" x-data="{ open:false, cols:{ user:true, id:false, device:true, item:true, vault:false, ver:false, type:true, status:true, dirs:false, files:false, size:true, vsize:true, up:false, down:false, started:true, ended:true, dur:true } }">
              <div class="border-b px-4 pt-4 pb-3" style="border-color: var(--eb-border-default);">
      <div class="flex flex-wrap items-center gap-2">
          <button type="button" data-jobs-status-chip data-status="Error" class="eb-badge eb-badge--danger eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot" style="background: var(--eb-danger-icon);"></span><span>Error</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Missed" class="eb-badge eb-badge--neutral eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot eb-job-chip-dot--empty"></span><span>Missed</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Warning" class="eb-badge eb-badge--warning eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot" style="background: var(--eb-warning-icon);"></span><span>Warning</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Timeout" class="eb-badge eb-badge--warning eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot" style="background: var(--eb-warning-icon);"></span><span>Timeout</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Cancelled" class="eb-badge eb-badge--danger eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot" style="background: var(--eb-danger-icon);"></span><span>Cancelled</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Running" class="eb-badge eb-badge--info eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot" style="background: var(--eb-info-icon);"></span><span>Running</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Skipped" class="eb-badge eb-badge--premium eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot" style="background: var(--eb-premium-text);"></span><span>Skipped</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Success" class="eb-badge eb-badge--success eb-job-chip disabled:cursor-not-allowed">
            <span class="eb-job-chip-dot" style="background: var(--eb-success-icon);"></span><span>Success</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button id="global-jobs-clear-filters" type="button" class="hidden eb-btn eb-btn-ghost eb-btn-xs shrink-0">
            Clear
          </button>
      </div>

        <div class="mt-4 flex w-full min-w-0 flex-wrap gap-2 sm:flex-nowrap">
          <div x-data="{ userOpen:false }" class="relative shrink-0" @click.away="userOpen=false" @jobs:username-selected.window="userOpen=false">
            <button @click="userOpen = !userOpen" type="button"
                    class="eb-app-toolbar-button">
              <span class="text-[var(--eb-text-muted)]">User:</span>
              <span id="global-jobs-username-label" class="font-medium">All users</span>
              <svg class="h-4 w-4 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>
            <div x-show="userOpen" x-cloak x-transition class="eb-menu absolute left-0 z-10 mt-2 w-72 overflow-hidden">
              <div id="global-jobs-username-menu" class="max-h-72 overflow-y-auto p-1"></div>
            </div>
            <select id="global-jobs-username" class="hidden" aria-hidden="true" tabindex="-1">
              <option value="">All users</option>
            </select>
          </div>
      </div>
      <div id="global-jobs-active-filters" class="mt-2 hidden text-xs text-[var(--eb-text-muted)]"></div>
    </div>

              <div class="eb-table-toolbar flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="relative shrink-0" @click.away="open=false">
          <button type="button" class="eb-app-toolbar-button" @click="open=!open">
            <span class="font-medium">Columns</span>
            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
          </button>
          <div x-show="open" x-transition class="eb-menu absolute z-10 mt-2 w-72 p-2">
            <div class="eb-menu-checklist two-col">
              <label class="eb-menu-checklist-item"><span>Username</span><input type="checkbox" class="eb-checkbox" x-model="cols.user"></label>
              <label class="eb-menu-checklist-item"><span>Job ID</span><input type="checkbox" class="eb-checkbox" x-model="cols.id"></label>
              <label class="eb-menu-checklist-item"><span>Device</span><input type="checkbox" class="eb-checkbox" x-model="cols.device"></label>
              <label class="eb-menu-checklist-item"><span>Protected Item</span><input type="checkbox" class="eb-checkbox" x-model="cols.item"></label>
              <label class="eb-menu-checklist-item"><span>Storage Vault</span><input type="checkbox" class="eb-checkbox" x-model="cols.vault"></label>
              <label class="eb-menu-checklist-item"><span>Version</span><input type="checkbox" class="eb-checkbox" x-model="cols.ver"></label>
              <label class="eb-menu-checklist-item"><span>Type</span><input type="checkbox" class="eb-checkbox" x-model="cols.type"></label>
              <label class="eb-menu-checklist-item"><span>Status</span><input type="checkbox" class="eb-checkbox" x-model="cols.status"></label>
              <label class="eb-menu-checklist-item"><span>Directories</span><input type="checkbox" class="eb-checkbox" x-model="cols.dirs"></label>
              <label class="eb-menu-checklist-item"><span>Files</span><input type="checkbox" class="eb-checkbox" x-model="cols.files"></label>
              <label class="eb-menu-checklist-item"><span>Size</span><input type="checkbox" class="eb-checkbox" x-model="cols.size"></label>
              <label class="eb-menu-checklist-item"><span>Storage Vault Size</span><input type="checkbox" class="eb-checkbox" x-model="cols.vsize"></label>
              <label class="eb-menu-checklist-item"><span>Uploaded</span><input type="checkbox" class="eb-checkbox" x-model="cols.up"></label>
              <label class="eb-menu-checklist-item"><span>Downloaded</span><input type="checkbox" class="eb-checkbox" x-model="cols.down"></label>
              <label class="eb-menu-checklist-item"><span>Started</span><input type="checkbox" class="eb-checkbox" x-model="cols.started"></label>
              <label class="eb-menu-checklist-item"><span>Ended</span><input type="checkbox" class="eb-checkbox" x-model="cols.ended"></label>
              <label class="eb-menu-checklist-item"><span>Duration</span><input type="checkbox" class="eb-checkbox" x-model="cols.dur"></label>
            </div>
          </div>
        </div>

        <div x-data="{ sizeOpen: false, pageSize: 10, sizes: [10, 25, 50, 100], setSize(size) { this.pageSize = size; this.sizeOpen = false; window.dispatchEvent(new CustomEvent('jobs:pagesize', { detail: size })); } }" class="relative">
          <button @click="sizeOpen = !sizeOpen" @click.away="sizeOpen = false" type="button"
                  class="eb-app-toolbar-button">
            <span class="text-[var(--eb-text-muted)]">Show</span>
            <span x-text="pageSize" class="font-medium"></span>
            <svg class="h-4 w-4 transition-transform" :class="sizeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
          </button>
          <div x-show="sizeOpen" x-cloak x-transition class="eb-menu absolute left-0 z-10 mt-2 w-24 overflow-hidden">
            <template x-for="size in sizes" :key="size">
              <button @click="setSize(size)" type="button"
                      :class="pageSize === size ? 'is-active' : ''"
                      class="eb-menu-item block w-full px-3 py-2 text-left text-sm transition-colors">
                <span x-text="size"></span>
              </button>
            </template>
          </div>
        </div>

        <div x-data="{ rangeOpen: false, rangeHours: 24, ranges: [24, 48, 60, 72], setRange(hours) { this.rangeHours = hours; this.rangeOpen = false; window.dispatchEvent(new CustomEvent('jobs:rangehours', { detail: hours })); } }" class="relative">
          <button @click="rangeOpen = !rangeOpen" @click.away="rangeOpen = false" type="button"
                  class="eb-app-toolbar-button">
            <span class="text-[var(--eb-text-muted)]">Range</span>
            <span class="font-medium"><span x-text="rangeHours"></span>h</span>
            <svg class="h-4 w-4 transition-transform" :class="rangeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
          </button>
          <div x-show="rangeOpen" x-cloak x-transition class="eb-menu absolute left-0 z-10 mt-2 w-24 overflow-hidden">
            <template x-for="hours in ranges" :key="hours">
              <button @click="setRange(hours)" type="button"
                      :class="rangeHours === hours ? 'is-active' : ''"
                      class="eb-menu-item block w-full px-3 py-2 text-left text-sm transition-colors">
                <span x-text="hours"></span>h
              </button>
            </template>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <div id="global-jobs-loading" class="hidden items-center gap-2 text-xs text-[var(--eb-text-secondary)]">
          <span class="h-3.5 w-3.5 animate-spin rounded-full border-2" style="border-color: color-mix(in srgb, var(--eb-primary) 30%, transparent); border-top-color: var(--eb-primary);"></span>
          <span>Loading...</span>
        </div>
        <input id="global-jobs-search" type="text" placeholder="Search jobs..." class="eb-input w-full xl:w-80">
      </div>
    </div>

              <div class="px-4 pb-2">
                <div class="table-scroll eb-table-shell overflow-x-auto">
                  <table id="global-jobs-table" class="eb-table min-w-full text-sm" data-job-table>
                    <thead>
                      <tr>
              <th x-show="cols.user" data-sort="Username"><button type="button" class="eb-table-sort-button">Username<span data-sort-indicator></span></button></th>
              <th x-show="cols.id" x-cloak data-sort="JobID"><button type="button" class="eb-table-sort-button">Job ID<span data-sort-indicator></span></button></th>
              <th x-show="cols.device" data-sort="Device"><button type="button" class="eb-table-sort-button">Device<span data-sort-indicator></span></button></th>
              <th x-show="cols.item" data-sort="ProtectedItem"><button type="button" class="eb-table-sort-button">Protected Item<span data-sort-indicator></span></button></th>
              <th x-show="cols.vault" data-sort="StorageVault"><button type="button" class="eb-table-sort-button">Storage Vault<span data-sort-indicator></span></button></th>
              <th x-show="cols.ver" data-sort="Version"><button type="button" class="eb-table-sort-button">Version<span data-sort-indicator></span></button></th>
              <th x-show="cols.type" data-sort="Type"><button type="button" class="eb-table-sort-button">Type<span data-sort-indicator></span></button></th>
              <th x-show="cols.status" data-sort="Status"><button type="button" class="eb-table-sort-button">Status<span data-sort-indicator></span></button></th>
              <th x-show="cols.dirs" data-sort="Directories"><button type="button" class="eb-table-sort-button">Directories<span data-sort-indicator></span></button></th>
              <th x-show="cols.files" data-sort="Files"><button type="button" class="eb-table-sort-button">Files<span data-sort-indicator></span></button></th>
              <th x-show="cols.size" data-sort="Size"><button type="button" class="eb-table-sort-button">Size<span data-sort-indicator></span></button></th>
              <th x-show="cols.vsize" data-sort="VaultSize"><button type="button" class="eb-table-sort-button">Storage Vault Size<span data-sort-indicator></span></button></th>
              <th x-show="cols.up" data-sort="Uploaded"><button type="button" class="eb-table-sort-button">Uploaded<span data-sort-indicator></span></button></th>
              <th x-show="cols.down" data-sort="Downloaded"><button type="button" class="eb-table-sort-button">Downloaded<span data-sort-indicator></span></button></th>
              <th x-show="cols.started" data-sort="Started"><button type="button" class="eb-table-sort-button">Started<span data-sort-indicator></span></button></th>
              <th x-show="cols.ended" data-sort="Ended"><button type="button" class="eb-table-sort-button">Ended<span data-sort-indicator></span></button></th>
              <th x-show="cols.dur" data-sort="Duration"><button type="button" class="eb-table-sort-button">Duration<span data-sort-indicator></span></button></th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--eb-border-default)]"></tbody>
                  </table>
                </div>
      </div>
              <div class="px-4 py-2">
                <div id="global-jobs-pager" class="eb-table-pagination text-xs font-medium text-[var(--eb-text-muted)]"></div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>
<script src="modules/addons/eazybackup/assets/js/job-reports.js" defer></script>
{include file="modules/addons/eazybackup/templates/console/partials/job-report-modal.tpl" serviceid="" username=""}

<script>
try {
  window.EB_JOBREPORTS_GLOBAL_ENDPOINT = '{$modulelink}&a=job-reports-global';
  window.EB_JOBREPORTS_ENDPOINT = '{$modulelink}&a=job-reports';
  const initGlobalJobs = function() {
    try {
      const f = window.jobReportsFactory && window.jobReportsFactory();
      if (!f || !f.makeGlobalJobsTable) return null;
      const table = document.getElementById('global-jobs-table');
      if (!table) return null;
      const api = f.makeGlobalJobsTable(table, {
        totalEl: document.getElementById('global-jobs-total'),
        loadingEl: document.getElementById('global-jobs-loading'),
        pagerEl: document.getElementById('global-jobs-pager'),
        searchInput: document.getElementById('global-jobs-search'),
        usernameDropdown: document.getElementById('global-jobs-username'),
        usernameMenuLabel: document.getElementById('global-jobs-username-label'),
        usernameMenuList: document.getElementById('global-jobs-username-menu'),
        rangeHours: 24,
        chipButtons: Array.from(document.querySelectorAll('#global-jobs-page [data-jobs-status-chip]')),
        clearBtn: document.getElementById('global-jobs-clear-filters'),
        summaryEl: document.getElementById('global-jobs-active-filters')
      });
      if (api && api.reload) api.reload();
      return api;
    } catch (e) { return null; }
  };
  if (window.jobReportsFactory) initGlobalJobs();
  else document.addEventListener('jobReports:ready', initGlobalJobs, { once: true });
} catch (e) {}
</script>
