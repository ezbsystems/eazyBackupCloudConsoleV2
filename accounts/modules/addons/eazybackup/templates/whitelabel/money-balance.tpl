{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Balance &amp; Reports</h1>
              <p class="eb-page-description mt-1">Stripe Connect balance and transaction history.</p>
            </div>
            <div class="shrink-0">
              <a href="{$modulelink}&a=ph-money-balance&from={$filters.from|escape}&to={$filters.to|escape}&type={$filters.type|escape}&limit={$filters.limit|default:50}&export=csv" class="eb-btn eb-btn-outline eb-btn-sm">Export CSV</a>
            </div>
          </div>
          <div class="p-6">
    <section class="eb-card-raised !p-0 overflow-hidden">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
        <h2 class="eb-app-card-title">Balance snapshot</h2>
      </div>
      <div class="px-6 py-6">

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="eb-stat-card">
            <div class="eb-stat-label">Available</div>
            <div class="eb-type-stat mt-1">
              {assign var=avail value=$balance.available|default:[]}
              {if $avail|@count > 0}
                {$avail.0.amount/100|string_format:'%.2f'} {$avail.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="eb-stat-card">
            <div class="eb-stat-label">Pending</div>
            <div class="eb-type-stat mt-1">
              {assign var=pending value=$balance.pending|default:[]}
              {if $pending|@count > 0}
                {$pending.0.amount/100|string_format:'%.2f'} {$pending.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="eb-stat-card">
            <div class="eb-stat-label">Quick links</div>
            <div class="mt-1 text-sm font-medium text-[var(--eb-text-primary)]">
              {if $dashboardUrl}
                <a href="{$dashboardUrl}" target="_blank" rel="noopener" class="font-medium text-[var(--eb-primary)] hover:underline">Open in Stripe Dashboard</a>
              {else}-{/if}
            </div>
          </div>
        </div>

        <div class="eb-subpanel w-full max-w-full min-w-0 overflow-hidden !p-5"
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
          <div class="eb-table-toolbar mb-4 flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="flex flex-wrap items-center gap-3">
            <div class="relative" @click.away="entriesOpen = false">
              <button type="button" @click="entriesOpen = !entriesOpen" class="eb-btn eb-btn-outline eb-btn-sm inline-flex items-center gap-2">
                <span x-text="'Show ' + entriesPerPage"></span>
                <svg class="h-4 w-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
              </button>
              <div x-show="entriesOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden !min-w-0" style="display: none;">
                <template x-for="size in [10,25,50,100]" :key="'balance-entries-' + size">
                  <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="entriesPerPage === size ? 'is-active' : ''" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
                </template>
              </div>
            </div>
            <div class="relative" @click.away="typeOpen = false">
              <button type="button" @click="typeOpen = !typeOpen" class="eb-btn eb-btn-outline eb-btn-sm inline-flex items-center gap-2">
                <span x-text="'Type: ' + typeLabel()"></span>
                <svg class="h-4 w-4 transition-transform" :class="typeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
              </button>
              <div x-show="typeOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-56 overflow-hidden !min-w-0" style="display: none;">
                <template x-for="option in typeOptionsList()" :key="'balance-type-' + option.value">
                  <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="typeFilter === option.value ? 'is-active' : ''" @click="setType(option.value); typeOpen = false;"><span x-text="option.label"></span></button>
                </template>
              </div>
            </div>
            </div>
            <div class="flex-1"></div>
            <input type="text" x-model="search" placeholder="Search ID, type, description, or currency" class="eb-toolbar-search w-full xl:w-80">
          </div>

          <div class="eb-table-shell">
            <table class="eb-table min-w-full text-sm">
              <thead>
                <tr>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('id')">ID <span x-text="sortIndicator('id')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('amount')">Amount <span x-text="sortIndicator('amount')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('currency')">Currency <span x-text="sortIndicator('currency')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('type')">Type <span x-text="sortIndicator('type')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('description')">Description <span x-text="sortIndicator('description')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('created')">Created <span x-text="sortIndicator('created')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('available')">Available On <span x-text="sortIndicator('available')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('fee')">Fee <span x-text="sortIndicator('fee')"></span></button></th>
                  <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('net')">Net <span x-text="sortIndicator('net')"></span></button></th>
                </tr>
              </thead>
              <tbody x-ref="tbody">
                {if $transactions|@count > 0}
                  {foreach from=$transactions item=row}
                    <tr
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
                      <td class="eb-table-mono">{$row.id|default:'-'}</td>
                      <td>{$row.amount/100|string_format:'%.2f'}</td>
                      <td>{$row.currency|upper|default:'USD'}</td>
                      <td>{$row.type|default:'-'}</td>
                      <td>{$row.description|default:'-'}</td>
                      <td>{if $row.created|default:0}{$row.created|date_format:'%Y-%m-%d %H:%M'}{else}-{/if}</td>
                      <td>{if $row.available_on|default:0}{$row.available_on|date_format:'%Y-%m-%d'}{else}-{/if}</td>
                      <td>{$row.fee/100|string_format:'%.2f'}</td>
                      <td>{$row.net/100|string_format:'%.2f'}</td>
                    </tr>
                  {/foreach}
                  <tr x-ref="noResults" style="display: none;">
                    <td colspan="9" class="px-4 py-6 text-center text-sm text-[var(--eb-text-muted)]">No transactions matched the current filters.</td>
                  </tr>
                {else}
                  <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-sm text-[var(--eb-text-muted)]">No transactions found.</td>
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
        </div>

      </div>
    </section>
          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='money-balance'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}
