{* Partner Hub - Signup approvals queue *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
    <div class="p-6">
    <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] pb-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Pending Signup Approvals</h1>
        <p class="eb-page-description mt-1">Review and approve or reject customer signups awaiting MSP action.</p>
      </div>
      <a href="{$modulelink}&a=ph-tenants-manage" class="eb-btn eb-btn-outline eb-btn-sm shrink-0">Back to Tenants</a>
    </div>

    {if isset($notice) && $notice == 'approved'}
      <div class="eb-alert eb-alert--success mt-4 text-sm">
        Signup approved and order accepted.
      </div>
    {/if}
    {if isset($notice) && $notice == 'rejected'}
      <div class="eb-alert eb-alert--info mt-4 text-sm">
        Signup rejected and order cancellation path attempted.
      </div>
    {/if}
    {if isset($error) && $error ne ''}
      <div class="eb-alert eb-alert--danger mt-4 text-sm">
        Action could not be completed: {$error|escape}
      </div>
    {/if}

    <div class="eb-card-raised mt-6 !p-4"
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
      <div class="eb-table-toolbar mb-4">
        <div class="relative" @click.away="entriesOpen = false">
          <button type="button" @click="entriesOpen = !entriesOpen" class="eb-btn eb-btn-outline eb-btn-sm inline-flex items-center gap-2">
            <span x-text="'Show ' + entriesPerPage"></span>
            <svg class="h-4 w-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
          </button>
          <div x-show="entriesOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden !min-w-0" style="display: none;">
            <template x-for="size in [10,25,50,100]" :key="'approvals-entries-' + size">
              <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="entriesPerPage === size ? 'is-active' : ''" @click="setEntries(size); entriesOpen = false;"><span x-text="size"></span></button>
            </template>
          </div>
        </div>
        <div class="relative" @click.away="statusOpen = false">
          <button type="button" @click="statusOpen = !statusOpen" class="eb-btn eb-btn-outline eb-btn-sm inline-flex items-center gap-2">
            <span x-text="'Status: ' + statusLabel()"></span>
            <svg class="h-4 w-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
          </button>
          <div x-show="statusOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-56 overflow-hidden !min-w-0" style="display: none;">
            <template x-for="option in [
              { value: 'pending_approval', label: 'Pending Approval' },
              { value: 'approving', label: 'Approving' },
              { value: 'rejecting', label: 'Rejecting' },
              { value: 'approved', label: 'Approved' },
              { value: 'rejected', label: 'Rejected' },
              { value: 'all', label: 'All' }
            ]" :key="'approvals-status-' + option.value">
              <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" :class="statusFilter === option.value ? 'is-active' : ''" @click="setStatus(option.value); statusOpen = false;"><span x-text="option.label"></span></button>
            </template>
          </div>
        </div>
        <div class="flex-1"></div>
        <input type="text" x-model="search" placeholder="Search tenant, email, client, or order" class="eb-toolbar-search w-full xl:w-80">
      </div>

      <div class="eb-table-shell">
        <table class="eb-table min-w-full text-sm">
          <thead>
            <tr>
              <th><button type="button" class="eb-table-sort-button" @click="setSort('tenant')">Tenant <span x-text="sortIndicator('tenant')"></span></button></th>
              <th><button type="button" class="eb-table-sort-button" @click="setSort('email')">Email <span x-text="sortIndicator('email')"></span></button></th>
              <th><button type="button" class="eb-table-sort-button" @click="setSort('client')">WHMCS Client <span x-text="sortIndicator('client')"></span></button></th>
              <th><button type="button" class="eb-table-sort-button" @click="setSort('order')">Order <span x-text="sortIndicator('order')"></span></button></th>
              <th><button type="button" class="eb-table-sort-button" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button></th>
              <th><button type="button" class="eb-table-sort-button" @click="setSort('submitted')">Submitted <span x-text="sortIndicator('submitted')"></span></button></th>
              <th class="!text-right">Actions</th>
            </tr>
          </thead>
          <tbody x-ref="tbody">
            {if $rows|@count > 0}
              {foreach from=$rows item=row}
                <tr data-row="approval"
                    data-tenant="{if $row.subdomain}{$row.subdomain|escape}{else}{$row.fqdn|default:'-'|escape}{/if}"
                    data-email="{$row.email|escape}"
                    data-client="{$row.whmcs_client_id|default:'-'|escape}"
                    data-order="{$row.whmcs_order_id|default:'-'|escape}"
                    data-status="{$row.status|default:'pending_approval'|escape}"
                    data-submitted="{$row.created_at|escape}">
                  <td class="eb-table-primary">{if $row.subdomain}{$row.subdomain|escape}{else}{$row.fqdn|default:'-'|escape}{/if}</td>
                  <td>{$row.email|escape}</td>
                  <td>{$row.whmcs_client_id|default:'-'|escape}</td>
                  <td>{$row.whmcs_order_id|default:'-'|escape}</td>
                  <td>
                    {if $row.status == 'pending_approval'}
                      <span class="eb-badge eb-badge--info">Pending Approval</span>
                    {elseif $row.status == 'approving'}
                      <span class="eb-badge eb-badge--warning">Approving</span>
                    {elseif $row.status == 'rejecting'}
                      <span class="eb-badge eb-badge--warning">Rejecting</span>
                    {elseif $row.status == 'approved'}
                      <span class="eb-badge eb-badge--success">Approved</span>
                    {elseif $row.status == 'rejected'}
                      <span class="eb-badge eb-badge--danger">Rejected</span>
                    {else}
                      <span class="eb-badge eb-badge--default">{$row.status|escape}</span>
                    {/if}
                  </td>
                  <td>{$row.created_at|escape}</td>
                  <td class="!text-right">
                    {if $row.status == 'pending_approval'}
                      <div class="flex items-center justify-end gap-2">
                        <form method="post" action="{$modulelink}&a=ph-signup-approve" class="inline-block">
                          <input type="hidden" name="event_id" value="{$row.id|escape}" />
                          <input type="hidden" name="token" value="{$token|escape}" />
                          <button type="submit" class="eb-btn eb-btn-success eb-btn-xs">Approve</button>
                        </form>
                        <form method="post" action="{$modulelink}&a=ph-signup-reject" class="inline-block">
                          <input type="hidden" name="event_id" value="{$row.id|escape}" />
                          <input type="hidden" name="approval_notes" value="Rejected from Partner Hub queue" />
                          <input type="hidden" name="token" value="{$token|escape}" />
                          <button type="submit" class="eb-btn eb-btn-danger-solid eb-btn-xs">Reject</button>
                        </form>
                      </div>
                    {else}
                      <span class="text-[var(--eb-text-muted)]">-</span>
                    {/if}
                  </td>
                </tr>
              {/foreach}
              <tr x-ref="noResults" style="display: none;">
                <td colspan="7" class="py-8 text-center text-[var(--eb-text-muted)]">No signups matched the current filters.</td>
              </tr>
            {else}
              <tr>
                <td colspan="7" class="py-8 text-center text-[var(--eb-text-muted)]">No signups are waiting for approval.</td>
              </tr>
            {/if}
          </tbody>
        </table>
      </div>
      <div class="eb-table-pagination">
        <div x-text="pageSummary()"></div>
        <div class="flex items-center gap-2">
          <button type="button" @click="prevPage()" :disabled="currentPage <= 1" class="eb-table-pagination-button">Prev</button>
          <span class="text-[var(--eb-text-secondary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
          <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages()" class="eb-table-pagination-button">Next</button>
        </div>
      </div>
    </div>
    </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='signup-approvals'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}
