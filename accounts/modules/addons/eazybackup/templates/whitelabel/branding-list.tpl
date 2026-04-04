{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
    <div class="space-y-6">
        <section class="eb-subpanel !p-0 overflow-hidden">
            <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
                <div>
                    <h2 class="eb-card-title !text-base">Tenant Branding Directory</h2>
                    <p class="eb-card-subtitle">Review branded tenants, domains, and quick management actions.</p>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                <div id="tenantsToolbar" class="eb-table-toolbar">
                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-btn eb-btn-secondary eb-btn-sm">
                            <span id="tenantsEntriesLabel">Show 25</span>
                            <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 0 1 1.414 0L10 10.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                             style="display: none;">
                            <button type="button" class="tenant-page-size eb-menu-option" data-size="10" @click="isOpen=false">10</button>
                            <button type="button" class="tenant-page-size eb-menu-option is-active" data-size="25" @click="isOpen=false">25</button>
                            <button type="button" class="tenant-page-size eb-menu-option" data-size="50" @click="isOpen=false">50</button>
                            <button type="button" class="tenant-page-size eb-menu-option" data-size="100" @click="isOpen=false">100</button>
                        </div>
                    </div>

                    <div class="flex-1"></div>

                    <input id="tenant-search"
                           type="text"
                           placeholder="Search tenants..."
                           aria-label="Search tenants"
                           class="eb-toolbar-search w-full xl:w-80" />
                </div>

                <div class="eb-table-shell">
                    <table id="tenant-branding-table" class="eb-table">
                        <thead>
                            <tr>
                                <th data-sort-index="0" aria-sort="none">
                                    <button type="button" class="eb-table-sort-button">
                                        FQDN
                                        <span class="eb-sort-indicator sort-indicator" data-col="0"></span>
                                    </button>
                                </th>
                                <th data-sort-index="1" aria-sort="none">
                                    <button type="button" class="eb-table-sort-button">
                                        Product
                                        <span class="eb-sort-indicator sort-indicator" data-col="1"></span>
                                    </button>
                                </th>
                                <th data-sort-index="2" aria-sort="none">
                                    <button type="button" class="eb-table-sort-button">
                                        Custom Domain
                                        <span class="eb-sort-indicator sort-indicator" data-col="2"></span>
                                    </button>
                                </th>
                                <th data-sort-index="3" aria-sort="none">
                                    <button type="button" class="eb-table-sort-button">
                                        Status
                                        <span class="eb-sort-indicator sort-indicator" data-col="3"></span>
                                    </button>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$tenants item=t}
                                <tr data-row="tenant">
                                    <td class="eb-table-primary whitespace-nowrap">{$t.fqdn}</td>
                                    <td class="whitespace-nowrap">{$t.product_name|default:'Unknown'}</td>
                                    <td class="whitespace-nowrap">{$t.custom_domain|default:'-'}</td>
                                    <td class="whitespace-nowrap">
                                        <span class="eb-badge {if strtolower($t.status) == 'active'}eb-badge--success eb-badge--dot{else}eb-badge--default{/if}">
                                            <span class="capitalize">{$t.status}</span>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <button type="button"
                                                class="eb-btn eb-btn-secondary eb-btn-xs btn-tenant-manage"
                                                data-tenant-tid="{$t.public_id}"
                                                data-fqdn="{$t.fqdn}"
                                                data-custom-domain="{$t.custom_domain|default:''}">
                                            Manage
                                        </button>
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr data-empty-row="1">
                                    <td colspan="5" class="py-8 text-center text-sm text-[var(--eb-text-muted)]">No tenants yet.</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>

                <div id="tenantsPagination" class="eb-table-pagination">
                    <div id="tenantsPageSummary"></div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="tenantsPrev" class="eb-table-pagination-button">Prev</button>
                        <span id="tenantsPageLabel" class="text-[var(--eb-text-primary)]"></span>
                        <button type="button" id="tenantsNext" class="eb-table-pagination-button">Next</button>
                    </div>
                </div>
            </div>

            <script>
            (function(){
              try {
                var input = document.getElementById('tenant-search');
                var table = document.getElementById('tenant-branding-table');
                if (!table) return;
                var tbody = table.querySelector('tbody');
                var headers = Array.prototype.slice.call(table.querySelectorAll('thead th[data-sort-index]'));
                var tenantRows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-row="tenant"]'));
                var pageSizeButtons = Array.prototype.slice.call(document.querySelectorAll('.tenant-page-size'));
                var entriesLabel = document.getElementById('tenantsEntriesLabel');
                var summaryEl = document.getElementById('tenantsPageSummary');
                var pageLabelEl = document.getElementById('tenantsPageLabel');
                var prevBtn = document.getElementById('tenantsPrev');
                var nextBtn = document.getElementById('tenantsNext');
                var state = { sortIndex: 0, sortDir: 1, pageSize: 25, currentPage: 1 };

                function cellText(tr, idx){
                  var td = tr.children[idx];
                  return (td ? (td.textContent || '').trim().toLowerCase() : '');
                }

                function updateSortIndicators(){
                  headers.forEach(function(h, idx){
                    var indicator = h.querySelector('.sort-indicator');
                    if (indicator) {
                      indicator.textContent = idx === state.sortIndex ? (state.sortDir === 1 ? '↑' : '↓') : '';
                    }
                    h.setAttribute('aria-sort', idx === state.sortIndex ? (state.sortDir === 1 ? 'ascending' : 'descending') : 'none');
                  });
                }

                function updatePagination(total, start, end, totalPages){
                  if (summaryEl) summaryEl.textContent = 'Showing ' + start + '-' + end + ' of ' + total + ' tenants';
                  if (pageLabelEl) pageLabelEl.textContent = 'Page ' + state.currentPage + ' / ' + totalPages;
                  if (prevBtn) prevBtn.disabled = state.currentPage <= 1;
                  if (nextBtn) nextBtn.disabled = state.currentPage >= totalPages;
                }

                function apply(){
                  var q = (input && input.value || '').trim().toLowerCase();
                  var filtered = tenantRows.filter(function(tr){
                    if (!q) return true;
                    return (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
                  });

                  filtered.sort(function(a, b){
                    var av = cellText(a, state.sortIndex);
                    var bv = cellText(b, state.sortIndex);
                    return av.localeCompare(bv, undefined, { numeric: true }) * state.sortDir;
                  });

                  var total = filtered.length;
                  var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
                  if (state.currentPage > totalPages) state.currentPage = totalPages;
                  var startIdx = (state.currentPage - 1) * state.pageSize;
                  var pageRows = filtered.slice(startIdx, startIdx + state.pageSize);

                  while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

                  if (total === 0) {
                    var emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = '<td colspan="5" class="py-8 text-center text-sm text-[var(--eb-text-muted)]">No tenants found.</td>';
                    tbody.appendChild(emptyRow);
                  } else {
                    pageRows.forEach(function(tr){ tbody.appendChild(tr); });
                  }

                  var start = total === 0 ? 0 : (startIdx + 1);
                  var end = total === 0 ? 0 : Math.min(startIdx + state.pageSize, total);
                  updateSortIndicators();
                  updatePagination(total, start, end, totalPages);
                }

                headers.forEach(function(h){
                  h.setAttribute('role', 'button');
                  h.setAttribute('tabindex', '0');
                  var idx = parseInt(h.getAttribute('data-sort-index'), 10);
                  function toggleSort(){
                    if (state.sortIndex === idx) {
                      state.sortDir *= -1;
                    } else {
                      state.sortIndex = idx;
                      state.sortDir = 1;
                    }
                    state.currentPage = 1;
                    apply();
                  }
                  h.addEventListener('click', toggleSort);
                  h.addEventListener('keydown', function(e){
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.preventDefault();
                      toggleSort();
                    }
                  });
                });

                if (input) {
                  input.addEventListener('input', function(){
                    state.currentPage = 1;
                    apply();
                  });
                }

                pageSizeButtons.forEach(function(btn){
                  btn.addEventListener('click', function(){
                    var size = parseInt(btn.getAttribute('data-size'), 10) || 25;
                    state.pageSize = size;
                    state.currentPage = 1;
                    if (entriesLabel) entriesLabel.textContent = 'Show ' + size;
                    pageSizeButtons.forEach(function(other){
                      other.classList.remove('is-active');
                    });
                    btn.classList.add('is-active');
                    apply();
                  });
                });

                if (prevBtn) {
                  prevBtn.addEventListener('click', function(){
                    if (state.currentPage > 1) {
                      state.currentPage -= 1;
                      apply();
                    }
                  });
                }

                if (nextBtn) {
                  nextBtn.addEventListener('click', function(){
                    var total = tenantRows.filter(function(tr){
                      var q = (input && input.value || '').trim().toLowerCase();
                      if (!q) return true;
                      return (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
                    }).length;
                    var totalPages = Math.max(1, Math.ceil(total / state.pageSize));
                    if (state.currentPage < totalPages) {
                      state.currentPage += 1;
                      apply();
                    }
                  });
                }

                apply();
              } catch (e) {}
            })();
            </script>
        </section>
    </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='branding-list'
  ebPhTitle='White-Label Tenants'
  ebPhDescription='Manage tenant branding, custom domains, and quick Partner Hub actions from one directory.'
  ebPhContent=$ebPhContent
}

