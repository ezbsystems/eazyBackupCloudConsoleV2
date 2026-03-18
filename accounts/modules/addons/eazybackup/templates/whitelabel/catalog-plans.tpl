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
                <p class="mt-1 text-sm text-slate-400">Build draft and published plan templates from your existing catalog products.</p>
              </div>
              <div class="flex items-center gap-3 shrink-0">
                <a href="{$modulelink}&a=ph-plan-export&format=csv" class="inline-flex items-center rounded-xl px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Export CSV</a>
                <a href="{$modulelink}&a=ph-plan-export&format=json" class="inline-flex items-center rounded-xl px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Export JSON</a>
                <button type="button" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90" @click="openCreate()">New Plan</button>
              </div>
            </div>
            <div class="p-6">
              <input type="hidden" id="eb-token" value="{$token}" />

              <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
                <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
                  <div class="text-xs font-medium text-slate-400">All Plans</div>
                  <div class="text-xl font-semibold text-slate-100 mt-1">{$plans|@count}</div>
                </div>
                <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
                  <div class="text-xs font-medium text-slate-400">Published</div>
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
                           active: 'Published',
                           draft: 'Draft',
                           archived: 'Archived',
                           all: 'All'
                         }[this.statusFilter] || 'Published';
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
                      <div x-show="entriesOpen" x-transition class="absolute left-0 mt-2 w-48 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
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
                      <div x-show="statusOpen" x-transition class="absolute left-0 mt-2 w-56 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
                        <template x-for="option in [
                          { value: 'active', label: 'Published' },
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-300">Published</span>
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
                              <div x-show="o" @click.outside="o=false" x-transition class="absolute right-0 z-50 mt-2 w-52 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl overflow-hidden p-1">
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; openEdit({$pl.id})">Edit Builder</button>
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; duplicatePlan({$pl.id})">Duplicate</button>
                                {if $plan_status == 'active'}
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; openAssign({$pl.id}, '{$pl.name|escape:'javascript'}', '{$plan_status|escape:'javascript'}')">Assign to Customer</button>
                                {/if}
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; openSubs({$pl.id})">View Subscriptions</button>
                                {if $plan_status == 'active'}
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-amber-300 hover:bg-slate-800/60 rounded-lg" @click="o=false; toggleStatus({$pl.id}, 'archived')">Archive</button>
                                {elseif $plan_status == 'draft' || $plan_status == 'archived'}
                                <button type="button" class="w-full text-left px-4 py-2 text-sm text-emerald-300 hover:bg-slate-800/60 rounded-lg" @click="o=false; toggleStatus({$pl.id}, 'active')">Publish</button>
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

              <div id="eb-plan-panel" class="hidden fixed inset-0 z-50">
                <div class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm" @click="closePanel()"></div>
                <div class="absolute inset-y-0 right-0 w-full max-w-7xl bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col">
                  <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
                    <div>
                      <h3 class="text-lg font-semibold text-slate-100" x-text="panelMode === 'create' ? 'Create Plan Template' : 'Edit Plan Template'"></h3>
                      <p class="mt-1 text-xs text-slate-400">Use existing published catalog prices to build a draft or published plan.</p>
                    </div>
                    <div class="flex items-center gap-3">
                      <span class="hidden sm:inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="currentPlanStatusClass()" x-text="currentPlanStatusLabel()"></span>
                      <button type="button" class="lg:hidden rounded-lg border border-slate-700 px-3 py-2 text-xs text-slate-200 hover:bg-slate-800" @click="mobileSummaryOpen = true">View Summary</button>
                      <button type="button" class="text-slate-400 hover:text-white" @click="closePanel()">&#10005;</button>
                    </div>
                  </div>

                  <div class="border-b border-slate-800 px-6 py-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                      <template x-for="stepNumber in [1,2,3,4]" :key="'step-' + stepNumber">
                        <button type="button" class="rounded-xl border px-4 py-3 text-left transition" :class="step >= stepNumber ? 'border-sky-500/40 bg-sky-500/10 text-white' : 'border-slate-700 bg-slate-900/70 text-slate-400'" @click="step = stepNumber <= step + 1 ? stepNumber : step">
                          <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Step <span x-text="stepNumber"></span></div>
                          <div class="mt-1 text-sm font-medium" x-text="['Plan Basics','Select Products','Configure Components','Review & Publish'][stepNumber - 1]"></div>
                        </button>
                      </template>
                    </div>
                  </div>

                  <div class="flex-1 min-h-0 overflow-hidden">
                    <div class="grid h-full lg:grid-cols-[minmax(0,1fr)_20rem]">
                      <div class="min-h-0 overflow-y-auto px-6 py-6">
                        <div x-show="step === 1" class="space-y-6">
                          <div class="rounded-2xl border border-slate-700 bg-slate-800/40 p-5">
                            <h4 class="text-base font-medium text-slate-100">Plan Basics</h4>
                            <p class="mt-1 text-sm text-slate-400">Set the plan name, trial period, billing interval, and currency before selecting catalog prices.</p>
                            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                              <label class="block md:col-span-2">
                                <span class="text-sm text-slate-400">Plan name</span>
                                <input x-model="planData.name" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" />
                              </label>
                              <label class="block md:col-span-2">
                                <span class="text-sm text-slate-400">Description</span>
                                <textarea x-model="planData.description" rows="3" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition"></textarea>
                              </label>
                              <label class="block">
                                <span class="text-sm text-slate-400">Billing interval</span>
                                <select x-model="planData.billing_interval" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
                                  <option value="month">Monthly</option>
                                  <option value="year">Yearly</option>
                                </select>
                              </label>
                              <label class="block">
                                <span class="text-sm text-slate-400">Currency</span>
                                <select x-model="planData.currency" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
                                  <option value="CAD">CAD</option>
                                  <option value="USD">USD</option>
                                  <option value="EUR">EUR</option>
                                  <option value="GBP">GBP</option>
                                  <option value="AUD">AUD</option>
                                </select>
                              </label>
                              <label class="block">
                                <span class="text-sm text-slate-400">Trial days</span>
                                <input x-model.number="planData.trial_days" type="number" min="0" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700" />
                              </label>
                              <label class="block">
                                <span class="text-sm text-slate-400">Status</span>
                                <select x-model="planData.status" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
                                  <option value="draft">Draft</option>
                                  <option value="active">Published</option>
                                  <option value="archived">Archived</option>
                                </select>
                              </label>
                            </div>
                          </div>
                        </div>

                        <div x-show="step === 2" class="space-y-6">
                          <div class="rounded-2xl border border-slate-700 bg-slate-800/40 p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                              <div>
                                <h4 class="text-base font-medium text-slate-100">Select Products</h4>
                                <p class="mt-1 text-sm text-slate-400">Choose published recurring prices that match this plan’s <span class="text-slate-200" x-text="planData.currency"></span> <span class="text-slate-500">/</span> <span class="text-slate-200" x-text="planData.billing_interval"></span> billing rules.</p>
                              </div>
                              <a :href="createProductUrl()" class="inline-flex items-center rounded-xl px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Manage Products</a>
                            </div>
                            <div class="mt-5 flex flex-col gap-3 md:flex-row">
                              <input x-model="catalogSearch" type="text" placeholder="Search products or prices" class="w-full md:flex-1 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                              <select x-model="catalogTypeFilter" class="w-full md:w-56 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                                <option value="all">All Types</option>
                                <option value="STORAGE_TB">Storage</option>
                                <option value="DEVICE_COUNT">Devices</option>
                                <option value="DISK_IMAGE">Disk Image</option>
                                <option value="HYPERV_VM">Hyper-V</option>
                                <option value="PROXMOX_VM">Proxmox</option>
                                <option value="VMWARE_VM">VMware</option>
                                <option value="M365_USER">Microsoft 365</option>
                                <option value="GENERIC">Generic Service</option>
                              </select>
                            </div>

                            <div class="mt-5 space-y-4" x-show="filteredCatalogProducts().length > 0">
                              <template x-for="product in filteredCatalogProducts()" :key="'catalog-product-' + product.id">
                                <div class="rounded-2xl border border-slate-700 bg-slate-950/40 p-4">
                                  <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                      <div class="flex flex-wrap items-center gap-2">
                                        <h5 class="text-sm font-semibold text-slate-100" x-text="product.name"></h5>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-slate-700 text-slate-200" x-text="productMetricLabel(product)"></span>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="product.active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-500/15 text-slate-400'" x-text="product.active ? 'Published product' : 'Draft product'"></span>
                                      </div>
                                      <p class="mt-1 text-xs text-slate-400" x-text="product.description || 'No description provided.'"></p>
                                    </div>
                                  </div>
                                  <div class="mt-4 overflow-hidden rounded-xl border border-slate-800">
                                    <table class="min-w-full divide-y divide-slate-800 text-xs">
                                      <thead class="bg-slate-900/80 text-slate-400">
                                        <tr>
                                          <th class="px-3 py-2 text-left font-medium">Price</th>
                                          <th class="px-3 py-2 text-left font-medium">Billing</th>
                                          <th class="px-3 py-2 text-left font-medium">Interval</th>
                                          <th class="px-3 py-2 text-left font-medium">Status</th>
                                          <th class="px-3 py-2 text-right font-medium">Action</th>
                                        </tr>
                                      </thead>
                                      <tbody class="divide-y divide-slate-800">
                                        <template x-for="price in visiblePricesForProduct(product)" :key="'catalog-price-' + price.id">
                                          <tr class="bg-slate-900/30">
                                            <td class="px-3 py-2">
                                              <div class="font-medium text-slate-100" x-text="price.name"></div>
                                              <div class="text-slate-400" x-text="componentPriceText({ unit_amount: price.unit_amount, currency: price.currency, interval: price.interval, unit_label: price.unit_label || '', metric_code: price.metric_code || product.base_metric_code, billing_type: price.billing_type })"></div>
                                            </td>
                                            <td class="px-3 py-2">
                                              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="priceBadgeClass(price)" x-text="billingLabel(price.billing_type)"></span>
                                            </td>
                                            <td class="px-3 py-2 text-slate-300" x-text="price.interval"></td>
                                            <td class="px-3 py-2">
                                              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="price.active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'" x-text="price.active ? 'Published price' : 'Archived legacy price'"></span>
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                              <button type="button" class="inline-flex items-center rounded-lg px-3 py-1.5 text-[11px] font-medium" :class="isSelectedPrice(price.id) ? 'bg-slate-700 text-slate-300 cursor-default' : 'bg-[rgb(var(--accent))] text-white hover:bg-[rgb(var(--accent))]/90'" :disabled="isSelectedPrice(price.id)" @click="addPriceToPlan(product.id, price.id)" x-text="isSelectedPrice(price.id) ? 'Added' : 'Add to Plan'"></button>
                                            </td>
                                          </tr>
                                        </template>
                                      </tbody>
                                    </table>
                                  </div>
                                </div>
                              </template>
                            </div>

                            <div x-show="filteredCatalogProducts().length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-700 bg-slate-950/30 px-6 py-10 text-center">
                              <div class="text-sm font-medium text-slate-200">No compatible products found</div>
                              <p class="mt-2 text-sm text-slate-400">Adjust the plan currency or billing interval, or create matching products and prices first.</p>
                              <a :href="createProductUrl()" class="mt-4 inline-flex items-center rounded-xl px-3 py-2 text-xs text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Open Catalog Products</a>
                            </div>
                          </div>
                        </div>

                        <div x-show="step === 3" class="space-y-6">
                          <div class="rounded-2xl border border-slate-700 bg-slate-800/40 p-5">
                            <h4 class="text-base font-medium text-slate-100">Configure Components</h4>
                            <p class="mt-1 text-sm text-slate-400">Set what is included in the plan and how usage above that included amount should be handled.</p>
                            <div class="mt-5 space-y-4" x-show="editComponents.length > 0">
                              <template x-for="(comp, ci) in editComponents" :key="'component-' + ci + '-' + comp.price_id">
                                <div class="rounded-2xl border border-slate-700 bg-slate-950/40 p-4">
                                  <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                      <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-semibold text-slate-100" x-text="comp.product_name || 'Catalog Product'"></div>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="componentBadgeClass(comp)" x-text="billingLabel(comp.billing_type)"></span>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-slate-700 text-slate-200" x-text="metricLabel(comp.metric_code)"></span>
                                        <span x-show="comp.is_legacy_attached" class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-amber-500/15 text-amber-300">Archived catalog price</span>
                                      </div>
                                      <div class="mt-1 text-xs text-slate-300" x-text="comp.price_name"></div>
                                      <div class="mt-1 text-xs text-slate-500" x-text="componentPriceText(comp)"></div>
                                    </div>
                                    <button type="button" class="inline-flex items-center justify-center rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-300 hover:bg-rose-500/20" @click="removeComponent(ci)">Remove</button>
                                  </div>

                                  <div x-show="legacyWarning(comp)" class="mt-3 rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-200" x-text="legacyWarning(comp)"></div>

                                  <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label class="block">
                                      <span class="text-xs text-slate-400">Included quantity</span>
                                      <input x-model.number="comp.default_qty" type="number" min="0" class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-800 text-xs text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600" />
                                      <p class="mt-2 text-[11px] text-slate-500">This plan includes <span class="text-slate-300" x-text="includedLabel(comp)"></span> before the overage rule is applied.</p>
                                    </label>
                                    <label class="block">
                                      <span class="text-xs text-slate-400">Overage behavior</span>
                                      <select x-model="comp.overage_mode" class="mt-1 w-full px-3 py-2 rounded-lg bg-slate-800 text-xs text-slate-100 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600">
                                        <option value="bill_all">Charge for all usage above included amount</option>
                                        <option value="cap_at_default">Do not bill usage above included amount</option>
                                      </select>
                                      <p class="mt-2 text-[11px] text-slate-500" x-text="overageExample(comp)"></p>
                                    </label>
                                  </div>
                                </div>
                              </template>
                            </div>
                            <div x-show="editComponents.length === 0" class="mt-5 rounded-2xl border border-dashed border-slate-700 bg-slate-950/30 px-6 py-10 text-center text-sm text-slate-400">
                              Add a price in the Select Products step to begin configuring included service levels.
                            </div>
                          </div>
                        </div>

                        <div x-show="step === 4" class="space-y-6">
                          <div class="rounded-2xl border border-slate-700 bg-slate-800/40 p-5">
                            <h4 class="text-base font-medium text-slate-100">Review &amp; Publish</h4>
                            <p class="mt-1 text-sm text-slate-400">Confirm what is included, how overage works, and whether this plan should stay as a draft or be published.</p>

                            <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                              <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4">
                                <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Plan</div>
                                <div class="mt-2 text-base font-semibold text-slate-100" x-text="planData.name || 'Untitled plan'"></div>
                                <div class="mt-1 text-sm text-slate-400" x-text="planData.description || 'No description provided.'"></div>
                              </div>
                              <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4">
                                <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Billing</div>
                                <div class="mt-2 text-base font-semibold text-slate-100"><span x-text="planData.currency"></span> <span class="text-slate-500">/</span> <span x-text="planData.billing_interval"></span></div>
                                <div class="mt-1 text-sm text-slate-400"><span x-text="planData.trial_days || 0"></span> day trial • <span x-text="currentPlanStatusLabel()"></span></div>
                              </div>
                            </div>

                            <div class="mt-5 space-y-3">
                              <template x-for="comp in sortedComponents()" :key="'review-' + comp.price_id + '-' + componentSortKey(comp)">
                                <div class="rounded-xl border border-slate-700 bg-slate-900/60 p-4">
                                  <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                      <div class="text-sm font-semibold text-slate-100" x-text="comp.product_name"></div>
                                      <div class="text-xs text-slate-300" x-text="comp.price_name"></div>
                                    </div>
                                    <div class="text-xs text-slate-400" x-text="componentPriceText(comp)"></div>
                                  </div>
                                  <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-slate-300">
                                    <div>
                                      <div class="text-slate-500">Included service</div>
                                      <div class="mt-1" x-text="includedLabel(comp)"></div>
                                    </div>
                                    <div>
                                      <div class="text-slate-500">Overage rule</div>
                                      <div class="mt-1" x-text="overageLabel(comp.overage_mode)"></div>
                                    </div>
                                  </div>
                                </div>
                              </template>
                            </div>

                            <div x-show="hasMeteredComponents()" class="mt-4 rounded-xl border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-xs text-sky-200">
                              Metered components are billed from usage records. The recurring base total below excludes any future metered overage.
                            </div>
                          </div>
                        </div>
                      </div>

                      <aside class="hidden lg:flex min-h-0 border-l border-slate-800 bg-slate-950/50 px-5 py-6">
                        <div class="w-full space-y-4">
                          <div>
                            <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Plan Summary</div>
                            <div class="mt-2 text-lg font-semibold text-slate-100" x-text="planData.name || 'Untitled plan'"></div>
                            <div class="mt-1 text-sm text-slate-400" x-text="planData.description || 'Build your plan from existing recurring catalog prices.'"></div>
                          </div>
                          <div class="rounded-2xl border border-slate-700 bg-slate-900/60 p-4">
                            <div class="text-xs text-slate-500">Billing Rules</div>
                            <div class="mt-2 text-sm text-slate-100"><span x-text="planData.currency"></span> • <span x-text="planData.billing_interval"></span></div>
                            <div class="mt-1 text-xs text-slate-400"><span x-text="planData.trial_days || 0"></span> day trial</div>
                            <div class="mt-2 inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium" :class="currentPlanStatusClass()" x-text="currentPlanStatusLabel()"></div>
                          </div>
                          <div class="rounded-2xl border border-slate-700 bg-slate-900/60 p-4">
                            <div class="flex items-center justify-between">
                              <div class="text-xs text-slate-500">Selected Components</div>
                              <div class="text-xs text-slate-400" x-text="editComponents.length + ' total'"></div>
                            </div>
                            <div class="mt-3 space-y-3" x-show="editComponents.length > 0">
                              <template x-for="comp in sortedComponents()" :key="'summary-' + comp.price_id + '-' + componentSortKey(comp)">
                                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                  <div class="text-xs font-medium text-slate-100" x-text="comp.product_name"></div>
                                  <div class="mt-1 text-[11px] text-slate-400" x-text="comp.price_name"></div>
                                  <div class="mt-2 flex items-center justify-between text-[11px] text-slate-300">
                                    <span x-text="includedLabel(comp) + ' included'"></span>
                                    <span x-text="componentPriceText(comp)"></span>
                                  </div>
                                </div>
                              </template>
                            </div>
                            <div x-show="editComponents.length === 0" class="mt-3 text-xs text-slate-500">No catalog prices selected yet.</div>
                          </div>
                          <div class="rounded-2xl border border-slate-700 bg-slate-900/60 p-4">
                            <div class="text-xs text-slate-500">Recurring Base Charges</div>
                            <div class="mt-2 text-lg font-semibold text-slate-100" x-text="reviewRecurringSummary()"></div>
                            <div class="mt-1 text-xs text-slate-400" x-show="hasMeteredComponents()">Metered overage charges apply separately based on usage.</div>
                          </div>
                        </div>
                      </aside>
                    </div>
                  </div>

                  <div class="border-t border-slate-800 px-6 py-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                      <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="closePanel()">Cancel</button>
                      <button type="button" x-show="step > 1" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="prevStep()">Back</button>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                      <button type="button" x-show="step < 4" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500" @click="nextStep()">Next</button>
                      <button type="button" x-show="step === 4" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-slate-100 border border-slate-700 bg-transparent hover:bg-slate-800" @click="savePlan('draft')" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Save Draft'"></button>
                      <button type="button" x-show="step === 4" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500" @click="savePlan('active')" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Publish'"></button>
                      <button type="button" x-show="step === 4 && panelMode === 'edit'" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-amber-200 border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20" @click="savePlan('archived')" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Archive'"></button>
                    </div>
                  </div>
                </div>

                <div x-show="mobileSummaryOpen" class="lg:hidden fixed inset-0 z-[60]">
                  <div class="absolute inset-0 bg-slate-950/80" @click="mobileSummaryOpen = false"></div>
                  <div class="absolute inset-x-0 bottom-0 rounded-t-3xl border-t border-slate-700 bg-slate-900 p-5 shadow-2xl max-h-[80vh] overflow-y-auto">
                    <div class="flex items-center justify-between">
                      <div class="text-sm font-semibold text-slate-100">Plan Summary</div>
                      <button type="button" class="text-slate-400 hover:text-white" @click="mobileSummaryOpen = false">&#10005;</button>
                    </div>
                    <div class="mt-4 space-y-4">
                      <div>
                        <div class="text-base font-semibold text-slate-100" x-text="planData.name || 'Untitled plan'"></div>
                        <div class="mt-1 text-sm text-slate-400" x-text="planData.description || 'Build your plan from existing recurring catalog prices.'"></div>
                      </div>
                      <div class="rounded-2xl border border-slate-700 bg-slate-950/50 p-4">
                        <div class="text-xs text-slate-500">Billing Rules</div>
                        <div class="mt-2 text-sm text-slate-100"><span x-text="planData.currency"></span> • <span x-text="planData.billing_interval"></span></div>
                        <div class="mt-1 text-xs text-slate-400"><span x-text="planData.trial_days || 0"></span> day trial</div>
                      </div>
                      <div class="rounded-2xl border border-slate-700 bg-slate-950/50 p-4">
                        <div class="flex items-center justify-between">
                          <div class="text-xs text-slate-500">Selected Components</div>
                          <div class="text-xs text-slate-400" x-text="editComponents.length + ' total'"></div>
                        </div>
                        <div class="mt-3 space-y-3" x-show="editComponents.length > 0">
                          <template x-for="comp in sortedComponents()" :key="'mobile-summary-' + comp.price_id + '-' + componentSortKey(comp)">
                            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                              <div class="text-xs font-medium text-slate-100" x-text="comp.product_name"></div>
                              <div class="mt-1 text-[11px] text-slate-400" x-text="comp.price_name"></div>
                              <div class="mt-2 text-[11px] text-slate-300" x-text="includedLabel(comp) + ' included • ' + componentPriceText(comp)"></div>
                            </div>
                          </template>
                        </div>
                        <div x-show="editComponents.length === 0" class="mt-3 text-xs text-slate-500">No catalog prices selected yet.</div>
                      </div>
                      <div class="rounded-2xl border border-slate-700 bg-slate-950/50 p-4">
                        <div class="text-xs text-slate-500">Recurring Base Charges</div>
                        <div class="mt-2 text-lg font-semibold text-slate-100" x-text="reviewRecurringSummary()"></div>
                        <div class="mt-1 text-xs text-slate-400" x-show="hasMeteredComponents()">Metered overage charges apply separately based on usage.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

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

<script type="application/json" id="eb-plan-catalog-json">{$catalog_products_json nofilter}</script>
<script type="application/json" id="eb-comet-accounts-json">[{foreach from=$comet_accounts item=ca name=ca_loop}{ldelim}"tenant_public_id":"{$ca.tenant_public_id|escape:'javascript'}","comet_username":"{$ca.comet_username|escape:'javascript'}","tenant_name":"{$ca.tenant_name|escape:'javascript'}"{rdelim}{if !$smarty.foreach.ca_loop.last},{/if}{/foreach}]</script>
<script src="modules/addons/eazybackup/assets/js/catalog-plans.js"></script>
