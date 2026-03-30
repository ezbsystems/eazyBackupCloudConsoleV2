{* Partner Hub — Catalog: Plans *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
          <div x-data="planPageFactory({ modulelink: '{$modulelink|escape:'javascript'}', token: '{$token|escape:'javascript'}' })">
            <div class="flex items-center justify-between border-b border-[var(--eb-border-subtle)] px-6 py-4">
              <div>
                <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Catalog - Plans</h1>
                <p class="eb-page-description mt-1">Build draft and published plan templates from your existing catalog products.</p>
              </div>
              <div class="flex shrink-0 items-center gap-3">
                <a href="{$modulelink}&a=ph-plan-export&format=csv" class="eb-btn eb-btn-outline eb-btn-xs">Export CSV</a>
                <a href="{$modulelink}&a=ph-plan-export&format=json" class="eb-btn eb-btn-outline eb-btn-xs">Export JSON</a>
                <button type="button" class="eb-btn eb-btn-orange eb-btn-md" @click="openCreate()">New Plan</button>
              </div>
            </div>
            <div class="p-6">
              <input type="hidden" id="eb-token" value="{$token}" />

              <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="eb-card !p-4">
                  <div class="eb-stat-label">All Plans</div>
                  <div class="eb-stat-value !mt-1 !text-xl">{$plans|@count}</div>
                </div>
                <div class="eb-card !p-4">
                  <div class="eb-stat-label">Published</div>
                  <div class="mt-1 text-xl font-semibold text-[var(--eb-success-text)]">{assign var='count_active_plans' value=0}{foreach from=$plans item=pl}{if $pl.status == 'active' || (!$pl.status && $pl.active)}{assign var='count_active_plans' value=$count_active_plans+1}{/if}{/foreach}{$count_active_plans}</div>
                </div>
                <div class="eb-card !p-4">
                  <div class="eb-stat-label">Draft</div>
                  <div class="mt-1 text-xl font-semibold text-[var(--eb-warning-text)]">{assign var='count_draft_plans' value=0}{foreach from=$plans item=pl}{if $pl.status == 'draft'}{assign var='count_draft_plans' value=$count_draft_plans+1}{/if}{/foreach}{$count_draft_plans}</div>
                </div>
                <div class="eb-card !p-4">
                  <div class="eb-stat-label">Archived</div>
                  <div class="mt-1 text-xl font-semibold text-[var(--eb-text-muted)]">{assign var='count_archived_plans' value=0}{foreach from=$plans item=pl}{if $pl.status == 'archived'}{assign var='count_archived_plans' value=$count_archived_plans+1}{/if}{/foreach}{$count_archived_plans}</div>
                </div>
              </div>

              <section class="eb-card-raised !p-0">
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
                  <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center">
                    <div class="relative" @click.away="entriesOpen = false">
                      <button type="button" @click="entriesOpen = !entriesOpen" class="eb-btn eb-btn-outline eb-btn-sm inline-flex items-center gap-2">
                        <span x-text="'Show ' + entriesPerPage"></span>
                        <svg class="h-4 w-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                      </button>
                      <div x-show="entriesOpen" x-transition class="absolute left-0 z-50 mt-2 w-48 overflow-hidden rounded-[var(--eb-radius-xl)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]" style="display: none;">
                        <template x-for="size in [10,25,50,100]" :key="'plans-entries-' + size">
                          <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="entriesPerPage === size ? 'bg-[var(--eb-bg-hover)] text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-secondary)] hover:bg-[var(--eb-bg-hover)]'" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
                        </template>
                      </div>
                    </div>
                    <div class="relative" @click.away="statusOpen = false">
                      <button type="button" @click="statusOpen = !statusOpen" class="eb-btn eb-btn-outline eb-btn-sm inline-flex items-center gap-2">
                        <span x-text="'Status: ' + statusLabel()"></span>
                        <svg class="h-4 w-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                      </button>
                      <div x-show="statusOpen" x-transition class="absolute left-0 z-50 mt-2 w-56 overflow-hidden rounded-[var(--eb-radius-xl)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]" style="display: none;">
                        <template x-for="option in [
                          { value: 'active', label: 'Published' },
                          { value: 'draft', label: 'Draft' },
                          { value: 'archived', label: 'Archived' },
                          { value: 'all', label: 'All' }
                        ]" :key="'plans-status-' + option.value">
                          <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="statusFilter === option.value ? 'bg-[var(--eb-bg-hover)] text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-secondary)] hover:bg-[var(--eb-bg-hover)]'" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
                        </template>
                      </div>
                    </div>
                    <div class="flex-1"></div>
                    <input type="text" x-model="search" placeholder="Search plans, currency, or interval" class="eb-toolbar-search w-full xl:w-80 rounded-full">
                  </div>

                  <div class="overflow-x-auto rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-subtle)]">
                    <table class="min-w-full divide-y divide-[var(--eb-border-subtle)] text-sm">
                      <thead class="bg-[var(--eb-bg-surface)] text-[var(--eb-text-secondary)]">
                        <tr>
                          <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-[var(--eb-text-primary)]" @click="setSort('name')">Name <span x-text="sortIndicator('name')"></span></button></th>
                          <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-[var(--eb-text-primary)]" @click="setSort('components')">Components <span x-text="sortIndicator('components')"></span></button></th>
                          <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-[var(--eb-text-primary)]" @click="setSort('currency')">Currency <span x-text="sortIndicator('currency')"></span></button></th>
                          <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-[var(--eb-text-primary)]" @click="setSort('interval')">Interval <span x-text="sortIndicator('interval')"></span></button></th>
                          <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-[var(--eb-text-primary)]" @click="setSort('subs')">Active Subs <span x-text="sortIndicator('subs')"></span></button></th>
                          <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-[var(--eb-text-primary)]" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button></th>
                          <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-[var(--eb-text-primary)]" @click="setSort('created')">Created <span x-text="sortIndicator('created')"></span></button></th>
                          <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                      </thead>
                      <tbody x-ref="tbody" class="divide-y divide-[var(--eb-border-subtle)]">
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
                        <tr class="transition hover:bg-[var(--eb-bg-hover)]"
                            data-row="plan"
                            data-name="{$pl.name|escape}"
                            data-components="{$comp_count|escape}"
                            data-currency="{$pl.currency|default:'CAD'|escape}"
                            data-interval="{$pl.billing_interval|default:'month'|escape}"
                            data-subs="{$pl.active_subs|default:0|escape}"
                            data-status="{$plan_status|escape}"
                            data-created="{$pl.created_at|date_format:'%Y-%m-%d'|escape}">
                          <td class="px-4 py-3">
                            <div class="font-medium text-[var(--eb-text-primary)]">{$pl.name|escape}</div>
                            {if $pl.description}<div class="mt-0.5 max-w-xs truncate text-xs text-[var(--eb-text-muted)]">{$pl.description|escape|truncate:60}</div>{/if}
                            <div class="mt-0.5 text-xs text-[var(--eb-text-muted)]">v{$pl.version}{if $pl.trial_days} &middot; {$pl.trial_days}-day trial{/if}</div>
                          </td>
                          <td class="px-4 py-3"><span class="text-[var(--eb-text-secondary)]">{$comp_count}</span></td>
                          <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$pl.currency|default:'CAD'}</td>
                          <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$pl.billing_interval|default:'month'}</td>
                          <td class="px-4 py-3">{if $pl.active_subs > 0}<span class="eb-badge eb-badge--success">{$pl.active_subs}</span>{else}<span class="text-[var(--eb-text-muted)]">0</span>{/if}</td>
                          <td class="px-4 py-3">
                            {if $plan_status == 'active'}
                            <span class="eb-badge eb-badge--success">Published</span>
                            {elseif $plan_status == 'draft'}
                            <span class="eb-badge eb-badge--warning">Draft</span>
                            {elseif $plan_status == 'archived'}
                            <span class="eb-badge eb-badge--neutral">Archived</span>
                            {else}
                            <span class="eb-badge eb-badge--default">{$plan_status|escape}</span>
                            {/if}
                          </td>
                          <td class="px-4 py-3 text-xs text-[var(--eb-text-muted)]">{$pl.created_at|date_format:'%Y-%m-%d'}</td>
                          <td class="px-4 py-3 text-right" x-data="planRowMenu()">
                            <div class="inline-block text-left">
                              <button type="button" x-ref="planMenuBtn" class="eb-btn eb-btn-outline eb-btn-xs cursor-pointer" @click="toggle()">&ctdot;</button>
                              <template x-teleport="body">
                                <div
                                  x-show="o"
                                  x-transition
                                  @click.outside="close()"
                                  class="w-52 overflow-hidden rounded-[var(--eb-radius-xl)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] p-1 shadow-[var(--eb-shadow-lg)]"
                                  :style="{ position: 'fixed', top: menuTop + 'px', left: menuLeft + 'px', zIndex: 70 }"
                                >
                                  <button type="button" class="w-full rounded-[var(--eb-radius-md)] px-4 py-2 text-left text-sm text-[var(--eb-text-secondary)] hover:bg-[var(--eb-bg-hover)]" @click="close(); openEdit({$pl.id})">Edit Builder</button>
                                  <button type="button" class="w-full rounded-[var(--eb-radius-md)] px-4 py-2 text-left text-sm text-[var(--eb-text-secondary)] hover:bg-[var(--eb-bg-hover)]" @click="close(); duplicatePlan({$pl.id})">Duplicate</button>
                                  {if $plan_status == 'active'}
                                  <button type="button" class="w-full rounded-[var(--eb-radius-md)] px-4 py-2 text-left text-sm text-[var(--eb-text-secondary)] hover:bg-[var(--eb-bg-hover)]" @click="close(); openAssign({$pl.id}, '{$pl.name|escape:'javascript'}', '{$plan_status|escape:'javascript'}')">Assign to Customer</button>
                                  {/if}
                                  <button type="button" class="w-full rounded-[var(--eb-radius-md)] px-4 py-2 text-left text-sm text-[var(--eb-text-secondary)] hover:bg-[var(--eb-bg-hover)]" @click="close(); openSubs({$pl.id})">View Subscriptions</button>
                                  {if $plan_status == 'active'}
                                  <button type="button" class="w-full rounded-[var(--eb-radius-md)] px-4 py-2 text-left text-sm text-[var(--eb-warning-text)] hover:bg-[var(--eb-bg-hover)]" @click="close(); toggleStatus({$pl.id}, 'archived')">Archive</button>
                                  {elseif $plan_status == 'draft' || $plan_status == 'archived'}
                                  <button type="button" class="w-full rounded-[var(--eb-radius-md)] px-4 py-2 text-left text-sm text-[var(--eb-success-text)] hover:bg-[var(--eb-bg-hover)]" @click="close(); toggleStatus({$pl.id}, 'active')">Publish</button>
                                  {/if}
                                  <button type="button" class="w-full rounded-[var(--eb-radius-md)] px-4 py-2 text-left text-sm text-[var(--eb-danger-text)] hover:bg-[var(--eb-bg-hover)]" @click="close(); deletePlan({$pl.id})">Delete</button>
                                </div>
                              </template>
                            </div>
                          </td>
                        </tr>
                        {foreachelse}
                        <tr>
                          <td colspan="8" class="px-4 py-8 text-center text-[var(--eb-text-muted)]">No plan templates yet. Create one to get started.</td>
                        </tr>
                        {/foreach}
                        {if $plans|@count > 0}
                        <tr x-ref="noResults" style="display: none;">
                          <td colspan="8" class="px-4 py-8 text-center text-[var(--eb-text-muted)]">No plan templates found.</td>
                        </tr>
                        {/if}
                      </tbody>
                    </table>
                  </div>
                  <div class="mt-4 flex flex-col gap-3 text-xs text-[var(--eb-text-muted)] sm:flex-row sm:items-center sm:justify-between">
                    <div x-text="pageSummary()"></div>
                    <div class="flex items-center gap-2">
                      <button type="button" @click="prevPage()" :disabled="currentPage <= 1" class="eb-btn eb-btn-outline eb-btn-xs disabled:cursor-not-allowed disabled:opacity-50">Prev</button>
                      <span class="text-[var(--eb-text-secondary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                      <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages()" class="eb-btn eb-btn-outline eb-btn-xs disabled:cursor-not-allowed disabled:opacity-50">Next</button>
                    </div>
                  </div>
                </div>
              </section>

              <div id="eb-plan-panel" class="hidden fixed inset-0 z-50">
                <div class="absolute inset-0 eb-drawer-backdrop backdrop-blur-sm" @click="closePanel()"></div>
                <div class="fixed inset-y-0 right-0 z-[1] flex h-full flex-col eb-drawer eb-drawer--panel pointer-events-auto">
                  {* Step strip uses border-y; avoid double line under title (eb-drawer-header default border-b). *}
                  <div class="eb-drawer-header items-start gap-4 !border-b-0 !px-6 !py-5">
                    <div class="min-w-0 flex-1">
                      <h3 class="eb-modal-title !text-lg" x-text="panelMode === 'create' ? 'Create Plan Template' : 'Edit Plan Template'"></h3>
                      <p class="eb-modal-subtitle mt-1 max-w-2xl">Use existing published catalog prices to build a draft or published plan.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                      <span class="hidden sm:inline-flex" :class="currentPlanStatusClass()" x-text="currentPlanStatusLabel()"></span>
                      <button type="button" class="eb-btn eb-btn-outline eb-btn-sm lg:hidden" @click="mobileSummaryOpen = true">View Summary</button>
                      <button type="button" class="eb-modal-close" @click="closePanel()" aria-label="Close">&#10005;</button>
                    </div>
                  </div>

                  <div class="border-y border-[var(--eb-border-default)] bg-[var(--eb-bg-surface)] px-6 py-4">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                      <template x-for="stepNumber in [1,2,3,4]" :key="'step-' + stepNumber">
                        <button type="button" class="rounded-[var(--eb-radius-xl)] border px-4 py-3 text-left transition" :class="step >= stepNumber ? 'border-[var(--eb-border-brand)] bg-[var(--eb-primary-soft)] text-[var(--eb-text-primary)]' : 'border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] text-[var(--eb-text-muted)]'" @click="step = stepNumber <= step + 1 ? stepNumber : step">
                          <div class="text-[11px] uppercase tracking-[0.18em] text-[var(--eb-text-muted)]">Step <span x-text="stepNumber"></span></div>
                          <div class="mt-1 text-sm font-medium" x-text="['Plan Basics','Select Products','Configure Components','Review & Publish'][stepNumber - 1]"></div>
                        </button>
                      </template>
                    </div>
                  </div>

                  <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-[var(--eb-bg-base)]">
                    <div class="grid min-h-0 flex-1 auto-rows-fr lg:grid-cols-[minmax(0,1fr)_22rem]">
                      <div class="min-h-0 overflow-y-auto bg-[var(--eb-bg-raised)] px-6 py-6 lg:border-r lg:border-[var(--eb-border-default)]">
                        <div x-show="step === 1" class="space-y-6">
                          <div class="eb-card">
                            <h4 class="eb-card-title">Plan Basics</h4>
                            <p class="eb-card-subtitle">Set the plan name, trial period, billing interval, and currency before selecting catalog prices.</p>
                            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                              <label class="block md:col-span-2">
                                <span class="eb-field-label">Plan name</span>
                                <input x-model="planData.name" class="eb-input mt-2" />
                              </label>
                              <label class="block md:col-span-2">
                                <span class="eb-field-label">Description</span>
                                <textarea x-model="planData.description" rows="3" class="eb-textarea mt-2"></textarea>
                              </label>
                              <div class="block" x-data="{ open: false, search: '' }" @click.outside="open = false">
                                <span class="eb-field-label">Billing interval</span>
                                <div class="relative mt-2">
                                  <button
                                    type="button"
                                    class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                  >
                                    <span x-text="({ month: 'Monthly', year: 'Yearly' }[planData.billing_interval] || 'Monthly')"></span>
                                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                  </button>
                                  <div x-show="open" x-transition class="absolute left-0 right-0 z-[90] mt-2 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]" style="display: none;">
                                    <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                      <input type="search" x-model="search" placeholder="Search billing intervals..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop />
                                    </div>
                                    <div class="max-h-52 overflow-y-auto p-1">
                                      <template x-for="option in [{ value: 'month', label: 'Monthly' }, { value: 'year', label: 'Yearly' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase()))" :key="'billing-interval-' + option.value">
                                        <button type="button" class="eb-menu-item w-full" :class="planData.billing_interval === option.value ? 'is-active' : ''" @click="planData.billing_interval = option.value; open = false; search = ''">
                                          <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                        </button>
                                      </template>
                                      <div x-show="[{ value: 'month', label: 'Monthly' }, { value: 'year', label: 'Yearly' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase())).length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No billing intervals match your search.</div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="block" x-data="{ open: false, search: '' }" @click.outside="open = false">
                                <span class="eb-field-label">Currency</span>
                                <div class="relative mt-2">
                                  <button
                                    type="button"
                                    class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                  >
                                    <span x-text="planData.currency || 'CAD'"></span>
                                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                  </button>
                                  <div x-show="open" x-transition class="absolute left-0 right-0 z-[90] mt-2 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]" style="display: none;">
                                    <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                      <input type="search" x-model="search" placeholder="Search currencies..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop />
                                    </div>
                                    <div class="max-h-52 overflow-y-auto p-1">
                                      <template x-for="option in ['CAD', 'USD', 'EUR', 'GBP', 'AUD'].filter((option) => !search || option.toLowerCase().includes(search.toLowerCase()))" :key="'plan-currency-' + option">
                                        <button type="button" class="eb-menu-item w-full" :class="planData.currency === option ? 'is-active' : ''" @click="planData.currency = option; open = false; search = ''">
                                          <span class="min-w-0 flex-1 truncate text-left" x-text="option"></span>
                                        </button>
                                      </template>
                                      <div x-show="['CAD', 'USD', 'EUR', 'GBP', 'AUD'].filter((option) => !search || option.toLowerCase().includes(search.toLowerCase())).length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No currencies match your search.</div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <label class="block">
                                <span class="eb-field-label">Trial days</span>
                                <input x-model.number="planData.trial_days" type="number" min="0" class="eb-input mt-2" />
                              </label>
                              <div class="block" x-data="{ open: false, search: '' }" @click.outside="open = false">
                                <span class="eb-field-label">Status</span>
                                <div class="relative mt-2">
                                  <button
                                    type="button"
                                    class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                  >
                                    <span x-text="({ draft: 'Draft', active: 'Published', archived: 'Archived' }[planData.status] || 'Draft')"></span>
                                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                  </button>
                                  <div x-show="open" x-transition class="absolute left-0 right-0 z-[90] mt-2 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]" style="display: none;">
                                    <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                      <input type="search" x-model="search" placeholder="Search statuses..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop />
                                    </div>
                                    <div class="max-h-52 overflow-y-auto p-1">
                                      <template x-for="option in [{ value: 'draft', label: 'Draft' }, { value: 'active', label: 'Published' }, { value: 'archived', label: 'Archived' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase()))" :key="'plan-status-' + option.value">
                                        <button type="button" class="eb-menu-item w-full" :class="planData.status === option.value ? 'is-active' : ''" @click="planData.status = option.value; open = false; search = ''">
                                          <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                        </button>
                                      </template>
                                      <div x-show="[{ value: 'draft', label: 'Draft' }, { value: 'active', label: 'Published' }, { value: 'archived', label: 'Archived' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase())).length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No statuses match your search.</div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div x-show="step === 2" class="space-y-6">
                          <div class="eb-card">
                            <div class="eb-card-header !mb-4">
                              <div>
                                <h4 class="eb-card-title">Select Products</h4>
                                <p class="eb-card-subtitle">Choose published recurring prices that match this plan’s <span class="text-[var(--eb-text-primary)]" x-text="planData.currency"></span> <span class="text-[var(--eb-text-muted)]">/</span> <span class="text-[var(--eb-text-primary)]" x-text="planData.billing_interval"></span> billing rules.</p>
                              </div>
                              <a :href="createProductUrl()" class="eb-btn eb-btn-outline eb-btn-xs shrink-0">Manage Products</a>
                            </div>
                            <div class="mt-2 flex flex-col gap-3 md:flex-row">
                              <input x-model="catalogSearch" type="text" placeholder="Search products or prices" class="eb-toolbar-search w-full md:flex-1 rounded-full">
                              <div class="relative w-full md:w-56" x-data="{ open: false, search: '' }" @click.outside="open = false">
                                <button
                                  type="button"
                                  class="eb-input relative flex w-full items-center justify-between gap-2 rounded-full pr-10 text-left"
                                  @click="open = !open"
                                  :aria-expanded="open"
                                >
                                  <span x-text="({ all: 'All Types', STORAGE_TB: 'Storage', DEVICE_COUNT: 'Devices', DISK_IMAGE: 'Disk Image', HYPERV_VM: 'Hyper-V', PROXMOX_VM: 'Proxmox', VMWARE_VM: 'VMware', M365_USER: 'Microsoft 365', GENERIC: 'Generic Service' }[catalogTypeFilter] || 'All Types')"></span>
                                  <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute left-0 right-0 z-[90] mt-2 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]" style="display: none;">
                                  <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                    <input type="search" x-model="search" placeholder="Search product types..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop />
                                  </div>
                                  <div class="max-h-52 overflow-y-auto p-1">
                                    <template x-for="option in [{ value: 'all', label: 'All Types' }, { value: 'STORAGE_TB', label: 'Storage' }, { value: 'DEVICE_COUNT', label: 'Devices' }, { value: 'DISK_IMAGE', label: 'Disk Image' }, { value: 'HYPERV_VM', label: 'Hyper-V' }, { value: 'PROXMOX_VM', label: 'Proxmox' }, { value: 'VMWARE_VM', label: 'VMware' }, { value: 'M365_USER', label: 'Microsoft 365' }, { value: 'GENERIC', label: 'Generic Service' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase()))" :key="'catalog-type-' + option.value">
                                      <button type="button" class="eb-menu-item w-full" :class="catalogTypeFilter === option.value ? 'is-active' : ''" @click="catalogTypeFilter = option.value; open = false; search = ''">
                                        <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                      </button>
                                    </template>
                                    <div x-show="[{ value: 'all', label: 'All Types' }, { value: 'STORAGE_TB', label: 'Storage' }, { value: 'DEVICE_COUNT', label: 'Devices' }, { value: 'DISK_IMAGE', label: 'Disk Image' }, { value: 'HYPERV_VM', label: 'Hyper-V' }, { value: 'PROXMOX_VM', label: 'Proxmox' }, { value: 'VMWARE_VM', label: 'VMware' }, { value: 'M365_USER', label: 'Microsoft 365' }, { value: 'GENERIC', label: 'Generic Service' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase())).length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No product types match your search.</div>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div class="mt-5 space-y-4" x-show="filteredCatalogProducts().length > 0">
                              <template x-for="product in filteredCatalogProducts()" :key="'catalog-product-' + product.id">
                                <div class="rounded-[var(--eb-radius-xl)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-surface)] p-4">
                                  <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                      <div class="flex flex-wrap items-center gap-2">
                                        <h5 class="text-sm font-semibold text-[var(--eb-text-primary)]" x-text="product.name"></h5>
                                        <span class="eb-badge eb-badge--default" x-text="productMetricLabel(product)"></span>
                                      </div>
                                      <p class="mt-1 text-xs text-[var(--eb-text-muted)]" x-text="product.description || 'No description provided.'"></p>
                                    </div>
                                  </div>
                                  <div class="mt-4 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-subtle)]">
                                    <table class="min-w-full divide-y divide-[var(--eb-border-subtle)] text-xs">
                                      <thead class="bg-[var(--eb-bg-surface)] text-[var(--eb-text-muted)]">
                                        <tr>
                                          <th class="px-3 py-2 text-left font-medium">Price</th>
                                          <th class="px-3 py-2 text-left font-medium">Billing</th>
                                          <th class="px-3 py-2 text-left font-medium">Interval</th>
                                          <th class="px-3 py-2 text-left font-medium">Status</th>
                                          <th class="px-3 py-2 text-right font-medium">Action</th>
                                        </tr>
                                      </thead>
                                      <tbody class="divide-y divide-[var(--eb-border-subtle)]">
                                        <template x-for="price in visiblePricesForProduct(product)" :key="'catalog-price-' + price.id">
                                          <tr class="bg-[var(--eb-bg-card)]">
                                            <td class="px-3 py-2">
                                              <div class="font-medium text-[var(--eb-text-primary)]" x-text="price.name"></div>
                                              <div class="text-[var(--eb-text-muted)]" x-text="componentPriceText({ unit_amount: price.unit_amount, currency: price.currency, interval: price.interval, unit_label: price.unit_label || '', metric_code: price.metric_code || product.base_metric_code, billing_type: price.billing_type })"></div>
                                            </td>
                                            <td class="px-3 py-2 text-[var(--eb-text-secondary)]" x-text="billingLabel(price.billing_type)"></td>
                                            <td class="px-3 py-2 text-[var(--eb-text-secondary)]" x-text="price.interval"></td>
                                            <td class="px-3 py-2 text-[var(--eb-text-secondary)]" x-text="price.active ? 'Published price' : 'Archived legacy price'"></td>
                                            <td class="px-3 py-2 text-right">
                                              <button type="button" :class="isSelectedPrice(price.id) ? 'eb-btn eb-btn-xs eb-btn-outline cursor-default' : 'eb-btn eb-btn-xs eb-btn-orange'" :disabled="isSelectedPrice(price.id)" @click="addPriceToPlan(product.id, price.id)" x-text="isSelectedPrice(price.id) ? 'Added' : 'Add to Plan'"></button>
                                            </td>
                                          </tr>
                                        </template>
                                      </tbody>
                                    </table>
                                  </div>
                                </div>
                              </template>
                            </div>

                            <div x-show="filteredCatalogProducts().length === 0" class="mt-5 rounded-[var(--eb-radius-xl)] border border-dashed border-[var(--eb-border-default)] bg-[var(--eb-bg-surface)] px-6 py-10 text-center">
                              <div class="text-sm font-medium text-[var(--eb-text-primary)]">No compatible products found</div>
                              <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Adjust the plan currency or billing interval, or create matching products and prices first.</p>
                              <a :href="createProductUrl()" class="eb-btn eb-btn-outline eb-btn-xs mt-4 inline-flex">Open Catalog Products</a>
                            </div>
                          </div>
                        </div>

                        <div x-show="step === 3" class="space-y-6">
                          <div class="eb-card">
                            <h4 class="eb-card-title">Configure Components</h4>
                            <p class="eb-card-subtitle">Set what is included in the plan and how usage above that included amount should be handled.</p>
                            <div class="mt-5 space-y-4" x-show="editComponents.length > 0">
                              <template x-for="(comp, ci) in editComponents" :key="'component-' + ci + '-' + comp.price_id">
                                <div class="rounded-[var(--eb-radius-xl)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-surface)] p-4">
                                  <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                      <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-semibold text-[var(--eb-text-primary)]" x-text="comp.product_name || 'Catalog Product'"></div>
                                        <span :class="componentBadgeClass(comp)" x-text="billingLabel(comp.billing_type)"></span>
                                        <span class="eb-badge eb-badge--default" x-text="metricLabel(comp.metric_code)"></span>
                                        <span x-show="comp.is_legacy_attached" class="eb-badge eb-badge--warning">Archived catalog price</span>
                                      </div>
                                      <div class="mt-1 text-xs text-[var(--eb-text-secondary)]" x-text="comp.price_name"></div>
                                      <div class="mt-1 text-xs text-[var(--eb-text-muted)]" x-text="componentPriceText(comp)"></div>
                                    </div>
                                    <button type="button" class="eb-btn eb-btn-danger eb-btn-sm shrink-0" @click="removeComponent(ci)">Remove</button>
                                  </div>

                                  <div x-show="legacyWarning(comp)" class="eb-alert eb-alert--warning mt-3 !px-3 !py-2 !text-xs" x-text="legacyWarning(comp)"></div>

                                  <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label class="block">
                                      <span class="eb-field-label !text-xs">Included quantity</span>
                                      <input x-model.number="comp.default_qty" type="number" min="0" class="eb-input mt-1 !text-xs" />
                                      <p class="eb-field-help">This plan includes <span class="text-[var(--eb-text-primary)]" x-text="includedLabel(comp)"></span> before the overage rule is applied.</p>
                                    </label>
                                    <div class="block" x-data="{ open: false, search: '' }" @click.outside="open = false">
                                      <span class="eb-field-label !text-xs">Overage behavior</span>
                                      <div class="relative mt-1">
                                        <button
                                          type="button"
                                          class="eb-input relative flex w-full items-center justify-between gap-2 pr-10 text-left !text-xs"
                                          @click="open = !open"
                                          :aria-expanded="open"
                                        >
                                          <span x-text="({ bill_all: 'Charge for all usage above included amount', cap_at_default: 'Do not bill usage above included amount' }[comp.overage_mode] || 'Charge for all usage above included amount')"></span>
                                          <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </button>
                                        <div x-show="open" x-transition class="absolute left-0 right-0 z-[90] mt-2 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]" style="display: none;">
                                          <div class="border-b border-[var(--eb-border-subtle)] p-2">
                                            <input type="search" x-model="search" placeholder="Search overage rules..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop />
                                          </div>
                                          <div class="max-h-52 overflow-y-auto p-1">
                                            <template x-for="option in [{ value: 'bill_all', label: 'Charge for all usage above included amount' }, { value: 'cap_at_default', label: 'Do not bill usage above included amount' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase()))" :key="'overage-mode-' + option.value">
                                              <button type="button" class="eb-menu-item w-full" :class="comp.overage_mode === option.value ? 'is-active' : ''" @click="comp.overage_mode = option.value; open = false; search = ''">
                                                <span class="min-w-0 flex-1 truncate text-left" x-text="option.label"></span>
                                              </button>
                                            </template>
                                            <div x-show="[{ value: 'bill_all', label: 'Charge for all usage above included amount' }, { value: 'cap_at_default', label: 'Do not bill usage above included amount' }].filter((option) => !search || option.label.toLowerCase().includes(search.toLowerCase())).length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No overage rules match your search.</div>
                                          </div>
                                        </div>
                                      </div>
                                      <p class="eb-field-help" x-text="overageExample(comp)"></p>
                                    </div>
                                  </div>
                                </div>
                              </template>
                            </div>
                            <div x-show="editComponents.length === 0" class="mt-5 rounded-[var(--eb-radius-xl)] border border-dashed border-[var(--eb-border-default)] bg-[var(--eb-bg-surface)] px-6 py-10 text-center text-sm text-[var(--eb-text-muted)]">
                              Add a price in the Select Products step to begin configuring included service levels.
                            </div>
                          </div>
                        </div>

                        <div x-show="step === 4" class="space-y-6">
                          <div class="eb-card">
                            <h4 class="eb-card-title">Review &amp; Publish</h4>
                            <p class="eb-card-subtitle">Confirm what is included, how overage works, and whether this plan should stay as a draft or be published.</p>

                            <div class="mt-5 grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                              <div class="eb-card !p-4">
                                <div class="eb-stat-label">Plan</div>
                                <div class="mt-2 text-base font-semibold text-[var(--eb-text-primary)]" x-text="planData.name || 'Untitled plan'"></div>
                                <div class="mt-1 text-sm text-[var(--eb-text-muted)]" x-text="planData.description || 'No description provided.'"></div>
                              </div>
                              <div class="eb-card !p-4">
                                <div class="eb-stat-label">Billing</div>
                                <div class="mt-2 text-base font-semibold text-[var(--eb-text-primary)]"><span x-text="planData.currency"></span> <span class="text-[var(--eb-text-muted)]">/</span> <span x-text="planData.billing_interval"></span></div>
                                <div class="mt-1 text-sm text-[var(--eb-text-muted)]"><span x-text="planData.trial_days || 0"></span> day trial • <span x-text="currentPlanStatusLabel()"></span></div>
                              </div>
                            </div>

                            <div class="mt-5 space-y-3">
                              <template x-for="comp in sortedComponents()" :key="'review-' + comp.price_id + '-' + componentSortKey(comp)">
                                <div class="eb-card !p-4">
                                  <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                      <div class="text-sm font-semibold text-[var(--eb-text-primary)]" x-text="comp.product_name"></div>
                                      <div class="text-xs text-[var(--eb-text-secondary)]" x-text="comp.price_name"></div>
                                    </div>
                                    <div class="text-xs text-[var(--eb-text-muted)]" x-text="componentPriceText(comp)"></div>
                                  </div>
                                  <div class="mt-3 grid grid-cols-1 gap-3 text-xs text-[var(--eb-text-secondary)] md:grid-cols-2">
                                    <div>
                                      <div class="text-[var(--eb-text-muted)]">Included service</div>
                                      <div class="mt-1" x-text="includedLabel(comp)"></div>
                                    </div>
                                    <div>
                                      <div class="text-[var(--eb-text-muted)]">Overage rule</div>
                                      <div class="mt-1" x-text="overageLabel(comp.overage_mode)"></div>
                                    </div>
                                  </div>
                                </div>
                              </template>
                            </div>

                            <div x-show="hasMeteredComponents()" class="eb-alert eb-alert--info mt-4 !text-xs">
                              Metered components are billed from usage records. The recurring base total below excludes any future metered overage.
                            </div>
                          </div>
                        </div>
                      </div>

                      <aside class="hidden min-h-0 w-full min-w-0 bg-[var(--eb-bg-surface)] p-4 lg:flex lg:min-h-0 lg:flex-col">
                        <div class="eb-card-raised flex min-h-0 w-full flex-1 flex-col overflow-hidden !p-0 shadow-[var(--eb-shadow-md)]">
                          <div class="border-b border-[var(--eb-border-subtle)] bg-[var(--eb-bg-raised)] px-4 py-4">
                            <div class="eb-stat-label">Plan Summary</div>
                            <div class="mt-2 text-lg font-semibold text-[var(--eb-text-primary)]" x-text="planData.name || 'Untitled plan'"></div>
                            <div class="mt-1 text-sm leading-snug text-[var(--eb-text-muted)]" x-text="planData.description || 'Build your plan from existing recurring catalog prices.'"></div>
                          </div>
                          <div class="border-b border-[var(--eb-border-subtle)] px-4 py-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Billing Rules</div>
                            <div class="mt-2 text-sm font-medium text-[var(--eb-text-primary)]"><span x-text="planData.currency"></span> <span class="text-[var(--eb-text-disabled)]">•</span> <span x-text="planData.billing_interval"></span></div>
                            <div class="mt-1 text-xs text-[var(--eb-text-muted)]"><span x-text="planData.trial_days || 0"></span> day trial</div>
                            <div class="mt-3 inline-flex" :class="currentPlanStatusClass()" x-text="currentPlanStatusLabel()"></div>
                          </div>
                          <div class="min-h-0 flex-1 overflow-y-auto border-b border-[var(--eb-border-subtle)] px-4 py-4">
                            <div class="flex items-center justify-between gap-2">
                              <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Selected Components</div>
                              <div class="eb-badge eb-badge--default text-[10px]" x-text="editComponents.length + ' total'"></div>
                            </div>
                            <div class="mt-3 space-y-2 rounded-[var(--eb-radius-md)] bg-[var(--eb-bg-surface)] p-2 ring-1 ring-[var(--eb-border-subtle)]" x-show="editComponents.length > 0">
                              <template x-for="comp in sortedComponents()" :key="'summary-' + comp.price_id + '-' + componentSortKey(comp)">
                                <div class="rounded-[var(--eb-radius-sm)] bg-[var(--eb-bg-overlay)] px-3 py-2.5 ring-1 ring-[var(--eb-border-faint)]">
                                  <div class="text-xs font-medium text-[var(--eb-text-primary)]" x-text="comp.product_name"></div>
                                  <div class="mt-0.5 text-[11px] text-[var(--eb-text-muted)]" x-text="comp.price_name"></div>
                                  <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-[var(--eb-text-secondary)]">
                                    <span x-text="includedLabel(comp) + ' included'"></span>
                                    <span class="shrink-0 font-medium tabular-nums text-[var(--eb-text-primary)]" x-text="componentPriceText(comp)"></span>
                                  </div>
                                </div>
                              </template>
                            </div>
                            <div x-show="editComponents.length === 0" class="mt-3 rounded-[var(--eb-radius-md)] bg-[var(--eb-bg-surface)] px-3 py-4 text-center text-xs text-[var(--eb-text-muted)] ring-1 ring-[var(--eb-border-subtle)]">No catalog prices selected yet.</div>
                          </div>
                          <div class="bg-[var(--eb-primary-soft)] px-4 py-4 ring-1 ring-[var(--eb-primary-border)]">
                            <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-secondary)]">Recurring Base Charges</div>
                            <div class="mt-2 text-lg font-semibold tabular-nums text-[var(--eb-text-primary)]" x-text="reviewRecurringSummary()"></div>
                            <div class="mt-1 text-xs text-[var(--eb-text-muted)]" x-show="hasMeteredComponents()">Metered overage charges apply separately based on usage.</div>
                          </div>
                        </div>
                      </aside>
                    </div>
                  </div>

                  <div class="flex flex-col gap-3 border-t border-[var(--eb-border-default)] bg-[var(--eb-bg-surface)] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                      <button type="button" class="eb-btn eb-btn-outline eb-btn-md" @click="closePanel()">Cancel</button>
                      <button type="button" x-show="step > 1" class="eb-btn eb-btn-outline eb-btn-md" @click="prevStep()">Back</button>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                      <button type="button" x-show="step < 4" class="eb-btn eb-btn-primary eb-btn-md" @click="nextStep()">Next</button>
                      <button type="button" x-show="step === 4" class="eb-btn eb-btn-outline eb-btn-md" @click="savePlan('draft')" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Save Draft'"></button>
                      <button type="button" x-show="step === 4" class="eb-btn eb-btn-success eb-btn-md" @click="savePlan('active')" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Publish'"></button>
                      <button type="button" x-show="step === 4 && panelMode === 'edit'" class="eb-btn eb-btn-warning eb-btn-md" @click="savePlan('archived')" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Archive'"></button>
                    </div>
                  </div>
                </div>

                <div x-show="mobileSummaryOpen" class="fixed inset-0 z-[60] lg:hidden">
                  <div class="absolute inset-0 eb-drawer-backdrop backdrop-blur-sm" @click="mobileSummaryOpen = false"></div>
                  <div class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-[var(--eb-radius-xl)] border border-b-0 border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] p-5 shadow-[var(--eb-shadow-lg)]">
                    <div class="flex items-center justify-between">
                      <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Plan Summary</div>
                      <button type="button" class="eb-modal-close" @click="mobileSummaryOpen = false" aria-label="Close">&#10005;</button>
                    </div>
                    <div class="mt-4 overflow-hidden rounded-[var(--eb-radius-xl)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-md)]">
                      <div class="border-b border-[var(--eb-border-subtle)] px-4 py-4">
                        <div class="text-base font-semibold text-[var(--eb-text-primary)]" x-text="planData.name || 'Untitled plan'"></div>
                        <div class="mt-1 text-sm leading-snug text-[var(--eb-text-muted)]" x-text="planData.description || 'Build your plan from existing recurring catalog prices.'"></div>
                      </div>
                      <div class="border-b border-[var(--eb-border-subtle)] px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Billing Rules</div>
                        <div class="mt-2 text-sm font-medium text-[var(--eb-text-primary)]"><span x-text="planData.currency"></span> <span class="text-[var(--eb-text-disabled)]">•</span> <span x-text="planData.billing_interval"></span></div>
                        <div class="mt-1 text-xs text-[var(--eb-text-muted)]"><span x-text="planData.trial_days || 0"></span> day trial</div>
                      </div>
                      <div class="border-b border-[var(--eb-border-subtle)] px-4 py-4">
                        <div class="flex items-center justify-between gap-2">
                          <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-muted)]">Selected Components</div>
                          <div class="eb-badge eb-badge--default text-[10px]" x-text="editComponents.length + ' total'"></div>
                        </div>
                        <div class="mt-3 space-y-2 rounded-[var(--eb-radius-md)] bg-[var(--eb-bg-surface)] p-2 ring-1 ring-[var(--eb-border-subtle)]" x-show="editComponents.length > 0">
                          <template x-for="comp in sortedComponents()" :key="'mobile-summary-' + comp.price_id + '-' + componentSortKey(comp)">
                            <div class="rounded-[var(--eb-radius-sm)] bg-[var(--eb-bg-overlay)] px-3 py-2.5 ring-1 ring-[var(--eb-border-faint)]">
                              <div class="text-xs font-medium text-[var(--eb-text-primary)]" x-text="comp.product_name"></div>
                              <div class="mt-0.5 text-[11px] text-[var(--eb-text-muted)]" x-text="comp.price_name"></div>
                              <div class="mt-2 text-[11px] text-[var(--eb-text-secondary)]" x-text="includedLabel(comp) + ' included • ' + componentPriceText(comp)"></div>
                            </div>
                          </template>
                        </div>
                        <div x-show="editComponents.length === 0" class="mt-3 rounded-[var(--eb-radius-md)] bg-[var(--eb-bg-surface)] px-3 py-4 text-center text-xs text-[var(--eb-text-muted)] ring-1 ring-[var(--eb-border-subtle)]">No catalog prices selected yet.</div>
                      </div>
                      <div class="bg-[var(--eb-primary-soft)] px-4 py-4 ring-1 ring-[var(--eb-primary-border)]">
                        <div class="text-xs font-semibold uppercase tracking-wide text-[var(--eb-text-secondary)]">Recurring Base Charges</div>
                        <div class="mt-2 text-lg font-semibold tabular-nums text-[var(--eb-text-primary)]" x-text="reviewRecurringSummary()"></div>
                        <div class="mt-1 text-xs text-[var(--eb-text-muted)]" x-show="hasMeteredComponents()">Metered overage charges apply separately based on usage.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div id="eb-plan-success-modal" class="fixed inset-0 z-[60] hidden flex items-center justify-center overflow-y-auto p-4">
                <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm" @click="closePlanSuccessModal()"></div>
                <div class="relative z-[1] my-auto w-full max-w-md" @click.stop>
                  <div class="eb-modal eb-modal--confirm flex w-full flex-col overflow-hidden">
                    <div class="eb-modal-header">
                      <h3 class="eb-modal-title" x-text="successModalTitle"></h3>
                      <button type="button" class="eb-modal-close" @click="closePlanSuccessModal()" aria-label="Close">&#10005;</button>
                    </div>
                    <div class="eb-modal-body">
                      <p class="text-sm leading-relaxed text-[var(--eb-text-secondary)]" x-text="successModalMessage"></p>
                    </div>
                    <div class="eb-modal-footer">
                      <button type="button" class="eb-btn eb-btn-primary eb-btn-md" @click="closePlanSuccessModal()">OK</button>
                    </div>
                  </div>
                </div>
              </div>

              <div id="eb-assign-plan-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto p-4">
                <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm" @click="closeAssign()"></div>
                <div class="relative z-[1] my-auto w-full max-w-2xl overflow-visible" @click.stop>
                  {* !overflow-visible: .eb-modal defaults to overflow:hidden which clips combobox panels *}
                  <div class="eb-modal flex min-h-[26rem] max-h-[90vh] w-full !max-w-2xl flex-col !overflow-visible">
                    <div class="eb-modal-header shrink-0 !border-b-[var(--eb-border-default)]">
                      <h3 class="eb-modal-title">Assign Plan to Customer</h3>
                      <button type="button" class="eb-modal-close" @click="closeAssign()" aria-label="Close">&#10005;</button>
                    </div>
                    <div class="eb-modal-body min-h-0 flex-1 space-y-5 overflow-visible text-sm">
                      <div>
                        <div class="eb-field-label !mb-1">Plan</div>
                        <div class="text-sm font-medium text-[var(--eb-text-primary)]" x-text="assignPlanName"></div>
                        <p x-show="assignPlanRequiresS3User()" class="mt-2 text-xs text-[var(--eb-text-muted)]">This plan requires an MSP-owned S3 user instead of an eazyBackup user.</p>
                      </div>
                      <div class="grid grid-cols-1 gap-5 md:grid-cols-2 md:gap-6">
                        <div class="relative min-w-0">
                          <span class="eb-field-label">Customer</span>
                          <button
                            type="button"
                            class="eb-input relative mt-2 flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left"
                            @click="toggleAssignTenantDropdown()"
                            :aria-expanded="assignTenantOpen"
                          >
                            <span class="min-w-0 truncate" :class="assignData.tenant_id ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="assignTenantLabel() || 'Select customer…'"></span>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="assignTenantOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                          </button>
                          <div
                            x-show="assignTenantOpen"
                            x-transition
                            @click.outside="assignTenantOpen = false"
                            class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                            style="display: none;"
                          >
                            <div class="border-b border-[var(--eb-border-subtle)] p-2">
                              <input
                                type="search"
                                x-ref="assignTenantSearchInput"
                                x-model="assignTenantSearch"
                                placeholder="Search customers…"
                                class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                                @click.stop
                              />
                            </div>
                            <div class="max-h-52 overflow-y-auto p-1">
                              <template x-for="t in filteredAssignTenants()" :key="t.public_id">
                                <button
                                  type="button"
                                  class="eb-menu-item w-full"
                                  :class="String(assignData.tenant_id) === String(t.public_id) ? 'is-active' : ''"
                                  @click="selectAssignTenant(t.public_id)"
                                >
                                  <span class="min-w-0 flex-1 truncate text-left" x-text="(t.name && t.name.trim()) ? t.name : t.public_id"></span>
                                </button>
                              </template>
                              <div x-show="filteredAssignTenants().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No matching customers.</div>
                            </div>
                          </div>
                        </div>
                        <div class="relative min-w-0" x-show="assignPlanRequiresCometUser()">
                          <span class="eb-field-label">eazyBackup user</span>
                          <button
                            type="button"
                            class="eb-input relative mt-2 flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left disabled:cursor-not-allowed disabled:opacity-50"
                            @click="assignData.tenant_id && toggleAssignUserDropdown()"
                            :disabled="!assignData.tenant_id"
                            :aria-expanded="assignUserOpen"
                          >
                            <span class="min-w-0 truncate" :class="assignData.comet_user_id ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="assignData.comet_user_id || assignUserPlaceholder()"></span>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="assignUserOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                          </button>
                          <div
                            x-show="assignUserOpen"
                            x-transition
                            @click.outside="assignUserOpen = false"
                            class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                            style="display: none;"
                          >
                            <div class="border-b border-[var(--eb-border-subtle)] p-2">
                              <input
                                type="search"
                                x-ref="assignUserSearchInput"
                                x-model="assignUserSearch"
                                placeholder="Search users…"
                                class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                                @click.stop
                              />
                            </div>
                            <div class="max-h-52 overflow-y-auto p-1">
                              <template x-for="u in filteredAssignUsers()" :key="u.comet_user_id || u.comet_username">
                                <button
                                  type="button"
                                  class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                                  :class="assignData.comet_user_id === (u.comet_user_id || u.comet_username) ? 'is-active' : ''"
                                  @click="selectAssignUser(u.comet_user_id || u.comet_username)"
                                >
                                  <span class="truncate text-left font-medium" x-text="u.comet_username || u.comet_user_id"></span>
                                  <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="u.tenant_name || u.tenant_public_id || ''"></span>
                                </button>
                              </template>
                              <div x-show="assignData.tenant_id && filteredCometAccounts.length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No eazyBackup users are linked to this customer.</div>
                              <div x-show="assignData.tenant_id && filteredCometAccounts.length > 0 && filteredAssignUsers().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No users match your search.</div>
                              <div x-show="!assignData.tenant_id" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">Select a customer to see users.</div>
                            </div>
                          </div>
                        </div>
                        <div class="relative min-w-0" x-show="assignPlanRequiresS3User()">
                          <span class="eb-field-label">S3 user</span>
                          <button
                            type="button"
                            class="eb-input relative mt-2 flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left disabled:cursor-not-allowed disabled:opacity-50"
                            @click="assignData.tenant_id && toggleAssignS3UserDropdown()"
                            :disabled="!assignData.tenant_id"
                            :aria-expanded="assignS3UserOpen"
                          >
                            <span class="min-w-0 truncate" :class="selectedS3UserId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="assignS3UserLabel() || (!assignData.tenant_id ? 'Select a customer first' : 'Select S3 user…')"></span>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="assignS3UserOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                          </button>
                          <p x-show="assignData.tenant_id" class="mt-2 text-xs text-[var(--eb-text-muted)]">Choose the MSP-owned S3 user that should back this storage subscription.</p>
                          <div
                            x-show="assignS3UserOpen"
                            x-transition
                            @click.outside="assignS3UserOpen = false"
                            class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                            style="display: none;"
                          >
                            <div class="border-b border-[var(--eb-border-subtle)] p-2">
                              <input
                                type="search"
                                x-ref="assignS3UserSearchInput"
                                x-model="assignS3UserSearch"
                                placeholder="Search S3 users…"
                                class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                                @click.stop
                              />
                            </div>
                            <div class="max-h-52 overflow-y-auto p-1">
                              <template x-for="user in filteredS3Users()" :key="'assign-s3-user-' + user.id">
                                <button
                                  type="button"
                                  class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                                  :class="String(selectedS3UserId) === String(user.id) ? 'is-active' : ''"
                                  @click="selectAssignS3User(user.id)"
                                >
                                  <span class="truncate text-left font-medium" x-text="user.display_label || user.name || user.username || ('S3 user #' + user.id)"></span>
                                  <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="'ID ' + user.id"></span>
                                </button>
                              </template>
                              <div x-show="!assignData.tenant_id" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">Select a customer first.</div>
                              <div x-show="assignData.tenant_id && filteredS3Users().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]" x-text="assignS3UserSearch ? 'No S3 users match your search.' : 'No S3 users are available for this MSP.'"></div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="eb-modal-footer shrink-0 !border-t-[var(--eb-border-default)]">
                      <button type="button" class="eb-btn eb-btn-outline eb-btn-md" @click="closeAssign()">Cancel</button>
                      <button type="button" class="eb-btn eb-btn-primary eb-btn-md" @click="submitAssign()" :disabled="isSaving">Create Subscription</button>
                    </div>
                  </div>
                </div>
              </div>

              <div id="eb-subs-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto p-4">
                <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm"></div>
                <div class="relative z-[1] my-auto flex w-full max-w-3xl max-h-[80vh] flex-col" @click.stop>
                  <div class="eb-modal flex max-h-[80vh] w-full !max-w-3xl flex-col overflow-hidden">
                    <div class="eb-modal-header">
                      <div>
                        <h3 class="eb-modal-title" x-text="subscriptionEditor.open ? 'Edit Subscription' : 'Active Subscriptions'">Active Subscriptions</h3>
                        <p class="mt-1 text-xs text-[var(--eb-text-muted)]" x-show="subscriptionEditor.open && subscriptionEditor.subscription" x-text="subscriptionEditor.subscription ? ((subscriptionEditor.subscription.tenant_name || subscriptionEditor.subscription.tenant_public_id || 'Tenant') + ' • ' + subscriptionEditor.subscription.comet_user_id) : ''"></p>
                      </div>
                      <button type="button" class="eb-modal-close" @click="closeSubs()" aria-label="Close">&#10005;</button>
                    </div>
                    <div class="eb-modal-body min-h-0 flex-1 overflow-y-auto !pt-4">
                      <template x-if="!subscriptionEditor.open">
                        <div>
                          <template x-if="subsLoading"><div class="py-8 text-center text-[var(--eb-text-muted)]">Loading...</div></template>
                          <template x-if="!subsLoading && subscriptions.length===0"><div class="py-8 text-center text-[var(--eb-text-muted)]">No active subscriptions.</div></template>
                          <template x-if="!subsLoading && subscriptions.length > 0">
                            <table class="w-full text-xs">
                              <thead>
                                <tr class="text-[var(--eb-text-muted)]">
                                  <th class="px-3 py-2 text-left">Tenant</th>
                                  <th class="px-3 py-2 text-left">eazyBackup User</th>
                                  <th class="px-3 py-2 text-left">Status</th>
                                  <th class="px-3 py-2 text-left">Created</th>
                                  <th class="px-3 py-2 text-right">Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <template x-for="sub in subscriptions" :key="sub.id">
                                  <tr class="border-t border-[var(--eb-border-subtle)]">
                                    <td class="px-3 py-2 text-[var(--eb-text-secondary)]" x-text="sub.tenant_name || sub.tenant_public_id || 'Tenant'"></td>
                                    <td class="px-3 py-2 text-[var(--eb-text-secondary)]" x-text="sub.comet_user_id"></td>
                                    <td class="px-3 py-2">
                                      <span class="eb-badge" :class="sub.status==='active' ? 'eb-badge--success' : (sub.status==='trialing' ? 'eb-badge--info' : 'eb-badge--neutral')" x-text="sub.status"></span>
                                    </td>
                                    <td class="px-3 py-2 text-[var(--eb-text-muted)]" x-text="sub.created_at ? sub.created_at.substring(0,10) : ''"></td>
                                    <td class="px-3 py-2 text-right">
                                      <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-xs" @click="openSubscriptionEditor(sub.id)">Edit</button>
                                        <button type="button" x-show="sub.status==='active' || sub.status==='trialing'" class="eb-btn eb-btn-outline eb-btn-xs" @click="pauseSubscription(sub.id)">Pause</button>
                                        <button type="button" x-show="sub.status==='paused'" class="eb-btn eb-btn-outline eb-btn-xs" @click="resumeSubscription(sub.id)">Resume</button>
                                        <button type="button" x-show="sub.status==='active' || sub.status==='trialing'" class="eb-btn eb-btn-ghost eb-btn-xs !text-[var(--eb-danger-text)]" @click="cancelSubscription(sub.id)">Cancel</button>
                                      </div>
                                    </td>
                                  </tr>
                                </template>
                              </tbody>
                            </table>
                          </template>
                        </div>
                      </template>
                      <template x-if="subscriptionEditor.open">
                        <div class="space-y-4">
                          <div class="eb-card-raised rounded-2xl border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-card)] p-4">
                            <div class="flex items-center justify-between gap-4">
                              <div>
                                <div class="text-sm font-semibold text-[var(--eb-text-primary)]" x-text="subscriptionEditor.subscription ? subscriptionEditor.subscription.plan_name : 'Subscription'"></div>
                                <div class="mt-1 text-xs text-[var(--eb-text-secondary)]">Adjust quantities, remove recurring items, swap to another active plan, then preview proration before saving.</div>
                              </div>
                              <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="subscriptionEditor.open = false">Back to list</button>
                            </div>
                          </div>

                          <template x-if="subscriptionEditor.loading">
                            <div class="py-8 text-center text-[var(--eb-text-muted)]">Loading subscription editor...</div>
                          </template>

                          <template x-if="!subscriptionEditor.loading">
                            <div class="space-y-4">
                              <label class="block">
                                <span class="text-xs font-medium uppercase tracking-wide text-[var(--eb-text-secondary)]">Swap Plan</span>
                                <select class="eb-input mt-2" :value="subscriptionEditor.swapPlanId" @change="applySwapPlan($event.target.value)">
                                  <option value="">Keep current plan</option>
                                  <template x-for="plan in subscriptionEditor.availablePlans" :key="'swap-' + plan.id">
                                    <option :value="plan.id" x-text="plan.name + ' (' + plan.currency + ' / ' + plan.billing_interval + ')'"></option>
                                  </template>
                                </select>
                              </label>

                              <div class="space-y-3">
                                <template x-for="(item, idx) in subscriptionEditor.items" :key="'editor-item-' + idx + '-' + item.plan_component_id + '-' + item.price_id">
                                  <div class="eb-card-raised rounded-2xl border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-card)] p-4">
                                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                      <div>
                                        <div class="text-sm font-semibold text-[var(--eb-text-primary)]" x-text="item.price_name"></div>
                                        <div class="mt-1 text-xs text-[var(--eb-text-secondary)]" x-text="metricLabel(item.metric_code) + ' • ' + billingLabel(item.kind === 'metered' ? 'metered' : 'per_unit')"></div>
                                        <div class="mt-2 text-xs text-[var(--eb-text-secondary)]" x-text="formatMoneyCents(item.unit_amount, item.currency) + (item.unit_label ? ' / ' + item.unit_label : '')"></div>
                                      </div>
                                      <button type="button" x-show="item.removable" class="eb-btn eb-btn-outline eb-btn-xs" @click="subscriptionEditor.items[idx].remove = !subscriptionEditor.items[idx].remove" x-text="subscriptionEditor.items[idx].remove ? 'Restore' : 'Remove'"></button>
                                    </div>
                                    <div class="mt-4 grid gap-4 md:grid-cols-2" :class="item.remove ? 'opacity-50' : ''">
                                      <label class="block">
                                        <span class="text-xs font-medium uppercase tracking-wide text-[var(--eb-text-secondary)]">Quantity</span>
                                        <input class="eb-input mt-2" type="number" min="0" x-model.number="subscriptionEditor.items[idx].quantity" :disabled="!item.editable_quantity || item.remove" />
                                      </label>
                                      <div class="rounded-xl border border-[var(--eb-border-subtle)] bg-[var(--eb-surface-elevated)] px-4 py-3 text-xs text-[var(--eb-text-secondary)]">
                                        Included by plan: <span class="font-medium text-[var(--eb-text-primary)]" x-text="item.default_qty"></span>
                                      </div>
                                    </div>
                                  </div>
                                </template>
                              </div>

                              <template x-if="subscriptionEditor.error">
                                <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" x-text="subscriptionEditor.error"></div>
                              </template>

                              <template x-if="subscriptionEditor.preview">
                                <div class="eb-card-raised rounded-2xl border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-card)] p-4">
                                  <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Proration Preview</div>
                                  <div class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                                    <div><div class="text-[var(--eb-text-secondary)]">Amount Due</div><div class="font-semibold text-[var(--eb-text-primary)]" x-text="formatMoneyCents(subscriptionEditor.preview.amount_due, subscriptionEditor.preview.currency)"></div></div>
                                    <div><div class="text-[var(--eb-text-secondary)]">Subtotal</div><div class="font-semibold text-[var(--eb-text-primary)]" x-text="formatMoneyCents(subscriptionEditor.preview.subtotal, subscriptionEditor.preview.currency)"></div></div>
                                    <div><div class="text-[var(--eb-text-secondary)]">Total</div><div class="font-semibold text-[var(--eb-text-primary)]" x-text="formatMoneyCents(subscriptionEditor.preview.total, subscriptionEditor.preview.currency)"></div></div>
                                    <div><div class="text-[var(--eb-text-secondary)]">Invoice Lines</div><div class="font-semibold text-[var(--eb-text-primary)]" x-text="subscriptionEditor.preview.line_count"></div></div>
                                  </div>
                                </div>
                              </template>

                              <div class="flex flex-wrap items-center justify-end gap-3">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="previewSubscriptionChanges()" x-text="subscriptionEditor.previewLoading ? 'Previewing...' : 'Preview Changes'"></button>
                                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="saveSubscriptionChanges()" x-text="subscriptionEditor.saving ? 'Saving...' : 'Save Changes'"></button>
                              </div>
                            </div>
                          </template>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='catalog-plans'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}

