{* Partner Hub — Tenants list *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
        <div x-data="{ openCreateModal: false, savingModal: false }">
        <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Customer Tenants</h1>
            <p class="eb-page-description mt-1">Create, review, and manage customer tenant records from Partner Hub.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            {if !isset($connect.chargesEnabled) || !$connect.chargesEnabled}
              <a href="{$modulelink}&a=ph-stripe-onboard" class="eb-btn eb-btn-warning eb-btn-sm">Connect Stripe</a>
            {/if}
            <button type="button" @click="openCreateModal = true" class="eb-btn eb-btn-primary eb-btn-sm">Create New Tenant</button>
            <a href="{$modulelink}&a=ph-tenants-manage" class="eb-btn eb-btn-outline eb-btn-sm">Back to Tenants</a>
          </div>
        </div>
        <div class="p-6">

    {if !isset($connect.chargesEnabled) || !$connect.chargesEnabled}
      <div class="eb-alert eb-alert--warning mt-3 text-sm">
        To accept payments, finish Stripe onboarding for this MSP. Click Connect Stripe to get started.
      </div>
    {/if}
    {if isset($connect_due) && $connect_due|@count > 0}
      <div class="eb-alert eb-alert--warning mt-3 text-sm">
        Stripe requires additional information. <a href="{$modulelink}&a=ph-stripe-connect" class="underline">View details</a> or <a href="{$modulelink}&a=ph-stripe-onboard" class="underline">Resume onboarding</a>.
      </div>
    {/if}
    {if isset($onboardError) && $onboardError}
      <div class="eb-alert eb-alert--danger mt-3 text-sm">
        We couldn't start Stripe onboarding. Please try again.
      </div>
    {/if}
    {if isset($onboardSuccess) && $onboardSuccess}
      <div class="eb-alert eb-alert--success my-3 text-sm">
        Stripe onboarding complete. What’s next: connect status may take a moment to update; you can review <a class="underline" href="{$modulelink}&a=ph-stripe-connect">Connect &amp; Status</a> or proceed to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
      </div>
    {/if}
    {if isset($onboardRefresh) && $onboardRefresh}
      <div class="eb-alert eb-alert--info mt-3 text-sm">
        You can resume Stripe onboarding at any time. If setup is complete, continue to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
      </div>
    {/if}

    {if $notice neq ''}
      <div class="eb-alert eb-alert--success mt-4 text-sm">
        Saved successfully.
      </div>
    {/if}
    {if $error neq ''}
      <div class="eb-alert eb-alert--danger mt-4 text-sm">
        Unable to process the request ({$error|escape}).
      </div>
    {/if}
    {if isset($legacy_notice) && $legacy_notice neq ''}
      <div class="eb-alert eb-alert--warning mt-4 text-sm">
        You were redirected here from a legacy e3 tenants URL ({$legacy_notice|escape}). Customer tenant management now lives in Partner Hub.
      </div>
    {/if}

    <div class="eb-subpanel !p-0 overflow-hidden">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
        <h2 class="eb-app-card-title">Existing Customer Tenants</h2>
      </div>
      <div class="p-4"
           x-data="{
             columnsOpen: false,
             entriesOpen: false,
             search: '',
             entriesPerPage: 25,
             currentPage: 1,
             sortKey: 'name',
             sortDirection: 'asc',
             filteredCount: 0,
             rows: [],
             cols: { public_id: true, name: true, slug: true, contact_email: true, status: true, updated: true, actions: true },
             init() {
               this.rows = Array.from(this.$refs.tbody.querySelectorAll('tr[data-tenant-row]'));
               this.$watch('search', () => { this.currentPage = 1; this.refreshRows(); });
               this.refreshRows();
             },
             setEntries(size) {
               this.entriesPerPage = Number(size) || 25;
               this.currentPage = 1;
               this.refreshRows();
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
               if (key === 'public_id') return String(row.getAttribute('data-tenant-public-id') || '').toLowerCase();
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
                 if (!query) return true;
                 return (row.textContent || '').toLowerCase().includes(query);
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
               if (this.filteredCount === 0) return 'Showing 0 of 0 tenants';
               const start = (this.currentPage - 1) * this.entriesPerPage + 1;
               const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
               return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' tenants';
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
          <div class="relative" @click.away="entriesOpen=false">
            <button type="button"
                    @click="entriesOpen=!entriesOpen"
                    class="eb-btn eb-btn-secondary eb-btn-sm">
              <span x-text="'Show ' + entriesPerPage"></span>
              <svg class="h-4 w-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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
                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                 style="display: none;">
              <template x-for="size in [10,25,50,100]" :key="'tenants-entries-' + size">
                <button type="button"
                        class="eb-menu-option"
                        :class="entriesPerPage === size ? 'is-active' : ''"
                        @click="setEntries(size); entriesOpen=false;">
                  <span x-text="size"></span>
                </button>
              </template>
            </div>
          </div>
          <div class="relative" @click.away="columnsOpen=false">
            <button type="button"
                    @click="columnsOpen=!columnsOpen"
                    class="eb-btn eb-btn-secondary eb-btn-sm">
              Columns
              <svg class="h-4 w-4 transition-transform" :class="columnsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
            <div x-show="columnsOpen"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-64 overflow-hidden"
                 style="display: none;">
              <div class="eb-menu-label">Visible Columns</div>
              <div class="eb-menu-checklist p-1">
                <label class="eb-menu-checklist-item"><span>Tenant ID</span><input type="checkbox" class="eb-checkbox" x-model="cols.public_id"></label>
                <label class="eb-menu-checklist-item"><span>Name</span><input type="checkbox" class="eb-checkbox" x-model="cols.name"></label>
                <label class="eb-menu-checklist-item"><span>Slug</span><input type="checkbox" class="eb-checkbox" x-model="cols.slug"></label>
                <label class="eb-menu-checklist-item"><span>Contact Email</span><input type="checkbox" class="eb-checkbox" x-model="cols.contact_email"></label>
                <label class="eb-menu-checklist-item"><span>Status</span><input type="checkbox" class="eb-checkbox" x-model="cols.status"></label>
                <label class="eb-menu-checklist-item"><span>Updated</span><input type="checkbox" class="eb-checkbox" x-model="cols.updated"></label>
                <label class="eb-menu-checklist-item"><span>Actions</span><input type="checkbox" class="eb-checkbox" x-model="cols.actions"></label>
              </div>
            </div>
          </div>
          <div class="flex-1"></div>
          <input type="text"
                 x-model.debounce.200ms="search"
                 placeholder="Search tenants..."
                 class="eb-toolbar-search w-full xl:w-80">
        </div>
        <div class="eb-table-shell">
          <table class="eb-table min-w-full text-sm">
            <thead>
              <tr>
                <th x-show="cols.public_id">
                  <button type="button" class="eb-table-sort-button" @click="setSort('public_id')">Tenant ID <span x-text="sortIndicator('public_id')"></span></button>
                </th>
                <th x-show="cols.name">
                  <button type="button" class="eb-table-sort-button" @click="setSort('name')">Name <span x-text="sortIndicator('name')"></span></button>
                </th>
                <th x-show="cols.slug">
                  <button type="button" class="eb-table-sort-button" @click="setSort('slug')">Slug <span x-text="sortIndicator('slug')"></span></button>
                </th>
                <th x-show="cols.contact_email">
                  <button type="button" class="eb-table-sort-button" @click="setSort('contact_email')">Contact Email <span x-text="sortIndicator('contact_email')"></span></button>
                </th>
                <th x-show="cols.status">
                  <button type="button" class="eb-table-sort-button" @click="setSort('status')">Status <span x-text="sortIndicator('status')"></span></button>
                </th>
                <th x-show="cols.updated">
                  <button type="button" class="eb-table-sort-button" @click="setSort('updated')">Updated <span x-text="sortIndicator('updated')"></span></button>
                </th>
                <th x-show="cols.actions" class="!text-right">Actions</th>
              </tr>
            </thead>
            <tbody x-ref="tbody">
            {foreach from=$tenants item=tenant}
              <tr data-tenant-row="1"
                  data-tenant-public-id="{$tenant.public_id|escape}"
                  data-name="{$tenant.name|escape}"
                  data-slug="{$tenant.slug|escape}"
                  data-contact-email="{$tenant.contact_email|default:'-'|escape}"
                  data-status="{$tenant.status|escape}"
                  data-updated="{$tenant.updated_at|default:''|escape}"
                  tabindex="0"
                  role="link"
                  class="cursor-pointer transition-colors hover:bg-white/5 focus:outline-none focus:ring-1 focus:ring-[var(--eb-border-orange)]"
                  aria-label="Open tenant {$tenant.name|escape}"
                  @click="window.location.href='{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}'"
                  @keydown.enter.prevent="window.location.href='{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}'"
                  @keydown.space.prevent="window.location.href='{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}'">
                <td x-show="cols.public_id" class="whitespace-nowrap eb-table-mono">{$tenant.public_id|escape}</td>
                <td x-show="cols.name" class="whitespace-nowrap eb-table-primary">{$tenant.name|escape}</td>
                <td x-show="cols.slug" class="whitespace-nowrap">{$tenant.slug|escape}</td>
                <td x-show="cols.contact_email" class="whitespace-nowrap">{$tenant.contact_email|default:'-'|escape}</td>
                <td x-show="cols.status" class="whitespace-nowrap">{$tenant.status|escape}</td>
                <td x-show="cols.updated" class="whitespace-nowrap">{$tenant.updated_at|default:'-'|escape}</td>
                <td x-show="cols.actions" class="!text-right">
                  <a class="eb-btn eb-btn-outline eb-btn-xs" href="{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}" @click.stop>Manage</a>
                </td>
              </tr>
            {foreachelse}
            {/foreach}
              <tr x-ref="noResults" {if $tenants|@count > 0}style="display:none;"{/if}>
                <td colspan="7" class="py-6 text-center text-[var(--eb-text-muted)]">No customer tenants yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="eb-table-pagination">
          <div x-text="pageSummary()"></div>
          <div class="flex items-center gap-2">
            <button type="button"
                    @click="prevPage()"
                    :disabled="currentPage <= 1"
                    class="eb-table-pagination-button">
              Prev
            </button>
            <span class="text-[var(--eb-text-secondary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
            <button type="button"
                    @click="nextPage()"
                    :disabled="currentPage >= totalPages()"
                    class="eb-table-pagination-button">
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
        </div>

        <!-- Create Tenant Modal -->
        <div x-show="openCreateModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
             style="display: none;"
             @keydown.escape.window="openCreateModal = false">
          <div class="absolute inset-0 eb-modal-backdrop min-h-full" @click="openCreateModal = false"></div>
          <div class="relative eb-modal !max-w-2xl max-h-[85vh] my-auto flex flex-col"
               @click.stop>
            <div class="eb-modal-header shrink-0">
              <h2 class="eb-modal-title">Create Customer Tenant</h2>
              <button type="button" @click="openCreateModal = false" class="eb-modal-close" aria-label="Close">×</button>
            </div>
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
            <div x-data="{ showAdmin: false, autoPassword: '1' }">
            <form id="create-tenant-form" method="post" action="{$modulelink}&a=ph-tenants-manage" class="space-y-6 p-6">
              <input type="hidden" name="eb_create_tenant" value="1" />
              {if isset($token) && $token neq ''}
                <input type="hidden" name="token" value="{$token}" />
              {/if}

              <div class="eb-subpanel space-y-4 !p-5">
                <h3 class="eb-type-eyebrow text-[var(--eb-text-muted)]">Organization</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div class="md:col-span-2">
                    <label class="eb-field-label">Company Name <span class="text-[var(--eb-danger-text)]">*</span></label>
                    <input type="text" name="name" required placeholder="Acme Corporation" class="eb-input w-full">
                    <p id="create-tenant-error-name" class="eb-field-error mt-1 hidden"></p>
                  </div>
                  <div>
                    <label class="eb-field-label">Slug</label>
                    <input type="text" name="slug" placeholder="auto-from-name" class="eb-input w-full font-mono">
                    <p class="eb-field-help mt-1">URL-friendly identifier. Optional.</p>
                  </div>
                  <div>
                    <label class="eb-field-label">Status</label>
                    <select name="status" class="eb-select w-full">
                      {foreach from=$statuses item=s}
                        <option value="{$s|escape}">{$s|escape}</option>
                      {/foreach}
                    </select>
                  </div>
                </div>
              </div>

              <div class="eb-subpanel space-y-4 !p-5">
                <h3 class="eb-type-eyebrow text-[var(--eb-text-muted)]">Contact Information</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label class="eb-field-label">Contact Email <span class="text-[var(--eb-danger-text)]">*</span></label>
                    <input type="email" name="contact_email" required placeholder="billing@acme.com" class="eb-input w-full">
                    <p id="create-tenant-error-contact_email" class="eb-field-error mt-1 hidden"></p>
                  </div>
                  <div>
                    <label class="eb-field-label">Contact Name <span class="text-[var(--eb-danger-text)]">*</span></label>
                    <input type="text" name="contact_name" required placeholder="Jane Smith" class="eb-input w-full">
                    <p id="create-tenant-error-contact_name" class="eb-field-error mt-1 hidden"></p>
                  </div>
                  <div class="md:col-span-2">
                    <label class="eb-field-label">Phone</label>
                    <input type="tel" name="contact_phone" placeholder="+1 (555) 123-4567" class="eb-input w-full">
                  </div>
                </div>
              </div>

              <div class="eb-subpanel space-y-4 !p-5">
                <h3 class="eb-type-eyebrow text-[var(--eb-text-muted)]">Billing Address <span class="font-normal text-[var(--eb-text-muted)]">(optional)</span></h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div class="md:col-span-2">
                    <label class="eb-field-label">Address Line 1</label>
                    <input type="text" name="address_line1" placeholder="123 Main Street" class="eb-input w-full">
                  </div>
                  <div class="md:col-span-2">
                    <label class="eb-field-label">Address Line 2</label>
                    <input type="text" name="address_line2" placeholder="Suite 100" class="eb-input w-full">
                  </div>
                  <div>
                    <label class="eb-field-label">City</label>
                    <input type="text" name="city" placeholder="Toronto" class="eb-input w-full">
                  </div>
                  <div>
                    <label class="eb-field-label">State / Province</label>
                    <input type="text" name="state" placeholder="Ontario" class="eb-input w-full">
                  </div>
                  <div>
                    <label class="eb-field-label">Postal Code</label>
                    <input type="text" name="postal_code" placeholder="M5V 1A1" class="eb-input w-full">
                  </div>
                  <div>
                    <label class="eb-field-label">Country Code</label>
                    <input type="text" name="country" maxlength="2" placeholder="CA" class="eb-input w-full uppercase" autocomplete="off" inputmode="text" autocapitalize="characters">
                    <p id="create-tenant-error-country" class="eb-field-error mt-1 hidden"></p>
                    <p id="create-tenant-hint-country" class="eb-field-help mt-1">2-letter ISO code (e.g. CA, US, GB)</p>
                  </div>
                </div>
              </div>

              <div class="eb-subpanel space-y-4 !p-5">
                <h3 class="eb-type-eyebrow text-[var(--eb-text-muted)]">Portal Admin Account</h3>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-[var(--eb-text-secondary)]">
                  <input type="checkbox" name="create_admin" value="1" x-model="showAdmin" class="eb-checkbox">
                  Create portal admin user for this tenant
                </label>
                <div x-show="showAdmin" x-cloak class="mt-4 space-y-4 border-l border-[var(--eb-border-orange)] pl-4" style="display: none;">
                  <p class="eb-field-help">Creates a tenant member that can log in to the tenant portal.</p>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                      <label class="eb-field-label">Admin Email</label>
                      <input type="email" name="admin_email" placeholder="admin@acme.com" class="eb-input w-full">
                    </div>
                    <div>
                      <label class="eb-field-label">Admin Name</label>
                      <input type="text" name="admin_name" placeholder="Admin User" class="eb-input w-full">
                    </div>
                  </div>
                  <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-[var(--eb-text-secondary)]">
                      <input type="radio" name="auto_password" value="1" x-model="autoPassword" class="eb-radio-input">
                      Auto-generate password and email to user
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-[var(--eb-text-secondary)]">
                      <input type="radio" name="auto_password" value="0" x-model="autoPassword" class="eb-radio-input">
                      Set password manually
                    </label>
                  </div>
                  <div x-show="autoPassword === '0'" x-cloak class="mt-2" style="display: none;">
                    <label class="eb-field-label">Password</label>
                    <input type="password" name="admin_password" minlength="8" placeholder="Minimum 8 characters" class="eb-input w-full">
                    <p id="create-tenant-error-admin_password" class="eb-field-error mt-1 hidden"></p>
                  </div>
                </div>
              </div>

              <div class="flex justify-end gap-3 border-t border-[var(--eb-border-subtle)] px-6 pb-6 pt-4">
                <button type="button" @click="openCreateModal = false" class="eb-btn eb-btn-secondary eb-btn-sm">
                  Cancel
                </button>
                <button type="submit" :disabled="savingModal" class="eb-btn eb-btn-primary eb-btn-sm disabled:cursor-not-allowed disabled:opacity-60">
                  <span x-show="!savingModal">Create Tenant</span>
                  <span x-show="savingModal" style="display: none;">Saving…</span>
                </button>
              </div>
            </form>
            </div>
            </div>
          </div>
        </div>
        </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='tenants'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}



<script>
(function () {
  function normalizeCountry(value) {
    var v = String(value || '');
    v = v.trim().toUpperCase().replace(/\s+/g, '');
    return v;
  }

  function showError(form, field, message) {
    var input = form.querySelector('[name="' + field + '"]');
    var el = document.getElementById('create-tenant-error-' + field);
    if (input) input.classList.add('border-rose-500');
    if (el) {
      el.textContent = message;
      el.classList.remove('hidden');
    }
    if (field === 'country') {
      var hint = document.getElementById('create-tenant-hint-country');
      if (hint) hint.classList.add('hidden');
    }
  }

  function clearError(form, field) {
    var input = form.querySelector('[name="' + field + '"]');
    var el = document.getElementById('create-tenant-error-' + field);
    if (input) input.classList.remove('border-rose-500');
    if (el) {
      el.textContent = '';
      el.classList.add('hidden');
    }
    if (field === 'country') {
      var hint = document.getElementById('create-tenant-hint-country');
      if (hint) hint.classList.remove('hidden');
    }
  }

  function validateCreateTenantForm(form) {
    ['name', 'contact_email', 'contact_name', 'country', 'admin_password'].forEach(function (f) {
      clearError(form, f);
    });

    var hasError = false;
    var name = (form.querySelector('[name="name"]') || {}).value || '';
    var email = (form.querySelector('[name="contact_email"]') || {}).value || '';
    var contactName = (form.querySelector('[name="contact_name"]') || {}).value || '';
    var countryInput = form.querySelector('[name="country"]');
    var country = normalizeCountry(countryInput ? countryInput.value : '').slice(0, 2);
    if (countryInput) countryInput.value = country;

    if (String(name).trim() === '') {
      showError(form, 'name', 'Required');
      hasError = true;
    }

    var emailVal = String(email).trim();
    if (emailVal === '') {
      showError(form, 'contact_email', 'Required');
      hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
      showError(form, 'contact_email', 'Invalid email format');
      hasError = true;
    }

    if (String(contactName).trim() === '') {
      showError(form, 'contact_name', 'Required');
      hasError = true;
    }

    // Country is constrained at input level (letters only, max 2). Backend remains source of truth.

    var createAdmin = form.querySelector('[name="create_admin"]');
    var autoPassword = form.querySelector('[name="auto_password"]:checked');
    var adminPassword = (form.querySelector('[name="admin_password"]') || {}).value || '';
    if (createAdmin && createAdmin.checked && autoPassword && autoPassword.value === '0' && String(adminPassword).length < 8) {
      showError(form, 'admin_password', 'Minimum 8 characters');
      hasError = true;
    }

    return !hasError;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('create-tenant-form');
    if (!form) return;
    var countryInput = form.querySelector('[name="country"]');
    if (countryInput) {
      countryInput.addEventListener('input', function () {
        countryInput.value = normalizeCountry(countryInput.value).slice(0, 2);
        clearError(form, 'country');
      });
      countryInput.addEventListener('paste', function () {
        window.setTimeout(function () {
          countryInput.value = normalizeCountry(countryInput.value).slice(0, 2);
          clearError(form, 'country');
        }, 0);
      });
    }
    form.addEventListener('submit', function (event) {
      if (!validateCreateTenantForm(form)) {
        event.preventDefault();
      }
    });
  });
})();
</script>
