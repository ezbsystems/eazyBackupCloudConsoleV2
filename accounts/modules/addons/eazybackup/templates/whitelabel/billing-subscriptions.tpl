{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
{capture assign=ebPhDescription}
  Search, filter, and review customer subscriptions linked to your Stripe Connect account.
{/capture}
{capture assign=ebPhContent}
  <section class="eb-subpanel overflow-hidden"
           x-data="{
                     entriesOpen: false,
                     statusOpen: false,
                     search: '{$q|escape:'javascript'}',
                     entriesPerPage: 25,
                     currentPage: 1,
                     sortKey: 'client',
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
                         past_due: 'Past Due',
                         incomplete: 'Incomplete',
                         trialing: 'Trialing',
                         canceled: 'Canceled',
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
                       if (this.filteredCount === 0) return 'Showing 0-0 of 0 subscriptions';
                       const start = (this.currentPage - 1) * this.entriesPerPage + 1;
                       const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
                       return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' subscriptions';
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
    <div class="mb-5 flex flex-col gap-1 border-b border-[var(--eb-border-subtle)] pb-4">
      <h2 class="eb-type-h4 text-[var(--eb-text-primary)]">Customer Subscriptions</h2>
      <p class="eb-page-description">Review billing relationships for customer tenants connected to your Stripe account.</p>
    </div>
    <div class="eb-table-toolbar">
      <div class="flex flex-wrap items-center gap-3">
                  <div class="relative" @click.away="entriesOpen = false">
                    <button type="button"
                            @click="entriesOpen = !entriesOpen"
                            class="eb-app-toolbar-button">
                      <span x-text="'Show ' + entriesPerPage"></span>
                      <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                      </svg>
                    </button>
                    <div x-show="entriesOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden !min-w-0"
                         style="display: none;">
                      <template x-for="size in [10,25,50,100]" :key="'subs-entries-' + size">
                        <button type="button"
                                class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]"
                                :class="entriesPerPage === size ? 'is-active' : ''"
                                @click="setEntries(size); entriesOpen = false;">
                          <span x-text="size"></span>
                        </button>
                      </template>
                    </div>
                  </div>
                  <div class="relative" @click.away="statusOpen = false">
                    <button type="button"
                            @click="statusOpen = !statusOpen"
                            class="eb-app-toolbar-button">
                      <span x-text="'Status: ' + statusLabel()"></span>
                      <svg class="w-4 h-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                      </svg>
                    </button>
                    <div x-show="statusOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-56 overflow-hidden !min-w-0"
                         style="display: none;">
                      <template x-for="option in [
                        { value: 'active', label: 'Active' },
                        { value: 'past_due', label: 'Past Due' },
                        { value: 'incomplete', label: 'Incomplete' },
                        { value: 'trialing', label: 'Trialing' },
                        { value: 'canceled', label: 'Canceled' },
                        { value: 'all', label: 'All' }
                      ]" :key="'subs-status-' + option.value">
                        <button type="button"
                                class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]"
                                :class="statusFilter === option.value ? 'is-active' : ''"
                                @click="setStatus(option.value); statusOpen = false;">
                          <span x-text="option.label"></span>
                        </button>
                      </template>
                    </div>
                  </div>
      </div>
      <input type="text"
             x-model="search"
             placeholder="Search client, plan, or subscription id"
             class="eb-input eb-app-toolbar-search">
    </div>

    <div class="eb-table-shell">
      <table class="eb-table">
        <thead>
                      <tr>
                        <th class="px-4 py-3 text-left font-medium">
                          <button type="button" class="eb-table-sort-button" @click="setSort('client')">Client <span x-text="sortIndicator('client')"></span></button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                          <button type="button" class="eb-table-sort-button" @click="setSort('plan')">Plan <span x-text="sortIndicator('plan')"></span></button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                          <button type="button" class="eb-table-sort-button" @click="setSort('price')">Price <span x-text="sortIndicator('price')"></span></button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                          <button type="button" class="eb-table-sort-button" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                          <button type="button" class="eb-table-sort-button" @click="setSort('started')">Started <span x-text="sortIndicator('started')"></span></button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                          <button type="button" class="eb-table-sort-button" @click="setSort('stripe')">Stripe ID <span x-text="sortIndicator('stripe')"></span></button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium">
                          <button type="button" class="eb-table-sort-button" @click="setSort('stripe_link')">Stripe <span x-text="sortIndicator('stripe_link')"></span></button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium">Actions</th>
                      </tr>
        </thead>
        <tbody x-ref="tbody">
                      {if $rows|@count > 0}
                        {foreach from=$rows item=row}
                          {assign var=st value=$row.stripe_status|default:'-'}
                          <tr
                              data-row="subscription"
                              data-client="{$row.tenant_name|default:'-'|escape}"
                              data-plan="{$row.plan_name|default:'-'|escape}"
                              data-price="{$row.price_nickname|default:'-'|escape}{if $row.price_cycle} ({$row.price_cycle|escape}){/if}"
                              data-status="{$st|escape}"
                              data-started="{$row.started_at|default:'-'|escape}"
                              data-stripe="{$row.stripe_subscription_id|default:'-'|escape}"
                              data-stripe_link="{if $msp.stripe_connect_id|default:'' && $row.stripe_subscription_id|default:''}open{else}-{/if}">
                            <td class="eb-table-primary">{$row.tenant_name|default:'-'}</td>
                            <td>{$row.plan_name|default:'-'}</td>
                            <td>{$row.price_nickname|default:'-'}{if $row.price_cycle} ({$row.price_cycle}){/if}</td>
                            <td>
                              <span class="eb-badge eb-badge--dot {if $st=='active'}eb-badge--success{elseif $st=='past_due' || $st=='incomplete' || $st=='trialing'}eb-badge--warning{elseif $st=='canceled'}eb-badge--default{else}eb-badge--danger{/if}">{$st}</span>
                            </td>
                            <td>{$row.started_at|default:'-'}</td>
                            <td class="eb-table-mono">{$row.stripe_subscription_id|default:'-'}</td>
                            <td>
                              {assign var=acct value=$msp.stripe_connect_id|default:''}
                              {if $acct}
                                <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/subscriptions/{$row.stripe_subscription_id}" target="_blank" rel="noopener" class="font-medium text-[var(--eb-primary)] hover:underline">Open</a>
                              {else}-{/if}
                            </td>
                            <td>
                              {if $row.tenant_public_id|default:'' neq '' && $row.tenant_status|default:'' neq 'deleted'}
                                <a href="{$modulelink}&a=ph-tenant&id={$row.tenant_public_id|escape:'url'}" class="eb-btn eb-btn-secondary eb-btn-xs">View Client</a>
                              {else}
                                <span class="eb-badge eb-badge--default">Deleted tenant</span>
                              {/if}
                            </td>
                          </tr>
                        {/foreach}
                        <tr x-ref="noResults" style="display: none;">
                          <td colspan="8" class="px-4 py-8 text-center text-[var(--eb-text-muted)]">No subscriptions found.</td>
                        </tr>
                      {else}
                        <tr>
                          <td colspan="8" class="px-4 py-8 text-center text-[var(--eb-text-muted)]">No subscriptions found.</td>
                        </tr>
                      {/if}
        </tbody>
      </table>
    </div>
    <div class="eb-table-pagination">
      <div x-text="pageSummary()"></div>
      <div class="flex items-center gap-2">
        <button type="button" @click="prevPage()" :disabled="currentPage <= 1" class="eb-table-pagination-button">Prev</button>
        <span class="text-[var(--eb-text-muted)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
        <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages()" class="eb-table-pagination-button">Next</button>
      </div>
    </div>
  </section>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='billing-subscriptions'
  ebPhTitle='Subscriptions'
  ebPhDescription=$ebPhDescription
  ebPhContent=$ebPhContent
}
