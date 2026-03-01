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

<div id="global-jobs-page" class="min-h-screen bg-slate-950 text-gray-300">
  {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

  <div class="container mx-auto px-4 py-8">
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
    class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/clientarea/partials/sidebar.tpl" ebSidebarPage='job-logs'}

        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800/60">
            <div class="flex items-center gap-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
              </svg>
              <h1 class="text-xl font-semibold text-white">Job Logs</h1>
            </div>
          </div>

          <div class="p-6">
            <div class="bg-slate-950/70 rounded-2xl border border-slate-800/80 shadow-[0_18px_40px_rgba(0,0,0,0.35)]" x-data="{ open:false, cols:{ user:true, id:false, device:true, item:true, vault:false, ver:false, type:true, status:true, dirs:false, files:false, size:true, vsize:true, up:false, down:false, started:true, ended:true, dur:true } }">
              <div class="border-b border-slate-800/80 px-4 pt-4 pb-3">
      <div class="flex flex-wrap items-center gap-2">
          <button type="button" data-jobs-status-chip data-status="Error" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span><span>Error</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Missed" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full border-2 border-slate-300"></span><span>Missed</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Warning" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span><span>Warning</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Timeout" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span><span>Timeout</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Cancelled" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span><span>Cancelled</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Running" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span><span>Running</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Skipped" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full bg-violet-500"></span><span>Skipped</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button type="button" data-jobs-status-chip data-status="Success" class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition select-none active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed">
            <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span><span>Success</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
          </button>
          <button id="global-jobs-clear-filters" type="button" class="hidden inline-flex shrink-0 items-center gap-1 rounded-full border border-slate-700/80 bg-slate-900/50 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:border-slate-600 hover:bg-slate-900/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
            Clear
          </button>
      </div>

        <div class="mt-4 flex w-full min-w-0 flex-wrap gap-2 sm:flex-nowrap">
          <div x-data="{ userOpen:false }" class="relative shrink-0" @click.away="userOpen=false" @jobs:username-selected.window="userOpen=false">
            <button @click="userOpen = !userOpen" type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700/80 bg-slate-900/50 px-3 py-2 text-sm text-white transition-all duration-200 hover:border-slate-600 hover:bg-slate-900/80">
              <span class="text-slate-400">User:</span>
              <span id="global-jobs-username-label" class="font-medium">All users</span>
              <svg class="h-4 w-4 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>
            <div x-show="userOpen" x-cloak x-transition class="absolute left-0 mt-2 w-72 overflow-hidden rounded-lg border border-slate-700 bg-slate-800 shadow-xl z-10">
              <div id="global-jobs-username-menu" class="max-h-72 overflow-y-auto p-1"></div>
            </div>
            <select id="global-jobs-username" class="hidden" aria-hidden="true" tabindex="-1">
              <option value="">All users</option>
            </select>
          </div>
      </div>
      <div id="global-jobs-active-filters" class="mt-2 hidden text-xs text-slate-400"></div>
    </div>

              <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="relative shrink-0" @click.away="open=false">
          <button type="button" class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80" @click="open=!open">
            <span class="font-medium">Columns</span>
            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
          </button>
          <div x-show="open" x-transition class="absolute mt-2 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-10 p-2">
            <div class="grid grid-cols-2 gap-1 text-sm text-slate-200">
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Username</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.user"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Job ID</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.id"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Device</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.device"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Protected Item</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.item"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Storage Vault</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.vault"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Version</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.ver"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Type</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.type"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Status</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.status"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Directories</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.dirs"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Files</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.files"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Size</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.size"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Storage Vault Size</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.vsize"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Uploaded</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.up"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Downloaded</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.down"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Started</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.started"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Ended</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.ended"></label>
              <label class="flex items-center justify-between rounded px-2 py-2 hover:bg-slate-800/60 cursor-pointer"><span>Duration</span><input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500" x-model="cols.dur"></label>
            </div>
          </div>
        </div>

        <div x-data="{ sizeOpen: false, pageSize: 10, sizes: [10, 25, 50, 100], setSize(size) { this.pageSize = size; this.sizeOpen = false; window.dispatchEvent(new CustomEvent('jobs:pagesize', { detail: size })); } }" class="relative">
          <button @click="sizeOpen = !sizeOpen" @click.away="sizeOpen = false" type="button"
                  class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
            <span class="text-slate-400">Show</span>
            <span x-text="pageSize" class="font-medium"></span>
            <svg class="h-4 w-4 transition-transform" :class="sizeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
          </button>
          <div x-show="sizeOpen" x-cloak x-transition class="absolute left-0 mt-2 w-24 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-10">
            <template x-for="size in sizes" :key="size">
              <button @click="setSize(size)" type="button"
                      :class="pageSize === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60 hover:text-white'"
                      class="block w-full px-3 py-2 text-left text-sm transition-colors">
                <span x-text="size"></span>
              </button>
            </template>
          </div>
        </div>

        <div x-data="{ rangeOpen: false, rangeHours: 24, ranges: [24, 48, 60, 72], setRange(hours) { this.rangeHours = hours; this.rangeOpen = false; window.dispatchEvent(new CustomEvent('jobs:rangehours', { detail: hours })); } }" class="relative">
          <button @click="rangeOpen = !rangeOpen" @click.away="rangeOpen = false" type="button"
                  class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
            <span class="text-slate-400">Range</span>
            <span class="font-medium"><span x-text="rangeHours"></span>h</span>
            <svg class="h-4 w-4 transition-transform" :class="rangeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
          </button>
          <div x-show="rangeOpen" x-cloak x-transition class="absolute left-0 mt-2 w-24 overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-10">
            <template x-for="hours in ranges" :key="hours">
              <button @click="setRange(hours)" type="button"
                      :class="rangeHours === hours ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60 hover:text-white'"
                      class="block w-full px-3 py-2 text-left text-sm transition-colors">
                <span x-text="hours"></span>h
              </button>
            </template>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <div id="global-jobs-loading" class="hidden items-center gap-2 text-xs text-slate-300">
          <span class="h-3.5 w-3.5 rounded-full border-2 border-[#fe5000]/30 border-t-[#fe5000] animate-spin"></span>
          <span>Loading...</span>
        </div>
        <input id="global-jobs-search" type="text" placeholder="Search jobs..." class="w-full xl:w-80 rounded-full border border-slate-700 bg-slate-900/70 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
      </div>
    </div>

              <div class="px-4 pb-2">
                <div class="table-scroll overflow-x-auto rounded-lg border border-slate-800">
                  <table id="global-jobs-table" class="min-w-full divide-y divide-slate-800 text-sm" data-job-table>
                    <thead class="bg-slate-900/80 text-slate-300">
                      <tr>
              <th x-show="cols.user" data-sort="Username" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Username<span data-sort-indicator></span></button></th>
              <th x-show="cols.id" x-cloak data-sort="JobID" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Job ID<span data-sort-indicator></span></button></th>
              <th x-show="cols.device" data-sort="Device" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Device<span data-sort-indicator></span></button></th>
              <th x-show="cols.item" data-sort="ProtectedItem" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Protected Item<span data-sort-indicator></span></button></th>
              <th x-show="cols.vault" data-sort="StorageVault" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Storage Vault<span data-sort-indicator></span></button></th>
              <th x-show="cols.ver" data-sort="Version" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Version<span data-sort-indicator></span></button></th>
              <th x-show="cols.type" data-sort="Type" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Type<span data-sort-indicator></span></button></th>
              <th x-show="cols.status" data-sort="Status" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Status<span data-sort-indicator></span></button></th>
              <th x-show="cols.dirs" data-sort="Directories" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Directories<span data-sort-indicator></span></button></th>
              <th x-show="cols.files" data-sort="Files" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Files<span data-sort-indicator></span></button></th>
              <th x-show="cols.size" data-sort="Size" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Size<span data-sort-indicator></span></button></th>
              <th x-show="cols.vsize" data-sort="VaultSize" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Storage Vault Size<span data-sort-indicator></span></button></th>
              <th x-show="cols.up" data-sort="Uploaded" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Uploaded<span data-sort-indicator></span></button></th>
              <th x-show="cols.down" data-sort="Downloaded" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Downloaded<span data-sort-indicator></span></button></th>
              <th x-show="cols.started" data-sort="Started" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Started<span data-sort-indicator></span></button></th>
              <th x-show="cols.ended" data-sort="Ended" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Ended<span data-sort-indicator></span></button></th>
              <th x-show="cols.dur" data-sort="Duration" class="px-4 py-3 text-left font-medium cursor-pointer"><button type="button" class="inline-flex items-center gap-1 hover:text-white">Duration<span data-sort-indicator></span></button></th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800"></tbody>
                  </table>
                </div>
      </div>
              <div class="px-4 py-2">
                <div id="global-jobs-pager" class="flex items-center gap-2 text-xs font-medium text-slate-400"></div>
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
