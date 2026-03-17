{* Partner Hub — Catalog: Plans *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='catalog-plans'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div x-data="planPageFactory({ modulelink: '{$modulelink|escape:'javascript'}', token: '{$token|escape:'javascript'}' })">
            <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
              <div>
                <h1 class="text-2xl font-semibold tracking-tight">Catalog - Plans</h1>
                <p class="mt-1 text-sm text-slate-400">Manage plan templates and assign them to customers.</p>
              </div>
              <div class="flex items-center gap-3 shrink-0">
                <a href="{$modulelink}&a=ph-plan-export&format=csv" class="inline-flex items-center rounded-xl px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Export CSV</a>
                <a href="{$modulelink}&a=ph-plan-export&format=json" class="inline-flex items-center rounded-xl px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Export JSON</a>
                <button type="button" class="inline-flex items-center rounded-xl px-4 py-2 text-sm text-emerald-300 ring-1 ring-emerald-500/30 bg-emerald-500/10 hover:bg-emerald-500/20" onclick="window.ebWizard && window.ebWizard.open()">Quick Plan</button>
                <button type="button" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90" @click="openCreate()">New Plan</button>
              </div>
            </div>
            <div class="p-6">

    {* Counter cards *}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs font-medium text-slate-400">All Plans</div>
        <div class="text-xl font-semibold text-slate-100 mt-1">{$plans|@count}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs font-medium text-slate-400">Active</div>
        <div class="text-xl font-semibold text-emerald-400 mt-1">{assign var='count_active_plans' value=0}{foreach from=$plans item=pl}{if $pl.status == 'active' || (!$pl.status && $pl.active)}{assign var='count_active_plans' value=$count_active_plans+1}{/if}{/foreach}{$count_active_plans}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs font-medium text-slate-400">Draft</div>
        <div class="text-xl font-semibold text-amber-400 mt-1">{assign var='count_draft_plans' value=0}{foreach from=$plans item=pl}{if $pl.status == 'draft'}{assign var='count_draft_plans' value=$count_draft_plans+1}{/if}{/foreach}{$count_draft_plans}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs font-medium text-slate-400">Archived</div>
        <div class="text-xl font-semibold text-slate-400 mt-1">{assign var='count_archived_plans' value=0}{foreach from=$plans item=pl}{if $pl.status == 'archived'}{assign var='count_archived_plans' value=$count_archived_plans+1}{/if}{/foreach}{$count_archived_plans}</div>
      </div>
    </div>

    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="p-4"
           x-data="{
             entriesOpen: false,
             statusOpen: false,
             search: '',
             entriesPerPage: 25,
             currentPage: 1,
             sortKey: 'name',
             sortDirection: 'asc',
             statusFilter: 'active',
             filteredCount: 0,
             rows: [],
             init() {
               this.rows = Array.from(this.$refs.tbody.querySelectorAll('tr[data-row]'));
               this.$watch('search', () => { this.currentPage = 1; this.refreshRows(); });
               this.refreshRows();
             },
             setEntries(size) {
               this.entriesPerPage = Number(size) || 25;
               this.currentPage = 1;
               this.refreshRows();
             },
             setStatus(status) {
               this.statusFilter = status;
               this.currentPage = 1;
               this.refreshRows();
             },
             statusLabel() {
               return {
                 active: 'Active',
                 draft: 'Draft',
                 archived: 'Archived',
                 all: 'All'
               }[this.statusFilter] || 'Active';
             },
             setSort(key) {
               if (key === 'actions') return;
               if (this.sortKey === key) {
                 this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
               } else {
                 this.sortKey = key;
                 this.sortDirection = 'asc';
               }
               this.refreshRows();
             },
             sortIndicator(key) {
               if (this.sortKey !== key || key === 'actions') return '';
               return this.sortDirection === 'asc' ? '↑' : '↓';
             },
             sortValue(row, key) {
               return String(row.getAttribute('data-' + key) || '').toLowerCase();
             },
             compareRows(left, right) {
               const a = this.sortValue(left, this.sortKey);
               const b = this.sortValue(right, this.sortKey);
               if (a < b) return this.sortDirection === 'asc' ? -1 : 1;
               if (a > b) return this.sortDirection === 'asc' ? 1 : -1;
               return 0;
             },
             refreshRows() {
               const query = this.search.trim().toLowerCase();
               const filtered = this.rows.filter((row) => {
                 const status = row.getAttribute('data-status') || '';
                 const matchesStatus = this.statusFilter === 'all' ? true : status === this.statusFilter;
                 const matchesQuery = !query ? true : (row.textContent || '').toLowerCase().includes(query);
                 return matchesStatus && matchesQuery;
               });
               filtered.sort((a, b) => this.compareRows(a, b));
               filtered.forEach((row) => this.$refs.tbody.appendChild(row));
               this.filteredCount = filtered.length;
               const pages = this.totalPages();
               if (this.currentPage > pages) this.currentPage = pages;
               const start = (this.currentPage - 1) * this.entriesPerPage;
               const end = start + this.entriesPerPage;
               const visibleRows = new Set(filtered.slice(start, end));
               this.rows.forEach((row) => {
                 row.style.display = visibleRows.has(row) ? '' : 'none';
               });
               if (this.$refs.noResults) {
                 this.$refs.noResults.style.display = filtered.length === 0 ? '' : 'none';
               }
             },
             totalPages() {
               return Math.max(1, Math.ceil(this.filteredCount / this.entriesPerPage));
             },
             pageSummary() {
               if (this.filteredCount === 0) return 'Showing 0-0 of 0 plans';
               const start = (this.currentPage - 1) * this.entriesPerPage + 1;
               const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
               return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' plans';
             },
             prevPage() {
               if (this.currentPage <= 1) return;
               this.currentPage -= 1;
               this.refreshRows();
             },
             nextPage() {
               if (this.currentPage >= this.totalPages()) return;
               this.currentPage += 1;
               this.refreshRows();
             }
           }"
           x-init="init()">
        <div class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
          <div class="relative" @click.away="entriesOpen = false">
            <button type="button" @click="entriesOpen = !entriesOpen" class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
              <span x-text="'Show ' + entriesPerPage"></span>
              <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
            <div x-show="entriesOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
              <template x-for="size in [10,25,50,100]" :key="'plans-entries-' + size">
                <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="entriesPerPage === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
              </template>
            </div>
          </div>
          <div class="relative" @click.away="statusOpen = false">
            <button type="button" @click="statusOpen = !statusOpen" class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
              <span x-text="'Status: ' + statusLabel()"></span>
              <svg class="w-4 h-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
            <div x-show="statusOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 mt-2 w-56 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
              <template x-for="option in [
                { value: 'active', label: 'Active' },
                { value: 'draft', label: 'Draft' },
                { value: 'archived', label: 'Archived' },
                { value: 'all', label: 'All' }
              ]" :key="'plans-status-' + option.value">
                <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="statusFilter === option.value ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
              </template>
            </div>
          </div>
          <div class="flex-1"></div>
          <input type="text" x-model="search" placeholder="Search plans, currency, or interval" class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-800">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/80 text-slate-300">
              <tr>
                <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('name')">Name <span x-text="sortIndicator('name')"></span></button></th>
                <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('components')">Components <span x-text="sortIndicator('components')"></span></button></th>
                <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('currency')">Currency <span x-text="sortIndicator('currency')"></span></button></th>
                <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('interval')">Interval <span x-text="sortIndicator('interval')"></span></button></th>
                <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('subs')">Active Subs <span x-text="sortIndicator('subs')"></span></button></th>
                <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button></th>
                <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('created')">Created <span x-text="sortIndicator('created')"></span></button></th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody x-ref="tbody" class="divide-y divide-slate-800">
              {foreach from=$plans item=pl}
              {assign var='comp_count' value=0}
              {foreach from=$components item=pc}{if $pc.plan_id == $pl.id}{assign var='comp_count' value=$comp_count+1}{/if}{/foreach}
              {assign var='plan_status' value=$pl.status|default:''}
              {if $plan_status eq ''}
                {if $pl.active}
                  {assign var='plan_status' value='active'}
                {else}
                  {assign var='plan_status' value='draft'}
                {/if}
              {/if}
              <tr class="hover:bg-slate-800/30 transition"
                  data-row="plan"
                  data-name="{$pl.name|escape}"
                  data-components="{$comp_count|escape}"
                  data-currency="{$pl.currency|default:'CAD'|escape}"
                  data-interval="{$pl.billing_interval|default:'month'|escape}"
                  data-subs="{$pl.active_subs|default:0|escape}"
                  data-status="{$plan_status|escape}"
                  data-created="{$pl.created_at|date_format:'%Y-%m-%d'|escape}">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-100">{$pl.name|escape}</div>
                  {if $pl.description}<div class="text-xs text-slate-400 mt-0.5 truncate max-w-xs">{$pl.description|escape|truncate:60}</div>{/if}
                  <div class="text-xs text-slate-500 mt-0.5">v{$pl.version}{if $pl.trial_days} &middot; {$pl.trial_days}-day trial{/if}</div>
                </td>
                <td class="px-4 py-3"><span class="text-slate-300">{$comp_count}</span></td>
                <td class="px-4 py-3 text-slate-300">{$pl.currency|default:'CAD'}</td>
                <td class="px-4 py-3 text-slate-300">{$pl.billing_interval|default:'month'}</td>
                <td class="px-4 py-3">{if $pl.active_subs > 0}<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-300">{$pl.active_subs}</span>{else}<span class="text-slate-500">0</span>{/if}</td>
                <td class="px-4 py-3">
                  {if $plan_status == 'active'}
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-300">Active</span>
                  {elseif $plan_status == 'draft'}
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-300">Draft</span>
                  {elseif $plan_status == 'archived'}
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-500/15 text-slate-400">Archived</span>
                  {else}
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">{$plan_status|escape}</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-xs text-slate-400">{$pl.created_at|date_format:'%Y-%m-%d'}</td>
                <td class="px-4 py-3 text-right" x-data="{ o:false }">
                  <div class="relative inline-block text-left">
                    <button type="button" class="px-3 py-1.5 text-xs bg-slate-700 rounded text-white hover:bg-slate-600 cursor-pointer" @click="o=!o">&ctdot;</button>
                    <div x-show="o" @click.outside="o=false" x-transition class="absolute right-0 z-50 mt-2 w-48 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl overflow-hidden p-1">
                      <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; openEdit({$pl.id})">Edit</button>
                      <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; duplicatePlan({$pl.id})">Duplicate</button>
                      <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; openAssign({$pl.id}, '{$pl.name|escape:'javascript'}')">Assign to Customer</button>
                      <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; openSubs({$pl.id})">View Subscriptions</button>
                      {if $plan_status == 'active'}
                      <button type="button" class="w-full text-left px-4 py-2 text-sm text-amber-300 hover:bg-slate-800/60 rounded-lg" @click="o=false; toggleStatus({$pl.id}, 'archived')">Archive</button>
                      {else}
                      <button type="button" class="w-full text-left px-4 py-2 text-sm text-emerald-300 hover:bg-slate-800/60 rounded-lg" @click="o=false; toggleStatus({$pl.id}, 'active')">Activate</button>
                      {/if}
                      <button type="button" class="w-full text-left px-4 py-2 text-sm text-rose-300 hover:bg-slate-800/60 rounded-lg" @click="o=false; deletePlan({$pl.id})">Delete</button>
                    </div>
                  </div>
                </td>
              </tr>
              {foreachelse}
              <tr>
                <td colspan="8" class="px-4 py-8 text-center text-slate-500">No plan templates yet. Create one to get started.</td>
              </tr>
              {/foreach}
              {if $plans|@count > 0}
              <tr x-ref="noResults" style="display: none;">
                <td colspan="8" class="px-4 py-8 text-center text-slate-500">No plan templates found.</td>
              </tr>
              {/if}
            </tbody>
          </table>
        </div>
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
          <div x-text="pageSummary()"></div>
          <div class="flex items-center gap-2">
            <button type="button" @click="prevPage()" :disabled="currentPage <= 1" class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">Prev</button>
            <span class="text-slate-300" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
            <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages()" class="px-3 py-1.5 rounded border border-slate-700 bg-slate-900/70 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
          </div>
        </div>
      </div>
    </section>

    {* ====== Plan Slide-Over Editor ====== *}
    <div id="eb-plan-panel" class="hidden fixed inset-0 z-50">
      <div class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm" @click="closePanel()"></div>
      <div class="absolute inset-y-0 right-0 w-full max-w-3xl bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
          <h3 class="text-lg font-semibold text-slate-100" x-text="panelMode==='create' ? 'Create Plan Template' : 'Edit Plan Template'"></h3>
          <button type="button" class="text-slate-400 hover:text-white" @click="closePanel()">&#10005;</button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 text-sm">

          {* Plan metadata *}
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block md:col-span-2"><span class="text-sm text-slate-400">Plan name</span>
              <input x-model="planData.name" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" />
            </label>
            <label class="block md:col-span-2"><span class="text-sm text-slate-400">Description</span>
              <textarea x-model="planData.description" rows="2" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition"></textarea>
            </label>
            <label class="block"><span class="text-sm text-slate-400">Billing interval</span>
              <select x-model="planData.billing_interval" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
                <option value="month">Monthly</option>
                <option value="year">Yearly</option>
              </select>
            </label>
            <label class="block"><span class="text-sm text-slate-400">Currency</span>
              <select x-model="planData.currency" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
                <option value="CAD">CAD</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
                <option value="AUD">AUD</option>
              </select>
            </label>
            <label class="block"><span class="text-sm text-slate-400">Trial period</span>
              <div class="mt-2 flex items-center gap-3">
                <input x-model.number="planData.trial_days" type="number" min="0" class="w-24 px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700" />
                <span class="text-slate-400 text-xs">days (0 = no trial)</span>
              </div>
            </label>
          </div>

          {* Components section *}
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="mb-3 flex items-center justify-between">
              <h4 class="text-sm font-medium text-slate-100">Components</h4>
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700" @click="addComponent()">Add component</button>
            </div>

            <template x-if="editComponents.length===0"><div class="text-xs text-slate-400">No components yet. Add product prices as components of this plan.</div></template>

            <div class="space-y-3">
              <template x-for="(comp, ci) in editComponents" :key="'comp-'+ci">
                <div class="rounded-lg border border-slate-700 bg-slate-950/40 p-3">
                  <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex-1">
                      <label class="block"><span class="text-xs text-slate-400">Product price</span>
                        <select x-model="comp.price_id" class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-800 text-xs text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600">
                          <option value="">Select a price&hellip;</option>
                          {foreach from=$prices item=pr}
                          <option value="{$pr.id}">{$pr.name|escape} &mdash; {$pr.kind|escape} &mdash; {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}{if $pr.unit_label} / {$pr.unit_label|escape}{/if}</option>
                          {/foreach}
                        </select>
                      </label>
                    </div>
                    <button type="button" class="text-rose-400 hover:text-rose-300 text-xs mt-4" @click="removeComponent(ci)">&times; Remove</button>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <label class="block"><span class="text-xs text-slate-400">Included quantity</span>
                      <input x-model.number="comp.default_qty" type="number" min="0" class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-800 text-xs text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600" />
                    </label>
                    <label class="block"><span class="text-xs text-slate-400">Overage mode</span>
                      <select x-model="comp.overage_mode" class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-800 text-xs text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600">
                        <option value="bill_all">Bill all usage</option>
                        <option value="cap_at_default">Cap at included quantity</option>
                      </select>
                    </label>
                  </div>
                </div>
              </template>
            </div>
          </div>

          {* Pricing preview *}
          <template x-if="editComponents.length > 0">
            <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
              <h4 class="text-sm font-medium text-slate-100 mb-2">Pricing Preview</h4>
              <div class="space-y-1 text-xs">
                <template x-for="(comp, ci) in editComponents" :key="'preview-'+ci">
                  <div class="flex items-center justify-between text-slate-300">
                    <span x-text="getPriceName(comp.price_id) || ('Component ' + (ci+1))"></span>
                    <span x-text="formatComponentPrice(comp)"></span>
                  </div>
                </template>
                <div class="border-t border-slate-700 pt-2 mt-2 flex items-center justify-between font-medium text-slate-100">
                  <span>Estimated total</span>
                  <span x-text="formatTotalPreview()"></span>
                </div>
              </div>
            </div>
          </template>

        </div>
        {* Footer *}
        <div class="border-t border-slate-800 px-6 py-5 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="closePanel()">Cancel</button>
          <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click="savePlan()" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Save'"></button>
        </div>
      </div>
    </div>

    {* ====== Assign Plan Modal ====== *}
    <div id="eb-assign-plan-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm">
      <div class="relative w-full max-w-2xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-700">
          <h3 class="text-lg font-semibold text-slate-100">Assign Plan to Customer</h3>
          <button type="button" class="text-slate-400 hover:text-white" @click="closeAssign()">&#10005;</button>
        </div>
        <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="md:col-span-2">
            <div class="text-xs text-slate-400 mb-1">Plan</div>
            <div class="text-sm text-slate-100 font-medium" x-text="assignPlanName"></div>
          </div>
          <label class="block"><span class="text-sm text-slate-400">Customer</span>
            <select x-model="assignData.tenant_id" @change="onTenantChange()" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
              <option value="">Select customer&hellip;</option>
              {foreach from=$tenants item=c}
              <option value="{$c.public_id|escape}">{$c.name|default:$c.public_id|escape}</option>
              {/foreach}
            </select>
          </label>
          <label class="block"><span class="text-sm text-slate-400">eazyBackup User</span>
            <select x-model="assignData.comet_user_id" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
              <option value="">Select user&hellip;</option>
              <template x-for="u in filteredCometAccounts" :key="u.comet_username">
                <option :value="u.comet_username" x-text="u.comet_username"></option>
              </template>
            </select>
          </label>
          <label class="block"><span class="text-sm text-slate-400">Application fee %</span>
            <input x-model.number="assignData.application_fee_percent" type="number" step="0.01" min="0" max="100" placeholder="e.g. 10.0" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700" />
          </label>
          <div class="md:col-span-2 flex items-center justify-end gap-3 mt-2">
            <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="closeAssign()">Cancel</button>
            <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click="submitAssign()" :disabled="isSaving">Create Subscription</button>
          </div>
        </div>
      </div>
    </div>

    {* ====== Subscriptions Modal ====== *}
    <div id="eb-subs-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm">
      <div class="relative w-full max-w-3xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl max-h-[80vh] flex flex-col">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-700">
          <h3 class="text-lg font-semibold text-slate-100">Active Subscriptions</h3>
          <button type="button" class="text-slate-400 hover:text-white" @click="closeSubs()">&#10005;</button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4">
          <template x-if="subsLoading"><div class="text-center py-8 text-slate-400">Loading...</div></template>
          <template x-if="!subsLoading && subscriptions.length===0"><div class="text-center py-8 text-slate-500">No active subscriptions.</div></template>
          <template x-if="!subsLoading && subscriptions.length > 0">
            <table class="w-full text-xs">
              <thead><tr class="text-slate-400"><th class="px-3 py-2 text-left">Tenant</th><th class="px-3 py-2 text-left">eazyBackup User</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Created</th><th class="px-3 py-2 text-right">Actions</th></tr></thead>
              <tbody>
                <template x-for="sub in subscriptions" :key="sub.id">
                  <tr class="border-t border-slate-800">
                    <td class="px-3 py-2 text-slate-300" x-text="sub.tenant_name || sub.tenant_public_id || 'Tenant'"></td>
                    <td class="px-3 py-2 text-slate-300" x-text="sub.comet_user_id"></td>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs" :class="sub.status==='active' ? 'bg-emerald-500/15 text-emerald-300' : (sub.status==='trialing' ? 'bg-sky-500/15 text-sky-300' : 'bg-slate-500/15 text-slate-400')" x-text="sub.status"></span></td>
                    <td class="px-3 py-2 text-slate-400" x-text="sub.created_at ? sub.created_at.substring(0,10) : ''"></td>
                    <td class="px-3 py-2 text-right">
                      <button type="button" x-show="sub.status==='active' || sub.status==='trialing'" class="text-xs text-rose-400 hover:text-rose-300" @click="cancelSubscription(sub.id)">Cancel</button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </template>
        </div>
      </div>
    </div>

            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</div>

