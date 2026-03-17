{* Partner Hub - Signup approvals queue *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='signup-approvals'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Pending Signup Approvals</h1>
      <a href="{$modulelink}&a=ph-tenants-manage" class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10 text-sm">Back to Tenants</a>
    </div>

    {if isset($notice) && $notice == 'approved'}
      <div class="mt-4 rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-white">
        Signup approved and order accepted.
      </div>
    {/if}
    {if isset($notice) && $notice == 'rejected'}
      <div class="mt-4 rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-white">
        Signup rejected and order cancellation path attempted.
      </div>
    {/if}
    {if isset($error) && $error ne ''}
      <div class="mt-4 rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
        Action could not be completed: {$error|escape}
      </div>
    {/if}

    <div class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4"
         x-data="{
           entriesOpen: false,
           statusOpen: false,
           search: '',
           entriesPerPage: 25,
           currentPage: 1,
           sortKey: 'tenant',
           sortDirection: 'asc',
           statusFilter: 'pending_approval',
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
               pending_approval: 'Pending Approval',
               approving: 'Approving',
               rejecting: 'Rejecting',
               approved: 'Approved',
               rejected: 'Rejected',
               all: 'All'
             }[this.statusFilter] || 'Pending Approval';
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
             if (this.filteredCount === 0) return 'Showing 0-0 of 0 signups';
             const start = (this.currentPage - 1) * this.entriesPerPage + 1;
             const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
             return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' signups';
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
            <template x-for="size in [10,25,50,100]" :key="'approvals-entries-' + size">
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
              { value: 'pending_approval', label: 'Pending Approval' },
              { value: 'approving', label: 'Approving' },
              { value: 'rejecting', label: 'Rejecting' },
              { value: 'approved', label: 'Approved' },
              { value: 'rejected', label: 'Rejected' },
              { value: 'all', label: 'All' }
            ]" :key="'approvals-status-' + option.value">
              <button type="button" class="w-full px-4 py-2 text-left text-sm transition" :class="statusFilter === option.value ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
            </template>
          </div>
        </div>
        <div class="flex-1"></div>
        <input type="text" x-model="search" placeholder="Search tenant, email, client, or order" class="w-full xl:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none hover:border-slate-600 hover:bg-slate-900/80">
      </div>

      <div class="overflow-x-auto rounded-lg border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/80 text-slate-300">
            <tr>
              <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('tenant')">Tenant <span x-text="sortIndicator('tenant')"></span></button></th>
              <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('email')">Email <span x-text="sortIndicator('email')"></span></button></th>
              <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('client')">WHMCS Client <span x-text="sortIndicator('client')"></span></button></th>
              <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('order')">Order <span x-text="sortIndicator('order')"></span></button></th>
              <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button></th>
              <th class="px-4 py-3 text-left font-medium"><button type="button" class="inline-flex items-center gap-1 hover:text-white" @click="setSort('submitted')">Submitted <span x-text="sortIndicator('submitted')"></span></button></th>
              <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody x-ref="tbody" class="divide-y divide-slate-800">
            {if $rows|@count > 0}
              {foreach from=$rows item=row}
                <tr class="hover:bg-slate-800/50"
                    data-row="approval"
                    data-tenant="{if $row.subdomain}{$row.subdomain|escape}{else}{$row.fqdn|default:'-'|escape}{/if}"
                    data-email="{$row.email|escape}"
                    data-client="{$row.whmcs_client_id|default:'-'|escape}"
                    data-order="{$row.whmcs_order_id|default:'-'|escape}"
                    data-status="{$row.status|default:'pending_approval'|escape}"
                    data-submitted="{$row.created_at|escape}">
                  <td class="px-4 py-3">{if $row.subdomain}{$row.subdomain|escape}{else}{$row.fqdn|default:'-'|escape}{/if}</td>
                  <td class="px-4 py-3">{$row.email|escape}</td>
                  <td class="px-4 py-3">{$row.whmcs_client_id|default:'-'|escape}</td>
                  <td class="px-4 py-3">{$row.whmcs_order_id|default:'-'|escape}</td>
                  <td class="px-4 py-3">
                    {if $row.status == 'pending_approval'}
                      <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-sky-500/15 text-sky-200 ring-1 ring-sky-300/20">Pending Approval</span>
                    {elseif $row.status == 'approving'}
                      <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-amber-500/20 text-amber-200 ring-1 ring-amber-300/20">Approving</span>
                    {elseif $row.status == 'rejecting'}
                      <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-amber-500/20 text-amber-200 ring-1 ring-amber-300/20">Rejecting</span>
                    {elseif $row.status == 'approved'}
                      <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-emerald-500/20 text-emerald-200 ring-1 ring-emerald-300/20">Approved</span>
                    {elseif $row.status == 'rejected'}
                      <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-rose-500/20 text-rose-200 ring-1 ring-rose-300/20">Rejected</span>
                    {else}
                      <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-white/10 text-white/70 ring-1 ring-white/15">{$row.status|escape}</span>
                    {/if}
                  </td>
                  <td class="px-4 py-3">{$row.created_at|escape}</td>
                  <td class="px-4 py-3 text-right">
                    {if $row.status == 'pending_approval'}
                      <div class="flex items-center justify-end gap-2">
                        <form method="post" action="{$modulelink}&a=ph-signup-approve" class="inline-block">
                          <input type="hidden" name="event_id" value="{$row.id|escape}" />
                          <input type="hidden" name="token" value="{$token|escape}" />
                          <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-500">Approve</button>
                        </form>
                        <form method="post" action="{$modulelink}&a=ph-signup-reject" class="inline-block">
                          <input type="hidden" name="event_id" value="{$row.id|escape}" />
                          <input type="hidden" name="approval_notes" value="Rejected from Partner Hub queue" />
                          <input type="hidden" name="token" value="{$token|escape}" />
                          <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-white bg-rose-600 hover:bg-rose-500">Reject</button>
                        </form>
                      </div>
                    {else}
                      <span class="text-slate-500">-</span>
                    {/if}
                  </td>
                </tr>
              {/foreach}
              <tr x-ref="noResults" style="display: none;">
                <td colspan="7" class="px-4 py-8 text-center text-white/60">No signups matched the current filters.</td>
              </tr>
            {else}
              <tr>
                <td colspan="7" class="px-4 py-8 text-center text-white/60">No signups are waiting for approval.</td>
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
        </main>
      </div>
    </div>
  </div>
</div>
