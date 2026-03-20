{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='billing-payments'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
            <div>
              <h1 class="text-2xl font-semibold tracking-tight">Payments</h1>
              <p class="mt-1 text-sm text-slate-400">View and manage one-time charges for setup fees and project work.</p>
            </div>
            <div class="shrink-0">
              <a href="{$modulelink}&a=ph-billing-payment-new" class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">New Payment</a>
            </div>
          </div>
          <div class="p-6">
            <input type="hidden" id="ph-payments-csrf" value="{$token|escape}" />
            <section class="bg-slate-950/70 rounded-2xl border border-slate-800/80 p-4">
              <div class="px-6 py-5">
                <h2 class="text-lg font-medium">One-Time Payments</h2>
                <p class="mt-1 text-sm text-slate-400">Track manual charges, project billing, and payment intent activity.</p>
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
                <div class="mb-4 flex flex-col xl:flex-row xl:items-center gap-3">
                  <div class="relative" @click.away="entriesOpen = false">
                    <button type="button" @click="entriesOpen = !entriesOpen" class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                      <span x-text="'Show ' + entriesPerPage"></span>
                      <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div x-show="entriesOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 mt-2 w-40 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
                      <template x-for="size in [10,25,50,100]" :key="'pay-entries-' + size">
                        <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="entriesPerPage === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
                      </template>
                    </div>
                  </div>
                  <div class="relative" @click.away="statusOpen = false">
                    <button type="button" @click="statusOpen = !statusOpen" class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                      <span x-text="'Status: ' + statusLabel()"></span>
                      <svg class="w-4 h-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div x-show="statusOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
                      <template x-for="option in [
                        { value: 'succeeded', label: 'Succeeded' },
                        { value: 'processing', label: 'Processing' },
                        { value: 'requires_action', label: 'Requires Action' },
                        { value: 'requires_payment_method', label: 'Requires Payment Method' },
                        { value: 'canceled', label: 'Canceled' },
                        { value: 'all', label: 'All' }
                      ]" :key="'pay-status-' + option.value">
                        <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="statusFilter === option.value ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
                      </template>
                    </div>
                  </div>
                  <div class="flex-1"></div>
                  <input type="text" x-model="search" placeholder="Search client, payment intent, or status" class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
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
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('intent')">Payment Intent <span x-text="sortIndicator('intent')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('stripe')">Stripe <span x-text="sortIndicator('stripe')"></span></button></th>
                        <th class="px-4 py-3 text-left font-medium">Actions</th>
                      </tr>
                    </thead>
                    <tbody x-ref="tbody" class="divide-y divide-slate-800">
                      {if $rows|@count > 0}
                        {foreach from=$rows item=row}
                          {assign var=st value=$row.status|default:'-'}
                          <tr class="hover:bg-slate-800/50"
                              data-row="payment"
                              data-client="{$row.tenant_name|default:'-'|escape}"
                              data-amount="{$row.amount/100|string_format:'%.2f'|escape}"
                              data-currency="{$row.currency|upper|default:'USD'|escape}"
                              data-status="{$st|escape}"
                              data-created="{$row.created|date_format:'%Y-%m-%d %H:%M'|escape}"
                              data-intent="{$row.stripe_payment_intent_id|default:'-'|escape}"
                              data-stripe="{if $msp.stripe_connect_id|default:'' && $row.stripe_payment_intent_id|default:''}open{else}-{/if}">
                            <td class="px-4 py-3 text-left font-medium text-slate-100">{$row.tenant_name|default:'-'}</td>
                            <td class="px-4 py-3 text-left text-slate-300">{$row.amount/100|string_format:'%.2f'}</td>
                            <td class="px-4 py-3 text-left text-slate-300">{$row.currency|upper|default:'USD'}</td>
                            <td class="px-4 py-3 text-left">
                              <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $st=='succeeded'}bg-emerald-500/15 text-emerald-200{elseif $st=='requires_payment_method' || $st=='requires_action' || $st=='processing'}bg-amber-500/15 text-amber-200{elseif $st=='canceled'}bg-slate-700 text-slate-300{else}bg-rose-500/15 text-rose-200{/if}"><span class="h-1.5 w-1.5 rounded-full {if $st=='succeeded'}bg-emerald-400{elseif $st=='requires_payment_method' || $st=='requires_action' || $st=='processing'}bg-amber-400{else}bg-slate-500{/if}"></span>{$st}</span>
                            </td>
                            <td class="px-4 py-3 text-left text-slate-300">{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
                            <td class="px-4 py-3 text-left text-slate-300">{$row.stripe_payment_intent_id|default:'-'}</td>
                            <td class="px-4 py-3 text-left">{assign var=acct value=$msp.stripe_connect_id|default:''}{if $acct}<a href="https://dashboard.stripe.com/connect/accounts/{$acct}/payments/{$row.stripe_payment_intent_id}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">Open</a>{else}-{/if}</td>
                            <td class="px-4 py-3 text-left">{if $st=='succeeded' && $acct && $row.stripe_payment_intent_id|default:''}<button type="button" class="rounded-lg border border-amber-600/50 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-200 hover:bg-amber-500/20" data-refund-pi="{$row.stripe_payment_intent_id|escape}">Refund</button>{else}<span class="text-slate-500">—</span>{/if}</td>
                          </tr>
                        {/foreach}
                        <tr x-ref="noResults" style="display: none;">
                          <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">No payments found.</td>
                        </tr>
                      {else}
                        <tr>
                          <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">
                            <div class="flex flex-col items-center gap-2">
                              <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800/80">
                                <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none">
                                  <path d="M5 12h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                  <path d="M12 5v14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                              </div>
                              <p>No payments found yet.</p>
                              <a href="{$modulelink}&a=ph-billing-payment-new" class="mt-1 inline-flex items-center gap-2 text-xs font-medium text-sky-400 hover:text-sky-300">New one-time payment</a>
                            </div>
                          </td>
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
<script>
(function () {
  var csrfEl = document.getElementById('ph-payments-csrf');
  var refundUrl = '{$modulelink}&a=ph-billing-refund';
  document.addEventListener('click', function (ev) {
    var t = ev.target && ev.target.closest ? ev.target.closest('[data-refund-pi]') : null;
    if (!t) return;
    ev.preventDefault();
    var pi = t.getAttribute('data-refund-pi');
    if (!csrfEl || !pi) return;
    if (!window.confirm('Issue a full refund for this payment?')) return;
    t.disabled = true;
    var body = new URLSearchParams();
    body.set('token', csrfEl.value);
    body.set('payment_intent_id', pi);
    fetch(refundUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.status === 'success') {
          window.location.reload();
          return;
        }
        window.alert((j && j.message) ? String(j.message) : 'Refund failed');
        t.disabled = false;
      })
      .catch(function () {
        window.alert('Refund request failed');
        t.disabled = false;
      });
  });
})();
</script>