{* ====== Quick Plan Wizard ====== *}
<div id="eb-wizard-panel" class="hidden fixed inset-0 z-50" x-data="planWizardFactory({ modulelink: '{$modulelink|escape:'javascript'}', token: '{$token|escape:'javascript'}', currency: '{$msp.default_currency|default:'CAD'|escape:'javascript'}' })" x-init="window.ebWizard = $data">
  <div class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm" @click="close()"></div>
  <div class="absolute inset-y-0 right-0 w-full max-w-2xl bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col">
    <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
      <h3 class="text-lg font-semibold text-slate-100">Quick Plan Wizard</h3>
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-1">
          <template x-for="s in [1,2,3,4]" :key="s">
            <div class="w-2.5 h-2.5 rounded-full" :class="step >= s ? 'bg-sky-500' : 'bg-slate-700'"></div>
          </template>
        </div>
        <button type="button" class="text-slate-400 hover:text-white" @click="close()">&#10005;</button>
      </div>
    </div>
    <div class="flex-1 overflow-y-auto px-6 py-6 text-sm">

      {* Step 1: Choose type *}
      <div x-show="step===1" class="space-y-4">
        <h4 class="text-base font-medium text-slate-100">What type of plan?</h4>
        <div class="grid grid-cols-1 gap-3">
          <button type="button" @click="selectType('cloud_backup')" class="rounded-xl border border-slate-700 bg-slate-800/50 p-5 text-left hover:bg-slate-800 transition">
            <div class="text-sm font-medium text-slate-100">eazyBackup Cloud Backup</div>
            <div class="text-xs text-slate-400 mt-1">Storage + devices + optional add-ons (Disk Image, Hyper-V, VMware, M365)</div>
          </button>
          <button type="button" @click="selectType('object_storage')" class="rounded-xl border border-slate-700 bg-slate-800/50 p-5 text-left hover:bg-slate-800 transition">
            <div class="text-sm font-medium text-slate-100">e3 Object Storage</div>
            <div class="text-xs text-slate-400 mt-1">Metered object storage billing (per GiB)</div>
          </button>
          <button type="button" @click="selectType('custom_service')" class="rounded-xl border border-slate-700 bg-slate-800/50 p-5 text-left hover:bg-slate-800 transition">
            <div class="text-sm font-medium text-slate-100">Custom Service</div>
            <div class="text-xs text-slate-400 mt-1">IT support, antivirus, consulting, or any recurring service</div>
          </button>
        </div>
      </div>

      {* Step 2: Configure resources *}
      <div x-show="step===2" class="space-y-4">
        <h4 class="text-base font-medium text-slate-100" x-text="isCustomService() ? 'Configure your service' : 'Configure included resources'"></h4>
        <template x-for="(r, ri) in resources" :key="r.key">
          <div class="rounded-lg border p-4" :class="r.enabled ? 'border-sky-500/40 bg-sky-500/5' : 'border-slate-700 bg-slate-800/30'">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <input type="checkbox" x-model="r.enabled" :disabled="r.required" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-600" />
                <span class="font-medium text-slate-100" x-text="stepLabel(r.key)"></span>
              </div>
              <span class="text-xs text-slate-400" x-text="r.billingType === 'metered' ? 'Metered' : 'Per-unit'"></span>
            </div>
            <div x-show="r.enabled" class="grid grid-cols-2 gap-3 mt-2">
              <label class="block"><span class="text-xs text-slate-400" x-text="r.billingType === 'metered' ? 'Included amount' : 'Default quantity'"></span>
                <input x-model.number="r.qty" type="number" min="0" class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-800 text-xs text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600" />
              </label>
              <div>
                <span class="text-xs text-slate-400">Unit</span>
                <div class="mt-1 px-3 py-2 rounded-lg bg-slate-800/60 text-xs text-slate-400 border border-slate-700" x-text="r.unit"></div>
              </div>
            </div>
          </div>
        </template>
      </div>

      {* Step 3: Set pricing *}
      <div x-show="step===3" class="space-y-4">
        <h4 class="text-base font-medium text-slate-100">Set pricing</h4>
        <template x-for="(r, ri) in resources" :key="'price-'+r.key">
          <div x-show="r.enabled" class="rounded-lg border border-slate-700 bg-slate-800/30 p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="font-medium text-slate-100" x-text="stepLabel(r.key)"></span>
              <span class="text-xs text-slate-400" x-text="r.billingType === 'metered' ? 'per ' + r.unit : 'per ' + r.unit"></span>
            </div>
            <div class="flex items-stretch rounded-lg overflow-hidden bg-slate-800 border border-slate-700">
              <span class="shrink-0 px-3 py-2.5 text-slate-400 select-none">$</span>
              <input x-model.number="r.amount" type="number" step="0.01" min="0" class="flex-1 min-w-0 bg-transparent text-slate-100 focus:outline-none px-3 py-2.5 text-sm" />
              <span class="shrink-0 px-3 py-2.5 text-slate-400 border-l border-slate-700 text-xs" x-text="'{$msp.default_currency|default:'CAD'}'"></span>
            </div>
          </div>
        </template>
        <label class="block"><span class="text-sm text-slate-400">Billing interval</span>
          <select x-model="billingInterval" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600">
            <option value="month">Monthly</option>
            <option value="year">Yearly</option>
          </select>
        </label>
      </div>

      {* Step 4: Review *}
      <div x-show="step===4" class="space-y-4">
        <h4 class="text-base font-medium text-slate-100">Review &amp; Publish</h4>
        <div class="space-y-3">
          <label class="block"><span class="text-sm text-slate-400">Plan name</span>
            <input x-model="planName" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600" />
          </label>
          <label class="block"><span class="text-sm text-slate-400">Description (optional)</span>
            <textarea x-model="planDescription" rows="2" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600"></textarea>
          </label>
          <label class="block"><span class="text-sm text-slate-400">Trial days</span>
            <input x-model.number="trialDays" type="number" min="0" class="mt-2 w-24 px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600" />
          </label>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4 mt-4">
          <h5 class="text-sm font-medium text-slate-100 mb-2">Summary</h5>
          <div class="space-y-1 text-xs">
            <template x-for="r in enabledResources()" :key="'sum-'+r.key">
              <div class="flex items-center justify-between text-slate-300">
                <span x-text="stepLabel(r.key)"></span>
                <span x-text="'$' + (r.amount || 0).toFixed(2) + ' / ' + r.unit + (r.billingType !== 'metered' && r.qty > 1 ? ' x ' + r.qty : '')"></span>
              </div>
            </template>
            <div class="border-t border-slate-700 pt-2 mt-2 flex items-center justify-between font-medium text-slate-100">
              <span>Estimated total</span>
              <span x-text="totalPreview()"></span>
            </div>
          </div>
        </div>
      </div>

    </div>
    {* Wizard footer *}
    <div class="border-t border-slate-800 px-6 py-5 flex items-center justify-between">
      <button type="button" x-show="step > 1" @click="prevStep()" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm">&larr; Back</button>
      <div x-show="step <= 1"></div>
      <div class="flex items-center gap-3">
        <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="close()">Cancel</button>
        <button type="button" x-show="step < 4" @click="nextStep()" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500">Next &rarr;</button>
        <button type="button" x-show="step===4" @click="publish()" :disabled="isSaving" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500" x-text="isSaving ? 'Creating...' : 'Create Plan'"></button>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="eb-comet-accounts-json">[{foreach from=$comet_accounts item=ca name=ca_loop}{ldelim}"tenant_public_id":"{$ca.tenant_public_id|escape:'javascript'}","comet_username":"{$ca.comet_username|escape:'javascript'}","tenant_name":"{$ca.tenant_name|escape:'javascript'}"{rdelim}{if !$smarty.foreach.ca_loop.last},{/if}{/foreach}]</script>
<script src="modules/addons/eazybackup/assets/js/catalog-plans-wizard.js"></script>
<script src="modules/addons/eazybackup/assets/js/catalog-plans.js"></script>
