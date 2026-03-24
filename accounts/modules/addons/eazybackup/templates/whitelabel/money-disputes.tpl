{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Disputes</h1>
              <p class="eb-page-description mt-1">View and search Stripe Connect disputes.</p>
            </div>
            <div class="shrink-0">
              <button type="button" id="eb-refresh-disputes" class="eb-btn eb-btn-primary eb-btn-sm">Refresh last 30 days</button>
            </div>
          </div>
          <div class="p-6">
            <section class="eb-card-raised !p-0 overflow-hidden">
              <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
                <h2 class="eb-app-card-title">Stripe Disputes</h2>
                <p class="eb-page-description mt-1">Review dispute activity, evidence deadlines, and charge references.</p>
              </div>
              <div class="px-6 py-6"
                   x-data="{
                     entriesOpen: false,
                     statusOpen: false,
                     search: '{$q|escape:'javascript'}',
                     entriesPerPage: 25,
                     currentPage: 1,
                     sortKey: 'dispute',
                     sortDirection: 'asc',
                     statusFilter: 'needs_response',
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
                         needs_response: 'Needs Response',
                         warning_needs_response: 'Warning Needs Response',
                         won: 'Won',
                         lost: 'Lost',
                         all: 'All'
                       }[this.statusFilter] || 'Needs Response';
                     },
                     setSort(key) {
                       if (key === 'stripe') return;
                       if (this.sortKey === key) {
                         this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                       } else {
                         this.sortKey = key;
                         this.sortDirection = 'asc';
                       }
                       this.refreshRows();
                     },
                     sortIndicator(key) {
                       if (this.sortKey !== key || key === 'stripe') return '';
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
                       if (this.filteredCount === 0) return 'Showing 0-0 of 0 disputes';
                       const start = (this.currentPage - 1) * this.entriesPerPage + 1;
                       const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
                       return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' disputes';
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
                      <template x-for="size in [10,25,50,100]" :key="'disputes-entries-' + size">
                        <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="entriesPerPage === size ? 'is-active' : ''" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
                      </template>
                    </div>
                  </div>
                  <div class="relative" @click.away="statusOpen = false">
                    <button type="button" @click="statusOpen = !statusOpen" class="eb-btn eb-btn-outline eb-btn-sm inline-flex items-center gap-2">
                      <span x-text="'Status: ' + statusLabel()"></span>
                      <svg class="h-4 w-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div x-show="statusOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-64 overflow-hidden !min-w-0" style="display: none;">
                      <template x-for="option in [
                        { value: 'needs_response', label: 'Needs Response' },
                        { value: 'warning_needs_response', label: 'Warning Needs Response' },
                        { value: 'won', label: 'Won' },
                        { value: 'lost', label: 'Lost' },
                        { value: 'all', label: 'All' }
                      ]" :key="'disputes-status-' + option.value">
                        <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="statusFilter === option.value ? 'is-active' : ''" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
                      </template>
                    </div>
                  </div>
                  </div>
                  <div class="flex-1"></div>
                  <input type="text" x-model="search" placeholder="Search dispute ID, status, reason, or charge" class="eb-toolbar-search w-full xl:w-80">
                </div>
                <div class="eb-table-shell">
                  <table class="eb-table min-w-full text-sm">
                    <thead>
                      <tr>
                        <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('dispute')">Dispute ID <span x-text="sortIndicator('dispute')"></span></button></th>
                        <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('amount')">Amount <span x-text="sortIndicator('amount')"></span></button></th>
                        <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('currency')">Currency <span x-text="sortIndicator('currency')"></span></button></th>
                        <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('reason')">Reason <span x-text="sortIndicator('reason')"></span></button></th>
                        <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button></th>
                        <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('evidence')">Evidence Due <span x-text="sortIndicator('evidence')"></span></button></th>
                        <th class="text-left"><button type="button" class="eb-table-sort-button" @click="setSort('charge')">Charge ID <span x-text="sortIndicator('charge')"></span></button></th>
                        <th class="text-left">Stripe</th>
                      </tr>
                    </thead>
                    <tbody x-ref="tbody">
                      {if $rows|@count > 0}
                        {foreach from=$rows item=row}
                          {assign var=st value=$row.status|default:'-'}
                          <tr
                              data-row="dispute"
                              data-dispute="{$row.stripe_dispute_id|default:'-'|escape}"
                              data-amount="{$row.amount/100|string_format:'%.2f'|escape}"
                              data-currency="{$row.currency|upper|default:'USD'|escape}"
                              data-reason="{$row.reason|default:'-'|escape}"
                              data-status="{$st|escape}"
                              data-evidence="{if $row.evidence_due_by > 0}{$row.evidence_due_by}{else}0{/if}"
                              data-charge="{$row.charge_id|default:'-'|escape}">
                            <td class="eb-table-mono">{$row.stripe_dispute_id|default:'-'}</td>
                            <td>{$row.amount/100|string_format:'%.2f'}</td>
                            <td>{$row.currency|upper|default:'USD'}</td>
                            <td>{$row.reason|default:'-'}</td>
                            <td>
                              <span class="eb-badge eb-badge--dot {if $st=='won'}eb-badge--success{elseif $st=='needs_response' || $st=='warning_needs_response'}eb-badge--warning{elseif $st=='lost'}eb-badge--danger{else}eb-badge--default{/if}">{$st}</span>
                            </td>
                            <td>{if $row.evidence_due_by > 0}{$row.evidence_due_by|date_format:'%Y-%m-%d %H:%M'}{else}&mdash;{/if}</td>
                            <td class="eb-table-mono">{$row.charge_id|default:'-'}</td>
                            <td>
                              {assign var=acct value=$msp.stripe_connect_id|default:''}
                              {if $acct}
                                <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/disputes/{$row.stripe_dispute_id}" target="_blank" rel="noopener" class="font-medium text-[var(--eb-primary)] hover:underline">Open</a>
                              {else}-{/if}
                            </td>
                          </tr>
                        {/foreach}
                        <tr x-ref="noResults" style="display: none;">
                          <td colspan="8" class="px-4 py-6 text-center text-sm text-[var(--eb-text-muted)]">No disputes matched the current filters.</td>
                        </tr>
                      {else}
                        <tr>
                          <td colspan="8" class="px-4 py-6 text-center text-sm text-[var(--eb-text-muted)]">No disputes found.</td>
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
            </section>
          </div>
      <script>
        (function(){
          var btn = document.getElementById('eb-refresh-disputes');
          if (!btn) return;
          btn.addEventListener('click', async function(){
            btn.disabled = true;
            try {
              const res = await fetch('{$modulelink}&a=ph-disputes-refresh', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ token: '{$token|escape:'javascript'}' }).toString() });
              const data = await res.json();
              if (!data || data.status !== 'success') { alert((data && data.message) || 'Refresh failed'); btn.disabled=false; return; }
              location.reload();
            } catch (e) { alert('Error: ' + e.message); btn.disabled=false; }
          });
        })();
      </script>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='money-disputes'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}
