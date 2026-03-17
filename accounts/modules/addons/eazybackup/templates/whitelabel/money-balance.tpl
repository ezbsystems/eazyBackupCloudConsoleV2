{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='money-balance'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
            <div>
              <h1 class="text-2xl font-semibold tracking-tight">Balance &amp; Reports</h1>
              <p class="mt-1 text-sm text-slate-400">Stripe Connect balance and transaction history.</p>
            </div>
            <div class="shrink-0">
              <a href="{$modulelink}&a=ph-money-balance&from={$filters.from|escape}&to={$filters.to|escape}&type={$filters.type|escape}&limit={$filters.limit|default:50}&export=csv" class="inline-flex items-center rounded-xl px-4 py-2 text-sm text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Export CSV</a>
            </div>
          </div>
          <div class="p-6">
    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800">
        <h2 class="text-lg font-medium text-slate-100">Balance & Reports</h2>
      </div>
      <div class="px-6 py-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400 text-sm">Available</div>
            <div class="text-slate-100 text-2xl font-semibold mt-1">
              {assign var=avail value=$balance.available|default:[]}
              {if $avail|@count > 0}
                {$avail.0.amount|default:0} {$avail.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400 text-sm">Pending</div>
            <div class="text-slate-100 text-2xl font-semibold mt-1">
              {assign var=pending value=$balance.pending|default:[]}
              {if $pending|@count > 0}
                {$pending.0.amount|default:0} {$pending.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400 text-sm">Quick Links</div>
            <div class="text-slate-100 mt-1">
              {if $dashboardUrl}
                <a href="{$dashboardUrl}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">Open in Stripe Dashboard</a>
              {else}-{/if}
            </div>
          </div>
        </div>

        <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg overflow-hidden"
             x-data="{
               entriesOpen: false,
               typeOpen: false,
               search: '',
               entriesPerPage: 25,
               currentPage: 1,
               sortKey: 'created',
               sortDirection: 'desc',
               typeFilter: 'all',
               filteredCount: 0,
               rows: [],
               typeOptions: [],
               init() {
                 this.rows = Array.from(this.$refs.tbody.querySelectorAll('tr[data-row]'));
                 this.typeOptions = Array.from(new Set(this.rows.map((row) => row.getAttribute('data-type') || '').filter(Boolean))).sort();
                 this.$watch('search', () => { this.currentPage = 1; this.refreshRows(); });
                 this.refreshRows();
               },
               setEntries(size) {
                 this.entriesPerPage = Number(size) || 25;
                 this.currentPage = 1;
                 this.refreshRows();
               },
               setType(type) {
                 this.typeFilter = type;
                 this.currentPage = 1;
                 this.refreshRows();
               },
               formatTypeLabel(value) {
                 if (!value || value === 'all') return 'All';
                 return value.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
               },
               typeLabel() {
                 return this.formatTypeLabel(this.typeFilter);
               },
               typeOptionsList() {
                 return [{ value: 'all', label: 'All' }].concat(this.typeOptions.map((value) => ({ value, label: this.formatTypeLabel(value) })));
               },
               setSort(key) {
                 if (this.sortKey === key) {
                   this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                 } else {
                   this.sortKey = key;
                   this.sortDirection = key === 'created' || key === 'available' ? 'desc' : 'asc';
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
                   const type = row.getAttribute('data-type') || '';
                   const matchesType = this.typeFilter === 'all' ? true : type === this.typeFilter;
                   const matchesQuery = !query ? true : (row.textContent || '').toLowerCase().includes(query);
                   return matchesType && matchesQuery;
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
                 if (this.filteredCount === 0) return 'Showing 0-0 of 0 transactions';
                 const start = (this.currentPage - 1) * this.entriesPerPage + 1;
                 const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
                 return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' transactions';
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
                <template x-for="size in [10,25,50,100]" :key="'balance-entries-' + size">
                  <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="entriesPerPage === size ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
                </template>
              </div>
            </div>
            <div class="relative" @click.away="typeOpen = false">
              <button type="button" @click="typeOpen = !typeOpen" class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
                <span x-text="'Type: ' + typeLabel()"></span>
                <svg class="w-4 h-4 transition-transform" :class="typeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
              </button>
              <div x-show="typeOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute left-0 mt-2 w-56 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden" style="display: none;">
                <template x-for="option in typeOptionsList()" :key="'balance-type-' + option.value">
                  <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="typeFilter === option.value ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setType(option.value); typeOpen = false;"><span x-text="option.label"></span></button>
                </template>
              </div>
            </div>
            <div class="flex-1"></div>
            <input type="text" x-model="search" placeholder="Search ID, type, description, or currency" class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm text-slate-300">
              <thead class="bg-slate-900/80 text-slate-300">
                <tr>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('id')">ID <span x-text="sortIndicator('id')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('amount')">Amount <span x-text="sortIndicator('amount')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('currency')">Currency <span x-text="sortIndicator('currency')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('type')">Type <span x-text="sortIndicator('type')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('description')">Description <span x-text="sortIndicator('description')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('created')">Created <span x-text="sortIndicator('created')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('available')">Available On <span x-text="sortIndicator('available')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('fee')">Fee <span x-text="sortIndicator('fee')"></span></button></th>
                  <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('net')">Net <span x-text="sortIndicator('net')"></span></button></th>
                </tr>
              </thead>
              <tbody x-ref="tbody" class="divide-y divide-slate-800">
                {if $transactions|@count > 0}
                  {foreach from=$transactions item=row}
                    <tr class="hover:bg-slate-800/50"
                        data-row="transaction"
                        data-id="{$row.id|default:'-'|escape}"
                        data-amount="{$row.amount|default:0|escape}"
                        data-currency="{$row.currency|upper|default:'USD'|escape}"
                        data-type="{$row.type|default:'-'|escape}"
                        data-description="{$row.description|default:'-'|escape}"
                        data-created="{$row.created|default:0|escape}"
                        data-available="{$row.available_on|default:0|escape}"
                        data-fee="{$row.fee|default:0|escape}"
                        data-net="{$row.net|default:0|escape}">
                      <td class="px-4 py-3">{$row.id|default:'-'}</td>
                      <td class="px-4 py-3">{$row.amount|default:0}</td>
                      <td class="px-4 py-3">{$row.currency|upper|default:'USD'}</td>
                      <td class="px-4 py-3">{$row.type|default:'-'}</td>
                      <td class="px-4 py-3">{$row.description|default:'-'}</td>
                      <td class="px-4 py-3">{$row.created|default:0}</td>
                      <td class="px-4 py-3">{$row.available_on|default:0}</td>
                      <td class="px-4 py-3">{$row.fee|default:0}</td>
                      <td class="px-4 py-3">{$row.net|default:0}</td>
                    </tr>
                  {/foreach}
                  <tr x-ref="noResults" style="display: none;">
                    <td colspan="9" class="px-4 py-6 text-center text-slate-400">No transactions matched the current filters.</td>
                  </tr>
                {else}
                  <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-slate-400">No transactions found.</td>
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

      </div>
    </section>
          </div>
        </main>
      </div>
    </div>
  </div>
</div>