<div id="tenant-slide-panel" class="fixed inset-y-0 right-0 z-50 !w-full max-w-xl eb-drawer transform translate-x-full transition-transform duration-200 ease-out">
    <div class="h-full flex flex-col">
        <div class="eb-drawer-header items-start gap-4">
            <div class="min-w-0">
                <h3 id="tenant-panel-title" class="eb-drawer-title">Manage Tenant</h3>
                <div class="mt-1 text-xs text-[var(--eb-text-muted)]">FQDN: <span id="tenant-panel-fqdn" class="eb-type-mono text-[var(--eb-text-primary)]"></span></div>
                <div class="mt-0.5 text-xs text-[var(--eb-text-muted)]">Custom Domain: <span id="tenant-panel-custom" class="eb-type-mono text-[var(--eb-text-primary)]"></span></div>
            </div>
            <button id="tenant-panel-close" class="eb-modal-close" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="eb-drawer-body space-y-4">
            <div class="grid grid-cols-1 gap-3">
                <a id="btn-tenant-create-order" href="{$modulelink}&a=createorder" class="eb-btn eb-btn-primary eb-btn-sm w-full justify-start">
                    Create Backup Account
                </a>
                <a id="btn-tenant-branding" href="#" class="eb-btn eb-btn-secondary eb-btn-sm w-full justify-start">
                    Manage Branding
                </a>
                <a id="btn-tenant-signup-settings" href="#" class="eb-btn eb-btn-secondary eb-btn-sm w-full justify-start">
                    Registration Page
                </a>
                <a id="btn-tenant-email-templates" href="#" class="eb-btn eb-btn-secondary eb-btn-sm w-full justify-start">
                    Email Templates
                </a>
            </div>
            <div class="text-xs text-[var(--eb-text-muted)]">Choose which area to manage for this tenant.</div>
        </div>
    </div>
