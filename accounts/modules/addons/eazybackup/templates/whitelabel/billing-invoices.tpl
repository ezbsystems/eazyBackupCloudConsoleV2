{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='billing-invoices'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
            <div>
              <h1 class="text-2xl font-semibold tracking-tight">Invoices</h1>
              <p class="mt-1 text-sm text-slate-400">Search and view invoices for your Stripe Connect account.</p>
            </div>
          </div>
          <div class="p-6">
            <section class="bg-slate-950/70 rounded-2xl border border-slate-800/80 p-4">
              <div class="px-6 py-5">
                <h2 class="text-lg font-medium">Customer Invoices</h2>
                <p class="mt-1 text-sm text-slate-400">Review invoice history and Stripe-hosted invoice links for connected tenants.</p>
              </div>
              <div class="border-t border-white/10"></div>
              <div class="p-4"
                   x-data="{
                     entriesOpen: false,
                     statusOpen: false,
                     search: '{$q|escape:'javascript'}',
                     entriesPerPage: 25,
                     currentPage: 1,
                     sortKey: 'client',
                     sortDirection: 'asc',
                     statusFilter: 'paid',
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
                         paid: 'Paid',
                         open: 'Open',
                         draft: 'Draft',
                         uncollectible: 'Uncollectible',
                         void: 'Void',
                         all: 'All'
                       }[this.statusFilter] || 'Paid';
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
                       if (this.filteredCount === 0) return 'Showing 0-0 of 0 invoices';
                       const start = (this.currentPage - 1) * this.entriesPerPage + 1;
                       const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
                       return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' invoices';
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
                      <template x-for="size in [10,25,50,100]" :key="'inv-entries-' + size">
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
                        { value: 'paid', label: 'Paid' },
                        { value: 'open', label: 'Open' },
                        { value: 'draft', label: 'Draft' },
                        { value: 'uncollectible', label: 'Uncollectible' },
                        { value: 'void', label: 'Void' },
                        { value: 'all', label: 'All' }
                      ]" :key="'inv-status-' + option.value">
                        <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="statusFilter === option.value ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
                      </template>
                    </div>
                  </div>
                  <div class="flex-1"></div>
                  <input type="text" x-model="search" placeholder="Search tenant, invoice id, or status" class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-800">
                  <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900/80 text-slate-300">
                      <tr>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('client')">Client <span x-text="sortIndicator('client')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('amount')">Amount <span x-text="sortIndicator('amount')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('currency')">Currency <span x-text="sortIndicator('currency')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('created')">Created <span x-text="sortIndicator('created')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('invoice')">Invoice <span x-text="sortIndicator('invoice')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('stripe')">Stripe <span x-text="sortIndicator('stripe')"></span></button></th>
                      </tr>
                    </thead>
                    <tbody x-ref="tbody" class="divide-y divide-slate-800">
                      {if $rows|@count > 0}
                        {foreach from=$rows item=row}
                          {assign var=st value=$row.status|default:'-'}
                          <tr class="hover:bg-slate-800/50"
                              data-row="invoice"
                              data-client="{$row.tenant_name|default:'-'|escape}"
                              data-amount="{$row.amount_total/100|string_format:'%.2f'|escape}"
                              data-currency="{$row.currency|upper|default:'USD'|escape}"
                              data-status="{$st|escape}"
                              data-created="{$row.created|date_format:'%Y-%m-%d %H:%M'|escape}"
                              data-invoice="{if $row.hosted_invoice_url}view{else}-{/if}"
                              data-stripe="{$row.stripe_invoice_id|default:'-'|escape}">
                            <td class="px-4 py-3 text-left font-medium text-slate-100">{$row.tenant_name|default:'-'}</td>
                            <td class="px-4 py-3 text-left text-slate-300">{$row.amount_total/100|string_format:'%.2f'}</td>
                            <td class="px-4 py-3 text-left text-slate-300">{$row.currency|upper|default:'USD'}</td>
                            <td class="px-4 py-3 text-left">
                              <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $st=='paid'}bg-emerald-500/15 text-emerald-200{elseif $st=='open' || $st=='uncollectible' || $st=='draft'}bg-amber-500/15 text-amber-200{elseif $st=='void'}bg-slate-700 text-slate-300{else}bg-rose-500/15 text-rose-200{/if}"><span class="h-1.5 w-1.5 rounded-full {if $st=='paid'}bg-emerald-400{elseif $st=='open' || $st=='uncollectible' || $st=='draft'}bg-amber-400{else}bg-slate-500{/if}"></span>{$st}</span>
                            </td>
                            <td class="px-4 py-3 text-left text-slate-300">{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
                            <td class="px-4 py-3 text-left">{if $row.hosted_invoice_url}<a href="{$row.hosted_invoice_url}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">View</a>{else}-{/if}</td>
                            <td class="px-4 py-3 text-left">{assign var=acct value=$msp.stripe_connect_id|default:''}{if $acct}<a href="https://dashboard.stripe.com/connect/accounts/{$acct}/invoices/{$row.stripe_invoice_id}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">Open</a>{else}-{/if}</td>
                          </tr>
                        {/foreach}
                        <tr x-ref="noResults" style="display: none;">
                          <td colspan="7" class="px-4 py-8 text-center text-slate-400">No invoices found.</td>
                        </tr>
                      {else}
                        <tr>
                          <td colspan="7" class="px-4 py-8 text-center text-slate-400">No invoices found.</td>
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
          </div>
        </main>
      </div>
    </div>
  </div>
</div>
