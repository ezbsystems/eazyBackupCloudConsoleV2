{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
{capture assign=ebPhActions}
  <div class="flex flex-wrap items-center justify-end gap-2">
    <a href="{$modulelink}&a=ph-billing-payment-new" class="eb-btn eb-btn-primary eb-btn-sm">New Payment</a>
  </div>
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
                     statusFilter: 'succeeded',
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
                         succeeded: 'Succeeded',
                         processing: 'Processing',
                         requires_action: 'Requires Action',
                         requires_payment_method: 'Requires Payment Method',
                         canceled: 'Canceled',
                         all: 'All'
                       }[this.statusFilter] || 'Succeeded';
                     },
                     setSort(key) {
                       if (this.sortKey === key) {
                         this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                       } else {
                         this.sortKey = key;
                         this.sortDirection = 'asc';
                       }
                       this.refreshRows();
                     },
                     sortIndicator(key) {
                       if (this.sortKey !== key) return '';
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
                       if (this.filteredCount === 0) return 'Showing 0-0 of 0 payments';
                       const start = (this.currentPage - 1) * this.entriesPerPage + 1;
                       const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
                       return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' payments';
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
      <h2 class="eb-type-h4 text-[var(--eb-text-primary)]">One-Time Payments</h2>
      <p class="eb-page-description">Track manual charges, project billing, and payment intent activity.</p>
    </div>
    <div class="eb-table-toolbar">
      <div class="flex flex-wrap items-center gap-3">
                  <div class="relative" @click.away="entriesOpen = false">
                    <button type="button" @click="entriesOpen = !entriesOpen" class="eb-app-toolbar-button">
                      <span x-text="'Show ' + entriesPerPage"></span>
                      <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div x-show="entriesOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden !min-w-0" style="display: none;">
                      <template x-for="size in [10,25,50,100]" :key="'pay-entries-' + size">
                        <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="entriesPerPage === size ? 'is-active' : ''" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
                      </template>
                    </div>
                  </div>
                  <div class="relative" @click.away="statusOpen = false">
                    <button type="button" @click="statusOpen = !statusOpen" class="eb-app-toolbar-button">
                      <span x-text="'Status: ' + statusLabel()"></span>
                      <svg class="w-4 h-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div x-show="statusOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-64 overflow-hidden !min-w-0" style="display: none;">
                      <template x-for="option in [
                        { value: 'succeeded', label: 'Succeeded' },
                        { value: 'processing', label: 'Processing' },
                        { value: 'requires_action', label: 'Requires Action' },
                        { value: 'requires_payment_method', label: 'Requires Payment Method' },
                        { value: 'canceled', label: 'Canceled' },
                        { value: 'all', label: 'All' }
                      ]" :key="'pay-status-' + option.value">
                        <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="statusFilter === option.value ? 'is-active' : ''" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
                      </template>
                    </div>
                  </div>
      </div>
      <input type="text" x-model="search" placeholder="Search client, payment intent, or status" class="eb-input eb-app-toolbar-search">
    </div>

    <div class="eb-table-shell">
      <table class="eb-table">
        <thead>
                      <tr>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="eb-table-sort-button" @click="setSort('client')">Client <span x-text="sortIndicator('client')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="eb-table-sort-button" @click="setSort('amount')">Amount <span x-text="sortIndicator('amount')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="eb-table-sort-button" @click="setSort('currency')">Currency <span x-text="sortIndicator('currency')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="eb-table-sort-button" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="eb-table-sort-button" @click="setSort('created')">Created <span x-text="sortIndicator('created')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="eb-table-sort-button" @click="setSort('intent')">Payment Intent <span x-text="sortIndicator('intent')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="eb-table-sort-button" @click="setSort('stripe')">Stripe <span x-text="sortIndicator('stripe')"></span></button></th>
                      </tr>
        </thead>
        <tbody x-ref="tbody">
                      {if $rows|@count > 0}
                        {foreach from=$rows item=row}
                          {assign var=st value=$row.status|default:'-'}
                          <tr
                              data-row="payment"
                              data-client="{$row.tenant_name|default:'-'|escape}"
                              data-amount="{$row.amount/100|string_format:'%.2f'|escape}"
                              data-currency="{$row.currency|upper|default:'USD'|escape}"
                              data-status="{$st|escape}"
                              data-created="{$row.created|date_format:'%Y-%m-%d %H:%M'|escape}"
                              data-intent="{$row.stripe_payment_intent_id|default:'-'|escape}"
                              data-stripe="{if $msp.stripe_connect_id|default:'' && $row.stripe_payment_intent_id|default:''}open{else}-{/if}">
                            <td class="eb-table-primary">{$row.tenant_name|default:'-'}</td>
                            <td>{$row.amount/100|string_format:'%.2f'}</td>
                            <td>{$row.currency|upper|default:'USD'}</td>
                            <td>
                              <span class="eb-badge eb-badge--dot {if $st=='succeeded'}eb-badge--success{elseif $st=='requires_payment_method' || $st=='requires_action' || $st=='processing'}eb-badge--warning{elseif $st=='canceled'}eb-badge--default{else}eb-badge--danger{/if}">{$st}</span>
                            </td>
                            <td>{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
                            <td class="eb-table-mono">{$row.stripe_payment_intent_id|default:'-'}</td>
                            <td>{assign var=acct value=$msp.stripe_connect_id|default:''}{if $acct}<a href="https://dashboard.stripe.com/connect/accounts/{$acct}/payments/{$row.stripe_payment_intent_id}" target="_blank" rel="noopener" class="font-medium text-[var(--eb-primary)] hover:underline">Open</a>{else}-{/if}</td>
                          </tr>
                        {/foreach}
                        <tr x-ref="noResults" style="display: none;">
                          <td colspan="7" class="px-4 py-10 text-center text-sm text-[var(--eb-text-muted)]">No payments found.</td>
                        </tr>
                      {else}
                        <tr>
                          <td colspan="7" class="px-4 py-10 text-center text-sm text-[var(--eb-text-muted)]">
                            <div class="flex flex-col items-center gap-2">
                              <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--eb-bg-overlay)]">
                                <svg class="h-4 w-4 text-[var(--eb-text-muted)]" viewBox="0 0 24 24" fill="none">
                                  <path d="M5 12h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                  <path d="M12 5v14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                              </div>
                              <p>No payments found yet.</p>
                              <a href="{$modulelink}&a=ph-billing-payment-new" class="mt-1 inline-flex items-center gap-2 text-xs font-medium text-[var(--eb-primary)] hover:underline">New one-time payment</a>
                            </div>
                          </td>
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
  ebPhSidebarPage='billing-payments'
  ebPhTitle='Payments'
  ebPhDescription='View and manage one-time charges for setup fees and project work.'
  ebPhActions=$ebPhActions
  ebPhContent=$ebPhContent
}