</div>

<script>
(function(){
  try {
    var modulelink = '{$modulelink}';
    var panel = document.getElementById('tenant-slide-panel');
    var closeBtn = document.getElementById('tenant-panel-close');
    var btnSignup = document.getElementById('btn-tenant-signup-settings');
    var btnBrand = document.getElementById('btn-tenant-branding');
    var btnEmail = document.getElementById('btn-tenant-email-templates');
    var elFqdn = document.getElementById('tenant-panel-fqdn');
    var elCustom = document.getElementById('tenant-panel-custom');
    function openPanel(tenantTid, fqdn, custom){
      try {
        if (elFqdn) elFqdn.textContent = fqdn || '';
        if (elCustom) elCustom.textContent = custom || '-';
        if (btnSignup) btnSignup.href = modulelink + '&a=whitelabel-signup-settings&tid=' + encodeURIComponent(String(tenantTid||''));
        if (btnBrand) btnBrand.href = modulelink + '&a=whitelabel-branding&tid=' + encodeURIComponent(String(tenantTid||''));
        if (btnEmail) btnEmail.href = modulelink + '&a=whitelabel-email-templates&tid=' + encodeURIComponent(String(tenantTid||''));
        panel.classList.remove('translate-x-full');
        panel.classList.add('translate-x-0');
      } catch(e) {}
    }
    function closePanel(){
      try { panel.classList.add('translate-x-full'); panel.classList.remove('translate-x-0'); } catch(e) {}
    }
    if (closeBtn) closeBtn.addEventListener('click', function(){ closePanel(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closePanel(); });
    var buttons = document.querySelectorAll('.btn-tenant-manage');
    buttons.forEach(function(b){
      b.addEventListener('click', function(){
        var tid = this.getAttribute('data-tenant-tid');
        var fqdn = this.getAttribute('data-fqdn') || '';
        var custom = this.getAttribute('data-custom-domain') || '';
        openPanel(tid, fqdn, custom);
      });
    });
  } catch(e) {}
})();
</script>