<script type="application/json" id="eb-plan-catalog-json">{$catalog_products_json nofilter}</script>
<script type="application/json" id="eb-assign-plans-json">{$assign_plans_json|default:'[]' nofilter}</script>
<script type="application/json" id="eb-assign-tenants-json">{$assign_tenants_json|default:'[]' nofilter}</script>
<script type="application/json" id="eb-s3-users-json">{$s3_users_json|default:'[]' nofilter}</script>
<script type="application/json" id="eb-comet-accounts-json">{$comet_accounts_json|default:'[]' nofilter}</script>
<select id="eb-assign-tenant-smarty-data" class="hidden" aria-hidden="true">
{foreach from=$assign_tenants item=c}
  <option value="{$c.public_id|escape}">{$c.name|escape}</option>
{/foreach}
</select>
<script type="application/json" id="eb-comet-accounts-smarty-data">
[
{foreach from=$comet_accounts item=ca name=cometAccounts}
  {ldelim}"tenant_public_id":"{$ca.tenant_public_id|escape:'javascript'}","comet_user_id":"{$ca.comet_user_id|escape:'javascript'}","tenant_name":"{$ca.tenant_name|escape:'javascript'}"{rdelim}{if !$smarty.foreach.cometAccounts.last},{/if}
{/foreach}
]
</script>
<script src="modules/addons/eazybackup/assets/js/catalog-plans.js"></script>
